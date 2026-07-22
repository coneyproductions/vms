<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('MINUTE_IN_SECONDS')) {
	define('MINUTE_IN_SECONDS', 60);
}

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function vms_test_assert_same($expected, $actual, string $message): void
{
	if ($expected !== $actual) {
		throw new RuntimeException(
			$message
			. "\nExpected: " . var_export($expected, true)
			. "\nActual: " . var_export($actual, true)
		);
	}
}

function vms_test_assert_true(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function vms_test_assert_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert_true(strpos($haystack, $needle) !== false, $message . "\nMissing: {$needle}");
}

function vms_test_assert_not_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert_true(strpos($haystack, $needle) === false, $message . "\nFound unexpected: {$needle}");
}

function vms_test_read_file(string $path): string
{
	$contents = file_get_contents($path);
	if (!is_string($contents)) {
		throw new RuntimeException("Failed reading file: {$path}");
	}

	return $contents;
}

function vms_test_extract_section(string $source, string $startMarker, string $endMarker): string
{
	$start = strpos($source, $startMarker);
	$end = strpos($source, $endMarker);
	if ($start === false || $end === false || $end <= $start) {
		throw new RuntimeException("Unable to extract source section between markers: {$startMarker} / {$endMarker}");
	}

	return substr($source, $start, $end - $start);
}

/**
 * @param mixed $value
 */
function vms_test_assert_recursive_not_contains(string $needle, $value, string $message): void
{
	if (is_array($value)) {
		foreach ($value as $item) {
			vms_test_assert_recursive_not_contains($needle, $item, $message);
		}
		return;
	}

	if (is_object($value)) {
		foreach (get_object_vars($value) as $item) {
			vms_test_assert_recursive_not_contains($needle, $item, $message);
		}
		return;
	}

	if (is_string($value)) {
		vms_test_assert_true(strpos($value, $needle) === false, $message . "\nFound raw snapshot fragment in: {$value}");
	}
}

function vms_test_reset_runtime_state(): void
{
	$GLOBALS['vms_test_runtime_warnings'] = array();
	$GLOBALS['vms_test_provider_mode'] = 'success';
	$GLOBALS['vms_test_provider_lookups'] = array();
	$GLOBALS['vms_test_build_payload_calls'] = array();
	$GLOBALS['vms_test_publish_calls'] = array();
	$GLOBALS['vms_test_queue_updates'] = array();
	$GLOBALS['vms_test_audit_logs'] = array();
	$GLOBALS['vms_test_map_calls'] = array();
	$GLOBALS['vms_test_map_result'] = array('account_id' => 77);
	$GLOBALS['vms_test_auth_state_calls'] = array();
	$GLOBALS['vms_test_render_result'] = array(
		'ok' => true,
		'context' => array('event_id' => 303),
		'rendered' => array(
			'caption' => 'Caption',
			'base_url' => 'https://example.test/base',
			'final_url' => 'https://example.test/final',
			'length' => 7,
			'limit' => 280,
			'needs_review' => false,
			'needs_review_reason' => '',
		),
	);
}

function vms_test_warning_count(): int
{
	return count((array) ($GLOBALS['vms_test_runtime_warnings'] ?? array()));
}

/**
 * @param array<string,mixed> $overrides
 * @return array<string,mixed>
 */
