<?php
defined('ABSPATH') || exit;

require_once VMS_DT_INCLUDES_DIR . 'helpers.php';
require_once VMS_DT_INCLUDES_DIR . 'deps.php';
require_once VMS_DT_INCLUDES_DIR . 'capabilities.php';
require_once VMS_DT_INCLUDES_DIR . 'integrations/bvm-reporting-provider.php';

if (!function_exists('vms_dt_bootstrap_trace')) {
	function vms_dt_bootstrap_trace(string $module, string $decision, array $context = array()): void
	{
		$parts = array(
			'module=' . $module,
			'decision=' . $decision,
		);

		foreach ($context as $key => $value) {
			if (is_array($value) || is_object($value)) {
				$value = wp_json_encode($value);
			}
			$parts[] = sanitize_key((string) $key) . '=' . str_replace(array("\r", "\n"), ' ', (string) $value);
		}

		$parts[] = 'memory=' . size_format(memory_get_usage(true), 2);

		error_log('[VMS DT TRACE] bootstrap ' . implode(' ', $parts));
	}
}

if (!function_exists('vms_dt_bootstrap_failure_store')) {
	function vms_dt_bootstrap_failure_store(string $module, Throwable $error): void
	{
		$failures = isset($GLOBALS['vms_dt_bootstrap_failures']) && is_array($GLOBALS['vms_dt_bootstrap_failures'])
			? $GLOBALS['vms_dt_bootstrap_failures']
			: array();

		$failures[] = array(
			'module' => $module,
			'message' => $error->getMessage(),
		);

		$GLOBALS['vms_dt_bootstrap_failures'] = $failures;
	}
}

if (!function_exists('vms_dt_render_bootstrap_failures')) {
	function vms_dt_render_bootstrap_failures(): void
	{
		if (!is_admin() || !current_user_can('activate_plugins')) {
			return;
		}

		$failures = isset($GLOBALS['vms_dt_bootstrap_failures']) && is_array($GLOBALS['vms_dt_bootstrap_failures'])
			? $GLOBALS['vms_dt_bootstrap_failures']
			: array();

		if (empty($failures)) {
			return;
		}

		foreach ($failures as $failure) {
			$module = isset($failure['module']) ? (string) $failure['module'] : 'unknown';
			$message = isset($failure['message']) ? (string) $failure['message'] : 'Unknown bootstrap error.';
			echo '<div class="notice notice-error"><p><strong>VMS Data Tools</strong> could not load module <code>' . esc_html($module) . '</code>: ' . esc_html($message) . '</p></div>';
		}
	}

	add_action('admin_notices', 'vms_dt_render_bootstrap_failures');
}

if (!function_exists('vms_dt_require_runtime_module')) {
	function vms_dt_require_runtime_module(string $module, string $path): bool
	{
		static $loaded = array();

		if (isset($loaded[$module])) {
			return $loaded[$module];
		}

		$start = microtime(true);

		try {
			require_once $path;
			$loaded[$module] = true;
			vms_dt_bootstrap_trace(
				$module,
				'loaded',
				array(
					'elapsed_ms' => round((microtime(true) - $start) * 1000, 2),
				)
			);
			return true;
		} catch (Throwable $error) {
			$loaded[$module] = false;
			vms_dt_bootstrap_failure_store($module, $error);
			vms_dt_bootstrap_trace(
				$module,
				'failed',
				array(
					'elapsed_ms' => round((microtime(true) - $start) * 1000, 2),
					'error' => $error->getMessage(),
				)
			);
			return false;
		}
	}
}

if (!function_exists('vms_dt_is_rest_request')) {
	function vms_dt_is_rest_request(): bool
	{
		return defined('REST_REQUEST') && REST_REQUEST;
	}
}

if (!function_exists('vms_dt_request_path')) {
	function vms_dt_request_path(): string
	{
		$uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
		if ($uri === '') {
			return '';
		}

		$path = wp_parse_url($uri, PHP_URL_PATH);
		return is_string($path) ? strtolower($path) : '';
	}
}

if (!function_exists('vms_dt_current_admin_page_slug')) {
	function vms_dt_current_admin_page_slug(): string
	{
		$page = isset($_REQUEST['page']) ? (string) wp_unslash($_REQUEST['page']) : '';
		return sanitize_key($page);
	}
}

