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
	unset($hook, $callback, $priority, $accepted_args);
}

function apply_filters(string $hook, $value)
{
	unset($hook);
	return $value;
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

function vms_admission_settings(): array
{
	return array('allow_uncheckin' => true, 'max_party_size' => 6);
}

function vms_admission_manage_capability(): string
{
	return 'manage_vms_admissions';
}

function vms_admission_door_capability(): string
{
	return 'checkin_vms_admissions';
}

function vms_admission_admin_should_load(): bool
{
	return !empty($GLOBALS['vms_test_admission_admin_should_load']);
}

function vms_pass_claims_capability(): string
{
	return 'manage_vms_pass_claims';
}

function vms_pass_claims_menu_slug(): string
{
	return 'vms-passes';
}

function vms_pass_claims_pop_user_message(): array
{
	return array();
}

function vms_pass_claims_get_tokens(int $batch_id, int $limit): array
{
	$GLOBALS['vms_test_pass_claim_token_args'] = array($batch_id, $limit);
	return array();
}

function vms_pass_claims_export_url(int $batch_id): string
{
	return 'https://example.test/export?batch_id=' . $batch_id;
}

function vms_pass_claims_render_tab_nav(string $tab): void
{
	$GLOBALS['vms_test_pass_claim_render_log'][] = 'tab:' . $tab;
}

function vms_pass_claims_render_sources_tab(): void
{
	$GLOBALS['vms_test_pass_claim_render_log'][] = 'sources';
}

function vms_pass_claims_render_batches_tab(): void
{
	$GLOBALS['vms_test_pass_claim_render_log'][] = 'batches';
}

function vms_pass_claims_render_reports_tab(): void
{
	$GLOBALS['vms_test_pass_claim_render_log'][] = 'reports';
}

function get_current_user_id(): int
{
	return 77;
}

function vms_admission_vendor_guest_pull_flash(int $user_id): array
{
	unset($user_id);
	return array();
}

function vms_admission_vendor_guest_portal_events(int $vendor_id): array
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

$adminUiSource = (string) file_get_contents($adminUiPath);
$passClaimsSource = (string) file_get_contents($passClaimsPath);
$vendorGuestSource = (string) file_get_contents($vendorGuestPath);

vms_test_assert($adminUiSource !== '', 'Admissions admin UI source should be readable.');
vms_test_assert($passClaimsSource !== '', 'Pass Claims source should be readable.');
vms_test_assert($vendorGuestSource !== '', 'Vendor Guest Portal source should be readable.');

eval(vms_test_extract_function($adminUiSource, 'vms_admission_admin_enqueue_assets'));
eval(vms_test_extract_function($passClaimsSource, 'vms_pass_claims_is_admin_page'));
eval(vms_test_extract_function($passClaimsSource, 'vms_pass_claims_render_admin_notices'));
eval(vms_test_extract_function($passClaimsSource, 'vms_pass_claims_render_passes_tab'));
eval(vms_test_extract_function($passClaimsSource, 'vms_pass_claims_render_admin_page'));
eval(vms_test_extract_function($vendorGuestSource, 'vms_admission_vendor_guest_portal_screen_key'));
eval(vms_test_extract_function($vendorGuestSource, 'vms_admission_vendor_guest_render_custom_tab'));

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
vms_admission_admin_enqueue_assets();
vms_test_assert_same(17, $GLOBALS['vms_test_localized']['vms-admissions-admin']['data']['eventPlanId'] ?? null, 'Admissions admin assets should preserve scalar post IDs.');

$GLOBALS['vms_test_styles'] = array();
$GLOBALS['vms_test_scripts'] = array();
$GLOBALS['vms_test_localized'] = array();
$GLOBALS['post'] = new WP_Post(25);
$_GET = array('post' => array('17'));
vms_admission_admin_enqueue_assets();
vms_test_assert_same(25, $GLOBALS['vms_test_localized']['vms-admissions-admin']['data']['eventPlanId'] ?? null, 'Admissions admin assets should reject array-shaped post IDs and fall back to the current post object.');

$_GET = array('page' => 'vms-passes');
vms_test_assert_same(true, vms_pass_claims_is_admin_page(), 'Pass Claims admin-page detection should preserve the valid scalar page slug.');
$_GET = array('page' => array('vms-passes'));
vms_test_assert_same(false, vms_pass_claims_is_admin_page(), 'Pass Claims admin-page detection should reject array-shaped page values.');

ob_start();
$_GET = array('result' => 'token_voided');
vms_pass_claims_render_admin_notices();
$noticesHtml = (string) ob_get_clean();
vms_test_assert_contains('Pass voided.', $noticesHtml, 'Pass Claims admin notices should preserve valid result notices.');

ob_start();
$_GET = array('result' => array('token_voided'));
vms_pass_claims_render_admin_notices();
$noticesHtml = (string) ob_get_clean();
vms_test_assert_not_contains('Pass voided.', $noticesHtml, 'Pass Claims admin notices should reject array-shaped result values.');

$GLOBALS['vms_test_pass_claim_token_args'] = array();
ob_start();
$_GET = array('batch_id' => array('9'));
vms_pass_claims_render_passes_tab();
ob_end_clean();
vms_test_assert_same(array(0, 300), $GLOBALS['vms_test_pass_claim_token_args'], 'Pass Claims passes-tab filtering should reject array-shaped batch IDs.');

$GLOBALS['vms_test_pass_claim_render_log'] = array();
ob_start();
$_GET = array('tab' => array('passes'));
vms_pass_claims_render_admin_page();
ob_end_clean();
vms_test_assert_same(array('tab:sources', 'sources'), $GLOBALS['vms_test_pass_claim_render_log'], 'Pass Claims admin-page routing should fall back to the default tab when tab is malformed.');

$GLOBALS['vms_test_logged_in'] = true;
$GLOBALS['vms_test_page_slug'] = 'vendor-portal';
$_GET = array('tab' => 'guest-list');
vms_test_assert_same('frontend:vms-vendor-portal-guest-list', vms_admission_vendor_guest_portal_screen_key('frontend:base'), 'Vendor Guest Portal should preserve the guest-list tab routing.');
$_GET = array('tab' => array('guest-list'));
vms_test_assert_same('frontend:base', vms_admission_vendor_guest_portal_screen_key('frontend:base'), 'Vendor Guest Portal should reject array-shaped tab values.');

$GLOBALS['vms_test_guest_events'] = array(
	array('event_plan_id' => 41, 'title' => 'Event One', 'remaining' => 3, 'allotment' => 5, 'used' => 2, 'event_date' => '2026-08-01', 'venue_name' => 'Room One'),
	array('event_plan_id' => 73, 'title' => 'Event Two', 'remaining' => 1, 'allotment' => 4, 'used' => 3, 'event_date' => '2026-08-02', 'venue_name' => 'Room Two'),
);
$portalContext = array('vendor_id' => 11, 'is_preview' => true);

ob_start();
$_GET = array('guest_event' => '73');
vms_admission_vendor_guest_render_custom_tab(false, 'guest-list', $portalContext);
$guestHtml = (string) ob_get_clean();
vms_test_assert_same(2, substr_count($guestHtml, 'data-vms-tour="vendor-portal-guest.card"'), 'Vendor Guest Portal should preserve scalar guest_event selection for the matching event card.');

ob_start();
$_GET = array('guest_event' => array('73'));
vms_admission_vendor_guest_render_custom_tab(false, 'guest-list', $portalContext);
$guestHtml = (string) ob_get_clean();
vms_test_assert_same(1, substr_count($guestHtml, 'data-vms-tour="vendor-portal-guest.card"'), 'Vendor Guest Portal should reject array-shaped guest_event values and fall back to the first event card.');

fwrite(STDOUT, "admissions read-only request state remediation: PASS\n");
