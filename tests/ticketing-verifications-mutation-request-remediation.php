<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

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

function vms_test_normalize_code(string $code): string
{
	$normalized = preg_replace('/\s+/', ' ', $code);
	return is_string($normalized) ? trim($normalized) : trim($code);
}

function vms_test_assert_code_order(string $first, string $second, string $haystack, string $message): void
{
	$haystack = vms_test_normalize_code($haystack);
	$first = vms_test_normalize_code($first);
	$second = vms_test_normalize_code($second);

	$firstPos = strpos($haystack, $first);
	$secondPos = strpos($haystack, $second);

	vms_test_assert($firstPos !== false, $message . "\nMissing first token: " . $first);
	vms_test_assert($secondPos !== false, $message . "\nMissing second token: " . $second);
	vms_test_assert($firstPos < $secondPos, $message);
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

function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	return true;
}

function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	return true;
}

function add_shortcode(string $tag, $callback): bool
{
	return true;
}

function apply_filters(string $hook, $value)
{
	return $value;
}

function is_admin(): bool
{
	return false;
}

function current_user_can(string $capability): bool
{
	return true;
}

function is_user_logged_in(): bool
{
	return true;
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

	if (strpos($location, '//') === 0) {
		return $fallback;
	}

	$parts = parse_url($location);
	if ($parts === false) {
		return $fallback;
	}

	if (!empty($parts['host']) && $parts['host'] !== 'example.test') {
		return $fallback;
	}

	return $location;
}

function get_option(string $key, $default = false)
{
	return $default;
}

function wp_max_upload_size(): int
{
	return 25 * 1024 * 1024;
}

$repoRoot = dirname(__DIR__);
$runtimeGuards = $repoRoot . '/includes/runtime-guards.php';
$verificationFile = $repoRoot . '/includes/integrations/ticketing-verifications.php';

require_once $runtimeGuards;
require_once $verificationFile;

$source = (string) file_get_contents($verificationFile);
vms_test_assert($source !== '', 'Ticketing verifications source should be readable.');

$decisionBody = vms_test_extract_function($source, 'bvmgr_ticketing_verification_handle_decision');
$submitBody = vms_test_extract_function($source, 'bvmgr_ticketing_verification_handle_submit');

vms_test_assert_code_order(
	"\$request_id = bvmgr_ticketing_verification_post_absint('request_id');",
	"wp_verify_nonce(\$nonce, 'vms_verification_decision_' . \$request_id)",
	$decisionBody,
	'Verification decisions should derive the dynamic nonce action from the sanitized request ID before verifying the existing nonce.'
);
vms_test_assert_code_order(
	"wp_verify_nonce(\$nonce, 'vms_verification_decision_' . \$request_id)",
	"\$decision = bvmgr_ticketing_verification_post_key('decision');",
	$decisionBody,
	'Verification decisions should not read the decision value until after the existing nonce passes.'
);
vms_test_assert_code_order(
	"\$decision = bvmgr_ticketing_verification_post_key('decision');",
	"\$review_notes = bvmgr_ticketing_verification_post_text_field('review_notes');",
	$decisionBody,
	'Verification decisions should continue to read review notes only after the decision value has been sanitized.'
);
vms_test_assert_code_order(
	"if (!is_user_logged_in()) {",
	"\$redirect_to = bvmgr_ticketing_verification_post_local_redirect('redirect_to', home_url('/'));",
	$submitBody,
	'Verification submit handlers should continue to derive the redirect target inside the login-required branch.'
);

$_POST = array('response_mode' => ' JSON ');
vms_test_assert_same('json', bvmgr_ticketing_verification_post_key('response_mode'), 'Verification POST key reads should sanitize scalar response modes.');
$_POST = array('response_mode' => array('json'));
vms_test_assert_same('', bvmgr_ticketing_verification_post_key('response_mode'), 'Verification POST key reads should reject array-shaped response modes.');

