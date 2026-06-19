<?php

defined('ABSPATH') || exit;

if (!defined('VMS_SAFETY_SETTINGS_OPTION')) {
	define('VMS_SAFETY_SETTINGS_OPTION', 'vms_safety_settings_v1');
}

if (!function_exists('vms_safety_settings_defaults')) {
	/**
	 * @return array<string,mixed>
	 */
	function vms_safety_settings_defaults(): array
	{
		return array(
			'dashboard_cards_enabled' => 1,
			'require_submitted_before_export' => 0,
			'enable_checklist_auto_create' => 0,
		);
	}
}

if (!function_exists('vms_safety_get_settings')) {
	/**
	 * @return array<string,mixed>
	 */
	function vms_safety_get_settings(): array
	{
		$stored = get_option(VMS_SAFETY_SETTINGS_OPTION, array());
		if (!is_array($stored)) {
			$stored = array();
		}
		return array_merge(vms_safety_settings_defaults(), $stored);
	}
}

if (!function_exists('vms_safety_update_settings')) {
	/**
	 * @param array<string,mixed> $incoming
	 */
	function vms_safety_update_settings(array $incoming): array
	{
		$current = vms_safety_get_settings();
		$current['dashboard_cards_enabled'] = !empty($incoming['dashboard_cards_enabled']) ? 1 : 0;
		$current['require_submitted_before_export'] = !empty($incoming['require_submitted_before_export']) ? 1 : 0;
		$current['enable_checklist_auto_create'] = !empty($incoming['enable_checklist_auto_create']) ? 1 : 0;
		update_option(VMS_SAFETY_SETTINGS_OPTION, $current, false);
		return $current;
	}
}

if (!function_exists('vms_safety_admin_tabs')) {
	/**
	 * @return array<string,string>
	 */
	function vms_safety_admin_tabs(): array
	{
		$tabs = array(
			'incidents' => __('Incident Reports', 'vms'),
			'documents' => __('Documents', 'vms'),
			'checklists' => __('Checklists', 'vms'),
			'settings' => __('Settings', 'vms'),
			'help-tour' => __('Help Tour', 'vms'),
		);
		return (array) apply_filters('vms_safety_admin_tabs', $tabs);
	}
}

if (!function_exists('vms_safety_admin_current_tab')) {
	function vms_safety_admin_current_tab(): string
	{
		$tabs = vms_safety_admin_tabs();
		$tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'incidents';
		return isset($tabs[$tab]) ? $tab : 'incidents';
	}
}

if (!function_exists('vms_safety_admin_url')) {
	/**
	 * @param array<string,mixed> $args
	 */
	function vms_safety_admin_url(array $args = array()): string
	{
		$base = admin_url('admin.php?page=vms-safety');
		if (empty($args)) {
			return $base;
		}
		return add_query_arg($args, $base);
	}
}

if (!function_exists('vms_safety_admin_redirect')) {
	/**
	 * @param array<string,mixed> $query
	 */
	function vms_safety_admin_redirect(array $query = array()): void
	{
		wp_safe_redirect(vms_safety_admin_url($query));
		exit;
	}
}

if (!function_exists('vms_safety_admin_notice')) {
	function vms_safety_admin_notice(string $message, string $type = 'success'): void
	{
		vms_safety_admin_redirect(array(
			'tab' => vms_safety_admin_current_tab(),
			'vms_safety_notice' => rawurlencode($message),
			'vms_safety_notice_type' => sanitize_key($type),
		));
	}
}

if (!function_exists('vms_safety_render_notices')) {
	function vms_safety_render_notices(): void
	{
		$notice = isset($_GET['vms_safety_notice']) ? sanitize_text_field(wp_unslash((string) $_GET['vms_safety_notice'])) : '';
		if ($notice === '') {
			return;
		}
		$type = isset($_GET['vms_safety_notice_type']) ? sanitize_key((string) $_GET['vms_safety_notice_type']) : 'success';
		$class = in_array($type, array('success', 'error', 'warning', 'info'), true) ? $type : 'success';
		echo '<div class="notice notice-' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($notice) . '</p></div>';
	}
}

if (!function_exists('vms_safety_register_admin_menu')) {
	function vms_safety_register_admin_menu(): void
	{
		add_submenu_page(
			'vms-dashboard',
			__('Safety', 'vms'),
			__('Safety', 'vms'),
			vms_safety_menu_capability(),
			'vms-safety',
			'vms_safety_render_admin_page'
		);
	}
}
add_action('admin_menu', 'vms_safety_register_admin_menu', 44);

if (!function_exists('vms_safety_enqueue_admin_assets')) {
	function vms_safety_enqueue_admin_assets(): void
	{
		$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
		if ($page !== 'vms-safety') {
			return;
		}
		$ver = defined('VMS_VERSION') ? (string) VMS_VERSION : null;
		wp_enqueue_style('vms-safety-admin', VMS_PLUGIN_URL . 'assets/css/vms-safety-admin.css', array(), $ver);
		wp_enqueue_script('vms-safety-admin', VMS_PLUGIN_URL . 'assets/js/vms-safety-admin.js', array(), $ver, true);
	}
}
add_action('admin_enqueue_scripts', 'vms_safety_enqueue_admin_assets', 35);

