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
$GLOBALS['vms_test_is_admin'] = true;
$GLOBALS['vms_test_logged_in'] = true;
$GLOBALS['vms_test_caps'] = array(
	'read' => true,
	'manage_options' => false,
);
$GLOBALS['vms_test_current_screen'] = null;
$GLOBALS['vms_test_query_registry'] = array();
$GLOBALS['vms_test_enqueued_styles'] = array();
$GLOBALS['vms_test_enqueued_scripts'] = array();
$GLOBALS['vms_test_localized'] = array();

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

function sanitize_text_field($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$sanitized = preg_replace('/[\x00-\x1F\x7F]+/', '', stripslashes((string) $value));
	return is_string($sanitized) ? trim($sanitized) : '';
}

function absint($value): int
{
	return abs((int) $value);
}

function wp_unslash($value)
{
	if (is_array($value)) {
		return array_map('wp_unslash', $value);
	}

	return is_string($value) ? stripslashes($value) : $value;
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

function is_admin(): bool
{
	return !empty($GLOBALS['vms_test_is_admin']);
}

function is_user_logged_in(): bool
{
	return !empty($GLOBALS['vms_test_logged_in']);
}

function current_user_can(string $capability): bool
{
	return !empty($GLOBALS['vms_test_caps'][$capability]);
}

function get_current_screen()
{
	return $GLOBALS['vms_test_current_screen'];
}

function get_option(string $name, $default = false)
{
	unset($name);
	return $default;
}

function update_option(string $name, $value, bool $autoload = true): bool
{
	unset($name, $value, $autoload);
	return true;
}

function get_transient(string $key)
{
	unset($key);
	return false;
}

function set_transient(string $key, $value, int $expiration): bool
{
	unset($key, $value, $expiration);
	return true;
}

function delete_transient(string $key): bool
{
	unset($key);
	return true;
}

function admin_url(string $path = ''): string
{
	return 'https://example.test/wp-admin/' . ltrim($path, '/');
}

function esc_url_raw($value): string
{
	return is_scalar($value) ? trim((string) $value) : '';
}

function rest_url(string $path = ''): string
{
	return 'https://example.test/wp-json/' . ltrim($path, '/');
}

function wp_enqueue_style(string $handle, string $src = '', array $deps = array(), $ver = false): void
{
	$GLOBALS['vms_test_enqueued_styles'][$handle] = compact('src', 'deps', 'ver');
}

function wp_enqueue_script(string $handle, string $src = '', array $deps = array(), $ver = false, bool $in_footer = false): void
{
	$GLOBALS['vms_test_enqueued_scripts'][$handle] = compact('src', 'deps', 'ver', 'in_footer');
}

function wp_localize_script(string $handle, string $name, array $data): void
{
	$GLOBALS['vms_test_localized'][$handle] = compact('name', 'data');
}

function wp_create_nonce(string $action): string
{
	return 'nonce:' . $action;
}

function wp_kses($text, array $allowed = array()): string
{
	unset($allowed);
	return is_scalar($text) ? (string) $text : '';
}

function wp_parse_url(string $url)
{
	return parse_url($url);
}

function apply_filters(string $hook, $value)
{
	unset($hook);
	return $value;
}

function bvmgr_get_tour_registry(): array
{
	return $GLOBALS['vms_test_query_registry'];
}

require dirname(__DIR__) . '/includes/runtime-guards.php';
require dirname(__DIR__) . '/includes/tours/class-vms-tours-screen.php';
require dirname(__DIR__) . '/includes/core/tours/class-vms-tours.php';

$serviceSource = (string) file_get_contents(dirname(__DIR__) . '/includes/tours/class-vms-tours-service.php');
$screenSource = (string) file_get_contents(dirname(__DIR__) . '/includes/tours/class-vms-tours-screen.php');
$coreToursSource = (string) file_get_contents(dirname(__DIR__) . '/includes/core/tours/class-vms-tours.php');

vms_test_assert($serviceSource !== '', 'Tours service source should be readable.');
vms_test_assert($screenSource !== '', 'Tours screen source should be readable.');
vms_test_assert($coreToursSource !== '', 'Core tours source should be readable.');

vms_test_assert_contains(
	'$page = bvmgr_request_read_key($_GET, \'page\');',
	$serviceSource,
	'Tours service enqueue scope should continue to read page through the shared key helper.'
);
vms_test_assert_contains(
	'$page = bvmgr_request_read_text_field($_GET, \'page\');',
	$screenSource,
	'Tours screen resolution should continue to read page through the shared text helper.'
);
vms_test_assert_contains(
	'$page = bvmgr_request_read_key($_GET, \'page\');',
	$coreToursSource,
	'Core tours page routing should continue to read page through the shared key helper.'
);

$screen = new BVMGR_Tours_Screen();

$_GET = array('page' => 'vms');
$GLOBALS['vms_test_current_screen'] = (object) array('id' => 'ignored', 'post_type' => '');
vms_test_assert($screen->resolve_admin_screen_key() === 'admin:vms-dashboard', 'Tours screen resolution should preserve the vms => vms-dashboard mapping.');

$_GET = array('page' => array('vms-guided-tours'));
$GLOBALS['vms_test_current_screen'] = (object) array('id' => 'VMS_Guided_Tours', 'post_type' => '');
vms_test_assert($screen->resolve_admin_screen_key() === 'admin:vms_guided_tours', 'Tours screen resolution should reject array-shaped page values and fall back to the current screen ID.');

$_GET = array('page' => array('vms-dashboard'));
$GLOBALS['vms_test_current_screen'] = (object) array('id' => '', 'post_type' => '');
vms_test_assert($screen->is_vms_admin_screen() === false, 'Tours screen detection should reject array-shaped page values when no CPT or screen fallback applies.');

$_GET = array('page' => array('vms-dashboard'));
$GLOBALS['vms_test_current_screen'] = (object) array('id' => '', 'post_type' => 'vms_event_plan');
vms_test_assert($screen->is_vms_admin_screen() === true, 'Tours screen detection should preserve the CPT fallback when page state is malformed.');

$_GET = array('page' => 'vms-dashboard');
$GLOBALS['vms_test_current_screen'] = (object) array('id' => '', 'post_type' => '');
vms_test_assert(BVMGR_Tours::is_admin_on_vms_page() === true, 'Core tours page routing should preserve valid scalar VMS admin pages.');

$_GET = array('page' => array('vms-dashboard'));
$GLOBALS['vms_test_current_screen'] = (object) array('id' => '', 'post_type' => '');
vms_test_assert(BVMGR_Tours::is_admin_on_vms_page() === false, 'Core tours page routing should reject array-shaped page values without a CPT fallback.');

$_GET = array('page' => array('vms-dashboard'));
$GLOBALS['vms_test_current_screen'] = (object) array('id' => '', 'post_type' => 'vms_vendor');
vms_test_assert(BVMGR_Tours::is_admin_on_vms_page() === true, 'Core tours page routing should preserve the CPT fallback when page state is malformed.');

$GLOBALS['vms_test_query_registry'] = array(
	array(
		'id' => 'tour-guided',
		'contexts' => array(
			array(
				'context_key' => 'admin:vms-guided-tours',
				'screen_id' => 'ignored',
				'url' => 'admin.php?page=vms-guided-tours',
			),
		),
		'steps' => array(
			array(
				'anchor' => '#guided-tour',
				'title' => 'Guided Tour',
				'content' => 'Help content',
			),
		),
	),
);

$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=vms-guided-tours';
$_GET = array('page' => 'vms-guided-tours');
$GLOBALS['vms_test_current_screen'] = (object) array('id' => 'ignored', 'post_type' => '');
vms_test_assert(BVMGR_Tours::get_current_context_key() === 'adminvms-guided-tours', 'Core tours context lookup should preserve valid scalar page matches through the current normalized context-key contract.');

$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=other';
$_GET = array('page' => array('vms-guided-tours'));
$GLOBALS['vms_test_current_screen'] = (object) array('id' => '', 'post_type' => '');
vms_test_assert(BVMGR_Tours::get_current_context_key() === '', 'Core tours context lookup should reject array-shaped page values when neither URL nor screen matches a registered context.');

$GLOBALS['vms_test_enqueued_styles'] = array();
$GLOBALS['vms_test_enqueued_scripts'] = array();
$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=other';
$_GET = array('page' => array('vms-guided-tours'));
$GLOBALS['vms_test_current_screen'] = (object) array('id' => '', 'post_type' => '');
BVMGR_Tours::enqueue_assets();
vms_test_assert($GLOBALS['vms_test_enqueued_styles'] === array(), 'Core tours asset enqueue should reject array-shaped page values when there is no other runtime context.');
vms_test_assert($GLOBALS['vms_test_enqueued_scripts'] === array(), 'Core tours asset enqueue should not boot tours assets for malformed page state alone.');

fwrite(STDOUT, "tours passive request state remediation: PASS\n");
