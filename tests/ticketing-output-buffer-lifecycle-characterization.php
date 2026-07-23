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

function vms_test_assert_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert_true(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function vms_test_assert_not_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert_true(strpos($haystack, $needle) === false, $message . "\nUnexpected: " . $needle);
}

function vms_test_read_file(string $path): string
{
	$contents = file_get_contents($path);
	if (!is_string($contents) || $contents === '') {
		vms_test_fail('Failed to read source file: ' . $path);
	}

	return $contents;
}

function vms_test_extract_function(string $source, string $name): string
{
	$needle = 'function ' . $name . '(';
	$start = strpos($source, $needle);
	if ($start === false) {
		vms_test_fail('Unable to locate function ' . $name . '.');
	}

	$brace = strpos($source, '{', $start);
	if ($brace === false) {
		vms_test_fail('Unable to locate opening brace for ' . $name . '.');
	}

	$depth = 1;
	$length = strlen($source);
	$inSingleQuote = false;
	$inDoubleQuote = false;
	$inLineComment = false;
	$inBlockComment = false;
	for ($i = $brace + 1; $i < $length; $i++) {
		$char = $source[$i];
		$nextChar = ($i + 1 < $length) ? $source[$i + 1] : '';
		$prevChar = ($i > 0) ? $source[$i - 1] : '';

		if ($inLineComment) {
			if ($char === "\n") {
				$inLineComment = false;
			}
			continue;
		}
		if ($inBlockComment) {
			if ($char === '*' && $nextChar === '/') {
				$inBlockComment = false;
				$i++;
			}
			continue;
		}
		if ($inSingleQuote) {
			if ($char === "'" && $prevChar !== '\\') {
				$inSingleQuote = false;
			}
			continue;
		}
		if ($inDoubleQuote) {
			if ($char === '"' && $prevChar !== '\\') {
				$inDoubleQuote = false;
			}
			continue;
		}

		if ($char === '/' && $nextChar === '/') {
			$inLineComment = true;
			$i++;
			continue;
		}
		if ($char === '/' && $nextChar === '*') {
			$inBlockComment = true;
			$i++;
			continue;
		}
		if ($char === "'") {
			$inSingleQuote = true;
			continue;
		}
		if ($char === '"') {
			$inDoubleQuote = true;
			continue;
		}

		if ($char === '{') {
			$depth++;
			continue;
		}
		if ($char === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $start, ($i - $start) + 1);
			}
		}
	}

	vms_test_fail('Unable to locate closing brace for ' . $name . '.');
}

function vms_test_find_action_priority(string $source, string $hook, string $callback): ?int
{
	$pattern = sprintf(
		'~add_action\(\s*[\'"]%s[\'"]\s*,\s*[\'"]%s[\'"](?:\s*,\s*(\d+))?~',
		preg_quote($hook, '~'),
		preg_quote($callback, '~')
	);
	if (!preg_match($pattern, $source, $matches)) {
		return null;
	}

	if (!isset($matches[1]) || $matches[1] === '') {
		return 10;
	}

	return (int) $matches[1];
}

/**
 * @return array<int,string>
 */
function vms_test_find_action_hooks_for_callback(string $source, string $callback): array
{
	$pattern = sprintf(
		'~add_action\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]%s[\'"]~',
		preg_quote($callback, '~')
	);
	if (!preg_match_all($pattern, $source, $matches)) {
		return array();
	}

	$hooks = array_values(array_unique(array_map('strval', (array) ($matches[1] ?? array()))));
	sort($hooks);
	return $hooks;
}

/**
 * @return array<int,string>
 */
function vms_test_find_direct_json_calls(string $functionBody): array
{
	if (!preg_match_all('/\bwp_send_json_(success|error)\s*\(/', $functionBody, $matches)) {
		return array();
	}

	$calls = array();
	foreach ((array) ($matches[0] ?? array()) as $match) {
		$calls[] = rtrim(trim((string) $match), '(');
	}

	$calls = array_values(array_unique($calls));
	sort($calls);
	return $calls;
}

