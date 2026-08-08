<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');

final class VMS_Goals_WPDB_Spy
{
	public string $prefix = 'wp_';
	public int $insert_id = 700;
	public array $log = array();
	public array $prepares = array();
	public array $get_var_queue = array();
	public array $get_results_queue = array();
	public array $get_row_queue = array();
	public array $update_queue = array();
	public array $insert_queue = array();
	public array $delete_queue = array();
	public array $query_queue = array();

	public function prepare(string $sql, ...$args): string
	{
		if (count($args) === 1 && is_array($args[0])) {
			$args = array_values($args[0]);
		}
		preg_match_all('/(?<!%)%(?:\\d+\\$)?[sdi]/', $sql, $matches);
		if (count($matches[0]) !== count($args)) {
			throw new RuntimeException('Placeholder mismatch: ' . $sql);
		}
		$index = 0;
		$final = (string) preg_replace_callback(
			'/(?<!%)%(?:\\d+\\$)?[sdi]/',
			function (array $match) use (&$index, $args): string {
				$value = $args[$index++];
				$type = substr($match[0], -1);
				if ($type === 'd') {
					return (string) (int) $value;
				}
				if ($type === 'i') {
					return chr(96) . str_replace(chr(96), chr(96) . chr(96), (string) $value) . chr(96);
				}
				return "'" . str_replace(array('\\\\', "'"), array('\\\\\\\\', "\\'"), (string) $value) . "'";
			},
			$sql
		);
		$call = array('sql' => $sql, 'args' => $args, 'final' => $final);
		$this->prepares[] = $call;
		$this->log[] = array_merge(array('kind' => 'prepare'), $call);
		return $final;
	}

	public function get_var(string $sql)
	{
		$result = $this->shift($this->get_var_queue, null);
		$this->log[] = array('kind' => 'get_var', 'sql' => $sql, 'result' => $result);
		return $result;
	}

	public function get_results(string $sql, $output = ARRAY_A)
	{
		unset($output);
		$result = $this->shift($this->get_results_queue, array());
		$this->log[] = array('kind' => 'get_results', 'sql' => $sql, 'result' => $result);
		return $result;
	}

	public function get_row(string $sql, $output = ARRAY_A)
	{
		unset($output);
		$result = $this->shift($this->get_row_queue, null);
		$this->log[] = array('kind' => 'get_row', 'sql' => $sql, 'result' => $result);
		return $result;
	}

	public function update(string $table, array $data, array $where, array $format, array $where_format)
	{
		$result = $this->shift($this->update_queue, 1);
		$this->log[] = compact('table', 'data', 'where', 'format', 'where_format', 'result') + array('kind' => 'update');
		return $result;
	}

	public function insert(string $table, array $data, array $format)
	{
		$result = $this->shift($this->insert_queue, 1);
		$this->log[] = compact('table', 'data', 'format', 'result') + array('kind' => 'insert');
		return $result;
	}

	public function delete(string $table, array $where, array $where_format)
	{
		$result = $this->shift($this->delete_queue, 1);
		$this->log[] = compact('table', 'where', 'where_format', 'result') + array('kind' => 'delete');
		return $result;
	}

	public function query(string $sql)
	{
		$result = $this->shift($this->query_queue, 1);
		$this->log[] = array('kind' => 'query', 'sql' => $sql, 'result' => $result);
		return $result;
	}

	private function shift(array &$queue, $default)
	{
		return $queue === array() ? $default : array_shift($queue);
	}
}

final class WP_Query
{
	public static array $queue = array();
	public static array $calls = array();
	public array $posts = array();

	public function __construct(array $args)
	{
		self::$calls[] = $args;
		$this->posts = self::$queue === array() ? array() : array_shift(self::$queue);
	}
}

