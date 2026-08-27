<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('MINUTE_IN_SECONDS')) {
	define('MINUTE_IN_SECONDS', 60);
}

if (!class_exists('WP_Error')) {
	class WP_Error
	{
		/** @var string */
		private $error_code;

		/** @var string */
		private $error_message;

		public function __construct(string $code = '', string $message = '')
		{
			$this->error_code = $code;
			$this->error_message = $message;
		}

		public function get_error_code(): string
		{
			return $this->error_code;
		}

		public function get_error_message(): string
		{
			return $this->error_message;
		}
	}
}

if (!function_exists('add_action')) {
	function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
	{
		$GLOBALS['vms_test_rows_add_action_calls'][] = array($hook, $callback, $priority, $accepted_args);
		return true;
	}
}

if (!function_exists('add_submenu_page')) {
	function add_submenu_page($parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, $callback = ''): string
	{
		$GLOBALS['vms_test_rows_add_submenu_calls'][] = array($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback);
		return 'vms-import-event-plans';
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

if (!function_exists('sanitize_html_class')) {
	function sanitize_html_class($class): string
	{
		$sanitized = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $class);
		return is_string($sanitized) ? trim($sanitized, '-') : '';
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
		return add_query_arg(array('_wpnonce' => sanitize_key($action)), $url);
	}
}

if (!function_exists('wp_nonce_field')) {
	function wp_nonce_field(string $action = '', string $name = '_wpnonce', bool $referer = true, bool $display = true): string
	{
		unset($action, $referer);
		$field = '<input type="hidden" name="' . esc_attr($name) . '" value="nonce" />';
		if ($display) {
			echo $field;
		}

		return $field;
	}
}

if (!function_exists('wp_json_encode')) {
	function wp_json_encode($value): string
	{
		$json = json_encode($value);
		return is_string($json) ? $json : '';
	}
}

if (!function_exists('wp_kses')) {
	function wp_kses($html, $allowed_html, $allowed_protocols = array()): string
	{
		unset($allowed_html, $allowed_protocols);
		return (string) $html;
	}
}

if (!function_exists('trailingslashit')) {
	function trailingslashit(string $path): string
	{
		return rtrim($path, "/\\") . '/';
	}
}

if (!function_exists('wp_normalize_path')) {
	function wp_normalize_path(string $path): string
	{
		return str_replace('\\', '/', $path);
	}
}

if (!function_exists('current_user_can')) {
	function current_user_can(string $capability): bool
	{
		unset($capability);
		return !empty($GLOBALS['vms_test_rows_current_user_can']);
	}
}

if (!function_exists('wp_die')) {
	function wp_die($message = ''): void
	{
		throw new RuntimeException((string) $message);
	}
}

if (!function_exists('get_current_user_id')) {
	function get_current_user_id(): int
	{
		return (int) ($GLOBALS['vms_test_rows_current_user_id'] ?? 0);
	}
}

if (!function_exists('get_transient')) {
	function get_transient(string $name)
	{
		$GLOBALS['vms_test_rows_get_transient_calls']++;
		$GLOBALS['vms_test_rows_get_transient_keys'][] = $name;
		return $GLOBALS['vms_test_rows_transients'][$name] ?? false;
	}
}

if (!function_exists('set_transient')) {
	function set_transient(string $name, $value, int $expiration = 0): bool
	{
		$GLOBALS['vms_test_rows_set_transient_calls']++;
		$GLOBALS['vms_test_rows_set_transient_payloads'][] = array($name, $value, $expiration);
		$GLOBALS['vms_test_rows_transients'][$name] = $value;
		return true;
	}
}

if (!function_exists('delete_transient')) {
	function delete_transient(string $name): bool
	{
		$GLOBALS['vms_test_rows_delete_transient_calls']++;
		$GLOBALS['vms_test_rows_delete_transient_keys'][] = $name;
		unset($GLOBALS['vms_test_rows_transients'][$name]);
		return true;
	}
}

if (!function_exists('get_option')) {
	function get_option(string $option, $default = false)
	{
		return array_key_exists($option, $GLOBALS['vms_test_rows_options']) ? $GLOBALS['vms_test_rows_options'][$option] : $default;
	}
}

if (!function_exists('is_wp_error')) {
	function is_wp_error($thing): bool
	{
		return $thing instanceof WP_Error;
	}
}

if (!function_exists('bvmgr_array_is_list_compat')) {
	function bvmgr_array_is_list_compat(array $value): bool
	{
		$index = 0;
		foreach ($value as $key => $_unused) {
			if ($key !== $index) {
				return false;
			}
			$index++;
		}

		return true;
	}
}

if (!function_exists('bvmgr_json_top_level_token')) {
	function bvmgr_json_top_level_token(string $raw): string
	{
		$raw = ltrim($raw);
		if ($raw === '') {
			return '';
		}

		return substr($raw, 0, 1);
	}
}

if (!function_exists('bvmgr_json_decode_associative')) {
	/**
	 * @return array{ok:bool,value:mixed,error_code:int,error_message:string,top_level_token:string}
	 */
	function bvmgr_json_decode_associative(string $raw, int $depth = 32): array
	{
		$top_level_token = bvmgr_json_top_level_token($raw);
		$raw = trim($raw);
		if ($raw === '') {
			return array(
				'ok' => false,
				'value' => null,
				'error_code' => JSON_ERROR_SYNTAX,
				'error_message' => 'Empty JSON payload.',
				'top_level_token' => '',
			);
		}

		$depth = max(1, min(128, $depth));
		$decoded = json_decode($raw, true, $depth);
		$error_code = json_last_error();
		if ($error_code !== JSON_ERROR_NONE) {
			return array(
				'ok' => false,
				'value' => null,
				'error_code' => $error_code,
				'error_message' => json_last_error_msg(),
				'top_level_token' => $top_level_token,
			);
		}

		return array(
			'ok' => true,
			'value' => $decoded,
			'error_code' => JSON_ERROR_NONE,
			'error_message' => '',
			'top_level_token' => $top_level_token,
		);
	}
}

if (!function_exists('bvmgr_json_decoded_is_object')) {
	function bvmgr_json_decoded_is_object(array $decoded, string $top_level_token): bool
	{
		if ($top_level_token !== '{') {
			return false;
		}

		return empty($decoded) || !bvmgr_array_is_list_compat($decoded);
	}
}

if (!function_exists('wp_upload_dir')) {
	function wp_upload_dir($time = null, bool $create_dir = true): array
	{
		unset($time, $create_dir);
		return array(
			'basedir' => $GLOBALS['vms_test_rows_safe_root'],
			'baseurl' => 'https://example.test/wp-content/uploads',
		);
	}
}

if (!function_exists('vms_event_plan_import_storage_path')) {
	function vms_event_plan_import_storage_path(string $reference): string
	{
		$GLOBALS['vms_test_rows_storage_path_calls']++;
		$GLOBALS['vms_test_rows_storage_path_references'][] = $reference;
		if ($reference === '') {
			return '';
		}

		return (string) ($GLOBALS['vms_test_rows_storage_map'][$reference] ?? $reference);
	}
}

if (!function_exists('vms_event_plan_import_path_is_safe')) {
	function vms_event_plan_import_path_is_safe(string $path): bool
	{
		$GLOBALS['vms_test_rows_path_is_safe_calls']++;
		$GLOBALS['vms_test_rows_path_is_safe_paths'][] = $path;

		if (in_array($path, $GLOBALS['vms_test_rows_forced_unsafe_paths'], true)) {
			return false;
		}

		$real_path = realpath($path);
		$real_root = realpath($GLOBALS['vms_test_rows_safe_root']);
		if (!is_string($real_path) || $real_path === '' || !is_string($real_root) || $real_root === '') {
			return false;
		}

		return strpos(wp_normalize_path($real_path), trailingslashit(wp_normalize_path($real_root))) === 0;
	}
}

$GLOBALS['vms_test_rows_add_action_calls'] = array();
$GLOBALS['vms_test_rows_add_submenu_calls'] = array();
$GLOBALS['vms_test_rows_current_user_can'] = true;
$GLOBALS['vms_test_rows_current_user_id'] = 7;
$GLOBALS['vms_test_rows_transients'] = array();
$GLOBALS['vms_test_rows_get_transient_calls'] = 0;
$GLOBALS['vms_test_rows_get_transient_keys'] = array();
$GLOBALS['vms_test_rows_set_transient_calls'] = 0;
$GLOBALS['vms_test_rows_set_transient_payloads'] = array();
$GLOBALS['vms_test_rows_delete_transient_calls'] = 0;
$GLOBALS['vms_test_rows_delete_transient_keys'] = array();
$GLOBALS['vms_test_rows_options'] = array();
$GLOBALS['vms_test_rows_storage_path_calls'] = 0;
$GLOBALS['vms_test_rows_storage_path_references'] = array();
$GLOBALS['vms_test_rows_path_is_safe_calls'] = 0;
$GLOBALS['vms_test_rows_path_is_safe_paths'] = array();
$GLOBALS['vms_test_rows_storage_map'] = array();
$GLOBALS['vms_test_rows_forced_unsafe_paths'] = array();
$GLOBALS['vms_test_rows_safe_root'] = sys_get_temp_dir() . '/vms-event-plan-import-rows-payload-output-remediation';
@mkdir($GLOBALS['vms_test_rows_safe_root'], 0777, true);

require_once dirname(__DIR__) . '/includes/admin-ui/shell.php';
require_once dirname(__DIR__) . '/includes/services/event-plan-import/event-plan-import-engine.php';
require_once dirname(__DIR__) . '/includes/admin/data-tools/page-event-plan-import.php';

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$reset = static function (): void {
	$_GET = array();
	$GLOBALS['vms_test_rows_current_user_can'] = true;
	$GLOBALS['vms_test_rows_current_user_id'] = 7;
	$GLOBALS['vms_test_rows_transients'] = array();
	$GLOBALS['vms_test_rows_get_transient_calls'] = 0;
	$GLOBALS['vms_test_rows_get_transient_keys'] = array();
	$GLOBALS['vms_test_rows_set_transient_calls'] = 0;
	$GLOBALS['vms_test_rows_set_transient_payloads'] = array();
	$GLOBALS['vms_test_rows_delete_transient_calls'] = 0;
	$GLOBALS['vms_test_rows_delete_transient_keys'] = array();
	$GLOBALS['vms_test_rows_options'] = array();
	$GLOBALS['vms_test_rows_storage_path_calls'] = 0;
	$GLOBALS['vms_test_rows_storage_path_references'] = array();
	$GLOBALS['vms_test_rows_path_is_safe_calls'] = 0;
	$GLOBALS['vms_test_rows_path_is_safe_paths'] = array();
	$GLOBALS['vms_test_rows_storage_map'] = array();
	$GLOBALS['vms_test_rows_forced_unsafe_paths'] = array();
};

$writeFile = static function (string $name, string $contents) use ($assert): string {
	$path = trailingslashit($GLOBALS['vms_test_rows_safe_root']) . $name;
	$written = file_put_contents($path, $contents);
	$assert($written === strlen($contents), 'Test fixture write should complete in full.');
	return $path;
};

$renderMainContent = static function (array $preview, string $preview_token = 'preview-token'): string {
	ob_start();
	vms_event_plan_import_render_main_content($preview, $preview_token, array(), array());
	return (string) ob_get_clean();
};

$previewPayload = static function (string $reference, string $source_name = 'danger<script>alert(1)</script>.csv'): array {
	return array(
		'summary' => array(
			'total_rows' => 1,
			'create' => 1,
			'update' => 0,
			'skip' => 0,
			'errors' => 0,
			'warnings' => 0,
		),
		'source_csv_name' => $source_name,
		'rows_json_storage_key' => $reference,
	);
};

$assertErrorPlacement = static function (string $html, string $message, string $label) use ($assert): void {
	$message_pos = strpos($html, $message);
	$heading_pos = strpos($html, 'Preview Results');
	$summary_pos = strpos($html, 'class="vms-admin-hub-grid"');
	$form_pos = strpos($html, 'id="vms-epcsv-commit-form"');
	$hidden_pos = strpos($html, 'name="preview_token"');
	$table_pos = strpos($html, 'class="widefat striped"');
	$commit_pos = strpos($html, 'Commit import');

	$assert($message_pos !== false, $label . ' should render the expected rows-payload message.');
	$assert($heading_pos !== false && $heading_pos < $message_pos, $label . ' should render the rows-payload message after the Preview Results heading.');
	$assert($summary_pos !== false && $summary_pos < $message_pos, $label . ' should render the rows-payload message after the preview summary.');
	$assert($form_pos !== false && $message_pos < $form_pos, $label . ' should keep the rows-payload message before the commit form.');
	$assert($hidden_pos !== false && $message_pos < $hidden_pos, $label . ' should keep the rows-payload message before the hidden preview token field.');
	$assert($table_pos !== false && $message_pos < $table_pos, $label . ' should keep the rows-payload message before the preview table.');
	$assert($commit_pos !== false && $message_pos < $commit_pos, $label . ' should keep the rows-payload message before the commit button.');
	$assert(substr_count($html, $message) === 1, $label . ' should render the rows-payload message exactly once.');
	$assert(strpos($html, 'notice notice-error inline') !== false, $label . ' should preserve the inline error notice classes.');
	$assert(strpos($html, 'No preview rows are available.') !== false, $label . ' should preserve the historical empty preview-table fallback while the rows payload is unavailable.');
};

$assert(vms_event_plan_import_rows_payload_error_messages() === array(
	'rows_json_missing' => 'Preview rows cache is missing. Please run Preview again.',
	'rows_json_unsafe' => 'Preview rows cache path is invalid.',
	'rows_json_too_large' => 'Preview rows cache is too large to validate safely.',
	'rows_json_empty' => 'Preview rows cache is empty.',
	'rows_json_invalid' => 'Preview rows cache is not valid JSON.',
), 'Rows-payload renderer should keep the exact package-owned five-code vocabulary.');

ob_start();
vms_event_plan_import_render_rows_payload_error('rows_json_missing');
$directFragment = (string) ob_get_clean();
$assert($directFragment === '<div class="notice notice-error inline"><p>Preview rows cache is missing. Please run Preview again.</p></div>', 'Rows-payload renderer should emit the fixed inline fragment for the missing-cache code.');

ob_start();
vms_event_plan_import_render_rows_payload_error('rows_json_missing<script>alert(1)</script>');
$unknownFragment = (string) ob_get_clean();
$assert($unknownFragment === '', 'Rows-payload renderer should ignore unknown or malformed error codes instead of echoing arbitrary text.');

$GLOBALS['vms_test_rows_add_submenu_calls'] = array();
vms_event_plan_import_register_admin_page();
$assert($GLOBALS['vms_test_rows_add_submenu_calls'] === array(
	array(
		null,
		'Import Event Plans (CSV)',
		'Import Event Plans (CSV)',
		'manage_options',
		'vms-import-event-plans',
		'vms_event_plan_import_render_admin_page',
	),
), 'Event Plan Import registration should preserve the null-parent submenu, manage_options gate, slug, and renderer.');

$missing_path = trailingslashit($GLOBALS['vms_test_rows_safe_root']) . 'missing-rows.json';
$unsafe_root = sys_get_temp_dir() . '/vms-event-plan-import-rows-payload-output-remediation-unsafe';
@mkdir($unsafe_root, 0777, true);
$unsafe_path = trailingslashit($unsafe_root) . 'unsafe-rows.json';
file_put_contents($unsafe_path, '{"columns":{},"rows":[]}');
$large_path = $writeFile('too-large-rows.json', str_repeat('A', (5 * 1024 * 1024) + 1));
$empty_path = $writeFile('empty-rows.json', '');
$invalid_json_path = $writeFile('invalid-json-rows.json', '{"columns":{},"rows":[');
$wrong_schema_path = $writeFile('wrong-schema-rows.json', '{"columns":{"has_agenda_text":true},"rows":{"bad":{"row_number":1}}}');
$valid_path = $writeFile('valid-rows.json', (string) json_encode(array(
	'columns' => array(
		'has_agenda_text' => true,
		'secondary_vendor_columns' => array('dessert_vendor'),
	),
	'rows' => array(
		array(
			'row_number' => 2,
			'event_key' => 'summer-fest',
			'existing_plan_id' => 15,
			'preview_action' => 'update',
			'warnings' => array('<b>Warn</b>'),
			'errors' => array(),
			'secondary_vendor_ids' => array(15),
			'secondary_vendor_create_names' => array('Dessert Cart'),
		),
	),
)));
$assert($valid_path !== false, 'Valid rows fixture path should be created.');

$error_cases = array(
	array(
		'label' => 'missing rows cache',
		'reference' => 'rows-missing',
		'path' => $missing_path,
		'unsafe' => false,
		'expected_code' => 'rows_json_missing',
		'expected_message' => 'Preview rows cache is missing. Please run Preview again.',
	),
	array(
		'label' => 'unsafe rows cache path',
		'reference' => 'rows-unsafe',
		'path' => $unsafe_path,
		'unsafe' => true,
		'expected_code' => 'rows_json_unsafe',
		'expected_message' => 'Preview rows cache path is invalid.',
	),
	array(
		'label' => 'oversized rows cache',
		'reference' => 'rows-too-large',
		'path' => $large_path,
		'unsafe' => false,
		'expected_code' => 'rows_json_too_large',
		'expected_message' => 'Preview rows cache is too large to validate safely.',
	),
	array(
		'label' => 'empty rows cache',
		'reference' => 'rows-empty',
		'path' => $empty_path,
		'unsafe' => false,
		'expected_code' => 'rows_json_empty',
		'expected_message' => 'Preview rows cache is empty.',
	),
	array(
		'label' => 'invalid JSON rows cache',
		'reference' => 'rows-invalid-json',
		'path' => $invalid_json_path,
		'unsafe' => false,
		'expected_code' => 'rows_json_invalid',
		'expected_message' => 'Preview rows cache is not valid JSON.',
	),
	array(
		'label' => 'wrong-schema rows cache',
		'reference' => 'rows-wrong-schema',
		'path' => $wrong_schema_path,
		'unsafe' => false,
		'expected_code' => 'rows_json_invalid',
		'expected_message' => 'Preview rows cache is not valid JSON.',
	),
);

foreach ($error_cases as $case) {
	$reset();
	$GLOBALS['vms_test_rows_storage_map'][$case['reference']] = $case['path'];
	if ($case['unsafe']) {
		$GLOBALS['vms_test_rows_forced_unsafe_paths'][] = $case['path'];
	}

	$rows_payload = vms_event_plan_import_read_rows_json($case['reference']);
	$assert($rows_payload instanceof WP_Error, $case['label'] . ' should produce a WP_Error from the rows decoder.');
	$assert($rows_payload->get_error_code() === $case['expected_code'], $case['label'] . ' should preserve the exact rows decoder error code.');
	$assert($rows_payload->get_error_message() === $case['expected_message'], $case['label'] . ' should preserve the exact rows decoder message.');
	$assert($GLOBALS['vms_test_rows_storage_path_calls'] === 1, $case['label'] . ' should resolve the rows storage reference exactly once.');
	$assert($GLOBALS['vms_test_rows_storage_path_references'] === array($case['reference']), $case['label'] . ' should preserve the storage reference passed into the decoder.');

	$reset();
	$GLOBALS['vms_test_rows_storage_map'][$case['reference']] = $case['path'];
	if ($case['unsafe']) {
		$GLOBALS['vms_test_rows_forced_unsafe_paths'][] = $case['path'];
	}

	$error_html = $renderMainContent($previewPayload($case['reference']));
	$assertErrorPlacement($error_html, $case['expected_message'], $case['label']);
	$assert(strpos($error_html, 'danger&lt;script&gt;alert(1)&lt;/script&gt;.csv') !== false, $case['label'] . ' should keep the source filename inert inside Preview Results.');
	$assert(strpos($error_html, 'danger<script>alert(1)</script>.csv') === false, $case['label'] . ' should not allow raw source filename markup into Preview Results.');
	$assert($GLOBALS['vms_test_rows_storage_path_calls'] === 1, $case['label'] . ' render should decode the rows payload exactly once.');
	$assert($GLOBALS['vms_test_rows_storage_path_references'] === array($case['reference']), $case['label'] . ' render should preserve the storage reference exactly once.');
}

$reset();
$GLOBALS['vms_test_rows_storage_map']['rows-valid'] = $valid_path;
$validRowsPayload = vms_event_plan_import_read_rows_json('rows-valid');
$assert(is_array($validRowsPayload), 'Valid rows cache should decode into an array payload.');
$assert(isset($validRowsPayload['rows']) && is_array($validRowsPayload['rows']) && count($validRowsPayload['rows']) === 1, 'Valid rows cache should preserve the single preview row.');

$reset();
$GLOBALS['vms_test_rows_storage_map']['rows-valid'] = $valid_path;
$validHtml = $renderMainContent($previewPayload('rows-valid'));
$assert(strpos($validHtml, 'Preview rows cache is missing. Please run Preview again.') === false, 'Valid rows cache render should not emit the missing-cache notice.');
$assert(strpos($validHtml, 'Preview rows cache path is invalid.') === false, 'Valid rows cache render should not emit the invalid-path notice.');
$assert(strpos($validHtml, 'Preview rows cache is too large to validate safely.') === false, 'Valid rows cache render should not emit the oversized-cache notice.');
$assert(strpos($validHtml, 'Preview rows cache is empty.') === false, 'Valid rows cache render should not emit the empty-cache notice.');
$assert(strpos($validHtml, 'Preview rows cache is not valid JSON.') === false, 'Valid rows cache render should not emit the invalid-JSON notice.');
$assert(strpos($validHtml, 'summer-fest') !== false, 'Valid rows cache render should preserve the decoded event key in the preview table.');
$assert(strpos($validHtml, '#15') !== false, 'Valid rows cache render should preserve the decoded plan identifier in the preview table.');
$assert(strpos($validHtml, 'UPDATE') !== false, 'Valid rows cache render should preserve the decoded preview action in the preview table.');
$assert(strpos($validHtml, '&lt;b&gt;Warn&lt;/b&gt;') !== false, 'Valid rows cache render should keep decoded row messages inert inside the preview table.');
$assert(strpos($validHtml, '<b>Warn</b>') === false, 'Valid rows cache render should not allow decoded row-message markup into the preview table.');
$assert(strpos($validHtml, 'id="vms-epcsv-commit-form"') !== false, 'Valid rows cache render should preserve the commit form.');
$assert(strpos($validHtml, 'name="preview_token"') !== false, 'Valid rows cache render should preserve the hidden preview token field.');
$assert($GLOBALS['vms_test_rows_storage_path_calls'] === 1, 'Valid rows cache render should decode the rows payload exactly once.');

$reset();
vms_event_plan_import_set_notice('success', 'Preview ready.');
vms_event_plan_import_set_preview_payload('preview-token-1', $previewPayload('rows-missing'));
$_GET = array(
	'preview_token' => 'preview-token-1',
);
ob_start();
vms_event_plan_import_render_admin_page();
$shellPage = (string) ob_get_clean();
$assert(substr_count($shellPage, 'Preview ready.') === 1, 'Shell render should emit the primary Event Plan Import notice exactly once.');
$assert(substr_count($shellPage, 'Preview rows cache is missing. Please run Preview again.') === 1, 'Shell render should emit the rows-payload error exactly once.');
$assert(strpos($shellPage, 'notice notice-success inline below-h2 vms-shell-notice') !== false, 'Shell render should keep the primary notice in the shared explicit-notice slot.');
$assert(strpos($shellPage, 'notice notice-error inline below-h2 vms-shell-notice') === false, 'Shell render should not normalize the nested rows-payload error through the shell notice pipeline.');
$assert(strpos($shellPage, 'Preview ready.') < strpos($shellPage, 'Upload a CSV, preview changes, then commit.'), 'Shell render should keep the primary page-level notice before ordinary page content.');
$assert(strpos($shellPage, 'Preview rows cache is missing. Please run Preview again.') > strpos($shellPage, 'vms-admin-shell__content'), 'Shell render should keep the rows-payload error inside the content section.');
$assert(strpos($shellPage, 'Preview rows cache is missing. Please run Preview again.') < strpos($shellPage, 'id="vms-epcsv-commit-form"'), 'Shell render should keep the rows-payload error before the commit form inside Preview Results.');
$assert($GLOBALS['vms_test_rows_get_transient_calls'] === 2, 'Shell render should resolve one notice transient and one preview transient.');
$assert($GLOBALS['vms_test_rows_delete_transient_calls'] === 1, 'Shell render should destructively pop only the primary notice transient.');

$reset();
vms_event_plan_import_set_notice('success', 'Preview ready.');
$fallbackNotice = vms_event_plan_import_pop_notice();
ob_start();
echo '<div class="wrap"><h1>' . esc_html__('Import Event Plans (CSV)', 'backstage-venue-manager') . '</h1>';
vms_event_plan_import_render_intro();
vms_event_plan_import_render_notice($fallbackNotice);
vms_event_plan_import_render_main_content($previewPayload('rows-missing'), 'preview-token-fallback', array(), array());
echo '</div>';
$fallbackPage = (string) ob_get_clean();
$assert(strpos($fallbackPage, 'Import Event Plans (CSV)') < strpos($fallbackPage, 'Upload a CSV, preview changes, then commit.'), 'No-shell fallback should keep the page heading before the intro copy.');
$assert(strpos($fallbackPage, 'Upload a CSV, preview changes, then commit.') < strpos($fallbackPage, 'Preview ready.'), 'No-shell fallback should keep the intro copy before the primary notice.');
$assert(strpos($fallbackPage, 'Preview ready.') < strpos($fallbackPage, 'name="event_plan_csv_file"'), 'No-shell fallback should keep the primary notice before the upload controls.');
$assert(strpos($fallbackPage, 'Preview Results') < strpos($fallbackPage, 'Preview rows cache is missing. Please run Preview again.'), 'No-shell fallback should keep the rows-payload error inside the Preview Results section.');
$assert(strpos($fallbackPage, 'Preview rows cache is missing. Please run Preview again.') < strpos($fallbackPage, 'id="vms-epcsv-commit-form"'), 'No-shell fallback should keep the rows-payload error before the commit form.');
$assert(strpos($fallbackPage, 'notice notice-error inline below-h2 vms-shell-notice') === false, 'No-shell fallback should keep the rows-payload error out of shell notice normalization.');

echo "Event Plan Import rows-payload output remediation assertions passed.\n";
