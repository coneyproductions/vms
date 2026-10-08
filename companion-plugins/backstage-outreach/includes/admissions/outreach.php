<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_pass_outreach_tab_slug')) {
	function vms_pass_outreach_tab_slug(): string
	{
		return 'outreach';
	}
}

if (!function_exists('vms_outreach_admin_menu_slug')) {
	function vms_outreach_admin_menu_slug(): string
	{
		return 'vms-outreach';
	}
}

if (!function_exists('vms_outreach_admin_page_url')) {
	function vms_outreach_admin_page_url(array $args = array()): string
	{
		return add_query_arg($args, admin_url('admin.php?page=' . vms_outreach_admin_menu_slug()));
	}
}

if (!function_exists('vms_pass_outreach_collapsible_context')) {
	function vms_pass_outreach_collapsible_context(int $campaign_id): string
	{
		return 'outreach-campaign:' . ($campaign_id > 0 ? (string) $campaign_id : 'new');
	}
}

if (!function_exists('vms_pass_outreach_section_has_errors')) {
	function vms_pass_outreach_section_has_errors(array $field_errors, array $keys): bool
	{
		foreach ($keys as $key) {
			if (!empty($field_errors[(string) $key])) {
				return true;
			}
		}

		return false;
	}
}

if (!function_exists('vms_pass_outreach_collapsible_details_attrs')) {
	function vms_pass_outreach_collapsible_details_attrs(string $context, string $section_id, array $args = array()): string
	{
		$classes = array_values(array_filter(array_map('sanitize_html_class', (array) ($args['classes'] ?? array()))));
		if (empty($classes)) {
			$classes = array('vms-pass-form-section');
		}

		if (!in_array('vms-pass-collapsible-panel', $classes, true)) {
			$classes[] = 'vms-pass-collapsible-panel';
		}

		$attributes = array(
			'class="' . esc_attr(implode(' ', array_unique($classes))) . '"',
			'data-vms-collapsible-section="1"',
			'data-vms-section-context="' . esc_attr($context) . '"',
			'data-vms-section-id="' . esc_attr(sanitize_key($section_id)) . '"',
		);

		if (!empty($args['anchor'])) {
			$attributes[] = 'id="' . esc_attr(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $args['anchor'])) . '"';
		}

		$default_open = !empty($args['default_open']);
		$force_open = !empty($args['force_open']);
		if ($default_open || $force_open) {
			$attributes[] = 'open';
		}
		if ($force_open) {
			$attributes[] = 'data-vms-force-open="1"';
		}

		return implode(' ', $attributes);
	}
}

if (!function_exists('vms_pass_outreach_render_collapsible_summary')) {
	function vms_pass_outreach_render_collapsible_summary(string $title, string $meta_html = ''): string
	{
		$html = '<summary class="vms-pass-collapsible-panel__summary">';
		$html .= '<span class="vms-pass-collapsible-panel__summary-text">' . esc_html($title) . '</span>';
		if ($meta_html !== '') {
			$html .= '<span class="vms-pass-collapsible-panel__summary-meta">' . $meta_html . '</span>';
		}
		$html .= '</summary>';

		return $html;
	}
}

if (!function_exists('vms_outreach_is_admin_page')) {
	function vms_outreach_is_admin_page(): bool
	{
		if (!is_admin()) {
			return false;
		}

		$page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
		return $page === vms_outreach_admin_menu_slug();
	}
}

if (!function_exists('vms_outreach_default_campaign_purpose')) {
	function vms_outreach_default_campaign_purpose(): string
	{
		return 'guest_pass_invitation';
	}
}

if (!function_exists('vms_outreach_purpose_catalog')) {
	function vms_outreach_purpose_catalog(): array
	{
		$purposes = array(
			'guest_pass_invitation' => array(
				'label' => __('Guest Pass Invitation', 'backstage-outreach'),
				'description' => __('Import recipients, reserve invite links, preview merge tags, export send-prep CSVs, and track claims/check-ins.', 'backstage-outreach'),
				'available' => true,
			),
			'vendor_recruitment' => array(
				'label' => __('Vendor Recruitment', 'backstage-outreach'),
				'description' => __('Vendor prospecting campaigns will reuse the same outreach engine without duplicating contacts, templates, or logs.', 'backstage-outreach'),
				'available' => false,
			),
			'sponsor_prospecting' => array(
				'label' => __('Sponsor Prospecting', 'backstage-outreach'),
				'description' => __('Sponsorship outreach will share campaigns, recipients, templates, and workflow tracking from this module.', 'backstage-outreach'),
				'available' => false,
			),
			'referral_partner_outreach' => array(
				'label' => __('Referral Partner Outreach', 'backstage-outreach'),
				'description' => __('Partner outreach will live here with the same import, preview, and tracking workflow.', 'backstage-outreach'),
				'available' => false,
			),
			'general_prospect_outreach' => array(
				'label' => __('General Prospect Outreach', 'backstage-outreach'),
				'description' => __('Broader prospecting campaigns can reuse this engine without turning VMS into a customer email sender.', 'backstage-outreach'),
				'available' => false,
			),
			'mailpoet_audience' => array(
				'label' => __('MailPoet Audience', 'backstage-outreach'),
				'description' => __('VMS can prepare MailPoet audiences, tags, or lists here. Customer sending remains in MailPoet.', 'backstage-outreach'),
				'available' => false,
			),
		);

		return apply_filters('vms_outreach_purpose_catalog', $purposes);
	}
}

if (!function_exists('vms_outreach_purpose_labels')) {
	function vms_outreach_purpose_labels(): array
	{
		$labels = array();
		foreach (vms_outreach_purpose_catalog() as $purpose_key => $purpose) {
			$labels[$purpose_key] = sanitize_text_field((string) ($purpose['label'] ?? $purpose_key));
		}
		return $labels;
	}
}

if (!function_exists('vms_outreach_normalize_campaign_purpose')) {
	function vms_outreach_normalize_campaign_purpose(string $purpose): string
	{
		$purpose = sanitize_key($purpose);
		$catalog = vms_outreach_purpose_catalog();
		if ($purpose === '' || !isset($catalog[$purpose])) {
			return vms_outreach_default_campaign_purpose();
		}
		return $purpose;
	}
}

if (!function_exists('vms_outreach_is_purpose_available')) {
	function vms_outreach_is_purpose_available(string $purpose): bool
	{
		$purpose = vms_outreach_normalize_campaign_purpose($purpose);
		$catalog = vms_outreach_purpose_catalog();
		return !empty($catalog[$purpose]['available']);
	}
}

if (!function_exists('vms_pass_outreach_admin_page_url')) {
	function vms_pass_outreach_admin_page_url(array $args = array(), bool $legacy_alias = false): string
	{
		if (!$legacy_alias && function_exists('vms_outreach_admin_page_url')) {
			return vms_outreach_admin_page_url($args);
		}

		$legacy_args = array_merge(array(
			'tab' => vms_pass_outreach_tab_slug(),
			'_vms_legacy_outreach_alias' => 1,
		), $args);

		return function_exists('vms_pass_claims_admin_page_url')
			? vms_pass_claims_admin_page_url($legacy_args)
			: admin_url('admin.php?page=vms-passes');
	}
}

if (!function_exists('vms_pass_outreach_allowed_statuses')) {
	function vms_pass_outreach_allowed_statuses(): array
	{
		return array('draft', 'active', 'closed');
	}
}

if (!function_exists('vms_pass_outreach_allowed_workflow_modes')) {
	function vms_pass_outreach_allowed_workflow_modes(): array
	{
		return array('upload_first', 'manual');
	}
}

if (!function_exists('vms_pass_outreach_allowed_tracking_category_modes')) {
	function vms_pass_outreach_allowed_tracking_category_modes(): array
	{
		return array('existing', 'new');
	}
}

if (!function_exists('vms_pass_outreach_allowed_recipient_source_modes')) {
	function vms_pass_outreach_allowed_recipient_source_modes(): array
	{
		return array('csv_new', 'existing_source', 'business_source', 'contacts');
	}
}

if (!function_exists('vms_pass_outreach_runtime_statuses')) {
	function vms_pass_outreach_runtime_statuses(): array
	{
		return array('active', 'closed');
	}
}

if (!function_exists('vms_pass_outreach_status_labels')) {
	function vms_pass_outreach_status_labels(): array
	{
		return array(
			'draft' => __('Draft', 'backstage-outreach'),
			'active' => __('Active', 'backstage-outreach'),
			'closed' => __('Closed', 'backstage-outreach'),
		);
	}
}

if (!function_exists('vms_pass_outreach_campaign_display_status')) {
	function vms_pass_outreach_campaign_display_status(array $campaign, ?array $summary = null): array
	{
		if ($summary === null && function_exists('vms_pass_outreach_campaign_summary')) {
			$summary = vms_pass_outreach_campaign_summary($campaign);
		}
		if (!is_array($summary)) {
			$summary = array();
		}
		if (function_exists('backstage_outreach_is_reusable_business_campaign')
			&& function_exists('backstage_outreach_business_contact_dashboard')
			&& backstage_outreach_is_reusable_business_campaign($campaign)) {
			$contact_dashboard = backstage_outreach_business_contact_dashboard(absint($campaign['id'] ?? 0));
			$contact_summary = (array) ($contact_dashboard['summary'] ?? array());
			$total_businesses = absint($contact_summary['total_businesses'] ?? 0);
			$without_contact = absint($contact_summary['no_contact'] ?? 0);
			$contacted = max(0, $total_businesses - $without_contact);
			if (absint($contact_summary['follow_up_needed'] ?? 0) > 0 || absint($contact_summary['email_failed'] ?? 0) > 0) {
				return array('key' => 'needs_attention', 'label' => __('Needs Attention', 'backstage-outreach'), 'variant' => 'failed');
			}
			if ($contacted > 0 && $without_contact > 0) {
				return array('key' => 'outreach_in_progress', 'label' => __('Outreach in progress', 'backstage-outreach'), 'variant' => 'partially_sent');
			}
			if ($contacted > 0 && $without_contact === 0) {
				return array('key' => 'outreach_recorded', 'label' => __('Outreach recorded', 'backstage-outreach'), 'variant' => 'complete');
			}
		}

		$stored_status = sanitize_key((string) ($campaign['status'] ?? 'draft'));
		$total = absint($summary['total_recipients'] ?? 0);
		$ready = absint($summary['ready_recipients'] ?? 0);
		$queued = absint($summary['queued_recipients'] ?? 0);
		$sent = absint($summary['sent_recipients'] ?? 0);
		$failed = absint($summary['failed_recipients'] ?? 0);

		if ($queued > 0) {
			return array(
				'key' => 'sending',
				'label' => __('Sending', 'backstage-outreach'),
				'variant' => 'active',
			);
		}
		if ($failed > 0) {
			return array(
				'key' => 'needs_attention',
				'label' => __('Needs Attention', 'backstage-outreach'),
				'variant' => 'failed',
			);
		}
		if ($sent > 0 && $ready > 0) {
			return array(
				'key' => 'partially_sent',
				'label' => __('Partially Sent', 'backstage-outreach'),
				'variant' => 'partially_sent',
			);
		}
		if ($sent > 0 && $queued === 0 && $ready === 0) {
			return array(
				'key' => 'complete',
				'label' => __('Complete', 'backstage-outreach'),
				'variant' => 'complete',
			);
		}
		if ($stored_status === 'closed') {
			return array(
				'key' => 'closed',
				'label' => __('Closed', 'backstage-outreach'),
				'variant' => 'closed',
			);
		}
		if ($stored_status === 'active' || $total > 0) {
			return array(
				'key' => 'ready',
				'label' => __('Ready', 'backstage-outreach'),
				'variant' => 'ready',
			);
		}

		return array(
			'key' => 'draft',
			'label' => __('Draft', 'backstage-outreach'),
			'variant' => 'draft',
		);
	}
}

if (!function_exists('vms_pass_outreach_allowed_eligibility_modes')) {
	function vms_pass_outreach_allowed_eligibility_modes(): array
	{
		return array('anyone_with_invite', 'first_time_visitors_only');
	}
}

if (!function_exists('vms_pass_outreach_eligibility_labels')) {
	function vms_pass_outreach_eligibility_labels(): array
	{
		return array(
			'anyone_with_invite' => __('Anyone With Invite', 'backstage-outreach'),
			'first_time_visitors_only' => __('First-Time Visitors Only', 'backstage-outreach'),
		);
	}
}

if (!function_exists('vms_pass_outreach_allowed_validity_types')) {
	function vms_pass_outreach_allowed_validity_types(): array
	{
		return array('batch_default', 'single_event', 'date_range', 'season', 'any_event');
	}
}

if (!function_exists('vms_pass_outreach_validity_labels')) {
	function vms_pass_outreach_validity_labels(): array
	{
		return array(
			'batch_default' => __('Match Linked Batch', 'backstage-outreach'),
			'single_event' => __('Single Event', 'backstage-outreach'),
			'date_range' => __('Date Range', 'backstage-outreach'),
			'season' => __('Season', 'backstage-outreach'),
			'any_event' => __('Any Event', 'backstage-outreach'),
		);
	}
}

if (!function_exists('vms_pass_outreach_form_validity_labels')) {
	function vms_pass_outreach_form_validity_labels(string $current = 'any_event'): array
	{
		$labels = array(
			'any_event' => __('Any Event', 'backstage-outreach'),
			'single_event' => __('Single Event', 'backstage-outreach'),
			'date_range' => __('Date Range', 'backstage-outreach'),
			'season' => __('Season', 'backstage-outreach'),
		);

		if ($current === 'batch_default') {
			$labels['batch_default'] = __('Match Linked Batch (Legacy)', 'backstage-outreach');
		}

		return $labels;
	}
}

if (!function_exists('vms_pass_outreach_csv_template_columns')) {
	function vms_pass_outreach_csv_template_columns(): array
	{
		return array(
			'first_name',
			'last_name',
			'name',
			'email',
			'phone',
			'company',
			'group',
			'notes',
			'expires_at',
		);
	}
}

if (!function_exists('vms_pass_outreach_csv_template_download_url')) {
	function vms_pass_outreach_csv_template_download_url(): string
	{
		return wp_nonce_url(
			add_query_arg(array(
				'action' => 'vms_pass_outreach_download_csv_template',
			), admin_url('admin-post.php')),
			'vms_pass_outreach_download_csv_template'
		);
	}
}

if (!function_exists('vms_pass_outreach_handle_csv_template_download')) {
	function vms_pass_outreach_handle_csv_template_download(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('You are not allowed to download this CSV template.', 'backstage-outreach'), '', array('response' => 403));
		}

		check_admin_referer('vms_pass_outreach_download_csv_template');

		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="guest-pass-outreach-template.csv"');

		$handle = fopen('php://output', 'wb');
		if ($handle === false) {
			wp_die(esc_html__('Could not generate the CSV template.', 'backstage-outreach'));
		}

		fputcsv($handle, vms_pass_outreach_csv_template_columns());
		fclose($handle);
		exit;
	}
}
add_action('admin_post_vms_pass_outreach_download_csv_template', 'vms_pass_outreach_handle_csv_template_download');

if (!function_exists('vms_pass_outreach_public_failure_message')) {
	function vms_pass_outreach_public_failure_message(): string
	{
		return __('This guest pass invitation could not be claimed. These invitations are limited and may depend on eligibility, availability, or prior use. You\'re still welcome to purchase tickets, or contact us if you believe this is a mistake.', 'backstage-outreach');
	}
}

if (!function_exists('vms_pass_outreach_draft_send_warning_message')) {
	function vms_pass_outreach_draft_send_warning_message(): string
	{
		return __('This campaign is still in Draft. Activate the campaign before sending invites, otherwise recipients cannot claim their passes.', 'backstage-outreach');
	}
}

if (!function_exists('vms_pass_outreach_activation_prompt_message')) {
	function vms_pass_outreach_activation_prompt_message(): string
	{
		return __('This campaign is in Draft. Invite links will not be claimable until the campaign is Active. Activate this campaign now?', 'backstage-outreach');
	}
}

if (!function_exists('vms_pass_outreach_default_email_subject')) {
	function vms_pass_outreach_default_email_subject(): string
	{
		return __('You\'re invited to Serenade Range', 'backstage-outreach');
	}
}

if (!function_exists('vms_pass_outreach_default_message_template')) {
	function vms_pass_outreach_default_message_template(): string
	{
		return implode("\n", array(
			'Hi {first_name},',
			'',
			'You\'ve been invited to experience Serenade Range with a guest pass.',
			'',
			'Claim your pass here:',
			'{invite_url}',
			'',
			'Guest passes are limited and subject to availability.',
			'',
			'We hope to see you soon!',
		));
	}
}

if (!function_exists('vms_pass_outreach_display_text')) {
	/**
	 * Repair legacy mojibake only for presentation. Stored campaign history is not rewritten.
	 */
	function vms_pass_outreach_display_text(string $value): string
	{
		$map = array(
			'Ã¢Â€Â¢' => '-',
			'â€¢' => '-',
			'â¢' => '-',
			'Ã¢Â€Â™' => "'",
			'â€™' => "'",
			'â' => "'",
			'Ã¢Â€Â˜' => "'",
			'â€˜' => "'",
			'â' => "'",
			'Ã¢Â€Âœ' => '"',
			'â€œ' => '"',
			'â' => '"',
			'Ã¢Â€Â' => '"',
			'â€' => '"',
			'â' => '"',
			'Ã¢Â€Â“' => '-',
			'â€“' => '-',
			'â' => '-',
			'Ã¢Â€Â”' => '-',
			'â€”' => '-',
			'â' => '-',
			'Ã¢Â€Â¦' => '...',
			'â€¦' => '...',
			'â¦' => '...',
			'Â ' => ' ',
			'Â·' => '|',
		);

		return str_replace(array_keys($map), array_values($map), wp_check_invalid_utf8($value, true));
	}
}

if (!function_exists('vms_pass_outreach_sanitize_plain_text_template')) {
	function vms_pass_outreach_sanitize_plain_text_template(string $value): string
	{
		$value = vms_pass_outreach_display_text($value);
		$value = str_replace(array("\r\n", "\r"), "\n", $value);
		$value = wp_check_invalid_utf8($value);
		$value = wp_strip_all_tags($value);
		$value = preg_replace("/[ \t]+\n/", "\n", $value);
		return trim((string) $value);
	}
}

	if (!function_exists('vms_pass_outreach_public_business_error_codes')) {
		function vms_pass_outreach_public_business_error_codes(): array
		{
			$codes = array(
				'already_claimed',
				'phone_limit',
				'email_limit',
				'batch_capacity_limit',
				'claim_insert_failed',
				'claim_outreach_update_failed',
				'reservation_insert_failed',
				'token_finalize_failed',
				'campaign_not_active',
				'campaign_expired',
				'campaign_cap_reached',
				'campaign_event_not_allowed',
				'campaign_eligibility_failed',
			'campaign_recipient_cap_reached',
		);
		if (function_exists('vms_pass_outreach_public_error_codes')) {
			$codes = array_merge($codes, vms_pass_outreach_public_error_codes());
		}
		return array_values(array_unique(array_filter(array_map('sanitize_key', $codes))));
	}
}

if (!function_exists('vms_pass_outreach_default_campaign_payload')) {
	function vms_pass_outreach_default_campaign_payload(): array
	{
		return array(
			'id' => 0,
			'campaign_name' => '',
			'campaign_purpose' => vms_outreach_default_campaign_purpose(),
			'email_subject' => vms_pass_outreach_default_email_subject(),
			'message_template' => vms_pass_outreach_default_message_template(),
			'internal_notes' => '',
			'related_source_id' => 0,
			'related_batch_id' => 0,
			'validity_type' => 'any_event',
			'single_event_plan_id' => 0,
			'start_date' => '',
			'end_date' => '',
			'season_label' => '',
			'expires_at' => '',
			'admissions_per_recipient' => 2,
			'total_admission_cap' => 0,
			'status' => 'draft',
			'eligibility_mode' => 'anyone_with_invite',
			'workflow_mode' => 'upload_first',
			'recipient_source_mode' => 'csv_new',
			'tracking_category_mode' => 'existing',
			'tracking_category_name' => '',
		);
	}
}

if (!function_exists('vms_pass_outreach_payload_value')) {
	function vms_pass_outreach_payload_value(array $payload, string $key, $default = '')
	{
		return array_key_exists($key, $payload) ? $payload[$key] : $default;
	}
}

if (!function_exists('vms_pass_outreach_normalize_campaign_row')) {
	function vms_pass_outreach_normalize_campaign_row(array $row): array
	{
		$defaults = vms_pass_outreach_default_campaign_payload();
		$row = array_merge($defaults, $row);
		$row['id'] = isset($row['id']) ? absint($row['id']) : 0;
		$row['related_source_id'] = isset($row['related_source_id']) ? absint($row['related_source_id']) : 0;
		$row['related_batch_id'] = isset($row['related_batch_id']) ? absint($row['related_batch_id']) : 0;
		$row['single_event_plan_id'] = isset($row['single_event_plan_id']) ? absint($row['single_event_plan_id']) : 0;
		$row['admissions_per_recipient'] = max(1, absint($row['admissions_per_recipient'] ?? 1));
		$row['total_admission_cap'] = max(0, absint($row['total_admission_cap'] ?? 0));
		$row['campaign_name'] = sanitize_text_field(vms_pass_outreach_display_text((string) ($row['campaign_name'] ?? '')));
		$row['campaign_purpose'] = vms_outreach_normalize_campaign_purpose((string) ($row['campaign_purpose'] ?? vms_outreach_default_campaign_purpose()));
		$row['email_subject'] = sanitize_text_field(vms_pass_outreach_display_text((string) ($row['email_subject'] ?? '')));
		$row['message_template'] = vms_pass_outreach_sanitize_plain_text_template(vms_pass_outreach_display_text((string) ($row['message_template'] ?? '')));
		$row['internal_notes'] = sanitize_textarea_field(vms_pass_outreach_display_text((string) ($row['internal_notes'] ?? '')));
		$row['validity_type'] = sanitize_key((string) ($row['validity_type'] ?? 'batch_default'));
		$row['season_label'] = sanitize_text_field((string) ($row['season_label'] ?? ''));
		$row['status'] = sanitize_key((string) ($row['status'] ?? 'draft'));
		$row['eligibility_mode'] = sanitize_key((string) ($row['eligibility_mode'] ?? 'anyone_with_invite'));
		$row['workflow_mode'] = sanitize_key((string) ($row['workflow_mode'] ?? 'upload_first'));
		$row['recipient_source_mode'] = sanitize_key((string) ($row['recipient_source_mode'] ?? ''));
		$row['tracking_category_mode'] = sanitize_key((string) ($row['tracking_category_mode'] ?? 'existing'));
		$row['tracking_category_name'] = sanitize_text_field((string) ($row['tracking_category_name'] ?? ''));
		$row['start_date'] = sanitize_text_field((string) ($row['start_date'] ?? ''));
		$row['end_date'] = sanitize_text_field((string) ($row['end_date'] ?? ''));
		$row['expires_at'] = sanitize_text_field((string) ($row['expires_at'] ?? ''));
		if (!in_array($row['workflow_mode'], vms_pass_outreach_allowed_workflow_modes(), true)) {
			$row['workflow_mode'] = 'upload_first';
		}
		if (!in_array($row['recipient_source_mode'], vms_pass_outreach_allowed_recipient_source_modes(), true)) {
			if ($row['tracking_category_mode'] === 'existing') {
				$row['recipient_source_mode'] = 'existing_source';
			} elseif ($row['tracking_category_name'] !== '') {
				$row['recipient_source_mode'] = 'csv_new';
			} else {
				$row['recipient_source_mode'] = 'csv_new';
			}
		}
		if (!in_array($row['tracking_category_mode'], vms_pass_outreach_allowed_tracking_category_modes(), true)) {
			$row['tracking_category_mode'] = $row['tracking_category_name'] !== '' ? 'new' : 'existing';
		}
		if ($row['email_subject'] === '') {
			$row['email_subject'] = vms_pass_outreach_default_email_subject();
		}
		if ($row['message_template'] === '') {
			$row['message_template'] = vms_pass_outreach_default_message_template();
		}
		return $row;
	}
}

if (!function_exists('vms_pass_outreach_campaign_form_flash_key')) {
	function vms_pass_outreach_campaign_form_flash_key(int $user_id): string
	{
		return 'vms_pass_outreach_campaign_form_flash_' . max(0, $user_id);
	}
}

if (!function_exists('vms_pass_outreach_set_campaign_form_flash')) {
	function vms_pass_outreach_set_campaign_form_flash(int $user_id, array $payload): void
	{
		if ($user_id <= 0) {
			return;
		}
		set_transient(vms_pass_outreach_campaign_form_flash_key($user_id), $payload, 10 * MINUTE_IN_SECONDS);
	}
}

if (!function_exists('vms_pass_outreach_clear_campaign_form_flash')) {
	function vms_pass_outreach_clear_campaign_form_flash(int $user_id): void
	{
		if ($user_id <= 0) {
			return;
		}
		delete_transient(vms_pass_outreach_campaign_form_flash_key($user_id));
	}
}

if (!function_exists('vms_pass_outreach_pull_campaign_form_flash')) {
	function vms_pass_outreach_pull_campaign_form_flash(int $user_id): array
	{
		if ($user_id <= 0) {
			return array();
		}

		$key = vms_pass_outreach_campaign_form_flash_key($user_id);
		$data = get_transient($key);
		delete_transient($key);
		return is_array($data) ? $data : array();
	}
}

if (!function_exists('vms_pass_outreach_upload_preview_key')) {
	function vms_pass_outreach_upload_preview_key(int $user_id): string
	{
		return 'vms_pass_outreach_upload_preview_' . max(0, $user_id);
	}
}

if (!function_exists('vms_pass_outreach_set_upload_preview')) {
	function vms_pass_outreach_set_upload_preview(int $user_id, array $payload): void
	{
		if ($user_id <= 0) {
			return;
		}
		set_transient(vms_pass_outreach_upload_preview_key($user_id), $payload, 10 * MINUTE_IN_SECONDS);
	}
}

if (!function_exists('vms_pass_outreach_get_upload_preview')) {
	function vms_pass_outreach_get_upload_preview(int $user_id): array
	{
		if ($user_id <= 0) {
			return array();
		}
		$data = get_transient(vms_pass_outreach_upload_preview_key($user_id));
		return is_array($data) ? $data : array();
	}
}

if (!function_exists('vms_pass_outreach_clear_upload_preview')) {
	function vms_pass_outreach_clear_upload_preview(int $user_id): void
	{
		if ($user_id <= 0) {
			return;
		}
		delete_transient(vms_pass_outreach_upload_preview_key($user_id));
	}
}

if (!function_exists('vms_pass_outreach_upload_mapping_key')) {
	function vms_pass_outreach_upload_mapping_key(int $user_id): string
	{
		return 'vms_pass_outreach_upload_mapping_' . max(0, $user_id);
	}
}

if (!function_exists('vms_pass_outreach_set_upload_mapping')) {
	function vms_pass_outreach_set_upload_mapping(int $user_id, array $payload): void
	{
		if ($user_id <= 0) {
			return;
		}
		set_transient(vms_pass_outreach_upload_mapping_key($user_id), $payload, 30 * MINUTE_IN_SECONDS);
	}
}

if (!function_exists('vms_pass_outreach_get_upload_mapping')) {
	function vms_pass_outreach_get_upload_mapping(int $user_id): array
	{
		if ($user_id <= 0) {
			return array();
		}
		$data = get_transient(vms_pass_outreach_upload_mapping_key($user_id));
		return is_array($data) ? $data : array();
	}
}

if (!function_exists('vms_pass_outreach_clear_upload_mapping')) {
	function vms_pass_outreach_clear_upload_mapping(int $user_id): void
	{
		if ($user_id <= 0) {
			return;
		}
		delete_transient(vms_pass_outreach_upload_mapping_key($user_id));
	}
}

if (!function_exists('vms_pass_outreach_business_batch_review_key')) {
	function vms_pass_outreach_business_batch_review_key(int $user_id): string
	{
		return 'vms_pass_outreach_business_batch_review_' . max(0, $user_id);
	}
}

if (!function_exists('vms_pass_outreach_get_business_batch_review')) {
	function vms_pass_outreach_get_business_batch_review(int $user_id): array
	{
		if ($user_id <= 0) {
			return array();
		}
		$review = get_transient(vms_pass_outreach_business_batch_review_key($user_id));
		return is_array($review) ? $review : array();
	}
}

if (!function_exists('vms_pass_outreach_set_business_batch_review')) {
	function vms_pass_outreach_set_business_batch_review(int $user_id, array $review): void
	{
		if ($user_id <= 0) {
			return;
		}
		set_transient(vms_pass_outreach_business_batch_review_key($user_id), $review, 10 * MINUTE_IN_SECONDS);
	}
}

if (!function_exists('vms_pass_outreach_clear_business_batch_review')) {
	function vms_pass_outreach_clear_business_batch_review(int $user_id): void
	{
		if ($user_id <= 0) {
			return;
		}
		delete_transient(vms_pass_outreach_business_batch_review_key($user_id));
	}
}

if (!function_exists('vms_pass_outreach_normalize_business_route_request')) {
	function vms_pass_outreach_normalize_business_route_request(array $raw): array
	{
		if (sanitize_key((string) ($raw['recipient_source_mode'] ?? '')) !== 'business_source') {
			return $raw;
		}
		if (isset($raw['business_campaign_name']) && is_scalar($raw['business_campaign_name'])) {
			$raw['campaign_name'] = sanitize_text_field((string) $raw['business_campaign_name']);
		}
		$touched = array_filter(array_map('sanitize_key', explode(',', sanitize_text_field((string) ($raw['business_batch_touched_fields'] ?? '')))));
		$compatible = array(
			'business_batch_admissions_per_link' => 'admissions_per_recipient',
			'business_batch_total_admission_cap' => 'total_admission_cap',
			'business_batch_validity_type' => 'validity_type',
			'business_batch_single_event_plan_id' => 'single_event_plan_id',
			'business_batch_start_date' => 'start_date',
			'business_batch_end_date' => 'end_date',
			'business_batch_season_label' => 'season_label',
			'business_batch_expires_at' => 'expires_at',
		);
		foreach ($compatible as $business_field => $campaign_field) {
			if (in_array($business_field, $touched, true) || !empty($raw[$business_field]) || !isset($raw[$campaign_field]) || !is_scalar($raw[$campaign_field])) {
				continue;
			}
			$raw[$business_field] = (string) $raw[$campaign_field];
		}
		return $raw;
	}
}

if (!function_exists('vms_pass_outreach_business_batch_review_error_fields')) {
	function vms_pass_outreach_business_batch_review_error_fields(WP_Error $error): array
	{
		$message = (string) $error->get_error_message();
		switch ((string) $error->get_error_code()) {
			case 'invalid_source':
			case 'missing_related_source':
			case 'invalid_related_source':
				return array('related_source_id' => $message);
			case 'missing_batch_name':
				return array('business_batch_name' => $message);
			case 'invalid_admissions_per_link':
				return array('business_batch_admissions_per_link' => $message);
			case 'missing_business_batch_capacity':
			case 'invalid_total_admission_cap':
				return array('business_batch_total_admission_cap' => $message);
			case 'invalid_business_admission_cap':
				return array('business_batch_per_business_admission_cap' => $message);
			case 'invalid_validity_type':
				return array('business_batch_validity_type' => $message);
			case 'invalid_single_event':
				return array('business_batch_single_event_plan_id' => $message);
			case 'invalid_date_range':
			case 'invalid_date_range_order':
			case 'invalid_season_dates':
				return array(
					'business_batch_start_date' => $message,
					'business_batch_end_date' => $message,
				);
			case 'invalid_season':
				return array('business_batch_season_label' => $message);
			case 'invalid_value_type':
			case 'unsupported_business_batch':
				return array('business_batch_offer_type' => $message);
			case 'invalid_percent_value':
			case 'invalid_fixed_value':
				return array('business_batch_offer_amount' => $message);
			default:
				return array();
		}
	}
}

if (!function_exists('vms_pass_outreach_business_batch_form_payload')) {
	function vms_pass_outreach_business_batch_form_payload(array $raw): array
	{
		$fields = array(
			'business_batch_name',
			'business_batch_offer_type',
			'business_batch_offer_amount',
			'business_batch_admissions_per_link',
			'business_batch_total_admission_cap',
			'business_batch_per_business_admission_cap',
			'business_batch_validity_type',
			'business_batch_single_event_plan_id',
			'business_batch_start_date',
			'business_batch_end_date',
			'business_batch_season_label',
			'business_batch_expires_at',
			'business_batch_notes',
		);
		$payload = array();
		foreach ($fields as $field) {
			$value = isset($raw[$field]) && is_scalar($raw[$field]) ? (string) $raw[$field] : '';
			$payload[$field] = $field === 'business_batch_notes'
				? sanitize_textarea_field($value)
				: sanitize_text_field($value);
		}
		return $payload;
	}
}

if (!function_exists('vms_pass_outreach_prepare_business_batch_review')) {
	function vms_pass_outreach_prepare_business_batch_review(array $raw, int $user_id)
	{
		$raw = vms_pass_outreach_normalize_business_route_request($raw);
		$source_id = absint($raw['related_source_id'] ?? 0);
		$source = function_exists('vms_pass_claims_get_source_by_id') ? vms_pass_claims_get_source_by_id($source_id) : null;
		if ($source_id <= 0 || !is_array($source)) {
			return new WP_Error('invalid_source', __('Select a valid business Source before reviewing a new offer batch.', 'backstage-outreach'));
		}

		$offer_type = sanitize_key((string) ($raw['business_batch_offer_type'] ?? ''));
		if (!in_array($offer_type, array('free', 'percent', 'fixed'), true)) {
			return new WP_Error('invalid_value_type', __('Choose Complimentary admission, Percentage off, or Fixed dollar amount off.', 'backstage-outreach'));
		}
		$offer_amount = isset($raw['business_batch_offer_amount']) && is_scalar($raw['business_batch_offer_amount'])
			? round((float) $raw['business_batch_offer_amount'], 2)
			: 0.0;
		if (in_array($offer_type, array('percent', 'fixed'), true) && !is_finite($offer_amount)) {
			return new WP_Error($offer_type === 'percent' ? 'invalid_percent_value' : 'invalid_fixed_value', __('Enter a finite numeric offer value.', 'backstage-outreach'));
		}
		if ($offer_type === 'fixed' && $offer_amount > 99999999.99) {
			return new WP_Error('invalid_fixed_value', __('Fixed Amount Off cannot exceed $99,999,999.99.', 'backstage-outreach'));
		}
		if (absint($raw['business_batch_total_admission_cap'] ?? 0) <= 0) {
			return new WP_Error('missing_business_batch_capacity', __('Enter the total admissions available across all businesses.', 'backstage-outreach'));
		}
		$per_business_admission_cap = absint($raw['business_batch_per_business_admission_cap'] ?? 0);
		if ($per_business_admission_cap > 50000) {
			return new WP_Error('invalid_business_admission_cap', __('Total admissions allowed per business cannot exceed 50,000.', 'backstage-outreach'));
		}

		$batch_raw = array(
			'source_id' => $source_id,
			'batch_name' => sanitize_text_field((string) ($raw['business_batch_name'] ?? '')),
			'quantity' => 0,
			'admissions_per_link' => absint($raw['business_batch_admissions_per_link'] ?? 0),
			'total_admission_cap' => absint($raw['business_batch_total_admission_cap'] ?? 0),
			'validity_type' => sanitize_key((string) ($raw['business_batch_validity_type'] ?? '')),
			'single_event_plan_id' => absint($raw['business_batch_single_event_plan_id'] ?? 0),
			'start_date' => sanitize_text_field((string) ($raw['business_batch_start_date'] ?? '')),
			'end_date' => sanitize_text_field((string) ($raw['business_batch_end_date'] ?? '')),
			'season_label' => sanitize_text_field((string) ($raw['business_batch_season_label'] ?? '')),
			'venue_ids' => array(),
			'value_type' => $offer_type,
			'value_amount' => $offer_type === 'free' ? 100.0 : $offer_amount,
			'expires_at' => sanitize_text_field((string) ($raw['business_batch_expires_at'] ?? '')),
			'status' => 'active',
			'checkin_open_mode' => 'same_day',
			'max_per_phone' => 0,
			'max_per_email' => 0,
			'notes' => sanitize_textarea_field((string) ($raw['business_batch_notes'] ?? '')),
		);
		$payload = function_exists('bvmgr_pass_claims_sanitize_batch_definition_payload')
			? bvmgr_pass_claims_sanitize_batch_definition_payload($batch_raw)
			: new WP_Error('business_batch_service_unavailable', __('Guest Pass batch-definition validation is unavailable.', 'backstage-outreach'));
		if (is_wp_error($payload)) {
			return $payload;
		}
		$payload['id'] = 1;
		$eligibility_error = vms_pass_outreach_business_batch_eligibility_error($payload, $source_id);
		unset($payload['id']);
		if (is_wp_error($eligibility_error)) {
			return $eligibility_error;
		}

		try {
			$review_token = bin2hex(random_bytes(24));
		} catch (Throwable $error) {
			$review_token = wp_generate_password(48, false, false);
		}
		$form_payload = vms_pass_outreach_soft_campaign_payload_for_form($raw, 0);
		$form_payload['recipient_source_mode'] = 'business_source';
		$form_payload['tracking_category_mode'] = 'existing';
		$form_payload['related_source_id'] = $source_id;
		$form_payload['related_batch_id'] = 0;
		$form_payload['admissions_per_recipient'] = absint($payload['admissions_per_link'] ?? 1);
		$form_payload['total_admission_cap'] = absint($payload['total_admission_cap'] ?? 0);
		$form_payload['validity_type'] = sanitize_key((string) ($payload['validity_type'] ?? 'any_event'));
		$form_payload['single_event_plan_id'] = absint($payload['single_event_plan_id'] ?? 0);
		$form_payload['start_date'] = sanitize_text_field((string) ($payload['start_date'] ?? ''));
		$form_payload['end_date'] = sanitize_text_field((string) ($payload['end_date'] ?? ''));
		$form_payload['season_label'] = sanitize_text_field((string) ($payload['season_label'] ?? ''));
		$form_payload['expires_at'] = sanitize_text_field((string) ($payload['expires_at'] ?? ''));
		$form_payload['business_admission_cap'] = $per_business_admission_cap;

		return array(
			'review_token' => $review_token,
			'payload_digest' => hash('sha256', (string) wp_json_encode($payload)),
			'batch_payload' => $payload,
			'per_business_admission_cap' => $per_business_admission_cap,
			'form_payload' => $form_payload,
			'source_name' => sanitize_text_field((string) ($source['source_name'] ?? '')),
			'created_at' => function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql'),
		);
	}
}

if (!function_exists('vms_pass_outreach_create_business_offer_batch')) {
	/**
	 * Create only the shared batch definition. Individual claim links remain at zero.
	 */
	function vms_pass_outreach_create_business_offer_batch(array $payload, int $user_id)
	{
		$validated = function_exists('bvmgr_pass_claims_sanitize_batch_definition_payload')
			? bvmgr_pass_claims_sanitize_batch_definition_payload($payload)
			: new WP_Error('business_batch_service_unavailable', __('Guest Pass batch-definition validation is unavailable.', 'backstage-outreach'));
		if (is_wp_error($validated)) {
			return $validated;
		}
		$eligibility_probe = array_merge($validated, array('id' => 1));
		$eligibility_error = vms_pass_outreach_business_batch_eligibility_error($eligibility_probe, absint($validated['source_id'] ?? 0));
		if (is_wp_error($eligibility_error)) {
			return $eligibility_error;
		}

		global $wpdb;
		$now = function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql');
		$insert = $wpdb->insert(
			vms_admission_table_pass_batches(),
			array(
				'source_id' => (int) $validated['source_id'],
				'batch_name' => (string) $validated['batch_name'],
				'quantity' => (int) $validated['quantity'],
				'admissions_per_link' => (int) $validated['admissions_per_link'],
				'total_admission_cap' => (int) $validated['total_admission_cap'],
				'validity_type' => (string) $validated['validity_type'],
				'single_event_plan_id' => (int) $validated['single_event_plan_id'],
				'start_date' => (string) $validated['start_date'],
				'end_date' => (string) $validated['end_date'],
				'season_label' => (string) $validated['season_label'],
				'venue_ids_json' => (string) $validated['venue_ids_json'],
				'value_type' => (string) $validated['value_type'],
				'value_amount' => (float) $validated['value_amount'],
				'applies_to' => 'entry_only',
				'expires_at' => (string) $validated['expires_at'],
				'status' => 'active',
				'checkin_open_mode' => (string) $validated['checkin_open_mode'],
				'max_per_phone' => (int) $validated['max_per_phone'],
				'max_per_email' => (int) $validated['max_per_email'],
				'notes' => (string) $validated['notes'],
				'generated_count' => 0,
				'created_by' => $user_id,
				'created_at' => $now,
			),
			array('%d', '%s', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%s')
		);
		if ($insert === false || absint($wpdb->insert_id ?? 0) <= 0) {
			return new WP_Error('business_batch_create_failed', __('Could not create the reviewed business offer batch.', 'backstage-outreach'));
		}

		$batch_id = absint($wpdb->insert_id);
		if (function_exists('bvmgr_admission_audit_log')) {
			bvmgr_admission_audit_log(0, null, 'pass_outreach_business_batch_create', $user_id, 'admin', array(
				'batch_id' => $batch_id,
				'source_id' => (int) $validated['source_id'],
				'quantity' => (int) $validated['quantity'],
				'admissions_per_link' => (int) $validated['admissions_per_link'],
				'total_admission_cap' => (int) $validated['total_admission_cap'],
				'value_type' => (string) $validated['value_type'],
				'value_amount' => (float) $validated['value_amount'],
				'generated_count' => 0,
			));
		}

		return function_exists('vms_pass_claims_get_batch_by_id')
			? vms_pass_claims_get_batch_by_id($batch_id)
			: array_merge($validated, array('id' => $batch_id, 'generated_count' => 0));
	}
}

if (!function_exists('vms_pass_outreach_create_preview_anchor')) {
	function vms_pass_outreach_create_preview_anchor(): string
	{
		return 'vms-outreach-recipient-source';
	}
}

if (!function_exists('vms_pass_outreach_create_preview_fragment')) {
	function vms_pass_outreach_create_preview_fragment(string $mode = ''): string
	{
		switch (sanitize_key($mode)) {
			case 'csv_new':
				return 'vms-outreach-recipient-preview-csv';
			case 'existing_source':
				return 'vms-outreach-recipient-preview-existing';
			case 'business_source':
				return 'vms-outreach-business-source-preview';
			case 'contacts':
				return 'vms-outreach-recipient-preview-contacts';
			default:
				return vms_pass_outreach_create_preview_anchor();
		}
	}
}

if (!function_exists('vms_pass_outreach_create_preview_url')) {
	function vms_pass_outreach_create_preview_url(string $mode = ''): string
	{
		$base_url = function_exists('vms_pass_claims_admin_page_url')
			? vms_pass_claims_admin_page_url(array(
				'tab' => vms_pass_outreach_tab_slug(),
			))
			: admin_url('admin.php?page=vms-passes');

		return $base_url . '#' . vms_pass_outreach_create_preview_fragment($mode);
	}
}

if (!function_exists('vms_pass_outreach_business_setup_anchor')) {
	function vms_pass_outreach_business_setup_anchor(): string
	{
		return 'vms-outreach-business-source-setup';
	}
}

if (!function_exists('vms_pass_outreach_business_setup_url')) {
	function vms_pass_outreach_business_setup_url(): string
	{
		$base_url = function_exists('vms_pass_claims_admin_page_url')
			? vms_pass_claims_admin_page_url(array(
				'tab' => vms_pass_outreach_tab_slug(),
			))
			: admin_url('admin.php?page=vms-passes');

		return $base_url . '#' . vms_pass_outreach_business_setup_anchor();
	}
}

if (!function_exists('vms_pass_outreach_business_review_action_url')) {
	function vms_pass_outreach_business_review_action_url(): string
	{
		return vms_pass_outreach_admin_page_url() . '#vms-outreach-business-review-action';
	}
}

if (!function_exists('vms_pass_outreach_create_review_anchor')) {
	function vms_pass_outreach_create_review_anchor(): string
	{
		return 'vms-outreach-review-create';
	}
}

if (!function_exists('vms_pass_outreach_create_review_url')) {
	function vms_pass_outreach_create_review_url(): string
	{
		$base_url = function_exists('vms_pass_claims_admin_page_url')
			? vms_pass_claims_admin_page_url(array(
				'tab' => vms_pass_outreach_tab_slug(),
			))
			: admin_url('admin.php?page=vms-passes');

		return $base_url . '#' . vms_pass_outreach_create_review_anchor();
	}
}

if (!function_exists('vms_pass_outreach_normalize_recipient_source_mode')) {
	function vms_pass_outreach_normalize_recipient_source_mode(array $raw, bool $has_sources = true): string
	{
		$mode = sanitize_key((string) ($raw['recipient_source_mode'] ?? ''));
		if (in_array($mode, vms_pass_outreach_allowed_recipient_source_modes(), true)) {
			if (in_array($mode, array('existing_source', 'business_source'), true) && !$has_sources) {
				return 'csv_new';
			}
			return $mode;
		}

		$tracking_mode = sanitize_key((string) ($raw['tracking_category_mode'] ?? ''));
		if ($tracking_mode === 'existing' && $has_sources) {
			return 'existing_source';
		}

		return 'csv_new';
	}
}

if (!function_exists('vms_pass_outreach_create_preview_setup_snapshot')) {
	function vms_pass_outreach_create_preview_setup_snapshot(array $setup): array
	{
		return array(
			'recipient_source_mode' => sanitize_key((string) ($setup['recipient_source_mode'] ?? 'csv_new')),
			'related_source_id' => absint($setup['related_source_id'] ?? 0),
			'related_batch_id' => absint($setup['related_batch_id'] ?? 0),
			'tracking_category_mode' => sanitize_key((string) ($setup['tracking_category_mode'] ?? 'existing')),
			'tracking_category_name' => sanitize_text_field((string) ($setup['tracking_category_name'] ?? '')),
			'admissions_per_recipient' => max(1, absint($setup['admissions_per_recipient'] ?? 1)),
			'validity_type' => sanitize_key((string) ($setup['validity_type'] ?? 'any_event')),
			'single_event_plan_id' => absint($setup['single_event_plan_id'] ?? 0),
			'start_date' => sanitize_text_field((string) ($setup['start_date'] ?? '')),
			'end_date' => sanitize_text_field((string) ($setup['end_date'] ?? '')),
			'season_label' => sanitize_text_field((string) ($setup['season_label'] ?? '')),
			'business_admission_cap' => absint($setup['business_admission_cap'] ?? 0),
		);
	}
}

if (!function_exists('vms_pass_outreach_business_distribution_handoff_key')) {
	function vms_pass_outreach_business_distribution_handoff_key(int $campaign_id): string
	{
		return 'vms_pass_outreach_business_distribution_handoff_' . get_current_user_id() . '_' . max(0, $campaign_id);
	}
}

if (!function_exists('vms_pass_outreach_create_message_preview_snapshot')) {
	function vms_pass_outreach_create_message_preview_snapshot(array $setup): array
	{
		return array(
			'email_subject' => sanitize_text_field((string) ($setup['email_subject'] ?? '')),
			'message_template' => vms_pass_outreach_sanitize_plain_text_template((string) ($setup['message_template'] ?? '')),
		);
	}
}

if (!function_exists('vms_pass_outreach_build_create_batch_preview')) {
	function vms_pass_outreach_build_create_batch_preview(array $campaign_setup, int $recipient_count): array
	{
		$recipient_count = max(0, $recipient_count);
		$admissions_per_recipient = max(1, absint($campaign_setup['admissions_per_recipient'] ?? 1));

		return array(
			'batch_name' => sanitize_text_field((string) ($campaign_setup['campaign_name'] ?? '')),
			'quantity' => $recipient_count,
			'admissions_per_link' => $admissions_per_recipient,
			'total_admission_cap' => $recipient_count * $admissions_per_recipient,
		);
	}
}

if (!function_exists('vms_pass_outreach_preview_sample_rows_from_prepared_rows')) {
	function vms_pass_outreach_preview_sample_rows_from_prepared_rows(array $prepared_rows, int $limit = 5): array
	{
		$rows = array();
		foreach (array_slice($prepared_rows, 0, max(1, $limit)) as $row) {
			$rows[] = array(
				'full_name' => function_exists('vms_pass_outreach_recipient_full_name')
					? vms_pass_outreach_recipient_full_name($row)
					: sanitize_text_field(trim((string) (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')))),
				'email' => sanitize_email((string) ($row['email'] ?? '')),
				'phone' => sanitize_text_field((string) ($row['phone'] ?? '')),
				'company' => sanitize_text_field((string) ($row['company'] ?? '')),
				'group_label' => sanitize_text_field((string) ($row['group_label'] ?? '')),
			);
		}

		return $rows;
	}
}

if (!function_exists('vms_pass_outreach_build_existing_source_list_preview')) {
	function vms_pass_outreach_build_existing_source_list_preview(int $source_id)
	{
		$source_id = absint($source_id);
		if ($source_id <= 0) {
			return new WP_Error('missing_related_source', __('Select an existing source list to preview.', 'backstage-outreach'));
		}
		if (!function_exists('vms_pass_claims_get_source_by_id')) {
			return new WP_Error('invalid_related_source', __('Source lists are unavailable.', 'backstage-outreach'));
		}

		$source = vms_pass_claims_get_source_by_id($source_id);
		if (!is_array($source)) {
			return new WP_Error('invalid_related_source', __('Select a valid existing source list.', 'backstage-outreach'));
		}

		global $wpdb;
		$campaigns_table = vms_admission_table_pass_outreach_campaigns();
		$recipients_table = vms_pass_outreach_recipient_table();
		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT r.*, c.campaign_name
			FROM {$recipients_table} r
			INNER JOIN {$campaigns_table} c ON c.id = r.campaign_id
			WHERE c.related_source_id = %d
				AND c.campaign_purpose = %s
			ORDER BY r.created_at DESC, r.id DESC
			LIMIT 5000",
			$source_id,
			'guest_pass_invitation'
		), ARRAY_A);
		if (!is_array($rows)) {
			$rows = array();
		}

		$seen_email_norms = array();
		$prepared_rows = array();
		$counts = array(
			'total_rows' => count($rows),
			'ready_count' => 0,
			'missing_email_count' => 0,
			'duplicate_count' => 0,
			'suppressed_count' => 0,
			'do_not_contact_count' => 0,
			'excluded_count' => 0,
			'skipped_count' => 0,
		);

		foreach ($rows as $row) {
			$row = function_exists('vms_pass_outreach_normalize_recipient_row')
				? vms_pass_outreach_normalize_recipient_row($row)
				: $row;
			$email = sanitize_email((string) ($row['email'] ?? ''));
			$email_norm = sanitize_text_field((string) ($row['email_norm'] ?? ''));
			if ($email_norm === '' || !is_email($email)) {
				$counts['missing_email_count'] += 1;
				$counts['skipped_count'] += 1;
				continue;
			}
			if (isset($seen_email_norms[$email_norm])) {
				$counts['duplicate_count'] += 1;
				$counts['skipped_count'] += 1;
				continue;
			}

			$guardrail = function_exists('vms_pass_outreach_recipient_contact_guardrail_state')
				? vms_pass_outreach_recipient_contact_guardrail_state($row)
				: array('blocked' => false, 'reason_code' => '');
			if (!empty($guardrail['blocked'])) {
				$reason_code = sanitize_key((string) ($guardrail['reason_code'] ?? ''));
				if ($reason_code === 'do_not_contact') {
					$counts['do_not_contact_count'] += 1;
				} elseif ($reason_code === 'suppressed') {
					$counts['suppressed_count'] += 1;
				} else {
					$counts['excluded_count'] += 1;
				}
				$counts['skipped_count'] += 1;
				$seen_email_norms[$email_norm] = true;
				continue;
			}

			$seen_email_norms[$email_norm] = true;
			$prepared_rows[] = array(
				'contact_id' => absint($row['contact_id'] ?? 0),
				'first_name' => sanitize_text_field((string) ($row['first_name'] ?? '')),
				'last_name' => sanitize_text_field((string) ($row['last_name'] ?? '')),
				'full_name' => sanitize_text_field((string) ($row['full_name'] ?? '')),
				'email' => $email,
				'phone' => sanitize_text_field((string) ($row['phone'] ?? '')),
				'company' => sanitize_text_field((string) ($row['company'] ?? '')),
				'group_label' => sanitize_text_field((string) ($row['group_label'] ?? '')),
				'notes' => sanitize_textarea_field((string) ($row['notes'] ?? '')),
			);
		}

		$counts['ready_count'] = count($prepared_rows);

		return array_merge($counts, array(
			'source_id' => $source_id,
			'source_name' => sanitize_text_field((string) ($source['source_name'] ?? '')),
			'prepared_rows' => $prepared_rows,
			'sample_rows' => vms_pass_outreach_preview_sample_rows_from_prepared_rows($prepared_rows),
		));
	}
}

if (!function_exists('vms_pass_outreach_business_batch_eligibility_error')) {
	/**
	 * Apply the canonical reusable-business batch eligibility rules.
	 */
	function vms_pass_outreach_business_batch_eligibility_error(array $batch, int $source_id = 0)
	{
		$batch_id = absint($batch['id'] ?? 0);
		if ($batch_id <= 0) {
			return new WP_Error('invalid_related_batch', __('Select a valid Guest Pass batch.', 'backstage-outreach'));
		}
		if ($source_id > 0 && absint($batch['source_id'] ?? 0) !== $source_id) {
			return new WP_Error('batch_source_mismatch', __('The selected batch does not belong to the selected business Source.', 'backstage-outreach'));
		}
		if (sanitize_key((string) ($batch['status'] ?? '')) !== 'active') {
			return new WP_Error('inactive_related_batch', __('Select an active Guest Pass batch for reusable business links.', 'backstage-outreach'));
		}

		$value_type = sanitize_key((string) ($batch['value_type'] ?? ''));
		$value_amount = (float) ($batch['value_amount'] ?? 0);
		$is_supported_discount = ($value_type === 'percent' && $value_amount > 0 && $value_amount <= 100)
			|| ($value_type === 'fixed' && is_finite($value_amount) && $value_amount > 0 && $value_amount <= 99999999.99);
		if ($value_type !== 'free' && !$is_supported_discount) {
			return new WP_Error('unsupported_business_batch', __('Reusable business links require a Free, Percentage Off, or Fixed Amount Off batch with a valid value.', 'backstage-outreach'));
		}

		return null;
	}
}

if (!function_exists('vms_pass_outreach_business_batch_offer_label')) {
	function vms_pass_outreach_business_batch_offer_label(array $batch): string
	{
		$people = max(1, absint($batch['admissions_per_link'] ?? 1));
		$value_type = sanitize_key((string) ($batch['value_type'] ?? ''));
		$value_amount = round((float) ($batch['value_amount'] ?? 0), 2);
		$display_amount = rtrim(rtrim(number_format($value_amount, 2, '.', ''), '0'), '.');
		if ($value_type === 'percent') {
			return sprintf(
				/* translators: 1: percentage amount, 2: maximum admissions per customer */
				_n('Admission Offer — %1$s%% off admission for up to %2$d person', 'Admission Offer — %1$s%% off admission for up to %2$d people', $people, 'backstage-outreach'),
				$display_amount,
				$people
			);
		}
		if ($value_type === 'fixed') {
			return sprintf(
				/* translators: 1: currency amount, 2: maximum admissions per customer */
				_n('Admission Offer — $%1$s off each admission for up to %2$d person', 'Admission Offer — $%1$s off each admission for up to %2$d people', $people, 'backstage-outreach'),
				$display_amount,
				$people
			);
		}

		return sprintf(
			/* translators: %d: maximum admissions per redeemed complimentary link */
			_n('Complimentary admission for up to %d person', 'Complimentary admission for up to %d people', $people, 'backstage-outreach'),
			$people
		);
	}
}

if (!function_exists('vms_pass_outreach_business_batch_scope_label')) {
	function vms_pass_outreach_business_batch_scope_label(array $batch): string
	{
		$validity_type = sanitize_key((string) ($batch['validity_type'] ?? ''));
		if ($validity_type === 'single_event') {
			$event = function_exists('bvmgr_pass_claims_get_event_plan_brief')
				? bvmgr_pass_claims_get_event_plan_brief(absint($batch['single_event_plan_id'] ?? 0))
				: null;
			return sprintf(__('One Event — %s', 'backstage-outreach'), sanitize_text_field((string) ($event['title'] ?? __('Selected event', 'backstage-outreach'))));
		}
		if ($validity_type === 'date_range') {
			return sprintf(
				__('Date Range — %1$s through %2$s', 'backstage-outreach'),
				sanitize_text_field((string) ($batch['start_date'] ?? '')),
				sanitize_text_field((string) ($batch['end_date'] ?? ''))
			);
		}
		if ($validity_type === 'season') {
			$start_date = sanitize_text_field((string) ($batch['start_date'] ?? ''));
			$end_date = sanitize_text_field((string) ($batch['end_date'] ?? ''));
			$date_label = ($start_date !== '' && $end_date !== '') ? $start_date . ' through ' . $end_date : '';
			$parts = array_filter(array(
				sanitize_text_field((string) ($batch['season_label'] ?? '')),
				$date_label,
			));
			return sprintf(__('Season — %s', 'backstage-outreach'), implode(' · ', $parts));
		}
		return __('Any Event', 'backstage-outreach');
	}
}

if (!function_exists('vms_pass_outreach_business_batch_catalog_entry')) {
	function vms_pass_outreach_business_batch_catalog_entry(array $batch): array
	{
		$batch_id = absint($batch['id'] ?? 0);
		$batch_name = sanitize_text_field((string) ($batch['batch_name'] ?? ''));
		if ($batch_name === '') {
			$batch_name = sprintf(__('Batch #%d', 'backstage-outreach'), $batch_id);
		}
		$quantity = absint($batch['quantity'] ?? 0);
		$admissions_per_link = max(1, absint($batch['admissions_per_link'] ?? 1));
		$total_admission_cap = absint($batch['total_admission_cap'] ?? 0);
		$offer_label = vms_pass_outreach_business_batch_offer_label($batch);

		return array(
			'id' => $batch_id,
			'source_id' => absint($batch['source_id'] ?? 0),
			'name' => $batch_name,
			'option_label' => sprintf(
				/* translators: 1: batch name, 2: offer summary */
				__('%1$s — %2$s', 'backstage-outreach'),
				$batch_name,
				$offer_label
			),
			'offer_label' => $offer_label,
			'scope_label' => vms_pass_outreach_business_batch_scope_label($batch),
			'quantity' => $quantity,
			'admissions_per_link' => $admissions_per_link,
			'total_admission_cap' => $total_admission_cap,
			'validity_type' => sanitize_key((string) ($batch['validity_type'] ?? '')),
			'single_event_plan_id' => absint($batch['single_event_plan_id'] ?? 0),
			'start_date' => sanitize_text_field((string) ($batch['start_date'] ?? '')),
			'end_date' => sanitize_text_field((string) ($batch['end_date'] ?? '')),
			'season_label' => sanitize_text_field((string) ($batch['season_label'] ?? '')),
			'expires_at' => sanitize_text_field((string) ($batch['expires_at'] ?? '')),
			'value_type' => sanitize_key((string) ($batch['value_type'] ?? '')),
			'value_amount' => (float) ($batch['value_amount'] ?? 0),
		);
	}
}

if (!function_exists('vms_pass_outreach_business_batch_catalog')) {
	function vms_pass_outreach_business_batch_catalog(array $batches): array
	{
		$catalog = array();
		foreach ($batches as $batch) {
			if (!is_array($batch) || is_wp_error(vms_pass_outreach_business_batch_eligibility_error($batch))) {
				continue;
			}
			$entry = vms_pass_outreach_business_batch_catalog_entry($batch);
			if ((int) $entry['id'] > 0 && (int) $entry['source_id'] > 0) {
				$catalog[(int) $entry['id']] = $entry;
			}
		}
		return $catalog;
	}
}

if (!function_exists('vms_pass_outreach_eligible_business_batches')) {
	function vms_pass_outreach_eligible_business_batches(array $batches, int $source_id): array
	{
		$source_id = absint($source_id);
		if ($source_id <= 0) {
			return array();
		}
		return array_values(array_filter($batches, static function ($batch) use ($source_id): bool {
			return is_array($batch) && !is_wp_error(vms_pass_outreach_business_batch_eligibility_error($batch, $source_id));
		}));
	}
}

if (!function_exists('vms_pass_outreach_build_business_source_preview')) {
	/**
	 * Build a review of current active reusable-business memberships.
	 *
	 * This deliberately does not read or create campaign recipient records. A
	 * business can receive a reusable signed link whether or not it has email.
	 */
	function vms_pass_outreach_build_business_source_preview(int $source_id, int $batch_id)
	{
		$source_id = absint($source_id);
		$batch_id = absint($batch_id);
		if ($source_id <= 0) {
			return new WP_Error('missing_related_source', __('Select a business Source to preview.', 'backstage-outreach'));
		}
		if ($batch_id <= 0) {
			return new WP_Error('missing_related_batch', __('Select an existing Guest Pass batch for the reusable business links.', 'backstage-outreach'));
		}
		if (!function_exists('vms_pass_claims_get_source_by_id') || !function_exists('vms_pass_claims_get_batch_by_id') || !function_exists('backstage_outreach_source_businesses')) {
			return new WP_Error('business_source_unavailable', __('Reusable business Sources are unavailable.', 'backstage-outreach'));
		}

		$source = vms_pass_claims_get_source_by_id($source_id);
		$batch = vms_pass_claims_get_batch_by_id($batch_id);
		if (!is_array($source)) {
			return new WP_Error('invalid_related_source', __('Select a valid business Source.', 'backstage-outreach'));
		}
		if (!is_array($batch)) {
			return new WP_Error('invalid_related_batch', __('Select a valid Guest Pass batch.', 'backstage-outreach'));
		}
		$eligibility_error = vms_pass_outreach_business_batch_eligibility_error($batch, $source_id);
		if (is_wp_error($eligibility_error)) {
			return $eligibility_error;
		}
		$batch_value_type = sanitize_key((string) ($batch['value_type'] ?? ''));
		$is_paid_discount_batch = in_array($batch_value_type, array('percent', 'fixed'), true);

		$businesses = backstage_outreach_source_businesses($source_id, false);
		$rows = array();
		$email_count = 0;
		$digest_rows = array();
		foreach ($businesses as $business) {
			$business_id = absint($business['id'] ?? 0);
			if ($business_id <= 0) {
				continue;
			}
			$email = sanitize_email((string) ($business['email'] ?? ''));
			$has_email = $email !== '' && is_email($email);
			if ($has_email) {
				$email_count++;
			}
			$address_parts = array_filter(array(
				sanitize_text_field((string) ($business['address_line'] ?? '')),
				sanitize_text_field((string) ($business['city'] ?? '')),
				sanitize_text_field((string) ($business['state'] ?? '')),
				sanitize_text_field((string) ($business['postal_code'] ?? '')),
			), static fn(string $part): bool => $part !== '');
			$row = array(
				'business_id' => $business_id,
				'membership_id' => absint($business['membership_id'] ?? 0),
				'business_name' => sanitize_text_field((string) ($business['business_name'] ?? '')),
				'contact_name' => sanitize_text_field((string) ($business['contact_name'] ?? '')),
				'email' => $has_email ? $email : '',
				'has_email' => $has_email,
				'phone' => sanitize_text_field((string) ($business['phone'] ?? '')),
				'website' => esc_url_raw((string) ($business['website'] ?? '')),
				'address' => implode(', ', $address_parts),
				'notes' => sanitize_textarea_field((string) ($business['notes'] ?? '')),
				'link_eligible' => true,
			);
			$rows[] = $row;
			$digest_rows[] = array(
				'business_id' => $business_id,
				'membership_id' => (int) $row['membership_id'],
				'business_name' => (string) $row['business_name'],
				'contact_name' => (string) $row['contact_name'],
				'email' => (string) $row['email'],
				'phone' => (string) $row['phone'],
				'website' => (string) $row['website'],
				'address' => (string) $row['address'],
				'notes' => (string) $row['notes'],
			);
		}

		$batch_digest = array(
			'id' => $batch_id,
			'source_id' => $source_id,
			'status' => (string) ($batch['status'] ?? ''),
			'quantity' => absint($batch['quantity'] ?? 0),
			'admissions_per_link' => max(1, absint($batch['admissions_per_link'] ?? 1)),
			'total_admission_cap' => absint($batch['total_admission_cap'] ?? 0),
			'value_type' => $batch_value_type,
			'value_amount' => (string) ($batch['value_amount'] ?? ''),
			'validity_type' => sanitize_key((string) ($batch['validity_type'] ?? '')),
			'single_event_plan_id' => absint($batch['single_event_plan_id'] ?? 0),
			'start_date' => sanitize_text_field((string) ($batch['start_date'] ?? '')),
			'end_date' => sanitize_text_field((string) ($batch['end_date'] ?? '')),
			'season_label' => sanitize_text_field((string) ($batch['season_label'] ?? '')),
		);

		return array(
			'source_id' => $source_id,
			'source_name' => sanitize_text_field((string) ($source['source_name'] ?? '')),
			'batch_id' => $batch_id,
			'batch_name' => sanitize_text_field((string) ($batch['batch_name'] ?? '')),
			'batch_offer_label' => vms_pass_outreach_business_batch_offer_label($batch),
			'batch_scope_label' => vms_pass_outreach_business_batch_scope_label($batch),
			'batch_offer_type' => $is_paid_discount_batch ? 'paid_discount' : 'complimentary',
			'active_membership_count' => count($rows),
			'link_eligible_count' => count($rows),
			'email_count' => $email_count,
			'missing_email_count' => count($rows) - $email_count,
			'individual_claim_link_quantity' => absint($batch['quantity'] ?? 0),
			'batch_admissions_per_link' => max(1, absint($batch['admissions_per_link'] ?? 1)),
			'batch_total_admission_cap' => absint($batch['total_admission_cap'] ?? 0),
			'rows' => $rows,
			'membership_digest' => hash('sha256', (string) wp_json_encode(array(
				'batch' => $batch_digest,
				'businesses' => $digest_rows,
			))),
		);
	}
}

if (!function_exists('vms_pass_outreach_soft_campaign_payload_for_form')) {
	function vms_pass_outreach_soft_campaign_payload_for_form(array $raw, int $campaign_id = 0): array
	{
		$defaults = vms_pass_outreach_default_campaign_payload();
		$payload = vms_pass_outreach_normalize_campaign_row(array(
			'id' => $campaign_id,
			'campaign_name' => sanitize_text_field((string) ($raw['campaign_name'] ?? '')),
			'email_subject' => sanitize_text_field((string) ($raw['email_subject'] ?? '')),
			'message_template' => vms_pass_outreach_sanitize_plain_text_template((string) ($raw['message_template'] ?? '')),
			'internal_notes' => sanitize_textarea_field((string) ($raw['internal_notes'] ?? '')),
			'related_source_id' => absint($raw['related_source_id'] ?? 0),
			'related_batch_id' => absint($raw['related_batch_id'] ?? 0),
			'validity_type' => sanitize_key((string) ($raw['validity_type'] ?? ($defaults['validity_type'] ?? 'any_event'))),
			'single_event_plan_id' => absint($raw['single_event_plan_id'] ?? 0),
			'start_date' => sanitize_text_field((string) ($raw['start_date'] ?? '')),
			'end_date' => sanitize_text_field((string) ($raw['end_date'] ?? '')),
			'season_label' => sanitize_text_field((string) ($raw['season_label'] ?? '')),
			'expires_at' => sanitize_text_field((string) ($raw['expires_at'] ?? '')),
			'admissions_per_recipient' => max(1, absint($raw['admissions_per_recipient'] ?? ($defaults['admissions_per_recipient'] ?? 2))),
			'total_admission_cap' => max(0, absint($raw['total_admission_cap'] ?? 0)),
			'status' => sanitize_key((string) ($raw['status'] ?? 'draft')),
			'eligibility_mode' => sanitize_key((string) ($raw['eligibility_mode'] ?? 'anyone_with_invite')),
			'workflow_mode' => sanitize_key((string) ($raw['workflow_mode'] ?? ($defaults['workflow_mode'] ?? 'upload_first'))),
			'recipient_source_mode' => sanitize_key((string) ($raw['recipient_source_mode'] ?? ($defaults['recipient_source_mode'] ?? 'csv_new'))),
			'tracking_category_mode' => sanitize_key((string) ($raw['tracking_category_mode'] ?? ($defaults['tracking_category_mode'] ?? 'existing'))),
			'tracking_category_name' => sanitize_text_field((string) ($raw['tracking_category_name'] ?? '')),
			'confirm_forward_only_changes' => !empty($raw['confirm_forward_only_changes']) ? 1 : 0,
		));
		$payload['business_admission_cap'] = absint($raw['business_admission_cap'] ?? ($raw['business_batch_per_business_admission_cap'] ?? 0));
		return $payload;
	}
}

if (!function_exists('vms_pass_outreach_has_tracking_category_request')) {
	function vms_pass_outreach_has_tracking_category_request(array $raw): bool
	{
		return absint($raw['related_source_id'] ?? 0) > 0
			|| sanitize_text_field((string) ($raw['tracking_category_name'] ?? '')) !== ''
			|| sanitize_key((string) ($raw['tracking_category_mode'] ?? '')) === 'new';
	}
}

if (!function_exists('vms_pass_outreach_resolve_tracking_category_request')) {
	function vms_pass_outreach_resolve_tracking_category_request(array $raw, bool $create_if_needed = false, int $user_id = 0)
	{
		$tracking_category_name = sanitize_text_field((string) ($raw['tracking_category_name'] ?? ''));
		$tracking_category_mode = sanitize_key((string) ($raw['tracking_category_mode'] ?? ''));
		$related_source_id = absint($raw['related_source_id'] ?? 0);

		if (!in_array($tracking_category_mode, vms_pass_outreach_allowed_tracking_category_modes(), true)) {
			$tracking_category_mode = $tracking_category_name !== '' ? 'new' : 'existing';
		}

			if ($tracking_category_mode === 'new') {
				if ($tracking_category_name === '') {
					return new WP_Error('missing_tracking_category_name', __('Enter a tracking source name.', 'backstage-outreach'));
				}
			if (!$create_if_needed) {
				return array(
					'source_id' => 0,
					'source_name' => $tracking_category_name,
					'tracking_category_mode' => 'new',
				);
			}
				if (!function_exists('vms_pass_claims_create_source_label')) {
					return new WP_Error('tracking_category_create_unavailable', __('Inline tracking source creation is unavailable.', 'backstage-outreach'));
				}
			$source = vms_pass_claims_create_source_label($tracking_category_name, $user_id);
			if (is_wp_error($source)) {
				return $source;
			}
			return array(
				'source_id' => absint($source['id'] ?? 0),
				'source_name' => sanitize_text_field((string) ($source['source_name'] ?? $tracking_category_name)),
				'tracking_category_mode' => 'new',
			);
		}

			if ($related_source_id <= 0) {
				return new WP_Error('missing_related_source', __('Select an existing tracking source or create a new one.', 'backstage-outreach'));
			}
			if (!function_exists('vms_pass_claims_get_source_by_id')) {
				return new WP_Error('invalid_related_source', __('Select a valid tracking source.', 'backstage-outreach'));
			}

			$source = vms_pass_claims_get_source_by_id($related_source_id);
			if (!is_array($source)) {
				return new WP_Error('invalid_related_source', __('Select a valid tracking source.', 'backstage-outreach'));
			}

		return array(
			'source_id' => $related_source_id,
			'source_name' => sanitize_text_field((string) ($source['source_name'] ?? '')),
			'tracking_category_mode' => 'existing',
		);
	}
}

if (!function_exists('vms_pass_outreach_campaign_db_formats')) {
	function vms_pass_outreach_campaign_db_formats(array $data): array
	{
		$map = array(
			'campaign_name' => '%s',
			'campaign_purpose' => '%s',
			'email_subject' => '%s',
			'message_template' => '%s',
			'internal_notes' => '%s',
			'related_source_id' => '%d',
			'related_batch_id' => '%d',
			'validity_type' => '%s',
			'single_event_plan_id' => '%d',
			'start_date' => '%s',
			'end_date' => '%s',
			'season_label' => '%s',
			'expires_at' => '%s',
			'admissions_per_recipient' => '%d',
			'total_admission_cap' => '%d',
			'status' => '%s',
			'eligibility_mode' => '%s',
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

if (!function_exists('vms_pass_outreach_get_campaign_by_id')) {
	function vms_pass_outreach_get_campaign_by_id(int $campaign_id): ?array
	{
		if ($campaign_id <= 0) {
			return null;
		}

		global $wpdb;
		$table = vms_admission_table_pass_outreach_campaigns();
		$sources = vms_admission_table_pass_sources();
		$batches = vms_admission_table_pass_batches();
		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT c.*, s.source_name, b.batch_name
			FROM {$table} c
			LEFT JOIN {$sources} s ON s.id = c.related_source_id
			LEFT JOIN {$batches} b ON b.id = c.related_batch_id
			WHERE c.id = %d
			LIMIT 1",
			$campaign_id
		), ARRAY_A);

		return is_array($row) ? vms_pass_outreach_normalize_campaign_row($row) : null;
	}
}

if (!function_exists('vms_pass_outreach_allows_legacy_source_only_mode')) {
	function vms_pass_outreach_allows_legacy_source_only_mode(int $campaign_id, int $related_batch_id = 0): bool
	{
		if ($campaign_id <= 0 || $related_batch_id > 0) {
			return false;
		}

		$existing_campaign = vms_pass_outreach_get_campaign_by_id($campaign_id);
		return is_array($existing_campaign) && absint($existing_campaign['related_batch_id'] ?? 0) <= 0;
	}
}

if (!function_exists('vms_pass_outreach_validation_error_fields')) {
	function vms_pass_outreach_validation_error_fields(string $code): array
	{
			switch (sanitize_key($code)) {
				case 'missing_campaign_name':
					return array('campaign_name');
				case 'purpose_unavailable':
					return array('campaign_purpose');
				case 'missing_related_batch':
				case 'invalid_related_batch':
				case 'inactive_related_batch':
				case 'unsupported_business_batch':
			case 'invalid_related_batch_source':
			case 'duplicate_live_batch_campaign':
			case 'campaign_batch_locked':
				return array('related_batch_id');
			case 'batch_source_mismatch':
				return array('related_source_id', 'related_batch_id');
			case 'missing_related_source':
			case 'invalid_related_source':
			case 'duplicate_live_source_campaign':
				return array('related_source_id');
			case 'missing_tracking_category_name':
			case 'tracking_category_create_unavailable':
			case 'source_create_failed':
			case 'missing_source_name':
				return array('tracking_category_name');
			case 'invalid_campaign_status':
				return array('status');
			case 'invalid_eligibility_mode':
				return array('eligibility_mode');
			case 'invalid_validity_type':
				return array('validity_type');
			case 'invalid_scope_event':
				return array('single_event_plan_id');
			case 'missing_scope_dates':
			case 'invalid_scope_dates':
				return array('start_date', 'end_date');
			case 'invalid_scope_season':
				return array('season_label', 'start_date', 'end_date');
			case 'invalid_admissions_per_recipient':
				return array('admissions_per_recipient');
			case 'invalid_total_campaign_cap':
				return array('total_admission_cap');
			case 'invalid_business_admission_cap':
				return array('business_batch_per_business_admission_cap');
			case 'campaign_scope_change_confirmation_required':
				return array('validity_type', 'single_event_plan_id', 'start_date', 'end_date', 'season_label', 'confirm_forward_only_changes');
			case 'campaign_cap_below_claimed_confirmation_required':
				return array('total_admission_cap', 'confirm_forward_only_changes');
			case 'recipient_cap_below_claimed_confirmation_required':
				return array('admissions_per_recipient', 'confirm_forward_only_changes');
			default:
				return array();
		}
	}
}

if (!function_exists('vms_pass_outreach_field_errors_from_error')) {
	function vms_pass_outreach_field_errors_from_error(WP_Error $error): array
	{
		$code = (string) $error->get_error_code();
		$message = (string) $error->get_error_message();
		$fields = vms_pass_outreach_validation_error_fields($code);
		$mapped = array();
		foreach ($fields as $field_key) {
			$mapped[sanitize_key((string) $field_key)] = $message;
		}
		return $mapped;
	}
}

if (!function_exists('vms_pass_outreach_field_wrapper_class')) {
	function vms_pass_outreach_field_wrapper_class(array $field_errors, array $keys = array(), string $extra_classes = ''): string
	{
		$classes = array();
		if ($extra_classes !== '') {
			$classes = preg_split('/\s+/', trim($extra_classes)) ?: array();
		}
		$has_error = false;
		foreach ($keys as $key) {
			if (!empty($field_errors[sanitize_key((string) $key)])) {
				$has_error = true;
				break;
			}
		}
		if ($has_error) {
			$classes[] = 'vms-pass-field-has-error';
		}
		$classes = array_values(array_unique(array_filter(array_map('sanitize_html_class', $classes))));
		return !empty($classes) ? ' class="' . esc_attr(implode(' ', $classes)) . '"' : '';
	}
}

if (!function_exists('vms_pass_outreach_render_field_messages')) {
	function vms_pass_outreach_render_field_messages(array $field_errors, array $keys = array(), string $description = ''): string
	{
		$parts = array();
		$error_messages = array();
		foreach ($keys as $key) {
			$key = sanitize_key((string) $key);
			if (!empty($field_errors[$key])) {
				$error_messages[] = (string) $field_errors[$key];
			}
		}
		foreach (array_values(array_unique($error_messages)) as $message) {
			$parts[] = '<span class="vms-pass-field-error">' . esc_html($message) . '</span>';
		}
		if ($description !== '') {
			$parts[] = '<span class="description">' . esc_html($description) . '</span>';
		}
		return implode('', $parts);
	}
}

if (!function_exists('vms_pass_outreach_format_datetime_input_value')) {
	function vms_pass_outreach_format_datetime_input_value(string $raw): string
	{
		$raw = trim($raw);
		if ($raw === '') {
			return '';
		}
		if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?$/', $raw)) {
			return $raw;
		}
		return function_exists('vms_pass_claims_format_local_datetime_input')
			? vms_pass_claims_format_local_datetime_input($raw)
			: $raw;
	}
}

if (!function_exists('vms_pass_outreach_get_campaigns')) {
	function vms_pass_outreach_get_campaigns(array $args = array()): array
	{
		global $wpdb;

		$table = vms_admission_table_pass_outreach_campaigns();
		$sources = vms_admission_table_pass_sources();
		$batches = vms_admission_table_pass_batches();
		$limit = isset($args['limit']) ? max(1, min(500, absint($args['limit']))) : 200;
		$where = array('1=1');
		$params = array();

		$source_id = isset($args['related_source_id']) ? absint($args['related_source_id']) : 0;
		if ($source_id > 0) {
			$where[] = 'c.related_source_id = %d';
			$params[] = $source_id;
		}

		$batch_id = isset($args['related_batch_id']) ? absint($args['related_batch_id']) : 0;
		if ($batch_id > 0) {
			$where[] = 'c.related_batch_id = %d';
			$params[] = $batch_id;
		}

		$status = isset($args['status']) ? sanitize_key((string) $args['status']) : '';
		if ($status !== '' && in_array($status, vms_pass_outreach_allowed_statuses(), true)) {
			$where[] = 'c.status = %s';
			$params[] = $status;
		}

		$campaign_purpose = '';
		if (isset($args['campaign_purpose'])) {
			$campaign_purpose = sanitize_key((string) $args['campaign_purpose']);
		} elseif (isset($args['purpose'])) {
			$campaign_purpose = sanitize_key((string) $args['purpose']);
		}
		if ($campaign_purpose !== '') {
			$catalog = vms_outreach_purpose_catalog();
			if (isset($catalog[$campaign_purpose])) {
				$where[] = 'c.campaign_purpose = %s';
				$params[] = $campaign_purpose;
			}
		}

		$sql = "SELECT c.*, s.source_name, b.batch_name
			FROM {$table} c
			LEFT JOIN {$sources} s ON s.id = c.related_source_id
			LEFT JOIN {$batches} b ON b.id = c.related_batch_id
			WHERE " . implode(' AND ', $where) . "
			ORDER BY COALESCE(c.updated_at, c.created_at) DESC, c.id DESC
			LIMIT %d";
		$params[] = $limit;

		$rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
		if (!is_array($rows)) {
			return array();
		}

		return array_map('vms_pass_outreach_normalize_campaign_row', $rows);
	}
}

if (!function_exists('vms_pass_outreach_find_live_conflict')) {
	function vms_pass_outreach_find_live_conflict(array $payload, int $exclude_campaign_id = 0): ?array
	{
		$status = sanitize_key((string) ($payload['status'] ?? 'draft'));
		if (!in_array($status, array('draft', 'active'), true)) {
			return null;
		}

		$related_batch_id = absint($payload['related_batch_id'] ?? 0);
		$related_source_id = absint($payload['related_source_id'] ?? 0);
		$campaign_purpose = vms_outreach_normalize_campaign_purpose((string) ($payload['campaign_purpose'] ?? vms_outreach_default_campaign_purpose()));
		if ($related_batch_id <= 0 && $related_source_id <= 0) {
			return null;
		}

		global $wpdb;
		$table = vms_admission_table_pass_outreach_campaigns();
			if ($related_batch_id > 0) {
				$sql = "SELECT id, campaign_name, status
					FROM {$table}
					WHERE related_batch_id = %d
						AND campaign_purpose = %s
						AND status IN ('draft', 'active')
						AND id <> %d
					ORDER BY COALESCE(updated_at, created_at) DESC, id DESC
					LIMIT 1";
				$row = $wpdb->get_row($wpdb->prepare($sql, $related_batch_id, $campaign_purpose, $exclude_campaign_id), ARRAY_A);
			} else {
				$sql = "SELECT id, campaign_name, status
					FROM {$table}
					WHERE related_source_id = %d
						AND campaign_purpose = %s
						AND (related_batch_id IS NULL OR related_batch_id = 0)
						AND status IN ('draft', 'active')
						AND id <> %d
					ORDER BY COALESCE(updated_at, created_at) DESC, id DESC
					LIMIT 1";
				$row = $wpdb->get_row($wpdb->prepare($sql, $related_source_id, $campaign_purpose, $exclude_campaign_id), ARRAY_A);
			}

		return is_array($row) ? $row : null;
	}
}

if (!function_exists('vms_pass_outreach_sanitize_upload_first_campaign_setup')) {
	function vms_pass_outreach_sanitize_upload_first_campaign_setup(array $raw, bool $require_campaign_name = true)
	{
		$payload = vms_pass_outreach_soft_campaign_payload_for_form($raw, 0);
		$has_sources = function_exists('vms_pass_claims_get_sources')
			? !empty(array_filter((array) vms_pass_claims_get_sources(true), static function ($source): bool {
				return absint(is_array($source) ? ($source['id'] ?? 0) : 0) > 0;
			}))
			: false;
		$campaign_name = sanitize_text_field(vms_pass_outreach_display_text((string) ($payload['campaign_name'] ?? '')));
		$campaign_purpose = vms_outreach_normalize_campaign_purpose((string) ($payload['campaign_purpose'] ?? vms_outreach_default_campaign_purpose()));
		$email_subject = sanitize_text_field(vms_pass_outreach_display_text((string) ($payload['email_subject'] ?? '')));
		$message_template = vms_pass_outreach_sanitize_plain_text_template((string) ($payload['message_template'] ?? ''));
		$internal_notes = sanitize_textarea_field(vms_pass_outreach_display_text((string) ($payload['internal_notes'] ?? '')));
		$related_batch_id = absint($payload['related_batch_id'] ?? 0);
		$validity_type = sanitize_key((string) ($payload['validity_type'] ?? 'batch_default'));
		$single_event_plan_id = absint($payload['single_event_plan_id'] ?? 0);
		$start_date = sanitize_text_field((string) ($payload['start_date'] ?? ''));
		$end_date = sanitize_text_field((string) ($payload['end_date'] ?? ''));
		$season_label = sanitize_text_field((string) ($payload['season_label'] ?? ''));
		$expires_at = function_exists('vms_pass_claims_parse_local_datetime')
			? vms_pass_claims_parse_local_datetime((string) ($payload['expires_at'] ?? ''))
			: '';
		$admissions_per_recipient = max(1, absint($payload['admissions_per_recipient'] ?? 1));
		$status = sanitize_key((string) ($payload['status'] ?? 'draft'));
		$eligibility_mode = sanitize_key((string) ($payload['eligibility_mode'] ?? 'anyone_with_invite'));
		$workflow_mode = 'upload_first';
		$recipient_source_mode = vms_pass_outreach_normalize_recipient_source_mode($payload, $has_sources);
		$tracking_category_mode = in_array($recipient_source_mode, array('existing_source', 'business_source'), true) ? 'existing' : 'new';
		$tracking_category_name = sanitize_text_field((string) ($payload['tracking_category_name'] ?? ''));
		$business_admission_cap = absint($payload['business_admission_cap'] ?? ($raw['business_batch_per_business_admission_cap'] ?? 0));
		if ($business_admission_cap > 50000) {
			return new WP_Error('invalid_business_admission_cap', __('Total admissions allowed per business cannot exceed 50,000.', 'backstage-outreach'));
		}

		if ($require_campaign_name && $campaign_name === '') {
			return new WP_Error('missing_campaign_name', __('Campaign name is required.', 'backstage-outreach'));
		}
		if (!vms_outreach_is_purpose_available($campaign_purpose)) {
			return new WP_Error('purpose_unavailable', __('This outreach purpose is not available yet. Guest Pass Invitation is live now; customer marketing sending should remain in MailPoet.', 'backstage-outreach'));
		}
		if ($recipient_source_mode !== 'business_source' && $related_batch_id > 0) {
			return new WP_Error('upload_preview_existing_batch', __('Leave the Guest Pass Batch field blank for upload-first creation. VMS will create the linked batch automatically.', 'backstage-outreach'));
		}
		if ($recipient_source_mode === 'business_source' && absint($payload['related_source_id'] ?? 0) <= 0) {
			return new WP_Error('missing_related_source', __('Select a business Source before choosing an offer batch.', 'backstage-outreach'));
		}
		if ($recipient_source_mode === 'business_source' && $related_batch_id <= 0) {
			return new WP_Error('missing_related_batch', __('Select an existing Guest Pass batch for the reusable business links.', 'backstage-outreach'));
		}
		if ($email_subject === '') {
			$email_subject = vms_pass_outreach_default_email_subject();
		}
		if ($message_template === '') {
			$message_template = vms_pass_outreach_default_message_template();
		}

		$source_payload = $payload;
		$source_payload['workflow_mode'] = $workflow_mode;
		$source_payload['recipient_source_mode'] = $recipient_source_mode;
		$source_payload['tracking_category_mode'] = $tracking_category_mode;
		$source_request = vms_pass_outreach_resolve_tracking_category_request($source_payload, false, 0);
		if (is_wp_error($source_request)) {
			return $source_request;
		}
		$related_source_id = absint($source_request['source_id'] ?? 0);
		$tracking_category_mode = sanitize_key((string) ($source_request['tracking_category_mode'] ?? $tracking_category_mode));
		$tracking_category_name = $tracking_category_mode === 'new'
			? sanitize_text_field((string) ($source_request['source_name'] ?? $tracking_category_name))
			: '';
		if ($recipient_source_mode === 'business_source') {
			$batch = function_exists('vms_pass_claims_get_batch_by_id') ? vms_pass_claims_get_batch_by_id($related_batch_id) : null;
			if (!is_array($batch)) {
				return new WP_Error('invalid_related_batch', __('Select a valid Guest Pass batch.', 'backstage-outreach'));
			}
			$eligibility_error = vms_pass_outreach_business_batch_eligibility_error($batch, $related_source_id);
			if (is_wp_error($eligibility_error)) {
				return $eligibility_error;
			}
			$admissions_per_recipient = max(1, absint($batch['admissions_per_link'] ?? 1));
			$validity_type = sanitize_key((string) ($batch['validity_type'] ?? 'any_event'));
			$single_event_plan_id = absint($batch['single_event_plan_id'] ?? 0);
			$start_date = sanitize_text_field((string) ($batch['start_date'] ?? ''));
			$end_date = sanitize_text_field((string) ($batch['end_date'] ?? ''));
			$season_label = sanitize_text_field((string) ($batch['season_label'] ?? ''));
			$expires_at = sanitize_text_field((string) ($batch['expires_at'] ?? ''));
		}

		if (!in_array($status, vms_pass_outreach_allowed_statuses(), true)) {
			return new WP_Error('invalid_campaign_status', __('Select a valid campaign status.', 'backstage-outreach'));
		}
		if (!in_array($eligibility_mode, vms_pass_outreach_allowed_eligibility_modes(), true)) {
			return new WP_Error('invalid_eligibility_mode', __('Select a valid eligibility mode.', 'backstage-outreach'));
		}
		if (!in_array($validity_type, vms_pass_outreach_allowed_validity_types(), true)) {
			return new WP_Error('invalid_validity_type', __('Select a valid campaign event scope.', 'backstage-outreach'));
		}
		if ($admissions_per_recipient < 1 || $admissions_per_recipient > 100) {
			return new WP_Error('invalid_admissions_per_recipient', __('Admissions per recipient must be between 1 and 100.', 'backstage-outreach'));
		}

		if ($validity_type === 'single_event') {
			$event = function_exists('vms_pass_claims_get_event_plan_brief') ? vms_pass_claims_get_event_plan_brief($single_event_plan_id) : null;
			if (!is_array($event)) {
				return new WP_Error('invalid_scope_event', __('Select a valid published event for Single Event scope.', 'backstage-outreach'));
			}
		}
		if ($validity_type === 'date_range') {
			if ($start_date === '' || $end_date === '') {
				return new WP_Error('missing_scope_dates', __('Start and end dates are required for Date Range scope.', 'backstage-outreach'));
			}
			if ($start_date > $end_date) {
				return new WP_Error('invalid_scope_dates', __('Start date must be on or before end date.', 'backstage-outreach'));
			}
		}
		if ($validity_type === 'season' && $season_label === '' && ($start_date === '' || $end_date === '')) {
			return new WP_Error('invalid_scope_season', __('For Season scope, provide a season label or a date range.', 'backstage-outreach'));
		}

			return array(
				'campaign_name' => $campaign_name,
				'campaign_purpose' => $campaign_purpose,
				'email_subject' => $email_subject,
				'message_template' => $message_template,
				'internal_notes' => $internal_notes,
			'related_source_id' => $related_source_id,
			'related_batch_id' => $recipient_source_mode === 'business_source' ? $related_batch_id : 0,
			'validity_type' => $validity_type,
			'single_event_plan_id' => $single_event_plan_id,
			'start_date' => $start_date,
			'end_date' => $end_date,
			'season_label' => $season_label,
			'expires_at' => $expires_at,
			'admissions_per_recipient' => $admissions_per_recipient,
			'total_admission_cap' => 0,
			'status' => $status,
			'eligibility_mode' => $eligibility_mode,
			'workflow_mode' => 'upload_first',
			'recipient_source_mode' => $recipient_source_mode,
			'tracking_category_mode' => in_array($tracking_category_mode, vms_pass_outreach_allowed_tracking_category_modes(), true) ? $tracking_category_mode : 'existing',
			'tracking_category_name' => $tracking_category_name,
			'business_admission_cap' => $business_admission_cap,
		);
	}
}

if (!function_exists('vms_pass_outreach_auto_batch_validity_type')) {
	function vms_pass_outreach_auto_batch_validity_type(array $campaign_setup): string
	{
		$validity_type = sanitize_key((string) ($campaign_setup['validity_type'] ?? 'batch_default'));
		if (function_exists('vms_pass_claims_allowed_validity_types') && in_array($validity_type, vms_pass_claims_allowed_validity_types(), true)) {
			return $validity_type;
		}

		if (absint($campaign_setup['single_event_plan_id'] ?? 0) > 0) {
			return 'single_event';
		}
		if (sanitize_text_field((string) ($campaign_setup['season_label'] ?? '')) !== '') {
			return 'season';
		}
		$start_date = sanitize_text_field((string) ($campaign_setup['start_date'] ?? ''));
		$end_date = sanitize_text_field((string) ($campaign_setup['end_date'] ?? ''));
		if ($start_date !== '' && $end_date !== '') {
			return 'date_range';
		}

		return 'any_event';
	}
}

if (!function_exists('vms_pass_outreach_build_auto_batch_payload')) {
	function vms_pass_outreach_build_auto_batch_payload(array $campaign_setup, int $recipient_count)
	{
		$recipient_count = max(0, $recipient_count);
		$admissions_per_recipient = max(1, absint($campaign_setup['admissions_per_recipient'] ?? 1));
		$total_admission_cap = $recipient_count * $admissions_per_recipient;
		$notes = sprintf(
			/* translators: %s: outreach campaign name */
			__('Auto-created for outreach campaign: %s', 'backstage-outreach'),
			(string) ($campaign_setup['campaign_name'] ?? '')
		);
		$internal_notes = trim((string) ($campaign_setup['internal_notes'] ?? ''));
		if ($internal_notes !== '') {
			$notes .= "\n\n" . $internal_notes;
		}

		$raw = array(
			'source_id' => absint($campaign_setup['related_source_id'] ?? 0),
			'batch_name' => sanitize_text_field((string) ($campaign_setup['campaign_name'] ?? '')),
			'quantity' => $recipient_count,
			'admissions_per_link' => $admissions_per_recipient,
			'total_admission_cap' => $total_admission_cap,
			'validity_type' => vms_pass_outreach_auto_batch_validity_type($campaign_setup),
			'single_event_plan_id' => absint($campaign_setup['single_event_plan_id'] ?? 0),
			'start_date' => sanitize_text_field((string) ($campaign_setup['start_date'] ?? '')),
			'end_date' => sanitize_text_field((string) ($campaign_setup['end_date'] ?? '')),
			'season_label' => sanitize_text_field((string) ($campaign_setup['season_label'] ?? '')),
			'venue_ids' => array(),
			'value_type' => 'free',
			'value_amount' => 100.0,
			'expires_at' => sanitize_text_field((string) ($campaign_setup['expires_at'] ?? '')),
			'status' => 'active',
			'checkin_open_mode' => 'same_day',
			'max_per_phone' => 0,
			'max_per_email' => 0,
			'notes' => $notes,
		);

		if (function_exists('vms_pass_claims_sanitize_batch_payload')) {
			return vms_pass_claims_sanitize_batch_payload($raw);
		}

		return array(
			'source_id' => absint($raw['source_id'] ?? 0),
			'batch_name' => sanitize_text_field((string) ($raw['batch_name'] ?? '')),
			'quantity' => absint($raw['quantity'] ?? 0),
			'admissions_per_link' => absint($raw['admissions_per_link'] ?? 1),
			'total_admission_cap' => absint($raw['total_admission_cap'] ?? 0),
			'validity_type' => sanitize_key((string) ($raw['validity_type'] ?? 'any_event')),
			'single_event_plan_id' => absint($raw['single_event_plan_id'] ?? 0),
			'start_date' => sanitize_text_field((string) ($raw['start_date'] ?? '')),
			'end_date' => sanitize_text_field((string) ($raw['end_date'] ?? '')),
			'season_label' => sanitize_text_field((string) ($raw['season_label'] ?? '')),
			'venue_ids_json' => '',
			'value_type' => sanitize_key((string) ($raw['value_type'] ?? 'free')),
			'value_amount' => (float) ($raw['value_amount'] ?? 100.0),
			'applies_to' => 'entry_only',
			'expires_at' => sanitize_text_field((string) ($raw['expires_at'] ?? '')),
			'status' => sanitize_key((string) ($raw['status'] ?? 'active')),
			'checkin_open_mode' => sanitize_key((string) ($raw['checkin_open_mode'] ?? 'same_day')),
			'max_per_phone' => absint($raw['max_per_phone'] ?? 0),
			'max_per_email' => absint($raw['max_per_email'] ?? 0),
			'notes' => sanitize_textarea_field((string) ($raw['notes'] ?? '')),
		);
	}
}

if (!function_exists('vms_pass_outreach_create_auto_batch')) {
	function vms_pass_outreach_create_auto_batch(array $batch_payload, int $user_id)
	{
		if (absint($batch_payload['source_id'] ?? 0) <= 0) {
			return new WP_Error('invalid_source', __('Select or create a valid tracking source before creating the outreach campaign.', 'backstage-outreach'));
		}
		if (absint($batch_payload['quantity'] ?? 0) <= 0) {
			return new WP_Error('invalid_quantity', __('At least one valid outreach recipient is required before creating an outreach batch.', 'backstage-outreach'));
		}

		global $wpdb;
		$table = vms_admission_table_pass_batches();
		$now = function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql');
		$insert = $wpdb->insert(
			$table,
			array(
				'source_id' => (int) $batch_payload['source_id'],
				'batch_name' => (string) $batch_payload['batch_name'],
				'quantity' => (int) $batch_payload['quantity'],
				'admissions_per_link' => (int) $batch_payload['admissions_per_link'],
				'total_admission_cap' => (int) $batch_payload['total_admission_cap'],
				'validity_type' => (string) $batch_payload['validity_type'],
				'single_event_plan_id' => (int) ($batch_payload['single_event_plan_id'] ?? 0),
				'start_date' => (string) ($batch_payload['start_date'] ?? ''),
				'end_date' => (string) ($batch_payload['end_date'] ?? ''),
				'season_label' => (string) ($batch_payload['season_label'] ?? ''),
				'venue_ids_json' => (string) ($batch_payload['venue_ids_json'] ?? ''),
				'value_type' => (string) ($batch_payload['value_type'] ?? 'free'),
				'value_amount' => (float) ($batch_payload['value_amount'] ?? 100.0),
				'applies_to' => 'entry_only',
				'expires_at' => (string) ($batch_payload['expires_at'] ?? ''),
				'status' => (string) ($batch_payload['status'] ?? 'active'),
				'checkin_open_mode' => (string) ($batch_payload['checkin_open_mode'] ?? 'same_day'),
				'max_per_phone' => (int) ($batch_payload['max_per_phone'] ?? 0),
				'max_per_email' => (int) ($batch_payload['max_per_email'] ?? 0),
				'notes' => (string) ($batch_payload['notes'] ?? ''),
				'created_by' => $user_id,
				'created_at' => $now,
			),
			array('%d', '%s', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s')
		);
		if ($insert === false) {
			return new WP_Error('outreach_batch_create_failed', __('Could not auto-create the outreach Guest Pass batch.', 'backstage-outreach'));
		}

		$batch_id = (int) $wpdb->insert_id;
		$generated = function_exists('vms_pass_claims_generate_tokens_for_batch')
			? vms_pass_claims_generate_tokens_for_batch($batch_id, (int) $batch_payload['quantity'], (int) $batch_payload['source_id'], $user_id)
			: new WP_Error('missing_batch_generator', __('Guest Pass token generation is unavailable.', 'backstage-outreach'));
		if (is_wp_error($generated)) {
			$wpdb->delete($table, array('id' => $batch_id), array('%d'));
			return $generated;
		}

		return array(
			'batch_id' => $batch_id,
			'batch' => function_exists('vms_pass_claims_get_batch_by_id') ? vms_pass_claims_get_batch_by_id($batch_id) : null,
			'generated' => $generated,
		);
	}
}

if (!function_exists('vms_pass_outreach_cleanup_auto_created_records')) {
	function vms_pass_outreach_cleanup_auto_created_records(int $campaign_id, int $batch_id): void
	{
		global $wpdb;

		if ($campaign_id > 0) {
			$recipients_table = function_exists('vms_pass_outreach_recipient_table') ? vms_pass_outreach_recipient_table() : '';
			$campaigns_table = vms_admission_table_pass_outreach_campaigns();
			if ($recipients_table !== '') {
				$wpdb->delete($recipients_table, array('campaign_id' => $campaign_id), array('%d'));
			}
			$wpdb->delete($campaigns_table, array('id' => $campaign_id), array('%d'));
		}

		if ($batch_id > 0) {
			$tokens_table = vms_admission_table_pass_tokens();
			$batches_table = vms_admission_table_pass_batches();
			$wpdb->delete($tokens_table, array('batch_id' => $batch_id), array('%d'));
			$wpdb->delete($batches_table, array('id' => $batch_id), array('%d'));
		}
	}
}

if (!function_exists('vms_pass_outreach_create_business_source_campaign')) {
	/**
	 * Create the reviewed campaign shell for reusable business links.
	 *
	 * No recipient/contact rows or individual claim tokens are created here.
	 */
	function vms_pass_outreach_create_business_source_campaign(array $campaign_setup, array $reviewed_preview, int $user_id)
	{
		if (sanitize_key((string) ($campaign_setup['recipient_source_mode'] ?? '')) !== 'business_source') {
			return new WP_Error('invalid_business_source_preview', __('Reusable business campaign review is invalid.', 'backstage-outreach'));
		}

		$current_preview = vms_pass_outreach_build_business_source_preview(
			absint($campaign_setup['related_source_id'] ?? 0),
			absint($campaign_setup['related_batch_id'] ?? 0)
		);
		if (is_wp_error($current_preview)) {
			return $current_preview;
		}
		if (absint($current_preview['link_eligible_count'] ?? 0) <= 0) {
			return new WP_Error('business_source_empty', __('The selected Source has no active reusable businesses.', 'backstage-outreach'));
		}
		$reviewed_digest = sanitize_text_field((string) ($reviewed_preview['membership_digest'] ?? ''));
		$current_digest = sanitize_text_field((string) ($current_preview['membership_digest'] ?? ''));
		if ($reviewed_digest === '' || $current_digest === '' || !hash_equals($reviewed_digest, $current_digest)) {
			return new WP_Error('business_source_changed', __('Active Source memberships changed after review. Refresh the business preview before creating the campaign.', 'backstage-outreach'));
		}

		$final_raw = array_merge($campaign_setup, array(
			'related_source_id' => absint($current_preview['source_id'] ?? 0),
			'related_batch_id' => absint($current_preview['batch_id'] ?? 0),
			'total_admission_cap' => absint($current_preview['batch_total_admission_cap'] ?? 0),
		));
		$payload = vms_pass_outreach_sanitize_campaign_payload($final_raw, 0);
		if (is_wp_error($payload)) {
			return $payload;
		}

		global $wpdb;
		$now = function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql');
		$insert_data = $payload;
		$insert_data['created_by'] = $user_id;
		$insert_data['created_at'] = $now;
		$inserted = $wpdb->insert(
			vms_admission_table_pass_outreach_campaigns(),
			$insert_data,
			vms_pass_outreach_campaign_db_formats($insert_data)
		);
		$campaign_id = $inserted === false ? 0 : absint($wpdb->insert_id ?? 0);
		if ($campaign_id <= 0) {
			return new WP_Error('campaign_create_failed', __('Could not create the reusable business campaign.', 'backstage-outreach'));
		}
		set_transient(
			vms_pass_outreach_business_distribution_handoff_key($campaign_id),
			array(
				'admission_cap' => absint($campaign_setup['business_admission_cap'] ?? 0),
				'source_id' => absint($current_preview['source_id'] ?? 0),
				'batch_id' => absint($current_preview['batch_id'] ?? 0),
			),
			30 * MINUTE_IN_SECONDS
		);

		if (function_exists('vms_admission_audit_log')) {
			vms_admission_audit_log(0, null, 'pass_outreach_business_campaign_create', $user_id, 'admin', array(
				'campaign_id' => $campaign_id,
				'campaign_name' => (string) ($payload['campaign_name'] ?? ''),
				'related_source_id' => (int) ($payload['related_source_id'] ?? 0),
				'related_batch_id' => (int) ($payload['related_batch_id'] ?? 0),
				'business_link_count' => absint($current_preview['link_eligible_count'] ?? 0),
				'business_email_count' => absint($current_preview['email_count'] ?? 0),
				'missing_business_email_count' => absint($current_preview['missing_email_count'] ?? 0),
				'recipient_count' => 0,
			));
		}

		return array(
			'campaign' => array_merge($payload, array('id' => $campaign_id)),
			'business_preview' => $current_preview,
		);
	}
}

if (!function_exists('vms_pass_outreach_sanitize_campaign_payload')) {
	function vms_pass_outreach_sanitize_campaign_payload(array $raw, int $campaign_id = 0)
	{
		$campaign_name = sanitize_text_field(vms_pass_outreach_display_text((string) ($raw['campaign_name'] ?? '')));
		$campaign_purpose = vms_outreach_normalize_campaign_purpose((string) ($raw['campaign_purpose'] ?? vms_outreach_default_campaign_purpose()));
		$email_subject = sanitize_text_field(vms_pass_outreach_display_text((string) ($raw['email_subject'] ?? '')));
		$message_template = vms_pass_outreach_sanitize_plain_text_template((string) ($raw['message_template'] ?? ''));
		$internal_notes = sanitize_textarea_field(vms_pass_outreach_display_text((string) ($raw['internal_notes'] ?? '')));
		$related_source_id = absint($raw['related_source_id'] ?? 0);
		$related_batch_id = absint($raw['related_batch_id'] ?? 0);
		$validity_type = sanitize_key((string) ($raw['validity_type'] ?? 'batch_default'));
		$single_event_plan_id = absint($raw['single_event_plan_id'] ?? 0);
		$start_date = sanitize_text_field((string) ($raw['start_date'] ?? ''));
		$end_date = sanitize_text_field((string) ($raw['end_date'] ?? ''));
		$season_label = sanitize_text_field((string) ($raw['season_label'] ?? ''));
		$expires_at = function_exists('vms_pass_claims_parse_local_datetime')
			? vms_pass_claims_parse_local_datetime((string) ($raw['expires_at'] ?? ''))
			: '';
		$admissions_per_recipient = max(1, absint($raw['admissions_per_recipient'] ?? 1));
		$total_admission_cap = max(0, absint($raw['total_admission_cap'] ?? 0));
		$status = sanitize_key((string) ($raw['status'] ?? 'draft'));
		$eligibility_mode = sanitize_key((string) ($raw['eligibility_mode'] ?? 'anyone_with_invite'));

		if ($campaign_name === '') {
			return new WP_Error('missing_campaign_name', __('Campaign name is required.', 'backstage-outreach'));
		}
		if (!vms_outreach_is_purpose_available($campaign_purpose)) {
			return new WP_Error('purpose_unavailable', __('This outreach purpose is not available yet. Guest Pass Invitation is live now; customer marketing sending should remain in MailPoet.', 'backstage-outreach'));
		}
		if ($email_subject === '') {
			$email_subject = vms_pass_outreach_default_email_subject();
		}
		if ($message_template === '') {
			$message_template = vms_pass_outreach_default_message_template();
		}

		$allow_legacy_source_only = vms_pass_outreach_allows_legacy_source_only_mode($campaign_id, $related_batch_id);
		$batch = null;
		if ($related_batch_id > 0) {
			$batch = function_exists('vms_pass_claims_get_batch_by_id') ? vms_pass_claims_get_batch_by_id($related_batch_id) : null;
			if (!is_array($batch)) {
				return new WP_Error('invalid_related_batch', __('Select a valid Guest Pass batch.', 'backstage-outreach'));
			}
			$batch_source_id = absint($batch['source_id'] ?? 0);
			if ($batch_source_id <= 0) {
				return new WP_Error('invalid_related_batch_source', __('The selected Guest Pass batch is missing its tracking source.', 'backstage-outreach'));
			}
			if ($related_source_id > 0 && $related_source_id !== $batch_source_id) {
				return new WP_Error('batch_source_mismatch', __('The selected batch does not belong to the selected tracking source.', 'backstage-outreach'));
			}
			$related_source_id = $batch_source_id;
		} elseif (!$allow_legacy_source_only) {
			return new WP_Error(
				'missing_related_batch',
				__('Choose a Guest Pass Batch / Invite Link Pool. Recipient outreach currently requires a linked batch so each imported recipient can reserve a unique invite link.', 'backstage-outreach')
			);
		}

		if ($related_source_id <= 0) {
			return new WP_Error(
				'missing_related_source',
				$allow_legacy_source_only
					? __('Select a tracking source for this legacy outreach campaign.', 'backstage-outreach')
					: __('Select a valid tracking source, or choose a batch so VMS can fill it automatically.', 'backstage-outreach')
			);
		}

		if (!function_exists('vms_pass_claims_get_source_by_id') || !vms_pass_claims_get_source_by_id($related_source_id)) {
			return new WP_Error('invalid_related_source', __('Select a valid tracking source.', 'backstage-outreach'));
		}

		if (!in_array($status, vms_pass_outreach_allowed_statuses(), true)) {
			return new WP_Error('invalid_campaign_status', __('Select a valid campaign status.', 'backstage-outreach'));
		}

		if (!in_array($eligibility_mode, vms_pass_outreach_allowed_eligibility_modes(), true)) {
			return new WP_Error('invalid_eligibility_mode', __('Select a valid eligibility mode.', 'backstage-outreach'));
		}

		if (!in_array($validity_type, vms_pass_outreach_allowed_validity_types(), true)) {
			return new WP_Error('invalid_validity_type', __('Select a valid campaign event scope.', 'backstage-outreach'));
		}

		if ($admissions_per_recipient < 1 || $admissions_per_recipient > 100) {
			return new WP_Error('invalid_admissions_per_recipient', __('Admissions per recipient must be between 1 and 100.', 'backstage-outreach'));
		}

		if ($total_admission_cap > 50000) {
			return new WP_Error('invalid_total_campaign_cap', __('Total campaign cap must be 50000 or less.', 'backstage-outreach'));
		}

		if ($validity_type === 'single_event') {
			$event = function_exists('vms_pass_claims_get_event_plan_brief') ? vms_pass_claims_get_event_plan_brief($single_event_plan_id) : null;
			if (!is_array($event)) {
				return new WP_Error('invalid_scope_event', __('Select a valid published event for Single Event scope.', 'backstage-outreach'));
			}
		}

		if ($validity_type === 'date_range') {
			if ($start_date === '' || $end_date === '') {
				return new WP_Error('missing_scope_dates', __('Start and end dates are required for Date Range scope.', 'backstage-outreach'));
			}
			if ($start_date > $end_date) {
				return new WP_Error('invalid_scope_dates', __('Start date must be on or before end date.', 'backstage-outreach'));
			}
		}

		if ($validity_type === 'season' && $season_label === '' && ($start_date === '' || $end_date === '')) {
			return new WP_Error('invalid_scope_season', __('For Season scope, provide a season label or a date range.', 'backstage-outreach'));
		}

			$payload = array(
				'campaign_name' => $campaign_name,
				'campaign_purpose' => $campaign_purpose,
				'email_subject' => $email_subject,
				'message_template' => $message_template,
			'internal_notes' => $internal_notes,
			'related_source_id' => $related_source_id,
			'related_batch_id' => $related_batch_id > 0 ? $related_batch_id : null,
			'validity_type' => $validity_type,
			'single_event_plan_id' => $single_event_plan_id > 0 ? $single_event_plan_id : null,
			'start_date' => $start_date !== '' ? $start_date : null,
			'end_date' => $end_date !== '' ? $end_date : null,
			'season_label' => $season_label !== '' ? $season_label : null,
			'expires_at' => $expires_at !== '' ? $expires_at : null,
			'admissions_per_recipient' => $admissions_per_recipient,
			'total_admission_cap' => $total_admission_cap,
			'status' => $status,
			'eligibility_mode' => $eligibility_mode,
		);

		if (function_exists('vms_pass_outreach_validate_campaign_batch_update')) {
			$batch_guard = vms_pass_outreach_validate_campaign_batch_update($campaign_id, $payload);
			if (is_wp_error($batch_guard)) {
				return $batch_guard;
			}
		}
		if (function_exists('vms_pass_outreach_validate_campaign_runtime_guardrails')) {
			$runtime_guard = vms_pass_outreach_validate_campaign_runtime_guardrails($campaign_id, $payload, $raw);
			if (is_wp_error($runtime_guard)) {
				return $runtime_guard;
			}
		}

		$conflict = vms_pass_outreach_find_live_conflict($payload, $campaign_id);
		if (is_array($conflict)) {
			$conflict_name = sanitize_text_field((string) ($conflict['campaign_name'] ?? ''));
			if ($related_batch_id > 0) {
				return new WP_Error(
					'duplicate_live_batch_campaign',
					$conflict_name !== ''
						? sprintf(__('Another draft or active outreach campaign is already linked to this batch: %s', 'backstage-outreach'), $conflict_name)
						: __('Another draft or active outreach campaign is already linked to this batch.', 'backstage-outreach')
				);
			}

				return new WP_Error(
					'duplicate_live_source_campaign',
					$conflict_name !== ''
						? sprintf(__('Another draft or active outreach campaign is already linked to this tracking source: %s', 'backstage-outreach'), $conflict_name)
						: __('Another draft or active outreach campaign is already linked to this tracking source.', 'backstage-outreach')
				);
			}

		return $payload;
	}
}

if (!function_exists('vms_pass_outreach_activate_campaign')) {
	function vms_pass_outreach_activate_campaign($campaign)
	{
		if (!is_array($campaign)) {
			$campaign = vms_pass_outreach_get_campaign_by_id(absint($campaign));
		}
		if (!is_array($campaign)) {
			return new WP_Error('campaign_missing', __('Outreach campaign not found.', 'backstage-outreach'));
		}

		$campaign_id = absint($campaign['id'] ?? 0);
		if ($campaign_id <= 0) {
			return new WP_Error('campaign_missing', __('Outreach campaign not found.', 'backstage-outreach'));
		}

		$status = sanitize_key((string) ($campaign['status'] ?? 'draft'));
		if ($status === 'active') {
			return $campaign;
		}
		if ($status === 'closed') {
			return new WP_Error('campaign_closed', __('This outreach campaign is closed. Reopen it in the campaign editor before sending invites.', 'backstage-outreach'));
		}

		$raw = $campaign;
		$raw['status'] = 'active';
		$payload = vms_pass_outreach_sanitize_campaign_payload($raw, $campaign_id);
		if (is_wp_error($payload)) {
			return $payload;
		}

		global $wpdb;
		$table = vms_admission_table_pass_outreach_campaigns();
		$now = function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql');
		$update_data = $payload;
		$update_data['updated_by'] = get_current_user_id();
		$update_data['updated_at'] = $now;
		$updated = $wpdb->update($table, $update_data, array('id' => $campaign_id), vms_pass_outreach_campaign_db_formats($update_data), array('%d'));
		if ($updated === false) {
			return new WP_Error('campaign_activate_failed', __('Could not activate the outreach campaign.', 'backstage-outreach'));
		}

		if (function_exists('vms_admission_audit_log')) {
			vms_admission_audit_log(0, null, 'pass_outreach_campaign_activated', get_current_user_id(), 'admin', array(
				'campaign_id' => $campaign_id,
				'campaign_name' => (string) ($payload['campaign_name'] ?? ''),
				'related_source_id' => (int) ($payload['related_source_id'] ?? 0),
				'related_batch_id' => (int) ($payload['related_batch_id'] ?? 0),
				'trigger' => 'delivery_guardrail',
			));
		}

		$activated = vms_pass_outreach_get_campaign_by_id($campaign_id);
		return is_array($activated) ? $activated : array_merge($campaign, $update_data, array('id' => $campaign_id));
	}
}

if (!function_exists('vms_pass_outreach_handle_campaign_save')) {
	function vms_pass_outreach_handle_campaign_save(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-outreach'));
		}

		check_admin_referer('vms_pass_outreach_campaign_save');

		$user_id = get_current_user_id();
		$campaign_id = isset($_POST['campaign_id']) ? absint(wp_unslash($_POST['campaign_id'])) : 0;
		$raw = isset($_POST) ? (array) wp_unslash($_POST) : array();
		$raw = vms_pass_outreach_normalize_business_route_request($raw);
		$save_mode = isset($_POST['save_mode']) ? sanitize_key((string) wp_unslash($_POST['save_mode'])) : 'standard';
		$file = isset($_FILES['recipient_csv']) && is_array($_FILES['recipient_csv']) ? $_FILES['recipient_csv'] : array();
		$has_uploaded_csv = isset($file['error']) && absint($file['error']) !== UPLOAD_ERR_NO_FILE;
		$upload_flow_redirect_url = vms_pass_outreach_create_preview_url();
		$csv_preview_redirect_url = vms_pass_outreach_create_preview_url('csv_new');
		$existing_source_preview_redirect_url = vms_pass_outreach_create_preview_url('existing_source');
		$business_source_preview_redirect_url = vms_pass_outreach_create_preview_url('business_source');
		$business_source_setup_redirect_url = vms_pass_outreach_business_setup_url();
		$business_review_action_redirect_url = vms_pass_outreach_business_review_action_url();
		$contacts_preview_redirect_url = vms_pass_outreach_create_preview_url('contacts');
		$review_redirect_url = vms_pass_outreach_create_review_url();

		if ($save_mode === 'business_batch_preview' || $save_mode === 'business_batch_commit') {
			if ($campaign_id > 0) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Business offer batch setup is only available while creating a new outreach campaign.', 'backstage-outreach'));
				}
				wp_safe_redirect(vms_pass_outreach_admin_page_url(array('campaign_id' => $campaign_id)));
				exit;
			}

			if ($save_mode === 'business_batch_preview') {
				$review = vms_pass_outreach_prepare_business_batch_review($raw, $user_id);
				vms_pass_outreach_clear_upload_preview($user_id);
				vms_pass_outreach_clear_upload_mapping($user_id);
				if (is_wp_error($review)) {
					vms_pass_outreach_clear_business_batch_review($user_id);
					vms_pass_outreach_set_campaign_form_flash($user_id, array(
						'campaign_id' => 0,
						'payload' => vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
						'business_batch_form' => vms_pass_outreach_business_batch_form_payload($raw),
						'field_errors' => vms_pass_outreach_business_batch_review_error_fields($review),
					));
					if (function_exists('vms_pass_claims_set_user_message')) {
						vms_pass_claims_set_user_message('error', $review->get_error_message());
					}
					wp_safe_redirect($business_source_setup_redirect_url);
					exit;
				}

				vms_pass_outreach_set_business_batch_review($user_id, $review);
				vms_pass_outreach_set_campaign_form_flash($user_id, array(
					'campaign_id' => 0,
					'payload' => (array) ($review['form_payload'] ?? array()),
					'business_batch_form' => vms_pass_outreach_business_batch_form_payload($raw),
					'field_errors' => array(),
				));
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('success', __('Offer batch review is ready. Confirm the read-only settings below to create the batch definition. No individual claim links will be generated.', 'backstage-outreach'));
				}
				wp_safe_redirect($business_source_setup_redirect_url);
				exit;
			}

			$review_token = isset($raw['business_batch_review_token']) && is_scalar($raw['business_batch_review_token'])
				? sanitize_text_field((string) $raw['business_batch_review_token'])
				: '';
			global $wpdb;
			$lock_name = 'vms-outreach-business-batch-' . $user_id;
			if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, 5)) !== 1) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Another business offer batch request is still running. Wait a moment and try again.', 'backstage-outreach'));
				}
				wp_safe_redirect($business_source_setup_redirect_url);
				exit;
			}
			try {
				$review = vms_pass_outreach_get_business_batch_review($user_id);
				$stored_token = sanitize_text_field((string) ($review['review_token'] ?? ''));
				$batch_payload = isset($review['batch_payload']) && is_array($review['batch_payload']) ? (array) $review['batch_payload'] : array();
				$stored_digest = sanitize_text_field((string) ($review['payload_digest'] ?? ''));
				$current_digest = hash('sha256', (string) wp_json_encode($batch_payload));
				if ($review_token === '' || $stored_token === '' || !hash_equals($stored_token, $review_token) || $stored_digest === '' || !hash_equals($stored_digest, $current_digest)) {
					if (function_exists('vms_pass_claims_set_user_message')) {
						vms_pass_claims_set_user_message('error', __('The reviewed batch is missing, expired, or was already submitted. Review the batch settings again.', 'backstage-outreach'));
					}
					wp_safe_redirect($business_source_setup_redirect_url);
					exit;
				}
				if (absint($raw['related_source_id'] ?? 0) !== absint($batch_payload['source_id'] ?? 0)) {
					vms_pass_outreach_clear_business_batch_review($user_id);
					vms_pass_outreach_set_campaign_form_flash($user_id, array(
						'campaign_id' => 0,
						'payload' => vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
						'business_batch_form' => vms_pass_outreach_business_batch_form_payload($raw),
						'field_errors' => array('related_source_id' => __('The Source changed after batch review. Review the new batch settings again for the selected Source.', 'backstage-outreach')),
					));
					if (function_exists('vms_pass_claims_set_user_message')) {
						vms_pass_claims_set_user_message('error', __('The Source changed after batch review. Review the batch again before creating it.', 'backstage-outreach'));
					}
					wp_safe_redirect($business_source_setup_redirect_url);
					exit;
				}

				vms_pass_outreach_clear_business_batch_review($user_id);
				$created_batch = vms_pass_outreach_create_business_offer_batch($batch_payload, $user_id);
				if (is_wp_error($created_batch)) {
					vms_pass_outreach_set_business_batch_review($user_id, $review);
					vms_pass_outreach_set_campaign_form_flash($user_id, array(
						'campaign_id' => 0,
						'payload' => (array) ($review['form_payload'] ?? array()),
						'field_errors' => array(),
					));
					if (function_exists('vms_pass_claims_set_user_message')) {
						vms_pass_claims_set_user_message('error', $created_batch->get_error_message());
					}
					wp_safe_redirect($business_source_setup_redirect_url);
					exit;
				}

				$form_payload = (array) ($review['form_payload'] ?? array());
				$form_payload['recipient_source_mode'] = 'business_source';
				$form_payload['tracking_category_mode'] = 'existing';
				$form_payload['related_source_id'] = absint($created_batch['source_id'] ?? 0);
				$form_payload['related_batch_id'] = absint($created_batch['id'] ?? 0);
				$form_payload['business_admission_cap'] = absint($review['per_business_admission_cap'] ?? 0);
				vms_pass_outreach_set_campaign_form_flash($user_id, array(
					'campaign_id' => 0,
					'payload' => $form_payload,
					'field_errors' => array(),
				));
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('success', __('Offer batch created with zero generated individual claim links. Review the active businesses next.', 'backstage-outreach'));
				}
				wp_safe_redirect($business_review_action_redirect_url);
				exit;
			} finally {
				$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
			}
		}

		if ($save_mode === 'upload_map') {
			if ($campaign_id > 0) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Upload-first mapping is only available when starting a new outreach campaign.', 'backstage-outreach'));
				}
				wp_safe_redirect(function_exists('vms_pass_claims_admin_page_url') ? vms_pass_claims_admin_page_url(array(
					'tab' => vms_pass_outreach_tab_slug(),
					'campaign_id' => $campaign_id,
				)) : admin_url('admin.php?page=vms-passes'));
				exit;
			}

			$setup = vms_pass_outreach_sanitize_upload_first_campaign_setup($raw);
			if (is_wp_error($setup)) {
				$field_errors = vms_pass_outreach_field_errors_from_error($setup);
				if ($setup->get_error_code() === 'upload_preview_existing_batch') {
					$field_errors['related_batch_id'] = $setup->get_error_message();
				}
				vms_pass_outreach_clear_upload_preview($user_id);
				vms_pass_outreach_set_campaign_form_flash($user_id, array(
					'campaign_id' => 0,
					'payload' => vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
					'field_errors' => $field_errors,
				));
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $setup->get_error_message());
				}
				wp_safe_redirect($upload_flow_redirect_url);
				exit;
			}

			$parsed = function_exists('vms_pass_outreach_parse_uploaded_csv_for_mapping')
				? vms_pass_outreach_parse_uploaded_csv_for_mapping($file)
				: new WP_Error('recipient_import_unavailable', __('Outreach recipient mapping is unavailable.', 'backstage-outreach'));
			if (is_wp_error($parsed)) {
				vms_pass_outreach_clear_upload_preview($user_id);
				vms_pass_outreach_set_campaign_form_flash($user_id, array(
					'campaign_id' => 0,
					'payload' => vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
					'field_errors' => array(),
				));
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $parsed->get_error_message());
				}
				wp_safe_redirect($upload_flow_redirect_url);
				exit;
			}

			$form_payload = vms_pass_outreach_soft_campaign_payload_for_form($raw, 0);
			$selected_mapping = function_exists('vms_pass_outreach_normalize_selected_csv_mapping')
				? vms_pass_outreach_normalize_selected_csv_mapping((array) ($parsed['suggested_mapping'] ?? array()), (array) ($parsed['header_row'] ?? array()))
				: array();
			vms_pass_outreach_set_upload_mapping($user_id, array_merge($parsed, array(
				'form_payload' => $form_payload,
				'campaign_setup' => $setup,
				'selected_mapping' => $selected_mapping,
				'mapped_at' => function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql'),
			)));
			vms_pass_outreach_clear_upload_preview($user_id);

			if (function_exists('vms_admission_audit_log')) {
				vms_admission_audit_log(0, null, 'pass_outreach_upload_mapping', $user_id, 'admin', array(
					'campaign_name' => (string) ($setup['campaign_name'] ?? ''),
					'file_name' => (string) ($parsed['file_name'] ?? ''),
					'total_rows' => count((array) ($parsed['data_rows'] ?? array())),
				));
			}

			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('success', __('CSV uploaded. Review the detected columns before previewing the campaign.', 'backstage-outreach'));
			}

			wp_safe_redirect($upload_flow_redirect_url);
			exit;
		}

		if ($save_mode === 'upload_preview') {
			if ($campaign_id > 0) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Upload-first preview is only available when starting a new outreach campaign.', 'backstage-outreach'));
				}
				wp_safe_redirect(function_exists('vms_pass_claims_admin_page_url') ? vms_pass_claims_admin_page_url(array(
					'tab' => vms_pass_outreach_tab_slug(),
					'campaign_id' => $campaign_id,
				)) : admin_url('admin.php?page=vms-passes'));
				exit;
			}

			if ($has_uploaded_csv) {
				$setup = vms_pass_outreach_sanitize_upload_first_campaign_setup($raw);
				if (is_wp_error($setup)) {
					$field_errors = vms_pass_outreach_field_errors_from_error($setup);
					if ($setup->get_error_code() === 'upload_preview_existing_batch') {
						$field_errors['related_batch_id'] = $setup->get_error_message();
					}
					vms_pass_outreach_clear_upload_preview($user_id);
					vms_pass_outreach_set_campaign_form_flash($user_id, array(
						'campaign_id' => 0,
						'payload' => vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
						'field_errors' => $field_errors,
					));
						if (function_exists('vms_pass_claims_set_user_message')) {
							vms_pass_claims_set_user_message('error', $setup->get_error_message());
						}
						wp_safe_redirect($upload_flow_redirect_url);
						exit;
					}

				$parsed = function_exists('vms_pass_outreach_parse_uploaded_csv_for_mapping')
					? vms_pass_outreach_parse_uploaded_csv_for_mapping($file)
					: new WP_Error('recipient_import_unavailable', __('Outreach recipient mapping is unavailable.', 'backstage-outreach'));
				if (is_wp_error($parsed)) {
					vms_pass_outreach_clear_upload_preview($user_id);
					vms_pass_outreach_set_campaign_form_flash($user_id, array(
						'campaign_id' => 0,
						'payload' => vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
						'field_errors' => array(),
					));
						if (function_exists('vms_pass_claims_set_user_message')) {
							vms_pass_claims_set_user_message('error', $parsed->get_error_message());
						}
						wp_safe_redirect($upload_flow_redirect_url);
						exit;
					}

				$form_payload = vms_pass_outreach_soft_campaign_payload_for_form($raw, 0);
				$selected_mapping = function_exists('vms_pass_outreach_normalize_selected_csv_mapping')
					? vms_pass_outreach_normalize_selected_csv_mapping((array) ($parsed['suggested_mapping'] ?? array()), (array) ($parsed['header_row'] ?? array()))
					: array();
				vms_pass_outreach_set_upload_mapping($user_id, array_merge($parsed, array(
					'form_payload' => $form_payload,
					'campaign_setup' => $setup,
					'selected_mapping' => $selected_mapping,
					'mapped_at' => function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql'),
				)));
				vms_pass_outreach_clear_upload_preview($user_id);
					if (function_exists('vms_pass_claims_set_user_message')) {
						vms_pass_claims_set_user_message('success', __('New CSV uploaded. Review the detected columns before previewing the campaign.', 'backstage-outreach'));
					}
					wp_safe_redirect($upload_flow_redirect_url);
					exit;
				}

			$mapping_state = vms_pass_outreach_get_upload_mapping($user_id);

			$setup = vms_pass_outreach_sanitize_upload_first_campaign_setup($raw);
			if (is_wp_error($setup)) {
				$field_errors = vms_pass_outreach_field_errors_from_error($setup);
				if ($setup->get_error_code() === 'upload_preview_existing_batch') {
					$field_errors['related_batch_id'] = $setup->get_error_message();
				}
				if (!empty($mapping_state)) {
					$mapping_state['form_payload'] = vms_pass_outreach_soft_campaign_payload_for_form($raw, 0);
					vms_pass_outreach_set_upload_mapping($user_id, $mapping_state);
				}
				vms_pass_outreach_clear_upload_preview($user_id);
				vms_pass_outreach_set_campaign_form_flash($user_id, array(
					'campaign_id' => 0,
					'payload' => vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
					'field_errors' => $field_errors,
				));
					if (function_exists('vms_pass_claims_set_user_message')) {
						vms_pass_claims_set_user_message('error', $setup->get_error_message());
					}
					wp_safe_redirect($upload_flow_redirect_url);
					exit;
				}
			if (empty($mapping_state)) {
				vms_pass_outreach_clear_upload_preview($user_id);
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Upload preview expired or is missing. Upload the CSV and review mapping again before previewing.', 'backstage-outreach'));
				}
				wp_safe_redirect($upload_flow_redirect_url);
				exit;
			}

			$selected_mapping = isset($raw['csv_mapping']) && is_array($raw['csv_mapping'])
				? (array) $raw['csv_mapping']
				: (array) ($mapping_state['selected_mapping'] ?? array());
			$normalized_mapping = function_exists('vms_pass_outreach_normalize_selected_csv_mapping')
				? vms_pass_outreach_normalize_selected_csv_mapping($selected_mapping, (array) ($mapping_state['header_row'] ?? array()))
				: array();
			$mapping_state['form_payload'] = vms_pass_outreach_soft_campaign_payload_for_form($raw, 0);
			$mapping_state['campaign_setup'] = $setup;
			$mapping_state['selected_mapping'] = $normalized_mapping;
			vms_pass_outreach_set_upload_mapping($user_id, $mapping_state);

			$import_preview = function_exists('vms_pass_outreach_preview_import_from_parsed_csv')
				? vms_pass_outreach_preview_import_from_parsed_csv($mapping_state, $normalized_mapping)
				: new WP_Error('recipient_import_unavailable', __('Outreach recipient preview is unavailable.', 'backstage-outreach'));
			if (is_wp_error($import_preview)) {
				vms_pass_outreach_clear_upload_preview($user_id);
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $import_preview->get_error_message());
				}
				wp_safe_redirect($upload_flow_redirect_url);
				exit;
			}

			$ready_count = count((array) ($import_preview['prepared_rows'] ?? array()));
			$batch_preview = array(
				'batch_name' => sanitize_text_field((string) ($setup['campaign_name'] ?? '')),
				'quantity' => $ready_count,
				'admissions_per_link' => max(1, absint($setup['admissions_per_recipient'] ?? 1)),
				'total_admission_cap' => $ready_count * max(1, absint($setup['admissions_per_recipient'] ?? 1)),
			);
			$form_payload = array_merge(
				vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
				array(
					'total_admission_cap' => (int) ($batch_preview['total_admission_cap'] ?? 0),
				)
			);
			$mapping_state['form_payload'] = $form_payload;
			$mapping_state['campaign_setup'] = $setup;
			$mapping_state['selected_mapping'] = (array) ($import_preview['selected_mapping'] ?? $normalized_mapping);
			vms_pass_outreach_set_upload_mapping($user_id, $mapping_state);
			vms_pass_outreach_set_upload_preview($user_id, array(
				'preview_mode' => 'csv_new',
				'form_payload' => $form_payload,
				'campaign_setup' => $setup,
				'import_preview' => $import_preview,
				'batch_preview' => $batch_preview,
				'previewed_at' => function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql'),
			));

			if (function_exists('vms_admission_audit_log')) {
				vms_admission_audit_log(0, null, 'pass_outreach_upload_preview', $user_id, 'admin', array(
					'campaign_name' => (string) ($setup['campaign_name'] ?? ''),
					'recipient_count' => $ready_count,
					'valid_email_count' => (int) ($import_preview['valid_email_count'] ?? 0),
					'duplicate_count' => (int) ($import_preview['duplicate_count'] ?? 0),
					'failed_count' => (int) ($import_preview['failed_count'] ?? 0),
				));
			}

			if (function_exists('vms_pass_claims_set_user_message')) {
				if ($ready_count > 0) {
					vms_pass_claims_set_user_message(
						'success',
						sprintf(
							__('Upload preview ready. %1$d recipients are ready to import and VMS will auto-create %2$d total guest passes on confirmation.', 'backstage-outreach'),
							$ready_count,
							(int) ($batch_preview['total_admission_cap'] ?? 0)
						)
					);
				} else {
					vms_pass_claims_set_user_message('error', __('Upload preview loaded, but no valid unique recipients are ready to import. Review the skipped rows and re-upload a corrected CSV.', 'backstage-outreach'));
				}
			}

			wp_safe_redirect($csv_preview_redirect_url);
			exit;
		}

		if ($save_mode === 'existing_source_preview') {
			if ($campaign_id > 0) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Source list preview is only available when starting a new outreach campaign.', 'backstage-outreach'));
				}
				wp_safe_redirect(function_exists('vms_pass_claims_admin_page_url') ? vms_pass_claims_admin_page_url(array(
					'tab' => vms_pass_outreach_tab_slug(),
					'campaign_id' => $campaign_id,
				)) : admin_url('admin.php?page=vms-passes'));
				exit;
			}

			$setup = vms_pass_outreach_sanitize_upload_first_campaign_setup($raw);
			if (is_wp_error($setup)) {
				vms_pass_outreach_clear_upload_preview($user_id);
				vms_pass_outreach_set_campaign_form_flash($user_id, array(
					'campaign_id' => 0,
					'payload' => vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
					'field_errors' => vms_pass_outreach_field_errors_from_error($setup),
				));
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $setup->get_error_message());
				}
				wp_safe_redirect($upload_flow_redirect_url);
				exit;
			}

			$recipient_preview = vms_pass_outreach_build_existing_source_list_preview((int) ($setup['related_source_id'] ?? 0));
			if (is_wp_error($recipient_preview)) {
				vms_pass_outreach_clear_upload_preview($user_id);
				vms_pass_outreach_set_campaign_form_flash($user_id, array(
					'campaign_id' => 0,
					'payload' => vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
					'field_errors' => vms_pass_outreach_field_errors_from_error($recipient_preview),
				));
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $recipient_preview->get_error_message());
				}
				wp_safe_redirect($upload_flow_redirect_url);
				exit;
			}

			$ready_count = count((array) ($recipient_preview['prepared_rows'] ?? array()));
			$batch_preview = vms_pass_outreach_build_create_batch_preview($setup, $ready_count);
			$form_payload = array_merge(
				vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
				array('total_admission_cap' => (int) ($batch_preview['total_admission_cap'] ?? 0))
			);
			vms_pass_outreach_clear_upload_mapping($user_id);
			vms_pass_outreach_set_upload_preview($user_id, array(
				'preview_mode' => 'existing_source',
				'form_payload' => $form_payload,
				'campaign_setup' => $setup,
				'recipient_preview' => $recipient_preview,
				'batch_preview' => $batch_preview,
				'previewed_at' => function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql'),
			));
			if (function_exists('vms_pass_claims_set_user_message')) {
				if ($ready_count > 0) {
					vms_pass_claims_set_user_message(
						'success',
						sprintf(
							__('Source list preview ready. %1$d recipients are ready and VMS will reserve %2$d guest passes on confirmation.', 'backstage-outreach'),
							$ready_count,
							(int) ($batch_preview['total_admission_cap'] ?? 0)
						)
					);
				} else {
					vms_pass_claims_set_user_message('error', __('This source list preview did not produce any ready recipients. Choose a different list or refresh after updating the source.', 'backstage-outreach'));
				}
			}
			wp_safe_redirect($existing_source_preview_redirect_url);
			exit;
		}

		if ($save_mode === 'business_source_preview') {
			if ($campaign_id > 0) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Reusable business preview is only available when starting a new outreach campaign.', 'backstage-outreach'));
				}
				wp_safe_redirect(vms_pass_outreach_admin_page_url(array('campaign_id' => $campaign_id)));
				exit;
			}

			$setup = vms_pass_outreach_sanitize_upload_first_campaign_setup($raw, false);
			if (is_wp_error($setup)) {
				vms_pass_outreach_clear_upload_preview($user_id);
				vms_pass_outreach_set_campaign_form_flash($user_id, array(
					'campaign_id' => 0,
					'payload' => vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
					'field_errors' => vms_pass_outreach_field_errors_from_error($setup),
				));
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $setup->get_error_message());
				}
				wp_safe_redirect($business_source_setup_redirect_url);
				exit;
			}

			$business_preview = vms_pass_outreach_build_business_source_preview(
				(int) ($setup['related_source_id'] ?? 0),
				(int) ($setup['related_batch_id'] ?? 0)
			);
			if (is_wp_error($business_preview)) {
				vms_pass_outreach_clear_upload_preview($user_id);
				vms_pass_outreach_set_campaign_form_flash($user_id, array(
					'campaign_id' => 0,
					'payload' => vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
					'field_errors' => vms_pass_outreach_field_errors_from_error($business_preview),
				));
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $business_preview->get_error_message());
				}
				wp_safe_redirect($business_source_setup_redirect_url);
				exit;
			}

			$link_count = absint($business_preview['link_eligible_count'] ?? 0);
			$form_payload = array_merge(vms_pass_outreach_soft_campaign_payload_for_form($raw, 0), array(
				'admissions_per_recipient' => absint($setup['admissions_per_recipient'] ?? 1),
				'validity_type' => sanitize_key((string) ($setup['validity_type'] ?? 'any_event')),
				'single_event_plan_id' => absint($setup['single_event_plan_id'] ?? 0),
				'start_date' => sanitize_text_field((string) ($setup['start_date'] ?? '')),
				'end_date' => sanitize_text_field((string) ($setup['end_date'] ?? '')),
				'season_label' => sanitize_text_field((string) ($setup['season_label'] ?? '')),
				'expires_at' => sanitize_text_field((string) ($setup['expires_at'] ?? '')),
				'business_admission_cap' => absint($setup['business_admission_cap'] ?? 0),
			));
			vms_pass_outreach_clear_upload_mapping($user_id);
			vms_pass_outreach_set_upload_preview($user_id, array(
				'preview_mode' => 'business_source',
				'form_payload' => $form_payload,
				'campaign_setup' => $setup,
				'business_preview' => $business_preview,
				'batch_preview' => array(
					'business_link_count' => $link_count,
					'individual_claim_link_quantity' => absint($business_preview['individual_claim_link_quantity'] ?? 0),
					'total_admission_cap' => absint($business_preview['batch_total_admission_cap'] ?? 0),
				),
				'previewed_at' => function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql'),
			));
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message(
					$link_count > 0 ? 'success' : 'error',
					$link_count > 0
						? sprintf(_n('%d active business is ready for a reusable link review. No email is sent.', '%d active businesses are ready for reusable link review. No email is sent.', $link_count, 'backstage-outreach'), $link_count)
						: __('This Source has no active reusable businesses. Add or reactivate a business before creating the campaign.', 'backstage-outreach')
				);
			}
			wp_safe_redirect($business_source_preview_redirect_url);
			exit;
		}

		if ($save_mode === 'contacts_preview' || $save_mode === 'contacts_select') {
			if ($campaign_id > 0) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Contact selection is only available when starting a new outreach campaign.', 'backstage-outreach'));
				}
				wp_safe_redirect(function_exists('vms_pass_claims_admin_page_url') ? vms_pass_claims_admin_page_url(array(
					'tab' => vms_pass_outreach_tab_slug(),
					'campaign_id' => $campaign_id,
				)) : admin_url('admin.php?page=vms-passes'));
				exit;
			}

			$setup = vms_pass_outreach_sanitize_upload_first_campaign_setup($raw);
			if (is_wp_error($setup)) {
				vms_pass_outreach_clear_upload_preview($user_id);
				vms_pass_outreach_set_campaign_form_flash($user_id, array(
					'campaign_id' => 0,
					'payload' => vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
					'field_errors' => vms_pass_outreach_field_errors_from_error($setup),
				));
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $setup->get_error_message());
				}
				wp_safe_redirect($upload_flow_redirect_url);
				exit;
			}

			$recipient_preview = function_exists('vms_pass_outreach_build_contact_audience_create_preview')
				? vms_pass_outreach_build_contact_audience_create_preview($raw)
				: new WP_Error('contact_audience_unavailable', __('Outreach contacts are unavailable.', 'backstage-outreach'));
			if (is_wp_error($recipient_preview)) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $recipient_preview->get_error_message());
				}
				wp_safe_redirect($upload_flow_redirect_url);
				exit;
			}

			$selected_contact_ids = function_exists('vms_pass_outreach_selected_contact_audience_contact_ids')
				? vms_pass_outreach_selected_contact_audience_contact_ids($raw)
				: array();
			$selected_rows = !empty($selected_contact_ids) && function_exists('vms_pass_outreach_contact_audience_selected_prepared_rows')
				? vms_pass_outreach_contact_audience_selected_prepared_rows($recipient_preview, $selected_contact_ids)
				: array();

			if ($save_mode === 'contacts_select' && empty($selected_rows)) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Select at least one currently eligible contact, then click Add Selected Contacts again.', 'backstage-outreach'));
				}
				$selected_contact_ids = array();
			}

			$batch_preview = vms_pass_outreach_build_create_batch_preview($setup, count($selected_rows));
			$form_payload = array_merge(
				vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
				array('total_admission_cap' => (int) ($batch_preview['total_admission_cap'] ?? 0))
			);
			vms_pass_outreach_clear_upload_mapping($user_id);
			vms_pass_outreach_set_upload_preview($user_id, array(
				'preview_mode' => 'contacts',
				'form_payload' => $form_payload,
				'campaign_setup' => $setup,
				'recipient_preview' => $recipient_preview,
				'selected_contact_ids' => $selected_contact_ids,
				'selected_count' => count($selected_rows),
				'batch_preview' => $batch_preview,
				'previewed_at' => function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql'),
			));
			if (function_exists('vms_pass_claims_set_user_message')) {
				if ($save_mode === 'contacts_select' && !empty($selected_rows)) {
					vms_pass_claims_set_user_message(
						'success',
						sprintf(
							__('%1$d contacts selected. VMS will reserve %2$d guest passes when you create the campaign.', 'backstage-outreach'),
							count($selected_rows),
							(int) ($batch_preview['total_admission_cap'] ?? 0)
						)
					);
				} elseif (absint($recipient_preview['eligible_count'] ?? 0) > 0) {
					vms_pass_claims_set_user_message('success', __('Contact preview ready. Select the contacts you want, then click Add Selected Contacts.', 'backstage-outreach'));
				} else {
					vms_pass_claims_set_user_message('error', __('No eligible contacts matched this preview. Adjust the filters and try again.', 'backstage-outreach'));
				}
			}
			wp_safe_redirect($contacts_preview_redirect_url);
			exit;
		}

		if ($save_mode === 'refresh_message_preview') {
			if ($campaign_id > 0) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Message preview refresh is only available when starting a new outreach campaign.', 'backstage-outreach'));
				}
				wp_safe_redirect(function_exists('vms_pass_claims_admin_page_url') ? vms_pass_claims_admin_page_url(array(
					'tab' => vms_pass_outreach_tab_slug(),
					'campaign_id' => $campaign_id,
				)) : admin_url('admin.php?page=vms-passes'));
				exit;
			}

			$preview_state = vms_pass_outreach_get_upload_preview($user_id);
			$mapping_state = vms_pass_outreach_get_upload_mapping($user_id);
			$preview_mode = sanitize_key((string) ($preview_state['preview_mode'] ?? (!empty($preview_state['import_preview']) ? 'csv_new' : '')));
			$preview_redirect_url = vms_pass_outreach_create_preview_url($preview_mode);
			if ($preview_mode === 'business_source' || sanitize_key((string) ($raw['recipient_source_mode'] ?? '')) === 'business_source') {
				$preview_redirect_url = $business_source_setup_redirect_url;
			}
			$campaign_setup = isset($preview_state['campaign_setup']) && is_array($preview_state['campaign_setup']) ? $preview_state['campaign_setup'] : array();

			if (empty($preview_state) || empty($campaign_setup)) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Recipient preview is missing. Preview the source list before refreshing the message preview.', 'backstage-outreach'));
				}
				wp_safe_redirect($preview_redirect_url);
				exit;
			}

			$current_setup = vms_pass_outreach_sanitize_upload_first_campaign_setup($raw);
			if (is_wp_error($current_setup)) {
				$field_errors = vms_pass_outreach_field_errors_from_error($current_setup);
				if ($current_setup->get_error_code() === 'upload_preview_existing_batch') {
					$field_errors['related_batch_id'] = $current_setup->get_error_message();
				}
				if (!empty($preview_state)) {
					$preview_state['form_payload'] = vms_pass_outreach_soft_campaign_payload_for_form($raw, 0);
					vms_pass_outreach_set_upload_preview($user_id, $preview_state);
				}
				if (!empty($mapping_state)) {
					$mapping_state['form_payload'] = vms_pass_outreach_soft_campaign_payload_for_form($raw, 0);
					vms_pass_outreach_set_upload_mapping($user_id, $mapping_state);
				}
				vms_pass_outreach_set_campaign_form_flash($user_id, array(
					'campaign_id' => 0,
					'payload' => vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
					'field_errors' => $field_errors,
				));
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $current_setup->get_error_message());
				}
				wp_safe_redirect($review_redirect_url);
				exit;
			}

			if (vms_pass_outreach_create_preview_setup_snapshot($current_setup) !== vms_pass_outreach_create_preview_setup_snapshot($campaign_setup)) {
				$preserved_payload = array_merge(
					vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
					array('total_admission_cap' => (int) (($preview_state['batch_preview']['total_admission_cap'] ?? 0)))
				);
				$preview_state['form_payload'] = $preserved_payload;
				vms_pass_outreach_set_upload_preview($user_id, $preview_state);
				if (!empty($mapping_state)) {
					$mapping_state['form_payload'] = $preserved_payload;
					vms_pass_outreach_set_upload_mapping($user_id, $mapping_state);
				}
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Guest Pass Offer or recipient source changed after the last recipient preview. Refresh Recipient Preview before refreshing the message preview.', 'backstage-outreach'));
				}
				wp_safe_redirect($preview_redirect_url);
				exit;
			}

			$form_payload = array_merge(
				vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
				array('total_admission_cap' => (int) (($preview_state['batch_preview']['total_admission_cap'] ?? 0)))
			);
			$preview_state['form_payload'] = $form_payload;
			$preview_state['campaign_setup'] = $current_setup;
			$preview_state['message_previewed_at'] = function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql');
			vms_pass_outreach_set_upload_preview($user_id, $preview_state);
			if (!empty($mapping_state)) {
				$mapping_state['form_payload'] = $form_payload;
				$mapping_state['campaign_setup'] = $current_setup;
				vms_pass_outreach_set_upload_mapping($user_id, $mapping_state);
			}
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('success', __('Message preview refreshed. Recipient preview and list selection were preserved.', 'backstage-outreach'));
			}
			wp_safe_redirect($review_redirect_url);
			exit;
		}

		if ($save_mode === 'upload_commit' || $save_mode === 'recipient_commit') {
			if ($campaign_id > 0) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Upload-first batch creation is only available when starting a new outreach campaign.', 'backstage-outreach'));
				}
				wp_safe_redirect(function_exists('vms_pass_claims_admin_page_url') ? vms_pass_claims_admin_page_url(array(
					'tab' => vms_pass_outreach_tab_slug(),
					'campaign_id' => $campaign_id,
				)) : admin_url('admin.php?page=vms-passes'));
				exit;
			}

			$preview_state = vms_pass_outreach_get_upload_preview($user_id);
			$mapping_state = vms_pass_outreach_get_upload_mapping($user_id);
			$preview_mode = sanitize_key((string) ($preview_state['preview_mode'] ?? (!empty($preview_state['import_preview']) ? 'csv_new' : '')));
			$preview_redirect_url = vms_pass_outreach_create_preview_url($preview_mode);
			if ($preview_mode === 'business_source' || sanitize_key((string) ($raw['recipient_source_mode'] ?? '')) === 'business_source') {
				$preview_redirect_url = $business_source_setup_redirect_url;
			}
			$campaign_setup = isset($preview_state['campaign_setup']) && is_array($preview_state['campaign_setup']) ? $preview_state['campaign_setup'] : array();
			$import_preview = isset($preview_state['import_preview']) && is_array($preview_state['import_preview']) ? $preview_state['import_preview'] : array();
			$prepared_rows = array();
			if (empty($preview_state) || empty($campaign_setup)) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Recipient preview expired or is missing. Preview the source list again before creating the campaign.', 'backstage-outreach'));
				}
				wp_safe_redirect($preview_redirect_url);
				exit;
			}

			$current_setup = vms_pass_outreach_sanitize_upload_first_campaign_setup($raw);
			if (is_wp_error($current_setup)) {
				$field_errors = vms_pass_outreach_field_errors_from_error($current_setup);
				if ($current_setup->get_error_code() === 'upload_preview_existing_batch') {
					$field_errors['related_batch_id'] = $current_setup->get_error_message();
				}
				if (!empty($mapping_state)) {
					$mapping_state['form_payload'] = vms_pass_outreach_soft_campaign_payload_for_form($raw, 0);
					vms_pass_outreach_set_upload_mapping($user_id, $mapping_state);
				}
				vms_pass_outreach_set_campaign_form_flash($user_id, array(
					'campaign_id' => 0,
					'payload' => vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
					'field_errors' => $field_errors,
				));
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $current_setup->get_error_message());
				}
				wp_safe_redirect($preview_redirect_url);
				exit;
			}

			if (vms_pass_outreach_create_preview_setup_snapshot($current_setup) !== vms_pass_outreach_create_preview_setup_snapshot($campaign_setup)) {
				$preserved_payload = array_merge(
					vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
					array('total_admission_cap' => (int) (($preview_state['batch_preview']['total_admission_cap'] ?? 0)))
				);
				if (!empty($preview_state)) {
					$preview_state['form_payload'] = $preserved_payload;
					vms_pass_outreach_set_upload_preview($user_id, $preview_state);
				}
				if (!empty($mapping_state)) {
					$mapping_state['form_payload'] = $preserved_payload;
					$mapping_state['campaign_setup'] = $current_setup;
					vms_pass_outreach_set_upload_mapping($user_id, $mapping_state);
				}
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Guest Pass Offer or recipient source changed after the last preview. Refresh Recipient Preview before creating the campaign.', 'backstage-outreach'));
				}
				wp_safe_redirect($preview_redirect_url);
				exit;
			}

			if ($preview_mode === 'csv_new' && !empty($mapping_state)) {
				$posted_mapping = function_exists('vms_pass_outreach_normalize_selected_csv_mapping')
					? vms_pass_outreach_normalize_selected_csv_mapping((array) ($raw['csv_mapping'] ?? array()), (array) ($mapping_state['header_row'] ?? array()))
					: array();
				$preview_mapping = function_exists('vms_pass_outreach_normalize_selected_csv_mapping')
					? vms_pass_outreach_normalize_selected_csv_mapping((array) ($import_preview['selected_mapping'] ?? ($mapping_state['selected_mapping'] ?? array())), (array) ($mapping_state['header_row'] ?? array()))
					: array();
				if ($posted_mapping !== $preview_mapping) {
					$mapping_state['form_payload'] = vms_pass_outreach_soft_campaign_payload_for_form($raw, 0);
					$mapping_state['campaign_setup'] = $current_setup;
					$mapping_state['selected_mapping'] = $posted_mapping;
					vms_pass_outreach_set_upload_mapping($user_id, $mapping_state);
					vms_pass_outreach_set_campaign_form_flash($user_id, array(
						'campaign_id' => 0,
						'payload' => vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
						'field_errors' => array(),
					));
					vms_pass_outreach_clear_upload_preview($user_id);
					if (function_exists('vms_pass_claims_set_user_message')) {
						vms_pass_claims_set_user_message('error', __('Column mapping changed after the last preview. Preview the campaign again before creating it.', 'backstage-outreach'));
					}
					wp_safe_redirect($preview_redirect_url);
					exit;
				}
			}

			if ($preview_mode === 'csv_new') {
				$prepared_rows = isset($import_preview['prepared_rows']) && is_array($import_preview['prepared_rows']) ? $import_preview['prepared_rows'] : array();
				if (empty($prepared_rows)) {
					if (function_exists('vms_pass_claims_set_user_message')) {
						vms_pass_claims_set_user_message('error', __('The previewed CSV does not contain any valid unique recipients to import.', 'backstage-outreach'));
					}
					wp_safe_redirect($preview_redirect_url);
					exit;
				}
			} elseif ($preview_mode === 'existing_source') {
				$recipient_preview = vms_pass_outreach_build_existing_source_list_preview((int) ($campaign_setup['related_source_id'] ?? 0));
				if (is_wp_error($recipient_preview)) {
					vms_pass_outreach_clear_upload_preview($user_id);
					if (function_exists('vms_pass_claims_set_user_message')) {
						vms_pass_claims_set_user_message('error', $recipient_preview->get_error_message());
					}
					wp_safe_redirect($preview_redirect_url);
					exit;
				}
				$prepared_rows = isset($recipient_preview['prepared_rows']) && is_array($recipient_preview['prepared_rows']) ? $recipient_preview['prepared_rows'] : array();
				if (empty($prepared_rows)) {
					if (function_exists('vms_pass_claims_set_user_message')) {
						vms_pass_claims_set_user_message('error', __('The selected source list does not currently have any ready recipients to import.', 'backstage-outreach'));
					}
					wp_safe_redirect($preview_redirect_url);
					exit;
				}
			} elseif ($preview_mode === 'business_source') {
				$reviewed_business_preview = isset($preview_state['business_preview']) && is_array($preview_state['business_preview'])
					? $preview_state['business_preview']
					: array();
				$current_business_preview = vms_pass_outreach_build_business_source_preview(
					absint($campaign_setup['related_source_id'] ?? 0),
					absint($campaign_setup['related_batch_id'] ?? 0)
				);
				if (is_wp_error($current_business_preview)) {
					vms_pass_outreach_clear_upload_preview($user_id);
					if (function_exists('vms_pass_claims_set_user_message')) {
						vms_pass_claims_set_user_message('error', $current_business_preview->get_error_message());
					}
					wp_safe_redirect($preview_redirect_url);
					exit;
				}
				if (empty($reviewed_business_preview['membership_digest'])
					|| !hash_equals((string) $reviewed_business_preview['membership_digest'], (string) ($current_business_preview['membership_digest'] ?? ''))) {
					vms_pass_outreach_clear_upload_preview($user_id);
					if (function_exists('vms_pass_claims_set_user_message')) {
						vms_pass_claims_set_user_message('error', __('Active Source memberships changed after review. Refresh the business preview before creating the campaign.', 'backstage-outreach'));
					}
					wp_safe_redirect($preview_redirect_url);
					exit;
				}
				if (absint($current_business_preview['link_eligible_count'] ?? 0) <= 0) {
					if (function_exists('vms_pass_claims_set_user_message')) {
						vms_pass_claims_set_user_message('error', __('The selected Source has no active reusable businesses.', 'backstage-outreach'));
					}
					wp_safe_redirect($preview_redirect_url);
					exit;
				}
			} elseif ($preview_mode === 'contacts') {
				$stored_selected_contact_ids = function_exists('vms_pass_outreach_selected_contact_audience_contact_ids')
					? vms_pass_outreach_selected_contact_audience_contact_ids(array(
						'selected_contact_ids' => (array) ($preview_state['selected_contact_ids'] ?? array()),
					))
					: array();
				$current_selected_contact_ids = function_exists('vms_pass_outreach_selected_contact_audience_contact_ids')
					? vms_pass_outreach_selected_contact_audience_contact_ids($raw)
					: array();
				if ($current_selected_contact_ids !== $stored_selected_contact_ids) {
					vms_pass_outreach_set_campaign_form_flash($user_id, array(
						'campaign_id' => 0,
						'payload' => vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
						'field_errors' => array(),
					));
					vms_pass_outreach_clear_upload_preview($user_id);
					if (function_exists('vms_pass_claims_set_user_message')) {
						vms_pass_claims_set_user_message('error', __('Selected contacts changed after the last preview. Click Add Selected Contacts again before creating the campaign.', 'backstage-outreach'));
					}
					wp_safe_redirect($preview_redirect_url);
					exit;
				}
				$stored_filters = function_exists('vms_pass_outreach_normalize_contact_audience_filters')
					? vms_pass_outreach_normalize_contact_audience_filters((array) (($preview_state['recipient_preview']['filters'] ?? array())))
					: array();
				$current_filters = function_exists('vms_pass_outreach_normalize_contact_audience_filters')
					? vms_pass_outreach_normalize_contact_audience_filters($raw)
					: array();
				if ($current_filters !== $stored_filters) {
					vms_pass_outreach_set_campaign_form_flash($user_id, array(
						'campaign_id' => 0,
						'payload' => vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
						'field_errors' => array(),
					));
					vms_pass_outreach_clear_upload_preview($user_id);
					if (function_exists('vms_pass_claims_set_user_message')) {
						vms_pass_claims_set_user_message('error', __('Contact filters changed after the last preview. Refresh the preview and add selected contacts again before creating the campaign.', 'backstage-outreach'));
					}
					wp_safe_redirect($preview_redirect_url);
					exit;
				}
				$recipient_preview = function_exists('vms_pass_outreach_build_contact_audience_create_preview')
					? vms_pass_outreach_build_contact_audience_create_preview($current_filters)
					: new WP_Error('contact_audience_unavailable', __('Outreach contacts are unavailable.', 'backstage-outreach'));
				if (is_wp_error($recipient_preview)) {
					vms_pass_outreach_clear_upload_preview($user_id);
					if (function_exists('vms_pass_claims_set_user_message')) {
						vms_pass_claims_set_user_message('error', $recipient_preview->get_error_message());
					}
					wp_safe_redirect($preview_redirect_url);
					exit;
				}
				$prepared_rows = function_exists('vms_pass_outreach_contact_audience_selected_prepared_rows')
					? vms_pass_outreach_contact_audience_selected_prepared_rows($recipient_preview, $stored_selected_contact_ids)
					: array();
				if (empty($prepared_rows)) {
					if (function_exists('vms_pass_claims_set_user_message')) {
						vms_pass_claims_set_user_message('error', __('No selected contacts are ready to import. Refresh the preview and add selected contacts again.', 'backstage-outreach'));
					}
					wp_safe_redirect($preview_redirect_url);
					exit;
				}
			} else {
				vms_pass_outreach_clear_upload_preview($user_id);
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Recipient preview is invalid or expired. Preview the source list again before creating the campaign.', 'backstage-outreach'));
				}
				wp_safe_redirect($preview_redirect_url);
				exit;
			}

			if (vms_pass_outreach_create_message_preview_snapshot($current_setup) !== vms_pass_outreach_create_message_preview_snapshot($campaign_setup)) {
				$preserved_payload = array_merge(
					vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
					array('total_admission_cap' => (int) (($preview_state['batch_preview']['total_admission_cap'] ?? 0)))
				);
				if (!empty($preview_state)) {
					$preview_state['form_payload'] = $preserved_payload;
					vms_pass_outreach_set_upload_preview($user_id, $preview_state);
				}
				if (!empty($mapping_state)) {
					$mapping_state['form_payload'] = $preserved_payload;
					vms_pass_outreach_set_upload_mapping($user_id, $mapping_state);
				}
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Email subject or message changed after the last preview. Refresh Message Preview before creating the campaign.', 'backstage-outreach'));
				}
				wp_safe_redirect($review_redirect_url);
				exit;
			}

			if ($preview_mode === 'business_source') {
				$created = vms_pass_outreach_create_business_source_campaign(
					$current_setup,
					(array) ($preview_state['business_preview'] ?? array()),
					$user_id
				);
				if (is_wp_error($created)) {
					if (function_exists('vms_pass_claims_set_user_message')) {
						vms_pass_claims_set_user_message('error', $created->get_error_message());
					}
					wp_safe_redirect($preview_redirect_url);
					exit;
				}

				$created_campaign = (array) ($created['campaign'] ?? array());
				$created_preview = (array) ($created['business_preview'] ?? array());
				$campaign_id = absint($created_campaign['id'] ?? 0);
				vms_pass_outreach_clear_campaign_form_flash($user_id);
				vms_pass_outreach_clear_upload_preview($user_id);
				vms_pass_outreach_clear_upload_mapping($user_id);
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message(
						'success',
						sprintf(
							__('Business campaign created with %1$d reviewed active memberships and zero email recipients. Continue below to review and save the reusable links / QRs.', 'backstage-outreach'),
							absint($created_preview['link_eligible_count'] ?? 0)
						)
					);
				}
				$campaign_url = vms_pass_outreach_admin_page_url(array('campaign_id' => $campaign_id));
				wp_safe_redirect($campaign_url . '#backstage-outreach-partners');
				exit;
			}

			$campaign_setup = $current_setup;
			$source_resolution = vms_pass_outreach_resolve_tracking_category_request($campaign_setup, true, $user_id);
			if (is_wp_error($source_resolution)) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $source_resolution->get_error_message());
				}
				wp_safe_redirect($preview_redirect_url);
				exit;
			}
			$campaign_setup['related_source_id'] = absint($source_resolution['source_id'] ?? 0);
			$campaign_setup['tracking_category_mode'] = sanitize_key((string) ($source_resolution['tracking_category_mode'] ?? ($campaign_setup['tracking_category_mode'] ?? 'existing')));
			$campaign_setup['tracking_category_name'] = $campaign_setup['tracking_category_mode'] === 'new'
				? sanitize_text_field((string) ($source_resolution['source_name'] ?? ($campaign_setup['tracking_category_name'] ?? '')))
				: '';

			$batch_preview = vms_pass_outreach_build_auto_batch_payload($campaign_setup, count($prepared_rows));
			if (is_wp_error($batch_preview)) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $batch_preview->get_error_message());
				}
				wp_safe_redirect($preview_redirect_url);
				exit;
			}
			$batch_result = vms_pass_outreach_create_auto_batch($batch_preview, $user_id);
			if (is_wp_error($batch_result)) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $batch_result->get_error_message());
				}
				wp_safe_redirect($preview_redirect_url);
				exit;
			}

			$batch_id = absint($batch_result['batch_id'] ?? 0);
			$final_raw = array_merge($campaign_setup, array(
				'related_source_id' => absint($campaign_setup['related_source_id'] ?? 0),
				'related_batch_id' => $batch_id,
				'total_admission_cap' => (int) ($batch_preview['total_admission_cap'] ?? 0),
			));
			$payload = vms_pass_outreach_sanitize_campaign_payload($final_raw, 0);
			if (is_wp_error($payload)) {
				vms_pass_outreach_cleanup_auto_created_records(0, $batch_id);
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $payload->get_error_message());
				}
				wp_safe_redirect($preview_redirect_url);
				exit;
			}

			global $wpdb;
			$table = vms_admission_table_pass_outreach_campaigns();
			$now = function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql');
			$insert_data = $payload;
			$insert_data['created_by'] = $user_id;
			$insert_data['created_at'] = $now;
			$inserted = $wpdb->insert($table, $insert_data, vms_pass_outreach_campaign_db_formats($insert_data));
			if ($inserted === false) {
				vms_pass_outreach_cleanup_auto_created_records(0, $batch_id);
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Could not create outreach campaign.', 'backstage-outreach'));
				}
				wp_safe_redirect($preview_redirect_url);
				exit;
			}
			$campaign_id = (int) $wpdb->insert_id;
			$campaign = array_merge($payload, array('id' => $campaign_id));
			$imported = function_exists('vms_pass_outreach_insert_prepared_recipients')
				? vms_pass_outreach_insert_prepared_recipients($campaign, $prepared_rows)
				: new WP_Error('recipient_import_unavailable', __('Outreach recipient import is unavailable.', 'backstage-outreach'));
			if (is_wp_error($imported)) {
				vms_pass_outreach_cleanup_auto_created_records($campaign_id, $batch_id);
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $imported->get_error_message());
				}
				wp_safe_redirect($preview_redirect_url);
				exit;
			}

			if (function_exists('vms_admission_audit_log')) {
				vms_admission_audit_log(0, null, 'pass_outreach_campaign_create', $user_id, 'admin', array(
					'campaign_id' => $campaign_id,
					'campaign_name' => (string) ($payload['campaign_name'] ?? ''),
					'email_subject' => (string) ($payload['email_subject'] ?? ''),
					'related_source_id' => (int) ($payload['related_source_id'] ?? 0),
					'related_batch_id' => (int) ($payload['related_batch_id'] ?? 0),
					'status' => (string) ($payload['status'] ?? ''),
					'eligibility_mode' => (string) ($payload['eligibility_mode'] ?? ''),
					'validity_type' => (string) ($payload['validity_type'] ?? ''),
					'total_admission_cap' => (int) ($payload['total_admission_cap'] ?? 0),
					'admissions_per_recipient' => (int) ($payload['admissions_per_recipient'] ?? 1),
					'auto_created_batch_id' => $batch_id,
					'imported_count' => (int) ($imported['imported_count'] ?? 0),
				));
			}

			vms_pass_outreach_clear_campaign_form_flash($user_id);
			vms_pass_outreach_clear_upload_preview($user_id);
			vms_pass_outreach_clear_upload_mapping($user_id);
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message(
					'success',
					sprintf(
						__('Outreach campaign created. VMS auto-created batch #%1$d, reserved %2$d invite links, and imported %3$d recipients.', 'backstage-outreach'),
						$batch_id,
						(int) ($batch_preview['quantity'] ?? count($prepared_rows)),
						(int) ($imported['imported_count'] ?? 0)
					)
				);
			}

			wp_safe_redirect(function_exists('vms_pass_claims_admin_page_url') ? vms_pass_claims_admin_page_url(array(
				'tab' => vms_pass_outreach_tab_slug(),
				'campaign_id' => $campaign_id,
			)) : admin_url('admin.php?page=vms-passes'));
			exit;
		}

		if ($campaign_id <= 0 && (absint($raw['related_batch_id'] ?? 0) <= 0 || vms_pass_outreach_has_tracking_category_request($raw))) {
			$source_resolution = vms_pass_outreach_resolve_tracking_category_request($raw, true, $user_id);
			if (is_wp_error($source_resolution)) {
				vms_pass_outreach_set_campaign_form_flash($user_id, array(
					'campaign_id' => $campaign_id,
					'payload' => vms_pass_outreach_soft_campaign_payload_for_form($raw, $campaign_id),
					'field_errors' => vms_pass_outreach_field_errors_from_error($source_resolution),
				));
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $source_resolution->get_error_message());
				}
				$args = array('tab' => vms_pass_outreach_tab_slug());
				if ($campaign_id > 0) {
					$args['campaign_id'] = $campaign_id;
				}
				wp_safe_redirect(function_exists('vms_pass_claims_admin_page_url') ? vms_pass_claims_admin_page_url($args) : admin_url('admin.php?page=vms-passes'));
				exit;
			}
			$raw['related_source_id'] = absint($source_resolution['source_id'] ?? 0);
		}

		$payload = vms_pass_outreach_sanitize_campaign_payload($raw, $campaign_id);
		if (is_wp_error($payload)) {
			vms_pass_outreach_set_campaign_form_flash($user_id, array(
				'campaign_id' => $campaign_id,
				'payload' => vms_pass_outreach_soft_campaign_payload_for_form($raw, $campaign_id),
				'field_errors' => vms_pass_outreach_field_errors_from_error($payload),
			));
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', $payload->get_error_message());
			}
			$args = array('tab' => vms_pass_outreach_tab_slug());
			if ($campaign_id > 0) {
				$args['campaign_id'] = $campaign_id;
			}
			wp_safe_redirect(function_exists('vms_pass_claims_admin_page_url') ? vms_pass_claims_admin_page_url($args) : admin_url('admin.php?page=vms-passes'));
			exit;
		}

		global $wpdb;
		$table = vms_admission_table_pass_outreach_campaigns();
		$now = function_exists('vms_admission_now_mysql') ? vms_admission_now_mysql() : current_time('mysql');

		if ($campaign_id > 0) {
			$update_data = $payload;
			$update_data['updated_by'] = $user_id;
			$update_data['updated_at'] = $now;
			$updated = $wpdb->update($table, $update_data, array('id' => $campaign_id), vms_pass_outreach_campaign_db_formats($update_data), array('%d'));
			if ($updated === false) {
				vms_pass_outreach_set_campaign_form_flash($user_id, array(
					'campaign_id' => $campaign_id,
					'payload' => vms_pass_outreach_soft_campaign_payload_for_form($raw, $campaign_id),
					'field_errors' => array(),
				));
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Could not update outreach campaign.', 'backstage-outreach'));
				}
				wp_safe_redirect(function_exists('vms_pass_claims_admin_page_url') ? vms_pass_claims_admin_page_url(array(
					'tab' => vms_pass_outreach_tab_slug(),
					'campaign_id' => $campaign_id,
				)) : admin_url('admin.php?page=vms-passes'));
				exit;
			}
		} else {
			$insert_data = $payload;
			$insert_data['created_by'] = $user_id;
			$insert_data['created_at'] = $now;
			$inserted = $wpdb->insert($table, $insert_data, vms_pass_outreach_campaign_db_formats($insert_data));
			if ($inserted === false) {
				vms_pass_outreach_set_campaign_form_flash($user_id, array(
					'campaign_id' => 0,
					'payload' => vms_pass_outreach_soft_campaign_payload_for_form($raw, 0),
					'field_errors' => array(),
				));
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Could not create outreach campaign.', 'backstage-outreach'));
				}
				wp_safe_redirect(function_exists('vms_pass_claims_admin_page_url') ? vms_pass_claims_admin_page_url(array('tab' => vms_pass_outreach_tab_slug())) : admin_url('admin.php?page=vms-passes'));
				exit;
			}
			$campaign_id = (int) $wpdb->insert_id;
		}

		if (function_exists('vms_admission_audit_log')) {
			vms_admission_audit_log(0, null, $campaign_id > 0 && !empty($raw['campaign_id']) ? 'pass_outreach_campaign_update' : 'pass_outreach_campaign_create', $user_id, 'admin', array(
				'campaign_id' => $campaign_id,
				'campaign_name' => (string) ($payload['campaign_name'] ?? ''),
				'email_subject' => (string) ($payload['email_subject'] ?? ''),
				'related_source_id' => (int) ($payload['related_source_id'] ?? 0),
				'related_batch_id' => (int) ($payload['related_batch_id'] ?? 0),
				'status' => (string) ($payload['status'] ?? ''),
				'eligibility_mode' => (string) ($payload['eligibility_mode'] ?? ''),
				'validity_type' => (string) ($payload['validity_type'] ?? ''),
				'total_admission_cap' => (int) ($payload['total_admission_cap'] ?? 0),
				'admissions_per_recipient' => (int) ($payload['admissions_per_recipient'] ?? 1),
			));
		}

		vms_pass_outreach_clear_upload_preview($user_id);
		vms_pass_outreach_clear_upload_mapping($user_id);
		vms_pass_outreach_clear_campaign_form_flash($user_id);
		if (function_exists('vms_pass_claims_set_user_message')) {
			vms_pass_claims_set_user_message('success', __('Outreach campaign saved.', 'backstage-outreach'));
		}

		wp_safe_redirect(function_exists('vms_pass_claims_admin_page_url') ? vms_pass_claims_admin_page_url(array(
			'tab' => vms_pass_outreach_tab_slug(),
			'campaign_id' => $campaign_id,
		)) : admin_url('admin.php?page=vms-passes'));
		exit;
	}
}
add_action('admin_post_vms_pass_outreach_campaign_save', 'vms_pass_outreach_handle_campaign_save');

if (!function_exists('vms_pass_outreach_usage_summary')) {
	function vms_pass_outreach_usage_summary(array $campaign): array
	{
		global $wpdb;

		$entries_table = vms_admission_table_entries();
		$related_batch_id = absint($campaign['related_batch_id'] ?? 0);
		$related_source_id = absint($campaign['related_source_id'] ?? 0);
		if ($related_batch_id <= 0 && $related_source_id <= 0) {
			return array(
				'entries_count' => 0,
				'headcount' => 0,
			);
		}

		if ($related_batch_id > 0) {
			$sql = "SELECT COUNT(1) AS entries_count, COALESCE(SUM(party_size), 0) AS headcount
				FROM {$entries_table}
				WHERE pass_batch_id = %d
					AND status <> 'canceled'";
			$row = $wpdb->get_row($wpdb->prepare($sql, $related_batch_id), ARRAY_A);
		} else {
			$sql = "SELECT COUNT(1) AS entries_count, COALESCE(SUM(party_size), 0) AS headcount
				FROM {$entries_table}
				WHERE pass_source_id = %d
					AND status <> 'canceled'";
			$row = $wpdb->get_row($wpdb->prepare($sql, $related_source_id), ARRAY_A);
		}

		return array(
			'entries_count' => isset($row['entries_count']) ? absint($row['entries_count']) : 0,
			'headcount' => isset($row['headcount']) ? absint($row['headcount']) : 0,
		);
	}
}

if (!function_exists('vms_pass_outreach_scope_summary')) {
	function vms_pass_outreach_scope_summary(array $campaign): string
	{
		$validity_type = sanitize_key((string) ($campaign['validity_type'] ?? 'batch_default'));
		if ($validity_type === 'batch_default') {
			return __('Uses linked batch scope', 'backstage-outreach');
		}

		if ($validity_type === 'single_event') {
			$event = function_exists('vms_pass_claims_get_event_plan_brief')
				? vms_pass_claims_get_event_plan_brief((int) ($campaign['single_event_plan_id'] ?? 0))
				: null;
			if (is_array($event)) {
				$label = (string) ($event['title'] ?? '');
				if (!empty($event['event_date'])) {
					$label .= ' (' . (string) $event['event_date'] . ')';
				}
				return $label;
			}
			return __('Single event', 'backstage-outreach');
		}

		if ($validity_type === 'date_range') {
			return trim((string) ($campaign['start_date'] ?? '') . ' - ' . (string) ($campaign['end_date'] ?? ''));
		}

		if ($validity_type === 'season') {
			$season_label = sanitize_text_field((string) ($campaign['season_label'] ?? ''));
			$date_label = trim((string) ($campaign['start_date'] ?? '') . ' - ' . (string) ($campaign['end_date'] ?? ''));
			if ($season_label !== '' && $date_label !== '') {
				return $season_label . ' (' . $date_label . ')';
			}
			if ($season_label !== '') {
				return $season_label;
			}
			if ($date_label !== '') {
				return $date_label;
			}
			return __('Season', 'backstage-outreach');
		}

		return __('Any published future event', 'backstage-outreach');
	}
}

if (!function_exists('vms_pass_outreach_render_outreach_tab')) {
	function vms_pass_outreach_render_outreach_tab(): void
	{
		$user_id = get_current_user_id();
		$page_slug = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : vms_outreach_admin_menu_slug();
		$campaign_id = isset($_GET['campaign_id']) ? absint((string) $_GET['campaign_id']) : 0;
		$campaign = $campaign_id > 0 ? vms_pass_outreach_get_campaign_by_id($campaign_id) : null;
		$form_payload = is_array($campaign) ? $campaign : vms_pass_outreach_default_campaign_payload();
		$field_errors = array();
		$business_batch_form = array();
		$business_batch_review = $campaign_id <= 0 ? vms_pass_outreach_get_business_batch_review($user_id) : array();
		$upload_mapping = $campaign_id <= 0 ? vms_pass_outreach_get_upload_mapping($user_id) : array();
		$upload_preview = $campaign_id <= 0 ? vms_pass_outreach_get_upload_preview($user_id) : array();
		if ($campaign_id <= 0 && !empty($upload_mapping['form_payload']) && is_array($upload_mapping['form_payload'])) {
			$form_payload = array_merge($form_payload, vms_pass_outreach_normalize_campaign_row((array) $upload_mapping['form_payload']));
		}
		if ($campaign_id <= 0 && !empty($upload_preview['form_payload']) && is_array($upload_preview['form_payload'])) {
			$form_payload = array_merge($form_payload, vms_pass_outreach_normalize_campaign_row((array) $upload_preview['form_payload']));
		}
		$flash = vms_pass_outreach_pull_campaign_form_flash($user_id);
		$flash_campaign_id = absint($flash['campaign_id'] ?? 0);
		if ($flash_campaign_id === $campaign_id) {
			if (!empty($flash['payload']) && is_array($flash['payload'])) {
				$form_payload = array_merge($form_payload, vms_pass_outreach_normalize_campaign_row((array) $flash['payload']));
			}
			if (!empty($flash['field_errors']) && is_array($flash['field_errors'])) {
				$field_errors = array_map('sanitize_text_field', (array) $flash['field_errors']);
			}
			if (!empty($flash['business_batch_form']) && is_array($flash['business_batch_form'])) {
				$business_batch_form = vms_pass_outreach_business_batch_form_payload((array) $flash['business_batch_form']);
			}
		}
		$campaign_catalog = vms_outreach_purpose_catalog();
		$live_campaign_purpose = vms_outreach_default_campaign_purpose();
		$live_purpose_definition = isset($campaign_catalog[$live_campaign_purpose]) && is_array($campaign_catalog[$live_campaign_purpose])
			? $campaign_catalog[$live_campaign_purpose]
			: array(
				'label' => __('Guest Pass Invitation', 'backstage-outreach'),
				'description' => __('Import recipients, reserve invite links, preview merge tags, export send-prep CSVs, and track claims/check-ins.', 'backstage-outreach'),
				'available' => true,
			);
		$planned_purpose_definitions = array();
		foreach ($campaign_catalog as $purpose_key => $purpose_definition) {
			if ($purpose_key === $live_campaign_purpose) {
				continue;
			}
			$planned_purpose_definitions[$purpose_key] = is_array($purpose_definition) ? $purpose_definition : array();
		}
		$campaign_purpose = vms_outreach_normalize_campaign_purpose((string) vms_pass_outreach_payload_value($form_payload, 'campaign_purpose', vms_outreach_default_campaign_purpose()));
		$form_payload['campaign_purpose'] = $campaign_purpose;
		$campaign_guardrails = is_array($campaign) && function_exists('vms_pass_outreach_campaign_edit_guardrails')
			? vms_pass_outreach_campaign_edit_guardrails($campaign)
			: array(
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
		$campaign_delivery_summary = is_array($campaign) && function_exists('vms_pass_outreach_campaign_summary')
			? vms_pass_outreach_campaign_summary($campaign)
			: array();
		$campaign_display_status = is_array($campaign) && function_exists('vms_pass_outreach_campaign_display_status')
			? vms_pass_outreach_campaign_display_status($campaign, $campaign_delivery_summary)
			: array(
				'key' => sanitize_key((string) ($form_payload['status'] ?? 'draft')),
				'label' => (string) (vms_pass_outreach_status_labels()[(string) ($form_payload['status'] ?? 'draft')] ?? ($form_payload['status'] ?? 'draft')),
				'variant' => sanitize_key((string) ($form_payload['status'] ?? 'draft')),
			);
		$confirm_forward_only_checked = !empty(vms_pass_outreach_payload_value($form_payload, 'confirm_forward_only_changes', 0));
		$campaign_status_filter = isset($_GET['campaign_status']) ? sanitize_key((string) wp_unslash($_GET['campaign_status'])) : '';
		if (!in_array($campaign_status_filter, vms_pass_outreach_allowed_statuses(), true)) {
			$campaign_status_filter = '';
		}
		$campaign_purpose_filter = isset($_GET['campaign_purpose']) ? sanitize_key((string) wp_unslash($_GET['campaign_purpose'])) : '';
		if ($campaign_purpose_filter !== '' && !isset($campaign_catalog[$campaign_purpose_filter])) {
			$campaign_purpose_filter = '';
		}
		$sources = function_exists('vms_pass_claims_get_sources') ? vms_pass_claims_get_sources(true) : array();
		$batches = function_exists('vms_pass_claims_get_batches') ? vms_pass_claims_get_batches(400) : array();
		$business_batch_catalog = vms_pass_outreach_business_batch_catalog((array) $batches);
		$event_plans = function_exists('vms_pass_claims_get_published_event_plans') ? vms_pass_claims_get_published_event_plans(300) : array();
		$campaigns = vms_pass_outreach_get_campaigns(array(
			'limit' => 250,
			'status' => $campaign_status_filter,
			'campaign_purpose' => $campaign_purpose_filter,
		));
		$source_tab_url = function_exists('vms_pass_claims_admin_page_url')
			? vms_pass_claims_admin_page_url(array('tab' => 'sources'))
			: admin_url('admin.php?page=vms-passes');
		$batches_tab_url = function_exists('vms_pass_claims_admin_page_url')
			? vms_pass_claims_admin_page_url(array('tab' => 'batches'))
			: admin_url('admin.php?page=vms-passes');
		$batch_placeholder = absint($form_payload['related_batch_id'] ?? 0) > 0
			? __('Change batch / invite link pool', 'backstage-outreach')
			: __('Select batch / invite link pool', 'backstage-outreach');
		$hidden_attr = static function (bool $hidden): string {
			return $hidden ? ' hidden' : '';
		};
		$help_index = 0;
		$render_help = static function (string $label, string $message) use (&$help_index): string {
			$message = trim($message);
			if ($message === '') {
				return '';
			}
			$help_index++;
			$tooltip_id = 'vms-pass-help-' . $help_index;
			return '<span class="vms-pass-help"><button type="button" class="vms-pass-help__toggle" aria-describedby="' . esc_attr($tooltip_id) . '" aria-expanded="false" aria-label="' . esc_attr(sprintf(__('More information about %s', 'backstage-outreach'), $label)) . '"><span aria-hidden="true">i</span></button><span id="' . esc_attr($tooltip_id) . '" class="vms-pass-help__popover" role="tooltip">' . esc_html($message) . '</span></span>';
		};
		$render_label = static function (string $label, array $args = array()) use ($render_help): string {
			$required = !empty($args['required']);
			$help = isset($args['help']) ? (string) $args['help'] : '';
			$html = '<span class="vms-pass-field-label-row">';
			$html .= '<span class="vms-pass-field-label">' . esc_html($label);
			if ($required) {
				$html .= ' <span class="vms-pass-required-marker" aria-hidden="true">*</span><span class="screen-reader-text">' . esc_html__('Required', 'backstage-outreach') . '</span>';
			}
			$html .= '</span>';
			$html .= $render_help($label, $help);
			$html .= '</span>';
			return $html;
		};
		$render_messages = static function (array $errors, array $keys = array()): string {
			return vms_pass_outreach_render_field_messages($errors, $keys, '');
		};
		$quick_popover_index = 0;
		$render_quick_popover_toggle = static function (string $label, string $button_classes, string $popover_html, string $aria_label, string $wrapper_classes = '') use (&$quick_popover_index): string {
			$label = sanitize_text_field($label);
			$button_classes = trim(preg_replace('/\s+/', ' ', $button_classes));
			$wrapper_classes = trim('vms-pass-help ' . $wrapper_classes);
			$popover_html = trim($popover_html);
			if ($label === '' || $popover_html === '') {
				return '<span>' . esc_html($label) . '</span>';
			}

			$quick_popover_index++;
			$tooltip_id = 'vms-pass-quick-popover-' . $quick_popover_index;

			return '<span class="' . esc_attr($wrapper_classes) . '" data-vms-quick-popover="1"><button type="button" class="' . esc_attr($button_classes) . '" data-vms-quick-popover-toggle="1" aria-describedby="' . esc_attr($tooltip_id) . '" aria-expanded="false" aria-label="' . esc_attr($aria_label) . '">' . esc_html($label) . '</button><span id="' . esc_attr($tooltip_id) . '" class="screen-reader-text">' . esc_html(wp_strip_all_tags($popover_html)) . '</span><span class="vms-pass-help__popover-source" data-vms-quick-popover-content="1" aria-hidden="true">' . wp_kses_post($popover_html) . '</span></span>';
		};
		$render_status_pill = static function (array $status_state) use ($render_quick_popover_toggle): string {
			$label = sanitize_text_field((string) ($status_state['label'] ?? ''));
			$variant = sanitize_html_class((string) ($status_state['variant'] ?? ($status_state['key'] ?? 'draft')));
			$tooltip = trim((string) ($status_state['tooltip'] ?? ''));
			$popover_html = trim((string) ($status_state['popover_html'] ?? ''));
			if ($label === '') {
				$label = sanitize_text_field((string) ($status_state['key'] ?? __('Status', 'backstage-outreach')));
			}
			if ($popover_html === '' && $tooltip !== '') {
				$popover_html = '<p class="vms-pass-floating-popover__eyebrow">' . esc_html__('Status', 'backstage-outreach') . '</p><p class="vms-pass-floating-popover__counts"><strong>' . esc_html($label) . '</strong></p><p class="vms-pass-floating-popover__next-step">' . esc_html($tooltip) . '</p>';
			}
			if ($popover_html === '') {
				return '<span class="vms-pass-status-pill is-' . esc_attr($variant) . '">' . esc_html($label) . '</span>';
			}
			return $render_quick_popover_toggle(
				$label,
				'vms-pass-help__toggle vms-pass-status-pill is-' . $variant . ' has-detail',
				$popover_html,
				sprintf(__('Show status details for %s', 'backstage-outreach'), $label),
				'vms-pass-help--pill'
			);
		};
		$campaign_next_action_message = static function (array $summary): string {
			$queued_recipients = absint($summary['queued_recipients'] ?? 0);
			$failed_recipients = absint($summary['failed_recipients'] ?? 0);
			if ($failed_recipients > 0) {
				$message = sprintf(_n('%d invite needs attention.', '%d invites need attention.', $failed_recipients, 'backstage-outreach'), $failed_recipients);
				if ($queued_recipients > 0) {
					$message .= ' ' . sprintf(_n('%d invite is queued and waiting to send.', '%d invites are queued and waiting to send.', $queued_recipients, 'backstage-outreach'), $queued_recipients);
				}

				return $message;
			}
			if ($queued_recipients > 0) {
				return sprintf(_n('%d invite is queued and waiting to send.', '%d invites are queued and waiting to send.', $queued_recipients, 'backstage-outreach'), $queued_recipients);
			}

			return __('No invites are currently queued.', 'backstage-outreach');
		};
		$render_purpose_pill = static function (string $purpose): string {
			$label = (string) (vms_outreach_purpose_labels()[$purpose] ?? $purpose);
			return '<span class="vms-pass-purpose-pill">' . esc_html($label) . '</span>';
		};
		$preview_context = function_exists('vms_pass_outreach_preview_context')
			? vms_pass_outreach_preview_context($form_payload)
			: array(
				'source_label' => __('Sample data', 'backstage-outreach'),
				'recipient' => array(),
			);
		$preview_recipient = isset($preview_context['recipient']) && is_array($preview_context['recipient']) ? $preview_context['recipient'] : array();
		$preview_context_label = sanitize_text_field((string) ($preview_context['source_label'] ?? __('Sample data', 'backstage-outreach')));
		$preview_subject = function_exists('vms_pass_outreach_build_invite_subject')
			? vms_pass_outreach_build_invite_subject($preview_recipient, $form_payload)
			: (string) vms_pass_outreach_payload_value($form_payload, 'email_subject', vms_pass_outreach_default_email_subject());
		$preview_message = function_exists('vms_pass_outreach_build_invite_message')
			? vms_pass_outreach_build_invite_message($preview_recipient, $form_payload)
			: (string) vms_pass_outreach_payload_value($form_payload, 'message_template', vms_pass_outreach_default_message_template());
		$merge_tag_help_text = '';
		if (function_exists('vms_pass_outreach_supported_merge_tags')) {
			$merge_tags = array_map(static function (string $tag): string {
				return '{' . $tag . '}';
			}, vms_pass_outreach_supported_merge_tags());
			$merge_tag_help_text = sprintf(
				/* translators: %s: comma-separated merge tags */
				__('Use merge tags like %s. Unknown tags are removed when messages are rendered.', 'backstage-outreach'),
				implode(', ', $merge_tags)
			);
		}
		$upload_import_preview = isset($upload_preview['import_preview']) && is_array($upload_preview['import_preview']) ? $upload_preview['import_preview'] : array();
		$upload_batch_preview = isset($upload_preview['batch_preview']) && is_array($upload_preview['batch_preview']) ? $upload_preview['batch_preview'] : array();
		$upload_mapping_headers = array_values(array_map('sanitize_text_field', (array) ($upload_mapping['header_row'] ?? array())));
		$upload_mapping_samples = array_values(array_map('sanitize_text_field', (array) ($upload_mapping['sample_values'] ?? array())));
		$upload_mapping_selected = function_exists('vms_pass_outreach_normalize_selected_csv_mapping')
			? vms_pass_outreach_normalize_selected_csv_mapping((array) ($upload_mapping['selected_mapping'] ?? array()), $upload_mapping_headers)
			: array();
		$upload_mapping_options = function_exists('vms_pass_outreach_import_mapping_options')
			? vms_pass_outreach_import_mapping_options()
			: array();
		$upload_has_mapping = !empty($upload_mapping_headers);
		$upload_mapping_file_name = sanitize_file_name((string) ($upload_mapping['file_name'] ?? ''));
		$upload_mapping_total_rows = count((array) ($upload_mapping['data_rows'] ?? array()));
		$upload_preview_sample_rows = array_values((array) ($upload_import_preview['sample_rows'] ?? array()));
		$upload_ready_count = absint($upload_import_preview['ready_count'] ?? 0);
		$upload_valid_email_count = absint($upload_import_preview['valid_email_count'] ?? 0);
		$upload_total_rows = absint($upload_import_preview['total_rows'] ?? 0);
		$upload_total_passes = absint($upload_batch_preview['total_admission_cap'] ?? 0);
		$upload_failed_rows = array_values((array) ($upload_import_preview['failed_rows'] ?? array()));
		$upload_failed_labels = array();
		foreach (array_slice($upload_failed_rows, 0, 5) as $failed_row) {
			$upload_failed_labels[] = sprintf(
				__('row %1$d: %2$s', 'backstage-outreach'),
				absint($failed_row['row_number'] ?? 0),
				sanitize_text_field((string) ($failed_row['reason'] ?? ''))
			);
		}
		if (count($upload_failed_rows) > count($upload_failed_labels)) {
			$upload_failed_labels[] = sprintf(__('and %d more', 'backstage-outreach'), count($upload_failed_rows) - count($upload_failed_labels));
			}
		$upload_has_preview = !empty($upload_preview);
		$create_preview_mode = sanitize_key((string) ($upload_preview['preview_mode'] ?? (!empty($upload_import_preview) ? 'csv_new' : '')));
		if (!in_array($create_preview_mode, vms_pass_outreach_allowed_recipient_source_modes(), true)) {
			$create_preview_mode = $upload_has_preview ? 'csv_new' : '';
		}
		$upload_map_button_label = $upload_has_mapping
			? __('Upload New CSV', 'backstage-outreach')
			: __('Review Mapping', 'backstage-outreach');
		$upload_map_button_class = $upload_has_mapping
			? 'button'
			: 'button button-primary';
		$upload_preview_button_label = ($create_preview_mode === 'csv_new' && !empty($upload_preview))
			? __('Refresh Preview', 'backstage-outreach')
			: __('Preview Campaign', 'backstage-outreach');
		$upload_preview_button_class = ($create_preview_mode === 'csv_new' && $upload_ready_count > 0)
			? 'button'
			: 'button button-primary';
		$has_sources = false;
		foreach ((array) $sources as $source) {
			if (absint($source['id'] ?? 0) > 0) {
				$has_sources = true;
				break;
			}
		}
		$contacts_mode_available = function_exists('vms_outreach_get_contacts')
			&& function_exists('vms_pass_outreach_build_contact_audience_create_preview')
			&& function_exists('vms_pass_outreach_contact_audience_status_filter_options')
			&& function_exists('vms_pass_outreach_normalize_contact_audience_filters');
		$is_create_screen = $campaign_id <= 0;
		if ($is_create_screen) {
			$campaign_purpose = $live_campaign_purpose;
			$form_payload['campaign_purpose'] = $campaign_purpose;
		}
		$workflow_mode = 'upload_first';
		$recipient_source_mode = $is_create_screen
			? vms_pass_outreach_normalize_recipient_source_mode($form_payload, $has_sources)
			: '';
		if ($recipient_source_mode === 'contacts' && !$contacts_mode_available) {
			$recipient_source_mode = 'csv_new';
		}
		if ($create_preview_mode !== '' && $create_preview_mode !== $recipient_source_mode) {
			$create_preview_mode = '';
		}
		$tracking_category_mode = $is_create_screen
			? (in_array($recipient_source_mode, array('existing_source', 'business_source'), true) ? 'existing' : 'new')
			: sanitize_key((string) vms_pass_outreach_payload_value($form_payload, 'tracking_category_mode', $has_sources ? 'existing' : 'new'));
		if (!$is_create_screen) {
			if (!$has_sources) {
				$tracking_category_mode = 'new';
			}
			if (!in_array($tracking_category_mode, vms_pass_outreach_allowed_tracking_category_modes(), true)) {
				$tracking_category_mode = $has_sources ? 'existing' : 'new';
			}
		}
		$form_payload['workflow_mode'] = $workflow_mode;
		if ($is_create_screen) {
			$form_payload['recipient_source_mode'] = $recipient_source_mode;
			$form_payload['tracking_category_mode'] = $tracking_category_mode;
		}
		$selected_business_source_id = $recipient_source_mode === 'business_source'
			? absint(vms_pass_outreach_payload_value($form_payload, 'related_source_id', 0))
			: 0;
		$eligible_business_batches = vms_pass_outreach_eligible_business_batches((array) $batches, $selected_business_source_id);
		$eligible_business_batch_ids = array_fill_keys(array_map(static function (array $batch): int {
			return absint($batch['id'] ?? 0);
		}, $eligible_business_batches), true);
		$selected_business_batch_id = $recipient_source_mode === 'business_source'
			? absint(vms_pass_outreach_payload_value($form_payload, 'related_batch_id', 0))
			: 0;
		if ($selected_business_batch_id > 0 && empty($eligible_business_batch_ids[$selected_business_batch_id])) {
			$selected_business_batch_id = 0;
			$form_payload['related_batch_id'] = 0;
			if ($create_preview_mode === 'business_source') {
				$create_preview_mode = '';
				$upload_preview = array();
			}
		}
		if (!empty($business_batch_review) && absint($business_batch_review['batch_payload']['source_id'] ?? 0) !== $selected_business_source_id) {
			$business_batch_review = array();
		}
		if (empty($business_batch_form) && !empty($business_batch_review['batch_payload']) && is_array($business_batch_review['batch_payload'])) {
			$reviewed_batch_payload = (array) $business_batch_review['batch_payload'];
			$business_batch_form = array(
				'business_batch_name' => (string) ($reviewed_batch_payload['batch_name'] ?? ''),
				'business_batch_offer_type' => sanitize_key((string) ($reviewed_batch_payload['value_type'] ?? '')),
				'business_batch_offer_amount' => in_array(sanitize_key((string) ($reviewed_batch_payload['value_type'] ?? '')), array('percent', 'fixed'), true) ? (string) ($reviewed_batch_payload['value_amount'] ?? '') : '',
				'business_batch_admissions_per_link' => (string) absint($reviewed_batch_payload['admissions_per_link'] ?? 0),
				'business_batch_total_admission_cap' => (string) absint($reviewed_batch_payload['total_admission_cap'] ?? 0),
				'business_batch_per_business_admission_cap' => (string) absint($business_batch_review['per_business_admission_cap'] ?? 0),
				'business_batch_validity_type' => (string) ($reviewed_batch_payload['validity_type'] ?? ''),
				'business_batch_single_event_plan_id' => (string) absint($reviewed_batch_payload['single_event_plan_id'] ?? 0),
				'business_batch_start_date' => (string) ($reviewed_batch_payload['start_date'] ?? ''),
				'business_batch_end_date' => (string) ($reviewed_batch_payload['end_date'] ?? ''),
				'business_batch_season_label' => (string) ($reviewed_batch_payload['season_label'] ?? ''),
				'business_batch_expires_at' => vms_pass_outreach_format_datetime_input_value((string) ($reviewed_batch_payload['expires_at'] ?? '')),
				'business_batch_notes' => (string) ($reviewed_batch_payload['notes'] ?? ''),
			);
		}
		$business_batch_value = static function (string $key, string $default = '') use ($business_batch_form): string {
			return array_key_exists($key, $business_batch_form) ? (string) $business_batch_form[$key] : $default;
		};
		$business_batch_validity_type = sanitize_key($business_batch_value('business_batch_validity_type'));
		$business_scope_visible = static function (array $types) use ($business_batch_validity_type): bool {
			return in_array($business_batch_validity_type, $types, true);
		};
		$validity_type = sanitize_key((string) vms_pass_outreach_payload_value($form_payload, 'validity_type', 'any_event'));
		if (!in_array($validity_type, vms_pass_outreach_allowed_validity_types(), true)) {
			$validity_type = 'any_event';
		}
		$show_existing_source_fields = $is_create_screen && $recipient_source_mode === 'existing_source';
		$show_business_source_fields = $is_create_screen && $recipient_source_mode === 'business_source';
		$show_new_source_fields = $is_create_screen && in_array($recipient_source_mode, array('csv_new', 'contacts'), true);
		$show_csv_mode = $is_create_screen && $recipient_source_mode === 'csv_new';
		$show_contacts_mode = $is_create_screen && $recipient_source_mode === 'contacts';
		$show_single_event_field = $validity_type === 'single_event';
		$show_date_fields = in_array($validity_type, array('date_range', 'season'), true);
		$show_season_field = $validity_type === 'season';
		$show_guest_pass_fields = $campaign_purpose === $live_campaign_purpose;
		$advanced_panel_open = !$is_create_screen;
		$existing_source_preview = ($create_preview_mode === 'existing_source' && !empty($upload_preview['recipient_preview']) && is_array($upload_preview['recipient_preview']))
			? (array) $upload_preview['recipient_preview']
			: array();
		$business_source_preview = ($create_preview_mode === 'business_source' && !empty($upload_preview['business_preview']) && is_array($upload_preview['business_preview']))
			? (array) $upload_preview['business_preview']
			: array();
		$contact_source_preview = ($create_preview_mode === 'contacts' && !empty($upload_preview['recipient_preview']) && is_array($upload_preview['recipient_preview']))
			? (array) $upload_preview['recipient_preview']
			: array();
		$contact_status_filter_options = $contacts_mode_available
			? vms_pass_outreach_contact_audience_status_filter_options()
			: array();
		$contact_create_filters = $contacts_mode_available
			? vms_pass_outreach_normalize_contact_audience_filters((array) ($contact_source_preview['filters'] ?? array()))
			: array();
		$contact_selected_ids = array_values(array_filter(array_map('absint', (array) ($upload_preview['selected_contact_ids'] ?? array()))));
		$contact_selected_lookup = array_fill_keys($contact_selected_ids, true);
		$contact_selected_count = absint($upload_preview['selected_count'] ?? count($contact_selected_ids));
		$contact_preview_rows = array_values((array) ($contact_source_preview['preview_rows'] ?? array()));
		$contact_selected_sample_rows = array();
		if (!empty($contact_source_preview['prepared_rows']) && is_array($contact_source_preview['prepared_rows'])) {
			$contact_selected_rows = function_exists('vms_pass_outreach_contact_audience_selected_prepared_rows')
				? vms_pass_outreach_contact_audience_selected_prepared_rows($contact_source_preview, $contact_selected_ids)
				: array();
			$contact_selected_sample_rows = vms_pass_outreach_preview_sample_rows_from_prepared_rows($contact_selected_rows);
		}
		$existing_source_sample_rows = array_values((array) ($existing_source_preview['sample_rows'] ?? array()));
		$existing_source_ready_count = absint($existing_source_preview['ready_count'] ?? 0);
		$business_source_rows = array_values((array) ($business_source_preview['rows'] ?? array()));
		$business_source_ready_count = absint($business_source_preview['link_eligible_count'] ?? 0);
		$create_preview_setup_snapshot = !empty($upload_preview['campaign_setup']) && is_array($upload_preview['campaign_setup'])
			? vms_pass_outreach_create_preview_setup_snapshot((array) $upload_preview['campaign_setup'])
			: array();
		$create_preview_setup_snapshot_json = !empty($create_preview_setup_snapshot) ? wp_json_encode($create_preview_setup_snapshot) : '';
		$create_message_preview_snapshot = !empty($upload_preview['campaign_setup']) && is_array($upload_preview['campaign_setup'])
			? vms_pass_outreach_create_message_preview_snapshot((array) $upload_preview['campaign_setup'])
			: array();
		$create_message_preview_snapshot_json = !empty($create_message_preview_snapshot) ? wp_json_encode($create_message_preview_snapshot) : '';
		$create_preview_ready_count = 0;
		$create_preview_total_passes = absint($upload_batch_preview['total_admission_cap'] ?? 0);
		if ($create_preview_mode === 'csv_new') {
			$create_preview_ready_count = $upload_ready_count;
		} elseif ($create_preview_mode === 'existing_source') {
			$create_preview_ready_count = $existing_source_ready_count;
		} elseif ($create_preview_mode === 'business_source') {
			$create_preview_ready_count = $business_source_ready_count;
		} elseif ($create_preview_mode === 'contacts') {
			$create_preview_ready_count = $contact_selected_count;
		}
		$live_purpose_label = sanitize_text_field((string) ($live_purpose_definition['label'] ?? $live_campaign_purpose));
		$live_purpose_description = sanitize_text_field(vms_pass_outreach_display_text((string) ($live_purpose_definition['description'] ?? '')));
		$scope_help_text = __('Choose whether these passes apply to any event, one event, a date range, or a season.', 'backstage-outreach');
		if (!empty($campaign_guardrails['has_recipients'])) {
			$scope_help_text .= ' ' . __('If you change this after invites are issued, the new scope applies only to future claims.', 'backstage-outreach');
		}
		$recipient_cap_help_text = __('Default number of guest passes each recipient can claim.', 'backstage-outreach');
		if (!empty($campaign_guardrails['has_claims']) || !empty($campaign_guardrails['has_checkins'])) {
			$recipient_cap_help_text .= ' ' . __('Lowering this never removes already claimed or checked-in admissions.', 'backstage-outreach');
		}
		$total_cap_help_text = __('Manual override. Upload-first preview calculates this automatically from recipient count multiplied by passes per recipient.', 'backstage-outreach');
		if (!empty($campaign_guardrails['has_claims']) || !empty($campaign_guardrails['has_checkins'])) {
			$total_cap_help_text .= ' ' . __('Lowering the cap below current claimed usage only blocks future claims.', 'backstage-outreach');
		}
		$forward_only_summary = sprintf(
			__('Issued invites: %1$d. Claimed admissions: %2$d. Checked in: %3$d.', 'backstage-outreach'),
			(int) ($campaign_guardrails['recipient_count'] ?? 0),
			(int) ($campaign_guardrails['claimed_headcount'] ?? 0),
			(int) ($campaign_guardrails['checked_in_headcount'] ?? 0)
		);
		$section_context = vms_pass_outreach_collapsible_context($campaign_id);
		$purpose_section_attrs = vms_pass_outreach_collapsible_details_attrs($section_context, 'purpose', array(
			'classes' => array('vms-pass-form-section', 'vms-pass-form-section--collapsible'),
			'default_open' => $is_create_screen,
			'force_open' => vms_pass_outreach_section_has_errors($field_errors, array('campaign_purpose')),
		));
		$campaign_section_attrs = vms_pass_outreach_collapsible_details_attrs($section_context, 'campaign', array(
			'classes' => array('vms-pass-form-section', 'vms-pass-form-section--collapsible'),
			'default_open' => true,
			'force_open' => vms_pass_outreach_section_has_errors($field_errors, array('campaign_name')),
		));
		$guest_pass_section_attrs = vms_pass_outreach_collapsible_details_attrs($section_context, 'guest_pass_offer', array(
			'classes' => array('vms-pass-form-section', 'vms-pass-form-section--collapsible'),
			'default_open' => $is_create_screen,
			'force_open' => vms_pass_outreach_section_has_errors($field_errors, array('admissions_per_recipient', 'validity_type', 'single_event_plan_id', 'start_date', 'end_date', 'season_label', 'confirm_forward_only_changes', 'status', 'eligibility_mode', 'expires_at', 'internal_notes')),
		));
		$recipient_source_section_attrs = vms_pass_outreach_collapsible_details_attrs($section_context, 'recipient_source', array(
			'classes' => array('vms-pass-form-section', 'vms-pass-form-section--collapsible'),
			'default_open' => $is_create_screen,
			'force_open' => vms_pass_outreach_section_has_errors($field_errors, array('related_source_id', 'tracking_category_name')),
			'anchor' => vms_pass_outreach_create_preview_anchor(),
		));
		$template_section_attrs = vms_pass_outreach_collapsible_details_attrs($section_context, 'template_message', array(
			'classes' => array('vms-pass-form-section', 'vms-pass-form-section--collapsible'),
			'default_open' => $is_create_screen && !$show_business_source_fields,
			'force_open' => vms_pass_outreach_section_has_errors($field_errors, array('email_subject', 'message_template')),
		));
		$preview_section_attrs = vms_pass_outreach_collapsible_details_attrs($section_context, 'review_create', array(
			'classes' => array('vms-pass-form-section', 'vms-pass-form-section--collapsible'),
			'default_open' => $is_create_screen,
			'anchor' => vms_pass_outreach_create_review_anchor(),
		));

		echo '<section id="vms-outreach-campaign-form" class="vms-pass-card">';
		echo '<h2>' . esc_html($campaign_id > 0 ? __('Edit Outreach Campaign', 'backstage-outreach') : __('Create Outreach Campaign', 'backstage-outreach')) . '</h2>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-form">';
		echo '<input type="hidden" name="action" value="vms_pass_outreach_campaign_save">';
		echo '<input type="hidden" name="campaign_id" value="' . esc_attr((string) ($form_payload['id'] ?? 0)) . '">';
		echo '<input type="hidden" name="campaign_purpose" value="' . esc_attr($campaign_purpose) . '"' . ($is_create_screen ? ' data-vms-purpose-hidden-input' : '') . '>';
		if ($is_create_screen) {
			echo '<input type="hidden" name="workflow_mode" value="upload_first">';
			echo '<input type="hidden" name="tracking_category_mode" value="' . esc_attr($tracking_category_mode) . '" data-vms-tracking-mode-input>';
		}
		vms_outreach_nonce_field(
			'vms_pass_outreach_campaign_save',
			'vms-outreach-campaign-save-' . absint($form_payload['id'] ?? 0) . '-nonce'
		);

		echo '<div class="vms-pass-form-sections">';

		if (!$is_create_screen) {
			echo '<div class="vms-pass-preview-summary">';
			echo '<h3>' . esc_html__('Campaign Actions', 'backstage-outreach') . '</h3>';
			echo '<p class="description">' . esc_html__('Status, linked batch, event scope, and message updates can be saved here at any time.', 'backstage-outreach') . '</p>';
			echo '<p class="vms-pass-actions"><button type="submit" class="button button-primary" name="save_mode" value="standard">' . esc_html__('Save Campaign', 'backstage-outreach') . '</button>';
			if ($campaign_id > 0) {
				echo ' <a class="button" href="' . esc_url(vms_pass_outreach_admin_page_url()) . '">' . esc_html__('New Campaign', 'backstage-outreach') . '</a>';
			}
			echo '</p>';
			echo '</div>';
		}

		if (!$is_create_screen) {
			echo '<details ' . $purpose_section_attrs . '>';
			echo vms_pass_outreach_render_collapsible_summary(__('Purpose', 'backstage-outreach'));
			echo '<div class="vms-pass-purpose-current">';
			echo '<label class="vms-pass-choice vms-pass-purpose-card vms-pass-purpose-card--active">';
			echo '<input type="radio" value="' . esc_attr($campaign_purpose) . '"' . checked($show_guest_pass_fields, true, false) . ' disabled>';
			echo '<span class="vms-pass-purpose-card__body">';
			echo '<span class="vms-pass-purpose-card__head">';
			echo '<span class="vms-pass-purpose-card__title">' . esc_html($live_purpose_label) . '</span>';
			echo '<span class="vms-pass-purpose-card__badge is-live">' . esc_html__('Live now', 'backstage-outreach') . '</span>';
			echo '</span>';
			if ($live_purpose_description !== '') {
				echo '<span class="vms-pass-purpose-card__copy">' . esc_html($live_purpose_description) . '</span>';
			}
			echo '</span>';
			echo '</label>';
			echo '</div>';
			if (!empty($planned_purpose_definitions)) {
				echo '<details class="vms-pass-purpose-roadmap">';
				echo '<summary><span class="vms-pass-purpose-roadmap__summary-text">' . esc_html__('Planned outreach purposes', 'backstage-outreach') . '</span><span class="vms-pass-purpose-card__badge is-planned">' . esc_html__('Planned', 'backstage-outreach') . '</span></summary>';
				echo '<div class="vms-pass-purpose-roadmap__list">';
				foreach ($planned_purpose_definitions as $purpose_key => $purpose_definition) {
					$purpose_label = sanitize_text_field((string) ($purpose_definition['label'] ?? $purpose_key));
					$purpose_description = sanitize_text_field(vms_pass_outreach_display_text((string) ($purpose_definition['description'] ?? '')));
					echo '<div class="vms-pass-purpose-roadmap__item">';
					echo '<div class="vms-pass-purpose-roadmap__meta">';
					echo '<span class="vms-pass-purpose-roadmap__title">' . esc_html($purpose_label) . '</span>';
					if ($purpose_description !== '') {
						echo '<span class="vms-pass-purpose-roadmap__copy">' . esc_html($purpose_description) . '</span>';
					}
					echo '</div>';
					echo '<span class="vms-pass-purpose-card__badge is-planned">' . esc_html__('Planned', 'backstage-outreach') . '</span>';
					echo '</div>';
				}
				echo '</div>';
				echo '</details>';
			}
			echo $render_messages($field_errors, array('campaign_purpose'));
			echo '</details>';
		}

		if ($is_create_screen) {
			echo '<details ' . $recipient_source_section_attrs . '>';
			echo vms_pass_outreach_render_collapsible_summary(__('Delivery / Recipient Route', 'backstage-outreach'));
			echo '<fieldset' . vms_pass_outreach_field_wrapper_class($field_errors, array('related_source_id', 'tracking_category_name'), 'vms-pass-section-fieldset') . '>';
			echo '<legend>' . $render_label(__('How will people receive this offer?', 'backstage-outreach'), array(
				'required' => true,
				'help' => __('Choose individual-recipient delivery or reusable links / QRs for businesses before completing route-specific campaign details.', 'backstage-outreach'),
			)) . '</legend>';
			echo '<div class="vms-pass-choice-row vms-pass-choice-row--stacked">';
			echo '<label class="vms-pass-choice"><input type="radio" name="recipient_source_mode" value="csv_new"' . checked($recipient_source_mode, 'csv_new', false) . '> <span>' . esc_html__('Upload CSV / Create New Source List', 'backstage-outreach') . '</span></label>';
			if ($has_sources) {
				echo '<label class="vms-pass-choice"><input type="radio" name="recipient_source_mode" value="existing_source"' . checked($recipient_source_mode, 'existing_source', false) . '> <span>' . esc_html__('Use Existing Source List', 'backstage-outreach') . '</span></label>';
				echo '<label class="vms-pass-choice"><input type="radio" name="recipient_source_mode" value="business_source"' . checked($recipient_source_mode, 'business_source', false) . '> <span>' . esc_html__('Reusable Business Links / QRs', 'backstage-outreach') . '</span></label>';
			}
			if ($contacts_mode_available) {
				echo '<label class="vms-pass-choice"><input type="radio" name="recipient_source_mode" value="contacts"' . checked($recipient_source_mode, 'contacts', false) . '> <span>' . esc_html__('Select saved Outreach contacts', 'backstage-outreach') . '</span></label>';
			}
			echo '</div></fieldset>';
			echo '<p class="description">' . esc_html__('Your draft stays on this page when you switch routes. Compatible unedited offer fields are copied into a new business-batch draft; existing batches are never changed.', 'backstage-outreach') . '</p>';
			echo '</details>';
		}

		echo '<details ' . $campaign_section_attrs . ($is_create_screen ? ' data-vms-nonbusiness-campaign-fields' . $hidden_attr($show_business_source_fields) : '') . '>';
		echo vms_pass_outreach_render_collapsible_summary(__('Campaign', 'backstage-outreach'));
		echo '<div class="vms-pass-grid">';
		echo '<label class="vms-pass-span-2' . (!empty($field_errors['campaign_name']) ? ' vms-pass-field-has-error' : '') . '">' . $render_label(__('Campaign Name', 'backstage-outreach'), array(
				'required' => true,
				'help' => __('Internal name for this outreach effort.', 'backstage-outreach'),
			)) . '<input type="text" name="campaign_name" value="' . esc_attr((string) vms_pass_outreach_payload_value($form_payload, 'campaign_name', '')) . '" placeholder="' . esc_attr__('Local Realtor Outreach - Fall 2026', 'backstage-outreach') . '" required>' . $render_messages($field_errors, array('campaign_name')) . '</label>';
		if (!$is_create_screen) {
				echo '<label class="vms-pass-span-2' . (!empty($field_errors['related_source_id']) ? ' vms-pass-field-has-error' : '') . '">' . $render_label(__('Tracking Source', 'backstage-outreach'), array(
					'help' => __('Reporting bucket on the Sources tab, such as Realtor Outreach or Sponsor Comp Passes.', 'backstage-outreach'),
				)) . '<select name="related_source_id">';
				echo '<option value="0">' . esc_html__('Select tracking source', 'backstage-outreach') . '</option>';
				foreach ((array) $sources as $source) {
					$source_id = absint($source['id'] ?? 0);
					if ($source_id <= 0) {
						continue;
					}
					echo '<option value="' . esc_attr((string) $source_id) . '"' . selected((int) vms_pass_outreach_payload_value($form_payload, 'related_source_id', 0), $source_id, false) . '>' . esc_html((string) ($source['source_name'] ?? '')) . '</option>';
				}
				echo '</select>' . $render_messages($field_errors, array('related_source_id')) . '</label>';
		}
		echo '</div>';
		echo '</details>';

		echo '<details ' . $guest_pass_section_attrs . ' data-vms-purpose-guest-pass' . ($is_create_screen ? ' data-vms-nonbusiness-offer-fields' : '') . $hidden_attr(!$show_guest_pass_fields || $show_business_source_fields) . '>';
		echo vms_pass_outreach_render_collapsible_summary(__('Guest Pass Offer', 'backstage-outreach'));
		echo '<div class="vms-pass-grid">';
		echo '<label class="' . (!empty($field_errors['admissions_per_recipient']) ? ' vms-pass-field-has-error' : '') . '">' . $render_label(__('Passes Per Recipient', 'backstage-outreach'), array(
			'required' => true,
			'help' => $recipient_cap_help_text,
		)) . '<input type="number" class="small-text" min="1" max="100" name="admissions_per_recipient" value="' . esc_attr((string) vms_pass_outreach_payload_value($form_payload, 'admissions_per_recipient', 2)) . '" required>' . $render_messages($field_errors, array('admissions_per_recipient')) . '</label>';
		echo '<label class="vms-pass-span-2' . (!empty($field_errors['validity_type']) ? ' vms-pass-field-has-error' : '') . '">' . $render_label(__('Applies To', 'backstage-outreach'), array(
			'required' => true,
			'help' => $scope_help_text,
		)) . '<select name="validity_type">';
			foreach (vms_pass_outreach_form_validity_labels($validity_type) as $key => $label) {
				echo '<option value="' . esc_attr((string) $key) . '"' . selected((string) vms_pass_outreach_payload_value($form_payload, 'validity_type', 'any_event'), (string) $key, false) . '>' . esc_html((string) $label) . '</option>';
			}
			echo '</select>' . $render_messages($field_errors, array('validity_type')) . '</label>';

			echo '<label class="vms-pass-span-2' . (!empty($field_errors['single_event_plan_id']) ? ' vms-pass-field-has-error' : '') . '" data-vms-validity-single-event' . $hidden_attr(!$show_single_event_field) . '>' . $render_label(__('Event', 'backstage-outreach'), array(
				'help' => __('Choose the event for this campaign.', 'backstage-outreach'),
			)) . '<select name="single_event_plan_id">';
		echo '<option value="0">' . esc_html__('Select event', 'backstage-outreach') . '</option>';
		foreach ((array) $event_plans as $plan) {
			$plan_id = absint($plan['id'] ?? 0);
			if ($plan_id <= 0) {
				continue;
			}
			$label = (string) ($plan['title'] ?? __('Event', 'backstage-outreach'));
			if (!empty($plan['event_date'])) {
				$label .= ' (' . (string) $plan['event_date'] . ')';
			}
			echo '<option value="' . esc_attr((string) $plan_id) . '"' . selected((int) vms_pass_outreach_payload_value($form_payload, 'single_event_plan_id', 0), $plan_id, false) . '>' . esc_html($label) . '</option>';
		}
			echo '</select>' . $render_messages($field_errors, array('single_event_plan_id')) . '</label>';

			echo '<div class="vms-pass-span-2 vms-pass-inline-pair" data-vms-validity-date-range' . $hidden_attr(!$show_date_fields) . '>';
			echo '<label' . vms_pass_outreach_field_wrapper_class($field_errors, array('start_date')) . '>' . $render_label(__('Start Date', 'backstage-outreach'), array(
				'help' => __('Required for Date Range. Optional for Season.', 'backstage-outreach'),
			)) . '<input type="date" name="start_date" value="' . esc_attr((string) vms_pass_outreach_payload_value($form_payload, 'start_date', '')) . '">' . $render_messages($field_errors, array('start_date')) . '</label>';
			echo '<label' . vms_pass_outreach_field_wrapper_class($field_errors, array('end_date')) . '>' . $render_label(__('End Date', 'backstage-outreach'), array(
				'help' => __('Required for Date Range. Optional for Season.', 'backstage-outreach'),
			)) . '<input type="date" name="end_date" value="' . esc_attr((string) vms_pass_outreach_payload_value($form_payload, 'end_date', '')) . '">' . $render_messages($field_errors, array('end_date')) . '</label>';
			echo '</div>';

			echo '<label class="vms-pass-span-2' . (!empty($field_errors['season_label']) ? ' vms-pass-field-has-error' : '') . '" data-vms-validity-season' . $hidden_attr(!$show_season_field) . '>' . $render_label(__('Season Label', 'backstage-outreach'), array(
				'help' => __('Reporting label for the season, such as Spring 2026.', 'backstage-outreach'),
			)) . '<input type="text" name="season_label" value="' . esc_attr((string) vms_pass_outreach_payload_value($form_payload, 'season_label', '')) . '" placeholder="Spring 2026">' . $render_messages($field_errors, array('season_label')) . '</label>';
			if (!$is_create_screen && !empty($campaign_guardrails['has_issued_activity'])) {
				echo '<div class="vms-pass-span-2 vms-pass-guardrail-note' . (!empty($field_errors['confirm_forward_only_changes']) ? ' vms-pass-field-has-error' : '') . '">';
				echo '<div class="vms-pass-guardrail-note__copy"><strong>' . esc_html__('Issued invites stay valid.', 'backstage-outreach') . '</strong> ' . esc_html__('Scope or cap edits only affect future claims and never remove claimed or checked-in admissions.', 'backstage-outreach') . '</div>';
				echo '<div class="vms-pass-guardrail-note__meta">' . esc_html($forward_only_summary) . '</div>';
				echo '<label class="vms-pass-inline-check"><input type="checkbox" name="confirm_forward_only_changes" value="1"' . checked($confirm_forward_only_checked, true, false) . '> <span>' . esc_html__('Confirm forward-only changes if you adjust scope or limits.', 'backstage-outreach') . '</span></label>';
				echo $render_messages($field_errors, array('confirm_forward_only_changes'));
				echo '</div>';
			}
			echo '</div>';
			echo '<details class="vms-pass-advanced-panel" data-vms-outreach-advanced-panel data-vms-purpose-guest-pass' . $hidden_attr(!$show_guest_pass_fields) . ($advanced_panel_open ? ' open' : '') . '>';
			echo '<summary>' . esc_html__('Advanced Guest Pass Controls', 'backstage-outreach') . '</summary>';
			echo '<div class="vms-pass-advanced-panel__body">';
			echo '<div class="vms-pass-grid">';
			if (!$is_create_screen) {
				echo '<label id="vms-outreach-batch-field" class="vms-pass-span-2' . (!empty($field_errors['related_batch_id']) ? ' vms-pass-field-has-error' : '') . '">' . $render_label(__('Use Existing Batch / Invite Link Pool', 'backstage-outreach'), array(
					'help' => __('Choose the linked batch for this campaign. Create flow now auto-builds the batch after recipient preview.', 'backstage-outreach'),
				)) . '<select name="related_batch_id">';
				echo '<option value="0">' . esc_html($batch_placeholder) . '</option>';
				foreach ((array) $batches as $batch) {
					$batch_row_id = absint($batch['id'] ?? 0);
					if ($batch_row_id <= 0) {
						continue;
					}
					$batch_label = (string) ($batch['batch_name'] ?? ('Batch #' . $batch_row_id));
					$source_name = sanitize_text_field((string) ($batch['source_name'] ?? ''));
					if ($source_name !== '') {
						$batch_label .= ' - ' . $source_name;
					}
					echo '<option value="' . esc_attr((string) $batch_row_id) . '"' . selected((int) vms_pass_outreach_payload_value($form_payload, 'related_batch_id', 0), $batch_row_id, false) . '>' . esc_html($batch_label) . '</option>';
				}
				echo '</select>' . $render_messages($field_errors, array('related_batch_id')) . '</label>';
			}
			echo '<label id="vms-outreach-campaign-status" class="' . (!empty($field_errors['status']) ? 'vms-pass-field-has-error' : '') . '">' . $render_label(__('Status', 'backstage-outreach'), array(
				'help' => __('Use Draft while preparing recipients. Active allows invite claims. Closed stops new claims.', 'backstage-outreach'),
			)) . '<select name="status">';
			foreach (vms_pass_outreach_status_labels() as $key => $label) {
				echo '<option value="' . esc_attr((string) $key) . '"' . selected((string) vms_pass_outreach_payload_value($form_payload, 'status', 'draft'), (string) $key, false) . '>' . esc_html((string) $label) . '</option>';
			}
			echo '</select>' . $render_messages($field_errors, array('status'));
			if (!$is_create_screen) {
				$stored_status_label = (string) (vms_pass_outreach_status_labels()[(string) vms_pass_outreach_payload_value($form_payload, 'status', 'draft')] ?? vms_pass_outreach_payload_value($form_payload, 'status', 'draft'));
				echo '<span class="description">' . esc_html(sprintf(__('Delivery display status: %1$s. Workflow status: %2$s.', 'backstage-outreach'), (string) ($campaign_display_status['label'] ?? __('Draft', 'backstage-outreach')), $stored_status_label)) . '</span>';
			}
			echo '</label>';

			echo '<label class="' . (!empty($field_errors['eligibility_mode']) ? 'vms-pass-field-has-error' : '') . '">' . $render_label(__('Eligibility Mode', 'backstage-outreach'), array(
				'help' => __('Controls whether anyone with the invite can claim, or whether recipient matching checks are enforced.', 'backstage-outreach'),
			)) . '<select name="eligibility_mode">';
			foreach (vms_pass_outreach_eligibility_labels() as $key => $label) {
				echo '<option value="' . esc_attr((string) $key) . '"' . selected((string) vms_pass_outreach_payload_value($form_payload, 'eligibility_mode', 'anyone_with_invite'), (string) $key, false) . '>' . esc_html((string) $label) . '</option>';
			}
			echo '</select>' . $render_messages($field_errors, array('eligibility_mode')) . '</label>';

			echo '<label>' . $render_label(__('Expiration', 'backstage-outreach'), array(
				'help' => __('Optional deadline for unused invite links in this campaign.', 'backstage-outreach'),
			)) . '<input type="datetime-local" name="expires_at" value="' . esc_attr(vms_pass_outreach_format_datetime_input_value((string) vms_pass_outreach_payload_value($form_payload, 'expires_at', ''))) . '">' . $render_messages($field_errors, array('expires_at')) . '</label>';

			if (!$is_create_screen) {
				echo '<label' . vms_pass_outreach_field_wrapper_class($field_errors, array('total_admission_cap')) . '>' . $render_label(__('Total Campaign Cap', 'backstage-outreach'), array(
					'help' => $total_cap_help_text,
				)) . '<input type="number" class="small-text" min="0" max="50000" name="total_admission_cap" value="' . esc_attr((string) vms_pass_outreach_payload_value($form_payload, 'total_admission_cap', 0)) . '">' . $render_messages($field_errors, array('total_admission_cap')) . '</label>';
			}

			echo '<label class="vms-pass-span-2">' . $render_label(__('Internal Description / Notes', 'backstage-outreach'), array(
				'help' => __('Internal notes about audience, approvals, message timing, or follow-up.', 'backstage-outreach'),
			)) . '<textarea name="internal_notes" rows="4">' . esc_textarea((string) vms_pass_outreach_payload_value($form_payload, 'internal_notes', '')) . '</textarea>' . $render_messages($field_errors, array('internal_notes')) . '</label>';
			echo '</div>';
			echo '</div>';
			echo '</details>';
			echo '</details>';

			if ($is_create_screen) {
				echo '<div class="vms-pass-recipient-route-fields">';

				echo '<div class="vms-pass-upload-step" data-vms-recipient-source-csv' . $hidden_attr(!$show_csv_mode) . '>';
				echo '<div class="vms-pass-grid">';
				echo '<label class="vms-pass-span-2' . (!empty($field_errors['tracking_category_name']) ? ' vms-pass-field-has-error' : '') . '">' . $render_label(__('Source / List Name', 'backstage-outreach'), array(
					'help' => __('Name for the new source list that will own this upload.', 'backstage-outreach'),
				)) . '<input type="text" name="tracking_category_name" value="' . esc_attr((string) vms_pass_outreach_payload_value($form_payload, 'tracking_category_name', '')) . '" placeholder="' . esc_attr__('Realtor Outreach', 'backstage-outreach') . '">' . $render_messages($field_errors, array('tracking_category_name')) . '</label>';
				echo '<label class="vms-pass-span-2 vms-pass-upload-field">' . $render_label(__('CSV File', 'backstage-outreach'), array(
					'required' => true,
					'help' => sprintf(
						__('Email is required. First name, last name, and full name are optional. Upload any CSV, map the columns you want to use, and preview before creating the campaign. Up to %d rows per upload.', 'backstage-outreach'),
						vms_pass_outreach_import_row_limit()
					),
				)) . '<div class="vms-pass-file-input-row"><input type="file" name="recipient_csv" accept=".csv,text/csv"></div></label>';
				echo '</div>';
				echo '<p class="vms-pass-upload-actions"><button type="submit" class="' . esc_attr($upload_map_button_class) . '" name="save_mode" value="upload_map">' . esc_html($upload_map_button_label) . '</button></p>';
				if ($upload_has_mapping) {
					echo '<div class="vms-pass-preview-summary vms-pass-preview-summary--mapping vms-pass-upload-mapping">';
					echo '<h3>' . esc_html__('Column Mapping', 'backstage-outreach') . '</h3>';
					echo '<table class="widefat striped vms-pass-mapping-table"><thead><tr><th>' . esc_html__('Uploaded Column', 'backstage-outreach') . '</th><th>' . esc_html__('Sample Value', 'backstage-outreach') . '</th><th>' . esc_html__('Map To', 'backstage-outreach') . '</th></tr></thead><tbody>';
					foreach ($upload_mapping_headers as $index => $header_label) {
						$sample_value = (string) ($upload_mapping_samples[$index] ?? '');
						$current_mapping = sanitize_key((string) ($upload_mapping_selected[$index] ?? ''));
						echo '<tr>';
						echo '<td><strong>' . esc_html($header_label !== '' ? $header_label : sprintf(__('Column %d', 'backstage-outreach'), $index + 1)) . '</strong></td>';
						echo '<td class="vms-pass-mapping-table__sample">' . esc_html($sample_value !== '' ? $sample_value : '—') . '</td>';
						echo '<td><select name="csv_mapping[' . esc_attr((string) $index) . ']">';
						foreach ($upload_mapping_options as $field_key => $field_label) {
							echo '<option value="' . esc_attr((string) $field_key) . '"' . selected($current_mapping, (string) $field_key, false) . '>' . esc_html((string) $field_label) . '</option>';
						}
						echo '</select></td>';
						echo '</tr>';
					}
					echo '</tbody></table>';
					if ($upload_mapping_file_name !== '') {
						echo '<p class="description">' . esc_html(sprintf(__('CSV: %1$s. Rows detected: %2$d.', 'backstage-outreach'), $upload_mapping_file_name, $upload_mapping_total_rows)) . '</p>';
					}
					echo '</div>';
					echo '<p class="vms-pass-upload-actions"><button type="submit" class="' . esc_attr($upload_preview_button_class) . '" name="save_mode" value="upload_preview">' . esc_html($upload_preview_button_label) . '</button></p>';
				}
				if ($create_preview_mode === 'csv_new') {
					echo '<div id="' . esc_attr(vms_pass_outreach_create_preview_fragment('csv_new')) . '" class="vms-pass-preview-summary vms-pass-upload-preview">';
					echo '<h3>' . esc_html__('Recipient Preview', 'backstage-outreach') . '</h3>';
					echo '<table class="widefat striped"><tbody>';
					$preview_rows = array(
						__('Preview File', 'backstage-outreach') => sanitize_text_field((string) ($upload_import_preview['file_name'] ?? '')),
						__('Total Rows', 'backstage-outreach') => (string) $upload_total_rows,
						__('Ready Recipients', 'backstage-outreach') => (string) $upload_ready_count,
						__('Valid Emails', 'backstage-outreach') => (string) $upload_valid_email_count,
						__('Skipped / Invalid Rows', 'backstage-outreach') => (string) absint($upload_import_preview['failed_count'] ?? 0),
						__('Duplicates', 'backstage-outreach') => (string) absint($upload_import_preview['duplicate_count'] ?? 0),
						__('Blank Rows Ignored', 'backstage-outreach') => (string) absint($upload_import_preview['blank_rows'] ?? 0),
						__('Passes Per Recipient', 'backstage-outreach') => (string) absint($upload_batch_preview['admissions_per_link'] ?? vms_pass_outreach_payload_value($form_payload, 'admissions_per_recipient', 2)),
						__('Total Passes Needed', 'backstage-outreach') => (string) $upload_total_passes,
					);
					foreach ($preview_rows as $label => $value) {
						echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html($value !== '' ? $value : __('Not set', 'backstage-outreach')) . '</td></tr>';
					}
					echo '</tbody></table>';
					if (!empty($upload_preview_sample_rows)) {
						echo '<div class="vms-pass-table-scroll vms-pass-upload-preview-table-wrap">';
						echo '<table class="widefat striped vms-pass-upload-preview-table"><thead><tr><th>' . esc_html__('Name', 'backstage-outreach') . '</th><th>' . esc_html__('Email', 'backstage-outreach') . '</th><th>' . esc_html__('Phone', 'backstage-outreach') . '</th><th>' . esc_html__('Company', 'backstage-outreach') . '</th><th>' . esc_html__('Group', 'backstage-outreach') . '</th></tr></thead><tbody>';
						foreach ($upload_preview_sample_rows as $sample_row) {
							$sample_name = sanitize_text_field((string) ($sample_row['full_name'] ?? ''));
							$sample_email = sanitize_text_field((string) ($sample_row['email'] ?? ''));
							$sample_phone = sanitize_text_field((string) ($sample_row['phone'] ?? ''));
							$sample_company = sanitize_text_field((string) ($sample_row['company'] ?? ''));
							$sample_group = sanitize_text_field((string) ($sample_row['group_label'] ?? ''));
							echo '<tr>';
							echo '<td>' . esc_html($sample_name !== '' ? $sample_name : '—') . '</td>';
							echo '<td>' . esc_html($sample_email !== '' ? $sample_email : '—') . '</td>';
							echo '<td>' . esc_html($sample_phone !== '' ? $sample_phone : '—') . '</td>';
							echo '<td>' . esc_html($sample_company !== '' ? $sample_company : '—') . '</td>';
							echo '<td>' . esc_html($sample_group !== '' ? $sample_group : '—') . '</td>';
							echo '</tr>';
						}
						echo '</tbody></table>';
						echo '</div>';
						echo '<p class="description">' . esc_html(sprintf(__('Showing the first %1$d ready recipients from this preview.', 'backstage-outreach'), count($upload_preview_sample_rows))) . '</p>';
					}
					if (!empty($upload_failed_labels)) {
						echo '<p class="description">' . esc_html__('Invalid row details:', 'backstage-outreach') . ' ' . esc_html(implode('; ', $upload_failed_labels)) . '</p>';
					}
					echo '<p class="description" data-vms-preview-stale-note hidden>' . esc_html__('Guest Pass Offer changed after this preview. Refresh Recipient Preview before creating the campaign.', 'backstage-outreach') . '</p>';
					if ($upload_ready_count <= 0) {
						echo '<p class="description">' . esc_html__('No valid recipients are ready yet. Fix the CSV and preview again to continue.', 'backstage-outreach') . '</p>';
					}
					echo '</div>';
				}
				echo '</div>';

				echo '<div class="vms-pass-upload-step" data-vms-recipient-source-existing' . $hidden_attr(!$show_existing_source_fields) . '>';
				echo '<div class="vms-pass-grid">';
				echo '<label class="vms-pass-span-2' . (!empty($field_errors['related_source_id']) ? ' vms-pass-field-has-error' : '') . '">' . $render_label(__('Existing Source / List', 'backstage-outreach'), array(
					'required' => true,
					'help' => __('Choose an existing source list and preview the recipients linked to it.', 'backstage-outreach'),
				)) . '<select name="related_source_id">';
				echo '<option value="0">' . esc_html__('Select source list', 'backstage-outreach') . '</option>';
				foreach ((array) $sources as $source) {
					$source_id = absint($source['id'] ?? 0);
					if ($source_id <= 0) {
						continue;
					}
					echo '<option value="' . esc_attr((string) $source_id) . '"' . selected((int) vms_pass_outreach_payload_value($form_payload, 'related_source_id', 0), $source_id, false) . '>' . esc_html((string) ($source['source_name'] ?? '')) . '</option>';
				}
				echo '</select>' . $render_messages($field_errors, array('related_source_id')) . '</label>';
				echo '</div>';
				echo '<p class="vms-pass-upload-actions"><button type="submit" class="' . esc_attr($create_preview_mode === 'existing_source' ? 'button' : 'button button-primary') . '" name="save_mode" value="existing_source_preview">' . esc_html($create_preview_mode === 'existing_source' ? __('Refresh Preview', 'backstage-outreach') : __('Preview Source List', 'backstage-outreach')) . '</button></p>';
				if ($create_preview_mode === 'existing_source') {
					echo '<div id="' . esc_attr(vms_pass_outreach_create_preview_fragment('existing_source')) . '" class="vms-pass-preview-summary vms-pass-upload-preview">';
					echo '<h3>' . esc_html__('Recipient Preview', 'backstage-outreach') . '</h3>';
					echo '<table class="widefat striped"><tbody>';
					$preview_rows = array(
						__('Source / List', 'backstage-outreach') => sanitize_text_field((string) ($existing_source_preview['source_name'] ?? '')),
						__('Source Rows', 'backstage-outreach') => (string) absint($existing_source_preview['total_rows'] ?? 0),
						__('Ready Recipients', 'backstage-outreach') => (string) $existing_source_ready_count,
						__('Suppressed', 'backstage-outreach') => (string) absint($existing_source_preview['suppressed_count'] ?? 0),
						__('Do Not Contact', 'backstage-outreach') => (string) absint($existing_source_preview['do_not_contact_count'] ?? 0),
						__('Duplicates', 'backstage-outreach') => (string) absint($existing_source_preview['duplicate_count'] ?? 0),
						__('Missing / Invalid Email', 'backstage-outreach') => (string) absint($existing_source_preview['missing_email_count'] ?? 0),
						__('Total Passes Needed', 'backstage-outreach') => (string) absint($upload_batch_preview['total_admission_cap'] ?? 0),
					);
					foreach ($preview_rows as $label => $value) {
						echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html($value !== '' ? $value : __('Not set', 'backstage-outreach')) . '</td></tr>';
					}
					echo '</tbody></table>';
					if (!empty($existing_source_sample_rows)) {
						echo '<div class="vms-pass-table-scroll vms-pass-upload-preview-table-wrap">';
						echo '<table class="widefat striped vms-pass-upload-preview-table"><thead><tr><th>' . esc_html__('Name', 'backstage-outreach') . '</th><th>' . esc_html__('Email', 'backstage-outreach') . '</th><th>' . esc_html__('Phone', 'backstage-outreach') . '</th><th>' . esc_html__('Company', 'backstage-outreach') . '</th><th>' . esc_html__('Group', 'backstage-outreach') . '</th></tr></thead><tbody>';
						foreach ($existing_source_sample_rows as $sample_row) {
							echo '<tr>';
							echo '<td>' . esc_html(sanitize_text_field((string) ($sample_row['full_name'] ?? '')) ?: '—') . '</td>';
							echo '<td>' . esc_html(sanitize_text_field((string) ($sample_row['email'] ?? '')) ?: '—') . '</td>';
							echo '<td>' . esc_html(sanitize_text_field((string) ($sample_row['phone'] ?? '')) ?: '—') . '</td>';
							echo '<td>' . esc_html(sanitize_text_field((string) ($sample_row['company'] ?? '')) ?: '—') . '</td>';
							echo '<td>' . esc_html(sanitize_text_field((string) ($sample_row['group_label'] ?? '')) ?: '—') . '</td>';
							echo '</tr>';
						}
						echo '</tbody></table>';
						echo '</div>';
					}
					echo '<p class="description" data-vms-preview-stale-note hidden>' . esc_html__('Guest Pass Offer changed after this preview. Refresh Recipient Preview before creating the campaign.', 'backstage-outreach') . '</p>';
					echo '</div>';
				}
				echo '</div>';

				echo '<div id="' . esc_attr(vms_pass_outreach_business_setup_anchor()) . '" class="vms-pass-upload-step vms-pass-business-workflow" data-vms-recipient-source-business' . $hidden_attr(!$show_business_source_fields) . '>';
				echo '<input type="hidden" name="business_batch_touched_fields" value="">';
				echo '<div class="notice notice-warning inline" data-vms-business-draft-conflict hidden><p>' . esc_html__('Some business-batch fields were edited independently, so their values were preserved instead of being replaced by the individual-recipient draft. Review the differences before confirming a new batch.', 'backstage-outreach') . '</p></div>';
				echo '<script type="application/json" data-vms-business-batch-catalog>' . wp_json_encode(array_values($business_batch_catalog), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
				echo '<ol class="vms-pass-business-steps" aria-label="' . esc_attr__('Reusable-business campaign setup steps', 'backstage-outreach') . '">';
				$business_steps = array(
					array(__('Source', 'backstage-outreach'), $selected_business_source_id > 0),
					array(__('Offer batch', 'backstage-outreach'), $selected_business_batch_id > 0),
					array(__('Business review', 'backstage-outreach'), $create_preview_mode === 'business_source' && $business_source_ready_count > 0),
					array(__('Campaign creation', 'backstage-outreach'), false),
					array(__('Business QR generation / print / export', 'backstage-outreach'), false),
				);
				$current_business_step = 1;
				if ($selected_business_source_id > 0) {
					$current_business_step = $selected_business_batch_id > 0 ? 3 : 2;
				}
				if ($create_preview_mode === 'business_source' && $business_source_ready_count > 0) {
					$current_business_step = 4;
				}
				foreach ($business_steps as $step_index => $step) {
					$step_number = $step_index + 1;
					$step_classes = !empty($step[1]) ? ' is-complete' : '';
					if ($step_number === $current_business_step) {
						$step_classes .= ' is-current';
					}
					echo '<li class="' . esc_attr(trim($step_classes)) . '"' . ($step_number === $current_business_step ? ' aria-current="step"' : '') . '><span>' . esc_html((string) $step_number) . '</span>' . esc_html((string) $step[0]) . '</li>';
				}
				echo '</ol>';
				echo '<div class="vms-pass-grid">';
				echo '<label class="vms-pass-span-2' . (!empty($field_errors['related_source_id']) ? ' vms-pass-field-has-error' : '') . '">' . $render_label(__('Step 1 — Business Source', 'backstage-outreach'), array(
					'required' => true,
					'help' => __('Choose the Source whose current active reusable-business memberships should receive one reviewed link / QR each.', 'backstage-outreach'),
				)) . '<select name="related_source_id" required data-vms-business-source aria-describedby="vms-outreach-business-guidance"' . (!empty($field_errors['related_source_id']) ? ' aria-invalid="true"' : '') . '>';
				echo '<option value="0">' . esc_html__('Select business Source', 'backstage-outreach') . '</option>';
				foreach ((array) $sources as $source) {
					$source_id = absint($source['id'] ?? 0);
					if ($source_id <= 0) {
						continue;
					}
					echo '<option value="' . esc_attr((string) $source_id) . '"' . selected($selected_business_source_id, $source_id, false) . '>' . esc_html((string) ($source['source_name'] ?? '')) . '</option>';
				}
				echo '</select>' . $render_messages($field_errors, array('related_source_id')) . '</label>';
				echo '<label class="vms-pass-span-2' . (!empty($field_errors['related_batch_id']) ? ' vms-pass-field-has-error' : '') . '">' . $render_label(__('Step 2 — Eligible Offer Batch', 'backstage-outreach'), array(
					'required' => true,
					'help' => __('Only active Free, Percentage Off, or Fixed Amount Off batches owned by this Source are available.', 'backstage-outreach'),
				)) . '<select name="related_batch_id" required data-vms-business-batch aria-describedby="vms-outreach-business-guidance"' . (!empty($field_errors['related_batch_id']) ? ' aria-invalid="true"' : '') . '>';
				echo '<option value="0">' . esc_html__('Select an eligible offer batch', 'backstage-outreach') . '</option>';
				foreach ($eligible_business_batches as $batch) {
					$entry = vms_pass_outreach_business_batch_catalog_entry($batch);
					echo '<option value="' . esc_attr((string) $entry['id']) . '"' . selected($selected_business_batch_id, (int) $entry['id'], false) . '>' . esc_html((string) $entry['option_label']) . '</option>';
				}
				echo '</select>' . $render_messages($field_errors, array('related_batch_id')) . '</label>';
				$selected_batch_entry = $selected_business_batch_id > 0 && isset($business_batch_catalog[$selected_business_batch_id]) ? $business_batch_catalog[$selected_business_batch_id] : array();
				echo '<fieldset class="vms-pass-span-2 vms-pass-section-fieldset vms-pass-limit-group" data-vms-business-limit-group><legend>' . esc_html__('Admission limits', 'backstage-outreach') . '</legend><div class="vms-pass-grid">';
				echo '<label class="' . (!empty($field_errors['business_batch_admissions_per_link']) ? 'vms-pass-field-has-error' : '') . '">' . $render_label(__('Admissions per customer', 'backstage-outreach'), array('required' => true, 'help' => __('The maximum admissions one customer can claim or purchase through a business link.', 'backstage-outreach'))) . '<input type="number" min="1" max="100" name="business_batch_admissions_per_link" value="' . esc_attr(!empty($selected_batch_entry) ? (string) absint($selected_batch_entry['admissions_per_link'] ?? 0) : $business_batch_value('business_batch_admissions_per_link')) . '"' . disabled(!empty($selected_batch_entry), true, false) . (!empty($field_errors['business_batch_admissions_per_link']) ? ' aria-invalid="true"' : '') . '>' . $render_messages($field_errors, array('business_batch_admissions_per_link')) . '</label>';
				echo '<label class="' . (!empty($field_errors['business_batch_per_business_admission_cap']) ? 'vms-pass-field-has-error' : '') . '">' . $render_label(__('Total admissions allowed per business', 'backstage-outreach'), array('help' => __('Optional. This counts admitted people, not customers or orders. Leave 0 for no separate per-business limit.', 'backstage-outreach'))) . '<input type="number" min="0" max="50000" name="business_batch_per_business_admission_cap" value="' . esc_attr((string) vms_pass_outreach_payload_value($form_payload, 'business_admission_cap', $business_batch_value('business_batch_per_business_admission_cap', '0'))) . '"' . (!empty($field_errors['business_batch_per_business_admission_cap']) ? ' aria-invalid="true"' : '') . '>' . $render_messages($field_errors, array('business_batch_per_business_admission_cap')) . '</label>';
				echo '<label class="' . (!empty($field_errors['business_batch_total_admission_cap']) ? 'vms-pass-field-has-error' : '') . '">' . $render_label(__('Total admissions available across all businesses', 'backstage-outreach'), array('required' => true, 'help' => __('The maximum number of admissions available through this offer across all participating businesses combined. For example, 100 allows up to 100 admissions in total. Each person counts toward the limit.', 'backstage-outreach'))) . '<input type="number" min="1" max="50000" name="business_batch_total_admission_cap" value="' . esc_attr(!empty($selected_batch_entry) ? (string) absint($selected_batch_entry['total_admission_cap'] ?? 0) : $business_batch_value('business_batch_total_admission_cap')) . '"' . disabled(!empty($selected_batch_entry), true, false) . (!empty($field_errors['business_batch_total_admission_cap']) ? ' aria-invalid="true"' : '') . '>' . $render_messages($field_errors, array('business_batch_total_admission_cap')) . '</label>';
				echo '</div><p class="description">' . esc_html__('The overall pool is shared. A per-business maximum does not reserve admissions, so the shared pool can run out before every business reaches its individual maximum.', 'backstage-outreach') . '</p></fieldset>';
				echo '</div>';
				echo '<div class="vms-pass-business-batch-summary" data-vms-business-batch-summary' . $hidden_attr(empty($selected_batch_entry)) . '>';
				if (!empty($selected_batch_entry)) {
					echo '<strong>' . esc_html((string) $selected_batch_entry['offer_label']) . '</strong>';
					echo '<span>' . esc_html(sprintf(__('Admissions per customer: %1$d. Total admissions available across all businesses: %2$d. Scope: %3$s.', 'backstage-outreach'), absint($selected_batch_entry['admissions_per_link'] ?? 0), absint($selected_batch_entry['total_admission_cap'] ?? 0), (string) ($selected_batch_entry['scope_label'] ?? ''))) . '</span>';
				}
				echo '</div>';
				$business_pair_ready = $selected_business_source_id > 0 && $selected_business_batch_id > 0;
				$business_guidance = $selected_business_source_id <= 0
					? __('Select a business Source to see its eligible offer batches.', 'backstage-outreach')
					: (empty($eligible_business_batches)
						? ''
						: ($selected_business_batch_id <= 0 ? __('Select an eligible offer batch before previewing active businesses.', 'backstage-outreach') : __('Source and offer batch are ready. Preview every active business before creating the campaign.', 'backstage-outreach')));
				echo '<p id="vms-outreach-business-guidance" class="vms-pass-business-guidance" role="status" aria-live="polite" data-vms-business-guidance' . $hidden_attr($business_guidance === '') . '>' . esc_html($business_guidance) . '</p>';
				echo '<div class="vms-pass-business-empty" role="status" aria-live="polite" data-vms-business-empty' . $hidden_attr($selected_business_source_id <= 0 || !empty($eligible_business_batches)) . '><p><strong>' . esc_html__('No eligible offer batch for this Source', 'backstage-outreach') . '</strong></p><p>' . esc_html__('Create the batch definition here, review it, and explicitly confirm it. This does not create individual claim links.', 'backstage-outreach') . '</p><button type="button" class="button" data-vms-open-business-batch>' . esc_html__('Create an offer batch for this Source', 'backstage-outreach') . '</button></div>';
				echo '<details class="vms-pass-business-batch-create" data-vms-business-batch-create' . (!empty($business_batch_form) || !empty($business_batch_review) ? ' open' : '') . '><summary>' . esc_html__('New offer batch setup', 'backstage-outreach') . '</summary>';
				echo '<p class="description">' . esc_html__('Reviewing is read-only. The batch definition is created only after explicit confirmation. Business QR count comes from the later reviewed membership list. No individual claim links are generated here.', 'backstage-outreach') . '</p>';
				echo '<div class="vms-pass-grid">';
				echo '<label class="vms-pass-span-2' . (!empty($field_errors['business_batch_name']) ? ' vms-pass-field-has-error' : '') . '">' . $render_label(__('Batch Name', 'backstage-outreach'), array('required' => true)) . '<input type="text" name="business_batch_name" value="' . esc_attr($business_batch_value('business_batch_name')) . '"' . (!empty($field_errors['business_batch_name']) ? ' aria-invalid="true"' : '') . '>' . $render_messages($field_errors, array('business_batch_name')) . '</label>';
				echo '<label class="' . (!empty($field_errors['business_batch_offer_type']) ? 'vms-pass-field-has-error' : '') . '">' . $render_label(__('Offer', 'backstage-outreach'), array('required' => true, 'help' => __('Choose complimentary admission, an operator-entered percentage off, or a fixed dollar amount off each eligible admission. Create multiple batches when you need different event/date restrictions or limits.', 'backstage-outreach'))) . '<select name="business_batch_offer_type"' . (!empty($field_errors['business_batch_offer_type']) ? ' aria-invalid="true"' : '') . '><option value="">' . esc_html__('Select offer', 'backstage-outreach') . '</option><option value="free"' . selected($business_batch_value('business_batch_offer_type'), 'free', false) . '>' . esc_html__('Complimentary admission', 'backstage-outreach') . '</option><option value="percent"' . selected($business_batch_value('business_batch_offer_type'), 'percent', false) . '>' . esc_html__('Percentage off', 'backstage-outreach') . '</option><option value="fixed"' . selected($business_batch_value('business_batch_offer_type'), 'fixed', false) . '>' . esc_html__('Fixed dollar amount off each admission', 'backstage-outreach') . '</option></select>' . $render_messages($field_errors, array('business_batch_offer_type')) . '</label>';
				$business_offer_type = sanitize_key($business_batch_value('business_batch_offer_type'));
				$business_offer_amount_visible = in_array($business_offer_type, array('percent', 'fixed'), true);
				echo '<label class="' . (!empty($field_errors['business_batch_offer_amount']) ? 'vms-pass-field-has-error' : '') . '" data-vms-business-offer-amount' . $hidden_attr(!$business_offer_amount_visible) . '><span data-vms-business-offer-amount-label>' . esc_html($business_offer_type === 'fixed' ? __('Amount off each admission', 'backstage-outreach') : __('Percentage off', 'backstage-outreach')) . '</span><span class="vms-pass-inline-value-field"><span aria-hidden="true" data-vms-business-offer-prefix' . $hidden_attr($business_offer_type !== 'fixed') . '>$</span><input type="number" min="0.01"' . ($business_offer_type === 'percent' ? ' max="100"' : ($business_offer_type === 'fixed' ? ' max="99999999.99"' : '')) . ' step="0.01" inputmode="decimal" name="business_batch_offer_amount" value="' . esc_attr($business_batch_value('business_batch_offer_amount')) . '"' . disabled(!$business_offer_amount_visible, true, false) . ($business_offer_amount_visible ? ' required' : '') . (!empty($field_errors['business_batch_offer_amount']) ? ' aria-invalid="true"' : '') . '><span aria-hidden="true" data-vms-business-offer-suffix' . $hidden_attr($business_offer_type !== 'percent') . '>%</span></span><span class="description" data-vms-business-offer-amount-help>' . esc_html($business_offer_type === 'fixed' ? __('Enter the dollar amount deducted from each eligible admission. A ticket is never reduced below $0.', 'backstage-outreach') : __('Enter a percentage greater than 0 and up to 100.', 'backstage-outreach')) . '</span>' . $render_messages($field_errors, array('business_batch_offer_amount')) . '</label>';
				echo '<label class="vms-pass-span-2' . (!empty($field_errors['business_batch_validity_type']) ? ' vms-pass-field-has-error' : '') . '">' . $render_label(__('Applies To', 'backstage-outreach'), array('required' => true)) . '<select name="business_batch_validity_type"' . (!empty($field_errors['business_batch_validity_type']) ? ' aria-invalid="true"' : '') . '><option value="">' . esc_html__('Select scope', 'backstage-outreach') . '</option>';
				foreach (vms_pass_outreach_form_validity_labels($business_batch_value('business_batch_validity_type')) as $key => $label) {
					echo '<option value="' . esc_attr((string) $key) . '"' . selected($business_batch_value('business_batch_validity_type'), (string) $key, false) . '>' . esc_html((string) $label) . '</option>';
				}
				echo '</select>' . $render_messages($field_errors, array('business_batch_validity_type')) . '</label>';
				echo '<label class="vms-pass-span-2' . (!empty($field_errors['business_batch_single_event_plan_id']) ? ' vms-pass-field-has-error' : '') . '" data-vms-business-scope="single_event"' . $hidden_attr(!$business_scope_visible(array('single_event'))) . '>' . $render_label(__('Event', 'backstage-outreach'), array('required' => true)) . '<select name="business_batch_single_event_plan_id" data-vms-business-scope-control data-vms-required-scope="single_event"' . disabled(!$business_scope_visible(array('single_event')), true, false) . ($business_scope_visible(array('single_event')) ? ' required' : '') . (!empty($field_errors['business_batch_single_event_plan_id']) ? ' aria-invalid="true"' : '') . '><option value="0">' . esc_html__('Select event', 'backstage-outreach') . '</option>';
				foreach ((array) $event_plans as $plan) {
					$plan_id = absint($plan['id'] ?? 0);
					if ($plan_id > 0) {
						echo '<option value="' . esc_attr((string) $plan_id) . '"' . selected(absint($business_batch_value('business_batch_single_event_plan_id')), $plan_id, false) . '>' . esc_html((string) ($plan['title'] ?? __('Event', 'backstage-outreach'))) . '</option>';
					}
				}
				echo '</select>' . $render_messages($field_errors, array('business_batch_single_event_plan_id')) . '</label>';
				echo '<label' . vms_pass_outreach_field_wrapper_class($field_errors, array('business_batch_start_date')) . ' data-vms-business-scope="date_range season"' . $hidden_attr(!$business_scope_visible(array('date_range', 'season'))) . '>' . $render_label(__('Start Date', 'backstage-outreach'), array('help' => __('Required for Date Range. Optional for Season when both start and end are supplied.', 'backstage-outreach'))) . '<input type="date" name="business_batch_start_date" value="' . esc_attr($business_batch_value('business_batch_start_date')) . '" data-vms-business-scope-control data-vms-required-scope="date_range"' . disabled(!$business_scope_visible(array('date_range', 'season')), true, false) . ($business_batch_validity_type === 'date_range' ? ' required' : '') . (!empty($field_errors['business_batch_start_date']) ? ' aria-invalid="true"' : '') . '>' . $render_messages($field_errors, array('business_batch_start_date')) . '</label>';
				echo '<label' . vms_pass_outreach_field_wrapper_class($field_errors, array('business_batch_end_date')) . ' data-vms-business-scope="date_range season"' . $hidden_attr(!$business_scope_visible(array('date_range', 'season'))) . '>' . $render_label(__('End Date', 'backstage-outreach'), array('help' => __('Required for Date Range. Optional for Season when both start and end are supplied.', 'backstage-outreach'))) . '<input type="date" name="business_batch_end_date" value="' . esc_attr($business_batch_value('business_batch_end_date')) . '" data-vms-business-scope-control data-vms-required-scope="date_range"' . disabled(!$business_scope_visible(array('date_range', 'season')), true, false) . ($business_batch_validity_type === 'date_range' ? ' required' : '') . (!empty($field_errors['business_batch_end_date']) ? ' aria-invalid="true"' : '') . '>' . $render_messages($field_errors, array('business_batch_end_date')) . '</label>';
				echo '<label class="vms-pass-span-2' . (!empty($field_errors['business_batch_season_label']) ? ' vms-pass-field-has-error' : '') . '" data-vms-business-scope="season"' . $hidden_attr(!$business_scope_visible(array('season'))) . '>' . $render_label(__('Season Label', 'backstage-outreach'), array('help' => __('Enter a season label, a start and end date, or both.', 'backstage-outreach'))) . '<input type="text" name="business_batch_season_label" value="' . esc_attr($business_batch_value('business_batch_season_label')) . '" data-vms-business-scope-control' . disabled(!$business_scope_visible(array('season')), true, false) . (!empty($field_errors['business_batch_season_label']) ? ' aria-invalid="true"' : '') . '>' . $render_messages($field_errors, array('business_batch_season_label')) . '</label>';
				echo '<label>' . $render_label(__('Offer Expiry (optional)', 'backstage-outreach'), array('help' => __('This expiry is separate from the event scope above.', 'backstage-outreach'))) . '<input type="datetime-local" name="business_batch_expires_at" value="' . esc_attr($business_batch_value('business_batch_expires_at')) . '"></label>';
				echo '<label class="vms-pass-span-2">' . $render_label(__('Batch Notes', 'backstage-outreach')) . '<textarea name="business_batch_notes" rows="3">' . esc_textarea($business_batch_value('business_batch_notes')) . '</textarea></label>';
				echo '</div>';
				echo '<p class="vms-pass-upload-actions"><button type="submit" class="button" name="save_mode" value="business_batch_preview">' . esc_html__('Review New Batch', 'backstage-outreach') . '</button></p>';
				if (!empty($business_batch_review['batch_payload']) && is_array($business_batch_review['batch_payload'])) {
					$reviewed = (array) $business_batch_review['batch_payload'];
					echo '<div class="vms-pass-preview-summary"><h3>' . esc_html__('Read-only Batch Review', 'backstage-outreach') . '</h3><table class="widefat striped"><tbody>';
					$reviewed_rows = array(
						__('Source', 'backstage-outreach') => (string) ($business_batch_review['source_name'] ?? ''),
						__('Batch', 'backstage-outreach') => (string) ($reviewed['batch_name'] ?? ''),
						__('Offer', 'backstage-outreach') => vms_pass_outreach_business_batch_offer_label(array_merge($reviewed, array('id' => 1))),
						__('Scope', 'backstage-outreach') => vms_pass_outreach_business_batch_scope_label($reviewed),
						__('Offer expiry', 'backstage-outreach') => !empty($reviewed['expires_at']) ? (string) $reviewed['expires_at'] : __('No expiry', 'backstage-outreach'),
						__('Business QR count', 'backstage-outreach') => __('Determined by the later reviewed membership list', 'backstage-outreach'),
						__('Admissions per customer', 'backstage-outreach') => (string) absint($reviewed['admissions_per_link'] ?? 0),
						__('Total admissions allowed per business', 'backstage-outreach') => absint($business_batch_review['per_business_admission_cap'] ?? 0) > 0 ? (string) absint($business_batch_review['per_business_admission_cap']) : __('No separate per-business limit', 'backstage-outreach'),
						__('Total admissions available across all businesses', 'backstage-outreach') => (string) absint($reviewed['total_admission_cap'] ?? 0),
					);
					foreach ($reviewed_rows as $label => $value) {
						echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
					}
					echo '</tbody></table><input type="hidden" name="business_batch_review_token" value="' . esc_attr((string) ($business_batch_review['review_token'] ?? '')) . '"><p class="vms-pass-upload-actions"><button type="submit" class="button button-primary" name="save_mode" value="business_batch_commit">' . esc_html__('Confirm and Create Batch Definition', 'backstage-outreach') . '</button></p></div>';
				}
				echo '</details>';
				echo '<p class="description"><strong>' . esc_html__('No email delivery is prepared or sent by this route.', 'backstage-outreach') . '</strong> ' . esc_html__('An email address is reference data only. Businesses without email remain eligible for reusable links.', 'backstage-outreach') . '</p>';
				echo '<p id="vms-outreach-business-review-action" class="vms-pass-upload-actions"><button type="submit" class="' . esc_attr($create_preview_mode === 'business_source' ? 'button' : 'button button-primary') . '" name="save_mode" value="business_source_preview" data-vms-business-preview-button' . disabled(!$business_pair_ready, true, false) . '>' . esc_html($create_preview_mode === 'business_source' ? __('Refresh Business Review', 'backstage-outreach') : __('Preview Active Businesses', 'backstage-outreach')) . '</button></p>';
				if ($create_preview_mode === 'business_source') {
					echo '<div id="' . esc_attr(vms_pass_outreach_create_preview_fragment('business_source')) . '" class="vms-pass-preview-summary vms-pass-upload-preview vms-pass-business-source-preview">';
					echo '<h3>' . esc_html__('Reusable Business Review', 'backstage-outreach') . '</h3>';
					echo '<table class="widefat striped"><tbody>';
					$business_totals = array(
						__('Source', 'backstage-outreach') => sanitize_text_field((string) ($business_source_preview['source_name'] ?? '')),
						__('Batch', 'backstage-outreach') => sanitize_text_field((string) ($business_source_preview['batch_name'] ?? '')),
						__('Batch Offer', 'backstage-outreach') => sanitize_text_field((string) ($business_source_preview['batch_offer_label'] ?? '')),
						__('Scope', 'backstage-outreach') => sanitize_text_field((string) ($business_source_preview['batch_scope_label'] ?? '')),
						__('Active Source Memberships', 'backstage-outreach') => (string) absint($business_source_preview['active_membership_count'] ?? 0),
						__('Eligible for Reusable Links / QRs', 'backstage-outreach') => (string) $business_source_ready_count,
						__('Businesses with Email (reference only)', 'backstage-outreach') => (string) absint($business_source_preview['email_count'] ?? 0),
						__('Email Delivery Unavailable', 'backstage-outreach') => (string) absint($business_source_preview['missing_email_count'] ?? 0),
						__('Admissions per customer', 'backstage-outreach') => (string) absint($business_source_preview['batch_admissions_per_link'] ?? 0),
						__('Total admissions allowed per business', 'backstage-outreach') => absint(vms_pass_outreach_payload_value($form_payload, 'business_admission_cap', 0)) > 0 ? (string) absint(vms_pass_outreach_payload_value($form_payload, 'business_admission_cap', 0)) : __('No separate per-business limit', 'backstage-outreach'),
						__('Total admissions available across all businesses', 'backstage-outreach') => (string) absint($business_source_preview['batch_total_admission_cap'] ?? 0),
					);
					foreach ($business_totals as $label => $value) {
						echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html($value !== '' ? $value : __('Not provided', 'backstage-outreach')) . '</td></tr>';
					}
					echo '</tbody></table>';
					if (!empty($business_source_rows)) {
						echo '<div class="vms-pass-table-scroll vms-pass-table-scroll--business-source"><table class="widefat striped vms-pass-business-source-table"><thead><tr><th>' . esc_html__('Business', 'backstage-outreach') . '</th><th>' . esc_html__('Contact', 'backstage-outreach') . '</th><th>' . esc_html__('Email', 'backstage-outreach') . '</th><th>' . esc_html__('Phone', 'backstage-outreach') . '</th><th>' . esc_html__('Address', 'backstage-outreach') . '</th><th>' . esc_html__('Reusable Link', 'backstage-outreach') . '</th></tr></thead><tbody>';
						foreach ($business_source_rows as $business_row) {
							$business_name = sanitize_text_field((string) ($business_row['business_name'] ?? ''));
							$contact_name = sanitize_text_field((string) ($business_row['contact_name'] ?? ''));
							$email = sanitize_email((string) ($business_row['email'] ?? ''));
							$phone = sanitize_text_field((string) ($business_row['phone'] ?? ''));
							$address = sanitize_text_field((string) ($business_row['address'] ?? ''));
							$notes = sanitize_textarea_field((string) ($business_row['notes'] ?? ''));
							echo '<tr>';
							echo '<td data-label="' . esc_attr__('Business', 'backstage-outreach') . '"><strong>' . esc_html($business_name !== '' ? $business_name : __('Not provided', 'backstage-outreach')) . '</strong>';
							if ($notes !== '') {
								echo '<details><summary>' . esc_html__('Notes', 'backstage-outreach') . '</summary><span>' . nl2br(esc_html($notes)) . '</span></details>';
							}
							echo '</td>';
							echo '<td data-label="' . esc_attr__('Contact', 'backstage-outreach') . '">' . esc_html($contact_name !== '' ? $contact_name : __('Not provided', 'backstage-outreach')) . '</td>';
							echo '<td data-label="' . esc_attr__('Email', 'backstage-outreach') . '">' . esc_html($email !== '' ? $email : __('Not provided', 'backstage-outreach')) . '<span class="description vms-pass-business-email-state">' . esc_html($email !== '' ? __('Reference only - email delivery is not prepared', 'backstage-outreach') : __('Email delivery unavailable', 'backstage-outreach')) . '</span></td>';
							echo '<td data-label="' . esc_attr__('Phone', 'backstage-outreach') . '">' . esc_html($phone !== '' ? $phone : __('Not provided', 'backstage-outreach')) . '</td>';
							echo '<td data-label="' . esc_attr__('Address', 'backstage-outreach') . '">' . esc_html($address !== '' ? $address : __('Not provided', 'backstage-outreach')) . '</td>';
							echo '<td data-label="' . esc_attr__('Reusable Link', 'backstage-outreach') . '"><strong>' . esc_html__('Eligible', 'backstage-outreach') . '</strong></td>';
							echo '</tr>';
						}
						echo '</tbody></table></div>';
					}
					echo '<p class="description" data-vms-preview-stale-note hidden>' . esc_html__('Source or batch changed after this review. Preview the current businesses again before creating the campaign.', 'backstage-outreach') . '</p>';
					echo '</div>';
				}
				echo '<section class="vms-pass-business-campaign-step" aria-labelledby="vms-outreach-business-create-heading"><h3 id="vms-outreach-business-create-heading">' . esc_html__('Step 4 — Create Campaign', 'backstage-outreach') . '</h3>';
				echo '<label class="vms-pass-span-2' . (!empty($field_errors['campaign_name']) ? ' vms-pass-field-has-error' : '') . '">' . $render_label(__('Campaign Name', 'backstage-outreach'), array('required' => true, 'help' => __('Internal name for this reviewed reusable-business campaign. It is required only when creating the campaign, so Source and batch review can happen first.', 'backstage-outreach'))) . '<input type="text" name="business_campaign_name" value="' . esc_attr((string) vms_pass_outreach_payload_value($form_payload, 'campaign_name', '')) . '" placeholder="' . esc_attr__('Business Admission Offer', 'backstage-outreach') . '"' . (!empty($field_errors['campaign_name']) ? ' aria-invalid="true"' : '') . '>' . $render_messages($field_errors, array('campaign_name')) . '</label>';
				echo '<p class="description">' . esc_html__('Create only after reviewing the Source, batch, and complete business list. Continue then opens Step 5 without generating individual claim links.', 'backstage-outreach') . '</p>';
				if ($create_preview_mode === 'business_source' && $create_preview_ready_count > 0) {
					echo '<p class="vms-pass-actions"><button type="submit" class="button button-primary" name="save_mode" value="recipient_commit" data-vms-create-campaign-button="1"' . (!empty($create_preview_setup_snapshot_json) ? ' data-vms-preview-setup="' . esc_attr($create_preview_setup_snapshot_json) . '"' : '') . '>' . esc_html__('Create Campaign and Continue to Business QR Setup', 'backstage-outreach') . '</button></p>';
				}
				echo '</section>';
				echo '</div>';

				echo '<div class="vms-pass-upload-step" data-vms-recipient-source-contacts' . $hidden_attr(!$show_contacts_mode) . '>';
				echo '<div class="vms-pass-grid">';
				echo '<label class="vms-pass-span-2' . (!empty($field_errors['tracking_category_name']) ? ' vms-pass-field-has-error' : '') . '">' . $render_label(__('Source / List Name', 'backstage-outreach'), array(
					'help' => __('Name for the new source list created from the contacts you select below.', 'backstage-outreach'),
				)) . '<input type="text" name="tracking_category_name" value="' . esc_attr((string) vms_pass_outreach_payload_value($form_payload, 'tracking_category_name', '')) . '" placeholder="' . esc_attr__('Preferred Realtor Contacts', 'backstage-outreach') . '">' . $render_messages($field_errors, array('tracking_category_name')) . '</label>';
				echo '<fieldset class="vms-pass-span-2 vms-pass-section-fieldset"><legend>' . esc_html__('Search filters for saved Outreach contacts', 'backstage-outreach') . '</legend><p class="description">' . esc_html__('These fields filter records already managed on the Outreach Contacts screen. They are not an autocomplete selector.', 'backstage-outreach') . '</p><label class="vms-pass-span-2">' . esc_html__('Search name, email, or business', 'backstage-outreach') . '<input type="search" name="search" autocomplete="off" value="' . esc_attr((string) ($contact_create_filters['search'] ?? '')) . '" placeholder="' . esc_attr__('name, email, business, city, source', 'backstage-outreach') . '"></label>';
				if (function_exists('vms_outreach_contact_type_options')) {
					echo '<label>' . esc_html__('Contact Type', 'backstage-outreach') . '<select name="contact_type"><option value="">' . esc_html__('All types', 'backstage-outreach') . '</option>';
					foreach (vms_outreach_contact_type_options() as $type_key => $type_label) {
						echo '<option value="' . esc_attr((string) $type_key) . '"' . selected((string) ($contact_create_filters['contact_type'] ?? ''), (string) $type_key, false) . '>' . esc_html((string) $type_label) . '</option>';
					}
					echo '</select></label>';
				}
				echo '<label>' . esc_html__('Status', 'backstage-outreach') . '<select name="status_scope">';
				foreach ($contact_status_filter_options as $status_key => $status_label_option) {
					echo '<option value="' . esc_attr((string) $status_key) . '"' . selected((string) ($contact_create_filters['status_scope'] ?? 'approved'), (string) $status_key, false) . '>' . esc_html((string) $status_label_option) . '</option>';
				}
				echo '</select></label>';
				echo '<label>' . esc_html__('City', 'backstage-outreach') . '<input type="text" name="city" value="' . esc_attr((string) ($contact_create_filters['city'] ?? '')) . '" placeholder="' . esc_attr__('Austin', 'backstage-outreach') . '"></label>';
				echo '<label>' . esc_html__('Source', 'backstage-outreach') . '<input type="text" name="source" value="' . esc_attr((string) ($contact_create_filters['source'] ?? '')) . '" placeholder="' . esc_attr__('broker list', 'backstage-outreach') . '"></label>';
				echo '<label class="vms-pass-span-2">' . esc_html__('Tag filter', 'backstage-outreach') . '<input type="text" name="tag" value="' . esc_attr((string) ($contact_create_filters['tag'] ?? '')) . '" placeholder="' . esc_attr__('luxury, preferred, north shore', 'backstage-outreach') . '"></label></fieldset>';
				if (function_exists('vms_outreach_contacts_admin_url')) {
					echo '<p class="description">' . wp_kses_post(sprintf(__('Saved contacts are managed on the <a href="%s">Outreach Contacts screen</a>. Preview matching details here, then explicitly select the contacts to add.', 'backstage-outreach'), esc_url(vms_outreach_contacts_admin_url()))) . '</p>';
				}
				echo '</div>';
				echo '<p class="vms-pass-upload-actions"><button type="submit" class="' . esc_attr($create_preview_mode === 'contacts' ? 'button' : 'button button-primary') . '" name="save_mode" value="contacts_preview">' . esc_html($create_preview_mode === 'contacts' ? __('Refresh Preview', 'backstage-outreach') : __('Preview Contacts', 'backstage-outreach')) . '</button></p>';
				if ($create_preview_mode === 'contacts') {
					echo '<div id="' . esc_attr(vms_pass_outreach_create_preview_fragment('contacts')) . '" class="vms-pass-preview-summary vms-pass-upload-preview">';
					echo '<h3>' . esc_html__('Recipient Preview', 'backstage-outreach') . '</h3>';
					echo '<table class="widefat striped"><tbody>';
					$preview_rows = array(
						__('Contacts Matched', 'backstage-outreach') => (string) absint($contact_source_preview['total_contacts'] ?? 0),
						__('Eligible to Add', 'backstage-outreach') => (string) absint($contact_source_preview['eligible_count'] ?? 0),
						__('Selected for Campaign', 'backstage-outreach') => (string) $contact_selected_count,
						__('Missing / Invalid Email', 'backstage-outreach') => (string) absint($contact_source_preview['missing_email_count'] ?? 0),
						__('Globally Suppressed', 'backstage-outreach') => (string) absint($contact_source_preview['globally_suppressed_count'] ?? 0),
						__('Excluded / Do Not Contact', 'backstage-outreach') => (string) absint($contact_source_preview['excluded_count'] ?? 0),
						__('Duplicate Emails', 'backstage-outreach') => (string) absint($contact_source_preview['duplicate_email_count'] ?? 0),
						__('Total Passes Needed', 'backstage-outreach') => (string) absint($upload_batch_preview['total_admission_cap'] ?? 0),
					);
					foreach ($preview_rows as $label => $value) {
						echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html($value !== '' ? $value : __('Not set', 'backstage-outreach')) . '</td></tr>';
					}
					echo '</tbody></table>';
					if (!empty($contact_preview_rows)) {
						echo '<h4>' . esc_html__('Eligible / Skipped Contacts', 'backstage-outreach') . '</h4>';
						echo '<div class="vms-pass-table-scroll"><table class="widefat striped"><thead><tr><th>' . esc_html__('Select', 'backstage-outreach') . '</th><th>' . esc_html__('Contact', 'backstage-outreach') . '</th><th>' . esc_html__('Email', 'backstage-outreach') . '</th><th>' . esc_html__('Business', 'backstage-outreach') . '</th><th>' . esc_html__('Status', 'backstage-outreach') . '</th><th>' . esc_html__('Reason', 'backstage-outreach') . '</th></tr></thead><tbody>';
						foreach ($contact_preview_rows as $row) {
							$contact_id = absint($row['contact_id'] ?? 0);
							$selectable = !empty($row['selectable']) && $contact_id > 0;
							$reason_label = sanitize_text_field((string) ($row['reason_label'] ?? ''));
							$status_label = sanitize_text_field((string) ($row['status'] ?? ''));
							echo '<tr>';
							echo '<td>';
							if ($selectable) {
								echo '<input type="checkbox" name="selected_contact_ids[]" value="' . esc_attr((string) $contact_id) . '"' . checked(isset($contact_selected_lookup[$contact_id]), true, false) . '>';
							} else {
								echo '—';
							}
							echo '</td>';
							echo '<td>' . esc_html(sanitize_text_field((string) ($row['contact_name'] ?? '')) ?: '—') . '</td>';
							echo '<td>' . esc_html(sanitize_text_field((string) ($row['email'] ?? '')) ?: '—') . '</td>';
							echo '<td>' . esc_html(sanitize_text_field((string) ($row['business_name'] ?? '')) ?: '—') . '</td>';
							echo '<td>' . esc_html($status_label !== '' ? $status_label : '—') . '</td>';
							echo '<td>' . esc_html($reason_label !== '' ? $reason_label : __('Eligible', 'backstage-outreach')) . '</td>';
							echo '</tr>';
						}
						echo '</tbody></table></div>';
						echo '<p class="description">' . esc_html__('Select the contacts you want in this campaign, then click Add Selected Contacts.', 'backstage-outreach') . '</p>';
						if (absint($contact_source_preview['eligible_count'] ?? 0) > 0) {
							echo '<p class="vms-pass-upload-actions"><button type="submit" class="button button-primary" name="save_mode" value="contacts_select">' . esc_html__('Add Selected Contacts', 'backstage-outreach') . '</button></p>';
						}
					}
					if (!empty($contact_selected_sample_rows)) {
						echo '<h4>' . esc_html__('Selected Recipients', 'backstage-outreach') . '</h4>';
						echo '<div class="vms-pass-table-scroll vms-pass-upload-preview-table-wrap">';
						echo '<table class="widefat striped vms-pass-upload-preview-table"><thead><tr><th>' . esc_html__('Name', 'backstage-outreach') . '</th><th>' . esc_html__('Email', 'backstage-outreach') . '</th><th>' . esc_html__('Phone', 'backstage-outreach') . '</th><th>' . esc_html__('Company', 'backstage-outreach') . '</th><th>' . esc_html__('Group', 'backstage-outreach') . '</th></tr></thead><tbody>';
						foreach ($contact_selected_sample_rows as $sample_row) {
							echo '<tr>';
							echo '<td>' . esc_html(sanitize_text_field((string) ($sample_row['full_name'] ?? '')) ?: '—') . '</td>';
							echo '<td>' . esc_html(sanitize_text_field((string) ($sample_row['email'] ?? '')) ?: '—') . '</td>';
							echo '<td>' . esc_html(sanitize_text_field((string) ($sample_row['phone'] ?? '')) ?: '—') . '</td>';
							echo '<td>' . esc_html(sanitize_text_field((string) ($sample_row['company'] ?? '')) ?: '—') . '</td>';
							echo '<td>' . esc_html(sanitize_text_field((string) ($sample_row['group_label'] ?? '')) ?: '—') . '</td>';
							echo '</tr>';
						}
						echo '</tbody></table></div>';
					}
					echo '<p class="description" data-vms-preview-stale-note hidden>' . esc_html__('Guest Pass Offer changed after this preview. Refresh Recipient Preview before creating the campaign.', 'backstage-outreach') . '</p>';
					if ($contact_selected_count <= 0) {
						echo '<p class="description">' . esc_html__('Previewed contacts are ready, but you still need to choose which contacts to include in this campaign.', 'backstage-outreach') . '</p>';
					}
					echo '</div>';
				}
				echo '</div>';
				echo '</div>';
			}

			echo '<details ' . $template_section_attrs . ($is_create_screen ? ' data-vms-business-message-secondary' : '') . '>';
			echo vms_pass_outreach_render_collapsible_summary($show_business_source_fields ? __('Business-sharing guidance', 'backstage-outreach') : __('Template / Message', 'backstage-outreach'));
			if ($show_business_source_fields) {
				echo '<p>' . esc_html__('After saving business links in Step 5, continue to Share with businesses. There you can edit a business-contact introduction, review every personalized customer-offer and flyer link, copy messages for contacts without email, and explicitly send only selected eligible addresses.', 'backstage-outreach') . '</p><p class="description">' . esc_html__('Reusable-business sharing does not use individual-recipient templates or reserve individual invitation links.', 'backstage-outreach') . '</p>';
			} else {
		echo '<div class="vms-pass-grid">';
		echo '<label class="vms-pass-span-2' . (!empty($field_errors['email_subject']) ? ' vms-pass-field-has-error' : '') . '">' . $render_label(__('Email Subject', 'backstage-outreach'), array(
			'help' => __('Subject line used for campaign preview, export, and send prep.', 'backstage-outreach'),
		)) . '<input type="text" name="email_subject" value="' . esc_attr((string) vms_pass_outreach_payload_value($form_payload, 'email_subject', vms_pass_outreach_default_email_subject())) . '">' . $render_messages($field_errors, array('email_subject')) . '</label>';
		echo '<label class="vms-pass-span-2' . (!empty($field_errors['message_template']) ? ' vms-pass-field-has-error' : '') . '">' . $render_label(__('Message Template', 'backstage-outreach'), array(
			'help' => trim(__('Outreach message template used for preview, export, and send prep.', 'backstage-outreach') . ' ' . $merge_tag_help_text),
		)) . '<textarea name="message_template" rows="9">' . esc_textarea((string) vms_pass_outreach_payload_value($form_payload, 'message_template', vms_pass_outreach_default_message_template())) . '</textarea>' . $render_messages($field_errors, array('message_template')) . '</label>';
		echo '</div>';
		echo '<div class="vms-pass-preview-summary vms-pass-preview-summary--message">';
		echo '<h3>' . esc_html__('Message Preview - Sample Data Only', 'backstage-outreach') . '</h3>';
		echo '<div class="notice notice-info inline vms-pass-sample-data-notice"><p><strong>' . esc_html__('SAMPLE DATA - NOT A DELIVERY PREVIEW', 'backstage-outreach') . '</strong><br>' . esc_html(sprintf(__('Preview context: %s. This does not confirm email readiness and does not send an email.', 'backstage-outreach'), $preview_context_label)) . '</p></div>';
		echo '<label>' . $render_label(__('Preview Subject', 'backstage-outreach')) . '<input type="text" readonly value="' . esc_attr($preview_subject) . '"></label>';
		echo '<label>' . $render_label(__('Preview Message', 'backstage-outreach')) . '<textarea rows="9" readonly>' . esc_textarea($preview_message) . '</textarea></label>';
		if ($is_create_screen && $create_preview_mode !== '') {
			echo '<div class="vms-pass-callout-actions">';
			echo '<button type="submit" class="button" name="save_mode" value="refresh_message_preview" data-vms-message-preview-button="1" disabled' . (!empty($create_preview_setup_snapshot_json) ? ' data-vms-preview-setup="' . esc_attr($create_preview_setup_snapshot_json) . '"' : '') . (!empty($create_message_preview_snapshot_json) ? ' data-vms-message-preview="' . esc_attr($create_message_preview_snapshot_json) . '"' : '') . '>' . esc_html__('Refresh Preview', 'backstage-outreach') . '</button>';
			echo '</div>';
			echo '<p class="description" data-vms-message-preview-stale-note hidden>' . esc_html__('Email subject or message changed after the last preview. Refresh Preview before creating this campaign.', 'backstage-outreach') . '</p>';
			echo '<p class="description" data-vms-message-preview-recipient-note hidden>' . esc_html__('Guest Pass Offer or recipient source changed after the last recipient preview. Refresh Recipient Preview before refreshing the message preview.', 'backstage-outreach') . '</p>';
		}
		echo '</div>';
			}
		echo '</details>';

		if ($is_create_screen) {
			echo '<details ' . $preview_section_attrs . '>';
			echo vms_pass_outreach_render_collapsible_summary(__('Review & Create', 'backstage-outreach'));
			echo '<div class="vms-pass-preview-stack">';
			echo '<div class="vms-pass-preview-summary" data-vms-individual-review-summary' . $hidden_attr($show_business_source_fields) . '>';
			echo '<h3>' . esc_html__('Recipient Totals', 'backstage-outreach') . '</h3>';
			echo '<table class="widefat striped"><tbody>';
			$review_rows = array(
				__('Recipient Source', 'backstage-outreach') => $create_preview_mode !== '' ? (string) (array(
					'csv_new' => __('Upload CSV / New Source List', 'backstage-outreach'),
					'existing_source' => __('Existing Source List', 'backstage-outreach'),
					'business_source' => __('Reusable Business Links / QRs', 'backstage-outreach'),
					'contacts' => __('Outreach Contacts', 'backstage-outreach'),
				)[$create_preview_mode] ?? __('Not set', 'backstage-outreach')) : __('Not previewed yet', 'backstage-outreach'),
				__('Ready Recipients', 'backstage-outreach') => (string) $create_preview_ready_count,
				__('Passes Per Recipient', 'backstage-outreach') => (string) absint(vms_pass_outreach_payload_value($form_payload, 'admissions_per_recipient', 2)),
				__('Total Passes Needed', 'backstage-outreach') => (string) $create_preview_total_passes,
			);
			foreach ($review_rows as $label => $value) {
				echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html($value !== '' ? $value : __('Not set', 'backstage-outreach')) . '</td></tr>';
			}
			echo '</tbody></table>';
			echo '<p class="description" data-vms-review-stale-note hidden>' . esc_html__('Guest Pass Offer changed after the last recipient preview. Refresh the preview before creating this campaign.', 'backstage-outreach') . '</p>';
			if ($create_preview_mode === '') {
				echo '<p class="description">' . esc_html__('Preview recipients in the Source List section before creating this campaign.', 'backstage-outreach') . '</p>';
			} elseif ($create_preview_mode === 'contacts' && $contact_selected_count <= 0) {
				echo '<p class="description">' . esc_html__('Select contacts and click Add Selected Contacts before creating this campaign.', 'backstage-outreach') . '</p>';
			} elseif ($create_preview_ready_count <= 0) {
				echo '<p class="description">' . esc_html($create_preview_mode === 'business_source' ? __('No active business memberships are ready. Refresh the business review after updating the Source.', 'backstage-outreach') : __('No recipients are ready yet. Refresh the Source List preview after fixing the inputs above.', 'backstage-outreach')) . '</p>';
			}
			echo '</div>';
			echo '<div class="vms-pass-preview-summary" data-vms-business-review-summary' . $hidden_attr(!$show_business_source_fields) . '>';
			echo '<h3>' . esc_html__('Reusable Business Workflow', 'backstage-outreach') . '</h3>';
			if ($create_preview_mode === 'business_source' && $business_source_ready_count > 0) {
				echo '<table class="widefat striped"><tbody>';
				$business_review_rows = array(
					__('Reviewed businesses', 'backstage-outreach') => (string) $business_source_ready_count,
					__('Offer', 'backstage-outreach') => sanitize_text_field((string) ($business_source_preview['batch_offer_label'] ?? '')),
					__('Scope', 'backstage-outreach') => sanitize_text_field((string) ($business_source_preview['batch_scope_label'] ?? '')),
					__('Admissions per customer', 'backstage-outreach') => (string) absint($business_source_preview['batch_admissions_per_link'] ?? 0),
					__('Total admissions allowed per business', 'backstage-outreach') => absint(vms_pass_outreach_payload_value($form_payload, 'business_admission_cap', 0)) > 0 ? (string) absint(vms_pass_outreach_payload_value($form_payload, 'business_admission_cap', 0)) : __('No separate per-business limit', 'backstage-outreach'),
					__('Total admissions across all businesses', 'backstage-outreach') => (string) absint($business_source_preview['batch_total_admission_cap'] ?? 0),
				);
				foreach ($business_review_rows as $label => $value) {
					echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
				}
				echo '</tbody></table><p class="description">' . esc_html__('Business review is complete. Create the campaign to continue directly to QR generation. Shared overall capacity may run out before every business reaches its individual maximum.', 'backstage-outreach') . '</p>';
			} elseif ($selected_business_batch_id > 0) {
				echo '<p class="description" data-vms-business-review-guidance>' . esc_html__('Next: preview every active Source membership in Step 3. Campaign creation stays unavailable until that read-only review succeeds.', 'backstage-outreach') . '</p>';
			} else {
				echo '<p class="description" data-vms-business-review-guidance>' . esc_html__('Next: select an eligible offer batch, or review and explicitly confirm a new batch definition. Business Review becomes available after confirmation.', 'backstage-outreach') . '</p>';
			}
			echo '<p class="description" data-vms-review-stale-note hidden>' . esc_html__('The Source, offer batch, limits, or memberships changed after review. Preview every active business again.', 'backstage-outreach') . '</p>';
			echo '</div>';
			echo '</div>';
			echo '</details>';
		}

		echo '</div>';
		echo '<div class="vms-pass-preview-summary vms-pass-preview-summary--actions"' . ($is_create_screen ? ' data-vms-nonbusiness-create-actions' . $hidden_attr($show_business_source_fields) : '') . '>';
		echo '<h3>' . esc_html($is_create_screen ? __('Create Campaign', 'backstage-outreach') : __('Save Campaign', 'backstage-outreach')) . '</h3>';
		if ($is_create_screen) {
			echo '<p class="description">' . esc_html__('Create actions stay visible here while you review the campaign details above.', 'backstage-outreach') . '</p>';
		} else {
			echo '<p class="description">' . esc_html__('Save actions stay visible here and are never hidden inside collapsed sections.', 'backstage-outreach') . '</p>';
		}
		echo '<p class="vms-pass-actions">';
		if ($is_create_screen) {
			if ($create_preview_mode !== '' && $create_preview_mode !== 'business_source' && $create_preview_ready_count > 0) {
				echo '<button type="submit" class="button button-primary" name="save_mode" value="recipient_commit" data-vms-create-campaign-button="1"' . (!empty($create_preview_setup_snapshot_json) ? ' data-vms-preview-setup="' . esc_attr($create_preview_setup_snapshot_json) . '"' : '') . ($create_preview_mode !== 'business_source' && !empty($create_message_preview_snapshot_json) ? ' data-vms-message-preview="' . esc_attr($create_message_preview_snapshot_json) . '"' : '') . '>' . esc_html($create_preview_mode === 'business_source' ? __('Create Campaign and Continue to Business QR Setup', 'backstage-outreach') : __('Create Campaign', 'backstage-outreach')) . '</button> ';
			}
		} else {
			echo '<button type="submit" class="button button-primary" name="save_mode" value="standard">' . esc_html__('Save Campaign', 'backstage-outreach') . '</button> ';
		}
		if ($campaign_id > 0) {
			echo '<a class="button" href="' . esc_url(vms_pass_outreach_admin_page_url()) . '">' . esc_html__('New Campaign', 'backstage-outreach') . '</a>';
		}
		echo '</p>';
		if ($is_create_screen && $create_preview_mode !== '' && $create_preview_ready_count > 0 && $create_preview_mode !== 'business_source') {
			echo '<p class="description" data-vms-create-message-preview-note hidden>' . esc_html__('Refresh the message preview before creating this campaign.', 'backstage-outreach') . '</p>';
			echo '<p class="description" data-vms-create-recipient-preview-note hidden>' . esc_html__('Refresh Recipient Preview before creating this campaign.', 'backstage-outreach') . '</p>';
		}
		echo '</div>';

		if ($is_create_screen) {
			echo '<script>';
			echo '(function(){';
			echo 'var root=document.getElementById("vms-outreach-campaign-form");';
			echo 'if(!root){return;}';
			echo 'var businessRoot=root.querySelector("[data-vms-recipient-source-business]");var businessCatalog=[];var businessReviewInvalidated=false;var catalogNode=businessRoot?businessRoot.querySelector("[data-vms-business-batch-catalog]"):null;try{businessCatalog=catalogNode?JSON.parse(catalogNode.textContent||"[]"):[];}catch(error){businessCatalog=[];}';
			echo 'var businessUi=' . wp_json_encode(array(
				'select_source' => __('Select a business Source to see its eligible offer batches.', 'backstage-outreach'),
				'select_batch' => __('Select an eligible offer batch before previewing active businesses.', 'backstage-outreach'),
				'ready' => __('Source and offer batch are ready. Preview every active business before creating the campaign.', 'backstage-outreach'),
				'create_batch' => __('Next: review and explicitly confirm a new offer batch for this Source. Business Review becomes available after confirmation.', 'backstage-outreach'),
				'option' => __('Select an eligible offer batch', 'backstage-outreach'),
				'limits' => __('Admissions per customer: %1$d. Total admissions available across all businesses: %2$d. Scope: %3$s.', 'backstage-outreach'),
				'percent_amount_label' => __('Percentage off', 'backstage-outreach'),
				'percent_amount_help' => __('Enter a percentage greater than 0 and up to 100.', 'backstage-outreach'),
				'fixed_amount_label' => __('Amount off each admission', 'backstage-outreach'),
				'fixed_amount_help' => __('Enter the dollar amount deducted from each eligible admission. A ticket is never reduced below $0.', 'backstage-outreach'),
			), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';';
			echo 'function namedField(name){return root.querySelector(\'[name="\'+name+\'"]:not([disabled])\');}';
			echo 'function selectedValue(name){var input=root.querySelector(\'input[name="\'+name+\'"]:checked:not([disabled])\');if(input){return input.value;}var field=namedField(name);return field?field.value:"";}';
			echo 'function toggleByAttr(attr, show){var nodes=root.querySelectorAll("["+attr+"]");nodes.forEach(function(node){node.hidden=!show;node.querySelectorAll("input,select,textarea,button").forEach(function(field){if(field.hasAttribute("data-vms-create-campaign-button")&&attr!=="data-vms-nonbusiness-create-actions"){return;}field.disabled=!show;});});}';
			echo 'function canonicalSnapshot(value){if(!value){return "";}try{return JSON.stringify(JSON.parse(value));}catch(error){return String(value||"");}}';
			echo 'function updatePurpose(){var purpose=selectedValue("campaign_purpose_select")||root.querySelector(\'input[name="campaign_purpose"]\').value||"guest_pass_invitation";var hidden=root.querySelector(\'input[name="campaign_purpose"]\');if(hidden){hidden.value=purpose;}toggleByAttr("data-vms-purpose-guest-pass",purpose==="guest_pass_invitation");}';
			echo 'function updateValidity(){var select=root.querySelector(\'select[name="validity_type"]\');var value=select?select.value:"";toggleByAttr("data-vms-validity-single-event",value==="single_event");toggleByAttr("data-vms-validity-date-range",value==="date_range"||value==="season");toggleByAttr("data-vms-validity-season",value==="season");}';
			echo 'function businessEntry(id){id=parseInt(id||"0",10)||0;return businessCatalog.find(function(entry){return(parseInt(entry.id,10)||0)===id;})||null;}';
			echo 'function formatBusinessLimits(entry){return String(businessUi.limits||"").replace("%1$d",parseInt(entry.admissions_per_link||0,10)||0).replace("%2$d",parseInt(entry.total_admission_cap||0,10)||0).replace("%3$s",entry.scope_label||"");}';
			echo 'function renderBusinessBatchState(){if(!businessRoot){return;}var source=businessRoot.querySelector("[data-vms-business-source]");var batch=businessRoot.querySelector("[data-vms-business-batch]");var sourceId=parseInt(source&&source.value||"0",10)||0;var entry=businessEntry(batch&&batch.value);var guidance=businessRoot.querySelector("[data-vms-business-guidance]");var reviewGuidance=root.querySelector("[data-vms-business-review-guidance]");var empty=businessRoot.querySelector("[data-vms-business-empty]");var summary=businessRoot.querySelector("[data-vms-business-batch-summary]");var preview=businessRoot.querySelector("[data-vms-business-preview-button]");var eligible=businessCatalog.filter(function(item){return(parseInt(item.source_id,10)||0)===sourceId;});var emptyVisible=sourceId>0&&eligible.length===0;var stateText=sourceId<=0?businessUi.select_source:(emptyVisible?businessUi.create_batch:(!entry?businessUi.select_batch:businessUi.ready));if(guidance){guidance.textContent=stateText;guidance.hidden=emptyVisible;}if(reviewGuidance){reviewGuidance.textContent=stateText;}if(empty){empty.hidden=!emptyVisible;}if(summary){summary.replaceChildren();summary.hidden=!entry;if(entry){var strong=document.createElement("strong");strong.textContent=entry.offer_label||"";var span=document.createElement("span");span.textContent=formatBusinessLimits(entry);summary.append(strong,span);}}var customer=businessRoot.querySelector(\'[name="business_batch_admissions_per_link"]\');var overall=businessRoot.querySelector(\'[name="business_batch_total_admission_cap"]\');[customer,overall].forEach(function(field){if(field&&!field.dataset.newBatchDraft){field.dataset.newBatchDraft=field.value||"";}});if(entry){if(customer){customer.value=parseInt(entry.admissions_per_link||0,10)||0;customer.disabled=true;}if(overall){overall.value=parseInt(entry.total_admission_cap||0,10)||0;overall.disabled=true;}}else{if(customer){customer.disabled=false;customer.value=customer.dataset.newBatchDraft||customer.value;}if(overall){overall.disabled=false;overall.value=overall.dataset.newBatchDraft||overall.value;}}if(preview){preview.disabled=sourceId<=0||!entry;}}';
			echo 'function updateBusinessBatchScope(){if(!businessRoot){return;}var select=businessRoot.querySelector(\'[name="business_batch_validity_type"]\');var value=select?select.value:"";businessRoot.querySelectorAll("[data-vms-business-scope]").forEach(function(node){var scopes=String(node.getAttribute("data-vms-business-scope")||"").split(/\\s+/);var show=scopes.indexOf(value)!==-1;node.hidden=!show;node.querySelectorAll("[data-vms-business-scope-control]").forEach(function(field){field.disabled=!show;var requiredScopes=String(field.getAttribute("data-vms-required-scope")||"").split(/\\s+/);field.required=show&&requiredScopes.indexOf(value)!==-1;});});}';
			echo 'function updateBusinessOffer(){if(!businessRoot){return;}var select=businessRoot.querySelector(\'[name="business_batch_offer_type"]\');var type=select?select.value:"";var wrapper=businessRoot.querySelector("[data-vms-business-offer-amount]");if(!wrapper){return;}var input=wrapper.querySelector(\'[name="business_batch_offer_amount"]\');var label=wrapper.querySelector("[data-vms-business-offer-amount-label]");var help=wrapper.querySelector("[data-vms-business-offer-amount-help]");var prefix=wrapper.querySelector("[data-vms-business-offer-prefix]");var suffix=wrapper.querySelector("[data-vms-business-offer-suffix]");var show=type==="percent"||type==="fixed";wrapper.hidden=!show;if(input){input.disabled=!show;input.required=show;if(type==="percent"){input.max="100";}else if(type==="fixed"){input.max="99999999.99";}else{input.removeAttribute("max");}}if(label){label.textContent=type==="fixed"?businessUi.fixed_amount_label:businessUi.percent_amount_label;}if(help){help.textContent=type==="fixed"?businessUi.fixed_amount_help:businessUi.percent_amount_help;}if(prefix){prefix.hidden=type!=="fixed";}if(suffix){suffix.hidden=type!=="percent";}}';
			echo 'function enableBusinessScopeDrafts(){if(!businessRoot){return;}businessRoot.querySelectorAll("[data-vms-business-scope-control]").forEach(function(field){field.disabled=false;});}';
			echo 'function syncBusinessBatches(initial){if(!businessRoot){return;}var source=businessRoot.querySelector("[data-vms-business-source]");var batch=businessRoot.querySelector("[data-vms-business-batch]");if(!source||!batch){return;}var sourceId=parseInt(source.value||"0",10)||0;var previous=parseInt(batch.value||"0",10)||0;var eligible=businessCatalog.filter(function(entry){return(parseInt(entry.source_id,10)||0)===sourceId;});batch.replaceChildren();var placeholder=document.createElement("option");placeholder.value="0";placeholder.textContent=businessUi.option;batch.appendChild(placeholder);eligible.forEach(function(entry){var option=document.createElement("option");option.value=String(entry.id);option.textContent=entry.option_label||entry.name||String(entry.id);batch.appendChild(option);});if(eligible.some(function(entry){return(parseInt(entry.id,10)||0)===previous;})){batch.value=String(previous);}else{batch.value="0";if(!initial&&previous>0){businessReviewInvalidated=true;}}renderBusinessBatchState();}';
			echo 'function syncBusinessCampaignName(mode){var businessName=businessRoot?businessRoot.querySelector(\'[name="business_campaign_name"]\'):null;var standardName=root.querySelector(\'[data-vms-nonbusiness-campaign-fields] [name="campaign_name"]\');if(mode==="business_source"&&businessName&&standardName&&!businessName.value){businessName.value=standardName.value;}if(mode!=="business_source"&&businessName&&standardName&&!standardName.value){standardName.value=businessName.value;}}';
			echo 'var businessDraftMap={business_batch_admissions_per_link:"admissions_per_recipient",business_batch_total_admission_cap:"total_admission_cap",business_batch_validity_type:"validity_type",business_batch_single_event_plan_id:"single_event_plan_id",business_batch_start_date:"start_date",business_batch_end_date:"end_date",business_batch_season_label:"season_label",business_batch_expires_at:"expires_at"};var businessTouched={};';
			echo 'function recordBusinessTouched(name){businessTouched[name]=true;var hidden=businessRoot?businessRoot.querySelector(\'[name="business_batch_touched_fields"]\'):null;if(hidden){hidden.value=Object.keys(businessTouched).join(",");}}';
			echo 'function syncBusinessOfferDraft(){if(!businessRoot){return;}var batch=businessRoot.querySelector("[data-vms-business-batch]");if(batch&&(parseInt(batch.value||"0",10)||0)>0){return;}var touchedField=businessRoot.querySelector(\'[name="business_batch_touched_fields"]\');String(touchedField?touchedField.value:"").split(",").filter(Boolean).forEach(function(name){businessTouched[name]=true;});var conflict=false;Object.keys(businessDraftMap).forEach(function(targetName){var sourceName=businessDraftMap[targetName];var source=root.querySelector(\'[data-vms-nonbusiness-offer-fields] [name="\'+sourceName+\'"]\');var target=businessRoot.querySelector(\'[name="\'+targetName+\'"]\');if(!source||!target||!source.value){return;}if(!businessTouched[targetName]){target.value=source.value;return;}if(target.value!==source.value){conflict=true;}});var note=document.querySelector("[data-vms-business-draft-conflict]");if(note){note.hidden=!conflict;}updateBusinessBatchScope();}';
			echo 'function updateRecipientSource(){var mode=selectedValue("recipient_source_mode")||"csv_new";var trackingInput=root.querySelector("[data-vms-tracking-mode-input]");syncBusinessCampaignName(mode);toggleByAttr("data-vms-recipient-source-csv",mode==="csv_new");toggleByAttr("data-vms-recipient-source-existing",mode==="existing_source");toggleByAttr("data-vms-recipient-source-business",mode==="business_source");toggleByAttr("data-vms-recipient-source-contacts",mode==="contacts");toggleByAttr("data-vms-nonbusiness-campaign-fields",mode!=="business_source");toggleByAttr("data-vms-nonbusiness-offer-fields",mode!=="business_source");toggleByAttr("data-vms-nonbusiness-create-actions",mode!=="business_source");toggleByAttr("data-vms-business-review-summary",mode==="business_source");toggleByAttr("data-vms-individual-review-summary",mode!=="business_source");var draftConflict=document.querySelector("[data-vms-business-draft-conflict]");if(draftConflict&&mode!=="business_source"){draftConflict.hidden=true;}var messageSection=root.querySelector("[data-vms-business-message-secondary]");if(messageSection&&mode==="business_source"){messageSection.open=false;}if(trackingInput){trackingInput.value=(mode==="existing_source"||mode==="business_source")?"existing":"new";}if(mode==="business_source"){syncBusinessOfferDraft();syncBusinessBatches(true);}}';
			echo 'function currentPreviewSetup(){var mode=selectedValue("recipient_source_mode")||"csv_new";var sourceField=mode==="business_source"&&businessRoot?businessRoot.querySelector("[data-vms-business-source]"):namedField("related_source_id");var batchField=mode==="business_source"&&businessRoot?businessRoot.querySelector("[data-vms-business-batch]"):namedField("related_batch_id");var entry=mode==="business_source"?businessEntry(batchField&&batchField.value):null;var businessCap=businessRoot?businessRoot.querySelector(\'[name="business_batch_per_business_admission_cap"]\'):null;return JSON.stringify({recipient_source_mode:mode,related_source_id:parseInt((sourceField||{}).value||"0",10)||0,related_batch_id:parseInt((batchField||{}).value||"0",10)||0,tracking_category_mode:(mode==="existing_source"||mode==="business_source")?"existing":"new",tracking_category_name:mode==="business_source"?"":(((namedField("tracking_category_name")||{}).value||"").trim()),admissions_per_recipient:entry?(parseInt(entry.admissions_per_link||1,10)||1):(parseInt((namedField("admissions_per_recipient")||{}).value||"0",10)||0),validity_type:entry?(entry.validity_type||""):((namedField("validity_type")||{}).value||""),single_event_plan_id:entry?(parseInt(entry.single_event_plan_id||0,10)||0):(parseInt((namedField("single_event_plan_id")||{}).value||"0",10)||0),start_date:entry?(entry.start_date||""):((namedField("start_date")||{}).value||""),end_date:entry?(entry.end_date||""):((namedField("end_date")||{}).value||""),season_label:entry?(entry.season_label||""):(((namedField("season_label")||{}).value||"").trim()),business_admission_cap:mode==="business_source"?(parseInt((businessCap||{}).value||"0",10)||0):0});}';
			echo 'function currentMessagePreview(){return JSON.stringify({email_subject:((namedField("email_subject")||{}).value||""),message_template:(((namedField("message_template")||{}).value||"").replace(/\\r\\n/g,"\\n"))});}';
			echo 'function updatePreviewStale(){var createButton=root.querySelector("[data-vms-create-campaign-button]");var messageButton=root.querySelector("[data-vms-message-preview-button]");var recipientSnapshot=createButton?(createButton.getAttribute("data-vms-preview-setup")||""):(messageButton?(messageButton.getAttribute("data-vms-preview-setup")||""):"");var messageSnapshot=createButton?(createButton.getAttribute("data-vms-message-preview")||""):(messageButton?(messageButton.getAttribute("data-vms-message-preview")||""):"");recipientSnapshot=canonicalSnapshot(recipientSnapshot);messageSnapshot=canonicalSnapshot(messageSnapshot);var recipientStale=businessReviewInvalidated||(recipientSnapshot!==""&&recipientSnapshot!==currentPreviewSetup());var messageStale=messageSnapshot!==""&&messageSnapshot!==currentMessagePreview();if(createButton){createButton.disabled=recipientStale||messageStale;}if(messageButton){messageButton.disabled=recipientStale||!messageStale;}root.querySelectorAll("[data-vms-preview-stale-note],[data-vms-review-stale-note],[data-vms-message-preview-recipient-note],[data-vms-create-recipient-preview-note]").forEach(function(node){node.hidden=!recipientStale;});root.querySelectorAll("[data-vms-message-preview-stale-note],[data-vms-create-message-preview-note]").forEach(function(node){node.hidden=recipientStale||!messageStale;});}';
			echo 'root.querySelectorAll(\'input[name="campaign_purpose_select"]\').forEach(function(node){node.addEventListener("change",updatePurpose);});';
			echo 'root.querySelectorAll(\'input[name="recipient_source_mode"]\').forEach(function(node){node.addEventListener("change",function(){updateRecipientSource();updatePreviewStale();});});';
			echo 'if(businessRoot){var businessSource=businessRoot.querySelector("[data-vms-business-source]");var businessBatch=businessRoot.querySelector("[data-vms-business-batch]");var businessScope=businessRoot.querySelector(\'[name="business_batch_validity_type"]\');var businessOffer=businessRoot.querySelector(\'[name="business_batch_offer_type"]\');var openBatch=businessRoot.querySelector("[data-vms-open-business-batch]");Object.keys(businessDraftMap).forEach(function(name){var field=businessRoot.querySelector(\'[name="\'+name+\'"]\');var source=root.querySelector(\'[data-vms-nonbusiness-offer-fields] [name="\'+businessDraftMap[name]+\'"]\');if(field&&source&&field.value&&source.value&&field.value!==source.value){recordBusinessTouched(name);}if(field){field.addEventListener("input",function(){recordBusinessTouched(name);businessReviewInvalidated=true;updatePreviewStale();});field.addEventListener("change",function(){recordBusinessTouched(name);businessReviewInvalidated=true;updatePreviewStale();});}});if(businessSource){businessSource.addEventListener("change",function(){businessReviewInvalidated=true;syncBusinessBatches(false);updatePreviewStale();});}if(businessBatch){businessBatch.addEventListener("change",function(){businessReviewInvalidated=true;renderBusinessBatchState();updatePreviewStale();});}if(businessScope){businessScope.addEventListener("change",updateBusinessBatchScope);}if(businessOffer){businessOffer.addEventListener("change",updateBusinessOffer);}if(openBatch){openBatch.addEventListener("click",function(){var details=businessRoot.querySelector("[data-vms-business-batch-create]");if(details){details.open=true;var first=details.querySelector("input,select,textarea");if(first){first.focus({preventScroll:true});first.scrollIntoView({block:"center",behavior:window.matchMedia("(prefers-reduced-motion: reduce)").matches?"auto":"smooth"});}}});}var createForm=root.querySelector("form");if(createForm){createForm.addEventListener("submit",function(event){var submitter=event.submitter;if(!submitter){return;}if(submitter.value==="business_batch_preview"){enableBusinessScopeDrafts();}if(submitter.value==="recipient_commit"&&selectedValue("recipient_source_mode")==="business_source"){var campaignName=businessRoot.querySelector(\'[name="business_campaign_name"]\');if(campaignName&&!campaignName.value.trim()){event.preventDefault();campaignName.setAttribute("aria-invalid","true");campaignName.focus();return;}}if(submitter.value!=="business_source_preview"){return;}var sourceId=parseInt(businessSource&&businessSource.value||"0",10)||0;var entry=businessEntry(businessBatch&&businessBatch.value);if(sourceId>0&&entry){return;}event.preventDefault();var invalid=sourceId<=0?businessSource:businessBatch;if(invalid){invalid.setAttribute("aria-invalid","true");invalid.focus();}renderBusinessBatchState();});}}';
			echo 'var validitySelect=root.querySelector(\'select[name="validity_type"]\');if(validitySelect){validitySelect.addEventListener("change",updateValidity);}';
			echo '[\'related_source_id\',\'related_batch_id\',\'tracking_category_name\',\'admissions_per_recipient\',\'single_event_plan_id\',\'start_date\',\'end_date\',\'season_label\',\'business_batch_per_business_admission_cap\'].forEach(function(name){root.querySelectorAll(\'[name="\'+name+\'"]\').forEach(function(field){field.addEventListener("change",updatePreviewStale);field.addEventListener("input",updatePreviewStale);});});';
			echo '[\'email_subject\',\'message_template\'].forEach(function(name){root.querySelectorAll(\'[name="\'+name+\'"]\').forEach(function(field){field.addEventListener("change",updatePreviewStale);field.addEventListener("input",updatePreviewStale);});});';
			echo 'if(validitySelect){validitySelect.addEventListener("change",updatePreviewStale);}';
				echo 'updatePurpose();updateValidity();updateRecipientSource();updateBusinessBatchScope();updateBusinessOffer();updatePreviewStale();if(window.location.hash==="#vms-outreach-business-review-action"&&businessRoot){var focusBusinessReviewAction=function(){var reviewButton=businessRoot.querySelector("[data-vms-business-preview-button]");if(reviewButton&&!reviewButton.disabled){reviewButton.focus({preventScroll:true});reviewButton.scrollIntoView({block:"center",behavior:window.matchMedia("(prefers-reduced-motion: reduce)").matches?"auto":"smooth"});}};window.setTimeout(focusBusinessReviewAction,150);window.addEventListener("load",function(){window.setTimeout(focusBusinessReviewAction,50);},{once:true});}';
			echo '})();';
			echo '</script>';
		}

		echo '</form>';
		echo '</section>';

		if ($campaign_id > 0 && $show_guest_pass_fields && function_exists('vms_pass_outreach_render_recipients_panel')) {
			vms_pass_outreach_render_recipients_panel($form_payload);
		}

		echo '<details ' . vms_pass_outreach_collapsible_details_attrs(vms_pass_outreach_collapsible_context($campaign_id), 'outreach_campaigns', array(
			'classes' => array('vms-pass-card', 'vms-pass-card--collapsible'),
			'default_open' => $campaign_id <= 0 || $campaign_status_filter !== '' || $campaign_purpose_filter !== '',
			'anchor' => 'vms-outreach-campaigns',
		)) . '>';
		echo vms_pass_outreach_render_collapsible_summary(__('Outreach Campaigns', 'backstage-outreach'));
		echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" class="vms-pass-form vms-pass-campaign-filters">';
		echo '<input type="hidden" name="page" value="' . esc_attr($page_slug) . '">';
		if (function_exists('vms_pass_claims_menu_slug') && $page_slug === vms_pass_claims_menu_slug()) {
			echo '<input type="hidden" name="tab" value="' . esc_attr(vms_pass_outreach_tab_slug()) . '">';
		}
		echo '<div class="vms-pass-grid">';
		echo '<label>' . $render_label(__('Purpose', 'backstage-outreach')) . '<select name="campaign_purpose">';
		echo '<option value="">' . esc_html__('All purposes', 'backstage-outreach') . '</option>';
		foreach ($campaign_catalog as $purpose_key => $purpose_definition) {
			$purpose_label = sanitize_text_field((string) ($purpose_definition['label'] ?? $purpose_key));
			echo '<option value="' . esc_attr($purpose_key) . '"' . selected($campaign_purpose_filter, $purpose_key, false) . '>' . esc_html($purpose_label) . '</option>';
		}
		echo '</select></label>';
		echo '<label>' . $render_label(__('Status', 'backstage-outreach')) . '<select name="campaign_status">';
		echo '<option value="">' . esc_html__('All statuses', 'backstage-outreach') . '</option>';
		foreach (vms_pass_outreach_status_labels() as $status_key => $status_label) {
			echo '<option value="' . esc_attr($status_key) . '"' . selected($campaign_status_filter, $status_key, false) . '>' . esc_html((string) $status_label) . '</option>';
		}
		echo '</select></label>';
		echo '</div>';
		echo '<p class="vms-pass-actions"><button type="submit" class="button">' . esc_html__('Apply Filters', 'backstage-outreach') . '</button> <a class="button" href="' . esc_url(vms_pass_outreach_admin_page_url()) . '">' . esc_html__('Reset', 'backstage-outreach') . '</a></p>';
		echo '</form>';
		echo '<div class="vms-pass-table-scroll vms-pass-table-scroll--campaigns">';
		echo '<table class="widefat striped vms-pass-data-table vms-pass-campaign-table">';
		echo '<thead><tr><th>' . esc_html__('Campaign', 'backstage-outreach') . '</th><th class="vms-pass-table-cell--center">' . esc_html__('Status', 'backstage-outreach') . '</th><th class="vms-pass-table-cell--center">' . esc_html__('Results', 'backstage-outreach') . '</th><th class="vms-pass-table-cell--center">' . esc_html__('Actions', 'backstage-outreach') . '</th></tr></thead><tbody>';
		if (empty($campaigns)) {
			$empty_message = ($campaign_status_filter !== '' || $campaign_purpose_filter !== '')
				? __('No outreach campaigns matched this view.', 'backstage-outreach')
				: __('No outreach campaigns yet.', 'backstage-outreach');
			echo '<tr><td colspan="4">' . esc_html($empty_message) . '</td></tr>';
		} else {
			foreach ($campaigns as $row) {
				$row_id = absint($row['id'] ?? 0);
				$campaign_summary = vms_pass_outreach_campaign_summary($row);
				$display_status = function_exists('vms_pass_outreach_campaign_display_status')
					? vms_pass_outreach_campaign_display_status($row, $campaign_summary)
					: array(
						'key' => sanitize_key((string) ($row['status'] ?? 'draft')),
						'label' => (string) (vms_pass_outreach_status_labels()[(string) ($row['status'] ?? 'draft')] ?? ($row['status'] ?? 'draft')),
						'variant' => sanitize_key((string) ($row['status'] ?? 'draft')),
					);
					$stored_status_label = (string) (vms_pass_outreach_status_labels()[(string) ($row['status'] ?? 'draft')] ?? ($row['status'] ?? 'draft'));
					if ($stored_status_label !== (string) ($display_status['label'] ?? '')) {
						$display_status['tooltip'] = sprintf(__('Workflow status: %s', 'backstage-outreach'), $stored_status_label);
					}
					$display_status['popover_html'] = '<p class="vms-pass-floating-popover__eyebrow">' . esc_html__('Status', 'backstage-outreach') . '</p><p class="vms-pass-floating-popover__counts"><strong>' . esc_html((string) ($display_status['label'] ?? __('Status', 'backstage-outreach'))) . '</strong></p><p class="vms-pass-floating-popover__next-step">' . esc_html(sprintf(__('Workflow status: %s', 'backstage-outreach'), $stored_status_label)) . '</p>';
					$link_bits = array();
					if (!empty($row['batch_name'])) {
						$link_bits[] = sprintf(__('Batch: %s', 'backstage-outreach'), (string) $row['batch_name']);
					}
				if (!empty($row['source_name'])) {
					$link_bits[] = sprintf(__('Tracking Source: %s', 'backstage-outreach'), (string) $row['source_name']);
				}
					$campaign_meta_bits = array_filter(array(
						!empty($link_bits) ? implode(' · ', $link_bits) : __('Unlinked', 'backstage-outreach'),
						vms_pass_outreach_scope_summary($row),
						(string) (vms_pass_outreach_eligibility_labels()[(string) ($row['eligibility_mode'] ?? '')] ?? (string) ($row['eligibility_mode'] ?? '')),
					));
					$edit_url = vms_pass_outreach_admin_page_url(array('campaign_id' => $row_id));
					$is_business_campaign = function_exists('backstage_outreach_is_reusable_business_campaign') && backstage_outreach_is_reusable_business_campaign($row);
					$contact_dashboard = $is_business_campaign && function_exists('backstage_outreach_business_contact_dashboard') ? backstage_outreach_business_contact_dashboard($row_id) : array();
					$contact_summary = (array) ($contact_dashboard['summary'] ?? array());
					$business_results_anchor = absint($contact_summary['total_businesses'] ?? 0) > 0 ? '#backstage-outreach-business-contacts' : '#backstage-outreach-partners';
					$results_url = $edit_url . ($is_business_campaign ? $business_results_anchor : '#vms-outreach-delivery-status');
					$delivery_line = $is_business_campaign
						? sprintf(
							__('%1$d businesses · %2$d email handed off · %3$d manual contacts', 'backstage-outreach'),
							absint($contact_summary['total_businesses'] ?? 0),
							absint($contact_summary['email_handed_off'] ?? 0),
							absint($contact_summary['manual_contacts'] ?? 0)
						)
						: (function_exists('vms_pass_outreach_campaign_counts_line')
							? vms_pass_outreach_campaign_counts_line($campaign_summary)
							: sprintf(__('Recipients %d', 'backstage-outreach'), absint($campaign_summary['total_recipients'] ?? 0)));
					$results_line = $is_business_campaign
						? sprintf(
							__('%1$d no contact · %2$d follow-ups needed', 'backstage-outreach'),
							absint($contact_summary['no_contact'] ?? 0),
							absint($contact_summary['follow_up_needed'] ?? 0)
						)
						: (function_exists('vms_pass_outreach_campaign_results_line')
							? vms_pass_outreach_campaign_results_line($row, $campaign_summary, array('include_total_admissions' => false))
							: sprintf(
								__('Claimed %1$d · Checked in %2$d', 'backstage-outreach'),
								absint($campaign_summary['claimed_recipients'] ?? 0),
								absint($campaign_summary['admissions_checked_in'] ?? 0)
							));
					$next_step = $is_business_campaign ? __('Open the business contact dashboard to log outreach, review history, and share personalized links.', 'backstage-outreach') : $campaign_next_action_message($campaign_summary);
					$results_popover_html = '<p class="vms-pass-floating-popover__eyebrow">' . esc_html__('Delivery & Results', 'backstage-outreach') . '</p><p class="vms-pass-floating-popover__counts"><strong>' . esc_html($delivery_line) . '</strong></p><p class="vms-pass-floating-popover__counts"><strong>' . esc_html($results_line) . '</strong></p><p class="vms-pass-floating-popover__next-step">' . esc_html($next_step) . '</p><div class="vms-pass-floating-popover__actions"><a class="button button-small" href="' . esc_url($results_url) . '">' . esc_html__('Open Delivery & Results', 'backstage-outreach') . '</a></div>';
					$passes_url = !empty($row['related_batch_id']) && function_exists('vms_pass_claims_admin_page_url')
						? vms_pass_claims_admin_page_url(array('tab' => 'passes', 'batch_id' => (int) $row['related_batch_id']))
					: '';
				$action_menu_id = 'vms-pass-campaign-actions-' . $row_id;

					echo '<tr>';
					echo '<td><strong>' . esc_html((string) ($row['campaign_name'] ?? '')) . '</strong><div class="description">#' . esc_html((string) $row_id) . ' | ' . wp_kses_post($render_purpose_pill((string) ($row['campaign_purpose'] ?? vms_outreach_default_campaign_purpose()))) . '</div><div class="description">' . esc_html(implode(' | ', $campaign_meta_bits)) . '</div></td>';
					echo '<td class="vms-pass-table-cell--center">' . $render_status_pill($display_status) . '</td>';
					echo '<td class="vms-pass-campaign-results">' . $render_quick_popover_toggle(
						__('View Results', 'backstage-outreach'),
						'button button-small vms-pass-results-trigger has-detail',
						$results_popover_html,
						sprintf(__('Show results for %s', 'backstage-outreach'), (string) ($row['campaign_name'] ?? __('this campaign', 'backstage-outreach'))),
						'vms-pass-help--results'
					) . '</td>';
					echo '<td class="vms-pass-campaign-actions"><a class="button button-small button-primary" href="' . esc_url($edit_url) . '">' . esc_html__('Manage Campaign', 'backstage-outreach') . '</a>';
				if ($passes_url !== '') {
					echo ' <div class="vms-pass-row-actions"><button type="button" class="button button-small vms-pass-row-actions__trigger" data-vms-action-menu-trigger="' . esc_attr($action_menu_id) . '" aria-haspopup="true" aria-expanded="false">' . esc_html__('Actions', 'backstage-outreach') . '</button><div id="' . esc_attr($action_menu_id) . '" class="vms-pass-row-actions__template" hidden><div class="vms-pass-row-actions__menu"><a class="button button-small" href="' . esc_url($passes_url) . '">' . esc_html__('View Linked Passes', 'backstage-outreach') . '</a></div></div></div>';
				}
				echo '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '</div>';
		echo '</details>';
		if ($campaign_id > 0 && is_array($campaign) && function_exists('backstage_outreach_render_business_distribution_panel')) {
			backstage_outreach_render_business_distribution_panel($campaign);
		}
		echo '<script>(function(){if(window.vmsPassQuickPopoverInit){window.vmsPassQuickPopoverInit(document);return;}var hoverDelay=120;var hideDelay=90;var openState={wrapper:null,toggle:null,pinned:false,hoverTimer:0,hideTimer:0,popover:null};function ensureQuickPopover(){if(openState.popover&&document.body.contains(openState.popover)){return openState.popover;}var existing=document.getElementById("vms-pass-floating-popover");if(existing){openState.popover=existing;return existing;}var popover=document.createElement("div");popover.id="vms-pass-floating-popover";popover.className="vms-pass-floating-popover";popover.setAttribute("role","tooltip");popover.setAttribute("aria-hidden","true");document.body.appendChild(popover);popover.addEventListener("mouseenter",function(){if(openState.hideTimer){window.clearTimeout(openState.hideTimer);openState.hideTimer=0;}});popover.addEventListener("mouseleave",function(){if(!openState.pinned){scheduleQuickPopoverClose();}});openState.popover=popover;return popover;}function clearQuickPopoverTimers(){if(openState.hoverTimer){window.clearTimeout(openState.hoverTimer);openState.hoverTimer=0;}if(openState.hideTimer){window.clearTimeout(openState.hideTimer);openState.hideTimer=0;}}function positionQuickPopover(toggle,popover){if(!toggle||!popover||!document.body.contains(toggle)){return;}popover.style.top="0px";popover.style.left="0px";popover.classList.add("is-visible");var rect=toggle.getBoundingClientRect();var popoverRect=popover.getBoundingClientRect();var viewportWidth=window.innerWidth||document.documentElement.clientWidth||0;var viewportHeight=window.innerHeight||document.documentElement.clientHeight||0;var horizontalPadding=16;var verticalGap=10;var left=rect.left+(rect.width-popoverRect.width)/2;if(left<horizontalPadding){left=horizontalPadding;}if(left+popoverRect.width>viewportWidth-horizontalPadding){left=Math.max(horizontalPadding,viewportWidth-popoverRect.width-horizontalPadding);}var showAbove=rect.bottom+verticalGap+popoverRect.height>viewportHeight-horizontalPadding&&rect.top-popoverRect.height-verticalGap>=horizontalPadding;var top=showAbove?rect.top-popoverRect.height-verticalGap:rect.bottom+verticalGap;if(top<horizontalPadding){top=horizontalPadding;}if(top+popoverRect.height>viewportHeight-horizontalPadding){top=Math.max(horizontalPadding,viewportHeight-popoverRect.height-horizontalPadding);}popover.classList.toggle("is-above",showAbove);popover.style.left=Math.round(left)+"px";popover.style.top=Math.round(top)+"px";}function openQuickPopover(wrapper,options){if(!wrapper){return;}var toggle=wrapper.querySelector("[data-vms-quick-popover-toggle]");var source=wrapper.querySelector("[data-vms-quick-popover-content]");if(!toggle||!source){return;}clearQuickPopoverTimers();var popover=ensureQuickPopover();var pinned=!!(options&&options.pinned);if(openState.wrapper&&openState.wrapper!==wrapper){closeQuickPopover();}popover.innerHTML=source.innerHTML;popover.setAttribute("aria-hidden","false");wrapper.classList.add("is-open");toggle.setAttribute("aria-expanded","true");openState.wrapper=wrapper;openState.toggle=toggle;openState.pinned=pinned;positionQuickPopover(toggle,popover);}function closeQuickPopover(){clearQuickPopoverTimers();var popover=ensureQuickPopover();if(openState.wrapper){openState.wrapper.classList.remove("is-open");}if(openState.toggle){openState.toggle.setAttribute("aria-expanded","false");}popover.classList.remove("is-visible","is-above");popover.setAttribute("aria-hidden","true");popover.innerHTML="";openState.wrapper=null;openState.toggle=null;openState.pinned=false;}function scheduleQuickPopoverOpen(wrapper,pinned){clearQuickPopoverTimers();openState.hoverTimer=window.setTimeout(function(){openQuickPopover(wrapper,{pinned:!!pinned});},hoverDelay);}function scheduleQuickPopoverClose(){clearQuickPopoverTimers();openState.hideTimer=window.setTimeout(function(){closeQuickPopover();},hideDelay);}function bindQuickPopover(wrapper){if(!wrapper||wrapper.getAttribute("data-vms-quick-popover-bound")==="1"){return;}var toggle=wrapper.querySelector("[data-vms-quick-popover-toggle]");if(!toggle){return;}wrapper.setAttribute("data-vms-quick-popover-bound","1");wrapper.addEventListener("mouseenter",function(){if(openState.wrapper===wrapper&&openState.pinned){return;}scheduleQuickPopoverOpen(wrapper,false);});wrapper.addEventListener("mouseleave",function(){if(openState.wrapper===wrapper&&!openState.pinned){scheduleQuickPopoverClose();}});toggle.addEventListener("focus",function(){openQuickPopover(wrapper,{pinned:false});});toggle.addEventListener("blur",function(event){if(wrapper.contains(event.relatedTarget)){return;}if(!openState.pinned){scheduleQuickPopoverClose();}});toggle.addEventListener("click",function(event){var isSame=openState.wrapper===wrapper;var willOpen=!isSame||!openState.pinned;if(willOpen){openQuickPopover(wrapper,{pinned:true});}else{closeQuickPopover();}event.preventDefault();event.stopPropagation();});}window.vmsPassQuickPopoverInit=function(root){var scope=root&&root.querySelectorAll?root:document;scope.querySelectorAll("[data-vms-quick-popover]").forEach(bindQuickPopover);};document.addEventListener("click",function(event){if(event.target.closest("[data-vms-quick-popover-toggle]")){return;}if(openState.wrapper&&!event.target.closest("#vms-pass-floating-popover")){closeQuickPopover();}});document.addEventListener("keydown",function(event){if(event.key==="Escape"){closeQuickPopover();}});document.addEventListener("scroll",function(){if(!openState.toggle){return;}if(!document.body.contains(openState.toggle)){closeQuickPopover();return;}positionQuickPopover(openState.toggle,ensureQuickPopover());},true);window.addEventListener("resize",function(){if(openState.toggle){positionQuickPopover(openState.toggle,ensureQuickPopover());}});window.vmsPassQuickPopoverInit(document);})();</script>';
	}
}

if (!function_exists('vms_pass_outreach_campaign_sort_priority')) {
	function vms_pass_outreach_campaign_sort_priority(array $campaign, int $batch_id): int
	{
		return absint($campaign['related_batch_id'] ?? 0) === $batch_id ? 0 : 1;
	}
}

if (!function_exists('vms_pass_outreach_resolve_campaign_for_batch')) {
	function vms_pass_outreach_resolve_campaign_for_batch(array $batch): ?array
	{
		$batch_id = absint($batch['id'] ?? 0);
		$source_id = absint($batch['source_id'] ?? 0);
		if ($batch_id <= 0 && $source_id <= 0) {
			return null;
		}

		global $wpdb;
		$table = vms_admission_table_pass_outreach_campaigns();
		$where = array();
		$params = array();
		$runtime_statuses = array_values(array_filter(array_map('sanitize_key', vms_pass_outreach_runtime_statuses())));
		if (empty($runtime_statuses)) {
			return null;
		}
		$status_placeholders = implode(', ', array_fill(0, count($runtime_statuses), '%s'));
		if ($batch_id > 0) {
			$where[] = "(related_batch_id = %d AND status IN ({$status_placeholders}))";
			$params[] = $batch_id;
			$params = array_merge($params, $runtime_statuses);
		}
		if ($source_id > 0) {
			$where[] = "((related_batch_id IS NULL OR related_batch_id = 0) AND related_source_id = %d AND status IN ({$status_placeholders}))";
			$params[] = $source_id;
			$params = array_merge($params, $runtime_statuses);
		}
		if (empty($where)) {
			return null;
		}

		$sql = "SELECT * FROM {$table}
			WHERE " . implode(' OR ', $where) . "
			ORDER BY COALESCE(updated_at, created_at) DESC, id DESC
			LIMIT 20";
		$rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
		if (!is_array($rows) || empty($rows)) {
			return null;
		}

		$rows = array_map('vms_pass_outreach_normalize_campaign_row', $rows);
		usort($rows, static function (array $left, array $right) use ($batch_id): int {
			$left_priority = vms_pass_outreach_campaign_sort_priority($left, $batch_id);
			$right_priority = vms_pass_outreach_campaign_sort_priority($right, $batch_id);
			if ($left_priority !== $right_priority) {
				return $left_priority <=> $right_priority;
			}
			$left_stamp = strtotime((string) ($left['updated_at'] ?? $left['created_at'] ?? '')) ?: 0;
			$right_stamp = strtotime((string) ($right['updated_at'] ?? $right['created_at'] ?? '')) ?: 0;
			if ($left_stamp !== $right_stamp) {
				return $right_stamp <=> $left_stamp;
			}
			return absint($right['id'] ?? 0) <=> absint($left['id'] ?? 0);
		});

		return $rows[0] ?? null;
	}
}

if (!function_exists('vms_pass_outreach_is_campaign_expired')) {
	function vms_pass_outreach_is_campaign_expired(array $campaign): bool
	{
		$expires_at = trim((string) ($campaign['expires_at'] ?? ''));
		if ($expires_at === '') {
			return false;
		}
		try {
			$deadline = new DateTimeImmutable($expires_at, wp_timezone());
		} catch (Exception $e) {
			return false;
		}
		return $deadline->getTimestamp() < time();
	}
}

if (!function_exists('vms_pass_outreach_campaign_preflight')) {
	function vms_pass_outreach_campaign_preflight(array $campaign): array
	{
		$status = sanitize_key((string) ($campaign['status'] ?? 'draft'));
		if ($status !== 'active') {
			return array(
				'ok' => false,
				'reason_code' => 'campaign_not_active',
				'campaign_status' => $status,
				'admin_reasons' => array(
					$status === 'closed' ? 'Campaign status closed' : 'Campaign status draft',
				),
			);
		}

		if (vms_pass_outreach_is_campaign_expired($campaign)) {
			return array(
				'ok' => false,
				'reason_code' => 'campaign_expired',
				'admin_reasons' => array('Invite expired'),
			);
		}

		return array(
			'ok' => true,
			'reason_code' => '',
			'admin_reasons' => array(),
		);
	}
}

if (!function_exists('vms_pass_outreach_claim_guardrail_message')) {
	function vms_pass_outreach_claim_guardrail_message(array $guardrail, string $fallback = ''): string
	{
		$reason_code = sanitize_key((string) ($guardrail['reason_code'] ?? ''));
		$reasons = array_values(array_filter(array_map('sanitize_text_field', (array) ($guardrail['admin_reasons'] ?? array()))));
		if ($reason_code === 'campaign_not_active') {
			$campaign_status = sanitize_key((string) ($guardrail['campaign_status'] ?? ''));
			if ($campaign_status === 'draft' || in_array('Campaign status draft', $reasons, true)) {
				return vms_pass_outreach_draft_send_warning_message();
			}
			if ($campaign_status === 'closed' || in_array('Campaign status closed', $reasons, true)) {
				return __('This campaign is closed. Reopen or activate the campaign before sending invites.', 'backstage-outreach');
			}
		}
		if (!empty($reasons[0])) {
			return (string) $reasons[0];
		}

		if ($fallback !== '') {
			return $fallback;
		}

		return __('This outreach campaign is not ready to send claimable invite links.', 'backstage-outreach');
	}
}

if (!function_exists('vms_pass_outreach_campaign_claim_guardrail')) {
	function vms_pass_outreach_campaign_claim_guardrail(array $campaign): array
	{
		$batch = vms_pass_outreach_campaign_batch($campaign);
		if (!is_array($batch)) {
			return array(
				'ok' => false,
				'reason_code' => 'campaign_missing_batch',
				'admin_reasons' => array('Linked Guest Pass batch not found'),
				'batch' => null,
				'eligible_events' => array(),
			);
		}

		$batch_status = sanitize_key((string) ($batch['status'] ?? 'draft'));
		if ($batch_status !== 'active') {
			return array(
				'ok' => false,
				'reason_code' => 'invite_not_active',
				'admin_reasons' => array(
					sprintf('Linked Guest Pass batch status %s', $batch_status !== '' ? $batch_status : 'inactive'),
				),
				'batch' => $batch,
				'eligible_events' => array(),
			);
		}

		$expires_at = trim((string) ($batch['expires_at'] ?? ''));
		if ($expires_at !== '' && strpos($expires_at, '0000-00-00') !== 0) {
			try {
				$expires_dt = new DateTimeImmutable($expires_at, wp_timezone());
				if (time() >= $expires_dt->getTimestamp()) {
					return array(
						'ok' => false,
						'reason_code' => 'campaign_expired',
						'admin_reasons' => array('Invite expired'),
						'batch' => $batch,
						'eligible_events' => array(),
					);
				}
			} catch (Exception $e) {
				// Ignore malformed expiration values so existing admin cleanup can correct them.
			}
		}

		$preflight = vms_pass_outreach_campaign_preflight($campaign);
		if (empty($preflight['ok'])) {
			$preflight['batch'] = $batch;
			$preflight['eligible_events'] = array();
			return $preflight;
		}

		$eligible_events = function_exists('vms_pass_claims_eligible_events_for_batch')
			? vms_pass_claims_eligible_events_for_batch($batch)
			: array();
		$eligible_events = function_exists('vms_pass_outreach_filter_events_for_campaign')
			? vms_pass_outreach_filter_events_for_campaign($campaign, $eligible_events)
			: $eligible_events;

		if (empty($eligible_events)) {
			return array(
				'ok' => false,
				'reason_code' => 'no_eligible_events',
				'admin_reasons' => array('No eligible published future events are available for this campaign'),
				'batch' => $batch,
				'eligible_events' => array(),
			);
		}

		return array(
			'ok' => true,
			'reason_code' => '',
			'admin_reasons' => array(),
			'batch' => $batch,
			'eligible_events' => $eligible_events,
		);
	}
}

if (!function_exists('vms_pass_outreach_campaign_scope_as_batch')) {
	function vms_pass_outreach_campaign_scope_as_batch(array $campaign): array
	{
		return array(
			'validity_type' => sanitize_key((string) ($campaign['validity_type'] ?? 'batch_default')),
			'single_event_plan_id' => absint($campaign['single_event_plan_id'] ?? 0),
			'start_date' => sanitize_text_field((string) ($campaign['start_date'] ?? '')),
			'end_date' => sanitize_text_field((string) ($campaign['end_date'] ?? '')),
			'season_label' => sanitize_text_field((string) ($campaign['season_label'] ?? '')),
			'venue_ids_json' => '',
		);
	}
}

if (!function_exists('vms_pass_outreach_filter_events_for_campaign')) {
	function vms_pass_outreach_filter_events_for_campaign(array $campaign, array $events): array
	{
		$validity_type = sanitize_key((string) ($campaign['validity_type'] ?? 'batch_default'));
		if ($validity_type === '' || $validity_type === 'batch_default' || empty($events) || !function_exists('vms_pass_claims_eligible_events_for_batch')) {
			return $events;
		}

		$campaign_events = vms_pass_claims_eligible_events_for_batch(vms_pass_outreach_campaign_scope_as_batch($campaign));
		$allowed_lookup = array();
		foreach ((array) $campaign_events as $campaign_event) {
			$allowed_event_id = absint($campaign_event['id'] ?? 0);
			if ($allowed_event_id > 0) {
				$allowed_lookup[$allowed_event_id] = true;
			}
		}

		if (empty($allowed_lookup)) {
			return array();
		}

		$filtered = array();
		foreach ($events as $event) {
			$event_id = absint($event['id'] ?? 0);
			if ($event_id > 0 && isset($allowed_lookup[$event_id])) {
				$filtered[] = $event;
			}
		}

		return $filtered;
	}
}

if (!function_exists('vms_pass_outreach_effective_recipient_cap')) {
	function vms_pass_outreach_effective_recipient_cap(array $batch, ?array $campaign = null): int
	{
		$batch_cap = max(1, absint($batch['admissions_per_link'] ?? 1));
		if (!is_array($campaign)) {
			return $batch_cap;
		}
		$campaign_cap = max(1, absint($campaign['admissions_per_recipient'] ?? 1));
		return max(1, min($batch_cap, $campaign_cap));
	}
}

if (!function_exists('vms_pass_outreach_campaign_claimed_headcount')) {
	function vms_pass_outreach_campaign_claimed_headcount(array $campaign): int
	{
		global $wpdb;

		$entries_table = vms_admission_table_entries();
		$related_batch_id = absint($campaign['related_batch_id'] ?? 0);
		$related_source_id = absint($campaign['related_source_id'] ?? 0);
		if ($related_batch_id <= 0 && $related_source_id <= 0) {
			return 0;
		}

		if ($related_batch_id > 0) {
			$sql = "SELECT COALESCE(SUM(party_size), 0)
				FROM {$entries_table}
				WHERE pass_batch_id = %d
					AND status <> 'canceled'";
			$value = $wpdb->get_var($wpdb->prepare($sql, $related_batch_id));
		} else {
			$sql = "SELECT COALESCE(SUM(party_size), 0)
				FROM {$entries_table}
				WHERE pass_source_id = %d
					AND status <> 'canceled'";
			$value = $wpdb->get_var($wpdb->prepare($sql, $related_source_id));
		}

		return max(0, absint($value));
	}
}

if (!function_exists('vms_pass_outreach_collect_match_fields')) {
	function vms_pass_outreach_collect_match_fields(array $row, array $identity, array $columns): array
	{
		$matched = array();
		foreach ($columns as $field => $column) {
			$expected = trim((string) ($identity[$field] ?? ''));
			$actual = trim((string) ($row[$column] ?? ''));
			if ($expected !== '' && $actual !== '' && $expected === $actual) {
				$matched[$field] = true;
			}
		}
		return $matched;
	}
}

if (!function_exists('vms_pass_outreach_match_reason_strings')) {
	function vms_pass_outreach_match_reason_strings(string $base, array $matched_by): array
	{
		$reasons = array();
		if (!empty($matched_by['phone'])) {
			$reasons[] = $base . ' by phone';
		}
		if (!empty($matched_by['email'])) {
			$reasons[] = $base . ' by email';
		}
		if (!empty($matched_by['name']) && empty($matched_by['phone']) && empty($matched_by['email'])) {
			$reasons[] = $base . ' by name';
		}
		if (empty($reasons)) {
			$reasons[] = $base;
		}
		return $reasons;
	}
}

if (!function_exists('vms_pass_outreach_prior_checkedin_history')) {
	function vms_pass_outreach_prior_checkedin_history(string $guest_name_norm, string $guest_email_norm, string $phone_norm): array
	{
		global $wpdb;

		$entries_table = vms_admission_table_entries();
		$where = array("status <> 'canceled'", 'checked_in_qty > 0');
		$params = array();
		$identity = array();
		if ($guest_email_norm !== '') {
			$identity[] = 'guest_email_norm = %s';
			$params[] = $guest_email_norm;
		}
		if ($phone_norm !== '') {
			$identity[] = 'phone_norm = %s';
			$params[] = $phone_norm;
		}
		if ($guest_name_norm !== '' && $guest_email_norm === '' && $phone_norm === '') {
			$identity[] = 'guest_name_norm = %s';
			$params[] = $guest_name_norm;
		}
		if (empty($identity)) {
			return array('count' => 0, 'event_ids' => array(), 'matched_by' => array());
		}
		$where[] = '(' . implode(' OR ', $identity) . ')';

		$sql = "SELECT event_plan_id, guest_name_norm, guest_email_norm, phone_norm
			FROM {$entries_table}
			WHERE " . implode(' AND ', $where) . '
			LIMIT 50';
		$rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
		if (!is_array($rows)) {
			return array('count' => 0, 'event_ids' => array(), 'matched_by' => array());
		}

		$event_ids = array();
		$matched_by = array();
		$identity_values = array(
			'name' => $guest_name_norm,
			'email' => $guest_email_norm,
			'phone' => $phone_norm,
		);
		foreach ($rows as $row) {
			$event_id = absint($row['event_plan_id'] ?? 0);
			if ($event_id > 0) {
				$event_ids[] = $event_id;
			}
			foreach (vms_pass_outreach_collect_match_fields($row, $identity_values, array(
				'name' => 'guest_name_norm',
				'email' => 'guest_email_norm',
				'phone' => 'phone_norm',
			)) as $field => $truthy) {
				$matched_by[$field] = true;
			}
		}

		$event_ids = array_values(array_unique(array_filter(array_map('absint', $event_ids))));
		return array(
			'count' => count($rows),
			'event_ids' => $event_ids,
			'matched_by' => $matched_by,
		);
	}
}

if (!function_exists('vms_pass_outreach_prior_guest_pass_claim_history')) {
	function vms_pass_outreach_prior_guest_pass_claim_history(string $guest_email_norm, string $phone_norm): array
	{
		global $wpdb;

		if ($guest_email_norm === '' && $phone_norm === '') {
			return array('count' => 0, 'event_ids' => array(), 'matched_by' => array());
		}

		$claims_table = vms_admission_table_pass_claims();
		$where = array();
		$params = array();
		if ($guest_email_norm !== '') {
			$where[] = 'LOWER(email) = %s';
			$params[] = $guest_email_norm;
		}
		if ($phone_norm !== '') {
			$where[] = 'phone_norm = %s';
			$params[] = $phone_norm;
		}

		$sql = "SELECT event_plan_id, LOWER(email) AS email_norm, phone_norm
			FROM {$claims_table}
			WHERE (" . implode(' OR ', $where) . ')
			LIMIT 50';
		$rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
		if (!is_array($rows)) {
			return array('count' => 0, 'event_ids' => array(), 'matched_by' => array());
		}

		$event_ids = array();
		$matched_by = array();
		$identity_values = array(
			'email' => $guest_email_norm,
			'phone' => $phone_norm,
		);
		foreach ($rows as $row) {
			$event_id = absint($row['event_plan_id'] ?? 0);
			if ($event_id > 0) {
				$event_ids[] = $event_id;
			}
			foreach (vms_pass_outreach_collect_match_fields($row, $identity_values, array(
				'email' => 'email_norm',
				'phone' => 'phone_norm',
			)) as $field => $truthy) {
				$matched_by[$field] = true;
			}
		}

		$event_ids = array_values(array_unique(array_filter(array_map('absint', $event_ids))));
		return array(
			'count' => count($rows),
			'event_ids' => $event_ids,
			'matched_by' => $matched_by,
		);
	}
}

if (!function_exists('vms_pass_outreach_prior_comp_admission_history')) {
	function vms_pass_outreach_prior_comp_admission_history(string $guest_name_norm, string $guest_email_norm, string $phone_norm): array
	{
		global $wpdb;

		$entries_table = vms_admission_table_entries();
		$where = array(
			"status <> 'canceled'",
			"(admission_kind IN ('comp', 'pass') OR source IN ('operator', 'vendor_portal', 'vendor_guest', 'pass_claim'))",
		);
		$params = array();
		$identity = array();
		if ($guest_email_norm !== '') {
			$identity[] = 'guest_email_norm = %s';
			$params[] = $guest_email_norm;
		}
		if ($phone_norm !== '') {
			$identity[] = 'phone_norm = %s';
			$params[] = $phone_norm;
		}
		if ($guest_name_norm !== '' && $guest_email_norm === '' && $phone_norm === '') {
			$identity[] = 'guest_name_norm = %s';
			$params[] = $guest_name_norm;
		}
		if (empty($identity)) {
			return array('count' => 0, 'event_ids' => array(), 'matched_by' => array());
		}
		$where[] = '(' . implode(' OR ', $identity) . ')';

		$sql = "SELECT event_plan_id, guest_name_norm, guest_email_norm, phone_norm
			FROM {$entries_table}
			WHERE " . implode(' AND ', $where) . '
			LIMIT 50';
		$rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
		if (!is_array($rows)) {
			return array('count' => 0, 'event_ids' => array(), 'matched_by' => array());
		}

		$event_ids = array();
		$matched_by = array();
		$identity_values = array(
			'name' => $guest_name_norm,
			'email' => $guest_email_norm,
			'phone' => $phone_norm,
		);
		foreach ($rows as $row) {
			$event_id = absint($row['event_plan_id'] ?? 0);
			if ($event_id > 0) {
				$event_ids[] = $event_id;
			}
			foreach (vms_pass_outreach_collect_match_fields($row, $identity_values, array(
				'name' => 'guest_name_norm',
				'email' => 'guest_email_norm',
				'phone' => 'phone_norm',
			)) as $field => $truthy) {
				$matched_by[$field] = true;
			}
		}

		$event_ids = array_values(array_unique(array_filter(array_map('absint', $event_ids))));
		return array(
			'count' => count($rows),
			'event_ids' => $event_ids,
			'matched_by' => $matched_by,
		);
	}
}

if (!function_exists('vms_pass_outreach_prior_paid_order_history')) {
	function vms_pass_outreach_prior_paid_order_history(string $guest_email_norm, string $phone_norm): array
	{
		$result = array(
			'count' => 0,
			'event_ids' => array(),
			'order_ids' => array(),
			'matched_by' => array(),
		);
		if (!function_exists('wc_get_orders')) {
			return $result;
		}

		$query_sets = array();
		if ($guest_email_norm !== '') {
			$query_sets['email'] = array(
				'limit' => 100,
				'return' => 'objects',
				'status' => array('wc-pending', 'wc-processing', 'wc-completed', 'wc-on-hold'),
				'billing_email' => $guest_email_norm,
			);
		}
		if ($phone_norm !== '') {
			$query_sets['phone'] = array(
				'limit' => 100,
				'return' => 'objects',
				'status' => array('wc-pending', 'wc-processing', 'wc-completed', 'wc-on-hold'),
				'billing_phone' => $phone_norm,
			);
		}
		if (empty($query_sets)) {
			return $result;
		}

		$paid_statuses = function_exists('vms_ticketing_v2_paid_order_statuses')
			? (array) vms_ticketing_v2_paid_order_statuses()
			: array('processing', 'completed', 'on-hold');
		if (function_exists('vms_ticketing_v2_paid_order_statuses_with_prefix')) {
			$paid_statuses = (array) vms_ticketing_v2_paid_order_statuses_with_prefix($paid_statuses);
		} else {
			$paid_statuses = array_values(array_unique(array_filter(array_map(static function ($status): string {
				$status = sanitize_key((string) $status);
				if ($status === '') {
					return '';
				}
				return strpos($status, 'wc-') === 0 ? $status : 'wc-' . $status;
			}, $paid_statuses))));
		}

		foreach (array_keys($query_sets) as $field) {
			$query_sets[$field]['status'] = $paid_statuses;
		}

		$seen_orders = array();
		$seen_events = array();
		foreach ($query_sets as $field => $query) {
			$orders = wc_get_orders($query);
			foreach ((array) $orders as $order) {
				if (!is_object($order) || !method_exists($order, 'get_id')) {
					continue;
				}
				if (function_exists('vms_admission_vendor_guest_is_internal_comp_order') && vms_admission_vendor_guest_is_internal_comp_order($order)) {
					continue;
				}
				$order_id = absint($order->get_id());
				if ($order_id <= 0) {
					continue;
				}

				$matched_ticket_event = false;
				foreach ((array) $order->get_items() as $item) {
					if (!is_object($item) || !method_exists($item, 'get_product_id')) {
						continue;
					}
					$product_id = absint($item->get_product_id());
					$variation_id = method_exists($item, 'get_variation_id') ? absint($item->get_variation_id()) : 0;
					$event_id = function_exists('vms_admission_vendor_guest_product_event_id')
						? vms_admission_vendor_guest_product_event_id($variation_id > 0 ? $variation_id : $product_id)
						: 0;
					if ($event_id <= 0 && $variation_id > 0 && function_exists('vms_admission_vendor_guest_product_event_id')) {
						$event_id = vms_admission_vendor_guest_product_event_id($product_id);
					}
					if ($event_id > 0) {
						$matched_ticket_event = true;
						$seen_events[$event_id] = true;
					}
				}

				if ($matched_ticket_event) {
					$seen_orders[$order_id] = true;
					$result['matched_by'][$field] = true;
				}
			}
		}

		$result['order_ids'] = array_values(array_map('absint', array_keys($seen_orders)));
		$result['event_ids'] = array_values(array_map('absint', array_keys($seen_events)));
		$result['count'] = count($result['order_ids']);
		return $result;
	}
}

if (!function_exists('vms_pass_outreach_evaluate_first_time_eligibility')) {
	function vms_pass_outreach_evaluate_first_time_eligibility(array $claimant): array
	{
		$guest_name_norm = function_exists('vms_admission_normalize_name')
			? vms_admission_normalize_name((string) ($claimant['guest_name'] ?? ''))
			: sanitize_text_field((string) ($claimant['guest_name'] ?? ''));
		$guest_email_norm = function_exists('vms_admission_normalize_email')
			? vms_admission_normalize_email((string) ($claimant['email'] ?? ''))
			: sanitize_email((string) ($claimant['email'] ?? ''));
		$phone_norm = function_exists('vms_admission_normalize_phone')
			? vms_admission_normalize_phone((string) ($claimant['phone'] ?? ''))
			: preg_replace('/\D+/', '', (string) ($claimant['phone'] ?? ''));

		$checkedin = vms_pass_outreach_prior_checkedin_history($guest_name_norm, $guest_email_norm, $phone_norm);
		$paid = vms_pass_outreach_prior_paid_order_history($guest_email_norm, $phone_norm);
		$guest_pass_claims = vms_pass_outreach_prior_guest_pass_claim_history($guest_email_norm, $phone_norm);
		$comp_admissions = vms_pass_outreach_prior_comp_admission_history($guest_name_norm, $guest_email_norm, $phone_norm);

		$admin_reasons = array();
		if (!empty($checkedin['count'])) {
			$admin_reasons = array_merge($admin_reasons, vms_pass_outreach_match_reason_strings('Matched previous attendance', (array) ($checkedin['matched_by'] ?? array())));
		}
		if (!empty($paid['count'])) {
			$admin_reasons = array_merge($admin_reasons, vms_pass_outreach_match_reason_strings('Matched previous paid order', (array) ($paid['matched_by'] ?? array())));
		}
		if (!empty($guest_pass_claims['count'])) {
			$admin_reasons = array_merge($admin_reasons, vms_pass_outreach_match_reason_strings('Matched prior guest pass claim', (array) ($guest_pass_claims['matched_by'] ?? array())));
		}
		if (!empty($comp_admissions['count'])) {
			$admin_reasons = array_merge($admin_reasons, vms_pass_outreach_match_reason_strings('Matched prior comp/guest admission', (array) ($comp_admissions['matched_by'] ?? array())));
		}

		$admin_reasons = array_values(array_unique(array_filter(array_map('sanitize_text_field', $admin_reasons))));
		$eligible = empty($admin_reasons);
		if (!$eligible) {
			array_unshift($admin_reasons, 'Eligibility failed: first-time visitors only');
		}

		return array(
			'eligible' => $eligible,
			'admin_reasons' => $admin_reasons,
			'checkedin_event_ids' => array_values(array_map('absint', (array) ($checkedin['event_ids'] ?? array()))),
			'paid_event_ids' => array_values(array_map('absint', (array) ($paid['event_ids'] ?? array()))),
			'paid_order_ids' => array_values(array_map('absint', (array) ($paid['order_ids'] ?? array()))),
			'guest_pass_claim_event_ids' => array_values(array_map('absint', (array) ($guest_pass_claims['event_ids'] ?? array()))),
			'comp_event_ids' => array_values(array_map('absint', (array) ($comp_admissions['event_ids'] ?? array()))),
		);
	}
}

if (!function_exists('vms_pass_outreach_evaluate_claim')) {
	function vms_pass_outreach_evaluate_claim(array $campaign, array $batch, array $event_plan, array $claimant): array
	{
		$preflight = vms_pass_outreach_campaign_preflight($campaign);
		if (empty($preflight['ok'])) {
			return array(
				'ok' => false,
				'reason_code' => (string) ($preflight['reason_code'] ?? 'campaign_not_active'),
				'admin_reasons' => array_values(array_map('sanitize_text_field', (array) ($preflight['admin_reasons'] ?? array()))),
				'details' => array(),
			);
		}

		$party_size = max(1, absint($claimant['party_size'] ?? 1));
		$recipient_cap = vms_pass_outreach_effective_recipient_cap($batch, $campaign);
		if ($party_size > $recipient_cap) {
			return array(
				'ok' => false,
				'reason_code' => 'campaign_recipient_cap_reached',
				'admin_reasons' => array(
					sprintf('Admissions per recipient exceeded (%1$d requested, limit %2$d)', $party_size, $recipient_cap),
				),
				'details' => array(
					'requested_party_size' => $party_size,
					'recipient_cap' => $recipient_cap,
				),
			);
		}

		$filtered_events = vms_pass_outreach_filter_events_for_campaign($campaign, array($event_plan));
		if (empty($filtered_events)) {
			return array(
				'ok' => false,
				'reason_code' => 'campaign_event_not_allowed',
				'admin_reasons' => array('Campaign scope does not allow the selected event'),
				'details' => array(
					'event_plan_id' => absint($event_plan['id'] ?? 0),
				),
			);
		}

		$total_cap = max(0, absint($campaign['total_admission_cap'] ?? 0));
		if ($total_cap > 0) {
			$claimed_headcount = vms_pass_outreach_campaign_claimed_headcount($campaign);
			if (($claimed_headcount + $party_size) > $total_cap) {
				return array(
					'ok' => false,
					'reason_code' => 'campaign_cap_reached',
					'admin_reasons' => array('Campaign cap reached'),
					'details' => array(
						'claimed_headcount' => $claimed_headcount,
						'total_campaign_cap' => $total_cap,
						'requested_party_size' => $party_size,
					),
				);
			}
		}

		if (sanitize_key((string) ($campaign['eligibility_mode'] ?? '')) === 'first_time_visitors_only') {
			$eligibility = vms_pass_outreach_evaluate_first_time_eligibility(array(
				'guest_name' => (string) ($claimant['guest_name'] ?? ''),
				'email' => (string) ($claimant['email'] ?? ''),
				'phone' => (string) ($claimant['phone'] ?? ''),
			));
			if (empty($eligibility['eligible'])) {
				return array(
					'ok' => false,
					'reason_code' => 'campaign_eligibility_failed',
					'admin_reasons' => array_values(array_map('sanitize_text_field', (array) ($eligibility['admin_reasons'] ?? array()))),
					'details' => array(
						'checkedin_event_ids' => array_values(array_map('absint', (array) ($eligibility['checkedin_event_ids'] ?? array()))),
						'paid_event_ids' => array_values(array_map('absint', (array) ($eligibility['paid_event_ids'] ?? array()))),
						'paid_order_ids' => array_values(array_map('absint', (array) ($eligibility['paid_order_ids'] ?? array()))),
						'guest_pass_claim_event_ids' => array_values(array_map('absint', (array) ($eligibility['guest_pass_claim_event_ids'] ?? array()))),
						'comp_event_ids' => array_values(array_map('absint', (array) ($eligibility['comp_event_ids'] ?? array()))),
					),
				);
			}
		}

		return array(
			'ok' => true,
			'reason_code' => '',
			'admin_reasons' => array(),
			'details' => array(),
		);
	}
}

if (!function_exists('vms_pass_outreach_log_claim_denial')) {
	function vms_pass_outreach_log_claim_denial(array $context = array()): void
	{
		if (!function_exists('vms_admission_audit_log')) {
			return;
		}

		$campaign = is_array($context['campaign'] ?? null) ? (array) $context['campaign'] : array();
		$batch = is_array($context['batch'] ?? null) ? (array) $context['batch'] : array();
		$recipient = is_array($context['recipient'] ?? null) ? (array) $context['recipient'] : array();
		$details = array(
			'reason_code' => sanitize_key((string) ($context['reason_code'] ?? 'claim_unavailable')),
			'public_message' => vms_pass_outreach_public_failure_message(),
			'admin_reasons' => array_values(array_filter(array_map('sanitize_text_field', (array) ($context['admin_reasons'] ?? array())))),
			'campaign_id' => absint($campaign['id'] ?? 0),
			'campaign_name' => sanitize_text_field((string) ($campaign['campaign_name'] ?? '')),
			'campaign_status' => sanitize_key((string) ($campaign['status'] ?? '')),
			'campaign_eligibility_mode' => sanitize_key((string) ($campaign['eligibility_mode'] ?? '')),
			'recipient_id' => absint($recipient['id'] ?? 0),
			'recipient_status' => sanitize_key((string) ($recipient['status'] ?? '')),
			'recipient_email' => sanitize_email((string) ($recipient['email'] ?? '')),
			'recipient_phone' => sanitize_text_field((string) ($recipient['phone'] ?? '')),
			'batch_id' => absint($batch['id'] ?? 0),
			'source_id' => absint($batch['source_id'] ?? ($campaign['related_source_id'] ?? 0)),
			'token_id' => absint($context['token_id'] ?? 0),
		);

		foreach ((array) ($context['details'] ?? array()) as $key => $value) {
			$clean_key = sanitize_key((string) $key);
			if ($clean_key === '') {
				continue;
			}
			if (is_array($value)) {
				$details[$clean_key] = array_values(array_map('absint', $value));
			} elseif (is_numeric($value)) {
				$details[$clean_key] = absint($value);
			} else {
				$details[$clean_key] = sanitize_text_field((string) $value);
			}
		}

		$event_plan_id = absint($context['event_plan_id'] ?? 0);
		vms_admission_audit_log($event_plan_id, null, 'pass_claim_denied', 0, 'public', $details);
	}
}

if (!function_exists('vms_pass_outreach_public_error_message_for_claim_error')) {
	function vms_pass_outreach_public_error_message_for_claim_error($error): string
	{
		if (is_wp_error($error)) {
			$code = sanitize_key((string) $error->get_error_code());
			if (in_array($code, vms_pass_outreach_public_business_error_codes(), true)) {
				return vms_pass_outreach_public_failure_message();
			}
			return $error->get_error_message();
		}

		$code = sanitize_key((string) $error);
		if (in_array($code, vms_pass_outreach_public_business_error_codes(), true)) {
			return vms_pass_outreach_public_failure_message();
		}

		return vms_pass_outreach_public_failure_message();
	}
}

if (!function_exists('vms_pass_outreach_render_public_unavailable')) {
	function vms_pass_outreach_render_public_unavailable(array $context = array()): void
	{
		vms_pass_outreach_log_claim_denial($context);

		$html = '<h1>' . esc_html__('Claim Unavailable', 'backstage-outreach') . '</h1>';
		$html .= '<p class="vms-pass-error">' . esc_html(vms_pass_outreach_public_failure_message()) . '</p>';

		if (function_exists('vms_pass_claims_render_public_shell')) {
			vms_pass_claims_render_public_shell(__('Claim Unavailable', 'backstage-outreach'), $html);
		}

		wp_die(esc_html(vms_pass_outreach_public_failure_message()));
	}
}
