<?php
require __DIR__.'/runtime.php';
require_once dirname(__DIR__,2).'/includes/admin/event-command-center.php';
$other=new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);$other->suppress_errors(true);
$assignment_table=bvmgr_staffing_table_name('assignments');$audit_table=bvmgr_staffing_table_name('audit');
// A second connection commits between two reads in the same managed transaction.
$wpdb->query("INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES ('staffing_isolation','first','off') ON DUPLICATE KEY UPDATE option_value='first'");
$isolation_observed=array();
$r=bvmgr_staffing_atomic(static function()use($other,&$isolation_observed){global $wpdb;$a=$wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='staffing_isolation'");$other->query("UPDATE {$wpdb->options} SET option_value='second' WHERE option_name='staffing_isolation'");$b=$wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='staffing_isolation'");$isolation_observed=array($a,$b);return array('ok'=>true);});check($r['ok'],'isolation probe commit');
check($isolation_observed===array('first','second'),'READ COMMITTED visibility within one transaction: '.json_encode($isolation_observed));
$f=fixture();$events=array();
$observe=static function($event)use(&$events,$other,$assignment_table,$audit_table){
    $events[]=array($event,bvmgr_staffing_transaction_active(),$other->get_var($other->prepare('SELECT status FROM %i WHERE assignment_id=%d',$assignment_table,$event['assignment_id'])),(int)$other->get_var($other->prepare('SELECT COUNT(*) FROM %i WHERE operation_id=%s',$audit_table,$event['operation_id'])));
};add_action('vms_staffing_assignment_transitioned',$observe);
$op=options($f,array('reason'=>'Reason checked'));$r=bvmgr_staffing_transition_assignment($f['id'],'confirmed',$op);check($r['ok']&&count($events)===1&&$events[0][1]===false&&$events[0][2]==='confirmed'&&$events[0][3]===1,'event observes committed state/audit on independent connection');
$payload=$events[0][0];check($payload['actor_user_id']===1&&$payload['actor_context']==='operator'&&$payload['previous_status']==='proposed'&&$payload['status']==='confirmed'&&$payload['reason']==='Reason checked'&&abs(strtotime($payload['created_at'].' UTC')-time())<5,'audit actor reason statuses UTC timestamp');
$r=bvmgr_staffing_transition_assignment($f['id'],'confirmed',$op);check($r['noop']&&count($events)===1,'retry emits no second event');
check(!isset(bvmgr_staffing_lifecycle_public_result($r)['assignment']['pay_rate_override']),'public response excludes private assignment fields');
$snapshot=bvmgr_staffing_resolve_event_snapshot($f['plan']);$ecc=bvmgr_event_command_center_get_staffing_snapshot($f['plan']);
foreach(array('assigned_headcount','proposed_headcount','confirmed_headcount','open_headcount_total','planned_headcount') as $key)check($snapshot[$key]===$ecc[$key],'shared ECC field '.$key);
check($snapshot['assigned_headcount']===1&&$snapshot['confirmed_headcount']===1&&$snapshot['proposed_headcount']===0,'confirmed shared counts');
$portal=bvmgr_staff_portal_get_assignment_rows($f['staff']);check(count($portal)===1&&$portal[0]['assignment_status']==='confirmed','portal sees same committed assignment');
$dirty=bvmgr_staffing_get_rollup($f['plan']);check(!empty($dirty['dirty']),'reader preserves dirty marker while returning current truth');
check(bvmgr_staffing_compute_rollup($f['plan'])['ok'],'explicit maintenance rebuild succeeds');
check(empty(bvmgr_staffing_get_rollup($f['plan'])['dirty']),'explicit rebuild clears dirty marker');
check(bvmgr_staffing_transition_assignment($f['id'],'canceled',options($f))['ok'],'integration cancel');$dirty=bvmgr_staffing_get_rollup($f['plan']);check(!empty($dirty['dirty']),'cancellation dirties rollup atomically');
$snapshot=bvmgr_staffing_resolve_event_snapshot($f['plan']);check($snapshot['assigned_headcount']===0&&$snapshot['proposed_headcount']===0&&$snapshot['confirmed_headcount']===0,'inactive normalized history contributes no coverage');check(bvmgr_staff_portal_get_assignment_rows($f['staff'])===array(),'inactive assignment does not return as upcoming shift');
remove_action('vms_staffing_assignment_transitioned',$observe);
// All status combinations not in the state machine fail without changing identity.
check(bvmgr_staffing_transition_assignment($f['id'],'completed',options($f))['error']==='forbidden','unknown target fails closed');
check(bvmgr_staffing_transition_assignment($f['id'],'proposed',options($f,array('reason'=>'')))['error']==='reason_required','deliberate reproposal reason');
check(bvmgr_staffing_transition_assignment($f['id'],'proposed',options($f))['ok'],'integration repropose');
check(bvmgr_staffing_transition_assignment($f['id'],'confirmed',options($f,array('event_plan_id'=>$f['plan']+1)))['error']==='forbidden','cross-plan context');
$user=wp_insert_user(array('user_login'=>'denied-'.wp_generate_uuid4(),'user_pass'=>'disposable','role'=>'subscriber'));wp_set_current_user($user);
check(bvmgr_staffing_transition_assignment($f['id'],'confirmed',options($f))['error']==='forbidden','operator capability denied');check(bvmgr_staffing_transition_assignment($f['id'],'confirmed',options($f,array('context'=>'staff')))['error']==='forbidden','unlinked staff denied');wp_set_current_user(1);
$before=bvmgr_staffing_lifecycle_row($f['id']);$n=audits($f['id']);
$other->get_var($other->prepare('SELECT GET_LOCK(%s,0)',bvmgr_staffing_lock_name()));$r=bvmgr_staffing_transition_assignment($f['id'],'confirmed',options($f));$other->get_var($other->prepare('SELECT RELEASE_LOCK(%s)',bvmgr_staffing_lock_name()));
check(!$r['ok']&&$r['error']==='staffing_busy'&&$before===bvmgr_staffing_lifecycle_row($f['id'])&&audits($f['id'])===$n,'lock timeout does not mutate');
$r=bvmgr_staffing_atomic(static function(){global $wpdb;$wpdb->query('COMMIT');return array('ok'=>true);});check(!$r['ok']&&$r['error']==='nested_transaction_control','accidental nested COMMIT blocked');
// Kill the managed connection during audit insertion: no wpdb replay or partial state.
$kill=static function($sql)use($other,$audit_table){global $wpdb;if(strpos($sql,'INSERT INTO `'.$audit_table.'`')===0){$connection=(int)$wpdb->get_var('SELECT CONNECTION_ID()');$other->query('KILL CONNECTION '.$connection);}return $sql;};
add_filter('query',$kill);$r=bvmgr_staffing_transition_assignment($f['id'],'confirmed',options($f));remove_filter('query',$kill);
check(!$r['ok']&&$r['error']==='database_error','lost connection rejected with no replay');check($before===bvmgr_staffing_lifecycle_row($f['id'])&&audits($f['id'])===$n,'lost connection rolls back row and audit');
check((int)$other->get_var($other->prepare('SELECT GET_LOCK(%s,0)',bvmgr_staffing_lock_name()))===1,'connection loss releases server lock');$other->get_var($other->prepare('SELECT RELEASE_LOCK(%s)',bvmgr_staffing_lock_name()));
// Adjacent windows are legal; moving an already confirmed slot into overlap rolls back.
$f=fixture();check(bvmgr_staffing_transition_assignment($f['id'],'confirmed',options($f))['ok'],'time test confirmation');
$other_plan=wp_insert_post(array('post_type'=>'vms_event_plan','post_status'=>'publish','post_title'=>'Adjacent fixture'));
update_post_meta($other_plan,'_vms_event_date','2030-09-06');update_post_meta($other_plan,'_vms_start_time','18:00');update_post_meta($other_plan,'_vms_end_time','20:00');update_post_meta($other_plan,'_vms_event_plan_status','confirmed');
$wpdb->insert(bvmgr_staffing_table_name('event_slots'),array('event_plan_id'=>$other_plan,'role_id'=>$f['role'],'headcount_needed'=>1,'shift_time_mode'=>'relative','start_anchor_key'=>'event_start','end_anchor_key'=>'event_end','created_at'=>bvmgr_staffing_now_mysql_utc(),'updated_at'=>bvmgr_staffing_now_mysql_utc()));$slot=(int)$wpdb->insert_id;
check(bvmgr_staffing_atomic(static function()use($slot,$f){bvmgr_staffing_matrix_proposals($slot,array($f['staff']));bvmgr_staffing_sync_lifecycle_window($slot);return array('ok'=>true);})['ok'],'adjacent proposal');
$id=(int)$wpdb->get_var($wpdb->prepare('SELECT assignment_id FROM %i WHERE slot_id=%d',$assignment_table,$slot));$g=array_merge($f,array('plan'=>$other_plan,'slot'=>$slot,'id'=>$id));
check(bvmgr_staffing_transition_assignment($id,'confirmed',options($g))['ok'],'half-open adjacent confirmed windows allowed');$before=bvmgr_staffing_lifecycle_row($id);
check(update_post_meta($other_plan,'_vms_start_time','17:00')===false,'time meta conflict rejected');check(get_post_meta($other_plan,'_vms_start_time',true)==='18:00'&&$before===bvmgr_staffing_lifecycle_row($id),'time meta and assignment revision rolled back');
$meta=get_metadata_raw('post',$other_plan,'_vms_start_time');$mid=(int)$wpdb->get_var($wpdb->prepare("SELECT meta_id FROM %i WHERE post_id=%d AND meta_key='_vms_start_time'",$wpdb->postmeta,$other_plan));
check(update_metadata_by_mid('post',$mid,'17:00')===false&&get_post_meta($other_plan,'_vms_start_time',true)==='18:00','by-mid change cannot bypass conflict validation');
check(update_post_meta($other_plan,'_vms_start_time','19:00')!==false,'nonconflicting time meta edit succeeds');check((int)bvmgr_staffing_lifecycle_row($id)['revision']===(int)$before['revision']+1,'time changes invalidate old response forms');
// Canonical reschedule must join the same transaction and reject staffing conflict.
$calendar=wp_insert_post(array('post_type'=>'tribe_events','post_status'=>'publish','post_title'=>'Staffing reschedule fixture'));
foreach(array('_EventStartDate'=>'2030-09-06 19:00:00','_EventEndDate'=>'2030-09-06 20:00:00','_EventStartDateUTC'=>get_gmt_from_date('2030-09-06 19:00:00'),'_EventEndDateUTC'=>get_gmt_from_date('2030-09-06 20:00:00'),'_EventTimezone'=>wp_timezone_string()) as $key=>$value)update_post_meta($calendar,$key,$value);
update_post_meta($other_plan,'_vms_tec_event_id',$calendar);
update_post_meta($other_plan,'_vms_event_plan_status','published');
$old=bvmgr_event_occurrence_for_plan($other_plan);$old_start=$old['start_local']??'';
$preview=bvmgr_event_occurrence_preview($other_plan,'2030-09-06 19:00:00','2030-09-06 17:00:00','rescheduled');
check($preview['allowed'],'reschedule fixture preview '.json_encode($preview['ambiguities']));
$before=bvmgr_staffing_lifecycle_row($id);$r=bvmgr_event_occurrence_apply($other_plan,'2030-09-06 19:00:00','2030-09-06 17:00:00','rescheduled',1);
check(!$r['ok']&&!empty($r['rolled_back'])&&get_post_meta($other_plan,'_vms_start_time',true)==='19:00'&&bvmgr_staffing_lifecycle_row($id)===$before,'canonical reschedule conflict rolls back complete operation '.json_encode($r));
$r=bvmgr_event_occurrence_apply($other_plan,'2030-09-06 19:00:00','2030-09-07 19:00:00','rescheduled',1);
check($r['ok']&&get_post_meta($other_plan,'_vms_event_date',true)==='2030-09-07','canonical nonconflicting reschedule commits');
$other->close();
echo 'PASS integration; '.$checks." database assertions\n";
// Additive preflight reports legacy evidence without editing it.
$receipt=bvmgr_staffing_lifecycle_preflight();check($receipt['ok']&&$receipt['schema']==='ready'&&count($receipt['duplicate_pairs'])>0,'read-only duplicate/schema preflight');
// Unknown historical state cannot be rewritten by a lifecycle action.
$f=fixture();$wpdb->update($assignment_table,array('status'=>'checked_in'),array('assignment_id'=>$f['id']));$before=bvmgr_staffing_lifecycle_row($f['id']);check(bvmgr_staffing_transition_assignment($f['id'],'canceled',options($f))['error']==='invalid_transition'&&$before===bvmgr_staffing_lifecycle_row($f['id']),'unknown legacy state preserved');
// Restore only the injected fixture fault before later whole-population gates.
$wpdb->update($assignment_table,array('status'=>'proposed'),array('assignment_id'=>$f['id']));
// Expired, inactive and missing-window transitions are rejected.
foreach(array('expired','inactive','missing','bad_date') as $case){$f=fixture();
    if($case==='expired')update_post_meta($f['plan'],'_vms_event_date','2020-01-01');
    if($case==='inactive')$wpdb->update(bvmgr_staffing_table_name('event_slots'),array('status'=>'canceled'),array('slot_id'=>$f['slot']));
    if($case==='missing')delete_post_meta($f['plan'],'_vms_event_date');
    if($case==='bad_date')update_post_meta($f['plan'],'_vms_event_date','2030-02-31');
    $before=bvmgr_staffing_lifecycle_row($f['id']);$n=audits($f['id']);$r=bvmgr_staffing_transition_assignment($f['id'],'confirmed',options($f));check(!$r['ok']&&$r['error']==='assignment_expired'&&$before===bvmgr_staffing_lifecycle_row($f['id'])&&$n===audits($f['id']),$case.' context cannot commit');
    if($case==='inactive')$wpdb->update(bvmgr_staffing_table_name('event_slots'),array('status'=>'active'),array('slot_id'=>$f['slot']));
}
// Durations support overnight work; reversed explicit clocks remain rejected.
$timezone=get_option('timezone_string');update_option('timezone_string','America/Chicago');$f=fixture();
$wpdb->update(bvmgr_staffing_table_name('event_slots'),array('shift_start_local'=>'23:00','shift_end_local'=>null,'duration_minutes'=>240),array('slot_id'=>$f['slot']));
$window=bvmgr_staffing_lifecycle_window(bvmgr_staffing_lifecycle_row($f['id']));check($window['end_local']->format('Y-m-d H:i')==='2030-09-07 03:00'&&$window['duration_minutes']===240,'explicit duration supports overnight shifts');
check($window['start_ts']%60===0&&$window['end_ts']%60===0,'canonical shift timestamps do not inherit request seconds');
update_post_meta($f['plan'],'_vms_event_date','2030-11-03');$wpdb->update(bvmgr_staffing_table_name('event_slots'),array('shift_start_local'=>'00:30','shift_end_local'=>'02:30','duration_minutes'=>null),array('slot_id'=>$f['slot']));
$window=bvmgr_staffing_lifecycle_window(bvmgr_staffing_lifecycle_row($f['id']));check($window['duration_minutes']===180,'DST fall transition resolves actual UTC duration');
update_post_meta($f['plan'],'_vms_event_date','2030-03-10');$wpdb->update(bvmgr_staffing_table_name('event_slots'),array('shift_start_local'=>'02:30','shift_end_local'=>'04:30'),array('slot_id'=>$f['slot']));check(bvmgr_staffing_lifecycle_window(bvmgr_staffing_lifecycle_row($f['id']))===array(),'nonexistent DST local time rejected');update_option('timezone_string',$timezone);
echo 'PASS extended integration; '.$checks." database assertions\n";
// A malformed partial migration must fail closed instead of accepting a named-but-wrong index.
$wpdb->query("ALTER TABLE `$audit_table` DROP INDEX lifecycle_operation, ADD KEY lifecycle_operation (operation_id)");
$r=bvmgr_staffing_transition_assignment($f['id'],'confirmed',options($f));check(!$r['ok']&&$r['error']==='lifecycle_migration_required','schema readiness verifies actual uniqueness');
check(!bvmgr_staffing_migrate_lifecycle()['ok'],'migration refuses incompatible existing index');
$wpdb->query("ALTER TABLE `$audit_table` DROP INDEX lifecycle_operation, ADD UNIQUE KEY lifecycle_operation (operation_id)");check(bvmgr_staffing_migrate_lifecycle()['ok'],'repaired fixture schema ready');
// The exact deferred Event Plan renderer includes controls and the canonical count split.
$f=fixture();$reflection=new ReflectionClass('BVMGR_Admin_Event_Plans');$admin=$reflection->newInstanceWithoutConstructor();
$build=$reflection->getMethod('build_event_plan_staff_response_payload');$build->setAccessible(true);$payload=$build->invoke($admin,$f['plan'],array());
$render=$reflection->getMethod('render_event_plan_staff_response_html');$render->setAccessible(true);$html=$render->invoke($admin,$payload['staff_context']);
check(strpos($html,'data-staffing-assignment="'.$f['id'].'"')!==false&&strpos($html,'Proposed · tentative')!==false&&strpos($html,'Open positions')!==false,'actual deferred editor response renders canonical lifecycle UI');
echo 'PASS complete integration; '.$checks." database assertions\n";
$f=fixture();update_post_meta($f['plan'],'_vms_event_date','2020-01-01');
check(bvmgr_staffing_transition_assignment($f['id'],'canceled',options($f,array('reason'=>'')))['error']==='reason_required','historical cancellation requires a reason');
check(bvmgr_staffing_transition_assignment($f['id'],'canceled',options($f))['ok'],'operator can deliberately cancel historical proposal');
echo 'PASS final integration; '.$checks." database assertions\n";
// A lost connection at COMMIT is explicitly indeterminate to the caller.
$f=fixture();$other=new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);$op=options($f);$n=audits($f['id']);
$kill_commit=static function($sql)use($other){global $wpdb;if($sql==='COMMIT')$other->query('KILL CONNECTION '.(int)$wpdb->get_var('SELECT CONNECTION_ID()'));return $sql;};
add_filter('query',$kill_commit);$r=bvmgr_staffing_transition_assignment($f['id'],'confirmed',$op);remove_filter('query',$kill_commit);
check(!$r['ok']&&$r['error']==='commit_outcome_unknown'&&empty($r['rolled_back']),'lost COMMIT acknowledgment never claims a definite rollback');
// The next HTTP request establishes a fresh host connection. The service itself
// must not reconnect or replay the transaction that just lost its connection.
check($wpdb->check_connection(false),'host restores connection before explicit retry');
check(bvmgr_staffing_transition_assignment($f['id'],'confirmed',$op)['ok']&&audits($f['id'])===$n+1,'same operation safely resolves an indeterminate response');$other->close();
echo 'PASS final transaction integration; '.$checks." database assertions\n";
