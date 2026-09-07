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
        'posts' => $prefix . 'posts', 'postmeta' => $prefix . 'postmeta',
        'terms' => $prefix . 'terms', 'term_taxonomy' => $prefix . 'term_taxonomy');
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
        $operation_duplicates = array();
        $invalid_operation_ids = array();
        if (isset($existing_columns['audit']['operation_id'])) {
            $audit_table = $tables['audit'];
            $operation_duplicates = $read("SELECT operation_id FROM `$audit_table` WHERE operation_id IS NOT NULL GROUP BY operation_id HAVING COUNT(*)>1");
            if ($operation_duplicates) $receipt['blockers'][] = 'duplicate_audit_operations';
            $invalid_operation_ids = $read("SELECT log_id FROM `$audit_table` WHERE operation_id IS NOT NULL AND operation_id NOT REGEXP '^[a-zA-Z0-9-]{16,64}$' ORDER BY log_id");
            if ($invalid_operation_ids) $receipt['blockers'][] = 'invalid_audit_operation_ids';
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
        // Population integrity is a gate, never a cleanup path. Retained audit snapshots
        // may reference deleted plans; operational slots and cache roots may not.
        $r = $tables['rollups']; $t = $tables['terms']; $tt = $tables['term_taxonomy'];
        $checks = array(
            'orphan_slots' => "SELECT s.slot_id,s.event_plan_id FROM `$s` s LEFT JOIN `$p` p ON p.ID=s.event_plan_id AND p.post_type='vms_event_plan' WHERE p.ID IS NULL ORDER BY s.slot_id",
            'invalid_slot_roles' => "SELECT s.slot_id,s.role_id FROM `$s` s WHERE NOT EXISTS (SELECT 1 FROM `$t` t JOIN `$tt` tt ON tt.term_id=t.term_id AND tt.taxonomy='vms_staff_role' WHERE t.term_id=s.role_id) ORDER BY s.slot_id",
            'invalid_slot_values' => "SELECT slot_id FROM `$s` WHERE slot_id<=0 OR event_plan_id<=0 OR role_id<=0 OR headcount_needed<0 OR break_minutes<0 OR duration_minutes<0 OR pay_rate<0 OR status NOT IN ('active','canceled') OR shift_time_mode NOT IN ('absolute','relative') OR pay_type NOT IN ('inherit_role','none','hourly','flat') OR (shift_start_local IS NOT NULL AND shift_start_local<>'' AND shift_start_local NOT REGEXP '^([01][0-9]|2[0-3]):[0-5][0-9]$') OR (shift_end_local IS NOT NULL AND shift_end_local<>'' AND shift_end_local NOT REGEXP '^([01][0-9]|2[0-3]):[0-5][0-9]$') OR (start_anchor_key IS NOT NULL AND start_anchor_key<>'' AND start_anchor_key NOT IN ('event_start','event_end','a1','a2','a3','a4')) OR (end_anchor_key IS NOT NULL AND end_anchor_key<>'' AND end_anchor_key NOT IN ('event_start','event_end','a1','a2','a3','a4')) ORDER BY slot_id",
            'invalid_assignment_values' => "SELECT assignment_id FROM `$a` WHERE assignment_id<=0 OR slot_id<=0 OR staff_id<=0 OR status NOT IN ('proposed','confirmed','declined','canceled') OR pay_rate_override<0 OR (pay_type_override IS NOT NULL AND pay_type_override<>'' AND pay_type_override NOT IN ('inherit_role','none','hourly','flat')) ORDER BY assignment_id",
            'active_assignments_on_inactive_slots' => "SELECT a.assignment_id FROM `$a` a JOIN `$s` s ON s.slot_id=a.slot_id WHERE a.status IN ('proposed','confirmed') AND s.status<>'active' ORDER BY a.assignment_id",
            'invalid_rollups' => "SELECT r.event_plan_id FROM `$r` r LEFT JOIN `$p` p ON p.ID=r.event_plan_id AND p.post_type='vms_event_plan' WHERE p.ID IS NULL ORDER BY r.event_plan_id",
            'duplicate_rollups' => "SELECT event_plan_id,COUNT(*) AS row_count FROM `$r` GROUP BY event_plan_id HAVING COUNT(*)>1 ORDER BY event_plan_id",
            'invalid_rollup_values' => "SELECT event_plan_id FROM `$r` WHERE event_plan_id<=0 OR dirty NOT IN (0,1) OR slots_total<0 OR headcount_needed_total<0 OR headcount_filled_total<0 OR open_headcount_total<0 OR open_slots_count<0 OR critical_slots_total<0 OR critical_open_headcount<0 OR critical_open_slots_count<0 OR conflict_count<0 OR unavailable_assigned_count<0 OR red_flag_reason_mask<0 OR est_labor_cost_total<0 OR est_hours_total<0 OR readiness_status NOT IN ('not_applicable','ready','needs_staff','red_flag') OR (missing_summary_json IS NOT NULL AND JSON_VALID(missing_summary_json)=0) OR (conflict_summary_json IS NOT NULL AND JSON_VALID(conflict_summary_json)=0) OR (dirty=0 AND (computed_at IS NULL OR calc_version='' OR calc_hash IS NULL OR calc_hash NOT REGEXP '^[0-9a-f]{32}$')) ORDER BY event_plan_id",
        );
        $receipt['population'] = array();
        foreach ($checks as $category => $sql) {
            $receipt['population'][$category] = $read($sql);
            if ($receipt['population'][$category]) $receipt['blockers'][] = $category;
        }
        // Multiple slots per role can represent separate shifts; do not invent a
        // uniqueness constraint. Terminal duplicate assignment history is supported.
        $receipt['population']['multiple_role_slots'] = $read("SELECT event_plan_id,role_id,COUNT(*) AS row_count FROM `$s` GROUP BY event_plan_id,role_id HAVING COUNT(*)>1 ORDER BY event_plan_id,role_id");
        $receipt['population']['trashed_plan_slots'] = $read("SELECT s.slot_id FROM `$s` s JOIN `$p` p ON p.ID=s.event_plan_id AND p.post_type='vms_event_plan' WHERE p.post_status='trash' ORDER BY s.slot_id");
        $receipt['population']['dirty_rollups'] = $read("SELECT event_plan_id FROM `$r` WHERE dirty=1 ORDER BY event_plan_id");
        $receipt['population']['tolerated_historical_slots'] = $read("SELECT s.slot_id FROM `$s` s JOIN `$p` p ON p.ID=s.event_plan_id AND p.post_type='vms_event_plan' WHERE s.status='canceled' OR p.post_status='trash' ORDER BY s.slot_id");
        $receipt['population']['tolerated_historical_rollups'] = $read("SELECT r.event_plan_id FROM `$r` r JOIN `$p` p ON p.ID=r.event_plan_id AND p.post_type='vms_event_plan' WHERE p.post_status='trash' OR r.event_status IN ('canceled','cancelled') ORDER BY r.event_plan_id");
        $receipt['historical_duplicate_pairs'] = array_values(array_filter($receipt['duplicate_pairs'], static function ($pair): bool { return (int) $pair['active_rows'] <= 1; }));
        $receipt['counts'] = array('orphan_assignments' => count($receipt['orphan_assignment_ids']),
            'duplicate_assignment_pairs' => count($receipt['duplicate_pairs']),
            'duplicate_active_assignment_pairs' => count($receipt['duplicate_pairs']) - count($receipt['historical_duplicate_pairs']),
            'invalid_confirmed_windows' => count($receipt['invalid_confirmed_window_ids']),
            'overlapping_confirmations' => count($receipt['overlapping_confirmations']));
        $receipt['counts']['duplicate_audit_operations'] = count($operation_duplicates);
        $receipt['invalid_audit_operation_ids'] = array_map('intval', array_column($invalid_operation_ids, 'log_id'));
        $receipt['counts']['invalid_audit_operation_ids'] = count($invalid_operation_ids);
        $receipt['counts']['historical_duplicate_assignment_pairs'] = count($receipt['historical_duplicate_pairs']);
        $receipt['counts']['invalid_lifecycle_statuses'] = (int) $read("SELECT COUNT(*) AS n FROM `$a` WHERE status IS NULL OR status NOT IN ('proposed','confirmed','declined','canceled')")[0]['n'];
        if ($receipt['counts']['invalid_lifecycle_statuses']) $receipt['blockers'][] = 'invalid_assignment_values';
        foreach ($receipt['population'] as $category => $rows) $receipt['counts'][$category] = count($rows);
        // Validate the base shape before additive DDL, including columns the current
        // fixture may not exercise. Missing/incompatible schemas must not be partly upgraded.
        $base = array(
            'assignments' => 'assignment_id slot_id staff_id status pay_type_override pay_rate_override notes shift_start_ts shift_end_ts actual_start_local actual_end_local created_at created_by updated_at updated_by',
            'event_slots' => 'slot_id event_plan_id role_id headcount_needed shift_time_mode shift_start_local shift_end_local start_anchor_key start_offset_minutes end_anchor_key end_offset_minutes duration_minutes break_minutes pay_type pay_rate display_label_override notes status created_at created_by updated_at updated_by',
            'audit' => 'log_id event_plan_id actor_user_id action before_json after_json created_at',
            'rollups' => 'event_plan_id venue_id event_status event_start_local slots_total headcount_needed_total headcount_filled_total open_headcount_total open_slots_count critical_slots_total critical_open_headcount critical_open_slots_count conflict_count unavailable_assigned_count red_flag_reason_mask readiness_status est_labor_cost_total est_hours_total missing_summary_json conflict_summary_json calc_version calc_hash computed_at dirty dirty_reason',
        );
        $receipt['schema_issues'] = array();
        foreach ($base as $kind => $names) {
            $table = $tables[$kind];
            $columns = array_column($read("SHOW COLUMNS FROM `$table`"), null, 'Field');
            foreach (explode(' ', $names) as $name) if (!isset($columns[$name])) $receipt['schema_issues'][] = "$kind.missing.$name";
            $key = explode(' ', $names)[0];
            $primary = array_values(array_filter($read("SHOW INDEX FROM `$table`"), static function ($index): bool { return $index['Key_name'] === 'PRIMARY'; }));
            if (count($primary) !== 1 || $primary[0]['Column_name'] !== $key || (int) $primary[0]['Non_unique'] !== 0 || $primary[0]['Sub_part'] !== null) $receipt['schema_issues'][] = "$kind.primary";
            if ($kind !== 'rollups' && isset($columns[$key]) && strpos($columns[$key]['Extra'], 'auto_increment') === false) $receipt['schema_issues'][] = "$kind.auto_increment";
            foreach ($columns as $name => $column) {
                if (in_array($name, array('slot_id','staff_id','role_id','assignment_id','log_id','event_plan_id'), true) && (!preg_match('/^bigint(?:\(\d+\))? unsigned$/i', $column['Type']) || ($kind !== 'audit' && $column['Null'] !== 'NO'))) $receipt['schema_issues'][] = "$kind.identity.$name";
            }
        }
        $receipt['counts']['base_schema_issues'] = count($receipt['schema_issues']);
        $receipt['counts']['nontransactional_tables'] = count(array_filter($receipt['engines'], static function ($engine): bool { return strtolower((string) $engine) !== 'innodb'; }));
        if ($receipt['schema_issues']) $receipt['blockers'][] = 'incompatible_base_schema';
        if (isset($existing_columns['assignments']['revision'])) {
            $receipt['population']['invalid_revisions'] = $read("SELECT assignment_id FROM `$a` WHERE revision IS NULL OR revision<0 ORDER BY assignment_id");
            $receipt['counts']['invalid_revisions'] = count($receipt['population']['invalid_revisions']);
            if ($receipt['counts']['invalid_revisions']) $receipt['blockers'][] = 'invalid_revisions';
        } else $receipt['counts']['invalid_revisions'] = 0;
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
