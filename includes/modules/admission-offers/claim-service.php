<?php
defined('ABSPATH') || exit;

final class BVMGR_Admission_Offer_Claim_Service
{
	/** @var object */
	private $db;
	private BVMGR_Admission_Offer_Fulfillment_Provider_Interface $provider;
	private BVMGR_Admission_Offer_Eligibility_Resolver $eligibility;
	/** @var callable|null */
	private $interrupt;

	/** @param object|null $db */
	public function __construct($db = null, ?BVMGR_Admission_Offer_Fulfillment_Provider_Interface $provider = null, ?callable $interrupt = null)
	{
		if ($db === null) {
			global $wpdb;
			$db = $wpdb;
		}
		$this->db = $db;
		$this->provider = $provider ?? new BVMGR_Admission_Offer_Native_Complimentary_Provider($db, $interrupt);
		$this->eligibility = new BVMGR_Admission_Offer_Eligibility_Resolver($db);
		$this->interrupt = $interrupt;
	}

	/**
	 * Internal provider-facing API. Authentication, nonces and abuse controls
	 * belong to the future distribution adapter; Phase B registers no endpoint.
	 *
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	public function claim(array $request): array
	{
		$offer_id = (int) ($request['offer_id'] ?? 0);
		$event_plan_id = (int) ($request['event_plan_id'] ?? 0);
		$quantity = (int) ($request['quantity'] ?? 0);
		$idempotency_key = (string) ($request['idempotency_key'] ?? '');
		$access_secret = (string) ($request['access_secret'] ?? '');
		if ($offer_id < 1 || $event_plan_id < 1 || $quantity < 1 || strlen($idempotency_key) < 16 || strlen($access_secret) < 32) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_claim_request');
		}
		$rate_context = array('offer_id' => $offer_id, 'event_plan_id' => $event_plan_id, 'provider' => sanitize_key((string) (($request['distribution_context']['provider'] ?? 'internal'))));
		if (!apply_filters('bvmgr_admission_offer_claim_rate_limit', true, $rate_context)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('claim_unavailable');
		}
		$claim = null;
		for ($attempt = 1; $attempt <= 3; $attempt++) {
			try {
				$claim = $this->establish_claim($request, $offer_id, $event_plan_id, $quantity, $idempotency_key, $access_secret);
				break;
			} catch (BVMGR_Admission_Offer_Transient_Transaction_Exception $error) {
				if ($attempt >= 3) throw $error;
				usleep($attempt * 25000);
			}
		}
		if (!is_array($claim)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('claim_transaction_failed');
		}
		if (in_array((string) ($claim['status'] ?? ''), array('revoked', 'canceled', 'expired'), true)) {
			return $this->result((int) $claim['id']);
		}
		$this->interrupt('after_claim_creation', array('claim_id' => (int) $claim['id']));
		try {
			$this->provider->fulfill_claim($claim, array('offer_type' => 'complimentary', 'actor_user_id' => (int) ($request['actor_user_id'] ?? 0)));
			$this->finalize((int) $claim['id']);
		} catch (Throwable $error) {
			$this->record_attempt_failure((int) $claim['id'], $error);
			throw $error;
		}
		return $this->result((int) $claim['id']);
	}

	/** @param array<string,mixed> $request @return array<string,mixed> */
	private function establish_claim(array $request, int $offer_id, int $event_plan_id, int $quantity, string $idempotency_key, string $access_secret): array
	{
		$tables = bvmgr_admission_offers_table_names();
		$now = gmdate('Y-m-d H:i:s');
		$this->transaction('START TRANSACTION');
		try {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Offer row is the canonical lock for claim/capacity serialization.
			$offer = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE id = %d FOR UPDATE', $tables['offers'], $offer_id), ARRAY_A);
			if (!is_array($offer)) throw new BVMGR_Admission_Offer_Domain_Exception('offer_not_claimable');
			$idempotency_hash = bvmgr_admission_offer_idempotency_hash($idempotency_key);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Replay lookup is protected by the locked Offer row.
			$reservation = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE offer_id = %d AND idempotency_key_hash = %s FOR UPDATE', $tables['reservations'], $offer_id, $idempotency_hash), ARRAY_A);
			if (is_array($reservation)) {
				if ((int) $reservation['event_plan_id'] !== $event_plan_id || (int) $reservation['quantity'] !== $quantity) throw new BVMGR_Admission_Offer_Domain_Exception('idempotency_key_conflict');
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Return the durable replay target from the same transaction.
				$existing = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE id = %d FOR UPDATE', $tables['claims'], (int) $reservation['claim_id']), ARRAY_A);
				if (!is_array($existing)) throw new BVMGR_Admission_Offer_Domain_Exception('claim_replay_corrupt');
				$this->transaction('COMMIT');
				return $existing;
			}
			if ((string) ($offer['status'] ?? '') !== 'active') throw new BVMGR_Admission_Offer_Domain_Exception('offer_not_claimable');
			if ((string) ($offer['offer_type'] ?? '') !== 'complimentary') throw new BVMGR_Admission_Offer_Domain_Exception('complimentary_offer_required');
			if ($quantity > (int) ($offer['max_qty_per_claim'] ?? 0)) throw new BVMGR_Admission_Offer_Domain_Exception('claim_quantity_exceeds_offer_limit');
			if (!empty($offer['claim_expires_at']) && (string) $offer['claim_expires_at'] <= $now) throw new BVMGR_Admission_Offer_Domain_Exception('offer_claim_expired');

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Rules are locked and revalidated inside the claim transaction.
			$rules = $this->db->get_results($this->db->prepare('SELECT * FROM %i WHERE offer_id = %d ORDER BY id ASC FOR UPDATE', $tables['eligibility'], $offer_id), ARRAY_A);
			$this->eligibility->resolve($offer_id, $event_plan_id, is_array($rules) ? $rules : array());
			$identity = is_array($request['claimant_identity'] ?? null) ? $request['claimant_identity'] : array();
			$distribution = is_array($request['distribution_context'] ?? null) ? $request['distribution_context'] : array();
			$claim_input = array(
				'offer_id' => $offer_id, 'event_plan_id' => $event_plan_id, 'quantity' => $quantity,
				'claimant_first_name' => sanitize_text_field((string) ($identity['first_name'] ?? '')),
				'claimant_last_name' => sanitize_text_field((string) ($identity['last_name'] ?? '')),
				'claimant_email' => sanitize_email((string) ($identity['email'] ?? '')),
				'claimant_phone' => sanitize_text_field((string) ($identity['phone'] ?? '')),
				'claimant_account_id' => (int) ($identity['account'] ?? 0),
				'attribution_source' => sanitize_text_field((string) ($distribution['attribution_source'] ?? '')),
				'distribution_provider' => sanitize_key((string) ($distribution['provider'] ?? 'internal')),
				'distribution_mode' => sanitize_key((string) ($distribution['mode'] ?? 'internal')),
				'campaign_ref' => sanitize_text_field((string) ($distribution['campaign_ref'] ?? '')),
				'distribution_subject_ref' => sanitize_text_field((string) ($distribution['subject_ref'] ?? '')),
				'expires_at' => $offer['claim_expires_at'] ?? null,
				'created_by' => (int) ($request['actor_user_id'] ?? 0),
			);
			$claim_id = (new BVMGR_Admission_Offer_Claim_Repository($this->db))->create($claim_input, $access_secret, $now);
			$this->reserve_identities($claim_id, $offer, $identity, $distribution, $now);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Capacity is counted while the Offer lock is held.
			$used = (int) $this->db->get_var($this->db->prepare("SELECT COALESCE(SUM(quantity),0) FROM %i WHERE offer_id = %d AND state IN ('held','order_attached','consumed')", $tables['reservations'], $offer_id));
			if ($offer['capacity_total'] !== null && $offer['capacity_total'] !== '' && ($used + $quantity) > (int) $offer['capacity_total']) throw new BVMGR_Admission_Offer_Domain_Exception('offer_capacity_exhausted');
			$expires_at = !empty($offer['claim_expires_at']) ? (string) $offer['claim_expires_at'] : $now;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Complimentary capacity begins as the certified held state inside the claim transaction.
			if ($this->db->insert($tables['reservations'], array('public_id' => bvmgr_admission_offer_generate_public_id('ar'), 'offer_id' => $offer_id, 'claim_id' => $claim_id, 'event_plan_id' => $event_plan_id, 'quantity' => $quantity, 'state' => 'held', 'idempotency_key_hash' => $idempotency_hash, 'expires_at' => $expires_at, 'state_version' => 1, 'created_at' => $now), array('%s','%d','%d','%d','%d','%s','%s','%s','%d','%s')) === false) throw new BVMGR_Admission_Offer_Domain_Exception('reservation_insert_failed');
			$reservation_id = (int) $this->db->insert_id;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- No checkout exists, so the hold is consumed before this transaction can become visible.
			if ($this->db->query($this->db->prepare("UPDATE %i SET state='consumed', consumed_at=%s, updated_at=%s, state_version=state_version+1 WHERE id=%d AND state='held'", $tables['reservations'], $now, $now, $reservation_id)) !== 1) throw new BVMGR_Admission_Offer_Domain_Exception('reservation_consumption_failed');
			$claim_public_id = (string) $this->db->get_var($this->db->prepare('SELECT public_id FROM %i WHERE id = %d', $tables['claims'], $claim_id));
			$fulfillment_repo = new BVMGR_Admission_Offer_Fulfillment_Repository($this->db);
			for ($unit = 1; $unit <= $quantity; $unit++) {
				$fulfillment_repo->create_pending(array('offer_id' => $offer_id, 'claim_id' => $claim_id, 'event_plan_id' => $event_plan_id, 'quantity' => 1, 'fulfillment_provider' => $this->provider->provider_key(), 'provider_ref' => $claim_public_id . ':unit:' . $unit), $now);
			}
			(new BVMGR_Admission_Offer_Event_Repository($this->db))->append(array('entity_type' => 'claim', 'entity_id' => $claim_id, 'event_key' => 'claim-created', 'new_state' => 'claimed', 'actor_user_id' => (int) ($request['actor_user_id'] ?? 0), 'actor_type' => 'distribution_provider', 'provider' => sanitize_key((string) ($distribution['provider'] ?? 'internal')), 'payload' => array('event_plan_id' => $event_plan_id, 'quantity' => $quantity)), $now);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Return the transaction-local claim after all invariant rows exist.
			$claim = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE id = %d', $tables['claims'], $claim_id), ARRAY_A);
			$this->transaction('COMMIT');
			return (array) $claim;
		} catch (Throwable $error) {
			$this->transaction('ROLLBACK', false);
			if ($error instanceof BVMGR_Admission_Offer_Transient_Transaction_Exception) {
				throw $error;
			}
			if (bvmgr_admission_offer_db_error_is_transient($this->db)) {
				throw new BVMGR_Admission_Offer_Transient_Transaction_Exception('claim_transaction_transient');
			}
			throw $error;
		}
	}

