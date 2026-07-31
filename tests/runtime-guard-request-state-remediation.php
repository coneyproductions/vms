<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
	throw new RuntimeException($message . ' @ ' . $file . ':' . $line, $severity);
});

function vms_test_assert(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
}

function vms_test_assert_same($expected, $actual, string $message): void
{
	vms_test_assert(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

function vms_test_assert_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function sanitize_text_field($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$value = stripslashes((string) $value);
	$value = preg_replace('/[\x00-\x1F\x7F]+/', '', $value);
	return is_string($value) ? trim($value) : '';
}

function sanitize_key($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$sanitized = preg_replace('/[^a-z0-9_-]+/i', '', strtolower((string) $value));
	return is_string($sanitized) ? $sanitized : '';
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

function is_admin(): bool
{
	return !empty($GLOBALS['vms_test_is_admin']);
}

function get_current_screen()
{
	return $GLOBALS['vms_test_current_screen'] ?? null;
}

function get_post_type(int $post_id): string
{
	return (string) ($GLOBALS['vms_test_post_types'][$post_id] ?? '');
}

function apply_filters(string $hook, $value)
{
	unset($hook);
	return $value;
}

function wp_doing_ajax(): bool
{
	return false;
}

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
{
	unset($hook, $callback, $priority, $accepted_args);
	return true;
}

function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
{
	unset($hook, $callback, $priority, $accepted_args);
	return true;
}

require dirname(__DIR__) . '/includes/runtime-guards.php';

$source = (string) file_get_contents(dirname(__DIR__) . '/includes/runtime-guards.php');
vms_test_assert($source !== '', 'Runtime guards source should be readable.');
vms_test_assert_contains(
	'$page = vms_request_read_key($_GET, \'page\');',
	$source,
	'Runtime guards should read passive admin page state through the shared key helper.'
);
vms_test_assert_contains(
	'$post_type = vms_request_read_key($_GET, \'post_type\');',
	$source,
	'Runtime guards should read passive admin post_type state through the shared key helper.'
);
vms_test_assert_contains(
	'$post_id = vms_request_read_absint($_GET, \'post\');',
	$source,
	'Runtime guards should read passive admin post IDs through the shared integer helper.'
);
vms_test_assert_contains(
	'return vms_request_read_scalar($_REQUEST, $key);',
	$source,
	'Runtime guards should route allowlisted dynamic request keys through the shared scalar helper.'
);
vms_test_assert_contains(
	"'vms_admin_heavy_action'",
	$source,
	'Runtime guard request-value allowlist should preserve the heavy-action key.'
);
vms_test_assert_contains(
	"'_wpnonce'",
	$source,
	'Runtime guard request-value allowlist should preserve the WordPress nonce fallback key.'
);

$GLOBALS['vms_test_is_admin'] = true;
$GLOBALS['vms_test_current_screen'] = null;
$GLOBALS['vms_test_post_types'] = array(
	41 => 'tribe_events',
	77 => 'vms_event_plan',
	88 => 'vms_event_plan',
);

$_GET = array('page' => 'VMS-Dashboard');
vms_test_assert_same('vms-dashboard', vms_resource_fingerprint_current_admin_page(), 'Passive admin page state should preserve valid scalar page slugs.');

$_GET = array(
	'page' => array('bad'),
	'post_type' => 'VMS_Event_Plan',
);
vms_test_assert_same('vms_event_plan', vms_resource_fingerprint_current_admin_page(), 'Passive admin page fingerprinting should reject array-shaped page values and fall back to post_type.');

$_GET = array(
	'page' => array('bad'),
	'post_type' => array('bad'),
	'post' => '77',
);
vms_test_assert_same('vms_event_plan', vms_resource_fingerprint_current_admin_page(), 'Passive admin page fingerprinting should reject malformed post_type and derive post scope from a valid scalar post ID.');

$_GET = array(
	'page' => array('bad'),
	'post_type' => array('bad'),
	'post' => array('77'),
);
$GLOBALS['vms_test_current_screen'] = (object) array('id' => 'Edit-VMS_Venue');
vms_test_assert_same('edit-vms_venue', vms_resource_fingerprint_current_admin_page(), 'Passive admin page fingerprinting should reject malformed post IDs and fall back to the current screen ID.');

$GLOBALS['vms_test_current_screen'] = (object) array('id' => 'edit-vms_event_plan');
$_GET = array();
vms_test_assert_same('edit-vms_event_plan', vms_admin_guard_current_screen_id(), 'Passive admin screen detection should preserve an explicit current screen ID.');

$GLOBALS['vms_test_current_screen'] = null;
$GLOBALS['pagenow'] = 'edit.php';
$_GET = array('post_type' => 'VMS_Event_Plan');
vms_test_assert_same('', vms_admin_guard_current_screen_id(), 'Passive admin screen detection should preserve the existing no-screen-id fallback behavior when only pagenow-based edit context is available.');

$_GET = array(
	'post_type' => array('bad'),
	'post' => '77',
);
vms_test_assert_same('', vms_admin_guard_current_screen_id(), 'Passive admin screen detection should preserve the existing no-screen-id fallback behavior when malformed post_type input leaves only the derived post ID path.');

$_REQUEST = array('action' => "  slashed\\-action  ");
vms_test_assert_same('slashed-action', vms_admin_guard_request_value('action'), 'Allowlisted dynamic request keys should unslash and trim scalar input.');

$_REQUEST = array('action' => array('bad'));
vms_test_assert_same('', vms_admin_guard_request_value('action'), 'Allowlisted dynamic request keys should reject array-shaped input.');

$_REQUEST = array('unexpected' => 'value');
vms_test_assert_same('', vms_admin_guard_request_value('unexpected'), 'Dynamic request reads should reject keys outside the finite allowlist.');

$_REQUEST = array('_wpnonce' => '  nonce-value  ');
vms_test_assert_same('nonce-value', vms_admin_guard_request_value('_wpnonce'), 'Dynamic request reads should preserve the existing nonce fallback keys.');

$_GET = array(
	'post_type' => array('bad'),
	'post' => '41',
);
$GLOBALS['vms_test_current_screen'] = null;
vms_test_assert_same(true, vms_admin_guard_is_tec_admin_request(), 'Passive TEC admin detection should reject malformed post_type values and preserve the post-ID fallback.');

$_GET = array(
	'page' => array('bad'),
	'post' => '88',
);
$GLOBALS['vms_test_current_screen'] = null;
$scope = vms_resource_fingerprint_sensitive_admin_scope();
vms_test_assert_same('event_plan_editor', $scope['scope_reason'] ?? '', 'Sensitive admin scope should preserve the event-plan editor classification derived from a valid scalar post ID.');
vms_test_assert_same('vms_event_plan', $scope['page'] ?? '', 'Sensitive admin scope should preserve the derived event-plan page slug.');
vms_test_assert_same(0, $scope['post_id'] ?? 0, 'Sensitive admin scope should preserve the existing derived-page contract without introducing a new post_id field in this branch.');

$_GET = array(
	'page' => array('bad'),
	'post' => array('88'),
);
vms_test_assert_same(array(), vms_resource_fingerprint_sensitive_admin_scope(), 'Sensitive admin scope should reject malformed array-shaped post IDs.');

fwrite(STDOUT, "runtime guard request state remediation: PASS\n");
