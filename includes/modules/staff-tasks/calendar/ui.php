<?php
defined('ABSPATH') || exit;

function bvmgr_tasks_calendar_url(array $args=array()): string
{
    return add_query_arg(array_merge(array('page'=>'vms-tasks-calendar'),$args),admin_url('admin.php'));
}

function bvmgr_tasks_calendar_select(string $key,string $label,array $options,$value): void
{
    echo '<label>'.esc_html($label).'<select name="'.esc_attr($key).'">';
    foreach ($options as $id=>$caption) echo '<option value="'.esc_attr((string)$id).'" '.selected((string)$value,(string)$id,false).'>'.esc_html($caption).'</option>';
    echo '</select></label>';
}

/** Facets contain task-linked identities only, always scoped to the viewer before aggregation. */
function bvmgr_tasks_calendar_options(array $data): array
{
    global $wpdb;
    $out=array('assignee'=>array(0=>__('All assignees','backstage-venue-manager')),'event'=>array(0=>__('All events','backstage-venue-manager')),'venue'=>array(0=>__('All venues','backstage-venue-manager')));
    $scope=$data['manager']?'1=1':$wpdb->prepare('t.assignee_user_id=%d',get_current_user_id());
    foreach (array('assignee'=>'assignee_user_id','event'=>'event_id','venue'=>'venue_id') as $key=>$column) {
        $join=$key==='assignee'?$wpdb->users:$wpdb->posts;$name=$key==='assignee'?'display_name':'post_title';
        $reference=$key==='venue'?'('.bvmgr_tasks_calendar_venue_sql().')':'t.'.$column;
        $sql=$wpdb->prepare("SELECT DISTINCT $reference AS id, p.$name AS label FROM %i t JOIN %i p ON p.ID=$reference WHERE $scope AND (t.status='open' OR $reference=%d) ORDER BY label ASC, id ASC LIMIT 201",bvmgr_tasks_table_name('task_instances'),$join,$data['filters'][$key]);
        foreach ((array)$wpdb->get_results($sql,ARRAY_A) as $row) $out[$key][(int)$row['id']]=$row['label'];
        $selected=(int)$data['filters'][$key];
        if ($selected && !isset($out[$key][$selected])) $out[$key][$selected]=sprintf(__('Selected filter #%d','backstage-venue-manager'),$selected);
    }
    return $out;
}

function bvmgr_tasks_calendar_labels(): array
{
    return array('open'=>__('Open','backstage-venue-manager'),'done'=>__('Completed','backstage-venue-manager'),'canceled'=>__('Canceled','backstage-venue-manager'),
        'skipped'=>__('Skipped','backstage-venue-manager'),'superseded'=>__('Superseded','backstage-venue-manager'),'scheduled'=>__('Scheduled','backstage-venue-manager'),
        'deadline'=>__('Deadline','backstage-venue-manager'),'start'=>__('Start · end unspecified','backstage-venue-manager'),'date'=>__('Due date','backstage-venue-manager'),
        'unscheduled'=>__('Unscheduled','backstage-venue-manager'),'synced'=>__('Google: synced','backstage-venue-manager'),'pending'=>__('Google: pending','backstage-venue-manager'),
        'update_pending'=>__('Google: update pending','backstage-venue-manager'),'failed_retryable'=>__('Google: retry needed','backstage-venue-manager'),
        'authorization_required'=>__('Google: authorization required','backstage-venue-manager'),'calendar_missing'=>__('Google: calendar needs attention','backstage-venue-manager'),
        'permanent_failure'=>__('Google: failed','backstage-venue-manager'),'blocked_timing_review'=>__('Google: timing review blocked','backstage-venue-manager'),
        'not_connected'=>__('Google: not connected','backstage-venue-manager'),'not_eligible'=>__('Google: not eligible','backstage-venue-manager'),'unavailable'=>__('Google: status unavailable','backstage-venue-manager'));
}

