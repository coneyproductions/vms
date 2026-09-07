<?php
/** Private captured population, imported only into the guarded disposable DB. */
require __DIR__ . '/bootstrap.php';
if (getenv('BVM_DISPOSABLE_DB_GUARDED') !== '1' || DB_NAME !== 'bvm_integration_source'
    || DB_HOST !== 'localhost:/private/tmp/bvm-authority-integration-20260906/runtime/mysql.sock') throw new RuntimeException('Guarded integration fixture required');
$original = $wpdb;
$wpdb = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
$wpdb->set_prefix('bvm_population_');
// Woo/Action Scheduler register extra table properties on the host adapter.
// Retain those disposable prerequisite mappings when switching staffing prefix.
$standard_properties = get_object_vars($wpdb);
foreach (get_object_vars($original) as $property => $value) {
    if (!array_key_exists($property, $standard_properties) && is_string($value) && str_starts_with($value, $original->prefix)) $wpdb->$property = $value;
}
if (($argv[1] ?? '') === '--migration-worker') {
    $result = bvmgr_staffing_migrate_lifecycle();
    $wpdb = $original; // Plugin shutdown callbacks must retain their own table map.
    echo json_encode($result, JSON_THROW_ON_ERROR), "\n";
    exit;
}
$capture = json_decode(gzdecode(file_get_contents(getenv('BVM_POPULATION_CAPTURE'))), true, 512, JSON_THROW_ON_ERROR);
$backup = json_decode(gzdecode(file_get_contents(getenv('BVM_POPULATION_BACKUP'))), true, 512, JSON_THROW_ON_ERROR);
$created = array(); $checks = 0; $receipt = array();
function pop_check($ok, string $label): void { global $checks; if (!$ok) throw new RuntimeException($label); $checks++; }
function pop_snapshot(): array {
    global $wpdb, $created;
    $state = array();
    foreach ($created as $table) {
        $rows = $wpdb->get_results("SELECT * FROM `$table` ORDER BY 1", ARRAY_A);
        // Keep complete staffing before-images; compare bulky unchanged WordPress
        // context with ordered hashes rather than retaining many 83k-row copies.
        if ($table === $wpdb->posts || $table === $wpdb->postmeta) $rows = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
        $state[$table] = array('schema' => $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N)[1], 'rows' => $rows);
    }
    return $state;
}
function pop_insert(string $table, array $rows): void {
    global $wpdb;
    foreach (array_chunk($rows, 250) as $chunk) {
        $columns = '`' . implode('`,`', array_keys($chunk[0])) . '`'; $values = array();
        foreach ($chunk as $row) $values[] = '(' . implode(',', array_map(static function ($value) use ($wpdb) {
            return $value === null ? 'NULL' : $wpdb->prepare('%s', $value);
        }, $row)) . ')';
        pop_check($wpdb->query("INSERT INTO `$table` ($columns) VALUES " . implode(',', $values)) === count($chunk), 'Exact captured inserts: ' . $table);
    }
}
function pop_remediate(array $backup): void {
    global $wpdb;
    $slots = $wpdb->prefix . 'vms_event_role_slots'; $rollups = $wpdb->prefix . 'vms_staffing_event_rollups';
    foreach (array('exact_slot_ids', 'exact_rollup_ids') as $key) pop_check(count($backup[$key]) === ($key === 'exact_slot_ids' ? 165 : 100), 'Approved population count');
    pop_check($wpdb->query("DELETE FROM `$slots` WHERE slot_id IN (" . implode(',', array_map('intval', $backup['exact_slot_ids'])) . ')') === 165, 'S1 exact 165 fixture rows');
    pop_check($wpdb->query("DELETE FROM `$rollups` WHERE event_plan_id IN (" . implode(',', array_map('intval', $backup['exact_rollup_ids'])) . ')') === 100, 'R1 exact 100 fixture rows');
}
try {
    pop_check($wpdb->get_var('SELECT VERSION()') === '8.0.35', 'Exact normal MySQL version');
    foreach ($capture['tables'] as $source => $table) {
        $target = $wpdb->prefix . substr($source, 3);
        pop_check(!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $target)), 'New private capture prefix');
        $schema = array_values($table['schema'][0])[1];
        $schema = preg_replace('/^CREATE TABLE `[^`]+`/', 'CREATE TABLE `' . $target . '`', $schema);
        pop_check($wpdb->query($schema) !== false, 'Exact captured schema'); $created[] = $target;
        pop_insert($target, $table['rows']);
    }
    $wpdb->query("CREATE TABLE `{$wpdb->options}` LIKE `{$original->options}`"); $created[] = $wpdb->options;
    pop_insert($wpdb->options, $capture['options']);
    $before = pop_snapshot();
    $a = $wpdb->prefix . 'vms_event_role_assignments'; $s = $wpdb->prefix . 'vms_event_role_slots';
    $audit = $wpdb->prefix . 'vms_staffing_audit_log'; $r = $wpdb->prefix . 'vms_staffing_event_rollups';
    pop_check(count($before[$a]['rows']) === 5 && count($before[$audit]['rows']) === 1337, 'Preserved assignments and full audit count');
    $gate = bvmgr_staffing_lifecycle_preflight();
    $receipt['before_counts'] = $gate['counts'];
    $pre = count($before[$s]['rows']) === 3399;
    if ($pre) {
        pop_check($gate['blockers'] === array('orphan_slots', 'invalid_rollups') && $gate['counts']['orphan_slots'] === 165 && $gate['counts']['invalid_rollups'] === 100, 'Exact pre-remediation population blockers');
        $result = bvmgr_staffing_migrate_lifecycle();
        pop_check(!$result['ok'] && pop_snapshot() === $before, 'Captured population direct migration makes zero changes');
        $pipes = array();
        $proc = proc_open(array(PHP_BINARY, dirname(__DIR__, 2) . '/scripts/staffing-lifecycle-preflight.php', '--socket=' . substr(DB_HOST, 10), '--database=' . DB_NAME, '--prefix=' . $wpdb->prefix), array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')), $pipes);
        fclose($pipes[0]); $json=stream_get_contents($pipes[1]);fclose($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);
        pop_check(proc_close($proc) === 1 && json_decode($json,true)['counts'] === $gate['counts'] && pop_snapshot() === $before, 'Captured population CLI exact nonzero/no mutation ' . $stderr);
        $wpdb->query('START TRANSACTION'); pop_remediate($backup); $wpdb->query('ROLLBACK');
        pop_check(pop_snapshot() === $before, 'Exact targeted deletion transaction rollback');
        $wpdb->query('START TRANSACTION'); pop_remediate($backup); $wpdb->query('COMMIT');
    } else pop_check(count($before[$s]['rows']) === 3234 && count($before[$r]['rows']) === 1103 && $gate['ok'], 'Actual remediated capture shape');
    $clean = pop_snapshot();
    pop_check(bvmgr_staffing_lifecycle_preflight()['ok'], 'Resulting shape has zero blockers');
    $workers = array();
    for ($i=0; $i<2; $i++) {
        $pipes=array();$proc=proc_open(array(PHP_BINARY,__FILE__,'--migration-worker'),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
        pop_check(is_resource($proc),'Independent migration worker');fclose($pipes[0]);$workers[]=array($proc,$pipes);
    }
    foreach ($workers as [$proc,$pipes]) {
        $json=stream_get_contents($pipes[1]);fclose($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);
        pop_check(proc_close($proc)===0 && !empty(json_decode($json,true)['ok']), 'Concurrent captured-shape migration ' . $stderr);
    }
    $migrated = pop_snapshot(); $old = $migrated;
    foreach ($old[$a]['rows'] as &$row) { pop_check($row['revision'] === '0', 'Default revision'); unset($row['revision']); } unset($row);
    foreach ($old[$audit]['rows'] as &$row) { pop_check($row['assignment_id'] === null && $row['operation_id'] === null, 'No fabricated historical audit identity'); unset($row['assignment_id'],$row['operation_id']); } unset($row);
    foreach ($clean as $table=>$state) pop_check($old[$table]['rows'] === $state['rows'], 'Every captured old-column row preserved: ' . $table);
    pop_check(bvmgr_staffing_migrate_lifecycle()['ok'] && pop_snapshot() === $migrated, 'Exact migration idempotence');
    $receipt['after_counts'] = bvmgr_staffing_lifecycle_preflight()['counts'];
    require_once dirname(__DIR__, 2) . '/includes/admin/event-command-center.php';
    wp_cache_flush();
    $plans = array_map('intval', $wpdb->get_col("SELECT DISTINCT s.event_plan_id FROM `$s` s JOIN `$a` a ON a.slot_id=s.slot_id UNION SELECT event_plan_id FROM `$s` WHERE status='canceled'"));
    pop_check(count($plans) > 0, 'Captured assigned and retained historical Event Plans');
    $admin = (new ReflectionClass('BVMGR_Admin_Event_Plans'))->newInstanceWithoutConstructor();
    $context = new ReflectionMethod($admin, 'get_event_plan_staff_render_context'); $context->setAccessible(true);
    $wpdb->query('START TRANSACTION');
    try {
        foreach ($plans as $plan) {
            $shared = bvmgr_staffing_resolve_event_snapshot($plan);
            $full = bvmgr_event_command_center_get_staffing_snapshot($plan);
            $light = bvmgr_event_command_center_get_staffing_snapshot_light($plan);
            $editor = $context->invoke($admin, $plan, array())['staffing_snapshot'];
            foreach (array('assigned_headcount','proposed_headcount','confirmed_headcount','open_headcount_total','planned_headcount') as $key) {
                pop_check($shared[$key] === $full[$key] && $shared[$key] === $light[$key] && $shared[$key] === $editor[$key], 'Captured Event Plan / full ECC / light ECC shared ' . $key);
            }
        }
    } finally { $wpdb->query('ROLLBACK'); wp_cache_flush(); }
    $consumer_rollback = pop_snapshot();
    foreach ($migrated as $table => $state) {
        pop_check($consumer_rollback[$table]['rows'] === $state['rows'], 'Consumer rollback preserves all rows: ' . $table);
        if ($consumer_rollback[$table]['schema'] !== $state['schema']) {
            // InnoDB does not rewind identity allocation on transaction rollback.
            // Restore only that verified disposable counter, never normal Local.
            pop_check(preg_replace('/ AUTO_INCREMENT=\d+/', '', $consumer_rollback[$table]['schema']) === preg_replace('/ AUTO_INCREMENT=\d+/', '', $state['schema']), 'Only disposable allocation counter may advance');
            preg_match('/ AUTO_INCREMENT=(\d+)/', $state['schema'], $match);
            pop_check(!empty($match[1]), 'Recorded pre-render allocation counter');
            $wpdb->query("ALTER TABLE `$table` AUTO_INCREMENT=" . (int) $match[1]);
        }
    }
    pop_check(pop_snapshot() === $migrated, 'Consumer/cache rollback and identity counter restoration exact');
    $receipt['captured_consumer_plan_ids'] = $plans;
    // Exact schema reversal is permissible here only because no lifecycle writes
    // occurred after backup. Promotion rollback with newer history must retain additions.
    $wpdb->query("ALTER TABLE `$audit` DROP INDEX lifecycle_operation, DROP INDEX lifecycle_assignment, DROP COLUMN assignment_id, DROP COLUMN operation_id");
    $wpdb->query("ALTER TABLE `$a` DROP COLUMN revision");
    pop_check(pop_snapshot() === $clean, 'Exact resulting-shape schema/state rollback');
    if ($pre) {
        pop_insert($s, $backup['slots']); pop_insert($r, $backup['rollups']);
        pop_check(pop_snapshot() === $before, 'Original population archive before-images are recoverable');
    }
    $receipt += array('ok'=>true,'checks'=>$checks,'pre_remediation_capture'=>$pre,'concurrent_migrations'=>2,
        'idempotent'=>true,'all_old_rows_preserved'=>true,'rollback_exact'=>true,'assignments_preserved'=>5,'audit_preserved'=>1337);
    echo json_encode($receipt,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR), "\n";
} finally {
    foreach (array_reverse($created) as $table) $wpdb->query("DROP TABLE `$table`");
    $wpdb=$original;
}
