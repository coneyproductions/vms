<?php
defined('ABSPATH') || exit;

function vms_ticket_integrity_register_admin_page(): void
{
	add_submenu_page(
		'vms-dashboard',
		__('Ticket Integrity', 'vms'),
		__('Ticket Integrity', 'vms'),
		'manage_options',
		'vms-ticket-integrity',
		'vms_ticket_integrity_render_admin_page'
	);
}
add_action('admin_menu', 'vms_ticket_integrity_register_admin_page', 45);

function vms_ticket_integrity_admin_enqueue_assets(string $hook): void
{
	unset($hook);

	$page = sanitize_key(wp_unslash((string) ($_GET['page'] ?? '')));
	if (!in_array($page, array('vms-ticket-integrity', 'vms-dashboard'), true)) {
		return;
	}

	wp_enqueue_style(
		'vms-admin-ticket-integrity',
		VMS_PLUGIN_URL . 'assets/css/admin-ticket-integrity.css',
		array(),
		function_exists('vms_asset_version') ? vms_asset_version() : (defined('VMS_VERSION') ? (string) VMS_VERSION : '')
	);

	if ($page !== 'vms-ticket-integrity') {
		return;
	}

	wp_enqueue_script(
		'vms-admin-ticket-integrity',
		VMS_PLUGIN_URL . 'assets/js/admin-ticket-integrity.js',
		array(),
		function_exists('vms_asset_version') ? vms_asset_version() : (defined('VMS_VERSION') ? (string) VMS_VERSION : ''),
		true
	);

	wp_add_inline_script(
		'vms-admin-ticket-integrity',
		'window.vmsTicketIntegrityAdmin = ' . wp_json_encode(
			array(
				'confirmRebuild' => __('Rebuild Ticket Config will attempt a real ticket repair for this Event Plan, may update live ticket mappings, and will log what changed. Continue?', 'vms'),
				'confirmCleanupDuplicates' => __('Resolve Duplicate Legacy Tickets will not delete sold tickets. It will retire unsold duplicate products and, when safer, promote the sold legacy product back into the active map while retiring the newer unsold duplicate. Continue?', 'vms'),
			)
		) . ';',
		'before'
	);
}
add_action('admin_enqueue_scripts', 'vms_ticket_integrity_admin_enqueue_assets', 45);

function vms_ticket_integrity_admin_redirect(string $notice, array $extra = array()): void
{
	$args = array_merge(
		$extra,
		array(
			'tim_notice' => sanitize_key($notice),
		)
	);

	wp_safe_redirect(vms_ticket_integrity_admin_url($args));
	exit;
}

function vms_ticket_integrity_handle_manual_scan(): void
{
	if (!current_user_can('manage_options')) {
		wp_die('Forbidden', 403);
	}

	check_admin_referer('vms_ticket_integrity_run_scan');
	$include_inactive = !empty($_POST['include_inactive']);
	$result = function_exists('vms_ticket_integrity_scan_all')
		? vms_ticket_integrity_scan_all(
			array(
				'trigger' => 'manual_admin',
				'include_inactive' => $include_inactive,
			)
		)
		: array('ok' => false, 'message' => 'scan_helper_missing');

	if (empty($result['ok'])) {
		vms_ticket_integrity_admin_redirect(
			'scan_failed',
			array(
				'detail' => sanitize_text_field((string) ($result['message'] ?? 'scan_failed')),
			)
		);
	}

	$summary = is_array($result['store']['summary'] ?? null) ? $result['store']['summary'] : array();
	vms_ticket_integrity_admin_redirect(
		'scan_complete',
		array(
			'red' => absint($summary['red'] ?? 0),
			'yellow' => absint($summary['yellow'] ?? 0),
			'include_inactive' => $include_inactive ? 1 : 0,
		)
	);
}
add_action('admin_post_vms_ticket_integrity_run_scan', 'vms_ticket_integrity_handle_manual_scan');

function vms_ticket_integrity_handle_event_scan(): void
{
	if (!current_user_can('manage_options')) {
		wp_die('Forbidden', 403);
	}

	check_admin_referer('vms_ticket_integrity_run_event_scan');
	$plan_id = absint($_POST['plan_id'] ?? 0);
	$result = function_exists('vms_ticket_integrity_scan_event_now')
		? vms_ticket_integrity_scan_event_now($plan_id, array('trigger' => 'manual_event_admin'))
		: array('ok' => false, 'message' => 'scan_helper_missing');

	if (empty($result['ok'])) {
		vms_ticket_integrity_admin_redirect(
			'event_scan_failed',
			array(
				'event' => $plan_id,
				'detail' => sanitize_text_field((string) ($result['message'] ?? 'event_scan_failed')),
			)
		);
	}

	vms_ticket_integrity_admin_redirect(
		'event_scan_complete',
		array(
			'event' => $plan_id,
		)
	);
}
add_action('admin_post_vms_ticket_integrity_run_event_scan', 'vms_ticket_integrity_handle_event_scan');

function vms_ticket_integrity_handle_save_settings(): void
{
	if (!current_user_can('manage_options')) {
		wp_die('Forbidden', 403);
	}

	check_admin_referer('vms_ticket_integrity_save_settings');
	$settings = function_exists('vms_ticket_integrity_update_settings')
		? vms_ticket_integrity_update_settings(
			array(
				'nightly_enabled' => !empty($_POST['nightly_enabled']) ? 1 : 0,
				'days_ahead' => absint($_POST['days_ahead'] ?? 120),
				'email_alerts_enabled' => !empty($_POST['email_alerts_enabled']) ? 1 : 0,
				'alert_recipient' => sanitize_email(wp_unslash((string) ($_POST['alert_recipient'] ?? ''))),
				'send_resolved_notifications' => !empty($_POST['send_resolved_notifications']) ? 1 : 0,
				'reminder_interval_hours' => absint($_POST['reminder_interval_hours'] ?? 24),
				'include_yellow_in_email_alerts' => !empty($_POST['include_yellow_in_email_alerts']) ? 1 : 0,
				'daily_report_enabled' => !empty($_POST['daily_report_enabled']) ? 1 : 0,
				'daily_report_recipient' => sanitize_email(wp_unslash((string) ($_POST['daily_report_recipient'] ?? ''))),
				'low_inventory_email_alerts_enabled' => !empty($_POST['low_inventory_email_alerts_enabled']) ? 1 : 0,
				'low_inventory_threshold' => absint($_POST['low_inventory_threshold'] ?? 25),
				'low_inventory_percent_threshold' => absint($_POST['low_inventory_percent_threshold'] ?? 10),
				'critical_inventory_threshold' => absint($_POST['critical_inventory_threshold'] ?? 5),
				'critical_inventory_percent_threshold' => absint($_POST['critical_inventory_percent_threshold'] ?? 3),
				'payment_gateway_health_enabled' => !empty($_POST['payment_gateway_health_enabled']) ? 1 : 0,
				'payment_gateway_health_interval' => sanitize_key(wp_unslash((string) ($_POST['payment_gateway_health_interval'] ?? 'vms_ticket_integrity_fifteen_minutes'))),
			)
		)
		: array();

	if (function_exists('vms_ticket_integrity_maybe_schedule_cron')) {
		vms_ticket_integrity_maybe_schedule_cron();
	}

	if (function_exists('vms_ticket_integrity_log_event')) {
		vms_ticket_integrity_log_event(
			'settings_saved',
			__('Ticket integrity settings saved.', 'vms'),
			array(
				'nightly_enabled' => !empty($settings['nightly_enabled']) ? '1' : '0',
				'days_ahead' => absint($settings['days_ahead'] ?? 0),
			)
		);
	}

	vms_ticket_integrity_admin_redirect('settings_saved');
}
add_action('admin_post_vms_ticket_integrity_save_settings', 'vms_ticket_integrity_handle_save_settings');

function vms_ticket_integrity_handle_send_daily_report(): void
{
	if (!current_user_can('manage_options')) {
		wp_die('Forbidden', 403);
	}

	check_admin_referer('vms_ticket_integrity_send_daily_report');
	if (!function_exists('vms_ticket_integrity_send_state_of_range_report')) {
		vms_ticket_integrity_admin_redirect('daily_report_failed', array('detail' => __('Daily report helper is unavailable.', 'vms')));
	}

	$result = vms_ticket_integrity_send_state_of_range_report('manual');
	if (!empty($result['ok'])) {
		vms_ticket_integrity_admin_redirect('daily_report_sent');
	}

	$detail = sanitize_text_field((string) ($result['message'] ?? __('The daily report could not be sent.', 'vms')));
	vms_ticket_integrity_admin_redirect('daily_report_failed', array('detail' => $detail));
}
add_action('admin_post_vms_ticket_integrity_send_daily_report', 'vms_ticket_integrity_handle_send_daily_report');

function vms_ticket_integrity_daily_report_preview_transient_key(int $user_id = 0): string
{
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();
	return 'vms_ticket_integrity_daily_report_preview_' . absint($user_id);
}

function vms_ticket_integrity_store_daily_report_preview(array $preview): void
{
	set_transient(
		vms_ticket_integrity_daily_report_preview_transient_key(),
		array(
			'subject' => sanitize_text_field((string) ($preview['subject'] ?? '')),
			'body' => (string) ($preview['body'] ?? ''),
			'recipient' => sanitize_email((string) ($preview['recipient'] ?? '')),
			'mode' => sanitize_key((string) ($preview['mode'] ?? 'preview')),
			'generated_at_gmt' => absint($preview['generated_at_gmt'] ?? time()),
		),
		15 * MINUTE_IN_SECONDS
	);
}

function vms_ticket_integrity_get_daily_report_preview(): array
{
	$preview = get_transient(vms_ticket_integrity_daily_report_preview_transient_key());
	return is_array($preview) ? $preview : array();
}

function vms_ticket_integrity_handle_daily_report_preview(): void
{
	if (!current_user_can('manage_options')) {
		wp_die('Forbidden', 403);
	}

	check_admin_referer('vms_ticket_integrity_preview_daily_report');
	if (!function_exists('vms_ticket_integrity_send_state_of_range_report')) {
		vms_ticket_integrity_admin_redirect('daily_report_failed', array('detail' => __('Daily report helper is unavailable.', 'vms')));
	}

	$result = vms_ticket_integrity_send_state_of_range_report(
		'manual',
		array(
			'dry_run' => true,
			'mode' => 'preview',
		)
	);
	if (empty($result['ok'])) {
		$detail = sanitize_text_field((string) ($result['message'] ?? __('The daily report preview could not be rendered.', 'vms')));
		vms_ticket_integrity_admin_redirect('daily_report_failed', array('detail' => $detail));
	}

	$email = is_array($result['email'] ?? null) ? $result['email'] : array();
	vms_ticket_integrity_store_daily_report_preview(
		array(
			'subject' => (string) ($email['subject'] ?? ''),
			'body' => (string) ($email['body'] ?? ''),
			'recipient' => function_exists('vms_ticket_integrity_daily_report_recipient') ? vms_ticket_integrity_daily_report_recipient() : '',
			'mode' => 'preview',
			'generated_at_gmt' => time(),
		)
	);

	vms_ticket_integrity_admin_redirect('daily_report_preview_ready');
}
add_action('admin_post_vms_ticket_integrity_preview_daily_report', 'vms_ticket_integrity_handle_daily_report_preview');

function vms_ticket_integrity_handle_daily_report_dry_run(): void
{
	if (!current_user_can('manage_options')) {
		wp_die('Forbidden', 403);
	}

	check_admin_referer('vms_ticket_integrity_dry_run_daily_report');
	if (!function_exists('vms_ticket_integrity_send_state_of_range_report')) {
		vms_ticket_integrity_admin_redirect('daily_report_failed', array('detail' => __('Daily report helper is unavailable.', 'vms')));
	}

	$result = vms_ticket_integrity_send_state_of_range_report(
		'manual',
		array(
			'dry_run' => true,
			'mode' => 'admin_dry_run',
		)
	);
	if (!empty($result['ok'])) {
		vms_ticket_integrity_admin_redirect('daily_report_dry_run_ready');
	}

	$detail = sanitize_text_field((string) ($result['message'] ?? __('The daily report dry run failed.', 'vms')));
	vms_ticket_integrity_admin_redirect('daily_report_failed', array('detail' => $detail));
}
add_action('admin_post_vms_ticket_integrity_dry_run_daily_report', 'vms_ticket_integrity_handle_daily_report_dry_run');

function vms_ticket_integrity_handle_send_daily_report_test(): void
{
	if (!current_user_can('manage_options')) {
		wp_die('Forbidden', 403);
	}

	check_admin_referer('vms_ticket_integrity_send_daily_report_test');
	if (!function_exists('vms_ticket_integrity_send_state_of_range_report')) {
		vms_ticket_integrity_admin_redirect('daily_report_failed', array('detail' => __('Daily report helper is unavailable.', 'vms')));
	}

	$recipient = sanitize_email(wp_unslash((string) ($_POST['test_recipient'] ?? '')));
	if ($recipient === '') {
		$recipient = sanitize_email((string) get_option('admin_email', ''));
	}
	if ($recipient === '') {
		vms_ticket_integrity_admin_redirect('daily_report_failed', array('detail' => __('No admin test recipient is configured.', 'vms')));
	}

	$result = vms_ticket_integrity_send_state_of_range_report(
		'manual',
		array(
			'mode' => 'admin_test',
			'recipient' => $recipient,
		)
	);
	if (!empty($result['ok'])) {
		vms_ticket_integrity_admin_redirect('daily_report_test_sent', array('recipient' => $recipient));
	}

	$detail = sanitize_text_field((string) ($result['message'] ?? __('The admin test email could not be sent.', 'vms')));
	vms_ticket_integrity_admin_redirect('daily_report_failed', array('detail' => $detail));
}
add_action('admin_post_vms_ticket_integrity_send_daily_report_test', 'vms_ticket_integrity_handle_send_daily_report_test');

function vms_ticket_integrity_run_rebuild(int $plan_id): array
{
	$plan_id = absint($plan_id);
	if ($plan_id <= 0) {
		return array('ok' => false, 'message' => 'invalid_plan');
	}

	if (function_exists('vms_ticket_integrity_repair_event')) {
		return vms_ticket_integrity_repair_event($plan_id);
	}

	return array('ok' => false, 'message' => 'repair_helper_missing');
}

function vms_ticket_integrity_handle_rebuild(): void
{
	if (!current_user_can('manage_options')) {
		wp_die('Forbidden', 403);
	}

	check_admin_referer('vms_ticket_integrity_rebuild');
	$plan_id = absint($_POST['plan_id'] ?? 0);

	if (function_exists('vms_ticket_integrity_log_event')) {
		vms_ticket_integrity_log_event(
			'repair_started',
			__('Ticket integrity rebuild started.', 'vms'),
			array('plan_id' => $plan_id)
		);
	}

	$result = vms_ticket_integrity_run_rebuild($plan_id);
	if (empty($result['ok'])) {
		if (function_exists('vms_ticket_integrity_log_event')) {
			vms_ticket_integrity_log_event(
				'repair_failed',
				__('Ticket integrity rebuild failed.', 'vms'),
				array(
					'plan_id' => $plan_id,
					'message' => (string) ($result['message'] ?? 'repair_failed'),
				)
			);
		}

		vms_ticket_integrity_admin_redirect(
			'rebuild_failed',
			array(
				'event' => $plan_id,
				'detail' => sanitize_text_field((string) ($result['message'] ?? 'rebuild_failed')),
			)
		);
	}

	if (function_exists('vms_ticket_integrity_scan_event_now')) {
		vms_ticket_integrity_scan_event_now($plan_id, array('trigger' => 'rebuild_followup'));
	}

	$repair_status = sanitize_key((string) ($result['repair_status'] ?? 'repaired'));
	$summary_text = sanitize_text_field((string) ($result['summary_text'] ?? ''));
	$notice = 'rebuild_complete';
	$log_type = 'repair_completed';
	$log_message = __('Ticket integrity rebuild completed.', 'vms');

	switch ($repair_status) {
		case 'no_changes':
			$notice = 'rebuild_no_change';
			$log_type = 'repair_no_changes';
			$log_message = __('Ticket integrity rebuild made no mapping changes.', 'vms');
			break;
		case 'partial_changes':
			$notice = 'rebuild_partial';
			$log_type = 'repair_partial_changes';
			$log_message = __('Ticket integrity rebuild made partial changes.', 'vms');
			break;
		case 'partial':
			$notice = 'rebuild_partial';
			$log_type = 'repair_partial';
			$log_message = __('Ticket integrity rebuild made changes, but unresolved conflicts remain.', 'vms');
			break;
		case 'blocked':
			$notice = 'rebuild_blocked';
			$log_type = 'repair_blocked';
			$log_message = __('Ticket integrity rebuild could not proceed safely.', 'vms');
			break;
	}

	if (function_exists('vms_ticket_integrity_log_event')) {
		vms_ticket_integrity_log_event(
			$log_type,
			$log_message,
			array(
				'plan_id' => $plan_id,
				'repair_status' => $repair_status,
				'summary' => $summary_text,
			)
		);
	}

	vms_ticket_integrity_admin_redirect(
		$notice,
		array(
			'event' => $plan_id,
			'detail' => $summary_text,
		)
	);
}
add_action('admin_post_vms_ticket_integrity_rebuild', 'vms_ticket_integrity_handle_rebuild');


