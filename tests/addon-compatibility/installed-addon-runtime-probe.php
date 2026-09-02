<?php

if (!defined('ABSPATH')) {
	fwrite(STDERR, "Run this probe through a real WordPress bootstrap.\n");
	exit(2);
}

$scenario = isset($args[0]) ? (string) $args[0] : 'unknown';
$expectedOpsVersion = getenv('BVM_SWEEP_EXPECTED_OPS_VERSION') ?: '0.1.65.1';
$expectedAgreementsVersion = getenv('BVM_SWEEP_EXPECTED_AGREEMENTS_VERSION') ?: '0.3.48';
$checks = array();
$runtimeErrors = array();

$check = static function (string $id, bool $passed, array $details = array()) use (&$checks): void {
	$checks[] = array('id' => $id, 'passed' => $passed, 'details' => $details);
};

set_error_handler(static function (int $severity, string $message, string $file, int $line) use (&$runtimeErrors): bool {
	$runtimeErrors[] = array(
		'severity' => $severity,
		'message' => $message,
		'file' => basename($file),
		'line' => $line,
	);
	return false;
});

if (!function_exists('set_current_screen')) {
	require_once ABSPATH . 'wp-admin/includes/screen.php';
}
if (!function_exists('add_menu_page')) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

wp_set_current_user(1);
set_current_screen('dashboard');
add_filter('woocommerce_prevent_automatic_wizard_redirect', '__return_true', PHP_INT_MAX);

$GLOBALS['menu'] = array();
$GLOBALS['submenu'] = array();
$GLOBALS['admin_page_hooks'] = array();
$GLOBALS['_registered_pages'] = array();
$GLOBALS['_parent_pages'] = array();

$lifecycleException = null;
try {
	if (did_action('admin_init') === 0) {
		do_action('admin_init');
	}
	do_action('admin_menu', '');
	if (did_action('rest_api_init') === 0) {
		do_action('rest_api_init', rest_get_server());
	}
} catch (Throwable $throwable) {
	$lifecycleException = array('class' => get_class($throwable), 'message' => $throwable->getMessage());
}
$check('admin-and-rest-lifecycle', $lifecycleException === null, $lifecycleException ?? array());

$activePlugins = array_values((array) get_option('active_plugins', array()));
$check('public-bvm-identity', defined('BVMGR_VERSION') && (string) BVMGR_VERSION === '1.2.0' && function_exists('bvmgr_core') && in_array('backstage-venue-manager/backstage-venue-manager.php', $activePlugins, true));
$check('legacy-vms-runtime-absent', !defined('VMS_VERSION') && !function_exists('vms_core') && !in_array('vms/vendor-management-system.php', $activePlugins, true));

$allMenuSlugs = array();
foreach ((array) $GLOBALS['menu'] as $row) {
	if (is_array($row) && isset($row[2])) {
		$allMenuSlugs[] = (string) $row[2];
	}
}
foreach ((array) $GLOBALS['submenu'] as $rows) {
	foreach ((array) $rows as $row) {
		if (is_array($row) && isset($row[2])) {
			$allMenuSlugs[] = (string) $row[2];
		}
	}
}
$allMenuSlugs = array_values(array_unique($allMenuSlugs));

$registry = function_exists('bvmgr_admin_menu_registry') ? bvmgr_admin_menu_registry() : array();
$routes = rest_get_server()->get_routes();
$routeCount = static function (string $prefix) use ($routes): int {
	$count = 0;
	foreach (array_keys($routes) as $route) {
		if (strpos((string) $route, $prefix) === 0) {
			$count++;
		}
	}
	return $count;
};

$check('mab-version', defined('VMS_MA_VERSION') && (string) VMS_MA_VERSION === '0.1.105.1');
$check('mab-public-module-gate', class_exists('VMS_Meta_Ads') && VMS_Meta_Ads::module_gate_status() === 'enabled' && bvmgr_module_is_registered('meta_ads_builder') && bvmgr_module_is_enabled('meta_ads_builder'));
$mabMenus = array('vms-ma-ads-builder', 'vms-ma-ads-promote', 'vms-ma-ads-performance', 'vms-ma-ads-logs', 'vms-ma-ads-settings');
$check('mab-menus', array_diff($mabMenus, $allMenuSlugs) === array(), array('missing' => array_values(array_diff($mabMenus, $allMenuSlugs))));
$check('mab-rest-and-cron', $routeCount('/vms-ma/v1') > 0 && has_action('vms_ma_budget_sync_cron', array('VMS_Meta_Ads', 'run_budget_sync_cron')) !== false, array('route_count' => $routeCount('/vms-ma/v1')));

$check('data-tools-version-and-dependency', defined('VMS_DT_VERSION') && (string) VMS_DT_VERSION === '0.5.54' && function_exists('vms_dt_is_vms_core_active') && vms_dt_is_vms_core_active());
$check('data-tools-public-resolution', vms_dt_core_function('vms_core') === 'bvmgr_core' && vms_dt_core_class('VMS_Vendor_Schema_Registry') === 'BVMGR_Vendor_Schema_Registry');
$check('data-tools-runtime', in_array('vms-data-tools', $allMenuSlugs, true) && function_exists('vms_dt_reporting_build_event_model') && $routeCount('/vms-data-tools/v1') > 0, array('route_count' => $routeCount('/vms-data-tools/v1')));