function vms_test_run_queue_case(array $overrides): array
{
	vms_test_reset_runtime_state();

	$GLOBALS['vms_test_provider_mode'] = (string) ($overrides['provider_mode'] ?? 'success');
	$GLOBALS['vms_test_map_result'] = array(
		'account_id' => (int) ($overrides['map_account_id'] ?? 77),
	);
	if (isset($overrides['render_result']) && is_array($overrides['render_result'])) {
		$GLOBALS['vms_test_render_result'] = $overrides['render_result'];
	}

	$row = array(
		'id' => (int) ($overrides['id'] ?? 101),
		'platform' => (string) ($overrides['platform'] ?? 'mock'),
		'venue_id' => (int) ($overrides['venue_id'] ?? 22),
		'event_id' => (int) ($overrides['event_id'] ?? 303),
		'attempts' => (int) ($overrides['attempts'] ?? 2),
		'destination_id' => (string) ($overrides['destination_id'] ?? 'dest-1'),
		'payload_snapshot_json' => $overrides['payload_snapshot_json'] ?? '',
		'platform_post_id' => (string) ($overrides['platform_post_id'] ?? ''),
	);

	$warningBefore = vms_test_warning_count();
	vms_social_queue_process_item($row);
	$warningAfter = vms_test_warning_count();

	$updates = $GLOBALS['vms_test_queue_updates'];
	$audits = $GLOBALS['vms_test_audit_logs'];
	$finalUpdate = $updates === array() ? array() : (array) end($updates);
	$finalData = is_array($finalUpdate['data'] ?? null) ? (array) $finalUpdate['data'] : array();
	$finalAudit = $audits === array() ? array() : (array) end($audits);
	$auditDetails = is_array($finalAudit['details'] ?? null) ? (array) $finalAudit['details'] : array();

	return array(
		'provider_lookup_count' => count($GLOBALS['vms_test_provider_lookups']),
		'build_payload_count' => count($GLOBALS['vms_test_build_payload_calls']),
		'publish_count' => count($GLOBALS['vms_test_publish_calls']),
		'map_count' => count($GLOBALS['vms_test_map_calls']),
		'auth_state_count' => count($GLOBALS['vms_test_auth_state_calls']),
		'selected_account' => $GLOBALS['vms_test_publish_calls'][0]['account_id'] ?? null,
		'final_status' => (string) ($finalData['status'] ?? ''),
		'final_error_code' => (string) ($finalData['last_error_code'] ?? ''),
		'final_error_message' => (string) ($finalData['last_error_message'] ?? ''),
		'final_attempts' => $finalData['attempts'] ?? null,
		'next_attempt_at_utc' => $finalData['next_attempt_at_utc'] ?? null,
		'platform_post_id' => (string) ($finalData['platform_post_id'] ?? ''),
		'updates' => $updates,
		'audits' => $audits,
		'audit_details' => $auditDetails,
		'warning_count' => $warningAfter - $warningBefore,
	);
}

if (!function_exists('add_action')) {
	function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
	{
		unset($hook, $callback, $priority, $acceptedArgs);
		return true;
	}
}

if (!function_exists('add_filter')) {
	function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
	{
		unset($hook, $callback, $priority, $acceptedArgs);
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

if (!function_exists('wp_next_scheduled')) {
	function wp_next_scheduled(string $hook)
	{
		unset($hook);
		return false;
	}
}

if (!function_exists('wp_schedule_event')) {
	function wp_schedule_event(int $timestamp, string $recurrence, string $hook, array $args = array(), bool $wpError = false): bool
	{
		unset($timestamp, $recurrence, $hook, $args, $wpError);
		return true;
	}
}

if (!function_exists('get_transient')) {
	function get_transient(string $key)
	{
		unset($key);
		return false;
	}
}

if (!function_exists('set_transient')) {
	function set_transient(string $key, string $value, int $expiration): bool
	{
		unset($key, $value, $expiration);
		return true;
	}
}

if (!function_exists('delete_transient')) {
	function delete_transient(string $key): bool
	{
		unset($key);
		return true;
	}
}

if (!interface_exists('VMS_Social_Provider_Interface')) {
	interface VMS_Social_Provider_Interface
	{
		/**
		 * @param array<string,mixed> $payload
		 * @param array<string,mixed> $context
		 * @return array<string,mixed>
		 */
		public function build_payload(array $payload, array $context): array;

		/**
		 * @param array<string,mixed> $providerPayload
		 * @return array<string,mixed>
		 */
		public function publish(int $accountId, string $destinationId, array $providerPayload): array;

		/**
		 * @return array<string,mixed>
		 */
		public function classify_error(Throwable $error): array;
	}
}

if (!class_exists('VMS_Test_Social_Queue_Provider')) {
	final class VMS_Test_Social_Queue_Provider implements VMS_Social_Provider_Interface
	{
		public function build_payload(array $payload, array $context): array
		{
			$GLOBALS['vms_test_build_payload_calls'][] = array(
				'payload' => $payload,
				'context' => $context,
			);

			return array(
				'built' => true,
				'payload' => $payload,
				'context' => $context,
			);
		}

		public function publish(int $accountId, string $destinationId, array $providerPayload): array
		{
			$GLOBALS['vms_test_publish_calls'][] = array(
				'account_id' => $accountId,
				'destination_id' => $destinationId,
				'provider_payload' => $providerPayload,
			);

			$mode = (string) ($GLOBALS['vms_test_provider_mode'] ?? 'success');
			if ($mode === 'throw_retry') {
				throw new RuntimeException('retry me');
			}

			if ($mode === 'throw_auth') {
				throw new RuntimeException('auth expired');
			}

			return array(
				'platform_post_id' => 'post-123',
				'ok' => true,
			);
		}

		public function classify_error(Throwable $error): array
		{
			unset($error);

			$mode = (string) ($GLOBALS['vms_test_provider_mode'] ?? 'success');
			if ($mode === 'throw_retry') {
				return array(
					'retryable' => true,
					'needs_review' => false,
					'auth_expired' => false,
					'error_code' => 'retryable_error',
					'message' => 'Retry later',
				);
			}

			if ($mode === 'throw_auth') {
				return array(
					'retryable' => false,
					'needs_review' => true,
					'auth_expired' => true,
					'error_code' => 'auth_expired',
					'message' => 'Auth expired',
				);
			}

			return array(
				'retryable' => false,
				'needs_review' => true,
				'auth_expired' => false,
				'error_code' => 'provider_error',
				'message' => 'Provider error',
			);
		}
	}
}

if (!function_exists('vms_social_queue_render_for_row')) {
	function vms_social_queue_render_for_row(array $row): array
	{
		unset($row);
		return (array) $GLOBALS['vms_test_render_result'];
	}
}

if (!function_exists('vms_social_get_provider')) {
	function vms_social_get_provider(string $platform)
	{
		$GLOBALS['vms_test_provider_lookups'][] = $platform;
		return new VMS_Test_Social_Queue_Provider();
	}
}

if (!function_exists('vms_social_queue_update')) {
	function vms_social_queue_update(int $queueId, array $data): void
	{
		$GLOBALS['vms_test_queue_updates'][] = array(
			'queue_id' => $queueId,
			'data' => $data,
		);
	}
}

if (!function_exists('vms_social_audit_log')) {
	function vms_social_audit_log(string $action, array $details, int $queueId, string $platform, int $userId): void
	{
		$GLOBALS['vms_test_audit_logs'][] = array(
			'action' => $action,
			'details' => $details,
			'queue_id' => $queueId,
			'platform' => $platform,
			'user_id' => $userId,
		);
	}
}

if (!function_exists('vms_social_venue_map_for_platform')) {
	function vms_social_venue_map_for_platform(int $venueId, string $platform): array
	{
		$GLOBALS['vms_test_map_calls'][] = array(
			'venue_id' => $venueId,
			'platform' => $platform,
		);
		return (array) $GLOBALS['vms_test_map_result'];
	}
}

if (!function_exists('vms_social_get_settings')) {
	function vms_social_get_settings(): array
	{
		return array('max_attempts' => 5);
	}
}

if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($value): string
	{
		if (!is_scalar($value)) {
			return '';
		}

		return trim((string) $value);
	}
}

if (!function_exists('sanitize_key')) {
	function sanitize_key($value): string
	{
		if (!is_scalar($value)) {
			return '';
		}

		$sanitized = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value));
		return is_string($sanitized) ? $sanitized : '';
	}
}

