<?php
/** Destructive tests only in newly created tables inside the hard-bound fixture. */
require __DIR__ . '/bootstrap.php';
$original = $wpdb;
$wpdb = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
$wpdb->set_prefix('bvm_orphan_gate_');
$created = array();
$checks = 0;
function gate_check($condition, string $label): void { global $checks; if (!$condition) throw new RuntimeException($label); $checks++; }
function gate_snapshot(): array {
    global $wpdb;
    $snapshot = array();
    foreach (array_merge(bvmgr_staffing_transaction_tables(), array($wpdb->terms, $wpdb->term_taxonomy, $wpdb->options)) as $table) {
        $snapshot[$table] = array('schema' => $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N)[1],
            'rows' => $wpdb->get_results("SELECT * FROM `$table` ORDER BY 1", ARRAY_A));
    }
    return $snapshot;
}
function gate_blocked(string $blocker): void {
    global $wpdb;
    $before = gate_snapshot();
    if ($blocker !== 'database_error') {
        $pipes = array();
        $process = proc_open(array(PHP_BINARY, dirname(__DIR__, 2) . '/scripts/staffing-lifecycle-preflight.php',
            '--socket=' . substr(DB_HOST, strlen('localhost:')), '--database=' . DB_NAME, '--prefix=' . $wpdb->prefix, '--user=' . DB_USER),
            array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')), $pipes, null, array_merge(getenv(),array('MYSQL_PWD'=>DB_PASSWORD)));
        gate_check(is_resource($process), 'standalone gate launched');
        fclose($pipes[0]); $json=stream_get_contents($pipes[1]); fclose($pipes[1]); $errors=stream_get_contents($pipes[2]); fclose($pipes[2]);
        $code=proc_close($process); $cli=json_decode($json,true);
        gate_check($code===1 && is_array($cli) && !$cli['ok'] && in_array($blocker,$cli['blockers'],true), 'CLI nonzero blocked receipt: '.$blocker.' '.$errors);
    }
    $queries = array();
    $capture = static function ($sql) use (&$queries) { $queries[] = $sql; return $sql; };
    add_filter('query', $capture);
    try {
        $receipt = bvmgr_staffing_lifecycle_preflight();
        gate_check(!$receipt['ok'] && in_array($blocker, $receipt['blockers'], true), 'blocked preflight: ' . $blocker);
        $result = bvmgr_staffing_migrate_lifecycle();
        gate_check(!$result['ok'] && $result['error'] === 'preflight_blocked', 'direct migration refuses: ' . $blocker);
    } finally { remove_filter('query', $capture); }
    gate_check($before === gate_snapshot(), 'zero schema/row/option/auto-increment mutation: ' . $blocker);
    gate_check(!array_filter($queries, static function ($sql) { return preg_match('/^\s*(ALTER|CREATE|DROP|TRUNCATE|RENAME|INSERT|UPDATE|DELETE|REPLACE)\b/i', $sql); }), 'zero mutation statements: ' . $blocker);
}
try {
    foreach (array_merge(bvmgr_staffing_transaction_tables(), array($wpdb->terms, $wpdb->term_taxonomy, $wpdb->options)) as $target) {
        gate_check(!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $target)), 'new disposable table');
        $source = $original->prefix . substr($target, strlen($wpdb->prefix));
        gate_check($wpdb->query("CREATE TABLE `$target` LIKE `$source`") !== false, 'clone schema');
        $created[] = $target;
    }
    $a = bvmgr_staffing_table_name('assignments'); $s = bvmgr_staffing_table_name('event_slots'); $audit = bvmgr_staffing_table_name('audit');
    // Always exercise the original pre-additive shape, regardless of fixture install state.
    if (in_array('revision', $wpdb->get_col("SHOW COLUMNS FROM `$a`"), true)) $wpdb->query("ALTER TABLE `$a` DROP COLUMN revision");
    foreach (array('lifecycle_operation','lifecycle_assignment') as $index) if (in_array($index,array_column($wpdb->get_results("SHOW INDEX FROM `$audit`",ARRAY_A),'Key_name'),true)) $wpdb->query("ALTER TABLE `$audit` DROP INDEX `$index`");
    foreach (array('assignment_id','operation_id') as $column) if (in_array($column,$wpdb->get_col("SHOW COLUMNS FROM `$audit`"),true)) $wpdb->query("ALTER TABLE `$audit` DROP COLUMN `$column`");
    $now = '2026-06-18 04:49:09';
    $wpdb->insert($a,array('assignment_id'=>19,'slot_id'=>3422,'staff_id'=>4588,'status'=>'proposed','created_at'=>$now,'updated_at'=>$now));
    $wpdb->insert($wpdb->options,array('option_name'=>'staffing_migration_version_canary','option_value'=>'before','autoload'=>'off'));
    gate_blocked('orphan_assignments'); // Missing slot.
    $wpdb->insert($s,array('slot_id'=>3422,'event_plan_id'=>4587,'role_id'=>89,'created_at'=>$now,'updated_at'=>$now));
    gate_blocked('orphan_assignments'); // Present slot, missing event and staff: original normal-local failure.
    foreach (array(4587=>'vms_event_plan',4588=>'vms_staff') as $id=>$type) $wpdb->insert($wpdb->posts,array('ID'=>$id,'post_type'=>$type,'post_title'=>'Disposable orphan gate','post_content'=>'','post_excerpt'=>'','to_ping'=>'','pinged'=>'','post_content_filtered'=>''));
    $wpdb->delete($wpdb->posts,array('ID'=>4588));
    gate_blocked('orphan_assignments'); // Missing staff alone.
    $wpdb->insert($wpdb->posts,array('ID'=>4588,'post_type'=>'vms_staff','post_content'=>'','post_excerpt'=>'','to_ping'=>'','pinged'=>'','post_content_filtered'=>''));
    $wpdb->delete($wpdb->posts,array('ID'=>4587));
    gate_blocked('orphan_assignments'); // Missing event alone.
    $wpdb->insert($wpdb->posts,array('ID'=>4587,'post_type'=>'vms_event_plan','post_content'=>'','post_excerpt'=>'','to_ping'=>'','pinged'=>'','post_content_filtered'=>''));
    $wpdb->insert($a,array('slot_id'=>3422,'staff_id'=>4588,'status'=>'proposed','created_at'=>$now,'updated_at'=>$now));
    gate_blocked('duplicate_active_assignments');
    $wpdb->insert($wpdb->terms,array('term_id'=>89,'name'=>'Gate role','slug'=>'gate-role'));
    $wpdb->insert($wpdb->term_taxonomy,array('term_id'=>89,'taxonomy'=>'vms_staff_role','description'=>''));
    $wpdb->query("UPDATE `$a` SET status='canceled' WHERE assignment_id<>19");
    gate_check(bvmgr_staffing_lifecycle_preflight()['ok'], 'inactive duplicate history allowed');
    $wpdb->query("UPDATE `$a` SET status='confirmed',shift_start_ts=0,shift_end_ts=0 WHERE assignment_id=19");
    gate_blocked('invalid_confirmed_windows');
    $wpdb->query("UPDATE `$a` SET status='proposed' WHERE assignment_id=19");
    $wpdb->query("ALTER TABLE `$audit` ADD COLUMN operation_id VARCHAR(64) NULL, ADD KEY lifecycle_operation(operation_id)");
    gate_blocked('incompatible_lifecycle_schema');
    $wpdb->query("ALTER TABLE `$audit` DROP INDEX lifecycle_operation, DROP COLUMN operation_id");
    $failure = static function ($sql) use ($a) { return strpos($sql,"FROM `$a` GROUP BY") !== false ? 'SELECT missing_preflight_column FROM `'.$a.'`' : $sql; };
    add_filter('query',$failure);
    try { gate_blocked('database_error'); } finally { remove_filter('query',$failure); }
    $wpdb->query("ALTER TABLE `$audit` ADD COLUMN operation_id VARCHAR(64) NULL");
    foreach (array(1,2) as $n) $wpdb->insert($audit,array('action'=>'disposable_duplicate_operation','created_at'=>$now,'operation_id'=>'duplicate'));
    gate_blocked('duplicate_audit_operations');
    $wpdb->query("DELETE FROM `$audit` WHERE action='disposable_duplicate_operation'");
    $wpdb->insert($audit,array('action'=>'disposable_invalid_operation','created_at'=>$now,'operation_id'=>'bad'));
    gate_blocked('invalid_audit_operation_ids');
    $wpdb->query("DELETE FROM `$audit` WHERE action='disposable_invalid_operation'");
    $wpdb->query("ALTER TABLE `$audit` DROP COLUMN operation_id");
    $wpdb->query("ALTER TABLE `$audit` ENGINE=MyISAM");
    gate_blocked('transactional_engine_required');
    $wpdb->query("ALTER TABLE `$audit` ENGINE=InnoDB");
    // Population blockers are exercised without an assignment pointing at them.
    $wpdb->delete($a,array('assignment_id'=>19));
    $wpdb->query("DELETE FROM `$a`");
    $wpdb->delete($wpdb->posts,array('ID'=>4587));
    gate_blocked('orphan_slots');
    $wpdb->insert($wpdb->posts,array('ID'=>4587,'post_type'=>'post','post_content'=>'','post_excerpt'=>'','to_ping'=>'','pinged'=>'','post_content_filtered'=>''));
    gate_blocked('orphan_slots'); // Wrong post type is not a recoverable implicit mapping.
    $wpdb->update($wpdb->posts,array('post_type'=>'vms_event_plan'),array('ID'=>4587));
    $wpdb->update($s,array('role_id'=>90),array('slot_id'=>3422));
    gate_blocked('invalid_slot_roles');
    $wpdb->update($s,array('role_id'=>89,'status'=>'unknown'),array('slot_id'=>3422));
    gate_blocked('invalid_slot_values');
    $wpdb->update($s,array('status'=>'active','headcount_needed'=>-1),array('slot_id'=>3422));
    gate_blocked('invalid_slot_values');
    $wpdb->update($s,array('headcount_needed'=>1),array('slot_id'=>3422));
    $wpdb->insert($a,array('assignment_id'=>19,'slot_id'=>3422,'staff_id'=>4588,'status'=>'completed','created_at'=>$now,'updated_at'=>$now));
    gate_blocked('invalid_assignment_values');
    $wpdb->update($a,array('status'=>'proposed'),array('assignment_id'=>19));
    $wpdb->update($s,array('status'=>'canceled'),array('slot_id'=>3422));
    gate_blocked('active_assignments_on_inactive_slots');
    $wpdb->update($s,array('status'=>'active'),array('slot_id'=>3422));
    $r = bvmgr_staffing_table_name('rollups');
    $wpdb->insert($r,array('event_plan_id'=>999,'dirty'=>1));
    gate_blocked('invalid_rollups'); // No slots and no event.
    $wpdb->update($r,array('event_plan_id'=>4587,'dirty'=>0),array('event_plan_id'=>999));
    gate_blocked('invalid_rollup_values'); // Clean cache cannot have no calculation identity.
    $wpdb->update($r,array('dirty'=>1,'headcount_filled_total'=>-1),array('event_plan_id'=>4587));
    gate_blocked('invalid_rollup_values');
    $wpdb->update($r,array('headcount_filled_total'=>0),array('event_plan_id'=>4587));
    $wpdb->query("ALTER TABLE `$r` DROP PRIMARY KEY");
    $wpdb->query("INSERT INTO `$r` SELECT * FROM `$r`");
    gate_blocked('duplicate_rollups');
    $wpdb->query("DELETE FROM `$r`");
    $wpdb->query("ALTER TABLE `$r` ADD PRIMARY KEY(event_plan_id)");
    $wpdb->query("ALTER TABLE `$a` DROP COLUMN notes");
    gate_blocked('incompatible_base_schema');
    $wpdb->query("ALTER TABLE `$a` ADD COLUMN notes TEXT NULL");
    $wpdb->query("ALTER TABLE `$a` MODIFY assignment_id BIGINT UNSIGNED NOT NULL");
    gate_blocked('incompatible_base_schema');
    $wpdb->query("ALTER TABLE `$a` MODIFY assignment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
    $wpdb->query("ALTER TABLE `$a` ADD COLUMN revision BIGINT NULL");
    $wpdb->query("UPDATE `$a` SET revision=-1");
    gate_blocked('invalid_revisions');
    $wpdb->query("ALTER TABLE `$a` DROP COLUMN revision");
    $wpdb->update($a,array('status'=>'confirmed','shift_start_ts'=>1900000000,'shift_end_ts'=>1900003600),array('assignment_id'=>19));
    $wpdb->insert($s,array('slot_id'=>3423,'event_plan_id'=>4587,'role_id'=>89,'created_at'=>$now,'updated_at'=>$now));
    $wpdb->insert($a,array('assignment_id'=>20,'slot_id'=>3423,'staff_id'=>4588,'status'=>'confirmed','shift_start_ts'=>1900001800,'shift_end_ts'=>1900005400,'created_at'=>$now,'updated_at'=>$now));
    gate_blocked('overlapping_confirmations');
    $wpdb->delete($a,array('assignment_id'=>20));
    $wpdb->delete($s,array('slot_id'=>3423));
    // A trashed plan still exists; canceled slots and terminal duplicate history
    // remain explicit supported state. Multiple shifts per role are also legal.
    $wpdb->update($wpdb->posts,array('post_status'=>'trash'),array('ID'=>4587));
    $wpdb->update($a,array('status'=>'canceled'),array('assignment_id'=>19));
    $wpdb->update($s,array('status'=>'canceled'),array('slot_id'=>3422));
    $wpdb->insert($s,array('event_plan_id'=>4587,'role_id'=>89,'created_at'=>$now,'updated_at'=>$now));
    $receipt=bvmgr_staffing_lifecycle_preflight();
    gate_check($receipt['ok'] && $receipt['counts']['trashed_plan_slots']===2 && $receipt['counts']['multiple_role_slots']===1, 'supported trash, canceled history and multiple shifts');
    gate_check(bvmgr_staffing_migrate_lifecycle()['ok'], 'clean explicit migration succeeds');
    $before = gate_snapshot();
    gate_check(bvmgr_staffing_migrate_lifecycle()['ok'] && gate_snapshot() === $before, 'idempotent migration preserves all history/options');
    echo "PASS $checks no-mutation gate assertions\n";
} finally {
    foreach (array_reverse($created) as $table) $wpdb->query("DROP TABLE `$table`");
    $wpdb = $original;
}
