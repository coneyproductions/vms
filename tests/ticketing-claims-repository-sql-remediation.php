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
	public array $get_row_queue = array();
	/** @var array<int,mixed> */
	public array $get_results_queue = array();
	/** @var mixed */
	public $insert_return = 1;
	/** @var mixed */
	public $query_return = 1;
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

	/**
	 * @return array<int,mixed>|mixed
	 */
	public function get_col(string $query)
	{
		$result = $this->shift_queue($this->get_col_queue, array());
		$this->record_execution('get_col', $query, $result);
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

	/** @return mixed */
	public function query(string $query)
	{
		$this->record_execution('query', $query, $this->query_return);
		return $this->query_return;
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

function current_time(string $type, bool $gmt = false): string
{
	unset($gmt);
	if ($type === 'mysql') {
		return '2026-08-07 09:45:00';
	}

	return '2026-08-07 09:45:00';
}

function sanitize_email($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$sanitized = filter_var((string) $value, FILTER_SANITIZE_EMAIL);
	return is_string($sanitized) ? strtolower(trim($sanitized)) : '';
}

function sanitize_key($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$sanitized = preg_replace('/[^a-z0-9_\-]+/i', '', strtolower((string) $value));
	return is_string($sanitized) ? $sanitized : '';
}

function sanitize_text_field($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	return trim(strip_tags((string) $value));
}

function wp_json_encode($value)
{
	$json = json_encode($value);
	return is_string($json) ? $json : false;
}

function vms_test_assert(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
}

function vms_test_assert_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function vms_test_assert_no_placeholders(string $sql, string $message): void
{
	vms_test_assert((bool) preg_match('/(?<!%)%(?:\d+\$)?[sdi]/', $sql) === false, $message . "\nSQL: " . $sql);
}

function vms_test_assert_same($expected, $actual, string $message): void
{
	vms_test_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
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

	$depth = 1;
	$length = strlen($source);
	for ($i = $brace + 1; $i < $length; $i++) {
		if ($source[$i] === '{') {
			$depth++;
			continue;
		}
		if ($source[$i] === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $start, ($i - $start) + 1);
			}
		}
	}

	throw new RuntimeException('Unable to locate closing brace for ' . $name . '.');
}

/**
 * @return array{query:string,args:array<int,mixed>,final_sql:string}
 */
function vms_test_last_prepare(VMS_Test_WPDB $wpdb): array
{
	$prepare = end($wpdb->prepare_calls);
	if (!is_array($prepare)) {
		throw new RuntimeException('Expected a prepare() call.');
	}

	return $prepare;
}

/**
 * @return array<string,mixed>
 */
function vms_test_last_call(VMS_Test_WPDB $wpdb, string $kind): array
{
	for ($index = count($wpdb->call_log) - 1; $index >= 0; $index--) {
		$call = $wpdb->call_log[$index];
		if (($call['kind'] ?? '') === $kind) {
			return $call;
		}
	}

	throw new RuntimeException('Expected a ' . $kind . ' call.');
}

function vms_test_reset_db(VMS_Test_WPDB $wpdb): void
{
	$wpdb->call_log = array();
	$wpdb->prepare_calls = array();
	$wpdb->get_col_queue = array();
	$wpdb->get_row_queue = array();
	$wpdb->get_results_queue = array();
	$wpdb->insert_return = 1;
	$wpdb->query_return = 1;
	$wpdb->update_return = 1;
}

$pluginRoot = dirname(__DIR__);
$frameworkPath = $pluginRoot . '/includes/integrations/ticketing-claims-framework.php';
$liveFrameworkPath = dirname(__DIR__, 3) . '/vms/includes/integrations/ticketing-claims-framework.php';

$frameworkSource = (string) file_get_contents($frameworkPath);
$liveFrameworkSource = (string) file_get_contents($liveFrameworkPath);

