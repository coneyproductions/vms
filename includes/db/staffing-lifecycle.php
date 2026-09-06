<?php
/** Explicit additive migration. No boot/activation hook runs this migration. */
defined('ABSPATH') || exit;
function bvmgr_staffing_migrate_lifecycle(): array
{
    global $wpdb;
    if (!current_user_can('manage_options') || bvmgr_staffing_transaction_active()) return array('ok' => false, 'error' => 'forbidden');
    if (get_class($wpdb) !== 'wpdb') return array('ok' => false, 'error' => 'unsupported_database_adapter');
    $original = $wpdb;
    $original->flush();
    $wpdb = new BVMGR_Staffing_Transaction_DB($original);
    $lock = bvmgr_staffing_lock_name();
    $locked = false;
    try {
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) throw new BVMGR_Staffing_Failure('staffing_busy');
        $locked = true;
        if ((int) $wpdb->get_var('SELECT @@autocommit') !== 1 || $wpdb->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED') === false) throw new BVMGR_Staffing_Failure('external_transaction');
        bvmgr_staffing_require_transaction_schema(false);
        $changes = array('assignments' => array('revision' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0'),
            'audit' => array('assignment_id' => 'BIGINT UNSIGNED NULL', 'operation_id' => 'VARCHAR(64) NULL'));
        foreach ($changes as $kind => $columns) {
            $table = bvmgr_staffing_table_name($kind);
            $present = $wpdb->get_col($wpdb->prepare('SHOW COLUMNS FROM %i', $table));
            foreach ($columns as $name => $ddl) {
                if (!in_array($name, $present, true) && $wpdb->query($wpdb->prepare('ALTER TABLE %i ADD COLUMN %i ', $table, $name) . $ddl) === false) throw new BVMGR_Staffing_Failure('migration_failed');
            }
        }
        $audit = bvmgr_staffing_table_name('audit');
        $indexes = $wpdb->get_results($wpdb->prepare('SHOW INDEX FROM %i', $audit), ARRAY_A);
        $names = array_column($indexes, 'Key_name');
        if (!in_array('lifecycle_operation', $names, true) && $wpdb->query($wpdb->prepare('ALTER TABLE %i ADD UNIQUE KEY lifecycle_operation (operation_id)', $audit)) === false) throw new BVMGR_Staffing_Failure('migration_failed');
        if (!in_array('lifecycle_assignment', $names, true) && $wpdb->query($wpdb->prepare('ALTER TABLE %i ADD KEY lifecycle_assignment (assignment_id)', $audit)) === false) throw new BVMGR_Staffing_Failure('migration_failed');
        bvmgr_staffing_require_transaction_schema();
        return array('ok' => true);
    } catch (Throwable $e) {
        return array('ok' => false, 'error' => $e instanceof BVMGR_Staffing_Failure ? $e->getMessage() : 'migration_failed');
    } finally {
        if ($locked) { try { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); } catch (Throwable $ignored) {} }
        $wpdb = $original;
    }
}

/** Read-only receipt for a future, separately authorized migration window. */
function bvmgr_staffing_lifecycle_preflight(): array
{
    global $wpdb;
    if (!current_user_can('manage_options')) return array('ok' => false, 'error' => 'forbidden');
    $engines = array();
    foreach (bvmgr_staffing_transaction_tables() as $table) {
        $engines[$table] = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table));
    }
    $duplicates = $wpdb->get_results($wpdb->prepare("SELECT slot_id, staff_id, COUNT(*) AS history_rows, SUM(status IN ('proposed','confirmed')) AS active_rows FROM %i GROUP BY slot_id, staff_id HAVING COUNT(*)>1 ORDER BY slot_id, staff_id", bvmgr_staffing_table_name('assignments')), ARRAY_A);
    $orphans = $wpdb->get_results($wpdb->prepare('SELECT a.assignment_id FROM %i a LEFT JOIN %i s ON s.slot_id=a.slot_id LEFT JOIN %i p ON p.ID=s.event_plan_id AND p.post_type=%s LEFT JOIN %i u ON u.ID=a.staff_id AND u.post_type=%s WHERE s.slot_id IS NULL OR p.ID IS NULL OR u.ID IS NULL ORDER BY a.assignment_id',
        bvmgr_staffing_table_name('assignments'), bvmgr_staffing_table_name('event_slots'), $wpdb->posts, 'vms_event_plan', $wpdb->posts, 'vms_staff'), ARRAY_A);
    try { bvmgr_staffing_require_transaction_schema(); $schema = 'ready'; }
    catch (BVMGR_Staffing_Failure $e) { $schema = $e->getMessage(); }
    return array('ok' => $wpdb->last_error === '', 'engines' => $engines, 'schema' => $schema, 'duplicate_pairs' => $duplicates, 'orphan_assignment_ids' => array_map('intval', array_column($orphans ?: array(), 'assignment_id')));
}
