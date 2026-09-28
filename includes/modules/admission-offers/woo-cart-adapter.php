<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_admission_offer_woo_error_message')) {
	function bvmgr_admission_offer_woo_error_message(): string
	{
		return __('This Admission Offer cart binding is unavailable or no longer valid.', 'backstage-venue-manager');
	}
}

if (!function_exists('bvmgr_admission_offer_woo_c2_barrier_message')) {
	function bvmgr_admission_offer_woo_c2_barrier_message(): string
	{
		return __('Offer checkout is not yet enabled.', 'backstage-venue-manager');
	}
}

if (!function_exists('bvmgr_admission_offer_woo_store_route_exception')) {
	function bvmgr_admission_offer_woo_store_route_exception(): Throwable
	{
		$class = '\\Automattic\\WooCommerce\\StoreApi\\Exceptions\\RouteException';
		if (class_exists($class)) {
			return new $class('bvmgr_admission_offer_invalid', bvmgr_admission_offer_woo_error_message(), 409);
		}
		return new RuntimeException(bvmgr_admission_offer_woo_error_message());
	}
}

if (!function_exists('bvmgr_admission_offer_woo_internal_mutation')) {
	function bvmgr_admission_offer_woo_internal_mutation(): bool
	{
		return !empty($GLOBALS['bvmgr_admission_offer_woo_internal_mutation']);
	}
}

if (!function_exists('bvmgr_admission_offer_woo_store_api_add_to_cart_data')) {
	/** @param array<string,mixed> $data @return array<string,mixed> */
	function bvmgr_admission_offer_woo_store_api_add_to_cart_data(array $data, $request): array
	{
		$extensions = is_object($request) && is_callable(array($request, 'get_param')) ? $request->get_param('extensions') : array();
		$extension = is_array($extensions) ? ($extensions['backstage-admission-offers'] ?? array()) : array();
		$checkout_public_id = is_array($extension) ? trim((string) ($extension['checkout_id'] ?? '')) : '';
		if ($checkout_public_id === '') {
			return $data;
		}
		$metadata = bvmgr_admission_offer_woocommerce_checkout_service()->metadata_from_session($checkout_public_id);
		if ($metadata !== array()) {
			$data['cart_item_data'] = is_array($data['cart_item_data'] ?? null) ? $data['cart_item_data'] : array();
			$data['cart_item_data'][BVMGR_Admission_Offer_Woo_Checkout_Service::CART_ITEM_KEY] = $metadata;
		}
		return $data;
	}
}

if (!function_exists('bvmgr_admission_offer_woo_add_cart_item_data')) {
	/** @param array<string,mixed> $cart_item_data @return array<string,mixed> */
	function bvmgr_admission_offer_woo_add_cart_item_data(array $cart_item_data, int $product_id, int $variation_id, int $quantity): array
	{
		unset($product_id, $variation_id, $quantity);
		if (BVMGR_Admission_Offer_Woo_Checkout_Service::cart_item_metadata($cart_item_data) !== array()) {
			return $cart_item_data;
		}
		$checkout_public_id = isset($_REQUEST['bvmgr_admission_offer_checkout'])
			? sanitize_text_field((string) wp_unslash($_REQUEST['bvmgr_admission_offer_checkout']))
			: '';
		$nonce = isset($_REQUEST['bvmgr_admission_offer_nonce'])
			? sanitize_text_field((string) wp_unslash($_REQUEST['bvmgr_admission_offer_nonce']))
			: '';
		if ($checkout_public_id === '' || !wp_verify_nonce($nonce, 'bvmgr_admission_offer_cart_' . $checkout_public_id)) {
			return $cart_item_data;
		}
		$metadata = bvmgr_admission_offer_woocommerce_checkout_service()->metadata_from_session($checkout_public_id);
		if ($metadata !== array()) {
			$cart_item_data[BVMGR_Admission_Offer_Woo_Checkout_Service::CART_ITEM_KEY] = $metadata;
		}
		return $cart_item_data;
	}
}

if (!function_exists('bvmgr_admission_offer_woo_add_to_cart_validation')) {
	/** @param array<string,mixed> $variation @param array<string,mixed> $cart_item_data */
	function bvmgr_admission_offer_woo_add_to_cart_validation($passed, int $product_id, int $quantity, int $variation_id = 0, array $variation = array(), array $cart_item_data = array()): bool
	{
		unset($variation);
		if (!$passed || BVMGR_Admission_Offer_Woo_Checkout_Service::cart_item_metadata($cart_item_data) === array()) {
			return (bool) $passed;
		}
		try {
			bvmgr_admission_offer_woocommerce_checkout_service()->validate_addition($variation_id ?: $product_id, $quantity, $cart_item_data);
			return true;
		} catch (Throwable $error) {
			if (function_exists('wc_add_notice')) {
				wc_add_notice(bvmgr_admission_offer_woo_error_message(), 'error');
			}
			return false;
		}
	}
}

