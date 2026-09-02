<?php
declare(strict_types=1);

final class VMS_Ticketing_V2_Ajax_Output_Buffer_Test_Exit extends RuntimeException
{
}

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

function vms_test_assert_code_contains(string $needle, string $haystack, string $message): void
{
	$normalizedNeedle = vms_test_normalize_code($needle);
	$normalizedHaystack = vms_test_normalize_code($haystack);
	vms_test_assert_contains($normalizedNeedle, $normalizedHaystack, $message);
}

function vms_test_normalize_code(string $code): string
{
	$normalized = preg_replace('/\s+/', ' ', $code);
	return is_string($normalized) ? trim($normalized) : trim($code);
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

/**
 * @return array<int,string>
 */
function vms_test_find_v2_wrapper_calls(string $functionBody): array
{
	if (!preg_match_all('/\b(bvmgr_ticketing_v2_ajax_send_(success|error))\s*\(/', $functionBody, $matches)) {
		return array();
	}

	$calls = array_values(array_unique(array_map('strval', (array) ($matches[1] ?? array()))));
	sort($calls);
	return $calls;
}

function vms_test_cleanup_output_buffers(int $targetLevel): void
{
	while (ob_get_level() > $targetLevel) {
		ob_end_clean();
	}
}

function vms_test_record_json_call(bool $success, $data, $statusCode, int $flags, int $numArgs): void
{
	$payload = array(
		'success' => $success,
		'data' => $data,
	);
	$json = json_encode($payload, $flags);
	if (!is_string($json)) {
		vms_test_fail('Failed to encode stub JSON payload.');
	}

	$GLOBALS['vms_test_wp_json_calls'][] = array(
		'success' => $success,
		'data' => $data,
		'status_code' => $statusCode,
		'flags' => $flags,
		'num_args' => $numArgs,
		'flag_state_at_send' => $GLOBALS['bvmgr_ajax_ob_started'] ?? null,
		'ob_level_at_send' => ob_get_level(),
		'json' => $json,
	);

	echo $json;
	throw new VMS_Ticketing_V2_Ajax_Output_Buffer_Test_Exit($success ? 'success' : 'error');
}

function wp_send_json_success($data = null, $statusCode = null, int $flags = 0): void
{
	vms_test_record_json_call(true, $data, $statusCode, $flags, func_num_args());
}

function wp_send_json_error($data = null, $statusCode = null, int $flags = 0): void
{
	vms_test_record_json_call(false, $data, $statusCode, $flags, func_num_args());
}

/**
 * @param array<int,mixed> $args
 * @return array<string,mixed>
 */
function vms_test_run_wrapper(string $callable, array $args, string $noise = ''): array
{
	$GLOBALS['vms_test_wp_json_calls'] = array();
	$startLevel = ob_get_level();
	$flagAfter = null;
	$output = '';

	try {
		ob_start();
		$collectorLevel = ob_get_level();

		if ($noise !== '') {
			$GLOBALS['bvmgr_ajax_ob_started'] = true;
			ob_start();
			echo $noise;
		} else {
			$GLOBALS['bvmgr_ajax_ob_started'] = false;
		}

		try {
			call_user_func_array($callable, $args);
			vms_test_fail('Expected ' . $callable . ' to terminate through the JSON sender.');
		} catch (VMS_Ticketing_V2_Ajax_Output_Buffer_Test_Exit $exception) {
			unset($exception);
		}

		$output = (string) ob_get_contents();
		$flagAfter = $GLOBALS['bvmgr_ajax_ob_started'] ?? null;
		$call = $GLOBALS['vms_test_wp_json_calls'][0] ?? null;
		vms_test_assert_true(is_array($call), 'Expected the stub JSON sender to capture exactly one call for ' . $callable . '.');

		return array(
			'call' => $call,
			'output' => $output,
			'collector_level' => $collectorLevel,
			'start_level' => $startLevel,
			'flag_after' => $flagAfter,
		);
	} finally {
		vms_test_cleanup_output_buffers($startLevel);
		unset($GLOBALS['bvmgr_ajax_ob_started'], $GLOBALS['vms_test_wp_json_calls']);
	}
}

try {
	$pluginRoot = dirname(__DIR__);
	$ticketingPath = $pluginRoot . '/includes/integrations/ticketing.php';
	$v2Path = $pluginRoot . '/includes/integrations/ticketing-rules-v2.php';

	$ticketingSource = vms_test_read_file($ticketingPath);
	$v2Source = vms_test_read_file($v2Path);

	$discardBody = vms_test_extract_function($ticketingSource, 'bvmgr_ticketing_ajax_discard_owned_buffer');
	$v2SuccessWrapperBody = vms_test_extract_function($ticketingSource, 'bvmgr_ticketing_v2_ajax_send_success');
	$v2ErrorWrapperBody = vms_test_extract_function($ticketingSource, 'bvmgr_ticketing_v2_ajax_send_error');

	eval($discardBody . "\n" . $v2SuccessWrapperBody . "\n" . $v2ErrorWrapperBody);

	vms_test_assert_code_contains('if (empty($GLOBALS[\'bvmgr_ajax_ob_started\'])) { return; }', $discardBody, 'The cleanup-only helper should return early when the ownership flag is false.');
	vms_test_assert_code_contains('if (ob_get_level() > 0) { @ob_end_clean(); }', $discardBody, 'The cleanup-only helper should only close one current buffer when a buffer exists.');
	vms_test_assert_code_contains('$GLOBALS[\'bvmgr_ajax_ob_started\'] = false;', $discardBody, 'The cleanup-only helper should always clear the ownership flag after a marked cleanup attempt.');
	vms_test_assert_code_contains('bvmgr_ticketing_ajax_discard_owned_buffer();', $v2SuccessWrapperBody, 'The V2 success wrapper should still call the cleanup-only helper.');
	vms_test_assert_code_contains('bvmgr_ticketing_ajax_discard_owned_buffer();', $v2ErrorWrapperBody, 'The V2 error wrapper should still call the cleanup-only helper.');
	vms_test_assert_code_contains('wp_send_json_success($data);', $v2SuccessWrapperBody, 'The V2 success wrapper should preserve the default WordPress success path when status and flags are omitted.');
	vms_test_assert_code_contains('wp_send_json_error($data);', $v2ErrorWrapperBody, 'The V2 error wrapper should preserve the default WordPress error path when status and flags are omitted.');

	$payloadReference = array(
		'nested' => array(
			'label' => 'alpha',
			'enabled' => true,
			'count' => 3,
			'note' => null,
		),
	);
	$payloadBefore = $payloadReference;
	$startLevel = ob_get_level();
	try {
		ob_start();
		$outerLevel = ob_get_level();
		echo 'outer-buffer';
		$GLOBALS['bvmgr_ajax_ob_started'] = false;
		bvmgr_ticketing_ajax_discard_owned_buffer();
		vms_test_assert_same($outerLevel, ob_get_level(), 'The cleanup-only helper should not close unrelated buffers when the ownership flag is false.');
		vms_test_assert_same('outer-buffer', (string) ob_get_contents(), 'The cleanup-only helper should not discard unrelated buffered output when the ownership flag is false.');
		vms_test_assert_same(false, $GLOBALS['bvmgr_ajax_ob_started'], 'The cleanup-only helper should leave the ownership flag false when no owned buffer is marked.');
		vms_test_assert_same($payloadBefore, $payloadReference, 'The cleanup-only helper should not mutate arbitrary response payload data.');
	} finally {
		vms_test_cleanup_output_buffers($startLevel);
		unset($GLOBALS['bvmgr_ajax_ob_started']);
	}

	$startLevel = ob_get_level();
	try {
		ob_start();
		$collectorLevel = ob_get_level();
		$GLOBALS['bvmgr_ajax_ob_started'] = true;
		ob_start();
		echo 'owned-noise';
		bvmgr_ticketing_ajax_discard_owned_buffer();
		vms_test_assert_same($collectorLevel, ob_get_level(), 'The cleanup-only helper should restore the expected buffer level after closing an owned buffer.');
		vms_test_assert_same('', (string) ob_get_contents(), 'The cleanup-only helper should discard owned buffered noise instead of emitting it.');
		vms_test_assert_same(false, $GLOBALS['bvmgr_ajax_ob_started'], 'The cleanup-only helper should clear the ownership flag after closing an owned buffer.');
	} finally {
		vms_test_cleanup_output_buffers($startLevel);
		unset($GLOBALS['bvmgr_ajax_ob_started']);
	}

	$GLOBALS['bvmgr_ajax_ob_started'] = true;
	$startLevel = ob_get_level();
	bvmgr_ticketing_ajax_discard_owned_buffer();
	vms_test_assert_same($startLevel, ob_get_level(), 'The cleanup-only helper should stay safe when the ownership flag is true but no current buffer exists.');
	vms_test_assert_same(false, $GLOBALS['bvmgr_ajax_ob_started'], 'The cleanup-only helper should clear the ownership flag even when no current buffer exists.');
	unset($GLOBALS['bvmgr_ajax_ob_started']);

	$successPayload = array(
		'ok' => true,
		'meta' => array(
			'count' => 2,
			'active' => true,
			'label' => 'ticket/success',
			'note' => null,
		),
	);
	$successResult = vms_test_run_wrapper('bvmgr_ticketing_v2_ajax_send_success', array($successPayload, 201), 'success-noise');
	vms_test_assert_same($successPayload, $successResult['call']['data'], 'The V2 success wrapper should forward associative payloads unchanged.');
	vms_test_assert_same(201, $successResult['call']['status_code'], 'The V2 success wrapper should preserve explicit HTTP statuses.');
	vms_test_assert_same(2, $successResult['call']['num_args'], 'The V2 success wrapper should call WordPress with the explicit status path when a status is supplied.');
	vms_test_assert_same(false, $successResult['call']['flag_state_at_send'], 'The V2 success wrapper should clear the ownership flag before JSON termination.');
	vms_test_assert_same(false, $successResult['flag_after'], 'The V2 success wrapper should leave the ownership flag false after cleanup.');
	vms_test_assert_same($successResult['call']['json'], $successResult['output'], 'Owned output should not precede the success JSON payload.');
	vms_test_assert_not_contains('success-noise', $successResult['output'], 'Owned buffered noise should not leak before the success JSON payload.');
	vms_test_assert_true(!array_key_exists('_vms_ajax_noise', $successResult['call']['data']), 'The V2 success wrapper must not attach legacy diagnostic noise.');

	$errorPayload = array(
		'ok' => false,
		'errors' => array(
			array('code' => 'bad_nonce', 'retry' => false),
		),
		'note' => null,
	);
	$errorResult = vms_test_run_wrapper('bvmgr_ticketing_v2_ajax_send_error', array($errorPayload, 422), 'error-noise');
	vms_test_assert_same($errorPayload, $errorResult['call']['data'], 'The V2 error wrapper should forward nested error payloads unchanged.');
	vms_test_assert_same(422, $errorResult['call']['status_code'], 'The V2 error wrapper should preserve explicit HTTP statuses.');
	vms_test_assert_same(2, $errorResult['call']['num_args'], 'The V2 error wrapper should call WordPress with the explicit status path when a status is supplied.');
	vms_test_assert_same(false, $errorResult['call']['flag_state_at_send'], 'The V2 error wrapper should clear the ownership flag before JSON termination.');
	vms_test_assert_same($errorResult['call']['json'], $errorResult['output'], 'Owned output should not precede the error JSON payload.');
	vms_test_assert_not_contains('error-noise', $errorResult['output'], 'Owned buffered noise should not leak before the error JSON payload.');
	vms_test_assert_true(!array_key_exists('_vms_ajax_noise', $errorResult['call']['data']), 'The V2 error wrapper must not attach legacy diagnostic noise.');

	$successDefaultResult = vms_test_run_wrapper('bvmgr_ticketing_v2_ajax_send_success', array(null));
	vms_test_assert_same(1, $successDefaultResult['call']['num_args'], 'The V2 success wrapper should preserve default-status behavior when no status is supplied.');
	vms_test_assert_same(null, $successDefaultResult['call']['status_code'], 'The V2 success wrapper should leave the default WordPress status untouched when omitted.');
	vms_test_assert_same(null, $successDefaultResult['call']['data'], 'The V2 success wrapper should allow null payloads.');

	$errorDefaultPayload = array(
		'message' => 'default-error',
		'ok' => false,
	);
	$errorDefaultResult = vms_test_run_wrapper('bvmgr_ticketing_v2_ajax_send_error', array($errorDefaultPayload));
	vms_test_assert_same(1, $errorDefaultResult['call']['num_args'], 'The V2 error wrapper should preserve default-status behavior when no status is supplied.');
	vms_test_assert_same(null, $errorDefaultResult['call']['status_code'], 'The V2 error wrapper should leave the default WordPress status untouched when omitted.');
	vms_test_assert_same($errorDefaultPayload, $errorDefaultResult['call']['data'], 'The V2 error wrapper should preserve default-path payloads unchanged.');

	$successFlags = JSON_UNESCAPED_SLASHES;
	$successFlagsPayload = array(
		'path' => 'https://example.com/a/b',
		'note' => 'alpha/beta',
	);
	$successFlagsResult = vms_test_run_wrapper('bvmgr_ticketing_v2_ajax_send_success', array($successFlagsPayload, 202, $successFlags));
	vms_test_assert_same(3, $successFlagsResult['call']['num_args'], 'The V2 success wrapper should forward JSON flags when they are supplied.');
	vms_test_assert_same($successFlags, $successFlagsResult['call']['flags'], 'The V2 success wrapper should forward JSON flags unchanged.');
	vms_test_assert_contains('https://example.com/a/b', $successFlagsResult['output'], 'The V2 success wrapper should preserve JSON flags in the emitted output.');
	vms_test_assert_not_contains('https:\\/\\/example.com\\/a\\/b', $successFlagsResult['output'], 'The V2 success wrapper should not lose supplied JSON flags.');

	$errorFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
	$errorFlagsPayload = array(
		'path' => 'https://example.com/failure',
		'label' => 'café',
	);
	$errorFlagsResult = vms_test_run_wrapper('bvmgr_ticketing_v2_ajax_send_error', array($errorFlagsPayload, 409, $errorFlags));
	vms_test_assert_same(3, $errorFlagsResult['call']['num_args'], 'The V2 error wrapper should forward JSON flags when they are supplied.');
	vms_test_assert_same($errorFlags, $errorFlagsResult['call']['flags'], 'The V2 error wrapper should forward JSON flags unchanged.');
	vms_test_assert_contains('https://example.com/failure', $errorFlagsResult['output'], 'The V2 error wrapper should preserve JSON flags in the emitted output.');
	vms_test_assert_contains('café', $errorFlagsResult['output'], 'The V2 error wrapper should preserve Unicode flags in the emitted output.');

	$atomicBody = vms_test_extract_function($v2Source, 'bvmgr_ticketing_v2_ajax_atomic_add_to_cart');
	$silentBody = vms_test_extract_function($v2Source, 'bvmgr_ticketing_v2_ajax_silent_add');
	$cartContextBody = vms_test_extract_function($v2Source, 'bvmgr_ticketing_v2_ajax_cart_context');

	vms_test_assert_same(array(), vms_test_find_direct_json_calls($atomicBody), 'Atomic add should no longer contain direct wp_send_json_* calls.');
	vms_test_assert_same(array(), vms_test_find_direct_json_calls($silentBody), 'Silent add should no longer contain direct wp_send_json_* calls.');
	vms_test_assert_same(array(), vms_test_find_direct_json_calls($cartContextBody), 'Cart context should no longer contain direct wp_send_json_* calls.');

	$expectedWrapperCalls = array('bvmgr_ticketing_v2_ajax_send_error', 'bvmgr_ticketing_v2_ajax_send_success');
	vms_test_assert_same($expectedWrapperCalls, vms_test_find_v2_wrapper_calls($atomicBody), 'Atomic add should now terminate exclusively through the V2 wrappers.');
	vms_test_assert_same($expectedWrapperCalls, vms_test_find_v2_wrapper_calls($silentBody), 'Silent add should now terminate exclusively through the V2 wrappers.');
	vms_test_assert_same($expectedWrapperCalls, vms_test_find_v2_wrapper_calls($cartContextBody), 'Cart context should now terminate exclusively through the V2 wrappers.');

	vms_test_assert_not_contains('bvmgr_ticketing_ajax_send_success(', $atomicBody, 'Atomic add should not route through the legacy success wrapper.');
	vms_test_assert_not_contains('bvmgr_ticketing_ajax_send_error(', $atomicBody, 'Atomic add should not route through the legacy error wrapper.');
	vms_test_assert_not_contains('bvmgr_ticketing_ajax_attach_noise(', $atomicBody, 'Atomic add should not route through the legacy noise helper.');
	vms_test_assert_not_contains('bvmgr_ticketing_ajax_send_success(', $silentBody, 'Silent add should not route through the legacy success wrapper.');
	vms_test_assert_not_contains('bvmgr_ticketing_ajax_send_error(', $silentBody, 'Silent add should not route through the legacy error wrapper.');
	vms_test_assert_not_contains('bvmgr_ticketing_ajax_attach_noise(', $silentBody, 'Silent add should not route through the legacy noise helper.');
	vms_test_assert_not_contains('bvmgr_ticketing_ajax_send_success(', $cartContextBody, 'Cart context should not route through the legacy success wrapper.');
	vms_test_assert_not_contains('bvmgr_ticketing_ajax_send_error(', $cartContextBody, 'Cart context should not route through the legacy error wrapper.');
	vms_test_assert_not_contains('bvmgr_ticketing_ajax_attach_noise(', $cartContextBody, 'Cart context should not route through the legacy noise helper.');

	vms_test_assert_same(2, substr_count(vms_test_normalize_code($atomicBody), vms_test_normalize_code("bvmgr_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'invalid_payload'), 400);")), 'Atomic add should keep both invalid_payload 400 error branches.');
	vms_test_assert_code_contains("bvmgr_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'bad_nonce'), 403);", $atomicBody, 'Atomic add should keep the bad_nonce 403 contract.');
	vms_test_assert_code_contains("bvmgr_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'empty_selection'), 400);", $atomicBody, 'Atomic add should keep the empty_selection 400 contract.');
	vms_test_assert_code_contains("bvmgr_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'cart_unavailable'), 400);", $atomicBody, 'Atomic add should keep the cart_unavailable 400 contract.');
	vms_test_assert_code_contains("bvmgr_ticketing_v2_ajax_send_error(array( 'ok' => false, 'message' => sanitize_text_field((string) (\$event_validation['code'] ?? 'event_unavailable')), 'notice_message' => sanitize_text_field((string) (\$event_validation['message'] ?? '')), ), (int) (\$event_validation['http'] ?? 400));", $atomicBody, 'Atomic add should keep dynamic event-validation status forwarding.');
	vms_test_assert_code_contains("bvmgr_ticketing_v2_ajax_send_error(array( 'ok' => false, 'message' => \$message, 'errors' => \$errors, 'notice_messages' => \$notice_messages, ), 400);", $atomicBody, 'Atomic add should keep the rollback error response contract.');
	vms_test_assert_code_contains("bvmgr_ticketing_v2_ajax_send_success(array( 'ok' => true, 'cart_url' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/'), 'added_tickets' => \$added_tickets, 'added_addons' => \$added_addons, 'added_total' => (\$added_tickets + \$added_addons), ));", $atomicBody, 'Atomic add should keep the final success response contract.');

	vms_test_assert_same(2, substr_count(vms_test_normalize_code($silentBody), vms_test_normalize_code("bvmgr_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'invalid_payload'), 400);")), 'Silent add should keep both invalid_payload 400 error branches.');
	vms_test_assert_code_contains("bvmgr_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'bad_nonce'), 403);", $silentBody, 'Silent add should keep the bad_nonce 403 contract.');
	vms_test_assert_code_contains("bvmgr_ticketing_v2_ajax_send_success(array('ok' => true, 'added' => 0));", $silentBody, 'Silent add should keep the empty-items success contract.');
	vms_test_assert_code_contains("bvmgr_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'cart_unavailable'), 400);", $silentBody, 'Silent add should keep the cart_unavailable 400 contract.');
	vms_test_assert_code_contains("bvmgr_ticketing_v2_ajax_send_error(array( 'ok' => false, 'message' => sanitize_text_field((string) (\$event_validation['code'] ?? 'event_unavailable')), 'notice_message' => sanitize_text_field((string) (\$event_validation['message'] ?? '')), ), (int) (\$event_validation['http'] ?? 400));", $silentBody, 'Silent add should keep dynamic event-validation status forwarding.');
	vms_test_assert_code_contains("bvmgr_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'ticketing_disabled'), 403);", $silentBody, 'Silent add should keep the ticketing_disabled 403 contract.');
	vms_test_assert_code_contains("bvmgr_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'add_failed', 'errors' => \$errors), 400);", $silentBody, 'Silent add should keep the partial-failure error response contract.');
	vms_test_assert_code_contains("bvmgr_ticketing_v2_ajax_send_success(array('ok' => true, 'added' => \$added));", $silentBody, 'Silent add should keep the final success response contract.');

	vms_test_assert_code_contains("bvmgr_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'bad_nonce'), 403);", $cartContextBody, 'Cart context should keep the bad_nonce 403 contract.');
	vms_test_assert_code_contains("bvmgr_ticketing_v2_ajax_send_error(array('ok' => false, 'message' => 'cart_unavailable'), 400);", $cartContextBody, 'Cart context should keep the cart_unavailable 400 contract.');
	vms_test_assert_code_contains("bvmgr_ticketing_v2_ajax_send_success(array( 'ok' => true, 'event_plan_id' => \$plan_id, 'tec_event_id' => \$tec_event_id, 'ga_qty_raw' => \$ga_qty_raw, 'ga_qty' => \$ga_qty, 'prior_qualifying_qty' => \$prior_qualifying_qty, 'prior_pool_qty_by_key' => \$prior_pool_qty_by_key, 'pool_qty_by_key' => \$pool_qty_by_key, 'has_checkout_blockers' => !empty(\$checkout_blocker_messages), 'checkout_blocker_messages' => \$checkout_blocker_messages, ));", $cartContextBody, 'Cart context should keep the final success response contract.');

	fwrite(STDOUT, "ticketing V2 AJAX output buffer ownership: PASS\n");
} catch (Throwable $throwable) {
	fwrite(STDERR, $throwable->getMessage() . "\n");
	exit(1);
}
