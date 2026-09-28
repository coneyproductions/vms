<?php
defined('ABSPATH') || exit;

final class BVMGR_Admission_Offer_Native_Complimentary_Provider implements BVMGR_Admission_Offer_Fulfillment_Provider_Interface
{
	/** @var object */
	private $db;
	/** @var callable|null */
	private $interrupt;

	/** @param object|null $db */
	public function __construct($db = null, ?callable $interrupt = null)
	{
		if ($db === null) {
			global $wpdb;
			$db = $wpdb;
		}
		$this->db = $db;
		$this->interrupt = $interrupt;
	}

	public function provider_key(): string
	{
		return 'bvm_native_comp';
	}

	/** @return array<string,mixed> */
	public function fulfill_claim(array $claim, array $context): array
	{
		if ((string) ($context['offer_type'] ?? '') !== 'complimentary') {
			throw new BVMGR_Admission_Offer_Domain_Exception('complimentary_provider_offer_type_rejected');
		}
		$claim_id = (int) ($claim['id'] ?? 0);
		$claim_public_id = (string) ($claim['public_id'] ?? '');
		$event_plan_id = (int) ($claim['event_plan_id'] ?? 0);
		$quantity = (int) ($claim['quantity'] ?? 0);
		if ($claim_id < 1 || !preg_match('/^ac_[a-f0-9]{32}$/', $claim_public_id) || $event_plan_id < 1 || $quantity < 1) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_native_fulfillment_claim');
		}
		$plan = bvmgr_admission_event_plan_context($event_plan_id);
		if (!is_array($plan) || (int) ($plan['venue_id'] ?? 0) < 1) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_native_fulfillment_event');
		}
		$fulfillments = bvmgr_admission_offers_table('fulfillments');
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reconciliation reads request-fresh expected unit rows.
		$units = $this->db->get_results($this->db->prepare('SELECT * FROM %i WHERE claim_id = %d AND fulfillment_provider = %s ORDER BY provider_ref ASC', $fulfillments, $claim_id, $this->provider_key()), ARRAY_A);
		if (!is_array($units) || count($units) !== $quantity) {
			throw new BVMGR_Admission_Offer_Domain_Exception('native_fulfillment_units_incomplete');
		}
		$entry_ids = array();
		foreach ($units as $index => $unit) {
			$provider_ref = (string) ($unit['provider_ref'] ?? '');
			$expected_ref = $claim_public_id . ':unit:' . ($index + 1);
			if ($provider_ref !== $expected_ref) {
				throw new BVMGR_Admission_Offer_Domain_Exception('native_fulfillment_unit_mismatch');
			}
			// Each unit is a short transaction. The fulfillment row lock prevents two
			// retrying processes from issuing the same deterministic unit together.
			if ($this->db->query('START TRANSACTION') === false) throw new BVMGR_Admission_Offer_Domain_Exception('native_fulfillment_transaction_start_failed');
			try {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Serialize reconciliation for this expected unit.
				$locked = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE id = %d AND claim_id = %d FOR UPDATE', $fulfillments, (int) $unit['id'], $claim_id), ARRAY_A);
				if (!is_array($locked)) throw new BVMGR_Admission_Offer_Domain_Exception('native_fulfillment_unit_missing');
				$entry = null;
				if ((int) ($locked['credential_ref'] ?? 0) > 0) {
					$entry = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE id = %d', bvmgr_admission_table_entries(), (int) $locked['credential_ref']), ARRAY_A);
				}
				if (!is_array($entry)) $entry = $this->find_or_create_entry($claim, $locked, $plan, $index + 1, $context);
				$entry_id = (int) ($entry['id'] ?? 0);
				$token = bvmgr_admission_ensure_entry_token($entry_id);
				if ($entry_id < 1 || $token === '') throw new BVMGR_Admission_Offer_Domain_Exception('native_admission_token_failed');
				$this->interrupt('after_admission_create', array('claim_id' => $claim_id, 'unit' => $index + 1, 'entry_id' => $entry_id));
				$now = bvmgr_admission_now_mysql();
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Deterministic credential linkage completes one plugin-owned expected fulfillment row.
				$updated = $this->db->query($this->db->prepare("UPDATE %i SET state = 'fulfilled', credential_ref = %s, fulfilled_at = COALESCE(fulfilled_at, %s), updated_at = %s, state_version = state_version + 1 WHERE id = %d AND claim_id = %d AND state IN ('pending','fulfilled') AND (credential_ref IS NULL OR credential_ref = %s)", $fulfillments, (string) $entry_id, $now, $now, (int) $locked['id'], $claim_id, (string) $entry_id));
				if ($updated === false) throw new BVMGR_Admission_Offer_Domain_Exception('native_fulfillment_link_failed');
				$this->interrupt('after_fulfillment_link', array('claim_id' => $claim_id, 'unit' => $index + 1, 'entry_id' => $entry_id));
				if ($this->db->query('COMMIT') === false) throw new BVMGR_Admission_Offer_Domain_Exception('native_fulfillment_transaction_commit_failed');
				$entry_ids[] = $entry_id;
				$this->interrupt('after_unit_commit', array('claim_id' => $claim_id, 'unit' => $index + 1, 'entry_id' => $entry_id));
			} catch (Throwable $error) {
				$this->db->query('ROLLBACK');
				throw $error;
			}
		}
		return array('provider' => $this->provider_key(), 'entry_ids' => $entry_ids, 'quantity' => count($entry_ids));
	}

	/** @param array<string,mixed> $claim @param array<string,mixed> $unit @param array<string,mixed> $plan @param array<string,mixed> $context @return array<string,mixed> */
	private function find_or_create_entry(array $claim, array $unit, array $plan, int $slot, array $context): array
	{
		$entries = bvmgr_admission_table_entries();
		$provider_ref = (string) $unit['provider_ref'];
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Provider reconciliation must find an admission created before an interrupted linkage write.
		$row = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE source = %s AND claim_reference = %s LIMIT 1', $entries, 'admission_offer', $provider_ref), ARRAY_A);
		if (is_array($row)) {
			return $row;
		}
		$name = trim((string) ($claim['claimant_first_name'] ?? '') . ' ' . (string) ($claim['claimant_last_name'] ?? ''));
		if ($name === '') {
			$name = __('Admission Offer Guest', 'backstage-venue-manager');
		}
		$email = sanitize_email((string) ($claim['claimant_email'] ?? ''));
		$phone = sanitize_text_field((string) ($claim['claimant_phone'] ?? ''));
		$meta = array('admission_offer' => array(
			'offer_id' => (int) ($claim['offer_id'] ?? 0),
			'claim_id' => (int) $claim['id'],
			'claim_public_id' => (string) $claim['public_id'],
			'fulfillment_id' => (int) $unit['id'],
			'fulfillment_public_id' => (string) $unit['public_id'],
			'unit' => $slot,
			'quantity' => (int) $claim['quantity'],
		));
		$data = array(
			'event_plan_id' => (int) $claim['event_plan_id'], 'venue_id' => (int) $plan['venue_id'],
			'admission_kind' => 'comp', 'source' => 'admission_offer', 'owner_vendor_id' => null,
			'guest_name' => $name, 'guest_name_norm' => bvmgr_admission_normalize_name($name),
			'guest_email' => $email !== '' ? $email : null, 'guest_email_norm' => $email !== '' ? bvmgr_admission_normalize_email($email) : null,
			'party_size' => 1, 'checked_in_qty' => 0, 'phone' => $phone !== '' ? $phone : null,
			'phone_norm' => $phone !== '' ? bvmgr_admission_normalize_phone($phone) : null,
			'notes' => __('Complimentary Admission Offer.', 'backstage-venue-manager'), 'status' => 'active',
			'claim_reference' => $provider_ref, 'claim_meta' => wp_json_encode($meta),
			'created_by' => (int) ($context['actor_user_id'] ?? 0), 'created_at' => bvmgr_admission_now_mysql(),
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Native provider writes the existing BVM-owned admissions authority table.
		if ($this->db->insert($entries, $data, array('%d','%d','%s','%s','%d','%s','%s','%s','%s','%d','%d','%s','%s','%s','%s','%s','%s','%d','%s')) === false) {
			throw new BVMGR_Admission_Offer_Domain_Exception('native_admission_insert_failed');
		}
		$entry_id = (int) $this->db->insert_id;
		bvmgr_admission_audit_log((int) $claim['event_plan_id'], $entry_id, 'admission_offer_create', (int) ($context['actor_user_id'] ?? 0), 'admission_offer', array('claim_id' => (int) $claim['id'], 'fulfillment_id' => (int) $unit['id']));
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Return the just-created native authority row.
		return (array) $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE id = %d', $entries, $entry_id), ARRAY_A);
	}

	/** @param array<string,mixed> $context */
	private function interrupt(string $point, array $context): void
	{
		if ($this->interrupt !== null) {
			($this->interrupt)($point, $context);
		}
	}
}
