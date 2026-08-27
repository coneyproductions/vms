<?php
declare(strict_types=1);

define('ABSPATH', __DIR__);
define('WEEK_IN_SECONDS', 7 * 24 * 60 * 60);
define('MINUTE_IN_SECONDS', 60);

$GLOBALS['vms_test_actions'] = array();
$GLOBALS['vms_test_doing_ajax'] = false;
$GLOBALS['vms_test_doing_cron'] = false;
$GLOBALS['vms_test_is_admin'] = true;
$GLOBALS['vms_test_manage_options'] = true;
$GLOBALS['vms_test_options'] = array();
$GLOBALS['vms_test_post_types'] = array();
$GLOBALS['vms_test_screen'] = null;
$GLOBALS['vms_test_transients'] = array();
$GLOBALS['vms_test_updated_options'] = array();
$GLOBALS['vms_test_deleted_options'] = array();
$GLOBALS['vms_test_deleted_transients'] = array();
$GLOBALS['vms_test_redirects'] = array();

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
{
	unset($accepted_args);
	if (!isset($GLOBALS['vms_test_actions'][$hook])) {
		$GLOBALS['vms_test_actions'][$hook] = array();
	}
	if (!isset($GLOBALS['vms_test_actions'][$hook][$priority])) {
		$GLOBALS['vms_test_actions'][$hook][$priority] = array();
	}
	$GLOBALS['vms_test_actions'][$hook][$priority][] = $callback;
	return true;
}

function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
{
	unset($hook, $callback, $priority, $accepted_args);
	return true;
}

function apply_filters(string $hook, $value)
{
	unset($hook);
	return $value;
}

function sanitize_key($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)) ?: '';
}

function sanitize_text_field($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$value = stripslashes((string) $value);
	$value = preg_replace('/[\x00-\x1F\x7F]+/', '', $value);
	return trim((string) $value);
}

function sanitize_textarea_field($value): string
{
	return sanitize_text_field($value);
}

function sanitize_email($value): string
{
	$value = sanitize_text_field($value);
	return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
}

function wp_unslash($value)
{
	if (is_array($value)) {
		return array_map('wp_unslash', $value);
	}

	if (is_string($value)) {
		return stripslashes($value);
	}

	return $value;
}

function absint($value): int
{
	return abs((int) $value);
}

function is_admin(): bool
{
	return !empty($GLOBALS['vms_test_is_admin']);
}

function wp_doing_ajax(): bool
{
	return !empty($GLOBALS['vms_test_doing_ajax']);
}

function wp_doing_cron(): bool
{
	return !empty($GLOBALS['vms_test_doing_cron']);
}

function current_user_can(string $capability): bool
{
	unset($capability);
	return !empty($GLOBALS['vms_test_manage_options']);
}

function get_current_screen()
{
	return $GLOBALS['vms_test_screen'];
}

function get_post_type(int $post_id)
{
	return $GLOBALS['vms_test_post_types'][$post_id] ?? '';
}

function get_option(string $name, $default = false)
{
	return array_key_exists($name, $GLOBALS['vms_test_options']) ? $GLOBALS['vms_test_options'][$name] : $default;
}

function update_option(string $name, $value, bool $autoload = true): bool
{
	unset($autoload);
	$GLOBALS['vms_test_options'][$name] = $value;
	$GLOBALS['vms_test_updated_options'][$name] = $value;
	return true;
}

function delete_option(string $name): bool
{
	unset($GLOBALS['vms_test_options'][$name]);
	$GLOBALS['vms_test_deleted_options'][] = $name;
	return true;
}

function get_transient(string $name)
{
	return array_key_exists($name, $GLOBALS['vms_test_transients']) ? $GLOBALS['vms_test_transients'][$name] : false;
}

function set_transient(string $name, $value, int $expiration = 0): bool
{
	unset($expiration);
	$GLOBALS['vms_test_transients'][$name] = $value;
	return true;
}

function delete_transient(string $name): bool
{
	unset($GLOBALS['vms_test_transients'][$name]);
	$GLOBALS['vms_test_deleted_transients'][] = $name;
	return true;
}

