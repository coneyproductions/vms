<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_email_followups_admin_slug')) {
	function vms_email_followups_admin_slug(): string
	{
		return 'vms-email-followups';
	}
}

if (!function_exists('vms_email_followups_admin_url')) {
	function vms_email_followups_admin_url(array $args = array()): string
	{
		$url = admin_url('admin.php?page=' . vms_email_followups_admin_slug());
		return empty($args) ? $url : add_query_arg($args, $url);
	}
}

if (!function_exists('vms_email_followups_register_menu')) {
	function vms_email_followups_register_menu(): void
	{
		add_submenu_page(
			'vms-dashboard',
			__('Email Follow-Ups', 'backstage-venue-manager'),
			__('Email Follow-Ups', 'backstage-venue-manager'),
			'manage_options',
			vms_email_followups_admin_slug(),
			'vms_email_followups_render_admin_page'
		);
	}
}
add_action('admin_menu', 'vms_email_followups_register_menu', 45);

if (!function_exists('vms_email_followups_register_shell_page')) {
	function vms_email_followups_register_shell_page(array $pages): array
	{
		$pages[] = vms_email_followups_admin_slug();
		return array_values(array_unique($pages));
	}
}
add_filter('vms_admin_ui_shell_pages', 'vms_email_followups_register_shell_page');

if (!function_exists('vms_email_followups_enqueue_admin_assets')) {
	function vms_email_followups_enqueue_admin_assets(): void
	{
		$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
		if ($page !== vms_email_followups_admin_slug()) {
			return;
		}
		wp_enqueue_style(
			'vms-email-followups-admin',
			VMS_PLUGIN_URL . 'assets/css/vms-email-followups-admin.css',
			array('vms-admin-ui'),
			defined('VMS_VERSION') ? (string) VMS_VERSION : null
		);
		wp_enqueue_script(
			'vms-email-followups-admin',
			VMS_PLUGIN_URL . 'assets/js/vms-email-followups-admin.js',
			array(),
			defined('VMS_VERSION') ? (string) VMS_VERSION : null,
			true
		);
	}
}
add_action('admin_enqueue_scripts', 'vms_email_followups_enqueue_admin_assets', 35);

if (!function_exists('vms_email_followups_current_tab')) {
	function vms_email_followups_current_tab(): string
	{
		$tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'overview';
		$tabs = array('overview', 'templates', 'preview', 'logs');
		return in_array($tab, $tabs, true) ? $tab : 'overview';
	}
}

if (!function_exists('vms_email_followups_redirect_notice')) {
	function vms_email_followups_redirect_notice(string $tab, string $notice, string $type = 'success', array $args = array()): void
	{
		$args = array_merge($args, array(
			'tab' => sanitize_key($tab),
			'vms_efu_notice' => rawurlencode($notice),
			'vms_efu_notice_type' => sanitize_key($type),
		));
		wp_safe_redirect(vms_email_followups_admin_url($args));
		exit;
	}
}

if (!function_exists('vms_email_followups_render_notices')) {
	function vms_email_followups_render_notices(): void
	{
		$notice = isset($_GET['vms_efu_notice']) ? sanitize_text_field(wp_unslash((string) $_GET['vms_efu_notice'])) : '';
		if ($notice === '') {
			return;
		}
		$type = isset($_GET['vms_efu_notice_type']) ? sanitize_key((string) $_GET['vms_efu_notice_type']) : 'success';
		if (!in_array($type, array('success', 'error', 'warning', 'info'), true)) {
			$type = 'success';
		}
		echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($notice) . '</p></div>';
	}
}

if (!function_exists('vms_email_followups_render_tabs')) {
	function vms_email_followups_render_tabs(string $active): void
	{
		$tabs = array(
			'overview' => __('Overview', 'backstage-venue-manager'),
			'templates' => __('Templates', 'backstage-venue-manager'),
			'preview' => __('Preview & Test', 'backstage-venue-manager'),
			'logs' => __('Logs', 'backstage-venue-manager'),
		);
		echo '<nav class="nav-tab-wrapper vms-email-followups-tabs" data-vms-tour="email-followups.tabs">';
		foreach ($tabs as $tab => $label) {
			$class = $active === $tab ? ' nav-tab-active' : '';
			echo '<a class="nav-tab' . esc_attr($class) . '" href="' . esc_url(vms_email_followups_admin_url(array('tab' => $tab))) . '">' . esc_html($label) . '</a>';
		}
		echo '</nav>';
	}
}

if (!function_exists('vms_email_followups_render_admin_page')) {
	function vms_email_followups_render_admin_page(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
		}
		$tab = vms_email_followups_current_tab();
		$preview_state = array();
		if ($tab === 'preview') {
			$preview_state = vms_email_followups_resolve_preview_state();
		}
		$render_notices = static function () use ($tab, $preview_state): void {
			vms_email_followups_render_page_notices($tab, $preview_state);
		};
		$render = static function () use ($tab, $preview_state): void {
			vms_email_followups_render_tabs($tab);
			if ($tab === 'templates') {
				vms_email_followups_render_templates_tab();
			} elseif ($tab === 'preview') {
				vms_email_followups_render_preview_tab($preview_state);
			} elseif ($tab === 'logs') {
				vms_email_followups_render_logs_tab();
			} else {
				vms_email_followups_render_overview_tab();
			}
		};

		if (function_exists('vms_admin_ui_render_shell')) {
			vms_admin_ui_render_shell(array(
				'title' => __('Email Follow-Ups', 'backstage-venue-manager'),
				'subtitle' => __('Event-aware buyer reminders, day-of updates, and post-show follow-ups.', 'backstage-venue-manager'),
				'shell_id' => 'vms-email-followups-admin',
				'notices_callback' => $render_notices,
			), $render);
			return;
		}
		echo '<div class="wrap" id="vms-email-followups-admin"><h1>' . esc_html__('Email Follow-Ups', 'backstage-venue-manager') . '</h1>';
		$render_notices();
		$render();
		echo '</div>';
	}
}

