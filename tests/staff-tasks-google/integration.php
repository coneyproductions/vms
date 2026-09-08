<?php
require dirname(__DIR__).'/staff-tasks/authority.php';
require __DIR__.'/fake.php';
require_once dirname(__DIR__,2).'/includes/modules/staff-tasks/google/ui.php';
$start_checks=$checks;
function google_state_edit(callable $f): void { $r=bvmgr_google_exclusive(static function()use($f){$s=bvmgr_google_state();$f($s);bvmgr_google_save($s);}); task_check(!is_wp_error($r),'state edit'); }
function google_connect(int $user,string $subject=''): void {
 $url=bvmgr_google_begin($user,'fixture-session-'.$user);task_check(is_string($url),'OAuth begin');parse_str(parse_url($url,PHP_URL_QUERY),$q);
 task_check($q['scope']==='openid '.BVMGR_GOOGLE_SCOPE&&$q['access_type']==='offline','narrow offline scope');
 google_fake(static function(&$s)use($q,$subject){$s['nonce']=$q['nonce'];if($subject)$s['subject']=$subject;else unset($s['subject']);});
 task_check(bvmgr_google_callback($user,'wrong-session',$q['state'],'fixture-'.$user)===false,'cross-session callback denied');
 task_check(bvmgr_google_callback($user,'fixture-session-'.$user,$q['state'],'fixture-'.$user)===true,'identity-bound callback');
 task_check(bvmgr_google_callback($user,'fixture-session-'.$user,$q['state'],'fixture-'.$user)===false,'callback single use');
}
function google_run(bool $force=false): void { $r=bvmgr_google_tick($force);task_check(!is_wp_error($r),'Google tick completed'); }
function google_mirror(array $task,int $user=0): array {return bvmgr_google_state()['mirrors'][($user?:$task['assignee_user_id']).':'.$task['id']]??array();}
function google_event(array $task,int $user=0): array {$s=bvmgr_google_state();$u=$user?:$task['assignee_user_id'];$key=$s['connections'][$u]['calendar'].':'.bvmgr_google_event_id($s['site'],$task['id']);return google_fake(static fn(&$f)=>$f['events'][$key]??array());}
function google_due(): void {google_state_edit(static function(&$s){foreach($s['mirrors'] as &$m)$m['next']=0;});}
function google_create(string $title,array $timing,int $user): array {return task_ok(task_command('create',0,array('title'=>$title,'instructions'=>'PRIVATE CUSTOMER SECRET /private/files','assignee_user_id'=>$user,'timing'=>$timing)), $title);}
function google_snapshot(): array {global $wpdb;$out=array();foreach($wpdb->get_col('SHOW TABLES') as $table){$rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM %i',$table),ARRAY_A);$h=array_map(static fn($r)=>hash('sha256',serialize($r)),$rows);sort($h);$out[$table]=hash('sha256',serialize($h));}return $out;}
google_connect($users['a']);google_connect($users['b']);
$s=bvmgr_google_state();task_check(!str_contains(json_encode($s),'fixture-access')&&!str_contains(json_encode($s),'fixture-refresh'),'tokens encrypted at rest');
google_run();$calendars=google_fake(static fn(&$s)=>count($s['calendars']));google_run();task_check(google_fake(static fn(&$s)=>count($s['calendars']))===$calendars&&$calendars===2,'one calendar per connection');
$scheduled=google_create('Scheduled work',array('timezone'=>'Asia/Tokyo','schedule_source'=>'fixed','start'=>'2030-10-20 10:00','end'=>'2030-10-20 11:00'),$users['a']);
$date=google_create('Date only',array('due_kind'=>'date','due_value'=>'2030-10-20'),$users['a']);
$due=google_create('Exact deadline',array('due_kind'=>'datetime','due_value'=>'2030-10-20 10:00'),$users['a']);
$none=google_create('No date',array(),$users['a']);
$start_only=google_create('Start only',array('schedule_source'=>'fixed','start'=>'2030-10-20 12:00'),$users['a']);
google_run(true);
$e=google_event($scheduled);task_check($e['start']['dateTime']==='2030-10-20T10:00:00+09:00'&&$e['end']['dateTime']==='2030-10-20T11:00:00+09:00','scheduled exact recorded timezone');
$e=google_event($date);task_check($e['start']['date']==='2030-10-20'&&$e['end']['date']==='2030-10-21'&&$e['transparency']==='transparent','date only exclusive end');
$e=google_event($due);task_check(str_starts_with($e['summary'],'[Deadline]')&&strtotime($e['end']['dateTime'])-strtotime($e['start']['dateTime'])===60,'exact due one minute deadline marker');
task_check(google_mirror($none)['state']==='not_eligible'&&google_event($none)===array(),'undated never invented');
task_check(str_contains(google_event($start_only)['summary'],'end unspecified'),'start-only honest marker');
task_check(!str_contains(json_encode(google_fake(static fn(&$s)=>$s['events'])),'PRIVATE CUSTOMER SECRET'),'private instructions excluded');
$legacy=google_create('Legacy review',array('due_kind'=>'date','due_value'=>'2030-10-20'),$users['a']);$wpdb->update(bvmgr_tasks_table_name('task_instances'),array('timing_json'=>null),array('id'=>$legacy['id']));google_run();task_check(google_mirror($legacy)['state']==='blocked_timing_review'&&google_event($legacy)===array(),'legacy timing blocked');
$id=google_event($scheduled)['id'];$scheduled=task_ok(task_command('timing',$scheduled['id'],array('timezone'=>'Asia/Tokyo','schedule_source'=>'fixed','start'=>'2030-10-21 10:00','end'=>'2030-10-21 11:00')),'update schedule');google_run();task_check(google_event($scheduled)['id']===$id&&str_starts_with(google_event($scheduled)['start']['dateTime'],'2030-10-21'),'same event schedule update');
foreach(array(array('done','Completed'),array('open','Scheduled'),array('canceled','Canceled'),array('open','Scheduled')) as [$status,$label]) { $scheduled=task_ok(task_command('transition',$scheduled['id'],array('status'=>$status,'reason'=>'fixture')),'status '.$status);google_run();task_check(google_event($scheduled)['id']===$id&&str_contains(google_event($scheduled)['summary'],$label),'same event '.$status); }
$scheduled=task_ok(task_command('transition',$scheduled['id'],array('status'=>'open')),'open for reassignment');
$scheduled=task_ok(task_command('assignment',$scheduled['id'],array('assignee_user_id'=>$users['b'],'assignment_mode'=>'person','assignment_locked'=>true)),'reassignment');google_run();task_check(str_starts_with(google_event($scheduled,$users['a'])['summary'],'[Retired]')&&google_event($scheduled,$users['b'])['id']===$id,'old retired new mirrored');
$timeout=google_create('Timeout create',array('due_kind'=>'date','due_value'=>'2030-10-20'),$users['a']);google_fake_fault('POST','/events','after');google_run();task_check(google_mirror($timeout)['state']==='failed_retryable','timeout persisted');$n=google_fake(static fn(&$s)=>count($s['events']));google_due();google_run();task_check(google_mirror($timeout)['state']==='synced'&&google_fake(static fn(&$s)=>count($s['events']))===$n,'remote success timeout retries same event');
foreach(array(429,503,412,403) as $code){google_fake_fault('GET','/events/',$code,$code===403?'rateLimitExceeded':'');google_run(true);$states=array_column(bvmgr_google_state()['mirrors'],'state');task_check(in_array('failed_retryable',$states,true),'retryable '.$code);google_due();google_run();}
google_fake_fault('GET','/events/',401);google_run(true);task_check(in_array('synced',array_column(bvmgr_google_state()['mirrors'],'state'),true),'401 refresh and retry');
$s=bvmgr_google_state();$cal=$s['connections'][$users['a']]['calendar'];$eid=bvmgr_google_event_id($s['site'],$date['id']);
google_fake(static function(&$s)use($cal,$eid){unset($s['events'][$cal.':'.$eid]);});google_run(true);task_check(google_event($date)['id']===$eid,'missing event safely recreated');
google_fake(static function(&$s)use($cal,$eid){$s['events'][$cal.':'.$eid]['summary']='External edit';$s['events'][$cal.':'.$eid]['reminders']=array('useDefault'=>false);$s['events'][$cal.':'.$eid]['colorId']='7';});google_run(true);$e=google_event($date);task_check(str_contains($e['summary'],'Date only')&&$e['reminders']['useDefault']===false&&$e['colorId']==='7','BVM fields restored user preferences preserved');
// Failed canonical transaction must create no task, audit, or Google work.
$before=bvmgr_google_state();$bad=task_command('create',0,array('title'=>'Rollback','timing'=>array('due_kind'=>'datetime','due_value'=>'invalid')));task_check(is_wp_error($bad)&&bvmgr_google_state()===$before,'failed task generates no Google work');
// Revocation is a reconnect state, not an infinite refresh loop.
google_state_edit(static function(&$s)use($users){$c=&$s['connections'][$users['a']];$t=bvmgr_google_unseal($c['tokens']);$t['expires']=0;$c['tokens']=bvmgr_google_seal($t);});google_fake_fault('POST','/token',400,'invalid_grant');google_run(true);task_check(bvmgr_google_state()['connections'][$users['a']]['status']==='authorization_required','revoked refresh reconnect required');
google_connect($users['a']);google_run(true);task_check(bvmgr_google_state()['connections'][$users['a']]['calendar']===$cal,'reconnect reuses calendar');
google_state_edit(static function(&$s)use($users){bvmgr_google_disconnect($s,$users['a']);});$n=google_fake(static fn(&$s)=>count($s['events']));google_run(true);task_check(google_fake(static fn(&$s)=>count($s['events']))===$n,'disconnect retains history');google_connect($users['a']);google_run(true);task_check(bvmgr_google_state()['connections'][$users['a']]['calendar']===$cal,'disconnect reconnect stable identity');
// Deleted calendar: never auto-create. Explicit replacement is guarded by old identity.
google_fake(static function(&$s)use($cal){unset($s['calendars'][$cal]);});google_run(true);task_check(bvmgr_google_state()['connections'][$users['a']]['calendar_state']==='missing','calendar missing visible');$n=google_fake(static fn(&$s)=>count($s['calendars']));google_run(true);task_check(google_fake(static fn(&$s)=>count($s['calendars']))===$n,'missing calendar no blind creation');
require __DIR__.'/concurrency.php';
// Read every real disposable table and reject even attempted mutations/HTTP.
$before=google_snapshot();$calls=google_fake(static fn(&$s)=>count($s['calls']));$writes=0;
$guard=static function($sql)use(&$writes){if(preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP|TRUNCATE)\b/i',$sql)){$writes++;throw new RuntimeException('read attempted mutation');}return $sql;};add_filter('query',$guard,PHP_INT_MAX);
for($i=0;$i<3;$i++){bvmgr_google_state();bvmgr_google_tasks();ob_start();bvmgr_google_page();bvmgr_tasks_render_my_tasks_page();ob_end_clean();}
remove_filter('query',$guard,PHP_INT_MAX);task_check($writes===0&&google_snapshot()===$before&&google_fake(static fn(&$s)=>count($s['calls']))===$calls,'all '.count($before).' disposable tables unchanged on repeated status/task reads');
echo 'READ CONTAINMENT '.count($before)." disposable tables; zero SQL/HTTP attempts\n";
echo 'PASS '.($checks-$start_checks).' Google Calendar assertions; total '.$checks."\n";
