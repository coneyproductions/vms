<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
if (!defined('ARRAY_A')) {
	define('ARRAY_A', 'ARRAY_A');
}

final class VMS_Rules_V2_WPDB_Spy
{
	public string $prefix;
	public string $posts;
	/** @var array<int,array{query:string,args:array<int,mixed>,sql:string}> */
	public array $prepares = array();
	/** @var array<int,array{kind:string,sql:string,result:mixed}> */
	public array $reads = array();
	/** @var array<int,mixed> */
	public array $get_var_queue = array();
	/** @var array<int,mixed> */
	public array $get_results_queue = array();
	/** @var array<int,string> */
	public array $mutations = array();

	public function __construct(string $prefix)
	{
		$this->prefix = $prefix;
		$this->posts = $prefix . 'posts';
	}

	public function prepare(string $query, ...$args): string
	{
		if (count($args) === 1 && is_array($args[0])) {
			$args = array_values($args[0]);
		}
		$index = 0;
		$sql = preg_replace_callback(
			'/(?<!%)%(?:\d+\$)?[sdi]/',
			function (array $match) use (&$index, $args): string {
				if (!array_key_exists($index, $args)) {
					throw new RuntimeException('Missing prepared-query argument.');
				}
				$value = $args[$index++];
				$type = substr($match[0], -1);
				if ($type === 'd') {
					return (string) (int) $value;
				}
				if ($type === 'i') {
					$parts = explode('.', (string) $value);
					return implode('.', array_map(static fn(string $part): string => '`' . str_replace('`', '``', $part) . '`', $parts));
				}
				return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $value) . "'";
			},
			$query
		);
		if (!is_string($sql) || $index !== count($args)) {
			throw new RuntimeException('Prepared-query placeholder/argument mismatch.');
		}
		$this->prepares[] = array('query' => $query, 'args' => $args, 'sql' => $sql);
		return $sql;
	}

	public function get_var(string $sql)
	{
		$result = $this->get_var_queue === array() ? null : array_shift($this->get_var_queue);
		$this->reads[] = array('kind' => 'get_var', 'sql' => $sql, 'result' => $result);
		return $result;
	}

	public function get_results(string $sql, $format = ARRAY_A)
	{
		unset($format);
		$result = $this->get_results_queue === array() ? array() : array_shift($this->get_results_queue);
		$this->reads[] = array('kind' => 'get_results', 'sql' => $sql, 'result' => $result);
		return $result;
	}

	public function query(string $sql): int
	{
		$this->mutations[] = $sql;
		throw new RuntimeException('Rules-v2 repository boundary unexpectedly mutated the database.');
	}
}

final class VMS_Rules_V2_WP_Query_Spy
{
	/** @var array<int,array<string,mixed>> */
	public static array $calls = array();
	/** @var array<int,array<int,int>> */
	public static array $queue = array();
	/** @var array<int,int> */
	public array $posts = array();

	public function __construct(array $args)
	{
		self::$calls[] = $args;
		$this->posts = self::$queue === array() ? array() : array_shift(self::$queue);
	}
}

class_alias(VMS_Rules_V2_WP_Query_Spy::class, 'WP_Query');

$GLOBALS['vms_rules_v2_wc_orders_queue'] = array();
$GLOBALS['vms_rules_v2_reset_postdata_calls'] = 0;

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_email($value): string
{
	if (!is_scalar($value)) {
		return '';
	}
	$sanitized = filter_var(strtolower(trim((string) $value)), FILTER_SANITIZE_EMAIL);
	return is_string($sanitized) ? $sanitized : '';
}

function sanitize_text_field($value): string
{
	return is_scalar($value) ? trim(strip_tags((string) $value)) : '';
}

function wc_get_orders(array $args): array
{
	unset($args);
	return $GLOBALS['vms_rules_v2_wc_orders_queue'] === array() ? array() : array_shift($GLOBALS['vms_rules_v2_wc_orders_queue']);
}

function wc_get_order(int $order_id)
{
	unset($order_id);
	return null;
}

function bvmgr_ticketing_b_meta_key(string $key, string $fallback): string
{
	unset($key);
	return $fallback;
}

function wp_reset_postdata(): void
{
	$GLOBALS['vms_rules_v2_reset_postdata_calls']++;
}

function vms_test_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function vms_test_same($expected, $actual, string $message): void
{
	vms_test_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function vms_test_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle . "\nSQL: " . $haystack);
}

function vms_test_not_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert(strpos($haystack, $needle) === false, $message . "\nUnexpected: " . $needle . "\nSQL: " . $haystack);
}

