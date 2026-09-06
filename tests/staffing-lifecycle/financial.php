<?php
/** Real SQL staffing/financial integration in the fixed disposable fixture. */
require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/core/staffing-financial.php';
require_once dirname(__DIR__, 2) . '/includes/core/financial-snapshot.php';
$checks = 0;
$metadata_filters_before = $GLOBALS['wp_filter']['get_post_metadata']->callbacks ?? array();
function sf_db_same($expected, $actual, string $label): void {
    global $checks; $checks++;
    if ($expected !== $actual) throw new RuntimeException($label . ': ' . var_export(array($expected, $actual), true));
}
function sf_db_labor(int $plan): array {
    $no_writes = static function ($sql) {
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i', $sql)) throw new RuntimeException('Labor read attempted mutation');
        return $sql;
    };
    add_filter('query', $no_writes, PHP_INT_MAX);
    try { return bvmgr_staffing_get_financial_labor($plan); }
    finally { remove_filter('query', $no_writes, PHP_INT_MAX); }
}
sf_db_same('ready', bvmgr_staffing_lifecycle_preflight()['schema'], 'fixture migrated');
$plan = wp_insert_post(array('post_type' => 'vms_event_plan', 'post_status' => 'publish', 'post_title' => 'Integration labor ' . wp_generate_uuid4()));
$staff = wp_insert_post(array('post_type' => 'vms_staff', 'post_status' => 'publish', 'post_title' => 'Integration labor staff'));
$term = wp_insert_term('Financial labor ' . wp_generate_uuid4(), 'vms_staff_role');
$role = (int) $term['term_id'];
wp_set_post_terms($staff, array($role), 'vms_staff_role');
update_term_meta($role, '_vms_staff_role_default_pay_type', 'hourly');
update_term_meta($role, '_vms_staff_role_default_rate', '20.00');
update_post_meta($plan, '_vms_event_date', '2030-10-12');
update_post_meta($plan, '_vms_start_time', '12:00');
update_post_meta($plan, '_vms_end_time', '14:00');
update_post_meta($plan, '_vms_event_plan_status', 'confirmed');
$empty = sf_db_labor($plan);
sf_db_same('available', $empty['availability'], 'known empty authority available');
sf_db_same(0, $empty['committed_cents'], 'known empty committed zero');
$now = bvmgr_staffing_now_mysql_utc();
$wpdb->insert(bvmgr_staffing_table_name('event_slots'), array('event_plan_id' => $plan, 'role_id' => $role,
    'status' => 'active', 'headcount_needed' => 2, 'pay_type' => 'inherit_role', 'shift_start_local' => '12:00',
    'shift_end_local' => '14:00', 'created_at' => $now, 'updated_at' => $now));
$slot = (int) $wpdb->insert_id;
$result = bvmgr_staffing_atomic(static function () use ($slot, $staff): array {
    bvmgr_staffing_matrix_proposals($slot, array($staff));
    bvmgr_staffing_sync_lifecycle_window($slot);
    return array('ok' => true);
});
sf_db_same(true, $result['ok'], 'real proposal created');
$id = (int) $wpdb->get_var($wpdb->prepare('SELECT assignment_id FROM %i WHERE slot_id=%d', bvmgr_staffing_table_name('assignments'), $slot));
$proposed = sf_db_labor($plan);
sf_db_same('available', $proposed['availability'], 'read-only snapshot works in MariaDB');
sf_db_same(8000, $proposed['planned_cents'], 'planned includes both positions');
sf_db_same(4000, $proposed['proposed_cents'], 'one tentative assignment');
sf_db_same(0, $proposed['committed_cents'], 'proposal not committed');
sf_db_same(null, $proposed['actual_cents'], 'actual unavailable');
$canonical = bvmgr_staffing_estimate_slot_cost(bvmgr_staffing_get_event_slots($plan)[0], bvmgr_staffing_role_meta_get($role), $plan);
sf_db_same((int) round($canonical['cost'] * 100), $proposed['planned_cents'], 'real canonical slot estimator agreement');
$change = static function (string $target) use ($id, $plan): array {
    $row = bvmgr_staffing_lifecycle_row($id);
    return bvmgr_staffing_transition_assignment($id, $target, array('context' => 'operator', 'event_plan_id' => $plan,
        'revision' => (int) $row['revision'], 'operation_id' => wp_generate_uuid4(), 'reason' => 'Disposable financial integration'));
};
sf_db_same(true, $change('confirmed')['ok'], 'confirm through canonical lifecycle');
$confirmed = sf_db_labor($plan);
sf_db_same(4000, $confirmed['committed_cents'], 'same-request committed updates');
sf_db_same(0, $confirmed['proposed_cents'], 'same-request tentative clears');
sf_db_same(false, $confirmed['evidence']['fingerprint'] === $proposed['evidence']['fingerprint'], 'current fingerprint');
sf_db_same($confirmed, sf_db_labor($plan), 'stable repeated coherent read');
$combined = bvmgr_financial_build_snapshot($plan, array('staffing_labor' => $confirmed,
    'ticket' => array('available' => true, 'calculated' => true, 'revenue_cents' => 12000, 'source' => 'disposable_receipts'),
    'forecast' => array('gross_revenue_cents' => 20000, 'direct_costs_cents' => 1000, 'processing_fees_cents' => 200)));