if (!function_exists('bvmgr_admission_offer_woo_store_validate_add')) {
	function bvmgr_admission_offer_woo_store_validate_add($product, array $request): void
	{
		$cart_item_data = is_array($request['cart_item_data'] ?? null) ? $request['cart_item_data'] : array();
		if (BVMGR_Admission_Offer_Woo_Checkout_Service::cart_item_metadata($cart_item_data) === array()) {
			return;
		}
		try {
			$product_id = is_object($product) && is_callable(array($product, 'get_id')) ? (int) $product->get_id() : 0;
			bvmgr_admission_offer_woocommerce_checkout_service()->validate_addition($product_id, (int) ($request['quantity'] ?? 0), $cart_item_data);
		} catch (Throwable $error) {
			throw bvmgr_admission_offer_woo_store_route_exception();
		}
	}
}

if (!function_exists('bvmgr_admission_offer_woo_item_added')) {
	/** @param array<string,mixed> $variation @param array<string,mixed> $cart_item_data */
	function bvmgr_admission_offer_woo_item_added(string $cart_item_key, int $product_id, int $quantity, int $variation_id, array $variation, array $cart_item_data): void
	{
		unset($cart_item_key, $product_id, $quantity, $variation_id, $variation);
		if (BVMGR_Admission_Offer_Woo_Checkout_Service::cart_item_metadata($cart_item_data) === array()) {
			return;
		}
		bvmgr_admission_offer_woocommerce_checkout_service()->renew_for_cart('eligible_item_added');
	}
}

if (!function_exists('bvmgr_admission_offer_woo_restore_cart_item_data')) {
	/** @param array<string,mixed> $session_data @param array<string,mixed> $values @return array<string,mixed> */
	function bvmgr_admission_offer_woo_restore_cart_item_data(array $session_data, array $values, string $cart_item_key): array
	{
		unset($cart_item_key);
		$metadata = BVMGR_Admission_Offer_Woo_Checkout_Service::cart_item_metadata($values);
		if ($metadata === array()) {
			return $session_data;
		}
		try {
			bvmgr_admission_offer_woocommerce_checkout_service()->validate_cart_item($session_data);
		} catch (Throwable $error) {
			unset($session_data[BVMGR_Admission_Offer_Woo_Checkout_Service::CART_ITEM_KEY]);
		}
		return $session_data;
	}
}

if (!function_exists('bvmgr_admission_offer_woo_cart_loaded')) {
	function bvmgr_admission_offer_woo_cart_loaded($cart): void
	{
		try {
			bvmgr_admission_offer_woocommerce_checkout_service()->validate_cart($cart, false);
		} catch (Throwable $error) {
			$contents = is_object($cart) && is_callable(array($cart, 'get_cart')) ? (array) $cart->get_cart() : array();
			foreach ($contents as $key => $item) {
				if (is_array($item) && BVMGR_Admission_Offer_Woo_Checkout_Service::cart_item_metadata($item) !== array()) {
					unset($contents[$key][BVMGR_Admission_Offer_Woo_Checkout_Service::CART_ITEM_KEY]);
				}
			}
			if (is_object($cart) && is_callable(array($cart, 'set_cart_contents'))) {
				$cart->set_cart_contents($contents);
			}
			bvmgr_admission_offer_woocommerce_checkout_service()->abandon_if_no_bound_items($cart, 'invalid_session_restore');
		}
	}
}

if (!function_exists('bvmgr_admission_offer_woo_check_cart_items')) {
	function bvmgr_admission_offer_woo_check_cart_items(): void
	{
		try {
			bvmgr_admission_offer_woocommerce_checkout_service()->validate_cart(null, false);
		} catch (Throwable $error) {
			if (function_exists('wc_add_notice')) {
				wc_add_notice(bvmgr_admission_offer_woo_error_message(), 'error');
			}
		}
	}
}

if (!function_exists('bvmgr_admission_offer_woo_store_cart_errors')) {
	function bvmgr_admission_offer_woo_store_cart_errors(WP_Error $errors, $cart): void
	{
		try {
			$result = bvmgr_admission_offer_woocommerce_checkout_service()->validate_cart($cart, false);
			if (!empty($GLOBALS['bvmgr_admission_offer_store_checkout_post']) && !empty($result['bound'])) {
				bvmgr_admission_offer_woocommerce_checkout_service()->renew_checkout_entry_once($cart);
				$errors->add('bvmgr_admission_offer_c2_barrier', bvmgr_admission_offer_woo_c2_barrier_message());
			}
		} catch (Throwable $error) {
			$errors->add('bvmgr_admission_offer_invalid', bvmgr_admission_offer_woo_error_message());
		}
	}
}

