<?php
defined('ABSPATH') || exit;

/**
 * Database mechanics shared by the paid Claim service's reservation lifecycle.
 * The state argument deliberately admits order_attached so a future commerce
 * adapter can use this same locked reconciliation path. C1 passes held only.
 */
final class BVMGR_Admission_Offer_Reservation_Lifecycle_Repository
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

	/** @param string[] $states @return array<int,array<string,mixed>> */
	public function lock_stale(int $offer_id, string $now, array $states = array('held')): array
	{
		$states = array_values(array_unique(array_map('strval', $states)));
		if ($offer_id < 1 || $states === array() || array_diff($states, array('held', 'order_attached'))) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_expiry_reconciliation');
		}
		$placeholders = implode(',', array_fill(0, count($states), '%s'));
		$args = array_merge(array(bvmgr_admission_offers_table('reservations'), $offer_id), $states, array($now));
		$sql = "SELECT * FROM %i WHERE offer_id = %d AND state IN ({$placeholders}) AND expires_at <= %s ORDER BY id ASC FOR UPDATE";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Stale rows are locked beneath the canonical Offer lock.
		$rows = $this->db->get_results($this->db->prepare($sql, ...$args), ARRAY_A);
		if (!is_array($rows)) {
			$this->throw_db_error('stale_reservation_lookup_failed');
		}
		return $rows;
	}

	/** @param array<string,mixed> $reservation @return array<string,mixed> */
	public function expire_locked(array $reservation, string $now): array
	{
		$from = (string) ($reservation['state'] ?? '');
		if (!in_array($from, array('held', 'order_attached'), true)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('reservation_not_expirable');
		}
		$table = bvmgr_admission_offers_table('reservations');
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Guarded transition of a previously locked plugin-owned row.
		$result = $this->db->query($this->db->prepare("UPDATE %i SET state = 'expired', expired_at = %s, updated_at = %s, state_version = state_version + 1 WHERE id = %d AND state = %s", $table, $now, $now, (int) $reservation['id'], $from));
		if ($result !== 1) {
			$this->throw_db_error('stale_reservation_expiry_failed');
		}
		$reservation['state'] = 'expired';
		$reservation['expired_at'] = $now;
		$reservation['updated_at'] = $now;
		$reservation['state_version'] = (int) ($reservation['state_version'] ?? 0) + 1;
		return $reservation;
	}

	private function throw_db_error(string $error_code): void
	{
		if (bvmgr_admission_offer_db_error_is_transient($this->db)) {
			throw new BVMGR_Admission_Offer_Transient_Transaction_Exception($error_code);
		}
		throw new BVMGR_Admission_Offer_Domain_Exception($error_code);
	}
}

/**
 * Paid percent/fixed Offer Claim and temporary capacity lifecycle.
 *
 * This is an internal domain API. It registers no route, menu, Woo hook, or
 * scheduler and creates no checkout, allocation, fulfillment, or credential.
 */
final class BVMGR_Admission_Offer_Paid_Claim_Service
{
	private const SOFT_TTL_SECONDS = 1200;
	private const HARD_TTL_SECONDS = 2700;
	private const MAX_ATTEMPTS = 3;
	private const RENEWABLE_ACTIVITIES = array(
		'validated_initial_activation',
		'eligible_item_added',
		'eligible_quantity_changed',
		'checkout_entered',
		'place_order_attempted',
	);
	private const NON_RENEWABLE_ACTIVITIES = array(
		'page_refresh',
		'session_restored',
		'totals_recalculated',
		'background_read',
		'invalid_change',
		'replay',
	);

	/** @var object */
	private $db;
	private BVMGR_Admission_Offer_Eligibility_Resolver $eligibility;
	private BVMGR_Admission_Offer_Reservation_Lifecycle_Repository $reservations;
	/** @var callable */
	private $clock;
	/** @var callable */
	private $retry_delay;
	/** @var callable */
	private $release_authorizer;
	/** @var callable|null */
	private $interrupt;
	private ?string $store_currency;

