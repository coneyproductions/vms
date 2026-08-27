<?php

declare(strict_types=1);

$g17a_root = dirname(__DIR__);
$g17a_shadow_root = dirname($g17a_root, 2) . '/vms';
$g17a_artifact_path = '/tmp/wporg-g16-checkpoint-final.aOSh8U/plugin-check.strict.json';

function g17a_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function g17a_same($expected, $actual, string $message): void
{
	g17a_assert($expected === $actual, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function g17a_contains(string $needle, string $haystack, string $message): void
{
	g17a_assert(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function g17a_not_contains(string $needle, string $haystack, string $message): void
{
	g17a_assert(strpos($haystack, $needle) === false, $message . "\nUnexpected: " . $needle);
}

function g17a_read(string $path): string
{
	$source = file_get_contents($path);
	g17a_assert(is_string($source) && $source !== '', 'Required source must be readable: ' . $path);
	return $source;
}

function g17a_extract_function(string $source, string $name): string
{
	$start = strpos($source, 'function ' . $name . '(');
	$brace = $start === false ? false : strpos($source, '{', $start);
	g17a_assert($start !== false && $brace !== false, 'Unable to find function: ' . $name);
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

function g17a_extract_guarded_function(string $source, string $name): string
{
	$start = strpos($source, "if (!function_exists('" . $name . "')) {");
	g17a_assert($start !== false, 'Unable to find guarded function: ' . $name);
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

function g17a_rename_function(string $source, string $from, string $to): string
{
	$renamed = preg_replace('/function\s+' . preg_quote($from, '/') . '\s*\(/', 'function ' . $to . '(', $source, 1, $count);
	g17a_assert(is_string($renamed) && $count === 1, 'Unable to rename function: ' . $from);
	return $renamed;
}

function g17a_replace_once(string $source, string $current, string $historical, string $label): string
{
	g17a_same(1, substr_count($source, $current), 'Projection anchor must occur once: ' . $label);
	return str_replace($current, $historical, $source, $count);
}

function g17a_restore_event_plans(string $source): string
{
	$cleanup_current = <<<'PHP'
						delete_option($progress_option);
						break;
PHP;
	$cleanup_historical = <<<'PHP'
						delete_option($progress_option);
						error_log(sprintf(
							'[VMS] Legacy ticket meta cleanup complete: version=%s scanned=%d cleaned=%d deleted_keys=%d template_applied=%d skipped_no_v2=%d',
							$target_version,
							(int) $summary['scanned'],
							(int) $summary['cleaned_plans'],
							(int) $summary['deleted_keys'],
							(int) $summary['template_applied'],
							(int) $summary['skipped_no_v2_config']
						));
						break;
PHP;
	$provider_current = <<<'PHP'
                    if (function_exists('vms_record_operational_issue')) {
                        vms_record_operational_issue('event_plan_tec_provider_unavailable', array(
                            'service' => 'the_events_calendar',
                            'operation' => 'resync_event',
                            'status' => 'unavailable',
                            'plan_id' => $post_id,
                            'event_id' => $existing_tec_id,
                        ));
                    }
PHP;
	$provider_historical = <<<'PHP'
                    error_log('VMS TEC: tribe_update_event() not available. Is The Events Calendar active?');
PHP;
	$resync_current = <<<'PHP'
                    if (function_exists('vms_record_operational_issue')) {
                        vms_record_operational_issue('event_plan_tec_resync_failed', array(
                            'service' => 'the_events_calendar',
                            'operation' => 'resync_event',
                            'status' => 'failed',
                            'plan_id' => $post_id,
                            'event_id' => $existing_tec_id,
                        ), is_wp_error($updated_id) ? $updated_id : 'tribe_update_event_failed');
                    }
PHP;
	$resync_historical = <<<'PHP'
                    $msg = is_wp_error($updated_id) ? $updated_id->get_error_message() : 'Unknown error';
                    error_log('VMS TEC: Failed to re-sync plan ' . $post_id . ' to TEC event ' . $existing_tec_id . ': ' . $msg);
PHP;
	$extras_current = <<<'PHP'
                if (function_exists('vms_record_operational_issue')) {
                    vms_record_operational_issue('event_plan_tec_extras_sync_failed', array(
                        'service' => 'the_events_calendar',
                        'operation' => 'sync_event_extras',
                        'status' => 'failed',
                        'plan_id' => $plan_id,
                        'event_id' => $tec_event_id,
                    ), is_wp_error($updated) ? $updated : 'tribe_update_event_failed');
                }
PHP;
	$extras_historical = <<<'PHP'
                $msg = is_wp_error($updated) ? $updated->get_error_message() : 'Unknown error';
                error_log('VMS TEC: Failed to sync TEC event extras for plan ' . $plan_id . ' (TEC event ' . $tec_event_id . '): ' . $msg);
PHP;

	$source = g17a_replace_once($source, $cleanup_current, $cleanup_historical, 'cleanup completion diagnostic');
	$source = g17a_replace_once($source, $provider_current, $provider_historical, 'TEC provider diagnostic');
	$source = g17a_replace_once($source, $resync_current, $resync_historical, 'TEC resync diagnostic');
	return g17a_replace_once($source, $extras_current, $extras_historical, 'TEC extras diagnostic');
}

function g17a_restore_profiler(string $source): string
{
	$current = <<<'PHP'
    vms_event_plan_save_profiler_store_profile($post_id, $profile);
}
PHP;
	$historical = <<<'PHP'
    vms_event_plan_save_profiler_store_profile($post_id, $profile);

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[VMS Event Plan Save Profile] ' . wp_json_encode($profile));
    }
}
PHP;
	return g17a_replace_once($source, $current, $historical, 'save-profiler duplicate debug dump');
}

function g17a_restore_performance(string $source): string
{
	$current = g17a_extract_function($source, 'vms_event_plan_perf_log');
	$historical = <<<'PHP'
function vms_event_plan_perf_log(string $hook_name, int $plan_id = 0, array $context = array()): void
	{
		if (!vms_event_plan_perf_trace_enabled()) {
			return;
		}

		$plan_id = absint($plan_id);
		$ticket_snapshot = vms_event_plan_ticketing_snapshot($plan_id);
		$entry = array(
			'logged_at_gmt' => gmdate('Y-m-d H:i:s'),
			'request_id' => vms_event_plan_perf_request_id(),
			'hook_name' => sanitize_text_field($hook_name),
			'event_plan_id' => $plan_id,
			'pid' => vms_event_plan_perf_pid(),
			'ticket_count' => absint($ticket_snapshot['effective_ticket_count'] ?? 0),
			'ticket_mode' => sanitize_key((string) ($ticket_snapshot['mode'] ?? '')),
		) + vms_event_plan_perf_request_context($plan_id);

		foreach ($context as $key => $value) {
			$key = sanitize_key((string) $key);
			if ($key === '') {
				continue;
			}

			if (is_scalar($value) || $value === null) {
				if (is_string($value)) {
					$entry[$key] = sanitize_text_field($value);
				} else {
					$entry[$key] = $value;
				}
				continue;
			}

			$encoded = wp_json_encode($value);
			$entry[$key] = is_string($encoded) ? $encoded : '';
		}

		$line = wp_json_encode($entry);
		if (!is_string($line) || $line === '') {
			return;
		}

		error_log($line . PHP_EOL, 3, vms_event_plan_perf_log_path());
	}
PHP;
	return g17a_replace_once($source, $current, $historical, 'Event Plan performance logger');
}

g17a_assert(is_file($g17a_artifact_path), 'Authoritative G16 strict JSON must be present.');
g17a_same('b0ebbddec1d17ce9a8770ae9ec385665f49962c6ebc1a3f2f1520e81d281b49c', hash_file('sha256', $g17a_artifact_path), 'Authoritative G16 strict JSON hash changed.');
$g17a_findings = json_decode(g17a_read($g17a_artifact_path), true, 512, JSON_THROW_ON_ERROR);
g17a_assert(is_array($g17a_findings), 'Authoritative G16 strict JSON must decode to an array.');
g17a_same(141, count($g17a_findings), 'G16 checkpoint finding total changed.');
g17a_same(125, count(array_filter($g17a_findings, static fn(array $row): bool => ($row['type'] ?? '') === 'ERROR')), 'G16 checkpoint ERROR count changed.');
g17a_same(16, count(array_filter($g17a_findings, static fn(array $row): bool => ($row['type'] ?? '') === 'WARNING')), 'G16 checkpoint WARNING count changed.');

$g17a_error_log_code = 'WordPress.PHP.DevelopmentFunctions.error_log_error_log';
$g17a_backtrace_code = 'WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace';
$g17a_logging_rows = array_values(array_filter(
	$g17a_findings,
	static fn(array $row): bool => in_array((string) ($row['code'] ?? ''), array($g17a_error_log_code, $g17a_backtrace_code), true)
));
$g17a_owned_files = array(
	'/privateincludes/cpt/event-plans.php',
	'/privateincludes/core/event-plan-save-profiler.php',
	'/privateincludes/core/event-plan-performance.php',
);
$g17a_owned_rows = array_values(array_filter(
	$g17a_logging_rows,
	static fn(array $row): bool => in_array((string) ($row['file'] ?? ''), $g17a_owned_files, true)
));
$g17a_inventory = array_map(
	static fn(array $row): string => sprintf('%s:%d:%d:%s', (string) $row['file'], (int) $row['line'], (int) $row['column'], (string) $row['code']),
	$g17a_owned_rows
);
g17a_same(array(
	'/privateincludes/cpt/event-plans.php:14183:7:' . $g17a_error_log_code,
	'/privateincludes/cpt/event-plans.php:14911:21:' . $g17a_error_log_code,
	'/privateincludes/cpt/event-plans.php:14933:21:' . $g17a_error_log_code,
	'/privateincludes/cpt/event-plans.php:15379:17:' . $g17a_error_log_code,
	'/privateincludes/core/event-plan-save-profiler.php:1321:9:' . $g17a_error_log_code,
	'/privateincludes/core/event-plan-performance.php:598:3:' . $g17a_error_log_code,
), $g17a_inventory, 'Partition A must own the exact six G17 rows.');
g17a_same(16, count($g17a_logging_rows), 'G17 checkpoint logging count changed.');
g17a_same(6, count($g17a_owned_rows), 'Partition A owned count changed.');
g17a_same(0, count($g17a_owned_rows) - 6, 'Projected Partition A logging count must be zero.');
g17a_same(10, count($g17a_logging_rows) - count($g17a_owned_rows), 'G17 findings outside Partition A must remain ten.');

$g17a_nonblocking_codes = array(
	'WordPress.Security.EscapeOutput.OutputNotEscaped',
	'PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent',
	'WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet',
);
g17a_same(125, count(array_filter(
	$g17a_findings,
	static fn(array $row): bool => in_array((string) ($row['code'] ?? ''), $g17a_nonblocking_codes, true)
)), 'Accepted nonblocking checkpoint set changed.');

$g17a_g16_files = array(
	'/privateincludes/vendor-applications.php',
	'/privateincludes/modules/admissions/rest.php',
	'/privateincludes/admin/data-tools/actions-event-plan-import.php',
	'/privateincludes/taxonomies/vendor-type.php',
	'/privateincludes/runtime-guards.php',
	'/privateincludes/integrations/ticketing-phase-b.php',
	'/privateincludes/core/notifications.php',
	'/privateincludes/core/vendor-application-confirmation.php',
	'/privateincludes/ticketing/ticket-integrity-monitor.php',
	'/privateincludes/admin/settings-page.php',
	'/privateincludes/core/goals-forecast.php',
);
g17a_same(0, count(array_filter(
	$g17a_logging_rows,
	static fn(array $row): bool => in_array((string) ($row['file'] ?? ''), $g17a_g16_files, true)
)), 'G16 scanner-zero inventory must remain unchanged.');

$g17a_relatives = array(
	'includes/cpt/event-plans.php',
	'includes/core/event-plan-save-profiler.php',
	'includes/core/event-plan-performance.php',
);
$g17a_sources = array('mirror' => array(), 'shadow' => array());
$g17a_expected_projection_hashes = array(
	'mirror' => array(
		'includes/cpt/event-plans.php' => '3a14f0780fc4ad1a91dc0b17a18c8c81262ea31dc0fa7a65c6a31460e9a3160a',
		'includes/core/event-plan-save-profiler.php' => '5d852d1e8c0e6b54474dc80e3beb20e4b0a4f1528eea5abecc1077e3cae2df80',
		'includes/core/event-plan-performance.php' => '5e5189ab4b333c6d5eeea7204cc10a21bc65fe2b34b97d628a8cdcdd0194743d',
	),
	'shadow' => array(
		'includes/cpt/event-plans.php' => '5e3e85ebad4258c2123f869d3ff01538f4c832cd8b9c07c5d0908b26ae4aa182',
		'includes/core/event-plan-save-profiler.php' => '01bbeb39317025962053a9edc186950f3a7d50513d6283e78e40d83580344899',
		'includes/core/event-plan-performance.php' => '010bfe16755b0e5ba8c9be3221a582cdbffb4d330ef34f578662e612f3f67bb9',
	),
);
foreach (array('mirror' => $g17a_root, 'shadow' => $g17a_shadow_root) as $tree => $tree_root) {
	foreach ($g17a_relatives as $relative) {
		$source = g17a_read($tree_root . '/' . $relative);
		$g17a_sources[$tree][$relative] = $source;
		g17a_same(0, preg_match_all('/phpcs:(?:ignore|disable)[^\n]*(?:DevelopmentFunctions|error_log|debug_backtrace)/i', $source), $tree . ' must not suppress development diagnostics: ' . $relative);
		g17a_not_contains('error_log(', $source, $tree . ' owned source must contain no direct logger: ' . $relative);
		g17a_not_contains('debug_backtrace(', $source, $tree . ' owned source must contain no stack collection: ' . $relative);

		if ($relative === 'includes/cpt/event-plans.php') {
			$projection = g17a_restore_event_plans($source);
		} elseif ($relative === 'includes/core/event-plan-save-profiler.php') {
			$projection = g17a_restore_profiler($source);
		} else {
			$projection = g17a_restore_performance($source);
		}
		g17a_same($g17a_expected_projection_hashes[$tree][$relative], hash('sha256', $projection), $tree . ' full-file pre-G17 projection changed: ' . $relative);
	}
}

$g17a_mutation = g17a_restore_event_plans($g17a_sources['mirror']['includes/cpt/event-plans.php']);
$g17a_mutation = str_replace('tribe_update_event() not available.', 'tribe_update_event() unavailable.', $g17a_mutation, $g17a_mutation_count);
g17a_same(1, $g17a_mutation_count, 'Owned projection mutation anchor changed.');
g17a_assert(hash('sha256', $g17a_mutation) !== $g17a_expected_projection_hashes['mirror']['includes/cpt/event-plans.php'], 'Immutable pre-G17 projection must reject an owned mutation.');

$g17a_mirror_event = $g17a_sources['mirror']['includes/cpt/event-plans.php'];
$g17a_shadow_event = $g17a_sources['shadow']['includes/cpt/event-plans.php'];
$g17a_mirror_cleanup = g17a_extract_function($g17a_mirror_event, 'vms_event_plan_cleanup_legacy_ticket_meta_once');
$g17a_shadow_cleanup = g17a_extract_function($g17a_shadow_event, 'vms_event_plan_cleanup_legacy_ticket_meta_once');
g17a_same(1, substr_count($g17a_mirror_cleanup, '!empty(vms_event_plan_current_post_request())'), 'Mirror cleanup must preserve its normalized request guard.');
g17a_same(1, substr_count($g17a_shadow_cleanup, '!empty($_POST)'), 'Shadow cleanup must preserve its direct request guard.');
g17a_same($g17a_shadow_cleanup, str_replace('!empty(vms_event_plan_current_post_request())', '!empty($_POST)', $g17a_mirror_cleanup), 'Cleanup parity may differ only at the established request guard.');
foreach (array('vms_resync_event_to_calendar', 'vms_tec_sync_event_extras_from_plan') as $function) {
	g17a_same(g17a_extract_function($g17a_mirror_event, $function), g17a_extract_function($g17a_shadow_event, $function), 'Event Plans owned boundary parity changed: ' . $function);
}
foreach (array('vms_event_plan_save_profiler_store_profile', 'vms_event_plan_save_profiler_finish') as $function) {
	g17a_same(
		g17a_extract_function($g17a_sources['mirror']['includes/core/event-plan-save-profiler.php'], $function),
		g17a_extract_function($g17a_sources['shadow']['includes/core/event-plan-save-profiler.php'], $function),
		'Save-profiler owned boundary parity changed: ' . $function
	);
}
foreach (array('vms_event_plan_perf_log_path', 'vms_event_plan_perf_log') as $function) {
	g17a_same(
		g17a_extract_function($g17a_sources['mirror']['includes/core/event-plan-performance.php'], $function),
		g17a_extract_function($g17a_sources['shadow']['includes/core/event-plan-performance.php'], $function),
		'Performance owned boundary parity changed: ' . $function
	);
}
g17a_assert($g17a_sources['mirror']['includes/cpt/event-plans.php'] !== $g17a_sources['shadow']['includes/cpt/event-plans.php'], 'Event Plans whole-file structural divergence must remain preserved.');

$g17a_perf_source = $g17a_sources['mirror']['includes/core/event-plan-performance.php'];
$g17a_perf_log = g17a_extract_function($g17a_perf_source, 'vms_event_plan_perf_log');
$g17a_perf_path = g17a_extract_function($g17a_perf_source, 'vms_event_plan_perf_log_path');
g17a_contains("return defined('VMS_EP_PERF_TRACE') && VMS_EP_PERF_TRACE;", $g17a_perf_source, 'Performance trace gate changed.');
g17a_contains("apply_filters('vms_event_plan_perf_log_path', \$path)", $g17a_perf_path, 'Legacy performance path filter must remain callable.');
g17a_not_contains('vms_event_plan_perf_log_path()', $g17a_perf_log, 'Action-only logger must leave the legacy path helper inert.');
g17a_not_contains('bvmgr_record_operational_issue', $g17a_perf_log, 'Performance tracing must not persist through the operational adapter.');
g17a_not_contains('vms_event_plan_perf_request_context', $g17a_perf_log, 'Performance action must not capture ambient request context.');
foreach (array('request_uri', 'current_user_id', "'pid'", 'query_count', 'sql', 'meta_key', 'wp_json_encode', 'PHP_EOL') as $forbidden) {
	g17a_not_contains($forbidden, $g17a_perf_log, 'Performance action retained forbidden field or sink.');
}
g17a_contains("do_action('vms_event_plan_perf_trace', \$entry);", $g17a_perf_log, 'Performance trace action contract changed.');
g17a_contains("\$integer_keys = array('count');", $g17a_perf_log, 'Performance count allowlist changed.');
g17a_contains("\$timing_keys = array('elapsed_ms', 'runtime_ms', 'duration_ms', 'total_elapsed_ms');", $g17a_perf_log, 'Performance timing allowlist changed.');

$g17a_resync = g17a_extract_function($g17a_mirror_event, 'vms_resync_event_to_calendar');
$g17a_extras = g17a_extract_function($g17a_mirror_event, 'vms_tec_sync_event_extras_from_plan');
foreach (array('event_plan_tec_provider_unavailable', 'event_plan_tec_resync_failed') as $event_code) {
	g17a_same(1, substr_count($g17a_resync, "bvmgr_record_operational_issue('" . $event_code . "'"), 'TEC event code must occur exactly once: ' . $event_code);
}
g17a_same(1, substr_count($g17a_extras, "bvmgr_record_operational_issue('event_plan_tec_extras_sync_failed'"), 'TEC extras event code must occur exactly once.');
foreach (array($g17a_resync, $g17a_extras) as $source) {
	g17a_not_contains('get_error_message()', $source, 'TEC operational adapter must receive error identity rather than a raw message.');
	g17a_not_contains('error_log(', $source, 'TEC boundary must contain no direct logger.');
}

if (!class_exists('WP_Post')) {
	class WP_Post
	{
	}
}
if (!class_exists('WP_Error')) {
	class WP_Error
	{
		private string $code;
		private string $message;
		public function __construct(string $code = '', string $message = '')
		{
			$this->code = $code;
			$this->message = $message;
		}
		public function get_error_code(): string
		{
			return $this->code;
		}
		public function get_error_message(): string
		{
			return $this->message;
		}
		public function get_error_messages(): array
		{
			return array($this->message);
		}
	}
}

function absint($value): int
{
	return abs((int) $value);
}

function sanitize_key($value): string
{
	return (string) preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value));
}

function is_wp_error($value): bool
{
	return $value instanceof WP_Error;
}

function apply_filters(string $hook, $value, ...$args)
{
	unset($args);
	$GLOBALS['g17a_filters'][] = array($hook, $value);
	if ($hook === 'vms_event_plan_perf_log_path' && isset($GLOBALS['g17a_filtered_perf_path'])) {
		return $GLOBALS['g17a_filtered_perf_path'];
	}
	return $value;
}

function do_action(string $hook, ...$args): void
{
	$GLOBALS['g17a_actions'][] = array($hook, $args);
}

function vms_event_plan_perf_trace_enabled(): bool
{
	return (bool) ($GLOBALS['g17a_perf_enabled'] ?? false);
}

function vms_event_plan_perf_request_id(): string
{
	return 'abcdef123456';
}

function vms_event_plan_ticketing_snapshot(int $plan_id): array
{
	$GLOBALS['g17a_ticket_snapshot_calls'][] = $plan_id;
	return array('effective_ticket_count' => 17, 'mode' => 'General Admission');
}

function is_admin(): bool
{
	return true;
}

function current_user_can(string $capability): bool
{
	return $capability === 'manage_options';
}

function g17a_tribe_update_event_available(): bool
{
	return (bool) ($GLOBALS['g17a_tribe_available'] ?? false);
}

function vms_event_plan_current_post_request(): array
{
	return array();
}

function get_option(string $key, $default = false)
{
	return array_key_exists($key, $GLOBALS['g17a_options']) ? $GLOBALS['g17a_options'][$key] : $default;
}

function update_option(string $key, $value, $autoload = null): bool
{
	unset($autoload);
	$GLOBALS['g17a_options'][$key] = $value;
	$GLOBALS['g17a_option_updates'][] = array($key, $value);
	return true;
}

function delete_option(string $key): bool
{
	unset($GLOBALS['g17a_options'][$key]);
	$GLOBALS['g17a_option_deletes'][] = $key;
	return true;
}

function vms_event_plan_legacy_ticket_meta_candidate_ids(int $cursor, int $batch_size): array
{
	$GLOBALS['g17a_cleanup_queries'][] = array($cursor, $batch_size);
	return array();
}

function vms_event_plan_save_profiler_recording_enabled(): bool
{
	return (bool) ($GLOBALS['g17a_profiler_recording_enabled'] ?? true);
}

function update_post_meta(int $post_id, string $key, $value): bool
{
	$GLOBALS['g17a_post_meta'][$post_id][$key] = $value;
	$GLOBALS['g17a_post_meta_updates'][] = array($post_id, $key, $value);
	return true;
}

function get_post_meta(int $post_id, string $key, bool $single = false)
{
	unset($single);
	return $GLOBALS['g17a_post_meta'][$post_id][$key] ?? '';
}

function delete_post_meta(int $post_id, string $key): bool
{
	unset($GLOBALS['g17a_post_meta'][$post_id][$key]);
	$GLOBALS['g17a_post_meta_deletes'][] = array($post_id, $key);
	return true;
}

$g17a_runtime_guards = g17a_read($g17a_root . '/includes/runtime-guards.php');
foreach (array('bvmgr_operational_issue_value_is_tainted', 'bvmgr_operational_issue_error_identity', 'bvmgr_operational_issue_context') as $helper) {
	eval(g17a_extract_guarded_function($g17a_runtime_guards, $helper));
}

function bvmgr_record_operational_issue(string $event_code, array $context = array(), $error = null): bool
{
	$record = array(
		'event_code' => $event_code,
		'context' => bvmgr_operational_issue_context($context),
	);
	$error_identity = bvmgr_operational_issue_error_identity($error);
	if ($error_identity !== array()) {
		$record['error'] = $error_identity;
	}
	$GLOBALS['g17a_operational_records'][] = $record;
	return true;
}

if (!defined('BVMGR_VERSION')) {
	define('BVMGR_VERSION', '1.2.0');
}

$g17a_cleanup_mirror_eval = g17a_rename_function($g17a_mirror_cleanup, 'vms_event_plan_cleanup_legacy_ticket_meta_once', 'g17a_cleanup_mirror');
$g17a_cleanup_shadow_eval = g17a_rename_function($g17a_shadow_cleanup, 'vms_event_plan_cleanup_legacy_ticket_meta_once', 'g17a_cleanup_shadow');
eval($g17a_cleanup_mirror_eval);
eval($g17a_cleanup_shadow_eval);

$g17a_run_cleanup = static function (string $function): array {
	$_POST = array();
	$GLOBALS['g17a_options'] = array(
		'vms_event_plan_legacy_ticket_cleanup_cursor' => 42,
		'vms_event_plan_legacy_ticket_cleanup_progress' => array(
			'version' => BVMGR_VERSION,
			'run_at_gmt' => '2026-08-08T00:00:00+00:00',
			'scanned' => 7,
			'cleaned_plans' => 3,
			'deleted_keys' => 9,
			'template_applied' => 2,
			'skipped_no_v2_config' => 1,
			'is_incremental' => true,
		),
	);
	$GLOBALS['g17a_option_updates'] = array();
	$GLOBALS['g17a_option_deletes'] = array();
	$GLOBALS['g17a_cleanup_queries'] = array();
	$GLOBALS['g17a_operational_records'] = array();
	$function();
	return array(
		'options' => $GLOBALS['g17a_options'],
		'updates' => $GLOBALS['g17a_option_updates'],
		'deletes' => $GLOBALS['g17a_option_deletes'],
		'queries' => $GLOBALS['g17a_cleanup_queries'],
		'operational' => $GLOBALS['g17a_operational_records'],
	);
};

$g17a_cleanup_mirror_result = $g17a_run_cleanup('g17a_cleanup_mirror');
$g17a_cleanup_shadow_result = $g17a_run_cleanup('g17a_cleanup_shadow');
g17a_same($g17a_cleanup_mirror_result, $g17a_cleanup_shadow_result, 'Cleanup mirror/shadow durable behavior changed.');
g17a_same(BVMGR_VERSION, $g17a_cleanup_mirror_result['options']['vms_event_plan_legacy_ticket_cleanup_version'] ?? '', 'Cleanup completion marker changed.');
$g17a_cleanup_last_run = $g17a_cleanup_mirror_result['options']['vms_event_plan_legacy_ticket_cleanup_last_run'] ?? array();
g17a_same(7, $g17a_cleanup_last_run['scanned'] ?? null, 'Cleanup scanned persistence changed.');
g17a_same(3, $g17a_cleanup_last_run['cleaned_plans'] ?? null, 'Cleanup cleaned-plan persistence changed.');
g17a_same(9, $g17a_cleanup_last_run['deleted_keys'] ?? null, 'Cleanup deleted-key persistence changed.');
g17a_assert(isset($g17a_cleanup_last_run['completed_at_gmt']), 'Cleanup completion timestamp must remain persisted.');
g17a_assert(!isset($g17a_cleanup_mirror_result['options']['vms_event_plan_legacy_ticket_cleanup_cursor']), 'Cleanup cursor must still be deleted.');
g17a_assert(!isset($g17a_cleanup_mirror_result['options']['vms_event_plan_legacy_ticket_cleanup_progress']), 'Cleanup progress must still be deleted.');
g17a_assert(!isset($g17a_cleanup_mirror_result['options']['vms_event_plan_legacy_ticket_cleanup_lock_until']), 'Cleanup lock must still be deleted.');
g17a_same(array(), $g17a_cleanup_mirror_result['operational'], 'Cleanup success must not create a duplicate operational record.');

$g17a_profiler_store = g17a_extract_function($g17a_sources['mirror']['includes/core/event-plan-save-profiler.php'], 'vms_event_plan_save_profiler_store_profile');
eval(g17a_rename_function($g17a_profiler_store, 'vms_event_plan_save_profiler_store_profile', 'g17a_profiler_store'));
$GLOBALS['g17a_profiler_recording_enabled'] = true;
$GLOBALS['g17a_post_meta'] = array();
$GLOBALS['g17a_post_meta_updates'] = array();
for ($g17a_profile_index = 1; $g17a_profile_index <= 6; $g17a_profile_index++) {
	g17a_profiler_store(501, array('profile_id' => $g17a_profile_index, 'elapsed_ms' => $g17a_profile_index * 10));
}
g17a_same(array('profile_id' => 6, 'elapsed_ms' => 60), $GLOBALS['g17a_post_meta'][501]['_vms_last_save_profile'] ?? null, 'Profiler last-profile persistence changed.');
g17a_same(array(6, 5, 4, 3, 2), array_map(
	static fn(array $profile): int => (int) ($profile['profile_id'] ?? 0),
	$GLOBALS['g17a_post_meta'][501]['_vms_event_plan_save_profile_history'] ?? array()
), 'Profiler five-entry history persistence changed.');
$g17a_profiler_finish = g17a_extract_function($g17a_sources['mirror']['includes/core/event-plan-save-profiler.php'], 'vms_event_plan_save_profiler_finish');
g17a_same(1, substr_count($g17a_profiler_finish, 'vms_event_plan_save_profiler_store_profile($post_id, $profile);'), 'Profiler finish must retain one durable store call.');
g17a_not_contains('WP_DEBUG', $g17a_profiler_finish, 'Profiler finish must not retain the duplicate debug branch.');

$g17a_path_eval = g17a_rename_function($g17a_perf_path, 'vms_event_plan_perf_log_path', 'g17a_perf_log_path');
eval($g17a_path_eval);
$GLOBALS['g17a_filters'] = array();
$GLOBALS['g17a_filtered_perf_path'] = sys_get_temp_dir() . '/bvm-g17-inert-path-' . getmypid() . '.log';
g17a_same($GLOBALS['g17a_filtered_perf_path'], g17a_perf_log_path(), 'Legacy performance path filter must remain callable.');
g17a_same('vms_event_plan_perf_log_path', $GLOBALS['g17a_filters'][0][0] ?? '', 'Legacy performance path filter name changed.');
g17a_assert(!is_file($GLOBALS['g17a_filtered_perf_path']), 'Fresh inert performance path sentinel unexpectedly exists before runtime proof.');

eval(g17a_rename_function($g17a_perf_log, 'vms_event_plan_perf_log', 'g17a_perf_log'));
$GLOBALS['g17a_perf_enabled'] = false;
$GLOBALS['g17a_actions'] = array();
$GLOBALS['g17a_ticket_snapshot_calls'] = array();
$g17a_operational_before_perf = count($GLOBALS['g17a_operational_records']);
g17a_perf_log('Disabled Hook', 77, array('elapsed_ms' => 12.5));
g17a_same(array(), $GLOBALS['g17a_actions'], 'Disabled performance tracing must emit zero actions.');
g17a_same(array(), $GLOBALS['g17a_ticket_snapshot_calls'], 'Disabled performance tracing must do no snapshot work.');
g17a_assert(!is_file($GLOBALS['g17a_filtered_perf_path']), 'Disabled performance tracing must write no file.');

$g17a_sentinel = 'recipient@example.test /private/tmp/raw.php?token=secret SQL SELECT * FROM wp_postmeta';
$GLOBALS['g17a_perf_enabled'] = true;
g17a_perf_log('Bad Hook ! Name', 77, array(
	'count' => '7.9',
	'elapsed_ms' => '12.34567',
	'runtime_ms' => INF,
	'duration_ms' => -1,
	'total_elapsed_ms' => 1000000001,
	'request_uri' => '/wp-admin/post.php?token=secret',
	'current_user_id' => 999,
	'actor_id' => 998,
	'pid' => 1234,
	'query_count' => 99,
	'sql' => 'SELECT * FROM wp_users',
	'meta_key' => '_secret_token',
	'arbitrary' => $g17a_sentinel,
	'nested' => array('secret' => $g17a_sentinel),
));
g17a_same(1, count($GLOBALS['g17a_actions']), 'Enabled performance tracing must emit exactly one action.');
g17a_same('vms_event_plan_perf_trace', $GLOBALS['g17a_actions'][0][0] ?? '', 'Performance action hook changed.');
$g17a_entry = $GLOBALS['g17a_actions'][0][1][0] ?? array();
g17a_same(array('logged_at_gmt', 'request_id', 'hook_name', 'event_plan_id', 'ticket_count', 'ticket_mode', 'count', 'elapsed_ms', 'total_elapsed_ms'), array_keys($g17a_entry), 'Performance action closed allowlist changed.');
g17a_assert(preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) ($g17a_entry['logged_at_gmt'] ?? '')) === 1, 'Performance timestamp format changed.');
g17a_assert(preg_match('/^[a-f0-9]{12}$/', (string) ($g17a_entry['request_id'] ?? '')) === 1, 'Performance request correlation must remain 12 lowercase hex.');
g17a_same('badhookname', $g17a_entry['hook_name'] ?? '', 'Performance hook sanitization changed.');
g17a_same(77, $g17a_entry['event_plan_id'] ?? null, 'Performance plan ID changed.');
g17a_same(17, $g17a_entry['ticket_count'] ?? null, 'Performance ticket count changed.');
g17a_same('generaladmission', $g17a_entry['ticket_mode'] ?? '', 'Performance ticket mode sanitization changed.');
g17a_same(7, $g17a_entry['count'] ?? null, 'Performance bounded count normalization changed.');
g17a_same(12.346, $g17a_entry['elapsed_ms'] ?? null, 'Performance timing normalization changed.');
g17a_same(1000000000.0, $g17a_entry['total_elapsed_ms'] ?? null, 'Performance timing cap changed.');
g17a_same(0, substr_count((string) json_encode($g17a_entry), $g17a_sentinel), 'Performance action leaked forbidden sentinel context.');
foreach (array('request_uri', 'current_user_id', 'actor_id', 'pid', 'query_count', 'sql', 'meta_key', 'arbitrary', 'nested', 'runtime_ms', 'duration_ms') as $forbidden_key) {
	g17a_assert(!array_key_exists($forbidden_key, $g17a_entry), 'Performance action retained forbidden key: ' . $forbidden_key);
}
g17a_same($g17a_operational_before_perf, count($GLOBALS['g17a_operational_records']), 'Performance action must not persist through the operational adapter.');
g17a_assert(!is_file($GLOBALS['g17a_filtered_perf_path']), 'Enabled performance tracing must write no file.');

