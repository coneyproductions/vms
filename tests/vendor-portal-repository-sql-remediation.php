<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

final class VMS_Portal_WPDB_Spy
{
	/** @var array<int,array{template:string,args:array<int,mixed>,sql:string}> */
	public array $prepares = array();
	/** @var array<int,array{sql:string,result:mixed}> */
	public array $reads = array();
	/** @var array<int,mixed> */
	public array $get_var_queue = array();

	public function prepare(string $query, ...$args): string
	{
		if (count($args) === 1 && is_array($args[0])) {
			$args = array_values($args[0]);
		}
		$index = 0;
		$sql = preg_replace_callback(
			'/(?<!%)%(?:\d+\$)?[sdi]/',
			static function (array $match) use (&$index, $args): string {
				if (!array_key_exists($index, $args)) {
					throw new RuntimeException('Missing prepared-query argument.');
				}
				$value = $args[$index++];
				$type = substr($match[0], -1);
				if ($type === 'd') {
					return (string) (int) $value;
				}
				if ($type === 'i') {
					return '`' . str_replace('`', '``', (string) $value) . '`';
				}
				return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $value) . "'";
			},
			$query
		);
		if (!is_string($sql) || $index !== count($args)) {
			throw new RuntimeException('Prepared-query placeholder/argument mismatch.');
		}
		$this->prepares[] = array('template' => $query, 'args' => $args, 'sql' => $sql);
		return $sql;
	}

	public function get_var(string $sql)
	{
		$result = $this->get_var_queue === array() ? null : array_shift($this->get_var_queue);
		$this->reads[] = array('sql' => $sql, 'result' => $result);
		return $result;
	}
}

final class WP_Post
{
	public int $ID;
	public string $post_status;

	public function __construct(int $id, string $post_status = 'publish')
	{
		$this->ID = $id;
		$this->post_status = $post_status;
	}
}

final class WP_Query
{
	/** @var array<int,array<string,mixed>> */
	public static array $calls = array();
	/** @var array<int,array<int,WP_Post>> */
	public static array $queue = array();
	/** @var array<int,WP_Post> */
	public array $posts = array();

	public function __construct(array $args)
	{
		self::$calls[] = $args;
		$this->posts = self::$queue === array() ? array() : array_shift(self::$queue);
	}
}

$GLOBALS['portal_admissions_table'] = 'wp_vms_admission_entries';
$GLOBALS['portal_bonus_cards'] = array();
$GLOBALS['portal_secondary_cards'] = array();
$GLOBALS['portal_event_statuses'] = array();
$GLOBALS['portal_meta'] = array();
$GLOBALS['portal_titles'] = array();
$GLOBALS['portal_posts'] = array();
$GLOBALS['portal_get_posts_calls'] = array();
$GLOBALS['portal_get_posts_queue'] = array();
$GLOBALS['portal_reset_postdata_calls'] = 0;

function absint($value): int
{
	return abs((int) $value);
}

function current_time(string $type)
{
	return $type === 'timestamp' ? 1786150800 : '2026-08-08 12:00:00';
}

function wp_timezone(): DateTimeZone
{
	return new DateTimeZone('America/Chicago');
}

function wp_date(string $format, $timestamp = null, $timezone = null): string
{
	unset($timestamp, $timezone);
	return $format === 'Y-m-d' ? '2026-08-08' : 'Aug 8, 2026';
}

function __(string $text, string $domain = ''): string
{
	unset($domain);
	return $text;
}

function vms_admission_table_entries(): string
{
	return (string) $GLOBALS['portal_admissions_table'];
}

function bvmgr_meta_key(string $scope, string $field): string
{
	unset($scope);
	$keys = array(
		'date' => '_vms_event_date',
		'band_vendor_id' => '_vms_band_vendor_id',
		'secondary_vendor_id' => '_vms_secondary_vendor_id',
		'lineup_entry_vendor_id' => '_vms_lineup_entry_vendor_id',
		'tec_event_id' => '_vms_tec_event_id',
	);
	return $keys[$field] ?? ('_vms_' . $field);
}

