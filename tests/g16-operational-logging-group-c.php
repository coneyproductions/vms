<?php
declare(strict_types=1);

$g16c_root = dirname(__DIR__);
$g16c_shadow = dirname($g16c_root, 2) . '/vms';
$g16c_artifact = '/tmp/wporg-datezero-g15.0zTh76/plugin-check.strict.json';

function g16c_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g16c_same($expected, $actual, string $message): void
{
	g16c_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function g16c_read(string $path): string
{
	$value = file_get_contents($path);
	g16c_assert(is_string($value) && $value !== '', 'Unable to read ' . $path);
	return $value;
}

function g16c_extract_function(string $source, string $name): string
{
	$start = strpos($source, 'function ' . $name . '(');
	$brace = $start === false ? false : strpos($source, '{', $start);
	g16c_assert($start !== false && $brace !== false, 'Missing function ' . $name);
	$depth = 1;
	for ($i = $brace + 1, $length = strlen($source); $i < $length; $i++) {
		$depth += $source[$i] === '{' ? 1 : 0;
		$depth -= $source[$i] === '}' ? 1 : 0;
		if ($depth === 0) {
			return substr($source, (int) $start, $i - (int) $start + 1);
		}
	}
	throw new RuntimeException('Unclosed function ' . $name);
}

function g16c_replace_once(string $source, string $current, string $replacement, string $message): string
{
	g16c_same(1, substr_count($source, $current), $message . ' count');
	return str_replace($current, $replacement, $source);
}





/** @param array<int,array{current:string,historical:string}> $specs */






$g16c_paths = array(
	'settings' => 'includes/admin/settings-page.php',
	'phase' => 'includes/integrations/ticketing-phase-b.php',
	'notifications' => 'includes/core/notifications.php',
	'ticket' => 'includes/ticketing/ticket-integrity-monitor.php',
);
$g16c_sources = array('mirror' => array(), 'shadow' => array());
foreach ($g16c_paths as $key => $relative) {
	$g16c_sources['mirror'][$key] = g16c_read($g16c_root . '/' . $relative);
	$g16c_sources['shadow'][$key] = g16c_read($g16c_shadow . '/' . $relative);
}

// Historical evidence-only gate retired; see docs/testing/phase-5b-test-baseline.md.
foreach (array('mirror', 'shadow') as $tree) {
	$combined = implode("\n", $g16c_sources[$tree]);
	g16c_same(2, preg_match_all('/(?<![A-Za-z0-9_])error_log\s*\(/', $combined), $tree . ' must retain exactly two direct last-resort calls.');
	g16c_same(2, preg_match_all('/phpcs:ignore WordPress\.PHP\.DevelopmentFunctions\.error_log_error_log -- [^\n]+/', $combined), $tree . ' must have exactly two line-local logging suppressions.');
	g16c_same(0, preg_match_all('/phpcs:(?:disable|ignoreFile)[^\n]*DevelopmentFunctions|phpcs:ignore WordPress\.PHP\.DevelopmentFunctions(?:\s|$)|phpcs:ignore WordPress\.PHP\.DevelopmentFunctions\.error_log_error_log\s*,/i', $combined), $tree . ' must not add a broad logging suppression.');
	g16c_same(0, substr_count($g16c_sources[$tree]['settings'], 'error_log('), $tree . ' settings must project its owned row to zero.');
	g16c_same(0, substr_count($g16c_sources[$tree]['phase'], 'error_log('), $tree . ' PhaseB must project its owned row to zero.');
	g16c_same(1, substr_count($g16c_sources[$tree]['notifications'], 'error_log('), $tree . ' notification must retain one suppressed fallback.');
	g16c_same(1, substr_count($g16c_sources[$tree]['ticket'], 'error_log('), $tree . ' Ticket fatal must retain one suppressed fallback.');
}

g16c_same($g16c_sources['mirror']['notifications'], $g16c_sources['shadow']['notifications'], 'Notification file must retain full parity.');
foreach (array('bvmgr_entitlements_sync_image_log', 'bvmgr_entitlements_sync_product_image_with_result', 'bvmgr_entitlements_sync_plan_image_changes') as $name) {
	g16c_same(g16c_extract_function($g16c_sources['mirror']['phase'], $name), g16c_extract_function($g16c_sources['shadow']['phase'], $name), 'PhaseB owned parity failed: ' . $name);
}
foreach (array('bvmgr_ticket_integrity_fatal_operation', 'bvmgr_ticket_integrity_fatal_source_scope', 'bvmgr_ticket_integrity_fatal_operational_context', 'bvmgr_ticket_integrity_fatal_guard_shutdown') as $name) {
	g16c_same(g16c_extract_function($g16c_sources['mirror']['ticket'], $name), g16c_extract_function($g16c_sources['shadow']['ticket'], $name), 'Ticket owned parity failed: ' . $name);
}
g16c_same(g16c_extract_function($g16c_sources['mirror']['settings'], 'bvmgr_handle_sync_entitlement_images'), g16c_extract_function($g16c_sources['shadow']['settings'], 'bvmgr_handle_sync_entitlement_images'), 'Settings owned handler parity failed.');

$g16c_phase = $g16c_sources['mirror']['phase'];
g16c_same(6, substr_count($g16c_phase, 'bvmgr_entitlements_sync_image_log('), 'PhaseB must contain the wrapper definition and five internal producers.');
foreach (array(
	'entitlement_image_sync_legacy',
	'entitlement_image_sync_product_failed',
	'entitlement_image_sync_product_save_failed',
	'entitlement_image_sync_product_completed',
	'entitlement_image_sync_product_result',
	'entitlement_image_sync_plan_skipped',
) as $event_code) {
	g16c_same(1, substr_count($g16c_phase, "'{$event_code}'"), 'PhaseB event-code count changed: ' . $event_code);
}
g16c_same(1, preg_match('/\x27entitlement_image_sync_product_save_failed\x27.{0,700}\$e\s*\n\s*\);/s', $g16c_phase), 'Caught Throwable must travel only through the adapter error argument.');
g16c_same(0, substr_count(g16c_extract_function($g16c_phase, 'bvmgr_entitlements_sync_image_log'), "'message'"), 'PhaseB wrapper must never put a raw message in context.');

$g16c_settings_handler = g16c_extract_function($g16c_sources['mirror']['settings'], 'bvmgr_handle_sync_entitlement_images');
g16c_assert(strpos($g16c_settings_handler, "bvmgr_entitlements_sync_image_log('entitlement_image_sync_backfill_completed'") !== false, 'Settings must prefer the PhaseB wrapper.');
g16c_assert(strpos($g16c_settings_handler, "bvmgr_record_operational_issue('entitlement_image_sync_backfill_completed'") !== false, 'Settings must fall back to the foundation adapter.');
g16c_assert(strpos($g16c_settings_handler, "'count' => (int) \$summary['errors']") !== false, 'Settings must retain only the bounded error count.');
g16c_same(0, substr_count($g16c_settings_handler, 'error_log('), 'Settings must not retain a direct fallback.');
$g16c_transient = strpos($g16c_settings_handler, "set_transient('vms_entitlement_image_sync_last'");
$g16c_record = strpos($g16c_settings_handler, "'entitlement_image_sync_backfill_completed'");
$g16c_redirect = strpos($g16c_settings_handler, 'wp_safe_redirect(');
g16c_assert($g16c_transient !== false && $g16c_record !== false && $g16c_redirect !== false && $g16c_transient < $g16c_record && $g16c_record < $g16c_redirect, 'Settings must preserve transient -> record -> redirect order.');

$g16c_notify = g16c_extract_function($g16c_sources['mirror']['notifications'], 'bvmgr_notify_insert_log');
g16c_same(1, substr_count($g16c_notify, 'bvmgr_record_operational_issue('), 'Notification failure must try the adapter exactly once.');
g16c_same(2, substr_count($g16c_notify, 'notification_log_insert_failed'), 'Notification fixed event must appear only in adapter and fallback payloads.');
g16c_assert(strpos($g16c_notify, "if (!\$recorded && function_exists('error_log'))") !== false, 'Notification fallback must require adapter false and an available direct logger.');
g16c_same(0, substr_count($g16c_notify, 'last_error'), 'Notification fallback must not retain database errors.');
g16c_same(0, substr_count($g16c_notify, 'wp_json_encode($entry)'), 'Notification fallback must not retain entry fields.');

$g16c_shutdown = g16c_extract_function($g16c_sources['mirror']['ticket'], 'bvmgr_ticket_integrity_fatal_guard_shutdown');
$g16c_direct = strpos($g16c_shutdown, "'[BVM operational] event=ticket_integrity_fatal_shutdown");
$g16c_state = strpos($g16c_shutdown, 'bvmgr_ticket_integrity_patch_daily_report_state(');
$g16c_option = strpos($g16c_shutdown, 'bvmgr_ticket_integrity_log_event(');
$g16c_final = strpos($g16c_shutdown, "['finalized'] = true");
g16c_assert($g16c_direct !== false && $g16c_state !== false && $g16c_option !== false && $g16c_final !== false && $g16c_direct < $g16c_state && $g16c_state < $g16c_option && $g16c_option < $g16c_final, 'Ticket fatal order must remain direct -> state -> option -> finalized.');
g16c_assert(strpos($g16c_shutdown, "if (function_exists('error_log'))") !== false, 'Ticket fatal fallback must remain safe when error_log is disabled.');
g16c_same(0, substr_count($g16c_shutdown, 'bvmgr_record_operational_issue('), 'Ticket option log must remain the sole structured sink.');
foreach (array('fatal_message', 'fatal_file', 'context=%', 'message=%', 'file=%') as $forbidden) {
	g16c_same(0, substr_count($g16c_shutdown, $forbidden), 'Ticket direct boundary leaked forbidden field: ' . $forbidden);
}

// Historical evidence-only gate retired; see docs/testing/phase-5b-test-baseline.md.
if (!defined('BVMGR_PLUGIN_PATH')) {
	define('BVMGR_PLUGIN_PATH', $g16c_root);
}
if (!function_exists('sanitize_key')) {
	function sanitize_key($value): string
	{
		return trim((string) preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)), '_-');
	}
}
if (!function_exists('sanitize_email')) {
	function sanitize_email($value): string
	{
		return filter_var((string) $value, FILTER_SANITIZE_EMAIL);
	}
}
if (!function_exists('__')) {
	function __($message, $domain = null): string
	{
		return (string) $message;
	}
}

