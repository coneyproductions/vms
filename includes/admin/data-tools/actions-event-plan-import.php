<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_event_plan_import_handle_preview_action')) {
	function vms_event_plan_import_handle_preview_action(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-venue-manager'));
		}

		check_admin_referer('vms_event_plan_import_preview');

		$upload = vms_upload_read_file($_FILES, 'event_plan_csv_file');
		if (is_wp_error($upload)) {
			vms_event_plan_import_set_notice('error', __('Please choose a valid CSV file to preview.', 'backstage-venue-manager'));
			wp_safe_redirect(vms_event_plan_import_admin_page_url());
			exit;
		}

		$validated = vms_validate_uploaded_file(
			$upload,
			array(
				'allowed_mimes' => vms_event_plan_import_allowed_mimes(),
				'max_bytes' => vms_event_plan_import_max_bytes(),
				'type_message' => __('Please choose a valid CSV file to preview.', 'backstage-venue-manager'),
				'empty_message' => __('The uploaded CSV file is empty.', 'backstage-venue-manager'),
				'too_large_message' => __('The uploaded CSV file is too large.', 'backstage-venue-manager'),
				'tmp_invalid_message' => __('The uploaded CSV file could not be verified.', 'backstage-venue-manager'),
			)
		);
		if (is_wp_error($validated)) {
			vms_event_plan_import_set_notice('error', $validated->get_error_message());
			wp_safe_redirect(vms_event_plan_import_admin_page_url());
			exit;
		}

		$token = vms_event_plan_import_make_token();
		$source_name = isset($upload['name']) ? (string) $upload['name'] : '';
		$prepared = vms_event_plan_import_prepare_generated_path('csv', $token, 'source');
		if (is_wp_error($prepared)) {
			vms_event_plan_import_set_notice('error', $prepared->get_error_message());
			wp_safe_redirect(vms_event_plan_import_admin_page_url());
			exit;
		}

		$target_path = (string) ($prepared['path'] ?? '');
		$target_key = (string) ($prepared['storage_key'] ?? '');
		$tmp_name = trim((string) ($validated['tmp_name'] ?? ''));
		if ($target_path === '' || $target_key === '' || $tmp_name === '' || !@move_uploaded_file($tmp_name, $target_path)) {
			vms_event_plan_import_set_notice('error', __('Failed to store uploaded CSV file.', 'backstage-venue-manager'));
			wp_safe_redirect(vms_event_plan_import_admin_page_url());
			exit;
		}
		@chmod($target_path, 0640);

		$options = array(
			'auto_create_missing_vendors' => !empty($_POST['auto_create_missing_vendors']),
			'allow_update_locked_plans' => !empty($_POST['allow_update_locked_plans']),
		);

		$preview = vms_event_plan_import_build_preview_from_csv($target_path, $source_name, $options, $token, $target_key);
		if (is_wp_error($preview)) {
			vms_event_plan_import_delete_stored_file($target_key);
			error_log('[VMS EPCSV] Preview build failed: ' . $preview->get_error_message());
			vms_event_plan_import_set_notice('error', $preview->get_error_message());
			wp_safe_redirect(vms_event_plan_import_admin_page_url());
			exit;
		}

		vms_event_plan_import_set_preview_payload($token, $preview);

		$summary = isset($preview['summary']) && is_array($preview['summary']) ? $preview['summary'] : array();
		$message = sprintf(
			/* translators: 1: create count, 2: update count, 3: skip count, 4: error count */
			__('Preview ready. Create: %1$d, Update: %2$d, Skip: %3$d, Errors: %4$d.', 'backstage-venue-manager'),
			(int) ($summary['create'] ?? 0),
			(int) ($summary['update'] ?? 0),
			(int) ($summary['skip'] ?? 0),
			(int) ($summary['errors'] ?? 0)
		);
		vms_event_plan_import_set_notice('success', $message);

		wp_safe_redirect(vms_event_plan_import_admin_page_url(array('preview_token' => $token)));
		exit;
	}
}
add_action('admin_post_vms_event_plan_import_preview', 'vms_event_plan_import_handle_preview_action');

