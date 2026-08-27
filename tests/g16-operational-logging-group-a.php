<?php

declare(strict_types=1);

$g16a_root = dirname(__DIR__);
$g16a_shadow_root = dirname($g16a_root, 2) . '/vms';
$g16a_artifact_path = '/tmp/wporg-datezero-g15.0zTh76/plugin-check.strict.json';

function g16a_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g16a_same($expected, $actual, string $message): void
{
	g16a_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function g16a_contains(string $needle, string $haystack, string $message): void
{
	g16a_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function g16a_read(string $path): string
{
	$source = file_get_contents($path);
	g16a_assert(is_string($source) && $source !== '', 'Required source must be readable: ' . $path);
	return $source;
}

function g16a_extract_function(string $source, string $name): string
{
	$start = strpos($source, 'function ' . $name . '(');
	$brace = $start === false ? false : strpos($source, '{', $start);
	g16a_assert($start !== false && $brace !== false, 'Unable to find function: ' . $name);
	$depth = 1;
	for ($offset = (int) $brace + 1, $length = strlen($source); $offset < $length; $offset++) {
		$depth += $source[$offset] === '{' ? 1 : 0;
		$depth -= $source[$offset] === '}' ? 1 : 0;
		if ($depth === 0) {
			return substr($source, (int) $start, $offset - (int) $start + 1);
		}
	}
	throw new RuntimeException('Unable to close function: ' . $name);
}

function g16a_extract_guarded_function(string $source, string $name): string
{
	$start = strpos($source, "if (!function_exists('" . $name . "')) {");
	g16a_assert($start !== false, 'Unable to find guarded function: ' . $name);
	$depth = 0;
	$opened = false;
	for ($offset = (int) $start, $length = strlen($source); $offset < $length; $offset++) {
		if ($source[$offset] === '{') {
			$depth++;
			$opened = true;
		} elseif ($source[$offset] === '}') {
			$depth--;
			if ($opened && $depth === 0) {
				return substr($source, (int) $start, $offset - (int) $start + 1);
			}
		}
	}
	throw new RuntimeException('Unable to close guarded function: ' . $name);
}

function g16a_operational_call(string $source, string $event_code): string
{
	$needle = "bvmgr_record_operational_issue('" . $event_code . "'";
	g16a_same(1, substr_count($source, $needle), 'Fixed event must occur exactly once: ' . $event_code);
	$start = strpos($source, $needle);
	$line_start = strrpos(substr($source, 0, (int) $start), "\n");
	$line_start = $line_start === false ? 0 : $line_start + 1;
	$end = strpos($source, ");\n", (int) $start);
	g16a_assert($end !== false, 'Operational call must end on its own line: ' . $event_code);
	return substr($source, $line_start, $end + 2 - $line_start);
}

function g16a_normalize(string $source): string
{
	$normalized = preg_replace('/\s+/', ' ', trim($source));
	return is_string($normalized) ? $normalized : '';
}

function g16a_restore_call(string $source, string $event_code, string $historical): string
{
	$call = g16a_operational_call($source, $event_code);
	$line_start = strpos($source, $call);
	g16a_assert($line_start !== false, 'Known operational call must be replaceable: ' . $event_code);
	$indent_length = strspn($call, " \t");
	$indent = substr($call, 0, $indent_length);
	$replacement = '';
	foreach (explode("\n", $historical) as $line) {
		$replacement .= $indent . $line . "\n";
	}
	return substr($source, 0, (int) $line_start) . $replacement . substr($source, (int) $line_start + strlen($call) + 1);
}

function g16a_restore_group_a_baseline(string $relative, string $source): string
{
	$maps = array(
		'includes/vendor-applications.php' => array(
			'vendor_app_vendor_create_failed' => "\$error_message = is_wp_error(\$vendor_id) ? \$vendor_id->get_error_message() : 'unknown error';\nerror_log('[VMS] vendor-applications: failed creating vendor for app_id ' . \$app_id . ' (' . \$error_message . ')');",
			'vendor_app_submitting_user_missing' => "error_log('[VMS] vendor-applications: submitting user missing for app_id ' . \$app_id . ' (user_id ' . \$user_id . ')');",
			'vendor_app_user_link_failed' => "error_log('[VMS] vendor-applications: failed linking submitting user ' . \$user_id . ' to vendor ' . \$vendor_id . ' for app_id ' . \$app_id);",
			'vendor_apply_turnstile_config_missing' => "error_log('[VMS] vendor-apply: Turnstile keys missing; blocking submission.');",
			'vendor_apply_turnstile_request_failed' => "error_log('[VMS] vendor-apply: Turnstile siteverify request failed: ' . \$resp->get_error_message());",
			'vendor_apply_turnstile_response_failed' => "error_log('[VMS] vendor-apply: Turnstile siteverify non-2xx or empty body. HTTP ' . \$code);",
			'vendor_app_vendor_type_unresolved' => "error_log('[VMS] vendor-applications: unknown vendor type slug \"' . \$slug . '\" on app_id ' . \$app_id . '; not assigning taxonomy term.');",
			'vendor_app_vendor_type_assignment_failed' => "error_log('[VMS] vendor-applications: failed setting vms_vendor_type terms for vendor_id ' . \$vendor_id . ' (app_id ' . \$app_id . ')');",
		),
		'includes/core/vendor-application-confirmation.php' => array(
			'vendor_app_review_ready_mail_failed' => "error_log('[VMS] vendor-apply: review-ready admin notification failed for app_id ' . \$app_id);",
		),
		'includes/core/goals-forecast.php' => array(
			'goals_legacy_issue' => "error_log('[VMS Goals] ' . \$message);",
			'goals_provider_hard_error_check_failed' => "vms_goals_log('Hard-error check failed (square): ' . \$e->getMessage());",
			'goals_provider_call_failed' => "vms_goals_log('Provider call failed (square): ' . \$e->getMessage());",
			'goals_actuals_refresh_failed' => "vms_goals_log('Actuals refresh failed for event ' . \$event_plan_id . ': ' . \$msg);",
			'goals_progress_capped' => "vms_goals_log('Goal progress evaluation capped at ' . \$max_events . ' events for performance.');",
		),
	);
	g16a_assert(isset($maps[$relative]), 'Unknown Group A projection target: ' . $relative);
	$map = $maps[$relative];
	if (
		$relative === 'includes/vendor-applications.php'
		&& strpos($source, "bvmgr_record_operational_issue('vendor_apply_turnstile_payload_invalid'") !== false
	) {
		$map['vendor_apply_turnstile_payload_invalid'] = "error_log('[VMS] vendor-apply: Turnstile siteverify returned an invalid JSON payload.');";
	}
	foreach ($map as $event_code => $historical) {
		$source = g16a_restore_call($source, $event_code, $historical);
	}
	return $source;
}

g16a_assert(is_file($g16a_artifact_path), 'Authoritative date-zero/G15 strict JSON must be present.');
g16a_same(
	'e0acd72b19d164c92958a99d9d1c58361fc90a8fcd1a0bf2c8d6f07b1ef9ef5a',
	hash_file('sha256', $g16a_artifact_path),
	'Authoritative date-zero/G15 strict JSON hash changed.'
);
$g16a_findings = json_decode(g16a_read($g16a_artifact_path), true, 512, JSON_THROW_ON_ERROR);
g16a_assert(is_array($g16a_findings), 'Authoritative strict JSON must decode to an array.');
g16a_same(167, count($g16a_findings), 'Authoritative finding total changed.');
g16a_same(125, count(array_filter($g16a_findings, static fn(array $row): bool => ($row['type'] ?? '') === 'ERROR')), 'Authoritative ERROR count changed.');
g16a_same(42, count(array_filter($g16a_findings, static fn(array $row): bool => ($row['type'] ?? '') === 'WARNING')), 'Authoritative WARNING count changed.');

$g16a_logging_code = 'WordPress.PHP.DevelopmentFunctions.error_log_error_log';
$g16a_owned_files = array(
	'/privateincludes/vendor-applications.php',
	'/privateincludes/core/vendor-application-confirmation.php',
	'/privateincludes/core/goals-forecast.php',
);
$g16a_logging_rows = array_values(array_filter(
	$g16a_findings,
	static fn(array $row): bool => ($row['code'] ?? '') === $g16a_logging_code
));
$g16a_owned_rows = array_values(array_filter(
	$g16a_logging_rows,
	static fn(array $row): bool => in_array((string) ($row['file'] ?? ''), $g16a_owned_files, true)
));
$g16a_inventory = array_map(
	static fn(array $row): string => sprintf('%s:%d:%d', (string) $row['file'], (int) $row['line'], (int) $row['column']),
	$g16a_owned_rows
);
$g16a_expected_inventory = array(
	'/privateincludes/vendor-applications.php:765:13',
	'/privateincludes/vendor-applications.php:795:13',
	'/privateincludes/vendor-applications.php:826:9',
	'/privateincludes/vendor-applications.php:2211:9',
	'/privateincludes/vendor-applications.php:2239:9',
	'/privateincludes/vendor-applications.php:2247:9',
	'/privateincludes/vendor-applications.php:2253:9',
	'/privateincludes/vendor-applications.php:2998:13',
	'/privateincludes/vendor-applications.php:3004:17',
	'/privateincludes/core/vendor-application-confirmation.php:1004:13',
	'/privateincludes/core/goals-forecast.php:14:3',
);
g16a_same($g16a_expected_inventory, $g16a_inventory, 'Group A must own the exact eleven authoritative logging rows.');
g16a_same(41, count($g16a_logging_rows), 'Authoritative direct error-log count changed.');
g16a_same(11, count($g16a_owned_rows), 'Group A owned logging count changed.');
g16a_same(0, count($g16a_owned_rows) - 11, 'Projected Group A logging count must be zero.');
g16a_same(30, count($g16a_logging_rows) - count($g16a_owned_rows), 'Direct error-log findings outside Group A must remain 30.');
g16a_same(31, 42 - count($g16a_owned_rows), 'All logging findings outside Group A, including debug_backtrace, must remain 31.');

$g16a_vendor_artifact_rows = array_values(array_filter(
	$g16a_findings,
	static fn(array $row): bool => ($row['file'] ?? '') === '/privateincludes/vendor-applications.php'
));
$g16a_adjacent = array_values(array_filter(
	$g16a_vendor_artifact_rows,
	static fn(array $row): bool => ($row['code'] ?? '') !== $g16a_logging_code
));
g16a_same(
	array(
		'2424:9:PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent',
		'2447:10:WordPress.Security.EscapeOutput.OutputNotEscaped',
		'2449:82:WordPress.Security.EscapeOutput.OutputNotEscaped',
	),
	array_map(static fn(array $row): string => sprintf('%d:%d:%s', (int) $row['line'], (int) $row['column'], (string) $row['code']), $g16a_adjacent),
	'The exact three adjacent accepted vendor rows must remain outside Group A.'
);

$g16a_relatives = array(
	'includes/vendor-applications.php',
	'includes/core/vendor-application-confirmation.php',
	'includes/core/goals-forecast.php',
);
$g16a_expected_projection_hashes = array(
	'mirror' => array(
		'includes/vendor-applications.php' => '096a6c0edfaf557eaab3ceda3f0f313659f96213c069ba4243c3e4aee3da1d73',
		'includes/core/vendor-application-confirmation.php' => '6fcf62e4276c305bccf62a5d9fb341b960c428db419e09dafc76ba45bc6b0f60',
		'includes/core/goals-forecast.php' => '21f50552b98982cc6f092d61f7c241c089058acfc02bd93fc7c7d6c07154e725',
	),
	'shadow' => array(
		'includes/vendor-applications.php' => 'b285be31fe7934decdb4d5640800303a32f3e82f84710df52b191abd898c004b',
		'includes/core/vendor-application-confirmation.php' => '98ccc52abb1a420d7ce2d935864c034296480aead8f0a58a468b519416b74fcd',
		'includes/core/goals-forecast.php' => '21f50552b98982cc6f092d61f7c241c089058acfc02bd93fc7c7d6c07154e725',
	),
);
$g16a_sources = array('mirror' => array(), 'shadow' => array());
foreach (array('mirror' => $g16a_root, 'shadow' => $g16a_shadow_root) as $tree => $tree_root) {
	foreach ($g16a_relatives as $relative) {
		$source = g16a_read($tree_root . '/' . $relative);
		$g16a_sources[$tree][$relative] = $source;
		$projection = g16a_restore_group_a_baseline($relative, $source);
		g16a_same($g16a_expected_projection_hashes[$tree][$relative], hash('sha256', $projection), $tree . ' full-file pre-G16 projection changed: ' . $relative);
		g16a_same(0, preg_match_all('/phpcs:(?:ignore|disable)[^\n]*(?:DevelopmentFunctions|error_log)/i', $source), $tree . ' must not suppress logging findings: ' . $relative);
	}
}
$g16a_mutation = str_replace("'timeout' => 8", "'timeout' => 9", g16a_restore_group_a_baseline('includes/vendor-applications.php', $g16a_sources['mirror']['includes/vendor-applications.php']), $g16a_mutation_count);
g16a_same(1, $g16a_mutation_count, 'Owned Turnstile mutation anchor should occur exactly once.');
g16a_assert(hash('sha256', $g16a_mutation) !== $g16a_expected_projection_hashes['mirror']['includes/vendor-applications.php'], 'Immutable full-file projection must reject an owned runtime mutation.');

$g16a_exact_calls = array(
	'vendor_app_vendor_create_failed' => "bvmgr_record_operational_issue('vendor_app_vendor_create_failed', array( 'service' => 'wordpress', 'operation' => 'create_vendor', 'status' => 'failed', 'entity_type' => 'vendor_application', 'post_id' => \$app_id, ), \$vendor_id);",
	'vendor_app_submitting_user_missing' => "bvmgr_record_operational_issue('vendor_app_submitting_user_missing', array( 'service' => 'wordpress', 'operation' => 'link_submitting_user', 'status' => 'missing', 'entity_type' => 'user', 'entity_id' => \$user_id, 'vendor_id' => \$vendor_id, 'post_id' => \$app_id, ));",
	'vendor_app_user_link_failed' => "bvmgr_record_operational_issue('vendor_app_user_link_failed', array( 'service' => 'wordpress', 'operation' => 'link_submitting_user', 'status' => 'failed', 'entity_type' => 'user', 'entity_id' => \$user_id, 'vendor_id' => \$vendor_id, 'post_id' => \$app_id, ));",
	'vendor_apply_turnstile_config_missing' => "bvmgr_record_operational_issue('vendor_apply_turnstile_config_missing', array( 'service' => 'turnstile', 'provider' => 'cloudflare', 'operation' => 'siteverify', 'status' => 'blocked', 'reason' => 'missing_configuration', ));",
	'vendor_apply_turnstile_request_failed' => "bvmgr_record_operational_issue('vendor_apply_turnstile_request_failed', array( 'service' => 'turnstile', 'provider' => 'cloudflare', 'operation' => 'siteverify', 'status' => 'failed', 'reason' => 'transport_error', ), \$resp);",
	'vendor_apply_turnstile_response_failed' => "bvmgr_record_operational_issue('vendor_apply_turnstile_response_failed', array( 'service' => 'turnstile', 'provider' => 'cloudflare', 'operation' => 'siteverify', 'status' => 'failed', 'reason' => 'unusable_response', 'http_status' => \$code, ));",
	'vendor_apply_turnstile_payload_invalid' => "bvmgr_record_operational_issue('vendor_apply_turnstile_payload_invalid', array( 'service' => 'turnstile', 'provider' => 'cloudflare', 'operation' => 'decode_response', 'status' => 'failed', 'reason' => 'invalid_json', ));",
	'vendor_app_vendor_type_unresolved' => "bvmgr_record_operational_issue('vendor_app_vendor_type_unresolved', array( 'service' => 'wordpress', 'operation' => 'resolve_vendor_type', 'status' => 'skipped', 'reason' => 'term_unresolved', 'post_id' => \$app_id, ), \$slug);",
	'vendor_app_vendor_type_assignment_failed' => "bvmgr_record_operational_issue('vendor_app_vendor_type_assignment_failed', array( 'service' => 'wordpress', 'operation' => 'assign_vendor_type', 'status' => 'failed', 'vendor_id' => \$vendor_id, 'post_id' => \$app_id, ), \$set);",
	'vendor_app_review_ready_mail_failed' => "bvmgr_record_operational_issue('vendor_app_review_ready_mail_failed', array( 'service' => 'wp_mail', 'operation' => 'review_ready_notification', 'status' => 'failed', 'entity_type' => 'vendor_application', 'post_id' => \$app_id, ));",
	'goals_legacy_issue' => "bvmgr_record_operational_issue('goals_legacy_issue', array( 'service' => 'goals_forecast', 'operation' => 'legacy_log', 'status' => 'reported', ), \$message);",
	'goals_provider_hard_error_check_failed' => "bvmgr_record_operational_issue('goals_provider_hard_error_check_failed', array( 'service' => 'goals_forecast', 'provider' => 'square', 'operation' => 'hard_error_check', 'status' => 'failed', ), \$e);",
	'goals_provider_call_failed' => "bvmgr_record_operational_issue('goals_provider_call_failed', array( 'service' => 'goals_forecast', 'provider' => 'square', 'operation' => 'fetch_actuals', 'status' => 'failed', 'event_id' => \$event_plan_id, ), \$e);",
	'goals_actuals_refresh_failed' => "bvmgr_record_operational_issue('goals_actuals_refresh_failed', array( 'service' => 'goals_forecast', 'provider' => sanitize_key((string) (\$result['provider'] ?? 'none')), 'operation' => 'refresh_actuals', 'status' => 'failed', 'event_id' => \$event_plan_id, ), \$msg);",
	'goals_progress_capped' => "bvmgr_record_operational_issue('goals_progress_capped', array( 'service' => 'goals_forecast', 'operation' => 'compute_progress', 'status' => 'capped', 'count' => \$max_events, 'entity_type' => 'goal', 'entity_id' => absint(\$goal['id'] ?? 0), ));",
);
$g16a_event_files = array(
	'vendor_app_review_ready_mail_failed' => 'includes/core/vendor-application-confirmation.php',
	'goals_legacy_issue' => 'includes/core/goals-forecast.php',
	'goals_provider_hard_error_check_failed' => 'includes/core/goals-forecast.php',
	'goals_provider_call_failed' => 'includes/core/goals-forecast.php',
	'goals_actuals_refresh_failed' => 'includes/core/goals-forecast.php',
	'goals_progress_capped' => 'includes/core/goals-forecast.php',
);
foreach ($g16a_exact_calls as $event_code => $expected_call) {
	$relative = $g16a_event_files[$event_code] ?? 'includes/vendor-applications.php';
	g16a_same($expected_call, g16a_normalize(g16a_operational_call($g16a_sources['mirror'][$relative], $event_code)), 'Exact event/context/error contract changed: ' . $event_code);
}

foreach (array('mirror', 'shadow') as $tree) {
	g16a_same(0, preg_match_all('/(?<![A-Za-z0-9_])error_log\s*\(/', implode("\n", $g16a_sources[$tree])), $tree . ' Group A targets must contain zero direct error_log calls.');
}
g16a_same(9, substr_count($g16a_sources['mirror']['includes/vendor-applications.php'], 'bvmgr_record_operational_issue('), 'Mirror vendor source must contain all nine Group A calls.');
g16a_same(8, substr_count($g16a_sources['shadow']['includes/vendor-applications.php'], 'bvmgr_record_operational_issue('), 'Shadow vendor source must contain only eight corresponding Group A calls.');
g16a_same(1, substr_count($g16a_sources['mirror']['includes/core/vendor-application-confirmation.php'], "bvmgr_record_operational_issue('vendor_app_review_ready_mail_failed'"), 'Mirror confirmation event count changed.');
g16a_same(5, substr_count($g16a_sources['mirror']['includes/core/goals-forecast.php'], 'bvmgr_record_operational_issue('), 'Mirror goals event count changed.');

foreach (array('bvmgr_vendor_app_get_or_create_vendor', 'bvmgr_vendor_app_link_submitting_user_to_vendor', 'bvmgr_vendor_app_sync_vendor_from_application') as $function) {
	g16a_same(g16a_extract_function($g16a_sources['mirror']['includes/vendor-applications.php'], $function), g16a_extract_function($g16a_sources['shadow']['includes/vendor-applications.php'], $function), 'Shared vendor boundary parity changed: ' . $function);
}
foreach (array('bvmgr_vendor_app_send_review_ready_admin_notification', 'bvmgr_vendor_app_maybe_notify_review_ready') as $function) {
	g16a_same(g16a_extract_function($g16a_sources['mirror']['includes/core/vendor-application-confirmation.php'], $function), g16a_extract_function($g16a_sources['shadow']['includes/core/vendor-application-confirmation.php'], $function), 'Shared confirmation boundary parity changed: ' . $function);
}
g16a_same($g16a_sources['mirror']['includes/core/goals-forecast.php'], $g16a_sources['shadow']['includes/core/goals-forecast.php'], 'Goals runtime must retain full mirror/shadow parity.');

$g16a_mirror_turnstile = g16a_extract_function($g16a_sources['mirror']['includes/vendor-applications.php'], 'bvmgr_vendor_apply_verify_turnstile');
$g16a_shadow_turnstile = g16a_extract_function($g16a_sources['shadow']['includes/vendor-applications.php'], 'bvmgr_vendor_apply_verify_turnstile');
foreach (array(
	'vendor_apply_turnstile_config_missing',
	'vendor_apply_turnstile_request_failed',
	'vendor_apply_turnstile_response_failed',
) as $event_code) {
	g16a_same(g16a_normalize(g16a_operational_call($g16a_mirror_turnstile, $event_code)), g16a_normalize(g16a_operational_call($g16a_shadow_turnstile, $event_code)), 'Corresponding Turnstile event must stay synchronized: ' . $event_code);
}
g16a_same(1, substr_count($g16a_mirror_turnstile, "bvmgr_record_operational_issue('vendor_apply_turnstile_payload_invalid'"), 'Mirror must retain its structured invalid-JSON event.');
g16a_same(0, substr_count($g16a_shadow_turnstile, "bvmgr_record_operational_issue('vendor_apply_turnstile_payload_invalid'"), 'Shadow must not gain the mirror-only invalid-JSON event.');
g16a_contains('bvmgr_vendor_apply_turnstile_response_token()', $g16a_mirror_turnstile, 'Mirror must retain its normalized Turnstile token helper.');
g16a_contains("bvmgr_request_read_scalar(\$_POST, 'cf-turnstile-response')", $g16a_shadow_turnstile, 'Shadow must retain its established direct normalized token reader.');
g16a_contains('bvmgr_vendor_apply_parse_turnstile_siteverify_body($body)', $g16a_mirror_turnstile, 'Mirror must retain its structured JSON parser.');
g16a_contains('json_decode($body, true)', $g16a_shadow_turnstile, 'Shadow must retain its established JSON decoder branch.');
g16a_assert($g16a_mirror_turnstile !== $g16a_shadow_turnstile, 'Intentional Turnstile mirror/shadow divergence must remain explicit.');

defined('ABSPATH') || define('ABSPATH', '/srv/wordpress/');
defined('BVMGR_PLUGIN_PATH') || define('BVMGR_PLUGIN_PATH', '/srv/wordpress/wp-content/plugins/vms/');
defined('BVMGR_VENDOR_CPT') || define('BVMGR_VENDOR_CPT', 'vms_vendor');

final class G16A_WP_Error
{
	private string $code;
	private string $message;

	public function __construct(string $code, string $message)
	{
		$this->code = $code;
		$this->message = $message;
	}

	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
	public function get_error_messages(): array { return array($this->message); }
}
class_alias(G16A_WP_Error::class, 'WP_Error');

final class WP_User
{
	public int $ID = 71;
	public string $user_login = 'safe-user';
}

function sanitize_key($value): string
{
	$clean = preg_replace('/[^a-z0-9_-]+/', '', strtolower(is_scalar($value) ? (string) $value : ''));
	return is_string($clean) ? $clean : '';
}
function absint($value): int { return abs((int) $value); }
function is_wp_error($value): bool { return $value instanceof WP_Error; }
function __(string $text, string $domain = ''): string { unset($domain); return $text; }
function get_option(string $key, $default = false) { return $key === 'admin_email' ? 'recipient-sentinel@example.test' : $default; }
function update_option(string $key, $value, bool $autoload = false): bool { unset($key, $value, $autoload); return true; }
function is_admin(): bool { return false; }
function wp_doing_ajax(): bool { return false; }
function wp_doing_cron(): bool { return false; }
function bvmgr_admin_guard_request_method(): string { return 'post'; }
function bvmgr_resource_fingerprint_current_admin_page(): string { return ''; }
function bvmgr_admin_guard_current_screen_id(): string { return ''; }
function bvmgr_request_current_uri(): string { return '/vendor/apply?token=token-sentinel'; }
function bvmgr_resource_fingerprint_store_entry(array $entry): void { $GLOBALS['g16a_entries'][] = $entry; }

function get_post(int $post_id): object { return (object) array('ID' => $post_id, 'post_type' => 'vms_vendor_application', 'post_title' => 'Safe Vendor'); }
function get_post_type(int $post_id): string { return (string) ($GLOBALS['g16a_post_types'][$post_id] ?? ''); }
function get_post_meta(int $post_id, string $key, bool $single = false)
{
	unset($single);
	return $GLOBALS['g16a_meta'][$post_id][$key] ?? '';
}
function update_post_meta(int $post_id, string $key, $value): bool
{
	$GLOBALS['g16a_meta_updates'][] = compact('post_id', 'key', 'value');
	$GLOBALS['g16a_meta'][$post_id][$key] = $value;
	return true;
}
function wp_insert_post(array $args, bool $wp_error = false)
{
	unset($args, $wp_error);
	return $GLOBALS['g16a_insert_result'];
}
function bvmgr_vendor_app_cpt_slugs(): array { return array('vms_vendor_application'); }
function bvmgr_vendor_app_sync_vendor_from_application(int $app_id, int $vendor_id): int { unset($app_id); return $vendor_id; }
function bvmgr_vendor_app_get_submitting_user_id(int $app_id): int { unset($app_id); return (int) $GLOBALS['g16a_user_id']; }
function get_userdata(int $user_id) { unset($user_id); return $GLOBALS['g16a_user']; }
function bvmgr_vendor_user_link_upsert(int $vendor_id, int $user_id, array $args, int $actor_user_id): bool
{
	unset($vendor_id, $user_id, $args, $actor_user_id);
	return (bool) $GLOBALS['g16a_link_result'];
}

function bvmgr_vendor_apply_turnstile_site_key(): string { return (string) $GLOBALS['g16a_site_key']; }
function bvmgr_vendor_apply_turnstile_secret_key(): string { return (string) $GLOBALS['g16a_secret_key']; }
function bvmgr_vendor_apply_turnstile_response_token(): string { return (string) $GLOBALS['g16a_token']; }
function bvmgr_request_remote_addr(): string { return '203.0.113.77'; }
function wp_remote_post(string $url, array $args) { $GLOBALS['g16a_remote_call'] = compact('url', 'args'); return $GLOBALS['g16a_remote_result']; }
function wp_remote_retrieve_response_code($response): int { return (int) ($response['response']['code'] ?? 0); }
function wp_remote_retrieve_body($response): string { return (string) ($response['body'] ?? ''); }
function bvmgr_vendor_apply_parse_turnstile_siteverify_body(string $body): array { unset($body); return $GLOBALS['g16a_parsed_body']; }

function bvmgr_vendor_app_get_confirmation_email(int $app_id): string { unset($app_id); return 'submitter-sentinel@example.test'; }
function get_the_title(int $post_id): string { unset($post_id); return 'Safe Vendor'; }
function apply_filters(string $hook, $value)
{
	if ($hook === 'vms_goals_progress_max_events') {
		return $GLOBALS['g16a_max_events'];
	}
	return $value;
}
function bvmgr_vendor_app_meta_key(string $suffix): string { return '_vms_app_' . $suffix; }
function bvmgr_vendor_app_vendor_type_label(string $type): string { return $type === '' ? 'Unknown' : $type; }
function bvmgr_vendor_app_confirmation_state_label(string $state): string { return $state; }
function bvmgr_vendor_app_get_confirmation_state(int $app_id): string { unset($app_id); return 'confirmed'; }
function admin_url(string $path = ''): string { return 'https://example.test/wp-admin/' . ltrim($path, '/'); }
function wp_mail($to, string $subject, string $message): bool
{
	$GLOBALS['g16a_mail_calls'][] = compact('to', 'subject', 'message');
	return (bool) $GLOBALS['g16a_mail_result'];
}
function bvmgr_vendor_app_is_review_ready(int $app_id): bool { unset($app_id); return true; }
function current_time(string $type): string { unset($type); return '2026-08-08 12:00:00'; }

function vms_square_actuals_has_hard_errors(array $raw): bool { unset($raw); throw new RuntimeException($GLOBALS['g16a_exception_message'], 731); }
function vms_pos_provider_detect(): array { return array('square' => array('slug' => 'square')); }
function vms_goals_get_settings(): array { return array('enabled_actuals_providers' => array('square'), 'default_trailing_window_events' => 2); }
function vms_square_get_event_actuals(int $event_plan_id, array $args): array { unset($event_plan_id, $args); throw new RuntimeException($GLOBALS['g16a_exception_message'], 732); }
function vms_goals_normalize_provider_actuals(string $provider, array $raw): array { return array('ok' => true, 'provider' => $provider, 'raw' => $raw); }
function vms_goals_get_event_ids_in_period(string $start, string $end, int $limit): array { unset($start, $end, $limit); return range(1001, 1026); }
function wp_timezone(): DateTimeZone { return new DateTimeZone('UTC'); }
function wp_date(string $format, $timestamp = null, $timezone = null): string { unset($format, $timestamp, $timezone); return '2026-08-08'; }
function vms_goals_get_event_pnl(int $event_id, array $args): array { unset($event_id, $args); return array(); }
function vms_goals_metric_value_from_pnl(string $metric, array $pnl): int { unset($metric, $pnl); return 0; }

function g16a_reset_runtime(): void
{
	$GLOBALS['g16a_entries'] = array();
	$GLOBALS['g16a_meta'] = array();
	$GLOBALS['g16a_meta_updates'] = array();
	$GLOBALS['g16a_post_types'] = array();
	$GLOBALS['g16a_insert_result'] = 0;
	$GLOBALS['g16a_user_id'] = 71;
	$GLOBALS['g16a_user'] = new WP_User();
	$GLOBALS['g16a_link_result'] = true;
	$GLOBALS['g16a_site_key'] = 'site-key';
	$GLOBALS['g16a_secret_key'] = 'secret-key';
	$GLOBALS['g16a_token'] = 'token-sentinel-raw';
	$GLOBALS['g16a_remote_result'] = array('response' => array('code' => 200), 'body' => '{"success":true}');
	$GLOBALS['g16a_remote_call'] = array();
	$GLOBALS['g16a_parsed_body'] = array('success' => true);
	$GLOBALS['g16a_mail_calls'] = array();
	$GLOBALS['g16a_mail_result'] = true;
	$GLOBALS['g16a_exception_message'] = 'transport sentinel token-secret recipient@example.test 198.51.100.8 /private/tmp/raw.php?nonce=bad User-Agent sentinel';
	$GLOBALS['g16a_max_events'] = 25;
}

function g16a_last_issue(): array
{
	$entry = end($GLOBALS['g16a_entries']);
	g16a_assert(is_array($entry), 'Expected a persisted operational entry.');
	$issue = $entry['flags']['operational_issue'][0] ?? null;
	g16a_assert(is_array($issue), 'Expected one persisted operational issue.');
	return $issue;
}

function g16a_assert_entry_redacted(array $entry, string $message): void
{
	$encoded = json_encode($entry, JSON_UNESCAPED_SLASHES);
	g16a_assert(is_string($encoded), $message . ' must encode.');
	foreach (array('token-sentinel', 'secret-key', 'site-key', '203.0.113.77', 'recipient@example.test', '198.51.100.8', '/private/tmp/raw.php', 'nonce=bad', 'User-Agent sentinel', 'submitter-sentinel@example.test') as $sentinel) {
		g16a_assert(stripos($encoded, $sentinel) === false, $message . ' persisted forbidden sentinel: ' . $sentinel);
	}
}

$g16a_runtime_source = g16a_read($g16a_root . '/includes/runtime-guards.php');
foreach (array(
	'bvmgr_operational_issue_value_is_tainted',
	'bvmgr_operational_issue_request_path',
	'bvmgr_operational_issue_error_identity',
	'bvmgr_operational_issue_context',
	'bvmgr_record_operational_issue',
) as $helper) {
	eval(g16a_extract_guarded_function($g16a_runtime_source, $helper));
}
foreach (array(
	array('includes/vendor-applications.php', 'bvmgr_vendor_app_get_or_create_vendor'),
	array('includes/vendor-applications.php', 'bvmgr_vendor_app_link_submitting_user_to_vendor'),
	array('includes/vendor-applications.php', 'bvmgr_vendor_apply_verify_turnstile'),
	array('includes/core/vendor-application-confirmation.php', 'bvmgr_vendor_app_send_review_ready_admin_notification'),
	array('includes/core/vendor-application-confirmation.php', 'bvmgr_vendor_app_maybe_notify_review_ready'),
	array('includes/core/goals-forecast.php', 'vms_goals_log'),
	array('includes/core/goals-forecast.php', 'vms_goals_provider_has_hard_errors'),
	array('includes/core/goals-forecast.php', 'vms_pos_get_event_actuals'),
	array('includes/core/goals-forecast.php', 'vms_goals_refresh_event_actuals'),
	array('includes/core/goals-forecast.php', 'vms_goals_compute_goal_progress'),
) as $target) {
	eval(g16a_extract_function($g16a_sources['mirror'][$target[0]], $target[1]));
}

g16a_reset_runtime();
$g16a_probe_error = new WP_Error('transport_failure', $GLOBALS['g16a_exception_message']);
g16a_assert(bvmgr_record_operational_issue('vendor_apply_turnstile_request_failed', array(
	'service' => 'turnstile',
	'provider' => 'cloudflare',
	'operation' => 'siteverify',
	'status' => 'failed',
	'reason' => 'transport_error',
	'correlation' => 'token-sentinel-raw',
	'uri_sentinel' => '/vendor/apply?nonce=bad',
), $g16a_probe_error), 'Operational adapter should accept a valid fixed event.');
$g16a_probe_entry = $GLOBALS['g16a_entries'][0];
$g16a_probe_issue = g16a_last_issue();
g16a_same(array('service' => 'turnstile', 'provider' => 'cloudflare', 'operation' => 'siteverify', 'status' => 'failed', 'reason' => 'transport_error'), $g16a_probe_issue['context'], 'Adapter must retain only allowlisted fixed context.');
g16a_same('', $g16a_probe_entry['request_uri'], 'Operational issues must not store ambient or rejected request paths.');
g16a_same('transport_failure', $g16a_probe_issue['error']['error_code'] ?? '', 'Safe error code should remain available.');
g16a_assert(preg_match('/^[a-f0-9]{24}$/', (string) ($g16a_probe_issue['error']['error_fingerprint'] ?? '')) === 1, 'Raw errors must persist only a deterministic 24-hex fingerprint.');
$g16a_first_fingerprint = $g16a_probe_issue['error']['error_fingerprint'];
bvmgr_record_operational_issue('vendor_apply_turnstile_request_failed', array('service' => 'turnstile'), $g16a_probe_error);
g16a_same($g16a_first_fingerprint, g16a_last_issue()['error']['error_fingerprint'] ?? '', 'Equivalent raw errors must have deterministic identities.');
g16a_assert_entry_redacted($g16a_probe_entry, 'Direct sentinel probe');

g16a_reset_runtime();
$GLOBALS['g16a_insert_result'] = new WP_Error('vendor_create_failed', $GLOBALS['g16a_exception_message']);
g16a_same(0, bvmgr_vendor_app_get_or_create_vendor(41), 'Vendor creation failure must retain its zero result.');
$g16a_issue = g16a_last_issue();
g16a_same('vendor_app_vendor_create_failed', $g16a_issue['event_code'], 'Vendor create failure event changed.');
g16a_same(array('service' => 'wordpress', 'operation' => 'create_vendor', 'status' => 'failed', 'entity_type' => 'vendor_application', 'post_id' => 41), $g16a_issue['context'], 'Vendor create failure context changed.');
g16a_same('vendor_create_failed', $g16a_issue['error']['error_code'] ?? '', 'Vendor create WP_Error should retain only its safe code.');
g16a_assert_entry_redacted($GLOBALS['g16a_entries'][0], 'Vendor creation failure');

g16a_reset_runtime();
$GLOBALS['g16a_insert_result'] = 0;
g16a_same(0, bvmgr_vendor_app_get_or_create_vendor(42), 'Non-WP_Error vendor creation failure must retain its zero result.');
g16a_assert(!isset(g16a_last_issue()['error']), 'Non-error vendor create result should not invent an error identity.');

g16a_reset_runtime();
$GLOBALS['g16a_user'] = false;
$g16a_missing_user = bvmgr_vendor_app_link_submitting_user_to_vendor(51, 61, 7);
g16a_assert($g16a_missing_user instanceof WP_Error, 'Missing submitting user must retain a caller-visible WP_Error.');
g16a_same('vms_vendor_app_missing_user', $g16a_missing_user->get_error_code(), 'Missing-user public error code changed.');
g16a_same('The submitting website account no longer exists.', $g16a_missing_user->get_error_message(), 'Missing-user public notice changed.');
g16a_same(array('service' => 'wordpress', 'operation' => 'link_submitting_user', 'status' => 'missing', 'entity_type' => 'user', 'entity_id' => 71, 'vendor_id' => 61, 'post_id' => 51), g16a_last_issue()['context'], 'Missing-user safe context changed.');

g16a_reset_runtime();
$GLOBALS['g16a_link_result'] = false;
$g16a_failed_link = bvmgr_vendor_app_link_submitting_user_to_vendor(52, 62, 8);
g16a_assert($g16a_failed_link instanceof WP_Error, 'Failed user link must retain a caller-visible WP_Error.');
g16a_same('vms_vendor_app_link_failed', $g16a_failed_link->get_error_code(), 'Failed-link public error code changed.');
g16a_same('The website account link could not be saved.', $g16a_failed_link->get_error_message(), 'Failed-link public notice changed.');
g16a_same('vendor_app_user_link_failed', g16a_last_issue()['event_code'], 'Failed-link event changed.');

g16a_reset_runtime();
g16a_same(true, bvmgr_vendor_app_link_submitting_user_to_vendor(53, 63, 9), 'Successful user link must retain true.');
g16a_same(array(), $GLOBALS['g16a_entries'], 'Successful user link must not record a failure.');

g16a_reset_runtime();
$GLOBALS['g16a_site_key'] = '';
g16a_same(false, bvmgr_vendor_apply_verify_turnstile(), 'Missing Turnstile configuration must still fail closed.');
g16a_same('vendor_apply_turnstile_config_missing', g16a_last_issue()['event_code'], 'Turnstile configuration event changed.');
g16a_same(array(), $GLOBALS['g16a_remote_call'], 'Missing Turnstile configuration must not call the provider.');

g16a_reset_runtime();
$GLOBALS['g16a_token'] = '';
g16a_same(false, bvmgr_vendor_apply_verify_turnstile(), 'Missing Turnstile token must still fail closed.');
g16a_same(array(), $GLOBALS['g16a_entries'], 'Missing Turnstile token must not create a new operational issue.');

g16a_reset_runtime();
$GLOBALS['g16a_remote_result'] = new WP_Error('transport_failure', $GLOBALS['g16a_exception_message']);
g16a_same(false, bvmgr_vendor_apply_verify_turnstile(), 'Turnstile transport failure must retain false.');
g16a_same('vendor_apply_turnstile_request_failed', g16a_last_issue()['event_code'], 'Turnstile transport event changed.');
g16a_assert_entry_redacted($GLOBALS['g16a_entries'][0], 'Turnstile transport failure');
g16a_same('token-sentinel-raw', $GLOBALS['g16a_remote_call']['args']['body']['response'], 'Turnstile provider request token contract changed.');
g16a_same('203.0.113.77', $GLOBALS['g16a_remote_call']['args']['body']['remoteip'], 'Turnstile provider request IP contract changed.');

g16a_reset_runtime();
$GLOBALS['g16a_remote_result'] = array('response' => array('code' => 503), 'body' => $GLOBALS['g16a_exception_message']);
g16a_same(false, bvmgr_vendor_apply_verify_turnstile(), 'Unusable Turnstile response must retain false.');
$g16a_issue = g16a_last_issue();
g16a_same('vendor_apply_turnstile_response_failed', $g16a_issue['event_code'], 'Turnstile response event changed.');
g16a_same(503, $g16a_issue['context']['http_status'] ?? 0, 'Turnstile response should retain only its numeric HTTP status.');
g16a_assert_entry_redacted($GLOBALS['g16a_entries'][0], 'Turnstile unusable response');

g16a_reset_runtime();
$GLOBALS['g16a_remote_result'] = array('response' => array('code' => 200), 'body' => $GLOBALS['g16a_exception_message']);
$GLOBALS['g16a_parsed_body'] = array();
g16a_same(false, bvmgr_vendor_apply_verify_turnstile(), 'Invalid Turnstile payload must retain false.');
g16a_same('vendor_apply_turnstile_payload_invalid', g16a_last_issue()['event_code'], 'Mirror invalid-payload event changed.');
g16a_assert_entry_redacted($GLOBALS['g16a_entries'][0], 'Turnstile invalid payload');

g16a_reset_runtime();
g16a_same(true, bvmgr_vendor_apply_verify_turnstile(), 'Successful Turnstile verification must retain true.');
g16a_same(array(), $GLOBALS['g16a_entries'], 'Successful Turnstile verification must not record a failure.');

g16a_reset_runtime();
$g16a_slug = 'slug-token-sentinel-/private/tmp/raw.php';
bvmgr_record_operational_issue('vendor_app_vendor_type_unresolved', array(
	'service' => 'wordpress',
	'operation' => 'resolve_vendor_type',
	'status' => 'skipped',
	'reason' => 'term_unresolved',
	'post_id' => 81,
), $g16a_slug);
$g16a_issue = g16a_last_issue();
g16a_same(array('service' => 'wordpress', 'operation' => 'resolve_vendor_type', 'status' => 'skipped', 'reason' => 'term_unresolved', 'post_id' => 81), $g16a_issue['context'], 'Unresolved vendor type should persist no raw slug.');
g16a_same('string', $g16a_issue['error']['error_class'] ?? '', 'Unresolved vendor type should retain only string identity.');
g16a_assert_entry_redacted($GLOBALS['g16a_entries'][0], 'Unresolved vendor type');

g16a_reset_runtime();
$g16a_assignment_recorded = bvmgr_record_operational_issue('vendor_app_vendor_type_assignment_failed', array(
	'service' => 'wordpress',
	'operation' => 'assign_vendor_type',
	'status' => 'failed',
	'vendor_id' => 82,
	'post_id' => 83,
), new WP_Error('term_assignment_failed', $GLOBALS['g16a_exception_message']));
if ($g16a_assignment_recorded) {
	g16a_same('term_assignment_failed', g16a_last_issue()['error']['error_code'] ?? '', 'Vendor type assignment should retain only its safe WP_Error code.');
	g16a_assert_entry_redacted($GLOBALS['g16a_entries'][0], 'Vendor type assignment');
}

g16a_reset_runtime();
$GLOBALS['g16a_mail_result'] = false;
g16a_same(false, bvmgr_vendor_app_send_review_ready_admin_notification(91), 'Failed review-ready mail must retain false.');
$g16a_issue = g16a_last_issue();
g16a_same('vendor_app_review_ready_mail_failed', $g16a_issue['event_code'], 'Review-ready mail event changed.');
g16a_same(array('service' => 'wp_mail', 'operation' => 'review_ready_notification', 'status' => 'failed', 'entity_type' => 'vendor_application', 'post_id' => 91), $g16a_issue['context'], 'Review-ready mail context changed.');
g16a_same('recipient-sentinel@example.test', $GLOBALS['g16a_mail_calls'][0]['to'], 'Caller-visible mail recipient contract changed.');
g16a_assert_entry_redacted($GLOBALS['g16a_entries'][0], 'Review-ready mail failure');

g16a_reset_runtime();
$GLOBALS['g16a_mail_result'] = false;
g16a_same(false, bvmgr_vendor_app_maybe_notify_review_ready(92), 'Failed review-ready retry must retain false.');
g16a_same(array(), $GLOBALS['g16a_meta_updates'], 'Failed review-ready retry must not write its notified marker.');

g16a_reset_runtime();
$GLOBALS['g16a_mail_result'] = true;
g16a_same(true, bvmgr_vendor_app_maybe_notify_review_ready(93), 'Successful review-ready mail must retain true.');
g16a_same('_vms_app_review_ready_notified_at', $GLOBALS['g16a_meta_updates'][0]['key'] ?? '', 'Successful review-ready mail must retain its marker key.');
g16a_same('2026-08-08 12:00:00', $GLOBALS['g16a_meta_updates'][0]['value'] ?? '', 'Successful review-ready mail must retain its marker value.');
g16a_same(array(), $GLOBALS['g16a_entries'], 'Successful review-ready mail must not record a failure.');

g16a_reset_runtime();
$GLOBALS['g16a_meta'][94]['_vms_app_review_ready_notified_at'] = 'already-sent';
g16a_same(true, bvmgr_vendor_app_maybe_notify_review_ready(94), 'Existing review-ready marker must retain true.');
g16a_same(array(), $GLOBALS['g16a_mail_calls'], 'Existing review-ready marker must prevent a retry.');

g16a_reset_runtime();
vms_goals_log($GLOBALS['g16a_exception_message']);
g16a_same('goals_legacy_issue', g16a_last_issue()['event_code'], 'Goals compatibility shim event changed.');
g16a_same(array('service' => 'goals_forecast', 'operation' => 'legacy_log', 'status' => 'reported'), g16a_last_issue()['context'], 'Goals compatibility shim context changed.');
g16a_assert_entry_redacted($GLOBALS['g16a_entries'][0], 'Goals compatibility shim');

g16a_reset_runtime();
g16a_same(false, vms_goals_provider_has_hard_errors('square', array(), array()), 'Caught hard-error check exception must retain the fallback result.');
g16a_same('goals_provider_hard_error_check_failed', g16a_last_issue()['event_code'], 'Goals hard-error check event changed.');
g16a_same(731, (int) (g16a_last_issue()['error']['error_code'] ?? 0), 'Goals hard-error exception code changed.');
g16a_assert_entry_redacted($GLOBALS['g16a_entries'][0], 'Goals hard-error check');

g16a_reset_runtime();
$g16a_provider_result = vms_pos_get_event_actuals(101, array('mode' => 'refresh'));
g16a_same(false, $g16a_provider_result['ok'], 'Provider exception must retain its false result.');
g16a_same('square', $g16a_provider_result['provider'], 'Provider exception must retain its provider result.');
g16a_same('Square provider error: ' . $GLOBALS['g16a_exception_message'], $g16a_provider_result['errors'][0], 'Privileged caller-visible provider error must remain unchanged.');
g16a_same('goals_provider_call_failed', g16a_last_issue()['event_code'], 'Goals provider-call event changed.');
g16a_assert_entry_redacted($GLOBALS['g16a_entries'][0], 'Goals provider call');

g16a_reset_runtime();
$g16a_refresh_result = vms_goals_refresh_event_actuals(102, array('mode' => 'refresh'));
g16a_same(false, $g16a_refresh_result['ok'], 'Failed actuals refresh must retain false.');
g16a_same('Square provider error: ' . $GLOBALS['g16a_exception_message'], $g16a_refresh_result['message'], 'Privileged refresh result must retain its raw caller-visible message.');
g16a_same(array('goals_provider_call_failed', 'goals_actuals_refresh_failed'), array_map(static fn(array $entry): string => (string) ($entry['flags']['operational_issue'][0]['event_code'] ?? ''), $GLOBALS['g16a_entries']), 'Failed refresh must retain exact producer ordering.');
foreach ($GLOBALS['g16a_entries'] as $entry) {
	g16a_assert_entry_redacted($entry, 'Goals actuals refresh');
}
g16a_same(array('ok' => false, 'message' => 'Invalid event plan id.'), vms_goals_refresh_event_actuals(0), 'Invalid refresh target result changed.');

g16a_reset_runtime();
$g16a_progress = vms_goals_compute_goal_progress(array(
	'id' => 111,
	'metric' => 'true_profit',
	'target_cents' => 2500,
	'period_start_local' => '2026-08-01',
	'period_end_local' => '2026-09-01',
));
g16a_same(true, $g16a_progress['is_truncated'], 'Capped goal progress must retain truncation state.');
g16a_same(25, $g16a_progress['max_events_evaluated'], 'Capped goal progress must retain exact maximum.');
g16a_same(25, count($g16a_progress['remaining_rows']), 'Capped goal progress must retain exact evaluated row count.');
g16a_same(array('service' => 'goals_forecast', 'operation' => 'compute_progress', 'status' => 'capped', 'count' => 25, 'entity_type' => 'goal', 'entity_id' => 111), g16a_last_issue()['context'], 'Capped goal progress safe context changed.');

g16a_assert($g16a_assignment_recorded, 'Exact 40-character vendor assignment event must be accepted by the operational adapter.');

fwrite(STDOUT, "G16 operational logging Group A: PASS\n");
