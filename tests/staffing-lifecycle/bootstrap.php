<?php
/** Hard-bound disposable runtime, never auto-discover a normal Local wp-load.php. */
ini_set('memory_limit', '512M');
$root = getenv('BVM_STAFFING_TEST_ROOT');
if ($root !== '/private/tmp/bvm-staffing-db/wordpress' || !is_file($root . '/wp-config.php')) throw new RuntimeException('Explicit disposable root required');
require_once $root . '/wp-load.php';
if (DB_HOST !== 'localhost:/private/tmp/bvm-staffing-db/mysql.sock' || DB_NAME !== 'staffing_test') throw new RuntimeException('Wrong database');
wp_set_current_user(1);
$repo = dirname(__DIR__, 2);
require_once $repo . '/includes/db/migrations.php';
if (getenv('BVM_STAFFING_INSTALL') === '1') bvmgr_db_migrate_vendor_core_v7();
require_once $repo . '/backstage-venue-manager.php';
require_once $repo . '/includes/core/staffing-lifecycle.php';
require_once $repo . '/includes/db/staffing-lifecycle.php';
if (!post_type_exists('vms_event_plan')) register_post_type('vms_event_plan', array('public' => false));
if (!post_type_exists('vms_staff')) register_post_type('vms_staff', array('public' => false));
if (!taxonomy_exists('vms_staff_role')) register_taxonomy('vms_staff_role', 'vms_staff');
