<?php
defined('ABSPATH') || exit;

/**
 * Provider-facing Woo session/cart binding for paid Admission Offers.
 *
 * C2 deliberately stops before orders, allocations, discounts, payments, and
 * credentials. The only durable commerce-side state it owns is a Checkout
 * attempt whose checkout_ref is an installation-keyed session proof.
 */
final class BVMGR_Admission_Offer_Woo_Checkout_Service
{
	public const SESSION_KEY = 'bvmgr_admission_offer_checkout_v1';
	public const CART_ITEM_KEY = 'bvmgr_admission_offer';
	public const BINDING_VERSION = 1;
	private const ACTIVE_CHECKOUT_STATES = array('checkout_started');
	private const PROVIDER_PREFIX = 'woocommerce_session_v1_k';
	private const BINDING_DOMAIN = "bvmgr-woo-checkout-binding-v1\0";

	/** @var object */
	private $db;
	/** @var callable */
	private $clock;
	/** @var callable */
	private $session_resolver;
	/** @var callable */
	private $cart_resolver;
	/** @var callable */
	private $product_resolver;
	/** @var callable|null */
	private $interrupt;
	private BVMGR_Admission_Offer_Paid_Claim_Service $paid;

	/**
	 * @param object|null $db
	 * @param callable|null $clock Returns a UTC mysql datetime.
	 * @param callable|null $session_resolver Returns the current WC session.
	 * @param callable|null $cart_resolver Returns the current WC cart.
	 * @param callable|null $product_resolver Returns a WC product for an ID.
	 */
	public function __construct(
		$db = null,
		?callable $clock = null,
		?callable $session_resolver = null,
		?callable $cart_resolver = null,
		?callable $product_resolver = null,
		?callable $interrupt = null
	) {
		if ($db === null) {
			global $wpdb;
			$db = $wpdb;
		}
		$this->db = $db;
		$this->clock = $clock ?? static fn(): string => gmdate('Y-m-d H:i:s');
		$this->session_resolver = $session_resolver ?? static fn() => function_exists('WC') && WC() ? WC()->session : null;
		$this->cart_resolver = $cart_resolver ?? static fn() => function_exists('WC') && WC() ? WC()->cart : null;
		$this->product_resolver = $product_resolver ?? static fn(int $product_id) => function_exists('wc_get_product') ? wc_get_product($product_id) : null;
		$this->interrupt = $interrupt;
		$release_authorizer = static fn(array $authority): bool => ($authority['actor_type'] ?? '') === 'woo_session'
			&& ($authority['provider'] ?? '') === 'woocommerce';
		$this->paid = new BVMGR_Admission_Offer_Paid_Claim_Service($db, $this->clock, null, $release_authorizer);
	}

	/**
	 * Activate or explicitly recover a Claim into the current Woo session.
	 *
	 * Required: claim_public_id, access_secret, product_id, quantity.
	 * Recovery also requires recover=true and previous_checkout_public_id.
	 *
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	public function activate(array $request): array
	{
		$claim_public_id = trim((string) ($request['claim_public_id'] ?? ''));
		$access_secret = (string) ($request['access_secret'] ?? '');
		$product_id = absint($request['product_id'] ?? 0);
		$quantity = $this->positive_int($request['quantity'] ?? 0);
		$recover = !empty($request['recover']);
		$previous_checkout_public_id = trim((string) ($request['previous_checkout_public_id'] ?? ''));
		if (!preg_match('/^ac_[a-f0-9]{32}$/', $claim_public_id) || strlen($access_secret) < 32 || $product_id < 1 || $quantity < 1) {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_activation_unavailable');
		}
		if ($recover && !preg_match('/^ax_[a-f0-9]{32}$/', $previous_checkout_public_id)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_recovery_unavailable');
		}

		$session = $this->require_session();
		$session_id = $this->session_id($session);
		$preview = $this->claim_preview($claim_public_id);
		if (!$preview || !hash_equals((string) ($preview['access_secret_hash'] ?? ''), bvmgr_admission_offer_hash_secret($access_secret))) {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_activation_unavailable');
		}

		// C1 remains the single authority for lazy expiry. This read-like activity
		// never renews, but it atomically expires a stale held reservation.
		$reservation_preview = $this->latest_reservation((int) $preview['id']);
		if (is_array($reservation_preview) && (string) ($reservation_preview['state'] ?? '') === 'held') {
			$this->paid->renew_reservation(
				(int) $preview['offer_id'],
				(int) $preview['id'],
				(int) $reservation_preview['id'],
				'background_read'
			);
		}

		$attempt = $this->create_or_reuse_attempt(
			$claim_public_id,
			$access_secret,
			$product_id,
			$quantity,
			$session_id,
			$recover,
			$previous_checkout_public_id
		);

		if (!empty($attempt['needs_reacquisition'])) {
			try {
				$reservation = $this->reacquire_reservation($attempt, $previous_checkout_public_id);
			} catch (BVMGR_Admission_Offer_Domain_Exception $error) {
				throw new BVMGR_Admission_Offer_Domain_Exception('checkout_recovery_unavailable');
			}
			$attempt = $this->create_or_reuse_attempt(
				$claim_public_id,
				$access_secret,
				$product_id,
				$quantity,
				$session_id,
				true,
				$previous_checkout_public_id
			);
			$attempt['reservation'] = $reservation;
		}

		$this->store_session_record($session, $attempt['session_record']);
		try {
			$cart_item_key = $this->bind_cart($attempt, $product_id, $quantity);
		} catch (Throwable $error) {
			$this->abandon((string) $attempt['checkout']['public_id'], 'activation_cart_failure');
			throw $error;
		}
		$attempt['cart_item_key'] = $cart_item_key;

		$this->renew_from_context($attempt, 'validated_initial_activation');
		return $attempt;
	}

	/**
	 * Recover Woo's undo-remove using the old same-session HMAC proof. A terminal
	 * Checkout is never revived; a new Reservation/Checkout is created instead.
	 *
	 * @param array<string,mixed> $metadata
	 * @return array<string,mixed>
	 */
	public function recover_restored_item(array $metadata, int $product_id, int $quantity): array
	{
		$session = $this->require_session();
		$record = $this->session_record($session);
		$checkout_public_id = trim((string) ($metadata['checkout_public_id'] ?? ''));
		if (!$record || (string) ($record['checkout_public_id'] ?? '') !== $checkout_public_id) {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_restore_unavailable');
		}
		$old = $this->checkout_context($checkout_public_id);
		if (!$old || !$this->verify_binding($old['checkout'], $old['claim'], $record, $this->session_id($session))) {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_restore_unavailable');
		}
		if ((string) $old['checkout']['state'] !== 'abandoned') {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_restore_unavailable');
		}
		$this->validate_product_allocation($old['claim'], $product_id, $quantity);

		$reservation = $this->reacquire_reservation(array(
			'offer' => $old['offer'],
			'claim' => $old['claim'],
			'reservation' => $old['reservation'],
		), $checkout_public_id);
		$attempt = $this->create_attempt_from_session_proof($old, $reservation, $record, $product_id, $quantity);
		$this->store_session_record($session, $attempt['session_record']);
		$this->renew_from_context($attempt, 'eligible_item_added');
		return $attempt;
	}

