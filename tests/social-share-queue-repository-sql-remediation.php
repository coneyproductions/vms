<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');

final class VMS_Test_WPDB
{
	public int $insert_id = 700;
	public array $log = array();
	public array $prepares = array();
	public array $row_queue = array();
	public array $rows_queue = array();
	public $insert_return = 1;
	public $update_return = 1;
	public $delete_return = 1;
	public $query_return = 1;

	public function prepare(string $sql, ...$args): string
	{
		if (count($args) === 1 && is_array($args[0])) {
			$args = array_values($args[0]);
		}
		preg_match_all('/(?<!%)%(?:\\d+\\$)?[sdi]/', $sql, $matches);
		if (count($matches[0]) !== count($args)) {
			throw new RuntimeException('Placeholder mismatch: ' . $sql);
		}
		$i = 0;
		$final = (string) preg_replace_callback(
			'/(?<!%)%(?:\\d+\\$)?[sdi]/',
			function (array $match) use (&$i, $args): string {
				$value = $args[$i++];
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

	public function get_row(string $sql, $output = ARRAY_A)
	{
		unset($output);
		$result = $this->shift($this->row_queue, null);
		$this->log[] = array('kind' => 'get_row', 'sql' => $sql, 'result' => $result);
		return $result;
	}

	public function get_results(string $sql, $output = ARRAY_A)
	{
		unset($output);
		$result = $this->shift($this->rows_queue, array());
		$this->log[] = array('kind' => 'get_results', 'sql' => $sql, 'result' => $result);
		return $result;
	}

	public function query(string $sql)
	{
		$this->log[] = array('kind' => 'query', 'sql' => $sql, 'result' => $this->query_return);
		return $this->query_return;
	}

	public function insert(string $table, array $data, array $format)
	{
		$this->log[] = compact('table', 'data', 'format') + array('kind' => 'insert');
		return $this->insert_return;
	}

	public function update(string $table, array $data, array $where, array $format, array $where_format)
	{
		$this->log[] = compact('table', 'data', 'where', 'format', 'where_format') + array('kind' => 'update');
		return $this->update_return;
	}

	public function delete(string $table, array $where, array $where_format)
	{
		$this->log[] = compact('table', 'where', 'where_format') + array('kind' => 'delete');
		return $this->delete_return;
	}

	private function shift(array &$queue, $default)
	{
		return $queue === array() ? $default : array_shift($queue);
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
function wp_json_encode($value) { return json_encode($value); }
function get_current_user_id(): int { return 44; }
function vms_social_now_mysql_utc(): string { return '2026-08-08 01:02:03'; }
function vms_social_table_accounts(): string { return 'wp_vms_social_accounts'; }
function vms_social_table_venue_map(): string { return 'wp_vms_social_venue_map'; }
function vms_social_table_templates(): string { return 'wp_vms_social_templates'; }
function vms_social_table_queue(): string { return 'wp_vms_social_queue'; }
function vms_social_encrypt_json(array $value): string { return 'encrypted:' . (string) json_encode($value); }
function vms_social_decrypt_json(string $value): array
{
	$decoded = json_decode(str_replace('encrypted:', '', $value), true);
	return is_array($decoded) ? $decoded : array();
}

function check($condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}
function same($expected, $actual, string $message): void
{
	check($expected === $actual, $message . "\\nExpected: " . var_export($expected, true) . "\\nActual: " . var_export($actual, true));
}
function contains(string $needle, string $haystack, string $message): void
{
	check(strpos($haystack, $needle) !== false, $message . "\\nMissing: " . $needle . "\\nSQL: " . $haystack);
}
function reset_db(VMS_Test_WPDB $db): void
{
	$db->log = array();
	$db->prepares = array();
	$db->row_queue = array();
	$db->rows_queue = array();
	$db->insert_return = 1;
	$db->update_return = 1;
	$db->delete_return = 1;
	$db->query_return = 1;
	$db->insert_id = 700;
}
function last_prepare(VMS_Test_WPDB $db): array
{
	$call = end($db->prepares);
	check(is_array($call), 'Expected prepare call.');
	return $call;
}
function last_call(VMS_Test_WPDB $db, string $kind): array
{
	for ($i = count($db->log) - 1; $i >= 0; $i--) {
		if (($db->log[$i]['kind'] ?? '') === $kind) {
			return $db->log[$i];
		}
	}
	throw new RuntimeException('Missing call kind: ' . $kind);
}
function kinds(VMS_Test_WPDB $db): array
{
	return array_column($db->log, 'kind');
}

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;
$source_path = dirname(__DIR__) . '/includes/social-share/queue-repo.php';
$source = file_get_contents($source_path);
check(is_string($source), 'Repository source should be readable.');
check(strpos($source, '{' . '$table}') === false, 'Custom-table identifiers must not be interpolated.');
contains('SELECT * FROM %i', $source, 'Reads should prepare identifiers.');
contains('UPDATE %i SET status', $source, 'Claims should prepare identifiers.');
require $source_path;

// Invalid IDs fail closed without executing SQL.
reset_db($wpdb);
same(null, vms_social_account_get(0), 'Account reads should reject zero IDs.');
same(null, vms_social_queue_get(0), 'Queue reads should reject zero IDs.');
same(null, vms_social_queue_latest_for_event(0), 'Latest-event reads should reject zero IDs.');
same(false, vms_social_queue_claim(0), 'Claims should reject zero IDs.');
same(false, vms_social_queue_update(0, array('status' => 'queued')), 'Updates should reject zero IDs.');
same(array(), $wpdb->log, 'Invalid IDs must not touch the database.');

// Account/venue/template read branches prepare identifiers and values.
reset_db($wpdb);
$wpdb->row_queue[] = array('id' => 17, 'platform' => 'webhook');
same(array('id' => 17, 'platform' => 'webhook'), vms_social_account_get(17), 'Account get should preserve its result.');
$prepare = last_prepare($wpdb);
same(array('wp_vms_social_accounts', 17), $prepare['args'], 'Account get should prepare table and ID.');
same('SELECT * FROM `wp_vms_social_accounts` WHERE id = 17', $prepare['final'], 'Account get SQL should be fully rendered.');
same(array('prepare', 'get_row'), kinds($wpdb), 'Account get should prepare before execution.');

reset_db($wpdb);
$wpdb->rows_queue[] = array(array('id' => 9));
same(array(array('id' => 9)), vms_social_account_rows(''), 'Unfiltered accounts should preserve rows.');
$prepare = last_prepare($wpdb);
same(array('wp_vms_social_accounts'), $prepare['args'], 'Unfiltered accounts should prepare the table.');
contains('ORDER BY id DESC', $prepare['final'], 'Unfiltered accounts should retain ordering.');

reset_db($wpdb);
$wpdb->rows_queue[] = array();
vms_social_account_rows('Web Hook!');
same(array('wp_vms_social_accounts', 'webhook'), last_prepare($wpdb)['args'], 'Filtered accounts should sanitize and prepare platform.');

reset_db($wpdb);
$wpdb->rows_queue[] = array(array('venue_id' => 21));
vms_social_venue_map_rows(21);
$prepare = last_prepare($wpdb);
same(array('wp_vms_social_venue_map', 21), $prepare['args'], 'Venue rows should prepare table and venue ID.');
contains('ORDER BY id DESC', $prepare['final'], 'Venue rows should retain ordering.');

reset_db($wpdb);
$wpdb->rows_queue[] = array();
vms_social_venue_map_rows();
same(array('wp_vms_social_venue_map'), last_prepare($wpdb)['args'], 'Unfiltered venue rows should still prepare the table.');

reset_db($wpdb);
$wpdb->row_queue[] = array('id' => 3, 'is_enabled' => 1);
same(array('id' => 3, 'is_enabled' => 1), vms_social_venue_map_for_platform(21, 'Web Hook!'), 'Venue platform lookup should preserve its result.');
$prepare = last_prepare($wpdb);
same(array('wp_vms_social_venue_map', 21, 'webhook'), $prepare['args'], 'Venue platform lookup should prepare its routing boundary.');
contains('is_enabled = 1 ORDER BY id DESC LIMIT 1', $prepare['final'], 'Venue platform lookup should retain enabled/latest selection.');

reset_db($wpdb);
$wpdb->rows_queue[] = array(array('id' => 4));
vms_social_templates_all('');
same(array('wp_vms_social_templates'), last_prepare($wpdb)['args'], 'Unfiltered templates should prepare the table.');

reset_db($wpdb);
$wpdb->rows_queue[] = array();
vms_social_templates_all('Web Hook!');
$prepare = last_prepare($wpdb);
same(array('wp_vms_social_templates', 'webhook'), $prepare['args'], 'Filtered templates should prepare the platform.');
contains('ORDER BY id DESC', $prepare['final'], 'Filtered templates should retain ordering.');

// Queue get/latest/list/due paths retain result, filter, limit, and ordering contracts.
reset_db($wpdb);
$wpdb->row_queue[] = array('id' => 71, 'status' => 'queued');
same(array('id' => 71, 'status' => 'queued'), vms_social_queue_get(71), 'Queue get should preserve its result.');
same(array('wp_vms_social_queue', 71), last_prepare($wpdb)['args'], 'Queue get should prepare table and ID.');

reset_db($wpdb);
$wpdb->row_queue[] = array('id' => 72);
vms_social_queue_latest_for_event(31);
$prepare = last_prepare($wpdb);
same(array('wp_vms_social_queue', 31), $prepare['args'], 'Latest-event lookup should prepare its boundary.');
contains('ORDER BY id DESC LIMIT 1', $prepare['final'], 'Latest-event lookup should remain newest-first.');

reset_db($wpdb);
$wpdb->rows_queue[] = array(array('id' => 99));
$listed = vms_social_queue_list(
	array('status' => 'Queued!', 'platform' => 'Web Hook!', 'venue_id' => 8, 'event_plan_id' => 9),
	999
);
same(array(array('id' => 99)), $listed, 'Queue list should preserve result rows.');
$prepare = last_prepare($wpdb);
same(array('wp_vms_social_queue', 'queued', 'webhook', 8, 9, 500), $prepare['args'], 'Queue list should prepare table, branch filters, and capped limit in order.');
contains('WHERE 1=1 AND status = ', $prepare['final'], 'Queue list should retain the status branch.');
contains('AND venue_id = 8 AND event_plan_id = 9 ORDER BY id DESC LIMIT 500', $prepare['final'], 'Queue list should retain ID filters, ordering, and cap.');
same(array('prepare', 'get_results'), kinds($wpdb), 'Queue list should prepare before execution.');

reset_db($wpdb);
$wpdb->rows_queue[] = array(array('id' => 101));
same(array(array('id' => 101)), vms_social_queue_due_items(0), 'Due-item reads should preserve results.');
$prepare = last_prepare($wpdb);
same(array('wp_vms_social_queue', '2026-08-08 01:02:03', '2026-08-08 01:02:03', 1), $prepare['args'], 'Due-item reads should prepare table, clocks, and bounded limit.');
contains("WHERE status = 'queued'", $prepare['final'], 'Due-item reads should remain queued-only.');
contains('ORDER BY scheduled_at_utc ASC, id ASC', $prepare['final'], 'Due-item reads should preserve scheduling/FIFO order.');

// Atomic claiming retains queued-state compare-and-set and affected-row semantics.
reset_db($wpdb);
$wpdb->query_return = 1;
same(true, vms_social_queue_claim(88), 'One-row compare-and-set should claim the item.');
$prepare = last_prepare($wpdb);
same(array('wp_vms_social_queue', '2026-08-08 01:02:03', 88), $prepare['args'], 'Claim should prepare table, timestamp, and ID.');
contains("WHERE id = 88 AND status = 'queued'", $prepare['final'], 'Claim must retain its atomic queued predicate.');
same(array('prepare', 'query'), kinds($wpdb), 'Claim should prepare before mutation.');
$wpdb->query_return = 0;
same(false, vms_social_queue_claim(88), 'Zero affected rows should report an already-claimed item.');
$wpdb->query_return = false;
same(false, vms_social_queue_claim(88), 'Query failure should report claim failure.');

// Queue creation preserves IDs, snapshots, scheduling, audit fields, and insert identity.
reset_db($wpdb);
$wpdb->insert_id = 812;
$queue_id = vms_social_queue_create(
	array(
		'event_plan_id' => -51,
		'tec_event_id' => 61,
		'venue_id' => 71,
		'platform' => 'Web Hook!',
		'destination_id' => ' destination <b>A</b> ',
		'template_id' => 81,
		'status' => 'not-a-state',
		'payload_snapshot_json' => array('schema' => 'queued', 'audit' => array('source' => 'test')),
		'last_error_code' => 'Old Error!',
		'last_error_message' => ' old <b>message</b> ',
	)
);
same(812, $queue_id, 'Queue creation should return insert_id.');
$insert = last_call($wpdb, 'insert');
same('wp_vms_social_queue', $insert['table'], 'Queue creation should target its custom table.');
same(51, $insert['data']['event_plan_id'], 'Queue creation should normalize event-plan ID.');
same('webhook', $insert['data']['platform'], 'Queue creation should sanitize platform.');
same('destination A', $insert['data']['destination_id'], 'Queue creation should sanitize destination.');
same('queued', $insert['data']['status'], 'Invalid status should retain queued fallback.');
same('2026-08-08 01:02:03', $insert['data']['scheduled_at_utc'], 'Empty schedule should use repository clock.');
same('{"schema":"queued","audit":{"source":"test"}}', $insert['data']['payload_snapshot_json'], 'Queue creation should retain encoded snapshot/audit data.');
same('olderror', $insert['data']['last_error_code'], 'Queue creation should sanitize error code.');
same('old message', $insert['data']['last_error_message'], 'Queue creation should sanitize error message.');
same(44, $insert['data']['created_by'], 'Queue creation should retain current-user default.');
same(count($insert['data']), count($insert['format']), 'Queue insert should retain complete format coverage.');

// The update allowlist and zero-row/failure distinction remain unchanged.
reset_db($wpdb);
$wpdb->update_return = 0;
same(
	true,
	vms_social_queue_update(
		90,
		array(
			'status' => 'invalid-state',
			'attempts' => 4,
			'last_error_message' => ' retry <b>later</b> ',
			'payload_snapshot_json' => '{"audit":"retained"}',
			'event_plan_id' => 999,
		)
	),
	'Zero-row wpdb update should remain a successful repository operation.'
);
$update = last_call($wpdb, 'update');
same(
	array(
		'status' => 'draft',
		'attempts' => 4,
		'last_error_message' => 'retry later',
		'payload_snapshot_json' => '{"audit":"retained"}',
		'updated_at' => '2026-08-08 01:02:03',
	),
	$update['data'],
	'Queue update should preserve allowlist, normalization, snapshot, and field order.'
);
same(array('%s', '%d', '%s', '%s', '%s'), $update['format'], 'Queue update should preserve format order.');
same(array('id' => 90), $update['where'], 'Queue update should retain exact ID boundary.');
$wpdb->update_return = false;
same(false, vms_social_queue_update(90, array('status' => 'posted')), 'wpdb failure should remain false.');
same(false, vms_social_queue_update(90, array('event_plan_id' => 2)), 'Disallowed-only update should fail closed.');

// Cancel/retry retain distinct scheduling, error, and audit-retention semantics.
reset_db($wpdb);
same(true, vms_social_queue_cancel(91), 'Cancel should persist.');
$cancel = last_call($wpdb, 'update');
same(
	array(
		'status' => 'canceled',
		'next_attempt_at_utc' => null,
		'updated_by' => 44,
		'updated_at' => '2026-08-08 01:02:03',
	),
	$cancel['data'],
	'Cancel should clear retry schedule without clearing snapshot/error audit fields.'
);

reset_db($wpdb);
same(true, vms_social_queue_retry(92), 'Retry should persist.');
$retry = last_call($wpdb, 'update');
same(
	array(
		'status' => 'queued',
		'next_attempt_at_utc' => null,
		'last_error_code' => '',
		'last_error_message' => '',
		'updated_by' => 44,
		'updated_at' => '2026-08-08 01:02:03',
	),
	$retry['data'],
	'Retry should clear schedule/errors without touching snapshot or attempt history.'
);

// Metadata patches read before writing, retain existing keys, and do not erase tokens.
reset_db($wpdb);
$wpdb->row_queue[] = array(
	'id' => 23,
	'platform' => 'webhook',
	'label' => 'Primary',
	'token_blob_enc' => 'keep-token',
	'meta_json' => '{"keep":"yes","last_error":"old"}',
);
same(true, vms_social_account_set_auth_state(23, 'Needs Review!', array('last_error' => 'new', 'audit' => 'retained')), 'Auth-state patch should save.');
$account_update = last_call($wpdb, 'update');
$meta = json_decode((string) $account_update['data']['meta_json'], true);
same(array('keep' => 'yes', 'last_error' => 'new', 'audit' => 'retained'), $meta, 'Auth-state patch should merge metadata.');
same(false, array_key_exists('token_blob_enc', $account_update['data']), 'Metadata-only patch must not overwrite encrypted token.');
same(array('prepare', 'get_row', 'update'), kinds($wpdb), 'Metadata patch should read current state before update.');

// Default-template reset must happen before replacement save.
reset_db($wpdb);
$wpdb->insert_id = 333;
same(
	333,
	vms_social_template_save(
		array(
			'platform' => 'Web Hook!',
			'name' => 'Primary',
			'body' => 'Body',
			'is_default' => 1,
			'settings_json' => array('mode' => 'safe'),
		)
	),
	'Default template creation should return insert_id.'
);
same(array('prepare', 'query', 'insert'), kinds($wpdb), 'Default reset must execute before replacement insert.');
$prepare = last_prepare($wpdb);
same(array('wp_vms_social_templates', 'webhook'), $prepare['args'], 'Default reset should prepare table/platform.');
contains('UPDATE `wp_vms_social_templates` SET is_default = 0', $prepare['final'], 'Default reset should target safe identifier.');

// CRUD IDs, formats, and failure semantics remain stable.
reset_db($wpdb);
$wpdb->insert_id = 444;
same(444, vms_social_account_save(array('platform' => 'webhook', 'token_json' => array('token' => 'secret'))), 'Account creation should return insert_id.');
$account_insert = last_call($wpdb, 'insert');
contains('encrypted:', (string) $account_insert['data']['token_blob_enc'], 'Account token should remain encrypted.');
same(count($account_insert['data']), count($account_insert['format']), 'Account insert should retain full format coverage.');

reset_db($wpdb);
$wpdb->insert_id = 555;
same(555, vms_social_venue_map_save(array('venue_id' => 7, 'platform' => 'webhook')), 'Venue-map creation should return insert_id.');
$venue_insert = last_call($wpdb, 'insert');
same(count($venue_insert['data']), count($venue_insert['format']), 'Venue-map insert should retain full format coverage.');

reset_db($wpdb);
$wpdb->update_return = false;
same(19, vms_social_venue_map_save(array('id' => 19, 'venue_id' => 7, 'platform' => 'webhook')), 'Venue-map update should retain ID return contract even when wpdb reports failure.');
$venue_update = last_call($wpdb, 'update');
same(array('id' => 19), $venue_update['where'], 'Venue-map update should retain ID boundary.');

reset_db($wpdb);
$wpdb->delete_return = 0;
same(false, vms_social_account_delete(4), 'Zero-row account delete should remain false.');
$wpdb->delete_return = 1;
same(true, vms_social_venue_map_delete(-5), 'One-row venue delete should remain true after ID normalization.');
same(array('id' => 5), last_call($wpdb, 'delete')['where'], 'Venue delete should use normalized ID.');
$wpdb->delete_return = false;
same(false, vms_social_template_delete(6), 'Failed template delete should remain false.');

// Scanner-target inventory: each of the 22 direct custom-table operations has one narrow annotation.
preg_match_all('/\\$wpdb->(?:get_row|get_results|query|insert|update|delete)\\s*\\(/', $source, $execution_matches);
same(22, count($execution_matches[0]), 'Repository database execution inventory should remain explicit.');
same(
	22,
	substr_count($source, 'WordPress.DB.DirectDatabaseQuery.DirectQuery'),
	'Every direct custom-table operation should have exactly one narrow DirectQuery annotation.'
);
same(2, substr_count($source, 'PluginCheck.Security.DirectDB.UnescapedDBParameter'), 'Only the bounded dynamic queue-list boundary should require Plugin Check parameter annotations.');

fwrite(STDOUT, "social-share queue repository SQL remediation: PASS\n");
