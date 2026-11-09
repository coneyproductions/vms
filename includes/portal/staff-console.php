<?php
/** Minimal personal summary; canonical staffing owns resolution, ownership and response writes. */
defined('ABSPATH') || exit;
function bvmgr_staff_console_staff_id(): int {
    if(!is_user_logged_in()||!function_exists('bvmgr_staff_portal_get_assignment_rows'))return 0;
    $id=(int)get_user_meta(get_current_user_id(),'_vms_staff_id',true);
    return $id>0 && bvmgr_staffing_assignment_owned_by(array('staff_id'=>$id),get_current_user_id()) ? $id : 0;
}
function bvmgr_staff_console_permission(WP_REST_Request $r) {
    return function_exists('vms_ops_staff_console_permission')?vms_ops_staff_console_permission($r,bvmgr_staff_console_staff_id()>0):new WP_Error('console_unavailable','Console unavailable.',array('status'=>503));
}
function bvmgr_staff_console_private(array $data): WP_REST_Response {$r=rest_ensure_response($data);$r->header('Cache-Control','private, no-store, max-age=0');$r->header('Vary','Cookie');return $r;}
add_action('rest_api_init',static function(){
    register_rest_route('bvm/v1','/staff-console/work',array('methods'=>'GET','permission_callback'=>'bvmgr_staff_console_permission','callback'=>'bvmgr_staff_console_work'));
    register_rest_route('bvm/v1','/staff-console/respond',array('methods'=>'POST','permission_callback'=>'bvmgr_staff_console_permission','callback'=>'bvmgr_staff_console_respond'));
});
function bvmgr_staff_console_work(WP_REST_Request $r){
    $permission=bvmgr_staff_console_permission($r);if(is_wp_error($permission))return $permission;
    $out=array();foreach(bvmgr_staff_portal_get_assignment_rows(bvmgr_staff_console_staff_id(),100)as$row){
        $native=bvmgr_staffing_lifecycle_row((int)$row['assignment_id']);
        if(!$native||!bvmgr_staffing_assignment_owned_by($native,get_current_user_id()))continue;
        $safe=array_intersect_key($row,array_flip(array('assignment_id','event_title','date_label','role_label','shift_label','assignment_status','start_ts','end_ts')));
        ob_start();bvmgr_staffing_render_lifecycle_controls(0,'staff',(int)$row['assignment_id']);$safe['controls']=(string)ob_get_clean();
        // The console has its own guarded click transport; never load the portal's global handler here.
        wp_dequeue_script('bvmgr-staffing-lifecycle');$out[]=$safe;
    }
    return bvmgr_staff_console_private(array('assignments'=>$out));
}
function bvmgr_staff_console_respond(WP_REST_Request $r){
    $permission=bvmgr_staff_console_permission($r);if(is_wp_error($permission))return $permission;
    $input=$r->get_json_params();if(!is_array($input))return new WP_Error('invalid','Invalid response.',array('status'=>400));
    $result=bvmgr_staffing_handle_lifecycle_request($input,'staff','POST');$safe=bvmgr_staffing_lifecycle_public_result($result);
    if(empty($result['ok']))return new WP_Error($safe['error']??'assignment_failed',bvmgr_staffing_lifecycle_message($safe['error']??''),array('status'=>in_array($safe['error']??'',array('forbidden','invalid_nonce'),true)?403:409));
    return bvmgr_staff_console_private($safe);
}
