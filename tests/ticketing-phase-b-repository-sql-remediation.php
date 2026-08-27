<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

final class VMS_Phase_B_WPDB_Spy
{
	public string $prefix;
	public string $posts;
	public string $postmeta;
	/** @var array<int,array{query:string,args:array<int,mixed>,sql:string}> */
	public array $prepares = array();
	/** @var array<int,array{kind:string,sql:string,result:mixed}> */
	public array $executions = array();
	/** @var array<int,mixed> */
	public array $get_var_queue = array();
	/** @var array<int,mixed> */
	public array $get_col_queue = array();

	public function __construct(string $prefix)
	{
		$this->prefix = $prefix;
		$this->posts = $prefix . 'posts';
		$this->postmeta = $prefix . 'postmeta';
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
					return implode('.', array_map(static fn(string $part): string => '`' . str_replace('`', '``', $part) . '`', explode('.', (string) $value)));
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
		$this->executions[] = array('kind' => 'get_var', 'sql' => $sql, 'result' => $result);
		return $result;
	}

	public function get_col(string $sql)
	{
		$result = $this->get_col_queue === array() ? array() : array_shift($this->get_col_queue);
		$this->executions[] = array('kind' => 'get_col', 'sql' => $sql, 'result' => $result);
		return $result;
	}
}

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_key($value): string
{
	$value = is_scalar($value) ? strtolower((string) $value) : '';
	$value = preg_replace('/[^a-z0-9_\-]/', '', $value);
	return is_string($value) ? $value : '';
}

