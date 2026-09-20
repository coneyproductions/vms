<?php
/**
 * Run via wp eval-file in a guarded disposable WordPress database.
 * BVM_P1C2_SCENARIO: data-tools, agreements, or both; companion source roots
 * come from BVM_P1C2_DATA_TOOLS and BVM_P1C2_AGREEMENTS. No plugin activation.
 */
if (!defined('ABSPATH') || getenv('BVM_P1C2_DISPOSABLE') !== '1') {
    throw new RuntimeException('A guarded disposable WordPress fixture is required.');
}
$scenario = getenv('BVM_P1C2_SCENARIO');
if (!in_array($scenario, array('data-tools', 'agreements', 'both'), true)) {
    throw new RuntimeException('Choose an explicit companion scenario.');
}
$root = getenv('BVM_P1C2_SOURCE') ?: dirname(__DIR__);
$checks = 0;
$assert = static function ($condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) { throw new RuntimeException($message); }
};
// Only unrelated application services are fixtures; real BVM producers,
// internal listeners, companion callbacks and WordPress dispatch are loaded.
function bvmgr_request_read_key(array $source, string $key): string {
    return isset($source[$key]) && is_scalar($source[$key]) ? sanitize_key(wp_unslash($source[$key])) : '';
}
function bvmgr_request_read_absint(array $source, string $key): int {
    return isset($source[$key]) && is_scalar($source[$key]) ? absint($source[$key]) : 0;
}
function bvmgr_get_active_vendor_ids_for_user($user): array { return array($GLOBALS['p1c2_vendor']); }
function bvmgr_get_primary_vendor_id_for_user($user): int { return $GLOBALS['p1c2_vendor']; }
function bvmgr_admission_vendor_guest_portal_events(int $vendor): array { return array(); }
function vms_dt_has_core_function($name): bool { return false; }
function vms_dt_vio_get_vendor_type_slug($vendor): string { return ''; }
function vms_dt_vio_normalize_opportunity_status($value): string { return (string) $value; }
function vms_dt_vio_create_interest_submission($vendor, $event, $args): array {
    $GLOBALS['p1c2_interest'][] = array($vendor, $event, $args);
    return array('status' => 'pending');
}
$dt = $scenario !== 'agreements';
$ag = $scenario !== 'data-tools';
define('BVMGR_PLUGIN_URL', 'https://example.invalid/bvm/');
define('BVMGR_VERSION', '1.2.0');
register_post_type('vms_vendor', array('public' => false));
register_post_type('vmsa_packet', array('public' => false));
$vendor = wp_insert_post(array('post_type' => 'vms_vendor', 'post_title' => 'Hook fixture vendor', 'post_status' => 'publish'));
$GLOBALS['p1c2_vendor'] = $vendor;
$user = wp_insert_user(array('user_login' => 'portal_fixture', 'user_pass' => 'disposable', 'role' => 'subscriber'));
$assert(!is_wp_error($user) && $vendor > 0, 'Native fixture creation');
wp_set_current_user($user);
$page = wp_insert_post(array('post_type' => 'page', 'post_title' => 'Portal fixture', 'post_status' => 'publish'));
$GLOBALS['post'] = get_post($page);
$_GET = $_POST = $_REQUEST = array();
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once $root . '/includes/core/prefix-b4-compat.php';
require_once $root . '/includes/portal/vendor-portal.php';
require_once $root . '/includes/modules/admissions/vendor-guest-portal.php';
if ($dt) {
    $dt_root = getenv('BVM_P1C2_DATA_TOOLS');
    $assert(is_file($dt_root . '/includes/vendor-invites/portal.php'), 'Explicit Data Tools source');
    require $dt_root . '/includes/vendor-invites/portal.php';
}
if ($ag) {
    $ag_root = getenv('BVM_P1C2_AGREEMENTS');
    $assert(is_file($ag_root . '/includes/vendor-portal.php'), 'Explicit Agreements source');
    require $ag_root . '/includes/helpers.php';
    require $ag_root . '/includes/vendor-portal.php';
    // A private empty packet supplies the navigation badge; content assertions
    // below also exercise the legitimate no-packet state without token creation.
    $packet = wp_insert_post(array('post_type' => 'vmsa_packet', 'post_status' => 'private', 'post_title' => 'Fixture packet'));
    update_post_meta($packet, '_vmsa_vendor_id', $vendor);
    update_post_meta($packet, '_vmsa_status', 'generated');
}
$legacy_calls = 0;
$events = array();
foreach (array('allowed_tabs', 'nav_links', 'render_custom_tab') as $suffix) {
    add_filter('vms_vendor_portal_' . $suffix, static function ($value = null) use (&$legacy_calls, $suffix) {
        if (current_filter() === 'vms_vendor_portal_' . $suffix) {
            ++$legacy_calls;
        }
        return $value;
    }, 1, 3);
    add_filter('bvmgr_vendor_portal_' . $suffix, static function (...$args) use (&$events, $suffix) {
        $events[$suffix][] = $args;
        return $args[0] ?? null;
    }, 1, 3);
}
foreach (array('allowed_tabs' => array('bvmgr_admission_vendor_guest_register_portal_tab', 1), 'nav_links' => array('bvmgr_admission_vendor_guest_add_nav_link', 2), 'render_custom_tab' => array('bvmgr_admission_vendor_guest_render_custom_tab', 3)) as $suffix => [$callback, $accepted]) {
    $hook = 'bvmgr_vendor_portal_' . $suffix;
    $assert(has_filter($hook, $callback) === 20, 'Internal listener canonical priority ' . $suffix);
    $assert($GLOBALS['wp_filter'][$hook]->callbacks[20][$callback]['accepted_args'] === $accepted, 'Internal accepted arguments ' . $suffix);
    $assert(has_filter('vms_vendor_portal_' . $suffix, $callback) === false, 'Internal listener no longer legacy-only ' . $suffix);
}
$tabs = bvmgr_vendor_portal_allowed_tabs();
$assert(in_array('guest-list', $tabs, true), 'Internal Guest List allowlist works');
$assert(in_array('agreements', $tabs, true) === $ag, 'Agreements allowlist follows exact loaded companion');
if ($ag) {
    $assert(has_filter('vms_vendor_portal_allowed_tabs', 'vmsa_register_vendor_portal_tab') === 20, 'Retained Agreements source remains on its legacy hook');
    $assert(has_filter('bvmgr_vendor_portal_allowed_tabs', 'vmsa_register_vendor_portal_tab') === 20, 'Agreements callback is bridged to canonical dispatch');
}
foreach (range(0, 2) as $repeat) {
    $before = count($events['allowed_tabs']);
    $assert(bvmgr_vendor_portal_allowed_tabs() === $tabs, 'Repeated allowlist preserves filter result');
    $assert(count($events['allowed_tabs']) === $before + 1, 'One allowlist dispatch per repeated call');
    $assert(end($events['allowed_tabs']) === array(array('dashboard', 'profile', 'tax-profile', 'history', 'availability', 'opportunities', 'all-vendors', 'tech')), 'Allowlist exact single argument and initial value');
}
$invalid_tabs = static function () { return false; };
add_filter('bvmgr_vendor_portal_allowed_tabs', $invalid_tabs, 999);
$assert(bvmgr_vendor_portal_allowed_tabs() === array('dashboard'), 'Non-array filter result retains dashboard fallback');
remove_filter('bvmgr_vendor_portal_allowed_tabs', $invalid_tabs, 999);
// Full, unchanged BVM shortcode flow: argument construction, navigation timing,
// requested-tab validation and custom dispatch with real internal consumers.
foreach (range(0, 2) as $repeat) {
    $_GET = array('tab' => 'guest-list');
    $before = array_map('count', $events);
    $html = bvmgr_vendor_portal_shortcode();
    $assert(substr_count($html, '>Guest List</a>') === 1, 'One internal navigation entry per repeat');
    $assert(substr_count($html, 'class="vms-vendor-guest-root"') === 1, 'One internal guest renderer per repeat');
    $assert(substr_count($html, '>Opportunities</a>') === ($dt ? 1 : 0), 'Data Tools navigation executes once');
    $assert(substr_count($html, 'vmsa-tab-badge') === ($ag ? 1 : 0), 'Agreements navigation executes once');
    $assert(count($events['nav_links']) === ($before['nav_links'] ?? 0) + 1, 'One canonical nav dispatch per shortcode');
    $assert(count($events['render_custom_tab']) === ($before['render_custom_tab'] ?? 0) + 1, 'One canonical custom dispatch per shortcode');
    $nav = end($events['nav_links']);
    $custom = end($events['render_custom_tab']);
    $assert(count($nav) === 2 && $nav[0] === 'guest-list', 'Nav argument order/count');
    $assert(count($custom) === 3 && $custom[0] === false && $custom[1] === 'guest-list' && $custom[2] === $nav[1], 'Custom initial value/order/context');
    $assert($nav[1]['vendor_id'] === $vendor && $nav[1]['user_id'] === $user && $nav[1]['vendor_post']->ID === $vendor, 'Native BVM context is preserved');
    $assert(strpos($html, '>Guest List</a>') < strpos($html, 'vms-portal-body'), 'Navigation still precedes portal body');
}
if ($dt) {
    $assert(has_filter('vms_vendor_portal_nav_links', 'vms_dt_vio_vendor_portal_nav_link') === 20, 'Retained Data Tools nav source remains on its legacy hook');
    $assert(has_filter('bvmgr_vendor_portal_nav_links', 'vms_dt_vio_vendor_portal_nav_link') === 20, 'Data Tools nav callback is bridged to canonical dispatch');
    $assert(has_filter('vms_vendor_portal_render_custom_tab', 'vms_dt_vio_vendor_portal_render_custom_tab') === 20, 'Retained Data Tools renderer remains on its legacy hook');
    $assert(has_filter('bvmgr_vendor_portal_render_custom_tab', 'vms_dt_vio_vendor_portal_render_custom_tab') === 20, 'Data Tools renderer is bridged to canonical dispatch');
}
if ($ag) {
    $assert(has_filter('bvmgr_vendor_portal_nav_links', 'vmsa_render_vendor_portal_nav_link') === 30, 'Agreements nav callback is bridged to canonical dispatch');
    $assert(has_filter('bvmgr_vendor_portal_render_custom_tab', 'vmsa_render_vendor_portal_custom_tab') === 30, 'Agreements renderer is bridged to canonical dispatch');
}
if ($ag) {
    // Remove fixture packet in this disposable DB only; exercise real Agreements
    // no-packet content without invoking unrelated review-token creation.
    wp_delete_post($packet, true);
    foreach (range(0, 2) as $repeat) {
        $_GET = array('tab' => 'agreements');
        $html = bvmgr_vendor_portal_shortcode();
        $assert(substr_count($html, '<h2>Agreements</h2>') === 1, 'One actual Agreements custom renderer');
        $assert(str_contains($html, 'No agreements yet'), 'Agreements empty state preserved');
        $assert(!str_contains($html, 'That portal section is not available'), 'Canonical Agreements handled return reaches BVM');
    }
}
// Contract seam for all filter values. BVM currently routes opportunities to its
// own core branch, so invoke the exact source dispatch expression in isolation
// to test the Data Tools extension callback without changing portal routing.
$portal_source = file_get_contents($root . '/includes/portal/vendor-portal.php');
$assert(preg_match('/\$custom_rendered = \(bool\) apply_filters\([^;]+;/', $portal_source, $match) === 1, 'Extract actual BVM custom producer');
$dispatch = $match[0];
if ($dt) {
    $GLOBALS['p1c2_interest'] = array();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = array('vms_dt_vio_portal_action' => 'submit_interest', 'event_plan_id' => '71');
    $_REQUEST = array('vms_dt_vio_interest_nonce' => wp_create_nonce('vms_dt_vio_submit_interest'));
    foreach (range(0, 2) as $repeat) {
        $tab = 'opportunities';
        $portal_context = array('vendor_id' => $vendor, 'tab' => $tab, 'base_url' => get_permalink());
        ob_start();
        eval($dispatch);
        $html = ob_get_clean();
        $assert($custom_rendered === true && substr_count($html, '<h3>Opportunities</h3>') === 1, 'Data Tools exact BVM filter contract');
        $assert(count($GLOBALS['p1c2_interest']) === $repeat + 1, 'One interest side effect per repeated canonical filter');
        $assert(end($GLOBALS['p1c2_interest']) === array($vendor, 71, array('actor_user_id' => $user)), 'Interest callback argument values');
    }
}
$tab = 'unhandled-fixture-tab';
$portal_context = array('vendor_id' => $vendor);
ob_start(); eval($dispatch); $unhandled_html = ob_get_clean();
$assert($custom_rendered === false && $unhandled_html === '', 'Unhandled filter semantics retained');
$assert($legacy_calls === 0, 'No legacy hook is directly dispatched');
foreach (array('allowed_tabs', 'nav_links', 'render_custom_tab') as $suffix) {
    $assert(!empty($events[$suffix]), 'Canonical runtime dispatch observed ' . $suffix);
}
echo "PASS P1-C2 $scenario: $checks assertions; real BVM shortcode, native hooks, internal listeners and exact companion source.\n";
