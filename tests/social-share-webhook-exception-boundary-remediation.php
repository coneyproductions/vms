<?php
declare(strict_types=1);

function vms_test_fail(string $message): void
{
	throw new RuntimeException($message);
}

function vms_test_assert_true(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	vms_test_fail($message);
}

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function vms_test_assert_same($expected, $actual, string $message): void
{
	if ($expected === $actual) {
		return;
	}

	vms_test_fail(
		$message
		. "\nExpected: " . var_export($expected, true)
		. "\nActual: " . var_export($actual, true)
	);
}

function vms_test_assert_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert_true(strpos($haystack, $needle) !== false, $message . "\nMissing: " . $needle);
}

function vms_test_assert_not_contains(string $needle, string $haystack, string $message): void
{
	vms_test_assert_true(strpos($haystack, $needle) === false, $message . "\nUnexpected: " . $needle);
}

function vms_test_read_file(string $path): string
{
	$contents = file_get_contents($path);
	if (!is_string($contents) || $contents === '') {
		vms_test_fail('Failed to read source file: ' . $path);
	}

	return $contents;
}

function vms_test_extract_function(string $source, string $name): string
{
	$pattern = '~function\s+' . preg_quote($name, '~') . '\s*\(~';
	if (!preg_match($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
		vms_test_fail('Unable to locate function ' . $name . '.');
	}
	$start = (int) $matches[0][1];

	$brace = strpos($source, '{', $start);
	if ($brace === false) {
		vms_test_fail('Unable to locate opening brace for ' . $name . '.');
	}

	$depth = 1;
	$length = strlen($source);
	for ($i = $brace + 1; $i < $length; $i++) {
		$char = $source[$i];
		if ($char === '{') {
			$depth++;
			continue;
		}
		if ($char === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($source, $start, ($i - $start) + 1);
			}
		}
	}

	vms_test_fail('Unable to locate closing brace for ' . $name . '.');
}

function vms_test_reset_runtime(): void
{
	$GLOBALS['vms_test_tokens'] = array();
	$GLOBALS['vms_test_remote_posts'] = array();
	$GLOBALS['vms_test_wp_remote_response'] = null;
	$GLOBALS['vms_test_provider_lookups'] = array();
	$GLOBALS['vms_test_queue_updates'] = array();
	$GLOBALS['vms_test_audit_logs'] = array();
	$GLOBALS['vms_test_auth_state_calls'] = array();
	$GLOBALS['vms_test_render_result'] = array(
		'ok' => true,
		'rendered' => array(
			'caption' => 'Caption',
			'final_url' => 'https://example.test/events/current',
		),
		'context' => array(
			'event_title' => 'Current Event',
			'event_date' => '2026-07-26',
			'start_time' => '19:00',
			'end_time' => '21:00',
			'venue_name' => 'Main Room',
			'venue_city' => 'Austin',
			'venue_state' => 'TX',
			'featured_image_url' => 'https://example.test/image.jpg',
		),
	);
	$GLOBALS['vms_test_snapshot_state'] = array(
		'ok' => true,
		'schema' => 'queued',
		'account_id' => 7,
	);
}

/**
 * @return array<string,mixed>
 */
function vms_test_seed_queue_row(int $queue_id): array
{
	return array(
		'id' => $queue_id,
		'event_plan_id' => 42,
		'tec_event_id' => 99,
		'venue_id' => 15,
		'platform' => 'webhook',
		'destination_id' => 'dest-queue',
		'attempts' => 0,
		'payload_snapshot_json' => '{"schema":"queued"}',
	);
}

/**
 * @return array<string,mixed>
 */
function vms_test_last_queue_update(): array
{
	$updates = $GLOBALS['vms_test_queue_updates'] ?? array();
	vms_test_assert_true(!empty($updates), 'Expected a queue update to be recorded.');
	$last = $updates[count($updates) - 1];
	vms_test_assert_true(is_array($last) && isset($last['data']) && is_array($last['data']), 'Expected the last queue update payload to be an array.');
	return $last['data'];
}

/**
 * @return array<string,mixed>
 */
function vms_test_last_audit_log(): array
{
	$logs = $GLOBALS['vms_test_audit_logs'] ?? array();
	vms_test_assert_true(!empty($logs), 'Expected an audit log entry to be recorded.');
	$last = $logs[count($logs) - 1];
	vms_test_assert_true(is_array($last), 'Expected the last audit log entry to be an array.');
	return $last;
}

