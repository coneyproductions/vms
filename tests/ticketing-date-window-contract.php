<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/');
}
if (!defined('DAY_IN_SECONDS')) {
	define('DAY_IN_SECONDS', 86400);
}

// G15 ticketing date-window regression coverage is assembled below.
$GLOBALS['g15_assertion_count'] = 0;

function g15_assert(bool $condition, string $message): void
{
	$GLOBALS['g15_assertion_count']++;
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g15_same($expected, $actual, string $message): void
{
	g15_assert(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

function g15_read(string $path): string
{
	$source = file_get_contents($path);
	if (!is_string($source) || $source === '') {
		throw new RuntimeException('Unable to read source: ' . $path);
	}
	return $source;
}

function g15_extract_function(string $source, string $name): string
{
	$sourceName = $name;
	$start = strpos($source, 'function ' . $sourceName . '(');
	if ($start === false && strpos($name, 'bvmgr_') === 0) {
		$sourceName = 'vms_' . substr($name, strlen('bvmgr_'));
		$start = strpos($source, 'function ' . $sourceName . '(');
	}
	$brace = $start === false ? false : strpos($source, '{', $start);
	if ($start === false || $brace === false) {
		throw new RuntimeException('Unable to locate function: ' . $name);
	}

	$depth = 1;
	for ($index = $brace + 1, $length = strlen($source); $index < $length; $index++) {
		$depth += $source[$index] === '{' ? 1 : 0;
		$depth -= $source[$index] === '}' ? 1 : 0;
		if ($depth === 0) {
			return substr($source, $start, ($index - $start) + 1);
		}
	}
	throw new RuntimeException('Unable to parse function: ' . $name);
}

/** @return array{legacy_to_canonical:array<string,string>,canonical_to_legacy:array<string,string>} */
function g15_prepare_eval_function(string $source, string $name, string $test_name, array $replacements = array()): string
{
	$function = g15_extract_function($source, $name);
	$count = 0;
	$function = str_replace('function ' . $name . '(', 'function ' . $test_name . '(', $function, $count);
	g15_same(1, $count, 'Unable to rename runtime function ' . $name . '.');
	foreach ($replacements as $current => $replacement) {
		$count = 0;
		$function = str_replace($current, $replacement, $function, $count);
		g15_same(1, $count, 'Unable to apply deterministic replacement in ' . $name . '.');
	}
	return $function;
}

$root = dirname(__DIR__);
$sources = array('mirror'=>array(
    'monitor'=>g15_read($root.'/includes/ticketing/ticket-integrity-monitor.php'),
    'phase_b'=>g15_read($root.'/includes/integrations/ticketing-phase-b.php'),
));
$owned_functions = array(
	'monitor' => array('bvmgr_ticket_integrity_format_datetime', 'bvmgr_ticket_integrity_build_targets'),
	'phase_b' => array('bvmgr_ticketing_b_resolve_sales_window', 'bvmgr_ticketing_v2_get_plan_sales_window_defaults'),
);
foreach (array('mirror') as $tree) {
	$owned_source = '';
	foreach ($owned_functions as $source_key => $function_names) {
		foreach ($function_names as $function_name) {
			$owned_source .= "\n" . g15_extract_function($sources[$tree][$source_key], $function_name);
		}
	}
	g15_same(0, preg_match_all('/(?<![A-Za-z0-9_])date\s*\(/', $owned_source), $tree . ' owned functions must contain zero native date() calls.');
	g15_same(5, preg_match_all('/(?<![A-Za-z0-9_])wp_date\s*\(/', $owned_source), $tree . ' owned functions must contain exactly five wp_date() calls.');
	g15_assert(
		preg_match('/phpcs:(?:disable|enable|ignoreFile|ignore)[^\r\n]*(?:WordPress\.DateTime|RestrictedFunctions\.date_date)/i', $owned_source) !== 1,
		$tree . ' owned functions must not suppress DateTime findings.'
	);
	g15_assert(strpos($owned_source, "function_exists('wp_date')") === false, $tree . ' owned functions retain a dead wp_date() fallback.');
	g15_assert(strpos($owned_source, "function_exists('wp_timezone')") === false, $tree . ' owned functions retain a dead wp_timezone() fallback.');
}

$GLOBALS['g15_now'] = 0;
$GLOBALS['g15_timezone_name'] = 'UTC';
$GLOBALS['g15_wp_date_calls'] = array();
$GLOBALS['g15_query_calls'] = array();
$GLOBALS['g15_query_queue'] = array();
$GLOBALS['g15_batch_size'] = 100;
$GLOBALS['g15_event_starts'] = array();
$GLOBALS['g15_event_ends'] = array();
$GLOBALS['g15_plan_anchors'] = array();

function g15_now(): int
{
	return (int) $GLOBALS['g15_now'];
}

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_key($value): string
{
	$value = is_scalar($value) ? strtolower((string) $value) : '';
	$clean = preg_replace('/[^a-z0-9_\-]/', '', $value);
	return is_string($clean) ? $clean : '';
}

function __($text, $domain = ''): string
{
	unset($domain);
	return (string) $text;
}

function wp_timezone(): DateTimeZone
{
	return new DateTimeZone((string) $GLOBALS['g15_timezone_name']);
}

function wp_date(string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null): string
{
	$timestamp = $timestamp ?? g15_now();
	$timezone = $timezone instanceof DateTimeZone ? $timezone : wp_timezone();
	$GLOBALS['g15_wp_date_calls'][] = array(
		'format' => $format,
		'timestamp' => $timestamp,
		'timezone' => $timezone->getName(),
	);
	return (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)->format($format);
}

function apply_filters(string $hook_name, $value)
{
	if ($hook_name === 'vms_ticket_integrity_target_query_batch_size') {
		return $GLOBALS['g15_batch_size'];
	}
	return $value;
}

function bvmgr_ticket_integrity_get_settings(): array
{
	return array('days_ahead' => 30);
}

function bvmgr_ticketing_b_meta_key(string $field, string $fallback): string
{
	unset($field);
	return $fallback;
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	unset($post_id, $key);
	return $single ? '' : array();
}

function wp_reset_postdata(): void
{
}

final class WP_Query
{
	/** @var mixed */
	public $posts;
	public int $max_num_pages;

	public function __construct(array $args)
	{
		$GLOBALS['g15_query_calls'][] = $args;
		$entry = $GLOBALS['g15_query_queue'] === array()
			? array('posts' => array(), 'max_num_pages' => 0)
			: array_shift($GLOBALS['g15_query_queue']);
		$this->posts = $entry['posts'] ?? array();
		$this->max_num_pages = (int) ($entry['max_num_pages'] ?? 0);
	}
}

function bvmgr_ticketing_b_get_tec_event_start(int $tec_event_id): string
{
	return (string) ($GLOBALS['g15_event_starts'][$tec_event_id] ?? '');
}

function bvmgr_ticketing_b_get_tec_event_end(int $tec_event_id): string
{
	return (string) ($GLOBALS['g15_event_ends'][$tec_event_id] ?? '');
}

function bvmgr_ticketing_v2_get_plan_event_anchor_datetimes(int $plan_id): array
{
	$anchors = $GLOBALS['g15_plan_anchors'][$plan_id] ?? array();
	return is_array($anchors) ? $anchors : array();
}

eval(g15_prepare_eval_function($sources['mirror']['monitor'], 'bvmgr_ticket_integrity_format_datetime', 'g15_ticket_integrity_format_datetime'));
eval(
	g15_prepare_eval_function(
		$sources['mirror']['monitor'],
		'bvmgr_ticket_integrity_build_targets',
		'g15_ticket_integrity_build_targets',
		array("\t\$now = time();" => "\t\$now = g15_now();")
	)
);
eval(g15_extract_function($sources['mirror']['phase_b'], 'bvmgr_ticketing_v2_normalize_sales_window_value'));
eval(g15_extract_function($sources['mirror']['phase_b'], 'bvmgr_ticketing_v2_normalize_relative_days'));
eval(g15_extract_function($sources['mirror']['phase_b'], 'bvmgr_ticketing_v2_relative_days_before_datetime'));
eval(
	g15_prepare_eval_function(
		$sources['mirror']['phase_b'],
		'bvmgr_ticketing_b_resolve_sales_window',
		'g15_ticketing_b_resolve_sales_window',
		array("wp_date('Y-m-d H:i:s', time(), \$tz)" => "wp_date('Y-m-d H:i:s', g15_now(), \$tz)")
	)
);
eval(
	g15_prepare_eval_function(
		$sources['mirror']['phase_b'],
		'bvmgr_ticketing_v2_get_plan_sales_window_defaults',
		'g15_ticketing_v2_get_plan_sales_window_defaults',
		array("wp_date('Y-m-d H:i:s', time(), \$tz)" => "wp_date('Y-m-d H:i:s', g15_now(), \$tz)")
	)
);

$format_timestamp = (new DateTimeImmutable('2026-03-08 05:30:00', new DateTimeZone('UTC')))->getTimestamp();
$GLOBALS['g15_timezone_name'] = 'UTC';
g15_same('Never', g15_ticket_integrity_format_datetime(0), 'Zero timestamp must retain the Never sentinel.');
g15_same('2026-03-08 5:30 am', g15_ticket_integrity_format_datetime(-$format_timestamp), 'Negative timestamps must retain absint normalization.');
$GLOBALS['g15_timezone_name'] = 'America/Chicago';
g15_same('2026-03-07 11:30 pm', g15_ticket_integrity_format_datetime($format_timestamp), 'Formatter must use the site timezone at the DST boundary.');

/** @return array<string,mixed> */
function g15_run_target_window(string $timezone_name, int $now, int $days_ahead): array
{
	$GLOBALS['g15_timezone_name'] = $timezone_name;
	$GLOBALS['g15_now'] = $now;
	$GLOBALS['g15_wp_date_calls'] = array();
	$GLOBALS['g15_query_calls'] = array();
	$GLOBALS['g15_query_queue'] = array(array('posts' => array(), 'max_num_pages' => 0));
	g15_ticket_integrity_build_targets(array('days_ahead' => $days_ahead));
	g15_same(1, count($GLOBALS['g15_query_calls']), 'Target scenario must issue one terminating WP_Query.');
	g15_same(2, count($GLOBALS['g15_wp_date_calls']), 'Target scenario must format exactly two bounds.');
	return $GLOBALS['g15_query_calls'][0];
}

$utc_now = (new DateTimeImmutable('2026-02-01 12:34:56', new DateTimeZone('UTC')))->getTimestamp();
$utc_args = g15_run_target_window('UTC', $utc_now, 7);
$utc_calls = $GLOBALS['g15_wp_date_calls'];
g15_same(7 * DAY_IN_SECONDS, $utc_calls[1]['timestamp'] - $utc_calls[0]['timestamp'], 'UTC horizon must remain exactly seven DAY_IN_SECONDS intervals.');
g15_same('2026-02-01', $utc_args['meta_query'][0]['value'][0] ?? null, 'UTC target start date changed.');
g15_same('2026-02-08', $utc_args['meta_query'][0]['value'][1] ?? null, 'UTC target end date changed.');

$expected_args = array(
	'post_type' => 'vms_event_plan',
	'post_status' => 'publish',
	'posts_per_page' => 100,
	'paged' => 1,
	'fields' => 'ids',
	'no_found_rows' => false,
	'meta_key' => '_vms_event_date',
	'orderby' => 'meta_value',
	'meta_type' => 'DATE',
	'order' => 'ASC',
	'meta_query' => array(
		array(
			'key' => '_vms_event_date',
			'value' => array('2026-02-01', '2026-02-08'),
			'compare' => 'BETWEEN',
			'type' => 'DATE',
		),
		array(
			'key' => '_vms_tec_event_id',
			'value' => 0,
			'compare' => '>',
			'type' => 'NUMERIC',
		),
	),
	'update_post_meta_cache' => false,
	'update_post_term_cache' => false,
	'cache_results' => false,
	'lazy_load_term_meta' => false,
	'suppress_filters' => true,
);
g15_same($expected_args, $utc_args, 'Complete Ticket Integrity target-query arguments changed.');

$spring_now = (new DateTimeImmutable('2026-03-08 00:30:00', new DateTimeZone('America/Chicago')))->getTimestamp();
$spring_args = g15_run_target_window('America/Chicago', $spring_now, 1);
$spring_calls = $GLOBALS['g15_wp_date_calls'];
g15_same(DAY_IN_SECONDS, $spring_calls[1]['timestamp'] - $spring_calls[0]['timestamp'], 'Spring DST horizon must remain exactly DAY_IN_SECONDS.');
g15_same(array('2026-03-08', '2026-03-09'), $spring_args['meta_query'][0]['value'] ?? null, 'Spring DST local date window changed.');

$fall_now = (new DateTimeImmutable('2026-11-01 00:30:00', new DateTimeZone('America/Chicago')))->getTimestamp();
$fall_args = g15_run_target_window('America/Chicago', $fall_now, 1);
$fall_calls = $GLOBALS['g15_wp_date_calls'];
g15_same(DAY_IN_SECONDS, $fall_calls[1]['timestamp'] - $fall_calls[0]['timestamp'], 'Fall DST horizon must remain exactly DAY_IN_SECONDS.');
g15_same(array('2026-11-01', '2026-11-01'), $fall_args['meta_query'][0]['value'] ?? null, 'Fall DST window must preserve exact-seconds arithmetic.');

$GLOBALS['g15_timezone_name'] = 'UTC';
$GLOBALS['g15_now'] = (new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC')))->getTimestamp();
$GLOBALS['g15_event_starts'][10] = '2030-06-10T19:00';
$GLOBALS['g15_event_ends'][10] = '2030-06-10 22:00';
$GLOBALS['g15_event_starts'][20] = 'not-an-event-start';
$GLOBALS['g15_event_ends'][20] = 'not-an-event-end';
$now_string = '2026-01-15 12:00:00';

g15_same(
	array('start' => $now_string, 'end' => '2030-06-10 22:00:00'),
	g15_ticketing_b_resolve_sales_window(10, array()),
	'Blank Phase B window must retain now/event-end defaults and anchor normalization.'
);
g15_same(
	array('start' => $now_string, 'end' => '2030-06-10 20:00:00'),
	g15_ticketing_b_resolve_sales_window(10, array('sales_start' => '', 'sales_end' => '2030-06-10 20:00:00')),
	'One-sided Phase B end must retain the now start default.'
);
g15_same(
	array('start' => '2030-06-01 09:00:00', 'end' => '2030-06-10 22:00:00'),
	g15_ticketing_b_resolve_sales_window(10, array('sales_start' => '2030-06-01 09:00:00', 'sales_end' => '')),
	'One-sided Phase B start must retain the event-end default.'
);
g15_same(
	array('start' => $now_string, 'end' => $now_string),
	g15_ticketing_b_resolve_sales_window(20, array()),
	'Invalid Phase B anchors must fail closed to the existing now/now defaults.'
);
g15_same(
	array('start' => '2030-06-01 09:15:00', 'end' => '2030-06-08 21:30:00'),
	g15_ticketing_b_resolve_sales_window(10, array('sales_start' => '2030-06-01 09:15:00', 'sales_end' => '2030-06-08 21:30:00')),
	'Persisted Phase B window values inside the event boundary must remain unchanged.'
);
g15_same(
	array('start' => '2030-06-10 22:00:00', 'end' => '2030-06-10 22:00:00'),
	g15_ticketing_b_resolve_sales_window(10, array('sales_start' => '2030-06-11 00:00:00', 'sales_end' => '2030-06-12 00:00:00')),
	'Phase B must retain event-end and inverted-window clamps.'
);
g15_same(
	array('start' => '2030-06-08 19:00:00', 'end' => '2030-06-10 22:00:00'),
	g15_ticketing_b_resolve_sales_window(10, array('sales_start_relative_days' => '2', 'sales_end_relative_days' => '0')),
	'Phase B relative-day resolution must retain normalized event anchors.'
);

$GLOBALS['g15_plan_anchors'][30] = array('event_start' => '2031-05-01T19:30', 'event_end' => '2031-05-01 22:15');
$GLOBALS['g15_plan_anchors'][31] = array('event_start' => '2031-05-02T20:00', 'event_end' => 'invalid-end');
$GLOBALS['g15_plan_anchors'][32] = array('event_start' => 'invalid-start', 'event_end' => 'invalid-end');
g15_same(
	array('sales_start' => $now_string, 'sales_end' => ''),
	g15_ticketing_v2_get_plan_sales_window_defaults(0),
	'Invalid plan IDs must retain now/blank defaults.'
);
g15_same(
	array('sales_start' => $now_string, 'sales_end' => '2031-05-01 22:15:00'),
	g15_ticketing_v2_get_plan_sales_window_defaults(30),
	'Persisted plan event-end anchors must remain the sales-end default.'
);
g15_same(
	array('sales_start' => $now_string, 'sales_end' => '2031-05-02 20:00:00'),
	g15_ticketing_v2_get_plan_sales_window_defaults(31),
	'Invalid persisted event-end anchors must retain the event-start fallback.'
);
g15_same(
	array('sales_start' => $now_string, 'sales_end' => ''),
	g15_ticketing_v2_get_plan_sales_window_defaults(32),
	'Invalid persisted plan anchors must retain a blank sales-end default.'
);

fwrite(
	STDOUT,
	"PASS: {$GLOBALS['g15_assertion_count']} current ticketing site-local formatting, exact-seconds target windows, and Phase B defaults/clamps assertions.\n"
);