if (!function_exists('wp_json_encode')) {
	function wp_json_encode($value)
	{
		return json_encode($value);
	}
}

if (!function_exists('wp_generate_uuid4')) {
	function wp_generate_uuid4(): string
	{
		return 'uuid-1234';
	}
}

if (!function_exists('vms_social_sanitize_details')) {
	function vms_social_sanitize_details($value)
	{
		return $value;
	}
}

if (!function_exists('vms_social_account_set_auth_state')) {
	function vms_social_account_set_auth_state(int $accountId, string $state, array $details): void
	{
		$GLOBALS['vms_test_auth_state_calls'][] = array(
			'account_id' => $accountId,
			'state' => $state,
			'details' => $details,
		);
	}
}

if (!function_exists('vms_social_next_attempt_utc')) {
	function vms_social_next_attempt_utc(int $attempt): string
	{
		unset($attempt);
		return '2099-01-01 00:00:00';
	}
}

set_error_handler(
	static function (int $errno, string $errstr, string $errfile, int $errline): bool {
		$GLOBALS['vms_test_runtime_warnings'][] = array(
			'errno' => $errno,
			'errstr' => $errstr,
			'errfile' => $errfile,
			'errline' => $errline,
		);
		return true;
	}
);

$repoRoot = dirname(__DIR__);
$runnerPath = $repoRoot . '/includes/social-share/queue-runner.php';
$liveRunnerPath = dirname(dirname($repoRoot)) . '/vms/includes/social-share/queue-runner.php';
$queueRepoPath = $repoRoot . '/includes/social-share/queue-repo.php';
$eventPanelPath = $repoRoot . '/includes/social-share/event-plan-panel.php';
$providerDir = $repoRoot . '/includes/social-share/providers';

$runnerSource = vms_test_read_file($runnerPath);
$liveRunnerSource = vms_test_read_file($liveRunnerPath);
$queueRepoSource = vms_test_read_file($queueRepoPath);
$eventPanelSource = vms_test_read_file($eventPanelPath);
$helperSection = vms_test_extract_section(
	$runnerSource,
	"if (!function_exists('vms_social_queue_decode_payload_snapshot')) {",
	"if (!function_exists('vms_social_queue_process_item')) {"
);
$runnerSection = vms_test_extract_section(
	$runnerSource,
	"if (!function_exists('vms_social_queue_process_item')) {",
	"if (!function_exists('vms_social_process_queue')) {"
);

