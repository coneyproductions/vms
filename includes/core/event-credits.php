<?php
/**
 * VMS Event Credits
 *
 * Cancellation-resolution foundation for optional customer event credits.
 * Refunds remain available; credits are opt-in records/coupons that can be
 * issued when a customer chooses credit instead of a refund.
 */

defined('ABSPATH') || exit;

if (!defined('VMS_CPT_EVENT_CREDIT')) {
	define('VMS_CPT_EVENT_CREDIT', 'vms_event_credit');
}

if (!function_exists('vms_event_credit_meta_keys')) {
	function vms_event_credit_meta_keys(): array
	{
		return array(
			'code' => '_vms_event_credit_code',
			'status' => '_vms_event_credit_status',
			'amount' => '_vms_event_credit_amount',
			'currency' => '_vms_event_credit_currency',
			'customer_email' => '_vms_event_credit_customer_email',
			'customer_name' => '_vms_event_credit_customer_name',
			'original_event_plan_id' => '_vms_event_credit_original_event_plan_id',
			'original_tec_event_id' => '_vms_event_credit_original_tec_event_id',
			'original_order_id' => '_vms_event_credit_original_order_id',
			'original_order_number' => '_vms_event_credit_original_order_number',
			'line_items' => '_vms_event_credit_line_items',
			'coupon_id' => '_vms_event_credit_coupon_id',
			'expires_at_gmt' => '_vms_event_credit_expires_at_gmt',
			'issued_at_gmt' => '_vms_event_credit_issued_at_gmt',
			'issued_by_user_id' => '_vms_event_credit_issued_by_user_id',
			'emailed_at_gmt' => '_vms_event_credit_emailed_at_gmt',
			'redeemed_at_gmt' => '_vms_event_credit_redeemed_at_gmt',
			'redeemed_order_id' => '_vms_event_credit_redeemed_order_id',
			'notes' => '_vms_event_credit_notes',
		);
	}
}

if (!function_exists('vms_event_credit_status_options')) {
	function vms_event_credit_status_options(): array
	{
		return array(
			'issued' => __('Issued', 'vms'),
			'emailed' => __('Issued + emailed', 'vms'),
			'redeemed' => __('Redeemed', 'vms'),
			'expired' => __('Expired', 'vms'),
			'voided' => __('Voided', 'vms'),
		);
	}
}

add_action('init', 'vms_register_event_credit_cpt');
if (!function_exists('vms_register_event_credit_cpt')) {
	function vms_register_event_credit_cpt(): void
	{
		register_post_type(VMS_CPT_EVENT_CREDIT, array(
			'labels' => array(
				'name' => __('Event Credits', 'vms'),
				'singular_name' => __('Event Credit', 'vms'),
				'menu_name' => __('Event Credits', 'vms'),
				'add_new_item' => __('Add Event Credit', 'vms'),
				'edit_item' => __('Edit Event Credit', 'vms'),
				'view_item' => __('View Event Credit', 'vms'),
				'search_items' => __('Search Event Credits', 'vms'),
			),
			'public' => false,
			'show_ui' => true,
			'show_in_menu' => 'vms-dashboard',
			'supports' => array('title'),
			'capability_type' => 'post',
			'capabilities' => array(
				'create_posts' => 'do_not_allow',
				'edit_post' => 'manage_options',
				'read_post' => 'manage_options',
				'delete_post' => 'manage_options',
				'edit_posts' => 'manage_options',
				'edit_others_posts' => 'manage_options',
				'publish_posts' => 'manage_options',
				'read_private_posts' => 'manage_options',
			),
			'map_meta_cap' => false,
			'has_archive' => false,
			'rewrite' => false,
		));
	}
}

if (!function_exists('vms_event_credit_format_money')) {
	function vms_event_credit_format_money($amount, string $currency = ''): string
	{
		$amount = (float) $amount;
		if (function_exists('wc_price')) {
			$args = array();
			if ($currency !== '') {
				$args['currency'] = $currency;
			}
			return wp_strip_all_tags((string) wc_price($amount, $args));
		}
		return '$' . number_format($amount, 2);
	}
}

if (!function_exists('vms_event_credit_generate_code')) {
	function vms_event_credit_generate_code(): string
	{
		$prefix = strtoupper((string) apply_filters('vms_event_credit_code_prefix', 'EVENT-CREDIT'));
		$prefix = preg_replace('/[^A-Z0-9\-]/', '', $prefix);
		if ($prefix === '') {
			$prefix = 'EVENT-CREDIT';
		}

		for ($i = 0; $i < 20; $i++) {
			$token = strtoupper(wp_generate_password(8, false, false));
			$code = $prefix . '-' . $token;
			$exists = function_exists('wc_get_coupon_id_by_code') ? absint(wc_get_coupon_id_by_code($code)) : 0;
			$existing_credit = get_posts(array(
				'post_type' => VMS_CPT_EVENT_CREDIT,
				'post_status' => array('publish', 'private', 'draft'),
				'fields' => 'ids',
				'posts_per_page' => 1,
				'meta_key' => '_vms_event_credit_code',
				'meta_value' => $code,
			));
			if ($exists <= 0 && empty($existing_credit)) {
				return $code;
			}
		}

		return $prefix . '-' . strtoupper(wp_generate_uuid4());
	}
}

if (!function_exists('vms_event_credit_default_expiration_gmt')) {
	function vms_event_credit_default_expiration_gmt(): string
	{
		$months = (int) apply_filters('vms_event_credit_default_expiration_months', 12);
		if ($months < 1) {
			$months = 12;
		}
		$expires = strtotime('+' . $months . ' months', time());
		return gmdate('Y-m-d 23:59:59', $expires);
	}
}

