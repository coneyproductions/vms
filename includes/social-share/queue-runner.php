<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_social_retry_backoff_seconds')) {
	/**
	 * @return array<int,int>
	 */
	function vms_social_retry_backoff_seconds(): array
	{
		return array(300, 900, 3600, 21600, 86400);
	}
}

if (!function_exists('vms_social_next_attempt_utc')) {
	function vms_social_next_attempt_utc(int $attempt): ?string
	{
		$attempt = max(1, $attempt);
		$steps = vms_social_retry_backoff_seconds();
		$index = min(count($steps) - 1, $attempt - 1);
		$delay = (int) $steps[$index];
		return gmdate('Y-m-d H:i:s', time() + $delay);
	}
}

if (!function_exists('vms_social_runner_acquire_lock')) {
	function vms_social_runner_acquire_lock(): bool
	{
		$key = defined('BVMGR_SOCIAL_LOCK_TRANSIENT') ? (string) BVMGR_SOCIAL_LOCK_TRANSIENT : 'vms_social_runner_lock';
		if (get_transient($key)) {
			return false;
		}
		set_transient($key, (string) time(), 120);
		return true;
	}
}

if (!function_exists('vms_social_runner_release_lock')) {
	function vms_social_runner_release_lock(): void
	{
		$key = defined('BVMGR_SOCIAL_LOCK_TRANSIENT') ? (string) BVMGR_SOCIAL_LOCK_TRANSIENT : 'vms_social_runner_lock';
		delete_transient($key);
	}
}

if (!function_exists('vms_social_schedule_cron')) {
	function vms_social_schedule_cron(): void
	{
		if (function_exists('bvmgr_should_run_runtime_maintenance') && !bvmgr_should_run_runtime_maintenance()) {
			return;
		}
		$hook = defined('BVMGR_SOCIAL_CRON_HOOK') ? (string) BVMGR_SOCIAL_CRON_HOOK : 'vms_social_process_queue';
		if (!function_exists('bvmgr_schedule_exists') || !bvmgr_schedule_exists('vms_social_5m')) {
			return;
		}
		if (!wp_next_scheduled($hook)) {
			wp_schedule_event(time() + 60, 'vms_social_5m', $hook);
		}
	}
}
add_action('init', 'vms_social_schedule_cron', 30);

add_filter('cron_schedules', function (array $schedules): array {
	if (!isset($schedules['vms_social_5m'])) {
		$schedules['vms_social_5m'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display' => __('Every 5 Minutes (Backstage Venue Manager Social)', 'backstage-venue-manager'),
		);
	}
	return $schedules;
});

if (!function_exists('vms_social_queue_render_for_row')) {
	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	function vms_social_queue_render_for_row(array $row): array
	{
		$context = vms_social_context_from_queue_row($row);
		$template = vms_social_template_for_platform((string) ($row['platform'] ?? ''), (int) ($row['template_id'] ?? 0));
		if (!is_array($template)) {
			return array(
				'ok' => false,
				'error' => 'template_missing',
				'message' => 'No template found for this queue item.',
				'context' => $context,
			);
		}

		$settings = vms_social_get_settings();
		$utm_enabled = !empty($settings['utm_enabled']);
		$rendered = vms_social_render_template_payload(
			(string) ($row['platform'] ?? ''),
			(string) ($template['body'] ?? ''),
			$context,
			$utm_enabled
		);

		return array(
			'ok' => true,
			'template' => $template,
			'context' => $context,
			'rendered' => $rendered,
		);
	}
}