	/**
	 * @param object|null $db
	 * @param callable|null $clock Returns a UTC mysql datetime.
	 * @param callable|null $release_authorizer Receives the trusted release context.
	 */
	public function __construct(
		$db = null,
		?callable $clock = null,
		?callable $retry_delay = null,
		?callable $release_authorizer = null,
		?callable $interrupt = null,
		?string $store_currency = null
	) {
		if ($db === null) {
			global $wpdb;
			$db = $wpdb;
		}
		$this->db = $db;
		$this->eligibility = new BVMGR_Admission_Offer_Eligibility_Resolver($db);
		$this->reservations = new BVMGR_Admission_Offer_Reservation_Lifecycle_Repository($db);
		$this->clock = $clock ?? static fn(): string => gmdate('Y-m-d H:i:s');
		$this->retry_delay = $retry_delay ?? static function (int $attempt): void {
			usleep($attempt * 25000);
		};
		$this->release_authorizer = $release_authorizer ?? static function (array $context): bool {
			if (($context['actor_type'] ?? '') === 'operator'
				&& function_exists('bvmgr_admission_current_user_can_manage')
				&& bvmgr_admission_current_user_can_manage()) {
				return true;
			}
			return (bool) apply_filters('bvmgr_admission_offer_paid_release_authorized', false, $context);
		};
		$this->interrupt = $interrupt;
		$this->store_currency = $store_currency !== null ? strtoupper(trim($store_currency)) : null;
	}

	/** @param array<string,mixed> $request @return array{claim:array<string,mixed>,reservation:array<string,mixed>} */
	public function claim(array $request): array
	{
		$offer_id = (int) ($request['offer_id'] ?? 0);
		$event_plan_id = (int) ($request['event_plan_id'] ?? 0);
		$quantity_value = $request['quantity'] ?? null;
		$quantity = (!is_bool($quantity_value) && filter_var($quantity_value, FILTER_VALIDATE_INT) !== false) ? (int) $quantity_value : 0;
		$idempotency_key = (string) ($request['idempotency_key'] ?? '');
		$access_secret = (string) ($request['access_secret'] ?? '');
		if ($offer_id < 1 || $event_plan_id < 1 || $quantity < 1 || strlen($idempotency_key) < 16 || strlen($access_secret) < 32) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_paid_claim_request');
		}
		$distribution = is_array($request['distribution_context'] ?? null) ? $request['distribution_context'] : array();
		$rate_context = array(
			'offer_id' => $offer_id,
			'event_plan_id' => $event_plan_id,
			'provider' => sanitize_key((string) ($distribution['provider'] ?? 'internal')),
		);
		if (!apply_filters('bvmgr_admission_offer_claim_rate_limit', true, $rate_context)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('claim_unavailable');
		}

