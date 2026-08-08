<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

final class VMS_Event_Plans_WPDB_Spy
{
	public string $posts = 'wp_posts';
	public string $postmeta = 'wp_postmeta';
	/** @var array<int,array{template:string,args:array<int,mixed>,sql:string}> */
	public array $prepares = array();
	/** @var array<int,array{sql:string,result:mixed}> */
	public array $reads = array();
	/** @var array<int,mixed> */
	public array $get_col_queue = array();

	public function __construct(string $prefix = 'wp_')
	{
		$this->posts = $prefix . 'posts';
		$this->postmeta = $prefix . 'postmeta';
	}

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

	public function get_col(string $sql)
	{
		$result = $this->get_col_queue === array() ? array() : array_shift($this->get_col_queue);
		$this->reads[] = array('sql' => $sql, 'result' => $result);
		return $result;
	}
}

final class WP_Post
{
	public int $ID;
	public string $post_type;
	public string $post_status;
	public string $post_title;

	public function __construct(int $id, string $post_type, string $post_status, string $post_title = '')
	{
		$this->ID = $id;
		$this->post_type = $post_type;
		$this->post_status = $post_status;
		$this->post_title = $post_title;
	}
}

final class WP_Query
{
	/** @var array<int,array<string,mixed>> */
	public static array $calls = array();
	/** @var array<int,array<int,int>> */
	public static array $queue = array();
	/** @var array<int,int> */
	public array $posts = array();

	public function __construct(array $args)
	{
		self::$calls[] = $args;
		$this->posts = self::$queue === array() ? array() : array_shift(self::$queue);
	}
}

$GLOBALS['event_plan_meta'] = array();
$GLOBALS['event_plan_posts'] = array();
$GLOBALS['event_plan_updates'] = array();
$GLOBALS['event_plan_deletes'] = array();
$GLOBALS['event_plan_adds'] = array();
$GLOBALS['event_plan_flags'] = array();
$GLOBALS['event_plan_perf_updates'] = array();
$GLOBALS['event_plan_venue_states'] = array();
$GLOBALS['event_plan_calendar_states'] = array();
$GLOBALS['event_plan_calendar_suppressed'] = array();
$GLOBALS['event_plan_legacy_keys'] = array();

function event_plan_reset_runtime(): void
{
	$GLOBALS['event_plan_meta'] = array();
	$GLOBALS['event_plan_posts'] = array();
	$GLOBALS['event_plan_updates'] = array();
	$GLOBALS['event_plan_deletes'] = array();
	$GLOBALS['event_plan_adds'] = array();
	$GLOBALS['event_plan_flags'] = array();
	$GLOBALS['event_plan_perf_updates'] = array();
	$GLOBALS['event_plan_venue_states'] = array();
	$GLOBALS['event_plan_calendar_states'] = array();
	$GLOBALS['event_plan_calendar_suppressed'] = array();
	$GLOBALS['event_plan_legacy_keys'] = array();
	WP_Query::$calls = array();
	WP_Query::$queue = array();
}

function absint($value): int
{
	return abs((int) $value);
}

function get_posts(array $args): array
{
	$query = new WP_Query($args);
	return $query->posts;
}

function get_post(int $post_id)
{
	return $GLOBALS['event_plan_posts'][$post_id] ?? null;
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	$value = $GLOBALS['event_plan_meta'][$post_id][$key] ?? ($single ? '' : array());
	return $value;
}

function update_post_meta(int $post_id, string $key, $value): bool
{
	$GLOBALS['event_plan_meta'][$post_id][$key] = $value;
	$GLOBALS['event_plan_updates'][] = array($post_id, $key, $value);
	return true;
}

function delete_post_meta(int $post_id, string $key, $value = null): bool
{
	unset($GLOBALS['event_plan_meta'][$post_id][$key]);
	$GLOBALS['event_plan_deletes'][] = array($post_id, $key, $value);
	return true;
}

function add_post_meta(int $post_id, string $key, $value, bool $unique = false): int
{
	$GLOBALS['event_plan_adds'][] = array($post_id, $key, $value, $unique);
	return count($GLOBALS['event_plan_adds']);
}

