<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_admission_admin_should_load')) {
	function bvmgr_admission_admin_should_load(): bool
	{
		if (!is_admin()) {
			return false;
		}
		if (!function_exists('get_current_screen')) {
			return false;
		}
		$screen = get_current_screen();
		if (!is_object($screen)) {
			return false;
		}
		return ($screen->post_type ?? '') === 'vms_event_plan';
	}
}

if (!function_exists('bvmgr_admission_admin_enqueue_assets')) {
	function bvmgr_admission_admin_enqueue_assets(): void
	{
		if (!bvmgr_admission_admin_should_load()) {
			return;
		}

		$post_id = bvmgr_request_read_absint($_GET, 'post'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin asset gating uses the current Event Plan post ID without changing mutation or nonce behavior.
		if ($post_id <= 0 && isset($GLOBALS['post']) && $GLOBALS['post'] instanceof WP_Post) {
			$post_id = (int) $GLOBALS['post']->ID;
		}

		$ver = defined('BVMGR_VERSION') ? BVMGR_VERSION : null;
		wp_enqueue_style(
			'bvmgr-admissions-admin',
			BVMGR_PLUGIN_URL . 'assets/css/vms-admissions-admin.css',
			array('bvmgr-admin'),
			$ver
		);
		wp_enqueue_script(
			'bvmgr-admissions-admin',
			BVMGR_PLUGIN_URL . 'assets/js/vms-admissions-admin.js',
			array(),
			$ver,
			true
		);

		wp_localize_script('bvmgr-admissions-admin', 'BVMGR_ADMISSIONS_ADMIN', array(
			'restUrl' => esc_url_raw(rest_url('vms/v1')),
			'nonce' => wp_create_nonce('wp_rest'),
			'eventPlanId' => $post_id,
			'settings' => bvmgr_admission_settings(),
			'canManage' => current_user_can(bvmgr_admission_manage_capability()) ? 1 : 0,
			'canCheckin' => current_user_can(bvmgr_admission_door_capability()) ? 1 : 0,
			'allowUncheckin' => !empty(bvmgr_admission_settings()['allow_uncheckin']) ? 1 : 0,
			'exportCsvUrl' => wp_nonce_url(
				admin_url('admin-post.php?action=vms_admissions_export_csv&event_plan_id=' . $post_id),
				'bvmgr_admissions_export_csv_' . $post_id
			),
		));
	}
}
add_action('admin_enqueue_scripts', 'bvmgr_admission_admin_enqueue_assets', 30);

if (!function_exists('bvmgr_admission_add_event_plan_metabox')) {
	function bvmgr_admission_add_event_plan_metabox(): void
	{
		add_meta_box(
			'vms_guest_list_comp_admission',
			__('Guest List / Comp Admission', 'backstage-venue-manager'),
			'bvmgr_admission_render_event_plan_metabox',
			'vms_event_plan',
			'normal',
			'default'
		);
	}
}
add_action('add_meta_boxes_vms_event_plan', 'bvmgr_admission_add_event_plan_metabox', 20);

if (!function_exists('bvmgr_admission_render_event_plan_metabox')) {
	function bvmgr_admission_render_event_plan_metabox(WP_Post $post): void
	{
		if (!current_user_can(bvmgr_admission_manage_capability())) {
			echo '<p>' . esc_html__('You do not have permission to manage Guest List entries.', 'backstage-venue-manager') . '</p>';
			return;
		}

		echo '<div class="vms-adm-box" data-event-plan-id="' . esc_attr((string) $post->ID) . '">';
		echo '<p class="vms-adm-row">' . esc_html__('Add and manage comp entries for this event plan.', 'backstage-venue-manager') . '</p>';
		echo '<div class="vms-adm-grid">';
		// Important: do not use HTML5 required attributes inside the Event Plan edit form.
		// Those can block saving the Event Plan even when the Guest List UI is unused.
		echo '<label>' . esc_html__('Guest Name', 'backstage-venue-manager') . '<input type="text" id="vms-adm-guest-name" autocomplete="off"></label>';
		echo '<label>' . esc_html__('Guest Email', 'backstage-venue-manager') . '<input type="text" id="vms-adm-guest-email" autocomplete="email"></label>';
		echo '<label>' . esc_html__('Party Size', 'backstage-venue-manager') . '<input type="text" id="vms-adm-party-size" inputmode="numeric" pattern="[0-9]*" value="1"></label>';
		echo '<label>' . esc_html__('Phone', 'backstage-venue-manager') . '<input type="text" id="vms-adm-phone"></label>';
		echo '<label>' . esc_html__('Notes', 'backstage-venue-manager') . '<input type="text" id="vms-adm-notes"></label>';
		echo '</div>';
		echo '<p class="vms-adm-actions">';
		echo '<button type="button" class="button button-primary" id="vms-adm-add-entry">' . esc_html__('Add Comp Entry', 'backstage-venue-manager') . '</button> ';
		echo '<a class="button" id="vms-adm-export-csv" href="#">' . esc_html__('Export Door List CSV', 'backstage-venue-manager') . '</a>';
		echo '</p>';
		echo '<p id="vms-adm-feedback" class="vms-adm-feedback" aria-live="polite"></p>';
		echo '<div id="vms-adm-summary" class="vms-adm-summary"></div>';
		echo '<div id="vms-adm-list" class="vms-adm-list"></div>';
		if (function_exists('bvmgr_admission_render_vendor_guest_config')) {
			bvmgr_admission_render_vendor_guest_config($post);
		}
		echo '</div>';
	}
}

if (!function_exists('bvmgr_admission_export_csv')) {
	function bvmgr_admission_export_csv(): void
	{
		$event_plan_id = isset($_GET['event_plan_id']) ? absint($_GET['event_plan_id']) : 0;
		if (!current_user_can(bvmgr_admission_manage_capability())) {
			wp_die(esc_html__('Access denied.', 'backstage-venue-manager'));
		}
		if ($event_plan_id <= 0) {
			wp_die(esc_html__('Missing event plan.', 'backstage-venue-manager'));
		}
		$nonce = (isset($_GET['_wpnonce']) && !is_array($_GET['_wpnonce']))
			? sanitize_text_field(wp_unslash((string) $_GET['_wpnonce']))
			: '';
		if (!wp_verify_nonce($nonce, bvmgr_nonce_action_for_value($nonce, 'bvmgr_admissions_export_csv_' . $event_plan_id))) {
			wp_die(esc_html__('Invalid request.', 'backstage-venue-manager'));
		}

		$plan = bvmgr_admission_event_plan_context($event_plan_id);
		if (!$plan) {
			wp_die(esc_html__('Event plan not found.', 'backstage-venue-manager'));
		}

		global $wpdb;
		$table = bvmgr_admission_table_entries();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- CSV exports read the plugin-owned admissions repository with a %i/%d-prepared identifier and filter, and the download must reflect request-fresh event-plan state.
		$rows = $wpdb->get_results($wpdb->prepare(
			'SELECT guest_name, guest_email, party_size, phone, notes, status, source, owner_vendor_id FROM %i WHERE event_plan_id = %d ORDER BY guest_name ASC, id ASC',
			$table,
			$event_plan_id
		), ARRAY_A);

		$filename = 'door-list-' . $event_plan_id . '-' . wp_date('Ymd-His', time(), wp_timezone()) . '.csv';
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=' . $filename);

		$fh = fopen('php://output', 'w');
		if ($fh === false) {
			wp_die(esc_html__('Could not export CSV.', 'backstage-venue-manager'));
		}

		fputcsv($fh, array('Guest Name', 'Guest Email', 'Party Size', 'Phone', 'Notes', 'Status', 'Source', 'Owner Vendor'));
		foreach ((array) $rows as $row) {
			$owner_vendor_id = isset($row['owner_vendor_id']) ? (int) $row['owner_vendor_id'] : 0;
			$owner_vendor_name = $owner_vendor_id > 0 ? (string) get_the_title($owner_vendor_id) : '';
			fputcsv($fh, array(
				(string) ($row['guest_name'] ?? ''),
				(string) ($row['guest_email'] ?? ''),
				(int) ($row['party_size'] ?? 0),
				(string) ($row['phone'] ?? ''),
				(string) ($row['notes'] ?? ''),
				(string) ($row['status'] ?? ''),
				(string) ($row['source'] ?? ''),
				$owner_vendor_name,
			));
		}
		fclose($fh); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the bounded administrator CSV response stream opened on php://output; no local filesystem path or WP_Filesystem replacement applies to this HTTP output handle.

		bvmgr_admission_audit_log($event_plan_id, null, 'export_csv', get_current_user_id(), 'admin', array(
			'row_count' => is_array($rows) ? count($rows) : 0,
		));
		exit;
	}
}
add_action('admin_post_vms_admissions_export_csv', 'bvmgr_admission_export_csv');