function wp_reset_postdata(): void
{
	$GLOBALS['portal_reset_postdata_calls']++;
}

function vms_vendor_portal_build_bonus_progress_card(int $plan_id, bool $history = false): array
{
	return $GLOBALS['portal_bonus_cards'][$plan_id . ':' . ($history ? '1' : '0')] ?? array();
}

function vms_vendor_portal_secondary_sales_visibility_enabled(): bool
{
	return true;
}

function vms_vendor_portal_build_secondary_sales_snapshot_card(int $plan_id): array
{
	return $GLOBALS['portal_secondary_cards'][$plan_id] ?? array();
}

function bvmgr_event_plan_get_status(int $plan_id, string $context): string
{
	unset($context);
	return $GLOBALS['portal_event_statuses'][$plan_id] ?? 'draft';
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	$value = $GLOBALS['portal_meta'][$post_id][$key] ?? ($single ? '' : array());
	if ($single && is_array($value)) {
		return $value === array() ? '' : reset($value);
	}
	return $value;
}

function bvmgr_format_local_ymd(string $date, string $format): string
{
	return $format . ':' . $date;
}

function bvmgr_vendor_portal_get_progress_headcount_context(int $plan_id): array
{
	return array('headcount' => $plan_id + 10);
}

function bvmgr_vendor_portal_get_count_breakdown(int $plan_id, array $context = array()): array
{
	return array('plan_id' => $plan_id, 'headcount' => (int) ($context['headcount'] ?? 0));
}

function get_the_title(int $post_id): string
{
	return $GLOBALS['portal_titles'][$post_id] ?? ('Plan ' . $post_id);
}

function get_post(int $post_id)
{
	return $GLOBALS['portal_posts'][$post_id] ?? null;
}

function get_permalink(int $post_id): string
{
	return 'https://example.test/event/' . $post_id . '/';
}

function get_posts(array $args): array
{
	$GLOBALS['portal_get_posts_calls'][] = $args;
	return $GLOBALS['portal_get_posts_queue'] === array() ? array() : array_shift($GLOBALS['portal_get_posts_queue']);
}

