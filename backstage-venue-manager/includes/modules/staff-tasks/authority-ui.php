<?php
defined('ABSPATH') || exit;
function bvmgr_tasks_detail_url(int $id): string { return add_query_arg(array('page'=>'vms-task-detail','task_id'=>$id),admin_url('admin.php')); }
function bvmgr_tasks_command_fields(array $row): void
{
    echo '<input type="hidden" name="revision" value="'.esc_attr((string)($row['revision']??0)).'"><input type="hidden" name="operation_id" value="'.esc_attr(wp_generate_uuid4()).'">';
}
function bvmgr_tasks_timing_summary(array $row): void
{
    $p=bvmgr_tasks_sync_record((int)$row['id']); if (!$p) return;
    $t=$p['timing'];
    echo '<p><a href="'.esc_url(bvmgr_tasks_detail_url((int)$row['id'])).'">'.esc_html__('Timing and history','backstage-venue-manager').'</a> · '.esc_html($t?($t['schedule_source']==='none'?'Due only / no time block':'Scheduled: '.$t['start'].' – '.$t['end'].' '.$t['timezone'].' ('.($t['schedule_source']==='fixed'?'manually pinned':'event-relative').')'):'Legacy timing requires review').'</p>';
    if ($p['review']!=='') echo '<p>'.esc_html('Review: '.$p['review']).'</p>';
}
function bvmgr_tasks_request_gate(): void
{
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST') wp_die(esc_html__('Task changes require POST.','backstage-venue-manager'),'',array('response'=>405));
    foreach ($_POST as $key=>$value) if (is_array($value) && $key!=='timing') wp_die('Invalid task request.','',array('response'=>400));
    $action=(string)($_POST['action']??'');
    if ($action==='vms_tasks_generate_event') return;
    if (!is_scalar($_POST['operation_id']??null) || !preg_match('/^[a-zA-Z0-9_-]{8,64}$/D',(string)$_POST['operation_id'])) wp_die('Missing task request identity. Refresh the form.','',array('response'=>400));
    $revision=null;
    if (in_array($action,array('vms_tasks_transition','vms_tasks_update_assignment','vms_tasks_save_timing'),true)) {
        if (!is_scalar($_POST['revision']??null) || !preg_match('/^\d{1,18}$/D',(string)$_POST['revision'])) wp_die('Missing task revision. Refresh the form.','',array('response'=>400));
        $revision=(int)$_POST['revision'];
    }
    $GLOBALS['bvmgr_tasks_request']=array('operation'=>(string)$_POST['operation_id'],'revision'=>$revision);
}
foreach (array('vms_tasks_transition','vms_tasks_generate_event','vms_tasks_update_assignment','vms_tasks_create_one_off','vms_tasks_save_timing') as $action) add_action('admin_post_'.$action,'bvmgr_tasks_request_gate',1);
add_action('wp_ajax_vms_tasks_create_one_off_ajax','bvmgr_tasks_request_gate',1);
function bvmgr_tasks_timing_input(array $input): array
{
    $result=array();
    foreach (array('timezone','due_kind','due_source','due_value','due_clock','due_offset','schedule_source','start','end','start_offset','duration_minutes','cancellation_policy') as $key) {
        if (isset($input[$key])) { if (!is_scalar($input[$key])) throw new RuntimeException('invalid_timing_input'); $result[$key]=sanitize_text_field((string)wp_unslash($input[$key])); }
    }
    return $result;
}
function bvmgr_tasks_timing_fields(array $t): void
{
    $defaults=array('timezone'=>wp_timezone_string(),'due_kind'=>'none','due_source'=>'fixed','due_value'=>'','due_clock'=>'10:00','due_offset'=>0,'schedule_source'=>'none','start'=>'','end'=>'','start_offset'=>0,'duration_minutes'=>0,'cancellation_policy'=>'review'); $t=array_merge($defaults,$t);
    $choices=array('due_kind'=>array('none'=>'No due date','date'=>'Date only (due by end of day)','datetime'=>'Due date and time'),
        'due_source'=>array('fixed'=>'Manually pinned','event_offset'=>'Minutes from event start','event_date'=>'Event date and local due clock'),
        'schedule_source'=>array('none'=>'No scheduled time block','fixed'=>'Manually pinned time block','event_offset'=>'Minutes from event start'),
        'cancellation_policy'=>array('review'=>'Flag for operator review','retain'=>'Keep task for cleanup / follow-up','cancel'=>'Cancel open task with event'));
    $labels=array('timezone'=>'Timezone','due_kind'=>'Due type','due_source'=>'Due timing source','due_value'=>'Pinned due date / date and time','due_clock'=>'Due clock on event date (HH:MM)',
        'due_offset'=>'Due offset in minutes','schedule_source'=>'Scheduled timing source','start'=>'Pinned scheduled start','end'=>'Pinned scheduled end (optional)',
        'start_offset'=>'Scheduled offset in minutes','duration_minutes'=>'Scheduled duration (0 means no end)','cancellation_policy'=>'If event is canceled');
    echo '<fieldset><legend>'.esc_html__('Due and scheduled timing','backstage-venue-manager').'</legend><p>'.esc_html__('Due dates do not reserve a time block. Event-relative rules follow the Event Plan; pinned times stay fixed. Use YYYY-MM-DD or YYYY-MM-DD HH:MM. Repeated or nonexistent DST clocks require an unambiguous time.','backstage-venue-manager').'</p>';
    foreach ($labels as $key=>$label) {
        echo '<p><label>'.esc_html($label).' ';
        if (isset($choices[$key])) { echo '<select name="timing['.esc_attr($key).']">'; foreach ($choices[$key] as $value=>$caption) echo '<option value="'.esc_attr($value).'" '.selected((string)$t[$key],$value,false).'>'.esc_html($caption).'</option>'; echo '</select>'; }
        else echo '<input name="timing['.esc_attr($key).']" value="'.esc_attr((string)$t[$key]).'">';
        echo '</label></p>';
    }
    echo '</fieldset>';
}
function bvmgr_tasks_detail_page(): void
{
    global $wpdb; $id=isset($_GET['task_id'])&&is_scalar($_GET['task_id'])?absint($_GET['task_id']):0; $row=bvmgr_tasks_get_instance($id);
    $manager=bvmgr_tasks_current_user_can_manage_all();
    if (!$row || (!$manager && (!bvmgr_tasks_current_user_can_view_self() || (int)$row['assignee_user_id']!==get_current_user_id()))) wp_die('Task unavailable.','',array('response'=>403));
    echo '<div class="wrap"><h1>'.esc_html($row['title']).'</h1><p>'.esc_html('Task #'.$id.' · '.$row['status'].' · revision '.($row['revision']??0)).'</p>';
    bvmgr_tasks_admin_render_notices(); bvmgr_tasks_timing_summary($row);
    echo '<p>'.esc_html('Assigned to: '.($row['assignee_user_id']?(get_userdata((int)$row['assignee_user_id'])->display_name??'Unavailable user'):'Unassigned')).'</p>';
    if ($manager && $row['status']==='open') {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="vms_tasks_save_timing"><input type="hidden" name="instance_id" value="'.esc_attr((string)$id).'">';
        wp_nonce_field('bvmgr_tasks_timing_'.$id); bvmgr_tasks_command_fields($row);
        $t=json_decode((string)($row['timing_json']??''),true)?:array('due_kind'=>empty($row['due_at_local'])?'none':'datetime','due_value'=>$row['due_at_local']??'');
        bvmgr_tasks_timing_fields($t); echo '<button class="button button-primary">'.esc_html__('Save task timing','backstage-venue-manager').'</button></form>';
    }
    echo '<h2>'.esc_html__('Task history','backstage-venue-manager').'</h2>';
    $logs=(array)$wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE task_instance_id=%d ORDER BY id DESC',bvmgr_tasks_table_name('task_logs'),$id),ARRAY_A);
    foreach ($logs as $log) echo '<details><summary>'.esc_html($log['created_at'].' UTC · '.$log['action'].' · user '.($log['actor_user_id']?:'system')).'</summary><pre style="white-space:pre-wrap">'.esc_html($log['details']).'</pre></details>';
    echo '<h2>'.esc_html__('Task notification history','backstage-venue-manager').'</h2>';
    $notifications=(array)$wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE source='vms_staff_tasks' ORDER BY id DESC",bvmgr_notify_log_table_name()),ARRAY_A);
    foreach ($notifications as $n) { $work=json_decode($n['payload_json'],true); if (!in_array($id,array_column($work['tasks']??array(),'id'),true)) continue; echo '<p>'.esc_html($n['created_at'].' UTC · '.$n['status'].' · '.$n['recipient_address'].' · '.($n['status']==='sent'?'Accepted by transport; inbox unverified':$n['error_message'])).'</p>'; }
    echo '<p><a href="'.esc_url(bvmgr_tasks_admin_page_url($manager?'vms-tasks':'vms-my-tasks')).'">'.esc_html__('Back to tasks','backstage-venue-manager').'</a></p></div>';
}
add_action('admin_menu',static function(){add_submenu_page(null,'Task timing and history','Task timing and history','read','vms-task-detail','bvmgr_tasks_detail_page');});
add_action('admin_post_vms_tasks_save_timing',static function(){
    $id=absint($_POST['instance_id']??0); check_admin_referer('bvmgr_tasks_timing_'.$id);
    try { $input=bvmgr_tasks_timing_input((array)($_POST['timing']??array())); $result=bvmgr_tasks_command('timing',$id,$input,bvmgr_tasks_request_revision(),bvmgr_tasks_request_operation()); }
    catch (Throwable $e) { $result=new WP_Error('invalid_timing',$e->getMessage()); }
    bvmgr_tasks_admin_redirect_url_with_notice(bvmgr_tasks_detail_url($id),is_wp_error($result)?'error':'success',is_wp_error($result)?$result->get_error_message():'Task timing saved.');
});
function bvmgr_tasks_upgrade_notice(): void
{
    if (!current_user_can('manage_options') || bvmgr_tasks_authority_ready()) return;
    echo '<div class="notice notice-warning"><p>'.esc_html__('Staff Tasks requires an explicit reliability update before new changes. Existing task history remains available.','backstage-venue-manager').'</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
    wp_nonce_field('bvmgr_tasks_upgrade'); echo '<input type="hidden" name="action" value="vms_tasks_upgrade"><button class="button">'.esc_html__('Update Staff Tasks reliability','backstage-venue-manager').'</button></form></div>';
}
add_action('admin_notices','bvmgr_tasks_upgrade_notice');
add_action('admin_post_vms_tasks_upgrade',static function(){
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST' || !current_user_can('manage_options')) wp_die('Forbidden','',array('response'=>403)); check_admin_referer('bvmgr_tasks_upgrade');
    bvmgr_tasks_install_authority(); wp_safe_redirect(bvmgr_tasks_admin_page_url('vms-tasks')); exit;
});

