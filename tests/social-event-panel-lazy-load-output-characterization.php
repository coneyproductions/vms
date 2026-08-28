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

final class VMS_Social_Event_Panel_Ajax_Exit extends RuntimeException
{
	/** @var array<string,mixed> */
	public array $response;

	/**
	 * @param array<string,mixed> $response
	 */
	public function __construct(array $response)
	{
		parent::__construct('social-event-panel-ajax-exit');
		$this->response = $response;
	}
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
$GLOBALS['vms_test_meta_boxes'] = array();
$GLOBALS['vms_test_scripts'] = array();
$GLOBALS['vms_test_localized_scripts'] = array();
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
		'_vms_social_platform_overrides' => array(
			'facebook' => 1,
			'linkedin' => 0,
			'x' => 1,
		),
		'_vms_social_template_overrides' => array(
			'facebook' => 101,
			'linkedin' => 0,
			'x' => 303,
		),
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
$GLOBALS['vms_test_ajax_referer_calls'] = array();
$GLOBALS['vms_test_runtime_reads'] = array(
	'get_post_meta' => 0,
	'get_post_type' => 0,
	'get_user_option' => 0,
	'current_user_can' => 0,
	'bvmgr_social_current_user_can_manage' => 0,
	'bvmgr_social_event_plan_context' => 0,
	'bvmgr_social_queue_latest_for_event' => 0,
	'bvmgr_social_templates_all' => 0,
	'bvmgr_social_template_default_for_platform' => 0,
	'bvmgr_social_template_for_platform' => 0,
	'bvmgr_social_render_template_payload' => 0,
	'bvmgr_social_get_settings' => 0,
	'wp_create_nonce' => 0,
	'check_ajax_referer' => 0,
);
$GLOBALS['vms_test_mutation_calls'] = array(
	'update_post_meta' => 0,
	'delete_post_meta' => 0,
	'update_option' => 0,
	'delete_option' => 0,
);
$GLOBALS['vms_test_current_user_can'] = true;
$GLOBALS['vms_test_social_manage'] = true;

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

if (!function_exists('add_meta_box')) {
	function add_meta_box(string $id, string $title, $callback, string $screen, string $context = 'advanced', string $priority = 'default'): void
	{
		$GLOBALS['vms_test_meta_boxes'][] = compact('id', 'title', 'callback', 'screen', 'context', 'priority');
	}
}

if (!function_exists('wp_enqueue_script')) {
	function wp_enqueue_script(string $handle, string $src = '', array $deps = array(), $ver = false, bool $in_footer = false): bool
	{
		$GLOBALS['vms_test_scripts'][] = array(
			'handle' => $handle,
			'src' => $src,
			'deps' => $deps,
			'ver' => $ver,
			'in_footer' => $in_footer,
		);
		return true;
	}
}

if (!function_exists('wp_localize_script')) {
	function wp_localize_script(string $handle, string $name, array $data): bool
	{
		$GLOBALS['vms_test_localized_scripts'][] = compact('handle', 'name', 'data');
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
		return 'https://example.test/wp-admin/' . ltrim($path, '/');
	}
}

if (!function_exists('add_query_arg')) {
	function add_query_arg(array $args = array(), string $url = ''): string
	{
		$base = $url !== '' ? $url : admin_url('admin.php');
		$parts = parse_url($base);
		$query = array();
		if (!empty($parts['query'])) {
			parse_str($parts['query'], $query);
		}
		foreach ($args as $key => $value) {
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

if (!function_exists('wp_unslash')) {
	function wp_unslash($value)
	{
		if (is_array($value)) {
			return array_map('wp_unslash', $value);
		}
		return is_string($value) ? stripslashes($value) : $value;
	}
}

if (!function_exists('is_admin')) {
	function is_admin(): bool
	{
		return !empty($GLOBALS['vms_test_is_admin']);
	}
}

if (!function_exists('get_current_screen')) {
	function get_current_screen()
	{
		return $GLOBALS['vms_test_current_screen'];
	}
}

if (!function_exists('get_user_option')) {
	function get_user_option(string $key)
	{
		$GLOBALS['vms_test_runtime_reads']['get_user_option']++;
		return $GLOBALS['vms_test_user_options'][$key] ?? '';
	}
}

if (!function_exists('current_user_can')) {
	function current_user_can(string $capability, ...$args): bool
	{
		unset($capability, $args);
		$GLOBALS['vms_test_runtime_reads']['current_user_can']++;
		return !empty($GLOBALS['vms_test_current_user_can']);
	}
}

if (!function_exists('get_post_meta')) {
	function get_post_meta(int $post_id, string $key = '', bool $single = false)
	{
		unset($single);
		$GLOBALS['vms_test_runtime_reads']['get_post_meta']++;
		return $GLOBALS['vms_test_post_meta'][$post_id][$key] ?? '';
	}
}

if (!function_exists('update_post_meta')) {
	function update_post_meta(int $post_id, string $meta_key, $meta_value, $prev_value = '')
	{
		unset($post_id, $meta_key, $meta_value, $prev_value);
		$GLOBALS['vms_test_mutation_calls']['update_post_meta']++;
		return true;
	}
}

if (!function_exists('delete_post_meta')) {
	function delete_post_meta(int $post_id, string $meta_key, $meta_value = ''): bool
	{
		unset($post_id, $meta_key, $meta_value);
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

if (!function_exists('get_post_type')) {
	function get_post_type(int $post_id): string
	{
		$GLOBALS['vms_test_runtime_reads']['get_post_type']++;
		return (string) ($GLOBALS['vms_test_post_types'][$post_id] ?? '');
	}
}

if (!function_exists('wp_nonce_field')) {
	function wp_nonce_field($action = -1, string $name = '_wpnonce', bool $referer = true, bool $display = true): string
	{
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
		$GLOBALS['vms_test_runtime_reads']['wp_create_nonce']++;
		return 'nonce:' . (string) $action;
	}
}

if (!function_exists('check_ajax_referer')) {
	function check_ajax_referer($action = -1, $query_arg = false, bool $stop = true): int
	{
		unset($stop);
		$GLOBALS['vms_test_runtime_reads']['check_ajax_referer']++;
		$value = '';
		if ($query_arg !== false && isset($_REQUEST[(string) $query_arg]) && !is_array($_REQUEST[(string) $query_arg])) {
			$value = (string) $_REQUEST[(string) $query_arg];
		}
		$GLOBALS['vms_test_ajax_referer_calls'][] = array(
			'action' => (string) $action,
			'query_arg' => $query_arg,
			'value' => $value,
		);
		if ($value !== 'nonce:' . (string) $action) {
			throw new RuntimeException('AJAX nonce check should preserve the exact action/query-arg contract.');
		}
		return 1;
	}
}

if (!function_exists('wp_send_json_success')) {
	function wp_send_json_success($data = null, int $status_code = 200): void
	{
		throw new VMS_Social_Event_Panel_Ajax_Exit(array(
			'success' => true,
			'data' => is_array($data) ? $data : array(),
			'status_code' => $status_code,
		));
	}
}

if (!function_exists('wp_send_json_error')) {
	function wp_send_json_error($data = null, int $status_code = 200): void
	{
		throw new VMS_Social_Event_Panel_Ajax_Exit(array(
			'success' => false,
			'data' => is_array($data) ? $data : array(),
			'status_code' => $status_code,
		));
	}
}

if (!function_exists('get_posts')) {
	function get_posts(array $args = array()): array
	{
		unset($args);
		return array();
	}
}

if (!function_exists('bvmgr_social_current_user_can_manage')) {
	function bvmgr_social_current_user_can_manage(): bool
	{
		$GLOBALS['vms_test_runtime_reads']['bvmgr_social_current_user_can_manage']++;
		return !empty($GLOBALS['vms_test_social_manage']);
	}
}

if (!function_exists('bvmgr_social_event_plan_context')) {
	function bvmgr_social_event_plan_context(int $event_plan_id): array
	{
		$GLOBALS['vms_test_runtime_reads']['bvmgr_social_event_plan_context']++;
		return (array) ($GLOBALS['vms_test_social_contexts'][$event_plan_id] ?? array());
	}
}

if (!function_exists('bvmgr_social_queue_latest_for_event')) {
	function bvmgr_social_queue_latest_for_event(int $event_plan_id): array
	{
		$GLOBALS['vms_test_runtime_reads']['bvmgr_social_queue_latest_for_event']++;
		return (array) ($GLOBALS['vms_test_social_queue_latest'][$event_plan_id] ?? array());
	}
}

if (!function_exists('bvmgr_social_templates_all')) {
	function bvmgr_social_templates_all(string $platform): array
	{
		$GLOBALS['vms_test_runtime_reads']['bvmgr_social_templates_all']++;
		return (array) ($GLOBALS['vms_test_social_templates'][$platform] ?? array());
	}
}

if (!function_exists('bvmgr_social_template_default_for_platform')) {
	function bvmgr_social_template_default_for_platform(string $platform): array
	{
		$GLOBALS['vms_test_runtime_reads']['bvmgr_social_template_default_for_platform']++;
		return (array) ($GLOBALS['vms_test_social_default_templates'][$platform] ?? array());
	}
}

if (!function_exists('bvmgr_social_template_for_platform')) {
	function bvmgr_social_template_for_platform(string $platform, int $template_id): array
	{
		$GLOBALS['vms_test_runtime_reads']['bvmgr_social_template_for_platform']++;
		foreach ((array) ($GLOBALS['vms_test_social_templates'][$platform] ?? array()) as $template) {
			if ((int) ($template['id'] ?? 0) === $template_id) {
				return (array) $template;
			}
		}
		return array('id' => $template_id, 'body' => '');
	}
}

if (!function_exists('bvmgr_social_render_template_payload')) {
	function bvmgr_social_render_template_payload(string $platform, string $body, array $context, bool $utm_enabled): array
	{
		unset($body, $context, $utm_enabled);
		$GLOBALS['vms_test_runtime_reads']['bvmgr_social_render_template_payload']++;
		return (array) ($GLOBALS['vms_test_social_rendered_payloads'][$platform] ?? array('caption' => '', 'final_url' => ''));
	}
}

if (!function_exists('bvmgr_social_get_settings')) {
	function bvmgr_social_get_settings(): array
	{
		$GLOBALS['vms_test_runtime_reads']['bvmgr_social_get_settings']++;
		return (array) $GLOBALS['vms_test_social_settings'];
	}
}

if (!function_exists('bvmgr_social_trim_preview')) {
	function bvmgr_social_trim_preview(string $text, int $limit = 180): string
	{
		return mb_substr($text, 0, $limit);
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
	$end = strpos($source, $endMarker);
	$assert($start !== false && $end !== false && $end > $start, 'Failed to isolate ' . $label . ' source.');
	return substr($source, (int) $start, (int) $end - (int) $start);
};

$parseFragment = static function (string $html, string $label) use ($assert): array {
	$previous = libxml_use_internal_errors(true);
	$document = new DOMDocument('1.0', 'UTF-8');
	$loaded = $document->loadHTML('<!DOCTYPE html><html><body><div id="vms-root">' . $html . '</div></body></html>');
	$errors = libxml_get_errors();
	libxml_clear_errors();
	libxml_use_internal_errors($previous);
	$assert($loaded, 'Failed to parse ' . $label . ' HTML fragment.');
	unset($errors);
	$xpath = new DOMXPath($document);
	$rootNode = $xpath->query('//*[@id="vms-root"]')->item(0);
	$assert($rootNode instanceof DOMElement, 'Failed to resolve wrapper root for ' . $label . '.');
	return array($document, $xpath, $rootNode);
};

$getDirectChildElements = static function (DOMElement $element): array {
	$children = array();
	foreach ($element->childNodes as $childNode) {
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
	$assert($actual === $expected, $label . ' should preserve the exact attribute contract.');
};

$dispatchAjax = static function (array $payload): array {
	$previousPost = $_POST ?? array();
	$previousRequest = $_REQUEST ?? array();
	$_POST = $payload;
	$_REQUEST = $payload;

	try {
		bvmgr_social_ajax_load_event_panel();
	} catch (VMS_Social_Event_Panel_Ajax_Exit $exit) {
		$_POST = $previousPost;
		$_REQUEST = $previousRequest;
		return $exit->response;
	} catch (Throwable $throwable) {
		$_POST = $previousPost;
		$_REQUEST = $previousRequest;
		throw $throwable;
	}

	$_POST = $previousPost;
	$_REQUEST = $previousRequest;
	throw new RuntimeException('Expected AJAX handler to terminate through wp_send_json_*().');
};

$pluginRoot = dirname(__DIR__);
$eventPanelPath = $pluginRoot . '/includes/social-share/event-plan-panel.php';
$socialAdminPath = $pluginRoot . '/includes/social-share/admin.php';

try {
	$eventPanelSource = $readFile($eventPanelPath);
	$socialAdminSource = $readFile($socialAdminPath);

	require_once $socialAdminPath;
	require_once $eventPanelPath;

	$assert(has_action('admin_enqueue_scripts', 'bvmgr_social_enqueue_admin_assets') === 30, 'Social Sharing should keep the admin_enqueue_scripts registration at priority 30.');
	$assert(has_action('add_meta_boxes_vms_event_plan', 'bvmgr_social_add_event_panel') === 10, 'Social Sharing should keep the Event Plan metabox registration hook.');
	$assert(has_action('admin_footer', 'bvmgr_social_event_panel_render_footer_forms') === 40, 'Social Sharing should keep the detached footer-form renderer registration at priority 40.');
	$assert(has_action('wp_ajax_vms_social_load_event_panel', 'bvmgr_social_ajax_load_event_panel') === 10, 'Social Sharing should keep the authenticated lazy-load AJAX registration.');

	$assert(strpos($socialAdminSource, 'wp_localize_script(') === false, 'Social Sharing admin enqueue should remain localization-free.');
	$assert(strpos($socialAdminSource, '$should_load = ($page === \'vms-social-sharing\') || ($post_type === \'vms_event_plan\');') !== false, 'Social Sharing admin enqueue should preserve the page/post-type load gate.');
	$assert(strpos($socialAdminSource, "'bvmgr-social-admin'") !== false, 'Social Sharing admin enqueue should use the canonical bvmgr-social-admin handle.');
	$assert(strpos($socialAdminSource, "BVMGR_PLUGIN_URL . 'assets/js/vms-social-admin.js'") !== false, 'Social Sharing admin enqueue should preserve the current script path.');

	$renderSource = $extractSource(
		$eventPanelSource,
		'function bvmgr_social_render_event_panel(WP_Post $post): void',
		"if (!function_exists('bvmgr_social_ajax_load_event_panel'))",
		'bvmgr_social_render_event_panel()'
	);
	$ajaxSource = $extractSource(
		$eventPanelSource,
		'function bvmgr_social_ajax_load_event_panel(): void',
		"if (!function_exists('bvmgr_social_save_event_panel'))",
		'bvmgr_social_ajax_load_event_panel()'
	);
	$markupSource = $extractSource(
		$eventPanelSource,
		'function bvmgr_social_event_panel_markup(int $event_plan_id): array',
		"if (!function_exists('bvmgr_social_add_event_panel'))",
		'bvmgr_social_event_panel_markup()'
	);
	$footerHtmlSource = $extractSource(
		$eventPanelSource,
		'function bvmgr_social_event_panel_footer_forms_html(int $event_plan_id, int $queue_id = 0): string',
		"if (!function_exists('bvmgr_social_event_panel_render_footer_forms'))",
		'bvmgr_social_event_panel_footer_forms_html()'
	);
	$footerRenderSource = $extractSource(
		$eventPanelSource,
		'function bvmgr_social_event_panel_render_footer_forms(): void',
		"if (!function_exists('bvmgr_social_event_panel_is_collapsed_for_user'))",
		'bvmgr_social_event_panel_render_footer_forms()'
	);

	$assert(substr_count($renderSource, 'bvmgr_social_event_panel_markup(') === 1, 'Synchronous panel render should resolve the event-panel markup producer exactly once.');
	$assert(strpos($renderSource, 'bvmgr_social_event_panel_register_footer_forms($event_plan_id, (int) ($payload[\'queue_id\'] ?? 0));') !== false, 'Synchronous panel render should preserve the detached footer-form registry handoff.');
	$assert(substr_count($ajaxSource, 'bvmgr_social_event_panel_markup(') === 1, 'AJAX lazy load should resolve the same event-panel markup producer exactly once.');
	$assert(strpos($ajaxSource, "'footer_forms_html' => bvmgr_social_event_panel_footer_forms_html(\$event_plan_id, (int) (\$payload['queue_id'] ?? 0)),") !== false, 'AJAX lazy load should preserve the footer_forms_html producer call.');
	$assert(strpos($ajaxSource, "wp_send_json_error(array('message' => 'Invalid Event Plan.'), 400);") !== false, 'AJAX lazy load should preserve the explicit invalid-plan response.');
	$assert(strpos($ajaxSource, "wp_send_json_error(array('message' => 'Not allowed.'), 403);") !== false, 'AJAX lazy load should preserve the explicit capability response.');
	$assert(strpos($ajaxSource, "check_ajax_referer('vms_social_load_event_panel', 'nonce');") !== false, 'AJAX lazy load should preserve the exact nonce action and request field.');
	$assert(strpos($ajaxSource, '$event_plan_id = absint(bvmgr_social_post_value(\'post_id\'));') !== false, 'AJAX lazy load should continue to normalize post_id through bvmgr_social_post_value() and absint().');

	foreach (array(
		'bvmgr_social_event_plan_context(',
		'bvmgr_social_event_meta_enabled_platforms(',
		'bvmgr_social_event_meta_template_overrides(',
		'get_post_meta(',
		'bvmgr_social_queue_latest_for_event(',
		'bvmgr_social_templates_all(',
		'bvmgr_social_template_default_for_platform(',
		'bvmgr_social_template_for_platform(',
		'bvmgr_social_render_template_payload(',
		'bvmgr_social_get_settings(',
		'bvmgr_social_event_share_url(',
		'wp_nonce_field(',
	) as $requiredReadMarker) {
		$assert(strpos($markupSource, $requiredReadMarker) !== false, 'Event-panel markup should preserve the external-read marker: ' . $requiredReadMarker);
	}
	$assert(strpos($renderSource, 'wp_create_nonce(\'vms_social_load_event_panel\')') !== false, 'Collapsed shell render should preserve the lazy-load nonce generation.');
	$assert(strpos($renderSource, 'admin_url(\'admin-ajax.php\')') !== false, 'Collapsed shell render should preserve the admin-ajax URL read.');
	$assert(strpos($footerHtmlSource, "wp_nonce_field('vms_social_event_queue', '_wpnonce', false);") !== false, 'Detached queue form should preserve the exact queue nonce field contract.');
	$assert(strpos($footerHtmlSource, "wp_nonce_field('vms_social_queue_cancel', '_wpnonce', false);") !== false, 'Detached cancel form should preserve the exact cancel nonce field contract.');
	$assert(strpos($footerHtmlSource, "wp_nonce_field('vms_social_queue_retry', '_wpnonce', false);") !== false, 'Detached retry form should preserve the exact retry nonce field contract.');

	foreach (array($renderSource, $ajaxSource, $markupSource, $footerHtmlSource, $footerRenderSource) as $loadPathSource) {
		foreach (array(
			'update_post_meta(',
			'delete_post_meta(',
			'update_option(',
			'delete_option(',
			'wp_insert_post(',
			'wp_update_post(',
		) as $forbiddenMutationMarker) {
			$assert(strpos($loadPathSource, $forbiddenMutationMarker) === false, 'Load-path characterization should stay read-only for business-state mutations: ' . $forbiddenMutationMarker);
		}
	}

	$GLOBALS['vms_test_meta_boxes'] = array();
	bvmgr_social_add_event_panel();
	$assert(count($GLOBALS['vms_test_meta_boxes']) === 1, 'Social Sharing should register exactly one Event Plan metabox.');
	$metaBox = $GLOBALS['vms_test_meta_boxes'][0];
	$assert($metaBox['id'] === 'vms_social_promotion', 'Social Sharing metabox ID should remain vms_social_promotion.');
	$assert($metaBox['title'] === 'Promotion (Social Sharing)', 'Social Sharing metabox title should remain unchanged.');
	$assert($metaBox['callback'] === 'bvmgr_social_render_event_panel', 'Social Sharing metabox callback should remain bvmgr_social_render_event_panel.');
	$assert($metaBox['screen'] === 'vms_event_plan', 'Social Sharing metabox should stay on the Event Plan edit screen.');
	$assert($metaBox['context'] === 'normal', 'Social Sharing metabox context should remain normal.');
	$assert($metaBox['priority'] === 'high', 'Social Sharing metabox priority should remain high.');

	$GLOBALS['vms_test_scripts'] = array();
	$_GET = array('page' => 'vms-social-sharing');
	$GLOBALS['vms_test_current_screen'] = (object) array('id' => 'social_page_vms-social-sharing', 'post_type' => '');
	bvmgr_social_enqueue_admin_assets();
	$assert(count($GLOBALS['vms_test_scripts']) === 1, 'Social Sharing admin page should enqueue the shared social admin script exactly once.');
	$assert($GLOBALS['vms_test_scripts'][0] === array(
		'handle' => 'bvmgr-social-admin',
		'src' => BVMGR_PLUGIN_URL . 'assets/js/vms-social-admin.js',
		'deps' => array(),
		'ver' => BVMGR_VERSION,
		'in_footer' => true,
	), 'Social Sharing admin page should preserve the exact script enqueue contract.');

	$GLOBALS['vms_test_scripts'] = array();
	$_GET = array();
	$GLOBALS['vms_test_current_screen'] = (object) array('id' => 'post', 'post_type' => 'vms_event_plan');
	bvmgr_social_enqueue_admin_assets();
	$assert(count($GLOBALS['vms_test_scripts']) === 1, 'Event Plan edit screens should also enqueue the shared social admin script.');

	$GLOBALS['vms_test_scripts'] = array();
	$_GET = array();
	$GLOBALS['vms_test_current_screen'] = (object) array('id' => 'dashboard', 'post_type' => 'post');
	bvmgr_social_enqueue_admin_assets();
	$assert($GLOBALS['vms_test_scripts'] === array(), 'Unrelated admin screens should not enqueue the Social Sharing admin script.');
	$assert($GLOBALS['vms_test_localized_scripts'] === array(), 'Social Sharing enqueue path should not localize the admin script.');

	$GLOBALS['bvmgr_social_event_panel_footer_forms'] = array();
	$GLOBALS['vms_test_current_screen'] = (object) array('id' => 'post', 'post_type' => 'vms_event_plan');
	$GLOBALS['vms_test_user_options'] = array(
		'closedpostboxes_post' => array('vms_social_promotion'),
	);
	ob_start();
	bvmgr_social_render_event_panel(new WP_Post(42, 'vms_event_plan'));
	$collapsedShellHtml = (string) ob_get_clean();
	$assert($GLOBALS['bvmgr_social_event_panel_footer_forms'] === array(), 'Collapsed lazy shell render should not register detached footer forms before the panel loads.');

	list($collapsedDoc, $collapsedXPath, $collapsedRoot) = $parseFragment($collapsedShellHtml, 'collapsed social shell');
	unset($collapsedDoc);
	$collapsedChildren = $getDirectChildElements($collapsedRoot);
	$assert(count($collapsedChildren) === 1, 'Collapsed Social Sharing shell should render exactly one outer element.');
	$collapsedShell = $collapsedChildren[0];
	$assert($collapsedShell->tagName === 'div', 'Collapsed Social Sharing shell should render as a div.');
	$assertAttributesExact($collapsedShell, array(
		'class' => 'vms-social-event-panel-shell',
		'data-vms-social-lazy' => '1',
		'data-vms-social-nonce' => 'nonce:vms_social_load_event_panel',
		'data-vms-social-post-id' => '42',
		'data-vms-social-url' => 'https://example.test/wp-admin/admin-ajax.php',
	), 'Collapsed Social Sharing shell');
	$collapsedParagraphs = $collapsedXPath->query('//*[@id="vms-root"]/div/p');
	$assert($collapsedParagraphs instanceof DOMNodeList && $collapsedParagraphs->length === 1, 'Collapsed Social Sharing shell should contain exactly one description paragraph.');
	$collapsedParagraph = $collapsedParagraphs->item(0);
	$assert($collapsedParagraph instanceof DOMElement && $collapsedParagraph->getAttribute('class') === 'description', 'Collapsed Social Sharing shell description should preserve the description class.');
	$assert($collapsedParagraph instanceof DOMElement && trim((string) $collapsedParagraph->textContent) === 'Open this panel to load social templates, previews, and queue actions.', 'Collapsed Social Sharing shell description should preserve the exact current copy.');
	$assert($collapsedXPath->query('//*[@id="vms-root"]/div//@*[name()!="class" and name()!="data-vms-social-lazy" and name()!="data-vms-social-post-id" and name()!="data-vms-social-url" and name()!="data-vms-social-nonce"]')->length === 0, 'Collapsed Social Sharing shell should not introduce uncharacterized attributes.');

	$GLOBALS['vms_test_user_options'] = array();
	$GLOBALS['bvmgr_social_event_panel_footer_forms'] = array();
	foreach (array_keys($GLOBALS['vms_test_mutation_calls']) as $mutationKey) {
		$GLOBALS['vms_test_mutation_calls'][$mutationKey] = 0;
	}
	$payload = bvmgr_social_event_panel_markup(42);
	$assert(array_keys($payload) === array('html', 'queue_id'), 'Event-panel markup producer should preserve the exact payload keys.');
	$assert((int) $payload['queue_id'] === 314, 'Event-panel markup producer should preserve the latest queue ID.');

	ob_start();
	bvmgr_social_render_event_panel(new WP_Post(42, 'vms_event_plan'));
	$synchronousHtml = (string) ob_get_clean();
	$assert($synchronousHtml === (string) $payload['html'], 'Synchronous panel render should echo the exact shared event-panel markup producer output.');
	$assert(isset($GLOBALS['bvmgr_social_event_panel_footer_forms'][42]), 'Synchronous panel render should register detached footer forms for the current Event Plan.');
	$assert($GLOBALS['bvmgr_social_event_panel_footer_forms'][42] === array(
		'event_plan_id' => 42,
		'queue_id' => 314,
	), 'Synchronous panel render should preserve the request-local footer-form registry payload.');

	$footerFormsHtmlNoQueue = bvmgr_social_event_panel_footer_forms_html(42, 0);
	$footerFormsHtml = bvmgr_social_event_panel_footer_forms_html(42, 314);
	ob_start();
	bvmgr_social_event_panel_render_footer_forms();
	$registeredFooterFormsHtml = (string) ob_get_clean();
	$assert($registeredFooterFormsHtml === $footerFormsHtml, 'Detached footer-form renderer should emit the exact shared footer-form producer output for the registered Event Plan.');

	list($mainDoc, $mainXPath, $mainRoot) = $parseFragment((string) $payload['html'], 'social event-panel markup');
	unset($mainDoc);
	$topLevelChildren = $getDirectChildElements($mainRoot);
	$assert(array_map(static fn (DOMElement $element): string => $element->tagName, $topLevelChildren) === array('input', 'input', 'p', 'p', 'div', 'div', 'hr', 'h4', 'p', 'p', 'div', 'div'), 'Social event-panel markup should preserve the exact current top-level element order.');

	$assertAttributesExact($topLevelChildren[0], array(
		'id' => 'vms_social_event_panel_nonce',
		'name' => 'vms_social_event_panel_nonce',
		'type' => 'hidden',
		'value' => 'nonce:vms_social_event_panel_save',
	), 'Social event-panel nonce input');
	$assertAttributesExact($topLevelChildren[1], array(
		'name' => '_wp_http_referer',
		'type' => 'hidden',
		'value' => '/wp-admin/post.php?post=42&action=edit',
	), 'Social event-panel referer input');
	$assertAttributesExact($topLevelChildren[2], array('class' => 'description'), 'Social event-panel lead description');
	$assert(trim((string) $topLevelChildren[2]->textContent) === 'Phase 1 manual toolkit: copy caption/link and open share dialogs. Queue actions currently use the Phase 0 provider framework.', 'Social event-panel lead description should preserve the exact current copy.');
	$assert(trim((string) $topLevelChildren[3]->textContent) === 'Do not post this event', 'Social event-panel checkbox paragraph should preserve the exact current copy.');

	$doNotPostCheckbox = $mainXPath->query('//*[@id="vms-root"]/p[2]/label/input')->item(0);
	$assert($doNotPostCheckbox instanceof DOMElement, 'Social event-panel should render the do-not-post checkbox.');
	$assertAttributesExact($doNotPostCheckbox, array(
		'checked' => 'checked',
		'name' => 'vms_social_do_not_post',
		'type' => 'checkbox',
		'value' => '1',
	), 'Social event-panel do-not-post checkbox');

	$warningNotice = $topLevelChildren[4];
	$assert($warningNotice->tagName === 'div', 'Social event-panel unpublished warning should render as a div.');
	$assertAttributesExact($warningNotice, array('class' => 'notice notice-warning inline'), 'Social event-panel unpublished warning');
	$warningParagraphs = $warningNotice->getElementsByTagName('p');
	$assert($warningParagraphs->length === 1, 'Social event-panel unpublished warning should preserve exactly one paragraph.');
	$warningStrongNodes = $warningNotice->getElementsByTagName('strong');
	$assert($warningStrongNodes->length === 1 && trim((string) $warningStrongNodes->item(0)->textContent) === 'Unpublished after social post', 'Social event-panel unpublished warning should preserve the strong heading.');
	$assert(trim((string) $warningNotice->textContent) === 'Unpublished after social post This event is now Draft but has at least one previously posted social queue item.', 'Social event-panel unpublished warning should preserve the exact current copy.');

	$platformGrid = $topLevelChildren[5];
	$assertAttributesExact($platformGrid, array('class' => 'vms-social-platform-grid'), 'Social event-panel platform grid');
	$platformSections = $platformGrid->getElementsByTagName('section');
	$assert($platformSections->length === 3, 'Social event-panel platform grid should render exactly three current platform cards.');

	$expectedEnabled = array(
		'facebook' => true,
		'linkedin' => false,
		'x' => true,
	);
	$expectedSelectedTemplateIds = array(
		'facebook' => '101',
		'linkedin' => '0',
		'x' => '303',
	);
	$expectedTemplateOptions = array(
		'facebook' => array('0', '101', '111'),
		'linkedin' => array('0', '202', '212'),
		'x' => array('0', '303', '313'),
	);
	foreach (array('facebook', 'linkedin', 'x') as $index => $platform) {
		$section = $platformSections->item($index);
		$assert($section instanceof DOMElement, 'Expected a section element for ' . $platform . '.');
		$assertAttributesExact($section, array('class' => 'vms-social-platform-card'), 'Platform card for ' . $platform);
		$sectionChildren = $getDirectChildElements($section);
		$assert(array_map(static fn (DOMElement $element): string => $element->tagName, $sectionChildren) === array('h4', 'p', 'p', 'div', 'p'), 'Platform card for ' . $platform . ' should preserve the exact child element order.');
		$assert(trim((string) $sectionChildren[0]->textContent) === ucfirst($platform), 'Platform heading should preserve the exact current label for ' . $platform . '.');

		$enabledInput = $mainXPath->query(sprintf('//*[@id="vms-root"]//input[@name="vms_social_enabled[%s]"]', $platform))->item(0);
		$assert($enabledInput instanceof DOMElement, 'Platform card should render the enabled checkbox for ' . $platform . '.');
		$expectedEnabledAttributes = array(
			'name' => 'vms_social_enabled[' . $platform . ']',
			'type' => 'checkbox',
			'value' => '1',
		);
		if ($expectedEnabled[$platform]) {
			$expectedEnabledAttributes['checked'] = 'checked';
		}
		$assertAttributesExact($enabledInput, $expectedEnabledAttributes, 'Enabled checkbox for ' . $platform);

		$templateSelect = $mainXPath->query(sprintf('//*[@id="vms-root"]//select[@name="vms_social_template[%s]"]', $platform))->item(0);
		$assert($templateSelect instanceof DOMElement, 'Platform card should render the template select for ' . $platform . '.');
		$assertAttributesExact($templateSelect, array('name' => 'vms_social_template[' . $platform . ']'), 'Template select for ' . $platform);
		$templateOptions = $templateSelect->getElementsByTagName('option');
		$assert($templateOptions->length === 3, 'Template select for ' . $platform . ' should preserve the default plus two configured template options.');
		$optionValues = array();
		$selectedOptionValue = '';
		foreach ($templateOptions as $optionNode) {
			$assert($optionNode instanceof DOMElement, 'Expected option nodes for the template select.');
			$optionValues[] = $optionNode->getAttribute('value');
			if ($optionNode->hasAttribute('selected')) {
				$selectedOptionValue = $optionNode->getAttribute('value');
			}
		}
		$assert($optionValues === $expectedTemplateOptions[$platform], 'Template option values for ' . $platform . ' should preserve the exact current inventory.');
		$assert($selectedOptionValue === $expectedSelectedTemplateIds[$platform], 'Template select for ' . $platform . ' should preserve the current selected/default contract.');

		$toolbox = $sectionChildren[3];
		$assertAttributesExact($toolbox, array('class' => 'vms-social-manual-tools'), 'Manual-tools wrapper for ' . $platform);
		$toolChildren = $getDirectChildElements($toolbox);
		$assert(array_map(static fn (DOMElement $element): string => $element->tagName, $toolChildren) === array('button', 'button', 'a'), 'Manual-tools wrapper for ' . $platform . ' should preserve the current button/link layout.');
		$copyCaptionButton = $toolChildren[0];
		$copyLinkButton = $toolChildren[1];
		$shareAnchor = $toolChildren[2];
		$expectedRendered = $GLOBALS['vms_test_social_rendered_payloads'][$platform];
		$assertAttributesExact($copyCaptionButton, array(
			'class' => 'button vms-social-copy-btn',
			'data-copy-text' => $expectedRendered['caption'],
			'type' => 'button',
		), 'Copy Caption button for ' . $platform);
		$assert(trim((string) $copyCaptionButton->textContent) === 'Copy Caption', 'Copy Caption button label should remain unchanged for ' . $platform . '.');
		$assertAttributesExact($copyLinkButton, array(
			'class' => 'button vms-social-copy-btn',
			'data-copy-text' => $expectedRendered['final_url'],
			'type' => 'button',
		), 'Copy Link button for ' . $platform);
		$assert(trim((string) $copyLinkButton->textContent) === 'Copy Link', 'Copy Link button label should remain unchanged for ' . $platform . '.');
		$assertAttributesExact($shareAnchor, array(
			'class' => 'button button-secondary',
			'href' => bvmgr_social_event_share_url($platform, $expectedRendered['final_url'], $expectedRendered['caption']),
			'rel' => 'noopener',
			'target' => '_blank',
		), 'Open Share Dialog link for ' . $platform);
		$assert(trim((string) $shareAnchor->textContent) === 'Open Share Dialog', 'Share-dialog link label should remain unchanged for ' . $platform . '.');

		$previewParagraph = $sectionChildren[4];
		$assertAttributesExact($previewParagraph, array('class' => 'description'), 'Preview paragraph for ' . $platform);
		$previewStrongNodes = $previewParagraph->getElementsByTagName('strong');
		$assert($previewStrongNodes->length === 1 && trim((string) $previewStrongNodes->item(0)->textContent) === 'Preview:', 'Preview paragraph for ' . $platform . ' should preserve the strong label.');
		$assert(trim((string) $previewParagraph->textContent) === 'Preview: ' . $expectedRendered['caption'], 'Preview paragraph for ' . $platform . ' should preserve the trimmed caption text.');
	}

	$assert($topLevelChildren[6]->tagName === 'hr' && !$topLevelChildren[6]->hasAttributes(), 'Social event-panel should preserve the plain horizontal rule before queue actions.');
	$assert($topLevelChildren[7]->tagName === 'h4' && trim((string) $topLevelChildren[7]->textContent) === 'Queue Actions', 'Social event-panel queue heading should preserve the exact current copy.');
	$assert($topLevelChildren[8]->tagName === 'p' && trim((string) $topLevelChildren[8]->textContent) === 'Latest queue item: #314 | failed | linkedin', 'Social event-panel queue summary should preserve the exact current summary text.');
	$assertAttributesExact($topLevelChildren[9], array('class' => 'description'), 'Social event-panel latest queue error paragraph');
	$assert(trim((string) $topLevelChildren[9]->textContent) === 'Rate limit <strong>retry</strong>', 'Social event-panel latest queue error paragraph should escape stored error markup.');

	$queueFormId = bvmgr_social_event_panel_form_id(42, 'event-queue');
	$queueCancelFormId = bvmgr_social_event_panel_form_id(42, 'queue-cancel');
	$queueRetryFormId = bvmgr_social_event_panel_form_id(42, 'queue-retry');
	$assert($queueFormId === 'vms-social-event-queue-form-42', 'Queue form ID derivation should preserve the exact current naming scheme.');
	$assert($queueCancelFormId === 'vms-social-queue-cancel-form-42', 'Cancel form ID derivation should preserve the exact current naming scheme.');
	$assert($queueRetryFormId === 'vms-social-queue-retry-form-42', 'Retry form ID derivation should preserve the exact current naming scheme.');

	$queueFormWrapper = $topLevelChildren[10];
	$assertAttributesExact($queueFormWrapper, array('class' => 'vms-social-event-queue-form'), 'Queue form wrapper');
	$queueFormChildren = $getDirectChildElements($queueFormWrapper);
	$assert(array_map(static fn (DOMElement $element): string => $element->tagName, $queueFormChildren) === array('p', 'p', 'p', 'p', 'p'), 'Queue form wrapper should preserve the exact current paragraph layout.');
	$queuePlatformSelect = $mainXPath->query('//*[@id="vms-root"]//select[@name="platform"]')->item(0);
	$assert($queuePlatformSelect instanceof DOMElement, 'Queue form should render the queue platform select.');
	$assertAttributesExact($queuePlatformSelect, array(
		'form' => $queueFormId,
		'name' => 'platform',
	), 'Queue platform select');
	$queuePlatformValues = array();
	foreach ($queuePlatformSelect->getElementsByTagName('option') as $optionNode) {
		$assert($optionNode instanceof DOMElement, 'Queue platform select should render option elements.');
		$queuePlatformValues[] = $optionNode->getAttribute('value');
	}
	$assert($queuePlatformValues === array('mock', 'webhook', 'facebook', 'linkedin', 'x'), 'Queue platform select should preserve the exact current platform options.');

	$templateIdInput = $mainXPath->query('//*[@id="vms-root"]//input[@name="template_id"]')->item(0);
	$destinationIdInput = $mainXPath->query('//*[@id="vms-root"]//input[@name="destination_id"]')->item(0);
	$scheduledAtInput = $mainXPath->query('//*[@id="vms-root"]//input[@name="scheduled_at_local"]')->item(0);
	$queueSubmitButton = $mainXPath->query('//*[@id="vms-root"]//button[@form="' . $queueFormId . '" and @type="submit"]')->item(0);
	$assert($templateIdInput instanceof DOMElement, 'Queue form should render the template_id input.');
	$assert($destinationIdInput instanceof DOMElement, 'Queue form should render the destination_id input.');
	$assert($scheduledAtInput instanceof DOMElement, 'Queue form should render the scheduled_at_local input.');
	$assert($queueSubmitButton instanceof DOMElement, 'Queue form should render the queue submit button.');
	$assertAttributesExact($templateIdInput, array(
		'form' => $queueFormId,
		'min' => '0',
		'name' => 'template_id',
		'step' => '1',
		'type' => 'number',
		'value' => '0',
	), 'Queue template_id input');
	$assertAttributesExact($destinationIdInput, array(
		'class' => 'regular-text',
		'form' => $queueFormId,
		'name' => 'destination_id',
		'type' => 'text',
		'value' => '',
	), 'Queue destination_id input');
	$assertAttributesExact($scheduledAtInput, array(
		'form' => $queueFormId,
		'name' => 'scheduled_at_local',
		'type' => 'datetime-local',
		'value' => '',
	), 'Queue scheduled_at_local input');
	$assertAttributesExact($queueSubmitButton, array(
		'class' => 'button button-primary',
		'form' => $queueFormId,
		'type' => 'submit',
	), 'Queue submit button');
	$assert(trim((string) $queueSubmitButton->textContent) === 'Queue / Schedule', 'Queue submit button should preserve the exact current copy.');

	$queueOps = $topLevelChildren[11];
	$assertAttributesExact($queueOps, array('class' => 'vms-social-event-queue-ops'), 'Queue operations wrapper');
	$queueOpsChildren = $getDirectChildElements($queueOps);
	$assert(count($queueOpsChildren) === 2, 'Queue operations wrapper should preserve exactly two visible submit buttons.');
	$assertAttributesExact($queueOpsChildren[0], array(
		'class' => 'button',
		'form' => $queueCancelFormId,
		'type' => 'submit',
	), 'Cancel Latest button');
	$assert(trim((string) $queueOpsChildren[0]->textContent) === 'Cancel Latest', 'Cancel Latest button label should remain unchanged.');
	$assertAttributesExact($queueOpsChildren[1], array(
		'class' => 'button button-secondary',
		'form' => $queueRetryFormId,
		'type' => 'submit',
	), 'Retry Latest button');
	$assert(trim((string) $queueOpsChildren[1]->textContent) === 'Retry Latest', 'Retry Latest button label should remain unchanged.');

	$allowedMainTags = array(
		'div' => array('class'),
		'h4' => array(),
		'hr' => array(),
		'input' => array('checked', 'class', 'form', 'id', 'min', 'name', 'step', 'type', 'value'),
		'label' => array(),
		'option' => array('selected', 'value'),
		'p' => array('class'),
		'section' => array('class'),
		'select' => array('form', 'name'),
		'strong' => array(),
		'button' => array('class', 'data-copy-text', 'form', 'type'),
		'a' => array('class', 'href', 'rel', 'target'),
	);
	$mainNodes = $mainXPath->query('//*[@id="vms-root"]//*');
	$assert($mainNodes instanceof DOMNodeList, 'Expected a DOMNodeList for main event-panel markup inspection.');
	foreach ($mainNodes as $node) {
		if (!$node instanceof DOMElement || $node->getAttribute('id') === 'vms-root') {
			continue;
		}
		$assert(isset($allowedMainTags[$node->tagName]), 'Main event-panel markup should not introduce the tag <' . $node->tagName . '>.');
		$allowedAttributes = $allowedMainTags[$node->tagName];
		foreach ($node->attributes as $attribute) {
			$assert(in_array($attribute->name, $allowedAttributes, true), 'Main event-panel markup should not introduce the attribute ' . $attribute->name . ' on <' . $node->tagName . '>.');
		}
	}
	$assert($mainXPath->query('//*[@id="vms-root"]//script | //*[@id="vms-root"]//style | //*[@id="vms-root"]//form | //*[@id="vms-root"]//span | //*[@id="vms-root"]//img | //*[@id="vms-root"]//svg')->length === 0, 'Main event-panel markup should stay within the current tag family and should not render script/style/form/span/img/svg nodes.');

	list($footerDocNoQueue, $footerXPathNoQueue, $footerRootNoQueue) = $parseFragment($footerFormsHtmlNoQueue, 'queue-only detached footer forms');
	unset($footerDocNoQueue);
	$queueOnlyForms = $getDirectChildElements($footerRootNoQueue);
	$assert(count($queueOnlyForms) === 1, 'Detached footer-form producer should render exactly one form when no queue item exists yet.');
	$assertAttributesExact($queueOnlyForms[0], array(
		'action' => 'https://example.test/wp-admin/admin-post.php',
		'class' => 'vms-social-detached-form',
		'id' => $queueFormId,
		'method' => 'post',
		'style' => 'display:none;',
	), 'Queue-only detached form');

	list($footerDoc, $footerXPath, $footerRoot) = $parseFragment($footerFormsHtml, 'detached footer forms');
	unset($footerDoc);
	$footerForms = $getDirectChildElements($footerRoot);
	$assert(array_map(static fn (DOMElement $element): string => $element->getAttribute('id'), $footerForms) === array($queueFormId, $queueCancelFormId, $queueRetryFormId), 'Detached footer-form producer should preserve the exact queue/cancel/retry form order.');
	foreach ($footerForms as $form) {
		$assert($form->tagName === 'form', 'Detached footer-form producer should render only form nodes at the top level.');
		$assertAttributesExact($form, array(
			'action' => 'https://example.test/wp-admin/admin-post.php',
			'class' => 'vms-social-detached-form',
			'id' => $form->getAttribute('id'),
			'method' => 'post',
			'style' => 'display:none;',
		), 'Detached form ' . $form->getAttribute('id'));
	}

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

	$assert($getHiddenInputMap($footerForms[0]) === array(
		array('name' => '_wpnonce', 'type' => 'hidden', 'value' => 'nonce:vms_social_event_queue'),
		array('name' => 'action', 'type' => 'hidden', 'value' => 'vms_social_event_queue'),
		array('name' => 'event_plan_id', 'type' => 'hidden', 'value' => '42'),
	), 'Queue detached form should preserve the exact hidden-field contract.');
	$assert($getHiddenInputMap($footerForms[1]) === array(
	array('name' => '_wpnonce', 'type' => 'hidden', 'value' => 'nonce:vms_social_queue_cancel'),
	array('name' => 'action', 'type' => 'hidden', 'value' => 'vms_social_queue_cancel'),
		array('name' => 'queue_id', 'type' => 'hidden', 'value' => '314'),
		array('name' => 'event_plan_id', 'type' => 'hidden', 'value' => '42'),
		array('name' => 'tab', 'type' => 'hidden', 'value' => 'queue'),
	), 'Cancel detached form should preserve the exact hidden-field contract.');
	$assert($getHiddenInputMap($footerForms[2]) === array(
	array('name' => '_wpnonce', 'type' => 'hidden', 'value' => 'nonce:vms_social_queue_retry'),
	array('name' => 'action', 'type' => 'hidden', 'value' => 'vms_social_queue_retry'),
		array('name' => 'queue_id', 'type' => 'hidden', 'value' => '314'),
		array('name' => 'event_plan_id', 'type' => 'hidden', 'value' => '42'),
		array('name' => 'tab', 'type' => 'hidden', 'value' => 'queue'),
	), 'Retry detached form should preserve the exact hidden-field contract.');
	$assert($footerXPath->query('//*[@id="vms-root"]//*[not(self::form or self::input)]')->length === 0, 'Detached footer-form producer should not introduce non-form, non-input nodes.');

	foreach (array_keys($GLOBALS['vms_test_mutation_calls']) as $mutationKey) {
		$assert($GLOBALS['vms_test_mutation_calls'][$mutationKey] === 0, 'Social event-panel load paths should not mutate business state through ' . $mutationKey . '.');
	}

	$GLOBALS['vms_test_ajax_referer_calls'] = array();
	$GLOBALS['vms_test_current_user_can'] = true;
	$GLOBALS['vms_test_social_manage'] = true;
	$successResponse = $dispatchAjax(array(
		'post_id' => '42',
		'nonce' => 'nonce:vms_social_load_event_panel',
	));
	$assert($successResponse['success'] === true, 'Social event-panel AJAX success response should stay successful for valid requests.');
	$assert((int) $successResponse['status_code'] === 200, 'Social event-panel AJAX success response should preserve the default 200 status.');
	$assert(array_keys((array) $successResponse['data']) === array('html', 'footer_forms_html'), 'Social event-panel AJAX success payload should preserve the exact html/footer_forms_html key set.');
	$assert((string) $successResponse['data']['html'] === (string) $payload['html'], 'Social event-panel AJAX success should return the exact shared event-panel markup HTML.');
	$assert((string) $successResponse['data']['footer_forms_html'] === $footerFormsHtml, 'Social event-panel AJAX success should return the exact shared detached footer-form HTML.');
	$assert($GLOBALS['vms_test_ajax_referer_calls'] === array(
		array(
			'action' => 'vms_social_load_event_panel',
			'query_arg' => 'nonce',
			'value' => 'nonce:vms_social_load_event_panel',
		),
	), 'Social event-panel AJAX success should preserve the exact nonce lifecycle.');

	$GLOBALS['vms_test_ajax_referer_calls'] = array();
	$invalidPlanResponse = $dispatchAjax(array(
		'post_id' => '999',
		'nonce' => 'nonce:vms_social_load_event_panel',
	));
	$assert($invalidPlanResponse['success'] === false, 'Social event-panel AJAX invalid-plan response should remain an error.');
	$assert((int) $invalidPlanResponse['status_code'] === 400, 'Social event-panel AJAX invalid-plan response should preserve the exact 400 status.');
	$assert(array_keys((array) $invalidPlanResponse['data']) === array('message'), 'Social event-panel AJAX invalid-plan response should preserve the exact message-only payload.');
	$assert((string) $invalidPlanResponse['data']['message'] === 'Invalid Event Plan.', 'Social event-panel AJAX invalid-plan response should preserve the exact error message.');
	$assert($GLOBALS['vms_test_ajax_referer_calls'] === array(), 'Social event-panel AJAX invalid-plan rejection should happen before the nonce check.');

	$GLOBALS['vms_test_ajax_referer_calls'] = array();
	$GLOBALS['vms_test_current_user_can'] = false;
	$GLOBALS['vms_test_social_manage'] = true;
	$notAllowedResponse = $dispatchAjax(array(
		'post_id' => '42',
		'nonce' => 'nonce:vms_social_load_event_panel',
	));
	$assert($notAllowedResponse['success'] === false, 'Social event-panel AJAX capability response should remain an error.');
	$assert((int) $notAllowedResponse['status_code'] === 403, 'Social event-panel AJAX capability response should preserve the exact 403 status.');
	$assert(array_keys((array) $notAllowedResponse['data']) === array('message'), 'Social event-panel AJAX capability response should preserve the exact message-only payload.');
	$assert((string) $notAllowedResponse['data']['message'] === 'Not allowed.', 'Social event-panel AJAX capability response should preserve the exact error message.');
	$assert($GLOBALS['vms_test_ajax_referer_calls'] === array(), 'Social event-panel AJAX capability rejection should happen before the nonce check.');

	fwrite(STDOUT, "social event-panel lazy-load output characterization: PASS\n");
} catch (Throwable $throwable) {
	fwrite(STDERR, 'social event-panel lazy-load output characterization: FAIL - ' . $throwable->getMessage() . "\n");
	exit(1);
}
