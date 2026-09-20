<?php
declare(strict_types=1);

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

$GLOBALS['vms_identity_test_options'] = array(
	'active_plugins' => array(),
	'active_sitewide_plugins' => array(),
);
$GLOBALS['vms_identity_test_updates'] = array();
$GLOBALS['vms_identity_test_multisite'] = false;
$GLOBALS['vms_identity_test_actions'] = array();

if (!function_exists('plugin_basename')) {
	function plugin_basename($plugin_file): string
	{
		$plugin_file = trim(str_replace('\\', '/', (string) $plugin_file), '/');
		$parts = explode('/', $plugin_file);
		return implode('/', array_slice($parts, -2));
	}
}

if (!function_exists('get_option')) {
	function get_option($option, $default = false)
	{
		return $GLOBALS['vms_identity_test_options'][(string) $option] ?? $default;
	}
}

if (!function_exists('update_option')) {
	function update_option($option, $value): bool
	{
		$GLOBALS['vms_identity_test_options'][(string) $option] = $value;
		$GLOBALS['vms_identity_test_updates'][] = (string) $option;
		return true;
	}
}

if (!function_exists('is_multisite')) {
	function is_multisite(): bool
	{
		return !empty($GLOBALS['vms_identity_test_multisite']);
	}
}

if (!function_exists('get_site_option')) {
	function get_site_option($option, $default = false)
	{
		return $GLOBALS['vms_identity_test_options'][(string) $option] ?? $default;
	}
}

if (!function_exists('update_site_option')) {
	function update_site_option($option, $value): bool
	{
		$GLOBALS['vms_identity_test_options'][(string) $option] = $value;
		$GLOBALS['vms_identity_test_updates'][] = (string) $option;
		return true;
	}
}

if (!function_exists('add_action')) {
	function add_action($hook, $callback, $priority = 10, $accepted_args = 1): bool
	{
		$GLOBALS['vms_identity_test_actions'][] = array((string) $hook, $callback, (int) $priority, (int) $accepted_args);
		return true;
	}
}

$plugin_root = dirname(__DIR__);
require_once $plugin_root . '/includes/plugin-basename-compat.php';

$canonical_file = $plugin_root . '/backstage-venue-manager.php';
$legacy_file = $plugin_root . '/vendor-management-system.php';
$canonical_source = (string) file_get_contents($canonical_file);
$legacy_source = (string) file_get_contents($legacy_file);
$vms_shim_source = (string) file_get_contents($plugin_root . '/vms.php');

$assert(is_file($canonical_file), 'Canonical backstage-venue-manager.php bootstrap must exist.');
$assert(strpos($canonical_source, 'Plugin Name: Backstage Venue Manager') !== false, 'Canonical bootstrap must expose the Backstage Venue Manager plugin header.');
$assert(strpos($canonical_source, 'Text Domain: backstage-venue-manager') !== false, 'Canonical bootstrap must retain the canonical text domain.');
$assert(preg_match('/^\s*\*\s*Plugin Name:/m', $legacy_source) !== 1, 'Legacy filename bridge must not expose a second plugin header.');
$assert(strpos($legacy_source, "__DIR__ . '/backstage-venue-manager.php'") !== false, 'Legacy filename bridge must delegate to the canonical bootstrap.');
$assert(strpos($legacy_source, "register_activation_hook(__FILE__, 'bvmgr_activate_plugin')") !== false, 'Legacy filename bridge must preserve activation behavior during an old-basename activation.');
$assert(strpos($legacy_source, "register_deactivation_hook(__FILE__, 'bvmgr_deactivate_plugin')") !== false, 'Legacy filename bridge must preserve deactivation behavior during an old-basename deactivation.');
$assert(strpos($vms_shim_source, "require_once __DIR__ . '/backstage-venue-manager.php';") !== false, 'Legacy vms.php bridge must delegate to the canonical bootstrap.');

$root_plugin_headers = array();
foreach ((array) glob($plugin_root . '/*.php') as $root_php_file) {
	$source = (string) file_get_contents($root_php_file);
	if (preg_match('/^\s*\*\s*Plugin Name:/m', $source) === 1) {
		$root_plugin_headers[] = basename($root_php_file);
	}
}
$assert($root_plugin_headers === array('backstage-venue-manager.php'), 'The package source must expose exactly one root plugin header in backstage-venue-manager.php.');

