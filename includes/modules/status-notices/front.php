<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_status_notice_admin_page_slug')) {
	function vms_status_notice_admin_page_slug(): string
	{
		return vms_request_read_key($_GET, 'page'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing here is read-only notice display context, not a mutation boundary.
	}
}

if (!function_exists('vms_status_notice_debug_requested')) {
	function vms_status_notice_debug_requested(): bool
	{
		return vms_request_read_bool_flag($_GET, 'vms_notice_debug'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The runtime debug flag only changes read-only notice display diagnostics for already-authorized viewers.
	}
}

if (!function_exists('vms_status_notice_runtime_context_front')) {
	function vms_status_notice_runtime_context_front(): array
	{
		$post_id = (int) get_queried_object_id();
		$post_type = '';
		if ($post_id > 0) {
			$post_type = (string) get_post_type($post_id);
		}

		$is_event_single = false;
		if (is_singular('tribe_events')) {
			$is_event_single = true;
		} elseif (function_exists('tribe_is_event') && function_exists('is_singular') && is_singular() && tribe_is_event()) {
			$is_event_single = true;
		}

		$is_woo_product = function_exists('is_product') ? (bool) is_product() : false;
		$is_woo_cart = function_exists('is_cart') ? (bool) is_cart() : false;
		$is_woo_checkout = function_exists('is_checkout') ? (bool) is_checkout() : false;
		$is_woo_account = function_exists('is_account_page') ? (bool) is_account_page() : false;
		$has_ticketing_hint = ($is_event_single || $is_woo_product || $is_woo_cart || $is_woo_checkout) ? 1 : 0;

		$request_uri = vms_request_current_uri('/');

		$roles = array();
		if (is_user_logged_in()) {
			$user = wp_get_current_user();
			if ($user instanceof WP_User) {
				$roles = array_values(array_map('sanitize_key', (array) $user->roles));
			}
		}

		$can_debug = current_user_can(vms_status_notices_capability()) ? 1 : 0;
		$debug_enabled = ($can_debug && vms_status_notice_debug_requested()) ? 1 : 0;

		return array(
			'is_admin' => 0,
			'page_slug' => '',
			'is_event_single' => $is_event_single ? 1 : 0,
			'is_woo_product' => $is_woo_product ? 1 : 0,
			'is_woo_cart' => $is_woo_cart ? 1 : 0,
			'is_woo_checkout' => $is_woo_checkout ? 1 : 0,
			'is_woo_account' => $is_woo_account ? 1 : 0,
			'post_type' => sanitize_key($post_type),
			'page_id' => $post_id,
			'has_vms_ticketing_wrapper' => $has_ticketing_hint,
			'request_uri' => $request_uri,
			'is_logged_in' => is_user_logged_in() ? 1 : 0,
			'roles' => $roles,
			'can_debug' => $can_debug,
			'debug_enabled' => $debug_enabled,
		);
	}
}

if (!function_exists('vms_status_notice_runtime_context_admin')) {
	function vms_status_notice_runtime_context_admin(): array
	{
		$page = vms_status_notice_admin_page_slug();
		$roles = array();
		$user = wp_get_current_user();
		if ($user instanceof WP_User) {
			$roles = array_values(array_map('sanitize_key', (array) $user->roles));
		}
		$can_debug = current_user_can(vms_status_notices_capability()) ? 1 : 0;
		$debug_enabled = ($can_debug && vms_status_notice_debug_requested()) ? 1 : 0;

		return array(
			'is_admin' => 1,
			'page_slug' => $page,
			'is_event_single' => 0,
			'is_woo_product' => 0,
			'is_woo_cart' => 0,
			'is_woo_checkout' => 0,
			'is_woo_account' => 0,
			'post_type' => '',
			'page_id' => 0,
			'has_vms_ticketing_wrapper' => 0,
			'request_uri' => vms_request_current_uri('/wp-admin/'),
			'is_logged_in' => is_user_logged_in() ? 1 : 0,
			'roles' => $roles,
			'can_debug' => $can_debug,
			'debug_enabled' => $debug_enabled,
		);
	}
}

if (!function_exists('vms_status_notice_prepare_runtime_notice')) {
	function vms_status_notice_prepare_runtime_notice(array $notice): array
	{
		$out = $notice;
		$out['id'] = (int) ($notice['id'] ?? 0);
		$out['enabled'] = !empty($notice['enabled']) ? 1 : 0;
		$out['dismissible'] = !empty($notice['dismissible']) ? 1 : 0;
		$out['priority'] = (int) ($notice['priority'] ?? 0);
		$out['updated_at'] = (int) ($notice['updated_at'] ?? 0);
		$out['intensity'] = (int) ($notice['intensity'] ?? 2);
		$out['trigger_delay_ms'] = (int) ($notice['trigger_delay_ms'] ?? 0);
		$out['start_ts'] = (int) ($notice['start_ts'] ?? 0);
		$out['end_ts'] = (int) ($notice['end_ts'] ?? 0);
		$out['include_page_types'] = array_values(array_map('sanitize_key', (array) ($notice['include_page_types'] ?? array())));
		$out['include_object_ids'] = array_values(array_map('absint', (array) ($notice['include_object_ids'] ?? array())));
		$out['exclude_object_ids'] = array_values(array_map('absint', (array) ($notice['exclude_object_ids'] ?? array())));
		$out['url_contains'] = array_values(array_map('strval', (array) ($notice['url_contains'] ?? array())));
		$out['url_excludes'] = array_values(array_map('strval', (array) ($notice['url_excludes'] ?? array())));
		$out['roles_include'] = array_values(array_map('sanitize_key', (array) ($notice['roles_include'] ?? array())));
		$out['roles_exclude'] = array_values(array_map('sanitize_key', (array) ($notice['roles_exclude'] ?? array())));
		$out['browser_include'] = array_values(array_map('sanitize_key', (array) ($notice['browser_include'] ?? array())));
		$out['os_include'] = array_values(array_map('sanitize_key', (array) ($notice['os_include'] ?? array())));
		$out['body_html'] = wp_kses_post((string) ($notice['body_html'] ?? ''));
		$out['headline'] = sanitize_text_field((string) ($notice['headline'] ?? ''));
		$out['primary_btn_label'] = sanitize_text_field((string) ($notice['primary_btn_label'] ?? ''));
		$out['primary_btn_url'] = esc_url_raw((string) ($notice['primary_btn_url'] ?? ''));
		$out['secondary_btn_label'] = sanitize_text_field((string) ($notice['secondary_btn_label'] ?? ''));
		$out['secondary_btn_url'] = esc_url_raw((string) ($notice['secondary_btn_url'] ?? ''));

		$user_ids_include = array_values(array_map('absint', (array) ($notice['user_ids_include'] ?? array())));
		if (!empty($user_ids_include)) {
			$out['current_user_match_include'] = (is_user_logged_in() && in_array(get_current_user_id(), $user_ids_include, true)) ? 1 : 0;
			$out['user_ids_include'] = array();
		} else {
			$out['current_user_match_include'] = 1;
			$out['user_ids_include'] = array();
		}

		return $out;
	}
}

if (!function_exists('vms_status_notice_enqueue_runtime_assets')) {
	function vms_status_notice_enqueue_runtime_assets(array $notices, array $context, string $script_handle): void
	{
		if (empty($notices)) {
			return;
		}

		$ver = defined('VMS_VERSION') ? VMS_VERSION : null;
		wp_enqueue_style(
			'vms-notices-front',
			VMS_PLUGIN_URL . 'assets/css/vms-notices-front.css',
			array('vms-ui'),
			$ver
		);

		wp_enqueue_script(
			$script_handle,
			VMS_PLUGIN_URL . 'assets/js/vms-notices-front.js',
			array(),
			$ver,
			true
		);

		wp_localize_script($script_handle, 'vmsStatusNoticesData', array(
			'notices' => array_values(array_map('vms_status_notice_prepare_runtime_notice', $notices)),
			'context' => $context,
		));
	}
}

if (!function_exists('vms_status_notice_maybe_enqueue_front')) {
	function vms_status_notice_maybe_enqueue_front(): void
	{
		if (is_admin()) {
			return;
		}
		$notices = vms_status_notice_enabled_for_scope('front');
		if (empty($notices)) {
			return;
		}
		vms_status_notice_enqueue_runtime_assets($notices, vms_status_notice_runtime_context_front(), 'vms-notices-front-runtime');
	}
}
add_action('wp_enqueue_scripts', 'vms_status_notice_maybe_enqueue_front', 45);

if (!function_exists('vms_status_notice_maybe_enqueue_admin_runtime')) {
	function vms_status_notice_maybe_enqueue_admin_runtime(): void
	{
		if (!is_admin() || !current_user_can(vms_status_notices_capability())) {
			return;
		}
		$notices = vms_status_notice_enabled_for_scope('admin');
		if (empty($notices)) {
			return;
		}
		vms_status_notice_enqueue_runtime_assets($notices, vms_status_notice_runtime_context_admin(), 'vms-notices-admin-runtime');
	}
}
add_action('admin_enqueue_scripts', 'vms_status_notice_maybe_enqueue_admin_runtime', 55);