if (!function_exists('bvmgr_admission_offer_woo_update_cart_validation')) {
	/** @param array<string,mixed> $values */
	function bvmgr_admission_offer_woo_update_cart_validation($passed, string $cart_item_key, array $values, int $quantity): bool
	{
		if (!$passed || BVMGR_Admission_Offer_Woo_Checkout_Service::cart_item_metadata($values) === array()) {
			return (bool) $passed;
		}
		try {
			bvmgr_admission_offer_woocommerce_checkout_service()->validate_quantity_change($cart_item_key, $values, $quantity);
			return true;
		} catch (Throwable $error) {
			if (function_exists('wc_add_notice')) {
				wc_add_notice(bvmgr_admission_offer_woo_error_message(), 'error');
			}
			return false;
		}
	}
}

if (!function_exists('bvmgr_admission_offer_woo_quantity_updated')) {
	function bvmgr_admission_offer_woo_quantity_updated(string $cart_item_key, int $quantity, int $old_quantity, $cart): void
	{
		if (bvmgr_admission_offer_woo_internal_mutation() || $quantity === $old_quantity) {
			return;
		}
		$item = is_object($cart) && is_callable(array($cart, 'get_cart_item')) ? $cart->get_cart_item($cart_item_key) : array();
		if (!is_array($item) || BVMGR_Admission_Offer_Woo_Checkout_Service::cart_item_metadata($item) === array()) {
			return;
		}
		try {
			bvmgr_admission_offer_woocommerce_checkout_service()->validate_quantity_change($cart_item_key, $item, $quantity, $cart);
			bvmgr_admission_offer_woocommerce_checkout_service()->renew_for_cart('eligible_quantity_changed', $cart);
		} catch (Throwable $error) {
			$GLOBALS['bvmgr_admission_offer_woo_internal_mutation'] = true;
			try {
				$cart->set_quantity($cart_item_key, $old_quantity, false);
			} finally {
				$GLOBALS['bvmgr_admission_offer_woo_internal_mutation'] = false;
			}
			if (defined('REST_REQUEST') && REST_REQUEST) {
				throw bvmgr_admission_offer_woo_store_route_exception();
			}
			if (function_exists('wc_add_notice')) {
				wc_add_notice(bvmgr_admission_offer_woo_error_message(), 'error');
			}
		}
	}
}

if (!function_exists('bvmgr_admission_offer_woo_item_removed')) {
	function bvmgr_admission_offer_woo_item_removed(string $cart_item_key, $cart): void
	{
		unset($cart_item_key);
		if (!bvmgr_admission_offer_woo_internal_mutation()) {
			bvmgr_admission_offer_woocommerce_checkout_service()->abandon_if_no_bound_items($cart, 'last_bound_item_removed');
		}
	}
}

if (!function_exists('bvmgr_admission_offer_woo_cart_emptied')) {
	function bvmgr_admission_offer_woo_cart_emptied(): void
	{
		if (!bvmgr_admission_offer_woo_internal_mutation()) {
			bvmgr_admission_offer_woocommerce_checkout_service()->abandon_if_no_bound_items(null, 'cart_emptied');
		}
	}
}

if (!function_exists('bvmgr_admission_offer_woo_item_restored')) {
	function bvmgr_admission_offer_woo_item_restored(string $cart_item_key, $cart): void
	{
		if (bvmgr_admission_offer_woo_internal_mutation()) {
			return;
		}
		$item = is_object($cart) && is_callable(array($cart, 'get_cart_item')) ? $cart->get_cart_item($cart_item_key) : array();
		$metadata = is_array($item) ? BVMGR_Admission_Offer_Woo_Checkout_Service::cart_item_metadata($item) : array();
		if ($metadata === array()) {
			return;
		}
		try {
			$product_id = absint(($item['variation_id'] ?? 0) ?: ($item['product_id'] ?? 0));
			$attempt = bvmgr_admission_offer_woocommerce_checkout_service()->recover_restored_item($metadata, $product_id, (int) ($item['quantity'] ?? 0));
			$item[BVMGR_Admission_Offer_Woo_Checkout_Service::CART_ITEM_KEY] = array(
				'version' => BVMGR_Admission_Offer_Woo_Checkout_Service::BINDING_VERSION,
				'claim_public_id' => (string) $attempt['claim']['public_id'],
				'checkout_public_id' => (string) $attempt['checkout']['public_id'],
				'event_plan_id' => (int) $attempt['claim']['event_plan_id'],
			);
			$cart->cart_contents[$cart_item_key] = $item;
		} catch (Throwable $error) {
			unset($item[BVMGR_Admission_Offer_Woo_Checkout_Service::CART_ITEM_KEY]);
			$cart->cart_contents[$cart_item_key] = $item;
			if (function_exists('wc_add_notice')) {
				wc_add_notice(bvmgr_admission_offer_woo_error_message(), 'error');
			}
		}
	}
}

