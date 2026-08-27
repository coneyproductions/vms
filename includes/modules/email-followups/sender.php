<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_email_followups_template_for')) {
	function vms_email_followups_template_for(string $email_key): array
	{
		$email_key = sanitize_key($email_key);
		$settings = vms_email_followups_settings();
		$templates = is_array($settings['templates'] ?? null) ? (array) $settings['templates'] : array();
		$defaults = vms_email_followups_default_templates();
		$template = is_array($templates[$email_key] ?? null) ? (array) $templates[$email_key] : (array) ($defaults[$email_key] ?? array());
		return array(
			'subject' => (string) ($template['subject'] ?? ''),
			'body' => (string) ($template['body'] ?? ''),
		);
	}
}

if (!function_exists('vms_email_followups_feedback_url')) {
	function vms_email_followups_feedback_url(int $event_plan_id, array $recipient = array(), string $email_key = ''): string
	{
		if ($event_plan_id <= 0 || !function_exists('bvmgr_feedback_survey_url')) {
			return '';
		}
		$source = $email_key !== '' ? 'email_followups_' . sanitize_key($email_key) : 'email_followups';
		return esc_url_raw(bvmgr_feedback_survey_url($event_plan_id, $recipient, $source));
	}
}

if (!function_exists('vms_email_followups_customer_first_name')) {
	function vms_email_followups_customer_first_name(array $recipient = array()): string
	{
		$name = trim((string) ($recipient['first_name'] ?? ''));
		if ($name === '') {
			$name = trim((string) ($recipient['name'] ?? ''));
		}
		if ($name === '' || strpos($name, '@') !== false) {
			return '';
		}

		$name = trim(html_entity_decode(wp_strip_all_tags($name), ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8'));
		if ($name === '') {
			return '';
		}

		$parts = preg_split('/\s+/', $name);
		$first = is_array($parts) && !empty($parts[0]) ? (string) $parts[0] : $name;
		$first = preg_replace('/[^\p{L}\p{M}\'’.-]+/u', '', $first);
		$first = trim((string) $first, " .,-_\t\n\r\0\x0B");

		return sanitize_text_field($first);
	}
}

if (!function_exists('vms_email_followups_customer_greeting')) {
	function vms_email_followups_customer_greeting(array $recipient = array()): string
	{
		$first_name = vms_email_followups_customer_first_name($recipient);
		/* translators: %s: human-readable value used in this message. */
		return $first_name !== '' ? sprintf(__('Hi %s,', 'backstage-venue-manager'), $first_name) : __('Hi there,', 'backstage-venue-manager');
	}
}

if (!function_exists('vms_email_followups_token_map')) {
	function vms_email_followups_token_map(array $context, array $recipient = array(), string $email_key = ''): array
	{
		$event_plan_id = absint($context['event_plan_id'] ?? 0);
		$feedback_url = vms_email_followups_feedback_url($event_plan_id, $recipient, $email_key);
		$settings = function_exists('vms_email_followups_settings') ? vms_email_followups_settings() : array();
		$customer_name = sanitize_text_field((string) ($recipient['name'] ?? ''));
		$customer_first_name = vms_email_followups_customer_first_name($recipient);
		$signature = trim(wp_strip_all_tags((string) ($settings['signature'] ?? '')));
		return array(
			'{event_name}' => (string) ($context['event_name'] ?? ''),
			'{event_date}' => (string) ($context['event_date_label'] ?? ($context['event_date'] ?? '')),
			'{start_time}' => (string) ($context['start_time_label'] ?? ''),
			'{end_time}' => (string) ($context['end_time_label'] ?? ''),
			'{gates_time}' => (string) ($context['gates_time_label'] ?? ''),
			'{venue_name}' => (string) ($context['venue_name'] ?? ''),
			'{event_url}' => (string) ($context['event_url'] ?? ''),
			'{feedback_url}' => (string) $feedback_url,
			'{site_url}' => (string) ($context['site_url'] ?? home_url('/')),
			'{site_name}' => (string) ($context['site_name'] ?? get_bloginfo('name')),
			'{signature}' => $signature,
			'{customer_name}' => $customer_name,
			'{customer_first_name}' => $customer_first_name,
			'{customer_greeting}' => vms_email_followups_customer_greeting($recipient),
		);
	}
}

if (!function_exists('vms_email_followups_render_message')) {
	function vms_email_followups_render_message(string $email_key, int $event_plan_id, array $recipient = array()): array
	{
		$template = vms_email_followups_template_for($email_key);
		$context = vms_email_followups_event_context($event_plan_id);
		$tokens = vms_email_followups_token_map($context, $recipient, $email_key);
		$subject = strtr((string) $template['subject'], $tokens);
		$body_text = strtr((string) $template['body'], $tokens);

		$body_html = wpautop(esc_html($body_text));
		$body_html = make_clickable($body_html);
		$body_html = '<div class="vms-email-followup-body">' . $body_html . '</div>';

		return array(
			'subject' => $subject,
			'body_text' => $body_text,
			'body_html' => $body_html,
			'context' => $context,
			'tokens' => $tokens,
		);
	}
}

if (!function_exists('vms_email_followups_headers')) {
	function vms_email_followups_headers(): array
	{
		$settings = vms_email_followups_settings();
		$headers = array('Content-Type: text/html; charset=UTF-8');
		$from_email = sanitize_email((string) ($settings['from_email'] ?? ''));
		$from_name = sanitize_text_field((string) ($settings['from_name'] ?? ''));
		$reply_to = sanitize_email((string) ($settings['reply_to_email'] ?? ''));
		if (is_email($from_email) && $from_name !== '') {
			$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
		}
		if (is_email($reply_to)) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}
		return $headers;
	}
}

if (!function_exists('vms_email_followups_send_test')) {
	function vms_email_followups_send_test(string $email_key, int $event_plan_id, string $to): array
	{
		$email_key = sanitize_key($email_key);
		$to = sanitize_email($to);
		if (!is_email($to)) {
			return array('ok' => false, 'message' => __('Invalid test recipient.', 'backstage-venue-manager'));
		}
		$rendered = vms_email_followups_render_message($email_key, $event_plan_id, array('email' => $to, 'name' => __('Test Recipient', 'backstage-venue-manager')));
		list($allowed, $reason) = vms_email_followups_context_allows_send((array) ($rendered['context'] ?? array()));
		if (!$allowed) {
			/* translators: %s: test blocked. */
			return array('ok' => false, 'message' => sprintf(__('Test blocked: %s', 'backstage-venue-manager'), $reason));
		}
		$ok = wp_mail($to, '[TEST] ' . (string) $rendered['subject'], (string) $rendered['body_html'], vms_email_followups_headers());
		vms_email_followups_log(array(
			'action' => 'test_send',
			'email_key' => $email_key,
			'event_plan_id' => $event_plan_id,
			'recipient' => $to,
			'status' => $ok ? 'sent' : 'error',
			'message' => $ok ? 'Test email sent.' : 'wp_mail returned false for test email.',
		));
		return array('ok' => (bool) $ok, 'message' => $ok ? __('Test email sent.', 'backstage-venue-manager') : __('WordPress email returned an error for this test.', 'backstage-venue-manager'));
	}
}


if (!function_exists('vms_email_followups_normalize_recipient_emails')) {
	function vms_email_followups_normalize_recipient_emails(array $emails): array
	{
		$normalized = array();
		foreach ($emails as $email) {
			$email = sanitize_email((string) $email);
			if (!is_email($email)) {
				continue;
			}
			$normalized[strtolower($email)] = $email;
		}
		return array_values($normalized);
	}
}

if (!function_exists('vms_email_followups_filter_recipients_by_email')) {
	function vms_email_followups_filter_recipients_by_email(array $recipients, array $emails): array
	{
		$emails = vms_email_followups_normalize_recipient_emails($emails);
		if (empty($emails)) {
			return $recipients;
		}
		$allowed = array_fill_keys(array_map('strtolower', $emails), true);
		$out = array();
		foreach ($recipients as $recipient) {
			if (!is_array($recipient)) {
				continue;
			}
			$email = strtolower(sanitize_email((string) ($recipient['email'] ?? '')));
			if ($email !== '' && isset($allowed[$email])) {
				$out[] = $recipient;
			}
		}
		return $out;
	}
}

if (!function_exists('vms_email_followups_send_event_email')) {
	function vms_email_followups_send_event_email(string $email_key, int $event_plan_id, string $mode = 'manual', array $args = array()): array
	{
		$email_key = sanitize_key($email_key);
		$mode = sanitize_key($mode);
		$definitions = vms_email_followups_template_definitions();
		if (!isset($definitions[$email_key])) {
			return array('ok' => false, 'sent' => 0, 'errors' => 0, 'skipped' => 0, 'message' => 'unknown_email_key');
		}

		$settings = vms_email_followups_settings();
		$enabled = is_array($settings['templates_enabled'] ?? null) ? (array) $settings['templates_enabled'] : array();
		if (empty($enabled[$email_key])) {
			return array('ok' => false, 'sent' => 0, 'errors' => 0, 'skipped' => 0, 'message' => 'template_disabled');
		}

		$context = vms_email_followups_event_context($event_plan_id);
		list($allowed, $reason) = vms_email_followups_context_allows_send($context);
		if (!$allowed) {
			vms_email_followups_log(array(
				'action' => $mode,
				'email_key' => $email_key,
				'event_plan_id' => $event_plan_id,
				'status' => 'skipped',
				'message' => $reason,
			));
			return array('ok' => false, 'sent' => 0, 'errors' => 0, 'skipped' => 1, 'message' => $reason);
		}

		$recipient_result = vms_email_followups_event_recipients($event_plan_id);
		$recipients = (array) ($recipient_result['recipients'] ?? array());
		$requested_emails = isset($args['recipient_emails']) && is_array($args['recipient_emails']) ? vms_email_followups_normalize_recipient_emails((array) $args['recipient_emails']) : array();
		if (!empty($requested_emails)) {
			$recipients = vms_email_followups_filter_recipients_by_email($recipients, $requested_emails);
		}
		if (empty($recipients)) {
			vms_email_followups_log(array(
				'action' => $mode,
				'email_key' => $email_key,
				'event_plan_id' => $event_plan_id,
				'status' => 'skipped',
				'message' => 'no_eligible_recipients',
			));
			return array('ok' => false, 'sent' => 0, 'errors' => 0, 'skipped' => 1, 'message' => 'no_eligible_recipients');
		}

		$total_selected = count($recipients);
		$limit = isset($args['limit']) ? max(0, (int) $args['limit']) : 0;
		$remaining_emails = array();
		if ($limit > 0 && count($recipients) > $limit) {
			$remaining = array_slice($recipients, $limit);
			foreach ($remaining as $recipient) {
				if (is_array($recipient) && !empty($recipient['email'])) {
					$remaining_emails[] = (string) $recipient['email'];
				}
			}
			$recipients = array_slice($recipients, 0, $limit);
		}

		$sent = 0;
		$errors = 0;
		$skipped = 0;
		foreach ($recipients as $recipient) {
			$email = sanitize_email((string) ($recipient['email'] ?? ''));
			if (!is_email($email)) {
				$skipped++;
				continue;
			}
			if (vms_email_followups_was_sent($event_plan_id, $email_key, $email)) {
				$skipped++;
				vms_email_followups_log(array(
					'action' => $mode,
					'email_key' => $email_key,
					'event_plan_id' => $event_plan_id,
					'recipient' => $email,
					'status' => 'skipped',
					'message' => 'duplicate_guard_prevented_resend',
				));
				continue;
			}

			$rendered = vms_email_followups_render_message($email_key, $event_plan_id, $recipient);
			$sync = vms_email_followups_maybe_sync_mailpoet_subscriber($email, (string) ($recipient['name'] ?? ''), array('ticket-buyer', 'vms-event-buyer'));
			$ok = wp_mail($email, (string) $rendered['subject'], (string) $rendered['body_html'], vms_email_followups_headers());
			vms_email_followups_log(array(
				'action' => $mode,
				'email_key' => $email_key,
				'event_plan_id' => $event_plan_id,
				'recipient' => $email,
				'status' => $ok ? 'sent' : 'error',
				'message' => $ok ? 'Email sent.' : 'wp_mail returned false.',
				'meta' => array(
					'qty' => (int) ($recipient['qty'] ?? 0),
					'mailpoet_sync' => $sync,
					'feedback_link' => $email_key === 'post_event' && !empty($rendered['tokens']['{feedback_url}']),
				),
			));
			if ($ok) {
				$sent++;
			} else {
				$errors++;
			}
		}

		return array(
			'ok' => $errors === 0 && $sent > 0,
			'sent' => $sent,
			'errors' => $errors,
			'skipped' => $skipped,
			'message' => 'complete',
			'attempted' => count($recipients),
			'total_selected' => $total_selected,
			'remaining' => count($remaining_emails),
			'remaining_emails' => vms_email_followups_normalize_recipient_emails($remaining_emails),
		);
	}
}