if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/../');
}

interface BVMGR_Social_Provider_Interface
{
	public function get_platform_key(): string;

	public function get_display_name(): string;

	public function get_capabilities(): array;

	public function get_oauth_fields(): array;

	/**
	 * @param array<string,mixed> $args
	 * @return mixed
	 */
	public function start_oauth(array $args = array());

	/**
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	public function handle_oauth_callback(array $request = array()): array;

	/**
	 * @return array<string,mixed>
	 */
	public function validate_connection(int $account_id): array;

	/**
	 * @param array<string,mixed> $queue_row
	 * @param array<string,mixed> $event_context
	 * @return array<string,mixed>
	 */
	public function build_payload(array $queue_row, array $event_context): array;

	/**
	 * @param array<string,mixed> $rendered_payload
	 * @return array<string,mixed>
	 */
	public function publish(int $account_id, string $destination_id, array $rendered_payload): array;

	/**
	 * @param mixed $error
	 * @return array<string,mixed>
	 */
	public function classify_error($error): array;
}

final class WP_Error
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
}

/**
 * @param mixed $value
 */
function is_wp_error($value): bool
{
	return $value instanceof WP_Error;
}

/**
 * @param mixed $url
 */
function esc_url_raw($url): string
{
	return trim((string) $url);
}

/**
 * @param mixed $value
 * @return string|false
 */
function wp_json_encode($value)
{
	return json_encode($value);
}

/**
 * @return array<string,mixed>
 */
function vms_social_account_token_json(int $account_id): array
{
	$tokens = $GLOBALS['vms_test_tokens'] ?? array();
	return is_array($tokens[$account_id] ?? null) ? $tokens[$account_id] : array();
}

/**
 * @param mixed $url
 * @param array<string,mixed> $args
 * @return mixed
 */
function wp_remote_post($url, array $args = array())
{
	$GLOBALS['vms_test_remote_posts'][] = array(
		'url' => (string) $url,
		'args' => $args,
	);

	return $GLOBALS['vms_test_wp_remote_response'];
}

/**
 * @param mixed $response
 */
function wp_remote_retrieve_response_code($response): int
{
	if (!is_array($response)) {
		return 0;
	}

	if (isset($response['response']) && is_array($response['response'])) {
		return (int) ($response['response']['code'] ?? 0);
	}

	return (int) ($response['code'] ?? 0);
}

function wp_generate_uuid4(): string
{
	return 'uuid-1234';
}

/**
 * @param mixed $value
 */
function sanitize_text_field($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$sanitized = strip_tags((string) $value);
	$sanitized = preg_replace('/[\r\n\t ]+/', ' ', $sanitized);
	return trim(is_string($sanitized) ? $sanitized : '');
}

/**
 * @param mixed $value
 */
function sanitize_key($value): string
{
	if (!is_scalar($value)) {
		return '';
	}

	$sanitized = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value));
	return is_string($sanitized) ? $sanitized : '';
}

/**
 * @param mixed $snapshot
 * @return array<string,mixed>
 */
function vms_social_queue_decode_payload_snapshot($snapshot): array
{
	unset($snapshot);
	return $GLOBALS['vms_test_snapshot_state'];
}

/**
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function vms_social_queue_render_for_row(array $row): array
{
	unset($row);
	return $GLOBALS['vms_test_render_result'];
}

/**
 * @return array<string,mixed>|null
 */
function vms_social_venue_map_for_platform(int $venue_id, string $platform): ?array
{
	unset($venue_id, $platform);
	return null;
}

/**
 * @return mixed
 */
function vms_social_get_provider(string $platform)
{
	$GLOBALS['vms_test_provider_lookups'][] = $platform;
	return new BVMGR_Social_Provider_Webhook();
}

/**
 * @param array<string,mixed> $data
 */
function vms_social_queue_update(int $queue_id, array $data): void
{
	$GLOBALS['vms_test_queue_updates'][] = array(
		'queue_id' => $queue_id,
		'data' => $data,
	);
}

/**
 * @param array<string,mixed> $details
 */
function vms_social_audit_log(string $action, array $details = array(), int $queue_id = 0, string $platform = '', ?int $actor_user_id = null): void
{
	$GLOBALS['vms_test_audit_logs'][] = array(
		'action' => $action,
		'details' => $details,
		'queue_id' => $queue_id,
		'platform' => $platform,
		'actor_user_id' => $actor_user_id,
	);
}

