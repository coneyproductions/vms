<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);

final class VMS_Ticketing_Claims_Admin_Test_Wp_Die extends RuntimeException
{
}

if (!class_exists('WP_User')) {
	final class WP_User
	{
		public int $ID;
		public string $user_email;

		public function __construct(int $id = 0, string $email = '')
		{
			$this->ID = $id;
			$this->user_email = $email;
		}
	}
}

if (!class_exists('WP_Post')) {
	final class WP_Post
	{
		public int $ID;

		public function __construct(int $id = 0)
		{
			$this->ID = $id;
		}
	}
}

function vms_test_assert(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
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
	return $text;
}

function esc_attr(string $text): string
{
	return $text;
}

function esc_url_raw(string $url): string
{
	return $url;
}

function esc_url(string $url): string
{
	return $url;
}

function sanitize_html_class(string $value): string
{
	$sanitized = preg_replace('/[^A-Za-z0-9_-]+/', '-', $value);
	return is_string($sanitized) ? trim($sanitized, '-') : '';
}

function apply_filters(string $hook, $value)
{
	return $value;
}

function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	unset($hook, $callback, $priority, $acceptedArgs);
	return true;
}

function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
	unset($priority, $acceptedArgs);
	$GLOBALS['vms_test_actions'][$hook][] = $callback;
	return true;
}

function is_admin(): bool
{
	return !empty($GLOBALS['vms_test_is_admin']);
}