function vms_ticket_integrity_find_ticket_config_row(array $context, string $ticket_key, string $title_token = ''): array
{
	$tickets = is_array($context['cfg']['tickets'] ?? null) ? array_values((array) $context['cfg']['tickets']) : array();
	$title_token = $title_token !== '' ? $title_token : vms_ticket_integrity_normalize_title_token((string) ($ticket_key ?: ''));
	foreach ($tickets as $index => $ticket_row) {
		if (!is_array($ticket_row)) {
			continue;
		}

		$enabled = array_key_exists('enabled', $ticket_row) ? !empty($ticket_row['enabled']) : true;
		if (!$enabled) {
			continue;
		}

		$row_ticket_key = sanitize_key((string) ($ticket_row['ticket_key'] ?? $ticket_row['key'] ?? ''));
		if ($row_ticket_key === '') {
			$row_ticket_key = 'ticket_' . (string) $index;
		}

		if ($ticket_key !== '' && $row_ticket_key === $ticket_key) {
			return $ticket_row;
		}

		$row_title_token = vms_ticket_integrity_normalize_title_token((string) ($ticket_row['title'] ?? $row_ticket_key));
		if ($title_token !== '' && $row_title_token === $title_token) {
			return $ticket_row;
		}
	}

	return array();
}

function vms_ticket_integrity_build_duplicate_cleanup_plan(int $plan_id): array
{
	if (function_exists('vms_ticket_integrity_duplicate_cleanup_build_plan')) {
		return vms_ticket_integrity_duplicate_cleanup_build_plan($plan_id);
	}

	return array('ok' => false, 'message' => 'duplicate_cleanup_helper_missing');
}

function vms_ticket_integrity_resolve_duplicate_legacy_products(int $plan_id): array
{
	if (function_exists('vms_ticket_integrity_duplicate_cleanup_run')) {
		return vms_ticket_integrity_duplicate_cleanup_run($plan_id, array('source_function' => 'vms_ticket_integrity_handle_duplicate_cleanup'));
	}

	return array('ok' => false, 'message' => 'duplicate_cleanup_helper_missing');
}

function vms_ticket_integrity_handle_duplicate_cleanup(): void
{
	if (!current_user_can('manage_options')) {
		wp_die('Forbidden', 403);
	}

	check_admin_referer('vms_ticket_integrity_cleanup_duplicates');
	$plan_id = absint($_POST['plan_id'] ?? 0);

	if (function_exists('vms_ticket_integrity_log_event')) {
		vms_ticket_integrity_log_event(
			'duplicate_cleanup_started',
			__('Duplicate legacy ticket cleanup started.', 'vms'),
			array('plan_id' => $plan_id)
		);
	}

	$result = vms_ticket_integrity_resolve_duplicate_legacy_products($plan_id);
	if (empty($result['ok']) && ($result['status'] ?? '') !== 'blocked') {
		if (function_exists('vms_ticket_integrity_log_event')) {
			vms_ticket_integrity_log_event(
				'duplicate_cleanup_failed',
				__('Duplicate legacy ticket cleanup failed.', 'vms'),
				array(
					'plan_id' => $plan_id,
					'message' => (string) ($result['message'] ?? 'duplicate_cleanup_failed'),
				)
			);
		}

		vms_ticket_integrity_admin_redirect(
			'duplicate_cleanup_failed',
			array(
				'event' => $plan_id,
				'detail' => sanitize_text_field((string) ($result['message'] ?? 'duplicate_cleanup_failed')),
			)
		);
	}

	if (function_exists('vms_ticket_integrity_scan_event_now')) {
		vms_ticket_integrity_scan_event_now($plan_id, array('trigger' => 'duplicate_cleanup_followup'));
	}

	$status = sanitize_key((string) ($result['status'] ?? 'complete'));
	$summary_text = sanitize_text_field((string) ($result['summary_text'] ?? ''));
	$notice = 'duplicate_cleanup_complete';
	$log_type = 'duplicate_cleanup_completed';
	$log_message = __('Duplicate legacy ticket cleanup completed.', 'vms');
	if ($status === 'partial') {
		$notice = 'duplicate_cleanup_partial';
		$log_type = 'duplicate_cleanup_partial';
		$log_message = __('Duplicate legacy ticket cleanup completed with warnings.', 'vms');
	} elseif ($status === 'blocked') {
		$notice = 'duplicate_cleanup_blocked';
		$log_type = 'duplicate_cleanup_blocked';
		$log_message = __('Duplicate legacy ticket cleanup was blocked for one or more sold paths.', 'vms');
	}

	if (function_exists('vms_ticket_integrity_log_event')) {
		vms_ticket_integrity_log_event(
			$log_type,
			$log_message,
			array(
				'plan_id' => $plan_id,
				'summary' => $summary_text,
				'adopted' => count((array) ($result['adopted'] ?? array())),
				'retired' => count((array) ($result['retired'] ?? array())),
			)
		);
	}

	vms_ticket_integrity_admin_redirect(
		$notice,
		array(
			'event' => $plan_id,
			'detail' => $summary_text,
		)
	);
}
add_action('admin_post_vms_ticket_integrity_cleanup_duplicates', 'vms_ticket_integrity_handle_duplicate_cleanup');

function vms_ticket_integrity_report_text_value($value): string
{
	if ($value === '' || $value === null) {
		return '—';
	}

	if (function_exists('vms_ticket_inventory_forensics_display_quantity')) {
		return (string) vms_ticket_inventory_forensics_display_quantity($value);
	}

	return is_scalar($value) ? (string) $value : '—';
}

function vms_ticket_integrity_build_event_report_markdown(array $event): string
{
	$lines = array();
	$generated_at = function_exists('wp_date')
		? wp_date('Y-m-d H:i:s T', time(), wp_timezone())
		: date('Y-m-d H:i:s');
	$lines[] = '# ' . trim((string) ($event['event_title'] ?? __('Ticket Integrity Report', 'vms')));
	$lines[] = '';
	$lines[] = '- Generated: ' . $generated_at;
	$lines[] = '- Plan ID: ' . absint($event['plan_id'] ?? 0);
	$lines[] = '- TEC Event ID: ' . absint($event['tec_event_id'] ?? 0);
	$lines[] = '- Status: ' . vms_ticket_integrity_status_label((string) ($event['status'] ?? 'unknown'));
	$lines[] = '- Origin: ' . (function_exists('vms_ticket_mutation_audit_origin_label') ? vms_ticket_mutation_audit_origin_label((string) ($event['origin_classification'] ?? '')) : (string) ($event['origin_classification'] ?? 'unknown'));
	$lines[] = '- Summary: ' . trim((string) ($event['issue_summary'] ?? __('No issues detected.', 'vms')));
	$lines[] = '';

	$issues = array_values((array) ($event['issues'] ?? array()));
	$open_issues = function_exists('vms_ticket_integrity_open_issues') ? vms_ticket_integrity_open_issues($issues) : $issues;
	$lines[] = '## Open Issues';
	if (empty($open_issues)) {
		$lines[] = '- None';
	} else {
		foreach ($open_issues as $issue) {
			if (!is_array($issue)) {
				continue;
			}
			$headline = sprintf(
				'%s: %s',
				vms_ticket_integrity_status_label((string) ($issue['severity'] ?? 'unknown')),
				(string) ($issue['title'] ?? __('Issue', 'vms'))
			);
			$lines[] = '- ' . $headline;
			$details = trim((string) ($issue['details'] ?? ''));
			if ($details !== '') {
				$lines[] = '  ' . $details;
			}
		}
	}
	$lines[] = '';

	$mutation = is_array($event['mutation_diagnostics'] ?? null) ? $event['mutation_diagnostics'] : array();
	$inventory = is_array($event['inventory_diagnostics'] ?? null) ? $event['inventory_diagnostics'] : array();
	$repair = is_array($event['repair_diagnostics'] ?? null) ? $event['repair_diagnostics'] : array();
	$lines[] = '## Diagnostics';
	$lines[] = '- Public Ticket Path: ' . (!empty($mutation['public_path_healthy']) ? __('Healthy', 'vms') : __('Needs review', 'vms'));
	$lines[] = '- Last Mapping Change: ' . (!empty($mutation['latest_mutation']) ? vms_ticket_integrity_format_datetime(absint($mutation['latest_mutation']['timestamp_gmt'] ?? 0)) : 'No change log yet');
	$lines[] = '- Last Inventory Change: ' . (!empty($inventory['latest_inventory_mutation']) ? vms_ticket_integrity_format_datetime(absint($inventory['latest_inventory_mutation']['timestamp_gmt'] ?? 0)) : 'No change log yet');
	$lines[] = '- Last Rebuild: ' . trim((string) ($repair['repair_status_label'] ?? __('No rebuild attempt logged yet', 'vms')));
	$lines[] = '- Repeated Drift: ' . (!empty($mutation['repeated_drift']['flagged']) || !empty($inventory['repeated_inventory_drift']['flagged']) ? __('Detected', 'vms') : __('Not detected', 'vms'));
	$lines[] = '- Zero-Available Mismatch: ' . (!empty($inventory['zero_available_mismatch']) ? __('Detected', 'vms') : __('Not detected', 'vms'));
	$lines[] = '- Woo Primary Mismatch: ' . (!empty($inventory['woo_primary_mismatch']) ? __('Detected', 'vms') : __('Not detected', 'vms'));
	$lines[] = '- TEC Follow-up Required: ' . (!empty($inventory['tec_followup_required']) ? __('Detected', 'vms') : __('Not detected', 'vms'));
	$lines[] = '- Woo Re-Corruption: ' . (!empty($inventory['woo_recorruption_detected']) ? __('Detected', 'vms') : __('Not detected', 'vms'));
	$lines[] = '- Likely Pattern: ' . trim((string) ($inventory['suspected_cause_label'] ?? __('Healthy / not flagged', 'vms')));
	$writer_suspect = is_array($inventory['upstream_writer_suspect'] ?? null) ? $inventory['upstream_writer_suspect'] : array();
	if (!empty($writer_suspect)) {
		$source_text = trim((string) ($writer_suspect['source_function'] ?? $writer_suspect['source_hook'] ?? ''));
		if ($source_text !== '') {
			$lines[] = '- Suspected Upstream Writer: ' . $source_text;
		}
		$reason_text = trim((string) ($writer_suspect['reason_text'] ?? ''));
		if ($reason_text !== '') {
			$lines[] = '- Last Conflicting Woo Write Reason: ' . $reason_text;
		}
	}
	$recommended_action = trim((string) ($inventory['recommended_action'] ?? $mutation['recommended_action'] ?? ''));
	if ($recommended_action !== '') {
		$lines[] = '- Recommended Next Action: ' . $recommended_action;
	}
	$lines[] = '';

	$lines[] = '## Repair Diagnostics';
	if (empty($repair)) {
		$lines[] = '- No saved rebuild diagnostics yet.';
	} else {
		$lines[] = '- Result: ' . trim((string) ($repair['repair_status_label'] ?? __('Unknown', 'vms')));
		$lines[] = '- Summary: ' . trim((string) ($repair['summary_text'] ?? ''));
		$lines[] = '- Preview Change Count: ' . absint($repair['preview_change_count'] ?? 0);
		$lines[] = '- Remaining Issue Summary: ' . trim((string) ($repair['remaining_issue_summary'] ?? __('No summary stored', 'vms')));
		$lines[] = '- Woo Verification: ' . trim((string) ($repair['woo_verification_label'] ?? __('Not stored', 'vms')));
		$lines[] = '- TEC Verification: ' . trim((string) ($repair['tec_verification_label'] ?? __('Not stored', 'vms')));
		if (!empty($repair['woo_recorruption_detected'])) {
			$lines[] = '- Woo Re-Corruption: ' . __('Detected after repair', 'vms');
		}
		$repair_writer = is_array($repair['upstream_writer_suspect'] ?? null) ? $repair['upstream_writer_suspect'] : array();
		$repair_source = trim((string) ($repair_writer['source_function'] ?? $repair_writer['source_hook'] ?? ''));
		if ($repair_source !== '') {
			$lines[] = '- Suspected Upstream Writer: ' . $repair_source;
		}
		$detail_state = trim((string) ($repair['detail_state_label'] ?? ''));
		if ($detail_state !== '') {
			$lines[] = '- Detailed Outcome: ' . $detail_state;
		}
		foreach ((array) ($repair['role_breakdown'] ?? array()) as $role_key => $role_group) {
			if (!is_array($role_group)) {
				continue;
			}
			$lines[] = '- ' . trim((string) ($role_group['label'] ?? $role_key)) . ': attempted ' . absint($role_group['attempted'] ?? 0) . ', succeeded ' . absint($role_group['succeeded'] ?? 0) . ', skipped ' . absint($role_group['skipped'] ?? 0) . ', no effect ' . absint($role_group['no_effect'] ?? 0) . ', partial ' . absint($role_group['partial'] ?? 0) . ', failed ' . absint($role_group['failed'] ?? 0);
			$lines[] = '  - Branch coverage: entered ' . absint($role_group['branch_entered'] ?? 0) . ', not entered ' . absint($role_group['branch_not_entered'] ?? 0) . ', blocked ' . absint($role_group['branch_blocked'] ?? 0);
			foreach ((array) ($role_group['entries'] ?? array()) as $entry) {
				if (!is_array($entry)) {
					continue;
				}

				$lines[] = '  - ' . trim((string) ($entry['label'] ?? __('Role entry', 'vms'))) . ': action ' . trim((string) ($entry['preview_action'] ?? 'noop')) . ', branch ' . trim((string) ($entry['branch_status_label'] ?? __('Branch not entered', 'vms'))) . ', result ' . trim((string) ($entry['result_label'] ?? __('Unknown', 'vms'))) . ', product #' . absint($entry['product_id'] ?? 0);
				if (!empty($entry['skip_reason_label'])) {
					$lines[] = '    - Skip reason: ' . trim((string) $entry['skip_reason_label']);
				}
				$lines[] = '    - Derivation: ' . trim((string) ($entry['derivation_source_label'] ?? $entry['derivation_source'] ?? __('Unknown', 'vms'))) . ' / Confidence: ' . trim((string) ($entry['confidence_label'] ?? $entry['confidence_level'] ?? __('Unknown', 'vms'))) . ' / Writer branch: ' . trim((string) ($entry['writer_branch_label'] ?? __('Not recorded', 'vms'))) . ' / Result health: ' . trim((string) ($entry['result_health_label'] ?? __('Not recorded', 'vms')));
				if (
					array_key_exists('final_stock_qty', $entry)
					|| trim((string) ($entry['final_stock_status'] ?? '')) !== ''
					|| array_key_exists('final_manage_stock', $entry)
				) {
					$lines[] = '    - Final inventory: stock ' . vms_ticket_integrity_report_text_value($entry['final_stock_qty'] ?? null) . ' / status ' . trim((string) ($entry['final_stock_status'] ?? '—')) . ' / manage stock ' . trim((string) ($entry['final_manage_stock_label'] ?? '—'));
				}
				$reason_text = trim((string) ($entry['reason_text'] ?? ''));
				if ($reason_text !== '') {
					$lines[] = '    - Reason: ' . $reason_text;
				}
			}
		}
		$warnings = array_values(array_filter(array_map('strval', (array) ($repair['warnings'] ?? array()))));
		if (!empty($warnings)) {
			$lines[] = '- Warnings:';
			foreach ($warnings as $warning) {
				$lines[] = '  - ' . $warning;
			}
		}
	}
	$lines[] = '';

	$lines[] = '## Inventory Snapshot';
	$lines[] = '| Ticket | Role | Product ID | VMS Intended | Woo State | TEC State | Agreement | Verification | Woo Stock Qty | Woo Stock Status | Woo Manage Stock | Sold Source | Woo total_sales | Last Woo Write Source | Last Write Reason |';
	$lines[] = '| --- | --- | ---: | --- | --- | --- | --- | --- | ---: | --- | --- | --- | ---: | --- | --- |';
	foreach ((array) ($inventory['ticket_rows'] ?? array()) as $row) {
		if (!is_array($row)) {
			continue;
		}
		$lines[] = '| ' . str_replace('|', '/', (string) ($row['ticket_label'] ?? __('Ticket', 'vms')))
			. ' | ' . str_replace('|', '/', (string) ($row['role_label'] ?? ''))
			. ' | ' . absint($row['product_id'] ?? 0)
			. ' | ' . str_replace('|', '/', (string) ($row['vms_intended_label'] ?? ''))
			. ' | ' . str_replace('|', '/', (string) ($row['woo_sellability_label'] ?? ''))
			. ' | ' . str_replace('|', '/', (string) ($row['tec_sellability_label'] ?? ''))
			. ' | ' . str_replace('|', '/', (string) ($row['agreement_label'] ?? ''))
			. ' | ' . str_replace('|', '/', (string) ($row['verification_result_label'] ?? ''))
			. ' | ' . vms_ticket_integrity_report_text_value($row['stock_qty'] ?? null)
			. ' | ' . str_replace('|', '/', (string) ($row['stock_status'] ?? ''))
			. ' | ' . str_replace('|', '/', (string) ($row['manage_stock_label'] ?? ''))
			. ' | ' . str_replace('|', '/', (string) ($row['sold_source_label'] ?? ''))
			. ' | ' . vms_ticket_integrity_report_text_value($row['woo_total_sales'] ?? null)
			. ' | ' . str_replace('|', '/', (string) ($row['last_change_source'] ?? ''))
			. ' | ' . str_replace('|', '/', (string) ($row['last_write_reason'] ?? ''))
			. ' |';
	}
	$lines[] = '';

	$comparison_rows = array_values((array) ($inventory['healthy_comparison']['rows'] ?? array()));
	$lines[] = '## Healthy vs Broken Comparison';
	if (empty($comparison_rows)) {
		$lines[] = '- No matched healthy baseline diff is currently stored.';
	} else {
		foreach ($comparison_rows as $comparison_row) {
			if (!is_array($comparison_row)) {
				continue;
			}
			$lines[] = '- ' . (string) ($comparison_row['label'] ?? __('Ticket', 'vms'));
			foreach ((array) ($comparison_row['differences'] ?? array()) as $difference) {
				if (!is_array($difference)) {
					continue;
				}
				$lines[] = '  - ' . (string) ($difference['label'] ?? __('Field', 'vms')) . ': broken ' . (string) ($difference['broken'] ?? '—') . ' / healthy ' . (string) ($difference['healthy'] ?? '—');
			}
		}
	}
	$lines[] = '';

	$lines[] = '## Recent Inventory Mutations';
	$recent_mutations = array_values((array) ($inventory['recent_inventory_mutations'] ?? array()));
	if (empty($recent_mutations)) {
		$lines[] = '- None recorded';
	} else {
		foreach (array_slice($recent_mutations, 0, 12) as $mutation_row) {
			if (!is_array($mutation_row)) {
				continue;
			}
			$details = is_array($mutation_row['details'] ?? null) ? $mutation_row['details'] : array();
			$result_health = sanitize_key((string) ($mutation_row['result_health'] ?? ''));
			$result_health_label = $result_health !== '' && function_exists('vms_ticketing_v2_inventory_result_health_label')
				? (string) vms_ticketing_v2_inventory_result_health_label($result_health)
				: trim((string) ($details['result_health'] ?? ''));
			$lines[] = '- ' . trim((string) ($mutation_row['change_type_label'] ?? __('Inventory mutation', 'vms'))) . ' / ' . vms_ticket_integrity_format_datetime(absint($mutation_row['timestamp_gmt'] ?? 0));
			$lines[] = '  - Source: ' . trim((string) ($mutation_row['source_function'] ?? $mutation_row['source_hook'] ?? ''));
			$lines[] = '  - Derivation: ' . trim((string) ($mutation_row['derivation_source_label'] ?? $mutation_row['derivation_source'] ?? __('Unknown', 'vms'))) . ' / Writer branch: ' . trim((string) ($details['writer_branch'] ?? $mutation_row['writer_branch'] ?? __('Not recorded', 'vms')));
			if ($result_health_label !== '') {
				$lines[] = '  - Result health: ' . $result_health_label;
			}
			$lines[] = '  - Stock qty: ' . vms_ticket_integrity_report_text_value($details['old_stock_qty'] ?? null) . ' -> ' . vms_ticket_integrity_report_text_value($details['new_stock_qty'] ?? null);
			$lines[] = '  - Stock status: ' . trim((string) ($details['old_stock_status'] ?? '—')) . ' -> ' . trim((string) ($details['new_stock_status'] ?? '—'));
			$lines[] = '  - Manage stock: ' . trim((string) ($details['old_manage_stock'] ?? '—')) . ' -> ' . trim((string) ($details['new_manage_stock'] ?? '—'));
			$lines[] = '  - Reason: ' . trim((string) ($mutation_row['reason_text'] ?? $mutation_row['summary_text'] ?? ''));
		}
	}

	return implode("\n", $lines) . "\n";
}