	/** @param array<string,mixed> $cart_item @return array<string,mixed> */
	public function validate_cart_item(array $cart_item): array
	{
		$metadata = self::cart_item_metadata($cart_item);
		if ($metadata === array()) {
			return array();
		}
		$session = $this->require_session();
		$record = $this->session_record($session);
		if (!$record || empty($record['active']) || (string) ($record['checkout_public_id'] ?? '') !== (string) ($metadata['checkout_public_id'] ?? '')) {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_binding_invalid');
		}
		$context = $this->checkout_context((string) $metadata['checkout_public_id']);
		if (!$context || !$this->verify_binding($context['checkout'], $context['claim'], $record, $this->session_id($session))) {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_binding_invalid');
		}
		$this->assert_live_context($context);
		$product_id = absint(($cart_item['variation_id'] ?? 0) ?: ($cart_item['product_id'] ?? 0));
		$quantity = $this->positive_int($cart_item['quantity'] ?? 0);
		$this->validate_product_allocation($context['claim'], $product_id, $quantity);
		if ((int) ($metadata['event_plan_id'] ?? 0) !== (int) $context['claim']['event_plan_id']
			|| (int) ($metadata['version'] ?? 0) !== self::BINDING_VERSION
			|| $product_id !== (int) ($record['product_id'] ?? 0)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_binding_invalid');
		}
		$context['metadata'] = $metadata;
		return $context;
	}

	/**
	 * Validate all bound items without renewing. Unbound products are ignored.
	 *
	 * @return array{bound:bool,complete:bool,total:int,context:?array<string,mixed>}
	 */
	public function validate_cart($cart = null, bool $require_complete = false): array
	{
		$cart = $cart ?: $this->cart();
		$items = is_object($cart) && is_callable(array($cart, 'get_cart')) ? (array) $cart->get_cart() : array();
		$bound = array_filter($items, static fn($item): bool => is_array($item) && self::cart_item_metadata($item) !== array());
		if ($bound === array()) {
			return array('bound' => false, 'complete' => false, 'total' => 0, 'context' => null);
		}
		$total = 0;
		$context = null;
		$checkout_public_id = '';
		foreach ($bound as $item) {
			$item_context = $this->validate_cart_item($item);
			$item_checkout = (string) $item_context['checkout']['public_id'];
			if ($checkout_public_id !== '' && $checkout_public_id !== $item_checkout) {
				throw new BVMGR_Admission_Offer_Domain_Exception('multiple_checkout_bindings');
			}
			$checkout_public_id = $item_checkout;
			$context = $item_context;
			$total += $this->positive_int($item['quantity'] ?? 0);
		}
		$claim_quantity = (int) ($context['claim']['quantity'] ?? 0);
		$allocated_quantity = (int) ($context['session_record']['allocation_quantity'] ?? $claim_quantity);
		if ($total > $claim_quantity || $total > $allocated_quantity) {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_quantity_exceeded');
		}
		$complete = $total === $claim_quantity;
		if ($require_complete && !$complete) {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_allocation_incomplete');
		}
		return array('bound' => true, 'complete' => $complete, 'total' => $total, 'context' => $context);
	}

	/** @param array<string,mixed> $cart_item_data */
	public function validate_addition(int $product_id, int $quantity, array $cart_item_data, $cart = null): void
	{
		$metadata = self::cart_item_metadata($cart_item_data);
		if ($metadata === array()) {
			return;
		}
		$probe = $cart_item_data;
		$probe['product_id'] = $product_id;
		$probe['variation_id'] = 0;
		$probe['quantity'] = $quantity;
		$context = $this->validate_cart_item($probe);
		$total = $quantity;
		$cart = $cart ?: $this->cart();
		foreach ((array) (is_object($cart) && is_callable(array($cart, 'get_cart')) ? $cart->get_cart() : array()) as $item) {
			$item_meta = is_array($item) ? self::cart_item_metadata($item) : array();
			if ($item_meta !== array() && (string) $item_meta['checkout_public_id'] === (string) $metadata['checkout_public_id']) {
				$total += $this->positive_int($item['quantity'] ?? 0);
			}
		}
		$maximum = min((int) $context['claim']['quantity'], (int) $context['session_record']['allocation_quantity']);
		if ($total > $maximum) {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_quantity_exceeded');
		}
	}

	public function renew_for_cart(string $activity, $cart = null): void
	{
		$validated = $this->validate_cart($cart, false);
		if (!empty($validated['bound']) && is_array($validated['context'])) {
			$this->renew_from_context($validated['context'], $activity);
		}
	}