if (!function_exists('vms_social_queue_decode_payload_snapshot')) {
	/**
	 * @param mixed $raw
	 * @return array<string,mixed>
	 */
	function vms_social_queue_decode_payload_snapshot($raw): array
	{
		$result = array(
			'ok' => false,
			'schema' => 'empty',
			'snapshot' => array(),
			'account_id' => 0,
			'allow_fallback_account' => false,
			'reason' => 'queue_snapshot_empty',
		);

		if ($raw === null) {
			return $result;
		}

		if (!is_string($raw)) {
			$result['schema'] = 'invalid';
			$result['reason'] = 'queue_snapshot_non_string';
			return $result;
		}

		$trimmed = trim($raw);
		if ($trimmed === '') {
			return $result;
		}

		if (substr($trimmed, 0, 1) !== '{') {
			$result['schema'] = 'invalid';
			$result['reason'] = 'queue_snapshot_non_object';
			return $result;
		}

		$decoded = json_decode($trimmed, true, 32);
		if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
			$result['schema'] = 'invalid';
			$result['reason'] = 'queue_snapshot_invalid_json';
			return $result;
		}

		$is_list = function_exists('array_is_list')
			? array_is_list($decoded)
			: (array_values($decoded) === $decoded);
		if ($is_list) {
			$result['schema'] = 'invalid';
			$result['reason'] = 'queue_snapshot_non_object';
			return $result;
		}

		$result['snapshot'] = $decoded;

		$has_queued_from = array_key_exists('queued_from', $decoded) && !is_array($decoded['queued_from']) && !is_object($decoded['queued_from']);
		$has_event_title = array_key_exists('event_title', $decoded) && !is_array($decoded['event_title']) && !is_object($decoded['event_title']);
		if ($has_queued_from && $has_event_title) {
			$result['ok'] = true;
			$result['schema'] = 'queued';
			$result['reason'] = '';

			if (array_key_exists('account_id', $decoded) && !is_array($decoded['account_id']) && !is_object($decoded['account_id'])) {
				$account_id = filter_var((string) $decoded['account_id'], FILTER_VALIDATE_INT);
				if ($account_id !== false && $account_id > 0) {
					$result['account_id'] = (int) $account_id;
				}
			}

			if ($result['account_id'] <= 0) {
				$result['reason'] = 'queue_snapshot_account_invalid';
			}

			return $result;
		}

		$has_rendered_preview = array_key_exists('caption', $decoded)
			&& array_key_exists('base_url', $decoded)
			&& array_key_exists('final_url', $decoded)
			&& array_key_exists('length', $decoded)
			&& array_key_exists('limit', $decoded)
			&& array_key_exists('needs_review', $decoded)
			&& array_key_exists('needs_review_reason', $decoded)
			&& !is_array($decoded['caption']) && !is_object($decoded['caption'])
			&& !is_array($decoded['base_url']) && !is_object($decoded['base_url'])
			&& !is_array($decoded['final_url']) && !is_object($decoded['final_url'])
			&& !is_array($decoded['length']) && !is_object($decoded['length'])
			&& !is_array($decoded['limit']) && !is_object($decoded['limit'])
			&& !is_array($decoded['needs_review']) && !is_object($decoded['needs_review'])
			&& !is_array($decoded['needs_review_reason']) && !is_object($decoded['needs_review_reason']);
		if ($has_rendered_preview) {
			$result['ok'] = true;
			$result['schema'] = 'rendered_preview';
			$result['allow_fallback_account'] = true;
			$result['reason'] = '';
			return $result;
		}

		$has_provider_result = isset($decoded['rendered'], $decoded['provider_payload'], $decoded['provider_result'])
			&& is_array($decoded['rendered'])
			&& is_array($decoded['provider_payload'])
			&& is_array($decoded['provider_result']);
		if ($has_provider_result) {
			$result['ok'] = true;
			$result['schema'] = 'provider_result';
			$result['reason'] = '';
			return $result;
		}

		$result['schema'] = 'unknown';
		$result['reason'] = 'queue_snapshot_unknown_schema';
		return $result;
	}
}

