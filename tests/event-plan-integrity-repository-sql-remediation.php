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

function bvmgr_meta_key(string $scope, string $field): string
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

function event_plan_normalize_sql(string $sql): string
{
	$normalized = preg_replace('/\s+/', ' ', trim($sql));
	if (!is_string($normalized)) {
		throw new RuntimeException('Unable to normalize prepared SQL.');
	}
	return $normalized;
}

/**
 * @param array<string,array{function:string,directive:string,target:string,context:string}> $occurrences
 * @return array{source:string,removed:int}
 */
function event_plan_strip_owned_annotations(string $source, array $occurrences): array
{
	$removed = 0;
	foreach ($occurrences as $id => $occurrence) {
		$pattern = '/^[ \t]*' . preg_quote($occurrence['directive'], '/') . '(?:\r\n|\n|\r)/m';
		$matches = preg_match_all($pattern, $source);
		event_plan_same(1, $matches, 'Owned annotation must occur exactly once in the whole source: ' . $id . '.');
		$source = (string) preg_replace($pattern, '', $source, 1, $count);
		event_plan_same(1, $count, 'Owned annotation removal failed: ' . $id . '.');
		$removed += $count;
	}
	return array('source' => $source, 'removed' => $removed);
}

/**
 * @param array<string,array{function:string,directive:string,target:string,context:string}> $occurrences
 * @return array<string,array<int,string>>
 */
function event_plan_validate_occurrence_anchors(string $source, array $occurrences): array
{
	$actual_codes = array();
	foreach ($occurrences as $id => $occurrence) {
		$function_source = event_plan_extract_function($source, $occurrence['function']);
		$lines = preg_split('/\R/', $function_source) ?: array();
		$directive_indexes = array();
		foreach ($lines as $index => $line) {
			if (trim($line) === $occurrence['directive']) {
				$directive_indexes[] = $index;
			}
		}
		event_plan_same(1, count($directive_indexes), 'Occurrence directive must be unique inside its function: ' . $id . '.');
		$directive_index = $directive_indexes[0];
		$target_line = $lines[$directive_index + 1] ?? '';
		event_plan_same($occurrence['target'], trim($target_line), 'Occurrence directive moved away from its exact target: ' . $id . '.');
		if ($occurrence['context'] !== '') {
			$nearby = implode("\n", array_slice($lines, $directive_index + 1, 10));
			event_plan_contains($occurrence['context'], $nearby, 'Occurrence context changed: ' . $id . '.');
		}
		if (!preg_match('/^\/\/ phpcs:ignore ([^\s]+) -- (.+)$/', $occurrence['directive'], $match)) {
			throw new RuntimeException('Occurrence directive is not an exact justified ignore: ' . $id . '.');
		}
		$actual_codes[$id] = explode(',', $match[1]);
		event_plan_assert(strlen(trim($match[2])) >= 32, 'Occurrence reason is not specific enough: ' . $id . '.');
	}
	return $actual_codes;
}

/**
 * @param array<int,string> $expected_directives
 */
