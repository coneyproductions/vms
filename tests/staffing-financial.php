<?php
/** Cross-domain value/basis contracts; real SQL/locking validation is separate. */
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
function __($s, $domain = '') { return $s; }
function absint($v) { return abs((int) $v); }
function wp_cache_delete(...$args) {}
function add_filter(...$args) {}
function remove_filter(...$args) {}
function maybe_unserialize($v) { return $v; }
function get_post_meta($id, $key, $single = true) { return $GLOBALS['metadata'][$key] ?? ''; }
function get_term_meta($id, $key, $single = true) { return str_ends_with($key, 'pay_type') ? 'hourly' : '20.00'; }
function bvmgr_staffing_transaction_active() { return false; }
function bvmgr_staffing_lock_name() { return 'fixture_lock'; }
function bvmgr_staffing_table_name($kind) { return 'wp_' . $kind; }
function bvmgr_staffing_require_transaction_schema() {}
function bvmgr_staffing_lifecycle_window($row) { return array('duration_minutes' => 120); }
function bvmgr_reporting_get_ticket_truth($id) { return array('available' => true, 'calculated' => true, 'revenue_cents' => 10000, 'source' => 'fixture_transactions'); }
function bvmgr_goals_get_event_pnl($id, $args) { return array('gross_revenue_cents' => 20000, 'direct_costs_cents' => 1000, 'processing_fees_cents' => 200); }
class BVMGR_Staffing_Failure extends RuntimeException {}
class wpdb {
    public $posts = 'wp_posts';
    public $postmeta = 'wp_postmeta';
    public $termmeta = 'wp_termmeta';
    public $term_taxonomy = 'wp_term_taxonomy';
    public function flush() {}
    public function prepare($sql, ...$args) { return $sql; }
    public function query($sql) {
        if (!preg_match('/^(SET TRANSACTION|START TRANSACTION|ROLLBACK)/', $sql)) throw new RuntimeException('Unexpected SQL or write: ' . $sql);
        $GLOBALS['read_queries'][] = $sql;
        return true;
    }
    public function get_var($sql) {
        if (!empty($GLOBALS['database_failure'])) throw new BVMGR_Staffing_Failure('database_error');
        if (str_contains($sql, 'LEFT JOIN')) return 0;
        if (str_contains($sql, 'post_type')) return 'vms_event_plan';
        if (str_contains($sql, 'ENGINE')) return 'InnoDB';
        return 1;
    }
    public function get_results($sql, $format) {
        if (str_contains($sql, 'term_id')) return array(array('meta_key' => '_vms_staff_role_default_pay_type', 'meta_value' => 'hourly'), array('meta_key' => '_vms_staff_role_default_rate', 'meta_value' => '20.00'));
        if (str_contains($sql, 'meta_key')) return array();
        if (str_contains($sql, 'a.*')) return $GLOBALS['read_assignments'];
        return $GLOBALS['read_slots'];
    }
}
class BVMGR_Staffing_Transaction_DB extends wpdb { public function __construct($source) {} }
require dirname(__DIR__) . '/includes/core/staffing-financial.php';
require dirname(__DIR__) . '/includes/core/financial-snapshot.php';
$checks = 0;
function sf_same($expected, $actual, $message) {
    global $checks; $checks++;
    if ($expected !== $actual) throw new RuntimeException($message . ': ' . var_export(array($expected, $actual), true));
}
function sf_slot(string $status = 'proposed'): array {
    return array('slot_id' => 1, 'role_id' => 1, 'status' => 'active', 'headcount_needed' => 2,
        'pay_type' => 'inherit_role', 'pay_rate' => null, 'assignments' => array(array(
            'assignment_id' => 10, 'staff_id' => 50, 'status' => $status, 'revision' => 1)));
}
function sf_labor(array $slots): array {
    return bvmgr_staffing_build_financial_labor($slots, array(1 => array('default_pay_type' => 'hourly', 'default_rate' => 20)), array(1 => array('duration_minutes' => 120)));
}
function sf_snapshot(array $labor, array $extra = array()): array {
    return bvmgr_financial_build_snapshot(1, array_merge(array('staffing_labor' => $labor,
        'forecast' => array('gross_revenue_cents' => 20000, 'direct_costs_cents' => 1000, 'processing_fees_cents' => 200)), $extra));
}
function sf_value(array $value, $cents, string $basis, string $label): void {
    sf_same($cents, $value['amount_cents'], $label . ' amount');
    sf_same($basis, $value['basis'], $label . ' basis');
    sf_same(false, $value['finalized'], $label . ' never final');
}
// 1. Proposed staffing is tentative and the slot forecast includes unfilled work.
$proposed = sf_labor(array(sf_slot()));
$s = sf_snapshot($proposed);
sf_value($s['forecast']['labor'], 8000, 'PLANNED_LABOR', 'proposed forecast');
sf_value($s['staffing']['committed'], 0, 'COMMITTED_LABOR', 'proposed not committed');
sf_same(4000, $proposed['proposed_cents'], 'tentative assigned estimate');
// 2. Confirmed assignment means commitment estimate, never paid labor.
$confirmed = sf_labor(array(sf_slot('confirmed')));
$s = sf_snapshot($confirmed);
sf_value($s['staffing']['committed'], 4000, 'COMMITTED_LABOR', 'confirmed');
sf_value($s['staffing']['actual'], null, 'UNAVAILABLE', 'no payroll actual');
// 3–4. Terminal responses leave planned need intact and remove commitments.
foreach (array('declined', 'canceled') as $state) {
    $labor = sf_labor(array(sf_slot($state))); $s = sf_snapshot($labor);
    sf_value($s['staffing']['committed'], 0, 'COMMITTED_LABOR', $state);
    sf_value($s['staffing']['planned'], 8000, 'PLANNED_LABOR', $state . ' slot need');
    sf_same(0, $labor['proposed_cents'], $state . ' excludes tentative');
}
// 5. Current lifecycle evidence changes deterministically.
sf_same(false, $proposed['evidence']['fingerprint'] === $confirmed['evidence']['fingerprint'], 'state changes fingerprint');
sf_same($confirmed, sf_labor(array(sf_slot('confirmed'))), 'same inputs deterministic');
// 6. Successfully observed empty authority is known zero; unfilled need is planned.
$s = sf_snapshot(sf_labor(array()));
sf_value($s['staffing']['committed'], 0, 'COMMITTED_LABOR', 'empty commitments');
sf_value($s['staffing']['planned'], 0, 'PLANNED_LABOR', 'empty plan');
$unfilled = sf_slot(); $unfilled['assignments'] = array();
sf_value(sf_snapshot(sf_labor(array($unfilled)))['staffing']['planned'], 8000, 'PLANNED_LABOR', 'unfilled planned');
// 7. Failure is unavailable, never fabricated zero.
$s = sf_snapshot(bvmgr_staffing_financial_unavailable('database_error'));
sf_value($s['staffing']['planned'], null, 'UNAVAILABLE', 'failed planned');
sf_value($s['staffing']['committed'], null, 'UNAVAILABLE', 'failed commitment');
sf_same('database_error', $s['staffing']['committed']['reason'], 'failure provenance');
// 8–9, 11–12. Paid, zero, transaction plus committed, and refund net receipts.
foreach (array(12000, 0, 15000, -1000) as $receipts) {
    $s = sf_snapshot($confirmed, array('ticket' => array('available' => true, 'calculated' => true,
        'revenue_cents' => $receipts, 'source' => 'woo_net_refunds'), 'manual' => array('direct' => 500)));
    sf_value($s['revenue']['tickets'], $receipts, 'TRANSACTIONAL_ACTUAL', 'ticket receipts');
    sf_value($s['staffing']['committed'], 4000, 'COMMITTED_LABOR', 'separate labor commitment');
    sf_value($s['actual']['margin'], $receipts - 500, 'DERIVED_ACTUAL', 'contribution excludes unpaid labor');
    sf_same('woo_net_refunds', $s['revenue']['tickets']['source'], 'transaction provenance');
}
// 10. Forecast uses planned labor once; committed does not double count it.
$s = sf_snapshot($confirmed);
sf_value($s['forecast']['margin'], 10800, 'FORECAST', 'forecast less planned labor');
// 13. Manual amounts remain reported evidence, independent of staffing state.
$s = sf_snapshot($confirmed, array('manual' => array('direct' => 1000, 'processing' => 100, 'concessions' => 800)));
sf_value($s['actual']['known_costs'], 1100, 'MANUAL_ACTUAL', 'manual costs');
sf_value($s['revenue']['manual_concessions'], 800, 'MANUAL_ACTUAL', 'manual concessions');
sf_value($s['staffing']['committed'], 4000, 'COMMITTED_LABOR', 'manual plus staffing');
// 14. Stale provider does not stale the current staffing authority.
$s = sf_snapshot($confirmed, array('ticket' => array('available' => true, 'calculated' => true,
    'revenue_cents' => 12000, 'freshness' => array('state' => 'stale'))));