vms_test_assert($frameworkSource !== '', 'Mirror ticketing claims framework source should be readable.');
vms_test_assert($liveFrameworkSource !== '', 'Live ticketing claims framework source should be readable.');

$targetFunctions = array(
	'vms_ticketing_claims_table_direct_grants',
	'vms_ticketing_claims_table_reservations',
	'vms_ticketing_claims_table_log',
	'vms_ticketing_claims_sanitize_program_list',
	'vms_ticketing_claims_grant_reservation_counts',
	'vms_ticketing_claims_find_active_direct_grant',
	'vms_ticketing_claims_log_result',
	'vms_ticketing_claims_create_direct_grant',
	'vms_ticketing_claims_allowed_grant_statuses',
	'vms_ticketing_claims_get_direct_grant',
	'vms_ticketing_claims_get_direct_grants',
	'vms_ticketing_claims_update_direct_grant_note',
	'vms_ticketing_claims_set_direct_grant_status',
	'vms_ticketing_claims_get_reservation',
	'vms_ticketing_claims_get_reservations',
	'vms_ticketing_claims_release_reservation',
	'vms_ticketing_claims_get_logs',
	'vms_ticketing_claims_recent_assignee_emails_for_buyer',
);

foreach ($targetFunctions as $functionName) {
	vms_test_assert_same(
		vms_test_extract_function($frameworkSource, $functionName),
		vms_test_extract_function($liveFrameworkSource, $functionName),
		$functionName . ' should remain mirror/live identical.'
	);
}

$logSource = vms_test_extract_function($frameworkSource, 'vms_ticketing_claims_log_result');
$createGrantSource = vms_test_extract_function($frameworkSource, 'vms_ticketing_claims_create_direct_grant');
$updateNoteSource = vms_test_extract_function($frameworkSource, 'vms_ticketing_claims_update_direct_grant_note');
$setStatusSource = vms_test_extract_function($frameworkSource, 'vms_ticketing_claims_set_direct_grant_status');
$releaseReservationSource = vms_test_extract_function($frameworkSource, 'vms_ticketing_claims_release_reservation');

vms_test_assert_contains('audit logging persists normalized custom-table rows', $logSource, 'Claims audit logging should document its direct custom-table insert boundary.');
vms_test_assert_contains('direct grant creation persists normalized custom-table rows', $createGrantSource, 'Claims direct grant creation should document its direct custom-table insert boundary.');
vms_test_assert_contains('grant-note updates write the plugin-owned grants table directly', $updateNoteSource, 'Claims grant-note updates should document their direct custom-table update boundary.');
vms_test_assert_contains('grant-status transitions write the plugin-owned grants table directly', $setStatusSource, 'Claims grant-status transitions should document their direct custom-table update boundary.');
vms_test_assert_contains('reservation releases write the plugin-owned reservations table directly', $releaseReservationSource, 'Claims reservation release should document its direct reservation update boundary.');
vms_test_assert_contains('reservation releases write the plugin-owned grants table directly', $releaseReservationSource, 'Claims reservation release should document its direct grant usage repair boundary.');

foreach ($targetFunctions as $functionName) {
	eval(vms_test_extract_function($frameworkSource, $functionName));
}

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;

vms_test_reset_db($wpdb);
$wpdb->get_results_queue[] = array(
	array('status' => 'reserved', 'cnt' => '2'),
	array('status' => 'used', 'cnt' => '1'),
);
vms_test_assert_same(
	array('reserved' => 2, 'used' => 1),
	vms_ticketing_claims_grant_reservation_counts(44),
	'Grant reservation counts should normalize keyed count rows.'
);
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same(
	'SELECT status, COUNT(1) AS cnt FROM %i WHERE direct_grant_id = %d GROUP BY status',
	$prepare['query'],
	'Grant reservation counts should prepare the reservations table identifier and grant ID.'
);
vms_test_assert_same(
	array('wp_vms_ticketing_claim_reservations', 44),
	$prepare['args'],
	'Grant reservation counts should pass the reservations table identifier and grant ID to prepare().'
);
vms_test_assert_no_placeholders(vms_test_last_call($wpdb, 'get_results')['query'], 'Grant reservation counts should execute fully prepared SQL.');

