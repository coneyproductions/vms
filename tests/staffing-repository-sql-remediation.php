<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

if (!defined('ARRAY_A')) {
	define('ARRAY_A', 'ARRAY_A');
}

final class VMS_Test_WPDB
{
	public string $prefix = 'wp_';
	public int $insert_id = 901;
	/** @var array<int,array<string,mixed>> */
	public array $call_log = array();
	/** @var array<int,array{query:string,args:array<int,mixed>,final_sql:string}> */
	public array $prepare_calls = array();
	/** @var array<int,mixed> */
	public array $get_col_queue = array();
	/** @var array<int,mixed> */
	public array $get_var_queue = array();
	/** @var array<int,mixed> */
	public array $get_row_queue = array();
	/** @var array<int,mixed> */
	public array $get_results_queue = array();
	/** @var mixed */
	public $insert_return = 1;
	/** @var mixed */
	public $update_return = 1;
	/** @var mixed */
	public $delete_return = 1;
	/** @var mixed */
	public $query_return = 1;

	public function prepare(string $query, ...$args): string
	{
		if (count($args) === 1 && is_array($args[0])) {
			$args = array_values($args[0]);
		}

		$arg_index = 0;
		$final_sql = (string) preg_replace_callback(
			'/(?<!%)%(?:\d+\$)?[sdi]/',
			function (array $matches) use (&$arg_index, $args): string {
				$placeholder = $matches[0];
				$value = $args[$arg_index] ?? null;
				$arg_index++;

				$type = substr($placeholder, -1);
				if ($type === 'd') {
					return (string) (int) $value;
				}
				if ($type === 'i') {
					return $this->quote_identifier((string) $value);
				}

				return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $value) . "'";
			},
			$query
		);

		$this->prepare_calls[] = array(
			'query' => $query,
			'args' => $args,
			'final_sql' => $final_sql,
		);
		$this->call_log[] = array(
			'kind' => 'prepare',
			'query' => $query,
			'args' => $args,
			'final_sql' => $final_sql,
		);

		return $final_sql;
	}

	/** @return mixed */
	public function get_col(string $query, int $column = 0)
	{
		unset($column);
		$result = $this->shift_queue($this->get_col_queue, array());
		$this->record_execution('get_col', $query, $result);
		return $result;
	}

	/** @return mixed */
	public function get_var(string $query)
	{
		$result = $this->shift_queue($this->get_var_queue, null);
		$this->record_execution('get_var', $query, $result);
		return $result;
	}

	/** @return mixed */
	public function get_row(string $query, $output = ARRAY_A)
	{
		unset($output);
		$result = $this->shift_queue($this->get_row_queue, null);
		$this->record_execution('get_row', $query, $result);
		return $result;
	}

	/** @return mixed */
	public function get_results(string $query, $output = ARRAY_A)
	{
		unset($output);
		$result = $this->shift_queue($this->get_results_queue, array());
		$this->record_execution('get_results', $query, $result);
		return $result;
	}

	/**
	 * @param array<string,mixed> $data
	 * @param array<int,string>   $format
	 * @return mixed
	 */
	public function insert(string $table, array $data, array $format)
	{
		$this->call_log[] = array(
			'kind' => 'insert',
			'table' => $table,
			'data' => $data,
			'format' => $format,
		);
		return $this->insert_return;
	}

	/**
	 * @param array<string,mixed> $data
	 * @param array<string,mixed> $where
	 * @param array<int,string>   $format
	 * @param array<int,string>   $where_format
	 * @return mixed
	 */
	public function update(string $table, array $data, array $where, array $format, array $where_format)
	{
		$this->call_log[] = array(
			'kind' => 'update',
			'table' => $table,
			'data' => $data,
			'where' => $where,
			'format' => $format,
			'where_format' => $where_format,
		);
		return $this->update_return;
	}

	/**
	 * @param array<string,mixed> $where
	 * @param array<int,string>   $where_format
	 * @return mixed
	 */
	public function delete(string $table, array $where, array $where_format)
	{
		$this->call_log[] = array(
			'kind' => 'delete',
			'table' => $table,
			'where' => $where,
			'where_format' => $where_format,
		);
		return $this->delete_return;
	}

	/** @return mixed */
	public function query(string $query)
	{
		$this->record_execution('query', $query, $this->query_return);
		return $this->query_return;
	}

	private function quote_identifier(string $identifier): string
	{
		$parts = array_map(
			static function (string $part): string {
				return '`' . str_replace('`', '``', $part) . '`';
			},
			explode('.', $identifier)
		);

		return implode('.', $parts);
	}

	/**
	 * @param array<int,mixed> $queue
	 * @param mixed            $default
	 * @return mixed
	 */
	private function shift_queue(array &$queue, $default)
	{
		if ($queue === array()) {
			return $default;
		}

		return array_shift($queue);
	}

	/**
	 * @param mixed $result
	 */
	private function record_execution(string $kind, string $query, $result): void
	{
		$this->call_log[] = array(
			'kind' => $kind,
			'query' => $query,
			'result' => $result,
		);
	}
}

function __(string $text, string $domain = ''): string
{
	unset($domain);
	return $text;
}

function current_time(string $type, bool $gmt = false): string
{
	unset($gmt);
	if ($type === 'mysql') {
		return '2026-08-02 12:00:00';
	}
	return '2026-08-02 12:00:00';
}

function sanitize_text_field($value): string
{
	return trim(strip_tags((string) $value));
}

function sanitize_textarea_field($value): string
{
	return trim((string) $value);
}

function sanitize_key(string $value): string
{
	return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', $value));
}

function wp_json_encode($value)
{
	$json = json_encode($value);
	return is_string($json) ? $json : false;
}

function get_current_user_id(): int
{
	return 77;
}

function absint($value): int
{
	return abs((int) $value);
}

function get_post_meta(int $post_id, string $key, bool $single = true)
{
	unset($single);
	return $GLOBALS['vms_test_post_meta'][$post_id][$key] ?? '';
}

