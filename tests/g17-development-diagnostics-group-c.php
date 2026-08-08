<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$shadow = dirname($root, 2) . '/vms';
$artifact = '/tmp/wporg-g16-checkpoint-final.aOSh8U/plugin-check.strict.json';

function g17c_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g17c_same($expected, $actual, string $message): void
{
	g17c_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function g17c_read(string $path): string
{
	$value = file_get_contents($path);
	g17c_assert(is_string($value) && $value !== '', 'Unable to read ' . $path);
	return $value;
}

function g17c_function(string $source, string $name): string
{
	$start = strpos($source, 'function ' . $name . '(');
	$brace = $start === false ? false : strpos($source, '{', $start);
	g17c_assert($start !== false && $brace !== false, 'Missing function ' . $name);
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

function g17c_once(string $source, string $current, string $replacement, string $message): string
{
	g17c_same(1, substr_count($source, $current), $message . ' count');
	return str_replace($current, $replacement, $source);
}

function g17c_swap(string $source, string $name, string $replacement): string
{
	return g17c_once($source, g17c_function($source, $name), $replacement, 'Function swap failed: ' . $name);
}

$paths = array(
	'staff' => 'includes/modules/staff-tasks/generator.php',
	'ticket' => 'includes/ticketing/ticket-mutation-audit.php',
);
$sources = array('mirror' => array(), 'shadow' => array());
foreach ($paths as $key => $relative) {
	$sources['mirror'][$key] = g17c_read($root . '/' . $relative);
	$sources['shadow'][$key] = g17c_read($shadow . '/' . $relative);
	g17c_same($sources['mirror'][$key], $sources['shadow'][$key], 'Owned mirror/shadow parity changed: ' . $relative);
}

g17c_same('b0ebbddec1d17ce9a8770ae9ec385665f49962c6ebc1a3f2f1520e81d281b49c', hash_file('sha256', $artifact), 'Artifact SHA changed.');
$findings = json_decode(g17c_read($artifact), true, 512, JSON_THROW_ON_ERROR);
g17c_same(141, count($findings), 'Artifact total changed.');
$expected = array(
	'includes/modules/staff-tasks/generator.php:605:4:WordPress.PHP.DevelopmentFunctions.error_log_error_log',
	'includes/modules/staff-tasks/generator.php:633:3:WordPress.PHP.DevelopmentFunctions.error_log_error_log',
	'includes/modules/staff-tasks/generator.php:677:4:WordPress.PHP.DevelopmentFunctions.error_log_error_log',
	'includes/ticketing/ticket-mutation-audit.php:270:2:WordPress.PHP.DevelopmentFunctions.error_log_error_log',
	'includes/ticketing/ticket-mutation-audit.php:318:9:WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace',
);
$owned = array();
$logging_total = 0;
foreach ($findings as $row) {
	$code = (string) ($row['code'] ?? '');
	$is_logging = strpos($code, 'WordPress.PHP.DevelopmentFunctions.error_log_') === 0;
	$logging_total += $is_logging ? 1 : 0;
	foreach ($paths as $relative) {
		if ($is_logging && substr((string) ($row['file'] ?? ''), -strlen($relative)) === $relative) {
			$owned[] = $relative . ':' . (int) ($row['line'] ?? 0) . ':' . (int) ($row['column'] ?? 0) . ':' . $code;
		}
	}
}
sort($expected);
sort($owned);
g17c_same(16, $logging_total, 'Authoritative logging total changed.');
g17c_same($expected, $owned, 'C5 artifact inventory changed.');
g17c_same(11, $logging_total - count($owned), 'Exactly eleven rows must remain outside C5.');

foreach (array('mirror', 'shadow') as $tree) {
	$combined = implode("\n", $sources[$tree]);
	g17c_same(0, preg_match_all('/(?<![A-Za-z0-9_])error_log\s*\(/', $combined), $tree . ' error-log projection changed.');
	g17c_same(1, preg_match_all('/debug_backtrace\s*\(\s*DEBUG_BACKTRACE_IGNORE_ARGS\s*,\s*40\s*\)/', $combined), $tree . ' bounded backtrace changed.');
	g17c_same(1, substr_count($combined, 'phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Mutation-source detection needs a bounded argument-free stack, and every frame is immediately reduced to a sanitized function identity.'), $tree . ' narrow annotation changed.');
	g17c_same(0, preg_match_all('/phpcs:(?:disable|enable|ignoreFile)[^\n]*DevelopmentFunctions|phpcs:ignore\s+WordPress\.PHP\.DevelopmentFunctions(?:\s|$|,)/i', $combined), $tree . ' broad suppression detected.');
	$ticket_lines = preg_split('/\R/', $sources[$tree]['ticket']);
	$backtrace_line = array_search("\t\$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 40);", $ticket_lines, true);
	g17c_assert(is_int($backtrace_line), $tree . ' exact backtrace line changed.');
	g17c_assert(strpos((string) ($ticket_lines[$backtrace_line - 1] ?? ''), 'phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace --') !== false, $tree . ' annotation must be line-local.');
}

$staff_schema = <<<'PHP'
			vms_record_operational_issue(
				'staff_tasks_schema_not_ready',
				array(
					'service' => 'staff_tasks',
					'operation' => 'nightly_generate',
					'status' => 'skipped',
				)
			);
PHP;
$staff_nightly_failure = <<<'PHP'
				vms_record_operational_issue(
					'staff_tasks_nightly_event_failed',
					array(
						'service' => 'staff_tasks',
						'operation' => 'nightly_generate',
						'status' => 'failed',
						'plan_id' => absint($event_id),
					),
					$run
				);
PHP;
$staff_direct = <<<'PHP'
			vms_record_operational_issue(
				'staff_tasks_event_generation_failed',
				array(
					'service' => 'staff_tasks',
					'operation' => 'generate_for_event',
					'status' => 'failed',
					'plan_id' => absint($post_id),
				),
				$run
			);
PHP;
$ticket_fallback = <<<'PHP'
	vms_record_operational_issue('ticket_mutation_audit_trace', array(
		'hook' => sanitize_key((string) ($context['hook_name'] ?? 'ticket_mutation_audit')),
		'action' => 'ticket_mutation_audit',
		'decision' => sanitize_key($decision),
		'reason' => $payload['reason'],
		'operation' => $payload['operation'],
		'plan_id' => $payload['object_id'],
	));
PHP;
$ticket_capture = g17c_function($sources['mirror']['ticket'], 'vms_ticket_mutation_audit_capture_source_trace');

$reconstruct_staff = static function (string $source) use ($staff_schema, $staff_nightly_failure, $staff_direct): string {
	$nightly = g17c_function($source, 'vms_tasks_run_nightly_generator');
	$nightly = g17c_once($nightly, $staff_schema, "\t\t\terror_log('[VMS Tasks] Nightly generator skipped: DB schema not ready.');", 'Schema reverse failed.');
	$nightly = g17c_once($nightly, $staff_nightly_failure, '', 'Nightly failure reverse failed.');
	$nightly = g17c_once($nightly, "\t\t\tif (is_wp_error(\$run)) {\n\n\t\t\t\t\$summary['warnings']++;", "\t\t\tif (is_wp_error(\$run)) {\n\t\t\t\t\$summary['warnings']++;", 'Nightly reverse whitespace failed.');
	$close = strrpos($nightly, "\n\t}");
	g17c_assert($close !== false, 'Nightly close missing.');
	$nightly = substr($nightly, 0, (int) $close) . "\n\t\terror_log('[VMS Tasks] nightly_generator ' . wp_json_encode(\$summary));" . substr($nightly, (int) $close);
	$source = g17c_swap($source, 'vms_tasks_run_nightly_generator', $nightly);
	$direct = g17c_function($source, 'vms_tasks_generate_for_event_safe');
	$direct = g17c_once($direct, $staff_direct, "\t\t\terror_log('[VMS Tasks] event generation failed: ' . \$run->get_error_message());", 'Direct reverse failed.');
	return g17c_swap($source, 'vms_tasks_generate_for_event_safe', $direct);
};
$reconstruct_ticket = static function (string $source) use ($ticket_fallback): string {
	$trace = g17c_function($source, 'vms_ticket_mutation_audit_trace');
	$historical = <<<'PHP'
	$elapsed_ms = $started_at > 0 ? max(0.0, round((microtime(true) - $started_at) * 1000, 1)) : 0.0;
	error_log('[VMS TRACE] ' . wp_json_encode(array(
		'hook' => sanitize_key((string) ($context['hook_name'] ?? 'ticket_mutation_audit')),
		'action' => 'ticket_mutation_audit',
		'decision' => sanitize_key($decision),
		'reason' => $payload['reason'],
		'request_uri' => function_exists('vms_admin_guard_request_uri') ? vms_admin_guard_request_uri() : vms_request_current_uri(''),
		'screen_id' => function_exists('vms_admin_guard_current_screen_id') ? vms_admin_guard_current_screen_id() : '',
		'elapsed_ms' => $elapsed_ms,
		'memory_mb' => round(((int) memory_get_usage(true)) / 1048576, 1),
		'meta_key' => $payload['meta_key'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This fallback trace payload reports the mutation metadata key; it does not configure a database query.
		'object_id' => $payload['object_id'],
		'operation' => $payload['operation'],
		'source_hook' => $payload['source_hook'],
		'source_function' => $payload['source_function'],
	)));
PHP;
	$trace = g17c_once($trace, $ticket_fallback, $historical, 'Ticket fallback reverse failed.');
	$source = g17c_swap($source, 'vms_ticket_mutation_audit_trace', $trace);
	$capture = g17c_function($source, 'vms_ticket_mutation_audit_capture_source_trace');
	$body_start = strpos($capture, "{\n") + 2;
	$body_end = strrpos($capture, "\n}");
	g17c_assert($body_start >= 2 && $body_end !== false, 'Capture bounds changed.');
	$capture = substr($capture, 0, $body_start) . "\treturn debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 40);" . substr($capture, (int) $body_end);
	return g17c_swap($source, 'vms_ticket_mutation_audit_capture_source_trace', $capture);
};

foreach (array('mirror', 'shadow') as $tree) {
	g17c_same('d1a95fcfef5d0d5bbe6188e92569cc5112aa4d06e5a27b72a73457bdb017253a', hash('sha256', $reconstruct_staff($sources[$tree]['staff'])), 'Pre-G17 Staff projection changed: ' . $tree);
	g17c_same('38f0e7b4584f343e8cdbeabf6c2d21c0b929883ece1cf85247d9ee5e0c83c784', hash('sha256', $reconstruct_ticket($sources[$tree]['ticket'])), 'Pre-G17 ticket projection changed: ' . $tree);
}
foreach (array(
	array('staff', 'staff_tasks_schema_not_ready', 'staff_tasks_schema_mutated', $reconstruct_staff),
	array('ticket', "vms_record_operational_issue('ticket_mutation_audit_trace'", "vms_record_operational_issue('ticket_mutation_audit_mutated'", $reconstruct_ticket),
) as $mutation) {
	$mutated = g17c_once($sources['mirror'][$mutation[0]], $mutation[1], $mutation[2], 'Mutation setup failed.');
	$rejected = false;
	try {
		$mutation[3]($mutated);
	} catch (RuntimeException $exception) {
		$rejected = true;
	}
	g17c_assert($rejected, 'Owned mutation did not invalidate reconstruction: ' . $mutation[0]);
}

if (!function_exists('sanitize_key')) {
	function sanitize_key($value): string
	{
		$clean = preg_replace('/[^a-z0-9_-]+/', '', strtolower(is_scalar($value) ? (string) $value : ''));
		return is_string($clean) ? $clean : '';
	}
}
if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($value): string
	{
		return trim(strip_tags(is_scalar($value) ? (string) $value : ''));
	}
}
if (!function_exists('absint')) {
	function absint($value): int
	{
		return abs((int) $value);
	}
}

$GLOBALS['g17c_order'] = array();
$GLOBALS['g17c_issues'] = array();
$GLOBALS['g17c_guards'] = array();
function vms_record_operational_issue(string $event_code, array $context = array(), $error = null): bool
{
	$GLOBALS['g17c_order'][] = 'issue:' . $event_code;
	$GLOBALS['g17c_issues'][] = array('event_code' => $event_code, 'context' => $context, 'error' => $error);
	return true;
}
function vms_admin_guard_trace(string $hook_name, string $decision, array $payload, float $started_at = 0.0): void
{
	$GLOBALS['g17c_order'][] = 'guard';
	$GLOBALS['g17c_guards'][] = compact('hook_name', 'decision', 'payload', 'started_at');
}

$trace_source = g17c_function($sources['mirror']['ticket'], 'vms_ticket_mutation_audit_trace');
$delegated = g17c_once($trace_source, 'function vms_ticket_mutation_audit_trace(', 'function g17c_ticket_delegated(', 'Delegated trace rename failed.');
eval($delegated);
$fallback = g17c_once($trace_source, 'function vms_ticket_mutation_audit_trace(', 'function g17c_ticket_fallback(', 'Fallback trace rename failed.');
$fallback = g17c_once($fallback, "if (function_exists('vms_admin_guard_trace')) {", 'if (false) {', 'Fallback injection failed.');
eval($fallback);

$sentinel = 'recipient@example.test token=TOPSECRET uri=/private/path meta=_secret source=/tmp/private.php';
$trace_context = array(
	'hook_name' => 'Ticket Mutation Audit',
	'reason' => 'Explicit Context',
	'object_id' => 41,
	'meta_key' => $sentinel,
	'operation' => 'Update',
	'source_hook' => $sentinel,
	'source_function' => $sentinel,
	'request_uri' => $sentinel,
	'raw_data' => $sentinel,
);
$started_at = microtime(true) - 0.5;
$GLOBALS['g17c_order'] = $GLOBALS['g17c_issues'] = $GLOBALS['g17c_guards'] = array();
g17c_ticket_delegated('Allowed', $trace_context, $started_at);
g17c_same(array('guard'), $GLOBALS['g17c_order'], 'Admin-guard delegation must return before fallback.');
g17c_same(0, count($GLOBALS['g17c_issues']), 'Delegation must not duplicate adapter trace.');
g17c_same('ticketmutationaudit', $GLOBALS['g17c_guards'][0]['hook_name'], 'Delegated hook changed.');
g17c_same('Allowed', $GLOBALS['g17c_guards'][0]['decision'], 'Delegated decision changed.');
g17c_same($started_at, $GLOBALS['g17c_guards'][0]['started_at'], 'Delegated timing changed.');
g17c_same($sentinel, $GLOBALS['g17c_guards'][0]['payload']['meta_key'], 'Delegated payload changed.');

$GLOBALS['g17c_order'] = $GLOBALS['g17c_issues'] = $GLOBALS['g17c_guards'] = array();
g17c_ticket_fallback('Allowed', $trace_context, $started_at);
g17c_same(array('issue:ticket_mutation_audit_trace'), $GLOBALS['g17c_order'], 'Unavailable admin guard must use one adapter fallback.');
$fallback_call = $GLOBALS['g17c_issues'][0];
g17c_same('ticket_mutation_audit_trace', $fallback_call['event_code'], 'Ticket fallback event changed.');
g17c_same(array('hook', 'action', 'decision', 'reason', 'operation', 'plan_id'), array_keys($fallback_call['context']), 'Ticket fallback allowlist changed.');
g17c_same(array(
	'hook' => 'ticketmutationaudit',
	'action' => 'ticket_mutation_audit',
	'decision' => 'allowed',
	'reason' => 'explicitcontext',
	'operation' => 'update',
	'plan_id' => 41,
), $fallback_call['context'], 'Ticket fallback context changed.');
g17c_same(null, $fallback_call['error'], 'Ticket fallback must not forward raw error data.');
g17c_same(0, substr_count((string) json_encode($fallback_call), $sentinel), 'Ticket fallback leaked URI/meta/source/raw sentinel data.');

foreach (array('vms_ticket_mutation_audit_skip_hooks', 'vms_ticket_mutation_audit_current_hook', 'vms_ticket_mutation_audit_capture_source_trace', 'vms_ticket_mutation_audit_detect_source') as $function_name) {
	eval(g17c_function($sources['mirror']['ticket'], $function_name));
}
function g17c_capture_probe(): array
{
	return vms_ticket_mutation_audit_capture_source_trace();
}
$frames = g17c_capture_probe();
g17c_assert($frames !== array() && count($frames) <= 40, 'Projected trace must contain at most forty frames.');
foreach ($frames as $frame) {
	g17c_same(array('function'), array_keys($frame), 'Projected frame must contain only function identity.');
	g17c_assert(is_string($frame['function']) && $frame['function'] !== '' && strlen($frame['function']) <= 80, 'Projected identity must be bounded.');
	g17c_assert(preg_match('/^[a-z0-9_-]+$/', $frame['function']) === 1, 'Projected identity must be sanitized.');
	foreach (array('file', 'line', 'args', 'class', 'type', 'object') as $key) {
		g17c_assert(!array_key_exists($key, $frame), 'Projected frame leaked key: ' . $key);
	}
}

$GLOBALS['wp_current_filter'] = array('save_post_vms_event_plan', 'updated_post_meta');
$injected = array(
	array('function' => 'vms_ticket_mutation_audit_detect_source', 'file' => $sentinel, 'args' => array($sentinel)),
	array('function' => 'apply_filters', 'class' => $sentinel),
	array('function' => 'tribe_inventory_sync', 'object' => (object) array('secret' => $sentinel)),
);
$immutable = $injected;
$detected = vms_ticket_mutation_audit_detect_source($injected);
g17c_same($immutable, $injected, 'Explicit source trace must remain immutable.');
g17c_same(array('source_hook' => 'save_post_vms_event_plan', 'source_function' => 'tribe_inventory_sync'), $detected, 'Explicit source detection changed.');
function vms_g17_ticket_source_probe(): array
{
	return vms_ticket_mutation_audit_detect_source();
}
g17c_same('vms_g17_ticket_source_probe', vms_g17_ticket_source_probe()['source_function'], 'Projected live source detection changed.');

if (!class_exists('WP_Error')) {
	final class WP_Error
	{
		public string $code;
		public string $message;
		public function __construct(string $code, string $message)
		{
			$this->code = $code;
			$this->message = $message;
		}
		public function get_error_message(): string
		{
			return $this->message;
		}
	}
}
if (!function_exists('is_wp_error')) {
	function is_wp_error($value): bool
	{
		return $value instanceof WP_Error;
	}
}

$GLOBALS['g17c_db_ready'] = true;
$GLOBALS['g17c_settings'] = array('horizon_days' => 60);
$GLOBALS['g17c_plan_ids'] = array();
$GLOBALS['g17c_generate_results'] = array();
$GLOBALS['g17c_generate_calls'] = array();
$GLOBALS['g17c_event_contexts'] = array();
$GLOBALS['g17c_allow_supersede'] = false;
$GLOBALS['g17c_summary'] = null;
function vms_tasks_db_ready(): bool
{
	$GLOBALS['g17c_order'][] = 'db_ready';
	return (bool) $GLOBALS['g17c_db_ready'];
}
function vms_tasks_get_settings(): array
{
	$GLOBALS['g17c_order'][] = 'settings';
	return $GLOBALS['g17c_settings'];
}
function vms_tasks_collect_upcoming_event_ids(int $horizon_days): array
{
	$GLOBALS['g17c_order'][] = 'collect:' . $horizon_days;
	return $GLOBALS['g17c_plan_ids'];
}
function vms_tasks_generate_for_event(int $post_id, array $args = array())
{
	$GLOBALS['g17c_order'][] = 'generate:' . $post_id;
	$GLOBALS['g17c_generate_calls'][] = array($post_id, $args);
	return $GLOBALS['g17c_generate_results'][$post_id] ?? array(
		'events_checked' => 0,
		'instances_created' => 0,
		'instances_superseded' => 0,
		'assignment_resolutions_applied' => 0,
		'warnings' => array(),
	);
}
function vms_tasks_get_event_context(int $post_id)
{
	$GLOBALS['g17c_order'][] = 'context:' . $post_id;
	return $GLOBALS['g17c_event_contexts'][$post_id] ?? null;
}
function vms_tasks_should_allow_supersede(int $post_id, array $event_context, array $settings): bool
{
	unset($event_context, $settings);
	$GLOBALS['g17c_order'][] = 'supersede:' . $post_id;
	return (bool) $GLOBALS['g17c_allow_supersede'];
}

$nightly_probe = g17c_function($sources['mirror']['staff'], 'vms_tasks_run_nightly_generator');
$nightly_probe = g17c_once($nightly_probe, 'function vms_tasks_run_nightly_generator(', 'function g17c_tasks_run_nightly_generator(', 'Nightly probe rename failed.');
$nightly_close = strrpos($nightly_probe, "\n\t}");
g17c_assert($nightly_close !== false, 'Nightly probe close changed.');
$nightly_probe = substr($nightly_probe, 0, (int) $nightly_close) . "\n\t\t\$GLOBALS['g17c_summary'] = \$summary;" . substr($nightly_probe, (int) $nightly_close);
eval($nightly_probe);
$direct_probe = g17c_function($sources['mirror']['staff'], 'vms_tasks_generate_for_event_safe');
$direct_probe = g17c_once($direct_probe, 'function vms_tasks_generate_for_event_safe(', 'function g17c_tasks_generate_for_event_safe(', 'Direct probe rename failed.');
eval($direct_probe);

$GLOBALS['g17c_db_ready'] = false;
$GLOBALS['g17c_order'] = $GLOBALS['g17c_issues'] = $GLOBALS['g17c_generate_calls'] = array();
$GLOBALS['g17c_summary'] = null;
g17c_same(null, g17c_tasks_run_nightly_generator(), 'Schema-unready nightly return changed.');
g17c_same(array('db_ready', 'issue:staff_tasks_schema_not_ready'), $GLOBALS['g17c_order'], 'Schema-unready nightly order changed.');
g17c_same(array('service' => 'staff_tasks', 'operation' => 'nightly_generate', 'status' => 'skipped'), $GLOBALS['g17c_issues'][0]['context'], 'Schema-unready context changed.');
g17c_same(array(), $GLOBALS['g17c_generate_calls'], 'Schema-unready nightly must not enter event queue.');
g17c_same(null, $GLOBALS['g17c_summary'], 'Schema-unready nightly must return before counters.');

$nightly_error = new WP_Error('nightly_failed', $sentinel);
$GLOBALS['g17c_db_ready'] = true;
$GLOBALS['g17c_settings'] = array('horizon_days' => 45);
$GLOBALS['g17c_plan_ids'] = array(11, 12);
$GLOBALS['g17c_generate_results'] = array(
	11 => $nightly_error,
	12 => array(
		'events_checked' => 3,
		'instances_created' => 4,
		'instances_superseded' => 5,
		'assignment_resolutions_applied' => 6,
		'warnings' => array('one', 'two'),
	),
);
$GLOBALS['g17c_order'] = $GLOBALS['g17c_issues'] = $GLOBALS['g17c_generate_calls'] = array();
$GLOBALS['g17c_summary'] = null;
g17c_same(null, g17c_tasks_run_nightly_generator(), 'Nightly processed return changed.');
g17c_same(array('db_ready', 'settings', 'collect:45', 'generate:11', 'issue:staff_tasks_nightly_event_failed', 'generate:12'), $GLOBALS['g17c_order'], 'Nightly event/failure order changed.');
g17c_same(array(array(11, array('allow_supersede' => false)), array(12, array('allow_supersede' => false))), $GLOBALS['g17c_generate_calls'], 'Nightly generation arguments changed.');
g17c_same(array(
	'events_checked' => 3,
	'instances_created' => 4,
	'instances_superseded' => 5,
	'assignment_resolutions_applied' => 6,
	'warnings' => 3,
), $GLOBALS['g17c_summary'], 'Nightly counters changed.');
g17c_same(array('service' => 'staff_tasks', 'operation' => 'nightly_generate', 'status' => 'failed', 'plan_id' => 11), $GLOBALS['g17c_issues'][0]['context'], 'Nightly failure context changed.');
g17c_same($nightly_error, $GLOBALS['g17c_issues'][0]['error'], 'Nightly WP_Error identity changed.');
g17c_same(1, count($GLOBALS['g17c_issues']), 'Nightly success summary must remain removed.');

$GLOBALS['g17c_db_ready'] = false;
$GLOBALS['g17c_order'] = $GLOBALS['g17c_issues'] = $GLOBALS['g17c_generate_calls'] = array();
g17c_same(null, g17c_tasks_generate_for_event_safe(21, 7), 'Schema-unready direct return changed.');
g17c_same(array('db_ready'), $GLOBALS['g17c_order'], 'Schema-unready direct flow changed.');

$GLOBALS['g17c_db_ready'] = true;
$GLOBALS['g17c_event_contexts'][21] = array('event_start_local' => '2026-08-09 19:00:00');
$GLOBALS['g17c_allow_supersede'] = true;
$direct_error = new WP_Error('direct_failed', $sentinel);
$GLOBALS['g17c_generate_results'][21] = $direct_error;
$GLOBALS['g17c_order'] = $GLOBALS['g17c_issues'] = $GLOBALS['g17c_generate_calls'] = array();
g17c_same(null, g17c_tasks_generate_for_event_safe(21, 7), 'Failed direct return changed.');
g17c_same(array('db_ready', 'context:21', 'settings', 'supersede:21', 'generate:21', 'issue:staff_tasks_event_generation_failed'), $GLOBALS['g17c_order'], 'Direct failure order changed.');
g17c_same(array(array(21, array('allow_supersede' => true, 'actor_user_id' => 7))), $GLOBALS['g17c_generate_calls'], 'Direct generation arguments changed.');
g17c_same(array('service' => 'staff_tasks', 'operation' => 'generate_for_event', 'status' => 'failed', 'plan_id' => 21), $GLOBALS['g17c_issues'][0]['context'], 'Direct failure context changed.');
g17c_same($direct_error, $GLOBALS['g17c_issues'][0]['error'], 'Direct WP_Error identity changed.');
g17c_same(0, substr_count((string) json_encode($GLOBALS['g17c_issues'][0]['context']), $sentinel), 'Direct context leaked raw error text.');

$GLOBALS['g17c_generate_results'][21] = array('events_checked' => 1, 'instances_created' => 0, 'instances_superseded' => 0, 'assignment_resolutions_applied' => 0, 'warnings' => array());
$GLOBALS['g17c_order'] = $GLOBALS['g17c_issues'] = $GLOBALS['g17c_generate_calls'] = array();
g17c_same(null, g17c_tasks_generate_for_event_safe(21, 7), 'Successful direct return changed.');
g17c_same(0, count($GLOBALS['g17c_issues']), 'Successful direct generation must not log a failure.');

g17c_same(g17c_function($sources['mirror']['staff'], 'vms_tasks_run_queued_event_generation'), g17c_function($sources['shadow']['staff'], 'vms_tasks_run_queued_event_generation'), 'Queued generation parity changed.');

fwrite(STDOUT, "G17 development diagnostics group C: PASS\n");