if (!function_exists('vms_email_followups_render_overview_tab')) {
	function vms_email_followups_render_overview_tab(): void
	{
		$settings = vms_email_followups_settings();
		$mailpoet = vms_email_followups_mailpoet_status();
		$due = vms_email_followups_due_items();
		echo '<section class="vms-efu-grid" data-vms-tour="email-followups.overview">';
		echo '<article class="vms-efu-card"><h2>' . esc_html__('Status', 'backstage-venue-manager') . '</h2>';
		echo '<p><strong>' . esc_html__('Module:', 'backstage-venue-manager') . '</strong> ' . esc_html(!empty($settings['enabled']) ? __('Enabled', 'backstage-venue-manager') : __('Disabled', 'backstage-venue-manager')) . '</p>';
		echo '<p><strong>' . esc_html__('Automatic scheduled sends:', 'backstage-venue-manager') . '</strong> ' . esc_html(!empty($settings['auto_send_enabled']) ? __('On', 'backstage-venue-manager') : __('Off — safest first-pass default', 'backstage-venue-manager')) . '</p>';
		echo '<p><strong>' . esc_html__('Due now:', 'backstage-venue-manager') . '</strong> ' . esc_html((string) count($due)) . '</p>';
		echo '<p class="description">' . esc_html__('Template timing now lives on each template so it is clear what sends before, day-of, after, or manually.', 'backstage-venue-manager') . '</p>';
		echo '</article>';

		echo '<article class="vms-efu-card"><h2>' . esc_html__('MailPoet', 'backstage-venue-manager') . '</h2>';
		echo '<p>' . esc_html((string) ($mailpoet['message'] ?? '')) . '</p>';
		if (($mailpoet['setup_complete'] ?? null) !== null) {
			echo '<p><strong>' . esc_html__('Setup complete:', 'backstage-venue-manager') . '</strong> ' . esc_html(!empty($mailpoet['setup_complete']) ? __('Yes', 'backstage-venue-manager') : __('No', 'backstage-venue-manager')) . '</p>';
		}
		if (!empty($settings['mailpoet_list_id'])) {
			echo '<p><strong>' . esc_html__('Configured list ID:', 'backstage-venue-manager') . '</strong> ' . esc_html((string) $settings['mailpoet_list_id']) . '</p>';
		}
		echo '<p class="description">' . esc_html__('VMS sends through WordPress email. If MailPoet is configured to send site emails, MailPoet handles delivery. Optional subscriber/list sync can also be enabled below.', 'backstage-venue-manager') . '</p>';
		echo '</article>';
		echo '</section>';

		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-efu-settings-form" data-vms-tour="email-followups.settings">';
		wp_nonce_field('vms_email_followups_save_settings');
		echo '<input type="hidden" name="action" value="vms_email_followups_save_settings" />';
		echo '<input type="hidden" name="tab" value="overview" />';
		echo '<h2>' . esc_html__('Global Settings', 'backstage-venue-manager') . '</h2>';
		echo '<p class="description">' . esc_html__('These are the shared safety, delivery, and signature settings. The “when should this send?” settings are on each template.', 'backstage-venue-manager') . '</p>';
		echo '<div class="vms-efu-form-grid">';
		vms_email_followups_checkbox_field('vms_email_followups[enabled]', __('Enable Email Follow-Ups module', 'backstage-venue-manager'), !empty($settings['enabled']));
		vms_email_followups_checkbox_field('vms_email_followups[auto_send_enabled]', __('Enable automatic scheduled sends', 'backstage-venue-manager'), !empty($settings['auto_send_enabled']), __('Leave off until staging tests prove recipient discovery and templates are correct.', 'backstage-venue-manager'));
		vms_email_followups_checkbox_field('vms_email_followups[mailpoet_sync_enabled]', __('Sync recipients to MailPoet list/tags before sending', 'backstage-venue-manager'), !empty($settings['mailpoet_sync_enabled']));
		echo '<label><span>' . esc_html__('MailPoet list ID', 'backstage-venue-manager') . '</span><input type="text" name="vms_email_followups[mailpoet_list_id]" value="' . esc_attr((string) $settings['mailpoet_list_id']) . '" /></label>';
		echo '<label><span>' . esc_html__('From name', 'backstage-venue-manager') . '</span><input type="text" name="vms_email_followups[from_name]" value="' . esc_attr((string) $settings['from_name']) . '" /></label>';
		echo '<label><span>' . esc_html__('From email', 'backstage-venue-manager') . '</span><input type="email" name="vms_email_followups[from_email]" value="' . esc_attr((string) $settings['from_email']) . '" /></label>';
		echo '<label><span>' . esc_html__('Reply-to email', 'backstage-venue-manager') . '</span><input type="email" name="vms_email_followups[reply_to_email]" value="' . esc_attr((string) $settings['reply_to_email']) . '" /></label>';
		echo '<label><span>' . esc_html__('Default test recipient', 'backstage-venue-manager') . '</span><input type="email" name="vms_email_followups[test_recipient]" value="' . esc_attr((string) $settings['test_recipient']) . '" /></label>';
		echo '<label><span>' . esc_html__('Due-window hours', 'backstage-venue-manager') . '</span><input type="number" min="1" max="72" name="vms_email_followups[reminder_window_hours]" value="' . esc_attr((string) $settings['reminder_window_hours']) . '" /></label>';
		echo '</div>';
		echo '<label class="vms-efu-signature-field"><span>' . esc_html__('Default signature', 'backstage-venue-manager') . '</span><textarea name="vms_email_followups[signature]" rows="4">' . esc_textarea((string) ($settings['signature'] ?? '')) . '</textarea><em>' . esc_html__('Use {signature} in any template. Leave this blank if you do not want a signature.', 'backstage-venue-manager') . '</em></label>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__('Save Settings', 'backstage-venue-manager') . '</button></p>';
		echo '</form>';
	}
}