function bvmgr_tasks_calendar_card(array $card,?array $place,array $data): void
{
    $labels=bvmgr_tasks_calendar_labels();$kind=$place['kind']??($card['review']!==''?'review':$card['type']);
    $heading=$kind==='review'?__('Timing review','backstage-venue-manager'):($labels[$kind]??'');
    if ($place && $kind!=='date') {
        $heading=$place['start']->format('g:i a T');
        if ($place['end']) $heading.=' – '.$place['end']->format($place['end']->format('Y-m-d')===$place['start']->format('Y-m-d')?'g:i a T':'M j, g:i a T');
        $heading.=' · '.$labels[$kind];
    }
    if (!$place && $kind!=='review' && is_array($card['timing'])) {
        $t=$card['timing'];$stamp=($t['start_utc']??'')?:($t['due_utc']??'');
        if ($kind==='date') $heading=$t['due_value'].' · '.$heading;
        elseif ($stamp) { try { $heading=(new DateTimeImmutable($stamp,new DateTimeZone('UTC')))->setTimezone(wp_timezone())->format('M j, Y · g:i a T').' · '.$heading; } catch (Throwable $e) {} }
    }
    echo '<article class="bvm-cal-card bvm-cal-card--'.esc_attr($kind).' bvm-cal-status--'.esc_attr($card['status']).'" data-task-id="'.esc_attr((string)$card['id']).'">';
    echo '<p class="bvm-cal-time">'.esc_html($heading).'</p><h4><a href="'.esc_url(bvmgr_tasks_detail_url($card['id'])).'">'.esc_html($card['title']).'</a></h4>';
    echo '<p class="bvm-cal-owner">'.esc_html($card['assignee']).'</p>';
    if ($card['event_id']) {
        $url=get_edit_post_link($card['event_id'],'url');
        echo '<p class="bvm-cal-event">'.($url?'<a href="'.esc_url($url).'">':'').esc_html($card['event_title']?:__('Event unavailable','backstage-venue-manager')).($url?'</a>':'').'</p>';
    }
    echo '<div class="bvm-cal-badges"><span>'.esc_html($labels[$card['status']]??$card['status']).'</span>';
    if ($card['overdue']) echo '<strong>'.esc_html__('Overdue','backstage-venue-manager').'</strong>';
    if (!$card['assignee_id']) echo '<strong>'.esc_html__('Unassigned','backstage-venue-manager').'</strong>';
    if ($card['review']!=='') echo '<strong>'.esc_html__('Timing review','backstage-venue-manager').'</strong>';
    if ($card['priority']==='high') echo '<strong>'.esc_html__('High priority','backstage-venue-manager').'</strong>';
    echo '</div><p class="bvm-cal-sync">'.esc_html($labels[$card['sync']]??$labels['unavailable']).'</p>';
    echo '<details><summary>'.esc_html__('Details and actions','backstage-venue-manager').'</summary><div class="bvm-cal-detail">';
    echo '<p>'.esc_html(sprintf(__('Task #%d · %s','backstage-venue-manager'),$card['id'],$card['required']?__('Required','backstage-venue-manager'):__('Optional','backstage-venue-manager'))).'</p>';
    if ($card['venue']) echo '<p>'.esc_html__('Venue:','backstage-venue-manager').' '.esc_html($card['venue']).'</p>';
    if ($card['event_date']) echo '<p>'.esc_html__('Event occurrence:','backstage-venue-manager').' '.esc_html($card['event_date'].' '.wp_timezone_string()).'</p>';
    $t=$card['timing'];
    if (is_array($t)) {
        echo '<p>'.esc_html__('Task timezone:','backstage-venue-manager').' '.esc_html($t['timezone']??'').'</p>';
        if (!empty($t['start'])) echo '<p>'.esc_html__('Recorded schedule:','backstage-venue-manager').' '.esc_html($t['start'].' – '.($t['end']?:__('End unspecified','backstage-venue-manager'))).'</p>';
        if (!empty($t['due_value'])) echo '<p>'.esc_html__('Due:','backstage-venue-manager').' '.esc_html($t['due_value']).'</p>';
        $relative=($t['schedule_source']??'none')==='event_offset'||($t['due_source']??'fixed')!=='fixed';
        echo '<p>'.esc_html($card['type']==='unscheduled'?__('No calendar timing','backstage-venue-manager'):($relative?__('Event-relative timing · follows canonical reconciliation','backstage-venue-manager'):__('Manually pinned timing','backstage-venue-manager'))).'</p>';
    }
    if ($card['review']!=='') echo '<p>'.esc_html__('Placement paused. Review timing and dependencies in the task editor.','backstage-venue-manager').'</p>';
    echo '<p><a href="'.esc_url(bvmgr_tasks_detail_url($card['id'])).'">'.esc_html__('Open timing and history','backstage-venue-manager').'</a></p>';
    if ($data['manager']) echo '<p><a href="'.esc_url(bvmgr_tasks_admin_page_url('vms-tasks',array('task_instance_id'=>$card['id']))).'">'.esc_html__('Edit or reassign in Staff Tasks','backstage-venue-manager').'</a></p>';
    $self=bvmgr_tasks_current_user_can_complete_self()&&$card['assignee_id']===get_current_user_id();
    $actions=array();
    if ($card['status']==='open' && ($data['manager']||$self)) $actions['done']=__('Complete task','backstage-venue-manager');
    if ($card['status']!=='open' && $card['status']!=='superseded' && $data['manager']) $actions['open']=__('Reopen task','backstage-venue-manager');
    if ($card['status']==='open' && $data['manager']) $actions['canceled']=__('Cancel task','backstage-venue-manager');
    foreach ($actions as $target=>$label) {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('bvmgr_tasks_transition');bvmgr_tasks_command_fields(array('revision'=>$card['revision']));
        foreach (array('action'=>'vms_tasks_transition','instance_id'=>$card['id'],'target_status'=>$target,'return_page'=>'vms-tasks-calendar') as $key=>$value) echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr((string)$value).'">';
        foreach ($data['filters'] as $key=>$value) echo '<input type="hidden" name="'.esc_attr('calendar_'.$key).'" value="'.esc_attr((string)$value).'">';
        if ($target==='canceled') echo '<label>'.esc_html__('Cancellation reason','backstage-venue-manager').'<input name="reason" required maxlength="255"></label>';
        echo '<button class="button" type="submit">'.esc_html($label).'</button></form>';
    }
    echo '</div></details></article>';
}

