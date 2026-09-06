<?php
/** Real provider/lifecycle/consumer acceptance; allowlisted disposable runtime only. */
$root = getenv('BVM_STAFFING_TEST_ROOT');
$mode = getenv('BVM_FINANCIAL_PROVIDER_MODE');
if ($root !== '/private/tmp/bvm-authority-integration-20260906/runtime/source-wordpress'
    || !in_array($mode, array('core-first', 'addon-first', 'inactive', 'absent'), true)) {
    throw new RuntimeException('Explicit disposable financial provider fixture required');
}
ini_set('memory_limit', '512M');
define('WP_ADMIN', true);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = '127.0.0.1:8791';
require $root . '/wp-load.php';
if (DB_NAME !== 'bvm_integration_source' || DB_HOST !== 'localhost:/private/tmp/bvm-authority-integration-20260906/runtime/mysql.sock') {
    throw new RuntimeException('Wrong financial provider database');
}
wp_set_current_user(1);
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
set_current_screen('vms_event_plan');
$checks = 0;
function fp_same($expected, $actual, string $label): void {
    global $checks;
    $checks++;
    if ($expected !== $actual) throw new RuntimeException($label . ': ' . var_export(array($expected, $actual), true));
}
fp_same(true, bvmgr_staffing_migrate_lifecycle()['ok'], 'explicit disposable schema ready');
$providerActive = in_array($mode, array('core-first', 'addon-first'), true);
fp_same($providerActive, in_array('vms-data-tools/vms-data-tools.php', get_option('active_plugins'), true), 'active provider state');
fp_same($mode !== 'absent', is_dir(WP_PLUGIN_DIR . '/vms-data-tools'), 'physical provider state');
$loaded = array_filter(get_included_files(), static fn($file) => str_contains($file, '/vms-data-tools/'));
fp_same($providerActive, !empty($loaded), 'real provider source load state');

$title = 'Financial provider ' . $mode . ' ' . wp_generate_uuid4();
$plan = wp_insert_post(array('post_type' => 'vms_event_plan', 'post_status' => 'publish', 'post_title' => $title));
$staff = wp_insert_post(array('post_type' => 'vms_staff', 'post_status' => 'publish', 'post_title' => $title . ' staff'));
$term = wp_insert_term($title, 'vms_staff_role');
fp_same(false, is_wp_error($term), 'synthetic role created');
$role = (int) $term['term_id'];
wp_set_post_terms($staff, array($role), 'vms_staff_role');
update_term_meta($role, '_vms_staff_role_default_pay_type', 'flat');
update_term_meta($role, '_vms_staff_role_default_rate', '25.00');
foreach (array('_vms_event_date' => '2032-08-19', '_vms_start_time' => '12:00', '_vms_end_time' => '14:00', '_vms_event_plan_status' => 'confirmed',
    '_vms_event_direct_costs_cents' => 0, '_vms_event_processing_fees_cents' => 0, '_vms_concessions_actual_source' => 'manual', '_vms_concessions_actual_cents' => 0) as $key => $value) {
    update_post_meta($plan, $key, $value);
}
fp_same('2032-08-19', get_post_meta($plan, '_vms_event_date', true), 'event date persisted');
$now = bvmgr_staffing_now_mysql_utc();
$wpdb->insert(bvmgr_staffing_table_name('event_slots'), array('event_plan_id' => $plan, 'role_id' => $role,
    'status' => 'active', 'headcount_needed' => 2, 'pay_type' => 'inherit_role', 'shift_start_local' => '12:00',
    'shift_end_local' => '14:00', 'created_at' => $now, 'updated_at' => $now));
