<?php
defined('ABSPATH') || exit;

function bvmgr_google_calendar(array &$state, int $user): bool
{
    $c =& $state['connections'][$user];
    if ($c['status']!=='connected') return false;
    if (!empty($c['calendar'])) return ($c['calendar_state'] ?? '')==='ready';
    if (($c['calendar_state'] ?? '')!=='new') return false;
    // calendars.insert has no caller-supplied identity. Persist uncertainty BEFORE the first request.
    $c['calendar_state']='creation_uncertain'; bvmgr_google_save($state);
    $r=bvmgr_google_api($state,$user,'POST','calendars',array('summary'=>'BVM Tasks','description'=>'BVM managed calendar '.$c['calendar_marker']));
    if ($r['code']===200 && is_string($r['data']['id'] ?? null) && $r['data']['id']!=='primary') {
        $c['calendar']=$r['data']['id']; $c['calendar_state']='ready'; bvmgr_google_save($state); return true;
    }
    // Even definitive failures require an explicit operator action; never loop calendar creation.
    $c['calendar_error']=bvmgr_google_failure($r,1)['state'];
    if (in_array($r['code'],array(400,401,403,404,429),true)) $c['calendar_state']='create_failed'; bvmgr_google_save($state); return false;
}

/** Recover ONLY the exact app-created calendar bearing this connection's random marker. */
function bvmgr_google_adopt(array &$state, int $user, string $calendar): bool
{
    $c =& $state['connections'][$user];
    if (!preg_match('/^[a-zA-Z0-9_.@-]{5,1024}$/D',$calendar) || $calendar==='primary') return false;
    $r=bvmgr_google_api($state,$user,'GET','calendars/'.rawurlencode($calendar));
    if ($r['code']!==200 || ($r['data']['description']??'')!=='BVM managed calendar '.$c['calendar_marker'] || ($r['data']['id']??'')!==$calendar) return false;
    $c['calendar']=$calendar; $c['calendar_state']='ready'; unset($c['calendar_error']); bvmgr_google_save($state); return true;
}

function bvmgr_google_queue(array &$state, array $tasks, bool $force = false, int $only_user = 0): void
{
    foreach ($tasks as $task) {
        $user=(int)$task['assignee_user_id'];
        if ($user>0 && isset($state['connections'][$user])) {
            $key=$user.':'.$task['task_id'];
            if (!isset($state['mirrors'][$key])) $state['mirrors'][$key]=array('user'=>$user,'task'=>$task['task_id'],'state'=>'pending','attempts'=>0,'next'=>0,'ever'=>false);
        }
    }
    $by_id=array_column($tasks,null,'task_id');
    foreach ($state['mirrors'] as &$mirror) {
        $user=$mirror['user']; $c=$state['connections'][$user]??array();
        $task=$by_id[$mirror['task']]??bvmgr_tasks_sync_record($mirror['task']);
        if (($c['status']??'')!=='connected') { $mirror['state']=($c['status']??'')==='authorization_required'?'authorization_required':'not_connected'; continue; }
        $projection=bvmgr_google_projection($task,$user,$state['site'],(!empty($mirror['ever']) || !empty($mirror['attempted'])));
        if (!bvmgr_google_allowed_user($user) && $task && (int)$task['assignee_user_id']===$user) $projection=array('state'=>'not_eligible','retire'=>(!empty($mirror['ever']) || !empty($mirror['attempted'])));
        if (!empty($projection['retire']) && (!empty($mirror['ever']) || !empty($mirror['attempted']))) {
            $projection=array('state'=>'pending','body'=>array('summary'=>'[Retired] BVM task #'.$mirror['task'],
                'description'=>'Managed by Backstage Venue Manager. This task is no longer calendar-eligible for this person. See BVM for current authority.',
                'transparency'=>'transparent','status'=>'confirmed','extendedProperties'=>array('private'=>array('bvm_owner'=>$state['site'],'bvm_task'=>(string)$mirror['task'],'bvm_user'=>(string)$user,'bvm_status'=>'retired'))), 'hash'=>'retired');
        }
        if (empty($projection['body'])) { $mirror['state']=$projection['state']; continue; }
        if (($c['calendar_state']??'')!=='ready') { $mirror['state']='calendar_missing'; continue; }
        $mirror['review_block']=!empty($projection['review_block']);
        if ($mirror['review_block'] && ($mirror['applied']??'')===$projection['hash']) { $mirror['state']='blocked_timing_review'; continue; }
        $changed=($mirror['wanted']??'')!==$projection['hash'];
        if ($changed || ($force && (!$only_user || $user===$only_user)) || in_array($mirror['state'],array('not_connected','authorization_required','calendar_missing','blocked_timing_review','not_eligible'),true)) {
            $mirror['wanted']=$projection['hash']; $mirror['body']=$projection['body']; $mirror['state']=(!empty($mirror['ever']) || !empty($mirror['attempted']))?'update_pending':'pending'; $mirror['attempts']=0; $mirror['next']=0;
        }
    }
    unset($mirror);
    bvmgr_google_save($state);
}

