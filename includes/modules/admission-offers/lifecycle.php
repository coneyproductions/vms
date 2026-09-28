<?php
defined('ABSPATH') || exit;

final class BVMGR_Admission_Offer_Lifecycle_Service
{
	/** @var object */
	private $db;

	/** @param object|null $db */
	public function __construct($db = null)
	{
		if ($db === null) {
			global $wpdb;
			$db = $wpdb;
		}
		$this->db = $db;
	}

	public function synchronize_entry(int $entry_id, string $source_action = 'native_change', int $actor_user_id = 0): void
	{
		if ($entry_id < 1) return;
		$entries = bvmgr_admission_table_entries();
		$tables = bvmgr_admission_offers_table_names();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Lifecycle observation reads the request-fresh native authority row.
		$entry = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE id = %d', $entries, $entry_id), ARRAY_A);
		if (!is_array($entry) || (string) ($entry['source'] ?? '') !== 'admission_offer') return;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Credential reference is the neutral link from fulfillment to native admission.
		$fulfillment = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE fulfillment_provider = %s AND credential_ref = %s LIMIT 1', $tables['fulfillments'], 'bvm_native_comp', (string) $entry_id), ARRAY_A);
		if (!is_array($fulfillment)) return;
		$native_status = (string) ($entry['status'] ?? 'active');
		$checked = max(0, (int) ($entry['checked_in_qty'] ?? 0));
		$party = max(1, (int) ($entry['party_size'] ?? 1));
		$state = $native_status === 'canceled' ? 'revoked' : ($checked >= $party ? 'used' : ($checked > 0 ? 'partially_used' : 'fulfilled'));
		$previous = (string) ($fulfillment['state'] ?? '');
		if ($state !== $previous) {
			$now = gmdate('Y-m-d H:i:s');
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Fulfillment state is a derived projection of native admission authority.
			if ($this->db->query($this->db->prepare('UPDATE %i SET state = %s, state_version = state_version + 1, updated_at = %s WHERE id = %d', $tables['fulfillments'], $state, $now, (int) $fulfillment['id'])) === false) throw new BVMGR_Admission_Offer_Domain_Exception('fulfillment_lifecycle_sync_failed');
			$audit_id = (int) $this->db->get_var($this->db->prepare('SELECT MAX(id) FROM %i WHERE entry_id = %d', bvmgr_admission_table_audit(), $entry_id));
			(new BVMGR_Admission_Offer_Event_Repository($this->db))->append(array('entity_type' => 'fulfillment', 'entity_id' => (int) $fulfillment['id'], 'event_key' => 'native-' . max(1, $audit_id) . '-' . sanitize_key($source_action), 'previous_state' => $previous, 'new_state' => $state, 'actor_user_id' => $actor_user_id, 'actor_type' => 'native_admission', 'provider' => 'bvm_native_comp', 'payload' => array('entry_id' => $entry_id, 'checked_in_qty' => $checked)), $now);
		}
		$this->synchronize_claim((int) $fulfillment['claim_id'], $source_action . '-' . max(1, (int) ($audit_id ?? 0)), $actor_user_id);
	}

	public function synchronize_claim(int $claim_id, string $source_action = 'native_change', int $actor_user_id = 0): void
	{
		$tables = bvmgr_admission_offers_table_names();
		$claim = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE id = %d', $tables['claims'], $claim_id), ARRAY_A);
		if (!is_array($claim) || in_array((string) $claim['status'], array('canceled', 'expired'), true)) return;
		$states = $this->db->get_col($this->db->prepare('SELECT state FROM %i WHERE claim_id = %d ORDER BY id ASC', $tables['fulfillments'], $claim_id));
		if (!is_array($states) || $states === array()) return;
		if (count(array_filter($states, static fn($state): bool => $state === 'revoked')) === count($states)) $derived = 'revoked';
		elseif (count(array_filter($states, static fn($state): bool => $state === 'used')) === count($states)) $derived = 'used';
		elseif (in_array('used', $states, true) || in_array('partially_used', $states, true)) $derived = 'partially_used';
		elseif (in_array('pending', $states, true) || in_array('exception', $states, true) || in_array('failed', $states, true)) $derived = 'claimed';
		else $derived = 'fulfilled';
		$previous = (string) $claim['status'];
		if ($previous === $derived) return;
		$now = gmdate('Y-m-d H:i:s');
		if ($this->db->query($this->db->prepare('UPDATE %i SET status = %s, state_version = state_version + 1, updated_by = %d, updated_at = %s WHERE id = %d', $tables['claims'], $derived, $actor_user_id, $now, $claim_id)) === false) throw new BVMGR_Admission_Offer_Domain_Exception('claim_lifecycle_sync_failed');
		(new BVMGR_Admission_Offer_Event_Repository($this->db))->append(array('entity_type' => 'claim', 'entity_id' => $claim_id, 'event_key' => 'aggregate-' . hash('sha256', $source_action . ':' . implode(',', $states) . ':' . $previous . ':' . $derived), 'previous_state' => $previous, 'new_state' => $derived, 'actor_user_id' => $actor_user_id, 'actor_type' => 'native_admission', 'provider' => 'bvm_native_comp', 'payload' => array('unit_states' => array_count_values($states))), $now);
	}

