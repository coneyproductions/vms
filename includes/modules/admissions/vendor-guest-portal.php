<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_admission_vendor_guest_meta_key')) {
	function vms_admission_vendor_guest_meta_key(): string
	{
		$key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'vendor_guest_rules') : '';
		return $key !== '' ? $key : '_vms_vendor_guest_rules';
	}
}

if (!function_exists('vms_admission_vendor_guest_flash_key')) {
	function vms_admission_vendor_guest_flash_key(int $user_id): string
	{
		return 'vms_vendor_guest_flash_' . max(0, $user_id);
	}
}

if (!function_exists('vms_admission_vendor_guest_set_flash')) {
	function vms_admission_vendor_guest_set_flash(int $user_id, array $payload): void
	{
		if ($user_id <= 0) {
			return;
		}
		set_transient(vms_admission_vendor_guest_flash_key($user_id), $payload, 120);
	}
}

if (!function_exists('vms_admission_vendor_guest_pull_flash')) {
	function vms_admission_vendor_guest_pull_flash(int $user_id): array
	{
		if ($user_id <= 0) {
			return array();
		}
		$key = vms_admission_vendor_guest_flash_key($user_id);
		$data = get_transient($key);
		delete_transient($key);
		return is_array($data) ? $data : array();
	}
}

if (!function_exists('vms_admission_get_event_vendor_ids')) {
	function vms_admission_get_event_vendor_ids(int $event_plan_id): array
	{
		$ids = array();
		$band_key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'band_vendor_id') : '_vms_band_vendor_id';
		$band_key = $band_key !== '' ? $band_key : '_vms_band_vendor_id';
		$secondary_ids_key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'secondary_vendor_ids') : '_vms_secondary_vendor_ids';
		$secondary_ids_key = $secondary_ids_key !== '' ? $secondary_ids_key : '_vms_secondary_vendor_ids';
		$secondary_idx_key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'secondary_vendor_id') : '_vms_secondary_vendor_id';
		$secondary_idx_key = $secondary_idx_key !== '' ? $secondary_idx_key : '_vms_secondary_vendor_id';

		$band_id = (int) get_post_meta($event_plan_id, $band_key, true);
		if ($band_id > 0) {
			$ids[] = $band_id;
		}

		$secondary_ids = get_post_meta($event_plan_id, $secondary_ids_key, true);
		if (!is_array($secondary_ids)) {
			$secondary_ids = get_post_meta($event_plan_id, $secondary_idx_key, false);
		}
		foreach ((array) $secondary_ids as $vendor_id) {
			$vendor_id = absint($vendor_id);
			if ($vendor_id > 0) {
				$ids[] = $vendor_id;
			}
		}

		$ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
		sort($ids, SORT_NUMERIC);
		return $ids;
	}
}

if (!function_exists('vms_admission_get_event_vendor_posts')) {
	function vms_admission_get_event_vendor_posts(int $event_plan_id): array
	{
		$rows = array();
		foreach (vms_admission_get_event_vendor_ids($event_plan_id) as $vendor_id) {
			$post = get_post($vendor_id);
			if (!($post instanceof WP_Post) || $post->post_type !== 'vms_vendor') {
				continue;
			}
			$rows[] = array(
				'id' => $vendor_id,
				'title' => (string) get_the_title($vendor_id),
				'post' => $post,
			);
		}
		return $rows;
	}
}

if (!function_exists('vms_admission_vendor_guest_default_rules')) {
	function vms_admission_vendor_guest_default_rules(): array
	{
		return array(
			'enabled' => 0,
			'first_time_only' => 0,
			'vendors' => array(),
		);
	}
}

if (!function_exists('vms_admission_vendor_guest_get_rules')) {
	function vms_admission_vendor_guest_get_rules(int $event_plan_id): array
	{
		$raw = get_post_meta($event_plan_id, vms_admission_vendor_guest_meta_key(), true);
		$rules = vms_admission_vendor_guest_default_rules();
		if (!is_array($raw)) {
			$raw = array();
		}
		$legacy_first_time_only = array_key_exists('first_time_only', $raw) ? (!empty($raw['first_time_only']) ? 1 : 0) : (int) ($rules['first_time_only'] ?? 0);
		$rules['enabled'] = !empty($raw['enabled']) ? 1 : 0;
		$rules['first_time_only'] = $legacy_first_time_only;
		$rules['vendors'] = array();
		foreach ((array) ($raw['vendors'] ?? array()) as $vendor_id => $vendor_rule) {
			$vendor_id = absint($vendor_id);
			if ($vendor_id <= 0) {
				continue;
			}
			$vendor_rule = is_array($vendor_rule) ? $vendor_rule : array();
			$rules['vendors'][$vendor_id] = array(
				'enabled' => !empty($vendor_rule['enabled']) ? 1 : 0,
				'allotment' => max(0, absint($vendor_rule['allotment'] ?? 0)),
				'first_time_only' => array_key_exists('first_time_only', $vendor_rule) ? (!empty($vendor_rule['first_time_only']) ? 1 : 0) : $legacy_first_time_only,
			);
		}
		return $rules;
	}
}

if (!function_exists('vms_admission_vendor_guest_get_vendor_rule')) {
	function vms_admission_vendor_guest_get_vendor_rule(int $event_plan_id, int $vendor_id): array
	{
		$rules = vms_admission_vendor_guest_get_rules($event_plan_id);
		$vendor_rule = (array) ($rules['vendors'][$vendor_id] ?? array());
		$legacy_first_time_only = !empty($rules['first_time_only']) ? 1 : 0;
		return array(
			'program_enabled' => !empty($rules['enabled']) ? 1 : 0,
			'legacy_first_time_only' => $legacy_first_time_only,
			'first_time_only' => array_key_exists('first_time_only', $vendor_rule) ? (!empty($vendor_rule['first_time_only']) ? 1 : 0) : $legacy_first_time_only,
			'enabled' => !empty($vendor_rule['enabled']) ? 1 : 0,
			'allotment' => max(0, absint($vendor_rule['allotment'] ?? 0)),
		);
	}
}

if (!function_exists('vms_admission_vendor_guest_is_enabled_for_vendor')) {
	function vms_admission_vendor_guest_is_enabled_for_vendor(int $event_plan_id, int $vendor_id): bool
	{
		$rule = vms_admission_vendor_guest_get_vendor_rule($event_plan_id, $vendor_id);
		return !empty($rule['program_enabled']) && !empty($rule['enabled']) && (int) ($rule['allotment'] ?? 0) > 0;
	}
}


if (!function_exists('vms_admission_vendor_guest_internal_comp_product_option_key')) {
	function vms_admission_vendor_guest_internal_comp_product_option_key(): string
	{
		return 'vms_admission_internal_comp_product_id';
	}
}

if (!function_exists('vms_admission_vendor_guest_internal_comp_order_meta_key')) {
	function vms_admission_vendor_guest_internal_comp_order_meta_key(): string
	{
		return '_vms_internal_comp_admission';
	}
}

if (!function_exists('vms_admission_vendor_guest_bridge_source_key')) {
	function vms_admission_vendor_guest_bridge_source_key(): string
	{
		return 'tec_woo_attendee';
	}
}

if (!function_exists('vms_admission_vendor_guest_split_name')) {
	function vms_admission_vendor_guest_split_name(string $full_name): array
	{
		$full_name = trim(preg_replace('/\s+/', ' ', $full_name));
		if ($full_name === '') {
			return array('first' => '', 'last' => '');
		}
		$parts = preg_split('/\s+/', $full_name);
		$first = (string) array_shift($parts);
		$last = trim(implode(' ', $parts));
		return array('first' => $first, 'last' => $last);
	}
}

if (!function_exists('vms_admission_vendor_guest_has_full_name')) {
	function vms_admission_vendor_guest_has_full_name(string $full_name): bool
	{
		$full_name = trim(preg_replace('/\s+/', ' ', $full_name));
		if ($full_name === '') {
			return false;
		}
		$parts = preg_split('/\s+/', $full_name);
		$parts = array_values(array_filter(array_map('trim', (array) $parts), static function ($part) {
			return $part !== '';
		}));
		return count($parts) >= 2;
	}
}

if (!function_exists('vms_admission_vendor_guest_parse_party_guests')) {
	function vms_admission_vendor_guest_parse_party_guests(array $post, int $party_size): array
	{
		$party_size = max(1, $party_size);
		$names = isset($post['guest_names']) && is_array($post['guest_names']) ? $post['guest_names'] : array();
		$emails = isset($post['guest_emails']) && is_array($post['guest_emails']) ? $post['guest_emails'] : array();
		$phones = isset($post['guest_phones']) && is_array($post['guest_phones']) ? $post['guest_phones'] : array();
		$guests = array();
		for ($slot = 1; $slot <= $party_size; $slot++) {
			$guest_name = sanitize_text_field((string) wp_unslash($names[$slot] ?? ''));
			$guest_email_raw = (string) wp_unslash($emails[$slot] ?? '');
			$guest_email = sanitize_email($guest_email_raw);
			$guest_phone = sanitize_text_field((string) wp_unslash($phones[$slot] ?? ''));
			$guests[] = array(
				'slot' => $slot,
				'name' => $guest_name,
				'email' => $guest_email,
				'email_raw' => $guest_email_raw,
				'phone' => $guest_phone,
			);
		}
		return $guests;
	}
}