function absint($value): int { return abs((int) $value); }
function sanitize_key($value): string
{
	$value = is_scalar($value) ? strtolower((string) $value) : '';
	$clean = preg_replace('/[^a-z0-9_\\-]+/', '', $value);
	return is_string($clean) ? $clean : '';
}
function sanitize_text_field($value): string { return is_scalar($value) ? trim(strip_tags((string) $value)) : ''; }
function wp_parse_args(array $args, array $defaults = array()): array { return array_merge($defaults, $args); }
function get_option(string $key, $default = false)
{
	unset($key);
	return $GLOBALS['vms_goal_options'] ?? $default;
}
function update_option(string $key, $value, bool $autoload = false): bool
{
	unset($key, $autoload);
	$GLOBALS['vms_goal_options'] = $value;
	return true;
}
function wp_timezone(): DateTimeZone { return new DateTimeZone('UTC'); }
function wp_date(string $format, $timestamp = null, $timezone = null): string
{
	unset($timestamp, $timezone);
	return $format === 'Y-m-d' ? '2026-08-10' : '2026-08-10 12:00:00';
}
function apply_filters(string $hook, $value)
{
	unset($hook);
	return $value;
}
function get_post_meta(int $id, string $key, bool $single = false)
{
	unset($single);
	return $GLOBALS['vms_goal_meta'][$id][$key] ?? '';
}
function vms_event_plan_should_include(int $id, string $context, array $args): bool
{
	unset($context, $args);
	return !in_array($id, $GLOBALS['vms_goal_excluded_ids'] ?? array(), true);
}
function vms_goals_get_event_pnl(int $id, array $args = array()): array
{
	unset($args);
	return $GLOBALS['vms_goal_pnl'][$id] ?? array();
}

function goal_check($condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}
function goal_same($expected, $actual, string $message): void
{
	goal_check($expected === $actual, $message . "\\nExpected: " . var_export($expected, true) . "\\nActual: " . var_export($actual, true));
}
function goal_contains(string $needle, string $haystack, string $message): void
{
	goal_check(strpos($haystack, $needle) !== false, $message . "\\nMissing: " . $needle . "\\nSQL: " . $haystack);
}
function goal_reset(VMS_Goals_WPDB_Spy $db): void
{
	$db->log = array();
	$db->prepares = array();
	$db->get_var_queue = array();
	$db->get_results_queue = array();
	$db->get_row_queue = array();
	$db->update_queue = array();
	$db->insert_queue = array();
	$db->delete_queue = array();
	$db->query_queue = array();
	$db->insert_id = 700;
	WP_Query::$queue = array();
	WP_Query::$calls = array();
	$GLOBALS['vms_goal_options'] = array('default_trailing_window_events' => 2);
	$GLOBALS['vms_goal_meta'] = array();
	$GLOBALS['vms_goal_pnl'] = array();
	$GLOBALS['vms_goal_excluded_ids'] = array();
}
function goal_last_prepare(VMS_Goals_WPDB_Spy $db): array
{
	$call = end($db->prepares);
	goal_check(is_array($call), 'Expected prepare call.');
	return $call;
}
function goal_last_call(VMS_Goals_WPDB_Spy $db, string $kind): array
{
	for ($index = count($db->log) - 1; $index >= 0; $index--) {
		if (($db->log[$index]['kind'] ?? '') === $kind) {
			return $db->log[$index];
		}
	}
	throw new RuntimeException('Missing call kind: ' . $kind);
}
function goal_kinds(VMS_Goals_WPDB_Spy $db): array
{
	return array_column($db->log, 'kind');
}
function goal_input(bool $active = false): array
{
	return array(
		'name' => 'Monthly Profit',
		'metric' => 'true_profit',
		'period_type' => 'custom',
		'period_start_local' => '2026-08-01 00:00:00',
		'period_end_local' => '2026-09-01 00:00:00',
		'target_cents' => 10000,
		'allocation_mode' => 'even',
		'weight_mode' => 'none',
		'venue_id' => 6,
		'is_active' => $active ? 1 : 0,
	);
}