	/** @param array<string,mixed> $cart_item */
	public function validate_quantity_change(string $cart_item_key, array $cart_item, int $new_quantity, $cart = null): void
	{
		if ($new_quantity < 1) {
			return;
		}
		$context = $this->validate_cart_item($cart_item);
		if ($context === array()) {
			return;
		}
		$checkout_public_id = (string) $context['checkout']['public_id'];
		$total = 0;
		$cart = $cart ?: $this->cart();
		foreach ((array) (is_object($cart) && is_callable(array($cart, 'get_cart')) ? $cart->get_cart() : array()) as $key => $item) {
			$metadata = is_array($item) ? self::cart_item_metadata($item) : array();
			if ($metadata === array() || (string) $metadata['checkout_public_id'] !== $checkout_public_id) {
				continue;
			}
			$total += (string) $key === $cart_item_key ? $new_quantity : $this->positive_int($item['quantity'] ?? 0);
		}
		$maximum = min((int) $context['claim']['quantity'], (int) $context['session_record']['allocation_quantity']);
		if ($total > $maximum) {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_quantity_exceeded');
		}
	}

	public function renew_checkout_entry_once($cart = null): void
	{
		$validated = $this->validate_cart($cart, true);
		if (empty($validated['bound']) || !is_array($validated['context'])) {
			return;
		}
		$session = $this->require_session();
		$record = $this->session_record($session);
		if (!$record || !empty($record['checkout_entered'])) {
			return;
		}
		$this->renew_from_context($validated['context'], 'checkout_entered');
		$record['checkout_entered'] = true;
		$this->store_session_record($session, $record);
	}

	public function abandon(string $checkout_public_id, string $reason): void
	{
		$reason = sanitize_key($reason) ?: 'cart_abandoned';
		$preview = $this->checkout_context($checkout_public_id);
		if (!$preview) {
			return;
		}
		$tables = bvmgr_admission_offers_table_names();
		$now = $this->now();
		$this->transaction('START TRANSACTION');
		try {
			$this->lock_row($tables['offers'], (int) $preview['offer']['id'], 'offer_not_found');
			$claim = $this->lock_row($tables['claims'], (int) $preview['claim']['id'], 'claim_not_found');
			$reservation = $this->lock_row($tables['reservations'], (int) $preview['reservation']['id'], 'reservation_not_found');
			$checkout = $this->lock_row($tables['checkouts'], (int) $preview['checkout']['id'], 'checkout_not_found');
			if ((string) $checkout['state'] === 'checkout_started') {
				$this->transition_checkout_locked($checkout, 'abandoned', $reason, $now);
			}
			$this->transaction('COMMIT');
		} catch (Throwable $error) {
			$this->transaction('ROLLBACK', false);
			throw $error;
		}

		$this->paid->release_reservation(
			(int) $claim['offer_id'],
			(int) $claim['id'],
			(int) $reservation['id'],
			$reason,
			array('actor_type' => 'woo_session', 'provider' => 'woocommerce', 'actor_user_id' => 0)
		);
		$session = ($this->session_resolver)();
		if (is_object($session)) {
			$record = $this->session_record($session);
			if ($record && (string) ($record['checkout_public_id'] ?? '') === $checkout_public_id) {
				$record['active'] = false;
				$record['checkout_entered'] = false;
				$this->store_session_record($session, $record);
			}
		}
	}

	public function abandon_if_no_bound_items($cart = null, string $reason = 'cart_items_removed'): void
	{
		$session = ($this->session_resolver)();
		if (!is_object($session)) {
			return;
		}
		$record = $this->session_record($session);
		if (!$record || empty($record['active'])) {
			return;
		}
		$cart = $cart ?: ($this->cart_resolver)();
		foreach ((array) (is_object($cart) && is_callable(array($cart, 'get_cart')) ? $cart->get_cart() : array()) as $item) {
			$metadata = is_array($item) ? self::cart_item_metadata($item) : array();
			if ($metadata !== array() && (string) $metadata['checkout_public_id'] === (string) $record['checkout_public_id']) {
				return;
			}
		}
		$this->abandon((string) $record['checkout_public_id'], $reason);
	}

	/** @param array<string,mixed> $cart_item */
	public static function cart_item_metadata(array $cart_item): array
	{
		$value = $cart_item[self::CART_ITEM_KEY] ?? null;
		if (!is_array($value)) {
			return array();
		}
		$metadata = array(
			'version' => (int) ($value['version'] ?? 0),
			'claim_public_id' => trim((string) ($value['claim_public_id'] ?? '')),
			'checkout_public_id' => trim((string) ($value['checkout_public_id'] ?? '')),
			'event_plan_id' => absint($value['event_plan_id'] ?? 0),
		);
		if ($metadata['version'] !== self::BINDING_VERSION
			|| !preg_match('/^ac_[a-f0-9]{32}$/', $metadata['claim_public_id'])
			|| !preg_match('/^ax_[a-f0-9]{32}$/', $metadata['checkout_public_id'])
			|| $metadata['event_plan_id'] < 1) {
			return array();
		}
		return $metadata;
	}

	/** @return array<string,mixed> */
	public function metadata_from_session(?string $checkout_public_id = null): array
	{
		$session = ($this->session_resolver)();
		$record = is_object($session) ? $this->session_record($session) : null;
		if (!$record || empty($record['active'])) {
			return array();
		}
		if ($checkout_public_id !== null && !hash_equals((string) $record['checkout_public_id'], $checkout_public_id)) {
			return array();
		}
		return array(
			'version' => self::BINDING_VERSION,
			'claim_public_id' => (string) $record['claim_public_id'],
			'checkout_public_id' => (string) $record['checkout_public_id'],
			'event_plan_id' => (int) $record['event_plan_id'],
		);
	}

