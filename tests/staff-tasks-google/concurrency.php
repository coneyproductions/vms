<?php
// Restore a deliberately deleted fixture calendar to continue independent scenarios.
google_fake(static function(&$f)use($cal){$c=bvmgr_google_state()['connections'];foreach($c as $connection)if($connection['calendar']===$cal)$f['calendars'][$cal]=array('id'=>$cal,'description'=>'BVM managed calendar '.$connection['calendar_marker']);});
google_state_edit(static function(&$s)use($users){$s['connections'][$users['a']]['calendar_state']='ready';});google_run(true);
// A lost local acknowledgement after a remote successful create must adopt the same deterministic event.
$lost=google_create('Lost local save',array('due_kind'=>'date','due_value'=>'2030-10-23'),$users['a']);
google_fake(static function(&$f){$f['fail_save']=true;});$r=bvmgr_google_tick();task_check(is_wp_error($r),'local save failure exposed');$id=google_event($lost)['id'];google_run();task_check(google_event($lost)['id']===$id&&google_mirror($lost)['state']==='synced','local save failure converges without duplicate');
function google_worker_start(): array {$pipes=array();$p=proc_open(array(PHP_BINARY,__DIR__.'/worker.php'),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes);fclose($pipes[0]);return array($p,$pipes);}
function google_worker_end(array $worker,bool $killed=false): string {[$p,$pipes]=$worker;$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);task_check($killed||$exit===0,'worker exits cleanly '.$err);return $out;}
function google_pause_worker(): array {$signal=dirname(getenv('BVM_GOOGLE_FAKE')).'/worker-ready';if(is_file($signal))unlink($signal);google_fake(static function(&$s)use($signal){$s['pause']=$signal;});$w=google_worker_start();$until=microtime(true)+8;while(!is_file($signal)&&microtime(true)<$until)usleep(10000);task_check(is_file($signal),'worker remote-create rendezvous');unlink($signal);return $w;}
$race=google_create('Worker race',array('due_kind'=>'date','due_value'=>'2030-10-24'),$users['a']);$w=google_pause_worker();$other=google_worker_start();task_check(google_worker_end($other)==='google_busy','second worker excluded during delivery');
// Task mutation can commit while transport waits; there is no task lock held over HTTP.
$race=task_ok(task_command('assignment',$race['id'],array('assignee_user_id'=>$users['b'],'assignment_mode'=>'person','assignment_locked'=>true)),'reassignment commits during Google request');google_worker_end($w);google_run();task_check(str_starts_with(google_event($race,$users['a'])['summary'],'[Retired]')&&google_mirror($race)['state']==='synced','pending reassignment retires old and converges new');
$crash=google_create('Worker crash',array('due_kind'=>'date','due_value'=>'2030-10-24'),$users['a']);$w=google_pause_worker();proc_terminate($w[0],9);google_worker_end($w,true);$remote_id=google_event($crash)['id'];google_run();task_check(google_mirror($crash)['state']==='synced'&&google_event($crash)['id']===$remote_id,'crash after create reuses deterministic identity');
$completion=google_create('Concurrent completion',array('due_kind'=>'date','due_value'=>'2030-10-24'),$users['a']);$w=google_pause_worker();$completion=task_ok(task_command('transition',$completion['id'],array('status'=>'done')),'completion commits during create');google_worker_end($w);google_run();task_check(str_starts_with(google_event($completion)['summary'],'[Completed]'),'completion during pending create converges');
// Event-relative projection only moves after canonical reconciliation; manual pin remains exact.
update_post_meta($plan,'_vms_event_plan_status','ready');bvmgr_tasks_reconcile_event($plan);
$relative=task_ok(task_command('create',0,array('title'=>'Relative Google','event_id'=>$plan,'assignee_user_id'=>$users['a'],'timing'=>array('schedule_source'=>'event_offset','start_offset'=>-30,'duration_minutes'=>15))),'relative Google task');
$pin=google_create('Pinned Google',array('schedule_source'=>'fixed','start'=>'2030-10-21 17:00','end'=>'2030-10-21 18:00'),$users['a']);google_run();$old=google_event($relative)['start'];$fixed=google_event($pin)['start'];
bvmgr_event_occurrence_authorized_write(static function()use($plan){update_post_meta($plan,'_vms_event_date','2030-10-25');});clean_post_cache($plan);google_run();task_check(google_mirror($relative)['state']==='blocked_timing_review'&&google_event($relative)['start']===$old,'unreconciled relative timing blocked');
bvmgr_tasks_reconcile_event($plan);google_run();task_check(google_event($relative)['start']!==$old&&google_event($pin)['start']===$fixed,'canonical relative movement and pinned preservation');
// Calendar timeout: preserve one creation intent and recover by exact application marker.
$third=wp_insert_user(array('user_login'=>'google-third','user_pass'=>'fixture','role'=>'editor'));google_connect($third);google_fake_fault('POST','/calendars','after');google_run();task_check(bvmgr_google_state()['connections'][$third]['calendar_state']==='creation_uncertain','uncertain calendar creation blocked');$n=google_fake(static fn(&$s)=>count($s['calendars']));google_run();task_check(google_fake(static fn(&$s)=>count($s['calendars']))===$n,'calendar timeout never duplicated');
$recover=google_fake(static fn(&$s)=>array_key_last($s['calendars']));google_state_edit(static function(&$s)use($third,$recover){task_check(!bvmgr_google_adopt($s,$third,'primary'),'primary adoption denied');task_check(bvmgr_google_adopt($s,$third,$recover),'uncertain calendar marker recovery');});
// Wrong-account reconnect is denied and existing connection remains intact.
$url=bvmgr_google_begin($users['a'],'fixture-session-'.$users['a']);parse_str(parse_url($url,PHP_URL_QUERY),$q);google_fake(static function(&$s)use($q){$s['nonce']=$q['nonce'];$s['subject']='different-google';});task_check(bvmgr_google_callback($users['a'],'fixture-session-'.$users['a'],$q['state'],'fixture-'.$users['a'])===false,'wrong Google account reconnect denied');
// 400 malformed data and ordinary 403 are permanent, 409/412 are bounded retries.
foreach(array(400,403) as $code){$f=bvmgr_google_failure(array('code'=>$code,'reason'=>'forbidden'),1);task_check($f['state']==='permanent_failure','permanent provider class '.$code);}
task_check(bvmgr_google_failure(array('code'=>503,'reason'=>''),8)['state']==='permanent_failure','transient retries bounded');
task_check(bvmgr_google_failure(array('code'=>409,'reason'=>''),1)['state']==='failed_retryable','duplicate insert retries safely');
// Repeated Sync Now does not add remote objects.
google_run(true);$n=google_fake(static fn(&$s)=>count($s['events']));google_run(true);task_check(google_fake(static fn(&$s)=>count($s['events']))===$n,'manual sync duplicate free');

