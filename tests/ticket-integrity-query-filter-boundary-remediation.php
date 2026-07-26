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

function vms_test_count_pattern(string $pattern, string $contents): int
{
	$count = preg_match_all($pattern, $contents);
	if ($count === false) {
		vms_test_fail('Failed counting pattern: ' . $pattern);
	}

	return $count;
}

function vms_test_collect_suppress_filters_true_occurrences(string $directory): array
{
	$matches = array();
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
	foreach ($iterator as $item) {
		if (!$item->isFile() || strtolower($item->getExtension()) !== 'php') {
			continue;
		}

		$contents = file_get_contents($item->getPathname());
		if (!is_string($contents)) {
			vms_test_fail('Failed to read PHP file while scanning suppress_filters: ' . $item->getPathname());
		}

		if (preg_match("/'suppress_filters'\\s*=>\\s*true/", $contents)) {
			$matches[] = $item->getPathname();
		}
	}

	sort($matches);
	return $matches;
}

/**
 * @param array<int,array<string,mixed>> $targets
 * @return array<int,int>
 */
function vms_test_target_plan_ids(array $targets): array
{
	$ids = array();
	foreach ($targets as $target) {
		$ids[] = (int) ($target['plan_id'] ?? 0);
	}

	return $ids;
}

function vms_test_seed_query_dataset(bool $include_cancelled_target = false): void
{
	$now = time();
	$tomorrow = date('Y-m-d', $now + DAY_IN_SECONDS);
	$day_after = date('Y-m-d', $now + (2 * DAY_IN_SECONDS));
	$future = date('Y-m-d', $now + (3 * DAY_IN_SECONDS));
	$past = date('Y-m-d', $now - DAY_IN_SECONDS);

	$GLOBALS['vms_test_query_pages'] = array(
		1 => array(101, 202),
		2 => array(303, 404),
	);
	$GLOBALS['vms_test_titles'] = array(
		1101 => 'Alpha Event',
		1202 => 'Bravo Event',
		1303 => 'Cancelled Event',
		1404 => 'Past Event',
	);
	$GLOBALS['vms_test_linked_tec_events'] = array(
		101 => 1101,
		202 => 1202,
		303 => 1303,
		404 => 1404,
	);
	$GLOBALS['vms_test_cancelled_events'] = array(
		1101 => false,
		1202 => $include_cancelled_target,
		1303 => true,
		1404 => false,
	);
	$GLOBALS['vms_test_event_ticket_products'] = array(
		1101 => array(9101),
		1202 => array(9202),
		1303 => array(9303),
		1404 => array(9404),
	);
	$GLOBALS['vms_test_meta'] = array(
		101 => array(
			'_vms_tec_event_id' => 1101,
			'_vms_event_date' => $tomorrow,
			'_vms_start_time' => '19:00',
		),
		202 => array(
			'_vms_tec_event_id' => 1202,
			'_vms_event_date' => $day_after,
			'_vms_start_time' => '20:00',
		),
		303 => array(
			'_vms_tec_event_id' => 1303,
			'_vms_event_date' => $future,
			'_vms_start_time' => '18:30',
		),
		404 => array(
			'_vms_tec_event_id' => 1404,
			'_vms_event_date' => $past,
			'_vms_start_time' => '17:00',
		),
	);
}

function vms_test_reset_runtime_state(): void
{
	$GLOBALS['vms_test_query_calls'] = array();
	$GLOBALS['vms_test_query_filter_callback'] = null;
	$GLOBALS['vms_test_apply_filters'] = array(
		'vms_ticket_integrity_target_query_batch_size' => 100,
	);
}

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('DAY_IN_SECONDS')) {
	define('DAY_IN_SECONDS', 86400);
}

if (!function_exists('absint')) {
	function absint($value): int
	{
		return abs((int) $value);
	}
}

if (!function_exists('__')) {
	function __(string $text, string $domain = ''): string
	{
		unset($domain);
		return $text;
	}
}

if (!function_exists('apply_filters')) {
	function apply_filters(string $tag, $value)
	{
		return $GLOBALS['vms_test_apply_filters'][$tag] ?? $value;
	}
}

if (!function_exists('sanitize_key')) {
	function sanitize_key($key): string
	{
		$key = strtolower((string) $key);
		$key = preg_replace('/[^a-z0-9_\-]/', '', $key);
		return is_string($key) ? $key : '';
	}
}

if (!function_exists('sanitize_email')) {
	function sanitize_email($email): string
	{
		$email = trim((string) $email);
		return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
	}
}