	/** @return array<string,mixed> */
	private function create_or_reuse_attempt(string $claim_public_id, string $access_secret, int $product_id, int $quantity, string $session_id, bool $recover, string $previous_checkout_public_id): array
	{
		$tables = bvmgr_admission_offers_table_names();
		$preview = $this->claim_preview($claim_public_id);
		if (!$preview) {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_activation_unavailable');
		}
		$now = $this->now();
		$this->transaction('START TRANSACTION');
		try {
			$offer = $this->lock_row($tables['offers'], (int) $preview['offer_id'], 'checkout_activation_unavailable');
			$claim = $this->lock_row($tables['claims'], (int) $preview['id'], 'checkout_activation_unavailable');
			$this->interrupt('after_claim_lock', array('claim_id' => (int) $claim['id']));
			$this->assert_claim_secret_and_state($offer, $claim, $claim_public_id, $access_secret, $now);
			$this->validate_product_allocation($claim, $product_id, $quantity);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Claim lock serializes active attempt selection.
			$reservations = $this->db->get_results($this->db->prepare('SELECT * FROM %i WHERE claim_id = %d ORDER BY id DESC FOR UPDATE', $tables['reservations'], (int) $claim['id']), ARRAY_A);
			$reservation = $this->select_current_reservation(is_array($reservations) ? $reservations : array());
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Claim lock serializes all Checkout attempts.
			$checkouts = $this->db->get_results($this->db->prepare('SELECT * FROM %i WHERE claim_id = %d ORDER BY id DESC FOR UPDATE', $tables['checkouts'], (int) $claim['id']), ARRAY_A);
			$checkouts = is_array($checkouts) ? $checkouts : array();
			$active = $this->active_checkout($checkouts);
			$recovered_active = false;
			$session_record = $this->session_record($this->require_session());

			if ($active) {
				if ($session_record
					&& $this->verify_binding($active, $claim, $session_record, $session_id)
					&& (int) ($session_record['product_id'] ?? 0) === $product_id
					&& (int) ($session_record['allocation_quantity'] ?? 0) === $quantity) {
					$this->transaction('COMMIT');
					return $this->attempt_result($offer, $claim, $reservation, $active, $session_record, true);
				}
				if (!$recover || !hash_equals((string) $active['public_id'], $previous_checkout_public_id)) {
					throw new BVMGR_Admission_Offer_Domain_Exception('checkout_session_conflict');
				}
				$this->transition_checkout_locked($active, 'abandoned', 'explicit_session_recovery', $now);
				$recovered_active = true;
				$active = null;
			}

			if ($recover && !$recovered_active && !$this->historical_checkout_matches($checkouts, $previous_checkout_public_id)) {
				throw new BVMGR_Admission_Offer_Domain_Exception('checkout_recovery_unavailable');
			}
			if (!$reservation || (string) ($reservation['state'] ?? '') !== 'held' || (string) ($reservation['expires_at'] ?? '') <= $now) {
				$this->transaction('COMMIT');
				if (!$recover) {
					throw new BVMGR_Admission_Offer_Domain_Exception('checkout_activation_unavailable');
				}
				return array(
					'needs_reacquisition' => true,
					'offer' => $offer,
					'claim' => $claim,
					'reservation' => $reservation,
				);
			}

			$attempt = $this->insert_checkout_locked($offer, $claim, $reservation, $product_id, $quantity, $session_id, $now);
			$this->transaction('COMMIT');
			return $attempt;
		} catch (Throwable $error) {
			$this->transaction('ROLLBACK', false);
			throw $error;
		}
	}

