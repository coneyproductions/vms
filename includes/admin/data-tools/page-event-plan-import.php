<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_event_plan_import_page_slug')) {
	function bvmgr_event_plan_import_page_slug(): string
	{
		return 'vms-import-event-plans';
	}
}

if (!function_exists('bvmgr_event_plan_import_admin_page_url')) {
	function bvmgr_event_plan_import_admin_page_url(array $args = array()): string
	{
		$url = add_query_arg(array('page' => bvmgr_event_plan_import_page_slug()), admin_url('admin.php'));
		if (!empty($args)) {
			$url = add_query_arg($args, $url);
		}
		return $url;
	}
}

if (!function_exists('bvmgr_event_plan_import_query_arg')) {
	function bvmgr_event_plan_import_query_arg(string $key): string
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only import preview state only controls admin display.
		if (!isset($_GET[$key])) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only import preview state is unslashed here and sanitized by the caller.
		return (string) wp_unslash($_GET[$key]);
	}
}

if (!function_exists('bvmgr_event_plan_import_enqueue_assets')) {
	function bvmgr_event_plan_import_enqueue_assets(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page gate for a hidden admin screen.
		$page = (isset($_GET['page']) && !is_array($_GET['page']))
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only page slug gate is unslashed here and sanitized immediately.
			? sanitize_key(wp_unslash((string) $_GET['page']))
			: '';
		if ($page !== bvmgr_event_plan_import_page_slug()) {
			return;
		}

		$version = function_exists('bvmgr_asset_version')
			? bvmgr_asset_version()
			: (defined('BVMGR_VERSION') ? (string) BVMGR_VERSION : '');

		wp_enqueue_script(
			'vms-event-plan-import',
			BVMGR_PLUGIN_URL . 'assets/js/vms-event-plan-import.js',
			array(),
			$version,
			true
		);
	}
}
add_action('admin_enqueue_scripts', 'bvmgr_event_plan_import_enqueue_assets', 50);

if (!function_exists('bvmgr_event_plan_import_register_admin_page')) {
	function bvmgr_event_plan_import_register_admin_page(): void
	{
		add_submenu_page(
			null,
			__('Import Event Plans (CSV)', 'backstage-venue-manager'),
			__('Import Event Plans (CSV)', 'backstage-venue-manager'),
			'manage_options',
			bvmgr_event_plan_import_page_slug(),
			'bvmgr_event_plan_import_render_admin_page'
		);
	}
}
add_action('admin_menu', 'bvmgr_event_plan_import_register_admin_page', 30);

if (!function_exists('bvmgr_event_plan_import_notice_class')) {
	function bvmgr_event_plan_import_notice_class(string $type): string
	{
		$type = sanitize_key($type);
		if ($type === 'error' || $type === 'critical') {
			return 'notice notice-error';
		}
		if ($type === 'warning') {
			return 'notice notice-warning';
		}
		if ($type === 'info') {
			return 'notice notice-info';
		}
		return 'notice notice-success';
	}
}

if (!function_exists('bvmgr_event_plan_import_render_notice')) {
	/**
	 * @param array<string,mixed> $notice
	 */
	function bvmgr_event_plan_import_render_notice(array $notice): void
	{
		if (empty($notice)) {
			return;
		}

		$type = (string) ($notice['type'] ?? 'info');
		$message = (string) ($notice['message'] ?? '');
		if ($message === '') {
			return;
		}

		echo '<div class="' . esc_attr(bvmgr_event_plan_import_notice_class($type)) . ' inline"><p>' . esc_html($message) . '</p></div>';
	}
}

if (!function_exists('bvmgr_event_plan_import_render_intro')) {
	function bvmgr_event_plan_import_render_intro(): void
	{
		echo '<p class="vms-admin-hub-intro">';
		echo esc_html__('Upload a CSV, preview changes, then commit. This importer only writes VMS Event Plan data and does not create or update TEC/Woo records.', 'backstage-venue-manager');
		echo '</p>';
	}
}