$wpdb = new VMS_Goals_WPDB_Spy();
$GLOBALS['wpdb'] = $wpdb;
$source_path = dirname(__DIR__) . '/includes/core/goals-forecast.php';
$source = file_get_contents($source_path);
goal_check(is_string($source) && $source !== '', 'Goals source should be readable.');
require $source_path;

// Invalid repository IDs fail closed without database activity.
goal_reset($wpdb);
goal_same(array(), vms_goals_get_goal(0), 'Goal get should reject zero IDs.');
goal_same(false, vms_goals_delete_goal(0), 'Goal delete should reject zero IDs.');
goal_same(false, vms_goals_set_active_goal(0), 'Goal activation should reject zero IDs.');
goal_same(array(), $wpdb->log, 'Invalid IDs must not touch the database.');

// Table identity and list reads preserve schema gating, ordering, results, and freshness.
goal_same('wp_vms_goals', vms_goals_table_name(), 'Goal table name should retain the WordPress prefix contract.');
goal_reset($wpdb);
$wpdb->get_var_queue[] = null;
goal_same(array(), vms_goals_list(), 'Missing goals table should return an empty list.');
goal_same(array('prepare', 'get_var'), goal_kinds($wpdb), 'Missing-table list should stop after the fresh schema probe.');

goal_reset($wpdb);
$wpdb->get_var_queue = array('wp_vms_goals', 'wp_vms_goals');
$wpdb->get_results_queue = array(
	array(array('id' => 9, 'is_active' => 1)),
	array(array('id' => 10, 'is_active' => 0)),
);
goal_same(array(array('id' => 9, 'is_active' => 1)), vms_goals_list(), 'Goal list should preserve first database result.');
goal_same(array(array('id' => 10, 'is_active' => 0)), vms_goals_list(), 'Repeated goal list should preserve fresh database result.');
$get_var_count = count(array_filter($wpdb->log, static function (array $call): bool { return ($call['kind'] ?? '') === 'get_var'; }));
$get_results_count = count(array_filter($wpdb->log, static function (array $call): bool { return ($call['kind'] ?? '') === 'get_results'; }));
goal_same(2, $get_var_count, 'Repeated lists should recheck current schema rather than retain a stale table cache.');
goal_same(2, $get_results_count, 'Repeated lists should reread current goal state rather than retain a stale result cache.');
$select_prepares = array_values(array_filter($wpdb->prepares, static function (array $call): bool { return strpos($call['sql'], 'SELECT *') === 0; }));
goal_same(array('wp_vms_goals'), $select_prepares[0]['args'], 'Goal list should prepare its table identifier.');
goal_same(
	'SELECT * FROM `wp_vms_goals` ORDER BY is_active DESC, updated_at_utc DESC, id DESC',
	$select_prepares[0]['final'],
	'Goal list should preserve active/recent ordering.'
);

goal_reset($wpdb);
$wpdb->get_row_queue[] = array('id' => 12, 'name' => 'Goal 12');
goal_same(array('id' => 12, 'name' => 'Goal 12'), vms_goals_get_goal(12), 'Goal get should preserve row results.');
$prepare = goal_last_prepare($wpdb);
goal_same(array('wp_vms_goals', 12), $prepare['args'], 'Goal get should prepare table and ID.');
goal_contains('WHERE id = 12 LIMIT 1', $prepare['final'], 'Goal get should preserve its bounded lookup.');

goal_reset($wpdb);
$wpdb->get_var_queue[] = 'wp_vms_goals';
$wpdb->get_row_queue[] = array('id' => 13, 'is_active' => 1);
goal_same(array('id' => 13, 'is_active' => 1), vms_goals_get_active_goal(), 'Active-goal read should preserve row results.');
$prepare = goal_last_prepare($wpdb);
goal_same(array('wp_vms_goals'), $prepare['args'], 'Active-goal read should prepare the table identifier.');
goal_contains('WHERE is_active = 1 ORDER BY updated_at_utc DESC, id DESC LIMIT 1', $prepare['final'], 'Active-goal read should preserve single/latest ordering.');