	/**
	 * Explicit operator action. Capacity is released only when requested and no
	 * unit has ever checked in. Identity enforcement rows are intentionally kept.
	 */
	public function revoke_claim(int $claim_id, string $reason, bool $release_capacity = false, ?int $actor_user_id = null): array
	{
		if (!function_exists('bvmgr_admission_current_user_can_manage') || !bvmgr_admission_current_user_can_manage()) throw new BVMGR_Admission_Offer_Domain_Exception('revocation_forbidden');
		$actor_user_id = $actor_user_id ?? get_current_user_id();
		$reason_code = sanitize_key($reason) ?: 'operator_revocation';
		$tables = bvmgr_admission_offers_table_names();
		$units = $this->db->get_results($this->db->prepare('SELECT * FROM %i WHERE claim_id = %d ORDER BY id ASC', $tables['fulfillments'], $claim_id), ARRAY_A);
		if (!is_array($units) || $units === array()) throw new BVMGR_Admission_Offer_Domain_Exception('claim_not_found');
		$checked = false;
		foreach ($units as $unit) {
			$entry_id = (int) ($unit['credential_ref'] ?? 0);
			if ($entry_id < 1) continue;
			$entry = $this->db->get_row($this->db->prepare('SELECT checked_in_qty, event_plan_id, status FROM %i WHERE id = %d', bvmgr_admission_table_entries(), $entry_id), ARRAY_A);
			if (!is_array($entry)) continue;
			$checked = $checked || (int) ($entry['checked_in_qty'] ?? 0) > 0;
			if ((string) ($entry['status'] ?? '') !== 'canceled') {
				if ($this->db->update(bvmgr_admission_table_entries(), array('status' => 'canceled', 'updated_by' => $actor_user_id, 'updated_at' => bvmgr_admission_now_mysql()), array('id' => $entry_id), array('%s','%d','%s'), array('%d')) === false) throw new BVMGR_Admission_Offer_Domain_Exception('native_admission_revocation_failed');
				bvmgr_admission_audit_log((int) $entry['event_plan_id'], $entry_id, 'admission_offer_revoke', $actor_user_id, 'admission_offer', array('reason_code' => $reason_code, 'claim_id' => $claim_id));
			}
		}
		$released = false;
		if ($release_capacity && !$checked) {
			$now = gmdate('Y-m-d H:i:s');
			$released = $this->db->query($this->db->prepare("UPDATE %i SET state='released', released_at=%s, updated_at=%s, state_version=state_version+1 WHERE claim_id=%d AND state='consumed'", $tables['reservations'], $now, $now, $claim_id)) === 1;
		}
		$this->synchronize_claim($claim_id, 'claim_revocation', $actor_user_id);
		(new BVMGR_Admission_Offer_Event_Repository($this->db))->append(array('entity_type' => 'claim', 'entity_id' => $claim_id, 'event_key' => 'revocation-' . wp_generate_uuid4(), 'previous_state' => null, 'new_state' => 'revoked', 'actor_user_id' => $actor_user_id, 'actor_type' => 'operator', 'provider' => 'bvm_native_comp', 'payload' => array('reason_code' => $reason_code, 'capacity_release_requested' => $release_capacity, 'capacity_released' => $released, 'checked_in_history' => $checked)));
		return array('claim_id' => $claim_id, 'revoked' => true, 'capacity_released' => $released, 'checked_in_history' => $checked);
	}

	public function revoke_fulfillment(int $fulfillment_id, string $reason, ?int $actor_user_id = null): array
	{
		if (!function_exists('bvmgr_admission_current_user_can_manage') || !bvmgr_admission_current_user_can_manage()) throw new BVMGR_Admission_Offer_Domain_Exception('revocation_forbidden');
		$actor_user_id = $actor_user_id ?? get_current_user_id();
		$reason_code = sanitize_key($reason) ?: 'operator_revocation';
		$table = bvmgr_admission_offers_table('fulfillments');
		$unit = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE id = %d', $table, $fulfillment_id), ARRAY_A);
		if (!is_array($unit) || (string) ($unit['fulfillment_provider'] ?? '') !== 'bvm_native_comp') throw new BVMGR_Admission_Offer_Domain_Exception('fulfillment_not_found');
		$entry_id = (int) ($unit['credential_ref'] ?? 0);
		$entry = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE id = %d', bvmgr_admission_table_entries(), $entry_id), ARRAY_A);
		if (!is_array($entry)) throw new BVMGR_Admission_Offer_Domain_Exception('native_admission_not_found');
		if ($this->db->update(bvmgr_admission_table_entries(), array('status' => 'canceled', 'updated_by' => $actor_user_id, 'updated_at' => bvmgr_admission_now_mysql()), array('id' => $entry_id), array('%s','%d','%s'), array('%d')) === false) throw new BVMGR_Admission_Offer_Domain_Exception('native_admission_revocation_failed');
		bvmgr_admission_audit_log((int) $entry['event_plan_id'], $entry_id, 'admission_offer_unit_revoke', $actor_user_id, 'admission_offer', array('reason_code' => $reason_code, 'fulfillment_id' => $fulfillment_id));
		return array('fulfillment_id' => $fulfillment_id, 'entry_id' => $entry_id, 'revoked' => true);
	}
}

if (!function_exists('bvmgr_admission_offer_observe_native_audit')) {
	function bvmgr_admission_offer_observe_native_audit(int $event_plan_id, ?int $entry_id, string $action, int $actor_user_id): void
	{
		unset($event_plan_id);
		if (!$entry_id) return;
		try {
			(new BVMGR_Admission_Offer_Lifecycle_Service())->synchronize_entry($entry_id, $action, $actor_user_id);
		} catch (Throwable $error) {
			if (function_exists('bvmgr_record_operational_issue')) {
				bvmgr_record_operational_issue('admission_offer_lifecycle_sync_failed', array('service' => 'admission_offers', 'entity_type' => 'admission', 'entity_id' => $entry_id, 'operation' => 'lifecycle_sync', 'status' => 'retry_required'), $error);
			}
		}
	}
}
add_action('bvmgr_admission_audit_logged', 'bvmgr_admission_offer_observe_native_audit', 10, 4);
