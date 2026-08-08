<?php

declare(strict_types=1);

$g16_root = dirname(__DIR__);
$g16_shadow_root = dirname($g16_root, 2) . '/vms';

function g16_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g16_same($expected, $actual, string $message): void
{
	g16_assert(
		$expected === $actual,
		$message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
	);
}

function g16_replace_once(string $search, string $replacement, string $subject, string $message): string
{
	$count = 0;
	$result = str_replace($search, $replacement, $subject, $count);
	g16_same(1, $count, $message);
	return $result;
}

function g16_extract_guarded_function(string $source, string $name): string
{
	$start = strpos($source, "if (!function_exists('{$name}')) {");
	g16_assert($start !== false, 'Missing guarded function: ' . $name);
	$length = strlen($source);
	$depth = 0;
	$opened = false;
	for ($offset = (int) $start; $offset < $length; $offset++) {
		if ($source[$offset] === '{') {
			$depth++;
			$opened = true;
			continue;
		}
		if ($source[$offset] === '}') {
			$depth--;
			if ($opened && $depth === 0) {
				return substr($source, (int) $start, $offset - (int) $start + 1);
			}
		}
	}

	throw new RuntimeException('Unclosed guarded function: ' . $name);
}

function g16_remove_guarded_function(string $source, string $name): string
{
	$block = g16_extract_guarded_function($source, $name);
	return g16_replace_once($block . "\n\n", '', $source, 'Guarded helper removal must occur once: ' . $name);
}

function g16_static_contract(string $root, string $shadow_root): array
{
	$relative = 'includes/runtime-guards.php';
	$sources = array(
		'mirror' => (string) file_get_contents($root . '/' . $relative),
		'shadow' => (string) file_get_contents($shadow_root . '/' . $relative),
	);
	g16_assert($sources['mirror'] !== '' && $sources['shadow'] !== '', 'Both runtime-guards sources must be readable.');

	$artifact_path = '/tmp/wporg-datezero-g15.0zTh76/plugin-check.strict.json';
	$expected_rows = array(
		'/privateincludes/runtime-guards.php:738:4',
		'/privateincludes/runtime-guards.php:1232:3',
	);
	g16_same(2, count($expected_rows), 'Embedded G16 runtime-guards inventory must remain exactly two rows.');
	g16_assert(is_file($artifact_path), 'Authoritative date-zero/G15 artifact must be present.');
	if (is_file($artifact_path)) {
		g16_same(
			'e0acd72b19d164c92958a99d9d1c58361fc90a8fcd1a0bf2c8d6f07b1ef9ef5a',
			hash_file('sha256', $artifact_path),
			'Authoritative date-zero/G15 artifact hash changed.'
		);
		$findings = json_decode((string) file_get_contents($artifact_path), true, 512, JSON_THROW_ON_ERROR);
		g16_assert(is_array($findings), 'Authoritative artifact must decode to an array.');
		g16_same(167, count($findings), 'Authoritative date-zero/G15 artifact total changed.');
		$code_counts = array();
		$rows = array();
		foreach ($findings as $finding) {
			$code = (string) ($finding['code'] ?? '');
			$code_counts[$code] = ($code_counts[$code] ?? 0) + 1;
			if (
				$code === 'WordPress.PHP.DevelopmentFunctions.error_log_error_log'
				&& str_ends_with((string) ($finding['file'] ?? ''), 'includes/runtime-guards.php')
			) {
				$rows[] = ($finding['file'] ?? '') . ':' . ($finding['line'] ?? 0) . ':' . ($finding['column'] ?? 0);
			}
		}
		g16_same(5, count($code_counts), 'Authoritative artifact code-family count changed.');
		g16_same(41, $code_counts['WordPress.PHP.DevelopmentFunctions.error_log_error_log'] ?? 0, 'Authoritative direct logging count changed.');
		g16_same(1, $code_counts['WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace'] ?? 0, 'Authoritative debug-backtrace logging count changed.');
		g16_same($expected_rows, $rows, 'Authoritative artifact must identify exactly the two owned runtime-guards rows.');
	}

	$helper_names = array(
		'vms_operational_issue_value_is_tainted',
		'vms_operational_issue_request_path',
		'vms_operational_issue_error_identity',
		'vms_operational_issue_context',
		'vms_record_operational_issue',
	);
	foreach ($sources as $tree => $source) {
		g16_same(0, preg_match_all('/(?<![A-Za-z0-9_])error_log\s*\(/', $source), $tree . ' must contain zero direct server-log calls.');
		g16_same(0, preg_match_all('/phpcs:(?:ignore|disable)[^\n]*(?:DevelopmentFunctions|error_log)/i', $source), $tree . ' must not suppress logging findings.');
		g16_same(3, substr_count($source, 'vms_record_operational_issue('), $tree . ' must contain one adapter definition and two owned calls.');
		g16_same(1, substr_count($source, "vms_record_operational_issue('admin_diagnostic'"), $tree . ' must record admin diagnostics once.');
		g16_same(1, substr_count($source, "vms_record_operational_issue('admin_guard_trace'"), $tree . ' must record admin guard traces once.');
		foreach ($helper_names as $helper_name) {
			g16_same(1, substr_count($source, "function {$helper_name}"), $tree . ' must define ' . $helper_name . ' exactly once.');
		}
	}

	foreach ($helper_names as $helper_name) {
		g16_same(
			g16_extract_guarded_function($sources['mirror'], $helper_name),
			g16_extract_guarded_function($sources['shadow'], $helper_name),
			'New operational helper must have exact mirror/shadow parity: ' . $helper_name
		);
	}
	g16_same(
		g16_extract_guarded_function($sources['mirror'], 'vms_admin_guard_trace'),
		g16_extract_guarded_function($sources['shadow'], 'vms_admin_guard_trace'),
		'Admin guard trace owned boundary must have exact mirror/shadow parity.'
	);
	g16_same(
		g16_extract_guarded_function($sources['mirror'], 'vms_resource_fingerprint_store_entry'),
		g16_extract_guarded_function($sources['shadow'], 'vms_resource_fingerprint_store_entry'),
		'Bounded non-autoload storage boundary must retain exact mirror/shadow parity.'
	);

	$mirror_render = g16_extract_guarded_function($sources['mirror'], 'vms_render_admin_diagnostics');
	$shadow_render = g16_extract_guarded_function($sources['shadow'], 'vms_render_admin_diagnostics');
	$screen_guard = "\t\tif (!function_exists('vms_admin_ui_is_admin_notice_screen') || !vms_admin_ui_is_admin_notice_screen()) {\n\t\t\treturn;\n\t\t}\n\n";
	$normalized_mirror_render = g16_replace_once($screen_guard, '', $mirror_render, 'Mirror-only admin-notice screen guard must remain exactly once.');
	g16_same($shadow_render, $normalized_mirror_render, 'Admin diagnostic target must preserve only the established mirror-only screen guard divergence.');

	return $sources;
}

