<?php
/** Exact captured shape on a separate disposable database; never normal Local. */
require __DIR__ . '/bootstrap.php';
if (DB_NAME !== 'bvm_integration_source' || DB_HOST !== 'localhost:/private/tmp/bvm-authority-integration-20260906/runtime/mysql.sock') throw new RuntimeException('Integration fixture required');
$source = $wpdb;
$shape_host = 'localhost:/private/tmp/bvm-orphan-shape-20260906/mysql.sock';
$wpdb = new wpdb(DB_USER, DB_PASSWORD, 'bvm_staffing_shape', $shape_host);
$wpdb->set_prefix('wp_');
$wpdb->suppress_errors(true);
if (($argv[1] ?? '') === '--migration-worker') { echo json_encode(bvmgr_staffing_migrate_lifecycle(), JSON_THROW_ON_ERROR), "\n"; exit; }
$receipt = array('database'=>'bvm_staffing_shape','source_candidate'=>'1375c53263dcb6c6cec4753dd4d5735e3057f5e3 plus sequencing fix','remove_test_container'=>getenv('BVM_STAFFING_REMOVE_TEST_CONTAINER')==='1');
function shape_assert($ok, string $label): void { if (!$ok) throw new RuntimeException($label); }
function shape_snapshot(): array {
    global $wpdb;
    $snapshot = array();
    foreach ($wpdb->get_col('SHOW TABLES') as $table) {
        $snapshot[$table] = array('schema'=>$wpdb->get_row("SHOW CREATE TABLE `$table`",ARRAY_N)[1], 'rows'=>$wpdb->get_results("SELECT * FROM `$table` ORDER BY 1",ARRAY_A));
    }
    return $snapshot;
}
$before = shape_snapshot();
$receipt['database_version'] = $wpdb->get_var('SELECT VERSION()');
$receipt['database_host'] = $shape_host;
if ($receipt['database_version'] !== '8.0.35') throw new RuntimeException('Same MySQL 8.0.35 shape required');
$a = bvmgr_staffing_table_name('assignments'); $s = bvmgr_staffing_table_name('event_slots'); $audit = bvmgr_staffing_table_name('audit'); $rollup = bvmgr_staffing_table_name('rollups');
try {
    shape_assert(count($before[$a]['rows'])===8 && count($before[$s]['rows'])===3400 && count($before[$audit]['rows'])===1337 && count($before[$rollup]['rows'])===1204,'Exact original row counts required');
    $receipt['original_preflight'] = bvmgr_staffing_lifecycle_preflight();
    shape_assert($receipt['original_preflight']['orphan_assignment_ids']===array(19,20,21) && !$receipt['original_preflight']['ok'],'Exact original orphans');
    $receipt['original_direct_migration'] = bvmgr_staffing_migrate_lifecycle();
    shape_assert(!$receipt['original_direct_migration']['ok'] && shape_snapshot()===$before,'Original gate zero schema/rows/version/auto-increment mutation');
    $receipt['original_no_mutation'] = true;
    // Rehearse the exact targeted DELETE and transaction rollback before committing in this fixture.
    $wpdb->query('START TRANSACTION');
    shape_assert($wpdb->query("DELETE FROM `$a` WHERE assignment_id IN (19,20,21) AND slot_id=3422 AND staff_id IN (4588,4589,4590)")===3,'Three proven disposable assignment rows');
    $wpdb->query('ROLLBACK');
    shape_assert(shape_snapshot()===$before,'Targeted data rollback exact');
    $wpdb->query('START TRANSACTION');
    shape_assert($wpdb->query("DELETE FROM `$a` WHERE assignment_id IN (19,20,21) AND slot_id=3422 AND staff_id IN (4588,4589,4590)")===3,'Repeat exact targeted remediation');
    if ($receipt['remove_test_container']) {
        shape_assert($wpdb->query("DELETE FROM `$s` WHERE slot_id=3422 AND event_plan_id=4587 AND role_id=89")===1,'Only test slot');
        shape_assert($wpdb->query("DELETE FROM `$rollup` WHERE event_plan_id=4587")===1,'Only test cache');
    }
    $wpdb->query('COMMIT');
    $remediated = shape_snapshot();
    $receipt['post_remediation_preflight'] = bvmgr_staffing_lifecycle_preflight();
    shape_assert($receipt['post_remediation_preflight']['ok'],'Remediated preflight must pass');
    $workers = array();
    for ($i=0; $i<2; $i++) {
        $pipes=array(); $process=proc_open(array(PHP_BINARY,__FILE__,'--migration-worker'),
            array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
        shape_assert(is_resource($process),'Independent migration worker started'); fclose($pipes[0]);
        $workers[]=array($process,$pipes);
    }
    $receipt['concurrent_migrations']=array();
    foreach ($workers as [$process,$pipes]) {
        $output=stream_get_contents($pipes[1]);fclose($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[2]);
        $code=proc_close($process);$result=json_decode($output,true);
        shape_assert($code===0 && !empty($result['ok']),'Independent migration succeeds: '.$output.' '.$errors);
        $receipt['concurrent_migrations'][]=$result;
    }
    $receipt['first_migration']=$receipt['concurrent_migrations'][0];
    $after = shape_snapshot();
    foreach ($after[$a]['rows'] as &$row) { shape_assert($row['revision']==='0','No invented revision history'); unset($row['revision']); } unset($row);
    foreach ($after[$audit]['rows'] as &$row) { shape_assert($row['assignment_id']===null && $row['operation_id']===null,'No invented audit identity'); unset($row['assignment_id'],$row['operation_id']); } unset($row);
    foreach ($remediated as $table=>$snapshot) shape_assert($snapshot['rows']===$after[$table]['rows'],'Every old-column row preserved: '.$table);
    $once = shape_snapshot();
    $receipt['second_migration'] = bvmgr_staffing_migrate_lifecycle();
    shape_assert($receipt['second_migration']['ok'] && shape_snapshot()===$once,'Exact idempotence');
    $receipt['post_migration_preflight'] = bvmgr_staffing_lifecycle_preflight();
    shape_assert($receipt['post_migration_preflight']['ok'] && $receipt['post_migration_preflight']['schema']==='ready','Ready final lifecycle schema');
    $receipt['preserved_assignments'] = count($after[$a]['rows']); $receipt['preserved_audit_rows'] = count($after[$audit]['rows']);
    $receipt['migrated_schemas'] = array($a=>$once[$a]['schema'],$audit=>$once[$audit]['schema']);
    $receipt['idempotence'] = true;
    // Disposable-only reversal: no lifecycle actions occurred, so additions contain defaults/nulls only.
    $wpdb->query("ALTER TABLE `$audit` DROP INDEX lifecycle_operation, DROP INDEX lifecycle_assignment, DROP COLUMN assignment_id, DROP COLUMN operation_id");
    $wpdb->query("ALTER TABLE `$a` DROP COLUMN revision");
    shape_assert(shape_snapshot()===$remediated,'Migration schema rollback exact');
    foreach (array($a,$s,$rollup) as $table) {
        $key=array_key_first($before[$table]['rows'][0]);
        $present=array_column($remediated[$table]['rows'], $key);
        foreach ($before[$table]['rows'] as $row) if (!in_array($row[$key],$present,true)) shape_assert($wpdb->insert($table,$row)!==false,'Restore exact removed fixture before-image');
    }
    shape_assert(shape_snapshot()===$before,'Complete original shape restored, audit and auto-increments exact');
    $receipt['transaction_rollback'] = true; $receipt['migration_rollback'] = true; $receipt['exact_original_restored'] = true;
    echo json_encode($receipt, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR), "\n";
} finally { $wpdb=$source; }