/** Read current authority including all open work; existing mirrors are always reconciled. */
function bvmgr_google_tasks(): array
{
    global $wpdb;
    $ids=$wpdb->get_col($wpdb->prepare("SELECT id FROM %i WHERE status IN ('open','done')", bvmgr_tasks_table_name('task_instances')));
    $out=array(); foreach ($ids as $id) { $p=bvmgr_tasks_sync_record((int)$id); if ($p) $out[]=$p; }
    return $out;
}

function bvmgr_google_deliver(array &$state, string $key): void
{
    $m =& $state['mirrors'][$key]; $user=$m['user']; $c =& $state['connections'][$user];
    if (!in_array($m['state'],array('pending','update_pending','failed_retryable'),true) || $m['next']>time() || $c['status']!=='connected' || $c['calendar_state']!=='ready') return;
    // Re-read before every operation. No task state is imported from Google.
    bvmgr_google_queue($state,array_filter(array(bvmgr_tasks_sync_record($m['task']))));
    if (!in_array($m['state'],array('pending','update_pending','failed_retryable'),true)) return;
    $id=bvmgr_google_event_id($state['site'],$m['task']);
    $base='calendars/'.rawurlencode($c['calendar']).'/events'; $path=$base.'/'.rawurlencode($id);
    $m['attempted']=true; $m['attempts']++; $m['last_attempt']=time(); bvmgr_google_save($state);
    $r=bvmgr_google_api($state,$user,'GET',$path);
    if ($r['code']===404 || $r['code']===410) {
        $calendar=bvmgr_google_api($state,$user,'GET','calendars/'.rawurlencode($c['calendar']));
        if ($calendar['code']===404 || $calendar['code']===410) { $c['calendar_state']='missing'; $m['state']='calendar_missing'; bvmgr_google_save($state); return; }
        if ($calendar['code']!==200) $r=$calendar;
        elseif ($m['wanted']==='retired' || !empty($m['review_block'])) { $m['state']='synced'; $m['applied']=$m['wanted']; bvmgr_google_save($state); return; }
        else $r=bvmgr_google_api($state,$user,'POST',$base.'?sendUpdates=none',array_merge(array('id'=>$id),$m['body']));
    } elseif ($r['code']===200) {
        $remote=$r['data']; $private=$remote['extendedProperties']['private']??array();
        // Deleted tombstones may contain only id. A durable attempted deterministic ID proves prior intent.
        if (($remote['status']??'')!=='cancelled' && (($private['bvm_owner']??'')!==$state['site'] || ($private['bvm_task']??'')!==(string)$m['task'] || ($private['bvm_user']??'')!==(string)$user)) {
            $m['state']='permanent_failure'; $m['error']='ownership_mismatch'; bvmgr_google_save($state); return;
        }
        $body=$m['body'];
        if (isset($body['extendedProperties']['private'])) $body['extendedProperties']['private']=array_merge($private,$body['extendedProperties']['private']);
        // Explicit null clears the old date/dateTime shape when switching all-day/timed.
        foreach (array('start','end') as $field) if (isset($body[$field])) $body[$field]=array_merge(array('date'=>null,'dateTime'=>null,'timeZone'=>null),$body[$field]);
        if (bvmgr_google_fields_match($m['body'],$remote)) { $r=array('code'=>200,'reason'=>'','data'=>$remote); }
        elseif (empty($remote['etag'])) $r=array('code'=>412,'reason'=>'conditionNotMet','data'=>array());
        else $r=bvmgr_google_api($state,$user,'PATCH',$path.'?sendUpdates=none',$body,$remote['etag']);
    }
    if (in_array($r['code'],array(200,201),true) && ($r['data']['id']??'')===$id) {
        $m['state']='synced'; $m['applied']=$m['wanted']; $m['ever']=true; $m['last_success']=time(); $m['attempts']=0; $m['next']=0; $m['error']=''; $c['last_success']=time();
    } else {
        $failure=bvmgr_google_failure($r,$m['attempts']); $m=array_merge($m,$failure); $m['error']='google_http_'.$r['code'];
    }
    $m['history'][]=array('at'=>time(),'state'=>$m['state'],'http'=>$r['code'],'wanted'=>$m['wanted']);
    $m['history']=array_slice($m['history'],-10);
    bvmgr_google_save($state);
    // A concurrent reassignment/completion becomes durable follow-up work before worker exit.
    bvmgr_google_queue($state,array_filter(array(bvmgr_tasks_sync_record($m['task']))));
}