// Save failure contracts distinguish unavailable schema and wpdb mutation failure.
goal_reset($wpdb);
$wpdb->get_var_queue[] = null;
goal_same(
	array('ok' => false, 'message' => 'Goals table is unavailable.'),
	vms_goals_save_goal(goal_input(), 22),
	'Goal save should preserve unavailable-table failure.'
);

goal_reset($wpdb);
$wpdb->get_var_queue[] = 'wp_vms_goals';
$wpdb->update_queue[] = false;
goal_same(
	array('ok' => false, 'message' => 'Failed to update goal.'),
	vms_goals_save_goal(goal_input(), 22),
	'Goal update should preserve wpdb failure.'
);

// Active updates persist normalized data before clearing every other active row.
goal_reset($wpdb);
$wpdb->get_var_queue[] = 'wp_vms_goals';
$wpdb->update_queue[] = 0;
$wpdb->query_queue[] = 1;
$saved = vms_goals_save_goal(goal_input(true), 22);
goal_same(array('ok' => true, 'goal_id' => 22, 'message' => 'Goal saved.'), $saved, 'Zero-row update should remain a successful save.');
goal_same(array('prepare', 'get_var', 'update', 'prepare', 'query'), goal_kinds($wpdb), 'Active goal update should write the selected goal before clearing other active rows.');
$update = goal_last_call($wpdb, 'update');
goal_same('wp_vms_goals', $update['table'], 'Goal update should target the plugin-owned table.');
goal_same(array('id' => 22), $update['where'], 'Goal update should retain exact ID boundary.');
goal_same('Monthly Profit', $update['data']['name'], 'Goal update should preserve normalized goal data.');
goal_same(1, $update['data']['is_active'], 'Goal update should preserve active state.');
goal_same(count($update['data']), count($update['format']), 'Goal update should retain complete format coverage.');
$prepare = goal_last_prepare($wpdb);
goal_same(array('wp_vms_goals', 22), $prepare['args'], 'Active exclusivity should prepare table and saved ID.');
goal_same('UPDATE `wp_vms_goals` SET is_active = 0 WHERE id <> 22', $prepare['final'], 'Active exclusivity should preserve all-other-goals predicate.');

// Insert paths preserve insert identity and failure behavior.
goal_reset($wpdb);
$wpdb->get_var_queue[] = 'wp_vms_goals';
$wpdb->insert_queue[] = false;
goal_same(
	array('ok' => false, 'message' => 'Failed to create goal.'),
	vms_goals_save_goal(goal_input(), 0),
	'Goal creation should preserve wpdb failure.'
);

goal_reset($wpdb);
$wpdb->insert_id = 44;
$wpdb->get_var_queue[] = 'wp_vms_goals';
$wpdb->insert_queue[] = 1;
$wpdb->query_queue[] = 1;
goal_same(
	array('ok' => true, 'goal_id' => 44, 'message' => 'Goal saved.'),
	vms_goals_save_goal(goal_input(true), 0),
	'Goal creation should return insert identity.'
);
goal_same(array('prepare', 'get_var', 'insert', 'prepare', 'query'), goal_kinds($wpdb), 'Active creation should insert before clearing every other active row.');
$insert = goal_last_call($wpdb, 'insert');
goal_same('wp_vms_goals', $insert['table'], 'Goal creation should target the plugin-owned table.');
goal_same(count($insert['data']), count($insert['format']), 'Goal creation should retain complete format coverage.');

