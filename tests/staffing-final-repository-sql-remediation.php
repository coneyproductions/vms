<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

if (!defined('ARRAY_A')) {
	define('ARRAY_A', 'ARRAY_A');
}

if (!defined('DAY_IN_SECONDS')) {
	define('DAY_IN_SECONDS', 86400);
}

final class WP_Post
{
	public int $ID = 0;
	public string $post_type = '';
	public string $post_status = 'publish';
	public string $post_title = '';

	/** @param array<string,mixed> $args */
	public function __construct(array $args = array())
	{
		foreach ($args as $key => $value) {
			if (property_exists($this, $key)) {
				$this->{$key} = is_int($this->{$key}) ? (int) $value : (string) $value;
			}
		}
	}
}

final class WP_User
{
	public int $ID = 0;
	public string $display_name = '';
	public string $user_email = '';

	/** @param array<string,mixed> $args */
	public function __construct(array $args = array())
	{
		foreach ($args as $key => $value) {
			if (property_exists($this, $key)) {
				$this->{$key} = is_int($this->{$key}) ? (int) $value : (string) $value;
			}
		}
	}
}

final class VMS_Test_WPDB
{
	public string $prefix = 'wp_';
	public string $posts = 'wp_posts';
	public string $postmeta = 'wp_postmeta';
	public string $usermeta = 'wp_usermeta';
	/** @var array<int,array<string,mixed>> */
	public array $call_log = array();
	/** @var array<int,array{query:string,args:array<int,mixed>,final_sql:string}> */
	public array $prepare_calls = array();
	/** @var array<int,mixed> */
	public array $get_col_queue = array();
	/** @var array<int,mixed> */
	public array $get_var_queue = array();

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

$GLOBALS['vms_test_actions'] = array();
$GLOBALS['vms_test_filters'] = array();

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

function esc_url($text): string
{
	return (string) $text;
}

function selected($selected, $current, bool $display = false): string
{
	unset($display);
	return $selected === $current ? 'selected="selected"' : '';
}

function wp_nonce_field(string $action = '', string $name = '_wpnonce', bool $referer = true, bool $display = true): void
{
	unset($action, $name, $referer, $display);
}

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
{
	if (!isset($GLOBALS['vms_test_actions'][$hook])) {
		$GLOBALS['vms_test_actions'][$hook] = array();
	}
	if (!isset($GLOBALS['vms_test_actions'][$hook][$priority])) {
		$GLOBALS['vms_test_actions'][$hook][$priority] = array();
	}
	$GLOBALS['vms_test_actions'][$hook][$priority][] = array(
		'callback' => $callback,
		'accepted_args' => $accepted_args,
	);

	return true;
}

function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
{
	if (!isset($GLOBALS['vms_test_filters'][$hook])) {
		$GLOBALS['vms_test_filters'][$hook] = array();
	}
	if (!isset($GLOBALS['vms_test_filters'][$hook][$priority])) {
		$GLOBALS['vms_test_filters'][$hook][$priority] = array();
	}
	$GLOBALS['vms_test_filters'][$hook][$priority][] = array(
		'callback' => $callback,
		'accepted_args' => $accepted_args,
	);

	return true;
}

function sanitize_text_field($value): string
{
	return trim(strip_tags((string) $value));
}

function sanitize_email($value): string
{
	return trim((string) $value);
}

/** @return mixed */
function wp_unslash($value)
{
	if (is_array($value)) {
		return array_map('wp_unslash', $value);
	}

	return is_string($value) ? stripslashes($value) : $value;
}

function wp_verify_nonce(string $nonce, string $action): bool
{
	unset($action);
	return $nonce !== '';
}

function current_user_can(string $capability, ...$args): bool
{
	unset($capability, $args);
	return true;
}

function wp_is_post_revision(int $post_id): bool
{
	unset($post_id);
	return false;
}

function get_post_meta(int $post_id, string $key, bool $single = true)
{
	unset($single);
	return $GLOBALS['vms_test_post_meta'][$post_id][$key] ?? '';
}

function update_post_meta(int $post_id, string $key, $value): void
{
	$GLOBALS['vms_test_post_meta'][$post_id][$key] = $value;
	$GLOBALS['vms_test_post_meta_updates'][] = array(
		'post_id' => $post_id,
		'key' => $key,
		'value' => $value,
	);
}

function delete_post_meta(int $post_id, string $key): void
{
	unset($GLOBALS['vms_test_post_meta'][$post_id][$key]);
	$GLOBALS['vms_test_post_meta_deletes'][] = array(
		'post_id' => $post_id,
		'key' => $key,
	);
}

function get_user_meta(int $user_id, string $key, bool $single = true)
{
	unset($single);
	return $GLOBALS['vms_test_user_meta'][$user_id][$key] ?? '';
}

function update_user_meta(int $user_id, string $key, $value): void
{
	$GLOBALS['vms_test_user_meta'][$user_id][$key] = $value;
	$GLOBALS['vms_test_user_meta_updates'][] = array(
		'user_id' => $user_id,
		'key' => $key,
		'value' => $value,
	);
}

function delete_user_meta(int $user_id, string $key): void
{
	unset($GLOBALS['vms_test_user_meta'][$user_id][$key]);
	$GLOBALS['vms_test_user_meta_deletes'][] = array(
		'user_id' => $user_id,
		'key' => $key,
	);
}

function get_user_by(string $field, $value)
{
	if ($field === 'id') {
		$user_id = (int) $value;
		return $GLOBALS['vms_test_users'][$user_id] ?? false;
	}
	if ($field === 'email') {
		foreach ($GLOBALS['vms_test_users'] as $user) {
			if ($user instanceof WP_User && $user->user_email === (string) $value) {
				return $user;
			}
		}
	}

	return false;
}

function get_users(array $args = array()): array
{
	$GLOBALS['vms_test_get_users_calls'][] = $args;
	return $GLOBALS['vms_test_get_users_result'];
}

function get_post(int $post_id)
{
	return $GLOBALS['vms_test_posts'][$post_id] ?? null;
}

function get_the_title(int $post_id): string
{
	$post = get_post($post_id);
	if ($post instanceof WP_Post) {
		return $post->post_title;
	}

	return $GLOBALS['vms_test_titles'][$post_id] ?? '';
}

function get_edit_post_link(int $post_id, string $context = ''): string
{
	unset($context);
	return 'https://example.test/wp-admin/post.php?post=' . $post_id . '&action=edit';
}

function get_posts(array $args = array()): array
{
	$GLOBALS['vms_test_get_posts_calls'][] = $args;
	if ($GLOBALS['vms_test_get_posts_queue'] === array()) {
		return array();
	}

	return array_shift($GLOBALS['vms_test_get_posts_queue']);
}

function absint($value): int
{
	return abs((int) $value);
}

function vms_test_today_ymd(): string
{
	return '2026-08-03';
}

function vms_test_horizon_ymd(int $days): string
{
	return (new DateTimeImmutable(vms_test_today_ymd(), new DateTimeZone('UTC')))->modify('+' . $days . ' days')->format('Y-m-d');
}

function wp_date(string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null): string
{
	if ($timestamp === null) {
		if ($format === 'Y-m-d') {
			return vms_test_today_ymd();
		}

		return vms_test_today_ymd() . ' 12:00:00';
	}

	$timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone('UTC');
	return (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)->format($format);
}

function wp_timezone(): DateTimeZone
{
	return new DateTimeZone('UTC');
}

function vms_meta_key(string $scope, string $key): string
{
	return $GLOBALS['vms_test_meta_keys'][$scope . ':' . $key] ?? '';
}

/** @return array<int,string> */
function vms_tasks_admin_get_venues(): array
{
	return $GLOBALS['vms_test_venues'];
}

/** @return array<string,mixed>|null */
function vms_tasks_get_event_context(int $event_id): ?array
{
	return $GLOBALS['vms_test_event_contexts'][$event_id] ?? null;
}

function vms_event_plan_should_include(int $plan_id, string $context, array $args = array()): bool
{
	$GLOBALS['vms_test_event_plan_should_include_calls'][] = array(
		'plan_id' => $plan_id,
		'context' => $context,
		'args' => $args,
	);

	return $GLOBALS['vms_test_event_plan_should_include'][$plan_id] ?? true;
}

function vms_staffing_get_rollup(int $plan_id)
{
	return $GLOBALS['vms_test_rollups'][$plan_id] ?? null;
}

function vms_staffing_compute_rollup(int $plan_id): array
{
	$GLOBALS['vms_test_compute_rollup_calls'][] = $plan_id;
	if (isset($GLOBALS['vms_test_rollup_recompute'][$plan_id])) {
		$GLOBALS['vms_test_rollups'][$plan_id] = $GLOBALS['vms_test_rollup_recompute'][$plan_id];
	}

	$rollup = $GLOBALS['vms_test_rollups'][$plan_id] ?? null;
	return is_array($rollup) ? $rollup : array('ok' => false, 'error' => 'missing_rollup');
}

function vms_staffing_dashboard_readiness_label(string $status): string
{
	return strtoupper($status);
}

function vms_test_fail(string $message): void
{
	throw new RuntimeException($message);
}

function vms_test_assert_true(bool $condition, string $message): void
{
	if (!$condition) {
		vms_test_fail($message);
	}
}

function vms_test_assert_same($expected, $actual, string $message): void
{
	if ($expected !== $actual) {
		vms_test_fail(
			$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
		);
	}
}

function vms_test_assert_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert_true(strpos($haystack, $needle) !== false, $message);
}