function vms_ticket_integrity_handle_export_report(): void
{
	if (!current_user_can('manage_options')) {
		wp_die('Forbidden', 403);
	}

	check_admin_referer('vms_ticket_integrity_export_report');
	$plan_id = absint($_POST['plan_id'] ?? 0);
	$event = function_exists('vms_ticket_integrity_scan_event_record')
		? vms_ticket_integrity_scan_event_record($plan_id, array('trigger' => 'manual_export'))
		: array();

	if ($plan_id <= 0 || empty($event)) {
		vms_ticket_integrity_admin_redirect(
			'event_scan_failed',
			array(
				'event' => $plan_id,
				'detail' => sanitize_text_field(__('Could not build diagnostics export for this event.', 'vms')),
			)
		);
	}

	$filename = 'ticket-integrity-report-plan-' . $plan_id . '-' . (function_exists('wp_date') ? wp_date('Ymd-His', time(), wp_timezone()) : date('Ymd-His')) . '.md';
	nocache_headers();
	header('Content-Type: text/markdown; charset=' . get_option('blog_charset'));
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	echo vms_ticket_integrity_build_event_report_markdown($event);
	exit;
}
add_action('admin_post_vms_ticket_integrity_export_report', 'vms_ticket_integrity_handle_export_report');

function vms_ticket_integrity_render_notice_from_query(): void
{
	$notice = sanitize_key(wp_unslash((string) ($_GET['tim_notice'] ?? '')));
	$detail = sanitize_text_field(wp_unslash((string) ($_GET['detail'] ?? '')));
	$notice_recipient = sanitize_email(wp_unslash((string) ($_GET['recipient'] ?? '')));

	if ($notice === '') {
		return;
	}

	$class = 'notice-info';
	$message = '';
	switch ($notice) {
		case 'scan_complete':
			$class = 'notice-success';
			$message = sprintf(
				/* translators: 1: red count, 2: yellow count */
				__('Ticket integrity scan completed. Red: %1$d. Yellow: %2$d.', 'vms'),
				absint(wp_unslash((string) ($_GET['red'] ?? 0))),
				absint(wp_unslash((string) ($_GET['yellow'] ?? 0)))
			);
			break;
		case 'event_scan_complete':
			$class = 'notice-success';
			$message = __('Event integrity scan completed.', 'vms');
			break;
		case 'settings_saved':
			$class = 'notice-success';
			$message = __('Ticket Integrity settings saved.', 'vms');
			break;
		case 'daily_report_sent':
			$class = 'notice-success';
			$message = __('State of the Range email sent.', 'vms');
			break;
		case 'daily_report_preview_ready':
			$class = 'notice-success';
			$message = __('State of the Range preview rendered successfully.', 'vms');
			break;
		case 'daily_report_dry_run_ready':
			$class = 'notice-success';
			$message = __('State of the Range dry-run diagnostic completed without sending email.', 'vms');
			break;
		case 'daily_report_test_sent':
			$class = 'notice-success';
			$message = __('State of the Range admin test email sent.', 'vms');
			if ($notice_recipient !== '') {
				$message .= ' ' . $notice_recipient;
			}
			break;
		case 'daily_report_failed':
			$class = 'notice-error';
			$message = __('State of the Range email failed to send.', 'vms');
			if ($detail !== '') {
				$message .= ' ' . $detail;
			}
			break;
		case 'rebuild_complete':
			$class = 'notice-success';
			$message = __('Repair completed and the event was re-scanned.', 'vms');
			if ($detail !== '') {
				$message .= ' ' . $detail;
			}
			break;
		case 'rebuild_no_change':
			$class = 'notice-info';
			$message = __('No mapping changes were needed and the event was re-scanned.', 'vms');
			if ($detail !== '') {
				$message .= ' ' . $detail;
			}
			break;
		case 'rebuild_partial':
			$class = 'notice-warning';
			$message = __('Repair made changes, but unresolved conflicts still remain.', 'vms');
			if ($detail !== '') {
				$message .= ' ' . $detail;
			}
			break;
		case 'rebuild_blocked':
			$class = 'notice-warning';
			$message = __('Repair could not proceed safely.', 'vms');
			if ($detail !== '') {
				$message .= ' ' . $detail;
			}
			break;
		case 'duplicate_cleanup_complete':
			$class = 'notice-success';
			$message = __('Duplicate legacy ticket cleanup completed and the event was re-scanned.', 'vms');
			if ($detail !== '') {
				$message .= ' ' . $detail;
			}
			break;
		case 'duplicate_cleanup_partial':
			$class = 'notice-warning';
			$message = __('Duplicate legacy ticket cleanup made progress, but warnings remain.', 'vms');
			if ($detail !== '') {
				$message .= ' ' . $detail;
			}
			break;
		case 'duplicate_cleanup_blocked':
			$class = 'notice-warning';
			$message = __('Duplicate legacy ticket cleanup was blocked for one or more sold paths.', 'vms');
			if ($detail !== '') {
				$message .= ' ' . $detail;
			}
			break;
		case 'scan_failed':
		case 'event_scan_failed':
		case 'rebuild_failed':
		case 'duplicate_cleanup_failed':
			$class = 'notice-error';
			$message = __('Ticket Integrity action failed.', 'vms');
			if ($detail !== '') {
				$message .= ' ' . $detail;
			}
			break;
	}

	if ($message === '') {
		return;
	}

	echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
}

function vms_ticket_integrity_render_summary_cards(array $summary, array $last_scan): void
{
	$cards = array(
		array('label' => __('Events Scanned', 'vms'), 'value' => absint($summary['events_scanned'] ?? 0), 'status' => 'neutral'),
		array('label' => __('Green', 'vms'), 'value' => absint($summary['green'] ?? 0), 'status' => 'green'),
		array('label' => __('Yellow', 'vms'), 'value' => absint($summary['yellow'] ?? 0), 'status' => 'yellow'),
		array('label' => __('Red', 'vms'), 'value' => absint($summary['red'] ?? 0), 'status' => 'red'),
		array('label' => __('Informational', 'vms'), 'value' => absint($summary['informational'] ?? 0), 'status' => 'informational'),
		array('label' => __('Last Scan', 'vms'), 'value' => vms_ticket_integrity_format_datetime(absint($last_scan['completed_at_gmt'] ?? 0)), 'status' => 'neutral'),
	);

	echo '<div class="vms-ticket-integrity__cards" data-vms-tour="ticket-integrity.summary">';
	foreach ($cards as $card) {
		echo '<div class="vms-ticket-integrity__card vms-ticket-integrity__card--' . esc_attr($card['status']) . '">';
		echo '<div class="vms-ticket-integrity__card-label">' . esc_html((string) $card['label']) . '</div>';
		echo '<div class="vms-ticket-integrity__card-value">' . esc_html((string) $card['value']) . '</div>';
		echo '</div>';
	}
	echo '</div>';
}

