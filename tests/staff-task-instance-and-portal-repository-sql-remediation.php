<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

if (!defined('ARRAY_A')) {
	define('ARRAY_A', 'ARRAY_A');
}

final class WP_Error
{
	private string $code;
	private string $message;

	public function __construct(string $code = '', string $message = '')
	{
		$this->code = $code;
		$this->message = $message;
	}

	public function get_error_code(): string
	{
		return $this->code;
	}

	public function get_error_message(): string
	{
		return $this->message;
	}
}

final class WP_Term
{
	public int $term_id = 0;

	public function __construct(int $term_id)
	{
		$this->term_id = $term_id;
	}
}

final class VMS_Test_WPDB
{
	public string $prefix = 'wp_';
	public string $usermeta = 'wp_usermeta';
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
	public function get_col(string $query)
	{
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

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_key(string $value): string
{
	return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', $value));
}

function sanitize_text_field($value): string
{
	return trim(strip_tags((string) $value));
}

function wp_kses_post($value): string
{
	return (string) $value;
}

function wp_json_encode($value)
{
	$json = json_encode($value);
	return is_string($json) ? $json : false;
}

function is_wp_error($thing): bool
{
	return $thing instanceof WP_Error;
}

function get_current_user_id(): int
{
	return 77;
}

function vms_tasks_table_name(string $name): string
{
	$map = array(
		'task_instances' => 'wp_vms_task_instances',
	);

	return $map[$name] ?? '';
}

function vms_tasks_now_utc_mysql(): string
{
	return '2026-08-02 17:00:00';
}

function vms_tasks_now_local_mysql(): string
{
	return '2026-08-02 12:00:00';
}

function vms_tasks_sanitize_status(string $status): string
{
	return sanitize_key($status);
}

function vms_tasks_sanitize_recurrence_pattern(string $pattern): string
{
	return sanitize_key($pattern);
}

function vms_tasks_normalize_recurrence_every_n_days(string $pattern, $value): int
{
	unset($pattern);
	$normalized = absint($value);
	return $normalized > 0 ? $normalized : 1;
}

function vms_tasks_recurrence_next_due_local(string $due_at_local, string $pattern, int $every_n_days): ?string
{
	unset($pattern, $every_n_days);
	$dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $due_at_local);
	if (!$dt instanceof DateTimeImmutable) {
		return null;
	}

	return $dt->modify('+1 day')->format('Y-m-d H:i:s');
}

function vms_tasks_sanitize_priority(string $priority): string
{
	return sanitize_key($priority);
}

function vms_tasks_sanitize_assignment_mode(string $mode): string
{
	return sanitize_key($mode);
}

function vms_tasks_log_task_action(int $instance_id, string $action, ?int $actor_user_id, $payload): void
{
	$GLOBALS['vms_task_logs'][] = array(
		'instance_id' => $instance_id,
		'action' => $action,
		'actor_user_id' => $actor_user_id,
		'payload' => $payload,
	);
}

function vms_tasks_emit_assignment_notification(array $instance): void
{
	$GLOBALS['vms_assignment_notifications'][] = $instance;
}

function taxonomy_exists(string $taxonomy): bool
{
	return $taxonomy === 'vms_staff_role';
}

function get_term_by(string $field, string $value, string $taxonomy)
{
	if ($field === 'slug' && $value !== '' && $taxonomy === 'vms_staff_role') {
		return new WP_Term(5);
	}

	return false;
}

function get_post_meta(int $post_id, string $key, bool $single = true)
{
	unset($single);
	return $GLOBALS['vms_test_post_meta'][$post_id][$key] ?? '';
}

function vms_staffing_table_name(string $name): string
{
	$map = array(
		'assignments' => 'wp_vms_assignments',
		'event_slots' => 'wp_vms_event_slots',
	);

	return $map[$name] ?? '';
}

function vms_staffing_role_map_by_id(bool $active_only = true): array
{
	unset($active_only);
	return array(
		5 => array('name' => 'Ops'),
	);
}

function get_the_title(int $post_id): string
{
	return $GLOBALS['vms_test_titles'][$post_id] ?? '';
}

function wp_timezone(): DateTimeZone
{
	return new DateTimeZone('America/Chicago');
}

function wp_date(string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null): string
{
	$timestamp = $timestamp ?? time();
	$timezone = $timezone ?? wp_timezone();
	$dt = new DateTimeImmutable('@' . $timestamp);
	return $dt->setTimezone($timezone)->format($format);
}

function vms_staff_portal_assignment_status_label(string $status): string
{
	return strtoupper($status);
}

function vms_staff_portal_visible_event_statuses(): array
{
	return array('confirmed', 'published', 'ready');
}

function vms_staffing_resolve_slot_window(int $plan_id, array $row): array
{
	unset($plan_id, $row);
	return array();
}

function vms_format_local_ymd(string $ymd, string $format): string
{
	unset($format);
	return $ymd;
}

function vms_event_plan_status_label(string $status): string
{
	return strtoupper($status);
}

function vms_event_plan_get_status(int $plan_id, string $context): string
{
	unset($context);
	return $GLOBALS['vms_test_plan_status'][$plan_id] ?? 'draft';
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

/** @return array<string,string> */
function vms_test_collect_target_hashes(string $source, array $function_names): array
{
	$hashes = array();
	foreach ($function_names as $function_name) {
		$hashes[$function_name] = hash('sha256', vms_test_extract_function($source, $function_name));
	}
	return $hashes;
}

function vms_test_last_call(VMS_Test_WPDB $wpdb, string $kind): array
{
	for ($i = count($wpdb->call_log) - 1; $i >= 0; $i--) {
		if (($wpdb->call_log[$i]['kind'] ?? '') === $kind) {
			return $wpdb->call_log[$i];
		}
	}

	throw new RuntimeException('Unable to locate call log entry for ' . $kind . '.');
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
	$GLOBALS['vms_task_logs'] = array();
	$GLOBALS['vms_assignment_notifications'] = array();
	$GLOBALS['vms_test_post_meta'] = array();
	$GLOBALS['vms_test_titles'] = array();
	$GLOBALS['vms_test_plan_status'] = array();

	return $wpdb;
}

$plugin_root = dirname(__DIR__);
$live_plugin_root = dirname($plugin_root, 2) . '/vms';
$store_path = $plugin_root . '/includes/modules/staff-tasks/store.php';
$portal_path = $plugin_root . '/includes/portal/staff-portal.php';
$live_store_path = $live_plugin_root . '/includes/modules/staff-tasks/store.php';
$live_portal_path = $live_plugin_root . '/includes/portal/staff-portal.php';

$store_source = (string) file_get_contents($store_path);
$portal_source = (string) file_get_contents($portal_path);
$live_store_source = (string) file_get_contents($live_store_path);
$live_portal_source = (string) file_get_contents($live_portal_path);

$store_targets = array(
	'vms_tasks_get_instance',
	'vms_tasks_get_instances',
	'vms_tasks_count_instances',
	'vms_tasks_insert_instance',
	'vms_tasks_update_instance_assignment',
	'vms_tasks_transition_instance_status',
	'vms_tasks_spawn_next_recurrence_instance',
	'vms_tasks_resolve_scheduled_role_user_id',
	'vms_tasks_select_existing_open_instance',
	'vms_tasks_supersede_open_instances',
);
$portal_targets = array(
	'vms_staff_portal_get_event_crew_rows',
	'vms_staff_portal_get_assignment_rows',
);

try {
	vms_test_assert_same(
		vms_test_collect_target_hashes($store_source, $store_targets),
		vms_test_collect_target_hashes($live_store_source, $store_targets),
		'Mirror and live task-instance repository targets should remain byte-identical.'
	);
	vms_test_assert_same(
		vms_test_collect_target_hashes($portal_source, $portal_targets),
		vms_test_collect_target_hashes($live_portal_source, $portal_targets),
		'Mirror and live staff-portal repository targets should remain byte-identical.'
	);

	$get_instances_source = vms_test_extract_function($store_source, 'vms_tasks_get_instances');
	$count_instances_source = vms_test_extract_function($store_source, 'vms_tasks_count_instances');
	$resolve_role_source = vms_test_extract_function($store_source, 'vms_tasks_resolve_scheduled_role_user_id');
	$crew_rows_source = vms_test_extract_function($portal_source, 'vms_staff_portal_get_event_crew_rows');
	$assignment_rows_source = vms_test_extract_function($portal_source, 'vms_staff_portal_get_assignment_rows');

	vms_test_assert_true(strpos($get_instances_source, 'SELECT * FROM {$t_instances}') === false, 'Task-instance list reads should no longer interpolate table names into SQL strings.');
	vms_test_assert_true(strpos($count_instances_source, 'SELECT COUNT(*) FROM {$t_instances}') === false, 'Task-instance count reads should no longer interpolate table names into SQL strings.');
	vms_test_assert_true(strpos($resolve_role_source, 'get_users(array(') === false, 'Scheduled-role fallback should no longer use get_users() meta queries.');
	vms_test_assert_true(strpos($crew_rows_source, '$sql = $wpdb->prepare(') === false, 'Crew-row reads should prepare inline at the execution boundary.');
	vms_test_assert_true(strpos($assignment_rows_source, '$sql = $wpdb->prepare(') === false, 'Assignment-row reads should prepare inline at the execution boundary.');

	foreach (array_merge($store_targets, $portal_targets) as $function_name) {
		if (in_array($function_name, $store_targets, true)) {
			eval(vms_test_extract_function($store_source, $function_name));
		} else {
			eval(vms_test_extract_function($portal_source, $function_name));
		}
	}

	$wpdb = vms_test_reset_state();
	$wpdb->get_row_queue[] = array('id' => 41, 'status' => 'open');
	vms_test_assert_same(array('id' => 41, 'status' => 'open'), vms_tasks_get_instance(41), 'Single task-instance reads should return the row unchanged.');
	$prepare = vms_test_find_prepare($wpdb, 'SELECT * FROM %i WHERE id = %d LIMIT 1');
	vms_test_assert_same(
		array('wp_vms_task_instances', 41),
		$prepare['args'],
		'Single task-instance reads should prepare the repository table identifier and integer ID.'
	);
	vms_test_assert_no_placeholders(vms_test_last_call($wpdb, 'get_row')['query'], 'Single task-instance reads should execute fully prepared SQL.');

	$wpdb = vms_test_reset_state();
	$wpdb->get_results_queue[] = array(array('id' => 1));
	$list_filters = array(
		'task_instance_id' => 'abc',
		'event_id' => 7,
		'event_linkage' => 'event',
		'status' => 'Open',
		'exclude_status' => 'superseded',
		'assignee_user_id' => 12,
		'role_key' => 'Stage_Manager',
		'venue_id' => 9,
		'required_only' => true,
		'due_before' => '2026-08-20 10:00:00',
		'due_after' => '2026-08-10 10:00:00',
		'limit' => 1500,
	);
	vms_test_assert_same(array(array('id' => 1)), vms_tasks_get_instances($list_filters), 'Task-instance list reads should return the queued row set unchanged.');
	$prepare = vms_test_find_prepare($wpdb, 'SELECT * FROM %i WHERE (%d = 0 OR id = %d)');
	vms_test_assert_same(
		array(
			'wp_vms_task_instances',
			1,
			0,
			1,
			7,
			'event',
			'event',
			'event',
			1,
			'open',
			1,
			'superseded',
			1,
			12,
			1,
			'stage_manager',
			1,
			9,
			1,
			1,
			'2026-08-20 10:00:00',
			1,
			'2026-08-10 10:00:00',
			1000,
		),
		$prepare['args'],
		'Task-instance list reads should normalize optional filters into sentinel-prepared arguments.'
	);
	vms_test_assert_no_placeholders(vms_test_last_call($wpdb, 'get_results')['query'], 'Task-instance list reads should execute fully prepared SQL.');

	$wpdb = vms_test_reset_state();
	$wpdb->get_var_queue[] = 4;
	$count_filters = array(
		'event_id' => 11,
		'event_linkage' => 'non_event',
		'status' => 'DONE',
		'exclude_status' => 'canceled',
		'role_key' => '!!!',
		'required_only' => true,
	);
	vms_test_assert_same(4, vms_tasks_count_instances($count_filters), 'Task-instance count reads should return the queued count value.');
	$prepare = vms_test_find_prepare($wpdb, 'SELECT COUNT(*) FROM %i WHERE (%d = 0 OR id = %d)');
	vms_test_assert_same(
		array(
			'wp_vms_task_instances',
			0,
			0,
			1,
			11,
			'non_event',
			'non_event',
			'non_event',
			1,
			'done',
			1,
			'canceled',
			0,
			0,
			1,
			'',
			0,
			0,
			1,
			0,
			'',
			0,
			'',
		),
		$prepare['args'],
		'Task-instance count reads should normalize optional filters into sentinel-prepared arguments.'
	);
	vms_test_assert_no_placeholders(vms_test_last_call($wpdb, 'get_var')['query'], 'Task-instance count reads should execute fully prepared SQL.');

	$wpdb = vms_test_reset_state();
	$wpdb->insert_id = 913;
	$inserted = vms_tasks_insert_instance(array(
		'title' => 'Line Check',
		'event_id' => 22,
		'assignee_user_id' => 12,
		'status' => 'open',
		'assignment_mode' => 'person',
	));
	vms_test_assert_same(913, $inserted, 'Task-instance inserts should return wpdb::insert_id on success.');
	$insert_call = vms_test_last_call($wpdb, 'insert');
	vms_test_assert_same('wp_vms_task_instances', $insert_call['table'], 'Task-instance inserts should target the task-instance repository table.');
	vms_test_assert_same('Line Check', $insert_call['data']['title'], 'Task-instance inserts should persist the normalized title.');

	$wpdb = vms_test_reset_state();
	$wpdb->get_row_queue[] = array('id' => 33, 'assignee_user_id' => 45);
	vms_test_assert_true(
		vms_tasks_update_instance_assignment(33, 45, true, 88, 'role', 'Front_Desk'),
		'Task-instance assignment updates should report success when wpdb::update() succeeds.'
	);
	$update_call = vms_test_last_call($wpdb, 'update');
	vms_test_assert_same('wp_vms_task_instances', $update_call['table'], 'Assignment updates should target the task-instance repository table.');
	vms_test_assert_same('front_desk', $update_call['data']['role_key'], 'Assignment updates should normalize the role key before persisting it.');
	vms_test_assert_same('role', $update_call['data']['assignment_mode'], 'Assignment updates should persist the provided assignment mode.');

	$wpdb = vms_test_reset_state();
	$wpdb->get_row_queue[] = array('id' => 44, 'status' => 'done');
	vms_test_assert_true(
		vms_tasks_transition_instance_status(44, 'open', '', 90),
		'Task-instance status transitions should report success when wpdb::update() succeeds.'
	);
	$update_call = vms_test_last_call($wpdb, 'update');
	vms_test_assert_same('open', $update_call['data']['status'], 'Status transitions should persist the normalized target status.');
	vms_test_assert_same(array('id' => 44), $update_call['where'], 'Status transitions should target the requested task-instance row.');

	$wpdb = vms_test_reset_state();
	$wpdb->insert_id = 902;
	$wpdb->get_row_queue[] = array(
		'id' => 55,
		'status' => 'done',
		'event_id' => 0,
		'due_at_local' => '2026-08-10 09:00:00',
		'recurrence_pattern' => 'daily',
		'recurrence_every_n_days' => 1,
		'recurrence_root_instance_id' => 0,
		'task_template_id' => 3,
		'origin_checklist_id' => 6,
		'venue_id' => 8,
		'event_type' => 'concert',
		'title' => 'Recurring',
		'instructions' => 'Bring mics.',
		'priority' => 'high',
		'is_required' => 1,
		'assignment_mode' => 'person',
		'role_key' => '',
		'assignee_user_id' => 71,
		'assignment_locked' => 1,
	);
	$wpdb->get_var_queue[] = 0;
	$wpdb->get_row_queue[] = array('id' => 902, 'assignee_user_id' => 71);
	vms_test_assert_same(902, vms_tasks_spawn_next_recurrence_instance(55, 91), 'Recurrence spawning should return the inserted task-instance ID when no successor exists.');
	$prepare = vms_test_find_prepare($wpdb, 'SELECT id FROM %i WHERE (id = %d OR recurrence_root_instance_id = %d)');
	vms_test_assert_same(
		array('wp_vms_task_instances', 55, 55, '2026-08-11 09:00:00', 'superseded'),
		$prepare['args'],
		'Recurrence spawning should prepare the repository table and successor lookup filters.'
	);
	$insert_call = vms_test_last_call($wpdb, 'insert');
	vms_test_assert_same(55, $insert_call['data']['recurrence_root_instance_id'], 'Recurrence spawning should persist the root instance ID on the successor row.');

	$wpdb = vms_test_reset_state();
	$wpdb->get_col_queue[] = array(123);
	$wpdb->get_var_queue[] = 88;
	$GLOBALS['vms_test_post_meta'][123]['_vms_linked_user_id'] = '';
	vms_test_assert_same(
		array('status' => 'single', 'assignee_user_id' => 88, 'staff_ids' => array(123)),
		vms_tasks_resolve_scheduled_role_user_id(11, 'ops'),
		'Scheduled-role resolution should return the linked user when exactly one staffed match exists.'
	);
	$prepare = vms_test_find_prepare($wpdb, 'SELECT DISTINCT a.staff_id FROM %i s INNER JOIN %i a ON a.slot_id = s.slot_id');
	vms_test_assert_same(
		array('wp_vms_event_slots', 'wp_vms_assignments', 11, 5, 'active', 'proposed', 'confirmed', 'checked_in'),
		$prepare['args'],
		'Scheduled-role resolution should prepare the staffing-table identifiers and filter values.'
	);
	$prepare = vms_test_find_prepare($wpdb, 'SELECT user_id FROM %i WHERE meta_key = %s AND meta_value = %s ORDER BY umeta_id ASC LIMIT 1');
	vms_test_assert_same(
		array('wp_usermeta', '_vms_staff_id', '123'),
		$prepare['args'],
		'Scheduled-role fallback should prepare the usermeta identifier, meta key, and normalized staff ID string.'
	);

	$wpdb = vms_test_reset_state();
	$wpdb->get_row_queue[] = array('id' => 1);
	vms_test_assert_same(array('id' => 1), vms_tasks_select_existing_open_instance(20, 4, 6, null, true), 'Strict due-null selection should return the queued row.');
	$prepare = vms_test_find_prepare($wpdb, 'due_at_local IS NULL ORDER BY id DESC LIMIT 1');
	vms_test_assert_same(
		array('wp_vms_task_instances', 20, 4, 6, 'open'),
		$prepare['args'],
		'Strict due-null selection should prepare the repository table and equality filters.'
	);

	$wpdb = vms_test_reset_state();
	$wpdb->get_row_queue[] = array('id' => 2);
	vms_test_assert_same(array('id' => 2), vms_tasks_select_existing_open_instance(20, 4, 6, '2026-08-20 08:00:00', true), 'Strict due-match selection should return the queued row.');
	$prepare = vms_test_find_prepare($wpdb, 'due_at_local = %s ORDER BY id DESC LIMIT 1');
	vms_test_assert_same(
		array('wp_vms_task_instances', 20, 4, 6, 'open', '2026-08-20 08:00:00'),
		$prepare['args'],
		'Strict due-match selection should prepare the repository table, equality filters, and due timestamp.'
	);

	$wpdb = vms_test_reset_state();
	$wpdb->get_row_queue[] = array('id' => 3);
	vms_test_assert_same(array('id' => 3), vms_tasks_select_existing_open_instance(20, 4, 6, '2026-08-20 08:00:00', false), 'Non-strict open-instance selection should return the queued row.');
	$prepare = vms_test_find_prepare($wpdb, 'status = %s ORDER BY id DESC LIMIT 1');
	vms_test_assert_same(
		array('wp_vms_task_instances', 20, 4, 6, 'open'),
		$prepare['args'],
		'Non-strict open-instance selection should prepare the repository table and equality filters.'
	);

	$wpdb = vms_test_reset_state();
	$wpdb->get_results_queue[] = array(array('id' => 5), array('id' => 6));
	vms_test_assert_same(2, vms_tasks_supersede_open_instances(20, 4, 6, 99, 72), 'Open-instance supersession should count each successful sibling update.');
	$prepare = vms_test_find_prepare($wpdb, 'SELECT id FROM %i WHERE event_id = %d AND task_template_id = %d AND origin_checklist_id = %d AND status = %s AND id <> %d');
	vms_test_assert_same(
		array('wp_vms_task_instances', 20, 4, 6, 'open', 99),
		$prepare['args'],
		'Open-instance supersession should prepare the repository table and sibling selection filters.'
	);
	$update_calls = array_values(array_filter(
		$wpdb->call_log,
		static fn(array $entry): bool => ($entry['kind'] ?? '') === 'update'
	));
	vms_test_assert_same(2, count($update_calls), 'Open-instance supersession should update every queued sibling row.');

	$wpdb = vms_test_reset_state();
	$GLOBALS['vms_test_titles'][51] = 'Alex';
	$wpdb->get_results_queue[] = array(array(
		'assignment_id' => 10,
		'staff_id' => 51,
		'assignment_status' => 'confirmed',
		'shift_start_ts' => 0,
		'shift_end_ts' => 0,
		'slot_id' => 80,
		'role_id' => 5,
		'display_label_override' => '',
		'shift_start_local' => '09:00',
		'shift_end_local' => '11:00',
	));
	$crew_rows = vms_staff_portal_get_event_crew_rows(22);
	vms_test_assert_same('Ops', $crew_rows[0]['role_label'] ?? '', 'Crew-row reads should preserve the staffing role label resolution.');
	vms_test_assert_same('09:00–11:00', $crew_rows[0]['shift_label'] ?? '', 'Crew-row reads should preserve the local shift label fallback.');
	$prepare = vms_test_find_prepare($wpdb, 'SELECT a.assignment_id, a.staff_id, a.status AS assignment_status');
	vms_test_assert_same(
		array('wp_vms_assignments', 'wp_vms_event_slots', 22),
		$prepare['args'],
		'Crew-row reads should prepare the staffing table identifiers and event-plan filter.'
	);
	vms_test_assert_no_placeholders(vms_test_last_call($wpdb, 'get_results')['query'], 'Crew-row reads should execute fully prepared SQL.');

	$wpdb = vms_test_reset_state();
	$GLOBALS['vms_test_titles'][22] = 'Load In';
	$GLOBALS['vms_test_post_meta'][22]['_vms_event_date'] = '2026-08-20';
	$GLOBALS['vms_test_plan_status'][22] = 'confirmed';
	$wpdb->get_results_queue[] = array(array(
		'assignment_id' => 11,
		'staff_id' => 51,
		'assignment_status' => 'proposed',
		'shift_start_ts' => 0,
		'shift_end_ts' => 0,
		'slot_id' => 81,
		'event_plan_id' => 22,
		'role_id' => 5,
		'display_label_override' => '',
		'shift_time_mode' => 'manual',
		'shift_start_local' => '14:00',
		'shift_end_local' => '16:00',
		'start_anchor_key' => '',
		'start_offset_minutes' => 0,
		'end_anchor_key' => '',
		'end_offset_minutes' => 0,
		'duration_minutes' => 120,
		'slot_notes' => 'Soundcheck',
	));
	$assignment_rows = vms_staff_portal_get_assignment_rows(51, 25);
	vms_test_assert_same(1, count($assignment_rows), 'Assignment-row reads should return visible future event assignments.');
	vms_test_assert_same('Load In', $assignment_rows[0]['event_title'] ?? '', 'Assignment-row reads should retain the event title payload.');
	vms_test_assert_same('Ops', $assignment_rows[0]['role_label'] ?? '', 'Assignment-row reads should retain the normalized role label.');
	$prepare = vms_test_find_prepare($wpdb, 'SELECT a.assignment_id, a.staff_id, a.status AS assignment_status');
	vms_test_assert_same(
		array('wp_vms_assignments', 'wp_vms_event_slots', 51, 25),
		$prepare['args'],
		'Assignment-row reads should prepare the staffing table identifiers, staff ID, and limit.'
	);
	vms_test_assert_no_placeholders(vms_test_last_call($wpdb, 'get_results')['query'], 'Assignment-row reads should execute fully prepared SQL.');

	fwrite(STDOUT, "Staff task-instance and portal repository SQL remediation OK.\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'Staff task-instance and portal repository SQL remediation FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
