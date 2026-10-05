<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_pass_outreach_public_error_codes')) {
	function vms_pass_outreach_public_error_codes(): array
	{
		return array(
			'invalid_invite_token',
			'invite_expired',
			'invite_revoked',
			'recipient_already_claimed',
			'campaign_missing_batch',
			'invite_token_not_ready',
			'invite_token_mismatch',
		);
	}
}

if (!function_exists('vms_pass_outreach_allowed_recipient_statuses')) {
	function vms_pass_outreach_allowed_recipient_statuses(): array
	{
		return array('draft', 'ready', 'sent', 'claimed', 'partially_used', 'used', 'expired', 'revoked');
	}
}

if (!function_exists('vms_pass_outreach_recipient_status_labels')) {
	function vms_pass_outreach_recipient_status_labels(): array
	{
		return array(
			'draft' => __('Draft', 'backstage-outreach'),
			'ready' => __('Ready', 'backstage-outreach'),
			'sent' => __('Sent', 'backstage-outreach'),
			'claimed' => __('Claimed', 'backstage-outreach'),
			'partially_used' => __('Partially Used', 'backstage-outreach'),
			'used' => __('Used', 'backstage-outreach'),
			'expired' => __('Expired', 'backstage-outreach'),
			'revoked' => __('Revoked', 'backstage-outreach'),
		);
	}
}

if (!function_exists('vms_pass_outreach_allowed_send_statuses')) {
	function vms_pass_outreach_allowed_send_statuses(): array
	{
		return array('not_sent', 'queued', 'sent', 'failed', 'suppressed', 'do_not_contact');
	}
}

if (!function_exists('vms_pass_outreach_send_status_labels')) {
	function vms_pass_outreach_send_status_labels(): array
	{
		return array(
			'not_sent' => __('Not Sent', 'backstage-outreach'),
			'queued' => __('Queued', 'backstage-outreach'),
			'sent' => __('Sent', 'backstage-outreach'),
			'failed' => __('Failed', 'backstage-outreach'),
			'suppressed' => __('Suppressed', 'backstage-outreach'),
			'do_not_contact' => __('Do Not Contact', 'backstage-outreach'),
		);
	}
}

if (!function_exists('vms_pass_outreach_send_method_labels')) {
	function vms_pass_outreach_send_method_labels(): array
	{
		return array(
			'email' => __('Email', 'backstage-outreach'),
			'manual_social' => __('Manual / Social', 'backstage-outreach'),
			'text_phone' => __('Text / Phone', 'backstage-outreach'),
			'draft' => __('Draft / No delivery method yet', 'backstage-outreach'),
			'manual' => __('Manual', 'backstage-outreach'),
			'vms_email' => __('VMS Email', 'backstage-outreach'),
		);
	}
}

if (!function_exists('vms_pass_outreach_recipient_delivery_method_options')) {
	function vms_pass_outreach_recipient_delivery_method_options(): array
	{
		return array(
			'email' => __('Email', 'backstage-outreach'),
			'manual_social' => __('Manual / Social', 'backstage-outreach'),
			'text_phone' => __('Text / Phone', 'backstage-outreach'),
			'draft' => __('Draft / No delivery method yet', 'backstage-outreach'),
		);
	}
}

if (!function_exists('vms_pass_outreach_recipient_delivery_method')) {
	function vms_pass_outreach_recipient_delivery_method(array $recipient): string
	{
		$method = sanitize_key((string) ($recipient['delivery_method'] ?? ($recipient['send_method'] ?? '')));
		$options = vms_pass_outreach_recipient_delivery_method_options();
		if ($method === 'email' || $method === 'vms_email') {
			return 'email';
		}
		if ($method === 'manual' || $method === 'manual_social') {
			return 'manual_social';
		}
		if ($method === 'text_phone') {
			return 'text_phone';
		}
		if ($method === 'draft') {
			return 'draft';
		}
		if (isset($options[$method])) {
			return $method;
		}
		return is_email((string) ($recipient['email'] ?? '')) ? 'email' : 'draft';
	}
}

if (!function_exists('vms_pass_outreach_recipient_delivery_method_label')) {
	function vms_pass_outreach_recipient_delivery_method_label(array $recipient): string
	{
		$stored_method = sanitize_key((string) ($recipient['send_method'] ?? ''));
		if ($stored_method !== '') {
			return vms_pass_outreach_send_method_label($stored_method);
		}

		$method = vms_pass_outreach_recipient_delivery_method($recipient);
		$options = vms_pass_outreach_recipient_delivery_method_options();
		return (string) ($options[$method] ?? '');
	}
}

if (!function_exists('vms_pass_outreach_delivery_method_requires_email')) {
	function vms_pass_outreach_delivery_method_requires_email(string $method): bool
	{
		return sanitize_key($method) === 'email';
	}
}

if (!function_exists('vms_pass_outreach_delivery_method_requires_phone')) {
	function vms_pass_outreach_delivery_method_requires_phone(string $method): bool
	{
		return sanitize_key($method) === 'text_phone';
	}
}

if (!function_exists('vms_pass_outreach_delivery_method_supports_email_queue')) {
	function vms_pass_outreach_delivery_method_supports_email_queue(string $method): bool
	{
		return vms_pass_outreach_delivery_method_requires_email($method);
	}
}

if (!function_exists('vms_pass_outreach_requested_delivery_method')) {
	function vms_pass_outreach_requested_delivery_method(array $raw, ?array $recipient = null): string
	{
		$requested = sanitize_key((string) ($raw['delivery_method'] ?? ''));
		$options = vms_pass_outreach_recipient_delivery_method_options();
		if (isset($options[$requested])) {
			return $requested;
		}
		if (is_array($recipient)) {
			return vms_pass_outreach_recipient_delivery_method($recipient);
		}
		return is_email((string) ($raw['email'] ?? '')) ? 'email' : 'draft';
	}
}

if (!function_exists('vms_pass_outreach_recipient_email_delivery_validation')) {
	function vms_pass_outreach_recipient_email_delivery_validation(array $recipient): array
	{
		$method = vms_pass_outreach_recipient_delivery_method($recipient);
		if (!vms_pass_outreach_delivery_method_supports_email_queue($method)) {
			$method_label = vms_pass_outreach_recipient_delivery_method_label($recipient);
			if ($method === 'draft') {
				return array(
					'ok' => false,
					'code' => 'recipient_delivery_not_email',
					'message' => __('Choose Email as the delivery method before submitting this recipient to the email queue.', 'backstage-outreach'),
				);
			}
			return array(
				'ok' => false,
				'code' => 'recipient_delivery_not_email',
				'message' => sprintf(
					__('%s recipients are not submitted to the email queue. Change the delivery method to Email first.', 'backstage-outreach'),
					$method_label !== '' ? $method_label : __('Non-email', 'backstage-outreach')
				),
			);
		}

		$email = sanitize_email((string) ($recipient['email'] ?? ''));
		if (!is_email($email)) {
			return array(
				'ok' => false,
				'code' => 'recipient_email_required',
				'message' => __('Email delivery requires a valid email address.', 'backstage-outreach'),
			);
		}

		return array(
			'ok' => true,
			'code' => 'ok',
			'email' => $email,
			'method' => $method,
		);
	}
}

if (!function_exists('vms_pass_outreach_default_recipient_payload')) {
	function vms_pass_outreach_default_recipient_payload(): array
	{
		return array(
			'id' => 0,
			'campaign_id' => 0,
			'pass_token_id' => 0,
			'pass_claim_id' => 0,
			'reservation_entry_id' => 0,
			'contact_id' => 0,
			'first_name' => '',
			'last_name' => '',
			'full_name' => '',
			'email' => '',
			'email_norm' => '',
			'phone' => '',
			'phone_norm' => '',
			'company' => '',
			'group_label' => '',
			'notes' => '',
			'invite_token' => '',
			'send_status' => 'not_sent',
			'sent_at' => '',
			'sent_by' => 0,
			'send_method' => '',
			'last_send_error' => '',
			'last_contacted_at' => '',
			'claimed_at' => '',
			'revoked_at' => '',
			'expires_at' => '',
			'status' => 'ready',
			'claimed_headcount' => 0,
		);
	}
}

if (!function_exists('vms_pass_outreach_import_row_limit')) {
	function vms_pass_outreach_import_row_limit(): int
	{
		return 500;
	}
}

if (!function_exists('vms_pass_outreach_import_max_file_bytes')) {
	function vms_pass_outreach_import_max_file_bytes(): int
	{
		return defined('MB_IN_BYTES') ? (2 * MB_IN_BYTES) : 2097152;
	}
}

if (!function_exists('vms_pass_outreach_recipient_table')) {
	function vms_pass_outreach_recipient_table(): string
	{
		return vms_admission_table_pass_outreach_recipients();
	}
}

if (!function_exists('vms_pass_outreach_normalize_recipient_row')) {
	function vms_pass_outreach_normalize_recipient_row(array $row): array
	{
		$row = array_merge(vms_pass_outreach_default_recipient_payload(), $row);
		$row['id'] = absint($row['id'] ?? 0);
		$row['campaign_id'] = absint($row['campaign_id'] ?? 0);
		$row['pass_token_id'] = absint($row['pass_token_id'] ?? 0);
		$row['pass_claim_id'] = absint($row['pass_claim_id'] ?? 0);
		$row['reservation_entry_id'] = absint($row['reservation_entry_id'] ?? 0);
		$row['contact_id'] = absint($row['contact_id'] ?? 0);
		$row['claimed_headcount'] = max(0, absint($row['claimed_headcount'] ?? 0));
		$row['first_name'] = sanitize_text_field((string) ($row['first_name'] ?? ''));
		$row['last_name'] = sanitize_text_field((string) ($row['last_name'] ?? ''));
		$row['full_name'] = sanitize_text_field((string) ($row['full_name'] ?? ''));
		$row['email'] = sanitize_email((string) ($row['email'] ?? ''));
		$row['email_norm'] = sanitize_text_field((string) ($row['email_norm'] ?? ''));
		$row['phone'] = sanitize_text_field((string) ($row['phone'] ?? ''));
		$row['phone_norm'] = sanitize_text_field((string) ($row['phone_norm'] ?? ''));
		$row['company'] = sanitize_text_field((string) ($row['company'] ?? ''));
		$row['group_label'] = sanitize_text_field((string) ($row['group_label'] ?? ''));
		$row['notes'] = sanitize_textarea_field((string) ($row['notes'] ?? ''));
		$row['invite_token'] = sanitize_text_field((string) ($row['invite_token'] ?? ''));
		$row['send_status'] = sanitize_key((string) ($row['send_status'] ?? 'not_sent'));
		$row['sent_at'] = sanitize_text_field((string) ($row['sent_at'] ?? ''));
		$row['sent_by'] = absint($row['sent_by'] ?? 0);
		$row['send_method'] = sanitize_key((string) ($row['send_method'] ?? ''));
		$row['last_send_error'] = sanitize_textarea_field((string) ($row['last_send_error'] ?? ''));
		$row['last_contacted_at'] = sanitize_text_field((string) ($row['last_contacted_at'] ?? ''));
		$row['claimed_at'] = sanitize_text_field((string) ($row['claimed_at'] ?? ''));
		$row['revoked_at'] = sanitize_text_field((string) ($row['revoked_at'] ?? ''));
		$row['expires_at'] = sanitize_text_field((string) ($row['expires_at'] ?? ''));
		$row['status'] = sanitize_key((string) ($row['status'] ?? 'ready'));
		if (!in_array($row['send_status'], vms_pass_outreach_allowed_send_statuses(), true)) {
			$row['send_status'] = !empty($row['sent_at']) || $row['status'] === 'sent' ? 'sent' : 'not_sent';
		}
		return $row;
	}
}

if (!function_exists('vms_pass_outreach_recipient_full_name')) {
	function vms_pass_outreach_recipient_full_name(array $recipient): string
	{
		$name = sanitize_text_field((string) ($recipient['full_name'] ?? ''));
		if ($name === '') {
			$name = trim((string) ($recipient['first_name'] ?? '') . ' ' . (string) ($recipient['last_name'] ?? ''));
		}
		if ($name !== '') {
			return $name;
		}
		if (!empty($recipient['email'])) {
			return (string) $recipient['email'];
		}
		if (!empty($recipient['company'])) {
			return (string) $recipient['company'];
		}
		if (!empty($recipient['phone'])) {
			return (string) $recipient['phone'];
		}
		return sprintf(__('Recipient #%d', 'backstage-outreach'), absint($recipient['id'] ?? 0));
	}
}

if (!function_exists('vms_pass_outreach_recipient_first_name')) {
	function vms_pass_outreach_recipient_first_name(array $recipient, string $fallback = ''): string
	{
		$first_name = sanitize_text_field((string) ($recipient['first_name'] ?? ''));
		if ($first_name !== '') {
			return $first_name;
		}

		list($derived_first_name) = vms_pass_outreach_split_name((string) ($recipient['full_name'] ?? ''));
		if ($derived_first_name !== '') {
			return $derived_first_name;
		}

		return sanitize_text_field($fallback);
	}
}

if (!function_exists('vms_pass_outreach_split_name')) {
	function vms_pass_outreach_split_name(string $name): array
	{
		$name = trim(preg_replace('/\s+/u', ' ', $name));
		if ($name === '') {
			return array('', '');
		}

		$parts = preg_split('/\s+/u', $name, 2);
		$first_name = sanitize_text_field((string) ($parts[0] ?? ''));
		$last_name = sanitize_text_field((string) ($parts[1] ?? ''));
		return array($first_name, $last_name);
	}
}

if (!function_exists('vms_pass_outreach_sanitize_recipient_identity_fields')) {
	function vms_pass_outreach_sanitize_recipient_identity_fields(array $raw, array $args = array())
	{
		$allow_name_split = !empty($args['allow_name_split']);
		$require_email = !array_key_exists('require_email', $args) || !empty($args['require_email']);
		$require_phone = !empty($args['require_phone']);
		$require_name_when_no_email = !empty($args['require_name_when_no_email']);
		$require_context_when_no_email = !empty($args['require_context_when_no_email']);
		$first_name = sanitize_text_field((string) ($raw['first_name'] ?? ''));
		$last_name = sanitize_text_field((string) ($raw['last_name'] ?? ''));
		$full_name = sanitize_text_field((string) ($raw['name'] ?? ($raw['full_name'] ?? '')));
		if ($full_name !== '' && $allow_name_split && $first_name === '') {
			list($derived_first_name, $derived_last_name) = vms_pass_outreach_split_name($full_name);
			if ($derived_first_name !== '') {
				$first_name = $derived_first_name;
			}
			if ($last_name === '' && $derived_last_name !== '') {
				$last_name = $derived_last_name;
			}
		}
		if ($full_name === '') {
			$full_name = trim($first_name . ' ' . $last_name);
		}

		$email_raw = sanitize_text_field((string) ($raw['email'] ?? ''));
		$email = sanitize_email($email_raw);
		$email_norm = function_exists('vms_admission_normalize_email') ? vms_admission_normalize_email($email) : sanitize_email($email);
		$phone = sanitize_text_field((string) ($raw['phone'] ?? ''));
		$phone_norm = function_exists('vms_admission_normalize_phone') ? vms_admission_normalize_phone($phone) : preg_replace('/\D+/', '', $phone);
		$company = sanitize_text_field((string) ($raw['company'] ?? ''));
		$group_value = array_key_exists('group_label', $raw) ? $raw['group_label'] : ($raw['group'] ?? '');
		$group_label = sanitize_text_field((string) $group_value);
		$notes = sanitize_textarea_field((string) ($raw['notes'] ?? ''));
		$expires_raw = sanitize_text_field((string) ($raw['expires_at'] ?? ''));
		$expires_at = function_exists('vms_pass_claims_parse_local_datetime')
			? vms_pass_claims_parse_local_datetime($expires_raw)
			: $expires_raw;

		if ($expires_raw !== '' && $expires_at === '') {
			return new WP_Error('invalid_recipient_expiration', __('Recipient expiration must be a valid local date/time.', 'backstage-outreach'));
		}

		if ($email_raw !== '' && ($email_norm === '' || !is_email($email))) {
			return new WP_Error('invalid_recipient_email', __('Enter a valid email address for this outreach recipient.', 'backstage-outreach'));
		}

		if ($require_email) {
			if ($email_raw === '' || $email_norm === '' || !is_email($email)) {
				return new WP_Error('recipient_email_required', __('Email delivery requires a valid email address.', 'backstage-outreach'));
			}
		}

		$phone_norm = is_string($phone_norm) ? sanitize_text_field($phone_norm) : '';
		if ($require_phone && $phone_norm === '') {
			return new WP_Error('recipient_phone_required', __('Text / Phone delivery requires a usable phone number.', 'backstage-outreach'));
		}

		if (!$require_email && $email_norm === '') {
			$has_name = $full_name !== '';
			$has_context = $phone_norm !== '' || $company !== '' || $group_label !== '' || $notes !== '';
			if (($require_name_when_no_email && !$has_name) || ($require_context_when_no_email && !$has_context)) {
				return new WP_Error(
					'recipient_identifying_info_required',
					__('When email is blank, add the recipient name plus at least one of phone, company, group, or notes.', 'backstage-outreach')
				);
			}
		}

		return array(
			'first_name' => $first_name,
			'last_name' => $last_name,
			'full_name' => $full_name,
			'email' => $email,
			'email_norm' => $email_norm,
			'phone' => $phone,
			'phone_norm' => $phone_norm,
			'company' => $company,
			'group_label' => $group_label,
			'notes' => $notes,
			'expires_at' => $expires_at,
		);
	}
}

if (!function_exists('vms_pass_outreach_format_admin_datetime')) {
	function vms_pass_outreach_format_admin_datetime(string $value): string
	{
		$value = trim($value);
		if ($value === '') {
			return '';
		}
		try {
			$dt = new DateTimeImmutable($value, wp_timezone());
			return (string) wp_date('Y-m-d g:i a', $dt->getTimestamp(), wp_timezone());
		} catch (Exception $e) {
			return $value;
		}
	}
}

if (!function_exists('vms_pass_outreach_generate_invite_token')) {
	function vms_pass_outreach_generate_invite_token(): string
	{
		try {
			$raw = random_bytes(24);
		} catch (Exception $e) {
			$raw = wp_generate_password(36, false, false);
			return 'gpi_' . strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', (string) $raw));
		}

		return 'gpi_' . rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
	}
}

if (!function_exists('vms_pass_outreach_generate_unique_invite_token')) {
	function vms_pass_outreach_generate_unique_invite_token(): string
	{
		global $wpdb;
		$table = vms_pass_outreach_recipient_table();

		for ($attempt = 0; $attempt < 10; $attempt += 1) {
			$token = vms_pass_outreach_generate_invite_token();
			$existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE invite_token = %s LIMIT 1", $token));
			if (empty($existing)) {
				return $token;
			}
		}

		return 'gpi_' . strtolower(wp_generate_password(40, false, false));
	}
}

if (!function_exists('vms_pass_outreach_build_invite_url')) {
	function vms_pass_outreach_build_invite_url(array $recipient): string
	{
		$invite_token = trim((string) ($recipient['invite_token'] ?? ''));
		if ($invite_token === '') {
			return '';
		}
		return home_url('/pass/invite/' . rawurlencode($invite_token));
	}
}

if (!function_exists('vms_pass_outreach_supported_merge_tags')) {
	function vms_pass_outreach_supported_merge_tags(): array
	{
		return array(
			'first_name',
			'last_name',
			'full_name',
			'email',
			'phone',
			'company',
			'group_label',
			'campaign_name',
			'invite_url',
			'expires_at',
			'admissions_per_recipient',
			'season_label',
			'venue_name',
			'venue_phone',
			'venue_url',
		);
	}
}

if (!function_exists('vms_pass_outreach_merge_tag_help_html')) {
	function vms_pass_outreach_merge_tag_help_html(): string
	{
		$tags = array();
		foreach (vms_pass_outreach_supported_merge_tags() as $tag) {
			$tags[] = '<code>{' . esc_html($tag) . '}</code>';
		}
		return '<p class="description">' . sprintf(
			/* translators: %s: comma-separated merge tags */
			esc_html__('Supported merge tags: %s. Unknown tags are removed when messages are rendered.', 'backstage-outreach'),
			implode(', ', $tags)
		) . '</p>';
	}
}

if (!function_exists('vms_pass_outreach_preview_context')) {
	function vms_pass_outreach_preview_context(array $campaign): array
	{
		$campaign_id = absint($campaign['id'] ?? 0);
		if ($campaign_id > 0) {
			$recipients = vms_pass_outreach_query_recipients_for_campaign($campaign_id, array(
				'limit' => 1,
			));
			if (!empty($recipients[0]) && is_array($recipients[0])) {
				return array(
					'source_label' => __('First recipient in this campaign', 'backstage-outreach'),
					'recipient' => $recipients[0],
				);
			}
		}

		$sample = vms_pass_outreach_default_recipient_payload();
		$sample['campaign_id'] = $campaign_id;
		$sample['first_name'] = 'Alex';
		$sample['last_name'] = 'Guest';
		$sample['full_name'] = 'Alex Guest';
		$sample['email'] = 'alex@example.com';
		$sample['phone'] = '555-555-0100';
		$sample['company'] = 'Sample Company';
		$sample['group_label'] = 'Preview Group';
		$sample['invite_token'] = 'preview-token';
		$sample['expires_at'] = sanitize_text_field((string) ($campaign['expires_at'] ?? ''));
		return array(
			'source_label' => __('Sample data', 'backstage-outreach'),
			'recipient' => $sample,
		);
	}
}

if (!function_exists('vms_pass_outreach_resolve_template_campaign')) {
	function vms_pass_outreach_resolve_template_campaign(array $recipient, ?array $campaign = null): ?array
	{
		if (is_array($campaign)) {
			return $campaign;
		}

		$campaign_id = absint($recipient['campaign_id'] ?? 0);
		if ($campaign_id <= 0) {
			return null;
		}

		return vms_pass_outreach_get_campaign_by_id($campaign_id);
	}
}

if (!function_exists('vms_pass_outreach_template_event_context')) {
	function vms_pass_outreach_template_event_context(array $campaign, ?array $batch = null): ?array
	{
		if (sanitize_key((string) ($campaign['validity_type'] ?? '')) === 'single_event') {
			$single_event = function_exists('vms_pass_claims_get_event_plan_brief')
				? vms_pass_claims_get_event_plan_brief(absint($campaign['single_event_plan_id'] ?? 0))
				: null;
			if (is_array($single_event)) {
				return $single_event;
			}
		}

		if (!is_array($batch) || !function_exists('vms_pass_claims_eligible_events_for_batch')) {
			return null;
		}

		$events = vms_pass_claims_eligible_events_for_batch($batch);
		$events = function_exists('vms_pass_outreach_filter_events_for_campaign')
			? vms_pass_outreach_filter_events_for_campaign($campaign, $events)
			: $events;
		return !empty($events[0]) && is_array($events[0]) ? $events[0] : null;
	}
}

if (!function_exists('vms_pass_outreach_template_context')) {
	function vms_pass_outreach_template_context(array $recipient, ?array $campaign = null): array
	{
		$campaign = vms_pass_outreach_resolve_template_campaign($recipient, $campaign);
		$batch = is_array($campaign) ? vms_pass_outreach_campaign_batch($campaign) : null;
		$source = is_array($campaign) && function_exists('vms_pass_claims_get_source_by_id')
			? vms_pass_claims_get_source_by_id(absint($campaign['related_source_id'] ?? 0))
			: null;
		$event = is_array($campaign) ? vms_pass_outreach_template_event_context($campaign, $batch) : null;
		$venue_id = is_array($event) ? absint($event['venue_id'] ?? 0) : 0;
		$site_name = sanitize_text_field((string) get_bloginfo('name'));
		if ($site_name === '') {
			$site_name = 'Serenade Range';
		}

		$venue_name = is_array($event) ? sanitize_text_field((string) ($event['venue_name'] ?? '')) : '';
		if ($venue_name === '') {
			$venue_name = $site_name;
		}

		$venue_phone = $venue_id > 0 ? sanitize_text_field((string) get_post_meta($venue_id, '_VenuePhone', true)) : '';
		if ($venue_phone === '' && is_array($source)) {
			$venue_phone = sanitize_text_field((string) ($source['phone'] ?? ''));
		}
		if ($venue_phone === '') {
			$venue_phone = __('our team', 'backstage-outreach');
		}

		$venue_url = $venue_id > 0 ? esc_url_raw((string) get_post_meta($venue_id, '_VenueURL', true)) : '';
		if ($venue_url === '' && $venue_id > 0) {
			$venue_url = get_permalink($venue_id) ?: '';
		}
		if ($venue_url === '') {
			$venue_url = home_url('/');
		}

		$first_name = vms_pass_outreach_recipient_first_name($recipient);
		$full_name = sanitize_text_field((string) ($recipient['full_name'] ?? ''));
		if ($full_name === '') {
			$full_name = trim((string) ($recipient['first_name'] ?? '') . ' ' . (string) ($recipient['last_name'] ?? ''));
		}
		if ($full_name === '') {
			$full_name = trim((string) ($recipient['email'] ?? ''));
		}
		if ($full_name === '') {
			$full_name = trim((string) ($recipient['company'] ?? ''));
		}
		$expires_at = trim((string) ($recipient['expires_at'] ?? ''));
		if ($expires_at === '' && is_array($campaign)) {
			$expires_at = trim((string) ($campaign['expires_at'] ?? ''));
		}
		if ($expires_at === '' && is_array($batch)) {
			$expires_at = trim((string) ($batch['expires_at'] ?? ''));
		}

		$expires_at_label = '';
		if ($expires_at !== '') {
			try {
				$expires_at_label = (string) wp_date('F j, Y g:i a', (new DateTimeImmutable($expires_at, wp_timezone()))->getTimestamp(), wp_timezone());
			} catch (Exception $e) {
				$expires_at_label = sanitize_text_field($expires_at);
			}
		}

		$season_label = is_array($campaign) ? sanitize_text_field((string) ($campaign['season_label'] ?? '')) : '';
		if ($season_label === '' && is_array($batch)) {
			$season_label = sanitize_text_field((string) ($batch['season_label'] ?? ''));
		}
		if ($season_label === '') {
			$season_label = __('Current Season', 'backstage-outreach');
		}

		$invite_url = vms_pass_outreach_build_invite_url($recipient);
		if ($invite_url === '') {
			$invite_url = __('Invite link unavailable', 'backstage-outreach');
		}

		$admissions_per_recipient = is_array($campaign)
			? max(1, absint($campaign['admissions_per_recipient'] ?? 1))
			: max(1, absint($batch['admissions_per_link'] ?? 1));

		return array(
			'campaign' => $campaign,
			'merge_values' => array(
				'first_name' => $first_name !== '' ? $first_name : __('there', 'backstage-outreach'),
				'last_name' => trim((string) ($recipient['last_name'] ?? '')),
				'full_name' => $full_name !== '' ? $full_name : __('Guest', 'backstage-outreach'),
				'email' => trim((string) ($recipient['email'] ?? '')) !== '' ? trim((string) ($recipient['email'] ?? '')) : __('your email address', 'backstage-outreach'),
				'phone' => trim((string) ($recipient['phone'] ?? '')) !== '' ? trim((string) ($recipient['phone'] ?? '')) : __('your phone number', 'backstage-outreach'),
				'company' => trim((string) ($recipient['company'] ?? '')) !== '' ? trim((string) ($recipient['company'] ?? '')) : __('your organization', 'backstage-outreach'),
				'group_label' => trim((string) ($recipient['group_label'] ?? '')) !== '' ? trim((string) ($recipient['group_label'] ?? '')) : __('your group', 'backstage-outreach'),
				'campaign_name' => is_array($campaign) && trim((string) ($campaign['campaign_name'] ?? '')) !== ''
					? trim((string) ($campaign['campaign_name'] ?? ''))
					: __('Guest Pass Outreach', 'backstage-outreach'),
				'invite_url' => $invite_url,
				'expires_at' => $expires_at_label !== '' ? $expires_at_label : __('the listed expiration date', 'backstage-outreach'),
				'admissions_per_recipient' => (string) $admissions_per_recipient,
				'season_label' => $season_label,
				'venue_name' => $venue_name,
				'venue_phone' => $venue_phone,
				'venue_url' => $venue_url,
			),
		);
	}
}

if (!function_exists('vms_pass_outreach_render_template_text')) {
	function vms_pass_outreach_render_template_text(string $template, array $merge_values): string
	{
		$template = str_replace(array("\r\n", "\r"), "\n", $template);
		$rendered = preg_replace_callback('/\{([a-z0-9_]+)\}/i', static function (array $matches) use ($merge_values): string {
			$key = sanitize_key((string) ($matches[1] ?? ''));
			if ($key === '' || !array_key_exists($key, $merge_values)) {
				return '';
			}
			return (string) $merge_values[$key];
		}, $template);

		if (!is_string($rendered)) {
			$rendered = $template;
		}

		$rendered = preg_replace('/[ \t]+/', ' ', $rendered);
		$rendered = preg_replace('/ +([,.!?;:])/', '$1', $rendered);
		$rendered = preg_replace("/[ \t]+\n/", "\n", $rendered);
		$rendered = preg_replace("/\n{3,}/", "\n\n", $rendered);
		return trim((string) $rendered);
	}
}

if (!function_exists('vms_pass_outreach_build_invite_subject')) {
	function vms_pass_outreach_build_invite_subject(array $recipient, ?array $campaign = null): string
	{
		$context = vms_pass_outreach_template_context($recipient, $campaign);
		$campaign = is_array($context['campaign'] ?? null) ? $context['campaign'] : null;
		$template = is_array($campaign)
			? sanitize_text_field((string) ($campaign['email_subject'] ?? ''))
			: '';
		if ($template === '') {
			$template = function_exists('vms_pass_outreach_default_email_subject')
				? vms_pass_outreach_default_email_subject()
				: __('You\'re invited to Serenade Range', 'backstage-outreach');
		}
		return vms_pass_outreach_render_template_text($template, (array) ($context['merge_values'] ?? array()));
	}
}

if (!function_exists('vms_pass_outreach_build_invite_message')) {
	function vms_pass_outreach_build_invite_message(array $recipient, ?array $campaign = null): string
	{
		$context = vms_pass_outreach_template_context($recipient, $campaign);
		$campaign = is_array($context['campaign'] ?? null) ? $context['campaign'] : null;
		$template = is_array($campaign)
			? vms_pass_outreach_sanitize_plain_text_template((string) ($campaign['message_template'] ?? ''))
			: '';
		if ($template === '') {
			$template = function_exists('vms_pass_outreach_default_message_template')
				? vms_pass_outreach_default_message_template()
				: '';
		}
		return vms_pass_outreach_render_template_text($template, (array) ($context['merge_values'] ?? array()));
	}
}

if (!function_exists('vms_pass_outreach_get_recipient_by_id')) {
	function vms_pass_outreach_get_recipient_by_id(int $recipient_id): ?array
	{
		if ($recipient_id <= 0) {
			return null;
		}

		global $wpdb;
		$table = vms_pass_outreach_recipient_table();
		$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $recipient_id), ARRAY_A);
		return is_array($row) ? vms_pass_outreach_normalize_recipient_row($row) : null;
	}
}

if (!function_exists('vms_pass_outreach_get_recipient_by_invite_token')) {
	function vms_pass_outreach_get_recipient_by_invite_token(string $invite_token): ?array
	{
		$invite_token = trim(sanitize_text_field($invite_token));
		if ($invite_token === '') {
			return null;
		}

		global $wpdb;
		$table = vms_pass_outreach_recipient_table();
		$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE invite_token = %s LIMIT 1", $invite_token), ARRAY_A);
		return is_array($row) ? vms_pass_outreach_normalize_recipient_row($row) : null;
	}
}

if (!function_exists('vms_pass_outreach_get_recipient_by_pass_token_id')) {
	function vms_pass_outreach_get_recipient_by_pass_token_id(int $pass_token_id): ?array
	{
		if ($pass_token_id <= 0) {
			return null;
		}

		global $wpdb;
		$table = vms_pass_outreach_recipient_table();
		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$table}
			WHERE pass_token_id = %d
			ORDER BY claimed_at DESC, id DESC
			LIMIT 1",
			$pass_token_id
		), ARRAY_A);
		return is_array($row) ? vms_pass_outreach_normalize_recipient_row($row) : null;
	}
}

if (!function_exists('vms_pass_outreach_get_recipients_for_campaign')) {
	function vms_pass_outreach_get_recipients_for_campaign(int $campaign_id, int $limit = 500): array
	{
		return vms_pass_outreach_query_recipients_for_campaign($campaign_id, array(
			'limit' => $limit,
		));
	}
}

if (!function_exists('vms_pass_outreach_query_recipients_for_campaign')) {
	function vms_pass_outreach_query_recipients_for_campaign(int $campaign_id, array $args = array()): array
	{
		if ($campaign_id <= 0) {
			return array();
		}

		global $wpdb;
		$table = vms_pass_outreach_recipient_table();
		$limit = isset($args['limit']) ? absint($args['limit']) : 500;
		$limit = $limit > 0 ? min(5000, $limit) : 0;
		$recipient_ids = isset($args['ids']) && is_array($args['ids']) ? array_values(array_filter(array_map('absint', $args['ids']))) : array();

		$where = array('campaign_id = %d');
		$params = array($campaign_id);
		if (!empty($recipient_ids)) {
			$placeholders = implode(', ', array_fill(0, count($recipient_ids), '%d'));
			$where[] = "id IN ({$placeholders})";
			$params = array_merge($params, $recipient_ids);
		}

		$sql = "SELECT *
			FROM {$table}
			WHERE " . implode(' AND ', $where) . '
			ORDER BY created_at DESC, id DESC';
		if ($limit > 0) {
			$sql .= ' LIMIT %d';
			$params[] = $limit;
		}
		$rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
		if (!is_array($rows)) {
			return array();
		}

		$rows = array_map('vms_pass_outreach_normalize_recipient_row', $rows);
		$campaign = isset($args['campaign']) && is_array($args['campaign']) ? $args['campaign'] : null;
		$checked_in_counts = isset($args['checked_in_counts']) && is_array($args['checked_in_counts']) ? $args['checked_in_counts'] : null;
		$search = sanitize_text_field((string) ($args['search'] ?? ''));
		$search = function_exists('mb_strtolower') ? mb_strtolower($search) : strtolower($search);
		$status_filter = sanitize_key((string) ($args['status'] ?? ''));
		$group_filter = sanitize_text_field((string) ($args['group_label'] ?? ''));

		$filtered = array();
		foreach ($rows as $row) {
			$status = vms_pass_outreach_recipient_status_for_display($row, $campaign, $checked_in_counts);
			if ($status_filter !== '' && $status !== $status_filter) {
				continue;
			}

			$row_group = sanitize_text_field((string) ($row['group_label'] ?? ''));
			if ($group_filter !== '' && $row_group !== $group_filter) {
				continue;
			}

			if ($search !== '') {
				$haystacks = array(
					vms_pass_outreach_recipient_full_name($row),
					(string) ($row['email'] ?? ''),
					(string) ($row['phone'] ?? ''),
					(string) ($row['company'] ?? ''),
					$row_group,
				);
				$matched = false;
				foreach ($haystacks as $haystack) {
					$haystack = function_exists('mb_strtolower') ? mb_strtolower((string) $haystack) : strtolower((string) $haystack);
					if ($haystack !== '' && strpos($haystack, $search) !== false) {
						$matched = true;
						break;
					}
				}
				if (!$matched) {
					continue;
				}
			}

			$filtered[] = $row;
		}

		return $filtered;
	}
}

if (!function_exists('vms_pass_outreach_campaign_group_labels')) {
	function vms_pass_outreach_campaign_group_labels(int $campaign_id): array
	{
		if ($campaign_id <= 0) {
			return array();
		}

		global $wpdb;
		$table = vms_pass_outreach_recipient_table();
		$rows = $wpdb->get_col($wpdb->prepare(
			"SELECT DISTINCT group_label
			FROM {$table}
			WHERE campaign_id = %d
				AND group_label IS NOT NULL
				AND group_label <> ''
			ORDER BY group_label ASC",
			$campaign_id
		));

		if (!is_array($rows)) {
			return array();
		}

		$labels = array_values(array_unique(array_filter(array_map(static function ($value): string {
			return sanitize_text_field((string) $value);
		}, $rows))));
		sort($labels, SORT_NATURAL | SORT_FLAG_CASE);
		return $labels;
	}
}

if (!function_exists('vms_pass_outreach_campaign_duplicate_lookup')) {
	function vms_pass_outreach_campaign_duplicate_lookup(int $campaign_id): array
	{
		$lookup = array(
			'email' => array(),
			'phone' => array(),
		);
		if ($campaign_id <= 0) {
			return $lookup;
		}

		global $wpdb;
		$table = vms_pass_outreach_recipient_table();
		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT id, email_norm, phone_norm
			FROM {$table}
			WHERE campaign_id = %d",
			$campaign_id
		), ARRAY_A);

		foreach ((array) $rows as $row) {
			$recipient_id = absint($row['id'] ?? 0);
			$email_norm = sanitize_text_field((string) ($row['email_norm'] ?? ''));
			$phone_norm = sanitize_text_field((string) ($row['phone_norm'] ?? ''));
			if ($recipient_id <= 0) {
				continue;
			}
			if ($email_norm !== '' && !isset($lookup['email'][$email_norm])) {
				$lookup['email'][$email_norm] = $recipient_id;
			}
			if ($phone_norm !== '' && !isset($lookup['phone'][$phone_norm])) {
				$lookup['phone'][$phone_norm] = $recipient_id;
			}
		}

		return $lookup;
	}
}

if (!function_exists('vms_pass_outreach_recipient_form_flash_key')) {
	function vms_pass_outreach_recipient_form_flash_key(int $user_id, int $campaign_id): string
	{
		return 'vms_pass_outreach_recipient_form_flash_' . max(0, $user_id) . '_' . max(0, $campaign_id);
	}
}

if (!function_exists('vms_pass_outreach_set_recipient_form_flash')) {
	function vms_pass_outreach_set_recipient_form_flash(int $user_id, int $campaign_id, array $payload): void
	{
		if ($user_id <= 0 || $campaign_id <= 0) {
			return;
		}
		set_transient(vms_pass_outreach_recipient_form_flash_key($user_id, $campaign_id), $payload, 10 * MINUTE_IN_SECONDS);
	}
}

if (!function_exists('vms_pass_outreach_pull_recipient_form_flash')) {
	function vms_pass_outreach_pull_recipient_form_flash(int $user_id, int $campaign_id): array
	{
		if ($user_id <= 0 || $campaign_id <= 0) {
			return array();
		}
		$key = vms_pass_outreach_recipient_form_flash_key($user_id, $campaign_id);
		$data = get_transient($key);
		delete_transient($key);
		return is_array($data) ? $data : array();
	}
}

if (!function_exists('vms_pass_outreach_recipient_validation_error_fields')) {
	function vms_pass_outreach_recipient_validation_error_fields(string $code): array
	{
		switch (sanitize_key($code)) {
			case 'invalid_recipient_email':
			case 'recipient_email_required':
				return array('delivery_method', 'email');
			case 'recipient_phone_required':
				return array('delivery_method', 'phone');
			case 'recipient_identifying_info_required':
				return array('delivery_method', 'first_name', 'last_name', 'phone', 'company', 'group_label', 'notes');
			case 'invalid_recipient_expiration':
				return array('expires_at');
			case 'duplicate_recipient_email':
				return array('email');
			case 'duplicate_recipient_phone':
				return array('phone');
			case 'no_available_campaign_token':
			case 'invite_token_not_ready':
			case 'campaign_missing_batch':
				return array('pass_token_id');
			default:
				return array();
		}
	}
}

if (!function_exists('vms_pass_outreach_recipient_field_errors_from_error')) {
	function vms_pass_outreach_recipient_field_errors_from_error(WP_Error $error): array
	{
		$message = (string) $error->get_error_message();
		$mapped = array();
		foreach (vms_pass_outreach_recipient_validation_error_fields((string) $error->get_error_code()) as $field_key) {
			$mapped[sanitize_key((string) $field_key)] = $message;
		}
		return $mapped;
	}
}

if (!function_exists('vms_pass_outreach_recipient_form_flash_payload')) {
	function vms_pass_outreach_recipient_form_flash_payload(array $raw, array $base = array()): array
	{
		$payload = array_merge(vms_pass_outreach_default_recipient_payload(), $base);
		$payload['id'] = absint($raw['recipient_id'] ?? ($payload['id'] ?? 0));
		$payload['contact_id'] = absint($raw['contact_id'] ?? ($payload['contact_id'] ?? 0));
		$payload['first_name'] = sanitize_text_field((string) ($raw['first_name'] ?? ($payload['first_name'] ?? '')));
		$payload['last_name'] = sanitize_text_field((string) ($raw['last_name'] ?? ($payload['last_name'] ?? '')));
		$payload['email'] = sanitize_text_field((string) ($raw['email'] ?? ($payload['email'] ?? '')));
		$payload['phone'] = sanitize_text_field((string) ($raw['phone'] ?? ($payload['phone'] ?? '')));
		$payload['company'] = sanitize_text_field((string) ($raw['company'] ?? ($payload['company'] ?? '')));
		$payload['group_label'] = sanitize_text_field((string) ($raw['group_label'] ?? ($raw['group'] ?? ($payload['group_label'] ?? ''))));
		$payload['notes'] = sanitize_textarea_field((string) ($raw['notes'] ?? ($payload['notes'] ?? '')));
		$payload['expires_at'] = sanitize_text_field((string) ($raw['expires_at'] ?? ($payload['expires_at'] ?? '')));
		$payload['delivery_method'] = vms_pass_outreach_requested_delivery_method($raw, $payload);
		return $payload;
	}
}

if (!function_exists('vms_pass_outreach_contact_audience_preview_key')) {
	function vms_pass_outreach_contact_audience_preview_key(int $user_id, int $campaign_id): string
	{
		return 'vms_pass_outreach_contact_audience_preview_' . max(0, $user_id) . '_' . max(0, $campaign_id);
	}
}

if (!function_exists('vms_pass_outreach_set_contact_audience_preview')) {
	function vms_pass_outreach_set_contact_audience_preview(int $user_id, int $campaign_id, array $payload): void
	{
		if ($user_id <= 0 || $campaign_id <= 0) {
			return;
		}

		set_transient(vms_pass_outreach_contact_audience_preview_key($user_id, $campaign_id), $payload, 15 * MINUTE_IN_SECONDS);
	}
}

if (!function_exists('vms_pass_outreach_get_contact_audience_preview')) {
	function vms_pass_outreach_get_contact_audience_preview(int $user_id, int $campaign_id): array
	{
		if ($user_id <= 0 || $campaign_id <= 0) {
			return array();
		}

		$data = get_transient(vms_pass_outreach_contact_audience_preview_key($user_id, $campaign_id));
		return is_array($data) ? $data : array();
	}
}

if (!function_exists('vms_pass_outreach_clear_contact_audience_preview')) {
	function vms_pass_outreach_clear_contact_audience_preview(int $user_id, int $campaign_id): void
	{
		if ($user_id <= 0 || $campaign_id <= 0) {
			return;
		}

		delete_transient(vms_pass_outreach_contact_audience_preview_key($user_id, $campaign_id));
	}
}

if (!function_exists('vms_pass_outreach_contact_audience_status_filter_options')) {
	function vms_pass_outreach_contact_audience_status_filter_options(): array
	{
		return array(
			'approved' => __('Approved Only', 'backstage-outreach'),
			'approved_or_maybe' => __('Approved + Maybe', 'backstage-outreach'),
			'all' => __('All Statuses', 'backstage-outreach'),
			'new' => __('New', 'backstage-outreach'),
			'needs_review' => __('Needs Review', 'backstage-outreach'),
			'maybe' => __('Maybe', 'backstage-outreach'),
			'queued' => __('Queued', 'backstage-outreach'),
			'contacted' => __('Contacted', 'backstage-outreach'),
			'interested' => __('Interested', 'backstage-outreach'),
			'applied' => __('Applied', 'backstage-outreach'),
			'excluded' => __('Excluded', 'backstage-outreach'),
			'do_not_contact' => __('Do Not Contact', 'backstage-outreach'),
		);
	}
}

if (!function_exists('vms_pass_outreach_default_contact_audience_filters')) {
	function vms_pass_outreach_default_contact_audience_filters(): array
	{
		return array(
			'search' => '',
			'contact_type' => '',
			'status_scope' => 'approved',
			'city' => '',
			'source' => '',
			'tag' => '',
		);
	}
}

if (!function_exists('vms_pass_outreach_normalize_contact_audience_filters')) {
	function vms_pass_outreach_normalize_contact_audience_filters(array $raw): array
	{
		$defaults = vms_pass_outreach_default_contact_audience_filters();
		$filters = $defaults;
		$filters['search'] = sanitize_text_field((string) ($raw['search'] ?? $defaults['search']));
		$filters['city'] = sanitize_text_field((string) ($raw['city'] ?? $defaults['city']));
		$filters['source'] = sanitize_text_field((string) ($raw['source'] ?? $defaults['source']));
		$filters['tag'] = sanitize_text_field((string) ($raw['tag'] ?? $defaults['tag']));

		$contact_type = sanitize_key((string) ($raw['contact_type'] ?? $defaults['contact_type']));
		$filters['contact_type'] = isset(vms_outreach_contact_type_options()[$contact_type]) ? $contact_type : '';

		$status_scope = sanitize_key((string) ($raw['status_scope'] ?? $defaults['status_scope']));
		$filters['status_scope'] = isset(vms_pass_outreach_contact_audience_status_filter_options()[$status_scope]) ? $status_scope : 'approved';

		return $filters;
	}
}

if (!function_exists('vms_pass_outreach_contact_audience_filter_statuses')) {
	function vms_pass_outreach_contact_audience_filter_statuses(string $status_scope): array
	{
		$status_scope = sanitize_key($status_scope);
		if ($status_scope === 'approved') {
			return array('approved');
		}
		if ($status_scope === 'approved_or_maybe') {
			return array('approved', 'maybe');
		}
		if ($status_scope === 'all') {
			return array_keys(vms_outreach_contact_status_options());
		}
		if (isset(vms_outreach_contact_status_options()[$status_scope])) {
			return array($status_scope);
		}

		return array('approved');
	}
}

if (!function_exists('vms_pass_outreach_contact_audience_status_allowed')) {
	function vms_pass_outreach_contact_audience_status_allowed(string $status, string $status_scope): bool
	{
		$status = sanitize_key($status);
		$status_scope = sanitize_key($status_scope);
		if (in_array($status, array('excluded', 'do_not_contact'), true)) {
			return false;
		}

		if ($status_scope === 'approved') {
			return $status === 'approved';
		}
		if ($status_scope === 'approved_or_maybe') {
			return in_array($status, array('approved', 'maybe'), true);
		}
		if ($status_scope === 'all') {
			return true;
		}

		return $status === $status_scope;
	}
}

if (!function_exists('vms_pass_outreach_contact_audience_filters_match')) {
	function vms_pass_outreach_contact_audience_filters_match(array $left, array $right): bool
	{
		return vms_pass_outreach_normalize_contact_audience_filters($left) === vms_pass_outreach_normalize_contact_audience_filters($right);
	}
}

if (!function_exists('vms_pass_outreach_selected_contact_audience_contact_ids')) {
	function vms_pass_outreach_selected_contact_audience_contact_ids(array $raw): array
	{
		$ids = array();
		$values = isset($raw['selected_contact_ids']) ? (array) $raw['selected_contact_ids'] : array();
		foreach ($values as $value) {
			$contact_id = absint($value);
			if ($contact_id > 0) {
				$ids[] = $contact_id;
			}
		}

		return array_values(array_unique($ids));
	}
}

if (!function_exists('vms_pass_outreach_recipient_linked_contact')) {
	function vms_pass_outreach_recipient_linked_contact(array $recipient): ?array
	{
		$contact_id = absint($recipient['contact_id'] ?? 0);
		if ($contact_id <= 0 || !function_exists('vms_outreach_get_contact_by_id')) {
			return null;
		}

		$contact = vms_outreach_get_contact_by_id($contact_id);
		return is_array($contact) ? $contact : null;
	}
}

if (!function_exists('vms_pass_outreach_recipient_contact_guardrail_state')) {
	function vms_pass_outreach_recipient_contact_guardrail_state(array $recipient): array
	{
		$email = sanitize_email((string) ($recipient['email'] ?? ''));
		$contact = vms_pass_outreach_recipient_linked_contact($recipient);
		$status = sanitize_key((string) ($contact['status'] ?? ''));
		$recipient_send_status = vms_pass_outreach_recipient_stored_send_status($recipient);

		if ($recipient_send_status === 'do_not_contact' || $status === 'do_not_contact') {
			return array(
				'blocked' => true,
				'reason_code' => 'do_not_contact',
				'reason_label' => __('Do Not Contact', 'backstage-outreach'),
				'contact' => $contact,
			);
		}

		$suppressed = $email !== '' && function_exists('vms_outreach_email_is_suppressed')
			? vms_outreach_email_is_suppressed($email)
			: false;

		if (!$suppressed && is_array($contact)) {
			$suppressed = !empty($contact['suppression_id']);
		}

		if ($suppressed) {
			return array(
				'blocked' => true,
				'reason_code' => 'suppressed',
				'reason_label' => __('Suppressed', 'backstage-outreach'),
				'contact' => $contact,
			);
		}

		if ($status === 'excluded') {
			return array(
				'blocked' => true,
				'reason_code' => $status,
				'reason_label' => __('Excluded', 'backstage-outreach'),
				'contact' => $contact,
			);
		}

		return array(
			'blocked' => false,
			'reason_code' => '',
			'reason_label' => '',
			'contact' => $contact,
		);
	}
}

if (!function_exists('vms_pass_outreach_contact_to_recipient_row')) {
	function vms_pass_outreach_contact_to_recipient_row(array $contact): array
	{
		$contact = function_exists('vms_outreach_normalize_contact_row')
			? vms_outreach_normalize_contact_row($contact)
			: $contact;
		$company = sanitize_text_field((string) ($contact['business_name'] ?? ''));
		$group_label = sanitize_text_field((string) ($contact['company_group'] ?? ''));
		if ($group_label === '' && !empty($contact['contact_type']) && function_exists('vms_outreach_contact_type_options')) {
			$group_label = sanitize_text_field((string) (vms_outreach_contact_type_options()[(string) $contact['contact_type']] ?? ''));
		}

		return array(
			'contact_id' => absint($contact['id'] ?? 0),
			'first_name' => sanitize_text_field((string) ($contact['first_name'] ?? '')),
			'last_name' => sanitize_text_field((string) ($contact['last_name'] ?? '')),
			'full_name' => sanitize_text_field((string) ($contact['contact_name'] ?? '')),
			'email' => sanitize_email((string) ($contact['email'] ?? '')),
			'phone' => sanitize_text_field((string) ($contact['phone'] ?? '')),
			'company' => $company,
			'group_label' => $group_label,
			'notes' => sanitize_textarea_field((string) ($contact['notes'] ?? '')),
		);
	}
}

if (!function_exists('vms_pass_outreach_build_contact_audience_preview_core')) {
	function vms_pass_outreach_build_contact_audience_preview_core(array $filters, array $duplicate_lookup = array(), int $campaign_id = 0)
	{
		if (!function_exists('vms_outreach_get_contacts')) {
			return new WP_Error('contact_audience_unavailable', __('Outreach contacts are unavailable.', 'backstage-outreach'));
		}

		$filters = vms_pass_outreach_normalize_contact_audience_filters($filters);
		$status_scope = (string) ($filters['status_scope'] ?? 'approved');
		$statuses = vms_pass_outreach_contact_audience_filter_statuses($status_scope);
		$contacts = vms_outreach_get_contacts(array(
			'limit' => 2000,
			'search' => (string) ($filters['search'] ?? ''),
			'contact_type' => (string) ($filters['contact_type'] ?? ''),
			'city' => (string) ($filters['city'] ?? ''),
			'source' => (string) ($filters['source'] ?? ''),
			'tag' => (string) ($filters['tag'] ?? ''),
			'statuses' => $statuses,
		));

		$duplicate_lookup = is_array($duplicate_lookup) ? $duplicate_lookup : array();
		$seen_email_norms = array();
		$prepared_rows = array();
		$preview_rows = array();
		$counts = array(
			'eligible_count' => 0,
			'already_in_campaign_count' => 0,
			'missing_email_count' => 0,
			'globally_suppressed_count' => 0,
			'excluded_count' => 0,
			'duplicate_email_count' => 0,
			'status_skipped_count' => 0,
			'skipped_count' => 0,
			'total_contacts' => count($contacts),
		);

		foreach ($contacts as $contact) {
			$contact = function_exists('vms_outreach_normalize_contact_row')
				? vms_outreach_normalize_contact_row($contact)
				: $contact;
			$email = sanitize_email((string) ($contact['email'] ?? ''));
			$email_norm = sanitize_text_field((string) ($contact['email_norm'] ?? ''));
			$status = sanitize_key((string) ($contact['status'] ?? 'new'));
			$reason_code = '';
			$reason_label = '';
			$action = 'add';

			if ($email_norm === '' || !is_email($email)) {
				$reason_code = 'missing_email';
				$reason_label = __('Missing / Invalid Email', 'backstage-outreach');
				$counts['missing_email_count'] += 1;
			} elseif (isset($seen_email_norms[$email_norm])) {
				$reason_code = 'duplicate_email';
				$reason_label = __('Duplicate Email', 'backstage-outreach');
				$counts['duplicate_email_count'] += 1;
			} elseif (isset($duplicate_lookup['email'][$email_norm])) {
				$reason_code = 'already_in_campaign';
				$reason_label = __('Already in Campaign', 'backstage-outreach');
				$counts['already_in_campaign_count'] += 1;
			} elseif (!empty($contact['suppression_id']) || (function_exists('vms_outreach_email_is_suppressed') && vms_outreach_email_is_suppressed($email))) {
				$reason_code = 'globally_suppressed';
				$reason_label = __('Globally Suppressed', 'backstage-outreach');
				$counts['globally_suppressed_count'] += 1;
			} elseif (in_array($status, array('excluded', 'do_not_contact'), true)) {
				$reason_code = 'excluded';
				$reason_label = __('Excluded / Do Not Contact', 'backstage-outreach');
				$counts['excluded_count'] += 1;
			} elseif (!vms_pass_outreach_contact_audience_status_allowed($status, $status_scope)) {
				$reason_code = 'status_ineligible';
				$reason_label = __('Status Not Eligible', 'backstage-outreach');
				$counts['status_skipped_count'] += 1;
			}

			if ($email_norm !== '') {
				$seen_email_norms[$email_norm] = true;
			}

			if ($reason_code !== '') {
				$action = 'skip';
				$counts['skipped_count'] += 1;
			} else {
				$prepared_rows[] = vms_pass_outreach_contact_to_recipient_row($contact);
				$counts['eligible_count'] += 1;
			}

			$contact_id = absint($contact['id'] ?? 0);
			$preview_rows[] = array(
				'action' => $action,
				'reason_code' => $reason_code,
				'reason_label' => $reason_label,
				'contact_id' => $contact_id,
				'selectable' => $action === 'add' && $contact_id > 0,
				'contact_name' => function_exists('vms_outreach_contact_display_name')
					? vms_outreach_contact_display_name($contact)
					: sanitize_text_field((string) ($contact['contact_name'] ?? '')),
				'email' => $email,
				'business_name' => sanitize_text_field((string) ($contact['business_name'] ?? '')),
				'contact_type' => sanitize_key((string) ($contact['contact_type'] ?? 'other')),
				'status' => $status,
				'city' => sanitize_text_field((string) ($contact['city'] ?? '')),
				'state' => sanitize_text_field((string) ($contact['state'] ?? '')),
				'source' => sanitize_text_field((string) ($contact['source'] ?? '')),
				'tags' => sanitize_text_field((string) ($contact['tags'] ?? '')),
				'suppressed' => !empty($contact['suppression_id']),
			);
		}

		return array_merge($counts, array(
			'campaign_id' => $campaign_id,
			'filters' => $filters,
			'prepared_rows' => $prepared_rows,
			'preview_rows' => $preview_rows,
		));
	}
}

if (!function_exists('vms_pass_outreach_build_contact_audience_preview')) {
	function vms_pass_outreach_build_contact_audience_preview(array $campaign, array $filters)
	{
		$campaign_id = absint($campaign['id'] ?? 0);
		if ($campaign_id <= 0) {
			return new WP_Error('invalid_campaign', __('Select a valid outreach campaign before adding contacts.', 'backstage-outreach'));
		}

		$campaign_purpose = function_exists('vms_outreach_normalize_campaign_purpose')
			? vms_outreach_normalize_campaign_purpose((string) ($campaign['campaign_purpose'] ?? ''))
			: sanitize_key((string) ($campaign['campaign_purpose'] ?? 'guest_pass_invitation'));
		if ($campaign_purpose !== 'guest_pass_invitation') {
			return new WP_Error('contact_audience_purpose_unavailable', __('Add from Contacts is currently available only for Guest Pass Invitation campaigns.', 'backstage-outreach'));
		}

		return vms_pass_outreach_build_contact_audience_preview_core(
			$filters,
			vms_pass_outreach_campaign_duplicate_lookup($campaign_id),
			$campaign_id
		);
	}
}

if (!function_exists('vms_pass_outreach_build_contact_audience_create_preview')) {
	function vms_pass_outreach_build_contact_audience_create_preview(array $filters)
	{
		return vms_pass_outreach_build_contact_audience_preview_core($filters, array(), 0);
	}
}

if (!function_exists('vms_pass_outreach_contact_audience_selected_prepared_rows')) {
	function vms_pass_outreach_contact_audience_selected_prepared_rows(array $preview, array $selected_contact_ids): array
	{
		$prepared_rows = isset($preview['prepared_rows']) && is_array($preview['prepared_rows']) ? $preview['prepared_rows'] : array();
		if (empty($prepared_rows) || empty($selected_contact_ids)) {
			return array();
		}

		$selected_lookup = array_fill_keys(array_values(array_filter(array_map('absint', $selected_contact_ids))), true);
		if (empty($selected_lookup)) {
			return array();
		}

		$selected_rows = array();
		foreach ($prepared_rows as $prepared_row) {
			$contact_id = absint($prepared_row['contact_id'] ?? 0);
			if ($contact_id <= 0 || !isset($selected_lookup[$contact_id])) {
				continue;
			}

			$selected_rows[] = $prepared_row;
		}

		return $selected_rows;
	}
}

if (!function_exists('vms_pass_outreach_checked_in_map_for_claim_ids')) {
	function vms_pass_outreach_checked_in_map_for_claim_ids(array $claim_ids): array
	{
		$claim_ids = array_values(array_filter(array_map('absint', $claim_ids)));
		if (empty($claim_ids)) {
			return array();
		}

		global $wpdb;
		$entries_table = vms_admission_table_entries();
		$placeholders = implode(', ', array_fill(0, count($claim_ids), '%d'));
		$sql = "SELECT
				pass_claim_id,
				SUM(CASE WHEN status <> 'canceled' AND checked_in_qty > 0 THEN 1 ELSE 0 END) AS checked_in_entries,
				SUM(CASE WHEN status <> 'canceled' THEN checked_in_qty ELSE 0 END) AS checked_in_headcount
			FROM {$entries_table}
			WHERE pass_claim_id IN ({$placeholders})
			GROUP BY pass_claim_id";
		$rows = $wpdb->get_results($wpdb->prepare($sql, $claim_ids), ARRAY_A);

		$map = array();
		foreach ((array) $rows as $row) {
			$claim_id = absint($row['pass_claim_id'] ?? 0);
			if ($claim_id <= 0) {
				continue;
			}
			$map[$claim_id] = array(
				'checked_in_entries' => max(0, absint($row['checked_in_entries'] ?? 0)),
				'checked_in_headcount' => max(0, absint($row['checked_in_headcount'] ?? 0)),
			);
		}

		return $map;
	}
}

if (!function_exists('vms_pass_outreach_checked_in_map_for_recipients')) {
	function vms_pass_outreach_checked_in_map_for_recipients(array $recipients): array
	{
		$claim_ids = array();
		foreach ($recipients as $recipient) {
			$claim_id = absint($recipient['pass_claim_id'] ?? 0);
			if ($claim_id > 0) {
				$claim_ids[] = $claim_id;
			}
		}
		return vms_pass_outreach_checked_in_map_for_claim_ids($claim_ids);
	}
}

if (!function_exists('vms_pass_outreach_campaign_has_recipients')) {
	function vms_pass_outreach_campaign_has_recipients(int $campaign_id): bool
	{
		if ($campaign_id <= 0) {
			return false;
		}

		global $wpdb;
		$table = vms_pass_outreach_recipient_table();
		$count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE campaign_id = %d", $campaign_id));
		return $count > 0;
	}
}

if (!function_exists('vms_pass_outreach_campaign_edit_guardrails')) {
	function vms_pass_outreach_campaign_edit_guardrails(array $campaign): array
	{
		$campaign_id = absint($campaign['id'] ?? 0);
		$defaults = array(
			'campaign_id' => $campaign_id,
			'recipient_count' => 0,
			'sent_count' => 0,
			'claimed_recipients' => 0,
			'claimed_headcount' => 0,
			'checked_in_headcount' => 0,
			'max_claimed_headcount_per_recipient' => 0,
			'has_recipients' => false,
			'has_sent' => false,
			'has_claims' => false,
			'has_checkins' => false,
			'has_issued_activity' => false,
		);
		if ($campaign_id <= 0) {
			return $defaults;
		}

		static $cache = array();
		if (isset($cache[$campaign_id])) {
			return $cache[$campaign_id];
		}

		$summary = function_exists('vms_pass_outreach_campaign_summary')
			? vms_pass_outreach_campaign_summary($campaign)
			: array();

		global $wpdb;
		$table = vms_pass_outreach_recipient_table();
		$max_claimed_headcount = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COALESCE(MAX(claimed_headcount), 0)
			FROM {$table}
			WHERE campaign_id = %d",
			$campaign_id
		));

		$guardrails = array(
			'campaign_id' => $campaign_id,
			'recipient_count' => max(0, absint($summary['total_recipients'] ?? 0)),
			'sent_count' => max(0, absint($summary['sent_recipients'] ?? 0)),
			'claimed_recipients' => max(0, absint($summary['claimed_recipients'] ?? 0)),
			'claimed_headcount' => max(0, absint($summary['admissions_claimed'] ?? 0)),
			'checked_in_headcount' => max(0, absint($summary['admissions_checked_in'] ?? 0)),
			'max_claimed_headcount_per_recipient' => max(0, $max_claimed_headcount),
		);
		$guardrails['has_recipients'] = $guardrails['recipient_count'] > 0;
		$guardrails['has_sent'] = $guardrails['sent_count'] > 0;
		$guardrails['has_claims'] = $guardrails['claimed_headcount'] > 0 || $guardrails['claimed_recipients'] > 0;
		$guardrails['has_checkins'] = $guardrails['checked_in_headcount'] > 0;
		$guardrails['has_issued_activity'] = $guardrails['has_recipients'] || $guardrails['has_sent'] || $guardrails['has_claims'] || $guardrails['has_checkins'];

		$cache[$campaign_id] = $guardrails;
		return $guardrails;
	}
}

if (!function_exists('vms_pass_outreach_validate_campaign_batch_update')) {
	function vms_pass_outreach_validate_campaign_batch_update(int $campaign_id, array $payload)
	{
		if ($campaign_id <= 0 || !vms_pass_outreach_campaign_has_recipients($campaign_id)) {
			return true;
		}

		$existing_campaign = vms_pass_outreach_get_campaign_by_id($campaign_id);
		if (!is_array($existing_campaign)) {
			return true;
		}

		$old_batch_id = absint($existing_campaign['related_batch_id'] ?? 0);
		$new_batch_id = absint($payload['related_batch_id'] ?? 0);
		if ($old_batch_id !== $new_batch_id) {
			return new WP_Error(
				'campaign_batch_locked',
				__('This campaign already has outreach recipients, so its linked Guest Pass batch cannot be changed. Create a new campaign if you need a different batch.', 'backstage-outreach')
			);
		}

		return true;
	}
}

if (!function_exists('vms_pass_outreach_validate_campaign_runtime_guardrails')) {
	function vms_pass_outreach_validate_campaign_runtime_guardrails(int $campaign_id, array $payload, array $raw = array())
	{
		if ($campaign_id <= 0) {
			return true;
		}

		$existing_campaign = vms_pass_outreach_get_campaign_by_id($campaign_id);
		if (!is_array($existing_campaign)) {
			return true;
		}

		$guardrails = vms_pass_outreach_campaign_edit_guardrails($existing_campaign);
		if (empty($guardrails['has_issued_activity'])) {
			return true;
		}

		$confirm_forward_only = !empty($raw['confirm_forward_only_changes']);
		$existing_scope = array(
			'validity_type' => sanitize_key((string) ($existing_campaign['validity_type'] ?? '')),
			'single_event_plan_id' => absint($existing_campaign['single_event_plan_id'] ?? 0),
			'start_date' => sanitize_text_field((string) ($existing_campaign['start_date'] ?? '')),
			'end_date' => sanitize_text_field((string) ($existing_campaign['end_date'] ?? '')),
			'season_label' => sanitize_text_field((string) ($existing_campaign['season_label'] ?? '')),
		);
		$incoming_scope = array(
			'validity_type' => sanitize_key((string) ($payload['validity_type'] ?? '')),
			'single_event_plan_id' => absint($payload['single_event_plan_id'] ?? 0),
			'start_date' => sanitize_text_field((string) ($payload['start_date'] ?? '')),
			'end_date' => sanitize_text_field((string) ($payload['end_date'] ?? '')),
			'season_label' => sanitize_text_field((string) ($payload['season_label'] ?? '')),
		);
		$scope_changed = $existing_scope !== $incoming_scope;

		$new_total_cap = max(0, absint($payload['total_admission_cap'] ?? 0));
		$old_total_cap = max(0, absint($existing_campaign['total_admission_cap'] ?? 0));
		$total_cap_lowered_below_claimed = $new_total_cap > 0
			&& ($old_total_cap === 0 || $new_total_cap < $old_total_cap)
			&& $guardrails['claimed_headcount'] > $new_total_cap;

		$new_recipient_cap = max(1, absint($payload['admissions_per_recipient'] ?? 1));
		$old_recipient_cap = max(1, absint($existing_campaign['admissions_per_recipient'] ?? 1));
		$recipient_cap_lowered_below_claimed = $new_recipient_cap < $old_recipient_cap
			&& $guardrails['max_claimed_headcount_per_recipient'] > $new_recipient_cap;

		if ($scope_changed && !$confirm_forward_only) {
			return new WP_Error(
				'campaign_scope_change_confirmation_required',
				$guardrails['has_claims'] || $guardrails['has_checkins']
					? __('This campaign already has claimed or checked-in passes. Confirm the forward-only changes checkbox before changing Applies To. Existing claims stay valid and the new scope only affects future claims.', 'backstage-outreach')
					: __('This campaign already has issued outreach recipients. Confirm the forward-only changes checkbox before changing Applies To so the updated scope applies only to future claims.', 'backstage-outreach')
			);
		}

		if ($total_cap_lowered_below_claimed && !$confirm_forward_only) {
			return new WP_Error(
				'campaign_cap_below_claimed_confirmation_required',
				sprintf(
					__('This campaign already has %1$d claimed admissions. Confirm the forward-only changes checkbox before lowering Total Campaign Cap to %2$d. Existing claims stay valid and the lower cap only blocks additional future claims.', 'backstage-outreach'),
					(int) $guardrails['claimed_headcount'],
					(int) $new_total_cap
				)
			);
		}

		if ($recipient_cap_lowered_below_claimed && !$confirm_forward_only) {
			return new WP_Error(
				'recipient_cap_below_claimed_confirmation_required',
				sprintf(
					__('A recipient has already claimed %1$d admissions. Confirm the forward-only changes checkbox before lowering Passes Per Recipient to %2$d. Existing claims stay valid and the lower limit only affects future claims.', 'backstage-outreach'),
					(int) $guardrails['max_claimed_headcount_per_recipient'],
					(int) $new_recipient_cap
				)
			);
		}

		return true;
	}
}

if (!function_exists('vms_pass_outreach_campaign_batch')) {
	function vms_pass_outreach_campaign_batch(array $campaign): ?array
	{
		$batch_id = absint($campaign['related_batch_id'] ?? 0);
		if ($batch_id <= 0 || !function_exists('vms_pass_claims_get_batch_by_id')) {
			return null;
		}
		$batch = vms_pass_claims_get_batch_by_id($batch_id);
		return is_array($batch) ? $batch : null;
	}
}

if (!function_exists('vms_pass_outreach_campaign_supports_recipients')) {
	function vms_pass_outreach_campaign_supports_recipients(array $campaign): bool
	{
		return is_array(vms_pass_outreach_campaign_batch($campaign));
	}
}

if (!function_exists('vms_pass_outreach_available_token_count')) {
	function vms_pass_outreach_available_token_count(array $campaign): int
	{
		$batch = vms_pass_outreach_campaign_batch($campaign);
		if (!is_array($batch)) {
			return 0;
		}

		global $wpdb;
		$tokens_table = vms_admission_table_pass_tokens();
		$recipients_table = vms_pass_outreach_recipient_table();
		$batch_id = absint($batch['id'] ?? 0);
		if ($batch_id <= 0) {
			return 0;
		}

		$count = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(1)
			FROM {$tokens_table} t
			LEFT JOIN {$recipients_table} r ON r.pass_token_id = t.id
			WHERE t.batch_id = %d
				AND t.status = 'unclaimed'
				AND r.id IS NULL",
			$batch_id
		));

		return max(0, absint($count));
	}
}

if (!function_exists('vms_pass_outreach_get_pass_token_row_for_recipient')) {
	function vms_pass_outreach_get_pass_token_row_for_recipient(array $recipient): ?array
	{
		$pass_token_id = absint($recipient['pass_token_id'] ?? 0);
		if ($pass_token_id <= 0 || !function_exists('vms_pass_claims_get_token_by_id')) {
			return null;
		}
		$row = vms_pass_claims_get_token_by_id($pass_token_id);
		return is_array($row) ? $row : null;
	}
}

if (!function_exists('vms_pass_outreach_validate_recipient_delivery')) {
	function vms_pass_outreach_validate_recipient_delivery(array $recipient, ?array $campaign = null, ?array $claim_guardrail = null): array
	{
		$campaign = is_array($campaign) ? $campaign : vms_pass_outreach_get_campaign_by_id(absint($recipient['campaign_id'] ?? 0));
		if (!is_array($campaign)) {
			return array(
				'ok' => false,
				'reason_code' => 'campaign_missing',
				'admin_reasons' => array(__('Outreach campaign not found.', 'backstage-outreach')),
				'details' => array(
					'recipient_id' => absint($recipient['id'] ?? 0),
				),
				'campaign' => null,
				'batch' => null,
				'token_row' => null,
			);
		}

		if ($claim_guardrail === null) {
			$claim_guardrail = function_exists('vms_pass_outreach_campaign_claim_guardrail')
				? vms_pass_outreach_campaign_claim_guardrail($campaign)
				: array('ok' => true, 'batch' => null);
		}
		if (empty($claim_guardrail['ok'])) {
			return array_merge($claim_guardrail, array(
				'campaign' => $campaign,
				'batch' => is_array($claim_guardrail['batch'] ?? null) ? (array) $claim_guardrail['batch'] : null,
				'token_row' => null,
			));
		}

		$batch = is_array($claim_guardrail['batch'] ?? null)
			? (array) $claim_guardrail['batch']
			: vms_pass_outreach_campaign_batch($campaign);
		if (!is_array($batch)) {
			return array(
				'ok' => false,
				'reason_code' => 'campaign_missing_batch',
				'admin_reasons' => array(__('Linked Guest Pass batch not found.', 'backstage-outreach')),
				'details' => array(
					'campaign_id' => absint($campaign['id'] ?? 0),
					'recipient_id' => absint($recipient['id'] ?? 0),
				),
				'campaign' => $campaign,
				'batch' => null,
				'token_row' => null,
			);
		}

		$invite_token = trim((string) ($recipient['invite_token'] ?? ''));
		if ($invite_token === '') {
			return array(
				'ok' => false,
				'reason_code' => 'invite_token_not_ready',
				'admin_reasons' => array(__('Invite link token is missing for this recipient.', 'backstage-outreach')),
				'details' => array(
					'campaign_id' => absint($campaign['id'] ?? 0),
					'recipient_id' => absint($recipient['id'] ?? 0),
				),
				'campaign' => $campaign,
				'batch' => $batch,
				'token_row' => null,
			);
		}

		$token_row = vms_pass_outreach_get_pass_token_row_for_recipient($recipient);
		if (!is_array($token_row)) {
			return array(
				'ok' => false,
				'reason_code' => 'invite_token_not_ready',
				'admin_reasons' => array(__('Reserved Guest Pass token is missing for this recipient.', 'backstage-outreach')),
				'details' => array(
					'campaign_id' => absint($campaign['id'] ?? 0),
					'recipient_id' => absint($recipient['id'] ?? 0),
				),
				'campaign' => $campaign,
				'batch' => $batch,
				'token_row' => null,
			);
		}

		$token_id = absint($token_row['id'] ?? 0);
		$token_status = sanitize_key((string) ($token_row['status'] ?? ''));
		if ($token_id <= 0 || absint($recipient['pass_token_id'] ?? 0) !== $token_id) {
			return array(
				'ok' => false,
				'reason_code' => 'invite_token_mismatch',
				'admin_reasons' => array(__('Reserved Guest Pass token does not match this recipient.', 'backstage-outreach')),
				'details' => array(
					'campaign_id' => absint($campaign['id'] ?? 0),
					'recipient_id' => absint($recipient['id'] ?? 0),
					'token_id' => $token_id,
				),
				'campaign' => $campaign,
				'batch' => $batch,
				'token_row' => $token_row,
			);
		}

		if (absint($token_row['batch_id'] ?? 0) !== absint($batch['id'] ?? 0)) {
			return array(
				'ok' => false,
				'reason_code' => 'invite_token_mismatch',
				'admin_reasons' => array(__('Reserved Guest Pass token is not in the campaign batch.', 'backstage-outreach')),
				'details' => array(
					'campaign_id' => absint($campaign['id'] ?? 0),
					'recipient_id' => absint($recipient['id'] ?? 0),
					'token_id' => $token_id,
					'batch_id' => absint($batch['id'] ?? 0),
				),
				'campaign' => $campaign,
				'batch' => $batch,
				'token_row' => $token_row,
			);
		}

		if ($token_status === 'void') {
			return array(
				'ok' => false,
				'reason_code' => 'invite_not_active',
				'admin_reasons' => array(__('Reserved Guest Pass token is void.', 'backstage-outreach')),
				'details' => array(
					'recipient_id' => absint($recipient['id'] ?? 0),
					'token_id' => $token_id,
				),
				'campaign' => $campaign,
				'batch' => $batch,
				'token_row' => $token_row,
			);
		}

		if ($token_status === 'claiming') {
			return array(
				'ok' => false,
				'reason_code' => 'invite_claim_in_progress',
				'admin_reasons' => array(__('Reserved Guest Pass token is currently mid-claim.', 'backstage-outreach')),
				'details' => array(
					'recipient_id' => absint($recipient['id'] ?? 0),
					'token_id' => $token_id,
				),
				'campaign' => $campaign,
				'batch' => $batch,
				'token_row' => $token_row,
			);
		}

		if ($token_status !== 'unclaimed' && $token_status !== 'claimed') {
			return array(
				'ok' => false,
				'reason_code' => 'invite_not_active',
				'admin_reasons' => array(__('Reserved Guest Pass token is not claimable.', 'backstage-outreach')),
				'details' => array(
					'recipient_id' => absint($recipient['id'] ?? 0),
					'token_id' => $token_id,
				),
				'campaign' => $campaign,
				'batch' => $batch,
				'token_row' => $token_row,
			);
		}

		if ($token_status === 'claimed' && empty($recipient['claimed_at'])) {
			return array(
				'ok' => false,
				'reason_code' => 'already_claimed',
				'admin_reasons' => array(__('Reserved Guest Pass token is already claimed.', 'backstage-outreach')),
				'details' => array(
					'recipient_id' => absint($recipient['id'] ?? 0),
					'token_id' => $token_id,
				),
				'campaign' => $campaign,
				'batch' => $batch,
				'token_row' => $token_row,
			);
		}

		$recipient_preflight = vms_pass_outreach_recipient_preflight($recipient, $campaign, $batch, $token_row);
		if (empty($recipient_preflight['ok'])) {
			return array_merge($recipient_preflight, array(
				'campaign' => $campaign,
				'batch' => $batch,
				'token_row' => $token_row,
			));
		}

		return array(
			'ok' => true,
			'reason_code' => '',
			'admin_reasons' => array(),
			'details' => array(
				'recipient_id' => absint($recipient['id'] ?? 0),
				'token_id' => $token_id,
			),
			'campaign' => $campaign,
			'batch' => $batch,
			'token_row' => $token_row,
		);
	}
}

if (!function_exists('vms_pass_outreach_find_available_pass_token_row')) {
	function vms_pass_outreach_find_available_pass_token_row(int $batch_id, int $exclude_recipient_id = 0, int $preferred_token_id = 0): ?array
	{
		if ($batch_id <= 0) {
			return null;
		}

		global $wpdb;
		$tokens_table = vms_admission_table_pass_tokens();
		$recipients_table = vms_pass_outreach_recipient_table();

		if ($preferred_token_id > 0) {
			$row = $wpdb->get_row($wpdb->prepare(
				"SELECT t.*
				FROM {$tokens_table} t
				LEFT JOIN {$recipients_table} r
					ON r.pass_token_id = t.id
					AND r.id <> %d
				WHERE t.id = %d
					AND t.batch_id = %d
					AND (t.status = 'unclaimed' OR t.status = 'claimed')
					AND r.id IS NULL
				LIMIT 1",
				$exclude_recipient_id,
				$preferred_token_id,
				$batch_id
			), ARRAY_A);
			if (is_array($row)) {
				return $row;
			}
		}

		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT t.*
			FROM {$tokens_table} t
			LEFT JOIN {$recipients_table} r
				ON r.pass_token_id = t.id
				AND r.id <> %d
			WHERE t.batch_id = %d
				AND t.status = 'unclaimed'
				AND r.id IS NULL
			ORDER BY t.id ASC
			LIMIT 1",
			$exclude_recipient_id,
			$batch_id
		), ARRAY_A);

		return is_array($row) ? $row : null;
	}
}

if (!function_exists('vms_pass_outreach_recipient_reset_send_method_value')) {
	function vms_pass_outreach_recipient_reset_send_method_value(array $recipient): string
	{
		$stored_method = sanitize_key((string) ($recipient['send_method'] ?? ''));
		if (in_array($stored_method, array('email', 'manual_social', 'text_phone', 'draft'), true)) {
			return $stored_method;
		}
		if ($stored_method === 'vms_email') {
			return 'email';
		}
		if ($stored_method === 'manual') {
			return is_email((string) ($recipient['email'] ?? '')) ? 'email' : 'manual_social';
		}
		return vms_pass_outreach_recipient_delivery_method($recipient);
	}
}

if (!function_exists('vms_pass_outreach_recipient_send_state_overrides')) {
	function vms_pass_outreach_recipient_send_state_overrides(?array $recipient, string $delivery_method): array
	{
		$delivery_method = sanitize_key($delivery_method);
		$options = vms_pass_outreach_recipient_delivery_method_options();
		if (!isset($options[$delivery_method])) {
			$delivery_method = 'draft';
		}

		$overrides = array(
			'send_method' => $delivery_method,
		);

		if (!is_array($recipient)) {
			return $overrides;
		}

		$stored_send_status = vms_pass_outreach_recipient_stored_send_status($recipient);
		$existing_send_method = sanitize_key((string) ($recipient['send_method'] ?? ''));
		if (!empty($recipient['sent_at']) || $stored_send_status === 'sent') {
			if ($existing_send_method !== '') {
				$overrides['send_method'] = $existing_send_method;
			}
			return $overrides;
		}

		if (
			!vms_pass_outreach_delivery_method_supports_email_queue($delivery_method)
			&& in_array($stored_send_status, array('queued', 'failed'), true)
		) {
			$overrides['send_status'] = 'not_sent';
			$overrides['last_send_error'] = '';
		}

		return $overrides;
	}
}

if (!function_exists('vms_pass_outreach_sanitize_recipient_payload')) {
	function vms_pass_outreach_sanitize_recipient_payload(array $raw, int $campaign_id, int $recipient_id = 0)
	{
		$campaign = vms_pass_outreach_get_campaign_by_id($campaign_id);
		if (!is_array($campaign)) {
			return new WP_Error('invalid_campaign', __('Select a valid outreach campaign before saving recipients.', 'backstage-outreach'));
		}

		$batch = vms_pass_outreach_campaign_batch($campaign);
		if (!is_array($batch)) {
			return new WP_Error('campaign_missing_batch', __('Recipients currently require a linked Guest Pass batch so each invite can reserve a unique claim token.', 'backstage-outreach'));
		}

		$recipient = $recipient_id > 0 ? vms_pass_outreach_get_recipient_by_id($recipient_id) : null;
		if ($recipient_id > 0 && !is_array($recipient)) {
			return new WP_Error('invalid_recipient', __('The selected outreach recipient could not be found.', 'backstage-outreach'));
		}
		if (is_array($recipient) && absint($recipient['campaign_id'] ?? 0) !== $campaign_id) {
			return new WP_Error('recipient_campaign_mismatch', __('That outreach recipient does not belong to the selected campaign.', 'backstage-outreach'));
		}

		$delivery_method = vms_pass_outreach_requested_delivery_method($raw, $recipient);
		$identity = vms_pass_outreach_sanitize_recipient_identity_fields($raw, array(
			'require_email' => vms_pass_outreach_delivery_method_requires_email($delivery_method),
			'require_phone' => vms_pass_outreach_delivery_method_requires_phone($delivery_method),
			'require_name_when_no_email' => !vms_pass_outreach_delivery_method_requires_email($delivery_method),
			'require_context_when_no_email' => !vms_pass_outreach_delivery_method_requires_email($delivery_method),
		));
		if (is_wp_error($identity)) {
			return $identity;
		}

		if (!vms_pass_outreach_delivery_method_requires_email($delivery_method)) {
			$has_name = trim((string) ($identity['full_name'] ?? '')) !== '';
			$has_context = trim((string) ($identity['phone_norm'] ?? '')) !== ''
				|| trim((string) ($identity['company'] ?? '')) !== ''
				|| trim((string) ($identity['group_label'] ?? '')) !== ''
				|| trim((string) ($identity['notes'] ?? '')) !== '';
			if (!$has_name || !$has_context) {
				return new WP_Error(
					'recipient_identifying_info_required',
					__('When email delivery is not selected, add the recipient name plus at least one of phone, company, group, or notes.', 'backstage-outreach')
				);
			}
		}

		$duplicate_lookup = vms_pass_outreach_campaign_duplicate_lookup($campaign_id);
		$email_norm = (string) ($identity['email_norm'] ?? '');
		$phone_norm = (string) ($identity['phone_norm'] ?? '');
		if (
			$email_norm !== ''
			&& isset($duplicate_lookup['email'][$email_norm])
			&& absint($duplicate_lookup['email'][$email_norm]) !== $recipient_id
		) {
			return new WP_Error('duplicate_recipient_email', __('This campaign already has a recipient with that email address.', 'backstage-outreach'));
		}
		if (
			$email_norm === ''
			&& $phone_norm !== ''
			&& isset($duplicate_lookup['phone'][$phone_norm])
			&& absint($duplicate_lookup['phone'][$phone_norm]) !== $recipient_id
		) {
			return new WP_Error('duplicate_recipient_phone', __('This campaign already has a recipient with that phone number.', 'backstage-outreach'));
		}

		$contact_id = absint($raw['contact_id'] ?? ($recipient['contact_id'] ?? 0));
		if ($contact_id > 0 && function_exists('vms_outreach_get_contact_by_id')) {
			$linked_contact = vms_outreach_get_contact_by_id($contact_id);
			if (!is_array($linked_contact)) {
				return new WP_Error('invalid_contact', __('The linked Outreach contact could not be found.', 'backstage-outreach'));
			}
		}

		$existing_token_id = is_array($recipient) ? absint($recipient['pass_token_id'] ?? 0) : 0;
		$existing_status = is_array($recipient) ? sanitize_key((string) ($recipient['status'] ?? 'ready')) : 'ready';
		$existing_invite_token = is_array($recipient) ? sanitize_text_field((string) ($recipient['invite_token'] ?? '')) : '';

		$pass_token_row = vms_pass_outreach_find_available_pass_token_row(
			absint($batch['id'] ?? 0),
			$recipient_id,
			$existing_token_id
		);

		if (!is_array($pass_token_row) && empty($recipient['claimed_at'] ?? '')) {
			return new WP_Error('no_available_campaign_token', __('No unclaimed guest pass tokens remain in the linked batch for outreach recipients.', 'backstage-outreach'));
		}

		$pass_token_id = is_array($pass_token_row) ? absint($pass_token_row['id'] ?? 0) : $existing_token_id;
		if ($pass_token_id <= 0) {
			return new WP_Error('invite_token_not_ready', __('This recipient does not have a reserved Guest Pass token.', 'backstage-outreach'));
		}

		$status = 'ready';
		if ($existing_status === 'draft') {
			$status = 'draft';
		}
		if (!empty($recipient['claimed_at'])) {
			$status = 'claimed';
		}
		if (!empty($recipient['revoked_at']) || $existing_status === 'revoked') {
			$status = 'revoked';
		}

		$send_state = vms_pass_outreach_recipient_send_state_overrides($recipient, $delivery_method);
		$stored_send_status = is_array($recipient) ? vms_pass_outreach_recipient_stored_send_status($recipient) : 'not_sent';
		$existing_last_send_error = is_array($recipient) ? sanitize_textarea_field((string) ($recipient['last_send_error'] ?? '')) : '';

		return array(
			'campaign_id' => $campaign_id,
			'pass_token_id' => $pass_token_id,
			'contact_id' => $contact_id > 0 ? $contact_id : null,
			'first_name' => $identity['first_name'] !== '' ? $identity['first_name'] : null,
			'last_name' => $identity['last_name'] !== '' ? $identity['last_name'] : null,
			'full_name' => $identity['full_name'] !== '' ? $identity['full_name'] : null,
			'email' => $identity['email'] !== '' ? $identity['email'] : null,
			'email_norm' => $identity['email_norm'] !== '' ? $identity['email_norm'] : null,
			'phone' => $identity['phone'] !== '' ? $identity['phone'] : null,
			'phone_norm' => $identity['phone_norm'] !== '' ? $identity['phone_norm'] : null,
			'company' => $identity['company'] !== '' ? $identity['company'] : null,
			'group_label' => $identity['group_label'] !== '' ? $identity['group_label'] : null,
			'notes' => $identity['notes'] !== '' ? $identity['notes'] : null,
			'invite_token' => $existing_invite_token !== '' ? $existing_invite_token : vms_pass_outreach_generate_unique_invite_token(),
			'send_method' => (string) ($send_state['send_method'] ?? ''),
			'expires_at' => $identity['expires_at'] !== '' ? $identity['expires_at'] : null,
			'status' => $status,
			'send_status' => isset($send_state['send_status']) ? (string) $send_state['send_status'] : $stored_send_status,
			'last_send_error' => array_key_exists('last_send_error', $send_state) ? (string) $send_state['last_send_error'] : $existing_last_send_error,
		);
	}
}

if (!function_exists('vms_pass_outreach_recipient_db_formats')) {
	function vms_pass_outreach_recipient_db_formats(array $data): array
	{
		$map = array(
			'campaign_id' => '%d',
			'pass_token_id' => '%d',
			'pass_claim_id' => '%d',
			'reservation_entry_id' => '%d',
			'contact_id' => '%d',
			'first_name' => '%s',
			'last_name' => '%s',
			'full_name' => '%s',
			'email' => '%s',
			'email_norm' => '%s',
			'phone' => '%s',
			'phone_norm' => '%s',
			'company' => '%s',
			'group_label' => '%s',
			'notes' => '%s',
			'invite_token' => '%s',
			'send_status' => '%s',
			'sent_at' => '%s',
			'sent_by' => '%d',
			'send_method' => '%s',
			'last_send_error' => '%s',
			'last_contacted_at' => '%s',
			'claimed_at' => '%s',
			'revoked_at' => '%s',
			'expires_at' => '%s',
			'status' => '%s',
			'claimed_headcount' => '%d',
			'created_by' => '%d',
			'created_at' => '%s',
			'updated_by' => '%d',
			'updated_at' => '%s',
		);

		$formats = array();
		foreach (array_keys($data) as $key) {
			$formats[] = $map[$key] ?? '%s';
		}
		return $formats;
	}
}

if (!function_exists('vms_pass_outreach_default_send_batch_size')) {
	function vms_pass_outreach_default_send_batch_size(): int
	{
		return max(1, (int) apply_filters('vms_pass_outreach_default_send_batch_size', 5));
	}
}

if (!function_exists('vms_pass_outreach_send_batch_cap')) {
	function vms_pass_outreach_send_batch_cap(): int
	{
		return max(1, (int) apply_filters('vms_pass_outreach_send_batch_cap', 10));
	}
}

if (!function_exists('vms_pass_outreach_recipient_resettable_status')) {
	function vms_pass_outreach_recipient_resettable_status(array $recipient): string
	{
		return sanitize_key((string) ($recipient['status'] ?? '')) === 'draft' ? 'draft' : 'ready';
	}
}

if (!function_exists('vms_pass_outreach_recipient_stored_send_status')) {
	function vms_pass_outreach_recipient_stored_send_status(array $recipient): string
	{
		$send_status = sanitize_key((string) ($recipient['send_status'] ?? ''));
		if (in_array($send_status, vms_pass_outreach_allowed_send_statuses(), true)) {
			return $send_status;
		}

		if (!empty($recipient['sent_at']) || sanitize_key((string) ($recipient['status'] ?? '')) === 'sent') {
			return 'sent';
		}

		return 'not_sent';
	}
}

if (!function_exists('vms_pass_outreach_send_status_from_guardrail')) {
	function vms_pass_outreach_send_status_from_guardrail(array $guardrail): string
	{
		if (empty($guardrail['blocked'])) {
			return '';
		}

		$reason_code = sanitize_key((string) ($guardrail['reason_code'] ?? ''));
		if ($reason_code === 'suppressed') {
			return 'suppressed';
		}
		if (in_array($reason_code, array('excluded', 'do_not_contact'), true)) {
			return 'do_not_contact';
		}

		return '';
	}
}

if (!function_exists('vms_pass_outreach_recipient_send_status_for_display')) {
	function vms_pass_outreach_recipient_send_status_for_display(array $recipient, ?array $guardrail = null): string
	{
		$stored_send_status = vms_pass_outreach_recipient_stored_send_status($recipient);
		if ($stored_send_status === 'do_not_contact') {
			return 'do_not_contact';
		}

		if (!is_array($guardrail)) {
			$guardrail = vms_pass_outreach_recipient_contact_guardrail_state($recipient);
		}

		$blocked_status = vms_pass_outreach_send_status_from_guardrail($guardrail);
		if ($blocked_status !== '') {
			return $blocked_status;
		}

		return vms_pass_outreach_recipient_stored_send_status($recipient);
	}
}

if (!function_exists('vms_pass_outreach_guardrail_skip_action')) {
	function vms_pass_outreach_guardrail_skip_action(array $guardrail): string
	{
		return vms_pass_outreach_send_status_from_guardrail($guardrail) === 'do_not_contact'
			? 'do_not_contact_skip'
			: 'suppressed_skip';
	}
}

if (!function_exists('vms_pass_outreach_send_method_label')) {
	function vms_pass_outreach_send_method_label(string $method): string
	{
		$method = sanitize_key($method);
		$labels = vms_pass_outreach_send_method_labels();
		return (string) ($labels[$method] ?? $method);
	}
}

if (!function_exists('vms_pass_outreach_send_batch_button_label')) {
	function vms_pass_outreach_send_batch_button_label(int $batch_size, int $queued_count = 0): string
	{
		if ($queued_count <= 0) {
			return __('Send Next Batch Now', 'backstage-outreach');
		}

		$count = max(1, $batch_size);
		$count = min($count, max(1, $queued_count));
		return sprintf(_n('Send Next %d Invite', 'Send Next %d Invites', $count, 'backstage-outreach'), $count);
	}
}

if (!function_exists('vms_pass_outreach_send_status_pill_html')) {
	function vms_pass_outreach_send_status_pill_html(string $send_status, string $tooltip = ''): string
	{
		$send_status = sanitize_key($send_status);
		$labels = vms_pass_outreach_send_status_labels();
		$label = (string) ($labels[$send_status] ?? $send_status);
		return vms_pass_outreach_named_status_pill_html($label, $send_status, $tooltip);
	}
}

if (!function_exists('vms_pass_outreach_named_status_pill_html')) {
	function vms_pass_outreach_named_status_pill_html(string $label, string $variant = 'draft', string $tooltip = ''): string
	{
		$label = sanitize_text_field($label);
		$variant = sanitize_html_class($variant !== '' ? $variant : 'draft');
		$tooltip = trim($tooltip);
		if ($tooltip === '') {
			return '<span class="vms-pass-status-pill is-' . esc_attr($variant) . '">' . esc_html($label) . '</span>';
		}

		static $popover_index = 0;
		$popover_index++;
		$tooltip_id = 'vms-pass-status-pill-popover-' . $popover_index;
		$tooltip_html = nl2br(esc_html($tooltip));

		return '<span class="vms-pass-help vms-pass-help--pill" data-vms-quick-popover="1"><button type="button" class="vms-pass-help__toggle vms-pass-status-pill is-' . esc_attr($variant) . ' has-detail" data-vms-quick-popover-toggle="1" aria-describedby="' . esc_attr($tooltip_id) . '" aria-expanded="false" aria-label="' . esc_attr(sprintf(__('Show details for %s', 'backstage-outreach'), $label)) . '">' . esc_html($label) . '</button><span id="' . esc_attr($tooltip_id) . '" class="screen-reader-text">' . esc_html(wp_strip_all_tags($tooltip)) . '</span><span class="vms-pass-help__popover-source" data-vms-quick-popover-content="1" aria-hidden="true">' . $tooltip_html . '</span></span>';
	}
}

if (!function_exists('vms_pass_outreach_recipient_claim_diagnostic')) {
	function vms_pass_outreach_recipient_claim_diagnostic(array $recipient, ?array $campaign = null): array
	{
		$campaign = is_array($campaign) ? $campaign : vms_pass_outreach_get_campaign_by_id(absint($recipient['campaign_id'] ?? 0));
		$claim_guardrail = is_array($campaign) && function_exists('vms_pass_outreach_campaign_claim_guardrail')
			? vms_pass_outreach_campaign_claim_guardrail($campaign)
			: array(
				'ok' => false,
				'reason_code' => 'campaign_missing',
				'admin_reasons' => array('Outreach campaign not found'),
				'batch' => null,
				'eligible_events' => array(),
			);
		$batch = is_array($claim_guardrail['batch'] ?? null)
			? (array) $claim_guardrail['batch']
			: (is_array($campaign) ? vms_pass_outreach_campaign_batch($campaign) : null);
		$token_row = vms_pass_outreach_get_pass_token_row_for_recipient($recipient);
		$token_status = sanitize_key((string) ($token_row['status'] ?? ''));
		$token_assigned = is_array($token_row)
			&& absint($recipient['pass_token_id'] ?? 0) === absint($token_row['id'] ?? 0);
		$eligible_events = is_array($claim_guardrail['eligible_events'] ?? null) ? (array) $claim_guardrail['eligible_events'] : array();
		$delivery_validation = vms_pass_outreach_validate_recipient_delivery($recipient, $campaign, $claim_guardrail);
		$reason_code = !empty($delivery_validation['ok']) ? '' : (string) ($delivery_validation['reason_code'] ?? 'claim_unavailable');
		$blocked_reason = !empty($delivery_validation['ok'])
			? ''
			: vms_pass_outreach_claim_guardrail_message($delivery_validation, __('This invite is not currently claimable.', 'backstage-outreach'));

		return array(
			'token_assigned' => $token_assigned,
			'token_status' => $token_status,
			'claimable' => ($reason_code === ''),
			'reason_code' => $reason_code,
			'blocked_reason' => $blocked_reason,
			'batch_status' => sanitize_key((string) ($batch['status'] ?? '')),
			'eligible_event_count' => count($eligible_events),
		);
	}
}

if (!function_exists('vms_pass_outreach_recipient_claim_display_state')) {
	function vms_pass_outreach_recipient_claim_display_state(array $recipient, ?array $campaign = null, ?array $checked_in_map = null): array
	{
		$status = vms_pass_outreach_recipient_status_for_display($recipient, $campaign, $checked_in_map);
		$claimed_at = trim((string) ($recipient['claimed_at'] ?? ''));
		$claimed_headcount = max(0, absint($recipient['claimed_headcount'] ?? 0));
		$admissions_per_recipient = max(1, absint($campaign['admissions_per_recipient'] ?? $claimed_headcount ?: 1));
		$checked_in_count = vms_pass_outreach_recipient_checked_in_count($recipient, $checked_in_map);
		$claim_diagnostic = vms_pass_outreach_recipient_claim_diagnostic($recipient, $campaign);

		$details = array();
		if ($claimed_at !== '') {
			$details[] = sprintf(__('Claimed: %s', 'backstage-outreach'), vms_pass_outreach_format_admin_datetime($claimed_at));
		}
		if ($claimed_headcount > 0) {
			$details[] = sprintf(__('Claimed Qty: %d', 'backstage-outreach'), $claimed_headcount);
		}
		$details[] = sprintf(__('Checked In: %d', 'backstage-outreach'), $checked_in_count);
		$details[] = sprintf(__('Token Assigned: %s', 'backstage-outreach'), !empty($claim_diagnostic['token_assigned']) ? __('Yes', 'backstage-outreach') : __('No', 'backstage-outreach'));
		$details[] = sprintf(__('Token Status: %s', 'backstage-outreach'), $claim_diagnostic['token_status'] !== '' ? (string) $claim_diagnostic['token_status'] : __('missing', 'backstage-outreach'));
		$details[] = sprintf(__('Claimable: %s', 'backstage-outreach'), !empty($claim_diagnostic['claimable']) ? __('Yes', 'backstage-outreach') : __('No', 'backstage-outreach'));
		$details[] = sprintf(__('Eligible Events: %d', 'backstage-outreach'), absint($claim_diagnostic['eligible_event_count'] ?? 0));
		if (!empty($claim_diagnostic['batch_status'])) {
			$details[] = sprintf(__('Batch Status: %s', 'backstage-outreach'), (string) $claim_diagnostic['batch_status']);
		}
		if (empty($claim_diagnostic['claimable']) && !empty($claim_diagnostic['blocked_reason'])) {
			$details[] = sprintf(__('Blocked: %s', 'backstage-outreach'), (string) $claim_diagnostic['blocked_reason']);
		}

		if ($claimed_at !== '') {
			$label = __('Claimed', 'backstage-outreach');
			$variant = 'sent';
			$key = 'claimed';

			if ($admissions_per_recipient > 1 && $claimed_headcount > 0 && $claimed_headcount < $admissions_per_recipient) {
				$label = __('Partially Claimed', 'backstage-outreach');
				$variant = 'partially_sent';
				$key = 'partially_claimed';
			}

			return array(
				'key' => $key,
				'label' => $label,
				'variant' => $variant,
				'tooltip' => implode("\n", array_filter($details)),
			);
		}

		if ($status === 'expired') {
			$details[] = __('Invite expired before claim.', 'backstage-outreach');
			return array(
				'key' => 'expired',
				'label' => __('Expired', 'backstage-outreach'),
				'variant' => 'failed',
				'tooltip' => implode("\n", array_filter($details)),
			);
		}

		if ($status === 'revoked') {
			$details[] = __('Invite was revoked before claim.', 'backstage-outreach');
			return array(
				'key' => 'revoked',
				'label' => __('Revoked', 'backstage-outreach'),
				'variant' => 'closed',
				'tooltip' => implode("\n", array_filter($details)),
			);
		}

		return array(
			'key' => 'not_claimed',
			'label' => __('Not Claimed', 'backstage-outreach'),
			'variant' => 'not_sent',
			'tooltip' => implode("\n", array_filter($details)),
		);
	}
}

if (!function_exists('vms_pass_outreach_analyze_recipients_for_delivery_actions')) {
	function vms_pass_outreach_analyze_recipients_for_delivery_actions(array $recipients, ?array $campaign = null): array
	{
		$campaign = is_array($campaign) ? $campaign : null;
		if ($campaign === null && !empty($recipients)) {
			$campaign = vms_pass_outreach_get_campaign_by_id(absint($recipients[0]['campaign_id'] ?? 0));
		}
		$claim_guardrail = is_array($campaign) && function_exists('vms_pass_outreach_campaign_claim_guardrail')
			? vms_pass_outreach_campaign_claim_guardrail($campaign)
			: array('ok' => false);
		$counts = array(
			'selected_total' => count($recipients),
			'queueable_unsent' => 0,
			'retryable_failed' => 0,
			'already_sent' => 0,
			'already_queued' => 0,
			'failed_selected' => 0,
			'validation_failed' => 0,
			'blocked' => 0,
			'other' => 0,
		);
		$queueable_unsent_ids = array();
		$retryable_failed_ids = array();
		$detail_messages = array();

		foreach ($recipients as $recipient) {
			$stored_send_status = vms_pass_outreach_recipient_stored_send_status($recipient);
			if (!empty($recipient['sent_at']) || $stored_send_status === 'sent') {
				$counts['already_sent'] += 1;
				continue;
			}
			if ($stored_send_status === 'queued') {
				$counts['already_queued'] += 1;
				continue;
			}
			if ($stored_send_status === 'failed') {
				$counts['failed_selected'] += 1;
			}
			if (!empty($recipient['revoked_at']) || sanitize_key((string) ($recipient['status'] ?? '')) === 'revoked') {
				$counts['blocked'] += 1;
				$detail_messages[] = __('Revoked recipients cannot be queued.', 'backstage-outreach');
				continue;
			}
			if (vms_pass_outreach_is_recipient_expired($recipient, $campaign)) {
				$counts['blocked'] += 1;
				$detail_messages[] = __('Expired recipients cannot be queued.', 'backstage-outreach');
				continue;
			}

			$guardrail = vms_pass_outreach_recipient_contact_guardrail_state($recipient);
			if (!empty($guardrail['blocked'])) {
				$counts['blocked'] += 1;
				$detail_messages[] = sprintf(
					__('Blocked recipient: %s.', 'backstage-outreach'),
					(string) ($guardrail['reason_label'] ?? __('Blocked', 'backstage-outreach'))
				);
				continue;
			}

			$delivery_validation = vms_pass_outreach_validate_recipient_delivery($recipient, $campaign, $claim_guardrail);
			if (empty($delivery_validation['ok'])) {
				$counts['validation_failed'] += 1;
				$detail_messages[] = vms_pass_outreach_claim_guardrail_message($delivery_validation, __('This outreach recipient is not ready to send claimable invite links.', 'backstage-outreach'));
				continue;
			}

			$email_delivery = vms_pass_outreach_recipient_email_delivery_validation($recipient);
			if (empty($email_delivery['ok'])) {
				$counts['validation_failed'] += 1;
				$detail_messages[] = (string) ($email_delivery['message'] ?? __('This outreach recipient is not ready for email delivery.', 'backstage-outreach'));
				continue;
			}

			if ($stored_send_status === 'failed') {
				$counts['retryable_failed'] += 1;
				$retryable_failed_ids[] = absint($recipient['id'] ?? 0);
				continue;
			}
			if ($stored_send_status === 'not_sent') {
				$counts['queueable_unsent'] += 1;
				$queueable_unsent_ids[] = absint($recipient['id'] ?? 0);
				continue;
			}

			$counts['other'] += 1;
		}

		$detail_messages = array_values(array_unique(array_filter(array_map('sanitize_text_field', $detail_messages))));

		return array(
			'counts' => $counts,
			'queueable_unsent_ids' => array_values(array_filter($queueable_unsent_ids)),
			'retryable_failed_ids' => array_values(array_filter($retryable_failed_ids)),
			'detail_messages' => $detail_messages,
		);
	}
}

if (!function_exists('vms_pass_outreach_pending_queue_selection_key')) {
	function vms_pass_outreach_pending_queue_selection_key(string $token): string
	{
		return 'vms_pass_outreach_pending_queue_selection_' . sanitize_key($token);
	}
}

if (!function_exists('vms_pass_outreach_store_pending_queue_selection')) {
	function vms_pass_outreach_store_pending_queue_selection(int $campaign_id, array $recipient_ids): string
	{
		$recipient_ids = array_values(array_filter(array_map('absint', $recipient_ids)));
		$token = wp_generate_password(20, false, false);
		set_transient(vms_pass_outreach_pending_queue_selection_key($token), array(
			'user_id' => get_current_user_id(),
			'campaign_id' => $campaign_id,
			'recipient_ids' => $recipient_ids,
		), HOUR_IN_SECONDS);

		return $token;
	}
}

if (!function_exists('vms_pass_outreach_get_pending_queue_selection')) {
	function vms_pass_outreach_get_pending_queue_selection(string $token, int $campaign_id): array
	{
		$stored = get_transient(vms_pass_outreach_pending_queue_selection_key($token));
		if (!is_array($stored)) {
			return array();
		}
		if (absint($stored['user_id'] ?? 0) !== get_current_user_id()) {
			return array();
		}
		if (absint($stored['campaign_id'] ?? 0) !== $campaign_id) {
			return array();
		}

		return array(
			'user_id' => absint($stored['user_id'] ?? 0),
			'campaign_id' => absint($stored['campaign_id'] ?? 0),
			'recipient_ids' => array_values(array_filter(array_map('absint', (array) ($stored['recipient_ids'] ?? array())))),
		);
	}
}

if (!function_exists('vms_pass_outreach_clear_pending_queue_selection')) {
	function vms_pass_outreach_clear_pending_queue_selection(string $token): void
	{
		delete_transient(vms_pass_outreach_pending_queue_selection_key($token));
	}
}

if (!function_exists('vms_pass_outreach_delivery_feedback_from_request')) {
	function vms_pass_outreach_delivery_feedback_from_request(int $campaign_id): array
	{
		$request_campaign_id = isset($_GET['campaign_id']) ? absint(wp_unslash($_GET['campaign_id'])) : 0;
		if ($campaign_id <= 0 || $request_campaign_id !== $campaign_id) {
			return array();
		}

		$notice = isset($_GET['delivery_notice']) ? sanitize_key((string) wp_unslash($_GET['delivery_notice'])) : '';
		$detail = isset($_GET['delivery_detail']) ? sanitize_text_field((string) wp_unslash($_GET['delivery_detail'])) : '';
		$activated = !empty($_GET['delivery_activated']);
		if ($notice === '') {
			return array();
		}

		switch ($notice) {
			case 'queue_selected':
			case 'queue_all_unsent':
			case 'retry_failed_selected':
				$affected = isset($_GET['delivery_affected']) ? absint(wp_unslash($_GET['delivery_affected'])) : 0;
				$skipped_sent = isset($_GET['delivery_skipped_sent']) ? absint(wp_unslash($_GET['delivery_skipped_sent'])) : 0;
				$skipped_queued = isset($_GET['delivery_skipped_queued']) ? absint(wp_unslash($_GET['delivery_skipped_queued'])) : 0;
				$skipped_failed = isset($_GET['delivery_skipped_failed']) ? absint(wp_unslash($_GET['delivery_skipped_failed'])) : 0;
				$validation_failed = isset($_GET['delivery_validation_failed']) ? absint(wp_unslash($_GET['delivery_validation_failed'])) : 0;
				$skipped_other = isset($_GET['delivery_skipped_other']) ? absint(wp_unslash($_GET['delivery_skipped_other'])) : 0;
				$summary_rows = array(
					array(
						'label' => __('Queued', 'backstage-outreach'),
						'value' => $affected,
					),
					array(
						'label' => __('Skipped already sent', 'backstage-outreach'),
						'value' => $skipped_sent,
					),
					array(
						'label' => __('Skipped already queued', 'backstage-outreach'),
						'value' => $skipped_queued,
					),
				);
				if ($notice === 'retry_failed_selected') {
					$summary_rows[] = array(
						'label' => __('Skipped not failed', 'backstage-outreach'),
						'value' => $skipped_failed,
					);
				} else {
					$summary_rows[] = array(
						'label' => __('Skipped failed invites', 'backstage-outreach'),
						'value' => $skipped_failed,
					);
				}
				$summary_rows[] = array(
					'label' => __('Failed validation', 'backstage-outreach'),
					'value' => $validation_failed,
				);
				if ($skipped_other > 0) {
					$summary_rows[] = array(
						'label' => __('Skipped other', 'backstage-outreach'),
						'value' => $skipped_other,
					);
				}
				if ($notice === 'queue_all_unsent') {
					$message = $affected > 0
						? __('Submitted all unsent recipients to the email queue.', 'backstage-outreach')
						: __('No unsent recipients were submitted to the email queue.', 'backstage-outreach');
				} elseif ($notice === 'retry_failed_selected') {
					$message = $affected > 0
						? __('Queued failed recipients for another send attempt.', 'backstage-outreach')
						: __('No failed recipients were queued for another send attempt.', 'backstage-outreach');
				} else {
					$message = $affected > 0
						? __('Submitted selected unsent recipients to the email queue.', 'backstage-outreach')
						: __('No selected recipients were submitted to the email queue.', 'backstage-outreach');
				}
				if ($detail !== '') {
					$message .= ' ' . $detail;
				}
				if ($activated) {
					$message = __('Campaign activated.', 'backstage-outreach') . ' ' . $message;
				}
				return array(
					'type' => $affected > 0 ? 'success' : 'error',
					'message' => $message,
					'summary_rows' => $summary_rows,
				);

			case 'send_batch':
				$sent = isset($_GET['delivery_sent']) ? absint(wp_unslash($_GET['delivery_sent'])) : 0;
				$skipped = isset($_GET['delivery_skipped']) ? absint(wp_unslash($_GET['delivery_skipped'])) : 0;
				$failed = isset($_GET['delivery_failed']) ? absint(wp_unslash($_GET['delivery_failed'])) : 0;
				return array(
					'type' => ($sent > 0 || ($failed === 0 && $skipped === 0)) ? 'success' : 'error',
					'message' => ($activated ? __('Campaign activated. ', 'backstage-outreach') : '') . sprintf(
						__('Batch complete. Sent: %1$d. Skipped: %2$d. Failed: %3$d.', 'backstage-outreach'),
						$sent,
						$skipped,
						$failed
					) . ($detail !== '' ? ' ' . $detail : ''),
					'summary_rows' => array(
						array(
							'label' => __('Sent', 'backstage-outreach'),
							'value' => $sent,
						),
						array(
							'label' => __('Skipped', 'backstage-outreach'),
							'value' => $skipped,
						),
						array(
							'label' => __('Failed', 'backstage-outreach'),
							'value' => $failed,
						),
					),
				);

			case 'send_none':
				return array(
					'type' => $detail !== '' ? 'error' : 'info',
					'message' => ($activated ? __('Campaign activated. ', 'backstage-outreach') : '') . ($detail !== '' ? $detail : __('No queued recipients were ready to send.', 'backstage-outreach')),
				);
		}

		return array();
	}
}

if (!function_exists('vms_pass_outreach_log_activity')) {
	function vms_pass_outreach_log_activity(array $context, string $action, string $result = 'success', string $message = '', array $extra = array()): void
	{
		if (!function_exists('vms_admission_audit_log')) {
			return;
		}

		$details = array_merge(
			array(
				'campaign_id' => absint($context['campaign_id'] ?? 0),
				'recipient_id' => absint($context['recipient_id'] ?? 0),
				'contact_id' => absint($context['contact_id'] ?? 0),
				'action' => sanitize_key($action),
				'result' => sanitize_key($result),
				'message' => sanitize_text_field($message),
			),
			$extra
		);

		vms_admission_audit_log(
			0,
			null,
			'pass_outreach_' . sanitize_key($action),
			get_current_user_id(),
			'admin',
			$details
		);
	}
}

if (!function_exists('vms_pass_outreach_prepare_campaign_for_delivery')) {
	function vms_pass_outreach_prepare_campaign_for_delivery(array $campaign, array $request = array())
	{
		$status = sanitize_key((string) ($campaign['status'] ?? 'draft'));
		if ($status === 'active') {
			return array(
				'campaign' => $campaign,
				'activated' => false,
			);
		}

		if ($status === 'draft' && !empty($request['activate_campaign'])) {
			$activated_campaign = function_exists('vms_pass_outreach_activate_campaign')
				? vms_pass_outreach_activate_campaign($campaign)
				: new WP_Error('campaign_activate_unavailable', __('Campaign activation is unavailable right now.', 'backstage-outreach'));
			if (is_wp_error($activated_campaign)) {
				return $activated_campaign;
			}

			return array(
				'campaign' => $activated_campaign,
				'activated' => true,
			);
		}

		$guardrail = function_exists('vms_pass_outreach_campaign_claim_guardrail')
			? vms_pass_outreach_campaign_claim_guardrail($campaign)
			: array(
				'ok' => false,
				'reason_code' => 'campaign_not_active',
				'admin_reasons' => array(vms_pass_outreach_draft_send_warning_message()),
			);
		return new WP_Error(
			(string) ($guardrail['reason_code'] ?? 'campaign_not_active'),
			vms_pass_outreach_claim_guardrail_message($guardrail, __('This outreach campaign is not ready to send claimable invite links.', 'backstage-outreach'))
		);
	}
}

if (!function_exists('vms_pass_outreach_result_detail_message')) {
	function vms_pass_outreach_result_detail_message(array $results, int $limit = 3): string
	{
		$messages = array();
		foreach ($results as $result) {
			$status = sanitize_key((string) ($result['status'] ?? ''));
			$message = sanitize_text_field((string) ($result['message'] ?? ''));
			if ($status === 'sent' || $message === '') {
				continue;
			}
			if (!in_array($message, $messages, true)) {
				$messages[] = $message;
			}
			if (count($messages) >= max(1, $limit)) {
				break;
			}
		}

		if (empty($messages)) {
			return '';
		}

		return sprintf(__('Details: %s', 'backstage-outreach'), implode(' ', $messages));
	}
}

if (!function_exists('vms_pass_outreach_update_recipient_row')) {
	function vms_pass_outreach_update_recipient_row(array $recipient, array $updates)
	{
		$recipient_id = absint($recipient['id'] ?? 0);
		if ($recipient_id <= 0) {
			return new WP_Error('invalid_recipient', __('The outreach recipient could not be found.', 'backstage-outreach'));
		}

		global $wpdb;
		$table = vms_pass_outreach_recipient_table();
		$now = function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql');

		if (!array_key_exists('updated_by', $updates)) {
			$updates['updated_by'] = get_current_user_id();
		}
		if (!array_key_exists('updated_at', $updates)) {
			$updates['updated_at'] = $now;
		}

		$result = $wpdb->update(
			$table,
			$updates,
			array('id' => $recipient_id),
			vms_pass_outreach_recipient_db_formats($updates),
			array('%d')
		);
		if ($result === false) {
			return new WP_Error('recipient_update_failed', __('Could not update the outreach recipient.', 'backstage-outreach'));
		}

		$updated = vms_pass_outreach_get_recipient_by_id($recipient_id);
		return is_array($updated) ? $updated : true;
	}
}

if (!function_exists('vms_pass_outreach_maybe_update_linked_contact_status')) {
	function vms_pass_outreach_maybe_update_linked_contact_status(array $recipient, string $target_status, array $allowed_current_statuses = array()): void
	{
		if (!function_exists('vms_outreach_save_contact')) {
			return;
		}

		$contact = vms_pass_outreach_recipient_linked_contact($recipient);
		if (!is_array($contact)) {
			return;
		}

		$current_status = sanitize_key((string) ($contact['status'] ?? ''));
		$target_status = sanitize_key($target_status);
		if ($target_status === '' || $current_status === $target_status) {
			return;
		}

		if (!empty($allowed_current_statuses) && !in_array($current_status, $allowed_current_statuses, true)) {
			return;
		}

		$payload = $contact;
		$payload['status'] = $target_status;
		$result = vms_outreach_save_contact($payload, get_current_user_id(), (int) ($contact['id'] ?? 0));
		if (is_wp_error($result)) {
			vms_pass_outreach_log_activity(
				array(
					'campaign_id' => absint($recipient['campaign_id'] ?? 0),
					'recipient_id' => absint($recipient['id'] ?? 0),
					'contact_id' => absint($contact['id'] ?? 0),
				),
				'contact_status_sync_failed',
				'error',
				$result->get_error_message(),
				array(
					'target_status' => $target_status,
					'current_status' => $current_status,
				)
			);
		}
	}
}

if (!function_exists('vms_pass_outreach_apply_guardrail_send_state')) {
	function vms_pass_outreach_apply_guardrail_send_state(array $recipient, array $guardrail, string $message = '')
	{
		$send_status = vms_pass_outreach_send_status_from_guardrail($guardrail);
		if ($send_status === '') {
			return true;
		}
		if (vms_pass_outreach_recipient_stored_send_status($recipient) === 'do_not_contact') {
			$send_status = 'do_not_contact';
		}

		$updates = array(
			'send_status' => $send_status,
			'last_send_error' => $message !== '' ? $message : (string) ($guardrail['reason_label'] ?? ''),
		);
		return vms_pass_outreach_update_recipient_row($recipient, $updates);
	}
}

if (!function_exists('vms_pass_outreach_recipient_can_mark_sent')) {
	function vms_pass_outreach_recipient_can_mark_sent(array $recipient): bool
	{
		if (!empty($recipient['claimed_at']) || !empty($recipient['revoked_at']) || vms_pass_outreach_is_recipient_expired($recipient)) {
			return false;
		}

		static $campaign_cache = array();
		static $claim_guardrail_cache = array();
		$campaign_id = absint($recipient['campaign_id'] ?? 0);
		if (!array_key_exists($campaign_id, $campaign_cache)) {
			$campaign_cache[$campaign_id] = vms_pass_outreach_get_campaign_by_id($campaign_id);
		}
		if (!array_key_exists($campaign_id, $claim_guardrail_cache)) {
			$campaign = $campaign_cache[$campaign_id];
			$claim_guardrail_cache[$campaign_id] = is_array($campaign) && function_exists('vms_pass_outreach_campaign_claim_guardrail')
				? vms_pass_outreach_campaign_claim_guardrail($campaign)
				: array('ok' => false);
		}

		$claim_guardrail = $claim_guardrail_cache[$campaign_id];
		$guardrail = vms_pass_outreach_recipient_contact_guardrail_state($recipient);
		if (!empty($guardrail['blocked'])) {
			return false;
		}

		if (empty(vms_pass_outreach_validate_recipient_delivery($recipient, is_array($campaign_cache[$campaign_id]) ? $campaign_cache[$campaign_id] : null, $claim_guardrail)['ok'])) {
			return false;
		}

		return in_array(vms_pass_outreach_recipient_stored_send_status($recipient), array('not_sent', 'queued', 'failed'), true);
	}
}

if (!function_exists('vms_pass_outreach_recipient_can_mark_not_sent')) {
	function vms_pass_outreach_recipient_can_mark_not_sent(array $recipient): bool
	{
		if (!empty($recipient['claimed_at']) || !empty($recipient['revoked_at'])) {
			return false;
		}

		$stored_status = vms_pass_outreach_recipient_stored_send_status($recipient);
		$guardrail = vms_pass_outreach_recipient_contact_guardrail_state($recipient);
		if (in_array($stored_status, array('suppressed', 'do_not_contact'), true) && !empty($guardrail['blocked'])) {
			return false;
		}

		return in_array($stored_status, array('queued', 'sent', 'failed', 'suppressed', 'do_not_contact'), true);
	}
}

if (!function_exists('vms_pass_outreach_recipient_can_queue')) {
	function vms_pass_outreach_recipient_can_queue(array $recipient): bool
	{
		if (!empty($recipient['claimed_at']) || !empty($recipient['revoked_at']) || vms_pass_outreach_is_recipient_expired($recipient)) {
			return false;
		}

		static $campaign_cache = array();
		static $claim_guardrail_cache = array();
		$campaign_id = absint($recipient['campaign_id'] ?? 0);
		if (!array_key_exists($campaign_id, $campaign_cache)) {
			$campaign_cache[$campaign_id] = vms_pass_outreach_get_campaign_by_id($campaign_id);
		}
		if (!array_key_exists($campaign_id, $claim_guardrail_cache)) {
			$campaign = $campaign_cache[$campaign_id];
			$claim_guardrail_cache[$campaign_id] = is_array($campaign) && function_exists('vms_pass_outreach_campaign_claim_guardrail')
				? vms_pass_outreach_campaign_claim_guardrail($campaign)
				: array('ok' => false);
		}

		$claim_guardrail = $claim_guardrail_cache[$campaign_id];
		$guardrail = vms_pass_outreach_recipient_contact_guardrail_state($recipient);
		if (!empty($guardrail['blocked'])) {
			return false;
		}

		if (empty(vms_pass_outreach_validate_recipient_delivery($recipient, is_array($campaign_cache[$campaign_id]) ? $campaign_cache[$campaign_id] : null, $claim_guardrail)['ok'])) {
			return false;
		}

		if (empty(vms_pass_outreach_recipient_email_delivery_validation($recipient)['ok'])) {
			return false;
		}

		return vms_pass_outreach_recipient_stored_send_status($recipient) === 'not_sent';
	}
}

if (!function_exists('vms_pass_outreach_recipient_can_retry_failed')) {
	function vms_pass_outreach_recipient_can_retry_failed(array $recipient): bool
	{
		if (!empty($recipient['claimed_at']) || !empty($recipient['revoked_at']) || vms_pass_outreach_is_recipient_expired($recipient)) {
			return false;
		}

		static $campaign_cache = array();
		static $claim_guardrail_cache = array();
		$campaign_id = absint($recipient['campaign_id'] ?? 0);
		if (!array_key_exists($campaign_id, $campaign_cache)) {
			$campaign_cache[$campaign_id] = vms_pass_outreach_get_campaign_by_id($campaign_id);
		}
		if (!array_key_exists($campaign_id, $claim_guardrail_cache)) {
			$campaign = $campaign_cache[$campaign_id];
			$claim_guardrail_cache[$campaign_id] = is_array($campaign) && function_exists('vms_pass_outreach_campaign_claim_guardrail')
				? vms_pass_outreach_campaign_claim_guardrail($campaign)
				: array('ok' => false);
		}

		$claim_guardrail = $claim_guardrail_cache[$campaign_id];
		$guardrail = vms_pass_outreach_recipient_contact_guardrail_state($recipient);
		if (!empty($guardrail['blocked'])) {
			return false;
		}

		if (empty(vms_pass_outreach_validate_recipient_delivery($recipient, is_array($campaign_cache[$campaign_id]) ? $campaign_cache[$campaign_id] : null, $claim_guardrail)['ok'])) {
			return false;
		}

		if (empty(vms_pass_outreach_recipient_email_delivery_validation($recipient)['ok'])) {
			return false;
		}

		return vms_pass_outreach_recipient_stored_send_status($recipient) === 'failed';
	}
}

if (!function_exists('vms_pass_outreach_recipient_can_mark_interested')) {
	function vms_pass_outreach_recipient_can_mark_interested(array $recipient): bool
	{
		$contact = vms_pass_outreach_recipient_linked_contact($recipient);
		if (!is_array($contact)) {
			return false;
		}

		$contact_status = sanitize_key((string) ($contact['status'] ?? ''));
		if (in_array($contact_status, array('interested', 'applied', 'excluded', 'do_not_contact'), true)) {
			return false;
		}

		$guardrail = vms_pass_outreach_recipient_contact_guardrail_state($recipient);
		return empty($guardrail['blocked']);
	}
}

if (!function_exists('vms_pass_outreach_recipient_can_suppress')) {
	function vms_pass_outreach_recipient_can_suppress(array $recipient): bool
	{
		$email = sanitize_email((string) ($recipient['email'] ?? ''));
		if (!is_email($email)) {
			return false;
		}

		$guardrail = vms_pass_outreach_recipient_contact_guardrail_state($recipient);
		$current_send_status = vms_pass_outreach_recipient_stored_send_status($recipient);
		return vms_pass_outreach_send_status_from_guardrail($guardrail) === '' && !in_array($current_send_status, array('suppressed', 'do_not_contact'), true);
	}
}

if (!function_exists('vms_pass_outreach_recipient_can_do_not_contact')) {
	function vms_pass_outreach_recipient_can_do_not_contact(array $recipient): bool
	{
		$email = sanitize_email((string) ($recipient['email'] ?? ''));
		if (!is_email($email)) {
			return false;
		}

		$contact = vms_pass_outreach_recipient_linked_contact($recipient);
		$contact_status = sanitize_key((string) ($contact['status'] ?? ''));
		return !in_array($contact_status, array('excluded', 'do_not_contact'), true)
			&& vms_pass_outreach_recipient_stored_send_status($recipient) !== 'do_not_contact';
	}
}

if (!function_exists('vms_pass_outreach_mark_recipient_sent')) {
	function vms_pass_outreach_mark_recipient_sent(array $recipient)
	{
		if (!vms_pass_outreach_recipient_can_mark_sent($recipient)) {
			$campaign = vms_pass_outreach_get_campaign_by_id(absint($recipient['campaign_id'] ?? 0));
			$delivery_validation = vms_pass_outreach_validate_recipient_delivery($recipient, $campaign);
			if (empty($delivery_validation['ok'])) {
				return new WP_Error(
					(string) ($delivery_validation['reason_code'] ?? 'recipient_cannot_mark_sent'),
					vms_pass_outreach_claim_guardrail_message($delivery_validation, __('This outreach campaign is not ready to mark invite links as sent.', 'backstage-outreach'))
				);
			}
			return new WP_Error('recipient_cannot_mark_sent', __('Only eligible outreach recipients can be marked as sent.', 'backstage-outreach'));
		}

		$now = function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql');
		$marked_send_method = in_array(vms_pass_outreach_recipient_delivery_method($recipient), array('manual_social', 'text_phone'), true)
			? vms_pass_outreach_recipient_delivery_method($recipient)
			: 'manual';
		$updated = vms_pass_outreach_update_recipient_row(
			$recipient,
			array(
				'send_status' => 'sent',
				'sent_at' => $now,
				'sent_by' => get_current_user_id(),
				'send_method' => $marked_send_method,
				'last_send_error' => '',
				'last_contacted_at' => $now,
			)
		);
		if (is_wp_error($updated)) {
			return new WP_Error('recipient_mark_sent_failed', __('Could not mark invite as sent.', 'backstage-outreach'));
		}

		vms_pass_outreach_maybe_update_linked_contact_status($recipient, 'contacted', array('new', 'needs_review', 'approved', 'maybe', 'queued'));
		vms_pass_outreach_log_activity(
			array(
				'campaign_id' => absint($recipient['campaign_id'] ?? 0),
				'recipient_id' => absint($recipient['id'] ?? 0),
				'contact_id' => absint($recipient['contact_id'] ?? 0),
			),
			'marked_sent',
			'success',
			__('Invite marked as sent.', 'backstage-outreach'),
			array(
				'send_method' => $marked_send_method,
			)
		);

		return $updated;
	}
}

if (!function_exists('vms_pass_outreach_mark_recipient_not_sent')) {
	function vms_pass_outreach_mark_recipient_not_sent(array $recipient)
	{
		if (!vms_pass_outreach_recipient_can_mark_not_sent($recipient)) {
			return new WP_Error('recipient_cannot_mark_not_sent', __('This outreach recipient cannot be reset to Not Sent right now.', 'backstage-outreach'));
		}

		$updates = array(
			'send_status' => 'not_sent',
			'sent_at' => null,
			'sent_by' => null,
			'send_method' => vms_pass_outreach_recipient_reset_send_method_value($recipient),
			'last_send_error' => '',
			'last_contacted_at' => null,
		);
		if (sanitize_key((string) ($recipient['status'] ?? '')) === 'sent') {
			$updates['status'] = vms_pass_outreach_recipient_resettable_status($recipient);
		}

		$updated = vms_pass_outreach_update_recipient_row($recipient, $updates);
		if (is_wp_error($updated)) {
			return new WP_Error('recipient_mark_not_sent_failed', __('Could not mark the outreach recipient as Not Sent.', 'backstage-outreach'));
		}

		vms_pass_outreach_log_activity(
			array(
				'campaign_id' => absint($recipient['campaign_id'] ?? 0),
				'recipient_id' => absint($recipient['id'] ?? 0),
				'contact_id' => absint($recipient['contact_id'] ?? 0),
			),
			'marked_not_sent',
			'success',
			__('Invite reset to Not Sent.', 'backstage-outreach')
		);

		return $updated;
	}
}

if (!function_exists('vms_pass_outreach_queue_recipient')) {
	function vms_pass_outreach_queue_recipient(array $recipient, array $args = array())
	{
		$allow_failed = !empty($args['allow_failed']);
		$campaign = vms_pass_outreach_get_campaign_by_id(absint($recipient['campaign_id'] ?? 0));
		$delivery_validation = vms_pass_outreach_validate_recipient_delivery($recipient, $campaign);
		if (empty($delivery_validation['ok'])) {
			return new WP_Error(
				(string) ($delivery_validation['reason_code'] ?? 'campaign_not_active'),
				vms_pass_outreach_claim_guardrail_message($delivery_validation, __('This outreach campaign is not ready to queue claimable invite links.', 'backstage-outreach'))
			);
		}

		$stored_status = vms_pass_outreach_recipient_stored_send_status($recipient);
		if ($stored_status === 'queued') {
			return new WP_Error('recipient_already_queued', __('This outreach recipient is already queued.', 'backstage-outreach'));
		}
		if ($stored_status === 'sent' || !empty($recipient['sent_at'])) {
			return new WP_Error('recipient_already_sent', __('Already-sent outreach recipients are not queued again without an explicit resend.', 'backstage-outreach'));
		}
		if ($stored_status === 'failed' && !$allow_failed) {
			return new WP_Error('recipient_retry_required', __('Failed outreach recipients use Retry Failed Invite instead of the normal queue action.', 'backstage-outreach'));
		}
		if (!empty($recipient['claimed_at']) || !empty($recipient['revoked_at']) || vms_pass_outreach_is_recipient_expired($recipient)) {
			return new WP_Error('recipient_queue_ineligible', __('This outreach recipient is not eligible to be queued.', 'backstage-outreach'));
		}
		if ($stored_status !== 'not_sent' && !($allow_failed && $stored_status === 'failed')) {
			return new WP_Error('recipient_queue_ineligible', __('This outreach recipient is not eligible to be queued.', 'backstage-outreach'));
		}

		$email_delivery = vms_pass_outreach_recipient_email_delivery_validation($recipient);
		if (empty($email_delivery['ok'])) {
			return new WP_Error(
				(string) ($email_delivery['code'] ?? 'recipient_delivery_not_email'),
				(string) ($email_delivery['message'] ?? __('This outreach recipient cannot be queued for email delivery.', 'backstage-outreach'))
			);
		}

		$guardrail = vms_pass_outreach_recipient_contact_guardrail_state($recipient);
		if (!empty($guardrail['blocked'])) {
			$message = sprintf(
				__('Skipped queueing because this recipient is blocked: %s.', 'backstage-outreach'),
				(string) ($guardrail['reason_label'] ?? __('Blocked', 'backstage-outreach'))
			);
			$guardrail_update = vms_pass_outreach_apply_guardrail_send_state($recipient, $guardrail, $message);
			vms_pass_outreach_log_activity(
				array(
					'campaign_id' => absint($recipient['campaign_id'] ?? 0),
					'recipient_id' => absint($recipient['id'] ?? 0),
					'contact_id' => absint($recipient['contact_id'] ?? 0),
				),
				vms_pass_outreach_guardrail_skip_action($guardrail),
				'skipped',
				$message
			);
			return is_wp_error($guardrail_update) ? $guardrail_update : new WP_Error('recipient_queue_blocked', $message);
		}

		$updated = vms_pass_outreach_update_recipient_row(
			$recipient,
			array(
				'send_status' => 'queued',
				'last_send_error' => '',
			)
		);
		if (is_wp_error($updated)) {
			return new WP_Error('recipient_queue_failed', __('Could not queue the outreach recipient.', 'backstage-outreach'));
		}

		vms_pass_outreach_log_activity(
			array(
				'campaign_id' => absint($recipient['campaign_id'] ?? 0),
				'recipient_id' => absint($recipient['id'] ?? 0),
				'contact_id' => absint($recipient['contact_id'] ?? 0),
			),
			$allow_failed ? 'retry_queued' : 'queued',
			'success',
			$allow_failed ? __('Failed recipient queued for another send attempt.', 'backstage-outreach') : __('Recipient queued for a later send.', 'backstage-outreach')
		);

		return $updated;
	}
}

if (!function_exists('vms_pass_outreach_mark_recipient_interested')) {
	function vms_pass_outreach_mark_recipient_interested(array $recipient)
	{
		if (!vms_pass_outreach_recipient_can_mark_interested($recipient)) {
			return new WP_Error('recipient_cannot_mark_interested', __('Only linked, contactable outreach recipients can be marked Interested.', 'backstage-outreach'));
		}

		vms_pass_outreach_maybe_update_linked_contact_status($recipient, 'interested', array('new', 'needs_review', 'approved', 'maybe', 'queued', 'contacted'));

		$now = function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql');
		$updates = array(
			'last_contacted_at' => $now,
			'last_send_error' => '',
		);
		if (vms_pass_outreach_recipient_stored_send_status($recipient) !== 'sent' || empty($recipient['sent_at'])) {
			$updates['send_status'] = 'sent';
			$updates['sent_at'] = $now;
			$updates['sent_by'] = get_current_user_id();
			$updates['send_method'] = 'manual';
		}

		$updated = vms_pass_outreach_update_recipient_row($recipient, $updates);
		if (is_wp_error($updated)) {
			return new WP_Error('recipient_mark_interested_failed', __('Could not mark the outreach recipient as Interested.', 'backstage-outreach'));
		}

		vms_pass_outreach_log_activity(
			array(
				'campaign_id' => absint($recipient['campaign_id'] ?? 0),
				'recipient_id' => absint($recipient['id'] ?? 0),
				'contact_id' => absint($recipient['contact_id'] ?? 0),
			),
			'marked_interested',
			'success',
			__('Linked contact marked Interested.', 'backstage-outreach')
		);

		return $updated;
	}
}

if (!function_exists('vms_pass_outreach_suppress_recipient')) {
	function vms_pass_outreach_suppress_recipient(array $recipient, string $reason = 'manual_admin')
	{
		if (!function_exists('vms_outreach_upsert_suppression')) {
			return new WP_Error('suppression_unavailable', __('Suppression management is unavailable.', 'backstage-outreach'));
		}

		$email = sanitize_email((string) ($recipient['email'] ?? ''));
		if (!is_email($email)) {
			return new WP_Error('recipient_email_invalid', __('A valid email address is required before suppressing this recipient.', 'backstage-outreach'));
		}

		$suppression = vms_outreach_upsert_suppression(
			array(
				'email' => $email,
				'reason' => sanitize_key($reason),
				'scope' => function_exists('vms_outreach_default_suppression_scope') ? vms_outreach_default_suppression_scope() : 'global_outreach',
				'source_contact_id' => absint($recipient['contact_id'] ?? 0),
				'source_campaign_id' => absint($recipient['campaign_id'] ?? 0),
				'source_label' => sprintf(
					__('Suppressed from outreach recipient #%d', 'backstage-outreach'),
					absint($recipient['id'] ?? 0)
				),
				'notes' => __('Created from Outreach recipient actions.', 'backstage-outreach'),
			),
			get_current_user_id()
		);
		if (is_wp_error($suppression)) {
			return $suppression;
		}

		$now = function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql');
		$updated = vms_pass_outreach_update_recipient_row(
			$recipient,
			array(
				'send_status' => 'suppressed',
				'last_send_error' => __('Recipient suppressed for future outreach sends.', 'backstage-outreach'),
				'last_contacted_at' => $now,
			)
		);
		if (is_wp_error($updated)) {
			return new WP_Error('recipient_suppress_failed', __('Could not update the outreach recipient after suppression.', 'backstage-outreach'));
		}

		vms_pass_outreach_log_activity(
			array(
				'campaign_id' => absint($recipient['campaign_id'] ?? 0),
				'recipient_id' => absint($recipient['id'] ?? 0),
				'contact_id' => absint($recipient['contact_id'] ?? 0),
			),
			'suppressed',
			'success',
			__('Recipient email suppressed for outreach.', 'backstage-outreach'),
			array(
				'suppression_reason' => sanitize_key($reason),
			)
		);

		return $updated;
	}
}

if (!function_exists('vms_pass_outreach_mark_recipient_do_not_contact')) {
	function vms_pass_outreach_mark_recipient_do_not_contact(array $recipient)
	{
		$email = sanitize_email((string) ($recipient['email'] ?? ''));
		if (!is_email($email)) {
			return new WP_Error('recipient_email_invalid', __('A valid email address is required before marking this recipient Do Not Contact.', 'backstage-outreach'));
		}

		$contact = vms_pass_outreach_recipient_linked_contact($recipient);
		if (is_array($contact) && function_exists('vms_outreach_save_contact')) {
			$payload = $contact;
			$payload['status'] = 'do_not_contact';
			$result = vms_outreach_save_contact($payload, get_current_user_id(), (int) ($contact['id'] ?? 0));
			if (is_wp_error($result)) {
				return $result;
			}
		} else {
			if (!function_exists('vms_outreach_upsert_suppression')) {
				return new WP_Error('suppression_unavailable', __('Suppression management is unavailable.', 'backstage-outreach'));
			}
			$suppression = vms_outreach_upsert_suppression(
				array(
					'email' => $email,
					'reason' => 'do_not_contact',
					'scope' => function_exists('vms_outreach_default_suppression_scope') ? vms_outreach_default_suppression_scope() : 'global_outreach',
					'source_contact_id' => absint($recipient['contact_id'] ?? 0),
					'source_campaign_id' => absint($recipient['campaign_id'] ?? 0),
					'source_label' => sprintf(
						__('Do Not Contact from outreach recipient #%d', 'backstage-outreach'),
						absint($recipient['id'] ?? 0)
					),
					'notes' => __('Created from Outreach recipient Do Not Contact action.', 'backstage-outreach'),
				),
				get_current_user_id()
			);
			if (is_wp_error($suppression)) {
				return $suppression;
			}
		}

		$now = function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql');
		$updated = vms_pass_outreach_update_recipient_row(
			$recipient,
			array(
				'send_status' => 'do_not_contact',
				'last_send_error' => __('Recipient marked Do Not Contact.', 'backstage-outreach'),
				'last_contacted_at' => $now,
			)
		);
		if (is_wp_error($updated)) {
			return new WP_Error('recipient_do_not_contact_failed', __('Could not update the outreach recipient to Do Not Contact.', 'backstage-outreach'));
		}

		vms_pass_outreach_log_activity(
			array(
				'campaign_id' => absint($recipient['campaign_id'] ?? 0),
				'recipient_id' => absint($recipient['id'] ?? 0),
				'contact_id' => absint($recipient['contact_id'] ?? 0),
			),
			'do_not_contact',
			'success',
			__('Recipient marked Do Not Contact.', 'backstage-outreach')
		);

		return $updated;
	}
}

if (!function_exists('vms_pass_outreach_mark_recipient_failed_send')) {
	function vms_pass_outreach_mark_recipient_failed_send(array $recipient, string $message)
	{
		$updated = vms_pass_outreach_update_recipient_row(
			$recipient,
			array(
				'send_status' => 'failed',
				'last_send_error' => $message,
			)
		);
		vms_pass_outreach_log_activity(
			array(
				'campaign_id' => absint($recipient['campaign_id'] ?? 0),
				'recipient_id' => absint($recipient['id'] ?? 0),
				'contact_id' => absint($recipient['contact_id'] ?? 0),
			),
			'failed',
			is_wp_error($updated) ? 'error' : 'failed',
			$message
		);
		return $updated;
	}
}

if (!function_exists('vms_pass_outreach_attempt_send_invite_email')) {
	function vms_pass_outreach_attempt_send_invite_email(array $recipient, ?array $campaign = null, array $args = array()): array
	{
		$campaign = is_array($campaign) ? $campaign : vms_pass_outreach_get_campaign_by_id(absint($recipient['campaign_id'] ?? 0));
		if (!is_array($campaign)) {
			return array(
				'status' => 'failed',
				'code' => 'campaign_missing',
				'message' => __('Outreach campaign not found.', 'backstage-outreach'),
				'recipient_id' => absint($recipient['id'] ?? 0),
			);
		}

		$delivery_validation = vms_pass_outreach_validate_recipient_delivery($recipient, $campaign);
		if (empty($delivery_validation['ok'])) {
			$message = vms_pass_outreach_claim_guardrail_message($delivery_validation, __('This outreach campaign is not ready to send claimable invite links.', 'backstage-outreach'));
			vms_pass_outreach_log_activity(
				array(
					'campaign_id' => absint($recipient['campaign_id'] ?? 0),
					'recipient_id' => absint($recipient['id'] ?? 0),
					'contact_id' => absint($recipient['contact_id'] ?? 0),
				),
				'send_skipped_delivery_validation',
				'skipped',
				$message,
				array(
					'reason_code' => (string) ($delivery_validation['reason_code'] ?? 'campaign_not_active'),
				)
			);
			return array(
				'status' => 'skipped',
				'code' => (string) ($delivery_validation['reason_code'] ?? 'campaign_not_active'),
				'message' => $message,
				'recipient_id' => absint($recipient['id'] ?? 0),
			);
		}

		$resend = !empty($args['resend']);
		$email_delivery = vms_pass_outreach_recipient_email_delivery_validation($recipient);
		if (empty($email_delivery['ok'])) {
			$message = (string) ($email_delivery['message'] ?? __('A valid email address is required before sending this invite.', 'backstage-outreach'));
			if (($email_delivery['code'] ?? '') === 'recipient_email_required') {
				vms_pass_outreach_mark_recipient_failed_send($recipient, $message);
			} elseif (vms_pass_outreach_recipient_stored_send_status($recipient) === 'queued') {
				vms_pass_outreach_update_recipient_row($recipient, array(
					'send_status' => 'not_sent',
					'last_send_error' => $message,
				));
			}
			return array(
				'status' => ($email_delivery['code'] ?? '') === 'recipient_email_required' ? 'failed' : 'skipped',
				'code' => (string) ($email_delivery['code'] ?? 'invalid_email'),
				'message' => $message,
				'recipient_id' => absint($recipient['id'] ?? 0),
			);
		}
		$email = sanitize_email((string) ($email_delivery['email'] ?? ''));

		if (!empty($recipient['revoked_at']) || sanitize_key((string) ($recipient['status'] ?? '')) === 'revoked') {
			return array(
				'status' => 'skipped',
				'code' => 'revoked',
				'message' => __('Revoked outreach recipients are skipped.', 'backstage-outreach'),
				'recipient_id' => absint($recipient['id'] ?? 0),
			);
		}

		if (vms_pass_outreach_is_recipient_expired($recipient, $campaign)) {
			return array(
				'status' => 'skipped',
				'code' => 'expired',
				'message' => __('Expired outreach recipients are skipped.', 'backstage-outreach'),
				'recipient_id' => absint($recipient['id'] ?? 0),
			);
		}

		$guardrail = vms_pass_outreach_recipient_contact_guardrail_state($recipient);
		if (!empty($guardrail['blocked'])) {
			$message = sprintf(
				__('Skipped sending because this recipient is blocked: %s.', 'backstage-outreach'),
				(string) ($guardrail['reason_label'] ?? __('Blocked', 'backstage-outreach'))
			);
			vms_pass_outreach_apply_guardrail_send_state($recipient, $guardrail, $message);
			vms_pass_outreach_log_activity(
				array(
					'campaign_id' => absint($recipient['campaign_id'] ?? 0),
					'recipient_id' => absint($recipient['id'] ?? 0),
					'contact_id' => absint($recipient['contact_id'] ?? 0),
				),
				vms_pass_outreach_guardrail_skip_action($guardrail),
				'skipped',
				$message
			);
			return array(
				'status' => 'skipped',
				'code' => 'guardrail_blocked',
				'message' => $message,
				'recipient_id' => absint($recipient['id'] ?? 0),
			);
		}

		if (!$resend && (vms_pass_outreach_recipient_stored_send_status($recipient) === 'sent' || !empty($recipient['sent_at']))) {
			return array(
				'status' => 'skipped',
				'code' => 'already_sent',
				'message' => __('This outreach recipient was already sent. Confirm resend to send again.', 'backstage-outreach'),
				'recipient_id' => absint($recipient['id'] ?? 0),
			);
		}

		$subject = vms_pass_outreach_build_invite_subject($recipient, $campaign);
		$body = vms_pass_outreach_build_invite_message($recipient, $campaign);
		$from_email = sanitize_email((string) get_option('admin_email'));
		$site_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
		$headers = array();
		if ($from_email !== '') {
			$headers[] = 'From: ' . $site_name . ' <' . $from_email . '>';
			$headers[] = 'Reply-To: ' . $from_email;
		}

		$mail_error = '';
		$mail_capture = static function ($wp_error) use (&$mail_error): void {
			if (is_wp_error($wp_error)) {
				$mail_error = $wp_error->get_error_message();
			}
		};
		add_action('wp_mail_failed', $mail_capture, 10, 1);
		$sent = wp_mail($email, $subject, $body, $headers);
		remove_action('wp_mail_failed', $mail_capture, 10);

		if (!$sent) {
			$message = $mail_error !== '' ? $mail_error : __('WordPress did not accept the outreach email for delivery.', 'backstage-outreach');
			vms_pass_outreach_mark_recipient_failed_send($recipient, $message);
			return array(
				'status' => 'failed',
				'code' => 'wp_mail_failed',
				'message' => $message,
				'recipient_id' => absint($recipient['id'] ?? 0),
			);
		}

		$now = function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql');
		$updated = vms_pass_outreach_update_recipient_row(
			$recipient,
			array(
				'send_status' => 'sent',
				'sent_at' => $now,
				'sent_by' => get_current_user_id(),
				'send_method' => 'vms_email',
				'last_send_error' => '',
				'last_contacted_at' => $now,
			)
		);

		vms_pass_outreach_maybe_update_linked_contact_status($recipient, 'contacted', array('new', 'needs_review', 'approved', 'maybe', 'queued'));
		vms_pass_outreach_log_activity(
			array(
				'campaign_id' => absint($recipient['campaign_id'] ?? 0),
				'recipient_id' => absint($recipient['id'] ?? 0),
				'contact_id' => absint($recipient['contact_id'] ?? 0),
			),
			'sent',
			is_wp_error($updated) ? 'error' : 'success',
			__('Invite email sent from VMS.', 'backstage-outreach'),
			array(
				'send_method' => 'vms_email',
				'resend' => $resend ? 1 : 0,
			)
		);

		return array(
			'status' => is_wp_error($updated) ? 'failed' : 'sent',
			'code' => is_wp_error($updated) ? 'recipient_update_failed' : 'sent',
			'message' => is_wp_error($updated) ? $updated->get_error_message() : __('Invite email sent.', 'backstage-outreach'),
			'recipient_id' => absint($recipient['id'] ?? 0),
		);
	}
}

if (!function_exists('vms_pass_outreach_get_queued_recipients_for_campaign')) {
	function vms_pass_outreach_get_queued_recipients_for_campaign(int $campaign_id, int $limit = 5): array
	{
		$campaign_id = absint($campaign_id);
		$limit = max(1, min(vms_pass_outreach_send_batch_cap(), absint($limit)));
		if ($campaign_id <= 0) {
			return array();
		}

		global $wpdb;
		$table = vms_pass_outreach_recipient_table();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				FROM {$table}
				WHERE campaign_id = %d
					AND send_status = %s
				ORDER BY COALESCE(updated_at, created_at) ASC, id ASC
				LIMIT %d",
				$campaign_id,
				'queued',
				$limit
			),
			ARRAY_A
		);

		return array_map('vms_pass_outreach_normalize_recipient_row', (array) $rows);
	}
}

if (!function_exists('vms_pass_outreach_send_next_queued_recipients')) {
	function vms_pass_outreach_send_next_queued_recipients(array $campaign, int $requested_batch_size = 0): array
	{
		$batch_cap = vms_pass_outreach_send_batch_cap();
		$batch_size = $requested_batch_size > 0 ? min($batch_cap, $requested_batch_size) : min($batch_cap, vms_pass_outreach_default_send_batch_size());
		$claim_guardrail = function_exists('vms_pass_outreach_campaign_claim_guardrail')
			? vms_pass_outreach_campaign_claim_guardrail($campaign)
			: array('ok' => true);
		if (empty($claim_guardrail['ok'])) {
			return array(
				'batch_size' => $batch_size,
				'sent' => 0,
				'skipped' => 0,
				'failed' => 0,
				'queued_found' => 0,
				'results' => array(),
				'error_message' => vms_pass_outreach_claim_guardrail_message($claim_guardrail, __('This outreach campaign is not ready to send claimable invite links.', 'backstage-outreach')),
				'reason_code' => (string) ($claim_guardrail['reason_code'] ?? 'campaign_not_active'),
			);
		}

		$recipients = vms_pass_outreach_get_queued_recipients_for_campaign(absint($campaign['id'] ?? 0), $batch_size);

		$summary = array(
			'batch_size' => $batch_size,
			'sent' => 0,
			'skipped' => 0,
			'failed' => 0,
			'queued_found' => count($recipients),
			'results' => array(),
		);

		foreach ($recipients as $recipient) {
			$result = vms_pass_outreach_attempt_send_invite_email($recipient, $campaign, array('resend' => false));
			$summary['results'][] = $result;
			if (($result['status'] ?? '') === 'sent') {
				$summary['sent'] += 1;
			} elseif (($result['status'] ?? '') === 'failed') {
				$summary['failed'] += 1;
			} else {
				$summary['skipped'] += 1;
			}
		}

		vms_pass_outreach_log_activity(
			array(
				'campaign_id' => absint($campaign['id'] ?? 0),
			),
			'batch_send',
			'success',
			__('Queued send run completed.', 'backstage-outreach'),
			array(
				'batch_size' => $batch_size,
				'sent_count' => $summary['sent'],
				'skipped_count' => $summary['skipped'],
				'failed_count' => $summary['failed'],
			)
		);

		return $summary;
	}
}

if (!function_exists('vms_pass_outreach_recipient_can_revoke')) {
	function vms_pass_outreach_recipient_can_revoke(array $recipient): bool
	{
		return empty($recipient['claimed_at']) && empty($recipient['revoked_at']) && sanitize_key((string) ($recipient['status'] ?? 'ready')) !== 'revoked';
	}
}

if (!function_exists('vms_pass_outreach_recipient_can_delete')) {
	function vms_pass_outreach_recipient_can_delete(array $recipient): bool
	{
		return empty($recipient['claimed_at']);
	}
}

if (!function_exists('vms_pass_outreach_mark_recipient_sent')) {
	function vms_pass_outreach_mark_recipient_sent(array $recipient)
	{
		if (!vms_pass_outreach_recipient_can_mark_sent($recipient)) {
			return new WP_Error('recipient_cannot_mark_sent', __('Only draft or ready outreach recipients can be marked as sent.', 'backstage-outreach'));
		}

		global $wpdb;
		$table = vms_pass_outreach_recipient_table();
		$recipient_id = absint($recipient['id'] ?? 0);
		$now = function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql');
		$result = $wpdb->update(
			$table,
			array(
				'sent_at' => $now,
				'status' => 'sent',
				'updated_by' => get_current_user_id(),
				'updated_at' => $now,
			),
			array('id' => $recipient_id),
			array('%s', '%s', '%d', '%s'),
			array('%d')
		);
		if ($result === false) {
			return new WP_Error('recipient_mark_sent_failed', __('Could not mark invite as sent.', 'backstage-outreach'));
		}

		if (function_exists('vms_admission_audit_log')) {
			vms_admission_audit_log(0, null, 'pass_outreach_recipient_mark_sent', get_current_user_id(), 'admin', array(
				'campaign_id' => absint($recipient['campaign_id'] ?? 0),
				'recipient_id' => $recipient_id,
			));
		}

		return true;
	}
}

if (!function_exists('vms_pass_outreach_revoke_recipient')) {
	function vms_pass_outreach_revoke_recipient(array $recipient)
	{
		if (!vms_pass_outreach_recipient_can_revoke($recipient)) {
			return new WP_Error('recipient_cannot_revoke', __('Claimed outreach recipients cannot be revoked.', 'backstage-outreach'));
		}

		global $wpdb;
		$table = vms_pass_outreach_recipient_table();
		$recipient_id = absint($recipient['id'] ?? 0);
		$now = function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql');
		$result = $wpdb->update(
			$table,
			array(
				'status' => 'revoked',
				'revoked_at' => $now,
				'updated_by' => get_current_user_id(),
				'updated_at' => $now,
			),
			array('id' => $recipient_id),
			array('%s', '%s', '%d', '%s'),
			array('%d')
		);
		if ($result === false) {
			return new WP_Error('recipient_revoke_failed', __('Could not revoke outreach recipient.', 'backstage-outreach'));
		}

		if (function_exists('vms_admission_audit_log')) {
			vms_admission_audit_log(0, null, 'pass_outreach_recipient_revoke', get_current_user_id(), 'admin', array(
				'campaign_id' => absint($recipient['campaign_id'] ?? 0),
				'recipient_id' => $recipient_id,
			));
		}

		return true;
	}
}

if (!function_exists('vms_pass_outreach_delete_recipient')) {
	function vms_pass_outreach_delete_recipient(array $recipient)
	{
		if (!vms_pass_outreach_recipient_can_delete($recipient)) {
			return new WP_Error('recipient_cannot_delete', __('Claimed outreach recipients cannot be deleted.', 'backstage-outreach'));
		}

		global $wpdb;
		$table = vms_pass_outreach_recipient_table();
		$recipient_id = absint($recipient['id'] ?? 0);
		$result = $wpdb->delete($table, array('id' => $recipient_id), array('%d'));
		if ($result === false) {
			return new WP_Error('recipient_delete_failed', __('Could not delete outreach recipient.', 'backstage-outreach'));
		}

		if (function_exists('vms_admission_audit_log')) {
			vms_admission_audit_log(0, null, 'pass_outreach_recipient_delete', get_current_user_id(), 'admin', array(
				'campaign_id' => absint($recipient['campaign_id'] ?? 0),
				'recipient_id' => $recipient_id,
				'pass_token_id' => absint($recipient['pass_token_id'] ?? 0),
			));
		}

		return true;
	}
}

if (!function_exists('vms_pass_outreach_campaign_summary')) {
	function vms_pass_outreach_campaign_summary(array $campaign): array
	{
		$recipients = vms_pass_outreach_query_recipients_for_campaign(absint($campaign['id'] ?? 0), array(
			'limit' => 0,
		));
		$checked_in_map = vms_pass_outreach_checked_in_map_for_recipients($recipients);

		$summary = array(
			'total_recipients' => count($recipients),
			'ready_recipients' => 0,
			'queued_recipients' => 0,
			'sent_recipients' => 0,
			'failed_recipients' => 0,
			'claimed_recipients' => 0,
			'revoked_recipients' => 0,
			'expired_recipients' => 0,
			'admissions_claimed' => 0,
			'admissions_checked_in' => 0,
			'unused_claimed_admissions' => 0,
			'claim_rate' => null,
			'attendance_rate' => null,
			'checked_in_map' => $checked_in_map,
			'recipients' => $recipients,
		);

		foreach ($recipients as $recipient) {
			$status = vms_pass_outreach_recipient_status_for_display($recipient, $campaign, $checked_in_map);
			$guardrail = vms_pass_outreach_recipient_contact_guardrail_state($recipient);
			$send_status = vms_pass_outreach_recipient_send_status_for_display($recipient, $guardrail);
			$claimed_headcount = max(0, absint($recipient['claimed_headcount'] ?? 0));
			$checked_in_count = vms_pass_outreach_recipient_checked_in_count($recipient, $checked_in_map);

			if (in_array($status, array('ready', 'draft'), true) && $send_status === 'not_sent') {
				$summary['ready_recipients'] += 1;
			}
			if ($send_status === 'queued' && empty($recipient['sent_at']) && empty($recipient['claimed_at']) && empty($recipient['revoked_at']) && $status !== 'expired') {
				$summary['queued_recipients'] += 1;
			}
			if ($send_status === 'failed' && empty($recipient['sent_at']) && empty($recipient['claimed_at']) && empty($recipient['revoked_at'])) {
				$summary['failed_recipients'] += 1;
			}
			if (in_array($status, array('sent', 'claimed', 'partially_used', 'used'), true) || !empty($recipient['sent_at'])) {
				$summary['sent_recipients'] += 1;
			}
			if (in_array($status, array('claimed', 'partially_used', 'used'), true)) {
				$summary['claimed_recipients'] += 1;
				$summary['admissions_claimed'] += $claimed_headcount;
			}
			if ($status === 'revoked') {
				$summary['revoked_recipients'] += 1;
			}
			if ($status === 'expired') {
				$summary['expired_recipients'] += 1;
			}

			$summary['admissions_checked_in'] += $checked_in_count;
		}

		$summary['unused_claimed_admissions'] = max(0, $summary['admissions_claimed'] - $summary['admissions_checked_in']);
		if ($summary['sent_recipients'] > 0) {
			$summary['claim_rate'] = $summary['claimed_recipients'] / $summary['sent_recipients'];
		}
		if ($summary['admissions_claimed'] > 0) {
			$summary['attendance_rate'] = $summary['admissions_checked_in'] / $summary['admissions_claimed'];
		}

		return $summary;
	}
}

if (!function_exists('vms_pass_outreach_campaign_counts_line')) {
	function vms_pass_outreach_campaign_counts_line(array $summary, array $args = array()): string
	{
		$total = max(0, absint($summary['total_recipients'] ?? 0));
		$ready = max(0, absint($summary['ready_recipients'] ?? 0));
		$queued = max(0, absint($summary['queued_recipients'] ?? 0));
		$sent = max(0, absint($summary['sent_recipients'] ?? 0));
		$failed = max(0, absint($summary['failed_recipients'] ?? 0));
		$claimed = max(0, absint($summary['claimed_recipients'] ?? 0));
		$checked_in = max(0, absint($summary['admissions_checked_in'] ?? 0));
		$include_claimed = !empty($args['include_claimed']) || $claimed > 0;
		$include_checked_in = !empty($args['include_checked_in']) || $checked_in > 0;

		$parts = array(
			sprintf(_n('%d recipient', '%d recipients', $total, 'backstage-outreach'), $total),
			sprintf(_n('%d ready', '%d ready', $ready, 'backstage-outreach'), $ready),
			sprintf(_n('%d queued', '%d queued', $queued, 'backstage-outreach'), $queued),
			sprintf(_n('%d sent', '%d sent', $sent, 'backstage-outreach'), $sent),
			sprintf(_n('%d failed', '%d failed', $failed, 'backstage-outreach'), $failed),
		);

		if ($include_claimed) {
			$parts[] = sprintf(_n('%d claimed', '%d claimed', $claimed, 'backstage-outreach'), $claimed);
		}
		if ($include_checked_in) {
			$parts[] = sprintf(_n('%d checked in', '%d checked in', $checked_in, 'backstage-outreach'), $checked_in);
		}

		return implode(' · ', $parts);
	}
}

if (!function_exists('vms_pass_outreach_campaign_total_admission_capacity')) {
	function vms_pass_outreach_campaign_total_admission_capacity(array $campaign, ?array $summary = null): int
	{
		$total_cap = max(0, absint($campaign['total_admission_cap'] ?? 0));
		if ($total_cap > 0) {
			return $total_cap;
		}

		if (!is_array($summary)) {
			$summary = array();
		}
		$total_recipients = max(0, absint($summary['total_recipients'] ?? 0));
		$admissions_per_recipient = max(1, absint($campaign['admissions_per_recipient'] ?? 1));
		return $total_recipients * $admissions_per_recipient;
	}
}

if (!function_exists('vms_pass_outreach_campaign_results_line')) {
	function vms_pass_outreach_campaign_results_line(array $campaign, array $summary, array $args = array()): string
	{
		$claimed_recipients = max(0, absint($summary['claimed_recipients'] ?? 0));
		$claimed_admissions = max(0, absint($summary['admissions_claimed'] ?? 0));
		$checked_in = max(0, absint($summary['admissions_checked_in'] ?? 0));
		$total_admissions = vms_pass_outreach_campaign_total_admission_capacity($campaign, $summary);
		$include_total_admissions = !array_key_exists('include_total_admissions', $args) || !empty($args['include_total_admissions']);

		$parts = array(
			sprintf(_n('%d claimed', '%d claimed', $claimed_recipients, 'backstage-outreach'), $claimed_recipients),
			sprintf(_n('%d admissions claimed', '%d admissions claimed', $claimed_admissions, 'backstage-outreach'), $claimed_admissions),
			sprintf(_n('%d checked in', '%d checked in', $checked_in, 'backstage-outreach'), $checked_in),
		);

		if ($include_total_admissions) {
			$parts[] = sprintf(_n('%d total admission', '%d total admissions', $total_admissions, 'backstage-outreach'), $total_admissions);
		}

		return implode(' · ', $parts);
	}
}

if (!function_exists('vms_pass_outreach_recipient_redirect_url')) {
	function vms_pass_outreach_recipient_redirect_url(int $campaign_id, int $recipient_id = 0, string $anchor = 'vms-outreach-recipients'): string
	{
		$args = array(
			'campaign_id' => $campaign_id,
		);
		if ($recipient_id > 0) {
			$args['recipient_id'] = $recipient_id;
		}
		$url = function_exists('vms_pass_outreach_admin_page_url')
			? vms_pass_outreach_admin_page_url($args)
			: admin_url('admin.php?page=vms-passes');

		$anchor = preg_replace('/[^A-Za-z0-9_-]/', '', $anchor);
		if ($anchor !== '') {
			$url .= '#' . $anchor;
		}

		return $url;
	}
}

if (!function_exists('vms_pass_outreach_recipient_feedback_redirect_url')) {
	function vms_pass_outreach_recipient_feedback_redirect_url(int $campaign_id, array $query_args = array(), int $recipient_id = 0, string $anchor = 'vms-outreach-delivery-status'): string
	{
		$url = vms_pass_outreach_recipient_redirect_url($campaign_id, $recipient_id, $anchor);
		if (empty($query_args)) {
			return $url;
		}

		return add_query_arg($query_args, $url);
	}
}

if (!function_exists('vms_pass_outreach_activate_campaign_url')) {
	function vms_pass_outreach_activate_campaign_url(int $campaign_id, int $recipient_id = 0, string $anchor = 'vms-outreach-delivery-status'): string
	{
		$args = array(
			'action' => 'vms_pass_outreach_activate_campaign',
			'campaign_id' => $campaign_id,
		);
		if ($recipient_id > 0) {
			$args['recipient_id'] = $recipient_id;
		}
		if ($anchor !== '') {
			$args['return_anchor'] = preg_replace('/[^A-Za-z0-9_-]/', '', $anchor);
		}

		return wp_nonce_url(
			add_query_arg($args, admin_url('admin-post.php')),
			'vms_pass_outreach_activate_campaign_' . $campaign_id
		);
	}
}

if (!function_exists('vms_pass_outreach_csv_header_aliases')) {
	function vms_pass_outreach_csv_header_aliases(): array
	{
		return array(
			'first_name' => 'first_name',
			'firstname' => 'first_name',
			'first' => 'first_name',
			'given_name' => 'first_name',
			'last_name' => 'last_name',
			'lastname' => 'last_name',
			'last' => 'last_name',
			'surname' => 'last_name',
			'name' => 'name',
			'full_name' => 'name',
			'contact_name' => 'name',
			'email' => 'email',
			'email_address' => 'email',
			'e_mail' => 'email',
			'contact_email' => 'email',
			'phone' => 'phone',
			'phone_number' => 'phone',
			'mobile' => 'phone',
			'cell' => 'phone',
			'telephone' => 'phone',
			'company' => 'company',
			'business' => 'company',
			'organization' => 'company',
			'brokerage' => 'company',
			'agency' => 'company',
			'group' => 'group',
			'group_label' => 'group',
			'category' => 'group',
			'segment' => 'group',
			'notes' => 'notes',
			'comments' => 'notes',
			'expires_at' => 'expires_at',
			'expires' => 'expires_at',
			'expiration' => 'expires_at',
			'expiration_date' => 'expires_at',
		);
	}
}

if (!function_exists('vms_pass_outreach_supported_import_fields')) {
	function vms_pass_outreach_supported_import_fields(): array
	{
		return array(
			'email' => __('Email', 'backstage-outreach'),
			'first_name' => __('First Name', 'backstage-outreach'),
			'last_name' => __('Last Name', 'backstage-outreach'),
			'name' => __('Full Name', 'backstage-outreach'),
			'phone' => __('Phone', 'backstage-outreach'),
			'company' => __('Company', 'backstage-outreach'),
			'group' => __('Group', 'backstage-outreach'),
			'notes' => __('Notes', 'backstage-outreach'),
			'expires_at' => __('Expires At', 'backstage-outreach'),
		);
	}
}

if (!function_exists('vms_pass_outreach_import_mapping_options')) {
	function vms_pass_outreach_import_mapping_options(): array
	{
		return array(
			'' => __('Do not import', 'backstage-outreach'),
		) + vms_pass_outreach_supported_import_fields();
	}
}

if (!function_exists('vms_pass_outreach_normalize_csv_header')) {
	function vms_pass_outreach_normalize_csv_header(string $header): string
	{
		$header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
		$header = function_exists('mb_strtolower') ? mb_strtolower($header, 'UTF-8') : strtolower($header);
		$header = preg_replace('/[^a-z0-9]+/i', '_', $header);
		return trim((string) $header, '_');
	}
}

if (!function_exists('vms_pass_outreach_csv_column_map')) {
	function vms_pass_outreach_csv_column_map(array $header_row): array
	{
		$selected_mapping = function_exists('vms_pass_outreach_suggested_csv_mapping')
			? vms_pass_outreach_suggested_csv_mapping($header_row)
			: array();
		$map = array();

		foreach ($selected_mapping as $index => $field) {
			$field = sanitize_key((string) $field);
			if ($field === '') {
				continue;
			}
			if (isset($map[$field])) {
				continue;
			}
			$map[$field] = (int) $index;
		}

		return $map;
	}
}

if (!function_exists('vms_pass_outreach_suggested_csv_mapping')) {
	function vms_pass_outreach_suggested_csv_mapping(array $header_row): array
	{
		$aliases = vms_pass_outreach_csv_header_aliases();
		$mapping = array();
		$used_fields = array();

		foreach ($header_row as $index => $header) {
			$mapping[(int) $index] = '';
			$normalized = vms_pass_outreach_normalize_csv_header((string) $header);
			if ($normalized === '') {
				continue;
			}

			$field = sanitize_key((string) ($aliases[$normalized] ?? ''));
			if ($field === '' || isset($used_fields[$field])) {
				continue;
			}

			$mapping[(int) $index] = $field;
			$used_fields[$field] = true;
		}

		return $mapping;
	}
}

if (!function_exists('vms_pass_outreach_normalize_selected_csv_mapping')) {
	function vms_pass_outreach_normalize_selected_csv_mapping(array $raw_mapping, array $header_row): array
	{
		$supported_fields = vms_pass_outreach_supported_import_fields();
		$mapping = array();

		foreach (array_values($header_row) as $index => $header) {
			unset($header);
			$field = sanitize_key((string) ($raw_mapping[$index] ?? ''));
			$mapping[$index] = array_key_exists($field, $supported_fields) ? $field : '';
		}

		return $mapping;
	}
}

if (!function_exists('vms_pass_outreach_validate_selected_csv_mapping')) {
	function vms_pass_outreach_validate_selected_csv_mapping(array $selected_mapping, array $header_row)
	{
		$selected_mapping = vms_pass_outreach_normalize_selected_csv_mapping($selected_mapping, $header_row);
		$map = array();
		$labels = vms_pass_outreach_supported_import_fields();

		foreach ($selected_mapping as $index => $field) {
			$field = sanitize_key((string) $field);
			if ($field === '') {
				continue;
			}
			if (isset($map[$field])) {
				return new WP_Error(
					'recipient_import_duplicate_mapping',
					sprintf(
						__('Map only one uploaded column to %s.', 'backstage-outreach'),
						(string) ($labels[$field] ?? $field)
					)
				);
			}
			$map[$field] = (int) $index;
		}

		if (!isset($map['email'])) {
			return new WP_Error('recipient_import_email_mapping_required', __('Map one uploaded column to Email before previewing or importing.', 'backstage-outreach'));
		}

		return array(
			'selected_mapping' => $selected_mapping,
			'column_map' => $map,
		);
	}
}

if (!function_exists('vms_pass_outreach_csv_row_blank')) {
	function vms_pass_outreach_csv_row_blank(array $row): bool
	{
		foreach ($row as $value) {
			if (trim((string) $value) !== '') {
				return false;
			}
		}
		return true;
	}
}

if (!function_exists('vms_pass_outreach_csv_sample_values')) {
	function vms_pass_outreach_csv_sample_values(array $header_row, array $data_rows): array
	{
		$samples = array_fill(0, count($header_row), '');
		foreach ($data_rows as $row_info) {
			$values = isset($row_info['values']) && is_array($row_info['values']) ? array_values($row_info['values']) : array();
			foreach ($samples as $index => $sample) {
				if ($sample !== '') {
					continue;
				}
				$value = trim((string) ($values[$index] ?? ''));
				if ($value !== '') {
					$samples[$index] = sanitize_text_field($value);
				}
			}
			if (!in_array('', $samples, true)) {
				break;
			}
		}

		return $samples;
	}
}

if (!function_exists('vms_pass_outreach_csv_file_error_message')) {
	function vms_pass_outreach_csv_file_error_message(int $error_code): string
	{
		$messages = array(
			UPLOAD_ERR_INI_SIZE => __('The uploaded CSV exceeds the server upload limit.', 'backstage-outreach'),
			UPLOAD_ERR_FORM_SIZE => __('The uploaded CSV exceeds the form upload limit.', 'backstage-outreach'),
			UPLOAD_ERR_PARTIAL => __('The CSV upload was incomplete. Please try again.', 'backstage-outreach'),
			UPLOAD_ERR_NO_FILE => __('Choose a CSV file to import.', 'backstage-outreach'),
			UPLOAD_ERR_NO_TMP_DIR => __('The server is missing a temporary upload directory.', 'backstage-outreach'),
			UPLOAD_ERR_CANT_WRITE => __('The uploaded CSV could not be written to disk.', 'backstage-outreach'),
			UPLOAD_ERR_EXTENSION => __('The uploaded CSV was blocked by a server extension.', 'backstage-outreach'),
		);
		return (string) ($messages[$error_code] ?? __('The CSV upload failed.', 'backstage-outreach'));
	}
}

if (!function_exists('vms_pass_outreach_parse_import_csv')) {
	function vms_pass_outreach_parse_import_csv(string $file_path): array
	{
		$handle = fopen($file_path, 'rb');
		if ($handle === false) {
			return array(
				'error' => new WP_Error('recipient_import_open_failed', __('Could not open the uploaded CSV file.', 'backstage-outreach')),
			);
		}

		$header_row = fgetcsv($handle);
		if (!is_array($header_row) || empty($header_row)) {
			fclose($handle);
			return array(
				'error' => new WP_Error('recipient_import_missing_header', __('The CSV file must include a header row.', 'backstage-outreach')),
			);
		}
		$header_row = array_values(array_map('sanitize_text_field', $header_row));
		$non_empty_headers = array_filter(array_map('vms_pass_outreach_normalize_csv_header', $header_row));
		if (empty($non_empty_headers)) {
			fclose($handle);
			return array(
				'error' => new WP_Error('recipient_import_headers_invalid', __('The CSV header row is missing readable column names.', 'backstage-outreach')),
			);
		}

		$row_limit = vms_pass_outreach_import_row_limit();
		$data_rows = array();
		$row_number = 1;
		$blank_rows = 0;
		while (($row = fgetcsv($handle)) !== false) {
			$row_number += 1;
			if (vms_pass_outreach_csv_row_blank((array) $row)) {
				$blank_rows += 1;
				continue;
			}
			if (count($data_rows) >= $row_limit) {
				fclose($handle);
				return array(
					'error' => new WP_Error(
					'recipient_import_row_limit',
						sprintf(__('CSV imports are limited to %d recipient rows per upload.', 'backstage-outreach'), $row_limit)
					),
				);
			}
			$data_rows[] = array(
				'row_number' => $row_number,
				'values' => array_map(static function ($value): string {
					return is_scalar($value) ? (string) $value : '';
				}, array_values((array) $row)),
			);
		}

		fclose($handle);
		return array(
			'header_row' => $header_row,
			'suggested_mapping' => vms_pass_outreach_suggested_csv_mapping($header_row),
			'sample_values' => vms_pass_outreach_csv_sample_values($header_row, $data_rows),
			'data_rows' => $data_rows,
			'blank_rows' => $blank_rows,
		);
	}
}

if (!function_exists('vms_pass_outreach_validate_import_file')) {
	function vms_pass_outreach_validate_import_file(array $file)
	{
		$error_code = isset($file['error']) ? absint($file['error']) : UPLOAD_ERR_NO_FILE;
		if ($error_code !== UPLOAD_ERR_OK) {
			return new WP_Error('recipient_import_upload_error', vms_pass_outreach_csv_file_error_message($error_code));
		}

		$tmp_name = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
		$filename = isset($file['name']) ? sanitize_file_name((string) $file['name']) : '';
		$file_size = isset($file['size']) ? absint($file['size']) : 0;
		if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
			return new WP_Error('recipient_import_upload_missing', __('The uploaded CSV could not be verified.', 'backstage-outreach'));
		}
		if ($filename === '' || strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) !== 'csv') {
			return new WP_Error('recipient_import_invalid_type', __('Upload a CSV file with a .csv extension.', 'backstage-outreach'));
		}
		if ($file_size <= 0 || $file_size > vms_pass_outreach_import_max_file_bytes()) {
			return new WP_Error(
				'recipient_import_file_too_large',
				sprintf(__('CSV uploads are limited to %d MB.', 'backstage-outreach'), max(1, (int) round(vms_pass_outreach_import_max_file_bytes() / 1048576)))
			);
		}

		return array(
			'tmp_name' => $tmp_name,
			'filename' => $filename,
			'file_size' => $file_size,
		);
	}
}

if (!function_exists('vms_pass_outreach_build_import_row_input')) {
	function vms_pass_outreach_build_import_row_input(array $values, array $column_map): array
	{
		$input = array();
		foreach ($column_map as $canonical => $index) {
			$input[$canonical] = isset($values[$index]) ? trim((string) $values[$index]) : '';
		}
		if (!isset($input['group_label']) && isset($input['group'])) {
			$input['group_label'] = $input['group'];
		}
		return $input;
	}
}

if (!function_exists('vms_pass_outreach_prepare_import_rows')) {
	function vms_pass_outreach_prepare_import_rows(array $data_rows, array $column_map, array $duplicate_lookup = array()): array
	{
		$duplicate_lookup = array_merge(
			array(
				'email' => array(),
				'phone' => array(),
			),
			$duplicate_lookup
		);
		$queued_lookup = array(
			'email' => array(),
			'phone' => array(),
		);
		$prepared_rows = array();
		$duplicates = array();
		$failed_rows = array();
		$valid_email_count = 0;

		foreach ($data_rows as $row_info) {
			$row_number = absint($row_info['row_number'] ?? 0);
			$values = isset($row_info['values']) && is_array($row_info['values']) ? $row_info['values'] : array();
			$raw_row = vms_pass_outreach_build_import_row_input($values, $column_map);
			$identity = vms_pass_outreach_sanitize_recipient_identity_fields($raw_row, array(
				'allow_name_split' => true,
			));
			if (is_wp_error($identity)) {
				$failed_rows[] = array(
					'row_number' => $row_number,
					'reason' => $identity->get_error_message(),
				);
				continue;
			}

			$email_norm = (string) ($identity['email_norm'] ?? '');
			$phone_norm = (string) ($identity['phone_norm'] ?? '');
			$is_duplicate = false;
			if ($email_norm !== '' && (isset($duplicate_lookup['email'][$email_norm]) || isset($queued_lookup['email'][$email_norm]))) {
				$is_duplicate = true;
			}
			if (!$is_duplicate && $email_norm === '' && $phone_norm !== '' && (isset($duplicate_lookup['phone'][$phone_norm]) || isset($queued_lookup['phone'][$phone_norm]))) {
				$is_duplicate = true;
			}
			if ($is_duplicate) {
				$duplicates[] = $row_number;
				continue;
			}

			if ($email_norm !== '') {
				$queued_lookup['email'][$email_norm] = true;
				$valid_email_count += 1;
			}
			if ($phone_norm !== '') {
				$queued_lookup['phone'][$phone_norm] = true;
			}

			$prepared_rows[] = array_merge($raw_row, $identity, array(
				'status' => 'ready',
			));
		}

		return array(
			'prepared_rows' => $prepared_rows,
			'duplicate_count' => count($duplicates),
			'duplicate_rows' => $duplicates,
			'failed_count' => count($failed_rows),
			'failed_rows' => $failed_rows,
			'valid_email_count' => $valid_email_count,
		);
	}
}

if (!function_exists('vms_pass_outreach_insert_prepared_recipients')) {
	function vms_pass_outreach_insert_prepared_recipients(array $campaign, array $prepared_rows)
	{
		$campaign_id = absint($campaign['id'] ?? 0);
		if ($campaign_id <= 0) {
			return new WP_Error('invalid_campaign', __('Select a valid outreach campaign before importing recipients.', 'backstage-outreach'));
		}
		if (!vms_pass_outreach_campaign_supports_recipients($campaign)) {
			return new WP_Error('campaign_missing_batch', __('Recipients currently require a linked Guest Pass batch so each invite can reserve a unique claim token.', 'backstage-outreach'));
		}

		$available_tokens = vms_pass_outreach_available_token_count($campaign);
		if (count($prepared_rows) > $available_tokens) {
			return new WP_Error(
				'recipient_import_token_shortage',
				sprintf(
					__('This campaign only has %1$d available Guest Pass tokens, but %2$d new recipients are ready to import after duplicate checks. No recipients were imported.', 'backstage-outreach'),
					$available_tokens,
					count($prepared_rows)
				)
			);
		}

		global $wpdb;
		$table = vms_pass_outreach_recipient_table();
		$inserted_ids = array();
		$user_id = get_current_user_id();
		$now = function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql');

		foreach ($prepared_rows as $row) {
			$payload = vms_pass_outreach_sanitize_recipient_payload($row, $campaign_id, 0);
			if (is_wp_error($payload)) {
				foreach ($inserted_ids as $inserted_id) {
					$wpdb->delete($table, array('id' => $inserted_id), array('%d'));
				}
				return $payload;
			}

			$insert = $payload;
			$insert['created_by'] = $user_id;
			$insert['created_at'] = $now;
			$result = $wpdb->insert($table, $insert, vms_pass_outreach_recipient_db_formats($insert));
			if ($result === false) {
				foreach ($inserted_ids as $inserted_id) {
					$wpdb->delete($table, array('id' => $inserted_id), array('%d'));
				}
				return new WP_Error('recipient_import_insert_failed', __('Could not import outreach recipients from this CSV.', 'backstage-outreach'));
			}
			$inserted_ids[] = absint($wpdb->insert_id);
		}

		return array(
			'imported_count' => count($inserted_ids),
			'available_tokens_before' => $available_tokens,
			'available_tokens_after' => max(0, $available_tokens - count($inserted_ids)),
			'inserted_ids' => $inserted_ids,
		);
	}
}

if (!function_exists('vms_pass_outreach_commit_contact_audience_preview')) {
	function vms_pass_outreach_commit_contact_audience_preview(array $campaign, array $preview, array $selected_contact_ids = array())
	{
		$prepared_rows = isset($preview['prepared_rows']) && is_array($preview['prepared_rows']) ? $preview['prepared_rows'] : array();
		if (empty($prepared_rows)) {
			return new WP_Error('contact_audience_empty', __('No eligible contacts are ready to add from this preview.', 'backstage-outreach'));
		}

		if (empty($selected_contact_ids)) {
			return new WP_Error('contact_audience_none_selected', __('Select at least one eligible contact to add.', 'backstage-outreach'));
		}

		$selected_rows = vms_pass_outreach_contact_audience_selected_prepared_rows($preview, $selected_contact_ids);
		if (empty($selected_rows)) {
			return new WP_Error('contact_audience_selection_invalid', __('Select at least one currently eligible contact to add.', 'backstage-outreach'));
		}

		$inserted = vms_pass_outreach_insert_prepared_recipients($campaign, $selected_rows);
		if (is_wp_error($inserted)) {
			return $inserted;
		}

		$result = array(
			'eligible_count' => absint($preview['eligible_count'] ?? count($prepared_rows)),
			'selected_count' => count($selected_rows),
			'already_in_campaign_count' => absint($preview['already_in_campaign_count'] ?? 0),
			'missing_email_count' => absint($preview['missing_email_count'] ?? 0),
			'globally_suppressed_count' => absint($preview['globally_suppressed_count'] ?? 0),
			'excluded_count' => absint($preview['excluded_count'] ?? 0),
			'duplicate_email_count' => absint($preview['duplicate_email_count'] ?? 0),
			'status_skipped_count' => absint($preview['status_skipped_count'] ?? 0),
			'skipped_count' => absint($preview['skipped_count'] ?? 0),
			'inserted_count' => absint($inserted['imported_count'] ?? 0),
			'available_tokens_before' => absint($inserted['available_tokens_before'] ?? 0),
			'available_tokens_after' => absint($inserted['available_tokens_after'] ?? 0),
			'inserted_ids' => array_values(array_map('absint', (array) ($inserted['inserted_ids'] ?? array()))),
		);

		if (function_exists('vms_admission_audit_log')) {
			vms_admission_audit_log(0, null, 'pass_outreach_contact_audience_add', get_current_user_id(), 'admin', array(
				'campaign_id' => absint($campaign['id'] ?? 0),
				'inserted_count' => $result['inserted_count'],
				'selected_count' => $result['selected_count'],
				'eligible_count' => $result['eligible_count'],
				'skipped_count' => $result['skipped_count'],
				'filters' => isset($preview['filters']) && is_array($preview['filters']) ? $preview['filters'] : array(),
			));
		}

		return $result;
	}
}

if (!function_exists('vms_pass_outreach_parse_uploaded_csv_for_mapping')) {
	function vms_pass_outreach_parse_uploaded_csv_for_mapping(array $file)
	{
		$file_info = vms_pass_outreach_validate_import_file($file);
		if (is_wp_error($file_info)) {
			return $file_info;
		}

		$parsed = vms_pass_outreach_parse_import_csv((string) ($file_info['tmp_name'] ?? ''));
		if (!empty($parsed['error']) && is_wp_error($parsed['error'])) {
			return $parsed['error'];
		}

		return array_merge($parsed, array(
			'file_name' => (string) ($file_info['filename'] ?? ''),
			'file_size' => absint($file_info['file_size'] ?? 0),
		));
	}
}

if (!function_exists('vms_pass_outreach_preview_import_from_parsed_csv')) {
	function vms_pass_outreach_preview_import_from_parsed_csv(array $parsed, array $selected_mapping, array $duplicate_lookup = array())
	{
		$header_row = array_values(array_map('sanitize_text_field', (array) ($parsed['header_row'] ?? array())));
		$mapping = vms_pass_outreach_validate_selected_csv_mapping($selected_mapping, $header_row);
		if (is_wp_error($mapping)) {
			return $mapping;
		}

		$column_map = (array) ($mapping['column_map'] ?? array());
		$data_rows = (array) ($parsed['data_rows'] ?? array());
		$prepared = vms_pass_outreach_prepare_import_rows($data_rows, $column_map, $duplicate_lookup);
		if (!is_array($prepared)) {
			$prepared = array();
		}

		$prepared_rows = (array) ($prepared['prepared_rows'] ?? array());
		$sample_rows = array();
		foreach (array_slice($prepared_rows, 0, 5) as $row) {
			$sample_rows[] = array(
				'full_name' => vms_pass_outreach_recipient_full_name($row),
				'email' => (string) ($row['email'] ?? ''),
				'phone' => (string) ($row['phone'] ?? ''),
				'company' => (string) ($row['company'] ?? ''),
				'group_label' => (string) ($row['group_label'] ?? ''),
			);
		}

		return array(
			'file_name' => sanitize_file_name((string) ($parsed['file_name'] ?? '')),
			'file_size' => absint($parsed['file_size'] ?? 0),
			'header_row' => $header_row,
			'selected_mapping' => array_values((array) ($mapping['selected_mapping'] ?? array())),
			'column_map' => $column_map,
			'detected_columns' => array_values(array_map('sanitize_key', array_keys($column_map))),
			'total_rows' => count($data_rows),
			'ready_count' => count($prepared_rows),
			'valid_email_count' => absint($prepared['valid_email_count'] ?? 0),
			'duplicate_count' => absint($prepared['duplicate_count'] ?? 0),
			'duplicate_rows' => array_values(array_map('absint', (array) ($prepared['duplicate_rows'] ?? array()))),
			'failed_count' => absint($prepared['failed_count'] ?? 0),
			'failed_rows' => array_values((array) ($prepared['failed_rows'] ?? array())),
			'blank_rows' => absint($parsed['blank_rows'] ?? 0),
			'prepared_rows' => $prepared_rows,
			'sample_rows' => $sample_rows,
		);
	}
}

if (!function_exists('vms_pass_outreach_preview_import_from_csv')) {
	function vms_pass_outreach_preview_import_from_csv(array $file)
	{
		$parsed = vms_pass_outreach_parse_uploaded_csv_for_mapping($file);
		if (is_wp_error($parsed)) {
			return $parsed;
		}

		return vms_pass_outreach_preview_import_from_parsed_csv($parsed, (array) ($parsed['suggested_mapping'] ?? array()));
	}
}

if (!function_exists('vms_pass_outreach_import_recipients_from_csv')) {
	function vms_pass_outreach_import_recipients_from_csv(array $campaign, array $file)
	{
		$campaign_id = absint($campaign['id'] ?? 0);
		if ($campaign_id <= 0) {
			return new WP_Error('invalid_campaign', __('Select a valid outreach campaign before importing recipients.', 'backstage-outreach'));
		}
		if (!vms_pass_outreach_campaign_supports_recipients($campaign)) {
			return new WP_Error('campaign_missing_batch', __('Recipients currently require a linked Guest Pass batch so each invite can reserve a unique claim token.', 'backstage-outreach'));
		}

		$parsed = vms_pass_outreach_parse_uploaded_csv_for_mapping($file);
		if (is_wp_error($parsed)) {
			return $parsed;
		}

		$duplicate_lookup = vms_pass_outreach_campaign_duplicate_lookup($campaign_id);
		$preview = vms_pass_outreach_preview_import_from_parsed_csv($parsed, (array) ($parsed['suggested_mapping'] ?? array()), $duplicate_lookup);
		if (is_wp_error($preview)) {
			return $preview;
		}

		$inserted = vms_pass_outreach_insert_prepared_recipients($campaign, (array) ($preview['prepared_rows'] ?? array()));
		if (is_wp_error($inserted)) {
			return $inserted;
		}

		$user_id = get_current_user_id();
		$results = array(
			'imported_count' => absint($inserted['imported_count'] ?? 0),
			'duplicate_count' => absint($preview['duplicate_count'] ?? 0),
			'duplicate_rows' => array_values(array_map('absint', (array) ($preview['duplicate_rows'] ?? array()))),
			'failed_count' => absint($preview['failed_count'] ?? 0),
			'failed_rows' => array_values((array) ($preview['failed_rows'] ?? array())),
			'blank_rows' => absint($preview['blank_rows'] ?? 0),
			'available_tokens_before' => absint($inserted['available_tokens_before'] ?? 0),
			'available_tokens_after' => absint($inserted['available_tokens_after'] ?? 0),
			'inserted_ids' => array_values(array_map('absint', (array) ($inserted['inserted_ids'] ?? array()))),
		);

		if (function_exists('vms_admission_audit_log')) {
			vms_admission_audit_log(0, null, 'pass_outreach_recipient_import', $user_id, 'admin', array(
				'campaign_id' => $campaign_id,
				'imported_count' => $results['imported_count'],
				'duplicate_rows' => $results['duplicate_rows'],
				'failed_rows' => $results['failed_rows'],
				'blank_rows' => $results['blank_rows'],
			));
		}

		return $results;
	}
}

if (!function_exists('vms_pass_outreach_export_filename')) {
	function vms_pass_outreach_export_filename(array $campaign, string $suffix = 'recipients'): string
	{
		$name = sanitize_title((string) ($campaign['campaign_name'] ?? 'campaign'));
		if ($name === '') {
			$name = 'campaign';
		}
		return $name . '-' . $suffix . '-' . gmdate('Ymd-His') . '.csv';
	}
}

if (!function_exists('vms_pass_outreach_export_rows')) {
	function vms_pass_outreach_export_rows(array $campaign, array $recipients, array $checked_in_map, string $format = 'full'): array
	{
		$rows = array();
		foreach ($recipients as $recipient) {
			$status = vms_pass_outreach_recipient_status_for_display($recipient, $campaign, $checked_in_map);
			$send_status = vms_pass_outreach_recipient_send_status_for_display($recipient);
			$invite_url = vms_pass_outreach_build_invite_url($recipient);
			$email_subject = vms_pass_outreach_build_invite_subject($recipient, $campaign);
			$invite_message = vms_pass_outreach_build_invite_message($recipient, $campaign);
			$full_name = vms_pass_outreach_recipient_full_name($recipient);

			if ($format === 'send_prep') {
				$rows[] = array(
					'email' => (string) ($recipient['email'] ?? ''),
					'first_name' => (string) ($recipient['first_name'] ?? ''),
					'last_name' => (string) ($recipient['last_name'] ?? ''),
					'full_name' => $full_name,
					'company' => (string) ($recipient['company'] ?? ''),
					'group_label' => (string) ($recipient['group_label'] ?? ''),
					'email_subject' => $email_subject,
					'invite_message' => $invite_message,
					'invite_url' => $invite_url,
					'status' => $status,
					'send_status' => $send_status,
					'sent_at' => (string) ($recipient['sent_at'] ?? ''),
					'sent_by' => absint($recipient['sent_by'] ?? 0),
					'send_method' => (string) ($recipient['send_method'] ?? ''),
					'last_send_error' => (string) ($recipient['last_send_error'] ?? ''),
					'last_contacted_at' => (string) ($recipient['last_contacted_at'] ?? ''),
					'claimed_at' => (string) ($recipient['claimed_at'] ?? ''),
				);
				continue;
			}

			$rows[] = array(
				'campaign_id' => absint($campaign['id'] ?? 0),
				'campaign_name' => (string) ($campaign['campaign_name'] ?? ''),
				'recipient_id' => absint($recipient['id'] ?? 0),
				'first_name' => (string) ($recipient['first_name'] ?? ''),
				'last_name' => (string) ($recipient['last_name'] ?? ''),
				'email' => (string) ($recipient['email'] ?? ''),
				'phone' => (string) ($recipient['phone'] ?? ''),
				'company' => (string) ($recipient['company'] ?? ''),
				'group_label' => (string) ($recipient['group_label'] ?? ''),
				'status' => $status,
				'send_status' => $send_status,
				'sent_at' => (string) ($recipient['sent_at'] ?? ''),
				'sent_by' => absint($recipient['sent_by'] ?? 0),
				'send_method' => (string) ($recipient['send_method'] ?? ''),
				'last_send_error' => (string) ($recipient['last_send_error'] ?? ''),
				'last_contacted_at' => (string) ($recipient['last_contacted_at'] ?? ''),
				'claimed_at' => (string) ($recipient['claimed_at'] ?? ''),
				'claimed_headcount' => max(0, absint($recipient['claimed_headcount'] ?? 0)),
				'checked_in_count' => vms_pass_outreach_recipient_checked_in_count($recipient, $checked_in_map),
				'email_subject' => $email_subject,
				'invite_url' => $invite_url,
				'invite_message' => $invite_message,
				'expires_at' => (string) ($recipient['expires_at'] ?? ''),
				'revoked_at' => (string) ($recipient['revoked_at'] ?? ''),
				'notes' => (string) ($recipient['notes'] ?? ''),
			);
		}
		return $rows;
	}
}

if (!function_exists('vms_pass_outreach_stream_recipient_export')) {
	function vms_pass_outreach_stream_recipient_export(array $campaign, array $recipient_ids = array(), string $format = 'full'): void
	{
		$format = $format === 'send_prep' ? 'send_prep' : 'full';
		$query_args = array(
			'limit' => 0,
			'campaign' => $campaign,
		);
		if (!empty($recipient_ids)) {
			$query_args['ids'] = $recipient_ids;
		}
		$recipients = vms_pass_outreach_query_recipients_for_campaign(absint($campaign['id'] ?? 0), $query_args);
		if (empty($recipients)) {
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', __('No outreach recipients matched the export request.', 'backstage-outreach'));
			}
			wp_safe_redirect(vms_pass_outreach_recipient_redirect_url(absint($campaign['id'] ?? 0)));
			exit;
		}

		$checked_in_map = vms_pass_outreach_checked_in_map_for_recipients($recipients);
		$rows = vms_pass_outreach_export_rows($campaign, $recipients, $checked_in_map, $format);
		$headers = $format === 'send_prep'
			? array(
				'email',
				'first_name',
				'last_name',
				'full_name',
				'company',
				'group_label',
				'email_subject',
				'invite_message',
				'invite_url',
				'status',
				'send_status',
				'sent_at',
				'sent_by',
				'send_method',
				'last_send_error',
				'last_contacted_at',
				'claimed_at',
			)
			: array(
				'campaign_id',
				'campaign_name',
				'recipient_id',
				'first_name',
				'last_name',
				'email',
				'phone',
				'company',
				'group_label',
				'status',
				'send_status',
				'sent_at',
				'sent_by',
				'send_method',
				'last_send_error',
				'last_contacted_at',
				'claimed_at',
				'claimed_headcount',
				'checked_in_count',
				'email_subject',
				'invite_url',
				'invite_message',
				'expires_at',
				'revoked_at',
				'notes',
			);

		if (function_exists('vms_admission_audit_log')) {
			vms_admission_audit_log(0, null, $format === 'send_prep' ? 'pass_outreach_send_prep_export' : 'pass_outreach_recipient_export', get_current_user_id(), 'admin', array(
				'campaign_id' => absint($campaign['id'] ?? 0),
				'export_format' => $format,
				'recipient_count' => count($rows),
				'selected_recipient_ids' => array_values(array_filter(array_map('absint', $recipient_ids))),
			));
		}

		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		$suffix = $format === 'send_prep'
			? (empty($recipient_ids) ? 'send-prep' : 'selected-send-prep')
			: (empty($recipient_ids) ? 'recipients' : 'selected-recipients');
		header('Content-Disposition: attachment; filename="' . vms_pass_outreach_export_filename($campaign, $suffix) . '"');

		$output = fopen('php://output', 'wb');
		if ($output === false) {
			wp_die(esc_html__('Could not open the export stream.', 'backstage-outreach'));
		}
		fputcsv($output, $headers);
		foreach ($rows as $row) {
			$values = array();
			foreach ($headers as $header_key) {
				$values[] = isset($row[$header_key]) ? (string) $row[$header_key] : '';
			}
			fputcsv($output, $values);
		}
		fclose($output);
		exit;
	}
}

if (!function_exists('vms_pass_outreach_handle_recipient_save')) {
	function vms_pass_outreach_handle_recipient_save(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-outreach'));
		}

		check_admin_referer('vms_pass_outreach_recipient_save');

		$campaign_id = isset($_POST['campaign_id']) ? absint(wp_unslash($_POST['campaign_id'])) : 0;
		$recipient_id = isset($_POST['recipient_id']) ? absint(wp_unslash($_POST['recipient_id'])) : 0;
		$user_id = get_current_user_id();
		$raw = isset($_POST) ? (array) wp_unslash($_POST) : array();
		$existing_recipient = $recipient_id > 0 ? vms_pass_outreach_get_recipient_by_id($recipient_id) : null;
		$payload = vms_pass_outreach_sanitize_recipient_payload($raw, $campaign_id, $recipient_id);
		if (is_wp_error($payload)) {
			vms_pass_outreach_set_recipient_form_flash($user_id, $campaign_id, array(
				'campaign_id' => $campaign_id,
				'recipient_id' => $recipient_id,
				'payload' => vms_pass_outreach_recipient_form_flash_payload($raw, is_array($existing_recipient) ? $existing_recipient : array()),
				'field_errors' => vms_pass_outreach_recipient_field_errors_from_error($payload),
			));
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', $payload->get_error_message());
			}
			wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id, $recipient_id, 'vms-outreach-individual-recipient'));
			exit;
		}

		global $wpdb;
		$table = vms_pass_outreach_recipient_table();
		$now = function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql');

		if ($recipient_id > 0) {
			$update = $payload;
			$update['updated_by'] = $user_id;
			$update['updated_at'] = $now;
			$result = $wpdb->update(
				$table,
				$update,
				array('id' => $recipient_id),
				vms_pass_outreach_recipient_db_formats($update),
				array('%d')
			);
			if ($result === false) {
				vms_pass_outreach_set_recipient_form_flash($user_id, $campaign_id, array(
					'campaign_id' => $campaign_id,
					'recipient_id' => $recipient_id,
					'payload' => vms_pass_outreach_recipient_form_flash_payload($raw, is_array($existing_recipient) ? $existing_recipient : array()),
					'field_errors' => array(),
				));
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Could not update outreach recipient.', 'backstage-outreach'));
				}
				wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id, $recipient_id, 'vms-outreach-individual-recipient'));
				exit;
			}
		} else {
			$insert = $payload;
			$insert['created_by'] = $user_id;
			$insert['created_at'] = $now;
			$result = $wpdb->insert(
				$table,
				$insert,
				vms_pass_outreach_recipient_db_formats($insert)
			);
			if ($result === false) {
				vms_pass_outreach_set_recipient_form_flash($user_id, $campaign_id, array(
					'campaign_id' => $campaign_id,
					'recipient_id' => 0,
					'payload' => vms_pass_outreach_recipient_form_flash_payload($raw),
					'field_errors' => array(),
				));
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Could not create outreach recipient.', 'backstage-outreach'));
				}
				wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id, 0, 'vms-outreach-individual-recipient'));
				exit;
			}
			$recipient_id = absint($wpdb->insert_id);
		}

		if (function_exists('vms_admission_audit_log')) {
			vms_admission_audit_log(0, null, !empty($raw['recipient_id']) ? 'pass_outreach_recipient_update' : 'pass_outreach_recipient_create', $user_id, 'admin', array(
				'campaign_id' => $campaign_id,
				'recipient_id' => $recipient_id,
				'pass_token_id' => absint($payload['pass_token_id'] ?? 0),
				'status' => (string) ($payload['status'] ?? ''),
				'email' => (string) ($payload['email'] ?? ''),
				'phone' => (string) ($payload['phone'] ?? ''),
			));
		}

		if (function_exists('vms_pass_claims_set_user_message')) {
			vms_pass_claims_set_user_message('success', __('Outreach recipient saved.', 'backstage-outreach'));
		}

		wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id, $recipient_id, 'vms-outreach-individual-recipient'));
		exit;
	}
}
add_action('admin_post_vms_pass_outreach_recipient_save', 'vms_pass_outreach_handle_recipient_save');

if (!function_exists('vms_pass_outreach_selected_recipient_ids')) {
	function vms_pass_outreach_selected_recipient_ids(array $raw): array
	{
		$ids = array();
		$values = isset($raw['recipient_ids']) ? (array) $raw['recipient_ids'] : array();
		foreach ($values as $value) {
			$recipient_id = absint($value);
			if ($recipient_id > 0) {
				$ids[] = $recipient_id;
			}
		}
		return array_values(array_unique($ids));
	}
}

if (!function_exists('vms_pass_outreach_handle_recipient_import')) {
	function vms_pass_outreach_handle_recipient_import(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-outreach'));
		}

		check_admin_referer('vms_pass_outreach_recipient_import');

		$campaign_id = isset($_POST['campaign_id']) ? absint(wp_unslash($_POST['campaign_id'])) : 0;
		$campaign = vms_pass_outreach_get_campaign_by_id($campaign_id);
		if (!is_array($campaign)) {
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', __('Outreach campaign not found.', 'backstage-outreach'));
			}
			wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id));
			exit;
		}

		$file = isset($_FILES['recipient_csv']) && is_array($_FILES['recipient_csv']) ? $_FILES['recipient_csv'] : array();
		$result = vms_pass_outreach_import_recipients_from_csv($campaign, $file);
		if (is_wp_error($result)) {
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', $result->get_error_message());
			}
			wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id));
			exit;
		}

		$parts = array(
			sprintf(_n('%d recipient imported.', '%d recipients imported.', (int) $result['imported_count'], 'backstage-outreach'), (int) $result['imported_count']),
		);
		if (!empty($result['duplicate_count'])) {
			$parts[] = sprintf(
				_n('%1$d duplicate row skipped (row %2$s).', '%1$d duplicate rows skipped (rows %2$s).', (int) $result['duplicate_count'], 'backstage-outreach'),
				(int) $result['duplicate_count'],
				vms_pass_outreach_format_number_list((array) $result['duplicate_rows'])
			);
		}
		if (!empty($result['failed_count'])) {
			$failed_labels = array();
			foreach (array_slice((array) $result['failed_rows'], 0, 5) as $failed_row) {
				$failed_labels[] = sprintf(
					__('row %1$d: %2$s', 'backstage-outreach'),
					absint($failed_row['row_number'] ?? 0),
					sanitize_text_field((string) ($failed_row['reason'] ?? ''))
				);
			}
			if (count((array) $result['failed_rows']) > count($failed_labels)) {
				$failed_labels[] = sprintf(__('and %d more', 'backstage-outreach'), count((array) $result['failed_rows']) - count($failed_labels));
			}
			$parts[] = sprintf(
				_n('%1$d row failed validation (%2$s).', '%1$d rows failed validation (%2$s).', (int) $result['failed_count'], 'backstage-outreach'),
				(int) $result['failed_count'],
				implode('; ', $failed_labels)
			);
		}
		if (!empty($result['blank_rows'])) {
			$parts[] = sprintf(_n('%d blank row ignored.', '%d blank rows ignored.', (int) $result['blank_rows'], 'backstage-outreach'), (int) $result['blank_rows']);
		}
		$parts[] = sprintf(__('Available tokens remaining: %d.', 'backstage-outreach'), (int) $result['available_tokens_after']);

		if (function_exists('vms_pass_claims_set_user_message')) {
			vms_pass_claims_set_user_message((int) $result['imported_count'] > 0 ? 'success' : 'error', implode(' ', array_filter($parts)));
		}

		wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id));
		exit;
	}
}
add_action('admin_post_vms_pass_outreach_recipient_import', 'vms_pass_outreach_handle_recipient_import');

if (!function_exists('vms_pass_outreach_handle_contact_audience')) {
	function vms_pass_outreach_handle_contact_audience(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-outreach'));
		}

		check_admin_referer('vms_pass_outreach_contact_audience');

		$campaign_id = isset($_POST['campaign_id']) ? absint(wp_unslash($_POST['campaign_id'])) : 0;
		$campaign = vms_pass_outreach_get_campaign_by_id($campaign_id);
		$preview_redirect_url = vms_pass_outreach_recipient_redirect_url($campaign_id, 0, 'vms-outreach-contact-audience');
		$commit_redirect_url = vms_pass_outreach_recipient_redirect_url($campaign_id, 0, 'vms-outreach-recipient-list');
		if (!is_array($campaign)) {
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', __('Outreach campaign not found.', 'backstage-outreach'));
			}
			wp_safe_redirect($preview_redirect_url);
			exit;
		}

		$user_id = get_current_user_id();
		$mode = isset($_POST['contact_audience_mode']) ? sanitize_key((string) wp_unslash($_POST['contact_audience_mode'])) : 'preview';
		$filters = vms_pass_outreach_normalize_contact_audience_filters(isset($_POST) ? (array) wp_unslash($_POST) : array());
		$selected_contact_ids = vms_pass_outreach_selected_contact_audience_contact_ids(isset($_POST) ? (array) wp_unslash($_POST) : array());

		if ($mode === 'preview') {
			$preview = vms_pass_outreach_build_contact_audience_preview($campaign, $filters);
			if (is_wp_error($preview)) {
				vms_pass_outreach_clear_contact_audience_preview($user_id, $campaign_id);
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $preview->get_error_message());
				}
				wp_safe_redirect($preview_redirect_url);
				exit;
			}

			vms_pass_outreach_set_contact_audience_preview($user_id, $campaign_id, $preview);
			if (function_exists('vms_pass_claims_set_user_message')) {
				$message = absint($preview['eligible_count'] ?? 0) > 0
					? sprintf(
						__('Audience preview ready. %1$d contacts can be added and %2$d will be skipped.', 'backstage-outreach'),
						(int) ($preview['eligible_count'] ?? 0),
						(int) ($preview['skipped_count'] ?? 0)
					)
					: __('Audience preview ready, but no contacts are currently eligible to add. Review the skipped reasons below.', 'backstage-outreach');
				vms_pass_claims_set_user_message(absint($preview['eligible_count'] ?? 0) > 0 ? 'success' : 'error', $message);
			}

			wp_safe_redirect($preview_redirect_url);
			exit;
		}

		if ($mode === 'commit') {
			$stored_preview = vms_pass_outreach_get_contact_audience_preview($user_id, $campaign_id);
			if (empty($stored_preview)) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Contact audience preview expired or is missing. Preview the audience again before adding contacts.', 'backstage-outreach'));
				}
				wp_safe_redirect($preview_redirect_url);
				exit;
			}

			if (!vms_pass_outreach_contact_audience_filters_match((array) ($stored_preview['filters'] ?? array()), $filters)) {
				vms_pass_outreach_clear_contact_audience_preview($user_id, $campaign_id);
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Contact filters changed after the last preview. Preview the audience again before adding contacts.', 'backstage-outreach'));
				}
				wp_safe_redirect($preview_redirect_url);
				exit;
			}

			$fresh_preview = vms_pass_outreach_build_contact_audience_preview($campaign, $filters);
			if (is_wp_error($fresh_preview)) {
				vms_pass_outreach_clear_contact_audience_preview($user_id, $campaign_id);
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $fresh_preview->get_error_message());
				}
				wp_safe_redirect($preview_redirect_url);
				exit;
			}

			$result = vms_pass_outreach_commit_contact_audience_preview($campaign, $fresh_preview, $selected_contact_ids);
			if (is_wp_error($result)) {
				if (!in_array($result->get_error_code(), array('contact_audience_none_selected', 'contact_audience_selection_invalid'), true)) {
					vms_pass_outreach_clear_contact_audience_preview($user_id, $campaign_id);
				}
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $result->get_error_message());
				}
				wp_safe_redirect($preview_redirect_url);
				exit;
			}
			vms_pass_outreach_clear_contact_audience_preview($user_id, $campaign_id);

			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message(
					(int) $result['inserted_count'] > 0 ? 'success' : 'error',
					sprintf(
						__('Added %1$d selected contacts. Skipped %2$d.', 'backstage-outreach'),
						(int) ($result['inserted_count'] ?? 0),
						(int) ($result['skipped_count'] ?? 0)
					)
				);
			}

			wp_safe_redirect($commit_redirect_url);
			exit;
		}

		if (function_exists('vms_pass_claims_set_user_message')) {
			vms_pass_claims_set_user_message('error', __('Unsupported contact audience action.', 'backstage-outreach'));
		}
		wp_safe_redirect($preview_redirect_url);
		exit;
	}
}
add_action('admin_post_vms_pass_outreach_contact_audience', 'vms_pass_outreach_handle_contact_audience');

if (!function_exists('vms_pass_outreach_handle_recipient_export')) {
	function vms_pass_outreach_handle_recipient_export(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-outreach'));
		}

		check_admin_referer('vms_pass_outreach_recipient_export');

		$campaign_id = isset($_REQUEST['campaign_id']) ? absint(wp_unslash($_REQUEST['campaign_id'])) : 0;
		$campaign = vms_pass_outreach_get_campaign_by_id($campaign_id);
		if (!is_array($campaign)) {
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', __('Outreach campaign not found.', 'backstage-outreach'));
			}
			wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id));
			exit;
		}

		$recipient_ids = isset($_REQUEST['recipient_ids']) ? vms_pass_outreach_selected_recipient_ids((array) wp_unslash($_REQUEST)) : array();
		$format = isset($_REQUEST['export_format']) ? sanitize_key((string) wp_unslash($_REQUEST['export_format'])) : 'full';
		vms_pass_outreach_stream_recipient_export($campaign, $recipient_ids, $format);
	}
}
add_action('admin_post_vms_pass_outreach_recipient_export', 'vms_pass_outreach_handle_recipient_export');

if (!function_exists('vms_pass_outreach_handle_activate_campaign')) {
	function vms_pass_outreach_handle_activate_campaign(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-outreach'));
		}

		$campaign_id = isset($_REQUEST['campaign_id']) ? absint(wp_unslash($_REQUEST['campaign_id'])) : 0;
		$recipient_id = isset($_REQUEST['recipient_id']) ? absint(wp_unslash($_REQUEST['recipient_id'])) : 0;
		$anchor = isset($_REQUEST['return_anchor']) ? preg_replace('/[^A-Za-z0-9_-]/', '', (string) wp_unslash($_REQUEST['return_anchor'])) : 'vms-outreach-delivery-status';
		$nonce = (isset($_REQUEST['_wpnonce']) && !is_array($_REQUEST['_wpnonce'])) ? sanitize_text_field(wp_unslash((string) $_REQUEST['_wpnonce'])) : '';
		if ($campaign_id <= 0 || !wp_verify_nonce($nonce, 'vms_pass_outreach_activate_campaign_' . $campaign_id)) {
			wp_die(esc_html__('Invalid request.', 'backstage-outreach'));
		}

		$campaign = vms_pass_outreach_get_campaign_by_id($campaign_id);
		if (!is_array($campaign)) {
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', __('Outreach campaign not found.', 'backstage-outreach'));
			}
			wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id, $recipient_id, $anchor));
			exit;
		}

		$result = function_exists('vms_pass_outreach_activate_campaign')
			? vms_pass_outreach_activate_campaign($campaign)
			: new WP_Error('campaign_activate_unavailable', __('Campaign activation is unavailable right now.', 'backstage-outreach'));
		if (function_exists('vms_pass_claims_set_user_message')) {
			vms_pass_claims_set_user_message(
				is_wp_error($result) ? 'error' : 'success',
				is_wp_error($result) ? $result->get_error_message() : __('Campaign activated. Invite links are now claimable.', 'backstage-outreach')
			);
		}

		wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id, $recipient_id, $anchor));
		exit;
	}
}
add_action('admin_post_vms_pass_outreach_activate_campaign', 'vms_pass_outreach_handle_activate_campaign');

if (!function_exists('vms_pass_outreach_handle_recipient_bulk')) {
	function vms_pass_outreach_handle_recipient_bulk(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-outreach'));
		}

		check_admin_referer('vms_pass_outreach_recipient_bulk');

		$campaign_id = isset($_POST['campaign_id']) ? absint(wp_unslash($_POST['campaign_id'])) : 0;
		$campaign = vms_pass_outreach_get_campaign_by_id($campaign_id);
		$action = isset($_POST['bulk_action']) ? sanitize_key((string) wp_unslash($_POST['bulk_action'])) : '';
		$activate_campaign = !empty($_POST['activate_campaign']);
		$queue_review_token = isset($_POST['queue_review_token']) ? sanitize_key((string) wp_unslash($_POST['queue_review_token'])) : '';
		$confirm_resend = !empty($_POST['confirm_resend']);
		$recipient_ids = vms_pass_outreach_selected_recipient_ids(isset($_POST) ? (array) wp_unslash($_POST) : array());

		if (!is_array($campaign)) {
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', __('Outreach campaign not found.', 'backstage-outreach'));
			}
			wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id));
			exit;
		}
		if ($action === '') {
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', __('Choose a bulk action to apply.', 'backstage-outreach'));
			}
			wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id));
			exit;
		}
		if ($action === 'export_selected') {
			if (empty($recipient_ids)) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Select at least one outreach recipient first.', 'backstage-outreach'));
				}
				wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id));
				exit;
			}
			vms_pass_outreach_stream_recipient_export($campaign, $recipient_ids);
		}

		$queue_action = $action;
		if ($queue_action === 'queue_selected_confirm') {
			$pending_selection = $queue_review_token !== '' ? vms_pass_outreach_get_pending_queue_selection($queue_review_token, $campaign_id) : array();
			if (empty($pending_selection['recipient_ids'])) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('The queue review expired. Select recipients again before submitting to the email queue.', 'backstage-outreach'));
				}
				wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id, 0, 'vms-outreach-recipient-list'));
				exit;
			}
			$recipient_ids = array_values((array) $pending_selection['recipient_ids']);
			$queue_action = 'queue_selected';
		}

		if (in_array($action, array('queue_selected', 'queue_selected_confirm', 'queue_all_unsent', 'retry_failed_selected', 'resend_selected'), true)) {
			if ($action !== 'queue_all_unsent' && empty($recipient_ids)) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Select at least one outreach recipient first.', 'backstage-outreach'));
				}
				wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id, 0, 'vms-outreach-recipient-list'));
				exit;
			}

			$recipients = vms_pass_outreach_query_recipients_for_campaign($campaign_id, array(
				'limit' => 0,
				'ids' => $action === 'queue_all_unsent' ? array() : $recipient_ids,
			));
			$recipient_lookup = array();
			foreach ($recipients as $recipient) {
				$recipient_lookup[absint($recipient['id'] ?? 0)] = $recipient;
			}
			$analysis = vms_pass_outreach_analyze_recipients_for_delivery_actions($recipients, $campaign);
			$analysis_counts = (array) ($analysis['counts'] ?? array());
			$skip_messages = array_values((array) ($analysis['detail_messages'] ?? array()));

			$mixed_selection_requires_review = $action === 'queue_selected'
				&& (
					absint($analysis_counts['already_sent'] ?? 0) > 0
					|| absint($analysis_counts['already_queued'] ?? 0) > 0
					|| absint($analysis_counts['failed_selected'] ?? 0) > 0
					|| absint($analysis_counts['validation_failed'] ?? 0) > 0
					|| absint($analysis_counts['blocked'] ?? 0) > 0
					|| absint($analysis_counts['other'] ?? 0) > 0
				);
			if ($mixed_selection_requires_review) {
				$review_token = vms_pass_outreach_store_pending_queue_selection($campaign_id, $recipient_ids);
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('info', __('Review the selected recipient summary before submitting to the email queue.', 'backstage-outreach'));
				}
				wp_safe_redirect(vms_pass_outreach_recipient_feedback_redirect_url($campaign_id, array(
					'queue_review' => $review_token,
				), 0, 'vms-outreach-recipient-list'));
				exit;
			}

			if ($action === 'resend_selected' && !$confirm_resend) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Confirm resend before sending another invite email to recipients who were already contacted.', 'backstage-outreach'));
				}
				wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id, 0, 'vms-outreach-recipient-list'));
				exit;
			}

			$delivery_campaign = vms_pass_outreach_prepare_campaign_for_delivery($campaign, array(
				'activate_campaign' => $activate_campaign,
			));
			if (is_wp_error($delivery_campaign)) {
				$delivery_error = $delivery_campaign->get_error_message();
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $delivery_error);
				}
				$redirect_notice = in_array($action, array('resend_selected'), true) ? array() : array(
					'delivery_notice' => $action === 'queue_all_unsent' ? 'queue_all_unsent' : ($action === 'retry_failed_selected' ? 'retry_failed_selected' : 'queue_selected'),
					'delivery_affected' => 0,
					'delivery_skipped_sent' => 0,
					'delivery_skipped_queued' => 0,
					'delivery_skipped_failed' => 0,
					'delivery_validation_failed' => 0,
					'delivery_skipped_other' => 0,
					'delivery_detail' => $delivery_error,
				);
				wp_safe_redirect(vms_pass_outreach_recipient_feedback_redirect_url(
					$campaign_id,
					$redirect_notice,
					0,
					in_array($action, array('resend_selected'), true) ? 'vms-outreach-recipient-list' : 'vms-outreach-delivery-status'
				));
				exit;
			}
			$campaign = (array) ($delivery_campaign['campaign'] ?? $campaign);
			$activated = !empty($delivery_campaign['activated']);

			if ($queue_review_token !== '') {
				vms_pass_outreach_clear_pending_queue_selection($queue_review_token);
			}

			if ($action === 'resend_selected') {
				$sent_count = 0;
				$skipped_not_sent = 0;
				$skipped_queued = 0;
				$skipped_failed = 0;
				$failed_count = 0;
				$detail_messages = array();

				foreach ($recipients as $recipient) {
					$stored_send_status = vms_pass_outreach_recipient_stored_send_status($recipient);
					$has_send_record = !empty($recipient['sent_at']) || $stored_send_status === 'sent';
					if (!$has_send_record) {
						if ($stored_send_status === 'queued') {
							$skipped_queued += 1;
						} elseif ($stored_send_status === 'failed') {
							$skipped_failed += 1;
						} else {
							$skipped_not_sent += 1;
						}
						continue;
					}

					$result = vms_pass_outreach_attempt_send_invite_email($recipient, $campaign, array(
						'resend' => true,
					));
					$result_status = sanitize_key((string) ($result['status'] ?? ''));
					if ($result_status === 'sent') {
						$sent_count += 1;
						continue;
					}
					if ($result_status === 'failed') {
						$failed_count += 1;
					} else {
						$skipped_not_sent += 1;
					}
					$result_message = sanitize_text_field((string) ($result['message'] ?? ''));
					if ($result_message !== '' && !in_array($result_message, $detail_messages, true)) {
						$detail_messages[] = $result_message;
					}
				}

				$message = sprintf(
					__('Resend run complete. Sent: %1$d. Skipped not previously sent: %2$d. Skipped queued: %3$d. Skipped failed: %4$d. Failed: %5$d.', 'backstage-outreach'),
					$sent_count,
					$skipped_not_sent,
					$skipped_queued,
					$skipped_failed,
					$failed_count
				);
				if (!empty($detail_messages)) {
					$message .= ' ' . sprintf(__('Details: %s', 'backstage-outreach'), implode(' ', array_slice($detail_messages, 0, 3)));
				}
				if ($activated) {
					$message = __('Campaign activated.', 'backstage-outreach') . ' ' . $message;
				}
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message($sent_count > 0 ? 'success' : 'error', $message);
				}
				wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id, 0, 'vms-outreach-recipient-list'));
				exit;
			}

			$queue_ids = array();
			$allow_failed_retry = false;
			$delivery_notice = 'queue_selected';
			$skip_sent = absint($analysis_counts['already_sent'] ?? 0);
			$skip_queued = absint($analysis_counts['already_queued'] ?? 0);
			$skip_failed = 0;
			$validation_failed = absint($analysis_counts['validation_failed'] ?? 0) + absint($analysis_counts['blocked'] ?? 0) + absint($analysis_counts['other'] ?? 0);
			$skip_other = 0;

			if ($action === 'queue_all_unsent') {
				$queue_ids = array_values((array) ($analysis['queueable_unsent_ids'] ?? array()));
				$skip_failed = absint($analysis_counts['failed_selected'] ?? 0);
				$delivery_notice = 'queue_all_unsent';
			} elseif ($action === 'retry_failed_selected') {
				$queue_ids = array_values((array) ($analysis['retryable_failed_ids'] ?? array()));
				$allow_failed_retry = true;
				$skip_failed = absint($analysis_counts['queueable_unsent'] ?? 0) + absint($analysis_counts['other'] ?? 0);
				$delivery_notice = 'retry_failed_selected';
			} else {
				$queue_ids = array_values((array) ($analysis['queueable_unsent_ids'] ?? array()));
				$skip_failed = absint($analysis_counts['failed_selected'] ?? 0);
			}

			$affected = 0;
			foreach ($queue_ids as $recipient_id) {
				if (!isset($recipient_lookup[$recipient_id])) {
					$skip_other += 1;
					continue;
				}
				$result = vms_pass_outreach_queue_recipient($recipient_lookup[$recipient_id], array(
					'allow_failed' => $allow_failed_retry,
				));
				if (is_wp_error($result)) {
					$error_code = sanitize_key($result->get_error_code());
					if ($error_code === 'recipient_already_sent') {
						$skip_sent += 1;
					} elseif ($error_code === 'recipient_already_queued') {
						$skip_queued += 1;
					} elseif ($error_code === 'recipient_retry_required') {
						$skip_failed += 1;
					} else {
						$validation_failed += 1;
					}
					$error_message = sanitize_text_field($result->get_error_message());
					if ($error_message !== '' && !in_array($error_message, $skip_messages, true)) {
						$skip_messages[] = $error_message;
					}
					continue;
				}
				$affected += 1;
			}

			$queue_message = $action === 'retry_failed_selected'
				? sprintf(
					__('Queued %1$d failed recipient(s) for another send attempt. Skipped already sent: %2$d. Skipped already queued: %3$d. Skipped not failed: %4$d. Validation failed: %5$d.', 'backstage-outreach'),
					$affected,
					$skip_sent,
					$skip_queued,
					$skip_failed,
					$validation_failed + $skip_other
				)
				: sprintf(
					__('Queued %1$d recipient(s). Skipped already sent: %2$d. Skipped already queued: %3$d. Skipped failed invites: %4$d. Failed validation: %5$d.', 'backstage-outreach'),
					$affected,
					$skip_sent,
					$skip_queued,
					$skip_failed,
					$validation_failed + $skip_other
				);
			if (!empty($skip_messages)) {
				$queue_message .= ' ' . sprintf(__('Details: %s', 'backstage-outreach'), implode(' ', array_slice($skip_messages, 0, 3)));
			}
			if ($activated) {
				$queue_message = __('Campaign activated.', 'backstage-outreach') . ' ' . $queue_message;
			}
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message($affected > 0 ? 'success' : 'error', $queue_message);
			}
			if (function_exists('vms_admission_audit_log')) {
				vms_admission_audit_log(0, null, 'pass_outreach_recipient_bulk_action', get_current_user_id(), 'admin', array(
					'campaign_id' => $campaign_id,
					'bulk_action' => $action,
					'affected_count' => $affected,
					'skipped_status_counts' => array(
						'already_sent' => $skip_sent,
						'already_queued' => $skip_queued,
						'failed_selected' => $skip_failed,
						'validation_failed' => $validation_failed,
						'skipped_other' => $skip_other,
					),
				));
			}

			wp_safe_redirect(vms_pass_outreach_recipient_feedback_redirect_url($campaign_id, array(
				'delivery_notice' => $delivery_notice,
				'delivery_affected' => $affected,
				'delivery_skipped_sent' => $skip_sent,
				'delivery_skipped_queued' => $skip_queued,
				'delivery_skipped_failed' => $skip_failed,
				'delivery_validation_failed' => $validation_failed,
				'delivery_skipped_other' => $skip_other,
				'delivery_activated' => $activated ? 1 : 0,
				'delivery_detail' => !empty($skip_messages)
					? sprintf(__('Details: %s', 'backstage-outreach'), implode(' ', array_slice($skip_messages, 0, 3)))
					: '',
			), 0, 'vms-outreach-delivery-status'));
			exit;
		}

		if (empty($recipient_ids)) {
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', __('Select at least one outreach recipient first.', 'backstage-outreach'));
			}
			wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id));
			exit;
		}

		$recipients = vms_pass_outreach_query_recipients_for_campaign($campaign_id, array(
			'limit' => 0,
			'ids' => $recipient_ids,
		));
		$affected = 0;
		$skipped = 0;
		$skipped_ids = array();
		$skip_status_counts = array();
		$skip_messages = array();

		foreach ($recipients as $recipient) {
			if ($action === 'mark_sent') {
				$result = vms_pass_outreach_mark_recipient_sent($recipient);
			} elseif ($action === 'mark_not_sent') {
				$result = vms_pass_outreach_mark_recipient_not_sent($recipient);
			} elseif ($action === 'revoke') {
				$result = vms_pass_outreach_revoke_recipient($recipient);
			} elseif ($action === 'delete') {
				$result = vms_pass_outreach_delete_recipient($recipient);
			} else {
				$result = new WP_Error('invalid_bulk_action', __('That bulk action is not supported.', 'backstage-outreach'));
			}

			if (is_wp_error($result)) {
				$skipped += 1;
				$skipped_ids[] = absint($recipient['id'] ?? 0);
				$skip_message = sanitize_text_field($result->get_error_message());
				if ($skip_message !== '' && !in_array($skip_message, $skip_messages, true)) {
					$skip_messages[] = $skip_message;
				}
				$skip_status = vms_pass_outreach_recipient_status_for_display($recipient, $campaign);
				if ($action === 'mark_sent') {
					$guardrail = vms_pass_outreach_recipient_contact_guardrail_state($recipient);
					$guardrail_send_status = vms_pass_outreach_send_status_from_guardrail($guardrail);
					$stored_send_status = vms_pass_outreach_recipient_stored_send_status($recipient);
					if ($guardrail_send_status !== '') {
						$skip_status = $guardrail_send_status;
					} elseif (in_array($stored_send_status, array('queued', 'sent', 'failed', 'suppressed', 'do_not_contact'), true)) {
						$skip_status = $stored_send_status;
					}
				}
				if (!isset($skip_status_counts[$skip_status])) {
					$skip_status_counts[$skip_status] = 0;
				}
				$skip_status_counts[$skip_status] += 1;
				continue;
			}
			$affected += 1;
		}

		$action_labels = array(
			'mark_sent' => __('marked as sent', 'backstage-outreach'),
			'mark_not_sent' => __('reset to not sent', 'backstage-outreach'),
			'revoke' => __('revoked', 'backstage-outreach'),
			'delete' => __('deleted', 'backstage-outreach'),
		);
		$message = sprintf(
			_n('%1$d outreach recipient %2$s.', '%1$d outreach recipients %2$s.', $affected, 'backstage-outreach'),
			$affected,
			(string) ($action_labels[$action] ?? __('updated', 'backstage-outreach'))
		);
		if ($skipped > 0) {
			$message .= ' ' . sprintf(
				_n('%1$d recipient skipped (%2$s).', '%1$d recipients skipped (%2$s).', $skipped, 'backstage-outreach'),
				$skipped,
				vms_pass_outreach_format_number_list($skipped_ids)
			);
			if ($action === 'mark_sent' && !empty($skip_status_counts)) {
				$skip_status_labels = array_merge(vms_pass_outreach_recipient_status_labels(), vms_pass_outreach_send_status_labels());
				$parts = array();
				foreach ($skip_status_counts as $status_key => $count) {
					$parts[] = sprintf(
						'%1$s: %2$d',
						(string) ($skip_status_labels[$status_key] ?? $status_key),
						(int) $count
					);
				}
				$message .= ' ' . sprintf(__('Skip reasons: %s.', 'backstage-outreach'), implode(', ', $parts));
			}
			if (!empty($skip_messages)) {
				$message .= ' ' . sprintf(__('Details: %s', 'backstage-outreach'), implode(' ', array_slice($skip_messages, 0, 3)));
			}
		}
		if (function_exists('vms_pass_claims_set_user_message')) {
			vms_pass_claims_set_user_message($affected === 0 ? 'error' : 'success', $message);
		}

		if (function_exists('vms_admission_audit_log')) {
			vms_admission_audit_log(0, null, 'pass_outreach_recipient_bulk_action', get_current_user_id(), 'admin', array(
				'campaign_id' => $campaign_id,
				'bulk_action' => $action,
				'affected_count' => $affected,
				'skipped_count' => $skipped,
				'skipped_status_counts' => $skip_status_counts,
				'skipped_ids' => $skipped_ids,
			));
		}

		wp_safe_redirect(vms_pass_outreach_recipient_feedback_redirect_url($campaign_id, array(), 0, 'vms-outreach-recipient-list'));
		exit;
	}
}
add_action('admin_post_vms_pass_outreach_recipient_bulk', 'vms_pass_outreach_handle_recipient_bulk');

if (!function_exists('vms_pass_outreach_handle_recipient_mark_sent')) {
	function vms_pass_outreach_handle_recipient_mark_sent(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-outreach'));
		}

		$recipient_id = isset($_REQUEST['recipient_id']) ? absint(wp_unslash($_REQUEST['recipient_id'])) : 0;
		$campaign_id = isset($_REQUEST['campaign_id']) ? absint(wp_unslash($_REQUEST['campaign_id'])) : 0;
		$nonce = (isset($_REQUEST['_wpnonce']) && !is_array($_REQUEST['_wpnonce'])) ? sanitize_text_field(wp_unslash((string) $_REQUEST['_wpnonce'])) : '';
		if ($recipient_id <= 0 || !wp_verify_nonce($nonce, 'vms_pass_outreach_recipient_mark_sent_' . $recipient_id)) {
			wp_die(esc_html__('Invalid request.', 'backstage-outreach'));
		}

		$recipient = vms_pass_outreach_get_recipient_by_id($recipient_id);
		if (!is_array($recipient)) {
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', __('Outreach recipient not found.', 'backstage-outreach'));
			}
			wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id));
			exit;
		}

		$result = vms_pass_outreach_mark_recipient_sent($recipient);
		if (function_exists('vms_pass_claims_set_user_message')) {
			vms_pass_claims_set_user_message(is_wp_error($result) ? 'error' : 'success', is_wp_error($result) ? $result->get_error_message() : __('Invite marked as sent.', 'backstage-outreach'));
		}

		wp_safe_redirect(vms_pass_outreach_recipient_redirect_url(absint($recipient['campaign_id'] ?? $campaign_id), $recipient_id));
		exit;
	}
}
add_action('admin_post_vms_pass_outreach_recipient_mark_sent', 'vms_pass_outreach_handle_recipient_mark_sent');

if (!function_exists('vms_pass_outreach_handle_recipient_mark_not_sent')) {
	function vms_pass_outreach_handle_recipient_mark_not_sent(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-outreach'));
		}

		$recipient_id = isset($_REQUEST['recipient_id']) ? absint(wp_unslash($_REQUEST['recipient_id'])) : 0;
		$campaign_id = isset($_REQUEST['campaign_id']) ? absint(wp_unslash($_REQUEST['campaign_id'])) : 0;
		$nonce = (isset($_REQUEST['_wpnonce']) && !is_array($_REQUEST['_wpnonce'])) ? sanitize_text_field(wp_unslash((string) $_REQUEST['_wpnonce'])) : '';
		if ($recipient_id <= 0 || !wp_verify_nonce($nonce, 'vms_pass_outreach_recipient_mark_not_sent_' . $recipient_id)) {
			wp_die(esc_html__('Invalid request.', 'backstage-outreach'));
		}

		$recipient = vms_pass_outreach_get_recipient_by_id($recipient_id);
		if (!is_array($recipient)) {
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', __('Outreach recipient not found.', 'backstage-outreach'));
			}
			wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id));
			exit;
		}

		$result = vms_pass_outreach_mark_recipient_not_sent($recipient);
		if (function_exists('vms_pass_claims_set_user_message')) {
			vms_pass_claims_set_user_message(is_wp_error($result) ? 'error' : 'success', is_wp_error($result) ? $result->get_error_message() : __('Invite reset to Not Sent.', 'backstage-outreach'));
		}

		wp_safe_redirect(vms_pass_outreach_recipient_redirect_url(absint($recipient['campaign_id'] ?? $campaign_id), $recipient_id));
		exit;
	}
}
add_action('admin_post_vms_pass_outreach_recipient_mark_not_sent', 'vms_pass_outreach_handle_recipient_mark_not_sent');

if (!function_exists('vms_pass_outreach_handle_recipient_queue')) {
	function vms_pass_outreach_handle_recipient_queue(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-outreach'));
		}

		$recipient_id = isset($_REQUEST['recipient_id']) ? absint(wp_unslash($_REQUEST['recipient_id'])) : 0;
		$campaign_id = isset($_REQUEST['campaign_id']) ? absint(wp_unslash($_REQUEST['campaign_id'])) : 0;
		$retry_failed = !empty($_REQUEST['retry_failed']);
		$nonce = (isset($_REQUEST['_wpnonce']) && !is_array($_REQUEST['_wpnonce'])) ? sanitize_text_field(wp_unslash((string) $_REQUEST['_wpnonce'])) : '';
		if ($recipient_id <= 0 || !wp_verify_nonce($nonce, 'vms_pass_outreach_recipient_queue_' . $recipient_id)) {
			wp_die(esc_html__('Invalid request.', 'backstage-outreach'));
		}

		$recipient = vms_pass_outreach_get_recipient_by_id($recipient_id);
		if (!is_array($recipient)) {
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', __('Outreach recipient not found.', 'backstage-outreach'));
			}
			wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id));
			exit;
		}

		$campaign = vms_pass_outreach_get_campaign_by_id(absint($recipient['campaign_id'] ?? $campaign_id));
		$delivery_campaign = is_array($campaign)
			? vms_pass_outreach_prepare_campaign_for_delivery($campaign, array(
				'activate_campaign' => !empty($_REQUEST['activate_campaign']),
			))
			: new WP_Error('campaign_missing', __('Outreach campaign not found.', 'backstage-outreach'));
		if (is_wp_error($delivery_campaign)) {
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', $delivery_campaign->get_error_message());
			}
			wp_safe_redirect(vms_pass_outreach_recipient_redirect_url(absint($recipient['campaign_id'] ?? $campaign_id), $recipient_id));
			exit;
		}

		$result = vms_pass_outreach_queue_recipient($recipient, array(
			'allow_failed' => $retry_failed,
		));
		if (function_exists('vms_pass_claims_set_user_message')) {
			$message = is_wp_error($result)
				? $result->get_error_message()
				: ($retry_failed ? __('Failed invite queued for another send attempt.', 'backstage-outreach') : __('Recipient queued for sending.', 'backstage-outreach'));
			if (!empty($delivery_campaign['activated'])) {
				$message = __('Campaign activated.', 'backstage-outreach') . ' ' . $message;
			}
			vms_pass_claims_set_user_message(is_wp_error($result) ? 'error' : 'success', $message);
		}

		wp_safe_redirect(vms_pass_outreach_recipient_redirect_url(absint($recipient['campaign_id'] ?? $campaign_id), $recipient_id));
		exit;
	}
}
add_action('admin_post_vms_pass_outreach_recipient_queue', 'vms_pass_outreach_handle_recipient_queue');

if (!function_exists('vms_pass_outreach_handle_recipient_send_email')) {
	function vms_pass_outreach_handle_recipient_send_email(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-outreach'));
		}

		$recipient_id = isset($_REQUEST['recipient_id']) ? absint(wp_unslash($_REQUEST['recipient_id'])) : 0;
		$campaign_id = isset($_REQUEST['campaign_id']) ? absint(wp_unslash($_REQUEST['campaign_id'])) : 0;
		$resend = !empty($_REQUEST['resend']);
		$nonce = (isset($_REQUEST['_wpnonce']) && !is_array($_REQUEST['_wpnonce'])) ? sanitize_text_field(wp_unslash((string) $_REQUEST['_wpnonce'])) : '';
		if ($recipient_id <= 0 || !wp_verify_nonce($nonce, 'vms_pass_outreach_recipient_send_email_' . $recipient_id)) {
			wp_die(esc_html__('Invalid request.', 'backstage-outreach'));
		}

		$recipient = vms_pass_outreach_get_recipient_by_id($recipient_id);
		if (!is_array($recipient)) {
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', __('Outreach recipient not found.', 'backstage-outreach'));
			}
			wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id));
			exit;
		}

		$campaign = vms_pass_outreach_get_campaign_by_id(absint($recipient['campaign_id'] ?? $campaign_id));
		$delivery_campaign = is_array($campaign)
			? vms_pass_outreach_prepare_campaign_for_delivery($campaign, array(
				'activate_campaign' => !empty($_REQUEST['activate_campaign']),
			))
			: new WP_Error('campaign_missing', __('Outreach campaign not found.', 'backstage-outreach'));
		if (is_wp_error($delivery_campaign)) {
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', $delivery_campaign->get_error_message());
			}
			wp_safe_redirect(vms_pass_outreach_recipient_redirect_url(absint($recipient['campaign_id'] ?? $campaign_id), $recipient_id));
			exit;
		}

		$campaign = (array) ($delivery_campaign['campaign'] ?? $campaign);
		$result = vms_pass_outreach_attempt_send_invite_email($recipient, $campaign, array(
			'resend' => $resend,
		));
		$message_type = ($result['status'] ?? '') === 'sent' ? 'success' : 'error';
		if (function_exists('vms_pass_claims_set_user_message')) {
			$message = (string) ($result['message'] ?? __('Could not send the outreach invite.', 'backstage-outreach'));
			if (!empty($delivery_campaign['activated'])) {
				$message = __('Campaign activated.', 'backstage-outreach') . ' ' . $message;
			}
			vms_pass_claims_set_user_message($message_type, $message);
		}

		wp_safe_redirect(vms_pass_outreach_recipient_redirect_url(absint($recipient['campaign_id'] ?? $campaign_id), $recipient_id));
		exit;
	}
}
add_action('admin_post_vms_pass_outreach_recipient_send_email', 'vms_pass_outreach_handle_recipient_send_email');

if (!function_exists('vms_pass_outreach_handle_recipient_mark_interested')) {
	function vms_pass_outreach_handle_recipient_mark_interested(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-outreach'));
		}

		$recipient_id = isset($_REQUEST['recipient_id']) ? absint(wp_unslash($_REQUEST['recipient_id'])) : 0;
		$campaign_id = isset($_REQUEST['campaign_id']) ? absint(wp_unslash($_REQUEST['campaign_id'])) : 0;
		$nonce = (isset($_REQUEST['_wpnonce']) && !is_array($_REQUEST['_wpnonce'])) ? sanitize_text_field(wp_unslash((string) $_REQUEST['_wpnonce'])) : '';
		if ($recipient_id <= 0 || !wp_verify_nonce($nonce, 'vms_pass_outreach_recipient_mark_interested_' . $recipient_id)) {
			wp_die(esc_html__('Invalid request.', 'backstage-outreach'));
		}

		$recipient = vms_pass_outreach_get_recipient_by_id($recipient_id);
		if (!is_array($recipient)) {
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', __('Outreach recipient not found.', 'backstage-outreach'));
			}
			wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id));
			exit;
		}

		$result = vms_pass_outreach_mark_recipient_interested($recipient);
		if (function_exists('vms_pass_claims_set_user_message')) {
			vms_pass_claims_set_user_message(is_wp_error($result) ? 'error' : 'success', is_wp_error($result) ? $result->get_error_message() : __('Linked contact marked Interested.', 'backstage-outreach'));
		}

		wp_safe_redirect(vms_pass_outreach_recipient_redirect_url(absint($recipient['campaign_id'] ?? $campaign_id), $recipient_id));
		exit;
	}
}
add_action('admin_post_vms_pass_outreach_recipient_mark_interested', 'vms_pass_outreach_handle_recipient_mark_interested');

if (!function_exists('vms_pass_outreach_handle_recipient_suppress')) {
	function vms_pass_outreach_handle_recipient_suppress(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-outreach'));
		}

		$recipient_id = isset($_REQUEST['recipient_id']) ? absint(wp_unslash($_REQUEST['recipient_id'])) : 0;
		$campaign_id = isset($_REQUEST['campaign_id']) ? absint(wp_unslash($_REQUEST['campaign_id'])) : 0;
		$nonce = (isset($_REQUEST['_wpnonce']) && !is_array($_REQUEST['_wpnonce'])) ? sanitize_text_field(wp_unslash((string) $_REQUEST['_wpnonce'])) : '';
		if ($recipient_id <= 0 || !wp_verify_nonce($nonce, 'vms_pass_outreach_recipient_suppress_' . $recipient_id)) {
			wp_die(esc_html__('Invalid request.', 'backstage-outreach'));
		}

		$recipient = vms_pass_outreach_get_recipient_by_id($recipient_id);
		if (!is_array($recipient)) {
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', __('Outreach recipient not found.', 'backstage-outreach'));
			}
			wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id));
			exit;
		}

		$result = vms_pass_outreach_suppress_recipient($recipient);
		if (function_exists('vms_pass_claims_set_user_message')) {
			vms_pass_claims_set_user_message(is_wp_error($result) ? 'error' : 'success', is_wp_error($result) ? $result->get_error_message() : __('Recipient suppressed for outreach.', 'backstage-outreach'));
		}

		wp_safe_redirect(vms_pass_outreach_recipient_redirect_url(absint($recipient['campaign_id'] ?? $campaign_id), $recipient_id));
		exit;
	}
}
add_action('admin_post_vms_pass_outreach_recipient_suppress', 'vms_pass_outreach_handle_recipient_suppress');

if (!function_exists('vms_pass_outreach_handle_recipient_do_not_contact')) {
	function vms_pass_outreach_handle_recipient_do_not_contact(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-outreach'));
		}

		$recipient_id = isset($_REQUEST['recipient_id']) ? absint(wp_unslash($_REQUEST['recipient_id'])) : 0;
		$campaign_id = isset($_REQUEST['campaign_id']) ? absint(wp_unslash($_REQUEST['campaign_id'])) : 0;
		$nonce = (isset($_REQUEST['_wpnonce']) && !is_array($_REQUEST['_wpnonce'])) ? sanitize_text_field(wp_unslash((string) $_REQUEST['_wpnonce'])) : '';
		if ($recipient_id <= 0 || !wp_verify_nonce($nonce, 'vms_pass_outreach_recipient_do_not_contact_' . $recipient_id)) {
			wp_die(esc_html__('Invalid request.', 'backstage-outreach'));
		}

		$recipient = vms_pass_outreach_get_recipient_by_id($recipient_id);
		if (!is_array($recipient)) {
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', __('Outreach recipient not found.', 'backstage-outreach'));
			}
			wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id));
			exit;
		}

		$result = vms_pass_outreach_mark_recipient_do_not_contact($recipient);
		if (function_exists('vms_pass_claims_set_user_message')) {
			vms_pass_claims_set_user_message(is_wp_error($result) ? 'error' : 'success', is_wp_error($result) ? $result->get_error_message() : __('Recipient marked Do Not Contact.', 'backstage-outreach'));
		}

		wp_safe_redirect(vms_pass_outreach_recipient_redirect_url(absint($recipient['campaign_id'] ?? $campaign_id), $recipient_id));
		exit;
	}
}
add_action('admin_post_vms_pass_outreach_recipient_do_not_contact', 'vms_pass_outreach_handle_recipient_do_not_contact');

if (!function_exists('vms_pass_outreach_handle_send_queued_recipients')) {
	function vms_pass_outreach_handle_send_queued_recipients(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-outreach'));
		}

		check_admin_referer('vms_pass_outreach_send_queued_recipients');

		$campaign_id = isset($_POST['campaign_id']) ? absint(wp_unslash($_POST['campaign_id'])) : 0;
		$batch_size = isset($_POST['batch_size']) ? absint(wp_unslash($_POST['batch_size'])) : vms_pass_outreach_default_send_batch_size();
		$campaign = vms_pass_outreach_get_campaign_by_id($campaign_id);
		if (!is_array($campaign)) {
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', __('Outreach campaign not found.', 'backstage-outreach'));
			}
			wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id));
			exit;
		}

		$delivery_campaign = vms_pass_outreach_prepare_campaign_for_delivery($campaign, array(
			'activate_campaign' => !empty($_POST['activate_campaign']),
		));
		if (is_wp_error($delivery_campaign)) {
			$message = $delivery_campaign->get_error_message();
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', $message);
			}
			wp_safe_redirect(vms_pass_outreach_recipient_feedback_redirect_url($campaign_id, array(
				'delivery_notice' => 'send_none',
				'delivery_detail' => $message,
			), 0, 'vms-outreach-delivery-status'));
			exit;
		}
		$campaign = (array) ($delivery_campaign['campaign'] ?? $campaign);

		$summary = vms_pass_outreach_send_next_queued_recipients($campaign, $batch_size);
		$message = sprintf(
			__('Queued send run complete. Sent: %1$d. Skipped: %2$d. Failed: %3$d.', 'backstage-outreach'),
			(int) ($summary['sent'] ?? 0),
			(int) ($summary['skipped'] ?? 0),
			(int) ($summary['failed'] ?? 0)
		);
		$message_type = (($summary['sent'] ?? 0) > 0) ? 'success' : 'error';
		$detail_message = vms_pass_outreach_result_detail_message((array) ($summary['results'] ?? array()));
		if (!empty($summary['error_message'])) {
			$message = (string) $summary['error_message'];
		} elseif (($summary['queued_found'] ?? 0) <= 0) {
			$message = __('No queued recipients were ready to send.', 'backstage-outreach');
		} elseif ($detail_message !== '') {
			$message .= ' ' . $detail_message;
		}
		if (!empty($delivery_campaign['activated'])) {
			$message = __('Campaign activated.', 'backstage-outreach') . ' ' . $message;
		}
		if (function_exists('vms_pass_claims_set_user_message')) {
			vms_pass_claims_set_user_message($message_type, $message);
		}

		wp_safe_redirect(vms_pass_outreach_recipient_feedback_redirect_url($campaign_id, array(
			'delivery_notice' => (($summary['queued_found'] ?? 0) > 0) ? 'send_batch' : 'send_none',
			'delivery_sent' => (int) ($summary['sent'] ?? 0),
			'delivery_skipped' => (int) ($summary['skipped'] ?? 0),
			'delivery_failed' => (int) ($summary['failed'] ?? 0),
			'delivery_activated' => !empty($delivery_campaign['activated']) ? 1 : 0,
			'delivery_detail' => $detail_message,
		), 0, 'vms-outreach-delivery-status'));
		exit;
	}
}
add_action('admin_post_vms_pass_outreach_send_queued_recipients', 'vms_pass_outreach_handle_send_queued_recipients');

if (!function_exists('vms_pass_outreach_handle_recipient_revoke')) {
	function vms_pass_outreach_handle_recipient_revoke(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-outreach'));
		}

		$recipient_id = isset($_REQUEST['recipient_id']) ? absint(wp_unslash($_REQUEST['recipient_id'])) : 0;
		$campaign_id = isset($_REQUEST['campaign_id']) ? absint(wp_unslash($_REQUEST['campaign_id'])) : 0;
		$nonce = (isset($_REQUEST['_wpnonce']) && !is_array($_REQUEST['_wpnonce'])) ? sanitize_text_field(wp_unslash((string) $_REQUEST['_wpnonce'])) : '';
		if ($recipient_id <= 0 || !wp_verify_nonce($nonce, 'vms_pass_outreach_recipient_revoke_' . $recipient_id)) {
			wp_die(esc_html__('Invalid request.', 'backstage-outreach'));
		}

		$recipient = vms_pass_outreach_get_recipient_by_id($recipient_id);
		if (!is_array($recipient)) {
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', __('Outreach recipient not found.', 'backstage-outreach'));
			}
			wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id));
			exit;
		}

		$result = vms_pass_outreach_revoke_recipient($recipient);
		if (function_exists('vms_pass_claims_set_user_message')) {
			vms_pass_claims_set_user_message(is_wp_error($result) ? 'error' : 'success', is_wp_error($result) ? $result->get_error_message() : __('Invite revoked.', 'backstage-outreach'));
		}

		wp_safe_redirect(vms_pass_outreach_recipient_redirect_url(absint($recipient['campaign_id'] ?? $campaign_id), $recipient_id));
		exit;
	}
}
add_action('admin_post_vms_pass_outreach_recipient_revoke', 'vms_pass_outreach_handle_recipient_revoke');

if (!function_exists('vms_pass_outreach_handle_recipient_delete')) {
	function vms_pass_outreach_handle_recipient_delete(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-outreach'));
		}

		$recipient_id = isset($_REQUEST['recipient_id']) ? absint(wp_unslash($_REQUEST['recipient_id'])) : 0;
		$campaign_id = isset($_REQUEST['campaign_id']) ? absint(wp_unslash($_REQUEST['campaign_id'])) : 0;
		$nonce = (isset($_REQUEST['_wpnonce']) && !is_array($_REQUEST['_wpnonce'])) ? sanitize_text_field(wp_unslash((string) $_REQUEST['_wpnonce'])) : '';
		if ($recipient_id <= 0 || !wp_verify_nonce($nonce, 'vms_pass_outreach_recipient_delete_' . $recipient_id)) {
			wp_die(esc_html__('Invalid request.', 'backstage-outreach'));
		}

		$recipient = vms_pass_outreach_get_recipient_by_id($recipient_id);
		if (!is_array($recipient)) {
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', __('Outreach recipient not found.', 'backstage-outreach'));
			}
			wp_safe_redirect(vms_pass_outreach_recipient_redirect_url($campaign_id));
			exit;
		}

		$result = vms_pass_outreach_delete_recipient($recipient);
		if (function_exists('vms_pass_claims_set_user_message')) {
			vms_pass_claims_set_user_message(is_wp_error($result) ? 'error' : 'success', is_wp_error($result) ? $result->get_error_message() : __('Outreach recipient deleted.', 'backstage-outreach'));
		}

		wp_safe_redirect(vms_pass_outreach_recipient_redirect_url(absint($recipient['campaign_id'] ?? $campaign_id)));
		exit;
	}
}
add_action('admin_post_vms_pass_outreach_recipient_delete', 'vms_pass_outreach_handle_recipient_delete');

if (!function_exists('vms_pass_outreach_recipient_checked_in_count')) {
	function vms_pass_outreach_recipient_checked_in_count(array $recipient, ?array $checked_in_map = null): int
	{
		$claim_id = absint($recipient['pass_claim_id'] ?? 0);
		if ($claim_id <= 0) {
			return 0;
		}

		if (is_array($checked_in_map) && isset($checked_in_map[$claim_id]['checked_in_headcount'])) {
			return max(0, absint($checked_in_map[$claim_id]['checked_in_headcount']));
		}

		global $wpdb;
		$entries_table = vms_admission_table_entries();
		$count = $wpdb->get_var($wpdb->prepare(
			"SELECT COALESCE(SUM(checked_in_qty), 0)
			FROM {$entries_table}
			WHERE pass_claim_id = %d
				AND status <> 'canceled'
				AND checked_in_qty > 0",
			$claim_id
		));

		return max(0, absint($count));
	}
}

if (!function_exists('vms_pass_outreach_is_recipient_expired')) {
	function vms_pass_outreach_is_recipient_expired(array $recipient, ?array $campaign = null): bool
	{
		$recipient_expires = trim((string) ($recipient['expires_at'] ?? ''));
		if ($recipient_expires !== '') {
			try {
				$recipient_dt = new DateTimeImmutable($recipient_expires, wp_timezone());
				if ($recipient_dt->getTimestamp() < time()) {
					return true;
				}
			} catch (Exception $e) {
				// Ignore malformed recipient expiration.
			}
		}

		if (is_array($campaign) && function_exists('vms_pass_outreach_is_campaign_expired')) {
			return vms_pass_outreach_is_campaign_expired($campaign);
		}

		return false;
	}
}

if (!function_exists('vms_pass_outreach_recipient_status_for_display')) {
	function vms_pass_outreach_recipient_status_for_display(array $recipient, ?array $campaign = null, ?array $checked_in_map = null): string
	{
		if (!empty($recipient['revoked_at']) || sanitize_key((string) ($recipient['status'] ?? '')) === 'revoked') {
			return 'revoked';
		}

		if (empty($recipient['claimed_at']) && vms_pass_outreach_is_recipient_expired($recipient, $campaign)) {
			return 'expired';
		}

		if (!empty($recipient['claimed_at'])) {
			$claimed_headcount = max(0, absint($recipient['claimed_headcount'] ?? 0));
			$checked_in = vms_pass_outreach_recipient_checked_in_count($recipient, $checked_in_map);
			if ($claimed_headcount > 0 && $checked_in >= $claimed_headcount) {
				return 'used';
			}
			if ($checked_in > 0) {
				return 'partially_used';
			}
			return 'claimed';
		}

		if (!empty($recipient['sent_at'])) {
			return 'sent';
		}

		$status = sanitize_key((string) ($recipient['status'] ?? 'ready'));
		return in_array($status, vms_pass_outreach_allowed_recipient_statuses(), true) ? $status : 'ready';
	}
}

if (!function_exists('vms_pass_outreach_set_public_context')) {
	function vms_pass_outreach_set_public_context(array $context): void
	{
		$GLOBALS['vms_pass_outreach_public_context'] = $context;
	}
}

if (!function_exists('vms_pass_outreach_get_public_context')) {
	function vms_pass_outreach_get_public_context(): array
	{
		$context = $GLOBALS['vms_pass_outreach_public_context'] ?? array();
		return is_array($context) ? $context : array();
	}
}

if (!function_exists('vms_pass_outreach_clear_public_context')) {
	function vms_pass_outreach_clear_public_context(): void
	{
		unset($GLOBALS['vms_pass_outreach_public_context']);
	}
}

if (!function_exists('vms_pass_outreach_resolve_public_invite_context')) {
	function vms_pass_outreach_resolve_public_invite_context(string $invite_token)
	{
		$recipient = vms_pass_outreach_get_recipient_by_invite_token($invite_token);
		if (!is_array($recipient)) {
			return new WP_Error('invalid_invite_token', __('Invalid invite token.', 'backstage-outreach'));
		}

		$campaign = vms_pass_outreach_get_campaign_by_id(absint($recipient['campaign_id'] ?? 0));
		if (!is_array($campaign)) {
			return new WP_Error('invalid_campaign', __('Linked outreach campaign not found.', 'backstage-outreach'));
		}

		$batch = vms_pass_outreach_campaign_batch($campaign);
		if (!is_array($batch)) {
			return new WP_Error('campaign_missing_batch', __('Linked Guest Pass batch not found for this outreach invite.', 'backstage-outreach'));
		}

		$token_row = vms_pass_outreach_get_pass_token_row_for_recipient($recipient);
		if (!is_array($token_row)) {
			return new WP_Error('invite_token_not_ready', __('This outreach invite does not have a reserved Guest Pass token.', 'backstage-outreach'));
		}

		return array(
			'recipient' => $recipient,
			'campaign' => $campaign,
			'batch' => $batch,
			'token_row' => $token_row,
		);
	}
}

if (!function_exists('vms_pass_outreach_claim_context_for_token')) {
	function vms_pass_outreach_claim_context_for_token(array $token_row, array $batch): array
	{
		$token_id = absint($token_row['id'] ?? 0);
		$active_context = vms_pass_outreach_get_public_context();
		if ($token_id > 0 && absint($active_context['token_row']['id'] ?? 0) === $token_id) {
			return $active_context;
		}

		$recipient = $token_id > 0 ? vms_pass_outreach_get_recipient_by_pass_token_id($token_id) : null;
		if (!is_array($recipient)) {
			return array();
		}

		$campaign = vms_pass_outreach_get_campaign_by_id(absint($recipient['campaign_id'] ?? 0));
		return array(
			'recipient' => $recipient,
			'campaign' => is_array($campaign) ? $campaign : null,
			'batch' => $batch,
			'token_row' => $token_row,
		);
	}
}

if (!function_exists('vms_pass_outreach_recipient_preflight')) {
	function vms_pass_outreach_recipient_preflight(array $recipient, array $campaign, array $batch, array $token_row): array
	{
		if (absint($recipient['campaign_id'] ?? 0) !== absint($campaign['id'] ?? 0)) {
			return array(
				'ok' => false,
				'reason_code' => 'invalid_invite_token',
				'admin_reasons' => array('Recipient campaign mismatch'),
				'details' => array(
					'campaign_id' => absint($campaign['id'] ?? 0),
					'recipient_id' => absint($recipient['id'] ?? 0),
				),
			);
		}

		if (absint($campaign['related_batch_id'] ?? 0) !== absint($batch['id'] ?? 0)) {
			return array(
				'ok' => false,
				'reason_code' => 'campaign_missing_batch',
				'admin_reasons' => array('Campaign batch mapping mismatch'),
				'details' => array(
					'campaign_id' => absint($campaign['id'] ?? 0),
					'batch_id' => absint($batch['id'] ?? 0),
				),
			);
		}

		if (absint($recipient['pass_token_id'] ?? 0) !== absint($token_row['id'] ?? 0)) {
			return array(
				'ok' => false,
				'reason_code' => 'invite_token_mismatch',
				'admin_reasons' => array('Invite token does not match reserved Guest Pass token'),
				'details' => array(
					'recipient_id' => absint($recipient['id'] ?? 0),
					'pass_token_id' => absint($recipient['pass_token_id'] ?? 0),
					'resolved_token_id' => absint($token_row['id'] ?? 0),
				),
			);
		}

		if (!empty($recipient['revoked_at']) || sanitize_key((string) ($recipient['status'] ?? '')) === 'revoked') {
			return array(
				'ok' => false,
				'reason_code' => 'invite_revoked',
				'admin_reasons' => array('Invite revoked'),
				'details' => array(
					'recipient_id' => absint($recipient['id'] ?? 0),
				),
			);
		}

		if (!empty($recipient['claimed_at'])) {
			return array(
				'ok' => false,
				'reason_code' => 'recipient_already_claimed',
				'admin_reasons' => array('Invite already claimed by outreach recipient'),
				'details' => array(
					'recipient_id' => absint($recipient['id'] ?? 0),
					'pass_claim_id' => absint($recipient['pass_claim_id'] ?? 0),
				),
			);
		}

		if (vms_pass_outreach_is_recipient_expired($recipient, $campaign)) {
			return array(
				'ok' => false,
				'reason_code' => 'invite_expired',
				'admin_reasons' => array('Invite expired'),
				'details' => array(
					'recipient_id' => absint($recipient['id'] ?? 0),
				),
			);
		}

		return array(
			'ok' => true,
			'reason_code' => '',
			'admin_reasons' => array(),
			'details' => array(),
		);
	}
}

if (!function_exists('vms_pass_outreach_evaluate_recipient_claim')) {
	function vms_pass_outreach_evaluate_recipient_claim(array $recipient, array $campaign, array $batch, array $token_row, array $event_plan, array $claimant): array
	{
		$recipient_preflight = vms_pass_outreach_recipient_preflight($recipient, $campaign, $batch, $token_row);
		if (empty($recipient_preflight['ok'])) {
			return $recipient_preflight;
		}

		if (!function_exists('vms_pass_outreach_evaluate_claim')) {
			return array(
				'ok' => true,
				'reason_code' => '',
				'admin_reasons' => array(),
				'details' => array(),
			);
		}

		return vms_pass_outreach_evaluate_claim($campaign, $batch, $event_plan, $claimant);
	}
}

if (!function_exists('vms_pass_outreach_record_recipient_claim_success')) {
	function vms_pass_outreach_record_recipient_claim_success(array $recipient, array $campaign, int $claim_id, int $entry_id, int $party_size, int $token_id): void
	{
		$recipient_id = absint($recipient['id'] ?? 0);
		if ($recipient_id <= 0) {
			return;
		}

		global $wpdb;
		$table = vms_pass_outreach_recipient_table();
		$now = function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql');
			$wpdb->update(
				$table,
				array(
					'status' => 'claimed',
					'claimed_at' => $now,
					'claimed_headcount' => max(1, $party_size),
					'pass_claim_id' => $claim_id,
					'reservation_entry_id' => $entry_id,
					'updated_by' => 0,
					'updated_at' => $now,
				),
				array('id' => $recipient_id),
				array('%s', '%s', '%d', '%d', '%d', '%d', '%s'),
				array('%d')
			);

		if (function_exists('vms_admission_audit_log')) {
			vms_admission_audit_log(0, $entry_id > 0 ? $entry_id : null, 'pass_outreach_recipient_claim', 0, 'public', array(
				'campaign_id' => absint($campaign['id'] ?? 0),
				'recipient_id' => $recipient_id,
				'token_id' => $token_id,
				'claim_id' => $claim_id,
				'claimed_headcount' => max(1, $party_size),
			));
		}
	}
}

if (!function_exists('vms_pass_outreach_format_ratio')) {
	function vms_pass_outreach_format_ratio($ratio): string
	{
		if ($ratio === null) {
			return '—';
		}
		return number_format_i18n(((float) $ratio) * 100, 1) . '%';
	}
}

if (!function_exists('vms_pass_outreach_format_number_list')) {
	function vms_pass_outreach_format_number_list(array $numbers, int $limit = 20): string
	{
		$numbers = array_values(array_filter(array_map('absint', $numbers)));
		if (empty($numbers)) {
			return '';
		}
		$display = array_slice($numbers, 0, max(1, $limit));
		$label = implode(', ', $display);
		if (count($numbers) > count($display)) {
			$label .= sprintf(__(' +%d more', 'backstage-outreach'), count($numbers) - count($display));
		}
		return $label;
	}
}

if (!function_exists('vms_pass_outreach_render_recipients_panel')) {
	function vms_pass_outreach_render_recipients_panel(array $campaign): void
	{
		$campaign_id = absint($campaign['id'] ?? 0);
		if ($campaign_id <= 0) {
			return;
		}

		$campaign = vms_pass_outreach_get_campaign_by_id($campaign_id);
		if (!is_array($campaign)) {
			return;
		}

		echo '<section id="vms-outreach-recipients" class="vms-pass-card">';
		echo '<h2>' . esc_html__('Outreach Recipients', 'backstage-outreach') . '</h2>';

		if (!vms_pass_outreach_campaign_supports_recipients($campaign)) {
			$choose_batch_url = function_exists('vms_pass_outreach_admin_page_url')
				? vms_pass_outreach_admin_page_url(array(
					'campaign_id' => $campaign_id,
				)) . '#vms-outreach-batch-field'
				: admin_url('admin.php?page=vms-passes');
			$batches_url = function_exists('vms_pass_claims_admin_page_url')
				? vms_pass_claims_admin_page_url(array('tab' => 'batches'))
				: admin_url('admin.php?page=vms-passes');
			echo '<div class="vms-pass-callout vms-pass-callout-warning">';
			echo '<h3>' . esc_html__('Recipient Import Unavailable', 'backstage-outreach') . '</h3>';
			echo '<p>' . esc_html__('Recipient import is unavailable because this campaign is not linked to a Guest Pass Batch / Invite Link Pool. Choose a batch to import recipients and generate invite links.', 'backstage-outreach') . '</p>';
			echo '<p class="vms-pass-actions"><a class="button button-primary" href="' . esc_url($choose_batch_url) . '">' . esc_html__('Choose Batch', 'backstage-outreach') . '</a> <a class="button" href="' . esc_url($batches_url) . '">' . esc_html__('Go to Batches', 'backstage-outreach') . '</a></p>';
			echo '</div>';
			echo '</section>';
			return;
		}

		$page_slug = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : (function_exists('vms_outreach_admin_menu_slug') ? vms_outreach_admin_menu_slug() : 'vms-passes');
		$search = isset($_GET['recipient_search']) ? sanitize_text_field((string) wp_unslash($_GET['recipient_search'])) : '';
		$status_filter = isset($_GET['recipient_status']) ? sanitize_key((string) wp_unslash($_GET['recipient_status'])) : '';
		$group_filter = isset($_GET['recipient_group_label']) ? sanitize_text_field((string) wp_unslash($_GET['recipient_group_label'])) : '';
		$recipient_filters_action = admin_url('admin.php') . '#vms-outreach-recipient-filters';
		$recipient_filters_reset_url = vms_pass_outreach_recipient_redirect_url($campaign_id, 0, 'vms-outreach-recipient-filters');
		$import_form_id = 'vms-outreach-recipient-import-' . $campaign_id;
		$export_form_id = 'vms-outreach-recipient-export-' . $campaign_id;
		$contact_audience_form_id = 'vms-outreach-contact-audience-' . $campaign_id;
		$available_tokens = vms_pass_outreach_available_token_count($campaign);
		$linked_batch = vms_pass_outreach_campaign_batch($campaign);
		$linked_batch_id = absint($linked_batch['id'] ?? 0);
		$linked_batch_passes_url = $linked_batch_id > 0 && function_exists('vms_pass_claims_admin_page_url')
			? vms_pass_claims_admin_page_url(array('tab' => 'passes', 'batch_id' => $linked_batch_id))
			: '';
		$batches_url = function_exists('vms_pass_claims_admin_page_url')
			? vms_pass_claims_admin_page_url(array('tab' => 'batches'))
			: admin_url('admin.php?page=vms-passes');
		$summary = vms_pass_outreach_campaign_summary($campaign);
		$default_send_batch_size = vms_pass_outreach_default_send_batch_size();
		$send_batch_cap = vms_pass_outreach_send_batch_cap();
		$all_checked_in_map = isset($summary['checked_in_map']) && is_array($summary['checked_in_map']) ? $summary['checked_in_map'] : array();
		$group_labels = vms_pass_outreach_campaign_group_labels($campaign_id);
		$recipients = vms_pass_outreach_query_recipients_for_campaign($campaign_id, array(
			'limit' => 0,
			'campaign' => $campaign,
			'checked_in_counts' => $all_checked_in_map,
			'search' => $search,
			'status' => $status_filter,
			'group_label' => $group_filter,
		));
		$contact_type_labels = function_exists('vms_outreach_contact_type_options') ? vms_outreach_contact_type_options() : array();
		$contact_status_labels = function_exists('vms_outreach_contact_status_options') ? vms_outreach_contact_status_options() : array();
		$contact_audience_status_options = vms_pass_outreach_contact_audience_status_filter_options();
		$clear_contact_audience_preview = !empty($_GET['clear_contact_audience_preview']);
		if ($clear_contact_audience_preview) {
			vms_pass_outreach_clear_contact_audience_preview(get_current_user_id(), $campaign_id);
		}
		$contact_audience_preview = vms_pass_outreach_get_contact_audience_preview(get_current_user_id(), $campaign_id);
		$contact_audience_has_preview = !empty($contact_audience_preview);
		$contact_audience_filters = vms_pass_outreach_normalize_contact_audience_filters((array) ($contact_audience_preview['filters'] ?? array()));
		$contact_audience_reset_url = function_exists('vms_pass_outreach_admin_page_url')
			? vms_pass_outreach_admin_page_url(array(
				'campaign_id' => $campaign_id,
				'clear_contact_audience_preview' => 1,
			)) . '#vms-outreach-contact-audience'
			: vms_pass_outreach_recipient_redirect_url($campaign_id, 0, 'vms-outreach-contact-audience');
		$section_context = function_exists('vms_pass_outreach_collapsible_context')
			? vms_pass_outreach_collapsible_context($campaign_id)
			: 'outreach-campaign:' . $campaign_id;
		$total_recipients = absint($summary['total_recipients'] ?? 0);
		$ready_recipients = absint($summary['ready_recipients'] ?? 0);
		$queued_recipients = absint($summary['queued_recipients'] ?? 0);
		$sent_recipients = absint($summary['sent_recipients'] ?? 0);
		$failed_recipients = absint($summary['failed_recipients'] ?? 0);
			$claimed_recipients = absint($summary['claimed_recipients'] ?? 0);
			$checked_in_recipients = absint($summary['admissions_checked_in'] ?? 0);
			$delivery_feedback = vms_pass_outreach_delivery_feedback_from_request($campaign_id);
			$queue_review_token = isset($_GET['queue_review']) ? sanitize_key((string) wp_unslash($_GET['queue_review'])) : '';
			$queue_review_selection = $queue_review_token !== '' ? vms_pass_outreach_get_pending_queue_selection($queue_review_token, $campaign_id) : array();
			$queue_review_recipients = !empty($queue_review_selection['recipient_ids'])
				? vms_pass_outreach_query_recipients_for_campaign($campaign_id, array(
					'limit' => 0,
					'ids' => (array) $queue_review_selection['recipient_ids'],
				))
				: array();
			$queue_review_analysis = !empty($queue_review_recipients)
				? vms_pass_outreach_analyze_recipients_for_delivery_actions($queue_review_recipients, $campaign)
				: array();
			$queue_review_counts = (array) ($queue_review_analysis['counts'] ?? array());
			$send_batch_input_value = max(1, min($default_send_batch_size, $send_batch_cap));
			$send_button_label = vms_pass_outreach_send_batch_button_label($send_batch_input_value, $queued_recipients);
			$delivery_counts_label = vms_pass_outreach_campaign_counts_line($summary);
			$usage_summary = function_exists('vms_pass_outreach_usage_summary')
				? vms_pass_outreach_usage_summary($campaign)
				: array(
					'entries_count' => 0,
					'headcount' => 0,
				);
			$total_admissions = vms_pass_outreach_campaign_total_admission_capacity($campaign, $summary);
			$results_counts_label = vms_pass_outreach_campaign_results_line($campaign, $summary, array(
				'include_total_admissions' => false,
			));
			$next_action_message = '';
			if ($failed_recipients > 0) {
				$next_action_message = sprintf(_n('%d invite needs attention.', '%d invites need attention.', $failed_recipients, 'backstage-outreach'), $failed_recipients);
				if ($queued_recipients > 0) {
					$next_action_message .= ' ' . sprintf(_n('%d invite is queued and waiting to send.', '%d invites are queued and waiting to send.', $queued_recipients, 'backstage-outreach'), $queued_recipients);
				}
			} elseif ($queued_recipients > 0) {
				$next_action_message = sprintf(_n('%d invite is queued and waiting to send.', '%d invites are queued and waiting to send.', $queued_recipients, 'backstage-outreach'), $queued_recipients);
			} else {
				$next_action_message = __('No invites are currently queued.', 'backstage-outreach');
			}
			$recipient_id = isset($_GET['recipient_id']) ? absint((string) $_GET['recipient_id']) : 0;
			$import_summary_meta = '<span class="vms-pass-filter-pill">' . esc_html(sprintf(__('Tokens: %d open', 'backstage-outreach'), $available_tokens)) . '</span>';
			$contact_audience_status_label = sanitize_text_field((string) ($contact_audience_status_options[(string) ($contact_audience_filters['status_scope'] ?? 'approved')] ?? __('Approved Only', 'backstage-outreach')));
			$contact_audience_summary_meta = '<span class="vms-pass-filter-pill is-active">' . esc_html(sprintf(__('Status: %s', 'backstage-outreach'), $contact_audience_status_label)) . '</span>';

			echo '<p class="description">' . esc_html($available_tokens > 0
				? sprintf(__('Recipients for this campaign each reserve one Guest Pass token from the linked batch. %d unassigned tokens remain.', 'backstage-outreach'), $available_tokens)
				: __('Recipients for this campaign each reserve one Guest Pass token from the linked batch. No unassigned tokens remain. Add more tokens before adding another claimable recipient.', 'backstage-outreach')) . '</p>';
			ob_start();
			echo '<div class="vms-pass-preview-summary vms-outreach-add-recipients-panel">';
			echo '<h3>' . esc_html__('Add Recipients', 'backstage-outreach') . '</h3>';
			echo '<p class="description">' . esc_html__('Use these tools to create or expand the audience for this campaign.', 'backstage-outreach') . '</p>';
			echo '<details ' . vms_pass_outreach_collapsible_details_attrs($section_context, 'import_from_csv', array(
				'classes' => array('vms-pass-callout', 'vms-pass-callout-info', 'vms-pass-callout--collapsible'),
				'default_open' => $total_recipients <= 0,
			'anchor' => 'vms-outreach-audience',
		)) . '>';
		echo vms_pass_outreach_render_collapsible_summary(__('Import from CSV', 'backstage-outreach'), $import_summary_meta);
		echo '<p>' . esc_html__('Upload a CSV to reserve one unique invite link per recipient from the linked Guest Pass Batch / Invite Link Pool.', 'backstage-outreach') . '</p>';
			echo '<form id="' . esc_attr($import_form_id) . '" method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-form">';
			echo '<input type="hidden" name="action" value="vms_pass_outreach_recipient_import">';
			echo '<input type="hidden" name="campaign_id" value="' . esc_attr((string) $campaign_id) . '">';
			vms_outreach_nonce_field('vms_pass_outreach_recipient_import', $import_form_id . '-nonce');
			echo '<div class="vms-pass-grid">';
			echo '<label class="vms-pass-span-2 vms-pass-upload-field">' . (function_exists('vms_outreach_admin_render_label')
				? vms_outreach_admin_render_label(__('CSV File', 'backstage-outreach'), array('required' => true))
				: '<span class="vms-pass-field-label-row"><span class="vms-pass-field-label">' . esc_html__('CSV File', 'backstage-outreach') . ' <span class="vms-pass-required-marker" aria-hidden="true">*</span><span class="screen-reader-text">' . esc_html__('Required', 'backstage-outreach') . '</span></span></span>') . '<div class="vms-pass-file-input-row"><input type="file" name="recipient_csv" accept=".csv,text/csv" required></div><span class="description">' . esc_html(sprintf(__('Email is required. First, last, and full name are optional. CSV imports are limited to %d rows per upload.', 'backstage-outreach'), vms_pass_outreach_import_row_limit())) . '</span></label>';
			echo '</div>';
			echo '</form>';
		echo '<form id="' . esc_attr($export_form_id) . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-form">';
		echo '<input type="hidden" name="action" value="vms_pass_outreach_recipient_export">';
		echo '<input type="hidden" name="campaign_id" value="' . esc_attr((string) $campaign_id) . '">';
		vms_outreach_nonce_field('vms_pass_outreach_recipient_export', $export_form_id . '-nonce');
		echo '</form>';
		echo '<div class="vms-pass-callout-actions">';
		echo '<button type="submit" form="' . esc_attr($import_form_id) . '" class="button button-primary">' . esc_html__('Import Recipients', 'backstage-outreach') . '</button>';
		echo '<button type="submit" form="' . esc_attr($export_form_id) . '" class="button" name="export_format" value="full">' . esc_html__('Export All Recipients', 'backstage-outreach') . '</button>';
		echo '<button type="submit" form="' . esc_attr($export_form_id) . '" class="button" name="export_format" value="send_prep">' . esc_html__('Export Send-Prep CSV', 'backstage-outreach') . '</button>';
		echo '</div>';
		echo '</details>';

		echo '<details ' . vms_pass_outreach_collapsible_details_attrs($section_context, 'add_from_contacts', array(
			'classes' => array('vms-pass-callout', 'vms-pass-callout-info', 'vms-pass-callout--collapsible'),
			'default_open' => $contact_audience_has_preview,
			'anchor' => 'vms-outreach-contact-audience',
		)) . '>';
		echo vms_pass_outreach_render_collapsible_summary(__('Add from Contacts', 'backstage-outreach'), $contact_audience_summary_meta);
		echo '<p>' . esc_html__('Filter reusable Outreach contacts, preview who can be added, then reserve invite links only for eligible contacts.', 'backstage-outreach') . '</p>';
		echo '<form id="' . esc_attr($contact_audience_form_id) . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-form">';
		echo '<input type="hidden" name="action" value="vms_pass_outreach_contact_audience">';
		echo '<input type="hidden" name="campaign_id" value="' . esc_attr((string) $campaign_id) . '">';
		vms_outreach_nonce_field('vms_pass_outreach_contact_audience', $contact_audience_form_id . '-nonce');
		echo '<div class="vms-pass-grid">';
		echo '<label class="vms-pass-span-2">' . esc_html__('Search', 'backstage-outreach') . '<input type="search" name="search" value="' . esc_attr((string) ($contact_audience_filters['search'] ?? '')) . '" placeholder="' . esc_attr__('name, email, business, city, source', 'backstage-outreach') . '"></label>';
		echo '<label>' . esc_html__('Type', 'backstage-outreach') . '<select name="contact_type"><option value="">' . esc_html__('All types', 'backstage-outreach') . '</option>';
		foreach ($contact_type_labels as $type_key => $type_label) {
			echo '<option value="' . esc_attr((string) $type_key) . '"' . selected((string) ($contact_audience_filters['contact_type'] ?? ''), (string) $type_key, false) . '>' . esc_html((string) $type_label) . '</option>';
		}
		echo '</select></label>';
		echo '<label>' . esc_html__('Status', 'backstage-outreach') . '<select name="status_scope">';
		foreach ($contact_audience_status_options as $status_key => $status_label_option) {
			echo '<option value="' . esc_attr((string) $status_key) . '"' . selected((string) ($contact_audience_filters['status_scope'] ?? 'approved'), (string) $status_key, false) . '>' . esc_html((string) $status_label_option) . '</option>';
		}
		echo '</select></label>';
		echo '<label>' . esc_html__('City', 'backstage-outreach') . '<input type="text" name="city" value="' . esc_attr((string) ($contact_audience_filters['city'] ?? '')) . '" placeholder="' . esc_attr__('Austin', 'backstage-outreach') . '"></label>';
		echo '<label>' . esc_html__('Source', 'backstage-outreach') . '<input type="text" name="source" value="' . esc_attr((string) ($contact_audience_filters['source'] ?? '')) . '" placeholder="' . esc_attr__('broker list', 'backstage-outreach') . '"></label>';
		echo '<label class="vms-pass-span-2">' . esc_html__('Tag', 'backstage-outreach') . '<input type="text" name="tag" value="' . esc_attr((string) ($contact_audience_filters['tag'] ?? '')) . '" placeholder="' . esc_attr__('luxury, preferred, north shore', 'backstage-outreach') . '"></label>';
		echo '</div>';
		echo '<p class="vms-pass-actions">';
		echo '<button type="submit" class="' . esc_attr($contact_audience_has_preview ? 'button' : 'button button-primary') . '" name="contact_audience_mode" value="preview">' . esc_html($contact_audience_has_preview ? __('Refresh Preview', 'backstage-outreach') : __('Preview Contacts', 'backstage-outreach')) . '</button> ';
		if ($contact_audience_has_preview && absint($contact_audience_preview['eligible_count'] ?? 0) > 0) {
			echo '<button type="submit" class="button button-primary" name="contact_audience_mode" value="commit">' . esc_html__('Add Selected Contacts', 'backstage-outreach') . '</button> ';
		}
		echo '<a class="button" href="' . esc_url($contact_audience_reset_url) . '">' . esc_html__('Reset', 'backstage-outreach') . '</a>';
		echo '</p>';
		echo '</form>';
		echo '<p class="vms-pass-inline-pills"><span class="vms-pass-filter-pill is-active">' . esc_html(sprintf(__('Status filter: %s', 'backstage-outreach'), $contact_audience_status_label)) . '</span></p>';

		if ($contact_audience_has_preview) {
			$contact_preview_rows = array_values((array) ($contact_audience_preview['preview_rows'] ?? array()));
			$visible_eligible_count = count(array_filter($contact_preview_rows, static function (array $row): bool {
				return !empty($row['selectable']);
			}));
			echo '<div class="vms-pass-preview-summary"><h3>' . esc_html__('Contact Audience Preview', 'backstage-outreach') . '</h3>';
			echo '<table class="widefat striped"><tbody>';
			$audience_summary_rows = array(
				__('Contacts Matched', 'backstage-outreach') => (string) absint($contact_audience_preview['total_contacts'] ?? 0),
				__('Eligible to Add', 'backstage-outreach') => (string) absint($contact_audience_preview['eligible_count'] ?? 0),
				__('Already in This Campaign', 'backstage-outreach') => (string) absint($contact_audience_preview['already_in_campaign_count'] ?? 0),
				__('Missing / Invalid Email', 'backstage-outreach') => (string) absint($contact_audience_preview['missing_email_count'] ?? 0),
				__('Globally Suppressed', 'backstage-outreach') => (string) absint($contact_audience_preview['globally_suppressed_count'] ?? 0),
				__('Excluded / Do Not Contact', 'backstage-outreach') => (string) absint($contact_audience_preview['excluded_count'] ?? 0),
				__('Duplicate Emails', 'backstage-outreach') => (string) absint($contact_audience_preview['duplicate_email_count'] ?? 0),
				__('Contacts Skipped', 'backstage-outreach') => (string) absint($contact_audience_preview['skipped_count'] ?? 0),
			);
			foreach ($audience_summary_rows as $label => $value) {
				echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
			}
			echo '</tbody></table>';
				echo '<p class="description">' . esc_html__('Select the contacts you want to add to this campaign.', 'backstage-outreach') . '</p>';
				echo '<div class="vms-pass-table-scroll vms-pass-table-scroll--contact-audience">';
				echo '<table class="widefat striped vms-pass-contact-audience-table"><thead><tr><td class="check-column"><input type="checkbox" data-vms-contact-audience-select-all="1" aria-label="' . esc_attr__('Select all eligible contacts', 'backstage-outreach') . '"' . disabled($visible_eligible_count <= 0, true, false) . '></td><th>' . esc_html__('Contact', 'backstage-outreach') . '</th><th>' . esc_html__('Email', 'backstage-outreach') . '</th><th>' . esc_html__('Type / Status', 'backstage-outreach') . '</th><th>' . esc_html__('Location / Source', 'backstage-outreach') . '</th><th class="vms-pass-table-cell--center">' . esc_html__('Result', 'backstage-outreach') . '</th></tr></thead><tbody>';
				if (empty($contact_preview_rows)) {
					echo '<tr><td colspan="6">' . esc_html__('No contacts matched. Try All Statuses, clear filters, or approve contacts first.', 'backstage-outreach') . '</td></tr>';
				} else {
				foreach ($contact_preview_rows as $row) {
					$contact_type = sanitize_key((string) ($row['contact_type'] ?? 'other'));
					$contact_status = sanitize_key((string) ($row['status'] ?? 'new'));
					$type_label = (string) ($contact_type_labels[$contact_type] ?? $contact_type);
					$status_label = (string) ($contact_status_labels[$contact_status] ?? $contact_status);
					$location_bits = array_filter(array(
						trim((string) ($row['city'] ?? '')),
						trim((string) ($row['state'] ?? '')),
					));
					$location_label = implode(', ', $location_bits);
					$source_label = sanitize_text_field((string) ($row['source'] ?? ''));
					echo '<tr>';
					echo '<td class="check-column">';
					if (!empty($row['selectable'])) {
						echo '<input type="checkbox" name="selected_contact_ids[]" value="' . esc_attr((string) absint($row['contact_id'] ?? 0)) . '" form="' . esc_attr($contact_audience_form_id) . '" data-vms-contact-audience-select="1" aria-label="' . esc_attr(sprintf(__('Select %s', 'backstage-outreach'), (string) ($row['contact_name'] ?? __('contact', 'backstage-outreach')))) . '">';
					} else {
						echo '<span aria-hidden="true">—</span>';
					}
					echo '</td>';
					echo '<td><strong>' . esc_html((string) ($row['contact_name'] ?? '')) . '</strong><div class="description">' . esc_html((string) ($row['business_name'] ?? '')) . '</div></td>';
					echo '<td>' . esc_html((string) ($row['email'] ?? '')) . '</td>';
					echo '<td>' . esc_html($type_label) . '<div class="description">' . esc_html($status_label) . '</div></td>';
					echo '<td>' . esc_html($location_label !== '' ? $location_label : '—') . '<div class="description">' . esc_html($source_label !== '' ? $source_label : '—') . '</div></td>';
						echo '<td class="vms-pass-table-cell--center">' . esc_html((string) ($row['action'] === 'add' ? __('Eligible', 'backstage-outreach') : (string) ($row['reason_label'] ?? __('Skip', 'backstage-outreach')))) . '</td>';
					echo '</tr>';
					}
				}
				echo '</tbody></table>';
				echo '</div>';
			echo '</div>';
		}
			echo '</details>';

		$recipient_id = isset($_GET['recipient_id']) ? absint((string) $_GET['recipient_id']) : 0;
		$recipient = $recipient_id > 0 ? vms_pass_outreach_get_recipient_by_id($recipient_id) : null;
		if (is_array($recipient) && absint($recipient['campaign_id'] ?? 0) !== $campaign_id) {
			$recipient = null;
			$recipient_id = 0;
		}
		$form_payload = is_array($recipient) ? $recipient : vms_pass_outreach_default_recipient_payload();
		$form_payload['delivery_method'] = vms_pass_outreach_recipient_delivery_method($form_payload);
		$field_errors = array();
		$recipient_flash = vms_pass_outreach_pull_recipient_form_flash(get_current_user_id(), $campaign_id);
		if (
			absint($recipient_flash['campaign_id'] ?? 0) === $campaign_id
			&& absint($recipient_flash['recipient_id'] ?? 0) === $recipient_id
		) {
			if (!empty($recipient_flash['payload']) && is_array($recipient_flash['payload'])) {
				$form_payload = array_merge($form_payload, (array) $recipient_flash['payload']);
			}
			if (!empty($recipient_flash['field_errors']) && is_array($recipient_flash['field_errors'])) {
				$field_errors = array_map('sanitize_text_field', (array) $recipient_flash['field_errors']);
			}
		}
		if (!is_array($recipient) && empty($recipient_flash)) {
			$form_payload['delivery_method'] = 'email';
		}
		$selected_delivery_method = vms_pass_outreach_recipient_delivery_method($form_payload);
		$linked_contact = is_array($recipient) ? vms_pass_outreach_recipient_linked_contact($recipient) : null;
		$linked_contact_guardrail = is_array($recipient) ? vms_pass_outreach_recipient_contact_guardrail_state($recipient) : array(
			'blocked' => false,
			'reason_label' => '',
		);
		$admin_label = static function (string $label, string $badge_text = '', string $badge_variant = 'required'): string {
			if (function_exists('vms_pass_claims_admin_field_label')) {
				return vms_pass_claims_admin_field_label($label, $badge_text, $badge_variant);
			}
			return esc_html($label);
		};
		$required_admin_label = static function (string $label): string {
			if (function_exists('vms_outreach_admin_render_label')) {
				return vms_outreach_admin_render_label($label, array('required' => true));
			}
			if (function_exists('vms_pass_claims_admin_help_label')) {
				return vms_pass_claims_admin_help_label($label, array('required' => true));
			}
			return '<span class="vms-pass-field-label-row"><span class="vms-pass-field-label">' . esc_html($label) . ' <span class="vms-pass-required-marker" aria-hidden="true">*</span><span class="screen-reader-text">' . esc_html__('Required', 'backstage-outreach') . '</span></span></span>';
		};
		$admin_description = static function (string $description): string {
			if (function_exists('vms_pass_claims_admin_field_description')) {
				return vms_pass_claims_admin_field_description($description);
			}
			return $description !== '' ? '<span class="description">' . esc_html($description) . '</span>' : '';
		};
		$render_field_messages = static function (array $keys, string $description = '') use ($field_errors, $admin_description): string {
			if (function_exists('vms_pass_outreach_render_field_messages')) {
				return vms_pass_outreach_render_field_messages($field_errors, $keys, $description);
			}
			return $admin_description($description);
		};
		$table_heading_attr = static function (string $description = ''): string {
			return $description !== '' ? ' title="' . esc_attr($description) . '"' : '';
		};
		$send_status_tooltip = static function (array $recipient_row, string $send_status, string $send_method_label, string $last_send_error, array $guardrail): string {
			$details = array();
			if ($send_status === 'queued') {
				$details[] = __('Waiting in the email queue for a manual batch send.', 'backstage-outreach');
			}
			if (!empty($recipient_row['sent_at'])) {
				$details[] = sprintf(__('Sent: %s', 'backstage-outreach'), vms_pass_outreach_format_admin_datetime((string) $recipient_row['sent_at']));
			}
			if (!empty($recipient_row['last_contacted_at']) && (string) ($recipient_row['last_contacted_at'] ?? '') !== (string) ($recipient_row['sent_at'] ?? '')) {
				$details[] = sprintf(__('Last Contact: %s', 'backstage-outreach'), vms_pass_outreach_format_admin_datetime((string) $recipient_row['last_contacted_at']));
			}
			if ($send_method_label !== '') {
				$details[] = sprintf(__('Method: %s', 'backstage-outreach'), $send_method_label);
			}
			if (!empty($guardrail['blocked'])) {
				$details[] = sprintf(__('Blocked: %s', 'backstage-outreach'), (string) ($guardrail['reason_label'] ?? __('Blocked', 'backstage-outreach')));
			}
			if ($last_send_error !== '' && in_array($send_status, array('failed', 'suppressed', 'do_not_contact'), true)) {
				$details[] = sprintf(__('Detail: %s', 'backstage-outreach'), $last_send_error);
			}
			return implode("\n", $details);
		};
		$delivery_method_options = vms_pass_outreach_recipient_delivery_method_options();
		$is_new_recipient_form = $recipient_id <= 0;
		$recipient_add_disabled = $is_new_recipient_form && $available_tokens <= 0;
		$reserved_token_id = absint($form_payload['pass_token_id'] ?? 0);
		if ($reserved_token_id > 0) {
			$reserved_token_value = '#' . $reserved_token_id;
			$reserved_token_help = sprintf(
				__('Reserved token #%1$d. %2$d unassigned tokens remain in the linked batch.', 'backstage-outreach'),
				$reserved_token_id,
				$available_tokens
			);
		} elseif ($available_tokens > 0) {
			$reserved_token_value = __('Will reserve on save', 'backstage-outreach');
			$reserved_token_help = sprintf(
				__('This recipient will reserve one Guest Pass token when saved. %d unassigned tokens remain in the linked batch.', 'backstage-outreach'),
				$available_tokens
			);
		} else {
			$reserved_token_value = __('No tokens available', 'backstage-outreach');
			$reserved_token_help = __('No guest pass invite tokens remain in the linked batch. Add or expand the linked batch before adding another claimable recipient.', 'backstage-outreach');
		}
		$recipient_tokens_warning = __('No guest pass invite tokens remain in the linked batch. Add more tokens before adding another claimable recipient.', 'backstage-outreach');
		$recipient_tokens_help = __('This Outreach screen cannot create more tokens. Open Guest Pass Batches to expand or replace the linked batch, then return here to add another recipient.', 'backstage-outreach');

		echo '<details ' . vms_pass_outreach_collapsible_details_attrs($section_context, 'individual_recipient', array(
			'classes' => array('vms-pass-preview-summary', 'vms-pass-preview-summary--collapsible'),
			'default_open' => $recipient_id > 0 || $total_recipients <= 0 || !empty($field_errors) || !empty($recipient_flash),
			'anchor' => 'vms-outreach-individual-recipient',
		)) . '>';
		echo vms_pass_outreach_render_collapsible_summary($recipient_id > 0 ? __('Edit Recipient', 'backstage-outreach') : __('Add Individual Recipient', 'backstage-outreach'));
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-form">';
		echo '<input type="hidden" name="action" value="vms_pass_outreach_recipient_save">';
		echo '<input type="hidden" name="campaign_id" value="' . esc_attr((string) $campaign_id) . '">';
			echo '<input type="hidden" name="recipient_id" value="' . esc_attr((string) ($form_payload['id'] ?? 0)) . '">';
			echo '<input type="hidden" name="contact_id" value="' . esc_attr((string) absint($form_payload['contact_id'] ?? 0)) . '">';
			vms_outreach_nonce_field(
				'vms_pass_outreach_recipient_save',
				'vms-outreach-recipient-save-' . $campaign_id . '-' . $recipient_id . '-nonce'
			);
			if ($available_tokens <= 0) {
				echo '<div class="vms-pass-callout vms-pass-callout-warning">';
				echo '<h3>' . esc_html__('No Invite Tokens Available', 'backstage-outreach') . '</h3>';
				echo '<p>' . esc_html($recipient_tokens_warning) . '</p>';
				echo '<p class="description">' . esc_html($recipient_tokens_help) . '</p>';
				echo '<p class="vms-pass-actions">';
				if ($linked_batch_passes_url !== '') {
					echo '<a class="button" href="' . esc_url($linked_batch_passes_url) . '">' . esc_html__('Open Linked Batch Tokens', 'backstage-outreach') . '</a> ';
				}
				echo '<a class="button" href="' . esc_url($batches_url) . '">' . esc_html__('Guest Pass Batches', 'backstage-outreach') . '</a>';
				echo '</p>';
				echo '</div>';
			}
			echo '<div class="vms-pass-grid">';
			if (is_array($linked_contact)) {
				$linked_contact_url = function_exists('vms_outreach_contacts_admin_url')
					? vms_outreach_contacts_admin_url(array('view' => 'edit', 'contact_id' => (int) ($linked_contact['id'] ?? 0)))
					: '';
				$linked_contact_label = function_exists('vms_outreach_contact_display_name')
					? vms_outreach_contact_display_name($linked_contact)
					: sanitize_text_field((string) ($linked_contact['contact_name'] ?? ''));
				$linked_contact_meta = !empty($linked_contact_guardrail['blocked'])
					? sprintf(__('Linked contact is currently blocked: %s.', 'backstage-outreach'), (string) ($linked_contact_guardrail['reason_label'] ?? __('Blocked', 'backstage-outreach')))
					: __('This recipient stays linked to the shared Outreach contact record.', 'backstage-outreach');
				echo '<label class="vms-pass-span-2">' . $admin_label(__('Linked Outreach Contact', 'backstage-outreach')) . '<input type="text" readonly value="' . esc_attr($linked_contact_label) . '">' . $admin_description($linked_contact_meta);
				if ($linked_contact_url !== '') {
					echo '<span class="description"><a href="' . esc_url($linked_contact_url) . '">' . esc_html__('Open linked contact', 'backstage-outreach') . '</a></span>';
				}
				echo '</label>';
			}
			echo '<label' . vms_pass_outreach_field_wrapper_class($field_errors, array('first_name', 'last_name')) . '>' . $admin_label(__('First Name', 'backstage-outreach')) . '<input type="text" name="first_name" value="' . esc_attr((string) ($form_payload['first_name'] ?? '')) . '">' . $render_field_messages(array('first_name'), __('Shown in exports and invite message merge fields.', 'backstage-outreach')) . '</label>';
			echo '<label' . vms_pass_outreach_field_wrapper_class($field_errors, array('last_name')) . '>' . $admin_label(__('Last Name', 'backstage-outreach')) . '<input type="text" name="last_name" value="' . esc_attr((string) ($form_payload['last_name'] ?? '')) . '">' . $render_field_messages(array('last_name'), __('Used with the first name for matching, lookup, and reporting.', 'backstage-outreach')) . '</label>';
			echo '<label' . vms_pass_outreach_field_wrapper_class($field_errors, array('delivery_method')) . '>' . $required_admin_label(__('Delivery Method', 'backstage-outreach')) . '<select name="delivery_method">';
			foreach ($delivery_method_options as $method_key => $method_label) {
				echo '<option value="' . esc_attr((string) $method_key) . '"' . selected($selected_delivery_method, (string) $method_key, false) . '>' . esc_html((string) $method_label) . '</option>';
			}
			echo '</select>' . $render_field_messages(array('delivery_method'), __('Email requires a valid email address. Manual / Social and Text / Phone can leave email blank when the recipient still has enough identifying context.', 'backstage-outreach')) . '</label>';
			echo '<label' . vms_pass_outreach_field_wrapper_class($field_errors, array('email')) . '>' . $admin_label(__('Email', 'backstage-outreach')) . '<input type="email" name="email" value="' . esc_attr((string) ($form_payload['email'] ?? '')) . '">' . $render_field_messages(array('email'), __('Required when Delivery Method is Email. Email-queue sending is disabled unless this field is valid.', 'backstage-outreach')) . '</label>';
			echo '<label' . vms_pass_outreach_field_wrapper_class($field_errors, array('phone')) . '>' . $admin_label(__('Phone', 'backstage-outreach')) . '<input type="text" name="phone" value="' . esc_attr((string) ($form_payload['phone'] ?? '')) . '">' . $render_field_messages(array('phone'), __('Required when Delivery Method is Text / Phone. Also used for matching and guest lookup at the gate.', 'backstage-outreach')) . '</label>';
			echo '<label' . vms_pass_outreach_field_wrapper_class($field_errors, array('company')) . '>' . $admin_label(__('Company', 'backstage-outreach')) . '<input type="text" name="company" value="' . esc_attr((string) ($form_payload['company'] ?? '')) . '">' . $render_field_messages(array('company'), __('Optional organization, sponsor, or partner name for reporting.', 'backstage-outreach')) . '</label>';
			echo '<label' . vms_pass_outreach_field_wrapper_class($field_errors, array('group_label')) . '>' . $admin_label(__('Group', 'backstage-outreach')) . '<input type="text" name="group_label" value="' . esc_attr((string) ($form_payload['group_label'] ?? '')) . '">' . $render_field_messages(array('group_label'), __('Optional tag for filtering, exports, or grouped outreach.', 'backstage-outreach')) . '</label>';
			echo '<label' . vms_pass_outreach_field_wrapper_class($field_errors, array('expires_at')) . '>' . $admin_label(__('Recipient Expiration Override', 'backstage-outreach')) . '<input type="datetime-local" name="expires_at" value="' . esc_attr(function_exists('vms_pass_outreach_format_datetime_input_value') ? vms_pass_outreach_format_datetime_input_value((string) ($form_payload['expires_at'] ?? '')) : (string) ($form_payload['expires_at'] ?? '')) . '">' . $render_field_messages(array('expires_at'), __('Optional per-recipient deadline. Leave blank to use the campaign or batch expiration.', 'backstage-outreach')) . '</label>';
			echo '<label' . vms_pass_outreach_field_wrapper_class($field_errors, array('pass_token_id')) . '>' . $admin_label(__('Reserved Guest Pass Token', 'backstage-outreach')) . '<input type="text" value="' . esc_attr($reserved_token_value) . '" readonly>' . $render_field_messages(array('pass_token_id'), $reserved_token_help) . '</label>';
			echo '<label class="vms-pass-span-2' . (!empty($field_errors['notes']) ? ' vms-pass-field-has-error' : '') . '">' . $admin_label(__('Notes', 'backstage-outreach')) . '<textarea name="notes" rows="3">' . esc_textarea((string) ($form_payload['notes'] ?? '')) . '</textarea>' . $render_field_messages(array('notes'), __('Internal notes about this recipient, relationship, or manual delivery plan. Use this field for social handles or delivery context when email is blank.', 'backstage-outreach')) . '</label>';
			echo '</div>';
			echo '<p class="vms-pass-actions">';
			echo '<button type="submit" class="button button-primary"' . disabled($recipient_add_disabled, true, false) . '>' . esc_html($recipient_id > 0 ? __('Update Recipient', 'backstage-outreach') : __('Add Recipient', 'backstage-outreach')) . '</button> ';
			if ($recipient_add_disabled) {
				echo '<span class="description">' . esc_html($recipient_tokens_warning) . '</span> ';
			}
		if ($recipient_id > 0) {
			echo '<a class="button" href="' . esc_url(vms_pass_outreach_recipient_redirect_url($campaign_id)) . '">' . esc_html__('New Recipient', 'backstage-outreach') . '</a>';
		}
		echo '</p>';
		echo '</form>';
		echo '</details>';

			if (is_array($recipient)) {
				$link_id = 'vms-outreach-link-edit-' . $recipient_id;
				$message_id = 'vms-outreach-message-edit-' . $recipient_id;
				echo '<div class="vms-pass-preview-summary"><h3>' . esc_html__('Invite Tools', 'backstage-outreach') . '</h3>';
				echo '<p class="description">' . esc_html__('Copy the unique invite link or the prebuilt message for this recipient. Edit the message before sending if needed.', 'backstage-outreach') . '</p>';
				echo '<label>' . $admin_label(__('Invite Link', 'backstage-outreach')) . '<input type="text" id="' . esc_attr($link_id) . '" readonly value="' . esc_attr(vms_pass_outreach_build_invite_url($recipient)) . '">' . $admin_description(__('Unique claim URL reserved for this recipient.', 'backstage-outreach')) . '</label>';
				echo '<label>' . $admin_label(__('Invite Message', 'backstage-outreach')) . '<textarea id="' . esc_attr($message_id) . '" rows="6" readonly>' . esc_textarea(vms_pass_outreach_build_invite_message($recipient, $campaign)) . '</textarea>' . $admin_description(__('Prebuilt message with the invite link. Edit it before sending if needed.', 'backstage-outreach')) . '</label>';
				echo '<p class="vms-pass-actions"><button type="button" class="button" data-vms-copy="#' . esc_attr($link_id) . '">' . esc_html__('Copy Link', 'backstage-outreach') . '</button> <button type="button" class="button" data-vms-copy="#' . esc_attr($message_id) . '">' . esc_html__('Copy Invite Message', 'backstage-outreach') . '</button></p>';
				echo '</div>';
			}
			echo '</div>';
			$add_recipients_panel_html = (string) ob_get_clean();
			if ($total_recipients <= 0) {
				echo $add_recipients_panel_html;
			}

			$campaign_status_key = sanitize_key((string) ($campaign['status'] ?? 'draft'));
			$campaign_is_draft = $campaign_status_key === 'draft';
			$campaign_display_status = function_exists('vms_pass_outreach_campaign_display_status')
				? vms_pass_outreach_campaign_display_status($campaign, $summary)
				: array(
					'key' => $campaign_status_key,
					'label' => (string) (vms_pass_outreach_status_labels()[$campaign_status_key] ?? $campaign_status_key),
				);
			$campaign_claim_guardrail = function_exists('vms_pass_outreach_campaign_claim_guardrail')
				? vms_pass_outreach_campaign_claim_guardrail($campaign)
				: array('ok' => true);
			$campaign_activation_preview = $campaign;
			if ($campaign_is_draft) {
				$campaign_activation_preview['status'] = 'active';
			}
			$campaign_activation_guardrail = $campaign_is_draft && function_exists('vms_pass_outreach_campaign_claim_guardrail')
				? vms_pass_outreach_campaign_claim_guardrail($campaign_activation_preview)
				: $campaign_claim_guardrail;
			$campaign_can_send_invites = !empty($campaign_claim_guardrail['ok']);
			$campaign_can_activate_delivery = $campaign_is_draft && !empty($campaign_activation_guardrail['ok']);
			$campaign_claim_guardrail_message = $campaign_can_send_invites
				? ''
				: vms_pass_outreach_claim_guardrail_message($campaign_claim_guardrail, __('This outreach campaign is not ready to send claimable invite links.', 'backstage-outreach'));
			if ($campaign_is_draft && in_array(sanitize_key((string) ($campaign_display_status['key'] ?? 'draft')), array('ready', 'sending', 'partially_sent'), true)) {
				$campaign_claim_guardrail_message .= ' ' . sprintf(
					__('Current delivery state: %s.', 'backstage-outreach'),
					(string) ($campaign_display_status['label'] ?? __('Draft', 'backstage-outreach'))
				);
			}
			$activation_prompt_message = function_exists('vms_pass_outreach_activation_prompt_message')
				? vms_pass_outreach_activation_prompt_message()
				: __('This campaign is in Draft. Invite links will not be claimable until the campaign is Active. Activate this campaign now?', 'backstage-outreach');
			$activate_campaign_url = vms_pass_outreach_activate_campaign_url($campaign_id, 0, 'vms-outreach-delivery-status');
			$bulk_action_options = array(
				'' => __('Bulk actions', 'backstage-outreach'),
				'queue_selected' => $campaign_is_draft ? __('Submit Selected to Email Queue (Activate First)', 'backstage-outreach') : __('Submit Selected to Email Queue', 'backstage-outreach'),
				'retry_failed_selected' => __('Retry Failed Invites', 'backstage-outreach'),
				'resend_selected' => __('Resend Selected Invites', 'backstage-outreach'),
				'mark_sent' => __('Mark Sent', 'backstage-outreach'),
				'mark_not_sent' => __('Mark Not Sent', 'backstage-outreach'),
				'revoke' => __('Revoke', 'backstage-outreach'),
				'delete' => __('Delete', 'backstage-outreach'),
				'export_selected' => __('Export Selected', 'backstage-outreach'),
			);

			echo '<div id="vms-outreach-delivery-status" class="vms-outreach-delivery-status-card">';
			echo '<div class="vms-outreach-delivery-status-card__header">';
			echo '<h3>' . esc_html__('Delivery & Results', 'backstage-outreach') . '</h3>';
			echo '</div>';
			echo '<div class="vms-outreach-delivery-status-card__counts">';
			echo '<p><strong>' . esc_html($delivery_counts_label) . '</strong></p>';
			echo '<p><strong>' . esc_html($results_counts_label) . '</strong></p>';
			echo '</div>';
			if (!empty($delivery_feedback['message'])) {
				echo '<div class="vms-outreach-delivery-status-card__feedback is-' . esc_attr(sanitize_html_class((string) ($delivery_feedback['type'] ?? 'info'))) . '"><p>' . esc_html((string) $delivery_feedback['message']) . '</p>';
				if (!empty($delivery_feedback['summary_rows']) && is_array($delivery_feedback['summary_rows'])) {
					echo '<ul class="vms-outreach-delivery-status-card__summary">';
					foreach ((array) $delivery_feedback['summary_rows'] as $summary_row) {
						$summary_label = sanitize_text_field((string) ($summary_row['label'] ?? ''));
						$summary_value = absint($summary_row['value'] ?? 0);
						if ($summary_label === '') {
							continue;
						}
						echo '<li><strong>' . esc_html($summary_label) . ':</strong> ' . esc_html((string) $summary_value) . '</li>';
					}
					echo '</ul>';
				}
				echo '</div>';
			}
			if (!$campaign_can_send_invites) {
				echo '<div class="vms-outreach-delivery-status-card__feedback is-error"><p>' . esc_html($campaign_claim_guardrail_message) . '</p></div>';
				if ($campaign_is_draft) {
					echo '<p class="vms-pass-actions"><a class="button button-primary" href="' . esc_url($activate_campaign_url) . '">' . esc_html__('Activate Campaign', 'backstage-outreach') . '</a></p>';
				}
			}
			echo '<p class="vms-outreach-delivery-status-card__next-step">' . esc_html($next_action_message) . '</p>';
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-form vms-pass-form--full vms-outreach-delivery-status-card__send-form">';
			echo '<input type="hidden" name="action" value="vms_pass_outreach_send_queued_recipients">';
			echo '<input type="hidden" name="campaign_id" value="' . esc_attr((string) $campaign_id) . '">';
			if ($campaign_is_draft) {
				echo '<input type="hidden" name="activate_campaign" value="1">';
			}
			vms_outreach_nonce_field(
				'vms_pass_outreach_send_queued_recipients',
				'vms-outreach-send-queued-' . $campaign_id . '-nonce'
			);
			echo '<p class="vms-pass-actions vms-outreach-delivery-status-card__send-row">';
			echo '<label>' . esc_html__('Batch Size', 'backstage-outreach') . ' <input type="number" name="batch_size" min="1" max="' . esc_attr((string) $send_batch_cap) . '" value="' . esc_attr((string) $send_batch_input_value) . '" style="width:72px;" data-vms-send-batch-size="1"></label> ';
			if ($campaign_is_draft) {
				echo '<button type="submit" class="' . esc_attr($queued_recipients > 0 && $campaign_can_activate_delivery ? 'button button-primary' : 'button') . '"' . ($queued_recipients > 0 && $campaign_can_activate_delivery ? ' onclick="return confirm(' . esc_attr(wp_json_encode($activation_prompt_message)) . ');"' : '') . disabled($queued_recipients <= 0 || !$campaign_can_activate_delivery, true, false) . '>' . esc_html__('Activate & Continue', 'backstage-outreach') . '</button>';
			} else {
				echo '<button type="submit" class="' . esc_attr($queued_recipients > 0 && $campaign_can_send_invites ? 'button button-primary' : 'button') . '" data-vms-send-batch-button="1" data-vms-queued-count="' . esc_attr((string) $queued_recipients) . '" data-vms-empty-label="' . esc_attr(__('Send Next Batch Now', 'backstage-outreach')) . '" data-vms-batch-label-singular="' . esc_attr(__('Send Next %d Invite', 'backstage-outreach')) . '" data-vms-batch-label-plural="' . esc_attr(__('Send Next %d Invites', 'backstage-outreach')) . '"' . disabled($queued_recipients <= 0 || !$campaign_can_send_invites, true, false) . '>' . esc_html($send_button_label) . '</button>';
			}
			echo '</p>';
			echo '</form>';
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-form vms-pass-form--full vms-outreach-delivery-status-card__send-form" data-vms-outreach-bulk-form="1"' . ($campaign_is_draft ? ' data-vms-campaign-draft="1" data-vms-activation-prompt="' . esc_attr($activation_prompt_message) . '"' : '') . '>';
			echo '<input type="hidden" name="action" value="vms_pass_outreach_recipient_bulk">';
			echo '<input type="hidden" name="campaign_id" value="' . esc_attr((string) $campaign_id) . '">';
			echo '<input type="hidden" name="bulk_action" value="queue_all_unsent">';
			echo '<input type="hidden" name="activate_campaign" value="">';
			vms_outreach_nonce_field(
				'vms_pass_outreach_recipient_bulk',
				'vms-outreach-recipient-bulk-queue-all-' . $campaign_id . '-nonce'
			);
			echo '<p class="vms-pass-actions">';
			echo '<button type="submit" class="' . esc_attr($ready_recipients > 0 && ($campaign_can_send_invites || $campaign_can_activate_delivery) ? 'button button-primary' : 'button') . '"' . disabled($ready_recipients <= 0 || (!$campaign_can_send_invites && !$campaign_can_activate_delivery), true, false) . '>' . esc_html__('Submit All Unsent to Email Queue', 'backstage-outreach') . '</button>';
			if ($failed_recipients > 0) {
				echo ' <span class="description">' . esc_html(sprintf(_n('%d failed invite should be retried separately.', '%d failed invites should be retried separately.', $failed_recipients, 'backstage-outreach'), $failed_recipients)) . '</span>';
			}
			echo '</p>';
			echo '</form>';
			echo '<details class="vms-outreach-delivery-status-card__details"><summary>' . esc_html__('View details', 'backstage-outreach') . '</summary><div class="vms-outreach-delivery-status-card__details-body"><p>' . esc_html(sprintf(
				__('Capacity: %1$d total admissions · %2$d used admissions', 'backstage-outreach'),
				$total_admissions,
				max(0, absint($usage_summary['headcount'] ?? 0))
			)) . '</p><p>' . esc_html(sprintf(
				__('Claim rate: %1$s', 'backstage-outreach'),
				vms_pass_outreach_format_ratio($summary['claim_rate'] ?? null)
			)) . '</p><p>' . esc_html(sprintf(
				__('Attendance rate: %1$s', 'backstage-outreach'),
				vms_pass_outreach_format_ratio($summary['attendance_rate'] ?? null)
			)) . '</p></div></details>';
			echo '</div>';

		echo '<details ' . vms_pass_outreach_collapsible_details_attrs($section_context, 'recipient_list', array(
			'classes' => array('vms-pass-preview-summary', 'vms-pass-preview-summary--collapsible'),
			'default_open' => $total_recipients > 0 || $recipient_id > 0,
			'anchor' => 'vms-outreach-recipient-list',
		)) . '>';
		echo vms_pass_outreach_render_collapsible_summary(__('Recipient List', 'backstage-outreach'), '<span class="vms-pass-filter-pill">' . esc_html(sprintf(_n('%d recipient', '%d recipients', $total_recipients, 'backstage-outreach'), $total_recipients)) . '</span>');
		echo '<div id="vms-outreach-recipient-filters" class="vms-pass-preview-summary"><h3>' . esc_html__('Recipient Filters', 'backstage-outreach') . '</h3>';
		echo '<form method="get" action="' . esc_url($recipient_filters_action) . '" class="vms-pass-form">';
		echo '<input type="hidden" name="page" value="' . esc_attr($page_slug) . '">';
		if (function_exists('vms_pass_claims_menu_slug') && $page_slug === vms_pass_claims_menu_slug()) {
			echo '<input type="hidden" name="tab" value="' . esc_attr(vms_pass_outreach_tab_slug()) . '">';
		}
		echo '<input type="hidden" name="campaign_id" value="' . esc_attr((string) $campaign_id) . '">';
		echo '<div class="vms-pass-grid">';
		echo '<label>' . esc_html__('Search', 'backstage-outreach') . '<input type="search" name="recipient_search" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('Name, email, phone, company', 'backstage-outreach') . '"></label>';
		echo '<label>' . esc_html__('Status', 'backstage-outreach') . '<select name="recipient_status">';
		echo '<option value="">' . esc_html__('All statuses', 'backstage-outreach') . '</option>';
		foreach (vms_pass_outreach_recipient_status_labels() as $status_key => $status_label) {
			echo '<option value="' . esc_attr($status_key) . '"' . selected($status_filter, $status_key, false) . '>' . esc_html((string) $status_label) . '</option>';
		}
		echo '</select></label>';
		echo '<label>' . esc_html__('Group', 'backstage-outreach') . '<select name="recipient_group_label">';
		echo '<option value="">' . esc_html__('All groups', 'backstage-outreach') . '</option>';
		foreach ($group_labels as $group_label_option) {
			echo '<option value="' . esc_attr($group_label_option) . '"' . selected($group_filter, $group_label_option, false) . '>' . esc_html($group_label_option) . '</option>';
		}
		echo '</select></label>';
		echo '</div>';
		echo '<p class="vms-pass-actions"><button type="submit" class="button">' . esc_html__('Apply Filters', 'backstage-outreach') . '</button> <a class="button" href="' . esc_url($recipient_filters_reset_url) . '">' . esc_html__('Reset Filters', 'backstage-outreach') . '</a></p>';
		echo '</form>';
		echo '</div>';

		if (!empty($queue_review_selection['recipient_ids']) && !empty($queue_review_counts)) {
			echo '<div class="vms-pass-callout vms-pass-callout-warning">';
			echo '<h3>' . esc_html__('Queue Review Required', 'backstage-outreach') . '</h3>';
			echo '<p>' . esc_html(sprintf(__('You selected %d recipients.', 'backstage-outreach'), absint($queue_review_counts['selected_total'] ?? 0))) . '</p>';
			echo '<p>' . esc_html(sprintf(_n('%d recipient has not been sent an invite.', '%d recipients have not been sent an invite.', (int) ($queue_review_counts['queueable_unsent'] ?? 0), 'backstage-outreach'), (int) ($queue_review_counts['queueable_unsent'] ?? 0))) . '</p>';
			echo '<p>' . esc_html(sprintf(_n('%d recipient was already sent an invite.', '%d recipients were already sent an invite.', (int) ($queue_review_counts['already_sent'] ?? 0), 'backstage-outreach'), (int) ($queue_review_counts['already_sent'] ?? 0))) . '</p>';
			echo '<p>' . esc_html(sprintf(_n('%d recipient is already queued.', '%d recipients are already queued.', (int) ($queue_review_counts['already_queued'] ?? 0), 'backstage-outreach'), (int) ($queue_review_counts['already_queued'] ?? 0))) . '</p>';
			if (absint($queue_review_counts['failed_selected'] ?? 0) > 0) {
				echo '<p>' . esc_html(sprintf(_n('%d recipient has a failed invite and should use Retry Failed Invites.', '%d recipients have failed invites and should use Retry Failed Invites.', (int) ($queue_review_counts['failed_selected'] ?? 0), 'backstage-outreach'), (int) ($queue_review_counts['failed_selected'] ?? 0))) . '</p>';
			}
			if (absint($queue_review_counts['validation_failed'] ?? 0) + absint($queue_review_counts['blocked'] ?? 0) + absint($queue_review_counts['other'] ?? 0) > 0) {
				echo '<p>' . esc_html(sprintf(_n('%d recipient failed validation or is blocked.', '%d recipients failed validation or are blocked.', (int) (absint($queue_review_counts['validation_failed'] ?? 0) + absint($queue_review_counts['blocked'] ?? 0) + absint($queue_review_counts['other'] ?? 0)), 'backstage-outreach'), (int) (absint($queue_review_counts['validation_failed'] ?? 0) + absint($queue_review_counts['blocked'] ?? 0) + absint($queue_review_counts['other'] ?? 0)))) . '</p>';
			}
			echo '<p><strong>' . esc_html(sprintf(__('Recommended: submit only the %d unsent recipients.', 'backstage-outreach'), absint($queue_review_counts['queueable_unsent'] ?? 0))) . '</strong></p>';
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-form" data-vms-outreach-bulk-form="1"' . ($campaign_is_draft ? ' data-vms-campaign-draft="1" data-vms-activation-prompt="' . esc_attr($activation_prompt_message) . '"' : '') . '>';
			echo '<input type="hidden" name="action" value="vms_pass_outreach_recipient_bulk">';
			echo '<input type="hidden" name="campaign_id" value="' . esc_attr((string) $campaign_id) . '">';
			echo '<input type="hidden" name="bulk_action" value="queue_selected_confirm">';
			echo '<input type="hidden" name="queue_review_token" value="' . esc_attr($queue_review_token) . '">';
			echo '<input type="hidden" name="activate_campaign" value="">';
			vms_outreach_nonce_field(
				'vms_pass_outreach_recipient_bulk',
				'vms-outreach-recipient-bulk-queue-review-' . $campaign_id . '-nonce'
			);
			echo '<p class="vms-pass-actions"><button type="submit" class="' . esc_attr(absint($queue_review_counts['queueable_unsent'] ?? 0) > 0 && ($campaign_can_send_invites || $campaign_can_activate_delivery) ? 'button button-primary' : 'button') . '"' . disabled(absint($queue_review_counts['queueable_unsent'] ?? 0) <= 0 || (!$campaign_can_send_invites && !$campaign_can_activate_delivery), true, false) . '>' . esc_html(sprintf(__('Submit %d Unsent Recipients', 'backstage-outreach'), absint($queue_review_counts['queueable_unsent'] ?? 0))) . '</button> <a class="button" href="' . esc_url(vms_pass_outreach_recipient_redirect_url($campaign_id, 0, 'vms-outreach-recipient-list')) . '">' . esc_html__('Cancel', 'backstage-outreach') . '</a></p>';
			echo '</form>';
			echo '</div>';
		}

		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-form vms-pass-form--full" data-vms-outreach-bulk-form="1"' . ($campaign_is_draft ? ' data-vms-campaign-draft="1" data-vms-activation-prompt="' . esc_attr($activation_prompt_message) . '"' : '') . '>';
			echo '<input type="hidden" name="action" value="vms_pass_outreach_recipient_bulk">';
			echo '<input type="hidden" name="campaign_id" value="' . esc_attr((string) $campaign_id) . '">';
			echo '<input type="hidden" name="activate_campaign" value="">';
			echo '<input type="hidden" name="confirm_resend" value="">';
			vms_outreach_nonce_field(
				'vms_pass_outreach_recipient_bulk',
				'vms-outreach-recipient-bulk-list-' . $campaign_id . '-nonce'
			);
			echo '<p class="description">' . esc_html(sprintf(__('Showing %1$d of %2$d recipients for this campaign.', 'backstage-outreach'), count($recipients), absint($summary['total_recipients'] ?? 0))) . '</p>';
			echo '<p class="vms-pass-actions">';
			echo '<select name="bulk_action" data-vms-bulk-action-select="1">';
			foreach ($bulk_action_options as $bulk_action_value => $bulk_action_label) {
				echo '<option value="' . esc_attr((string) $bulk_action_value) . '">' . esc_html((string) $bulk_action_label) . '</option>';
			}
			echo '</select> <button type="button" class="button" data-vms-select-visible-unsent="1">' . esc_html__('Select Visible Unsent', 'backstage-outreach') . '</button> <button type="submit" class="button">' . esc_html__('Apply', 'backstage-outreach') . '</button></p>';
			echo '<div id="vms-outreach-recipient-table" class="vms-pass-table-scroll vms-pass-table-scroll--recipients" data-vms-sticky-table="1">';
			echo '<table class="widefat striped vms-pass-data-table vms-pass-recipient-table">';
			echo '<colgroup>';
			echo '<col class="vms-pass-col-check">';
			echo '<col class="vms-pass-col-name">';
			echo '<col class="vms-pass-col-email">';
			echo '<col class="vms-pass-col-phone">';
			echo '<col class="vms-pass-col-company">';
			echo '<col class="vms-pass-col-status">';
			echo '<col class="vms-pass-col-sent">';
			echo '<col class="vms-pass-col-claimed">';
			echo '<col class="vms-pass-col-claimed-qty">';
			echo '<col class="vms-pass-col-checked-in">';
			echo '<col class="vms-pass-col-actions">';
			echo '</colgroup>';
				echo '<thead><tr><td class="check-column"><input type="checkbox" data-vms-outreach-select-all></td><th>' . esc_html__('Name', 'backstage-outreach') . '</th><th>' . esc_html__('Email', 'backstage-outreach') . '</th><th>' . esc_html__('Phone', 'backstage-outreach') . '</th><th>' . esc_html__('Company / Group', 'backstage-outreach') . '</th><th class="vms-pass-table-cell--center"' . $table_heading_attr(__('Ready, sent, claimed, revoked, or expired', 'backstage-outreach')) . '>' . esc_html__('Status', 'backstage-outreach') . '</th><th class="vms-pass-table-cell--center"' . $table_heading_attr(__('Manual or VMS email delivery tracking for this recipient', 'backstage-outreach')) . '>' . esc_html__('Invite', 'backstage-outreach') . '</th><th class="vms-pass-table-cell--center"' . $table_heading_attr(__('Compact claim status. Hover for claim details.', 'backstage-outreach')) . '>' . esc_html__('Claimed', 'backstage-outreach') . '</th><th class="vms-pass-table-cell--center"' . $table_heading_attr(__('Passes reserved from this invite', 'backstage-outreach')) . '>' . esc_html__('Claimed Qty', 'backstage-outreach') . '</th><th class="vms-pass-table-cell--center"' . $table_heading_attr(__('Scans used at the gate', 'backstage-outreach')) . '>' . esc_html__('Checked In', 'backstage-outreach') . '</th><th class="vms-pass-table-cell--center">' . esc_html__('Actions', 'backstage-outreach') . '</th></tr></thead><tbody>';
		if (empty($recipients)) {
			echo '<tr><td colspan="11">' . esc_html__('No outreach recipients matched this view.', 'backstage-outreach') . '</td></tr>';
		} else {
			$status_labels = vms_pass_outreach_recipient_status_labels();
			foreach ($recipients as $row) {
				$row_id = absint($row['id'] ?? 0);
				$status = vms_pass_outreach_recipient_status_for_display($row, $campaign, $all_checked_in_map);
				$status_label = (string) ($status_labels[$status] ?? $status);
				$row_linked_contact = vms_pass_outreach_recipient_linked_contact($row);
				$row_contact_guardrail = vms_pass_outreach_recipient_contact_guardrail_state($row);
				$row_send_status = vms_pass_outreach_recipient_send_status_for_display($row, $row_contact_guardrail);
					$row_send_method_label = vms_pass_outreach_recipient_delivery_method_label($row);
					$row_last_send_error = trim((string) ($row['last_send_error'] ?? ''));
					$row_send_tooltip = $send_status_tooltip($row, $row_send_status, $row_send_method_label, $row_last_send_error, $row_contact_guardrail);
					$row_claim_state = vms_pass_outreach_recipient_claim_display_state($row, $campaign, $all_checked_in_map);
					$row_claim_tooltip = trim((string) ($row_claim_state['tooltip'] ?? ''));
					$linked_contact_url = '';
					$linked_contact_name = '';
					if (is_array($row_linked_contact)) {
						$linked_contact_name = function_exists('vms_outreach_contact_display_name')
							? vms_outreach_contact_display_name($row_linked_contact)
							: sanitize_text_field((string) ($row_linked_contact['contact_name'] ?? ''));
						$linked_contact_url = function_exists('vms_outreach_contacts_admin_url')
							? vms_outreach_contacts_admin_url(array('view' => 'edit', 'contact_id' => (int) ($row_linked_contact['id'] ?? 0)))
							: '';
					}
					$row_name = vms_pass_outreach_recipient_full_name($row);
					$name_tooltip_bits = array(
						sprintf(__('Recipient ID: %d', 'backstage-outreach'), $row_id),
					);
					if ($linked_contact_name !== '') {
						$name_tooltip_bits[] = sprintf(__('Linked contact: %s', 'backstage-outreach'), $linked_contact_name);
					}
					$name_tooltip = implode("\n", array_filter($name_tooltip_bits));
					$name_html = '<strong>' . esc_html($row_name) . '</strong>';
					if ($linked_contact_url !== '') {
						$name_html = '<strong><a href="' . esc_url($linked_contact_url) . '"' . ($name_tooltip !== '' ? ' title="' . esc_attr($name_tooltip) . '"' : '') . '>' . esc_html($row_name) . '</a></strong>';
					} elseif ($name_tooltip !== '') {
						$name_html = '<strong><span title="' . esc_attr($name_tooltip) . '">' . esc_html($row_name) . '</span></strong>';
					}
					$company_group = trim((string) ($row['company'] ?? ''));
					$group_label = trim((string) ($row['group_label'] ?? ''));
					if ($company_group !== '' && $group_label !== '') {
						$company_group .= ' / ' . $group_label;
					} elseif ($company_group === '') {
					$company_group = $group_label;
				}
				$edit_url = vms_pass_outreach_recipient_redirect_url($campaign_id, $row_id);
				$mark_sent_url = wp_nonce_url(add_query_arg(array(
					'action' => 'vms_pass_outreach_recipient_mark_sent',
					'campaign_id' => $campaign_id,
					'recipient_id' => $row_id,
				), admin_url('admin-post.php')), 'vms_pass_outreach_recipient_mark_sent_' . $row_id);
				$mark_not_sent_url = wp_nonce_url(add_query_arg(array(
					'action' => 'vms_pass_outreach_recipient_mark_not_sent',
					'campaign_id' => $campaign_id,
					'recipient_id' => $row_id,
				), admin_url('admin-post.php')), 'vms_pass_outreach_recipient_mark_not_sent_' . $row_id);
				$queue_url = wp_nonce_url(add_query_arg(array(
					'action' => 'vms_pass_outreach_recipient_queue',
					'campaign_id' => $campaign_id,
					'recipient_id' => $row_id,
				), admin_url('admin-post.php')), 'vms_pass_outreach_recipient_queue_' . $row_id);
				$send_email_url = wp_nonce_url(add_query_arg(array(
					'action' => 'vms_pass_outreach_recipient_send_email',
					'campaign_id' => $campaign_id,
					'recipient_id' => $row_id,
				), admin_url('admin-post.php')), 'vms_pass_outreach_recipient_send_email_' . $row_id);
				$resend_email_url = wp_nonce_url(add_query_arg(array(
					'action' => 'vms_pass_outreach_recipient_send_email',
					'campaign_id' => $campaign_id,
					'recipient_id' => $row_id,
					'resend' => 1,
				), admin_url('admin-post.php')), 'vms_pass_outreach_recipient_send_email_' . $row_id);
				$mark_interested_url = wp_nonce_url(add_query_arg(array(
					'action' => 'vms_pass_outreach_recipient_mark_interested',
					'campaign_id' => $campaign_id,
					'recipient_id' => $row_id,
				), admin_url('admin-post.php')), 'vms_pass_outreach_recipient_mark_interested_' . $row_id);
				$suppress_url = wp_nonce_url(add_query_arg(array(
					'action' => 'vms_pass_outreach_recipient_suppress',
					'campaign_id' => $campaign_id,
					'recipient_id' => $row_id,
				), admin_url('admin-post.php')), 'vms_pass_outreach_recipient_suppress_' . $row_id);
				$do_not_contact_url = wp_nonce_url(add_query_arg(array(
					'action' => 'vms_pass_outreach_recipient_do_not_contact',
					'campaign_id' => $campaign_id,
					'recipient_id' => $row_id,
				), admin_url('admin-post.php')), 'vms_pass_outreach_recipient_do_not_contact_' . $row_id);
				$revoke_url = wp_nonce_url(add_query_arg(array(
					'action' => 'vms_pass_outreach_recipient_revoke',
					'campaign_id' => $campaign_id,
					'recipient_id' => $row_id,
				), admin_url('admin-post.php')), 'vms_pass_outreach_recipient_revoke_' . $row_id);
				$delete_url = wp_nonce_url(add_query_arg(array(
					'action' => 'vms_pass_outreach_recipient_delete',
					'campaign_id' => $campaign_id,
					'recipient_id' => $row_id,
				), admin_url('admin-post.php')), 'vms_pass_outreach_recipient_delete_' . $row_id);
				$link_id = 'vms-outreach-link-' . $row_id;
				$message_id = 'vms-outreach-message-' . $row_id;
				$can_mark_sent = vms_pass_outreach_recipient_can_mark_sent($row);
				$can_mark_not_sent = vms_pass_outreach_recipient_can_mark_not_sent($row);
				$can_queue = vms_pass_outreach_recipient_can_queue($row);
				$can_retry_failed = vms_pass_outreach_recipient_can_retry_failed($row);
				$can_mark_interested = vms_pass_outreach_recipient_can_mark_interested($row);
				$can_suppress = vms_pass_outreach_recipient_can_suppress($row);
				$can_do_not_contact = vms_pass_outreach_recipient_can_do_not_contact($row);
				$can_revoke = vms_pass_outreach_recipient_can_revoke($row);
				$can_delete = vms_pass_outreach_recipient_can_delete($row);
				$has_send_record = !empty($row['sent_at']) || vms_pass_outreach_recipient_stored_send_status($row) === 'sent';
				$row_delivery_validation = vms_pass_outreach_validate_recipient_delivery($row, $campaign, $campaign_claim_guardrail);
				$row_email_delivery = vms_pass_outreach_recipient_email_delivery_validation($row);
				$row_activation_validation = array('ok' => false);
				if ($campaign_can_activate_delivery) {
					$row_activation_validation = vms_pass_outreach_validate_recipient_delivery($row, $campaign_activation_preview, $campaign_activation_guardrail);
				}
				$can_attempt_send = !empty($row_email_delivery['ok'])
					&& empty($row_contact_guardrail['blocked'])
					&& !empty($row_delivery_validation['ok']);
				$can_send_email = $can_attempt_send && !$has_send_record;
				$can_resend_email = $can_attempt_send && $has_send_record;
				$can_activate_delivery = $campaign_is_draft
					&& $campaign_can_activate_delivery
					&& empty($row_contact_guardrail['blocked'])
					&& !empty($row_activation_validation['ok']);
				$can_activate_send_email = $can_activate_delivery && !empty($row_email_delivery['ok']) && !$has_send_record;
				$can_activate_resend_email = $can_activate_delivery && !empty($row_email_delivery['ok']) && $has_send_record;
				$stored_send_status = vms_pass_outreach_recipient_stored_send_status($row);
				$can_activate_queue = $can_activate_delivery && !empty($row_email_delivery['ok']) && $stored_send_status === 'not_sent';
				$can_activate_retry_failed = $can_activate_delivery && !empty($row_email_delivery['ok']) && $stored_send_status === 'failed';
				$draft_action_confirm = ' onclick="return confirm(' . esc_attr(wp_json_encode($activation_prompt_message)) . ');"';

					echo '<tr>';
					echo '<th scope="row" class="check-column"><input type="checkbox" name="recipient_ids[]" value="' . esc_attr((string) $row_id) . '" data-vms-outreach-recipient data-vms-send-status="' . esc_attr($row_send_status) . '"></th>';
					echo '<td>' . $name_html . '</td>';
					echo '<td>' . esc_html((string) ($row['email'] ?? '')) . '</td>';
					echo '<td>' . esc_html((string) ($row['phone'] ?? '')) . '</td>';
					echo '<td>' . esc_html($company_group) . '</td>';
					echo '<td class="vms-pass-table-cell--center">' . esc_html($status_label);
					if (!empty($row_contact_guardrail['blocked'])) {
						echo '<div class="description">' . esc_html((string) ($row_contact_guardrail['reason_label'] ?? __('Blocked', 'backstage-outreach'))) . '</div>';
					}
					echo '</td>';
					echo '<td class="vms-pass-table-cell--center">' . vms_pass_outreach_send_status_pill_html($row_send_status, $row_send_tooltip);
					if ($row_send_method_label !== '') {
						echo '<div class="description">' . esc_html($row_send_method_label) . '</div>';
					}
					echo '</td>';
					echo '<td class="vms-pass-table-cell--center">' . vms_pass_outreach_named_status_pill_html((string) ($row_claim_state['label'] ?? __('Claimed', 'backstage-outreach')), (string) ($row_claim_state['variant'] ?? 'not_sent'), $row_claim_tooltip) . '</td>';
					echo '<td class="vms-pass-table-cell--count">' . esc_html((string) absint($row['claimed_headcount'] ?? 0)) . '</td>';
					echo '<td class="vms-pass-table-cell--count">' . esc_html((string) vms_pass_outreach_recipient_checked_in_count($row, $all_checked_in_map)) . '</td>';
					echo '<td class="vms-pass-recipient-actions">';
				echo '<input type="text" class="vms-pass-copy-source" id="' . esc_attr($link_id) . '" readonly value="' . esc_attr(vms_pass_outreach_build_invite_url($row)) . '">';
				echo '<textarea class="vms-pass-copy-source" id="' . esc_attr($message_id) . '" readonly>' . esc_textarea(vms_pass_outreach_build_invite_message($row, $campaign)) . '</textarea>';
				echo '<div class="vms-pass-row-actions">';
				echo '<button type="button" class="button button-small vms-pass-row-actions__trigger" data-vms-action-menu-trigger="vms-pass-recipient-actions-' . esc_attr((string) $row_id) . '" aria-haspopup="true" aria-expanded="false">' . esc_html__('Actions', 'backstage-outreach') . '</button>';
				echo '<div id="vms-pass-recipient-actions-' . esc_attr((string) $row_id) . '" class="vms-pass-row-actions__template" hidden>';
				echo '<div class="vms-pass-row-actions__menu">';
					echo '<button type="button" class="button button-small" data-vms-copy="#' . esc_attr($link_id) . '">' . esc_html__('Copy Link', 'backstage-outreach') . '</button>';
					echo '<button type="button" class="button button-small" data-vms-copy="#' . esc_attr($message_id) . '">' . esc_html__('Copy Invite Message', 'backstage-outreach') . '</button>';
					echo '<a class="button button-small" href="' . esc_url($edit_url) . '">' . esc_html__('Edit', 'backstage-outreach') . '</a>';
					if ($linked_contact_url !== '') {
						echo '<a class="button button-small" href="' . esc_url($linked_contact_url) . '">' . esc_html__('View Linked Contact', 'backstage-outreach') . '</a>';
					}
					if ($can_send_email) {
						echo '<a class="button button-small" href="' . esc_url($send_email_url) . '">' . esc_html__('Send Invite Email', 'backstage-outreach') . '</a>';
					} elseif ($can_activate_send_email) {
						echo '<a class="button button-small" href="' . esc_url(add_query_arg('activate_campaign', 1, $send_email_url)) . '"' . $draft_action_confirm . '>' . esc_html__('Activate & Send Invite Email', 'backstage-outreach') . '</a>';
					}
				if ($can_resend_email) {
					echo '<a class="button button-small" href="' . esc_url($resend_email_url) . '" onclick="return confirm(' . esc_attr(wp_json_encode(__('This recipient already has a sent record. Resend the invite email?', 'backstage-outreach'))) . ');">' . esc_html__('Resend Invite Email', 'backstage-outreach') . '</a>';
				} elseif ($can_activate_resend_email) {
					echo '<a class="button button-small" href="' . esc_url(add_query_arg(array('activate_campaign' => 1, 'resend' => 1), $send_email_url)) . '"' . $draft_action_confirm . '>' . esc_html__('Activate & Resend Invite Email', 'backstage-outreach') . '</a>';
				}
				if ($can_mark_sent) {
					echo '<a class="button button-small" href="' . esc_url($mark_sent_url) . '">' . esc_html__('Mark Sent', 'backstage-outreach') . '</a>';
				}
				if ($can_mark_not_sent) {
					echo '<a class="button button-small" href="' . esc_url($mark_not_sent_url) . '">' . esc_html__('Mark Not Sent', 'backstage-outreach') . '</a>';
				}
				if ($can_queue) {
					echo '<a class="button button-small" href="' . esc_url($queue_url) . '">' . esc_html__('Submit to Email Queue', 'backstage-outreach') . '</a>';
				} elseif ($can_activate_queue) {
					echo '<a class="button button-small" href="' . esc_url(add_query_arg('activate_campaign', 1, $queue_url)) . '"' . $draft_action_confirm . '>' . esc_html__('Activate & Queue Invite', 'backstage-outreach') . '</a>';
				} elseif ($can_retry_failed) {
					echo '<a class="button button-small" href="' . esc_url(add_query_arg('retry_failed', 1, $queue_url)) . '">' . esc_html__('Retry Failed Invite', 'backstage-outreach') . '</a>';
				} elseif ($can_activate_retry_failed) {
					echo '<a class="button button-small" href="' . esc_url(add_query_arg(array('activate_campaign' => 1, 'retry_failed' => 1), $queue_url)) . '"' . $draft_action_confirm . '>' . esc_html__('Activate & Retry Failed Invite', 'backstage-outreach') . '</a>';
				}
				if ($can_mark_interested) {
					echo '<a class="button button-small" href="' . esc_url($mark_interested_url) . '">' . esc_html__('Mark Interested', 'backstage-outreach') . '</a>';
				}
				if ($can_suppress) {
					echo '<a class="button button-small" href="' . esc_url($suppress_url) . '" onclick="return confirm(' . esc_attr(wp_json_encode(__('Suppress this email from future outreach sends?', 'backstage-outreach'))) . ');">' . esc_html__('Suppress', 'backstage-outreach') . '</a>';
				}
				if ($can_do_not_contact) {
					echo '<a class="button button-small" href="' . esc_url($do_not_contact_url) . '" onclick="return confirm(' . esc_attr(wp_json_encode(__('Mark this recipient Do Not Contact and add outreach suppression?', 'backstage-outreach'))) . ');">' . esc_html__('Do Not Contact', 'backstage-outreach') . '</a>';
				}
				if ($can_revoke) {
					echo '<a class="button button-small" href="' . esc_url($revoke_url) . '" onclick="return confirm(' . esc_attr(wp_json_encode(__('Revoke this outreach invite?', 'backstage-outreach'))) . ');">' . esc_html__('Revoke', 'backstage-outreach') . '</a>';
				}
				if ($can_delete) {
					echo '<a class="button button-small" href="' . esc_url($delete_url) . '" onclick="return confirm(' . esc_attr(wp_json_encode(__('Delete this outreach recipient?', 'backstage-outreach'))) . ');">' . esc_html__('Delete', 'backstage-outreach') . '</a>';
				}
				echo '</div>';
				echo '</div>';
				echo '</div>';
				echo '</td>';
				echo '</tr>';
			}
			}
			echo '</tbody></table>';
			echo '</div>';
			echo '<p class="vms-pass-actions">';
			echo '<select data-vms-bulk-action-select="1">';
			foreach ($bulk_action_options as $bulk_action_value => $bulk_action_label) {
				echo '<option value="' . esc_attr((string) $bulk_action_value) . '">' . esc_html((string) $bulk_action_label) . '</option>';
			}
			echo '</select> <button type="button" class="button" data-vms-select-visible-unsent="1">' . esc_html__('Select Visible Unsent', 'backstage-outreach') . '</button> <button type="submit" class="button">' . esc_html__('Apply', 'backstage-outreach') . '</button></p>';
			echo '</form>';
			echo '</details>';
			if ($total_recipients > 0) {
				echo $add_recipients_panel_html;
			}
			echo '<script>(function(){function updateSendButtonLabel(){var button=document.querySelector("[data-vms-send-batch-button]");var input=document.querySelector("[data-vms-send-batch-size]");if(!button||!input){return;}var queued=parseInt(button.getAttribute("data-vms-queued-count")||"0",10)||0;var value=parseInt(input.value||"0",10)||1;if(value<1){value=1;}if(queued<=0){button.textContent=button.getAttribute("data-vms-empty-label")||"Send Next Batch Now";return;}var count=Math.min(value,queued);var template=(count===1?(button.getAttribute("data-vms-batch-label-singular")||"Send Next %d Invite"):(button.getAttribute("data-vms-batch-label-plural")||"Send Next %d Invites"));button.textContent=template.replace("%d",String(count));}function getBulkAction(form){var select=form.querySelector(\'select[name="bulk_action"]\');if(select){return select.value||"";}var hidden=form.querySelector(\'input[name="bulk_action"]\');return hidden?hidden.value||"":"";}function syncBulkActionSelects(form,source){if(!form){return;}var selects=form.querySelectorAll("[data-vms-bulk-action-select]");if(!selects.length){return;}var value=source?source.value:(getBulkAction(form)||"");selects.forEach(function(select){if(select!==source){select.value=value;}});var primary=form.querySelector(\'select[name="bulk_action"]\');if(primary&&primary!==source){primary.value=value;}}function syncSelectAll(form){if(!form){return;}var selectAll=form.querySelector("[data-vms-outreach-select-all]");if(!selectAll){return;}var boxes=form.querySelectorAll("[data-vms-outreach-recipient]");if(!boxes.length){selectAll.checked=false;return;}var allChecked=true;boxes.forEach(function(box){if(!box.checked){allChecked=false;}});selectAll.checked=allChecked;}document.addEventListener("change",function(event){if(event.target.matches("[data-vms-outreach-select-all]")){var checked=!!event.target.checked;var form=event.target.form||event.target.closest("form");if(!form){return;}form.querySelectorAll("[data-vms-outreach-recipient]").forEach(function(box){box.checked=checked;});syncSelectAll(form);}if(event.target.matches("[data-vms-outreach-recipient]")){syncSelectAll(event.target.form||event.target.closest("form"));}if(event.target.matches("[data-vms-send-batch-size]")){updateSendButtonLabel();}if(event.target.matches("[data-vms-bulk-action-select]")){syncBulkActionSelects(event.target.form||event.target.closest("form"),event.target);}});document.addEventListener("click",function(event){var unsentButton=event.target.closest("[data-vms-select-visible-unsent]");if(unsentButton){var form=unsentButton.closest("form");if(!form){return;}form.querySelectorAll("[data-vms-outreach-recipient]").forEach(function(box){box.checked=box.getAttribute("data-vms-send-status")==="not_sent";});syncSelectAll(form);}});document.addEventListener("input",function(event){if(event.target.matches("[data-vms-send-batch-size]")){updateSendButtonLabel();}});document.querySelectorAll("[data-vms-outreach-bulk-form]").forEach(function(form){form.addEventListener("submit",function(event){var action=getBulkAction(form);var activateField=form.querySelector(\'input[name="activate_campaign"]\');var resendField=form.querySelector(\'input[name="confirm_resend"]\');if(activateField){activateField.value="";}if(resendField){resendField.value="";}if(action==="resend_selected"){if(!window.confirm("This will send another invite email to recipients who were already contacted. Continue?")){event.preventDefault();return;}if(resendField){resendField.value="1";}}if(["queue_selected","queue_selected_confirm","queue_all_unsent","retry_failed_selected","resend_selected"].indexOf(action)!==-1&&form.getAttribute("data-vms-campaign-draft")==="1"){var promptMessage=form.getAttribute("data-vms-activation-prompt")||"";if(promptMessage!==""&&!window.confirm(promptMessage)){event.preventDefault();return;}if(activateField){activateField.value="1";}}});syncSelectAll(form);syncBulkActionSelects(form,null);});updateSendButtonLabel();})();</script>';
		echo '</section>';
	}
}
