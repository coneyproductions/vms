<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('BVMGR_PLUGIN_URL')) {
	define('BVMGR_PLUGIN_URL', 'https://example.test/wp-content/plugins/backstage-venue-manager/');
}

if (!defined('BVMGR_VERSION')) {
	define('BVMGR_VERSION', 'test-version');
}

if (!class_exists('WP_Post')) {
	class WP_Post
	{
		public int $ID;
		public string $post_type;

		public function __construct(int $id = 0, string $post_type = 'vms_event_plan')
		{
			$this->ID = $id;
			$this->post_type = $post_type;
		}
	}
}

$GLOBALS['vms_test_actions'] = array();
$GLOBALS['vms_test_current_screen'] = (object) array(
	'id' => 'post',
	'post_type' => 'vms_event_plan',
);
$GLOBALS['vms_test_is_admin'] = true;
$GLOBALS['vms_test_user_options'] = array();
$GLOBALS['vms_test_post_types'] = array(
	42 => 'vms_event_plan',
);
$GLOBALS['vms_test_post_meta'] = array(
	42 => array(
		'_vms_social_do_not_post' => '1',
		'_vms_social_unpublished_after_post' => '1',
	),
);
$GLOBALS['vms_test_social_contexts'] = array(
	42 => array(
		'event_plan_id' => 42,
		'event_url' => 'https://events.example.test/event/42',
		'ticket_url' => 'https://tickets.example.test/event/42?from=<panel>',
	),
);
$GLOBALS['vms_test_social_enabled'] = array(
	42 => array(
		'facebook' => 1,
		'linkedin' => 0,
		'x' => 1,
	),
);
$GLOBALS['vms_test_social_template_overrides'] = array(
	42 => array(
		'facebook' => 101,
		'linkedin' => 0,
		'x' => 303,
	),
);
$GLOBALS['vms_test_social_templates'] = array(
	'facebook' => array(
		array('id' => 101, 'name' => 'Facebook Launch <Draft>', 'body' => 'fb body 101'),
		array('id' => 111, 'name' => 'Facebook Backup & More', 'body' => 'fb body 111'),
	),
	'linkedin' => array(
		array('id' => 202, 'name' => 'LinkedIn Default <Primary>', 'body' => 'li body 202'),
		array('id' => 212, 'name' => 'LinkedIn Secondary & Review', 'body' => 'li body 212'),
	),
	'x' => array(
		array('id' => 303, 'name' => 'X Main <Queue>', 'body' => 'x body 303'),
		array('id' => 313, 'name' => 'X Backup & Retry', 'body' => 'x body 313'),
	),
);
$GLOBALS['vms_test_social_default_templates'] = array(
	'facebook' => array('id' => 101),
	'linkedin' => array('id' => 202),
	'x' => array('id' => 303),
);
$GLOBALS['vms_test_social_rendered_payloads'] = array(
	'facebook' => array(
		'caption' => 'Facebook caption <unsafe>',
		'final_url' => 'https://share.example.test/facebook?event=42&copy=<draft>',
	),
	'linkedin' => array(
		'caption' => 'LinkedIn caption <unsafe>',
		'final_url' => 'https://share.example.test/linkedin?event=42&copy=<draft>',
	),
	'x' => array(
		'caption' => 'X caption <unsafe>',
		'final_url' => 'https://share.example.test/x?event=42&copy=<draft>',
	),
);
$GLOBALS['vms_test_social_settings'] = array('utm_enabled' => 1);
$GLOBALS['vms_test_social_queue_latest'] = array(
	42 => array(
		'id' => 314,
		'status' => 'failed',
		'platform' => 'linkedin',
		'last_error_message' => 'Rate limit <strong>retry</strong>',
	),
);
$GLOBALS['vms_test_current_user_can'] = true;
$GLOBALS['vms_test_social_manage'] = true;
$GLOBALS['vms_test_forbidden_runtime_reads'] = array(
	'get_post_meta' => 0,
	'get_post_type' => 0,
	'get_user_option' => 0,
	'current_user_can' => 0,
	'vms_social_current_user_can_manage' => 0,
	'vms_social_event_plan_context' => 0,
	'vms_social_event_meta_enabled_platforms' => 0,
	'vms_social_event_meta_template_overrides' => 0,
	'vms_social_queue_latest_for_event' => 0,
	'vms_social_templates_all' => 0,
	'vms_social_template_default_for_platform' => 0,
	'vms_social_template_for_platform' => 0,
	'vms_social_render_template_payload' => 0,
	'vms_social_get_settings' => 0,
	'wp_nonce_field' => 0,
	'wp_create_nonce' => 0,
	'admin_url' => 0,
);
$GLOBALS['vms_test_mutation_calls'] = array(
	'update_post_meta' => 0,
	'delete_post_meta' => 0,
	'update_option' => 0,
	'delete_option' => 0,
	'wp_insert_post' => 0,
	'wp_update_post' => 0,
);

if (!function_exists('add_action')) {
	function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
	{
		if (!isset($GLOBALS['vms_test_actions'][$hook]) || !is_array($GLOBALS['vms_test_actions'][$hook])) {
			$GLOBALS['vms_test_actions'][$hook] = array();
		}
		if (!isset($GLOBALS['vms_test_actions'][$hook][$priority]) || !is_array($GLOBALS['vms_test_actions'][$hook][$priority])) {
			$GLOBALS['vms_test_actions'][$hook][$priority] = array();
		}
		$GLOBALS['vms_test_actions'][$hook][$priority][] = $callback;
		unset($accepted_args);
		return true;
	}
}

if (!function_exists('has_action')) {
	function has_action(string $hook, $callback = false)
	{
		$callbacks = $GLOBALS['vms_test_actions'][$hook] ?? array();
		if ($callback === false) {
			return $callbacks !== array();
		}
		foreach ((array) $callbacks as $priority => $group) {
			foreach ((array) $group as $registered) {
				if ($registered === $callback) {
					return (int) $priority;
				}
			}
		}
		return false;
	}
}