function event_plan_validate_owned_db_annotations(string $scope, array $expected_directives): void
{
	if (preg_match('/phpcs:(?:disable|enable|ignoreFile)/', $scope) === 1) {
		throw new RuntimeException('Block, file, and broad PHPCS directives are forbidden in the owned source.');
	}
	$actual_directives = array();
	foreach (preg_split('/\R/', $scope) ?: array() as $line) {
		if (strpos($line, 'phpcs:') === false || strpos($line, 'WordPress.DB') === false) {
			continue;
		}
		$actual_directives[] = trim($line);
	}
	sort($actual_directives);
	sort($expected_directives);
	event_plan_same($expected_directives, $actual_directives, 'Every DB-related PHPCS annotation must be one of the seven exact owned directives.');
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
$slow_query_code = 'WordPress.DB.SlowDBQuery.slow_db_query_meta_query';
$prepared_code = 'WordPress.DB.PreparedSQL.NotPrepared';
$direct_code = 'WordPress.DB.DirectDatabaseQuery.DirectQuery';
$no_cache_code = 'WordPress.DB.DirectDatabaseQuery.NoCaching';
$owned_occurrences = array(
	'Q1' => array(
		'function' => 'vms_integrity_scan_event_plans_for_orphaned_venues',
		'directive' => '// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This finite ID-only integrity batch must locate positive Venue references before repairing broken links.',
		'target' => "'meta_query' => array(",
		'context' => "'key' => '_vms_venue_id'",
	),
	'Q2' => array(
		'function' => 'vms_integrity_list_event_plans_with_venue_issues',
		'directive' => '// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This finite ID-only reconciliation list must locate positive Venue references for operator review.',
		'target' => "'meta_query' => array(",
		'context' => "'key' => '_vms_venue_id'",
	),
	'Q3' => array(
		'function' => 'vms_integrity_list_event_plans_with_calendar_issues',
		'directive' => '// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This finite ID-only reconciliation list must combine linked calendar IDs with publish-ready plans to report integrity issues.',
		'target' => "'meta_query' => array(",
		'context' => "'key' => '_vms_tec_event_id'",
	),
	'Q4' => array(
		'function' => 'vms_integrity_scan_event_plans_for_orphaned_calendar_events',
		'directive' => '// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This finite ID-only integrity batch must combine linked calendar IDs with publish-ready plans before repairs.',
		'target' => "'meta_query' => array(",
		'context' => "'key' => '_vms_tec_event_id'",
	),
	'Q5' => array(
		'function' => 'vms_integrity_scan_event_plans_for_missing_vendors',
		'directive' => '// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This finite ID-only integrity batch must locate positive primary Vendor references before repairing broken links.',
		'target' => "'meta_query' => array(",
		'context' => "'key' => '_vms_band_vendor_id'",
	),
	'Q6' => array(
		'function' => 'vms_integrity_scan_event_plans_for_missing_vendors',
		'directive' => '// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This finite ID-only integrity batch must locate serialized secondary Vendor assignments before validating each ID.',
		'target' => "'meta_query' => array(",
		'context' => "'key' => '_vms_secondary_vendor_ids'",
	),
	'D1/N1/P1' => array(
		'function' => 'vms_event_plan_legacy_ticket_meta_candidate_ids',
		'directive' => '// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Legacy-ticket cleanup executes this immediately prepared ID batch and must read current metadata before deleting it.',
		'target' => '$rows = $wpdb->get_col($wpdb->prepare($sql, ...$params));',
		'context' => '$wpdb->prepare($sql, ...$params)',
	),
);

// Immutable Wave 3 strict-JSON evidence: nine packaged rows on seven physical occurrences.
$artifact_rows = array(
	'Q1' => array('line' => 12994, 'code' => $slow_query_code, 'occurrence' => 'Q1'),
	'Q2' => array('line' => 13083, 'code' => $slow_query_code, 'occurrence' => 'Q2'),
	'Q3' => array('line' => 13189, 'code' => $slow_query_code, 'occurrence' => 'Q3'),
	'Q4' => array('line' => 13294, 'code' => $slow_query_code, 'occurrence' => 'Q4'),
	'Q5' => array('line' => 13442, 'code' => $slow_query_code, 'occurrence' => 'Q5'),
	'Q6' => array('line' => 13463, 'code' => $slow_query_code, 'occurrence' => 'Q6'),
	'P1' => array('line' => 14104, 'code' => $prepared_code, 'occurrence' => 'D1/N1/P1'),
	'D1' => array('line' => 14104, 'code' => $direct_code, 'occurrence' => 'D1/N1/P1'),
	'N1' => array('line' => 14104, 'code' => $no_cache_code, 'occurrence' => 'D1/N1/P1'),
);
$owned_source = '';
foreach ($owned_functions as $function) {
	$function_source = event_plan_extract_function($source, $function);
	event_plan_same($function_source, event_plan_extract_function($shadow_source, $function), $function . ' should remain mirror/shadow-live identical.');
	$owned_source .= "\n" . $function_source;
}
event_plan_assert(hash('sha256', $source) !== hash('sha256', $shadow_source), 'Intentional whole-file Event Plan divergence should remain preserved.');
$mirror_actual_codes = event_plan_validate_occurrence_anchors($source, $owned_occurrences);
$shadow_actual_codes = event_plan_validate_occurrence_anchors($shadow_source, $owned_occurrences);
event_plan_same($mirror_actual_codes, $shadow_actual_codes, 'Mirror/shadow occurrence directives and anchors should remain identical.');

$expected_directives = array_column($owned_occurrences, 'directive');
event_plan_validate_owned_db_annotations($owned_source, $expected_directives);
$mirror_baseline = event_plan_strip_owned_annotations($source, $owned_occurrences);
$shadow_baseline = event_plan_strip_owned_annotations($shadow_source, $owned_occurrences);
event_plan_same(7, $mirror_baseline['removed'], 'The authoritative mirror projection must strip exactly seven owned comments.');
event_plan_same(7, $shadow_baseline['removed'], 'The authoritative shadow projection must strip exactly seven owned comments.');
event_plan_same('9f79047a6eaf35cc47e877bf6f65415d6ef66e0ab3013f9749cd30bde93b677a', hash('sha256', $mirror_baseline['source']), 'Mirror whole-source baseline changed outside the seven owned comments.');
event_plan_same('2378e0d997513114f04a65804170969c78868af64d496bb1442b03a974630f8d', hash('sha256', $shadow_baseline['source']), 'Shadow whole-source baseline changed outside the seven owned comments.');

foreach (array(
	'mirror' => array($mirror_baseline['source'], '9f79047a6eaf35cc47e877bf6f65415d6ef66e0ab3013f9749cd30bde93b677a'),
	'shadow' => array($shadow_baseline['source'], '2378e0d997513114f04a65804170969c78868af64d496bb1442b03a974630f8d'),
) as $tree => $baseline_case) {
	$mutated_source = str_replace('ORDER BY p.ID ASC', 'ORDER BY p.ID DESC', $baseline_case[0], $mutation_count);
	event_plan_same(1, $mutation_count, 'The non-comment runtime mutation control should change one SQL ordering token: ' . $tree . '.');
	event_plan_assert(hash('sha256', $mutated_source) !== $baseline_case[1], 'The immutable whole-source baseline must reject non-comment runtime mutation: ' . $tree . '.');
}

$artifact_counts = array_count_values(array_column($artifact_rows, 'code'));
ksort($artifact_counts);
$expected_artifact_counts = array(
	$direct_code => 1,
	$no_cache_code => 1,
	$prepared_code => 1,
	$slow_query_code => 6,
);
ksort($expected_artifact_counts);
event_plan_same($expected_artifact_counts, $artifact_counts, 'Artifact row identities should derive the exact Q6/D1/N1/P1 inventory.');

$covered_artifact_rows = array();
foreach ($owned_occurrences as $occurrence_id => $occurrence) {
	$artifact_codes = array();
	foreach ($artifact_rows as $artifact_id => $artifact_row) {
		if ($artifact_row['occurrence'] === $occurrence_id) {
			$artifact_codes[] = $artifact_row['code'];
			$covered_artifact_rows[$artifact_id] = $artifact_row;
		}
	}
	event_plan_same($artifact_codes, $mirror_actual_codes[$occurrence_id], 'Artifact rows no longer match the exact directive codes at occurrence ' . $occurrence_id . '.');
}
$residual_artifact_rows = array_diff_key($artifact_rows, $covered_artifact_rows);
event_plan_same(array(), $residual_artifact_rows, 'Every artifact row must be covered by one exact anchored occurrence.');
event_plan_same(array_keys($artifact_rows), array_keys($covered_artifact_rows), 'Artifact coverage must preserve every row identity, not just aggregate counts.');

foreach (array(
	$owned_source . "\n// phpcs:disable WordPress.DB",
	$owned_source . "\n// phpcs:ignore WordPress.DB -- invented broad category suppression",
	$owned_source . "\n// phpcs:ignore WordPress.DB.SlowDBQuery -- invented broad family suppression",
	$owned_source . "\n// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.Security.EscapeOutput.OutputNotEscaped -- invented mixed-category suppression",
	$owned_source . "\n// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.DirectDatabaseQuery.DirectQuery -- invented mixed-occurrence suppression",
) as $negative_scope) {
	$rejected = false;
	try {
		event_plan_validate_owned_db_annotations($negative_scope, $expected_directives);
	} catch (RuntimeException $exception) {
		$rejected = true;
	}
	event_plan_assert($rejected, 'Broad-suppression negative control should be rejected.');
}
event_plan_assert(strpos($owned_source, 'wp_cache_') === false && strpos($owned_source, '_transient') === false, 'The fresh integrity/cleanup reads should not gain persistent caching.');

foreach ($owned_functions as $function) {
	eval(event_plan_extract_function($source, $function));
}

// Whole-source hashes above are authoritative for every runtime query/SQL token; spies below also lock exact calls and behavioral results.
event_plan_reset_runtime();
WP_Query::$queue = array(array(), array(), array(), array(), array(), array());
$empty_venue_scan = vms_integrity_scan_event_plans_for_orphaned_venues(17);
$empty_venue_list = vms_integrity_list_event_plans_with_venue_issues(0);
$empty_calendar_list = vms_integrity_list_event_plans_with_calendar_issues(19);
$empty_calendar_scan = vms_integrity_scan_event_plans_for_orphaned_calendar_events(-4);
$empty_vendor_scan = vms_integrity_scan_event_plans_for_missing_vendors(23);
event_plan_same(6, count(WP_Query::$calls), 'The five integrity functions should retain six query occurrences.');
$venue_meta_query = array(array('key' => '_vms_venue_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'));
$calendar_meta_query = array(
	'relation' => 'OR',
	array('key' => '_vms_tec_event_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'),
	array('key' => '_vms_event_plan_status', 'value' => array('published', 'ready'), 'compare' => 'IN'),
);
$integrity_query_args = static function (int $limit, array $meta_query): array {
	return array(
		'post_type' => 'vms_event_plan',
		'post_status' => array('publish', 'draft'),
		'posts_per_page' => $limit,
		'fields' => 'ids',
		'no_found_rows' => true,
		'orderby' => 'ID',
		'order' => 'DESC',
		'meta_query' => $meta_query,
	);
};
$expected_query_calls = array(
	$integrity_query_args(17, $venue_meta_query),
	$integrity_query_args(500, $venue_meta_query),
	$integrity_query_args(19, $calendar_meta_query),
	$integrity_query_args(500, $calendar_meta_query),
	$integrity_query_args(23, array(array('key' => '_vms_band_vendor_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'))),
	$integrity_query_args(23, array(array('key' => '_vms_secondary_vendor_ids', 'compare' => 'EXISTS'))),
);
event_plan_same($expected_query_calls, WP_Query::$calls, 'All six WP_Query/get_posts argument arrays must remain exact, including the absence of extra arguments.');
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
WP_Query::$queue[] = array(105, 106, 107, 108);
$GLOBALS['event_plan_meta'] = array(
	105 => array('_vms_venue_id' => 205, '_vms_event_plan_status' => 'published'),
	106 => array('_vms_venue_id' => 206, '_vms_event_plan_status' => 'ready'),
	107 => array('_vms_venue_id' => 207, '_vms_event_plan_status' => 'ready'),
	108 => array('_vms_venue_id' => 208, '_vms_event_plan_status' => 'published'),
);
$GLOBALS['event_plan_venue_states'] = array(205 => 'missing', 206 => 'ok', 207 => 'trashed', 208 => 'unpublished');
$GLOBALS['event_plan_posts'][105] = new WP_Post(105, 'vms_event_plan', 'publish', 'Broken Venue Plan');
$GLOBALS['event_plan_posts'][107] = new WP_Post(107, 'vms_event_plan', 'publish', 'Trashed Venue Plan');
$GLOBALS['event_plan_posts'][108] = new WP_Post(108, 'vms_event_plan', 'publish', 'Unpublished Venue Plan');
$GLOBALS['event_plan_posts'][207] = new WP_Post(207, 'vms_venue', 'trash', 'Trashed Scan Venue');
$GLOBALS['event_plan_posts'][208] = new WP_Post(208, 'vms_venue', 'draft', 'Draft Scan Venue');
$venue_scan = vms_integrity_scan_event_plans_for_orphaned_venues(4);
event_plan_same(array('checked' => 4, 'flagged_missing_venue' => 1, 'flagged_trashed_venue' => 1, 'flagged_venue_unpublished' => 1, 'cleared_venue_refs' => 1, 'forced_draft' => 3), $venue_scan, 'Venue repair scan counters changed.');
event_plan_same(0, $GLOBALS['event_plan_meta'][105]['_vms_venue_id'], 'Missing Venue reference should still be cleared.');
event_plan_same('draft', $GLOBALS['event_plan_meta'][105]['_vms_event_plan_status'], 'Broken published plan should still be forced to Draft.');
event_plan_same(207, $GLOBALS['event_plan_meta'][107]['_vms_venue_id'], 'Trashed Venue reference should remain available for operator repair.');
event_plan_same(208, $GLOBALS['event_plan_meta'][108]['_vms_venue_id'], 'Unpublished Venue reference should remain available for operator repair.');
event_plan_same(array(
	array('missing_venue', array(105, 205, '')),
	array('trashed_venue', array(107, 207, 'Trashed Scan Venue')),
	array('venue_unpublished', array(108, 208, 'Draft Scan Venue')),
), $GLOBALS['event_plan_flags'], 'Venue scan flag calls changed.');
event_plan_same(array(105, 107, 108), array_column($GLOBALS['event_plan_perf_updates'], 'plan_id'), 'Venue scan Draft update targets changed.');
event_plan_same(array_fill(0, 3, 'event_plan_force_draft_scan_vendor_or_venue'), array_column($GLOBALS['event_plan_perf_updates'], 'context'), 'Venue scan update contexts changed.');

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
$expected_prepared_sql = "SELECT DISTINCT p.ID FROM wp_cleanup_posts p INNER JOIN wp_cleanup_postmeta pm ON pm.post_id = p.ID WHERE p.post_type = 'vms_event_plan' AND p.post_status IN ('publish','private','draft','pending','future') AND pm.meta_key IN ('_vms_legacy_ticket_product_id','_vms_legacy_ticket_price') AND p.ID > 0 ORDER BY p.ID ASC LIMIT 200";
event_plan_same($expected_prepared_sql, event_plan_normalize_sql($wpdb->prepares[0]['sql']), 'Normalized prepared legacy candidate SQL changed.');
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