if (!function_exists('bvmgr_event_plan_import_rows_payload_error_messages')) {
	/**
	 * @return array<string,string>
	 */
	function bvmgr_event_plan_import_rows_payload_error_messages(): array
	{
		return array(
			'rows_json_missing' => __('Preview rows cache is missing. Please run Preview again.', 'backstage-venue-manager'),
			'rows_json_unsafe' => __('Preview rows cache path is invalid.', 'backstage-venue-manager'),
			'rows_json_too_large' => __('Preview rows cache is too large to validate safely.', 'backstage-venue-manager'),
			'rows_json_empty' => __('Preview rows cache is empty.', 'backstage-venue-manager'),
			'rows_json_invalid' => __('Preview rows cache is not valid JSON.', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('bvmgr_event_plan_import_render_rows_payload_error')) {
	function bvmgr_event_plan_import_render_rows_payload_error(string $error_code): void
	{
		$messages = bvmgr_event_plan_import_rows_payload_error_messages();
		$error_code = sanitize_key($error_code);
		if (!isset($messages[$error_code]) || $messages[$error_code] === '') {
			return;
		}

		echo '<div class="notice notice-error inline"><p>' . esc_html($messages[$error_code]) . '</p></div>';
	}
}

if (!function_exists('bvmgr_event_plan_import_render_summary_cards')) {
	/**
	 * @param array<string,mixed> $summary
	 */
	function bvmgr_event_plan_import_render_summary_cards(array $summary): void
	{
		$cards = array(
			'total_rows' => __('Rows', 'backstage-venue-manager'),
			'create' => __('Create', 'backstage-venue-manager'),
			'update' => __('Update', 'backstage-venue-manager'),
			'skip' => __('Skip', 'backstage-venue-manager'),
			'errors' => __('Errors', 'backstage-venue-manager'),
			'warnings' => __('Warnings', 'backstage-venue-manager'),
		);

		echo '<div class="vms-admin-hub-grid">';
		foreach ($cards as $key => $label) {
			$value = (int) ($summary[$key] ?? 0);
			echo '<div class="vms-admin-hub-card" style="cursor:default;">';
			echo '<strong>' . esc_html($label) . '</strong>';
			echo '<span style="font-size:1.65rem;line-height:1.1;font-weight:700;">' . esc_html((string) $value) . '</span>';
			echo '</div>';
		}
		echo '</div>';
	}
}

if (!function_exists('bvmgr_event_plan_import_render_preview_table')) {
	/**
	 * @param array<int,array<string,mixed>> $rows
	 */
	function bvmgr_event_plan_import_render_preview_table(array $rows, bool $show_selectors = false): void
	{
		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		if ($show_selectors) {
			echo '<th>' . esc_html__('Commit', 'backstage-venue-manager') . '</th>';
		}
		echo '<th>' . esc_html__('Row', 'backstage-venue-manager') . '</th>';
		echo '<th>' . esc_html__('Event Key', 'backstage-venue-manager') . '</th>';
		echo '<th>' . esc_html__('Plan', 'backstage-venue-manager') . '</th>';
		echo '<th>' . esc_html__('Action', 'backstage-venue-manager') . '</th>';
		echo '<th>' . esc_html__('Messages', 'backstage-venue-manager') . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if (empty($rows)) {
			$colspan = $show_selectors ? 6 : 5;
			echo '<tr><td colspan="' . esc_attr((string) $colspan) . '">' . esc_html__('No preview rows are available.', 'backstage-venue-manager') . '</td></tr>';
		} else {
			foreach ($rows as $row) {
				if (!is_array($row)) {
					continue;
				}
				$row_number = (int) ($row['row_number'] ?? 0);
				$event_key = (string) ($row['event_key'] ?? '');
				$plan_id = (int) ($row['plan_id'] ?? 0);
				$action = sanitize_key((string) ($row['action'] ?? ''));
				$messages = isset($row['messages']) && is_array($row['messages']) ? $row['messages'] : array();

				$action_label = $action !== '' ? strtoupper($action) : '';
				$plan_label = $plan_id > 0 ? '#' . $plan_id : '-';
				$is_eligible = in_array($action, array('create', 'update'), true);

				$msg_text = array();
				foreach ($messages as $message) {
					$message = trim((string) $message);
					if ($message !== '') {
						$msg_text[] = $message;
					}
				}

				echo '<tr>';
				if ($show_selectors) {
					if ($is_eligible && $row_number > 0) {
						echo '<td><input type="checkbox" class="vms-epcsv-row-check" name="selected_rows[]" value="' . esc_attr((string) $row_number) . '" checked="checked" /></td>';
					} else {
						echo '<td>-</td>';
					}
				}
				echo '<td>' . esc_html((string) $row_number) . '</td>';
				echo '<td><code>' . esc_html($event_key) . '</code></td>';
				echo '<td>' . esc_html($plan_label) . '</td>';
				echo '<td><strong>' . esc_html($action_label) . '</strong></td>';
				echo '<td>' . esc_html(implode(' | ', $msg_text)) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody>';
		echo '</table>';
	}
}

if (!function_exists('bvmgr_event_plan_import_render_main_content')) {
	/**
	 * @param array<string,mixed> $preview
	 * @param array<string,mixed> $latest_run
	 * @param array<string,mixed> $revertible_run
	 */
	function bvmgr_event_plan_import_render_main_content(array $preview, string $preview_token, array $latest_run, array $revertible_run): void
	{
		echo '<section class="vms-pass-card">';
		echo '<h2>' . esc_html__('Preview Import', 'backstage-venue-manager') . '</h2>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
		wp_nonce_field('vms_event_plan_import_preview');
		echo '<input type="hidden" name="action" value="vms_event_plan_import_preview" />';
		echo '<p><label><strong>' . esc_html__('CSV file', 'backstage-venue-manager') . '</strong><br />';
		echo '<input type="file" name="event_plan_csv_file" accept=".csv,text/csv,text/plain" />';
		echo '</label></p>';

		echo '<p><label>';
		echo '<input type="checkbox" name="auto_create_missing_vendors" value="1" checked="checked" /> ';
		echo esc_html__('Auto-create missing vendors', 'backstage-venue-manager');
		echo '</label></p>';

		echo '<p><label>';
		echo '<input type="checkbox" name="allow_update_locked_plans" value="1" /> ';
		echo esc_html__('Allow updates to Published/Cancelled plans', 'backstage-venue-manager');
		echo '</label></p>';

		echo '<p class="description">';
		echo esc_html__('Required columns: event_key, event_date, venue_name, primary_vendor_name. Preview must run before Commit.', 'backstage-venue-manager');
		echo '</p>';

		echo '<p>';
		echo '<button type="submit" class="button button-primary">' . esc_html__('Preview changes', 'backstage-venue-manager') . '</button> ';
		$sample_url = wp_nonce_url(
			add_query_arg(array('action' => 'vms_event_plan_import_download_sample_csv'), admin_url('admin-post.php')),
			'vms_event_plan_import_download_sample_csv'
		);
		echo '<a class="button" href="' . esc_url($sample_url) . '">' . esc_html__('Download sample CSV', 'backstage-venue-manager') . '</a>';
		echo '</p>';
		echo '</form>';
		echo '</section>';

		if (!empty($preview) && is_array($preview)) {
			$summary = isset($preview['summary']) && is_array($preview['summary']) ? $preview['summary'] : array();
			$total_rows = (int) ($summary['total_rows'] ?? 0);
			$source_name = (string) ($preview['source_csv_name'] ?? '');
			$rows_payload = bvmgr_event_plan_import_read_rows_json((string) ($preview['rows_json_storage_key'] ?? ($preview['rows_json_path'] ?? '')));
			$preview_rows = array();
			if (!is_wp_error($rows_payload)) {
				$rows = isset($rows_payload['rows']) && is_array($rows_payload['rows']) ? $rows_payload['rows'] : array();
				foreach ($rows as $row) {
					if (!is_array($row)) {
						continue;
					}
					$messages = array();
					$errors = isset($row['errors']) && is_array($row['errors']) ? $row['errors'] : array();
					$warnings = isset($row['warnings']) && is_array($row['warnings']) ? $row['warnings'] : array();
					foreach (array_merge($errors, $warnings) as $message) {
						$message = trim((string) $message);
						if ($message !== '') {
							$messages[] = $message;
						}
					}
					$preview_rows[] = array(
						'row_number' => (int) ($row['row_number'] ?? 0),
						'event_key' => (string) ($row['event_key'] ?? ''),
						'plan_id' => (int) ($row['existing_plan_id'] ?? 0),
						'action' => sanitize_key((string) ($row['preview_action'] ?? '')),
						'messages' => $messages,
					);
				}
			}

			echo '<section class="vms-pass-card">';
			echo '<h2>' . esc_html__('Preview Results', 'backstage-venue-manager') . '</h2>';
			echo '<p>';
			echo '<strong>' . esc_html__('Source file:', 'backstage-venue-manager') . '</strong> ';
			echo '<code>' . esc_html($source_name !== '' ? $source_name : __('(unknown)', 'backstage-venue-manager')) . '</code>';
			echo '</p>';
			bvmgr_event_plan_import_render_summary_cards($summary);

			if (is_wp_error($rows_payload)) {
				bvmgr_event_plan_import_render_rows_payload_error((string) $rows_payload->get_error_code());
			}

			$selected_required_message = __('Select at least one eligible row before committing selected rows.', 'backstage-venue-manager');
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="vms-epcsv-commit-form" data-vms-selected-required-message="' . esc_attr($selected_required_message) . '" style="margin-top:16px;">';
			wp_nonce_field('vms_event_plan_import_commit');
			echo '<input type="hidden" name="action" value="vms_event_plan_import_commit" />';
			echo '<input type="hidden" name="preview_token" value="' . esc_attr($preview_token) . '" />';

			echo '<p><strong>' . esc_html__('Commit scope', 'backstage-venue-manager') . '</strong></p>';
			echo '<p>';
			echo '<label style="margin-right:14px;"><input type="radio" name="commit_scope" value="all" checked="checked" /> ' . esc_html__('Commit all eligible rows', 'backstage-venue-manager') . '</label>';
			echo '<label><input type="radio" name="commit_scope" value="selected" /> ' . esc_html__('Commit selected rows only', 'backstage-venue-manager') . '</label>';
			echo '</p>';

			echo '<p class="description">';
			echo esc_html__('Eligible rows are Create/Update rows. Error and Skip rows are never committed.', 'backstage-venue-manager');
			echo '</p>';
			echo '<p>';
			echo '<button type="button" class="button" id="vms-epcsv-select-all">' . esc_html__('Select all eligible', 'backstage-venue-manager') . '</button> ';
			echo '<button type="button" class="button" id="vms-epcsv-clear-all">' . esc_html__('Clear selection', 'backstage-venue-manager') . '</button> ';
			echo '<span class="description">' . esc_html__('Selected rows:', 'backstage-venue-manager') . ' <strong id="vms-epcsv-selected-count">0</strong></span>';
			echo '</p>';

			bvmgr_event_plan_import_render_preview_table($preview_rows, true);

			echo '<p class="vms-pass-actions">';
			echo '<button type="submit" class="button button-primary">' . esc_html__('Commit import', 'backstage-venue-manager') . '</button>';
			echo '</p>';
			echo '</form>';

			$report_url = wp_nonce_url(
				add_query_arg(
					array(
						'action' => 'vms_event_plan_import_download_report_csv',
						'preview_token' => $preview_token,
					),
					admin_url('admin-post.php')
				),
				'vms_event_plan_import_download_report_' . $preview_token
			);
			echo '<p class="vms-pass-actions">';
			echo '<a class="button" href="' . esc_url($report_url) . '">' . esc_html__('Download full report CSV', 'backstage-venue-manager') . '</a>';
			echo '</p>';

			echo '<p class="description">';
			echo esc_html(sprintf(
				/* translators: %d: row count */
				__('Showing all parsed preview rows. Total parsed rows: %d.', 'backstage-venue-manager'),
				$total_rows
			));
			echo '</p>';
			echo '</section>';
		}

		if (!empty($latest_run) && is_array($latest_run)) {
			$run_id = (string) ($latest_run['run_id'] ?? '');
			$run_summary = isset($latest_run['summary']) && is_array($latest_run['summary']) ? $latest_run['summary'] : array();
			$run_time = (string) ($latest_run['created_at_local'] ?? '');
			$run_hash = (string) ($latest_run['source_file_hash'] ?? '');

			echo '<section class="vms-pass-card">';
			echo '<h2>' . esc_html__('Last Import Run', 'backstage-venue-manager') . '</h2>';
			echo '<p><strong>' . esc_html__('Run ID:', 'backstage-venue-manager') . '</strong> <code>' . esc_html($run_id) . '</code></p>';
			if ($run_time !== '') {
				echo '<p><strong>' . esc_html__('Timestamp:', 'backstage-venue-manager') . '</strong> ' . esc_html($run_time) . '</p>';
			}
			if ($run_hash !== '') {
				echo '<p><strong>' . esc_html__('File hash:', 'backstage-venue-manager') . '</strong> <code>' . esc_html($run_hash) . '</code></p>';
			}
			bvmgr_event_plan_import_render_summary_cards($run_summary);
			echo '</section>';
		}

		if (!empty($revertible_run) && is_array($revertible_run)) {
			$revert_run_id = (string) ($revertible_run['run_id'] ?? '');
			echo '<section class="vms-pass-card">';
			echo '<h2>' . esc_html__('Rollback', 'backstage-venue-manager') . '</h2>';
			echo '<p class="description">';
			echo esc_html__('Revert restores only the fields touched by this importer for updated plans in the latest reversible run.', 'backstage-venue-manager');
			echo '</p>';
			echo '<p><strong>' . esc_html__('Revert candidate:', 'backstage-venue-manager') . '</strong> <code>' . esc_html($revert_run_id) . '</code></p>';
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
			wp_nonce_field('vms_event_plan_import_revert_last');
			echo '<input type="hidden" name="action" value="vms_event_plan_import_revert_last" />';
			echo '<button type="submit" class="button button-secondary">' . esc_html__('Revert last import', 'backstage-venue-manager') . '</button>';
			echo '</form>';
			echo '</section>';
		}
	}
}

if (!function_exists('bvmgr_event_plan_import_render_admin_page')) {
	function bvmgr_event_plan_import_render_admin_page(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions.', 'backstage-venue-manager'));
		}

		$preview_token = sanitize_key(bvmgr_event_plan_import_query_arg('preview_token'));
		$preview = ($preview_token !== '') ? bvmgr_event_plan_import_get_preview_payload($preview_token) : array();
		$notice = bvmgr_event_plan_import_pop_notice();
		$latest_run = array();
		$runs = bvmgr_event_plan_import_get_audit_runs();
		if (!empty($runs)) {
			$latest_run = is_array($runs[0]) ? $runs[0] : array();
		}
		$revertible_run = bvmgr_event_plan_import_latest_revertible_run();
		$render_notice = static function () use ($notice): void {
			bvmgr_event_plan_import_render_notice($notice);
		};
		$render_intro = static function (): void {
			bvmgr_event_plan_import_render_intro();
		};
		$render_main_content = static function () use ($preview, $preview_token, $latest_run, $revertible_run): void {
			bvmgr_event_plan_import_render_main_content($preview, $preview_token, $latest_run, $revertible_run);
		};
		$render_content = static function () use ($render_intro, $render_main_content): void {
			$render_intro();
			$render_main_content();
		};

		if (function_exists('bvmgr_admin_ui_render_shell')) {
			bvmgr_admin_ui_render_shell(
				array(
					'title' => __('Import Event Plans (CSV)', 'backstage-venue-manager'),
					'subtitle' => __('Preview then commit VMS-only Event Plan updates.', 'backstage-venue-manager'),
					'notices_callback' => $render_notice,
				),
				$render_content
			);
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__('Import Event Plans (CSV)', 'backstage-venue-manager') . '</h1>';
		$render_intro();
		$render_notice();
		$render_main_content();
		echo '</div>';
	}
}