require $runnerPath;

vms_test_assert_true(function_exists('vms_social_queue_decode_payload_snapshot'), 'Snapshot helper should exist.');
vms_test_assert_true(function_exists('vms_social_queue_process_item'), 'Queue runner should exist.');
vms_test_assert_contains('vms_social_queue_decode_payload_snapshot(', $runnerSection, 'Runner should use the snapshot helper.');
vms_test_assert_same(0, substr_count($runnerSection, 'json_decode('), 'Runner body should not use raw json_decode().');
vms_test_assert_same(1, substr_count($helperSection, 'json_decode('), 'Helper should retain exactly one raw json_decode().');
vms_test_assert_same(1, substr_count($runnerSource, 'json_decode('), 'Queue runner file should contain exactly one raw json_decode().');
vms_test_assert_same(1, substr_count($runnerSection, 'vms_social_get_provider('), 'Exactly one provider lookup should remain in the runner.');

$snapshotStatePos = strpos($runnerSection, '$snapshot_state = vms_social_queue_decode_payload_snapshot');
$providerLookupPos = strpos($runnerSection, '$provider = vms_social_get_provider');
vms_test_assert_true($snapshotStatePos !== false && $providerLookupPos !== false && $snapshotStatePos < $providerLookupPos, 'Provider lookup should occur after snapshot validation begins.');
vms_test_assert_true(
	preg_match('/else\s*\{\s*\$mark_snapshot_for_review\(.+?\);\s*return;\s*\}\s*\$provider = vms_social_get_provider/s', $runnerSection) === 1,
	'Invalid or unknown snapshot branch should return before provider lookup.'
);
vms_test_assert_same(hash('sha256', $runnerSource), hash('sha256', $liveRunnerSource), 'Mirror and live queue-runner files should be byte-identical.');
vms_test_assert_true(strpos($queueRepoSource, 'vms_social_queue_decode_payload_snapshot') === false, 'queue-repo should not reference the snapshot helper.');
vms_test_assert_true(strpos($queueRepoSource, 'queue_snapshot_') === false, 'queue-repo should remain outside this snapshot slice.');
vms_test_assert_contains("json_decode((string) (\$account['meta_json'] ?? ''), true);", $queueRepoSource, 'meta_json decode should remain in queue-repo.');
vms_test_assert_contains("'payload_snapshot_json' => array(", $eventPanelSource, 'Queued snapshot writer should remain array-backed.');
vms_test_assert_contains("'queued_from' => 'event_panel'", $eventPanelSource, 'Queued snapshot writer should preserve queued_from.');
vms_test_assert_contains("'account_id' => is_array(\$map) ? (int) (\$map['account_id'] ?? 0) : 0", $eventPanelSource, 'Queued snapshot writer should preserve account_id.');
vms_test_assert_contains("'event_title' => (string) (\$context['event_title'] ?? '')", $eventPanelSource, 'Queued snapshot writer should preserve event_title.');
vms_test_assert_contains("'payload_snapshot_json' => wp_json_encode(\$rendered)", $runnerSource, 'Rendered-preview writer should remain unchanged.');
vms_test_assert_contains("'rendered' => \$rendered", $runnerSource, 'Provider-result writer should preserve rendered payload.');
vms_test_assert_contains("'provider_payload' => vms_social_sanitize_details(\$provider_payload)", $runnerSource, 'Provider-result writer should preserve sanitized provider payload.');
vms_test_assert_contains("'provider_result' => vms_social_sanitize_details(\$result)", $runnerSource, 'Provider-result writer should preserve sanitized provider result.');

$providerFiles = glob($providerDir . '/*.php');
vms_test_assert_true(is_array($providerFiles) && $providerFiles !== array(), 'Provider adapter files should exist.');
foreach ($providerFiles as $providerFile) {
	$providerSource = vms_test_read_file($providerFile);
	vms_test_assert_true(strpos($providerSource, 'queue_snapshot_') === false, 'Provider adapter should not contain queue snapshot remediation markers: ' . basename($providerFile));
	vms_test_assert_true(strpos($providerSource, 'vms_social_queue_decode_payload_snapshot') === false, 'Provider adapter should not reference the snapshot helper: ' . basename($providerFile));
}

