<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}
if (!defined('ARRAY_A')) {
	define('ARRAY_A', 'ARRAY_A');
}

final class BVMGR_Admission_Offer_Repository_Test_DB
{
	public string $prefix = 'wp_';
	public int $insert_id = 0;
	public int $last_errno = 0;
	public string $last_error = '';
	/** @var string[] */
	public array $queries = array();
	/** @var array<string,array<int,array<string,mixed>>> */
	public array $rows = array();
	/** @var array<string,bool> */
	private array $unique = array();

	public function insert(string $table, array $data, array $formats)
	{
		if (count($data) !== count($formats)) {
			throw new RuntimeException('Repository format count mismatch for ' . $table);
		}
		$unique_key = null;
		if (str_ends_with($table, 'vms_admission_offers')) {
			$unique_key = $table . ':public:' . $data['public_id'];
		} elseif (str_ends_with($table, 'vms_admission_offer_claims')) {
			$unique_key = $table . ':public:' . $data['public_id'];
			$secret_key = $table . ':secret:' . $data['access_secret_hash'];
			if (isset($this->unique[$secret_key])) return false;
			$this->unique[$secret_key] = true;
		} elseif (str_ends_with($table, 'vms_admission_offer_claim_identities')) {
			$unique_key = $table . ':' . implode(':', array($data['offer_id'], $data['identity_scope_key'], $data['identity_type'], $data['hash_key_version'], $data['identity_hash']));
		}
		if ($unique_key !== null && isset($this->unique[$unique_key])) {
			return false;
		}
		if ($unique_key !== null) $this->unique[$unique_key] = true;
		$this->insert_id++;
		$data['id'] = $this->insert_id;
		$this->rows[$table][] = $data;
		return 1;
	}

	public function prepare(string $query, ...$args): string
	{
		$this->queries[] = $query;
		return 'TESTPREP:' . base64_encode(serialize(array($query, $args)));
	}

	public function query(string $prepared)
	{
		$this->queries[] = $prepared;
		if (in_array($prepared, array('START TRANSACTION', 'COMMIT', 'ROLLBACK'), true)) return 1;
		if (!str_starts_with($prepared, 'TESTPREP:')) return false;
		[$query, $args] = unserialize(base64_decode(substr($prepared, 9)), array('allowed_classes' => false));
		if (str_starts_with($query, 'UPDATE %i SET')) return 1;
		if (!str_starts_with($query, 'INSERT INTO')) return false;
		$table = (string) $args[0];
		$key = $table . ':' . $args[1] . ':' . $args[2] . ':' . $args[3];
		if (isset($this->unique[$key])) {
			$this->insert_id = (int) $this->unique[$key];
			return 0;
		}
		$this->insert_id++;
		$this->unique[$key] = $this->insert_id;
		$this->rows[$table][] = array(
			'id' => $this->insert_id,
			'entity_type' => $args[1],
			'entity_id' => $args[2],
			'event_key' => $args[3],
			'payload_redacted' => $args[9],
		);
		return 1;
	}

	public function get_row(string $prepared, $output = null): ?array
	{
		[$query, $args] = $this->decode($prepared);
		$table = (string) $args[0];
		$id = (int) $args[1];
		foreach ($this->rows[$table] ?? array() as $row) {
			if ((int) ($row['id'] ?? 0) === $id) return $row;
		}
		return null;
	}

	/** @return array<int,array<string,mixed>> */
	public function get_results(string $prepared, $output = null): array
	{
		[$query, $args] = $this->decode($prepared);
		$table = (string) $args[0];
		$result = array();
		foreach ($this->rows[$table] ?? array() as $row) {
			if ((int) $row['offer_id'] === (int) $args[1]
				&& $row['identity_scope_key'] === $args[2]
				&& $row['identity_type'] === $args[3]) {
				$result[] = array('hash_key_version' => $row['hash_key_version'], 'identity_hash' => $row['identity_hash']);
			}
		}
		return $result;
	}

	/** @return array{0:string,1:array<int,mixed>} */
	private function decode(string $prepared): array
	{
		if (!str_starts_with($prepared, 'TESTPREP:')) throw new RuntimeException('Expected prepared query.');
		return unserialize(base64_decode(substr($prepared, 9)), array('allowed_classes' => false));
	}
}

$GLOBALS['wpdb'] = new BVMGR_Admission_Offer_Repository_Test_DB();
function wp_json_encode($value, int $flags = 0): string { return (string) json_encode($value, $flags); }

require_once dirname(__DIR__) . '/includes/modules/admission-offers/domain.php';
require_once dirname(__DIR__) . '/includes/modules/admission-offers/schema.php';
require_once dirname(__DIR__) . '/includes/modules/admission-offers/keys.php';
require_once dirname(__DIR__) . '/includes/modules/admission-offers/repositories.php';