	/** @param array<string,mixed> $old @param array<string,mixed> $reservation @param array<string,mixed> $record @return array<string,mixed> */
	private function create_attempt_from_session_proof(array $old, array $reservation, array $record, int $product_id, int $quantity): array
	{
		$tables = bvmgr_admission_offers_table_names();
		$session_id = $this->session_id($this->require_session());
		$now = $this->now();
		$this->transaction('START TRANSACTION');
		try {
			$offer = $this->lock_row($tables['offers'], (int) $old['offer']['id'], 'checkout_restore_unavailable');
			$claim = $this->lock_row($tables['claims'], (int) $old['claim']['id'], 'checkout_restore_unavailable');
			$reservation = $this->lock_row($tables['reservations'], (int) $reservation['id'], 'checkout_restore_unavailable');
			$old_checkout = $this->lock_row($tables['checkouts'], (int) $old['checkout']['id'], 'checkout_restore_unavailable');
			if (!$this->verify_binding($old_checkout, $claim, $record, $session_id) || (string) $old_checkout['state'] !== 'abandoned') {
				throw new BVMGR_Admission_Offer_Domain_Exception('checkout_restore_unavailable');
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Claim lock guarantees one active attempt.
			$active = $this->db->get_row($this->db->prepare("SELECT * FROM %i WHERE claim_id = %d AND state = 'checkout_started' ORDER BY id DESC LIMIT 1 FOR UPDATE", $tables['checkouts'], (int) $claim['id']), ARRAY_A);
			if (is_array($active)) {
				throw new BVMGR_Admission_Offer_Domain_Exception('checkout_restore_unavailable');
			}
			$this->assert_live_context(array(
				'offer' => $offer,
				'claim' => $claim,
				'reservation' => $reservation,
				'checkout' => array_merge($old_checkout, array('reservation_id' => (int) $reservation['id'])),
			), false);
			$attempt = $this->insert_checkout_locked($offer, $claim, $reservation, $product_id, $quantity, $session_id, $now);
			$this->transaction('COMMIT');
			return $attempt;
		} catch (Throwable $error) {
			$this->transaction('ROLLBACK', false);
			throw $error;
		}
	}

	/** @param array<string,mixed> $offer @param array<string,mixed> $claim @param array<string,mixed> $reservation @return array<string,mixed> */
	private function insert_checkout_locked(array $offer, array $claim, array $reservation, int $product_id, int $quantity, string $session_id, string $now): array
	{
		$public_id = bvmgr_admission_offer_generate_public_id('ax');
		$nonce = bin2hex(random_bytes(24));
		$keyring = BVMGR_Admission_Offer_Identity_Keyring::load_or_create($this->db);
		$key_version = $keyring->active_version();
		$keys = $keyring->keys();
		$binding = $this->binding_hmac($public_id, (string) $claim['public_id'], $session_id, $nonce, $keys[$key_version]);
		$amounts = $this->checkout_amounts($product_id, $quantity);
		$checkout_id = (new BVMGR_Admission_Offer_Checkout_Repository($this->db))->create(array(
			'public_id' => $public_id,
			'offer_id' => (int) $offer['id'],
			'claim_id' => (int) $claim['id'],
			'reservation_id' => (int) $reservation['id'],
			'checkout_provider' => self::PROVIDER_PREFIX . $key_version,
			'checkout_ref' => $binding,
			'currency' => $amounts['currency'],
			'eligible_subtotal_minor' => $amounts['subtotal_minor'],
			'discount_minor' => 0,
			'total_minor' => $amounts['subtotal_minor'],
		), $now);
		$checkout = $this->lock_row(bvmgr_admission_offers_table('checkouts'), $checkout_id, 'checkout_insert_failed');
		(new BVMGR_Admission_Offer_Event_Repository($this->db))->append(array(
			'entity_type' => 'checkout',
			'entity_id' => $checkout_id,
			'event_key' => 'woocommerce-checkout-started',
			'previous_state' => null,
			'new_state' => 'checkout_started',
			'actor_type' => 'woo_session',
			'provider' => 'woocommerce',
			'payload' => array('event_plan_id' => (int) $claim['event_plan_id'], 'product_id' => $product_id, 'quantity' => $quantity, 'binding_version' => self::BINDING_VERSION),
		), $now);
		$record = array(
			'version' => self::BINDING_VERSION,
			'active' => true,
			'claim_public_id' => (string) $claim['public_id'],
			'checkout_public_id' => $public_id,
			'event_plan_id' => (int) $claim['event_plan_id'],
			'product_id' => $product_id,
			'allocation_quantity' => $quantity,
			'binding_nonce' => $nonce,
			'checkout_entered' => false,
		);
		return $this->attempt_result($offer, $claim, $reservation, $checkout, $record, false);
	}

	/** @param array<string,mixed> $attempt @return array<string,mixed> */
	private function reacquire_reservation(array $attempt, string $previous_checkout_public_id): array
	{
		$claim = $attempt['claim'];
		$offer = $attempt['offer'];
		$key = 'woo-recovery:' . (string) $claim['public_id'] . ':' . $previous_checkout_public_id;
		$service = new BVMGR_Admission_Offer_Capacity_Service(new BVMGR_Admission_Offer_WPDB_Capacity_Store($this->db));
		$reservation = $service->acquire(
			(int) $offer['id'],
			(int) $claim['id'],
			(int) $claim['event_plan_id'],
			(int) $claim['quantity'],
			$key,
			$this->now()
		);
		(new BVMGR_Admission_Offer_Event_Repository($this->db))->append(array(
			'entity_type' => 'reservation',
			'entity_id' => (int) $reservation['id'],
			'event_key' => 'paid-reservation-recovered',
			'previous_state' => null,
			'new_state' => 'held',
			'actor_type' => 'woo_session',
			'provider' => 'woocommerce',
			'payload' => array('claim_id' => (int) $claim['id']),
		), $this->now());
		return $reservation;
	}

	/** @param array<string,mixed> $attempt */
	private function bind_cart(array $attempt, int $product_id, int $quantity): string
	{
		$cart = $this->cart();
		$metadata = array(
			'version' => self::BINDING_VERSION,
			'claim_public_id' => (string) $attempt['claim']['public_id'],
			'checkout_public_id' => (string) $attempt['checkout']['public_id'],
			'event_plan_id' => (int) $attempt['claim']['event_plan_id'],
		);
		foreach ((array) $cart->get_cart() as $key => $item) {
			$item_metadata = is_array($item) ? self::cart_item_metadata($item) : array();
			if ($item_metadata === array()) {
				continue;
			}
			if ((string) $item_metadata['checkout_public_id'] === (string) $metadata['checkout_public_id']) {
				$this->validate_cart($cart, false);
				return (string) $key;
			}
			// A recovered browser binding replaces only the old bound line. The
			// guarded flag prevents removal hooks from releasing the new attempt.
			$GLOBALS['bvmgr_admission_offer_woo_internal_mutation'] = true;
			try {
				$cart->remove_cart_item((string) $key);
			} finally {
				$GLOBALS['bvmgr_admission_offer_woo_internal_mutation'] = false;
			}
		}
		$key = $cart->add_to_cart($product_id, $quantity, 0, array(), array(self::CART_ITEM_KEY => $metadata));
		if (!is_string($key) || $key === '') {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_cart_binding_failed');
		}
		return $key;
	}

	/** @param array<string,mixed> $claim */
	private function validate_product_allocation(array $claim, int $product_id, int $quantity): void
	{
		if ($quantity < 1 || $quantity > (int) ($claim['quantity'] ?? 0)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_quantity_exceeded');
		}
		$product = ($this->product_resolver)($product_id);
		if (!is_object($product) || !is_callable(array($product, 'get_id')) || (int) $product->get_id() !== $product_id) {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_product_not_eligible');
		}
		$event_plan_id = (int) ($claim['event_plan_id'] ?? 0);
		$legacy_event_resolver = 'v' . 'ms_ticketing_b_get_linked_tec_event_id';
		$legacy_product_resolver = 'v' . 'ms_ticketing_b_get_event_ticket_products';
		$legacy_meta_key_resolver = 'v' . 'ms_ticketing_v2_product_meta_key';
		$legacy_entitlement_resolver = 'v' . 'ms_ticketing_v2_product_is_entitlement';
		$event_resolver = function_exists('bvmgr_ticketing_b_get_linked_tec_event_id')
			? 'bvmgr_ticketing_b_get_linked_tec_event_id'
			: (function_exists($legacy_event_resolver) ? $legacy_event_resolver : null);
		$product_resolver = function_exists('bvmgr_ticketing_b_get_event_ticket_products')
			? 'bvmgr_ticketing_b_get_event_ticket_products'
			: (function_exists($legacy_product_resolver) ? $legacy_product_resolver : null);
		$tec_event_id = is_callable($event_resolver) ? (int) $event_resolver($event_plan_id) : 0;
		$product_ids = $tec_event_id > 0 && is_callable($product_resolver)
			? array_values(array_unique(array_map('absint', (array) $product_resolver($tec_event_id))))
			: array();
		$linked_event_id = (int) get_post_meta($product_id, '_tribe_wooticket_for_event', true);
		$meta_key_resolver = function_exists('bvmgr_ticketing_v2_product_meta_key')
			? 'bvmgr_ticketing_v2_product_meta_key'
			: (function_exists($legacy_meta_key_resolver) ? $legacy_meta_key_resolver : null);
		$plan_key = is_callable($meta_key_resolver) ? (string) $meta_key_resolver('event_plan_id') : '_vms_event_plan_id';
		$plan_marker = (int) get_post_meta($product_id, $plan_key ?: '_vms_event_plan_id', true);
		$entitlement_resolver = function_exists('bvmgr_ticketing_v2_product_is_entitlement')
			? 'bvmgr_ticketing_v2_product_is_entitlement'
			: (function_exists($legacy_entitlement_resolver) ? $legacy_entitlement_resolver : null);
		$is_entitlement = is_callable($entitlement_resolver) && (bool) $entitlement_resolver($product_id);
		if ($tec_event_id < 1 || !in_array($product_id, $product_ids, true) || $linked_event_id !== $tec_event_id
			|| ($plan_marker > 0 && $plan_marker !== $event_plan_id) || $is_entitlement) {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_product_not_eligible');
		}
	}

	/** @param array<string,mixed> $offer @param array<string,mixed> $claim */
	private function assert_claim_secret_and_state(array $offer, array $claim, string $claim_public_id, string $access_secret, string $now): void
	{
		$valid_secret = hash_equals((string) ($claim['access_secret_hash'] ?? ''), bvmgr_admission_offer_hash_secret($access_secret));
		if (!$valid_secret || (string) ($claim['public_id'] ?? '') !== $claim_public_id
			|| (string) ($claim['status'] ?? '') !== 'claimed'
			|| (string) ($offer['status'] ?? '') !== 'active'
			|| !in_array((string) ($offer['offer_type'] ?? ''), array('percent', 'fixed'), true)
			|| (!empty($claim['expires_at']) && (string) $claim['expires_at'] <= $now)
			|| (!empty($offer['claim_expires_at']) && (string) $offer['claim_expires_at'] <= $now)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_activation_unavailable');
		}
		try {
			(new BVMGR_Admission_Offer_Eligibility_Resolver($this->db))->resolve((int) $offer['id'], (int) $claim['event_plan_id']);
		} catch (Throwable $error) {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_activation_unavailable');
		}
		$this->assert_identity_state($offer, $claim);
	}

	/** @param array<string,mixed> $offer @param array<string,mixed> $claim */
	private function assert_identity_state(array $offer, array $claim): void
	{
		$policy = json_decode((string) ($offer['identity_policy_json'] ?? ''), true);
		$types = is_array($policy) ? ($policy['identity_types'] ?? $policy['types'] ?? array('email')) : array();
		$types = array_values(array_unique(array_filter(array_map('sanitize_key', is_array($types) ? $types : array()))));
		$table = bvmgr_admission_offers_table('identities');
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Identity authority is validated under the canonical Claim lock.
		$rows = $this->db->get_results($this->db->prepare('SELECT offer_id, identity_type, hash_key_version, identity_hash FROM %i WHERE claim_id = %d FOR UPDATE', $table, (int) $claim['id']), ARRAY_A);
		$keyring = BVMGR_Admission_Offer_Identity_Keyring::load_or_create($this->db);
		$keys = $keyring->keys();
		$by_type = array();
		foreach (is_array($rows) ? $rows : array() as $row) {
			$by_type[(string) ($row['identity_type'] ?? '')] = $row;
		}
		foreach ($types as $type) {
			$row = $by_type[$type] ?? null;
			$version = is_array($row) ? (int) ($row['hash_key_version'] ?? 0) : 0;
			if (!is_array($row) || (int) ($row['offer_id'] ?? 0) !== (int) $offer['id']
				|| !isset($keys[$version]) || !preg_match('/^[a-f0-9]{64}$/', (string) ($row['identity_hash'] ?? ''))) {
				throw new BVMGR_Admission_Offer_Domain_Exception('checkout_activation_unavailable');
			}
		}
	}

	/** @param array<string,mixed> $context */
	private function assert_live_context(array $context, bool $require_active_checkout = true): void
	{
		$now = $this->now();
		$offer = $context['offer'];
		$claim = $context['claim'];
		$reservation = $context['reservation'];
		$checkout = $context['checkout'];
		if ((string) $offer['status'] !== 'active' || (string) $claim['status'] !== 'claimed'
			|| (string) $reservation['state'] !== 'held' || (string) $reservation['expires_at'] <= $now
			|| ($require_active_checkout && !in_array((string) $checkout['state'], self::ACTIVE_CHECKOUT_STATES, true))
			|| (int) $claim['offer_id'] !== (int) $offer['id']
			|| (int) $reservation['claim_id'] !== (int) $claim['id']
			|| (int) $checkout['claim_id'] !== (int) $claim['id']
			|| (int) $checkout['reservation_id'] !== (int) $reservation['id']) {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_binding_invalid');
		}
	}

	/** @param array<string,mixed> $checkout @param array<string,mixed> $claim @param array<string,mixed> $record */
	private function verify_binding(array $checkout, array $claim, array $record, string $session_id): bool
	{
		if ((int) ($record['version'] ?? 0) !== self::BINDING_VERSION
			|| (string) ($record['claim_public_id'] ?? '') !== (string) ($claim['public_id'] ?? '')
			|| (string) ($record['checkout_public_id'] ?? '') !== (string) ($checkout['public_id'] ?? '')
			|| !preg_match('/^' . preg_quote(self::PROVIDER_PREFIX, '/') . '([1-9][0-9]*)$/', (string) ($checkout['checkout_provider'] ?? ''), $matches)) {
			return false;
		}
		$key_version = (int) $matches[1];
		$keyring = BVMGR_Admission_Offer_Identity_Keyring::load_or_create($this->db);
		$keys = $keyring->keys();
		$nonce = (string) ($record['binding_nonce'] ?? '');
		if (!isset($keys[$key_version]) || !preg_match('/^[a-f0-9]{48}$/', $nonce)) {
			return false;
		}
		$expected = $this->binding_hmac((string) $checkout['public_id'], (string) $claim['public_id'], $session_id, $nonce, $keys[$key_version]);
		return hash_equals((string) ($checkout['checkout_ref'] ?? ''), $expected);
	}

	private function binding_hmac(string $checkout_public_id, string $claim_public_id, string $session_id, string $nonce, string $key): string
	{
		return bvmgr_admission_offer_hmac(self::BINDING_DOMAIN . $checkout_public_id . "\0" . $claim_public_id . "\0" . $session_id . "\0" . $nonce, $key);
	}

	/** @return array{currency:string,subtotal_minor:int} */
	private function checkout_amounts(int $product_id, int $quantity): array
	{
		$product = ($this->product_resolver)($product_id);
		$price = is_object($product) && is_callable(array($product, 'get_price')) ? (float) $product->get_price() : 0.0;
		$minor = function_exists('wc_add_number_precision') ? (int) wc_add_number_precision($price * $quantity) : (int) round($price * $quantity * 100);
		$currency = function_exists('get_woocommerce_currency') ? strtoupper((string) get_woocommerce_currency()) : strtoupper((string) get_option('woocommerce_currency', ''));
		if ($minor < 1 || !preg_match('/^[A-Z]{3}$/', $currency)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_product_price_invalid');
		}
		return array('currency' => $currency, 'subtotal_minor' => $minor);
	}

	/** @param array<string,mixed> $context */
	private function renew_from_context(array $context, string $activity): void
	{
		$reservation = $this->paid->renew_reservation(
			(int) $context['offer']['id'],
			(int) $context['claim']['id'],
			(int) $context['reservation']['id'],
			$activity
		);
		if ((string) ($reservation['state'] ?? '') !== 'held') {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_binding_invalid');
		}
	}

	/** @return array<string,mixed>|null */
	private function checkout_context(string $checkout_public_id): ?array
	{
		if (!preg_match('/^ax_[a-f0-9]{32}$/', $checkout_public_id)) {
			return null;
		}
		$tables = bvmgr_admission_offers_table_names();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Checkout validation must use request-fresh authority rows.
		$checkout = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE public_id = %s LIMIT 1', $tables['checkouts'], $checkout_public_id), ARRAY_A);
		if (!is_array($checkout)) {
			return null;
		}
		$claim = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE id = %d', $tables['claims'], (int) $checkout['claim_id']), ARRAY_A);
		$offer = is_array($claim) ? $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE id = %d', $tables['offers'], (int) $claim['offer_id']), ARRAY_A) : null;
		$reservation = !empty($checkout['reservation_id']) ? $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE id = %d', $tables['reservations'], (int) $checkout['reservation_id']), ARRAY_A) : null;
		if (!is_array($claim) || !is_array($offer) || !is_array($reservation)) {
			return null;
		}
		$record = $this->session_record(($this->session_resolver)());
		return array('checkout' => $checkout, 'claim' => $claim, 'offer' => $offer, 'reservation' => $reservation, 'session_record' => $record ?: array());
	}

	/** @return array<string,mixed>|null */
	private function claim_preview(string $public_id): ?array
	{
		$table = bvmgr_admission_offers_table('claims');
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Untrusted public ID is resolved before canonical row locks.
		$row = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE public_id = %s LIMIT 1', $table, $public_id), ARRAY_A);
		return is_array($row) ? $row : null;
	}

	/** @return array<string,mixed>|null */
	private function latest_reservation(int $claim_id): ?array
	{
		$table = bvmgr_admission_offers_table('reservations');
		$row = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE claim_id = %d ORDER BY id DESC LIMIT 1', $table, $claim_id), ARRAY_A);
		return is_array($row) ? $row : null;
	}

	/** @param array<int,array<string,mixed>> $rows @return array<string,mixed>|null */
	private function select_current_reservation(array $rows): ?array
	{
		foreach ($rows as $row) {
			if ((string) ($row['state'] ?? '') === 'held') {
				return $row;
			}
		}
		return $rows[0] ?? null;
	}

	/** @param array<int,array<string,mixed>> $rows @return array<string,mixed>|null */
	private function active_checkout(array $rows): ?array
	{
		foreach ($rows as $row) {
			if (in_array((string) ($row['state'] ?? ''), self::ACTIVE_CHECKOUT_STATES, true)) {
				return $row;
			}
		}
		return null;
	}

	/** @param array<int,array<string,mixed>> $rows */
	private function historical_checkout_matches(array $rows, string $public_id): bool
	{
		foreach ($rows as $row) {
			if ((string) ($row['public_id'] ?? '') === $public_id && !in_array((string) ($row['state'] ?? ''), self::ACTIVE_CHECKOUT_STATES, true)) {
				return true;
			}
		}
		return false;
	}

	/** @param array<string,mixed> $checkout */
	private function transition_checkout_locked(array $checkout, string $to, string $reason, string $now): void
	{
		if (!bvmgr_admission_offer_state_transition_allowed('checkout', (string) $checkout['state'], $to)) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_checkout_transition');
		}
		$table = bvmgr_admission_offers_table('checkouts');
		$result = $this->db->query($this->db->prepare('UPDATE %i SET state = %s, state_version = state_version + 1, updated_at = %s WHERE id = %d AND state = %s AND state_version = %d', $table, $to, $now, (int) $checkout['id'], (string) $checkout['state'], (int) $checkout['state_version']));
		if ($result !== 1) {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_transition_failed');
		}
		(new BVMGR_Admission_Offer_Event_Repository($this->db))->append(array(
			'entity_type' => 'checkout', 'entity_id' => (int) $checkout['id'],
			'event_key' => 'woocommerce-checkout-' . $to,
			'previous_state' => (string) $checkout['state'], 'new_state' => $to,
			'actor_type' => 'woo_session', 'provider' => 'woocommerce',
			'payload' => array('reason_code' => $reason),
		), $now);
	}