sf_value($s['revenue']['tickets'], null, 'UNAVAILABLE', 'stale receipt');
sf_value($s['staffing']['committed'], 4000, 'COMMITTED_LABOR', 'current staffing with stale ticket');
sf_same('current_normalized_rows', $s['staffing']['committed']['freshness'], 'independent freshness');
// 15. Both UI consumers call the same financial wrapper (source integration contract).
$root = dirname(__DIR__);
foreach (array('/includes/admin/event-command-center.php', '/includes/admin/goals-forecast.php') as $file) {
    sf_same(true, str_contains(file_get_contents($root . $file), 'bvmgr_financial_get_event_snapshot'), $file . ' shared financial truth');
}
// Real wrapper and read-adapter execution against a strict read-only query double.
$wpdb = new wpdb(); $GLOBALS['read_slots'] = array(sf_slot());
$GLOBALS['read_assignments'] = sf_slot()['assignments']; $GLOBALS['read_assignments'][0]['slot_id'] = 1;
$first = bvmgr_financial_get_event_snapshot(1);
sf_value($first['staffing']['committed'], 0, 'COMMITTED_LABOR', 'wrapper proposal');
$GLOBALS['read_assignments'][0]['status'] = 'confirmed'; $GLOBALS['read_assignments'][0]['revision']++;
$second = bvmgr_financial_get_event_snapshot(1);
sf_value($second['staffing']['committed'], 4000, 'COMMITTED_LABOR', 'same-request current confirmation');
sf_same(2, $second['staffing']['committed']['assignment_revisions'][10], 'current revision evidence');
$GLOBALS['database_failure'] = true;
sf_value(bvmgr_financial_get_event_snapshot(1)['staffing']['committed'], null, 'UNAVAILABLE', 'wrapper SQL failure');
$GLOBALS['database_failure'] = false;
sf_same(2, count(array_filter($GLOBALS['read_queries'], static fn($sql) => str_contains($sql, 'READ ONLY'))), 'read-only transaction required');
// Assignment-specific rates, invalid inputs, duplicates and historical authority.
$slot = sf_slot('confirmed'); $slot['assignments'][0]['pay_rate_override'] = '30.00';
sf_same(6000, sf_labor(array($slot))['committed_cents'], 'assignment override wins');
sf_same(8000, sf_labor(array($slot))['planned_cents'], 'override does not rewrite slot forecast');
$slot['assignments'][0]['pay_type_override'] = 'flat';
sf_same(3000, sf_labor(array($slot))['committed_cents'], 'flat assignment override');
$slot['assignments'][0]['pay_rate_override'] = '-1';
sf_same(null, sf_labor(array($slot))['committed_cents'], 'invalid assignment rate unavailable');
$slot = sf_slot('confirmed'); $slot['assignments'][] = $slot['assignments'][0];
sf_same('unavailable', sf_labor(array($slot))['availability'], 'active duplicates need deliberate review');
$slot = sf_slot('checked_in');
sf_same('unknown_assignment_status', sf_labor(array($slot))['evidence']['reason'], 'unknown historical state not inferred');
sf_same('unavailable', bvmgr_staffing_build_financial_labor(array(), array(), array(), true)['availability'], 'legacy cost cannot become normalized zero');
$slot = sf_slot('confirmed'); $slot['status'] = 'canceled';
sf_same(0, sf_labor(array($slot))['committed_cents'], 'inactive normalized slot excluded');
sf_same(null, bvmgr_staffing_financial_estimate(array('pay_type' => 'hourly', 'pay_rate' => 20), array(), array(), 1), 'missing window unavailable');
sf_same(0, bvmgr_staffing_financial_estimate(array('pay_type' => 'none'), array(), array(), 1), 'explicit unpaid known zero');
echo "PASS {$checks} staffing/financial value and provenance assertions\n";
