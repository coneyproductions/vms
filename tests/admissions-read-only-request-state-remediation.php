<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('BVMGR_PLUGIN_URL', 'https://example.test/wp-content/plugins/backstage-venue-manager/');
define('BVMGR_VERSION', 'test-version');

function vms_test_assert(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
}

function vms_test_assert_same($expected, $actual, string $message): void
{
	vms_test_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function vms_test_assert_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function vms_test_assert_not_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert(strpos($haystack, $needle) === false, $message . "\nUnexpected: " . $needle);
}

function vms_test_extract_function(string $source, string $name): string
{
	$needle = 'function ' . $name . '(';
	$start = strpos($source, $needle);
	if ($start === false) {
		throw new RuntimeException('Unable to locate function ' . $name . '.');
	}

	$brace = strpos($source, '{', $start);
	if ($brace === false) {
		throw new RuntimeException('Unable to locate opening brace for ' . $name . '.');
	}

	$depth = 1;
	$length = strlen($source);
	for ($i = $brace + 1; $i < $length; $i++) {
		if ($source[$i] === '{') {
			$depth++;
			continue;
		}
		if ($source[$i] === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $start, ($i - $start) + 1);
			}
		}
	}

	throw new RuntimeException('Unable to locate closing brace for ' . $name . '.');
}

function __(string $text, string $domain = ''): string
{
	return $text;
}

function esc_html__(string $text, string $domain = ''): string
{
	return $text;
}

function esc_html(string $text): string
{
	return htmlspecialchars($text, ENT_QUOTES);
}

function esc_attr(string $text): string
{
	return htmlspecialchars($text, ENT_QUOTES);
}

function esc_url(string $text): string
{
	return $text;
}

function esc_url_raw(string $text): string
{
	return $text;
}

function sanitize_key($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$sanitized = preg_replace('/[^a-z0-9_-]+/i', '', strtolower((string) $value));
	return is_string($sanitized) ? $sanitized : '';
}

function sanitize_text_field($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$sanitized = preg_replace('/[\x00-\x1F\x7F]+/', '', strip_tags((string) $value));
	return is_string($sanitized) ? trim($sanitized) : '';
}

function wp_unslash($value)
{
	if (is_array($value)) {
		return array_map('wp_unslash', $value);
	}

	return is_string($value) ? stripslashes($value) : $value;
}

function absint($value): int
{
	return abs((int) $value);
}

function is_admin(): bool
{
	return !empty($GLOBALS['vms_test_is_admin']);
}

function is_user_logged_in(): bool
{
	return !empty($GLOBALS['vms_test_logged_in']);
}

function is_page(string $slug): bool
{
	return $slug === (string) ($GLOBALS['vms_test_page_slug'] ?? '');
}

function current_user_can(string $capability): bool
{
	return !empty($GLOBALS['vms_test_caps'][(string) $capability]);
}

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void
{
	unset($hook, $callback, $priority, $accepted_args);
}

function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void
{
	$GLOBALS['vms_test_filters'][$hook][$priority][] = array(
		'callback' => $callback,
		'accepted_args' => $accepted_args,
	);
	ksort($GLOBALS['vms_test_filters'][$hook]);
}

function apply_filters(string $hook, $value, ...$args)
{
	foreach ((array) ($GLOBALS['vms_test_filters'][$hook] ?? array()) as $callbacks) {
		foreach ((array) $callbacks as $entry) {
			$accepted_args = max(1, (int) ($entry['accepted_args'] ?? 1));
			$callback_args = array_slice(array_merge(array($value), $args), 0, $accepted_args);
			$value = call_user_func_array($entry['callback'], $callback_args);
		}
	}
	return $value;
}

function get_permalink(): string
{
	return 'https://example.test/vendor-portal/';
}

function add_query_arg(array $args, string $url): string
{
	return rtrim($url, '?') . '?' . http_build_query($args);
}

function admin_url(string $path = ''): string
{
	return 'https://example.test/wp-admin/' . ltrim($path, '/');
}

function wp_nonce_url(string $url, string $action): string
{
	return $url . '#nonce=' . $action;
}

function rest_url(string $path = ''): string
{
	return 'https://example.test/wp-json/' . ltrim($path, '/');
}

function wp_create_nonce(string $action): string
{
	return 'nonce:' . $action;
}

