<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_notify_log_table_name')) {
	function bvmgr_notify_log_table_name(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'vms_notify_log';
	}
}

if (!function_exists('bvmgr_notify_db_schema_option_key')) {
	function bvmgr_notify_db_schema_option_key(): string
	{
		return 'vms_notify_db_schema_version';
	}
}

if (!function_exists('bvmgr_notify_db_schema_target')) {
	function bvmgr_notify_db_schema_target(): string
	{
		return '1.0.0';
	}
}

if (!function_exists('bvmgr_notify_digest_enabled_option_key')) {
	function bvmgr_notify_digest_enabled_option_key(): string
	{
		return 'vms_notify_digest_enabled';
	}
}

if (!function_exists('bvmgr_notify_digest_time_option_key')) {
	function bvmgr_notify_digest_time_option_key(): string
	{
		return 'vms_notify_digest_time';
	}
}

if (!function_exists('bvmgr_notify_digest_window_option_key')) {
	function bvmgr_notify_digest_window_option_key(): string
	{
		return 'vms_notify_digest_window';
	}
}

if (!function_exists('bvmgr_notify_digest_last_run_option_key')) {
	function bvmgr_notify_digest_last_run_option_key(): string
	{
		return 'vms_notify_digest_last_run_day';
	}
}

if (!function_exists('bvmgr_notify_valid_digest_window')) {
	function bvmgr_notify_valid_digest_window(string $window): string
	{
		$window = sanitize_key($window);
		return in_array($window, array('today', 'next3', 'next7'), true) ? $window : 'next3';
	}
}

if (!function_exists('bvmgr_notify_valid_channel')) {
	function bvmgr_notify_valid_channel(string $channel): string
	{
		$channel = sanitize_key($channel);
		return in_array($channel, array('email', 'sms', 'whatsapp'), true) ? $channel : '';
	}
}

if (!function_exists('bvmgr_notify_sanitize_template_key')) {
	function bvmgr_notify_sanitize_template_key(string $template_key): string
	{
		$template_key = strtolower(trim($template_key));
		return preg_replace('/[^a-z0-9._-]/', '', $template_key) ?? '';
	}
}

if (!function_exists('bvmgr_notify_maybe_upgrade_schema')) {
	function bvmgr_notify_maybe_upgrade_schema(): void
	{
		$current = (string) get_option(bvmgr_notify_db_schema_option_key(), '');
		$target = bvmgr_notify_db_schema_target();
		if ($current === $target) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		$table = bvmgr_notify_log_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			source VARCHAR(80) NOT NULL,
			event_key VARCHAR(80) NOT NULL,
			recipient_user_id BIGINT(20) UNSIGNED NULL,
			recipient_address VARCHAR(255) NULL,
			channel VARCHAR(30) NOT NULL,
			locale VARCHAR(20) NULL,
			template_key VARCHAR(120) NOT NULL,
			payload_json LONGTEXT NULL,
			provider VARCHAR(60) NOT NULL,
			provider_message_id VARCHAR(120) NULL,
			status VARCHAR(30) NOT NULL,
			error_message TEXT NULL,
			PRIMARY KEY (id),
			KEY created_at (created_at),
			KEY recipient_user_id (recipient_user_id),
			KEY event_key (event_key),
			KEY status (status)
		) {$charset_collate};";

		dbDelta($sql);
		update_option(bvmgr_notify_db_schema_option_key(), $target, false);
	}
}
add_action('plugins_loaded', 'bvmgr_notify_maybe_upgrade_schema', 9);

if (!function_exists('bvmgr_notify_register_default_options')) {
	function bvmgr_notify_register_default_options(): void
	{
		add_option(bvmgr_notify_digest_enabled_option_key(), 0);
		add_option(bvmgr_notify_digest_time_option_key(), '08:00');
		add_option(bvmgr_notify_digest_window_option_key(), 'next3');
	}
}
add_action('admin_init', 'bvmgr_notify_register_default_options', 1);