		return $this->retry(function () use ($request, $offer_id, $event_plan_id, $quantity, $idempotency_key, $access_secret): array {
			return $this->claim_once($request, $offer_id, $event_plan_id, $quantity, $idempotency_key, $access_secret);
		});
	}

	/**
	 * Renew only for a validated semantic activity. Read-only/replay activity is
	 * intentionally a no-op and does not change state_version or emit an event.
	 *
	 * @return array<string,mixed>
	 */
	public function renew_reservation(int $offer_id, int $claim_id, int $reservation_id, string $activity): array
	{
		$activity = sanitize_key($activity);
		if (!in_array($activity, array_merge(self::RENEWABLE_ACTIVITIES, self::NON_RENEWABLE_ACTIVITIES), true)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_reservation_activity');
		}
		return $this->retry(function () use ($offer_id, $claim_id, $reservation_id, $activity): array {
			return $this->renew_once($offer_id, $claim_id, $reservation_id, $activity);
		});
	}

	/** @param array<string,mixed> $authority @return array<string,mixed> */
	public function release_reservation(int $offer_id, int $claim_id, int $reservation_id, string $reason_code, array $authority): array
	{
		$reason_code = bvmgr_admission_offer_required_key($reason_code, 80);
		$authority['actor_type'] = bvmgr_admission_offer_required_key($authority['actor_type'] ?? '', 40);
		$authority['provider'] = bvmgr_admission_offer_nullable_key($authority['provider'] ?? null, 80);
		$authority['actor_user_id'] = max(0, (int) ($authority['actor_user_id'] ?? 0));
		if (!(bool) ($this->release_authorizer)($authority)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('reservation_release_forbidden');
		}
		return $this->retry(function () use ($offer_id, $claim_id, $reservation_id, $reason_code, $authority): array {
			return $this->release_once($offer_id, $claim_id, $reservation_id, $reason_code, $authority);
		});
	}

	/** @param array<string,mixed> $request @return array{claim:array<string,mixed>,reservation:array<string,mixed>} */
	private function claim_once(array $request, int $offer_id, int $event_plan_id, int $quantity, string $idempotency_key, string $access_secret): array
	{
		$tables = bvmgr_admission_offers_table_names();
		$now = $this->now();
		$idempotency_hash = bvmgr_admission_offer_idempotency_hash($idempotency_key);
		$this->transaction('START TRANSACTION');
		try {
			// Canonical order: Offer -> Claim -> Reservation/Identity -> state/event writes.
			$offer = $this->lock_row($tables['offers'], $offer_id, 'offer_not_claimable');
			$this->interrupt('after_offer_lock', array('offer_id' => $offer_id));
			$this->validate_paid_offer($offer);

			// The Offer lock serializes all mutation. This read identifies the Claim
			// before the canonical Claim -> Reservation row locks are acquired.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Request-fresh replay lookup beneath the Offer lock.
			$reservation = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE offer_id = %d AND idempotency_key_hash = %s', $tables['reservations'], $offer_id, $idempotency_hash), ARRAY_A);
			if (!is_array($reservation) && !empty($this->db->last_error)) {
				$this->throw_db_error('reservation_idempotency_lookup_failed');
			}
			if (is_array($reservation)) {
				$claim = $this->lock_row($tables['claims'], (int) $reservation['claim_id'], 'claim_replay_corrupt');
				$reservation = $this->lock_row($tables['reservations'], (int) $reservation['id'], 'claim_replay_corrupt');
				if ((string) $reservation['state'] === 'held' && $this->reservation_time_limit_reached($reservation, $offer, $claim, $now)) {
					$reservation = $this->expire_one($reservation, $now);
				}
				$this->assert_replay_matches($request, $offer, $claim, $reservation, $event_plan_id, $quantity, $access_secret);
				$this->transaction('COMMIT');
				return array('claim' => $claim, 'reservation' => $reservation);
			}

			if ((string) ($offer['status'] ?? '') !== 'active') {
				throw new BVMGR_Admission_Offer_Domain_Exception('offer_not_claimable');
			}
			if ($quantity > (int) ($offer['max_qty_per_claim'] ?? 0)) {
				throw new BVMGR_Admission_Offer_Domain_Exception('claim_quantity_exceeds_offer_limit');
			}
			if (!empty($offer['claim_expires_at']) && (string) $offer['claim_expires_at'] <= $now) {
				throw new BVMGR_Admission_Offer_Domain_Exception('offer_claim_expired');
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Eligibility is request-fresh and locked inside the Claim transaction.
			$rules = $this->db->get_results($this->db->prepare('SELECT * FROM %i WHERE offer_id = %d ORDER BY id ASC FOR UPDATE', $tables['eligibility'], $offer_id), ARRAY_A);
			if (!is_array($rules)) {
				$this->throw_db_error('eligibility_lookup_failed');
			}
			$this->eligibility->resolve($offer_id, $event_plan_id, $rules);
			$this->expire_stale_held($offer_id, $now);
			$identity = is_array($request['claimant_identity'] ?? null) ? $request['claimant_identity'] : array();
			$distribution = is_array($request['distribution_context'] ?? null) ? $request['distribution_context'] : array();
			$claim_input = $this->claim_input($offer, $identity, $distribution, $event_plan_id, $quantity, (int) ($request['actor_user_id'] ?? 0));
			$claim_id = (new BVMGR_Admission_Offer_Claim_Repository($this->db))->create($claim_input, $access_secret, $now);
			$this->reserve_identities($claim_id, $offer, $identity, $distribution, $now);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Count follows stale reconciliation under the Offer lock.
			$used_value = $this->db->get_var($this->db->prepare("SELECT COALESCE(SUM(quantity),0) FROM %i WHERE offer_id = %d AND ((state = 'held' AND expires_at > %s) OR state IN ('order_attached','consumed'))", $tables['reservations'], $offer_id, $now));
			if ($used_value === null && !empty($this->db->last_error)) {
				$this->throw_db_error('capacity_count_failed');
			}
			$used = (int) $used_value;
			if ($offer['capacity_total'] !== null && $offer['capacity_total'] !== '' && ($used + $quantity) > (int) $offer['capacity_total']) {
				throw new BVMGR_Admission_Offer_Domain_Exception('offer_capacity_exhausted');
			}
			$claim = $this->lock_row($tables['claims'], $claim_id, 'claim_insert_failed');
			$expires_at = $this->expiration_target($now, $now, $offer, $claim);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- One temporary paid hold is created in the Claim transaction.
			$inserted = $this->db->insert($tables['reservations'], array(
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
			), array('%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s'));
			if ($inserted === false) {
				$this->throw_db_error('reservation_insert_failed');
			}
			$reservation_id = (int) $this->db->insert_id;
			$this->interrupt('after_reservation_insert', array('claim_id' => $claim_id, 'reservation_id' => $reservation_id));
			$events = new BVMGR_Admission_Offer_Event_Repository($this->db);
			$provider = sanitize_key((string) ($distribution['provider'] ?? 'internal')) ?: 'internal';
			$events->append(array(
				'entity_type' => 'claim', 'entity_id' => $claim_id, 'event_key' => 'paid-claim-created',
				'new_state' => 'claimed', 'actor_user_id' => (int) ($request['actor_user_id'] ?? 0),
				'actor_type' => 'distribution_provider', 'provider' => $provider,
				'payload' => array('event_plan_id' => $event_plan_id, 'quantity' => $quantity, 'offer_type' => $offer['offer_type']),
			), $now);
			$events->append(array(
				'entity_type' => 'reservation', 'entity_id' => $reservation_id, 'event_key' => 'paid-reservation-held',
				'new_state' => 'held', 'actor_user_id' => (int) ($request['actor_user_id'] ?? 0),
				'actor_type' => 'distribution_provider', 'provider' => $provider,
				'payload' => array('event_plan_id' => $event_plan_id, 'quantity' => $quantity, 'expires_at' => $expires_at),
			), $now);
			$reservation = $this->lock_row($tables['reservations'], $reservation_id, 'reservation_insert_failed');
			$this->transaction('COMMIT');
			return array('claim' => $claim, 'reservation' => $reservation);
		} catch (Throwable $error) {
			$this->rollback_and_rethrow($error, 'paid_claim_transaction_transient');
		}
	}

	/** @return array<string,mixed> */
	private function renew_once(int $offer_id, int $claim_id, int $reservation_id, string $activity): array
	{
		$tables = bvmgr_admission_offers_table_names();
		$now = $this->now();
		$this->transaction('START TRANSACTION');
		try {
			$offer = $this->lock_row($tables['offers'], $offer_id, 'offer_not_reservable');
			$this->validate_paid_offer($offer);
			$claim = $this->lock_row($tables['claims'], $claim_id, 'claim_not_reservable');
			$reservation = $this->lock_row($tables['reservations'], $reservation_id, 'reservation_not_found');
			$this->assert_reservation_owner($offer_id, $claim_id, $claim, $reservation);
			if ((string) $reservation['state'] === 'held' && $this->reservation_time_limit_reached($reservation, $offer, $claim, $now)) {
				$reservation = $this->expire_one($reservation, $now);
			}
			if ((string) $reservation['state'] !== 'held' || in_array($activity, self::NON_RENEWABLE_ACTIVITIES, true)) {
				$this->transaction('COMMIT');
				return $reservation;
			}
			if ((string) ($claim['status'] ?? '') !== 'claimed') {
				throw new BVMGR_Admission_Offer_Domain_Exception('claim_not_reservable');
			}
			$target = $this->expiration_target($now, (string) $reservation['created_at'], $offer, $claim);
			if ($target <= (string) $reservation['expires_at']) {
				$this->transaction('COMMIT');
				return $reservation;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Renewal is guarded by all canonical locks and current held state.
			$result = $this->db->query($this->db->prepare('UPDATE %i SET expires_at = %s, updated_at = %s, state_version = state_version + 1 WHERE id = %d AND state = %s', $tables['reservations'], $target, $now, $reservation_id, 'held'));
			if ($result !== 1) {
				$this->throw_db_error('reservation_renewal_failed');
			}
			(new BVMGR_Admission_Offer_Event_Repository($this->db))->append(array(
				'entity_type' => 'reservation', 'entity_id' => $reservation_id,
				'event_key' => 'paid-reservation-renewed:' . str_replace(array('-', ' ', ':'), '', $target),
				'previous_state' => 'held', 'new_state' => 'held', 'actor_type' => 'system',
				'payload' => array('activity' => $activity, 'expires_at' => $target),
			), $now);
			$reservation = $this->lock_row($tables['reservations'], $reservation_id, 'reservation_reload_failed');
			$this->transaction('COMMIT');
			return $reservation;
		} catch (Throwable $error) {
			$this->rollback_and_rethrow($error, 'reservation_renewal_transient');
		}
	}

	/** @param array<string,mixed> $authority @return array<string,mixed> */
	private function release_once(int $offer_id, int $claim_id, int $reservation_id, string $reason_code, array $authority): array
	{
		$tables = bvmgr_admission_offers_table_names();
		$now = $this->now();
		$this->transaction('START TRANSACTION');
		try {
			$offer = $this->lock_row($tables['offers'], $offer_id, 'offer_not_found');
			$this->validate_paid_offer($offer);
			$claim = $this->lock_row($tables['claims'], $claim_id, 'claim_not_found');
			$reservation = $this->lock_row($tables['reservations'], $reservation_id, 'reservation_not_found');
			$this->assert_reservation_owner($offer_id, $claim_id, $claim, $reservation);
			if ((string) $reservation['state'] === 'held' && $this->reservation_time_limit_reached($reservation, $offer, $claim, $now)) {
				$reservation = $this->expire_one($reservation, $now);
			}
			if ((string) $reservation['state'] !== 'held') {
				$this->transaction('COMMIT');
				return $reservation;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Explicit authorized release of a locked paid hold.
			$result = $this->db->query($this->db->prepare("UPDATE %i SET state = 'released', released_at = %s, updated_at = %s, state_version = state_version + 1 WHERE id = %d AND state = 'held'", $tables['reservations'], $now, $now, $reservation_id));
			if ($result !== 1) {
				$this->throw_db_error('reservation_release_failed');
			}
			(new BVMGR_Admission_Offer_Event_Repository($this->db))->append(array(
				'entity_type' => 'reservation', 'entity_id' => $reservation_id, 'event_key' => 'paid-reservation-released',
				'previous_state' => 'held', 'new_state' => 'released',
				'actor_user_id' => $authority['actor_user_id'], 'actor_type' => $authority['actor_type'],
				'provider' => $authority['provider'], 'payload' => array('reason_code' => $reason_code),
			), $now);
			$reservation = $this->lock_row($tables['reservations'], $reservation_id, 'reservation_reload_failed');
			$this->transaction('COMMIT');
			return $reservation;
		} catch (Throwable $error) {
			$this->rollback_and_rethrow($error, 'reservation_release_transient');
		}
	}

	/** @param array<string,mixed> $offer */
	private function validate_paid_offer(array $offer): void
	{
		$type = (string) ($offer['offer_type'] ?? '');
		if (!in_array($type, array('percent', 'fixed'), true)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('paid_offer_required');
		}
		$policy = json_decode((string) ($offer['identity_policy_json'] ?? ''), true);
		if (!is_array($policy)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_identity_policy');
		}
		$currency = $this->store_currency;
		if ($currency === null) {
			$currency = strtoupper(trim((string) get_option('woocommerce_currency', '')));
		}
		BVMGR_Admission_Offer_Value::from_array(array(
			'public_id' => $offer['public_id'] ?? '', 'name' => $offer['name'] ?? '', 'offer_type' => $type,
			'percent_basis_points' => $offer['percent_basis_points'] ?? null,
			'fixed_amount_minor' => $offer['fixed_amount_minor'] ?? null, 'currency' => $offer['currency'] ?? '',
			'status' => $offer['status'] ?? '', 'source_id' => $offer['source_id'] ?? null,
			'max_qty_per_claim' => $offer['max_qty_per_claim'] ?? 0, 'capacity_total' => $offer['capacity_total'] ?? null,
			'reservation_ttl_seconds' => $offer['reservation_ttl_seconds'] ?? self::SOFT_TTL_SECONDS,
			'claim_expires_at' => $offer['claim_expires_at'] ?? null, 'stacking_policy' => $offer['stacking_policy'] ?? '',
			'identity_policy' => $policy, 'identity_policy_version' => $offer['identity_policy_version'] ?? 0,
		), $currency);
	}

	/** @param array<string,mixed> $offer @param array<string,mixed> $identity @param array<string,mixed> $distribution @return array<string,mixed> */
	private function claim_input(array $offer, array $identity, array $distribution, int $event_plan_id, int $quantity, int $actor_user_id): array
	{
		$email = trim((string) ($identity['email'] ?? ''));
		$phone = trim((string) ($identity['phone'] ?? ''));
		if ($email !== '') {
			bvmgr_admission_offer_normalize_identity('email', $email);
		}
		if ($phone !== '') {
			bvmgr_admission_offer_normalize_identity('phone', $phone);
		}
		return array(
			'offer_id' => (int) $offer['id'], 'event_plan_id' => $event_plan_id, 'quantity' => $quantity,
			'claimant_first_name' => sanitize_text_field((string) ($identity['first_name'] ?? '')),
			'claimant_last_name' => sanitize_text_field((string) ($identity['last_name'] ?? '')),
			'claimant_email' => sanitize_email($email), 'claimant_phone' => sanitize_text_field($phone),
			'claimant_account_id' => (int) ($identity['account'] ?? 0),
			'attribution_source' => sanitize_text_field((string) ($distribution['attribution_source'] ?? '')),
			'distribution_provider' => sanitize_key((string) ($distribution['provider'] ?? 'internal')) ?: 'internal',
			'distribution_mode' => sanitize_key((string) ($distribution['mode'] ?? 'internal')) ?: 'internal',
			'campaign_ref' => sanitize_text_field((string) ($distribution['campaign_ref'] ?? '')),
			'distribution_subject_ref' => sanitize_text_field((string) ($distribution['subject_ref'] ?? '')),
			'expires_at' => $offer['claim_expires_at'] ?? null, 'created_by' => $actor_user_id,
		);
	}

	/** @param array<string,mixed> $offer @param array<string,mixed> $identity @param array<string,mixed> $distribution */
	private function reserve_identities(int $claim_id, array $offer, array $identity, array $distribution, string $now): void
	{
		[$scope, $types] = $this->identity_policy($offer, $distribution);
		$keyring = BVMGR_Admission_Offer_Identity_Keyring::load_or_create($this->db);
		$keys = $keyring->keys();
		$active = $keyring->active_version();
		if (!isset($keys[$active])) {
			throw new BVMGR_Admission_Offer_Domain_Exception('identity_keyring_corrupt');
		}
		$table = bvmgr_admission_offers_table('identities');
		foreach ($types as $type) {
			$value = $this->identity_value($identity, $type);
			$normalized = bvmgr_admission_offer_normalize_identity($type, $value);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- All retained key versions are checked under the Offer lock.
			$rows = $this->db->get_results($this->db->prepare('SELECT hash_key_version, identity_hash FROM %i WHERE offer_id = %d AND identity_scope_key = %s AND identity_type = %s FOR UPDATE', $table, (int) $offer['id'], $scope, $type), ARRAY_A);
			if (!is_array($rows)) {
				$this->throw_db_error('identity_lookup_failed');
			}
			foreach ($rows as $row) {
				$version = (int) ($row['hash_key_version'] ?? 0);
				if (!isset($keys[$version])) {
					throw new BVMGR_Admission_Offer_Domain_Exception('identity_key_version_missing');
				}
				if (hash_equals((string) $row['identity_hash'], bvmgr_admission_offer_identity_hash($scope, $type, $normalized, $keys[$version]))) {
					throw new BVMGR_Admission_Offer_Domain_Exception('duplicate_or_invalid_scoped_identity');
				}
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Enforcement row is atomic with Claim and Reservation.
			if ($this->db->insert($table, array(
				'claim_id' => $claim_id, 'offer_id' => (int) $offer['id'], 'identity_scope_key' => $scope,
				'identity_type' => $type, 'hash_key_version' => $active,
				'identity_hash' => bvmgr_admission_offer_identity_hash($scope, $type, $normalized, $keys[$active]),
				'created_at' => $now,
			), array('%d', '%d', '%s', '%s', '%d', '%s', '%s')) === false) {
				$this->throw_db_error('duplicate_or_invalid_scoped_identity');
			}
		}
	}

	/** @param array<string,mixed> $request @param array<string,mixed> $offer @param array<string,mixed> $claim @param array<string,mixed> $reservation */
	private function assert_replay_matches(array $request, array $offer, array $claim, array $reservation, int $event_plan_id, int $quantity, string $access_secret): void
	{
		$identity = is_array($request['claimant_identity'] ?? null) ? $request['claimant_identity'] : array();
		$distribution = is_array($request['distribution_context'] ?? null) ? $request['distribution_context'] : array();
		$expected = $this->claim_input($offer, $identity, $distribution, $event_plan_id, $quantity, (int) ($request['actor_user_id'] ?? 0));
		$comparisons = array(
			'offer_id' => (int) $expected['offer_id'], 'event_plan_id' => (int) $expected['event_plan_id'], 'quantity' => (int) $expected['quantity'],
			'claimant_first_name' => (string) $expected['claimant_first_name'], 'claimant_last_name' => (string) $expected['claimant_last_name'],
			'claimant_email_norm' => ($expected['claimant_email'] !== '' ? strtolower((string) $expected['claimant_email']) : ''),
			'claimant_phone_norm' => ($expected['claimant_phone'] !== '' ? bvmgr_admission_offer_normalize_identity('phone', (string) $expected['claimant_phone']) : ''),
			'claimant_account_id' => (int) $expected['claimant_account_id'],
			'attribution_source' => (string) $expected['attribution_source'], 'distribution_provider' => (string) $expected['distribution_provider'],
			'distribution_mode' => (string) $expected['distribution_mode'], 'campaign_ref' => (string) $expected['campaign_ref'],
			'distribution_subject_ref' => (string) $expected['distribution_subject_ref'],
		);
		foreach ($comparisons as $key => $value) {
			$stored = in_array($key, array('offer_id', 'event_plan_id', 'quantity', 'claimant_account_id'), true) ? (int) ($claim[$key] ?? 0) : (string) ($claim[$key] ?? '');
			if ($stored !== $value) {
				throw new BVMGR_Admission_Offer_Domain_Exception('idempotency_key_conflict');
			}
		}
		if ((int) ($reservation['claim_id'] ?? 0) !== (int) $claim['id']
			|| (int) ($reservation['event_plan_id'] ?? 0) !== $event_plan_id
			|| (int) ($reservation['quantity'] ?? 0) !== $quantity
			|| !hash_equals((string) ($claim['access_secret_hash'] ?? ''), bvmgr_admission_offer_hash_secret($access_secret))) {
			throw new BVMGR_Admission_Offer_Domain_Exception('idempotency_key_conflict');
		}
		$this->assert_replay_identities($offer, $claim, $identity, $distribution);
	}

	/** @param array<string,mixed> $offer @param array<string,mixed> $claim @param array<string,mixed> $identity @param array<string,mixed> $distribution */
	private function assert_replay_identities(array $offer, array $claim, array $identity, array $distribution): void
	{
		[$scope, $types] = $this->identity_policy($offer, $distribution);
		$keyring = BVMGR_Admission_Offer_Identity_Keyring::load_or_create($this->db);
		$keys = $keyring->keys();
		$table = bvmgr_admission_offers_table('identities');
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Stored enforcement rows are authoritative replay evidence.
		$rows = $this->db->get_results($this->db->prepare('SELECT identity_scope_key, identity_type, hash_key_version, identity_hash FROM %i WHERE claim_id = %d ORDER BY identity_type ASC FOR UPDATE', $table, (int) $claim['id']), ARRAY_A);
		if (!is_array($rows) || count($rows) !== count($types)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('idempotency_key_conflict');
		}
		$by_type = array();
		foreach ($rows as $row) {
			$by_type[(string) $row['identity_type']] = $row;
		}
		foreach ($types as $type) {
			$row = $by_type[$type] ?? null;
			$version = is_array($row) ? (int) ($row['hash_key_version'] ?? 0) : 0;
			if (!is_array($row) || (string) $row['identity_scope_key'] !== $scope || !isset($keys[$version])) {
				throw new BVMGR_Admission_Offer_Domain_Exception('idempotency_key_conflict');
			}
			$hash = bvmgr_admission_offer_identity_hash($scope, $type, $this->identity_value($identity, $type), $keys[$version]);
			if (!hash_equals((string) $row['identity_hash'], $hash)) {
				throw new BVMGR_Admission_Offer_Domain_Exception('idempotency_key_conflict');
			}
		}
	}

	/** @param array<string,mixed> $offer @param array<string,mixed> $distribution @return array{0:string,1:string[]} */
	private function identity_policy(array $offer, array $distribution): array
	{
		$policy = json_decode((string) ($offer['identity_policy_json'] ?? '{}'), true);
		$policy = is_array($policy) ? $policy : array();
		$types = $policy['identity_types'] ?? $policy['types'] ?? array('email');
		$types = array_values(array_unique(array_map('sanitize_key', is_array($types) ? $types : array())));
		if ($types === array()) {
			throw new BVMGR_Admission_Offer_Domain_Exception('identity_policy_requires_type');
		}
		$scope_mode = sanitize_key((string) ($policy['identity_scope'] ?? $policy['scope'] ?? 'offer'));
		$scope = 'offer';
		if ($scope_mode === 'distribution') {
			$scope = bvmgr_admission_offer_normalize_scope_key((string) ($distribution['identity_scope_key'] ?? ''));
			if ($scope === 'offer') {
				throw new BVMGR_Admission_Offer_Domain_Exception('distribution_identity_scope_required');
			}
		} elseif ($scope_mode !== 'offer') {
			$scope = bvmgr_admission_offer_normalize_scope_key($scope_mode);
		}
		return array($scope, $types);
	}

	/** @param array<string,mixed> $identity */
	private function identity_value(array $identity, string $type): string
	{
		return $type === 'account' ? (string) ((int) ($identity['account'] ?? 0)) : (string) ($identity[$type] ?? '');
	}

	private function expire_stale_held(int $offer_id, string $now): void
	{
		foreach ($this->reservations->lock_stale($offer_id, $now, array('held')) as $reservation) {
			$this->expire_one($reservation, $now);
		}
	}

	/** @param array<string,mixed> $reservation @return array<string,mixed> */
	private function expire_one(array $reservation, string $now): array
	{
		$expired = $this->reservations->expire_locked($reservation, $now);
		(new BVMGR_Admission_Offer_Event_Repository($this->db))->append(array(
			'entity_type' => 'reservation', 'entity_id' => (int) $expired['id'], 'event_key' => 'paid-reservation-expired',
			'previous_state' => (string) $reservation['state'], 'new_state' => 'expired', 'actor_type' => 'system',
			'payload' => array('expired_at' => $now),
		), $now);
		return $expired;
	}

	/** @param array<string,mixed> $offer @param array<string,mixed> $claim */
	private function expiration_target(string $now, string $created_at, array $offer, array $claim): string
	{
		$zone = new DateTimeZone('UTC');
		$target = (new DateTimeImmutable($now, $zone))->modify('+' . self::SOFT_TTL_SECONDS . ' seconds');
		$limits = array(
			(new DateTimeImmutable($created_at, $zone))->modify('+' . self::HARD_TTL_SECONDS . ' seconds'),
		);
		foreach (array($offer['claim_expires_at'] ?? null, $claim['expires_at'] ?? null) as $limit) {
			if ($limit) {
				$limits[] = new DateTimeImmutable((string) $limit, $zone);
			}
		}
		foreach ($limits as $limit) {
			if ($limit < $target) {
				$target = $limit;
			}
		}
		$result = $target->format('Y-m-d H:i:s');
		if ($result <= $now) {
			throw new BVMGR_Admission_Offer_Domain_Exception('reservation_expiry_not_future');
		}
		return $result;
	}

	/** @param array<string,mixed> $reservation @param array<string,mixed> $offer @param array<string,mixed> $claim */
	private function reservation_time_limit_reached(array $reservation, array $offer, array $claim, string $now): bool
	{
		foreach (array($reservation['expires_at'] ?? null, $offer['claim_expires_at'] ?? null, $claim['expires_at'] ?? null) as $limit) {
			if ($limit && (string) $limit <= $now) {
				return true;
			}
		}
		return false;
	}

	/** @param array<string,mixed> $claim @param array<string,mixed> $reservation */
	private function assert_reservation_owner(int $offer_id, int $claim_id, array $claim, array $reservation): void
	{
		if ((int) ($claim['offer_id'] ?? 0) !== $offer_id
			|| (int) ($reservation['offer_id'] ?? 0) !== $offer_id
			|| (int) ($reservation['claim_id'] ?? 0) !== $claim_id) {
			throw new BVMGR_Admission_Offer_Domain_Exception('reservation_owner_mismatch');
		}
	}

	/** @return array<string,mixed> */
	private function lock_row(string $table, int $id, string $error_code): array
	{
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Canonical transaction row lock.
		$row = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE id = %d FOR UPDATE', $table, $id), ARRAY_A);
		if (!is_array($row)) {
			if (!empty($this->db->last_error)) {
				$this->throw_db_error($error_code);
			}
			throw new BVMGR_Admission_Offer_Domain_Exception($error_code);
		}
		return $row;
	}

	private function now(): string
	{
		$now = bvmgr_admission_offer_nullable_datetime((string) ($this->clock)());
		if ($now === null) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_clock');
		}
		return $now;
	}

	/** @return mixed */
	private function retry(callable $operation)
	{
		for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
			try {
				return $operation();
			} catch (BVMGR_Admission_Offer_Transient_Transaction_Exception $error) {
				if ($attempt >= self::MAX_ATTEMPTS) {
					throw $error;
				}
				($this->retry_delay)($attempt);
			}
		}
		throw new BVMGR_Admission_Offer_Domain_Exception('paid_claim_retry_exhausted');
	}

	private function transaction(string $sql, bool $throw = true): void
	{
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Explicit InnoDB transaction boundary.
		if ($this->db->query($sql) === false && $throw) {
			$this->throw_db_error('paid_claim_transaction_boundary_failed');
		}
	}

	private function rollback_and_rethrow(Throwable $error, string $transient_code): void
	{
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- A failed rollback is itself a surfaced consistency failure.
		if ($this->db->query('ROLLBACK') === false) {
			$this->throw_db_error('paid_claim_transaction_rollback_failed');
		}
		if ($error instanceof BVMGR_Admission_Offer_Transient_Transaction_Exception) {
			throw $error;
		}
		if (bvmgr_admission_offer_db_error_is_transient($this->db)) {
			throw new BVMGR_Admission_Offer_Transient_Transaction_Exception($transient_code);
		}
		throw $error;
	}

	private function throw_db_error(string $error_code): void
	{
		if (bvmgr_admission_offer_db_error_is_transient($this->db)) {
			throw new BVMGR_Admission_Offer_Transient_Transaction_Exception($error_code);
		}
		throw new BVMGR_Admission_Offer_Domain_Exception($error_code);
	}

	/** @param array<string,mixed> $context */
	private function interrupt(string $point, array $context): void
	{
		if ($this->interrupt !== null) {
			($this->interrupt)($point, $context);
		}
	}
}