function vms_ticket_integrity_render_payment_gateway_health_panel(array $health): void
{
	$status = sanitize_key((string) ($health['status'] ?? 'unknown'));
	$status_label = function_exists('vms_ticket_integrity_payment_gateway_status_label')
		? vms_ticket_integrity_payment_gateway_status_label($status)
		: strtoupper($status);
	$status_css = function_exists('vms_ticket_integrity_payment_gateway_status_css')
		? vms_ticket_integrity_payment_gateway_status_css($status)
		: 'neutral';
	$checkout = is_array($health['checkout'] ?? null) ? $health['checkout'] : array();
	$square = is_array($health['square'] ?? null) ? $health['square'] : array();
	$apple_pay = is_array($health['apple_pay'] ?? null) ? $health['apple_pay'] : array();
	$site_environment = is_array($health['site_environment'] ?? null) ? $health['site_environment'] : array();
	$incident = is_array($health['incident'] ?? null) ? $health['incident'] : array();
	$last_incident = is_array($health['last_incident'] ?? null) ? $health['last_incident'] : array();

	echo '<section class="vms-ticket-integrity__panel" data-vms-tour="ticket-integrity.payment-gateway-health">';
	echo '<div class="vms-ticket-integrity__payment-health-header">';
	echo '<div>';
	echo '<h2>' . esc_html__('Payment Gateway Health', 'vms') . '</h2>';
	echo '<p class="description">' . esc_html__('Detect whether customers can currently pay at checkout and keep the most recent payment incident visible after recovery.', 'vms') . '</p>';
	echo '</div>';
	echo '<div class="vms-ticket-integrity__payment-health-status">';
	echo '<span class="' . esc_attr(vms_ticket_integrity_status_css_class($status_css)) . '">' . esc_html($status_label) . '</span>';
		/* translators: %s: last payment gateway health check timestamp in the site timezone. */
		echo '<div class="vms-ticket-integrity__payment-health-meta">' . esc_html(sprintf(__('Last checked: %s', 'vms'), (string) ($health['last_checked_local'] ?? __('Never', 'vms')))) . '</div>';
	echo '</div>';
	echo '</div>';

	if (!empty($health['summary'])) {
		echo '<p class="vms-ticket-integrity__payment-health-summary">' . esc_html((string) $health['summary']) . '</p>';
	}

	echo '<div class="vms-ticket-integrity__cards vms-ticket-integrity__payment-health-cards">';
	echo '<div class="vms-ticket-integrity__card vms-ticket-integrity__card--' . esc_attr($status_css) . '">';
	echo '<div class="vms-ticket-integrity__card-label">' . esc_html__('Checkout Methods', 'vms') . '</div>';
	echo '<div class="vms-ticket-integrity__card-value">' . esc_html((string) absint($checkout['available_count'] ?? 0)) . '</div>';
	echo '<div class="vms-ticket-integrity__payment-card-meta">' . esc_html(!empty($checkout['available_gateway_titles']) ? implode(', ', (array) $checkout['available_gateway_titles']) : __('None available', 'vms')) . '</div>';
	echo '</div>';

	echo '<div class="vms-ticket-integrity__card vms-ticket-integrity__card--' . esc_attr(!empty($square['connection_present']) ? 'green' : 'red') . '">';
	echo '<div class="vms-ticket-integrity__card-label">' . esc_html__('Square Connection', 'vms') . '</div>';
	echo '<div class="vms-ticket-integrity__card-value">' . esc_html(!empty($square['connection_present']) ? __('Connected', 'vms') : __('Disconnected', 'vms')) . '</div>';
	echo '<div class="vms-ticket-integrity__payment-card-meta">' . esc_html(!empty($square['has_location_id']) ? __('Location present', 'vms') : __('Location missing', 'vms')) . '</div>';
	echo '</div>';

	echo '<div class="vms-ticket-integrity__card vms-ticket-integrity__card--' . esc_attr(!empty($square['gateway_enabled']) ? 'green' : 'yellow') . '">';
	echo '<div class="vms-ticket-integrity__card-label">' . esc_html__('Square Gateway', 'vms') . '</div>';
	echo '<div class="vms-ticket-integrity__card-value">' . esc_html(!empty($square['gateway_enabled']) ? __('Enabled', 'vms') : __('Disabled', 'vms')) . '</div>';
	echo '<div class="vms-ticket-integrity__payment-card-meta">' . esc_html((string) ($square['environment_label'] ?? __('Unknown', 'vms'))) . '</div>';
	echo '</div>';

	$apple_pay_card_status = !empty($apple_pay['failed']) ? 'yellow' : (!empty($apple_pay['enabled']) ? 'green' : 'neutral');
	echo '<div class="vms-ticket-integrity__card vms-ticket-integrity__card--' . esc_attr($apple_pay_card_status) . '">';
	echo '<div class="vms-ticket-integrity__card-label">' . esc_html__('Apple Pay', 'vms') . '</div>';
	echo '<div class="vms-ticket-integrity__card-value">' . esc_html(!empty($apple_pay['failed']) ? __('Warning', 'vms') : (!empty($apple_pay['enabled']) ? __('Ready', 'vms') : __('Off', 'vms'))) . '</div>';
	echo '<div class="vms-ticket-integrity__payment-card-meta">' . esc_html(!empty($apple_pay['failed']) ? __('Domain registration failed', 'vms') : (!empty($apple_pay['enabled']) ? __('Domain registered or pending', 'vms') : __('Digital wallets not enabled', 'vms'))) . '</div>';
	echo '</div>';
	echo '</div>';

	echo '<div class="vms-ticket-integrity__payment-health-columns">';
	echo '<div class="vms-ticket-integrity__payment-health-column">';
	echo '<h3>' . esc_html__('Checks', 'vms') . '</h3>';
	echo '<ul class="vms-ticket-integrity__audit-list">';
	foreach ((array) ($health['checks'] ?? array()) as $check) {
		if (!is_array($check)) {
			continue;
		}
		$check_css = sanitize_key((string) ($check['status_css'] ?? 'neutral'));
		echo '<li>';
		echo '<span class="' . esc_attr(vms_ticket_integrity_status_css_class($check_css)) . '">' . esc_html((string) ($check['status_label'] ?? __('Unknown', 'vms'))) . '</span> ';
		echo '<strong>' . esc_html((string) ($check['label'] ?? __('Check', 'vms'))) . '</strong>';
		echo '<div class="vms-ticket-integrity__issue-detail">' . esc_html((string) ($check['message'] ?? '')) . '</div>';
		echo '</li>';
	}
	echo '</ul>';
	echo '</div>';

	echo '<div class="vms-ticket-integrity__payment-health-column">';
	echo '<h3>' . esc_html__('Diagnostics', 'vms') . '</h3>';
	echo '<ul class="vms-ticket-integrity__audit-list">';
	echo '<li><strong>' . esc_html__('Site environment', 'vms') . ':</strong> ' . esc_html((string) ($site_environment['label'] ?? __('Unknown', 'vms'))) . '</li>';
	echo '<li><strong>' . esc_html__('Gateway source', 'vms') . ':</strong> ' . esc_html((string) ($checkout['source'] ?? __('Unknown', 'vms'))) . '</li>';
	echo '<li><strong>' . esc_html__('Square auth', 'vms') . ':</strong> ' . esc_html(!empty($square['authenticated']) ? __('Present', 'vms') : __('Missing', 'vms')) . '</li>';
	echo '<li><strong>' . esc_html__('Square location', 'vms') . ':</strong> ' . esc_html(!empty($square['has_location_id']) ? __('Present', 'vms') : __('Missing', 'vms')) . '</li>';
	if (!empty($health['diagnostic_message'])) {
		echo '<li><strong>' . esc_html__('Diagnostic note', 'vms') . ':</strong> ' . esc_html((string) $health['diagnostic_message']) . '</li>';
	}
	echo '</ul>';
	echo '</div>';
	echo '</div>';

	echo '<div class="vms-ticket-integrity__payment-health-memory">';
	echo '<h3>' . esc_html__('Incident Memory', 'vms') . '</h3>';
	if (!empty($incident['active'])) {
			/* translators: %s: timestamp when the current payment incident was first detected. */
			echo '<p><strong>' . esc_html__('Current incident', 'vms') . ':</strong> ' . esc_html(sprintf(__('first detected %s', 'vms'), vms_ticket_integrity_format_datetime(absint($incident['first_detected_failure_gmt'] ?? 0)))) . '</p>';
		if (!empty($incident['diagnostic_message'])) {
			echo '<p class="vms-ticket-integrity__issue-detail">' . esc_html((string) $incident['diagnostic_message']) . '</p>';
		}
	} elseif (!empty($last_incident['resolved_at_gmt'])) {
			/* translators: %s: timestamp when the most recent payment incident was resolved. */
			echo '<p><strong>' . esc_html__('Most recent incident', 'vms') . ':</strong> ' . esc_html(sprintf(__('resolved %s', 'vms'), vms_ticket_integrity_format_datetime(absint($last_incident['resolved_at_gmt'] ?? 0)))) . '</p>';
		if (!empty($last_incident['summary'])) {
			echo '<p class="vms-ticket-integrity__issue-detail">' . esc_html((string) $last_incident['summary']) . '</p>';
		}
	} else {
		echo '<p>' . esc_html__('No payment gateway incidents have been recorded yet.', 'vms') . '</p>';
	}
	echo '</div>';
	echo '</section>';
}

function vms_ticket_integrity_daily_report_result_label(string $result): string
{
	$result = sanitize_key($result);
	switch ($result) {
		case 'render_started':
			return __('Render started', 'vms');
		case 'rendered':
			return __('Rendered', 'vms');
		case 'dry_run_rendered':
			return __('Dry run rendered', 'vms');
		case 'send_attempted':
			return __('Send attempted', 'vms');
		case 'send_success':
		case 'sent':
			return __('Send success', 'vms');
		case 'skipped_no_snapshot':
			return __('Skipped: no usable snapshot', 'vms');
		case 'skipped_scan_failed':
			return __('Skipped: refresh scan failed', 'vms');
		case 'send_failed':
			return __('Send failed', 'vms');
		case 'scan_refresh_failed':
			return __('Refresh scan failed', 'vms');
		case 'disabled':
			return __('Disabled', 'vms');
		case 'no_recipient':
			return __('No recipient configured', 'vms');
		case 'empty_body':
			return __('Rendered empty body', 'vms');
		default:
			return $result !== '' ? ucwords(str_replace('_', ' ', $result)) : __('Never run', 'vms');
	}
}

function vms_ticket_integrity_render_daily_report_status_panel(array $settings): void
{
	if (!function_exists('vms_ticket_integrity_daily_report_status_snapshot')) {
		return;
	}

	$status = vms_ticket_integrity_daily_report_status_snapshot();
	$state = is_array($status['state'] ?? null) ? $status['state'] : array();
	$configured_recipient = function_exists('vms_ticket_integrity_daily_report_recipient')
		? vms_ticket_integrity_daily_report_recipient($settings)
		: sanitize_email((string) get_option('admin_email', ''));
	$scheduled_timestamps = array_map('absint', (array) ($status['scheduled_timestamps'] ?? array()));

	echo '<section class="vms-ticket-integrity__panel">';
	echo '<h2>' . esc_html__('State of the Range Status', 'vms') . '</h2>';
	echo '<p class="description">' . esc_html__('Track whether the daily report was scheduled, rendered, and handed off to the site mailer separately.', 'vms') . '</p>';
	echo '<div class="vms-ticket-integrity__diagnostic-grid">';
	vms_ticket_integrity_render_diagnostic_meta(__('Hook', 'vms'), (string) ($status['hook'] ?? 'vms_ticket_integrity_daily_report'));
	vms_ticket_integrity_render_diagnostic_meta(__('Expected Local Time', 'vms'), (string) ($status['expected_local_time'] ?? '06:05'));
	vms_ticket_integrity_render_diagnostic_meta(__('Next Scheduled Run', 'vms'), (string) ($status['next_scheduled_run_local'] ?? __('Never', 'vms')), !empty($status['next_scheduled_run_at']) ? 'good' : 'warning');
	vms_ticket_integrity_render_diagnostic_meta(__('Scheduled Hook Count', 'vms'), (string) absint($status['scheduled_hook_count'] ?? 0), absint($status['scheduled_hook_count'] ?? 0) === 1 ? 'good' : 'warning');
	vms_ticket_integrity_render_diagnostic_meta(__('Last Scheduled Run', 'vms'), vms_ticket_integrity_format_datetime(absint($state['last_scheduled_run_at'] ?? 0)));
	vms_ticket_integrity_render_diagnostic_meta(__('Last Successful Render', 'vms'), vms_ticket_integrity_format_datetime(absint($state['last_render_finished_at'] ?? 0)));
	vms_ticket_integrity_render_diagnostic_meta(__('Last Send Attempt', 'vms'), vms_ticket_integrity_format_datetime(absint($state['last_send_attempt_at'] ?? 0)));
	vms_ticket_integrity_render_diagnostic_meta(__('Last Successful Send', 'vms'), vms_ticket_integrity_format_datetime(absint($state['last_successful_send_at'] ?? 0)));
	vms_ticket_integrity_render_diagnostic_meta(__('Last Result', 'vms'), vms_ticket_integrity_daily_report_result_label((string) ($state['last_result'] ?? '')), ($state['last_status'] ?? '') === 'sent' ? 'good' : (($state['last_error'] ?? '') !== '' ? 'warning' : ''));
	vms_ticket_integrity_render_diagnostic_meta(__('Configured Recipient', 'vms'), $configured_recipient !== '' ? $configured_recipient : __('None configured', 'vms'), $configured_recipient !== '' ? 'good' : 'warning');
	vms_ticket_integrity_render_diagnostic_meta(__('Last Mailer', 'vms'), (string) ($state['last_mailer'] ?? '') !== '' ? (string) $state['last_mailer'] : __('Unknown', 'vms'));
	vms_ticket_integrity_render_diagnostic_meta(__('Last Trigger / Mode', 'vms'), trim(implode(' / ', array_filter(array((string) ($state['last_trigger'] ?? ''), (string) ($state['last_mode'] ?? ''))))));
	echo '</div>';

	echo '<div class="vms-ticket-integrity__diagnostic-columns">';
	echo '<div class="vms-ticket-integrity__diagnostic-section">';
	echo '<h5>' . esc_html__('Schedule Details', 'vms') . '</h5>';
	echo '<ul class="vms-ticket-integrity__audit-list">';
	echo '<li><strong>' . esc_html__('Scheduled timestamps', 'vms') . ':</strong> ';
	if (empty($scheduled_timestamps)) {
		echo esc_html__('None', 'vms');
	} else {
		echo esc_html(implode(', ', array_map('vms_ticket_integrity_format_datetime', $scheduled_timestamps)));
	}
	echo '</li>';
	echo '<li><strong>' . esc_html__('Site timezone', 'vms') . ':</strong> ' . esc_html(wp_timezone_string() !== '' ? wp_timezone_string() : 'UTC') . '</li>';
	echo '</ul>';
	echo '</div>';

	echo '<div class="vms-ticket-integrity__diagnostic-section">';
	echo '<h5>' . esc_html__('Last Delivery Attempt', 'vms') . '</h5>';
	echo '<ul class="vms-ticket-integrity__audit-list">';
	echo '<li><strong>' . esc_html__('Recipient used', 'vms') . ':</strong> ' . esc_html((string) ($state['last_recipient'] ?? '') !== '' ? (string) $state['last_recipient'] : __('None', 'vms')) . '</li>';
	echo '<li><strong>' . esc_html__('Subject', 'vms') . ':</strong> ' . esc_html((string) ($state['last_subject'] ?? '') !== '' ? (string) $state['last_subject'] : __('Unknown', 'vms')) . '</li>';
	echo '<li><strong>' . esc_html__('Used stale snapshot', 'vms') . ':</strong> ' . esc_html(!empty($state['used_stale_snapshot']) ? __('Yes', 'vms') : __('No', 'vms')) . '</li>';
	echo '<li><strong>' . esc_html__('Last error', 'vms') . ':</strong> ' . esc_html((string) ($state['last_error'] ?? '') !== '' ? (string) $state['last_error'] : __('None', 'vms')) . '</li>';
	echo '</ul>';
	echo '</div>';
	echo '</div>';

	if (absint($status['scheduled_hook_count'] ?? 0) !== 1) {
		echo '<p class="vms-ticket-integrity__diagnostic-note">' . esc_html__('The report hook count is not exactly one. VMS will repair duplicates or missing hooks on the next runtime-maintenance pass.', 'vms') . '</p>';
	}
	if ((string) ($state['last_error'] ?? '') !== '') {
		echo '<p class="vms-ticket-integrity__diagnostic-note">' . esc_html__('The last run recorded a delivery or render error. Use Preview or Dry-Run Diagnostic below before sending a live test email.', 'vms') . '</p>';
	}
	echo '</section>';
}

function vms_ticket_integrity_render_daily_report_preview_panel(): void
{
	$preview = vms_ticket_integrity_get_daily_report_preview();
	if (empty($preview)) {
		return;
	}

	echo '<section class="vms-ticket-integrity__panel">';
	echo '<h2>' . esc_html__('State of the Range Preview', 'vms') . '</h2>';
	echo '<div class="vms-ticket-integrity__diagnostic-grid">';
	vms_ticket_integrity_render_diagnostic_meta(__('Mode', 'vms'), vms_ticket_integrity_daily_report_result_label((string) ($preview['mode'] ?? 'preview')));
	vms_ticket_integrity_render_diagnostic_meta(__('Generated', 'vms'), vms_ticket_integrity_format_datetime(absint($preview['generated_at_gmt'] ?? 0)));
	vms_ticket_integrity_render_diagnostic_meta(__('Recipient', 'vms'), (string) ($preview['recipient'] ?? '') !== '' ? (string) $preview['recipient'] : __('Not used for preview', 'vms'));
	vms_ticket_integrity_render_diagnostic_meta(__('Subject', 'vms'), (string) ($preview['subject'] ?? ''));
	echo '</div>';
	echo '<pre style="margin-top:14px;white-space:pre-wrap;word-break:break-word;max-height:420px;overflow:auto;">' . esc_html((string) ($preview['body'] ?? '')) . '</pre>';
	echo '</section>';
}