foreach (array(
	'bvmgr_ticket_integrity_is_fatal_error',
	'bvmgr_ticket_integrity_is_memory_fatal',
	'bvmgr_ticket_integrity_fatal_operation',
	'bvmgr_ticket_integrity_fatal_source_scope',
	'bvmgr_ticket_integrity_fatal_operational_context',
) as $function) {
	eval(g16c_extract_function($g16c_sources['mirror']['ticket'], $function));
}
g16c_same('scan', bvmgr_ticket_integrity_fatal_operation('SCAN'), 'Ticket operation scan normalization failed.');
g16c_same('daily_report', bvmgr_ticket_integrity_fatal_operation('Daily_Report'), 'Ticket operation daily-report normalization failed.');
g16c_same('unknown', bvmgr_ticket_integrity_fatal_operation('send_email'), 'Ticket operation allowlist failed.');
g16c_same('includes_ticketing_workerphp', bvmgr_ticket_integrity_fatal_source_scope($g16c_root . '/includes/ticketing/worker.php'), 'Plugin-relative source token changed.');
g16c_same('external', bvmgr_ticket_integrity_fatal_source_scope($g16c_root . '-collision/secret.php'), 'Plugin-root prefix collision must be external.');
g16c_same('external', bvmgr_ticket_integrity_fatal_source_scope($g16c_root . '/includes/../secret.php'), 'Traversal-like source must be external.');