	/** @return object */
	private function require_session()
	{
		$session = ($this->session_resolver)();
		if (!is_object($session) || !is_callable(array($session, 'get')) || !is_callable(array($session, 'set')) || !is_callable(array($session, 'get_customer_id'))) {
			throw new BVMGR_Admission_Offer_Domain_Exception('woocommerce_session_required');
		}
		$this->session_id($session);
		return $session;
	}

	private function session_id($session): string
	{
		$id = is_object($session) && is_callable(array($session, 'get_customer_id')) ? (string) $session->get_customer_id() : '';
		if ($id === '') {
			throw new BVMGR_Admission_Offer_Domain_Exception('woocommerce_session_required');
		}
		// Woo keys authenticated customer data by user ID, which can be shared by
		// multiple browsers. Add the current WordPress login-session token when it
		// exists so a second authenticated browser does not inherit this proof.
		$user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
		$login_token = $user_id > 0 && function_exists('wp_get_session_token') ? (string) wp_get_session_token() : '';
		if ($login_token !== '') {
			$id .= "\0wordpress-login\0" . $login_token;
		}
		return $id;
	}

	/** @return array<string,mixed>|null */
	private function session_record($session): ?array
	{
		if (!is_object($session) || !is_callable(array($session, 'get'))) {
			return null;
		}
		$record = $session->get(self::SESSION_KEY, null);
		return is_array($record) ? $record : null;
	}