$_POST = array('review_notes' => "  Needs follow-up  ");
vms_test_assert_same('Needs follow-up', bvmgr_ticketing_verification_post_text_field('review_notes'), 'Verification POST text reads should sanitize scalar review notes.');
$_POST = array('review_notes' => array('bad'));
vms_test_assert_same('', bvmgr_ticketing_verification_post_text_field('review_notes'), 'Verification POST text reads should reject array-shaped review notes.');

$_POST = array('redirect_to' => '/my-account/?view=verification');
vms_test_assert_same('/my-account/?view=verification', bvmgr_ticketing_verification_post_local_redirect('redirect_to', '/fallback'), 'Verification POST redirects should preserve local redirects.');
$_POST = array('redirect_to' => array('/my-account/'));
vms_test_assert_same('/fallback', bvmgr_ticketing_verification_post_local_redirect('redirect_to', '/fallback'), 'Verification POST redirects should reject array-shaped redirect targets.');
$_POST = array('redirect_to' => 'https://evil.example/out');
vms_test_assert_same('/fallback', bvmgr_ticketing_verification_post_local_redirect('redirect_to', '/fallback'), 'Verification POST redirects should reject external redirect targets.');

$_POST = array('request_id' => '57');
vms_test_assert_same(57, bvmgr_ticketing_verification_post_absint('request_id'), 'Verification POST integer reads should preserve scalar request IDs.');
$_POST = array('request_id' => array('57'));
vms_test_assert_same(0, bvmgr_ticketing_verification_post_absint('request_id'), 'Verification POST integer reads should reject array-shaped request IDs.');

$_POST = array('vms_verified_programs_profile_present' => '1');
vms_test_assert_same(true, bvmgr_ticketing_verification_post_has_scalar('vms_verified_programs_profile_present'), 'Verification profile-present flags should accept scalar presence markers.');
$_POST = array('vms_verified_programs_profile_present' => array('1'));
vms_test_assert_same(false, bvmgr_ticketing_verification_post_has_scalar('vms_verified_programs_profile_present'), 'Verification profile-present flags should reject array-shaped presence markers.');

$_POST = array('vms_verified_allowance' => array('veteran' => '3'));
vms_test_assert_same(true, bvmgr_ticketing_verification_post_has_array('vms_verified_allowance'), 'Verification allowance mutations should accept array-shaped payloads.');
$_POST = array('vms_verified_allowance' => '3');
vms_test_assert_same(false, bvmgr_ticketing_verification_post_has_array('vms_verified_allowance'), 'Verification allowance mutations should reject scalar-shaped payloads.');

$_POST = array(
	'vms_verification_program_allowances' => array(
		'veteran' => '5',
		'teacher' => array('7'),
	),
);
vms_test_assert_same(
	array(
		'veteran' => '5',
		'teacher' => array('7'),
	),
	bvmgr_ticketing_verification_post_array('vms_verification_program_allowances'),
	'Verification POST arrays should preserve the unslashed top-level array for schema-specific sanitizers.'
);

$allowances = bvmgr_ticketing_verification_sanitize_allowances(array(
	'veteran' => '5',
	'teacher' => array('7'),
));
vms_test_assert_same(5, $allowances['veteran'] ?? 0, 'Verification allowance sanitization should preserve scalar numeric allowances.');
vms_test_assert_same(2, $allowances['teacher'] ?? 0, 'Verification allowance sanitization should reject nested allowance arrays and fall back to the default allowance.');

$uploadSettings = bvmgr_ticketing_verification_sanitize_upload_settings(array('max_upload_mb' => array('9')));
vms_test_assert_same(20, $uploadSettings['max_upload_mb'] ?? 0, 'Verification upload settings should reject nested upload-size arrays and fall back to the default max upload size.');

fwrite(STDOUT, "ticketing verifications mutation request remediation: PASS\n");
