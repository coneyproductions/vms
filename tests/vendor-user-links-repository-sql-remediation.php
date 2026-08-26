<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');
define('BVMGR_USER_PRIMARY_VENDOR_META_KEY', '_vms_vendor_id');
define('BVMGR_VENDOR_PRIMARY_USER_META_KEY', '_vms_vendor_user_id');
define('BVMGR_DB_TABLE_VENDOR_USER_LINKS_SUFFIX', 'vms_vendor_user_links');

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

final class VMS_Vendor_Link_WPDB_Spy
{
	public string $prefix = 'wp_';
	public array $prepares = array();
	public array $calls = array();
	public array $get_var_queue = array();
	public array $get_results_queue = array();
	public array $query_queue = array();
	public array $update_queue = array();
	public array $delete_queue = array();

	public function esc_like(string $text): string
	{
		return addcslashes($text, '_%\\');
	}

	public function prepare(string $query, ...$args): string
	{
		$index = 0;
		$sql = (string) preg_replace_callback(
			'/(?<!%)%(?:\d+\$)?[sdi]/',
			function (array $match) use (&$index, $args): string {
				$value = $args[$index] ?? null;
				$index++;
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
		$this->prepares[] = array('query' => $query, 'args' => $args, 'sql' => $sql);
		return $sql;
	}

	public function get_var(string $sql)
	{
		$result = $this->shift($this->get_var_queue, null);
		$this->calls[] = array('kind' => 'get_var', 'sql' => $sql, 'result' => $result);
		return $result;
	}

	public function get_results(string $sql, $output = ARRAY_A)
	{
		unset($output);
		$result = $this->shift($this->get_results_queue, array());
		$this->calls[] = array('kind' => 'get_results', 'sql' => $sql, 'result' => $result);
		return $result;
	}

	public function query(string $sql)
	{
		$result = $this->shift($this->query_queue, 1);
		$this->calls[] = array('kind' => 'query', 'sql' => $sql, 'result' => $result);
		vms_test_event('query', array('sql' => $sql));
		return $result;
	}

	public function update(string $table, array $data, array $where, array $format, array $where_format)
	{
		$result = $this->shift($this->update_queue, 1);
		$call = compact('table', 'data', 'where', 'format', 'where_format', 'result');
		$call['kind'] = 'update';
		$this->calls[] = $call;
		vms_test_event('update', $call);
		return $result;
	}

	public function delete(string $table, array $where, array $where_format)
	{
		$result = $this->shift($this->delete_queue, 1);
		$call = compact('table', 'where', 'where_format', 'result');
		$call['kind'] = 'delete';
		$this->calls[] = $call;
		vms_test_event('delete', $call);
		return $result;
	}

	private function shift(array &$queue, $default)
	{
		return $queue === array() ? $default : array_shift($queue);
	}
}

function vms_test_event(string $kind, array $details = array()): void
{
	$GLOBALS['vms_events'][] = array('kind' => $kind) + $details;
}

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_key($value): string
{
	$value = is_scalar($value) ? preg_replace('/[^a-z0-9_\-]+/i', '', strtolower((string) $value)) : '';
	return is_string($value) ? $value : '';
}

function sanitize_email($value): string
{
	$value = is_scalar($value) ? filter_var((string) $value, FILTER_SANITIZE_EMAIL) : '';
	return is_string($value) ? strtolower(trim($value)) : '';
}

function is_email($value): bool
{
	return is_string(filter_var((string) $value, FILTER_VALIDATE_EMAIL));
}

function current_time(string $type, bool $gmt = false): string
{
	unset($type, $gmt);
	return '2026-08-07 16:20:00';
}

function get_user_meta(int $id, string $key, bool $single = false)
{
	unset($single);
	return $GLOBALS['vms_user_meta'][$id][$key] ?? '';
}

function update_user_meta(int $id, string $key, $value): bool
{
	$GLOBALS['vms_user_meta'][$id][$key] = $value;
	vms_test_event('update_user_meta', compact('id', 'key', 'value'));
	return true;
}

function delete_user_meta(int $id, string $key): bool
{
	unset($GLOBALS['vms_user_meta'][$id][$key]);
	vms_test_event('delete_user_meta', compact('id', 'key'));
	return true;
}

function get_post_meta(int $id, string $key, bool $single = false)
{
	unset($single);
	return $GLOBALS['vms_post_meta'][$id][$key] ?? '';
}

function update_post_meta(int $id, string $key, $value): bool
{
	$GLOBALS['vms_post_meta'][$id][$key] = $value;
	vms_test_event('update_post_meta', compact('id', 'key', 'value'));
	return true;
}

function delete_post_meta(int $id, string $key): bool
{
	unset($GLOBALS['vms_post_meta'][$id][$key]);
	vms_test_event('delete_post_meta', compact('id', 'key'));
	return true;
}

function get_post(int $id)
{
	return $GLOBALS['vms_posts'][$id] ?? null;
}

function do_action(string $tag, $value = null): void
{
	vms_test_event('action', compact('tag', 'value'));
}

function vms_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function vms_same($expected, $actual, string $message): void
{
	vms_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function vms_contains(string $needle, string $haystack, string $message): void
{
	vms_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function vms_extract_function(string $source, string $name): string
{
	$start = strpos($source, 'function ' . $name . '(');
	$brace = $start === false ? false : strpos($source, '{', $start);
	if ($start === false || $brace === false) {
		throw new RuntimeException('Unable to locate ' . $name . '.');
	}
	$depth = 1;
	for ($index = $brace + 1, $length = strlen($source); $index < $length; $index++) {
		$depth += $source[$index] === '{' ? 1 : ($source[$index] === '}' ? -1 : 0);
		if ($depth === 0) {
			return substr($source, $start, ($index - $start) + 1);
		}
	}
	throw new RuntimeException('Unable to close ' . $name . '.');
}

function vms_reset(VMS_Vendor_Link_WPDB_Spy $wpdb): void
{
	$wpdb->prepares = array();
	$wpdb->calls = array();
	$wpdb->get_var_queue = array();
	$wpdb->get_results_queue = array();
	$wpdb->query_queue = array();
	$wpdb->update_queue = array();
	$wpdb->delete_queue = array();
	WP_Query::$queue = array();
	WP_Query::$calls = array();
	$GLOBALS['vms_user_meta'] = array();
	$GLOBALS['vms_post_meta'] = array();
	$GLOBALS['vms_posts'] = array();
	$GLOBALS['vms_events'] = array();
}

function vms_last_prepare(VMS_Vendor_Link_WPDB_Spy $wpdb): array
{
	$prepare = end($wpdb->prepares);
	if (!is_array($prepare)) {
		throw new RuntimeException('Expected prepare().');
	}
	return $prepare;
}

function vms_last_call(VMS_Vendor_Link_WPDB_Spy $wpdb, string $kind): array
{
	for ($index = count($wpdb->calls) - 1; $index >= 0; $index--) {
		if (($wpdb->calls[$index]['kind'] ?? '') === $kind) {
			return $wpdb->calls[$index];
		}
	}
	throw new RuntimeException('Expected database call: ' . $kind);
}

function vms_no_placeholders(string $sql, string $message): void
{
	vms_assert(preg_match('/(?<!%)%(?:\d+\$)?[sdi]/', $sql) !== 1, $message . "\nSQL: " . $sql);
}

function vms_event_kinds(): array
{
	return array_column($GLOBALS['vms_events'], 'kind');
}

$root = dirname(__DIR__);
$source = (string) file_get_contents($root . '/includes/core/vendor-user-links.php');
$live_source = (string) file_get_contents(dirname(__DIR__, 3) . '/vms/includes/core/vendor-user-links.php');
vms_assert($source !== '' && $live_source !== '', 'Mirror and shadow-live sources must be readable.');
vms_same($source, $live_source, 'Mirror and shadow-live vendor-link repositories must be byte-identical.');

vms_same(2, substr_count($source, 'WordPress.DB.SlowDBQuery.slow_db_query_meta_query'), 'Only the two finite compatibility meta queries should have slow-query annotations.');
vms_assert(strpos($source, 'phpcs:disable') === false, 'Blanket PHPCS suppression is forbidden.');
vms_assert(strpos($source, 'phpcs:ignoreFile') === false, 'File-level PHPCS suppression is forbidden.');
foreach (array('FROM {$t}', 'UPDATE {$t}', 'INSERT INTO {$t}', 'WHERE {$where}', '$wpdb->get_results($sql', '$wpdb->query($sql') as $unsafe) {
	vms_assert(strpos($source, $unsafe) === false, 'Legacy scanner target remains: ' . $unsafe);
}
vms_contains('FROM %i WHERE user_id = %d AND link_status = %s', $source, 'Active user reads must prepare identifier and values.');
vms_contains('FROM %i WHERE vendor_id = %d AND link_status = %s', $source, 'Active vendor reads must prepare identifier and values.');
vms_contains('INSERT INTO %i', $source, 'Upserts must prepare the table identifier.');
vms_contains('ON DUPLICATE KEY UPDATE', $source, 'Upserts must retain duplicate-key behavior.');
vms_contains('is_primary  = IF(VALUES(is_primary)=1, 1, is_primary)', $source, 'Upserts must not demote an existing primary implicitly.');

$functions = array(
	'vms_vendor_user_links_table',
	'vms_vendor_user_links_table_exists',
	'vms_vendor_user_link_normalize_role',
	'vms_vendor_user_link_normalize_status',
	'vms_vendor_user_links_get_by_user',
	'vms_vendor_user_links_get_by_user_legacy',
	'vms_get_active_vendor_ids_for_user',
	'vms_user_can_access_vendor',
	'vms_vendor_user_links_set_primary_for_user',
	'vms_vendor_user_links_get_by_vendor',
	'vms_vendor_user_link_exists',
	'vms_vendor_user_link_vendor_email_meta_keys',
	'vms_vendor_user_link_find_vendor_matches_for_email',
	'vms_vendor_user_link_upsert',
	'vms_vendor_user_link_update',
	'vms_vendor_user_link_delete',
);
foreach ($functions as $function) {
	eval(vms_extract_function($source, $function));
}

$wpdb = new VMS_Vendor_Link_WPDB_Spy();
$GLOBALS['wpdb'] = $wpdb;
$table = 'wp_vms_vendor_user_links';

vms_reset($wpdb);
$wpdb->get_var_queue[] = $table;
vms_same(true, vms_vendor_user_links_table_exists(), 'The exact repository table should be recognized.');
$prepare = vms_last_prepare($wpdb);
vms_same('SHOW TABLES LIKE %s', $prepare['query'], 'The schema probe should prepare its LIKE value.');
vms_same(array('wp\\_vms\\_vendor\\_user\\_links'), $prepare['args'], 'The schema probe should pass its escaped table name as a value.');
vms_no_placeholders(vms_last_call($wpdb, 'get_var')['sql'], 'The schema probe should execute prepared SQL.');

vms_reset($wpdb);
$wpdb->get_var_queue[] = $table;
$wpdb->get_results_queue[] = array(
	array('vendor_id' => '41', 'user_role' => 'owner', 'link_status' => 'active', 'is_primary' => '1'),
);
$rows = vms_vendor_user_links_get_by_user(22, false);
vms_same(
	array(array('vendor_id' => 41, 'user_role' => 'owner', 'link_status' => 'active', 'is_primary' => 1)),
	$rows,
	'Active user reads should normalize and return repository rows.'
);
$prepare = vms_last_prepare($wpdb);
vms_same(
	'SELECT vendor_id, user_role, link_status, is_primary FROM %i WHERE user_id = %d AND link_status = %s ORDER BY is_primary DESC, vendor_id ASC',
	$prepare['query'],
	'Active user reads should preserve filtering and ordering.'
);
vms_same(array($table, 22, 'active'), $prepare['args'], 'Active user reads should prepare table, user, and status.');
vms_no_placeholders(vms_last_call($wpdb, 'get_results')['sql'], 'Active user reads should execute prepared SQL.');

vms_reset($wpdb);
$wpdb->get_var_queue[] = $table;
$wpdb->get_results_queue[] = array(
	array('vendor_id' => '42', 'user_role' => 'viewer', 'link_status' => 'disabled', 'is_primary' => '0'),
);
$rows = vms_vendor_user_links_get_by_user(22, true);
vms_same('disabled', $rows[0]['link_status'] ?? '', 'Inactive-inclusive user reads should preserve disabled rows.');
$prepare = vms_last_prepare($wpdb);
vms_same(array($table, 22), $prepare['args'], 'Inactive-inclusive user reads should omit the active-status argument.');
vms_assert(strpos($prepare['query'], 'link_status =') === false, 'Inactive-inclusive user reads should not filter link status.');

vms_reset($wpdb);
$wpdb->get_var_queue[] = null;
$GLOBALS['vms_user_meta'][22][BVMGR_USER_PRIMARY_VENDOR_META_KEY] = 91;
WP_Query::$queue[] = array(91, 92, 91);
$rows = vms_vendor_user_links_get_by_user(22, false);
vms_same(array(91, 92), array_column($rows, 'vendor_id'), 'Missing-table user reads should preserve and deduplicate legacy pointers.');
$legacy = WP_Query::$calls[0] ?? array();
vms_same('vms_vendor', $legacy['post_type'] ?? '', 'Legacy user fallback should query vendor posts.');
vms_same('22', $legacy['meta_query'][0]['value'] ?? '', 'Legacy user fallback should retain reverse primary-user lookup.');
vms_same(true, $legacy['no_found_rows'] ?? false, 'Legacy user fallback should retain no-count optimization.');

vms_reset($wpdb);
$wpdb->get_var_queue[] = $table;
$wpdb->get_results_queue[] = array(
	array('user_id' => '22', 'user_role' => 'manager', 'link_status' => 'active'),
);
$rows = vms_vendor_user_links_get_by_vendor(41, false);
vms_same(
	array(array('user_id' => 22, 'user_role' => 'manager', 'link_status' => 'active')),
	$rows,
	'Active vendor reads should normalize and return repository rows.'
);
$prepare = vms_last_prepare($wpdb);
vms_same(array($table, 41, 'active'), $prepare['args'], 'Active vendor reads should prepare table, vendor, and status.');
vms_contains('ORDER BY link_status ASC, user_role ASC, user_id ASC', $prepare['query'], 'Vendor reads should retain deterministic ordering.');
vms_no_placeholders(vms_last_call($wpdb, 'get_results')['sql'], 'Active vendor reads should execute prepared SQL.');

vms_reset($wpdb);
$wpdb->get_var_queue[] = $table;
$wpdb->get_results_queue[] = array(
	array('user_id' => '23', 'user_role' => 'viewer', 'link_status' => 'disabled'),
);
$rows = vms_vendor_user_links_get_by_vendor(41, true);
vms_same('disabled', $rows[0]['link_status'] ?? '', 'Inactive-inclusive vendor reads should preserve disabled rows.');
$prepare = vms_last_prepare($wpdb);
vms_same(array($table, 41), $prepare['args'], 'Inactive-inclusive vendor reads should omit the active-status argument.');
vms_assert(strpos($prepare['query'], 'link_status =') === false, 'Inactive-inclusive vendor reads should not filter link status.');

vms_reset($wpdb);
$wpdb->get_var_queue[] = null;
$GLOBALS['vms_post_meta'][41][BVMGR_VENDOR_PRIMARY_USER_META_KEY] = 22;
vms_same(
	array(array('user_id' => 22, 'user_role' => 'primary_contact', 'link_status' => 'active')),
	vms_vendor_user_links_get_by_vendor(41),
	'Missing-table vendor reads should preserve the primary-contact fallback.'
);

vms_reset($wpdb);
WP_Query::$queue[] = array(44, 44, 45);
vms_same(
	array(44, 45),
	vms_vendor_user_link_find_vendor_matches_for_email(' Manager@Example.com '),
	'Email matching should sanitize input and deduplicate vendor IDs.'
);
$email_query = WP_Query::$calls[0] ?? array();
vms_same('OR', $email_query['meta_query']['relation'] ?? '', 'Email matching should retain OR semantics.');
vms_same(4, count($email_query['meta_query'] ?? array()), 'Email matching should retain exactly three finite key clauses plus relation.');
vms_same(true, $email_query['no_found_rows'] ?? false, 'Email matching should retain no-count optimization.');

vms_reset($wpdb);
$wpdb->get_var_queue = array($table, $table);
$wpdb->get_results_queue[] = array(
	array('vendor_id' => 41, 'user_role' => 'owner', 'link_status' => 'active', 'is_primary' => 0),
);
$wpdb->query_queue = array(1, 1);
vms_same(true, vms_vendor_user_links_set_primary_for_user(22, 41, 7), 'Authorized primary reassignment should succeed.');
vms_same(
	array('update_user_meta', 'query', 'query'),
	vms_event_kinds(),
	'Primary reassignment should sync legacy metadata, clear prior rows, then set the requested row.'
);
$primary_prepares = array_slice($wpdb->prepares, -2);
vms_same(
	array($table, '2026-08-07 16:20:00', 7, 22),
	$primary_prepares[0]['args'],
	'Primary clearing should prepare table, timestamp, actor, and user.'
);
vms_same(
	array($table, '2026-08-07 16:20:00', 7, 22, 41),
	$primary_prepares[1]['args'],
	'Primary selection should additionally prepare the requested vendor.'
);
vms_contains('SET is_primary = 0', $primary_prepares[0]['query'], 'Primary reassignment should clear old primaries first.');
vms_contains('SET is_primary = 1', $primary_prepares[1]['query'], 'Primary reassignment should set the requested primary second.');
vms_no_placeholders($primary_prepares[0]['sql'], 'Primary clear should be fully prepared.');
vms_no_placeholders($primary_prepares[1]['sql'], 'Primary set should be fully prepared.');

vms_reset($wpdb);
$wpdb->get_var_queue[] = $table;
$wpdb->get_results_queue[] = array();
vms_same(false, vms_vendor_user_links_set_primary_for_user(22, 41, 7), 'Primary reassignment should reject a user without an active link.');
vms_same(array(), vms_event_kinds(), 'Unauthorized primary reassignment should not mutate pointers or rows.');

vms_reset($wpdb);
$GLOBALS['vms_user_meta'][22][BVMGR_USER_PRIMARY_VENDOR_META_KEY] = 99;
$wpdb->get_var_queue = array($table, $table);
$wpdb->get_results_queue[] = array();
$wpdb->query_queue[] = 1;
vms_same(
	true,
	vms_vendor_user_link_upsert(77, 22, array('role' => 'unknown', 'status' => 'active', 'source' => 'test'), 9),
	'Repository upsert should report successful execution.'
);
$prepare = vms_last_prepare($wpdb);
vms_contains('ON DUPLICATE KEY UPDATE', $prepare['query'], 'Repository upsert should preserve duplicate-key semantics.');
vms_same(
	array($table, 77, 22, 'manager', 'active', 0, '2026-08-07 16:20:00', 9, '2026-08-07 16:20:00', 9),
	$prepare['args'],
	'Repository upsert should prepare its table and all nine inserted values in order.'
);
vms_no_placeholders(vms_last_call($wpdb, 'query')['sql'], 'Repository upsert should execute prepared SQL.');
vms_same(
	array('query', 'update_post_meta', 'action'),
	vms_event_kinds(),
	'Successful upsert should write repository state before the legacy pointer and creation action.'
);

vms_reset($wpdb);
$GLOBALS['vms_user_meta'][22][BVMGR_USER_PRIMARY_VENDOR_META_KEY] = 99;
$wpdb->get_var_queue = array($table, $table);
$wpdb->get_results_queue[] = array();
$wpdb->query_queue[] = false;
vms_same(false, vms_vendor_user_link_upsert(77, 22, array(), 9), 'Failed repository upsert should report failure.');
vms_same(array('query'), vms_event_kinds(), 'Failed repository upsert should not update legacy vendor metadata or dispatch creation.');

vms_reset($wpdb);
$GLOBALS['vms_user_meta'][22][BVMGR_USER_PRIMARY_VENDOR_META_KEY] = 99;
$wpdb->get_var_queue = array(null, null);
vms_same(true, vms_vendor_user_link_upsert(77, 22, array(), 9), 'Missing-table upsert should retain the legacy fallback.');
vms_same(22, $GLOBALS['vms_post_meta'][77][BVMGR_VENDOR_PRIMARY_USER_META_KEY] ?? 0, 'Legacy fallback should seed the empty vendor primary-user pointer.');
vms_same(array('update_post_meta', 'action'), vms_event_kinds(), 'Legacy fallback should update the vendor pointer before creation notification.');

vms_reset($wpdb);
$wpdb->get_var_queue[] = $table;
$wpdb->update_queue[] = 1;
vms_same(
	true,
	vms_vendor_user_link_update(77, 22, array('role' => 'owner', 'status' => 'disabled'), 9),
	'Repository update should report wpdb success.'
);
$update = vms_last_call($wpdb, 'update');
vms_same($table, $update['table'], 'Repository update should target the plugin-owned table.');
vms_same(
	array('user_role' => 'owner', 'link_status' => 'disabled', 'updated_at' => '2026-08-07 16:20:00', 'updated_by' => 9),
	$update['data'],
	'Repository update should retain normalized values and audit data.'
);
vms_same(array('vendor_id' => 77, 'user_id' => 22), $update['where'], 'Repository update should scope the exact vendor/user row.');
vms_same(array('%s', '%s', '%s', '%d'), $update['format'], 'Repository update should retain value formats.');

vms_reset($wpdb);
$GLOBALS['vms_post_meta'][77][BVMGR_VENDOR_PRIMARY_USER_META_KEY] = 22;
$GLOBALS['vms_user_meta'][22][BVMGR_USER_PRIMARY_VENDOR_META_KEY] = 77;
$GLOBALS['vms_posts'][88] = (object) array('post_type' => 'vms_vendor');
$wpdb->get_var_queue = array($table, $table, $table, $table);
$wpdb->get_results_queue = array(
	array(array('vendor_id' => 88, 'user_role' => 'manager', 'link_status' => 'active', 'is_primary' => 0)),
	array(array('vendor_id' => 88, 'user_role' => 'manager', 'link_status' => 'active', 'is_primary' => 0)),
);
$wpdb->delete_queue[] = 1;
$wpdb->query_queue = array(1, 1);
vms_same(true, vms_vendor_user_link_delete(77, 22, 5), 'Repository delete should remove the link and promote a remaining active vendor.');
vms_same(
	array('delete_post_meta', 'delete_user_meta', 'delete', 'update_user_meta', 'query', 'query'),
	vms_event_kinds(),
	'Delete should clear matching legacy pointers, remove the row, then perform ordered replacement-primary writes.'
);
$delete = vms_last_call($wpdb, 'delete');
vms_same(array('vendor_id' => 77, 'user_id' => 22), $delete['where'], 'Repository delete should scope the exact vendor/user row.');
vms_same(array('%d', '%d'), $delete['where_format'], 'Repository delete should retain integer where formats.');
vms_same(88, $GLOBALS['vms_user_meta'][22][BVMGR_USER_PRIMARY_VENDOR_META_KEY] ?? 0, 'Delete should assign the remaining active vendor as replacement primary.');

$scanner_inventory = array(
	'WordPress.DB.DirectDatabaseQuery.DirectQuery' => 8,
	'WordPress.DB.DirectDatabaseQuery.NoCaching' => 8,
	'WordPress.DB.PreparedSQL.NotPrepared' => 4,
	'WordPress.DB.PreparedSQL.InterpolatedNotPrepared' => 7,
	'WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare' => 2,
	'PluginCheck.Security.DirectDB.UnescapedDBParameter' => 5,
	'WordPress.DB.SlowDBQuery.slow_db_query_meta_query' => 2,
);
vms_same(36, array_sum($scanner_inventory), 'The remediation contract should inventory all 36 owned historical scanner findings.');
vms_same(10, substr_count($source, 'WordPress.DB.DirectDatabaseQuery.DirectQuery'), 'Each current direct repository call or branch should have one narrow annotation.');
vms_same(10, substr_count($source, 'WordPress.DB.DirectDatabaseQuery.NoCaching'), 'Each current request-fresh repository call or branch should have one narrow no-cache annotation.');

echo "vendor user links repository SQL remediation: PASS\n";