if (!function_exists('vms_event_plan_import_handle_commit_action')) {
	function vms_event_plan_import_handle_commit_action(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-venue-manager'));
		}

		check_admin_referer('vms_event_plan_import_commit');

		$token = isset($_POST['preview_token']) ? sanitize_key((string) $_POST['preview_token']) : '';
		if ($token === '') {
			vms_event_plan_import_set_notice('error', __('Missing preview token. Please run Preview again.', 'backstage-venue-manager'));
			wp_safe_redirect(vms_event_plan_import_admin_page_url());
			exit;
		}

		$preview = vms_event_plan_import_get_preview_payload($token);
		if (empty($preview)) {
			vms_event_plan_import_set_notice('error', __('Preview has expired or is missing. Please run Preview again.', 'backstage-venue-manager'));
			wp_safe_redirect(vms_event_plan_import_admin_page_url());
			exit;
		}

		$commit_scope_raw = isset($_POST['commit_scope']) ? sanitize_key((string) $_POST['commit_scope']) : 'all';
		$commit_scope = in_array($commit_scope_raw, array('all', 'selected'), true) ? $commit_scope_raw : 'all';
		$selected_rows_raw = isset($_POST['selected_rows']) ? (array) wp_unslash($_POST['selected_rows']) : array();
		$selected_rows = array_values(array_unique(array_filter(array_map('absint', $selected_rows_raw), static function ($row_number): bool {
			return $row_number > 0;
		})));

		if ($commit_scope === 'selected' && empty($selected_rows)) {
			vms_event_plan_import_set_notice('error', __('Select at least one eligible row before committing selected rows.', 'backstage-venue-manager'));
			wp_safe_redirect(vms_event_plan_import_admin_page_url(array('preview_token' => $token)));
			exit;
		}

		$result = vms_event_plan_import_run_commit($preview, array(
			'scope' => $commit_scope,
			'selected_rows' => $selected_rows,
		));
		if (is_wp_error($result)) {
			error_log('[VMS EPCSV] Commit failed: ' . $result->get_error_message());
			vms_event_plan_import_set_notice('error', $result->get_error_message());
			wp_safe_redirect(vms_event_plan_import_admin_page_url(array('preview_token' => $token)));
			exit;
		}

		vms_event_plan_import_delete_preview_payload($token);

		$summary = isset($result['summary']) && is_array($result['summary']) ? $result['summary'] : array();
		$message = sprintf(
			/* translators: 1: create count, 2: update count, 3: skip count, 4: error count */
			__('Import committed. Create: %1$d, Update: %2$d, Skip: %3$d, Errors: %4$d.', 'backstage-venue-manager'),
			(int) ($summary['create'] ?? 0),
			(int) ($summary['update'] ?? 0),
			(int) ($summary['skip'] ?? 0),
			(int) ($summary['errors'] ?? 0)
		);

		$template_not_applied = (int) ($summary['template_not_applied'] ?? 0);
		if ($template_not_applied > 0) {
			$message .= ' ' . sprintf(
				/* translators: %d: plans count */
				__('Ticketing template not applied on %d plan(s).', 'backstage-venue-manager'),
				$template_not_applied
			);
		}
		if ($commit_scope === 'selected') {
			$message .= ' ' . sprintf(
				/* translators: %d: selected rows committed */
				__('Selected rows committed: %d.', 'backstage-venue-manager'),
				(int) ($summary['selected_rows_committed'] ?? 0)
			);
		}

		vms_event_plan_import_set_notice('success', $message);
		wp_safe_redirect(vms_event_plan_import_admin_page_url());
		exit;
	}
}
add_action('admin_post_vms_event_plan_import_commit', 'vms_event_plan_import_handle_commit_action');

if (!function_exists('vms_event_plan_import_handle_download_report_action')) {
	function vms_event_plan_import_handle_download_report_action(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-venue-manager'));
		}

		$token = isset($_REQUEST['preview_token']) ? sanitize_key((string) $_REQUEST['preview_token']) : '';
		if ($token === '') {
			wp_die(esc_html__('Missing preview token.', 'backstage-venue-manager'));
		}

		$nonce = (isset($_REQUEST['_wpnonce']) && !is_array($_REQUEST['_wpnonce']))
			? sanitize_text_field(wp_unslash((string) $_REQUEST['_wpnonce']))
			: '';
		if (!wp_verify_nonce($nonce, 'vms_event_plan_import_download_report_' . $token)) {
			wp_die(esc_html__('Invalid request nonce.', 'backstage-venue-manager'));
		}

		$preview = vms_event_plan_import_get_preview_payload($token);
		if (empty($preview) || !is_array($preview)) {
			wp_die(esc_html__('Preview report is no longer available. Run Preview again.', 'backstage-venue-manager'));
		}

		$path = vms_event_plan_import_storage_path((string) ($preview['report_csv_storage_key'] ?? ($preview['report_csv_path'] ?? '')));
		if ($path === '' || !file_exists($path) || !vms_event_plan_import_path_is_safe($path)) {
			wp_die(esc_html__('Preview report file is missing.', 'backstage-venue-manager'));
		}

		$filename = 'vms-event-plan-import-preview-' . gmdate('Ymd-His') . '.csv';
		if (function_exists('vms_private_files_stream_path')) {
			vms_private_files_stream_path($path, $filename, 'text/csv');
		}

		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
		readfile($path);
		exit;
	}
}
add_action('admin_post_vms_event_plan_import_download_report_csv', 'vms_event_plan_import_handle_download_report_action');

