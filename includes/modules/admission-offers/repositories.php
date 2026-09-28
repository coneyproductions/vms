<?php
defined('ABSPATH') || exit;

abstract class BVMGR_Admission_Offer_Repository_Base
{
	/** @var object */
	protected $db;

	/** @param object $db */
	public function __construct($db = null)
	{
		if ($db === null) {
			global $wpdb;
			$db = $wpdb;
		}
		$this->db = $db;
	}

	/** @param array<string,mixed> $data @param string[] $formats */
	protected function insert_or_throw(string $table, array $data, array $formats, string $error_code): int
	{
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Repository writes a plugin-owned Admission Offers table.
		$result = $this->db->insert($table, $data, $formats);
		if ($result === false) {
			throw new BVMGR_Admission_Offer_Domain_Exception($error_code);
		}

		return (int) $this->db->insert_id;
	}

	protected function now(?string $now = null): string
	{
		if ($now !== null && $now !== '') {
			return $now;
		}
		return function_exists('current_time') ? (string) current_time('mysql', true) : gmdate('Y-m-d H:i:s');
	}
}

final class BVMGR_Admission_Offer_Repository extends BVMGR_Admission_Offer_Repository_Base
{
	public function create(BVMGR_Admission_Offer_Value $offer, int $actor_user_id, ?string $now = null): int
	{
		$data = $offer->to_array();
		$data['created_by'] = $actor_user_id;
		$data['created_at'] = $this->now($now);
		$data['state_version'] = 1;

		return $this->insert_or_throw(
			bvmgr_admission_offers_table('offers'),
			$data,
			array('%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%d'),
			'offer_insert_failed'
		);
	}
}

final class BVMGR_Admission_Offer_Eligibility_Repository extends BVMGR_Admission_Offer_Repository_Base
{
	public function create(int $offer_id, BVMGR_Admission_Offer_Eligibility_Value $eligibility, int $actor_user_id, ?string $now = null): int
	{
		if ($offer_id < 1) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_offer_id');
		}
		$data = $eligibility->to_array();
		$data = array_merge(array('offer_id' => $offer_id), $data, array(
			'created_by' => $actor_user_id,
			'created_at' => $this->now($now),
		));

		return $this->insert_or_throw(
			bvmgr_admission_offers_table('eligibility'),
			$data,
			array('%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s'),
			'eligibility_insert_failed'
		);
	}
}

final class BVMGR_Admission_Offer_Claim_Repository extends BVMGR_Admission_Offer_Repository_Base
{
	/** @param array<string,mixed> $input */
	public function create(array $input, string $access_secret, ?string $now = null): int
	{
		$offer_id = (int) ($input['offer_id'] ?? 0);
		$event_plan_id = (int) ($input['event_plan_id'] ?? 0);
		$quantity = (int) ($input['quantity'] ?? 0);
		if ($offer_id < 1 || $event_plan_id < 1 || $quantity < 1 || $quantity > 65535) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_claim');
		}

		$email = trim((string) ($input['claimant_email'] ?? ''));
		$email_norm = $email !== '' ? strtolower($email) : null;
		if ($email_norm !== null && !filter_var($email_norm, FILTER_VALIDATE_EMAIL)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_claim_email');
		}
		$phone = trim((string) ($input['claimant_phone'] ?? ''));
		$phone_norm = null;
		if ($phone !== '') {
			$phone_norm = bvmgr_admission_offer_normalize_identity('phone', $phone);
		}

		$public_id = isset($input['public_id']) ? trim((string) $input['public_id']) : bvmgr_admission_offer_generate_public_id('ac');
		if (!preg_match('/^ac_[a-f0-9]{32}$/', $public_id)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_claim_public_id');
		}
		$data = array(
			'public_id' => $public_id,
			'offer_id' => $offer_id,
			'event_plan_id' => $event_plan_id,
			'quantity' => $quantity,
			'status' => 'claimed',
			'claimant_first_name' => bvmgr_admission_offer_bounded_text($input['claimant_first_name'] ?? '', 120),
			'claimant_last_name' => bvmgr_admission_offer_bounded_text($input['claimant_last_name'] ?? '', 120),
			'claimant_email' => $email !== '' ? $email : null,
			'claimant_email_norm' => $email_norm,
			'claimant_phone' => $phone !== '' ? $phone : null,
			'claimant_phone_norm' => $phone_norm,
			'claimant_account_id' => isset($input['claimant_account_id']) && (int) $input['claimant_account_id'] > 0 ? (int) $input['claimant_account_id'] : null,
			'attribution_source' => bvmgr_admission_offer_nullable_text($input['attribution_source'] ?? null, 120),
			'distribution_provider' => bvmgr_admission_offer_nullable_key($input['distribution_provider'] ?? null, 80),
			'distribution_mode' => bvmgr_admission_offer_nullable_key($input['distribution_mode'] ?? null, 40),
			'campaign_ref' => bvmgr_admission_offer_nullable_text($input['campaign_ref'] ?? null, 190),
			'distribution_subject_ref' => bvmgr_admission_offer_nullable_text($input['distribution_subject_ref'] ?? null, 190),
			'access_secret_hash' => bvmgr_admission_offer_hash_secret($access_secret),
			'expires_at' => bvmgr_admission_offer_nullable_datetime($input['expires_at'] ?? null),
			'state_version' => 1,
			'created_by' => isset($input['created_by']) && (int) $input['created_by'] > 0 ? (int) $input['created_by'] : null,
			'created_at' => $this->now($now),
		);