function g16_projection_contract(array $sources): void
{
	$pre_edit_hashes = array(
		'mirror' => '1261e696095164c55a75911a3605ee443aef7af06a7fd521560e228e688fa6aa',
		'shadow' => 'bb7c0aa5bd62862b3f5fded51d9f03c82bb03ccab5cb017f51f10cad9a281d52',
	);
	$helper_names = array(
		'vms_operational_issue_value_is_tainted',
		'vms_operational_issue_request_path',
		'vms_operational_issue_error_identity',
		'vms_operational_issue_context',
		'vms_record_operational_issue',
	);

	foreach ($sources as $tree => $source) {
		$render = g16_extract_guarded_function($source, 'vms_render_admin_diagnostics');
		$delete_position = strpos($render, "delete_transient('vms_admin_diagnostic_queue')");
		$record_position = strpos($render, "vms_record_operational_issue('admin_diagnostic'");
		$echo_position = strpos($render, "echo '<div class=\"notice notice-warning\"");
		$seen_position = strrpos($render, "update_option('vms_admin_diagnostic_seen'");
		g16_assert(
			$delete_position !== false && $record_position !== false && $echo_position !== false && $seen_position !== false
			&& $delete_position < $record_position && $record_position < $echo_position && $echo_position < $seen_position,
			$tree . ' admin diagnostic order must remain delete -> record -> echo -> seen update.'
		);

		$trace = g16_extract_guarded_function($source, 'vms_admin_guard_trace');
		$flag_position = strpos($trace, "vms_resource_fingerprint_flag('heavy_admin_guard'");
		$marker_position = strpos($trace, "vms_resource_fingerprint_add_marker('heavy_admin_guard.'");
		$trace_record_position = strpos($trace, "vms_record_operational_issue('admin_guard_trace'");
		g16_assert(
			$flag_position !== false && $marker_position !== false && $trace_record_position !== false
			&& $flag_position < $marker_position && $marker_position < $trace_record_position,
			$tree . ' admin guard trace order must remain flag -> marker -> operational record.'
		);
		g16_same(0, substr_count($trace, 'foreach ($context'), $tree . ' trace must not persist arbitrary caller context.');

		$projection = $source;
		foreach ($helper_names as $helper_name) {
			$projection = g16_remove_guarded_function($projection, $helper_name);
		}
		$projection = g16_replace_once(
			"vms_record_operational_issue('admin_diagnostic', array('diagnostic_code' => sanitize_key((string) \$code)), \$message);",
			"error_log('[VMS] ' . \$message);",
			$projection,
			$tree . ' projection must restore admin diagnostic logging.'
		);
		$projection = g16_replace_once(
			"vms_record_operational_issue('admin_guard_trace', \$payload);",
			"error_log('[VMS TRACE] ' . wp_json_encode(\$payload));",
			$projection,
			$tree . ' projection must restore admin guard trace logging.'
		);
		$path_count = 0;
		$projection = str_replace(
			'vms_operational_issue_request_path(vms_admin_guard_request_uri())',
			'vms_resource_fingerprint_compact_value(vms_admin_guard_request_uri())',
			$projection,
			$path_count
		);
		g16_same(2, $path_count, $tree . ' projection must restore both historical request URI expressions.');
		$trace_privacy_block = "		\$trace_context = vms_operational_issue_context(array(\n"
			. "			'hook' => \$hook_name,\n"
			. "			'action' => (string) (\$context['task'] ?? ''),\n"
			. "			'decision' => \$decision,\n"
			. "			'reason' => (string) (\$context['reason'] ?? ''),\n"
			. "			'admin_page' => vms_resource_fingerprint_current_admin_page(),\n"
			. "			'screen_id' => vms_admin_guard_current_screen_id(),\n"
			. "		));\n"
			. "		\$hook_name = (string) (\$trace_context['hook'] ?? 'heavy_admin_block');\n"
			. "		\$action = (string) (\$trace_context['action'] ?? '');\n"
			. "		\$decision = (string) (\$trace_context['decision'] ?? '');\n"
			. "		\$reason = (string) (\$trace_context['reason'] ?? '');\n"
			. "		\$admin_page = (string) (\$trace_context['admin_page'] ?? '');\n"
			. "		\$screen_id = (string) (\$trace_context['screen_id'] ?? '');\n";
		$historical_hook_block = "		\$hook_name = sanitize_key(\$hook_name);\n"
			. "		if (\$hook_name === '') {\n"
			. "			\$hook_name = 'heavy_admin_block';\n"
			. "		}\n\n";
		$projection = g16_replace_once($trace_privacy_block, $historical_hook_block, $projection, $tree . ' projection must restore historical trace normalization.');
		$projection = g16_replace_once("'action' => \$action,", "'action' => sanitize_key((string) (\$context['task'] ?? '')),", $projection, $tree . ' projection must restore historical action normalization.');
		$projection = g16_replace_once("'decision' => \$decision,", "'decision' => sanitize_key(\$decision),", $projection, $tree . ' projection must restore historical decision normalization.');
		$projection = g16_replace_once("'reason' => \$reason,", "'reason' => sanitize_key((string) (\$context['reason'] ?? '')),", $projection, $tree . ' projection must restore historical reason normalization.');
		$projection = g16_replace_once("'admin_page' => \$admin_page,", "'admin_page' => vms_resource_fingerprint_current_admin_page(),", $projection, $tree . ' projection must restore historical admin page projection.');
		$projection = g16_replace_once("'screen_id' => \$screen_id,", "'screen_id' => vms_admin_guard_current_screen_id(),", $projection, $tree . ' projection must restore historical screen projection.');
		$projection = g16_replace_once(
			"'error' => vms_operational_issue_error_identity(\$e),",
			"'error' => vms_resource_fingerprint_compact_value(\$e->getMessage()),",
			$projection,
			$tree . ' projection must restore the historical Action Scheduler error expression.'
		);

		$flag_anchor = "\t\tvms_resource_fingerprint_flag('heavy_admin_guard', \$payload);";
		$historical_loop = "\t\tforeach (\$context as \$key => \$value) {\n\t\t\tif (in_array(\$key, array('task', 'reason'), true)) {\n\t\t\t\tcontinue;\n\t\t\t}\n\t\t\t\$payload[sanitize_key((string) \$key)] = vms_resource_fingerprint_compact_value(\$value);\n\t\t}\n\n" . $flag_anchor;
		$projection = g16_replace_once($flag_anchor, $historical_loop, $projection, $tree . ' projection must restore the arbitrary historical trace loop.');

		g16_same($pre_edit_hashes[$tree], hash('sha256', $projection), $tree . ' immutable pre-edit projection hash must match.');
		$mutation = g16_replace_once("return 'vms_resource_fingerprint_log';", "return 'vms_resource_fingerprint_log_mutated';", $projection, $tree . ' mutation anchor must occur once.');
		g16_assert(hash('sha256', $mutation) !== $pre_edit_hashes[$tree], $tree . ' non-owned mutation must fail the immutable projection.');
	}
}

