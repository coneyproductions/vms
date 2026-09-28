<?php
declare(strict_types=1);

/** @param callable(bool,string):void $assert @return array<string,mixed> */
function bvmgr_admission_offers_phase_c2_real_certify(callable $assert, string $wordpress_root): array
{
	global $wpdb;
	$focused_assertions = 0;
	$base_assert = $assert;
	$assert = static function (bool $condition, string $message) use (&$focused_assertions, $base_assert): void {
		$focused_assertions++;
		$base_assert($condition, $message);
	};
	$tables = bvmgr_admission_offers_table_names();
	foreach (array_reverse($tables) as $table) $wpdb->query("DELETE FROM {$table}");
	wp_set_current_user(1);
	update_option('woocommerce_currency', 'USD', false);
	$assert(defined('WC_VERSION') && WC_VERSION === '11.1.2', 'C2 certification requires WooCommerce 11.1.2.');

	$mail_calls = array();
	$http_calls = array();
	$mail_filter = static function ($pre, array $atts) use (&$mail_calls) { $mail_calls[] = array_keys($atts); return true; };
	$http_filter = static function ($pre, array $args, string $url) use (&$http_calls) { $http_calls[] = parse_url($url, PHP_URL_HOST); return new WP_Error('c2_network_blocked', 'C2 certification blocks outbound HTTP.'); };
	add_filter('pre_wp_mail', $mail_filter, 10, 2);
	add_filter('pre_http_request', $http_filter, 10, 3);

	$venue_id = wp_insert_post(array('post_type' => 'vms_venue', 'post_status' => 'publish', 'post_title' => 'Phase C2 Venue'));
	$event_id = wp_insert_post(array('post_type' => 'vms_event_plan', 'post_status' => 'publish', 'post_title' => 'Phase C2 Event'));
	update_post_meta($event_id, '_vms_venue_id', $venue_id);
	update_post_meta($event_id, '_vms_event_date', '2026-12-15');
	update_post_meta($event_id, function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status', 'published');
	$tec_event_id = wp_insert_post(array('post_type' => 'tribe_events', 'post_status' => 'publish', 'post_title' => 'Phase C2 TEC Event'));
	$other_tec_event_id = wp_insert_post(array('post_type' => 'tribe_events', 'post_status' => 'publish', 'post_title' => 'Phase C2 Other TEC Event'));
	update_post_meta($event_id, function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'tec_event_id') : '_vms_tec_event_id', $tec_event_id);

	$make_product = static function (string $name, string $price, int $linked_event = 0, int $plan_id = 0): int {
		$product = new WC_Product_Simple();
		$product->set_name($name);
		$product->set_status('publish');
		$product->set_regular_price($price);
		$product->set_price($price);
		$product->set_catalog_visibility('visible');
		$product->set_manage_stock(false);
		$product_id = $product->save();
		if ($linked_event > 0) update_post_meta($product_id, '_tribe_wooticket_for_event', $linked_event);
		if ($plan_id > 0) {
			$key = function_exists('bvmgr_ticketing_v2_product_meta_key') ? bvmgr_ticketing_v2_product_meta_key('event_plan_id') : '_vms_event_plan_id';
			update_post_meta($product_id, $key, $plan_id);
		}
		return $product_id;
	};
	$ticket_product_id = $make_product('Phase C2 GA', '50.00', $tec_event_id, $event_id);
	$wrong_event_product_id = $make_product('Phase C2 Wrong Event', '45.00', $other_tec_event_id, 999999);
	$ordinary_product_id = $make_product('Phase C2 Concession', '7.50');
	$product_price_before = (string) wc_get_product($ticket_product_id)->get_price();
	wp_set_current_user(0);

	$offer_repo = new BVMGR_Admission_Offer_Repository($wpdb);
	$eligibility_repo = new BVMGR_Admission_Offer_Eligibility_Repository($wpdb);
	$sequence = 0;
	$make_offer = static function (array $overrides = array()) use (&$sequence, $offer_repo, $eligibility_repo): int {
		$sequence++;
		$input = array_merge(array(
			'name' => 'Phase C2 Offer ' . $sequence,
			'offer_type' => 'percent',
			'percent_basis_points' => 2500,
			'status' => 'active',
			'max_qty_per_claim' => 2,
			'capacity_total' => 20,
			'reservation_ttl_seconds' => 1200,
			'identity_policy' => array('identity_types' => array('email'), 'identity_scope' => 'offer'),
		), $overrides);
		if (($input['offer_type'] ?? '') === 'fixed') {
			$input['percent_basis_points'] = null;
			$input['fixed_amount_minor'] = $input['fixed_amount_minor'] ?? 500;
			$input['currency'] = 'USD';
		}
		$id = $offer_repo->create(BVMGR_Admission_Offer_Value::from_array($input, 'USD'), 1);
		$eligibility_repo->create($id, BVMGR_Admission_Offer_Eligibility_Value::from_array(array('scope_type' => 'any_event', 'mode' => 'include')), 1);
		return $id;
	};
	$request_no = 0;
	$make_claim = static function (int $offer_id, int $quantity, string $label) use (&$request_no, $event_id): array {
		$request_no++;
		$secret = hash('sha256', 'phase-c2-secret-' . $label . '-' . $request_no);
		$request = array(
			'offer_id' => $offer_id,
			'event_plan_id' => $event_id,
			'quantity' => $quantity,
			'idempotency_key' => 'phase-c2-idem-' . $label . '-' . $request_no . '-000000000000',
			'access_secret' => $secret,
			'claimant_identity' => array('first_name' => 'C2', 'last_name' => 'Guest', 'email' => $label . '-' . $request_no . '@example.invalid'),
			'distribution_context' => array('provider' => 'phase_c2_fixture', 'mode' => 'test_fixture'),
			'actor_user_id' => 1,
		);
		$result = (new BVMGR_Admission_Offer_Paid_Claim_Service())->claim($request);
		return array('secret' => $secret, 'result' => $result);
	};
	$make_woo = static function (): array {
		$session = new WC_Session_Handler();
		$session->init_session_cookie();
		$cart = new WC_Cart();
		WC()->session = $session;
		WC()->cart = $cart;
		return array($session, $cart);
	};
	$service_for = static function ($session, $cart) use ($wpdb): BVMGR_Admission_Offer_Woo_Checkout_Service {
		return new BVMGR_Admission_Offer_Woo_Checkout_Service($wpdb, null, static fn() => $session, static fn() => $cart);
	};
	$rejects = static function (callable $callback, string $code) use ($assert): void {
		try { $callback(); $assert(false, 'Expected rejection: ' . $code); }
		catch (BVMGR_Admission_Offer_Domain_Exception $error) { $assert($error->getMessage() === $code, 'Wrong rejection for ' . $code . ': ' . $error->getMessage()); }
	};
	$count_orders = static function () use ($wpdb): array {
		$wc_orders = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'wc_orders')));
		return array(
			'orders' => $wc_orders ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders") : (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='shop_order'"),
			'order_items' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_order_items"),
		);
	};
	$negative_snapshot = static function () use ($wpdb, $tables, $count_orders): array {
		$entries = function_exists('bvmgr_admission_table_entries') ? bvmgr_admission_table_entries() : '';
		$pass_claims = function_exists('bvmgr_admission_table_pass_claims') ? bvmgr_admission_table_pass_claims() : '';
		$table_counts = static function (string $pattern) use ($wpdb): array {
			$result = array();
			foreach ((array) $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $pattern)) as $table) {
				$result[(string) $table] = (int) $wpdb->get_var('SELECT COUNT(*) FROM `' . str_replace('`', '``', (string) $table) . '`');
			}
			ksort($result);
			return $result;
		};
		return array_merge($count_orders(), array(
			'allocations' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['allocations']}"),
			'fulfillments' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['fulfillments']}"),
			'admissions' => $entries ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$entries}") : 0,
			'pass_claims' => $pass_claims ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$pass_claims}") : 0,
			'attendees' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('tribe_tpp_attendees','tribe_wooticket','tribe_rsvp_attendees')"),
			'coupons' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='shop_coupon'"),
			'outreach_tables' => $table_counts('%outreach%'),
			'outreach_options' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", '%outreach%')),
			'discount_tables' => $table_counts('%discount%'),
			'discount_options' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", '%discount%')),
			'square_tables' => $table_counts('%square%'),
			'square_options' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", '%square%')),
		));
	};
	$assert(
		has_filter('woocommerce_add_to_cart_validation', 'bvmgr_admission_offer_woo_add_to_cart_validation')
		&& has_action('woocommerce_store_api_validate_add_to_cart', 'bvmgr_admission_offer_woo_store_validate_add')
		&& has_filter('woocommerce_store_api_add_to_cart_data', 'bvmgr_admission_offer_woo_store_api_add_to_cart_data')
		&& has_filter('woocommerce_get_cart_item_from_session', 'bvmgr_admission_offer_woo_restore_cart_item_data')
		&& has_action('woocommerce_cart_loaded_from_session', 'bvmgr_admission_offer_woo_cart_loaded')
		&& has_action('woocommerce_check_cart_items', 'bvmgr_admission_offer_woo_check_cart_items')
		&& has_action('woocommerce_store_api_cart_errors', 'bvmgr_admission_offer_woo_store_cart_errors')
		&& has_filter('woocommerce_update_cart_validation', 'bvmgr_admission_offer_woo_update_cart_validation')
		&& has_action('woocommerce_after_cart_item_quantity_update', 'bvmgr_admission_offer_woo_quantity_updated')
		&& has_action('woocommerce_cart_item_removed', 'bvmgr_admission_offer_woo_item_removed')
		&& has_action('woocommerce_cart_emptied', 'bvmgr_admission_offer_woo_cart_emptied')
		&& has_action('woocommerce_cart_item_restored', 'bvmgr_admission_offer_woo_item_restored')
		&& has_action('woocommerce_after_checkout_validation', 'bvmgr_admission_offer_woo_classic_checkout_barrier'),
		'C2 must register the certified classic and Store API cart/session hook set.'
	);

	// Activation rejects stale and terminal authority with the same generic error.
	$expired_claim_fixture = $make_claim($make_offer(), 1, 'expired-claim');
	$wpdb->update($tables['claims'], array('expires_at' => gmdate('Y-m-d H:i:s', time() - 60)), array('id' => (int) $expired_claim_fixture['result']['claim']['id']), array('%s'), array('%d'));
	[$expired_claim_session, $expired_claim_cart] = $make_woo();
	$rejects(static fn() => $service_for($expired_claim_session, $expired_claim_cart)->activate(array(
		'claim_public_id' => $expired_claim_fixture['result']['claim']['public_id'], 'access_secret' => $expired_claim_fixture['secret'],
		'product_id' => $ticket_product_id, 'quantity' => 1,
	)), 'checkout_activation_unavailable');
	$expired_reservation_fixture = $make_claim($make_offer(), 1, 'expired-reservation');
	$wpdb->update($tables['reservations'], array('expires_at' => gmdate('Y-m-d H:i:s', time() - 60)), array('id' => (int) $expired_reservation_fixture['result']['reservation']['id']), array('%s'), array('%d'));
	[$expired_reservation_session, $expired_reservation_cart] = $make_woo();
	$rejects(static fn() => $service_for($expired_reservation_session, $expired_reservation_cart)->activate(array(
		'claim_public_id' => $expired_reservation_fixture['result']['claim']['public_id'], 'access_secret' => $expired_reservation_fixture['secret'],
		'product_id' => $ticket_product_id, 'quantity' => 1,
	)), 'checkout_activation_unavailable');
	$assert((string) $wpdb->get_var($wpdb->prepare("SELECT state FROM {$tables['reservations']} WHERE id=%d", (int) $expired_reservation_fixture['result']['reservation']['id'])) === 'expired', 'Expired activation must preserve the Reservation as expired history.');
	$released_fixture = $make_claim($make_offer(), 1, 'released-reservation');
	$release_service = new BVMGR_Admission_Offer_Paid_Claim_Service($wpdb, null, null, static fn(): bool => true);
	$release_service->release_reservation(
		(int) $released_fixture['result']['claim']['offer_id'],
		(int) $released_fixture['result']['claim']['id'],
		(int) $released_fixture['result']['reservation']['id'],
		'c2_released_fixture',
		array('actor_type' => 'distribution_provider', 'provider' => 'phase_c2_fixture', 'actor_user_id' => 1)
	);
	[$released_session, $released_cart] = $make_woo();
	$rejects(static fn() => $service_for($released_session, $released_cart)->activate(array(
		'claim_public_id' => $released_fixture['result']['claim']['public_id'], 'access_secret' => $released_fixture['secret'],
		'product_id' => $ticket_product_id, 'quantity' => 1,
	)), 'checkout_activation_unavailable');
	$terminal_fixture = $make_claim($make_offer(), 1, 'terminal-claim');
	$wpdb->update($tables['claims'], array('status' => 'canceled'), array('id' => (int) $terminal_fixture['result']['claim']['id']), array('%s'), array('%d'));
	[$terminal_session, $terminal_cart] = $make_woo();
	$rejects(static fn() => $service_for($terminal_session, $terminal_cart)->activate(array(
		'claim_public_id' => $terminal_fixture['result']['claim']['public_id'], 'access_secret' => $terminal_fixture['secret'],
		'product_id' => $ticket_product_id, 'quantity' => 1,
	)), 'checkout_activation_unavailable');
	$identity_fixture = $make_claim($make_offer(), 1, 'missing-identity');
	$wpdb->delete($tables['identities'], array('claim_id' => (int) $identity_fixture['result']['claim']['id']), array('%d'));
	[$identity_session, $identity_cart] = $make_woo();
	$rejects(static fn() => $service_for($identity_session, $identity_cart)->activate(array(
		'claim_public_id' => $identity_fixture['result']['claim']['public_id'], 'access_secret' => $identity_fixture['secret'],
		'product_id' => $ticket_product_id, 'quantity' => 1,
	)), 'checkout_activation_unavailable');

	$percent_offer = $make_offer();
	$percent_claim = $make_claim($percent_offer, 2, 'percent');
	$claim = $percent_claim['result']['claim'];
	$reservation = $percent_claim['result']['reservation'];
	$before_negative = $negative_snapshot();
	[$session_a, $cart_a] = $make_woo();
	$service_a = $service_for($session_a, $cart_a);
	$activation_request = array(
		'claim_public_id' => $claim['public_id'],
		'access_secret' => $percent_claim['secret'],
		'product_id' => $ticket_product_id,
		'quantity' => 2,
	);
	$activated = $service_a->activate($activation_request);
	$checkout_id = (int) $activated['checkout']['id'];
	$checkout_public_id = (string) $activated['checkout']['public_id'];
	$assert((string) $activated['claim']['status'] === 'claimed' && (string) $activated['reservation']['state'] === 'held' && (string) $activated['checkout']['state'] === 'checkout_started', 'Activation must end at claimed + held + checkout_started.');
	$assert((int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['checkouts']}") === 1, 'Activation must create exactly one Checkout.');
	$assert(count($cart_a->get_cart()) === 1 && array_sum(array_column($cart_a->get_cart(), 'quantity')) === 2, 'Activation must bind the explicit quantity to the real Woo cart.');
	$session_record = $session_a->get(BVMGR_Admission_Offer_Woo_Checkout_Service::SESSION_KEY);
	$cart_item = reset($cart_a->cart_contents);
	$metadata = BVMGR_Admission_Offer_Woo_Checkout_Service::cart_item_metadata($cart_item);
	$assert(array_keys($metadata) === array('version', 'claim_public_id', 'checkout_public_id', 'event_plan_id'), 'Cart metadata must contain only neutral pointers.');
	$assert(!str_contains(serialize($session_record), $percent_claim['secret']) && !str_contains(serialize($cart_item), $percent_claim['secret']), 'Raw Claim secret must be absent from Woo session and cart.');
	$assert(!str_contains((string) $activated['checkout']['checkout_ref'], $session_a->get_customer_id()) && strlen((string) $activated['checkout']['checkout_ref']) === 64, 'Checkout must persist only an HMAC, never the raw session ID.');
	$cart_a->calculate_totals();
	$priced_item = $cart_a->get_cart_item((string) array_key_first($cart_a->get_cart()));
	$assert((float) $priced_item['line_subtotal'] === 100.0 && (float) $priced_item['line_total'] === 100.0
		&& (float) $cart_a->get_discount_total() === 0.0 && $cart_a->get_fees() === array(),
		'C2 must preserve catalog line values and add no discount, coupon, or fee.');

	$replay = $service_a->activate($activation_request);
	$assert(!empty($replay['reused']) && (int) $replay['checkout']['id'] === $checkout_id && count($cart_a->get_cart()) === 1, 'Same-session tabs must reuse one Checkout and cart binding.');
	$bad_secret = $activation_request; $bad_secret['access_secret'] = hash('sha256', 'wrong-secret');
	$rejects(static fn() => $service_a->activate($bad_secret), 'checkout_activation_unavailable');
	$wrong_product = $activation_request; $wrong_product['product_id'] = $wrong_event_product_id;
	$rejects(static fn() => $service_a->activate($wrong_product), 'checkout_product_not_eligible');
	$arbitrary_product = $activation_request; $arbitrary_product['product_id'] = $ordinary_product_id;
	$rejects(static fn() => $service_a->activate($arbitrary_product), 'checkout_product_not_eligible');
	$over = $activation_request; $over['quantity'] = 3;
	$rejects(static fn() => $service_a->activate($over), 'checkout_quantity_exceeded');
	$missing = $activation_request; unset($missing['product_id']);
	$rejects(static fn() => $service_a->activate($missing), 'checkout_activation_unavailable');

	[$session_b, $cart_b] = $make_woo();
	$service_b = $service_for($session_b, $cart_b);
	$rejects(static fn() => $service_b->activate($activation_request), 'checkout_session_conflict');
	$stolen = array(
		'product_id' => $ticket_product_id,
		'quantity' => 2,
		BVMGR_Admission_Offer_Woo_Checkout_Service::CART_ITEM_KEY => $metadata,
	);
	$rejects(static fn() => $service_b->validate_cart_item($stolen), 'checkout_binding_invalid');

	WC()->session = $session_a; WC()->cart = $cart_a;
	$item_key = (string) array_key_first($cart_a->get_cart());
	$before_read_version = (int) $wpdb->get_var($wpdb->prepare("SELECT state_version FROM {$tables['reservations']} WHERE id=%d", (int) $reservation['id']));
	$service_a->validate_cart($cart_a, false);
	$cart_a->calculate_totals();
	$after_read_version = (int) $wpdb->get_var($wpdb->prepare("SELECT state_version FROM {$tables['reservations']} WHERE id=%d", (int) $reservation['id']));
	$assert($before_read_version === $after_read_version, 'Cart display/totals validation must not renew.');
	$short_expiry = gmdate('Y-m-d H:i:s', time() + 60);
	$wpdb->update($tables['reservations'], array('expires_at' => $short_expiry), array('id' => (int) $reservation['id']), array('%s'), array('%d'));
	$semantic_version = (int) $wpdb->get_var($wpdb->prepare("SELECT state_version FROM {$tables['reservations']} WHERE id=%d", (int) $reservation['id']));
	$cart_a->set_quantity($item_key, 1, false);
	$assert((int) $cart_a->get_cart_item($item_key)['quantity'] === 1 && !$service_a->validate_cart($cart_a, false)['complete'], 'Quantity one may remain in cart but must be incomplete.');
	$renewed_reservation = $wpdb->get_row($wpdb->prepare("SELECT expires_at, state_version FROM {$tables['reservations']} WHERE id=%d", (int) $reservation['id']), ARRAY_A);
	$assert((string) $renewed_reservation['expires_at'] > $short_expiry && (int) $renewed_reservation['state_version'] > $semantic_version, 'A valid semantic quantity change must renew the held Reservation.');
	$cart_a->set_quantity($item_key, 2, false);
	$assert($service_a->validate_cart($cart_a, true)['complete'], 'Quantity two must be complete.');
	$version_before_invalid = (int) $wpdb->get_var($wpdb->prepare("SELECT state_version FROM {$tables['reservations']} WHERE id=%d", (int) $reservation['id']));
	$cart_a->set_quantity($item_key, 3, false);
	$assert((int) $cart_a->get_cart_item($item_key)['quantity'] === 2, 'Excessive quantity must be reverted.');
	$assert((int) $wpdb->get_var($wpdb->prepare("SELECT state_version FROM {$tables['reservations']} WHERE id=%d", (int) $reservation['id'])) === $version_before_invalid, 'Invalid quantity must not renew.');

	$session_a->set('cart', (new WC_Cart_Session($cart_a))->get_cart_for_session());
	$restored_cart = new WC_Cart();
	WC()->session = $session_a; WC()->cart = $restored_cart;
	$restore_version = (int) $wpdb->get_var($wpdb->prepare("SELECT state_version FROM {$tables['reservations']} WHERE id=%d", (int) $reservation['id']));
	(new WC_Cart_Session($restored_cart))->get_cart_from_session();
	$assert(count($restored_cart->get_cart()) === 1 && $service_for($session_a, $restored_cart)->validate_cart($restored_cart, true)['complete'], 'Real Woo cart-session restoration must preserve a valid binding.');
	$assert((int) $wpdb->get_var($wpdb->prepare("SELECT state_version FROM {$tables['reservations']} WHERE id=%d", (int) $reservation['id'])) === $restore_version, 'Session restoration must not renew.');
	$cart_a = $restored_cart; $service_a = $service_for($session_a, $cart_a);
	$restored_item = $cart_a->get_cart_item((string) array_key_first($cart_a->get_cart()));
	$tampered_event = $restored_item;
	$tampered_event[BVMGR_Admission_Offer_Woo_Checkout_Service::CART_ITEM_KEY]['event_plan_id'] = $event_id + 999;
	$rejects(static fn() => $service_a->validate_cart_item($tampered_event), 'checkout_binding_invalid');

	$classic_errors = new WP_Error();
	$orders_before_barrier = $count_orders();
	$checkout_short_expiry = gmdate('Y-m-d H:i:s', time() + 60);
	$wpdb->update($tables['reservations'], array('expires_at' => $checkout_short_expiry), array('id' => (int) $reservation['id']), array('%s'), array('%d'));
	bvmgr_admission_offer_woo_classic_checkout_barrier(array(), $classic_errors);
	$assert($classic_errors->get_error_code() === 'bvmgr_admission_offer_c2_barrier' && $count_orders() === $orders_before_barrier, 'Classic Offer checkout must stop before order creation.');
	$checkout_entry = $wpdb->get_row($wpdb->prepare("SELECT expires_at, state_version FROM {$tables['reservations']} WHERE id=%d", (int) $reservation['id']), ARRAY_A);
	$assert((string) $checkout_entry['expires_at'] > $checkout_short_expiry, 'First validated checkout entry must renew the held Reservation.');
	$store_errors = new WP_Error();
	$GLOBALS['bvmgr_admission_offer_store_checkout_post'] = true;
	bvmgr_admission_offer_woo_store_cart_errors($store_errors, $cart_a);
	$GLOBALS['bvmgr_admission_offer_store_checkout_post'] = false;
	$assert($store_errors->get_error_code() === 'bvmgr_admission_offer_c2_barrier' && $count_orders() === $orders_before_barrier, 'Store API Offer checkout must stop before order creation.');
	$assert((int) $wpdb->get_var($wpdb->prepare("SELECT state_version FROM {$tables['reservations']} WHERE id=%d", (int) $reservation['id'])) === (int) $checkout_entry['state_version'], 'Duplicate checkout validation must not renew more than once per session.');

	WC()->session = $session_b; WC()->cart = $cart_b;
	$recovery_request = $activation_request + array('recover' => true, 'previous_checkout_public_id' => $checkout_public_id);
	$recovered = $service_b->activate($recovery_request);
	$assert((int) $recovered['checkout']['id'] !== $checkout_id && (int) $recovered['reservation']['id'] === (int) $reservation['id'], 'Explicit session recovery must replace the Checkout while retaining a live held Reservation.');
	$assert((string) $wpdb->get_var($wpdb->prepare("SELECT state FROM {$tables['checkouts']} WHERE id=%d", $checkout_id)) === 'abandoned', 'Recovery must retain the old Checkout as abandoned history.');
	$assert((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['checkouts']} WHERE claim_id=%d AND state='checkout_started'", (int) $claim['id'])) === 1, 'Recovery must leave exactly one active Checkout.');
	$rejects(static fn() => $service_a->validate_cart($cart_a, false), 'checkout_binding_invalid');

	$recovered_key = (string) array_key_first($cart_b->get_cart());
	$recovered_checkout_id = (int) $recovered['checkout']['id'];
	$cart_b->remove_cart_item($recovered_key);
	$assert((string) $wpdb->get_var($wpdb->prepare("SELECT state FROM {$tables['checkouts']} WHERE id=%d", $recovered_checkout_id)) === 'abandoned', 'Last bound item removal must abandon the Checkout.');
	$assert((string) $wpdb->get_var($wpdb->prepare("SELECT state FROM {$tables['reservations']} WHERE id=%d", (int) $reservation['id'])) === 'released', 'Last bound item removal must release capacity.');
	$assert((string) $wpdb->get_var($wpdb->prepare("SELECT status FROM {$tables['claims']} WHERE id=%d", (int) $claim['id'])) === 'claimed', 'Removal must retain the durable Claim.');
	$cart_b->restore_cart_item($recovered_key);
	$restored_item = $cart_b->get_cart_item($recovered_key);
	$restored_metadata = BVMGR_Admission_Offer_Woo_Checkout_Service::cart_item_metadata($restored_item);
	$assert($restored_metadata !== array() && $restored_metadata['checkout_public_id'] !== (string) $recovered['checkout']['public_id'], 'Undo remove must create a new Checkout binding, never revive the released one.');
	$new_reservation_id = (int) $wpdb->get_var($wpdb->prepare("SELECT reservation_id FROM {$tables['checkouts']} WHERE public_id=%s", $restored_metadata['checkout_public_id']));
	$assert($new_reservation_id !== (int) $reservation['id'] && (string) $wpdb->get_var($wpdb->prepare("SELECT state FROM {$tables['reservations']} WHERE id=%d", $new_reservation_id)) === 'held', 'Undo remove must atomically acquire a new held Reservation.');

	// Actual Store API CartController add/update/validation path.
	$GLOBALS['bvmgr_admission_offer_woo_internal_mutation'] = true;
	try { $cart_b->remove_cart_item($recovered_key); } finally { $GLOBALS['bvmgr_admission_offer_woo_internal_mutation'] = false; }
	$active_meta = $service_b->metadata_from_session($restored_metadata['checkout_public_id']);
	$controller = new Automattic\WooCommerce\StoreApi\Utilities\CartController();
	$store_key = $controller->add_to_cart(array('id' => $ticket_product_id, 'quantity' => 2, 'cart_item_data' => array(BVMGR_Admission_Offer_Woo_Checkout_Service::CART_ITEM_KEY => $active_meta)));
	$assert(is_string($store_key) && $service_b->validate_cart($cart_b, true)['complete'], 'Actual Store API CartController add must preserve the binding.');
	$controller->set_cart_item_quantity($store_key, 1);
	$assert((int) $cart_b->get_cart_item($store_key)['quantity'] === 1, 'Store API quantity update must allow a valid incomplete cart.');
	$controller->set_cart_item_quantity($store_key, 2);
	$assert($service_b->validate_cart($cart_b, true)['complete'], 'Store API quantity update must restore completeness.');
	try {
		$controller->set_cart_item_quantity($store_key, 3);
	} catch (Throwable $error) {
		// Store API requests convert this same rejection into a route exception.
	}
	$assert((int) $cart_b->get_cart_item($store_key)['quantity'] === 2, 'Store API excessive quantity must be rejected and reverted.');

	// Ordinary Store API and classic carts remain unaffected by the C2 barrier.
	[$ordinary_session, $ordinary_cart] = $make_woo();
	$ordinary_controller = new Automattic\WooCommerce\StoreApi\Utilities\CartController();
	$ordinary_key = $ordinary_controller->add_to_cart(array('id' => $ordinary_product_id, 'quantity' => 1, 'cart_item_data' => array()));
	$ordinary_errors = new WP_Error();
	$GLOBALS['bvmgr_admission_offer_store_checkout_post'] = true;
	bvmgr_admission_offer_woo_store_cart_errors($ordinary_errors, $ordinary_cart);
	$GLOBALS['bvmgr_admission_offer_store_checkout_post'] = false;
	$ordinary_classic_errors = new WP_Error();
	bvmgr_admission_offer_woo_classic_checkout_barrier(array(), $ordinary_classic_errors);
	$ordinary_cart->calculate_totals();
	$assert(is_string($ordinary_key) && !$ordinary_errors->has_errors() && !$ordinary_classic_errors->has_errors()
		&& (float) $ordinary_cart->get_total('edit') === 7.5 && (float) $ordinary_cart->get_discount_total() === 0.0,
		'Ordinary Store API and classic carts must remain unblocked and retain ordinary totals.');

	// Fixed-value Claims use the same provider-neutral binding without pricing mutation.
	$fixed_offer = $make_offer(array('offer_type' => 'fixed', 'fixed_amount_minor' => 500));
	$fixed_claim = $make_claim($fixed_offer, 1, 'fixed');
	[$fixed_session, $fixed_cart] = $make_woo();
	$fixed_service = $service_for($fixed_session, $fixed_cart);
	$fixed = $fixed_service->activate(array('claim_public_id' => $fixed_claim['result']['claim']['public_id'], 'access_secret' => $fixed_claim['secret'], 'product_id' => $ticket_product_id, 'quantity' => 1));
	$assert((string) $fixed['checkout']['state'] === 'checkout_started' && count($fixed_cart->get_cart()) === 1, 'Fixed Claim must bind through the same C2 path.');

	// Empty-cart abandonment releases only the Offer hold; the Claim remains durable.
	$empty_claim = $make_claim($make_offer(), 1, 'empty-cart');
	[$empty_session, $empty_cart] = $make_woo();
	$empty_service = $service_for($empty_session, $empty_cart);
	$empty_attempt = $empty_service->activate(array(
		'claim_public_id' => $empty_claim['result']['claim']['public_id'], 'access_secret' => $empty_claim['secret'],
		'product_id' => $ticket_product_id, 'quantity' => 1,
	));
	$empty_cart->empty_cart();
	$assert((string) $wpdb->get_var($wpdb->prepare("SELECT state FROM {$tables['checkouts']} WHERE id=%d", (int) $empty_attempt['checkout']['id'])) === 'abandoned', 'Emptying the cart must abandon the bound Checkout.');
	$assert((string) $wpdb->get_var($wpdb->prepare("SELECT state FROM {$tables['reservations']} WHERE id=%d", (int) $empty_attempt['reservation']['id'])) === 'released', 'Emptying the cart must release the held Reservation.');

	// A released hold can recover only by winning a fresh capacity acquisition.
	$capacity_offer = $make_offer(array('capacity_total' => 1, 'max_qty_per_claim' => 1));
	$capacity_first = $make_claim($capacity_offer, 1, 'capacity-first');
	[$capacity_session_a, $capacity_cart_a] = $make_woo();
	$capacity_service_a = $service_for($capacity_session_a, $capacity_cart_a);
	$capacity_request = array(
		'claim_public_id' => $capacity_first['result']['claim']['public_id'], 'access_secret' => $capacity_first['secret'],
		'product_id' => $ticket_product_id, 'quantity' => 1,
	);
	$capacity_attempt = $capacity_service_a->activate($capacity_request);
	$capacity_cart_a->remove_cart_item((string) array_key_first($capacity_cart_a->get_cart()));
	$make_claim($capacity_offer, 1, 'capacity-winner');
	[$capacity_session_b, $capacity_cart_b] = $make_woo();
	$capacity_recovery = $capacity_request + array('recover' => true, 'previous_checkout_public_id' => (string) $capacity_attempt['checkout']['public_id']);
	$rejects(static fn() => $service_for($capacity_session_b, $capacity_cart_b)->activate($capacity_recovery), 'checkout_recovery_unavailable');

	// The stored HMAC is authoritative; neutral pointers alone never suffice.
	$hmac_claim = $make_claim($make_offer(), 1, 'hmac-tamper');
	[$hmac_session, $hmac_cart] = $make_woo();
	$hmac_service = $service_for($hmac_session, $hmac_cart);
	$hmac_attempt = $hmac_service->activate(array(
		'claim_public_id' => $hmac_claim['result']['claim']['public_id'], 'access_secret' => $hmac_claim['secret'],
		'product_id' => $ticket_product_id, 'quantity' => 1,
	));
	$wpdb->update($tables['checkouts'], array('checkout_ref' => str_repeat('0', 64)), array('id' => (int) $hmac_attempt['checkout']['id']), array('%s'), array('%d'));
	$rejects(static fn() => $hmac_service->validate_cart($hmac_cart, true), 'checkout_binding_invalid');

	// Two genuinely independent Woo/MySQL sessions race on the canonical Claim lock.
	$race_claim = $make_claim($make_offer(), 1, 'checkout-race');
	$worker = __DIR__ . '/worker.php';
	$start_worker = static function (array $payload) use ($worker, $wordpress_root): array {
		$command = array(PHP_BINARY, $worker, $wordpress_root, base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)));
		$pipes = array();
		$process = proc_open($command, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
		if (!is_resource($process)) throw new RuntimeException('checkout_worker_start_failed');
		fclose($pipes[0]);
		return array($process, $pipes);
	};
	$finish_worker = static function (array $handle): array {
		[$process, $pipes] = $handle;
		$stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($process);
		if ($code !== 0 || $stdout === '') throw new RuntimeException('checkout_worker_failed:' . $code . ':' . $stderr);
		return json_decode($stdout, true, 32, JSON_THROW_ON_ERROR);
	};
	$race_marker = sys_get_temp_dir() . '/bvm-ao-c2-' . bin2hex(random_bytes(6));
	$race_release = $race_marker . '.release';
	$race_request = array(
		'claim_public_id' => $race_claim['result']['claim']['public_id'], 'access_secret' => $race_claim['secret'],
		'product_id' => $ticket_product_id, 'quantity' => 1,
	);
	$race_base = array('mode' => 'woocommerce_checkout', 'now' => gmdate('Y-m-d H:i:s'), 'request' => $race_request);
	$race_a = $start_worker($race_base + array('marker' => $race_marker, 'release' => $race_release));
	$race_deadline = microtime(true) + 10;
	while (!is_file($race_marker)) {
		if (microtime(true) > $race_deadline) throw new RuntimeException('checkout_race_marker_timeout');
		usleep(20000);
	}
	$race_b = $start_worker($race_base);
	usleep(400000);
	$assert(proc_get_status($race_b[0])['running'], 'Second Woo session must genuinely wait for the canonical Claim lock.');
	file_put_contents($race_release, 'release');
	$race_result_a = $finish_worker($race_a);
	$race_result_b = $finish_worker($race_b);
	@unlink($race_marker); @unlink($race_release);
	$assert($race_result_a['connection_id'] !== $race_result_b['connection_id'] && $race_result_a['session_id'] !== $race_result_b['session_id'], 'Checkout contenders must use distinct MySQL and Woo sessions.');
	$assert(!empty($race_result_a['ok']) && empty($race_result_b['ok']) && ($race_result_b['error'] ?? '') === 'checkout_session_conflict', 'Two-browser race must create one binding and reject the other session.');
	$assert((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['checkouts']} WHERE claim_id=%d AND state='checkout_started'", (int) $race_claim['result']['claim']['id'])) === 1, 'Two-browser race must leave exactly one active Checkout attempt.');

	$product_price_after = (string) wc_get_product($ticket_product_id)->get_price();
	$after_negative = $negative_snapshot();
	$assert($product_price_before === $product_price_after, 'C2 must not change the product price.');
	$assert($before_negative === $after_negative, 'C2 must create zero orders, order items, allocations, fulfillments, coupons, attendees, admissions, or legacy Pass Claims.');
	$assert($mail_calls === array() && $http_calls === array(), 'C2 must make no outbound mail or HTTP/payment request.');
	$secret_like = '%' . $wpdb->esc_like($percent_claim['secret']) . '%';
	$secret_occurrences = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['checkouts']} WHERE checkout_ref LIKE %s", $secret_like));
	$secret_occurrences += (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['events']} WHERE payload_redacted LIKE %s", $secret_like));
	$assert($secret_occurrences === 0, 'Raw Claim secret must not appear in Checkout/event persistence.');
	$runtime_source = (string) file_get_contents(dirname(__DIR__, 2) . '/includes/modules/admission-offers/woo-checkout-service.php')
		. (string) file_get_contents(dirname(__DIR__, 2) . '/includes/modules/admission-offers/woo-cart-adapter.php');
	foreach (array('woocommerce_before_calculate_totals', 'woocommerce_cart_calculate_fees', 'wc_create_order(', 'woocommerce_checkout_create_order', 'woocommerce_payment_', 'square_request', 'tribe_tickets_attendee', 'bvmgr_admission_create') as $forbidden) {
		$assert(stripos($runtime_source, $forbidden) === false, 'C2 runtime contains forbidden order/price/payment/credential surface: ' . $forbidden);
	}

	remove_filter('pre_wp_mail', $mail_filter, 10);
	remove_filter('pre_http_request', $http_filter, 10);

	return array(
		'focused_assertions' => $focused_assertions,
		'woocommerce_version' => WC_VERSION,
		'percent_claim_id' => (int) $claim['id'],
		'fixed_claim_id' => (int) $fixed_claim['result']['claim']['id'],
		'checkout_rows' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['checkouts']}"),
		'active_checkout_rows' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['checkouts']} WHERE state='checkout_started'"),
		'negative' => $after_negative,
		'mail_calls' => count($mail_calls),
		'http_calls' => count($http_calls),
		'cross_browser_race' => array('a' => $race_result_a, 'b' => $race_result_b),
	);
}