function bvmgr_google_tick(bool $force = false, int $only_user = 0)
{
    if (!bvmgr_google_configured() || !bvmgr_tasks_authority_ready()) return null;
    return bvmgr_google_exclusive(static function() use ($force,$only_user) {
        $state=bvmgr_google_state(); if (empty($state['site'])) return null;
        foreach ($state['connections'] as $user=>$c) if ((!$only_user || (int)$user===$only_user) && $c['status']==='connected' && bvmgr_google_allowed_user((int)$user)) bvmgr_google_calendar($state,(int)$user);
        // Periodic reconciliation also repairs external changes; failed retry clocks are respected.
        $reconcile=$force || ($state['reconcile_after']??0)<=time();
        if ($reconcile) foreach ($state['connections'] as $user=>&$c) {
            if (($only_user && (int)$user!==$only_user) || $c['status']!=='connected' || ($c['calendar_state']??'')!=='ready') continue;
            if (!$force && ($c['calendar_error']??'')==='permanent_failure') continue;
            $health=bvmgr_google_api($state,(int)$user,'GET','calendars/'.rawurlencode($c['calendar']));
            if (in_array($health['code'],array(404,410),true)) $c['calendar_state']='missing';
            if ($health['code']===200) { $c['health_attempts']=0; $c['calendar_error']=''; }
            else { $c['health_attempts']=($c['health_attempts']??0)+1; $c['calendar_error']=bvmgr_google_failure($health,$c['health_attempts'])['state']; }
        }
        unset($c);
        bvmgr_google_queue($state,bvmgr_google_tasks(),$force,$only_user);
        if ($reconcile) {
            foreach ($state['mirrors'] as &$m) if ((!$only_user || $m['user']===$only_user) && $m['state']==='synced' && ($m['applied']??'')!=='retired') $m['state']='update_pending';
            unset($m); if (!$only_user) $state['reconcile_after']=time()+3600; bvmgr_google_save($state);
        }
        $delivered=0; $deadline=microtime(true)+45;
        $keys=array_keys($state['mirrors']);
        usort($keys,static function($a,$b)use($state){
            $x=$state['mirrors'][$a]; $y=$state['mirrors'][$b];
            return (($x['last_attempt']??0)<=>($y['last_attempt']??0)) ?: strcmp($a,$b);
        });
        foreach ($keys as $key) {
            $m=$state['mirrors'][$key];
            if ($only_user && $m['user']!==$only_user) continue;
            if (in_array($m['state'],array('pending','update_pending','failed_retryable'),true) && $m['next']<=time()) { bvmgr_google_deliver($state,$key); if (++$delivered>=20 || microtime(true)>=$deadline) break; }
        }
        return $delivered;
    });
}
// Reuse the explicitly installed Staff Tasks cron. Page rendering never dispatches this worker.
add_action('vms_tasks_notifications_tick', 'bvmgr_google_tick', 30);
add_action('bvmgr_tasks_notifications_tick', 'bvmgr_google_tick', 30);