if (!function_exists('vms_event_plan_import_handle_revert_last_action')) {
	function vms_event_plan_import_handle_revert_last_action(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-venue-manager'));
		}

		check_admin_referer('vms_event_plan_import_revert_last');

		$result = vms_event_plan_import_revert_last_run();
		if (is_wp_error($result)) {
			error_log('[VMS EPCSV] Revert failed: ' . $result->get_error_message());
			vms_event_plan_import_set_notice('error', $result->get_error_message());
			wp_safe_redirect(vms_event_plan_import_admin_page_url());
			exit;
		}

		$message = sprintf(
			/* translators: 1: restored count, 2: failed count */
			__('Revert complete. Restored: %1$d, Failed: %2$d.', 'backstage-venue-manager'),
			(int) ($result['restored'] ?? 0),
			(int) ($result['failed'] ?? 0)
		);
		vms_event_plan_import_set_notice('success', $message);
		wp_safe_redirect(vms_event_plan_import_admin_page_url());
		exit;
	}
}
add_action('admin_post_vms_event_plan_import_revert_last', 'vms_event_plan_import_handle_revert_last_action');

if (!function_exists('vms_event_plan_import_handle_download_sample_csv')) {
	function vms_event_plan_import_handle_download_sample_csv(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Access denied.', 'backstage-venue-manager'));
		}

		check_admin_referer('vms_event_plan_import_download_sample_csv');

		nocache_headers();
		$filename = 'vms-event-plan-import-sample-' . gmdate('Ymd-His') . '.csv';
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');

		$out = fopen('php://output', 'wb');
		if (!is_resource($out)) {
			wp_die(esc_html__('Could not open output stream.', 'backstage-venue-manager'));
		}

		fputcsv($out, array(
			'event_key',
			'event_date',
			'venue_name',
			'primary_vendor_name',
			'event_title',
			'start_time',
			'end_time',
			'agenda_text',
			'comp_structure',
			'flat_fee_amount',
			'door_split_percent',
			'attendance_bonus_mode',
			'attendance_bonus_start_count',
			'attendance_bonus_step_size',
			'attendance_bonus_step_bonus',
			'attendance_bonus_per_ticket_rate',
			'attendance_bonus_max_bonus',
			'secondary_vendor_type',
			'secondary_vendor_1',
			'secondary_vendor_2',
			'secondary_vendor_3',
		));

		fputcsv($out, array(
			'2026-03-07-wheelhouse',
			'2026-03-07',
			'Main Venue',
			'Wheelhouse',
			'Wheelhouse',
			'19:00',
			'21:30',
			'Bring a chair.',
			'flat_fee',
			'500',
			'',
			'',
			'',
			'',
			'',
			'',
			'',
			'food_truck',
			'Taco Truck',
			'BBQ Trailer',
			'',
		));

		fputcsv($out, array(
			'2026-03-08-acoustic-night',
			'2026-03-08',
			'Main Venue',
			'Acoustic Duo',
			'Acoustic Night',
			'19:30',
			'22:00',
			'Doors at 6:30pm.',
			'door_split',
			'',
			'70',
			'',
			'',
			'',
			'',
			'',
			'',
			'photographer',
			'Jane Photos',
			'',
			'',
		));

		fputcsv($out, array(
			'2026-03-09-bonus-night',
			'2026-03-09',
			'Main Venue',
			'Bonus Band',
			'Bonus Night',
			'20:00',
			'23:00',
			'Bonus starts after the presale threshold.',
			'attendance_bonus',
			'500',
			'',
			'step',
			'100',
			'50',
			'250',
			'',
			'10000',
			'',
			'',
			'',
			'',
		));

		fclose($out);
		exit;
	}
}
add_action('admin_post_vms_event_plan_import_download_sample_csv', 'vms_event_plan_import_handle_download_sample_csv');