if (!function_exists('bvmgr_notify_seed_user_defaults')) {
	function bvmgr_notify_seed_user_defaults(int $user_id): void
	{
		$user_id = absint($user_id);
		if ($user_id <= 0) {
			return;
		}
		if (get_user_meta($user_id, 'vms_notify_email_enabled', true) === '') {
			update_user_meta($user_id, 'vms_notify_email_enabled', '1');
		}
		if (get_user_meta($user_id, 'vms_notify_sms_enabled', true) === '') {
			update_user_meta($user_id, 'vms_notify_sms_enabled', '0');
		}
		if (get_user_meta($user_id, 'vms_notify_whatsapp_enabled', true) === '') {
			update_user_meta($user_id, 'vms_notify_whatsapp_enabled', '0');
		}
		if (get_user_meta($user_id, 'vms_locale_preference', true) === '') {
			update_user_meta($user_id, 'vms_locale_preference', '');
		}
	}
}
add_action('user_register', 'bvmgr_notify_seed_user_defaults');

if (!function_exists('bvmgr_notify_user_locale')) {
	function bvmgr_notify_user_locale(int $user_id): string
	{
		$user_id = absint($user_id);
		$preferred = sanitize_text_field((string) get_user_meta($user_id, 'vms_locale_preference', true));
		if ($preferred !== '') {
			return $preferred;
		}
		$site_locale = (string) get_locale();
		return $site_locale !== '' ? $site_locale : 'en_US';
	}
}

if (!function_exists('bvmgr_notify_user_channel_enabled')) {
	function bvmgr_notify_user_channel_enabled(int $user_id, string $channel): bool
	{
		$user_id = absint($user_id);
		$channel = bvmgr_notify_valid_channel($channel);
		if ($user_id <= 0 || $channel === '') {
			return false;
		}

		if ($channel === 'email') {
			$raw = get_user_meta($user_id, 'vms_notify_email_enabled', true);
			return $raw === '' ? true : !empty($raw);
		}
		if ($channel === 'sms') {
			return !empty(get_user_meta($user_id, 'vms_notify_sms_enabled', true));
		}
		if ($channel === 'whatsapp') {
			return !empty(get_user_meta($user_id, 'vms_notify_whatsapp_enabled', true));
		}
		return false;
	}
}

if (!function_exists('bvmgr_notify_user_phone_e164')) {
	function bvmgr_notify_user_phone_e164(int $user_id): string
	{
		$user_id = absint($user_id);
		if ($user_id <= 0) {
			return '';
		}
		$phone = trim((string) get_user_meta($user_id, 'vms_phone_e164', true));
		if (!preg_match('/^\+[1-9][0-9]{7,14}$/', $phone)) {
			return '';
		}
		return $phone;
	}
}

if (!function_exists('bvmgr_notify_default_channels_for_user')) {
	/** @return string[] */
	function bvmgr_notify_default_channels_for_user(int $user_id): array
	{
		$channels = array();
		foreach (array('email', 'sms', 'whatsapp') as $channel) {
			if (bvmgr_notify_user_channel_enabled($user_id, $channel)) {
				$channels[] = $channel;
			}
		}
		return $channels;
	}
}

if (!function_exists('bvmgr_notify_channel_provider_key')) {
	function bvmgr_notify_channel_provider_key(string $channel): string
	{
		$channel = bvmgr_notify_valid_channel($channel);
		if ($channel === 'email') {
			return 'core_email';
		}
		if ($channel === 'sms') {
			return sanitize_key((string) apply_filters('vms_notify_channel_provider_sms', ''));
		}
		if ($channel === 'whatsapp') {
			return sanitize_key((string) apply_filters('vms_notify_channel_provider_whatsapp', ''));
		}
		return '';
	}
}

if (!function_exists('bvmgr_notify_get_providers')) {
	/**
	 * @return array<string,callable>
	 */
	function bvmgr_notify_get_providers(): array
	{
		$providers = array(
			'core_email' => 'bvmgr_notify_provider_core_email_send',
		);
		$providers = (array) apply_filters('vms_notify_providers', $providers);

		$normalized = array();
		foreach ($providers as $key => $callback) {
			$provider_key = sanitize_key((string) $key);
			if ($provider_key === '' || !is_callable($callback)) {
				continue;
			}
			$normalized[$provider_key] = $callback;
		}
		return $normalized;
	}
}