function bvmgr_tasks_calendar_page(): void
{
    $data=bvmgr_tasks_calendar_read($_GET);
    if (is_wp_error($data)) wp_die(esc_html($data->get_error_message()),'',array('response'=>(int)($data->get_error_data()['status']??403)));
    $f=$data['filters'];$range=$data['range'];$options=bvmgr_tasks_calendar_options($data);$today=wp_date('Y-m-d');
    echo '<div class="wrap bvm-calendar"><header class="bvm-cal-heading"><div><p class="bvm-cal-eyebrow">'.esc_html__('BVM · Staff operations','backstage-venue-manager').'</p><h1>'.esc_html__('Staff Tasks Calendar','backstage-venue-manager').'</h1><p>'.esc_html__('Plan the day. See who owns the week.','backstage-venue-manager').'</p></div><a class="button" href="'.esc_url(bvmgr_tasks_admin_page_url($data['manager']?'vms-tasks':'vms-my-tasks')).'">'.esc_html__('Task list and editor','backstage-venue-manager').'</a></header>';
    bvmgr_tasks_admin_render_notices();
    echo '<nav class="bvm-cal-toolbar" aria-label="'.esc_attr__('Calendar navigation','backstage-venue-manager').'">';
    $step=$f['view']==='day'?1:7;
    foreach (array('previous'=>array($range['start']->modify('-'.$step.' days')->format('Y-m-d'),__('Previous','backstage-venue-manager')),'today'=>array($today,__('Today','backstage-venue-manager')),'next'=>array($range['start']->modify('+'.$step.' days')->format('Y-m-d'),__('Next','backstage-venue-manager'))) as $key=>$nav) echo '<a class="button" rel="'.esc_attr($key==='previous'?'prev':($key==='next'?'next':'')).'" href="'.esc_url(bvmgr_tasks_calendar_url(array_merge($f,array('date'=>$nav[0],'range_page'=>0,'queue_page'=>0)))).'">'.esc_html($nav[1]).'</a>';
    echo '<h2>'.esc_html($range['start']->format('M j, Y').($f['view']==='week'?' – '.$range['end']->modify('-1 day')->format('M j, Y'):'')).'</h2><div class="bvm-cal-switch">';
    foreach (array('day'=>__('Day','backstage-venue-manager'),'week'=>__('Week','backstage-venue-manager')) as $view=>$label) echo '<a class="button '.($f['view']===$view?'button-primary':'').'" '.($f['view']===$view?'aria-current="page"':'').' href="'.esc_url(bvmgr_tasks_calendar_url(array_merge($f,array('view'=>$view,'range_page'=>0,'queue_page'=>0)))).'">'.esc_html($label).'</a>';
    echo '</div></nav><p class="bvm-cal-zone">'.esc_html(sprintf(__('Times shown in %s. Date-only tasks stay on their recorded date.','backstage-venue-manager'),wp_timezone_string())).'</p>';
    echo '<form method="get" class="bvm-cal-filters"><input type="hidden" name="page" value="vms-tasks-calendar"><input type="hidden" name="view" value="'.esc_attr($f['view']).'"><label>'.esc_html__('Go to date','backstage-venue-manager').'<input type="date" name="date" value="'.esc_attr($f['date']).'"></label>';
    if ($data['manager']) bvmgr_tasks_calendar_select('assignee',__('Assignee','backstage-venue-manager'),$options['assignee'],$f['assignee']);
    bvmgr_tasks_calendar_select('event',__('Event','backstage-venue-manager'),$options['event'],$f['event']);
    bvmgr_tasks_calendar_select('venue',__('Venue','backstage-venue-manager'),$options['venue'],$f['venue']);
    bvmgr_tasks_calendar_select('type',__('Task type','backstage-venue-manager'),array(''=>__('All types','backstage-venue-manager'),'scheduled'=>__('Scheduled','backstage-venue-manager'),'deadline'=>__('Deadline only','backstage-venue-manager'),'date'=>__('Date only','backstage-venue-manager'),'unscheduled'=>__('Unscheduled','backstage-venue-manager')),$f['type']);
    bvmgr_tasks_calendar_select('status',__('Status','backstage-venue-manager'),array('all'=>__('All statuses','backstage-venue-manager'),'open'=>__('Open','backstage-venue-manager'),'done'=>__('Completed','backstage-venue-manager'),'canceled'=>__('Canceled','backstage-venue-manager'),'skipped'=>__('Skipped','backstage-venue-manager'),'superseded'=>__('Superseded','backstage-venue-manager')),$f['status']);
    bvmgr_tasks_calendar_select('required',__('Requirement','backstage-venue-manager'),array('all'=>__('Required + optional','backstage-venue-manager'),'1'=>__('Required','backstage-venue-manager'),'0'=>__('Optional','backstage-venue-manager')),$f['required']);
    echo '<input type="hidden" name="focus" value="'.esc_attr($f['focus']).'"><button class="button button-primary">'.esc_html__('Apply filters','backstage-venue-manager').'</button><a href="'.esc_url(bvmgr_tasks_calendar_url(array('date'=>$f['date'],'view'=>$f['view']))).'">'.esc_html__('Reset filters','backstage-venue-manager').'</a></form>';
    echo '<nav class="bvm-cal-shortcuts" aria-label="'.esc_attr__('Task shortcuts','backstage-venue-manager').'">';
    foreach (array(''=>__('Everything','backstage-venue-manager'),'my'=>__('My tasks','backstage-venue-manager'),'unassigned'=>__('Unassigned','backstage-venue-manager'),'overdue'=>__('Overdue','backstage-venue-manager'),'review'=>__('Timing review','backstage-venue-manager'),'sync'=>__('Sync attention','backstage-venue-manager')) as $focus=>$label) {
        if (!$data['manager']&&$focus==='unassigned') continue;
        echo '<a '.($f['focus']===$focus?'aria-current="page"':'').' href="'.esc_url(bvmgr_tasks_calendar_url(array_merge($f,array('focus'=>$focus,'assignee'=>$f['focus']==='my'?0:$f['assignee'],'range_page'=>0,'queue_page'=>0)))).'">'.esc_html($label).'</a>';
    }
    echo '</nav><p><a href="#bvm-cal-attention-title">'.esc_html(sprintf(__('%d unplaced / attention items in this result · Review queue','backstage-venue-manager'),count($data['queue']))).'</a></p><p class="bvm-cal-legend">'.esc_html__('Time blocks = scheduled work · Diamond markers = deadlines · Date area = due dates · Counts show tasks, not workload scores','backstage-venue-manager').'</p>';
    echo '<div class="bvm-cal-layout"><section class="bvm-cal-board bvm-cal-board--'.esc_attr($f['view']).'" aria-label="'.esc_attr__('Dated tasks','backstage-venue-manager').'">';
    foreach ($data['days'] as $ymd=>$day) {
        echo '<section class="bvm-cal-day'.($ymd===$today?' bvm-cal-day--today':'').'" aria-label="'.esc_attr($day['date']->format('l, F j')).'"><header><h3><a href="'.esc_url(bvmgr_tasks_calendar_url(array_merge($f,array('date'=>$ymd,'view'=>'day','range_page'=>0)))).'">'.esc_html($day['date']->format('D')).' <span>'.esc_html($day['date']->format('j')).'</span></a></h3>';
        if (!empty($data['owners'][$ymd])) { echo '<details class="bvm-cal-workload"><summary>'.esc_html(sprintf(__('%d people / assignment groups','backstage-venue-manager'),count($data['owners'][$ymd]))).'</summary>';foreach ($data['owners'][$ymd] as $owner) echo '<p>'.esc_html($owner['name'].' · '.count($owner['tasks'])).'</p>';echo '</details>'; }
        echo '</header><div class="bvm-cal-date-area"><h4>'.esc_html__('Due dates','backstage-venue-manager').'</h4>';
        foreach ($day['all_day'] as $place) bvmgr_tasks_calendar_card($data['cards'][$place['task']],$place,$data);
        if (!$day['all_day']) echo '<p class="bvm-cal-empty">'.esc_html__('No date-only tasks','backstage-venue-manager').'</p>';
        echo '</div><div class="bvm-cal-timed"><h4>'.esc_html__('Schedule and deadlines','backstage-venue-manager').'</h4>';
        foreach ($day['timed'] as $place) bvmgr_tasks_calendar_card($data['cards'][$place['task']],$place,$data);
        if (!$day['timed']) echo '<p class="bvm-cal-empty">'.esc_html__('No timed tasks','backstage-venue-manager').'</p>';
        echo '</div></section>';
    }
    echo '</section><aside class="bvm-cal-attention" aria-labelledby="bvm-cal-attention-title"><h2 id="bvm-cal-attention-title">'.esc_html__('Unplaced & attention','backstage-venue-manager').'</h2><p>'.esc_html__('Matching attention work across dates. Review warnings pause calendar placement.','backstage-venue-manager').'</p>';
    foreach ($data['queue'] as $card) bvmgr_tasks_calendar_card($card,null,$data);
    if (!$data['queue']) echo '<p class="bvm-cal-empty">'.esc_html__('No attention items in this result page.','backstage-venue-manager').'</p>';
    if ($data['queue_more'] || $f['queue_page']) {
        echo '<nav aria-label="'.esc_attr__('Attention pages','backstage-venue-manager').'">';
        if ($f['queue_page']) echo '<a class="button" href="'.esc_url(bvmgr_tasks_calendar_url(array_merge($f,array('queue_page'=>$f['queue_page']-1)))).'">'.esc_html__('Previous attention','backstage-venue-manager').'</a>';
        if ($data['queue_more']) echo '<a class="button" href="'.esc_url(bvmgr_tasks_calendar_url(array_merge($f,array('queue_page'=>$f['queue_page']+1)))).'">'.esc_html__('More attention','backstage-venue-manager').'</a>';
        echo '</nav><p>'.esc_html__('Attention is checked in batches of 100 candidates. Continue to review later items.','backstage-venue-manager').'</p>';
    }
    echo '</aside></div>';
    if ($data['range_more'] || $f['range_page']) {
        echo '<nav class="bvm-cal-pagination" aria-label="'.esc_attr__('Calendar result pages','backstage-venue-manager').'"><p>'.esc_html__('This is a partial calendar: up to 200 dated tasks per result page. Narrow filters or review the other pages before assessing workload.','backstage-venue-manager').'</p>';
        if ($f['range_page']) echo '<a class="button" href="'.esc_url(bvmgr_tasks_calendar_url(array_merge($f,array('range_page'=>$f['range_page']-1)))).'">'.esc_html__('Previous tasks','backstage-venue-manager').'</a>';
        if ($data['range_more']) echo '<a class="button" href="'.esc_url(bvmgr_tasks_calendar_url(array_merge($f,array('range_page'=>$f['range_page']+1)))).'">'.esc_html__('More tasks','backstage-venue-manager').'</a>';
        echo '</nav>';
    }
    echo '</div>';
}