function vms_meta_key(string $scope, string $field): string
{
	unset($scope);
	$keys = array(
		'status' => '_vms_event_plan_status',
		'integrity_issue' => '_vms_integrity_issue',
		'integrity_ts' => '_vms_integrity_ts',
	);
	return $keys[$field] ?? ('_vms_' . $field);
}

function vms_event_plan_get_venue_state(int $venue_id): string
{
	return $GLOBALS['event_plan_venue_states'][$venue_id] ?? 'ok';
}

function vms_event_plan_get_calendar_event_state(int $event_id): string
{
	return $GLOBALS['event_plan_calendar_states'][$event_id] ?? 'ok';
}

function vms_integrity_calendar_unpublished_applies_for_status(string $status): bool
{
	return $status === 'published';
}

function vms_event_plan_calendar_unpublished_suppressed(int $plan_id): bool
{
	return !empty($GLOBALS['event_plan_calendar_suppressed'][$plan_id]);
}

function event_plan_record_flag(string $kind, array $args): void
{
	$GLOBALS['event_plan_flags'][] = array($kind, $args);
}

function vms_event_plan_flag_missing_venue(int $plan_id, int $venue_id, string $title = ''): void
{
	event_plan_record_flag('missing_venue', array($plan_id, $venue_id, $title));
}

function vms_event_plan_flag_trashed_venue(int $plan_id, int $venue_id, string $title = ''): void
{
	event_plan_record_flag('trashed_venue', array($plan_id, $venue_id, $title));
}

function vms_event_plan_flag_venue_unpublished(int $plan_id, int $venue_id, string $title = ''): void
{
	event_plan_record_flag('venue_unpublished', array($plan_id, $venue_id, $title));
}

function vms_event_plan_flag_missing_vendor(int $plan_id, int $vendor_id, string $title = ''): void
{
	event_plan_record_flag('missing_vendor', array($plan_id, $vendor_id, $title));
}

function vms_event_plan_flag_trashed_vendor(int $plan_id, int $vendor_id, string $title = ''): void
{
	event_plan_record_flag('trashed_vendor', array($plan_id, $vendor_id, $title));
}

function vms_event_plan_flag_missing_secondary_vendor(int $plan_id, array $vendor_ids, array $titles): void
{
	event_plan_record_flag('missing_secondary_vendor', array($plan_id, $vendor_ids, $titles));
}

function vms_event_plan_flag_trashed_secondary_vendor(int $plan_id, int $vendor_id, string $title = ''): void
{
	event_plan_record_flag('trashed_secondary_vendor', array($plan_id, $vendor_id, $title));
}

function vms_event_plan_perf_wp_update_post(array $data, string $context, int $plan_id): int
{
	$GLOBALS['event_plan_perf_updates'][] = array('data' => $data, 'context' => $context, 'plan_id' => $plan_id);
	return $plan_id;
}

function vms_event_plan_legacy_ticket_meta_keys(): array
{
	return $GLOBALS['event_plan_legacy_keys'];
}

