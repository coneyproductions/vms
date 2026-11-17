<?php
/** Bounded, permission-scoped calendar reads of instantiated Staff Tasks. No writes or transport. */
defined('ABSPATH') || exit;

function bvmgr_tasks_calendar_request(array $input): array
{
    $get=static fn($key,$default='')=>isset($input[$key])&&is_scalar($input[$key])?sanitize_text_field((string)wp_unslash($input[$key])):$default;
    $today=wp_date('Y-m-d');$date=$get('date',$today);
    $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date,wp_timezone());
    if (!$parsed || $parsed->format('Y-m-d')!==$date || $date<'1900-01-01' || $date>'2100-12-31') $date=$today;
    $f=array('date'=>$date,'view'=>$get('view')==='day'?'day':'week','assignee'=>absint($get('assignee')),
        'event'=>absint($get('event')),'venue'=>absint($get('venue')),'type'=>$get('type'),
        'status'=>$get('status','all'),'required'=>$get('required','all'),'focus'=>$get('focus'),
        'range_page'=>max(0,min(100000,absint($get('range_page')))),'queue_page'=>max(0,min(100000,absint($get('queue_page')))));
    if (!in_array($f['type'],array('','scheduled','deadline','date','unscheduled'),true)) $f['type']='';
    if (!in_array($f['status'],array('all','open','done','canceled','skipped','superseded'),true)) $f['status']='all';
    if (!in_array($f['required'],array('all','1','0'),true)) $f['required']='all';
    if (!in_array($f['focus'],array('','my','unassigned','overdue','review','sync'),true)) $f['focus']='';
    return $f;
}

function bvmgr_tasks_calendar_range(array $f): array
{
    $start=new DateTimeImmutable($f['date'].' 00:00:00',wp_timezone());
    if ($f['view']==='week') $start=$start->modify('-'.(((int)$start->format('w')-(int)get_option('start_of_week',1)+7)%7).' days');
    $end=$start->modify($f['view']==='day'?'+1 day':'+7 days');$utc=new DateTimeZone('UTC');
    return array('start'=>$start,'end'=>$end,'start_utc'=>$start->setTimezone($utc)->format('Y-m-d H:i:s'),'end_utc'=>$end->setTimezone($utc)->format('Y-m-d H:i:s'));
}

/** Fixed JSON paths only; malformed legacy JSON must never break or invent calendar timing. */
function bvmgr_tasks_calendar_json(string $key): string
{
    if (!in_array($key,array('start_utc','end_utc','due_utc','due_kind','due_value','review','occurrence_start_utc'),true)) throw new InvalidArgumentException('Unknown timing field');
    return "NULLIF(JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN JSON_VALID(t.timing_json) THEN t.timing_json ELSE '{}' END, '$.".$key."')), 'null')";
}

/** Event-linked venue comes from current Event Plan context, not its old task snapshot. */
function bvmgr_tasks_calendar_venue_sql(): string
{
    global $wpdb;
    return $wpdb->prepare("CASE WHEN t.event_id>0 THEN COALESCE((SELECT CAST(vm.meta_value AS UNSIGNED) FROM %i vm WHERE vm.post_id=t.event_id AND vm.meta_key='_vms_venue_id' ORDER BY vm.meta_id ASC LIMIT 1),0) ELSE COALESCE(t.venue_id,0) END",$wpdb->postmeta);
}

function bvmgr_tasks_calendar_sync(array $state,array $task): array
{
    $user=$task['assignee_user_id'];$c=$state['connections'][$user]??array();$m=$state['mirrors'][$user.':'.$task['task_id']]??null;
    if ($task['review']!=='' || !is_array($task['timing'])) return array('state'=>'blocked_timing_review','attention'=>true);
    if (!$user) return array('state'=>'not_eligible','attention'=>false);
    if (($c['status']??'')!=='connected') return array('state'=>($c['status']??'')==='authorization_required'?'authorization_required':'not_connected','attention'=>!empty($m)||($c['status']??'')==='authorization_required');
    $p=bvmgr_google_projection($task,$user,$state['site']??'',!empty($m['attempted'])||!empty($m['ever']));
    if (($p['state']??'')==='not_eligible') return array('state'=>'not_eligible','attention'=>false);
    if (($c['calendar_state']??'')!=='ready') return array('state'=>'calendar_missing','attention'=>true);
    $status=$m?bvmgr_google_mirror_projection($state,$m,$task)['state']:'pending';
    $known=array('synced','pending','update_pending','failed_retryable','authorization_required','calendar_missing','permanent_failure','blocked_timing_review','not_connected','not_eligible');
    if (!in_array($status,$known,true)) $status='permanent_failure';
    return array('state'=>$status,'attention'=>in_array($status,array('failed_retryable','authorization_required','calendar_missing','permanent_failure','blocked_timing_review','not_connected'),true));
}

