<?php
/** Execute with wp eval-file in the explicitly supervised disposable ZIP install. */
if (getenv('BVM_DISPOSABLE_DB_GUARDED') !== '1'
    || DB_NAME !== 'bvm_wporg'
    || DB_HOST !== 'localhost:' . getenv('BVM_DISPOSABLE_DB_SOCKET')
    || strpos(ABSPATH, '/private/tmp/bvm-wporg-readiness-') !== 0) {
    throw new RuntimeException('Supervised disposable readiness database required');
}
global $wpdb;
wp_set_current_user(1);
bvmgr_staffing_require_transaction_schema();
$assert = static function ($ok, $label) { if (!$ok) throw new RuntimeException($label); };
$assert(bvmgr_staffing_migrate_lifecycle()['ok'], 'fresh explicit migration is idempotent');
$writes = array();
$trap = static function ($sql) use (&$writes) {
    if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP)\b/i', $sql)) $writes[] = $sql;
    return $sql;
};
add_filter('query', $trap, PHP_INT_MAX);
bvmgr_db_migrate_vendor_core_v7();
$assert(bvmgr_staffing_migrate_lifecycle()['ok'], 'repeated lifecycle migration');
remove_filter('query', $trap, PHP_INT_MAX);
$assert(!$writes, 'repeated migrations issue no DML or DDL');

// An existing old staffing population must not be upgraded by the base installer.
$prefix = $wpdb->prefix;
$legacy_prefix = 'bvm_wporg_legacy_';
$assignments = $legacy_prefix . BVMGR_DB_TABLE_EVENT_ROLE_ASSIGNMENTS_SUFFIX;
$audit = $legacy_prefix . BVMGR_DB_TABLE_STAFFING_AUDIT_LOG_SUFFIX;
$wpdb->query($wpdb->prepare('CREATE TABLE %i LIKE %i', $assignments, bvmgr_staffing_table_name('assignments')));
$wpdb->query($wpdb->prepare('CREATE TABLE %i LIKE %i', $audit, bvmgr_staffing_table_name('audit')));
$wpdb->query($wpdb->prepare('ALTER TABLE %i DROP COLUMN revision', $assignments));
$wpdb->query($wpdb->prepare('ALTER TABLE %i DROP INDEX lifecycle_operation, DROP INDEX lifecycle_assignment, DROP COLUMN assignment_id, DROP COLUMN operation_id', $audit));
$wpdb->insert($audit, array('action'=>'retained_legacy_canary','created_at'=>'2030-01-01 00:00:00'));
$old_version = static fn() => 'vendor_core_v2';
try {
    $wpdb->prefix = $legacy_prefix;
    add_filter('pre_option_vms_db_schema_version', $old_version);
    set_error_handler(static function ($severity, $message) { throw new RuntimeException($message); });
    try {
        bvmgr_db_migrate_vendor_core_v3();
        $assert($wpdb->last_error === '', 'base installer produced no database error');
    } finally {
        restore_error_handler();
    }
    $assert(!in_array('revision', $wpdb->get_col($wpdb->prepare('SHOW COLUMNS FROM %i', $assignments)), true), 'existing assignments require explicit migration');
    $assert(!in_array('operation_id', $wpdb->get_col($wpdb->prepare('SHOW COLUMNS FROM %i', $audit)), true), 'existing audit requires explicit migration');
    $assert($wpdb->get_var($wpdb->prepare('SELECT action FROM %i', $audit)) === 'retained_legacy_canary', 'legacy audit retained');
} finally {
    remove_filter('pre_option_vms_db_schema_version', $old_version);
    $wpdb->prefix = $prefix;
    update_option('vms_db_schema_version', 'vendor_core_v7');
    foreach ($wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($legacy_prefix) . '%')) as $table) {
        $wpdb->query($wpdb->prepare('DROP TABLE %i', $table));
    }
}
echo "Fresh schema, repeat idempotence, and existing-population preservation PASS\n";
