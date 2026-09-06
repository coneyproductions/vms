<?php
/** SQL-only staffing gate: no WordPress boot, migration, hooks or persistent writes. */
defined('ABSPATH') || exit;

/**
 * The reader must return associative rows and throw on EVERY failed query.
 * Both the offline CLI and the authorized runtime migration use this inspection.
 */
function bvmgr_staffing_lifecycle_inspect(callable $read, string $prefix, array $table_overrides = array()): array
{
    $receipt = array('ok' => false, 'blockers' => array(), 'engines' => array(), 'schema' => 'unknown',
        'duplicate_pairs' => array(), 'orphan_assignment_ids' => array());
    if (!preg_match('/^[a-zA-Z0-9_]+$/D', $prefix)) return array_merge($receipt, array('error' => 'invalid_prefix'));
    $tables = array('assignments' => $prefix . 'vms_event_role_assignments', 'event_slots' => $prefix . 'vms_event_role_slots',
        'audit' => $prefix . 'vms_staffing_audit_log', 'rollups' => $prefix . 'vms_staffing_event_rollups',
        'posts' => $prefix . 'posts', 'postmeta' => $prefix . 'postmeta');
    $tables = array_replace($tables, array_intersect_key($table_overrides, $tables));
    foreach ($tables as $table) if (!is_string($table) || !preg_match('/^[a-zA-Z0-9_]+$/D', $table)) return array_merge($receipt, array('error' => 'invalid_table'));
    try {
        foreach ($tables as $table) {
            $rows = $read("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table'");
            $receipt['engines'][$table] = $rows[0]['ENGINE'] ?? null;
            if (strtolower((string) $receipt['engines'][$table]) !== 'innodb') $receipt['blockers'][] = 'transactional_engine_required';
        }
        $missing = false;
        $existing_columns = array();
        $expected = array('assignments' => array('revision' => array('/^bigint(?:\(\d+\))? unsigned$/i', 'NO', '0')),
            'audit' => array('assignment_id' => array('/^bigint(?:\(\d+\))? unsigned$/i', 'YES', null), 'operation_id' => array('/^varchar\(64\)$/i', 'YES', null)));
        foreach ($expected as $kind => $names) {
            $columns = array_column($read('SHOW COLUMNS FROM `' . $tables[$kind] . '`'), null, 'Field');
            $existing_columns[$kind] = $columns;
            foreach ($names as $name => $shape) {
                if (!isset($columns[$name])) { $missing = true; continue; }
                $column = $columns[$name];
                if (!preg_match($shape[0], $column['Type']) || $column['Null'] !== $shape[1] || $column['Default'] !== $shape[2]) $receipt['blockers'][] = 'incompatible_lifecycle_schema';
            }
        }
        $indexes = $read('SHOW INDEX FROM `' . $tables['audit'] . '`');
        foreach (array('lifecycle_operation' => array('operation_id', 0), 'lifecycle_assignment' => array('assignment_id', 1)) as $name => $shape) {
            $index = array_values(array_filter($indexes, static function ($row) use ($name): bool { return $row['Key_name'] === $name; }));
            if (!$index) { $missing = true; continue; }
            if (count($index) !== 1 || $index[0]['Column_name'] !== $shape[0] || (int) $index[0]['Non_unique'] !== $shape[1] || $index[0]['Sub_part'] !== null) $receipt['blockers'][] = 'incompatible_lifecycle_schema';
        }
        if (isset($existing_columns['audit']['operation_id'])) {
            $audit_table = $tables['audit'];
            $operation_duplicates = $read("SELECT operation_id FROM `$audit_table` WHERE operation_id IS NOT NULL GROUP BY operation_id HAVING COUNT(*)>1");
            if ($operation_duplicates) $receipt['blockers'][] = 'duplicate_audit_operations';
        }
        $receipt['schema'] = $missing ? 'lifecycle_migration_required' : 'ready';
        $a = $tables['assignments']; $s = $tables['event_slots']; $p = $tables['posts'];
        $receipt['duplicate_pairs'] = $read("SELECT slot_id, staff_id, COUNT(*) AS history_rows, SUM(status IN ('proposed','confirmed')) AS active_rows FROM `$a` GROUP BY slot_id, staff_id HAVING COUNT(*)>1 ORDER BY slot_id, staff_id");
        foreach ($receipt['duplicate_pairs'] as $pair) if ((int) $pair['active_rows'] > 1) $receipt['blockers'][] = 'duplicate_active_assignments';
        $orphans = $read("SELECT a.assignment_id FROM `$a` a LEFT JOIN `$s` s ON s.slot_id=a.slot_id LEFT JOIN `$p` p ON p.ID=s.event_plan_id AND p.post_type='vms_event_plan' LEFT JOIN `$p` u ON u.ID=a.staff_id AND u.post_type='vms_staff' WHERE s.slot_id IS NULL OR p.ID IS NULL OR u.ID IS NULL ORDER BY a.assignment_id");
        $receipt['orphan_assignment_ids'] = array_map('intval', array_column($orphans, 'assignment_id'));
        if ($orphans) $receipt['blockers'][] = 'orphan_assignments';
        // Tentative/historical rows may have no resolved window. A commitment may not.
        $receipt['invalid_confirmed_window_ids'] = array_map('intval', array_column($read("SELECT assignment_id FROM `$a` WHERE status='confirmed' AND (shift_start_ts IS NULL OR shift_start_ts<=0 OR shift_end_ts IS NULL OR shift_end_ts<=shift_start_ts) ORDER BY assignment_id"), 'assignment_id'));
        if ($receipt['invalid_confirmed_window_ids']) $receipt['blockers'][] = 'invalid_confirmed_windows';
        $receipt['overlapping_confirmations'] = $read("SELECT a.assignment_id, b.assignment_id AS conflicting_assignment_id FROM `$a` a JOIN `$a` b ON a.staff_id=b.staff_id AND a.assignment_id<b.assignment_id AND a.shift_start_ts<b.shift_end_ts AND b.shift_start_ts<a.shift_end_ts WHERE a.status='confirmed' AND b.status='confirmed' ORDER BY a.assignment_id,b.assignment_id");
        if ($receipt['overlapping_confirmations']) $receipt['blockers'][] = 'overlapping_confirmations';
        $receipt['blockers'] = array_values(array_unique($receipt['blockers']));
        $receipt['ok'] = !$receipt['blockers'];
        if (!$receipt['ok']) $receipt['error'] = 'preflight_blocked';
    } catch (Throwable $e) {
        // Later successful reads must never erase an earlier SQL failure.
        $receipt['blockers'][] = 'database_error';
        $receipt['error'] = 'database_error';
        $receipt['ok'] = false;
    }
    return $receipt;
}
