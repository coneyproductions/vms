<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_outreach_admin_sections')) {
	function vms_outreach_admin_sections(): array
	{
		return array(
			'campaigns' => __('Campaigns', 'backstage-outreach'),
			'contacts' => __('Contacts / Prospects', 'backstage-outreach'),
			'suppression' => __('Suppression', 'backstage-outreach'),
		);
	}
}

if (!function_exists('vms_outreach_normalize_admin_section')) {
	function vms_outreach_normalize_admin_section(string $section): string
	{
		$section = sanitize_key($section);
		$sections = vms_outreach_admin_sections();
		return isset($sections[$section]) ? $section : 'campaigns';
	}
}

if (!function_exists('vms_outreach_current_admin_section')) {
	function vms_outreach_current_admin_section(): string
	{
		$section = isset($_GET['section']) ? (string) wp_unslash($_GET['section']) : 'campaigns';
		return vms_outreach_normalize_admin_section($section);
	}
}

if (!function_exists('vms_outreach_contacts_filter_args_from_request')) {
	function vms_outreach_contacts_filter_args_from_request(): array
	{
		$args = array();
		$search = isset($_GET['contact_search']) ? sanitize_text_field((string) wp_unslash($_GET['contact_search'])) : '';
		$status = isset($_GET['contact_status']) ? sanitize_key((string) wp_unslash($_GET['contact_status'])) : '';
		$type = isset($_GET['contact_type']) ? sanitize_key((string) wp_unslash($_GET['contact_type'])) : '';
		$suppressed = isset($_GET['contact_suppressed']) ? sanitize_key((string) wp_unslash($_GET['contact_suppressed'])) : '';

		if ($search !== '') {
			$args['contact_search'] = $search;
		}
		if ($status !== '') {
			$args['contact_status'] = $status;
		}
		if ($type !== '') {
			$args['contact_type'] = $type;
		}
		if ($suppressed !== '') {
			$args['contact_suppressed'] = $suppressed;
		}

		return $args;
	}
}

if (!function_exists('vms_outreach_suppression_filter_args_from_request')) {
	function vms_outreach_suppression_filter_args_from_request(): array
	{
		$args = array();
		$search = isset($_GET['suppression_search']) ? sanitize_text_field((string) wp_unslash($_GET['suppression_search'])) : '';
		if ($search !== '') {
			$args['suppression_search'] = $search;
		}

		return $args;
	}
}

if (!function_exists('vms_outreach_contacts_admin_url')) {
	function vms_outreach_contacts_admin_url(array $args = array()): string
	{
		return vms_outreach_admin_page_url(array_merge(array('section' => 'contacts'), $args));
	}
}

if (!function_exists('vms_outreach_suppression_admin_url')) {
	function vms_outreach_suppression_admin_url(array $args = array()): string
	{
		return vms_outreach_admin_page_url(array_merge(array('section' => 'suppression'), $args));
	}
}

if (!function_exists('vms_outreach_admin_section_context')) {
	function vms_outreach_admin_section_context(string $section): string
	{
		return 'outreach-admin:' . vms_outreach_normalize_admin_section($section);
	}
}

if (!function_exists('vms_outreach_contacts_form_anchor')) {
	function vms_outreach_contacts_form_anchor(): string
	{
		return 'vms-outreach-contact-form';
	}
}

if (!function_exists('vms_outreach_contacts_import_anchor')) {
	function vms_outreach_contacts_import_anchor(): string
	{
		return 'vms-outreach-contact-import';
	}
}

if (!function_exists('vms_outreach_suppression_form_anchor')) {
	function vms_outreach_suppression_form_anchor(): string
	{
		return 'vms-outreach-suppression-form';
	}
}

if (!function_exists('vms_outreach_admin_url_with_anchor')) {
	function vms_outreach_admin_url_with_anchor(string $url, string $anchor): string
	{
		$anchor = preg_replace('/[^A-Za-z0-9_-]/', '', $anchor);
		if ($anchor === '') {
			return $url;
		}

		return $url . '#' . $anchor;
	}
}

if (!function_exists('vms_outreach_contacts_form_url')) {
	function vms_outreach_contacts_form_url(array $args = array()): string
	{
		return vms_outreach_admin_url_with_anchor(vms_outreach_contacts_admin_url($args), vms_outreach_contacts_form_anchor());
	}
}

if (!function_exists('vms_outreach_contacts_import_url')) {
	function vms_outreach_contacts_import_url(array $args = array()): string
	{
		return vms_outreach_admin_url_with_anchor(vms_outreach_contacts_admin_url($args), vms_outreach_contacts_import_anchor());
	}
}

if (!function_exists('vms_outreach_suppression_form_url')) {
	function vms_outreach_suppression_form_url(array $args = array()): string
	{
		return vms_outreach_admin_url_with_anchor(vms_outreach_suppression_admin_url($args), vms_outreach_suppression_form_anchor());
	}
}