/** A task can have a scheduled placement and a separate due marker; both retain the same task ID. */
function bvmgr_tasks_calendar_placements(array $task,array $range): array
{
    if ($task['review']!=='' || !is_array($task['timing'])) return array();
    $t=$task['timing'];$out=array();$utc=new DateTimeZone('UTC');
    try {
        if (!empty($t['start_utc'])) {
            $start=new DateTimeImmutable($t['start_utc'],$utc);$end=!empty($t['end_utc'])?new DateTimeImmutable($t['end_utc'],$utc):null;
            if ($end && $end<=$start) return array();
            if ($start<$range['end'] && ($end?$end>$range['start']:$start>=$range['start'])) $out[]=array('kind'=>$end?'scheduled':'start','start'=>$start,'end'=>$end);
        }
        if (($t['due_kind']??'')==='date') {
            $day=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$t['due_value'],wp_timezone());
            if ($day && $day->format('Y-m-d')===$t['due_value'] && $day>=$range['start'] && $day<$range['end']) $out[]=array('kind'=>'date','start'=>$day,'end'=>null);
        } elseif (($t['due_kind']??'')==='datetime' && !empty($t['due_utc'])) {
            $due=new DateTimeImmutable($t['due_utc'],$utc);
            if ($due>=$range['start'] && $due<$range['end']) $out[]=array('kind'=>'deadline','start'=>$due,'end'=>null);
        }
    } catch (Throwable $e) { return array(); }
    return $out;
}

function bvmgr_tasks_calendar_type(array $task): string
{
    $t=$task['timing'];
    if (!empty($t['start_utc'])) return 'scheduled';
    if (($t['due_kind']??'')==='date') return 'date';
    if (($t['due_kind']??'')==='datetime' && !empty($t['due_utc'])) return 'deadline';
    return 'unscheduled';
}

function bvmgr_tasks_calendar_attention(array $task,array $sync): array
{
    $reasons=array();
    if ($task['review']!=='') $reasons[]='review';
    elseif (bvmgr_tasks_calendar_type($task)==='unscheduled') $reasons[]='unscheduled';
    if (!$task['assignee_user_id']) $reasons[]='unassigned';
    if ($task['overdue'] && $task['review']==='') $reasons[]='overdue';
    if ($sync['attention']) $reasons[]='sync';
    return $reasons;
}

