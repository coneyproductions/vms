<?php
require __DIR__.'/bootstrap.php';
$job=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
add_filter('pre_wp_mail',static function($pre,$mail)use($job){
 if(!str_starts_with($mail['subject'],'Staffing update:'))return false;
 if(bvmgr_staffing_transaction_active())throw new RuntimeException('mail inside transaction');
 file_put_contents($job['mail_log'],json_encode($mail)."\n",FILE_APPEND|LOCK_EX);
 if(!empty($job['crash']))exit(23);
 return true;
},PHP_INT_MAX,2);
file_put_contents($argv[2],(string)$wpdb->get_var('SELECT CONNECTION_ID()'));
$deadline=microtime(true)+10;while(!is_file($job['go'])&&microtime(true)<$deadline)usleep(10000);
if(!is_file($job['go']))exit(24);
if($job['kind']==='transition')$r=bvmgr_staffing_transition_assignment($job['id'],$job['target'],$job['options']);else{$r=array('ok'=>true);bvmgr_staffing_notify_tick();}
echo json_encode($r);
