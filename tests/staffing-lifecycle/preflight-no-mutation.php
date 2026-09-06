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
    foreach (array_merge(bvmgr_staffing_transaction_tables(), array($wpdb->options)) as $table) {
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
    foreach (array_merge(bvmgr_staffing_transaction_tables(), array($wpdb->options)) as $target) {
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
    $wpdb->query("ALTER TABLE `$audit` DROP COLUMN operation_id");
    $wpdb->query("ALTER TABLE `$audit` ENGINE=MyISAM");
    gate_blocked('transactional_engine_required');
    $wpdb->query("ALTER TABLE `$audit` ENGINE=InnoDB");
    gate_check(bvmgr_staffing_migrate_lifecycle()['ok'], 'clean explicit migration succeeds');
    $before = gate_snapshot();
    gate_check(bvmgr_staffing_migrate_lifecycle()['ok'] && gate_snapshot() === $before, 'idempotent migration preserves all history/options');
    echo "PASS $checks no-mutation gate assertions\n";
} finally {
    foreach (array_reverse($created) as $table) $wpdb->query("DROP TABLE `$table`");
    $wpdb = $original;
}