if (!function_exists('vms_dt_request_post_type')) {
	function vms_dt_request_post_type(): string
	{
		$post_type = isset($_REQUEST['post_type']) ? sanitize_key((string) wp_unslash($_REQUEST['post_type'])) : '';
		if ($post_type !== '') {
			return $post_type;
		}

		$post_id = 0;
		if (isset($_REQUEST['post'])) {
			$post_id = absint($_REQUEST['post']);
		} elseif (isset($_REQUEST['post_ID'])) {
			$post_id = absint($_REQUEST['post_ID']);
		}

		if ($post_id <= 0) {
			return '';
		}

		$resolved = get_post_type($post_id);
		return is_string($resolved) ? sanitize_key($resolved) : '';
	}
}

if (!function_exists('vms_dt_data_tools_page_slugs')) {
	function vms_dt_data_tools_page_slugs(): array
	{
		return array(
			vms_dt_get_menu_slug_data_tools(),
			vms_dt_get_menu_slug_events_import(),
			vms_dt_get_menu_slug_vendor_import(),
			vms_dt_get_menu_slug_vendor_invites(),
			vms_dt_get_menu_slug_holidays_import(),
			vms_dt_get_menu_slug_payables_export(),
			vms_dt_get_menu_slug_ticket_revenue_export(),
			vms_dt_get_menu_slug_square_ticket_merge(),
			vms_dt_get_menu_slug_revenue_intelligence(),
			vms_dt_get_menu_slug_reporting_single_event(),
			vms_dt_get_menu_slug_reporting_compare_events(),
			vms_dt_get_menu_slug_reporting_season_year(),
			vms_dt_get_menu_slug_reporting_performer_payouts(),
			vms_dt_get_menu_slug_reporting_profitability(),
			vms_dt_get_menu_slug_reporting_ticket_pace(),
			'vms-square-sync',
		);
	}
}

if (!function_exists('vms_dt_is_data_tools_admin_page_request')) {
	function vms_dt_is_data_tools_admin_page_request(): bool
	{
		if (!is_admin()) {
			return false;
		}

		$page = vms_dt_current_admin_page_slug();
		if ($page === '') {
			return false;
		}

		return in_array($page, vms_dt_data_tools_page_slugs(), true);
	}
}

if (!function_exists('vms_dt_is_data_tools_rest_request')) {
	function vms_dt_is_data_tools_rest_request(): bool
	{
		if (!vms_dt_is_rest_request()) {
			return false;
		}

		$uri = isset($_SERVER['REQUEST_URI']) ? strtolower((string) wp_unslash($_SERVER['REQUEST_URI'])) : '';
		if ($uri === '') {
			return false;
		}

		return strpos($uri, '/wp-json/' . strtolower(trim(vms_dt_rest_namespace(), '/'))) !== false;
	}
}

if (!function_exists('vms_dt_is_vendor_portal_request')) {
	function vms_dt_is_vendor_portal_request(): bool
	{
		$path = vms_dt_request_path();
		if ($path !== '') {
			foreach (array('/vendor-portal', '/vendor-claim') as $fragment) {
				if (strpos($path, $fragment) !== false) {
					return true;
				}
			}
		}

		if (isset($_REQUEST['vms_vendor_claim']) && (string) wp_unslash($_REQUEST['vms_vendor_claim']) === '1') {
			return true;
		}

		return !empty($_REQUEST['vms_preview_vendor']);
	}
}

if (!function_exists('vms_dt_is_vendor_edit_request')) {
	function vms_dt_is_vendor_edit_request(): bool
	{
		return is_admin() && vms_dt_request_post_type() === 'vms_vendor';
	}
}

if (!function_exists('vms_dt_is_event_plan_request')) {
	function vms_dt_is_event_plan_request(): bool
	{
		if (is_admin() && vms_dt_request_post_type() === 'vms_event_plan') {
			return true;
		}

		$action = isset($_REQUEST['action']) ? sanitize_key((string) wp_unslash($_REQUEST['action'])) : '';
		return $action === 'vms_square_snapshot_event_actuals';
	}
}