		return $this->insert_or_throw(
			bvmgr_admission_offers_table('claims'),
			$data,
			array('%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s'),
			'claim_insert_failed'
		);
	}
}

final class BVMGR_Admission_Offer_Identity_Repository extends BVMGR_Admission_Offer_Repository_Base
{
	private BVMGR_Admission_Offer_Identity_Keyring_Interface $keyring;
	/** @var callable */
	private $retry_delay;

	/** @param object|null $db */
	public function __construct($db = null, ?BVMGR_Admission_Offer_Identity_Keyring_Interface $keyring = null, ?callable $retry_delay = null)
	{
		parent::__construct($db);
		$this->keyring = $keyring ?? BVMGR_Admission_Offer_Identity_Keyring::load_or_create($this->db);
		$this->retry_delay = $retry_delay ?? static function (int $attempt): void {
			usleep($attempt * 25000);
		};
	}

	public function reserve(
		int $claim_id,
		int $offer_id,
		?string $identity_scope_key,
		string $identity_type,
		string $identity_value,
		?string $now = null
	): int {
		if ($claim_id < 1 || $offer_id < 1) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_identity_owner');
		}
		$scope_key = bvmgr_admission_offer_normalize_scope_key($identity_scope_key);
		$identity_type = strtolower(trim($identity_type));
		$normalized = bvmgr_admission_offer_normalize_identity($identity_type, $identity_value);
		$keys = $this->keyring->keys();
		$active_version = $this->keyring->active_version();
		if (!isset($keys[$active_version])) {
			throw new BVMGR_Admission_Offer_Domain_Exception('identity_keyring_corrupt');
		}

		for ($attempt = 1; $attempt <= 3; $attempt++) {
			$begun = false;
			try {
				$this->transaction_query('START TRANSACTION', 'identity_transaction_start_failed');
				$begun = true;
				// Canonical order for every Admission Offers transaction:
				// Offer -> Claim -> Reservation/Identity -> state/event write.
				$offers = bvmgr_admission_offers_table('offers');
				$claims = bvmgr_admission_offers_table('claims');
				$identities = bvmgr_admission_offers_table('identities');
				$offer = $this->db->get_row($this->db->prepare('SELECT id FROM %i WHERE id = %d FOR UPDATE', $offers, $offer_id), ARRAY_A);
				if (!is_array($offer)) {
					throw new BVMGR_Admission_Offer_Domain_Exception('invalid_identity_owner');
				}
				$claim = $this->db->get_row($this->db->prepare('SELECT id, offer_id FROM %i WHERE id = %d FOR UPDATE', $claims, $claim_id), ARRAY_A);
				if (!is_array($claim) || (int) ($claim['offer_id'] ?? 0) !== $offer_id) {
					throw new BVMGR_Admission_Offer_Domain_Exception('invalid_identity_owner');
				}
				$rows = $this->db->get_results($this->db->prepare('SELECT hash_key_version, identity_hash FROM %i WHERE offer_id = %d AND identity_scope_key = %s AND identity_type = %s FOR UPDATE', $identities, $offer_id, $scope_key, $identity_type), ARRAY_A);
				if (!is_array($rows)) {
					$this->throw_db_error('identity_lookup_failed');
				}
				$candidates = array();
				foreach ($keys as $version => $key) {
					$candidates[(int) $version] = bvmgr_admission_offer_identity_hash($scope_key, $identity_type, $normalized, $key);
				}
				foreach ($rows as $row) {
					$version = (int) ($row['hash_key_version'] ?? 0);
					if (!isset($keys[$version])) {
						throw new BVMGR_Admission_Offer_Domain_Exception('identity_key_version_missing');
					}
					if (hash_equals($candidates[$version], (string) ($row['identity_hash'] ?? ''))) {
						throw new BVMGR_Admission_Offer_Domain_Exception('duplicate_or_invalid_scoped_identity');
					}
				}
				$data = array(
					'claim_id' => $claim_id,
					'offer_id' => $offer_id,
					'identity_scope_key' => $scope_key,
					'identity_type' => $identity_type,
					'hash_key_version' => $active_version,
					'identity_hash' => $candidates[$active_version],
					'created_at' => $this->now($now),
				);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Serialized insert into the plugin-owned identity enforcement table.
				if ($this->db->insert($identities, $data, array('%d', '%d', '%s', '%s', '%d', '%s', '%s')) === false) {
					$this->throw_db_error('duplicate_or_invalid_scoped_identity');
				}
				$id = (int) $this->db->insert_id;
				$this->transaction_query('COMMIT', 'identity_transaction_commit_failed');
				$begun = false;
				return $id;
			} catch (Throwable $error) {
				if ($begun) {
					$this->transaction_query('ROLLBACK', 'identity_transaction_rollback_failed');
				}
				if ($error instanceof BVMGR_Admission_Offer_Transient_Transaction_Exception && $attempt < 3) {
					($this->retry_delay)($attempt);
					continue;
				}
				throw $error;
			}
		}
		throw new BVMGR_Admission_Offer_Domain_Exception('identity_transaction_retry_exhausted');
	}

	private function transaction_query(string $sql, string $error_code): void
	{
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Explicit InnoDB transaction boundary.
		if ($this->db->query($sql) === false) {
			$this->throw_db_error($error_code);
		}
	}

	private function throw_db_error(string $error_code): void
	{
		if (bvmgr_admission_offer_db_error_is_transient($this->db)) {
			throw new BVMGR_Admission_Offer_Transient_Transaction_Exception($error_code);
		}
		throw new BVMGR_Admission_Offer_Domain_Exception($error_code);
	}
}

