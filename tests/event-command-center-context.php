<?php
/** Optional read-only adapters: real communications summary and provider boundary fixtures. */
define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);
$GLOBALS['ecc_meta'] = array();
$GLOBALS['ecc_caps'] = true;
$GLOBALS['ecc_history'] = array();
$GLOBALS['ecc_packets'] = array();
$GLOBALS['ecc_private'] = true;
function __($text, $domain = '') { return $text; }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_-]/i', '', $value)); }
function get_post_type($id) { return $id === 42 ? 'vms_event_plan' : 'post'; }
function current_user_can($cap, ...$args) { return $GLOBALS['ecc_caps']; }
function get_post_meta($id, $key, $single = true) { return $GLOBALS['ecc_meta'][$id][$key] ?? ''; }
function add_query_arg($key, $value, $url) { return $url . '&' . $key . '=' . $value; }
function human_time_diff($from, $to) { return (string) ($to - $from) . ' seconds'; }
function bvmgr_event_day_report_url($id) { return 'https://example.test/admin-post.php?action=vms_event_day_report&event_plan_id=' . $id . '&_wpnonce=report-' . $id; }
function bvmgr_admission_manage_capability() { return 'manage_options'; }
function bvmgr_event_occurrence_history($id) { return $GLOBALS['ecc_history']; }
function bvmgr_event_communication_admin_url($id) { return 'https://example.test/post.php?post=' . $id . '#bvmgr-event-communications'; }
function bvmgr_admin_ui_registered_page_url($slug) { return 'https://example.test/admin.php?page=' . $slug; }
function wp_remote_get(...$args) { throw new RuntimeException('HTTP is forbidden'); }
function update_post_meta(...$args) { throw new RuntimeException('Writes are forbidden'); }
require dirname(__DIR__) . '/includes/core/event-communications.php';
require dirname(__DIR__) . '/includes/admin/event-command-center-context.php';
$checks = 0;
function ecc_assert($condition, $message) { global $checks; $checks++; if (!$condition) { throw new RuntimeException($message); } }
ecc_assert(!class_exists('WooCommerce') && !class_exists('VMSX_Weather_Risk_Advisory_Engine'), 'Absent-provider tests must run before companion classes exist.');
$absent = bvmgr_event_command_center_get_operational_context(42);
ecc_assert(!$absent['documents']['available'], 'Absent Agreements must be unavailable.');
ecc_assert(count($absent['tools']) === 2, 'Absent optional tools must not render.');
ecc_assert(bvmgr_event_command_center_event_day_url(9) === '', 'Wrong post type cannot launch report.');
ecc_assert(bvmgr_event_command_center_event_day_url(0) === '', 'Missing event cannot launch report.');
ecc_assert(strpos(bvmgr_event_command_center_event_day_url(42), 'event_plan_id=42&_wpnonce=report-42') !== false, 'Report must use canonical scoped helper.');
ecc_assert(bvmgr_event_command_center_get_operational_weather(42, array())['state'] === 'unavailable', 'Absent Weather must fail gracefully.');

// Explicit companion fixture contracts: no installed plugin or WordPress bootstrap.
if (true) {
class WooCommerce {}
class VMSX_Weather_Risk_Capabilities { public static function can_view_event($id) { return $GLOBALS['ecc_caps']; } }
class VMSX_Weather_Risk_Settings { public static function get() { return array('enabled' => $GLOBALS['ecc_weather_enabled'] ?? true); } }
class VMSX_Weather_Risk_Admin_Menu { public static function details_url($id) { return 'https://example.test/admin.php?page=vms-weather-risk&event_plan_id=' . $id; } }
class VMSX_Weather_Risk_Advisory_Engine {
    public static function get_snapshot($id) { if (!empty($GLOBALS['ecc_weather_throw'])) { throw new RuntimeException('Provider failure'); } return $GLOBALS['ecc_weather'] ?? null; }
    public static function refresh_snapshot(...$args) { throw new RuntimeException('Refresh is forbidden'); }
}
}
// Define after absence checks so conditional availability is exercised in one process.
if (true) {
function vmsa_can_manage() { return $GLOBALS["ecc_caps"]; }
function vmsa_get_event_plan_packet_ids($id) { return array_keys($GLOBALS["ecc_packets"]); }
function vmsa_build_operator_queue_row($id) { return $GLOBALS["ecc_packets"][$id]; }
function vmsa_admin_page_url($args) { return "https://example.test/admin.php?page=vms-agreements&packet_id=" . $args["packet_id"]; }
function vmsa_agreement_setup_url($id) { return "https://example.test/admin.php?page=vms-agreements&vmsa_setup_event_plan=" . $id; }
function bvmgr_staff_portal_get_event_tech_docs($id) { return array(array("vendor_id" => 6, "doc_key" => "stage_plot", "label" => "Stage plot", "vendor_name" => "Performer", "url" => "https://example.test/private?plan_id=" . $id)); }
function bvmgr_vendor_portal_user_can_download_tech_doc($vendor, $key, $plan) { return $GLOBALS["ecc_private"]; }
}