$g16c_sentinel = 'recipient@example.test token=TOPSECRET uri=/private/path sql=SELECT-all';
$g16c_fatal_error = array(
	'type' => E_ERROR,
	'message' => 'Allowed memory size exhausted ' . $g16c_sentinel,
	'file' => $g16c_root . '/includes/ticketing/worker.php',
	'line' => 812,
);
$g16c_contexts = bvmgr_ticket_integrity_fatal_operational_context(
	'guard-secret',
	'Daily_Report',
	array('trigger' => 'CRON Daily', 'mode' => 'Email Now', 'recipient' => 'recipient@example.test', 'arbitrary' => $g16c_sentinel),
	$g16c_fatal_error,
	123.45
);
g16c_same(array('operation', 'memory_exhausted', 'fatal_type', 'line', 'source_scope', 'correlation'), array_keys($g16c_contexts['direct']), 'Ticket direct allowlist changed.');
g16c_same('daily_report', $g16c_contexts['direct']['operation'], 'Ticket direct operation changed.');
g16c_same(1, $g16c_contexts['direct']['memory_exhausted'], 'Ticket memory characterization failed.');
g16c_same(E_ERROR, $g16c_contexts['direct']['fatal_type'], 'Ticket fatal type changed.');
g16c_same(812, $g16c_contexts['direct']['line'], 'Ticket fatal line changed.');
g16c_assert(preg_match('/^[a-f0-9]{24}$/', $g16c_contexts['direct']['correlation']) === 1, 'Ticket correlation must be 24 lowercase hex.');
g16c_same($g16c_contexts['direct']['correlation'], $g16c_contexts['option']['correlation'], 'Ticket direct/option correlation must match.');
g16c_same('crondaily', $g16c_contexts['option']['trigger'], 'Ticket option trigger changed.');
g16c_same('emailnow', $g16c_contexts['option']['mode'], 'Ticket option mode changed.');
g16c_same(123.5, $g16c_contexts['option']['peak_memory_mb'], 'Ticket option peak-memory bound changed.');
g16c_same(0, substr_count(json_encode($g16c_contexts), $g16c_sentinel), 'Ticket operational contexts leaked sentinel data.');