// Delete and activate preserve ID, mutation ordering, and false-vs-zero semantics.
goal_reset($wpdb);
$wpdb->delete_queue[] = false;
goal_same(false, vms_goals_delete_goal(5), 'Goal delete should preserve wpdb failure.');
$wpdb->delete_queue[] = 0;
goal_same(true, vms_goals_delete_goal(5), 'Zero-row goal delete should remain a successful repository operation.');
goal_same(array('id' => 5), goal_last_call($wpdb, 'delete')['where'], 'Goal delete should retain exact ID boundary.');

goal_reset($wpdb);
$wpdb->get_var_queue[] = null;
goal_same(false, vms_goals_set_active_goal(6), 'Goal activation should fail when schema is unavailable.');
goal_same(array('prepare', 'get_var'), goal_kinds($wpdb), 'Unavailable activation should not mutate rows.');

goal_reset($wpdb);
$wpdb->get_var_queue[] = 'wp_vms_goals';
$wpdb->query_queue[] = 1;
$wpdb->update_queue[] = 0;
goal_same(true, vms_goals_set_active_goal(6), 'Zero-row selected-goal update should remain successful.');
goal_same(array('prepare', 'get_var', 'prepare', 'query', 'update'), goal_kinds($wpdb), 'Goal activation should clear prior active flags before activating the selected goal.');
$clear_prepare = goal_last_prepare($wpdb);
goal_same(array('wp_vms_goals'), $clear_prepare['args'], 'Goal activation reset should prepare the table identifier.');
goal_same('UPDATE `wp_vms_goals` SET is_active = 0', $clear_prepare['final'], 'Goal activation reset should preserve full-table active-flag clearing.');
$active_update = goal_last_call($wpdb, 'update');
goal_same(array('is_active' => 1), array_intersect_key($active_update['data'], array('is_active' => true)), 'Goal activation should set the selected row active.');
goal_same(array('id' => 6), $active_update['where'], 'Goal activation should retain selected ID boundary.');

goal_reset($wpdb);
$wpdb->get_var_queue[] = 'wp_vms_goals';
$wpdb->query_queue[] = 1;
$wpdb->update_queue[] = false;
goal_same(false, vms_goals_set_active_goal(6), 'Goal activation should preserve selected-row wpdb failure.');

// Event-period selection retains bounded canonical date-meta ordering and inclusion filtering.
goal_reset($wpdb);
WP_Query::$queue[] = array(101, 102, 103, 104);
$GLOBALS['vms_goal_excluded_ids'] = array(104);
goal_same(
	array(101, 102, 103),
	vms_goals_get_event_ids_in_period('2026-08-01', '2026-09-01', 12),
	'Event-period query should preserve IDs and financial inclusion filtering.'
);
$query_args = WP_Query::$calls[0];
goal_same('vms_event_plan', $query_args['post_type'], 'Event-period query should retain Event Plan ownership.');
goal_same(12, $query_args['posts_per_page'], 'Event-period query should retain its bounded limit.');
goal_same('_vms_event_date', $query_args['meta_key'], 'Event-period query should retain canonical date ordering key.');
goal_same('meta_value', $query_args['orderby'], 'Event-period query should retain metadata ordering.');
goal_same(
	array(
		'key' => '_vms_event_date',
		'value' => array('2026-08-01', '2026-08-31'),
		'compare' => 'BETWEEN',
		'type' => 'DATE',
	),
	$query_args['meta_query'][0],
	'Event-period query should retain inclusive local-date boundaries.'
);