function wp_enqueue_style(string $handle, string $src = '', array $deps = array(), $ver = false): void
{
	$GLOBALS['vms_test_styles'][$handle] = array('src' => $src, 'deps' => $deps, 'ver' => $ver);
}

function wp_enqueue_script(string $handle, string $src = '', array $deps = array(), $ver = false, bool $in_footer = false): void
{
	$GLOBALS['vms_test_scripts'][$handle] = array('src' => $src, 'deps' => $deps, 'ver' => $ver, 'in_footer' => $in_footer);
}

function wp_localize_script(string $handle, string $name, array $data): void
{
	$GLOBALS['vms_test_localized'][$handle] = array('name' => $name, 'data' => $data);
}

function bvmgr_admission_settings(): array
{
	return array('allow_uncheckin' => true, 'max_party_size' => 6);
}

function bvmgr_admission_manage_capability(): string
{
	return 'manage_vms_admissions';
}

function bvmgr_admission_door_capability(): string
{
	return 'checkin_vms_admissions';
}

function bvmgr_admission_admin_should_load(): bool
{
	return !empty($GLOBALS['vms_test_admission_admin_should_load']);
}

function bvmgr_pass_claims_capability(): string
{
	return 'manage_vms_pass_claims';
}

function bvmgr_pass_claims_menu_slug(): string
{
	return 'vms-passes';
}

function bvmgr_pass_claims_pop_user_message(): array
{
	return array();
}

function bvmgr_pass_claims_get_tokens(int $batch_id, int $limit): array
{
	$GLOBALS['vms_test_pass_claim_token_args'] = array($batch_id, $limit);
	return array();
}

function bvmgr_pass_claims_export_url(int $batch_id): string
{
	return 'https://example.test/export?batch_id=' . $batch_id;
}

function bvmgr_pass_claims_render_tab_nav(string $tab): void
{
	$GLOBALS['vms_test_pass_claim_render_log'][] = 'tab:' . $tab;
}

function bvmgr_pass_claims_render_sources_tab(): void
{
	$GLOBALS['vms_test_pass_claim_render_log'][] = 'sources';
}

function bvmgr_pass_claims_render_batches_tab(): void
{
	$GLOBALS['vms_test_pass_claim_render_log'][] = 'batches';
}

function bvmgr_pass_claims_render_reports_tab(): void
{
	$GLOBALS['vms_test_pass_claim_render_log'][] = 'reports';
}

function get_current_user_id(): int
{
	return 77;
}

function bvmgr_admission_vendor_guest_pull_flash(int $user_id): array
{
	unset($user_id);
	return array();
}

function bvmgr_admission_vendor_guest_portal_events(int $vendor_id): array
{
	unset($vendor_id);
	return (array) ($GLOBALS['vms_test_guest_events'] ?? array());
}

function bvmgr_portal_notice(string $type, string $message): string
{
	return '<div class="' . esc_attr($type) . '">' . esc_html($message) . '</div>';
}

function wp_kses_post(string $html): string
{
	return $html;
}

class WP_Post
{
	public $ID;

	public function __construct(int $id)
	{
		$this->ID = $id;
	}
}

require_once dirname(__DIR__) . '/includes/runtime-guards.php';

$pluginRoot = dirname(__DIR__);
$adminUiPath = $pluginRoot . '/includes/modules/admissions/admin-ui.php';
$passClaimsPath = $pluginRoot . '/includes/modules/admissions/pass-claims.php';
$vendorGuestPath = $pluginRoot . '/includes/modules/admissions/vendor-guest-portal.php';
$vendorPortalPath = $pluginRoot . '/includes/portal/vendor-portal.php';

$adminUiSource = (string) file_get_contents($adminUiPath);
$passClaimsSource = (string) file_get_contents($passClaimsPath);
$vendorGuestSource = (string) file_get_contents($vendorGuestPath);
$vendorPortalSource = (string) file_get_contents($vendorPortalPath);

vms_test_assert($adminUiSource !== '', 'Admissions admin UI source should be readable.');
vms_test_assert($passClaimsSource !== '', 'Pass Claims source should be readable.');
vms_test_assert($vendorGuestSource !== '', 'Vendor Guest Portal source should be readable.');
vms_test_assert($vendorPortalSource !== '', 'Vendor Portal source should be readable.');