function vms_staffing_ensure_template_attendance_band_schema(): bool
{
	return $GLOBALS['vms_test_template_schema_ready'];
}

function vms_staffing_ensure_template_slot_activation_schema(): bool
{
	return $GLOBALS['vms_test_template_slot_schema_ready'];
}

function vms_staffing_role_map_by_id(bool $active_only = true): array
{
	unset($active_only);
	return $GLOBALS['vms_test_role_map'];
}

function vms_staffing_get_event_role_activation_thresholds(int $event_plan_id): array
{
	return $GLOBALS['vms_test_event_thresholds'][$event_plan_id] ?? array();
}

function vms_staffing_set_event_role_activation_thresholds(int $event_plan_id, array $thresholds): void
{
	$GLOBALS['vms_test_threshold_sets'][] = array(
		'event_plan_id' => $event_plan_id,
		'thresholds' => $thresholds,
	);
	$GLOBALS['vms_test_event_thresholds'][$event_plan_id] = $thresholds;
}

function vms_staffing_set_event_applied_template_id(int $event_plan_id, int $template_id, string $source): void
{
	$GLOBALS['vms_test_applied_templates'][] = array(
		'event_plan_id' => $event_plan_id,
		'template_id' => $template_id,
		'source' => $source,
	);
}

function vms_staffing_mark_rollup_dirty(int $event_plan_id, string $reason): void
{
	$GLOBALS['vms_test_rollup_dirty'][] = array(
		'event_plan_id' => $event_plan_id,
		'reason' => $reason,
	);
}

function vms_staffing_compute_rollup(int $event_plan_id): void
{
	$GLOBALS['vms_test_rollup_computes'][] = $event_plan_id;
}

function vms_staffing_pick_template_for_event(int $venue_id, string $event_date_ymd, string $event_type = '', ?int $headcount = null): ?array
{
	$GLOBALS['vms_test_pick_template_calls'][] = array(
		'venue_id' => $venue_id,
		'event_date_ymd' => $event_date_ymd,
		'event_type' => $event_type,
		'headcount' => $headcount,
	);
	return $GLOBALS['vms_test_pick_template_result'];
}

function vms_staffing_pick_template_event_type(int $event_plan_id): string
{
	return sanitize_key((string) get_post_meta($event_plan_id, '_vms_event_type', true));
}

function bvmgr_staffing_resolve_slot_window(int $event_plan_id, array $slot): array
{
	unset($event_plan_id);
	$slot_id = isset($slot['slot_id']) ? absint($slot['slot_id']) : 0;
	return $GLOBALS['vms_test_slot_windows'][$slot_id] ?? array(
		'start_local' => null,
		'end_local' => null,
		'start_ts' => null,
		'end_ts' => null,
		'duration_minutes' => null,
	);
}

function vms_test_assert_true(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
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

	throw new RuntimeException(
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

function vms_test_assert_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert_true(strpos($haystack, $needle) !== false, $message . "\nNeedle: " . $needle . "\nHaystack: " . $haystack);
}

function vms_test_normalize_sql(string $sql): string
{
	return trim((string) preg_replace('/\s+/', ' ', $sql));
}

function vms_test_extract_function(string $source, string $name): string
{
	$matched = preg_match(
		'/function\s+' . preg_quote($name, '/') . '\s*\(/',
		$source,
		$matches,
		PREG_OFFSET_CAPTURE
	);
	if ($matched !== 1) {
		throw new RuntimeException('Unable to locate function ' . $name . '.');
	}
	$start = (int) $matches[0][1];

	$brace = strpos($source, '{', $start);
	if ($brace === false) {
		throw new RuntimeException('Unable to locate opening brace for ' . $name . '.');
	}

	$depth = 1;
	$length = strlen($source);
	$in_single = false;
	$in_double = false;
	$in_line_comment = false;
	$in_block_comment = false;
	for ($i = $brace + 1; $i < $length; $i++) {
		$char = $source[$i];
		$next_char = $i + 1 < $length ? $source[$i + 1] : '';
		$previous_char = $i > 0 ? $source[$i - 1] : '';

		if ($in_line_comment) {
			if ($char === "\n") {
				$in_line_comment = false;
			}
			continue;
		}
		if ($in_block_comment) {
			if ($char === '*' && $next_char === '/') {
				$in_block_comment = false;
				$i++;
			}
			continue;
		}
		if ($in_single) {
			if ($char === "'" && $previous_char !== '\\') {
				$in_single = false;
			}
			continue;
		}
		if ($in_double) {
			if ($char === '"' && $previous_char !== '\\') {
				$in_double = false;
			}
			continue;
		}

		if ($char === '/' && $next_char === '/') {
			$in_line_comment = true;
			$i++;
			continue;
		}
		if ($char === '/' && $next_char === '*') {
			$in_block_comment = true;
			$i++;
			continue;
		}
		if ($char === "'") {
			$in_single = true;
			continue;
		}
		if ($char === '"') {
			$in_double = true;
			continue;
		}

		if ($char === '{') {
			$depth++;
		} elseif ($char === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $start, ($i - $start) + 1);
			}
		}
	}

	throw new RuntimeException('Unable to locate closing brace for ' . $name . '.');
}

/**
 * @param array<int,string> $paths
 * @return array<int,string>
 */
function vms_test_collect_db_phpcs_inventory(array $paths): array
{
	$inventory = array();
	foreach ($paths as $path) {
		$lines = file($path, FILE_IGNORE_NEW_LINES);
		if ($lines === false) {
			throw new RuntimeException('Unable to read ' . $path . '.');
		}

		foreach ($lines as $index => $line) {
			if (strpos($line, 'phpcs:ignore') === false || strpos($line, 'WordPress.DB.DirectDatabaseQuery') === false) {
				continue;
			}

			$codes = trim((string) preg_replace('/^.*phpcs:ignore\s+([^ ]+).*$/', '$1', $line));
			$inventory[] = str_replace(dirname(__DIR__) . '/', '', $path) . ':' . ($index + 1) . ':' . $codes;
		}
	}

	return $inventory;
}