if (!function_exists('vms_event_credit_find_existing')) {
	function vms_event_credit_find_existing(int $event_plan_id, int $order_id): int
	{
		$event_plan_id = absint($event_plan_id);
		$order_id = absint($order_id);
		if ($event_plan_id <= 0 || $order_id <= 0) {
			return 0;
		}

		$ids = get_posts(array(
			'post_type' => VMS_CPT_EVENT_CREDIT,
			'post_status' => array('publish', 'private', 'draft'),
			'fields' => 'ids',
			'posts_per_page' => 1,
			'orderby' => 'ID',
			'order' => 'DESC',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => '_vms_event_credit_original_event_plan_id',
					'value' => $event_plan_id,
					'compare' => '=',
				),
				array(
					'key' => '_vms_event_credit_original_order_id',
					'value' => $order_id,
					'compare' => '=',
				),
			),
		));

		return !empty($ids) ? absint($ids[0]) : 0;
	}
}

if (!function_exists('vms_event_credits_get_latest_candidate_scan')) {
	function vms_event_credits_get_latest_candidate_scan(int $event_plan_id): array
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return array();
		}

		$scan = get_post_meta($event_plan_id, '_vms_event_credit_candidate_scan', true);
		if (is_array($scan) && !empty($scan['data']) && is_array($scan['data'])) {
			return $scan;
		}

		$summary_key = function_exists('vms_meta_key')
			? (vms_meta_key('event_plan', 'cancel_job_summary') ?: '_vms_cancel_job_summary')
			: '_vms_cancel_job_summary';
		$summary = get_post_meta($event_plan_id, $summary_key, true);
		if (!is_array($summary) || empty($summary['steps']) || !is_array($summary['steps'])) {
			return array();
		}

		foreach ($summary['steps'] as $step) {
			if (!is_array($step) || sanitize_key((string) ($step['key'] ?? '')) !== 'refund_discovery') {
				continue;
			}
			$data = isset($step['data']) && is_array($step['data']) ? $step['data'] : array();
			if (!empty($data)) {
				return array(
					'status' => sanitize_key((string) ($step['status'] ?? '')),
					'message' => sanitize_text_field((string) ($step['message'] ?? '')),
					'scanned_at_gmt' => sanitize_text_field((string) ($step['updated_at_gmt'] ?? '')),
					'source' => 'cancellation_job_summary',
					'data' => $data,
				);
			}
		}

		return array();
	}
}

if (!function_exists('vms_event_credits_discover_candidates')) {
	function vms_event_credits_discover_candidates(int $event_plan_id): array
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return array('status' => 'failed', 'message' => 'invalid_event_plan_id', 'data' => array());
		}

		if (!function_exists('vms_cancellation_run_step')) {
			return array('status' => 'blocked', 'message' => 'cancellation_engine_unavailable', 'data' => array());
		}

		$summary_key = function_exists('vms_meta_key')
			? (vms_meta_key('event_plan', 'cancel_job_summary') ?: '_vms_cancel_job_summary')
			: '_vms_cancel_job_summary';
		$summary = get_post_meta($event_plan_id, $summary_key, true);
		if (!is_array($summary)) {
			$summary = array('event_plan_id' => $event_plan_id);
		}

		$result = vms_cancellation_run_step($event_plan_id, 'stop_sales_queue_refunds', 'refund_discovery', $summary);
		if (!is_array($result)) {
			$result = array('status' => 'failed', 'message' => 'candidate_scan_failed', 'data' => array());
		}

		$scan = array(
			'status' => sanitize_key((string) ($result['status'] ?? 'failed')),
			'message' => sanitize_text_field((string) ($result['message'] ?? '')),
			'scanned_at_gmt' => gmdate('Y-m-d H:i:s'),
			'source' => 'event_credit_manual_scan',
			'data' => isset($result['data']) && is_array($result['data']) ? $result['data'] : array(),
		);
		update_post_meta($event_plan_id, '_vms_event_credit_candidate_scan', $scan);

		return $scan;
	}
}

if (!function_exists('vms_event_credits_find_candidate_for_order')) {
	function vms_event_credits_find_candidate_for_order(int $event_plan_id, int $order_id): array
	{
		$order_id = absint($order_id);
		if ($order_id <= 0) {
			return array();
		}

		$scan = vms_event_credits_get_latest_candidate_scan($event_plan_id);
		$candidates = isset($scan['data']['candidates']) && is_array($scan['data']['candidates']) ? $scan['data']['candidates'] : array();
		foreach ($candidates as $candidate) {
			if (is_array($candidate) && absint($candidate['order_id'] ?? 0) === $order_id) {
				return $candidate;
			}
		}

		$scan = vms_event_credits_discover_candidates($event_plan_id);
		$candidates = isset($scan['data']['candidates']) && is_array($scan['data']['candidates']) ? $scan['data']['candidates'] : array();
		foreach ($candidates as $candidate) {
			if (is_array($candidate) && absint($candidate['order_id'] ?? 0) === $order_id) {
				return $candidate;
			}
		}

		return array();
	}
}

if (!function_exists('vms_event_credit_create_coupon')) {
	function vms_event_credit_create_coupon(int $credit_id, string $code, float $amount, string $customer_email, string $expires_at_gmt): int
	{
		$credit_id = absint($credit_id);
		$amount = max(0.0, (float) $amount);
		if (!function_exists('wc_format_coupon_code')) {
			return 0;
		}
		$code = wc_format_coupon_code($code);
		if ($credit_id <= 0 || $code === '' || $amount <= 0 || !post_type_exists('shop_coupon')) {
			return 0;
		}

		$existing_coupon_id = function_exists('wc_get_coupon_id_by_code') ? absint(wc_get_coupon_id_by_code($code)) : 0;
		if ($existing_coupon_id > 0) {
			return $existing_coupon_id;
		}

		$coupon_id = wp_insert_post(array(
			'post_title' => $code,
			'post_content' => '',
			'post_status' => 'publish',
			'post_author' => get_current_user_id(),
			'post_type' => 'shop_coupon',
		), true);

		if (is_wp_error($coupon_id) || absint($coupon_id) <= 0) {
			return 0;
		}

		$coupon_id = absint($coupon_id);
		update_post_meta($coupon_id, 'discount_type', 'fixed_cart');
		update_post_meta($coupon_id, 'coupon_amount', wc_format_decimal($amount, 2));
		update_post_meta($coupon_id, 'individual_use', 'yes');
		update_post_meta($coupon_id, 'usage_limit', '1');
		update_post_meta($coupon_id, 'usage_limit_per_user', '1');
		update_post_meta($coupon_id, 'free_shipping', 'no');
		update_post_meta($coupon_id, 'exclude_sale_items', 'no');
		update_post_meta($coupon_id, '_vms_event_credit_id', $credit_id);

		$restrict_to_email = (bool) apply_filters('vms_event_credit_restrict_coupon_to_customer_email', true, $credit_id, $customer_email);
		if ($restrict_to_email && is_email($customer_email)) {
			update_post_meta($coupon_id, 'customer_email', array(sanitize_email($customer_email)));
		}

		$expires_ts = $expires_at_gmt !== '' ? strtotime($expires_at_gmt . ' GMT') : 0;
		if ($expires_ts > 0) {
			update_post_meta($coupon_id, 'date_expires', $expires_ts);
		}

		return $coupon_id;
	}
}

