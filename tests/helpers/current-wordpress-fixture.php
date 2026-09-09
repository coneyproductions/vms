<?php
/** Canonical post-migration fixtures, restricted to the supervised disposable database. */
if (getenv('BVM_DISPOSABLE_DB_GUARDED') !== '1') throw new RuntimeException('Disposable supervisor required');
require_once dirname(__DIR__) . '/staffing-lifecycle/bootstrap.php';
$result = bvmgr_staffing_migrate_lifecycle();
if (empty($result['ok'])) {
    throw new RuntimeException('Canonical fixture lifecycle migration failed: ' . wp_json_encode($result));
}
