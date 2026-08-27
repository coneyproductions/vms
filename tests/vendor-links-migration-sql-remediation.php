<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

final class VMS_Migration_WPDB_Spy
{
	/** @var array<int,array{template:string,args:array<int,mixed>,sql:string}> */
	public array $prepares = array();
	/** @var array<int,array{sql:string,result:mixed}> */
	public array $queries = array();
	/** @var array<int,mixed> */
	public array $query_results = array();

	public function prepare(string $template, ...$args): string
	{
		if (count($args) === 1 && is_array($args[0])) {
			$args = array_values($args[0]);
		}

		$index = 0;
		$sql = preg_replace_callback(
			'/(?<!%)%(?:\d+\$)?[sdfi]/',
			static function (array $match) use (&$index, $args): string {
				if (!array_key_exists($index, $args)) {
					throw new RuntimeException('Missing prepared-query argument.');
				}
				$value = $args[$index++];
				$type = substr($match[0], -1);
				if ($type === 'd') {
					return (string) (int) $value;
				}
				if ($type === 'f') {
					return (string) (float) $value;
				}
				if ($type === 'i') {
					return '`' . str_replace('`', '``', (string) $value) . '`';
				}
				return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $value) . "'";
			},
			$template
		);
		if (!is_string($sql) || $index !== count($args)) {
			throw new RuntimeException('Prepared-query placeholder/argument mismatch.');
		}
		if (preg_match('/(?<!%)%(?:\d+\$)?[sdfi]/', $sql) === 1) {
			throw new RuntimeException('Prepared SQL retains an unresolved placeholder.');
		}

		$this->prepares[] = array('template' => $template, 'args' => $args, 'sql' => $sql);
		return $sql;
	}

	public function query(string $sql)
	{
		$result = $this->query_results === array() ? 1 : array_shift($this->query_results);
		$this->queries[] = array('sql' => $sql, 'result' => $result);
		return $result;
	}

	public function get_charset_collate(): string
	{
		return '';
	}
}

final class WP_Query
{
	/** @var array<int,array<string,mixed>> */
	public static array $calls = array();
	/** @var array<int,int> */
	public array $posts = array();

	public function __construct(array $args)
	{
		self::$calls[] = $args;
		$this->posts = $GLOBALS['migration_vendor_ids'];
	}
}

function current_time(string $type, bool $gmt = false): string
{
	unset($type, $gmt);
	return '2026-08-08 03:40:00';
}

function absint($value): int
{
	return abs((int) $value);
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	unset($single);
	return $GLOBALS['migration_post_meta'][$post_id][$key] ?? '';
}

function get_user_by(string $field, int $user_id)
{
	unset($field);
	return $GLOBALS['migration_users_by_id'][$user_id] ?? false;
}

function get_user_meta(int $user_id, string $key, bool $single = false)
{
	unset($single);
	return $GLOBALS['migration_user_meta'][$user_id][$key] ?? '';
}

function get_users(array $args): array
{
	$GLOBALS['migration_get_users_calls'][] = $args;
	return $GLOBALS['migration_get_users_result'];
}

function get_post(int $post_id)
{
	return $GLOBALS['migration_posts_by_id'][$post_id] ?? null;
}

