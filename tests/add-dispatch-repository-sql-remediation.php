<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

if (!defined('ARRAY_A')) {
	define('ARRAY_A', 'ARRAY_A');
}

final class VMS_Test_WPDB
{
	/** @var array<int,array{query:string,args:array<int,mixed>,final_sql:string}> */
	public array $prepare_calls = array();

	/** @var array<int,array<string,mixed>> */
	public array $call_log = array();

	/** @var array<int,mixed> */
	public array $get_var_queue = array();

	/** @var array<int,mixed> */
	public array $get_row_queue = array();

	/** @var array<int,mixed> */
	public array $get_results_queue = array();

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

	return trim((string) $value);
}

function get_the_title(int $post_id): string
{
	return 'Title #' . $post_id;
}

function vms_add_dispatch_table_name(string $table): string
{
	return 'wp_vms_add_dispatch_' . sanitize_key($table);
}

function vms_add_dispatch_token_signature(string $public_key, int $request_id, int $vendor_id, string $created_at): string
{
	return hash('sha256', strtolower($public_key) . '|' . $request_id . '|' . $vendor_id . '|' . $created_at);
}

function vms_test_assert(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
}

function vms_test_assert_same($expected, $actual, string $message): void
{
	vms_test_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function vms_test_assert_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function vms_test_assert_no_placeholders(string $sql, string $message): void
{
	vms_test_assert((bool) preg_match('/(?<!%)%(?:\d+\$)?[sdi]/', $sql) === false, $message . "\nSQL: " . $sql);
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

function vms_test_reset_db(VMS_Test_WPDB $wpdb): void
{
	$wpdb->prepare_calls = array();
	$wpdb->call_log = array();
	$wpdb->get_var_queue = array();
	$wpdb->get_row_queue = array();
	$wpdb->get_results_queue = array();
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
function vms_test_last_execution(VMS_Test_WPDB $wpdb, string $kind): array
{
	for ($index = count($wpdb->call_log) - 1; $index >= 0; $index--) {
		$call = $wpdb->call_log[$index];
		if (($call['kind'] ?? '') === $kind) {
			return $call;
		}
	}

	throw new RuntimeException('Expected a ' . $kind . ' call.');
}

$pluginRoot = dirname(__DIR__);
$helpersPath = $pluginRoot . '/includes/modules/availability-date-dispatch/helpers.php';
$liveHelpersPath = dirname(__DIR__, 3) . '/vms/includes/modules/availability-date-dispatch/helpers.php';

$helpersSource = (string) file_get_contents($helpersPath);
$liveHelpersSource = (string) file_get_contents($liveHelpersPath);

vms_test_assert($helpersSource !== '', 'Mirror ADD helpers source should be readable.');
vms_test_assert($liveHelpersSource !== '', 'Live ADD helpers source should be readable.');

$targetFunctions = array(
	'vms_add_dispatch_vendor_previously_contacted',
	'vms_add_dispatch_get_response',
	'vms_add_dispatch_find_response_by_raw_token',
	'vms_add_dispatch_log',
	'vms_add_dispatch_get_request',
	'vms_add_dispatch_get_requests_for_event_plan',
	'vms_add_dispatch_get_recent_requests',
	'vms_add_dispatch_get_responses_for_request',
	'vms_add_dispatch_get_recent_responses_for_event_plan',
	'vms_add_dispatch_create_request',
	'vms_add_dispatch_prepare_resend',
	'vms_add_dispatch_close_request',
	'vms_add_dispatch_record_public_response',
	'vms_add_dispatch_get_portal_interest_response',
	'vms_add_dispatch_pending_portal_interest_count',
	'vms_add_dispatch_get_vendor_portal_interest_rows',
	'vms_add_dispatch_reactivate_portal_interest',
	'vms_add_dispatch_withdraw_portal_interest',
	'vms_add_dispatch_apply_assignment_review',
	'vms_add_dispatch_get_recent_responses',
	'vms_add_dispatch_get_pending_portal_interest_rows',
);

foreach ($targetFunctions as $function_name) {
	vms_test_assert_same(
		vms_test_extract_function($helpersSource, $function_name),
		vms_test_extract_function($liveHelpersSource, $function_name),
		$function_name . ' should remain mirror/live identical.'
	);
}

$createRequestSource = vms_test_extract_function($helpersSource, 'vms_add_dispatch_create_request');
$recordResponseSource = vms_test_extract_function($helpersSource, 'vms_add_dispatch_record_public_response');
$reactivateSource = vms_test_extract_function($helpersSource, 'vms_add_dispatch_reactivate_portal_interest');
$withdrawSource = vms_test_extract_function($helpersSource, 'vms_add_dispatch_withdraw_portal_interest');
$assignSource = vms_test_extract_function($helpersSource, 'vms_add_dispatch_apply_assignment_review');

vms_test_assert_contains('ADD request creation persists normalized custom-table rows', $createRequestSource, 'ADD request creation should document its direct custom-table insert boundary.');
vms_test_assert_contains('ADD recipient creation persists normalized custom-table rows', $createRequestSource, 'ADD recipient creation should document its direct custom-table insert boundary.');
vms_test_assert_contains('ADD request rollback deletes the plugin-owned request row directly', $createRequestSource, 'ADD request rollback should document its direct delete boundary.');
vms_test_assert_contains('ADD request finalization writes the plugin-owned request row directly', $createRequestSource, 'ADD request finalization should document its direct update boundary.');
vms_test_assert_contains('ADD public-response writes the plugin-owned response row directly', $recordResponseSource, 'ADD public response should document its direct response update boundary.');
vms_test_assert_contains('Portal-interest reactivation writes the plugin-owned response row directly', $reactivateSource, 'Portal-interest reactivation should document its direct response update boundary.');
vms_test_assert_contains('Portal-interest withdrawal writes the plugin-owned response row directly', $withdrawSource, 'Portal-interest withdrawal should document its direct response update boundary.');
vms_test_assert_contains('ADD assignment finalization writes the plugin-owned response row directly', $assignSource, 'ADD assignment finalization should document its direct response update boundary.');

eval(vms_test_extract_function($helpersSource, 'vms_add_dispatch_vendor_previously_contacted'));
eval(vms_test_extract_function($helpersSource, 'vms_add_dispatch_get_response'));
eval(vms_test_extract_function($helpersSource, 'vms_add_dispatch_find_response_by_raw_token'));
eval(vms_test_extract_function($helpersSource, 'vms_add_dispatch_get_request'));
eval(vms_test_extract_function($helpersSource, 'vms_add_dispatch_get_requests_for_event_plan'));
eval(vms_test_extract_function($helpersSource, 'vms_add_dispatch_get_recent_requests'));
eval(vms_test_extract_function($helpersSource, 'vms_add_dispatch_get_responses_for_request'));
eval(vms_test_extract_function($helpersSource, 'vms_add_dispatch_get_recent_responses_for_event_plan'));
eval(vms_test_extract_function($helpersSource, 'vms_add_dispatch_get_portal_interest_response'));
eval(vms_test_extract_function($helpersSource, 'vms_add_dispatch_pending_portal_interest_count'));
eval(vms_test_extract_function($helpersSource, 'vms_add_dispatch_get_vendor_portal_interest_rows'));
eval(vms_test_extract_function($helpersSource, 'vms_add_dispatch_get_recent_responses'));
eval(vms_test_extract_function($helpersSource, 'vms_add_dispatch_get_pending_portal_interest_rows'));

$wpdb = new VMS_Test_WPDB();
$GLOBALS['wpdb'] = $wpdb;

vms_test_reset_db($wpdb);
$wpdb->get_var_queue[] = '2';
vms_test_assert_same(true, vms_add_dispatch_vendor_previously_contacted(71, 9), 'Previously contacted vendors should return true when the count is positive.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same('SELECT COUNT(1) FROM %i WHERE event_plan_id = %d AND vendor_id = %d', $prepare['query'], 'Previously contacted lookup should prepare the responses table identifier plus both integer filters.');
vms_test_assert_same(array('wp_vms_add_dispatch_responses', 71, 9), $prepare['args'], 'Previously contacted lookup should pass the responses table identifier and both integers to prepare().');
vms_test_assert_no_placeholders(vms_test_last_execution($wpdb, 'get_var')['query'], 'Previously contacted lookup should execute fully prepared SQL.');

vms_test_reset_db($wpdb);
$wpdb->get_row_queue[] = array('id' => 14, 'vendor_id' => 9);
$row = vms_add_dispatch_get_response(14);
vms_test_assert_same(array('id' => 14, 'vendor_id' => 9), $row, 'Single ADD response reads should return the queued row.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same('SELECT * FROM %i WHERE id = %d', $prepare['query'], 'Single ADD response reads should prepare the responses table identifier and response ID.');
vms_test_assert_same(array('wp_vms_add_dispatch_responses', 14), $prepare['args'], 'Single ADD response reads should pass the responses table identifier and response ID to prepare().');
vms_test_assert_no_placeholders(vms_test_last_execution($wpdb, 'get_row')['query'], 'Single ADD response reads should execute fully prepared SQL.');

vms_test_reset_db($wpdb);
$publicKey = 'abc123';
$createdAt = '2026-08-07 12:34:56';
$rawToken = $publicKey . '.' . vms_add_dispatch_token_signature($publicKey, 31, 44, $createdAt);
$wpdb->get_row_queue[] = array(
	'token_public_key' => $publicKey,
	'request_id' => 31,
	'vendor_id' => 44,
	'created_at' => $createdAt,
	'token_hash' => hash('sha256', $rawToken),
);
vms_test_assert_same($wpdb->get_row_queue[0], vms_add_dispatch_find_response_by_raw_token($rawToken), 'Raw-token lookups should return the queued matching response row.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same('SELECT * FROM %i WHERE token_public_key = %s', $prepare['query'], 'Raw-token lookups should prepare the responses table identifier and public key.');
vms_test_assert_same(array('wp_vms_add_dispatch_responses', $publicKey), $prepare['args'], 'Raw-token lookups should pass the responses table identifier and public key to prepare().');
vms_test_assert_no_placeholders(vms_test_last_execution($wpdb, 'get_row')['query'], 'Raw-token lookups should execute fully prepared SQL.');

vms_test_reset_db($wpdb);
$wpdb->get_row_queue[] = array('id' => 55, 'status' => 'active');
vms_test_assert_same(array('id' => 55, 'status' => 'active'), vms_add_dispatch_get_request(55), 'Single ADD request reads should return the queued request row.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same('SELECT * FROM %i WHERE id = %d', $prepare['query'], 'Single ADD request reads should prepare the requests table identifier and request ID.');
vms_test_assert_same(array('wp_vms_add_dispatch_requests', 55), $prepare['args'], 'Single ADD request reads should pass the requests table identifier and request ID to prepare().');

vms_test_reset_db($wpdb);
$wpdb->get_results_queue[] = array(array('id' => 99, 'recipient_total' => '3'));
$rows = vms_add_dispatch_get_requests_for_event_plan(777, 4);
vms_test_assert_same(array(array('id' => 99, 'recipient_total' => '3')), $rows, 'Event-plan request rollups should return the queued rows.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_contains('FROM %i AS r', $prepare['query'], 'Event-plan request rollups should prepare the requests-table identifier.');
vms_test_assert_contains('LEFT JOIN %i AS resp', $prepare['query'], 'Event-plan request rollups should prepare the responses-table identifier.');
vms_test_assert_contains('WHERE r.event_plan_id = %d', $prepare['query'], 'Event-plan request rollups should filter by event_plan_id through prepare().');
vms_test_assert_same(array('wp_vms_add_dispatch_requests', 'wp_vms_add_dispatch_responses', 777, 4), $prepare['args'], 'Event-plan request rollups should pass both table identifiers plus the event-plan and limit integers to prepare().');
vms_test_assert_no_placeholders(vms_test_last_execution($wpdb, 'get_results')['query'], 'Event-plan request rollups should execute fully prepared SQL.');

vms_test_reset_db($wpdb);
$wpdb->get_results_queue[] = array(array('id' => 100, 'recipient_total' => '2'));
$rows = vms_add_dispatch_get_recent_requests(5);
vms_test_assert_same(array(array('id' => 100, 'recipient_total' => '2')), $rows, 'Recent request rollups should return the queued rows.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_contains('FROM %i AS r', $prepare['query'], 'Recent request rollups should prepare the requests-table identifier.');
vms_test_assert_contains('LEFT JOIN %i AS resp', $prepare['query'], 'Recent request rollups should prepare the responses-table identifier.');
vms_test_assert_same(array('wp_vms_add_dispatch_requests', 'wp_vms_add_dispatch_responses', 5), $prepare['args'], 'Recent request rollups should pass both table identifiers plus the limit integer to prepare().');

vms_test_reset_db($wpdb);
$wpdb->get_results_queue[] = array(array('vendor_id' => 22));
$rows = vms_add_dispatch_get_responses_for_request(88);
vms_test_assert_same('Title #22', $rows[0]['vendor_title'] ?? '', 'Request response lists should still decorate vendor titles.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same('SELECT * FROM %i WHERE request_id = %d ORDER BY responded_at DESC, created_at ASC', $prepare['query'], 'Request response lists should prepare the responses table identifier and request filter.');
vms_test_assert_same(array('wp_vms_add_dispatch_responses', 88), $prepare['args'], 'Request response lists should pass the responses table identifier and request ID to prepare().');

vms_test_reset_db($wpdb);
$wpdb->get_results_queue[] = array(array('vendor_id' => 33));
$rows = vms_add_dispatch_get_recent_responses_for_event_plan(444, 7);
vms_test_assert_same('Title #33', $rows[0]['vendor_title'] ?? '', 'Recent event-plan response lists should still decorate vendor titles.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same('SELECT * FROM %i WHERE event_plan_id = %d ORDER BY created_at DESC LIMIT %d', $prepare['query'], 'Recent event-plan response lists should prepare the responses table identifier, event-plan filter, and limit.');
vms_test_assert_same(array('wp_vms_add_dispatch_responses', 444, 7), $prepare['args'], 'Recent event-plan response lists should pass the responses table identifier, event-plan filter, and limit to prepare().');

vms_test_reset_db($wpdb);
$wpdb->get_row_queue[] = array('id' => 1, 'request_status' => 'active');
vms_add_dispatch_get_portal_interest_response(77, 9, true);
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_contains('FROM %i AS resp', $prepare['query'], 'Active portal-interest lookups should prepare the responses-table identifier.');
vms_test_assert_contains('INNER JOIN %i AS req', $prepare['query'], 'Active portal-interest lookups should prepare the requests-table identifier.');
vms_test_assert_contains('AND req.status = %s', $prepare['query'], 'Active portal-interest lookups should bind the active request-status filter through prepare().');
vms_test_assert_same(array('wp_vms_add_dispatch_responses', 'wp_vms_add_dispatch_requests', 77, 9, 'portal_interest', 'active'), $prepare['args'], 'Active portal-interest lookups should pass both table identifiers plus the event-plan, vendor, source, and active filters to prepare().');

vms_test_reset_db($wpdb);
$wpdb->get_row_queue[] = array('id' => 2, 'request_status' => 'closed');
vms_add_dispatch_get_portal_interest_response(77, 9, false);
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_same(array('wp_vms_add_dispatch_responses', 'wp_vms_add_dispatch_requests', 77, 9, 'portal_interest'), $prepare['args'], 'Passive portal-interest lookups should omit the active request-status argument.');
vms_test_assert_no_placeholders(vms_test_last_execution($wpdb, 'get_row')['query'], 'Portal-interest lookups should execute fully prepared SQL.');

vms_test_reset_db($wpdb);
$wpdb->get_var_queue[] = '7';
vms_test_assert_same(7, vms_add_dispatch_pending_portal_interest_count(), 'Pending portal-interest counters should return the queued count.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_contains('FROM %i AS resp', $prepare['query'], 'Pending portal-interest counters should prepare the responses-table identifier.');
vms_test_assert_contains('INNER JOIN %i AS req', $prepare['query'], 'Pending portal-interest counters should prepare the requests-table identifier.');
vms_test_assert_same(array('wp_vms_add_dispatch_responses', 'wp_vms_add_dispatch_requests', 'active', 'portal_interest', 'available'), $prepare['args'], 'Pending portal-interest counters should pass both table identifiers plus status/source filters to prepare().');
$prepare_count = count($wpdb->prepare_calls);
vms_test_assert_same(7, vms_add_dispatch_pending_portal_interest_count(), 'Pending portal-interest counters should reuse the request-local cache on subsequent reads.');
vms_test_assert_same($prepare_count, count($wpdb->prepare_calls), 'Pending portal-interest counters should not prepare a second query after the request-local cache is populated.');

vms_test_reset_db($wpdb);
$wpdb->get_results_queue[] = array(array('vendor_id' => 21));
$rows = vms_add_dispatch_get_vendor_portal_interest_rows(21, 6);
vms_test_assert_same(array(array('vendor_id' => 21)), $rows, 'Vendor portal-interest history should return the queued rows.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_contains('FROM %i AS resp', $prepare['query'], 'Vendor portal-interest history should prepare the responses-table identifier.');
vms_test_assert_contains('INNER JOIN %i AS req', $prepare['query'], 'Vendor portal-interest history should prepare the requests-table identifier.');
vms_test_assert_same(array('wp_vms_add_dispatch_responses', 'wp_vms_add_dispatch_requests', 21, 'portal_interest', 6), $prepare['args'], 'Vendor portal-interest history should pass both table identifiers plus the vendor, source, and limit values to prepare().');

vms_test_reset_db($wpdb);
$wpdb->get_results_queue[] = array(array('vendor_id' => 41, 'event_plan_id' => 88));
$rows = vms_add_dispatch_get_recent_responses(3);
vms_test_assert_same('Title #41', $rows[0]['vendor_title'] ?? '', 'Recent response lists should still decorate vendor titles.');
vms_test_assert_same('Title #88', $rows[0]['event_title'] ?? '', 'Recent response lists should still decorate event titles.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_contains('LEFT JOIN %i AS req', $prepare['query'], 'Recent response lists should prepare the requests-table identifier for the join.');
vms_test_assert_same(array('wp_vms_add_dispatch_responses', 'wp_vms_add_dispatch_requests', 3), $prepare['args'], 'Recent response lists should pass both table identifiers plus the limit to prepare().');

vms_test_reset_db($wpdb);
$wpdb->get_results_queue[] = array(array('vendor_id' => 51, 'event_plan_id' => 99));
$rows = vms_add_dispatch_get_pending_portal_interest_rows(8);
vms_test_assert_same('Title #51', $rows[0]['vendor_title'] ?? '', 'Pending portal-interest rows should still decorate vendor titles.');
vms_test_assert_same('Title #99', $rows[0]['event_title'] ?? '', 'Pending portal-interest rows should still decorate event titles.');
$prepare = vms_test_last_prepare($wpdb);
vms_test_assert_contains('INNER JOIN %i AS req', $prepare['query'], 'Pending portal-interest rows should prepare the requests-table identifier.');
vms_test_assert_same(array('wp_vms_add_dispatch_responses', 'wp_vms_add_dispatch_requests', 'portal_interest', 'available', 8), $prepare['args'], 'Pending portal-interest rows should pass both table identifiers plus source, status, and limit values to prepare().');
vms_test_assert_no_placeholders(vms_test_last_execution($wpdb, 'get_results')['query'], 'Pending portal-interest rows should execute fully prepared SQL.');

fwrite(STDOUT, "ADD repository SQL remediation: PASS\n");