/** Only explicit confirmed replacement of a proven missing calendar may start a new create intent. */
function bvmgr_google_replace_missing(array &$state, int $user, string $expected): bool
{
    $c =& $state['connections'][$user];
    if (($c['calendar_state']??'')!=='missing' || empty($c['calendar']) || !hash_equals(hash('sha256',$c['calendar']),$expected)) return false;
    $r=bvmgr_google_api($state,$user,'GET','calendars/'.rawurlencode($c['calendar']));
    if (!in_array($r['code'],array(404,410),true)) return false;
    $c['previous_calendar']=$c['calendar']; $c['calendar']=''; $c['calendar_marker']=bin2hex(random_bytes(24)); $c['calendar_state']='new';
    bvmgr_google_save($state); return true;
}

function bvmgr_google_retry_calendar(array &$state, int $user): bool
{
    if (($state['connections'][$user]['calendar_state']??'')!=='create_failed') return false;
    $state['connections'][$user]['calendar_state']='new'; bvmgr_google_save($state); return true;
}

/** Current status projection, including task changes committed since the last worker. No writes. */
function bvmgr_google_mirror_view(array $state, array $mirror): array
{
    $c=$state['connections'][$mirror['user']]??array();
    if (($c['status']??'')!=='connected') { $mirror['state']=($c['status']??'')==='authorization_required'?'authorization_required':'not_connected'; return $mirror; }
    return bvmgr_google_mirror_projection($state,$mirror,bvmgr_tasks_sync_record($mirror['task']));
}

/** Read-only status using an already authorized canonical task projection. */
function bvmgr_google_mirror_projection(array $state,array $mirror,?array $task): array
{
    $c=$state['connections'][$mirror['user']]??array();
    if (($c['status']??'')!=='connected') { $mirror['state']=($c['status']??'')==='authorization_required'?'authorization_required':'not_connected'; return $mirror; }
    $p=bvmgr_google_projection($task,$mirror['user'],$state['site'],!empty($mirror['attempted']));
    if (($p['state']??'')==='blocked_timing_review' || !empty($p['review_block'])) $mirror['state']='blocked_timing_review';
    elseif (!empty($p['hash']) && ($mirror['applied']??'')!==$p['hash'] && $mirror['state']==='synced') $mirror['state']='update_pending';
    elseif (!empty($p['retire']) && ($mirror['applied']??'')!=='retired') $mirror['state']='update_pending';
    return $mirror;
}

/** Compare only fields BVM owns; unrelated Google preferences never trigger replacement. */
function bvmgr_google_fields_match(array $owned, array $remote): bool
{
    // Google may omit its default busy value and return equivalent instants in UTC.
    if (array_key_exists('transparency',$owned) && !array_key_exists('transparency',$remote)) $remote['transparency']='opaque';
    foreach ($owned as $key=>$value) {
        if (!array_key_exists($key,$remote)) return false;
        if (is_array($value)) { if (!is_array($remote[$key]) || !bvmgr_google_fields_match($value,$remote[$key])) return false; }
        elseif ($value!==$remote[$key] && ($key!=='dateTime' || !bvmgr_google_same_instant($value,$remote[$key]))) return false;
    }
    return true;
}

/** Compare valid offset-bearing RFC3339 instants; retain separate strict IANA-zone checks. */
function bvmgr_google_same_instant($left, $right): bool
{
    $pattern='/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D';
    $values=array();
    foreach (array($left,$right) as $value) {
        if (!is_string($value) || !preg_match($pattern,$value)) return false;
        try { $date=new DateTimeImmutable($value); $errors=DateTimeImmutable::getLastErrors(); }
        catch (Throwable $e) { return false; }
        if ($errors && ($errors['warning_count'] || $errors['error_count'])) return false;
        $values[]=$date->format('U.u');
    }
    return $values[0]===$values[1];
}