// Goal progress preserves completed/remaining classification and forecast arithmetic.
goal_reset($wpdb);
WP_Query::$queue[] = array(101, 102, 103);
$GLOBALS['vms_goal_meta'] = array(
	101 => array('_vms_event_date' => '2026-08-01'),
	102 => array('_vms_event_date' => '2026-08-09'),
	103 => array('_vms_event_date' => '2026-08-15'),
);
$GLOBALS['vms_goal_pnl'] = array(
	101 => array('true_profit_cents' => 1000),
	102 => array('true_profit_cents' => 3000),
);
$progress = vms_goals_compute_goal_progress(
	array(
		'metric' => 'true_profit',
		'target_cents' => 10000,
		'period_start_local' => '2026-08-01',
		'period_end_local' => '2026-09-01',
	)
);
goal_same(4000, $progress['actual_to_date_cents'], 'Goal progress should sum completed-event profit.');
goal_same(6000, $progress['remaining_required_cents'], 'Goal progress should preserve remaining target.');
goal_same(1, $progress['remaining_events_count'], 'Goal progress should classify future events as remaining.');
goal_same(6000, $progress['required_avg_per_remaining_event_cents'], 'Goal progress should preserve required average.');
goal_same(2000, $progress['trailing_avg_cents'], 'Goal progress should preserve trailing completed-event average.');
goal_same(6000, $progress['projected_end_cents'], 'Goal progress should preserve trailing-average projection.');
goal_same(4000, $progress['projection_gap_cents'], 'Goal progress should preserve projection gap.');
goal_same(
	array(
		'event_plan_id' => 103,
		'event_date' => '2026-08-15',
		'required_contribution_cents' => 6000,
	),
	$progress['allocations'][0],
	'Goal progress should preserve per-event allocation semantics.'
);

// Exact scanner-target inventory: 31 DB rows are removed while the one logging row remains untouched.
$owned_baseline = array(
	'UnescapedDBParameter' => 2,
	'DirectQuery' => 13,
	'NoCaching' => 12,
	'InterpolatedNotPrepared' => 2,
	'slow_db_query_meta_key' => 1,
	'slow_db_query_meta_query' => 1,
);
goal_same(31, array_sum($owned_baseline), 'G13 goals DB baseline should remain explicitly reconciled.');
preg_match_all('/\\$wpdb->(?:get_var|get_results|get_row|update|insert|query|delete)\\s*\\(/', $source, $db_operations);
goal_same(13, count($db_operations[0]), 'Goals custom-table operation inventory should remain explicit.');
goal_same(13, substr_count($source, 'WordPress.DB.DirectDatabaseQuery.DirectQuery'), 'Every direct operation should have one narrow DirectQuery annotation.');
goal_same(12, substr_count($source, 'WordPress.DB.DirectDatabaseQuery.NoCaching'), 'Every read/update/delete direct operation should have one narrow NoCaching annotation.');
goal_same(0, substr_count($source, 'PluginCheck.Security.DirectDB.UnescapedDBParameter'), 'Prepared table identifiers should eliminate UnescapedDBParameter findings without suppression.');
goal_same(0, substr_count($source, 'WordPress.DB.PreparedSQL.InterpolatedNotPrepared'), 'Prepared table identifiers should eliminate interpolation findings without suppression.');
goal_same(1, substr_count($source, 'WordPress.DB.SlowDBQuery.slow_db_query_meta_key'), 'Only the intentional Event Plan date-order key should carry the exact scanner slow-meta-key annotation.');
goal_same(1, substr_count($source, 'WordPress.DB.SlowDBQuery.slow_db_query_meta_query'), 'Only the intentional Event Plan date-period query should carry the exact scanner slow-meta-query annotation.');
preg_match_all('/phpcs:(?:disable|ignoreFile)\\b/i', $source, $broad_suppressions);
goal_same(0, count($broad_suppressions[0]), 'Goals remediation must not use file-wide or block-wide PHPCS suppression.');
goal_check(strpos($source, 'UPDATE {' . '$table}') === false, 'Goals SQL must not interpolate custom-table identifiers.');
goal_same(1, substr_count($source, 'error_log('), 'The deferred logging inventory should remain exactly one row.');
goal_contains("error_log('[VMS Goals] ' . " . '$message' . ');', $source, 'The deferred operational logging statement must remain untouched.');

fwrite(STDOUT, "goals forecast repository SQL remediation: PASS\n");