if (!function_exists('vms_email_followups_checkbox_field')) {
	function vms_email_followups_checkbox_field(string $name, string $label, bool $checked, string $description = ''): void
	{
		echo '<label class="vms-efu-check"><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked(true, $checked, false) . ' /> <span>' . esc_html($label) . '</span>';
		if ($description !== '') {
			echo '<em>' . esc_html($description) . '</em>';
		}
		echo '</label>';
	}
}

if (!function_exists('vms_email_followups_render_template_schedule_fields')) {
	function vms_email_followups_render_template_schedule_fields(string $key, array $def, string $base_name): void
	{
		$mode = function_exists('vms_email_followups_template_schedule_mode') ? vms_email_followups_template_schedule_mode($def) : 'manual';
		$days = abs((int) ($def['offset_days'] ?? 0));
		$hour = min(23, max(0, (int) ($def['send_hour'] ?? 9)));
		echo '<div class="vms-efu-schedule-grid">';
		echo '<label><span>' . esc_html__('Send timing', 'backstage-venue-manager') . '</span><select name="' . esc_attr($base_name . '[schedule_mode]') . '">';
		$options = array(
			'manual' => __('Manual only', 'backstage-venue-manager'),
			'before' => __('Before event', 'backstage-venue-manager'),
			'day_of' => __('Day of event', 'backstage-venue-manager'),
			'after' => __('After event', 'backstage-venue-manager'),
		);
		foreach ($options as $value => $label) {
			echo '<option value="' . esc_attr($value) . '" ' . selected($mode, $value, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select></label>';
		echo '<label><span>' . esc_html__('Days', 'backstage-venue-manager') . '</span><input type="number" min="0" max="60" name="' . esc_attr($base_name . '[schedule_days]') . '" value="' . esc_attr((string) $days) . '" /><em>' . esc_html__('Ignored for manual/day-of templates.', 'backstage-venue-manager') . '</em></label>';
		echo '<label><span>' . esc_html__('Send hour', 'backstage-venue-manager') . '</span><input type="number" min="0" max="23" name="' . esc_attr($base_name . '[send_hour]') . '" value="' . esc_attr((string) $hour) . '" /><em>' . esc_html__('24-hour clock; 9 means 9:00 AM.', 'backstage-venue-manager') . '</em></label>';
		echo '</div>';
		if (function_exists('vms_email_followups_template_timing_label')) {
			echo '<p class="description"><strong>' . esc_html__('Current timing:', 'backstage-venue-manager') . '</strong> ' . esc_html(vms_email_followups_template_timing_label($def)) . '</p>';
		}
	}
}

if (!function_exists('vms_email_followups_render_templates_tab')) {
	function vms_email_followups_render_templates_tab(): void
	{
		$settings = vms_email_followups_settings();
		$templates = (array) ($settings['templates'] ?? array());
		$enabled = (array) ($settings['templates_enabled'] ?? array());
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" data-vms-tour="email-followups.templates">';
		wp_nonce_field('vms_email_followups_save_settings');
		echo '<input type="hidden" name="action" value="vms_email_followups_save_settings" />';
		echo '<input type="hidden" name="tab" value="templates" />';
		echo '<div class="vms-efu-token-box"><h2>' . esc_html__('Available Tokens', 'backstage-venue-manager') . '</h2><p class="description">' . esc_html__('Use {customer_greeting} and {signature} for the safest human-feeling opening and closing.', 'backstage-venue-manager') . '</p><div class="vms-efu-token-list">';
		foreach (vms_email_followups_tokens_help() as $token => $desc) {
			echo '<code>' . esc_html($token) . '</code><span>' . esc_html($desc) . '</span>';
		}
		echo '</div></div>';

		foreach (vms_email_followups_template_definitions() as $key => $def) {
			$template = is_array($templates[$key] ?? null) ? (array) $templates[$key] : array();
			$is_custom = !empty($def['custom']);
			echo '<section class="vms-efu-template-card">';
			echo '<div class="vms-efu-template-heading"><div>';
			echo '<h2>' . esc_html((string) ($def['label'] ?? $key)) . '</h2>';
			echo '<p class="description">' . esc_html((string) ($def['description'] ?? '')) . '</p>';
			echo '</div>';
			vms_email_followups_checkbox_field('vms_email_followups[templates_enabled][' . $key . ']', __('Enable this template', 'backstage-venue-manager'), !empty($enabled[$key]));
			echo '</div>';
			if ($is_custom) {
				echo '<div class="vms-efu-form-grid">';
				echo '<label><span>' . esc_html__('Template name', 'backstage-venue-manager') . '</span><input type="text" name="vms_email_followups[template_meta][' . esc_attr($key) . '][label]" value="' . esc_attr((string) ($def['label'] ?? '')) . '" /></label>';
				echo '<label><span>' . esc_html__('Description', 'backstage-venue-manager') . '</span><input type="text" name="vms_email_followups[template_meta][' . esc_attr($key) . '][description]" value="' . esc_attr((string) ($def['description'] ?? '')) . '" /></label>';
				echo '</div>';
			}
			vms_email_followups_render_template_schedule_fields($key, (array) $def, 'vms_email_followups[template_meta][' . $key . ']');
			echo '<label><span>' . esc_html__('Subject', 'backstage-venue-manager') . '</span><input type="text" name="vms_email_followups[templates][' . esc_attr($key) . '][subject]" value="' . esc_attr((string) ($template['subject'] ?? '')) . '" /></label>';
			echo '<label><span>' . esc_html__('Body', 'backstage-venue-manager') . '</span><textarea name="vms_email_followups[templates][' . esc_attr($key) . '][body]" rows="10">' . esc_textarea((string) ($template['body'] ?? '')) . '</textarea></label>';
			echo '<p class="vms-efu-template-save"><button type="submit" class="button button-primary">' . esc_html__('Save Template Changes', 'backstage-venue-manager') . '</button> <span class="description">' . esc_html__('Saves all template changes on this page.', 'backstage-venue-manager') . '</span></p>';
			if ($is_custom) {
				echo '<label class="vms-efu-check vms-efu-delete-template"><input type="checkbox" name="vms_email_followups[delete_custom_templates][' . esc_attr($key) . ']" value="1" /> <span>' . esc_html__('Delete this custom template when saving', 'backstage-venue-manager') . '</span></label>';
			}
			echo '</section>';
		}

		echo '<section class="vms-efu-template-card vms-efu-new-template">';
		echo '<h2>' . esc_html__('Add Template', 'backstage-venue-manager') . '</h2>';
		echo '<p class="description">' . esc_html__('Create another reusable follow-up, such as a food-truck apology note, rain update, sponsor thank-you, VIP reminder, or special post-show offer.', 'backstage-venue-manager') . '</p>';
		echo '<div class="vms-efu-form-grid">';
		echo '<label><span>' . esc_html__('Template name', 'backstage-venue-manager') . '</span><input type="text" name="vms_email_followups[new_template][label]" value="" /></label>';
		echo '<label><span>' . esc_html__('Description', 'backstage-venue-manager') . '</span><input type="text" name="vms_email_followups[new_template][description]" value="" /></label>';
		echo '</div>';
		vms_email_followups_render_template_schedule_fields('new_template', array('kind' => 'manual', 'offset_days' => 0, 'send_hour' => 9), 'vms_email_followups[new_template]');
		echo '<label><span>' . esc_html__('Subject', 'backstage-venue-manager') . '</span><input type="text" name="vms_email_followups[new_template][subject]" value="" /></label>';
		echo '<label><span>' . esc_html__('Body', 'backstage-venue-manager') . '</span><textarea name="vms_email_followups[new_template][body]" rows="8">' . esc_textarea("{customer_greeting}\n\n\n\n{signature}") . '</textarea></label>';
		echo '<p class="vms-efu-template-save"><button type="submit" name="vms_email_followups[create_new_template]" value="1" class="button button-primary">' . esc_html__('Save New Template', 'backstage-venue-manager') . '</button> <span class="description">' . esc_html__('Creates the template and saves all template changes on this page.', 'backstage-venue-manager') . '</span></p>';
		echo '</section>';

		echo '<p><button type="submit" class="button button-primary">' . esc_html__('Save Templates', 'backstage-venue-manager') . '</button></p>';
		echo '</form>';
	}
}

if (!function_exists('vms_email_followups_selected_plan_id')) {
	/**
	 * @param array<int,WP_Post>|null $event_choices
	 */
	function vms_email_followups_selected_plan_id(?array $event_choices = null): int
	{
		$raw = isset($_GET['event_plan_id']) ? absint($_GET['event_plan_id']) : 0;
		if ($raw > 0) {
			return $raw;
		}
		if ($event_choices === null) {
			$event_choices = function_exists('vms_email_followups_event_choices') ? vms_email_followups_event_choices(1) : vms_email_followups_upcoming_event_choices(1);
		}
		return !empty($event_choices[0]) && $event_choices[0] instanceof WP_Post ? (int) $event_choices[0]->ID : 0;
	}
}

if (!function_exists('vms_email_followups_resolve_preview_state')) {
	/**
	 * @return array{event_plan_id:int,email_key:string,event_choices:array<int,WP_Post>,template_definitions:array<string,mixed>}
	 */
	function vms_email_followups_resolve_preview_state(): array
	{
		$selected_event_plan_id = isset($_GET['event_plan_id']) ? absint($_GET['event_plan_id']) : 0;
		$event_choices = function_exists('vms_email_followups_event_choices')
			? vms_email_followups_event_choices(120, $selected_event_plan_id)
			: vms_email_followups_upcoming_event_choices(80);
		$event_plan_id = vms_email_followups_selected_plan_id($event_choices);
		$email_key = isset($_GET['email_key']) ? sanitize_key((string) $_GET['email_key']) : 'know_before';
		$template_definitions = vms_email_followups_template_definitions();
		if (!isset($template_definitions[$email_key])) {
			$email_key = 'know_before';
		}

		return array(
			'event_plan_id' => $event_plan_id,
			'email_key' => $email_key,
			'event_choices' => $event_choices,
			'template_definitions' => $template_definitions,
		);
	}
}

if (!function_exists('vms_email_followups_render_preview_empty_state_notice')) {
	/**
	 * @param array<string,mixed> $preview_state
	 */
	function vms_email_followups_render_preview_empty_state_notice(array $preview_state): void
	{
		$event_plan_id = isset($preview_state['event_plan_id']) ? absint($preview_state['event_plan_id']) : 0;
		if ($event_plan_id > 0) {
			return;
		}

		echo '<div class="notice notice-warning inline"><p>' . esc_html__('No Event Plans found for preview/testing.', 'backstage-venue-manager') . '</p></div>';
	}
}

if (!function_exists('vms_email_followups_render_page_notices')) {
	/**
	 * @param array<string,mixed> $preview_state
	 */
	function vms_email_followups_render_page_notices(string $tab, array $preview_state = array()): void
	{
		vms_email_followups_render_notices();
		if ($tab !== 'preview') {
			return;
		}

		vms_email_followups_render_preview_empty_state_notice($preview_state);
	}
}

if (!function_exists('vms_email_followups_render_preview_tab')) {
	/**
	 * @param array<string,mixed> $preview_state
	 */
	function vms_email_followups_render_preview_tab(array $preview_state = array()): void
	{
		if (empty($preview_state)) {
			$preview_state = vms_email_followups_resolve_preview_state();
		}

		$event_plan_id = isset($preview_state['event_plan_id']) ? absint($preview_state['event_plan_id']) : 0;
		$email_key = isset($preview_state['email_key']) ? sanitize_key((string) $preview_state['email_key']) : 'know_before';
		$event_choices = isset($preview_state['event_choices']) && is_array($preview_state['event_choices'])
			? $preview_state['event_choices']
			: (function_exists('vms_email_followups_event_choices') ? vms_email_followups_event_choices(120, $event_plan_id) : vms_email_followups_upcoming_event_choices(80));
		$template_definitions = isset($preview_state['template_definitions']) && is_array($preview_state['template_definitions'])
			? $preview_state['template_definitions']
			: vms_email_followups_template_definitions();
		if (!isset($template_definitions[$email_key])) {
			$email_key = 'know_before';
		}
		$settings = vms_email_followups_settings();

		echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" class="vms-efu-filter-form" data-vms-tour="email-followups.preview-controls">';
		echo '<input type="hidden" name="page" value="' . esc_attr(vms_email_followups_admin_slug()) . '" />';
		echo '<input type="hidden" name="tab" value="preview" />';
		echo '<label><span>' . esc_html__('Event', 'backstage-venue-manager') . '</span><select name="event_plan_id">';
		foreach ($event_choices as $plan) {
			if (!$plan instanceof WP_Post) {
				continue;
			}
			$label = function_exists('vms_email_followups_event_choice_label') ? vms_email_followups_event_choice_label($plan) : ((string) get_post_meta($plan->ID, '_vms_event_date', true) . ' — ' . get_the_title($plan));
			echo '<option value="' . esc_attr((string) $plan->ID) . '" ' . selected($event_plan_id, (int) $plan->ID, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select></label>';
		echo '<label><span>' . esc_html__('Template', 'backstage-venue-manager') . '</span><select name="email_key">';
		foreach ($template_definitions as $key => $def) {
			echo '<option value="' . esc_attr($key) . '" ' . selected($email_key, $key, false) . '>' . esc_html((string) ($def['label'] ?? $key)) . '</option>';
		}
		echo '</select></label>';
		echo '<button type="submit" class="button">' . esc_html__('Preview', 'backstage-venue-manager') . '</button>';
		echo '</form>';

		if ($event_plan_id <= 0) {
			return;
		}

		$context = vms_email_followups_event_context($event_plan_id);
		$recipient_result = vms_email_followups_event_recipients($event_plan_id);
		$recipients = (array) ($recipient_result['recipients'] ?? array());
		$rendered = vms_email_followups_render_message($email_key, $event_plan_id, !empty($recipients[0]) ? (array) $recipients[0] : array('name' => 'Preview Recipient'));
		$scheduled_ts = vms_email_followups_scheduled_timestamp($event_plan_id, $email_key);
		list($allowed, $reason) = vms_email_followups_context_allows_send($context);

		echo '<section class="vms-efu-grid">';
		echo '<article class="vms-efu-card"><h2>' . esc_html__('Recipient Preview', 'backstage-venue-manager') . '</h2>';
		echo '<p><strong>' . esc_html__('Eligible recipients:', 'backstage-venue-manager') . '</strong> ' . esc_html((string) count($recipients)) . '</p>';
		echo '<p><strong>' . esc_html__('Net tickets represented:', 'backstage-venue-manager') . '</strong> ' . esc_html((string) (($recipient_result['counts']['tickets_net'] ?? 0))) . '</p>';
		echo '<p><strong>' . esc_html__('Send allowed:', 'backstage-venue-manager') . '</strong> ' . esc_html($allowed ? __('Yes', 'backstage-venue-manager') : __('No', 'backstage-venue-manager')) . '</p>';
		if ($email_key === 'post_event') {
			$feedback_url = (string) ($rendered['tokens']['{feedback_url}'] ?? '');
			echo '<p><strong>' . esc_html__('Feedback link:', 'backstage-venue-manager') . '</strong> ' . esc_html($feedback_url !== '' ? __('Included', 'backstage-venue-manager') : __('Unavailable', 'backstage-venue-manager')) . '</p>';
		}
		if (!$allowed) {
			echo '<p class="vms-efu-warning">' . esc_html($reason) . '</p>';
		}
		if ($scheduled_ts > 0) {
			echo '<p><strong>' . esc_html__('Scheduled timing:', 'backstage-venue-manager') . '</strong> ' . esc_html(wp_date('M j, Y g:ia', $scheduled_ts, wp_timezone())) . '</p>';
		}
		echo '</article>';
		echo '<article class="vms-efu-card"><h2>' . esc_html__('Rendered Email', 'backstage-venue-manager') . '</h2>';
		echo '<p><strong>' . esc_html__('Subject:', 'backstage-venue-manager') . '</strong> ' . esc_html((string) $rendered['subject']) . '</p>';
		echo '<div class="vms-efu-email-preview">' . wp_kses_post((string) $rendered['body_html']) . '</div>';
		echo '</article>';
		echo '</section>';

		$batch_token = isset($_GET['batch_token']) ? sanitize_key((string) $_GET['batch_token']) : '';
		$batch = $batch_token !== '' ? vms_email_followups_batch_get($batch_token) : array();
		if (!empty($batch) && (int) ($batch['event_plan_id'] ?? 0) === $event_plan_id && sanitize_key((string) ($batch['email_key'] ?? '')) === $email_key) {
			$remaining_count = count((array) ($batch['emails'] ?? array()));
			echo '<section class="vms-efu-batch-card">';
			echo '<h2>' . esc_html__('Manual send in progress', 'backstage-venue-manager') . '</h2>';
			/* translators: %d: number of items described in this message. */
			echo '<p>' . esc_html(sprintf(_n('%d selected recipient is waiting for the next send step.', '%d selected recipients are waiting for the next send step.', $remaining_count, 'backstage-venue-manager'), $remaining_count)) . '</p>';
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-efu-batch-continue-form">';
			wp_nonce_field('vms_email_followups_manual_send');
			echo '<input type="hidden" name="action" value="vms_email_followups_manual_send" />';
			echo '<input type="hidden" name="event_plan_id" value="' . esc_attr((string) $event_plan_id) . '" />';
			echo '<input type="hidden" name="email_key" value="' . esc_attr($email_key) . '" />';
			echo '<input type="hidden" name="batch_token" value="' . esc_attr($batch_token) . '" />';
			echo '<input type="hidden" name="confirm_send" value="1" />';
			echo '<p class="vms-efu-send-progress" aria-live="polite"></p>';
			echo '<button type="submit" class="button button-primary">' . esc_html__('Continue Sending Next Batch', 'backstage-venue-manager') . '</button>';
			echo '</form>';
			echo '</section>';
		}

		echo '<section class="vms-efu-actions" data-vms-tour="email-followups.test-send">';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('vms_email_followups_send_test');
		echo '<input type="hidden" name="action" value="vms_email_followups_send_test" />';
		echo '<input type="hidden" name="event_plan_id" value="' . esc_attr((string) $event_plan_id) . '" />';
		echo '<input type="hidden" name="email_key" value="' . esc_attr($email_key) . '" />';
		echo '<label><span>' . esc_html__('Send test to', 'backstage-venue-manager') . '</span><input type="email" name="test_recipient" value="' . esc_attr((string) $settings['test_recipient']) . '" /></label> ';
		echo '<button type="submit" class="button button-primary">' . esc_html__('Send Test Email', 'backstage-venue-manager') . '</button>';
		echo '</form>';

		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-efu-manual-send-form">';
		wp_nonce_field('vms_email_followups_manual_send');
		echo '<input type="hidden" name="action" value="vms_email_followups_manual_send" />';
		echo '<input type="hidden" name="event_plan_id" value="' . esc_attr((string) $event_plan_id) . '" />';
		echo '<input type="hidden" name="email_key" value="' . esc_attr($email_key) . '" />';
		echo '<input type="hidden" name="recipient_selection_present" value="1" />';
		if (!empty($recipients)) {
			/* translators: %d: number used in this message. */
			echo '<details class="vms-efu-recipient-list vms-efu-recipient-picker" open><summary>' . esc_html(sprintf(_n('Choose recipients (%d eligible; all selected by default)', 'Choose recipients (%d eligible; all selected by default)', count($recipients), 'backstage-venue-manager'), count($recipients))) . '</summary>';
			echo '<div class="vms-efu-recipient-tools"><button type="button" class="button" data-vms-efu-select="all">' . esc_html__('Select all', 'backstage-venue-manager') . '</button> <button type="button" class="button" data-vms-efu-select="none">' . esc_html__('Select none', 'backstage-venue-manager') . '</button> <span class="vms-efu-selected-count" aria-live="polite"></span></div>';
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Send', 'backstage-venue-manager') . '</th><th>' . esc_html__('Email', 'backstage-venue-manager') . '</th><th>' . esc_html__('Name', 'backstage-venue-manager') . '</th><th>' . esc_html__('Tickets', 'backstage-venue-manager') . '</th><th>' . esc_html__('Orders', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
			foreach ($recipients as $recipient) {
				$email = sanitize_email((string) ($recipient['email'] ?? ''));
				if (!is_email($email)) {
					continue;
				}
				echo '<tr><td><input type="checkbox" name="selected_recipients[]" value="' . esc_attr($email) . '" checked data-vms-efu-recipient /></td><td>' . esc_html($email) . '</td><td>' . esc_html((string) ($recipient['name'] ?? '')) . '</td><td>' . esc_html((string) ($recipient['qty'] ?? 0)) . '</td><td>' . esc_html(implode(', ', (array) ($recipient['order_numbers'] ?? array()))) . '</td></tr>';
			}
			echo '</tbody></table></details>';
		} else {
			echo '<p class="vms-efu-warning">' . esc_html__('No eligible recipients were found for this event/template preview.', 'backstage-venue-manager') . '</p>';
		}
		echo '<label class="vms-efu-check"><input type="checkbox" name="confirm_send" value="1" /> <span>' . esc_html__('I understand this sends to the selected eligible recipients above.', 'backstage-venue-manager') . '</span></label>';
		/* translators: %d: number used in this message. */
		echo '<p class="vms-efu-send-note">' . esc_html(sprintf(__('Manual sends are processed in batches of up to %d recipients to reduce timeout risk. If more remain, VMS will show a Continue Sending button after the page returns.', 'backstage-venue-manager'), vms_email_followups_manual_batch_size())) . '</p>';
		echo '<p class="vms-efu-send-progress" aria-live="polite"></p>';
		echo '<button type="submit" class="button">' . esc_html__('Send to Selected Recipients', 'backstage-venue-manager') . '</button>';
		echo '</form>';
		echo '</section>';
	}
}

if (!function_exists('vms_email_followups_render_logs_tab')) {
	function vms_email_followups_render_logs_tab(): void
	{
		$logs = vms_email_followups_get_logs(200);
		echo '<section data-vms-tour="email-followups.logs">';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-efu-clear-logs">';
		wp_nonce_field('vms_email_followups_clear_logs');
		echo '<input type="hidden" name="action" value="vms_email_followups_clear_logs" />';
		echo '<button type="submit" class="button">' . esc_html__('Clear Logs', 'backstage-venue-manager') . '</button>';
		echo '</form>';
		if (empty($logs)) {
			echo '<p>' . esc_html__('No email follow-up logs yet.', 'backstage-venue-manager') . '</p></section>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Time', 'backstage-venue-manager') . '</th><th>' . esc_html__('Status', 'backstage-venue-manager') . '</th><th>' . esc_html__('Action', 'backstage-venue-manager') . '</th><th>' . esc_html__('Template', 'backstage-venue-manager') . '</th><th>' . esc_html__('Event', 'backstage-venue-manager') . '</th><th>' . esc_html__('Recipient', 'backstage-venue-manager') . '</th><th>' . esc_html__('Message', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
		foreach ($logs as $log) {
			$plan_id = absint($log['event_plan_id'] ?? 0);
			echo '<tr><td>' . esc_html((string) ($log['created_at'] ?? '')) . '</td><td>' . esc_html((string) ($log['status'] ?? '')) . '</td><td>' . esc_html((string) ($log['action'] ?? '')) . '</td><td>' . esc_html((string) ($log['email_key'] ?? '')) . '</td><td>' . esc_html($plan_id > 0 ? get_the_title($plan_id) : '') . '</td><td>' . esc_html((string) ($log['recipient'] ?? '')) . '</td><td>' . esc_html((string) ($log['message'] ?? '')) . '</td></tr>';
		}
		echo '</tbody></table></section>';
	}
}

if (!function_exists('vms_email_followups_save_settings_post')) {
	function vms_email_followups_save_settings_post(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
		}
		check_admin_referer('vms_email_followups_save_settings');
		$input = isset($_POST['vms_email_followups']) && is_array($_POST['vms_email_followups']) ? (array) $_POST['vms_email_followups'] : array();
		$tab = isset($_POST['tab']) ? sanitize_key((string) $_POST['tab']) : 'overview';
		$input['_tab'] = $tab;
		update_option(vms_email_followups_option_key(), vms_email_followups_sanitize_settings($input), false);
		vms_email_followups_redirect_notice($tab, __('Email follow-up settings saved.', 'backstage-venue-manager'));
	}
}
add_action('admin_post_vms_email_followups_save_settings', 'vms_email_followups_save_settings_post');


if (!function_exists('vms_email_followups_manual_batch_size')) {
	function vms_email_followups_manual_batch_size(): int
	{
		return (int) apply_filters('vms_email_followups_manual_batch_size', 50);
	}
}

if (!function_exists('vms_email_followups_batch_transient_key')) {
	function vms_email_followups_batch_transient_key(string $token): string
	{
		return 'vms_efu_batch_' . sanitize_key($token);
	}
}

if (!function_exists('vms_email_followups_batch_create')) {
	function vms_email_followups_batch_create(int $event_plan_id, string $email_key, array $emails): string
	{
		$emails = function_exists('vms_email_followups_normalize_recipient_emails') ? vms_email_followups_normalize_recipient_emails($emails) : array();
		if (empty($emails)) {
			return '';
		}
		$token = wp_generate_password(20, false, false);
		set_transient(vms_email_followups_batch_transient_key($token), array(
			'event_plan_id' => absint($event_plan_id),
			'email_key' => sanitize_key($email_key),
			'emails' => $emails,
			'created_at' => time(),
		), 12 * HOUR_IN_SECONDS);
		return $token;
	}
}

if (!function_exists('vms_email_followups_batch_get')) {
	function vms_email_followups_batch_get(string $token): array
	{
		$token = sanitize_key($token);
		if ($token === '') {
			return array();
		}
		$data = get_transient(vms_email_followups_batch_transient_key($token));
		return is_array($data) ? $data : array();
	}
}

if (!function_exists('vms_email_followups_batch_clear')) {
	function vms_email_followups_batch_clear(string $token): void
	{
		$token = sanitize_key($token);
		if ($token !== '') {
			delete_transient(vms_email_followups_batch_transient_key($token));
		}
	}
}

if (!function_exists('vms_email_followups_send_test_post')) {
	function vms_email_followups_send_test_post(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
		}
		check_admin_referer('vms_email_followups_send_test');
		$event_plan_id = isset($_POST['event_plan_id']) ? absint($_POST['event_plan_id']) : 0;
		$email_key = isset($_POST['email_key']) ? sanitize_key((string) $_POST['email_key']) : 'know_before';
		$to = isset($_POST['test_recipient']) ? sanitize_email((string) wp_unslash($_POST['test_recipient'])) : '';
		$result = vms_email_followups_send_test($email_key, $event_plan_id, $to);
		vms_email_followups_redirect_notice('preview', (string) ($result['message'] ?? ''), !empty($result['ok']) ? 'success' : 'error', array('event_plan_id' => $event_plan_id, 'email_key' => $email_key));
	}
}
add_action('admin_post_vms_email_followups_send_test', 'vms_email_followups_send_test_post');

if (!function_exists('vms_email_followups_manual_send_post')) {
	function vms_email_followups_manual_send_post(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
		}
		check_admin_referer('vms_email_followups_manual_send');
		$event_plan_id = isset($_POST['event_plan_id']) ? absint($_POST['event_plan_id']) : 0;
		$email_key = isset($_POST['email_key']) ? sanitize_key((string) $_POST['email_key']) : 'know_before';
		$batch_token = isset($_POST['batch_token']) ? sanitize_key((string) $_POST['batch_token']) : '';
		if (empty($_POST['confirm_send'])) {
			vms_email_followups_redirect_notice('preview', __('Manual send was not confirmed, so no recipient emails were sent.', 'backstage-venue-manager'), 'warning', array('event_plan_id' => $event_plan_id, 'email_key' => $email_key));
		}

		$recipient_emails = array();
		if ($batch_token !== '') {
			$batch = vms_email_followups_batch_get($batch_token);
			if (empty($batch) || (int) ($batch['event_plan_id'] ?? 0) !== $event_plan_id || sanitize_key((string) ($batch['email_key'] ?? '')) !== $email_key) {
				vms_email_followups_redirect_notice('preview', __('The saved send batch expired or no longer matches this event/template. No emails were sent.', 'backstage-venue-manager'), 'warning', array('event_plan_id' => $event_plan_id, 'email_key' => $email_key));
			}
			$recipient_emails = function_exists('vms_email_followups_normalize_recipient_emails') ? vms_email_followups_normalize_recipient_emails((array) ($batch['emails'] ?? array())) : array();
		} elseif (!empty($_POST['recipient_selection_present'])) {
			$selected = isset($_POST['selected_recipients']) && is_array($_POST['selected_recipients']) ? (array) wp_unslash($_POST['selected_recipients']) : array();
			$recipient_emails = function_exists('vms_email_followups_normalize_recipient_emails') ? vms_email_followups_normalize_recipient_emails($selected) : array();
			if (empty($recipient_emails)) {
				vms_email_followups_redirect_notice('preview', __('No recipients were selected, so no emails were sent.', 'backstage-venue-manager'), 'warning', array('event_plan_id' => $event_plan_id, 'email_key' => $email_key));
			}
		}

		$result = vms_email_followups_send_event_email($email_key, $event_plan_id, 'manual', array(
			'recipient_emails' => $recipient_emails,
			'limit' => vms_email_followups_manual_batch_size(),
		));

		$remaining = function_exists('vms_email_followups_normalize_recipient_emails') ? vms_email_followups_normalize_recipient_emails((array) ($result['remaining_emails'] ?? array())) : array();
		$args = array('event_plan_id' => $event_plan_id, 'email_key' => $email_key);
		if (!empty($remaining)) {
			$new_token = $batch_token !== '' ? $batch_token : vms_email_followups_batch_create($event_plan_id, $email_key, $remaining);
			if ($batch_token !== '') {
				vms_email_followups_batch_clear($batch_token);
				$new_token = vms_email_followups_batch_create($event_plan_id, $email_key, $remaining);
			}
			if ($new_token !== '') {
				$args['batch_token'] = $new_token;
			}
		} elseif ($batch_token !== '') {
			vms_email_followups_batch_clear($batch_token);
		}

		/* translators: 1: sent count, 2: skipped count, 3: error count. */
		$message = sprintf(__('Manual send step complete: %1$d sent, %2$d skipped, %3$d errors.', 'backstage-venue-manager'), (int) ($result['sent'] ?? 0), (int) ($result['skipped'] ?? 0), (int) ($result['errors'] ?? 0));
		if (!empty($remaining)) {
			/* translators: %d: number of items described in this message. */
			$message .= ' ' . sprintf(_n('%d recipient remains for the next batch.', '%d recipients remain for the next batch.', count($remaining), 'backstage-venue-manager'), count($remaining));
		}
		vms_email_followups_redirect_notice('preview', $message, !empty($result['ok']) ? 'success' : 'warning', $args);
	}
}
add_action('admin_post_vms_email_followups_manual_send', 'vms_email_followups_manual_send_post');

if (!function_exists('vms_email_followups_clear_logs_post')) {
	function vms_email_followups_clear_logs_post(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
		}
		check_admin_referer('vms_email_followups_clear_logs');
		vms_email_followups_clear_logs();
		vms_email_followups_redirect_notice('logs', __('Email follow-up logs cleared.', 'backstage-venue-manager'));
	}
}
add_action('admin_post_vms_email_followups_clear_logs', 'vms_email_followups_clear_logs_post');

if (!function_exists('vms_email_followups_register_tours')) {
	function vms_email_followups_register_tours(array $tours): array
	{
		$tours[] = array(
			'id' => 'vms.email_followups.basics',
			'title' => __('Email Follow-Ups', 'backstage-venue-manager'),
			'screen' => 'admin:' . vms_email_followups_admin_slug(),
			'version' => '1.0.0',
			'level' => 'beginner',
			'audience' => array('admin'),
			'steps' => array(
				array(
					'id' => 'overview',
					'selector' => '[data-vms-tour="email-followups.overview"]',
					'title' => __('Start with status', 'backstage-venue-manager'),
					'body' => __('This screen checks whether the routine is enabled, whether automatic sends are still guarded, and whether MailPoet is available for delivery/list sync.', 'backstage-venue-manager'),
					'position' => 'bottom',
				),
				array(
					'id' => 'settings',
					'selector' => '[data-vms-tour="email-followups.settings"]',
					'title' => __('Keep the first pass safe', 'backstage-venue-manager'),
					'body' => __('Automatic sends are intentionally separate from the module toggle. Preview recipients and send tests before allowing scheduled customer emails.', 'backstage-venue-manager'),
					'position' => 'top',
				),
				array(
					'id' => 'preview',
					'selector' => '[data-vms-tour="email-followups.preview-controls"]',
					'title' => __('Preview each event', 'backstage-venue-manager'),
					'body' => __('Pick an event and template to see the resolved copy, eligible buyers, skipped conditions, and the event-date-based timing before anything goes out.', 'backstage-venue-manager'),
					'position' => 'bottom',
				),
			),
		);
		return $tours;
	}
}
add_filter('vms_tours_register', 'vms_email_followups_register_tours');