$menu_source = (string) file_get_contents($plugin_root . '/includes/admin/menu.php');
$approvals_source = (string) file_get_contents($plugin_root . '/includes/admin/approvals-review-queue.php');
$settings_source = (string) file_get_contents($plugin_root . '/includes/admin/settings-page.php');
$nav_source = (string) file_get_contents($plugin_root . '/includes/admin-ui/nav.php');
$readme_source = (string) file_get_contents($plugin_root . '/readme.txt');
$assert(strpos($menu_source, "__('Backstage Venue Manager', 'backstage-venue-manager')") !== false, 'Top-level admin menu must use the canonical public product name.');
$assert(strpos($menu_source, 'Welcome to the Backstage Venue Manager dashboard') !== false, 'Dashboard welcome copy must use the canonical public product name.');
$assert(strpos($approvals_source, "__('Backstage Venue Manager', 'backstage-venue-manager')") !== false, 'Approvals badge restoration must preserve the canonical menu label.');
$assert(strpos($settings_source, 'Backstage Venue Manager Settings') !== false, 'Settings heading must use the canonical public product name.');
$assert(strpos($nav_source, 'aria-label="Backstage Venue Manager top navigation"') !== false, 'Primary admin navigation must use the canonical public product name.');
$assert(strpos($readme_source, 'Open the `Backstage Venue Manager` admin menu') !== false, 'Readme installation navigation must use the canonical public product name.');

$public_runtime_files = array($plugin_root . '/readme.txt');
$runtime_iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($plugin_root . '/includes', FilesystemIterator::SKIP_DOTS)
);
foreach ($runtime_iterator as $runtime_file) {
	if ($runtime_file->isFile() && strtolower((string) $runtime_file->getExtension()) === 'php') {
		$public_runtime_files[] = (string) $runtime_file->getPathname();
	}
}
foreach (array($plugin_root . '/assets/admin-ticketing.js', $plugin_root . '/assets/js/vms-event-plan-workflow.js') as $public_js_file) {
	$public_runtime_files[] = $public_js_file;
}

$forbidden_public_branding = array(
	'Vendor Management System',
	'Venue Management System',
	'VMS Settings',
	'All VMS Pages',
	'VMS Ops Console',
	'VMS Notifications',
	'VMS Documentation',
	'VMS Resource Fingerprints',
	'VMS Docs',
	'VMS admin screens',
	'VMS pages when anchors are missing',
	'VMS menu when anchor drift exists',
	'VMS version changes',
	'This automated notice was sent by VMS.',
	'VMS stale-check report',
	'Missing internal VMS file',
	'VMS Reference: Keys + Identifiers',
	'Welcome to the VMS dashboard',
	'Open VMS',
	'[VMS] Staff certification',
	'[VMS] Vendor account',
	'[VMS] Vendor portal',
);
foreach ($public_runtime_files as $public_runtime_file) {
	$source = (string) file_get_contents($public_runtime_file);
	foreach ($forbidden_public_branding as $forbidden_branding) {
		$assert(strpos($source, $forbidden_branding) === false, basename($public_runtime_file) . ' retains stale public product branding: ' . $forbidden_branding);
	}
}

$assert(!function_exists('bvmgr_migrate_legacy_plugin_basename'), 'Automatic activation-state migration must not be shipped.');
$assert(!function_exists('bvmgr_register_legacy_plugin_basename_compatibility'), 'Legacy activation interception must not be shipped.');
$assert($GLOBALS['vms_identity_test_updates'] === array(), 'Loading identity helpers must not write any options.');

$recognized_basenames = bvmgr_recognized_plugin_lifecycle_basenames();
foreach (array(
	'backstage-venue-manager/backstage-venue-manager.php',
	'backstage-venue-manager/vendor-management-system.php',
	'vms/backstage-venue-manager.php',
	'vms/vendor-management-system.php',
) as $recognized_basename) {
	$assert(in_array($recognized_basename, $recognized_basenames, true), 'Lifecycle tracking must recognize ' . $recognized_basename . '.');
}

if ($failures !== array()) {
	fwrite(STDERR, "Plugin identity alignment failures:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "Plugin identity alignment tests passed.\n";
