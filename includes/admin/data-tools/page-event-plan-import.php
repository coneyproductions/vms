<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_event_plan_import_admin_page_url')) {
	function vms_event_plan_import_admin_page_url(array $args = array()): string
	{
		$url = admin_url('admin.php?page=vms-import-event-plans');
		if (!empty($args)) {
			$url = add_query_arg($args, $url);
		}
		return $url;
	}
}

if (!function_exists('vms_event_plan_import_query_arg')) {
	function vms_event_plan_import_query_arg(string $key): string
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only import preview state only controls admin display.
		if (!isset($_GET[$key])) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only import preview state is unslashed here and sanitized by the caller.
		return (string) wp_unslash($_GET[$key]);
	}
}

if (!function_exists('vms_event_plan_import_register_admin_page')) {
	function vms_event_plan_import_register_admin_page(): void
	{
		add_submenu_page(
			null,
			__('Import Event Plans (CSV)', 'vms'),
			__('Import Event Plans (CSV)', 'vms'),
			'manage_options',
			'vms-import-event-plans',
			'vms_event_plan_import_render_admin_page'
		);
	}
}
add_action('admin_menu', 'vms_event_plan_import_register_admin_page', 30);

if (!function_exists('vms_event_plan_import_notice_class')) {
	function vms_event_plan_import_notice_class(string $type): string
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

if (!function_exists('vms_event_plan_import_render_summary_cards')) {
	/**
	 * @param array<string,mixed> $summary
	 */
	function vms_event_plan_import_render_summary_cards(array $summary): void
	{
		$cards = array(
			'total_rows' => __('Rows', 'vms'),
			'create' => __('Create', 'vms'),
			'update' => __('Update', 'vms'),
			'skip' => __('Skip', 'vms'),
			'errors' => __('Errors', 'vms'),
			'warnings' => __('Warnings', 'vms'),
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

if (!function_exists('vms_event_plan_import_render_preview_table')) {
	/**
	 * @param array<int,array<string,mixed>> $rows
	 */
	function vms_event_plan_import_render_preview_table(array $rows, bool $show_selectors = false): void
	{
		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		if ($show_selectors) {
			echo '<th>' . esc_html__('Commit', 'vms') . '</th>';
		}
		echo '<th>' . esc_html__('Row', 'vms') . '</th>';
		echo '<th>' . esc_html__('Event Key', 'vms') . '</th>';
		echo '<th>' . esc_html__('Plan', 'vms') . '</th>';
		echo '<th>' . esc_html__('Action', 'vms') . '</th>';
		echo '<th>' . esc_html__('Messages', 'vms') . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if (empty($rows)) {
			$colspan = $show_selectors ? 6 : 5;
			echo '<tr><td colspan="' . esc_attr((string) $colspan) . '">' . esc_html__('No preview rows are available.', 'vms') . '</td></tr>';
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

if (!function_exists('vms_event_plan_import_render_admin_page')) {
	function vms_event_plan_import_render_admin_page(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions.', 'vms'));
		}

		$preview_token = sanitize_key(vms_event_plan_import_query_arg('preview_token'));
		$preview = ($preview_token !== '') ? vms_event_plan_import_get_preview_payload($preview_token) : array();
		$notice = vms_event_plan_import_pop_notice();
		$latest_run = array();
		$runs = vms_event_plan_import_get_audit_runs();
		if (!empty($runs)) {
			$latest_run = is_array($runs[0]) ? $runs[0] : array();
		}
		$revertible_run = vms_event_plan_import_latest_revertible_run();

		$render_content = static function () use ($notice, $preview, $preview_token, $latest_run, $revertible_run): void {
			echo '<p class="vms-admin-hub-intro">';
			echo esc_html__('Upload a CSV, preview changes, then commit. This importer only writes VMS Event Plan data and does not create or update TEC/Woo records.', 'vms');
			echo '</p>';

			if (!empty($notice) && is_array($notice)) {
				$type = (string) ($notice['type'] ?? 'info');
				$message = (string) ($notice['message'] ?? '');
				if ($message !== '') {
					echo '<div class="' . esc_attr(vms_event_plan_import_notice_class($type)) . ' inline"><p>' . esc_html($message) . '</p></div>';
				}
			}

			echo '<section class="vms-pass-card">';
			echo '<h2>' . esc_html__('Preview Import', 'vms') . '</h2>';
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
			wp_nonce_field('vms_event_plan_import_preview');
			echo '<input type="hidden" name="action" value="vms_event_plan_import_preview" />';
			echo '<p><label><strong>' . esc_html__('CSV file', 'vms') . '</strong><br />';
			echo '<input type="file" name="event_plan_csv_file" accept=".csv,text/csv,text/plain" />';
			echo '</label></p>';

			echo '<p><label>';
			echo '<input type="checkbox" name="auto_create_missing_vendors" value="1" checked="checked" /> ';
			echo esc_html__('Auto-create missing vendors', 'vms');
			echo '</label></p>';

			echo '<p><label>';
			echo '<input type="checkbox" name="allow_update_locked_plans" value="1" /> ';
			echo esc_html__('Allow updates to Published/Cancelled plans', 'vms');
			echo '</label></p>';

			echo '<p class="description">';
			echo esc_html__('Required columns: event_key, event_date, venue_name, primary_vendor_name. Preview must run before Commit.', 'vms');
			echo '</p>';

			echo '<p>';
			echo '<button type="submit" class="button button-primary">' . esc_html__('Preview changes', 'vms') . '</button> ';
			$sample_url = wp_nonce_url(
				add_query_arg(array('action' => 'vms_event_plan_import_download_sample_csv'), admin_url('admin-post.php')),
				'vms_event_plan_import_download_sample_csv'
			);
			echo '<a class="button" href="' . esc_url($sample_url) . '">' . esc_html__('Download sample CSV', 'vms') . '</a>';
			echo '</p>';
			echo '</form>';
			echo '</section>';

			if (!empty($preview) && is_array($preview)) {
				$summary = isset($preview['summary']) && is_array($preview['summary']) ? $preview['summary'] : array();
				$total_rows = (int) ($summary['total_rows'] ?? 0);
				$source_name = (string) ($preview['source_csv_name'] ?? '');
				$rows_payload = vms_event_plan_import_read_rows_json((string) ($preview['rows_json_path'] ?? ''));
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
				echo '<h2>' . esc_html__('Preview Results', 'vms') . '</h2>';
				echo '<p>';
				echo '<strong>' . esc_html__('Source file:', 'vms') . '</strong> ';
				echo '<code>' . esc_html($source_name !== '' ? $source_name : __('(unknown)', 'vms')) . '</code>';
				echo '</p>';
				vms_event_plan_import_render_summary_cards($summary);

				if (is_wp_error($rows_payload)) {
					echo '<div class="notice notice-error inline"><p>' . esc_html($rows_payload->get_error_message()) . '</p></div>';
				}

				echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="vms-epcsv-commit-form" style="margin-top:16px;">';
				wp_nonce_field('vms_event_plan_import_commit');
				echo '<input type="hidden" name="action" value="vms_event_plan_import_commit" />';
				echo '<input type="hidden" name="preview_token" value="' . esc_attr($preview_token) . '" />';

				echo '<p><strong>' . esc_html__('Commit scope', 'vms') . '</strong></p>';
				echo '<p>';
				echo '<label style="margin-right:14px;"><input type="radio" name="commit_scope" value="all" checked="checked" /> ' . esc_html__('Commit all eligible rows', 'vms') . '</label>';
				echo '<label><input type="radio" name="commit_scope" value="selected" /> ' . esc_html__('Commit selected rows only', 'vms') . '</label>';
				echo '</p>';

				echo '<p class="description">';
				echo esc_html__('Eligible rows are Create/Update rows. Error and Skip rows are never committed.', 'vms');
				echo '</p>';
				echo '<p>';
				echo '<button type="button" class="button" id="vms-epcsv-select-all">' . esc_html__('Select all eligible', 'vms') . '</button> ';
				echo '<button type="button" class="button" id="vms-epcsv-clear-all">' . esc_html__('Clear selection', 'vms') . '</button> ';
				echo '<span class="description">' . esc_html__('Selected rows:', 'vms') . ' <strong id="vms-epcsv-selected-count">0</strong></span>';
				echo '</p>';

				vms_event_plan_import_render_preview_table($preview_rows, true);

				echo '<p class="vms-pass-actions">';
				echo '<button type="submit" class="button button-primary">' . esc_html__('Commit import', 'vms') . '</button>';
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
				echo '<a class="button" href="' . esc_url($report_url) . '">' . esc_html__('Download full report CSV', 'vms') . '</a>';
				echo '</p>';

				echo '<p class="description">';
				echo esc_html(sprintf(
					/* translators: %d: row count */
					__('Showing all parsed preview rows. Total parsed rows: %d.', 'vms'),
					$total_rows
				));
				echo '</p>';

				$selected_required_message = __('Select at least one eligible row before committing selected rows.', 'vms');
				echo '<script>(function(){';
				echo 'var form=document.getElementById("vms-epcsv-commit-form");if(!form){return;}';
				echo 'var checks=Array.prototype.slice.call(form.querySelectorAll(".vms-epcsv-row-check"));';
				echo 'var scopeSelected=form.querySelector(\'input[name="commit_scope"][value="selected"]\');';
				echo 'var scopeAll=form.querySelector(\'input[name="commit_scope"][value="all"]\');';
				echo 'var countNode=document.getElementById("vms-epcsv-selected-count");';
				echo 'var btnAll=document.getElementById("vms-epcsv-select-all");';
				echo 'var btnClear=document.getElementById("vms-epcsv-clear-all");';
				echo 'function updateCount(){var c=0;checks.forEach(function(cb){if(cb.checked){c++;}});if(countNode){countNode.textContent=String(c);}return c;}';
				echo 'if(btnAll){btnAll.addEventListener("click",function(){checks.forEach(function(cb){cb.checked=true;});updateCount();});}';
				echo 'if(btnClear){btnClear.addEventListener("click",function(){checks.forEach(function(cb){cb.checked=false;});updateCount();});}';
				echo 'checks.forEach(function(cb){cb.addEventListener("change",updateCount);});';
				echo 'form.addEventListener("submit",function(e){var selectedCount=updateCount();if(scopeSelected&&scopeSelected.checked&&selectedCount===0){e.preventDefault();window.alert(' . wp_json_encode($selected_required_message) . ');return;}if(scopeAll&&scopeAll.checked){return;}});';
				echo 'updateCount();';
				echo '})();</script>';
				echo '</section>';
			}

			if (!empty($latest_run) && is_array($latest_run)) {
				$run_id = (string) ($latest_run['run_id'] ?? '');
				$run_summary = isset($latest_run['summary']) && is_array($latest_run['summary']) ? $latest_run['summary'] : array();
				$run_time = (string) ($latest_run['created_at_local'] ?? '');
				$run_hash = (string) ($latest_run['source_file_hash'] ?? '');

				echo '<section class="vms-pass-card">';
				echo '<h2>' . esc_html__('Last Import Run', 'vms') . '</h2>';
				echo '<p><strong>' . esc_html__('Run ID:', 'vms') . '</strong> <code>' . esc_html($run_id) . '</code></p>';
				if ($run_time !== '') {
					echo '<p><strong>' . esc_html__('Timestamp:', 'vms') . '</strong> ' . esc_html($run_time) . '</p>';
				}
				if ($run_hash !== '') {
					echo '<p><strong>' . esc_html__('File hash:', 'vms') . '</strong> <code>' . esc_html($run_hash) . '</code></p>';
				}
				vms_event_plan_import_render_summary_cards($run_summary);
				echo '</section>';
			}

			if (!empty($revertible_run) && is_array($revertible_run)) {
				$revert_run_id = (string) ($revertible_run['run_id'] ?? '');
				echo '<section class="vms-pass-card">';
				echo '<h2>' . esc_html__('Rollback', 'vms') . '</h2>';
				echo '<p class="description">';
				echo esc_html__('Revert restores only the fields touched by this importer for updated plans in the latest reversible run.', 'vms');
				echo '</p>';
				echo '<p><strong>' . esc_html__('Revert candidate:', 'vms') . '</strong> <code>' . esc_html($revert_run_id) . '</code></p>';
				echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
				wp_nonce_field('vms_event_plan_import_revert_last');
				echo '<input type="hidden" name="action" value="vms_event_plan_import_revert_last" />';
				echo '<button type="submit" class="button button-secondary">' . esc_html__('Revert last import', 'vms') . '</button>';
				echo '</form>';
				echo '</section>';
			}
		};

		if (function_exists('vms_admin_ui_render_shell')) {
			vms_admin_ui_render_shell(
				array(
					'title' => __('Import Event Plans (CSV)', 'vms'),
					'subtitle' => __('Preview then commit VMS-only Event Plan updates.', 'vms'),
				),
				$render_content
			);
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__('Import Event Plans (CSV)', 'vms') . '</h1>';
		$render_content();
		echo '</div>';
	}
}
