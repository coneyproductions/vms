<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
define('DAY_IN_SECONDS', 86400);

final class VMS_Ticket_Diagnostics_WPDB_Spy
{
	public string $prefix = 'wp_';
	public int $insert_id = 700;
	public array $log = array();
	public array $prepares = array();
	public array $query_queue = array();
	public array $insert_queue = array();
	public array $get_results_queue = array();

	public function prepare(string $sql, ...$args): string
	{
		if (count($args) === 1 && is_array($args[0])) {
			$args = array_values($args[0]);
		}

		preg_match_all('/(?<!%)%(?:\d+\$)?[sdfi]/', $sql, $matches);
		if (count($matches[0]) !== count($args)) {
			throw new RuntimeException('Placeholder mismatch: ' . $sql);
		}

		$index = 0;
		$final = (string) preg_replace_callback(
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
			$sql
		);

		if (preg_match('/(?<!%)%(?:\d+\$)?[sdfi]/', $final) === 1) {
			throw new RuntimeException('Unresolved placeholder: ' . $final);
		}

		$call = array('kind' => 'prepare', 'sql' => $sql, 'args' => $args, 'final' => $final);
		$this->prepares[] = $call;
		$this->log[] = $call;
		return $final;
	}

	public function query(string $sql)
	{
		$result = $this->shift($this->query_queue, 1);
		$this->log[] = array('kind' => 'query', 'sql' => $sql, 'result' => $result);
		return $result;
	}

	public function insert(string $table, array $data, array $format)
	{
		$result = $this->shift($this->insert_queue, 1);
		$this->log[] = compact('table', 'data', 'format', 'result') + array('kind' => 'insert');
		return $result;
	}

	public function get_results(string $sql, $output = ARRAY_A)
	{
		unset($output);
		$result = $this->shift($this->get_results_queue, array());
		$this->log[] = array('kind' => 'get_results', 'sql' => $sql, 'result' => $result);
		return $result;
	}

	public function get_charset_collate(): string
	{
		return '';
	}

	private function shift(array &$queue, $default)
	{
		return $queue === array() ? $default : array_shift($queue);
	}
}

function add_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): void
{
	unset($hook_name, $callback, $priority, $accepted_args);
}

