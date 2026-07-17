<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

$GLOBALS['vms_test_actions'] = array();
$GLOBALS['vms_test_current_user_caps'] = array(
	'manage_options' => true,
);
$GLOBALS['vms_test_current_screen'] = null;
$GLOBALS['vms_test_pending_certification_count'] = 0;
$GLOBALS['vms_test_provider_reads'] = array(
	'current_user_can' => 0,
	'get_current_screen' => 0,
	'pending_count' => 0,
	'admin_url' => 0,
);
$GLOBALS['vms_test_mutation_calls'] = array(
	'review_url_helper' => 0,
	'review_items_helper' => 0,
);

if (!function_exists('add_action')) {
	function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
	{
		if (!isset($GLOBALS['vms_test_actions'][$hook])) {
			$GLOBALS['vms_test_actions'][$hook] = array();
		}
		if (!isset($GLOBALS['vms_test_actions'][$hook][$priority])) {
			$GLOBALS['vms_test_actions'][$hook][$priority] = array();
		}

		$GLOBALS['vms_test_actions'][$hook][$priority][] = array(
			'callback' => $callback,
			'accepted_args' => $accepted_args,
		);

		return true;
	}
}

if (!function_exists('__')) {
	function __(string $text, string $domain = ''): string
	{
		unset($domain);
		return $text;
	}
}

if (!function_exists('_n')) {
	function _n(string $single, string $plural, int $number, string $domain = ''): string
	{
		unset($domain);
		return $number === 1 ? $single : $plural;
	}
}