if (!function_exists('vms_social_queue_process_item')) {
	/**
	 * @param array<string,mixed> $row
	 */
	function vms_social_queue_process_item(array $row): void
	{
		$queue_id = (int) ($row['id'] ?? 0);
		if ($queue_id <= 0) {
			return;
		}

		if (!empty($row['platform_post_id'])) {
			vms_social_queue_update($queue_id, array(
				'status' => 'posted',
				'updated_by' => 0,
			));
			vms_social_audit_log('publish_ok', array('note' => 'already had platform_post_id; normalized to posted'), $queue_id, (string) ($row['platform'] ?? ''), 0);
			return;
		}

		$render = vms_social_queue_render_for_row($row);
		if (empty($render['ok'])) {
			vms_social_queue_update($queue_id, array(
				'status' => 'needs_review',
				'last_error_code' => (string) ($render['error'] ?? 'template_error'),
				'last_error_message' => (string) ($render['message'] ?? 'Template render failed.'),
				'updated_by' => 0,
			));
			vms_social_audit_log('publish_fail', $render, $queue_id, (string) ($row['platform'] ?? ''), 0);
			return;
		}

		$rendered = (array) ($render['rendered'] ?? array());
		if (!empty($rendered['needs_review'])) {
			vms_social_queue_update($queue_id, array(
				'status' => 'needs_review',
				'payload_snapshot_json' => wp_json_encode($rendered),
				'last_error_code' => 'caption_too_long',
				'last_error_message' => 'Rendered caption exceeds platform character limit.',
				'updated_by' => 0,
			));
			vms_social_audit_log('publish_fail', array('reason' => 'caption_too_long', 'length' => (int) ($rendered['length'] ?? 0), 'limit' => (int) ($rendered['limit'] ?? 0)), $queue_id, (string) ($row['platform'] ?? ''), 0);
			return;
		}

		$payload = array_merge(
			$row,
			array(
				'rendered_caption' => (string) ($rendered['caption'] ?? ''),
				'final_url' => (string) ($rendered['final_url'] ?? ''),
			)
		);

		$snapshot_state = vms_social_queue_decode_payload_snapshot($row['payload_snapshot_json'] ?? null);
		$mark_snapshot_for_review = static function (string $code, string $schema = '') use ($queue_id, $row): void {
			vms_social_queue_update($queue_id, array(
				'status' => 'needs_review',
				'last_error_code' => $code !== '' ? $code : 'queue_snapshot_invalid',
				'last_error_message' => 'Stored queue snapshot is invalid or unsupported; review the queued social post before retrying.',
				'updated_by' => 0,
			));
			vms_social_audit_log(
				'publish_fail',
				array(
					'reason' => $code !== '' ? $code : 'queue_snapshot_invalid',
					'snapshot_schema' => $schema,
				),
				$queue_id,
				(string) ($row['platform'] ?? ''),
				0
			);
		};

		$account_id = 0;
		if (!empty($snapshot_state['ok']) && (string) ($snapshot_state['schema'] ?? '') === 'queued') {
			$account_id = (int) ($snapshot_state['account_id'] ?? 0);
			if ($account_id <= 0) {
				$mark_snapshot_for_review((string) ($snapshot_state['reason'] ?? 'queue_snapshot_account_invalid'), 'queued');
				return;
			}
		} elseif (!empty($snapshot_state['ok']) && (string) ($snapshot_state['schema'] ?? '') === 'rendered_preview') {
			$map = vms_social_venue_map_for_platform((int) ($row['venue_id'] ?? 0), (string) ($row['platform'] ?? ''));
			if (is_array($map)) {
				$account_id = (int) ($map['account_id'] ?? 0);
			}
			if ($account_id <= 0) {
				$mark_snapshot_for_review('queue_snapshot_account_unavailable', 'rendered_preview');
				return;
			}
		} elseif (!empty($snapshot_state['ok']) && (string) ($snapshot_state['schema'] ?? '') === 'provider_result') {
			$mark_snapshot_for_review('queue_snapshot_provider_result', 'provider_result');
			return;
		} else {
			$mark_snapshot_for_review((string) ($snapshot_state['reason'] ?? 'queue_snapshot_invalid'), (string) ($snapshot_state['schema'] ?? 'invalid'));
			return;
		}

		$provider = bvmgr_social_get_provider((string) ($row['platform'] ?? ''));
		if (!($provider instanceof BVMGR_Social_Provider_Interface)) {
			vms_social_queue_update($queue_id, array(
				'status' => 'needs_review',
				'last_error_code' => 'provider_missing',
				'last_error_message' => 'No provider registered for this platform.',
				'updated_by' => 0,
			));
			vms_social_audit_log('publish_fail', array('reason' => 'provider_missing'), $queue_id, (string) ($row['platform'] ?? ''), 0);
			return;
		}

		$attempts = (int) ($row['attempts'] ?? 0) + 1;
		$max_attempts = (int) (vms_social_get_settings()['max_attempts'] ?? 5);

		try {
			$provider_payload = $provider->build_payload($payload, (array) ($render['context'] ?? array()));
			$result = $provider->publish($account_id, (string) ($row['destination_id'] ?? ''), $provider_payload);
			$platform_post_id = sanitize_text_field((string) ($result['platform_post_id'] ?? ''));
			if ($platform_post_id === '') {
				$platform_post_id = 'unknown-' . wp_generate_uuid4();
			}

			vms_social_queue_update($queue_id, array(
				'status' => 'posted',
				'attempts' => $attempts,
				'next_attempt_at_utc' => null,
				'platform_post_id' => $platform_post_id,
				'payload_snapshot_json' => wp_json_encode(array(
					'rendered' => $rendered,
					'provider_payload' => vms_social_sanitize_details($provider_payload),
					'provider_result' => vms_social_sanitize_details($result),
				)),
				'last_error_code' => '',
				'last_error_message' => '',
				'updated_by' => 0,
			));

			vms_social_audit_log('publish_ok', array(
				'platform_post_id' => $platform_post_id,
				'attempts' => $attempts,
			), $queue_id, (string) ($row['platform'] ?? ''), 0);
		} catch (Throwable $error) {
			$class = $provider->classify_error($error);
			$class = is_array($class) ? $class : array();
			$needs_review = !empty($class['needs_review']);
			$retryable = !empty($class['retryable']);
			$auth_expired = !empty($class['auth_expired']);
			$error_code = sanitize_key((string) ($class['error_code'] ?? 'publish_error'));
			$message = sanitize_text_field((string) ($class['message'] ?? $error->getMessage()));

			if ($auth_expired && $account_id > 0) {
				vms_social_account_set_auth_state($account_id, 'expired', array('last_error' => $message));
			}

			$status = 'failed';
			$next_attempt = null;
			if ($needs_review) {
				$status = 'needs_review';
			} elseif ($retryable && $attempts < $max_attempts) {
				$status = 'failed';
				$next_attempt = vms_social_next_attempt_utc($attempts);
			}

			vms_social_queue_update($queue_id, array(
				'status' => $status,
				'attempts' => $attempts,
				'next_attempt_at_utc' => $next_attempt,
				'last_error_code' => $error_code,
				'last_error_message' => $message,
				'updated_by' => 0,
			));

			vms_social_audit_log('publish_fail', array(
				'error_code' => $error_code,
				'message' => $message,
				'attempts' => $attempts,
				'next_attempt_at_utc' => $next_attempt,
				'status' => $status,
			), $queue_id, (string) ($row['platform'] ?? ''), 0);
		}
	}
}