$GLOBALS['g16c_order'] = array();
$GLOBALS['g16c_direct_logs'] = array();
$GLOBALS['g16c_state_patches'] = array();
$GLOBALS['g16c_option_logs'] = array();
function g16c_capture_error_log(string $message): void
{
	$GLOBALS['g16c_order'][] = 'direct';
	$GLOBALS['g16c_direct_logs'][] = $message;
}
function bvmgr_ticket_integrity_patch_daily_report_state(array $changes): void
{
	$GLOBALS['g16c_order'][] = 'state';
	$GLOBALS['g16c_state_patches'][] = $changes;
}
function bvmgr_ticket_integrity_log_event(string $event, string $message, array $context): void
{
	$GLOBALS['g16c_order'][] = 'option';
	$GLOBALS['g16c_option_logs'][] = array($event, $message, $context);
}

$g16c_shutdown_eval = $g16c_shutdown;
$g16c_shutdown_eval = g16c_replace_once($g16c_shutdown_eval, 'function bvmgr_ticket_integrity_fatal_guard_shutdown(', 'function g16c_ticket_integrity_fatal_guard_shutdown(', 'Ticket runtime rename failed.');
$g16c_shutdown_eval = g16c_replace_once($g16c_shutdown_eval, 'error_get_last()', '$GLOBALS[\'g16c_fatal_error\']', 'Ticket runtime fatal injection failed.');
$g16c_shutdown_eval = g16c_replace_once($g16c_shutdown_eval, 'error_log(', 'g16c_capture_error_log(', 'Ticket runtime logger capture failed.');
eval($g16c_shutdown_eval);