function vms_test_no_placeholders(string $sql): void
{
	vms_test_assert(preg_match('/(?<!%)%(?:\d+\$)?[sdi]/', $sql) !== 1, 'Executed SQL retains a placeholder: ' . $sql);
}

function vms_test_extract_function(string $source, string $name): string
{
	$start = strpos($source, 'function ' . $name . '(');
	$brace = $start === false ? false : strpos($source, '{', $start);
	if ($start === false || $brace === false) {
		throw new RuntimeException('Unable to find function ' . $name . '.');
	}
	$depth = 1;
	for ($i = $brace + 1, $length = strlen($source); $i < $length; $i++) {
		$depth += $source[$i] === '{' ? 1 : 0;
		$depth -= $source[$i] === '}' ? 1 : 0;
		if ($depth === 0) {
			return substr($source, $start, ($i - $start) + 1);
		}
	}
	throw new RuntimeException('Unable to parse function ' . $name . '.');
}

/** @return array{query:string,args:array<int,mixed>,sql:string} */
function vms_test_prepare_containing(VMS_Rules_V2_WPDB_Spy $spy, string $needle): array
{
	foreach (array_reverse($spy->prepares) as $call) {
		if (strpos($call['query'], $needle) !== false) {
			return $call;
		}
	}
	throw new RuntimeException('Missing prepare containing ' . $needle . '.');
}

/** @return array{kind:string,sql:string,result:mixed} */
function vms_test_read_containing(VMS_Rules_V2_WPDB_Spy $spy, string $needle): array
{
	foreach (array_reverse($spy->reads) as $call) {
		if (strpos($call['sql'], $needle) !== false) {
			return $call;
		}
	}
	throw new RuntimeException('Missing read containing ' . $needle . '.');
}

$source = file_get_contents(dirname(__DIR__) . '/includes/integrations/ticketing-rules-v2.php');
if (!is_string($source)) {
	throw new RuntimeException('Unable to read rules-v2 source.');
}

$purchased_source = vms_test_extract_function($source, 'bvmgr_ticketing_v2_purchased_ticket_qty_for_user');
$decode_source = vms_test_extract_function($source, 'bvmgr_ticketing_v2_decode_stored_claim_assignment_rows');
$assignee_source = vms_test_extract_function($source, 'bvmgr_ticketing_v2_assignee_consumed_qty_for_event');
$plan_source = vms_test_extract_function($source, 'bvmgr_ticketing_v2_find_plan_id_by_tec_event_id');
eval($purchased_source);
eval($decode_source);
eval($assignee_source);
eval($plan_source);
$fallback_assignee_source = preg_replace(
	'/function bvmgr_ticketing_v2_assignee_consumed_qty_for_event\(/',
	'function vms_ticketing_v2_assignee_consumed_qty_for_event_fallback(',
	$assignee_source,
	1
);
if (!is_string($fallback_assignee_source)) {
	throw new RuntimeException('Unable to create isolated assignee fallback function.');
}
eval($fallback_assignee_source);

$owned_inventory = array(
	'PluginCheck.Security.DirectDB.UnescapedDBParameter' => 2,
	'WordPress.DB.DirectDatabaseQuery.DirectQuery' => 8,
	'WordPress.DB.DirectDatabaseQuery.NoCaching' => 8,
	'WordPress.DB.PreparedSQL.NotPrepared' => 3,
	'WordPress.DB.SlowDBQuery.slow_db_query_meta_query' => 2,
);
vms_test_same(23, array_sum($owned_inventory), 'Rules-v2 owned scanner inventory should remain exactly 23 baseline rows.');

$covered_rows = 0;
$coverage = array(
	array('body' => $purchased_source, 'fragment' => 'load-order fallback performs prepared WooCommerce capability probes', 'rows' => 2),
	array('body' => $purchased_source, 'fragment' => 'stats-table capability probe', 'rows' => 2),
	array('body' => $purchased_source, 'fragment' => 'HPOS-orders capability probe', 'rows' => 2),
	array('body' => $purchased_source, 'fragment' => 'purchased-quantity aggregate contains only prepared identifiers/values', 'rows' => 1),
	array('body' => $purchased_source, 'fragment' => 'phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching', 'rows' => 4),
	array('body' => $assignee_source, 'fragment' => 'lookup-table name', 'rows' => 2),
	array('body' => $assignee_source, 'fragment' => 'stats-table name', 'rows' => 2),
	array('body' => $assignee_source, 'fragment' => 'itemmeta-table name', 'rows' => 2),
	array('body' => $assignee_source, 'fragment' => 'phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching', 'rows' => 4),
	array('body' => vms_test_extract_function($source, 'bvmgr_ticketing_v2_resolve_ticket_max_context'), 'fragment' => 'WordPress.DB.SlowDBQuery.slow_db_query_meta_query', 'rows' => 1),
	array('body' => $plan_source, 'fragment' => 'WordPress.DB.SlowDBQuery.slow_db_query_meta_query', 'rows' => 1),
);
foreach ($coverage as $boundary) {
	vms_test_contains($boundary['fragment'], $boundary['body'], 'Missing occurrence-specific rules-v2 scanner coverage.');
	$covered_rows += $boundary['rows'];
}
vms_test_same(0, array_sum($owned_inventory) - $covered_rows, 'All 23 owned baseline rows should have zero residual intent.');