function vms_test_find_ob_start_callback(string $functionBody): ?string
{
	if (!preg_match('/\bob_start\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $functionBody, $matches)) {
		return null;
	}

	return (string) $matches[1];
}

function vms_test_assert_no_same_flow_explicit_close(string $functionBody, string $functionName): void
{
	foreach (array('ob_get_clean(', 'ob_end_clean(', 'ob_end_flush(', 'ob_get_contents(') as $token) {
		vms_test_assert_not_contains(
			$token,
			$functionBody,
			$functionName . ' should not explicitly close or drain its own full-response buffer in the same function.'
		);
	}
}

try {
	$pluginRoot = dirname(__DIR__);
	$loadPath = $pluginRoot . '/includes/integrations/load.php';
	$ticketingPath = $pluginRoot . '/includes/integrations/ticketing.php';
	$v2Path = $pluginRoot . '/includes/integrations/ticketing-rules-v2.php';

	$loadSource = vms_test_read_file($loadPath);
	$ticketingSource = vms_test_read_file($ticketingPath);
	$v2Source = vms_test_read_file($v2Path);

	$firstRequirePos = strpos($loadSource, 'require_once');
	vms_test_assert_true($firstRequirePos !== false, 'load.php should still contain integration require_once statements.');
	$loadPrologue = substr($loadSource, 0, (int) $firstRequirePos);
	vms_test_assert_contains("defined('DOING_AJAX') && DOING_AJAX", $loadPrologue, 'load.php should still gate the global opener with DOING_AJAX.');
	vms_test_assert_contains("empty(\$GLOBALS['vms_ajax_ob_started'])", $loadPrologue, 'load.php should still guard ownership with vms_ajax_ob_started.');
	vms_test_assert_contains("\$GLOBALS['vms_ajax_ob_started'] = true;", $loadPrologue, 'load.php should still record AJAX buffer ownership.');
	vms_test_assert_contains('ob_start();', $loadPrologue, 'load.php should still open the request-global AJAX buffer.');
	vms_test_assert_true(
		strpos($loadPrologue, "defined('DOING_AJAX') && DOING_AJAX") < strpos($loadPrologue, 'ob_start();'),
		'load.php should still open the AJAX buffer only inside the DOING_AJAX gate.'
	);

	$legacyAttachBody = vms_test_extract_function($ticketingSource, 'vms_ticketing_ajax_attach_noise');
	$legacySuccessBody = vms_test_extract_function($ticketingSource, 'vms_ticketing_ajax_send_success');
	$legacyErrorBody = vms_test_extract_function($ticketingSource, 'vms_ticketing_ajax_send_error');

	vms_test_assert_contains("!empty(\$GLOBALS['vms_ajax_ob_started'])", $legacyAttachBody, 'Legacy cleanup helper should still consult the shared AJAX buffer ownership flag.');
	vms_test_assert_contains('ob_get_contents()', $legacyAttachBody, 'Legacy cleanup helper should still read buffered AJAX noise before closing.');
	vms_test_assert_contains('@ob_end_clean();', $legacyAttachBody, 'Legacy cleanup helper should still explicitly close the owned AJAX buffer.');
	vms_test_assert_contains("\$GLOBALS['vms_ajax_ob_started'] = false;", $legacyAttachBody, 'Legacy cleanup helper should still reset shared AJAX buffer ownership to false.');
	vms_test_assert_contains('vms_ticketing_ajax_attach_noise($data)', $legacySuccessBody, 'Legacy success wrapper should still route through the cleanup helper.');
	vms_test_assert_contains('wp_send_json_success($data, $http_status)', $legacySuccessBody, 'Legacy success wrapper should still send JSON through wp_send_json_success().');
	vms_test_assert_contains('vms_ticketing_ajax_attach_noise($data)', $legacyErrorBody, 'Legacy error wrapper should still route through the cleanup helper.');
	vms_test_assert_contains('wp_send_json_error($data, $http_status)', $legacyErrorBody, 'Legacy error wrapper should still send JSON through wp_send_json_error().');

	$v2AjaxExpectations = array(
		'vms_ticketing_v2_ajax_silent_add' => array(
			'hooks' => array('wp_ajax_nopriv_vms_ticketing_v2_silent_add', 'wp_ajax_vms_ticketing_v2_silent_add'),
			'direct_calls' => array('wp_send_json_error', 'wp_send_json_success'),
		),
		'vms_ticketing_v2_ajax_atomic_add_to_cart' => array(
			'hooks' => array('wp_ajax_nopriv_vms_ticketing_v2_atomic_add_to_cart', 'wp_ajax_vms_ticketing_v2_atomic_add_to_cart'),
			'direct_calls' => array('wp_send_json_error', 'wp_send_json_success'),
		),
		'vms_ticketing_v2_ajax_cart_context' => array(
			'hooks' => array('wp_ajax_nopriv_vms_ticketing_v2_cart_context', 'wp_ajax_vms_ticketing_v2_cart_context'),
			'direct_calls' => array('wp_send_json_error', 'wp_send_json_success'),
		),
	);

	foreach ($v2AjaxExpectations as $callback => $expectation) {
		$body = vms_test_extract_function($v2Source, $callback);
		$hooks = vms_test_find_action_hooks_for_callback($v2Source, $callback);
		vms_test_assert_same($expectation['hooks'], $hooks, $callback . ' should retain its exact AJAX action registrations.');

		$directCalls = vms_test_find_direct_json_calls($body);
		vms_test_assert_same($expectation['direct_calls'], $directCalls, $callback . ' should retain its direct wp_send_json_* response ownership.');

		vms_test_assert_not_contains('vms_ticketing_ajax_send_success(', $body, $callback . ' should not route through the legacy success wrapper.');
		vms_test_assert_not_contains('vms_ticketing_ajax_send_error(', $body, $callback . ' should not route through the legacy error wrapper.');
		vms_test_assert_not_contains('vms_ticketing_ajax_attach_noise(', $body, $callback . ' should not route through the legacy cleanup helper directly.');
	}

	$myTicketsPriority = vms_test_find_action_priority($v2Source, 'template_redirect', 'vms_ticketing_v2_start_my_tickets_notice_buffer');
	vms_test_assert_same(1, $myTicketsPriority, 'The My Tickets notice buffer should remain registered on template_redirect priority 1.');

	$myTicketsOpenerBody = vms_test_extract_function($v2Source, 'vms_ticketing_v2_start_my_tickets_notice_buffer');
	$myTicketsFilterBody = vms_test_extract_function($v2Source, 'vms_ticketing_v2_filter_my_tickets_notice_html');
	vms_test_assert_same(
		'vms_ticketing_v2_filter_my_tickets_notice_html',
		vms_test_find_ob_start_callback($myTicketsOpenerBody),
		'The My Tickets notice opener should still own a callback-based full-response buffer.'
	);
	vms_test_assert_contains(
		"\$GLOBALS['vms_ticketing_v2_my_tickets_notice_buffer_started'] = true;",
		$myTicketsOpenerBody,
		'The My Tickets opener should still record that its callback-owned response buffer has started.'
	);
	vms_test_assert_contains(
		'vms_ticketing_v2_rewrite_my_tickets_notice_html(',
		$myTicketsFilterBody,
		'The My Tickets buffer callback should still own the response rewrite path.'
	);
	vms_test_assert_no_same_flow_explicit_close($myTicketsOpenerBody, 'vms_ticketing_v2_start_my_tickets_notice_buffer');

	$serverMountPriority = vms_test_find_action_priority($v2Source, 'template_redirect', 'vms_ticketing_v2_server_mount_boot');
	vms_test_assert_same(5, $serverMountPriority, 'The server-mount buffer should remain registered on template_redirect priority 5.');

	$serverMountBootBody = vms_test_extract_function($v2Source, 'vms_ticketing_v2_server_mount_boot');
	$serverMountCallbackBody = vms_test_extract_function($v2Source, 'vms_ticketing_v2_server_mount_callback');
	vms_test_assert_same(
		'vms_ticketing_v2_server_mount_callback',
		vms_test_find_ob_start_callback($serverMountBootBody),
		'The server-mount opener should still own a callback-based full-response buffer.'
	);
	vms_test_assert_contains(
		'strpos($html, \'id="vms-reserved-addons"\')',
		$serverMountCallbackBody,
		'The server-mount callback should still inspect the rendered public event-page HTML.'
	);
	vms_test_assert_contains(
		'strpos($html, \'id="tribe-tickets__tickets-form"\')',
		$serverMountCallbackBody,
		'The server-mount callback should still target the public TEC tickets form markup.'
	);
	vms_test_assert_contains(
		'return $mounted_html;',
		$serverMountCallbackBody,
		'The server-mount callback should still return transformed public event-page HTML.'
	);
	vms_test_assert_no_same_flow_explicit_close($serverMountBootBody, 'vms_ticketing_v2_server_mount_boot');

	fwrite(STDOUT, "ticketing output buffer lifecycle characterization: PASS\n");
} catch (Throwable $throwable) {
	fwrite(STDERR, $throwable->getMessage() . "\n");
	exit(1);
}