/**
 * @param array<string,mixed> $meta_patch
 */
function vms_social_account_set_auth_state(int $account_id, string $auth_state, array $meta_patch = array()): void
{
	$GLOBALS['vms_test_auth_state_calls'][] = array(
		'account_id' => $account_id,
		'auth_state' => $auth_state,
		'meta_patch' => $meta_patch,
	);
}

/**
 * @return array<string,int>
 */
function vms_social_get_settings(): array
{
	return array('max_attempts' => 5);
}

function vms_social_next_attempt_utc(int $attempt): string
{
	unset($attempt);
	return '2099-01-01 00:00:00';
}

/**
 * @param mixed $value
 * @return mixed
 */
function vms_social_sanitize_details($value)
{
	return $value;
}

try {
	$pluginRoot = dirname(__DIR__);
	$webhookPath = $pluginRoot . '/includes/social-share/providers/class-provider-webhook.php';
	$queueRunnerPath = $pluginRoot . '/includes/social-share/queue-runner.php';
	$ticketingPath = $pluginRoot . '/includes/integrations/ticketing-rules-v2.php';
	$adminPath = $pluginRoot . '/includes/social-share/admin.php';
	$eventPanelPath = $pluginRoot . '/includes/social-share/event-plan-panel.php';
	$auditPath = $pluginRoot . '/includes/social-share/audit.php';

	$webhookSource = vms_test_read_file($webhookPath);
	$queueRunnerSource = vms_test_read_file($queueRunnerPath);
	$ticketingSource = vms_test_read_file($ticketingPath);
	$adminSource = vms_test_read_file($adminPath);
	$eventPanelSource = vms_test_read_file($eventPanelPath);
	$auditSource = vms_test_read_file($auditPath);

	$ignoreToken = 'phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped';
	vms_test_assert_same(
		2,
		substr_count($webhookSource, $ignoreToken),
		'Webhook provider should keep exactly two line-specific ExceptionNotEscaped suppressions.'
	);
	vms_test_assert_same(
		2,
		substr_count($ticketingSource, $ignoreToken),
		'Ticketing exception suppressions should remain unchanged in this child.'
	);
	vms_test_assert_contains(
		"throw new RuntimeException((string) \$response->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal plain-text webhook transport diagnostic; the queue runner sanitizes it for storage and downstream sinks escape or JSON-encode it contextually.",
		$webhookSource,
		'Webhook transport throw should keep the bounded same-line ExceptionNotEscaped suppression.'
	);
	vms_test_assert_contains(
		"throw new RuntimeException('Webhook returned HTTP ' . \$code); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal plain-text webhook status diagnostic; the queue runner sanitizes it for storage and downstream sinks escape or JSON-encode it contextually.",
		$webhookSource,
		'Webhook status throw should keep the bounded same-line ExceptionNotEscaped suppression.'
	);
	vms_test_assert_contains(
		"\$message = sanitize_text_field((string) (\$class['message'] ?? \$error->getMessage()));",
		$queueRunnerSource,
		'Queue runner should sanitize provider exception messages before storing them.'
	);
	vms_test_assert_contains(
		"echo '<td>' . esc_html((string) \$row['last_error_message']) . '</td>';",
		$adminSource,
		'Social Sharing admin queue table should escape stored error text at render time.'
	);
	vms_test_assert_contains(
		"echo '<p class=\"description\">' . esc_html((string) \$last_queue['last_error_message']) . '</p>';",
		$eventPanelSource,
		'Social Sharing event panel should escape stored error text at render time.'
	);
	vms_test_assert_contains(
		"\$details_json = wp_json_encode(\$sanitized_details);",
		$auditSource,
		'Social Sharing audit logging should JSON-encode structured details instead of HTML-escaping them at construction.'
	);

	require_once $webhookPath;
	eval(vms_test_extract_function($queueRunnerSource, 'vms_social_queue_process_item'));

	$GLOBALS['vms_test_tokens'][7] = array(
		'webhook_url' => 'https://hooks.example.test/publish?token=secret-query-token',
		'signing_secret' => 'secret-signing-value',
	);

	vms_test_reset_runtime();
	$GLOBALS['vms_test_tokens'][7] = array(
		'webhook_url' => 'https://hooks.example.test/publish?token=secret-query-token',
		'signing_secret' => 'secret-signing-value',
	);
	$provider = new BVMGR_Social_Provider_Webhook();
	$successPayload = array(
		'caption' => 'Launch now',
		'count' => 1,
	);
	$GLOBALS['vms_test_wp_remote_response'] = array(
		'response' => array('code' => 204),
	);
	$successResult = $provider->publish(7, 'dest-direct', $successPayload);
	vms_test_assert_same('webhook-uuid-1234', $successResult['platform_post_id'], 'Webhook publish success should preserve the platform post ID prefix contract.');
	vms_test_assert_same(204, $successResult['http_code'], 'Webhook publish success should preserve the response code in the result payload.');
	vms_test_assert_same('dest-direct', $successResult['destination_id'], 'Webhook publish success should preserve the destination ID.');
	vms_test_assert_same(1, count($GLOBALS['vms_test_remote_posts']), 'Webhook publish success should issue exactly one remote POST request.');
	$successRequest = $GLOBALS['vms_test_remote_posts'][0];
	vms_test_assert_same('https://hooks.example.test/publish?token=secret-query-token', $successRequest['url'], 'Webhook publish should continue posting to the configured webhook URL.');
	vms_test_assert_same(12, $successRequest['args']['timeout'] ?? null, 'Webhook publish should preserve the current timeout.');
	vms_test_assert_same('application/json', $successRequest['args']['headers']['Content-Type'] ?? null, 'Webhook publish should preserve the JSON content type header.');
	vms_test_assert_same(
		hash_hmac('sha256', (string) json_encode($successPayload), 'secret-signing-value'),
		$successRequest['args']['headers']['X-VMS-Signature'] ?? null,
		'Webhook publish should preserve the HMAC signature header contract.'
	);
	vms_test_assert_same((string) json_encode($successPayload), $successRequest['args']['body'] ?? null, 'Webhook publish should preserve the JSON request body payload.');

	vms_test_reset_runtime();
	$GLOBALS['vms_test_tokens'][7] = array(
		'webhook_url' => 'https://hooks.example.test/publish?token=secret-query-token',
		'signing_secret' => 'secret-signing-value',
	);
	$GLOBALS['vms_test_wp_remote_response'] = new WP_Error('http_request_failed', '<strong>Temporary outage</strong> & retry');
	try {
		$provider->publish(7, 'dest-direct', $successPayload);
		vms_test_fail('Webhook publish should throw on WP_Error responses.');
	} catch (RuntimeException $exception) {
		vms_test_assert_same('<strong>Temporary outage</strong> & retry', $exception->getMessage(), 'Webhook publish should preserve the raw transport diagnostic as plain text for downstream sanitization.');
		vms_test_assert_not_contains('secret-query-token', $exception->getMessage(), 'Webhook transport diagnostics should not expose endpoint query tokens.');
		vms_test_assert_not_contains('hooks.example.test', $exception->getMessage(), 'Webhook transport diagnostics should not expose the configured endpoint host when the underlying transport message does not include it.');
	}

	vms_test_reset_runtime();
	$GLOBALS['vms_test_tokens'][7] = array(
		'webhook_url' => 'https://hooks.example.test/publish?token=secret-query-token',
		'signing_secret' => 'secret-signing-value',
	);
	$GLOBALS['vms_test_wp_remote_response'] = array(
		'response' => array('code' => 403),
		'body' => '<html>denied</html>',
	);
	try {
		$provider->publish(7, 'dest-direct', $successPayload);
		vms_test_fail('Webhook publish should throw on non-2xx HTTP responses.');
	} catch (RuntimeException $exception) {
		vms_test_assert_same('Webhook returned HTTP 403', $exception->getMessage(), 'Webhook publish should preserve the bounded HTTP status diagnostic.');
		vms_test_assert_not_contains('<html>denied</html>', $exception->getMessage(), 'Webhook status diagnostics should not include remote response bodies.');
		vms_test_assert_not_contains('secret-query-token', $exception->getMessage(), 'Webhook status diagnostics should not expose endpoint query tokens.');
	}

	vms_test_reset_runtime();
	$GLOBALS['vms_test_tokens'][7] = array(
		'webhook_url' => 'https://hooks.example.test/publish?token=secret-query-token',
		'signing_secret' => 'secret-signing-value',
	);
	$GLOBALS['vms_test_wp_remote_response'] = new WP_Error('http_request_failed', '<strong>Temporary outage</strong> & retry');
	vms_social_queue_process_item(vms_test_seed_queue_row(101));
	$queueUpdate = vms_test_last_queue_update();
	vms_test_assert_same('failed', $queueUpdate['status'] ?? null, 'Queue runner should preserve the failed status for retryable webhook transport errors.');
	vms_test_assert_same(1, $queueUpdate['attempts'] ?? null, 'Queue runner should increment attempts for webhook failures.');
	vms_test_assert_same('2099-01-01 00:00:00', $queueUpdate['next_attempt_at_utc'] ?? null, 'Queue runner should keep retry scheduling for retryable webhook transport errors.');
	vms_test_assert_same('webhook_error', $queueUpdate['last_error_code'] ?? null, 'Queue runner should preserve the webhook transport error code classification.');
	vms_test_assert_same('Temporary outage & retry', $queueUpdate['last_error_message'] ?? null, 'Queue runner should sanitize stored webhook transport diagnostics down to plain text.');
	vms_test_assert_not_contains('<strong>', (string) ($queueUpdate['last_error_message'] ?? ''), 'Queue runner should strip HTML tags before storing webhook transport diagnostics.');
	vms_test_assert_not_contains('&lt;', (string) ($queueUpdate['last_error_message'] ?? ''), 'Queue runner should store plain text, not pre-escaped HTML entities.');
	vms_test_assert_same(0, count($GLOBALS['vms_test_auth_state_calls']), 'Queue runner should not mark auth expired for generic transport failures.');
	$transportAudit = vms_test_last_audit_log();
	vms_test_assert_same('publish_fail', $transportAudit['action'] ?? null, 'Queue runner should record webhook transport failures in the publish_fail audit path.');
	vms_test_assert_same('Temporary outage & retry', $transportAudit['details']['message'] ?? null, 'Queue runner should audit the same sanitized plain-text transport diagnostic.');
	vms_test_assert_same('failed', $transportAudit['details']['status'] ?? null, 'Queue runner should preserve the failed audit status for retryable webhook transport errors.');

	vms_test_reset_runtime();
	$GLOBALS['vms_test_tokens'][7] = array(
		'webhook_url' => 'https://hooks.example.test/publish?token=secret-query-token',
		'signing_secret' => 'secret-signing-value',
	);
	$GLOBALS['vms_test_wp_remote_response'] = array(
		'response' => array('code' => 403),
		'body' => '<html>denied</html>',
	);
	vms_social_queue_process_item(vms_test_seed_queue_row(202));
	$authUpdate = vms_test_last_queue_update();
	vms_test_assert_same('failed', $authUpdate['status'] ?? null, 'Queue runner should preserve the failed status for webhook auth failures.');
	vms_test_assert_same(1, $authUpdate['attempts'] ?? null, 'Queue runner should increment attempts for webhook auth failures.');
	vms_test_assert_true(array_key_exists('next_attempt_at_utc', $authUpdate), 'Queue runner auth failures should still persist the next_attempt_at_utc field.');
	vms_test_assert_same(null, $authUpdate['next_attempt_at_utc'], 'Queue runner should not schedule retries after webhook auth failures.');
	vms_test_assert_same('webhook_auth', $authUpdate['last_error_code'] ?? null, 'Queue runner should preserve the webhook auth classification when the bounded 403 status diagnostic is thrown.');
	vms_test_assert_same('Webhook returned HTTP 403', $authUpdate['last_error_message'] ?? null, 'Queue runner should store the bounded HTTP status diagnostic as plain text.');
	vms_test_assert_same(1, count($GLOBALS['vms_test_auth_state_calls']), 'Queue runner should mark webhook auth failures as expired on the account side path.');
	vms_test_assert_same(
		array(
			'account_id' => 7,
			'auth_state' => 'expired',
			'meta_patch' => array('last_error' => 'Webhook returned HTTP 403'),
		),
		$GLOBALS['vms_test_auth_state_calls'][0],
		'Queue runner should store the sanitized plain-text auth diagnostic in account metadata.'
	);
	$authAudit = vms_test_last_audit_log();
	vms_test_assert_same('Webhook returned HTTP 403', $authAudit['details']['message'] ?? null, 'Queue runner should audit the bounded HTTP status diagnostic without adding HTML escaping.');
	vms_test_assert_same('webhook_auth', $authAudit['details']['error_code'] ?? null, 'Queue runner should preserve the webhook auth audit classification.');

	echo "social share webhook exception boundary remediation: PASS\n";
} catch (Throwable $throwable) {
	fwrite(STDERR, $throwable->getMessage() . "\n");
	exit(1);
}
