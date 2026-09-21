<?php
/** Exact real-WordPress migration test, evaluated only by the guarded disposable supervisor. */
if (getenv('BVM_DISPOSABLE_DB_GUARDED') !== '1' || DB_NAME !== 'bvm_tranche2'
    || DB_HOST !== 'localhost:' . getenv('BVM_DISPOSABLE_DB_SOCKET') || rtrim(ABSPATH, '/') !== getenv('BVM_TRANCHE2_WORDPRESS_ROOT')) throw new RuntimeException('Disposable supervisor required');

global $wpdb;
wp_set_current_user(1);
$assert = static function ($ok, string $label): void { if (!$ok) throw new RuntimeException($label); };
$config = bvmgr_private_storage_config();
$assert(!is_wp_error($config), 'outside-webroot configuration accepted');
$uploads = wp_upload_dir(null, false);
$current = $uploads['basedir'] . '/backstage-venue-manager/private/site-' . get_current_blog_id();
$write_current = static function (string $key, string $bytes) use ($current): string {
    $path = $current . '/' . $key;
    wp_mkdir_p(dirname($path));
    if (file_put_contents($path, $bytes) !== strlen($bytes)) throw new RuntimeException('current-uploads fixture write failed');
    return $path;
};

// The retained Local 15-object state is reproduced from a task-owned copy, never exercised in place.
$fixture = getenv('BVM_EXISTING_15_FIXTURE');
$manifest_path = getenv('BVM_EXISTING_15_MANIFEST');
$manifest = is_string($manifest_path) && is_file($manifest_path) ? json_decode((string) file_get_contents($manifest_path), true) : null;
$assert(is_string($fixture) && is_dir($fixture) && is_array($manifest) && count($manifest) === 15, 'exact 15-object fixture supplied');
foreach ($manifest as $item) {
    $key = (string) $item['key'];
    $source = $fixture . '/' . basename($key);
    $destination = $config['site'] . '/' . $key;
    wp_mkdir_p(dirname($destination));
    $assert(copy($source, $destination), 'fixture object copied into disposable outside root');
    $assert(hash_file('sha256', $destination) === $item['sha256'] && filesize($destination) === $item['bytes'], 'fixture object exact');
    update_option('bvmgr_private_migration_' . hash('sha256', $key), array('key' => $key, 'sha256' => $item['sha256'], 'bytes' => $item['bytes'], 'state' => 'complete'), false);
}
$writes = array();
$trap = static function ($sql) use (&$writes) { if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP)\b/i', $sql)) $writes[] = $sql; return $sql; };
add_filter('query', $trap, PHP_INT_MAX);
$inventory_15 = bvmgr_private_storage_inventory();
$pending_15 = bvmgr_private_storage_pending();
$again_15 = bvmgr_private_storage_migrate();
foreach ($manifest as $item) $assert(bvmgr_private_storage_resolve($item['key']) === $config['site'] . '/' . $item['key'], 'completed fixture resolves');
remove_filter('query', $trap, PHP_INT_MAX);
$assert(!is_wp_error($inventory_15) && !$inventory_15 && !$pending_15, '15 complete receipts produce zero pending objects');
$assert($again_15['ok'] && $again_15['migrated'] === 0 && $again_15['remaining'] === 0, '15 complete objects require zero physical copies');
$assert(!$writes, '15 complete fixture causes zero metadata writes');

// Success from the short-lived 1.3 uploads tree.
$success_key = 'tax-docs/current-success.pdf';
$success_bytes = "SYNTHETIC CURRENT UPLOAD SUCCESS\n";
$success_source = $write_current($success_key, $success_bytes);
$success = bvmgr_private_storage_migrate();
$assert($success['ok'] && !file_exists($success_source), 'current uploads success removes verified source');
$assert(file_get_contents(bvmgr_private_storage_target($success_key)) === $success_bytes, 'current uploads success preserves bytes');