$helperCases = array(
	'queued_positive' => array(
		'raw' => '{"queued_from":"event_panel","account_id":55,"event_title":"Concert"}',
		'schema' => 'queued',
		'ok' => true,
		'account' => 55,
		'reason' => '',
	),
	'queued_numeric_string' => array(
		'raw' => '{"queued_from":"event_panel","account_id":"57","event_title":"Concert"}',
		'schema' => 'queued',
		'ok' => true,
		'account' => 57,
		'reason' => '',
	),
	'queued_zero_account' => array(
		'raw' => '{"queued_from":"event_panel","account_id":0,"event_title":"Concert"}',
		'schema' => 'queued',
		'ok' => true,
		'account' => 0,
		'reason' => 'queue_snapshot_account_invalid',
	),
	'queued_negative_account' => array(
		'raw' => '{"queued_from":"event_panel","account_id":-9,"event_title":"Concert"}',
		'schema' => 'queued',
		'ok' => true,
		'account' => 0,
		'reason' => 'queue_snapshot_account_invalid',
	),
	'queued_non_numeric_account' => array(
		'raw' => '{"queued_from":"event_panel","account_id":"abc","event_title":"Concert"}',
		'schema' => 'queued',
		'ok' => true,
		'account' => 0,
		'reason' => 'queue_snapshot_account_invalid',
	),
	'rendered_preview' => array(
		'raw' => '{"caption":"Caption","base_url":"https://example.test/base","final_url":"https://example.test/final","length":20,"limit":280,"needs_review":true,"needs_review_reason":"caption_too_long"}',
		'schema' => 'rendered_preview',
		'ok' => true,
		'account' => 0,
		'reason' => '',
		'allow_fallback_account' => true,
	),
	'provider_result' => array(
		'raw' => '{"rendered":{"caption":"Caption"},"provider_payload":{"nested":{"media":["hero"]}},"provider_result":{"id":"abc","counts":{"likes":1}}}',
		'schema' => 'provider_result',
		'ok' => true,
		'account' => 0,
		'reason' => '',
	),
	'empty_object' => array(
		'raw' => '{}',
		'schema' => 'invalid',
		'ok' => false,
		'account' => 0,
		'reason' => 'queue_snapshot_non_object',
	),
	'unknown_object' => array(
		'raw' => '{"foo":"bar"}',
		'schema' => 'unknown',
		'ok' => false,
		'account' => 0,
		'reason' => 'queue_snapshot_unknown_schema',
	),
	'arbitrary_account_only' => array(
		'raw' => '{"account_id":999}',
		'schema' => 'unknown',
		'ok' => false,
		'account' => 0,
		'reason' => 'queue_snapshot_unknown_schema',
	),
	'missing_required_keys' => array(
		'raw' => '{"queued_from":"event_panel","account_id":55}',
		'schema' => 'unknown',
		'ok' => false,
		'account' => 0,
		'reason' => 'queue_snapshot_unknown_schema',
	),
	'list_json' => array(
		'raw' => '[1,2,3]',
		'schema' => 'invalid',
		'ok' => false,
		'account' => 0,
		'reason' => 'queue_snapshot_non_object',
	),
	'empty_list_json' => array(
		'raw' => '[]',
		'schema' => 'invalid',
		'ok' => false,
		'account' => 0,
		'reason' => 'queue_snapshot_non_object',
	),
	'scalar_string' => array(
		'raw' => '"hello"',
		'schema' => 'invalid',
		'ok' => false,
		'account' => 0,
		'reason' => 'queue_snapshot_non_object',
	),
	'number' => array(
		'raw' => '123',
		'schema' => 'invalid',
		'ok' => false,
		'account' => 0,
		'reason' => 'queue_snapshot_non_object',
	),
	'true' => array(
		'raw' => 'true',
		'schema' => 'invalid',
		'ok' => false,
		'account' => 0,
		'reason' => 'queue_snapshot_non_object',
	),
	'false' => array(
		'raw' => 'false',
		'schema' => 'invalid',
		'ok' => false,
		'account' => 0,
		'reason' => 'queue_snapshot_non_object',
	),
	'null_literal' => array(
		'raw' => 'null',
		'schema' => 'invalid',
		'ok' => false,
		'account' => 0,
		'reason' => 'queue_snapshot_non_object',
	),
	'empty_string' => array(
		'raw' => '',
		'schema' => 'empty',
		'ok' => false,
		'account' => 0,
		'reason' => 'queue_snapshot_empty',
	),
	'whitespace_only' => array(
		'raw' => " \n\t ",
		'schema' => 'empty',
		'ok' => false,
		'account' => 0,
		'reason' => 'queue_snapshot_empty',
	),
	'malformed_json' => array(
		'raw' => '{"queued_from":',
		'schema' => 'invalid',
		'ok' => false,
		'account' => 0,
		'reason' => 'queue_snapshot_invalid_json',
	),
	'truncated_json' => array(
		'raw' => '{"queued_from":"event_panel","account_id":55,"event_title":"Concert"',
		'schema' => 'invalid',
		'ok' => false,
		'account' => 0,
		'reason' => 'queue_snapshot_invalid_json',
	),
	'invalid_utf8' => array(
		'raw' => "{\"queued_from\":\"event_panel\",\"event_title\":\"Bad\",\"account_id\":1,\"broken\":\"" . chr(0xC3) . chr(0x28) . "\"}",
		'schema' => 'invalid',
		'ok' => false,
		'account' => 0,
		'reason' => 'queue_snapshot_invalid_json',
	),
	'excessive_depth' => array(
		'raw' => str_repeat('{"a":', 40) . '1' . str_repeat('}', 40),
		'schema' => 'invalid',
		'ok' => false,
		'account' => 0,
		'reason' => 'queue_snapshot_invalid_json',
	),
	'duplicate_account_keys' => array(
		'raw' => '{"queued_from":"event_panel","account_id":11,"account_id":"42","event_title":"Concert"}',
		'schema' => 'queued',
		'ok' => true,
		'account' => 42,
		'reason' => '',
	),
	'large_valid_object' => array(
		'raw' => json_encode(array(
			'queued_from' => 'event_panel',
			'account_id' => 18,
			'event_title' => str_repeat('Big Event ', 600),
			'extra' => str_repeat('x', 4096),
		)),
		'schema' => 'queued',
		'ok' => true,
		'account' => 18,
		'reason' => '',
	),
);

