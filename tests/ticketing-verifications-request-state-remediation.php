<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

final class VMS_Ticketing_Verifications_Test_Wp_Die extends RuntimeException
{
}

function vms_test_assert(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
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
	return $text;
}

function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	unset($priority, $acceptedArgs);
	$GLOBALS['vms_test_actions'][$hook][] = $callback;
	return true;
}

function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	unset($hook, $callback, $priority, $acceptedArgs);
	return true;
}

function add_shortcode(string $tag, $callback): bool
{
	unset($tag, $callback);
	return true;
}

function apply_filters(string $hook, $value)
{
	return $value;
}

function is_admin(): bool
{
	return !empty($GLOBALS['vms_test_is_admin']);
}

function current_user_can(string $capability): bool
{
	return !empty($GLOBALS['vms_test_capabilities'][$capability]);
}

function is_user_logged_in(): bool
{
	return !empty($GLOBALS['vms_test_logged_in']);
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

function sanitize_textarea_field($value): string
{
	return sanitize_text_field($value);
}

function sanitize_email($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	return strtolower(trim((string) $value));
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

function home_url(string $path = '/'): string
{
	return 'https://example.test' . $path;
}

function wp_validate_redirect(string $location, string $fallback = ''): string
{
	$location = trim($location);
	if ($location === '') {
		return $fallback;
	}

	if (strpos($location, '/') === 0 || strpos($location, 'https://example.test/') === 0) {
		return $location;
	}

	return $fallback;
}

function remove_query_arg($keys, string $url): string
{
	$keys = (array) $keys;
	$parts = parse_url($url);
	$base = '';
	if (isset($parts['scheme'], $parts['host'])) {
		$base = $parts['scheme'] . '://' . $parts['host'];
	}
	$base .= $parts['path'] ?? '';

	$query = array();
	if (!empty($parts['query'])) {
		parse_str((string) $parts['query'], $query);
	}

	foreach ($keys as $key) {
		unset($query[(string) $key]);
	}

	$queryString = http_build_query($query);
	return $queryString === '' ? $base : ($base . '?' . $queryString);
}

function get_option(string $key, $default = false)
{
	return $default;
}

function wp_max_upload_size(): int
{
	return 25 * 1024 * 1024;
}

function wp_die($message = ''): void
{
	if (is_scalar($message)) {
		throw new VMS_Ticketing_Verifications_Test_Wp_Die((string) $message);
	}

	throw new VMS_Ticketing_Verifications_Test_Wp_Die('wp_die');
}

$repoRoot = dirname(__DIR__);
$runtimeGuards = $repoRoot . '/includes/runtime-guards.php';
$verificationFile = $repoRoot . '/includes/integrations/ticketing-verifications.php';

require_once $runtimeGuards;
require_once $verificationFile;

$source = (string) file_get_contents($verificationFile);
vms_test_assert($source !== '', 'Ticketing verifications source should be readable.');
vms_test_assert(strpos($source, "wp_verify_nonce(\$nonce, 'vms_submit_verification_request')") !== false, 'Verification submit nonce action should remain unchanged.');
vms_test_assert(strpos($source, "wp_verify_nonce(\$nonce, 'vms_verification_decision_' . \$request_id)") !== false, 'Verification decision nonce action should remain unchanged.');
vms_test_assert(strpos($source, "wp_verify_nonce(\$nonce, 'vms_verification_proof_' . \$request_id)") !== false, 'Verification proof nonce action should remain unchanged.');
vms_test_assert(strpos($source, 'bvmgr_ticketing_verification_proof_payload($request_id)') !== false, 'Verification proof payload authorization flow should remain unchanged.');
vms_test_assert(strpos($source, "'vms_verification_program_allowances'") !== false, 'Verification program allowances request key should remain unchanged.');
vms_test_assert(strpos($source, "'vms_verification_upload_settings'") !== false, 'Verification upload settings request key should remain unchanged.');
vms_test_assert(strpos($source, "'vms_verified_programs_profile'") !== false, 'Verification profile request key should remain unchanged.');
vms_test_assert(strpos($source, "'vms_verified_allowance'") !== false, 'Verification allowance request key should remain unchanged.');

$actions = $GLOBALS['vms_test_actions'] ?? array();
vms_test_assert(in_array('bvmgr_ticketing_verification_handle_submit', $actions['admin_post_vms_submit_verification'] ?? array(), true), 'Verification submit admin-post hook should remain registered.');
vms_test_assert(in_array('bvmgr_ticketing_verification_handle_decision', $actions['admin_post_vms_verification_decision'] ?? array(), true), 'Verification decision admin-post hook should remain registered.');
vms_test_assert(in_array('bvmgr_ticketing_verification_stream_proof', $actions['admin_post_vms_view_verification_proof'] ?? array(), true), 'Verification proof-view admin-post hook should remain registered.');

$GLOBALS['vms_test_capabilities'] = array(
	'vms_manage_verifications' => false,
	'manage_options' => false,
);
$GLOBALS['vms_test_logged_in'] = true;
$_GET = array();
$fallback = 'https://example.test/my-account/';

vms_test_assert(bvmgr_ticketing_verification_query_key('vms_verification_notice') === '', 'Verification notice helper should return an empty string when state is missing.');
vms_test_assert(bvmgr_ticketing_verification_query_text_field('s') === '', 'Verification text helper should return an empty string when state is missing.');
vms_test_assert(bvmgr_ticketing_verification_query_absint('request_id') === 0, 'Verification integer helper should return zero when state is missing.');
vms_test_assert(bvmgr_ticketing_verification_query_bool_flag('vms_debug') === false, 'Verification debug helper should return false when state is missing.');
vms_test_assert(bvmgr_ticketing_verification_query_local_redirect('vms_return_to', $fallback) === $fallback, 'Verification redirect helper should preserve the fallback when state is missing.');

$_GET['vms_verification_notice'] = ' submitted ';
vms_test_assert(bvmgr_ticketing_verification_query_key('vms_verification_notice') === 'submitted', 'Verification notice helper should sanitize scalar notice state.');

$_GET['vms_verify_program'] = 'First Responder';
vms_test_assert(bvmgr_ticketing_verification_query_key('vms_verify_program') === 'firstresponder', 'Verification program helper should sanitize scalar program state.');

$_GET['s'] = ' Search ';
vms_test_assert(bvmgr_ticketing_verification_query_text_field('s') === 'Search', 'Verification text helper should sanitize scalar search state.');

$_GET['request_id'] = '37';
vms_test_assert(bvmgr_ticketing_verification_query_absint('request_id') === 37, 'Verification integer helper should preserve scalar request IDs.');

$_GET['vms_debug'] = '1';
vms_test_assert(bvmgr_ticketing_verification_query_bool_flag('vms_debug') === true, 'Verification debug helper should preserve scalar boolean flags.');

$_GET['vms_return_to'] = '/my-account/?vms_verification=1';
vms_test_assert(bvmgr_ticketing_verification_query_local_redirect('vms_return_to', $fallback) === '/my-account/?vms_verification=1', 'Verification redirect helper should preserve scalar local redirect state.');

$_GET['vms_verification_notice'] = array('submitted');
vms_test_assert(bvmgr_ticketing_verification_query_key('vms_verification_notice') === '', 'Verification notice helper should reject array-shaped notice state.');

$_GET['request_id'] = array('37');
vms_test_assert(bvmgr_ticketing_verification_query_absint('request_id') === 0, 'Verification integer helper should reject array-shaped request IDs.');

$_GET['vms_debug'] = array('1');
vms_test_assert(bvmgr_ticketing_verification_query_bool_flag('vms_debug') === false, 'Verification debug helper should reject array-shaped boolean flags.');

$_GET['vms_return_to'] = array('/my-account/');
vms_test_assert(bvmgr_ticketing_verification_query_local_redirect('vms_return_to', $fallback) === $fallback, 'Verification redirect helper should reject array-shaped redirect state.');

$uploadSettings = bvmgr_ticketing_verification_sanitize_upload_settings(array('max_upload_mb' => '40'));
vms_test_assert(($uploadSettings['max_upload_mb'] ?? 0) === 40, 'Verification structured settings arrays should remain accepted and sanitized.');

$_GET = array();
try {
	bvmgr_ticketing_verification_stream_proof();
	throw new RuntimeException('Verification proof stream should have stopped on the capability gate.');
} catch (VMS_Ticketing_Verifications_Test_Wp_Die $exception) {
	vms_test_assert(strpos($exception->getMessage(), 'Insufficient permissions.') !== false, 'Verification proof stream should preserve capability and private-file authorization gates.');
}

fwrite(STDOUT, "ticketing verifications request state remediation: PASS\n");
