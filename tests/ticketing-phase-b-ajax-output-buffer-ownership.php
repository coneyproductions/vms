<?php
declare(strict_types=1);

final class VMS_Ticketing_Phase_B_Ajax_Output_Buffer_Test_Exit extends RuntimeException
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

function vms_test_normalize_code(string $code): string
{
	$normalized = preg_replace('/\s+/', ' ', $code);
	return is_string($normalized) ? trim($normalized) : trim($code);
}

function vms_test_assert_code_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert_contains(vms_test_normalize_code($needle), vms_test_normalize_code($haystack), $message);
}

function vms_test_assert_code_count(int $expectedCount, string $needle, string $haystack, string $message): void
{
	$actualCount = substr_count(vms_test_normalize_code($haystack), vms_test_normalize_code($needle));
	vms_test_assert_same($expectedCount, $actualCount, $message);
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

/**
 * @return array<int,string>
 */
function vms_test_find_v2_wrapper_calls(string $functionBody): array
{
	if (!preg_match_all('/\b(vms_ticketing_v2_ajax_send_(success|error))\s*\(/', $functionBody, $matches)) {
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
	throw new VMS_Ticketing_Phase_B_Ajax_Output_Buffer_Test_Exit($success ? 'success' : 'error');
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
		} catch (VMS_Ticketing_Phase_B_Ajax_Output_Buffer_Test_Exit $exception) {
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
	$phaseBPath = $pluginRoot . '/includes/integrations/ticketing-phase-b.php';

	$ticketingSource = vms_test_read_file($ticketingPath);
	$phaseBSource = vms_test_read_file($phaseBPath);

	$discardBody = vms_test_extract_function($ticketingSource, 'vms_ticketing_ajax_discard_owned_buffer');
	$v2SuccessWrapperBody = vms_test_extract_function($ticketingSource, 'vms_ticketing_v2_ajax_send_success');
	$v2ErrorWrapperBody = vms_test_extract_function($ticketingSource, 'vms_ticketing_v2_ajax_send_error');
	$fastHelperBody = vms_test_extract_function($phaseBSource, 'vms_ticketing_v2_ajax_send_json_success_fast');

	eval($discardBody . "\n" . $v2SuccessWrapperBody . "\n" . $v2ErrorWrapperBody);

	vms_test_assert_code_contains('vms_ticketing_ajax_discard_owned_buffer();', $v2SuccessWrapperBody, 'The V2 success wrapper should still invoke the cleanup-only helper.');
	vms_test_assert_code_contains('vms_ticketing_ajax_discard_owned_buffer();', $v2ErrorWrapperBody, 'The V2 error wrapper should still invoke the cleanup-only helper.');

	$successResult = vms_test_run_wrapper('vms_ticketing_v2_ajax_send_success', array(array('ok' => true, 'scope' => 'phase-b'), 201), 'phase-b-success-noise');
	vms_test_assert_same(true, $successResult['call']['success'], 'The V2 success wrapper should still terminate through the success sender.');
	vms_test_assert_same(array('ok' => true, 'scope' => 'phase-b'), $successResult['call']['data'], 'The V2 success wrapper should forward Phase B payloads unchanged.');
	vms_test_assert_same(201, $successResult['call']['status_code'], 'The V2 success wrapper should preserve explicit HTTP statuses.');
	vms_test_assert_same(false, $successResult['call']['flag_state_at_send'], 'The V2 success wrapper should clear AJAX ownership before JSON termination.');
	vms_test_assert_same(false, $successResult['flag_after'], 'The V2 success wrapper should leave ownership cleared after cleanup.');
	vms_test_assert_same($successResult['call']['json'], $successResult['output'], 'The V2 success wrapper should discard owned buffer noise before emitting JSON.');
	vms_test_assert_not_contains('phase-b-success-noise', $successResult['output'], 'Owned Phase B success noise should not leak into the JSON response.');

	$errorResult = vms_test_run_wrapper('vms_ticketing_v2_ajax_send_error', array(array('message' => 'bad_nonce'), 403), 'phase-b-error-noise');
	vms_test_assert_same(false, $errorResult['call']['success'], 'The V2 error wrapper should still terminate through the error sender.');
	vms_test_assert_same(array('message' => 'bad_nonce'), $errorResult['call']['data'], 'The V2 error wrapper should forward Phase B error payloads unchanged.');
	vms_test_assert_same(403, $errorResult['call']['status_code'], 'The V2 error wrapper should preserve explicit HTTP statuses.');
	vms_test_assert_same(false, $errorResult['call']['flag_state_at_send'], 'The V2 error wrapper should clear AJAX ownership before JSON termination.');
	vms_test_assert_same(false, $errorResult['flag_after'], 'The V2 error wrapper should leave ownership cleared after cleanup.');
	vms_test_assert_same($errorResult['call']['json'], $errorResult['output'], 'The V2 error wrapper should discard owned buffer noise before emitting JSON.');
	vms_test_assert_not_contains('phase-b-error-noise', $errorResult['output'], 'Owned Phase B error noise should not leak into the JSON response.');

	vms_test_assert_code_contains('$operation = sanitize_key($operation);', $fastHelperBody, 'The fast success helper should still sanitize the operation name.');
	vms_test_assert_code_contains("header('X-VMS-Fast-Ajax: ' . \$operation);", $fastHelperBody, 'The fast success helper should still emit the X-VMS-Fast-Ajax header.');
	vms_test_assert_code_contains('while (ob_get_level() > 0) { @ob_end_clean(); }', $fastHelperBody, 'The fast success helper should still drain active buffers directly.');
	vms_test_assert_code_contains('echo $payload;', $fastHelperBody, 'The fast success helper should still echo the pre-encoded payload.');
	vms_test_assert_code_contains('exit;', $fastHelperBody, 'The fast success helper should still terminate immediately after the fast response path.');

	$phaseBAjaxExpectations = array(
		'vms_ticketing_b_ajax_save_tiers' => array(
			'hooks' => array('wp_ajax_vms_ticketing_save_tiers'),
			'wrapper_calls' => array('vms_ticketing_v2_ajax_send_error', 'vms_ticketing_v2_ajax_send_success'),
		),
		'vms_ticketing_b_ajax_preview_sync' => array(
			'hooks' => array('wp_ajax_vms_ticketing_preview_sync'),
			'wrapper_calls' => array('vms_ticketing_v2_ajax_send_error', 'vms_ticketing_v2_ajax_send_success'),
		),
		'vms_ticketing_b_ajax_commit_sync' => array(
			'hooks' => array('wp_ajax_vms_ticketing_commit_sync'),
			'wrapper_calls' => array('vms_ticketing_v2_ajax_send_error', 'vms_ticketing_v2_ajax_send_success'),
		),
		'vms_ticketing_v2_ajax_save_config' => array(
			'hooks' => array('wp_ajax_vms_ticketing_v2_save_config'),
			'wrapper_calls' => array('vms_ticketing_v2_ajax_send_error'),
			'fast_success_call' => "vms_ticketing_v2_ajax_send_json_success_fast(\$response, 'ticketing-v2-save-config');",
		),
		'vms_ticketing_v2_ajax_save_template' => array(
			'hooks' => array('wp_ajax_vms_ticketing_v2_save_template'),
			'wrapper_calls' => array('vms_ticketing_v2_ajax_send_error', 'vms_ticketing_v2_ajax_send_success'),
		),
		'vms_ticketing_v2_ajax_apply_template' => array(
			'hooks' => array('wp_ajax_vms_ticketing_v2_apply_template'),
			'wrapper_calls' => array('vms_ticketing_v2_ajax_send_error', 'vms_ticketing_v2_ajax_send_success'),
		),
		'vms_ticketing_v2_ajax_clear_config' => array(
			'hooks' => array('wp_ajax_vms_ticketing_v2_clear_config'),
			'wrapper_calls' => array('vms_ticketing_v2_ajax_send_error', 'vms_ticketing_v2_ajax_send_success'),
		),
		'vms_ticketing_v2_ajax_init_from_legacy' => array(
			'hooks' => array('wp_ajax_vms_ticketing_v2_init_from_legacy'),
			'wrapper_calls' => array('vms_ticketing_v2_ajax_send_error'),
		),
		'vms_ticketing_v2_ajax_set_default_template' => array(
			'hooks' => array('wp_ajax_vms_ticketing_v2_set_default_template'),
			'wrapper_calls' => array('vms_ticketing_v2_ajax_send_error', 'vms_ticketing_v2_ajax_send_success'),
		),
		'vms_ticketing_v2_ajax_preview_sync' => array(
			'hooks' => array('wp_ajax_vms_ticketing_v2_preview_sync'),
			'wrapper_calls' => array('vms_ticketing_v2_ajax_send_error'),
			'fast_success_call' => "vms_ticketing_v2_ajax_send_json_success_fast(\$preview, 'ticketing-v2-preview-sync');",
		),
		'vms_ticketing_v2_ajax_commit_sync' => array(
			'hooks' => array('wp_ajax_vms_ticketing_v2_commit_sync'),
			'wrapper_calls' => array('vms_ticketing_v2_ajax_send_error', 'vms_ticketing_v2_ajax_send_success'),
		),
	);

	foreach ($phaseBAjaxExpectations as $callback => $expectation) {
		$body = vms_test_extract_function($phaseBSource, $callback);
		$hooks = vms_test_find_action_hooks_for_callback($phaseBSource, $callback);

		vms_test_assert_same($expectation['hooks'], $hooks, $callback . ' should retain its exact authenticated AJAX action registration.');
		foreach ($expectation['hooks'] as $hook) {
			$noprivHook = str_replace('wp_ajax_', 'wp_ajax_nopriv_', $hook);
			vms_test_assert_not_contains($noprivHook, $phaseBSource, $callback . ' must not introduce an unauthenticated AJAX action.');
		}

		vms_test_assert_same($expectation['wrapper_calls'], vms_test_find_v2_wrapper_calls($body), $callback . ' should terminate only through the expected V2 cleanup wrappers.');
		vms_test_assert_same(array(), vms_test_find_direct_json_calls($body), $callback . ' should not retain direct wp_send_json_* calls.');
		vms_test_assert_not_contains('vms_ticketing_ajax_send_success(', $body, $callback . ' should not route through the legacy AJAX success wrapper.');
		vms_test_assert_not_contains('vms_ticketing_ajax_send_error(', $body, $callback . ' should not route through the legacy AJAX error wrapper.');
		vms_test_assert_not_contains('vms_ticketing_ajax_attach_noise(', $body, $callback . ' should not route through the legacy AJAX noise helper.');

		if (isset($expectation['fast_success_call'])) {
			vms_test_assert_contains($expectation['fast_success_call'], $body, $callback . ' should preserve its fast success responder.');
			vms_test_assert_not_contains('vms_ticketing_v2_ajax_send_success(', $body, $callback . ' should not replace the fast success path with the ordinary V2 success wrapper.');
		} else {
			vms_test_assert_not_contains('vms_ticketing_v2_ajax_send_json_success_fast(', $body, $callback . ' should not gain a fast success responder.');
		}
	}

	$saveTiersBody = vms_test_extract_function($phaseBSource, 'vms_ticketing_b_ajax_save_tiers');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);", $saveTiersBody, 'Save tiers should keep the bad_nonce 403 contract.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'forbidden'), 403);", $saveTiersBody, 'Save tiers should keep the forbidden 403 contract.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'invalid_payload_tiers'), 400);", $saveTiersBody, 'Save tiers should keep the invalid_payload_tiers 400 contract.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_success(array('tiers' => \$tiers_out));", $saveTiersBody, 'Save tiers should keep the final tiers success payload.');

	$previewSyncBody = vms_test_extract_function($phaseBSource, 'vms_ticketing_b_ajax_preview_sync');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);", $previewSyncBody, 'Legacy preview sync should keep the bad_nonce 403 contract.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'forbidden'), 403);", $previewSyncBody, 'Legacy preview sync should keep the forbidden 403 contract.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => \$preview['message'] ?? 'error'), 400);", $previewSyncBody, 'Legacy preview sync should still forward preview_fail-style messages unchanged.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_success(\$preview);", $previewSyncBody, 'Legacy preview sync should keep the preview success payload unchanged.');

	$commitSyncBody = vms_test_extract_function($phaseBSource, 'vms_ticketing_b_ajax_commit_sync');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);", $commitSyncBody, 'Legacy commit sync should keep the bad_nonce 403 contract.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'forbidden'), 403);", $commitSyncBody, 'Legacy commit sync should keep the forbidden 403 contract.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'invalid_payload_items'), 400);", $commitSyncBody, 'Legacy commit sync should keep the invalid_payload_items 400 contract.');
	vms_test_assert_code_contains('$http = isset($res[\'http\']) ? (int) $res[\'http\'] : 400;', $commitSyncBody, 'Legacy commit sync should still derive the dynamic HTTP status from the commit_fail result.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array( 'message' => \$res['message'] ?? 'error', 'error_code' => \$res['error_code'] ?? (\$res['message'] ?? 'error'), 'error_summary' => \$res['error_summary'] ?? '', 'diagnostics' => is_array(\$res['diagnostics'] ?? null) ? \$res['diagnostics'] : array(), ), \$http);", $commitSyncBody, 'Legacy commit sync should keep the dynamic commit_fail error payload and status forwarding.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_success(\$res);", $commitSyncBody, 'Legacy commit sync should keep the final commit success payload.');

	$saveConfigBody = vms_test_extract_function($phaseBSource, 'vms_ticketing_v2_ajax_save_config');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);", $saveConfigBody, 'Save config should keep the bad_nonce 403 contract.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'forbidden'), 403);", $saveConfigBody, 'Save config should keep the forbidden 403 contract.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'invalid_payload_config'), 400);", $saveConfigBody, 'Save config should keep the invalid_payload_config 400 contract.');
	vms_test_assert_contains("vms_ticketing_v2_ajax_send_json_success_fast(\$response, 'ticketing-v2-save-config');", $saveConfigBody, 'Save config should keep its fast success responder.');

	$saveTemplateBody = vms_test_extract_function($phaseBSource, 'vms_ticketing_v2_ajax_save_template');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);", $saveTemplateBody, 'Save template should keep the bad_nonce 403 contract.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'forbidden'), 403);", $saveTemplateBody, 'Save template should keep the forbidden 403 contract.');
	vms_test_assert_code_count(4, "vms_ticketing_v2_ajax_send_error(array('message' => 'invalid_payload_config'), 400);", $saveTemplateBody, 'Save template should keep all invalid_payload_config 400 branches, including malformed request-shape rejection.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => \$res['message'] ?? 'error'), 400);", $saveTemplateBody, 'Save template should still forward save_fail-style messages unchanged.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_success(array( 'template_id' => (string) (\$res['template_id'] ?? ''), 'templates' => \$list, ));", $saveTemplateBody, 'Save template should keep the final template success payload.');

	$applyTemplateBody = vms_test_extract_function($phaseBSource, 'vms_ticketing_v2_ajax_apply_template');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);", $applyTemplateBody, 'Apply template should keep the bad_nonce 403 contract.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'forbidden'), 403);", $applyTemplateBody, 'Apply template should keep the forbidden 403 contract.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => \$res['message'] ?? 'error'), 400);", $applyTemplateBody, 'Apply template should still forward apply_fail-style messages unchanged.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_success(array( 'config' => \$res['config'], 'config_hash' => vms_ticketing_v2_hash_config_for_sync(\$res['config']), 'applied_show_datetime' => (string) (\$res['applied_show_datetime'] ?? ''), ));", $applyTemplateBody, 'Apply template should keep the final apply-template success payload.');

	$clearConfigBody = vms_test_extract_function($phaseBSource, 'vms_ticketing_v2_ajax_clear_config');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);", $clearConfigBody, 'Clear config should keep the bad_nonce 403 contract.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'forbidden'), 403);", $clearConfigBody, 'Clear config should keep the forbidden 403 contract.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_success(array( 'config' => vms_ticketing_v2_default_config(\$plan_id), ));", $clearConfigBody, 'Clear config should keep the final reset success payload.');

	$initFromLegacyBody = vms_test_extract_function($phaseBSource, 'vms_ticketing_v2_ajax_init_from_legacy');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);", $initFromLegacyBody, 'Init from legacy should keep the bad_nonce 403 contract.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'forbidden'), 403);", $initFromLegacyBody, 'Init from legacy should keep the forbidden 403 contract.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array( 'message' => 'legacy_init_retired', 'detail' => __('Legacy Ticketing initializer is retired. Configure Ticketing v2 directly and use Preview → Commit.', 'backstage-venue-manager'), ), 400);", $initFromLegacyBody, 'Init from legacy should keep the retired initializer error payload unchanged.');

	$setDefaultTemplateBody = vms_test_extract_function($phaseBSource, 'vms_ticketing_v2_ajax_set_default_template');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);", $setDefaultTemplateBody, 'Set default template should keep the bad_nonce 403 contract.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'forbidden'), 403);", $setDefaultTemplateBody, 'Set default template should keep the forbidden 403 contract.');
	vms_test_assert_code_count(2, "vms_ticketing_v2_ajax_send_error(array('message' => 'template_not_found'), 400);", $setDefaultTemplateBody, 'Set default template should keep both template_not_found 400 branches.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_success(array( 'default_template_id' => \$template_id, 'default_template_name' => \$name, ));", $setDefaultTemplateBody, 'Set default template should keep the final default-template success payload.');

	$v2PreviewSyncBody = vms_test_extract_function($phaseBSource, 'vms_ticketing_v2_ajax_preview_sync');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);", $v2PreviewSyncBody, 'V2 preview sync should keep the bad_nonce 403 contract.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'forbidden'), 403);", $v2PreviewSyncBody, 'V2 preview sync should keep the forbidden 403 contract.');
	vms_test_assert_code_contains('$http = isset($preview[\'http\']) ? (int) $preview[\'http\'] : 400;', $v2PreviewSyncBody, 'V2 preview sync should still derive the dynamic HTTP status from the preview_fail result.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array( 'message' => \$preview['message'] ?? 'error', 'preview_elapsed_ms' => \$preview_elapsed_ms, 'request_age_at_handler_ms' => \$request_age_at_handler_ms, ), \$http);", $v2PreviewSyncBody, 'V2 preview sync should keep the preview_fail payload and dynamic status forwarding.');
	vms_test_assert_contains("vms_ticketing_v2_ajax_send_json_success_fast(\$preview, 'ticketing-v2-preview-sync');", $v2PreviewSyncBody, 'V2 preview sync should keep its fast success responder.');

	$v2CommitSyncBody = vms_test_extract_function($phaseBSource, 'vms_ticketing_v2_ajax_commit_sync');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);", $v2CommitSyncBody, 'V2 commit sync should keep the bad_nonce 403 contract.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'forbidden'), 403);", $v2CommitSyncBody, 'V2 commit sync should keep the forbidden 403 contract.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array('message' => 'invalid_payload_preview_id'), 400);", $v2CommitSyncBody, 'V2 commit sync should keep the invalid_payload_preview_id 400 contract.');
	vms_test_assert_code_contains('$http = isset($res[\'http\']) ? (int) $res[\'http\'] : 400;', $v2CommitSyncBody, 'V2 commit sync should still derive the dynamic HTTP status from the commit_fail result.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_error(array( 'message' => \$res['message'] ?? 'error', 'error_code' => \$res['error_code'] ?? (\$res['message'] ?? 'error'), 'error_summary' => \$res['error_summary'] ?? '', 'diagnostics' => is_array(\$res['diagnostics'] ?? null) ? \$res['diagnostics'] : array(), ), \$http);", $v2CommitSyncBody, 'V2 commit sync should keep the dynamic commit_fail error payload and status forwarding.');
	vms_test_assert_code_contains("vms_ticketing_v2_ajax_send_success(\$res);", $v2CommitSyncBody, 'V2 commit sync should keep the final commit success payload.');

	fwrite(STDOUT, "ticketing Phase B AJAX output buffer ownership: PASS\n");
} catch (Throwable $throwable) {
	fwrite(STDERR, $throwable->getMessage() . "\n");
	exit(1);
}
