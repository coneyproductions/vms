<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('BVMGR_VERSION', 'test-version');
define('BVMGR_PLUGIN_URL', 'https://example.test/wp-content/plugins/backstage-venue-manager/');

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
	throw new RuntimeException($message . ' @ ' . $file . ':' . $line, $severity);
});

$GLOBALS['vms_test_actions'] = array();
$GLOBALS['vms_test_filters'] = array();
$GLOBALS['vms_test_enqueued_styles'] = array();
$GLOBALS['vms_test_enqueued_scripts'] = array();
$GLOBALS['vms_test_current_screen'] = null;

function vms_test_assert(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
}

function vms_test_assert_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function sanitize_key($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$sanitized = preg_replace('/[^a-z0-9_-]+/i', '', strtolower((string) $value));
	return is_string($sanitized) ? $sanitized : '';
}

function wp_unslash($value)
{
	if (is_array($value)) {
		return array_map('wp_unslash', $value);
	}

	return is_string($value) ? stripslashes($value) : $value;
}

function get_current_screen()
{
	return $GLOBALS['vms_test_current_screen'];
}

function is_admin(): bool
{
	return false;
}

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
{
	unset($priority, $accepted_args);
	$GLOBALS['vms_test_actions'][$hook][] = $callback;
	return true;
}

function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
{
	unset($priority, $accepted_args);
	$GLOBALS['vms_test_filters'][$hook][] = $callback;
	return true;
}

function get_option(string $name, $default = false)
{
	unset($name);
	return $default;
}

function apply_filters(string $hook, $value)
{
	unset($hook);
	return $value;
}

function wp_enqueue_style(string $handle, string $src = '', array $deps = array(), $ver = false): void
{
	$GLOBALS['vms_test_enqueued_styles'][$handle] = compact('src', 'deps', 'ver');
}

function wp_enqueue_script(string $handle, string $src = '', array $deps = array(), $ver = false, bool $in_footer = false): void
{
	$GLOBALS['vms_test_enqueued_scripts'][$handle] = compact('src', 'deps', 'ver', 'in_footer');
}

function __(string $text, string $domain = ''): string
{
	unset($domain);
	return $text;
}

function esc_html($text): string
{
	return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

require dirname(__DIR__) . '/includes/runtime-guards.php';
require dirname(__DIR__) . '/includes/core/plugin.php';
require dirname(__DIR__) . '/includes/core/registry/admin-menu.php';

$helpersSource = (string) file_get_contents(dirname(__DIR__) . '/includes/helpers.php');
$corePluginSource = (string) file_get_contents(dirname(__DIR__) . '/includes/core/plugin.php');
$adminMenuSource = (string) file_get_contents(dirname(__DIR__) . '/includes/core/registry/admin-menu.php');

vms_test_assert($helpersSource !== '', 'Helpers source should be readable.');
vms_test_assert($corePluginSource !== '', 'Core plugin source should be readable.');
vms_test_assert($adminMenuSource !== '', 'Admin menu source should be readable.');

vms_test_assert_contains(
	'$page = vms_request_read_key($_GET, \'page\');',
	$helpersSource,
	'Legacy helper-backed admin asset scope should continue to read page through the shared key helper.'
);
vms_test_assert_contains(
	'$page = vms_request_read_key($_GET, \'page\');',
	$adminMenuSource,
	'Missing-callback page rendering should read page through the shared key helper.'
);

$adminBodyClassFilter = $GLOBALS['vms_test_filters']['admin_body_class'][0] ?? null;
$adminEnqueueAction = $GLOBALS['vms_test_actions']['admin_enqueue_scripts'][0] ?? null;

vms_test_assert(is_callable($adminBodyClassFilter), 'Core plugin should register the admin_body_class filter.');
vms_test_assert(is_callable($adminEnqueueAction), 'Core plugin should register the admin_enqueue_scripts action.');

$_GET = array('page' => 'vms-dashboard');
$GLOBALS['vms_test_current_screen'] = (object) array('post_type' => '');
$classes = $adminBodyClassFilter('base');
vms_test_assert(strpos($classes, 'vms-admin') !== false, 'Valid scalar VMS admin page state should still add the VMS admin class.');
vms_test_assert(strpos($classes, 'vms-page-vms-dashboard') !== false, 'Valid scalar VMS admin page state should still add the page-specific admin class.');

$_GET = array('page' => array('vms-dashboard'));
$GLOBALS['vms_test_current_screen'] = (object) array('post_type' => '');
$classes = $adminBodyClassFilter('base');
vms_test_assert($classes === 'base', 'Array-shaped admin page state should be rejected when no CPT fallback applies.');

$_GET = array('page' => array('vms-dashboard'));
$GLOBALS['vms_test_current_screen'] = (object) array('post_type' => 'vms_event_plan');
$classes = $adminBodyClassFilter('base');
vms_test_assert(strpos($classes, 'vms-admin') !== false, 'Malformed admin page state should still allow the CPT fallback to scope VMS admin styling.');
vms_test_assert(strpos($classes, 'vms-cpt-vms_event_plan') !== false, 'CPT fallback should still preserve the CPT-specific admin class.');

$GLOBALS['vms_test_enqueued_styles'] = array();
$GLOBALS['vms_test_enqueued_scripts'] = array();
$_GET = array('page' => array('vms-dashboard'));
$GLOBALS['vms_test_current_screen'] = (object) array('post_type' => '');
$adminEnqueueAction();
vms_test_assert($GLOBALS['vms_test_enqueued_styles'] === array(), 'Array-shaped admin page state should not enqueue VMS assets without another valid scope signal.');
vms_test_assert($GLOBALS['vms_test_enqueued_scripts'] === array(), 'Array-shaped admin page state should not enqueue VMS scripts without another valid scope signal.');

$GLOBALS['vms_test_enqueued_styles'] = array();
$GLOBALS['vms_test_enqueued_scripts'] = array();
$_GET = array('page' => array('vms-dashboard'));
$GLOBALS['vms_test_current_screen'] = (object) array('post_type' => 'vms_event_plan');
$adminEnqueueAction();
vms_test_assert(isset($GLOBALS['vms_test_enqueued_styles']['vms-admin']), 'CPT fallback should still enqueue VMS admin assets when page state is malformed.');
vms_test_assert(isset($GLOBALS['vms_test_enqueued_scripts']['vms-number-input-guard']), 'CPT fallback should still enqueue the shared admin number-input guard.');

ob_start();
$_GET = array('page' => array('vms-broken-page'));
vms_admin_menu_render_missing_callback_page();
$missingCallbackHtml = (string) ob_get_clean();
vms_test_assert(strpos($missingCallbackHtml, '<code>') === false, 'Missing-callback page rendering should reject array-shaped page values.');

ob_start();
$_GET = array('page' => 'vms-broken-page');
vms_admin_menu_render_missing_callback_page();
$missingCallbackHtml = (string) ob_get_clean();
vms_test_assert(strpos($missingCallbackHtml, 'vms-broken-page') !== false, 'Missing-callback page rendering should preserve valid scalar page slugs.');

fwrite(STDOUT, "passive display state remediation: PASS\n");