add_action('admin_menu',static function(){
    if (bvmgr_tasks_current_user_can_view_self()) add_submenu_page(bvmgr_tasks_admin_parent_slug(),__('Staff Tasks Calendar','backstage-venue-manager'),__('Tasks Calendar','backstage-venue-manager'),'read','vms-tasks-calendar','bvmgr_tasks_calendar_page');
});
add_action('admin_enqueue_scripts',static function(){
    if (isset($_GET['page']) && is_scalar($_GET['page']) && $_GET['page']==='vms-tasks-calendar' && bvmgr_tasks_current_user_can_view_self()) wp_enqueue_style('bvm-staff-tasks-calendar',plugins_url('assets/css/staff-tasks-calendar.css',dirname(__DIR__,4).'/vms.php'),array(),'1.0.0');
});
add_filter('woocommerce_prevent_admin_access',static function($prevent){
    if (isset($_GET['page']) && is_scalar($_GET['page']) && $_GET['page']==='vms-tasks-calendar' && bvmgr_tasks_current_user_can_view_self()) return false;
    return $prevent;
});
add_action('admin_notices',static function(){
    if (!bvmgr_tasks_current_user_can_view_self()) return;
    $page=isset($_GET['page'])&&is_scalar($_GET['page'])?(string)$_GET['page']:'';
    if (!in_array($page,array('vms-tasks','vms-my-tasks','vms-dashboard','vms-event-command-center'),true)) return;
    $args=array();if ($page==='vms-my-tasks') $args['focus']='my';
    if ($page==='vms-event-command-center' && isset($_GET['plan_id']) && is_scalar($_GET['plan_id'])) $args['event']=absint($_GET['plan_id']);
    echo '<p><a class="button" href="'.esc_url(bvmgr_tasks_calendar_url($args)).'">'.esc_html__('Open Staff Tasks Calendar','backstage-venue-manager').'</a></p>';
});
