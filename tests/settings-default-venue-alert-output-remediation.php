<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('VMS_VERSION')) {
	define('VMS_VERSION', 'test-version');
}

$GLOBALS['vms_test_actions'] = array();
$GLOBALS['vms_test_options'] = array();
$GLOBALS['vms_test_posts'] = array();
$GLOBALS['vms_test_get_posts_args'] = array();
$GLOBALS['vms_test_default_venue_all_query_result'] = array();
$GLOBALS['vms_test_default_venue_published_query_result'] = array();
$GLOBALS['vms_test_provider_reads'] = array(
	'get_option' => 0,
	'get_posts' => 0,
	'get_post_type' => 0,
	'get_post_status' => 0,
	'get_the_title' => 0,
	'get_edit_post_link' => 0,
);
$GLOBALS['vms_test_registered_settings'] = array();
$GLOBALS['vms_test_registered_sections'] = array();
$GLOBALS['vms_test_registered_fields'] = array();

if (!function_exists('add_action')) {
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
}

if (!function_exists('add_filter')) {
	function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
	{
		unset($hook, $callback, $priority, $accepted_args);
		return true;
	}
}

if (!function_exists('register_setting')) {
	function register_setting(string $option_group, string $option_name, array $args = array()): bool
	{
		$GLOBALS['vms_test_registered_settings'][] = array(
			'option_group' => $option_group,
			'option_name' => $option_name,
			'args' => $args,
		);
		return true;
	}
}

if (!function_exists('add_settings_section')) {
	function add_settings_section(string $id, string $title, $callback, string $page): bool
	{
		$GLOBALS['vms_test_registered_sections'][] = array(
			'id' => $id,
			'title' => $title,
			'callback' => $callback,
			'page' => $page,
		);
		return true;
	}
}