sf_db_same('COMMITTED_LABOR', $combined['staffing']['committed']['basis'], 'combined committed basis');
sf_db_same('TRANSACTIONAL_ACTUAL', $combined['revenue']['tickets']['basis'], 'combined receipt basis');
sf_db_same(10800, $combined['forecast']['margin']['amount_cents'], 'planned labor deducted once');
sf_db_same('UNAVAILABLE', $combined['staffing']['actual']['basis'], 'combined actual remains unavailable');
// Metadata is read from this SQL snapshot, not a cached normalized role getter.
get_term_meta($role, '_vms_staff_role_default_rate', true);
$wpdb->update($wpdb->termmeta, array('meta_value' => '30.00'), array('term_id' => $role, 'meta_key' => '_vms_staff_role_default_rate'));
sf_db_same(6000, sf_db_labor($plan)['committed_cents'], 'current role rate bypasses stale metadata cache');
$wpdb->update(bvmgr_staffing_table_name('assignments'), array('pay_rate_override' => 50), array('assignment_id' => $id));
sf_db_same(10000, sf_db_labor($plan)['committed_cents'], 'real assignment rate override');
sf_db_same(12000, sf_db_labor($plan)['planned_cents'], 'role forecast unaffected by personal override');
sf_db_same(true, $change('canceled')['ok'], 'cancel through canonical lifecycle');
sf_db_same(0, sf_db_labor($plan)['committed_cents'], 'canceled excluded');
sf_db_same(true, $change('proposed')['ok'], 'reproposal through canonical lifecycle');
$user = wp_insert_user(array('user_login' => 'labor-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber'));
update_user_meta($user, '_vms_staff_id', $staff);
wp_set_current_user($user);
$row = bvmgr_staffing_lifecycle_row($id);
$result = bvmgr_staffing_transition_assignment($id, 'declined', array('context' => 'staff', 'event_plan_id' => $plan,
    'revision' => (int) $row['revision'], 'operation_id' => wp_generate_uuid4()));
sf_db_same(true, $result['ok'], 'owning staff decline');
wp_set_current_user(1);
sf_db_same(0, sf_db_labor($plan)['proposed_cents'], 'declined excluded');
sf_db_same(12000, sf_db_labor($plan)['planned_cents'], 'decline preserves planned need');
// Real SQL read error: no fake empty fallback, no table damage.
$assignment_table = bvmgr_staffing_table_name('assignments');
$failure = static function ($sql) use ($assignment_table) {
    return str_starts_with($sql, 'SELECT a.*') && str_contains($sql, $assignment_table) ? 'INVALID SQL LABOR READ' : $sql;
};
add_filter('query', $failure);
try { $unavailable = sf_db_labor($plan); }
finally { remove_filter('query', $failure); }
sf_db_same('unavailable', $unavailable['availability'], 'SQL failure unavailable');
sf_db_same(null, $unavailable['committed_cents'], 'SQL failure not zero');
sf_db_same('available', sf_db_labor($plan)['availability'], 'next independent read recovers');
sf_db_same(null, $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)', bvmgr_staffing_lock_name())), 'read lock released');
sf_db_same(true, $metadata_filters_before === ($GLOBALS['wp_filter']['get_post_metadata']->callbacks ?? array()), 'temporary metadata projection removed');
echo "PASS {$checks} real staffing/financial SQL assertions; synthetic plan {$plan}\n";