// Verify a controlled missing-calendar replacement can only be requested once.
$third_calendar=bvmgr_google_state()['connections'][$third]['calendar'];
google_fake(static function(&$s)use($third_calendar){unset($s['calendars'][$third_calendar]);});google_run(true);
google_state_edit(static function(&$s)use($third,$third_calendar){task_check(bvmgr_google_replace_missing($s,$third,hash('sha256',$third_calendar)),'explicit missing calendar replacement');task_check(!bvmgr_google_replace_missing($s,$third,hash('sha256',$third_calendar)),'duplicate replacement request ignored');});
google_run();task_check(bvmgr_google_state()['connections'][$third]['calendar_state']==='ready','replacement ready');
// A proven reject can be retried explicitly; uncertain creates cannot.
google_state_edit(static function(&$s)use($third){$s['connections'][$third]['calendar_state']='creation_uncertain';task_check(!bvmgr_google_retry_calendar($s,$third),'uncertain create cannot be blindly retried');$s['connections'][$third]['calendar_state']='ready';});
// Quiet reads derive pending revision state before any worker persists it.
$quiet=google_create('Read sees pending',array('due_kind'=>'date','due_value'=>'2030-10-27'),$users['a']);google_run();
$quiet=task_ok(task_command('transition',$quiet['id'],array('status'=>'done')),'quiet completion');$st=bvmgr_google_state();$view=bvmgr_google_mirror_view($st,google_mirror($quiet));task_check($view['state']==='update_pending'&&bvmgr_google_state()===$st,'current revision status without queue write');google_run();
// A no-op manual reconciliation performs no repeated provider writes.
$patches=google_fake(static fn(&$s)=>count(array_filter($s['calls'],static fn($c)=>in_array($c['method'],array('POST','PATCH'),true))));google_run(true);
task_check(google_fake(static fn(&$s)=>count(array_filter($s['calls'],static fn($c)=>in_array($c['method'],array('POST','PATCH'),true))))===$patches,'same canonical revision never redelivered unnecessarily');

