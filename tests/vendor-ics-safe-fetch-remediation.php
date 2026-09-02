<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}
if (!defined('MB_IN_BYTES')) {
	define('MB_IN_BYTES', 1024 * 1024);
}

final class WP_Error
{
	public function __construct(private string $message)
	{
	}

	public function get_error_message(): string
	{
		return $this->message;
	}
}

function vms_test_fail(string $message): void
{
	throw new RuntimeException($message);
}

function vms_test_assert_true(bool $condition, string $message): void
{
	if (!$condition) {
		vms_test_fail($message);
	}
}

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function vms_test_assert_same($expected, $actual, string $message): void
{
	if ($expected !== $actual) {
		vms_test_fail(
			$message
			. "\nExpected: " . var_export($expected, true)
			. "\nActual: " . var_export($actual, true)
		);
	}
}

function vms_test_reset(string $url): void
{
	$GLOBALS['vms_test_ics_url'] = $url;
	$GLOBALS['vms_test_validated_url'] = false;
	$GLOBALS['vms_test_validation_calls'] = array();
	$GLOBALS['vms_test_safe_get_calls'] = array();
	$GLOBALS['vms_test_remote_response'] = new WP_Error('Remote request was not configured.');
	$GLOBALS['vms_test_meta_updates'] = array();
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	unset($post_id, $single);
	return $key === '_vms_ics_url' ? (string) $GLOBALS['vms_test_ics_url'] : '';
}

function __(string $text, string $domain = 'default'): string
{
	unset($domain);
	return $text;
}

function wp_http_validate_url(string $url)
{
	$GLOBALS['vms_test_validation_calls'][] = $url;
	return $GLOBALS['vms_test_validated_url'];
}

function wp_safe_remote_get(string $url, array $args = array())
{
	$GLOBALS['vms_test_safe_get_calls'][] = array(
		'url' => $url,
		'args' => $args,
	);
	return $GLOBALS['vms_test_remote_response'];
}

function is_wp_error($value): bool
{
	return $value instanceof WP_Error;
}

function wp_remote_retrieve_response_code($response): int
{
	return is_array($response) ? (int) ($response['code'] ?? 0) : 0;
}

function wp_remote_retrieve_body($response): string
{
	return is_array($response) ? (string) ($response['body'] ?? '') : '';
}

function update_post_meta(int $post_id, string $key, $value): bool
{
	$GLOBALS['vms_test_meta_updates'][] = array($post_id, $key, $value);
	return true;
}

try {
	require_once dirname(__DIR__) . '/includes/integrations/vendor-ics-sync.php';

	vms_test_reset('http://127.0.0.1/private.ics');
	$private_result = bvmgr_vendor_ics_sync_now(17, array('2026-08-08'));
	vms_test_assert_same(false, $private_result['ok'] ?? null, 'Unsafe ICS URLs must fail closed.');
	vms_test_assert_same(
		array('http://127.0.0.1/private.ics'),
		$GLOBALS['vms_test_validation_calls'],
		'The stored ICS URL must pass through WordPress URL safety validation.'
	);
	vms_test_assert_same(array(), $GLOBALS['vms_test_safe_get_calls'], 'Rejected URLs must not reach the HTTP client.');

	vms_test_reset('https://calendar.example.test/feed.ics');
	$GLOBALS['vms_test_validated_url'] = 'https://calendar.example.test/feed.ics';
	$GLOBALS['vms_test_remote_response'] = new WP_Error('Connection refused.');
	$remote_error_result = bvmgr_vendor_ics_sync_now(17, array('2026-08-08'));
	vms_test_assert_same(false, $remote_error_result['ok'] ?? null, 'Remote request failures must remain non-mutating failures.');
	vms_test_assert_same('Connection refused.', $remote_error_result['error'] ?? null, 'Remote request error reporting changed unexpectedly.');
	vms_test_assert_same(1, count($GLOBALS['vms_test_safe_get_calls']), 'A validated ICS URL should make one safe request.');
	$call = $GLOBALS['vms_test_safe_get_calls'][0];
	vms_test_assert_same('https://calendar.example.test/feed.ics', $call['url'], 'The validated URL must be used for the request.');
	vms_test_assert_same(15, $call['args']['timeout'] ?? null, 'ICS timeout changed unexpectedly.');
	vms_test_assert_same(3, $call['args']['redirection'] ?? null, 'ICS redirect limit changed unexpectedly.');
	vms_test_assert_same(
		(2 * MB_IN_BYTES) + 1,
		$call['args']['limit_response_size'] ?? null,
		'The request must cap the response while retaining one byte for overflow detection.'
	);
	vms_test_assert_same('text/calendar', $call['args']['headers']['Accept'] ?? null, 'ICS Accept header changed unexpectedly.');
	vms_test_assert_same(array(), $GLOBALS['vms_test_meta_updates'], 'Failed safe requests must not mutate availability metadata.');

	vms_test_reset('https://calendar.example.test/large.ics');
	$GLOBALS['vms_test_validated_url'] = 'https://calendar.example.test/large.ics';
	$GLOBALS['vms_test_remote_response'] = array(
		'code' => 200,
		'body' => str_repeat('A', (2 * MB_IN_BYTES) + 1),
	);
	$oversized_result = bvmgr_vendor_ics_sync_now(17, array('2026-08-08'));
	vms_test_assert_same(false, $oversized_result['ok'] ?? null, 'Oversized ICS responses must fail closed.');
	vms_test_assert_true(
		str_contains((string) ($oversized_result['error'] ?? ''), 'too large'),
		'Oversized ICS responses should return the bounded size error.'
	);
	vms_test_assert_same(array(), $GLOBALS['vms_test_meta_updates'], 'Oversized ICS responses must not mutate availability metadata.');

	fwrite(STDOUT, "vendor ICS safe fetch remediation: PASS\n");
} catch (Throwable $throwable) {
	fwrite(STDERR, "vendor ICS safe fetch remediation: FAIL\n" . $throwable->getMessage() . "\n");
	exit(1);
}
