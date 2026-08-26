<?php
declare(strict_types=1);

final class VMS_Ticketing_Claims_Ajax_Output_Buffer_Test_Exit extends RuntimeException
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

function vms_test_assert_code_order(string $first, string $second, string $haystack, string $message): void
{
	$normalizedHaystack = vms_test_normalize_code($haystack);
	$normalizedFirst = vms_test_normalize_code($first);
	$normalizedSecond = vms_test_normalize_code($second);

	$firstPos = strpos($normalizedHaystack, $normalizedFirst);
	$secondPos = strpos($normalizedHaystack, $normalizedSecond);

	vms_test_assert_true($firstPos !== false, $message . "\nMissing first token: " . $normalizedFirst);
	vms_test_assert_true($secondPos !== false, $message . "\nMissing second token: " . $normalizedSecond);
	vms_test_assert_true($firstPos < $secondPos, $message);
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
	throw new VMS_Ticketing_Claims_Ajax_Output_Buffer_Test_Exit($success ? 'success' : 'error');
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
		} catch (VMS_Ticketing_Claims_Ajax_Output_Buffer_Test_Exit $exception) {
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
	$claimsPath = $pluginRoot . '/includes/integrations/ticketing-claims-customer.php';

	$ticketingSource = vms_test_read_file($ticketingPath);
	$claimsSource = vms_test_read_file($claimsPath);

	$discardBody = vms_test_extract_function($ticketingSource, 'vms_ticketing_ajax_discard_owned_buffer');
	$v2SuccessWrapperBody = vms_test_extract_function($ticketingSource, 'vms_ticketing_v2_ajax_send_success');
	$v2ErrorWrapperBody = vms_test_extract_function($ticketingSource, 'vms_ticketing_v2_ajax_send_error');
	$clientLogBody = vms_test_extract_function($claimsSource, 'vms_ticketing_claims_handle_client_log_action');
	$assigneeBody = vms_test_extract_function($claimsSource, 'vms_ticketing_claims_handle_validate_assignee');
	$existingCountsHelperBody = vms_test_extract_function($claimsSource, 'vms_ticketing_claims_post_existing_counts');

	eval($discardBody . "\n" . $v2SuccessWrapperBody . "\n" . $v2ErrorWrapperBody);

	vms_test_assert_code_contains(
		'vms_ticketing_ajax_discard_owned_buffer();',
		$v2SuccessWrapperBody,
		'The V2 success wrapper should still invoke the cleanup-only helper.'
	);
	vms_test_assert_code_contains(
		'vms_ticketing_ajax_discard_owned_buffer();',
		$v2ErrorWrapperBody,
		'The V2 error wrapper should still invoke the cleanup-only helper.'
	);

	vms_test_assert_same(
		array('wp_ajax_vms_ticketing_claims_log_client_action'),
		vms_test_find_action_hooks_for_callback($claimsSource, 'vms_ticketing_claims_handle_client_log_action'),
		'The client-log action should remain authenticated-only and map to the same callback.'
	);
	vms_test_assert_not_contains(
		'wp_ajax_nopriv_vms_ticketing_claims_log_client_action',
		$claimsSource,
		'The client-log action must not gain a logged-out registration.'
	);
	vms_test_assert_same(
		array('wp_ajax_nopriv_vms_ticketing_claims_validate_assignee', 'wp_ajax_vms_ticketing_claims_validate_assignee'),
		vms_test_find_action_hooks_for_callback($claimsSource, 'vms_ticketing_claims_handle_validate_assignee'),
		'Assignee validation should retain both authenticated and nopriv registrations on the same callback.'
	);

	$expectedWrapperCalls = array('vms_ticketing_v2_ajax_send_error', 'vms_ticketing_v2_ajax_send_success');
	vms_test_assert_same(array(), vms_test_find_direct_json_calls($clientLogBody), 'Client logging should no longer terminate through direct wp_send_json_* calls.');
	vms_test_assert_same(array(), vms_test_find_direct_json_calls($assigneeBody), 'Assignee validation should no longer terminate through direct wp_send_json_* calls.');
	vms_test_assert_same($expectedWrapperCalls, vms_test_find_v2_wrapper_calls($clientLogBody), 'Client logging should now terminate through the cleanup wrappers.');
	vms_test_assert_same($expectedWrapperCalls, vms_test_find_v2_wrapper_calls($assigneeBody), 'Assignee validation should now terminate through the cleanup wrappers.');
	vms_test_assert_not_contains('vms_ticketing_ajax_send_success(', $clientLogBody, 'Client logging should not route through the legacy success wrapper.');
	vms_test_assert_not_contains('vms_ticketing_ajax_send_error(', $clientLogBody, 'Client logging should not route through the legacy error wrapper.');
	vms_test_assert_not_contains('vms_ticketing_ajax_attach_noise(', $clientLogBody, 'Client logging should not route through the legacy noise helper.');
	vms_test_assert_not_contains('vms_ticketing_ajax_send_success(', $assigneeBody, 'Assignee validation should not route through the legacy success wrapper.');
	vms_test_assert_not_contains('vms_ticketing_ajax_send_error(', $assigneeBody, 'Assignee validation should not route through the legacy error wrapper.');
	vms_test_assert_not_contains('vms_ticketing_ajax_attach_noise(', $assigneeBody, 'Assignee validation should not route through the legacy noise helper.');
	vms_test_assert_code_count(3, 'vms_ticketing_v2_ajax_send_error(', $clientLogBody, 'Client logging should keep its three error exits.');
	vms_test_assert_code_count(1, 'vms_ticketing_v2_ajax_send_success(', $clientLogBody, 'Client logging should keep its single success exit.');
	vms_test_assert_code_count(8, 'vms_ticketing_v2_ajax_send_error(', $assigneeBody, 'Assignee validation should keep its eight error exits.');
	vms_test_assert_code_count(1, 'vms_ticketing_v2_ajax_send_success(', $assigneeBody, 'Assignee validation should keep its single success exit.');

	vms_test_assert_code_order(
		'if (!is_user_logged_in()) {',
		"if (!check_ajax_referer('vms_ticketing_claims_log_client_action', 'nonce', false)) {",
		$clientLogBody,
		'Client logging should still check the logged-in state before nonce validation.'
	);
	vms_test_assert_code_order(
		"if (!check_ajax_referer('vms_ticketing_claims_log_client_action', 'nonce', false)) {",
		'if (!in_array($reason_code, $allowed, true)) {',
		$clientLogBody,
		'Client logging should still validate the nonce before reason validation.'
	);
	vms_test_assert_code_order(
		'vms_ticketing_claims_log_result(array(',
		"vms_ticketing_v2_ajax_send_success(array('ok' => true));",
		$clientLogBody,
		'Client logging should still record the result before emitting the final success response.'
	);
	vms_test_assert_code_count(1, 'vms_ticketing_claims_log_result(array(', $clientLogBody, 'Client logging should still perform exactly one log mutation.');
	vms_test_assert_not_contains('set_transient(', $clientLogBody, 'Client logging should not gain transient-based deduplication.');
	vms_test_assert_not_contains('wp_schedule_single_event(', $clientLogBody, 'Client logging should not gain retry scheduling.');
	vms_test_assert_not_contains('JSON_', $clientLogBody, 'Client logging should not gain JSON flags.');
	vms_test_assert_code_contains(
		<<<'PHP'
if (!is_user_logged_in()) {
	vms_ticketing_v2_ajax_send_error(array('message' => 'login_required'), 401);
}
PHP,
		$clientLogBody,
		'Client logging should keep the login_required 401 contract.'
	);
	vms_test_assert_code_contains(
		<<<'PHP'
if (!check_ajax_referer('vms_ticketing_claims_log_client_action', 'nonce', false)) {
	vms_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);
}
PHP,
		$clientLogBody,
		'Client logging should keep the bad_nonce 403 contract and exact nonce action/field.'
	);
	vms_test_assert_code_contains(
		<<<'PHP'
if (!in_array($reason_code, $allowed, true)) {
	vms_ticketing_v2_ajax_send_error(array('message' => 'invalid_reason'), 400);
}
PHP,
		$clientLogBody,
		'Client logging should keep the invalid_reason 400 contract.'
	);
	vms_test_assert_code_contains(
		"vms_ticketing_v2_ajax_send_success(array('ok' => true));",
		$clientLogBody,
		'Client logging should keep the final ok=true success payload.'
	);

	vms_test_assert_code_order(
		"if (!check_ajax_referer('vms_ticketing_claims_validate_assignee', 'nonce', false)) {",
		'if (!is_user_logged_in()) {',
		$assigneeBody,
		'Assignee validation should still check the nonce before the logged-in state.'
	);
	vms_test_assert_code_order(
		'if (!is_user_logged_in()) {',
		"if (\$product_id <= 0 || \$assignee_email === '') {",
		$assigneeBody,
		'Assignee validation should still perform the logged-in check before request-shape validation.'
	);
	vms_test_assert_not_contains('vms_ticketing_claims_log_result(', $assigneeBody, 'Assignee validation should remain read-only.');
	vms_test_assert_not_contains('JSON_', $assigneeBody, 'Assignee validation should not gain JSON flags.');
	vms_test_assert_not_contains('json_decode(', $assigneeBody, 'Mirror assignee validation should keep helper-backed existing_counts parsing.');
	vms_test_assert_code_contains(
		"\$existing_counts = vms_ticketing_claims_post_existing_counts();",
		$assigneeBody,
		'Mirror assignee validation should keep helper-backed existing_counts parsing.'
	);
	vms_test_assert_code_contains(
		"if (!isset(\$_POST['existing_counts']) || !is_array(\$_POST['existing_counts'])) {",
		$existingCountsHelperBody,
		'Mirror assignee validation should reject non-array existing_counts payloads before unslashing.'
	);
	vms_test_assert_code_order(
		"if (!isset(\$_POST['existing_counts']) || !is_array(\$_POST['existing_counts'])) {",
		"\$raw_existing_counts = wp_unslash(\$_POST['existing_counts']);",
		$existingCountsHelperBody,
		'Mirror assignee validation should reject non-array existing_counts payloads before unslashing.'
	);
	vms_test_assert_code_contains(
		<<<'PHP'
$raw_existing_counts = wp_unslash($_POST['existing_counts']);
PHP,
		$existingCountsHelperBody,
		'Mirror assignee validation should unslash existing_counts only after confirming the top-level array shape.'
	);
	vms_test_assert_code_contains(
		"return vms_ticketing_claims_parse_existing_counts_payload(\$raw_existing_counts);",
		$existingCountsHelperBody,
		'Mirror assignee validation should keep the shared existing_counts normalization helper.'
	);
	vms_test_assert_code_contains(
		<<<'PHP'
if (!check_ajax_referer('vms_ticketing_claims_validate_assignee', 'nonce', false)) {
	vms_ticketing_v2_ajax_send_error(array(
		'ok' => false,
		'message' => __('Session expired. Please refresh and try again.', 'backstage-venue-manager'),
		'reason_code' => 'bad_nonce',
	), 403);
}
PHP,
		$assigneeBody,
		'Assignee validation should keep the bad_nonce 403 contract and exact nonce action/field.'
	);
	vms_test_assert_code_contains(
		<<<'PHP'
if (!is_user_logged_in()) {
	vms_ticketing_v2_ajax_send_error(array(
		'ok' => false,
		'message' => __('Log in before checking approved guest emails for this ticket.', 'backstage-venue-manager'),
		'reason_code' => 'login_required',
	), 401);
}
PHP,
		$assigneeBody,
		'Assignee validation should keep the login_required 401 contract for logged-out requests.'
	);
	vms_test_assert_code_contains(
		<<<'PHP'
if ($product_id <= 0 || $assignee_email === '') {
	vms_ticketing_v2_ajax_send_error(array(
		'ok' => false,
		'message' => __('Enter a valid registered email address first.', 'backstage-venue-manager'),
		'reason_code' => 'invalid_request',
	), 400);
}
PHP,
		$assigneeBody,
		'Assignee validation should keep the invalid_request 400 contract.'
	);
	vms_test_assert_code_contains(
		<<<'PHP'
if (!function_exists('vms_ticketing_v2_resolve_verified_ticket_context')) {
	vms_ticketing_v2_ajax_send_error(array(
		'ok' => false,
		'message' => __('Ticket validation is temporarily unavailable.', 'backstage-venue-manager'),
		'reason_code' => 'context_unavailable',
	), 400);
}
PHP,
		$assigneeBody,
		'Assignee validation should keep the context_unavailable 400 contract.'
	);
	vms_test_assert_code_contains(
		<<<'PHP'
if ($visibility_mode !== 'verified') {
	vms_ticketing_v2_ajax_send_error(array(
		'ok' => false,
		'message' => __('This ticket does not support claim-ticket assignment.', 'backstage-venue-manager'),
		'reason_code' => 'ticket_not_verified',
	), 400);
}
PHP,
		$assigneeBody,
		'Assignee validation should keep the ticket_not_verified 400 contract.'
	);
	vms_test_assert_code_contains(
		<<<'PHP'
if ($event_id <= 0) {
	vms_ticketing_v2_ajax_send_error(array(
		'ok' => false,
		'message' => __('Could not determine the event for this ticket.', 'backstage-venue-manager'),
		'reason_code' => 'event_missing',
	), 400);
}
PHP,
		$assigneeBody,
		'Assignee validation should keep the event_missing 400 contract.'
	);
	vms_test_assert_code_contains(
		<<<'PHP'
if (!($user instanceof WP_User)) {
	vms_ticketing_v2_ajax_send_error(array(
		'ok' => false,
		'message' => function_exists('vms_ticketing_v2_claim_assignment_unknown_guest_message')
			? vms_ticketing_v2_claim_assignment_unknown_guest_message()
			: __("We couldn't find an approved qualified guest account for this email. The guest needs to register and be approved before this ticket can be claimed.", 'backstage-venue-manager'),
		'reason_code' => 'account_not_found',
		'ticket_label' => $ticket_label,
	), 200);
}
PHP,
		$assigneeBody,
		'Assignee validation should keep the account_not_found deliberate 200 error envelope.'
	);
	vms_test_assert_code_contains(
		<<<'PHP'
if ($eligible && $remaining_before_assignment <= 0) {
	$eligible = false;
	$reason_code = 'assignee_limit_reached';
PHP,
		$assigneeBody,
		'Assignee validation should still derive assignee_limit_reached before the shared deliberate-200 error envelope.'
	);
	vms_test_assert_code_contains(
		<<<'PHP'
if (!$eligible) {
	vms_ticketing_v2_ajax_send_error(array(
		'ok' => false,
		'message' => $message,
		'reason_code' => $reason_code,
		'assignee_email' => sanitize_email((string) $user->user_email),
		'ticket_label' => $ticket_label,
	), 200);
}
PHP,
		$assigneeBody,
		'Assignee validation should keep the shared deliberate-200 ineligible error envelope with the existing privacy-shaped fields.'
	);
	vms_test_assert_code_contains(
		<<<'PHP'
vms_ticketing_v2_ajax_send_success(array(
	'ok' => true,
	'message' => $message,
	'reason_code' => $reason_code,
	'assignee_email' => sanitize_email((string) $user->user_email),
	'ticket_label' => $ticket_label,
));
PHP,
		$assigneeBody,
		'Assignee validation should keep the final success envelope with the existing public fields and normal success status.'
	);

	$loginRequiredResult = vms_test_run_wrapper(
		'vms_ticketing_v2_ajax_send_error',
		array(array('message' => 'login_required'), 401),
		'claims-login-noise'
	);
	vms_test_assert_same(false, $loginRequiredResult['call']['success'], 'The wrapper should terminate claims login failures through wp_send_json_error().');
	vms_test_assert_same(array('message' => 'login_required'), $loginRequiredResult['call']['data'], 'The wrapper should preserve the claims login_required payload.');
	vms_test_assert_same(401, $loginRequiredResult['call']['status_code'], 'The wrapper should preserve the explicit 401 status.');
	vms_test_assert_same(false, $loginRequiredResult['call']['flag_state_at_send'], 'The wrapper should clear ownership before the 401 JSON send.');
	vms_test_assert_same(false, $loginRequiredResult['flag_after'], 'The wrapper should leave ownership cleared after the 401 response.');
	vms_test_assert_same($loginRequiredResult['call']['json'], $loginRequiredResult['output'], 'Owned output should not precede the 401 JSON payload.');
	vms_test_assert_not_contains('claims-login-noise', $loginRequiredResult['output'], 'Owned 401 noise should not leak into the JSON response.');

	$badNonceResult = vms_test_run_wrapper(
		'vms_ticketing_v2_ajax_send_error',
		array(array('ok' => false, 'message' => 'bad_nonce', 'reason_code' => 'bad_nonce'), 403),
		'claims-nonce-noise'
	);
	vms_test_assert_same(false, $badNonceResult['call']['success'], 'The wrapper should terminate bad_nonce claims failures through wp_send_json_error().');
	vms_test_assert_same(403, $badNonceResult['call']['status_code'], 'The wrapper should preserve the explicit 403 status.');
	vms_test_assert_same(false, $badNonceResult['call']['flag_state_at_send'], 'The wrapper should clear ownership before the 403 JSON send.');
	vms_test_assert_same($badNonceResult['call']['json'], $badNonceResult['output'], 'Owned output should not precede the 403 JSON payload.');
	vms_test_assert_not_contains('claims-nonce-noise', $badNonceResult['output'], 'Owned 403 noise should not leak into the JSON response.');

	$invalidRequestResult = vms_test_run_wrapper(
		'vms_ticketing_v2_ajax_send_error',
		array(array('ok' => false, 'message' => 'invalid_request', 'reason_code' => 'invalid_request'), 400),
		'claims-invalid-noise'
	);
	vms_test_assert_same(false, $invalidRequestResult['call']['success'], 'The wrapper should terminate invalid-request claims failures through wp_send_json_error().');
	vms_test_assert_same(400, $invalidRequestResult['call']['status_code'], 'The wrapper should preserve the explicit 400 status.');
	vms_test_assert_same(false, $invalidRequestResult['call']['flag_state_at_send'], 'The wrapper should clear ownership before the 400 JSON send.');
	vms_test_assert_same($invalidRequestResult['call']['json'], $invalidRequestResult['output'], 'Owned output should not precede the 400 JSON payload.');
	vms_test_assert_not_contains('claims-invalid-noise', $invalidRequestResult['output'], 'Owned 400 noise should not leak into the JSON response.');

	$deliberate200Payload = array(
		'ok' => false,
		'message' => 'This email is not approved for this ticket yet.',
		'reason_code' => 'assignee_limit_reached',
		'assignee_email' => 'guest@example.com',
		'ticket_label' => 'VIP Admission',
	);
	$deliberate200Result = vms_test_run_wrapper(
		'vms_ticketing_v2_ajax_send_error',
		array($deliberate200Payload, 200),
		'claims-200-noise'
	);
	vms_test_assert_same(false, $deliberate200Result['call']['success'], 'The wrapper should keep deliberate claims 200 failures on the error sender.');
	vms_test_assert_same($deliberate200Payload, $deliberate200Result['call']['data'], 'The wrapper should preserve deliberate claims 200 payloads unchanged.');
	vms_test_assert_same(200, $deliberate200Result['call']['status_code'], 'The wrapper should preserve the deliberate HTTP 200 error status.');
	vms_test_assert_same(false, $deliberate200Result['call']['flag_state_at_send'], 'The wrapper should clear ownership before the deliberate 200 JSON send.');
	vms_test_assert_same(false, $deliberate200Result['flag_after'], 'The wrapper should leave ownership cleared after the deliberate 200 response.');
	vms_test_assert_same($deliberate200Result['call']['json'], $deliberate200Result['output'], 'Owned output should not precede the deliberate 200 JSON payload.');
	vms_test_assert_not_contains('claims-200-noise', $deliberate200Result['output'], 'Owned deliberate-200 noise should not leak into the JSON response.');
	vms_test_assert_true(!array_key_exists('_vms_ajax_noise', $deliberate200Result['call']['data']), 'Claims deliberate-200 payloads must not gain legacy diagnostic noise.');

	$successPayload = array(
		'ok' => true,
		'message' => 'Eligible account confirmed for this ticket.',
		'reason_code' => 'ok',
		'assignee_email' => 'guest@example.com',
		'ticket_label' => 'VIP Admission',
		'meta' => array(
			'remaining_after_assignment' => 1,
			'eligible' => true,
		),
	);
	$successResult = vms_test_run_wrapper(
		'vms_ticketing_v2_ajax_send_success',
		array($successPayload),
		'claims-success-noise'
	);
	vms_test_assert_same(true, $successResult['call']['success'], 'The wrapper should terminate claims success payloads through wp_send_json_success().');
	vms_test_assert_same($successPayload, $successResult['call']['data'], 'The wrapper should preserve claims success payloads unchanged, including nested arrays.');
	vms_test_assert_same(null, $successResult['call']['status_code'], 'The wrapper should preserve the normal default success status when no explicit status is supplied.');
	vms_test_assert_same(1, $successResult['call']['num_args'], 'The wrapper should preserve the default success path when no explicit status or flags are supplied.');
	vms_test_assert_same(false, $successResult['call']['flag_state_at_send'], 'The wrapper should clear ownership before the success JSON send.');
	vms_test_assert_same(false, $successResult['flag_after'], 'The wrapper should leave ownership cleared after the success response.');
	vms_test_assert_same($successResult['call']['json'], $successResult['output'], 'Owned output should not precede the success JSON payload.');
	vms_test_assert_not_contains('claims-success-noise', $successResult['output'], 'Owned success noise should not leak into the JSON response.');
	vms_test_assert_true(!array_key_exists('_vms_ajax_noise', $successResult['call']['data']), 'Claims success payloads must not gain legacy diagnostic noise.');

	fwrite(STDOUT, "ticketing claims AJAX output buffer ownership: PASS\n");
} catch (Throwable $throwable) {
	fwrite(STDERR, $throwable->getMessage() . "\n");
	exit(1);
}