if (!function_exists('vms_event_credit_send_email')) {
	function vms_event_credit_send_email(int $credit_id): bool
	{
		$credit_id = absint($credit_id);
		if ($credit_id <= 0 || get_post_type($credit_id) !== VMS_CPT_EVENT_CREDIT) {
			return false;
		}

		$keys = vms_event_credit_meta_keys();
		$email = sanitize_email((string) get_post_meta($credit_id, $keys['customer_email'], true));
		if (!is_email($email)) {
			return false;
		}

		$code = sanitize_text_field((string) get_post_meta($credit_id, $keys['code'], true));
		$amount = (float) get_post_meta($credit_id, $keys['amount'], true);
		$currency = sanitize_text_field((string) get_post_meta($credit_id, $keys['currency'], true));
		$event_plan_id = absint(get_post_meta($credit_id, $keys['original_event_plan_id'], true));
		$expires_at_gmt = sanitize_text_field((string) get_post_meta($credit_id, $keys['expires_at_gmt'], true));
		$event_title = $event_plan_id > 0 ? trim((string) get_the_title($event_plan_id)) : '';
		if ($event_title === '') {
			$event_title = __('your cancelled event', 'vms');
		}

		$site_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
		$subject = sprintf(__('%s Event Credit', 'vms'), $site_name);
		$expires_text = '';
		$expires_ts = $expires_at_gmt !== '' ? strtotime($expires_at_gmt . ' GMT') : 0;
		if ($expires_ts > 0) {
			$expires_text = wp_date('F j, Y', $expires_ts, wp_timezone());
		}

		$lines = array();
		$lines[] = sprintf(__('Because %s was cancelled, we have issued an event credit for %s.', 'vms'), $event_title, vms_event_credit_format_money($amount, $currency));
		$lines[] = '';
		$lines[] = sprintf(__('Event Credit Code: %s', 'vms'), $code);
		if ($expires_text !== '') {
			$lines[] = sprintf(__('Good through: %s', 'vms'), $expires_text);
		}
		$lines[] = '';
		$lines[] = __('Use this code at checkout for an eligible future event. If you would rather receive a refund instead, reply to this message and we will help.', 'vms');
		$lines[] = '';
		$lines[] = sprintf(__('Thank you, %s', 'vms'), $site_name);

		$sent = wp_mail($email, $subject, implode("\n", $lines));
		if ($sent) {
			update_post_meta($credit_id, $keys['emailed_at_gmt'], gmdate('Y-m-d H:i:s'));
			update_post_meta($credit_id, $keys['status'], 'emailed');
		}

		return (bool) $sent;
	}
}

