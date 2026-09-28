<?php
defined('ABSPATH') || exit;

interface BVMGR_Admission_Offer_Capacity_Store_Interface
{
	public function begin(): void;
	public function commit(): void;
	public function rollback(): void;

	/** @return array<string,mixed>|null */
	public function lock_offer(int $offer_id): ?array;

	/** @return array<string,mixed>|null */
	public function lock_claim(int $claim_id): ?array;

	public function expire_stale(int $offer_id, string $now): int;

	/** @return array<string,mixed>|null */
	public function find_by_idempotency(int $offer_id, string $idempotency_key_hash): ?array;

	public function capacity_used(int $offer_id, string $now): int;

	/** @return array<string,mixed> */
	public function insert_hold(array $reservation): array;

	/** @return array<string,mixed> */
	public function renew_hold(int $reservation_id, string $expires_at, string $now): array;
}

final class BVMGR_Admission_Offer_WPDB_Capacity_Store implements BVMGR_Admission_Offer_Capacity_Store_Interface
{
	/** @var object */
	private $db;

	/** @param object $db */
	public function __construct($db = null)
	{
		if ($db === null) {
			global $wpdb;
			$db = $wpdb;
		}
		$this->db = $db;
	}

	public function begin(): void
	{
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Capacity mutation requires one explicit InnoDB transaction.
		if ($this->db->query('START TRANSACTION') === false) {
			$this->throw_db_error('capacity_transaction_start_failed');
		}
	}

	public function commit(): void
	{
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Completes the explicit capacity transaction.
		if ($this->db->query('COMMIT') === false) {
			$this->throw_db_error('capacity_transaction_commit_failed');
		}
	}

	public function rollback(): void
	{
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reverts the explicit capacity transaction on every failure path.
		if ($this->db->query('ROLLBACK') === false) {
			$this->throw_db_error('capacity_transaction_rollback_failed');
		}
	}

	public function lock_offer(int $offer_id): ?array
	{
		$table = bvmgr_admission_offers_table('offers');
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- FOR UPDATE is the serialization boundary for global Offer capacity.
		$row = $this->db->get_row($this->db->prepare('SELECT id, status, max_qty_per_claim, capacity_total, reservation_ttl_seconds, claim_expires_at FROM %i WHERE id = %d FOR UPDATE', $table, $offer_id), ARRAY_A);
		if (!is_array($row) && !empty($this->db->last_error)) {
			$this->throw_db_error('offer_lock_failed');
		}
		return is_array($row) ? $row : null;
	}

	public function lock_claim(int $claim_id): ?array
	{
		$table = bvmgr_admission_offers_table('claims');
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Claim is locked inside the same capacity transaction to bind quantity and Event Plan.
		$row = $this->db->get_row($this->db->prepare('SELECT id, offer_id, event_plan_id, quantity, status, expires_at FROM %i WHERE id = %d FOR UPDATE', $table, $claim_id), ARRAY_A);
		if (!is_array($row) && !empty($this->db->last_error)) {
			$this->throw_db_error('claim_lock_failed');
		}
		return is_array($row) ? $row : null;
	}

	public function expire_stale(int $offer_id, string $now): int
	{
		$table = bvmgr_admission_offers_table('reservations');
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Lazy expiry occurs under the locked Offer row before capacity is counted.
		$result = $this->db->query($this->db->prepare("UPDATE %i SET state = 'expired', expired_at = %s, updated_at = %s, state_version = state_version + 1 WHERE offer_id = %d AND state = 'held' AND expires_at <= %s", $table, $now, $now, $offer_id, $now));
		if ($result === false) {
			$this->throw_db_error('stale_reservation_expiry_failed');
		}
		return (int) $result;
	}

	public function find_by_idempotency(int $offer_id, string $idempotency_key_hash): ?array
	{
		$table = bvmgr_admission_offers_table('reservations');
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotency lookup is transaction-local and request-fresh.
		$row = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE offer_id = %d AND idempotency_key_hash = %s FOR UPDATE', $table, $offer_id, $idempotency_key_hash), ARRAY_A);
		if (!is_array($row) && !empty($this->db->last_error)) {
			$this->throw_db_error('reservation_idempotency_lookup_failed');
		}
		return is_array($row) ? $row : null;
	}