$slot = (int) $wpdb->insert_id;
$proposal = bvmgr_staffing_atomic(static function () use ($slot, $staff): array {
    bvmgr_staffing_matrix_proposals($slot, array($staff));
    bvmgr_staffing_sync_lifecycle_window($slot);
    return array('ok' => true);
});
fp_same(true, $proposal['ok'], 'real proposal created');
$assignment = (int) $wpdb->get_var($wpdb->prepare('SELECT assignment_id FROM %i WHERE slot_id=%d', bvmgr_staffing_table_name('assignments'), $slot));
$states = array();
$assertConsumers = static function (string $state, int $committed) use ($plan, $title, $mode, $providerActive, &$states): void {
    $snapshot = bvmgr_financial_get_event_snapshot($plan);
    $truth = bvmgr_reporting_get_ticket_truth($plan);
    fp_same(true, !empty($truth['available']) && !empty($truth['calculated']), $state . ' real ticket authority available');
    fp_same(0, $truth['revenue_cents'], $state . ' actual empty receipt source zero');
    fp_same(0, $snapshot['revenue']['tickets']['amount_cents'], $state . ' receipt zero retained');
    fp_same('TRANSACTIONAL_ACTUAL', $snapshot['revenue']['tickets']['basis'], $state . ' receipt basis');
    fp_same($providerActive, $snapshot['revenue']['tickets']['provider_id'] !== '', $state . ' active provider selected or core fallback');
    fp_same(5000, $snapshot['staffing']['planned']['amount_cents'], $state . ' two flat positions planned');
    fp_same('PLANNED_LABOR', $snapshot['staffing']['planned']['basis'], $state . ' planned basis');
    fp_same($committed, $snapshot['staffing']['committed']['amount_cents'], $state . ' current commitment');
    fp_same('COMMITTED_LABOR', $snapshot['staffing']['committed']['basis'], $state . ' committed basis');
    fp_same(null, $snapshot['staffing']['actual']['amount_cents'], $state . ' paid payroll absent');
    fp_same('UNAVAILABLE', $snapshot['staffing']['actual']['basis'], $state . ' payroll not invented');
    $ecc = bvmgr_event_command_center_get_financial_snapshot($plan);
    foreach (array('staffing', 'forecast', 'costs') as $key) fp_same($snapshot[$key], $ecc[$key], $state . ' ECC ' . $key . ' agreement');
    $data = bvmgr_event_profitability_get_rows('all', $title);
    fp_same(1, count($data['rows']), $state . ' profitability exact synthetic row');
    $row = $data['rows'][0];
    fp_same($plan, $row['event_plan_id'], $state . ' profitability identity');
    fp_same($snapshot['staffing'], $row['financial_snapshot']['staffing'], $state . ' profitability authority agreement');
    fp_same(5000, $row['labor_cents'], $state . ' profitability planned labor once');
    fp_same('MIXED_PLANNING_ESTIMATE', $row['contribution_basis'], $state . ' profitability basis');
    ob_start(); bvmgr_financial_render_summary($snapshot); $summary = ob_get_clean();
    // Compare the stable dl block, excluding calculation-clock text.
    preg_match('/<dl class="bvm-financial-authority">.*?<\/dl>/s', $summary, $match);
    fp_same(true, !empty($match[0]), $state . ' canonical render produced');
    ob_start(); bvmgr_goals_event_plan_metabox_html(get_post($plan)); $goals = ob_get_clean();
    fp_same(true, str_contains($goals, $match[0]), $state . ' actual Event Plan goals metabox agreement');
    $_GET['s'] = $title;
    ob_start(); bvmgr_event_profitability_render_admin_page(); $profit = ob_get_clean();
    unset($_GET['s']);
    fp_same(true, str_contains($profit, $match[0]), $state . ' actual profitability rendered agreement');
    fp_same(true, str_contains($profit, 'not actual event profit or final accounting'), $state . ' planning disclaimer');
    $states[$state] = array('snapshot' => $snapshot, 'ticket_truth' => $truth, 'profitability_row' => $row,
        'goals_html_sha256' => hash('sha256', $goals), 'profitability_html_sha256' => hash('sha256', $profit));
};
$assertConsumers('proposed', 0);
foreach (array('confirmed' => 2500, 'canceled' => 0) as $target => $amount) {
    $row = bvmgr_staffing_lifecycle_row($assignment);
    $change = bvmgr_staffing_transition_assignment($assignment, $target, array('context' => 'operator', 'event_plan_id' => $plan,
        'revision' => (int) $row['revision'], 'operation_id' => wp_generate_uuid4(), 'reason' => 'Disposable financial provider acceptance'));
    fp_same(true, $change['ok'], 'real ' . $target . ' transition');
    $assertConsumers($target, $amount);
}
echo json_encode(array('mode' => $mode, 'checks' => $checks, 'passed' => true, 'plan' => $plan, 'active_plugins' => get_option('active_plugins'), 'states' => $states), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
