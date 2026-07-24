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

/**
 * @return array{priority:int,accepted_args:int}|null
 */
function vms_test_find_filter_registration(string $source, string $hook, string $callback): ?array
{
	$pattern = sprintf(
		'~add_filter\(\s*[\'"]%s[\'"]\s*,\s*[\'"]%s[\'"](?:\s*,\s*(\d+))?(?:\s*,\s*(\d+))?~',
		preg_quote($hook, '~'),
		preg_quote($callback, '~')
	);
	if (!preg_match($pattern, $source, $matches)) {
		return null;
	}

	return array(
		'priority' => (!isset($matches[1]) || $matches[1] === '') ? 10 : (int) $matches[1],
		'accepted_args' => (!isset($matches[2]) || $matches[2] === '') ? 1 : (int) $matches[2],
	);
}

/**
 * @return array{total_count:int,type_count:int,type_label:string,should_render:bool}
 */
function vms_test_native_totals(array $countByType): array
{
	$totalCount = 0;
	$typeCount = 0;
	$typeLabel = '';

	foreach ($countByType as $item) {
		if (!is_array($item) || empty($item['count'])) {
			continue;
		}

		$count = (int) $item['count'];
		$typeLabel = ($count > 1)
			? (string) ($item['plural'] ?? '')
			: (string) ($item['singular'] ?? '');
		$totalCount += $count;
		$typeCount++;
	}

	return array(
		'total_count' => $totalCount,
		'type_count' => $typeCount,
		'type_label' => $typeLabel,
		'should_render' => !empty($totalCount),
	);
}

/**
 * @param mixed $value
 */
function absint($value): int
{
	return abs((int) $value);
}

function vms_test_reset_context(): void
{
	$GLOBALS['vms_test_context'] = array(
		'is_admin' => false,
		'is_logged_in' => true,
		'is_ajax' => false,
		'is_json' => false,
		'is_feed' => false,
		'is_singular' => true,
		'singular_post_type' => 'tribe_events',
		'post_types' => array(123 => 'tribe_events', 456 => 'post'),
		'current_user_id' => 77,
		'active_count' => -1,
		'ticket_singular' => 'Ticket',
		'ticket_plural' => 'Tickets',
		'helper_calls' => array(),
		'label_calls' => array(),
	);
}

function is_admin(): bool
{
	return !empty($GLOBALS['vms_test_context']['is_admin']);
}

function is_user_logged_in(): bool
{
	return !empty($GLOBALS['vms_test_context']['is_logged_in']);
}

function wp_doing_ajax(): bool
{
	return !empty($GLOBALS['vms_test_context']['is_ajax']);
}

function wp_is_json_request(): bool
{
	return !empty($GLOBALS['vms_test_context']['is_json']);
}

function is_feed(): bool
{
	return !empty($GLOBALS['vms_test_context']['is_feed']);
}

function is_singular($postType = ''): bool
{
	if (empty($GLOBALS['vms_test_context']['is_singular'])) {
		return false;
	}

	if ($postType === '' || $postType === null) {
		return true;
	}

	return (string) $postType === (string) ($GLOBALS['vms_test_context']['singular_post_type'] ?? '');
}

/**
 * @param mixed $post
 */
function get_post_type($post = null): string
{
	$postId = (int) $post;
	$postTypes = $GLOBALS['vms_test_context']['post_types'] ?? array();
	return (string) ($postTypes[$postId] ?? '');
}

function get_current_user_id(): int
{
	return (int) ($GLOBALS['vms_test_context']['current_user_id'] ?? 0);
}

function tribe_get_ticket_label_singular(string $context = ''): string
{
	$GLOBALS['vms_test_context']['label_calls'][] = array('singular', $context);
	return (string) ($GLOBALS['vms_test_context']['ticket_singular'] ?? 'Ticket');
}

function tribe_get_ticket_label_plural(string $context = ''): string
{
	$GLOBALS['vms_test_context']['label_calls'][] = array('plural', $context);
	return (string) ($GLOBALS['vms_test_context']['ticket_plural'] ?? 'Tickets');
}