eval(vms_test_extract_function($adminUiSource, 'bvmgr_admission_admin_enqueue_assets'));
eval(vms_test_extract_function($passClaimsSource, 'bvmgr_pass_claims_is_admin_page'));
eval(vms_test_extract_function($passClaimsSource, 'bvmgr_pass_claims_render_admin_notices'));
eval(vms_test_extract_function($passClaimsSource, 'bvmgr_pass_claims_render_passes_tab'));
eval(vms_test_extract_function($passClaimsSource, 'bvmgr_pass_claims_render_admin_page'));
eval(vms_test_extract_function($vendorPortalSource, 'bvmgr_vendor_portal_query_key'));
eval(vms_test_extract_function($vendorPortalSource, 'bvmgr_vendor_portal_query_absint'));
eval(vms_test_extract_function($vendorPortalSource, 'bvmgr_vendor_portal_allowed_tabs'));
eval(vms_test_extract_function($vendorPortalSource, 'bvmgr_vendor_portal_get_requested_tab'));
eval(vms_test_extract_function($vendorPortalSource, 'bvmgr_vendor_portal_get_requested_vendor_id'));
eval(vms_test_extract_function($vendorGuestSource, 'bvmgr_admission_vendor_guest_register_portal_tab'));
eval(vms_test_extract_function($vendorGuestSource, 'bvmgr_admission_vendor_guest_portal_url'));
eval(vms_test_extract_function($vendorGuestSource, 'bvmgr_admission_vendor_guest_add_nav_link'));
eval(vms_test_extract_function($vendorGuestSource, 'bvmgr_admission_vendor_guest_portal_screen_key'));
eval(vms_test_extract_function($vendorGuestSource, 'bvmgr_admission_vendor_guest_render_custom_tab'));

add_filter('vms_vendor_portal_allowed_tabs', 'bvmgr_admission_vendor_guest_register_portal_tab', 20);
add_filter('vms_vendor_portal_allowed_tabs', static function ($tabs) {
	if (!is_array($tabs)) {
		return $tabs;
	}
	$tabs[] = 'agreements';
	return array_values(array_unique(array_filter(array_map('sanitize_key', $tabs))));
}, 20);

vms_test_assert_contains(
	"\$post_id = bvmgr_request_read_absint(\$_GET, 'post');",
	$adminUiSource,
	'Admissions admin UI should read the Event Plan post ID through the shared integer request helper.'
);
vms_test_assert_contains(
	"\$result = bvmgr_request_read_key(\$_GET, 'result');",
	$passClaimsSource,
	'Pass Claims admin notices should read the result filter through the shared key helper.'
);
vms_test_assert_contains(
	"\$batch_id = bvmgr_request_read_absint(\$_GET, 'batch_id');",
	$passClaimsSource,
	'Pass Claims passes-tab filtering should read batch_id through the shared integer helper.'
);
vms_test_assert_contains(
	"\$tab = bvmgr_request_read_key(\$_GET, 'tab');",
	$passClaimsSource,
	'Pass Claims admin page routing should read the tab through the shared key helper.'
);
vms_test_assert_contains(
	"\$tab = bvmgr_request_read_key(\$_GET, 'tab');",
	$vendorGuestSource,
	'Vendor Guest Portal screen-key routing should read tab through the shared key helper.'
);
vms_test_assert_contains(
	"\$selected_event = bvmgr_request_read_absint(\$_GET, 'guest_event');",
	$vendorGuestSource,
	'Vendor Guest Portal event selection should read guest_event through the shared integer helper.'
);
vms_test_assert_not_contains(
	"isset(\$_GET['guest_event']) ? absint((string) wp_unslash(\$_GET['guest_event'])) : 0;",
	$vendorGuestSource,
	'Vendor Guest Portal should no longer broad-cast guest_event through a string coercion path.'
);
vms_test_assert_contains(
	"add_filter('vms_vendor_portal_allowed_tabs', 'bvmgr_admission_vendor_guest_register_portal_tab', 20);",
	$vendorGuestSource,
	'Vendor Guest Portal should register its canonical slug with the portal allowlist.'
);

$requiredPortalTabs = array('dashboard', 'profile', 'tax-profile', 'history', 'availability', 'opportunities', 'all-vendors', 'tech', 'guest-list', 'agreements');
$allowedPortalTabs = bvmgr_vendor_portal_allowed_tabs();
vms_test_assert_same(array(), array_values(array_diff($requiredPortalTabs, $allowedPortalTabs)), 'Guest List and Agreements should preserve the complete Vendor Portal routing matrix.');