$g17a_resync_eval = g17a_rename_function($g17a_resync, 'vms_resync_event_to_calendar', 'g17a_resync_event_to_calendar');
$g17a_resync_eval = g17a_replace_once($g17a_resync_eval, "function_exists('tribe_update_event')", 'g17a_tribe_update_event_available()', 'TEC provider availability injection');
eval($g17a_resync_eval);
$GLOBALS['g17a_operational_records'] = array();
$GLOBALS['g17a_tribe_available'] = false;
$g17a_provider_result = g17a_resync_event_to_calendar(101, new WP_Post(), 202);
g17a_same(false, $g17a_provider_result, 'Missing TEC provider return behavior changed.');
g17a_same(array(
	'event_code' => 'event_plan_tec_provider_unavailable',
	'context' => array(
		'service' => 'the_events_calendar',
		'operation' => 'resync_event',
		'status' => 'unavailable',
		'plan_id' => 101,
		'event_id' => 202,
	),
), $GLOBALS['g17a_operational_records'][0] ?? null, 'Missing TEC provider structured event changed.');

function vms_build_tec_event_args(int $plan_id, int $tec_event_id = 0): array
{
	$GLOBALS['g17a_tec_order'][] = 'build_args';
	return array('post_title' => 'Safe Event', 'plan_id' => $plan_id, 'tec_event_id' => $tec_event_id);
}