function add_filter(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): void
{
	unset($hook_name, $callback, $priority, $accepted_args);
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

function get_option(string $key, $default = false)
{
	return array_key_exists($key, $GLOBALS['vms_diag_options']) ? $GLOBALS['vms_diag_options'][$key] : $default;
}

function wp_json_encode($value): string
{
	$encoded = json_encode($value);
	return is_string($encoded) ? $encoded : '';
}

function wp_doing_cron(): bool
{
	return !empty($GLOBALS['vms_diag_doing_cron']);
}

function is_admin(): bool
{
	return !empty($GLOBALS['vms_diag_is_admin']);
}

function bvmgr_admin_guard_heavy_hooks_disabled(): bool
{
	return !empty($GLOBALS['vms_diag_heavy_disabled']);
}

function bvmgr_admin_guard_should_allow_heavy_block(string $hook_name, array $descriptor): array
{
	$GLOBALS['vms_diag_guard_calls'][] = compact('hook_name', 'descriptor');
	return $GLOBALS['vms_diag_guard_result'];
}

function bvmgr_admin_guard_trace(string $hook_name, string $decision, array $payload, float $started_at = 0.0): void
{
	$GLOBALS['vms_diag_trace_calls'][] = compact('hook_name', 'decision', 'payload', 'started_at');
}

function diag_check(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function diag_same($expected, $actual, string $message): void
{
	diag_check(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

function diag_contains(string $needle, string $haystack, string $message): void
{
	diag_check(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle . "\nText: " . $haystack);
}

function diag_reset(VMS_Ticket_Diagnostics_WPDB_Spy $db): void
{
	$db->insert_id = 700;
	$db->log = array();
	$db->prepares = array();
	$db->query_queue = array();
	$db->insert_queue = array();
	$db->get_results_queue = array();
	$GLOBALS['vms_diag_options'] = array(
		'vms_ticket_inventory_audit_db_schema_version' => 'ticket_inventory_audit_v2',
		'vms_ticket_mutation_audit_db_schema_version' => 'ticket_mutation_audit_v1',
	);
	$GLOBALS['vms_diag_doing_cron'] = false;
	$GLOBALS['vms_diag_is_admin'] = true;
	$GLOBALS['vms_diag_heavy_disabled'] = false;
	$GLOBALS['vms_diag_guard_result'] = array('allowed' => true, 'reason' => 'allowed_action');
	$GLOBALS['vms_diag_guard_calls'] = array();
	$GLOBALS['vms_diag_trace_calls'] = array();
	$GLOBALS['bvmgr_ticket_mutation_audit_context_stack'] = array();
}

function diag_calls(VMS_Ticket_Diagnostics_WPDB_Spy $db, string $kind): array
{
	return array_values(array_filter(
		$db->log,
		static function (array $call) use ($kind): bool {
			return ($call['kind'] ?? '') === $kind;
		}
	));
}

function diag_last_call(VMS_Ticket_Diagnostics_WPDB_Spy $db, string $kind): array
{
	$calls = diag_calls($db, $kind);
	diag_check($calls !== array(), 'Expected wpdb call kind: ' . $kind);
	return $calls[count($calls) - 1];
}

function diag_assert_no_unresolved_sql(VMS_Ticket_Diagnostics_WPDB_Spy $db): void
{
	foreach ($db->log as $call) {
		if (!isset($call['sql']) || ($call['kind'] ?? '') === 'prepare') {
			continue;
		}
		diag_check(
			preg_match('/(?<!%)%(?:\d+\$)?[sdfi]/', (string) $call['sql']) !== 1,
			'Executed SQL must not contain unresolved placeholders: ' . (string) $call['sql']
		);
	}
}

function diag_has_broad_suppression(string $source): bool
{
	return preg_match('/phpcs:(?:disable|enable|ignoreFile)\b/i', $source) === 1;
}

$wpdb = new VMS_Ticket_Diagnostics_WPDB_Spy();
$GLOBALS['wpdb'] = $wpdb;
$plugin_root = dirname(__DIR__);
$inventory_path = $plugin_root . '/includes/ticketing/ticket-inventory-forensics.php';
$mutation_path = $plugin_root . '/includes/ticketing/ticket-mutation-audit.php';
$inventory_source = file_get_contents($inventory_path);
$mutation_source = file_get_contents($mutation_path);
diag_check(is_string($inventory_source) && $inventory_source !== '', 'Inventory-forensics source should be readable.');
diag_check(is_string($mutation_source) && $mutation_source !== '', 'Mutation-audit source should be readable.');
require $mutation_path;
require $inventory_path;

// Guard decisions retain disabled, explicit-context, and delegated allow/deny semantics.
diag_reset($wpdb);
$GLOBALS['vms_diag_heavy_disabled'] = true;
$guard = vms_ticket_mutation_audit_guard_decision('Ticket Mutation Audit', -17, '_stock');
diag_same(false, $guard['allowed'], 'Mutation guard should fail closed when heavy hooks are disabled.');
diag_same('constant_disabled', $guard['reason'], 'Mutation guard should retain its disabled reason.');
diag_same('ticketmutationaudit', $guard['hook_name'], 'Mutation guard should sanitize its hook descriptor.');
diag_same(17, $guard['object_id'], 'Mutation guard should normalize object IDs.');
diag_same('_stock', $guard['meta_key'], 'Mutation guard should preserve its diagnostic metadata key.');

diag_reset($wpdb);
vms_ticket_mutation_audit_push_context(array('source_hook' => 'save_post', 'source_function' => 'save_ticket'));
$guard = vms_ticket_mutation_audit_guard_decision('ticket_mutation_audit', 22, '_stock');
diag_same(true, $guard['allowed'], 'Explicit mutation context should allow mutation auditing.');
diag_same('explicit_mutation_context', $guard['reason'], 'Explicit context should retain its reason.');
vms_ticket_mutation_audit_pop_context();

diag_reset($wpdb);
$GLOBALS['vms_diag_guard_result'] = array('allowed' => false, 'reason' => 'passive_admin_request');
$guard = vms_ticket_mutation_audit_guard_decision('ticket_mutation_audit', 23, '_manage_stock');
diag_same(false, $guard['allowed'], 'Delegated mutation guard denial should remain authoritative.');
diag_same('passive_admin_request', $guard['reason'], 'Delegated mutation guard reason should remain intact.');
diag_same(
	array('task' => 'ticket_mutation_audit', 'allow_action' => 'ticket_mutation_audit'),
	$GLOBALS['vms_diag_guard_calls'][0]['descriptor'],
	'Mutation guard should retain its operation-specific heavy-hook descriptor.'
);

diag_reset($wpdb);
$guard = vms_ticket_inventory_forensics_guard_decision(
	'ticket_inventory_forensics',
	24,
	'_stock_status',
	array('source_hook' => 'save_post', 'source_function' => 'save_inventory')
);
diag_same(true, $guard['allowed'], 'Explicit inventory mutation context should allow forensics.');
diag_same('explicit_mutation_context', $guard['reason'], 'Inventory context should retain its allow reason.');
diag_same('_stock_status', $guard['meta_key'], 'Inventory guard should preserve its diagnostic metadata key.');

diag_reset($wpdb);
$GLOBALS['vms_diag_guard_result'] = array('allowed' => true, 'reason' => 'allowed_action');
$guard = vms_ticket_inventory_forensics_guard_decision('ticket_inventory_forensics', 25, '_stock');
diag_same(true, $guard['allowed'], 'Delegated inventory guard approval should remain authoritative.');
diag_same(
	array('task' => 'ticket_inventory_forensics', 'allow_action' => 'ticket_inventory_forensics'),
	$GLOBALS['vms_diag_guard_calls'][0]['descriptor'],
	'Inventory guard should retain its operation-specific heavy-hook descriptor.'
);

// Trace boundaries preserve mutation descriptors without treating meta_key as query configuration.
diag_reset($wpdb);
$started_at = microtime(true) - 0.25;
vms_ticket_mutation_audit_trace(
	'allowed',
	array(
		'hook_name' => 'Ticket Mutation Audit',
		'reason' => 'Explicit Context',
		'object_id' => 31,
		'meta_key' => '_stock',
		'operation' => 'Update',
		'source_hook' => 'save_post',
		'source_function' => 'save_ticket',
	),
	$started_at
);
$trace = $GLOBALS['vms_diag_trace_calls'][0];
diag_same('ticketmutationaudit', $trace['hook_name'], 'Mutation trace should sanitize its hook name.');
diag_same('allowed', $trace['decision'], 'Mutation trace should preserve its decision.');
diag_same('_stock', $trace['payload']['meta_key'], 'Mutation trace should preserve the diagnostic metadata key.');
diag_same('update', $trace['payload']['operation'], 'Mutation trace should sanitize its operation descriptor.');
diag_same($started_at, $trace['started_at'], 'Mutation trace should preserve its timing boundary.');

diag_reset($wpdb);
vms_ticket_inventory_forensics_trace(
	'finished',
	array(
		'hook_name' => 'Ticket Inventory Forensics',
		'reason' => 'Allowed Action',
		'object_id' => 32,
		'meta_key' => '_stock_status',
		'operation' => 'Direct Log',
		'source_hook' => 'admin_post_repair',
		'source_function' => 'repair_ticket',
	),
	$started_at
);
$trace = $GLOBALS['vms_diag_trace_calls'][0];
diag_same('ticketinventoryforensics', $trace['hook_name'], 'Forensics trace should sanitize its hook name.');
diag_same('finished', $trace['decision'], 'Forensics trace should preserve its decision.');
diag_same('_stock_status', $trace['payload']['meta_key'], 'Forensics trace should preserve the diagnostic metadata key.');
diag_same('directlog', $trace['payload']['operation'], 'Forensics trace should sanitize its operation descriptor.');

// Both prune paths retain the exact 90-day cutoff and prepare identifier/value arguments in order.
diag_reset($wpdb);
$before_prune = time() - (90 * DAY_IN_SECONDS);
vms_ticket_inventory_forensics_prune_logs();
$after_prune = time() - (90 * DAY_IN_SECONDS);
$prepare = $wpdb->prepares[0];
diag_same('DELETE FROM %i WHERE created_at_gmt < %s', $prepare['sql'], 'Forensics prune should prepare identifier then cutoff.');
diag_same('wp_vms_ticket_inventory_audit', $prepare['args'][0], 'Forensics prune should pass its custom-table identifier first.');
$cutoff_timestamp = strtotime((string) $prepare['args'][1] . ' UTC');
diag_check(is_int($cutoff_timestamp) && $cutoff_timestamp >= $before_prune - 1 && $cutoff_timestamp <= $after_prune + 1, 'Forensics prune should retain the 90-day cutoff.');
diag_same("DELETE FROM `wp_vms_ticket_inventory_audit` WHERE created_at_gmt < '" . $prepare['args'][1] . "'", diag_last_call($wpdb, 'query')['sql'], 'Forensics prune should execute fully prepared SQL.');
diag_assert_no_unresolved_sql($wpdb);

diag_reset($wpdb);
$before_prune = time() - (90 * DAY_IN_SECONDS);
vms_ticket_mutation_audit_prune_logs();
$after_prune = time() - (90 * DAY_IN_SECONDS);
$prepare = $wpdb->prepares[0];
diag_same('DELETE FROM %i WHERE created_at_gmt < %s', $prepare['sql'], 'Mutation prune should prepare identifier then cutoff.');
diag_same('wp_vms_ticket_mutation_audit', $prepare['args'][0], 'Mutation prune should pass its custom-table identifier first.');
$cutoff_timestamp = strtotime((string) $prepare['args'][1] . ' UTC');
diag_check(is_int($cutoff_timestamp) && $cutoff_timestamp >= $before_prune - 1 && $cutoff_timestamp <= $after_prune + 1, 'Mutation prune should retain the 90-day cutoff.');
diag_same("DELETE FROM `wp_vms_ticket_mutation_audit` WHERE created_at_gmt < '" . $prepare['args'][1] . "'", diag_last_call($wpdb, 'query')['sql'], 'Mutation prune should execute fully prepared SQL.');
diag_assert_no_unresolved_sql($wpdb);

// Insert gates preserve schema checks, false-vs-zero behavior, formats, identities, and post-success pruning.
diag_reset($wpdb);
unset($GLOBALS['vms_diag_options']['vms_ticket_inventory_audit_db_schema_version']);
diag_same(0, vms_ticket_inventory_forensics_insert(array('plan_id' => 4)), 'Forensics insert should fail closed before schema readiness.');
diag_same(array(), $wpdb->log, 'Unavailable forensics schema should not touch wpdb.');

diag_reset($wpdb);
$wpdb->insert_queue[] = false;
diag_same(0, vms_ticket_inventory_forensics_insert(array('plan_id' => 4)), 'Forensics insert should preserve wpdb failure.');
diag_same(0, count(diag_calls($wpdb, 'query')), 'Failed forensics inserts must not prune history.');

diag_reset($wpdb);
$wpdb->insert_id = 701;
$wpdb->insert_queue[] = 0;
$forensics_id = vms_ticket_inventory_forensics_insert(
	array(
		'plan_id' => 4,
		'tec_event_id' => 5,
		'event_title' => '<b>Summer Show</b>',
		'product_id' => 6,
		'user_id' => null,
		'trigger_source' => 'Manual Action!',
		'source_hook' => 'admin_post_repair!',
		'source_function' => '<i>repair_ticket</i>',
		'mutation_key' => '_Stock',
		'product_role' => 'Standard Ticket',
		'change_type' => 'Stock Restored',
		'result_status' => 'skipped',
		'derivation_source' => 'Repair Audit',
		'confidence_level' => 'authoritative',
		'expected_effect' => 'reopen',
		'reason_text' => '<b>Repair completed</b>',
		'summary_text' => '<b>Stock restored</b>',
		'before_json' => array('stock' => 0),
		'after_json' => array('stock' => 8),
		'details_json' => array('writer_branch' => 'repair'),
	)
);
diag_same(701, $forensics_id, 'Zero affected rows should remain a successful forensics insert returning insert_id.');
$insert = diag_last_call($wpdb, 'insert');
diag_same('wp_vms_ticket_inventory_audit', $insert['table'], 'Forensics insert should target its plugin-owned table.');
diag_same('Summer Show', $insert['data']['event_title'], 'Forensics insert should retain title sanitization.');
diag_same(null, $insert['data']['user_id'], 'Forensics insert should preserve a nullable user ID.');
diag_same('manualaction', $insert['data']['trigger_source'], 'Forensics insert should retain trigger sanitization.');
diag_same('skipped', $insert['data']['result_status'], 'Forensics insert should preserve its supported skipped result.');
diag_same('{"stock":0}', $insert['data']['before_json'], 'Forensics insert should encode snapshot arrays.');
diag_same(
	array('%s', '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
	$insert['format'],
	'Forensics insert should preserve its exact format contract.'
);
diag_same(count($insert['data']), count($insert['format']), 'Every forensics insert field should retain a format.');
diag_same(1, count(diag_calls($wpdb, 'query')), 'Successful forensics insert should prune once.');
diag_assert_no_unresolved_sql($wpdb);

diag_reset($wpdb);
unset($GLOBALS['vms_diag_options']['vms_ticket_mutation_audit_db_schema_version']);
diag_same(0, vms_ticket_mutation_audit_insert(array('plan_id' => 7)), 'Mutation insert should fail closed before schema readiness.');
diag_same(array(), $wpdb->log, 'Unavailable mutation schema should not touch wpdb.');

diag_reset($wpdb);
$wpdb->insert_queue[] = false;
diag_same(0, vms_ticket_mutation_audit_insert(array('plan_id' => 7)), 'Mutation insert should preserve wpdb failure.');
diag_same(0, count(diag_calls($wpdb, 'query')), 'Failed mutation inserts must not prune history.');

diag_reset($wpdb);
$wpdb->insert_id = 702;
$wpdb->insert_queue[] = 0;
$mutation_id = vms_ticket_mutation_audit_insert(
	array(
		'plan_id' => 7,
		'tec_event_id' => 8,
		'event_title' => '<b>Autumn Show</b>',
		'user_id' => null,
		'trigger_source' => 'Save Hook!',
		'source_hook' => 'save_post!',
		'source_function' => '<i>save_ticket</i>',
		'change_type' => 'Sync Map Updated',
		'result_status' => 'skipped',
		'summary_text' => '<b>Mapping refreshed</b>',
		'before_json' => array('mapped' => 0),
		'after_json' => array('mapped' => 1),
	)
);
diag_same(702, $mutation_id, 'Zero affected rows should remain a successful mutation insert returning insert_id.');
$insert = diag_last_call($wpdb, 'insert');
diag_same('wp_vms_ticket_mutation_audit', $insert['table'], 'Mutation insert should target its plugin-owned table.');
diag_same('Autumn Show', $insert['data']['event_title'], 'Mutation insert should retain title sanitization.');
diag_same(null, $insert['data']['user_id'], 'Mutation insert should preserve a nullable user ID.');
diag_same('savehook', $insert['data']['trigger_source'], 'Mutation insert should retain trigger sanitization.');
diag_same('success', $insert['data']['result_status'], 'Unsupported mutation result states should retain the success fallback.');
diag_same(
	array('%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
	$insert['format'],
	'Mutation insert should preserve its exact format contract.'
);
diag_same(count($insert['data']), count($insert['format']), 'Every mutation insert field should retain a format.');
diag_same(1, count(diag_calls($wpdb, 'query')), 'Successful mutation insert should prune once.');
diag_assert_no_unresolved_sql($wpdb);

diag_same('partial', vms_ticket_inventory_forensics_normalize_result_status('Partial'), 'Forensics result normalization should preserve partial.');
diag_same('skipped', vms_ticket_inventory_forensics_normalize_result_status('Skipped'), 'Forensics result normalization should preserve skipped.');
diag_same('success', vms_ticket_inventory_forensics_normalize_result_status('unexpected'), 'Forensics result normalization should preserve success fallback.');
diag_same('no_op', vms_ticket_mutation_audit_normalize_result_status('no_op'), 'Mutation result normalization should preserve no-op.');
diag_same('success', vms_ticket_mutation_audit_normalize_result_status('skipped'), 'Mutation result normalization should preserve success fallback.');

// Recent-log repositories preserve invalid/schema gates, fresh reads, DESC limits, and the optional product filter.
diag_reset($wpdb);
diag_same(array(), vms_ticket_inventory_forensics_recent_logs(0, 8, 4), 'Forensics recent logs should reject invalid plan IDs.');
unset($GLOBALS['vms_diag_options']['vms_ticket_inventory_audit_db_schema_version']);
diag_same(array(), vms_ticket_inventory_forensics_recent_logs(4, 8, 4), 'Forensics recent logs should fail closed before schema readiness.');
diag_same(array(), $wpdb->log, 'Rejected forensics reads should not touch wpdb.');

diag_reset($wpdb);
$wpdb->get_results_queue = array(
	array(array('id' => 81, 'plan_id' => 4, 'product_id' => 9, 'result_status' => 'success')),
	array(array('id' => 82, 'plan_id' => 4, 'product_id' => 9, 'result_status' => 'partial')),
);
$first = vms_ticket_inventory_forensics_recent_logs(4, 500, 9);
$second = vms_ticket_inventory_forensics_recent_logs(4, 500, 9);
diag_same(81, $first[0]['id'], 'First product-filtered forensics read should preserve its database result.');
diag_same(82, $second[0]['id'], 'Repeated product-filtered forensics read should remain fresh.');
diag_same(2, count(diag_calls($wpdb, 'get_results')), 'Repeated product-filtered forensics reads should query twice.');
foreach ($wpdb->prepares as $prepare) {
	diag_same(
		'SELECT * FROM %i WHERE plan_id = %d AND product_id = %d ORDER BY id DESC LIMIT %d',
		$prepare['sql'],
		'Product-filtered forensics SQL should retain its exact prepared shape.'
	);
	diag_same(array('wp_vms_ticket_inventory_audit', 4, 9, 100), $prepare['args'], 'Product-filtered forensics read should pass table, plan, product, and clamped limit in order.');
	diag_same('SELECT * FROM `wp_vms_ticket_inventory_audit` WHERE plan_id = 4 AND product_id = 9 ORDER BY id DESC LIMIT 100', $prepare['final'], 'Product-filtered forensics SQL should retain DESC order and bounded limit.');
}
diag_assert_no_unresolved_sql($wpdb);

diag_reset($wpdb);
$wpdb->get_results_queue[] = array(array('id' => 83, 'plan_id' => 4, 'result_status' => 'no_op'));
$rows = vms_ticket_inventory_forensics_recent_logs(4, 0, 0);
diag_same(83, $rows[0]['id'], 'Plan-wide forensics read should preserve its result.');
$prepare = $wpdb->prepares[0];
diag_same('SELECT * FROM %i WHERE plan_id = %d ORDER BY id DESC LIMIT %d', $prepare['sql'], 'Plan-wide forensics SQL should retain its exact prepared shape.');
diag_same(array('wp_vms_ticket_inventory_audit', 4, 1), $prepare['args'], 'Plan-wide forensics read should pass table, plan, and minimum limit in order.');
diag_same('SELECT * FROM `wp_vms_ticket_inventory_audit` WHERE plan_id = 4 ORDER BY id DESC LIMIT 1', $prepare['final'], 'Plan-wide forensics SQL should retain DESC order and bounded limit.');
diag_assert_no_unresolved_sql($wpdb);

diag_reset($wpdb);
diag_same(array(), vms_ticket_mutation_audit_recent_logs(0, 5), 'Mutation recent logs should reject invalid plan IDs.');
unset($GLOBALS['vms_diag_options']['vms_ticket_mutation_audit_db_schema_version']);
diag_same(array(), vms_ticket_mutation_audit_recent_logs(7, 5), 'Mutation recent logs should fail closed before schema readiness.');
diag_same(array(), $wpdb->log, 'Rejected mutation reads should not touch wpdb.');

diag_reset($wpdb);
$wpdb->get_results_queue = array(
	array(array('id' => 91, 'plan_id' => 7, 'result_status' => 'success')),
	array(array('id' => 92, 'plan_id' => 7, 'result_status' => 'failed')),
);
$first = vms_ticket_mutation_audit_recent_logs(7, 500);
$second = vms_ticket_mutation_audit_recent_logs(7, 500);
diag_same(91, $first[0]['id'], 'First mutation history read should preserve its database result.');
diag_same(92, $second[0]['id'], 'Repeated mutation history read should remain fresh.');
diag_same(2, count(diag_calls($wpdb, 'get_results')), 'Repeated mutation history reads should query twice.');
foreach ($wpdb->prepares as $prepare) {
	diag_same('SELECT * FROM %i WHERE plan_id = %d ORDER BY id DESC LIMIT %d', $prepare['sql'], 'Mutation-history SQL should retain its exact prepared shape.');
	diag_same(array('wp_vms_ticket_mutation_audit', 7, 50), $prepare['args'], 'Mutation-history read should pass table, plan, and clamped limit in order.');
	diag_same('SELECT * FROM `wp_vms_ticket_mutation_audit` WHERE plan_id = 7 ORDER BY id DESC LIMIT 50', $prepare['final'], 'Mutation-history SQL should retain DESC order and bounded limit.');
}
diag_assert_no_unresolved_sql($wpdb);

// Reconcile the exact 27-row G10 baseline without suppressing prepared-identifier findings.
$owned_baseline = array(
	'includes/ticketing/ticket-inventory-forensics.php' => array(
		'WordPress.DB.SlowDBQuery.slow_db_query_meta_key' => 2,
		'WordPress.DB.DirectDatabaseQuery.DirectQuery' => 4,
		'WordPress.DB.DirectDatabaseQuery.NoCaching' => 3,
		'PluginCheck.Security.DirectDB.UnescapedDBParameter' => 3,
		'WordPress.DB.PreparedSQL.InterpolatedNotPrepared' => 3,
	),
	'includes/ticketing/ticket-mutation-audit.php' => array(
		'WordPress.DB.SlowDBQuery.slow_db_query_meta_key' => 3,
		'WordPress.DB.DirectDatabaseQuery.DirectQuery' => 3,
		'WordPress.DB.DirectDatabaseQuery.NoCaching' => 2,
		'PluginCheck.Security.DirectDB.UnescapedDBParameter' => 2,
		'WordPress.DB.PreparedSQL.InterpolatedNotPrepared' => 2,
	),
);
diag_same(15, array_sum($owned_baseline['includes/ticketing/ticket-inventory-forensics.php']), 'Inventory-forensics baseline should remain exactly 15 rows.');
diag_same(12, array_sum($owned_baseline['includes/ticketing/ticket-mutation-audit.php']), 'Mutation-audit baseline should remain exactly 12 rows.');
diag_same(27, array_sum(array_map('array_sum', $owned_baseline)), 'Combined G10 diagnostics baseline should remain exactly 27 rows.');

preg_match_all('/\$wpdb->(?:query|insert|get_results)\s*\(/', $inventory_source, $inventory_operations);
preg_match_all('/\$wpdb->(?:query|insert|get_results)\s*\(/', $mutation_source, $mutation_operations);
diag_same(4, count($inventory_operations[0]), 'Inventory-forensics should retain exactly four custom-table operations.');
diag_same(3, count($mutation_operations[0]), 'Mutation-audit should retain exactly three custom-table operations.');
diag_same(4, substr_count($inventory_source, 'WordPress.DB.DirectDatabaseQuery.DirectQuery'), 'Every forensics operation should have one narrow DirectQuery annotation.');
diag_same(3, substr_count($mutation_source, 'WordPress.DB.DirectDatabaseQuery.DirectQuery'), 'Every mutation-audit operation should have one narrow DirectQuery annotation.');
diag_same(3, substr_count($inventory_source, 'WordPress.DB.DirectDatabaseQuery.NoCaching'), 'Only forensics prune/read operations should have narrow NoCaching annotations.');
diag_same(2, substr_count($mutation_source, 'WordPress.DB.DirectDatabaseQuery.NoCaching'), 'Only mutation prune/read operations should have narrow NoCaching annotations.');
diag_same(0, substr_count($inventory_source, 'PluginCheck.Security.DirectDB.UnescapedDBParameter'), 'Forensics %i preparation should eliminate unescaped-identifier findings without suppression.');
diag_same(0, substr_count($mutation_source, 'PluginCheck.Security.DirectDB.UnescapedDBParameter'), 'Mutation %i preparation should eliminate unescaped-identifier findings without suppression.');
diag_same(0, substr_count($inventory_source, 'WordPress.DB.PreparedSQL.InterpolatedNotPrepared'), 'Forensics %i preparation should eliminate interpolated-query findings without suppression.');
diag_same(0, substr_count($mutation_source, 'WordPress.DB.PreparedSQL.InterpolatedNotPrepared'), 'Mutation %i preparation should eliminate interpolated-query findings without suppression.');

preg_match_all('/^[^\n]*[\x27]meta_key[\x27]\s*=>[^\n]*phpcs:ignore WordPress\.DB\.SlowDBQuery\.slow_db_query_meta_key[^\n]*$/m', $inventory_source, $inventory_meta_annotations);
preg_match_all('/^[^\n]*[\x27]meta_key[\x27]\s*=>[^\n]*phpcs:ignore WordPress\.DB\.SlowDBQuery\.slow_db_query_meta_key[^\n]*$/m', $mutation_source, $mutation_meta_annotations);
diag_same(2, count($inventory_meta_annotations[0]), 'Forensics should annotate only its two descriptor-array meta_key occurrences.');
diag_same(2, count($mutation_meta_annotations[0]), 'Mutation audit should annotate only its two retained descriptor-array meta_key occurrences.');
diag_same(2, substr_count($inventory_source, 'WordPress.DB.SlowDBQuery.slow_db_query_meta_key'), 'Forensics should carry exactly two occurrence-specific slow-meta-key annotations.');
diag_same(2, substr_count($mutation_source, 'WordPress.DB.SlowDBQuery.slow_db_query_meta_key'), 'Mutation audit should carry exactly two retained occurrence-specific slow-meta-key annotations.');

diag_check(!diag_has_broad_suppression($inventory_source), 'Forensics remediation must reject file-wide and block-wide PHPCS suppression.');
diag_check(!diag_has_broad_suppression($mutation_source), 'Mutation remediation must reject file-wide and block-wide PHPCS suppression.');
diag_check(diag_has_broad_suppression($inventory_source . "\n// phpcs:disable WordPress.DB.DirectDatabaseQuery"), 'The broad-suppression guard should reject an invented block-wide suppression.');
diag_check(strpos($inventory_source, 'DELETE FROM {' . '$table}') === false, 'Forensics prune must not interpolate its custom-table identifier.');
diag_check(strpos($inventory_source, 'FROM {' . '$table}') === false, 'Forensics reads must not interpolate custom-table identifiers.');
diag_check(strpos($mutation_source, 'DELETE FROM {' . '$table}') === false, 'Mutation prune must not interpolate its custom-table identifier.');
diag_check(strpos($mutation_source, 'FROM {' . '$table}') === false, 'Mutation reads must not interpolate custom-table identifiers.');
diag_contains('Ninety-day retention pruning deletes expired rows from the plugin-owned forensics table', $inventory_source, 'Forensics prune annotation should stay operation-specific.');
diag_contains('Product-filtered forensics history must read request-fresh', $inventory_source, 'Filtered forensics read annotation should stay operation-specific.');
diag_contains('Plan-wide forensics history must read request-fresh', $inventory_source, 'Plan-wide forensics read annotation should stay operation-specific.');
diag_contains('Ninety-day retention pruning deletes expired rows from the plugin-owned mutation audit table', $mutation_source, 'Mutation prune annotation should stay operation-specific.');
diag_contains('Mutation history must read request-fresh', $mutation_source, 'Mutation-history read annotation should stay operation-specific.');
diag_same(0, substr_count($mutation_source, 'error_log('), 'Mutation trace fallback should use the bounded operational adapter.');
diag_same(1, substr_count($mutation_source, 'debug_backtrace('), 'Mutation source detection should retain exactly one bounded backtrace call.');
diag_same(1, substr_count($mutation_source, 'WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace'), 'Mutation source detection should carry exactly one narrow backtrace annotation.');
diag_contains("bvmgr_record_operational_issue('ticket_mutation_audit_trace'", $mutation_source, 'Mutation trace fallback should retain its fixed operational event.');

// Baseline and remediated shared runtime stay byte-identical across the isolated mirror/shadow pair.
$shadow_root = dirname($plugin_root, 2) . '/vms';
$shadow_inventory = file_get_contents($shadow_root . '/includes/ticketing/ticket-inventory-forensics.php');
$shadow_mutation = file_get_contents($shadow_root . '/includes/ticketing/ticket-mutation-audit.php');
diag_same($inventory_source, $shadow_inventory, 'Inventory-forensics mirror/shadow files should retain full parity.');
diag_same($mutation_source, $shadow_mutation, 'Mutation-audit mirror/shadow files should retain full parity.');

fwrite(STDOUT, "ticket diagnostics repository SQL remediation: PASS\n");
