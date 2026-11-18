<?php
/** Explicit additive migration. No boot/activation hook runs this migration. */
defined('ABSPATH') || exit;
require_once __DIR__ . '/staffing-lifecycle-preflight.php';
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
        $preflight = bvmgr_staffing_lifecycle_preflight();
        if (empty($preflight['ok'])) return array('ok' => false, 'error' => 'preflight_blocked', 'preflight' => $preflight);
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
    return bvmgr_staffing_lifecycle_inspect(static function (string $sql) use ($wpdb): array {
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if ($wpdb->last_error !== '' || !is_array($rows)) throw new BVMGR_Staffing_Failure('database_error');
        return $rows;
    }, $wpdb->prefix, array('assignments' => bvmgr_staffing_table_name('assignments'),
        'event_slots' => bvmgr_staffing_table_name('event_slots'), 'audit' => bvmgr_staffing_table_name('audit'),
        'rollups' => bvmgr_staffing_table_name('rollups'), 'posts' => $wpdb->posts, 'postmeta' => $wpdb->postmeta));
}