defined('ABSPATH') || define('ABSPATH', '/srv/wordpress/');
defined('VMS_PLUGIN_PATH') || define('VMS_PLUGIN_PATH', '/srv/wordpress/wp-content/plugins/vms/');
defined('WEEK_IN_SECONDS') || define('WEEK_IN_SECONDS', 604800);
defined('MINUTE_IN_SECONDS') || define('MINUTE_IN_SECONDS', 60);

if (!class_exists('WP_Error')) {
	class WP_Error
	{
		private string $code;
		private array $messages;

		public function __construct(string $code, string $message)
		{
			$this->code = $code;
			$this->messages = array($message);
		}

		public function get_error_code(): string
		{
			return $this->code;
		}

		public function get_error_messages(): array
		{
			return $this->messages;
		}
	}
}

function is_wp_error($value): bool
{
	return $value instanceof WP_Error;
}

function sanitize_key($value): string
{
	if (!is_scalar($value)) {
		return '';
	}
	$clean = preg_replace('/[^a-z0-9_-]/i', '', strtolower((string) $value));
	return is_string($clean) ? $clean : '';
}

function sanitize_text_field($value): string
{
	if (!is_scalar($value)) {
		return '';
	}
	$value = stripslashes((string) $value);
	$value = preg_replace('/[\x00-\x1F\x7F]+/', '', $value);
	return is_string($value) ? trim($value) : '';
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
	return is_string($value) ? stripslashes($value) : $value;
}

function wp_strip_all_tags($value): string
{
	return strip_tags((string) $value);
}

function absint($value): int
{
	return abs((int) $value);
}

function apply_filters(string $hook, $value)
{
	unset($hook);
	return $value;
}

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
{
	unset($hook, $callback, $priority, $accepted_args);
	return true;
}

function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
{
	unset($hook, $callback, $priority, $accepted_args);
	return true;
}

function is_admin(): bool
{
	return !empty($GLOBALS['g16_is_admin']);
}

function wp_doing_ajax(): bool
{
	return !empty($GLOBALS['g16_doing_ajax']);
}

function wp_doing_cron(): bool
{
	return !empty($GLOBALS['g16_doing_cron']);
}

function current_user_can(string $capability): bool
{
	unset($capability);
	return !empty($GLOBALS['g16_manage_options']);
}

function get_current_screen()
{
	return $GLOBALS['g16_screen'] ?? null;
}

function get_post_type(int $post_id): string
{
	return (string) ($GLOBALS['g16_post_types'][$post_id] ?? '');
}

function get_current_user_id(): int
{
	return 99;
}

function get_option(string $name, $default = false)
{
	if (($GLOBALS['g16_throw_get_option'] ?? '') === $name) {
		unset($GLOBALS['g16_throw_get_option']);
		throw new RuntimeException('forced get_option failure');
	}
	return array_key_exists($name, $GLOBALS['g16_options']) ? $GLOBALS['g16_options'][$name] : $default;
}

function update_option(string $name, $value, bool $autoload = true): bool
{
	if (($GLOBALS['g16_throw_update_option'] ?? '') === $name) {
		unset($GLOBALS['g16_throw_update_option']);
		throw new RuntimeException('forced update_option failure');
	}
	if ($name === 'vms_resource_fingerprint_log' && !empty($GLOBALS['g16_reenter_adapter'])) {
		$GLOBALS['g16_reenter_adapter'] = false;
		$GLOBALS['g16_reentry_result'] = vms_record_operational_issue('recursive_attempt', array('status' => 'failed'));
	}
	$GLOBALS['g16_options'][$name] = $value;
	$event = array(
		'type' => 'update_option',
		'name' => $name,
		'autoload' => $autoload,
	);
	if ($name === 'vms_resource_fingerprint_log') {
		$state = is_array($GLOBALS['vms_resource_fingerprint'] ?? null) ? $GLOBALS['vms_resource_fingerprint'] : array();
		$event['flag_ready'] = !empty($state['flags']['heavy_admin_guard']);
		$event['marker_ready'] = !empty($state['markers']);
	}
	$GLOBALS['g16_events'][] = $event;
	return true;
}

function get_transient(string $name)
{
	return array_key_exists($name, $GLOBALS['g16_transients']) ? $GLOBALS['g16_transients'][$name] : false;
}

function set_transient(string $name, $value, int $expiration = 0): bool
{
	$GLOBALS['g16_transients'][$name] = $value;
	$GLOBALS['g16_events'][] = array('type' => 'set_transient', 'name' => $name, 'expiration' => $expiration);
	return true;
}

function delete_transient(string $name): bool
{
	unset($GLOBALS['g16_transients'][$name]);
	$GLOBALS['g16_events'][] = array('type' => 'delete_transient', 'name' => $name);
	return true;
}