function migration_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function migration_same($expected, $actual, string $message): void
{
	migration_assert(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

function migration_contains(string $needle, string $haystack, string $message): void
{
	migration_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function migration_fixture_reset(VMS_Migration_WPDB_Spy $db): void
{
	$db->prepares = array();
	$db->queries = array();
	$db->query_results = array(1, 1, 0, false, 1, 1);
	WP_Query::$calls = array();
	$GLOBALS['migration_vendor_ids'] = array(11, 0, 12, 14);
	$GLOBALS['migration_post_meta'] = array(
		11 => array('_vms_vendor_user_id' => 101),
		12 => array('_vms_vendor_user_id' => 102),
		14 => array('_vms_vendor_user_id' => 999),
	);
	$GLOBALS['migration_users_by_id'] = array(
		101 => (object) array('ID' => 101),
		102 => (object) array('ID' => 102),
	);
	$GLOBALS['migration_user_meta'] = array(
		101 => array('_vms_vendor_id' => 11),
		102 => array('_vms_vendor_id' => 99),
		103 => array('_vms_vendor_id' => 13),
		104 => array('_vms_vendor_id' => 0),
	);
	$GLOBALS['migration_get_users_calls'] = array();
	$GLOBALS['migration_get_users_result'] = array(
		(object) array('ID' => 101),
		(object) array('ID' => 103),
		(object) array('ID' => 0),
		(object) array('ID' => 104),
	);
	$GLOBALS['migration_posts_by_id'] = array(
		11 => (object) array('ID' => 11, 'post_type' => 'vms_vendor'),
		13 => (object) array('ID' => 13, 'post_type' => 'vms_vendor'),
	);
}

$plugin_root = dirname(__DIR__);
$runtime_path = $plugin_root . '/includes/db/migrations.php';
$shadow_path = dirname(__DIR__, 3) . '/vms/includes/db/migrations.php';
$source = (string) file_get_contents($runtime_path);
$shadow_source = (string) file_get_contents($shadow_path);
migration_assert($source !== '' && $shadow_source !== '', 'Mirror and shadow migration sources should be readable.');
migration_same($source, $shadow_source, 'Migration runtime must remain mirror/shadow-live byte-identical.');

$wpdb = new VMS_Migration_WPDB_Spy();
$GLOBALS['wpdb'] = $wpdb;
require $runtime_path;

$table = 'wp_vms_vendor_user_links';
migration_fixture_reset($wpdb);
bvmgr_db_backfill_vendor_user_links_from_legacy($table);

migration_same(1, count(WP_Query::$calls), 'Vendor legacy-pointer enumeration should execute once.');
$vendor_args = WP_Query::$calls[0];
migration_same('vms_vendor', $vendor_args['post_type'], 'Vendor backfill post type changed.');
migration_same(array('publish', 'draft', 'private'), $vendor_args['post_status'], 'Vendor backfill statuses changed.');
migration_same(-1, $vendor_args['posts_per_page'], 'Vendor backfill must retain its complete-set migration query.');
migration_same(array(array('key' => '_vms_vendor_user_id', 'compare' => 'EXISTS')), $vendor_args['meta_query'], 'Vendor legacy-pointer filter changed.');
migration_same(true, $vendor_args['no_found_rows'], 'Vendor migration pagination behavior changed.');

migration_same(1, count($GLOBALS['migration_get_users_calls']), 'User legacy-pointer enumeration should execute once.');
migration_same(
	array('fields' => array('ID'), 'meta_key' => '_vms_vendor_id', 'orderby' => 'ID', 'order' => 'ASC'),
	$GLOBALS['migration_get_users_calls'][0],
	'User legacy-pointer query arguments changed.'
);

migration_same(6, count($wpdb->prepares), 'Backfill should retain two vendor upserts, two user upserts, and two primary-normalization updates.');
migration_same(6, count($wpdb->queries), 'Every prepared migration statement should execute even when an earlier query reports zero or false.');

$expected_templates = array(
	'INSERT INTO %i',
	'INSERT INTO %i',
	'INSERT INTO %i',
	'UPDATE %i SET is_primary = 0',
	'INSERT INTO %i',
	'UPDATE %i SET is_primary = 0',
);
foreach ($wpdb->prepares as $index => $prepare) {
	migration_contains($expected_templates[$index], $prepare['template'], 'Migration SQL template order changed at index ' . $index . '.');
	migration_same($table, $prepare['args'][0], 'Every migration statement should prepare the link-table identifier first.');
	migration_contains('`wp_vms_vendor_user_links`', $prepare['sql'], 'Prepared migration SQL should contain the quoted custom-table identifier.');
	migration_assert(preg_match('/(?<!%)%(?:\d+\$)?[sdfi]/', $prepare['sql']) !== 1, 'Executed migration SQL must not retain placeholders.');
	migration_same($prepare['sql'], $wpdb->queries[$index]['sql'], 'Prepared/executed SQL order changed at index ' . $index . '.');
}

migration_same(array($table, 11, 101, 'primary_contact', 'active', 1, '2026-08-08 03:40:00', 0, '2026-08-08 03:40:00', 0), $wpdb->prepares[0]['args'], 'Primary vendor-pointer upsert arguments changed.');
migration_same(array($table, 12, 102, 'primary_contact', 'active', 0, '2026-08-08 03:40:00', 0, '2026-08-08 03:40:00', 0), $wpdb->prepares[1]['args'], 'Nonprimary vendor-pointer upsert arguments changed.');
migration_same(array($table, 11, 101, 'manager', 'active', 1, '2026-08-08 03:40:00', 0, '2026-08-08 03:40:00', 0), $wpdb->prepares[2]['args'], 'First user-pointer upsert arguments changed.');
migration_same(array($table, '2026-08-08 03:40:00', 0, 101, 11), $wpdb->prepares[3]['args'], 'First single-primary normalization arguments changed.');
migration_same(array($table, 13, 103, 'manager', 'active', 1, '2026-08-08 03:40:00', 0, '2026-08-08 03:40:00', 0), $wpdb->prepares[4]['args'], 'Second user-pointer upsert arguments changed.');
migration_same(array($table, '2026-08-08 03:40:00', 0, 103, 13), $wpdb->prepares[5]['args'], 'Second single-primary normalization arguments changed.');
migration_contains('ON DUPLICATE KEY UPDATE', $wpdb->prepares[0]['template'], 'Vendor upsert must retain idempotence.');
migration_contains('is_primary  = IF(VALUES(is_primary)=1, 1, is_primary)', $wpdb->prepares[0]['template'], 'Vendor upsert primary-promotion rule changed.');
migration_contains("is_primary  = 1", $wpdb->prepares[2]['template'], 'User upsert must retain primary assignment.');
migration_contains('vendor_id <> 11', $wpdb->prepares[3]['sql'], 'Single-primary normalization must exclude the selected vendor.');

$first_run_sql = array_column($wpdb->queries, 'sql');
migration_fixture_reset($wpdb);
bvmgr_db_backfill_vendor_user_links_from_legacy($table);
migration_same($first_run_sql, array_column($wpdb->queries, 'sql'), 'Repeated idempotent backfill should retain identical SQL and ordering.');

$historical_inventory = array(
	'PluginCheck.Security.DirectDB.UnescapedDBParameter' => 3,
	'WordPress.DB.DirectDatabaseQuery.DirectQuery' => 3,
	'WordPress.DB.DirectDatabaseQuery.NoCaching' => 3,
	'WordPress.DB.PreparedSQL.InterpolatedNotPrepared' => 3,
	'WordPress.DB.PreparedSQL.NotPrepared' => 2,
	'WordPress.DB.SlowDBQuery.slow_db_query_meta_key' => 1,
	'WordPress.DB.SlowDBQuery.slow_db_query_meta_query' => 1,
);
migration_same(16, array_sum($historical_inventory), 'G13 migration baseline should remain exactly 16 rows.');
migration_same(3, substr_count($source, 'WordPress.DB.DirectDatabaseQuery.DirectQuery'), 'Each migration write should have one narrow DirectQuery annotation.');
migration_same(3, substr_count($source, 'WordPress.DB.DirectDatabaseQuery.NoCaching'), 'Each migration write should have one narrow NoCaching annotation.');
migration_same(2, substr_count($source, 'WordPress.DB.PreparedSQL.NotPrepared'), 'Only the two immediately prepared SQL variables should carry NotPrepared annotations.');
migration_same(1, substr_count($source, 'WordPress.DB.SlowDBQuery.slow_db_query_meta_key'), 'User enumeration should carry one exact slow-meta-key annotation.');
migration_same(1, substr_count($source, 'WordPress.DB.SlowDBQuery.slow_db_query_meta_query'), 'Vendor enumeration should carry one exact slow-meta-query annotation.');
migration_same(0, substr_count($source, 'PluginCheck.Security.DirectDB.UnescapedDBParameter'), 'Prepared identifiers should clear UnescapedDBParameter without suppressing it.');
migration_same(0, substr_count($source, 'WordPress.DB.PreparedSQL.InterpolatedNotPrepared'), 'Prepared identifiers should clear interpolation findings without suppressing them.');
migration_same(2, substr_count($source, 'INSERT INTO %i'), 'Both migration upserts should prepare their table identifier.');
migration_same(1, substr_count($source, 'UPDATE %i SET is_primary'), 'Primary normalization should prepare its table identifier.');
migration_assert(preg_match('/phpcs:(?:disable|enable|ignoreFile)\b/i', $source) !== 1, 'Migration remediation must not use broad PHPCS suppression.');

fwrite(STDOUT, "vendor links migration SQL remediation: PASS\n");