final class BVMGR_Admission_Offer_Checkout_Repository extends BVMGR_Admission_Offer_Repository_Base
{
	/** @param array<string,mixed> $input */
	public function create(array $input, ?string $now = null): int
	{
		$eligible = bvmgr_admission_offer_integer_value($input['eligible_subtotal_minor'] ?? 0, 'invalid_checkout_minor_units');
		$discount = bvmgr_admission_offer_integer_value($input['discount_minor'] ?? -1, 'invalid_checkout_minor_units');
		$total = bvmgr_admission_offer_integer_value($input['total_minor'] ?? 0, 'invalid_checkout_minor_units');
		$currency = strtoupper(trim((string) ($input['currency'] ?? '')));
		if ((int) ($input['offer_id'] ?? 0) < 1 || (int) ($input['claim_id'] ?? 0) < 1 || !preg_match('/^[A-Z]{3}$/', $currency)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_checkout');
		}
		if ($eligible < 1 || $discount < 0 || $discount >= $eligible || $total !== ($eligible - $discount) || $total < 1) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_checkout_minor_units');
		}

		$data = array(
			'public_id' => bvmgr_admission_offer_generate_public_id('ax'),
			'offer_id' => (int) $input['offer_id'],
			'claim_id' => (int) $input['claim_id'],
			'reservation_id' => isset($input['reservation_id']) && (int) $input['reservation_id'] > 0 ? (int) $input['reservation_id'] : null,
			'state' => 'checkout_started',
			'checkout_provider' => bvmgr_admission_offer_nullable_key($input['checkout_provider'] ?? null, 80),
			'checkout_ref' => bvmgr_admission_offer_nullable_text($input['checkout_ref'] ?? null, 190),
			'order_provider' => null,
			'order_ref' => null,
			'currency' => $currency,
			'eligible_subtotal_minor' => $eligible,
			'discount_minor' => $discount,
			'total_minor' => $total,
			'state_version' => 1,
			'created_at' => $this->now($now),
		);

		return $this->insert_or_throw(
			bvmgr_admission_offers_table('checkouts'),
			$data,
			array('%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s'),
			'checkout_insert_failed'
		);
	}
}