	public function capacity_used(int $offer_id, string $now): int
	{
		$table = bvmgr_admission_offers_table('reservations');
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Serialized capacity count includes live holds and committed reservations only.
		$value = $this->db->get_var($this->db->prepare("SELECT COALESCE(SUM(quantity), 0) FROM %i WHERE offer_id = %d AND ((state = 'held' AND expires_at > %s) OR state IN ('order_attached', 'consumed'))", $table, $offer_id, $now));
		if ($value === null && !empty($this->db->last_error)) {
			$this->throw_db_error('capacity_count_failed');
		}
		return max(0, (int) $value);
	}

	public function insert_hold(array $reservation): array
	{
		$table = bvmgr_admission_offers_table('reservations');
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Transactional repository write to a plugin-owned table.
		$result = $this->db->insert(
			$table,
			$reservation,
			array('%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s')
		);
		if ($result === false) {
			$this->throw_db_error('reservation_insert_failed');
		}
		$reservation['id'] = (int) $this->db->insert_id;
		return $reservation;
	}

	public function renew_hold(int $reservation_id, string $expires_at, string $now): array
	{
		$table = bvmgr_admission_offers_table('reservations');
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Renewal is guarded by the Offer lock and current held state.
		$result = $this->db->query($this->db->prepare("UPDATE %i SET expires_at = %s, updated_at = %s, state_version = state_version + 1 WHERE id = %d AND state = 'held'", $table, $expires_at, $now, $reservation_id));
		if ($result === false) {
			$this->throw_db_error('reservation_renewal_failed');
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Return the transaction-local renewed reservation.
		$row = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE id = %d', $table, $reservation_id), ARRAY_A);
		if (!is_array($row)) {
			if (!empty($this->db->last_error)) {
				$this->throw_db_error('reservation_reload_failed');
			}
			throw new BVMGR_Admission_Offer_Domain_Exception('reservation_reload_failed');
		}
		return $row;
	}

	private function throw_db_error(string $error_code): void
	{
		if (bvmgr_admission_offer_db_error_is_transient($this->db)) {
			throw new BVMGR_Admission_Offer_Transient_Transaction_Exception($error_code);
		}
		throw new BVMGR_Admission_Offer_Domain_Exception($error_code);
	}
}

final class BVMGR_Admission_Offer_Capacity_Service
{
	private BVMGR_Admission_Offer_Capacity_Store_Interface $store;
	/** @var callable */
	private $retry_delay;
	private int $max_attempts;

	public function __construct(BVMGR_Admission_Offer_Capacity_Store_Interface $store, ?callable $retry_delay = null, int $max_attempts = 3)
	{
		if ($max_attempts < 1 || $max_attempts > 5) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_retry_policy');
		}
		$this->store = $store;
		$this->retry_delay = $retry_delay ?? static function (int $attempt): void {
			usleep($attempt * 25000);
		};
		$this->max_attempts = $max_attempts;
	}

	/** @return array<string,mixed> */
	public function acquire(int $offer_id, int $claim_id, int $event_plan_id, int $quantity, string $idempotency_key, ?string $now = null): array
	{
		for ($attempt = 1; $attempt <= $this->max_attempts; $attempt++) {
			try {
				return $this->acquire_once($offer_id, $claim_id, $event_plan_id, $quantity, $idempotency_key, $now);
			} catch (BVMGR_Admission_Offer_Transient_Transaction_Exception $error) {
				if ($attempt >= $this->max_attempts) {
					throw $error;
				}
				($this->retry_delay)($attempt);
			}
		}
		throw new BVMGR_Admission_Offer_Domain_Exception('capacity_transaction_retry_exhausted');
	}

