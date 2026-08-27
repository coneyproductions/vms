<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('BVMGR_PLUGIN_URL', 'https://example.test/wp-content/plugins/backstage-venue-manager/');
define('BVMGR_VERSION', 'test-version');

function vms_test_assert(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
}

function vms_test_assert_same($expected, $actual, string $message): void
{
	vms_test_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function vms_test_assert_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function vms_test_assert_not_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert(strpos($haystack, $needle) === false, $message . "\nUnexpected: " . $needle);
}

function vms_test_extract_function(string $source, string $name): string
{
	$needle = 'function ' . $name . '(';
	$start = strpos($source, $needle);
	if ($start === false) {
		throw new RuntimeException('Unable to locate function ' . $name . '.');
	}

	$brace = strpos($source, '{', $start);
	if ($brace === false) {
		throw new RuntimeException('Unable to locate opening brace for ' . $name . '.');
	}

	$depth = 1;
	$length = strlen($source);
	for ($i = $brace + 1; $i < $length; $i++) {
		if ($source[$i] === '{') {
			$depth++;
			continue;
		}
		if ($source[$i] === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $start, ($i - $start) + 1);
			}
		}
	}

	throw new RuntimeException('Unable to locate closing brace for ' . $name . '.');
}

function __(string $text, string $domain = ''): string
{
	return $text;
}

function esc_html__(string $text, string $domain = ''): string
{
	return $text;
}

function esc_html(string $text): string
{
	return htmlspecialchars($text, ENT_QUOTES);
}

