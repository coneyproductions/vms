<?php
/** Representative Wave4 schema in a separate prefix inside the allowlisted disposable DB. */
require __DIR__ . '/bootstrap.php';
$source_db = $wpdb;
$old_tables = array();
foreach (array('assignments', 'event_slots', 'audit', 'rollups') as $kind) $old_tables[$kind] = bvmgr_staffing_table_name($kind);
$old_tables['posts'] = $wpdb->posts;
$old_tables['postmeta'] = $wpdb->postmeta;
$old_tables['terms'] = $wpdb->terms;
$old_tables['term_taxonomy'] = $wpdb->term_taxonomy;
$wpdb = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
$wpdb->set_prefix('bvm_migration_receipt_');
$receipt = array('database' => DB_NAME, 'prefix' => $wpdb->prefix, 'source' => 'Wave4 schema reconstructed by removing only additive lifecycle columns from fresh disposable clones');
$created = array();
try {
    foreach ($old_tables as $kind => $source) {
        $target = in_array($kind, array('posts', 'postmeta', 'terms', 'term_taxonomy'), true) ? $wpdb->$kind : bvmgr_staffing_table_name($kind);
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $target))) throw new RuntimeException('Receipt prefix already exists; preserve it');
        if ($wpdb->query($wpdb->prepare('CREATE TABLE %i LIKE %i', $target, $source)) === false) throw new RuntimeException('Clone schema failed');
        $created[] = $target;
        if ($wpdb->query($wpdb->prepare('INSERT INTO %i SELECT * FROM %i', $target, $source)) === false) throw new RuntimeException('Clone fixture rows failed');
    }
    $assignments = bvmgr_staffing_table_name('assignments');
    $audit = bvmgr_staffing_table_name('audit');
    $wpdb->query($wpdb->prepare('ALTER TABLE %i DROP COLUMN revision', $assignments));
    $wpdb->query($wpdb->prepare('ALTER TABLE %i DROP INDEX lifecycle_operation, DROP INDEX lifecycle_assignment, DROP COLUMN assignment_id, DROP COLUMN operation_id', $audit));
    $before_rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY assignment_id', $assignments), ARRAY_A);
    $before_audit = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY log_id', $audit), ARRAY_A);
    $receipt['before'] = bvmgr_staffing_lifecycle_preflight();
    if ($receipt['before']['schema'] === 'ready' || !$receipt['before']['duplicate_pairs']) throw new RuntimeException('Representative pre-migration/duplicate fixture missing');
    $receipt['first_migration'] = bvmgr_staffing_migrate_lifecycle();
    if (empty($receipt['first_migration']['ok'])) throw new RuntimeException('Migration failed');
    $after_rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY assignment_id', $assignments), ARRAY_A);
    foreach ($after_rows as &$row) { if ((int) $row['revision'] !== 0) throw new RuntimeException('Unexpected revision backfill'); unset($row['revision']); } unset($row);
    $after_audit = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY log_id', $audit), ARRAY_A);
    foreach ($after_audit as &$row) { if ($row['assignment_id'] !== null || $row['operation_id'] !== null) throw new RuntimeException('Fabricated historic audit identity'); unset($row['assignment_id'], $row['operation_id']); } unset($row);
    if ($before_rows !== $after_rows || $before_audit !== $after_audit) throw new RuntimeException('History changed');
    $schemas = array();
    foreach (array($assignments, $audit) as $table) $schemas[$table] = $wpdb->get_row($wpdb->prepare('SHOW CREATE TABLE %i', $table), ARRAY_N)[1];
    $receipt['second_migration'] = bvmgr_staffing_migrate_lifecycle();
    foreach ($schemas as $table => $ddl) if ($ddl !== $wpdb->get_row($wpdb->prepare('SHOW CREATE TABLE %i', $table), ARRAY_N)[1]) throw new RuntimeException('Non-idempotent schema');
    $receipt['after'] = bvmgr_staffing_lifecycle_preflight();
    if (empty($receipt['second_migration']['ok']) || $receipt['after']['schema'] !== 'ready' || $receipt['before']['duplicate_pairs'] !== $receipt['after']['duplicate_pairs']) throw new RuntimeException('Invalid final receipt');
    $receipt['schemas'] = $schemas;
    $receipt['preserved_assignment_count'] = count($before_rows);
    $receipt['preserved_audit_count'] = count($before_audit);
    $receipt['history_preserved'] = true;
    $receipt['idempotent'] = true;
    echo json_encode($receipt, JSON_PRETTY_PRINT) . "\n";
} finally {
    // These are newly created disposable clones, never original rollback artifacts.
    foreach (array_reverse($created) as $table) $wpdb->query($wpdb->prepare('DROP TABLE %i', $table));
    $wpdb = $source_db;
}