function vms_test_assert_not_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert_true(strpos($haystack, $needle) === false, $message);
}

function vms_test_normalize_sql(string $sql): string
{
	$normalized = preg_replace('/\s+/', ' ', trim($sql));
	if (!is_string($normalized)) {
		vms_test_fail('Unable to normalize SQL.');
	}

	return $normalized;
}

function vms_test_find_matching_brace(string $source, int $brace_pos): int
{
	$depth = 0;
	$length = strlen($source);
	$in_single = false;
	$in_double = false;
	$in_line_comment = false;
	$in_block_comment = false;
	$previous_char = '';

	for ($i = $brace_pos; $i < $length; $i++) {
		$char = $source[$i];
		$next_char = ($i + 1 < $length) ? $source[$i + 1] : '';

		if ($in_line_comment) {
			if ($char === "\n") {
				$in_line_comment = false;
			}
			$previous_char = $char;
			continue;
		}
		if ($in_block_comment) {
			if ($char === '*' && $next_char === '/') {
				$in_block_comment = false;
				$i++;
				$previous_char = '/';
				continue;
			}
			$previous_char = $char;
			continue;
		}
		if ($in_single) {
			if ($char === "'" && $previous_char !== '\\') {
				$in_single = false;
			}
			$previous_char = $char;
			continue;
		}
		if ($in_double) {
			if ($char === '"' && $previous_char !== '\\') {
				$in_double = false;
			}
			$previous_char = $char;
			continue;
		}

		if ($char === '/' && $next_char === '/') {
			$in_line_comment = true;
			$i++;
			$previous_char = '/';
			continue;
		}
		if ($char === '/' && $next_char === '*') {
			$in_block_comment = true;
			$i++;
			$previous_char = '*';
			continue;
		}
		if ($char === "'") {
			$in_single = true;
			$previous_char = $char;
			continue;
		}
		if ($char === '"') {
			$in_double = true;
			$previous_char = $char;
			continue;
		}

		if ($char === '{') {
			$depth++;
		} elseif ($char === '}') {
			$depth--;
			if ($depth === 0) {
				return $i;
			}
		}

		$previous_char = $char;
	}

	throw new RuntimeException('Unable to locate matching brace.');
}

function vms_test_extract_function(string $source, string $name): string
{
	$needle = 'function ' . $name . '(';
	$start = strpos($source, $needle);
	if ($start === false) {
		throw new RuntimeException('Unable to locate function ' . $name . '.');
	}
	$brace = strpos($source, '{', $start);
	if ($brace === false) {
		throw new RuntimeException('Unable to locate opening brace for ' . $name . '.');
	}
	$end = vms_test_find_matching_brace($source, $brace);

	return substr($source, $start, $end - $start + 1);
}