foreach ($requiredPortalTabs as $requiredPortalTab) {
	$_GET = array('tab' => $requiredPortalTab);
	vms_test_assert_same($requiredPortalTab, bvmgr_vendor_portal_get_requested_tab('dashboard'), 'Vendor Portal direct routing should preserve the allowed tab: ' . $requiredPortalTab);
}

$_GET = array('tab' => 'guest_list');
vms_test_assert_same('dashboard', bvmgr_vendor_portal_get_requested_tab('dashboard'), 'The noncanonical guest_list alias should continue to fail closed to Dashboard.');

$_GET = array('tab' => 'guest-list', 'vendor_id' => '5505');
vms_test_assert_same('guest-list', bvmgr_vendor_portal_get_requested_tab('dashboard'), 'Direct tab=guest-list should survive Vendor Portal validation.');
vms_test_assert_same(5505, bvmgr_vendor_portal_get_requested_vendor_id(), 'Direct Guest List routing should preserve vendor_id=5505.');

$routingPortalContext = array(
	'base_url' => 'https://example.test/vendor-portal/',
	'vendor_id' => 5505,
	'is_preview' => false,
);
ob_start();
bvmgr_admission_vendor_guest_add_nav_link('guest-list', $routingPortalContext);
$guestListNavHtml = (string) ob_get_clean();
vms_test_assert_contains('class="is-active"', $guestListNavHtml, 'Guest List should receive active navigation styling.');
vms_test_assert_contains('tab=guest-list', $guestListNavHtml, 'Guest List navigation should use the canonical route slug.');
vms_test_assert_contains('vendor_id=5505', $guestListNavHtml, 'Guest List navigation should retain the authorized vendor context.');

preg_match('/href="([^"]+)"/', $guestListNavHtml, $guestListNavMatch);
$clickedGuestListUrl = html_entity_decode((string) ($guestListNavMatch[1] ?? ''), ENT_QUOTES);
parse_str((string) parse_url($clickedGuestListUrl, PHP_URL_QUERY), $clickedGuestListQuery);
$_GET = $clickedGuestListQuery;
vms_test_assert_same('guest-list', bvmgr_vendor_portal_get_requested_tab('dashboard'), 'Clicking the normal Guest List link should route to Guest List.');
vms_test_assert_same(5505, bvmgr_vendor_portal_get_requested_vendor_id(), 'Clicked Guest List navigation should preserve vendor_id=5505.');
vms_test_assert(bvmgr_vendor_portal_get_requested_tab('dashboard') !== 'dashboard', 'Dashboard should not remain selected for Guest List requests.');

$_GET = array('tab' => 'agreements', 'vendor_id' => '5505');
vms_test_assert_same('agreements', bvmgr_vendor_portal_get_requested_tab('dashboard'), 'Direct tab=agreements should continue to survive Vendor Portal validation.');
vms_test_assert_same(5505, bvmgr_vendor_portal_get_requested_vendor_id(), 'Direct Agreements routing should preserve vendor_id=5505.');

$_GET = array('tab' => 'not-a-real-section');
vms_test_assert_same('dashboard', bvmgr_vendor_portal_get_requested_tab('dashboard'), 'Unknown portal tabs should continue to fail closed to Dashboard.');

$GLOBALS['vms_test_is_admin'] = true;
$GLOBALS['vms_test_admission_admin_should_load'] = true;
$GLOBALS['vms_test_caps'] = array(
	'manage_vms_admissions' => true,
	'checkin_vms_admissions' => true,
	'manage_vms_pass_claims' => true,
);
$GLOBALS['vms_test_styles'] = array();
$GLOBALS['vms_test_scripts'] = array();
$GLOBALS['vms_test_localized'] = array();
$GLOBALS['post'] = null;
$_GET = array('post' => '17');
bvmgr_admission_admin_enqueue_assets();
vms_test_assert_same(17, $GLOBALS['vms_test_localized']['bvmgr-admissions-admin']['data']['eventPlanId'] ?? null, 'Admissions admin assets should preserve scalar post IDs.');

$GLOBALS['vms_test_styles'] = array();
$GLOBALS['vms_test_scripts'] = array();
$GLOBALS['vms_test_localized'] = array();
$GLOBALS['post'] = new WP_Post(25);
$_GET = array('post' => array('17'));
bvmgr_admission_admin_enqueue_assets();
vms_test_assert_same(25, $GLOBALS['vms_test_localized']['bvmgr-admissions-admin']['data']['eventPlanId'] ?? null, 'Admissions admin assets should reject array-shaped post IDs and fall back to the current post object.');