function admin_url(string $path = ''): string
{
	return 'https://example.test/wp-admin/' . ltrim($path, '/');
}

function add_query_arg($args, string $url = ''): string
{
	$base = $url !== '' ? $url : admin_url('index.php');
	$parts = parse_url($base);
	$query = array();
	if (!empty($parts['query'])) {
		parse_str($parts['query'], $query);
	}
	foreach ((array) $args as $key => $value) {
		$query[(string) $key] = (string) $value;
	}

	$rebuilt = '';
	if (!empty($parts['scheme'])) {
		$rebuilt .= $parts['scheme'] . '://';
	}
	if (!empty($parts['host'])) {
		$rebuilt .= $parts['host'];
	}
	if (!empty($parts['path'])) {
		$rebuilt .= $parts['path'];
	}
	if (!empty($query)) {
		$rebuilt .= '?' . http_build_query($query);
	}

	return $rebuilt;
}

function remove_query_arg($keys, string $url = ''): string
{
	$base = $url !== '' ? $url : admin_url('index.php');
	$parts = parse_url($base);
	$query = array();
	if (!empty($parts['query'])) {
		parse_str($parts['query'], $query);
	}
	foreach ((array) $keys as $key) {
		unset($query[(string) $key]);
	}

	$rebuilt = '';
	if (!empty($parts['scheme'])) {
		$rebuilt .= $parts['scheme'] . '://';
	}
	if (!empty($parts['host'])) {
		$rebuilt .= $parts['host'];
	}
	if (!empty($parts['path'])) {
		$rebuilt .= $parts['path'];
	}
	if (!empty($query)) {
		$rebuilt .= '?' . http_build_query($query);
	}

	return $rebuilt;
}

function wp_nonce_url(string $url, string $action): string
{
	return add_query_arg(array('_wpnonce' => 'nonce:' . $action), $url);
}

function check_admin_referer(string $action): bool
{
	unset($action);
	return true;
}

function wp_safe_redirect(string $location): bool
{
	$GLOBALS['vms_test_redirects'][] = $location;
	return true;
}

