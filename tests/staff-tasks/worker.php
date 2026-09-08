<?php
require dirname(__DIR__).'/staffing-lifecycle/bootstrap.php';
$job=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
wp_set_current_user((int)($job['actor']??1));
if (!empty($job['crash_before_audit'])) add_filter('query',static function($sql){if(str_starts_with($sql,'INSERT INTO `'.bvmgr_tasks_table_name('task_logs').'`')){remove_all_actions('shutdown');exit(25);}return $sql;},PHP_INT_MAX);
add_filter('pre_wp_mail',static function($pre,$mail)use($job){if(!empty($GLOBALS['bvmgr_tasks_transaction']))throw new RuntimeException('mail inside task transaction');file_put_contents($job['mail'],json_encode($mail)."\n",FILE_APPEND|LOCK_EX);if(!empty($job['crash_mail']))exit(23);return true;},PHP_INT_MAX,2);
file_put_contents($job['ready'],'ready');$deadline=microtime(true)+15;while(!is_file($job['go'])&&microtime(true)<$deadline)usleep(10000);if(!is_file($job['go']))exit(26);
if($job['kind']==='command')$r=bvmgr_tasks_command($job['action'],$job['id'],$job['input'],$job['revision']??null,$job['operation']);
elseif($job['kind']==='generate')$r=bvmgr_tasks_generate_for_event($job['plan']);
elseif($job['kind']==='reconcile')$r=bvmgr_tasks_reconcile_event($job['plan']);
else{bvmgr_tasks_delivery_tick($job['scan']??'');$r=array('tick'=>true);}
echo json_encode(is_wp_error($r)?array('ok'=>false,'error'=>$r->get_error_code()):array('ok'=>true,'result'=>$r));