if (!function_exists('get_option')) {
	function get_option(string $option, $default = false)
	{
		return $GLOBALS['vms_test_options'][$option] ?? $default;
	}
}

if (!function_exists('get_post_meta')) {
	function get_post_meta(int $post_id, string $key, bool $single = true)
	{
		unset($single);
		return $GLOBALS['vms_test_meta'][$post_id][$key] ?? '';
	}
}

if (!function_exists('metadata_exists')) {
	function metadata_exists(string $meta_type, int $object_id, string $meta_key): bool
	{
		unset($meta_type);
		return array_key_exists($meta_key, $GLOBALS['vms_test_meta'][$object_id] ?? array());
	}
}

if (!function_exists('get_the_title')) {
	function get_the_title(int $post_id): string
	{
		return (string) ($GLOBALS['vms_test_titles'][$post_id] ?? '');
	}
}

if (!function_exists('wp_reset_postdata')) {
	function wp_reset_postdata(): void
	{
	}
}

if (!function_exists('wp_timezone')) {
	function wp_timezone(): DateTimeZone
	{
		return new DateTimeZone('UTC');
	}
}

if (!function_exists('wp_date')) {
	function wp_date(string $format, int $timestamp, ?DateTimeZone $timezone = null): string
	{
		$timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone('UTC');
		$date = new DateTimeImmutable('@' . $timestamp);
		return $date->setTimezone($timezone)->format($format);
	}
}

if (!function_exists('vms_ticketing_b_meta_key')) {
	function vms_ticketing_b_meta_key(string $field, string $fallback): string
	{
		unset($field);
		return $fallback;
	}
}

if (!function_exists('vms_ticketing_b_get_linked_tec_event_id')) {
	function vms_ticketing_b_get_linked_tec_event_id(int $plan_id): int
	{
		return (int) ($GLOBALS['vms_test_linked_tec_events'][$plan_id] ?? 0);
	}
}

if (!function_exists('vms_ticket_integrity_parse_wp_datetime')) {
	function vms_ticket_integrity_parse_wp_datetime(string $value): int
	{
		$timestamp = strtotime($value . ' UTC');
		return $timestamp === false ? 0 : $timestamp;
	}
}

if (!function_exists('vms_ticketing_b_get_mode')) {
	function vms_ticketing_b_get_mode(int $plan_id): string
	{
		unset($plan_id);
		return 'read_only';
	}
}

if (!function_exists('vms_ticketing_b_get_event_ticket_products')) {
	function vms_ticketing_b_get_event_ticket_products(int $tec_event_id): array
	{
		return $GLOBALS['vms_test_event_ticket_products'][$tec_event_id] ?? array();
	}
}

if (!function_exists('vms_tec_is_cancelled_event')) {
	function vms_tec_is_cancelled_event(int $tec_event_id): bool
	{
		return !empty($GLOBALS['vms_test_cancelled_events'][$tec_event_id]);
	}
}

if (!class_exists('WP_Query')) {
	final class WP_Query
	{
		/** @var array<int,int> */
		public array $posts = array();

		public int $max_num_pages = 0;

		/**
		 * @param array<string,mixed> $args
		 */
		public function __construct(array $args = array())
		{
			$GLOBALS['vms_test_query_calls'][] = $args;
			$page = abs((int) ($args['paged'] ?? 1));
			$pages = $GLOBALS['vms_test_query_pages'] ?? array();
			$posts = $pages[$page] ?? array();
			if (!empty($GLOBALS['vms_test_query_filter_callback']) && empty($args['suppress_filters'])) {
				$callback = $GLOBALS['vms_test_query_filter_callback'];
				$posts = $callback($posts, $args);
			}

			$this->posts = array_values(array_map('intval', $posts));
			$this->max_num_pages = count($pages);
		}
	}
}

require_once dirname(__DIR__) . '/includes/ticketing/ticket-integrity-monitor.php';

$monitor_path = dirname(__DIR__) . '/includes/ticketing/ticket-integrity-monitor.php';
$vendor_type_path = dirname(__DIR__) . '/includes/taxonomies/vendor-type.php';
$admin_page_path = dirname(__DIR__) . '/includes/admin/ticket-integrity-page.php';
$cron_path = dirname(__DIR__) . '/includes/ticketing/ticket-integrity-cron.php';
$daily_report_path = dirname(__DIR__) . '/includes/ticketing/ticket-integrity-daily-report.php';
$live_monitor_path = dirname(__DIR__, 3) . '/vms/includes/ticketing/ticket-integrity-monitor.php';
$includes_dir = dirname(__DIR__) . '/includes';

