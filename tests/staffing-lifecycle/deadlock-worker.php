<?php
require __DIR__.'/bootstrap.php';
$job=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
$wpdb->query('START TRANSACTION');
try {
    $wpdb->query("UPDATE {$wpdb->options} SET option_value='locked' WHERE option_name LIKE 'staffing_deadlock_probe_%'");
    $wpdb->query($wpdb->prepare('UPDATE %i SET dirty=1 WHERE event_plan_id=%d',bvmgr_staffing_table_name('rollups'),$job['plan']));
    file_put_contents($job['ready'],'ready');
    $deadline=microtime(true)+10;while(!is_file($job['go'])&&microtime(true)<$deadline)usleep(10000);
    if(!is_file($job['go']))throw new RuntimeException('deadlock barrier timeout');
    // A deliberately non-cooperating external writer exercises InnoDB's victim handling.
    $wpdb->query($wpdb->prepare('UPDATE %i SET updated_by=1 WHERE assignment_id=%d',bvmgr_staffing_table_name('assignments'),$job['id']));
} finally {$wpdb->query('ROLLBACK');}
