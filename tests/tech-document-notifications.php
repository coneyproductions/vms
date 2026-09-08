<?php
/** Deterministic runtime contracts. No WordPress database, network or mail transport. */
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
$GLOBALS['checks'] = 0;
function check($value, string $label): void { if (!$value) { throw new RuntimeException($label); } $GLOBALS['checks']++; }
function __($s, $domain = '') { return $s; }
function esc_html__($s, $domain = '') { return htmlspecialchars($s); }
function esc_html($s) { return htmlspecialchars((string) $s); }
function esc_attr($s) { return esc_html($s); }
function esc_url($s) { return esc_html($s); }
function wp_kses_post($s) { return $s; }
function absint($v) { return abs((int) $v); }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($s)); }
function sanitize_title($s) { return strtolower(preg_replace('/[ _]+/', '-', $s)); }
function sanitize_text_field($s) { return strip_tags((string) $s); }
function sanitize_textarea_field($s) { return (string) $s; }
function sanitize_file_name($s) { return basename($s); }
function sanitize_email($s) { return trim($s); }
function is_email($s) { return filter_var($s, FILTER_VALIDATE_EMAIL); }
function wp_json_encode($s, $flags = 0) { return json_encode($s, $flags); }
function wp_date($format, $ts = null, $tz = null) { return gmdate($format, $ts ?? time()); }
function wp_timezone() { return new DateTimeZone('UTC'); }
function wp_timezone_string() { return 'UTC'; }
function current_time($format, $gmt = false) { return gmdate('Y-m-d H:i:s'); }
function wp_generate_uuid4() { return 'attempt-' . ++$GLOBALS['uuid']; }
function bvmgr_private_storage_config() { return $GLOBALS['storage_ready'] ? array('site' => $GLOBALS['tmp']) : new WP_Error('not_ready', 'Not ready'); }
function bvmgr_private_storage_safe_file($path) { return bvmgr_private_files_path_is_safe($path); }
function current_user_can($cap, ...$args) { return $GLOBALS['can_edit']; }
function check_admin_referer($action, $name) { if (($_POST[$name] ?? '') !== 'valid') throw new RuntimeException('nonce rejected'); }
function wp_die($message, ...$args) { throw new RuntimeException($message); }
function get_term_meta($id, $key, $single) { return $GLOBALS['role_settings'][$id] ?? ''; }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { $GLOBALS['meta'][$id][$key] = $value; return true; }
function get_post_type($id) { return $id === 10 ? 'vms_event_plan' : ($id === 20 ? 'vms_vendor' : 'vms_staff'); }
function get_post_status($id) { return 'publish'; }
function get_the_title($id) { return 'Fixture ' . $id; }
function get_userdata($id) { return $GLOBALS['users'][$id] ?? false; }
function get_users($args) { return $GLOBALS['user_matches'] ?? array(); }
function get_current_user_id() { return $GLOBALS['actor']; }
function wp_set_current_user($id) { $GLOBALS['actor'] = $id; }
function bvmgr_calendar_plan_vendor_ids($id) { return array('band_id' => 20); }
function bvmgr_event_plan_get_status($id, $context) { return $GLOBALS['plan_status']; }
function bvmgr_staff_portal_visible_event_statuses() { return array('confirmed', 'ready', 'published', 'tentative'); }
function bvmgr_staffing_get_event_slots($id) { return $GLOBALS['slots']; }
function bvmgr_vendor_portal_user_can_download_tech_doc($vendor, $key, $plan) { return $GLOBALS['access'] && get_current_user_id() > 0; }
function admin_url($s = '') { return $GLOBALS['base_url'] . '/wp-admin/' . $s; }
function wp_parse_url($url, $part) { return parse_url($url, $part); }
function bvmgr_private_files_path_is_safe($path) { return str_starts_with($path, $GLOBALS['tmp']); }
function get_posts($args) { return $GLOBALS['plans']; }
function add_action($key, $callback, $priority = 10, $accepted = 1) { $GLOBALS['hooks'][$key][] = $callback; }
function add_filter($key, $callback, $priority = 10, $accepted = 1) {}
function wp_mail($to, $subject, $body, $headers = array()) { $GLOBALS['mail'][] = compact('to', 'subject', 'body'); if ($GLOBALS['throw']) throw new RuntimeException('/private/never-log-this'); return $GLOBALS['transport']; }
function bvmgr_record_operational_issue($event, $context) { $GLOBALS['issues'][] = $event; return true; }
class WP_Error { function __construct(public $code, public $message) {} function get_error_message() { return $this->message; } }
function is_wp_error($value) { return $value instanceof WP_Error; }
function bvmgr_vendor_portal_tech_doc_payload($vendor, $key) {
    $id = (int) get_post_meta($vendor, '_vms_' . $key . '_attachment_id', true);
    return $GLOBALS['files'][$id] ?? new WP_Error('missing', 'Unavailable');
}
function bvmgr_vendor_portal_tech_doc_meta_key($key) { return '_vms_' . $key . '_attachment_id'; }
function bvmgr_vendor_portal_tech_doc_storage_kind_meta_key($key) { return '_vms_' . $key . '_storage_kind'; }
function bvmgr_request_method() { return $GLOBALS['method']; }
function wp_unslash($value) { return $value; }
function wp_verify_nonce($nonce, $action) { return $nonce === 'valid'; }
function bvmgr_nonce_action_for_value($nonce, $action) { return $action; }
function bvmgr_upload_request_has_file($files, $key) { return isset($files[$key]); }
function bvmgr_vendor_portal_store_tech_doc_upload($id, $field) { return $_FILES[$field]; }
function bvmgr_private_files_delete($id) { unset($GLOBALS['files'][$id]); }
function bvmgr_vendor_flag_vendor_update($id, $context) { $GLOBALS['review'][] = $context; }
function bvmgr_portal_notice($type, $message) { return $message; }
function bvmgr_vendor_portal_tech_doc_download_url($vendor, $key, $plan = 0) { return admin_url('admin-post.php?action=download&doc_key=' . $key); }
function wp_nonce_field($action, $name) { echo '<input name="' . $name . '" value="valid">'; }
class TechTestDB {
    public $prefix = 'wp_'; public $dbname = 'fixture'; public $rows = array(); public $last_error = ''; public $lock = true; public $insert_ok = true;
    function prepare($sql, ...$args) { return array('sql' => $sql, 'args' => $args); }
    function get_var($q) { return str_contains($q['sql'], 'GET_LOCK') ? (int) $this->lock : 1; }
    function get_results($q, $type) { return array_reverse(array_values(array_filter($this->rows, fn($r) => $r['source'] === $q['args'][1] && $r['event_key'] === $q['args'][2]))); }
    function insert($table, $row, $format) { if (!$this->insert_ok) return false; $row['id'] = count($this->rows) + 1; $this->rows[] = $row; return 1; }
}
require dirname(__DIR__) . '/includes/core/notifications.php';
require dirname(__DIR__) . '/includes/core/tech-document-notifications.php';
require dirname(__DIR__) . '/includes/admin/tech-document-notifications.php';
$portal = file_get_contents(dirname(__DIR__) . '/includes/portal/vendor-portal.php');
$start = strpos($portal, "if (!function_exists('bvmgr_vendor_portal_render_tech_docs'))");
$end = strpos($portal, "if (!function_exists('bvmgr_vendor_portal_render_profile'))", $start);
eval(substr($portal, $start, $end - $start));
$GLOBALS['tmp'] = sys_get_temp_dir() . '/bvm-tech-doc-tests-' . bin2hex(random_bytes(6)); mkdir($GLOBALS['tmp']);
function fixture_file($id, $content) { $path = $GLOBALS['tmp'] . '/' . $id . '.pdf'; file_put_contents($path, $content); $GLOBALS['files'][$id] = array('file_id' => $id, 'path' => $path, 'filename' => 'stage-' . $id . '.pdf', 'mime' => 'application/pdf', 'storage_kind' => 'private_file'); }
function reset_fixture() {
    $GLOBALS['wpdb'] = new TechTestDB(); $GLOBALS['uuid'] = 0; $GLOBALS['actor'] = 1; $GLOBALS['mail'] = $GLOBALS['issues'] = $GLOBALS['review'] = array();
    $GLOBALS['role_settings'] = $GLOBALS['user_matches'] = array(); $GLOBALS['storage_ready'] = $GLOBALS['can_edit'] = $GLOBALS['transport'] = $GLOBALS['access'] = true; $GLOBALS['throw'] = false; $GLOBALS['base_url'] = 'https://fixture.test';
    $GLOBALS['plan_status'] = 'confirmed'; $GLOBALS['plans'] = array(10); $GLOBALS['method'] = 'get';
    $GLOBALS['meta'] = array(10 => array('_vms_event_date' => gmdate('Y-m-d', time() + 86400), '_vms_start_time' => '20:00'), 20 => array('_vms_stage_plot_attachment_id' => 1), 30 => array('_vms_linked_user_id' => 3));
    $GLOBALS['users'] = array(3 => (object) array('ID' => 3, 'user_email' => 'tech@example.test'));
    $GLOBALS['slots'] = array(array('slot_id' => 1, 'role_id' => 7, 'role_name' => 'Sound technician', 'role_meta' => array('role_id' => 7, 'name' => 'Sound technician', 'slug' => 'sound-technician'), 'status' => 'active', 'assignments' => array(array('assignment_id' => 1, 'staff_id' => 30, 'status' => 'confirmed'))));
    fixture_file(1, '%PDF original'); $_GET = $_POST = $_FILES = array();
}
function reasons() { return array_column($GLOBALS['wpdb']->rows, 'error_message'); }
function dispatch() { bvmgr_tech_doc_dispatch(10, 20, array('stage_plot' => 'new')); }
try {
    reset_fixture(); dispatch(); check(count($GLOBALS['mail']) === 1, 'confirmed technician receives'); check($GLOBALS['mail'][0]['to'] === 'tech@example.test', 'correct email');
    check(array_column($GLOBALS['wpdb']->rows, 'status') === array('queued', 'sent'), 'intent and terminal audit'); check(get_current_user_id() === 1, 'actor restored');
    $message = $GLOBALS['mail'][0]; check(str_contains($message['body'], 'Fixture 10') && str_contains($message['body'], '20:00') && str_contains($message['body'], 'Fixture 20'), 'operational context');
    check(str_contains($message['body'], 'admin-post.php?action=bvmgr_tech_documents'), 'authenticated landing');
    check(!str_contains(json_encode(array($message, $GLOBALS['wpdb']->rows)), $GLOBALS['tmp']), 'no private path in mail or log');
    check(!str_contains($message['body'], '_wpnonce') && !str_contains($message['body'], '/uploads/'), 'no uploader nonce or direct upload URL');
    dispatch(); check(count($GLOBALS['mail']) === 1 && in_array('duplicate_suppressed_sent', reasons()), 'repeated dispatch deduped');
    foreach (array('proposed', 'declined', 'canceled', 'cancelled') as $status) {
        reset_fixture(); $GLOBALS['slots'][0]['assignments'][0]['status'] = $status; dispatch();
        check(!$GLOBALS['mail'] && in_array('no_confirmed_technician', reasons()), $status . ' excluded visibly');
    }
    reset_fixture(); $GLOBALS['slots'][0]['status'] = 'canceled'; dispatch(); check(!$GLOBALS['mail'], 'canceled slot excluded');
    reset_fixture(); $GLOBALS['slots'] = array(); dispatch(); check(in_array('no_confirmed_technician', reasons()), 'no technician audited');
    ob_start(); bvmgr_tech_doc_status_render(10); $view = ob_get_clean(); check(str_contains($view, 'No confirmed technician'), 'no technician visible');
    reset_fixture(); $GLOBALS['users'][3]->user_email = ''; dispatch(); check(!$GLOBALS['mail'] && in_array('missing_email', reasons()), 'missing email');
    reset_fixture(); $GLOBALS['access'] = false; dispatch(); check(!$GLOBALS['mail'] && in_array('no_authorized_access', reasons()), 'access failure');
    reset_fixture(); $GLOBALS['users'] = array(); $GLOBALS['user_matches'] = array(4,5); dispatch(); check(in_array('ambiguous_staff_user', reasons()), 'ambiguous user no first row');
    reset_fixture(); $GLOBALS['meta'][31]['_vms_linked_user_id'] = 4; $GLOBALS['users'][4] = (object) array('ID'=>4,'user_email'=>'other@example.test');
    $GLOBALS['slots'][0]['assignments'][] = array('assignment_id'=>2,'staff_id'=>31,'status'=>'confirmed'); dispatch(); check(count($GLOBALS['mail']) === 2, 'multiple technicians all receive');
    check(bvmgr_tech_doc_recipients(10,20)[0]['email'] === 'other@example.test', 'deterministic recipient ordering');
    reset_fixture(); $GLOBALS['meta'][31]['_vms_linked_user_id'] = 4; $GLOBALS['users'][4] = (object) array('ID'=>4,'user_email'=>'TECH@example.test');
    $GLOBALS['slots'][0]['assignments'][] = array('assignment_id'=>2,'staff_id'=>31,'status'=>'confirmed'); dispatch(); check(count($GLOBALS['mail']) === 1, 'duplicate email case normalized');
    $row = json_decode($GLOBALS['wpdb']->rows[0]['payload_json'], true); check(count($row['recipient']['staff_ids']) === 2, 'duplicate identities audited');
    foreach (array('sound-technician','audio-technician','production-technician','foh-engineer','monitor-engineer','technical-lead') as $role) check(bvmgr_tech_doc_role_is_technical(array('slug'=>$role)), 'technical alias '.$role);
    check(!bvmgr_tech_doc_role_is_technical(array('slug'=>'bartender')), 'unrelated role excluded');
    $GLOBALS['role_settings'][8] = '1'; check(bvmgr_tech_doc_role_is_technical(array('role_id'=>8,'slug'=>'custom')), 'explicit role authority');
    $GLOBALS['role_settings'][7] = '0'; check(!bvmgr_tech_doc_role_is_technical(array('role_id'=>7,'slug'=>'sound')), 'explicit role opt out');
    reset_fixture(); bvmgr_tech_doc_dispatch(10,20,array('tax_w9_upload'=>'new')); check(!$GLOBALS['mail'], 'nontechnical no send');
    reset_fixture(); fixture_file(2,'%PDF rider'); $GLOBALS['meta'][20]['_vms_tech_rider_attachment_id']=2; bvmgr_tech_doc_dispatch(10,20,array('tech_rider'=>'new')); check(count($GLOBALS['mail'])===1 && str_contains($GLOBALS['mail'][0]['body'],'production rider'), 'rider triggers');
    reset_fixture(); fixture_file(2,'%PDF list'); $GLOBALS['meta'][20]['_vms_input_list_attachment_id']=2; bvmgr_tech_doc_dispatch(10,20,array('stage_plot'=>'new','input_list'=>'new')); check(count($GLOBALS['mail'])===1, 'bulk batched');
    reset_fixture(); dispatch(); fixture_file(2,'%PDF revised'); $GLOBALS['meta'][20]['_vms_stage_plot_attachment_id']=2; bvmgr_tech_doc_dispatch(10,20,array('stage_plot'=>'updated')); check(count($GLOBALS['mail'])===2 && str_contains($GLOBALS['mail'][1]['body'],'updated'), 'revision triggers');
    fixture_file(3,'%PDF revised'); $GLOBALS['meta'][20]['_vms_stage_plot_attachment_id']=3; dispatch(); check(count($GLOBALS['mail'])===2, 'identical bytes different id suppressed');
    reset_fixture(); $GLOBALS['transport']=false; dispatch(); check(in_array('wp_mail reported failure.',reasons()), 'transport failure audited'); check(get_post_meta(20,'_vms_stage_plot_attachment_id',true)===1,'document retained');
    bvmgr_tech_doc_dispatch(10,20,array('stage_plot'=>'current'),'manual_retry'); check(count($GLOBALS['mail'])===1 && in_array('duplicate_suppressed_cooldown',reasons()), 'retry cooldown');
    foreach($GLOBALS['wpdb']->rows as &$r) $r['created_at']=gmdate('Y-m-d H:i:s',time()-61); unset($r); $GLOBALS['transport']=true; bvmgr_tech_doc_dispatch(10,20,array('stage_plot'=>'current'),'manual_retry'); check(count($GLOBALS['mail'])===2,'retry after failure');
    $GLOBALS['slots'][0]['assignments'][0]['status']='declined'; bvmgr_tech_doc_dispatch(10,20,array('stage_plot'=>'current'),'manual_retry'); check(count($GLOBALS['mail'])===2,'retry never resurrects declined');
    reset_fixture(); $GLOBALS['throw']=true; dispatch(); check(in_array('Email provider outcome unknown; investigate before retry.',reasons()),'provider exception contained'); check(!str_contains(json_encode($GLOBALS['wpdb']->rows),'never-log-this'),'exception path redacted');
    reset_fixture(); $GLOBALS['wpdb']->insert_ok=false; dispatch(); check(!$GLOBALS['mail'] && count($GLOBALS['issues'])===1,'audit failure fails closed');
    reset_fixture(); $GLOBALS['wpdb']->lock=false; dispatch(); check(!$GLOBALS['mail'] && in_array('delivery_busy_retry_manually',reasons()),'concurrency lock');
    reset_fixture(); bvmgr_tech_doc_log(10,20,array(bvmgr_tech_doc_snapshot(20,'stage_plot')),bvmgr_tech_doc_recipients(10,20)[0],'queued','','uncertain'); dispatch(); check(!$GLOBALS['mail'] && in_array('duplicate_suppressed_uncertain',reasons()),'unfinished intent no automatic resend');
    reset_fixture(); $GLOBALS['base_url']='file:///bad'; dispatch(); check(!$GLOBALS['mail'] && in_array('invalid_access_route',reasons()),'invalid route');
    reset_fixture(); unset($GLOBALS['files'][1]); dispatch(); check(!$GLOBALS['mail'] && in_array('document_unavailable',reasons()),'deleted document');
    reset_fixture(); $GLOBALS['plans']=array(); bvmgr_tech_doc_uploaded(20,array('stage_plot'=>'new')); check(!$GLOBALS['mail'] && in_array('no_current_linked_event',reasons()),'no event audited');
    reset_fixture(); $GLOBALS['plan_status']='canceled'; dispatch(); check(!$GLOBALS['mail'],'canceled event excluded');
    reset_fixture(); $GLOBALS['method']='post'; $_POST=array('vms_techdocs_save'=>1,'bvmgr_techdocs_nonce'=>'valid'); fixture_file(2,'%PDF vendor revision'); $_FILES=array('vms_stage_plot'=>2);
    ob_start(); bvmgr_vendor_portal_render_tech_docs(20); ob_end_clean(); check(count($GLOBALS['mail'])===1 && get_post_meta(20,'_vms_stage_plot_attachment_id',true)===2,'real vendor upload renderer targets technician'); check($GLOBALS['review']===array('tech_docs'),'review flow retained');
    fixture_file(3,'%PDF vendor revision'); $_FILES=array('vms_stage_plot'=>3); ob_start(); bvmgr_vendor_portal_render_tech_docs(20); ob_end_clean(); check(count($GLOBALS['mail'])===1,'identical reupload no send');
    $_FILES=array(); ob_start(); bvmgr_vendor_portal_render_tech_docs(20); ob_end_clean(); check(count($GLOBALS['mail'])===1,'metadata only save');
    $GLOBALS['method']='get'; $before=serialize(array($GLOBALS['wpdb']->rows,$GLOBALS['meta'],$GLOBALS['mail'])); ob_start(); bvmgr_vendor_portal_render_tech_docs(20); bvmgr_tech_doc_status_render(10); ob_end_clean(); check($before===serialize(array($GLOBALS['wpdb']->rows,$GLOBALS['meta'],$GLOBALS['mail'])),'read only views zero writes or sends');
    reset_fixture(); $GLOBALS['method']='post'; $_POST=array('vms_techdocs_save'=>1,'bvmgr_techdocs_nonce'=>'wrong'); fixture_file(2,'%PDF bad nonce'); $_FILES=array('vms_stage_plot'=>2); ob_start(); bvmgr_vendor_portal_render_tech_docs(20); ob_end_clean(); check(!$GLOBALS['mail'] && get_post_meta(20,'_vms_stage_plot_attachment_id',true)===1,'invalid upload nonce');
    reset_fixture(); $GLOBALS['storage_ready']=false; dispatch(); check(!$GLOBALS['mail'] && in_array('private_storage_not_ready',reasons()),'unqualified storage blocks delivery');
    ob_start(); bvmgr_tech_doc_status_render(10); $blocked=ob_get_clean(); check(str_contains($blocked,'verified non-public document storage is not ready'),'storage failure visible');
    reset_fixture(); $GLOBALS['method']='post'; $_GET=array('vendor_id'=>20); $_POST=array('vms_techdocs_save'=>1,'bvmgr_techdocs_nonce'=>'valid'); fixture_file(2,'%PDF admin upload'); $_FILES=array('vms_stage_plot'=>2);
    ob_start(); bvmgr_tech_doc_admin_page(); ob_end_clean(); check(count($GLOBALS['mail'])===1 && get_post_meta(20,'_vms_stage_plot_attachment_id',true)===2,'admin upload shares private save and notification path');
    reset_fixture(); $_GET=array('plan_id'=>10); $GLOBALS['can_edit']=false; $denied=false; ob_start(); try { bvmgr_tech_doc_admin_page(); } catch(RuntimeException $e) { $denied=true; } ob_end_clean(); check($denied && !$GLOBALS['mail'],'admin object authorization');
    reset_fixture(); $_GET=array('plan_id'=>10); $_POST=array('bvmgr_tech_doc_retry'=>1,'bvmgr_tech_doc_nonce'=>'wrong'); $GLOBALS['method']='post'; $denied=false; ob_start(); try { bvmgr_tech_doc_admin_page(); } catch(RuntimeException $e) { $denied=true; } ob_end_clean(); check($denied && !$GLOBALS['mail'],'manual retry nonce rejected');
    reset_fixture(); $_GET=array('plan_id'=>10); $_POST=array('bvmgr_tech_doc_retry'=>1,'bvmgr_tech_doc_nonce'=>'valid'); $GLOBALS['method']='post'; ob_start(); bvmgr_tech_doc_admin_page(); ob_end_clean(); check(count($GLOBALS['mail'])===1,'manual retry current technician');
    ob_start(); bvmgr_tech_doc_admin_page(); ob_end_clean(); check(count($GLOBALS['mail'])===1,'double manual POST suppressed');
    echo 'PASS: ' . $GLOBALS['checks'] . " tech-document notification assertions\n";
} finally { foreach (glob($GLOBALS['tmp'].'/*') as $path) unlink($path); rmdir($GLOBALS['tmp']); }
