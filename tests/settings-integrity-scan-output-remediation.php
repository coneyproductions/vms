<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('MINUTE_IN_SECONDS')) {
	define('MINUTE_IN_SECONDS', 60);
}

if (!defined('OBJECT')) {
	define('OBJECT', 'OBJECT');
}

if (!defined('BVMGR_VERSION')) {
	define('BVMGR_VERSION', 'test-version');
}

$GLOBALS['vms_test_actions'] = array();
$GLOBALS['vms_test_current_user_can'] = true;
$GLOBALS['vms_test_current_user_id'] = 7;
$GLOBALS['vms_test_options'] = array();
$GLOBALS['vms_test_transients'] = array();
$GLOBALS['vms_test_transient_get_calls'] = 0;
$GLOBALS['vms_test_transient_get_keys'] = array();
$GLOBALS['vms_test_transient_set_calls'] = 0;
$GLOBALS['vms_test_transient_set_payloads'] = array();
$GLOBALS['vms_test_transient_delete_calls'] = 0;
$GLOBALS['vms_test_transient_delete_keys'] = array();
$GLOBALS['vms_test_redirects'] = array();
$GLOBALS['vms_test_referer_actions'] = array();
$GLOBALS['vms_test_referer_fail'] = false;
$GLOBALS['vms_test_provider_reads'] = array(
	'get_option' => 0,
	'get_posts' => 0,
	'get_post_meta' => 0,
	'get_page_by_path' => 0,
	'get_permalink' => 0,
);
$GLOBALS['vms_test_scan_calls'] = array();

if (!function_exists('add_action')) {
	function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
	{
		if (!isset($GLOBALS['vms_test_actions'][$hook])) {
			$GLOBALS['vms_test_actions'][$hook] = array();
		}
		if (!isset($GLOBALS['vms_test_actions'][$hook][$priority])) {
			$GLOBALS['vms_test_actions'][$hook][$priority] = array();
		}
		$GLOBALS['vms_test_actions'][$hook][$priority][] = $callback;
		return true;
	}
}

if (!function_exists('add_filter')) {
	function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
	{
		unset($hook, $callback, $priority, $accepted_args);
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

if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($value): string
	{
		if (!is_scalar($value)) {
			return '';
		}

		$sanitized = preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $value));
		return is_string($sanitized) ? trim($sanitized) : '';
	}
}

if (!function_exists('absint')) {
	function absint($value): int
	{
		return abs((int) $value);
	}
}

if (!function_exists('wp_unslash')) {
	function wp_unslash($value)
	{
		if (is_array($value)) {
			return array_map('wp_unslash', $value);
		}

		return is_string($value) ? stripslashes($value) : $value;
	}
}

if (!function_exists('sanitize_textarea_field')) {
	function sanitize_textarea_field($value): string
	{
		return sanitize_text_field($value);
	}
}

if (!function_exists('sanitize_email')) {
	function sanitize_email($value): string
	{
		return sanitize_text_field($value);
	}
}

if (!function_exists('bvmgr_request_read_scalar')) {
	function bvmgr_request_read_scalar(array $source, string $key): string
	{
		if (!array_key_exists($key, $source) || !is_scalar($source[$key])) {
			return '';
		}

		$value = wp_unslash($source[$key]);
		return is_scalar($value) ? trim((string) $value) : '';
	}
}

if (!function_exists('bvmgr_request_read_text_field')) {
	function bvmgr_request_read_text_field(array $source, string $key): string
	{
		$value = bvmgr_request_read_scalar($source, $key);
		return $value === '' ? '' : sanitize_text_field($value);
	}
}

if (!function_exists('bvmgr_request_read_textarea_field')) {
	function bvmgr_request_read_textarea_field(array $source, string $key): string
	{
		$value = bvmgr_request_read_scalar($source, $key);
		return $value === '' ? '' : sanitize_textarea_field($value);
	}
}

if (!function_exists('bvmgr_request_read_email')) {
	function bvmgr_request_read_email(array $source, string $key): string
	{
		$value = bvmgr_request_read_scalar($source, $key);
		return $value === '' ? '' : sanitize_email($value);
	}
}

if (!function_exists('bvmgr_request_read_key')) {
	function bvmgr_request_read_key(array $source, string $key): string
	{
		$value = bvmgr_request_read_scalar($source, $key);
		return $value === '' ? '' : sanitize_key($value);
	}
}

if (!function_exists('bvmgr_request_read_absint')) {
	function bvmgr_request_read_absint(array $source, string $key): int
	{
		$value = bvmgr_request_read_scalar($source, $key);
		return $value === '' ? 0 : absint($value);
	}
}

if (!function_exists('bvmgr_request_read_bool_flag')) {
	function bvmgr_request_read_bool_flag(array $source, string $key): bool
	{
		if (!array_key_exists($key, $source)) {
			return false;
		}

		$value = $source[$key];
		if (is_array($value) || is_object($value)) {
			return false;
		}

		$value = wp_unslash($value);
		if (!is_scalar($value)) {
			return false;
		}

		$value = strtolower(trim((string) $value));
		if ($value === '') {
			return false;
		}

		return !in_array($value, array('0', 'false', 'off', 'no'), true);
	}
}