function vms_ticketing_v2_active_ticket_count_for_event_user(int $eventId, int $userId): int
{
	$GLOBALS['vms_test_context']['helper_calls'][] = array($eventId, $userId);
	return (int) ($GLOBALS['vms_test_context']['active_count'] ?? -1);
}

/**
 * @param array<string,mixed> $overrides
 * @param array<string,mixed> $countByType
 * @return array{result:array,output:string,helper_calls:array,label_calls:array}
 */
function vms_test_run_case(array $overrides, array $countByType, int $eventId, int $userId): array
{
	vms_test_reset_context();
	$GLOBALS['vms_test_context'] = array_replace($GLOBALS['vms_test_context'], $overrides);

	ob_start();
	$result = vms_ticketing_v2_filter_my_tickets_link_ticket_count_by_type($countByType, $eventId, $userId);
	$output = ob_get_clean();
	if (!is_string($output)) {
		$output = '';
	}

	return array(
		'result' => $result,
		'output' => $output,
		'helper_calls' => (array) ($GLOBALS['vms_test_context']['helper_calls'] ?? array()),
		'label_calls' => (array) ($GLOBALS['vms_test_context']['label_calls'] ?? array()),
	);
}

/**
 * @param mixed $value
 */
function vms_test_assert_no_identity_keys($value, string $message): void
{
	if (!is_array($value)) {
		return;
	}

	foreach ($value as $key => $child) {
		$keyString = is_string($key) ? $key : '';
		vms_test_assert_true(!in_array($keyString, array('user_id', 'current_user_id'), true), $message . "\nUnexpected key: " . $keyString);
		vms_test_assert_no_identity_keys($child, $message);
	}
}