if (!function_exists('vms_outreach_admin_collapsible_details_attrs')) {
	function vms_outreach_admin_collapsible_details_attrs(string $context, string $section_id, array $args = array()): string
	{
		if (function_exists('vms_pass_outreach_collapsible_details_attrs')) {
			return vms_pass_outreach_collapsible_details_attrs($context, $section_id, $args);
		}

		$classes = array_values(array_filter(array_map('sanitize_html_class', (array) ($args['classes'] ?? array()))));
		if (empty($classes)) {
			$classes = array('vms-pass-card', 'vms-pass-card--collapsible');
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

		if (!empty($args['default_open']) || !empty($args['force_open'])) {
			$attributes[] = 'open';
		}
		if (!empty($args['force_open'])) {
			$attributes[] = 'data-vms-force-open="1"';
		}

		return implode(' ', $attributes);
	}
}

if (!function_exists('vms_outreach_admin_render_collapsible_summary')) {
	function vms_outreach_admin_render_collapsible_summary(string $title, string $meta_html = ''): string
	{
		if (function_exists('vms_pass_outreach_render_collapsible_summary')) {
			return vms_pass_outreach_render_collapsible_summary($title, $meta_html);
		}

		$html = '<summary class="vms-pass-collapsible-panel__summary">';
		$html .= '<span class="vms-pass-collapsible-panel__summary-text">' . esc_html($title) . '</span>';
		if ($meta_html !== '') {
			$html .= '<span class="vms-pass-collapsible-panel__summary-meta">' . $meta_html . '</span>';
		}
		$html .= '</summary>';

		return $html;
	}
}

if (!function_exists('vms_outreach_admin_help_counter')) {
	function vms_outreach_admin_help_counter(): int
	{
		static $counter = 0;
		$counter += 1;
		return $counter;
	}
}

if (!function_exists('vms_outreach_admin_render_help')) {
	function vms_outreach_admin_render_help(string $label, string $message): string
	{
		$message = trim($message);
		if ($message === '') {
			return '';
		}

		$tooltip_id = 'vms-outreach-help-' . vms_outreach_admin_help_counter();
		return '<span class="vms-pass-help"><button type="button" class="vms-pass-help__toggle" aria-describedby="' . esc_attr($tooltip_id) . '" aria-expanded="false" aria-label="' . esc_attr(sprintf(__('More information about %s', 'backstage-outreach'), $label)) . '"><span aria-hidden="true">i</span></button><span id="' . esc_attr($tooltip_id) . '" class="vms-pass-help__popover" role="tooltip">' . esc_html($message) . '</span></span>';
	}
}

if (!function_exists('vms_outreach_admin_render_label')) {
	function vms_outreach_admin_render_label(string $label, array $args = array()): string
	{
		$required = !empty($args['required']);
		$help = isset($args['help']) ? (string) $args['help'] : '';
		$html = '<span class="vms-pass-field-label-row">';
		$html .= '<span class="vms-pass-field-label">' . esc_html($label);
		if ($required) {
			$html .= ' <span class="vms-pass-required-marker" aria-hidden="true">*</span><span class="screen-reader-text">' . esc_html__('Required', 'backstage-outreach') . '</span>';
		}
		$html .= '</span>';
		$html .= vms_outreach_admin_render_help($label, $help);
		$html .= '</span>';
		return $html;
	}
}

if (!function_exists('vms_outreach_admin_render_messages')) {
	function vms_outreach_admin_render_messages(array $field_errors, array $keys = array(), string $description = ''): string
	{
		return function_exists('vms_pass_outreach_render_field_messages')
			? vms_pass_outreach_render_field_messages($field_errors, $keys, $description)
			: '';
	}
}

if (!function_exists('vms_outreach_admin_status_pill')) {
	function vms_outreach_admin_status_pill(string $status, array $labels): string
	{
		$status = sanitize_key($status);
		$label = (string) ($labels[$status] ?? $status);
		return '<span class="vms-pass-status-pill is-' . esc_attr($status) . '">' . esc_html($label) . '</span>';
	}
}

if (!function_exists('vms_outreach_contact_form_flash_key')) {
	function vms_outreach_contact_form_flash_key(int $user_id): string
	{
		return 'vms_outreach_contact_form_flash_' . max(0, $user_id);
	}
}

if (!function_exists('vms_outreach_set_contact_form_flash')) {
	function vms_outreach_set_contact_form_flash(int $user_id, array $payload): void
	{
		if ($user_id <= 0) {
			return;
		}
		set_transient(vms_outreach_contact_form_flash_key($user_id), $payload, 10 * MINUTE_IN_SECONDS);
	}
}

if (!function_exists('vms_outreach_pull_contact_form_flash')) {
	function vms_outreach_pull_contact_form_flash(int $user_id): array
	{
		if ($user_id <= 0) {
			return array();
		}
		$key = vms_outreach_contact_form_flash_key($user_id);
		$data = get_transient($key);
		delete_transient($key);
		return is_array($data) ? $data : array();
	}
}

if (!function_exists('vms_outreach_suppression_form_flash_key')) {
	function vms_outreach_suppression_form_flash_key(int $user_id): string
	{
		return 'vms_outreach_suppression_form_flash_' . max(0, $user_id);
	}
}

if (!function_exists('vms_outreach_set_suppression_form_flash')) {
	function vms_outreach_set_suppression_form_flash(int $user_id, array $payload): void
	{
		if ($user_id <= 0) {
			return;
		}
		set_transient(vms_outreach_suppression_form_flash_key($user_id), $payload, 10 * MINUTE_IN_SECONDS);
	}
}

if (!function_exists('vms_outreach_pull_suppression_form_flash')) {
	function vms_outreach_pull_suppression_form_flash(int $user_id): array
	{
		if ($user_id <= 0) {
			return array();
		}
		$key = vms_outreach_suppression_form_flash_key($user_id);
		$data = get_transient($key);
		delete_transient($key);
		return is_array($data) ? $data : array();
	}
}

if (!function_exists('vms_outreach_contact_validation_error_fields')) {
	function vms_outreach_contact_validation_error_fields(string $code): array
	{
		switch (sanitize_key($code)) {
			case 'invalid_email':
				return array('email');
			default:
				return array();
		}
	}
}

if (!function_exists('vms_outreach_suppression_validation_error_fields')) {
	function vms_outreach_suppression_validation_error_fields(string $code): array
	{
		switch (sanitize_key($code)) {
			case 'invalid_email':
				return array('email');
			default:
				return array();
		}
	}
}

if (!function_exists('vms_outreach_admin_actions_html')) {
	function vms_outreach_admin_actions_html(): string
	{
		$section = vms_outreach_current_admin_section();
		$actions = array();

		if ($section === 'contacts') {
			$filter_args = vms_outreach_contacts_filter_args_from_request();
			$actions[] = '<a class="button vms-outreach-admin-action is-primary is-contacts" href="' . esc_url(vms_outreach_contacts_form_url($filter_args)) . '" data-vms-open-section-target="' . esc_attr(vms_outreach_contacts_form_anchor()) . '">' . esc_html__('New Contact', 'backstage-outreach') . '</a>';
			$actions[] = '<a class="button vms-outreach-admin-action is-secondary is-contacts" href="' . esc_url(vms_outreach_contacts_import_url($filter_args)) . '" data-vms-open-section-target="' . esc_attr(vms_outreach_contacts_import_anchor()) . '">' . esc_html__('Import CSV', 'backstage-outreach') . '</a>';
		} elseif ($section === 'suppression') {
			return '';
		} else {
			if (function_exists('vms_pass_outreach_admin_page_url')) {
				$actions[] = '<a class="button vms-outreach-admin-action is-primary is-campaign" href="' . esc_url(vms_pass_outreach_admin_page_url()) . '">' . esc_html__('New Campaign', 'backstage-outreach') . '</a>';
			}
			if (function_exists('vms_pass_claims_admin_page_url')) {
				$actions[] = '<a class="button vms-outreach-admin-action is-guest-pass" href="' . esc_url(vms_pass_claims_admin_page_url(array('tab' => 'sources'))) . '">' . esc_html__('Guest Pass Sources', 'backstage-outreach') . '</a>';
				$actions[] = '<a class="button vms-outreach-admin-action is-guest-pass" href="' . esc_url(vms_pass_claims_admin_page_url(array('tab' => 'batches'))) . '">' . esc_html__('Guest Pass Batches', 'backstage-outreach') . '</a>';
			}
		}

		return implode(' ', $actions);
	}
}

if (!function_exists('vms_outreach_admin_actions_label')) {
	function vms_outreach_admin_actions_label(string $section): string
	{
		switch (vms_outreach_normalize_admin_section($section)) {
			case 'contacts':
				return __('Contact Actions', 'backstage-outreach');
			default:
				return __('Campaign Actions', 'backstage-outreach');
		}
	}
}

if (!function_exists('vms_outreach_render_admin_screen')) {
	function vms_outreach_render_admin_screen(): void
	{
		$section = vms_outreach_current_admin_section();

		echo '<div class="vms-outreach-admin-toolbar vms-outreach-admin-toolbar--' . esc_attr($section) . '">';
		echo '<nav class="vms-pass-tabs" aria-label="' . esc_attr__('Outreach sections', 'backstage-outreach') . '">';
		foreach (vms_outreach_admin_sections() as $key => $label) {
			$class = 'vms-pass-tab vms-outreach-tab is-' . sanitize_html_class($key);
			if ($key === $section) {
				$class .= ' is-current';
			}
			$url = $key === 'campaigns' ? vms_outreach_admin_page_url() : vms_outreach_admin_page_url(array('section' => $key));
			echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
		}
		echo '</nav>';
		$actions_html = vms_outreach_admin_actions_html();
		if ($actions_html !== '') {
			echo '<div class="vms-outreach-admin-actions vms-outreach-admin-actions--' . esc_attr($section) . '">';
			echo '<span class="vms-outreach-admin-actions__label">' . esc_html(vms_outreach_admin_actions_label($section)) . '</span>';
			echo '<div class="vms-outreach-admin-actions__buttons">' . $actions_html . '</div>';
			echo '</div>';
		}
		echo '</div>';

		if ($section === 'contacts') {
			vms_outreach_render_contacts_screen();
			return;
		}
		if ($section === 'suppression') {
			vms_outreach_render_suppression_screen();
			return;
		}

		if (function_exists('vms_pass_outreach_render_outreach_tab')) {
			vms_pass_outreach_render_outreach_tab();
			return;
		}

		echo '<div class="notice notice-error"><p>' . esc_html__('The Outreach campaigns renderer is unavailable.', 'backstage-outreach') . '</p></div>';
	}
}

if (!function_exists('vms_outreach_render_contacts_screen')) {
	function vms_outreach_render_contacts_screen(): void
	{
		$user_id = get_current_user_id();
		$view = sanitize_key((string) ($_GET['view'] ?? ''));
		$contact_id = absint($_GET['contact_id'] ?? 0);
		$contact = $contact_id > 0 ? vms_outreach_get_contact_by_id($contact_id) : null;
		$field_errors = array();
		$form_payload = is_array($contact) ? $contact : vms_outreach_default_contact_payload();
		$flash = vms_outreach_pull_contact_form_flash($user_id);
		if (absint($flash['contact_id'] ?? 0) === $contact_id) {
			if (!empty($flash['payload']) && is_array($flash['payload'])) {
				$form_payload = array_merge($form_payload, vms_outreach_normalize_contact_row((array) $flash['payload']));
			}
			if (!empty($flash['field_errors']) && is_array($flash['field_errors'])) {
				$field_errors = array_map('sanitize_text_field', (array) $flash['field_errors']);
			}
		}

		$search = sanitize_text_field((string) ($_GET['contact_search'] ?? ''));
		$status = sanitize_key((string) ($_GET['contact_status'] ?? ''));
		$contact_type = sanitize_key((string) ($_GET['contact_type'] ?? ''));
		$suppressed_filter = sanitize_key((string) ($_GET['contact_suppressed'] ?? ''));
		$contacts = vms_outreach_get_contacts(array(
			'search' => $search,
			'status' => $status,
			'contact_type' => $contact_type,
			'suppressed' => $suppressed_filter,
			'limit' => 250,
		));
		$type_options = vms_outreach_contact_type_options();
		$status_options = vms_outreach_contact_status_options();
		$suppression_reason_options = vms_outreach_suppression_reason_options();
		$mapping_state = vms_outreach_get_contact_import_mapping($user_id);
		$preview_state = vms_outreach_get_contact_import_preview($user_id);
		$header_row = array_values(array_map('sanitize_text_field', (array) ($mapping_state['header_row'] ?? array())));
		$sample_values = array_values(array_map('sanitize_text_field', (array) ($mapping_state['sample_values'] ?? array())));
		$selected_mapping = vms_outreach_normalize_selected_contact_csv_mapping((array) ($mapping_state['selected_mapping'] ?? array()), $header_row);
		$mapping_options = vms_outreach_contact_import_mapping_options();
		$has_mapping = !empty($header_row);
		$has_preview = !empty($preview_state);
		$preview_rows = array_values((array) ($preview_state['preview_rows'] ?? array()));
		$invalid_rows = array_values((array) ($preview_state['invalid_rows'] ?? array()));
		$section_context = vms_outreach_admin_section_context('contacts');
		$show_contact_form = $view === 'edit' || $contact_id > 0 || !empty($field_errors);
		$show_import_form = $view === 'import' || $has_mapping || $has_preview;

		echo '<p class="description vms-pass-tab-intro">' . esc_html__('Shared prospect records for non-customer outreach. These contacts stay inside VMS and never sync to MailPoet.', 'backstage-outreach') . '</p>';

		echo '<details ' . vms_outreach_admin_collapsible_details_attrs($section_context, 'contact_form', array(
			'classes' => array('vms-pass-card', 'vms-pass-card--collapsible', 'vms-outreach-inline-card', 'vms-outreach-inline-card--contact'),
			'default_open' => false,
			'force_open' => $show_contact_form,
			'anchor' => vms_outreach_contacts_form_anchor(),
		)) . '>';
		echo vms_outreach_admin_render_collapsible_summary($contact_id > 0 ? __('Edit Contact / Prospect', 'backstage-outreach') : __('Add New Contact', 'backstage-outreach'));
		echo '<div class="vms-outreach-inline-card__body">';
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-form vms-pass-form--full">';
			echo '<input type="hidden" name="action" value="vms_outreach_contact_save">';
			echo '<input type="hidden" name="contact_id" value="' . esc_attr((string) $contact_id) . '">';
			vms_outreach_nonce_field(
				'vms_outreach_contact_save',
				'vms-outreach-contact-save-' . $contact_id . '-nonce'
			);

			if (absint($form_payload['suppression_id'] ?? 0) > 0) {
				echo '<div class="vms-pass-guardrail-note">';
				echo '<div class="vms-pass-guardrail-note__copy"><strong>' . esc_html__('Suppressed globally.', 'backstage-outreach') . '</strong> ' . esc_html__('Future outreach queue logic should exclude this email until an admin removes suppression.', 'backstage-outreach') . '</div>';
				echo '<div class="vms-pass-guardrail-note__meta">' . esc_html(sprintf(__('Reason: %1$s. Suppressed: %2$s.', 'backstage-outreach'), (string) ($suppression_reason_options[(string) ($form_payload['suppression_reason'] ?? '')] ?? (string) ($form_payload['suppression_reason'] ?? '')), function_exists('vms_pass_outreach_format_admin_datetime') ? vms_pass_outreach_format_admin_datetime((string) ($form_payload['suppressed_at'] ?? '')) : (string) ($form_payload['suppressed_at'] ?? ''))) . '</div>';
				echo '<div class="vms-pass-callout-actions"><a class="button" href="' . esc_url(vms_outreach_suppression_form_url(array('suppression_id' => (int) ($form_payload['suppression_id'] ?? 0)))) . '">' . esc_html__('Manage Suppression', 'backstage-outreach') . '</a></div>';
				echo '</div>';
			}

			echo '<div class="vms-pass-form-sections">';

			echo '<section class="vms-pass-form-section"><div class="vms-pass-form-section__header"><h3>' . esc_html__('Identity', 'backstage-outreach') . '</h3></div><div class="vms-pass-grid">';
			echo '<label class="vms-pass-span-2' . (!empty($field_errors['contact_name']) ? ' vms-pass-field-has-error' : '') . '">' . vms_outreach_admin_render_label(__('Contact Name', 'backstage-outreach'), array('help' => __('Full contact name. If first or last name is blank, VMS will derive them when possible.', 'backstage-outreach'))) . '<input type="text" name="contact_name" value="' . esc_attr((string) ($form_payload['contact_name'] ?? '')) . '" placeholder="' . esc_attr__('Jamie Smith', 'backstage-outreach') . '">' . vms_outreach_admin_render_messages($field_errors, array('contact_name')) . '</label>';
			echo '<label' . vms_pass_outreach_field_wrapper_class($field_errors, array('first_name')) . '>' . vms_outreach_admin_render_label(__('First Name', 'backstage-outreach')) . '<input type="text" name="first_name" value="' . esc_attr((string) ($form_payload['first_name'] ?? '')) . '"></label>';
			echo '<label' . vms_pass_outreach_field_wrapper_class($field_errors, array('last_name')) . '>' . vms_outreach_admin_render_label(__('Last Name', 'backstage-outreach')) . '<input type="text" name="last_name" value="' . esc_attr((string) ($form_payload['last_name'] ?? '')) . '"></label>';
			echo '<label class="vms-pass-span-2' . (!empty($field_errors['email']) ? ' vms-pass-field-has-error' : '') . '">' . vms_outreach_admin_render_label(__('Email', 'backstage-outreach'), array('required' => true, 'help' => __('Primary dedupe key. CSV imports and future queueing treat email as the canonical contact identity.', 'backstage-outreach'))) . '<input type="email" name="email" value="' . esc_attr((string) ($form_payload['email'] ?? '')) . '" required>' . vms_outreach_admin_render_messages($field_errors, array('email')) . '</label>';
			echo '<label>' . vms_outreach_admin_render_label(__('Phone', 'backstage-outreach')) . '<input type="text" name="phone" value="' . esc_attr((string) ($form_payload['phone'] ?? '')) . '"></label>';
			echo '<label' . vms_pass_outreach_field_wrapper_class($field_errors, array('status')) . '>' . vms_outreach_admin_render_label(__('Status', 'backstage-outreach'), array('help' => __('Setting a contact to Do Not Contact also writes a global suppression record. Removing suppression later is still a separate admin action.', 'backstage-outreach'))) . '<select name="status">';
			foreach ($status_options as $status_key => $status_label) {
				echo '<option value="' . esc_attr($status_key) . '"' . selected((string) ($form_payload['status'] ?? 'new'), $status_key, false) . '>' . esc_html($status_label) . '</option>';
			}
			echo '</select>' . vms_outreach_admin_render_messages($field_errors, array('status')) . '</label>';
			echo '</div></section>';

			echo '<section class="vms-pass-form-section"><div class="vms-pass-form-section__header"><h3>' . esc_html__('Business / Classification', 'backstage-outreach') . '</h3></div><div class="vms-pass-grid">';
			echo '<label>' . vms_outreach_admin_render_label(__('Business Name', 'backstage-outreach')) . '<input type="text" name="business_name" value="' . esc_attr((string) ($form_payload['business_name'] ?? '')) . '"></label>';
			echo '<label>' . vms_outreach_admin_render_label(__('Company / Group', 'backstage-outreach')) . '<input type="text" name="company_group" value="' . esc_attr((string) ($form_payload['company_group'] ?? '')) . '"></label>';
			echo '<label>' . vms_outreach_admin_render_label(__('Type', 'backstage-outreach'), array('help' => __('Primary outreach classification. Use tags for extra nuance instead of creating a new type system now.', 'backstage-outreach'))) . '<select name="contact_type">';
			foreach ($type_options as $type_key => $type_label) {
				echo '<option value="' . esc_attr($type_key) . '"' . selected((string) ($form_payload['contact_type'] ?? 'other'), $type_key, false) . '>' . esc_html($type_label) . '</option>';
			}
			echo '</select></label>';
			echo '<label>' . vms_outreach_admin_render_label(__('Tags', 'backstage-outreach'), array('help' => __('Optional comma-separated tags.', 'backstage-outreach'))) . '<input type="text" name="tags" value="' . esc_attr((string) ($form_payload['tags'] ?? '')) . '" placeholder="' . esc_attr__('north side, luxury, referral', 'backstage-outreach') . '"></label>';
			echo '<label class="vms-pass-span-2">' . vms_outreach_admin_render_label(__('Source', 'backstage-outreach'), array('help' => __('Where this prospect came from: import list, operator note, referral, event lead, and so on.', 'backstage-outreach'))) . '<input type="text" name="source" value="' . esc_attr((string) ($form_payload['source'] ?? '')) . '"></label>';
			echo '</div></section>';

			echo '<section class="vms-pass-form-section"><div class="vms-pass-form-section__header"><h3>' . esc_html__('Location / Web', 'backstage-outreach') . '</h3></div><div class="vms-pass-grid">';
			echo '<label>' . vms_outreach_admin_render_label(__('City', 'backstage-outreach')) . '<input type="text" name="city" value="' . esc_attr((string) ($form_payload['city'] ?? '')) . '"></label>';
			echo '<label>' . vms_outreach_admin_render_label(__('State', 'backstage-outreach')) . '<input type="text" name="state" value="' . esc_attr((string) ($form_payload['state'] ?? '')) . '"></label>';
			echo '<label class="vms-pass-span-2">' . vms_outreach_admin_render_label(__('Website', 'backstage-outreach')) . '<input type="text" name="website" value="' . esc_attr((string) ($form_payload['website'] ?? '')) . '" placeholder="' . esc_attr__('https://example.com', 'backstage-outreach') . '"></label>';
			echo '<label>' . vms_outreach_admin_render_label(__('Facebook URL', 'backstage-outreach')) . '<input type="text" name="facebook_url" value="' . esc_attr((string) ($form_payload['facebook_url'] ?? '')) . '"></label>';
			echo '<label>' . vms_outreach_admin_render_label(__('Instagram URL', 'backstage-outreach')) . '<input type="text" name="instagram_url" value="' . esc_attr((string) ($form_payload['instagram_url'] ?? '')) . '"></label>';
			echo '</div></section>';

			echo '<section class="vms-pass-form-section"><div class="vms-pass-form-section__header"><h3>' . esc_html__('Notes', 'backstage-outreach') . '</h3></div><div class="vms-pass-grid">';
			echo '<label class="vms-pass-span-2">' . vms_outreach_admin_render_label(__('Notes', 'backstage-outreach')) . '<textarea name="notes" rows="5">' . esc_textarea((string) ($form_payload['notes'] ?? '')) . '</textarea></label>';
			echo '</div></section>';

			echo '</div>';
			echo '<p class="vms-pass-actions"><button type="submit" class="button button-primary">' . esc_html($contact_id > 0 ? __('Save Contact', 'backstage-outreach') : __('Add Contact', 'backstage-outreach')) . '</button> <a class="button" href="' . esc_url(vms_outreach_contacts_admin_url()) . '">' . esc_html__('Back to Contacts', 'backstage-outreach') . '</a></p>';
			echo '</form>';
		echo '</div>';
		echo '</details>';

		echo '<details ' . vms_outreach_admin_collapsible_details_attrs($section_context, 'contact_import', array(
			'classes' => array('vms-pass-card', 'vms-pass-card--collapsible', 'vms-outreach-inline-card', 'vms-outreach-inline-card--import'),
			'default_open' => $show_import_form,
			'force_open' => $view === 'import',
			'anchor' => vms_outreach_contacts_import_anchor(),
		)) . '>';
		echo vms_outreach_admin_render_collapsible_summary(__('Import Contacts CSV', 'backstage-outreach'));
		echo '<div class="vms-outreach-inline-card__body">';
			echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-form vms-pass-form--full">';
			echo '<input type="hidden" name="action" value="vms_outreach_contact_import">';
			vms_outreach_nonce_field('vms_outreach_contact_import', 'vms-outreach-contact-import-nonce');
			echo '<div class="vms-pass-form-sections">';
			echo '<section class="vms-pass-form-section"><div class="vms-pass-form-section__header"><h3>' . esc_html__('Upload', 'backstage-outreach') . '</h3></div><div class="vms-pass-grid">';
			echo '<label class="vms-pass-span-2 vms-pass-upload-field">' . vms_outreach_admin_render_label(__('CSV File', 'backstage-outreach'), array('required' => true, 'help' => sprintf(__('Upload whatever columns you have. VMS will suggest a mapping, let you adjust it, then preview the import before anything is committed. Up to %d rows per upload.', 'backstage-outreach'), vms_outreach_contact_import_row_limit()))) . '<div class="vms-pass-file-input-row"><input type="file" name="contact_csv" accept=".csv,text/csv"></div></label>';
			echo '</div></section>';
			echo '<p class="vms-pass-actions">';
			echo '<button type="submit" class="button button-primary" name="save_mode" value="map">' . esc_html__('Review Selected CSV', 'backstage-outreach') . '</button> ';
			if ($has_mapping || $has_preview) {
				echo '<button type="submit" class="button" name="save_mode" value="clear">' . esc_html__('Discard Saved Preview', 'backstage-outreach') . '</button> ';
			}
			echo '</p>';

			if ($has_mapping) {
				echo '<section class="vms-pass-form-section"><div class="vms-pass-form-section__header"><h3>' . esc_html__('Column Mapping', 'backstage-outreach') . '</h3></div>';
				echo '<div class="vms-pass-preview-summary vms-pass-preview-summary--mapping">';
				echo '<table class="widefat striped vms-pass-mapping-table"><thead><tr><th>' . esc_html__('Uploaded Column', 'backstage-outreach') . '</th><th>' . esc_html__('Sample Value', 'backstage-outreach') . '</th><th>' . esc_html__('Map To', 'backstage-outreach') . '</th></tr></thead><tbody>';
				foreach ($header_row as $index => $header_label) {
					$current_mapping = sanitize_key((string) ($selected_mapping[$index] ?? ''));
					$sample_value = (string) ($sample_values[$index] ?? '');
					echo '<tr>';
					echo '<td><strong>' . esc_html($header_label !== '' ? $header_label : sprintf(__('Column %d', 'backstage-outreach'), $index + 1)) . '</strong></td>';
					echo '<td class="vms-pass-mapping-table__sample">' . esc_html($sample_value !== '' ? $sample_value : '—') . '</td>';
					echo '<td><select name="csv_mapping[' . esc_attr((string) $index) . ']">';
					foreach ($mapping_options as $field_key => $field_label) {
						echo '<option value="' . esc_attr((string) $field_key) . '"' . selected($current_mapping, (string) $field_key, false) . '>' . esc_html((string) $field_label) . '</option>';
					}
					echo '</select></td>';
					echo '</tr>';
				}
				echo '</tbody></table>';
				echo '<p class="description">' . esc_html(sprintf(__('CSV: %1$s. Rows detected: %2$d.', 'backstage-outreach'), sanitize_file_name((string) ($mapping_state['file_name'] ?? '')), count((array) ($mapping_state['data_rows'] ?? array())))) . '</p>';
				echo '</div></section>';
			}

			if ($has_preview) {
				echo '<section class="vms-pass-form-section"><div class="vms-pass-form-section__header"><h3>' . esc_html__('Preview', 'backstage-outreach') . '</h3></div>';
				echo '<div class="vms-pass-preview-stack">';
				echo '<div class="vms-pass-preview-summary"><table class="widefat striped"><tbody>';
				$summary_rows = array(
					__('Preview File', 'backstage-outreach') => sanitize_text_field((string) ($preview_state['file_name'] ?? '')),
					__('New Contacts', 'backstage-outreach') => (string) absint($preview_state['new_count'] ?? 0),
					__('Existing Contacts Updated', 'backstage-outreach') => (string) absint($preview_state['update_count'] ?? 0),
					__('Duplicates Skipped / Merged', 'backstage-outreach') => (string) absint($preview_state['duplicate_merge_count'] ?? 0),
					__('Suppressed Contacts Detected', 'backstage-outreach') => (string) absint($preview_state['suppressed_count'] ?? 0),
					__('Invalid Emails', 'backstage-outreach') => (string) absint($preview_state['invalid_email_count'] ?? 0),
					__('Blank Rows Ignored', 'backstage-outreach') => (string) absint($preview_state['blank_rows'] ?? 0),
				);
				foreach ($summary_rows as $label => $value) {
					echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
				}
				echo '</tbody></table></div>';
				echo '<div class="vms-pass-preview-summary"><h3>' . esc_html__('Preview Rows', 'backstage-outreach') . '</h3>';
				echo '<div class="vms-pass-table-scroll vms-pass-table-scroll--outreach-import">';
				echo '<div class="vms-outreach-import-preview-head" role="row">';
				echo '<span role="columnheader">' . esc_html__('Rows', 'backstage-outreach') . '</span>';
				echo '<span role="columnheader">' . esc_html__('Email', 'backstage-outreach') . '</span>';
				echo '<span role="columnheader">' . esc_html__('Contact', 'backstage-outreach') . '</span>';
				echo '<span role="columnheader">' . esc_html__('Business', 'backstage-outreach') . '</span>';
				echo '<span role="columnheader">' . esc_html__('Action', 'backstage-outreach') . '</span>';
				echo '<span role="columnheader">' . esc_html__('Suppressed', 'backstage-outreach') . '</span>';
				echo '<span role="columnheader">' . esc_html__('Type', 'backstage-outreach') . '</span>';
				echo '<span role="columnheader">' . esc_html__('Status', 'backstage-outreach') . '</span>';
				echo '</div>';
				echo '<table class="widefat striped vms-outreach-import-preview-table" aria-label="' . esc_attr__('Preview Rows', 'backstage-outreach') . '"><colgroup><col class="vms-outreach-import-preview-table__col-rows"><col class="vms-outreach-import-preview-table__col-email"><col class="vms-outreach-import-preview-table__col-contact"><col class="vms-outreach-import-preview-table__col-business"><col class="vms-outreach-import-preview-table__col-action"><col class="vms-outreach-import-preview-table__col-suppressed"><col class="vms-outreach-import-preview-table__col-type"><col class="vms-outreach-import-preview-table__col-status"></colgroup><tbody>';
				if (empty($preview_rows)) {
					echo '<tr><td colspan="8">' . esc_html__('No valid rows are ready yet.', 'backstage-outreach') . '</td></tr>';
				} else {
					foreach (array_slice($preview_rows, 0, 20) as $row) {
						$action_label = (string) ($row['action'] ?? '') === 'update' ? __('Update', 'backstage-outreach') : __('New', 'backstage-outreach');
						$suppressed_label = !empty($row['suppressed'])
							? (string) ($suppression_reason_options[(string) ($row['suppression_reason'] ?? '')] ?? __('Yes', 'backstage-outreach'))
							: __('No', 'backstage-outreach');
						echo '<tr>';
						echo '<td>' . esc_html(implode(', ', array_map('absint', (array) ($row['row_numbers'] ?? array())))) . '</td>';
						echo '<td>' . esc_html((string) ($row['email'] ?? '')) . '</td>';
						echo '<td>' . esc_html((string) ($row['contact_name'] ?? '')) . '</td>';
						echo '<td>' . esc_html((string) ($row['business_name'] ?? '')) . '</td>';
						echo '<td>' . esc_html($action_label) . '</td>';
						echo '<td>' . esc_html($suppressed_label) . '</td>';
						echo '<td>' . esc_html((string) ($type_options[(string) ($row['contact_type'] ?? 'other')] ?? (string) ($row['contact_type'] ?? ''))) . '</td>';
						echo '<td>' . esc_html((string) ($status_options[(string) ($row['status'] ?? 'new')] ?? (string) ($row['status'] ?? ''))) . '</td>';
						echo '</tr>';
					}
				}
				echo '</tbody></table></div>';
				if (!empty($invalid_rows)) {
					$invalid_labels = array();
					foreach (array_slice($invalid_rows, 0, 6) as $invalid_row) {
						$invalid_labels[] = sprintf(__('row %1$d: %2$s', 'backstage-outreach'), absint($invalid_row['row_number'] ?? 0), sanitize_text_field((string) ($invalid_row['reason'] ?? '')));
					}
					echo '<p class="description">' . esc_html__('Invalid row details:', 'backstage-outreach') . ' ' . esc_html(implode('; ', $invalid_labels)) . '</p>';
				}
				echo '</div></div></section>';
			}

			echo '</div>';
			echo '<p class="vms-pass-actions">';
			if ($has_mapping) {
				echo '<button type="submit" class="' . esc_attr($has_preview ? 'button' : 'button button-primary') . '" name="save_mode" value="preview">' . esc_html($has_preview ? __('Refresh Preview', 'backstage-outreach') : __('Preview Import', 'backstage-outreach')) . '</button> ';
			}
			if ($has_preview && !empty($preview_rows)) {
				echo '<button type="submit" class="button button-primary" name="save_mode" value="commit">' . esc_html__('Commit Import', 'backstage-outreach') . '</button> ';
			}
			if ($has_mapping || $has_preview) {
				echo '<button type="submit" class="button" name="save_mode" value="clear">' . esc_html__('Discard Saved Preview', 'backstage-outreach') . '</button> ';
			}
			echo '<a class="button" href="' . esc_url(vms_outreach_contacts_admin_url()) . '">' . esc_html__('Back to Contacts', 'backstage-outreach') . '</a>';
			echo '</p>';
			echo '</form>';
		echo '</div>';
		echo '</details>';

		echo '<section class="vms-pass-card">';
		echo '<h2>' . esc_html__('Contacts / Prospects', 'backstage-outreach') . '</h2>';
		echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" class="vms-pass-form vms-pass-campaign-filters">';
		echo '<input type="hidden" name="page" value="' . esc_attr(vms_outreach_admin_menu_slug()) . '">';
		echo '<input type="hidden" name="section" value="contacts">';
		echo '<div class="vms-pass-grid">';
		echo '<label class="vms-pass-span-2">' . vms_outreach_admin_render_label(__('Search', 'backstage-outreach')) . '<input type="text" name="contact_search" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('name, email, business, city, source', 'backstage-outreach') . '"></label>';
		echo '<label>' . vms_outreach_admin_render_label(__('Status', 'backstage-outreach')) . '<select name="contact_status"><option value="">' . esc_html__('All statuses', 'backstage-outreach') . '</option>';
		foreach ($status_options as $status_key => $status_label) {
			echo '<option value="' . esc_attr($status_key) . '"' . selected($status, $status_key, false) . '>' . esc_html($status_label) . '</option>';
		}
		echo '</select></label>';
		echo '<label>' . vms_outreach_admin_render_label(__('Type', 'backstage-outreach')) . '<select name="contact_type"><option value="">' . esc_html__('All types', 'backstage-outreach') . '</option>';
		foreach ($type_options as $type_key => $type_label) {
			echo '<option value="' . esc_attr($type_key) . '"' . selected($contact_type, $type_key, false) . '>' . esc_html($type_label) . '</option>';
		}
		echo '</select></label>';
		echo '<label>' . vms_outreach_admin_render_label(__('Suppressed', 'backstage-outreach')) . '<select name="contact_suppressed"><option value="">' . esc_html__('All contacts', 'backstage-outreach') . '</option><option value="yes"' . selected($suppressed_filter, 'yes', false) . '>' . esc_html__('Suppressed only', 'backstage-outreach') . '</option><option value="no"' . selected($suppressed_filter, 'no', false) . '>' . esc_html__('Unsuppressed only', 'backstage-outreach') . '</option></select></label>';
		echo '</div>';
		echo '<p class="vms-pass-actions"><button type="submit" class="button">' . esc_html__('Apply Filters', 'backstage-outreach') . '</button> <a class="button" href="' . esc_url(vms_outreach_contacts_admin_url()) . '">' . esc_html__('Reset', 'backstage-outreach') . '</a></p>';
		echo '</form>';
		echo '<div class="vms-pass-table-scroll vms-pass-table-scroll--outreach-contacts" data-vms-sticky-table="1">';
		echo '<table class="widefat striped vms-outreach-contact-table"><thead><tr><th>' . esc_html__('Contact', 'backstage-outreach') . '</th><th>' . esc_html__('Business', 'backstage-outreach') . '</th><th>' . esc_html__('Type / Tags', 'backstage-outreach') . '</th><th>' . esc_html__('Location', 'backstage-outreach') . '</th><th class="vms-pass-table-cell--center">' . esc_html__('Status', 'backstage-outreach') . '</th><th>' . esc_html__('Source', 'backstage-outreach') . '</th><th>' . esc_html__('Updated', 'backstage-outreach') . '</th><th class="vms-pass-table-cell--center">' . esc_html__('Actions', 'backstage-outreach') . '</th></tr></thead><tbody>';
		if (empty($contacts)) {
			echo '<tr><td colspan="8">' . esc_html__('No contacts matched this view.', 'backstage-outreach') . '</td></tr>';
		} else {
			foreach ($contacts as $row) {
				$row_id = absint($row['id'] ?? 0);
				echo '<tr>';
				echo '<td><strong>' . esc_html(vms_outreach_contact_display_name($row)) . '</strong><div class="description">' . esc_html((string) ($row['email'] ?? '')) . '</div>';
				if (!empty($row['phone'])) {
					echo '<div class="description">' . esc_html((string) $row['phone']) . '</div>';
				}
				echo '</td>';
				echo '<td>' . esc_html((string) ($row['business_name'] ?? '')) . (!empty($row['company_group']) ? '<div class="description">' . esc_html((string) $row['company_group']) . '</div>' : '') . '</td>';
				echo '<td>' . esc_html((string) ($type_options[(string) ($row['contact_type'] ?? 'other')] ?? (string) ($row['contact_type'] ?? ''))) . (!empty($row['tags']) ? '<div class="description">' . esc_html((string) $row['tags']) . '</div>' : '') . '</td>';
				echo '<td>' . esc_html(vms_outreach_contact_location_label($row)) . '</td>';
				echo '<td class="vms-pass-table-cell--center">' . vms_outreach_admin_status_pill((string) ($row['status'] ?? 'new'), $status_options);
				if (absint($row['suppression_id'] ?? 0) > 0) {
					echo ' ' . vms_outreach_admin_status_pill('suppressed', array('suppressed' => __('Suppressed', 'backstage-outreach')));
				}
				echo '</td>';
				echo '<td>' . esc_html((string) ($row['source'] ?? '')) . '</td>';
				$updated_at = (string) ($row['updated_at'] ?: ($row['created_at'] ?? ''));
				echo '<td>' . esc_html(function_exists('vms_pass_outreach_format_admin_datetime') ? vms_pass_outreach_format_admin_datetime($updated_at) : $updated_at) . '</td>';
				echo '<td class="vms-pass-campaign-actions"><a class="button button-small" href="' . esc_url(vms_outreach_contacts_form_url(array('contact_id' => $row_id))) . '">' . esc_html__('Edit', 'backstage-outreach') . '</a> ';
				if (absint($row['suppression_id'] ?? 0) > 0) {
					echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-outreach-inline-form">';
					echo '<input type="hidden" name="action" value="vms_outreach_suppression_remove">';
					echo '<input type="hidden" name="suppression_id" value="' . esc_attr((string) ($row['suppression_id'] ?? 0)) . '">';
					vms_outreach_nonce_field(
						'vms_outreach_suppression_remove',
						'vms-outreach-contact-suppression-remove-' . $row_id . '-nonce'
					);
					echo '<button type="submit" class="button button-small" onclick="return window.confirm(' . esc_attr(wp_json_encode(__('Remove suppression for this email?', 'backstage-outreach'))) . ');">' . esc_html__('Unsuppress', 'backstage-outreach') . '</button>';
					echo '</form> ';
				} else {
					echo '<a class="button button-small" href="' . esc_url(vms_outreach_suppression_form_url(array('contact_id' => $row_id))) . '">' . esc_html__('Suppress', 'backstage-outreach') . '</a> ';
				}
				echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-outreach-inline-form">';
				echo '<input type="hidden" name="action" value="vms_outreach_contact_delete">';
				echo '<input type="hidden" name="contact_id" value="' . esc_attr((string) $row_id) . '">';
				vms_outreach_nonce_field(
					'vms_outreach_contact_delete',
					'vms-outreach-contact-delete-' . $row_id . '-nonce'
				);
				echo '<button type="submit" class="button button-small" onclick="return window.confirm(' . esc_attr(wp_json_encode(__('Delete this contact? Suppression records, if any, will be kept.', 'backstage-outreach'))) . ');">' . esc_html__('Delete', 'backstage-outreach') . '</button>';
				echo '</form></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '</div>';
		echo '</section>';
	}
}

if (!function_exists('vms_outreach_render_suppression_screen')) {
	function vms_outreach_render_suppression_screen(): void
	{
		$user_id = get_current_user_id();
		$view = sanitize_key((string) ($_GET['view'] ?? ''));
		$suppression_id = absint($_GET['suppression_id'] ?? 0);
		$contact_id = absint($_GET['contact_id'] ?? 0);
		$suppression = $suppression_id > 0 ? vms_outreach_get_suppression_by_id($suppression_id) : null;
		$contact = $contact_id > 0 ? vms_outreach_get_contact_by_id($contact_id) : null;
		$field_errors = array();
		$form_payload = is_array($suppression) ? $suppression : vms_outreach_default_suppression_payload();

		if (!is_array($suppression) && is_array($contact)) {
			$form_payload['email'] = (string) ($contact['email'] ?? '');
			$form_payload['source_contact_id'] = (int) ($contact['id'] ?? 0);
			$form_payload['source_label'] = sprintf(
				/* translators: %s: contact display name */
				__('Manual suppression from contact %s', 'backstage-outreach'),
				vms_outreach_contact_display_name($contact)
			);
		}

		$flash = vms_outreach_pull_suppression_form_flash($user_id);
		if (absint($flash['suppression_id'] ?? 0) === $suppression_id) {
			if (!empty($flash['payload']) && is_array($flash['payload'])) {
				$form_payload = array_merge($form_payload, vms_outreach_normalize_suppression_row((array) $flash['payload']));
			}
			if (!empty($flash['field_errors']) && is_array($flash['field_errors'])) {
				$field_errors = array_map('sanitize_text_field', (array) $flash['field_errors']);
			}
		}

		$search = sanitize_text_field((string) ($_GET['suppression_search'] ?? ''));
		$suppressions = vms_outreach_get_suppressions(array(
			'search' => $search,
			'limit' => 250,
		));
		$reason_options = vms_outreach_suppression_reason_options();
		echo '<p class="description vms-pass-tab-intro">' . esc_html__('Global outreach suppression lives outside any single campaign. Deleting a contact does not clear suppression, and re-imports stay blocked until an admin removes it.', 'backstage-outreach') . '</p>';

		echo '<section id="' . esc_attr(vms_outreach_suppression_form_anchor()) . '" class="vms-pass-card vms-outreach-inline-card vms-outreach-inline-card--suppression">';
		echo '<h2>' . esc_html($suppression_id > 0 ? __('Edit Suppression', 'backstage-outreach') : __('Add Suppression', 'backstage-outreach')) . '</h2>';
		echo '<div class="vms-outreach-inline-card__body">';
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-form">';
			echo '<input type="hidden" name="action" value="vms_outreach_suppression_save">';
			echo '<input type="hidden" name="suppression_id" value="' . esc_attr((string) $suppression_id) . '">';
			echo '<input type="hidden" name="scope" value="' . esc_attr(vms_outreach_default_suppression_scope()) . '">';
			echo '<input type="hidden" name="source_contact_id" value="' . esc_attr((string) absint($form_payload['source_contact_id'] ?? 0)) . '">';
			echo '<input type="hidden" name="source_campaign_id" value="' . esc_attr((string) absint($form_payload['source_campaign_id'] ?? 0)) . '">';
			echo '<input type="hidden" name="source_label" value="' . esc_attr((string) ($form_payload['source_label'] ?? '')) . '">';
			vms_outreach_nonce_field(
				'vms_outreach_suppression_save',
				'vms-outreach-suppression-save-' . $suppression_id . '-nonce'
			);
			echo '<div class="vms-pass-grid">';
			echo '<label class="vms-pass-span-2' . (!empty($field_errors['email']) ? ' vms-pass-field-has-error' : '') . '">' . vms_outreach_admin_render_label(__('Email', 'backstage-outreach'), array('required' => true, 'help' => __('This address will be excluded from future Outreach queueing until suppression is removed.', 'backstage-outreach'))) . '<input type="email" name="email" value="' . esc_attr((string) ($form_payload['email'] ?? '')) . '" required>' . vms_outreach_admin_render_messages($field_errors, array('email')) . '</label>';
			echo '<label>' . vms_outreach_admin_render_label(__('Reason', 'backstage-outreach')) . '<select name="reason">';
			foreach ($reason_options as $reason_key => $reason_label) {
				echo '<option value="' . esc_attr($reason_key) . '"' . selected((string) ($form_payload['reason'] ?? 'manual_admin'), $reason_key, false) . '>' . esc_html($reason_label) . '</option>';
			}
			echo '</select></label>';
			echo '<label>' . vms_outreach_admin_render_label(__('Scope', 'backstage-outreach')) . '<input type="text" value="' . esc_attr((string) (vms_outreach_suppression_scope_labels()[vms_outreach_default_suppression_scope()] ?? vms_outreach_default_suppression_scope())) . '" readonly></label>';
			echo '<label class="vms-pass-span-2">' . vms_outreach_admin_render_label(__('Source Context', 'backstage-outreach'), array('help' => __('Optional reference to the contact or campaign that led to this suppression.', 'backstage-outreach'))) . '<input type="text" value="' . esc_attr((string) ($form_payload['source_label'] ?? '')) . '" readonly></label>';
			echo '<label class="vms-pass-span-2">' . vms_outreach_admin_render_label(__('Notes', 'backstage-outreach')) . '<textarea name="notes" rows="5">' . esc_textarea((string) ($form_payload['notes'] ?? '')) . '</textarea></label>';
			echo '</div>';
			echo '<p class="vms-pass-actions"><button type="submit" class="button button-primary">' . esc_html($suppression_id > 0 ? __('Save Suppression', 'backstage-outreach') : __('Add Suppression', 'backstage-outreach')) . '</button> <a class="button" href="' . esc_url(vms_outreach_suppression_admin_url()) . '">' . esc_html__('Back to Suppression', 'backstage-outreach') . '</a></p>';
			echo '</form>';
		echo '</div>';
		echo '</section>';

		echo '<section class="vms-pass-card">';
		echo '<h2>' . esc_html__('Suppression List', 'backstage-outreach') . '</h2>';
		echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" class="vms-pass-form vms-pass-campaign-filters">';
		echo '<input type="hidden" name="page" value="' . esc_attr(vms_outreach_admin_menu_slug()) . '">';
		echo '<input type="hidden" name="section" value="suppression">';
		echo '<div class="vms-pass-grid">';
		echo '<label class="vms-pass-span-2">' . vms_outreach_admin_render_label(__('Search', 'backstage-outreach')) . '<input type="text" name="suppression_search" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('email, reason, contact, source', 'backstage-outreach') . '"></label>';
		echo '</div>';
		echo '<p class="vms-pass-actions"><button type="submit" class="button">' . esc_html__('Apply Filters', 'backstage-outreach') . '</button> <a class="button" href="' . esc_url(vms_outreach_suppression_admin_url()) . '">' . esc_html__('Reset', 'backstage-outreach') . '</a></p>';
		echo '</form>';
		echo '<div class="vms-pass-table-scroll vms-pass-table-scroll--outreach-suppression" data-vms-sticky-table="1">';
		echo '<table class="widefat striped vms-outreach-suppression-table"><thead><tr><th>' . esc_html__('Email', 'backstage-outreach') . '</th><th>' . esc_html__('Reason', 'backstage-outreach') . '</th><th>' . esc_html__('Linked Contact', 'backstage-outreach') . '</th><th>' . esc_html__('Source Context', 'backstage-outreach') . '</th><th>' . esc_html__('Suppressed', 'backstage-outreach') . '</th><th class="vms-pass-table-cell--center">' . esc_html__('Actions', 'backstage-outreach') . '</th></tr></thead><tbody>';
		if (empty($suppressions)) {
			echo '<tr><td colspan="6">' . esc_html__('No suppression records matched this view.', 'backstage-outreach') . '</td></tr>';
		} else {
			foreach ($suppressions as $row) {
				echo '<tr>';
				echo '<td><strong>' . esc_html((string) ($row['email'] ?? '')) . '</strong></td>';
				echo '<td>' . esc_html((string) ($reason_options[(string) ($row['reason'] ?? '')] ?? (string) ($row['reason'] ?? ''))) . '</td>';
				$linked_contact = absint($row['contact_id'] ?? 0) > 0
					? '<a href="' . esc_url(vms_outreach_contacts_form_url(array('contact_id' => (int) ($row['contact_id'] ?? 0)))) . '">' . esc_html(vms_outreach_contact_display_name($row)) . '</a>'
					: esc_html__('None', 'backstage-outreach');
				echo '<td>' . $linked_contact . '</td>';
				echo '<td>' . esc_html((string) ($row['source_label'] ?? '')) . '</td>';
				$suppressed_at = (string) ($row['suppressed_at'] ?? '');
				echo '<td>' . esc_html(function_exists('vms_pass_outreach_format_admin_datetime') ? vms_pass_outreach_format_admin_datetime($suppressed_at) : $suppressed_at) . '</td>';
				echo '<td class="vms-pass-campaign-actions"><a class="button button-small" href="' . esc_url(vms_outreach_suppression_form_url(array('suppression_id' => (int) ($row['id'] ?? 0)))) . '">' . esc_html__('Edit', 'backstage-outreach') . '</a> ';
				echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-outreach-inline-form">';
				echo '<input type="hidden" name="action" value="vms_outreach_suppression_remove">';
				echo '<input type="hidden" name="suppression_id" value="' . esc_attr((string) ($row['id'] ?? 0)) . '">';
				vms_outreach_nonce_field(
					'vms_outreach_suppression_remove',
					'vms-outreach-suppression-remove-' . absint($row['id'] ?? 0) . '-nonce'
				);
				echo '<button type="submit" class="button button-small" onclick="return window.confirm(' . esc_attr(wp_json_encode(__('Remove this suppression record?', 'backstage-outreach'))) . ');">' . esc_html__('Unsuppress', 'backstage-outreach') . '</button>';
				echo '</form></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '</div>';
		echo '</section>';
	}
}

if (!function_exists('vms_outreach_handle_contact_save')) {
	function vms_outreach_handle_contact_save(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-outreach'));
		}

		check_admin_referer('vms_outreach_contact_save');

		$user_id = get_current_user_id();
		$contact_id = absint($_POST['contact_id'] ?? 0);
		$raw = isset($_POST) ? (array) wp_unslash($_POST) : array();
		$result = vms_outreach_save_contact($raw, $user_id, $contact_id);
		if (is_wp_error($result)) {
			$field_errors = array();
			foreach (vms_outreach_contact_validation_error_fields($result->get_error_code()) as $field) {
				$field_errors[$field] = $result->get_error_message();
			}
			vms_outreach_set_contact_form_flash($user_id, array(
				'contact_id' => $contact_id,
				'payload' => $raw,
				'field_errors' => $field_errors,
			));
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', $result->get_error_message());
			}
			wp_safe_redirect(vms_outreach_contacts_form_url(array('contact_id' => $contact_id)));
			exit;
		}

		if (function_exists('vms_pass_claims_set_user_message')) {
			vms_pass_claims_set_user_message('success', $contact_id > 0 ? __('Contact updated.', 'backstage-outreach') : __('Contact created.', 'backstage-outreach'));
		}
		wp_safe_redirect(vms_outreach_contacts_form_url(array('contact_id' => (int) ($result['id'] ?? 0))));
		exit;
	}
}
add_action('admin_post_vms_outreach_contact_save', 'vms_outreach_handle_contact_save');

if (!function_exists('vms_outreach_handle_contact_delete')) {
	function vms_outreach_handle_contact_delete(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-outreach'));
		}
		check_admin_referer('vms_outreach_contact_delete');

		$contact_id = absint($_POST['contact_id'] ?? 0);
		$deleted = vms_outreach_delete_contact($contact_id);
		if (function_exists('vms_pass_claims_set_user_message')) {
			vms_pass_claims_set_user_message($deleted ? 'success' : 'error', $deleted ? __('Contact deleted. Suppression records were left intact.', 'backstage-outreach') : __('Could not delete the contact.', 'backstage-outreach'));
		}
		wp_safe_redirect(vms_outreach_contacts_admin_url());
		exit;
	}
}
add_action('admin_post_vms_outreach_contact_delete', 'vms_outreach_handle_contact_delete');

if (!function_exists('vms_outreach_handle_contact_import')) {
	function vms_outreach_handle_contact_import(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-outreach'));
		}
		check_admin_referer('vms_outreach_contact_import');

		$user_id = get_current_user_id();
		$save_mode = sanitize_key((string) ($_POST['save_mode'] ?? 'map'));
		$file = isset($_FILES['contact_csv']) && is_array($_FILES['contact_csv']) ? $_FILES['contact_csv'] : array();
		$has_uploaded_csv = isset($file['error']) && absint($file['error']) !== UPLOAD_ERR_NO_FILE;

		if ($save_mode === 'clear') {
			vms_outreach_clear_contact_import_mapping($user_id);
			vms_outreach_clear_contact_import_preview($user_id);
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('success', __('Saved contact import preview discarded.', 'backstage-outreach'));
			}
			wp_safe_redirect(vms_outreach_contacts_import_url());
			exit;
		}

		if ($save_mode === 'map') {
			$parsed = vms_outreach_parse_uploaded_contact_csv_for_mapping($file);
			if (is_wp_error($parsed)) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $parsed->get_error_message());
				}
				wp_safe_redirect(vms_outreach_contacts_import_url());
				exit;
			}

			$parsed['selected_mapping'] = vms_outreach_normalize_selected_contact_csv_mapping((array) ($parsed['suggested_mapping'] ?? array()), (array) ($parsed['header_row'] ?? array()));
			vms_outreach_set_contact_import_mapping($user_id, $parsed);
			vms_outreach_clear_contact_import_preview($user_id);
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('success', __('CSV uploaded. Review the detected columns before previewing the import.', 'backstage-outreach'));
			}
			wp_safe_redirect(vms_outreach_contacts_import_url());
			exit;
		}

		if ($save_mode === 'preview') {
			$mapping_state = vms_outreach_get_contact_import_mapping($user_id);
			if ($has_uploaded_csv) {
				$parsed = vms_outreach_parse_uploaded_contact_csv_for_mapping($file);
				if (is_wp_error($parsed)) {
					if (function_exists('vms_pass_claims_set_user_message')) {
						vms_pass_claims_set_user_message('error', $parsed->get_error_message());
					}
					wp_safe_redirect(vms_outreach_contacts_import_url());
					exit;
				}
				$parsed['selected_mapping'] = vms_outreach_normalize_selected_contact_csv_mapping((array) ($parsed['suggested_mapping'] ?? array()), (array) ($parsed['header_row'] ?? array()));
				$mapping_state = $parsed;
				vms_outreach_set_contact_import_mapping($user_id, $mapping_state);
				vms_outreach_clear_contact_import_preview($user_id);
			}

			if (empty($mapping_state)) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Upload a CSV before previewing the import.', 'backstage-outreach'));
				}
				wp_safe_redirect(vms_outreach_contacts_import_url());
				exit;
			}

			$selected_mapping = isset($_POST['csv_mapping']) && is_array($_POST['csv_mapping'])
				? (array) wp_unslash($_POST['csv_mapping'])
				: (array) ($mapping_state['selected_mapping'] ?? array());
			$normalized_mapping = vms_outreach_normalize_selected_contact_csv_mapping($selected_mapping, (array) ($mapping_state['header_row'] ?? array()));
			$mapping_state['selected_mapping'] = $normalized_mapping;
			vms_outreach_set_contact_import_mapping($user_id, $mapping_state);

			$preview = vms_outreach_preview_contact_import_from_parsed_csv($mapping_state, $normalized_mapping);
			if (is_wp_error($preview)) {
				vms_outreach_clear_contact_import_preview($user_id);
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $preview->get_error_message());
				}
				wp_safe_redirect(vms_outreach_contacts_import_url());
				exit;
			}

			vms_outreach_set_contact_import_preview($user_id, $preview);
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('success', __('Import preview ready. Review counts and suppressed emails before committing.', 'backstage-outreach'));
			}
			wp_safe_redirect(vms_outreach_contacts_import_url());
			exit;
		}

		if ($save_mode === 'commit') {
			$mapping_state = vms_outreach_get_contact_import_mapping($user_id);
			$preview_state = vms_outreach_get_contact_import_preview($user_id);
			if (empty($mapping_state) || empty($preview_state)) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Import preview expired or is missing. Preview the import again before committing.', 'backstage-outreach'));
				}
				wp_safe_redirect(vms_outreach_contacts_import_url());
				exit;
			}

			$posted_mapping = vms_outreach_normalize_selected_contact_csv_mapping((array) ($_POST['csv_mapping'] ?? array()), (array) ($mapping_state['header_row'] ?? array()));
			$preview_mapping = vms_outreach_normalize_selected_contact_csv_mapping((array) ($preview_state['selected_mapping'] ?? array()), (array) ($mapping_state['header_row'] ?? array()));
			if ($posted_mapping !== $preview_mapping) {
				$mapping_state['selected_mapping'] = $posted_mapping;
				vms_outreach_set_contact_import_mapping($user_id, $mapping_state);
				vms_outreach_clear_contact_import_preview($user_id);
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', __('Column mapping changed after the last preview. Preview again before committing the import.', 'backstage-outreach'));
				}
				wp_safe_redirect(vms_outreach_contacts_import_url());
				exit;
			}

			$result = vms_outreach_commit_contact_import_preview($preview_state, $user_id);
			if (is_wp_error($result)) {
				if (function_exists('vms_pass_claims_set_user_message')) {
					vms_pass_claims_set_user_message('error', $result->get_error_message());
				}
				wp_safe_redirect(vms_outreach_contacts_import_url());
				exit;
			}

			vms_outreach_clear_contact_import_mapping($user_id);
			vms_outreach_clear_contact_import_preview($user_id);
			if (function_exists('vms_pass_claims_set_user_message')) {
				$message = sprintf(
					__('Import complete. %1$d new contacts, %2$d existing contacts updated, %3$d duplicate rows merged, %4$d suppressed contacts detected, %5$d invalid emails skipped.', 'backstage-outreach'),
					absint($result['new_count'] ?? 0),
					absint($result['update_count'] ?? 0),
					absint($result['duplicate_merge_count'] ?? 0),
					absint($result['suppressed_count'] ?? 0),
					absint($result['invalid_email_count'] ?? 0)
				);
				vms_pass_claims_set_user_message('success', $message);
			}
			wp_safe_redirect(vms_outreach_contacts_admin_url());
			exit;
		}

		wp_safe_redirect(vms_outreach_contacts_import_url());
		exit;
	}
}
add_action('admin_post_vms_outreach_contact_import', 'vms_outreach_handle_contact_import');