final class BVMGR_Admission_Offer_Order_Allocation_Repository extends BVMGR_Admission_Offer_Repository_Base
{
	/** @param array<string,mixed> $input */
	public function create(array $input, ?string $now = null): int
	{
		$required_positive = array('checkout_id', 'claim_id', 'offer_id', 'event_plan_id', 'quantity');
		foreach ($required_positive as $key) {
			if ((int) ($input[$key] ?? 0) < 1) {
				throw new BVMGR_Admission_Offer_Domain_Exception('invalid_order_allocation');
			}
		}
		$currency = strtoupper(trim((string) ($input['currency'] ?? '')));
		if (!preg_match('/^[A-Z]{3}$/', $currency)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_allocation_currency');
		}
		$eligible = bvmgr_admission_offer_integer_value($input['eligible_subtotal_minor'] ?? 0, 'invalid_allocation_minor_units');
		$discount = bvmgr_admission_offer_integer_value($input['discount_minor'] ?? 0, 'invalid_allocation_minor_units');
		if ($eligible < 1 || $discount < 0 || $discount >= $eligible) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_allocation_minor_units');
		}
		$data = array(
			'checkout_id' => (int) $input['checkout_id'],
			'claim_id' => (int) $input['claim_id'],
			'offer_id' => (int) $input['offer_id'],
			'event_plan_id' => (int) $input['event_plan_id'],
			'order_provider' => bvmgr_admission_offer_required_key($input['order_provider'] ?? '', 80),
			'order_ref' => bvmgr_admission_offer_required_text($input['order_ref'] ?? '', 190),
			'order_item_ref' => bvmgr_admission_offer_required_text($input['order_item_ref'] ?? '', 190),
			'product_ref' => bvmgr_admission_offer_nullable_text($input['product_ref'] ?? null, 190),
			'quantity' => (int) $input['quantity'],
			'eligible_subtotal_minor' => $eligible,
			'discount_minor' => $discount,
			'currency' => $currency,
			'created_at' => $this->now($now),
		);

		return $this->insert_or_throw(
			bvmgr_admission_offers_table('allocations'),
			$data,
			array('%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s'),
			'order_allocation_insert_failed'
		);
	}
}

final class BVMGR_Admission_Offer_Fulfillment_Repository extends BVMGR_Admission_Offer_Repository_Base
{
	/** @param array<string,mixed> $input */
	public function create_pending(array $input, ?string $now = null): int
	{
		foreach (array('offer_id', 'claim_id', 'event_plan_id', 'quantity') as $key) {
			if ((int) ($input[$key] ?? 0) < 1) {
				throw new BVMGR_Admission_Offer_Domain_Exception('invalid_fulfillment');
			}
		}
		$data = array(
			'public_id' => bvmgr_admission_offer_generate_public_id('af'),
			'offer_id' => (int) $input['offer_id'],
			'claim_id' => (int) $input['claim_id'],
			'event_plan_id' => (int) $input['event_plan_id'],
			'quantity' => (int) $input['quantity'],
			'state' => 'pending',
			'fulfillment_provider' => bvmgr_admission_offer_required_key($input['fulfillment_provider'] ?? '', 80),
			'provider_ref' => bvmgr_admission_offer_nullable_text($input['provider_ref'] ?? null, 190),
			'credential_ref' => null,
			'state_version' => 1,
			'created_at' => $this->now($now),
		);

		return $this->insert_or_throw(
			bvmgr_admission_offers_table('fulfillments'),
			$data,
			array('%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s'),
			'fulfillment_insert_failed'
		);
	}
}

final class BVMGR_Admission_Offer_Event_Repository extends BVMGR_Admission_Offer_Repository_Base
{
	/** @param array<string,mixed> $event */
	public function append(array $event, ?string $now = null): int
	{
		$entity_type = bvmgr_admission_offer_required_key($event['entity_type'] ?? '', 32);
		$entity_id = (int) ($event['entity_id'] ?? 0);
		$event_key = bvmgr_admission_offer_required_text($event['event_key'] ?? '', 190);
		if ($entity_id < 1) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_event_entity');
		}
		$payload = bvmgr_admission_offer_redact_payload(is_array($event['payload'] ?? null) ? $event['payload'] : array());
		$table = bvmgr_admission_offers_table('events');
		$occurred_at = bvmgr_admission_offer_nullable_datetime($event['occurred_at'] ?? null) ?? $this->now($now);
		$created_at = $this->now($now);

		$sql = $this->db->prepare(
			'INSERT INTO %i (entity_type, entity_id, event_key, previous_state, new_state, actor_user_id, actor_type, provider, payload_redacted, occurred_at, created_at) VALUES (%s, %d, %s, %s, %s, %d, %s, %s, %s, %s, %s) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)',
			$table,
			$entity_type,
			$entity_id,
			$event_key,
			bvmgr_admission_offer_nullable_key($event['previous_state'] ?? null, 24),
			bvmgr_admission_offer_nullable_key($event['new_state'] ?? null, 24),
			isset($event['actor_user_id']) ? (int) $event['actor_user_id'] : 0,
			bvmgr_admission_offer_nullable_key($event['actor_type'] ?? null, 40),
			bvmgr_admission_offer_nullable_key($event['provider'] ?? null, 80),
			bvmgr_admission_offer_canonical_json_encode($payload),
			$occurred_at,
			$created_at
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Append-only plugin-owned domain ledger uses a no-content-change duplicate clause to return the original event ID on retry.
		$result = $this->db->query($sql);
		if ($result === false) {
			throw new BVMGR_Admission_Offer_Domain_Exception('event_append_failed');
		}

		return (int) $this->db->insert_id;
	}
}