function vms_ticket_integrity_render_settings_form(array $settings): void
{
	$recipient_placeholder = sanitize_email((string) get_option('admin_email', ''));
	$test_recipient = sanitize_email((string) get_option('admin_email', ''));

	echo '<section class="vms-ticket-integrity__panel" data-vms-tour="ticket-integrity.settings">';
	echo '<h2>' . esc_html__('Monitor Settings', 'vms') . '</h2>';
	echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-ticket-integrity__settings-form">';
	wp_nonce_field('vms_ticket_integrity_save_settings');
	echo '<input type="hidden" name="action" value="vms_ticket_integrity_save_settings" />';

	echo '<label><input type="checkbox" name="nightly_enabled" value="1"' . checked(!empty($settings['nightly_enabled']), true, false) . ' /> ' . esc_html__('Enable nightly integrity scan', 'vms') . '</label>';
	echo '<label>' . esc_html__('Days ahead to scan', 'vms') . '<input type="number" min="1" max="365" name="days_ahead" value="' . esc_attr((string) absint($settings['days_ahead'] ?? 120)) . '" /></label>';
	echo '<label><input type="checkbox" name="email_alerts_enabled" value="1"' . checked(!empty($settings['email_alerts_enabled']), true, false) . ' /> ' . esc_html__('Enable integrity exception emails', 'vms') . '</label>';
	echo '<label>' . esc_html__('Alert recipient email', 'vms') . '<input type="email" name="alert_recipient" value="' . esc_attr((string) ($settings['alert_recipient'] ?? '')) . '" placeholder="' . esc_attr($recipient_placeholder) . '" /></label>';
	echo '<label><input type="checkbox" name="send_resolved_notifications" value="1"' . checked(!empty($settings['send_resolved_notifications']), true, false) . ' /> ' . esc_html__('Send resolved notifications', 'vms') . '</label>';
	echo '<label>' . esc_html__('Reminder interval (hours)', 'vms') . '<input type="number" min="1" max="168" name="reminder_interval_hours" value="' . esc_attr((string) absint($settings['reminder_interval_hours'] ?? 24)) . '" /></label>';
	echo '<label><input type="checkbox" name="include_yellow_in_email_alerts" value="1"' . checked(!empty($settings['include_yellow_in_email_alerts']), true, false) . ' /> ' . esc_html__('Include Yellow issues in integrity exception emails', 'vms') . '</label>';
	echo '<label><input type="checkbox" name="daily_report_enabled" value="1"' . checked(!empty($settings['daily_report_enabled']), true, false) . ' /> ' . esc_html__('Enable daily “State of the Range” email', 'vms') . '</label>';
	echo '<label>' . esc_html__('Daily report recipient email', 'vms') . '<input type="email" name="daily_report_recipient" value="' . esc_attr((string) ($settings['daily_report_recipient'] ?? '')) . '" placeholder="' . esc_attr($recipient_placeholder) . '" /></label>';
	echo '<label><input type="checkbox" name="payment_gateway_health_enabled" value="1"' . checked(!empty($settings['payment_gateway_health_enabled']), true, false) . ' /> ' . esc_html__('Enable scheduled payment gateway health checks', 'vms') . '</label>';
	echo '<label>' . esc_html__('Payment gateway health interval', 'vms');
	echo '<select name="payment_gateway_health_interval">';
	echo '<option value="vms_ticket_integrity_fifteen_minutes"' . selected((string) ($settings['payment_gateway_health_interval'] ?? 'vms_ticket_integrity_fifteen_minutes'), 'vms_ticket_integrity_fifteen_minutes', false) . '>' . esc_html__('Every 15 minutes', 'vms') . '</option>';
	echo '<option value="hourly"' . selected((string) ($settings['payment_gateway_health_interval'] ?? 'vms_ticket_integrity_fifteen_minutes'), 'hourly', false) . '>' . esc_html__('Hourly', 'vms') . '</option>';
	echo '</select></label>';
	echo '<label><input type="checkbox" name="low_inventory_email_alerts_enabled" value="1"' . checked(!empty($settings['low_inventory_email_alerts_enabled']), true, false) . ' /> ' . esc_html__('Send low-inventory alert emails', 'vms') . '</label>';
	echo '<label>' . esc_html__('Low inventory threshold (tickets)', 'vms') . '<input type="number" min="1" max="10000" name="low_inventory_threshold" value="' . esc_attr((string) absint($settings['low_inventory_threshold'] ?? 25)) . '" /></label>';
	echo '<label>' . esc_html__('Low inventory threshold (%)', 'vms') . '<input type="number" min="1" max="100" name="low_inventory_percent_threshold" value="' . esc_attr((string) absint($settings['low_inventory_percent_threshold'] ?? 10)) . '" /></label>';
	echo '<label>' . esc_html__('Critical inventory threshold (tickets)', 'vms') . '<input type="number" min="1" max="10000" name="critical_inventory_threshold" value="' . esc_attr((string) absint($settings['critical_inventory_threshold'] ?? 5)) . '" /></label>';
	echo '<label>' . esc_html__('Critical inventory threshold (%)', 'vms') . '<input type="number" min="1" max="100" name="critical_inventory_percent_threshold" value="' . esc_attr((string) absint($settings['critical_inventory_percent_threshold'] ?? 3)) . '" /></label>';
	echo '<p class="description">' . esc_html__('Nightly scans still drive the underlying integrity checks. The daily State of the Range email is scheduled separately for the morning and will refresh the scan first if the stored data is stale.', 'vms') . '</p>';

	submit_button(__('Save Ticket Integrity Settings', 'vms'), 'secondary', 'submit', false);
	echo '</form>';

	echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-ticket-integrity__settings-form" style="margin-top:12px;">';
	wp_nonce_field('vms_ticket_integrity_send_daily_report');
	echo '<input type="hidden" name="action" value="vms_ticket_integrity_send_daily_report" />';
	submit_button(__('Send State of the Range Now', 'vms'), 'secondary', 'submit', false);
	echo '</form>';

	echo '<div class="vms-ticket-integrity__diagnostic-columns">';
	echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-ticket-integrity__settings-form">';
	wp_nonce_field('vms_ticket_integrity_preview_daily_report');
	echo '<input type="hidden" name="action" value="vms_ticket_integrity_preview_daily_report" />';
	submit_button(__('Preview Today’s Report', 'vms'), 'secondary', 'submit', false);
	echo '</form>';

	echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-ticket-integrity__settings-form">';
	wp_nonce_field('vms_ticket_integrity_dry_run_daily_report');
	echo '<input type="hidden" name="action" value="vms_ticket_integrity_dry_run_daily_report" />';
	submit_button(__('Dry-Run Diagnostic', 'vms'), 'secondary', 'submit', false);
	echo '</form>';

	echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-ticket-integrity__settings-form">';
	wp_nonce_field('vms_ticket_integrity_send_daily_report_test');
	echo '<input type="hidden" name="action" value="vms_ticket_integrity_send_daily_report_test" />';
	echo '<label>' . esc_html__('Admin test recipient', 'vms') . '<input type="email" name="test_recipient" value="' . esc_attr($test_recipient) . '" placeholder="' . esc_attr($test_recipient) . '" /></label>';
	submit_button(__('Send Test to Admin', 'vms'), 'secondary', 'submit', false);
	echo '</form>';
	echo '</div>';
	echo '</section>';
}

function vms_ticket_integrity_format_audit_source(array $entry): string
{
	$parts = array();
	$trigger = sanitize_key((string) ($entry['trigger_source'] ?? ''));
	if ($trigger !== '') {
		$parts[] = function_exists('vms_ticket_mutation_audit_trigger_label')
			? vms_ticket_mutation_audit_trigger_label($trigger)
			: ucwords(str_replace('_', ' ', $trigger));
	}

	$source_function = trim((string) ($entry['source_function'] ?? ''));
	if ($source_function !== '') {
		$parts[] = $source_function;
	} else {
		$source_hook = trim((string) ($entry['source_hook'] ?? ''));
		if ($source_hook !== '') {
			$parts[] = $source_hook;
		}
	}

	return implode(' / ', array_filter($parts));
}

function vms_ticket_integrity_format_repair_result(array $entry): string
{
	$result = sanitize_key((string) ($entry['result_status'] ?? ''));
	switch ($result) {
		case 'success':
			return __('Repair completed', 'vms');
		case 'no_op':
			return __('No changes were needed', 'vms');
		case 'partial_changes':
			return __('Repair made partial changes', 'vms');
		case 'partial':
			return __('Repair attempted but unresolved conflicts remain', 'vms');
		case 'failed':
			return __('Repair could not proceed safely', 'vms');
		default:
			return __('No rebuild attempt logged yet', 'vms');
	}
}

function vms_ticket_integrity_render_diagnostic_meta(string $label, string $value, string $modifier = ''): void
{
	$class = 'vms-ticket-integrity__diagnostic-meta';
	if ($modifier !== '') {
		$class .= ' vms-ticket-integrity__diagnostic-meta--' . sanitize_html_class($modifier);
	}

	echo '<div class="' . esc_attr($class) . '">';
	echo '<div class="vms-ticket-integrity__diagnostic-label">' . esc_html($label) . '</div>';
	echo '<div class="vms-ticket-integrity__diagnostic-value">' . esc_html($value) . '</div>';
	echo '</div>';
}

function vms_ticket_integrity_render_subdetails(string $title, callable $callback, array $args = array()): void
{
	$classes = array('vms-ticket-integrity__subdetails');
	if (!empty($args['class'])) {
		$classes[] = sanitize_html_class((string) $args['class']);
	}

	$summary_meta = trim((string) ($args['summary_meta'] ?? ''));
	$body_class = 'vms-ticket-integrity__subdetails-body';
	if (!empty($args['body_class'])) {
		$body_class .= ' ' . sanitize_html_class((string) $args['body_class']);
	}

	echo '<details class="' . esc_attr(implode(' ', array_filter($classes))) . '"' . (!empty($args['open']) ? ' open' : '') . '>';
	echo '<summary>';
	echo '<span class="vms-ticket-integrity__subdetails-title">' . esc_html($title) . '</span>';
	if ($summary_meta !== '') {
		echo '<span class="vms-ticket-integrity__subdetails-meta">' . esc_html($summary_meta) . '</span>';
	}
	echo '</summary>';
	echo '<div class="' . esc_attr($body_class) . '">';
	$callback();
	echo '</div>';
	echo '</details>';
}

function vms_ticket_integrity_render_ticket_mapping_list(array $tickets): void
{
	if (empty($tickets)) {
		echo '<p class="vms-ticket-integrity__diagnostic-empty">' . esc_html__('No active mapped tickets are stored in the current config snapshot.', 'vms') . '</p>';
		return;
	}

	echo '<ul class="vms-ticket-integrity__mutation-list">';
	foreach ($tickets as $ticket) {
		if (!is_array($ticket)) {
			continue;
		}

		$title = trim((string) ($ticket['title'] ?? $ticket['ticket_key'] ?? __('Ticket', 'vms')));
		$product_id = absint($ticket['mapped_product_id'] ?? 0);
		$visibility_mode = sanitize_key((string) ($ticket['visibility_mode'] ?? 'public'));
		$enabled = !array_key_exists('enabled', $ticket) || !empty($ticket['enabled']);
			$detail = $product_id > 0
				/* translators: %d: mapped WooCommerce product ID. */
				? sprintf(__('Product #%d', 'vms'), $product_id)
				: __('Not currently mapped to a product', 'vms');
			if ($visibility_mode !== '') {
				/* translators: %s: ticket visibility mode label. */
				$detail .= ' / ' . sprintf(__('Visibility: %s', 'vms'), str_replace('_', ' ', $visibility_mode));
			}
		if (!$enabled) {
			$detail .= ' / ' . __('Disabled in config', 'vms');
		}

		echo '<li><strong>' . esc_html($title) . '</strong><div class="vms-ticket-integrity__issue-detail">' . esc_html($detail) . '</div></li>';
	}
	echo '</ul>';
}

function vms_ticket_integrity_render_product_diagnostic_list(array $products, string $empty_message): void
{
	if (empty($products)) {
		echo '<p class="vms-ticket-integrity__diagnostic-empty">' . esc_html($empty_message) . '</p>';
		return;
	}

	echo '<ul class="vms-ticket-integrity__mutation-list">';
	foreach ($products as $product) {
		if (!is_array($product)) {
			continue;
		}

		$product_id = absint($product['product_id'] ?? 0);
		$title = trim((string) ($product['title'] ?? __('Untitled product', 'vms')));
		$status = trim((string) ($product['post_status'] ?? ''));
		$sku = trim((string) ($product['sku'] ?? ''));
			/* translators: %d: WooCommerce product ID. */
			$parts = array(sprintf(__('Product #%d', 'vms'), $product_id));
			if ($status !== '') {
				/* translators: %s: WordPress post status for the product. */
				$parts[] = sprintf(__('Status: %s', 'vms'), $status);
			}
			if ($sku !== '') {
				/* translators: %s: WooCommerce product SKU. */
				$parts[] = sprintf(__('SKU: %s', 'vms'), $sku);
			}
		if (!empty($product['is_mapped'])) {
			$parts[] = __('Currently mapped', 'vms');
		}

		echo '<li><strong>' . esc_html($title) . '</strong><div class="vms-ticket-integrity__issue-detail">' . esc_html(implode(' / ', $parts)) . '</div></li>';
	}
	echo '</ul>';
}

function vms_ticket_integrity_render_mutation_diagnostics(array $event): void
{
	$diagnostics = is_array($event['mutation_diagnostics'] ?? null) ? $event['mutation_diagnostics'] : array();
	$origin = is_array($diagnostics['origin'] ?? null) ? $diagnostics['origin'] : array();
	if (empty($origin)) {
		$classification = sanitize_key((string) ($event['origin_classification'] ?? ''));
		$origin = array(
			'classification' => $classification,
			'label' => function_exists('vms_ticket_mutation_audit_origin_label')
				? vms_ticket_mutation_audit_origin_label($classification)
				: ucwords(str_replace('_', ' ', $classification)),
			'reasons' => array_values(array_filter(array_map('strval', (array) ($event['origin_reasons'] ?? array())))),
		);
	}

	$latest_mutation = is_array($diagnostics['latest_mutation'] ?? null) ? $diagnostics['latest_mutation'] : array();
	$last_repair = is_array($diagnostics['last_repair'] ?? null) ? $diagnostics['last_repair'] : array();
	$recent_mutations = array_values((array) ($diagnostics['recent_mutations'] ?? array()));
	$repeated_drift = is_array($diagnostics['repeated_drift'] ?? null) ? $diagnostics['repeated_drift'] : array();
	$active_mapped_tickets = array_values((array) ($diagnostics['active_mapped_tickets'] ?? array()));
	$legacy_leftovers = array_values((array) ($diagnostics['legacy_leftovers'] ?? array()));
	$untracked_products = array_values((array) ($diagnostics['untracked_products'] ?? array()));
	$recommended_action = trim((string) ($diagnostics['recommended_action'] ?? ''));
	$public_path_healthy = !empty($diagnostics['public_path_healthy']);
	$latest_result = trim((string) ($latest_mutation['result_label'] ?? ''));
	$latest_source = vms_ticket_integrity_format_audit_source($latest_mutation);
	$last_repair_label = !empty($last_repair)
		? vms_ticket_integrity_format_repair_result($last_repair)
		: __('No rebuild attempt logged yet', 'vms');
	$drift_value = !empty($repeated_drift['flagged']) ? __('Detected', 'vms') : __('Not detected', 'vms');
	$origin_reasons = array_values(array_filter(array_map('strval', (array) ($origin['reasons'] ?? array()))));

	echo '<section class="vms-ticket-integrity__diagnostics" data-vms-tour="ticket-integrity.diagnostics">';
	echo '<h4>' . esc_html__('Mutation Diagnostics', 'vms') . '</h4>';
	echo '<div class="vms-ticket-integrity__diagnostic-grid">';
	vms_ticket_integrity_render_diagnostic_meta(
		__('Event origin', 'vms'),
		trim((string) ($origin['label'] ?? __('Unknown', 'vms')))
	);
	vms_ticket_integrity_render_diagnostic_meta(
		__('Last mapping change', 'vms'),
		!empty($latest_mutation) ? vms_ticket_integrity_format_datetime(absint($latest_mutation['timestamp_gmt'] ?? 0)) : __('No change log yet', 'vms')
	);
	vms_ticket_integrity_render_diagnostic_meta(
		__('Last change source', 'vms'),
		$latest_source !== '' ? $latest_source : __('No source logged yet', 'vms')
	);
	vms_ticket_integrity_render_diagnostic_meta(
		__('Last change result', 'vms'),
		$latest_result !== '' ? $latest_result : __('No result logged yet', 'vms')
	);
	vms_ticket_integrity_render_diagnostic_meta(
		__('Last rebuild', 'vms'),
		$last_repair_label
	);
	vms_ticket_integrity_render_diagnostic_meta(
		__('Repeated drift', 'vms'),
		$drift_value,
		!empty($repeated_drift['flagged']) ? 'warning' : 'good'
	);
	vms_ticket_integrity_render_diagnostic_meta(
		__('Public ticket path', 'vms'),
		$public_path_healthy ? __('Healthy', 'vms') : __('Needs review', 'vms'),
		$public_path_healthy ? 'good' : 'warning'
	);
	echo '</div>';

	if (!empty($origin_reasons)) {
		vms_ticket_integrity_render_subdetails(
			__('Why this origin was chosen', 'vms'),
			static function () use ($origin_reasons): void {
				echo '<ul class="vms-ticket-integrity__mutation-list">';
				foreach ($origin_reasons as $reason) {
					echo '<li>' . esc_html($reason) . '</li>';
				}
				echo '</ul>';
			},
				array(
					/* translators: %d: number of reasons explaining the selected ticket origin. */
					'summary_meta' => sprintf(_n('%d reason', '%d reasons', count($origin_reasons), 'vms'), count($origin_reasons)),
				)
			);
	}

	if (!empty($repeated_drift['flagged']) && !empty($repeated_drift['message'])) {
		echo '<p class="vms-ticket-integrity__diagnostic-note vms-ticket-integrity__diagnostic-note--warning">' . esc_html((string) ($repeated_drift['message'])) . '</p>';
	}

	vms_ticket_integrity_render_subdetails(
		__('Mapping snapshot', 'vms'),
		static function () use ($active_mapped_tickets, $legacy_leftovers, $untracked_products): void {
			echo '<div class="vms-ticket-integrity__diagnostic-columns">';
			echo '<div class="vms-ticket-integrity__diagnostic-section">';
			echo '<h5>' . esc_html__('Current mapped tickets', 'vms') . '</h5>';
			vms_ticket_integrity_render_ticket_mapping_list($active_mapped_tickets);
			echo '</div>';

			echo '<div class="vms-ticket-integrity__diagnostic-section">';
			echo '<h5>' . esc_html__('Legacy leftovers', 'vms') . '</h5>';
			vms_ticket_integrity_render_product_diagnostic_list($legacy_leftovers, __('No legacy leftovers are currently attached.', 'vms'));
			echo '</div>';

			echo '<div class="vms-ticket-integrity__diagnostic-section">';
			echo '<h5>' . esc_html__('Untracked attached products', 'vms') . '</h5>';
			vms_ticket_integrity_render_product_diagnostic_list($untracked_products, __('No extra attached products are currently outside the active map.', 'vms'));
			echo '</div>';
			echo '</div>';
		},
		array(
			'summary_meta' => sprintf(
				/* translators: 1: mapped tickets, 2: legacy leftovers, 3: untracked products */
				__('Mapped %1$d / Legacy %2$d / Extra %3$d', 'vms'),
				count($active_mapped_tickets),
				count($legacy_leftovers),
				count($untracked_products)
			)
		)
	);

	if ($recommended_action !== '') {
		echo '<p class="vms-ticket-integrity__diagnostic-note">' . esc_html($recommended_action) . '</p>';
	}

	vms_ticket_integrity_render_subdetails(
		__('Recent mutation history', 'vms'),
		static function () use ($recent_mutations): void {
			if (empty($recent_mutations)) {
				echo '<p class="vms-ticket-integrity__diagnostic-empty">' . esc_html__('No mutation history has been recorded for this event yet.', 'vms') . '</p>';
				return;
			}

			echo '<ul class="vms-ticket-integrity__mutation-list">';
			foreach ($recent_mutations as $mutation) {
				if (!is_array($mutation)) {
					continue;
				}

				$headline = trim((string) ($mutation['change_type_label'] ?? __('Ticket mutation', 'vms')));
				$result_label = trim((string) ($mutation['result_label'] ?? ''));
				if ($result_label !== '') {
					$headline .= ' / ' . $result_label;
				}
				$time_text = vms_ticket_integrity_format_datetime(absint($mutation['timestamp_gmt'] ?? 0));
				$source_text = vms_ticket_integrity_format_audit_source($mutation);
				$summary_text = trim((string) ($mutation['summary_text'] ?? ''));
				$details = is_array($mutation['details'] ?? null) ? $mutation['details'] : array();
				$derivation_text = trim((string) ($mutation['derivation_source_label'] ?? $mutation['derivation_source'] ?? ''));
				$writer_branch_text = trim((string) ($details['writer_branch'] ?? $mutation['writer_branch'] ?? ''));
				$result_health = sanitize_key((string) ($mutation['result_health'] ?? ''));
				$result_health_text = $result_health !== '' && function_exists('vms_ticketing_v2_inventory_result_health_label')
					? (string) vms_ticketing_v2_inventory_result_health_label($result_health)
					: '';
				$stock_transition = vms_ticket_integrity_report_text_value($details['old_stock_qty'] ?? null) . ' -> ' . vms_ticket_integrity_report_text_value($details['new_stock_qty'] ?? null);
				$status_transition = trim((string) ($details['old_stock_status'] ?? '—')) . ' -> ' . trim((string) ($details['new_stock_status'] ?? '—'));
				$manage_transition = trim((string) ($details['old_manage_stock'] ?? '—')) . ' -> ' . trim((string) ($details['new_manage_stock'] ?? '—'));

				echo '<li>';
				echo '<strong>' . esc_html($headline) . '</strong>';
				echo '<div class="vms-ticket-integrity__issue-detail">' . esc_html($time_text) . '</div>';
				if ($source_text !== '') {
					echo '<div class="vms-ticket-integrity__issue-detail">' . esc_html($source_text) . '</div>';
				}
				if ($derivation_text !== '' || $writer_branch_text !== '' || $result_health_text !== '') {
					echo '<div class="vms-ticket-integrity__issue-detail">' . esc_html(
						sprintf(
							/* translators: 1: derivation text, 2: writer branch, 3: result health */
							__('Derivation: %1$s / Writer branch: %2$s / Result health: %3$s', 'vms'),
							$derivation_text !== '' ? $derivation_text : __('Unknown', 'vms'),
							$writer_branch_text !== '' ? $writer_branch_text : __('Not recorded', 'vms'),
							$result_health_text !== '' ? $result_health_text : __('Not recorded', 'vms')
						)
					) . '</div>';
				}
				echo '<div class="vms-ticket-integrity__issue-detail">' . esc_html(
					sprintf(
						/* translators: 1: stock qty transition, 2: stock status transition, 3: manage stock transition */
						__('Stock qty: %1$s / Stock status: %2$s / Manage stock: %3$s', 'vms'),
						$stock_transition,
						$status_transition,
						$manage_transition
					)
				) . '</div>';
				if ($summary_text !== '') {
					echo '<div class="vms-ticket-integrity__issue-detail">' . esc_html($summary_text) . '</div>';
				}
				echo '</li>';
			}
			echo '</ul>';
		},
			array(
				/* translators: %d: number of recent mutation log entries. */
				'summary_meta' => sprintf(_n('%d entry', '%d entries', count($recent_mutations), 'vms'), count($recent_mutations))
			)
		);
	echo '</section>';
}