if (!function_exists('vms_social_process_queue')) {
	/**
	 * @return array<string,mixed>
	 */
	function vms_social_process_queue(int $batch_size = 20): array
	{
		$summary = array(
			'locked' => false,
			'skipped' => false,
			'processed' => 0,
			'run_at' => vms_social_now_mysql_utc(),
		);

		if (!vms_social_runner_acquire_lock()) {
			$summary['locked'] = true;
			return $summary;
		}

		try {
			if (!vms_social_is_enabled() || vms_social_kill_switch_active()) {
				$summary['skipped'] = true;
				return $summary;
			}

			$due = vms_social_queue_due_items($batch_size);
			foreach ($due as $row) {
				$queue_id = (int) ($row['id'] ?? 0);
				if ($queue_id <= 0) {
					continue;
				}
				if (!vms_social_queue_claim($queue_id)) {
					continue;
				}
				$fresh = vms_social_queue_get($queue_id);
				if (!is_array($fresh)) {
					continue;
				}
				vms_social_queue_process_item($fresh);
				$summary['processed']++;
			}
		} finally {
			vms_social_runner_release_lock();
		}

		return $summary;
	}
}

$bvmgr_social_cron_hook = defined('BVMGR_SOCIAL_CRON_HOOK') ? (string) BVMGR_SOCIAL_CRON_HOOK : 'vms_social_process_queue';
add_action($bvmgr_social_cron_hook, function (): void {
	$summary = vms_social_process_queue(20);
	vms_social_audit_log('runner_tick', $summary, 0, '', 0);
});