	/** @param array<string,mixed> $offer @param array<string,mixed> $identity @param array<string,mixed> $distribution */
	private function reserve_identities(int $claim_id, array $offer, array $identity, array $distribution, string $now): void
	{
		$policy = json_decode((string) ($offer['identity_policy_json'] ?? '{}'), true);
		$policy = is_array($policy) ? $policy : array();
		$types = $policy['identity_types'] ?? $policy['types'] ?? array('email');
		$types = array_values(array_unique(array_map('sanitize_key', is_array($types) ? $types : array())));
		if ($types === array()) throw new BVMGR_Admission_Offer_Domain_Exception('identity_policy_requires_type');
		$scope_mode = sanitize_key((string) ($policy['identity_scope'] ?? $policy['scope'] ?? 'offer'));
		$scope = 'offer';
		if ($scope_mode === 'distribution') {
			$scope = bvmgr_admission_offer_normalize_scope_key((string) ($distribution['identity_scope_key'] ?? ''));
			if ($scope === 'offer') throw new BVMGR_Admission_Offer_Domain_Exception('distribution_identity_scope_required');
		} elseif ($scope_mode !== 'offer') {
			$scope = bvmgr_admission_offer_normalize_scope_key($scope_mode);
		}
		$keyring = BVMGR_Admission_Offer_Identity_Keyring::load_or_create($this->db);
		$keys = $keyring->keys();
		$active = $keyring->active_version();
		$table = bvmgr_admission_offers_table('identities');
		foreach ($types as $type) {
			$value = (string) ($identity[$type] ?? '');
			if ($type === 'account') $value = (string) ((int) ($identity['account'] ?? 0));
			$normalized = bvmgr_admission_offer_normalize_identity($type, $value);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Identity uniqueness is checked under the locked Offer row in the same transaction.
			$rows = $this->db->get_results($this->db->prepare('SELECT hash_key_version, identity_hash FROM %i WHERE offer_id = %d AND identity_scope_key = %s AND identity_type = %s FOR UPDATE', $table, (int) $offer['id'], $scope, $type), ARRAY_A);
			foreach ((array) $rows as $row) {
				$version = (int) ($row['hash_key_version'] ?? 0);
				if (!isset($keys[$version])) throw new BVMGR_Admission_Offer_Domain_Exception('identity_key_version_missing');
				if (hash_equals((string) $row['identity_hash'], bvmgr_admission_offer_identity_hash($scope, $type, $normalized, $keys[$version]))) throw new BVMGR_Admission_Offer_Domain_Exception('duplicate_or_invalid_scoped_identity');
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Identity enforcement row is part of the claim transaction.
			if ($this->db->insert($table, array('claim_id' => $claim_id, 'offer_id' => (int) $offer['id'], 'identity_scope_key' => $scope, 'identity_type' => $type, 'hash_key_version' => $active, 'identity_hash' => bvmgr_admission_offer_identity_hash($scope, $type, $normalized, $keys[$active]), 'created_at' => $now), array('%d','%d','%s','%s','%d','%s','%s')) === false) throw new BVMGR_Admission_Offer_Domain_Exception('duplicate_or_invalid_scoped_identity');
		}
	}

	private function finalize(int $claim_id): void
	{
		$tables = bvmgr_admission_offers_table_names();
		$this->transaction('START TRANSACTION');
		try {
			$claim = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE id = %d FOR UPDATE', $tables['claims'], $claim_id), ARRAY_A);
			if (!is_array($claim)) throw new BVMGR_Admission_Offer_Domain_Exception('claim_not_found');
			$count = (int) $this->db->get_var($this->db->prepare("SELECT COUNT(*) FROM %i WHERE claim_id = %d AND state = 'fulfilled' AND credential_ref IS NOT NULL", $tables['fulfillments'], $claim_id));
			if ($count !== (int) $claim['quantity']) throw new BVMGR_Admission_Offer_Domain_Exception('native_fulfillment_units_incomplete');
			if ((string) $claim['status'] === 'claimed') {
				$now = gmdate('Y-m-d H:i:s');
				if ($this->db->query($this->db->prepare("UPDATE %i SET status='fulfilled', state_version=state_version+1, updated_at=%s WHERE id=%d AND status='claimed'", $tables['claims'], $now, $claim_id)) !== 1) throw new BVMGR_Admission_Offer_Domain_Exception('claim_fulfillment_finalize_failed');
				(new BVMGR_Admission_Offer_Event_Repository($this->db))->append(array('entity_type' => 'claim', 'entity_id' => $claim_id, 'event_key' => 'claim-fulfilled', 'previous_state' => 'claimed', 'new_state' => 'fulfilled', 'actor_type' => 'fulfillment_provider', 'provider' => $this->provider->provider_key(), 'payload' => array('quantity' => $count)), $now);
			}
			$this->transaction('COMMIT');
			$this->interrupt('after_final_state', array('claim_id' => $claim_id));
		} catch (Throwable $error) {
			$this->transaction('ROLLBACK', false);
			throw $error;
		}
	}

	private function record_attempt_failure(int $claim_id, Throwable $error): void
	{
		try {
			$table = bvmgr_admission_offers_table('claims');
			$current = (string) $this->db->get_var($this->db->prepare('SELECT status FROM %i WHERE id = %d', $table, $claim_id));
			if (in_array($current, array('fulfilled', 'partially_used', 'used', 'revoked'), true)) return;
			$error_code = ($error instanceof BVMGR_Admission_Offer_Domain_Exception && preg_match('/^[a-z0-9_:-]{1,80}$/', $error->getMessage())) ? $error->getMessage() : 'provider_exception';
			(new BVMGR_Admission_Offer_Event_Repository($this->db))->append(array('entity_type' => 'claim', 'entity_id' => $claim_id, 'event_key' => 'fulfillment-attempt-failed-' . substr(hash('sha256', get_class($error) . ':' . $error_code), 0, 20), 'actor_type' => 'system', 'provider' => $this->provider->provider_key(), 'payload' => array('error_code' => $error_code)));
		} catch (Throwable $ignored) {
			// Preserve the original failure; operational recovery remains replay-safe.
		}
	}

	/** @return array<string,mixed> */
	private function result(int $claim_id): array
	{
		$tables = bvmgr_admission_offers_table_names();
		$claim = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE id = %d', $tables['claims'], $claim_id), ARRAY_A);
		$fulfillments = $this->db->get_results($this->db->prepare('SELECT * FROM %i WHERE claim_id = %d ORDER BY provider_ref ASC', $tables['fulfillments'], $claim_id), ARRAY_A);
		return array('claim' => is_array($claim) ? $claim : array(), 'fulfillments' => is_array($fulfillments) ? $fulfillments : array());
	}

	private function transaction(string $sql, bool $throw = true): void
	{
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Explicit InnoDB transaction boundary.
		if ($this->db->query($sql) === false && $throw) throw new BVMGR_Admission_Offer_Domain_Exception('claim_transaction_boundary_failed');
	}

	/** @param array<string,mixed> $context */
	private function interrupt(string $point, array $context): void
	{
		if ($this->interrupt !== null) ($this->interrupt)($point, $context);
	}
}