$GLOBALS['g16c_fatal_error'] = $g16c_fatal_error;
$GLOBALS['bvmgr_ticket_integrity_fatal_guard_reserve'] = 'reserve';
$GLOBALS['bvmgr_ticket_integrity_fatal_guards'] = array(
	'guard-secret' => array(
		'operation' => 'daily_report',
		'context' => array(
			'trigger' => 'CRON Daily',
			'mode' => 'Email Now',
			'recipient' => 'recipient@example.test',
			'arbitrary' => $g16c_sentinel,
		),
		'finalized' => false,
	),
);
g16c_ticket_integrity_fatal_guard_shutdown();
g16c_same(array('direct', 'state', 'option'), $GLOBALS['g16c_order'], 'Ticket fatal runtime order changed.');
g16c_same(true, $GLOBALS['bvmgr_ticket_integrity_fatal_guards']['guard-secret']['finalized'], 'Ticket fatal guard must finalize after sinks.');
g16c_same('recipient@example.test', $GLOBALS['g16c_state_patches'][0]['last_recipient'], 'Ticket business-state recipient must be preserved.');
g16c_same('crondaily', $GLOBALS['g16c_state_patches'][0]['last_trigger'], 'Ticket business-state trigger changed.');
g16c_same('emailnow', $GLOBALS['g16c_state_patches'][0]['last_mode'], 'Ticket business-state mode changed.');
g16c_same('daily_report_failed', $GLOBALS['g16c_option_logs'][0][0], 'Ticket option event changed.');
g16c_same(0, substr_count(json_encode($GLOBALS['g16c_option_logs']), $g16c_sentinel), 'Ticket option log leaked sentinel data.');
g16c_same(0, substr_count($GLOBALS['g16c_direct_logs'][0], $g16c_sentinel), 'Ticket direct fallback leaked sentinel data.');
g16c_assert(preg_match('/^\[BVM operational\] event=ticket_integrity_fatal_shutdown operation=daily_report memory_exhausted=1 fatal_type=1 line=812 source_scope=includes_ticketing_workerphp correlation=[a-f0-9]{24}$/', $GLOBALS['g16c_direct_logs'][0]) === 1, 'Ticket direct payload allowlist changed.');

$g16c_shutdown_no_direct = g16c_replace_once($g16c_shutdown, 'function bvmgr_ticket_integrity_fatal_guard_shutdown(', 'function g16c_ticket_integrity_shutdown_no_direct(', 'Ticket no-direct rename failed.');
$g16c_shutdown_no_direct = g16c_replace_once($g16c_shutdown_no_direct, 'error_get_last()', '$GLOBALS[\'g16c_fatal_error\']', 'Ticket no-direct fatal injection failed.');
$g16c_shutdown_no_direct = g16c_replace_once($g16c_shutdown_no_direct, "function_exists('error_log')", 'false', 'Ticket disabled logger injection failed.');
eval($g16c_shutdown_no_direct);
$GLOBALS['g16c_order'] = array();
$GLOBALS['bvmgr_ticket_integrity_fatal_guards']['guard-secret']['finalized'] = false;
g16c_ticket_integrity_shutdown_no_direct();
g16c_same(array('state', 'option'), $GLOBALS['g16c_order'], 'Disabled error_log must not interrupt state/option processing.');

$GLOBALS['g16c_adapter_result'] = true;
$GLOBALS['g16c_adapter_calls'] = array();
function bvmgr_record_operational_issue(string $event_code, array $context = array(), $error = null): bool
{
	$GLOBALS['g16c_adapter_calls'][] = array($event_code, $context, $error);
	return (bool) $GLOBALS['g16c_adapter_result'];
}
if (!function_exists('absint')) {
	function absint($value): int { return abs((int) $value); }
}
if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($value): string { return trim((string) $value); }
}
if (!function_exists('sanitize_textarea_field')) {
	function sanitize_textarea_field($value): string { return trim((string) $value); }
}
if (!function_exists('current_time')) {
	function current_time($type, $gmt = false): string { return '2026-08-08 12:00:00'; }
}
if (!function_exists('wp_json_encode')) {
	function wp_json_encode($value): string { return (string) json_encode($value); }
}
function bvmgr_notify_log_table_name(): string { return 'wp_vms_notification_log'; }
function bvmgr_notify_sanitize_template_key(string $value): string { return sanitize_key($value); }
function bvmgr_notify_redact_payload_for_log($value): array { return array(); }