if (!function_exists('vms_outreach_handle_suppression_save')) {
	function vms_outreach_handle_suppression_save(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-outreach'));
		}
		check_admin_referer('vms_outreach_suppression_save');

		$user_id = get_current_user_id();
		$suppression_id = absint($_POST['suppression_id'] ?? 0);
		$raw = isset($_POST) ? (array) wp_unslash($_POST) : array();
		$result = vms_outreach_upsert_suppression($raw, $user_id, $suppression_id);
		if (is_wp_error($result)) {
			$field_errors = array();
			foreach (vms_outreach_suppression_validation_error_fields($result->get_error_code()) as $field) {
				$field_errors[$field] = $result->get_error_message();
			}
			vms_outreach_set_suppression_form_flash($user_id, array(
				'suppression_id' => $suppression_id,
				'payload' => $raw,
				'field_errors' => $field_errors,
			));
			if (function_exists('vms_pass_claims_set_user_message')) {
				vms_pass_claims_set_user_message('error', $result->get_error_message());
			}
			wp_safe_redirect(vms_outreach_suppression_form_url(array('suppression_id' => $suppression_id)));
			exit;
		}

		if (function_exists('vms_pass_claims_set_user_message')) {
			vms_pass_claims_set_user_message('success', $suppression_id > 0 ? __('Suppression updated.', 'backstage-outreach') : __('Suppression added.', 'backstage-outreach'));
		}
		wp_safe_redirect(vms_outreach_suppression_form_url(array('suppression_id' => (int) ($result['id'] ?? 0))));
		exit;
	}
}
add_action('admin_post_vms_outreach_suppression_save', 'vms_outreach_handle_suppression_save');

if (!function_exists('vms_outreach_handle_suppression_remove')) {
	function vms_outreach_handle_suppression_remove(): void
	{
		if (!current_user_can(function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-outreach'));
		}
		check_admin_referer('vms_outreach_suppression_remove');

		$suppression_id = absint($_POST['suppression_id'] ?? 0);
		$removed = vms_outreach_remove_suppression($suppression_id);
		if (function_exists('vms_pass_claims_set_user_message')) {
			vms_pass_claims_set_user_message($removed ? 'success' : 'error', $removed ? __('Suppression removed.', 'backstage-outreach') : __('Could not remove the suppression record.', 'backstage-outreach'));
		}
		wp_safe_redirect(vms_outreach_suppression_admin_url());
		exit;
	}
}
add_action('admin_post_vms_outreach_suppression_remove', 'vms_outreach_handle_suppression_remove');
