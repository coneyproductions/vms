<?php
/** Real disposable SQL regression: caught writer errors are failures too. */
define('WP_ADMIN', true);
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
require __DIR__ . '/runtime.php';
require_once dirname(__DIR__, 2) . '/includes/admin-ui/helpers.php';
require_once dirname(__DIR__, 2) . '/includes/admin/event-command-center.php';
require_once dirname(__DIR__, 2) . '/includes/admin/event-profitability-report.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
set_current_screen('vms_event_plan');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = $_POST = array();
wp_set_current_user(1);
// Stock request-local cache keeps unrelated transient bookkeeping out of SQL.
check(get_class($GLOBALS['wp_object_cache']) === 'WP_Object_Cache', 'stock disposable cache');
wp_using_ext_object_cache(true);
$f = fixture();
$wpdb->update(bvmgr_staffing_table_name('event_slots'), array('headcount_needed' => 2, 'pay_type' => 'flat', 'pay_rate' => 100), array('slot_id' => $f['slot']));
$editor = (new ReflectionClass('BVMGR_Admin_Event_Plans'))->newInstanceWithoutConstructor();
$context_method = new ReflectionMethod($editor, 'get_event_plan_staff_render_context');
$context_method->setAccessible(true);
$cards_method = new ReflectionMethod($editor, 'render_event_plan_staff_response_html');
$cards_method->setAccessible(true);
require __DIR__ . '/readonly-manifest.php';