function esc_html($value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function esc_html__(string $text, string $domain = ''): string
{
	unset($domain);
	return $text;
}

function esc_url($value): string
{
	return (string) $value;
}

function __(string $text, string $domain = ''): string
{
	unset($domain);
	return $text;
}

function vms_ticket_integrity_admin_url(array $args = array()): string
{
	return add_query_arg($args, admin_url('admin.php?page=vms-ticket-integrity'));
}

function vms_ticket_integrity_format_datetime(int $timestamp): string
{
	return 'DATE:' . $timestamp;
}

require dirname(__DIR__) . '/includes/runtime-guards.php';
require dirname(__DIR__) . '/includes/admin-ui/context.php';
require dirname(__DIR__) . '/includes/admin/admin-notices.php';
require dirname(__DIR__) . '/includes/ticketing/ticket-integrity-payment-gateway-health.php';

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$capture = static function (callable $callback): string {
	ob_start();
	$callback();
	return (string) ob_get_clean();
};

$flattenActions = static function (string $hook): array {
	$callbacks = array();
	$priorities = $GLOBALS['vms_test_actions'][$hook] ?? array();
	ksort($priorities);
	foreach ($priorities as $bucket) {
		foreach ($bucket as $callback) {
			$callbacks[] = $callback;
		}
	}
	return $callbacks;
};

$setScreen = static function (array $get, $screen, array $post_types = array()): void {
	$_GET = $get;
	$_POST = array();
	$_REQUEST = $get;
	$GLOBALS['vms_test_screen'] = $screen;
	$GLOBALS['vms_test_post_types'] = $post_types;
	$GLOBALS['vms_test_deleted_transients'] = array();
	$GLOBALS['vms_test_updated_options'] = array();
};

$seedFirstRunState = static function (): void {
	$GLOBALS['vms_test_options']['vms_show_first_run_notice'] = '1';
};

$seedRuntimeState = static function (): void {
	$GLOBALS['vms_test_transients']['vms_admin_diagnostic_queue'] = array(
		'missing_asset' => array(
			'message' => 'Missing internal VMS file for smoke test.',
			'queued_at' => 123,
		),
	);
	$GLOBALS['vms_test_options']['vms_admin_diagnostic_seen'] = array();
};

$seedPaymentState = static function (): void {
	$GLOBALS['vms_test_options'][vms_ticket_integrity_payment_gateway_notice_option_key()] = array(
		'active' => true,
		'status' => 'critical',
		'message' => 'Checkout APIs are failing.',
		'first_detected_failure_gmt' => 456,
	);
};

$contextSource = file_get_contents(dirname(__DIR__) . '/includes/admin-ui/context.php');
$adminNoticesSource = file_get_contents(dirname(__DIR__) . '/includes/admin/admin-notices.php');
$runtimeSource = file_get_contents(dirname(__DIR__) . '/includes/runtime-guards.php');
$paymentSource = file_get_contents(dirname(__DIR__) . '/includes/ticketing/ticket-integrity-payment-gateway-health.php');

$assert(is_string($contextSource) && strpos($contextSource, 'function bvmgr_admin_ui_is_admin_notice_screen') !== false, 'Context helper should define the shared admin-notice screen predicate.');
$assert(is_string($contextSource) && strpos($contextSource, 'return bvmgr_admin_ui_is_vms_screen($screen);') !== false, 'Admin-notice screen predicate should delegate to the established VMS screen helper.');
$assert(is_string($adminNoticesSource) && strpos($adminNoticesSource, "add_action('admin_notices', function () {") !== false, 'First-run notice should remain hooked to admin_notices.');
$assert(is_string($runtimeSource) && strpos($runtimeSource, "add_action('admin_notices', 'bvmgr_render_admin_diagnostics');") !== false, 'Runtime diagnostics should remain hooked to admin_notices.');
$assert(is_string($paymentSource) && strpos($paymentSource, "add_action('admin_notices', 'vms_ticket_integrity_render_payment_gateway_admin_notice', 18);") !== false, 'Payment gateway notice should remain hooked to admin_notices at priority 18.');
$assert(strpos((string) $adminNoticesSource, 'bvmgr_admin_ui_is_admin_notice_screen') !== false, 'First-run notice should use the shared admin-notice screen predicate.');
$assert(strpos((string) $runtimeSource, 'bvmgr_admin_ui_is_admin_notice_screen') !== false, 'Runtime diagnostics should use the shared admin-notice screen predicate.');
$assert(strpos((string) $paymentSource, 'bvmgr_admin_ui_is_admin_notice_screen') !== false, 'Payment gateway notice should use the shared admin-notice screen predicate.');
$assert(strpos((string) $adminNoticesSource, 'vms_dismiss_first_run_notice') !== false, 'First-run dismissal action and nonce should remain present.');

$adminNoticeCallbacks = $flattenActions('admin_notices');
$closureCallbacks = array_values(array_filter($adminNoticeCallbacks, static function ($callback): bool {
	return $callback instanceof Closure;
}));
$assert(count($closureCallbacks) === 1, 'Exactly one anonymous first-run admin_notices callback should be registered.');
$assert(in_array('bvmgr_render_admin_diagnostics', $adminNoticeCallbacks, true), 'Runtime diagnostics callback should remain registered.');
$assert(in_array('vms_ticket_integrity_render_payment_gateway_admin_notice', $adminNoticeCallbacks, true), 'Payment gateway notice callback should remain registered.');

$firstRunCallback = $closureCallbacks[0];
$runtimeCallback = 'bvmgr_render_admin_diagnostics';
$paymentCallback = 'vms_ticket_integrity_render_payment_gateway_admin_notice';

$setScreen(array('page' => 'vms-dashboard'), null);
$assert(bvmgr_admin_ui_is_admin_notice_screen() === false, 'Admin-notice predicate should fail closed when screen context is unavailable.');

$setScreen(
	array('page' => 'vms-dashboard'),
	(object) array('id' => 'toplevel_page_vms-dashboard', 'post_type' => '')
);
$assert(bvmgr_admin_ui_is_admin_notice_screen() === true, 'VMS dashboard should be an allowed admin-notice screen.');

$setScreen(
	array('post_type' => 'vms_event_plan'),
	(object) array('id' => 'vms_event_plan', 'post_type' => 'vms_event_plan')
);
$assert(bvmgr_admin_ui_is_admin_notice_screen() === true, 'Event Plan edit/new screens should be allowed admin-notice screens.');

$setScreen(
	array('page' => 'vms-ticket-integrity'),
	(object) array('id' => 'vms-dashboard_page_vms-ticket-integrity', 'post_type' => '')
);
$assert(bvmgr_admin_ui_is_admin_notice_screen() === true, 'Ticket Integrity screen should be an allowed admin-notice screen.');

$setScreen(
	array('page' => 'plugins.php'),
	(object) array('id' => 'plugins', 'post_type' => '')
);
$assert(bvmgr_admin_ui_is_admin_notice_screen() === false, 'Unrelated admin screens should not be allowed admin-notice screens.');

$unrelatedScreens = array(
	array(
		'label' => 'dashboard',
		'get' => array(),
		'screen' => (object) array('id' => 'dashboard', 'post_type' => ''),
		'post_types' => array(),
	),
	array(
		'label' => 'plugins',
		'get' => array(),
		'screen' => (object) array('id' => 'plugins', 'post_type' => ''),
		'post_types' => array(),
	),
	array(
		'label' => 'posts list',
		'get' => array('post_type' => 'post'),
		'screen' => (object) array('id' => 'edit-post', 'post_type' => 'post'),
		'post_types' => array(),
	),
	array(
		'label' => 'post edit',
		'get' => array('post' => '77'),
		'screen' => (object) array('id' => 'post', 'post_type' => 'post'),
		'post_types' => array(77 => 'post'),
	),
	array(
		'label' => 'plugin admin page',
		'get' => array('page' => 'wc-status'),
		'screen' => (object) array('id' => 'woocommerce_page_wc-status', 'post_type' => ''),
		'post_types' => array(),
	),
);

foreach ($unrelatedScreens as $fixture) {
	$setScreen($fixture['get'], $fixture['screen'], $fixture['post_types']);
	$seedFirstRunState();
	$seedRuntimeState();
	$seedPaymentState();

	$assert($capture($firstRunCallback) === '', 'First-run notice should stay hidden on unrelated screen: ' . $fixture['label']);
	$assert($capture($runtimeCallback) === '', 'Runtime diagnostics should stay hidden on unrelated screen: ' . $fixture['label']);
	$assert($capture($paymentCallback) === '', 'Payment gateway notice should stay hidden on unrelated screen: ' . $fixture['label']);
	$assert(isset($GLOBALS['vms_test_transients']['vms_admin_diagnostic_queue']), 'Runtime diagnostics queue should not be consumed on unrelated screen: ' . $fixture['label']);
}

$setScreen(
	array('page' => 'vms-dashboard'),
	(object) array('id' => 'toplevel_page_vms-dashboard', 'post_type' => '')
);
$seedFirstRunState();
$seedRuntimeState();
$seedPaymentState();

$firstRunHtml = $capture($firstRunCallback);
$assert(strpos($firstRunHtml, 'notice-success') !== false, 'First-run notice should retain its success class on VMS screens.');
$assert(strpos($firstRunHtml, 'Backstage Venue Manager is activated.') !== false, 'First-run notice should use the canonical product name on plugin screens.');
$assert(strpos($firstRunHtml, 'Open Backstage Venue Manager') !== false, 'First-run notice CTA should use the canonical product name.');
$assert(strpos($firstRunHtml, 'vms_dismiss_first_run_notice=1') !== false, 'First-run dismissal link should remain present on VMS screens.');

$runtimeHtml = $capture($runtimeCallback);
$assert(strpos($runtimeHtml, 'notice notice-warning') !== false, 'Runtime diagnostics should retain their warning class on VMS screens.');
$assert(strpos($runtimeHtml, 'Missing internal VMS file for smoke test.') !== false, 'Runtime diagnostics should still render queued messages on VMS screens.');
$assert(!isset($GLOBALS['vms_test_transients']['vms_admin_diagnostic_queue']), 'Runtime diagnostics queue should be consumed on allowed VMS screens.');
$assert(isset($GLOBALS['vms_test_options']['vms_admin_diagnostic_seen']['missing_asset']), 'Runtime diagnostics seen-state should still be recorded on allowed VMS screens.');

$paymentHtml = $capture($paymentCallback);
$assert(strpos($paymentHtml, 'notice notice-error') !== false, 'Payment gateway notice should retain its error class on VMS screens.');
$assert(strpos($paymentHtml, 'Checkout APIs are failing.') !== false, 'Payment gateway notice message should remain visible on VMS screens.');
$assert(strpos($paymentHtml, 'Open Ticket Integrity') !== false, 'Payment gateway notice CTA should remain present on VMS screens.');

$setScreen(
	array('post' => '101'),
	(object) array('id' => 'vms_event_plan', 'post_type' => 'vms_event_plan'),
	array(101 => 'vms_event_plan')
);
$seedFirstRunState();
$assert(strpos($capture($firstRunCallback), 'Backstage Venue Manager is activated.') !== false, 'First-run notice should remain eligible on Event Plan edit screens.');

$setScreen(
	array('page' => 'vms-ticket-integrity'),
	(object) array('id' => 'vms-dashboard_page_vms-ticket-integrity', 'post_type' => '')
);
$seedPaymentState();
$assert(strpos($capture($paymentCallback), 'Payment Gateway Health:') !== false, 'Payment gateway notice should remain visible on the Ticket Integrity screen.');

$setScreen(
	array('page' => 'vms-dashboard'),
	(object) array('id' => 'toplevel_page_vms-dashboard', 'post_type' => '')
);
$GLOBALS['vms_test_manage_options'] = false;
$seedFirstRunState();
$seedRuntimeState();
$seedPaymentState();
$assert($capture($firstRunCallback) === '', 'First-run notice should still require manage_options.');
$assert($capture($runtimeCallback) === '', 'Runtime diagnostics should still require manage_options.');
$assert($capture($paymentCallback) === '', 'Payment gateway notice should still require manage_options.');
$GLOBALS['vms_test_manage_options'] = true;

$setScreen(
	array('page' => 'vms-dashboard'),
	(object) array('id' => 'toplevel_page_vms-dashboard', 'post_type' => '')
);
unset($GLOBALS['vms_test_options']['vms_show_first_run_notice']);
$assert($capture($firstRunCallback) === '', 'First-run notice should still depend on its existing option flag.');

$GLOBALS['vms_test_transients']['vms_admin_diagnostic_queue'] = array();
$assert($capture($runtimeCallback) === '', 'Runtime diagnostics should still depend on a queued diagnostic.');

$GLOBALS['vms_test_options'][vms_ticket_integrity_payment_gateway_notice_option_key()] = array(
	'active' => false,
	'status' => 'ok',
	'message' => 'healthy',
);
$assert($capture($paymentCallback) === '', 'Payment gateway notice should still depend on an active critical payment-health state.');

$setScreen(array('page' => 'vms-dashboard'), null);
$seedFirstRunState();
$seedRuntimeState();
$seedPaymentState();
$assert($capture($firstRunCallback) === '', 'First-run notice should fail closed when screen context is unavailable.');
$assert($capture($runtimeCallback) === '', 'Runtime diagnostics should fail closed when screen context is unavailable.');
$assert($capture($paymentCallback) === '', 'Payment gateway notice should fail closed when screen context is unavailable.');
$assert(isset($GLOBALS['vms_test_transients']['vms_admin_diagnostic_queue']), 'Runtime diagnostics queue should remain intact when screen context is unavailable.');

fwrite(STDOUT, "Admin notice scope remediation OK.\n");