// Interruption after publication and durable receipt, followed by idempotent retry.
$retry_key = 'tax-docs/current-retry.pdf';
$retry_bytes = "SYNTHETIC CURRENT UPLOAD RETRY\n";
$retry_source = $write_current($retry_key, $retry_bytes);
$block = static function ($path) use ($retry_source) { return $path === $retry_source ? '' : $path; };
add_filter('wp_delete_file', $block);
$interrupted = bvmgr_private_storage_migrate();
remove_filter('wp_delete_file', $block);
$assert(!$interrupted['ok'] && is_file($retry_source), 'interruption retains current uploads source');
$assert(file_get_contents(bvmgr_private_storage_target($retry_key)) === $retry_bytes, 'interruption retains verified destination');
$retried = bvmgr_private_storage_migrate();
$assert($retried['ok'] && !file_exists($retry_source), 'retry completes without republishing');

// Conflicting destination fails closed and remains recoverable.
$conflict_key = 'tax-docs/current-conflict.pdf';
$conflict_bytes = "SYNTHETIC CURRENT UPLOAD CONFLICT\n";
$conflict_source = $write_current($conflict_key, $conflict_bytes);
$conflict_destination = bvmgr_private_storage_target($conflict_key);
wp_mkdir_p(dirname($conflict_destination));
file_put_contents($conflict_destination, 'DIFFERENT');
$conflict = bvmgr_private_storage_migrate();
$assert(!$conflict['ok'] && file_get_contents($conflict_source) === $conflict_bytes && file_get_contents($conflict_destination) === 'DIFFERENT', 'conflict preserves both copies');
wp_delete_file($conflict_destination);
$assert(bvmgr_private_storage_migrate()['ok'] && !file_exists($conflict_source), 'resolved conflict retries safely');

// A persisted digest mismatch cannot publish or remove current uploads bytes.
$mismatch_key = 'tax-docs/current-mismatch.pdf';
$mismatch_bytes = "SYNTHETIC CURRENT UPLOAD DIGEST\n";
$mismatch_source = $write_current($mismatch_key, $mismatch_bytes);
$wpdb->insert(bvmgr_private_files_table(), array('original_filename' => 'Current mismatch.pdf', 'stored_filename' => $mismatch_key, 'mime_type' => 'application/pdf', 'file_size' => strlen($mismatch_bytes), 'sha256' => hash('sha256', 'WRONG'), 'created_at' => current_time('mysql')));
$mismatch_id = (int) $wpdb->insert_id;
$mismatch = bvmgr_private_storage_migrate();
$assert(!$mismatch['ok'] && file_get_contents($mismatch_source) === $mismatch_bytes && !file_exists(bvmgr_private_storage_target($mismatch_key)), 'digest mismatch publishes and deletes nothing');
$wpdb->update(bvmgr_private_files_table(), array('sha256' => hash('sha256', $mismatch_bytes)), array('id' => $mismatch_id));
$assert(bvmgr_private_storage_migrate()['ok'] && !file_exists($mismatch_source), 'corrected digest permits retry');

$assert(!is_dir($current), 'empty BVM-owned current uploads site root cleaned');
$idempotent = bvmgr_private_storage_migrate();
$assert($idempotent['ok'] && $idempotent['migrated'] === 0 && $idempotent['remaining'] === 0, 'completed current uploads migration is idempotent');

$receipt = array(
    'ok' => true,
    'source_family' => 'wp-content/uploads/backstage-venue-manager/private/site-' . get_current_blog_id(),
    'destination' => $config['site'],
    'existing_complete_objects' => count($manifest),
    'existing_complete_db_writes' => count($writes),
    'success' => $success,
    'interrupted' => $interrupted,
    'retry' => $retried,
    'conflict' => $conflict,
    'digest_mismatch' => $mismatch,
    'cleanup' => !is_dir($current),
    'idempotent' => $idempotent,
);
file_put_contents(getenv('BVM_QUAL_EVIDENCE') . '/private-storage-current-uploads-migration.json', wp_json_encode($receipt, JSON_PRETTY_PRINT));
echo "PASS current uploads to outside-webroot success, interruption, retry, conflict, digest mismatch, cleanup, idempotence, and exact 15-object completed fixture\n";