function vms_test_extract_inline_closure(string $source, string $marker): string
{
	$marker_pos = strpos($source, $marker);
	if ($marker_pos === false) {
		throw new RuntimeException('Marker not found: ' . $marker);
	}
	$function_pos = strpos($source, 'function', $marker_pos);
	if ($function_pos === false) {
		throw new RuntimeException('Closure not found for marker: ' . $marker);
	}
	$brace = strpos($source, '{', $function_pos);
	if ($brace === false) {
		throw new RuntimeException('Closure brace not found for marker: ' . $marker);
	}
	$end = vms_test_find_matching_brace($source, $brace);

	return substr($source, $function_pos, $end - $function_pos + 1);
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

function vms_test_reset_runtime(): VMS_Test_WPDB
{
	$wpdb = new VMS_Test_WPDB();
	$GLOBALS['wpdb'] = $wpdb;
	$GLOBALS['vms_test_post_meta'] = array();
	$GLOBALS['vms_test_user_meta'] = array();
	$GLOBALS['vms_test_post_meta_updates'] = array();
	$GLOBALS['vms_test_post_meta_deletes'] = array();
	$GLOBALS['vms_test_user_meta_updates'] = array();
	$GLOBALS['vms_test_user_meta_deletes'] = array();
	$GLOBALS['vms_test_users'] = array();
	$GLOBALS['vms_test_posts'] = array();
	$GLOBALS['vms_test_titles'] = array();
	$GLOBALS['vms_test_get_users_calls'] = array();
	$GLOBALS['vms_test_get_users_result'] = array();
	$GLOBALS['vms_test_get_posts_calls'] = array();
	$GLOBALS['vms_test_get_posts_queue'] = array();
	$GLOBALS['vms_test_meta_keys'] = array(
		'event_plan:date' => '_vms_event_date',
	);
	$GLOBALS['vms_test_venues'] = array();
	$GLOBALS['vms_test_event_contexts'] = array();
	$GLOBALS['vms_test_event_plan_should_include'] = array();
	$GLOBALS['vms_test_event_plan_should_include_calls'] = array();
	$GLOBALS['vms_test_rollups'] = array();
	$GLOBALS['vms_test_rollup_recompute'] = array();
	$GLOBALS['vms_test_compute_rollup_calls'] = array();

	return $wpdb;
}

$plugin_root = dirname(__DIR__);
$live_plugin_root = dirname($plugin_root, 2) . '/vms';

$store_path = $plugin_root . '/includes/modules/staff-tasks/store.php';
$db_path = $plugin_root . '/includes/modules/staff-tasks/db.php';
$admin_ui_path = $plugin_root . '/includes/modules/staff-tasks/admin-ui.php';
$generator_path = $plugin_root . '/includes/modules/staff-tasks/generator.php';
$staff_portal_path = $plugin_root . '/includes/portal/staff-portal.php';
$core_staffing_path = $plugin_root . '/includes/core/staffing.php';
$admin_staffing_path = $plugin_root . '/includes/admin/staffing.php';
$staff_list_columns_path = $plugin_root . '/includes/admin/staff-list-columns.php';
$staff_user_link_path = $plugin_root . '/includes/admin/staff-user-link.php';
$staff_vendor_link_path = $plugin_root . '/includes/admin/staff-vendor-link.php';
$vendor_staff_link_path = $plugin_root . '/includes/admin/vendor-staff-link.php';

$live_admin_ui_path = $live_plugin_root . '/includes/modules/staff-tasks/admin-ui.php';
$live_generator_path = $live_plugin_root . '/includes/modules/staff-tasks/generator.php';
$live_core_staffing_path = $live_plugin_root . '/includes/core/staffing.php';
$live_staff_list_columns_path = $live_plugin_root . '/includes/admin/staff-list-columns.php';
$live_staff_user_link_path = $live_plugin_root . '/includes/admin/staff-user-link.php';
$live_staff_vendor_link_path = $live_plugin_root . '/includes/admin/staff-vendor-link.php';
$live_vendor_staff_link_path = $live_plugin_root . '/includes/admin/vendor-staff-link.php';

$store_source = (string) file_get_contents($store_path);
$db_source = (string) file_get_contents($db_path);
$admin_ui_source = (string) file_get_contents($admin_ui_path);
$generator_source = (string) file_get_contents($generator_path);
$staff_portal_source = (string) file_get_contents($staff_portal_path);
$core_staffing_source = (string) file_get_contents($core_staffing_path);
$admin_staffing_source = (string) file_get_contents($admin_staffing_path);
$staff_list_columns_source = (string) file_get_contents($staff_list_columns_path);
$staff_user_link_source = (string) file_get_contents($staff_user_link_path);
$staff_vendor_link_source = (string) file_get_contents($staff_vendor_link_path);
$vendor_staff_link_source = (string) file_get_contents($vendor_staff_link_path);

$live_admin_ui_source = (string) file_get_contents($live_admin_ui_path);
$live_generator_source = (string) file_get_contents($live_generator_path);
$live_core_staffing_source = (string) file_get_contents($live_core_staffing_path);
$live_staff_list_columns_source = (string) file_get_contents($live_staff_list_columns_path);
$live_staff_user_link_source = (string) file_get_contents($live_staff_user_link_path);
$live_staff_vendor_link_source = (string) file_get_contents($live_staff_vendor_link_path);
$live_vendor_staff_link_source = (string) file_get_contents($live_vendor_staff_link_path);

require_once $staff_list_columns_path;
require_once $staff_user_link_path;
require_once $vendor_staff_link_path;
require_once $staff_vendor_link_path;

eval(vms_test_extract_function($core_staffing_source, 'vms_staffing_get_staff_user'));
eval(vms_test_extract_function($core_staffing_source, 'vms_staffing_build_dashboard_response'));
eval(vms_test_extract_function($core_staffing_source, 'vms_staffing_collect_rebuild_plan_ids'));
eval(vms_test_extract_function($admin_ui_source, 'vms_tasks_admin_get_event_options'));

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
	'includes/admin/staff-list-columns.php:69:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/admin/staff-user-link.php:131:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/admin/staff-vendor-link.php:146:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/admin/vendor-staff-link.php:155:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/modules/staff-tasks/generator.php:590:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
);

