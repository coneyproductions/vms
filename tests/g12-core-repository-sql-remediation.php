<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');

final class WP_Error
{
	private string $code;
	private string $message;

	public function __construct(string $code, string $message)
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

final class VMS_G12_Core_WPDB_Spy
{
	public string $prefix = 'wp_';
	public int $insert_id = 901;
	public array $calls = array();
	public array $prepares = array();
	public array $get_var_queue = array();
	public array $get_results_queue = array();
	public array $get_row_queue = array();
	public array $insert_queue = array();
	public array $delete_queue = array();

	public function prepare(string $template, ...$args): string
	{
		if (count($args) === 1 && is_array($args[0])) {
			$args = array_values($args[0]);
		}

		preg_match_all('/(?<!%)%(?:\d+\$)?[sdfi]/', $template, $matches);
		if (count($matches[0]) !== count($args)) {
			throw new RuntimeException('Prepared-query placeholder mismatch: ' . $template);
		}

		$index = 0;
		$sql = preg_replace_callback(
			'/(?<!%)%(?:\d+\$)?[sdfi]/',
			static function (array $match) use (&$index, $args): string {
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
		if (!is_string($sql) || preg_match('/(?<!%)%(?:\d+\$)?[sdfi]/', $sql) === 1) {
			throw new RuntimeException('Prepared query retained an unresolved placeholder: ' . $template);
		}

		$call = array(
			'kind' => 'prepare',
			'template' => $template,
			'args' => array_values($args),
			'sql' => $sql,
		);
		$this->prepares[] = $call;
		$this->calls[] = $call;
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
		$result = $this->shift($this->get_results_queue, array());
		$this->calls[] = array('kind' => 'get_results', 'sql' => $sql, 'output' => $output, 'result' => $result);
		return $result;
	}

	public function get_row(string $sql, $output = ARRAY_A)
	{
		$result = $this->shift($this->get_row_queue, null);
		$this->calls[] = array('kind' => 'get_row', 'sql' => $sql, 'output' => $output, 'result' => $result);
		return $result;
	}

	public function insert(string $table, array $data, array $format)
	{
		$result = $this->shift($this->insert_queue, 1);
		$this->calls[] = array(
			'kind' => 'insert',
			'table' => $table,
			'data' => $data,
			'format' => $format,
			'result' => $result,
		);
		return $result;
	}

	public function delete(string $table, array $where, array $where_format)
	{
		$result = $this->shift($this->delete_queue, 1);
		$this->calls[] = array(
			'kind' => 'delete',
			'table' => $table,
			'where' => $where,
			'where_format' => $where_format,
			'result' => $result,
		);
		return $result;
	}

	public function reset(): void
	{
		$this->insert_id = 901;
		$this->calls = array();
		$this->prepares = array();
		$this->get_var_queue = array();
		$this->get_results_queue = array();
		$this->get_row_queue = array();
		$this->insert_queue = array();
		$this->delete_queue = array();
	}

	private function shift(array &$queue, $default)
	{
		return $queue === array() ? $default : array_shift($queue);
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

function sanitize_key($value): string
{
	$value = is_scalar($value) ? strtolower((string) $value) : '';
	$clean = preg_replace('/[^a-z0-9_\-]+/', '', $value);
	return is_string($clean) ? $clean : '';
}

function sanitize_text_field($value): string
{
	return is_scalar($value) ? trim(strip_tags((string) $value)) : '';
}

function sanitize_textarea_field($value): string
{
	return sanitize_text_field($value);
}

function sanitize_file_name($value): string
{
	$value = sanitize_text_field($value);
	$value = preg_replace('/\s+/', '-', $value);
	$value = preg_replace('/[^A-Za-z0-9._\-]/', '', is_string($value) ? $value : '');
	return is_string($value) ? $value : '';
}

function current_time(string $type, bool $gmt = false): string
{
	$GLOBALS['g12_current_time_calls'][] = array($type, $gmt);
	return '2026-08-08 03:04:05';
}

function wp_json_encode($value): string
{
	$encoded = json_encode($value);
	return is_string($encoded) ? $encoded : '';
}

function get_current_user_id(): int
{
	return 44;
}

function wp_normalize_path(string $path): string
{
	return str_replace('\\', '/', $path);
}

function vms_notify_log_table_name(): string
{
	return (string) $GLOBALS['g12_notify_table'];
}

function vms_notify_sanitize_template_key(string $template_key): string
{
	$template_key = strtolower(trim($template_key));
	return (string) preg_replace('/[^a-z0-9._-]/', '', $template_key);
}

function vms_notify_redact_payload_for_log($value)
{
	return $value;
}

function vms_private_files_table(): string
{
	return (string) $GLOBALS['g12_private_table'];
}

function vms_private_files_validate_storage_key(string $storage_key): string
{
	$storage_key = trim(str_replace('\\', '/', $storage_key));
	if ($storage_key === '' || $storage_key[0] === '/' || strpos($storage_key, '..') !== false || strpos($storage_key, ':') !== false) {
		return '';
	}
	return $storage_key;
}

function vms_private_files_safe_download_name(string $filename, string $fallback_base = 'download'): string
{
	$filename = sanitize_file_name($filename);
	if ($filename !== '') {
		return $filename;
	}
	$fallback_base = sanitize_file_name($fallback_base);
	return $fallback_base !== '' ? $fallback_base : 'download';
}

function vms_private_files_absolute_path(string $storage_key): string
{
	return isset($GLOBALS['g12_private_paths'][$storage_key])
		? (string) $GLOBALS['g12_private_paths'][$storage_key]
		: '';
}

function vms_private_files_path_is_safe(string $path): bool
{
	return !empty($GLOBALS['g12_safe_paths'][$path]);
}

function wp_delete_file(string $path): void
{
	$GLOBALS['g12_deleted_paths'][] = $path;
	if (is_file($path)) {
		unlink($path);
	}
}

function g12_check(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g12_same($expected, $actual, string $message): void
{
	g12_check(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

function g12_contains(string $needle, string $haystack, string $message): void
{
	g12_check(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function g12_not_contains(string $needle, string $haystack, string $message): void
{
	g12_check(strpos($haystack, $needle) === false, $message . "\nUnexpected: " . $needle);
}

function g12_calls(VMS_G12_Core_WPDB_Spy $db, string $kind): array
{
	return array_values(array_filter(
		$db->calls,
		static function (array $call) use ($kind): bool {
			return ($call['kind'] ?? '') === $kind;
		}
	));
}

function g12_extract_function(string $source, string $name): string
{
	$start = strpos($source, 'function ' . $name . '(');
	$brace = $start === false ? false : strpos($source, '{', $start);
	if ($start === false || $brace === false) {
		throw new RuntimeException('Unable to locate function ' . $name . '.');
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

function g12_validate_db_suppressions(string $source): void
{
	if (preg_match('/phpcs:(?:disable|enable|ignoreFile)\b/i', $source) === 1) {
		throw new RuntimeException('Broad PHPCS suppression is forbidden in the G12 core slice.');
	}
	$allowed = array(
		'WordPress.DB.DirectDatabaseQuery.DirectQuery' => true,
		'WordPress.DB.DirectDatabaseQuery.NoCaching' => true,
	);
	foreach (preg_split('/\R/', $source) ?: array() as $line) {
		if (
			strpos($line, 'phpcs:') === false
			|| (
				strpos($line, 'WordPress.DB.') === false
				&& strpos($line, 'PluginCheck.Security.DirectDB.') === false
			)
		) {
			continue;
		}
		if (!preg_match('/phpcs:ignore ([^\s]+) -- (.+)$/', $line, $match)) {
			throw new RuntimeException('Every database suppression must be exact, one-line, and justified: ' . $line);
		}
		foreach (explode(',', $match[1]) as $code) {
			if (!isset($allowed[$code])) {
				throw new RuntimeException('Unapproved database suppression: ' . $code);
			}
		}
		if (strlen(trim($match[2])) < 40) {
			throw new RuntimeException('Database suppression lacks an operation-specific reason.');
		}
	}
}

function g12_assert_no_unresolved_sql(VMS_G12_Core_WPDB_Spy $db): void
{
	foreach ($db->calls as $call) {
		if (!isset($call['sql']) || ($call['kind'] ?? '') === 'prepare') {
			continue;
		}
		g12_check(
			preg_match('/(?<!%)%(?:\d+\$)?[sdfi]/', (string) $call['sql']) !== 1,
			'Executed SQL retained an unresolved placeholder: ' . (string) $call['sql']
		);
	}
}

$root = dirname(__DIR__);
$notify_path = $root . '/includes/core/notifications.php';
$private_path = $root . '/includes/core/private-files.php';
$notify_source = (string) file_get_contents($notify_path);
$private_source = (string) file_get_contents($private_path);
g12_check($notify_source !== '', 'Notification source should be readable.');
g12_check($private_source !== '', 'Private-file source should be readable.');

$shadow_root = dirname($root, 2) . '/vms';
$shadow_notify_path = $shadow_root . '/includes/core/notifications.php';
$shadow_private_path = $shadow_root . '/includes/core/private-files.php';
$shadow_notify_source = (string) file_get_contents($shadow_notify_path);
g12_same($notify_source, $shadow_notify_source, 'Notification mirror/shadow-live full-file parity changed.');
g12_check(!file_exists($shadow_private_path), 'The intentionally mirror-only private-files runtime must not gain a shadow-live counterpart.');
$mirror_load = (string) file_get_contents($root . '/includes/core/load.php');
$shadow_load = (string) file_get_contents($shadow_root . '/includes/core/load.php');
g12_contains("require_once __DIR__ . '/private-files.php';", $mirror_load, 'Mirror core load should retain the private-file broker.');
g12_not_contains("require_once __DIR__ . '/private-files.php';", $shadow_load, 'Shadow-live core load must not gain the mirror-only private-file broker.');

$historical_rows = array(
	'includes/core/notifications.php:360:15:WordPress.DB.DirectDatabaseQuery.DirectQuery',
	'includes/core/notifications.php:745:28:WordPress.DB.DirectDatabaseQuery.DirectQuery',
	'includes/core/notifications.php:745:28:WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/notifications.php:751:17:WordPress.DB.DirectDatabaseQuery.DirectQuery',
	'includes/core/notifications.php:751:17:WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/notifications.php:751:18:PluginCheck.Security.DirectDB.UnescapedDBParameter',
	'includes/core/notifications.php:751:30:WordPress.DB.PreparedSQL.NotPrepared',
	'includes/core/private-files.php:352:16:WordPress.DB.DirectDatabaseQuery.DirectQuery',
	'includes/core/private-files.php:352:16:WordPress.DB.DirectDatabaseQuery.NoCaching',
	'includes/core/private-files.php:352:17:PluginCheck.Security.DirectDB.UnescapedDBParameter',
	'includes/core/private-files.php:352:40:WordPress.DB.PreparedSQL.InterpolatedNotPrepared',
	'includes/core/private-files.php:475:21:WordPress.DB.DirectDatabaseQuery.DirectQuery',
	'includes/core/private-files.php:642:20:WordPress.DB.DirectDatabaseQuery.DirectQuery',
	'includes/core/private-files.php:642:20:WordPress.DB.DirectDatabaseQuery.NoCaching',
);
g12_same(14, count($historical_rows), 'The Wave 3 G12 core inventory should remain exactly 14 packaged DB rows.');
$historical_file_counts = array();
$historical_code_counts = array();
foreach ($historical_rows as $row) {
	$parts = explode(':', $row, 4);
	$historical_file_counts[$parts[0]] = ($historical_file_counts[$parts[0]] ?? 0) + 1;
	$historical_code_counts[$parts[3]] = ($historical_code_counts[$parts[3]] ?? 0) + 1;
}
ksort($historical_file_counts);
ksort($historical_code_counts);
$expected_file_counts = array(
	'includes/core/notifications.php' => 7,
	'includes/core/private-files.php' => 7,
);
$expected_code_delta = array(
	'PluginCheck.Security.DirectDB.UnescapedDBParameter' => 2,
	'WordPress.DB.DirectDatabaseQuery.DirectQuery' => 6,
	'WordPress.DB.DirectDatabaseQuery.NoCaching' => 4,
	'WordPress.DB.PreparedSQL.InterpolatedNotPrepared' => 1,
	'WordPress.DB.PreparedSQL.NotPrepared' => 1,
);
ksort($expected_file_counts);
ksort($expected_code_delta);
g12_same($expected_file_counts, $historical_file_counts, 'The historical per-file row inventory changed.');
g12_same($expected_code_delta, $historical_code_counts, 'The exact expected G12 core rule delta changed.');

$notify_insert_source = g12_extract_function($notify_source, 'vms_notify_insert_log');
$notify_recent_source = g12_extract_function($notify_source, 'vms_notify_recent_logs');
$private_get_source = g12_extract_function($private_source, 'vms_private_file_get');
$private_register_source = g12_extract_function($private_source, 'vms_private_files_register_path');
$private_path_source = g12_extract_function($private_source, 'vms_private_file_path');
$private_delete_source = g12_extract_function($private_source, 'vms_private_files_delete');
$notify_owned_source = $notify_insert_source . "\n" . $notify_recent_source;
$private_owned_source = $private_get_source . "\n" . $private_register_source . "\n" . $private_delete_source;

g12_same(3, substr_count($notify_owned_source, 'WordPress.DB.DirectDatabaseQuery.DirectQuery'), 'Notification operations should retain exactly three narrow DirectQuery annotations.');
g12_same(2, substr_count($notify_owned_source, 'WordPress.DB.DirectDatabaseQuery.NoCaching'), 'Notification reads should retain exactly two narrow NoCaching annotations.');
g12_same(3, substr_count($private_owned_source, 'WordPress.DB.DirectDatabaseQuery.DirectQuery'), 'Private-file operations should retain exactly three narrow DirectQuery annotations.');
g12_same(2, substr_count($private_owned_source, 'WordPress.DB.DirectDatabaseQuery.NoCaching'), 'Private-file read/delete boundaries should retain exactly two narrow NoCaching annotations.');
g12_validate_db_suppressions($notify_owned_source);
g12_validate_db_suppressions($private_owned_source);
foreach (
	array(
		'PluginCheck.Security.DirectDB.UnescapedDBParameter',
		'WordPress.DB.PreparedSQL.InterpolatedNotPrepared',
		'WordPress.DB.PreparedSQL.NotPrepared',
	) as $forbidden_suppression
) {
	g12_not_contains($forbidden_suppression, $notify_owned_source, 'Notification SQL safety must be implemented instead of suppressed.');
	g12_not_contains($forbidden_suppression, $private_owned_source, 'Private-file SQL safety must be implemented instead of suppressed.');
}
foreach (
	array(
		$notify_owned_source . "\n// phpcs:disable WordPress.DB",
		$private_owned_source . "\n// phpcs:ignore WordPress.DB.PreparedSQL -- invented family suppression",
	) as $negative_scope
) {
	$rejected = false;
	try {
		g12_validate_db_suppressions($negative_scope);
	} catch (RuntimeException $exception) {
		$rejected = true;
	}
	g12_check($rejected, 'The broad-suppression negative control should be rejected.');
}

g12_contains("'SHOW TABLES LIKE %s'", $notify_recent_source, 'Notification schema probing should retain its prepared value placeholder.');
g12_contains("'SELECT * FROM %i ORDER BY id DESC LIMIT %d'", $notify_recent_source, 'Notification history should prepare its table identifier and bounded limit.');
g12_not_contains('$sql = "SELECT * FROM {$table} ORDER BY id DESC LIMIT "', $notify_recent_source, 'Notification history must not rebuild concatenated SQL.');
g12_contains("'SELECT * FROM %i WHERE id = %d'", $private_get_source, 'Private-file lookup should prepare its table identifier and file ID.');
g12_not_contains('SELECT * FROM {$table} WHERE id = %d', $private_get_source, 'Private-file lookup must not interpolate its table identifier.');
g12_same(1, substr_count($notify_source, 'error_log('), 'The adjacent G16 notification error_log row must remain exactly unchanged.');
g12_not_contains('DevelopmentFunctions.error_log_error_log', $notify_source, 'This DB slice must not suppress the deferred G16 notification log row.');
g12_same(1, substr_count($private_source, '@chmod($destination, 0640)'), 'Private upload permissions must retain the exact 0640 boundary.');
g12_same(3, substr_count($private_source, 'wp_delete_file('), 'Private-file mismatch, rollback, and deletion cleanup paths must remain intact.');

eval($notify_insert_source);
eval($notify_recent_source);
eval($private_get_source);
eval($private_register_source);
eval($private_path_source);
eval($private_delete_source);

$wpdb = new VMS_G12_Core_WPDB_Spy();
$GLOBALS['wpdb'] = $wpdb;
$GLOBALS['g12_notify_table'] = '';
$GLOBALS['g12_private_table'] = 'wp_vms_private_files';
$GLOBALS['g12_private_paths'] = array();
$GLOBALS['g12_safe_paths'] = array();
$GLOBALS['g12_deleted_paths'] = array();
$GLOBALS['g12_current_time_calls'] = array();

// Invalid notification repository state fails closed before any database access.
vms_notify_insert_log(array('event_key' => 'ignored'));
g12_same(array(), $wpdb->calls, 'An unavailable notification table should reject inserts without touching wpdb.');
g12_same(array(), vms_notify_recent_logs(10), 'An unavailable notification table should reject history reads.');
g12_same(array(), $wpdb->calls, 'Rejected notification history should not touch wpdb.');

// Notification insertion retains sanitization, payload, UTC timestamp, field order, and exact formats.
$wpdb->reset();
$GLOBALS['g12_notify_table'] = 'wp_vms_notify_log';
$wpdb->insert_queue[] = 1;
vms_notify_insert_log(
	array(
		'source' => 'Core Source!',
		'event_key' => 'Task Assigned!',
		'recipient_user_id' => -9,
		'recipient_address' => '<b>person@example.test</b>',
		'channel' => 'fax',
		'locale' => '<i>es_MX</i>',
		'template_key' => 'Staff Tasks.Task Assigned!',
		'payload' => array('task_id' => 17),
		'provider' => 'Core Email!',
		'provider_message_id' => '<b>provider-22</b>',
		'status' => 'unexpected',
		'error_message' => '<b>retry later</b>',
	)
);
$insert_calls = g12_calls($wpdb, 'insert');
g12_same(1, count($insert_calls), 'Notification logging should perform one insert.');
$notify_insert = $insert_calls[0];
g12_same('wp_vms_notify_log', $notify_insert['table'], 'Notification logging should target its plugin-owned table.');
g12_same(
	array(
		'created_at' => '2026-08-08 03:04:05',
		'source' => 'coresource',
		'event_key' => 'taskassigned',
		'recipient_user_id' => 9,
		'recipient_address' => 'person@example.test',
		'channel' => 'email',
		'locale' => 'es_MX',
		'template_key' => 'stafftasks.taskassigned',
		'payload_json' => '{"task_id":17}',
		'provider' => 'coreemail',
		'provider_message_id' => 'provider-22',
		'status' => 'failed',
		'error_message' => 'retry later',
	),
	$notify_insert['data'],
	'Notification insert row normalization changed.'
);
g12_same(
	array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
	$notify_insert['format'],
	'Notification insert formats changed.'
);
g12_same(count($notify_insert['data']), count($notify_insert['format']), 'Every notification field should retain one format.');
g12_same(array(array('mysql', true)), $GLOBALS['g12_current_time_calls'], 'Notification logging should retain its UTC timestamp request.');

// wpdb insert failure remains observable through the existing deferred G16 fallback.
$wpdb->reset();
$wpdb->insert_queue[] = false;
$log_path = tempnam(sys_get_temp_dir(), 'bvm-g12-notify-');
g12_check(is_string($log_path) && $log_path !== '', 'A temporary notification failure log should be available.');
$previous_error_log = ini_get('error_log');
$previous_log_errors = ini_get('log_errors');
ini_set('error_log', $log_path);
ini_set('log_errors', '1');
vms_notify_insert_log(array('event_key' => 'Failed Event!'));
ini_set('error_log', is_string($previous_error_log) ? $previous_error_log : '');
ini_set('log_errors', is_string($previous_log_errors) ? $previous_log_errors : '1');
$failure_log = (string) file_get_contents($log_path);
unlink($log_path);
g12_contains('[VMS Notify] Failed to insert notification log row for event_key=failedevent', $failure_log, 'Notification insert failure logging changed.');

// Schema failure stops before history reads and preserves the prepared SHOW probe.
$wpdb->reset();
$wpdb->get_var_queue[] = null;
g12_same(array(), vms_notify_recent_logs(8), 'Missing notification schema should fail closed.');
g12_same(1, count($wpdb->prepares), 'Missing notification schema should perform only one prepare.');
g12_same('SHOW TABLES LIKE %s', $wpdb->prepares[0]['template'], 'Notification schema probe SQL changed.');
g12_same(array('wp_vms_notify_log'), $wpdb->prepares[0]['args'], 'Notification schema probe arguments changed.');
g12_same(0, count(g12_calls($wpdb, 'get_results')), 'Missing notification schema must not read history rows.');
g12_assert_no_unresolved_sql($wpdb);

// History reads preserve row shape, failure fallback, DESC order, both limit clamps, and no persistent cache.
$wpdb->reset();
$wpdb->get_var_queue[] = 'wp_vms_notify_log';
$wpdb->get_results_queue[] = array(array('id' => 71, 'status' => 'sent'));
$minimum_rows = vms_notify_recent_logs(0);
g12_same(array(array('id' => 71, 'status' => 'sent')), $minimum_rows, 'Notification history row shape changed.');
g12_same('SELECT * FROM %i ORDER BY id DESC LIMIT %d', $wpdb->prepares[1]['template'], 'Notification history SQL shape changed.');
g12_same(array('wp_vms_notify_log', 1), $wpdb->prepares[1]['args'], 'Notification minimum-limit preparation changed.');
g12_same('SELECT * FROM `wp_vms_notify_log` ORDER BY id DESC LIMIT 1', $wpdb->prepares[1]['sql'], 'Notification minimum-limit SQL changed.');
g12_assert_no_unresolved_sql($wpdb);

$wpdb->reset();
$wpdb->get_var_queue = array('wp_vms_notify_log', 'wp_vms_notify_log');
$wpdb->get_results_queue = array(
	array(array('id' => 72, 'status' => 'failed')),
	array(array('id' => 73, 'status' => 'skipped')),
);
$first_history = vms_notify_recent_logs(-500);
$second_history = vms_notify_recent_logs(500);
g12_same(72, $first_history[0]['id'], 'First notification history read changed.');
g12_same(73, $second_history[0]['id'], 'Repeated notification history should remain request-fresh.');
g12_same(2, count(g12_calls($wpdb, 'get_results')), 'Repeated notification history should query twice without a persistent cache.');
g12_same(array('wp_vms_notify_log', 100), $wpdb->prepares[1]['args'], 'Negative notification limits should retain absint and maximum clamping.');
g12_same(array('wp_vms_notify_log', 100), $wpdb->prepares[3]['args'], 'Large notification limits should retain maximum clamping.');
g12_assert_no_unresolved_sql($wpdb);

$wpdb->reset();
$wpdb->get_var_queue[] = 'wp_vms_notify_log';
$wpdb->get_results_queue[] = false;
g12_same(array(), vms_notify_recent_logs(12), 'Notification database read failure should retain an empty-array result.');

// Private-file lookup rejects invalid IDs and rereads authorization-sensitive rows without caching.
$wpdb->reset();
g12_same(null, vms_private_file_get(0), 'Invalid private-file IDs should fail closed.');
g12_same(array(), $wpdb->calls, 'Invalid private-file IDs should not touch wpdb.');
$wpdb->get_row_queue[] = false;
g12_same(null, vms_private_file_get(18), 'Private-file read failure should retain a null result.');
g12_same('SELECT * FROM %i WHERE id = %d', $wpdb->prepares[0]['template'], 'Private-file lookup SQL shape changed.');
g12_same(array('wp_vms_private_files', 18), $wpdb->prepares[0]['args'], 'Private-file lookup preparation order changed.');
g12_assert_no_unresolved_sql($wpdb);

$wpdb->reset();
$wpdb->get_row_queue = array(
	array('id' => 19, 'stored_filename' => 'tax-docs/first.pdf', 'related_post_id' => 81),
	array('id' => 19, 'stored_filename' => 'tax-docs/second.pdf', 'related_post_id' => 82),
);
$first_private = vms_private_file_get(19);
$second_private = vms_private_file_get(19);
g12_same('tax-docs/first.pdf', $first_private['stored_filename'], 'First private-file row changed.');
g12_same('tax-docs/second.pdf', $second_private['stored_filename'], 'Repeated private-file lookup should observe current authorization metadata.');
g12_same(2, count(g12_calls($wpdb, 'get_row')), 'Repeated private-file lookup should query twice without a persistent cache.');
foreach ($wpdb->prepares as $prepare) {
	g12_same(array('wp_vms_private_files', 19), $prepare['args'], 'Repeated private-file lookup arguments changed.');
}
g12_assert_no_unresolved_sql($wpdb);

// Private-file registration retains validation/failure behavior and the exact metadata/format contract.
$wpdb->reset();
$invalid_registration = vms_private_files_register_path('', '', '', '', array());
g12_check($invalid_registration instanceof WP_Error, 'Invalid private-file registration should return WP_Error.');
g12_same('private_upload_register_failed', $invalid_registration->get_error_code(), 'Invalid private-file registration error code changed.');
g12_same(array(), g12_calls($wpdb, 'insert'), 'Invalid private-file registration should not insert.');

$registered_path = tempnam(sys_get_temp_dir(), 'bvm-g12-private-');
g12_check(is_string($registered_path) && $registered_path !== '', 'A temporary private file should be available.');
file_put_contents($registered_path, 'private payload');
$storage_key = 'tax-docs/file.pdf';
$GLOBALS['g12_private_paths'][$storage_key] = $registered_path;
$GLOBALS['g12_safe_paths'][$registered_path] = true;
$wpdb->insert_queue[] = false;
$failed_registration = vms_private_files_register_path($storage_key, $registered_path, 'W 9.pdf', 'application/pdf', array());
g12_check($failed_registration instanceof WP_Error, 'Failed private-file insert should return WP_Error.');
g12_same('private_upload_register_failed', $failed_registration->get_error_code(), 'Failed private-file insert error code changed.');

$wpdb->reset();
$wpdb->insert_id = 909;
$wpdb->insert_queue[] = 1;
$registered_id = vms_private_files_register_path(
	$storage_key,
	$registered_path,
	'<b>W 9.pdf</b>',
	'application/pdf',
	array(
		'created_by' => -12,
		'related_post_type' => 'VMS Vendor!',
		'related_post_id' => -77,
	)
);
g12_same(909, $registered_id, 'Successful private-file registration should return insert_id.');
$private_insert = g12_calls($wpdb, 'insert')[0];
g12_same('wp_vms_private_files', $private_insert['table'], 'Private-file registration table changed.');
g12_same(
	array(
		'original_filename' => 'W-9.pdf',
		'stored_filename' => $storage_key,
		'mime_type' => 'application/pdf',
		'file_size' => strlen('private payload'),
		'sha256' => hash('sha256', 'private payload'),
		'created_at' => '2026-08-08 03:04:05',
		'created_by' => 12,
		'related_post_type' => 'vmsvendor',
		'related_post_id' => 77,
	),
	$private_insert['data'],
	'Private-file metadata row contract changed.'
);
g12_same(
	array('%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%d'),
	$private_insert['format'],
	'Private-file insert formats changed.'
);
g12_same(count($private_insert['data']), count($private_insert['format']), 'Every private-file metadata field should retain one format.');

// Private-file deletion preserves lookup gates, bounded cleanup, false failure, and zero-row success semantics.
$wpdb->reset();
g12_same(false, vms_private_files_delete(0), 'Invalid private-file deletion should fail closed.');
g12_same(array(), $wpdb->calls, 'Invalid private-file deletion should not touch wpdb.');
$wpdb->get_row_queue[] = null;
g12_same(false, vms_private_files_delete(25), 'Missing private-file rows should fail closed.');
g12_same(0, count(g12_calls($wpdb, 'delete')), 'Missing private-file rows should not issue deletion.');

$delete_failure_path = tempnam(sys_get_temp_dir(), 'bvm-g12-delete-fail-');
g12_check(is_string($delete_failure_path) && $delete_failure_path !== '', 'A temporary private deletion file should be available.');
file_put_contents($delete_failure_path, 'delete failure path');
$GLOBALS['g12_private_paths']['tax-docs/delete-fail.pdf'] = $delete_failure_path;
$GLOBALS['g12_safe_paths'][$delete_failure_path] = true;
$wpdb->reset();
$wpdb->get_row_queue[] = array('id' => 26, 'stored_filename' => 'tax-docs/delete-fail.pdf');
$wpdb->delete_queue[] = false;
g12_same(false, vms_private_files_delete(26), 'wpdb deletion failure should remain false.');
g12_check(!file_exists($delete_failure_path), 'Bounded local cleanup should remain complete before a database deletion failure.');
$failed_delete = g12_calls($wpdb, 'delete')[0];
g12_same('wp_vms_private_files', $failed_delete['table'], 'Private-file deletion table changed.');
g12_same(array('id' => 26), $failed_delete['where'], 'Private-file deletion predicate changed.');
g12_same(array('%d'), $failed_delete['where_format'], 'Private-file deletion format changed.');

$delete_success_path = tempnam(sys_get_temp_dir(), 'bvm-g12-delete-ok-');
g12_check(is_string($delete_success_path) && $delete_success_path !== '', 'A second temporary private deletion file should be available.');
file_put_contents($delete_success_path, 'delete success path');
$GLOBALS['g12_private_paths']['tax-docs/delete-ok.pdf'] = $delete_success_path;
$GLOBALS['g12_safe_paths'][$delete_success_path] = true;
$wpdb->reset();
$wpdb->get_row_queue[] = array('id' => 27, 'stored_filename' => 'tax-docs/delete-ok.pdf');
$wpdb->delete_queue[] = 0;
g12_same(true, vms_private_files_delete(27), 'A zero-row wpdb delete should retain the existing non-false success contract.');
g12_check(!file_exists($delete_success_path), 'Successful private-file deletion should retain bounded local cleanup.');
g12_same(array('id' => 27), g12_calls($wpdb, 'delete')[0]['where'], 'Successful private-file deletion predicate changed.');
g12_assert_no_unresolved_sql($wpdb);

if (is_file($registered_path)) {
	unlink($registered_path);
}

fwrite(STDOUT, "g12 core repository SQL remediation: PASS\n");