if (!function_exists('__')) {
	function __(string $text, string $domain = ''): string
	{
		unset($domain);
		return $text;
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
		unset($domain);
		return esc_html($text);
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
		$url = trim((string) $url);
		if ($url === '' || preg_match('~^\s*javascript:~i', $url) === 1) {
			return '';
		}
		return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('esc_url_raw')) {
	function esc_url_raw($url): string
	{
		$url = trim((string) $url);
		if ($url === '' || preg_match('~^\s*javascript:~i', $url) === 1) {
			return '';
		}
		return $url;
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

if (!function_exists('checked')) {
	function checked($checked, $current = true, bool $display = true): string
	{
		unset($display);
		return (string) $checked === (string) $current ? ' checked="checked"' : '';
	}
}

if (!function_exists('selected')) {
	function selected($selected, $current = true, bool $display = true): string
	{
		unset($display);
		return (string) $selected === (string) $current ? ' selected="selected"' : '';
	}
}

if (!function_exists('admin_url')) {
	function admin_url(string $path = ''): string
	{
		$GLOBALS['vms_test_forbidden_runtime_reads']['admin_url']++;
		return 'https://example.test/wp-admin/' . ltrim($path, '/');
	}
}

if (!function_exists('wp_nonce_field')) {
	function wp_nonce_field($action = -1, string $name = '_wpnonce', bool $referer = true, bool $display = true): string
	{
		$GLOBALS['vms_test_forbidden_runtime_reads']['wp_nonce_field']++;
		$html = '<input type="hidden" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr('nonce:' . (string) $action) . '" />';
		if ($referer) {
			$html .= '<input type="hidden" name="_wp_http_referer" value="/wp-admin/post.php?post=42&amp;action=edit" />';
		}
		if ($display) {
			echo $html;
		}
		return $html;
	}
}

if (!function_exists('wp_create_nonce')) {
	function wp_create_nonce($action = -1): string
	{
		$GLOBALS['vms_test_forbidden_runtime_reads']['wp_create_nonce']++;
		return 'nonce:' . (string) $action;
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

if (!function_exists('current_user_can')) {
	function current_user_can(string $capability, ...$args): bool
	{
		unset($capability, $args);
		$GLOBALS['vms_test_forbidden_runtime_reads']['current_user_can']++;
		return (bool) $GLOBALS['vms_test_current_user_can'];
	}
}

if (!function_exists('get_post_type')) {
	function get_post_type(int $post_id): string
	{
		$GLOBALS['vms_test_forbidden_runtime_reads']['get_post_type']++;
		return (string) ($GLOBALS['vms_test_post_types'][$post_id] ?? '');
	}
}

if (!function_exists('get_post_meta')) {
	function get_post_meta(int $post_id, string $key, bool $single = false)
	{
		unset($single);
		$GLOBALS['vms_test_forbidden_runtime_reads']['get_post_meta']++;
		return $GLOBALS['vms_test_post_meta'][$post_id][$key] ?? '';
	}
}

if (!function_exists('get_user_option')) {
	function get_user_option(string $option)
	{
		$GLOBALS['vms_test_forbidden_runtime_reads']['get_user_option']++;
		return $GLOBALS['vms_test_user_options'][$option] ?? false;
	}
}

if (!function_exists('is_admin')) {
	function is_admin(): bool
	{
		return (bool) $GLOBALS['vms_test_is_admin'];
	}
}

if (!function_exists('get_current_screen')) {
	function get_current_screen()
	{
		return $GLOBALS['vms_test_current_screen'];
	}
}

if (!function_exists('vms_social_current_user_can_manage')) {
	function vms_social_current_user_can_manage(): bool
	{
		$GLOBALS['vms_test_forbidden_runtime_reads']['vms_social_current_user_can_manage']++;
		return (bool) $GLOBALS['vms_test_social_manage'];
	}
}

if (!function_exists('vms_social_event_plan_context')) {
	function vms_social_event_plan_context(int $event_plan_id): array
	{
		$GLOBALS['vms_test_forbidden_runtime_reads']['vms_social_event_plan_context']++;
		return (array) ($GLOBALS['vms_test_social_contexts'][$event_plan_id] ?? array());
	}
}

if (!function_exists('vms_social_event_meta_enabled_platforms')) {
	function vms_social_event_meta_enabled_platforms(int $event_plan_id): array
	{
		$GLOBALS['vms_test_forbidden_runtime_reads']['vms_social_event_meta_enabled_platforms']++;
		return (array) ($GLOBALS['vms_test_social_enabled'][$event_plan_id] ?? array());
	}
}

if (!function_exists('vms_social_event_meta_template_overrides')) {
	function vms_social_event_meta_template_overrides(int $event_plan_id): array
	{
		$GLOBALS['vms_test_forbidden_runtime_reads']['vms_social_event_meta_template_overrides']++;
		return (array) ($GLOBALS['vms_test_social_template_overrides'][$event_plan_id] ?? array());
	}
}

if (!function_exists('vms_social_queue_latest_for_event')) {
	function vms_social_queue_latest_for_event(int $event_plan_id): array
	{
		$GLOBALS['vms_test_forbidden_runtime_reads']['vms_social_queue_latest_for_event']++;
		return (array) ($GLOBALS['vms_test_social_queue_latest'][$event_plan_id] ?? array());
	}
}

if (!function_exists('vms_social_templates_all')) {
	function vms_social_templates_all(string $platform): array
	{
		$GLOBALS['vms_test_forbidden_runtime_reads']['vms_social_templates_all']++;
		return (array) ($GLOBALS['vms_test_social_templates'][$platform] ?? array());
	}
}

if (!function_exists('vms_social_template_default_for_platform')) {
	function vms_social_template_default_for_platform(string $platform): array
	{
		$GLOBALS['vms_test_forbidden_runtime_reads']['vms_social_template_default_for_platform']++;
		return (array) ($GLOBALS['vms_test_social_default_templates'][$platform] ?? array());
	}
}

if (!function_exists('vms_social_template_for_platform')) {
	function vms_social_template_for_platform(string $platform, int $template_id): array
	{
		$GLOBALS['vms_test_forbidden_runtime_reads']['vms_social_template_for_platform']++;
		foreach ((array) ($GLOBALS['vms_test_social_templates'][$platform] ?? array()) as $template) {
			if ((int) ($template['id'] ?? 0) === $template_id) {
				return (array) $template;
			}
		}
		return array();
	}
}

if (!function_exists('vms_social_render_template_payload')) {
	function vms_social_render_template_payload(string $platform, string $body, array $context, bool $utm_enabled): array
	{
		unset($body, $context, $utm_enabled);
		$GLOBALS['vms_test_forbidden_runtime_reads']['vms_social_render_template_payload']++;
		return (array) ($GLOBALS['vms_test_social_rendered_payloads'][$platform] ?? array());
	}
}

if (!function_exists('vms_social_get_settings')) {
	function vms_social_get_settings(): array
	{
		$GLOBALS['vms_test_forbidden_runtime_reads']['vms_social_get_settings']++;
		return (array) $GLOBALS['vms_test_social_settings'];
	}
}

if (!function_exists('vms_social_trim_preview')) {
	function vms_social_trim_preview(string $text, int $width = 180): string
	{
		unset($width);
		return $text;
	}
}

if (!function_exists('update_post_meta')) {
	function update_post_meta(int $post_id, string $meta_key, $meta_value)
	{
		unset($post_id, $meta_key, $meta_value);
		$GLOBALS['vms_test_mutation_calls']['update_post_meta']++;
		return true;
	}
}

if (!function_exists('delete_post_meta')) {
	function delete_post_meta(int $post_id, string $meta_key)
	{
		unset($post_id, $meta_key);
		$GLOBALS['vms_test_mutation_calls']['delete_post_meta']++;
		return true;
	}
}

if (!function_exists('update_option')) {
	function update_option(string $option, $value, $autoload = null): bool
	{
		unset($option, $value, $autoload);
		$GLOBALS['vms_test_mutation_calls']['update_option']++;
		return true;
	}
}

if (!function_exists('delete_option')) {
	function delete_option(string $option): bool
	{
		unset($option);
		$GLOBALS['vms_test_mutation_calls']['delete_option']++;
		return true;
	}
}

if (!function_exists('wp_insert_post')) {
	function wp_insert_post(array $postarr, bool $wp_error = false, bool $fire_after_hooks = true)
	{
		unset($postarr, $wp_error, $fire_after_hooks);
		$GLOBALS['vms_test_mutation_calls']['wp_insert_post']++;
		return 0;
	}
}

if (!function_exists('wp_update_post')) {
	function wp_update_post(array $postarr = array(), bool $wp_error = false, bool $fire_after_hooks = true)
	{
		unset($postarr, $wp_error, $fire_after_hooks);
		$GLOBALS['vms_test_mutation_calls']['wp_update_post']++;
		return 0;
	}
}

$assert = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}

	throw new RuntimeException($message);
};

$readFile = static function (string $path) use ($assert): string {
	$contents = @file_get_contents($path);
	$assert(is_string($contents) && $contents !== '', 'Expected readable source file: ' . $path);
	return $contents;
};

$extractSource = static function (string $source, string $startMarker, string $endMarker, string $label) use ($assert): string {
	$start = strpos($source, $startMarker);
	$assert($start !== false, 'Failed to locate start marker for ' . $label . '.');
	$end = strpos($source, $endMarker, $start);
	$assert($end !== false, 'Failed to locate end marker for ' . $label . '.');
	return substr($source, $start, $end - $start);
};

$parseFragment = static function (string $html, string $label) use ($assert): array {
	$doc = new DOMDocument('1.0', 'UTF-8');
	$loaded = @$doc->loadHTML('<?xml encoding="utf-8" ?><div id="vms-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
	$assert($loaded === true, 'Failed to parse HTML fragment: ' . $label);
	$xpath = new DOMXPath($doc);
	$root = $xpath->query('//*[@id="vms-root"]')->item(0);
	$assert($root instanceof DOMElement, 'Failed to locate fragment root: ' . $label);
	return array($doc, $xpath, $root);
};

$getDirectChildElements = static function (DOMElement $root): array {
	$children = array();
	foreach ($root->childNodes as $childNode) {
		if ($childNode instanceof DOMElement) {
			$children[] = $childNode;
		}
	}
	return $children;
};

$assertAttributesExact = static function (DOMElement $element, array $expected, string $label) use ($assert): void {
	$actual = array();
	foreach ($element->attributes as $attribute) {
		$actual[$attribute->name] = $attribute->value;
	}
	ksort($actual);
	ksort($expected);
	$assert($actual === $expected, $label . ' attributes did not match.');
};

$resetForbiddenCounters = static function (): void {
	foreach (array_keys($GLOBALS['vms_test_forbidden_runtime_reads']) as $key) {
		$GLOBALS['vms_test_forbidden_runtime_reads'][$key] = 0;
	}
	foreach (array_keys($GLOBALS['vms_test_mutation_calls']) as $key) {
		$GLOBALS['vms_test_mutation_calls'][$key] = 0;
	}
};

$assertNoForbiddenRuntimeCalls = static function (string $label) use ($assert): void {
	foreach ($GLOBALS['vms_test_forbidden_runtime_reads'] as $key => $count) {
		$assert($count === 0, $label . ' should not call ' . $key . '().');
	}
	foreach ($GLOBALS['vms_test_mutation_calls'] as $key => $count) {
		$assert($count === 0, $label . ' should not call ' . $key . '().');
	}
};

$pluginRoot = dirname(__DIR__);
$eventPanelPath = $pluginRoot . '/includes/social-share/event-plan-panel.php';
$socialAdminAssetPath = $pluginRoot . '/assets/js/vms-social-admin.js';

try {
	$eventPanelSource = $readFile($eventPanelPath);
	$socialAdminAssetSource = $readFile($socialAdminAssetPath);

	require_once $eventPanelPath;

	$assert(function_exists('vms_social_build_event_panel_view'), 'Main context builder should exist.');
	$assert(function_exists('vms_social_render_event_panel_html'), 'Main finite renderer should exist.');
	$assert(function_exists('vms_social_build_event_panel_footer_forms_view'), 'Footer context builder should exist.');
	$assert(function_exists('vms_social_render_event_panel_footer_forms_markup'), 'Footer finite renderer should exist.');

	foreach (array(
		'function vms_social_event_panel_markup(int $event_plan_id): array',
		'function vms_social_event_panel_footer_forms_html(int $event_plan_id, int $queue_id = 0): string',
		'function vms_social_event_panel_register_footer_forms(int $event_plan_id, int $queue_id = 0): void',
		'function vms_social_event_panel_render_footer_forms(): void',
		'function vms_social_render_event_panel(WP_Post $post): void',
		'function vms_social_ajax_load_event_panel(): void',
		'function vms_social_event_panel_form_id(int $event_plan_id, string $kind): string',
		'function vms_social_build_event_panel_view(int $event_plan_id): array',
		'function vms_social_render_event_panel_html(array $view): string',
		'function vms_social_build_event_panel_footer_forms_view(int $event_plan_id, int $queue_id = 0): array',
		'function vms_social_render_event_panel_footer_forms_markup(array $view): string',
	) as $requiredSignature) {
		$assert(strpos($eventPanelSource, $requiredSignature) !== false, 'Expected source signature marker: ' . $requiredSignature);
	}

	$markupSource = $extractSource(
		$eventPanelSource,
		'function vms_social_event_panel_markup(int $event_plan_id): array',
		"if (!function_exists('vms_social_add_event_panel'))",
		'vms_social_event_panel_markup()'
	);
	$footerHtmlSource = $extractSource(
		$eventPanelSource,
		'function vms_social_event_panel_footer_forms_html(int $event_plan_id, int $queue_id = 0): string',
		"if (!function_exists('vms_social_event_panel_render_footer_forms'))",
		'vms_social_event_panel_footer_forms_html()'
	);
	$renderSource = $extractSource(
		$eventPanelSource,
		'function vms_social_render_event_panel(WP_Post $post): void',
		"if (!function_exists('vms_social_ajax_load_event_panel'))",
		'vms_social_render_event_panel()'
	);
	$ajaxSource = $extractSource(
		$eventPanelSource,
		'function vms_social_ajax_load_event_panel(): void',
		"if (!function_exists('vms_social_save_event_panel'))",
		'vms_social_ajax_load_event_panel()'
	);
	$builderSource = $extractSource(
		$eventPanelSource,
		'function vms_social_build_event_panel_view(int $event_plan_id): array',
		"if (!function_exists('vms_social_normalize_event_panel_platform_render_view'))",
		'vms_social_build_event_panel_view()'
	);
	$rendererSource = $extractSource(
		$eventPanelSource,
		'function vms_social_render_event_panel_html(array $view): string',
		"if (!function_exists('vms_social_build_event_panel_footer_forms_view'))",
		'vms_social_render_event_panel_html()'
	);
	$footerBuilderSource = $extractSource(
		$eventPanelSource,
		'function vms_social_build_event_panel_footer_forms_view(int $event_plan_id, int $queue_id = 0): array',
		"if (!function_exists('vms_social_render_event_panel_footer_forms_markup'))",
		'vms_social_build_event_panel_footer_forms_view()'
	);
	$footerRendererSource = $extractSource(
		$eventPanelSource,
		'function vms_social_render_event_panel_footer_forms_markup(array $view): string',
		"if (!function_exists('vms_social_event_panel_register_footer_forms'))",
		'vms_social_render_event_panel_footer_forms_markup()'
	);

	$assert(strpos($markupSource, 'vms_social_build_event_panel_view(') !== false, 'Public main producer should delegate to the main context builder.');
	$assert(strpos($markupSource, 'vms_social_render_event_panel_html($view)') !== false, 'Public main producer should delegate to the main finite renderer.');
	$assert(strpos($footerHtmlSource, 'vms_social_build_event_panel_footer_forms_view(') !== false, 'Public footer producer should delegate to the footer context builder.');
	$assert(strpos($footerHtmlSource, 'vms_social_render_event_panel_footer_forms_markup($view)') !== false, 'Public footer producer should delegate to the footer finite renderer.');
	$assert(strpos($renderSource, 'vms_social_event_panel_markup($event_plan_id)') !== false, 'Synchronous render should still call the public main producer.');
	$assert(strpos($renderSource, 'vms_social_event_panel_register_footer_forms($event_plan_id, (int) ($payload[\'queue_id\'] ?? 0));') !== false, 'Synchronous render should still use the footer registry with the public payload.');
	$assert(strpos($ajaxSource, 'vms_social_event_panel_markup($event_plan_id)') !== false, 'AJAX render should still call the public main producer.');
	$assert(strpos($ajaxSource, '\'footer_forms_html\' => vms_social_event_panel_footer_forms_html($event_plan_id, (int) ($payload[\'queue_id\'] ?? 0)),') !== false, 'AJAX render should still call the public footer producer.');
	$assert(strpos($ajaxSource, "wp_send_json_error(array('message' => 'Invalid Event Plan.'), 400);") !== false, 'AJAX invalid-plan failure should remain unchanged.');
	$assert(strpos($ajaxSource, "wp_send_json_error(array('message' => 'Not allowed.'), 403);") !== false, 'AJAX capability failure should remain unchanged.');
	$assert(strpos($ajaxSource, "check_ajax_referer('vms_social_load_event_panel', 'nonce');") !== false, 'AJAX nonce check should remain unchanged.');

	foreach (array(
		'get_post_meta(',
		'get_post_type(',
		'current_user_can(',
		'get_user_option(',
		'vms_social_event_plan_context(',
		'vms_social_event_meta_enabled_platforms(',
		'vms_social_event_meta_template_overrides(',
		'vms_social_queue_latest_for_event(',
		'vms_social_templates_all(',
		'vms_social_template_default_for_platform(',
		'vms_social_template_for_platform(',
		'vms_social_render_template_payload(',
		'vms_social_get_settings(',
		'vms_social_event_share_url(',
		'wp_nonce_field(',
		'wp_create_nonce(',
		'admin_url(',
		'update_post_meta(',
		'delete_post_meta(',
		'update_option(',
		'delete_option(',
		'wp_insert_post(',
		'wp_update_post(',
	) as $forbiddenMarker) {
		$assert(strpos($rendererSource, $forbiddenMarker) === false, 'Main renderer should stay read-free and mutation-free: ' . $forbiddenMarker);
		$assert(strpos($footerRendererSource, $forbiddenMarker) === false, 'Footer renderer should stay read-free and mutation-free: ' . $forbiddenMarker);
	}

	foreach (array(
		'vms_social_event_plan_context(',
		'vms_social_event_meta_enabled_platforms(',
		'vms_social_event_meta_template_overrides(',
		'get_post_meta(',
		'vms_social_queue_latest_for_event(',
		'vms_social_get_settings(',
		'vms_social_build_event_panel_nonce_view(',
	) as $requiredBuilderMarker) {
		$assert(strpos($builderSource, $requiredBuilderMarker) !== false, 'Main builder should keep the expected external-read marker: ' . $requiredBuilderMarker);
	}
	$assert(strpos($footerBuilderSource, 'admin_url(\'admin-post.php\')') !== false, 'Footer builder should own admin-post URL generation.');
	$assert(strpos($footerBuilderSource, 'wp_create_nonce(\'vms_social_event_queue\')') !== false, 'Footer builder should own detached queue nonce generation.');
	$assert(strpos($eventPanelSource, 'wp_kses(') === false, 'No broad wp_kses() layer should be introduced in the Social Sharing event panel.');
	$assert(strpos($eventPanelSource, 'wp_kses_post(') === false, 'No broad wp_kses_post() layer should be introduced in the Social Sharing event panel.');
	$assert(strpos($socialAdminAssetSource, 'shell.innerHTML = String(payload.data.html);') !== false, 'JS source should remain unchanged for shell.innerHTML insertion.');
	$assert(strpos($socialAdminAssetSource, 'wrapper.innerHTML = String(html);') !== false, 'JS source should remain unchanged for detached footer parsing.');

	$queuedView = vms_social_build_event_panel_view(42);
	$assert(array_keys($queuedView) === array(
		'event_plan_id',
		'nonce_value',
		'referer_value',
		'do_not_post',
		'flag_unpublished',
		'platforms',
		'last_queue',
		'queue_id',
		'queue_form_id',
		'queue_cancel_form_id',
		'queue_retry_form_id',
	), 'Main context builder should preserve the documented finite key set.');
	$assert(array_keys((array) $queuedView['platforms']) === array('facebook', 'linkedin', 'x'), 'Main context builder should normalize exactly the current platform set.');
	foreach ((array) $queuedView['platforms'] as $platform => $platformView) {
		$assert(is_array($platformView), 'Expected a platform view array for ' . $platform . '.');
		$assert(array_keys($platformView) === array(
			'enabled',
			'selected_template_id',
			'template_options',
			'caption',
			'final_url',
			'share_url',
			'preview',
		), 'Platform view should preserve a finite key set for ' . $platform . '.');
	}
	$assert(is_array($queuedView['last_queue']) && array_keys($queuedView['last_queue']) === array('id', 'status', 'platform', 'last_error_message'), 'Main context builder should normalize a finite last_queue shape.');
	$assert((int) $queuedView['queue_id'] === 314, 'Main context builder should preserve the latest queue ID.');
	$assert((string) $queuedView['nonce_value'] === 'nonce:vms_social_event_panel_save', 'Main context builder should preserve the current panel nonce contract.');
	$assert((string) $queuedView['referer_value'] === '/wp-admin/post.php?post=42&action=edit', 'Main context builder should preserve the current referer contract.');

	$queuedFooterView = vms_social_build_event_panel_footer_forms_view(42, 314);
	$assert(array_keys($queuedFooterView) === array(
		'event_plan_id',
		'queue_id',
		'action_url',
		'queue_form_id',
		'queue_cancel_form_id',
		'queue_retry_form_id',
		'queue_nonce_value',
		'queue_cancel_nonce_value',
		'queue_retry_nonce_value',
	), 'Footer context builder should preserve the documented finite key set.');
	$assert((string) $queuedFooterView['action_url'] === 'https://example.test/wp-admin/admin-post.php', 'Footer context builder should preserve the admin-post URL contract.');

	$resetForbiddenCounters();
	$queuedDirectHtml = vms_social_render_event_panel_html($queuedView);
	$assertNoForbiddenRuntimeCalls('Main renderer direct execution');
	$resetForbiddenCounters();
	$queuedDirectFooterHtml = vms_social_render_event_panel_footer_forms_markup($queuedFooterView);
	$assertNoForbiddenRuntimeCalls('Footer renderer direct execution');

	$publicQueuedPayload = vms_social_event_panel_markup(42);
	$assert((string) $publicQueuedPayload['html'] === $queuedDirectHtml, 'Public main producer should return the exact finite renderer output.');
	$assert((int) $publicQueuedPayload['queue_id'] === 314, 'Public main producer should preserve the queue_id contract.');
	$assert(vms_social_event_panel_footer_forms_html(42, 314) === $queuedDirectFooterHtml, 'Public footer producer should return the exact finite renderer output.');

	$GLOBALS['vms_test_user_options'] = array();
	$GLOBALS['bvmgr_social_event_panel_footer_forms'] = array();
	ob_start();
	vms_social_render_event_panel(new WP_Post(42, 'vms_event_plan'));
	$openPanelHtml = (string) ob_get_clean();
	$assert($openPanelHtml === (string) $publicQueuedPayload['html'], 'Synchronous open-panel rendering should still echo the public main producer output.');
	$assert(isset($GLOBALS['bvmgr_social_event_panel_footer_forms'][42]), 'Synchronous open-panel rendering should still populate the footer registry.');

	list($queuedDoc, $queuedXPath, $queuedRoot) = $parseFragment($queuedDirectHtml, 'queued main renderer');
	unset($queuedDoc);
	$queuedChildren = $getDirectChildElements($queuedRoot);
	$assert(array_map(static fn (DOMElement $element): string => $element->tagName, $queuedChildren) === array('input', 'input', 'p', 'p', 'div', 'div', 'hr', 'h4', 'p', 'p', 'div', 'div'), 'Queued main renderer should preserve the exact current top-level order.');
	$assertAttributesExact($queuedChildren[0], array(
		'id' => 'vms_social_event_panel_nonce',
		'name' => 'vms_social_event_panel_nonce',
		'type' => 'hidden',
		'value' => 'nonce:vms_social_event_panel_save',
	), 'Queued main nonce input');
	$assertAttributesExact($queuedChildren[1], array(
		'name' => '_wp_http_referer',
		'type' => 'hidden',
		'value' => '/wp-admin/post.php?post=42&action=edit',
	), 'Queued main referer input');
	$assert(trim((string) $queuedChildren[2]->textContent) === 'Phase 1 manual toolkit: copy caption/link and open share dialogs. Queue actions currently use the Phase 0 provider framework.', 'Queued main renderer should preserve the lead description copy.');
	$assert(trim((string) $queuedChildren[3]->textContent) === 'Do not post this event', 'Queued main renderer should preserve the do-not-post copy.');
	$warning = $queuedChildren[4];
	$assertAttributesExact($warning, array('class' => 'notice notice-warning inline'), 'Queued warning wrapper');
	$assert(trim((string) $warning->textContent) === 'Unpublished after social post This event is now Draft but has at least one previously posted social queue item.', 'Queued warning copy should remain unchanged.');
	$queueSummary = $queuedChildren[8];
	$assert(trim((string) $queueSummary->textContent) === 'Latest queue item: #314 | failed | linkedin', 'Queued renderer should preserve the latest queue summary.');
	$queueError = $queuedChildren[9];
	$assertAttributesExact($queueError, array('class' => 'description'), 'Queued queue error wrapper');
	$assert(trim((string) $queueError->textContent) === 'Rate limit <strong>retry</strong>', 'Queued renderer should preserve escaped queue error text.');

	$queuedPlatformSections = $queuedXPath->query('//*[@id="vms-root"]/div[@class="vms-social-platform-grid"]/section');
	$assert($queuedPlatformSections instanceof DOMNodeList && $queuedPlatformSections->length === 3, 'Queued renderer should preserve exactly three platform sections.');
	$assert($queuedXPath->query('//*[@id="vms-root"]//a[@class="button button-secondary"]')->length === 3, 'Queued renderer should preserve the current share-link count.');
	$assert($queuedXPath->query('//*[@id="vms-root"]//button[@form="vms-social-queue-cancel-form-42"]')->length === 1, 'Queued renderer should preserve the cancel button form coupling.');
	$assert($queuedXPath->query('//*[@id="vms-root"]//button[@form="vms-social-queue-retry-form-42"]')->length === 1, 'Queued renderer should preserve the retry button form coupling.');

	$defaultNoQueueView = array(
		'event_plan_id' => 42,
		'nonce_value' => 'nonce:vms_social_event_panel_save',
		'referer_value' => '/wp-admin/post.php?post=42&action=edit',
		'do_not_post' => false,
		'flag_unpublished' => false,
		'platforms' => array(
			'facebook' => array(
				'enabled' => 1,
				'selected_template_id' => 0,
				'template_options' => array(
					array('id' => 101, 'name' => 'Facebook Launch <Draft>'),
					array('id' => 111, 'name' => 'Facebook Backup & More'),
				),
				'caption' => 'Facebook default preview',
				'final_url' => 'https://share.example.test/facebook?event=42',
				'share_url' => 'https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fshare.example.test%2Ffacebook%3Fevent%3D42',
				'preview' => 'Facebook default preview',
			),
			'linkedin' => array(
				'enabled' => 1,
				'selected_template_id' => 0,
				'template_options' => array(
					array('id' => 202, 'name' => 'LinkedIn Default <Primary>'),
					array('id' => 212, 'name' => 'LinkedIn Secondary & Review'),
				),
				'caption' => 'LinkedIn default preview',
				'final_url' => 'https://share.example.test/linkedin?event=42',
				'share_url' => 'https://www.linkedin.com/sharing/share-offsite/?url=https%3A%2F%2Fshare.example.test%2Flinkedin%3Fevent%3D42',
				'preview' => 'LinkedIn default preview',
			),
			'x' => array(
				'enabled' => 1,
				'selected_template_id' => 0,
				'template_options' => array(
					array('id' => 303, 'name' => 'X Main <Queue>'),
					array('id' => 313, 'name' => 'X Backup & Retry'),
				),
				'caption' => 'X default preview',
				'final_url' => 'https://share.example.test/x?event=42',
				'share_url' => 'https://twitter.com/intent/tweet?text=X%20default%20preview&url=https%3A%2F%2Fshare.example.test%2Fx%3Fevent%3D42',
				'preview' => 'X default preview',
			),
		),
		'last_queue' => array(),
		'queue_id' => 0,
		'queue_form_id' => 'vms-social-event-queue-form-42',
		'queue_cancel_form_id' => 'vms-social-queue-cancel-form-42',
		'queue_retry_form_id' => 'vms-social-queue-retry-form-42',
	);
	$resetForbiddenCounters();
	$defaultNoQueueHtml = vms_social_render_event_panel_html($defaultNoQueueView);
	$assertNoForbiddenRuntimeCalls('Main renderer default/no-queue execution');
	list($defaultDoc, $defaultXPath, $defaultRoot) = $parseFragment($defaultNoQueueHtml, 'default no-queue renderer');
	unset($defaultDoc);
	$defaultChildren = $getDirectChildElements($defaultRoot);
	$assert(array_map(static fn (DOMElement $element): string => $element->tagName, $defaultChildren) === array('input', 'input', 'p', 'p', 'div', 'hr', 'h4', 'div'), 'Default/no-queue renderer should preserve the exact reduced top-level order.');
	$assert($defaultXPath->query('//*[@id="vms-root"]//*[@class="notice notice-warning inline"]')->length === 0, 'Default/no-queue renderer should omit the unpublished warning.');
	$assert($defaultXPath->query('//*[@id="vms-root"]//*[contains(text(),"Latest queue item:")]')->length === 0, 'Default/no-queue renderer should omit queue summary text.');
	$assert($defaultXPath->query('//*[@id="vms-root"]//*[@class="vms-social-event-queue-ops"]')->length === 0, 'Default/no-queue renderer should omit queue operations.');
	$defaultCheckbox = $defaultXPath->query('//*[@id="vms-root"]/p[2]/label/input')->item(0);
	$assert($defaultCheckbox instanceof DOMElement && !$defaultCheckbox->hasAttribute('checked'), 'Default/no-queue renderer should leave the do-not-post checkbox unchecked.');

	$hostileView = array(
		'event_plan_id' => 42,
		'nonce_value' => 'nonce:vms_social_event_panel_save"><svg onload=alert(1)>',
		'referer_value' => 'javascript:alert(1)',
		'do_not_post' => true,
		'flag_unpublished' => true,
		'platforms' => array(
			'facebook' => array(
				'enabled' => 1,
				'selected_template_id' => 999,
				'template_options' => array(
					array('id' => 901, 'name' => 'Alpha <script>alert(1)</script>'),
				),
				'caption' => '<img src=x onerror=alert(1)>',
				'final_url' => 'javascript:alert(2)',
				'share_url' => 'javascript:alert(3)',
				'preview' => '<svg onload=alert(4)>',
				'extra_attr' => 'ignored',
			),
			'linkedin' => array(
				'enabled' => 0,
				'selected_template_id' => 0,
				'template_options' => array(
					array('id' => 0, 'name' => 'Zero should still stay text only'),
				),
				'caption' => 'LinkedIn hostile "caption"',
				'final_url' => 'https://safe.example.test/linkedin?x=<bad>',
				'share_url' => 'https://www.linkedin.com/sharing/share-offsite/?url=https%3A%2F%2Fsafe.example.test%2Flinkedin',
				'preview' => 'LinkedIn hostile "caption"',
			),
			'x' => array(
				'enabled' => 1,
				'selected_template_id' => 303,
				'template_options' => array(
					array('id' => 303, 'name' => 'X Main <Queue>'),
				),
				'caption' => 'X hostile caption',
				'final_url' => 'https://safe.example.test/x',
				'share_url' => 'https://twitter.com/intent/tweet?text=X%20hostile%20caption&url=https%3A%2F%2Fsafe.example.test%2Fx',
				'preview' => 'X hostile caption',
			),
			'instagram' => array(
				'enabled' => 1,
				'selected_template_id' => 1,
				'template_options' => array(array('id' => 1, 'name' => 'Should never render')),
				'caption' => 'Nope',
				'final_url' => 'https://unsafe.example.test',
				'share_url' => 'https://unsafe.example.test',
				'preview' => 'Nope',
			),
		),
		'last_queue' => array(
			'id' => 77,
			'status' => '<b>failed</b>',
			'platform' => 'x"><script>alert(5)</script>',
			'last_error_message' => '<script>alert(6)</script>',
		),
		'queue_id' => 77,
		'queue_form_id' => 'queue" onmouseover="alert(7)',
		'queue_cancel_form_id' => 'cancel" onclick="alert(8)',
		'queue_retry_form_id' => 'retry" onfocus="alert(9)',
	);
	$resetForbiddenCounters();
	$hostileMainHtml = vms_social_render_event_panel_html($hostileView);
	$assertNoForbiddenRuntimeCalls('Main renderer hostile execution');
	list($hostileDoc, $hostileXPath, $hostileRoot) = $parseFragment($hostileMainHtml, 'hostile main renderer');
	unset($hostileDoc, $hostileRoot);
	$assert($hostileXPath->query('//*[@id="vms-root"]//script | //*[@id="vms-root"]//style | //*[@id="vms-root"]//img | //*[@id="vms-root"]//svg')->length === 0, 'Hostile main view should not inject executable or media markup.');
	$assert($hostileXPath->query('//*[@id="vms-root"]//section')->length === 3, 'Hostile main view should still render only the supported three platform sections.');
	$assert(strpos($hostileMainHtml, 'instagram') === false, 'Hostile main view should ignore unsupported platform variants.');
	$assert($hostileXPath->query('//*[@id="vms-root"]//@href[starts-with(translate(., "JAVASCRIPT", "javascript"), "javascript:")]')->length === 0, 'Hostile main view should not emit unsafe href URLs.');
	$assert($hostileXPath->query('//*[@id="vms-root"]//@onload | //*[@id="vms-root"]//@onclick | //*[@id="vms-root"]//@onfocus | //*[@id="vms-root"]//@onmouseover')->length === 0, 'Hostile main view should not introduce event-handler attributes.');

	$queuedFooterHtmlNoQueue = vms_social_render_event_panel_footer_forms_markup(vms_social_build_event_panel_footer_forms_view(42, 0));
	$assert($queuedFooterHtmlNoQueue === vms_social_event_panel_footer_forms_html(42, 0), 'Public footer producer should preserve the one-form state.');
	list($footerNoQueueDoc, $footerNoQueueXPath, $footerNoQueueRoot) = $parseFragment($queuedFooterHtmlNoQueue, 'footer no-queue renderer');
	unset($footerNoQueueDoc);
	$footerNoQueueChildren = $getDirectChildElements($footerNoQueueRoot);
	$assert(count($footerNoQueueChildren) === 1, 'Footer renderer should preserve the one-form no-queue state.');
	$assertAttributesExact($footerNoQueueChildren[0], array(
		'action' => 'https://example.test/wp-admin/admin-post.php',
		'class' => 'vms-social-detached-form',
		'id' => 'vms-social-event-queue-form-42',
		'method' => 'post',
		'style' => 'display:none;',
	), 'No-queue detached form');

	list($footerQueuedDoc, $footerQueuedXPath, $footerQueuedRoot) = $parseFragment($queuedDirectFooterHtml, 'footer queued renderer');
	unset($footerQueuedDoc);
	$footerQueuedChildren = $getDirectChildElements($footerQueuedRoot);
	$assert(array_map(static fn (DOMElement $element): string => $element->getAttribute('id'), $footerQueuedChildren) === array(
		'vms-social-event-queue-form-42',
		'vms-social-queue-cancel-form-42',
		'vms-social-queue-retry-form-42',
	), 'Footer renderer should preserve the exact three-form order.');
	$getHiddenInputMap = static function (DOMElement $form): array {
		$inputs = array();
		foreach ($form->getElementsByTagName('input') as $inputNode) {
			if (!$inputNode instanceof DOMElement) {
				continue;
			}
			$inputs[] = array(
				'name' => $inputNode->getAttribute('name'),
				'type' => $inputNode->getAttribute('type'),
				'value' => $inputNode->getAttribute('value'),
			);
		}
		return $inputs;
	};
	$assert($getHiddenInputMap($footerQueuedChildren[0]) === array(
		array('name' => '_wpnonce', 'type' => 'hidden', 'value' => 'nonce:vms_social_event_queue'),
		array('name' => 'action', 'type' => 'hidden', 'value' => 'vms_social_event_queue'),
		array('name' => 'event_plan_id', 'type' => 'hidden', 'value' => '42'),
	), 'Queue detached form should preserve the exact hidden-field contract.');
	$assert($getHiddenInputMap($footerQueuedChildren[1]) === array(
		array('name' => '_wpnonce', 'type' => 'hidden', 'value' => 'nonce:vms_social_queue_cancel'),
		array('name' => 'action', 'type' => 'hidden', 'value' => 'vms_social_queue_cancel'),
		array('name' => 'queue_id', 'type' => 'hidden', 'value' => '314'),
		array('name' => 'event_plan_id', 'type' => 'hidden', 'value' => '42'),
		array('name' => 'tab', 'type' => 'hidden', 'value' => 'queue'),
	), 'Cancel detached form should preserve the exact hidden-field contract.');
	$assert($getHiddenInputMap($footerQueuedChildren[2]) === array(
		array('name' => '_wpnonce', 'type' => 'hidden', 'value' => 'nonce:vms_social_queue_retry'),
		array('name' => 'action', 'type' => 'hidden', 'value' => 'vms_social_queue_retry'),
		array('name' => 'queue_id', 'type' => 'hidden', 'value' => '314'),
		array('name' => 'event_plan_id', 'type' => 'hidden', 'value' => '42'),
		array('name' => 'tab', 'type' => 'hidden', 'value' => 'queue'),
	), 'Retry detached form should preserve the exact hidden-field contract.');

	$resetForbiddenCounters();
	$hostileFooterHtml = vms_social_render_event_panel_footer_forms_markup(array(
		'event_plan_id' => 42,
		'queue_id' => 7,
		'action_url' => 'javascript:alert(1)',
		'queue_form_id' => 'queue" onmouseover="alert(2)',
		'queue_cancel_form_id' => 'cancel" onclick="alert(3)',
		'queue_retry_form_id' => 'retry" onfocus="alert(4)',
		'queue_nonce_value' => 'nonce:queue"><script>alert(5)</script>',
		'queue_cancel_nonce_value' => 'nonce:cancel"><script>alert(6)</script>',
		'queue_retry_nonce_value' => 'nonce:retry"><script>alert(7)</script>',
		'extra_form_variant' => array('nope' => true),
	));
	$assertNoForbiddenRuntimeCalls('Footer renderer hostile execution');
	list($hostileFooterDoc, $hostileFooterXPath, $hostileFooterRoot) = $parseFragment($hostileFooterHtml, 'hostile footer renderer');
	unset($hostileFooterDoc);
	$hostileFooterChildren = $getDirectChildElements($hostileFooterRoot);
	$assert(count($hostileFooterChildren) === 3, 'Hostile footer view should still render only the fixed three forms.');
	$assert($hostileFooterXPath->query('//*[@id="vms-root"]//script | //*[@id="vms-root"]//style')->length === 0, 'Hostile footer view should not inject executable markup.');
	$assert($hostileFooterXPath->query('//*[@id="vms-root"]//@action[starts-with(translate(., "JAVASCRIPT", "javascript"), "javascript:")]')->length === 0, 'Hostile footer view should not emit unsafe form actions.');
	$assert($hostileFooterXPath->query('//*[@id="vms-root"]//@onload | //*[@id="vms-root"]//@onclick | //*[@id="vms-root"]//@onfocus | //*[@id="vms-root"]//@onmouseover')->length === 0, 'Hostile footer view should not introduce event-handler attributes.');

	$mainFormIds = array(
		(string) $queuedXPath->query('//*[@id="vms-root"]//select[@name="platform"]')->item(0)?->getAttribute('form'),
		(string) $queuedXPath->query('//*[@id="vms-root"]//input[@name="template_id"]')->item(0)?->getAttribute('form'),
		(string) $queuedXPath->query('//*[@id="vms-root"]//input[@name="destination_id"]')->item(0)?->getAttribute('form'),
		(string) $queuedXPath->query('//*[@id="vms-root"]//input[@name="scheduled_at_local"]')->item(0)?->getAttribute('form'),
	);
	$assert(count(array_unique($mainFormIds)) === 1 && $mainFormIds[0] === 'vms-social-event-queue-form-42', 'Visible queue controls should preserve the detached queue form coupling.');
	$footerQueuedIds = array_map(static fn (DOMElement $form): string => $form->getAttribute('id'), $footerQueuedChildren);
	$assert(in_array($mainFormIds[0], $footerQueuedIds, true), 'Visible queue control form IDs should match detached form IDs.');

	fwrite(STDOUT, "social event-panel finite renderer output remediation: PASS\n");
} catch (Throwable $throwable) {
	fwrite(STDERR, 'social event-panel finite renderer output remediation: FAIL - ' . $throwable->getMessage() . "\n");
	exit(1);
}
