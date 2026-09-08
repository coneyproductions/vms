<?php
require dirname(__DIR__).'/staffing-lifecycle/bootstrap.php';
require __DIR__.'/fake.php';
if (($argv[1]??'')==='anonymous_callback') {
    require_once dirname(__DIR__,2).'/includes/modules/staff-tasks/google/ui.php';
    wp_set_current_user(0); $_GET['code']='must-not-survive-callback';
    add_filter('wp_redirect',static function($url){echo $url;return $url;});
    do_action('admin_post_nopriv_bvmgr_google_callback');
}
$r=bvmgr_google_tick(false);
echo is_wp_error($r)?$r->get_error_code():(string)$r;