function get_post_thumbnail_id(int $post_id): int
{
	unset($post_id);
	return 0;
}

function tribe_update_event(int $event_id, array $args)
{
	$GLOBALS['g17a_tec_order'][] = 'tribe_update';
	$GLOBALS['g17a_tec_update_args'][] = array($event_id, $args);
	return $GLOBALS['g17a_tribe_result'];
}

$GLOBALS['g17a_tec_order'] = array();
$GLOBALS['g17a_tec_update_args'] = array();
$GLOBALS['g17a_tribe_available'] = true;
$GLOBALS['g17a_tribe_result'] = new WP_Error('tec_transport_failed', $g17a_sentinel);
$g17a_resync_failure = g17a_resync_event_to_calendar(303, new WP_Post(), 404);
g17a_same(false, $g17a_resync_failure, 'TEC resync failure return behavior changed.');
g17a_same(array('build_args', 'tribe_update'), $GLOBALS['g17a_tec_order'], 'TEC resync failure order changed.');
$g17a_resync_record = $GLOBALS['g17a_operational_records'][1] ?? array();
g17a_same('event_plan_tec_resync_failed', $g17a_resync_record['event_code'] ?? '', 'TEC resync failure event changed.');
g17a_same(array(
	'service' => 'the_events_calendar',
	'operation' => 'resync_event',
	'status' => 'failed',
	'plan_id' => 303,
	'event_id' => 404,
), $g17a_resync_record['context'] ?? array(), 'TEC resync safe context changed.');
g17a_same('tec_transport_failed', $g17a_resync_record['error']['error_code'] ?? '', 'TEC resync error identity changed.');
g17a_assert(preg_match('/^[a-f0-9]{24}$/', (string) ($g17a_resync_record['error']['error_fingerprint'] ?? '')) === 1, 'TEC resync error fingerprint changed.');
g17a_same(0, substr_count((string) json_encode($g17a_resync_record), $g17a_sentinel), 'TEC resync event leaked raw error content.');

