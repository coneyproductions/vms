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

function vms_test_assert_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function vms_test_assert_not_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert(strpos($haystack, $needle) === false, $message . "\nUnexpected: " . $needle);
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

function sanitize_email($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$value = strtolower(trim((string) $value));
	return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
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

function admin_url(string $path = ''): string
{
	return 'https://example.test/wp-admin/' . ltrim($path, '/');
}

function home_url(string $path = '/'): string
{
	return 'https://example.test' . $path;
}

function add_query_arg($args, string $url = ''): string
{
	if (!is_array($args)) {
		return $url;
	}

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

	foreach ($args as $key => $value) {
		$query[(string) $key] = $value;
	}

	$queryString = http_build_query($query);
	return $queryString === '' ? $base : ($base . '?' . $queryString);
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

function bvmgr_json_decode_associative(string $raw, int $depth = 8): array
{
	$decoded = json_decode($raw, true, $depth);
	return array(
		'ok' => json_last_error() === JSON_ERROR_NONE,
		'value' => $decoded,
		'top_level_token' => is_array($decoded) ? '{' : '',
	);
}

function bvmgr_json_decoded_is_object(array $value, string $token = ''): bool
{
	return $token === '{';
}

$repoRoot = dirname(__DIR__);
$runtimeGuards = $repoRoot . '/includes/runtime-guards.php';
$claimsAdmin = $repoRoot . '/includes/integrations/ticketing-claims-admin.php';
$claimsCustomer = $repoRoot . '/includes/integrations/ticketing-claims-customer.php';
$frontBundle = $repoRoot . '/assets/vms-ticketing-front.js';

require_once $runtimeGuards;
require_once $claimsAdmin;
require_once $claimsCustomer;

$claimsAdminSource = (string) file_get_contents($claimsAdmin);
$claimsCustomerSource = (string) file_get_contents($claimsCustomer);
$frontBundleSource = (string) file_get_contents($frontBundle);
vms_test_assert($claimsAdminSource !== '', 'Claims admin source should be readable.');
vms_test_assert($claimsCustomerSource !== '', 'Claims customer source should be readable.');
vms_test_assert($frontBundleSource !== '', 'Ticketing front bundle source should be readable.');

$updateGrantNoteBody = vms_test_extract_function($claimsAdminSource, 'bvmgr_ticketing_claims_handle_update_grant_note');
$setGrantStatusBody = vms_test_extract_function($claimsAdminSource, 'bvmgr_ticketing_claims_handle_set_grant_status');
$existingCountsHelperBody = vms_test_extract_function($claimsCustomerSource, 'bvmgr_ticketing_claims_post_existing_counts');

vms_test_assert_code_order(
	"\$grant_id = bvmgr_ticketing_claims_post_absint('grant_id');",
	"check_admin_referer('vms_ticketing_claims_update_grant_note_' . \$grant_id);",
	$updateGrantNoteBody,
	'Grant-note mutations should derive the dynamic nonce action from the sanitized grant ID before verifying the existing nonce.'
);
vms_test_assert_code_order(
	"check_admin_referer('vms_ticketing_claims_update_grant_note_' . \$grant_id);",
	"\$event_plan_id = bvmgr_ticketing_claims_post_absint('event_plan_id');",
	$updateGrantNoteBody,
	'Grant-note mutations should read the event plan ID only after the existing nonce has been verified.'
);
vms_test_assert_code_order(
	"\$grant_id = bvmgr_ticketing_claims_post_absint('grant_id');",
	"check_admin_referer('vms_ticketing_claims_set_grant_status_' . \$grant_id);",
	$setGrantStatusBody,
	'Grant-status mutations should derive the dynamic nonce action from the sanitized grant ID before verifying the existing nonce.'
);
vms_test_assert_code_order(
	"check_admin_referer('vms_ticketing_claims_set_grant_status_' . \$grant_id);",
	"\$event_plan_id = bvmgr_ticketing_claims_post_absint('event_plan_id');",
	$setGrantStatusBody,
	'Grant-status mutations should read the event plan ID only after the existing nonce has been verified.'
);
vms_test_assert_contains(
	"bvmgr_ticketing_claims_post_local_redirect('_wp_http_referer')",
	$claimsAdminSource,
	'Claims admin redirects should consume the posted referer through the local redirect helper.'
);
vms_test_assert_contains(
	"\$existing_counts = bvmgr_ticketing_claims_post_existing_counts();",
	$claimsCustomerSource,
	'Assignee validation should route existing_counts through the subsystem-local POST helper.'
);
vms_test_assert_contains(
	'return bvmgr_ticketing_claims_parse_existing_counts_payload($raw_existing_counts);',
	$existingCountsHelperBody,
	'The existing_counts POST helper should keep the shared normalization logic.'
);
vms_test_assert_not_contains(
	"payload.set('existing_counts', JSON.stringify(collectTicketClaimVerifiedCounts(ticketModel, rowState.seat)));",
	$frontBundleSource,
	'Tickets front-end assignee validation should no longer submit existing_counts as a scalar JSON string.'
);
vms_test_assert_contains(
	"payload.set('existing_counts[' + emailKey + ']', String(existingCounts[emailKey]));",
	$frontBundleSource,
	'Tickets front-end assignee validation should submit existing_counts as array-shaped POST fields.'
);

$_POST = array('grant_id' => '42');
vms_test_assert_same(42, bvmgr_ticketing_claims_post_absint('grant_id'), 'Claims admin POST integers should preserve scalar grant IDs.');
$_POST = array('grant_id' => array('42'));
vms_test_assert_same(0, bvmgr_ticketing_claims_post_absint('grant_id'), 'Claims admin POST integers should reject array-shaped grant IDs.');

$_POST = array('_wp_http_referer' => '/wp-admin/post.php?post=9&action=edit');
vms_test_assert_same('/wp-admin/post.php?post=9&action=edit', bvmgr_ticketing_claims_post_local_redirect('_wp_http_referer', '/fallback'), 'Claims admin referers should preserve safe local redirects.');
$_POST = array('_wp_http_referer' => array('/wp-admin/post.php?post=9&action=edit'));
vms_test_assert_same('/fallback', bvmgr_ticketing_claims_post_local_redirect('_wp_http_referer', '/fallback'), 'Claims admin referers should reject array-shaped values.');
$_POST = array('_wp_http_referer' => 'https://evil.example/phish');
vms_test_assert_same('/fallback', bvmgr_ticketing_claims_post_local_redirect('_wp_http_referer', '/fallback'), 'Claims admin referers should reject external redirects.');

$_POST = array(
	'existing_counts' => array(
		'Buyer@example.com' => '2',
		'Guest@example.com' => '-3',
		'bad' => array('nested'),
		'' => '7',
	),
);
vms_test_assert_same(
	array(
		'buyer@example.com' => 2,
		'guest@example.com' => 3,
	),
	bvmgr_ticketing_claims_post_existing_counts(),
	'Claims customer existing_counts should accept only top-level arrays and normalize keyed nonnegative counts.'
);

$_POST = array(
	'existing_counts' => '{"buyer@example.com":2}',
);
vms_test_assert_same(array(), bvmgr_ticketing_claims_post_existing_counts(), 'Claims customer existing_counts should reject scalar JSON payloads at the POST boundary.');

vms_test_assert_same(
	array('buyer@example.com' => 2),
	bvmgr_ticketing_claims_parse_existing_counts_payload('{"buyer@example.com":2}'),
	'The shared existing_counts parser should continue to support legacy JSON object payloads when called directly.'
);

fwrite(STDOUT, "ticketing claims mutation request remediation: PASS\n");