if (!function_exists('vms_event_credit_create_for_order')) {
	function vms_event_credit_create_for_order(int $event_plan_id, int $order_id, array $args = array()): array
	{
		$event_plan_id = absint($event_plan_id);
		$order_id = absint($order_id);
		if ($event_plan_id <= 0 || $order_id <= 0) {
			return array('ok' => false, 'credit_id' => 0, 'error' => 'invalid_request');
		}
		if (!function_exists('wc_get_order')) {
			return array('ok' => false, 'credit_id' => 0, 'error' => 'woocommerce_unavailable');
		}

		$existing_id = vms_event_credit_find_existing($event_plan_id, $order_id);
		if ($existing_id > 0) {
			return array('ok' => true, 'credit_id' => $existing_id, 'existing' => true);
		}

		$candidate = vms_event_credits_find_candidate_for_order($event_plan_id, $order_id);
		if (empty($candidate)) {
			return array('ok' => false, 'credit_id' => 0, 'error' => 'order_not_credit_eligible');
		}

		$order = wc_get_order($order_id);
		if (!$order || !is_object($order)) {
			return array('ok' => false, 'credit_id' => 0, 'error' => 'order_not_found');
		}

		$amount = isset($candidate['estimated_refund_total']) ? (float) $candidate['estimated_refund_total'] : 0.0;
		$remaining_total = method_exists($order, 'get_total_refunded') ? max(0.0, ((float) $order->get_total()) - ((float) $order->get_total_refunded())) : $amount;
		$amount = min(max(0.0, $amount), max(0.0, $remaining_total));
		if ($amount <= 0) {
			return array('ok' => false, 'credit_id' => 0, 'error' => 'no_remaining_credit_amount');
		}

		$code = vms_event_credit_generate_code();
		$customer_email = method_exists($order, 'get_billing_email') ? sanitize_email((string) $order->get_billing_email()) : '';
		$first = method_exists($order, 'get_billing_first_name') ? trim((string) $order->get_billing_first_name()) : '';
		$last = method_exists($order, 'get_billing_last_name') ? trim((string) $order->get_billing_last_name()) : '';
		$customer_name = trim($first . ' ' . $last);
		$currency = method_exists($order, 'get_currency') ? sanitize_text_field((string) $order->get_currency()) : '';
		$order_number = method_exists($order, 'get_order_number') ? sanitize_text_field((string) $order->get_order_number()) : (string) $order_id;
		$expires_at_gmt = !empty($args['expires_at_gmt']) ? sanitize_text_field((string) $args['expires_at_gmt']) : vms_event_credit_default_expiration_gmt();
		$tec_key = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'tec_event_id') ?: '_vms_tec_event_id') : '_vms_tec_event_id';
		$tec_event_id = absint(get_post_meta($event_plan_id, $tec_key, true));

		$title = sprintf(__('Event Credit %1$s — Order %2$s', 'vms'), $code, $order_number);
		$credit_id = wp_insert_post(array(
			'post_type' => VMS_CPT_EVENT_CREDIT,
			'post_status' => 'publish',
			'post_title' => $title,
			'post_author' => get_current_user_id(),
		), true);

		if (is_wp_error($credit_id) || absint($credit_id) <= 0) {
			return array('ok' => false, 'credit_id' => 0, 'error' => 'credit_insert_failed');
		}

		$credit_id = absint($credit_id);
		$keys = vms_event_credit_meta_keys();
		update_post_meta($credit_id, $keys['code'], $code);
		update_post_meta($credit_id, $keys['status'], 'issued');
		update_post_meta($credit_id, $keys['amount'], wc_format_decimal($amount, 2));
		update_post_meta($credit_id, $keys['currency'], $currency);
		update_post_meta($credit_id, $keys['customer_email'], $customer_email);
		update_post_meta($credit_id, $keys['customer_name'], $customer_name);
		update_post_meta($credit_id, $keys['original_event_plan_id'], $event_plan_id);
		update_post_meta($credit_id, $keys['original_tec_event_id'], $tec_event_id);
		update_post_meta($credit_id, $keys['original_order_id'], $order_id);
		update_post_meta($credit_id, $keys['original_order_number'], $order_number);
		update_post_meta($credit_id, $keys['line_items'], isset($candidate['line_items']) && is_array($candidate['line_items']) ? $candidate['line_items'] : array());
		update_post_meta($credit_id, $keys['expires_at_gmt'], $expires_at_gmt);
		update_post_meta($credit_id, $keys['issued_at_gmt'], gmdate('Y-m-d H:i:s'));
		update_post_meta($credit_id, $keys['issued_by_user_id'], get_current_user_id());

		$coupon_id = function_exists('wc_format_coupon_code') ? vms_event_credit_create_coupon($credit_id, $code, $amount, $customer_email, $expires_at_gmt) : 0;
		if ($coupon_id > 0) {
			update_post_meta($credit_id, $keys['coupon_id'], $coupon_id);
			update_post_meta($coupon_id, '_vms_event_credit_id', $credit_id);
			update_post_meta($coupon_id, '_vms_event_credit_original_event_plan_id', $event_plan_id);
			update_post_meta($coupon_id, '_vms_event_credit_original_order_id', $order_id);
		}

		if (method_exists($order, 'add_order_note')) {
			$order->add_order_note(sprintf('VMS Event Credit %1$s issued for %2$s from cancelled Event Plan #%3$d.', $code, vms_event_credit_format_money($amount, $currency), $event_plan_id));
		}

		$email_sent = false;
		if (!empty($args['send_email'])) {
			$email_sent = vms_event_credit_send_email($credit_id);
		}

		do_action('vms_event_credit_created', $credit_id, $event_plan_id, $order_id, $candidate, $args);

		return array(
			'ok' => true,
			'credit_id' => $credit_id,
			'coupon_id' => $coupon_id,
			'code' => $code,
			'amount' => $amount,
			'email_sent' => $email_sent,
		);
	}
}

add_action('save_post_vms_event_plan', 'vms_event_credits_handle_event_plan_save', 30, 2);
if (!function_exists('vms_event_credits_handle_event_plan_save')) {
	function vms_event_credits_handle_event_plan_save(int $post_id, WP_Post $post): void
	{
		if (!isset($_POST['vms_event_plan_details_nonce']) || !wp_verify_nonce((string) $_POST['vms_event_plan_details_nonce'], 'vms_save_event_plan_details')) {
			return;
		}
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		if (!current_user_can('edit_post', $post_id)) {
			return;
		}
		if (!isset($_POST['vms_event_credit_action'])) {
			return;
		}

		$action_raw = sanitize_text_field((string) wp_unslash($_POST['vms_event_credit_action']));
		$action_raw = trim($action_raw);
		if ($action_raw === '') {
			return;
		}

		if ($action_raw === 'refresh_candidates') {
			$scan = vms_event_credits_discover_candidates($post_id);
			$count = isset($scan['data']['candidate_order_count']) ? absint($scan['data']['candidate_order_count']) : 0;
			if (function_exists('vms_add_admin_notice')) {
				vms_add_admin_notice(sprintf(__('Event Credit candidate scan complete. Found %d candidate order(s).', 'vms'), $count), 'success');
			}
			update_post_meta($post_id, '_vms_admin_scroll_to', 'vms_event_credits_panel');
			return;
		}

		$send_email = false;
		$order_id = 0;
		if (strpos($action_raw, 'issue_email:') === 0) {
			$send_email = true;
			$order_id = absint(substr($action_raw, strlen('issue_email:')));
		} elseif (strpos($action_raw, 'issue_only:') === 0) {
			$order_id = absint(substr($action_raw, strlen('issue_only:')));
		}
		if ($order_id <= 0) {
			return;
		}

		$result = vms_event_credit_create_for_order($post_id, $order_id, array('send_email' => $send_email));
		if (!empty($result['ok'])) {
			$credit_id = absint($result['credit_id'] ?? 0);
			$code = sanitize_text_field((string) ($result['code'] ?? get_post_meta($credit_id, '_vms_event_credit_code', true)));
			if (function_exists('vms_add_admin_notice')) {
				$msg = !empty($result['existing'])
					? sprintf(__('An Event Credit already exists for this order: %s', 'vms'), $code)
					: sprintf(__('Event Credit created: %s', 'vms'), $code);
				if ($send_email && empty($result['email_sent'])) {
					$msg .= ' ' . __('The credit was created, but the customer email could not be sent.', 'vms');
				}
				vms_add_admin_notice($msg, empty($result['email_sent']) && $send_email ? 'warning' : 'success');
			}
		} else {
			if (function_exists('vms_add_admin_notice')) {
				$error = sanitize_text_field((string) ($result['error'] ?? 'event_credit_failed'));
				vms_add_admin_notice(sprintf(__('Event Credit could not be created: %s', 'vms'), $error), 'error');
			}
		}
		update_post_meta($post_id, '_vms_admin_scroll_to', 'vms_event_credits_panel');
	}
}

