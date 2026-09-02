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

				if ($value === null) {
					return 'NULL';
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

function esc_html__($text, string $domain = ''): string
{
	return __( (string) $text, $domain );
}

function esc_html($text): string
{
	return (string) $text;
}

function esc_attr($text): string
{
	return (string) $text;
}

function selected($selected, $current, bool $display = false): string
{
	unset($display);
	return $selected === $current ? 'selected="selected"' : '';
}

function checked($checked, $current = true, bool $display = false): string
{
	unset($display);
	return $checked === $current ? 'checked="checked"' : '';
}

function wp_die($message = ''): void
{
	throw new RuntimeException((string) $message);
}

function current_user_can(string $capability): bool
{
	unset($capability);
	return !empty($GLOBALS['vms_test_current_user_can']);
}

function wp_nonce_field(string $action = '', string $name = '_wpnonce', bool $referer = true, bool $display = true): void
{
	unset($action, $name, $referer, $display);
}

function check_admin_referer(string $action = '', string $name = '_wpnonce')
{
	unset($action, $name);
	return true;
}

/** @return mixed */
function wp_unslash($value)
{
	if (is_array($value)) {
		return array_map('wp_unslash', $value);
	}

	return is_string($value) ? stripslashes($value) : $value;
}

function sanitize_text_field($value): string
{
	return trim(strip_tags((string) $value));
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

function metadata_exists(string $object_type, int $object_id, string $meta_key): bool
{
	unset($object_type);
	if (isset($GLOBALS['vms_test_metadata_exists'][$object_id][$meta_key])) {
		return (bool) $GLOBALS['vms_test_metadata_exists'][$object_id][$meta_key];
	}

	return array_key_exists($meta_key, $GLOBALS['vms_test_post_meta'][$object_id] ?? array());
}

function update_post_meta(int $post_id, string $key, $value)
{
	$GLOBALS['vms_test_updated_meta'][] = array(
		'post_id' => $post_id,
		'key' => $key,
		'value' => $value,
	);
	$GLOBALS['vms_test_post_meta'][$post_id][$key] = $value;
	return true;
}

function delete_post_meta(int $post_id, string $key)
{
	$GLOBALS['vms_test_deleted_meta'][] = array(
		'post_id' => $post_id,
		'key' => $key,
	);
	unset($GLOBALS['vms_test_post_meta'][$post_id][$key]);
	return true;
}

function get_the_title(int $post_id): string
{
	return $GLOBALS['vms_test_titles'][$post_id] ?? ('Post ' . $post_id);
}

function do_action(string $hook, ...$args): void
{
	$GLOBALS['vms_test_actions'][] = array(
		'hook' => $hook,
		'args' => $args,
	);
}

function bvmgr_staffing_admin_request_method(): string
{
	return $GLOBALS['vms_test_admin_request_method'] ?? 'GET';
}

function bvmgr_staffing_admin_get_venues(): array
{
	return $GLOBALS['vms_test_admin_venues'] ?? array();
}

function bvmgr_staffing_rebuild_rollups(array $filters, bool $preview): array
{
	$GLOBALS['vms_test_rebuild_rollups_calls'][] = array(
		'filters' => $filters,
		'preview' => $preview,
	);

	return $GLOBALS['vms_test_rebuild_rollups_result'] ?? array(
		'preview' => $preview ? 1 : 0,
		'matched_count' => 0,
		'rebuilt_count' => 0,
		'error_count' => 0,
		'errors' => array(),
	);
}

function bvmgr_staffing_get_event_plan_ticket_sales_snapshot(int $event_plan_id): array
{
	return $GLOBALS['vms_test_ticket_snapshots'][$event_plan_id] ?? array();
}

function bvmgr_admission_table_entries(): string
{
	return $GLOBALS['vms_test_admissions_table'] ?? '';
}

function bvmgr_staffing_table_name(string $name): string
{
	return $GLOBALS['vms_test_table_names'][$name] ?? '';
}

function bvmgr_staffing_role_map_by_id(bool $active_only = true): array
{
	unset($active_only);
	return $GLOBALS['vms_test_role_map'];
}

function bvmgr_staffing_now_mysql_utc(): string
{
	return '2026-08-02 12:00:00';
}

function bvmgr_staffing_get_event_slots(int $event_plan_id, bool $include_canceled = false): array
{
	unset($event_plan_id, $include_canceled);
	return $GLOBALS['vms_test_event_slots_after_save'] ?? array();
}

function bvmgr_staffing_sync_assignment_shift_timestamps_for_slot(int $slot_id): void
{
	$GLOBALS['vms_test_sync_calls'][] = $slot_id;
}

function bvmgr_staffing_build_legacy_staff_assignments_from_slots(int $event_plan_id): array
{
	unset($event_plan_id);
	return $GLOBALS['vms_test_legacy_assignments'] ?? array();
}

function bvmgr_staffing_audit_log(string $action, int $object_id, array $before, array $after, ?int $actor_user_id = null): void
{
	$GLOBALS['vms_test_audit_log_calls'][] = array(
		'action' => $action,
		'object_id' => $object_id,
		'before' => $before,
		'after' => $after,
		'actor_user_id' => $actor_user_id,
	);
}

function bvmgr_staffing_event_plan_datetime(int $event_plan_id): array
{
	return $GLOBALS['vms_test_event_datetimes'][$event_plan_id] ?? array();
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

function bvmgr_event_plan_get_status(int $event_plan_id, string $context = ''): string
{
	unset($context);
	return $GLOBALS['vms_test_event_status'][$event_plan_id] ?? 'draft';
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
 * @param array<int,string>               $actual_inventory
 * @param array<string,array<int,string>> $expected_groups
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
	foreach ($group_names as $group_name) {
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

	$classify_message = 'Every DirectQuery/NoCaching suppression must be classified as ' . vms_test_format_group_list($group_names) . '.';
	vms_test_assert_same(array(), $unknown_inventory, $classify_message);
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

/**
 * @return array<int,array<string,mixed>>
 */
function vms_test_filter_calls_by_table(VMS_Test_WPDB $wpdb, string $kind, string $table): array
{
	return array_values(
		array_filter(
			vms_test_filter_calls($wpdb, $kind),
			static function (array $call) use ($table): bool {
				return ($call['table'] ?? '') === $table;
			}
		)
	);
}

/**
 * @return array<string,mixed>
 */
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
	$GLOBALS['vms_test_current_user_can'] = true;
	$GLOBALS['vms_test_admin_request_method'] = 'GET';
	$GLOBALS['vms_test_admin_venues'] = array();
	$GLOBALS['vms_test_rebuild_rollups_calls'] = array();
	$GLOBALS['vms_test_rebuild_rollups_result'] = array(
		'preview' => 1,
		'matched_count' => 0,
		'rebuilt_count' => 0,
		'error_count' => 0,
		'errors' => array(),
	);
	$GLOBALS['vms_test_table_names'] = array(
		'rollups' => 'wp_vms_staffing_rollups',
		'event_slots' => 'wp_vms_event_role_slots',
		'assignments' => 'wp_vms_event_role_assignments',
	);
	$GLOBALS['vms_test_ticket_snapshots'] = array();
	$GLOBALS['vms_test_admissions_table'] = '';
	$GLOBALS['vms_test_post_meta'] = array();
	$GLOBALS['vms_test_metadata_exists'] = array();
	$GLOBALS['vms_test_titles'] = array();
	$GLOBALS['vms_test_role_map'] = array(
		5 => array('name' => 'Ops', 'default_headcount' => 2, 'default_pay_type' => 'hourly', 'default_rate' => 20.0, 'default_notes' => 'Ops note'),
		6 => array('name' => 'Usher', 'default_headcount' => 1, 'default_pay_type' => 'flat', 'default_rate' => 150.0, 'default_notes' => 'Usher note'),
		9 => array('name' => 'Security', 'default_headcount' => 1, 'default_pay_type' => 'none', 'default_rate' => null, 'default_notes' => ''),
	);
	$GLOBALS['vms_test_event_slots_after_save'] = array();
	$GLOBALS['vms_test_sync_calls'] = array();
	$GLOBALS['vms_test_legacy_assignments'] = array();
	$GLOBALS['vms_test_updated_meta'] = array();
	$GLOBALS['vms_test_deleted_meta'] = array();
	$GLOBALS['vms_test_audit_log_calls'] = array();
	$GLOBALS['vms_test_event_datetimes'] = array();
	$GLOBALS['vms_test_slot_windows'] = array();
	$GLOBALS['vms_test_event_status'] = array();
	$GLOBALS['vms_test_actions'] = array();

	return $wpdb;
}

function vms_test_run_admin_rollups_query_assertions(): void
{
	$wpdb = vms_test_reset_state();
	$wpdb->get_var_queue = array(3);

	ob_start();
	bvmgr_staffing_admin_render_rollups_page();
	$output = (string) ob_get_clean();

	vms_test_assert_contains('Dirty rollups:', $output, 'Rollups admin page should render the dirty-rollup status summary.');
	$prepare = vms_test_find_prepare($wpdb, 'SELECT COUNT(*) FROM %i WHERE dirty = %d');
	vms_test_assert_same(array('wp_vms_staffing_rollups', 1), $prepare['args'], 'Rollups admin page should prepare the rollups table identifier and dirty flag.');
	vms_test_assert_no_placeholders(vms_test_last_call($wpdb, 'get_var')['query'], 'Rollups admin dirty-count reads should execute fully prepared SQL.');
}

function vms_test_run_headcount_context_assertions(): void
{
	$wpdb = vms_test_reset_state();
	$GLOBALS['vms_test_ticket_snapshots'][44] = array(
		'qty' => 5,
		'resolved' => true,
	);
	$GLOBALS['vms_test_admissions_table'] = 'wp_vms_admissions_entries_44';
	$wpdb->get_var_queue = array(
		'wp_vms_admissions_entries_44',
		7,
	);

	$context = bvmgr_staffing_get_event_plan_headcount_context(44);
	vms_test_assert_same(
		array(
			'wired' => true,
			'headcount' => 12,
			'source' => 'anticipated_guests',
			'label' => 'Anticipated guests',
		),
		$context,
		'Headcount context should combine ticketing and admissions counts when the admissions table exists.'
	);

	$show_prepare = vms_test_find_prepare($wpdb, 'SHOW TABLES LIKE %s');
	vms_test_assert_same(array('wp_vms_admissions_entries_44'), $show_prepare['args'], 'Admissions table probes should prepare the table-existence lookup with a string placeholder.');
	$sum_prepare = vms_test_find_prepare($wpdb, "SELECT COALESCE(SUM(CASE WHEN status <> 'canceled' THEN party_size ELSE 0 END), 0) FROM %i WHERE event_plan_id = %d");
	vms_test_assert_same(array('wp_vms_admissions_entries_44', 44), $sum_prepare['args'], 'Admissions headcount reads should prepare the table identifier and event-plan ID.');
	foreach (vms_test_filter_calls($wpdb, 'get_var') as $call) {
		vms_test_assert_no_placeholders($call['query'], 'Admissions headcount queries should execute fully prepared SQL.');
	}
}

function vms_test_run_mark_rollup_dirty_assertions(): void
{
	$wpdb = vms_test_reset_state();
	$GLOBALS['vms_test_post_meta'][77]['_vms_venue_id'] = 14;
	$GLOBALS['vms_test_event_status'][77] = 'confirmed';
	$GLOBALS['vms_test_event_datetimes'][77] = array(
		'start_local' => new DateTimeImmutable('2026-08-20 17:30:00'),
	);

	bvmgr_staffing_mark_rollup_dirty(77, 'event_staffing_saved');

	$prepare = vms_test_find_prepare($wpdb, 'INSERT INTO %i (event_plan_id, venue_id, event_status, event_start_local, dirty, dirty_reason, computed_at, calc_version)');
	vms_test_assert_same(
		array('wp_vms_staffing_rollups', 77, 14, 'confirmed', '2026-08-20 17:30:00', 'event_staffing_saved', '2026-08-02 12:00:00', 'staffing_v1'),
		$prepare['args'],
		'Dirty-rollup writes should prepare the rollups repository identifier and event metadata explicitly.'
	);
	vms_test_assert_no_placeholders(vms_test_last_call($wpdb, 'query')['query'], 'Dirty-rollup writes should execute fully prepared SQL.');
}

function vms_test_run_save_event_roles_matrix_assertions(): void
{
	$wpdb = vms_test_reset_state();
	$wpdb->insert_id = 1501;
	$GLOBALS['vms_test_post_meta'][88]['_vms_venue_id'] = 12;
	$GLOBALS['vms_test_post_meta'][88]['_vms_event_date'] = '2026-08-20';
	$GLOBALS['vms_test_event_status'][88] = 'confirmed';
	$GLOBALS['vms_test_event_datetimes'][88] = array(
		'start_local' => new DateTimeImmutable('2026-08-20 18:00:00'),
	);
	$GLOBALS['vms_test_event_slots_after_save'] = array(
		array('slot_id' => 700, 'role_id' => 5),
		array('slot_id' => 1501, 'role_id' => 6),
	);
	$GLOBALS['vms_test_legacy_assignments'] = array(
		'ops' => array(101, 102, 103),
	);
	$wpdb->get_results_queue = array(
		array(
			array('slot_id' => 700, 'role_id' => 5, 'status' => 'active'),
			array('slot_id' => 701, 'role_id' => 9, 'status' => 'active'),
		),
		array(
			array('assignment_id' => 901, 'staff_id' => 101, 'status' => 'canceled'),
			array('assignment_id' => 902, 'staff_id' => 777, 'status' => 'confirmed'),
		),
		array(),
		array(),
	);

	$result = bvmgr_staffing_save_event_roles_matrix(
		88,
		array(5 => 2, 6 => 1, 9 => 0),
		array(5 => array(101, 102), 6 => array(103), 9 => array()),
		array(5 => 'absolute', 6 => 'absolute', 9 => 'absolute'),
		array(5 => '09:00', 6 => '10:00'),
		array(5 => '11:00', 6 => '12:00'),
		array(),
		array(),
		array(),
		array(),
		array(),
		77,
		array(
			'before_slots' => array(
				array('slot_id' => 700, 'role_id' => 5),
				array('slot_id' => 701, 'role_id' => 9),
			),
			'desired_signature' => array(
				5 => array('staff_ids' => array(101, 102)),
				6 => array('staff_ids' => array(103)),
			),
			'current_signature' => array(
				5 => array('staff_ids' => array(101)),
				9 => array('staff_ids' => array(777)),
			),
		)
	);

	vms_test_assert_true(!empty($result['ok']), 'Matrix saves should report success when the repository tables are available.');
	vms_test_assert_same(2, $result['slot_count'], 'Matrix saves should count only the active managed role rows.');
	vms_test_assert_same(3, $result['assignment_count'], 'Matrix saves should count each desired staff assignment.');
	vms_test_assert_same(array(700, 1501), $GLOBALS['vms_test_sync_calls'], 'Matrix saves should resync assignment timestamps for each active managed slot.');

	$existing_prepare = vms_test_find_prepare($wpdb, 'SELECT * FROM %i WHERE event_plan_id = %d ORDER BY slot_id ASC');
	vms_test_assert_same(array('wp_vms_event_role_slots', 88), $existing_prepare['args'], 'Matrix saves should prepare the event-slot repository identifier and event-plan ID before reconciling rows.');
	$cancel_prepare = vms_test_find_prepare($wpdb, "UPDATE %i SET status = 'canceled', updated_at = %s, updated_by = %d WHERE slot_id = %d AND status IN ('proposed','confirmed')");
	vms_test_assert_same(array('wp_vms_event_role_assignments', '2026-08-02 12:00:00', 77, 701), $cancel_prepare['args'], 'Matrix saves should prepare assignment-cancel updates with the repository identifier, timestamp, actor, and slot ID.');
	$dirty_prepare = vms_test_find_prepare($wpdb, 'INSERT INTO %i (event_plan_id, venue_id, event_status, event_start_local, dirty, dirty_reason, computed_at, calc_version)');
	vms_test_assert_same(
		array('wp_vms_staffing_rollups', 88, 12, 'confirmed', '2026-08-20 18:00:00', 'event_staffing_saved', '2026-08-02 12:00:00', 'staffing_v1'),
		$dirty_prepare['args'],
		'Matrix saves should mark the event rollup dirty through the prepared rollups repository write.'
	);
	$assignment_prepare = vms_test_find_prepare($wpdb, 'SELECT assignment_id, staff_id, status FROM %i WHERE slot_id = %d ORDER BY assignment_id ASC');
	vms_test_assert_same(array('wp_vms_event_role_assignments', 700), $assignment_prepare['args'], 'Matrix saves should prepare assignment reads with the assignment repository identifier and active slot ID.');

	$slot_updates = vms_test_filter_calls_by_table($wpdb, 'update', 'wp_vms_event_role_slots');
	vms_test_assert_same(2, count($slot_updates), 'Matrix saves should update the existing managed slot row and cancel the obsolete slot row.');
	vms_test_assert_same(array('slot_id' => 700), $slot_updates[0]['where'], 'Matrix saves should target the existing managed slot row first.');
	vms_test_assert_same('active', $slot_updates[0]['data']['status'], 'Matrix saves should reactivate the managed slot row during updates.');
	vms_test_assert_same(array('slot_id' => 701), $slot_updates[1]['where'], 'Matrix saves should target the obsolete slot row for cancellation.');
	vms_test_assert_same('canceled', $slot_updates[1]['data']['status'], 'Matrix saves should cancel obsolete slot rows.');

	$assignment_updates = vms_test_filter_calls_by_table($wpdb, 'update', 'wp_vms_event_role_assignments');
	vms_test_assert_same(2, count($assignment_updates), 'Matrix saves should revive retained assignments and cancel omitted assignments.');
	vms_test_assert_same(array('assignment_id' => 901), $assignment_updates[0]['where'], 'Matrix saves should revive the retained assignment first.');
	vms_test_assert_same('proposed', $assignment_updates[0]['data']['status'], 'Matrix saves should revive retained assignments as proposed.');
	vms_test_assert_same(array('assignment_id' => 902), $assignment_updates[1]['where'], 'Matrix saves should cancel the omitted assignment row.');
	vms_test_assert_same('canceled', $assignment_updates[1]['data']['status'], 'Matrix saves should cancel omitted assignments.');

	$slot_inserts = vms_test_filter_calls_by_table($wpdb, 'insert', 'wp_vms_event_role_slots');
	vms_test_assert_same(1, count($slot_inserts), 'Matrix saves should insert one slot row for each new managed role.');
	vms_test_assert_same(6, $slot_inserts[0]['data']['role_id'], 'Matrix saves should insert the new managed role into the slot repository.');
	vms_test_assert_same(1, $slot_inserts[0]['data']['headcount_needed'], 'Matrix saves should persist the requested headcount on inserted slot rows.');

	$assignment_inserts = vms_test_filter_calls_by_table($wpdb, 'insert', 'wp_vms_event_role_assignments');
	vms_test_assert_same(2, count($assignment_inserts), 'Matrix saves should insert one assignment row for each newly assigned staff member.');
	vms_test_assert_same(array(102, 103), array($assignment_inserts[0]['data']['staff_id'], $assignment_inserts[1]['data']['staff_id']), 'Matrix saves should preserve the normalized assignment insertion order.');

	foreach (vms_test_filter_calls($wpdb, 'query') as $call) {
		vms_test_assert_no_placeholders($call['query'], 'Matrix save query execution should not retain unresolved placeholders.');
	}

	vms_test_assert_same(1, count($GLOBALS['vms_test_updated_meta']), 'Matrix saves should refresh the legacy staff-assignment meta once.');
	vms_test_assert_same('_vms_staff_assignments', $GLOBALS['vms_test_updated_meta'][0]['key'], 'Matrix saves should refresh the legacy staff-assignment meta key.');
	vms_test_assert_same(1, count($GLOBALS['vms_test_audit_log_calls']), 'Matrix saves should append one staffing audit-log entry.');
	vms_test_assert_same('event_staffing_save', $GLOBALS['vms_test_audit_log_calls'][0]['action'], 'Matrix saves should record the staffing-save audit action.');
	vms_test_assert_same('vms_staffing_event_saved', $GLOBALS['vms_test_actions'][0]['hook'] ?? '', 'Matrix saves should fire the staffing saved action hook.');
}

function vms_test_run_compute_rollup_assertions(): void
{
	$wpdb = vms_test_reset_state();
	$GLOBALS['vms_test_role_map'] = array(
		5 => array('name' => 'Ops', 'is_critical' => 1, 'default_pay_type' => 'none', 'default_rate' => null),
		9 => array('name' => 'Usher', 'is_critical' => 0, 'default_pay_type' => 'none', 'default_rate' => null),
	);
	$GLOBALS['vms_test_post_meta'][55]['_vms_event_date'] = '2026-08-20';
	$GLOBALS['vms_test_post_meta'][55]['_vms_venue_id'] = 21;
	$GLOBALS['vms_test_event_status'][55] = 'confirmed';
	$GLOBALS['vms_test_event_datetimes'][55] = array(
		'start_local' => new DateTimeImmutable('2026-08-20 18:30:00'),
	);
	$GLOBALS['vms_test_titles'][201] = 'Alex';
	$GLOBALS['vms_test_slot_windows'][701] = array(
		'start_local' => new DateTimeImmutable('2026-08-20 18:00:00'),
		'end_local' => new DateTimeImmutable('2026-08-20 19:00:00'),
		'duration_minutes' => 60,
	);
	$GLOBALS['vms_test_slot_windows'][702] = array(
		'start_local' => new DateTimeImmutable('2026-08-20 19:00:00'),
		'end_local' => new DateTimeImmutable('2026-08-20 20:00:00'),
		'duration_minutes' => 60,
	);
	$wpdb->get_results_queue = array(
		array(
			array('slot_id' => 701, 'role_id' => 5, 'headcount_needed' => 2, 'pay_type' => 'none', 'status' => 'active'),
			array('slot_id' => 702, 'role_id' => 9, 'headcount_needed' => 1, 'pay_type' => 'none', 'status' => 'active'),
		),
		array(
			array('assignment_id' => 31, 'slot_id' => 701, 'staff_id' => 201, 'status' => 'confirmed', 'shift_start_ts' => 100, 'shift_end_ts' => 200),
			array('assignment_id' => 32, 'slot_id' => 701, 'staff_id' => 202, 'status' => 'proposed', 'shift_start_ts' => null, 'shift_end_ts' => null),
			array('assignment_id' => 33, 'slot_id' => 702, 'staff_id' => 203, 'status' => 'proposed', 'shift_start_ts' => null, 'shift_end_ts' => null),
		),
	);
	$wpdb->get_var_queue = array(2);

	$result = bvmgr_staffing_compute_rollup(55);
	vms_test_assert_true(!empty($result['ok']), 'Rollup recompute should report success for a valid event plan.');
	vms_test_assert_same(2, $result['slots_total'], 'Rollup recompute should count each active slot.');
	vms_test_assert_same(3, $result['headcount_needed_total'], 'Rollup recompute should total required headcount across active slots.');
	vms_test_assert_same(3, $result['headcount_filled_total'], 'Rollup recompute should total proposed and confirmed assignments across active slots.');
	vms_test_assert_same(0, $result['open_headcount_total'], 'Rollup recompute should report no open headcount when every role is filled.');
	vms_test_assert_same(1, $result['conflict_count'], 'Rollup recompute should count confirmed overlap conflicts from the bounded overlap query.');
	vms_test_assert_same('red_flag', $result['readiness_status'], 'Rollup recompute should flag readiness red when confirmed overlaps exist.');

	$slot_prepare = vms_test_find_prepare($wpdb, "SELECT * FROM %i WHERE event_plan_id = %d AND status = 'active' ORDER BY slot_id ASC");
	vms_test_assert_same(array('wp_vms_event_role_slots', 55), $slot_prepare['args'], 'Rollup recompute should prepare the slot repository identifier and event-plan ID.');
	$assignment_prepare = vms_test_find_prepare($wpdb, 'SELECT * FROM %i WHERE slot_id IN (%d, %d) ORDER BY assignment_id ASC');
	vms_test_assert_same(array('wp_vms_event_role_assignments', 701, 702), $assignment_prepare['args'], 'Rollup recompute should prepare a bounded IN-list for assignment reads.');
	$overlap_prepare = vms_test_find_prepare($wpdb, 'SELECT COUNT(*) FROM %i a INNER JOIN %i s ON s.slot_id = a.slot_id');
	vms_test_assert_same(array('wp_vms_event_role_assignments', 'wp_vms_event_role_slots', 201, 31, 55, 200, 100), $overlap_prepare['args'], 'Rollup overlap probes should prepare repository identifiers, assignment filters, and overlap bounds.');
	$upsert_prepare = vms_test_find_prepare($wpdb, 'INSERT INTO %i (event_plan_id, venue_id, event_status, event_start_local, slots_total, headcount_needed_total');
	vms_test_assert_same('wp_vms_staffing_rollups', $upsert_prepare['args'][0], 'Rollup recompute should prepare the rollups repository identifier for the upsert.');
	vms_test_assert_same(55, $upsert_prepare['args'][1], 'Rollup recompute should bind the event-plan ID in the rollup upsert.');

	foreach (vms_test_filter_calls($wpdb, 'get_results') as $call) {
		vms_test_assert_no_placeholders($call['query'], 'Rollup recompute reads should execute fully prepared SQL.');
	}
	foreach (vms_test_filter_calls($wpdb, 'get_var') as $call) {
		vms_test_assert_no_placeholders($call['query'], 'Rollup overlap probes should execute fully prepared SQL.');
	}
	foreach (vms_test_filter_calls($wpdb, 'query') as $call) {
		vms_test_assert_no_placeholders($call['query'], 'Rollup upserts should execute fully prepared SQL.');
	}
}

function vms_test_run_get_rollup_assertions(): void
{
	$wpdb = vms_test_reset_state();
	$wpdb->get_row_queue = array(
		array(
			'event_plan_id' => 55,
			'readiness_status' => 'ready',
		),
	);

	$row = bvmgr_staffing_get_rollup(55);
	vms_test_assert_same(array('event_plan_id' => 55, 'readiness_status' => 'ready'), $row, 'Rollup reads should return the queued repository row unchanged.');

	$prepare = vms_test_find_prepare($wpdb, 'SELECT * FROM %i WHERE event_plan_id = %d');
	vms_test_assert_same(array('wp_vms_staffing_rollups', 55), $prepare['args'], 'Rollup reads should prepare the rollups repository identifier and event-plan ID.');
	vms_test_assert_no_placeholders(vms_test_last_call($wpdb, 'get_row')['query'], 'Rollup reads should execute fully prepared SQL.');
}

$plugin_root = dirname(__DIR__);
$live_plugin_root = dirname($plugin_root, 2) . '/vms';
$store_path = $plugin_root . '/includes/modules/staff-tasks/store.php';
$db_path = $plugin_root . '/includes/modules/staff-tasks/db.php';
$admin_ui_path = $plugin_root . '/includes/modules/staff-tasks/admin-ui.php';
$staff_portal_path = $plugin_root . '/includes/portal/staff-portal.php';
$core_staffing_path = $plugin_root . '/includes/core/staffing.php';
$admin_staffing_path = $plugin_root . '/includes/admin/staffing.php';
$live_core_staffing_path = $live_plugin_root . '/includes/core/staffing.php';
$live_admin_staffing_path = $live_plugin_root . '/includes/admin/staffing.php';

$core_staffing_source = (string) file_get_contents($core_staffing_path);
$admin_staffing_source = (string) file_get_contents($admin_staffing_path);
$live_core_staffing_source = (string) file_get_contents($live_core_staffing_path);
$live_admin_staffing_source = (string) file_get_contents($live_admin_staffing_path);

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
	'includes/admin/staffing.php:869:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
);

$expected_t5_inventory = array(
	'includes/core/staffing.php:710:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:3815:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/staffing.php:3915:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
);

$actual_inventory = vms_test_collect_db_phpcs_inventory(array($store_path, $admin_ui_path, $db_path, $staff_portal_path, $core_staffing_path, $admin_staffing_path));
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
$invented_inventory[] = 'includes/admin/staffing.php:999999:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching';
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
vms_test_assert_true(strpos($core_staffing_source, 'phpcs:disable') === false, 'No file-level or block-level PHPCS disable should appear in core staffing.php.');
vms_test_assert_true(strpos($admin_staffing_source, 'phpcs:disable') === false, 'No file-level or block-level PHPCS disable should appear in admin staffing.php.');

$admin_targets = array(
	'bvmgr_staffing_admin_render_rollups_page',
);
$core_targets = array(
	'bvmgr_staffing_get_event_plan_headcount_context',
	'bvmgr_staffing_save_event_roles_matrix',
	'bvmgr_staffing_mark_rollup_dirty',
	'bvmgr_staffing_compute_rollup',
	'bvmgr_staffing_get_rollup',
);
vms_test_assert_same(
	vms_test_collect_target_hashes($admin_staffing_source, $admin_targets),
	vms_test_collect_target_hashes($live_admin_staffing_source, $admin_targets),
	'Mirror and live rollups-admin targets should remain byte-identical.'
);
vms_test_assert_same(
	vms_test_collect_target_hashes($core_staffing_source, $core_targets),
	vms_test_collect_target_hashes($live_core_staffing_source, $core_targets),
	'Mirror and live staffing matrix and rollup targets should remain byte-identical.'
);

eval(vms_test_extract_function($admin_staffing_source, 'bvmgr_staffing_admin_render_rollups_page'));
eval(vms_test_extract_function($core_staffing_source, 'bvmgr_staffing_get_event_plan_headcount_context'));
eval(vms_test_extract_function($core_staffing_source, 'bvmgr_staffing_mark_rollup_dirty'));
eval(vms_test_extract_function($core_staffing_source, 'bvmgr_staffing_estimate_slot_cost'));
eval(vms_test_extract_function($core_staffing_source, 'bvmgr_staffing_compute_rollup'));
eval(vms_test_extract_function($core_staffing_source, 'bvmgr_staffing_get_rollup'));
eval(vms_test_extract_function($core_staffing_source, 'bvmgr_staffing_save_event_roles_matrix'));

try {
	vms_test_run_admin_rollups_query_assertions();
	vms_test_run_headcount_context_assertions();
	vms_test_run_mark_rollup_dirty_assertions();
	vms_test_run_save_event_roles_matrix_assertions();
	vms_test_run_compute_rollup_assertions();
	vms_test_run_get_rollup_assertions();
	echo "OK\n";
} catch (Throwable $exception) {
	fwrite(STDERR, $exception->getMessage() . "\n");
	exit(1);
}
