<?php
require __DIR__.'/runtime.php';
$f=fixture();
function staffing_test_deadlock_count(): int {
    global $wpdb;
    $count=$wpdb->get_var("SHOW GLOBAL STATUS LIKE 'Innodb_deadlocks'",1);
    // MariaDB exposes a status variable; MySQL 8.0 exposes the InnoDB metric.
    if($count===null)$count=$wpdb->get_var("SELECT COUNT FROM information_schema.INNODB_METRICS WHERE NAME='lock_deadlocks'");
    check($count!==null&&ctype_digit((string)$count),'server exposes an actual deadlock counter');
    return (int)$count;
}
$deadlocks_before=staffing_test_deadlock_count();
for($i=0;$i<100;$i++)$wpdb->query($wpdb->prepare('INSERT INTO %i (option_name,option_value,autoload) VALUES (%s,%s,%s) ON DUPLICATE KEY UPDATE option_value=VALUES(option_value)',$wpdb->options,'staffing_deadlock_probe_'.$i,'ready','off'));
$dir=sys_get_temp_dir().'/bvm-staffing-deadlock-'.bin2hex(random_bytes(5));mkdir($dir,0700);
$job=array('plan'=>$f['plan'],'id'=>$f['id'],'ready'=>$dir.'/ready','go'=>$dir.'/go');file_put_contents($dir.'/job',json_encode($job));
$process=null;$pipes=array();
try {
$process=proc_open(array(PHP_BINARY,__DIR__.'/deadlock-worker.php',$dir.'/job'),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes);fclose($pipes[0]);
$deadline=microtime(true)+10;while(!is_file($job['ready'])&&microtime(true)<$deadline)usleep(10000);check(is_file($job['ready']),'external worker holds real row lock');
$table=bvmgr_staffing_table_name('rollups');$signal=static function($sql)use($table,$job){if(strpos($sql,'INSERT INTO `'.$table.'`')===0)file_put_contents($job['go'],'go');return $sql;};
$before=bvmgr_staffing_lifecycle_row($f['id']);$count=audits($f['id']);$operation=options($f);add_filter('query',$signal);
$r=bvmgr_staffing_transition_assignment($f['id'],'confirmed',$operation);remove_filter('query',$signal);
$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($process)===0,'deadlock worker exits '.$out.$err);
check(staffing_test_deadlock_count()>$deadlocks_before,'server reports a real InnoDB deadlock');
check(!$r['ok']&&$r['error']==='database_error','real InnoDB deadlock victim fails closed '.json_encode($r));
check($before===bvmgr_staffing_lifecycle_row($f['id'])&&audits($f['id'])===$count,'deadlock rolls back state and lifecycle audit');
$r=bvmgr_staffing_transition_assignment($f['id'],'confirmed',$operation);check($r['ok']&&audits($f['id'])===$count+1,'same logical operation retries safely after deadlock');
echo 'PASS real deadlock and explicit retry; '.$checks." database assertions\n";
} finally {
    if(is_resource($process))proc_terminate($process);
    foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);
    if(is_resource($process))proc_close($process);
    foreach(glob($dir.'/*') as $file)unlink($file);
    rmdir($dir);
}