if (!function_exists('vms_event_credits_render_event_plan_panel')) {
	function vms_event_credits_render_event_plan_panel(int $event_plan_id, string $plan_status = ''): void
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return;
		}
		$status_key = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'status') ?: '_vms_event_plan_status') : '_vms_event_plan_status';
		if ($plan_status === '') {
			$plan_status = sanitize_key((string) get_post_meta($event_plan_id, $status_key, true));
		}
		if ($plan_status !== 'cancelled') {
			return;
		}

		$scan = vms_event_credits_get_latest_candidate_scan($event_plan_id);
		$candidates = isset($scan['data']['candidates']) && is_array($scan['data']['candidates']) ? $scan['data']['candidates'] : array();
		$scanned_at = sanitize_text_field((string) ($scan['scanned_at_gmt'] ?? ''));
		$source = sanitize_key((string) ($scan['source'] ?? ''));
		?>
		<div id="vms_event_credits_panel" class="vms-event-credits-panel">
			<hr />
			<h4><?php esc_html_e('Customer Resolution: Event Credits', 'vms'); ?></h4>
			<p class="description">
				<?php esc_html_e('Refunds should remain available. Use Event Credits only when a customer chooses credit toward a future eligible event instead of a refund.', 'vms'); ?>
			</p>
			<p>
				<button type="submit" name="vms_event_credit_action" value="refresh_candidates" class="button button-secondary">
					<?php esc_html_e('Refresh Eligible Orders', 'vms'); ?>
				</button>
			</p>
			<?php if ($scanned_at !== '') : ?>
				<p class="description">
					<?php
					printf(
						/* translators: 1: GMT date, 2: source key */
						esc_html__('Latest scan: %1$s GMT (%2$s).', 'vms'),
						esc_html($scanned_at),
						esc_html($source !== '' ? $source : 'unknown')
					);
					?>
				</p>
			<?php endif; ?>

			<?php if (empty($candidates)) : ?>
				<p class="description"><?php esc_html_e('No eligible paid ticket/add-on orders are currently available for Event Credit issuance. Refresh the scan after cancellation/refund discovery if this looks wrong.', 'vms'); ?></p>
			<?php else : ?>
				<div class="vms-event-credits-table-wrap">
					<table class="widefat striped vms-event-credits-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Order', 'vms'); ?></th>
								<th><?php esc_html_e('Customer', 'vms'); ?></th>
								<th><?php esc_html_e('Eligible Amount', 'vms'); ?></th>
								<th><?php esc_html_e('Lines', 'vms'); ?></th>
								<th><?php esc_html_e('Credit', 'vms'); ?></th>
								<th><?php esc_html_e('Action', 'vms'); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($candidates as $candidate) : ?>
							<?php
							if (!is_array($candidate)) {
								continue;
							}
							$order_id = absint($candidate['order_id'] ?? 0);
							if ($order_id <= 0) {
								continue;
							}
							$order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
							$order_number = sanitize_text_field((string) ($candidate['order_number'] ?? $order_id));
							$currency = sanitize_text_field((string) ($candidate['currency'] ?? ''));
							$amount = (float) ($candidate['estimated_refund_total'] ?? 0.0);
							$email = ($order && method_exists($order, 'get_billing_email')) ? sanitize_email((string) $order->get_billing_email()) : '';
							$name = '';
							if ($order && method_exists($order, 'get_billing_first_name')) {
								$name = trim((string) $order->get_billing_first_name() . ' ' . (string) $order->get_billing_last_name());
							}
							$lines = isset($candidate['line_items']) && is_array($candidate['line_items']) ? $candidate['line_items'] : array();
							$line_names = array();
							foreach ($lines as $line) {
								if (!is_array($line)) {
									continue;
								}
								$label = trim((string) ($line['name'] ?? ''));
								$qty = (float) ($line['refundable_qty'] ?? 0.0);
								if ($label !== '') {
									$line_names[] = ($qty > 0 ? rtrim(rtrim(number_format($qty, 2), '0'), '.') . ' × ' : '') . $label;
								}
							}
							$existing_credit_id = vms_event_credit_find_existing($event_plan_id, $order_id);
							$existing_code = $existing_credit_id > 0 ? sanitize_text_field((string) get_post_meta($existing_credit_id, '_vms_event_credit_code', true)) : '';
							$existing_status = $existing_credit_id > 0 ? sanitize_key((string) get_post_meta($existing_credit_id, '_vms_event_credit_status', true)) : '';
							$credit_url = $existing_credit_id > 0 ? get_edit_post_link($existing_credit_id) : '';
							?>
							<tr>
								<td>
									<?php if ($order_id > 0 && function_exists('wc_get_order')) : ?>
										<a href="<?php echo esc_url(admin_url('post.php?post=' . $order_id . '&action=edit')); ?>">#<?php echo esc_html($order_number); ?></a>
									<?php else : ?>
										#<?php echo esc_html($order_number); ?>
									<?php endif; ?>
								</td>
								<td>
									<?php echo esc_html($name !== '' ? $name : __('Unknown customer', 'vms')); ?><br />
									<?php echo $email !== '' ? '<code>' . esc_html($email) . '</code>' : esc_html__('No billing email', 'vms'); ?>
								</td>
								<td><?php echo esc_html(vms_event_credit_format_money($amount, $currency)); ?></td>
								<td><?php echo esc_html(!empty($line_names) ? implode(' | ', $line_names) : __('Matched event items', 'vms')); ?></td>
								<td>
									<?php if ($existing_credit_id > 0) : ?>
										<?php if ($credit_url) : ?><a href="<?php echo esc_url($credit_url); ?>"><?php echo esc_html($existing_code); ?></a><?php else : echo esc_html($existing_code); endif; ?>
										<br /><span class="vms-event-credit-status vms-event-credit-status--<?php echo esc_attr($existing_status); ?>"><?php echo esc_html(ucwords(str_replace('_', ' ', $existing_status))); ?></span>
									<?php else : ?>
										<?php esc_html_e('Not issued', 'vms'); ?>
									<?php endif; ?>
								</td>
								<td>
									<?php if ($existing_credit_id > 0) : ?>
										<?php esc_html_e('Already created', 'vms'); ?>
									<?php elseif ($amount <= 0) : ?>
										<?php esc_html_e('No remaining amount', 'vms'); ?>
									<?php else : ?>
										<button type="submit" name="vms_event_credit_action" value="issue_only:<?php echo esc_attr((string) $order_id); ?>" class="button button-secondary">
											<?php esc_html_e('Create Credit Only', 'vms'); ?>
										</button>
										<button type="submit" name="vms_event_credit_action" value="issue_email:<?php echo esc_attr((string) $order_id); ?>" class="button button-primary">
											<?php esc_html_e('Issue + Email', 'vms'); ?>
										</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<p class="description"><?php esc_html_e('Event Credit coupons are one-use fixed-cart coupons and are restricted to eligible future event products. They are restricted to the original billing email by default when an email is available.', 'vms'); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}