eval(g17a_rename_function($g17a_extras, 'vms_tec_sync_event_extras_from_plan', 'g17a_tec_sync_event_extras_from_plan'));
$GLOBALS['g17a_tec_order'] = array();
$GLOBALS['g17a_post_meta_deletes'] = array();
$GLOBALS['g17a_tribe_result'] = new WP_Error('tec_extras_failed', $g17a_sentinel);
$g17a_extras_result = g17a_tec_sync_event_extras_from_plan(505, 606);
g17a_same(null, $g17a_extras_result, 'TEC extras void return behavior changed.');
g17a_same(array('build_args', 'tribe_update'), $GLOBALS['g17a_tec_order'], 'TEC extras update order changed.');
g17a_same(array(array(606, '_EventOrganizerID')), $GLOBALS['g17a_post_meta_deletes'], 'TEC extras organizer cleanup order changed.');
$g17a_extras_record = $GLOBALS['g17a_operational_records'][2] ?? array();
g17a_same('event_plan_tec_extras_sync_failed', $g17a_extras_record['event_code'] ?? '', 'TEC extras failure event changed.');
g17a_same(array(
	'service' => 'the_events_calendar',
	'operation' => 'sync_event_extras',
	'status' => 'failed',
	'plan_id' => 505,
	'event_id' => 606,
), $g17a_extras_record['context'] ?? array(), 'TEC extras safe context changed.');
g17a_same('tec_extras_failed', $g17a_extras_record['error']['error_code'] ?? '', 'TEC extras error identity changed.');
g17a_same(0, substr_count((string) json_encode($g17a_extras_record), $g17a_sentinel), 'TEC extras event leaked raw error content.');

fwrite(STDOUT, "G17 Event Plans/performance diagnostics remediation OK.\n");