foreach ($helperCases as $label => $case) {
	$warningBefore = vms_test_warning_count();
	$result = vms_social_queue_decode_payload_snapshot($case['raw']);
	$warningAfter = vms_test_warning_count();

	vms_test_assert_same($case['schema'], $result['schema'] ?? null, "Unexpected schema for helper case {$label}.");
	vms_test_assert_same($case['ok'], !empty($result['ok']), "Unexpected ok flag for helper case {$label}.");
	vms_test_assert_same($case['account'], (int) ($result['account_id'] ?? 0), "Unexpected account normalization for helper case {$label}.");
	vms_test_assert_same($case['reason'], (string) ($result['reason'] ?? ''), "Unexpected reason for helper case {$label}.");
	vms_test_assert_same(0, $warningAfter - $warningBefore, "Helper case {$label} should not emit warnings.");
	vms_test_assert_true(is_array($result['snapshot'] ?? null), "Helper case {$label} should always return a snapshot array.");
	vms_test_assert_true(strpos((string) ($result['reason'] ?? ''), '{') === false, "Helper case {$label} should not return raw JSON in reason.");
	vms_test_assert_true(strpos((string) ($result['reason'] ?? ''), '[') === false, "Helper case {$label} should not return raw JSON-like reason text.");

	if (array_key_exists('allow_fallback_account', $case)) {
		vms_test_assert_same($case['allow_fallback_account'], !empty($result['allow_fallback_account']), "Unexpected fallback flag for helper case {$label}.");
	}
}

$providerResultSnapshot = vms_social_queue_decode_payload_snapshot('{"rendered":{"caption":"Caption"},"provider_payload":{"nested":{"media":["hero"],"flags":{"published":false}}},"provider_result":{"status":"queued","ids":[1,2]}}');
vms_test_assert_same('hero', $providerResultSnapshot['snapshot']['provider_payload']['nested']['media'][0] ?? null, 'Nested provider payload should remain intact.');
vms_test_assert_same(false, $providerResultSnapshot['snapshot']['provider_payload']['nested']['flags']['published'] ?? null, 'Nested provider payload flags should remain intact.');

$queuedSuccess = vms_test_run_queue_case(array(
	'payload_snapshot_json' => '{"queued_from":"event_panel","account_id":55,"event_title":"Concert"}',
	'map_account_id' => 77,
));
vms_test_assert_same(1, $queuedSuccess['provider_lookup_count'], 'Valid queued publish should perform exactly one provider lookup.');
vms_test_assert_same(0, $queuedSuccess['map_count'], 'Valid queued publish should not consult the venue map.');
vms_test_assert_same(1, $queuedSuccess['publish_count'], 'Valid queued publish should call publish once.');
vms_test_assert_same(1, $queuedSuccess['build_payload_count'], 'Valid queued publish should build payload once.');
vms_test_assert_same(55, $queuedSuccess['selected_account'], 'Valid queued publish should use stored account.');
vms_test_assert_same('posted', $queuedSuccess['final_status'], 'Valid queued publish should post successfully.');
vms_test_assert_same(3, $queuedSuccess['final_attempts'], 'Valid queued publish should increment attempts at publish boundary.');
vms_test_assert_same('', $queuedSuccess['final_error_code'], 'Valid queued publish should clear error code.');
vms_test_assert_same(0, $queuedSuccess['warning_count'], 'Valid queued publish should not emit warnings.');