add_filter('woocommerce_coupon_is_valid_for_product', 'vms_event_credit_coupon_is_valid_for_product', 10, 4);
if (!function_exists('vms_event_credit_coupon_is_valid_for_product')) {
	function vms_event_credit_coupon_is_valid_for_product($valid, $product, $coupon, $values)
	{
		if (!$coupon || !is_object($coupon) || !method_exists($coupon, 'get_id')) {
			return $valid;
		}
		$coupon_id = absint($coupon->get_id());
		$credit_id = $coupon_id > 0 ? absint(get_post_meta($coupon_id, '_vms_event_credit_id', true)) : 0;
		if ($credit_id <= 0) {
			return $valid;
		}

		$product_id = 0;
		if ($product && is_object($product) && method_exists($product, 'get_id')) {
			$product_id = absint($product->get_id());
		}
		if ($product_id <= 0 && is_array($values)) {
			$product_id = absint($values['product_id'] ?? 0);
		}
		return vms_event_credit_product_is_eligible($credit_id, $product_id);
	}
}

add_filter('woocommerce_coupon_is_valid_for_cart', 'vms_event_credit_coupon_is_valid_for_cart', 10, 2);
if (!function_exists('vms_event_credit_coupon_is_valid_for_cart')) {
	function vms_event_credit_coupon_is_valid_for_cart($valid, $coupon)
	{
		if (!$coupon || !is_object($coupon) || !method_exists($coupon, 'get_id')) {
			return $valid;
		}
		$coupon_id = absint($coupon->get_id());
		$credit_id = $coupon_id > 0 ? absint(get_post_meta($coupon_id, '_vms_event_credit_id', true)) : 0;
		if ($credit_id <= 0) {
			return $valid;
		}

		// Event Credits should only discount eligible products, never the whole cart.
		return false;
	}
}

add_filter('woocommerce_coupon_is_valid', 'vms_event_credit_coupon_is_valid', 10, 3);
if (!function_exists('vms_event_credit_coupon_is_valid')) {
	function vms_event_credit_coupon_is_valid($valid, $coupon, $discounts)
	{
		if (!$valid || !$coupon || !is_object($coupon) || !method_exists($coupon, 'get_id')) {
			return $valid;
		}
		$coupon_id = absint($coupon->get_id());
		$credit_id = $coupon_id > 0 ? absint(get_post_meta($coupon_id, '_vms_event_credit_id', true)) : 0;
		if ($credit_id <= 0) {
			return $valid;
		}
		if (!$discounts || !is_object($discounts) || !method_exists($discounts, 'get_items_to_validate')) {
			return $valid;
		}

		foreach ((array) $discounts->get_items_to_validate() as $item) {
			$product = is_object($item) && isset($item->product) ? $item->product : null;
			if (!$product || !is_object($product) || !method_exists($product, 'get_id')) {
				continue;
			}

			$product_ids = array(absint($product->get_id()));
			if (method_exists($product, 'get_parent_id')) {
				$product_ids[] = absint($product->get_parent_id());
			}

			foreach (array_unique(array_filter($product_ids)) as $product_id) {
				if (vms_event_credit_product_is_eligible($credit_id, $product_id)) {
					return true;
				}
			}
		}

		return false;
	}
}

if (!function_exists('vms_event_credit_product_is_eligible')) {
	function vms_event_credit_product_is_eligible(int $credit_id, int $product_id): bool
	{
		$credit_id = absint($credit_id);
		$product_id = absint($product_id);
		if ($credit_id <= 0 || $product_id <= 0) {
			return false;
		}

		$keys = vms_event_credit_meta_keys();
		$original_event_plan_id = absint(get_post_meta($credit_id, $keys['original_event_plan_id'], true));
		$product_plan_id = absint(get_post_meta($product_id, '_vms_event_plan_id', true));
		if ($product_plan_id <= 0) {
			$product_plan_id = absint(get_post_meta($product_id, 'vms_event_plan_id', true));
		}
		if ($product_plan_id > 0 && $original_event_plan_id > 0 && $product_plan_id === $original_event_plan_id) {
			return false;
		}

		$tec_event_id = absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true));
		if ($tec_event_id <= 0) {
			$tec_event_id = absint(get_post_meta($product_id, '_vms_tec_event_id', true));
		}
		if ($tec_event_id <= 0 && $product_plan_id <= 0) {
			$role = function_exists('vms_cancellation_refund_product_role') ? sanitize_key((string) vms_cancellation_refund_product_role($product_id)) : '';
			if ($role === '') {
				return false;
			}
		}

		if ($product_plan_id > 0) {
			$status_key = function_exists('vms_meta_key') ? (vms_meta_key('event_plan', 'status') ?: '_vms_event_plan_status') : '_vms_event_plan_status';
			$event_status = sanitize_key((string) get_post_meta($product_plan_id, $status_key, true));
			if ($event_status === 'cancelled') {
				return false;
			}
			$event_date = sanitize_text_field((string) get_post_meta($product_plan_id, '_vms_event_date', true));
			$today = function_exists('wp_date') ? wp_date('Y-m-d', time(), wp_timezone()) : date('Y-m-d');
			if ($event_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date) && $event_date < $today) {
				return false;
			}
		}

		return (bool) apply_filters('vms_event_credit_product_is_eligible', true, $credit_id, $product_id, $product_plan_id, $tec_event_id);
	}
}

