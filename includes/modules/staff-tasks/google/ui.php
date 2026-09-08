<?php
defined('ABSPATH') || exit;

function bvmgr_google_form(string $action, string $label): void
{
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
    wp_nonce_field('bvmgr_google_action');
    echo '<input type="hidden" name="action" value="bvmgr_google_action"><input type="hidden" name="operation" value="'.esc_attr($action).'">';
    if ($action==='recover') echo '<label>'.esc_html__('BVM calendar ID (from Google Calendar settings)','backstage-venue-manager').' <input name="calendar_id" maxlength="1024" required></label> ';
    if ($action==='replace') { $c=bvmgr_google_state()['connections'][get_current_user_id()]??array(); echo '<input type="hidden" name="expected" value="'.esc_attr(hash('sha256',$c['calendar']??'')).'"><label><input type="checkbox" name="confirm" value="yes" required> '.esc_html__('I confirm the old BVM calendar is deleted and want one replacement.','backstage-venue-manager').'</label> '; }
    echo '<button class="button">'.esc_html($label).'</button></form>';
}
function bvmgr_google_page(): void
{
    if (!bvmgr_tasks_current_user_can_view_self()) wp_die('Forbidden','',array('response'=>403));
    $state=bvmgr_google_state(); $user=get_current_user_id(); $own=$state['connections'][$user]??array();
    foreach ($state['mirrors'] as $key=>$mirror) $state['mirrors'][$key]=bvmgr_google_mirror_view($state,$mirror);
    echo '<div class="wrap"><h1>'.esc_html__('Google Calendar — BVM Tasks','backstage-venue-manager').'</h1><p>'.esc_html__('BVM manages your assigned tasks in a dedicated secondary calendar. Edit tasks in BVM. One-minute deadline/start markers are not work-duration blocks. Task instructions and private documents are not sent to Google.','backstage-venue-manager').'</p>';
    echo '<p>'.esc_html(bvmgr_google_configured()?'Site Google credentials configured.':'Site Google credentials not configured. Ask your administrator to complete setup.').'</p>';
    $visible=current_user_can('manage_options')?$state['connections']:array($user=>$own);
    foreach ($visible as $id=>$c) {
        $counts=array(); foreach ($state['mirrors'] as $m) if ($m['user']===(int)$id) $counts[$m['state']]=($counts[$m['state']]??0)+1;
        echo '<h2>'.esc_html('BVM user #'.$id).'</h2><p>'.esc_html('Connection: '.($c['status']??'not_connected').' · Calendar: '.($c['calendar_state']??'not_created').' · Last successful sync: '.(!empty($c['last_success'])?gmdate('Y-m-d H:i:s',$c['last_success']).' UTC':'never')).'</p>';
        if (!empty($c['calendar_error'])) echo '<p>'.esc_html('Calendar check: '.$c['calendar_error']).'</p>';
        if (!empty($c['oauth_error'])) echo '<p>'.esc_html($c['oauth_error']).'</p>';
        foreach ($counts as $status=>$count) echo '<p>'.esc_html($status.': '.$count).'</p>';
    }
    if (bvmgr_google_allowed_user($user) && bvmgr_google_configured()) {
        bvmgr_google_form('connect',empty($own['subject'])?'Connect Google account':'Reconnect the same Google account');
        if (!empty($own['subject'])) { bvmgr_google_form('sync','Sync Now'); bvmgr_google_form('disconnect','Disconnect (keep calendar and history)'); }
        if (($own['calendar_state']??'')==='create_failed') bvmgr_google_form('retry_calendar','Retry rejected calendar creation');
        if (in_array($own['calendar_state']??'',array('creation_uncertain','missing'),true)) {
            echo '<p>'.esc_html__('Calendar creation or availability needs review. In Google Calendar settings, find the BVM Tasks calendar and copy its calendar ID below. BVM verifies its application marker before adoption. No automatic replacement calendar will be created.','backstage-venue-manager').'</p>';
            bvmgr_google_form('recover','Recover existing BVM calendar');
            if (($own['calendar_state']??'')==='missing') bvmgr_google_form('replace','Replace confirmed missing calendar');
        }
    }
    echo '<h2>'.esc_html__('Task synchronization status','backstage-venue-manager').'</h2>';
    foreach ($state['mirrors'] as $m) if ($m['user']===$user || current_user_can('manage_options')) {
        echo '<p>'.esc_html('Task #'.$m['task'].' · user #'.$m['user'].' · '.$m['state'].' · '.($m['error']??'')).'</p>';
        if (!empty($m['history'])) {
            echo '<details><summary>'.esc_html__('Recent synchronization attempts','backstage-venue-manager').'</summary>';
            foreach ($m['history'] as $h) echo '<p>'.esc_html(gmdate('Y-m-d H:i:s',$h['at']).' UTC · '.$h['state'].' · HTTP '.$h['http']).'</p>';
            echo '</details>';
        }
    }
    // Include unsynced/unconnected tasks as a pure projection; never create status records on reads.
    foreach (bvmgr_google_tasks() as $p) if ($p['assignee_user_id']===$user || current_user_can('manage_options')) {
        $key=$p['assignee_user_id'].':'.$p['task_id']; if (isset($state['mirrors'][$key])) continue;
        $status=($p['review']??'')!==''?'blocked_timing_review':($p['assignee_user_id']?'not_connected':'not_eligible');
        echo '<p>'.esc_html('Task #'.$p['task_id'].' · '.$status).'</p>';
    }
    if (current_user_can('manage_options')) echo '<p>'.esc_html__('Configure BVM_GOOGLE_CLIENT_ID, BVM_GOOGLE_CLIENT_SECRET and BVM_GOOGLE_TOKEN_KEY in the server environment/wp-config.php. Use a Web application OAuth client, Calendar API, openid and calendar.app.created only. Register this exact redirect URI:','backstage-venue-manager').' <code>'.esc_html(bvmgr_google_redirect_uri()).'</code></p>';
    echo '</div>';
}
add_action('admin_menu',static function(){ add_submenu_page(current_user_can('list_users')?'users.php':'profile.php','BVM Tasks Google Calendar','BVM Tasks Google Calendar','read','bvm-google-tasks','bvmgr_google_page'); });
add_filter('woocommerce_prevent_admin_access',static function($prevent){
    return isset($_GET['page']) && is_string($_GET['page']) && $_GET['page']==='bvm-google-tasks' && bvmgr_tasks_current_user_can_view_self()?false:$prevent;
});
add_action('admin_post_bvmgr_google_action',static function(){
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST' || !bvmgr_tasks_current_user_can_view_self()) wp_die('Forbidden','',array('response'=>403));
    check_admin_referer('bvmgr_google_action');
    $op=isset($_POST['operation']) && is_string($_POST['operation'])?sanitize_key(wp_unslash($_POST['operation'])):'';
    $user=get_current_user_id();
    if ($op==='connect') {
        $url=bvmgr_google_begin($user,wp_get_session_token());
        if (is_string($url)) { wp_redirect($url); exit; }
    } elseif ($op==='sync') bvmgr_google_tick(true,$user);
    elseif (in_array($op,array('recover','disconnect','replace','retry_calendar'),true)) bvmgr_google_exclusive(static function() use ($op,$user){
        $state=bvmgr_google_state(); if (!isset($state['connections'][$user])) return;
        if ($op==='disconnect') bvmgr_google_disconnect($state,$user);
        elseif ($op==='retry_calendar') bvmgr_google_retry_calendar($state,$user);
        elseif ($op==='replace') { if (($_POST['confirm']??'')==='yes' && is_string($_POST['expected']??null)) bvmgr_google_replace_missing($state,$user,wp_unslash($_POST['expected'])); }
        else { $id=isset($_POST['calendar_id']) && is_string($_POST['calendar_id'])?sanitize_text_field(wp_unslash($_POST['calendar_id'])):''; bvmgr_google_adopt($state,$user,$id); }
    });
    wp_safe_redirect(bvmgr_google_page_url()); exit;
});
// Only the original authenticated WordPress session can complete the grant exchange.
add_action('admin_post_bvmgr_google_callback',static function(){
    nocache_headers(); header('Referrer-Policy: no-referrer');
    $value=static function($key){ return isset($_GET[$key]) && is_string($_GET[$key])?wp_unslash($_GET[$key]):''; };
    bvmgr_google_callback(get_current_user_id(),wp_get_session_token(),$value('state'),$value('code'));
    wp_safe_redirect(bvmgr_google_page_url()); exit;
});
// An expired WP session cannot consume OAuth state; clear the callback URL without exchanging anything.
add_action('admin_post_nopriv_bvmgr_google_callback',static function(){
    nocache_headers(); header('Referrer-Policy: no-referrer');
    wp_safe_redirect(wp_login_url(bvmgr_google_page_url())); exit;
});