$monitor_source = vms_test_read_file($monitor_path);
$vendor_type_source = vms_test_read_file($vendor_type_path);
$admin_page_source = vms_test_read_file($admin_page_path);
$cron_source = vms_test_read_file($cron_path);
$daily_report_source = vms_test_read_file($daily_report_path);
$build_targets_source = vms_test_extract_function($monitor_source, 'vms_ticket_integrity_build_targets');
$scan_all_source = vms_test_extract_function($monitor_source, 'vms_ticket_integrity_scan_all');

vms_test_assert_same(
	1,
	vms_test_count_pattern('/WordPressVIPMinimum\.Performance\.WPQueryParams\.SuppressFilters_suppress_filters/', $monitor_source),
	'Ticket Integrity monitor should contain exactly one line-specific SuppressFilters suppression.'
);
vms_test_assert_contains(
	"Ticket Integrity scans require the canonical unfiltered event-plan dataset",
	$monitor_source,
	'Ticket Integrity suppression should explain the bounded canonical-query requirement.'
);
vms_test_assert_not_contains(
	'WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters',
	$vendor_type_source,
	'Deferred vendor-type occurrence must remain unsuppressed for WPORG-28R-E2.'
);
vms_test_assert_contains(
	"'suppress_filters' => true",
	$vendor_type_source,
	'Deferred vendor-type occurrence must remain visible in source.'
);
vms_test_assert_same(
	2,
	count(vms_test_collect_suppress_filters_true_occurrences($includes_dir)),
	'Exactly two suppress_filters=true occurrences should remain across includes/: Ticket Integrity and vendor-type.'
);
vms_test_assert_contains(
	"function vms_ticket_integrity_build_targets(array \$args = array()): array",
	$monitor_source,
	'Ticket Integrity monitor should still expose the target-builder helper.'
);
vms_test_assert_contains(
	'vms_ticket_integrity_build_targets($args);',
	$scan_all_source,
	'Full scans should still build targets through vms_ticket_integrity_build_targets().'
);
vms_test_assert_contains(
	"current_user_can('manage_options')",
	$admin_page_source,
	'Manual Ticket Integrity scans should remain manage_options-gated.'
);
vms_test_assert_contains(
	"check_admin_referer('vms_ticket_integrity_run_scan')",
	$admin_page_source,
	'Manual Ticket Integrity scans should remain nonce-protected.'
);
vms_test_assert_contains(
	"add_action('vms_ticket_integrity_daily_scan', 'vms_ticket_integrity_run_daily_scan');",
	$cron_source,
	'Full Ticket Integrity scans should remain schedulable through the daily cron hook.'
);
vms_test_assert_contains(
	"vms_ticket_integrity_scan_all(array('trigger' => sanitize_key(\$trigger)))",
	$daily_report_source,
	'Daily report refresh should continue to reuse the full Ticket Integrity scan path.'
);
vms_test_assert_same(
	'4ae832840023a8cd2d4c9a805e839927b003f71b29e0efa61bc1415944ff8c87',
	hash_file('sha256', $vendor_type_path),
	'Vendor-type source should remain unchanged in this child.'
);
vms_test_assert_same(
	'066eeaf16b910c930d4ad23eeca2b48669dbc889713d62dafcc80a7c58848122',
	hash_file('sha256', $live_monitor_path),
	'Live Ticket Integrity monitor must remain unchanged in this mirror-only child.'
);

vms_test_reset_runtime_state();
vms_test_seed_query_dataset(false);
$GLOBALS['vms_test_query_filter_callback'] = static function (array $posts, array $args): array {
	unset($args);
	return array_values(array_filter($posts, static function (int $plan_id): bool {
		return $plan_id !== 202;
	}));
};

$targets = vms_ticket_integrity_build_targets(
	array(
		'days_ahead' => 7,
	)
);

$query_calls = $GLOBALS['vms_test_query_calls'];
vms_test_assert_same(2, count($query_calls), 'Target builder should paginate through both query pages.');