if (!function_exists('vms_admission_vendor_guest_is_internal_comp_order')) {
	function vms_admission_vendor_guest_is_internal_comp_order($order_ref): bool
	{
		if (!function_exists('wc_get_order')) {
			return false;
		}
		$order = is_numeric($order_ref) ? wc_get_order((int) $order_ref) : $order_ref;
		if (!is_object($order) || !method_exists($order, 'get_meta')) {
			return false;
		}
		return (bool) $order->get_meta(vms_admission_vendor_guest_internal_comp_order_meta_key(), true);
	}
}

if (!function_exists('vms_admission_vendor_guest_bridge_context_from_claim_meta')) {
	function vms_admission_vendor_guest_bridge_context_from_claim_meta($claim_meta): array
	{
		if (is_string($claim_meta) && $claim_meta !== '') {
			$decoded = json_decode($claim_meta, true);
			if (is_array($decoded)) {
				$claim_meta = $decoded;
			}
		}
		$claim_meta = is_array($claim_meta) ? $claim_meta : array();
		$bridge = is_array($claim_meta['bridge'] ?? null) ? $claim_meta['bridge'] : array();
		return array(
			'source' => (string) ($bridge['source'] ?? ''),
			'product_id' => absint($bridge['product_id'] ?? 0),
			'order_id' => absint($bridge['order_id'] ?? 0),
			'order_item_id' => absint($bridge['order_item_id'] ?? 0),
			'attendee_ids' => array_values(array_filter(array_map('absint', (array) ($bridge['attendee_ids'] ?? array())))),
			'tec_event_id' => absint($bridge['tec_event_id'] ?? 0),
			'email_dispatched' => !empty($bridge['email_dispatched']) ? 1 : 0,
		);
	}
}

if (!function_exists('vms_admission_vendor_guest_internal_comp_product_id')) {
	function vms_admission_vendor_guest_internal_comp_product_id(): int
	{
		if (!function_exists('wc_get_product') || !class_exists('WC_Product_Simple')) {
			return 0;
		}
		$stored_id = absint(get_option(vms_admission_vendor_guest_internal_comp_product_option_key(), 0));
		if ($stored_id > 0) {
			$product = wc_get_product($stored_id);
			if ($product) {
				return $stored_id;
			}
		}
		$product_post = array(
			'post_type' => 'product',
			'post_status' => 'private',
			'post_title' => __('Complimentary Admission', 'backstage-venue-manager'),
			'post_excerpt' => __('Internal scanner-native comp admission product used by VMS for non-public guest passes.', 'backstage-venue-manager'),
			'post_content' => '',
			'post_author' => get_current_user_id() ?: 1,
		);
		$product_id = wp_insert_post($product_post, true);
		if (is_wp_error($product_id) || $product_id <= 0) {
			return 0;
		}
		$product = new WC_Product_Simple($product_id);
		$product->set_name(__('Complimentary Admission', 'backstage-venue-manager'));
		$product->set_regular_price('0');
		$product->set_price('0');
		$product->set_virtual(true);
		$product->set_catalog_visibility('hidden');
		$product->set_manage_stock(false);
		$product->set_stock_status('instock');
		$product->set_sold_individually(false);
		$product->set_reviews_allowed(false);
		$product->save();
		update_post_meta($product_id, vms_admission_vendor_guest_internal_comp_order_meta_key(), 1);
		update_post_meta($product_id, '_vms_internal_comp_product', 1);
		update_option(vms_admission_vendor_guest_internal_comp_product_option_key(), $product_id, false);
		return (int) $product_id;
	}
}

if (!function_exists('vms_admission_vendor_guest_bridge_cancel')) {
	function vms_admission_vendor_guest_bridge_cancel(array $bridge, string $reason = ''): void
	{
		$bridge = vms_admission_vendor_guest_bridge_context_from_claim_meta(array('bridge' => $bridge));
		foreach ((array) ($bridge['attendee_ids'] ?? array()) as $attendee_id) {
			$attendee_id = absint($attendee_id);
			if ($attendee_id > 0 && get_post($attendee_id)) {
				wp_trash_post($attendee_id);
			}
		}
		$order_id = absint($bridge['order_id'] ?? 0);
		if ($order_id > 0 && function_exists('wc_get_order')) {
			$order = wc_get_order($order_id);
			if ($order && method_exists($order, 'set_status')) {
				$order->set_status('cancelled');
				if ($reason !== '' && method_exists($order, 'add_order_note')) {
					$order->add_order_note($reason);
				}
				$order->save();
			}
		}
	}
}

if (!function_exists('vms_admission_vendor_guest_bridge_create')) {
	function vms_admission_vendor_guest_bridge_create(int $event_plan_id, int $vendor_id, string $guest_name, string $guest_email, string $phone, int $party_size, string $notes, int $user_id)
	{
		if (!function_exists('wc_create_order') || !function_exists('wc_get_product')) {
			return new WP_Error('vms_vendor_guest_bridge_wc_missing', __('WooCommerce is required to issue scanner-native complimentary admissions.', 'backstage-venue-manager'));
		}
		if (!class_exists('Tribe__Tickets_Plus__Commerce__WooCommerce__Main')) {
			return new WP_Error('vms_vendor_guest_bridge_tec_missing', __('Event Tickets Plus (WooCommerce tickets) is required to issue scanner-native complimentary admissions.', 'backstage-venue-manager'));
		}
		$tec_event_id = (int) get_post_meta($event_plan_id, '_vms_tec_event_id', true);
		if ($tec_event_id <= 0) {
			return new WP_Error('vms_vendor_guest_bridge_no_event', __('This event plan is not connected to a TEC event yet, so a scannable complimentary admission cannot be issued.', 'backstage-venue-manager'));
		}
		$product_id = vms_admission_vendor_guest_internal_comp_product_id();
		if ($product_id <= 0) {
			return new WP_Error('vms_vendor_guest_bridge_no_product', __('Could not prepare the internal complimentary admission product.', 'backstage-venue-manager'));
		}
		$product = wc_get_product($product_id);
		if (!$product) {
			return new WP_Error('vms_vendor_guest_bridge_bad_product', __('The internal complimentary admission product is unavailable.', 'backstage-venue-manager'));
		}
		$provider = Tribe__Tickets_Plus__Commerce__WooCommerce__Main::get_instance();
		if (!is_object($provider)) {
			return new WP_Error('vms_vendor_guest_bridge_no_provider', __('The WooCommerce ticket provider could not be loaded.', 'backstage-venue-manager'));
		}
		$name = vms_admission_vendor_guest_split_name($guest_name);
		$order = wc_create_order(array(
			'created_via' => 'vms_vendor_guest',
			'customer_id' => 0,
		));
		if (is_wp_error($order) || !is_object($order)) {
			return new WP_Error('vms_vendor_guest_bridge_order_failed', __('Could not create the complimentary admission order.', 'backstage-venue-manager'));
		}
		$order->set_billing_first_name($name['first']);
		$order->set_billing_last_name($name['last']);
		if ($guest_email !== '') {
			$order->set_billing_email($guest_email);
		}
		if ($phone !== '') {
			$order->set_billing_phone($phone);
		}
		$order->set_customer_note($notes);
		$order->update_meta_data(vms_admission_vendor_guest_internal_comp_order_meta_key(), 1);
		$order->update_meta_data('_vms_vendor_guest_source', 'vendor_portal');
		$order->update_meta_data('_vms_vendor_id', $vendor_id);
		$order->update_meta_data('_vms_event_plan_id', $event_plan_id);
		$order->update_meta_data('_vms_tec_event_id', $tec_event_id);
		$order->update_meta_data('_tribe_has_tickets', '1');
		if (method_exists($order, 'set_created_via')) {
			$order->set_created_via('vms_vendor_guest');
		}
		$item = new WC_Order_Item_Product();
		$item->set_product($product);
		$item->set_name(sprintf(__('Complimentary Admission — %s', 'backstage-venue-manager'), $guest_name));
		$item->set_quantity(max(1, $party_size));
		$item->set_subtotal(0);
		$item->set_total(0);
		$item->add_meta_data(vms_admission_vendor_guest_internal_comp_order_meta_key(), 1, true);
		$item->add_meta_data('_vms_vendor_id', $vendor_id, true);
		$item->add_meta_data('_vms_event_plan_id', $event_plan_id, true);
		$item->add_meta_data('_vms_tec_event_id', $tec_event_id, true);
		$order->add_item($item);
		$order->calculate_totals(false);
		$order->set_status('completed');
		$order->save();
		$order_id = (int) $order->get_id();
		$order_item_id = (int) $item->get_id();
		if ($order_id <= 0 || $order_item_id <= 0) {
			return new WP_Error('vms_vendor_guest_bridge_order_item_failed', __('Could not finalize the complimentary admission order item.', 'backstage-venue-manager'));
		}
		$currency_symbol = function_exists('get_woocommerce_currency_symbol') ? (string) get_woocommerce_currency_symbol() : '$';
		$attendee_ids = array();
		for ($i = 1; $i <= max(1, $party_size); $i++) {
			$attendee_post = array(
				'post_status' => 'publish',
				'post_title' => $order_id . ' | ' . get_the_title($product_id) . ' | ' . $guest_name . ' | ' . $i,
				'post_type' => 'tribe_wooticket',
				'ping_status' => 'closed',
			);
			$attendee_post = apply_filters('wootickets_attendee_insert_args', $attendee_post, $order_id, $product_id, $tec_event_id);
			$attendee_id = wp_insert_post($attendee_post, true);
			if (is_wp_error($attendee_id) || $attendee_id <= 0) {
				vms_admission_vendor_guest_bridge_cancel(array(
					'order_id' => $order_id,
					'attendee_ids' => $attendee_ids,
				), __('Bridge cleanup after attendee creation failure.', 'backstage-venue-manager'));
				return new WP_Error('vms_vendor_guest_bridge_attendee_failed', __('Could not create the scanner attendee record.', 'backstage-venue-manager'));
			}
			$security_code = method_exists($provider, 'generate_security_code')
				? (string) $provider->generate_security_code($order_id, $attendee_id)
				: substr(md5($order_id . '_' . $attendee_id), 0, 10);
			update_post_meta($attendee_id, '_tribe_wooticket_product', $product_id);
			update_post_meta($attendee_id, '_tribe_wooticket_order', $order_id);
			update_post_meta($attendee_id, '_tribe_wooticket_order_item', $order_item_id);
			update_post_meta($attendee_id, '_tribe_wooticket_event', $tec_event_id);
			update_post_meta($attendee_id, '_tribe_wooticket_checkedin', 0);
			update_post_meta($attendee_id, '_tribe_wooticket_security_code', $security_code);
			update_post_meta($attendee_id, '_paid_price', '0');
			update_post_meta($attendee_id, '_price_currency_symbol', $currency_symbol);
			update_post_meta($attendee_id, '_vms_vendor_guest_name', $guest_name);
			if ($guest_email !== '') {
				update_post_meta($attendee_id, '_vms_vendor_guest_email', $guest_email);
			}
			if ($phone !== '') {
				update_post_meta($attendee_id, '_vms_vendor_guest_phone', $phone);
			}
			update_post_meta($attendee_id, '_vms_internal_comp_admission', 1);
			update_post_meta($attendee_id, '_vms_vendor_id', $vendor_id);
			update_post_meta($attendee_id, '_vms_event_plan_id', $event_plan_id);
			$attendee_ids[] = (int) $attendee_id;
			do_action('event_ticket_woo_attendee_created', $attendee_id, $tec_event_id, $order, $product_id);
			do_action('event_tickets_woocommerce_ticket_created', $attendee_id, $order_id, $product_id, $i);
		}
		do_action('event_tickets_woocommerce_tickets_generated_for_product', $product_id, $order_id, max(1, $party_size), $tec_event_id);
		update_post_meta($order_id, '_tribe_has_tickets', '1');
		do_action('event_tickets_woocommerce_tickets_generated', $order_id);
		$email_dispatched = 0;
		if ($guest_email !== '' && method_exists($provider, 'send_tickets_email')) {
			$provider->send_tickets_email($order_id);
			$email_dispatched = 1;
		}
		return array(
			'source' => vms_admission_vendor_guest_bridge_source_key(),
			'product_id' => $product_id,
			'order_id' => $order_id,
			'order_item_id' => $order_item_id,
			'attendee_ids' => $attendee_ids,
			'tec_event_id' => $tec_event_id,
			'email_dispatched' => $email_dispatched,
		);
	}
}