function portal_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function portal_same($expected, $actual, string $message): void
{
	portal_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function portal_contains(string $needle, string $haystack, string $message): void
{
	portal_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function portal_no_placeholders(string $sql, string $message): void
{
	portal_assert(preg_match('/(?<!%)%(?:\d+\$)?[sdi]/', $sql) !== 1, $message . "\nSQL: " . $sql);
}

function portal_extract_function(string $source, string $name): string
{
	$start = strpos($source, 'function ' . $name . '(');
	$brace = $start === false ? false : strpos($source, '{', $start);
	if ($start === false || $brace === false) {
		throw new RuntimeException('Unable to find function ' . $name . '.');
	}
	$depth = 1;
	for ($index = $brace + 1, $length = strlen($source); $index < $length; $index++) {
		$depth += $source[$index] === '{' ? 1 : 0;
		$depth -= $source[$index] === '}' ? 1 : 0;
		if ($depth === 0) {
			return substr($source, $start, ($index - $start) + 1);
		}
	}
	throw new RuntimeException('Unable to parse function ' . $name . '.');
}

function portal_extract_assignment_call(string $source, string $marker): string
{
	$start = strpos($source, $marker);
	$open = $start === false ? false : strpos($source, '(', $start);
	if ($start === false || $open === false) {
		throw new RuntimeException('Unable to find call assignment: ' . $marker);
	}
	$depth = 0;
	for ($index = $open, $length = strlen($source); $index < $length; $index++) {
		$depth += $source[$index] === '(' ? 1 : 0;
		$depth -= $source[$index] === ')' ? 1 : 0;
		if ($depth === 0) {
			$semicolon = strpos($source, ';', $index);
			if ($semicolon === false) {
				break;
			}
			return substr($source, $start, ($semicolon - $start) + 1);
		}
	}
	throw new RuntimeException('Unable to parse call assignment: ' . $marker);
}

function portal_strip_owned_annotations(string $source): string
{
	$source = (string) preg_replace(
		'/^[ \t]*\/\/ phpcs:ignore WordPress\.DB\.DirectDatabaseQuery\.DirectQuery,WordPress\.DB\.DirectDatabaseQuery\.NoCaching -- [^\r\n]*(?:\r?\n|$)/m',
		'',
		$source
	);
	return (string) preg_replace(
		'/ \/\/ phpcs:ignore WordPress\.DB\.SlowDBQuery\.(?:slow_db_query_meta_key|slow_db_query_meta_query) -- [^\r\n]*/',
		'',
		$source
	);
}

function portal_validate_narrow_suppressions(string $scope): void
{
	if (strpos($scope, 'phpcs:disable') !== false || strpos($scope, 'phpcs:enable') !== false || strpos($scope, 'phpcs:ignoreFile') !== false) {
		throw new RuntimeException('Broad PHPCS suppression is forbidden in the portal repository slice.');
	}
	$allowed = array(
		'WordPress.DB.DirectDatabaseQuery.DirectQuery' => true,
		'WordPress.DB.DirectDatabaseQuery.NoCaching' => true,
		'WordPress.DB.SlowDBQuery.slow_db_query_meta_key' => true,
		'WordPress.DB.SlowDBQuery.slow_db_query_meta_query' => true,
	);
	foreach (preg_split('/\R/', $scope) ?: array() as $line) {
		if (strpos($line, 'phpcs:') === false) {
			continue;
		}
		if (!preg_match('/phpcs:ignore ([^\s]+) -- (.+)$/', $line, $match)) {
			throw new RuntimeException('Every suppression must be one-line, exact, and justified: ' . $line);
		}
		foreach (explode(',', $match[1]) as $code) {
			if (!isset($allowed[$code])) {
				throw new RuntimeException('Unclassified or broad scanner suppression: ' . $code);
			}
		}
		if (strlen(trim($match[2])) < 24) {
			throw new RuntimeException('Scanner suppression lacks an operation-specific reason.');
		}
	}
}

$root = dirname(__DIR__);
$mirror_path = $root . '/includes/portal/vendor-portal.php';
$live_path = dirname(__DIR__, 3) . '/vms/includes/portal/vendor-portal.php';
$source = (string) file_get_contents($mirror_path);
$live_source = (string) file_get_contents($live_path);
portal_assert($source !== '' && $live_source !== '', 'Mirror and shadow-live portal sources should be readable.');

$owned_functions = array(
	'vms_vendor_portal_get_admissions_headcount',
	'vms_vendor_portal_get_guest_admissions_count',
	'vms_vendor_portal_get_bonus_progress_cards',
	'vms_vendor_portal_get_recent_bonus_history_cards',
	'vms_vendor_portal_get_secondary_sales_snapshot_cards',
	'vms_vendor_portal_get_secondary_sales_history_cards',
	'vms_vendor_portal_get_past_assigned_event_rows',
	'vms_vendor_portal_get_next_headliner_booking',
);
$owned_source = '';
foreach ($owned_functions as $function) {
	$function_source = portal_extract_function($source, $function);
	portal_same($function_source, portal_extract_function($live_source, $function), $function . ' should remain mirror/shadow-live identical.');
	$owned_source .= "\n" . $function_source;
}
$shortcode_upcoming = portal_extract_assignment_call($source, '$upcoming = get_posts(array(');
$live_shortcode_upcoming = portal_extract_assignment_call($live_source, '$upcoming = get_posts(array(');
portal_same($shortcode_upcoming, $live_shortcode_upcoming, 'The shortcode upcoming-bookings query occurrence should remain mirror/shadow-live identical.');
$owned_source .= "\n" . $shortcode_upcoming;
portal_assert(hash('sha256', $source) !== hash('sha256', $live_source), 'Unrelated whole-file mirror/live portal divergence should remain preserved.');
portal_same('beff1414667f838dae8a0bed6c31a3c563fc7c4c82cc6d9267f74c13d1e091b7', hash('sha256', portal_strip_owned_annotations($source)), 'Mirror projection outside the owned annotations changed.');
portal_same('ff09aef15ccb0a4f21908de27a09646ba121b51d2e12f65ffb25edef05eac712', hash('sha256', portal_strip_owned_annotations($live_source)), 'Shadow-live projection outside the owned annotations changed.');

$scanner_inventory = array(
	'WordPress.DB.DirectDatabaseQuery.DirectQuery' => 4,
	'WordPress.DB.DirectDatabaseQuery.NoCaching' => 4,
	'WordPress.DB.SlowDBQuery.slow_db_query_meta_key' => 7,
	'WordPress.DB.SlowDBQuery.slow_db_query_meta_query' => 7,
);
portal_same(22, array_sum($scanner_inventory), 'The focused inventory should remain exactly 22 historical portal DB rows.');
foreach ($scanner_inventory as $code => $expected) {
	portal_same($expected, substr_count($owned_source, $code), 'Owned scanner coverage count changed for ' . $code . '.');
}
portal_validate_narrow_suppressions($owned_source);
$negative_controls = array(
	$owned_source . "\n// phpcs:disable WordPress.DB",
	$owned_source . "\n// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- invented broad family suppression",
);
foreach ($negative_controls as $negative_scope) {
	$rejected = false;
	try {
		portal_validate_narrow_suppressions($negative_scope);
	} catch (RuntimeException $exception) {
		$rejected = true;
	}
	portal_assert($rejected, 'The broad-suppression negative control should be rejected.');
}

foreach ($owned_functions as $function) {
	eval(portal_extract_function($source, $function));
}

// Admissions aggregates retain prepared identifiers/values, fresh counts, and request-local table readiness.
$wpdb = new VMS_Portal_WPDB_Spy();
$GLOBALS['wpdb'] = $wpdb;
portal_same(0, vms_vendor_portal_get_admissions_headcount(0), 'Invalid headcount IDs should fail closed.');
portal_same(array(), $wpdb->reads, 'Invalid headcount IDs should not query admissions.');
$table = 'wp_vms_admission_entries_headcount';
$GLOBALS['portal_admissions_table'] = $table;
$wpdb->get_var_queue = array($table, 17, 19);
portal_same(17, vms_vendor_portal_get_admissions_headcount(71), 'Headcount aggregate result changed.');
portal_same(19, vms_vendor_portal_get_admissions_headcount(71), 'Headcount should reread current admission rows on repeat calls.');
portal_same(3, count($wpdb->reads), 'Headcount should cache only table readiness, not aggregate results.');
portal_same(array($table), $wpdb->prepares[0]['args'], 'Headcount table probe should prepare its table pattern.');
portal_same(array($table, 71), $wpdb->prepares[1]['args'], 'Headcount aggregate should prepare table and Event Plan ID.');
portal_same(array($table, 71), $wpdb->prepares[2]['args'], 'Repeated headcount should preserve prepared inputs.');
portal_contains("status <> 'canceled'", $wpdb->prepares[1]['sql'], 'Headcount canceled-admission exclusion changed.');
portal_contains('event_plan_id = 71', $wpdb->prepares[1]['sql'], 'Headcount Event Plan filter changed.');
portal_no_placeholders($wpdb->prepares[1]['sql'], 'Headcount aggregate should execute fully prepared SQL.');

$wpdb = new VMS_Portal_WPDB_Spy();
$GLOBALS['wpdb'] = $wpdb;
$GLOBALS['portal_admissions_table'] = 'wp_vms_admission_entries_missing';
$wpdb->get_var_queue = array(null);
portal_same(0, vms_vendor_portal_get_admissions_headcount(72), 'A missing admissions table should preserve zero headcount.');
portal_same(1, count($wpdb->reads), 'A missing admissions table should stop after the readiness probe.');

$wpdb = new VMS_Portal_WPDB_Spy();
$GLOBALS['wpdb'] = $wpdb;
$table = 'wp_vms_admission_entries_guest';
$GLOBALS['portal_admissions_table'] = $table;
$wpdb->get_var_queue = array($table, 8, 3);
portal_same(8, vms_vendor_portal_get_guest_admissions_count(81), 'Guest-admission aggregate result changed.');
portal_same(8, vms_vendor_portal_get_guest_admissions_count(81), 'Guest-admission result should retain its per-plan request cache.');
portal_same(3, vms_vendor_portal_get_guest_admissions_count(82), 'A second plan should retain a fresh guest-admission aggregate.');
portal_same(3, count($wpdb->reads), 'Guest counts should reuse table readiness and cache only matching plan results.');
portal_same(array($table), $wpdb->prepares[0]['args'], 'Guest table probe should prepare its table pattern.');
portal_same(array($table, 81), $wpdb->prepares[1]['args'], 'Guest aggregate should prepare table and first plan ID.');
portal_same(array($table, 82), $wpdb->prepares[2]['args'], 'Guest aggregate should prepare table and second plan ID.');
portal_no_placeholders($wpdb->prepares[2]['sql'], 'Guest aggregate should execute fully prepared SQL.');

// WP_Query-backed portal cards retain status/date/vendor filters, limits, ordering, and result shapes.
WP_Query::$calls = array();
WP_Query::$queue = array(
	array(new WP_Post(101), new WP_Post(102), new WP_Post(103)),
	array(new WP_Post(104)),
	array(new WP_Post(105)),
	array(new WP_Post(106)),
	array(new WP_Post(201), new WP_Post(202), new WP_Post(203)),
);
$GLOBALS['portal_reset_postdata_calls'] = 0;
$GLOBALS['portal_bonus_cards'] = array(
	'101:0' => array('plan_id' => 101, 'mode' => 'progress'),
	'103:0' => array('plan_id' => 103, 'mode' => 'progress'),
	'104:1' => array('plan_id' => 104, 'mode' => 'history'),
);
$GLOBALS['portal_secondary_cards'] = array(
	105 => array('plan_id' => 105, 'mode' => 'secondary-progress'),
	106 => array('plan_id' => 106, 'mode' => 'secondary-history'),
);
portal_same(
	array(array('plan_id' => 101, 'mode' => 'progress'), array('plan_id' => 103, 'mode' => 'progress')),
	vms_vendor_portal_get_bonus_progress_cards(42, 2),
	'Bonus-progress card filtering/result order changed.'
);
portal_same(array(array('plan_id' => 104, 'mode' => 'history')), vms_vendor_portal_get_recent_bonus_history_cards(42, 1), 'Bonus-history results changed.');
portal_same(array(array('plan_id' => 105, 'mode' => 'secondary-progress')), vms_vendor_portal_get_secondary_sales_snapshot_cards(42, 1), 'Secondary snapshot results changed.');
portal_same(array(array('plan_id' => 106, 'mode' => 'secondary-history')), vms_vendor_portal_get_secondary_sales_history_cards(42, 1), 'Secondary history results changed.');

$GLOBALS['portal_event_statuses'] = array(201 => 'published', 202 => 'draft', 203 => 'published');
$GLOBALS['portal_meta'] = array(
	201 => array(
		'_vms_band_vendor_id' => 42,
		'_vms_secondary_vendor_id' => 0,
		'_vms_lineup_entry_vendor_id' => array(),
		'_vms_event_date' => '2026-07-01',
	),
	203 => array(
		'_vms_band_vendor_id' => 7,
		'_vms_secondary_vendor_id' => 8,
		'_vms_lineup_entry_vendor_id' => array(42),
		'_vms_event_date' => '2026-06-01',
	),
);
$GLOBALS['portal_titles'] = array(201 => 'Primary Show', 203 => 'Lineup Show');
$past_rows = vms_vendor_portal_get_past_assigned_event_rows(42, 2);
portal_same(array(201, 203), array_column($past_rows, 'plan_id'), 'Past-event result filtering/order changed.');
portal_same(array('primary', 'lineup'), array_column($past_rows, 'role_key'), 'Past-event vendor role resolution changed.');
portal_same(array(211, 213), array_column($past_rows, 'attendance_count'), 'Past-event attendance result shape changed.');
portal_same(5, count(WP_Query::$calls), 'The five owned WP_Query operations should each execute once.');
portal_same(5, $GLOBALS['portal_reset_postdata_calls'], 'Each owned WP_Query operation should preserve postdata reset behavior.');

$expected_statuses = array('publish', 'draft', 'pending', 'private');
$progress_args = WP_Query::$calls[0];
portal_same('vms_event_plan', $progress_args['post_type'], 'Bonus-progress post type changed.');
portal_same($expected_statuses, $progress_args['post_status'], 'Bonus-progress statuses changed.');
portal_same(6, $progress_args['posts_per_page'], 'Bonus-progress overfetch limit changed.');
portal_same('meta_value', $progress_args['orderby'], 'Bonus-progress ordering mode changed.');
portal_same('_vms_event_date', $progress_args['meta_key'], 'Bonus-progress ordering key changed.');
portal_same('ASC', $progress_args['order'], 'Bonus-progress direction changed.');
portal_same(true, $progress_args['no_found_rows'], 'Bonus-progress pagination behavior changed.');
portal_same(array('key' => '_vms_event_date', 'value' => '2026-08-08', 'compare' => '>=', 'type' => 'DATE'), $progress_args['meta_query'][0], 'Bonus-progress date filter changed.');
portal_same(array('key' => '_vms_band_vendor_id', 'value' => 42, 'compare' => '=', 'type' => 'NUMERIC'), $progress_args['meta_query'][1], 'Bonus-progress vendor filter changed.');

$history_args = WP_Query::$calls[1];
portal_same(4, $history_args['posts_per_page'], 'Bonus-history overfetch limit changed.');
portal_same('DESC', $history_args['order'], 'Bonus-history direction changed.');
portal_same('<', $history_args['meta_query'][0]['compare'], 'Bonus-history date boundary changed.');
portal_same('_vms_band_vendor_id', $history_args['meta_query'][1]['key'], 'Bonus-history vendor key changed.');

$secondary_args = WP_Query::$calls[2];
portal_same(4, $secondary_args['posts_per_page'], 'Secondary snapshot overfetch limit changed.');
portal_same('ASC', $secondary_args['order'], 'Secondary snapshot direction changed.');
portal_same('_vms_secondary_vendor_id', $secondary_args['meta_query'][1]['key'], 'Secondary snapshot vendor key changed.');
portal_same(42, $secondary_args['meta_query'][1]['value'], 'Secondary snapshot vendor filter changed.');

$secondary_history_args = WP_Query::$calls[3];
portal_same('DESC', $secondary_history_args['order'], 'Secondary-history direction changed.');
portal_same('<', $secondary_history_args['meta_query'][0]['compare'], 'Secondary-history date boundary changed.');
portal_same('_vms_secondary_vendor_id', $secondary_history_args['meta_query'][1]['key'], 'Secondary-history vendor key changed.');

$past_args = WP_Query::$calls[4];
portal_same(10, $past_args['posts_per_page'], 'Past-event overfetch limit changed.');
portal_same('DESC', $past_args['order'], 'Past-event ordering changed.');
portal_same('<', $past_args['meta_query'][0]['compare'], 'Past-event date boundary changed.');
portal_same('OR', $past_args['meta_query'][1]['relation'], 'Past-event assignment relation changed.');
portal_same(array('_vms_band_vendor_id', '_vms_secondary_vendor_id', '_vms_lineup_entry_vendor_id'), array_column(array_slice($past_args['meta_query'][1], 1), 'key'), 'Past-event assignment keys changed.');
portal_same(array(42, 42, 42), array_column(array_slice($past_args['meta_query'][1], 1), 'value'), 'Past-event vendor filters changed.');

// get_posts-backed next-headliner and shortcode queries retain exact arguments and returned objects.
$next_plan = new WP_Post(301);
$dashboard_plan = new WP_Post(302);
$GLOBALS['portal_get_posts_calls'] = array();
$GLOBALS['portal_get_posts_queue'] = array(array($next_plan), array($dashboard_plan));
$GLOBALS['portal_meta'][301] = array('_vms_event_date' => '2026-09-12', '_vms_tec_event_id' => 401);
$GLOBALS['portal_posts'][401] = new WP_Post(401, 'publish');
$GLOBALS['portal_titles'][301] = 'Plan Title';
$GLOBALS['portal_titles'][401] = 'Public Event Title';
$next = vms_vendor_portal_get_next_headliner_booking(42);
portal_same(
	array(
		'plan_id' => 301,
		'tec_event_id' => 401,
		'title' => 'Public Event Title',
		'date' => '2026-09-12',
		'date_label' => 'F j, Y:2026-09-12',
		'event_url' => 'https://example.test/event/401/',
	),
	$next,
	'Next-headliner result shape changed.'
);
$next_args = $GLOBALS['portal_get_posts_calls'][0];
portal_same($expected_statuses, $next_args['post_status'], 'Next-headliner statuses changed.');
portal_same(1, $next_args['posts_per_page'], 'Next-headliner limit changed.');
portal_same('meta_value', $next_args['orderby'], 'Next-headliner ordering mode changed.');
portal_same('_vms_event_date', $next_args['meta_key'], 'Next-headliner ordering key changed.');
portal_same('ASC', $next_args['order'], 'Next-headliner direction changed.');
portal_same(array('key' => '_vms_band_vendor_id', 'value' => '42'), $next_args['meta_query'][0], 'Next-headliner vendor filter changed.');
portal_same(array('key' => '_vms_event_date', 'value' => '2026-08-08', 'compare' => '>=', 'type' => 'DATE'), $next_args['meta_query'][1], 'Next-headliner date filter changed.');

$shortcode_runner = eval(
	'return static function (int $vendor_id, string $today, string $k_band_vendor_id, string $k_secondary_vendor_id, string $k_lineup_vendor_id): array {'
	. $shortcode_upcoming
	. ' return $upcoming; };'
);
portal_assert(is_callable($shortcode_runner), 'Shortcode upcoming-query runner should be executable.');
$shortcode_result = $shortcode_runner(42, '2026-08-08', '_vms_band_vendor_id', '_vms_secondary_vendor_id', '_vms_lineup_entry_vendor_id');
portal_same(array($dashboard_plan), $shortcode_result, 'Shortcode upcoming query should preserve get_posts results.');
$shortcode_args = $GLOBALS['portal_get_posts_calls'][1];
portal_same($expected_statuses, $shortcode_args['post_status'], 'Shortcode upcoming statuses changed.');
portal_same(5, $shortcode_args['posts_per_page'], 'Shortcode upcoming limit changed.');
portal_same('meta_value', $shortcode_args['orderby'], 'Shortcode upcoming ordering mode changed.');
portal_same('_vms_event_date', $shortcode_args['meta_key'], 'Shortcode upcoming ordering key changed.');
portal_same('ASC', $shortcode_args['order'], 'Shortcode upcoming direction changed.');
portal_same(array('key' => '_vms_event_date', 'value' => '2026-08-08', 'compare' => '>=', 'type' => 'DATE'), $shortcode_args['meta_query'][0], 'Shortcode upcoming date filter changed.');
portal_same('OR', $shortcode_args['meta_query'][1]['relation'], 'Shortcode upcoming vendor relation changed.');
portal_same(array('_vms_band_vendor_id', '_vms_secondary_vendor_id', '_vms_lineup_entry_vendor_id'), array_column(array_slice($shortcode_args['meta_query'][1], 1), 'key'), 'Shortcode upcoming assignment keys changed.');
portal_same(array(42, 42, 42), array_column(array_slice($shortcode_args['meta_query'][1], 1), 'value'), 'Shortcode upcoming vendor filters changed.');

echo "Vendor portal repository SQL remediation checks passed.\n";