try {
	$actual_inventory = vms_test_collect_db_phpcs_inventory(
		array(
			$store_path,
			$admin_ui_path,
			$db_path,
			$staff_portal_path,
			$core_staffing_path,
			$admin_staffing_path,
			$staff_list_columns_path,
			$staff_user_link_path,
			$staff_vendor_link_path,
			$vendor_staff_link_path,
			$generator_path,
		)
	);
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
	$invented_inventory[] = 'includes/admin/vendor-staff-link.php:999999:WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching';
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

	foreach (array(
		$staff_list_columns_source,
		$staff_user_link_source,
		$staff_vendor_link_source,
		$vendor_staff_link_source,
		$core_staffing_source,
		$admin_ui_source,
		$generator_source,
	) as $source) {
		vms_test_assert_true(strpos($source, 'phpcs:disable') === false, 'No T5 runtime source should introduce a file-level or block-level PHPCS disable.');
	}

	vms_test_assert_same(
		hash('sha256', $staff_list_columns_source),
		hash('sha256', $live_staff_list_columns_source),
		'Mirror/live staff-list-columns.php should remain byte-identical.'
	);
	vms_test_assert_same(
		hash('sha256', $staff_user_link_source),
		hash('sha256', $live_staff_user_link_source),
		'Mirror/live staff-user-link.php should remain byte-identical.'
	);
	vms_test_assert_same(
		hash('sha256', $staff_vendor_link_source),
		hash('sha256', $live_staff_vendor_link_source),
		'Mirror/live staff-vendor-link.php should remain byte-identical.'
	);
	vms_test_assert_same(
		hash('sha256', $generator_source),
		hash('sha256', $live_generator_source),
		'Mirror/live staff-tasks generator should remain byte-identical.'
	);

	vms_test_assert_same(
		vms_test_collect_target_hashes(
			$core_staffing_source,
			array(
				'vms_staffing_get_staff_user',
				'vms_staffing_build_dashboard_response',
				'vms_staffing_collect_rebuild_plan_ids',
			)
		),
		vms_test_collect_target_hashes(
			$live_core_staffing_source,
			array(
				'vms_staffing_get_staff_user',
				'vms_staffing_build_dashboard_response',
				'vms_staffing_collect_rebuild_plan_ids',
			)
		),
		'Mirror/live core staffing T5 targets should remain byte-identical.'
	);
	vms_test_assert_true(
		hash('sha256', $core_staffing_source) !== hash('sha256', $live_core_staffing_source),
		'Mirror/live core staffing should retain authorized whole-file divergence while the T5 functions stay aligned.'
	);

	vms_test_assert_same(
		vms_test_collect_target_hashes($admin_ui_source, array('vms_tasks_admin_get_event_options')),
		vms_test_collect_target_hashes($live_admin_ui_source, array('vms_tasks_admin_get_event_options')),
		'Mirror/live admin-ui T5 target should remain byte-identical.'
	);
	vms_test_assert_true(
		hash('sha256', $admin_ui_source) !== hash('sha256', $live_admin_ui_source),
		'Mirror/live admin-ui should retain authorized whole-file divergence while the T5 function stays aligned.'
	);

	$vendor_staff_marker = "add_action('save_post_vms_vendor', function (int \$post_id, WP_Post \$post, bool \$update): void {";
	vms_test_assert_same(
		hash('sha256', vms_test_extract_inline_closure($vendor_staff_link_source, $vendor_staff_marker)),
		hash('sha256', vms_test_extract_inline_closure($live_vendor_staff_link_source, $vendor_staff_marker)),
		'Mirror/live vendor-staff save_post closure should remain byte-identical.'
	);
	vms_test_assert_true(
		hash('sha256', $vendor_staff_link_source) !== hash('sha256', $live_vendor_staff_link_source),
		'Mirror/live vendor-staff link should retain authorized whole-file divergence while the T5 closure stays aligned.'
	);

	$list_link_source = vms_test_extract_function($staff_list_columns_source, 'vms_staff_admin_list_linked_user_id');
	$user_link_save_source = vms_test_extract_inline_closure($staff_user_link_source, "add_action('save_post_vms_staff', function (int \$post_id, WP_Post \$post, bool \$update): void {");
	$staff_vendor_save_source = vms_test_extract_inline_closure($staff_vendor_link_source, "add_action('save_post_vms_staff', function (int \$post_id, WP_Post \$post, bool \$update): void {");
	$vendor_staff_save_source = vms_test_extract_inline_closure($vendor_staff_link_source, $vendor_staff_marker);
	$staff_user_source = vms_test_extract_function($core_staffing_source, 'vms_staffing_get_staff_user');
	$dashboard_source = vms_test_extract_function($core_staffing_source, 'vms_staffing_build_dashboard_response');
	$rebuild_source = vms_test_extract_function($core_staffing_source, 'vms_staffing_collect_rebuild_plan_ids');
	$event_options_source = vms_test_extract_function($admin_ui_source, 'vms_tasks_admin_get_event_options');

	vms_test_assert_contains('SELECT user_id FROM %i WHERE meta_key = %s AND meta_value = %s ORDER BY umeta_id ASC LIMIT 1', $list_link_source, 'Linked-user admin fallback should read usermeta through prepared SQL.');
	vms_test_assert_not_contains('get_users(array(', $list_link_source, 'Linked-user admin fallback should not reintroduce a get_users() meta query.');

	vms_test_assert_contains('SELECT pm.post_id FROM %i AS pm INNER JOIN %i AS p ON p.ID = pm.post_id', $user_link_save_source, 'Staff/user save hook should query duplicate reverse links through prepared postmeta SQL.');
	vms_test_assert_not_contains("'meta_query'", $user_link_save_source, 'Staff/user save hook should not reintroduce a meta_query duplicate scan.');

	vms_test_assert_contains('SELECT pm.post_id FROM %i AS pm INNER JOIN %i AS p ON p.ID = pm.post_id', $staff_vendor_save_source, 'Staff/vendor save hook should query duplicate reverse links through prepared postmeta SQL.');
	vms_test_assert_not_contains("'meta_query'", $staff_vendor_save_source, 'Staff/vendor save hook should not reintroduce a meta_query duplicate scan.');

	vms_test_assert_contains('SELECT pm.post_id FROM %i AS pm INNER JOIN %i AS p ON p.ID = pm.post_id', $vendor_staff_save_source, 'Vendor/staff save hook should query duplicate reverse links through prepared postmeta SQL.');
	vms_test_assert_not_contains("'meta_query'", $vendor_staff_save_source, 'Vendor/staff save hook should not reintroduce a meta_query duplicate scan.');

	vms_test_assert_contains('SELECT user_id FROM %i WHERE meta_key = %s AND meta_value = %s ORDER BY umeta_id ASC LIMIT 1', $staff_user_source, 'Staffing user lookup should read usermeta through prepared SQL.');
	vms_test_assert_not_contains('get_users(array(', $staff_user_source, 'Staffing user lookup should not reintroduce a get_users() fallback.');

	vms_test_assert_contains('SELECT p.ID FROM %i AS pm INNER JOIN %i AS p ON p.ID = pm.post_id', $dashboard_source, 'Dashboard candidate collection should query event-date postmeta through prepared SQL.');
	vms_test_assert_not_contains('get_posts(array(', $dashboard_source, 'Dashboard candidate collection should not reintroduce a get_posts() meta query.');
	vms_test_assert_not_contains("'meta_query'", $dashboard_source, 'Dashboard candidate collection should not reintroduce a meta_query clause.');

	vms_test_assert_contains('LEFT JOIN %i AS venue_meta', $rebuild_source, 'Rebuild candidate collection should keep the optional venue postmeta join inside the literal prepared SQL.');
	vms_test_assert_not_contains('get_posts($qargs)', $rebuild_source, 'Rebuild candidate collection should not reintroduce a get_posts() meta query.');
	vms_test_assert_not_contains("'meta_query'", $rebuild_source, 'Rebuild candidate collection should not reintroduce a meta_query clause.');

	vms_test_assert_contains("'orderby' => 'ID'", $event_options_source, 'Admin event fallback should use ID ordering before PHP sorting.');
	vms_test_assert_contains('usort($event_ids, static function (int $left, int $right) use ($event_dates): int {', $event_options_source, 'Admin event fallback should sort by cached event-date values in PHP.');
	vms_test_assert_not_contains("'meta_key' => \$k_date", $event_options_source, 'Admin event fallback should not order directly by meta_key.');
	vms_test_assert_not_contains("'orderby' => 'meta_value'", $event_options_source, 'Admin event fallback should not order directly by meta_value.');
	vms_test_assert_not_contains("'meta_query'", $event_options_source, 'Admin event fallback should not reintroduce a meta_query clause.');

	vms_test_assert_contains('SELECT p.ID FROM %i AS pm INNER JOIN %i AS p ON p.ID = pm.post_id', $generator_source, 'Generator horizon reads should query event-date postmeta through prepared SQL.');
	vms_test_assert_not_contains('get_posts(array(', $generator_source, 'Generator horizon reads should not reintroduce a get_posts() meta query.');
	vms_test_assert_not_contains("'meta_query'", $generator_source, 'Generator horizon reads should not reintroduce a meta_query clause.');

	$staff_user_callbacks = $GLOBALS['vms_test_actions']['save_post_vms_staff'][20] ?? array();
	vms_test_assert_same(2, count($staff_user_callbacks), 'Staff save hook registrations should contain the user-link and vendor-link callbacks at priority 20.');
	$staff_user_save_callback = $staff_user_callbacks[0]['callback'];
	$staff_vendor_save_callback = $staff_user_callbacks[1]['callback'];
	$vendor_staff_save_callback = $GLOBALS['vms_test_actions']['save_post_vms_vendor'][20][0]['callback'] ?? null;
	vms_test_assert_true(is_callable($staff_user_save_callback), 'Staff/user save callback should be callable.');
	vms_test_assert_true(is_callable($staff_vendor_save_callback), 'Staff/vendor save callback should be callable.');
	vms_test_assert_true(is_callable($vendor_staff_save_callback), 'Vendor/staff save callback should be callable.');

	$wpdb = vms_test_reset_runtime();
	$GLOBALS['vms_test_post_meta'][12]['_vms_linked_user_id'] = 44;
	vms_test_assert_same(44, vms_staff_admin_list_linked_user_id(12), 'Linked-user admin helper should prefer the normalized cached forward pointer.');
	vms_test_assert_same(array(), $wpdb->prepare_calls, 'Linked-user admin helper should not query usermeta when the cached forward pointer exists.');

	$wpdb = vms_test_reset_runtime();
	$wpdb->get_var_queue = array(71);
	vms_test_assert_same(71, vms_staff_admin_list_linked_user_id(12), 'Linked-user admin helper should fall back to the prepared reverse usermeta pointer.');
	vms_test_assert_same(array(), $GLOBALS['vms_test_get_users_calls'], 'Linked-user admin helper should not fall back to get_users() for reverse lookups.');
	$prepare = vms_test_find_prepare($wpdb, 'SELECT user_id FROM %i WHERE meta_key = %s AND meta_value = %s ORDER BY umeta_id ASC LIMIT 1');
	vms_test_assert_same(array('wp_usermeta', '_vms_staff_id', '12'), $prepare['args'], 'Linked-user admin helper should prepare the usermeta table, reverse-link meta key, and staff ID string.');
	vms_test_assert_no_placeholders($prepare['final_sql'], 'Linked-user admin helper final SQL should not retain placeholders.');
	vms_test_assert_no_placeholders($wpdb->call_log[count($wpdb->call_log) - 1]['query'], 'Linked-user admin helper execution SQL should not retain placeholders.');

	$wpdb = vms_test_reset_runtime();
	$wpdb->get_col_queue = array(array(33, 71, 72));
	$GLOBALS['vms_test_users'][55] = new WP_User(array('ID' => 55, 'display_name' => 'Pat User'));
	$GLOBALS['vms_test_user_meta'][55]['_vms_staff_id'] = 91;
	$_POST = array(
		'vms_staff_user_link_nonce' => 'nonce',
		'vms_linked_user_id' => '55',
	);
	$staff_user_post = new WP_Post(array('ID' => 33, 'post_type' => 'vms_staff'));
	$staff_user_save_callback(33, $staff_user_post, true);
	$prepare = vms_test_find_prepare($wpdb, 'SELECT pm.post_id FROM %i AS pm INNER JOIN %i AS p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_type = %s ORDER BY pm.meta_id ASC');
	vms_test_assert_same(array('wp_postmeta', 'wp_posts', '_vms_linked_user_id', '55', 'vms_staff'), $prepare['args'], 'Staff/user save hook should prepare postmeta/posts identifiers plus the target user filter.');
	vms_test_assert_no_placeholders($prepare['final_sql'], 'Staff/user save hook final SQL should not retain placeholders.');
	vms_test_assert_no_placeholders($wpdb->call_log[count($wpdb->call_log) - 1]['query'], 'Staff/user save hook execution SQL should not retain placeholders.');
	vms_test_assert_same(
		array(
			array('post_id' => 91, 'key' => '_vms_linked_user_id'),
			array('post_id' => 71, 'key' => '_vms_linked_user_id'),
			array('post_id' => 72, 'key' => '_vms_linked_user_id'),
		),
		$GLOBALS['vms_test_post_meta_deletes'],
		'Staff/user save hook should unlink prior duplicate staff rows while preserving the current staff ID.'
	);
	vms_test_assert_same(55, $GLOBALS['vms_test_post_meta'][33]['_vms_linked_user_id'], 'Staff/user save hook should update the forward staff-to-user pointer.');
	vms_test_assert_same(33, $GLOBALS['vms_test_user_meta'][55]['_vms_staff_id'], 'Staff/user save hook should update the reverse user-to-staff pointer.');

	$wpdb = vms_test_reset_runtime();
	$wpdb->get_col_queue = array(array(40, 77, 78));
	$GLOBALS['vms_test_posts'][60] = new WP_Post(array('ID' => 60, 'post_type' => 'vms_vendor', 'post_title' => 'Vendor 60'));
	$GLOBALS['vms_test_post_meta'][60]['_vms_linked_staff_id'] = 95;
	$_POST = array(
		'vms_staff_vendor_link_nonce' => 'nonce',
		'vms_linked_vendor_id' => '60',
	);
	$staff_vendor_post = new WP_Post(array('ID' => 40, 'post_type' => 'vms_staff'));
	$staff_vendor_save_callback(40, $staff_vendor_post, true);
	$prepare = vms_test_find_prepare($wpdb, 'SELECT pm.post_id FROM %i AS pm INNER JOIN %i AS p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_type = %s ORDER BY pm.meta_id ASC');
	vms_test_assert_same(array('wp_postmeta', 'wp_posts', '_vms_linked_vendor_id', '60', 'vms_staff'), $prepare['args'], 'Staff/vendor save hook should prepare postmeta/posts identifiers plus the target vendor filter.');
	vms_test_assert_no_placeholders($prepare['final_sql'], 'Staff/vendor save hook final SQL should not retain placeholders.');
	vms_test_assert_same(
		array(
			array('post_id' => 77, 'key' => '_vms_linked_vendor_id'),
			array('post_id' => 78, 'key' => '_vms_linked_vendor_id'),
			array('post_id' => 95, 'key' => '_vms_linked_vendor_id'),
		),
		$GLOBALS['vms_test_post_meta_deletes'],
		'Staff/vendor save hook should unlink duplicate staff rows plus any reverse-linked prior staff.'
	);
	vms_test_assert_same(60, $GLOBALS['vms_test_post_meta'][40]['_vms_linked_vendor_id'], 'Staff/vendor save hook should update the forward staff-to-vendor pointer.');
	vms_test_assert_same(40, $GLOBALS['vms_test_post_meta'][60]['_vms_linked_staff_id'], 'Staff/vendor save hook should update the reverse vendor-to-staff pointer.');

	$wpdb = vms_test_reset_runtime();
	$wpdb->get_col_queue = array(array(50, 88, 89));
	$GLOBALS['vms_test_posts'][75] = new WP_Post(array('ID' => 75, 'post_type' => 'vms_staff', 'post_title' => 'Staff 75'));
	$GLOBALS['vms_test_post_meta'][75]['_vms_linked_vendor_id'] = 96;
	$_POST = array(
		'vms_vendor_staff_link_nonce' => 'nonce',
		'vms_linked_staff_id' => '75',
	);
	$vendor_staff_post = new WP_Post(array('ID' => 50, 'post_type' => 'vms_vendor'));
	$vendor_staff_save_callback(50, $vendor_staff_post, true);
	$prepare = vms_test_find_prepare($wpdb, 'SELECT pm.post_id FROM %i AS pm INNER JOIN %i AS p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_type = %s ORDER BY pm.meta_id ASC');
	vms_test_assert_same(array('wp_postmeta', 'wp_posts', '_vms_linked_staff_id', '75', 'vms_vendor'), $prepare['args'], 'Vendor/staff save hook should prepare postmeta/posts identifiers plus the target staff filter.');
	vms_test_assert_no_placeholders($prepare['final_sql'], 'Vendor/staff save hook final SQL should not retain placeholders.');
	vms_test_assert_same(
		array(
			array('post_id' => 88, 'key' => '_vms_linked_staff_id'),
			array('post_id' => 89, 'key' => '_vms_linked_staff_id'),
			array('post_id' => 96, 'key' => '_vms_linked_staff_id'),
		),
		$GLOBALS['vms_test_post_meta_deletes'],
		'Vendor/staff save hook should unlink duplicate vendor rows plus any reverse-linked prior vendor.'
	);
	vms_test_assert_same(75, $GLOBALS['vms_test_post_meta'][50]['_vms_linked_staff_id'], 'Vendor/staff save hook should update the forward vendor-to-staff pointer.');
	vms_test_assert_same(50, $GLOBALS['vms_test_post_meta'][75]['_vms_linked_vendor_id'], 'Vendor/staff save hook should update the reverse staff-to-vendor pointer.');

	$wpdb = vms_test_reset_runtime();
	$GLOBALS['vms_test_post_meta'][44]['_vms_linked_user_id'] = 87;
	$GLOBALS['vms_test_users'][87] = new WP_User(array('ID' => 87, 'display_name' => 'Cache User'));
	$user = vms_staffing_get_staff_user(44);
	vms_test_assert_true($user instanceof WP_User && $user->ID === 87, 'Staffing user lookup should prefer the normalized cached forward pointer.');
	vms_test_assert_same(array(), $wpdb->prepare_calls, 'Staffing user lookup should not query usermeta when the cached forward pointer exists.');

	$wpdb = vms_test_reset_runtime();
	$wpdb->get_var_queue = array(99);
	$GLOBALS['vms_test_users'][99] = new WP_User(array('ID' => 99, 'display_name' => 'Fallback User'));
	$user = vms_staffing_get_staff_user(44);
	vms_test_assert_true($user instanceof WP_User && $user->ID === 99, 'Staffing user lookup should fall back to the prepared reverse usermeta pointer.');
	$prepare = vms_test_find_prepare($wpdb, 'SELECT user_id FROM %i WHERE meta_key = %s AND meta_value = %s ORDER BY umeta_id ASC LIMIT 1');
	vms_test_assert_same(array('wp_usermeta', '_vms_staff_id', '44'), $prepare['args'], 'Staffing user lookup should prepare the usermeta table, reverse-link meta key, and staff ID string.');
	vms_test_assert_same(array(), $GLOBALS['vms_test_get_users_calls'], 'Staffing user lookup should not reintroduce get_users() for reverse lookups.');

	$wpdb = vms_test_reset_runtime();
	$wpdb->get_col_queue = array(array(201, 202, 203, 204));
	$GLOBALS['vms_test_post_meta'][201]['_vms_venue_id'] = 7;
	$GLOBALS['vms_test_post_meta'][201]['_vms_event_date'] = '2026-08-10';
	$GLOBALS['vms_test_post_meta'][201]['_vms_start_time'] = '18:00';
	$GLOBALS['vms_test_post_meta'][202]['_vms_venue_id'] = 7;
	$GLOBALS['vms_test_post_meta'][202]['_vms_event_date'] = '2026-08-11';
	$GLOBALS['vms_test_post_meta'][202]['_vms_start_time'] = '19:00';
	$GLOBALS['vms_test_post_meta'][203]['_vms_venue_id'] = 9;
	$GLOBALS['vms_test_post_meta'][204]['_vms_venue_id'] = 7;
	$GLOBALS['vms_test_event_plan_should_include'][201] = true;
	$GLOBALS['vms_test_event_plan_should_include'][202] = true;
	$GLOBALS['vms_test_event_plan_should_include'][204] = false;
	$GLOBALS['vms_test_rollups'][201] = array(
		'dirty' => 1,
		'readiness_status' => 'needs_staff',
		'open_headcount_total' => 1,
		'red_flag_reason_mask' => 0,
		'est_labor_cost_total' => '100.00',
		'missing_summary_json' => '{"ops":1}',
		'conflict_summary_json' => '[]',
	);
	$GLOBALS['vms_test_rollup_recompute'][201] = array(
		'dirty' => 0,
		'readiness_status' => 'ready',
		'open_headcount_total' => 0,
		'red_flag_reason_mask' => 0,
		'est_labor_cost_total' => '125.00',
		'missing_summary_json' => '[]',
		'conflict_summary_json' => '[]',
	);
	$GLOBALS['vms_test_rollups'][202] = array(
		'dirty' => 0,
		'readiness_status' => 'red_flag',
		'open_headcount_total' => 2,
		'red_flag_reason_mask' => 4,
		'est_labor_cost_total' => '',
		'missing_summary_json' => '{"usher":2}',
		'conflict_summary_json' => '{"overlap":1}',
	);
	$GLOBALS['vms_test_posts'][7] = new WP_Post(array('ID' => 7, 'post_type' => 'vms_venue', 'post_title' => 'Main Hall'));
	$GLOBALS['vms_test_posts'][201] = new WP_Post(array('ID' => 201, 'post_type' => 'vms_event_plan', 'post_title' => 'Alpha'));
	$GLOBALS['vms_test_posts'][202] = new WP_Post(array('ID' => 202, 'post_type' => 'vms_event_plan', 'post_title' => 'Beta'));
	$response = vms_staffing_build_dashboard_response(array('staffing_n' => 2, 'venue_id' => '7', 'include_drafts' => true));
	vms_test_assert_same(array(201, 202), array_column($response['items'], 'plan_id'), 'Dashboard response should keep prepared-query order, venue filtering, inclusion gates, and the item cap.');
	vms_test_assert_same(array(201), $GLOBALS['vms_test_compute_rollup_calls'], 'Dashboard response should recompute only dirty rollups.');
	vms_test_assert_same('READY', $response['items'][0]['readiness_label'], 'Dashboard response should format readiness labels through the shared helper.');
	$prepare = vms_test_find_prepare($wpdb, 'SELECT p.ID FROM %i AS pm INNER JOIN %i AS p ON p.ID = pm.post_id WHERE p.post_type = %s AND p.post_status IN (%s, %s, %s, %s, %s) AND pm.meta_key = %s AND pm.meta_value >= %s ORDER BY pm.meta_value ASC, p.ID ASC LIMIT %d');
	vms_test_assert_same(array('wp_postmeta', 'wp_posts', 'vms_event_plan', 'publish', 'draft', 'pending', 'private', 'future', '_vms_event_date', vms_test_today_ymd(), 120), $prepare['args'], 'Dashboard response should prepare the postmeta/posts identifiers, bounded statuses, event-date key, today boundary, and hard limit.');
	vms_test_assert_no_placeholders($prepare['final_sql'], 'Dashboard response final SQL should not retain placeholders.');

	$wpdb = vms_test_reset_runtime();
	$wpdb->get_col_queue = array(array(301, '302', 302, 0, 303));
	$GLOBALS['vms_test_event_plan_should_include'][301] = true;
	$GLOBALS['vms_test_event_plan_should_include'][302] = false;
	$GLOBALS['vms_test_event_plan_should_include'][303] = true;
	$plan_ids = vms_staffing_collect_rebuild_plan_ids(
		array(
			'start_date' => '2026-08-10',
			'end_date' => '2026-08-31',
			'venue_id' => 14,
			'include_drafts' => true,
			'include_cancelled' => true,
		)
	);
	vms_test_assert_same(array(301, 303), $plan_ids, 'Rebuild candidate collection should dedupe prepared-query results after inclusion gating.');
	$prepare = vms_test_find_prepare($wpdb, 'SELECT p.ID FROM %i AS date_meta INNER JOIN %i AS p ON p.ID = date_meta.post_id LEFT JOIN %i AS venue_meta ON venue_meta.post_id = p.ID AND venue_meta.meta_key = %s WHERE p.post_type = %s AND p.post_status IN (%s, %s, %s, %s, %s) AND date_meta.meta_key = %s AND (%s = %s OR date_meta.meta_value >= %s) AND (%s = %s OR date_meta.meta_value <= %s) AND (%d = 0 OR venue_meta.meta_value = %s) ORDER BY date_meta.meta_value ASC, p.ID ASC');
	vms_test_assert_same(array('wp_postmeta', 'wp_posts', 'wp_postmeta', '_vms_venue_id', 'vms_event_plan', 'publish', 'draft', 'pending', 'private', 'future', '_vms_event_date', '2026-08-10', '', '2026-08-10', '2026-08-31', '', '2026-08-31', 14, '14'), $prepare['args'], 'Rebuild candidate collection should prepare postmeta/posts identifiers, optional date filters, and the optional venue join inside one literal SQL template.');
	vms_test_assert_no_placeholders($prepare['final_sql'], 'Rebuild candidate collection final SQL should not retain placeholders.');

	$wpdb = vms_test_reset_runtime();
	$GLOBALS['vms_test_get_posts_queue'] = array(
		array(602, 601, 603),
	);
	$GLOBALS['vms_test_post_meta'][601]['_vms_event_date'] = '2026-08-10';
	$GLOBALS['vms_test_post_meta'][602]['_vms_event_date'] = '';
	$GLOBALS['vms_test_post_meta'][603]['_vms_event_date'] = '2026-08-08';
	$GLOBALS['vms_test_venues'] = array(7 => 'Main Hall');
	$GLOBALS['vms_test_event_contexts'][601] = array('event_title' => 'Late Show', 'date_ymd' => '2026-08-10', 'venue_id' => 7);
	$GLOBALS['vms_test_event_contexts'][603] = array('event_title' => 'Early Show', 'date_ymd' => '2026-08-08', 'venue_id' => 7);
	$options = vms_tasks_admin_get_event_options();
	vms_test_assert_same(
		array(
			603 => 'Early Show - 2026-08-08 @ Main Hall',
			601 => 'Late Show - 2026-08-10 @ Main Hall',
		),
		$options,
		'Admin event fallback should drop empty event-date rows and sort the remaining IDs by event date in PHP.'
	);
	vms_test_assert_same(1, count($GLOBALS['vms_test_get_posts_calls']), 'Admin event fallback should perform one bounded fallback get_posts() call.');
	vms_test_assert_same('ID', $GLOBALS['vms_test_get_posts_calls'][0]['orderby'], 'Admin event fallback should request a stable ID-order batch before PHP sorting.');
	vms_test_assert_true(!isset($GLOBALS['vms_test_get_posts_calls'][0]['meta_key']), 'Admin event fallback should not order directly by meta_key.');
	vms_test_assert_true(!isset($GLOBALS['vms_test_get_posts_calls'][0]['meta_query']), 'Admin event fallback should not include a meta_query clause.');

	eval(vms_test_extract_function($generator_source, 'vms_tasks_collect_upcoming_event_ids'));
	$wpdb = vms_test_reset_runtime();
	$wpdb->get_col_queue = array(array(81, '82', 82, 0));
	$GLOBALS['vms_test_meta_keys']['event_plan:date'] = '_vms_event_date';
	$ids = vms_tasks_collect_upcoming_event_ids(30);
	vms_test_assert_same(array(81, 82), $ids, 'Generator horizon reads should dedupe prepared-query results and discard non-positive IDs.');
	$prepare = vms_test_find_prepare($wpdb, 'SELECT p.ID FROM %i AS pm INNER JOIN %i AS p ON p.ID = pm.post_id WHERE p.post_type = %s AND p.post_status IN (%s, %s, %s, %s, %s) AND pm.meta_key = %s AND pm.meta_value >= %s AND pm.meta_value <= %s ORDER BY pm.meta_value ASC, p.ID ASC');
	$generator_today = wp_date('Y-m-d', time(), wp_timezone());
	$generator_horizon = wp_date('Y-m-d', time() + (30 * DAY_IN_SECONDS), wp_timezone());
	vms_test_assert_same(array('wp_postmeta', 'wp_posts', 'vms_event_plan', 'publish', 'private', 'draft', 'pending', 'future', '_vms_event_date', $generator_today, $generator_horizon), $prepare['args'], 'Generator horizon reads should prepare postmeta/posts identifiers, bounded statuses, the event-date key, and the runtime-relative date window.');
	vms_test_assert_no_placeholders($prepare['final_sql'], 'Generator horizon final SQL should not retain placeholders.');

	fwrite(STDOUT, "staffing final repository sql remediation: PASS\n");
} catch (Throwable $e) {
	fwrite(STDERR, 'staffing final repository sql remediation: FAIL - ' . $e->getMessage() . "\n");
	exit(1);
}