add_action('woocommerce_order_status_changed', 'vms_event_credits_sync_redeemed_from_order', 20, 4);
if (!function_exists('vms_event_credits_sync_redeemed_from_order')) {
	function vms_event_credits_sync_redeemed_from_order(int $order_id, string $old_status, string $new_status, $order): void
	{
		if (!$order || !is_object($order) || !method_exists($order, 'get_coupon_codes')) {
			return;
		}
		$paid_statuses = function_exists('wc_get_is_paid_statuses') ? (array) wc_get_is_paid_statuses() : array('processing', 'completed');
		if (!in_array($new_status, $paid_statuses, true)) {
			return;
		}
		$codes = (array) $order->get_coupon_codes();
		foreach ($codes as $code) {
			$coupon_id = function_exists('wc_get_coupon_id_by_code') ? absint(wc_get_coupon_id_by_code((string) $code)) : 0;
			$credit_id = $coupon_id > 0 ? absint(get_post_meta($coupon_id, '_vms_event_credit_id', true)) : 0;
			if ($credit_id <= 0 || get_post_type($credit_id) !== VMS_CPT_EVENT_CREDIT) {
				continue;
			}
			$keys = vms_event_credit_meta_keys();
			$status = sanitize_key((string) get_post_meta($credit_id, $keys['status'], true));
			if ($status === 'redeemed') {
				continue;
			}
			update_post_meta($credit_id, $keys['status'], 'redeemed');
			update_post_meta($credit_id, $keys['redeemed_at_gmt'], gmdate('Y-m-d H:i:s'));
			update_post_meta($credit_id, $keys['redeemed_order_id'], absint($order_id));
		}
	}
}

add_action('add_meta_boxes_' . VMS_CPT_EVENT_CREDIT, 'vms_event_credit_add_meta_boxes');
if (!function_exists('vms_event_credit_add_meta_boxes')) {
	function vms_event_credit_add_meta_boxes(): void
	{
		add_meta_box('vms_event_credit_details', __('Event Credit Details', 'vms'), 'vms_event_credit_render_details_metabox', VMS_CPT_EVENT_CREDIT, 'normal', 'high');
	}
}

if (!function_exists('vms_event_credit_render_details_metabox')) {
	function vms_event_credit_render_details_metabox(WP_Post $post): void
	{
		wp_nonce_field('vms_save_event_credit', 'vms_event_credit_nonce');
		$keys = vms_event_credit_meta_keys();
		$status = sanitize_key((string) get_post_meta($post->ID, $keys['status'], true));
		if ($status === '') {
			$status = 'issued';
		}
		$code = sanitize_text_field((string) get_post_meta($post->ID, $keys['code'], true));
		$amount = (float) get_post_meta($post->ID, $keys['amount'], true);
		$currency = sanitize_text_field((string) get_post_meta($post->ID, $keys['currency'], true));
		$email = sanitize_email((string) get_post_meta($post->ID, $keys['customer_email'], true));
		$name = sanitize_text_field((string) get_post_meta($post->ID, $keys['customer_name'], true));
		$order_id = absint(get_post_meta($post->ID, $keys['original_order_id'], true));
		$event_plan_id = absint(get_post_meta($post->ID, $keys['original_event_plan_id'], true));
		$coupon_id = absint(get_post_meta($post->ID, $keys['coupon_id'], true));
		$expires_at_gmt = sanitize_text_field((string) get_post_meta($post->ID, $keys['expires_at_gmt'], true));
		$notes = (string) get_post_meta($post->ID, $keys['notes'], true);
		?>
		<table class="form-table vms-event-credit-details-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e('Code', 'vms'); ?></th>
				<td><code><?php echo esc_html($code); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('Status', 'vms'); ?></th>
				<td>
					<select name="vms_event_credit_status">
						<?php foreach (vms_event_credit_status_options() as $key => $label) : ?>
							<option value="<?php echo esc_attr((string) $key); ?>" <?php selected($status, (string) $key); ?>><?php echo esc_html((string) $label); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('Amount', 'vms'); ?></th>
				<td><?php echo esc_html(vms_event_credit_format_money($amount, $currency)); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('Customer', 'vms'); ?></th>
				<td><?php echo esc_html($name !== '' ? $name : __('Unknown', 'vms')); ?><?php echo $email !== '' ? '<br /><code>' . esc_html($email) . '</code>' : ''; ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('Original Event / Order', 'vms'); ?></th>
				<td>
					<?php if ($event_plan_id > 0) : ?><a href="<?php echo esc_url(get_edit_post_link($event_plan_id)); ?>"><?php echo esc_html(get_the_title($event_plan_id) ?: sprintf(__('Event Plan #%d', 'vms'), $event_plan_id)); ?></a><?php endif; ?>
					<?php if ($order_id > 0) : ?><br /><a href="<?php echo esc_url(admin_url('post.php?post=' . $order_id . '&action=edit')); ?>"><?php echo esc_html(sprintf(__('Order #%d', 'vms'), $order_id)); ?></a><?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('Coupon', 'vms'); ?></th>
				<td><?php echo $coupon_id > 0 ? '<a href="' . esc_url(admin_url('post.php?post=' . $coupon_id . '&action=edit')) . '">' . esc_html(sprintf(__('Coupon #%d', 'vms'), $coupon_id)) . '</a>' : esc_html__('No coupon created', 'vms'); ?></td>
			</tr>
			<tr>
				<th scope="row"><label for="vms_event_credit_expires_at_gmt"><?php esc_html_e('Expires at GMT', 'vms'); ?></label></th>
				<td><input type="text" class="regular-text" id="vms_event_credit_expires_at_gmt" name="vms_event_credit_expires_at_gmt" value="<?php echo esc_attr($expires_at_gmt); ?>" placeholder="YYYY-mm-dd HH:ii:ss" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="vms_event_credit_notes"><?php esc_html_e('Internal notes', 'vms'); ?></label></th>
				<td><textarea class="large-text" rows="4" id="vms_event_credit_notes" name="vms_event_credit_notes"><?php echo esc_textarea($notes); ?></textarea></td>
			</tr>
		</table>
		<?php
	}
}

