<?php
declare(strict_types=1);

function vms_test_fail(string $message): void
{
	throw new RuntimeException($message);
}

function vms_test_assert_true(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	vms_test_fail($message);
}

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function vms_test_assert_same($expected, $actual, string $message): void
{
	if ($expected === $actual) {
		return;
	}

	vms_test_fail(
		$message
		. "\nExpected: " . var_export($expected, true)
		. "\nActual: " . var_export($actual, true)
	);
}

function vms_test_read_file(string $path): string
{
	$contents = file_get_contents($path);
	if (!is_string($contents) || $contents === '') {
		vms_test_fail('Failed to read source file: ' . $path);
	}

	return $contents;
}

function vms_test_find_matching_brace(string $code, int $open_brace_pos): int
{
	$depth = 0;
	$length = strlen($code);
	for ($index = $open_brace_pos; $index < $length; $index++) {
		$char = $code[$index];
		if ($char === '{') {
			$depth++;
			continue;
		}

		if ($char === '}') {
			$depth--;
			if ($depth === 0) {
				return $index;
			}
		}
	}

	vms_test_fail('Matching brace not found.');
}

function vms_test_extract_guarded_function(string $source, string $function_name): string
{
	$marker = "if (!function_exists('" . $function_name . "'))";
	$start = strpos($source, $marker);
	if ($start === false) {
		vms_test_fail('Function wrapper not found: ' . $function_name);
	}

	$brace_pos = strpos($source, '{', $start);
	if ($brace_pos === false) {
		vms_test_fail('Wrapper brace not found: ' . $function_name);
	}

	$end_pos = vms_test_find_matching_brace($source, $brace_pos);
	return substr($source, $start, $end_pos - $start + 1);
}

/**
 * @return array{value:mixed,warnings:array<int,array{severity:int,message:string}>}
 */
function vms_test_capture(callable $callback): array
{
	$warnings = array();
	set_error_handler(
		static function (int $severity, string $message) use (&$warnings): bool {
			$warnings[] = array(
				'severity' => $severity,
				'message' => $message,
			);
			return true;
		}
	);

	try {
		$value = $callback();
	} finally {
		restore_error_handler();
	}

	return array(
		'value' => $value,
		'warnings' => $warnings,
	);
}

function vms_test_assert_no_warnings(array $warnings, string $message): void
{
	vms_test_assert_same(0, count($warnings), $message . ' should not emit warnings.');
}

function _get_component_from_parsed_url_array($parts, int $component = -1)
{
	if ($component === -1) {
		return $parts;
	}

	$map = array(
		PHP_URL_SCHEME => 'scheme',
		PHP_URL_HOST => 'host',
		PHP_URL_PORT => 'port',
		PHP_URL_USER => 'user',
		PHP_URL_PASS => 'pass',
		PHP_URL_PATH => 'path',
		PHP_URL_QUERY => 'query',
		PHP_URL_FRAGMENT => 'fragment',
	);

	if (!array_key_exists($component, $map)) {
		return false;
	}

	$key = $map[$component];
	return is_array($parts) && array_key_exists($key, $parts) ? $parts[$key] : null;
}

function wp_parse_url($url, int $component = -1)
{
	$to_unset = array();
	$url = (string) $url;

	if (str_starts_with($url, '//')) {
		$to_unset[] = 'scheme';
		$url = 'placeholder:' . $url;
	} elseif (str_starts_with($url, '/')) {
		$to_unset[] = 'scheme';
		$to_unset[] = 'host';
		$url = 'placeholder://placeholder' . $url;
	}

	$parts = parse_url($url);
	if ($parts === false) {
		return false;
	}

	foreach ($to_unset as $key) {
		unset($parts[$key]);
	}

	return _get_component_from_parsed_url_array($parts, $component);
}

function wp_unslash($value)
{
	if (is_array($value)) {
		return array_map('wp_unslash', $value);
	}

	if (is_string($value)) {
		return stripslashes($value);
	}

	return $value;
}

function vms_request_server_value(string $key): string
{
	if (!isset($_SERVER[$key]) || !is_scalar($_SERVER[$key])) {
		return '';
	}

	$value = wp_unslash($_SERVER[$key]);
	if (!is_scalar($value)) {
		return '';
	}

	return trim((string) $value);
}