$first_query = $query_calls[0];
vms_test_assert_same('vms_event_plan', $first_query['post_type'] ?? null, 'Ticket Integrity target query should stay scoped to Event Plans.');
vms_test_assert_same('publish', $first_query['post_status'] ?? null, 'Ticket Integrity target query should remain publish-only at the WP post_status layer.');
vms_test_assert_same(100, $first_query['posts_per_page'] ?? null, 'Ticket Integrity target query should preserve the default batch size of 100.');
vms_test_assert_same('ids', $first_query['fields'] ?? null, 'Ticket Integrity target query should continue returning IDs only.');
vms_test_assert_same(false, $first_query['no_found_rows'] ?? null, 'Ticket Integrity target query should preserve pagination counts.');
vms_test_assert_same('_vms_event_date', $first_query['meta_key'] ?? null, 'Ticket Integrity target query should continue ordering by _vms_event_date.');
vms_test_assert_same('meta_value', $first_query['orderby'] ?? null, 'Ticket Integrity target query should continue ordering by event-date meta_value.');
vms_test_assert_same('DATE', $first_query['meta_type'] ?? null, 'Ticket Integrity target query should preserve DATE meta typing.');
vms_test_assert_same('ASC', $first_query['order'] ?? null, 'Ticket Integrity target query should preserve ascending event order.');
vms_test_assert_same(false, $first_query['update_post_meta_cache'] ?? null, 'Ticket Integrity target query should keep post-meta cache priming disabled.');
vms_test_assert_same(false, $first_query['update_post_term_cache'] ?? null, 'Ticket Integrity target query should keep term-cache priming disabled.');
vms_test_assert_same(false, $first_query['cache_results'] ?? null, 'Ticket Integrity target query should keep result caching disabled.');
vms_test_assert_same(false, $first_query['lazy_load_term_meta'] ?? null, 'Ticket Integrity target query should keep lazy term-meta loading disabled.');
vms_test_assert_same(true, $first_query['suppress_filters'] ?? null, 'Ticket Integrity target query must explicitly request the canonical unfiltered dataset.');
vms_test_assert_same(1, $first_query['paged'] ?? null, 'Ticket Integrity target query should start at page 1.');

$first_meta_query = $first_query['meta_query'] ?? array();
vms_test_assert_true(is_array($first_meta_query) && count($first_meta_query) === 2, 'Ticket Integrity target query should keep the two explicit meta constraints.');
vms_test_assert_same('_vms_event_date', $first_meta_query[0]['key'] ?? null, 'Target query should constrain the event-date meta key.');
vms_test_assert_same('BETWEEN', $first_meta_query[0]['compare'] ?? null, 'Target query should bound the date window with BETWEEN.');
vms_test_assert_same('DATE', $first_meta_query[0]['type'] ?? null, 'Target query should compare event dates as DATE values.');
vms_test_assert_same(array(wp_date('Y-m-d', time(), wp_timezone()), wp_date('Y-m-d', time() + (7 * DAY_IN_SECONDS), wp_timezone())), $first_meta_query[0]['value'] ?? null, 'Target query should derive the inclusive date window from today through the requested days_ahead horizon.');
vms_test_assert_same('_vms_tec_event_id', $first_meta_query[1]['key'] ?? null, 'Target query should require a linked TEC event meta value.');
vms_test_assert_same('>', $first_meta_query[1]['compare'] ?? null, 'Target query should require TEC event IDs greater than zero.');
vms_test_assert_same('NUMERIC', $first_meta_query[1]['type'] ?? null, 'Target query should compare linked TEC IDs numerically.');

vms_test_assert_same(
	array(101, 202),
	vms_test_target_plan_ids($targets),
	'Ticket Integrity target enumeration should retain canonical plans even when an external visibility filter would otherwise hide one.'
);

vms_test_reset_runtime_state();
vms_test_seed_query_dataset(true);
$include_inactive_false = vms_ticket_integrity_build_targets(array('days_ahead' => 7, 'include_inactive' => false));
$include_inactive_true = vms_ticket_integrity_build_targets(array('days_ahead' => 7, 'include_inactive' => true));
vms_test_assert_same(
	array(101),
	vms_test_target_plan_ids($include_inactive_false),
	'Default Ticket Integrity scans should exclude cancelled events after building the canonical dataset.'
);
vms_test_assert_same(
	array(101, 202, 303),
	vms_test_target_plan_ids($include_inactive_true),
	'Explicit include_inactive scans should retain cancelled canonical targets inside the bounded date window.'
);

vms_test_reset_runtime_state();
vms_test_seed_query_dataset(false);
$GLOBALS['vms_test_apply_filters']['vms_ticket_integrity_target_query_batch_size'] = 999;
vms_ticket_integrity_build_targets(array('days_ahead' => 7));
$bounded_query = $GLOBALS['vms_test_query_calls'][0] ?? array();
vms_test_assert_same(500, $bounded_query['posts_per_page'] ?? null, 'Ticket Integrity target query should cap batch size at 500 when a filter requests an unsafe larger value.');

fwrite(STDOUT, "Ticket Integrity query filter boundary remediation test passed.\n");