if (!function_exists('bvmgr_admission_offer_woo_classic_checkout_barrier')) {
	function bvmgr_admission_offer_woo_classic_checkout_barrier(array $data, WP_Error $errors): void
	{
		unset($data);
		try {
			$result = bvmgr_admission_offer_woocommerce_checkout_service()->validate_cart(null, false);
			if (empty($result['bound'])) {
				return;
			}
			bvmgr_admission_offer_woocommerce_checkout_service()->renew_checkout_entry_once();
			$errors->add('bvmgr_admission_offer_c2_barrier', bvmgr_admission_offer_woo_c2_barrier_message());
		} catch (Throwable $error) {
			$errors->add('bvmgr_admission_offer_invalid', bvmgr_admission_offer_woo_error_message());
		}
	}
}

if (!function_exists('bvmgr_admission_offer_woo_mark_store_checkout_request')) {
	function bvmgr_admission_offer_woo_mark_store_checkout_request($response, array $handler, WP_REST_Request $request)
	{
		unset($handler);
		$route = $request->get_route();
		$GLOBALS['bvmgr_admission_offer_store_checkout_post'] = $request->get_method() === WP_REST_Server::CREATABLE
			&& (bool) preg_match('#^/wc/store/v[0-9]+/checkout/?$#', $route);
		return $response;
	}
}

if (!function_exists('bvmgr_admission_offer_woo_clear_store_checkout_request')) {
	function bvmgr_admission_offer_woo_clear_store_checkout_request($response, WP_REST_Server $server, WP_REST_Request $request)
	{
		unset($server, $request);
		$GLOBALS['bvmgr_admission_offer_store_checkout_post'] = false;
		return $response;
	}
}

if (!function_exists('bvmgr_admission_offer_woo_boot')) {
	function bvmgr_admission_offer_woo_boot(): void
	{
		add_filter('woocommerce_store_api_add_to_cart_data', 'bvmgr_admission_offer_woo_store_api_add_to_cart_data', 10, 2);
		add_filter('woocommerce_add_cart_item_data', 'bvmgr_admission_offer_woo_add_cart_item_data', 10, 4);
		add_filter('woocommerce_add_to_cart_validation', 'bvmgr_admission_offer_woo_add_to_cart_validation', 20, 6);
		add_action('woocommerce_store_api_validate_add_to_cart', 'bvmgr_admission_offer_woo_store_validate_add', 20, 2);
		add_action('woocommerce_add_to_cart', 'bvmgr_admission_offer_woo_item_added', 20, 6);
		add_filter('woocommerce_get_cart_item_from_session', 'bvmgr_admission_offer_woo_restore_cart_item_data', 20, 3);
		add_action('woocommerce_cart_loaded_from_session', 'bvmgr_admission_offer_woo_cart_loaded', 20, 1);
		add_action('woocommerce_check_cart_items', 'bvmgr_admission_offer_woo_check_cart_items', 20, 0);
		add_action('woocommerce_store_api_cart_errors', 'bvmgr_admission_offer_woo_store_cart_errors', 20, 2);
		add_filter('woocommerce_update_cart_validation', 'bvmgr_admission_offer_woo_update_cart_validation', 20, 4);
		add_action('woocommerce_after_cart_item_quantity_update', 'bvmgr_admission_offer_woo_quantity_updated', 20, 4);
		add_action('woocommerce_cart_item_removed', 'bvmgr_admission_offer_woo_item_removed', 5, 2);
		add_action('woocommerce_cart_emptied', 'bvmgr_admission_offer_woo_cart_emptied', 5, 0);
		add_action('woocommerce_cart_item_restored', 'bvmgr_admission_offer_woo_item_restored', 5, 2);
		add_action('woocommerce_after_checkout_validation', 'bvmgr_admission_offer_woo_classic_checkout_barrier', 1000, 2);
		add_filter('rest_request_before_callbacks', 'bvmgr_admission_offer_woo_mark_store_checkout_request', 10, 3);
		add_filter('rest_post_dispatch', 'bvmgr_admission_offer_woo_clear_store_checkout_request', 1000, 3);
	}
}

bvmgr_admission_offer_woo_boot();