/**
 * @param array<int,string> $inventory
 * @return array<int,string>
 */
function vms_test_sort_inventory(array $inventory): array
{
	$sorted = array_values($inventory);
	sort($sorted, SORT_STRING);
	return $sorted;
}

/**
 * @param array<int,string> $group_names
 */
function vms_test_format_group_list(array $group_names): string
{
	$labels = array_values(array_map('strtoupper', $group_names));
	if ($labels === array()) {
		return '';
	}
	if (count($labels) === 1) {
		return $labels[0];
	}
	if (count($labels) === 2) {
		return $labels[0] . ' or ' . $labels[1];
	}

	$last = array_pop($labels);
	return implode(', ', $labels) . ', or ' . $last;
}

/**
 * @param array<int,string>                $actual_inventory
 * @param array<string,array<int,string>>  $expected_groups
 * @return array<string,array<int,string>>
 */
function vms_test_reconcile_directquery_inventory_groups(array $actual_inventory, array $expected_groups): array
{
	$group_names = array_keys($expected_groups);
	for ($i = 0; $i < count($group_names); $i++) {
		for ($j = $i + 1; $j < count($group_names); $j++) {
			$overlap = array_values(array_intersect($expected_groups[$group_names[$i]], $expected_groups[$group_names[$j]]));
			vms_test_assert_same(
				array(),
				$overlap,
				'DirectQuery/NoCaching ownership must remain disjoint between ' . $group_names[$i] . ' and ' . $group_names[$j] . '.'
			);
		}
	}

	$duplicate_inventory = array_keys(
		array_filter(
			array_count_values($actual_inventory),
			static function (int $count): bool {
				return $count > 1;
			}
		)
	);
	vms_test_assert_same(array(), $duplicate_inventory, 'DirectQuery/NoCaching inventory should not contain duplicate suppression entries.');

	$lookups = array();
	foreach ($expected_groups as $group_name => $entries) {
		$lookups[$group_name] = array_fill_keys($entries, true);
	}

	$actual_groups = array();
	$classifications = array();
	$unknown_inventory = array();
	foreach (array_keys($expected_groups) as $group_name) {
		$actual_groups[$group_name] = array();
	}

	foreach ($actual_inventory as $entry) {
		$matched_group = null;
		foreach ($lookups as $group_name => $lookup) {
			if (isset($lookup[$entry])) {
				$matched_group = $group_name;
				break;
			}
		}

		if ($matched_group === null) {
			$unknown_inventory[] = $entry;
			continue;
		}

		$actual_groups[$matched_group][] = $entry;
		$classifications[] = $entry;
	}

	vms_test_assert_same(
		array(),
		$unknown_inventory,
		'Every DirectQuery/NoCaching suppression must be classified as ' . vms_test_format_group_list($group_names) . '.'
	);
	foreach ($expected_groups as $group_name => $entries) {
		vms_test_assert_same($entries, $actual_groups[$group_name], 'The accepted ' . strtoupper($group_name) . ' DirectQuery/NoCaching inventory should remain exact.');
	}
	$expected_union = array();
	foreach ($expected_groups as $entries) {
		$expected_union = array_merge($expected_union, $entries);
	}
	vms_test_assert_same(
		vms_test_sort_inventory($expected_union),
		vms_test_sort_inventory($actual_inventory),
		'The combined DirectQuery/NoCaching inventories should reconcile to the complete actual inventory.'
	);
	vms_test_assert_same($actual_inventory, $classifications, 'The classified DirectQuery/NoCaching inventory should preserve the full actual inventory order.');

	return $actual_groups;
}

/**
 * @return array<string,string>
 */
function vms_test_collect_target_hashes(string $source, array $function_names): array
{
	$hashes = array();
	foreach ($function_names as $function_name) {
		$hashes[$function_name] = hash('sha256', vms_test_extract_function($source, $function_name));
	}
	return $hashes;
}

/**
 * @return array<string,mixed>
 */
function vms_test_last_call(VMS_Test_WPDB $wpdb, string $kind): array
{
	for ($i = count($wpdb->call_log) - 1; $i >= 0; $i--) {
		if (($wpdb->call_log[$i]['kind'] ?? '') === $kind) {
			return $wpdb->call_log[$i];
		}
	}

	throw new RuntimeException('Unable to locate call log entry for ' . $kind . '.');
}

/**
 * @return array<int,array<string,mixed>>
 */
function vms_test_filter_calls(VMS_Test_WPDB $wpdb, string $kind): array
{
	return array_values(
		array_filter(
			$wpdb->call_log,
			static function (array $call) use ($kind): bool {
				return ($call['kind'] ?? '') === $kind;
			}
		)
	);
}

function vms_test_find_prepare(VMS_Test_WPDB $wpdb, string $needle): array
{
	foreach ($wpdb->prepare_calls as $prepare_call) {
		if (strpos(vms_test_normalize_sql($prepare_call['query']), $needle) !== false) {
			return $prepare_call;
		}
	}

	throw new RuntimeException('Unable to locate prepare() call containing ' . $needle . '.');
}

function vms_test_assert_no_placeholders(string $sql, string $message): void
{
	vms_test_assert_true(preg_match('/%(?:\d+\$)?[sdi]/', $sql) !== 1, $message . "\nSQL: " . $sql);
}

function vms_test_reset_state(): VMS_Test_WPDB
{
	$wpdb = new VMS_Test_WPDB();
	$GLOBALS['wpdb'] = $wpdb;
	$GLOBALS['vms_test_post_meta'] = array();
	$GLOBALS['vms_test_role_map'] = array(
		5 => array('name' => 'Ops'),
		9 => array('name' => 'Usher'),
	);
	$GLOBALS['vms_test_template_schema_ready'] = true;
	$GLOBALS['vms_test_template_slot_schema_ready'] = true;
	$GLOBALS['vms_test_event_thresholds'] = array();
	$GLOBALS['vms_test_threshold_sets'] = array();
	$GLOBALS['vms_test_applied_templates'] = array();
	$GLOBALS['vms_test_rollup_dirty'] = array();
	$GLOBALS['vms_test_rollup_computes'] = array();
	$GLOBALS['vms_test_pick_template_result'] = null;
	$GLOBALS['vms_test_pick_template_calls'] = array();
	$GLOBALS['vms_test_slot_windows'] = array();

	return $wpdb;
}