function vms_ticket_integrity_render_compact_table_facts(array $facts): string
{
	$lines = array();
	foreach ($facts as $label => $value) {
		$label = trim((string) $label);
		$value = trim((string) $value);
		if ($label === '') {
			continue;
		}
		if ($value === '') {
			$value = '—';
		}
		$lines[] = sprintf(
			'<div class="vms-ticket-integrity__stacked-line"><span class="vms-ticket-integrity__stacked-label">%1$s</span><span class="vms-ticket-integrity__stacked-value">%2$s</span></div>',
			esc_html($label),
			esc_html($value)
		);
	}

	if (empty($lines)) {
		$lines[] = '<div class="vms-ticket-integrity__stacked-line"><span class="vms-ticket-integrity__stacked-value">—</span></div>';
	}

	return '<div class="vms-ticket-integrity__stacked-cell">' . implode('', $lines) . '</div>';
}

function vms_ticket_integrity_render_inventory_value($value): string
{
	if (function_exists('vms_ticket_inventory_forensics_display_quantity')) {
		return (string) vms_ticket_inventory_forensics_display_quantity($value);
	}

	if ($value === '' || $value === null) {
		return '—';
	}

	return is_scalar($value) ? (string) $value : '—';
}

function vms_ticket_integrity_render_inventory_diagnostics(array $event): void
{
	$diagnostics = is_array($event['inventory_diagnostics'] ?? null) ? $event['inventory_diagnostics'] : array();
	if (empty($diagnostics)) {
		return;
	}

	$latest_mutation = is_array($diagnostics['latest_inventory_mutation'] ?? null) ? $diagnostics['latest_inventory_mutation'] : array();
	$recent_mutations = array_values((array) ($diagnostics['recent_inventory_mutations'] ?? array()));
	$ticket_rows = array_values((array) ($diagnostics['ticket_rows'] ?? array()));
	$cause_reasons = array_values(array_filter(array_map('strval', (array) ($diagnostics['cause_reasons'] ?? array()))));
	$comparison = is_array($diagnostics['healthy_comparison'] ?? null) ? $diagnostics['healthy_comparison'] : array();
	$cluster = is_array($diagnostics['origin_cluster'] ?? null) ? $diagnostics['origin_cluster'] : array();
	$repeated_drift = is_array($diagnostics['repeated_inventory_drift'] ?? null) ? $diagnostics['repeated_inventory_drift'] : array();
	$recorruption = is_array($diagnostics['woo_recorruption'] ?? null) ? $diagnostics['woo_recorruption'] : array();
	$writer_suspect = is_array($diagnostics['upstream_writer_suspect'] ?? null) ? $diagnostics['upstream_writer_suspect'] : array();
	$latest_source = vms_ticket_integrity_format_audit_source($latest_mutation);

	echo '<section class="vms-ticket-integrity__diagnostics vms-ticket-integrity__diagnostics--inventory" data-vms-tour="ticket-integrity.inventory">';
	echo '<h4>' . esc_html__('Inventory Forensics', 'vms') . '</h4>';
	echo '<div class="vms-ticket-integrity__diagnostic-grid">';
	vms_ticket_integrity_render_diagnostic_meta(
		__('Event Capacity', 'vms'),
		vms_ticket_integrity_render_inventory_value($diagnostics['event_capacity'] ?? null)
	);
	vms_ticket_integrity_render_diagnostic_meta(
		__('Sold Count', 'vms'),
		vms_ticket_integrity_render_inventory_value($diagnostics['event_sold'] ?? null)
	);
	vms_ticket_integrity_render_diagnostic_meta(
		__('Available (TEC)', 'vms'),
		vms_ticket_integrity_render_inventory_value($diagnostics['event_available'] ?? null)
	);
	vms_ticket_integrity_render_diagnostic_meta(
		__('Zero-Available Mismatch', 'vms'),
		!empty($diagnostics['zero_available_mismatch']) ? __('Detected', 'vms') : __('Not detected', 'vms'),
		!empty($diagnostics['zero_available_mismatch']) ? 'warning' : 'good'
	);
	vms_ticket_integrity_render_diagnostic_meta(
		__('Likely Pattern', 'vms'),
		trim((string) ($diagnostics['suspected_cause_label'] ?? __('Healthy / not flagged', 'vms')))
	);
	vms_ticket_integrity_render_diagnostic_meta(
		__('Woo Primary Mismatch', 'vms'),
		!empty($diagnostics['woo_primary_mismatch']) ? __('Detected', 'vms') : __('Not detected', 'vms'),
		!empty($diagnostics['woo_primary_mismatch']) ? 'warning' : 'good'
	);
	vms_ticket_integrity_render_diagnostic_meta(
		__('TEC Follow-up', 'vms'),
		!empty($diagnostics['tec_followup_required']) ? __('Required', 'vms') : __('Not required', 'vms'),
		!empty($diagnostics['tec_followup_required']) ? 'warning' : 'good'
	);
	vms_ticket_integrity_render_diagnostic_meta(
		__('Woo Re-Corruption', 'vms'),
		!empty($diagnostics['woo_recorruption_detected']) ? __('Detected', 'vms') : __('Not detected', 'vms'),
		!empty($diagnostics['woo_recorruption_detected']) ? 'warning' : 'good'
	);
	vms_ticket_integrity_render_diagnostic_meta(
		__('Last Inventory Change', 'vms'),
		!empty($latest_mutation) ? vms_ticket_integrity_format_datetime(absint($latest_mutation['timestamp_gmt'] ?? 0)) : __('No change log yet', 'vms')
	);
	vms_ticket_integrity_render_diagnostic_meta(
		__('Last Change Source', 'vms'),
		$latest_source !== '' ? $latest_source : __('No source logged yet', 'vms')
	);
	echo '</div>';

	if (!empty($cluster['message'])) {
		echo '<p class="vms-ticket-integrity__diagnostic-note">' . esc_html((string) $cluster['message']) . '</p>';
	}

	if (!empty($repeated_drift['flagged']) && !empty($repeated_drift['message'])) {
		echo '<p class="vms-ticket-integrity__diagnostic-note vms-ticket-integrity__diagnostic-note--warning">' . esc_html((string) $repeated_drift['message']) . '</p>';
	}

	if (!empty($recorruption['flagged']) && !empty($recorruption['message'])) {
		echo '<p class="vms-ticket-integrity__diagnostic-note vms-ticket-integrity__diagnostic-note--warning">' . esc_html((string) $recorruption['message']) . '</p>';
	}

	if (!empty($writer_suspect)) {
		$source_text = trim((string) ($writer_suspect['source_function'] ?? $writer_suspect['source_hook'] ?? ''));
		$reason_text = trim((string) ($writer_suspect['reason_text'] ?? ''));
		$parts = array();
			if ($source_text !== '') {
				/* translators: %s: function or hook name suspected of rewriting ticket inventory. */
				$parts[] = sprintf(__('Likely upstream writer: %s', 'vms'), $source_text);
			}
			if ($reason_text !== '') {
				/* translators: %s: most recent reason recorded for the conflicting ticket inventory write. */
				$parts[] = sprintf(__('Last conflicting reason: %s', 'vms'), $reason_text);
			}
		if (!empty($parts)) {
			echo '<p class="vms-ticket-integrity__diagnostic-note">' . esc_html(implode(' / ', $parts)) . '</p>';
		}
	}

	if (!empty($cause_reasons)) {
		vms_ticket_integrity_render_subdetails(
			__('Why This Was Flagged', 'vms'),
			static function () use ($cause_reasons): void {
				echo '<ul class="vms-ticket-integrity__mutation-list">';
				foreach ($cause_reasons as $reason) {
					echo '<li>' . esc_html($reason) . '</li>';
				}
				echo '</ul>';
			},
				array(
					/* translators: %d: number of reasons why the ticket integrity issue was flagged. */
					'summary_meta' => sprintf(_n('%d reason', '%d reasons', count($cause_reasons), 'vms'), count($cause_reasons)),
				)
			);
	}

	$recommended_action = trim((string) ($diagnostics['recommended_action'] ?? ''));
	if ($recommended_action !== '') {
		echo '<p class="vms-ticket-integrity__diagnostic-note vms-ticket-integrity__diagnostic-note--warning">' . esc_html($recommended_action) . '</p>';
	}

	vms_ticket_integrity_render_subdetails(
		__('Per-Ticket Snapshot', 'vms'),
		static function () use ($ticket_rows): void {
			if (empty($ticket_rows)) {
				echo '<p class="vms-ticket-integrity__diagnostic-empty">' . esc_html__('No inventory-tracked ticket rows were found for this event.', 'vms') . '</p>';
				return;
			}

			echo '<div class="vms-ticket-integrity__inventory-table-wrap">';
			echo '<table class="widefat striped vms-ticket-integrity__inventory-table">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__('Ticket', 'vms') . '</th>';
			echo '<th>' . esc_html__('Role/Type', 'vms') . '</th>';
			echo '<th>' . esc_html__('Product ID', 'vms') . '</th>';
			echo '<th>' . esc_html__('VMS Intended', 'vms') . '</th>';
			echo '<th>' . esc_html__('Woo State', 'vms') . '</th>';
			echo '<th>' . esc_html__('TEC State', 'vms') . '</th>';
			echo '<th>' . esc_html__('Agreement', 'vms') . '</th>';
			echo '<th>' . esc_html__('Verification', 'vms') . '</th>';
			echo '<th>' . esc_html__('Woo Inventory', 'vms') . '</th>';
			echo '<th>' . esc_html__('Sales', 'vms') . '</th>';
			echo '<th>' . esc_html__('Last Write', 'vms') . '</th>';
			echo '</tr></thead><tbody>';
			foreach ($ticket_rows as $row) {
				if (!is_array($row)) {
					continue;
				}

				$last_changed = absint($row['last_changed_gmt'] ?? 0);
				echo '<tr>';
				echo '<td><strong>' . esc_html((string) ($row['ticket_label'] ?? __('Ticket', 'vms'))) . '</strong></td>';
				echo '<td>' . esc_html((string) ($row['role_label'] ?? __('Ticket', 'vms'))) . '</td>';
				echo '<td><code>#' . esc_html((string) absint($row['product_id'] ?? 0)) . '</code></td>';
				echo '<td>' . esc_html((string) ($row['vms_intended_label'] ?? '—')) . '</td>';
				echo '<td>' . esc_html((string) ($row['woo_sellability_label'] ?? '—')) . '</td>';
				echo '<td>' . esc_html((string) ($row['tec_sellability_label'] ?? '—')) . '</td>';
				echo '<td>' . esc_html((string) ($row['agreement_label'] ?? '—')) . '</td>';
				echo '<td>' . esc_html((string) ($row['verification_result_label'] ?? '—')) . '</td>';
				$woo_inventory_html = vms_ticket_integrity_render_compact_table_facts(
					array(
						__('Stock Qty', 'vms')      => vms_ticket_integrity_render_inventory_value($row['stock_qty'] ?? null),
						__('Manage Stock', 'vms')   => (string) ($row['manage_stock_label'] ?? '—'),
						__('Stock Status', 'vms')   => (string) ($row['stock_status'] ?? '—'),
					)
				);
				$sales_html = vms_ticket_integrity_render_compact_table_facts(
					array(
						__('Sold', 'vms')            => vms_ticket_integrity_render_inventory_value($row['sold_qty'] ?? null),
						__('Sold Source', 'vms')     => (string) ($row['sold_source_label'] ?? '—'),
						__('Woo total_sales', 'vms') => vms_ticket_integrity_render_inventory_value($row['woo_total_sales'] ?? null),
					)
				);
				$last_write_html = vms_ticket_integrity_render_compact_table_facts(
					array(
						__('Last Changed', 'vms')          => $last_changed > 0 ? vms_ticket_integrity_format_datetime($last_changed) : __('No log yet', 'vms'),
						__('Last Woo Write Source', 'vms') => (string) ($row['last_change_source'] ?? '—'),
						__('Last Write Reason', 'vms')     => (string) ($row['last_write_reason'] ?? '—'),
					)
				);
				echo '<td>' . $woo_inventory_html . '</td>';
				echo '<td>' . $sales_html . '</td>';
				echo '<td>' . $last_write_html . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
			echo '</div>';
		},
			array(
				/* translators: %d: number of ticket inventory rows in the per-ticket snapshot. */
				'summary_meta' => sprintf(_n('%d row', '%d rows', count($ticket_rows), 'vms'), count($ticket_rows))
			)
		);

	if (!empty($comparison)) {
		$comparison_rows = array_values((array) ($comparison['rows'] ?? array()));
		vms_ticket_integrity_render_subdetails(
			__('Healthy vs Broken Comparison', 'vms'),
			static function () use ($comparison, $comparison_rows): void {
				if (!empty($comparison['healthy_plan_id'])) {
					$comparison_note = sprintf(
						/* translators: 1: event title, 2: plan id, 3: origin label */
						__('Healthy baseline: %1$s (Plan #%2$d, %3$s).', 'vms'),
						(string) ($comparison['healthy_event_title'] ?? __('Healthy event', 'vms')),
						absint($comparison['healthy_plan_id'] ?? 0),
						(string) ($comparison['healthy_origin_label'] ?? __('VMS-native', 'vms'))
					);
					echo '<p class="vms-ticket-integrity__diagnostic-note">' . esc_html($comparison_note) . '</p>';
				}

				if (empty($comparison_rows)) {
					echo '<p class="vms-ticket-integrity__diagnostic-empty">' . esc_html__('No matched ticket rows produced a field-level difference against the healthy baseline yet.', 'vms') . '</p>';
					return;
				}

				echo '<ul class="vms-ticket-integrity__mutation-list">';
				foreach ($comparison_rows as $comparison_row) {
					if (!is_array($comparison_row)) {
						continue;
					}

					echo '<li>';
					echo '<strong>' . esc_html((string) ($comparison_row['label'] ?? __('Ticket', 'vms'))) . '</strong>';
					foreach ((array) ($comparison_row['differences'] ?? array()) as $difference) {
						if (!is_array($difference)) {
							continue;
						}

						$line = sprintf(
							/* translators: 1: field label, 2: broken value, 3: healthy value */
							__('%1$s: broken %2$s / healthy %3$s', 'vms'),
							(string) ($difference['label'] ?? __('Field', 'vms')),
							(string) ($difference['broken'] ?? '—'),
							(string) ($difference['healthy'] ?? '—')
						);
						echo '<div class="vms-ticket-integrity__issue-detail">' . esc_html($line) . '</div>';
					}
					echo '</li>';
				}
				echo '</ul>';
			},
			array(
				/* translators: %d: number of comparison rows against the healthy ticket baseline. */
				'summary_meta' => sprintf(_n('%d row', '%d rows', count($comparison_rows), 'vms'), count($comparison_rows))
			)
		);
	}

	vms_ticket_integrity_render_subdetails(
		__('Recent Inventory Mutations', 'vms'),
		static function () use ($recent_mutations): void {
			if (empty($recent_mutations)) {
				echo '<p class="vms-ticket-integrity__diagnostic-empty">' . esc_html__('No inventory mutation history has been recorded for this event yet.', 'vms') . '</p>';
				return;
			}

			echo '<ul class="vms-ticket-integrity__mutation-list">';
			foreach ($recent_mutations as $mutation) {
				if (!is_array($mutation)) {
					continue;
				}

				$headline = trim((string) ($mutation['change_type_label'] ?? __('Inventory mutation', 'vms')));
				$product_id = absint($mutation['product_id'] ?? 0);
					if ($product_id > 0) {
						/* translators: %d: WooCommerce product ID tied to the inventory mutation. */
						$headline .= ' / ' . sprintf(__('Product #%d', 'vms'), $product_id);
					}

				$time_text = vms_ticket_integrity_format_datetime(absint($mutation['timestamp_gmt'] ?? 0));
				$source_text = vms_ticket_integrity_format_audit_source($mutation);
				$summary_text = trim((string) ($mutation['summary_text'] ?? ''));

				echo '<li>';
				echo '<strong>' . esc_html($headline) . '</strong>';
				echo '<div class="vms-ticket-integrity__issue-detail">' . esc_html($time_text) . '</div>';
				if ($source_text !== '') {
					echo '<div class="vms-ticket-integrity__issue-detail">' . esc_html($source_text) . '</div>';
				}
				if ($summary_text !== '') {
					echo '<div class="vms-ticket-integrity__issue-detail">' . esc_html($summary_text) . '</div>';
				}
				echo '</li>';
			}
			echo '</ul>';
		},
			array(
				/* translators: %d: number of recent inventory mutation log entries. */
				'summary_meta' => sprintf(_n('%d entry', '%d entries', count($recent_mutations), 'vms'), count($recent_mutations))
			)
		);
	echo '</section>';
}

function vms_ticket_integrity_render_repair_diagnostics(array $event): void
{
	$report = is_array($event['repair_diagnostics'] ?? null) ? $event['repair_diagnostics'] : array();
	if (empty($report)) {
		return;
	}

	$warnings = array_values(array_filter(array_map('strval', (array) ($report['warnings'] ?? array()))));
	$detail_state = trim((string) ($report['detail_state_label'] ?? ''));

	echo '<section class="vms-ticket-integrity__diagnostics" data-vms-tour="ticket-integrity.repair-diagnostics">';
	echo '<h4>' . esc_html__('Repair Diagnostics', 'vms') . '</h4>';
	echo '<div class="vms-ticket-integrity__diagnostic-grid">';
	vms_ticket_integrity_render_diagnostic_meta(
		__('Last Rebuild Result', 'vms'),
		trim((string) ($report['repair_status_label'] ?? __('Unknown', 'vms')))
	);
	vms_ticket_integrity_render_diagnostic_meta(
		__('Saved', 'vms'),
		!empty($report['saved_at_gmt']) ? vms_ticket_integrity_format_datetime(absint($report['saved_at_gmt'])) : __('No timestamp', 'vms')
	);
	vms_ticket_integrity_render_diagnostic_meta(
		__('Preview Change Count', 'vms'),
		(string) absint($report['preview_change_count'] ?? 0)
	);
	vms_ticket_integrity_render_diagnostic_meta(
		__('Remaining Issue Summary', 'vms'),
		trim((string) ($report['remaining_issue_summary'] ?? __('No summary stored', 'vms')))
	);
	vms_ticket_integrity_render_diagnostic_meta(
		__('Woo Verification', 'vms'),
		trim((string) ($report['woo_verification_label'] ?? __('Not stored', 'vms')))
	);
	vms_ticket_integrity_render_diagnostic_meta(
		__('TEC Verification', 'vms'),
		trim((string) ($report['tec_verification_label'] ?? __('Not stored', 'vms')))
	);
	echo '</div>';

	$summary_text = trim((string) ($report['summary_text'] ?? ''));
	if ($summary_text !== '') {
		echo '<p class="vms-ticket-integrity__diagnostic-note">' . esc_html($summary_text) . '</p>';
	}

	if ($detail_state !== '') {
		echo '<p class="vms-ticket-integrity__diagnostic-note vms-ticket-integrity__diagnostic-note--warning">' . esc_html($detail_state) . '</p>';
	}

	if (!empty($report['woo_recorruption_detected'])) {
		echo '<p class="vms-ticket-integrity__diagnostic-note vms-ticket-integrity__diagnostic-note--warning">' . esc_html__('Woo was repaired successfully at rebuild time, but a later write has since re-closed inventory.', 'vms') . '</p>';
	}

	$repair_writer = is_array($report['upstream_writer_suspect'] ?? null) ? $report['upstream_writer_suspect'] : array();
	if (!empty($repair_writer)) {
		$source_text = trim((string) ($repair_writer['source_function'] ?? $repair_writer['source_hook'] ?? ''));
		$reason_text = trim((string) ($repair_writer['reason_text'] ?? ''));
		$parts = array();
			if ($source_text !== '') {
				/* translators: %s: function or hook name suspected of re-closing repaired inventory. */
				$parts[] = sprintf(__('Likely upstream writer: %s', 'vms'), $source_text);
			}
			if ($reason_text !== '') {
				/* translators: %s: most recent reason recorded for the conflicting post-repair write. */
				$parts[] = sprintf(__('Last conflicting reason: %s', 'vms'), $reason_text);
			}
		if (!empty($parts)) {
			echo '<p class="vms-ticket-integrity__diagnostic-note">' . esc_html(implode(' / ', $parts)) . '</p>';
		}
	}

	echo '<div class="vms-ticket-integrity__diagnostic-columns">';
	foreach ((array) ($report['role_breakdown'] ?? array()) as $role_key => $role_group) {
		if (!is_array($role_group)) {
			continue;
		}

		$entries = array_values((array) ($role_group['entries'] ?? array()));
		vms_ticket_integrity_render_subdetails(
			(string) ($role_group['label'] ?? $role_key),
			static function () use ($role_group, $entries): void {
				echo '<div class="vms-ticket-integrity__issue-detail">' . esc_html(
					sprintf(
						/* translators: 1: attempted, 2: succeeded, 3: skipped, 4: no effect, 5: partial, 6: failed */
						__('Attempted %1$d / Succeeded %2$d / Skipped %3$d / No effect %4$d / Partial %5$d / Failed %6$d', 'vms'),
						absint($role_group['attempted'] ?? 0),
						absint($role_group['succeeded'] ?? 0),
						absint($role_group['skipped'] ?? 0),
						absint($role_group['no_effect'] ?? 0),
						absint($role_group['partial'] ?? 0),
						absint($role_group['failed'] ?? 0)
					)
				) . '</div>';
				echo '<div class="vms-ticket-integrity__issue-detail">' . esc_html(
					sprintf(
						/* translators: 1: entered count, 2: not entered count, 3: blocked count */
						__('Branch entered %1$d / Branch not entered %2$d / Branch blocked %3$d', 'vms'),
						absint($role_group['branch_entered'] ?? 0),
						absint($role_group['branch_not_entered'] ?? 0),
						absint($role_group['branch_blocked'] ?? 0)
					)
				) . '</div>';

				if (empty($entries)) {
					echo '<p class="vms-ticket-integrity__diagnostic-empty">' . esc_html__('No entries recorded for this role in the last rebuild.', 'vms') . '</p>';
					return;
				}

				echo '<ul class="vms-ticket-integrity__mutation-list">';
				foreach ($entries as $entry) {
					if (!is_array($entry)) {
						continue;
					}

					echo '<li>';
					echo '<strong>' . esc_html((string) ($entry['label'] ?? __('Role entry', 'vms'))) . '</strong>';
					echo '<div class="vms-ticket-integrity__issue-detail">' . esc_html(
						sprintf(
							/* translators: 1: action, 2: branch label, 3: result label, 4: product id */
							__('Action: %1$s / Branch: %2$s / Result: %3$s / Product #%4$d', 'vms'),
							(string) ($entry['preview_action'] ?? 'noop'),
							(string) ($entry['branch_status_label'] ?? __('Branch not entered', 'vms')),
							(string) ($entry['result_label'] ?? __('Unknown', 'vms')),
							absint($entry['product_id'] ?? 0)
						)
					) . '</div>';
					$skip_reason = trim((string) ($entry['skip_reason_label'] ?? ''));
					if ($skip_reason !== '') {
						echo '<div class="vms-ticket-integrity__issue-detail">' . esc_html(
							sprintf(
								/* translators: 1: skip reason, 2: expected flag, 3: safety flag */
								__('Skip reason: %1$s / Expected skip: %2$s / Safety-driven: %3$s', 'vms'),
								$skip_reason,
								!empty($entry['skip_expected']) ? __('Yes', 'vms') : __('No', 'vms'),
								!empty($entry['skip_safety_driven']) ? __('Yes', 'vms') : __('No', 'vms')
							)
						) . '</div>';
					}
					echo '<div class="vms-ticket-integrity__issue-detail">' . esc_html(
						sprintf(
							/* translators: 1: source value, 2: derivation source, 3: confidence, 4: writer branch, 5: result health */
							__('Source value: %1$s / Derivation: %2$s / Confidence: %3$s / Writer branch: %4$s / Result health: %5$s', 'vms'),
							(string) ($entry['source_value'] ?? '—'),
							(string) ($entry['derivation_source_label'] ?? $entry['derivation_source'] ?? 'unknown'),
							(string) ($entry['confidence_label'] ?? $entry['confidence_level'] ?? 'unknown'),
							(string) ($entry['writer_branch_label'] ?? __('Not recorded', 'vms')),
							(string) ($entry['result_health_label'] ?? __('Not recorded', 'vms'))
						)
					) . '</div>';
					if (
						array_key_exists('final_stock_qty', $entry)
						|| trim((string) ($entry['final_stock_status'] ?? '')) !== ''
						|| array_key_exists('final_manage_stock', $entry)
					) {
						echo '<div class="vms-ticket-integrity__issue-detail">' . esc_html(
							sprintf(
								/* translators: 1: stock qty, 2: stock status, 3: manage stock label */
								__('Final inventory: stock %1$s / status %2$s / manage stock %3$s', 'vms'),
								vms_ticket_integrity_render_inventory_value($entry['final_stock_qty'] ?? null),
								(string) ($entry['final_stock_status'] ?? '—'),
								(string) ($entry['final_manage_stock_label'] ?? '—')
							)
						) . '</div>';
					}
					$reason_text = trim((string) ($entry['reason_text'] ?? ''));
					if ($reason_text !== '') {
						echo '<div class="vms-ticket-integrity__issue-detail">' . esc_html($reason_text) . '</div>';
					}
					echo '</li>';
				}
				echo '</ul>';
			},
				array(
					/* translators: %d: number of repair log entries recorded for the current ticket role. */
					'summary_meta' => sprintf(_n('%d entry', '%d entries', count($entries), 'vms'), count($entries)),
					'class' => 'vms-ticket-integrity__subdetails--role'
				)
			);
	}
	echo '</div>';

	if (!empty($warnings)) {
		vms_ticket_integrity_render_subdetails(
			__('Repair Warnings', 'vms'),
			static function () use ($warnings): void {
				echo '<ul class="vms-ticket-integrity__mutation-list">';
				foreach ($warnings as $warning) {
					echo '<li>' . esc_html($warning) . '</li>';
				}
				echo '</ul>';
			},
				array(
					/* translators: %d: number of repair warnings shown for the current event. */
					'summary_meta' => sprintf(_n('%d warning', '%d warnings', count($warnings), 'vms'), count($warnings)),
				)
			);
	}

	echo '</section>';
}

function vms_ticket_integrity_render_results_table(array $events, int $focused_plan_id = 0): void
{
	echo '<section class="vms-ticket-integrity__panel" data-vms-tour="ticket-integrity.table">';
	echo '<h2>' . esc_html__('Results', 'vms') . '</h2>';
	echo '<div class="vms-ticket-integrity__results-table-wrap">';
	echo '<table class="widefat striped vms-ticket-integrity__results-table">';
	echo '<thead><tr>';
	echo '<th>' . esc_html__('Status', 'vms') . '</th>';
	echo '<th>' . esc_html__('Event', 'vms') . '</th>';
	echo '<th>' . esc_html__('Event Date', 'vms') . '</th>';
	echo '<th>' . esc_html__('Issue Summary', 'vms') . '</th>';
	echo '<th>' . esc_html__('First Detected', 'vms') . '</th>';
	echo '<th>' . esc_html__('Last Detected', 'vms') . '</th>';
	echo '<th>' . esc_html__('Actions', 'vms') . '</th>';
	echo '</tr></thead><tbody>';

	if (empty($events)) {
		echo '<tr><td colspan="7">' . esc_html__('No scan results are stored yet. Run the monitor to populate this table.', 'vms') . '</td></tr>';
		echo '</tbody></table>';
		echo '</div>';
		echo '</section>';
		return;
	}

	foreach ($events as $event) {
		$plan_id = absint($event['plan_id'] ?? 0);
		$status = (string) ($event['status'] ?? 'green');
		$issues = is_array($event['issues'] ?? null) ? $event['issues'] : array();
		$open_issues = vms_ticket_integrity_open_issues($issues);
		$first_detected = absint($event['first_detected_gmt'] ?? vms_ticket_integrity_issue_first_detected($issues));
		$last_detected = absint($event['last_detected_gmt'] ?? vms_ticket_integrity_issue_last_detected($issues));
		$event_row_id = 'vms-ticket-integrity-event-' . $plan_id;
		$event_url = (string) ($event['event_url'] ?? '');
		$edit_plan_url = (string) ($event['edit_plan_url'] ?? '');
		$mode = sanitize_key((string) ($event['mode'] ?? ''));
		$row_class = ($focused_plan_id > 0 && $focused_plan_id === $plan_id) ? ' class="vms-ticket-integrity__focused-row"' : '';

		echo '<tr id="' . esc_attr($event_row_id) . '"' . $row_class . '>';
		echo '<td><span class="' . esc_attr(vms_ticket_integrity_status_css_class($status)) . '">' . esc_html(vms_ticket_integrity_status_label($status)) . '</span></td>';
		echo '<td>';
		echo '<strong>' . esc_html((string) ($event['event_title'] ?? __('Untitled event', 'vms'))) . '</strong>';
		if ($event_url !== '') {
			echo '<div><a href="' . esc_url($event_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('View Event', 'vms') . '</a></div>';
		}
		echo '</td>';
		echo '<td>' . esc_html((string) ($event['event_date_local'] ?? '')) . '</td>';
		echo '<td>' . esc_html((string) ($event['issue_summary'] ?? __('No issues detected.', 'vms'))) . '</td>';
		echo '<td>' . esc_html(vms_ticket_integrity_format_datetime($first_detected)) . '</td>';
		echo '<td>' . esc_html(vms_ticket_integrity_format_datetime($last_detected)) . '</td>';
		echo '<td>';
		echo '<div class="vms-ticket-integrity__actions">';
		if ($edit_plan_url !== '') {
			echo '<a class="button button-small" href="' . esc_url($edit_plan_url) . '">' . esc_html__('Edit Tickets', 'vms') . '</a>';
		}

		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-ticket-integrity__inline-form">';
		wp_nonce_field('vms_ticket_integrity_run_event_scan');
		echo '<input type="hidden" name="action" value="vms_ticket_integrity_run_event_scan" />';
		echo '<input type="hidden" name="plan_id" value="' . esc_attr((string) $plan_id) . '" />';
		echo '<button type="submit" class="button button-small">' . esc_html__('Re-run Scan', 'vms') . '</button>';
		echo '</form>';

		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-ticket-integrity__inline-form">';
		wp_nonce_field('vms_ticket_integrity_export_report');
		echo '<input type="hidden" name="action" value="vms_ticket_integrity_export_report" />';
		echo '<input type="hidden" name="plan_id" value="' . esc_attr((string) $plan_id) . '" />';
		echo '<button type="submit" class="button button-small">' . esc_html__('Download Report', 'vms') . '</button>';
		echo '</form>';

		$has_duplicate_cleanup_issue = false;
		foreach ($open_issues as $open_issue) {
			$issue_key = sanitize_key((string) ($open_issue['key'] ?? ''));
			if (in_array($issue_key, array('duplicate_live_ticket_products_attached', 'extra_public_ticket_products_attached'), true)) {
				$has_duplicate_cleanup_issue = true;
				break;
			}
		}

		if ($mode === 'vms_managed') {
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-ticket-integrity__inline-form" data-vms-ticket-integrity-confirm="rebuild">';
			wp_nonce_field('vms_ticket_integrity_rebuild');
			echo '<input type="hidden" name="action" value="vms_ticket_integrity_rebuild" />';
			echo '<input type="hidden" name="plan_id" value="' . esc_attr((string) $plan_id) . '" />';
			echo '<button type="submit" class="button button-small button-secondary" data-vms-tour="ticket-integrity.rebuild">' . esc_html__('Rebuild Ticket Config', 'vms') . '</button>';
			echo '</form>';

			if ($has_duplicate_cleanup_issue) {
				echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-ticket-integrity__inline-form" data-vms-ticket-integrity-confirm="cleanup-duplicates">';
				wp_nonce_field('vms_ticket_integrity_cleanup_duplicates');
				echo '<input type="hidden" name="action" value="vms_ticket_integrity_cleanup_duplicates" />';
				echo '<input type="hidden" name="plan_id" value="' . esc_attr((string) $plan_id) . '" />';
				echo '<button type="submit" class="button button-small button-link-delete">' . esc_html__('Resolve Duplicate Legacy Tickets', 'vms') . '</button>';
				echo '</form>';
			}
		} else {
			echo '<span class="vms-ticket-integrity__action-note">' . esc_html__('Rebuild available in VMS-managed mode only.', 'vms') . '</span>';
		}
		echo '</div>';
		echo '</td>';
		echo '</tr>';

		echo '<tr class="vms-ticket-integrity__details-row">';
		echo '<td colspan="7">';
		echo '<details class="vms-ticket-integrity__details" data-vms-tour="ticket-integrity.details">';
		echo '<summary>' . esc_html__('View Details', 'vms') . '</summary>';

		if (!empty($open_issues)) {
			echo '<ul class="vms-ticket-integrity__issue-list">';
			foreach ($open_issues as $issue) {
				echo '<li>';
				echo '<span class="' . esc_attr(vms_ticket_integrity_status_css_class((string) ($issue['severity'] ?? ''))) . '">' . esc_html(vms_ticket_integrity_status_label((string) ($issue['severity'] ?? ''))) . '</span> ';
				echo '<strong>' . esc_html((string) ($issue['title'] ?? __('Issue', 'vms'))) . '</strong>';
				$details = trim((string) ($issue['details'] ?? ''));
				if ($details !== '') {
					echo '<div class="vms-ticket-integrity__issue-detail">' . esc_html($details) . '</div>';
				}
				echo '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p>' . esc_html__('No open issues are currently stored for this event.', 'vms') . '</p>';
		}

		$product_provenance = is_array($event['product_provenance'] ?? null) ? $event['product_provenance'] : array();
		if (!empty($product_provenance)) {
			echo '<h4>' . esc_html__('Linked Product Provenance', 'vms') . '</h4>';
			echo '<ul class="vms-ticket-integrity__product-list">';
			foreach ($product_provenance as $product) {
				$product_id = absint($product['product_id'] ?? 0);
				echo '<li>';
				echo '<strong>' . esc_html((string) ($product['title'] ?? __('Untitled product', 'vms'))) . '</strong> ';
				echo '<code>#' . esc_html((string) $product_id) . '</code>';
				if (!empty($product['labels']) && is_array($product['labels'])) {
					echo '<span class="vms-ticket-integrity__labels">';
					foreach ($product['labels'] as $label) {
						echo '<span class="vms-ticket-integrity__label">' . esc_html((string) $label) . '</span>';
					}
					echo '</span>';
				}
				echo '</li>';
			}
			echo '</ul>';
		}

		vms_ticket_integrity_render_mutation_diagnostics($event);
		vms_ticket_integrity_render_repair_diagnostics($event);
		vms_ticket_integrity_render_inventory_diagnostics($event);

		echo '</details>';
		echo '</td>';
		echo '</tr>';
	}

	echo '</tbody></table>';
	echo '</div>';
	echo '</section>';
}

function vms_ticket_integrity_render_audit_log(array $logs): void
{
	echo '<section class="vms-ticket-integrity__panel">';
	echo '<h2>' . esc_html__('Recent Audit Log', 'vms') . '</h2>';

	if (empty($logs)) {
		echo '<p>' . esc_html__('No audit entries are recorded yet.', 'vms') . '</p>';
		echo '</section>';
		return;
	}

	echo '<ul class="vms-ticket-integrity__audit-list">';
	foreach (array_slice($logs, 0, 12) as $entry) {
		if (!is_array($entry)) {
			continue;
		}

		echo '<li>';
		echo '<strong>' . esc_html(vms_ticket_integrity_format_datetime(absint($entry['timestamp_gmt'] ?? 0))) . '</strong> ';
		echo '<code>' . esc_html((string) ($entry['type'] ?? 'event')) . '</code> ';
		echo esc_html((string) ($entry['message'] ?? ''));
		echo '</li>';
	}
	echo '</ul>';
	echo '</section>';
}


function vms_ticket_integrity_menu_alert_needed(): bool
{
	if (function_exists('vms_ticket_integrity_payment_gateway_menu_alert_needed') && vms_ticket_integrity_payment_gateway_menu_alert_needed()) {
		return true;
	}

	$settings = function_exists('vms_ticket_integrity_get_settings') ? vms_ticket_integrity_get_settings() : array();
	if (!empty($settings['email_alerts_enabled'])) {
		return false;
	}

	$store = function_exists('vms_ticket_integrity_get_results_store') ? vms_ticket_integrity_get_results_store() : array();
	$summary = is_array($store['summary'] ?? null) ? $store['summary'] : array();
	$problem_events = absint($summary['red'] ?? 0) + absint($summary['yellow'] ?? 0);
	return $problem_events > 0;
}

function vms_ticket_integrity_menu_alert_markup(): string
{
	$inner = '<span class="plugin-count">!</span>';
	return ' <span class="update-plugins count-1 vms-ticket-integrity-alert-badge"><span class="update-count">' . $inner . '</span></span><span class="screen-reader-text">' . esc_html__('Ticket Integrity has critical payment gateway health or open monitor issues.', 'vms') . '</span>';
}

function vms_ticket_integrity_add_menu_alert_badge(): void
{
	if (!current_user_can('manage_options') || !vms_ticket_integrity_menu_alert_needed()) {
		return;
	}

	global $submenu, $menu;
	$markup = vms_ticket_integrity_menu_alert_markup();

	if (isset($submenu['vms-dashboard']) && is_array($submenu['vms-dashboard'])) {
		foreach ($submenu['vms-dashboard'] as $index => $item) {
			if (($item[2] ?? '') !== 'vms-ticket-integrity') {
				continue;
			}
			if (strpos((string) ($item[0] ?? ''), 'vms-ticket-integrity-alert-badge') !== false) {
				break;
			}
			$submenu['vms-dashboard'][$index][0] = (string) ($item[0] ?? __('Ticket Integrity', 'vms')) . $markup;
			break;
		}
	}

	if (is_array($menu)) {
		foreach ($menu as $index => $item) {
			if (($item[2] ?? '') !== 'vms-dashboard') {
				continue;
			}
			if (strpos((string) ($item[0] ?? ''), 'vms-ticket-integrity-alert-badge') !== false) {
				break;
			}
			$menu[$index][0] = (string) ($item[0] ?? 'VMS') . $markup;
			break;
		}
	}
}
add_action('admin_menu', 'vms_ticket_integrity_add_menu_alert_badge', 1001);

function vms_ticket_integrity_render_menu_alert_badge_css(): void
{
	if (!current_user_can('manage_options') || !vms_ticket_integrity_menu_alert_needed()) {
		return;
	}

	echo '<style id="vms-ticket-integrity-alert-dot-css">';
	echo '#adminmenu .vms-ticket-integrity-alert-badge{margin-left:6px;min-width:18px;height:18px;line-height:18px;border-radius:999px;background:#d63638;box-shadow:none;}';
	echo '#adminmenu .vms-ticket-integrity-alert-badge .update-count,#adminmenu .vms-ticket-integrity-alert-badge .plugin-count{display:block;min-width:18px;height:18px;line-height:18px;padding:0 4px;color:#fff;font-size:11px;font-weight:700;text-align:center;}';
	echo '</style>';
}
add_action('admin_head', 'vms_ticket_integrity_render_menu_alert_badge_css', 20);

function vms_ticket_integrity_render_admin_page(): void
{
	$settings = function_exists('vms_ticket_integrity_get_settings') ? vms_ticket_integrity_get_settings() : array();
	$store = function_exists('vms_ticket_integrity_get_results_store') ? vms_ticket_integrity_get_results_store() : array('events' => array(), 'summary' => array(), 'last_scan' => array());
	if (function_exists('vms_ticket_integrity_prepare_payment_gateway_health')) {
		$store['payment_gateway_health'] = vms_ticket_integrity_prepare_payment_gateway_health('admin_page', 20 * MINUTE_IN_SECONDS);
	}
	$summary = is_array($store['summary'] ?? null) ? $store['summary'] : array();
	$last_scan = is_array($store['last_scan'] ?? null) ? $store['last_scan'] : array();
	$payment_gateway_health = is_array($store['payment_gateway_health'] ?? null) ? $store['payment_gateway_health'] : array();
	$events = function_exists('vms_ticket_integrity_get_sorted_events') ? vms_ticket_integrity_get_sorted_events() : array();
	$logs = function_exists('vms_ticket_integrity_get_logs') ? vms_ticket_integrity_get_logs() : array();

	$filter_plan_id = absint(wp_unslash((string) ($_GET['event'] ?? 0)));
	if ($filter_plan_id > 0) {
		$events = array_values(array_filter($events, static function (array $event) use ($filter_plan_id): bool {
			return absint($event['plan_id'] ?? 0) === $filter_plan_id;
		}));
	}

	$tour_button = function_exists('vms_render_help_button')
		? vms_render_help_button(
			array(
				'tour_id' => 'vms.ticket_integrity.monitor',
				'anchor' => 'ticket-integrity.help',
				'label' => __('Start Guided Tour', 'vms'),
				'class' => 'button-secondary',
			)
		)
		: '<button type="button" class="button button-secondary vms-tour-help-trigger" data-vms-tour-start="vms.ticket_integrity.monitor" data-vms-tour="ticket-integrity.help">' . esc_html__('Start Guided Tour', 'vms') . '</button>';

	$actions_html = '<div class="vms-ticket-integrity__header-actions" data-vms-tour="ticket-integrity.help">' . $tour_button . '</div>';

	if (function_exists('vms_admin_ui_render_shell')) {
		vms_admin_ui_render_shell(
			array(
				'title' => __('Ticket Integrity', 'vms'),
				'subtitle' => __('Proactively scan published upcoming events for customer-facing ticketing failures before sales are lost.', 'vms'),
				'shell_id' => 'vms-ticket-integrity',
				'content_class' => 'vms-ticket-integrity-page',
				'actions_html' => $actions_html,
			),
			static function () use ($settings, $summary, $last_scan, $payment_gateway_health, $events, $logs, $filter_plan_id): void {
				vms_ticket_integrity_render_notice_from_query();

				echo '<section class="vms-ticket-integrity__panel" data-vms-tour="ticket-integrity.run">';
				echo '<h2>' . esc_html__('Run Ticket Integrity Check Now', 'vms') . '</h2>';
				echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-ticket-integrity__run-form">';
				wp_nonce_field('vms_ticket_integrity_run_scan');
				echo '<input type="hidden" name="action" value="vms_ticket_integrity_run_scan" />';
				echo '<label><input type="checkbox" name="include_inactive" value="1" /> ' . esc_html__('Include cancelled / inactive events on this manual run', 'vms') . '</label>';
				echo '<button type="submit" class="button button-primary">' . esc_html__('Run Check Now', 'vms') . '</button>';
				echo '</form>';
				echo '</section>';

				vms_ticket_integrity_render_summary_cards($summary, $last_scan);
				vms_ticket_integrity_render_payment_gateway_health_panel($payment_gateway_health);
				vms_ticket_integrity_render_daily_report_status_panel($settings);
				vms_ticket_integrity_render_daily_report_preview_panel();

				if ($filter_plan_id > 0) {
					echo '<p class="vms-ticket-integrity__filter-note">' . esc_html__('Showing a single event from the dashboard/problem list.', 'vms') . ' <a href="' . esc_url(vms_ticket_integrity_admin_url()) . '">' . esc_html__('Clear filter', 'vms') . '</a></p>';
				}

				vms_ticket_integrity_render_settings_form($settings);
				vms_ticket_integrity_render_results_table($events, $filter_plan_id);
				vms_ticket_integrity_render_audit_log($logs);
			}
		);
		return;
	}

	echo '<div class="wrap"><h1>' . esc_html__('Ticket Integrity', 'vms') . '</h1></div>';
}