	/** @param array<string,mixed> $record */
	private function store_session_record($session, array $record): void
	{
		$allowed = array('version', 'active', 'claim_public_id', 'checkout_public_id', 'event_plan_id', 'product_id', 'allocation_quantity', 'binding_nonce', 'checkout_entered');
		$record = array_intersect_key($record, array_fill_keys($allowed, true));
		$session->set(self::SESSION_KEY, $record);
	}

	/** @return object */
	private function cart()
	{
		$cart = ($this->cart_resolver)();
		if (!is_object($cart) || !is_callable(array($cart, 'get_cart')) || !is_callable(array($cart, 'add_to_cart'))) {
			throw new BVMGR_Admission_Offer_Domain_Exception('woocommerce_cart_required');
		}
		return $cart;
	}

	/** @param array<string,mixed>|null $reservation @param array<string,mixed> $record @return array<string,mixed> */
	private function attempt_result(array $offer, array $claim, ?array $reservation, array $checkout, array $record, bool $reused): array
	{
		return array(
			'offer' => $offer,
			'claim' => $claim,
			'reservation' => $reservation,
			'checkout' => $checkout,
			'session_record' => $record,
			'reused' => $reused,
		);
	}

	/** @return array<string,mixed> */
	private function lock_row(string $table, int $id, string $error_code): array
	{
		$row = $this->db->get_row($this->db->prepare('SELECT * FROM %i WHERE id = %d FOR UPDATE', $table, $id), ARRAY_A);
		if (!is_array($row)) {
			throw new BVMGR_Admission_Offer_Domain_Exception($error_code);
		}
		return $row;
	}