	/** @return array<string,mixed> */
	private function acquire_once(int $offer_id, int $claim_id, int $event_plan_id, int $quantity, string $idempotency_key, ?string $now = null): array
	{
		if ($offer_id < 1 || $claim_id < 1 || $event_plan_id < 1 || $quantity < 1 || strlen($idempotency_key) < 16) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_capacity_request');
		}
		$now = bvmgr_admission_offer_nullable_datetime($now) ?: gmdate('Y-m-d H:i:s');
		$idempotency_hash = bvmgr_admission_offer_idempotency_hash($idempotency_key);
		$this->store->begin();
		try {
			// Canonical order: Offer -> Claim -> Reservation/Identity -> state/event write.
			$offer = $this->store->lock_offer($offer_id);
			if (!$offer || (string) ($offer['status'] ?? '') !== 'active') {
				throw new BVMGR_Admission_Offer_Domain_Exception('offer_not_reservable');
			}
			$max_qty = (int) ($offer['max_qty_per_claim'] ?? 0);
			if ($max_qty < 1 || $quantity > $max_qty) {
				throw new BVMGR_Admission_Offer_Domain_Exception('claim_quantity_exceeds_offer_limit');
			}

			$claim = $this->store->lock_claim($claim_id);
			if (!$claim
				|| (int) ($claim['offer_id'] ?? 0) !== $offer_id
				|| (int) ($claim['event_plan_id'] ?? 0) !== $event_plan_id
				|| (int) ($claim['quantity'] ?? 0) !== $quantity
				|| in_array((string) ($claim['status'] ?? ''), array('expired', 'canceled', 'revoked', 'refunded'), true)
			) {
				throw new BVMGR_Admission_Offer_Domain_Exception('claim_not_reservable');
			}
			if (!empty($claim['expires_at']) && (string) $claim['expires_at'] <= $now) {
				throw new BVMGR_Admission_Offer_Domain_Exception('claim_expired');
			}

			$this->store->expire_stale($offer_id, $now);
			$existing = $this->store->find_by_idempotency($offer_id, $idempotency_hash);
			$expires_at = $this->expiry_for($offer, $claim, $now);
			if ($existing) {
				if ((int) ($existing['claim_id'] ?? 0) !== $claim_id
					|| (int) ($existing['event_plan_id'] ?? 0) !== $event_plan_id
					|| (int) ($existing['quantity'] ?? 0) !== $quantity) {
					throw new BVMGR_Admission_Offer_Domain_Exception('idempotency_key_conflict');
				}
				if ((string) ($existing['state'] ?? '') === 'held') {
					$existing = $this->store->renew_hold((int) $existing['id'], $expires_at, $now);
				}
				$this->store->commit();
				return $existing;
			}

			$used = $this->store->capacity_used($offer_id, $now);
			$capacity_total = $offer['capacity_total'] ?? null;
			if ($capacity_total !== null && $capacity_total !== '' && ($used + $quantity) > (int) $capacity_total) {
				throw new BVMGR_Admission_Offer_Domain_Exception('offer_capacity_exhausted');
			}

			$reservation = $this->store->insert_hold(array(
				'public_id' => bvmgr_admission_offer_generate_public_id('ar'),
				'offer_id' => $offer_id,
				'claim_id' => $claim_id,
				'event_plan_id' => $event_plan_id,
				'quantity' => $quantity,
				'state' => 'held',
				'idempotency_key_hash' => $idempotency_hash,
				'expires_at' => $expires_at,
				'state_version' => 1,
				'created_at' => $now,
			));
			$this->store->commit();
			return $reservation;
		} catch (Throwable $error) {
			$this->store->rollback();
			throw $error;
		}
	}

	/** @param array<string,mixed> $offer @param array<string,mixed> $claim */
	private function expiry_for(array $offer, array $claim, string $now): string
	{
		$ttl = max(60, min(2700, (int) ($offer['reservation_ttl_seconds'] ?? 1200)));
		$expiry = (new DateTimeImmutable($now, new DateTimeZone('UTC')))->modify('+' . $ttl . ' seconds');
		foreach (array($offer['claim_expires_at'] ?? null, $claim['expires_at'] ?? null) as $limit) {
			if (!$limit) {
				continue;
			}
			$limit_date = new DateTimeImmutable((string) $limit, new DateTimeZone('UTC'));
			if ($limit_date < $expiry) {
				$expiry = $limit_date;
			}
		}
		if ($expiry->format('Y-m-d H:i:s') <= $now) {
			throw new BVMGR_Admission_Offer_Domain_Exception('reservation_expiry_not_future');
		}

		return $expiry->format('Y-m-d H:i:s');
	}
}
