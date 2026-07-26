<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('VMS_SLOW_REQUEST_LOGGER_ENABLED', false);
define('VMS_SLOW_REQUEST_LOGGER_TIME_THRESHOLD', 999999.0);
define('VMS_SLOW_REQUEST_LOGGER_MEMORY_THRESHOLD', 1);
define('VMS_SLOW_REQUEST_LOGGER_MAX_BYTES', 1048576);
define('VMS_SLOW_REQUEST_LOGGER_PATH', sys_get_temp_dir() . '/vms-slow-request-logger-characterization-' . getmypid() . '.log');

function vms_test_slow_logger_assert(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
}

function vms_test_slow_logger_assert_same($expected, $actual, string $message): void
{
	vms_test_slow_logger_assert(
		$expected === $actual,
		$message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.'
	);
}

function vms_test_slow_logger_assert_contains(string $needle, string $haystack, string $message): void
{
	vms_test_slow_logger_assert(
		strpos($haystack, $needle) !== false,
		$message . ' Missing substring: ' . $needle
	);
}

function vms_test_slow_logger_assert_not_contains(string $needle, string $haystack, string $message): void
{
	vms_test_slow_logger_assert(
		strpos($haystack, $needle) === false,
		$message . ' Unexpected substring: ' . $needle
	);
}