$check('events-slider-runtime', defined('VMS_EVENTS_SLIDER_VERSION') && (string) VMS_EVENTS_SLIDER_VERSION === '1.0.10' && shortcode_exists('vms_events_slider') && shortcode_exists('serenade_events_slider') && vms_events_slider_core_function('vms_event_plan_get_status') === 'bvmgr_event_plan_get_status');

$check('raf-version-and-resolution', defined('VMS_RAF_VERSION') && (string) VMS_RAF_VERSION === '0.2.6' && vms_raf_core_function('vms_register_admin_page') === 'bvmgr_register_admin_page' && vms_raf_core_function('vms_admin_ui_render_shell') === 'bvmgr_admin_ui_render_shell');
$check('raf-registry', isset($registry['vms-raf']), array('registry_has_page' => isset($registry['vms-raf'])));

$check('investor-version-and-resolution', defined('VMS_INVESTOR_PORTAL_VERSION') && (string) VMS_INVESTOR_PORTAL_VERSION === '0.2.3' && vms_investor_core_function('vms_event_command_center_get_ticket_reporting_truth') === 'bvmgr_event_command_center_get_ticket_reporting_truth');
$check('investor-registry', isset($registry['vms-investor-portal']), array('registry_has_page' => isset($registry['vms-investor-portal'])));

$check('ops-version-and-resolution', defined('VMS_OPS_CONSOLE_VERSION') && (string) VMS_OPS_CONSOLE_VERSION === $expectedOpsVersion && vms_ops_core_function('vms_get_event_plan_for_tec_event') === 'bvmgr_get_event_plan_for_tec_event');
$check('ops-runtime', $routeCount('/vms-ops/v1') > 0 && in_array('vms-ops-console-members', $allMenuSlugs, true), array('route_count' => $routeCount('/vms-ops/v1')));

$agreementTabs = function_exists('bvmgr_vendor_portal_allowed_tabs') ? bvmgr_vendor_portal_allowed_tabs() : array();
$check(
	'agreements-regression',
	defined('VMSA_VERSION')
		&& (string) VMSA_VERSION === $expectedAgreementsVersion
		&& in_array('vms-agreements', $allMenuSlugs, true)
		&& has_filter('vms_vendor_portal_allowed_tabs', 'vmsa_register_vendor_portal_tab') !== false
		&& in_array('agreements', $agreementTabs, true),
	array('allowed_tabs' => $agreementTabs)
);
$check('express-bar-regression', defined('VMSEB_VERSION') && (string) VMSEB_VERSION === '0.6.24' && in_array('vms-express-bar', $allMenuSlugs, true) && in_array('vms-bar-menu', $allMenuSlugs, true));
$check('sponsorships-standalone-regression', defined('VMS_SPONSORSHIPS_VERSION') && (string) VMS_SPONSORSHIPS_VERSION === '0.1.7.1' && in_array('vms-sponsorships', $allMenuSlugs, true) && shortcode_exists('vms_sponsor_inquiry'));
$check('event-venue-map-regression', class_exists('Event_Venue_Map_Modal') && (string) Event_Venue_Map_Modal::VERSION === '1.2.4' && has_filter('the_content') !== false && has_action('wp_footer') !== false);

ob_start();
try {
	do_action('admin_notices');
} catch (Throwable $throwable) {
	$lifecycleException = array('class' => get_class($throwable), 'message' => $throwable->getMessage());
}
$notices = preg_replace('/\s+/', ' ', wp_strip_all_tags((string) ob_get_clean())) ?: '';
$falseNoticePhrases = array(
	'VMS core module system is unavailable',
	'Reactivate VMS core before using MAB',
	'VMS Core is not detected',
	'Activate VMS Core to enable Data Tools',
);
$presentFalseNotices = array_values(array_filter($falseNoticePhrases, static fn(string $phrase): bool => stripos($notices, $phrase) !== false));
$check('false-legacy-notices-absent', $presentFalseNotices === array(), array('present' => $presentFalseNotices));

$relevantRuntimeErrors = array_values(array_filter($runtimeErrors, static function (array $error): bool {
	$ignored = array(
		'Function _load_textdomain_just_in_time was called',
		'wp_version_check(): An unexpected error occurred',
		'wp_update_plugins(): An unexpected error occurred',
		'wp_update_themes(): An unexpected error occurred',
	);
	foreach ($ignored as $fragment) {
		if (strpos($error['message'], $fragment) !== false) {
			return false;
		}
	}
	return true;
}));
$check('no-new-runtime-errors', $relevantRuntimeErrors === array(), array('errors' => $relevantRuntimeErrors));

$failed = array_values(array_filter($checks, static fn(array $row): bool => !$row['passed']));
echo wp_json_encode(array(
	'scenario' => $scenario,
	'active_plugins' => $activePlugins,
	'checks' => $checks,
	'failed_count' => count($failed),
	'status' => $failed === array() ? 'PASS' : 'FAIL',
), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($failed === array() ? 0 : 1);