// Purchased-ticket aggregate: HPOS refund joins, product/variation IDs, statuses, result, and request cache.
$wpdb = new VMS_Rules_V2_WPDB_Spy('wp_hpos_');
$oi = 'wp_hpos_woocommerce_order_items';
$oim = 'wp_hpos_woocommerce_order_itemmeta';
$stats = 'wp_hpos_wc_order_stats';
$orders = 'wp_hpos_wc_orders';
$wpdb->get_var_queue = array($oi, $oim, $stats, $orders, 6);
$GLOBALS['wpdb'] = $wpdb;
$purchased = bvmgr_ticketing_v2_purchased_ticket_qty_for_user(101, array(9, 8, 9, 0));
vms_test_same(6, $purchased, 'HPOS purchased-ticket aggregate result changed.');
$call = vms_test_prepare_containing($wpdb, 'GREATEST(0, line_items.qty');
vms_test_same(
	array($oi, $oim, 8, 9, 8, 9, $stats, 101, $oi, $oim, $oim, 'wp_hpos_posts', 'wc-processing', 'wc-completed', 'wc-on-hold'),
	$call['args'],
	'HPOS purchased-ticket prepare arguments changed.'
);
$sql = vms_test_read_containing($wpdb, 'GREATEST(0, line_items.qty')['sql'];
vms_test_contains('LEFT JOIN `wp_hpos_wc_orders` refund_orders', $sql, 'HPOS refund type join changed.');
vms_test_contains('refund_posts.post_type = \'shop_order_refund\' OR refund_orders.type = \'shop_order_refund\'', $sql, 'HPOS/CPT refund type alternatives changed.');
vms_test_contains('HAVING product_id IN (8,9) OR variation_id IN (8,9)', $sql, 'Purchased-ticket product/variation matching changed.');
vms_test_contains("stats.status IN ('wc-processing','wc-completed','wc-on-hold')", $sql, 'Purchased-ticket paid statuses changed.');
vms_test_no_placeholders($sql);
$read_count = count($wpdb->reads);
vms_test_same(6, bvmgr_ticketing_v2_purchased_ticket_qty_for_user(101, array(8, 9)), 'Request-cached purchased-ticket result changed.');
vms_test_same($read_count, count($wpdb->reads), 'Repeated purchased-ticket lookup should use its request cache.');
vms_test_same(array(), $wpdb->mutations, 'Purchased-ticket lookup must remain read-only.');

// Non-HPOS refund branch preserves CPT refund detection and the same aggregate semantics.
$wpdb = new VMS_Rules_V2_WPDB_Spy('wp_cpt_');
$oi = 'wp_cpt_woocommerce_order_items';
$oim = 'wp_cpt_woocommerce_order_itemmeta';
$stats = 'wp_cpt_wc_order_stats';
$wpdb->get_var_queue = array($oi, $oim, $stats, null, 3);
$GLOBALS['wpdb'] = $wpdb;
vms_test_same(3, bvmgr_ticketing_v2_purchased_ticket_qty_for_user(102, array(11)), 'CPT purchased-ticket aggregate result changed.');
$sql = vms_test_read_containing($wpdb, 'GREATEST(0, line_items.qty')['sql'];
vms_test_not_contains('refund_orders', $sql, 'CPT refund branch should not reference unavailable HPOS orders.');
vms_test_contains("AND (refund_posts.post_type = 'shop_order_refund')", $sql, 'CPT refund type condition changed.');
vms_test_no_placeholders($sql);

// Missing repository tables preserve the WooCommerce API fallback.
$wpdb = new VMS_Rules_V2_WPDB_Spy('wp_missing_');
$wpdb->get_var_queue = array(null, null);
$GLOBALS['wpdb'] = $wpdb;
$GLOBALS['vms_rules_v2_wc_orders_queue'][] = array();
vms_test_same(0, bvmgr_ticketing_v2_purchased_ticket_qty_for_user(103, array(12)), 'Missing purchased-ticket tables should preserve the WooCommerce API fallback.');