function current_user_can(string $capability): bool
{
	return !empty($GLOBALS['vms_test_capabilities'][$capability]);
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

function sanitize_textarea_field($value): string
{
	return sanitize_text_field($value);
}

function sanitize_email($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	return strtolower(trim((string) $value));
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

function admin_url(string $path = ''): string
{
	return 'https://example.test/wp-admin/' . ltrim($path, '/');
}

function home_url(string $path = '/'): string
{
	return 'https://example.test' . $path;
}

function add_query_arg($args, string $url = ''): string
{
	if (!is_array($args)) {
		return $url;
	}

	$parts = parse_url($url);
	$base = '';
	if (isset($parts['scheme'], $parts['host'])) {
		$base = $parts['scheme'] . '://' . $parts['host'];
	}
	$base .= $parts['path'] ?? '';

	$query = array();
	if (!empty($parts['query'])) {
		parse_str((string) $parts['query'], $query);
	}

	foreach ($args as $key => $value) {
		$query[(string) $key] = $value;
	}

	$queryString = http_build_query($query);
	return $queryString === '' ? $base : ($base . '?' . $queryString);
}

function wp_validate_redirect(string $location, string $fallback = ''): string
{
	$location = trim($location);
	if ($location === '') {
		return $fallback;
	}

	if (strpos($location, '/') === 0 || strpos($location, 'https://example.test/') === 0) {
		return $location;
	}

	return $fallback;
}

function wp_die($message = ''): void
{
	if (is_scalar($message)) {
		throw new VMS_Ticketing_Claims_Admin_Test_Wp_Die((string) $message);
	}

	throw new VMS_Ticketing_Claims_Admin_Test_Wp_Die('wp_die');
}

function get_current_screen()
{
	return $GLOBALS['vms_test_screen'] ?? null;
}

$repoRoot = dirname(__DIR__);
$runtimeGuards = $repoRoot . '/includes/runtime-guards.php';
$claimsAdmin = $repoRoot . '/includes/integrations/ticketing-claims-admin.php';

require_once $runtimeGuards;
require_once $claimsAdmin;

$source = (string) file_get_contents($claimsAdmin);
vms_test_assert($source !== '', 'Claims admin source should be readable.');
vms_test_assert(strpos($source, "wp_create_nonce('vms_ticketing_claims_create_grant')") !== false, 'Claims admin should preserve the create-grant nonce action.');
vms_test_assert(strpos($source, "'action' => 'vms_ticketing_claims_create_grant'") !== false, 'Claims admin should preserve the create-grant admin-post action name.');
vms_test_assert(strpos($source, "'vms_claim_lookup'") !== false, 'Claims admin should preserve the claims lookup request key.');
vms_test_assert(strpos($source, "'vms_claim_user_id'") !== false, 'Claims admin should preserve the selected-user request key.');

$footerHooks = $GLOBALS['vms_test_actions']['admin_footer'] ?? array();
$noticeHooks = $GLOBALS['vms_test_actions']['admin_notices'] ?? array();
vms_test_assert(in_array('vms_ticketing_claims_render_event_metabox_footer_forms', $footerHooks, true), 'Claims admin footer forms hook should remain registered.');
vms_test_assert(in_array('vms_ticketing_claims_render_admin_notices', $noticeHooks, true), 'Claims admin notices hook should remain registered.');

$GLOBALS['vms_test_is_admin'] = true;
$GLOBALS['vms_test_capabilities'] = array(
	'vms_manage_verifications' => false,
	'manage_options' => true,
);
$_GET = array();

vms_test_assert(vms_ticketing_claims_query_key('page') === '', 'Claims admin page helper should return an empty string when the page key is missing.');
vms_test_assert(vms_ticketing_claims_is_admin_page() === false, 'Claims admin page detection should be false when the page key is missing.');

$_GET['page'] = 'VMS-Credential-Claims';
vms_test_assert(vms_ticketing_claims_query_key('page') === 'vms-credential-claims', 'Claims admin page helper should sanitize the current page slug.');
vms_test_assert(vms_ticketing_claims_is_admin_page() === true, 'Claims admin page detection should recognize the claims page slug.');

$_GET['page'] = array('vms-credential-claims');
vms_test_assert(vms_ticketing_claims_query_key('page') === '', 'Claims admin page helper should reject array-shaped page values.');
vms_test_assert(vms_ticketing_claims_is_admin_page() === false, 'Claims admin page detection should reject array-shaped page values.');

$_GET['vms_claim_lookup'] = ' Alpha ';
vms_test_assert(vms_ticketing_claims_query_text_field('vms_claim_lookup') === 'Alpha', 'Claims admin text helper should sanitize scalar lookup text.');
$_GET['vms_claim_lookup'] = array('Alpha');
vms_test_assert(vms_ticketing_claims_query_text_field('vms_claim_lookup') === '', 'Claims admin text helper should reject array-shaped lookup text.');

$_GET['vms_claim_user_id'] = '42';
vms_test_assert(vms_ticketing_claims_query_absint('vms_claim_user_id') === 42, 'Claims admin integer helper should preserve scalar user IDs.');
$_GET['vms_claim_user_id'] = array('42');
vms_test_assert(vms_ticketing_claims_query_absint('vms_claim_user_id') === 0, 'Claims admin integer helper should reject array-shaped user IDs.');

$_GET['vms_claim_res_email'] = 'USER@example.com ';
vms_test_assert(vms_ticketing_claims_query_email('vms_claim_res_email') === 'user@example.com', 'Claims admin email helper should sanitize scalar email filters.');
$_GET['vms_claim_res_email'] = array('user@example.com');
vms_test_assert(vms_ticketing_claims_query_email('vms_claim_res_email') === '', 'Claims admin email helper should reject array-shaped email filters.');

$_GET = array('page' => 'vms-credential-claims', 'vms_claim_notice' => 'grant_created');
ob_start();
vms_ticketing_claims_render_admin_notices();
$noticeMarkup = (string) ob_get_clean();
vms_test_assert(strpos($noticeMarkup, 'Direct event grant created.') !== false, 'Claims admin notices should still render the existing success message for scalar notice state.');

$_GET = array('page' => 'vms-credential-claims', 'vms_claim_notice' => array('grant_created'));
ob_start();
vms_ticketing_claims_render_admin_notices();
$arrayNoticeMarkup = (string) ob_get_clean();
vms_test_assert($arrayNoticeMarkup === '', 'Claims admin notices should reject array-shaped notice state.');

global $bvmgr_ticketing_claims_event_metabox_forms;
$bvmgr_ticketing_claims_event_metabox_forms = array();
vms_ticketing_claims_event_metabox_register_form(
	'claims-structured',
	'get',
	'https://example.test/wp-admin/post.php',
	array(
		'ids' => array('10', '12'),
		'filters' => array('status' => 'reserved'),
	)
);
vms_test_assert(is_array($bvmgr_ticketing_claims_event_metabox_forms['claims-structured']['hidden_fields']['ids'] ?? null), 'Claims admin should continue to preserve legitimate structured hidden-field arrays.');
vms_test_assert(($bvmgr_ticketing_claims_event_metabox_forms['claims-structured']['hidden_fields']['filters']['status'] ?? '') === 'reserved', 'Claims admin should preserve nested structured hidden-field values.');

$GLOBALS['vms_test_capabilities'] = array(
	'vms_manage_verifications' => false,
	'manage_options' => false,
);
$_GET = array();
try {
	vms_ticketing_claims_render_admin_page();
	throw new RuntimeException('Claims admin page should have stopped on the capability gate.');
} catch (VMS_Ticketing_Claims_Admin_Test_Wp_Die $exception) {
	vms_test_assert(strpos($exception->getMessage(), 'Insufficient permissions.') !== false, 'Claims admin page should preserve the capability gate before request-state processing.');
}

fwrite(STDOUT, "ticketing claims admin request state remediation: PASS\n");