/** Same permission gate for the HTML endpoint and any direct server-side read consumer. */
function bvmgr_tasks_calendar_read(array $input)
{
    global $wpdb;
    $manager=bvmgr_tasks_current_user_can_manage_all();$viewer=get_current_user_id();
    if (!$viewer || (!$manager && !bvmgr_tasks_current_user_can_view_self())) return new WP_Error('calendar_forbidden',__('You cannot view Staff Tasks Calendar.','backstage-venue-manager'),array('status'=>403));
    if (!bvmgr_tasks_authority_ready()) return new WP_Error('calendar_unavailable',__('Staff Tasks requires its existing reliability update.','backstage-venue-manager'),array('status'=>503));
    $f=bvmgr_tasks_calendar_request($input);$range=bvmgr_tasks_calendar_range($f);
    if (!$manager || $f['focus']==='my') $f['assignee']=$viewer;
    // Read once. Only the explicitly allowlisted status projection below reaches the response.
    try { $state=bvmgr_google_state();$sync_unavailable=false; }
    catch (Throwable $e) { $state=array('connections'=>array(),'mirrors'=>array());$sync_unavailable=true; }
    $where=array('1=1');$args=array(bvmgr_tasks_table_name('task_instances'));
    foreach (array('assignee'=>'assignee_user_id','event'=>'event_id','venue'=>'venue_id') as $key=>$column) if ($f[$key]) { $where[]=($key==='venue'?'('.bvmgr_tasks_calendar_venue_sql().')':'t.'.$column).'=%d';$args[]=$f[$key]; }
    if ($f['status']!=='all') { $where[]='t.status=%s';$args[]=$f['status']; }
    if ($f['required']!=='all') { $where[]='t.is_required=%d';$args[]=(int)$f['required']; }
    $start=bvmgr_tasks_calendar_json('start_utc');$end=bvmgr_tasks_calendar_json('end_utc');$due=bvmgr_tasks_calendar_json('due_utc');$kind=bvmgr_tasks_calendar_json('due_kind');$date=bvmgr_tasks_calendar_json('due_value');$review=bvmgr_tasks_calendar_json('review');$occurrence=bvmgr_tasks_calendar_json('occurrence_start_utc');
    if ($f['type']==='scheduled') $where[]="$start IS NOT NULL";
    if ($f['type']==='deadline') $where[]="$start IS NULL AND $kind='datetime'";
    if ($f['type']==='date') $where[]="$start IS NULL AND $kind='date'";
    if ($f['type']==='unscheduled') $where[]="$start IS NULL AND $due IS NULL";
    if ($f['focus']==='unassigned') $where[]='COALESCE(t.assignee_user_id,0)=0';
    if ($f['focus']==='overdue') { $where[]="t.status='open' AND $due<%s";$args[]=gmdate('Y-m-d H:i:s'); }
    $base='SELECT t.* FROM %i t WHERE '.implode(' AND ',$where);
    $in_range="(($start<%s AND (($end IS NOT NULL AND $end>%s) OR ($end IS NULL AND $start>=%s))) OR ($kind='datetime' AND $due>=%s AND $due<%s) OR ($kind='date' AND $date>=%s AND $date<%s))";
    $range_args=array_merge($args,array($range['end_utc'],$range['start_utc'],$range['start_utc'],$range['start_utc'],$range['end_utc'],$range['start']->format('Y-m-d'),$range['end']->format('Y-m-d'),$f['range_page']*200));
    $range_sql=$wpdb->prepare($base.' AND '.$in_range.' ORDER BY t.id ASC LIMIT 201 OFFSET %d',...$range_args);
    $range_rows=(array)$wpdb->get_results($range_sql,ARRAY_A);$range_more=count($range_rows)>200;$range_rows=array_slice($range_rows,0,200);
    $issue_ids=array();
    foreach ($state['mirrors']??array() as $m) {
        if (!$manager && (int)$m['user']!==$viewer) continue;
        $c=$state['connections'][$m['user']]??array();
        if (!in_array($m['state']??'',array('synced','not_eligible'),true) || ($c['status']??'')!=='connected' || ($c['calendar_state']??'')!=='ready') $issue_ids[]=(int)$m['task'];
    }
    $issue_ids=array_values(array_unique(array_filter($issue_ids)));
    $attention="($start IS NULL AND $due IS NULL) OR COALESCE($review,'legacy_timing_requires_review')<>'' OR COALESCE(t.assignee_user_id,0)=0 OR $due<%s OR $occurrence IS NOT NULL OR t.assignment_mode='scheduled_role'";
    // Select possible authority warnings, then let the canonical adapter decide the actual review state.
    $attention.=$wpdb->prepare(" OR (t.event_id>0 AND NOT EXISTS (SELECT 1 FROM %i ep WHERE ep.ID=t.event_id AND ep.post_type='vms_event_plan' AND ep.post_status NOT IN ('trash','auto-draft')))
        OR EXISTS (SELECT 1 FROM %i em WHERE em.post_id=t.event_id AND em.meta_key='_vms_event_plan_status' AND em.meta_value IN ('cancelled','archived'))
        OR (t.assignee_user_id>0 AND NOT EXISTS (SELECT 1 FROM %i u WHERE u.ID=t.assignee_user_id AND u.user_status=0))
        OR EXISTS (SELECT 1 FROM %i ul LEFT JOIN %i sp ON sp.ID=CAST(ul.meta_value AS UNSIGNED)
          WHERE ul.user_id=t.assignee_user_id AND ul.meta_key='_vms_staff_id' AND CAST(ul.meta_value AS UNSIGNED)>0 AND
          (sp.ID IS NULL OR sp.post_type<>'vms_staff' OR sp.post_status<>'publish'
           OR EXISTS (SELECT 1 FROM %i other_link WHERE other_link.meta_key='_vms_staff_id' AND other_link.meta_value=ul.meta_value AND other_link.user_id<>t.assignee_user_id)
           OR EXISTS (SELECT 1 FROM %i reverse_link WHERE reverse_link.meta_key='_vms_linked_user_id' AND
             ((reverse_link.post_id=sp.ID AND reverse_link.meta_value NOT IN ('','0',CAST(t.assignee_user_id AS CHAR))) OR (reverse_link.meta_value=CAST(t.assignee_user_id AS CHAR) AND reverse_link.post_id<>sp.ID)))))
        OR ((t.generation_key IS NULL OR t.generation_key='') AND t.task_template_id>0 AND EXISTS (SELECT 1 FROM %i duplicate_task WHERE duplicate_task.event_id=t.event_id AND duplicate_task.task_template_id=t.task_template_id AND duplicate_task.id<>t.id))",
        $wpdb->posts,$wpdb->postmeta,$wpdb->users,$wpdb->usermeta,$wpdb->posts,$wpdb->usermeta,$wpdb->postmeta,bvmgr_tasks_table_name('task_instances'));
    $queue_args=array_merge($args,array(gmdate('Y-m-d H:i:s')));
    $connection_users=array();foreach ($state['connections']??array() as $uid=>$connection) if (($manager||(int)$uid===$viewer) && (($connection['status']??'')==='authorization_required' || (($connection['status']??'')==='connected' && ($connection['calendar_state']??'')!=='ready'))) $connection_users[]=(int)$uid;
    if ($connection_users) { $attention.=' OR t.assignee_user_id IN ('.implode(',',array_fill(0,count($connection_users),'%d')).')';$queue_args=array_merge($queue_args,$connection_users); }

    if ($issue_ids) { $attention.=' OR t.id IN ('.implode(',',array_fill(0,count($issue_ids),'%d')).')';$queue_args=array_merge($queue_args,$issue_ids); }
    $queue_args[]=$f['queue_page']*100;
    $queue_sql=$wpdb->prepare($base.($f['status']==='all'?" AND t.status='open'":'').' AND ('.$attention.') ORDER BY t.id ASC LIMIT 101 OFFSET %d',...$queue_args);
    $queue_rows=(array)$wpdb->get_results($queue_sql,ARRAY_A);$queue_more=count($queue_rows)>100;$queue_rows=array_slice($queue_rows,0,100);
    $rows=array_column(array_merge($range_rows,$queue_rows),null,'id');$tasks=bvmgr_tasks_sync_records(array_values($rows));
    $range_ids=array_fill_keys(array_column($range_rows,'id'),true);
    $cards=array();$queue=array();$days=array();$owners=array();
    for ($day=$range['start'];$day<$range['end'];$day=$day->modify('+1 day')) $days[$day->format('Y-m-d')]=array('date'=>$day,'all_day'=>array(),'timed'=>array());
    foreach ($tasks as $id=>$task) {
        $sync=$sync_unavailable?array('state'=>'unavailable','attention'=>true):bvmgr_tasks_calendar_sync($state,$task);
        $reasons=bvmgr_tasks_calendar_attention($task,$sync);
        if ($f['focus']==='review' && !in_array('review',$reasons,true)) continue;
        if ($f['focus']==='sync' && !$sync['attention']) continue;
        if ($f['focus']==='overdue' && (!in_array('overdue',$reasons,true))) continue;
        $row=$rows[$id];$user=$task['assignee_user_id'];$event=$task['event'];
        $owner=$user?(get_userdata($user)->display_name??__('Unavailable user','backstage-venue-manager')):__('Unassigned','backstage-venue-manager');
        $venue=(int)($event['venue_id']??$row['venue_id']);
        $card=array('id'=>$id,'title'=>$task['title'],'status'=>$task['status'],'revision'=>$task['revision'],'assignee_id'=>$user,'assignee'=>$owner,
            'event_id'=>$task['event_plan_id'],'event_title'=>$event['event_title']??($task['event_plan_id']?get_the_title($task['event_plan_id']):''),
            'event_date'=>$event['event_start_local']??'','venue_id'=>$venue,'venue'=>$venue?get_the_title($venue):'',
            'required'=>(bool)$row['is_required'],'priority'=>$task['priority'],'review'=>$task['review'],'overdue'=>$task['overdue']&&$task['review']==='',
            'timing'=>$task['timing'],'type'=>bvmgr_tasks_calendar_type($task),'sync'=>$sync['state'],'attention'=>$reasons);
        $cards[$id]=$card;
        if (($task['status']==='open' || $f['status']===$task['status'] || ($task['review']!=='' && isset($range_ids[$id]))) && $reasons) $queue[$id]=$card;
        foreach (bvmgr_tasks_calendar_placements($task,$range) as $place) {
            foreach ($days as $ymd=>&$day) {
                $tomorrow=$day['date']->modify('+1 day');
                $overlap=$place['end']?($place['start']<$tomorrow && $place['end']>$day['date']):($place['start']>=$day['date'] && $place['start']<$tomorrow);
                if (!$overlap) continue;
                $item=array('task'=>$id,'kind'=>$place['kind'],'start'=>$place['start']->setTimezone(wp_timezone()),'end'=>$place['end']?$place['end']->setTimezone(wp_timezone()):null);
                $day[$place['kind']==='date'?'all_day':'timed'][]=$item;
                $owners[$ymd][$user]['name']=$owner;$owners[$ymd][$user]['tasks'][$id]=true;
            }
            unset($day);
        }
    }
    foreach ($days as &$day) usort($day['timed'],static fn($a,$b)=>($a['start']<=>$b['start'])?:($a['task']<=>$b['task']));unset($day);
    usort($queue,static fn($a,$b)=>(!empty($b['review'])<=>!empty($a['review']))?:($b['overdue']<=>$a['overdue'])?:($a['id']<=>$b['id']));
    return array('filters'=>$f,'range'=>$range,'days'=>$days,'cards'=>$cards,'queue'=>$queue,'owners'=>$owners,'manager'=>$manager,
        'range_more'=>$range_more,'queue_more'=>$queue_more,'range_scanned'=>count($range_rows),'queue_scanned'=>count($queue_rows),'sync_unavailable'=>$sync_unavailable);
}
