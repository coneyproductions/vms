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

final class VMS_Test_WPDB
{
	public string $prefix = 'wp_';
	public string $postmeta = 'wp_postmeta';
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
	/** @var array<int,string> */
	public array $esc_like_calls = array();
	/** @var mixed */
	public $insert_return = 1;
	/** @var mixed */
	public $update_return = 1;
	/** @var mixed */
	public $delete_return = 1;

	public function esc_like(string $text): string
	{
		$this->esc_like_calls[] = $text;
		return addcslashes($text, '_%\\');
	}

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

	/**
	 * @return array<int,mixed>|mixed
	 */
	public function get_col(string $query)
	{
		$result = $this->shift_queue($this->get_col_queue, array());
		$this->record_execution('get_col', $query, $result);
		return $result;
	}

	/**
	 * @return mixed
	 */
	public function get_var(string $query)
	{
		$result = $this->shift_queue($this->get_var_queue, null);
		$this->record_execution('get_var', $query, $result);
		return $result;
	}

	/**
	 * @return mixed
	 */
	public function get_row(string $query, $output = ARRAY_A)
	{
		unset($output);
		$result = $this->shift_queue($this->get_row_queue, null);
		$this->record_execution('get_row', $query, $result);
		return $result;
	}

	/**
	 * @return mixed
	 */
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
		return '2026-08-01 12:34:56';
	}
	return '2026-08-01 12:34:56';
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

function get_current_user_id(): int
{
	return 77;
}

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_key(string $value): string
{
	return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', $value));
}

function is_wp_error($thing): bool
{
	return $thing instanceof WP_Error;
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
	vms_test_assert_true(strpos($haystack, $needle) !== false, $message);
}