vms_test_reset_db($wpdb);
$wpdb->get_row_queue[] = array('id' => 71, 'status' => 'active');
$grant = vms_ticketing_claims_find_active_direct_grant(array(
	'user_id' => 19,
	'event_id' => 77,
	'ticket_product_id' => 88,
	'ticket_key' => 'VIP',
	'grant_type' => 'event_ticket_eligibility',
	'allowed_programs' => array('Green Room', 'Backstage'),
));
vms_test_assert_same(array('id' => 71, 'status' => 'active'), $grant, 'Active direct grant lookups should return the queued matching row.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_contains('SELECT * FROM %i WHERE user_id = %d AND event_id = %d', $prepare['query'], 'Active direct grant lookups should prepare the grants table identifier and required user/event filters.');
vms_test_assert_contains('(ticket_product_id = 0 OR ticket_product_id = %d)', $prepare['query'], 'Active direct grant lookups should preserve the optional product guard.');
vms_test_assert_contains("(ticket_key = '' OR ticket_key = %s)", $prepare['query'], 'Active direct grant lookups should preserve the optional ticket key guard.');
vms_test_assert_contains('credential_program IN (%s, %s)', $prepare['query'], 'Active direct grant lookups should preserve the prepared credential-program list.');
vms_test_assert_same(
	array('wp_vms_ticketing_direct_grants', 19, 77, 88, 'vip', 'event_ticket_eligibility', 'greenroom', 'backstage'),
	$prepare['args'],
	'Active direct grant lookups should pass the grants table identifier and every accepted filter to prepare().'
);
vms_test_assert_no_placeholders(vms_test_last_call($wpdb, 'get_row')['query'], 'Active direct grant lookups should execute fully prepared SQL.');

vms_test_reset_db($wpdb);
$wpdb->insert_id = 321;
vms_test_assert_same(
	321,
	vms_ticketing_claims_log_result(array(
		'event_id' => 12,
		'ticket_product_id' => 45,
		'ticket_key' => 'vip access',
		'buyer_user_id' => 78,
		'assignee_user_id' => 90,
		'assignee_email' => 'Guest@Example.com',
		'rule_path' => 'event_direct_grant',
		'direct_grant_id' => 17,
		'result' => 'success',
		'reason_code' => 'grant_created',
		'message' => 'Created',
		'context' => array('matched_program' => 'backstage'),
	)),
	'Claims audit logging should return the queued insert ID on success.'
);
$insert = vms_test_last_call($wpdb, 'insert');
vms_test_assert_same('wp_vms_ticketing_claim_log', $insert['table'], 'Claims audit logging should insert into the custom log table.');
vms_test_assert_same('vipaccess', $insert['data']['ticket_key'], 'Claims audit logging should sanitize the stored ticket key.');
vms_test_assert_same('guest@example.com', $insert['data']['assignee_email'], 'Claims audit logging should sanitize the stored assignee email.');
vms_test_assert_same('{"matched_program":"backstage"}', $insert['data']['context_json'], 'Claims audit logging should persist encoded context JSON.');
vms_test_assert_same('2026-08-07 09:45:00', $insert['data']['created_at'], 'Claims audit logging should stamp the queued test time.');

vms_test_reset_db($wpdb);
$wpdb->insert_id = 654;
vms_test_assert_same(
	654,
	vms_ticketing_claims_create_direct_grant(array(
		'event_id' => 12,
		'user_id' => 19,
		'grant_type' => 'event_grant',
		'ticket_product_id' => 45,
		'ticket_key' => 'Vip Access',
		'credential_program' => 'Backstage',
		'qty_limit' => 3,
		'status' => 'reserved',
		'note' => '<strong>Ready</strong>',
		'actor_user_id' => 88,
	)),
	'Direct grant creation should return the queued insert ID on success.'
);
$insert = vms_test_last_call($wpdb, 'insert');
vms_test_assert_same('wp_vms_ticketing_direct_grants', $insert['table'], 'Direct grant creation should insert into the custom grants table.');
vms_test_assert_same('vipaccess', $insert['data']['ticket_key'], 'Direct grant creation should sanitize the stored ticket key.');
vms_test_assert_same('backstage', $insert['data']['credential_program'], 'Direct grant creation should sanitize the stored credential program.');
vms_test_assert_same('Ready', $insert['data']['note'], 'Direct grant creation should sanitize the stored note.');
vms_test_assert_same('2026-08-07 09:45:00', $insert['data']['created_at'], 'Direct grant creation should stamp the queued test time.');

vms_test_reset_db($wpdb);
$wpdb->get_row_queue[] = array('id' => 55, 'status' => 'active');
vms_test_assert_same(array('id' => 55, 'status' => 'active'), vms_ticketing_claims_get_direct_grant(55), 'Single direct grant reads should return the queued row.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same('SELECT * FROM %i WHERE id = %d', $prepare['query'], 'Single direct grant reads should prepare the grants table identifier and grant ID.');
vms_test_assert_same(array('wp_vms_ticketing_direct_grants', 55), $prepare['args'], 'Single direct grant reads should pass the grants table identifier and grant ID to prepare().');
vms_test_assert_no_placeholders(vms_test_last_call($wpdb, 'get_row')['query'], 'Single direct grant reads should execute fully prepared SQL.');

vms_test_reset_db($wpdb);
$wpdb->get_results_queue[] = array(array('id' => 55, 'status' => 'active'));
$rows = vms_ticketing_claims_get_direct_grants(array(
	'event_id' => 12,
	'user_id' => 19,
	'status' => 'active',
	'grant_type' => 'event_grant',
	'ticket_product_id' => 45,
	'ticket_key' => 'VIP',
	'credential_program' => 'Backstage',
	'limit' => 25,
	'offset' => 5,
));
vms_test_assert_same(array(array('id' => 55, 'status' => 'active')), $rows, 'Direct grant lists should return the queued rows.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same(
	'SELECT * FROM %i WHERE 1=1 AND event_id = %d AND user_id = %d AND status = %s AND grant_type = %s AND ticket_product_id = %d AND ticket_key = %s AND credential_program = %s ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d',
	$prepare['query'],
	'Direct grant lists should prepare the grants table identifier, every accepted filter, and pagination.'
);
vms_test_assert_same(
	array('wp_vms_ticketing_direct_grants', 12, 19, 'active', 'event_grant', 45, 'vip', 'backstage', 25, 5),
	$prepare['args'],
	'Direct grant lists should pass the grants table identifier, accepted filters, and pagination values to prepare().'
);
vms_test_assert_no_placeholders(vms_test_last_call($wpdb, 'get_results')['query'], 'Direct grant lists should execute fully prepared SQL.');

vms_test_reset_db($wpdb);
vms_test_assert_same(true, vms_ticketing_claims_update_direct_grant_note(17, '<strong>Operator note</strong>', 77), 'Direct grant note updates should report success when wpdb::update() succeeds.');
$update = vms_test_last_call($wpdb, 'update');
vms_test_assert_same('wp_vms_ticketing_direct_grants', $update['table'], 'Direct grant note updates should target the custom grants table.');
vms_test_assert_same('Operator note', $update['data']['note'], 'Direct grant note updates should sanitize the stored note.');
vms_test_assert_same(array('id' => 17), $update['where'], 'Direct grant note updates should scope the update by grant ID.');

vms_test_reset_db($wpdb);
$wpdb->get_row_queue[] = array('id' => 91, 'status' => 'reserved', 'qty_limit' => 2, 'qty_used' => 1);
vms_test_assert_same(true, vms_ticketing_claims_set_direct_grant_status(91, 'used', 77), 'Grant status transitions should report success for allowed state changes.');
$update = vms_test_last_call($wpdb, 'update');
vms_test_assert_same('wp_vms_ticketing_direct_grants', $update['table'], 'Grant status transitions should target the custom grants table.');
vms_test_assert_same('used', $update['data']['status'], 'Grant status transitions should persist the requested normalized status.');
vms_test_assert_same(2, $update['data']['qty_used'], 'Grant status transitions should mark used grants at the quantity limit.');
vms_test_assert_same(array('id' => 91), $update['where'], 'Grant status transitions should scope the update by grant ID.');

vms_test_reset_db($wpdb);
$wpdb->get_row_queue[] = array('id' => 23, 'status' => 'reserved');
vms_test_assert_same(array('id' => 23, 'status' => 'reserved'), vms_ticketing_claims_get_reservation(23), 'Single reservation reads should return the queued row.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same('SELECT * FROM %i WHERE id = %d', $prepare['query'], 'Single reservation reads should prepare the reservations table identifier and reservation ID.');
vms_test_assert_same(array('wp_vms_ticketing_claim_reservations', 23), $prepare['args'], 'Single reservation reads should pass the reservations table identifier and reservation ID to prepare().');
vms_test_assert_no_placeholders(vms_test_last_call($wpdb, 'get_row')['query'], 'Single reservation reads should execute fully prepared SQL.');

vms_test_reset_db($wpdb);
$wpdb->get_results_queue[] = array(array('id' => 44, 'status' => 'reserved'));
$rows = vms_ticketing_claims_get_reservations(array(
	'event_id' => 12,
	'buyer_user_id' => 19,
	'assignee_user_id' => 21,
	'direct_grant_id' => 91,
	'status' => 'reserved',
	'assignee_email' => 'Guest@Example.com',
	'ticket_product_id' => 45,
	'ticket_key' => 'VIP',
	'limit' => 30,
	'offset' => 2,
));
vms_test_assert_same(array(array('id' => 44, 'status' => 'reserved')), $rows, 'Reservation lists should return the queued rows.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same(
	'SELECT * FROM %i WHERE 1=1 AND event_id = %d AND buyer_user_id = %d AND assignee_user_id = %d AND direct_grant_id = %d AND status = %s AND assignee_email = %s AND ticket_product_id = %d AND ticket_key = %s ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d',
	$prepare['query'],
	'Reservation lists should prepare the reservations table identifier, every accepted filter, and pagination.'
);
vms_test_assert_same(
	array('wp_vms_ticketing_claim_reservations', 12, 19, 21, 91, 'reserved', 'guest@example.com', 45, 'vip', 30, 2),
	$prepare['args'],
	'Reservation lists should pass the reservations table identifier, accepted filters, and pagination values to prepare().'
);
vms_test_assert_no_placeholders(vms_test_last_call($wpdb, 'get_results')['query'], 'Reservation lists should execute fully prepared SQL.');

vms_test_reset_db($wpdb);
$wpdb->get_row_queue[] = array('id' => 66, 'status' => 'reserved', 'direct_grant_id' => 91);
vms_test_assert_same(true, vms_ticketing_claims_release_reservation(66, 77), 'Reservation releases should report success when both repository writes succeed.');
$update = vms_test_last_call($wpdb, 'update');
vms_test_assert_same('wp_vms_ticketing_claim_reservations', $update['table'], 'Reservation releases should update the custom reservations table.');
vms_test_assert_same('released', $update['data']['status'], 'Reservation releases should persist the released status.');
$query = vms_test_last_call($wpdb, 'query');
vms_test_assert_contains('UPDATE `wp_vms_ticketing_direct_grants`', $query['query'], 'Reservation releases should restore grant usage through the custom grants table.');
vms_test_assert_contains('updated_by = 77', $query['query'], 'Reservation releases should pass the actor ID through the grant usage repair query.');
vms_test_assert_no_placeholders($query['query'], 'Reservation releases should execute a fully prepared grant usage repair query.');

vms_test_reset_db($wpdb);
$wpdb->get_results_queue[] = array(array('id' => 87, 'result' => 'success'));
$rows = vms_ticketing_claims_get_logs(array(
	'event_id' => 12,
	'ticket_product_id' => 45,
	'ticket_key' => 'VIP',
	'buyer_user_id' => 19,
	'assignee_user_id' => 21,
	'assignee_email' => 'Guest@Example.com',
	'result' => 'success',
	'reason_code' => 'grant_created',
	'rule_path' => 'event_direct_grant',
	'direct_grant_only' => true,
	'credential_program' => 'Backstage',
	'reservation_status' => 'reserved',
	'limit' => 40,
	'offset' => 3,
));
vms_test_assert_same(array(array('id' => 87, 'result' => 'success')), $rows, 'Claims log lists should return the queued rows.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_contains('SELECT * FROM %i WHERE 1=1 AND event_id = %d', $prepare['query'], 'Claims log lists should prepare the log table identifier and event filter.');
vms_test_assert_contains('(context_json LIKE %s OR context_json LIKE %s)', $prepare['query'], 'Claims log lists should preserve the prepared credential-program search.');
vms_test_assert_contains('(direct_grant_id > 0 OR rule_path = %s)', $prepare['query'], 'Claims log lists should preserve the direct-grant-only filter.');
vms_test_assert_contains('context_json LIKE %s ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d', $prepare['query'], 'Claims log lists should preserve the prepared reservation-status filter and pagination.');
vms_test_assert_same(
	array(
		'wp_vms_ticketing_claim_log',
		12,
		45,
		'vip',
		19,
		21,
		'guest@example.com',
		'success',
		'grant_created',
		'event_direct_grant',
		'%"matched_program":"backstage"%',
		'%"allowed_programs":["backstage"%',
		'event_direct_grant',
		'%"reservation_status":"reserved"%',
		40,
		3,
	),
	$prepare['args'],
	'Claims log lists should pass the log table identifier, accepted filters, and pagination values to prepare().'
);
vms_test_assert_no_placeholders(vms_test_last_call($wpdb, 'get_results')['query'], 'Claims log lists should execute fully prepared SQL.');

vms_test_reset_db($wpdb);
$wpdb->get_col_queue[] = array('Guest@Example.com', 'guest@example.com', '', 'Second@Example.com', 'Third@Example.com');
$emails = vms_ticketing_claims_recent_assignee_emails_for_buyer(19, 2, 12);
vms_test_assert_same(array('guest@example.com', 'second@example.com'), $emails, 'Recent helper-email lookups should sanitize, de-duplicate, and cap the returned assignee emails.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same(
	"SELECT assignee_email FROM %i WHERE buyer_user_id = %d AND assignee_email <> '' AND result = 'success' AND event_id = %d ORDER BY created_at DESC, id DESC LIMIT %d",
	$prepare['query'],
	'Recent helper-email lookups should prepare the log table identifier, buyer/event filters, and scan limit.'
);
vms_test_assert_same(
	array('wp_vms_ticketing_claim_log', 19, 12, 250),
	$prepare['args'],
	'Recent helper-email lookups should pass the log table identifier, buyer/event filters, and scan limit to prepare().'
);
vms_test_assert_no_placeholders(vms_test_last_call($wpdb, 'get_col')['query'], 'Recent helper-email lookups should execute fully prepared SQL.');

fwrite(STDOUT, "Ticketing Claims repository SQL remediation: PASS\n");