$attempts = $read_transactions = array();
$guard = static function ($sql) use (&$attempts, &$read_transactions) {
    if (preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $sql)) {
        if (!preg_match('/\b(GET_LOCK|RELEASE_LOCK|FOR UPDATE|INTO OUTFILE)\b/i', $sql)) return $sql;
        if (preg_match('/^SELECT (GET_LOCK|RELEASE_LOCK)\(/', $sql) && in_array('bvmgr_staffing_get_financial_labor', array_column(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 'function'), true)) return $sql;
    }
    // Existing financial helper owns a genuine consistent read, never cache repair.
    if (in_array($sql, array('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ', 'START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY', 'ROLLBACK'), true)
        && in_array('bvmgr_staffing_get_financial_labor', array_column(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 'function'), true)) {
        $read_transactions[] = $sql; return $sql;
    }
    $attempts[] = array('sql' => $sql, 'trace' => wp_debug_backtrace_summary());
    throw new RuntimeException('Staffing read attempted a write or write transaction');
};
$surfaces = array(
    'shared_snapshot' => static fn() => bvmgr_staffing_resolve_event_snapshot($f['plan']),
    'full_ecc_render' => static fn() => bvmgr_event_command_center_render_page_content($f['plan']),
    'light_module_render' => static fn() => bvmgr_event_command_center_render_event_plan_module_hub_metabox(get_post($f['plan'])),
    'event_plan_role_cards' => static fn() => $cards_method->invoke($editor, $context_method->invoke($editor, $f['plan'], array())),
    'event_plan_summary_render' => static fn() => $editor->render_event_plan_details_meta_box(get_post($f['plan'])),
    'staff_portal_display' => static fn() => bvmgr_staff_portal_render_assigned_event_cards($f['staff'], bvmgr_staff_portal_get_assignment_rows($f['staff'])),
    'staffing_dashboard' => static fn() => bvmgr_staffing_build_dashboard_response(array('include_drafts' => true)),
    'lifecycle_controls' => static fn() => bvmgr_staffing_render_lifecycle_controls($f['plan']),
    'profitability_labor' => static fn() => bvmgr_event_profitability_get_labor_cost_cents($f['plan']),
    'event_plan_financial' => static fn() => bvmgr_financial_get_event_snapshot($f['plan']),
    'ecc_financial' => static fn() => bvmgr_event_command_center_get_financial_snapshot($f['plan']),
    'assigned_staff_map' => static fn() => bvmgr_staffing_get_event_assigned_staff_map($f['plan']),
);
$report = array('states' => array(), 'surfaces' => array_keys($surfaces));
foreach (array('current', 'missing', 'dirty', 'stale', 'slots_changed', 'assignment_changed', 'incomplete', 'malformed') as $state) {
    check(bvmgr_staffing_compute_rollup($f['plan'])['ok'], 'explicit fixture rebuild ' . $state);
    $table = bvmgr_staffing_table_name('rollups');
    if ($state === 'missing') $wpdb->delete($table, array('event_plan_id' => $f['plan']));
    if ($state === 'dirty') bvmgr_staffing_mark_rollup_dirty($f['plan'], 'readonly_fixture');
    if ($state === 'stale') $wpdb->update($table, array('headcount_filled_total' => 999, 'conflict_count' => 999, 'est_labor_cost_total' => 999), array('event_plan_id' => $f['plan']));
    if ($state === 'slots_changed') $wpdb->update(bvmgr_staffing_table_name('event_slots'), array('headcount_needed' => 3), array('slot_id' => $f['slot']));
    if ($state === 'assignment_changed') $wpdb->update(bvmgr_staffing_table_name('assignments'), array('status' => 'canceled'), array('assignment_id' => $f['id']));
    // Invalid timestamp is representable in schema as NULL; missing keys are a reader-input case below.
    if ($state === 'malformed') $wpdb->update($table, array('computed_at' => null), array('event_plan_id' => $f['plan']));
    $stored = bvmgr_staffing_get_rollup($f['plan']);
    $needed = $state === 'slots_changed' ? 3 : 2;
    $assigned = $state === 'assignment_changed' ? 0 : 1;
    $before = staffing_read_manifest();
    $attempts = array();
    $wpdb->query('SET SESSION TRANSACTION READ ONLY');
    add_filter('query', $guard, PHP_INT_MAX);
    try {
        $snapshot = bvmgr_staffing_resolve_event_snapshot($f['plan']);
        check($snapshot['planned_headcount'] === $needed && $snapshot['assigned_headcount'] === $assigned && $snapshot['conflict_count'] === 0, 'current truth ' . $state);
        $expected_state = in_array($state, array('slots_changed', 'assignment_changed'), true) ? 'stale' : ($state === 'current' || $state === 'incomplete' ? 'fresh' : $state);
        check($snapshot['rollup_storage_state'] === $expected_state, 'storage provenance ' . $state . ': ' . $snapshot['rollup_storage_state']);
        if ($state === 'current') {
            $unknown_hours = $stored; $unknown_hours['est_hours_total'] = null;
            check(bvmgr_staffing_resolve_event_snapshot($f['plan'], array('rollup' => $unknown_hours))['rollup_storage_state'] === 'stale', 'unknown stored hours are not a verified zero');
        }
        if ($state === 'incomplete') {
            unset($stored['headcount_filled_total']);
            $partial = bvmgr_staffing_resolve_event_snapshot($f['plan'], array('rollup' => $stored));
            check($partial['rollup_storage_state'] === 'incomplete' && $partial['assigned_headcount'] === 1, 'incomplete stored projection derives correctly');
        }
        foreach ($surfaces as $name => $read) {
            $level = ob_get_level(); ob_start();
            try { $value = $read(); $html = ob_get_contents(); }
            finally { while (ob_get_level() > $level) ob_end_clean(); }
            check(!$attempts, 'zero attempted writes including caught errors: ' . $state . '/' . $name . ' ' . wp_json_encode($attempts));
            check(staffing_read_manifest() === $before, 'all tables/schema/options/audit/rollup unchanged: ' . $state . '/' . $name);
            if ($name === 'staff_portal_display' && $assigned === 0) check($html === '' && bvmgr_staff_portal_get_assignment_rows($f['staff']) === array(), 'canceled assignment is absent from portal');
            elseif (str_contains($name, 'render') || in_array($name, array('event_plan_role_cards', 'staff_portal_display', 'lifecycle_controls'), true)) check(strlen($html) + (is_string($value) ? strlen($value) : 0) > 0, 'actual output ' . $state . '/' . $name);
        }
        $labor = bvmgr_staffing_get_financial_labor($f['plan']);
        check($labor['planned_cents'] === $needed * 10000 && $labor['proposed_cents'] === $assigned * 10000 && $labor['committed_cents'] === 0, 'financial values ' . $state);
        check($labor['basis']['planned'] === 'planned_slot_headcount' && $labor['basis']['committed'] === 'confirmed_assignment_estimate' && $labor['actual_cents'] === null, 'financial basis ' . $state);
        $report['states'][$state] = array('attempted_writes' => $attempts, 'tables' => count($before), 'manifest_unchanged' => staffing_read_manifest() === $before, 'provenance' => $snapshot['rollup_provenance']);
    } finally {
        remove_filter('query', $guard, PHP_INT_MAX);
        $wpdb->query('SET SESSION TRANSACTION READ WRITE');
    }
    $wpdb->update(bvmgr_staffing_table_name('event_slots'), array('headcount_needed' => 2), array('slot_id' => $f['slot']));
    $wpdb->update(bvmgr_staffing_table_name('assignments'), array('status' => 'proposed'), array('assignment_id' => $f['id']));
}
// Real lifecycle writes still invalidate atomically; every subsequent read stays read-only.
$mut = fixture();
$wpdb->update(bvmgr_staffing_table_name('event_slots'), array('headcount_needed' => 2, 'pay_type' => 'flat', 'pay_rate' => 100), array('slot_id' => $mut['slot']));
$staff_user = wp_insert_user(array('user_login' => 'readonly-staff-' . wp_generate_uuid4(), 'user_pass' => 'disposable', 'role' => 'subscriber'));
update_user_meta($staff_user, '_vms_staff_id', $mut['staff']);
foreach (array('confirmed', 'canceled', 'proposed', 'declined', 'proposed', 'canceled') as $index => $target) {
    wp_set_current_user($target === 'declined' ? $staff_user : 1);
    $audit_before = audits($mut['id']);
    $result = bvmgr_staffing_transition_assignment($mut['id'], $target, options($mut, array('context' => $target === 'declined' ? 'staff' : 'operator')));
    check($result['ok'] && audits($mut['id']) === $audit_before + 1, 'atomic lifecycle and audit ' . $target);
    check(!empty(bvmgr_staffing_get_rollup($mut['plan'])['dirty']), 'mutation explicitly invalidates rollup ' . $target);
    wp_set_current_user(1);
    $before = staffing_read_manifest(); $attempts = array();
    $wpdb->query('SET SESSION TRANSACTION READ ONLY'); add_filter('query', $guard, PHP_INT_MAX);
    try {
        $snapshot = bvmgr_staffing_resolve_event_snapshot($mut['plan']);
        $labor = bvmgr_staffing_get_financial_labor($mut['plan']);
        $active = in_array($target, array('proposed', 'confirmed'), true) ? 1 : 0;
        check($snapshot['assigned_headcount'] === $active && $snapshot['proposed_headcount'] === (int) ($target === 'proposed') && $snapshot['confirmed_headcount'] === (int) ($target === 'confirmed'), 'post-mutation read truth ' . $target);
        check($labor['planned_cents'] === 20000 && $labor['committed_cents'] === ($target === 'confirmed' ? 10000 : 0) && $labor['proposed_cents'] === ($target === 'proposed' ? 10000 : 0), 'post-mutation financial truth ' . $target);
        check(!$attempts && staffing_read_manifest() === $before, 'post-mutation reads preserve all storage ' . $target);
        $report['mutations'][$index] = array('target' => $target, 'read_writes' => 0, 'audit_atomic' => true, 'rollup_remains_dirty' => true);
    } finally { remove_filter('query', $guard, PHP_INT_MAX); $wpdb->query('SET SESSION TRANSACTION READ WRITE'); }
}
check(bvmgr_staffing_compute_rollup($mut['plan'])['ok'] && empty(bvmgr_staffing_get_rollup($mut['plan'])['dirty']), 'explicit final rebuild persists and clears dirty');
// Availability and cross-event overlap can change without a marker on this plan.
$conflict = fixture();
$peer = fixture();
$wpdb->update(bvmgr_staffing_table_name('assignments'), array('status' => 'confirmed'), array('assignment_id' => $conflict['id']));
check(bvmgr_staffing_compute_rollup($conflict['plan'])['ok'], 'persist conflict-free starting rollup');
$wpdb->update(bvmgr_staffing_table_name('assignments'), array('staff_id' => $conflict['staff'], 'status' => 'confirmed'), array('assignment_id' => $peer['id']));
update_post_meta($conflict['staff'], '_vms_availability_manual', array('2030-09-06' => 'unavailable'));
$before = staffing_read_manifest(); $attempts = array();
$wpdb->query('SET SESSION TRANSACTION READ ONLY'); add_filter('query', $guard, PHP_INT_MAX);
try {
    $snapshot = bvmgr_staffing_resolve_event_snapshot($conflict['plan']);
    check($snapshot['conflict_count'] === 2 && $snapshot['rollup']['unavailable_assigned_count'] === 1 && $snapshot['rollup']['conflict_count'] === 1, 'fresh overlap plus availability conflicts replace stale zero');
    check($snapshot['rollup_storage_state'] === 'stale', 'cross-event conflict reports stale storage');
    check(!$attempts && staffing_read_manifest() === $before, 'conflict derivation changes no storage');
} finally { remove_filter('query', $guard, PHP_INT_MAX); $wpdb->query('SET SESSION TRANSACTION READ WRITE'); }
$report['fresh_conflicts_verified'] = true;
// Restore deliberately invalid cross-event fixture state before later lifecycle suites.
$wpdb->update(bvmgr_staffing_table_name('assignments'), array('staff_id' => $peer['staff'], 'status' => 'proposed'), array('assignment_id' => $peer['id']));
delete_post_meta($conflict['staff'], '_vms_availability_manual');
check(bvmgr_staffing_compute_rollup($f['plan'])['ok'], 'restore final malformed fixture for subsequent suites');
$report['checks'] = $checks; $report['ok'] = true; $report['financial_read_transactions'] = count($read_transactions);
if (getenv('BVM_READONLY_REPORT')) file_put_contents(getenv('BVM_READONLY_REPORT'), wp_json_encode($report, JSON_PRETTY_PRINT));
echo 'PASS ' . $checks . ' write-trap, value, provenance and manifest assertions across 12 real read surfaces / 8 rollup states' . PHP_EOL;