try {
	$pluginRoot = dirname(__DIR__);
	$sourcePath = $pluginRoot . '/includes/integrations/ticketing-rules-v2.php';
	$source = vms_test_read_file($sourcePath);

	$registration = vms_test_find_filter_registration(
		$source,
		'tec_tickets_my_tickets_link_ticket_count_by_type',
		'vms_ticketing_v2_filter_my_tickets_link_ticket_count_by_type'
	);
	vms_test_assert_true(is_array($registration), 'The native TEC My Tickets filter registration should exist.');
	vms_test_assert_same(99, $registration['priority'], 'The native TEC My Tickets filter should run at the expected late priority.');
	vms_test_assert_same(3, $registration['accepted_args'], 'The native TEC My Tickets filter should retain the installed accepted-argument count.');
	vms_test_assert_not_contains("add_action('template_redirect', 'vms_ticketing_v2_start_my_tickets_notice_buffer'", $source, 'The obsolete My Tickets template_redirect registration should be absent.');

	foreach (array(
		'vms_ticketing_v2_start_my_tickets_notice_buffer',
		'vms_ticketing_v2_filter_my_tickets_notice_html',
		'vms_ticketing_v2_my_tickets_notice_count',
		'vms_ticketing_v2_my_tickets_notice_buffer_started',
		'vms_ticketing_v2_rewrite_my_tickets_notice_html',
	) as $obsoleteSymbol) {
		vms_test_assert_not_contains($obsoleteSymbol, $source, 'Obsolete My Tickets buffer symbol should be removed.');
	}

	vms_test_assert_not_contains("ob_start('vms_ticketing_v2_filter_my_tickets_notice_html')", $source, 'No My Tickets callback-based output buffer should remain.');
	vms_test_assert_not_contains('You\s+have\s+\d+\s+Tickets?\s+for\s+this\s+Event', $source, 'No My Tickets full-page regex transformation should remain.');

	$callbackSource = vms_test_extract_function($source, 'vms_ticketing_v2_filter_my_tickets_link_ticket_count_by_type');
	vms_test_assert_contains('vms_ticketing_v2_active_ticket_count_for_event_user(', $callbackSource, 'The new callback should call the existing active-ticket-count helper.');
	vms_test_assert_not_contains('$GLOBALS', $callbackSource, 'The new callback should not write globals.');
	vms_test_assert_not_contains('ob_start(', $callbackSource, 'The new callback should not open output buffers.');
	vms_test_assert_not_contains('echo ', $callbackSource, 'The new callback should not echo output.');
	vms_test_assert_not_contains('wp_send_json', $callbackSource, 'The new callback should not terminate through JSON senders.');
	vms_test_assert_not_contains('get_queried_object_id(', $callbackSource, 'The new callback should not rely on queried-object fallback IDs.');
	vms_test_assert_not_contains('preg_match(', $callbackSource, 'The new callback should not parse whole-page HTML.');
	vms_test_assert_not_contains('preg_replace(', $callbackSource, 'The new callback should not parse whole-page HTML.');
	vms_test_assert_not_contains('stripos(', $callbackSource, 'The new callback should not inspect whole-page HTML strings.');

	eval($callbackSource);

	$baseCounts = array(
		'rsvp' => array('count' => 2, 'singular' => 'RSVP', 'plural' => 'RSVPs'),
		'ticket' => array('count' => 3, 'singular' => 'Ticket', 'plural' => 'Tickets'),
		'series' => array('count' => 4, 'singular' => 'Pass', 'plural' => 'Passes'),
	);

	$loggedOut = vms_test_run_case(
		array('is_logged_in' => false, 'current_user_id' => 0),
		$baseCounts,
		123,
		0
	);
	vms_test_assert_same($baseCounts, $loggedOut['result'], 'Logged-out requests should return the native counts unchanged.');
	vms_test_assert_same(array(), $loggedOut['helper_calls'], 'Logged-out requests should not query the active-ticket helper.');

	$adminRequest = vms_test_run_case(
		array('is_admin' => true),
		$baseCounts,
		123,
		77
	);
	vms_test_assert_same($baseCounts, $adminRequest['result'], 'Admin requests should return the native counts unchanged.');
	vms_test_assert_same(array(), $adminRequest['helper_calls'], 'Admin requests should not query the active-ticket helper.');

	$ajaxRequest = vms_test_run_case(
		array('is_ajax' => true),
		$baseCounts,
		123,
		77
	);
	vms_test_assert_same($baseCounts, $ajaxRequest['result'], 'AJAX requests should return the native counts unchanged.');
	vms_test_assert_same(array(), $ajaxRequest['helper_calls'], 'AJAX requests should not query the active-ticket helper.');

	$jsonRequest = vms_test_run_case(
		array('is_json' => true),
		$baseCounts,
		123,
		77
	);
	vms_test_assert_same($baseCounts, $jsonRequest['result'], 'JSON requests should return the native counts unchanged.');
	vms_test_assert_same(array(), $jsonRequest['helper_calls'], 'JSON requests should not query the active-ticket helper.');

	$feedRequest = vms_test_run_case(
		array('is_feed' => true),
		$baseCounts,
		123,
		77
	);
	vms_test_assert_same($baseCounts, $feedRequest['result'], 'Feed requests should return the native counts unchanged.');
	vms_test_assert_same(array(), $feedRequest['helper_calls'], 'Feed requests should not query the active-ticket helper.');

	$nonEventTarget = vms_test_run_case(
		array('post_types' => array(123 => 'post')),
		$baseCounts,
		123,
		77
	);
	vms_test_assert_same($baseCounts, $nonEventTarget['result'], 'Non-event targets should return the native counts unchanged.');
	vms_test_assert_same(array(), $nonEventTarget['helper_calls'], 'Non-event targets should not query the active-ticket helper.');

	$negativeCount = vms_test_run_case(
		array('active_count' => -1),
		$baseCounts,
		123,
		77
	);
	vms_test_assert_same($baseCounts, $negativeCount['result'], 'Negative helper counts should preserve the native counts unchanged.');
	vms_test_assert_same(array(array(123, 77)), $negativeCount['helper_calls'], 'Negative helper counts should still come from the existing active-ticket helper.');

	$zeroCount = vms_test_run_case(
		array('active_count' => 0),
		$baseCounts,
		123,
		77
	);
	$zeroTotals = vms_test_native_totals($zeroCount['result']);
	vms_test_assert_same(0, $zeroTotals['total_count'], 'Zero active tickets should force a native total of zero.');
	vms_test_assert_same(false, $zeroTotals['should_render'], 'Zero active tickets should suppress native notice rendering.');
	vms_test_assert_same(0, (int) $zeroCount['result']['ticket']['count'], 'Zero active tickets should leave the ticket count at zero.');

	$oneTicket = vms_test_run_case(
		array('active_count' => 1),
		$baseCounts,
		123,
		77
	);
	$oneTotals = vms_test_native_totals($oneTicket['result']);
	vms_test_assert_same(1, $oneTotals['total_count'], 'One active ticket should force the native total to one.');
	vms_test_assert_same(true, $oneTotals['should_render'], 'One active ticket should still allow native rendering.');
	vms_test_assert_same('Ticket', $oneTotals['type_label'], 'One active ticket should preserve native singular wording.');

	$multiTicketInput = $baseCounts;
	$multiTicketBefore = $multiTicketInput;
	$multiTicket = vms_test_run_case(
		array('active_count' => 6),
		$multiTicketInput,
		123,
		77
	);
	$multiTotals = vms_test_native_totals($multiTicket['result']);
	vms_test_assert_same(6, $multiTotals['total_count'], 'Multiple active tickets should force the native total to the exact helper count.');
	vms_test_assert_same('Tickets', $multiTotals['type_label'], 'Multiple active tickets should preserve native plural wording.');
	vms_test_assert_same(0, (int) $multiTicket['result']['rsvp']['count'], 'Incoming mixed RSVP counts should no longer contribute to the My Tickets native total.');
	vms_test_assert_same(0, (int) $multiTicket['result']['series']['count'], 'Incoming mixed series-pass counts should no longer contribute to the My Tickets native total.');
	vms_test_assert_same(6, (int) $multiTicket['result']['ticket']['count'], 'The ticket slot should carry the exact VMS active-ticket total.');
	vms_test_assert_same($multiTicketBefore, $multiTicketInput, 'The original input array should remain unchanged outside the returned filtered value.');
	vms_test_assert_same(array(array(123, 77)), $multiTicket['helper_calls'], 'The existing active-ticket helper should be called with the authoritative event and current user IDs.');
	vms_test_assert_same('', $multiTicket['output'], 'The new callback should not emit output.');
	vms_test_assert_no_identity_keys($multiTicket['result'], 'The filtered result should not expose user-identifying keys.');
	$multiJson = json_encode($multiTicket['result']);
	vms_test_assert_true(is_string($multiJson), 'The filtered mixed-count result should remain JSON-serializable.');
	vms_test_assert_not_contains('77', $multiJson, 'The filtered result should not leak the current user ID.');

	$emptyCounts = vms_test_run_case(
		array(
			'active_count' => 5,
			'ticket_singular' => 'Admission',
			'ticket_plural' => 'Admissions',
		),
		array(),
		123,
		77
	);
	$emptyTotals = vms_test_native_totals($emptyCounts['result']);
	vms_test_assert_true(is_array($emptyCounts['result']), 'Empty incoming count maps should still return an array.');
	vms_test_assert_same(5, $emptyTotals['total_count'], 'Empty incoming count maps should still produce the exact helper total.');
	vms_test_assert_same(5, (int) $emptyCounts['result']['ticket']['count'], 'Empty incoming count maps should materialize the native ticket entry with the exact helper total.');
	vms_test_assert_same('Admission', (string) $emptyCounts['result']['ticket']['singular'], 'Empty incoming count maps should still source native singular labels.');
	vms_test_assert_same('Admissions', (string) $emptyCounts['result']['ticket']['plural'], 'Empty incoming count maps should still source native plural labels.');
	vms_test_assert_same(array(array('singular', 'my-tickets-view-link'), array('plural', 'my-tickets-view-link')), $emptyCounts['label_calls'], 'Empty incoming count maps should derive ticket labels from the native label helpers.');

	$largeCount = vms_test_run_case(
		array('active_count' => 4096),
		$baseCounts,
		123,
		77
	);
	$largeTotals = vms_test_native_totals($largeCount['result']);
	vms_test_assert_same(4096, $largeTotals['total_count'], 'Large valid helper counts should remain stable.');
	vms_test_assert_same(4096, (int) $largeCount['result']['ticket']['count'], 'Large valid helper counts should remain nonnegative integers in the ticket slot.');

	fwrite(STDOUT, "ticketing My Tickets notice source-filter remediation: PASS\n");
} catch (Throwable $throwable) {
	fwrite(STDERR, $throwable->getMessage() . "\n");
	exit(1);
}