function event_plan_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function event_plan_same($expected, $actual, string $message): void
{
	event_plan_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function event_plan_contains(string $needle, string $haystack, string $message): void
{
	event_plan_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function event_plan_array_contains(array $needle, array $haystack, string $message): void
{
	event_plan_assert(in_array($needle, $haystack, true), $message . "\nMissing: " . var_export($needle, true));
}

function event_plan_extract_function(string $source, string $name): string
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

function event_plan_owned_projection(string $source, array $names): string
{
	foreach ($names as $name) {
		$source = str_replace(event_plan_extract_function($source, $name), '/* owned function: ' . $name . ' */', $source);
	}
	return $source;
}

function event_plan_validate_narrow_suppressions(string $scope): void
{
	foreach (array('phpcs:disable', 'phpcs:enable', 'phpcs:ignoreFile') as $forbidden) {
		if (strpos($scope, $forbidden) !== false) {
			throw new RuntimeException('Broad PHPCS suppression is forbidden: ' . $forbidden);
		}
	}
	$allowed = array(
		'WordPress.DB.SlowDBQuery.slow_db_query_meta_query' => true,
		'WordPress.DB.PreparedSQL.NotPrepared' => true,
		'WordPress.DB.DirectDatabaseQuery.DirectQuery' => true,
		'WordPress.DB.DirectDatabaseQuery.NoCaching' => true,
	);
	foreach (preg_split('/\R/', $scope) ?: array() as $line) {
		if (strpos($line, 'phpcs:') === false) {
			continue;
		}
		if (!preg_match('/phpcs:ignore ([^\s]+) -- (.+)$/', $line, $match)) {
			throw new RuntimeException('Every suppression must be exact, line-local, and justified: ' . $line);
		}
		foreach (explode(',', $match[1]) as $code) {
			if (!isset($allowed[$code])) {
				throw new RuntimeException('Broad or unowned suppression code: ' . $code);
			}
		}
		if (strlen(trim($match[2])) < 32) {
			throw new RuntimeException('Suppression reason is not occurrence-specific.');
		}
	}
}

$root = dirname(__DIR__);
$mirror_path = $root . '/includes/cpt/event-plans.php';
$shadow_path = dirname(__DIR__, 3) . '/vms/includes/cpt/event-plans.php';
$source = (string) file_get_contents($mirror_path);
$shadow_source = (string) file_get_contents($shadow_path);
event_plan_assert($source !== '' && $shadow_source !== '', 'Mirror and shadow-live Event Plan sources should be readable.');

$owned_functions = array(
	'vms_integrity_scan_event_plans_for_orphaned_venues',
	'vms_integrity_list_event_plans_with_venue_issues',
	'vms_integrity_list_event_plans_with_calendar_issues',
	'vms_integrity_scan_event_plans_for_orphaned_calendar_events',
	'vms_integrity_scan_event_plans_for_missing_vendors',
	'vms_event_plan_legacy_ticket_meta_candidate_ids',
);
$owned_source = '';
foreach ($owned_functions as $function) {
	$function_source = event_plan_extract_function($source, $function);
	event_plan_same($function_source, event_plan_extract_function($shadow_source, $function), $function . ' should remain mirror/shadow-live identical.');
	$owned_source .= "\n" . $function_source;
}
event_plan_assert(hash('sha256', $source) !== hash('sha256', $shadow_source), 'Intentional whole-file Event Plan divergence should remain preserved.');
event_plan_same('fc6caaa83c0772709038aa0f82425bbdebb1445a881b3aabcca8e296746bc181', hash('sha256', event_plan_owned_projection($source, $owned_functions)), 'Mirror content outside the six owned functions changed.');
event_plan_same('965636d50000dda90c9e940c46a08c515201127044bd8efb95c3d776747f1da0', hash('sha256', event_plan_owned_projection($shadow_source, $owned_functions)), 'Shadow-live content outside the six owned functions changed.');

$scanner_inventory = array(
	'WordPress.DB.DirectDatabaseQuery.DirectQuery' => 1,
	'WordPress.DB.DirectDatabaseQuery.NoCaching' => 1,
	'WordPress.DB.PreparedSQL.NotPrepared' => 1,
	'WordPress.DB.SlowDBQuery.slow_db_query_meta_query' => 6,
);
event_plan_same(9, array_sum($scanner_inventory), 'Historical Event Plan scanner inventory should remain exactly nine rows.');
$covered_rows = 0;
foreach ($scanner_inventory as $code => $expected) {
	event_plan_same($expected, substr_count($owned_source, $code), 'Owned scanner coverage count changed for ' . $code . '.');
	$covered_rows += $expected;
}
event_plan_same(7, substr_count($owned_source, 'phpcs:ignore'), 'There should be exactly seven occurrence-specific annotations for nine rows.');
event_plan_same(0, array_sum($scanner_inventory) - $covered_rows, 'All nine owned historical rows should have zero residual intent.');
event_plan_validate_narrow_suppressions($owned_source);
foreach (array(
	$owned_source . "\n// phpcs:disable WordPress.DB",
	$owned_source . "\n// phpcs:ignore WordPress.DB.SlowDBQuery -- invented broad family suppression",
) as $negative_scope) {
	$rejected = false;
	try {
		event_plan_validate_narrow_suppressions($negative_scope);
	} catch (RuntimeException $exception) {
		$rejected = true;
	}
	event_plan_assert($rejected, 'Broad-suppression negative control should be rejected.');
}
event_plan_assert(strpos($owned_source, 'wp_cache_') === false && strpos($owned_source, '_transient') === false, 'The fresh integrity/cleanup reads should not gain persistent caching.');

foreach ($owned_functions as $function) {
	eval(event_plan_extract_function($source, $function));
}

// Every integrity occurrence retains its exact WP_Query/get_posts contract and empty-result shape.
event_plan_reset_runtime();
WP_Query::$queue = array(array(), array(), array(), array(), array(), array());
$empty_venue_scan = vms_integrity_scan_event_plans_for_orphaned_venues(17);
$empty_venue_list = vms_integrity_list_event_plans_with_venue_issues(0);
$empty_calendar_list = vms_integrity_list_event_plans_with_calendar_issues(19);
$empty_calendar_scan = vms_integrity_scan_event_plans_for_orphaned_calendar_events(-4);
$empty_vendor_scan = vms_integrity_scan_event_plans_for_missing_vendors(23);
event_plan_same(6, count(WP_Query::$calls), 'The five integrity functions should retain six query occurrences.');
$expected_limits = array(17, 500, 19, 500, 23, 23);
foreach (WP_Query::$calls as $index => $args) {
	event_plan_same('vms_event_plan', $args['post_type'], 'Integrity post type changed at query ' . $index . '.');
	event_plan_same(array('publish', 'draft'), $args['post_status'], 'Integrity post statuses changed at query ' . $index . '.');
	event_plan_same($expected_limits[$index], $args['posts_per_page'], 'Integrity finite batch limit changed at query ' . $index . '.');
	event_plan_same('ids', $args['fields'], 'Integrity query should remain ID-only at query ' . $index . '.');
	event_plan_same(true, $args['no_found_rows'], 'Integrity count suppression changed at query ' . $index . '.');
	event_plan_same('ID', $args['orderby'], 'Integrity ordering key changed at query ' . $index . '.');
	event_plan_same('DESC', $args['order'], 'Integrity ordering direction changed at query ' . $index . '.');
}
$venue_meta_query = array(array('key' => '_vms_venue_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'));
$calendar_meta_query = array(
	'relation' => 'OR',
	array('key' => '_vms_tec_event_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'),
	array('key' => '_vms_event_plan_status', 'value' => array('published', 'ready'), 'compare' => 'IN'),
);
event_plan_same($venue_meta_query, WP_Query::$calls[0]['meta_query'], 'Orphaned-Venue scan meta query changed.');
event_plan_same($venue_meta_query, WP_Query::$calls[1]['meta_query'], 'Venue reconciliation meta query changed.');
event_plan_same($calendar_meta_query, WP_Query::$calls[2]['meta_query'], 'Calendar reconciliation meta query changed.');
event_plan_same($calendar_meta_query, WP_Query::$calls[3]['meta_query'], 'Calendar scan meta query changed.');
event_plan_same(array(array('key' => '_vms_band_vendor_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC')), WP_Query::$calls[4]['meta_query'], 'Primary-Vendor meta query changed.');
event_plan_same(array(array('key' => '_vms_secondary_vendor_ids', 'compare' => 'EXISTS')), WP_Query::$calls[5]['meta_query'], 'Secondary-Vendor meta query changed.');
event_plan_same(array('checked' => 0, 'flagged_missing_venue' => 0, 'flagged_trashed_venue' => 0, 'flagged_venue_unpublished' => 0, 'cleared_venue_refs' => 0, 'forced_draft' => 0), $empty_venue_scan, 'Empty Venue scan result changed.');
event_plan_same(array('trashed' => array(), 'missing' => array(), 'unpublished' => array()), $empty_venue_list, 'Empty Venue list result changed.');
event_plan_same(array('trashed' => array(), 'missing' => array(), 'unpublished' => array(), 'unlinked' => array()), $empty_calendar_list, 'Empty calendar list result changed.');
event_plan_same(array('checked' => 0, 'flagged_calendar_event_unlinked' => 0, 'flagged_missing_calendar_event' => 0, 'flagged_trashed_calendar_event' => 0, 'flagged_calendar_event_unpublished' => 0, 'cleared_calendar_event_refs' => 0, 'forced_draft' => 0), $empty_calendar_scan, 'Empty calendar scan result changed.');
event_plan_same(array('checked' => 0, 'flagged_missing_vendor' => 0, 'flagged_trashed_vendor' => 0, 'flagged_missing_secondary_vendor' => 0, 'flagged_trashed_secondary_vendor' => 0, 'removed_missing_secondary_vendor_ids' => 0, 'forced_draft' => 0), $empty_vendor_scan, 'Empty Vendor scan result changed.');

// Venue listing and mutation branches preserve categorization, fallback titles, link clearing, and Draft enforcement.
event_plan_reset_runtime();
WP_Query::$queue[] = array(101, 102, 103, 104);
$GLOBALS['event_plan_meta'] = array(101 => array('_vms_venue_id' => 201), 102 => array('_vms_venue_id' => 202), 103 => array('_vms_venue_id' => 203), 104 => array('_vms_venue_id' => 204));
$GLOBALS['event_plan_venue_states'] = array(201 => 'missing', 202 => 'trashed', 203 => 'unpublished', 204 => 'ok');
$GLOBALS['event_plan_posts'] = array(
	101 => new WP_Post(101, 'vms_event_plan', 'publish', 'Missing Plan'),
	103 => new WP_Post(103, 'vms_event_plan', 'draft', 'Unpublished Plan'),
	202 => new WP_Post(202, 'vms_venue', 'trash', 'Trashed Venue'),
	203 => new WP_Post(203, 'vms_venue', 'draft', 'Draft Venue'),
);
$venue_issues = vms_integrity_list_event_plans_with_venue_issues(4);
event_plan_same(array(101), array_column($venue_issues['missing'], 'plan_id'), 'Missing Venue categorization changed.');
event_plan_same(array(102), array_column($venue_issues['trashed'], 'plan_id'), 'Trashed Venue categorization changed.');
event_plan_same(array(103), array_column($venue_issues['unpublished'], 'plan_id'), 'Unpublished Venue categorization changed.');
event_plan_same('Event Plan #102', $venue_issues['trashed'][0]['plan_title'], 'Venue list fallback title changed.');
event_plan_same('Trashed Venue', $venue_issues['trashed'][0]['venue_title'], 'Venue list title lookup changed.');

event_plan_reset_runtime();
WP_Query::$queue[] = array(105, 106);
$GLOBALS['event_plan_meta'] = array(
	105 => array('_vms_venue_id' => 205, '_vms_event_plan_status' => 'published'),
	106 => array('_vms_venue_id' => 206, '_vms_event_plan_status' => 'ready'),
);
$GLOBALS['event_plan_venue_states'] = array(205 => 'missing', 206 => 'ok');
$GLOBALS['event_plan_posts'][105] = new WP_Post(105, 'vms_event_plan', 'publish', 'Broken Venue Plan');
$venue_scan = vms_integrity_scan_event_plans_for_orphaned_venues(2);
event_plan_same(array('checked' => 2, 'flagged_missing_venue' => 1, 'flagged_trashed_venue' => 0, 'flagged_venue_unpublished' => 0, 'cleared_venue_refs' => 1, 'forced_draft' => 1), $venue_scan, 'Venue repair scan counters changed.');
event_plan_same(0, $GLOBALS['event_plan_meta'][105]['_vms_venue_id'], 'Missing Venue reference should still be cleared.');
event_plan_same('draft', $GLOBALS['event_plan_meta'][105]['_vms_event_plan_status'], 'Broken published plan should still be forced to Draft.');
event_plan_same(array(array('missing_venue', array(105, 205, ''))), $GLOBALS['event_plan_flags'], 'Missing Venue flag call changed.');
event_plan_same('event_plan_force_draft_scan_vendor_or_venue', $GLOBALS['event_plan_perf_updates'][0]['context'], 'Venue scan update context changed.');

// Calendar list/scan results preserve unlinked, missing, trashed, unpublished, suppression, and stale-flag behavior.
event_plan_reset_runtime();
WP_Query::$queue[] = array(301, 302, 303, 304, 305, 306);
foreach (range(301, 306) as $plan_id) {
	$GLOBALS['event_plan_posts'][$plan_id] = new WP_Post($plan_id, 'vms_event_plan', 'publish', 'Plan ' . $plan_id);
}
$GLOBALS['event_plan_posts'][403] = new WP_Post(403, 'tribe_events', 'trash', 'Trashed Calendar');
$GLOBALS['event_plan_posts'][404] = new WP_Post(404, 'tribe_events', 'draft', 'Draft Calendar');
$GLOBALS['event_plan_posts'][405] = new WP_Post(405, 'tribe_events', 'draft', 'Ignored Calendar');
$GLOBALS['event_plan_posts'][406] = new WP_Post(406, 'tribe_events', 'draft', 'Suppressed Calendar');
$GLOBALS['event_plan_meta'] = array(
	301 => array('_vms_event_plan_status' => 'published', '_vms_tec_event_id' => 0, '_vms_tec_event_url' => 'https://example.test/unlinked'),
	302 => array('_vms_event_plan_status' => 'published', '_vms_tec_event_id' => 402, '_vms_tec_event_url' => ''),
	303 => array('_vms_event_plan_status' => 'published', '_vms_tec_event_id' => 403, '_vms_tec_event_url' => ''),
	304 => array('_vms_event_plan_status' => 'published', '_vms_tec_event_id' => 404, '_vms_tec_event_url' => ''),
	305 => array('_vms_event_plan_status' => 'draft', '_vms_tec_event_id' => 405, '_vms_tec_event_url' => ''),
	306 => array('_vms_event_plan_status' => 'published', '_vms_tec_event_id' => 406, '_vms_tec_event_url' => ''),
);
$GLOBALS['event_plan_calendar_states'] = array(402 => 'missing', 403 => 'trashed', 404 => 'unpublished', 405 => 'unpublished', 406 => 'unpublished');
$GLOBALS['event_plan_calendar_suppressed'][306] = true;
$calendar_issues = vms_integrity_list_event_plans_with_calendar_issues(6);
event_plan_same(array(301), array_column($calendar_issues['unlinked'], 'plan_id'), 'Unlinked calendar categorization changed.');
event_plan_same(array(302), array_column($calendar_issues['missing'], 'plan_id'), 'Missing calendar categorization changed.');
event_plan_same(array(303), array_column($calendar_issues['trashed'], 'plan_id'), 'Trashed calendar categorization changed.');
event_plan_same(array(304), array_column($calendar_issues['unpublished'], 'plan_id'), 'Unpublished/suppressed calendar categorization changed.');
event_plan_same('Trashed Calendar', $calendar_issues['trashed'][0]['tec_event_title'], 'Calendar title result changed.');

event_plan_reset_runtime();
WP_Query::$queue[] = array(311, 312, 313, 314, 315);
foreach (range(311, 315) as $plan_id) {
	$GLOBALS['event_plan_posts'][$plan_id] = new WP_Post($plan_id, 'vms_event_plan', 'publish', 'Plan ' . $plan_id);
}
$GLOBALS['event_plan_posts'][413] = new WP_Post(413, 'tribe_events', 'trash', 'Trashed');
$GLOBALS['event_plan_posts'][414] = new WP_Post(414, 'tribe_events', 'draft', 'Unpublished');
$GLOBALS['event_plan_posts'][415] = new WP_Post(415, 'tribe_events', 'draft', 'Draft Unpublished');
$GLOBALS['event_plan_meta'] = array(
	311 => array('_vms_event_plan_status' => 'published', '_vms_tec_event_id' => 0, '_vms_tec_event_url' => 'stale', '_vms_integrity_issue' => ''),
	312 => array('_vms_event_plan_status' => 'published', '_vms_tec_event_id' => 412, '_vms_tec_event_url' => 'stale', '_vms_integrity_issue' => ''),
	313 => array('_vms_event_plan_status' => 'published', '_vms_tec_event_id' => 413, '_vms_tec_event_url' => '', '_vms_integrity_issue' => ''),
	314 => array('_vms_event_plan_status' => 'published', '_vms_tec_event_id' => 414, '_vms_tec_event_url' => '', '_vms_integrity_issue' => ''),
	315 => array('_vms_event_plan_status' => 'draft', '_vms_tec_event_id' => 415, '_vms_tec_event_url' => '', '_vms_integrity_issue' => 'calendar_event_unpublished', '_vms_integrity_ts' => 123),
);
$calendar_scan = vms_integrity_scan_event_plans_for_orphaned_calendar_events(5);
event_plan_same(array('checked' => 5, 'flagged_calendar_event_unlinked' => 1, 'flagged_missing_calendar_event' => 1, 'flagged_trashed_calendar_event' => 1, 'flagged_calendar_event_unpublished' => 1, 'cleared_calendar_event_refs' => 1, 'forced_draft' => 3), $calendar_scan, 'Calendar integrity scan counters changed.');
event_plan_same(0, $GLOBALS['event_plan_meta'][312]['_vms_tec_event_id'], 'Missing calendar ID should still be cleared.');
event_plan_same('', $GLOBALS['event_plan_meta'][311]['_vms_tec_event_url'], 'Unlinked stale calendar URL should still be cleared.');
event_plan_same('published', $GLOBALS['event_plan_meta'][314]['_vms_event_plan_status'], 'Unpublished calendar visibility mismatch should not force Draft.');
event_plan_same('', $GLOBALS['event_plan_meta'][315]['_vms_integrity_issue'], 'Inapplicable stale unpublished flag should still clear.');
event_plan_same(0, $GLOBALS['event_plan_meta'][315]['_vms_integrity_ts'], 'Inapplicable stale unpublished timestamp should still clear.');
event_plan_same(3, count($GLOBALS['event_plan_perf_updates']), 'Calendar scan Draft enforcement count changed.');

// Vendor scan preserves merged/deduplicated plans, link cleanup, secondary index rebuilding, flags, and Draft enforcement.
event_plan_reset_runtime();
WP_Query::$queue = array(array(501, 502), array(502, 503));
$GLOBALS['event_plan_meta'] = array(
	501 => array('_vms_band_vendor_id' => 601, '_vms_secondary_vendor_ids' => array(), '_vms_event_plan_status' => 'published'),
	502 => array('_vms_band_vendor_id' => 602, '_vms_secondary_vendor_ids' => array(603, 604, 605, 605, 0), '_vms_event_plan_status' => 'ready'),
	503 => array('_vms_band_vendor_id' => 606, '_vms_secondary_vendor_ids' => 'not-an-array', '_vms_event_plan_status' => 'draft'),
);
foreach (range(501, 503) as $plan_id) {
	$GLOBALS['event_plan_posts'][$plan_id] = new WP_Post($plan_id, 'vms_event_plan', $plan_id === 503 ? 'draft' : 'publish', 'Plan ' . $plan_id);
}
$GLOBALS['event_plan_posts'][602] = new WP_Post(602, 'vms_vendor', 'trash', 'Trashed Primary');
$GLOBALS['event_plan_posts'][604] = new WP_Post(604, 'vms_vendor', 'trash', 'Trashed Secondary');
$GLOBALS['event_plan_posts'][605] = new WP_Post(605, 'vms_vendor', 'publish', 'Good Secondary');
$GLOBALS['event_plan_posts'][606] = new WP_Post(606, 'vms_vendor', 'publish', 'Good Primary');
$vendor_scan = vms_integrity_scan_event_plans_for_missing_vendors(3);
event_plan_same(array('checked' => 3, 'flagged_missing_vendor' => 1, 'flagged_trashed_vendor' => 1, 'flagged_missing_secondary_vendor' => 1, 'flagged_trashed_secondary_vendor' => 1, 'removed_missing_secondary_vendor_ids' => 1, 'forced_draft' => 2), $vendor_scan, 'Vendor integrity scan counters changed.');
event_plan_same(0, $GLOBALS['event_plan_meta'][501]['_vms_band_vendor_id'], 'Missing primary Vendor reference should still be cleared.');
event_plan_same(array(604, 605), $GLOBALS['event_plan_meta'][502]['_vms_secondary_vendor_ids'], 'Missing/duplicate secondary Vendor normalization changed.');
event_plan_array_contains(array(502, '_vms_secondary_vendor_id', null), $GLOBALS['event_plan_deletes'], 'Secondary Vendor index should still be rebuilt.');
event_plan_same(array(array(502, '_vms_secondary_vendor_id', 604, false), array(502, '_vms_secondary_vendor_id', 605, false)), $GLOBALS['event_plan_adds'], 'Secondary Vendor index entries changed.');
event_plan_same(2, count($GLOBALS['event_plan_perf_updates']), 'Vendor scan Draft enforcement count changed.');

// Legacy cleanup candidate reads retain empty-key short circuit, exact prepare order, ID batching, normalization, and failure fallback.
event_plan_reset_runtime();
$wpdb = new VMS_Event_Plans_WPDB_Spy('wp_empty_');
$GLOBALS['wpdb'] = $wpdb;
$GLOBALS['event_plan_legacy_keys'] = array('', '');
event_plan_same(array(), vms_event_plan_legacy_ticket_meta_candidate_ids(9, 20), 'Empty legacy-key set should still fail closed.');
event_plan_same(array(), $wpdb->prepares, 'Empty legacy-key set should not prepare SQL.');
event_plan_same(array(), $wpdb->reads, 'Empty legacy-key set should not execute SQL.');

$wpdb = new VMS_Event_Plans_WPDB_Spy('wp_cleanup_');
$GLOBALS['wpdb'] = $wpdb;
$GLOBALS['event_plan_legacy_keys'] = array('_vms_legacy_ticket_product_id', '', '_vms_legacy_ticket_price');
$wpdb->get_col_queue[] = array('7', '0', '9', 'bad', '-2', '7');
event_plan_same(array(7, 9, 2, 7), vms_event_plan_legacy_ticket_meta_candidate_ids(-5, 999), 'Legacy candidate ID normalization/order changed.');
event_plan_same(1, count($wpdb->prepares), 'Legacy candidate query should prepare exactly once.');
event_plan_same(1, count($wpdb->reads), 'Legacy candidate query should execute exactly once.');
$expected_prepare_args = array('publish', 'private', 'draft', 'pending', 'future', '_vms_legacy_ticket_product_id', '_vms_legacy_ticket_price', 0, 200);
event_plan_same($expected_prepare_args, $wpdb->prepares[0]['args'], 'Legacy candidate prepare argument order changed.');
event_plan_contains('SELECT DISTINCT p.ID', $wpdb->prepares[0]['sql'], 'Legacy candidate DISTINCT selection changed.');
event_plan_contains('FROM wp_cleanup_posts p', $wpdb->prepares[0]['sql'], 'Legacy candidate posts table changed.');
event_plan_contains('INNER JOIN wp_cleanup_postmeta pm ON pm.post_id = p.ID', $wpdb->prepares[0]['sql'], 'Legacy candidate postmeta join changed.');
event_plan_contains("p.post_status IN ('publish','private','draft','pending','future')", $wpdb->prepares[0]['sql'], 'Legacy candidate status order changed.');
event_plan_contains("pm.meta_key IN ('_vms_legacy_ticket_product_id','_vms_legacy_ticket_price')", $wpdb->prepares[0]['sql'], 'Legacy candidate key order changed.');
event_plan_contains('p.ID > 0', $wpdb->prepares[0]['sql'], 'Legacy candidate cursor normalization changed.');
event_plan_contains('ORDER BY p.ID ASC', $wpdb->prepares[0]['sql'], 'Legacy candidate order changed.');
event_plan_contains('LIMIT 200', $wpdb->prepares[0]['sql'], 'Legacy candidate maximum batch changed.');
event_plan_assert(preg_match('/(?<!%)%(?:\d+\$)?[sdi]/', $wpdb->reads[0]['sql']) !== 1, 'Executed legacy candidate SQL should retain no placeholders.');

$wpdb = new VMS_Event_Plans_WPDB_Spy('wp_failure_');
$GLOBALS['wpdb'] = $wpdb;
$wpdb->get_col_queue[] = false;
event_plan_same(array(), vms_event_plan_legacy_ticket_meta_candidate_ids(12, 0), 'Non-array database failure should still fail closed.');
event_plan_same(array('publish', 'private', 'draft', 'pending', 'future', '_vms_legacy_ticket_product_id', '_vms_legacy_ticket_price', 12, 1), $wpdb->prepares[0]['args'], 'Legacy minimum batch/cursor preparation changed.');

fwrite(STDOUT, "PASS: Event Plan integrity queries, repair results, legacy cleanup preparation/failures, exact nine-row inventory, narrow suppressions, and mirror/shadow projections are covered.\n");