if (!function_exists('vms_admission_find_duplicate_entry_count')) {
	function vms_admission_find_duplicate_entry_count(int $event_plan_id, string $guest_name_norm, string $guest_email_norm = '', string $phone_norm = '', int $exclude_entry_id = 0): array
	{
		global $wpdb;
		$table = vms_admission_table_entries();
		$where = array('event_plan_id = %d', "status <> 'canceled'");
		$params = array($event_plan_id);
		if ($exclude_entry_id > 0) {
			$where[] = 'id <> %d';
			$params[] = $exclude_entry_id;
		}
		$identity_where = array();
		if ($guest_email_norm !== '') {
			$identity_where[] = 'guest_email_norm = %s';
			$params[] = $guest_email_norm;
		}
		if ($phone_norm !== '') {
			$identity_where[] = 'phone_norm = %s';
			$params[] = $phone_norm;
		}
		if ($guest_name_norm !== '') {
			$identity_where[] = 'guest_name_norm = %s';
			$params[] = $guest_name_norm;
		}
		if (empty($identity_where)) {
			return array('count' => 0);
		}
		$where[] = '(' . implode(' OR ', $identity_where) . ')';
		$count = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(1) FROM {$table} WHERE " . implode(' AND ', $where),
			$params
		));
		return array('count' => $count);
	}
}

if (!function_exists('vms_admission_vendor_guest_ticket_product_ids')) {
	function vms_admission_vendor_guest_ticket_product_ids(int $event_plan_id): array
	{
		$tec_event_id = (int) get_post_meta($event_plan_id, '_vms_tec_event_id', true);
		$product_ids = array();
		if ($tec_event_id > 0 && function_exists('vms_ticketing_get_ticket_product_ids_for_tec_event')) {
			$product_ids = (array) vms_ticketing_get_ticket_product_ids_for_tec_event($tec_event_id);
		} elseif ($tec_event_id > 0 && function_exists('vms_get_ticket_product_ids_for_event')) {
			$product_ids = (array) vms_get_ticket_product_ids_for_event($tec_event_id);
		} elseif (function_exists('vms_vendor_portal_get_ticket_product_ids')) {
			$product_ids = (array) vms_vendor_portal_get_ticket_product_ids($event_plan_id);
		}
		$product_ids = array_values(array_unique(array_filter(array_map('absint', $product_ids))));
		sort($product_ids, SORT_NUMERIC);
		return $product_ids;
	}
}

if (!function_exists('vms_admission_vendor_guest_product_event_id')) {
	function vms_admission_vendor_guest_product_event_id(int $product_id): int
	{
		$product_id = absint($product_id);
		if ($product_id <= 0) {
			return 0;
		}
		$event_id = (int) get_post_meta($product_id, '_tribe_wooticket_for_event', true);
		if ($event_id <= 0) {
			$event_id = (int) get_post_meta($product_id, '_vms_tec_event_id', true);
		}
		return $event_id;
	}
}

if (!function_exists('vms_admission_vendor_guest_paid_history')) {
	function vms_admission_vendor_guest_paid_history(string $guest_email_norm, string $phone_norm, int $target_event_plan_id = 0): array
	{
		$result = array(
			'same_event_paid' => 0,
			'any_paid' => 0,
			'event_ids' => array(),
			'order_ids' => array(),
		);
		if (!function_exists('wc_get_orders')) {
			return $result;
		}
		$query_sets = array();
		if ($guest_email_norm !== '') {
			$query_sets[] = array(
				'limit' => 100,
				'return' => 'objects',
				'status' => array('wc-pending', 'wc-processing', 'wc-completed', 'wc-on-hold'),
				'billing_email' => $guest_email_norm,
			);
		}
		if ($phone_norm !== '') {
			$query_sets[] = array(
				'limit' => 100,
				'return' => 'objects',
				'status' => array('wc-pending', 'wc-processing', 'wc-completed', 'wc-on-hold'),
				'billing_phone' => $phone_norm,
			);
		}
		if (empty($query_sets)) {
			return $result;
		}
		$target_event_id = $target_event_plan_id > 0 ? (int) get_post_meta($target_event_plan_id, '_vms_tec_event_id', true) : 0;
		$target_products = $target_event_plan_id > 0 ? vms_admission_vendor_guest_ticket_product_ids($target_event_plan_id) : array();
		$target_product_lookup = array_fill_keys($target_products, true);
		$seen_orders = array();
		$seen_events = array();
		foreach ($query_sets as $query) {
			$orders = wc_get_orders($query);
			foreach ((array) $orders as $order) {
				if (!is_object($order) || !method_exists($order, 'get_id')) {
					continue;
				}
				$order_id = (int) $order->get_id();
				if ($order_id <= 0 || isset($seen_orders[$order_id])) {
					continue;
				}
				if (vms_admission_vendor_guest_is_internal_comp_order($order)) {
					continue;
				}
				$seen_orders[$order_id] = true;
				$matched_any = false;
				foreach ((array) $order->get_items() as $item) {
					if (!is_object($item) || !method_exists($item, 'get_product_id')) {
						continue;
					}
					$product_id = (int) $item->get_product_id();
					$variation_id = method_exists($item, 'get_variation_id') ? (int) $item->get_variation_id() : 0;
					$event_id = vms_admission_vendor_guest_product_event_id($variation_id);
					if ($event_id <= 0) {
						$event_id = vms_admission_vendor_guest_product_event_id($product_id);
					}
					if ($event_id > 0) {
						$matched_any = true;
						$seen_events[$event_id] = true;
					}
					if (!empty($target_product_lookup) && (isset($target_product_lookup[$product_id]) || ($variation_id > 0 && isset($target_product_lookup[$variation_id])))) {
						$result['same_event_paid'] = 1;
					}
					if ($target_event_id > 0 && $event_id === $target_event_id) {
						$result['same_event_paid'] = 1;
					}
				}
				if ($matched_any) {
					$result['order_ids'][] = $order_id;
				}
			}
		}
		$result['event_ids'] = array_values(array_map('absint', array_keys($seen_events)));
		$result['any_paid'] = !empty($result['event_ids']) ? 1 : 0;
		return $result;
	}
}