$plugin_root = dirname(__DIR__);
$live_plugin_root = dirname($plugin_root, 2) . '/vms';
$store_path = $plugin_root . '/includes/modules/staff-tasks/store.php';
$db_path = $plugin_root . '/includes/modules/staff-tasks/db.php';
$admin_ui_path = $plugin_root . '/includes/modules/staff-tasks/admin-ui.php';
$staff_portal_path = $plugin_root . '/includes/portal/staff-portal.php';
$staffing_path = $plugin_root . '/includes/core/staffing.php';
$live_staffing_path = $live_plugin_root . '/includes/core/staffing.php';

$store_source = (string) file_get_contents($store_path);
$db_source = (string) file_get_contents($db_path);
$admin_ui_source = (string) file_get_contents($admin_ui_path);
$staff_portal_source = (string) file_get_contents($staff_portal_path);
$staffing_source = (string) file_get_contents($staffing_path);
$live_staffing_source = (string) file_get_contents($live_staffing_path);

$expected_t1_inventory = array(
	'includes/modules/staff-tasks/store.php:221:WordPress.DB.DirectDatabaseQuery.DirectQuery',
	'includes/modules/staff-tasks/store.php:247:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:270:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:297:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:311:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:324:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:336:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:409:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:419:WordPress.DB.DirectDatabaseQuery.DirectQuery',
	'includes/modules/staff-tasks/store.php:438:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:466:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:481:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:495:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:509:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:522:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:536:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:549:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:561:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:629:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:639:WordPress.DB.DirectDatabaseQuery.DirectQuery',
	'includes/modules/staff-tasks/store.php:664:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:704:WordPress.DB.DirectDatabaseQuery.DirectQuery',
	'includes/modules/staff-tasks/store.php:925:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:973:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:989:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:1003:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:1016:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/admin-ui.php:274:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/db.php:55:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
);

$expected_t2_inventory = array(
	'includes/modules/staff-tasks/store.php:1088:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:1158:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:1246:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:1362:WordPress.DB.DirectDatabaseQuery.DirectQuery',
	'includes/modules/staff-tasks/store.php:1421:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:1595:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:1677:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:1778:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:1810:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:1848:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:1861:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:1876:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:1912:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/store.php:1936:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/portal/staff-portal.php:741:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/portal/staff-portal.php:1208:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
);

$expected_t3_inventory = array(
	'includes/core/staffing.php:46:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:90:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:1231:WordPress.DB.DirectDatabaseQuery.DirectQuery',
	'includes/core/staffing.php:1257:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:1283:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:1306:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:1308:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:1328:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:1462:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:1483:WordPress.DB.DirectDatabaseQuery.DirectQuery',
	'includes/core/staffing.php:1510:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:1513:WordPress.DB.DirectDatabaseQuery.DirectQuery',
	'includes/core/staffing.php:1729:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:1738:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:1760:WordPress.DB.DirectDatabaseQuery.DirectQuery',
	'includes/core/staffing.php:1965:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:1973:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:1997:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:2018:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:2136:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:2175:WordPress.DB.DirectDatabaseQuery.DirectQuery',
);

$expected_t4_inventory = array(
	'includes/core/staffing.php:2491:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:2495:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:3071:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:3136:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:3149:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:3171:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:3196:WordPress.DB.DirectDatabaseQuery.DirectQuery',
	'includes/core/staffing.php:3230:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:3251:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:3265:WordPress.DB.DirectDatabaseQuery.DirectQuery',
	'includes/core/staffing.php:3288:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:3352:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:3438:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:3455:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:3597:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:3692:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:3779:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
);

$expected_t5_inventory = array(
	'includes/core/staffing.php:710:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:3815:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:3915:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
);

$actual_inventory = vms_test_collect_db_phpcs_inventory(array($store_path, $admin_ui_path, $db_path, $staff_portal_path, $staffing_path));
vms_test_reconcile_directquery_inventory_groups(
	$actual_inventory,
	array(
		't1' => $expected_t1_inventory,
		't2' => $expected_t2_inventory,
		't3' => $expected_t3_inventory,
		't4' => $expected_t4_inventory,
		't5' => $expected_t5_inventory,
	)
);
$invented_inventory = $actual_inventory;
$invented_inventory[] = 'includes/core/staffing.php:999999:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching';
$negative_control_rejected = false;
try {
	vms_test_reconcile_directquery_inventory_groups(
		$invented_inventory,
		array(
			't1' => $expected_t1_inventory,
			't2' => $expected_t2_inventory,
			't3' => $expected_t3_inventory,
			't4' => $expected_t4_inventory,
			't5' => $expected_t5_inventory,
		)
	);
} catch (RuntimeException $exception) {
	$negative_control_rejected = true;
	vms_test_assert_contains(
		'Every DirectQuery/NoCaching suppression must be classified as T1, T2, T3, T4, or T5.',
		$exception->getMessage(),
		'Synthetic negative control should fail because the invented suppression is unclassified.'
	);
}
vms_test_assert_true($negative_control_rejected, 'Synthetic negative control should be rejected.');
vms_test_assert_true(strpos($staffing_source, 'phpcs:disable') === false, 'No file-level or block-level PHPCS disable should appear in staffing.php.');