final class G16CNotificationWPDB
{
	public int $insert_result = 0;
	public array $calls = array();
	public function insert($table, $data, $format): int
	{
		$this->calls[] = array($table, $data, $format);
		return $this->insert_result;
	}
}
$g16c_notify_eval = g16c_replace_once($g16c_notify, 'function bvmgr_notify_insert_log(', 'function g16c_notify_insert_log(', 'Notification runtime rename failed.');
$g16c_notify_eval = g16c_replace_once($g16c_notify_eval, 'error_log(', 'g16c_capture_error_log(', 'Notification runtime logger capture failed.');
eval($g16c_notify_eval);
$wpdb = new G16CNotificationWPDB();
$g16c_notify_entry = array(
	'event_key' => 'Event Key ' . str_repeat('x', 100),
	'recipient_address' => 'recipient@example.test',
	'error_message' => $g16c_sentinel,
	'payload' => array('secret' => $g16c_sentinel),
);
$GLOBALS['g16c_adapter_calls'] = array();
$GLOBALS['g16c_direct_logs'] = array();
$GLOBALS['g16c_adapter_result'] = true;
g16c_notify_insert_log($g16c_notify_entry);
g16c_same(1, count($GLOBALS['g16c_adapter_calls']), 'Notification insert failure must call adapter once.');
g16c_same('notification_log_insert_failed', $GLOBALS['g16c_adapter_calls'][0][0], 'Notification adapter event changed.');
g16c_same(80, strlen($GLOBALS['g16c_adapter_calls'][0][1]['event_key']), 'Notification event key must be bounded.');
g16c_same(array(), $GLOBALS['g16c_direct_logs'], 'Successful notification adapter must suppress direct fallback.');
$GLOBALS['g16c_adapter_calls'] = array();
$GLOBALS['g16c_direct_logs'] = array();
$GLOBALS['g16c_adapter_result'] = false;
g16c_notify_insert_log($g16c_notify_entry);
g16c_same(1, count($GLOBALS['g16c_adapter_calls']), 'False notification adapter must still be called once.');
g16c_same(1, count($GLOBALS['g16c_direct_logs']), 'False notification adapter must use one fallback.');
g16c_same(0, substr_count($GLOBALS['g16c_direct_logs'][0], $g16c_sentinel), 'Notification fallback leaked sentinel data.');
g16c_assert(preg_match('/^\[BVM operational\] event=notification_log_insert_failed event_key=[a-z0-9_-]{1,80}$/', $GLOBALS['g16c_direct_logs'][0]) === 1, 'Notification fallback payload changed.');

$g16c_phase_logger = g16c_extract_function($g16c_sources['mirror']['phase'], 'bvmgr_entitlements_sync_image_log');
eval($g16c_phase_logger);
$GLOBALS['g16c_adapter_calls'] = array();
$GLOBALS['g16c_adapter_result'] = false;
bvmgr_entitlements_sync_image_log('legacy detail ' . $g16c_sentinel);
g16c_same('entitlement_image_sync_legacy', $GLOBALS['g16c_adapter_calls'][0][0], 'PhaseB legacy event changed.');
g16c_same(array('service' => 'ticketing', 'operation' => 'sync_image', 'status' => 'legacy'), $GLOBALS['g16c_adapter_calls'][0][1], 'PhaseB legacy context changed.');
g16c_same('legacy detail ' . $g16c_sentinel, $GLOBALS['g16c_adapter_calls'][0][2], 'PhaseB legacy string must travel only as adapter error identity input.');
$phase_error = new RuntimeException($g16c_sentinel, 77);
bvmgr_entitlements_sync_image_log('entitlement_image_sync_product_save_failed', array('service' => 'ticketing', 'operation' => 'sync_image', 'stage' => 'product_save', 'status' => 'warning_wc_save_failed', 'product_id' => 12, 'plan_id' => 34, 'post_id' => 56), $phase_error);
g16c_same($phase_error, $GLOBALS['g16c_adapter_calls'][1][2], 'PhaseB Throwable must travel only as adapter error identity input.');
g16c_same(array('product_id' => 12, 'plan_id' => 34, 'post_id' => 56), array_intersect_key($GLOBALS['g16c_adapter_calls'][1][1], array_flip(array('product_id', 'plan_id', 'post_id'))), 'PhaseB safe IDs changed.');