$renderedPreviewSuccess = vms_test_run_queue_case(array(
	'payload_snapshot_json' => '{"caption":"Caption","base_url":"https://example.test/base","final_url":"https://example.test/final","length":20,"limit":280,"needs_review":true,"needs_review_reason":"caption_too_long"}',
	'map_account_id' => 77,
));
vms_test_assert_same(1, $renderedPreviewSuccess['provider_lookup_count'], 'Rendered-preview fallback should perform exactly one provider lookup.');
vms_test_assert_same(1, $renderedPreviewSuccess['map_count'], 'Rendered-preview fallback should consult the venue map once.');
vms_test_assert_same(77, $renderedPreviewSuccess['selected_account'], 'Rendered-preview fallback should use venue-map account.');
vms_test_assert_same('posted', $renderedPreviewSuccess['final_status'], 'Rendered-preview fallback should preserve normal success behavior.');

$invalidJsonCase = vms_test_run_queue_case(array(
	'payload_snapshot_json' => '{"queued_from":',
));
vms_test_assert_same(0, $invalidJsonCase['provider_lookup_count'], 'Invalid JSON should perform zero provider lookups.');
vms_test_assert_same(0, $invalidJsonCase['map_count'], 'Invalid JSON should perform zero venue-map lookups.');
vms_test_assert_same(0, $invalidJsonCase['build_payload_count'], 'Invalid JSON should not build payload.');
vms_test_assert_same(0, $invalidJsonCase['publish_count'], 'Invalid JSON should not publish.');
vms_test_assert_same('needs_review', $invalidJsonCase['final_status'], 'Invalid JSON should move to needs_review.');
vms_test_assert_same('queue_snapshot_invalid_json', $invalidJsonCase['final_error_code'], 'Invalid JSON should preserve machine-readable reason.');
vms_test_assert_same(null, $invalidJsonCase['final_attempts'], 'Invalid JSON should not increment attempts.');
vms_test_assert_same(null, $invalidJsonCase['next_attempt_at_utc'], 'Invalid JSON should not schedule retry.');
vms_test_assert_same(0, $invalidJsonCase['auth_state_count'], 'Invalid JSON should not mutate auth state.');
vms_test_assert_same(0, $invalidJsonCase['warning_count'], 'Invalid JSON path should not emit warnings.');
vms_test_assert_recursive_not_contains('{"queued_from":', $invalidJsonCase['updates'], 'Invalid JSON should not expose raw snapshot in updates.');
vms_test_assert_recursive_not_contains('{"queued_from":', $invalidJsonCase['audits'], 'Invalid JSON should not expose raw snapshot in audits.');

$unknownObjectCase = vms_test_run_queue_case(array(
	'payload_snapshot_json' => '{"foo":"bar"}',
));
vms_test_assert_same(0, $unknownObjectCase['provider_lookup_count'], 'Unknown object should perform zero provider lookups.');
vms_test_assert_same(0, $unknownObjectCase['map_count'], 'Unknown object should perform zero venue-map lookups.');
vms_test_assert_same(0, $unknownObjectCase['publish_count'], 'Unknown object should not publish.');
vms_test_assert_same('needs_review', $unknownObjectCase['final_status'], 'Unknown object should move to needs_review.');
vms_test_assert_same('queue_snapshot_unknown_schema', $unknownObjectCase['final_error_code'], 'Unknown object should preserve unknown-schema reason.');

$invalidQueuedAccountCase = vms_test_run_queue_case(array(
	'payload_snapshot_json' => '{"queued_from":"event_panel","account_id":0,"event_title":"Concert"}',
));
vms_test_assert_same(0, $invalidQueuedAccountCase['provider_lookup_count'], 'Invalid queued account should perform zero provider lookups.');
vms_test_assert_same(0, $invalidQueuedAccountCase['map_count'], 'Invalid queued account should not fall back to venue map.');
vms_test_assert_same(0, $invalidQueuedAccountCase['publish_count'], 'Invalid queued account should not publish.');
vms_test_assert_same('needs_review', $invalidQueuedAccountCase['final_status'], 'Invalid queued account should move to needs_review.');
vms_test_assert_same('queue_snapshot_account_invalid', $invalidQueuedAccountCase['final_error_code'], 'Invalid queued account should preserve account-invalid reason.');
vms_test_assert_same(null, $invalidQueuedAccountCase['final_attempts'], 'Invalid queued account should not increment attempts.');