if (!function_exists('add_settings_field')) {
	function add_settings_field(string $id, string $title, $callback, string $page, string $section = 'default', array $args = array()): bool
	{
		$GLOBALS['vms_test_registered_fields'][] = array(
			'id' => $id,
			'title' => $title,
			'callback' => $callback,
			'page' => $page,
			'section' => $section,
			'args' => $args,
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

if (!function_exists('esc_html__')) {
	function esc_html__(string $text, string $domain = ''): string
	{
		return esc_html(__($text, $domain));
	}
}

if (!function_exists('esc_html')) {
	function esc_html($text): string
	{
		return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

if (!function_exists('wp_unslash')) {
	function wp_unslash($value)
	{
		if (is_array($value)) {
			return array_map('wp_unslash', $value);
		}

		return is_string($value) ? stripslashes($value) : $value;
	}
}

if (!function_exists('absint')) {
	function absint($value): int
	{
		return abs((int) $value);
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

if (!function_exists('vms_request_read_scalar')) {
	function vms_request_read_scalar(array $source, string $key): string
	{
		if (!array_key_exists($key, $source) || !is_scalar($source[$key])) {
			return '';
		}

		$value = wp_unslash($source[$key]);
		return is_scalar($value) ? trim((string) $value) : '';
	}
}

if (!function_exists('vms_request_read_text_field')) {
	function vms_request_read_text_field(array $source, string $key): string
	{
		$value = vms_request_read_scalar($source, $key);
		return $value === '' ? '' : sanitize_text_field($value);
	}
}

if (!function_exists('vms_request_read_textarea_field')) {
	function vms_request_read_textarea_field(array $source, string $key): string
	{
		$value = vms_request_read_scalar($source, $key);
		return $value === '' ? '' : sanitize_textarea_field($value);
	}
}

if (!function_exists('vms_request_read_email')) {
	function vms_request_read_email(array $source, string $key): string
	{
		$value = vms_request_read_scalar($source, $key);
		return $value === '' ? '' : sanitize_email($value);
	}
}

if (!function_exists('vms_request_read_key')) {
	function vms_request_read_key(array $source, string $key): string
	{
		$value = vms_request_read_scalar($source, $key);
		return $value === '' ? '' : sanitize_key($value);
	}
}

if (!function_exists('vms_request_read_absint')) {
	function vms_request_read_absint(array $source, string $key): int
	{
		$value = vms_request_read_scalar($source, $key);
		return $value === '' ? 0 : absint($value);
	}
}

if (!function_exists('vms_request_read_bool_flag')) {
	function vms_request_read_bool_flag(array $source, string $key): bool
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

if (!function_exists('selected')) {
	function selected($selected, $current = true, bool $display = true): string
	{
		unset($display);
		return (string) $selected === (string) $current ? 'selected="selected"' : '';
	}
}

if (!function_exists('checked')) {
	function checked($checked, $current = true, bool $display = true): string
	{
		unset($display);
		return (string) $checked === (string) $current ? 'checked="checked"' : '';
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

if (!function_exists('wp_nonce_url')) {
	function wp_nonce_url(string $url, string $action): string
	{
		return add_query_arg(array('_wpnonce' => 'nonce:' . $action), $url);
	}
}

if (!function_exists('wp_kses')) {
	function wp_kses(string $text, array $allowed_html = array()): string
	{
		unset($allowed_html);
		return $text;
	}
}

if (!function_exists('wp_kses_post')) {
	function wp_kses_post(string $text): string
	{
		return $text;
	}
}

if (!function_exists('get_option')) {
	function get_option(string $option, $default = false)
	{
		$GLOBALS['vms_test_provider_reads']['get_option']++;
		return array_key_exists($option, $GLOBALS['vms_test_options']) ? $GLOBALS['vms_test_options'][$option] : $default;
	}
}

if (!function_exists('get_posts')) {
	function get_posts(array $args = array()): array
	{
		$GLOBALS['vms_test_provider_reads']['get_posts']++;
		$GLOBALS['vms_test_get_posts_args'][] = $args;

		if (($args['post_type'] ?? '') === 'vms_venue' && ($args['fields'] ?? '') === 'ids') {
			if (is_array($args['post_status'] ?? null)) {
				return $GLOBALS['vms_test_default_venue_all_query_result'];
			}

			if (($args['post_status'] ?? '') === 'publish') {
				return $GLOBALS['vms_test_default_venue_published_query_result'];
			}
		}

		return array();
	}
}

if (!function_exists('get_post_type')) {
	function get_post_type($post_id)
	{
		$GLOBALS['vms_test_provider_reads']['get_post_type']++;
		$post_id = (int) $post_id;
		return $GLOBALS['vms_test_posts'][$post_id]['post_type'] ?? false;
	}
}

if (!function_exists('get_post_status')) {
	function get_post_status($post_id)
	{
		$GLOBALS['vms_test_provider_reads']['get_post_status']++;
		$post_id = (int) $post_id;
		return $GLOBALS['vms_test_posts'][$post_id]['post_status'] ?? false;
	}
}

if (!function_exists('get_the_title')) {
	function get_the_title(int $post_id): string
	{
		$GLOBALS['vms_test_provider_reads']['get_the_title']++;
		return (string) ($GLOBALS['vms_test_posts'][$post_id]['post_title'] ?? '');
	}
}

if (!function_exists('get_edit_post_link')) {
	function get_edit_post_link(int $post_id, string $context = 'display'): string
	{
		unset($context);
		$GLOBALS['vms_test_provider_reads']['get_edit_post_link']++;
		return (string) ($GLOBALS['vms_test_posts'][$post_id]['edit_url'] ?? '');
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
	$GLOBALS['vms_test_options'] = array();
	$GLOBALS['vms_test_posts'] = array();
	$GLOBALS['vms_test_get_posts_args'] = array();
	$GLOBALS['vms_test_default_venue_all_query_result'] = array();
	$GLOBALS['vms_test_default_venue_published_query_result'] = array();
	$GLOBALS['vms_test_provider_reads'] = array(
		'get_option' => 0,
		'get_posts' => 0,
		'get_post_type' => 0,
		'get_post_status' => 0,
		'get_the_title' => 0,
		'get_edit_post_link' => 0,
	);
};

$run_admin_init_hooks = static function (): void {
	$hooks = $GLOBALS['vms_test_actions']['admin_init'][10] ?? array();
	foreach ($hooks as $callback) {
		if ($callback instanceof Closure) {
			$callback();
		}
	}
};

$assert(is_string($settingsSource) && $settingsSource !== '', 'Settings source should be readable.');
$assert(is_string($shellSource) && $shellSource !== '', 'Shell source should be readable.');
$assert(strpos($settingsSource, "add_action('admin_post_vms_set_default_venue', 'vms_handle_set_default_venue');") !== false, 'Default-venue admin-post registration should remain unchanged.');
$assert(strpos($settingsSource, "register_setting('vms_settings_group', 'vms_settings', array(") !== false, 'Settings registration should preserve the vms_settings option registration.');
$assert(strpos($settingsSource, "'sanitize_callback' => 'vms_sanitize_settings'") !== false, 'Settings registration should preserve the sanitize callback.');
$assert(strpos($settingsSource, "'default' => array()") !== false, 'Settings registration should preserve the default array.');
$assert(strpos($settingsSource, "'vms_default_venue_id'") !== false, 'The Default Venue field registration should remain present.');
$assert(strpos($settingsSource, "'vms_field_default_venue'") !== false, 'The Default Venue field callback should remain unchanged.');
$assert(strpos($settingsSource, "name=\"vms_settings[default_venue_id]\" class=\"vms-minw-320\"") !== false, 'The Default Venue select control should remain unchanged.');
$assert(strpos($settingsSource, '$out[\'default_venue_id\'] = isset($input[\'default_venue_id\']) ? absint($input[\'default_venue_id\']) : 0;') !== false, 'Default Venue option sanitization should remain unchanged.');
$assert(strpos($settingsSource, "isset(\$_GET['venue_id']) ? absint(wp_unslash(\$_GET['venue_id'])) : 0;") !== false, 'Default Venue action handler should preserve venue_id normalization.');
$assert(strpos($settingsSource, "wp_verify_nonce(\$nonce, 'vms_set_default_venue_' . \$venue_id)") !== false, 'Default Venue action handler should preserve the nonce action.');
$assert(strpos($settingsSource, "Venue must be published before it can be set as the Default Venue.") !== false, 'Default Venue action handler should preserve the published-venue guard.');
$assert(strpos($settingsSource, "vms_render_settings_default_venue_alert(") !== false, 'Default Venue field should route through the dedicated alert renderer.');
$assert(strpos($settingsSource, "vms_build_settings_default_venue_alert_context(") !== false, 'Default Venue field should route through the dedicated alert context builder.');
$assert(strpos($settingsSource, 'function vms_render_settings_alert(') === false, 'No generic Settings alert renderer should be introduced.');
$assert(strpos($settingsSource, 'vms_render_settings_page_integrity_scan_result(vms_get_settings_page_integrity_scan_result_context());') !== false, 'Settings integrity-scan output should remain unchanged.');
$assert(strpos($settingsSource, '<strong>Entitlement image sync complete.</strong>') !== false, 'Entitlement image-sync output should remain unchanged.');
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

$run_admin_init_hooks();
$settingsRegistrationFound = false;
foreach ($GLOBALS['vms_test_registered_settings'] as $registration) {
	if (($registration['option_group'] ?? '') === 'vms_settings_group' && ($registration['option_name'] ?? '') === 'vms_settings') {
		$settingsRegistrationFound = true;
		$assertSame('array', $registration['args']['type'] ?? '', 'Settings registration should preserve the array type.');
		$assertSame('vms_sanitize_settings', $registration['args']['sanitize_callback'] ?? '', 'Settings registration should preserve the sanitize callback.');
		$assertSame(array(), $registration['args']['default'] ?? null, 'Settings registration should preserve the default array.');
	}
}
$assert($settingsRegistrationFound, 'The vms_settings registration should be discoverable through the admin_init hooks.');

$defaultVenueFieldFound = false;
foreach ($GLOBALS['vms_test_registered_fields'] as $field) {
	if (($field['id'] ?? '') === 'vms_default_venue_id') {
		$defaultVenueFieldFound = true;
		$assertSame('Default Venue', $field['title'] ?? '', 'Default Venue field title should remain unchanged.');
		$assertSame('vms_field_default_venue', $field['callback'] ?? '', 'Default Venue field callback should remain unchanged.');
		$assertSame('vms-settings', $field['page'] ?? '', 'Default Venue field page should remain unchanged.');
		$assertSame('vms_settings_venues', $field['section'] ?? '', 'Default Venue field section should remain unchanged.');
	}
}
$assert($defaultVenueFieldFound, 'The Default Venue field should be registered through the admin_init hooks.');

$reset_state();
$GLOBALS['vms_test_options']['vms_settings'] = array('default_venue_id' => 22);
$GLOBALS['vms_test_default_venue_all_query_result'] = array(11, 22);
$GLOBALS['vms_test_default_venue_published_query_result'] = array(22);
$GLOBALS['vms_test_posts'] = array(
	11 => array(
		'post_type' => 'vms_venue',
		'post_status' => 'draft',
		'post_title' => 'Draft Venue',
		'edit_url' => 'https://example.test/wp-admin/post.php?post=11&action=edit',
	),
	22 => array(
		'post_type' => 'vms_venue',
		'post_status' => 'publish',
		'post_title' => 'Published Venue',
		'edit_url' => 'https://example.test/wp-admin/post.php?post=22&action=edit',
	),
);
ob_start();
vms_field_default_venue();
$valid_field_html = (string) ob_get_clean();
$assertSame(
	'<select name="vms_settings[default_venue_id]" class="vms-minw-320"><option value="0">— None —</option><option value="11" >Draft Venue</option><option value="22" selected="selected">Published Venue</option></select><p class="description">Used when no venue is selected in context.</p>',
	$valid_field_html,
	'Default Venue field should preserve the existing select control, ordering, and description when no alert is needed.'
);
$assertSame(
	array(
		array(
			'post_type' => 'vms_venue',
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'ASC',
			'fields' => 'ids',
			'post_status' => array('publish', 'draft', 'private', 'pending', 'future'),
		),
		array(
			'post_type' => 'vms_venue',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'post_status' => 'publish',
		),
	),
	$GLOBALS['vms_test_get_posts_args'],
	'Default Venue field should preserve the candidate and published-venue query shapes.'
);

$reset_state();
$GLOBALS['vms_test_posts'] = array(
	31 => array(
		'post_type' => 'vms_venue',
		'post_status' => 'draft',
		'post_title' => 'Solo Draft Venue',
		'edit_url' => '',
	),
);
$single_unpublished_context = vms_build_settings_default_venue_alert_context(0, array(31), array(), false);
$assertSame(true, $single_unpublished_context['show'], 'Single unpublished venue should produce an alert.');
$assertSame('single_unpublished', $single_unpublished_context['state'], 'Single unpublished venue should use the dedicated finite state.');
$assertSame('notice notice-error vms-settings-default-venue-alert', $single_unpublished_context['notice_class'], 'Single unpublished venue should preserve the error alert classes.');
$assertSame('draft', $single_unpublished_context['status'], 'Single unpublished venue should preserve the status text.');
$assertSame(true, $single_unpublished_context['primary_action']['visible'], 'Single unpublished venue should preserve the primary publish action.');
$assertSame('Open venue and publish', $single_unpublished_context['primary_action']['label'], 'Single unpublished venue should preserve the action label.');
$assertSame('https://example.test/wp-admin/post.php?post=31&action=edit', $single_unpublished_context['primary_action']['href'], 'Single unpublished venue should preserve the fallback edit destination.');
$assertSame('button button-primary', $single_unpublished_context['primary_action']['class'], 'Single unpublished venue should preserve the primary action class.');
$assertSame(false, $single_unpublished_context['secondary_action']['visible'], 'Single unpublished venue should not add a second action.');

$reset_state();
$GLOBALS['vms_test_posts'] = array(
	41 => array(
		'post_type' => 'vms_venue',
		'post_status' => 'private',
		'post_title' => 'Private Venue',
		'edit_url' => 'https://example.test/wp-admin/post.php?post=41&action=edit',
	),
	42 => array(
		'post_type' => 'vms_venue',
		'post_status' => 'publish',
		'post_title' => 'Main <Hall>',
		'edit_url' => 'https://example.test/wp-admin/post.php?post=42&action=edit',
	),
);
$selected_unpublished_context = vms_build_settings_default_venue_alert_context(41, array(41, 42), array(42), false);
$assertSame(true, $selected_unpublished_context['show'], 'Selected unpublished venue should produce an alert.');
$assertSame('selected_unpublished', $selected_unpublished_context['state'], 'Selected unpublished venue should use the dedicated finite state.');
$assertSame('notice notice-warning vms-settings-default-venue-alert', $selected_unpublished_context['notice_class'], 'Selected unpublished venue should preserve the warning alert classes.');
$assertSame('private', $selected_unpublished_context['status'], 'Selected unpublished venue should preserve the status text.');
$assertSame('Open selected venue', $selected_unpublished_context['primary_action']['label'], 'Selected unpublished venue should preserve the selected-venue action label.');
$assertSame('https://example.test/wp-admin/post.php?post=41&action=edit', $selected_unpublished_context['primary_action']['href'], 'Selected unpublished venue should preserve the selected-venue action destination.');
$assertSame('button button-secondary', $selected_unpublished_context['primary_action']['class'], 'Selected unpublished venue should preserve the selected-venue action class.');
$assertSame(true, $selected_unpublished_context['secondary_action']['visible'], 'Selected unpublished venue should preserve the fix-now action when exactly one venue is publishable.');
$assertSame('Fix now: set Default Venue to “Main <Hall>”', $selected_unpublished_context['secondary_action']['label'], 'Fix-now action should preserve the exact label before escaping.');
$assertSame('https://example.test/wp-admin/admin-post.php?action=vms_set_default_venue&venue_id=42&_wpnonce=nonce%3Avms_set_default_venue_42', $selected_unpublished_context['secondary_action']['href'], 'Fix-now action should preserve the nonce-protected destination.');
$assertSame('button button-primary', $selected_unpublished_context['secondary_action']['class'], 'Fix-now action should preserve the primary button class.');

$reset_state();
$GLOBALS['vms_test_posts'] = array(
	52 => array(
		'post_type' => 'vms_venue',
		'post_status' => 'publish',
		'post_title' => 'Only & Venue',
		'edit_url' => 'https://example.test/wp-admin/post.php?post=52&action=edit',
	),
);
$unset_fix_context = vms_build_settings_default_venue_alert_context(0, array(52), array(52), false);
$assertSame(true, $unset_fix_context['show'], 'A missing default venue with one published candidate should produce an alert.');
$assertSame('unset', $unset_fix_context['state'], 'A missing default venue should use the unset finite state.');
$assertSame(false, $unset_fix_context['primary_action']['visible'], 'Unset default venue should not show the selected-venue action.');
$assertSame(true, $unset_fix_context['secondary_action']['visible'], 'Unset default venue should show the fix-now action when exactly one published venue exists.');

$reset_state();
$missing_saved_context = vms_build_settings_default_venue_alert_context(99, array(), array(), false);
$assertSame(true, $missing_saved_context['show'], 'A missing saved venue should still produce the not-set alert.');
$assertSame('unset', $missing_saved_context['state'], 'A missing saved venue should normalize to the unset finite state.');
$assertSame(false, $missing_saved_context['primary_action']['visible'], 'A missing saved venue should not show the selected-venue action.');
$assertSame(false, $missing_saved_context['secondary_action']['visible'], 'A missing saved venue with no published choices should not show the fix-now action.');

$reset_state();
$hidden_context = vms_build_settings_default_venue_alert_context(22, array(11, 22), array(22), true);
$assertSame(false, $hidden_context['show'], 'A published saved venue should produce no alert.');
$assertSame('hidden', $hidden_context['state'], 'A published saved venue should use the hidden finite state.');

$renderer_context = array(
	'show' => true,
	'state' => 'single_unpublished',
	'notice_class' => 'notice notice-error vms-settings-default-venue-alert',
	'status' => 'draft',
	'primary_action' => array(
		'visible' => true,
		'class' => 'button button-primary',
		'label' => 'Open venue and publish',
		'href' => 'https://example.test/wp-admin/post.php?post=31&action=edit',
		'target' => '',
		'rel' => '',
	),
	'secondary_action' => vms_settings_default_venue_alert_default_action_context(),
);
$reset_state();
ob_start();
vms_render_settings_default_venue_alert($renderer_context);
$rendered_single_unpublished = (string) ob_get_clean();
$assertSame(
	'<div class="notice notice-error vms-settings-default-venue-alert"><p><strong>Action required:</strong> Your only venue is not published (status: <strong>draft</strong>). This will cause Schedule and Season Dates to appear empty.</p><p><a class="button button-primary" href="https://example.test/wp-admin/post.php?post=31&amp;action=edit">Open venue and publish</a></p></div>',
	$rendered_single_unpublished,
	'The default-venue alert renderer should preserve the exact single-unpublished finite markup contract.'
);
$assertSame(
	array(
		'get_option' => 0,
		'get_posts' => 0,
		'get_post_type' => 0,
		'get_post_status' => 0,
		'get_the_title' => 0,
		'get_edit_post_link' => 0,
	),
	$GLOBALS['vms_test_provider_reads'],
	'The default-venue alert renderer should perform no external reads.'
);
$assert(strpos($rendered_single_unpublished, '<script') === false && strpos($rendered_single_unpublished, '<style') === false && strpos($rendered_single_unpublished, 'onclick=') === false, 'The default-venue alert renderer should not emit executable markup.');

$reset_state();
$renderer_context = array(
	'show' => true,
	'state' => 'selected_unpublished',
	'notice_class' => 'notice notice-warning vms-settings-default-venue-alert',
	'status' => 'private',
	'primary_action' => array(
		'visible' => true,
		'class' => 'button button-secondary',
		'label' => 'Open selected venue',
		'href' => 'https://example.test/wp-admin/post.php?post=41&action=edit',
		'target' => '',
		'rel' => '',
	),
	'secondary_action' => array(
		'visible' => true,
		'class' => 'button button-primary',
		'label' => 'Fix now: set Default Venue to “Main <Hall>”',
		'href' => 'https://example.test/wp-admin/admin-post.php?action=vms_set_default_venue&venue_id=42&_wpnonce=nonce%3Avms_set_default_venue_42',
		'target' => '',
		'rel' => '',
	),
);
ob_start();
vms_render_settings_default_venue_alert($renderer_context);
$rendered_selected_unpublished = (string) ob_get_clean();
$assertSame(
	'<div class="notice notice-warning vms-settings-default-venue-alert"><p><strong>Default Venue needs attention:</strong> The selected venue is not published (status: <strong>private</strong>). Publish it or choose a published venue.</p><p><a class="button button-secondary" href="https://example.test/wp-admin/post.php?post=41&amp;action=edit">Open selected venue</a></p><p><a class="button button-primary" href="https://example.test/wp-admin/admin-post.php?action=vms_set_default_venue&amp;venue_id=42&amp;_wpnonce=nonce%3Avms_set_default_venue_42">Fix now: set Default Venue to “Main &lt;Hall&gt;”</a></p></div>',
	$rendered_selected_unpublished,
	'The default-venue alert renderer should preserve the exact selected-unpublished finite markup contract.'
);

$reset_state();
$renderer_context = array(
	'show' => true,
	'state' => 'unset',
	'notice_class' => 'notice notice-warning vms-settings-default-venue-alert',
	'status' => '',
	'primary_action' => vms_settings_default_venue_alert_default_action_context(),
	'secondary_action' => array(
		'visible' => true,
		'class' => 'button button-primary',
		'label' => 'Fix now: set Default Venue to “Only & Venue”',
		'href' => 'https://example.test/wp-admin/admin-post.php?action=vms_set_default_venue&venue_id=52&_wpnonce=nonce%3Avms_set_default_venue_52',
		'target' => '',
		'rel' => '',
	),
	);
ob_start();
vms_render_settings_default_venue_alert($renderer_context);
$rendered_unset = (string) ob_get_clean();
$assertSame(
	'<div class="notice notice-warning vms-settings-default-venue-alert"><p><strong>Default Venue is not set.</strong> This can cause parts of VMS to load with no venue context (especially in single-venue installs).</p><p><a class="button button-primary" href="https://example.test/wp-admin/admin-post.php?action=vms_set_default_venue&amp;venue_id=52&amp;_wpnonce=nonce%3Avms_set_default_venue_52">Fix now: set Default Venue to “Only &amp; Venue”</a></p></div>',
	$rendered_unset,
	'The default-venue alert renderer should preserve the exact unset finite markup contract.'
);

ob_start();
vms_render_settings_default_venue_alert($hidden_context);
$rendered_hidden = (string) ob_get_clean();
$assertSame('', $rendered_hidden, 'The default-venue alert renderer should stay silent for no-alert states.');

$reset_state();
$GLOBALS['vms_test_options']['vms_settings'] = array('default_venue_id' => 41);
$GLOBALS['vms_test_default_venue_all_query_result'] = array(41, 42);
$GLOBALS['vms_test_default_venue_published_query_result'] = array(42);
$GLOBALS['vms_test_posts'] = array(
	41 => array(
		'post_type' => 'vms_venue',
		'post_status' => 'private',
		'post_title' => 'Private Venue',
		'edit_url' => 'https://example.test/wp-admin/post.php?post=41&action=edit',
	),
	42 => array(
		'post_type' => 'vms_venue',
		'post_status' => 'publish',
		'post_title' => 'Main <Hall>',
		'edit_url' => 'https://example.test/wp-admin/post.php?post=42&action=edit',
	),
);
ob_start();
vms_field_default_venue();
$field_with_alert = (string) ob_get_clean();
$assert(strpos($field_with_alert, '<select name="vms_settings[default_venue_id]" class="vms-minw-320">') === 0, 'The Default Venue setting control should remain at the start of the field output.');
$assert(strpos($field_with_alert, '<p class="description">Used when no venue is selected in context.</p>') !== false, 'The Default Venue description should remain present.');
$assert(strpos($field_with_alert, 'vms-settings-default-venue-alert') > strpos($field_with_alert, '<p class="description">Used when no venue is selected in context.</p>'), 'The Default Venue alert should remain after the description.');
$assert(strpos($field_with_alert, 'Fix now: set Default Venue to “Main &lt;Hall&gt;”') !== false, 'The Default Venue alert should escape dynamic venue titles at final output.');
$assert(strpos($field_with_alert, '<script') === false && strpos($field_with_alert, '<style') === false && strpos($field_with_alert, 'onclick=') === false, 'The field output should not contain executable markup.');

$captured_notices_html = '';
$remaining_field_html = vms_admin_ui_extract_notice_markup('<table><tr><td>' . $field_with_alert . '</td></tr></table>', $captured_notices_html);
$assertSame('', $captured_notices_html, 'The Default Venue alert should remain page-local and should not be captured into the Administrator shell notice buffer.');
$assert(strpos($remaining_field_html, 'vms-settings-default-venue-alert') !== false, 'The Default Venue alert should remain in page-local field content after shell notice extraction.');

fwrite(STDOUT, "settings default venue alert remediation: PASS\n");