add_action('save_post_' . VMS_CPT_EVENT_CREDIT, 'vms_event_credit_save_metabox', 10, 2);
if (!function_exists('vms_event_credit_save_metabox')) {
	function vms_event_credit_save_metabox(int $post_id, WP_Post $post): void
	{
		if (!isset($_POST['vms_event_credit_nonce']) || !wp_verify_nonce((string) $_POST['vms_event_credit_nonce'], 'vms_save_event_credit')) {
			return;
		}
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		if (!current_user_can('manage_options')) {
			return;
		}
		$keys = vms_event_credit_meta_keys();
		$status = isset($_POST['vms_event_credit_status']) ? sanitize_key((string) wp_unslash($_POST['vms_event_credit_status'])) : 'issued';
		$options = array_keys(vms_event_credit_status_options());
		if (!in_array($status, $options, true)) {
			$status = 'issued';
		}
		update_post_meta($post_id, $keys['status'], $status);

		$expires = isset($_POST['vms_event_credit_expires_at_gmt']) ? sanitize_text_field((string) wp_unslash($_POST['vms_event_credit_expires_at_gmt'])) : '';
		if ($expires !== '' && strtotime($expires . ' GMT') === false) {
			$expires = '';
		}
		if ($expires === '') {
			delete_post_meta($post_id, $keys['expires_at_gmt']);
		} else {
			update_post_meta($post_id, $keys['expires_at_gmt'], $expires);
		}

		$notes = isset($_POST['vms_event_credit_notes']) ? sanitize_textarea_field((string) wp_unslash($_POST['vms_event_credit_notes'])) : '';
		if ($notes === '') {
			delete_post_meta($post_id, $keys['notes']);
		} else {
			update_post_meta($post_id, $keys['notes'], $notes);
		}

		$coupon_id = absint(get_post_meta($post_id, $keys['coupon_id'], true));
		if ($coupon_id > 0 && get_post_type($coupon_id) === 'shop_coupon') {
			if ($status === 'voided') {
				wp_update_post(array('ID' => $coupon_id, 'post_status' => 'draft'));
			} elseif (in_array($status, array('issued', 'emailed'), true)) {
				wp_update_post(array('ID' => $coupon_id, 'post_status' => 'publish'));
			}
			$expires_ts = $expires !== '' ? strtotime($expires . ' GMT') : 0;
			if ($expires_ts > 0) {
				update_post_meta($coupon_id, 'date_expires', $expires_ts);
			}
		}
	}
}

add_filter('manage_' . VMS_CPT_EVENT_CREDIT . '_posts_columns', 'vms_event_credit_admin_columns');
if (!function_exists('vms_event_credit_admin_columns')) {
	function vms_event_credit_admin_columns(array $columns): array
	{
		return array(
			'cb' => $columns['cb'] ?? '<input type="checkbox" />',
			'title' => __('Credit', 'vms'),
			'vms_credit_status' => __('Status', 'vms'),
			'vms_credit_amount' => __('Amount', 'vms'),
			'vms_credit_customer' => __('Customer', 'vms'),
			'vms_credit_original' => __('Original Event / Order', 'vms'),
			'vms_credit_expires' => __('Expires', 'vms'),
		);
	}
}

add_action('manage_' . VMS_CPT_EVENT_CREDIT . '_posts_custom_column', 'vms_event_credit_admin_column_content', 10, 2);
if (!function_exists('vms_event_credit_admin_column_content')) {
	function vms_event_credit_admin_column_content(string $column, int $post_id): void
	{
		$keys = vms_event_credit_meta_keys();
		switch ($column) {
			case 'vms_credit_status':
				$status = sanitize_key((string) get_post_meta($post_id, $keys['status'], true));
				echo esc_html(ucwords(str_replace('_', ' ', $status ?: 'issued')));
				break;
			case 'vms_credit_amount':
				echo esc_html(vms_event_credit_format_money((float) get_post_meta($post_id, $keys['amount'], true), (string) get_post_meta($post_id, $keys['currency'], true)));
				break;
			case 'vms_credit_customer':
				$name = sanitize_text_field((string) get_post_meta($post_id, $keys['customer_name'], true));
				$email = sanitize_email((string) get_post_meta($post_id, $keys['customer_email'], true));
				echo esc_html($name !== '' ? $name : __('Unknown', 'vms'));
				if ($email !== '') {
					echo '<br /><code>' . esc_html($email) . '</code>';
				}
				break;
			case 'vms_credit_original':
				$event_plan_id = absint(get_post_meta($post_id, $keys['original_event_plan_id'], true));
				$order_id = absint(get_post_meta($post_id, $keys['original_order_id'], true));
				if ($event_plan_id > 0) {
					echo '<a href="' . esc_url(get_edit_post_link($event_plan_id)) . '">' . esc_html(get_the_title($event_plan_id) ?: ('#' . $event_plan_id)) . '</a>';
				}
				if ($order_id > 0) {
					echo '<br /><a href="' . esc_url(admin_url('post.php?post=' . $order_id . '&action=edit')) . '">' . esc_html(sprintf(__('Order #%d', 'vms'), $order_id)) . '</a>';
				}
				break;
			case 'vms_credit_expires':
				$expires = sanitize_text_field((string) get_post_meta($post_id, $keys['expires_at_gmt'], true));
				$ts = $expires !== '' ? strtotime($expires . ' GMT') : 0;
				echo $ts > 0 ? esc_html(wp_date('M j, Y', $ts, wp_timezone())) : esc_html__('No expiration', 'vms');
				break;
		}
	}
}
