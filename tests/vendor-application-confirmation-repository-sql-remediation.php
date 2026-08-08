<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');
define('MINUTE_IN_SECONDS', 60);

final class WP_Error
{
	private string $code;
	private string $message;
	private $data = null;

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

	public function add_data($data): void
	{
		$this->data = $data;
	}

	public function get_error_data()
	{
		return $this->data;
	}
}

final class WP_User
{
	public int $ID;
	public string $user_email;

	public function __construct(int $id = 88, string $email = 'vendor@example.test')
	{
		$this->ID = $id;
		$this->user_email = $email;
	}
}

final class VMS_Confirmation_WPDB_Spy
{
	public string $prefix = 'wp_';
	public int $insert_id = 901;
	public array $prepares = array();
	public array $calls = array();
	public array $get_row_queue = array();
	public array $query_queue = array();
	public array $update_queue = array();
	public array $insert_queue = array();

	public function prepare(string $query, ...$args): string
	{
		if (count($args) === 1 && is_array($args[0])) {
			$args = array_values($args[0]);
		}
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
					return chr(96) . str_replace(chr(96), chr(96) . chr(96), (string) $value) . chr(96);
				}
				return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $value) . "'";
			},
			$query
		);
		$this->prepares[] = array('query' => $query, 'args' => $args, 'sql' => $sql);
		return $sql;
	}

	public function get_row(string $sql, $output = ARRAY_A)
	{
		unset($output);
		$result = $this->shift($this->get_row_queue, null);
		$this->calls[] = array('kind' => 'get_row', 'sql' => $sql, 'result' => $result);
		return $result;
	}

	public function query(string $sql)
	{
		$result = $this->shift($this->query_queue, 1);
		$call = array('kind' => 'query', 'sql' => $sql, 'result' => $result);
		$this->calls[] = $call;
		vms_test_event('query', $call);
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

	public function insert(string $table, array $data, array $format)
	{
		$result = $this->shift($this->insert_queue, 1);
		$call = compact('table', 'data', 'format', 'result');
		$call['kind'] = 'insert';
		$this->calls[] = $call;
		vms_test_event('insert', $call);
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
	$value = is_scalar($value) ? preg_replace('/[^a-z0-9_\-]+/i', '', strtolower((string) $value)) : '';
	return is_string($value) ? $value : '';
}

function sanitize_text_field($value): string
{
	return is_scalar($value) ? trim(strip_tags((string) $value)) : '';
}

function sanitize_email($value): string
{
	$value = is_scalar($value) ? filter_var((string) $value, FILTER_SANITIZE_EMAIL) : '';
	return is_string($value) ? strtolower(trim($value)) : '';
}

function is_email($value): bool
{
	return filter_var((string) $value, FILTER_VALIDATE_EMAIL) !== false;
}

function is_wp_error($value): bool
{
	return $value instanceof WP_Error;
}

function current_time(string $type, bool $gmt = false): string
{
	unset($type, $gmt);
	return '2026-08-07 21:15:00';
}

function get_current_user_id(): int
{
	return 17;
}

function vms_vendor_app_meta_key(string $name): string
{
	return '_vms_app_' . sanitize_key($name);
}

function vms_vendor_app_cpt_slugs(): array
{
	return array('vms_vendor_application');
}

function vms_vendor_app_generate_raw_confirmation_token(): string
{
	return 'raw-confirmation-token';
}

function vms_vendor_app_hash_confirmation_token(string $raw): string
{
	return hash('sha256', 'test|' . trim($raw));
}

function vms_vendor_app_get_confirmation_email(int $app_id): string
{
	return sanitize_email((string) ($GLOBALS['vms_app_emails'][$app_id] ?? ''));
}

function vms_vendor_app_confirmation_window_seconds(): int
{
	return 172800;
}

function vms_vendor_app_confirmation_endpoint_url(array $args = array()): string
{
	return 'https://example.test/availability-dispatch/confirm?' . http_build_query($args);
}

function vms_request_remote_addr(): string
{
	return '203.0.113.9';
}

function vms_request_user_agent(): string
{
	return 'Repository Test Agent';
}

function get_posts(array $args): array
{
	$GLOBALS['vms_get_posts_calls'][] = $args;
	return $GLOBALS['vms_get_posts_queue'] === array() ? array() : array_shift($GLOBALS['vms_get_posts_queue']);
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	unset($single);
	return $GLOBALS['vms_post_meta'][$post_id][$key] ?? '';
}

function update_post_meta(int $post_id, string $key, $value): bool
{
	$GLOBALS['vms_post_meta'][$post_id][$key] = $value;
	vms_test_event('update_post_meta', compact('post_id', 'key', 'value'));
	return true;
}

function get_transient(string $key)
{
	return $GLOBALS['vms_transients'][$key] ?? false;
}

function set_transient(string $key, $value, int $expiration): bool
{
	$GLOBALS['vms_transients'][$key] = $value;
	vms_test_event('set_transient', compact('key', 'value', 'expiration'));
	return true;
}

function get_userdata(int $user_id)
{
	return $GLOBALS['vms_users'][$user_id] ?? false;
}

function get_the_title(int $post_id): string
{
	return (string) ($GLOBALS['vms_titles'][$post_id] ?? '');
}

function wp_strip_all_tags(string $text): string
{
	return strip_tags($text);
}

function vms_vendor_app_get_status(int $app_id): string
{
	return (string) ($GLOBALS['vms_statuses'][$app_id] ?? 'pending');
}

function vms_vendor_app_get_confirmation_state(int $app_id): string
{
	return (string) ($GLOBALS['vms_states'][$app_id] ?? 'unconfirmed');
}

function vms_vendor_app_confirmation_attempt_rate_limited(string $raw_token): bool
{
	unset($raw_token);
	return (bool) ($GLOBALS['vms_rate_limited'] ?? false);
}

function vms_vendor_app_note_confirmation_attempt_failure(string $raw_token): void
{
	vms_test_event('attempt_failure', array('raw_token' => $raw_token));
}

function get_user_by(string $field, string $value)
{
	unset($field, $value);
	return $GLOBALS['vms_existing_user'] ?? false;
}

function vms_vendor_app_resolve_or_create_user_for_email(int $app_id, string $email)
{
	unset($app_id, $email);
	return $GLOBALS['vms_resolved_user'] ?? new WP_User();
}

function vms_vendor_app_mark_review_ready(int $app_id, string $source, int $user_id): void
{
	vms_test_event('review_ready', compact('app_id', 'source', 'user_id'));
}

function vms_vendor_app_maybe_notify_review_ready(int $app_id): bool
{
	vms_test_event('notify_ready', compact('app_id'));
	return true;
}

function vms_vendor_app_clear_confirmation_attempt_failures(string $raw_token): void
{
	vms_test_event('clear_attempts', compact('raw_token'));
}

function vms_vendor_app_confirmation_reset_url(): string
{
	return 'https://example.test/reset-password/';
}

function home_url(string $path = ''): string
{
	return 'https://example.test' . $path;
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

function vms_no_placeholders(string $sql, string $message): void
{
	vms_assert(preg_match('/(?<!%)%(?:\d+\$)?[sdi]/', $sql) !== 1, $message . "\nSQL: " . $sql);
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

function vms_reset(VMS_Confirmation_WPDB_Spy $wpdb): void
{
	$wpdb->insert_id = 901;
	$wpdb->prepares = array();
	$wpdb->calls = array();
	$wpdb->get_row_queue = array();
	$wpdb->query_queue = array();
	$wpdb->update_queue = array();
	$wpdb->insert_queue = array();
	$GLOBALS['vms_events'] = array();
	$GLOBALS['vms_app_emails'] = array();
	$GLOBALS['vms_get_posts_calls'] = array();
	$GLOBALS['vms_get_posts_queue'] = array();
	$GLOBALS['vms_post_meta'] = array();
	$GLOBALS['vms_transients'] = array();
	$GLOBALS['vms_users'] = array();
	$GLOBALS['vms_titles'] = array();
	$GLOBALS['vms_statuses'] = array();
	$GLOBALS['vms_states'] = array();
	$GLOBALS['vms_rate_limited'] = false;
	$GLOBALS['vms_existing_user'] = false;
	$GLOBALS['vms_resolved_user'] = new WP_User();
}

function vms_last_prepare(VMS_Confirmation_WPDB_Spy $wpdb): array
{
	$prepare = end($wpdb->prepares);
	if (!is_array($prepare)) {
		throw new RuntimeException('Expected prepare().');
	}
	return $prepare;
}

function vms_last_call(VMS_Confirmation_WPDB_Spy $wpdb, string $kind): array
{
	for ($index = count($wpdb->calls) - 1; $index >= 0; $index--) {
		if (($wpdb->calls[$index]['kind'] ?? '') === $kind) {
			return $wpdb->calls[$index];
		}
	}
	throw new RuntimeException('Expected database call: ' . $kind);
}

function vms_error_code($value): string
{
	return $value instanceof WP_Error ? $value->get_error_code() : '';
}

$root = dirname(__DIR__);
$source = (string) file_get_contents($root . '/includes/core/vendor-application-confirmation.php');
$live_source = (string) file_get_contents(dirname(__DIR__, 3) . '/vms/includes/core/vendor-application-confirmation.php');
vms_assert($source !== '' && $live_source !== '', 'Mirror and shadow-live confirmation sources should be readable.');

$owned_functions = array(
	'vms_vendor_app_find_application_by_public_lookup_key',
	'vms_vendor_app_get_latest_confirmation_token_row',
	'vms_vendor_app_get_latest_open_confirmation_token_row',
	'vms_vendor_app_get_confirmation_token_row_by_hash',
	'vms_vendor_app_invalidate_confirmation_token',
	'vms_vendor_app_invalidate_open_confirmation_tokens',
	'vms_vendor_app_create_confirmation_token',
	'vms_vendor_app_mark_confirmation_token_sent',
	'vms_vendor_app_mark_confirmation_token_consumed',
	'vms_vendor_app_find_duplicate_open_application',
	'vms_vendor_app_find_recent_application_for_user',
	'vms_vendor_app_expire_stale_confirmations',
);
foreach ($owned_functions as $function) {
	vms_same(
		vms_extract_function($source, $function),
		vms_extract_function($live_source, $function),
		$function . ' should remain mirror/shadow-live identical.'
	);
}
vms_same(
	vms_extract_function($source, 'vms_vendor_app_send_review_ready_admin_notification'),
	vms_extract_function($live_source, 'vms_vendor_app_send_review_ready_admin_notification'),
	'G16 review-ready mail failure boundary should remain mirror/shadow-live identical.'
);

$scanner_inventory = array(
	'PluginCheck.Security.DirectDB.UnescapedDBParameter' => 4,
	'WordPress.DB.DirectDatabaseQuery.DirectQuery' => 8,
	'WordPress.DB.DirectDatabaseQuery.NoCaching' => 7,
	'WordPress.DB.PreparedSQL.InterpolatedNotPrepared' => 2,
	'WordPress.DB.PreparedSQL.NotPrepared' => 2,
	'WordPress.DB.SlowDBQuery.slow_db_query_meta_query' => 4,
);
vms_same(27, array_sum($scanner_inventory), 'The test should inventory exactly the 27 owned historical DB findings.');
vms_same(4, substr_count($source, 'WordPress.DB.SlowDBQuery.slow_db_query_meta_query'), 'Exactly four bounded meta queries should carry operation-specific annotations.');
vms_same(9, substr_count($source, 'WordPress.DB.DirectDatabaseQuery.DirectQuery'), 'Each direct custom-table operation branch should have one narrow annotation.');
vms_same(8, substr_count($source, 'WordPress.DB.DirectDatabaseQuery.NoCaching'), 'Each request-fresh custom-table operation branch should have one narrow no-cache annotation.');
vms_same(0, substr_count($source, 'error_log('), 'The deferred G16 logging row should be migrated without suppression.');
vms_same(1, substr_count($source, "vms_record_operational_issue('vendor_app_review_ready_mail_failed'"), 'The G16 mail-failure event should occur exactly once.');
vms_contains("'post_id'     => \$app_id", $source, 'The mail-failure event should retain only the safe application identity.');
vms_assert(strpos($source, "error_log('[VMS] vendor-apply: review-ready admin notification failed") === false, 'The historical raw server-log sink should be absent.');
vms_assert(strpos($source, 'phpcs:disable') === false && strpos($source, 'phpcs:ignoreFile') === false, 'Repository remediation should not use blanket suppression.');
foreach (array('FROM {$table}', '"UPDATE " . vms_vendor_app_confirm_tokens_table()', '$wpdb->get_row($wpdb->prepare($sql') as $unsafe) {
	vms_assert(strpos($source, $unsafe) === false, 'Legacy scanner target should be absent: ' . $unsafe);
}
vms_contains('FROM %i', $source, 'Custom-table reads should prepare table identifiers.');
vms_contains('UPDATE %i', $source, 'Open-token invalidation should prepare its table identifier.');

$runtime_functions = array_merge(
	array(
		'vms_vendor_app_confirm_tokens_table',
		'vms_vendor_app_normalize_dedupe_business_name',
		'vms_vendor_app_validate_confirmation_token',
		'vms_vendor_app_process_confirmation',
	),
	$owned_functions
);
foreach (array_values(array_unique($runtime_functions)) as $function) {
	eval(vms_extract_function($source, $function));
}

$wpdb = new VMS_Confirmation_WPDB_Spy();
$GLOBALS['wpdb'] = $wpdb;
$table = 'wp_vms_vendor_app_confirm_tokens';

vms_reset($wpdb);
$wpdb->get_row_queue[] = array('id' => '31', 'application_id' => '19');
$row = vms_vendor_app_get_latest_confirmation_token_row(19);
vms_same(array('id' => '31', 'application_id' => '19'), $row, 'Latest-token lookup should return the repository row.');
$prepare = vms_last_prepare($wpdb);
vms_contains('FROM %i', $prepare['query'], 'Latest-token lookup should prepare the table identifier.');
vms_contains('ORDER BY id DESC', $prepare['query'], 'Latest-token lookup should preserve newest-first semantics.');
vms_same(array($table, 19), $prepare['args'], 'Latest-token lookup should prepare table and application ID.');
vms_no_placeholders(vms_last_call($wpdb, 'get_row')['sql'], 'Latest-token lookup should execute prepared SQL.');

vms_reset($wpdb);
$wpdb->get_row_queue[] = false;
vms_same(null, vms_vendor_app_get_latest_confirmation_token_row(19), 'A failed latest-token read should normalize to null.');
vms_reset($wpdb);
vms_same(null, vms_vendor_app_get_latest_confirmation_token_row(0), 'Invalid application IDs should fail closed without a query.');
vms_same(array(), $wpdb->calls, 'Invalid latest-token lookup should not touch the repository.');

vms_reset($wpdb);
$wpdb->get_row_queue[] = array('id' => 32);
vms_same(array('id' => 32), vms_vendor_app_get_latest_open_confirmation_token_row(19, false), 'Open-token lookup should return its row.');
$prepare = vms_last_prepare($wpdb);
vms_same(array($table, 19), $prepare['args'], 'Open-token lookup should prepare table and application ID.');
vms_contains('consumed_at IS NULL', $prepare['query'], 'Open-token lookup should exclude consumed rows.');
vms_contains('invalidated_at IS NULL', $prepare['query'], 'Open-token lookup should exclude invalidated rows.');
vms_assert(strpos($prepare['query'], 'expires_at >=') === false, 'Non-expiry-gated open lookup should not add an expiry predicate.');

vms_reset($wpdb);
$wpdb->get_row_queue[] = array('id' => 33);
vms_same(array('id' => 33), vms_vendor_app_get_latest_open_confirmation_token_row(19, true), 'Unexpired open-token lookup should return its row.');
$prepare = vms_last_prepare($wpdb);
vms_same(array($table, 19, '2026-08-07 21:15:00'), $prepare['args'], 'Expiry-gated lookup should prepare table, application, and current UTC time.');
vms_contains('expires_at >= %s', $prepare['query'], 'Expiry-gated lookup should preserve its expiry predicate.');
vms_no_placeholders(vms_last_call($wpdb, 'get_row')['sql'], 'Expiry-gated lookup should execute prepared SQL.');

vms_reset($wpdb);
$wpdb->get_row_queue[] = array('id' => 34, 'token_hash' => 'abc');
vms_same(array('id' => 34, 'token_hash' => 'abc'), vms_vendor_app_get_confirmation_token_row_by_hash(' abc '), 'Hash lookup should trim and return its row.');
$prepare = vms_last_prepare($wpdb);
vms_same(array($table, 'abc'), $prepare['args'], 'Hash lookup should prepare table and normalized hash.');
vms_contains('WHERE token_hash = %s', $prepare['query'], 'Hash lookup should preserve exact-match semantics.');
vms_no_placeholders(vms_last_call($wpdb, 'get_row')['sql'], 'Hash lookup should execute prepared SQL.');
vms_reset($wpdb);
vms_same(null, vms_vendor_app_get_confirmation_token_row_by_hash('  '), 'Empty token hashes should fail closed without a query.');
vms_same(array(), $wpdb->calls, 'Empty hash lookup should not touch the repository.');

vms_reset($wpdb);
$wpdb->update_queue[] = false;
vms_same(null, vms_vendor_app_invalidate_confirmation_token(55, 'Admin rotation!'), 'Single-token invalidation should retain its void result even when wpdb reports failure.');
$update = vms_last_call($wpdb, 'update');
vms_same($table, $update['table'], 'Single-token invalidation should target the confirmation repository.');
vms_same(
	array('invalidated_at' => '2026-08-07 21:15:00', 'invalidated_reason' => 'adminrotation'),
	$update['data'],
	'Single-token invalidation should normalize its audit values.'
);
vms_same(array('id' => 55, 'invalidated_at' => null, 'consumed_at' => null), $update['where'], 'Single-token invalidation should only mutate an open matching row.');
vms_same(array('%d', '%s', '%s'), $update['where_format'], 'Single-token invalidation should retain its null-aware where formats.');

vms_reset($wpdb);
$wpdb->query_queue[] = false;
vms_same(null, vms_vendor_app_invalidate_open_confirmation_tokens(19, 'Confirmed!'), 'Bulk invalidation should retain its void result when wpdb reports failure.');
$prepare = vms_last_prepare($wpdb);
vms_same(array($table, '2026-08-07 21:15:00', 'confirmed', 19), $prepare['args'], 'Bulk invalidation should prepare table, timestamp, reason, and application.');
vms_contains('UPDATE %i', $prepare['query'], 'Bulk invalidation should prepare its table identifier.');
vms_contains('consumed_at IS NULL', $prepare['query'], 'Bulk invalidation should preserve open-row guards.');
vms_no_placeholders(vms_last_call($wpdb, 'query')['sql'], 'Bulk invalidation should execute prepared SQL.');

vms_reset($wpdb);
vms_same(
	'vms_vendor_app_confirm_app_missing',
	vms_error_code(vms_vendor_app_create_confirmation_token(0)),
	'Token creation should reject a missing application without mutation.'
);
vms_same(array(), $GLOBALS['vms_events'], 'Invalid token creation should not mutate repository state.');

vms_reset($wpdb);
vms_same(
	'vms_vendor_app_confirm_email_invalid',
	vms_error_code(vms_vendor_app_create_confirmation_token(19, array('email' => 'not-an-email'))),
	'Token creation should reject an invalid email without rotating tokens.'
);
vms_same(array(), $GLOBALS['vms_events'], 'Invalid-email token creation should not mutate repository state.');

vms_reset($wpdb);
$wpdb->insert_id = 902;
$wpdb->query_queue[] = 1;
$wpdb->insert_queue[] = 1;
$created = vms_vendor_app_create_confirmation_token(
	19,
	array('email' => ' Vendor@Example.test ', 'invalidate_reason' => 'resend', 'created_by_user_id' => 44)
);
vms_assert(is_array($created), 'Successful token creation should return token details.');
vms_same(902, $created['token_id'] ?? 0, 'Successful token creation should return wpdb insert ID.');
vms_same('raw-confirmation-token', $created['token'] ?? '', 'Successful token creation should return the generated raw token.');
vms_contains('token=raw-confirmation-token', (string) ($created['confirm_url'] ?? ''), 'Successful token creation should return its confirmation URL.');
vms_same(array('query', 'insert'), array_column($GLOBALS['vms_events'], 'kind'), 'Token creation should invalidate prior open rows before inserting the replacement.');
$insert = vms_last_call($wpdb, 'insert');
vms_same($table, $insert['table'], 'Token creation should insert into the confirmation repository.');
vms_same(19, $insert['data']['application_id'], 'Token creation should persist the application ID.');
vms_same('vendor@example.test', $insert['data']['email'], 'Token creation should normalize the stored email.');
vms_same(vms_vendor_app_hash_confirmation_token('raw-confirmation-token'), $insert['data']['token_hash'], 'Token creation should store only the token hash.');
vms_same(44, $insert['data']['created_by_user_id'], 'Token creation should persist the normalized actor.');
vms_same(null, $insert['data']['consumed_at'], 'New token rows should remain unconsumed.');
vms_same(13, count($insert['format']), 'Token creation should retain format coverage for every inserted column.');

vms_reset($wpdb);
$wpdb->query_queue[] = 1;
$wpdb->insert_queue[] = false;
$failed = vms_vendor_app_create_confirmation_token(19, array('email' => 'vendor@example.test'));
vms_same('vms_vendor_app_confirm_token_create_failed', vms_error_code($failed), 'Failed repository insertion should return the existing creation error.');
vms_same(array('query', 'insert'), array_column($GLOBALS['vms_events'], 'kind'), 'Failed insertion should still occur only after prior-token invalidation.');

vms_reset($wpdb);
vms_same(null, vms_vendor_app_mark_confirmation_token_sent(61), 'Sent-state mutation should retain its void result.');
$update = vms_last_call($wpdb, 'update');
vms_same(array('sent_at' => '2026-08-07 21:15:00'), $update['data'], 'Sent-state mutation should persist the current UTC time.');
vms_same(array('id' => 61), $update['where'], 'Sent-state mutation should scope the token ID.');
vms_same(array('%s'), $update['format'], 'Sent-state mutation should retain its data format.');
vms_same(array('%d'), $update['where_format'], 'Sent-state mutation should retain its ID format.');

vms_reset($wpdb);
$wpdb->update_queue[] = false;
vms_same(null, vms_vendor_app_mark_confirmation_token_consumed(62, -9), 'Consumption should retain its void result when wpdb reports failure.');
$update = vms_last_call($wpdb, 'update');
vms_same($table, $update['table'], 'Consumption should update the confirmation repository.');
vms_same(
	array(
		'consumed_at' => '2026-08-07 21:15:00',
		'resolved_user_id' => 0,
		'consumed_ip' => '203.0.113.9',
		'consumed_user_agent' => 'Repository Test Agent',
	),
	$update['data'],
	'Consumption should retain normalized user and request context.'
);
vms_same(array('id' => 62), $update['where'], 'Consumption should scope the token ID.');
vms_same(array('%s', '%d', '%s', '%s'), $update['format'], 'Consumption should retain all data formats.');

vms_reset($wpdb);
$wpdb->get_row_queue[] = array(
	'id' => 70,
	'application_id' => 19,
	'email' => 'vendor@example.test',
	'consumed_at' => '2026-08-07 20:00:00',
	'invalidated_at' => null,
	'expires_at' => '2999-01-01 00:00:00',
);
$used = vms_vendor_app_validate_confirmation_token('used-token');
vms_same('vms_vendor_app_confirm_token_used', vms_error_code($used), 'Validation should reject an already consumed token.');
vms_same(array(), $GLOBALS['vms_events'], 'Consumed-token validation should not mutate application or token state.');

vms_reset($wpdb);
$wpdb->get_row_queue[] = array(
	'id' => 71,
	'application_id' => 19,
	'email' => 'vendor@example.test',
	'consumed_at' => null,
	'invalidated_at' => null,
	'expires_at' => '2000-01-01 00:00:00',
);
$expired = vms_vendor_app_validate_confirmation_token('expired-token');
vms_same('vms_vendor_app_confirm_token_expired', vms_error_code($expired), 'Validation should reject an expired token.');
vms_same('expired', $GLOBALS['vms_post_meta'][19]['_vms_app_confirmation_state'] ?? '', 'Expired validation should preserve the application-state transition.');
vms_same(array('update_post_meta'), array_column($GLOBALS['vms_events'], 'kind'), 'Expired validation should only mutate application confirmation state.');

vms_reset($wpdb);
$wpdb->get_row_queue[] = array(
	'id' => 72,
	'application_id' => 19,
	'email' => 'vendor@example.test',
	'consumed_at' => null,
	'invalidated_at' => null,
	'expires_at' => '2999-01-01 00:00:00',
);
$GLOBALS['vms_resolved_user'] = new WP_User(88, 'vendor@example.test');
$processed = vms_vendor_app_process_confirmation('valid-token');
vms_assert(is_array($processed), 'Valid confirmation processing should return the existing success payload.');
vms_same(19, $processed['app_id'] ?? 0, 'Confirmation processing should preserve application ID.');
vms_same(88, $processed['user_id'] ?? 0, 'Confirmation processing should preserve resolved user ID.');
vms_same(
	array('update', 'query', 'review_ready', 'notify_ready', 'clear_attempts'),
	array_column($GLOBALS['vms_events'], 'kind'),
	'Confirmation processing should consume the matched token before invalidating remaining open rows and advancing review state.'
);
$update = vms_last_call($wpdb, 'update');
vms_same(72, $update['where']['id'] ?? 0, 'Confirmation processing should consume the validated token row.');
$prepare = vms_last_prepare($wpdb);
vms_same(array($table, '2026-08-07 21:15:00', 'confirmed', 19), $prepare['args'], 'Confirmation processing should invalidate remaining rows with prepared lifecycle values.');

vms_reset($wpdb);
$wpdb->get_row_queue[] = null;
$invalid = vms_vendor_app_process_confirmation('unknown-token');
vms_same('vms_vendor_app_confirm_token_invalid', vms_error_code($invalid), 'Unknown tokens should retain the invalid-token result.');
vms_same(array('attempt_failure'), array_column($GLOBALS['vms_events'], 'kind'), 'Unknown-token processing should record failure without consuming or invalidating rows.');

vms_reset($wpdb);
$GLOBALS['vms_get_posts_queue'][] = array(81);
vms_same(81, vms_vendor_app_find_application_by_public_lookup_key(' lookup-key '), 'Public lookup should return the first matching application ID.');
$args = $GLOBALS['vms_get_posts_calls'][0] ?? array();
vms_same(1, $args['posts_per_page'] ?? 0, 'Public lookup should remain bounded to one application.');
vms_same('_vms_app_public_lookup_key', $args['meta_query'][0]['key'] ?? '', 'Public lookup should retain its metadata key.');
vms_same('lookup-key', $args['meta_query'][0]['value'] ?? '', 'Public lookup should sanitize its exact metadata value.');
vms_same(false, $args['update_post_meta_cache'] ?? true, 'Public lookup should retain disabled metadata cache priming.');

vms_reset($wpdb);
$GLOBALS['vms_get_posts_queue'][] = array(82);
$GLOBALS['vms_titles'][82] = 'The Vendor Co';
$GLOBALS['vms_statuses'][82] = 'pending';
$GLOBALS['vms_states'][82] = 'unconfirmed';
$duplicate = vms_vendor_app_find_duplicate_open_application('vendor@example.test', ' The   Vendor Co ');
vms_same('unconfirmed', $duplicate['duplicate_kind'] ?? '', 'Duplicate lookup should preserve unconfirmed classification.');
vms_same(82, $duplicate['app_id'] ?? 0, 'Duplicate lookup should return the matched application.');
$args = $GLOBALS['vms_get_posts_calls'][0] ?? array();
vms_same(-1, $args['posts_per_page'] ?? 0, 'Duplicate lookup should retain its complete candidate scan.');
vms_same('vendor@example.test', $args['meta_query'][0]['value'] ?? '', 'Duplicate lookup should retain exact normalized-email filtering.');

vms_reset($wpdb);
$GLOBALS['vms_users'][22] = new WP_User(22, 'vendor@example.test');
$GLOBALS['vms_get_posts_queue'][] = array(83);
$GLOBALS['vms_statuses'][83] = 'pending';
$GLOBALS['vms_states'][83] = 'confirmed';
$recent = vms_vendor_app_find_recent_application_for_user(22);
vms_same('pending_review', $recent['kind'] ?? '', 'Recent-application lookup should preserve pending-review classification.');
vms_same(83, $recent['app_id'] ?? 0, 'Recent-application lookup should return the matched application.');
$args = $GLOBALS['vms_get_posts_calls'][0] ?? array();
vms_same('vendor@example.test', $args['meta_query'][0]['value'] ?? '', 'Recent-application lookup should filter by the authenticated user email.');

vms_reset($wpdb);
$GLOBALS['vms_get_posts_queue'][] = array(84, 85);
vms_vendor_app_expire_stale_confirmations();
vms_same(
	array('set_transient', 'update_post_meta', 'update_post_meta'),
	array_column($GLOBALS['vms_events'], 'kind'),
	'Expiry sweep should acquire its lock before marking each returned application expired.'
);
vms_same('expired', $GLOBALS['vms_post_meta'][84]['_vms_app_confirmation_state'] ?? '', 'Expiry sweep should update the first stale application.');
vms_same('expired', $GLOBALS['vms_post_meta'][85]['_vms_app_confirmation_state'] ?? '', 'Expiry sweep should update the second stale application.');
$args = $GLOBALS['vms_get_posts_calls'][0] ?? array();
vms_same('AND', $args['meta_query']['relation'] ?? '', 'Expiry sweep should retain the state-and-age intersection.');
vms_same('unconfirmed', $args['meta_query'][0]['value'] ?? '', 'Expiry sweep should retain the unconfirmed-state predicate.');
vms_same('<=', $args['meta_query'][1]['compare'] ?? '', 'Expiry sweep should retain its threshold comparison.');
vms_same('DATETIME', $args['meta_query'][1]['type'] ?? '', 'Expiry sweep should retain datetime comparison semantics.');

vms_reset($wpdb);
$GLOBALS['vms_transients']['vms_vendor_app_expire_stale_confirmations_lock'] = '1';
vms_vendor_app_expire_stale_confirmations();
vms_same(array(), $GLOBALS['vms_get_posts_calls'], 'An active expiry lock should prevent another candidate query.');
vms_same(array(), $GLOBALS['vms_events'], 'An active expiry lock should prevent mutations.');

echo "vendor application confirmation repository SQL remediation: PASS\n";