if (!function_exists('vms_admission_vendor_guest_comp_history')) {
	function vms_admission_vendor_guest_comp_history(int $event_plan_id, string $guest_name_norm, string $guest_email_norm, string $phone_norm): array
	{
		global $wpdb;
		$table = vms_admission_table_entries();
		$where = array("status <> 'canceled'", 'event_plan_id <> %d');
		$params = array($event_plan_id);
		$identity = array();
		if ($guest_email_norm !== '') {
			$identity[] = 'guest_email_norm = %s';
			$params[] = $guest_email_norm;
		}
		if ($phone_norm !== '') {
			$identity[] = 'phone_norm = %s';
			$params[] = $phone_norm;
		}
		if ($guest_name_norm !== '' && $guest_email_norm === '' && $phone_norm === '') {
			$identity[] = 'guest_name_norm = %s';
			$params[] = $guest_name_norm;
		}
		if (empty($identity)) {
			return array('count' => 0, 'event_ids' => array());
		}
		$where[] = '(' . implode(' OR ', $identity) . ')';
		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT DISTINCT event_plan_id FROM {$table} WHERE " . implode(' AND ', $where) . ' LIMIT 20',
			$params
		), ARRAY_A);
		$event_ids = array_values(array_unique(array_filter(array_map(static function ($row): int {
				return isset($row['event_plan_id']) ? (int) $row['event_plan_id'] : 0;
			}, (array) $rows))));
		if (($guest_email_norm !== '' || $phone_norm !== '') && function_exists('vms_admission_table_pass_claims')) {
			$claims_table = vms_admission_table_pass_claims();
			$claim_where = array('event_plan_id <> %d');
			$claim_params = array($event_plan_id);
			$claim_identity = array();
			if ($guest_email_norm !== '') {
				$claim_identity[] = 'LOWER(email) = %s';
				$claim_params[] = $guest_email_norm;
			}
			if ($phone_norm !== '') {
				$claim_identity[] = 'phone_norm = %s';
				$claim_params[] = $phone_norm;
			}
			if (!empty($claim_identity)) {
				$claim_where[] = '(' . implode(' OR ', $claim_identity) . ')';
				$claim_rows = $wpdb->get_results($wpdb->prepare(
					"SELECT DISTINCT event_plan_id FROM {$claims_table} WHERE " . implode(' AND ', $claim_where) . ' LIMIT 20',
					$claim_params
				), ARRAY_A);
				foreach ((array) $claim_rows as $row) {
					$event_id = isset($row['event_plan_id']) ? (int) $row['event_plan_id'] : 0;
					if ($event_id > 0) {
						$event_ids[] = $event_id;
					}
				}
			}
		}
		$event_ids = array_values(array_unique(array_filter(array_map('absint', $event_ids))));
		return array('count' => count($event_ids), 'event_ids' => $event_ids);
	}
}

if (!function_exists('vms_admission_vendor_guest_validation_report')) {
	function vms_admission_vendor_guest_validation_report(int $event_plan_id, string $guest_name, string $guest_email, string $phone): array
	{
		$guest_name_norm = vms_admission_normalize_name($guest_name);
		$guest_email_norm = vms_admission_normalize_email($guest_email);
		$phone_norm = vms_admission_normalize_phone($phone);
		$duplicate = vms_admission_find_duplicate_entry_count($event_plan_id, $guest_name_norm, $guest_email_norm, $phone_norm, 0);
		$comp_history = vms_admission_vendor_guest_comp_history($event_plan_id, $guest_name_norm, $guest_email_norm, $phone_norm);
		$paid_history = vms_admission_vendor_guest_paid_history($guest_email_norm, $phone_norm, $event_plan_id);
		return array(
			'guest_name_norm' => $guest_name_norm,
			'guest_email_norm' => $guest_email_norm,
			'phone_norm' => $phone_norm,
			'duplicate_count' => (int) ($duplicate['count'] ?? 0),
			'prior_comp_count' => (int) ($comp_history['count'] ?? 0),
			'prior_comp_event_ids' => array_values(array_map('absint', (array) ($comp_history['event_ids'] ?? array()))),
			'any_paid' => !empty($paid_history['any_paid']) ? 1 : 0,
			'same_event_paid' => !empty($paid_history['same_event_paid']) ? 1 : 0,
			'paid_event_ids' => array_values(array_map('absint', (array) ($paid_history['event_ids'] ?? array()))),
			'order_ids' => array_values(array_map('absint', (array) ($paid_history['order_ids'] ?? array()))),
		);
	}
}

if (!function_exists('vms_admission_vendor_guest_entries_for_vendor')) {
	function vms_admission_vendor_guest_entries_for_vendor(int $event_plan_id, int $vendor_id, bool $include_canceled = true): array
	{
		global $wpdb;
		$table = vms_admission_table_entries();
		$where = 'event_plan_id = %d AND owner_vendor_id = %d AND source = %s';
		$params = array($event_plan_id, $vendor_id, 'vendor_portal');
		if (!$include_canceled) {
			$where .= " AND status <> 'canceled'";
		}
		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC, id DESC",
			$params
		), ARRAY_A);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('vms_admission_vendor_guest_used_headcount')) {
	function vms_admission_vendor_guest_used_headcount(int $event_plan_id, int $vendor_id): int
	{
		$total = 0;
		foreach (vms_admission_vendor_guest_entries_for_vendor($event_plan_id, $vendor_id, false) as $row) {
			$total += max(1, (int) ($row['party_size'] ?? 1));
		}
		return max(0, $total);
	}
}

if (!function_exists('vms_admission_vendor_guest_event_plan_can_vendor_manage')) {
	function vms_admission_vendor_guest_event_plan_can_vendor_manage(int $event_plan_id, int $vendor_id, int $user_id): bool
	{
		if ($event_plan_id <= 0 || $vendor_id <= 0 || $user_id <= 0) {
			return false;
		}
		if (function_exists('vms_user_can_access_vendor') && !vms_user_can_access_vendor($user_id, $vendor_id)) {
			return false;
		}
		if (!in_array($vendor_id, vms_admission_get_event_vendor_ids($event_plan_id), true)) {
			return false;
		}
		return vms_admission_vendor_guest_is_enabled_for_vendor($event_plan_id, $vendor_id);
	}
}

if (!function_exists('vms_admission_vendor_guest_portal_events')) {
	function vms_admission_vendor_guest_portal_events(int $vendor_id): array
	{
		if ($vendor_id <= 0) {
			return array();
		}
		$today = wp_date('Y-m-d', time(), wp_timezone());
		$band_key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'band_vendor_id') : '_vms_band_vendor_id';
		$band_key = $band_key !== '' ? $band_key : '_vms_band_vendor_id';
		$secondary_idx_key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'secondary_vendor_id') : '_vms_secondary_vendor_id';
		$secondary_idx_key = $secondary_idx_key !== '' ? $secondary_idx_key : '_vms_secondary_vendor_id';
		$posts = get_posts(array(
			'post_type' => 'vms_event_plan',
			'post_status' => array('publish', 'private', 'draft', 'pending'),
			'posts_per_page' => 25,
			'orderby' => 'meta_value',
			'meta_key' => '_vms_event_date',
			'order' => 'ASC',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => '_vms_event_date',
					'value' => $today,
					'compare' => '>=',
					'type' => 'DATE',
				),
				array(
					'relation' => 'OR',
					array('key' => $band_key, 'value' => $vendor_id, 'compare' => '=', 'type' => 'NUMERIC'),
					array('key' => $secondary_idx_key, 'value' => $vendor_id, 'compare' => '=', 'type' => 'NUMERIC'),
				),
			),
		));
		$rows = array();
		foreach ((array) $posts as $post) {
			$post_id = (int) $post->ID;
			if (!vms_admission_vendor_guest_is_enabled_for_vendor($post_id, $vendor_id)) {
				continue;
			}
			$plan = vms_admission_event_plan_context($post_id);
			if (!$plan) {
				continue;
			}
			$rule = vms_admission_vendor_guest_get_vendor_rule($post_id, $vendor_id);
			$used = vms_admission_vendor_guest_used_headcount($post_id, $vendor_id);
			$allotment = (int) ($rule['allotment'] ?? 0);
			$remaining = max(0, $allotment - $used);
			$venue_name = (string) ($plan['venue_name'] ?? '');
			$rows[] = array(
				'event_plan_id' => $post_id,
				'title' => (string) get_the_title($post_id),
				'event_date' => (string) ($plan['event_date'] ?? ''),
				'venue_name' => $venue_name,
				'allotment' => $allotment,
				'used' => $used,
				'remaining' => $remaining,
				'first_time_only' => !empty($rule['first_time_only']) ? 1 : 0,
				'entries' => vms_admission_vendor_guest_entries_for_vendor($post_id, $vendor_id, true),
			);
		}
		return $rows;
	}
}