	private function positive_int($value): int
	{
		if (is_bool($value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
			return 0;
		}
		return max(0, (int) $value);
	}

	private function now(): string
	{
		$now = bvmgr_admission_offer_nullable_datetime((string) ($this->clock)());
		if ($now === null) {
			throw new BVMGR_Admission_Offer_Domain_Exception('invalid_clock');
		}
		return $now;
	}

	private function transaction(string $sql, bool $throw = true): void
	{
		if ($this->db->query($sql) === false && $throw) {
			throw new BVMGR_Admission_Offer_Domain_Exception('checkout_transaction_failed');
		}
	}

	/** @param array<string,mixed> $context */
	private function interrupt(string $point, array $context): void
	{
		if ($this->interrupt !== null) {
			($this->interrupt)($point, $context);
		}
	}
}

if (!function_exists('bvmgr_admission_offer_woocommerce_checkout_service')) {
	function bvmgr_admission_offer_woocommerce_checkout_service(): BVMGR_Admission_Offer_Woo_Checkout_Service
	{
		static $service = null;
		if (!$service instanceof BVMGR_Admission_Offer_Woo_Checkout_Service) {
			$service = new BVMGR_Admission_Offer_Woo_Checkout_Service();
		}
		return $service;
	}
}

if (!function_exists('bvmgr_admission_offer_woocommerce_activate')) {
	/** @param array<string,mixed> $request @return array<string,mixed> */
	function bvmgr_admission_offer_woocommerce_activate(array $request): array
	{
		return bvmgr_admission_offer_woocommerce_checkout_service()->activate($request);
	}
}