if (!function_exists('bvmgr_notify_provider_core_email_send')) {
	/**
	 * @param array<string,mixed> $message
	 * @return array<string,mixed>
	 */
	function bvmgr_notify_provider_core_email_send(array $message): array
	{
		$to = sanitize_email((string) ($message['to'] ?? ''));
		if (!is_email($to)) {
			return array(
				'success' => false,
				'error_message' => __('Recipient email is invalid.', 'backstage-venue-manager'),
			);
		}

		$subject = sanitize_text_field((string) ($message['subject'] ?? ''));
		if ($subject === '') {
			$subject = __('Notification', 'backstage-venue-manager');
		}

		$body_text = trim((string) ($message['body_text'] ?? ''));
		$body_html = trim((string) ($message['body_html'] ?? ''));
		if ($body_text === '' && $body_html === '') {
			return array(
				'success' => false,
				'error_message' => __('Message body is empty.', 'backstage-venue-manager'),
			);
		}

		$sent = false;
		if ($body_html !== '') {
			$headers = array('Content-Type: text/html; charset=UTF-8');
			$sent = (bool) wp_mail($to, $subject, wp_kses_post($body_html), $headers);
		} else {
			$sent = (bool) wp_mail($to, $subject, $body_text);
		}

		return array(
			'success' => $sent,
			'provider_message_id' => null,
			'error_message' => $sent ? '' : __('wp_mail reported failure.', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('bvmgr_notify_redact_payload_for_log')) {
	/**
	 * @param mixed $value
	 * @return mixed
	 */
	function bvmgr_notify_redact_payload_for_log($value)
	{
		if (is_array($value)) {
			$out = array();
			foreach ($value as $k => $v) {
				$key = sanitize_key((string) $k);
				if ($key !== '' && preg_match('/(token|secret|password|auth|api_key)/i', $key)) {
					$out[$key] = '[redacted]';
					continue;
				}
				$out[$key !== '' ? $key : $k] = bvmgr_notify_redact_payload_for_log($v);
			}
			return $out;
		}
		if (is_object($value)) {
			return bvmgr_notify_redact_payload_for_log((array) $value);
		}
		if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
			return $value;
		}

		$text = sanitize_textarea_field((string) $value);
		if (function_exists('mb_substr')) {
			return mb_substr($text, 0, 2000);
		}
		return substr($text, 0, 2000);
	}
}

if (!function_exists('bvmgr_notify_insert_log')) {
	/**
	 * created_at is stored in UTC (WordPress current_time with GMT=true).
	 *
	 * @param array<string,mixed> $entry
	 */
	function bvmgr_notify_insert_log(array $entry): void
	{
		global $wpdb;
		$table = bvmgr_notify_log_table_name();
		if ($table === '') {
			return;
		}

		$status = sanitize_key((string) ($entry['status'] ?? 'queued'));
		if (!in_array($status, array('queued', 'sent', 'failed', 'skipped'), true)) {
			$status = 'failed';
		}
		$channel = sanitize_key((string) ($entry['channel'] ?? 'email'));
		if (!in_array($channel, array('email', 'sms', 'whatsapp'), true)) {
			$channel = 'email';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Notification audit writes persist one normalized row in the plugin-owned log table through wpdb::insert(); no core API owns this repository.
		$ok = $wpdb->insert(
			$table,
			array(
				'created_at' => current_time('mysql', true),
				'source' => sanitize_key((string) ($entry['source'] ?? 'vms_core')),
				'event_key' => sanitize_key((string) ($entry['event_key'] ?? 'unknown')),
				'recipient_user_id' => !empty($entry['recipient_user_id']) ? absint($entry['recipient_user_id']) : null,
				'recipient_address' => sanitize_text_field((string) ($entry['recipient_address'] ?? '')),
				'channel' => $channel,
				'locale' => sanitize_text_field((string) ($entry['locale'] ?? '')),
				'template_key' => bvmgr_notify_sanitize_template_key((string) ($entry['template_key'] ?? 'unknown')),
				'payload_json' => wp_json_encode(bvmgr_notify_redact_payload_for_log($entry['payload'] ?? array())),
				'provider' => sanitize_key((string) ($entry['provider'] ?? 'unknown')),
				'provider_message_id' => sanitize_text_field((string) ($entry['provider_message_id'] ?? '')),
				'status' => $status,
				'error_message' => sanitize_textarea_field((string) ($entry['error_message'] ?? '')),
			),
			array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
		);
		if ($ok !== 1) {
			$event_key = substr(sanitize_key((string) ($entry['event_key'] ?? 'unknown')), 0, 80);
			$recorded = function_exists('bvmgr_record_operational_issue') && bvmgr_record_operational_issue(
				'notification_log_insert_failed',
				array(
					'service' => 'notifications',
					'operation' => 'insert_log',
					'status' => 'failed',
					'event_key' => $event_key,
				)
			);
			if (!$recorded && function_exists('error_log')) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Preserve one minimal fallback when both the notification-table insert and the bounded option-backed operational adapter are unavailable; payload is limited to a fixed event and sanitized bounded event key.
				error_log('[BVM operational] event=notification_log_insert_failed event_key=' . $event_key);
			}
		}
	}
}

if (!function_exists('bvmgr_notify_locale_is_spanish')) {
	function bvmgr_notify_locale_is_spanish(string $locale): bool
	{
		$locale = strtolower(trim($locale));
		return strpos($locale, 'es') === 0;
	}
}

if (!function_exists('bvmgr_notify_default_template_payload')) {
	/**
	 * @param array<string,mixed> $vars
	 * @return array<string,string>|null
	 */
	function bvmgr_notify_default_template_payload(string $template_key, string $locale, array $vars): ?array
	{
		$is_es = bvmgr_notify_locale_is_spanish($locale);
		$title = trim((string) ($vars['task_title'] ?? ''));
		$due = trim((string) ($vars['due_datetime'] ?? ''));
		$event = trim((string) ($vars['event_context'] ?? ''));
		$link = esc_url_raw((string) ($vars['task_url'] ?? ''));

		$event_line = $event !== '' ? ($is_es ? ('Evento: ' . $event) : ('Event: ' . $event)) : ($is_es ? 'Sin evento vinculado' : 'Not linked to an event');
		$due_line = $due !== '' ? ($is_es ? ('Vence: ' . $due) : ('Due: ' . $due)) : ($is_es ? 'Sin fecha límite' : 'No due date');

		if ($template_key === 'staff_tasks.task_assigned') {
			$subject = $is_es ? ('Nueva tarea asignada: ' . $title) : ('New task assigned: ' . $title);
			$body = ($is_es ? "Se te asignó una tarea.\n" : "A task was assigned to you.\n")
				. ($title !== '' ? ($is_es ? ('Tarea: ' . $title . "\n") : ('Task: ' . $title . "\n")) : '')
				. $event_line . "\n"
				. $due_line . "\n"
				. ($link !== '' ? (($is_es ? 'Abrir tareas: ' : 'Open tasks: ') . $link) : '');
			return array(
				'subject' => $subject,
				'body_text' => $body,
				'body_html' => nl2br(esc_html($body)),
				'source' => 'vms_staff_tasks',
			);
		}

		if ($template_key === 'staff_tasks.task_due_soon') {
			$subject = $is_es ? ('Tarea próxima a vencer: ' . $title) : ('Task due soon: ' . $title);
			$body = ($is_es ? "Una tarea vencerá pronto.\n" : "A task is due soon.\n")
				. ($title !== '' ? ($is_es ? ('Tarea: ' . $title . "\n") : ('Task: ' . $title . "\n")) : '')
				. $event_line . "\n"
				. $due_line . "\n"
				. ($link !== '' ? (($is_es ? 'Abrir tareas: ' : 'Open tasks: ') . $link) : '');
			return array(
				'subject' => $subject,
				'body_text' => $body,
				'body_html' => nl2br(esc_html($body)),
				'source' => 'vms_staff_tasks',
			);
		}

		if ($template_key === 'staff_tasks.task_overdue') {
			$subject = $is_es ? ('Tarea vencida: ' . $title) : ('Task overdue: ' . $title);
			$body = ($is_es ? "Una tarea está vencida.\n" : "A task is overdue.\n")
				. ($title !== '' ? ($is_es ? ('Tarea: ' . $title . "\n") : ('Task: ' . $title . "\n")) : '')
				. $event_line . "\n"
				. $due_line . "\n"
				. ($link !== '' ? (($is_es ? 'Abrir tareas: ' : 'Open tasks: ') . $link) : '');
			return array(
				'subject' => $subject,
				'body_text' => $body,
				'body_html' => nl2br(esc_html($body)),
				'source' => 'vms_staff_tasks',
			);
		}

		if ($template_key === 'staff_tasks.task_digest_daily') {
			$count = absint($vars['task_count'] ?? 0);
			$tasks = isset($vars['tasks']) && is_array($vars['tasks']) ? $vars['tasks'] : array();
			$subject = $is_es
				? sprintf('Resumen diario de tareas (%d)', $count)
				: sprintf('Daily task digest (%d)', $count);

			$lines = array();
			$lines[] = $is_es ? 'Tareas abiertas:' : 'Open tasks:';
			foreach (array_slice($tasks, 0, 20) as $task_row) {
				$task_title = trim((string) ($task_row['task_title'] ?? ''));
				$task_due = trim((string) ($task_row['due_datetime'] ?? ''));
				$task_event = trim((string) ($task_row['event_context'] ?? ''));
				$line = '- ' . ($task_title !== '' ? $task_title : ($is_es ? 'Tarea sin título' : 'Untitled task'));
				if ($task_due !== '') {
					$line .= ' | ' . ($is_es ? 'Vence' : 'Due') . ': ' . $task_due;
				}
				if ($task_event !== '') {
					$line .= ' | ' . ($is_es ? 'Evento' : 'Event') . ': ' . $task_event;
				}
				$lines[] = $line;
			}
			if ($link !== '') {
				$lines[] = '';
				$lines[] = ($is_es ? 'Abrir tareas: ' : 'Open tasks: ') . $link;
			}
			$body = implode("\n", $lines);
			return array(
				'subject' => $subject,
				'body_text' => $body,
				'body_html' => nl2br(esc_html($body)),
				'source' => 'vms_staff_tasks',
			);
		}

		return null;
	}
}

if (!function_exists('bvmgr_notify_resolve_template_payload')) {
	/**
	 * @param array<string,mixed> $vars
	 * @return array<string,string>|WP_Error
	 */
	function bvmgr_notify_resolve_template_payload(string $event_key, string $template_key, string $locale, array $vars, int $user_id)
	{
		$custom = apply_filters('vms_notify_template_payload', null, $event_key, $template_key, $locale, $vars, $user_id);
		if (is_array($custom)) {
			return array(
				'subject' => sanitize_text_field((string) ($custom['subject'] ?? '')),
				'body_text' => sanitize_textarea_field((string) ($custom['body_text'] ?? '')),
				'body_html' => wp_kses_post((string) ($custom['body_html'] ?? '')),
				'source' => sanitize_key((string) ($custom['source'] ?? 'vms_module')),
			);
		}

		$fallback = bvmgr_notify_default_template_payload($template_key, $locale, $vars);
		if (is_array($fallback)) {
			return $fallback;
		}

		return new WP_Error('vms_notify_template_missing', __('Notification template is missing.', 'backstage-venue-manager'));
	}
}

if (!function_exists('bvmgr_notify_get_recipient_for_channel')) {
	function bvmgr_notify_get_recipient_for_channel(int $user_id, string $channel): string
	{
		$channel = bvmgr_notify_valid_channel($channel);
		if ($channel === '') {
			return '';
		}
		if ($channel === 'email') {
			$user = get_userdata($user_id);
			$email = $user && isset($user->user_email) ? sanitize_email((string) $user->user_email) : '';
			return is_email($email) ? $email : '';
		}
		return bvmgr_notify_user_phone_e164($user_id);
	}
}

if (!function_exists('bvmgr_notify_user')) {
	/**
	 * @param array<string,mixed> $vars
	 * @param string[] $channels
	 * @return array<string,mixed>
	 */
	function bvmgr_notify_user(int $user_id, string $event_key, string $template_key, array $vars, array $channels = array()): array
	{
		$user_id = absint($user_id);
		$event_key = sanitize_key($event_key);
		$template_key = bvmgr_notify_sanitize_template_key($template_key);
		if ($user_id <= 0 || $event_key === '' || $template_key === '') {
			return array(
				'ok' => false,
				'sent' => 0,
				'failed' => 0,
				'skipped' => 1,
				'results' => array(),
			);
		}

		$locale = bvmgr_notify_user_locale($user_id);
		$payload = bvmgr_notify_resolve_template_payload($event_key, $template_key, $locale, $vars, $user_id);
		if (is_wp_error($payload)) {
			bvmgr_notify_insert_log(array(
				'source' => 'vms_core',
				'event_key' => $event_key,
				'recipient_user_id' => $user_id,
				'recipient_address' => '',
				'channel' => 'email',
				'locale' => $locale,
				'template_key' => $template_key,
				'payload' => $vars,
				'provider' => 'core_email',
				'status' => 'failed',
				'error_message' => $payload->get_error_message(),
			));
			return array(
				'ok' => false,
				'sent' => 0,
				'failed' => 1,
				'skipped' => 0,
				'results' => array(),
			);
		}

		$selected_channels = array();
		if (!empty($channels)) {
			foreach ($channels as $channel) {
				$key = bvmgr_notify_valid_channel((string) $channel);
				if ($key !== '') {
					$selected_channels[$key] = $key;
				}
			}
		} else {
			foreach (bvmgr_notify_default_channels_for_user($user_id) as $channel) {
				$selected_channels[$channel] = $channel;
			}
		}
		$selected_channels = array_values($selected_channels);
		if (empty($selected_channels)) {
			bvmgr_notify_insert_log(array(
				'source' => (string) ($payload['source'] ?? 'vms_module'),
				'event_key' => $event_key,
				'recipient_user_id' => $user_id,
				'recipient_address' => '',
				'channel' => 'email',
				'locale' => $locale,
				'template_key' => $template_key,
				'payload' => $vars,
				'provider' => 'core_email',
				'status' => 'skipped',
				'error_message' => __('All user notification channels are disabled.', 'backstage-venue-manager'),
			));
			return array(
				'ok' => false,
				'sent' => 0,
				'failed' => 0,
				'skipped' => 1,
				'results' => array(),
			);
		}

		$providers = bvmgr_notify_get_providers();
		$results = array();
		$sent = 0;
		$failed = 0;
		$skipped = 0;

		foreach ($selected_channels as $channel) {
			$provider_key = bvmgr_notify_channel_provider_key($channel);
			$recipient = bvmgr_notify_get_recipient_for_channel($user_id, $channel);

			if ($recipient === '') {
				$skipped++;
				$error = __('Recipient address is missing for this channel.', 'backstage-venue-manager');
				bvmgr_notify_insert_log(array(
					'source' => (string) ($payload['source'] ?? 'vms_module'),
					'event_key' => $event_key,
					'recipient_user_id' => $user_id,
					'recipient_address' => '',
					'channel' => $channel,
					'locale' => $locale,
					'template_key' => $template_key,
					'payload' => $vars,
					'provider' => $provider_key !== '' ? $provider_key : 'unavailable',
					'status' => 'skipped',
					'error_message' => $error,
				));
				$results[] = array('channel' => $channel, 'status' => 'skipped', 'error' => $error);
				continue;
			}

			if ($provider_key === '' || !isset($providers[$provider_key])) {
				$skipped++;
				$error = __('Channel provider is not installed.', 'backstage-venue-manager');
				bvmgr_notify_insert_log(array(
					'source' => (string) ($payload['source'] ?? 'vms_module'),
					'event_key' => $event_key,
					'recipient_user_id' => $user_id,
					'recipient_address' => $recipient,
					'channel' => $channel,
					'locale' => $locale,
					'template_key' => $template_key,
					'payload' => $vars,
					'provider' => $provider_key !== '' ? $provider_key : 'unavailable',
					'status' => 'skipped',
					'error_message' => $error,
				));
				$results[] = array('channel' => $channel, 'status' => 'skipped', 'error' => $error);
				continue;
			}

			$message = array(
				'channel' => $channel,
				'to' => $recipient,
				'subject' => (string) ($payload['subject'] ?? ''),
				'body_text' => (string) ($payload['body_text'] ?? ''),
				'body_html' => (string) ($payload['body_html'] ?? ''),
				'locale' => $locale,
				'meta' => array(
					'source' => (string) ($payload['source'] ?? 'vms_module'),
					'event_key' => $event_key,
					'template_key' => $template_key,
					'user_id' => $user_id,
				),
			);
			$provider_result = call_user_func($providers[$provider_key], $message);
			$provider_ok = is_array($provider_result) && !empty($provider_result['success']);
			$provider_msg_id = is_array($provider_result) ? sanitize_text_field((string) ($provider_result['provider_message_id'] ?? '')) : '';
			$error_message = is_array($provider_result) ? sanitize_textarea_field((string) ($provider_result['error_message'] ?? '')) : __('Provider returned an invalid response.', 'backstage-venue-manager');

			if ($provider_ok) {
				$sent++;
				bvmgr_notify_insert_log(array(
					'source' => (string) ($payload['source'] ?? 'vms_module'),
					'event_key' => $event_key,
					'recipient_user_id' => $user_id,
					'recipient_address' => $recipient,
					'channel' => $channel,
					'locale' => $locale,
					'template_key' => $template_key,
					'payload' => $vars,
					'provider' => $provider_key,
					'provider_message_id' => $provider_msg_id,
					'status' => 'sent',
					'error_message' => '',
				));
				$results[] = array('channel' => $channel, 'status' => 'sent', 'provider' => $provider_key);
			} else {
				$failed++;
				bvmgr_notify_insert_log(array(
					'source' => (string) ($payload['source'] ?? 'vms_module'),
					'event_key' => $event_key,
					'recipient_user_id' => $user_id,
					'recipient_address' => $recipient,
					'channel' => $channel,
					'locale' => $locale,
					'template_key' => $template_key,
					'payload' => $vars,
					'provider' => $provider_key,
					'provider_message_id' => $provider_msg_id,
					'status' => 'failed',
					'error_message' => $error_message !== '' ? $error_message : __('Provider send failed.', 'backstage-venue-manager'),
				));
				$results[] = array('channel' => $channel, 'status' => 'failed', 'provider' => $provider_key, 'error' => $error_message);
			}
		}

		return array(
			'ok' => $failed === 0,
			'sent' => $sent,
			'failed' => $failed,
			'skipped' => $skipped,
			'results' => $results,
		);
	}
}

if (!function_exists('bvmgr_notify_recent_logs')) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function bvmgr_notify_recent_logs(int $limit = 10): array
	{
		global $wpdb;
		$table = bvmgr_notify_log_table_name();
		if ($table === '') {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Notification schema readiness must observe the newly created plugin-owned log table in the current request, so this prepared probe is intentionally uncached.
		$exists = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
		if ($exists !== $table) {
			return array();
		}
		$limit = max(1, min(100, absint($limit)));
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Notification history must read request-fresh audit rows from the plugin-owned log table after sends, failures, or skips.
		$rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY id DESC LIMIT %d', $table, $limit), ARRAY_A);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('bvmgr_notify_run_digest_tick')) {
	function bvmgr_notify_run_digest_tick(): void
	{
		$enabled = !empty(get_option(bvmgr_notify_digest_enabled_option_key(), 0));
		if (!$enabled) {
			return;
		}

		$now_local = current_time('mysql', false);
		$today = wp_date('Y-m-d', time(), wp_timezone());
		$time = sanitize_text_field((string) get_option(bvmgr_notify_digest_time_option_key(), '08:00'));
		if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
			$time = '08:00';
		}
		$scheduled_at = $today . ' ' . $time . ':00';
		if (strtotime($now_local) < strtotime($scheduled_at)) {
			return;
		}

		$last_run = sanitize_text_field((string) get_option(bvmgr_notify_digest_last_run_option_key(), ''));
		if ($last_run === $today) {
			return;
		}

		$window = bvmgr_notify_valid_digest_window((string) get_option(bvmgr_notify_digest_window_option_key(), 'next3'));
		do_action('vms_notify_digest_tick', array(
			'run_day' => $today,
			'run_at_local' => $now_local,
			'window' => $window,
		));
		update_option(bvmgr_notify_digest_last_run_option_key(), $today, false);
	}
}
add_action('vms_notify_digest_tick_cron', 'bvmgr_notify_run_digest_tick');

if (!function_exists('bvmgr_notify_ensure_digest_cron')) {
	function bvmgr_notify_ensure_digest_cron(): void
	{
		if (function_exists('bvmgr_should_run_runtime_maintenance') && !bvmgr_should_run_runtime_maintenance()) {
			return;
		}
		if (!wp_next_scheduled('vms_notify_digest_tick_cron')) {
			wp_schedule_event(time() + 120, 'hourly', 'vms_notify_digest_tick_cron');
		}
	}
}
add_action('init', 'bvmgr_notify_ensure_digest_cron', 30);