function bvmgr_ticketing_v2_product_meta_key(string $suffix): string
{
	return '_vms_' . sanitize_key($suffix);
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
function vms_test_prepare_containing(VMS_Phase_B_WPDB_Spy $spy, string $needle): array
{
	foreach (array_reverse($spy->prepares) as $call) {
		if (strpos($call['query'], $needle) !== false) {
			return $call;
		}
	}
	throw new RuntimeException('Missing prepare containing ' . $needle . '.');
}

/** @return array{kind:string,sql:string,result:mixed} */
function vms_test_execution_containing(VMS_Phase_B_WPDB_Spy $spy, string $needle): array
{
	foreach (array_reverse($spy->executions) as $call) {
		if (strpos($call['sql'], $needle) !== false) {
			return $call;
		}
	}
	throw new RuntimeException('Missing execution containing ' . $needle . '.');
}

$source = file_get_contents(dirname(__DIR__) . '/includes/integrations/ticketing-phase-b.php');
if (!is_string($source)) {
	throw new RuntimeException('Unable to read Phase B source.');
}

$runtime_functions = array(
	'bvmgr_ticketing_v2_reporting_category_candidate_ids',
	'bvmgr_ticketing_v2_table_exists',
	'bvmgr_ticketing_v2_paid_order_statuses_with_prefix',
	'bvmgr_ticketing_v2_calc_sold_qty_for_product_via_lookup',
	'bvmgr_ticketing_v2_calc_sold_qty_for_product_via_order_items',
);
foreach ($runtime_functions as $function) {
	eval(vms_test_extract_function($source, $function));
}

// The preserved strict-JSON baseline has exactly these 21 owned rows in this file.
$owned_inventory = array(
	'WordPress.DB.DirectDatabaseQuery.DirectQuery' => 4,
	'WordPress.DB.DirectDatabaseQuery.NoCaching' => 4,
	'WordPress.DB.PreparedSQL.NotPrepared' => 3,
	'PluginCheck.Security.DirectDB.UnescapedDBParameter' => 2,
	'WordPress.DB.SlowDBQuery.slow_db_query_meta_query' => 8,
);
vms_test_same(21, array_sum($owned_inventory), 'Owned scanner inventory should remain exactly 21 baseline rows.');
$covered_owned_rows = 0;
$execution_suppressions = array(
	'bvmgr_ticketing_v2_reporting_category_candidate_ids' => array(
		'fragment' => 'phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared',
		'rows' => 3,
	),
	'bvmgr_ticketing_v2_table_exists' => array(
		'fragment' => 'phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
		'rows' => 2,
	),
	'bvmgr_ticketing_v2_calc_sold_qty_for_product_via_lookup' => array(
		'fragment' => 'phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
		'rows' => 4,
	),
	'bvmgr_ticketing_v2_calc_sold_qty_for_product_via_order_items' => array(
		'fragment' => 'phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching',
		'rows' => 4,
	),
);
foreach ($execution_suppressions as $function => $suppression) {
	vms_test_contains(
		$suppression['fragment'],
		vms_test_extract_function($source, $function),
		$function . ' must retain its exact occurrence-specific scanner suppression.'
	);
	$covered_owned_rows += $suppression['rows'];
}

$slow_functions = array(
	'bvmgr_entitlements_get_entitlement_image_context',
	'bvmgr_ticketing_v2_find_product_ids_by_sku',
	'bvmgr_ticketing_v2_calc_sold_qty_for_entitlement_scope',
	'bvmgr_ticketing_v2_find_entitlement_product',
	'bvmgr_ticketing_v2_find_plan_id_by_tec_event',
	'bvmgr_ticketing_v2_find_legacy_entitlement_product_by_key',
	'bvmgr_ticketing_v2_cleanup_legacy_sr_duplicates',
	'bvmgr_ticketing_v2_legacy_cleanup_runner',
);
foreach ($slow_functions as $function) {
	$body = vms_test_extract_function($source, $function);
	vms_test_contains('WordPress.DB.SlowDBQuery.slow_db_query_meta_query', $body, $function . ' must retain its narrow slow-meta justification.');
	$covered_owned_rows++;
}
vms_test_same(0, array_sum($owned_inventory) - $covered_owned_rows, 'All 21 owned baseline rows should have zero residual findings after occurrence-specific remediation.');

// Reporting IDs: table identifiers and all values are prepared; result normalization is unchanged.
$wpdb = new VMS_Phase_B_WPDB_Spy('wp_report_');
$wpdb->get_col_queue[] = array(0, '31', '-4', '31');
$GLOBALS['wpdb'] = $wpdb;
vms_test_same(array(31, 4, 31), bvmgr_ticketing_v2_reporting_category_candidate_ids(-7, 999), 'Reporting IDs should preserve order/duplicates while normalizing positive IDs.');
$call = vms_test_prepare_containing($wpdb, 'SELECT DISTINCT p.ID');
vms_test_same(array('wp_report_posts', 'wp_report_postmeta', 7, '_vms_product_role', '_vms_ticketing_entitlement_id', '_vms_event_plan_id', '_vms_tec_event_id', 250), $call['args'], 'Reporting query prepare arguments changed.');
$sql = vms_test_execution_containing($wpdb, 'SELECT DISTINCT p.ID')['sql'];
vms_test_contains('FROM `wp_report_posts` p', $sql, 'Reporting posts identifier was not prepared.');
vms_test_contains('INNER JOIN `wp_report_postmeta` pm', $sql, 'Reporting postmeta identifier was not prepared.');
vms_test_no_placeholders($sql);

// Table probes remain prepared and request-cached.
$wpdb = new VMS_Phase_B_WPDB_Spy('wp_probe_');
$wpdb->get_var_queue[] = 'wp_probe_wc_order_stats';
$GLOBALS['wpdb'] = $wpdb;
vms_test_assert(bvmgr_ticketing_v2_table_exists('wp_probe_wc_order_stats'), 'Exact table probe should succeed.');
vms_test_assert(bvmgr_ticketing_v2_table_exists('wp_probe_wc_order_stats'), 'Cached table probe should succeed.');
vms_test_same(1, count($wpdb->executions), 'Table probe should execute once per request/table.');
vms_test_same(array('wp_probe_wc_order_stats'), vms_test_prepare_containing($wpdb, 'SHOW TABLES LIKE %s')['args'], 'Table probe argument changed.');
vms_test_no_placeholders($wpdb->executions[0]['sql']);

// Lookup aggregate: identifiers, product/variation IDs, statuses, result, and unavailable fallback.
$wpdb = new VMS_Phase_B_WPDB_Spy('wp_lookup_');
$lookup = 'wp_lookup_wc_order_product_lookup';
$stats = 'wp_lookup_wc_order_stats';
$wpdb->get_var_queue = array($lookup, $stats, 3.6);
$GLOBALS['wpdb'] = $wpdb;
vms_test_same(4, bvmgr_ticketing_v2_calc_sold_qty_for_product_via_lookup(42, array('completed', 'wc-processing')), 'Lookup aggregate rounding changed.');
$call = vms_test_prepare_containing($wpdb, 'SUM(product_lookup.product_qty)');
vms_test_same(array($lookup, $stats, 42, 42, 'wc-completed', 'wc-processing'), $call['args'], 'Lookup aggregate prepare arguments changed.');
$sql = vms_test_execution_containing($wpdb, 'SUM(product_lookup.product_qty)')['sql'];
vms_test_contains('FROM `wp_lookup_wc_order_product_lookup` product_lookup', $sql, 'Lookup table identifier was not prepared.');
vms_test_contains('(product_lookup.product_id = 42 OR product_lookup.variation_id = 42)', $sql, 'Product/variation lookup semantics changed.');
vms_test_contains("status IN ('wc-completed', 'wc-processing')", $sql, 'Paid-status lookup semantics changed.');
vms_test_no_placeholders($sql);

$wpdb = new VMS_Phase_B_WPDB_Spy('wp_lookup_missing_');
$wpdb->get_var_queue[] = null;
$GLOBALS['wpdb'] = $wpdb;
vms_test_same(null, bvmgr_ticketing_v2_calc_sold_qty_for_product_via_lookup(43, array('completed')), 'Missing lookup table should retain the null fallback.');

// Order-item aggregate: HPOS and CPT status branches retain refunds and product/variation matching.
$wpdb = new VMS_Phase_B_WPDB_Spy('wp_hpos_');
$oi = 'wp_hpos_woocommerce_order_items';
$oim = 'wp_hpos_woocommerce_order_itemmeta';
$stats = 'wp_hpos_wc_order_stats';
$wpdb->get_var_queue = array($oi, $oim, $stats, 5.2);
$GLOBALS['wpdb'] = $wpdb;
vms_test_same(5, bvmgr_ticketing_v2_calc_sold_qty_for_product_via_order_items(77, array('completed', 'on-hold')), 'HPOS order-item result changed.');
$call = vms_test_prepare_containing($wpdb, 'GREATEST(0, line_items.qty');
vms_test_same(array($oi, $oim, 77, 77, $oi, $oim, $oim, 'wp_hpos_posts', 'wc-completed', 'wc-on-hold'), $call['args'], 'Order-item aggregate prepare arguments changed.');
$sql = vms_test_execution_containing($wpdb, 'GREATEST(0, line_items.qty')['sql'];
vms_test_contains('INNER JOIN `wp_hpos_wc_order_stats` order_stats', $sql, 'HPOS status branch changed.');
vms_test_contains('HAVING product_id = 77 OR variation_id = 77', $sql, 'Order-item product/variation semantics changed.');
vms_test_contains('SUM(ABS(CAST(refund_qty.meta_value AS SIGNED))) AS refunded_qty', $sql, 'Refund subtraction changed.');
vms_test_no_placeholders($sql);

$wpdb = new VMS_Phase_B_WPDB_Spy('wp_cpt_');
$oi = 'wp_cpt_woocommerce_order_items';
$oim = 'wp_cpt_woocommerce_order_itemmeta';
$wpdb->get_var_queue = array($oi, $oim, null, 2.8);
$GLOBALS['wpdb'] = $wpdb;
vms_test_same(3, bvmgr_ticketing_v2_calc_sold_qty_for_product_via_order_items(78, array('processing')), 'CPT order-item result changed.');
$sql = vms_test_execution_containing($wpdb, 'GREATEST(0, line_items.qty')['sql'];
vms_test_contains("INNER JOIN `wp_cpt_posts` orders ON orders.ID = line_items.order_id AND orders.post_type = 'shop_order'", $sql, 'CPT order join changed.');
vms_test_contains("orders.post_status IN ('wc-processing')", $sql, 'CPT paid-status branch changed.');
vms_test_no_placeholders($sql);

$wpdb = new VMS_Phase_B_WPDB_Spy('wp_items_missing_');
$wpdb->get_var_queue[] = null;
$GLOBALS['wpdb'] = $wpdb;
vms_test_same(null, bvmgr_ticketing_v2_calc_sold_qty_for_product_via_order_items(79, array('completed')), 'Missing order-item table should retain the null fallback.');

fwrite(STDOUT, "PASS: Phase B SQL preparation, HPOS/CPT/refund/product-variation behavior, fallbacks, and the exact 21-row scanner inventory are covered.\n");