$providerResultCase = vms_test_run_queue_case(array(
	'payload_snapshot_json' => '{"rendered":[],"provider_payload":[],"provider_result":[]}',
));
vms_test_assert_same(0, $providerResultCase['provider_lookup_count'], 'Provider-result snapshot should not perform provider lookup.');
vms_test_assert_same(0, $providerResultCase['map_count'], 'Provider-result snapshot should not perform venue-map lookup.');
vms_test_assert_same(0, $providerResultCase['publish_count'], 'Provider-result snapshot should not publish.');
vms_test_assert_same('needs_review', $providerResultCase['final_status'], 'Provider-result snapshot should move to needs_review.');
vms_test_assert_same('queue_snapshot_provider_result', $providerResultCase['final_error_code'], 'Provider-result snapshot should preserve provider-result reason.');

$shortCircuitCase = vms_test_run_queue_case(array(
	'payload_snapshot_json' => '{"rendered":[],"provider_payload":[],"provider_result":[]}',
	'platform_post_id' => 'existing-post',
));
vms_test_assert_same(0, $shortCircuitCase['provider_lookup_count'], 'platform_post_id short-circuit should perform zero provider lookups.');
vms_test_assert_same(0, $shortCircuitCase['publish_count'], 'platform_post_id short-circuit should not publish.');
vms_test_assert_same('posted', $shortCircuitCase['final_status'], 'platform_post_id short-circuit should normalize to posted.');

$retryCase = vms_test_run_queue_case(array(
	'payload_snapshot_json' => '{"queued_from":"event_panel","account_id":55,"event_title":"Concert"}',
	'provider_mode' => 'throw_retry',
));
vms_test_assert_same(1, $retryCase['provider_lookup_count'], 'Retryable failure should still perform one provider lookup.');
vms_test_assert_same(1, $retryCase['publish_count'], 'Retryable failure should still attempt publish once.');
vms_test_assert_same('failed', $retryCase['final_status'], 'Retryable failure should remain failed.');
vms_test_assert_same('retryable_error', $retryCase['final_error_code'], 'Retryable failure should preserve provider error code.');
vms_test_assert_same(3, $retryCase['final_attempts'], 'Retryable failure should increment attempts once.');
vms_test_assert_same('2099-01-01 00:00:00', $retryCase['next_attempt_at_utc'], 'Retryable failure should schedule next attempt.');
vms_test_assert_same(0, $retryCase['auth_state_count'], 'Retryable failure should not mutate auth state.');

$authExpiredCase = vms_test_run_queue_case(array(
	'payload_snapshot_json' => '{"queued_from":"event_panel","account_id":55,"event_title":"Concert"}',
	'provider_mode' => 'throw_auth',
));
vms_test_assert_same(1, $authExpiredCase['provider_lookup_count'], 'Auth-expired failure should still perform one provider lookup.');
vms_test_assert_same(1, $authExpiredCase['publish_count'], 'Auth-expired failure should still attempt publish once.');
vms_test_assert_same('needs_review', $authExpiredCase['final_status'], 'Auth-expired failure should move to needs_review.');
vms_test_assert_same('auth_expired', $authExpiredCase['final_error_code'], 'Auth-expired failure should preserve provider error code.');
vms_test_assert_same(3, $authExpiredCase['final_attempts'], 'Auth-expired failure should increment attempts once.');
vms_test_assert_same(1, $authExpiredCase['auth_state_count'], 'Auth-expired failure should patch auth state once.');

$sequentialFirst = vms_test_run_queue_case(array(
	'payload_snapshot_json' => '{"queued_from":"event_panel","account_id":11,"event_title":"First"}',
	'map_account_id' => 22,
));
$sequentialSecond = vms_test_run_queue_case(array(
	'payload_snapshot_json' => '{"caption":"Second","base_url":"https://example.test/base","final_url":"https://example.test/final","length":20,"limit":280,"needs_review":true,"needs_review_reason":"caption_too_long"}',
	'map_account_id' => 88,
));
vms_test_assert_same(11, $sequentialFirst['selected_account'], 'First sequential item should use its stored account.');
vms_test_assert_same(88, $sequentialSecond['selected_account'], 'Second sequential item should not leak prior account state.');
vms_test_assert_same(1, $sequentialFirst['provider_lookup_count'], 'First sequential item should perform one provider lookup.');
vms_test_assert_same(1, $sequentialSecond['provider_lookup_count'], 'Second sequential item should perform one provider lookup.');

restore_error_handler();

echo "social share queue snapshot json remediation: PASS\n";