$operation = '00000000-0000-4000-8000-000000000001';
$missing = '00000000-0000-4000-8000-000000000002';
$GLOBALS['ecc_history'] = array(array('operation_id' => $operation), array('operation_id' => $operation), array('operation_id' => $missing, 'impact_counts' => array('customers' => 3)));
$GLOBALS['ecc_meta'][42][bvmgr_event_communication_meta_key($operation)] = array('event_plan_id' => 42, 'operation_id' => $operation,
    'audience' => array('a' => array('email_valid' => true), 'b' => array('email_valid' => true)),
    'recipient_states' => array('a' => array('written_notice' => array('status' => 'pending')), 'b' => array('written_notice' => array('status' => 'failed'), 'attempts' => array(array('started_at_utc' => '2026-09-06 12:00:00')))));
$context = bvmgr_event_command_center_get_operational_context(42);
ecc_assert($context['communications']['available'], 'Real communication adapter available.');
ecc_assert($context['communications']['pending'] === 1, 'Duplicate history must not double count notices.');
ecc_assert($context['communications']['failed'] === 1, 'Failed notices remain separate.');
ecc_assert($context['communications']['review_required'] === 2, 'Missing ledger and uncertain send attempt need review.');
ecc_assert(strpos($context['communications']['summary'], 'Occurrence-change') !== false, 'Comms summary must identify its limited scope.');
ecc_assert(count($context['tools']) === 3, 'Existing authorized private doc is linked.');
$GLOBALS['ecc_meta'][42]['_vms_express_bar_enabled'] = '1';
ecc_assert(count(bvmgr_event_command_center_get_operational_context(42)['tools']) === 4, 'Configured Express Bar is event scoped.');
$GLOBALS['ecc_private'] = false;
ecc_assert(count(bvmgr_event_command_center_get_operational_context(42)['tools']) === 3, 'Unauthorized private doc is omitted.');
$GLOBALS['ecc_packets'] = array(
    10 => array('event_id' => 42, 'status' => 'sent', 'bucket' => 'sent', 'title' => 'Performer agreement', 'terms_state' => 'current', 'status_label' => 'Sent', 'terms_label' => 'Current'),
    11 => array('event_id' => 42, 'status' => 'acknowledged', 'bucket' => 'acknowledged', 'terms_state' => 'current'),
    12 => array('event_id' => 42, 'status' => 'voided', 'bucket' => 'history'),
    13 => array('event_id' => 99, 'status' => 'sent', 'bucket' => 'sent'),
);
$documents = bvmgr_event_command_center_get_operational_context(42)['documents'];
ecc_assert(count($documents['issues']) === 1, 'Pending agreement only; history and other event excluded.');
ecc_assert(strpos($documents['issues'][0]['action_url'], 'packet_id=10') !== false, 'Agreement uses operator packet route.');
$GLOBALS['ecc_packets'][11]['terms_state'] = 'stale';
ecc_assert(count(bvmgr_event_command_center_get_operational_context(42)['documents']['issues']) === 2, 'Acknowledged but stale agreement needs review.');
if (true) {
    function bvmgr_admission_render_event_plan_metabox($post) {}
    function bvmgr_event_command_center_edit_fragment_url($id, $fragment) { return 'https://example.test/post.php?post=' . $id . '#' . $fragment; }
}
$routes = bvmgr_event_command_center_get_operational_context(42);
ecc_assert($routes['event_day_url'] === bvmgr_event_command_center_event_day_url(42), 'Header report URL matches canonical tool.');
$admissions = array_values(array_filter($routes['tools'], static fn($row) => $row['label'] === 'Admissions / check-in'));
ecc_assert(count($admissions) === 1 && strpos($admissions[0]['url'], 'post=42#vms_guest_list_comp_admission') !== false, 'Admissions keeps the Event Plan scope.');
$profitability = array_values(array_filter($routes['tools'], static fn($row) => $row['label'] === 'Profitability report (all events)'));
ecc_assert(count($profitability) === 1 && strpos($profitability[0]['url'], 'event_plan_id') === false, 'Profitability does not invent an event ID filter.');
ecc_assert(count($routes['marketing_tools']) === 2, 'Registered marketing workspaces exposed independently.');

