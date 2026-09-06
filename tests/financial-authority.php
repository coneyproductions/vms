<?php
/** Disposable in-memory integration test. No WordPress boot, network or database. */
declare(strict_types=1);
define('ABSPATH', __DIR__);
set_error_handler(static function ($severity, $message, $file, $line): bool { throw new ErrorException($message, 0, $severity, $file, $line); });
function __($s, $d = '') { return $s; }
function esc_html__($s, $d = '') { return htmlspecialchars($s, ENT_QUOTES); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_attr__($s, $d = '') { return esc_html($s); }
function esc_attr($s) { return esc_html($s); }
function esc_url($s) { return esc_html($s); }
function absint($v) { return abs((int) $v); }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $v)); }
function sanitize_text_field($v) { return strip_tags((string) $v); }
function wp_strip_all_tags($v) { return strip_tags((string) $v); }
function add_action(...$args) {}
function get_post_meta($id, $key, $single = true) { return $GLOBALS['fixtures'][$id]['meta'][$key] ?? ''; }
function update_post_meta(...$args) { throw new RuntimeException('Forbidden write'); }
function update_option(...$args) { throw new RuntimeException('Forbidden option write'); }
function wp_remote_get(...$args) { throw new RuntimeException('Forbidden network'); }
function bvmgr_goals_fmt_money($n) { return '$' . number_format($n / 100, 2); }
function bvmgr_goals_get_event_pnl($id, $args) {
    if (empty($args['include_overhead'])) { same('forecast', $args['headcount_mode'], 'Only forecast enters financial model service'); }
    return $GLOBALS['fixtures'][$id]['forecast'] ?? array();
}
function bvmgr_staffing_get_financial_labor($id) {
    $staffing = $GLOBALS['fixtures'][$id]['staffing'] ?? array();
    $available = ($staffing['rollup_state'] ?? '') === 'fresh';
    $amount = $available ? (int) round(($staffing['rollup']['est_labor_cost_total'] ?? 0) * 100) : null;
    return array('availability' => $available ? 'available' : 'unavailable', 'planned_cents' => $amount, 'committed_cents' => $amount);
}
function bvmgr_ticket_revenue_build_report($args) {
    $id = $args['event_plan_id'];
    if (!empty($GLOBALS['fixtures'][$id]['core_error'])) { throw new RuntimeException('Core unavailable'); }
    return $GLOBALS['fixtures'][$id]['core'] ?? array();
}
if (!in_array('--no-woo', $argv, true)) {
    class WooCommerce {}
    class BVMGR_Ticket_Revenue_Service {}
    function wc_get_orders() { return array(); }
}
class WP_Error { function get_error_code() { return 'fixture'; } function get_error_message() { return 'Provider unavailable'; } }
function is_wp_error($v) { return $v instanceof WP_Error; }
$GLOBALS['assertions'] = 0;
function same($expected, $actual, $message) {
    $GLOBALS['assertions']++;
    if ($expected !== $actual) { throw new RuntimeException($message . ': ' . var_export(array($expected, $actual), true)); }
}
require dirname(__DIR__) . '/includes/core/reporting-providers.php';
require dirname(__DIR__) . '/includes/core/financial-ticket-source.php';
require dirname(__DIR__) . '/includes/core/financial-snapshot.php';
function transaction(int $cents, int $paid = 1, int $free = 0): array {
    return array('available' => true, 'calculated' => true, 'source' => 'dt_reporting_model', 'source_label' => 'Data Tools <fixture>', 'paid_qty' => $paid, 'free_qty' => $free, 'total_qty' => $paid + $free, 'revenue_cents' => $cents);
}
bvmgr_reporting_register_provider(array('id' => 'vms-data-tools', 'label' => 'Data Tools', 'version' => '0.5.55', 'contract_version' => 1, 'capabilities' => array('event_ticket_sales'), 'callback' => static function ($id, $context) {
    same('event_command_center', $context['scope'], 'Accepted provider scope preserved');
    $fixture = $GLOBALS['fixtures'][$id] ?? array();
    if (!empty($fixture['throw'])) { throw new RuntimeException('Provider failure'); }
    if (!empty($fixture['wp_error'])) { return new WP_Error(); }
    return $fixture['provider'] ?? array('available' => false, 'calculated' => false);
}));
$forecast = array('gross_revenue_cents' => 40000, 'direct_costs_cents' => 5000, 'processing_fees_cents' => 200);
$staffing = array('rollup_state' => 'fresh', 'rollup' => array('est_labor_cost_total' => 20, 'computed_at' => '2026-09-06 12:00:00'));
$GLOBALS['fixtures'] = array(
    1 => array('provider' => transaction(0, 0)),
    2 => array('provider' => transaction(12000, 6, 3), 'forecast' => $forecast, 'staffing' => $staffing),
    3 => array('provider' => transaction(12000), 'meta' => array('_vms_event_direct_costs_cents' => 15000, '_vms_event_processing_fees_cents' => 500)),
    4 => array('provider' => transaction(12000), 'meta' => array('_vms_event_direct_costs_cents' => 2000, '_vms_event_processing_fees_cents' => 500)),
    5 => array('forecast' => $forecast, 'staffing' => $staffing),
    6 => array('provider' => transaction(12000), 'meta' => array('_vms_event_actuals_totals' => array('ticket_revenue_cents' => 99999, 'gross_revenue_cents' => 99999))),
    7 => array('provider' => transaction(12000), 'meta' => array('_vms_event_actuals_totals' => array('ticket_revenue_cents' => 500), '_vms_event_actuals_provider' => 'square', '_vms_event_actuals_pulled_at_utc' => '2020-01-01 00:00:00')),
    8 => array('provider' => array_merge(transaction(9000), array('freshness' => array('is_stale' => true)))),
    9 => array('throw' => true),
    10 => array('wp_error' => true),
    11 => array('core' => array('rows' => array(array('item_kind' => 'ticket', 'quantity' => 5, 'refunded_quantity' => 1, 'net_subtotal_cents' => 6400), array('item_kind' => 'ticket', 'quantity' => 3, 'net_subtotal_cents' => 0), array('item_kind' => 'addon', 'quantity' => 1, 'net_subtotal_cents' => 9000)))),
    12 => array('core' => array('rows' => array())),
    13 => array('meta' => array('_vms_ticket_stats_v1' => array('qty_sold' => 0, 'revenue_cents' => 0, 'computed_at_gmt' => time()))),
    14 => array('meta' => array('_vms_ticket_stats_v1' => array('qty_sold' => 4, 'revenue_cents' => 1000, 'computed_at_gmt' => 1000))),
    15 => array('provider' => transaction(0, 0), 'meta' => array('_vms_event_direct_costs_cents' => 0, '_vms_event_processing_fees_cents' => 0)),
    16 => array('provider' => transaction(10000), 'meta' => array('_vms_event_direct_costs_cents' => 1200)),
    17 => array('provider' => transaction(10000), 'staffing' => $staffing),
    18 => array('provider' => transaction(10000), 'meta' => array('_vms_event_processing_fees_cents' => 200)),
    19 => array('provider' => transaction(0, 0, 7)),
    20 => array('meta' => array('_vms_concessions_actual_cents' => 3000, '_vms_concessions_actual_source' => 'manual')),
    21 => array('core_error' => true),
    23 => array('provider' => transaction(10000), 'staffing' => $staffing, 'meta' => array('_vms_event_direct_costs_cents' => 1000, '_vms_event_processing_fees_cents' => 200, '_vms_concessions_actual_cents' => 4000, '_vms_concessions_actual_source' => 'manual')),
    24 => array('provider' => transaction(5000), 'staffing' => array('rollup_state' => 'fresh', 'rollup' => array('est_labor_cost_total' => 0)), 'meta' => array('_vms_event_direct_costs_cents' => 10000, '_vms_event_processing_fees_cents' => 0, '_vms_concessions_actual_cents' => 0, '_vms_concessions_actual_source' => 'manual')),
    22 => array('provider' => transaction(4000), 'forecast' => $forecast, 'staffing' => array('rollup_state' => 'dirty', 'rollup' => array('est_labor_cost_total' => 0))),
);
$s = array();
foreach ($GLOBALS['fixtures'] as $id => $fixture) { $s[$id] = bvmgr_financial_get_event_snapshot($id); }
same(0, $s[1]['revenue']['tickets']['amount_cents'], 'Valid zero preserved');
same('TRANSACTIONAL_ACTUAL', $s[1]['revenue']['tickets']['basis'], 'Valid zero basis');
same(null, $s[1]['actual']['margin']['amount_cents'], 'Missing costs do not manufacture margin');
same(12000, $s[2]['revenue']['tickets']['amount_cents'], 'Paid and comp do not inflate receipts');
same(6, $s[2]['ticket_qty'], 'Paid quantity excludes comp');
same(40000, $s[2]['forecast']['gross']['amount_cents'], 'Separate forecast gross');
same('FORECAST', $s[2]['forecast']['margin']['basis'], 'Forecast margin typed');
same(32800, $s[2]['forecast']['margin']['amount_cents'], 'Forecast deducts scheduled labor exactly once');
same(-3500, $s[3]['actual']['margin']['amount_cents'], 'Negative contribution preserved');
same(9500, $s[4]['actual']['margin']['amount_cents'], 'Profitable contribution');
same('DERIVED_ACTUAL', $s[4]['actual']['margin']['basis'], 'Mixed actual contribution explicitly derived');
same(null, $s[5]['revenue']['tickets']['amount_cents'], 'Forecast does not substitute for actual');
same(12000, $s[6]['revenue']['tickets']['amount_cents'], 'Legacy manual total cannot override');
same(99999, $s[6]['recorded_observations']['gross_revenue_cents']['observed_amount_cents'], 'Legacy observation remains inspectable');
same('UNAVAILABLE', $s[6]['recorded_observations']['gross_revenue_cents']['basis'], 'Legacy provenance not relabeled actual');
same('square', $s[7]['recorded_observations']['ticket_revenue_cents']['provider_id'], 'Imported totals keep source');
foreach (array(8, 9, 10, 14, 21) as $id) { same(null, $s[$id]['revenue']['tickets']['amount_cents'], 'Stale/error/unavailable remains unavailable ' . $id); }
same(class_exists('WooCommerce') ? 6400 : null, $s[11]['revenue']['tickets']['amount_cents'], 'Woo refund-adjusted fallback excludes add-ons');
same(class_exists('WooCommerce') ? 0 : null, $s[12]['revenue']['tickets']['amount_cents'], 'Core absence differs from valid empty report');
same(0, $s[13]['revenue']['tickets']['amount_cents'], 'Fresh valid-zero cache preserved');
same(0, $s[15]['actual']['margin']['amount_cents'], 'Explicit zero costs remain zero');
same(8800, $s[16]['actual']['margin']['amount_cents'], 'Vendor cost only gives partial contribution');
same(null, $s[17]['actual']['margin']['amount_cents'], 'Labor estimate alone is not an actual cost');
same(9800, $s[18]['actual']['margin']['amount_cents'], 'Processing-only partial contribution');
same(0, $s[19]['revenue']['tickets']['amount_cents'], 'Free tickets do not imply revenue');
same('MANUAL_ACTUAL', $s[20]['revenue']['manual_concessions']['basis'], 'Explicit manual source remains separate');
same(null, $s[22]['forecast']['margin']['amount_cents'], 'Dirty labor estimate cannot become fresh zero');
foreach ($s as $snapshot) {
    same($snapshot['staffing']['planned'], $snapshot['forecast']['labor'], 'Forecast uses exactly the planned staffing observation');
    same('UNAVAILABLE', $snapshot['staffing']['actual']['basis'], 'Neither planned nor committed staffing proves paid labor');
    same(null, $snapshot['actual']['gross']['amount_cents'], 'Ticket channels cannot certify whole event gross');
    same('UNAVAILABLE', $snapshot['final']['basis'], 'No accounting final source');
    same(false, $snapshot['final']['finalized'], 'Never finalize');
}
$unavailable = bvmgr_financial_build_snapshot(100, array('staffing_labor' => array('availability' => 'unavailable', 'planned_cents' => 1000, 'committed_cents' => 900)));
same(null, $unavailable['staffing']['planned']['amount_cents'], 'Unavailable authority rejects stale numeric planned field');
same(null, $unavailable['staffing']['committed']['amount_cents'], 'Unavailable authority rejects stale numeric committed field');
$before = serialize($GLOBALS['fixtures']);
ob_start(); bvmgr_financial_render_summary($s[2]); $html = ob_get_clean();
same(true, strpos($html, 'Transactional ticket receipts') !== false && strpos($html, 'Forecast gross revenue') !== false, 'Shared renderer shows actual and forecast separately');
same(false, strpos($html, '<fixture>') !== false, 'Provenance escaped');
same(true, strpos($html, 'Unavailable') !== false, 'Missing values render unavailable');
same($before, serialize($GLOBALS['fixtures']), 'Inputs never mutated');
// Rendered report integration, using actual row collector and nullable aggregate logic.
class WP_Query { public $posts = array(2, 3, 23, 24); function __construct($args) {} }
function get_post_status($id) { return 'publish'; }
function get_the_title($id) { return 'Event ' . $id; }
function get_edit_post_link($id, $context = '') { return '/event/' . $id; }
function wp_date($format, $timestamp = null) { return gmdate($format, $timestamp ?? time()); }
function get_option($name) { return $name === 'date_format' ? 'Y-m-d' : ''; }
function bvmgr_event_profitability_get_event_timestamp($id) { return time() - 100; }
require dirname(__DIR__) . '/includes/admin/event-profitability-report.php';
$report = bvmgr_event_profitability_get_rows();
same(39000, $report['summary']['ticket_revenue_cents'], 'Report sums shared transaction receipts');
same(4400, $report['summary']['total_contribution_cents'], 'Known positive and negative estimates aggregate without fake zero rows');
same(2, $report['summary']['known']['total_contribution_cents'], 'Report counts known estimates');
same(2, $report['summary']['unavailable']['total_contribution_cents'], 'Report counts omitted values');
same('Past event — provisional', bvmgr_event_profitability_stage_label(2, time() - 100, 'complete'), 'Past date never means final accounting');
foreach ($report['rows'] as $row) { same($s[$row['event_plan_id']], $row['financial_snapshot'], 'Reporting reuses shared contract'); }
// Compatibility wrappers are executable, not merely source string assertions.
$ecc = file_get_contents(dirname(__DIR__) . '/includes/admin/event-command-center.php');
preg_match('/function bvmgr_event_command_center_get_financial_snapshot\(int \$plan_id\): array\s*\{[^}]+\}/', $ecc, $match);
eval($match[0]);
same($s[2], bvmgr_event_command_center_get_financial_snapshot(2), 'ECC and reporting resolve identical financial meaning');
function current_user_can($cap) { return true; }
function admin_url($path = '') { return '/admin/' . $path; }
function add_query_arg($args, $url) { return $url; }
ob_start(); bvmgr_event_profitability_render_admin_page(); $report_html = ob_get_clean();
same(true, strpos($report_html, 'Transactional ticket receipts') !== false && strpos($report_html, 'unavailable events excluded') !== false, 'Report renders scoped receipts and omitted-row disclosure');
same(false, strpos($report_html, 'ticket_revenue_cents:') !== false, 'Report displays human-readable labels');
class WP_Post { public $ID = 2; }
function bvmgr_pos_provider_detect() { return array(); }
function bvmgr_goals_get_manual_event_actual_totals($id) { return array(); }
function bvmgr_goals_event_plan_refresh_url($id) { return '/refresh'; }
function bvmgr_goals_get_active_goal() { return array(); }
function bvmgr_goals_break_even_headcount($id, $args) { return array(); }
function bvmgr_goals_query_value($key) { return ''; }
function bvmgr_goals_admin_url($args) { return '/goals'; }
function wp_nonce_field(...$args) {}
function selected($a, $b, $echo) { return $a === $b ? 'selected' : ''; }
function wp_json_encode($v, $flags = 0) { return json_encode($v, $flags); }
$source = file_get_contents(dirname(__DIR__) . '/includes/admin/goals-forecast.php');
$start = strpos($source, 'function bvmgr_goals_event_plan_metabox_html(');
$brace = strpos($source, '{', $start); $depth = 1;
for ($end = $brace + 1; $depth; $end++) { $depth += $source[$end] === '{' ? 1 : ($source[$end] === '}' ? -1 : 0); }
eval(substr($source, $start, $end - $start));
ob_start(); bvmgr_goals_event_plan_metabox_html(new WP_Post()); $plan_html = ob_get_clean();
foreach (array('Transactional ticket receipts', 'Forecast gross revenue', 'Final accounting', 'Unavailable', 'not independently verified') as $label) {
    same(true, strpos($plan_html, $label) !== false && strpos($report_html, $label) !== false, 'Event Plan/report vocabulary agrees: ' . $label);
}
same(true, strpos($plan_html, '$120.00') !== false && strpos($plan_html, '$400.00') !== false, 'Event Plan renders actual receipts alongside forecast');
same(false, strpos($plan_html, '<strong>True Profit</strong>') !== false, 'Legacy model does not claim true profit');
echo 'Financial authority PASS: ' . $GLOBALS['assertions'] . ' assertions' . (class_exists('WooCommerce') ? ' (Woo available)' : ' (Woo absent)') . "\n";