// Real patch precondition conflict: external edit, 412, then refetch and targeted repair.
$st=bvmgr_google_state();$calendar=$st['connections'][$users['a']]['calendar'];$identity=bvmgr_google_event_id($st['site'],$date['id']);
google_fake(static function(&$s)use($calendar,$identity){$s['events'][$calendar.':'.$identity]['summary']='External changed during repair';});google_fake_fault('PATCH','/events/',412);google_run(true);
task_check(google_mirror($date)['state']==='failed_retryable','patch ETag conflict retries');google_due();google_run();task_check(google_mirror($date)['state']==='synced'&&str_contains(google_event($date)['summary'],'Date only'),'ETag conflict refetch convergence');
// Switching all-day to exact due time clears the old resource date shape.
$date=task_ok(task_command('timing',$date['id'],array('due_kind'=>'datetime','due_value'=>'2030-10-20 13:45')),'all-day to due-time change');google_run();$ev=google_event($date);task_check(empty($ev['start']['date'])&&str_contains($ev['start']['dateTime'],'13:45:00'),'all-day date cleared on timed patch');
// Manual replay creates one canonical task and one mirror.
$operation=wp_generate_uuid4();$input=array('title'=>'Replayed Google create','assignee_user_id'=>$users['a'],'timing'=>array('due_kind'=>'date','due_value'=>'2030-10-28'));
$one=task_ok(bvmgr_tasks_command('create',0,$input,null,$operation),'first create request');google_run();$id=google_event($one)['id'];$again=task_ok(bvmgr_tasks_command('create',0,$input,null,$operation),'replayed create request');google_run();task_check($again['id']===$one['id']&&google_event($again)['id']===$id,'request replay does not duplicate queue or event');

// Review freezes timing while terminal status still retires active work visually.
$reviewed=google_create('Review after mirror',array('schedule_source'=>'fixed','start'=>'2030-10-28 12:00','end'=>'2030-10-28 13:00'),$users['a']);google_run();$frozen_start=google_event($reviewed)['start'];
$wpdb->update(bvmgr_tasks_table_name('task_instances'),array('timing_json'=>null),array('id'=>$reviewed['id']));$reviewed=task_ok(task_command('transition',$reviewed['id'],array('status'=>'canceled','reason'=>'fixture')),'cancel reviewed task');google_run();$event=google_event($reviewed);task_check(google_mirror($reviewed)['state']==='blocked_timing_review'&&str_starts_with($event['summary'],'[Canceled]')&&$event['start']===$frozen_start&&$event['transparency']==='transparent','reviewed cancellation changes status without inventing timing');
// Insert conflict after a stale/missing GET converges to the existing deterministic resource.
google_fake_fault('GET','/events/',404);google_run(true);task_check(in_array('failed_retryable',array_column(bvmgr_google_state()['mirrors'],'state'),true),'actual duplicate insert 409 handled');google_due();google_run();
// A deleted-event tombstone is restored on the same logical identity in the fake contract.
$st=bvmgr_google_state();$calendar=$st['connections'][$users['a']]['calendar'];$identity=bvmgr_google_event_id($st['site'],$due['id']);
google_fake(static function(&$s)use($calendar,$identity){$s['events'][$calendar.':'.$identity]=array('id'=>$identity,'etag'=>'"deleted-fixture"','status'=>'cancelled');});google_run(true);task_check(google_event($due)['id']===$identity&&google_event($due)['status']==='confirmed','tombstone restoration retains event identity');
// More than one bounded batch must drain fairly, including repeated manual requests.
$backlog=array();for($i=0;$i<23;$i++)$backlog[]=google_create('Backlog '.$i,array('due_kind'=>'date','due_value'=>'2030-10-29'),$users['b']);
google_run(true);google_run(true);for($i=0;$i<4;$i++)google_run();foreach($backlog as $task)task_check(google_mirror($task)['state']==='synced','bounded batches drain every current task');

$cp=array();$child=proc_open(array(PHP_BINARY,__DIR__.'/worker.php','anonymous_callback'),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$cp);fclose($cp[0]);$url=stream_get_contents($cp[1]);$error=stream_get_contents($cp[2]);fclose($cp[1]);fclose($cp[2]);task_check(proc_close($child)===0&&str_contains($url,'wp-login.php')&&!str_contains($url,'must-not-survive-callback'),'expired session callback cleans code and redirects to login');