if (!function_exists('admin_url')) {
	function admin_url(string $path = ''): string
	{
		return 'https://example.test/wp-admin/' . ltrim($path, '/');
	}
}

if (!function_exists('add_query_arg')) {
	function add_query_arg($args, string $url = ''): string
	{
		$base = $url !== '' ? $url : admin_url('admin.php');
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
		if ($query !== array()) {
			$rebuilt .= '?' . http_build_query($query);
		}

		return $rebuilt;
	}
}

if (!function_exists('wp_json_encode')) {
	function wp_json_encode($value): string
	{
		$json = json_encode($value);
		return is_string($json) ? $json : '';
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

if (!function_exists('esc_attr__')) {
	function esc_attr__(string $text, string $domain = ''): string
	{
		return esc_attr(__($text, $domain));
	}
}

if (!function_exists('esc_url')) {
	function esc_url($url): string
	{
		return htmlspecialchars((string) $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('sanitize_html_class')) {
	function sanitize_html_class($class): string
	{
		$sanitized = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $class);
		return is_string($sanitized) ? trim($sanitized, '-') : '';
	}
}

if (!function_exists('current_user_can')) {
	function current_user_can(string $capability): bool
	{
		unset($capability);
		return !empty($GLOBALS['vms_test_current_user_can']);
	}
}

if (!function_exists('get_current_user_id')) {
	function get_current_user_id(): int
	{
		return (int) ($GLOBALS['vms_test_current_user_id'] ?? 0);
	}
}

if (!function_exists('wp_die')) {
	function wp_die($message = ''): void
	{
		throw new RuntimeException((string) $message);
	}
}

if (!function_exists('check_admin_referer')) {
	function check_admin_referer(string $action): bool
	{
		$GLOBALS['vms_test_referer_actions'][] = $action;
		if (!empty($GLOBALS['vms_test_referer_fail'])) {
			throw new RuntimeException('Nonce check failed.');
		}
		return true;
	}
}

if (!function_exists('get_option')) {
	function get_option(string $option, $default = false)
	{
		$GLOBALS['vms_test_provider_reads']['get_option']++;
		return array_key_exists($option, $GLOBALS['vms_test_options']) ? $GLOBALS['vms_test_options'][$option] : $default;
	}
}

if (!function_exists('get_transient')) {
	function get_transient(string $key)
	{
		$GLOBALS['vms_test_transient_get_calls']++;
		$GLOBALS['vms_test_transient_get_keys'][] = $key;
		return array_key_exists($key, $GLOBALS['vms_test_transients']) ? $GLOBALS['vms_test_transients'][$key] : false;
	}
}

if (!function_exists('set_transient')) {
	function set_transient(string $key, $value, int $expiration = 0): bool
	{
		$GLOBALS['vms_test_transient_set_calls']++;
		$GLOBALS['vms_test_transient_set_payloads'][] = array(
			'key' => $key,
			'value' => $value,
			'expiration' => $expiration,
		);
		$GLOBALS['vms_test_transients'][$key] = $value;
		return true;
	}
}

if (!function_exists('delete_transient')) {
	function delete_transient(string $key): bool
	{
		$GLOBALS['vms_test_transient_delete_calls']++;
		$GLOBALS['vms_test_transient_delete_keys'][] = $key;
		unset($GLOBALS['vms_test_transients'][$key]);
		return true;
	}
}

if (!function_exists('wp_safe_redirect')) {
	function wp_safe_redirect(string $location): bool
	{
		$GLOBALS['vms_test_redirects'][] = $location;
		return true;
	}
}

if (!function_exists('checked')) {
	function checked($checked, $current = true, bool $display = true): string
	{
		unset($display);
		return (string) $checked === (string) $current ? 'checked="checked"' : '';
	}
}

if (!function_exists('selected')) {
	function selected($selected, $current = true, bool $display = true): string
	{
		unset($display);
		return (string) $selected === (string) $current ? 'selected="selected"' : '';
	}
}

if (!function_exists('wp_nonce_field')) {
	function wp_nonce_field(string $action = '', string $name = '_wpnonce', bool $referer = true, bool $display = true): string
	{
		$field = '<input type="hidden" name="' . esc_attr($name) . '" value="nonce:' . esc_attr($action) . '" />';
		if ($referer) {
			$field .= '<input type="hidden" name="_wp_http_referer" value="/wp-admin/admin.php?page=vms-settings" />';
		}
		if ($display) {
			echo $field;
		}
		return $field;
	}
}

if (!function_exists('submit_button')) {
	function submit_button($text = null, string $type = 'primary', string $name = 'submit', bool $wrap = true, $other_attributes = null): void
	{
		unset($other_attributes);
		$text = $text === null ? 'Save Changes' : (string) $text;
		$button = '<button type="submit" class="button button-' . esc_attr($type) . '" name="' . esc_attr($name) . '">' . esc_html($text) . '</button>';
		echo $wrap ? '<p class="submit">' . $button . '</p>' : $button;
	}
}

if (!function_exists('settings_fields')) {
	function settings_fields(string $option_group): void
	{
		echo '<input type="hidden" name="option_page" value="' . esc_attr($option_group) . '" />';
	}
}

if (!function_exists('do_settings_sections')) {
	function do_settings_sections(string $page): void
	{
		unset($page);
	}
}

if (!function_exists('wpautop')) {
	function wpautop(string $text): string
	{
		return '<p>' . $text . '</p>';
	}
}

if (!function_exists('wp_kses_post')) {
	function wp_kses_post(string $text): string
	{
		return $text;
	}
}

if (!function_exists('wp_kses')) {
	function wp_kses(string $text, array $allowed_html = array()): string
	{
		unset($allowed_html);
		return $text;
	}
}

if (!function_exists('bvmgr_required_public_pages')) {
	function bvmgr_required_public_pages(): array
	{
		return array();
	}
}

if (!function_exists('get_page_by_path')) {
	function get_page_by_path(string $page_path, string $output = OBJECT, string $post_type = 'page')
	{
		unset($page_path, $output, $post_type);
		$GLOBALS['vms_test_provider_reads']['get_page_by_path']++;
		return null;
	}
}

if (!function_exists('get_permalink')) {
	function get_permalink(int $post_id = 0): string
	{
		unset($post_id);
		$GLOBALS['vms_test_provider_reads']['get_permalink']++;
		return '';
	}
}

if (!function_exists('wp_nonce_url')) {
	function wp_nonce_url(string $url, string $action): string
	{
		return add_query_arg(array('_wpnonce' => 'nonce:' . $action), $url);
	}
}

if (!function_exists('wp_timezone')) {
	function wp_timezone(): DateTimeZone
	{
		return new DateTimeZone('UTC');
	}
}

if (!function_exists('wp_date')) {
	function wp_date(string $format, int $timestamp, ?DateTimeZone $timezone = null): string
	{
		$timezone = $timezone ?: new DateTimeZone('UTC');
		$date = new DateTimeImmutable('@' . $timestamp);
		return $date->setTimezone($timezone)->format($format);
	}
}

if (!function_exists('get_posts')) {
	function get_posts(array $args = array()): array
	{
		unset($args);
		$GLOBALS['vms_test_provider_reads']['get_posts']++;
		return array();
	}
}

if (!function_exists('get_post_meta')) {
	function get_post_meta(int $post_id, string $key = '', bool $single = false)
	{
		unset($post_id, $key, $single);
		$GLOBALS['vms_test_provider_reads']['get_post_meta']++;
		return '';
	}
}

if (!function_exists('get_post_type')) {
	function get_post_type($post_id)
	{
		unset($post_id);
		return 'vms_venue';
	}
}

if (!function_exists('get_post_status')) {
	function get_post_status($post_id)
	{
		unset($post_id);
		return 'publish';
	}
}

if (!function_exists('get_the_title')) {
	function get_the_title(int $post_id): string
	{
		return 'Post ' . $post_id;
	}
}

if (!function_exists('get_edit_post_link')) {
	function get_edit_post_link(int $post_id, string $context = 'display'): string
	{
		unset($context);
		return 'https://example.test/wp-admin/post.php?post=' . $post_id . '&action=edit';
	}
}

if (!function_exists('bvmgr_integrity_scan_event_plans_for_missing_vendors')) {
	function bvmgr_integrity_scan_event_plans_for_missing_vendors(int $limit = 500): array
	{
		$GLOBALS['vms_test_scan_calls'][] = array('mode' => 'vendors', 'limit' => $limit);
		return $GLOBALS['vms_test_scan_vendor_result'] ?? array();
	}
}

if (!function_exists('bvmgr_integrity_scan_event_plans_for_orphaned_venues')) {
	function bvmgr_integrity_scan_event_plans_for_orphaned_venues(int $limit = 500): array
	{
		$GLOBALS['vms_test_scan_calls'][] = array('mode' => 'venues', 'limit' => $limit);
		return $GLOBALS['vms_test_scan_venue_result'] ?? array();
	}
}

if (!function_exists('bvmgr_integrity_scan_event_plans_for_orphaned_calendar_events')) {
	function bvmgr_integrity_scan_event_plans_for_orphaned_calendar_events(int $limit = 500): array
	{
		$GLOBALS['vms_test_scan_calls'][] = array('mode' => 'events', 'limit' => $limit);
		return $GLOBALS['vms_test_scan_event_result'] ?? array();
	}
}

if (!function_exists('bvmgr_integrity_scan_event_plans_all')) {
	function bvmgr_integrity_scan_event_plans_all(int $limit = 500): array
	{
		$GLOBALS['vms_test_scan_calls'][] = array('mode' => 'all', 'limit' => $limit);
		return $GLOBALS['vms_test_scan_all_result'] ?? array();
	}
}

require_once dirname(__DIR__) . '/includes/admin-ui/shell.php';
require_once dirname(__DIR__) . '/includes/admin/settings-page.php';

$pluginRoot = dirname(__DIR__);
$settingsSource = file_get_contents($pluginRoot . '/includes/admin/settings-page.php');
$shellSource = file_get_contents($pluginRoot . '/includes/admin-ui/shell.php');

$assert = static function ($condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$assertSame = static function ($expected, $actual, string $message) use ($assert): void {
	$assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
};

$reset_state = static function (): void {
	$GLOBALS['vms_test_current_user_can'] = true;
	$GLOBALS['vms_test_current_user_id'] = 7;
	$GLOBALS['vms_test_options'] = array();
	$GLOBALS['vms_test_transients'] = array();
	$GLOBALS['vms_test_transient_get_calls'] = 0;
	$GLOBALS['vms_test_transient_get_keys'] = array();
	$GLOBALS['vms_test_transient_set_calls'] = 0;
	$GLOBALS['vms_test_transient_set_payloads'] = array();
	$GLOBALS['vms_test_transient_delete_calls'] = 0;
	$GLOBALS['vms_test_transient_delete_keys'] = array();
	$GLOBALS['vms_test_redirects'] = array();
	$GLOBALS['vms_test_referer_actions'] = array();
	$GLOBALS['vms_test_referer_fail'] = false;
	$GLOBALS['vms_test_provider_reads'] = array(
		'get_option' => 0,
		'get_posts' => 0,
		'get_post_meta' => 0,
		'get_page_by_path' => 0,
		'get_permalink' => 0,
	);
	$GLOBALS['vms_test_scan_calls'] = array();
	$GLOBALS['vms_test_scan_vendor_result'] = array();
	$GLOBALS['vms_test_scan_venue_result'] = array();
	$GLOBALS['vms_test_scan_event_result'] = array();
	$GLOBALS['vms_test_scan_all_result'] = array();
	$_GET = array();
	$_POST = array();
	bvmgr_get_settings_page_integrity_scan_result_context(true);
};

$assert(strpos($settingsSource, "add_action('admin_post_vms_integrity_scan', 'bvmgr_handle_integrity_scan');") !== false, 'Settings source should preserve the admin-post registration.');
$assert(isset($GLOBALS['vms_test_actions']['admin_post_vms_integrity_scan'][10]), 'Settings should register the integrity scan admin-post action at priority 10.');
$assertSame('bvmgr_handle_integrity_scan', $GLOBALS['vms_test_actions']['admin_post_vms_integrity_scan'][10][0], 'Settings should preserve the integrity scan handler callback.');
$assert(strpos($settingsSource, "isset(\$_POST['mode']) ? sanitize_key((string) \$_POST['mode']) : 'all';") !== false, 'Integrity scan handler should preserve mode normalization without widening the request contract.');
$assert(strpos($settingsSource, "isset(\$_POST['limit']) ? (int) \$_POST['limit'] : 500;") !== false, 'Integrity scan handler should preserve limit normalization and default.');
$assert(strpos($settingsSource, "check_admin_referer('vms_integrity_scan');") !== false, 'Integrity scan handler should preserve the nonce action.');
$assert(strpos($settingsSource, "wp_nonce_field('vms_integrity_scan');") !== false, 'Integrity scan controls should preserve the nonce field action.');
$assert(strpos($settingsSource, "name=\"action\" value=\"vms_integrity_scan\"") !== false, 'Integrity scan controls should preserve the admin-post action field.');
$assert(strpos($settingsSource, 'function bvmgr_build_settings_page_integrity_scan_result_context(') !== false, 'Settings should expose a dedicated integrity-scan context builder.');
$assert(strpos($settingsSource, 'function bvmgr_render_settings_page_integrity_scan_result(') !== false, 'Settings should expose a dedicated integrity-scan renderer.');
$assert(strpos($settingsSource, 'function vms_render_settings_page_result(') === false, 'Settings should not introduce a generic result renderer.');
$assert(strpos($settingsSource, "bvmgr_render_settings_page_integrity_scan_result(bvmgr_get_settings_page_integrity_scan_result_context());") !== false, 'Settings content should route integrity results through the dedicated page-local renderer.');
$assert(strpos($settingsSource, '<div class="vms-settings-integrity-scan-result">') !== false, 'Settings should wrap the integrity result in a page-local family container.');
$assert(strpos($settingsSource, "echo '<div class=\"notice notice-success\"><p><strong>Integrity scan complete.</strong> Mode: '") === false, 'Settings content should no longer inline the old top-level captured notice block.');
$assert(strpos($settingsSource, '<strong>Entitlement image sync complete.</strong>') !== false, 'Settings should preserve the separate entitlement image-sync family.');
$assert(strpos($settingsSource, "echo '<div class=\"notice notice-success\"><p>' . esc_html__('Default venue updated.', 'backstage-venue-manager') . '</p></div>';") !== false, 'Settings should preserve the separate default-venue explicit notice family.');
$assertSame(
	array(
		'div' => array('class' => true),
		'p' => array(),
	),
	bvmgr_admin_ui_explicit_notice_allowed_html(),
	'Administrator shell simple notice contract should remain unchanged.'
);
$assertSame(
	array(
		'div' => array('class' => true),
		'p' => array(),
		'strong' => array(),
	),
	bvmgr_admin_ui_rich_explicit_notice_allowed_html(),
	'Administrator shell rich notice contract should remain unchanged.'
);

$reset_state();
$GLOBALS['vms_test_current_user_can'] = false;
try {
	bvmgr_handle_integrity_scan();
	throw new RuntimeException('Expected permission failure was not thrown.');
} catch (RuntimeException $e) {
	$assertSame('Insufficient permissions.', $e->getMessage(), 'Integrity scan handler should preserve the capability failure message.');
}
$assertSame(array(), $GLOBALS['vms_test_referer_actions'], 'Integrity scan handler should fail before nonce checks when capability is missing.');
$assertSame(array(), $GLOBALS['vms_test_scan_calls'], 'Integrity scan handler should not start a scan when capability fails.');
$assertSame(array(), $GLOBALS['vms_test_transient_set_payloads'], 'Integrity scan handler should not write transients when capability fails.');
$assertSame(array(), $GLOBALS['vms_test_redirects'], 'Integrity scan handler should not redirect when capability fails.');

$reset_state();
$GLOBALS['vms_test_referer_fail'] = true;
try {
	bvmgr_handle_integrity_scan();
	throw new RuntimeException('Expected nonce failure was not thrown.');
} catch (RuntimeException $e) {
	$assertSame('Nonce check failed.', $e->getMessage(), 'Integrity scan handler should preserve nonce failure ordering.');
}
$assertSame(array('vms_integrity_scan'), $GLOBALS['vms_test_referer_actions'], 'Integrity scan handler should preserve the nonce action.');
$assertSame(array(), $GLOBALS['vms_test_scan_calls'], 'Integrity scan handler should not start a scan when nonce verification fails.');
$assertSame(array(), $GLOBALS['vms_test_transient_set_payloads'], 'Integrity scan handler should not write transients when nonce verification fails.');
$assertSame(array(), $GLOBALS['vms_test_redirects'], 'Integrity scan handler should not redirect when nonce verification fails.');

$handler_cases = array(
	array(
		'post' => array('mode' => 'vendors', 'limit' => '-25'),
		'expected_mode' => 'vendors',
		'expected_limit' => 500,
		'result' => array('checked' => 4, 'flagged_missing_vendor' => 1, 'flagged_trashed_vendor' => 0, 'flagged_missing_secondary_vendor' => 2, 'flagged_trashed_secondary_vendor' => 0, 'removed_missing_secondary_vendor_ids' => 2, 'forced_draft' => 1),
	),
	array(
		'post' => array('mode' => 'venues', 'limit' => '7001'),
		'expected_mode' => 'venues',
		'expected_limit' => 5000,
		'result' => array('checked' => 2, 'flagged_missing_venue' => 0, 'flagged_trashed_venue' => 1, 'flagged_venue_unpublished' => 1, 'cleared_venue_refs' => 0, 'forced_draft' => 1),
	),
	array(
		'post' => array('mode' => 'events', 'limit' => '250'),
		'expected_mode' => 'events',
		'expected_limit' => 250,
		'result' => array('checked' => 3, 'flagged_calendar_event_unlinked' => 1, 'flagged_missing_calendar_event' => 0, 'flagged_trashed_calendar_event' => 1, 'flagged_calendar_event_unpublished' => 1, 'cleared_calendar_event_refs' => 0, 'forced_draft' => 0),
	),
	array(
		'post' => array('mode' => 'ALL<script>', 'limit' => '18'),
		'expected_mode' => 'all',
		'expected_limit' => 18,
		'result' => array(
			'vendors' => array('checked' => 1, 'flagged_missing_vendor' => 0, 'flagged_trashed_vendor' => 0, 'flagged_missing_secondary_vendor' => 0, 'flagged_trashed_secondary_vendor' => 0, 'removed_missing_secondary_vendor_ids' => 0, 'forced_draft' => 0),
			'venues' => array('checked' => 1, 'flagged_missing_venue' => 0, 'flagged_trashed_venue' => 1, 'flagged_venue_unpublished' => 0, 'cleared_venue_refs' => 0, 'forced_draft' => 0),
			'events' => array('checked' => 1, 'flagged_calendar_event_unlinked' => 0, 'flagged_missing_calendar_event' => 1, 'flagged_trashed_calendar_event' => 0, 'flagged_calendar_event_unpublished' => 0, 'cleared_calendar_event_refs' => 0, 'forced_draft' => 0),
		),
	),
);

foreach ($handler_cases as $case) {
	$reset_state();
	$_POST = $case['post'];
	$GLOBALS['vms_test_scan_vendor_result'] = $case['expected_mode'] === 'vendors' ? $case['result'] : array();
	$GLOBALS['vms_test_scan_venue_result'] = $case['expected_mode'] === 'venues' ? $case['result'] : array();
	$GLOBALS['vms_test_scan_event_result'] = $case['expected_mode'] === 'events' ? $case['result'] : array();
	$GLOBALS['vms_test_scan_all_result'] = $case['expected_mode'] === 'all' ? $case['result'] : array();

	bvmgr_handle_integrity_scan();

	$assertSame(array('vms_integrity_scan'), $GLOBALS['vms_test_referer_actions'], 'Integrity scan handler should verify the same nonce action for every mode.');
	$assertSame(array(array('mode' => $case['expected_mode'], 'limit' => $case['expected_limit'])), $GLOBALS['vms_test_scan_calls'], 'Integrity scan handler should preserve mode dispatch and limit clamping.');
	$assertSame(1, $GLOBALS['vms_test_transient_set_calls'], 'Integrity scan handler should write exactly one transient result.');
	$transient_write = $GLOBALS['vms_test_transient_set_payloads'][0];
	$assertSame('vms_integrity_scan_last', $transient_write['key'], 'Integrity scan handler should preserve the transient key.');
	$assertSame(10 * MINUTE_IN_SECONDS, $transient_write['expiration'], 'Integrity scan handler should preserve the transient lifetime.');
	$assertSame($case['expected_mode'], $transient_write['value']['mode'], 'Integrity scan handler should preserve the stored mode value.');
	$assertSame($case['expected_limit'], $transient_write['value']['limit'], 'Integrity scan handler should preserve the stored limit value.');
	$assertSame($case['result'], $transient_write['value']['results'], 'Integrity scan handler should preserve the stored result payload.');
	$assertSame(array('https://example.test/wp-admin/admin.php?page=vms-settings&vms_scan_done=1'), $GLOBALS['vms_test_redirects'], 'Integrity scan handler should preserve the redirect destination and marker.');
}

$composite_context = bvmgr_build_settings_page_integrity_scan_result_context(
	array(
		'ts' => 1735689600,
		'mode' => 'all',
		'limit' => 6001,
		'results' => array(
			'vendors' => array(
				'checked' => '4<script>',
				'flagged_missing_vendor' => '1<script>',
				'flagged_trashed_vendor' => 2,
				'flagged_missing_secondary_vendor' => 3,
				'flagged_trashed_secondary_vendor' => 4,
				'forced_draft' => 5,
			),
			'venues' => array(
				'checked' => 6,
				'flagged_missing_venue' => 7,
				'flagged_trashed_venue' => 8,
				'flagged_venue_unpublished' => 9,
				'cleared_venue_refs' => 10,
				'forced_draft' => 11,
			),
			'events' => array(
				'checked' => 12,
				'flagged_calendar_event_unlinked' => 13,
				'flagged_missing_calendar_event' => 14,
				'flagged_trashed_calendar_event' => 15,
				'flagged_calendar_event_unpublished' => 16,
				'cleared_calendar_event_refs' => 17,
				'forced_draft' => 18,
			),
		),
	),
	true
);
$assertSame(true, $composite_context['show'], 'Integrity scan context builder should expose composite results.');
$assertSame('composite', $composite_context['status'], 'Integrity scan context builder should classify grouped results.');
$assertSame('composite', $composite_context['layout'], 'Integrity scan context builder should select the composite renderer branch.');
$assertSame(5000, $composite_context['limit'], 'Integrity scan context builder should clamp the stored limit to the existing maximum.');
$assertSame('all', $composite_context['mode'], 'Integrity scan context builder should preserve the normalized mode.');
$assertSame('2025-01-01 00:00', $composite_context['timestamp'], 'Integrity scan context builder should preserve the existing timestamp format.');
$assertSame(true, $composite_context['sections'][1]['action']['visible'], 'Integrity scan context builder should preserve the venue reconcile action visibility.');
$assertSame('Review trashed venue links', $composite_context['sections'][1]['action']['label'], 'Integrity scan context builder should preserve the venue reconcile action label.');
$assertSame('https://example.test/wp-admin/admin.php?page=vms-integrity-venue-links', $composite_context['sections'][1]['action']['href'], 'Integrity scan context builder should preserve the venue reconcile action URL.');
$assertSame('button button-secondary', $composite_context['sections'][1]['action']['class'], 'Integrity scan context builder should preserve the venue reconcile action class.');
$assertSame('', $composite_context['sections'][1]['action']['target'], 'Integrity scan context builder should preserve the absence of a venue reconcile target attribute.');
$assertSame('', $composite_context['sections'][1]['action']['rel'], 'Integrity scan context builder should preserve the absence of a venue reconcile rel attribute.');
$assertSame(true, $composite_context['sections'][2]['action']['visible'], 'Integrity scan context builder should preserve the calendar reconcile action visibility.');

$single_context = bvmgr_build_settings_page_integrity_scan_result_context(
	array(
		'ts' => 1735689600,
		'mode' => 'events',
		'limit' => -50,
		'results' => array(
			'checked' => 4,
			'flagged_calendar_event_unlinked' => '7<script>',
			'flagged_missing_calendar_event' => 8,
			'flagged_trashed_calendar_event' => 9,
			'flagged_calendar_event_unpublished' => 10,
			'cleared_calendar_event_refs' => 11,
			'forced_draft' => 12,
		),
	),
	true
);
$assertSame('single', $single_context['status'], 'Integrity scan context builder should preserve the single-mode branch.');
$assertSame(500, $single_context['limit'], 'Integrity scan context builder should preserve the existing minimum/default limit behavior.');
$assertSame('{"checked":4,"flagged_calendar_event_unlinked":7,"flagged_missing_calendar_event":8,"flagged_trashed_calendar_event":9,"flagged_calendar_event_unpublished":10,"cleared_calendar_event_refs":11,"forced_draft":12}', $single_context['single_result_json'], 'Integrity scan context builder should normalize single-mode diagnostic values into a finite JSON string.');

$missing_context = bvmgr_build_settings_page_integrity_scan_result_context(false, true);
$assertSame(false, $missing_context['show'], 'Integrity scan context builder should stay silent when the transient is missing.');
$assertSame('missing', $missing_context['status'], 'Integrity scan context builder should classify missing stored results.');

$invalid_context = bvmgr_build_settings_page_integrity_scan_result_context(
	array(
		'ts' => 1735689600,
		'mode' => 'all',
		'limit' => 5,
		'results' => array(
			'checked' => 1,
		),
	),
	true
);
$assertSame(false, $invalid_context['show'], 'Integrity scan context builder should stay silent for invalid single-mode result shapes.');
$assertSame('invalid', $invalid_context['status'], 'Integrity scan context builder should classify invalid stored results.');

$reset_state();
$renderer_context = array(
	'show' => true,
	'status' => 'composite',
	'layout' => 'composite',
	'notice_class' => 'notice notice-success',
	'summary_title' => 'Integrity scan complete.',
	'mode_label' => 'all',
	'limit' => 500,
	'timestamp' => '2025-01-01 00:00',
	'sections' => $composite_context['sections'],
);
ob_start();
bvmgr_render_settings_page_integrity_scan_result($renderer_context);
$rendered_composite = (string) ob_get_clean();
$assertSame(
	'<div class="vms-settings-integrity-scan-result"><div class="notice notice-success"><p><strong>Integrity scan complete.</strong> Mode: all &nbsp;|&nbsp; Limit: 500 &nbsp;|&nbsp; 2025-01-01 00:00</p><p><strong>Event Plans (Vendor links):</strong> Checked 4, Missing 1, Trashed 2, Secondary missing 3, Secondary trashed 4, Forced draft 5</p><p><strong>Event Plans (Venue links):</strong> Checked 6, Missing 7, Trashed 8, Unpublished 9, Cleared refs 10, Forced draft 11 &nbsp;|&nbsp; <a class="button button-secondary" href="https://example.test/wp-admin/admin.php?page=vms-integrity-venue-links">Review trashed venue links</a></p><p><strong>Event Plans (Calendar):</strong> Checked 12, Unlinked 13, Missing 14, Trashed 15, Unpublished 16, Cleared refs 17, Forced draft 18 &nbsp;|&nbsp; <a class="button button-secondary" href="https://example.test/wp-admin/admin.php?page=vms-integrity-calendar-links">Review calendar links</a></p></div></div>',
	$rendered_composite,
	'Integrity scan renderer should preserve the exact finite composite markup contract.'
);
$assert(strpos($rendered_composite, '<script') === false && strpos($rendered_composite, '<style') === false && strpos($rendered_composite, 'onclick=') === false, 'Integrity scan renderer should not emit executable markup.');
$assertSame(
	array(
		'get_option' => 0,
		'get_posts' => 0,
		'get_post_meta' => 0,
		'get_page_by_path' => 0,
		'get_permalink' => 0,
	),
	$GLOBALS['vms_test_provider_reads'],
	'Integrity scan renderer should not perform provider or database reads.'
);
$assertSame(0, $GLOBALS['vms_test_transient_get_calls'], 'Integrity scan renderer should not perform transient reads.');
$assertSame(array(), $GLOBALS['vms_test_scan_calls'], 'Integrity scan renderer should not perform scan calls.');

ob_start();
bvmgr_render_settings_page_integrity_scan_result($single_context);
$rendered_single = (string) ob_get_clean();
$assertSame(
	'<div class="vms-settings-integrity-scan-result"><div class="notice notice-success"><p><strong>Integrity scan complete.</strong> Mode: events &nbsp;|&nbsp; Limit: 500 &nbsp;|&nbsp; 2025-01-01 00:00</p><p><strong>Results:</strong> {&quot;checked&quot;:4,&quot;flagged_calendar_event_unlinked&quot;:7,&quot;flagged_missing_calendar_event&quot;:8,&quot;flagged_trashed_calendar_event&quot;:9,&quot;flagged_calendar_event_unpublished&quot;:10,&quot;cleared_calendar_event_refs&quot;:11,&quot;forced_draft&quot;:12}</p></div></div>',
	$rendered_single,
	'Integrity scan renderer should preserve the exact finite single-mode markup contract.'
);

ob_start();
bvmgr_render_settings_page_integrity_scan_result($missing_context);
$rendered_missing = (string) ob_get_clean();
$assertSame('', $rendered_missing, 'Integrity scan renderer should stay silent when the normalized context is not displayable.');

$reset_state();
$_GET = array(
	'vms_scan_done' => '1',
);
$GLOBALS['vms_test_transients']['vms_integrity_scan_last'] = array(
	'ts' => 1735689600,
	'mode' => 'all',
	'limit' => 500,
	'results' => array(
		'vendors' => array('checked' => 1, 'flagged_missing_vendor' => 0, 'flagged_trashed_vendor' => 0, 'flagged_missing_secondary_vendor' => 0, 'flagged_trashed_secondary_vendor' => 0, 'forced_draft' => 0),
		'venues' => array('checked' => 1, 'flagged_missing_venue' => 0, 'flagged_trashed_venue' => 1, 'flagged_venue_unpublished' => 0, 'cleared_venue_refs' => 0, 'forced_draft' => 0),
		'events' => array('checked' => 1, 'flagged_calendar_event_unlinked' => 1, 'flagged_missing_calendar_event' => 0, 'flagged_trashed_calendar_event' => 0, 'flagged_calendar_event_unpublished' => 0, 'cleared_calendar_event_refs' => 0, 'forced_draft' => 0),
	),
);
bvmgr_get_settings_page_integrity_scan_result_context(true);
ob_start();
bvmgr_render_settings_page_content();
$content_html = (string) ob_get_clean();
$captured_notices_html = '';
$remaining_content_html = bvmgr_admin_ui_extract_notice_markup($content_html, $captured_notices_html);
$assertSame(1, $GLOBALS['vms_test_transient_get_calls'], 'Settings content render should read the integrity-scan transient exactly once.');
$assertSame(array('vms_integrity_scan_last'), $GLOBALS['vms_test_transient_get_keys'], 'Settings content render should read only the integrity-scan transient for this family.');
$assert(strpos($captured_notices_html, 'Integrity scan complete.') === false, 'Integrity scan output should no longer be captured into the shell notice buffer.');
$assert(strpos($remaining_content_html, 'vms-settings-integrity-scan-result') !== false, 'Integrity scan output should remain in page-local content.');
$assert(strpos($remaining_content_html, 'Review trashed venue links') !== false, 'Integrity scan content should preserve the venue action link.');
$assert(strpos($remaining_content_html, 'Review calendar links') !== false, 'Integrity scan content should preserve the calendar action link.');
$assert(strpos($remaining_content_html, 'vms-settings-integrity-scan-result') < strpos($remaining_content_html, '<h2>Data Integrity</h2>'), 'Integrity scan output should remain ordered immediately before the integrity-scan controls.');

$reset_state();
$_GET = array(
	'vms_scan_done' => '1',
);
$GLOBALS['vms_test_transients']['vms_integrity_scan_last'] = array(
	'ts' => 1735689600,
	'mode' => 'all',
	'limit' => 500,
	'results' => array(
		'vendors' => array('checked' => 1, 'flagged_missing_vendor' => 0, 'flagged_trashed_vendor' => 0, 'flagged_missing_secondary_vendor' => 0, 'flagged_trashed_secondary_vendor' => 0, 'forced_draft' => 0),
		'venues' => array('checked' => 1, 'flagged_missing_venue' => 0, 'flagged_trashed_venue' => 1, 'flagged_venue_unpublished' => 0, 'cleared_venue_refs' => 0, 'forced_draft' => 0),
		'events' => array('checked' => 1, 'flagged_calendar_event_unlinked' => 1, 'flagged_missing_calendar_event' => 0, 'flagged_trashed_calendar_event' => 0, 'flagged_calendar_event_unpublished' => 0, 'cleared_calendar_event_refs' => 0, 'forced_draft' => 0),
	),
);
bvmgr_get_settings_page_integrity_scan_result_context(true);
ob_start();
bvmgr_render_settings_page();
$shell_page = (string) ob_get_clean();
$assert(strpos($shell_page, 'below-h2 vms-shell-notice"><p><strong>Integrity scan complete.') === false, 'Integrity scan output should no longer depend on the shell captured-notice region.');
$assert(strpos($shell_page, '<div class="vms-settings-integrity-scan-result"><div class="notice notice-success">') !== false, 'Integrity scan output should render through the page-local Settings content path.');
$assert(strpos($shell_page, 'vms-settings-integrity-scan-result') < strpos($shell_page, '<h2>Data Integrity</h2>'), 'Integrity scan shell output should preserve the result before the integrity controls.');
$assert(strpos($shell_page, 'Review trashed venue links') !== false && strpos($shell_page, 'Review calendar links') !== false, 'Integrity scan shell output should preserve both action links.');

$reset_state();
$_GET = array(
	'vms_entitlement_image_sync_done' => '1',
);
$GLOBALS['vms_test_transients']['vms_entitlement_image_sync_last'] = array(
	'ts' => 1735689600,
	'checked' => 3,
	'updated' => 1,
	'skipped' => 1,
	'errors' => 1,
	'results' => array(
		array(
			'status' => 'error_missing_entitlement',
			'product_id' => 11,
			'entitlement_id' => 'vip',
			'message' => 'missing_entitlement',
		),
	),
);
ob_start();
bvmgr_render_settings_page_content();
$img_sync_content = (string) ob_get_clean();
$img_sync_captured = '';
$img_sync_remaining = bvmgr_admin_ui_extract_notice_markup($img_sync_content, $img_sync_captured);
$assert(strpos($img_sync_captured, 'Entitlement image sync complete.') !== false, 'Entitlement image-sync summary notice should remain unchanged and captured separately.');
$assert(strpos($img_sync_remaining, 'Entitlement Image Sync Errors') !== false, 'Entitlement image-sync detail card should remain unchanged.');

$_GET = array(
	'vms_notice' => 'default_venue_set',
);
ob_start();
bvmgr_render_settings_page_notices();
$default_venue_notice = (string) ob_get_clean();
$assertSame('<div class="notice notice-success"><p>Default venue updated.</p></div>', $default_venue_notice, 'Default-venue notice should remain unchanged.');

fwrite(STDOUT, "settings integrity scan output remediation: PASS\n");
