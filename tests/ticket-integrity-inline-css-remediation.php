<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('BVMGR_PLUGIN_URL', 'https://example.test/wp-content/plugins/backstage-venue-manager/');
define('BVMGR_VERSION', 'test-version');

$GLOBALS['vms_test_manage_options'] = false;
$GLOBALS['vms_test_settings'] = array();
$GLOBALS['vms_test_store'] = array('summary' => array('red' => 0, 'yellow' => 0));
$GLOBALS['vms_test_styles'] = array();
$GLOBALS['vms_test_scripts'] = array();
$GLOBALS['vms_test_inline_scripts'] = array();
$GLOBALS['menu'] = array();
$GLOBALS['submenu'] = array();

function __(string $text, string $domain = ''): string { unset($domain); return $text; }
function esc_html__(string $text, string $domain = ''): string { unset($domain); return $text; }
function absint($value): int { return abs((int) $value); }
function sanitize_key($value): string { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)) ?: ''; }
function wp_unslash($value) { return $value; }
function current_user_can(string $cap): bool { unset($cap); return !empty($GLOBALS['vms_test_manage_options']); }
function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void { unset($hook, $callback, $priority, $accepted_args); }
function add_submenu_page(...$args): void { unset($args); }
function wp_enqueue_style(string $handle, string $src = '', array $deps = array(), $ver = false, string $media = 'all'): void { unset($deps, $ver, $media); $GLOBALS['vms_test_styles'][$handle] = $src; }
function wp_enqueue_script(string $handle, string $src = '', array $deps = array(), $ver = false, bool $in_footer = false): void { unset($deps, $ver, $in_footer); $GLOBALS['vms_test_scripts'][$handle] = $src; }
function wp_add_inline_script(string $handle, string $script, string $position = 'after'): void { $GLOBALS['vms_test_inline_scripts'][$handle] = array('script' => $script, 'position' => $position); }
function wp_json_encode($value): string { return json_encode($value) ?: 'null'; }
function bvmgr_ticket_integrity_get_settings(): array { return $GLOBALS['vms_test_settings']; }
function bvmgr_ticket_integrity_get_results_store(): array { return $GLOBALS['vms_test_store']; }

require_once dirname(__DIR__) . '/includes/admin/ticket-integrity-page.php';

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};
$reset_assets = static function (): void {
	$GLOBALS['vms_test_styles'] = array();
	$GLOBALS['vms_test_scripts'] = array();
	$GLOBALS['vms_test_inline_scripts'] = array();
};
$seed_menu = static function (): void {
	$GLOBALS['menu'] = array(array(0 => 'VMS', 2 => 'vms-dashboard'));
	$GLOBALS['submenu'] = array('vms-dashboard' => array(array(0 => 'Ticket Integrity', 2 => 'vms-ticket-integrity')));
};

$pageSource = file_get_contents(dirname(__DIR__) . '/includes/admin/ticket-integrity-page.php');
$cssSource = file_get_contents(dirname(__DIR__) . '/assets/css/admin-ticket-integrity.css');
$assert(is_string($pageSource) && strpos($pageSource, 'vms-ticket-integrity-alert-dot-css') === false, 'Ticket Integrity admin page should no longer print the inline badge style block.');
$assert(is_string($pageSource) && strpos($pageSource, "add_action('admin_head', 'vms_ticket_integrity_render_menu_alert_badge_css'") === false, 'Ticket Integrity admin page should no longer hook admin_head for badge CSS.');
$assert(is_string($cssSource) && strpos($cssSource, '#adminmenu .vms-ticket-integrity-alert-badge') !== false, 'Ticket Integrity badge selector should live in the external admin stylesheet.');
$assert(is_string($cssSource) && strpos($cssSource, '#adminmenu .vms-ticket-integrity-alert-badge .plugin-count') !== false, 'Ticket Integrity badge count selector should live in the external admin stylesheet.');

$GLOBALS['vms_test_manage_options'] = true;
$GLOBALS['vms_test_store'] = array('summary' => array('red' => 1, 'yellow' => 0));
$GLOBALS['vms_test_settings'] = array();
$_GET = array();
$reset_assets();
bvmgr_ticket_integrity_admin_enqueue_assets('dashboard_page_tools');
$assert(isset($GLOBALS['vms_test_styles']['bvmgr-admin-ticket-integrity']), 'Ticket Integrity badge styling should enqueue its canonical external stylesheet when the menu badge is needed.');
$assert(!isset($GLOBALS['vms_test_scripts']['bvmgr-admin-ticket-integrity']), 'Ticket Integrity admin script should stay off non-Ticket-Integrity screens.');

$_GET = array('page' => 'vms-ticket-integrity');
$GLOBALS['vms_test_store'] = array('summary' => array('red' => 0, 'yellow' => 0));
$reset_assets();
bvmgr_ticket_integrity_admin_enqueue_assets('toplevel_page_vms-dashboard');
$assert(isset($GLOBALS['vms_test_styles']['bvmgr-admin-ticket-integrity']), 'Ticket Integrity page should enqueue its canonical stylesheet.');
$assert(isset($GLOBALS['vms_test_scripts']['bvmgr-admin-ticket-integrity']), 'Ticket Integrity page should enqueue its canonical admin script.');
$assert(strpos((string) ($GLOBALS['vms_test_inline_scripts']['bvmgr-admin-ticket-integrity']['script'] ?? ''), 'window.BVMGR_TICKET_INTEGRITY_ADMIN = ') !== false, 'Ticket Integrity page should emit its canonical inline JS configuration.');

$seed_menu();
$GLOBALS['vms_test_store'] = array('summary' => array('red' => 1, 'yellow' => 0));
bvmgr_ticket_integrity_add_menu_alert_badge();
$assert(strpos((string) ($GLOBALS['menu'][0][0] ?? ''), 'vms-ticket-integrity-alert-badge') !== false, 'Ticket Integrity menu badge should still appear when alert conditions are met.');
$assert(strpos((string) ($GLOBALS['submenu']['vms-dashboard'][0][0] ?? ''), 'vms-ticket-integrity-alert-badge') !== false, 'Ticket Integrity submenu badge should still appear when alert conditions are met.');

$seed_menu();
$GLOBALS['vms_test_store'] = array('summary' => array('red' => 0, 'yellow' => 0));
bvmgr_ticket_integrity_add_menu_alert_badge();
$assert(strpos((string) ($GLOBALS['menu'][0][0] ?? ''), 'vms-ticket-integrity-alert-badge') === false, 'Ticket Integrity menu badge should stay absent when alert conditions are not met.');
$assert(strpos((string) ($GLOBALS['submenu']['vms-dashboard'][0][0] ?? ''), 'vms-ticket-integrity-alert-badge') === false, 'Ticket Integrity submenu badge should stay absent when alert conditions are not met.');

fwrite(STDOUT, "Ticket Integrity inline CSS remediation OK.\n");
