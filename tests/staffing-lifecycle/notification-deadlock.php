<?php
require __DIR__.'/bootstrap.php';
if(!bvmgr_staffing_migrate_lifecycle()['ok']||!bvmgr_staffing_notify_enable())throw new RuntimeException('Notification deadlock setup');
require __DIR__.'/deadlock.php';
bvmgr_staffing_notify_tick();
$logs=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}vms_notify_log WHERE source='vms_staffing'",ARRAY_A);$work=array();
foreach($logs as $log){$payload=json_decode($log['payload_json'],true);if(($payload['phase']??'')==='work'){$work[$payload['audit_id']]=($work[$payload['audit_id']]??0)+1;if((int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE log_id=%d',bvmgr_staffing_table_name('audit'),$payload['audit_id']))!==1)throw new RuntimeException('Notification without committed audit');}}
if(!$work||max($work)!==1)throw new RuntimeException('Duplicate notification identity after deadlock/retry');
echo 'PASS notification/deadlock: '.count($work)." unique committed audit identities; no rollback notification\n";