try {
	$plugin_root = dirname(__DIR__);
	$source_path = $plugin_root . '/includes/core/slow-request-logger.php';
	$source = vms_test_read_file($source_path);

	vms_test_assert_true(
		strpos($source, 'wp_parse_url($request_uri, PHP_URL_PATH)') !== false,
		'Slow Request Logger should use wp_parse_url() for the request path component.'
	);
	vms_test_assert_true(
		strpos($source, 'wp_parse_url($request_uri, PHP_URL_QUERY)') !== false,
		'Slow Request Logger should use wp_parse_url() for the request query component.'
	);
	vms_test_assert_true(
		preg_match('/(?<!wp_)parse_url\s*\(/', $source) !== 1,
		'Slow Request Logger should no longer contain native parse_url() calls.'
	);

	eval(vms_test_extract_guarded_function($source, 'vms_slow_request_logger_parse_request_uri'));

	$comparison_cases = array(
		'absolute_https' => 'https://example.com/path?x=1#frag',
		'http_with_port' => 'http://example.com:8080/path?x=1#frag',
		'protocol_relative' => '//example.com/path?x=1',
		'relative_path' => '/relative/path?x=1',
		'ipv6' => 'https://[2001:db8::1]:8443/path?x=1',
		'user_info' => 'https://user:pass@example.com:8443/path?x=1',
		'malformed' => 'http://example.com:bad/path?x=1',
		'empty' => '',
	);

	foreach ($comparison_cases as $label => $url) {
		foreach (array(PHP_URL_PATH => 'path', PHP_URL_QUERY => 'query') as $component => $component_label) {
			$native = vms_test_capture(
				static function () use ($url, $component) {
					return parse_url($url, $component);
				}
			);
			$wordpress = vms_test_capture(
				static function () use ($url, $component) {
					return wp_parse_url($url, $component);
				}
			);

			vms_test_assert_same(
				$native['value'],
				$wordpress['value'],
				$label . ' should preserve the ' . $component_label . ' component when routed through wp_parse_url().'
			);
			vms_test_assert_no_warnings($native['warnings'], $label . ' native parse_url() comparison');
			vms_test_assert_no_warnings($wordpress['warnings'], $label . ' wp_parse_url() comparison');
		}
	}

	$_SERVER = array(
		'REQUEST_URI' => '/relative/path?x=1#frag',
	);
	$_REQUEST = array();
	$relative = vms_test_capture(
		static function (): array {
			return vms_slow_request_logger_parse_request_uri();
		}
	);
	vms_test_assert_no_warnings($relative['warnings'], 'Relative request URI parse');
	vms_test_assert_same('/relative/path', $relative['value']['path'], 'Relative request URIs should preserve the existing parsed path.');
	vms_test_assert_same(array('x' => '1'), $relative['value']['query'], 'Relative request URIs should preserve the existing parsed query.');

	$_SERVER = array(
		'REQUEST_URI' => '/wp-admin/admin-post.php',
	);
	$_REQUEST = array(
		'action' => 'foo-bar',
	);
	$fallback_action = vms_test_capture(
		static function (): array {
			return vms_slow_request_logger_parse_request_uri();
		}
	);
	vms_test_assert_no_warnings($fallback_action['warnings'], 'Fallback action parse');
	vms_test_assert_same(
		array('action' => 'foo-bar'),
		$fallback_action['value']['query'],
		'Missing query actions should still fall back to $_REQUEST["action"].'
	);

	$_SERVER = array(
		'REQUEST_URI' => 'https://user:pass@example.com:8443/checkout?key=abc#frag',
	);
	$_REQUEST = array();
	$credential_case = vms_test_capture(
		static function (): array {
			return vms_slow_request_logger_parse_request_uri();
		}
	);
	vms_test_assert_no_warnings($credential_case['warnings'], 'Credential-bearing URL parse');
	vms_test_assert_same('/checkout', $credential_case['value']['path'], 'Credential-bearing URLs should still expose only the parsed path.');
	vms_test_assert_same(array('key' => 'abc'), $credential_case['value']['query'], 'Credential-bearing URLs should still expose only the parsed query.');
	vms_test_assert_true(
		!array_key_exists('host', $credential_case['value']) && !array_key_exists('user', $credential_case['value']) && !array_key_exists('pass', $credential_case['value']),
		'Credential-bearing URLs should not expose host or credential components through the parsed request array.'
	);

	$_SERVER = array(
		'REQUEST_URI' => 'http://example.com:bad/path?x=1',
	);
	$_REQUEST = array();
	$malformed = vms_test_capture(
		static function (): array {
			return vms_slow_request_logger_parse_request_uri();
		}
	);
	vms_test_assert_no_warnings($malformed['warnings'], 'Malformed request URI parse');
	vms_test_assert_same('/', $malformed['value']['path'], 'Malformed request URIs should still fall back to the root path.');
	vms_test_assert_same(array(), $malformed['value']['query'], 'Malformed request URIs should still fall back to an empty query array.');

	$_SERVER = array(
		'REQUEST_URI' => '',
	);
	$_REQUEST = array();
	$empty = vms_test_capture(
		static function (): array {
			return vms_slow_request_logger_parse_request_uri();
		}
	);
	vms_test_assert_no_warnings($empty['warnings'], 'Empty request URI parse');
	vms_test_assert_same('/', $empty['value']['path'], 'Empty request URIs should still normalize to the root path.');
	vms_test_assert_same(array(), $empty['value']['query'], 'Empty request URIs should still normalize to an empty query array.');

	echo "slow-request logger URL helper remediation: PASS\n";
} catch (Throwable $throwable) {
	fwrite(STDERR, $throwable->getMessage() . "\n");
	exit(1);
}