$target_functions = array(
	'vms_staffing_templates_have_attendance_band_columns',
	'vms_staffing_template_slots_have_activation_threshold_column',
	'vms_staffing_audit_log',
	'vms_staffing_get_templates',
	'vms_staffing_get_template',
	'vms_staffing_delete_template',
	'vms_staffing_get_template_slots',
	'vms_staffing_save_template',
	'vms_staffing_apply_template_to_event',
	'vms_staffing_sync_assignment_shift_timestamps_for_slot',
	'bvmgr_staffing_get_event_slots',
	'vms_staffing_seed_event_slots_from_template',
);
vms_test_assert_same(
	vms_test_collect_target_hashes($staffing_source, $target_functions),
	vms_test_collect_target_hashes($live_staffing_source, $target_functions),
	'Mirror and live staffing repository targets should remain byte-identical.'
);

foreach (array(
	'vms_staffing_table_name',
	'vms_staffing_now_mysql_utc',
	'vms_staffing_templates_have_attendance_band_columns',
	'vms_staffing_template_slots_have_activation_threshold_column',
	'vms_staffing_audit_log',
	'vms_staffing_get_templates',
	'vms_staffing_get_template',
	'vms_staffing_delete_template',
	'vms_staffing_get_template_slots',
	'vms_staffing_template_normalize_slot_row',
	'vms_staffing_save_template',
	'vms_staffing_apply_template_to_event',
	'vms_staffing_sync_assignment_shift_timestamps_for_slot',
	'bvmgr_staffing_get_event_slots',
	'vms_staffing_seed_event_slots_from_template',
) as $function_name) {
	eval(vms_test_extract_function($staffing_source, $function_name));
}

