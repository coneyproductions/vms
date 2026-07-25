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
	$pattern = '~function\s+' . preg_quote($name, '~') . '\s*\(~';
	if (!preg_match($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
		vms_test_fail('Unable to locate function ' . $name . '.');
	}
	$start = (int) $matches[0][1];

	$brace = strpos($source, '{', $start);
	if ($brace === false) {
		vms_test_fail('Unable to locate opening brace for ' . $name . '.');
	}

	$depth = 1;
	$length = strlen($source);
	for ($i = $brace + 1; $i < $length; $i++) {
		$char = $source[$i];
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
 * @return array{0:mixed,1:string,2:int,3:int}
 */
function vms_test_call_with_capture(callable $callback): array
{
	$before = ob_get_level();
	ob_start();
	try {
		$result = $callback();
		$output = (string) ob_get_clean();
	} catch (Throwable $throwable) {
		while (ob_get_level() > $before) {
			ob_end_clean();
		}
		throw $throwable;
	}

	return array($result, $output, $before, ob_get_level());
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
		'is_singular' => false,
		'singular_post_type' => 'tribe_events',
		'queried_object_id' => 0,
		'post_types' => array(),
		'cancelled_event_ids' => array(),
		'plan_ids' => array(),
		'disabled_by_plan' => array(),
		'disabled_helper_calls' => array(),
	);
}

function is_admin(): bool
{
	return !empty($GLOBALS['vms_test_context']['is_admin']);
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

function get_queried_object_id(): int
{
	return (int) ($GLOBALS['vms_test_context']['queried_object_id'] ?? 0);
}

/**
 * @param mixed $post
 */
function get_post_type($post = null): string
{
	$postId = (int) $post;
	return (string) ($GLOBALS['vms_test_context']['post_types'][$postId] ?? '');
}

function vms_tec_is_cancelled_event(int $eventId): bool
{
	return in_array($eventId, (array) ($GLOBALS['vms_test_context']['cancelled_event_ids'] ?? array()), true);
}

function vms_ticketing_v2_find_plan_id_by_tec_event_id(int $eventId): int
{
	return (int) ($GLOBALS['vms_test_context']['plan_ids'][$eventId] ?? 0);
}

function vms_ticketing_v2_disabled_ticket_products_for_plan(int $planId): array
{
	$GLOBALS['vms_test_context']['disabled_helper_calls'][] = $planId;
	return (array) ($GLOBALS['vms_test_context']['disabled_by_plan'][$planId] ?? array());
}

try {
	$pluginRoot = dirname(__DIR__);
	$ticketingRulesPath = $pluginRoot . '/includes/integrations/ticketing-rules-v2.php';
	$frontBundlePath = $pluginRoot . '/assets/vms-ticketing-front.js';
	$ticketingRulesSource = vms_test_read_file($ticketingRulesPath);
	$frontBundleSource = vms_test_read_file($frontBundlePath);

	$cancelledRegistration = vms_test_find_filter_registration(
		$ticketingRulesSource,
		'tribe_tickets_get_tickets_query_args',
		'vms_tec_suppress_tickets_for_cancelled_events'
	);
	vms_test_assert_true(is_array($cancelledRegistration), 'Cancelled-event suppression should remain registered on the native ticket query filter.');
	vms_test_assert_same(20, $cancelledRegistration['priority'], 'Cancelled-event suppression should remain at priority 20.');
	vms_test_assert_same(1, $cancelledRegistration['accepted_args'], 'Cancelled-event suppression should retain the one-argument filter contract.');

	$disabledRegistration = vms_test_find_filter_registration(
		$ticketingRulesSource,
		'tribe_tickets_get_tickets_query_args',
		'vms_ticketing_v2_filter_disabled_ticket_query_args'
	);
	vms_test_assert_true(is_array($disabledRegistration), 'Disabled-ticket suppression should be registered on the native ticket query filter.');
	vms_test_assert_same(30, $disabledRegistration['priority'], 'Disabled-ticket suppression should run after cancelled-event suppression.');
	vms_test_assert_same(1, $disabledRegistration['accepted_args'], 'Disabled-ticket suppression should retain the installed one-argument filter contract.');
	vms_test_assert_true($disabledRegistration['priority'] > $cancelledRegistration['priority'], 'Disabled-ticket suppression should run after cancelled-event suppression.');

	vms_test_assert_not_contains('function vms_ticketing_v2_strip_disabled_ticket_rows_from_html(', $ticketingRulesSource, 'Disabled rows should no longer be removed through server-mount HTML stripping.');
	vms_test_assert_contains("'disabledTicketProductIds' => array_values(array_unique(array_filter(array_map('absint', \$disabled_ticket_product_ids))))", $ticketingRulesSource, 'Frontend disabled-ticket ID localization should remain present.');
	vms_test_assert_contains("'disabledTicketMap' => \$disabled_ticket_map", $ticketingRulesSource, 'Frontend disabled-ticket map localization should remain present.');
	vms_test_assert_contains('function vms_ticketing_v2_disabled_ticket_products_for_plan(int $plan_id): array', $ticketingRulesSource, 'The existing disabled-ticket helper should remain present.');
	vms_test_assert_contains('phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in', $ticketingRulesSource, 'Disabled-ticket suppression should keep any bounded post__not_in exception limited to the single packaged Plugin Check rule.');
	vms_test_assert_contains('hideDisabledTicketRows(state)', $frontBundleSource, 'Frontend disabled-row hiding should remain available as a fail-closed backup.');
	vms_test_assert_contains('disabledTicketMap', $frontBundleSource, 'Frontend disabled-ticket mapping should remain available as a fail-closed backup.');

	eval(vms_test_extract_function($ticketingRulesSource, 'vms_ticketing_v2_ticket_query_event_meta_keys'));
	eval(vms_test_extract_function($ticketingRulesSource, 'vms_ticketing_v2_event_id_from_ticket_query_args'));
	eval(vms_test_extract_function($ticketingRulesSource, 'vms_ticketing_v2_filter_disabled_ticket_query_args'));

	vms_test_assert_same(
		array('_tribe_rsvp_for_event', '_tribe_tpp_for_event', '_tec_tickets_commerce_event'),
		vms_ticketing_v2_ticket_query_event_meta_keys(),
		'Ticket-query event meta-key resolution should retain the installed Event Tickets relation keys.'
	);

	vms_test_reset_context();
	$args = array('provider' => 'all', 'posts_per_page' => 25);
	[$result, $output, $beforeLevel, $afterLevel] = vms_test_call_with_capture(
		static function () use ($args): array {
			return vms_ticketing_v2_filter_disabled_ticket_query_args($args);
		}
	);
	vms_test_assert_same($args, $result, 'Invalid ticket-query event resolution should leave args unchanged.');
	vms_test_assert_same('', $output, 'Invalid ticket-query event resolution should not emit direct output.');
	vms_test_assert_same($beforeLevel, $afterLevel, 'Invalid ticket-query event resolution should not leak output buffers.');
	vms_test_assert_same(array(), $GLOBALS['vms_test_context']['disabled_helper_calls'], 'Invalid ticket-query event resolution should not consult the disabled-ticket helper.');

	vms_test_reset_context();
	$GLOBALS['vms_test_context']['post_types'][201] = 'post';
	$args = array('event' => 201, 'provider' => 'all');
	[$result] = vms_test_call_with_capture(
		static function () use ($args): array {
			return vms_ticketing_v2_filter_disabled_ticket_query_args($args);
		}
	);
	vms_test_assert_same($args, $result, 'Non-event targets should leave ticket query args unchanged.');

	vms_test_reset_context();
	$GLOBALS['vms_test_context']['post_types'][202] = 'tribe_events';
	$GLOBALS['vms_test_context']['cancelled_event_ids'][] = 202;
	$args = array('event' => 202, 'provider' => 'all');
	[$result] = vms_test_call_with_capture(
		static function () use ($args): array {
			return vms_ticketing_v2_filter_disabled_ticket_query_args($args);
		}
	);
	vms_test_assert_same($args, $result, 'Cancelled events should leave disabled-ticket suppression to the existing cancellation filter.');
	vms_test_assert_same(array(), $GLOBALS['vms_test_context']['disabled_helper_calls'], 'Cancelled events should not consult the disabled-ticket helper.');

	vms_test_reset_context();
	$GLOBALS['vms_test_context']['post_types'][203] = 'tribe_events';
	$args = array('event' => 203, 'provider' => 'all');
	[$result] = vms_test_call_with_capture(
		static function () use ($args): array {
			return vms_ticketing_v2_filter_disabled_ticket_query_args($args);
		}
	);
	vms_test_assert_same($args, $result, 'Events without a VMS plan should leave ticket query args unchanged.');
	vms_test_assert_same(array(), $GLOBALS['vms_test_context']['disabled_helper_calls'], 'Events without a VMS plan should not consult the disabled-ticket helper.');

	vms_test_reset_context();
	$GLOBALS['vms_test_context']['post_types'][204] = 'tribe_events';
	$GLOBALS['vms_test_context']['plan_ids'][204] = 604;
	$GLOBALS['vms_test_context']['disabled_by_plan'][604] = array('product_ids' => array());
	$args = array('event' => 204, 'provider' => 'all', 'posts_per_page' => 10);
	[$result] = vms_test_call_with_capture(
		static function () use ($args): array {
			return vms_ticketing_v2_filter_disabled_ticket_query_args($args);
		}
	);
	vms_test_assert_same($args, $result, 'Events with no disabled ticket IDs should leave query args unchanged.');
	vms_test_assert_same(array(604), $GLOBALS['vms_test_context']['disabled_helper_calls'], 'Events with no disabled ticket IDs should still consult the disabled-ticket helper for their plan.');

	vms_test_reset_context();
	$GLOBALS['vms_test_context']['post_types'][205] = 'tribe_events';
	$GLOBALS['vms_test_context']['plan_ids'][205] = 605;
	$GLOBALS['vms_test_context']['disabled_by_plan'][605] = array('product_ids' => array(9, '10', 10, 0, -1, '11'));
	$args = array(
		'event' => 205,
		'provider' => 'all',
		'post__not_in' => array(3, '9'),
		'posts_per_page' => 15,
	);
	[$result] = vms_test_call_with_capture(
		static function () use ($args): array {
			return vms_ticketing_v2_filter_disabled_ticket_query_args($args);
		}
	);
	vms_test_assert_same(array(3, 9, 10, 11), $result['post__not_in'], 'Disabled-ticket suppression should merge, normalize, and deduplicate native exclusion IDs.');
	vms_test_assert_same('all', $result['provider'], 'Disabled-ticket suppression should preserve unrelated provider query args.');
	vms_test_assert_same(15, $result['posts_per_page'], 'Disabled-ticket suppression should preserve unrelated pagination args.');
	vms_test_assert_same(array(605), $GLOBALS['vms_test_context']['disabled_helper_calls'], 'Disabled-ticket suppression should consult the helper with the resolved plan ID.');

	vms_test_reset_context();
	$GLOBALS['vms_test_context']['post_types'][206] = 'tribe_events';
	$GLOBALS['vms_test_context']['plan_ids'][206] = 606;
	$GLOBALS['vms_test_context']['disabled_by_plan'][606] = array('product_ids' => array(21, 22));
	$args = array(
		'meta_query' => array(
			'relation' => 'AND',
			array(
				'key' => '_tribe_tpp_for_event',
				'value' => 206,
			),
		),
		'provider' => 'commerce',
	);
	[$result] = vms_test_call_with_capture(
		static function () use ($args): array {
			return vms_ticketing_v2_filter_disabled_ticket_query_args($args);
		}
	);
	vms_test_assert_same(array(21, 22), $result['post__not_in'], 'Disabled-ticket suppression should resolve the authoritative event ID from the native query meta contract.');
	vms_test_assert_same('commerce', $result['provider'], 'Meta-query event resolution should preserve unrelated provider query args.');
	vms_test_assert_same(array(606), $GLOBALS['vms_test_context']['disabled_helper_calls'], 'Meta-query event resolution should consult the helper with the resolved plan ID.');

	vms_test_reset_context();
	$GLOBALS['vms_test_context']['is_singular'] = true;
	$GLOBALS['vms_test_context']['queried_object_id'] = 207;
	$GLOBALS['vms_test_context']['post_types'][207] = 'tribe_events';
	$GLOBALS['vms_test_context']['plan_ids'][207] = 607;
	$GLOBALS['vms_test_context']['disabled_by_plan'][607] = array('product_ids' => array(31));
	$args = array('provider' => 'all');
	[$result] = vms_test_call_with_capture(
		static function () use ($args): array {
			return vms_ticketing_v2_filter_disabled_ticket_query_args($args);
		}
	);
	vms_test_assert_same(array(31), $result['post__not_in'], 'Disabled-ticket suppression should fall back to the queried event on singular event requests when native args omit the event ID.');
	vms_test_assert_same(array(607), $GLOBALS['vms_test_context']['disabled_helper_calls'], 'Singular event fallback should consult the helper with the queried event plan ID.');

	fwrite(STDOUT, "ticketing disabled-ticket native suppression remediation: PASS\n");
} catch (Throwable $throwable) {
	fwrite(STDERR, $throwable->getMessage() . "\n");
	exit(1);
}
