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
		'is_ajax' => false,
		'is_json' => false,
		'is_feed' => false,
		'is_trackback' => false,
		'is_robots' => false,
		'is_singular' => true,
		'singular_post_type' => 'tribe_events',
		'queried_object_id' => 0,
		'current_post_id' => 0,
		'post_types' => array(),
		'cancelled_event_ids' => array(),
		'plan_ids' => array(),
		'post_content' => array(),
		'render_output' => array(),
		'render_calls' => array(),
		'main_query' => true,
		'in_the_loop' => true,
	);
}

function is_admin(): bool
{
	return !empty($GLOBALS['vms_test_context']['is_admin']);
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

function is_trackback(): bool
{
	return !empty($GLOBALS['vms_test_context']['is_trackback']);
}

function is_robots(): bool
{
	return !empty($GLOBALS['vms_test_context']['is_robots']);
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

function bvmgr_tec_is_cancelled_event(int $eventId): bool
{
	return in_array($eventId, (array) ($GLOBALS['vms_test_context']['cancelled_event_ids'] ?? array()), true);
}

function bvmgr_ticketing_v2_find_plan_id_by_tec_event_id(int $eventId): int
{
	return (int) ($GLOBALS['vms_test_context']['plan_ids'][$eventId] ?? 0);
}

function vms_ticketing_v2_render_entitlements_block(int $tecEventId, int $planId): string
{
	$GLOBALS['vms_test_context']['render_calls'][] = array($tecEventId, $planId);
	return (string) ($GLOBALS['vms_test_context']['render_output'][$tecEventId] ?? '');
}

/**
 * @param mixed $post
 */
function get_post_field(string $field, $post = null): string
{
	if ($field !== 'post_content') {
		return '';
	}

	$postId = (int) $post;
	return (string) ($GLOBALS['vms_test_context']['post_content'][$postId] ?? '');
}

function has_shortcode(string $content, string $tag): bool
{
	return strpos($content, '[' . $tag) !== false;
}

function is_main_query(): bool
{
	return !empty($GLOBALS['vms_test_context']['main_query']);
}

function in_the_loop(): bool
{
	return !empty($GLOBALS['vms_test_context']['in_the_loop']);
}

function get_the_ID(): int
{
	return (int) ($GLOBALS['vms_test_context']['current_post_id'] ?? 0);
}

final class Vms_Test_Ticketing_Template
{
	/** @var array<string,mixed> */
	private $values;

	/**
	 * @param array<string,mixed> $values
	 */
	public function __construct(array $values)
	{
		$this->values = $values;
	}

	/**
	 * @param mixed $default
	 * @return mixed
	 */
	public function get(string $key, $default = null)
	{
		return $this->values[$key] ?? $default;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function get_values(): array
	{
		return $this->values;
	}
}

try {
	$pluginRoot = dirname(__DIR__);
	$ticketingRulesPath = $pluginRoot . '/includes/integrations/ticketing-rules-v2.php';
	$ticketingRulesSource = vms_test_read_file($ticketingRulesPath);

	$footerRegistration = vms_test_find_filter_registration(
		$ticketingRulesSource,
		'tribe_template_before_include_html:tickets/v2/tickets/footer',
		'vms_ticketing_v2_filter_ticket_footer_with_entitlements_mount'
	);
	vms_test_assert_true(is_array($footerRegistration), 'Native footer placement should be registered on the TEC footer seam.');
	vms_test_assert_same(20, $footerRegistration['priority'], 'Native footer placement should run at priority 20.');
	vms_test_assert_same(4, $footerRegistration['accepted_args'], 'Native footer placement should accept the full four-argument TEC footer contract.');
	vms_test_assert_not_contains("add_action('template_redirect', 'vms_ticketing_v2_server_mount_boot'", $ticketingRulesSource, 'The obsolete server-mount template_redirect opener should be removed.');
	vms_test_assert_not_contains('function vms_ticketing_v2_server_mount_boot(', $ticketingRulesSource, 'The obsolete server-mount opener should be removed.');
	vms_test_assert_not_contains('function vms_ticketing_v2_server_mount_callback(', $ticketingRulesSource, 'The obsolete server-mount callback should be removed.');
	vms_test_assert_not_contains('function vms_ticketing_v2_strip_disabled_ticket_rows_from_html(', $ticketingRulesSource, 'The obsolete server-mount disabled-row stripping helper should be removed.');
	vms_test_assert_not_contains('function vms_ticketing_v2_strip_cancelled_event_purchase_blocks(', $ticketingRulesSource, 'The obsolete server-mount cancellation stripping helper should be removed.');
	vms_test_assert_not_contains('function vms_ticketing_v2_extract_div_block_at(', $ticketingRulesSource, 'The obsolete server-mount HTML extraction helper should be removed.');
	vms_test_assert_not_contains('function vms_ticketing_v2_remove_element_with_id(', $ticketingRulesSource, 'The obsolete server-mount element-removal helper should be removed.');
	vms_test_assert_not_contains('function vms_ticketing_v2_extract_named_div_block(', $ticketingRulesSource, 'The obsolete server-mount named-div extraction helper should be removed.');
	vms_test_assert_not_contains('function vms_ticketing_v2_find_tag_open_before(', $ticketingRulesSource, 'The obsolete server-mount tag scanner should be removed.');
	vms_test_assert_not_contains('function vms_ticketing_v2_opening_div_has_class(', $ticketingRulesSource, 'The obsolete server-mount class matcher should be removed.');
	vms_test_assert_not_contains("ob_start('vms_ticketing_v2_server_mount_callback')", $ticketingRulesSource, 'The obsolete server-mount callback buffer should be removed.');

	$footerFunctionSource = vms_test_extract_function($ticketingRulesSource, 'vms_ticketing_v2_filter_ticket_footer_with_entitlements_mount');
	$appendFunctionSource = vms_test_extract_function($ticketingRulesSource, 'vms_ticketing_v2_append_entitlements_to_tec_event');
	vms_test_assert_not_contains('ob_start(', $footerFunctionSource, 'The native footer callback should not open any output buffer.');
	vms_test_assert_not_contains('ob_start(', $appendFunctionSource, 'The automatic append fallback should not open any output buffer.');

	vms_test_assert_not_contains('<script>', $ticketingRulesSource, 'The declarative renderer contract should not emit raw executable <script> blocks.');
	vms_test_assert_not_contains('</script>', $ticketingRulesSource, 'The declarative renderer contract should not emit raw executable </script> tags.');
	vms_test_assert_not_contains('window.__vmsInlineTicketingController', $ticketingRulesSource, 'The declarative renderer contract should not retain the removed inline controller bootstrap.');
	vms_test_assert_not_contains('data-vms-inline-controller-owner', $ticketingRulesSource, 'The declarative renderer contract should not retain the removed inline-controller owner marker.');
	vms_test_assert_not_contains('assets/vms-ticketing-front-server-controls.js', $ticketingRulesSource, 'The declarative renderer contract should not retain the dormant sidecar as a runtime dependency.');

	eval(vms_test_extract_function($ticketingRulesSource, 'vms_ticketing_v2_native_footer_mount_placed'));
	eval($footerFunctionSource);
	eval($appendFunctionSource);

	$validFooterPath = '/tmp/vendor/event-tickets/src/views/v2/tickets/footer.php';
	$footerHtml = "<footer class=\"tribe-tickets-footer\">Footer</footer>";
	$renderedMarkup = "<section id=\"vms-reserved-addons\">Mounted</section>";
	$expectedMount = "<div\n"
		. "    id=\"vms-addon-mount\"\n"
		. "    class=\"vms-addon-mount vms-addon-mount--server\"\n"
		. ">\n"
		. $renderedMarkup
		. "\n</div>\n";

	vms_test_reset_context();
	$GLOBALS['vms_test_context']['is_admin'] = true;
	$GLOBALS['vms_test_context']['post_types'][101] = 'tribe_events';
	$GLOBALS['vms_test_context']['plan_ids'][101] = 501;
	$GLOBALS['vms_test_context']['render_output'][101] = $renderedMarkup;
	[$result, $output, $beforeLevel, $afterLevel] = vms_test_call_with_capture(
		static function () use ($footerHtml, $validFooterPath): string {
			return vms_ticketing_v2_filter_ticket_footer_with_entitlements_mount($footerHtml, $validFooterPath, array('tickets', 'v2', 'tickets', 'footer'), new Vms_Test_Ticketing_Template(array('post_id' => 101)));
		}
	);
	vms_test_assert_same($footerHtml, $result, 'Admin requests should leave the native footer unchanged.');
	vms_test_assert_same('', $output, 'Admin requests should not emit direct output.');
	vms_test_assert_same($beforeLevel, $afterLevel, 'Admin requests should not leak output buffers.');

	foreach (array(
		'ajax' => 'is_ajax',
		'json' => 'is_json',
		'feed' => 'is_feed',
		'trackback' => 'is_trackback',
		'robots' => 'is_robots',
	) as $label => $flag) {
		vms_test_reset_context();
		$GLOBALS['vms_test_context'][$flag] = true;
		$GLOBALS['vms_test_context']['post_types'][102] = 'tribe_events';
		$GLOBALS['vms_test_context']['plan_ids'][102] = 502;
		$GLOBALS['vms_test_context']['render_output'][102] = $renderedMarkup;
		[$result, $output, $beforeLevel, $afterLevel] = vms_test_call_with_capture(
			static function () use ($footerHtml, $validFooterPath): string {
				return vms_ticketing_v2_filter_ticket_footer_with_entitlements_mount($footerHtml, $validFooterPath, array('tickets', 'v2', 'tickets', 'footer'), new Vms_Test_Ticketing_Template(array('post_id' => 102)));
			}
		);
		vms_test_assert_same($footerHtml, $result, strtoupper($label) . ' requests should leave the native footer unchanged.');
		vms_test_assert_same('', $output, strtoupper($label) . ' requests should not emit direct output.');
		vms_test_assert_same($beforeLevel, $afterLevel, strtoupper($label) . ' requests should not leak output buffers.');
	}

	vms_test_reset_context();
	$GLOBALS['vms_test_context']['is_singular'] = false;
	[$result] = vms_test_call_with_capture(
		static function () use ($footerHtml, $validFooterPath): string {
			return vms_ticketing_v2_filter_ticket_footer_with_entitlements_mount($footerHtml, $validFooterPath, array(), new Vms_Test_Ticketing_Template(array('post_id' => 0)));
		}
	);
	vms_test_assert_same($footerHtml, $result, 'Invalid event targets should leave the native footer unchanged.');

	vms_test_reset_context();
	$GLOBALS['vms_test_context']['post_types'][103] = 'post';
	[$result] = vms_test_call_with_capture(
		static function () use ($footerHtml, $validFooterPath): string {
			return vms_ticketing_v2_filter_ticket_footer_with_entitlements_mount($footerHtml, $validFooterPath, array(), new Vms_Test_Ticketing_Template(array('post_id' => 103)));
		}
	);
	vms_test_assert_same($footerHtml, $result, 'Non-event targets should leave the native footer unchanged.');

	vms_test_reset_context();
	$GLOBALS['vms_test_context']['post_types'][104] = 'tribe_events';
	$GLOBALS['vms_test_context']['cancelled_event_ids'][] = 104;
	[$result] = vms_test_call_with_capture(
		static function () use ($footerHtml, $validFooterPath): string {
			return vms_ticketing_v2_filter_ticket_footer_with_entitlements_mount($footerHtml, $validFooterPath, array(), new Vms_Test_Ticketing_Template(array('post_id' => 104)));
		}
	);
	vms_test_assert_same($footerHtml, $result, 'Cancelled events should leave the native footer unchanged.');

	vms_test_reset_context();
	$GLOBALS['vms_test_context']['post_types'][105] = 'tribe_events';
	[$result] = vms_test_call_with_capture(
		static function () use ($footerHtml, $validFooterPath): string {
			return vms_ticketing_v2_filter_ticket_footer_with_entitlements_mount($footerHtml, $validFooterPath, array(), new Vms_Test_Ticketing_Template(array('post_id' => 105)));
		}
	);
	vms_test_assert_same($footerHtml, $result, 'Events without a VMS plan should leave the native footer unchanged.');

	vms_test_reset_context();
	$GLOBALS['vms_test_context']['post_types'][106] = 'tribe_events';
	$GLOBALS['vms_test_context']['plan_ids'][106] = 506;
	$GLOBALS['vms_test_context']['post_content'][106] = 'Before [vms_reserved_add_ons] after';
	$GLOBALS['vms_test_context']['render_output'][106] = $renderedMarkup;
	[$result] = vms_test_call_with_capture(
		static function () use ($footerHtml, $validFooterPath): string {
			return vms_ticketing_v2_filter_ticket_footer_with_entitlements_mount($footerHtml, $validFooterPath, array(), new Vms_Test_Ticketing_Template(array('post_id' => 106)));
		}
	);
	vms_test_assert_same($footerHtml, $result, 'Manual shortcode placement should keep precedence over native footer placement.');

	vms_test_reset_context();
	$GLOBALS['vms_test_context']['post_types'][107] = 'tribe_events';
	$GLOBALS['vms_test_context']['plan_ids'][107] = 507;
	$GLOBALS['vms_test_context']['render_output'][107] = '';
	[$result] = vms_test_call_with_capture(
		static function () use ($footerHtml, $validFooterPath): string {
			return vms_ticketing_v2_filter_ticket_footer_with_entitlements_mount($footerHtml, $validFooterPath, array(), new Vms_Test_Ticketing_Template(array('post_id' => 107)));
		}
	);
	vms_test_assert_same($footerHtml, $result, 'Empty renderer output should leave the native footer unchanged.');

	vms_test_reset_context();
	$GLOBALS['vms_test_context']['queried_object_id'] = 999;
	$GLOBALS['vms_test_context']['post_types'][120] = 'tribe_events';
	$GLOBALS['vms_test_context']['plan_ids'][120] = 520;
	$GLOBALS['vms_test_context']['render_output'][120] = $renderedMarkup;
	[$result, $output, $beforeLevel, $afterLevel] = vms_test_call_with_capture(
		static function () use ($footerHtml, $validFooterPath): string {
			return vms_ticketing_v2_filter_ticket_footer_with_entitlements_mount($footerHtml, $validFooterPath, array(), new Vms_Test_Ticketing_Template(array('post_id' => 120)));
		}
	);
	vms_test_assert_same($expectedMount . $footerHtml, $result, 'Valid footer placement should prepend exactly one add-on mount ahead of the original footer.');
	vms_test_assert_same($expectedMount, substr($result, 0, strlen($expectedMount)), 'Valid footer placement should prepend the exact declarative mount markup.');
	vms_test_assert_same($footerHtml, substr($result, strlen($expectedMount)), 'Valid footer placement should preserve the original footer bytes unchanged after the inserted mount.');
	vms_test_assert_same('', $output, 'Valid footer placement should not emit direct output.');
	vms_test_assert_same($beforeLevel, $afterLevel, 'Valid footer placement should not open any output buffer.');
	vms_test_assert_same(array(array(120, 520)), $GLOBALS['vms_test_context']['render_calls'], 'Valid footer placement should render entitlements for the template-derived event and resolved plan.');

	[$result] = vms_test_call_with_capture(
		static function () use ($footerHtml, $validFooterPath): string {
			return vms_ticketing_v2_filter_ticket_footer_with_entitlements_mount($footerHtml, $validFooterPath, array(), new Vms_Test_Ticketing_Template(array('post_id' => 120)));
		}
	);
	vms_test_assert_same($footerHtml, $result, 'Repeated invocation for the same event should not duplicate the native footer mount.');

	vms_test_reset_context();
	$GLOBALS['vms_test_context']['post_types'][121] = 'tribe_events';
	$GLOBALS['vms_test_context']['plan_ids'][121] = 521;
	$GLOBALS['vms_test_context']['render_output'][121] = $renderedMarkup;
	[$result] = vms_test_call_with_capture(
		static function () use ($footerHtml, $validFooterPath, $expectedMount): string {
			return vms_ticketing_v2_filter_ticket_footer_with_entitlements_mount($footerHtml, $validFooterPath, array(), new Vms_Test_Ticketing_Template(array('post_id' => 121)));
		}
	);
	vms_test_assert_same($expectedMount . $footerHtml, $result, 'Different event IDs should remain independently renderable after another event has already mounted.');

	vms_test_reset_context();
	$GLOBALS['vms_test_context']['current_post_id'] = 120;
	$GLOBALS['vms_test_context']['queried_object_id'] = 120;
	$GLOBALS['vms_test_context']['post_types'][120] = 'tribe_events';
	$GLOBALS['vms_test_context']['plan_ids'][120] = 520;
	$GLOBALS['vms_test_context']['render_output'][120] = $renderedMarkup;
	$content = "<article>Event body</article>";
	[$result] = vms_test_call_with_capture(
		static function () use ($content): string {
			return vms_ticketing_v2_append_entitlements_to_tec_event($content);
		}
	);
	vms_test_assert_same($content, $result, 'Successful native footer placement should suppress the later automatic append fallback for the same event.');

	vms_test_reset_context();
	$GLOBALS['vms_test_context']['current_post_id'] = 122;
	$GLOBALS['vms_test_context']['queried_object_id'] = 122;
	$GLOBALS['vms_test_context']['post_types'][122] = 'tribe_events';
	$GLOBALS['vms_test_context']['plan_ids'][122] = 522;
	$GLOBALS['vms_test_context']['render_output'][122] = $renderedMarkup;
	[$result] = vms_test_call_with_capture(
		static function () use ($content): string {
			return vms_ticketing_v2_append_entitlements_to_tec_event($content);
		}
	);
	vms_test_assert_same($content . $renderedMarkup, $result, 'Absent native footer placement should preserve the automatic append fallback.');
	vms_test_assert_same(1, substr_count($result, 'id="vms-reserved-addons"'), 'Automatic append fallback should not duplicate the reserved-addons root.');
	vms_test_assert_same(0, substr_count($result, 'id="vms-addon-mount"'), 'Automatic append fallback should not create the native footer mount host.');

	vms_test_reset_context();
	$GLOBALS['vms_test_context']['current_post_id'] = 123;
	$GLOBALS['vms_test_context']['queried_object_id'] = 123;
	$GLOBALS['vms_test_context']['post_types'][123] = 'tribe_events';
	$GLOBALS['vms_test_context']['plan_ids'][123] = 523;
	$GLOBALS['vms_test_context']['post_content'][123] = 'Manual [vms_reserved_add_ons] block';
	$GLOBALS['vms_test_context']['render_output'][123] = $renderedMarkup;
	[$result] = vms_test_call_with_capture(
		static function () use ($content): string {
			return vms_ticketing_v2_append_entitlements_to_tec_event($content . ' [vms_reserved_add_ons]');
		}
	);
	vms_test_assert_same($content . ' [vms_reserved_add_ons]', $result, 'Automatic append should preserve shortcode precedence.');

	vms_test_reset_context();
	$GLOBALS['vms_test_context']['current_post_id'] = 124;
	$GLOBALS['vms_test_context']['queried_object_id'] = 124;
	$GLOBALS['vms_test_context']['post_types'][124] = 'tribe_events';
	$GLOBALS['vms_test_context']['plan_ids'][124] = 524;
	$GLOBALS['vms_test_context']['cancelled_event_ids'][] = 124;
	$GLOBALS['vms_test_context']['render_output'][124] = $renderedMarkup;
	[$result] = vms_test_call_with_capture(
		static function () use ($content): string {
			return vms_ticketing_v2_append_entitlements_to_tec_event($content);
		}
	);
	vms_test_assert_same($content, $result, 'Cancelled events should still suppress the append fallback.');

	vms_test_reset_context();
	$GLOBALS['vms_test_context']['post_types'][125] = 'tribe_events';
	$GLOBALS['vms_test_context']['plan_ids'][125] = 525;
	$GLOBALS['vms_test_context']['render_output'][125] = $renderedMarkup;
	define('REST_REQUEST', true);
	[$result] = vms_test_call_with_capture(
		static function () use ($footerHtml, $validFooterPath): string {
			return vms_ticketing_v2_filter_ticket_footer_with_entitlements_mount($footerHtml, $validFooterPath, array(), new Vms_Test_Ticketing_Template(array('post_id' => 125)));
		}
	);
	vms_test_assert_same($footerHtml, $result, 'REST requests should leave the native footer unchanged.');

	fwrite(STDOUT, "ticketing server-mount native footer remediation: PASS\n");
} catch (Throwable $throwable) {
	fwrite(STDERR, $throwable->getMessage() . "\n");
	exit(1);
}