try {
	$wpdb = vms_test_reset_state();
	$wpdb->get_col_queue = array(
		array('template_id', 'min_headcount', 'max_headcount'),
		array('template_slot_id', 'activation_threshold'),
	);
	vms_test_assert_true(vms_staffing_templates_have_attendance_band_columns(), 'Template attendance-band schema probe should report both expected columns.');
	vms_test_assert_true(vms_staffing_template_slots_have_activation_threshold_column(), 'Template-slot schema probe should report the activation-threshold column.');
	vms_test_assert_same('DESC %i', $wpdb->prepare_calls[0]['query'], 'Template schema probe should prepare the DESC statement with an identifier placeholder.');
	vms_test_assert_same(array('wp_vms_staffing_templates'), $wpdb->prepare_calls[0]['args'], 'Template schema probe should prepare the templates table identifier.');
	vms_test_assert_same('DESC %i', $wpdb->prepare_calls[1]['query'], 'Template-slot schema probe should prepare the DESC statement with an identifier placeholder.');
	vms_test_assert_same(array('wp_vms_staffing_template_slots'), $wpdb->prepare_calls[1]['args'], 'Template-slot schema probe should prepare the template-slots table identifier.');
	foreach (vms_test_filter_calls($wpdb, 'get_col') as $call) {
		vms_test_assert_no_placeholders($call['query'], 'Schema probes should execute fully prepared SQL.');
	}

	$wpdb = vms_test_reset_state();
	vms_staffing_audit_log('template_save', 45, array('before' => 1), array('after' => 2), 19);
	$audit_inserts = vms_test_filter_calls($wpdb, 'insert');
	vms_test_assert_same(1, count($audit_inserts), 'Audit logging should perform exactly one insert.');
	vms_test_assert_same('wp_vms_staffing_audit_log', $audit_inserts[0]['table'], 'Audit logging should target the staffing audit table.');
	vms_test_assert_same('template_save', $audit_inserts[0]['data']['action'], 'Audit logging should preserve the sanitized action key.');
	vms_test_assert_same('2026-08-02 12:00:00', $audit_inserts[0]['data']['created_at'], 'Audit logging should persist the current UTC timestamp.');

	$wpdb = vms_test_reset_state();
	$wpdb->get_results_queue = array(
		array(
			array('template_id' => 9, 'priority' => 200),
			array('template_id' => 4, 'priority' => 100),
		),
	);
	$template_rows = vms_staffing_get_templates(array('is_active' => 1, 'auto_apply' => 0));
	vms_test_assert_same(9, $template_rows[0]['template_id'], 'Template list reads should preserve the database ordering.');
	$prepare = vms_test_find_prepare($wpdb, 'SELECT * FROM %i WHERE (%d = -1 OR is_active = %d) AND (%d = -1 OR auto_apply_on_event_create = %d) ORDER BY priority DESC, template_id ASC');
	vms_test_assert_same(array('wp_vms_staffing_templates', 1, 1, 0, 0), $prepare['args'], 'Template list reads should bind both filter sentinels and values explicitly.');
	vms_test_assert_no_placeholders($prepare['final_sql'], 'Template list final SQL should not retain unresolved placeholders.');
	vms_test_assert_no_placeholders(vms_test_last_call($wpdb, 'get_results')['query'], 'Template list reads should execute fully prepared SQL.');

	$wpdb = vms_test_reset_state();
	$wpdb->get_results_queue = array(array());
	vms_staffing_get_templates(array());
	$prepare = vms_test_last_call($wpdb, 'prepare');
	vms_test_assert_same(array('wp_vms_staffing_templates', -1, -1, -1, -1), $prepare['args'], 'Unfiltered template list reads should keep both sentinel filters disabled.');

	$wpdb = vms_test_reset_state();
	$wpdb->get_row_queue = array(
		array('template_id' => 55, 'name' => 'VIP'),
	);
	$wpdb->get_results_queue = array(
		array(
			array('template_slot_id' => 2, 'role_id' => 5),
			array('template_slot_id' => 5, 'role_id' => 9),
		),
	);
	$template = vms_staffing_get_template(55);
	vms_test_assert_same(55, $template['template_id'], 'Single template reads should return the queued template row.');
	vms_test_assert_same(array(2, 5), array_column($template['slots'], 'template_slot_id'), 'Template slot reads should preserve template_slot_id ordering.');
	$single_prepare = vms_test_find_prepare($wpdb, 'SELECT * FROM %i WHERE template_id = %d');
	vms_test_assert_same(array('wp_vms_staffing_templates', 55), $single_prepare['args'], 'Single template reads should prepare the templates table identifier and template ID.');
	$slot_prepare = vms_test_find_prepare($wpdb, 'SELECT * FROM %i WHERE template_id = %d ORDER BY template_slot_id ASC');
	vms_test_assert_same(array('wp_vms_staffing_template_slots', 55), $slot_prepare['args'], 'Template slot reads should prepare the template-slots table identifier and template ID.');
	vms_test_assert_no_placeholders(vms_test_last_call($wpdb, 'get_results')['query'], 'Template slot reads should execute fully prepared SQL.');

	$wpdb = vms_test_reset_state();
	$wpdb->get_row_queue = array(
		array('template_id' => 17, 'name' => 'Night Shift'),
	);
	$wpdb->get_results_queue = array(
		array(
			array('template_slot_id' => 1, 'role_id' => 5),
		),
	);
	$deleted = vms_staffing_delete_template(17, 91);
	vms_test_assert_true($deleted, 'Template deletion should return true when the template delete reports success.');
	$delete_calls = vms_test_filter_calls($wpdb, 'delete');
	vms_test_assert_same(2, count($delete_calls), 'Template deletion should delete slot rows before deleting the parent template row.');
	vms_test_assert_same('wp_vms_staffing_template_slots', $delete_calls[0]['table'], 'Template deletion should remove template-slot rows first.');
	vms_test_assert_same('wp_vms_staffing_templates', $delete_calls[1]['table'], 'Template deletion should remove the parent template row second.');
	vms_test_assert_same('wp_vms_staffing_audit_log', vms_test_last_call($wpdb, 'insert')['table'], 'Template deletion should append an audit-log insert after the delete cascade.');

	$wpdb = vms_test_reset_state();
	$save_update = vms_staffing_save_template(
		array(
			'template_id' => 12,
			'name' => 'Load In',
			'is_active' => 1,
			'auto_apply_on_event_create' => 1,
			'slots' => array(
				array(
					'role_id' => 5,
					'base_headcount' => 2,
					'activation_threshold' => 3,
					'shift_time_mode' => 'absolute',
					'shift_start_local' => '09:00',
					'shift_end_local' => '11:00',
					'pay_type' => 'inherit_role',
				),
				array(
					'role_id' => 0,
					'base_headcount' => 99,
				),
			),
		),
		81
	);
	vms_test_assert_same(array('ok' => true, 'template_id' => 12, 'slot_count' => 1), $save_update, 'Template updates should preserve the template ID and normalized slot count.');
	$update_calls = vms_test_filter_calls($wpdb, 'update');
	$delete_calls = vms_test_filter_calls($wpdb, 'delete');
	$insert_calls = vms_test_filter_calls($wpdb, 'insert');
	vms_test_assert_same(1, count($update_calls), 'Template updates should mutate the parent template row once.');
	vms_test_assert_same('wp_vms_staffing_templates', $update_calls[0]['table'], 'Template updates should target the templates repository table.');
	vms_test_assert_same(1, count($delete_calls), 'Template updates should clear existing slot rows once.');
	vms_test_assert_same('wp_vms_staffing_template_slots', $delete_calls[0]['table'], 'Template updates should delete existing template-slot rows before reinserting them.');
	vms_test_assert_same('wp_vms_staffing_template_slots', $insert_calls[0]['table'], 'Template updates should reinsert normalized slot rows first.');
	vms_test_assert_same(12, $insert_calls[0]['data']['template_id'], 'Template updates should preserve the parent template ID on replacement slot inserts.');
	vms_test_assert_same('wp_vms_staffing_audit_log', $insert_calls[1]['table'], 'Template updates should end with an audit-log insert.');

	$wpdb = vms_test_reset_state();
	$wpdb->insert_id = 444;
	$save_insert = vms_staffing_save_template(
		array(
			'name' => 'Close Out',
			'is_active' => 1,
			'auto_apply_on_event_create' => 0,
			'slots' => array(
				array(
					'role_id' => 9,
					'base_headcount' => 1,
					'activation_threshold' => 2,
					'shift_time_mode' => 'relative',
					'start_anchor_key' => 'event_start',
					'end_anchor_key' => 'event_end',
					'pay_type' => 'none',
				),
				array(
					'role_id' => 5,
					'base_headcount' => 3,
					'activation_threshold' => 4,
					'shift_time_mode' => 'absolute',
					'shift_start_local' => '18:00',
					'shift_end_local' => '20:00',
					'pay_type' => 'hourly',
					'pay_rate' => '25.50',
				),
			),
		),
		81
	);
	vms_test_assert_same(array('ok' => true, 'template_id' => 444, 'slot_count' => 2), $save_insert, 'Template inserts should return the generated insert ID and slot count.');
	$delete_calls = vms_test_filter_calls($wpdb, 'delete');
	$insert_calls = vms_test_filter_calls($wpdb, 'insert');
	vms_test_assert_same('wp_vms_staffing_templates', $insert_calls[0]['table'], 'Template inserts should write the parent template row first.');
	vms_test_assert_same(444, $delete_calls[0]['where']['template_id'], 'Template inserts should clear replacement slot rows against the generated insert ID.');
	vms_test_assert_same(array(9, 5), array($insert_calls[1]['data']['role_id'], $insert_calls[2]['data']['role_id']), 'Template inserts should preserve the normalized slot insertion order.');
	vms_test_assert_same('wp_vms_staffing_audit_log', $insert_calls[3]['table'], 'Template inserts should end with an audit-log insert.');

	$wpdb = vms_test_reset_state();
	$wpdb->get_row_queue = array(
		array('template_id' => 21, 'name' => 'Concert'),
	);
	$wpdb->get_results_queue = array(
		array(
			array('template_slot_id' => 1, 'role_id' => 5, 'base_headcount' => 2, 'activation_threshold' => 2, 'shift_time_mode' => 'absolute'),
			array('template_slot_id' => 2, 'role_id' => 9, 'base_headcount' => 1, 'activation_threshold' => 4, 'shift_time_mode' => 'absolute'),
		),
		array(
			array('slot_id' => 301, 'role_id' => 5, 'status' => 'active'),
		),
		array(
			array('assignment_id' => 11, 'slot_id' => 301, 'status' => 'confirmed', 'staff_id' => 700),
		),
	);
	$merge_result = vms_staffing_apply_template_to_event(88, 21, 'merge_missing', 91);
	vms_test_assert_same(array('ok' => true, 'template_id' => 21, 'seeded' => 1, 'skipped' => 1, 'mode' => 'merge_missing'), $merge_result, 'Merge-missing template application should skip already-active roles and seed only missing ones.');
	$insert_calls = vms_test_filter_calls($wpdb, 'insert');
	vms_test_assert_same('wp_vms_event_role_slots', $insert_calls[0]['table'], 'Merge-missing template application should insert event-slot rows.');
	vms_test_assert_same(9, $insert_calls[0]['data']['role_id'], 'Merge-missing template application should seed only the missing role.');
	vms_test_assert_same(
		array(
			array(
				'event_plan_id' => 88,
				'thresholds' => array(9 => 4),
			),
		),
		$GLOBALS['vms_test_threshold_sets'],
		'Merge-missing template application should preserve threshold updates for seeded roles only.'
	);
	vms_test_assert_same(
		array(
			array('event_plan_id' => 88, 'template_id' => 21, 'source' => 'manual_merge'),
		),
		$GLOBALS['vms_test_applied_templates'],
		'Merge-missing template application should record the applied template source.'
	);

	$wpdb = vms_test_reset_state();
	$wpdb->get_row_queue = array(
		array('template_id' => 33, 'name' => 'Festival'),
	);
	$wpdb->get_results_queue = array(
		array(
			array('template_slot_id' => 7, 'role_id' => 5, 'base_headcount' => 2, 'activation_threshold' => 3, 'shift_time_mode' => 'absolute'),
			array('template_slot_id' => 8, 'role_id' => 9, 'base_headcount' => 1, 'activation_threshold' => 5, 'shift_time_mode' => 'relative'),
		),
		array(
			array('slot_id' => 401, 'role_id' => 5, 'status' => 'active'),
			array('slot_id' => 402, 'role_id' => 9, 'status' => 'canceled'),
		),
		array(
			array('assignment_id' => 21, 'slot_id' => 401, 'status' => 'proposed', 'staff_id' => 1001),
			array('assignment_id' => 22, 'slot_id' => 402, 'status' => 'confirmed', 'staff_id' => 1002),
		),
	);
	$replace_result = vms_staffing_apply_template_to_event(90, 33, 'replace_all', 91);
	vms_test_assert_same(array('ok' => true, 'template_id' => 33, 'seeded' => 2, 'skipped' => 0, 'mode' => 'replace_all'), $replace_result, 'Replace-all template application should cancel existing assignments, clear slots, and reseed the template roles.');
	$query_calls = vms_test_filter_calls($wpdb, 'query');
	$delete_calls = vms_test_filter_calls($wpdb, 'delete');
	$insert_calls = vms_test_filter_calls($wpdb, 'insert');
	vms_test_assert_same(2, count($query_calls), 'Replace-all template application should cancel assignments for each existing slot before deleting slot rows.');
	vms_test_assert_same('wp_vms_event_role_slots', $delete_calls[0]['table'], 'Replace-all template application should delete existing event-slot rows after canceling assignments.');
	vms_test_assert_same(array(5, 9), array($insert_calls[0]['data']['role_id'], $insert_calls[1]['data']['role_id']), 'Replace-all template application should preserve template slot order when reseeding event slots.');
	$cancel_prepare = vms_test_find_prepare($wpdb, "UPDATE %i SET status = 'canceled', updated_at = %s, updated_by = %d WHERE slot_id = %d AND status IN ('proposed','confirmed')");
	vms_test_assert_same('wp_vms_event_role_assignments', $cancel_prepare['args'][0], 'Replace-all template application should prepare the assignments table identifier for cancellation updates.');
	vms_test_assert_same(
		array(
			array('event_plan_id' => 90, 'template_id' => 33, 'source' => 'manual_replace'),
		),
		$GLOBALS['vms_test_applied_templates'],
		'Replace-all template application should record the replace-all applied-template source.'
	);

	$wpdb = vms_test_reset_state();
	$GLOBALS['vms_test_slot_windows'][515] = array(
		'start_local' => null,
		'end_local' => null,
		'start_ts' => 1700000100,
		'end_ts' => 1700003700,
		'duration_minutes' => 60,
	);
	$wpdb->get_row_queue = array(
		array('slot_id' => 515, 'event_plan_id' => 88),
	);
	vms_staffing_sync_assignment_shift_timestamps_for_slot(515);
	$slot_prepare = vms_test_find_prepare($wpdb, 'SELECT * FROM %i WHERE slot_id = %d');
	vms_test_assert_same(array('wp_vms_event_role_slots', 515), $slot_prepare['args'], 'Slot timestamp sync should prepare the event-slot table identifier and slot ID.');
	$sync_prepare = vms_test_find_prepare($wpdb, "UPDATE %i SET shift_start_ts = %s, shift_end_ts = %s, updated_at = %s, updated_by = %d WHERE slot_id = %d AND status IN ('proposed','confirmed')");
	vms_test_assert_same('wp_vms_event_role_assignments', $sync_prepare['args'][0], 'Slot timestamp sync should prepare the assignments table identifier.');
	vms_test_assert_no_placeholders(vms_test_last_call($wpdb, 'query')['query'], 'Slot timestamp sync should execute fully prepared SQL.');

	$wpdb = vms_test_reset_state();
	$wpdb->get_results_queue = array(
		array(
			array('slot_id' => 701, 'role_id' => 5, 'status' => 'active'),
			array('slot_id' => 702, 'role_id' => 9, 'status' => 'active'),
		),
		array(
			array('assignment_id' => 31, 'slot_id' => 702, 'status' => 'confirmed', 'staff_id' => 2002),
			array('assignment_id' => 32, 'slot_id' => 701, 'status' => 'proposed', 'staff_id' => 2001),
			array('assignment_id' => 33, 'slot_id' => 702, 'status' => 'proposed', 'staff_id' => 2003),
		),
	);
	$event_slots = bvmgr_staffing_get_event_slots(55, false);
	vms_test_assert_same(array(701, 702), array_column($event_slots, 'slot_id'), 'Event-slot reads should preserve slot ordering.');
	vms_test_assert_same(array(32), array_column($event_slots[0]['assignments'], 'assignment_id'), 'Event-slot enrichment should group assignments by slot ID.');
	vms_test_assert_same(array(31, 33), array_column($event_slots[1]['assignments'], 'assignment_id'), 'Event-slot enrichment should preserve assignment ordering within each slot.');
	vms_test_assert_same('Ops', $event_slots[0]['role_name'], 'Event-slot enrichment should attach role names from the role map.');
	$slot_list_prepare = vms_test_find_prepare($wpdb, 'SELECT * FROM %i WHERE event_plan_id = %d AND (%d = 1 OR status = %s) ORDER BY slot_id ASC');
	vms_test_assert_same(array('wp_vms_event_role_slots', 55, 0, 'active'), $slot_list_prepare['args'], 'Event-slot reads should bind the active-only gate explicitly when canceled rows are excluded.');
	$assignment_prepare = vms_test_find_prepare($wpdb, 'SELECT * FROM %i WHERE slot_id IN (%d, %d) ORDER BY assignment_id ASC');
	vms_test_assert_same(array('wp_vms_event_role_assignments', 701, 702), $assignment_prepare['args'], 'Event-slot enrichment should prepare a bounded IN-list for assignment reads.');
	vms_test_assert_no_placeholders(vms_test_last_call($wpdb, 'get_results')['query'], 'Event-slot assignment reads should execute fully prepared SQL.');

	$wpdb = vms_test_reset_state();
	$wpdb->get_results_queue = array(
		array(
			array('slot_id' => 801, 'role_id' => 5, 'status' => 'active'),
			array('slot_id' => 802, 'role_id' => 9, 'status' => 'canceled'),
		),
		array(
			array('assignment_id' => 41, 'slot_id' => 801, 'status' => 'confirmed', 'staff_id' => 3001),
			array('assignment_id' => 42, 'slot_id' => 802, 'status' => 'proposed', 'staff_id' => 3002),
		),
	);
	$event_slots = bvmgr_staffing_get_event_slots(56, true);
	vms_test_assert_same(2, count($event_slots), 'Event-slot reads should allow canceled rows through when requested.');
	$slot_list_prepare = vms_test_find_prepare($wpdb, 'SELECT * FROM %i WHERE event_plan_id = %d AND (%d = 1 OR status = %s) ORDER BY slot_id ASC');
	vms_test_assert_same(array('wp_vms_event_role_slots', 56, 1, 'active'), $slot_list_prepare['args'], 'Event-slot reads should disable the active-only gate explicitly when canceled rows are included.');

	$wpdb = vms_test_reset_state();
	$wpdb->get_var_queue = array(2);
	$seed_gate_result = vms_staffing_seed_event_slots_from_template(44, false, 91);
	vms_test_assert_same(
		array('ok' => true, 'seeded' => 0, 'template_id' => 0, 'skipped' => 'slots_exist'),
		$seed_gate_result,
		'Template seeding should short-circuit when active event slots already exist.'
	);
	$seed_gate_prepare = vms_test_find_prepare($wpdb, "SELECT COUNT(*) FROM %i WHERE event_plan_id = %d AND status = 'active'");
	vms_test_assert_same(array('wp_vms_event_role_slots', 44), $seed_gate_prepare['args'], 'Template seeding should prepare the event-slot table identifier and event ID for the count gate.');
	vms_test_assert_same(array(), vms_test_filter_calls($wpdb, 'insert'), 'Template seeding should not insert new rows when the count gate short-circuits.');

	$wpdb = vms_test_reset_state();
	$wpdb->get_var_queue = array(0);
	$wpdb->get_results_queue = array(
		array(
			array('template_slot_id' => 51, 'role_id' => 9, 'base_headcount' => 2, 'shift_time_mode' => 'relative'),
			array('template_slot_id' => 52, 'role_id' => 5, 'base_headcount' => 1, 'shift_time_mode' => 'absolute'),
		),
	);
	$GLOBALS['vms_test_post_meta'][44] = array(
		'_vms_venue_id' => 7,
		'_vms_event_date' => '2026-09-10',
		'_vms_event_type' => 'festival',
	);
	$GLOBALS['vms_test_pick_template_result'] = array('template_id' => 77);
	$seed_result = vms_staffing_seed_event_slots_from_template(44, false, 91);
	vms_test_assert_same(array('ok' => true, 'seeded' => 2, 'template_id' => 77), $seed_result, 'Template seeding should insert each template slot in order when the gate is clear.');
	$insert_calls = vms_test_filter_calls($wpdb, 'insert');
	vms_test_assert_same(array(9, 5), array($insert_calls[0]['data']['role_id'], $insert_calls[1]['data']['role_id']), 'Template seeding should preserve template-slot order during event-slot inserts.');
	vms_test_assert_same(
		array(
			array('event_plan_id' => 44, 'template_id' => 77, 'source' => 'auto'),
		),
		$GLOBALS['vms_test_applied_templates'],
		'Template seeding should record the automatically applied template.'
	);
	vms_test_assert_same(
		array(
			array('event_plan_id' => 44, 'reason' => 'seed_from_template'),
		),
		$GLOBALS['vms_test_rollup_dirty'],
		'Template seeding should mark the event rollup dirty with the seed-from-template reason.'
	);
	vms_test_assert_same(array(44), $GLOBALS['vms_test_rollup_computes'], 'Template seeding should trigger one rollup recomputation.');

	echo "OK\n";
} catch (Throwable $throwable) {
	fwrite(STDERR, $throwable->getMessage() . "\n");
	exit(1);
}