if (!function_exists('vms_dt_should_load_vendor_invites')) {
	function vms_dt_should_load_vendor_invites(): bool
	{
		return vms_dt_is_data_tools_admin_page_request()
			|| vms_dt_is_vendor_edit_request()
			|| vms_dt_is_vendor_portal_request();
	}
}

if (!function_exists('vms_dt_should_load_import_engines')) {
	function vms_dt_should_load_import_engines(): bool
	{
		return vms_dt_is_data_tools_admin_page_request()
			|| vms_dt_is_data_tools_rest_request();
	}
}

if (!function_exists('vms_dt_should_load_square_sync')) {
	function vms_dt_should_load_square_sync(): bool
	{
		return vms_dt_is_data_tools_admin_page_request()
			|| vms_dt_is_event_plan_request()
			|| (function_exists('wp_doing_cron') && wp_doing_cron());
	}
}

if (!function_exists('vms_dt_load_runtime_modules')) {
	function vms_dt_load_runtime_modules(): void
	{
		if (vms_dt_should_load_vendor_invites()) {
			vms_dt_require_runtime_module('vendor_invites', VMS_DT_INCLUDES_DIR . 'vendor-invites/load.php');
		}

		if (vms_dt_should_load_import_engines()) {
			vms_dt_require_runtime_module('events_import_engine', VMS_DT_SERVICES_DIR . 'events-import/events-import-engine.php');
			vms_dt_require_runtime_module('vendor_import_engine', VMS_DT_SERVICES_DIR . 'vendor-import/vendor-import-engine.php');
		}

		if (vms_dt_should_load_square_sync()) {
			vms_dt_require_runtime_module('square_sync', VMS_DT_INCLUDES_DIR . 'integrations/square/vms-square-sync.php');
		}
	}
}

function vms_dt_activate(): void
{
	if (vms_dt_has_core_function('vms_resource_fingerprint_flag')) {
		vms_dt_call_core_function('vms_resource_fingerprint_flag', 'plugin_activation', 'vms-data-tools/vms-data-tools.php');
	}

	// Ensure roles/caps are registered even if init hasn't run yet.
	vms_dt_register_capabilities();
	vms_dt_grant_default_capabilities();
}

function vms_dt_deactivate(): void
{
	if (vms_dt_has_core_function('vms_resource_fingerprint_flag')) {
		vms_dt_call_core_function('vms_resource_fingerprint_flag', 'plugin_deactivation', 'vms-data-tools/vms-data-tools.php');
	}

	// Keep capabilities by default (non-destructive).
	// If you ever want to remove caps on deactivate, do it intentionally and with care.
}

function vms_dt_init(): void
{
	// Capabilities should exist even if the plugin was installed before this code existed.
	vms_dt_register_capabilities();

	// Dependency guard: don’t crash admin, but don’t run features either.
	if (!vms_dt_is_vms_core_active()) {
		add_action('admin_notices', 'vms_dt_admin_notice_missing_core');
		return;
	}

	vms_dt_load_runtime_modules();

	// Admin + REST.
	if (is_admin()) {
		// Admin page callbacks (render + register functions)
		require_once VMS_DT_ADMIN_DIR . 'page-events-import.php';
		require_once VMS_DT_ADMIN_DIR . 'page-vendor-import.php';
		require_once VMS_DT_ADMIN_DIR . 'page-holidays-import.php';
        require_once VMS_DT_ADMIN_DIR . 'page-payables-export.php';
        require_once VMS_DT_ADMIN_DIR . 'page-ticket-revenue-export.php';
        require_once VMS_DT_ADMIN_DIR . 'page-square-ticket-merge.php';
        require_once VMS_DT_ADMIN_DIR . 'page-revenue-intelligence.php';
        require_once VMS_DT_ADMIN_DIR . 'page-reporting-module.php';

		// Admin menu registry (single source of truth)
		require_once VMS_DT_ADMIN_DIR . 'menu.php';

		// Other admin-only components
		require_once VMS_DT_ADMIN_DIR . 'assets.php';
		require_once VMS_DT_ADMIN_DIR . 'downloads.php';
	}

	require_once VMS_DT_INCLUDES_DIR . 'rest/routes.php';
	require_once VMS_DT_INCLUDES_DIR . 'docs-register.php';
}