function esc_url(string $text): string
{
	return $text;
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

function current_user_can(string $capability): bool
{
	return !empty($GLOBALS['vms_test_caps'][(string) $capability]);
}

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void
{
	unset($hook, $callback, $priority, $accepted_args);
}

function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void
{
	unset($hook, $callback, $priority, $accepted_args);
}

function apply_filters(string $hook, $value)
{
	unset($hook);
	return $value;
}

function admin_url(string $path = ''): string
{
	return 'https://example.test/wp-admin/' . ltrim($path, '/');
}

function wp_enqueue_style(string $handle, string $src = '', array $deps = array(), $ver = false): void
{
	$GLOBALS['vms_test_styles'][$handle] = array('src' => $src, 'deps' => $deps, 'ver' => $ver);
}

function wp_enqueue_script(string $handle, string $src = '', array $deps = array(), $ver = false, bool $in_footer = false): void
{
	$GLOBALS['vms_test_scripts'][$handle] = array('src' => $src, 'deps' => $deps, 'ver' => $ver, 'in_footer' => $in_footer);
}

function get_current_screen()
{
	return $GLOBALS['vms_test_screen'] ?? null;
}

function is_admin(): bool
{
	return !empty($GLOBALS['vms_test_is_admin']);
}

function bvmgr_add_dispatch_page_slug(): string
{
	return 'vms-add-dispatch';
}

function bvmgr_admin_ui_is_vms_screen(): bool
{
	return !empty($GLOBALS['vms_test_vms_screen']);
}

function get_query_var(string $key)
{
	return $GLOBALS['vms_test_query_vars'][$key] ?? '';
}

function absint($value): int
{
	return abs((int) $value);
}

require_once dirname(__DIR__) . '/includes/runtime-guards.php';

$pluginRoot = dirname(__DIR__);
$adminUiPath = $pluginRoot . '/includes/modules/availability-date-dispatch/admin-ui.php';
$helpersPath = $pluginRoot . '/includes/modules/availability-date-dispatch/helpers.php';

$adminUiSource = (string) file_get_contents($adminUiPath);
$helpersSource = (string) file_get_contents($helpersPath);

vms_test_assert($adminUiSource !== '', 'ADD admin UI source should be readable.');
vms_test_assert($helpersSource !== '', 'ADD helpers source should be readable.');

eval(vms_test_extract_function($adminUiSource, 'bvmgr_add_dispatch_enqueue_admin_assets'));
eval(vms_test_extract_function($adminUiSource, 'bvmgr_add_dispatch_should_render_shell_count'));
eval(vms_test_extract_function($adminUiSource, 'bvmgr_add_dispatch_dashboard_filters'));
eval(vms_test_extract_function($adminUiSource, 'bvmgr_add_dispatch_render_assignment_review'));
eval(vms_test_extract_function($helpersSource, 'bvmgr_add_dispatch_get_request_token'));
eval(vms_test_extract_function($helpersSource, 'bvmgr_add_dispatch_get_request_choice'));

vms_test_assert_contains(
	"\$page = bvmgr_request_read_key(\$_GET, 'page');",
	$adminUiSource,
	'ADD admin UI should read page through the shared key helper for both enqueue and shell-count gates.'
);
vms_test_assert_contains(
	"\$selected_type = bvmgr_request_read_key(\$_GET, 'assign_as');",
	$adminUiSource,
	'ADD assignment review should read assign_as through the shared key helper.'
);
vms_test_assert_contains(
	"\$dashboard_filters = bvmgr_add_dispatch_dashboard_filters(\$_GET);",
	$adminUiSource,
	'ADD dashboard rendering should pass the request through the narrowed dashboard-filter helper.'
);
vms_test_assert_not_contains(
	"isset(\$_GET) && is_array(\$_GET) ? (array) wp_unslash(\$_GET) : array()",
	$adminUiSource,
	'ADD dashboard rendering should no longer broad-cast the whole GET array through a generic unslash path.'
);
vms_test_assert_contains(
	"\$token = bvmgr_request_read_scalar(\$_GET, 'vms_add_dispatch_token');",
	$helpersSource,
	'ADD response-token lookup should preserve the shared scalar token helper.'
);
vms_test_assert_contains(
	"\$choice = bvmgr_request_read_key(\$_GET, 'choice');",
	$helpersSource,
	'ADD response-choice lookup should preserve the shared key helper.'
);

$filters = bvmgr_add_dispatch_dashboard_filters(array(
	'show_full_groups' => '1',
	'show_over_capacity_groups' => array('bad'),
	'include_past_events' => 'no',
	'include_cancelled_events' => 'yes',
));
vms_test_assert_same(
	array(
		'show_full_groups' => true,
		'show_over_capacity_groups' => false,
		'include_past_events' => false,
		'include_cancelled_events' => true,
	),
	$filters,
	'ADD dashboard filters should normalize only the allowed boolean flags and reject nested arrays.'
);

$GLOBALS['vms_test_query_vars'] = array('vms_add_dispatch_token' => 'query-token');
$_GET = array();
$_SERVER['REQUEST_URI'] = '';
vms_test_assert_same('query-token', bvmgr_add_dispatch_get_request_token(), 'ADD response-token lookup should still prefer the routed query var.');

$GLOBALS['vms_test_query_vars']['vms_add_dispatch_token'] = '';
$_GET = array('vms_add_dispatch_token' => 'raw%20token');
$_SERVER['REQUEST_URI'] = '';
vms_test_assert_same('raw token', bvmgr_add_dispatch_get_request_token(), 'ADD response-token lookup should still accept a normal scalar query-string token.');

$_GET = array('vms_add_dispatch_token' => array('bad-token'));
$_SERVER['REQUEST_URI'] = '/availability-dispatch/respond/uri%20token';
vms_test_assert_same('uri token', bvmgr_add_dispatch_get_request_token(), 'ADD response-token lookup should reject array-shaped query-string tokens and fall back to the routed URI token.');

$_GET = array('choice' => 'yes');
vms_test_assert_same('available', bvmgr_add_dispatch_get_request_choice(), 'ADD response-choice lookup should preserve the yes => available normalization.');
$_GET = array('choice' => 'unavailable');
vms_test_assert_same('unavailable', bvmgr_add_dispatch_get_request_choice(), 'ADD response-choice lookup should preserve the direct available/unavailable choices.');
$_GET = array('choice' => array('yes'));
vms_test_assert_same('', bvmgr_add_dispatch_get_request_choice(), 'ADD response-choice lookup should reject array-shaped choice values.');

$GLOBALS['vms_test_vms_screen'] = false;
$_GET = array('page' => 'vms-dashboard');
vms_test_assert_same(true, bvmgr_add_dispatch_should_render_shell_count(), 'ADD shell-count gating should preserve the dashboard page slug.');
$_GET = array('page' => array('vms-dashboard'));
vms_test_assert_same(false, bvmgr_add_dispatch_should_render_shell_count(), 'ADD shell-count gating should reject array-shaped page values.');

$GLOBALS['vms_test_caps'] = array('manage_options' => true);
$GLOBALS['vms_test_styles'] = array();
$GLOBALS['vms_test_scripts'] = array();
$GLOBALS['vms_test_screen'] = (object) array('post_type' => '');
$_GET = array('page' => 'vms-add-dispatch');
bvmgr_add_dispatch_enqueue_admin_assets();
vms_test_assert(isset($GLOBALS['vms_test_styles']['vms-add-dispatch-admin']), 'ADD admin enqueue should still load the stylesheet for the valid page slug.');
vms_test_assert(isset($GLOBALS['vms_test_scripts']['vms-add-dispatch-admin']), 'ADD admin enqueue should still load the script for the valid page slug.');

$GLOBALS['vms_test_styles'] = array();
$GLOBALS['vms_test_scripts'] = array();
$GLOBALS['vms_test_screen'] = (object) array('post_type' => '');
$_GET = array('page' => array('vms-add-dispatch'));
bvmgr_add_dispatch_enqueue_admin_assets();
vms_test_assert_same(array(), $GLOBALS['vms_test_styles'], 'ADD admin enqueue should reject array-shaped page values.');
vms_test_assert_same(array(), $GLOBALS['vms_test_scripts'], 'ADD admin enqueue should reject array-shaped page values.');

class WP_Error
{
	private $message;

	public function __construct(string $message)
	{
		$this->message = $message;
	}

	public function get_error_message(): string
	{
		return $this->message;
	}
}

function is_wp_error($value): bool
{
	return $value instanceof WP_Error;
}

function bvmgr_add_dispatch_assignment_review(int $response_id, string $selected_type)
{
	$GLOBALS['vms_test_assignment_review_args'] = array($response_id, $selected_type);
	return new WP_Error('No assignment');
}

function bvmgr_add_dispatch_admin_url(array $args = array()): string
{
	unset($args);
	return 'https://example.test/wp-admin/admin.php?page=vms-add-dispatch';
}

ob_start();
$_GET = array('assign_as' => array('primary'));
bvmgr_add_dispatch_render_assignment_review(55);
ob_end_clean();
vms_test_assert_same(array(55, ''), $GLOBALS['vms_test_assignment_review_args'], 'ADD assignment review should reject array-shaped assign_as values.');

ob_start();
$_GET = array('assign_as' => 'primary');
bvmgr_add_dispatch_render_assignment_review(55);
ob_end_clean();
vms_test_assert_same(array(55, 'primary'), $GLOBALS['vms_test_assignment_review_args'], 'ADD assignment review should preserve scalar assign_as values.');

fwrite(STDOUT, "availability date dispatch request state remediation: PASS\n");