if (!function_exists('esc_html')) {
	function esc_html($text): string
	{
		return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('esc_html__')) {
	function esc_html__(string $text, string $domain = ''): string
	{
		return esc_html(__($text, $domain));
	}
}

if (!function_exists('esc_attr')) {
	function esc_attr($text): string
	{
		return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('esc_url')) {
	function esc_url($url): string
	{
		return htmlspecialchars((string) $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('sanitize_key')) {
	function sanitize_key($value): string
	{
		if (!is_scalar($value)) {
			return '';
		}

		$sanitized = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value));
		return is_string($sanitized) ? $sanitized : '';
	}
}

if (!function_exists('wp_kses')) {
	function wp_kses(string $text, array $allowed_html = array()): string
	{
		unset($allowed_html);
		return $text;
	}
}

if (!function_exists('current_user_can')) {
	function current_user_can(string $capability): bool
	{
		$GLOBALS['vms_test_provider_reads']['current_user_can']++;
		return !empty($GLOBALS['vms_test_current_user_caps'][$capability]);
	}
}

if (!function_exists('get_current_screen')) {
	function get_current_screen()
	{
		$GLOBALS['vms_test_provider_reads']['get_current_screen']++;
		return $GLOBALS['vms_test_current_screen'];
	}
}

if (!function_exists('admin_url')) {
	function admin_url(string $path = ''): string
	{
		$GLOBALS['vms_test_provider_reads']['admin_url']++;
		return 'https://example.test/wp-admin/' . ltrim($path, '/');
	}
}

if (!function_exists('vms_staffing_get_pending_staff_qualification_count')) {
	function vms_staffing_get_pending_staff_qualification_count()
	{
		$GLOBALS['vms_test_provider_reads']['pending_count']++;
		return $GLOBALS['vms_test_pending_certification_count'];
	}
}

if (!function_exists('vms_staffing_get_staff_qualification_review_items')) {
	function vms_staffing_get_staff_qualification_review_items(string $status = 'pending_verification'): array
	{
		$GLOBALS['vms_test_mutation_calls']['review_items_helper']++;
		unset($status);
		return array();
	}
}

if (!function_exists('vms_staffing_staff_qualification_review_url')) {
	function vms_staffing_staff_qualification_review_url(int $staff_id): string
	{
		$GLOBALS['vms_test_mutation_calls']['review_url_helper']++;
		return 'https://example.test/wp-admin/post.php?post=' . $staff_id . '&action=edit';
	}
}

require_once dirname(__DIR__) . '/includes/admin-ui/shell.php';
require_once dirname(__DIR__) . '/includes/admin/staff-certifications.php';

$pluginRoot = dirname(__DIR__);
$staffCertificationsSource = file_get_contents($pluginRoot . '/includes/admin/staff-certifications.php');
$staffingSource = file_get_contents($pluginRoot . '/includes/core/staffing.php');
$shellSource = file_get_contents($pluginRoot . '/includes/admin-ui/shell.php');

$assert = static function ($condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$assertSame = static function ($expected, $actual, string $message) use ($assert): void {
	$assert(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
};

$reset_state = static function (): void {
	$GLOBALS['vms_test_current_user_caps'] = array(
		'manage_options' => true,
	);
	$GLOBALS['vms_test_current_screen'] = null;
	$GLOBALS['vms_test_pending_certification_count'] = 0;
	$GLOBALS['vms_test_provider_reads'] = array(
		'current_user_can' => 0,
		'get_current_screen' => 0,
		'pending_count' => 0,
		'admin_url' => 0,
	);
	$GLOBALS['vms_test_mutation_calls'] = array(
		'review_url_helper' => 0,
		'review_items_helper' => 0,
	);
};

$assert(is_string($staffCertificationsSource) && $staffCertificationsSource !== '', 'Staff Certifications source should be readable.');
$assert(is_string($staffingSource) && $staffingSource !== '', 'Staffing source should be readable.');
$assert(is_string($shellSource) && $shellSource !== '', 'Shell source should be readable.');
$assert(strpos($staffCertificationsSource, "add_action('admin_notices', 'vms_staff_certifications_render_pending_review_admin_notice');") !== false, 'Staff Certifications should preserve the admin_notices registration through the named callback.');
$assert(strpos($staffCertificationsSource, "add_action('admin_notices', function (): void {") === false, 'Staff Certifications should no longer keep the pending-review warning as an anonymous admin_notices closure.');
$assert(strpos($staffCertificationsSource, 'function vms_staff_certifications_get_pending_review_warning_context(): array') !== false, 'Staff Certifications should expose a dedicated pending-review warning context builder.');
$assert(strpos($staffCertificationsSource, 'function vms_staff_certifications_render_pending_review_warning(array $context): void') !== false, 'Staff Certifications should expose a dedicated pending-review warning renderer.');
$assert(strpos($staffCertificationsSource, 'function vms_staff_certifications_render_pending_review_admin_notice(): void') !== false, 'Staff Certifications should expose a dedicated pending-review warning hook callback.');
$assert(strpos($staffCertificationsSource, 'vms_staff_certifications_render_pending_review_warning(vms_staff_certifications_get_pending_review_warning_context());') !== false, 'Staff Certifications admin_notices callback should route through the new context builder and renderer.');
$assert(strpos($staffCertificationsSource, '$screen && isset($screen->id) && $screen->id === \'vms_page_vms-staff-certifications\'') !== false, 'Staff Certifications warning should preserve the exact screen visibility guard.');
$assert(strpos($staffCertificationsSource, "admin_url('admin.php?page=vms-staff-certifications')") !== false, 'Staff Certifications warning should preserve the exact administration URL builder.');
$assert(strpos($staffCertificationsSource, 'notice notice-warning is-dismissible vms-staff-certifications-admin-notice') !== false, 'Staff Certifications warning should preserve the exact notice classes.');
$assert(strpos($staffCertificationsSource, 'esc_html__(\'Open review queue\'') !== false, 'Staff Certifications warning should preserve the exact link label.');
$assert(strpos($staffCertificationsSource, 'vms_staffing_get_pending_staff_qualification_count()') !== false, 'Staff Certifications pending-count helper should preserve the existing producer.');
$assert(strpos($staffingSource, "return count(vms_staffing_get_staff_qualification_review_items('pending_verification'));") !== false, 'Staff Certifications warning should preserve the exact pending_verification count producer.');
$assert(strpos($staffingSource, "'post_status' => array('publish', 'draft', 'pending', 'private')") !== false, 'Staff Certifications warning should preserve the exact staff post statuses included by the provider.');
$assert(strpos($staffingSource, "return array('active', 'pending_verification', 'rejected', 'expired', 'inactive');") !== false, 'Staff Certifications warning should preserve the existing qualification status vocabulary.');
$assert(strpos($staffingSource, 'if ($row_status !== $status) {') !== false, 'Staff Certifications warning should preserve the exact row-status filter.');
$assert(strpos($staffCertificationsSource, 'wp_kses_post(') === false, 'Staff Certifications warning should not introduce a broad sanitizer.');

$rendererStart = strpos($staffCertificationsSource, 'function vms_staff_certifications_render_pending_review_warning(array $context): void');
$callbackStart = strpos($staffCertificationsSource, 'function vms_staff_certifications_render_pending_review_admin_notice(): void');
$assert(is_int($rendererStart) && is_int($callbackStart) && $rendererStart < $callbackStart, 'Staff Certifications warning renderer source should be locatable.');
$rendererSource = substr($staffCertificationsSource, $rendererStart, $callbackStart - $rendererStart);
$assert(is_string($rendererSource) && $rendererSource !== '', 'Staff Certifications warning renderer source should be extractable.');
$assert(strpos($rendererSource, 'current_user_can(') === false, 'Staff Certifications warning renderer should not perform capability reads.');
$assert(strpos($rendererSource, 'get_current_screen(') === false, 'Staff Certifications warning renderer should not perform screen reads.');
$assert(strpos($rendererSource, 'vms_staff_certifications_pending_count(') === false, 'Staff Certifications warning renderer should not perform count-provider reads.');
$assert(strpos($rendererSource, 'admin_url(') === false, 'Staff Certifications warning renderer should not build URLs.');
$assert(strpos($rendererSource, 'vms_staffing_staff_qualification_review_url(') === false, 'Staff Certifications warning renderer should not invoke certification review helpers.');
$assert(strpos($rendererSource, 'esc_url($review_url)') !== false, 'Staff Certifications warning renderer should escape the review URL.');

$assertSame(
	array(
		'div' => array('class' => true),
		'p' => array(),
	),
	vms_admin_ui_explicit_notice_allowed_html(),
	'Administrator shell simple notice contract should remain unchanged.'
);
$assertSame(
	array(
		'div' => array('class' => true),
		'p' => array(),
		'strong' => array(),
	),
	vms_admin_ui_rich_explicit_notice_allowed_html(),
	'Administrator shell rich notice contract should remain unchanged.'
);

$assert(isset($GLOBALS['vms_test_actions']['admin_notices'][10][0]), 'Staff Certifications should remain registered on admin_notices at the default priority.');
$assertSame(
	array(
		'callback' => 'vms_staff_certifications_render_pending_review_admin_notice',
		'accepted_args' => 1,
	),
	$GLOBALS['vms_test_actions']['admin_notices'][10][0],
	'Staff Certifications should preserve the admin_notices callback name, priority, and accepted-args behavior.'
);

$reset_state();
$GLOBALS['vms_test_current_user_caps']['manage_options'] = false;
ob_start();
vms_staff_certifications_render_pending_review_admin_notice();
$noCapabilityNotice = (string) ob_get_clean();
$assertSame('', $noCapabilityNotice, 'Staff Certifications warning should stay hidden without manage_options.');
$assertSame(
	array(
		'current_user_can' => 1,
		'get_current_screen' => 0,
		'pending_count' => 0,
		'admin_url' => 0,
	),
	$GLOBALS['vms_test_provider_reads'],
	'Staff Certifications warning should preserve the capability short-circuit before screen, count, and URL reads.'
);

$reset_state();
$GLOBALS['vms_test_current_screen'] = (object) array('id' => 'vms_page_vms-staff-certifications');
$GLOBALS['vms_test_pending_certification_count'] = 4;
$hiddenScreenContext = vms_staff_certifications_get_pending_review_warning_context();
$assertSame(
	array(
		'show' => false,
		'pending_count' => 0,
		'review_url' => '',
	),
	$hiddenScreenContext,
	'Staff Certifications warning should stay hidden on the Staff Certifications admin screen.'
);
$assertSame(
	array(
		'current_user_can' => 1,
		'get_current_screen' => 1,
		'pending_count' => 0,
		'admin_url' => 0,
	),
	$GLOBALS['vms_test_provider_reads'],
	'Staff Certifications warning should preserve the existing screen guard ordering.'
);

$reset_state();
$GLOBALS['vms_test_current_screen'] = (object) array('id' => 'dashboard');
$zeroCountContext = vms_staff_certifications_get_pending_review_warning_context();
$assertSame(
	array(
		'show' => false,
		'pending_count' => 0,
		'review_url' => '',
	),
	$zeroCountContext,
	'Staff Certifications warning should stay hidden when no pending certifications exist.'
);
$assertSame(
	array(
		'current_user_can' => 1,
		'get_current_screen' => 1,
		'pending_count' => 1,
		'admin_url' => 0,
	),
	$GLOBALS['vms_test_provider_reads'],
	'Staff Certifications warning should preserve the existing count lookup ordering.'
);

$reset_state();
$GLOBALS['vms_test_current_screen'] = (object) array('id' => 'dashboard');
$GLOBALS['vms_test_pending_certification_count'] = 1;
$singleContext = vms_staff_certifications_get_pending_review_warning_context();
$assertSame(
	array(
		'show' => true,
		'pending_count' => 1,
		'review_url' => 'https://example.test/wp-admin/admin.php?page=vms-staff-certifications',
	),
	$singleContext,
	'Staff Certifications warning context should preserve the single pending-review count and review URL.'
);
ob_start();
vms_staff_certifications_render_pending_review_admin_notice();
$singleCallbackNotice = (string) ob_get_clean();
$assertSame(
	'<div class="notice notice-warning is-dismissible vms-staff-certifications-admin-notice"><p><strong>1 staff certification needs review.</strong> <a href="https://example.test/wp-admin/admin.php?page=vms-staff-certifications">Open review queue</a></p></div>',
	$singleCallbackNotice,
	'Staff Certifications warning callback should preserve the exact singular markup contract.'
);
$assert(strpos($singleCallbackNotice, 'No staff certifications are waiting for review.') === false, 'Staff Certifications pending-review warning should not include the separate empty-state notice.');
$assert(strpos($singleCallbackNotice, 'Review on Staff Profile') === false, 'Staff Certifications pending-review warning should not include row-action links from the page content.');
$assert(strpos($singleCallbackNotice, ' inline') === false, 'Staff Certifications pending-review warning should not include the inline page-local notice family.');

$reset_state();
$renderedSingleNotice = '';
ob_start();
vms_staff_certifications_render_pending_review_warning(
	array(
		'show' => true,
		'pending_count' => 1,
		'review_url' => 'https://example.test/wp-admin/admin.php?page=vms-staff-certifications',
	)
);
$renderedSingleNotice = (string) ob_get_clean();
$assertSame(
	'<div class="notice notice-warning is-dismissible vms-staff-certifications-admin-notice"><p><strong>1 staff certification needs review.</strong> <a href="https://example.test/wp-admin/admin.php?page=vms-staff-certifications">Open review queue</a></p></div>',
	$renderedSingleNotice,
	'Staff Certifications warning renderer should preserve the exact singular markup contract.'
);
$assertSame(
	array(
		'current_user_can' => 0,
		'get_current_screen' => 0,
		'pending_count' => 0,
		'admin_url' => 0,
	),
	$GLOBALS['vms_test_provider_reads'],
	'Staff Certifications warning renderer should perform no external reads.'
);
$assertSame(
	array(
		'review_url_helper' => 0,
		'review_items_helper' => 0,
	),
	$GLOBALS['vms_test_mutation_calls'],
	'Staff Certifications warning renderer should not invoke certification review or mutation helpers.'
);

$reset_state();
ob_start();
vms_staff_certifications_render_pending_review_warning(
	array(
		'show' => true,
		'pending_count' => 3,
		'review_url' => 'https://example.test/wp-admin/admin.php?page=vms-staff-certifications',
	)
);
$renderedPluralNotice = (string) ob_get_clean();
$assertSame(
	'<div class="notice notice-warning is-dismissible vms-staff-certifications-admin-notice"><p><strong>3 staff certifications need review.</strong> <a href="https://example.test/wp-admin/admin.php?page=vms-staff-certifications">Open review queue</a></p></div>',
	$renderedPluralNotice,
	'Staff Certifications warning renderer should preserve the exact plural markup contract.'
);

$reset_state();
$GLOBALS['vms_test_current_screen'] = (object) array('id' => 'dashboard');
$GLOBALS['vms_test_pending_certification_count'] = '-5';
$negativeContext = vms_staff_certifications_get_pending_review_warning_context();
$assertSame(
	array(
		'show' => false,
		'pending_count' => 0,
		'review_url' => '',
	),
	$negativeContext,
	'Staff Certifications warning context should normalize negative provider counts to a hidden state.'
);

$reset_state();
ob_start();
vms_staff_certifications_render_pending_review_warning(
	array(
		'show' => true,
		'pending_count' => '12<script>alert(1)</script>',
		'review_url' => 'https://example.test/wp-admin/admin.php?page=vms-staff-certifications&unsafe=1',
	)
);
$normalizedMalformedNotice = (string) ob_get_clean();
$assertSame(
	'<div class="notice notice-warning is-dismissible vms-staff-certifications-admin-notice"><p><strong>12 staff certifications need review.</strong> <a href="https://example.test/wp-admin/admin.php?page=vms-staff-certifications&amp;unsafe=1">Open review queue</a></p></div>',
	$normalizedMalformedNotice,
	'Staff Certifications warning renderer should normalize malformed counts safely and escape the URL.'
);
$assert(
	strpos($normalizedMalformedNotice, '<script') === false
	&& strpos($normalizedMalformedNotice, 'onclick=') === false
	&& strpos($normalizedMalformedNotice, 'target=') === false
	&& strpos($normalizedMalformedNotice, 'rel=') === false,
	'Staff Certifications warning renderer should not emit scripts, handlers, or unexpected link attributes.'
);

$reset_state();
ob_start();
vms_staff_certifications_render_pending_review_warning(
	array(
		'show' => false,
		'pending_count' => 9,
		'review_url' => 'https://example.test/wp-admin/admin.php?page=vms-staff-certifications',
	)
);
$hiddenNotice = (string) ob_get_clean();
$assertSame('', $hiddenNotice, 'Staff Certifications warning renderer should stay silent for hidden contexts.');

fwrite(STDOUT, "staff certifications pending review notice output remediation: PASS\n");