function esc_html($value): string
{
	$GLOBALS['g16_events'][] = array('type' => 'escape_html', 'value' => (string) $value);
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function vms_admin_ui_is_admin_notice_screen(): bool
{
	return !empty($GLOBALS['g16_notice_screen']);
}

function wp_json_encode($value, int $flags = 0)
{
	return json_encode($value, $flags);
}

function _get_cron_array(): array
{
	return array();
}

function g16_reset_runtime_state(): void
{
	$GLOBALS['g16_is_admin'] = true;
	$GLOBALS['g16_doing_ajax'] = false;
	$GLOBALS['g16_doing_cron'] = false;
	$GLOBALS['g16_manage_options'] = true;
	$GLOBALS['g16_notice_screen'] = true;
	$GLOBALS['g16_screen'] = (object) array('id' => 'vms-dashboard');
	$GLOBALS['g16_post_types'] = array();
	$GLOBALS['g16_options'] = array();
	$GLOBALS['g16_transients'] = array();
	$GLOBALS['g16_events'] = array();
	unset(
		$GLOBALS['g16_throw_get_option'],
		$GLOBALS['g16_throw_update_option'],
		$GLOBALS['g16_reenter_adapter'],
		$GLOBALS['g16_reentry_result']
	);
	$GLOBALS['vms_resource_fingerprint'] = array(
		'started_at' => microtime(true),
		'flags' => array(),
		'markers' => array(),
		'open_spans' => array(),
		'notes' => array(),
		'finalized' => false,
	);
	$_GET = array('page' => 'vms-dashboard');
	$_POST = array();
	$_REQUEST = $_GET;
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=vms-dashboard';
	$_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
}

function g16_assert_sentinels_absent(array $values, array $sentinels, string $message): void
{
	$serialized = serialize($values);
	foreach ($sentinels as $sentinel) {
		g16_assert(stripos($serialized, $sentinel) === false, $message . ' Leaked sentinel: ' . $sentinel);
	}
}

function g16_runtime_adapter_contract(string $runtime_path, string $tree): void
{
	g16_assert(is_file($runtime_path), $tree . ' runtime source must exist.');
	$GLOBALS['g16_is_admin'] = false;
	$_GET = $_POST = $_REQUEST = array();
	$_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
	require $runtime_path;

	g16_same('/wp-admin/admin.php', vms_operational_issue_request_path('https://example.test/wp-admin/admin.php?page=vms&token=TOKEN-SENTINEL'), $tree . ' must retain only the request path.');
	g16_same('/wp-json/vms/v1/status', vms_operational_issue_request_path('/wp-json/vms/v1/status?email=private.person@example.test#fragment'), $tree . ' must strip query and fragment sentinels.');
	g16_same('', vms_operational_issue_request_path('/Users/private/SENTINEL-FILE.php'), $tree . ' must reject an absolute filesystem path.');
	g16_same('', vms_operational_issue_request_path('/wp-admin/../private/config.php'), $tree . ' must reject parent-path traversal.');
	g16_same('', vms_operational_issue_request_path('/reset/TOKEN-SENTINEL'), $tree . ' must reject token-like path values.');
	g16_same('', vms_operational_issue_request_path('/staff/private.person@example.test'), $tree . ' must reject email-like path values.');
	g16_same('', vms_operational_issue_request_path('/staff/private.person%40example.test'), $tree . ' must reject encoded email-like path values.');
	g16_same('', vms_operational_issue_request_path('/reset/TOKEN%2DSENTINEL'), $tree . ' must reject encoded token separators.');
	g16_same('', vms_operational_issue_request_path('/wp-admin/%2e%2e/private/config.php'), $tree . ' must reject encoded parent-path traversal.');
	g16_same('', vms_operational_issue_request_path('/safe/%00segment'), $tree . ' must reject decoded control bytes.');
	g16_same('', vms_operational_issue_request_path('/staff/private.person%2540example.test'), $tree . ' must reject a still-encoded email representation after one decode.');
	g16_same('', vms_operational_issue_request_path('/wp-admin/%252e%252e/private/config.php'), $tree . ' must reject a still-encoded traversal representation after one decode.');
	g16_same('', vms_operational_issue_request_path('/reset/TOKEN%252DSENTINEL'), $tree . ' must reject a still-encoded token separator after one decode.');
	g16_same('/staff/My File', vms_operational_issue_request_path('/staff/My%20File'), $tree . ' must retain a normal decoded path.');
	$long_safe_path = str_repeat('/safe-segment-123', 20);
	g16_same(180, strlen(vms_operational_issue_request_path($long_safe_path)), $tree . ' request paths must be length-bounded.');

	$raw_error = 'RAW-ERROR-SENTINEL private.person@example.test TOKEN-SENTINEL';
	$throwable = new RuntimeException($raw_error, 73);
	$identity_one = vms_operational_issue_error_identity($throwable);
	$identity_two = vms_operational_issue_error_identity(new RuntimeException($raw_error, 73));
	g16_same($identity_one, $identity_two, $tree . ' Throwable fingerprints must be deterministic.');
	g16_same('runtimeexception', $identity_one['error_class'] ?? '', $tree . ' Throwable class must be sanitized.');
	g16_same('73', $identity_one['error_code'] ?? '', $tree . ' Throwable code must be sanitized.');
	g16_assert((bool) preg_match('/^[a-f0-9]{24}$/', (string) ($identity_one['error_fingerprint'] ?? '')), $tree . ' Throwable fingerprint must be truncated SHA-256.');

	$wp_error_identity = vms_operational_issue_error_identity(new WP_Error('remote_failed', $raw_error));
	g16_same('wp_error', $wp_error_identity['error_class'] ?? '', $tree . ' WP_Error class must be sanitized.');
	g16_same('remote_failed', $wp_error_identity['error_code'] ?? '', $tree . ' WP_Error code must be retained safely.');
	g16_assert((bool) preg_match('/^[a-f0-9]{24}$/', (string) ($wp_error_identity['error_fingerprint'] ?? '')), $tree . ' WP_Error fingerprint must be truncated SHA-256.');
	$string_identity = vms_operational_issue_error_identity($raw_error);
	g16_same('string', $string_identity['error_class'] ?? '', $tree . ' String errors must carry only their safe type.');
	g16_assert((bool) preg_match('/^[a-f0-9]{24}$/', (string) ($string_identity['error_fingerprint'] ?? '')), $tree . ' String fingerprint must be truncated SHA-256.');
	$unsafe_error_codes = array(
		'Human readable error code',
		'private.person@example.test',
		'sk_live_1234567890abcdef',
	);
	foreach ($unsafe_error_codes as $unsafe_error_code) {
		$unsafe_identity = vms_operational_issue_error_identity(new WP_Error($unsafe_error_code, 'generic failure'));
		g16_assert(!array_key_exists('error_code', $unsafe_identity), $tree . ' unsafe error codes must be omitted rather than sanitized: ' . $unsafe_error_code);
		g16_assert((bool) preg_match('/^[a-f0-9]{24}$/', (string) ($unsafe_identity['error_fingerprint'] ?? '')), $tree . ' unsafe error codes must contribute only to a truncated fingerprint.');
		g16_assert(stripos(serialize($unsafe_identity), $unsafe_error_code) === false, $tree . ' unsafe error codes must never persist raw.');
	}
	g16_same(
		vms_operational_issue_error_identity(new WP_Error($unsafe_error_codes[0], 'generic failure')),
		vms_operational_issue_error_identity(new WP_Error($unsafe_error_codes[0], 'generic failure')),
		$tree . ' unsafe error-code fingerprints must remain deterministic.'
	);

	$tainted_context = vms_operational_issue_context(array(
		'hook' => 'safe_hook?QUERY-SENTINEL=1',
		'action' => '/Users/private/SENTINEL-FILE.php',
		'decision' => 'TOKEN-SENTINEL',
		'reason' => 'private.person@example.test',
		'admin_page' => '203.0.113.77',
		'screen_id' => 'UA-SENTINEL',
		'service' => 'SECRET-SENTINEL',
		'message' => 'MESSAGE-SENTINEL',
		'sql' => 'SELECT SQL-SENTINEL',
		'count' => array(99),
		'path' => '/otherwise-safe/web/path',
		'provider' => 'sk_live_1234567890abcdef',
		'entity_type' => 'eyJhbGci.eyJzdWIi.signature',
		'status' => str_repeat('z', 45),
		'operation' => str_repeat('f', 32),
		'stage' => 'Human readable prose',
		'trigger' => 'Manual trigger prose',
		'mode' => 'sk_test_1234567890abcdef',
		'source_scope' => 'private.person@example.test',
		'event_key' => 'eyJhbGci.eyJzdWIi.signature',
		'correlation' => str_repeat('a', 32),
		'entity_id' => -1,
		'fatal_type' => -1,
		'memory_exhausted' => array(1),
		'recipient_id' => 88,
		'email_id' => 99,
	));
	g16_same(array(), $tainted_context, $tree . ' tainted values and non-scalars must be dropped even under allowed keys.');

	$future_context = vms_operational_issue_context(array(
		'provider' => 'Square',
		'entity_type' => 'Vendor_Profile',
		'trigger' => 'WP_Shutdown',
		'mode' => 'Automated',
		'source_scope' => 'Event_Plan',
		'event_key' => 'Fatal_Error',
		'correlation' => 'abcdef0123456789abcdef01',
		'entity_id' => '42',
		'vendor_id' => 43,
		'event_id' => 44,
		'plan_id' => 45,
		'product_id' => 46,
		'post_id' => PHP_INT_MAX,
		'fatal_type' => 1,
		'memory_exhausted' => '1',
		'request_path' => '/ops/vendors/42?token=TOKEN-SENTINEL',
	));
	g16_same(array(
		'provider' => 'square',
		'entity_type' => 'vendor_profile',
		'trigger' => 'wp_shutdown',
		'mode' => 'automated',
		'source_scope' => 'event_plan',
		'event_key' => 'fatal_error',
		'correlation' => 'abcdef0123456789abcdef01',
		'entity_id' => 42,
		'vendor_id' => 43,
		'event_id' => 44,
		'plan_id' => 45,
		'product_id' => 46,
		'post_id' => 1000000000,
		'fatal_type' => 1,
		'memory_exhausted' => 1,
		'request_path' => '/ops/vendors/42',
	), $future_context, $tree . ' downstream operational provider, entity, trigger, correlation, bounded integer, and web-path fields must remain finite and allowlisted.');

	g16_reset_runtime_state();
	$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?email=private.person@example.test&token=TOKEN-SENTINEL';
	g16_same(true, vms_record_operational_issue('ambient_route_test', array('status' => 'failed')), $tree . ' adapter must accept an issue without explicit path context.');
	$ambient_entry = $GLOBALS['g16_options']['vms_resource_fingerprint_log'][0] ?? array();
	g16_same('', $ambient_entry['request_uri'] ?? null, $tree . ' adapter must never auto-capture the ambient request path.');
	g16_assert(stripos(serialize($ambient_entry), 'private.person@example.test') === false, $tree . ' ambient email must not enter operational storage.');
	g16_assert(stripos(serialize($ambient_entry), 'TOKEN-SENTINEL') === false, $tree . ' ambient token must not enter operational storage.');

	g16_reset_runtime_state();
	$GLOBALS['g16_options']['vms_resource_fingerprint_log'] = array_fill(0, 65, array('seed' => true));
	$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=vms&token=TOKEN-SENTINEL&email=private.person@example.test';
	$stored = vms_record_operational_issue('service_edge_failure', array(
		'hook' => 'safe_hook',
		'action' => 'safe_action',
		'decision' => 'failed',
		'reason' => 'remote_failure',
		'request_uri' => 'https://example.test/wp-json/vms/v1/run?token=TOKEN-SENTINEL',
		'service' => 'remote_gateway',
		'operation' => 'retry',
		'status' => 'failed',
		'elapsed_ms' => 12.34,
		'message' => 'MESSAGE-SENTINEL',
		'query' => 'QUERY-SENTINEL=1',
		'file' => '/Users/private/SENTINEL-FILE.php',
		'recipient' => 'private.person@example.test',
		'ip' => '203.0.113.77',
		'user_agent' => 'UA-SENTINEL',
		'token' => 'TOKEN-SENTINEL',
		'secret' => 'SECRET-SENTINEL',
		'nonce' => 'NONCE-SENTINEL',
		'cookie' => 'COOKIE-SENTINEL',
	), $throwable);
	g16_same(true, $stored, $tree . ' adapter must store a valid operational issue.');
	$entries = $GLOBALS['g16_options']['vms_resource_fingerprint_log'];
	g16_same(60, count($entries), $tree . ' adapter must reuse the bounded resource-fingerprint limit.');
	$entry = $entries[59];
	g16_same('/wp-json/vms/v1/run', $entry['request_uri'] ?? '', $tree . ' stored request URI must be path-only.');
	$last_event = $GLOBALS['g16_events'][count($GLOBALS['g16_events']) - 1] ?? array();
	g16_same(false, $last_event['autoload'] ?? true, $tree . ' operational storage must remain non-autoload.');
	foreach (array('captured_at_gmt', 'runtime_ms', 'peak_memory_mb', 'request_uri', 'request_method', 'admin_page', 'screen_id', 'user_id', 'context', 'flags', 'markers', 'notes', 'due_wp_cron', 'action_scheduler') as $entry_key) {
		g16_assert(array_key_exists($entry_key, $entry), $tree . ' operational entry must remain compatible with the admin table: ' . $entry_key);
	}
	$issue = $entry['flags']['operational_issue'][0] ?? array();
	g16_same('service_edge_failure', $issue['event_code'] ?? '', $tree . ' event code must remain fixed and sanitized.');
	g16_same(array(
		'hook' => 'safe_hook',
		'action' => 'safe_action',
		'decision' => 'failed',
		'reason' => 'remote_failure',
		'request_uri' => '/wp-json/vms/v1/run',
		'service' => 'remote_gateway',
		'operation' => 'retry',
		'status' => 'failed',
		'elapsed_ms' => 12.3,
	), $issue['context'] ?? array(), $tree . ' issue context must retain only strict allowlisted finite scalars.');
	g16_same($identity_one, $issue['error'] ?? array(), $tree . ' stored Throwable identity must match the deterministic safe projection.');
	g16_same(true, vms_record_operational_issue('vendor_app_vendor_type_assignment_failed', array('status' => 'failed')), $tree . ' long semantic fixed event codes must not be mistaken for secret-shaped values.');
	$semantic_event_entry = end($GLOBALS['g16_options']['vms_resource_fingerprint_log']);
	g16_same('vendor_app_vendor_type_assignment_failed', $semantic_event_entry['flags']['operational_issue'][0]['event_code'] ?? '', $tree . ' accepted semantic event code changed.');

	$before_rejected = count($GLOBALS['g16_options']['vms_resource_fingerprint_log']);
	foreach (array('TOKEN-SENTINEL', 'Human readable event', 'private.person@example.test', 'sk_live_1234567890abcdef', str_repeat('a', 40), str_repeat('a', 65)) as $unsafe_event_code) {
		g16_same(false, vms_record_operational_issue($unsafe_event_code, array('status' => 'failed')), $tree . ' prose, PII, and secret-shaped event codes must be rejected: ' . $unsafe_event_code);
	}
	g16_same($before_rejected, count($GLOBALS['g16_options']['vms_resource_fingerprint_log']), $tree . ' rejected events must not mutate storage.');
	g16_same(true, vms_record_operational_issue('wp_error_failure', array('status' => 'failed'), new WP_Error('remote_failed', $raw_error)), $tree . ' adapter must accept WP_Error safely.');
	g16_same(true, vms_record_operational_issue('string_failure', array('status' => 'failed'), $raw_error), $tree . ' adapter must accept raw strings safely.');
	$entries = $GLOBALS['g16_options']['vms_resource_fingerprint_log'];
	g16_same(60, count($entries), $tree . ' repeated adapter writes must remain bounded.');
	$last_two = array_slice($entries, -2);
	g16_same($wp_error_identity, $last_two[0]['flags']['operational_issue'][0]['error'] ?? array(), $tree . ' WP_Error storage must retain only safe identity fields.');
	g16_same($string_identity, $last_two[1]['flags']['operational_issue'][0]['error'] ?? array(), $tree . ' string storage must retain only safe identity fields.');

	$sentinels = array(
		'RAW-ERROR-SENTINEL',
		'private.person@example.test',
		'TOKEN-SENTINEL',
		'QUERY-SENTINEL',
		'MESSAGE-SENTINEL',
		'SQL-SENTINEL',
		'/Users/private/SENTINEL-FILE.php',
		'203.0.113.77',
		'UA-SENTINEL',
		'SECRET-SENTINEL',
		'NONCE-SENTINEL',
		'COOKIE-SENTINEL',
	);
	g16_assert_sentinels_absent($entries, $sentinels, $tree . ' recursively serialized adapter entries must contain no sensitive sentinel.');

	g16_reset_runtime_state();
	$GLOBALS['g16_reenter_adapter'] = true;
	g16_same(true, vms_record_operational_issue('outer_issue', array('status' => 'failed')), $tree . ' outer adapter call must remain successful during a recursive storage callback.');
	g16_same(false, $GLOBALS['g16_reentry_result'] ?? null, $tree . ' recursive adapter entry must fail closed.');
	$reentry_entries = $GLOBALS['g16_options']['vms_resource_fingerprint_log'] ?? array();
	g16_same(1, count($reentry_entries), $tree . ' recursion guard must prevent a nested operational entry.');
	g16_same('outer_issue', $reentry_entries[0]['flags']['operational_issue'][0]['event_code'] ?? '', $tree . ' recursion guard must preserve the outer operational entry.');

	g16_reset_runtime_state();
	$GLOBALS['g16_throw_get_option'] = 'vms_resource_fingerprint_log';
	g16_same(false, vms_record_operational_issue('read_failure', array('status' => 'failed')), $tree . ' throwing option reads must be contained and return false.');
	g16_assert(!isset($GLOBALS['g16_options']['vms_resource_fingerprint_log']), $tree . ' failed option reads must not create operational storage.');
	g16_same(true, vms_record_operational_issue('read_recovery', array('status' => 'recovered')), $tree . ' recursion guard must clear after a caught option-read failure.');

	g16_reset_runtime_state();
	$GLOBALS['g16_throw_update_option'] = 'vms_resource_fingerprint_log';
	g16_same(false, vms_record_operational_issue('write_failure', array('status' => 'failed')), $tree . ' throwing option writes must be contained and return false.');
	g16_assert(!isset($GLOBALS['g16_options']['vms_resource_fingerprint_log']), $tree . ' failed option writes must not create operational storage.');
	g16_same(true, vms_record_operational_issue('write_recovery', array('status' => 'recovered')), $tree . ' recursion guard must clear after a caught option-write failure.');

	g16_reset_runtime_state();
	$GLOBALS['g16_throw_update_option'] = 'vms_resource_fingerprint_log';
	vms_admin_guard_trace('staff_guard', 'blocked', array('task' => 'staff_sync', 'reason' => 'passive_request'));
	$failed_trace_state = $GLOBALS['vms_resource_fingerprint'];
	g16_assert(!empty($failed_trace_state['flags']['heavy_admin_guard']), $tree . ' guard trace must retain its flag when operational storage throws.');
	g16_assert(!empty($failed_trace_state['markers']), $tree . ' guard trace must retain its marker when operational storage throws.');
	g16_assert(!isset($GLOBALS['g16_options']['vms_resource_fingerprint_log']), $tree . ' failed guard operational storage must not escape or create an entry.');

	g16_reset_runtime_state();
	$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=vms-dashboard&token=TOKEN-SENTINEL&email=private.person@example.test';
	vms_admin_guard_trace('staff_guard', 'blocked', array(
		'task' => 'staff_sync',
		'reason' => 'passive_request',
		'message' => 'MESSAGE-SENTINEL',
		'query' => 'QUERY-SENTINEL=1',
		'file' => '/Users/private/SENTINEL-FILE.php',
		'recipient' => 'private.person@example.test',
		'ip' => '203.0.113.77',
		'user_agent' => 'UA-SENTINEL',
		'token' => 'TOKEN-SENTINEL',
		'nonce' => 'NONCE-SENTINEL',
	));

	$state = $GLOBALS['vms_resource_fingerprint'];
	$flag_payload = $state['flags']['heavy_admin_guard'][0] ?? array();
	$marker = $state['markers'][0] ?? array();
	g16_same(
		array('hook', 'action', 'decision', 'reason', 'request_uri', 'admin_page', 'screen_id', 'elapsed_ms', '...'),
		array_keys($flag_payload),
		$tree . ' trace flag must retain the historical eight-field compact bound.'
	);
	g16_same('staff_guard', $flag_payload['hook'] ?? '', $tree . ' trace flag must preserve the safe hook.');
	g16_same('staff_sync', $flag_payload['action'] ?? '', $tree . ' trace flag must preserve the safe action.');
	g16_same('blocked', $flag_payload['decision'] ?? '', $tree . ' trace flag must preserve the safe decision.');
	g16_same('passive_request', $flag_payload['reason'] ?? '', $tree . ' trace flag must preserve the safe reason.');
	g16_same('/wp-admin/admin.php', $flag_payload['request_uri'] ?? '', $tree . ' trace flag URI must be path-only.');
	g16_same($flag_payload, $marker['context'] ?? array(), $tree . ' trace marker must preserve the same fixed safe payload.');
	g16_same('heavy_admin_guard.staff_guard', $marker['label'] ?? '', $tree . ' trace marker label must remain stable.');

	$trace_event = $GLOBALS['g16_events'][0] ?? array();
	g16_same('vms_resource_fingerprint_log', $trace_event['name'] ?? '', $tree . ' trace must record through bounded fingerprint storage.');
	g16_same(true, $trace_event['flag_ready'] ?? false, $tree . ' trace flag must exist before operational storage.');
	g16_same(true, $trace_event['marker_ready'] ?? false, $tree . ' trace marker must exist before operational storage.');
	$trace_entries = $GLOBALS['g16_options']['vms_resource_fingerprint_log'] ?? array();
	$trace_issue = $trace_entries[0]['flags']['operational_issue'][0] ?? array();
	g16_same('admin_guard_trace', $trace_issue['event_code'] ?? '', $tree . ' trace must record the fixed event code.');
	$trace_issue_context = $trace_issue['context'] ?? array();
	g16_same(
		array('hook', 'action', 'decision', 'reason', 'request_uri', 'admin_page', 'screen_id', 'elapsed_ms', 'memory_mb'),
		array_keys($trace_issue_context),
		$tree . ' operational trace must retain all nine fixed allowlisted fields.'
	);
	foreach (array('hook', 'action', 'decision', 'reason', 'request_uri', 'admin_page', 'screen_id', 'elapsed_ms') as $trace_key) {
		g16_same($flag_payload[$trace_key] ?? null, $trace_issue_context[$trace_key] ?? null, $tree . ' trace flag and operational record must agree for ' . $trace_key . '.');
	}
	g16_assert(is_float($trace_issue_context['memory_mb'] ?? null) || is_int($trace_issue_context['memory_mb'] ?? null), $tree . ' operational trace memory must remain finite numeric context.');
	g16_assert_sentinels_absent($trace_entries, $sentinels, $tree . ' trace operational entry must recursively exclude sensitive caller values.');

	vms_resource_fingerprint_shutdown();
	$trace_entries = $GLOBALS['g16_options']['vms_resource_fingerprint_log'] ?? array();
	g16_same(2, count($trace_entries), $tree . ' trace shutdown must append the compatible aggregate entry.');
	g16_same('/wp-admin/admin.php', $trace_entries[1]['request_uri'] ?? '', $tree . ' aggregate trace URI must also be path-only.');
	g16_same(true, $GLOBALS['vms_resource_fingerprint']['finalized'] ?? false, $tree . ' aggregate trace state must finalize once.');
	g16_assert_sentinels_absent($trace_entries, $sentinels, $tree . ' all recursively serialized trace entries must exclude sensitive values.');

	g16_reset_runtime_state();
	$diagnostic_message = 'Privileged RAW-ERROR-SENTINEL for private.person@example.test with TOKEN-SENTINEL';
	vms_queue_admin_diagnostic('missing_dependency', $diagnostic_message);
	$GLOBALS['g16_events'] = array();
	ob_start();
	vms_render_admin_diagnostics();
	$notice_output = (string) ob_get_clean();
	g16_same(
		'<div class="notice notice-warning"><p>' . htmlspecialchars($diagnostic_message, ENT_QUOTES, 'UTF-8') . '</p></div>',
		$notice_output,
		$tree . ' privileged diagnostic notice output must remain unchanged.'
	);
	$diagnostic_events = $GLOBALS['g16_events'];
	g16_same('delete_transient', $diagnostic_events[0]['type'] ?? '', $tree . ' diagnostic queue must be deleted before rendering.');
	g16_same('vms_admin_diagnostic_queue', $diagnostic_events[0]['name'] ?? '', $tree . ' diagnostic queue deletion target must remain stable.');
	g16_same('update_option', $diagnostic_events[1]['type'] ?? '', $tree . ' operational record must precede the notice echo.');
	g16_same('vms_resource_fingerprint_log', $diagnostic_events[1]['name'] ?? '', $tree . ' diagnostic must use bounded operational storage.');
	g16_same(false, $diagnostic_events[1]['autoload'] ?? true, $tree . ' diagnostic operational storage must remain non-autoload.');
	g16_same('escape_html', $diagnostic_events[2]['type'] ?? '', $tree . ' privileged notice echo must follow its safe operational record.');
	g16_same('update_option', $diagnostic_events[3]['type'] ?? '', $tree . ' seen state must update after the loop.');
	g16_same('vms_admin_diagnostic_seen', $diagnostic_events[3]['name'] ?? '', $tree . ' diagnostic seen option must remain stable.');
	g16_same(false, $diagnostic_events[3]['autoload'] ?? true, $tree . ' diagnostic seen option must remain non-autoload.');

	$diagnostic_entries = $GLOBALS['g16_options']['vms_resource_fingerprint_log'] ?? array();
	g16_same(1, count($diagnostic_entries), $tree . ' diagnostic render must store one operational issue.');
	$diagnostic_issue = $diagnostic_entries[0]['flags']['operational_issue'][0] ?? array();
	g16_same('admin_diagnostic', $diagnostic_issue['event_code'] ?? '', $tree . ' diagnostic event code must remain fixed.');
	g16_same(array('diagnostic_code' => 'missing_dependency'), $diagnostic_issue['context'] ?? array(), $tree . ' diagnostic context must retain only its code.');
	g16_same('string', $diagnostic_issue['error']['error_class'] ?? '', $tree . ' diagnostic message must derive only a safe string identity.');
	g16_assert((bool) preg_match('/^[a-f0-9]{24}$/', (string) ($diagnostic_issue['error']['error_fingerprint'] ?? '')), $tree . ' diagnostic fingerprint must be truncated SHA-256.');
	g16_assert_sentinels_absent($diagnostic_entries, $sentinels, $tree . ' diagnostic operational storage must exclude the raw privileged message.');
	g16_assert(!isset($GLOBALS['g16_transients']['vms_admin_diagnostic_queue']), $tree . ' rendered diagnostic queue must remain deleted.');
	g16_assert(!empty($GLOBALS['g16_options']['vms_admin_diagnostic_seen']['missing_dependency']), $tree . ' rendered diagnostic must retain its seen gate.');

	$GLOBALS['g16_events'] = array();
	ob_start();
	vms_render_admin_diagnostics();
	g16_same('', (string) ob_get_clean(), $tree . ' empty queue must not repeat a seen diagnostic.');
	g16_same(array(), $GLOBALS['g16_events'], $tree . ' empty queue must not mutate diagnostic state.');

	g16_reset_runtime_state();
	$failed_diagnostic_message = 'Privileged diagnostic remains visible when operational storage fails';
	vms_queue_admin_diagnostic('storage_failure_diagnostic', $failed_diagnostic_message);
	$GLOBALS['g16_events'] = array();
	$GLOBALS['g16_throw_get_option'] = 'vms_resource_fingerprint_log';
	ob_start();
	vms_render_admin_diagnostics();
	$failed_notice_output = (string) ob_get_clean();
	g16_same(
		'<div class="notice notice-warning"><p>' . htmlspecialchars($failed_diagnostic_message, ENT_QUOTES, 'UTF-8') . '</p></div>',
		$failed_notice_output,
		$tree . ' diagnostic caller must preserve its privileged notice when operational storage throws.'
	);
	g16_assert(!isset($GLOBALS['g16_options']['vms_resource_fingerprint_log']), $tree . ' throwing diagnostic storage must not create an operational entry.');
	g16_assert(!isset($GLOBALS['g16_transients']['vms_admin_diagnostic_queue']), $tree . ' throwing diagnostic storage must not change queue deletion behavior.');
	g16_assert(!empty($GLOBALS['g16_options']['vms_admin_diagnostic_seen']['storage_failure_diagnostic']), $tree . ' throwing diagnostic storage must not change the seen gate.');

	g16_reset_runtime_state();
	vms_queue_admin_diagnostic('unauthorized_diagnostic', 'RAW-ERROR-SENTINEL unauthorized notice');
	$GLOBALS['g16_events'] = array();
	$GLOBALS['g16_manage_options'] = false;
	ob_start();
	vms_render_admin_diagnostics();
	g16_same('', (string) ob_get_clean(), $tree . ' unauthorized users must not see diagnostics.');
	g16_same(array(), $GLOBALS['g16_events'], $tree . ' unauthorized render must not mutate queue, issue, or seen state.');
	g16_assert(isset($GLOBALS['g16_transients']['vms_admin_diagnostic_queue']['unauthorized_diagnostic']), $tree . ' unauthorized render must preserve the queue.');
	$GLOBALS['vms_resource_fingerprint']['finalized'] = true;
}

function g16_run_runtime_child(string $runtime_path, string $tree): void
{
	$command = array(PHP_BINARY, __FILE__, '--runtime', $runtime_path, $tree);
	$process = proc_open($command, array(
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w'),
	), $pipes);
	g16_assert(is_resource($process), 'Unable to start the ' . $tree . ' runtime proof.');
	$stdout = (string) stream_get_contents($pipes[1]);
	$stderr = (string) stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$exit_code = proc_close($process);
	g16_same(0, $exit_code, $tree . " runtime proof failed.\n" . $stdout . $stderr);
	g16_assert(strpos($stdout, '[PASS] ' . $tree . ' runtime') !== false, $tree . ' runtime proof did not report PASS.');
}

try {
	if (($argv[1] ?? '') === '--runtime') {
		g16_runtime_adapter_contract((string) ($argv[2] ?? ''), (string) ($argv[3] ?? 'unknown'));
		echo '[PASS] ' . ($argv[3] ?? 'unknown') . " runtime\n";
		exit(0);
	}

	$g16_sources = g16_static_contract($g16_root, $g16_shadow_root);
	g16_projection_contract($g16_sources);
	g16_run_runtime_child($g16_root . '/includes/runtime-guards.php', 'mirror');
	g16_run_runtime_child($g16_shadow_root . '/includes/runtime-guards.php', 'shadow');
	echo "[PASS] G16 operational logging foundation\n";
} catch (Throwable $error) {
	fwrite(STDERR, '[FAIL] ' . $error->getMessage() . "\n");
	exit(1);
}