$GLOBALS['ecc_weather'] = array('event_id' => 42, 'computed_at_utc' => time() - 60, 'weather_risk' => array('band' => 'Watch', 'reasons' => array('Wind warning')),
    'provider_health' => array('responded' => 1), 'window' => array('ok' => true, 'label' => 'Doors to close', 'event_start_utc' => time() + 7200, 'window_end_utc' => time() + 14400));
$weather = bvmgr_event_command_center_get_operational_weather(42, array());
ecc_assert($weather['state'] === 'current' && $weather['concern'], 'Current Weather warning recognized.');
ecc_assert($weather['summary'] === 'Wind warning' && $weather['window_label'] === 'Doors to close', 'Use actual weather reason/window.');
ecc_assert(strpos($weather['url'], 'event_plan_id=42') !== false, 'Weather details retain event context.');
$GLOBALS['ecc_weather']['weather_risk']['reasons'] = array();
ecc_assert(strpos(bvmgr_event_command_center_get_operational_weather(42, array())['summary'], 'Elevated weather risk') !== false, 'Elevated risk without a reason must not claim no concern.');
$GLOBALS['ecc_weather']['weather_risk']['band'] = 'Low';
ecc_assert(!bvmgr_event_command_center_get_operational_weather(42, array())['concern'], 'Low weather does not invent warning.');
$GLOBALS['ecc_weather']['computed_at_utc'] = time() - 4000;
ecc_assert(bvmgr_event_command_center_get_operational_weather(42, array())['state'] === 'stale', 'Scheduler stale cadence honored.');
$GLOBALS['ecc_weather']['event_id'] = 99;
ecc_assert(bvmgr_event_command_center_get_operational_weather(42, array())['state'] === 'unavailable', 'Cross-event snapshot rejected.');
$GLOBALS['ecc_weather']['event_id'] = 42;
$GLOBALS['ecc_weather']['provider_health']['responded'] = 0;
ecc_assert(bvmgr_event_command_center_get_operational_weather(42, array())['state'] === 'unavailable', 'No usable providers cannot certify weather.');
$GLOBALS['ecc_weather_enabled'] = false;
ecc_assert(!bvmgr_event_command_center_get_operational_weather(42, array())['active'], 'Disabled optional module stays disabled.');
$GLOBALS['ecc_weather_enabled'] = true;
$GLOBALS['ecc_weather_throw'] = true;
ecc_assert(bvmgr_event_command_center_get_operational_weather(42, array())['state'] === 'unavailable', 'Provider exception fails gracefully.');
$GLOBALS['ecc_caps'] = false;
ecc_assert(bvmgr_event_command_center_event_day_url(42) === '', 'Report capability enforced.');
$denied = bvmgr_event_command_center_get_operational_context(42);
ecc_assert(!$denied['communications']['available'] && !$denied['documents']['available'] && !$denied['tools'], 'Unauthorized summaries and tools omitted.');
ecc_assert(bvmgr_event_command_center_get_operational_weather(42, array('url' => 'settings'))['url'] === '', 'Unauthorized weather link omitted.');
echo "ECC optional context passed {$checks} assertions.\n";