function bvmgr_tasks_template_schedule_fields(array $template): void
{
    $t=json_decode((string)($template['timing_json']??''),true)?:array();
    echo '<fieldset><legend>'.esc_html__('Generated task scheduling','backstage-venue-manager').'</legend><p>'.esc_html__('These rules apply to future instances only. Due timing uses the existing due controls below.','backstage-venue-manager').'</p><p><label>'.esc_html__('Scheduled block','backstage-venue-manager').' <select name="timing[schedule_source]">';
    foreach (array('none'=>'No scheduled time block','event_offset'=>'Relative to event start') as $value=>$label) echo '<option value="'.esc_attr($value).'" '.selected($t['schedule_source']??'none',$value,false).'>'.esc_html($label).'</option>';
    echo '</select></label></p><p><label>'.esc_html__('Minutes from event start','backstage-venue-manager').' <input type="number" name="timing[start_offset]" value="'.esc_attr((string)($t['start_offset']??0)).'"></label> <label>'.esc_html__('Duration in minutes (0 = no end)','backstage-venue-manager').' <input type="number" min="0" name="timing[duration_minutes]" value="'.esc_attr((string)($t['duration_minutes']??0)).'"></label></p><p><label>'.esc_html__('Event cancellation','backstage-venue-manager').' <select name="timing[cancellation_policy]">';
    foreach (array('review'=>'Require operator review','retain'=>'Keep cleanup/follow-up work','cancel'=>'Cancel open task') as $value=>$label) echo '<option value="'.esc_attr($value).'" '.selected($t['cancellation_policy']??'review',$value,false).'>'.esc_html($label).'</option>';
    echo '</select></label></p></fieldset>';
}

// WooCommerce's subscriber redirect must not hide the existing, capability-gated task screens.
add_filter('woocommerce_prevent_admin_access',static function($prevent){
    $page=isset($_GET['page'])&&is_scalar($_GET['page'])?(string)$_GET['page']:'';
    if (in_array($page,array('vms-my-tasks','vms-task-detail'),true) && bvmgr_tasks_current_user_can_view_self()) return false;
    return $prevent;
});