if (!function_exists('vms_admission_render_vendor_guest_config')) {
	function vms_admission_render_vendor_guest_config(WP_Post $post): void
	{
		$event_plan_id = (int) $post->ID;
		$rules = vms_admission_vendor_guest_get_rules($event_plan_id);
		$vendors = vms_admission_get_event_vendor_posts($event_plan_id);
		$fallback_tour = array(
			'tourId' => 'vms.event_plan.vendor_guest.fallback',
			'options' => array(
				'scrollIntoView' => true,
			),
			'steps' => array(
				array(
					'id' => 'vendor_guest_help',
					'selector' => '[data-vms-tour="vendor-guest.help"]',
					'title' => __('What This Does', 'backstage-venue-manager'),
					'html' => wp_kses_post(__('Use this section when you want a vendor to bring a controlled number of their own guests without opening a public free-for-all.', 'backstage-venue-manager')),
					'placement' => 'top',
				),
				array(
					'id' => 'vendor_guest_config',
					'selector' => '[data-vms-tour="vendor-guest.config"]',
					'title' => __('Turn The Program On Per Event', 'backstage-venue-manager'),
					'html' => wp_kses_post(__('Enable the program only for events where you want vendors to add guest names themselves from the portal.', 'backstage-venue-manager')),
					'placement' => 'top',
				),
				array(
					'id' => 'vendor_guest_table',
					'selector' => '[data-vms-tour="vendor-guest.table"]',
					'title' => __('Cap Each Assigned Vendor', 'backstage-venue-manager'),
					'html' => wp_kses_post(__('Give each assigned vendor their own headcount cap and repeat-guest rule so promo partners can stay first-time-only while primary acts can reuse their guest list when needed.', 'backstage-venue-manager')),
					'placement' => 'top',
				),
			),
		);
		$fallback_attr = esc_attr(wp_json_encode($fallback_tour));
		echo '<div class="vms-adm-vendor-guest-config" data-vms-tour="vendor-guest.config">';
		echo '<p class="vms-adm-vendor-guest-help" data-vms-tour="vendor-guest.help"><button type="button" class="button button-secondary vms-tour-help-trigger" data-vms-tour-start="vms.event_plan.vendor_guest" data-vms-tour-fallback="' . $fallback_attr . '" data-vms-tour="vendor-guest.help-action">' . esc_html__('Start Guided Tour', 'backstage-venue-manager') . '</button></p>';
		echo '<h3>' . esc_html__('Vendor-Managed Guest Admissions', 'backstage-venue-manager') . '</h3>';
		echo '<p class="description">' . esc_html__('Let assigned vendors add their own complimentary guests from the vendor portal while you keep control of caps and each vendor’s repeat-guest policy.', 'backstage-venue-manager') . '</p>';
		echo '<p><label><input type="checkbox" name="vms_vendor_guest_rules[enabled]" value="1" ' . checked(!empty($rules['enabled']), true, false) . '> ' . esc_html__('Enable vendor-managed guest admissions for this event', 'backstage-venue-manager') . '</label></p>';
		if (empty($vendors)) {
			echo '<p class="description vms-adm-vendor-guest-empty">' . esc_html__('Assign at least one vendor to this event plan before enabling vendor-managed guest admissions.', 'backstage-venue-manager') . '</p>';
			echo '</div>';
			return;
		}
		echo '<table class="vms-adm-vendor-guest-table" data-vms-tour="vendor-guest.table"><thead><tr><th>' . esc_html__('Vendor', 'backstage-venue-manager') . '</th><th>' . esc_html__('Allow Portal Entries', 'backstage-venue-manager') . '</th><th>' . esc_html__('Guest Headcount Cap', 'backstage-venue-manager') . '</th><th>' . esc_html__('First-Time Guests Only', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
		foreach ($vendors as $vendor) {
			$vendor_id = (int) ($vendor['id'] ?? 0);
			$vendor_rule = vms_admission_vendor_guest_get_vendor_rule($event_plan_id, $vendor_id);
			$row_enabled = !empty($vendor_rule['enabled']);
			$control_state = $row_enabled ? '' : ' disabled';
			echo '<tr data-vms-vendor-guest-row>';
			echo '<td>' . esc_html((string) ($vendor['title'] ?? '')) . '</td>';
			echo '<td><label><input type="checkbox" name="vms_vendor_guest_rules[vendors][' . esc_attr((string) $vendor_id) . '][enabled]" value="1" ' . checked($row_enabled, true, false) . ' data-vms-vendor-guest-toggle> ' . esc_html__('Yes', 'backstage-venue-manager') . '</label></td>';
			echo '<td><input type="number" min="0" step="1" name="vms_vendor_guest_rules[vendors][' . esc_attr((string) $vendor_id) . '][allotment]" value="' . esc_attr((string) max(0, absint($vendor_rule['allotment'] ?? 0))) . '" data-vms-vendor-guest-control="allotment"' . $control_state . '></td>';
			echo '<td><label><input type="checkbox" name="vms_vendor_guest_rules[vendors][' . esc_attr((string) $vendor_id) . '][first_time_only]" value="1" ' . checked(!empty($vendor_rule['first_time_only']), true, false) . ' data-vms-vendor-guest-control="first_time_only"' . $control_state . '> ' . esc_html__('Yes', 'backstage-venue-manager') . '</label></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '</div>';
	}
}

if (!function_exists('vms_admission_save_vendor_guest_config')) {
	function vms_admission_save_vendor_guest_config(int $post_id, WP_Post $post): void
	{
		if ($post->post_type !== 'vms_event_plan') {
			return;
		}
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		if (wp_is_post_revision($post_id)) {
			return;
		}
		if (!isset($_POST['vms_event_plan_details_nonce']) || !wp_verify_nonce((string) wp_unslash($_POST['vms_event_plan_details_nonce']), 'vms_save_event_plan_details')) {
			return;
		}
		if (!current_user_can('edit_post', $post_id)) {
			return;
		}
		if (!isset($_POST['vms_vendor_guest_rules'])) {
			return;
		}
		$raw = wp_unslash($_POST['vms_vendor_guest_rules']);
		if (!is_array($raw)) {
			return;
		}
		$allowed_vendor_ids = vms_admission_get_event_vendor_ids($post_id);
		$allowed_lookup = array_fill_keys($allowed_vendor_ids, true);
		$existing_rules = vms_admission_vendor_guest_get_rules($post_id);
		$normalized = array(
			'enabled' => !empty($raw['enabled']) ? 1 : 0,
			'first_time_only' => !empty($existing_rules['first_time_only']) ? 1 : 0,
			'vendors' => array(),
		);
		foreach ((array) ($raw['vendors'] ?? array()) as $vendor_id => $rule) {
			$vendor_id = absint($vendor_id);
			if ($vendor_id <= 0 || !isset($allowed_lookup[$vendor_id])) {
				continue;
			}
			$rule = is_array($rule) ? $rule : array();
			$normalized['vendors'][$vendor_id] = array(
				'enabled' => !empty($rule['enabled']) ? 1 : 0,
				'allotment' => max(0, absint($rule['allotment'] ?? 0)),
				'first_time_only' => !empty($rule['first_time_only']) ? 1 : 0,
			);
		}
		update_post_meta($post_id, vms_admission_vendor_guest_meta_key(), $normalized);
	}
}
add_action('save_post_vms_event_plan', 'vms_admission_save_vendor_guest_config', 30, 2);

if (!function_exists('vms_admission_vendor_guest_portal_url')) {
	function vms_admission_vendor_guest_portal_url(array $portal_context, int $event_plan_id = 0): string
	{
		$base_url = (string) ($portal_context['base_url'] ?? get_permalink());
		$args = array('tab' => 'guest-list');
		$vendor_id = isset($portal_context['vendor_id']) ? absint($portal_context['vendor_id']) : 0;
		if ($vendor_id > 0) {
			$args['vendor_id'] = $vendor_id;
		}
		if (!empty($portal_context['is_preview']) && function_exists('vms_vendor_portal_get_preview_query_args')) {
			$args = array_merge((array) vms_vendor_portal_get_preview_query_args($vendor_id), $args);
		}
		if ($event_plan_id > 0) {
			$args['guest_event'] = $event_plan_id;
		}
		return (string) add_query_arg($args, $base_url);
	}
}

if (!function_exists('vms_admission_vendor_guest_add_nav_link')) {
	function vms_admission_vendor_guest_add_nav_link(string $tab, array $portal_context): void
	{
		$url = vms_admission_vendor_guest_portal_url($portal_context);
		echo '<a class="' . ($tab === 'guest-list' ? 'is-active' : '') . '" href="' . esc_url($url) . '">' . esc_html__('Guest List', 'backstage-venue-manager') . '</a>';
	}
}
add_action('vms_vendor_portal_nav_links', 'vms_admission_vendor_guest_add_nav_link', 20, 2);

if (!function_exists('vms_admission_vendor_guest_portal_screen_key')) {
	function vms_admission_vendor_guest_portal_screen_key(string $screen_key): string
	{
		if (!is_user_logged_in() || !is_page('vendor-portal')) {
			return $screen_key;
		}
		$tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'dashboard';
		if ($tab === 'guest-list') {
			return 'frontend:vms-vendor-portal-guest-list';
		}
		return $screen_key;
	}
}
add_filter('vms_tours_frontend_screen_key', 'vms_admission_vendor_guest_portal_screen_key', 60);

if (!function_exists('vms_admission_vendor_guest_register_tours')) {
	function vms_admission_vendor_guest_register_tours(array $tours): array
	{
		$tours[] = array(
			'id' => 'vms.event_plan.vendor_guest',
			'title' => __('Vendor-Managed Guest Admissions', 'backstage-venue-manager'),
			'screen' => 'admin:vms_event_plan',
			'version' => '1.0.0',
			'level' => 'beginner',
			'description' => __('Control how many complimentary guests each assigned vendor can add from their portal.', 'backstage-venue-manager'),
			'auto_run' => false,
			'priority' => 20,
			'steps' => array(
				array(
					'id' => 'vendor_guest_help',
					'selector' => '[data-vms-tour="vendor-guest.help"]',
					'title' => __('What This Does', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Use this section when you want a vendor to bring a controlled number of their own guests without opening a public free-for-all.', 'backstage-venue-manager')),
					'placement' => 'top',
				),
				array(
					'id' => 'vendor_guest_config',
					'selector' => '[data-vms-tour="vendor-guest.config"]',
					'title' => __('Turn The Program On Per Event', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Enable the program only for events where you want vendors to add guest names themselves from the portal.', 'backstage-venue-manager')),
					'placement' => 'top',
				),
				array(
					'id' => 'vendor_guest_table',
					'selector' => '[data-vms-tour="vendor-guest.table"]',
					'title' => __('Cap Each Assigned Vendor', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Give each assigned vendor their own headcount cap and repeat-guest rule so promo partners can stay first-time-only while primary acts can reuse their guest list when needed.', 'backstage-venue-manager')),
					'placement' => 'top',
				),
			),
		);
		$tours[] = array(
			'id' => 'vms.vendor.portal.guest_list',
			'title' => __('Vendor Portal Guest List', 'backstage-venue-manager'),
			'screen' => 'frontend:vms-vendor-portal-guest-list',
			'version' => '1.0.0',
			'level' => 'beginner',
			'description' => __('Show vendors how to add their own hosted guests without breaking event rules.', 'backstage-venue-manager'),
			'priority' => 12,
			'auto_run' => true,
			'auto_run_delay_ms' => 700,
			'steps' => array(
				array(
					'id' => 'vendor_guest_portal_help',
					'selector' => '[data-vms-tour="vendor-portal-guest.help"]',
					'title' => __('Stay Inside The Cap', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('This page shows how many complimentary guests you are allowed to add for each upcoming event and how many spots remain.', 'backstage-venue-manager')),
					'placement' => 'top',
				),
				array(
					'id' => 'vendor_guest_portal_card',
					'selector' => '[data-vms-tour="vendor-portal-guest.card"]',
					'title' => __('One Card Per Event', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Each event card shows the current cap, the remaining room, and the rules the venue is enforcing for those guest entries.', 'backstage-venue-manager')),
					'placement' => 'top',
				),
				array(
					'id' => 'vendor_guest_portal_form',
					'selector' => '[data-vms-tour="vendor-portal-guest.form"]',
					'title' => __('Add Real Guest Info', 'backstage-venue-manager'),
					'body' => wp_kses_post(__('Enter the guest’s real contact info so the venue can check for prior comps, prior ticket purchases, and same-event duplicates before admitting them for free.', 'backstage-venue-manager')),
					'placement' => 'top',
				),
			),
		);
		return $tours;
	}
}
add_filter('vms_tours_register', 'vms_admission_vendor_guest_register_tours', 45);

if (!function_exists('vms_admission_vendor_guest_render_custom_tab')) {
	function vms_admission_vendor_guest_render_custom_tab(bool $rendered, string $tab, array $portal_context): bool
	{
		if ($tab !== 'guest-list') {
			return $rendered;
		}
		$vendor_id = isset($portal_context['vendor_id']) ? absint($portal_context['vendor_id']) : 0;
		$user_id = get_current_user_id();
		$is_preview = !empty($portal_context['is_preview']);
		$flash = vms_admission_vendor_guest_pull_flash($user_id);
		$events = vms_admission_vendor_guest_portal_events($vendor_id);
		echo '<div class="vms-vendor-guest-root">';
		echo '<div class="vms-vendor-guest-tour" data-vms-tour="vendor-portal-guest.help"><button type="button" class="button button-secondary vms-tour-help-trigger" data-vms-tour-start="vms.vendor.portal.guest_list">' . esc_html__('Start Guided Tour', 'backstage-venue-manager') . '</button></div>';
		echo '<p class="vms-vendor-guest-help">' . esc_html__('Use this page to add the complimentary guests the venue has allowed for your upcoming events. Every entry is checked against the current door list and paid customer history before it is accepted.', 'backstage-venue-manager') . '</p>';
		if (!empty($flash['message']) && function_exists('vms_portal_notice')) {
			echo vms_portal_notice(!empty($flash['type']) ? (string) $flash['type'] : 'success', (string) $flash['message']);
		}
		if ($is_preview && function_exists('vms_portal_notice')) {
			echo vms_portal_notice('warning', __('Admin preview is read-only here so you can inspect the workflow without changing the vendor’s guest list.', 'backstage-venue-manager'));
		}
		if (empty($events)) {
			if (function_exists('vms_portal_notice')) {
				echo vms_portal_notice('warning', __('No upcoming events currently allow this vendor account to add complimentary guests.', 'backstage-venue-manager'));
			} else {
				echo '<p>' . esc_html__('No upcoming events currently allow this vendor account to add complimentary guests.', 'backstage-venue-manager') . '</p>';
			}
			echo '</div>';
			return true;
		}
		$max_party = max(1, (int) (vms_admission_settings()['max_party_size'] ?? 6));
		$selected_event = isset($_GET['guest_event']) ? absint((string) wp_unslash($_GET['guest_event'])) : 0;
		$event_index = 0;
		foreach ($events as $event) {
			$event_plan_id = (int) ($event['event_plan_id'] ?? 0);
			$card_attr = ($selected_event === $event_plan_id || $event_index === 0) ? ' data-vms-tour="vendor-portal-guest.card"' : '';
			echo '<section class="vms-vendor-guest-card"' . $card_attr . '>';
			echo '<div class="vms-vendor-guest-topline"><div><h3>' . esc_html((string) ($event['title'] ?? '')) . '</h3></div><span class="vms-vendor-guest-pill">' . esc_html(sprintf(__('Remaining: %1$d of %2$d', 'backstage-venue-manager'), (int) ($event['remaining'] ?? 0), (int) ($event['allotment'] ?? 0))) . '</span></div>';
			$meta = array();
			if (!empty($event['event_date'])) {
				$meta[] = esc_html((string) $event['event_date']);
			}
			if (!empty($event['venue_name'])) {
				$meta[] = esc_html((string) $event['venue_name']);
			}
			$meta[] = esc_html(sprintf(__('Used headcount: %d', 'backstage-venue-manager'), (int) ($event['used'] ?? 0)));
			if (!empty($event['first_time_only'])) {
				$meta[] = esc_html__('First-time guests only', 'backstage-venue-manager');
			}
			echo '<div class="vms-vendor-guest-meta"><span>' . implode('</span><span>', $meta) . '</span></div>';
			if (!$is_preview) {
				if (function_exists('wp_enqueue_script')) {
					wp_enqueue_script('vms-vendor-guest-portal', VMS_PLUGIN_URL . 'assets/js/vms-vendor-guest-portal.js', array(), defined('VMS_VERSION') ? VMS_VERSION : false, true);
				}
				$form_anchor = ($selected_event === $event_plan_id || $event_index === 0) ? ' data-vms-tour="vendor-portal-guest.form"' : '';
				echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-vendor-guest-form" data-vms-vendor-guest-form data-max-party="' . esc_attr((string) $max_party) . '"' . $form_anchor . '>';
				echo '<input type="hidden" name="action" value="vms_vendor_guest_portal_submit">';
				echo '<input type="hidden" name="mode" value="add">';
				echo '<input type="hidden" name="event_plan_id" value="' . esc_attr((string) $event_plan_id) . '">';
				echo '<input type="hidden" name="vendor_id" value="' . esc_attr((string) $vendor_id) . '">';
				echo '<input type="hidden" name="return_url" value="' . esc_attr(vms_admission_vendor_guest_portal_url($portal_context, $event_plan_id)) . '">';
				wp_nonce_field('vms_vendor_guest_portal_submit', '_vms_vendor_guest_nonce');
				echo '<div class="vms-vendor-guest-party-list">';
				echo '<fieldset class="vms-vendor-guest-person is-primary" data-vms-guest-slot="1">';
				echo '<legend data-vms-guest-legend>' . esc_html__('Guest Pass', 'backstage-venue-manager') . '</legend>';
				echo '<div class="vms-vendor-guest-person-grid">';
				echo '<label class="vms-vendor-guest-person-grid__party-size">' . esc_html__('Party Size', 'backstage-venue-manager') . '<span class="vms-vendor-guest-qty" data-vms-guest-qty><button type="button" class="button button-secondary vms-vendor-guest-qty__button" data-vms-guest-qty-decrease aria-label="' . esc_attr__('Decrease party size', 'backstage-venue-manager') . '">−</button><input type="number" name="party_size" min="1" max="' . esc_attr((string) $max_party) . '" step="1" value="1" inputmode="numeric" pattern="[0-9]*" data-vms-guest-party-size><button type="button" class="button button-secondary vms-vendor-guest-qty__button" data-vms-guest-qty-increase aria-label="' . esc_attr__('Increase party size', 'backstage-venue-manager') . '">+</button></span><p class="vms-vendor-guest-person-help vms-vendor-guest-person-help--party">' . esc_html__('Increase party size to open connected guest slots below.', 'backstage-venue-manager') . '</p></label>';
				echo '<label>' . esc_html__('Full Name', 'backstage-venue-manager') . ' <span class="vms-vendor-guest-required-marker">*</span><input type="text" name="guest_names[1]" autocomplete="name" data-vms-guest-anchor-name></label>';
				echo '<div class="vms-vendor-guest-contact-group"><span class="vms-vendor-guest-contact-group__label">' . esc_html__('Contact Info', 'backstage-venue-manager') . ' <span class="vms-vendor-guest-required-marker">*</span></span><div class="vms-vendor-guest-contact-group__grid"><label>' . esc_html__('Email', 'backstage-venue-manager') . '<input type="email" name="guest_emails[1]" autocomplete="email"></label><label>' . esc_html__('Phone', 'backstage-venue-manager') . '<input type="text" name="guest_phones[1]" autocomplete="tel"></label></div><p class="vms-vendor-guest-person-help">' . esc_html__('Add at least one: email or phone.', 'backstage-venue-manager') . '</p></div>';
				echo '</div>';
				echo '<div class="vms-vendor-guest-companion-list">';
				for ($slot = 2; $slot <= $max_party; $slot++) {
					$card_hidden = ' hidden';
					$card_disabled = ' disabled';
					$label = sprintf(__('Guest Pass +%d', 'backstage-venue-manager'), $slot - 1);
					echo '<fieldset class="vms-vendor-guest-person is-companion" data-vms-guest-slot="' . esc_attr((string) $slot) . '"' . $card_hidden . '>';
					echo '<legend data-vms-guest-legend>' . esc_html($label) . '</legend>';
					echo '<div class="vms-vendor-guest-person-grid">';
					echo '<label>' . esc_html__('Full Name', 'backstage-venue-manager') . ' <span class="vms-vendor-guest-required-marker">*</span><input type="text" name="guest_names[' . esc_attr((string) $slot) . ']" autocomplete="name"' . $card_disabled . '></label>';
					echo '<div class="vms-vendor-guest-contact-group"><span class="vms-vendor-guest-contact-group__label">' . esc_html__('Contact Info', 'backstage-venue-manager') . ' <span class="vms-vendor-guest-required-marker">*</span></span><div class="vms-vendor-guest-contact-group__grid"><label>' . esc_html__('Email', 'backstage-venue-manager') . '<input type="email" name="guest_emails[' . esc_attr((string) $slot) . ']" autocomplete="email"' . $card_disabled . '></label><label>' . esc_html__('Phone', 'backstage-venue-manager') . '<input type="text" name="guest_phones[' . esc_attr((string) $slot) . ']" autocomplete="tel"' . $card_disabled . '></label></div><p class="vms-vendor-guest-person-help">' . esc_html__('Add at least one: email or phone.', 'backstage-venue-manager') . '</p></div>';
					echo '</div>';
					echo '</fieldset>';
				}
				echo '</div>';
				echo '<div class="vms-vendor-guest-person-footer">';
				echo '<div class="vms-vendor-guest-form-grid">';
				echo '<label class="vms-vendor-guest-form-grid__notes">' . esc_html__('Notes', 'backstage-venue-manager') . '<textarea name="notes" rows="2"></textarea></label>';
				echo '</div>';
				echo '<div class="vms-vendor-guest-form-actions"><button type="submit" class="button button-primary">' . esc_html__('Add Guest Passes', 'backstage-venue-manager') . '</button></div>';
				echo '</div>';
				echo '</fieldset>';
				echo '</div>';
				echo '</form>';
			}
			$entries = (array) ($event['entries'] ?? array());
			if (!empty($entries)) {
				echo '<div class="vms-vendor-guest-list">';
				foreach ($entries as $row) {
					$entry_id = isset($row['id']) ? (int) $row['id'] : 0;
					$status = sanitize_html_class((string) ($row['status'] ?? 'active'));
					echo '<article class="vms-vendor-guest-entry">';
					echo '<div class="vms-vendor-guest-entry-top"><strong>' . esc_html((string) ($row['guest_name'] ?? '')) . '</strong><span class="vms-vendor-guest-status is-' . esc_attr($status) . '">' . esc_html((string) ($row['status'] ?? 'active')) . '</span></div>';
					$entry_meta = array();
					if (!empty($row['guest_email'])) {
						$entry_meta[] = esc_html((string) $row['guest_email']);
					}
					if (!empty($row['phone'])) {
						$entry_meta[] = esc_html((string) $row['phone']);
					}
					$entry_meta[] = esc_html(sprintf(__('Party Size: %d', 'backstage-venue-manager'), max(1, (int) ($row['party_size'] ?? 1))));
					if (!empty($row['notes'])) {
						$entry_meta[] = esc_html((string) $row['notes']);
					}
					echo '<div class="vms-vendor-guest-entry-meta"><span>' . implode('</span><span>', $entry_meta) . '</span></div>';
					if (!$is_preview && $entry_id > 0 && (string) ($row['status'] ?? '') !== 'checked_in' && (string) ($row['status'] ?? '') !== 'canceled') {
						echo '<div class="vms-vendor-guest-form-actions"><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
						echo '<input type="hidden" name="action" value="vms_vendor_guest_portal_submit">';
						echo '<input type="hidden" name="mode" value="cancel">';
						echo '<input type="hidden" name="entry_id" value="' . esc_attr((string) $entry_id) . '">';
						echo '<input type="hidden" name="event_plan_id" value="' . esc_attr((string) $event_plan_id) . '">';
						echo '<input type="hidden" name="vendor_id" value="' . esc_attr((string) $vendor_id) . '">';
						echo '<input type="hidden" name="return_url" value="' . esc_attr(vms_admission_vendor_guest_portal_url($portal_context, $event_plan_id)) . '">';
						wp_nonce_field('vms_vendor_guest_portal_submit', '_vms_vendor_guest_nonce');
						echo '<button type="submit" class="button">' . esc_html__('Remove Guest', 'backstage-venue-manager') . '</button>';
						echo '</form></div>';
					}
					echo '</article>';
				}
				echo '</div>';
			}
			echo '</section>';
			$event_index++;
		}
		echo '</div>';
		return true;
	}
}
add_filter('vms_vendor_portal_render_custom_tab', 'vms_admission_vendor_guest_render_custom_tab', 20, 3);

if (!function_exists('vms_admission_vendor_guest_handle_submit')) {
	function vms_admission_vendor_guest_handle_submit(): void
	{
		if (!is_user_logged_in()) {
			wp_die(esc_html__('Please log in to manage guest entries.', 'backstage-venue-manager'));
		}
		$nonce = isset($_POST['_vms_vendor_guest_nonce']) ? (string) wp_unslash($_POST['_vms_vendor_guest_nonce']) : '';
		if (!wp_verify_nonce($nonce, 'vms_vendor_guest_portal_submit')) {
			wp_die(esc_html__('Security check failed.', 'backstage-venue-manager'));
		}
		$user_id = get_current_user_id();
		$mode = isset($_POST['mode']) ? sanitize_key((string) wp_unslash($_POST['mode'])) : 'add';
		$vendor_id = isset($_POST['vendor_id']) ? absint((string) wp_unslash($_POST['vendor_id'])) : 0;
		$event_plan_id = isset($_POST['event_plan_id']) ? absint((string) wp_unslash($_POST['event_plan_id'])) : 0;
		$return_url = isset($_POST['return_url']) ? esc_url_raw((string) wp_unslash($_POST['return_url'])) : '';
		if ($return_url === '') {
			$return_url = home_url('/vendor-portal/?tab=guest-list');
		}
		$flash_type = 'success';
		$flash_message = __('Guest list updated.', 'backstage-venue-manager');
		if ($mode === 'cancel') {
			$entry_id = isset($_POST['entry_id']) ? absint((string) wp_unslash($_POST['entry_id'])) : 0;
			global $wpdb;
			$table = vms_admission_table_entries();
			$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $entry_id), ARRAY_A);
			if (!is_array($row)) {
				$flash_type = 'error';
				$flash_message = __('That guest entry could not be found.', 'backstage-venue-manager');
			} elseif ((int) ($row['owner_vendor_id'] ?? 0) !== $vendor_id || (int) ($row['event_plan_id'] ?? 0) !== $event_plan_id || (string) ($row['source'] ?? '') !== 'vendor_portal') {
				$flash_type = 'error';
				$flash_message = __('You cannot remove that guest entry.', 'backstage-venue-manager');
			} elseif (!vms_admission_vendor_guest_event_plan_can_vendor_manage($event_plan_id, $vendor_id, $user_id)) {
				$flash_type = 'error';
				$flash_message = __('This event is not currently open for vendor-managed guest entries.', 'backstage-venue-manager');
			} else {
				$claim_meta = $row['claim_meta'] ?? '';
				$bridge = vms_admission_vendor_guest_bridge_context_from_claim_meta($claim_meta);
				vms_admission_vendor_guest_bridge_cancel($bridge, __('Vendor guest entry removed before check-in.', 'backstage-venue-manager'));
				$updated = $wpdb->update(
					$table,
					array(
						'status' => 'canceled',
						'updated_by' => $user_id,
						'updated_at' => vms_admission_now_mysql(),
					),
					array('id' => $entry_id),
					array('%s', '%d', '%s'),
					array('%d')
				);
				if ($updated === false) {
					$flash_type = 'error';
					$flash_message = __('Could not remove that guest entry.', 'backstage-venue-manager');
				} else {
					vms_admission_audit_log($event_plan_id, $entry_id, 'vendor_portal_cancel', $user_id, 'vendor_portal', array(
						'vendor_id' => $vendor_id,
						'bridge' => $bridge,
					));
					$flash_message = __('Guest entry removed.', 'backstage-venue-manager');
				}
			}
		} else {
			if (!vms_admission_vendor_guest_event_plan_can_vendor_manage($event_plan_id, $vendor_id, $user_id)) {
				$flash_type = 'error';
				$flash_message = __('This event is not currently open for vendor-managed guest entries.', 'backstage-venue-manager');
			} else {
				$notes = sanitize_textarea_field((string) wp_unslash($_POST['notes'] ?? ''));
				$party_size = max(1, absint((string) wp_unslash($_POST['party_size'] ?? '1')));
				$settings = vms_admission_settings();
				$max_party = max(1, (int) ($settings['max_party_size'] ?? 6));
				if ($party_size > $max_party) {
					$party_size = $max_party;
				}
				$submitted_guests = vms_admission_vendor_guest_parse_party_guests($_POST, $party_size);
				$rule = vms_admission_vendor_guest_get_vendor_rule($event_plan_id, $vendor_id);
				$used = vms_admission_vendor_guest_used_headcount($event_plan_id, $vendor_id);
				$allotment = (int) ($rule['allotment'] ?? 0);
				if (($used + $party_size) > $allotment) {
					$flash_type = 'error';
					$flash_message = sprintf(__('This guest group would exceed your allowed headcount cap for this event. Remaining spots: %d.', 'backstage-venue-manager'), max(0, $allotment - $used));
				} else {
					$seen_names = array();
					$seen_emails = array();
					$seen_phones = array();
					$validated_guests = array();
					foreach ($submitted_guests as $guest_row) {
						$slot = (int) ($guest_row['slot'] ?? 0);
						$guest_name = (string) ($guest_row['name'] ?? '');
						$guest_email = (string) ($guest_row['email'] ?? '');
						$guest_email_raw = (string) ($guest_row['email_raw'] ?? '');
						$phone = (string) ($guest_row['phone'] ?? '');
						$guest_label = $slot === 1 ? __('the primary guest', 'backstage-venue-manager') : sprintf(__('guest %d', 'backstage-venue-manager'), $slot);
						if (!vms_admission_vendor_guest_has_full_name($guest_name)) {
							$flash_type = 'error';
							$flash_message = sprintf(__('Please enter a real first and last name for %s.', 'backstage-venue-manager'), $guest_label);
							break;
						}
						if ($guest_email_raw !== '' && $guest_email === '') {
							$flash_type = 'error';
							$flash_message = sprintf(__('Please enter a valid email address for %s.', 'backstage-venue-manager'), $guest_label);
							break;
						}
						if ($guest_email === '' && trim($phone) === '') {
							$flash_type = 'error';
							$flash_message = sprintf(__('Please add an email or phone number for %s.', 'backstage-venue-manager'), $guest_label);
							break;
						}
						$name_norm = vms_admission_normalize_name($guest_name);
						$email_norm = vms_admission_normalize_email($guest_email);
						$phone_norm = vms_admission_normalize_phone($phone);
						if ($name_norm !== '' && isset($seen_names[$name_norm])) {
							$flash_type = 'error';
							$flash_message = sprintf(__('You entered %1$s more than once in this guest group. Each person needs their own named pass.', 'backstage-venue-manager'), $guest_name);
							break;
						}
						if ($email_norm !== '' && isset($seen_emails[$email_norm])) {
							$flash_type = 'error';
							$flash_message = sprintf(__('Each guest needs their own contact info. The email for %s is already being used in this guest group.', 'backstage-venue-manager'), $guest_name);
							break;
						}
						if ($phone_norm !== '' && isset($seen_phones[$phone_norm])) {
							$flash_type = 'error';
							$flash_message = sprintf(__('Each guest needs their own contact info. The phone number for %s is already being used in this guest group.', 'backstage-venue-manager'), $guest_name);
							break;
						}
						$report = vms_admission_vendor_guest_validation_report($event_plan_id, $guest_name, $guest_email, $phone);
						if (!empty($report['duplicate_count'])) {
							$flash_type = 'error';
							$flash_message = sprintf(__('%s is already on the list for this event.', 'backstage-venue-manager'), $guest_name);
							break;
						} elseif (!empty($report['same_event_paid'])) {
							$flash_type = 'error';
							$flash_message = sprintf(__('%s already appears to have a paid ticket for this event.', 'backstage-venue-manager'), $guest_name);
							break;
						} elseif (!empty($rule['first_time_only']) && (!empty($report['prior_comp_count']) || !empty($report['any_paid']))) {
							$flash_type = 'error';
							$flash_message = sprintf(__('%s does not qualify for this event’s first-time-only free pass rule.', 'backstage-venue-manager'), $guest_name);
							break;
						}
						$seen_names[$name_norm] = true;
						if ($email_norm !== '') {
							$seen_emails[$email_norm] = true;
						}
						if ($phone_norm !== '') {
							$seen_phones[$phone_norm] = true;
						}
						$validated_guests[] = array(
							'slot' => $slot,
							'name' => $guest_name,
							'email' => $guest_email,
							'phone' => $phone,
							'report' => $report,
						);
					}
					if ($flash_type !== 'error') {
						global $wpdb;
						$table = vms_admission_table_entries();
						$group_key = 'vendor-guest-' . wp_generate_uuid4();
						$created_rows = array();
						$created_bridges = array();
						$email_count = 0;
						$insert_failed = false;
						$venue_id = (int) get_post_meta($event_plan_id, '_vms_venue_id', true);
						foreach ($validated_guests as $guest_payload) {
							$guest_name = (string) $guest_payload['name'];
							$guest_email = (string) $guest_payload['email'];
							$phone = (string) $guest_payload['phone'];
							$report = (array) $guest_payload['report'];
							$bridge = vms_admission_vendor_guest_bridge_create($event_plan_id, $vendor_id, $guest_name, $guest_email, $phone, 1, $notes, $user_id);
							if (is_wp_error($bridge)) {
								$flash_type = 'error';
								$flash_message = $bridge->get_error_message();
								$insert_failed = true;
								break;
							}
							$claim_meta = array(
								'validation' => $report,
								'bridge' => $bridge,
								'group_key' => $group_key,
								'group_size' => count($validated_guests),
								'group_primary_name' => (string) ($validated_guests[0]['name'] ?? ''),
							);
							$insert = $wpdb->insert(
								$table,
								array(
									'event_plan_id' => $event_plan_id,
									'venue_id' => $venue_id,
									'admission_kind' => 'comp',
									'source' => 'vendor_portal',
									'owner_vendor_id' => $vendor_id,
									'guest_name' => $guest_name,
									'guest_name_norm' => (string) ($report['guest_name_norm'] ?? ''),
									'guest_email' => $guest_email,
									'guest_email_norm' => (string) ($report['guest_email_norm'] ?? ''),
									'party_size' => 1,
									'checked_in_qty' => 0,
									'phone' => $phone,
									'phone_norm' => (string) ($report['phone_norm'] ?? ''),
									'notes' => $notes,
									'status' => 'active',
									'claim_reference' => $group_key,
									'claim_meta' => wp_json_encode($claim_meta),
									'created_by' => $user_id,
									'created_at' => vms_admission_now_mysql(),
								),
								array('%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
							);
							if ($insert === false) {
								vms_admission_vendor_guest_bridge_cancel($bridge, __('Bridge cleanup after admission row insert failure.', 'backstage-venue-manager'));
								$flash_type = 'error';
								$flash_message = __('Could not add that guest right now.', 'backstage-venue-manager');
								$insert_failed = true;
								break;
							}
							$entry_id = (int) $wpdb->insert_id;
							if (function_exists('vms_admission_ensure_entry_token')) {
								vms_admission_ensure_entry_token($entry_id);
							}
							$created_rows[] = $entry_id;
							$created_bridges[] = $bridge;
							if (!empty($bridge['email_dispatched'])) {
								$email_count++;
							}
							vms_admission_audit_log($event_plan_id, $entry_id, 'vendor_portal_create', $user_id, 'vendor_portal', array(
								'vendor_id' => $vendor_id,
								'validation' => $report,
								'bridge' => $bridge,
								'group_key' => $group_key,
								'group_size' => count($validated_guests),
							));
						}
						if ($insert_failed) {
							foreach ($created_bridges as $bridge) {
								vms_admission_vendor_guest_bridge_cancel($bridge, __('Bridge rollback after partial vendor guest failure.', 'backstage-venue-manager'));
							}
							foreach ($created_rows as $entry_id) {
								$wpdb->delete($table, array('id' => $entry_id), array('%d'));
							}
						} else {
							$total_added = count($validated_guests);
							if ($email_count > 0 && $email_count === $total_added) {
								$flash_message = sprintf(_n('%d guest pass added and emailed for scanner check-in.', '%d guest passes added and emailed for scanner check-in.', $total_added, 'backstage-venue-manager'), $total_added);
							} elseif ($email_count > 0) {
								$flash_message = sprintf(_n('%1$d guest pass added. %2$d pass email was sent and the rest are ready in the scanner.', '%1$d guest passes added. %2$d pass emails were sent and the rest are ready in the scanner.', $total_added, 'backstage-venue-manager'), $total_added, $email_count);
							} else {
								$flash_message = sprintf(_n('%d guest pass added and ready in the scanner.', '%d guest passes added and ready in the scanner.', $total_added, 'backstage-venue-manager'), $total_added);
							}
						}
					}
				}
			}
		}
		vms_admission_vendor_guest_set_flash($user_id, array('type' => $flash_type, 'message' => $flash_message));
		wp_safe_redirect($return_url);
		exit;
	}
}
add_action('admin_post_vms_vendor_guest_portal_submit', 'vms_admission_vendor_guest_handle_submit');
