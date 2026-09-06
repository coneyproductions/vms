<?php
require __DIR__ . '/bootstrap.php';
$checks = 0;
function check($condition, $label) { global $checks; if (!$condition) throw new RuntimeException($label); $checks++; }
function fixture(): array {
    global $wpdb;
    $plan = wp_insert_post(array('post_type'=>'vms_event_plan','post_status'=>'publish','post_title'=>'Disposable lifecycle'));
    update_post_meta($plan,'_vms_event_date','2030-09-06'); update_post_meta($plan,'_vms_start_time','12:00'); update_post_meta($plan,'_vms_end_time','18:00'); update_post_meta($plan,'_vms_event_plan_status','confirmed');
    $staff=wp_insert_post(array('post_type'=>'vms_staff','post_status'=>'publish','post_title'=>'Disposable staff'));
    $term=wp_insert_term('Lifecycle '.wp_generate_uuid4(),'vms_staff_role');$role=(int)$term['term_id'];wp_set_post_terms($staff,array($role),'vms_staff_role');
    $now=bvmgr_staffing_now_mysql_utc();
    $wpdb->insert(bvmgr_staffing_table_name('event_slots'),array('event_plan_id'=>$plan,'role_id'=>$role,'headcount_needed'=>1,'shift_start_local'=>'12:00','shift_end_local'=>'18:00','created_at'=>$now,'updated_at'=>$now));$slot=(int)$wpdb->insert_id;
    $r=bvmgr_staffing_atomic(static function()use($slot,$staff){bvmgr_staffing_matrix_proposals($slot,array($staff));bvmgr_staffing_sync_lifecycle_window($slot);return array('ok'=>true);});
    check($r['ok'],'proposal: '.json_encode($r));
    $id=(int)$wpdb->get_var($wpdb->prepare('SELECT assignment_id FROM %i WHERE slot_id=%d',bvmgr_staffing_table_name('assignments'),$slot));
    return compact('plan','staff','role','slot','id');
}
function options(array $f, array $more=array()): array {
    $row=bvmgr_staffing_lifecycle_row($f['id']);
    return array_merge(array('context'=>'operator','event_plan_id'=>$f['plan'],'revision'=>(int)$row['revision'],'operation_id'=>wp_generate_uuid4(),'reason'=>'Disposable test'),$more);
}
function audits(int $id): int {global $wpdb;return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE assignment_id=%d AND action='assignment_transition'",bvmgr_staffing_table_name('audit'),$id));}
check(bvmgr_staffing_migrate_lifecycle()['ok'],'migration');check(bvmgr_staffing_migrate_lifecycle()['ok'],'migration repeated');
$f=fixture();$op=options($f);$count=audits($f['id']);$r=bvmgr_staffing_transition_assignment($f['id'],'confirmed',$op);check($r['ok'],'confirm '.json_encode($r));check(audits($f['id'])===$count+1,'one transition audit');
$r=bvmgr_staffing_transition_assignment($f['id'],'confirmed',$op);check($r['ok']&&!empty($r['noop'])&&audits($f['id'])===$count+1,'idempotence');
check(!bvmgr_staffing_transition_assignment($f['id'],'declined',options($f))['ok'],'invalid operator decline');
check(bvmgr_staffing_transition_assignment($f['id'],'canceled',options($f))['ok'],'cancel');check(bvmgr_staffing_transition_assignment($f['id'],'proposed',options($f))['ok'],'repropose');
check(!bvmgr_staffing_transition_assignment($f['id'],'confirmed',array_merge($op,array('operation_id'=>wp_generate_uuid4())))['ok'],'old revision');
// Real SQL failures, injected via wpdb's query filter; no fake DB return values.
foreach(array('audit','assignments','rollups') as $kind){
    $before=bvmgr_staffing_lifecycle_row($f['id']);$count=audits($f['id']);$table=bvmgr_staffing_table_name($kind);
    $filter=static function($sql)use($table){if(preg_match('/^(INSERT|UPDATE)/i',trim($sql))&&strpos($sql,'`'.$table.'`')!==false)return 'INVALID SQL FOR ATOMICITY TEST';return $sql;};
    add_filter('query',$filter);$r=bvmgr_staffing_transition_assignment($f['id'],'confirmed',options($f));remove_filter('query',$filter);
    check(!$r['ok']&&bvmgr_staffing_lifecycle_row($f['id'])===$before&&audits($f['id'])===$count,'rollback '.$kind.' '.json_encode($r));
}
// External transaction must remain owned by its caller.
$wpdb->query('START TRANSACTION');$wpdb->query("INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES ('staffing_outer_probe','pending','off')");
$r=bvmgr_staffing_transition_assignment($f['id'],'confirmed',options($f));check(!$r['ok']&&$r['error']==='external_transaction','nested rejection');$wpdb->query('ROLLBACK');check(!$wpdb->get_var("SELECT option_id FROM {$wpdb->options} WHERE option_name='staffing_outer_probe'"),'outer did not commit');


// Staff ownership and real WordPress nonce checks.
$f=fixture();$user=wp_insert_user(array('user_login'=>'staff-'.wp_generate_uuid4(),'user_pass'=>'disposable','role'=>'subscriber'));
update_user_meta($user,'_vms_staff_id',$f['staff']);wp_set_current_user($user);
function request_for(array $f,string $target,string $context='staff'): array {
    $row=bvmgr_staffing_lifecycle_row($f['id']);$rev=(int)$row['revision'];
    return array('assignment_id'=>(string)$f['id'],'event_plan_id'=>(string)$f['plan'],'revision'=>(string)$rev,'target'=>$target,'operation_id'=>wp_generate_uuid4(),
        'nonce'=>wp_create_nonce(bvmgr_staffing_lifecycle_nonce($f['id'],$target,$rev,$context)),'reason'=>'test reason');
}
$req=request_for($f,'confirmed');
check(bvmgr_staffing_handle_lifecycle_request(array_merge($req,array('nonce'=>'wrong')),'staff','POST')['error']==='invalid_nonce','nonce rejection');
check(!bvmgr_staffing_handle_lifecycle_request($req,'staff','GET')['ok'],'GET rejection');
check(!bvmgr_staffing_handle_lifecycle_request($req,'operator','POST')['ok'],'cross-context nonce');
check(bvmgr_staffing_handle_lifecycle_request($req,'staff','POST')['ok'],'staff accepts own proposal');
check(bvmgr_staffing_handle_lifecycle_request($req,'staff','POST')['noop'],'staff acceptance retry');
wp_set_current_user(1);check(bvmgr_staffing_transition_assignment($f['id'],'canceled',options($f))['ok'],'operator cancels staff commitment');check(bvmgr_staffing_transition_assignment($f['id'],'proposed',options($f))['ok'],'operator reproposes');
wp_set_current_user($user);$req=request_for($f,'declined');check(bvmgr_staffing_handle_lifecycle_request($req,'staff','POST')['ok'],'staff decline');
update_user_meta($user,'_vms_staff_id',999999);$count=audits($f['id']);check(!bvmgr_staffing_handle_lifecycle_request($req,'staff','POST')['ok']&&audits($f['id'])===$count,'cross-user retry denied');
wp_set_current_user(1);
// Stale matrix selection must preserve Declined, and cancellation is explicit.
check(bvmgr_staffing_atomic(static function()use($f){bvmgr_staffing_matrix_proposals($f['slot'],array($f['staff']));return array('ok'=>true);})['ok'],'stale checkbox save');
check(bvmgr_staffing_lifecycle_row($f['id'])['status']==='declined','decline survives stale checkbox');
check(bvmgr_staffing_atomic(static function()use($f){bvmgr_staffing_matrix_proposals($f['slot'],array());return array('ok'=>true);})['ok'],'history omission');check(bvmgr_staffing_lifecycle_row($f['id'])['status']==='declined','decline survives unrelated matrix');
// Every legacy duplicate survives additive migration unchanged.
$wpdb->insert(bvmgr_staffing_table_name('assignments'),array('slot_id'=>$f['slot'],'staff_id'=>$f['staff'],'status'=>'canceled','created_at'=>bvmgr_staffing_now_mysql_utc(),'updated_at'=>bvmgr_staffing_now_mysql_utc()));
$before=$wpdb->get_results('SELECT * FROM '.bvmgr_staffing_table_name('assignments').' ORDER BY assignment_id',ARRAY_A);check(bvmgr_staffing_migrate_lifecycle()['ok'],'duplicate migration');check($before===$wpdb->get_results('SELECT * FROM '.bvmgr_staffing_table_name('assignments').' ORDER BY assignment_id',ARRAY_A),'migration preserves duplicate history');
// Fail closed for nontransactional audit storage, then restore only the fixture.
$table=bvmgr_staffing_table_name('audit');$wpdb->query("ALTER TABLE `$table` ENGINE=MyISAM");
$r=bvmgr_staffing_transition_assignment($f['id'],'proposed',options($f));check(!$r['ok']&&$r['error']==='transactional_engine_required','engine precondition');$wpdb->query("ALTER TABLE `$table` ENGINE=InnoDB");
echo "PASS {$checks} real-database assertions\n";