if (!function_exists('bvmgr_admission_offer_transition_entity')) {
	function bvmgr_admission_offer_transition_entity($db, string $kind, int $id, string $from, string $to, int $expected_version, ?int $actor_user_id = null, ?string $now = null): bool
	{
		$entity_type = $kind === 'offers' ? 'offer' : rtrim($kind, 's');
		if (!in_array($kind, array('offers', 'claims', 'reservations', 'checkouts', 'fulfillments'), true)
			|| !bvmgr_admission_offer_state_transition_allowed($entity_type, $from, $to)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_state_transition');
		}
		if (in_array($to, array('paid', 'fulfilled', 'partially_used', 'used', 'refunded'), true)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('provider_authority_required');
		}
		$table = bvmgr_admission_offers_table($kind);
		$state_column = in_array($kind, array('offers', 'claims'), true) ? 'status' : 'state';
		$now = $now ?: (function_exists('current_time') ? (string) current_time('mysql', true) : gmdate('Y-m-d H:i:s'));
		$sql = "UPDATE %i SET {$state_column} = %s, state_version = state_version + 1, updated_at = %s";
		$args = array($table, $to, $now);
		if ($actor_user_id !== null && in_array($kind, array('offers', 'claims'), true)) {
			$sql .= ', updated_by = %d';
			$args[] = $actor_user_id;
		}
		$sql .= " WHERE id = %d AND {$state_column} = %s AND state_version = %d";
		$args[] = $id;
		$args[] = $from;
		$args[] = $expected_version;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Optimistic state transition targets a single plugin-owned row.
		$result = $db->query($db->prepare($sql, ...$args));

		return $result === 1;
	}
}

if (!function_exists('bvmgr_admission_offer_redact_payload')) {
	function bvmgr_admission_offer_redact_payload(array $payload): array
	{
		$redacted = array();
		foreach ($payload as $key => $value) {
			$key_text = strtolower((string) $key);
			if (preg_match('/(secret|token|password|credential|email|phone|address)/', $key_text)) {
				$redacted[$key] = '[redacted]';
			} elseif (is_array($value)) {
				$redacted[$key] = bvmgr_admission_offer_redact_payload($value);
			} elseif (is_scalar($value) || $value === null) {
				$redacted[$key] = $value;
			}
		}

		return $redacted;
	}
}

if (!function_exists('bvmgr_admission_offer_bounded_text')) {
	function bvmgr_admission_offer_bounded_text($value, int $max): string
	{
		$value = trim((string) $value);
		if (strlen($value) > $max) {
			throw new BVMGR_Admission_Offer_Domain_Exception('text_too_long');
		}
		return $value;
	}
}

if (!function_exists('bvmgr_admission_offer_nullable_text')) {
	function bvmgr_admission_offer_nullable_text($value, int $max): ?string
	{
		$value = bvmgr_admission_offer_bounded_text($value ?? '', $max);
		return $value !== '' ? $value : null;
	}
}

if (!function_exists('bvmgr_admission_offer_required_text')) {
	function bvmgr_admission_offer_required_text($value, int $max): string
	{
		$value = bvmgr_admission_offer_bounded_text($value, $max);
		if ($value === '') {
			throw new BVMGR_Admission_Offer_Domain_Exception('required_text_missing');
		}
		return $value;
	}
}

if (!function_exists('bvmgr_admission_offer_nullable_key')) {
	function bvmgr_admission_offer_nullable_key($value, int $max): ?string
	{
		$value = strtolower(trim((string) ($value ?? '')));
		if ($value === '') {
			return null;
		}
		if (strlen($value) > $max || !preg_match('/^[a-z0-9][a-z0-9._:-]*$/', $value)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_provider_key');
		}
		return $value;
	}
}

if (!function_exists('bvmgr_admission_offer_required_key')) {
	function bvmgr_admission_offer_required_key($value, int $max): string
	{
		$value = bvmgr_admission_offer_nullable_key($value, $max);
		if ($value === null) {
			throw new BVMGR_Admission_Offer_Domain_Exception('required_key_missing');
		}
		return $value;
	}
}