$_GET = array('page' => 'vms-passes');
vms_test_assert_same(true, bvmgr_pass_claims_is_admin_page(), 'Pass Claims admin-page detection should preserve the valid scalar page slug.');
$_GET = array('page' => array('vms-passes'));
vms_test_assert_same(false, bvmgr_pass_claims_is_admin_page(), 'Pass Claims admin-page detection should reject array-shaped page values.');

ob_start();
$_GET = array('result' => 'token_voided');
bvmgr_pass_claims_render_admin_notices();
$noticesHtml = (string) ob_get_clean();
vms_test_assert_contains('Pass voided.', $noticesHtml, 'Pass Claims admin notices should preserve valid result notices.');

ob_start();
$_GET = array('result' => array('token_voided'));
bvmgr_pass_claims_render_admin_notices();
$noticesHtml = (string) ob_get_clean();
vms_test_assert_not_contains('Pass voided.', $noticesHtml, 'Pass Claims admin notices should reject array-shaped result values.');

$GLOBALS['vms_test_pass_claim_token_args'] = array();
ob_start();
$_GET = array('batch_id' => array('9'));
bvmgr_pass_claims_render_passes_tab();
ob_end_clean();
vms_test_assert_same(array(0, 300), $GLOBALS['vms_test_pass_claim_token_args'], 'Pass Claims passes-tab filtering should reject array-shaped batch IDs.');

$GLOBALS['vms_test_pass_claim_render_log'] = array();
ob_start();
$_GET = array('tab' => array('passes'));
bvmgr_pass_claims_render_admin_page();
ob_end_clean();
vms_test_assert_same(array('tab:sources', 'sources'), $GLOBALS['vms_test_pass_claim_render_log'], 'Pass Claims admin-page routing should fall back to the default tab when tab is malformed.');

$GLOBALS['vms_test_logged_in'] = true;
$GLOBALS['vms_test_page_slug'] = 'vendor-portal';
$_GET = array('tab' => 'guest-list');
vms_test_assert_same('frontend:vms-vendor-portal-guest-list', bvmgr_admission_vendor_guest_portal_screen_key('frontend:base'), 'Vendor Guest Portal should preserve the guest-list tab routing.');
$_GET = array('tab' => array('guest-list'));
vms_test_assert_same('frontend:base', bvmgr_admission_vendor_guest_portal_screen_key('frontend:base'), 'Vendor Guest Portal should reject array-shaped tab values.');

$GLOBALS['vms_test_guest_events'] = array(
	array('event_plan_id' => 41, 'title' => 'Event One', 'remaining' => 3, 'allotment' => 5, 'used' => 2, 'event_date' => '2026-08-01', 'venue_name' => 'Room One'),
	array('event_plan_id' => 73, 'title' => 'Event Two', 'remaining' => 1, 'allotment' => 4, 'used' => 3, 'event_date' => '2026-08-02', 'venue_name' => 'Room Two'),
);
$portalContext = array('vendor_id' => 5505, 'is_preview' => true);

ob_start();
$_GET = array('tab' => 'guest-list', 'vendor_id' => '5505', 'guest_event' => '73');
$resolvedGuestListTab = bvmgr_vendor_portal_get_requested_tab('dashboard');
$guestListRendered = bvmgr_admission_vendor_guest_render_custom_tab(false, $resolvedGuestListTab, $portalContext);
$guestHtml = (string) ob_get_clean();
vms_test_assert_same(true, $guestListRendered, 'The Guest List content provider should claim the validated Guest List route.');
vms_test_assert_contains('class="vms-vendor-guest-root"', $guestHtml, 'The existing Guest List UI should render for a validated direct route.');
vms_test_assert_same(2, substr_count($guestHtml, 'data-vms-tour="vendor-portal-guest.card"'), 'Vendor Guest Portal should preserve scalar guest_event selection for the matching event card.');

ob_start();
$_GET = array('guest_event' => array('73'));
bvmgr_admission_vendor_guest_render_custom_tab(false, 'guest-list', $portalContext);
$guestHtml = (string) ob_get_clean();
vms_test_assert_same(1, substr_count($guestHtml, 'data-vms-tour="vendor-portal-guest.card"'), 'Vendor Guest Portal should reject array-shaped guest_event values and fall back to the first event card.');

fwrite(STDOUT, "admissions read-only request state remediation: PASS\n");