final class BVMGR_Admission_Offer_Test_Keyring implements BVMGR_Admission_Offer_Identity_Keyring_Interface
{
	/** @param array<int,string> $keys */
	public function __construct(private array $ring, private int $active) {}
	public function active_version(): int { return $this->active; }
	public function keys(): array { return $this->ring; }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
	$assertions++;
	if (!$condition) throw new RuntimeException($message);
};
$rejects = static function (callable $callback, string $code) use ($assert): void {
	try { $callback(); } catch (BVMGR_Admission_Offer_Domain_Exception $error) {
		$assert($error->getMessage() === $code, 'Unexpected rejection: ' . $error->getMessage());
		return;
	}
	throw new RuntimeException('Expected rejection: ' . $code);
};

$db = $GLOBALS['wpdb'];
$offer_repo = new BVMGR_Admission_Offer_Repository($db);
$offer_input = array(
	'public_id' => 'ao_' . str_repeat('1', 32),
	'name' => 'Repository Offer',
	'offer_type' => 'complimentary',
	'capacity_total' => 2,
	'max_qty_per_claim' => 2,
	'identity_policy' => array(),
);
$offer = BVMGR_Admission_Offer_Value::from_array($offer_input);
$offer_id = $offer_repo->create($offer, 7, '2026-09-28 12:00:00');
$assert($offer_id === 1, 'Offer repository must return inserted ID.');
$rejects(static fn() => $offer_repo->create($offer, 7, '2026-09-28 12:00:01'), 'offer_insert_failed');

$claim_repo = new BVMGR_Admission_Offer_Claim_Repository($db);
$raw_secret = str_repeat('raw-secret-', 4);
$claim_id = $claim_repo->create(array(
	'offer_id' => $offer_id,
	'event_plan_id' => 55,
	'quantity' => 2,
	'claimant_email' => 'Guest@Example.com',
	'claimant_phone' => '+1 (615) 555-0100',
	'distribution_provider' => 'provider-x',
	'campaign_ref' => 'opaque-campaign-1',
), $raw_secret, '2026-09-28 12:01:00');
$claim_rows = $db->rows['wp_vms_admission_offer_claims'];
$claim = end($claim_rows);
$assert($claim_id > 0, 'Claim repository must insert Claim.');
$assert($claim['access_secret_hash'] === hash('sha256', "bvmgr-claim-access-v1\0" . $raw_secret), 'Claim access credential must use stable domain-separated hashing.');
$assert(!in_array($raw_secret, $claim, true), 'Raw Claim access secret must never be persisted.');
$assert($claim['claimant_email_norm'] === 'guest@example.com', 'Claim email normalization must be retained separately.');
$assert($claim['claimant_phone_norm'] === '16155550100', 'Claim phone normalization must be retained separately.');

$v1_key = str_repeat('1', 32);
$v2_key = str_repeat('2', 32);
$identity_repo = new BVMGR_Admission_Offer_Identity_Repository($db, new BVMGR_Admission_Offer_Test_Keyring(array(1 => $v1_key), 1), static function (): void {});
$first_identity = $identity_repo->reserve($claim_id, $offer_id, null, 'email', 'Guest@Example.com', '2026-09-28 12:02:00');
$assert($first_identity > 0, 'First scoped identity must be accepted.');
$rotated_repo = new BVMGR_Admission_Offer_Identity_Repository($db, new BVMGR_Admission_Offer_Test_Keyring(array(1 => $v1_key, 2 => $v2_key), 2), static function (): void {});
$rejects(static fn() => $rotated_repo->reserve($claim_id, $offer_id, 'offer', 'email', 'guest@example.com', '2026-09-28 12:02:01'), 'duplicate_or_invalid_scoped_identity');
$other_scope_id = $rotated_repo->reserve($claim_id, $offer_id, 'provider:campaign:2', 'email', 'guest@example.com', '2026-09-28 12:02:02');
$assert($other_scope_id > $first_identity, 'Same identity under a different approved scope must be allowed.');
$identity_rows = $db->rows['wp_vms_admission_offer_claim_identities'];
$assert(!in_array('guest@example.com', end($identity_rows), true), 'Identity enforcement table must not store normalized raw identity.');
$assert($identity_rows[0]['hash_key_version'] === 1 && end($identity_rows)['hash_key_version'] === 2, 'Identity enforcement rows must retain the key version used at insertion.');
$missing_key_repo = new BVMGR_Admission_Offer_Identity_Repository($db, new BVMGR_Admission_Offer_Test_Keyring(array(2 => $v2_key), 2), static function (): void {});
$rejects(static fn() => $missing_key_repo->reserve($claim_id, $offer_id, 'offer', 'email', 'different@example.com', '2026-09-28 12:02:03'), 'identity_key_version_missing');
$lock_log = implode("\n", $db->queries);
$assert(strpos($lock_log, 'SELECT id FROM %i') < strpos($lock_log, 'SELECT id, offer_id FROM %i'), 'Identity transaction must lock Offer before Claim.');