if (!function_exists('vms_safety_render_admin_page')) {
	function vms_safety_render_admin_page(): void
	{
		if (!vms_safety_user_can_view()) {
			wp_die(esc_html__('You do not have permission to access Safety.', 'vms'));
		}

		$actions = '';
		if (function_exists('vms_render_help_button')) {
			$actions = vms_render_help_button(array(
				'label' => __('Launch Help Tour', 'vms'),
				'tour_id' => 'vms_safety_overview',
				'anchor' => 'safety.help',
			));
		}

		if (function_exists('vms_admin_ui_render_shell')) {
			vms_admin_ui_render_shell(
				array(
					'title' => __('Safety Toolkit', 'vms'),
					'subtitle' => __('Incident reports, private document vault, and checklists.', 'vms'),
					'actions_html' => $actions,
				),
				'vms_safety_render_admin_page_content'
			);
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__('Safety Toolkit', 'vms') . '</h1>';
		vms_safety_render_admin_page_content();
		echo '</div>';
	}
}

if (!function_exists('vms_safety_render_admin_page_content')) {
	function vms_safety_render_admin_page_content(): void
	{
		$tab = vms_safety_admin_current_tab();
		$tabs = vms_safety_admin_tabs();

		echo '<div class="vms-safety-admin" data-vms-tour="safety.help">';
		vms_safety_render_notices();

		echo '<nav class="nav-tab-wrapper" data-vms-tour="safety.tabs">';
		foreach ($tabs as $key => $label) {
			$class = ($key === $tab) ? 'nav-tab nav-tab-active' : 'nav-tab';
			echo '<a class="' . esc_attr($class) . '" href="' . esc_url(vms_safety_admin_url(array('tab' => $key))) . '">' . esc_html($label) . '</a>';
		}
		echo '</nav>';

		echo '<section class="vms-safety-panel" data-vms-tour="safety.panel">';
		switch ($tab) {
			case 'incidents':
				vms_safety_render_incidents_tab();
				break;
			case 'documents':
				vms_safety_render_documents_tab();
				break;
			case 'checklists':
				vms_safety_render_checklists_tab();
				break;
			case 'settings':
				vms_safety_render_settings_tab();
				break;
			case 'help-tour':
				vms_safety_render_help_tab();
				break;
			default:
				do_action('vms_safety_render_tab_' . $tab);
				break;
		}
		echo '</section>';
		echo '</div>';
	}
}

if (!function_exists('vms_safety_incident_status_options')) {
	/**
	 * @return array<string,string>
	 */
	function vms_safety_incident_status_options(): array
	{
		return array(
			'draft' => __('Draft', 'vms'),
			'submitted' => __('Submitted', 'vms'),
			'reviewed' => __('Reviewed', 'vms'),
			'closed' => __('Closed', 'vms'),
		);
	}
}

if (!function_exists('vms_safety_incident_severity_options')) {
	/**
	 * @return array<string,string>
	 */
	function vms_safety_incident_severity_options(): array
	{
		return array(
			'low' => __('Low', 'vms'),
			'medium' => __('Medium', 'vms'),
			'high' => __('High', 'vms'),
		);
	}
}

if (!function_exists('vms_safety_render_incidents_tab')) {
	function vms_safety_render_incidents_tab(): void
	{
		$incident_id = isset($_GET['incident_id']) ? absint($_GET['incident_id']) : 0;
		$editing = $incident_id > 0 ? get_post($incident_id) : null;
		if (!$editing instanceof WP_Post || $editing->post_type !== 'vms_incident') {
			$editing = null;
		}

		echo '<h2>' . esc_html__('Incident Reports', 'vms') . '</h2>';
		echo '<p class="description">' . esc_html__('Document what happened, actions taken, witnesses, and supporting files.', 'vms') . '</p>';

		$incidents = get_posts(array(
			'post_type' => 'vms_incident',
			'post_status' => array('publish', 'draft'),
			'posts_per_page' => 20,
			'orderby' => 'date',
			'order' => 'DESC',
		));

		echo '<table class="widefat striped" data-vms-tour="safety.incidents.list">';
		echo '<thead><tr><th>' . esc_html__('Incident', 'vms') . '</th><th>' . esc_html__('Date/Time', 'vms') . '</th><th>' . esc_html__('Severity', 'vms') . '</th><th>' . esc_html__('Status', 'vms') . '</th><th>' . esc_html__('Actions', 'vms') . '</th></tr></thead><tbody>';
		if (empty($incidents)) {
			echo '<tr><td colspan="5">' . esc_html__('No incidents yet.', 'vms') . '</td></tr>';
		}
		foreach ($incidents as $incident) {
			$iid = (int) $incident->ID;
			$datetime = (string) get_post_meta($iid, 'vms_incident_datetime', true);
			$severity = (string) get_post_meta($iid, 'vms_incident_severity', true);
			$status = (string) get_post_meta($iid, 'vms_incident_status', true);
			if ($status === '') {
				$status = 'draft';
			}
			echo '<tr>';
			echo '<td><strong>' . esc_html(get_the_title($iid)) . '</strong></td>';
			echo '<td>' . esc_html($datetime !== '' ? $datetime : '—') . '</td>';
			echo '<td>' . esc_html(ucfirst($severity !== '' ? $severity : 'low')) . '</td>';
			echo '<td>' . esc_html(ucfirst($status)) . '</td>';
			echo '<td class="vms-safety-actions">';
			echo '<a class="button button-small" href="' . esc_url(vms_safety_admin_url(array('tab' => 'incidents', 'incident_id' => $iid))) . '">' . esc_html__('Edit', 'vms') . '</a> ';
			echo '<a class="button button-small" href="' . esc_url(wp_nonce_url(add_query_arg(array('action' => 'vms_safety_export_incident', 'incident_id' => $iid), admin_url('admin-post.php')), 'vms_safety_export_incident_' . $iid)) . '" target="_blank">' . esc_html__('Print', 'vms') . '</a> ';
			if ($status !== 'closed') {
				echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-safety-inline-form">';
				wp_nonce_field('vms_safety_close_incident_' . $iid);
				echo '<input type="hidden" name="action" value="vms_safety_close_incident" />';
				echo '<input type="hidden" name="incident_id" value="' . esc_attr((string) $iid) . '" />';
				echo '<button type="submit" class="button button-small">' . esc_html__('Close', 'vms') . '</button>';
				echo '</form>';
			}
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		$edit_title = $editing ? __('Edit Incident', 'vms') : __('New Incident', 'vms');
		$iid = $editing ? (int) $editing->ID : 0;
		$incident_datetime = $editing ? (string) get_post_meta($iid, 'vms_incident_datetime', true) : '';
		$incident_zone = $editing ? (string) get_post_meta($iid, 'vms_incident_zone', true) : '';
		$incident_severity = $editing ? (string) get_post_meta($iid, 'vms_incident_severity', true) : 'low';
		$incident_status = $editing ? (string) get_post_meta($iid, 'vms_incident_status', true) : 'draft';
		$actions_taken = $editing ? (string) get_post_meta($iid, 'vms_incident_actions_taken', true) : '';
		$witnesses = $editing ? (string) get_post_meta($iid, 'vms_incident_witnesses', true) : '';
		$event_plan_id = $editing ? (int) get_post_meta($iid, 'vms_incident_event_plan_id', true) : 0;
		$internal_notes = $editing ? (string) get_post_meta($iid, 'vms_incident_internal_notes', true) : '';

		echo '<hr />';
		echo '<h3 data-vms-tour="safety.incidents.form">' . esc_html($edit_title) . '</h3>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data" class="vms-safety-form" data-vms-safety-sticky-form="1">';
		wp_nonce_field('vms_safety_save_incident');
		echo '<input type="hidden" name="action" value="vms_safety_save_incident" />';
		echo '<input type="hidden" name="incident_id" value="' . esc_attr((string) $iid) . '" />';

		echo '<div class="vms-safety-grid">';
		echo '<p><label>' . esc_html__('Title', 'vms') . '<br /><input type="text" name="incident_title" required class="regular-text" value="' . esc_attr($editing ? get_the_title($editing) : '') . '" /></label></p>';
		echo '<p><label>' . esc_html__('Date/Time (Y-m-d H:i)', 'vms') . '<br /><input type="text" name="incident_datetime" class="regular-text" value="' . esc_attr($incident_datetime) . '" placeholder="2026-03-04 19:00" /></label></p>';
		echo '<p><label>' . esc_html__('Location/Zone', 'vms') . '<br /><input type="text" name="incident_zone" class="regular-text" value="' . esc_attr($incident_zone) . '" /></label></p>';
		echo '<p><label>' . esc_html__('Severity', 'vms') . '<br /><select name="incident_severity">';
		foreach (vms_safety_incident_severity_options() as $sev => $label) {
			echo '<option value="' . esc_attr($sev) . '" ' . selected($incident_severity, $sev, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select></label></p>';
		echo '<p><label>' . esc_html__('Status', 'vms') . '<br /><select name="incident_status">';
		foreach (vms_safety_incident_status_options() as $st => $label) {
			echo '<option value="' . esc_attr($st) . '" ' . selected($incident_status, $st, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select></label></p>';
		echo '<p><label>' . esc_html__('Linked Event Plan ID (optional)', 'vms') . '<br /><input type="number" min="0" name="incident_event_plan_id" value="' . esc_attr((string) $event_plan_id) . '" /></label></p>';
		echo '</div>';

		echo '<p><label>' . esc_html__('What happened', 'vms') . '<br /><textarea name="incident_description" rows="4" class="large-text">' . esc_textarea($editing ? (string) $editing->post_content : '') . '</textarea></label></p>';
		echo '<p><label>' . esc_html__('Actions taken', 'vms') . '<br /><textarea name="incident_actions_taken" rows="3" class="large-text">' . esc_textarea($actions_taken) . '</textarea></label></p>';
		echo '<p><label>' . esc_html__('Witnesses (optional)', 'vms') . '<br /><textarea name="incident_witnesses" rows="3" class="large-text">' . esc_textarea($witnesses) . '</textarea></label></p>';
		echo '<p><label>' . esc_html__('Internal notes (admin only)', 'vms') . '<br /><textarea name="incident_internal_notes" rows="3" class="large-text">' . esc_textarea($internal_notes) . '</textarea></label></p>';
		echo '<p><label>' . esc_html__('Attachments/photos', 'vms') . '<br /><input type="file" name="incident_attachments[]" multiple /></label></p>';

		if ($editing) {
			$attachments = get_post_meta($iid, 'vms_incident_attachments', true);
			$attachments = is_array($attachments) ? $attachments : array();
			if (!empty($attachments)) {
				echo '<p><strong>' . esc_html__('Existing attachments', 'vms') . ':</strong><br />';
				$links = array();
				foreach ($attachments as $file_id) {
					$row = vms_safety_private_file_get((int) $file_id);
					if (!$row) {
						continue;
					}
					$links[] = '<a href="' . esc_url(vms_safety_private_file_download_url((int) $file_id)) . '">' . esc_html((string) $row['original_filename']) . '</a>';
				}
				echo implode(' | ', $links);
				echo '</p>';
			}
		}

		echo '<div class="vms-safety-sticky-save" data-vms-tour="safety.savebar">';
		echo '<button type="submit" class="button button-primary">' . esc_html__('Save Incident', 'vms') . '</button>';
		echo '</div>';
		echo '</form>';
	}
}

if (!function_exists('vms_safety_normalize_multi_upload')) {
	/**
	 * @param array<string,mixed> $files
	 * @return array<int,array<string,mixed>>
	 */
	function vms_safety_normalize_multi_upload(array $files): array
	{
		$out = array();
		$names = isset($files['name']) && is_array($files['name']) ? $files['name'] : array();
		foreach ($names as $i => $name) {
			$out[] = array(
				'name' => (string) $name,
				'type' => isset($files['type'][$i]) ? (string) $files['type'][$i] : '',
				'tmp_name' => isset($files['tmp_name'][$i]) ? (string) $files['tmp_name'][$i] : '',
				'error' => isset($files['error'][$i]) ? (int) $files['error'][$i] : UPLOAD_ERR_NO_FILE,
				'size' => isset($files['size'][$i]) ? (int) $files['size'][$i] : 0,
			);
		}
		return $out;
	}
}

if (!function_exists('vms_safety_handle_save_incident')) {
	function vms_safety_handle_save_incident(): void
	{
		if (!vms_safety_user_can_manage()) {
			wp_die(esc_html__('Not allowed.', 'vms'));
		}
		check_admin_referer('vms_safety_save_incident');

		$incident_id = isset($_POST['incident_id']) ? absint($_POST['incident_id']) : 0;
		$is_update = ($incident_id > 0);
		$title = isset($_POST['incident_title']) ? sanitize_text_field(wp_unslash((string) $_POST['incident_title'])) : '';
		$content = isset($_POST['incident_description']) ? wp_kses_post(wp_unslash((string) $_POST['incident_description'])) : '';
		if ($title === '') {
			vms_safety_admin_notice(__('Incident title is required.', 'vms'), 'error');
		}

		$postarr = array(
			'post_type' => 'vms_incident',
			'post_title' => $title,
			'post_content' => $content,
			'post_status' => 'publish',
		);
		if ($incident_id > 0) {
			$postarr['ID'] = $incident_id;
		}

		$result = $is_update ? wp_update_post($postarr, true) : wp_insert_post($postarr, true);
		if (is_wp_error($result) || !$result) {
			vms_safety_admin_notice(__('Could not save incident.', 'vms'), 'error');
		}

		$incident_id = (int) $result;
		$severity = isset($_POST['incident_severity']) ? sanitize_key((string) $_POST['incident_severity']) : 'low';
		$status = isset($_POST['incident_status']) ? sanitize_key((string) $_POST['incident_status']) : 'draft';
		$valid_severity = array_keys(vms_safety_incident_severity_options());
		$valid_status = array_keys(vms_safety_incident_status_options());
		if (!in_array($severity, $valid_severity, true)) {
			$severity = 'low';
		}
		if (!in_array($status, $valid_status, true)) {
			$status = 'draft';
		}

		update_post_meta($incident_id, 'vms_incident_datetime', sanitize_text_field((string) ($_POST['incident_datetime'] ?? '')));
		update_post_meta($incident_id, 'vms_incident_zone', sanitize_text_field((string) ($_POST['incident_zone'] ?? '')));
		update_post_meta($incident_id, 'vms_incident_severity', $severity);
		update_post_meta($incident_id, 'vms_incident_status', $status);
		update_post_meta($incident_id, 'vms_incident_event_plan_id', absint($_POST['incident_event_plan_id'] ?? 0));
		update_post_meta($incident_id, 'vms_incident_actions_taken', sanitize_textarea_field((string) ($_POST['incident_actions_taken'] ?? '')));
		update_post_meta($incident_id, 'vms_incident_witnesses', sanitize_textarea_field((string) ($_POST['incident_witnesses'] ?? '')));
		update_post_meta($incident_id, 'vms_incident_internal_notes', sanitize_textarea_field((string) ($_POST['incident_internal_notes'] ?? '')));

		$attachment_ids = get_post_meta($incident_id, 'vms_incident_attachments', true);
		$attachment_ids = is_array($attachment_ids) ? $attachment_ids : array();
		if (isset($_FILES['incident_attachments']) && is_array($_FILES['incident_attachments'])) {
			$uploads = vms_safety_normalize_multi_upload($_FILES['incident_attachments']);
			foreach ($uploads as $upload) {
				if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
					continue;
				}
				$file_id = vms_safety_store_private_upload($upload, array('related_post_type' => 'vms_incident', 'related_post_id' => $incident_id));
				if (!is_wp_error($file_id)) {
					$attachment_ids[] = (int) $file_id;
				}
			}
		}
		update_post_meta($incident_id, 'vms_incident_attachments', array_values(array_unique(array_map('absint', $attachment_ids))));

		vms_safety_audit_log($is_update ? 'incident_updated' : 'incident_created', array('incident_id' => $incident_id, 'status' => $status));
		vms_safety_admin_redirect(array('tab' => 'incidents', 'incident_id' => $incident_id, 'vms_safety_notice' => rawurlencode(__('Incident saved.', 'vms')), 'vms_safety_notice_type' => 'success'));
	}
}
add_action('admin_post_vms_safety_save_incident', 'vms_safety_handle_save_incident');

if (!function_exists('vms_safety_handle_close_incident')) {
	function vms_safety_handle_close_incident(): void
	{
		if (!vms_safety_user_can_manage()) {
			wp_die(esc_html__('Not allowed.', 'vms'));
		}
		$incident_id = isset($_POST['incident_id']) ? absint($_POST['incident_id']) : 0;
		if ($incident_id <= 0) {
			vms_safety_admin_notice(__('Missing incident id.', 'vms'), 'error');
		}
		check_admin_referer('vms_safety_close_incident_' . $incident_id);

		update_post_meta($incident_id, 'vms_incident_status', 'closed');
		vms_safety_audit_log('incident_closed', array('incident_id' => $incident_id));
		vms_safety_admin_redirect(array('tab' => 'incidents', 'vms_safety_notice' => rawurlencode(__('Incident closed.', 'vms'))));
	}
}
add_action('admin_post_vms_safety_close_incident', 'vms_safety_handle_close_incident');

if (!function_exists('vms_safety_render_export_shell')) {
	function vms_safety_render_export_shell(string $title, string $body_html): void
	{
		echo '<!doctype html><html><head><meta charset="utf-8" />';
		echo '<title>' . esc_html($title) . '</title>';
		echo '</head><body>';
		echo '<p><button onclick="window.print()">Print / Save as PDF</button></p>';
		echo $body_html;
		echo '</body></html>';
	}
}

if (!function_exists('vms_safety_handle_export_incident')) {
	function vms_safety_handle_export_incident(): void
	{
		if (!vms_safety_user_can_export()) {
			wp_die(esc_html__('Not allowed.', 'vms'));
		}
		$incident_id = isset($_GET['incident_id']) ? absint($_GET['incident_id']) : 0;
		check_admin_referer('vms_safety_export_incident_' . $incident_id);
		$incident = $incident_id > 0 ? get_post($incident_id) : null;
		if (!$incident instanceof WP_Post || $incident->post_type !== 'vms_incident') {
			wp_die(esc_html__('Incident not found.', 'vms'));
		}

		$settings = vms_safety_get_settings();
		$status = (string) get_post_meta($incident_id, 'vms_incident_status', true);
		if (!empty($settings['require_submitted_before_export']) && !in_array($status, array('submitted', 'reviewed', 'closed'), true)) {
			wp_die(esc_html__('Incident must be submitted before export.', 'vms'));
		}

		$html = '<h1>' . esc_html(get_the_title($incident_id)) . '</h1>';
		$html .= '<p class="muted">' . esc_html__('Sample operational report. Not legal advice.', 'vms') . '</p>';
		$html .= '<div class="block"><strong>Date/Time:</strong> ' . esc_html((string) get_post_meta($incident_id, 'vms_incident_datetime', true)) . '</div>';
		$html .= '<div class="block"><strong>Zone:</strong> ' . esc_html((string) get_post_meta($incident_id, 'vms_incident_zone', true)) . '</div>';
		$html .= '<div class="block"><strong>Severity:</strong> ' . esc_html((string) get_post_meta($incident_id, 'vms_incident_severity', true)) . '</div>';
		$html .= '<div class="block"><strong>Status:</strong> ' . esc_html($status) . '</div>';
		$html .= '<div class="block"><strong>Description:</strong><br />' . nl2br(esc_html((string) $incident->post_content)) . '</div>';
		$html .= '<div class="block"><strong>Actions taken:</strong><br />' . nl2br(esc_html((string) get_post_meta($incident_id, 'vms_incident_actions_taken', true))) . '</div>';
		$html .= '<div class="block"><strong>Witnesses:</strong><br />' . nl2br(esc_html((string) get_post_meta($incident_id, 'vms_incident_witnesses', true))) . '</div>';

		$attachments = get_post_meta($incident_id, 'vms_incident_attachments', true);
		$attachments = is_array($attachments) ? $attachments : array();
		if (!empty($attachments)) {
			$html .= '<div class="block"><strong>Attachments:</strong><ul>';
			foreach ($attachments as $file_id) {
				$row = vms_safety_private_file_get((int) $file_id);
				if (!$row) {
					continue;
				}
				$html .= '<li>' . esc_html((string) $row['original_filename']) . '</li>';
			}
			$html .= '</ul></div>';
		}

		vms_safety_audit_log('incident_exported', array('incident_id' => $incident_id));
		vms_safety_render_export_shell(get_the_title($incident_id), $html);
		exit;
	}
}
add_action('admin_post_vms_safety_export_incident', 'vms_safety_handle_export_incident');

if (!function_exists('vms_safety_doc_category_options')) {
	/**
	 * @return array<string,string>
	 */
	function vms_safety_doc_category_options(): array
	{
		return array(
			'plan' => __('Plans', 'vms'),
			'agreement' => __('Agreements', 'vms'),
			'insurance' => __('Insurance', 'vms'),
			'other' => __('Other', 'vms'),
		);
	}
}

if (!function_exists('vms_safety_render_documents_tab')) {
	function vms_safety_render_documents_tab(): void
	{
		echo '<h2>' . esc_html__('Document Vault', 'vms') . '</h2>';
		echo '<p class="description" data-vms-tour="safety.documents.upload">' . esc_html__('Private storage with secure download links, version references, and review dates.', 'vms') . '</p>';

		$docs = get_posts(array(
			'post_type' => 'vms_doc',
			'post_status' => array('publish', 'draft'),
			'posts_per_page' => 50,
			'orderby' => 'date',
			'order' => 'DESC',
		));

		echo '<table class="widefat striped" data-vms-tour="safety.documents.list">';
		echo '<thead><tr><th>' . esc_html__('Title', 'vms') . '</th><th>' . esc_html__('Category', 'vms') . '</th><th>' . esc_html__('Last Reviewed', 'vms') . '</th><th>' . esc_html__('Next Review', 'vms') . '</th><th>' . esc_html__('Actions', 'vms') . '</th></tr></thead><tbody>';
		if (empty($docs)) {
			echo '<tr><td colspan="5">' . esc_html__('No safety documents yet.', 'vms') . '</td></tr>';
		}
		foreach ($docs as $doc) {
			$doc_id = (int) $doc->ID;
			$file_id = (int) get_post_meta($doc_id, 'vms_doc_private_file_id', true);
			$category = (string) get_post_meta($doc_id, 'vms_doc_category', true);
			$last = (string) get_post_meta($doc_id, 'vms_doc_last_reviewed', true);
			$next = (string) get_post_meta($doc_id, 'vms_doc_next_review', true);
			echo '<tr>';
			echo '<td>' . esc_html(get_the_title($doc_id)) . '</td>';
			echo '<td>' . esc_html($category !== '' ? $category : 'other') . '</td>';
			echo '<td>' . esc_html($last !== '' ? $last : '—') . '</td>';
			echo '<td>' . esc_html($next !== '' ? $next : '—') . '</td>';
			echo '<td class="vms-safety-actions">';
			if ($file_id > 0) {
				echo '<a class="button button-small" href="' . esc_url(vms_safety_private_file_download_url($file_id)) . '">' . esc_html__('Download', 'vms') . '</a> ';
			}
			echo '<a class="button button-small" target="_blank" href="' . esc_url(wp_nonce_url(add_query_arg(array('action' => 'vms_safety_export_doc', 'doc_id' => $doc_id), admin_url('admin-post.php')), 'vms_safety_export_doc_' . $doc_id)) . '">' . esc_html__('Print', 'vms') . '</a>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<hr />';
		echo '<h3>' . esc_html__('Upload Document', 'vms') . '</h3>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data" class="vms-safety-form" data-vms-safety-sticky-form="1">';
		wp_nonce_field('vms_safety_save_doc');
		echo '<input type="hidden" name="action" value="vms_safety_save_doc" />';
		echo '<div class="vms-safety-grid">';
		echo '<p><label>' . esc_html__('Title', 'vms') . '<br /><input type="text" class="regular-text" name="doc_title" required /></label></p>';
		echo '<p><label>' . esc_html__('Category', 'vms') . '<br /><select name="doc_category">';
		foreach (vms_safety_doc_category_options() as $key => $label) {
			echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
		}
		echo '</select></label></p>';
		echo '<p><label>' . esc_html__('Last Reviewed', 'vms') . '<br /><input type="date" name="doc_last_reviewed" /></label></p>';
		echo '<p><label>' . esc_html__('Next Review', 'vms') . '<br /><input type="date" name="doc_next_review" /></label></p>';
		echo '<p><label>' . esc_html__('Related Event Plan ID (optional)', 'vms') . '<br /><input type="number" min="0" name="doc_related_event_plan_id" /></label></p>';
		echo '<p><label>' . esc_html__('Related Vendor ID (optional)', 'vms') . '<br /><input type="number" min="0" name="doc_related_vendor_id" /></label></p>';
		echo '<p><label>' . esc_html__('Version parent Doc ID (optional)', 'vms') . '<br /><input type="number" min="0" name="doc_version_parent" /></label></p>';
		echo '</div>';
		echo '<p><label>' . esc_html__('File', 'vms') . '<br /><input type="file" name="doc_file" required /></label></p>';
		echo '<div class="vms-safety-sticky-save"><button type="submit" class="button button-primary">' . esc_html__('Save Document', 'vms') . '</button></div>';
		echo '</form>';
	}
}

if (!function_exists('vms_safety_handle_save_doc')) {
	function vms_safety_handle_save_doc(): void
	{
		if (!vms_safety_user_can_manage()) {
			wp_die(esc_html__('Not allowed.', 'vms'));
		}
		check_admin_referer('vms_safety_save_doc');

		$title = sanitize_text_field((string) ($_POST['doc_title'] ?? ''));
		if ($title === '') {
			vms_safety_admin_redirect(array('tab' => 'documents', 'vms_safety_notice' => rawurlencode(__('Document title is required.', 'vms')), 'vms_safety_notice_type' => 'error'));
		}

		if (!isset($_FILES['doc_file']) || !is_array($_FILES['doc_file'])) {
			vms_safety_admin_redirect(array('tab' => 'documents', 'vms_safety_notice' => rawurlencode(__('Please upload a file.', 'vms')), 'vms_safety_notice_type' => 'error'));
		}

		$doc_id = wp_insert_post(array(
			'post_type' => 'vms_doc',
			'post_status' => 'publish',
			'post_title' => $title,
		), true);
		if (is_wp_error($doc_id) || !$doc_id) {
			vms_safety_admin_redirect(array('tab' => 'documents', 'vms_safety_notice' => rawurlencode(__('Could not create document record.', 'vms')), 'vms_safety_notice_type' => 'error'));
		}
		$doc_id = (int) $doc_id;

		$file_id = vms_safety_store_private_upload($_FILES['doc_file'], array('related_post_type' => 'vms_doc', 'related_post_id' => $doc_id));
		if (is_wp_error($file_id)) {
			wp_delete_post($doc_id, true);
			vms_safety_admin_redirect(array('tab' => 'documents', 'vms_safety_notice' => rawurlencode($file_id->get_error_message()), 'vms_safety_notice_type' => 'error'));
		}

		update_post_meta($doc_id, 'vms_doc_private_file_id', (int) $file_id);
		update_post_meta($doc_id, 'vms_doc_category', sanitize_key((string) ($_POST['doc_category'] ?? 'other')));
		update_post_meta($doc_id, 'vms_doc_last_reviewed', sanitize_text_field((string) ($_POST['doc_last_reviewed'] ?? '')));
		update_post_meta($doc_id, 'vms_doc_next_review', sanitize_text_field((string) ($_POST['doc_next_review'] ?? '')));
		update_post_meta($doc_id, 'vms_doc_related_event_plan_id', absint($_POST['doc_related_event_plan_id'] ?? 0));
		update_post_meta($doc_id, 'vms_doc_related_vendor_id', absint($_POST['doc_related_vendor_id'] ?? 0));
		update_post_meta($doc_id, 'vms_doc_version_parent', absint($_POST['doc_version_parent'] ?? 0));

		$version_parent = (int) get_post_meta($doc_id, 'vms_doc_version_parent', true);
		vms_safety_audit_log($version_parent > 0 ? 'doc_versioned' : 'doc_uploaded', array('doc_id' => $doc_id, 'file_id' => (int) $file_id));
		vms_safety_admin_redirect(array('tab' => 'documents', 'vms_safety_notice' => rawurlencode(__('Document saved.', 'vms'))));
	}
}
add_action('admin_post_vms_safety_save_doc', 'vms_safety_handle_save_doc');

if (!function_exists('vms_safety_handle_export_doc')) {
	function vms_safety_handle_export_doc(): void
	{
		if (!vms_safety_user_can_export()) {
			wp_die(esc_html__('Not allowed.', 'vms'));
		}
		$doc_id = isset($_GET['doc_id']) ? absint($_GET['doc_id']) : 0;
		check_admin_referer('vms_safety_export_doc_' . $doc_id);
		$doc = $doc_id > 0 ? get_post($doc_id) : null;
		if (!$doc instanceof WP_Post || $doc->post_type !== 'vms_doc') {
			wp_die(esc_html__('Document not found.', 'vms'));
		}

		$html = '<h1>' . esc_html(get_the_title($doc_id)) . '</h1>';
		$html .= '<p class="muted">' . esc_html__('Sample template for operational use. Not legal advice. Local requirements vary. Have your insurer and attorney review.', 'vms') . '</p>';
		$html .= '<div class="block"><strong>Category:</strong> ' . esc_html((string) get_post_meta($doc_id, 'vms_doc_category', true)) . '</div>';
		$html .= '<div class="block"><strong>Last reviewed:</strong> ' . esc_html((string) get_post_meta($doc_id, 'vms_doc_last_reviewed', true)) . '</div>';
		$html .= '<div class="block"><strong>Next review:</strong> ' . esc_html((string) get_post_meta($doc_id, 'vms_doc_next_review', true)) . '</div>';
		$html .= '<div class="block"><strong>Notes:</strong><br />' . nl2br(esc_html((string) $doc->post_content)) . '</div>';

		$file_id = (int) get_post_meta($doc_id, 'vms_doc_private_file_id', true);
		if ($file_id > 0) {
			$row = vms_safety_private_file_get($file_id);
			if ($row) {
				$html .= '<div class="block"><strong>Attached file:</strong> ' . esc_html((string) $row['original_filename']) . '</div>';
			}
		}

		vms_safety_audit_log('doc_reviewed', array('doc_id' => $doc_id));
		vms_safety_render_export_shell(get_the_title($doc_id), $html);
		exit;
	}
}
add_action('admin_post_vms_safety_export_doc', 'vms_safety_handle_export_doc');

if (!function_exists('vms_safety_parse_checklist_lines')) {
	/**
	 * @return array<int,string>
	 */
	function vms_safety_parse_checklist_lines(string $lines): array
	{
		$parts = preg_split('/\r\n|\r|\n/', $lines);
		if (!is_array($parts)) {
			return array();
		}
		$out = array();
		foreach ($parts as $line) {
			$line = trim((string) $line);
			if ($line === '') {
				continue;
			}
			$out[] = $line;
		}
		return array_values(array_unique($out));
	}
}

if (!function_exists('vms_safety_render_checklists_tab')) {
	function vms_safety_render_checklists_tab(): void
	{
		echo '<h2>' . esc_html__('Checklists', 'vms') . '</h2>';
		echo '<p class="description" data-vms-tour="safety.checklists.templates">' . esc_html__('Create reusable templates and spin up event-linked or general checklist instances.', 'vms') . '</p>';

		$templates = get_posts(array(
			'post_type' => 'vms_checklist_tpl',
			'post_status' => array('publish', 'draft'),
			'posts_per_page' => 50,
			'orderby' => 'date',
			'order' => 'DESC',
		));
		$instances = get_posts(array(
			'post_type' => 'vms_checklist',
			'post_status' => array('publish', 'draft'),
			'posts_per_page' => 30,
			'orderby' => 'date',
			'order' => 'DESC',
		));

		echo '<div class="vms-safety-grid">';
		echo '<div>';
		echo '<h3>' . esc_html__('New Checklist Template', 'vms') . '</h3>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-safety-form">';
		wp_nonce_field('vms_safety_save_checklist_tpl');
		echo '<input type="hidden" name="action" value="vms_safety_save_checklist_tpl" />';
		echo '<p><label>' . esc_html__('Template title', 'vms') . '<br /><input type="text" class="regular-text" name="tpl_title" required /></label></p>';
		echo '<p><label>' . esc_html__('Applies to', 'vms') . '<br /><select name="tpl_applies_to"><option value="event">Event</option><option value="general">General</option><option value="both">Both</option></select></label></p>';
		echo '<p><label>' . esc_html__('Auto-create on event status (optional)', 'vms') . '<br /><input type="text" class="regular-text" name="tpl_autocreate_on_event_status" placeholder="published" /></label></p>';
		echo '<p><label>' . esc_html__('Checklist items (one per line)', 'vms') . '<br /><textarea name="tpl_items" class="large-text" rows="8" required></textarea></label></p>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__('Save Template', 'vms') . '</button></p>';
		echo '</form>';
		echo '</div>';

		echo '<div>';
		echo '<h3>' . esc_html__('Create Checklist Instance', 'vms') . '</h3>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-safety-form">';
		wp_nonce_field('vms_safety_create_checklist');
		echo '<input type="hidden" name="action" value="vms_safety_create_checklist" />';
		echo '<p><label>' . esc_html__('Title', 'vms') . '<br /><input type="text" class="regular-text" name="checklist_title" required /></label></p>';
		echo '<p><label>' . esc_html__('Template', 'vms') . '<br /><select name="template_id" required>';
		echo '<option value="">' . esc_html__('Select template', 'vms') . '</option>';
		foreach ($templates as $tpl) {
			echo '<option value="' . esc_attr((string) $tpl->ID) . '">' . esc_html($tpl->post_title) . '</option>';
		}
		echo '</select></label></p>';
		echo '<p><label>' . esc_html__('Linked Event Plan ID (optional)', 'vms') . '<br /><input type="number" min="0" name="checklist_event_plan_id" /></label></p>';
		echo '<p><button type="submit" class="button button-secondary">' . esc_html__('Create Checklist', 'vms') . '</button></p>';
		echo '</form>';
		echo '</div>';
		echo '</div>';

		echo '<h3>' . esc_html__('Templates', 'vms') . '</h3>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Template', 'vms') . '</th><th>' . esc_html__('Applies To', 'vms') . '</th><th>' . esc_html__('Items', 'vms') . '</th></tr></thead><tbody>';
		if (empty($templates)) {
			echo '<tr><td colspan="3">' . esc_html__('No checklist templates yet.', 'vms') . '</td></tr>';
		}
		foreach ($templates as $tpl) {
			$items = get_post_meta((int) $tpl->ID, 'vms_chk_tpl_items', true);
			$items = is_array($items) ? $items : array();
			echo '<tr><td>' . esc_html($tpl->post_title) . '</td><td>' . esc_html((string) get_post_meta((int) $tpl->ID, 'vms_chk_tpl_applies_to', true)) . '</td><td>' . esc_html((string) count($items)) . '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h3 data-vms-tour="safety.checklists.instances">' . esc_html__('Checklist Instances', 'vms') . '</h3>';
		if (empty($instances)) {
			echo '<p>' . esc_html__('No checklist instances yet.', 'vms') . '</p>';
		}
		foreach ($instances as $instance) {
			$cid = (int) $instance->ID;
			$items = get_post_meta($cid, 'vms_chk_items', true);
			$items = is_array($items) ? $items : array();
			$status = (string) get_post_meta($cid, 'vms_chk_status', true);
			if ($status === '') {
				$status = 'open';
			}
			echo '<article class="vms-safety-card">';
			echo '<header><strong>' . esc_html(get_the_title($cid)) . '</strong> <span class="vms-safety-chip">' . esc_html(strtoupper($status)) . '</span> ';
			echo '<a class="button button-small" target="_blank" href="' . esc_url(wp_nonce_url(add_query_arg(array('action' => 'vms_safety_export_checklist', 'checklist_id' => $cid), admin_url('admin-post.php')), 'vms_safety_export_checklist_' . $cid)) . '">' . esc_html__('Print', 'vms') . '</a>';
			echo '</header>';
			if (empty($items)) {
				echo '<p>' . esc_html__('No items.', 'vms') . '</p>';
			} else {
				echo '<ul class="vms-safety-checklist">';
				foreach ($items as $idx => $item) {
					$label = isset($item['label']) ? (string) $item['label'] : '';
					$done = !empty($item['done']);
					echo '<li class="' . esc_attr($done ? 'is-done' : '') . '">';
					echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-safety-inline-form">';
					wp_nonce_field('vms_safety_toggle_checklist_item_' . $cid . '_' . $idx);
					echo '<input type="hidden" name="action" value="vms_safety_toggle_checklist_item" />';
					echo '<input type="hidden" name="checklist_id" value="' . esc_attr((string) $cid) . '" />';
					echo '<input type="hidden" name="item_index" value="' . esc_attr((string) $idx) . '" />';
					echo '<button type="submit" class="button button-small">' . esc_html($done ? __('Mark Open', 'vms') : __('Mark Done', 'vms')) . '</button> ';
					echo '<span>' . esc_html($label) . '</span>';
					echo '</form>';
					echo '</li>';
				}
				echo '</ul>';
			}
			echo '</article>';
		}
	}
}

if (!function_exists('vms_safety_handle_save_checklist_tpl')) {
	function vms_safety_handle_save_checklist_tpl(): void
	{
		if (!vms_safety_user_can_manage()) {
			wp_die(esc_html__('Not allowed.', 'vms'));
		}
		check_admin_referer('vms_safety_save_checklist_tpl');

		$title = sanitize_text_field((string) ($_POST['tpl_title'] ?? ''));
		$items_raw = sanitize_textarea_field((string) ($_POST['tpl_items'] ?? ''));
		if ($title === '' || trim($items_raw) === '') {
			vms_safety_admin_redirect(array('tab' => 'checklists', 'vms_safety_notice' => rawurlencode(__('Template title and items are required.', 'vms')), 'vms_safety_notice_type' => 'error'));
		}

		$tpl_id = wp_insert_post(array(
			'post_type' => 'vms_checklist_tpl',
			'post_status' => 'publish',
			'post_title' => $title,
		), true);
		if (is_wp_error($tpl_id) || !$tpl_id) {
			vms_safety_admin_redirect(array('tab' => 'checklists', 'vms_safety_notice' => rawurlencode(__('Could not save template.', 'vms')), 'vms_safety_notice_type' => 'error'));
		}
		$tpl_id = (int) $tpl_id;

		$items = vms_safety_parse_checklist_lines($items_raw);
		update_post_meta($tpl_id, 'vms_chk_tpl_title', $title);
		update_post_meta($tpl_id, 'vms_chk_tpl_items', $items);
		update_post_meta($tpl_id, 'vms_chk_tpl_applies_to', sanitize_key((string) ($_POST['tpl_applies_to'] ?? 'event')));
		update_post_meta($tpl_id, 'vms_chk_tpl_autocreate_on_event_status', sanitize_key((string) ($_POST['tpl_autocreate_on_event_status'] ?? '')));

		vms_safety_audit_log('checklist_created', array('template_id' => $tpl_id, 'type' => 'template'));
		vms_safety_admin_redirect(array('tab' => 'checklists', 'vms_safety_notice' => rawurlencode(__('Checklist template saved.', 'vms'))));
	}
}
add_action('admin_post_vms_safety_save_checklist_tpl', 'vms_safety_handle_save_checklist_tpl');

if (!function_exists('vms_safety_handle_create_checklist')) {
	function vms_safety_handle_create_checklist(): void
	{
		if (!vms_safety_user_can_manage()) {
			wp_die(esc_html__('Not allowed.', 'vms'));
		}
		check_admin_referer('vms_safety_create_checklist');

		$template_id = absint($_POST['template_id'] ?? 0);
		$title = sanitize_text_field((string) ($_POST['checklist_title'] ?? ''));
		if ($template_id <= 0 || $title === '') {
			vms_safety_admin_redirect(array('tab' => 'checklists', 'vms_safety_notice' => rawurlencode(__('Checklist title and template are required.', 'vms')), 'vms_safety_notice_type' => 'error'));
		}
		$template = get_post($template_id);
		if (!$template instanceof WP_Post || $template->post_type !== 'vms_checklist_tpl') {
			vms_safety_admin_redirect(array('tab' => 'checklists', 'vms_safety_notice' => rawurlencode(__('Template not found.', 'vms')), 'vms_safety_notice_type' => 'error'));
		}
		$line_items = get_post_meta($template_id, 'vms_chk_tpl_items', true);
		$line_items = is_array($line_items) ? $line_items : array();
		$items = array();
		foreach ($line_items as $idx => $label) {
			$items[] = array(
				'id' => 'item_' . (int) $idx,
				'label' => sanitize_text_field((string) $label),
				'done' => 0,
				'timestamp' => '',
				'user_id' => 0,
			);
		}

		$checklist_id = wp_insert_post(array(
			'post_type' => 'vms_checklist',
			'post_status' => 'publish',
			'post_title' => $title,
		), true);
		if (is_wp_error($checklist_id) || !$checklist_id) {
			vms_safety_admin_redirect(array('tab' => 'checklists', 'vms_safety_notice' => rawurlencode(__('Could not create checklist.', 'vms')), 'vms_safety_notice_type' => 'error'));
		}
		$checklist_id = (int) $checklist_id;

		update_post_meta($checklist_id, 'vms_chk_title', $title);
		update_post_meta($checklist_id, 'vms_chk_items', $items);
		update_post_meta($checklist_id, 'vms_chk_status', 'open');
		update_post_meta($checklist_id, 'vms_chk_event_plan_id', absint($_POST['checklist_event_plan_id'] ?? 0));
		update_post_meta($checklist_id, 'vms_chk_template_id', $template_id);

		if (has_action('vms_safety_checklist_created')) {
			do_action('vms_safety_checklist_created', $checklist_id, $items);
		}

		vms_safety_audit_log('checklist_created', array('checklist_id' => $checklist_id, 'template_id' => $template_id));
		vms_safety_admin_redirect(array('tab' => 'checklists', 'vms_safety_notice' => rawurlencode(__('Checklist created.', 'vms'))));
	}
}
add_action('admin_post_vms_safety_create_checklist', 'vms_safety_handle_create_checklist');

if (!function_exists('vms_safety_handle_toggle_checklist_item')) {
	function vms_safety_handle_toggle_checklist_item(): void
	{
		if (!vms_safety_user_can_manage()) {
			wp_die(esc_html__('Not allowed.', 'vms'));
		}
		$checklist_id = absint($_POST['checklist_id'] ?? 0);
		$item_index = absint($_POST['item_index'] ?? -1);
		check_admin_referer('vms_safety_toggle_checklist_item_' . $checklist_id . '_' . $item_index);
		if ($checklist_id <= 0 || $item_index < 0) {
			vms_safety_admin_redirect(array('tab' => 'checklists', 'vms_safety_notice' => rawurlencode(__('Invalid checklist item.', 'vms')), 'vms_safety_notice_type' => 'error'));
		}

		$items = get_post_meta($checklist_id, 'vms_chk_items', true);
		$items = is_array($items) ? $items : array();
		if (!isset($items[$item_index]) || !is_array($items[$item_index])) {
			vms_safety_admin_redirect(array('tab' => 'checklists', 'vms_safety_notice' => rawurlencode(__('Checklist item not found.', 'vms')), 'vms_safety_notice_type' => 'error'));
		}

		$current_done = !empty($items[$item_index]['done']);
		$items[$item_index]['done'] = $current_done ? 0 : 1;
		$items[$item_index]['timestamp'] = current_time('mysql');
		$items[$item_index]['user_id'] = get_current_user_id();

		$all_done = true;
		foreach ($items as $it) {
			if (empty($it['done'])) {
				$all_done = false;
				break;
			}
		}

		update_post_meta($checklist_id, 'vms_chk_items', $items);
		update_post_meta($checklist_id, 'vms_chk_status', $all_done ? 'complete' : 'open');
		vms_safety_audit_log($all_done ? 'checklist_completed' : 'checklist_item_checked', array('checklist_id' => $checklist_id, 'item_index' => $item_index));

		vms_safety_admin_redirect(array('tab' => 'checklists', 'vms_safety_notice' => rawurlencode(__('Checklist updated.', 'vms'))));
	}
}
add_action('admin_post_vms_safety_toggle_checklist_item', 'vms_safety_handle_toggle_checklist_item');

if (!function_exists('vms_safety_handle_export_checklist')) {
	function vms_safety_handle_export_checklist(): void
	{
		if (!vms_safety_user_can_export()) {
			wp_die(esc_html__('Not allowed.', 'vms'));
		}
		$checklist_id = isset($_GET['checklist_id']) ? absint($_GET['checklist_id']) : 0;
		check_admin_referer('vms_safety_export_checklist_' . $checklist_id);
		$post = $checklist_id > 0 ? get_post($checklist_id) : null;
		if (!$post instanceof WP_Post || $post->post_type !== 'vms_checklist') {
			wp_die(esc_html__('Checklist not found.', 'vms'));
		}

		$items = get_post_meta($checklist_id, 'vms_chk_items', true);
		$items = is_array($items) ? $items : array();
		$html = '<h1>' . esc_html(get_the_title($checklist_id)) . '</h1>';
		$html .= '<p class="muted">' . esc_html__('Operational checklist completion report.', 'vms') . '</p>';
		$html .= '<div class="block"><strong>Status:</strong> ' . esc_html((string) get_post_meta($checklist_id, 'vms_chk_status', true)) . '</div>';
		$html .= '<div class="block"><strong>Items</strong><ol>';
		foreach ($items as $item) {
			$label = isset($item['label']) ? (string) $item['label'] : '';
			$done = !empty($item['done']) ? __('Done', 'vms') : __('Open', 'vms');
			$stamp = isset($item['timestamp']) ? (string) $item['timestamp'] : '';
			$html .= '<li>' . esc_html($label . ' [' . $done . ']' . ($stamp !== '' ? ' - ' . $stamp : '')) . '</li>';
		}
		$html .= '</ol></div>';

		vms_safety_audit_log('checklist_completed', array('checklist_id' => $checklist_id, 'exported' => 1));
		vms_safety_render_export_shell(get_the_title($checklist_id), $html);
		exit;
	}
}
add_action('admin_post_vms_safety_export_checklist', 'vms_safety_handle_export_checklist');

if (!function_exists('vms_safety_render_settings_tab')) {
	function vms_safety_render_settings_tab(): void
	{
		$settings = vms_safety_get_settings();
		echo '<h2>' . esc_html__('Safety Settings', 'vms') . '</h2>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-safety-form">';
		wp_nonce_field('vms_safety_save_settings');
		echo '<input type="hidden" name="action" value="vms_safety_save_settings" />';
		echo '<p><label><input type="checkbox" name="dashboard_cards_enabled" value="1" ' . checked(1, (int) $settings['dashboard_cards_enabled'], false) . ' /> ' . esc_html__('Enable dashboard safety cards', 'vms') . '</label></p>';
		echo '<p><label><input type="checkbox" name="require_submitted_before_export" value="1" ' . checked(1, (int) $settings['require_submitted_before_export'], false) . ' /> ' . esc_html__('Require incident Submitted status before export', 'vms') . '</label></p>';
		echo '<p><label><input type="checkbox" name="enable_checklist_auto_create" value="1" ' . checked(1, (int) $settings['enable_checklist_auto_create'], false) . ' /> ' . esc_html__('Enable checklist auto-create rules', 'vms') . '</label></p>';
		echo '<p class="description">' . esc_html__('File retention policy note: keep private files only as long as needed for operational and legal recordkeeping.', 'vms') . '</p>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__('Save Settings', 'vms') . '</button></p>';
		echo '</form>';

		echo '<h3>' . esc_html__('Recent Activity', 'vms') . '</h3>';
		$activity = vms_safety_recent_activity(15);
		if (empty($activity)) {
			echo '<p>' . esc_html__('No recent safety activity logged yet.', 'vms') . '</p>';
			return;
		}
		echo '<ul class="vms-safety-activity">';
		foreach ($activity as $row) {
			$event = isset($row['event']) ? (string) $row['event'] : 'event';
			$time = isset($row['time']) ? (string) $row['time'] : '';
			$user_id = isset($row['user_id']) ? (int) $row['user_id'] : 0;
			echo '<li><strong>' . esc_html($event) . '</strong> <span class="muted">' . esc_html($time) . ' | user #' . $user_id . '</span></li>';
		}
		echo '</ul>';
	}
}

if (!function_exists('vms_safety_handle_save_settings')) {
	function vms_safety_handle_save_settings(): void
	{
		if (!vms_safety_user_can_manage()) {
			wp_die(esc_html__('Not allowed.', 'vms'));
		}
		check_admin_referer('vms_safety_save_settings');
		vms_safety_update_settings((array) $_POST);
		vms_safety_audit_log('settings_updated', array('module' => 'safety'));
		vms_safety_admin_redirect(array('tab' => 'settings', 'vms_safety_notice' => rawurlencode(__('Settings saved.', 'vms'))));
	}
}
add_action('admin_post_vms_safety_save_settings', 'vms_safety_handle_save_settings');

if (!function_exists('vms_safety_render_help_tab')) {
	function vms_safety_render_help_tab(): void
	{
		echo '<h2 data-vms-tour="safety.help">' . esc_html__('Guided Help Tour', 'vms') . '</h2>';
		echo '<p>' . esc_html__('Use the button below to run the Safety module walkthrough.', 'vms') . '</p>';
		if (function_exists('vms_render_help_button')) {
			echo vms_render_help_button(array(
				'label' => __('Start Safety Tour', 'vms'),
				'tour_id' => 'vms_safety_overview',
				'class' => 'button button-primary',
			));
		}
	}
}