if (!defined('MINUTE_IN_SECONDS')) {
	define('MINUTE_IN_SECONDS', 60);
}
function current_user_can($capability): bool { return true; }
function wp_die($message): void { throw new RuntimeException((string) $message); }
// Action-selection double; nonce compatibility is covered by wporg-prefix-b4-nonces.
function bvmgr_nonce_action_for_request($action, $field = false) { return $action; }
function check_admin_referer($action): void { $GLOBALS['g16c_nonce_checked'] = $action; }
function get_posts($args): array { return array(); }
function set_transient($key, $value, $expiration): bool
{
	$GLOBALS['g16c_settings_order'][] = 'transient';
	$GLOBALS['g16c_transient'] = array($key, $value, $expiration);
	return true;
}
function admin_url($path = ''): string { return 'https://example.test/wp-admin/' . ltrim((string) $path, '/'); }
function add_query_arg($args, $url): string { return $url . '?' . http_build_query($args); }
function wp_safe_redirect($url): bool
{
	$GLOBALS['g16c_settings_order'][] = 'redirect';
	$GLOBALS['g16c_redirect'] = $url;
	return true;
}
final class G16CSettingsExit extends RuntimeException {}

$g16c_settings_eval = $g16c_settings_handler;
$g16c_settings_eval = g16c_replace_once($g16c_settings_eval, 'function bvmgr_handle_sync_entitlement_images(', 'function g16c_handle_sync_entitlement_images(', 'Settings runtime rename failed.');
$g16c_settings_eval = g16c_replace_once($g16c_settings_eval, "function_exists('bvmgr_entitlements_sync_image_log')", 'false', 'Settings PhaseB-unavailable injection failed.');
$g16c_settings_eval = g16c_replace_once($g16c_settings_eval, 'exit;', 'throw new G16CSettingsExit();', 'Settings exit capture failed.');
eval($g16c_settings_eval);
$GLOBALS['g16c_adapter_calls'] = array();
$GLOBALS['g16c_adapter_result'] = false;
$GLOBALS['g16c_settings_order'] = array();
$GLOBALS['g16c_transient'] = null;
$GLOBALS['g16c_redirect'] = null;
try {
	g16c_handle_sync_entitlement_images();
	throw new RuntimeException('Settings handler must terminate after redirect.');
} catch (G16CSettingsExit $exception) {
	// Expected control-flow sentinel.
}
g16c_same('bvmgr_sync_entitlement_images', $GLOBALS['g16c_nonce_checked'], 'Settings nonce contract changed.');
g16c_same(array('transient', 'redirect'), $GLOBALS['g16c_settings_order'], 'Settings adapter false must preserve transient/redirect behavior.');
g16c_same('vms_entitlement_image_sync_last', $GLOBALS['g16c_transient'][0], 'Settings transient key changed.');
g16c_same(0, $GLOBALS['g16c_transient'][1]['errors'], 'Settings empty backfill summary changed.');
g16c_assert(strpos((string) $GLOBALS['g16c_redirect'], 'vms_entitlement_image_sync_done=1') !== false, 'Settings redirect contract changed.');
g16c_same(1, count($GLOBALS['g16c_adapter_calls']), 'Settings must call foundation adapter exactly once when PhaseB wrapper is unavailable.');
g16c_same('entitlement_image_sync_backfill_completed', $GLOBALS['g16c_adapter_calls'][0][0], 'Settings adapter event changed.');
g16c_same('completed', $GLOBALS['g16c_adapter_calls'][0][1]['status'], 'Settings success status changed.');
g16c_same(0, $GLOBALS['g16c_adapter_calls'][0][1]['count'], 'Settings bounded error count changed.');

echo "G16 operational logging group C regression checks passed.\n";