function vms_test_extract_function(string $source, string $name): string
{
	$needle = 'function ' . $name;
	$start = strpos($source, $needle);
	if ($start === false) {
		throw new RuntimeException('Unable to locate function ' . $name . '.');
	}

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
 * @param array<int,string> $actual_inventory
 * @param array<int,string> $expected_t1_inventory
 * @param array<int,string> $expected_t2_inventory
 * @return array{t1: array<int,string>, t2: array<int,string>, classified: array<int,string>}
 */
function vms_test_reconcile_directquery_inventory(
	array $actual_inventory,
	array $expected_t1_inventory,
	array $expected_t2_inventory
): array {
	$overlap = array_values(array_intersect($expected_t1_inventory, $expected_t2_inventory));
	vms_test_assert_same(array(), $overlap, 'T1 and T2 DirectQuery/NoCaching ownership must not overlap.');

	$duplicate_inventory = array_keys(
		array_filter(
			array_count_values($actual_inventory),
			static function (int $count): bool {
				return $count > 1;
			}
		)
	);
	vms_test_assert_same(array(), $duplicate_inventory, 'DirectQuery/NoCaching inventory should not contain duplicate suppression entries.');

	$expected_t1_lookup = array_fill_keys($expected_t1_inventory, true);
	$expected_t2_lookup = array_fill_keys($expected_t2_inventory, true);
	$actual_t1_inventory = array();
	$actual_t2_inventory = array();
	$classified_inventory = array();
	$unknown_inventory = array();

	foreach ($actual_inventory as $entry) {
		if (isset($expected_t1_lookup[$entry])) {
			$actual_t1_inventory[] = $entry;
			$classified_inventory[] = $entry;
			continue;
		}

		if (isset($expected_t2_lookup[$entry])) {
			$actual_t2_inventory[] = $entry;
			$classified_inventory[] = $entry;
			continue;
		}

		$unknown_inventory[] = $entry;
	}

	vms_test_assert_same(array(), $unknown_inventory, 'Every DirectQuery/NoCaching suppression must be classified as T1 or T2.');
	vms_test_assert_same($expected_t1_inventory, $actual_t1_inventory, 'The accepted T1 DirectQuery/NoCaching inventory should remain exact.');
	vms_test_assert_same($expected_t2_inventory, $actual_t2_inventory, 'The accepted T2 DirectQuery/NoCaching inventory should remain exact.');
	vms_test_assert_same(
		vms_test_sort_inventory(array_merge($actual_t1_inventory, $actual_t2_inventory)),
		vms_test_sort_inventory($actual_inventory),
		'The combined T1/T2 DirectQuery/NoCaching inventories should reconcile to the complete actual inventory.'
	);
	vms_test_assert_same($actual_inventory, $classified_inventory, 'The classified DirectQuery/NoCaching inventory should preserve the full actual inventory order.');

	return array(
		't1' => $actual_t1_inventory,
		't2' => $actual_t2_inventory,
		'classified' => $classified_inventory,
	);
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

function vms_test_last_prepare(VMS_Test_WPDB $wpdb): array
{
	if ($wpdb->prepare_calls === array()) {
		throw new RuntimeException('Expected a prepare() call.');
	}

	return $wpdb->prepare_calls[count($wpdb->prepare_calls) - 1];
}

function vms_test_assert_no_placeholders(string $sql, string $message): void
{
	vms_test_assert_true(preg_match('/%(?:\d+\$)?[sdi]/', $sql) !== 1, $message . "\nSQL: " . $sql);
}

/** @return list<string> */
function vms_test_target_functions(): array
{
	return array(
		'vms_tasks_admin_get_event_type_options',
		'vms_tasks_db_ready',
		'vms_tasks_log_task_action',
		'vms_tasks_has_task_action_log',
		'vms_tasks_get_task_template',
		'vms_tasks_get_task_templates',
		'vms_tasks_upsert_task_template',
		'vms_tasks_get_checklist_template',
		'vms_tasks_get_checklist_templates',
		'vms_tasks_upsert_checklist_template',
		'vms_tasks_replace_checklist_items',
		'vms_tasks_get_checklist_items',
		'vms_tasks_get_applicable_checklists',
	);
}

$plugin_root = dirname(__DIR__);
$live_plugin_root = dirname($plugin_root, 2) . '/vms';
$store_path = $plugin_root . '/includes/modules/staff-tasks/store.php';
$db_path = $plugin_root . '/includes/modules/staff-tasks/db.php';
$admin_ui_path = $plugin_root . '/includes/modules/staff-tasks/admin-ui.php';
$staff_portal_path = $plugin_root . '/includes/portal/staff-portal.php';
$live_store_path = $live_plugin_root . '/includes/modules/staff-tasks/store.php';
$live_db_path = $live_plugin_root . '/includes/modules/staff-tasks/db.php';
$live_admin_ui_path = $live_plugin_root . '/includes/modules/staff-tasks/admin-ui.php';

$store_source = (string) file_get_contents($store_path);
$db_source = (string) file_get_contents($db_path);
$admin_ui_source = (string) file_get_contents($admin_ui_path);
$live_store_source = (string) file_get_contents($live_store_path);
$live_db_source = (string) file_get_contents($live_db_path);
$live_admin_ui_source = (string) file_get_contents($live_admin_ui_path);

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
$actual_inventory = vms_test_collect_db_phpcs_inventory(array($store_path, $admin_ui_path, $db_path, $staff_portal_path));
vms_test_reconcile_directquery_inventory($actual_inventory, $expected_t1_inventory, $expected_t2_inventory);
$invented_inventory = $actual_inventory;
$invented_inventory[] = 'includes/portal/staff-portal.php:999999:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching';
$negative_control_rejected = false;
try {
	vms_test_reconcile_directquery_inventory($invented_inventory, $expected_t1_inventory, $expected_t2_inventory);
} catch (RuntimeException $exception) {
	$negative_control_rejected = true;
	vms_test_assert_contains(
		'Every DirectQuery/NoCaching suppression must be classified as T1 or T2.',
		$exception->getMessage(),
		'Synthetic negative control should fail because the invented suppression is unclassified.'
	);
}
vms_test_assert_true($negative_control_rejected, 'Synthetic negative control should reject an invented DirectQuery/NoCaching suppression.');
vms_test_assert_true(strpos($store_source, 'PluginCheck.Security.DirectDB') === false, 'No DirectDB suppression should remain in the mirror store repository.');
vms_test_assert_true(strpos($admin_ui_source, 'PluginCheck.Security.DirectDB') === false, 'No DirectDB suppression should remain in the mirror admin selector repository.');
vms_test_assert_true(strpos($db_source, 'PluginCheck.Security.DirectDB') === false, 'No DirectDB suppression should remain in the mirror schema repository.');
vms_test_assert_true(strpos($store_source, 'phpcs:disable') === false, 'No file-level or block-level PHPCS disable should appear in store.php.');
vms_test_assert_true(strpos($admin_ui_source, 'phpcs:disable') === false, 'No file-level or block-level PHPCS disable should appear in admin-ui.php.');
vms_test_assert_true(strpos($db_source, 'phpcs:disable') === false, 'No file-level or block-level PHPCS disable should appear in db.php.');

$target_functions = vms_test_target_functions();
$mirror_hashes = array_merge(
	vms_test_collect_target_hashes($store_source, array_slice($target_functions, 2)),
	vms_test_collect_target_hashes($db_source, array('vms_tasks_db_ready')),
	vms_test_collect_target_hashes($admin_ui_source, array('vms_tasks_admin_get_event_type_options'))
);
$live_hashes = array_merge(
	vms_test_collect_target_hashes($live_store_source, array_slice($target_functions, 2)),
	vms_test_collect_target_hashes($live_db_source, array('vms_tasks_db_ready')),
	vms_test_collect_target_hashes($live_admin_ui_source, array('vms_tasks_admin_get_event_type_options'))
);
vms_test_assert_same($mirror_hashes, $live_hashes, 'All edited T1 target functions should remain byte-identical across mirror and live.');
vms_test_assert_true(
	hash('sha256', $admin_ui_source) !== hash('sha256', $live_admin_ui_source),
	'Mirror/live admin-ui.php should retain unrelated whole-file divergence while the edited target function stays aligned.'
);

foreach (array(
	'vms_tasks_now_utc_mysql',
	'vms_tasks_allowed_priorities',
	'vms_tasks_sanitize_priority',
	'vms_tasks_sanitize_scope',
	'vms_tasks_sanitize_due_mode',
	'vms_tasks_sanitize_assignment_mode',
	'vms_tasks_sanitize_apply_mode',
	'vms_tasks_log_task_action',
	'vms_tasks_has_task_action_log',
	'vms_tasks_get_task_template',
	'vms_tasks_get_task_templates',
	'vms_tasks_upsert_task_template',
	'vms_tasks_get_checklist_template',
	'vms_tasks_get_checklist_templates',
	'vms_tasks_upsert_checklist_template',
	'vms_tasks_replace_checklist_items',
	'vms_tasks_decode_checklist_overrides',
	'vms_tasks_get_checklist_items',
	'vms_tasks_get_applicable_checklists',
) as $function_name) {
	eval(vms_test_extract_function($store_source, $function_name));
}
foreach (array('vms_tasks_table_name', 'vms_tasks_db_ready') as $function_name) {
	eval(vms_test_extract_function($db_source, $function_name));
}
eval(vms_test_extract_function($admin_ui_source, 'vms_tasks_admin_get_event_type_options'));

$GLOBALS['wpdb'] = new VMS_Test_WPDB();

$wpdb = $GLOBALS['wpdb'];
$wpdb->get_col_queue[] = array('Rock Show', 'VIP Night', 'rock-show', '', 'VIP Night');
$types = vms_tasks_admin_get_event_type_options();
vms_test_assert_same(
	array(
		'rockshow' => 'rockshow',
		'vipnight' => 'vipnight',
		'rock-show' => 'rock-show',
	),
	$types,
	'Event-type options should sanitize keys, discard blanks, and preserve selector continuity.'
);
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same(
	'SELECT DISTINCT meta_value FROM %i WHERE meta_key = %s AND meta_value <> \'\' ORDER BY meta_value ASC',
	$prepare['query'],
	'The event-type selector should prepare its identifier and fixed meta-key filter.'
);
vms_test_assert_same(array('wp_postmeta', '_vms_event_type'), $prepare['args'], 'Event-type selector should prepare the postmeta table and fixed meta key.');
vms_test_assert_contains('`wp_postmeta`', $prepare['final_sql'], 'Event-type selector final SQL should quote the postmeta identifier.');
vms_test_assert_no_placeholders($prepare['final_sql'], 'Event-type selector final SQL should not retain unresolved placeholders.');
vms_test_assert_same('get_col', vms_test_last_call($wpdb, 'get_col')['kind'], 'Event-type selector should execute through get_col().');

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;
$expected_tables = array(
	'wp_vms_task_templates',
	'wp_vms_checklist_templates',
	'wp_vms_checklist_items',
	'wp_vms_task_instances',
	'wp_vms_task_logs',
);
$wpdb->get_var_queue = $expected_tables;
vms_test_assert_true(vms_tasks_db_ready(), 'DB readiness should return true when every required table probe matches exactly.');
vms_test_assert_same($expected_tables, $wpdb->esc_like_calls, 'DB readiness should escape each literal table name before SHOW TABLES LIKE probes.');
vms_test_assert_same(5, count(array_filter($wpdb->call_log, static fn(array $entry): bool => ($entry['kind'] ?? '') === 'get_var')), 'DB readiness should execute one get_var() probe per required table.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same('SHOW TABLES LIKE %s', $prepare['query'], 'DB readiness should keep the bounded SHOW TABLES LIKE probe.');
vms_test_assert_same(array(addcslashes('wp_vms_task_logs', '_%\\')), $prepare['args'], 'DB readiness should pass the escaped literal table name through prepare().');

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;
$wpdb->get_var_queue = array($expected_tables[0], 'not_the_checklist_templates_table');
vms_test_assert_true(!vms_tasks_db_ready(), 'DB readiness should fail closed when any required table probe misses.');

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;
vms_tasks_log_task_action(12, '!!!', null, '');
$insert = vms_test_last_call($wpdb, 'insert');
vms_test_assert_same('wp_vms_task_logs', $insert['table'], 'Task-action logs should target the task_logs repository table.');
vms_test_assert_same('unknown', $insert['data']['action'], 'Task-action logs should fall back to the bounded unknown action key.');
vms_test_assert_same(77, $insert['data']['actor_user_id'], 'Task-action logs should default the actor to the current user when one exists.');
vms_test_assert_same(null, $insert['data']['details'], 'Task-action logs should preserve null details for empty strings.');
vms_test_assert_same('2026-08-01 12:34:56', $insert['data']['created_at'], 'Task-action logs should retain the shared current-time helper contract.');

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;
$wpdb->get_var_queue[] = '99';
vms_test_assert_true(vms_tasks_has_task_action_log(12, 'Needs Review'), 'Task-action existence lookups should return true when a row is present.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same(
	'SELECT id FROM %i WHERE task_instance_id = %d AND action = %s LIMIT 1',
	$prepare['query'],
	'Task-action existence lookups should prepare the custom table, integer ID, and action key.'
);
vms_test_assert_same(array('wp_vms_task_logs', 12, 'needsreview'), $prepare['args'], 'Task-action existence lookups should normalize the action key before prepare().');
vms_test_assert_no_placeholders($prepare['final_sql'], 'Task-action existence lookups should not retain unresolved placeholders.');

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;
$wpdb->get_row_queue[] = array('id' => 14, 'title' => 'Load In');
vms_test_assert_same(array('id' => 14, 'title' => 'Load In'), vms_tasks_get_task_template(14), 'Single task-template reads should return the repository row as an associative array.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same('SELECT * FROM %i WHERE id = %d LIMIT 1', $prepare['query'], 'Single task-template reads should prepare the identifier and integer template ID.');
vms_test_assert_same(null, vms_tasks_get_task_template(0), 'Single task-template reads should fail closed on a non-positive template ID.');

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;
$wpdb->get_results_queue[] = array(array('id' => 1), array('id' => 2));
$rows = vms_tasks_get_task_templates(array('is_active' => 1, 'scope' => 'Calendar'));
vms_test_assert_same(array(array('id' => 1), array('id' => 2)), $rows, 'Task-template list repositories should return the row set unchanged when get_results() yields an array.');
$prepare = vms_test_last_prepare($wpdb);
	vms_test_assert_same(
		'SELECT * FROM %i WHERE 1=1 AND is_active = %d AND scope = %s ORDER BY is_active DESC, title ASC, id ASC',
		$prepare['query'],
		'Task-template lists should prepare the custom table and each accepted filter value.'
	);
	vms_test_assert_same(array('wp_vms_task_templates', 1, 'general'), $prepare['args'], 'Task-template list filters should normalize scope through the shared allowlist.');

	$wpdb = new VMS_Test_WPDB();
	$GLOBALS['wpdb'] = $wpdb;
	vms_tasks_get_task_templates(array('scope' => 'Calendar'));
	$prepare = vms_test_last_prepare($wpdb);
	vms_test_assert_same(
		'SELECT * FROM %i WHERE 1=1 AND scope = %s ORDER BY is_active DESC, title ASC, id ASC',
		$prepare['query'],
		'Task-template list scope-only reads should use the finite scope branch.'
	);
	vms_test_assert_same(array('wp_vms_task_templates', 'general'), $prepare['args'], 'Task-template list scope-only reads should normalize scope through the shared allowlist.');

	$wpdb = new VMS_Test_WPDB();
	$GLOBALS['wpdb'] = $wpdb;
	$wpdb->get_results_queue[] = false;
	vms_test_assert_same(array(), vms_tasks_get_task_templates(), 'Task-template lists should fail closed to an empty array on database failure.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same(
	'SELECT * FROM %i WHERE 1=1 ORDER BY is_active DESC, title ASC, id ASC',
	$prepare['query'],
	'Task-template lists without filters should still prepare the custom table identifier.'
);
vms_test_assert_same(array('wp_vms_task_templates'), $prepare['args'], 'Task-template lists without filters should pass only the table identifier to prepare().');

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;
$update_result = vms_tasks_upsert_task_template(
	array(
		'title' => ' Load In ',
		'instructions' => '<em>Doors</em>',
		'is_active' => 1,
		'priority' => 'HIGH',
		'required_default' => 0,
		'scope' => 'Calendar',
		'due_mode' => 'bad-mode',
		'due_offset_minutes' => '',
		'due_time_local' => '9:30',
		'assignment_mode' => 'scheduled_role',
		'role_key' => 'Lead Tech',
		'assignee_user_id' => 0,
	),
	9
);
vms_test_assert_same(9, $update_result, 'Task-template updates should return the existing template ID on success.');
$update = vms_test_last_call($wpdb, 'update');
vms_test_assert_same('wp_vms_task_templates', $update['table'], 'Task-template updates should target the task_templates repository table.');
vms_test_assert_same('Load In', $update['data']['title'], 'Task-template updates should trim and sanitize the title.');
vms_test_assert_same('general', $update['data']['scope'], 'Task-template updates should normalize the scope allowlist.');
vms_test_assert_same('none', $update['data']['due_mode'], 'Task-template updates should preserve the due-mode allowlist.');
vms_test_assert_same(null, $update['data']['due_time_local'], 'Task-template updates should clear malformed due_time_local values.');
vms_test_assert_same('leadtech', $update['data']['role_key'], 'Task-template updates should sanitize role keys before persistence.');
vms_test_assert_same(null, $update['data']['assignee_user_id'], 'Task-template updates should preserve null assignee IDs for non-positive input.');

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;
$wpdb->insert_id = 944;
$insert_result = vms_tasks_upsert_task_template(
	array(
		'title' => 'Soundcheck',
		'due_offset_minutes' => null,
	)
);
vms_test_assert_same(944, $insert_result, 'Task-template inserts should return the wpdb insert_id.');
$insert = vms_test_last_call($wpdb, 'insert');
vms_test_assert_same('wp_vms_task_templates', $insert['table'], 'Task-template inserts should target the task_templates repository table.');
vms_test_assert_true(isset($insert['data']['created_at']), 'Task-template inserts should preserve the created_at field.');

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;
$wpdb->get_row_queue[] = array('id' => 4, 'name' => 'Doors Checklist');
vms_test_assert_same(array('id' => 4, 'name' => 'Doors Checklist'), vms_tasks_get_checklist_template(4), 'Single checklist-template reads should return the repository row as an associative array.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same('SELECT * FROM %i WHERE id = %d LIMIT 1', $prepare['query'], 'Single checklist-template reads should prepare the identifier and integer checklist ID.');

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;
$wpdb->get_results_queue[] = array(array('id' => 7));
$rows = vms_tasks_get_checklist_templates(array('is_active' => 0, 'apply_mode' => 'bogus', 'scope' => 'Calendar'));
vms_test_assert_same(array(array('id' => 7)), $rows, 'Checklist-template list repositories should return the row set unchanged when get_results() yields an array.');
$prepare = vms_test_last_prepare($wpdb);
	vms_test_assert_same(
		'SELECT * FROM %i WHERE 1=1 AND is_active = %d AND apply_mode = %s AND scope = %s ORDER BY is_active DESC, priority_order ASC, id ASC',
		$prepare['query'],
		'Checklist-template lists should prepare the custom table and each accepted filter value.'
	);
	vms_test_assert_same(array('wp_vms_checklist_templates', 0, 'default_all_events', 'general'), $prepare['args'], 'Checklist-template lists should normalize apply_mode and scope through their shared allowlists.');

	$wpdb = new VMS_Test_WPDB();
	$GLOBALS['wpdb'] = $wpdb;
	vms_tasks_get_checklist_templates(array('apply_mode' => 'bogus'));
	$prepare = vms_test_last_prepare($wpdb);
	vms_test_assert_same(
		'SELECT * FROM %i WHERE 1=1 AND apply_mode = %s ORDER BY is_active DESC, priority_order ASC, id ASC',
		$prepare['query'],
		'Checklist-template list apply-mode-only reads should use the finite apply_mode branch.'
	);
	vms_test_assert_same(array('wp_vms_checklist_templates', 'default_all_events'), $prepare['args'], 'Checklist-template list apply-mode-only reads should normalize apply_mode through the shared allowlist.');

	$wpdb = new VMS_Test_WPDB();
	$GLOBALS['wpdb'] = $wpdb;
	$update_result = vms_tasks_upsert_checklist_template(
	array(
		'name' => ' Doors ',
		'is_active' => 1,
		'priority_order' => 5,
		'scope' => 'general',
		'apply_mode' => 'by_venue',
		'venue_id' => 12,
		'event_type' => 'Rock Show',
	),
	31
);
vms_test_assert_same(31, $update_result, 'Checklist-template updates should return the existing checklist ID on success.');
$update = vms_test_last_call($wpdb, 'update');
vms_test_assert_same('wp_vms_checklist_templates', $update['table'], 'Checklist-template updates should target the checklist_templates repository table.');
vms_test_assert_same('Doors', $update['data']['name'], 'Checklist-template updates should trim and sanitize the name.');
vms_test_assert_same('default_all_events', $update['data']['apply_mode'], 'General-scope checklist updates should force the default_all_events apply mode.');
vms_test_assert_same(null, $update['data']['venue_id'], 'General-scope checklist updates should null the venue_id field.');
vms_test_assert_same(null, $update['data']['event_type'], 'General-scope checklist updates should null the event_type field.');

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;
$wpdb->insert_id = 222;
$insert_result = vms_tasks_upsert_checklist_template(
	array(
		'name' => 'VIP',
		'apply_mode' => 'by_event_type',
		'event_type' => 'VIP Night',
	)
);
vms_test_assert_same(222, $insert_result, 'Checklist-template inserts should return the wpdb insert_id.');
$insert = vms_test_last_call($wpdb, 'insert');
vms_test_assert_same('vipnight', $insert['data']['event_type'], 'Checklist-template inserts should sanitize event_type before persistence.');

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;
$wpdb->get_row_queue = array(
	array('id' => 7, 'scope' => 'event'),
	array('id' => 21, 'scope' => 'event'),
	array('id' => 22, 'scope' => 'general'),
);
$items_result = vms_tasks_replace_checklist_items(
	7,
	array(
		array(
			'task_template_id' => 21,
			'sort_order' => 9,
			'overrides' => array(
				'required_default' => 0,
				'priority' => 'HIGH',
				'assignment_mode' => 'person',
				'role_key' => 'Lead Tech',
				'assignee_user_id' => '88',
				'due_offset_minutes' => '-30',
			),
		),
		array(
			'task_template_id' => 22,
			'overrides' => array(),
		),
	)
);
vms_test_assert_same(true, $items_result, 'Checklist-item replacement should succeed when the checklist and valid templates resolve.');
$execution_kinds = array_values(
	array_map(
		static fn(array $entry): string => (string) $entry['kind'],
		array_values(
			array_filter(
				$wpdb->call_log,
				static fn(array $entry): bool => ($entry['kind'] ?? '') !== 'prepare'
			)
		)
	)
);
vms_test_assert_same('get_row', $execution_kinds[0] ?? '', 'Checklist-item replacement should resolve the checklist repository row before mutating child rows.');
vms_test_assert_same('delete', $execution_kinds[1] ?? '', 'Checklist-item replacement should clear prior child rows before reinserting the normalized ordered set.');
$insert = vms_test_last_call($wpdb, 'insert');
vms_test_assert_same('wp_vms_checklist_items', $insert['table'], 'Checklist-item replacement should insert into the checklist_items repository table.');
vms_test_assert_same(9, $insert['data']['sort_order'], 'Checklist-item replacement should preserve the provided sort order.');
vms_test_assert_same(
	'{"required_default":0,"priority":"high","assignment_mode":"person","role_key":"leadtech","assignee_user_id":88,"due_offset_minutes":-30}',
	$insert['data']['overrides_json'],
	'Checklist-item replacement should JSON-encode the normalized override payload.'
);

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;
$wpdb->get_results_queue[] = array(
	array(
		'id' => 10,
		'overrides_json' => '{"priority":"high","required_default":0}',
		'template_title' => 'Load In',
	),
	array(
		'id' => 11,
		'overrides_json' => '{"priority":"urgent"}',
		'template_title' => 'Doors',
	),
);
$rows = vms_tasks_get_checklist_items(7);
vms_test_assert_same('Load In', $rows[0]['template_title'], 'Checklist-item joined reads should preserve the selected joined columns.');
vms_test_assert_same(array('priority' => 'high', 'required_default' => 0), $rows[0]['overrides'], 'Checklist-item joined reads should decode valid override payloads.');
vms_test_assert_same('valid', $rows[0]['overrides_state'], 'Checklist-item joined reads should expose the valid override state.');
vms_test_assert_same('invalid', $rows[1]['overrides_state'], 'Checklist-item joined reads should fail closed on malformed override payloads.');
vms_test_assert_same('priority_value', $rows[1]['overrides_reason'], 'Checklist-item joined reads should expose the invalid override reason.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same(
	'SELECT ci.*, tt.title AS template_title, tt.is_active AS template_active, tt.scope AS template_scope
				 FROM %i ci
				 LEFT JOIN %i tt ON tt.id = ci.task_template_id
				 WHERE ci.checklist_id = %d
				 ORDER BY ci.sort_order ASC, ci.id ASC',
	$prepare['query'],
	'Checklist-item joined reads should prepare both custom-table identifiers plus the checklist ID.'
);
vms_test_assert_same(array('wp_vms_checklist_items', 'wp_vms_task_templates', 7), $prepare['args'], 'Checklist-item joined reads should preserve identifier and ID argument order.');
vms_test_assert_contains('LEFT JOIN `wp_vms_task_templates` tt', $prepare['final_sql'], 'Checklist-item joined reads should preserve the LEFT JOIN contract.');
vms_test_assert_contains('ORDER BY ci.sort_order ASC, ci.id ASC', $prepare['final_sql'], 'Checklist-item joined reads should preserve stable ordering.');

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;
$wpdb->get_results_queue[] = false;
vms_test_assert_same(array(), vms_tasks_get_checklist_items(7), 'Checklist-item joined reads should fail closed to an empty array on database failure.');

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;
$wpdb->get_results_queue[] = array(array('id' => 1));
$rows = vms_tasks_get_applicable_checklists(0, '');
vms_test_assert_same(array(array('id' => 1)), $rows, 'Applicable checklist selection should return the row set unchanged when get_results() yields an array.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same(
	'SELECT * FROM %i WHERE is_active = 1 AND scope = \'event\' AND (apply_mode = \'default_all_events\') ORDER BY priority_order ASC, id ASC',
	$prepare['query'],
	'Default-only applicable checklist selection should keep the fixed default_all_events branch.'
);
vms_test_assert_same(array('wp_vms_checklist_templates'), $prepare['args'], 'Default-only applicable checklist selection should prepare only the table identifier.');

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;
$wpdb->get_results_queue[] = array();
vms_tasks_get_applicable_checklists(15, '');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same(array('wp_vms_checklist_templates', 'by_venue', 15), $prepare['args'], 'Venue-specific applicable checklist selection should preserve identifier, mode, then venue ID ordering.');
vms_test_assert_contains("(apply_mode = 'by_venue' AND venue_id = 15)", $prepare['final_sql'], 'Venue-specific applicable checklist selection should preserve the venue branch.');

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;
$wpdb->get_results_queue[] = array();
vms_tasks_get_applicable_checklists(0, 'VIP Night');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same(array('wp_vms_checklist_templates', 'by_event_type', 'vipnight'), $prepare['args'], 'Event-type applicable checklist selection should sanitize the event_type before prepare().');
vms_test_assert_contains("(apply_mode = 'by_event_type' AND event_type = 'vipnight')", $prepare['final_sql'], 'Event-type applicable checklist selection should preserve the event-type branch.');

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;
$wpdb->get_results_queue[] = array();
vms_tasks_get_applicable_checklists(15, 'VIP Night');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same(
	array('wp_vms_checklist_templates', 'by_venue', 15, 'by_event_type', 'vipnight'),
	$prepare['args'],
	'Combined applicable checklist selection should preserve identifier, venue branch, then event-type branch ordering.'
);
vms_test_assert_contains("apply_mode = 'default_all_events' OR (apply_mode = 'by_venue' AND venue_id = 15) OR (apply_mode = 'by_event_type' AND event_type = 'vipnight')", $prepare['final_sql'], 'Combined applicable checklist selection should preserve the exact OR-branch contract.');
vms_test_assert_no_placeholders($prepare['final_sql'], 'Applicable checklist selection final SQL should not retain unresolved placeholders.');

echo "staff tasks repository sql remediation: PASS\n";