function vms_test_slow_logger_find_matching_brace(string $code, int $openBracePos): int
{
	$depth = 0;
	$length = strlen($code);
	for ($index = $openBracePos; $index < $length; $index++) {
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

	throw new RuntimeException('Matching brace not found.');
}

function vms_test_slow_logger_extract_guarded_function(string $path, string $functionName): string
{
	$code = (string) file_get_contents($path);
	$marker = "if (!function_exists('" . $functionName . "'))";
	$start = strpos($code, $marker);
	if ($start === false) {
		throw new RuntimeException('Function wrapper not found: ' . $functionName);
	}

	$bracePos = strpos($code, '{', $start);
	if ($bracePos === false) {
		throw new RuntimeException('Wrapper brace not found: ' . $functionName);
	}

	$endPos = vms_test_slow_logger_find_matching_brace($code, $bracePos);
	return substr($code, $start, $endPos - $start + 1);
}

function sanitize_text_field($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$value = stripslashes((string) $value);
	$value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', '', $value);
	$value = str_replace(array("\r", "\n", "\t"), ' ', (string) $value);
	return trim((string) $value);
}

function sanitize_key($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$value = strtolower((string) $value);
	return (string) preg_replace('/[^a-z0-9_\-]/', '', $value);
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

function wp_parse_url(string $url, int $component = -1)
{
	if ($component === -1) {
		return parse_url($url);
	}

	return parse_url($url, $component);
}

function wp_json_encode($value)
{
	return json_encode($value);
}

function wp_salt(string $scheme = 'auth'): string
{
	return 'logger-test-salt-' . $scheme;
}

function get_option(string $key, $default = false)
{
	return $default;
}

function wp_mkdir_p(string $target): bool
{
	return is_dir($target) || mkdir($target, 0777, true);
}

function wp_is_writable(string $path): bool
{
	return is_writable($path);
}

function wp_delete_file(string $path): bool
{
	return @unlink($path);
}

function wp_delete_file_from_directory(string $path, string $directory): bool
{
	$realPath = realpath($path);
	$realDirectory = realpath($directory);
	if ($realPath === false || $realDirectory === false) {
		return false;
	}

	$normalizedPath = str_replace('\\', '/', $realPath);
	$normalizedDirectory = rtrim(str_replace('\\', '/', $realDirectory), '/') . '/';
	if (!str_starts_with($normalizedPath, $normalizedDirectory)) {
		return false;
	}

	return wp_delete_file($path);
}

function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	unset($hook, $callback, $priority, $acceptedArgs);
	return true;
}

function vms_test_slow_logger_capture(callable $callback): array
{
	$warnings = array();
	set_error_handler(
		static function (int $severity, string $message, string $file = '', int $line = 0) use (&$warnings): bool {
			$warnings[] = array(
				'severity' => $severity,
				'message' => $message,
				'file' => $file,
				'line' => $line,
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

function vms_test_slow_logger_assert_no_warnings(array $warnings, string $message): void
{
	vms_test_slow_logger_assert_same(0, count($warnings), $message . ' should not warn.');
}

function vms_test_slow_logger_reset_runtime(): void
{
	$_SERVER = array();
	$_REQUEST = array();
	unset($GLOBALS['vms_slow_request_logger']);
	if (file_exists(VMS_SLOW_REQUEST_LOGGER_PATH)) {
		unlink(VMS_SLOW_REQUEST_LOGGER_PATH);
	}
}

function vms_test_slow_logger_hash_ip(string $ip): string
{
	return substr(hash_hmac('sha256', strtolower($ip), wp_salt('auth')), 0, 12);
}

function vms_test_slow_logger_bootstrap_state(array $match): array
{
	return array(
		'matched' => true,
		'started_at' => isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime(true),
		'method' => strtoupper(vms_request_method()),
		'normalized_uri' => (string) ($match['normalized_uri'] ?? '/'),
		'scope' => (string) ($match['scope'] ?? ''),
		'reason' => (string) ($match['reason'] ?? ''),
		'user_agent_class' => vms_slow_request_logger_user_agent_class(),
		'ip_hash' => vms_slow_request_logger_source_ip_hash(),
		'response_status' => 0,
	);
}

function vms_test_slow_logger_match(array $server, array $request = array()): array
{
	vms_test_slow_logger_reset_runtime();
	$_SERVER = $server;
	$_REQUEST = $request;

	return vms_test_slow_logger_capture(
		static function (): array {
			return vms_slow_request_logger_match_request();
		}
	);
}

function vms_test_slow_logger_parse(array $server, array $request = array()): array
{
	vms_test_slow_logger_reset_runtime();
	$_SERVER = $server;
	$_REQUEST = $request;

	return vms_test_slow_logger_capture(
		static function (): array {
			return vms_slow_request_logger_parse_request_uri();
		}
	);
}

function vms_test_slow_logger_write_payload(array $server, array $request = array(), int $responseStatus = 202): array
{
	vms_test_slow_logger_reset_runtime();
	$_SERVER = $server;
	$_REQUEST = $request;

	$matchResult = vms_test_slow_logger_capture(
		static function (): array {
			return vms_slow_request_logger_match_request();
		}
	);
	vms_test_slow_logger_assert_no_warnings($matchResult['warnings'], 'Match request');
	$match = $matchResult['value'];
	vms_test_slow_logger_assert(!empty($match['matched']), 'Payload exercise should match the logger scope.');

	$GLOBALS['vms_slow_request_logger'] = vms_test_slow_logger_bootstrap_state($match);
	$GLOBALS['vms_slow_request_logger']['response_status'] = $responseStatus;

	$shutdownResult = vms_test_slow_logger_capture(
		static function (): void {
			vms_slow_request_logger_shutdown();
		}
	);
	vms_test_slow_logger_assert_no_warnings($shutdownResult['warnings'], 'Shutdown payload write');

	$lines = file(VMS_SLOW_REQUEST_LOGGER_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	vms_test_slow_logger_assert(is_array($lines) && count($lines) === 1, 'Logger payload should write exactly one JSON line.');
	$rawLine = (string) $lines[0];
	$entry = json_decode($rawLine, true);
	vms_test_slow_logger_assert(is_array($entry), 'Logger payload should decode as an associative array.');

	return array(
		'match' => $match,
		'raw_line' => $rawLine,
		'entry' => $entry,
	);
}

$pluginRoot = dirname(__DIR__);
$loggerPath = $pluginRoot . '/includes/core/slow-request-logger.php';
$runtimeGuardsPath = $pluginRoot . '/includes/runtime-guards.php';
$loggerSource = (string) file_get_contents($loggerPath);

foreach (array('vms_request_server_value', 'vms_request_method', 'vms_request_current_uri', 'vms_request_user_agent') as $helperName) {
	eval(vms_test_slow_logger_extract_guarded_function($runtimeGuardsPath, $helperName));
}

require $loggerPath;

try {
	vms_test_slow_logger_assert_contains("vms_request_server_value('REQUEST_URI')", $loggerSource, 'Logger should source REQUEST_URI through the shared server-value helper.');
	vms_test_slow_logger_assert_contains('vms_request_server_value($key)', $loggerSource, 'Logger should source proxy headers through the shared server-value helper.');
	vms_test_slow_logger_assert_contains("substr(vms_request_server_value('HTTP_USER_AGENT'), 0, 255)", $loggerSource, 'Logger should source the user agent through the shared server-value helper plus its local cap.');
	vms_test_slow_logger_assert_contains('strtoupper(vms_request_method())', $loggerSource, 'Logger should source the request method through the shared method helper.');
	vms_test_slow_logger_assert_contains("isset(\$_SERVER['REQUEST_TIME_FLOAT']) ? (float) \$_SERVER['REQUEST_TIME_FLOAT'] : microtime(true)", $loggerSource, 'Logger should preserve the direct REQUEST_TIME_FLOAT timing read.');
	vms_test_slow_logger_assert_not_contains('vms_request_current_uri(', $loggerSource, 'Logger should not adopt vms_request_current_uri().');
	vms_test_slow_logger_assert_not_contains('vms_request_remote_addr(', $loggerSource, 'Logger should not adopt vms_request_remote_addr().');
	vms_test_slow_logger_assert_not_contains('vms_request_user_agent(', $loggerSource, 'Logger should not adopt vms_request_user_agent().');

	preg_match_all('/\$_SERVER\[[^\]]+\]/', $loggerSource, $serverMatches);
	$uniqueServerMatches = array_values(array_unique($serverMatches[0]));
	vms_test_slow_logger_assert_same(
		array("\$_SERVER['REQUEST_TIME_FLOAT']"),
		$uniqueServerMatches,
		'Logger should retain only the accepted direct REQUEST_TIME_FLOAT read.'
	);

	$missingUri = vms_test_slow_logger_parse(array());
	vms_test_slow_logger_assert_no_warnings($missingUri['warnings'], 'Missing REQUEST_URI parse');
	vms_test_slow_logger_assert_same('/', $missingUri['value']['path'], 'Missing REQUEST_URI should fall back to /.');
	vms_test_slow_logger_assert_same(array(), $missingUri['value']['query'], 'Missing REQUEST_URI should produce an empty query array.');

	$missingMatch = vms_test_slow_logger_match(array());
	vms_test_slow_logger_assert_no_warnings($missingMatch['warnings'], 'Missing REQUEST_URI match');
	vms_test_slow_logger_assert_same(array('matched' => false), $missingMatch['value'], 'Missing REQUEST_URI should not produce a matched logger scope.');

	$adminPost = vms_test_slow_logger_match(
		array('REQUEST_URI' => '/wp-admin/admin-post.php?action=Foo_Bar&secret=1')
	);
	vms_test_slow_logger_assert_no_warnings($adminPost['warnings'], 'Ordinary admin-post match');
	vms_test_slow_logger_assert_same('/wp-admin/admin-post.php?action=foo_bar', $adminPost['value']['normalized_uri'], 'Admin-post normalization should preserve the existing action-only logger key.');

	vms_test_slow_logger_reset_runtime();
	$_SERVER['REQUEST_URI'] = 'wp-admin/admin-post.php?action=Foo_Bar';
	$missingSlashHelper = vms_request_current_uri('/');
	$missingSlashMatch = vms_test_slow_logger_capture(
		static function (): array {
			return vms_slow_request_logger_match_request();
		}
	);
	vms_test_slow_logger_assert_no_warnings($missingSlashMatch['warnings'], 'Missing-leading-slash match');
	vms_test_slow_logger_assert_same('/wp-admin/admin-post.php?action=Foo_Bar', $missingSlashHelper, 'The shared current-URI helper would add a leading slash.');
	vms_test_slow_logger_assert_same(array('matched' => false), $missingSlashMatch['value'], 'Logger should preserve its unmatched missing-leading-slash behavior.');

	vms_test_slow_logger_reset_runtime();
	$_SERVER['REQUEST_URI'] = "/wp-admin/admin-post.php?action=Foo\x00_Bar";
	$_REQUEST['action'] = "Foo\x00_Bar";
	$controlByteHelper = vms_request_current_uri('/');
	$controlByteMatch = vms_test_slow_logger_capture(
		static function (): array {
			return vms_slow_request_logger_match_request();
		}
	);
	vms_test_slow_logger_assert_no_warnings($controlByteMatch['warnings'], 'Control-byte match');
	vms_test_slow_logger_assert_same('/wp-admin/admin-post.php?action=Foo_Bar', $controlByteHelper, 'The shared current-URI helper would strip control bytes.');
	vms_test_slow_logger_assert_same('/wp-admin/admin-post.php?action=foo__bar', $controlByteMatch['value']['normalized_uri'], 'Logger should preserve its existing control-byte parsing semantics.');

	vms_test_slow_logger_reset_runtime();
	$_SERVER['REQUEST_URI'] = '/' . str_repeat('a', 2055) . '?key=secret';
	$longParse = vms_test_slow_logger_capture(
		static function (): array {
			return vms_slow_request_logger_parse_request_uri();
		}
	);
	$longHelper = vms_request_current_uri('/');
	vms_test_slow_logger_assert_no_warnings($longParse['warnings'], 'Long URI parse');
	vms_test_slow_logger_assert_same(2056, strlen((string) $longParse['value']['path']), 'Logger should preserve over-2048 request paths for its local parser.');
	vms_test_slow_logger_assert_same(2048, strlen($longHelper), 'The shared current-URI helper would cap the URI at 2048 characters.');

	$orderReceived = vms_test_slow_logger_match(
		array('REQUEST_URI' => '/checkout/order-received/12345/?key=wc_order_sensitive')
	);
	vms_test_slow_logger_assert_no_warnings($orderReceived['warnings'], 'Order-received match');
	vms_test_slow_logger_assert_same('/checkout/order-received/{order_id}/?key=[redacted]', $orderReceived['value']['normalized_uri'], 'Order-received normalization should preserve its redacted logger key.');

	$wallet = vms_test_slow_logger_match(
		array('REQUEST_URI' => '/tickets/?tec-tickets-wallet-plus-pdf=1&attendee_id=99&security_code=shh&foo=bar')
	);
	vms_test_slow_logger_assert_no_warnings($wallet['warnings'], 'Wallet PDF match');
	vms_test_slow_logger_assert_same('/tickets?tec-tickets-wallet-plus-pdf=1&attendee_id={id}&security_code=[redacted]', $wallet['value']['normalized_uri'], 'Wallet normalization should preserve attendee and security-code redaction.');

	$loopback = vms_test_slow_logger_match(
		array(
			'REQUEST_URI' => '/wp-cron.php?doing_wp_cron=12345&action=Do_Cron&nonce=abc',
			'HTTP_USER_AGENT' => 'WordPress/7.0; https://example.test',
		)
	);
	vms_test_slow_logger_assert_no_warnings($loopback['warnings'], 'Loopback match');
	vms_test_slow_logger_assert_same('/wp-cron.php?action=do_cron&doing_wp_cron=[redacted]', $loopback['value']['normalized_uri'], 'Loopback normalization should preserve action sanitization and cron redaction.');

	$wcAjax = vms_test_slow_logger_match(
		array('REQUEST_URI' => '/?wc-ajax=FoO_Bar')
	);
	vms_test_slow_logger_assert_no_warnings($wcAjax['warnings'], 'wc-ajax match');
	vms_test_slow_logger_assert_same('/?wc-ajax=foo_bar', $wcAjax['value']['normalized_uri'], 'wc-ajax normalization should preserve its sanitized action key.');

	$cartContext = vms_test_slow_logger_match(
		array('REQUEST_URI' => '/wp-admin/admin-ajax.php?action=vms_ticketing_v2_cart_context')
	);
	vms_test_slow_logger_assert_no_warnings($cartContext['warnings'], 'Cart-context match');
	vms_test_slow_logger_assert_same('/wp-admin/admin-ajax.php?action=vms_ticketing_v2_cart_context', $cartContext['value']['normalized_uri'], 'Cart-context normalization should preserve the fixed diagnostic key.');

	$remoteOnlyIp = vms_test_slow_logger_capture(
		static function (): string {
			$_SERVER = array('REMOTE_ADDR' => '203.0.113.7');
			return vms_slow_request_logger_source_ip_hash();
		}
	);
	vms_test_slow_logger_assert_no_warnings($remoteOnlyIp['warnings'], 'Remote-only IP hash');
	vms_test_slow_logger_assert_same(vms_test_slow_logger_hash_ip('203.0.113.7'), $remoteOnlyIp['value'], 'Remote-only hashing should preserve the 12-character auth-salt HMAC.');

	$xffIp = vms_test_slow_logger_capture(
		static function (): string {
			$_SERVER = array(
				'HTTP_X_FORWARDED_FOR' => ' 2001:DB8::A , 203.0.113.7',
				'REMOTE_ADDR' => '203.0.113.7',
			);
			return vms_slow_request_logger_source_ip_hash();
		}
	);
	vms_test_slow_logger_assert_no_warnings($xffIp['warnings'], 'XFF IP hash');
	vms_test_slow_logger_assert_same(vms_test_slow_logger_hash_ip('2001:db8::a'), $xffIp['value'], 'XFF hashing should preserve first-element trimming and lowercasing.');

	$cfIp = vms_test_slow_logger_capture(
		static function (): string {
			$_SERVER = array(
				'HTTP_CF_CONNECTING_IP' => '198.51.100.9',
				'HTTP_X_FORWARDED_FOR' => '198.51.100.3, 203.0.113.7',
				'REMOTE_ADDR' => '203.0.113.7',
			);
			return vms_slow_request_logger_source_ip_hash();
		}
	);
	vms_test_slow_logger_assert_no_warnings($cfIp['warnings'], 'CF-precedence IP hash');
	vms_test_slow_logger_assert_same(vms_test_slow_logger_hash_ip('198.51.100.9'), $cfIp['value'], 'CF should preserve precedence over XFF and REMOTE_ADDR.');

	$malformedCfIp = vms_test_slow_logger_capture(
		static function (): string {
			$_SERVER = array(
				'HTTP_CF_CONNECTING_IP' => array('198.51.100.10'),
				'REMOTE_ADDR' => '203.0.113.7',
			);
			return vms_slow_request_logger_source_ip_hash();
		}
	);
	vms_test_slow_logger_assert_no_warnings($malformedCfIp['warnings'], 'Malformed CF IP hash');
	vms_test_slow_logger_assert_same(vms_test_slow_logger_hash_ip('203.0.113.7'), $malformedCfIp['value'], 'Malformed CF arrays should fail closed to the next valid source.');

	$missingIp = vms_test_slow_logger_capture(
		static function (): string {
			$_SERVER = array();
			return vms_slow_request_logger_source_ip_hash();
		}
	);
	vms_test_slow_logger_assert_no_warnings($missingIp['warnings'], 'Missing IP hash');
	vms_test_slow_logger_assert_same('', $missingIp['value'], 'Missing IP inputs should produce an empty hash.');

	$ordinaryUa = vms_test_slow_logger_capture(
		static function (): string {
			$_SERVER = array('HTTP_USER_AGENT' => 'Googlebot/2.1');
			return vms_slow_request_logger_user_agent_class();
		}
	);
	vms_test_slow_logger_assert_no_warnings($ordinaryUa['warnings'], 'Ordinary UA classification');
	vms_test_slow_logger_assert_same('Googlebot', $ordinaryUa['value'], 'UA classification should preserve Googlebot detection.');

	$longUa = vms_test_slow_logger_capture(
		static function (): int {
			$_SERVER = array('HTTP_USER_AGENT' => str_repeat('A', 300));
			return strlen(vms_slow_request_logger_user_agent());
		}
	);
	vms_test_slow_logger_assert_no_warnings($longUa['warnings'], 'Long UA cap');
	vms_test_slow_logger_assert_same(255, $longUa['value'], 'UA capture should preserve the 255-character cap.');

	$malformedUa = vms_test_slow_logger_capture(
		static function (): array {
			$_SERVER = array('HTTP_USER_AGENT' => array('Bot/1.0'));
			return array(
				'raw' => vms_slow_request_logger_user_agent(),
				'class' => vms_slow_request_logger_user_agent_class(),
			);
		}
	);
	vms_test_slow_logger_assert_no_warnings($malformedUa['warnings'], 'Malformed UA handling');
	vms_test_slow_logger_assert_same('', $malformedUa['value']['raw'], 'Malformed UA arrays should fail closed to an empty diagnostic value.');
	vms_test_slow_logger_assert_same('browser', $malformedUa['value']['class'], 'Malformed UA arrays should preserve the default browser class.');

	vms_test_slow_logger_reset_runtime();
	$_SERVER['HTTP_USER_AGENT'] = "  Tablet\tBrowser/1.0\x00  ";
	$whitespaceUa = vms_test_slow_logger_capture(
		static function (): array {
			return array(
				'logger' => vms_slow_request_logger_user_agent(),
				'helper_server' => vms_request_server_value('HTTP_USER_AGENT'),
				'helper_user_agent' => vms_request_user_agent(),
			);
		}
	);
	vms_test_slow_logger_assert_no_warnings($whitespaceUa['warnings'], 'Whitespace UA handling');
	vms_test_slow_logger_assert_same("Tablet\tBrowser/1.0", $whitespaceUa['value']['logger'], 'Logger UA capture should preserve helper-backed trimming without sanitize_text_field normalization.');
	vms_test_slow_logger_assert_same($whitespaceUa['value']['helper_server'], $whitespaceUa['value']['logger'], 'Logger UA capture should align with vms_request_server_value().');
	vms_test_slow_logger_assert_same('Tablet Browser/1.0', $whitespaceUa['value']['helper_user_agent'], 'The shared user-agent helper would additionally sanitize control whitespace.');
	vms_test_slow_logger_assert(
		$whitespaceUa['value']['logger'] !== $whitespaceUa['value']['helper_user_agent'],
		'Logger UA capture should remain distinct from vms_request_user_agent() semantics.'
	);

	$missingMethod = vms_test_slow_logger_capture(
		static function (): string {
			$_SERVER = array();
			$state = vms_test_slow_logger_bootstrap_state(array('normalized_uri' => '/', 'scope' => '', 'reason' => ''));
			return $state['method'];
		}
	);
	vms_test_slow_logger_assert_no_warnings($missingMethod['warnings'], 'Missing request method');
	vms_test_slow_logger_assert_same('GET', $missingMethod['value'], 'Missing request methods should preserve the GET fallback.');

	$mixedMethod = vms_test_slow_logger_capture(
		static function (): string {
			$_SERVER = array('REQUEST_METHOD' => 'pOsT');
			$state = vms_test_slow_logger_bootstrap_state(array('normalized_uri' => '/', 'scope' => '', 'reason' => ''));
			return $state['method'];
		}
	);
	vms_test_slow_logger_assert_no_warnings($mixedMethod['warnings'], 'Mixed-case request method');
	vms_test_slow_logger_assert_same('POST', $mixedMethod['value'], 'Mixed-case request methods should preserve uppercase diagnostic persistence.');

	$malformedMethod = vms_test_slow_logger_capture(
		static function (): string {
			$_SERVER = array('REQUEST_METHOD' => array('POST'));
			$state = vms_test_slow_logger_bootstrap_state(array('normalized_uri' => '/', 'scope' => '', 'reason' => ''));
			return $state['method'];
		}
	);
	vms_test_slow_logger_assert_no_warnings($malformedMethod['warnings'], 'Malformed request method');
	vms_test_slow_logger_assert_same('GET', $malformedMethod['value'], 'Malformed request-method arrays should fail closed to GET.');

	$timingOnly = vms_test_slow_logger_capture(
		static function (): array {
			$_SERVER = array('REQUEST_TIME_FLOAT' => '123.456789');
			return vms_test_slow_logger_bootstrap_state(array('normalized_uri' => '/', 'scope' => '', 'reason' => ''));
		}
	);
	vms_test_slow_logger_assert_no_warnings($timingOnly['warnings'], 'Timing-only REQUEST_TIME_FLOAT boundary');
	vms_test_slow_logger_assert_same(123.456789, $timingOnly['value']['started_at'], 'REQUEST_TIME_FLOAT should remain a direct timing-only bootstrap value.');
	vms_test_slow_logger_assert_not_contains('request_id', $loggerSource, 'Logger should not introduce request identifiers in this slice.');

	$orderPayload = vms_test_slow_logger_write_payload(
		array(
			'REQUEST_URI' => '/checkout/order-received/12345/?key=wc_order_sensitive',
			'REQUEST_METHOD' => 'get',
			'REMOTE_ADDR' => '203.0.113.7',
			'REQUEST_TIME_FLOAT' => (string) (microtime(true) - 0.5),
		)
	);
	vms_test_slow_logger_assert_same('/checkout/order-received/{order_id}/?key=[redacted]', $orderPayload['entry']['normalized_uri'], 'Order payload should persist only the redacted normalized URI.');
	vms_test_slow_logger_assert_not_contains('wc_order_sensitive', $orderPayload['raw_line'], 'Order payload should not persist the raw order key.');

	$loopbackPayload = vms_test_slow_logger_write_payload(
		array(
			'REQUEST_URI' => '/wp-cron.php?doing_wp_cron=12345&action=Do_Cron',
			'HTTP_USER_AGENT' => 'WordPress/7.0; https://example.test',
			'REQUEST_TIME_FLOAT' => (string) (microtime(true) - 0.5),
		)
	);
	vms_test_slow_logger_assert_same('/wp-cron.php?action=do_cron&doing_wp_cron=[redacted]', $loopbackPayload['entry']['normalized_uri'], 'Loopback payload should persist only the redacted normalized URI.');
	vms_test_slow_logger_assert_not_contains('doing_wp_cron=12345', $loopbackPayload['raw_line'], 'Loopback payload should not persist the raw doing_wp_cron value.');
	vms_test_slow_logger_assert_contains('doing_wp_cron=[redacted]', $loopbackPayload['raw_line'], 'Loopback payload should preserve the redacted doing_wp_cron marker.');

	$walletPayload = vms_test_slow_logger_write_payload(
		array(
			'REQUEST_URI' => '/tickets/?tec-tickets-wallet-plus-pdf=1&attendee_id=99&security_code=shh&foo=bar',
			'REQUEST_METHOD' => 'pOsT',
			'HTTP_CF_CONNECTING_IP' => '203.0.113.7',
			'HTTP_USER_AGENT' => 'Googlebot/2.1',
			'REQUEST_TIME_FLOAT' => '123.456789',
		)
	);
	$expectedPayloadKeys = array(
		'timestamp',
		'method',
		'normalized_uri',
		'scope',
		'reason',
		'trigger',
		'elapsed_seconds',
		'peak_memory_bytes',
		'peak_memory_mb',
		'memory_limit',
		'response_status',
		'fatal_error',
		'user_agent_class',
		'ip_hash',
	);
	$actualPayloadKeys = array_keys($walletPayload['entry']);
	sort($expectedPayloadKeys);
	sort($actualPayloadKeys);
	vms_test_slow_logger_assert_same($expectedPayloadKeys, $actualPayloadKeys, 'Wallet payload should preserve the existing payload keys without additions.');
	vms_test_slow_logger_assert_same('/tickets?tec-tickets-wallet-plus-pdf=1&attendee_id={id}&security_code=[redacted]', $walletPayload['entry']['normalized_uri'], 'Wallet payload should preserve the redacted normalized URI.');
	vms_test_slow_logger_assert_same('POST', $walletPayload['entry']['method'], 'Wallet payload should preserve the normalized diagnostic method.');
	vms_test_slow_logger_assert_same('Googlebot', $walletPayload['entry']['user_agent_class'], 'Wallet payload should preserve UA-class persistence only.');
	vms_test_slow_logger_assert_same(vms_test_slow_logger_hash_ip('203.0.113.7'), $walletPayload['entry']['ip_hash'], 'Wallet payload should preserve the 12-character auth-salt IP hash.');
	vms_test_slow_logger_assert_same(202, $walletPayload['entry']['response_status'], 'Wallet payload should preserve the response status field.');
	vms_test_slow_logger_assert(
		is_numeric($walletPayload['entry']['elapsed_seconds']) && (float) $walletPayload['entry']['elapsed_seconds'] >= 0.0,
		'Wallet payload should preserve a numeric elapsed-seconds diagnostic field.'
	);
	vms_test_slow_logger_assert_not_contains('security_code=shh', $walletPayload['raw_line'], 'Wallet payload should not persist the raw wallet security code.');
	vms_test_slow_logger_assert_not_contains('attendee_id=99', $walletPayload['raw_line'], 'Wallet payload should not persist the raw wallet attendee ID.');
	vms_test_slow_logger_assert_not_contains('203.0.113.7', $walletPayload['raw_line'], 'Wallet payload should not persist the raw source IP.');
	vms_test_slow_logger_assert_not_contains('Googlebot/2.1', $walletPayload['raw_line'], 'Wallet payload should not persist the raw user agent.');
	vms_test_slow_logger_assert_not_contains('123.456789', $walletPayload['raw_line'], 'Wallet payload should not persist the raw REQUEST_TIME_FLOAT input.');
	vms_test_slow_logger_assert_not_contains('request_time_float', $walletPayload['raw_line'], 'Wallet payload should not add a raw timing field.');
	vms_test_slow_logger_assert_not_contains('request_id', $walletPayload['raw_line'], 'Wallet payload should not add a request identifier.');
	vms_test_slow_logger_assert_contains('security_code=[redacted]', $walletPayload['raw_line'], 'Wallet payload should preserve the redacted security-code marker.');
	vms_test_slow_logger_assert_contains('attendee_id={id}', $walletPayload['raw_line'], 'Wallet payload should preserve the redacted attendee marker.');
} finally {
	vms_test_slow_logger_reset_runtime();
}

echo "Slow Request Logger request input characterization OK.\n";