$checkout_repo = new BVMGR_Admission_Offer_Checkout_Repository($db);
$rejects(static fn() => $checkout_repo->create(array(
	'offer_id' => $offer_id,
	'claim_id' => $claim_id,
	'currency' => 'USD',
	'eligible_subtotal_minor' => 1000,
	'discount_minor' => 1000,
	'total_minor' => 0,
)), 'invalid_checkout_minor_units');
$rejects(static fn() => $checkout_repo->create(array(
	'offer_id' => $offer_id,
	'claim_id' => $claim_id,
	'currency' => 'USD',
	'eligible_subtotal_minor' => 1000.5,
	'discount_minor' => 100,
	'total_minor' => 900,
)), 'invalid_checkout_minor_units');
$checkout_id = $checkout_repo->create(array(
	'offer_id' => $offer_id,
	'claim_id' => $claim_id,
	'currency' => 'USD',
	'eligible_subtotal_minor' => 1000,
	'discount_minor' => 250,
	'total_minor' => 750,
	'checkout_provider' => 'future-commerce',
	'checkout_ref' => 'checkout-ref-1',
), '2026-09-28 12:02:30');
$assert($checkout_id > 0, 'Checkout repository must persist provider-neutral minor-unit state.');

$allocation_repo = new BVMGR_Admission_Offer_Order_Allocation_Repository($db);
$allocation_id = $allocation_repo->create(array(
	'checkout_id' => $checkout_id,
	'claim_id' => $claim_id,
	'offer_id' => $offer_id,
	'event_plan_id' => 55,
	'order_provider' => 'future-commerce',
	'order_ref' => 'order-1',
	'order_item_ref' => 'line-1',
	'product_ref' => 'product-1',
	'quantity' => 2,
	'eligible_subtotal_minor' => 1000,
	'discount_minor' => 250,
	'currency' => 'USD',
), '2026-09-28 12:02:31');
$assert($allocation_id > 0, 'Order allocation repository must persist opaque external references.');

$fulfillment_repo = new BVMGR_Admission_Offer_Fulfillment_Repository($db);
$fulfillment_id = $fulfillment_repo->create_pending(array(
	'offer_id' => $offer_id,
	'claim_id' => $claim_id,
	'event_plan_id' => 55,
	'quantity' => 2,
	'fulfillment_provider' => 'future-provider',
	'provider_ref' => 'pending-1',
), '2026-09-28 12:02:32');
$assert($fulfillment_id > 0, 'Fulfillment repository must create pending metadata without issuing a credential.');
$fulfillment_rows = $db->rows['wp_vms_admission_offer_fulfillments'];
$assert(end($fulfillment_rows)['credential_ref'] === null, 'Pending foundation must not create a credential reference.');

$event_repo = new BVMGR_Admission_Offer_Event_Repository($db);
$event = array(
	'entity_type' => 'claim',
	'entity_id' => $claim_id,
	'event_key' => 'claim-created:unit-test',
	'new_state' => 'claimed',
	'payload' => array('email' => 'guest@example.com', 'safe_count' => 2, 'nested' => array('token' => 'raw-token')),
	'occurred_at' => '2026-09-28 12:03:00',
);
$first_event = $event_repo->append($event, '2026-09-28 12:03:00');
$retry = $event;
$retry['new_state'] = 'fulfilled';
$retry['payload'] = array('safe_count' => 999, 'email' => 'replacement@example.com');
$retry_event = $event_repo->append($retry, '2026-09-28 12:03:01');
$assert($first_event > 0 && $retry_event === $first_event, 'Domain event retry must return the original idempotent event ID.');
$event_rows = $db->rows['wp_vms_admission_offer_events'];
$assert(count($event_rows) === 1, 'Duplicate event key must retain one immutable event row.');
$payload = json_decode((string) end($event_rows)['payload_redacted'], true);
$assert($payload['email'] === '[redacted]' && $payload['nested']['token'] === '[redacted]', 'Domain event payload must redact identity and secrets.');
$assert($payload['safe_count'] === 2, 'Domain event payload may preserve safe audit context.');
$rejects(static fn() => bvmgr_admission_offer_transition_entity($db, 'checkouts', $checkout_id, 'payment_pending', 'paid', 1), 'provider_authority_required');
$claim_transition_query_offset = count($db->queries);
$assert(bvmgr_admission_offer_transition_entity($db, 'claims', $claim_id, 'claimed', 'canceled', 1, 7, '2026-09-28 12:04:00'), 'Generic Claim transition must succeed through the status column.');
$claim_transition_queries = array_slice($db->queries, $claim_transition_query_offset);
$claim_transition_sql = implode("\n", $claim_transition_queries);
$assert(str_contains($claim_transition_sql, 'SET status = %s'), 'Claim transition must write status.');
$assert(str_contains($claim_transition_sql, 'AND status = %s'), 'Claim transition must compare current status.');
$assert(!str_contains($claim_transition_sql, 'SET state = %s') && !str_contains($claim_transition_sql, 'AND state = %s'), 'Claim transition must never reference a nonexistent Claim state column.');

foreach (array_keys($db->rows) as $table) {
	$assert(!str_contains($table, 'vms_pass_'), 'Admission Offers repository must not write legacy pass tables.');
	$assert(!str_ends_with($table, 'vms_admission_entries'), 'Admission Offers repository must not create native admissions.');
}

echo "Admission Offers repositories: PASS ({$assertions} assertions)\n";