// Assignee lookup: identifiers/values, decoded result count, and lookup-support caching.
$wpdb = new VMS_Rules_V2_WPDB_Spy('wp_claim_');
$lookup = 'wp_claim_wc_order_product_lookup';
$stats = 'wp_claim_wc_order_stats';
$itemmeta = 'wp_claim_woocommerce_order_itemmeta';
$wpdb->get_var_queue = array($lookup, $stats, $itemmeta);
$wpdb->get_results_queue[] = array(
	array('assignments_json' => '[{"assignee_email":"guest@example.com"},{"email":"other@example.com"}]'),
	array('assignments_json' => '[{"email":"GUEST@example.com"}]'),
);
$GLOBALS['wpdb'] = $wpdb;
$consumed = bvmgr_ticketing_v2_assignee_consumed_qty_for_event(55, 'Guest@Example.com', array(3, 4, 3));
vms_test_same(2, $consumed, 'Assignee consumed quantity should count matching stored assignment rows.');
$call = vms_test_prepare_containing($wpdb, 'SELECT lookup.order_item_id');
vms_test_same(
	array($lookup, $stats, $itemmeta, $itemmeta, 'wc-processing', 'wc-completed', 'wc-on-hold', 3, 4, 3, 4, 55),
	$call['args'],
	'Assignee lookup prepare arguments changed.'
);
$sql = vms_test_read_containing($wpdb, 'SELECT lookup.order_item_id')['sql'];
vms_test_contains('FROM `wp_claim_wc_order_product_lookup` lookup', $sql, 'Assignee lookup table identifier was not prepared.');
vms_test_contains('LEFT JOIN `wp_claim_woocommerce_order_itemmeta` claim_meta', $sql, 'Assignee claim-meta join changed.');
vms_test_contains('(lookup.product_id IN (3,4) OR lookup.variation_id IN (3,4))', $sql, 'Assignee product/variation matching changed.');
vms_test_contains('CAST(event_meta.meta_value AS UNSIGNED) = 55', $sql, 'Assignee event filter changed.');
vms_test_no_placeholders($sql);
vms_test_same(array(), $wpdb->mutations, 'Assignee lookup must remain read-only.');

$wpdb = new VMS_Rules_V2_WPDB_Spy('wp_claim_fallback_');
$wpdb->get_var_queue = array(null, null, null);
$GLOBALS['wpdb'] = $wpdb;
$GLOBALS['vms_rules_v2_wc_orders_queue'][] = array();
vms_test_same(0, vms_ticketing_v2_assignee_consumed_qty_for_event_fallback(56, 'guest@example.com', array(3)), 'Missing assignee lookup tables should preserve the WooCommerce API fallback.');

// WP_Query fallback remains exact, bounded, deterministic, and resets global post state.
VMS_Rules_V2_WP_Query_Spy::$calls = array();
VMS_Rules_V2_WP_Query_Spy::$queue = array(array(901));
$GLOBALS['vms_rules_v2_reset_postdata_calls'] = 0;
vms_test_same(0, bvmgr_ticketing_v2_find_plan_id_by_tec_event_id(0), 'Invalid TEC event IDs should not query.');
vms_test_same(901, bvmgr_ticketing_v2_find_plan_id_by_tec_event_id(77), 'WP_Query fallback should return the newest matching plan ID.');
vms_test_same(1, count(VMS_Rules_V2_WP_Query_Spy::$calls), 'WP_Query fallback should execute once.');
$args = VMS_Rules_V2_WP_Query_Spy::$calls[0];
vms_test_same(1, $args['posts_per_page'], 'Plan fallback should remain single-result bounded.');
vms_test_same('modified', $args['orderby'], 'Plan fallback ordering changed.');
vms_test_same('DESC', $args['order'], 'Plan fallback ordering direction changed.');
vms_test_same('_vms_tec_event_id', $args['meta_query'][0]['key'], 'Plan fallback meta key changed.');
vms_test_same('77', $args['meta_query'][0]['value'], 'Plan fallback event value changed.');
vms_test_same(1, $GLOBALS['vms_rules_v2_reset_postdata_calls'], 'Plan fallback should reset post data exactly once.');

fwrite(STDOUT, "PASS: rules-v2 prepared repositories preserve HPOS/CPT refunds, product/variation/status filters, assignee counts, fallbacks, read-only behavior, WP_Query semantics, and the exact 23-row inventory.\n");
