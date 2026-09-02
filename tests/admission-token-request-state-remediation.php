<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('BVMGR_PLUGIN_URL', 'https://example.test/wp-content/plugins/backstage-venue-manager/');
define('BVMGR_VERSION', 'test-version');
define('ARRAY_A', 'ARRAY_A');

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

function esc_attr(string $text): string
{
	return htmlspecialchars($text, ENT_QUOTES);
}

function esc_attr__(string $text, string $domain = ''): string
{
	return $text;
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

function sanitize_text_field($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$sanitized = preg_replace('/[\x00-\x1F\x7F]+/', '', strip_tags((string) $value));
	return is_string($sanitized) ? trim($sanitized) : '';
}

function wp_unslash($value)
{
	if (is_array($value)) {
		return array_map('wp_unslash', $value);
	}

	return is_string($value) ? stripslashes($value) : $value;
}

function absint($value): int
{
	return abs((int) $value);
}

function is_admin(): bool
{
	return false;
}

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void
{
	unset($hook, $callback, $priority, $accepted_args);
}

function add_filter(string $hook, $callback, int $priority = 10): void
{
	unset($hook, $callback, $priority);
}

function apply_filters(string $hook, $value)
{
	unset($hook);
	return $value;
}

function wp_enqueue_style(string $handle, string $src = '', array $deps = array(), $ver = false): void
{
	unset($handle, $src, $deps, $ver);
}

function get_header(): void
{
}

function get_footer(): void
{
}

function status_header(int $status): void
{
	$GLOBALS['vms_test_status_headers'][] = $status;
}

function nocache_headers(): void
{
	$GLOBALS['vms_test_nocache_calls'] = (int) ($GLOBALS['vms_test_nocache_calls'] ?? 0) + 1;
}

function get_query_var(string $key)
{
	return $GLOBALS['vms_test_query_vars'][$key] ?? '';
}

function get_the_title(int $post_id): string
{
	return 'Title #' . $post_id;
}

function get_post_meta(int $post_id, string $key, bool $single = true)
{
	unset($single);
	if ($key === '_vms_event_date') {
		return '2026-08-01';
	}
	if ($key === '_vms_event_plan_status') {
		return 'ready';
	}
	return '';
}

function bvmgr_meta_key(string $group, string $key): string
{
	return '_vms_event_plan_status';
}

function bvmgr_admission_table_entries(): string
{
	return 'wp_vms_admission_entries';
}

function bvmgr_admission_scan_url(string $token): string
{
	return 'https://example.test/admission/scan/' . rawurlencode($token);
}

function bvmgr_admission_qr_image_url(string $payload): string
{
	return 'https://example.test/qr/' . rawurlencode($payload);
}

function bvmgr_admission_group_entries(array $row): array
{
	return array($row);
}

function bvmgr_admission_ensure_entry_token(int $entry_id): string
{
	return 'group-' . $entry_id;
}

class vms_test_wpdb
{
	public function prepare(string $query, string $token): string
	{
		return $query . ' -- ' . $token;
	}

	public function get_row(string $query, $output_type)
	{
		unset($query, $output_type);
		return null;
	}
}

require_once dirname(__DIR__) . '/includes/runtime-guards.php';
require_once dirname(__DIR__) . '/includes/core/prefix-b4-compat.php';

$pluginRoot = dirname(__DIR__);
$admissionTokensPath = $pluginRoot . '/includes/modules/admissions/admission-tokens.php';
$passClaimsPath = $pluginRoot . '/includes/modules/admissions/pass-claims.php';

$admissionTokensSource = (string) file_get_contents($admissionTokensPath);
$passClaimsSource = (string) file_get_contents($passClaimsPath);

vms_test_assert($admissionTokensSource !== '', 'Admission Tokens source should be readable.');
vms_test_assert($passClaimsSource !== '', 'Pass Claims source should be readable.');

eval(vms_test_extract_function($passClaimsSource, 'bvmgr_pass_claims_get_request_token'));
$scanRouterSource = vms_test_extract_function($admissionTokensSource, 'bvmgr_admission_scan_template_router');
$scanRouterSource = str_replace("exit;", 'return;', $scanRouterSource);
eval($scanRouterSource);

vms_test_assert_contains(
	"bvmgr_get_query_var_compat('bvmgr_pass_claim_token')",
	$passClaimsSource,
	'Pass Claims token lookup should use the canonical-first B4 query compatibility helper.'
);
vms_test_assert_contains(
	"bvmgr_get_query_var_compat('bvmgr_admission_scan_token')",
	$admissionTokensSource,
	'Admission scan routing should use the canonical-first B4 query compatibility helper.'
);
vms_test_assert_contains(
	"bvmgr_request_read_bool_flag(\$_GET, 'vms_print_pass')",
	$admissionTokensSource,
	'Admission scan routing should use the shared boolean helper for print-mode state.'
);
vms_test_assert_not_contains(
	"!empty(\$_GET['vms_print_pass'])",
	$admissionTokensSource,
	'Admission scan routing should no longer treat array-shaped print flags as truthy.'
);

$GLOBALS['vms_test_query_vars'] = array('vms_pass_claim_token' => 'query-token');
$_GET = array();
$_SERVER['REQUEST_URI'] = '';
vms_test_assert_same('query-token', bvmgr_pass_claims_get_request_token(), 'Pass Claims request-token helper should still prefer the rewrite query var.');

$GLOBALS['vms_test_query_vars']['vms_pass_claim_token'] = '';
$_GET = array('vms_pass_claim_token' => 'get%20token');
$_SERVER['REQUEST_URI'] = '';
vms_test_assert_same('get token', bvmgr_pass_claims_get_request_token(), 'Pass Claims request-token helper should still accept a normal scalar query-string token.');

$_GET = array('vms_pass_claim_token' => array('bad-token'));
$_SERVER['REQUEST_URI'] = '/pass/claim/uri%20token';
vms_test_assert_same('uri token', bvmgr_pass_claims_get_request_token(), 'Pass Claims request-token helper should reject array-shaped query-string tokens and fall back to the routed URI token.');

$_GET = array('vms_pass_claim_token' => array('bad-token'));
$_SERVER['REQUEST_URI'] = '';
vms_test_assert_same('', bvmgr_pass_claims_get_request_token(), 'Pass Claims request-token helper should return an empty string when every token source is malformed or missing.');

global $wpdb;
$wpdb = new vms_test_wpdb();

$GLOBALS['vms_test_query_vars'] = array('vms_admission_scan_token' => '');
$GLOBALS['vms_test_status_headers'] = array();
$GLOBALS['vms_test_nocache_calls'] = 0;
$_GET = array('vms_admission_scan_token' => array('bad-token'));
ob_start();
bvmgr_admission_scan_template_router();
$routerOutput = (string) ob_get_clean();
vms_test_assert_same(array(), $GLOBALS['vms_test_status_headers'], 'Admission scan routing should return early when the query-string token is array-shaped.');
vms_test_assert_same('', $routerOutput, 'Admission scan routing should emit no output when the query-string token is malformed.');

$GLOBALS['vms_test_query_vars'] = array('vms_admission_scan_token' => '');
$GLOBALS['vms_test_status_headers'] = array();
$GLOBALS['vms_test_nocache_calls'] = 0;
$_GET = array(
	'vms_admission_scan_token' => 'scan%20token',
	'vms_print_pass' => array('1'),
);
ob_start();
bvmgr_admission_scan_template_router();
$routerOutput = (string) ob_get_clean();
vms_test_assert_same(array(404), $GLOBALS['vms_test_status_headers'], 'Admission scan routing should still reach the 404 public renderer for a scalar token with no matching record.');
vms_test_assert_contains('class="site-main vms-pass-public-page"', $routerOutput, 'Admission scan routing should still render the public pass shell for scalar tokens.');
vms_test_assert_not_contains('vms-pass-public-page--print', $routerOutput, 'Admission scan routing should reject array-shaped print flags.');

$GLOBALS['vms_test_query_vars'] = array('vms_admission_scan_token' => '');
$GLOBALS['vms_test_status_headers'] = array();
$GLOBALS['vms_test_nocache_calls'] = 0;
$_GET = array(
	'vms_admission_scan_token' => 'scan%20token',
	'vms_print_pass' => '1',
);
ob_start();
bvmgr_admission_scan_template_router();
$routerOutput = (string) ob_get_clean();
vms_test_assert_contains('vms-pass-public-page--print', $routerOutput, 'Admission scan routing should preserve scalar print-mode state.');

fwrite(STDOUT, "admission token request state remediation: PASS\n");
