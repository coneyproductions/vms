<?php
require __DIR__.'/bootstrap.php';
$job=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
if(isset($job['user']))wp_set_current_user($job['user']);
file_put_contents($argv[2],(string)$wpdb->get_var('SELECT CONNECTION_ID()'));
// Parent holds the real server advisory lock until both processes are ready.
add_filter('query',static function($sql){if($sql==='START TRANSACTION')usleep(300000);return $sql;});
$start=microtime(true);
if($job['kind']==='confirm'||$job['kind']==='transition')$result=bvmgr_staffing_transition_assignment($job['id'],$job['target']??'confirmed',$job['options']);
elseif($job['kind']==='matrix')$result=bvmgr_staffing_save_event_roles_matrix($job['plan'],array($job['role']=>1),array($job['role']=>array($job['staff'])),array($job['role']=>'absolute'),array($job['role']=>'12:00'),array($job['role']=>'18:00'));
else $result=bvmgr_staffing_atomic(static function()use($job){bvmgr_staffing_matrix_proposals($job['slot'],array($job['staff']));return array('ok'=>true);});
echo json_encode(array('result'=>$result,'elapsed'=>microtime(true)-$start),JSON_THROW_ON_ERROR)."\n";
