<?php
defined('ABSPATH') || exit;

function vms_ticket_integrity_settings_option_key(): string
{
	return 'vms_ticket_integrity_settings';
}

function vms_ticket_integrity_results_option_key(): string
{
	return 'vms_ticket_integrity_results';
}

function vms_ticket_integrity_log_option_key(): string
{
	return 'vms_ticket_integrity_log';
}

function vms_ticket_integrity_scan_lock_key(): string
{
	return 'vms_ticket_integrity_scan_lock';
}

function vms_ticket_integrity_admin_url(array $args = array()): string
{
	return add_query_arg($args, admin_url('admin.php?page=vms-ticket-integrity'));
}

function vms_ticket_integrity_defaults(): array
{
	return array(
		'nightly_enabled' => 1,
		'days_ahead' => 120,
		'email_alerts_enabled' => 0,
		'alert_recipient' => '',
		'send_resolved_notifications' => 0,
		'reminder_interval_hours' => 24,
		'include_yellow_in_email_alerts' => 0,
		'daily_report_enabled' => 1,
		'daily_report_recipient' => '',
		'low_inventory_email_alerts_enabled' => 1,
		'low_inventory_threshold' => 25,
		'low_inventory_percent_threshold' => 10,
		'critical_inventory_threshold' => 5,
		'critical_inventory_percent_threshold' => 3,
		'payment_gateway_health_enabled' => 1,
		'payment_gateway_health_interval' => 'vms_ticket_integrity_fifteen_minutes',
	);
}

function vms_ticket_integrity_sanitize_settings(array $raw): array
{
	$defaults = vms_ticket_integrity_defaults();
	$settings = array();

	$settings['nightly_enabled'] = !empty($raw['nightly_enabled']) ? 1 : 0;
	$settings['days_ahead'] = max(1, min(365, absint($raw['days_ahead'] ?? $defaults['days_ahead'])));
	$settings['email_alerts_enabled'] = !empty($raw['email_alerts_enabled']) ? 1 : 0;
	$settings['alert_recipient'] = sanitize_email((string) ($raw['alert_recipient'] ?? ''));
	$settings['send_resolved_notifications'] = !empty($raw['send_resolved_notifications']) ? 1 : 0;
	$settings['reminder_interval_hours'] = max(1, min(168, absint($raw['reminder_interval_hours'] ?? $defaults['reminder_interval_hours'])));
	$settings['include_yellow_in_email_alerts'] = !empty($raw['include_yellow_in_email_alerts']) ? 1 : 0;
	$settings['daily_report_enabled'] = !empty($raw['daily_report_enabled']) ? 1 : 0;
	$settings['daily_report_recipient'] = sanitize_email((string) ($raw['daily_report_recipient'] ?? ''));
	$settings['low_inventory_email_alerts_enabled'] = !empty($raw['low_inventory_email_alerts_enabled']) ? 1 : 0;
	$settings['low_inventory_threshold'] = max(1, min(10000, absint($raw['low_inventory_threshold'] ?? $defaults['low_inventory_threshold'])));
	$settings['low_inventory_percent_threshold'] = max(1, min(100, absint($raw['low_inventory_percent_threshold'] ?? $defaults['low_inventory_percent_threshold'])));
	$settings['critical_inventory_threshold'] = max(1, min(10000, absint($raw['critical_inventory_threshold'] ?? $defaults['critical_inventory_threshold'])));
	$settings['critical_inventory_percent_threshold'] = max(1, min(100, absint($raw['critical_inventory_percent_threshold'] ?? $defaults['critical_inventory_percent_threshold'])));
	$settings['payment_gateway_health_enabled'] = !empty($raw['payment_gateway_health_enabled']) ? 1 : 0;
	$interval = sanitize_key((string) ($raw['payment_gateway_health_interval'] ?? $defaults['payment_gateway_health_interval']));
	$settings['payment_gateway_health_interval'] = in_array($interval, array('vms_ticket_integrity_fifteen_minutes', 'hourly'), true)
		? $interval
		: $defaults['payment_gateway_health_interval'];
	if ($settings['critical_inventory_threshold'] > $settings['low_inventory_threshold']) {
		$settings['critical_inventory_threshold'] = $settings['low_inventory_threshold'];
	}
	if ($settings['critical_inventory_percent_threshold'] > $settings['low_inventory_percent_threshold']) {
		$settings['critical_inventory_percent_threshold'] = $settings['low_inventory_percent_threshold'];
	}

	return $settings;
}

function vms_ticket_integrity_get_settings(): array
{
	$stored = get_option(vms_ticket_integrity_settings_option_key(), array());
	if (!is_array($stored)) {
		$stored = array();
	}

	return vms_ticket_integrity_sanitize_settings(array_merge(vms_ticket_integrity_defaults(), $stored));
}

function vms_ticket_integrity_update_settings(array $raw): array
{
	$settings = vms_ticket_integrity_sanitize_settings($raw);
	update_option(vms_ticket_integrity_settings_option_key(), $settings, false);
	return $settings;
}

function vms_ticket_integrity_status_rank(string $status): int
{
	$status = sanitize_key($status);
	switch ($status) {
		case 'red':
			return 40;
		case 'yellow':
			return 30;
		case 'informational':
			return 20;
		case 'green':
			return 10;
		default:
			return 0;
	}
}

function vms_ticket_integrity_status_label(string $status): string
{
	$status = sanitize_key($status);
	switch ($status) {
		case 'red':
			return __('Red', 'backstage-venue-manager');
		case 'yellow':
			return __('Yellow', 'backstage-venue-manager');
		case 'informational':
			return __('Informational', 'backstage-venue-manager');
		case 'green':
			return __('Green', 'backstage-venue-manager');
		default:
			return __('Unknown', 'backstage-venue-manager');
	}
}

function vms_ticket_integrity_status_css_class(string $status): string
{
	$status = sanitize_html_class(sanitize_key($status));
	return 'vms-ticket-integrity__status vms-ticket-integrity__status--' . ($status !== '' ? $status : 'unknown');
}

function vms_ticket_integrity_sort_issues(array $issues): array
{
	usort(
		$issues,
		static function (array $a, array $b): int {
			$a_rank = vms_ticket_integrity_status_rank((string) ($a['severity'] ?? ''));
			$b_rank = vms_ticket_integrity_status_rank((string) ($b['severity'] ?? ''));
			if ($a_rank !== $b_rank) {
				return $b_rank <=> $a_rank;
			}

			$a_title = (string) ($a['title'] ?? '');
			$b_title = (string) ($b['title'] ?? '');
			return strcasecmp($a_title, $b_title);
		}
	);

	return $issues;
}

function vms_ticket_integrity_issue_status(array $issue): string
{
	$status = sanitize_key((string) ($issue['status'] ?? 'open'));
	return ($status === 'resolved') ? 'resolved' : 'open';
}

function vms_ticket_integrity_open_issues(array $issues): array
{
	$out = array();
	foreach ($issues as $issue) {
		if (!is_array($issue) || vms_ticket_integrity_issue_status($issue) !== 'open') {
			continue;
		}
		$out[] = $issue;
	}

	return vms_ticket_integrity_sort_issues($out);
}

function vms_ticket_integrity_status_from_issues(array $issues): string
{
	$open_issues = vms_ticket_integrity_open_issues($issues);
	if (empty($open_issues)) {
		return 'green';
	}

	$top = reset($open_issues);
	$severity = sanitize_key((string) ($top['severity'] ?? ''));
	if (in_array($severity, array('red', 'yellow', 'informational'), true)) {
		return $severity;
	}

	return 'yellow';
}

function vms_ticket_integrity_issue_summary(array $issues): string
{
	$open_issues = vms_ticket_integrity_open_issues($issues);
	if (empty($open_issues)) {
		return __('No issues detected.', 'backstage-venue-manager');
	}

	$titles = array();
	foreach ($open_issues as $issue) {
		$title = trim((string) ($issue['title'] ?? ''));
		if ($title === '') {
			continue;
		}
		$titles[] = $title;
		if (count($titles) >= 2) {
			break;
		}
	}

	if (empty($titles)) {
		/* translators: %d: number of open issues. */
		return sprintf(_n('%d open issue', '%d open issues', count($open_issues), 'backstage-venue-manager'), count($open_issues));
	}

	$summary = implode('; ', $titles);
	$remaining = count($open_issues) - count($titles);
	if ($remaining > 0) {
		/* translators: %d: number of remaining issues */
		$summary .= ' ' . sprintf(__('(+%d more)', 'backstage-venue-manager'), $remaining);
	}

	return $summary;
}

function vms_ticket_integrity_issue_first_detected(array $issues): int
{
	$first = 0;
	foreach ($issues as $issue) {
		if (!is_array($issue)) {
			continue;
		}
		$value = absint($issue['first_detected_gmt'] ?? 0);
		if ($value <= 0) {
			continue;
		}
		if ($first === 0 || $value < $first) {
			$first = $value;
		}
	}

	return $first;
}

function vms_ticket_integrity_issue_last_detected(array $issues): int
{
	$last = 0;
	foreach ($issues as $issue) {
		if (!is_array($issue)) {
			continue;
		}
		$value = absint($issue['last_detected_gmt'] ?? 0);
		if ($value > $last) {
			$last = $value;
		}
	}

	return $last;
}

function vms_ticket_integrity_log_event(string $type, string $message, array $context = array()): void
{
	$type = sanitize_key($type);
	$message = sanitize_text_field($message);

	$entry = array(
		'timestamp_gmt' => time(),
		'type' => $type,
		'message' => $message,
		'user_id' => get_current_user_id(),
		'context' => array(),
	);

	foreach ($context as $key => $value) {
		$key = sanitize_key((string) $key);
		if ($key === '') {
			continue;
		}

		if (is_scalar($value) || $value === null) {
			$entry['context'][$key] = is_string($value) ? sanitize_text_field($value) : $value;
			continue;
		}

		$entry['context'][$key] = wp_json_encode($value);
	}

	$log = get_option(vms_ticket_integrity_log_option_key(), array());
	if (!is_array($log)) {
		$log = array();
	}

	array_unshift($log, $entry);
	if (count($log) > 200) {
		$log = array_slice($log, 0, 200);
	}

	update_option(vms_ticket_integrity_log_option_key(), $log, false);
}

function vms_ticket_integrity_get_logs(): array
{
	$log = get_option(vms_ticket_integrity_log_option_key(), array());
	return is_array($log) ? $log : array();
}

function vms_ticket_integrity_is_fatal_error(?array $error): bool
{
	if (!is_array($error)) {
		return false;
	}

	return in_array((int) ($error['type'] ?? 0), array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR), true);
}

function vms_ticket_integrity_is_memory_fatal(?array $error): bool
{
	if (!vms_ticket_integrity_is_fatal_error($error)) {
		return false;
	}

	$message = strtolower(trim((string) ($error['message'] ?? '')));
	return ($message !== '' && strpos($message, 'allowed memory size') !== false);
}

function vms_ticket_integrity_memory_limit_bytes(): int
{
	$raw = trim((string) ini_get('memory_limit'));
	if ($raw === '' || $raw === '-1') {
		return 0;
	}

	$unit = strtolower(substr($raw, -1));
	$value = (float) $raw;
	switch ($unit) {
		case 'g':
			$value *= 1024;
		case 'm':
			$value *= 1024;
		case 'k':
			$value *= 1024;
	}

	return max(0, (int) round($value));
}

function vms_ticket_integrity_scan_memory_snapshot(array $args = array()): array
{
	$limit_bytes = vms_ticket_integrity_memory_limit_bytes();
	$usage_bytes = function_exists('memory_get_usage') ? (int) memory_get_usage(true) : 0;
	$minimum_headroom_bytes = max(
		8 * 1024 * 1024,
		(int) apply_filters('vms_ticket_integrity_min_scan_headroom_bytes', 64 * 1024 * 1024, $args)
	);
	$headroom_bytes = ($limit_bytes > 0) ? max(0, $limit_bytes - $usage_bytes) : PHP_INT_MAX;

	return array(
		'memory_limit_bytes' => $limit_bytes,
		'memory_limit_mb' => ($limit_bytes > 0) ? round($limit_bytes / 1048576, 1) : 0.0,
		'memory_usage_bytes' => $usage_bytes,
		'memory_usage_mb' => round($usage_bytes / 1048576, 1),
		'headroom_bytes' => $headroom_bytes,
		'headroom_mb' => ($limit_bytes > 0) ? round($headroom_bytes / 1048576, 1) : 0.0,
		'minimum_headroom_bytes' => $minimum_headroom_bytes,
		'minimum_headroom_mb' => round($minimum_headroom_bytes / 1048576, 1),
	);
}

function vms_ticket_integrity_scan_has_memory_headroom(array $args = array()): bool
{
	$snapshot = vms_ticket_integrity_scan_memory_snapshot($args);
	if (($snapshot['memory_limit_bytes'] ?? 0) <= 0) {
		return true;
	}

	return (($snapshot['headroom_bytes'] ?? 0) >= ($snapshot['minimum_headroom_bytes'] ?? 0));
}

function vms_ticket_integrity_begin_fatal_guard(string $operation, array $context = array()): string
{
	$operation = sanitize_key($operation);
	if ($operation === '') {
		$operation = 'unknown';
	}

	if (empty($GLOBALS['vms_ticket_integrity_fatal_guard_registered'])) {
		$GLOBALS['vms_ticket_integrity_fatal_guard_registered'] = true;
		register_shutdown_function('vms_ticket_integrity_fatal_guard_shutdown');
	}

	if (empty($GLOBALS['vms_ticket_integrity_fatal_guard_reserve'])) {
		$GLOBALS['vms_ticket_integrity_fatal_guard_reserve'] = str_repeat('x', 262144);
	}

	$guards = $GLOBALS['vms_ticket_integrity_fatal_guards'] ?? array();
	if (!is_array($guards)) {
		$guards = array();
	}

	$guard_id = uniqid('tim_guard_', true);
	$guards[$guard_id] = array(
		'operation' => $operation,
		'context' => $context,
		'finalized' => false,
		'started_at_gmt' => time(),
	);
	$GLOBALS['vms_ticket_integrity_fatal_guards'] = $guards;

	return $guard_id;
}

function vms_ticket_integrity_end_fatal_guard(string $guard_id): void
{
	$guards = $GLOBALS['vms_ticket_integrity_fatal_guards'] ?? array();
	if (!is_array($guards) || empty($guards[$guard_id]) || !is_array($guards[$guard_id])) {
		return;
	}

	$guards[$guard_id]['finalized'] = true;
	$GLOBALS['vms_ticket_integrity_fatal_guards'] = $guards;
}

function vms_ticket_integrity_fatal_guard_shutdown(): void
{
	$guards = $GLOBALS['vms_ticket_integrity_fatal_guards'] ?? array();
	if (!is_array($guards) || empty($guards)) {
		return;
	}

	$error = error_get_last();
	if (!vms_ticket_integrity_is_fatal_error($error)) {
		return;
	}

	unset($GLOBALS['vms_ticket_integrity_fatal_guard_reserve']);

	$is_memory_fatal = vms_ticket_integrity_is_memory_fatal($error);
	$fatal_message = trim((string) ($error['message'] ?? ''));
	$fatal_file = trim((string) ($error['file'] ?? ''));
	if ($fatal_file !== '' && defined('ABSPATH')) {
		$fatal_file = str_replace(ABSPATH, '', $fatal_file);
	}
	$peak_memory_mb = function_exists('memory_get_peak_usage')
		? round(((int) memory_get_peak_usage(true)) / 1048576, 1)
		: 0.0;

	foreach ($guards as $guard_id => $guard) {
		if (!is_array($guard) || !empty($guard['finalized'])) {
			continue;
		}

		$operation = sanitize_key((string) ($guard['operation'] ?? 'unknown'));
		$context = is_array($guard['context'] ?? null) ? $guard['context'] : array();
		$context['fatal_type'] = (int) ($error['type'] ?? 0);
		$context['fatal_message'] = $fatal_message;
		$context['fatal_file'] = $fatal_file;
		$context['fatal_line'] = (int) ($error['line'] ?? 0);
		$context['peak_memory_mb'] = $peak_memory_mb;
		$context['memory_exhausted'] = $is_memory_fatal ? 1 : 0;

		$event_type = 'scan_failed';
		$message = __('Ticket integrity scan hit a fatal error.', 'backstage-venue-manager');
		if ($operation === 'scan') {
			$event_type = $is_memory_fatal ? 'scan_failed_memory' : 'scan_failed';
			$message = $is_memory_fatal
				? __('Ticket integrity scan exhausted PHP memory.', 'backstage-venue-manager')
				: __('Ticket integrity scan hit a fatal error.', 'backstage-venue-manager');
		} elseif ($operation === 'daily_report') {
			$event_type = 'daily_report_failed';
			$message = $is_memory_fatal
				? __('State of the Range failed during a PHP memory exhaustion.', 'backstage-venue-manager')
				: __('State of the Range hit a fatal error before send.', 'backstage-venue-manager');
		}

		if (function_exists('error_log')) {
			$encoded_context = function_exists('wp_json_encode') ? wp_json_encode($context) : json_encode($context);
			error_log(
				sprintf(
					'[VMS TICKET INTEGRITY FATAL] operation=%1$s memory_exhausted=%2$d type=%3$d file=%4$s line=%5$d message=%6$s context=%7$s',
					$operation,
					$is_memory_fatal ? 1 : 0,
					(int) ($error['type'] ?? 0),
					$fatal_file,
					(int) ($error['line'] ?? 0),
					$fatal_message,
					is_string($encoded_context) ? $encoded_context : ''
				)
			);
		}

		if ($operation === 'daily_report' && function_exists('vms_ticket_integrity_patch_daily_report_state')) {
			$state_changes = array(
				'last_status' => 'failed',
				'last_error' => $is_memory_fatal ? 'fatal_memory_exhausted' : 'fatal_error',
			);
			if (!empty($context['trigger'])) {
				$state_changes['last_trigger'] = sanitize_key((string) $context['trigger']);
			}
			if (!empty($context['mode'])) {
				$state_changes['last_mode'] = sanitize_key((string) $context['mode']);
			}
			if (!empty($context['recipient'])) {
				$state_changes['last_recipient'] = sanitize_email((string) $context['recipient']);
			}
			vms_ticket_integrity_patch_daily_report_state($state_changes);
		}

		vms_ticket_integrity_log_event($event_type, $message, $context);
		$guards[$guard_id]['finalized'] = true;
	}

	$GLOBALS['vms_ticket_integrity_fatal_guards'] = $guards;
}

function vms_ticket_integrity_get_results_store(): array
{
	$store = get_option(vms_ticket_integrity_results_option_key(), array());
	if (!is_array($store)) {
		$store = array();
	}

	if (!isset($store['events']) || !is_array($store['events'])) {
		$store['events'] = array();
	}
	if (!isset($store['summary']) || !is_array($store['summary'])) {
		$store['summary'] = array();
	}
	if (!isset($store['payment_gateway_health']) || !is_array($store['payment_gateway_health'])) {
		$store['payment_gateway_health'] = function_exists('vms_ticket_integrity_get_payment_gateway_health')
			? vms_ticket_integrity_get_payment_gateway_health()
			: array();
	}

	return $store;
}

function vms_ticket_integrity_event_store_key(int $plan_id, int $tec_event_id): string
{
	if ($plan_id > 0) {
		return 'plan_' . $plan_id;
	}
	return 'event_' . $tec_event_id;
}

function vms_ticket_integrity_sort_events(array $events): array
{
	usort(
		$events,
		static function (array $a, array $b): int {
			$a_rank = vms_ticket_integrity_status_rank((string) ($a['status'] ?? ''));
			$b_rank = vms_ticket_integrity_status_rank((string) ($b['status'] ?? ''));
			if ($a_rank !== $b_rank) {
				return $b_rank <=> $a_rank;
			}

			$a_ts = absint($a['event_timestamp'] ?? 0);
			$b_ts = absint($b['event_timestamp'] ?? 0);
			if ($a_ts !== $b_ts) {
				if ($a_ts <= 0) {
					return 1;
				}
				if ($b_ts <= 0) {
					return -1;
				}
				return $a_ts <=> $b_ts;
			}

			return strcasecmp((string) ($a['event_title'] ?? ''), (string) ($b['event_title'] ?? ''));
		}
	);

	return $events;
}

function vms_ticket_integrity_get_sorted_events(): array
{
	$store = vms_ticket_integrity_get_results_store();
	return vms_ticket_integrity_sort_events(array_values($store['events'] ?? array()));
}

function vms_ticket_integrity_calculate_summary(array $events): array
{
	$summary = array(
		'events_scanned' => count($events),
		'green' => 0,
		'yellow' => 0,
		'red' => 0,
		'informational' => 0,
	);

	foreach ($events as $event) {
		if (!is_array($event)) {
			continue;
		}
		$status = sanitize_key((string) ($event['status'] ?? 'green'));
		if (!isset($summary[$status])) {
			continue;
		}
		$summary[$status]++;
	}

	return $summary;
}

function vms_ticket_integrity_format_datetime(int $timestamp): string
{
	$timestamp = absint($timestamp);
	if ($timestamp <= 0) {
		return __('Never', 'backstage-venue-manager');
	}

	if (function_exists('wp_date')) {
		return wp_date('Y-m-d g:i a', $timestamp, wp_timezone());
	}

	return date('Y-m-d g:i a', $timestamp);
}

function vms_ticket_integrity_acquire_scan_lock(string $owner = ''): bool
{
	$current = get_transient(vms_ticket_integrity_scan_lock_key());
	$ttl = 15 * MINUTE_IN_SECONDS;
	$stale_after = $ttl + MINUTE_IN_SECONDS;
	if (!empty($current)) {
		$is_stale = !is_array($current);
		$started_at = is_array($current) ? absint($current['started_at_gmt'] ?? 0) : 0;
		if ($started_at > 0 && $started_at < (time() - $stale_after)) {
			$is_stale = true;
		}

		if ($is_stale) {
			delete_transient(vms_ticket_integrity_scan_lock_key());
			if (function_exists('vms_ticket_integrity_log_event')) {
				vms_ticket_integrity_log_event(
					'scan_lock_cleared',
					__('Ticket integrity scan lock was cleared after expiring or becoming invalid.', 'backstage-venue-manager'),
					array(
						'owner' => sanitize_text_field((string) ($current['owner'] ?? '')),
						'started_at_gmt' => $started_at,
					)
				);
			}
			$current = false;
		}
	}
	if (!empty($current)) {
		return false;
	}

	set_transient(
		vms_ticket_integrity_scan_lock_key(),
		array(
			'owner' => sanitize_text_field($owner),
			'started_at_gmt' => time(),
		),
		$ttl
	);

	return true;
}

function vms_ticket_integrity_release_scan_lock(): void
{
	delete_transient(vms_ticket_integrity_scan_lock_key());
}

function vms_ticket_integrity_plan_uses_ticketing(int $plan_id, int $tec_event_id = 0): bool
{
	$plan_id = absint($plan_id);
	$tec_event_id = absint($tec_event_id);
	if ($plan_id <= 0) {
		return false;
	}

	$mode = function_exists('vms_ticketing_b_get_mode')
		? sanitize_key((string) vms_ticketing_b_get_mode($plan_id))
		: 'read_only';

	if ($mode === 'vms_managed' && function_exists('vms_ticketing_v2_k')) {
		$raw_config = get_post_meta($plan_id, vms_ticketing_v2_k('config'), true);
		if (is_array($raw_config)) {
			foreach ((array) ($raw_config['tickets'] ?? array()) as $ticket_row) {
				if (!is_array($ticket_row) || empty($ticket_row['enabled'])) {
					continue;
				}
				if (trim((string) ($ticket_row['title'] ?? '')) !== '') {
					return true;
				}
			}

			foreach ((array) ($raw_config['entitlements'] ?? array()) as $entitlement_row) {
				if (!is_array($entitlement_row) || empty($entitlement_row['enabled'])) {
					continue;
				}
				if (trim((string) ($entitlement_row['label'] ?? '')) !== '') {
					return true;
				}
			}
		}
	}

	$legacy_ids_key = function_exists('vms_ticketing_b_meta_key')
		? vms_ticketing_b_meta_key('ticket_product_ids', '_vms_ticket_product_ids_v1')
		: '_vms_ticket_product_ids_v1';
	if ($legacy_ids_key !== '' && metadata_exists('post', $plan_id, $legacy_ids_key)) {
		$legacy_ids = get_post_meta($plan_id, $legacy_ids_key, true);
		if (is_array($legacy_ids) && !empty($legacy_ids)) {
			return true;
		}
	}

	if (function_exists('vms_ticketing_v2_get_sync')) {
		$sync = (array) vms_ticketing_v2_get_sync($plan_id);
		foreach ((array) ($sync['map']['entitlements'] ?? array()) as $row) {
			if (!is_array($row)) {
				continue;
			}
			if (absint($row['woo_product_id'] ?? 0) > 0) {
				return true;
			}
		}
	}

	if ($tec_event_id > 0 && function_exists('vms_ticketing_b_get_event_ticket_products')) {
		$product_ids = vms_ticketing_b_get_event_ticket_products($tec_event_id);
		if (!empty($product_ids)) {
			return true;
		}
	}

	return false;
}

function vms_ticket_integrity_build_targets(array $args = array()): array
{
	$settings = vms_ticket_integrity_get_settings();
	$days_ahead = max(1, absint($args['days_ahead'] ?? $settings['days_ahead']));
	$include_inactive = !empty($args['include_inactive']);
	$now = time();
	$cutoff = $now + ($days_ahead * DAY_IN_SECONDS);
	$tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
	$start_date = function_exists('wp_date') ? wp_date('Y-m-d', $now, $tz) : date('Y-m-d', $now);
	$end_date = function_exists('wp_date') ? wp_date('Y-m-d', $cutoff, $tz) : date('Y-m-d', $cutoff);
	$tec_event_meta_key = function_exists('vms_ticketing_b_meta_key')
		? vms_ticketing_b_meta_key('tec_event_id', '_vms_tec_event_id')
		: '_vms_tec_event_id';
	$targets = array();
	$batch_size = max(25, min(500, (int) apply_filters('vms_ticket_integrity_target_query_batch_size', 100)));
	$paged = 1;

	do {
		$query = new WP_Query(
			array(
				'post_type' => 'vms_event_plan',
				'post_status' => 'publish',
				'posts_per_page' => $batch_size,
				'paged' => $paged,
				'fields' => 'ids',
				'no_found_rows' => false,
				'meta_key' => '_vms_event_date',
				'orderby' => 'meta_value',
				'meta_type' => 'DATE',
				'order' => 'ASC',
				'meta_query' => array(
					array(
						'key' => '_vms_event_date',
						'value' => array($start_date, $end_date),
						'compare' => 'BETWEEN',
						'type' => 'DATE',
					),
					array(
						'key' => $tec_event_meta_key,
						'value' => 0,
						'compare' => '>',
						'type' => 'NUMERIC',
					),
				),
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'cache_results' => false,
				'lazy_load_term_meta' => false,
				'suppress_filters' => true,
			)
		);

		$plan_ids = is_array($query->posts) ? $query->posts : array();
		foreach ($plan_ids as $plan_id) {
			$plan_id = absint($plan_id);
			if ($plan_id <= 0) {
				continue;
			}

			$tec_event_id = function_exists('vms_ticketing_b_get_linked_tec_event_id')
				? absint(vms_ticketing_b_get_linked_tec_event_id($plan_id))
				: absint(get_post_meta($plan_id, '_vms_tec_event_id', true));
			if ($tec_event_id <= 0) {
				continue;
			}

			$event_date = trim((string) get_post_meta($plan_id, '_vms_event_date', true));
			$event_time = trim((string) get_post_meta($plan_id, '_vms_start_time', true));
			$event_raw = $event_date;
			if ($event_raw !== '' && $event_time !== '') {
				$event_raw .= ' ' . $event_time;
			}
			$event_timestamp = function_exists('vms_ticket_integrity_parse_wp_datetime')
				? absint(vms_ticket_integrity_parse_wp_datetime($event_raw))
				: 0;
			if ($event_timestamp <= 0 && function_exists('vms_ticket_integrity_event_timestamp')) {
				$event_timestamp = absint(vms_ticket_integrity_event_timestamp($plan_id, $tec_event_id));
			}
			if ($event_timestamp <= 0 || $event_timestamp < $now || $event_timestamp > $cutoff) {
				continue;
			}

			$is_cancelled = function_exists('vms_tec_is_cancelled_event')
				? (bool) vms_tec_is_cancelled_event($tec_event_id)
				: false;
			if (!$include_inactive && $is_cancelled) {
				continue;
			}

			if (!$include_inactive && !vms_ticket_integrity_plan_uses_ticketing($plan_id, $tec_event_id)) {
				continue;
			}

			$targets[] = array(
				'plan_id' => $plan_id,
				'tec_event_id' => $tec_event_id,
				'event_timestamp' => $event_timestamp,
				'event_title' => get_the_title($tec_event_id),
			);
		}

		$has_more = ($paged < (int) $query->max_num_pages) && !empty($plan_ids);
		wp_reset_postdata();
		unset($plan_ids, $query);
		$paged++;
	} while ($has_more);

	usort(
		$targets,
		static function (array $a, array $b): int {
			$a_ts = absint($a['event_timestamp'] ?? 0);
			$b_ts = absint($b['event_timestamp'] ?? 0);
			if ($a_ts !== $b_ts) {
				return $a_ts <=> $b_ts;
			}
			return strcasecmp((string) ($a['event_title'] ?? ''), (string) ($b['event_title'] ?? ''));
		}
	);

	return $targets;
}

function vms_ticket_integrity_build_failure_result(int $plan_id, int $tec_event_id, Throwable $error): array
{
	$plan_id = absint($plan_id);
	$tec_event_id = absint($tec_event_id);

	$event_timestamp = function_exists('vms_ticket_integrity_event_timestamp')
		? absint(vms_ticket_integrity_event_timestamp($plan_id, $tec_event_id))
		: 0;
	$event_title = $tec_event_id > 0 ? get_the_title($tec_event_id) : get_the_title($plan_id);

	$issues = array();
	if (function_exists('vms_ticket_integrity_issue')) {
		$issues[] = vms_ticket_integrity_issue(
			'scan_failed',
			'red',
			'scan',
			__('Ticket integrity scan failed', 'backstage-venue-manager'),
			__('The monitor hit an unexpected error while scanning this event. Review the audit log and re-run the event scan manually.', 'backstage-venue-manager'),
			array(
				'error_message' => $error->getMessage(),
			)
		);
	}

	return array(
		'plan_id' => $plan_id,
		'tec_event_id' => $tec_event_id,
		/* translators: %d: number used in this message. */
		'event_title' => $event_title !== '' ? $event_title : sprintf(__('Event %d', 'backstage-venue-manager'), $tec_event_id > 0 ? $tec_event_id : $plan_id),
		'event_timestamp' => $event_timestamp,
		'event_date_local' => $event_timestamp > 0 ? vms_ticket_integrity_format_datetime($event_timestamp) : '',
		'event_url' => $tec_event_id > 0 ? get_permalink($tec_event_id) : '',
		'edit_plan_url' => $plan_id > 0 ? get_edit_post_link($plan_id, '') : '',
		'edit_event_url' => $tec_event_id > 0 ? get_edit_post_link($tec_event_id, '') : '',
		'status' => 'red',
		'issue_summary' => __('Ticket integrity scan failed.', 'backstage-venue-manager'),
		'issues' => $issues,
		'scanned_at_gmt' => time(),
	);
}

function vms_ticket_integrity_should_alert_issue(array $issue, array $settings): bool
{
	$issue_kind = sanitize_key((string) ($issue['issue_kind'] ?? ''));
	if ($issue_kind === 'low_inventory') {
		return !empty($settings['low_inventory_email_alerts_enabled']);
	}

	$severity = sanitize_key((string) ($issue['severity'] ?? ''));
	if ($severity === 'red') {
		return true;
	}

	return ($severity === 'yellow' && !empty($settings['include_yellow_in_email_alerts']));
}

function vms_ticket_integrity_send_alert_email(array &$events, array $alerts, array $resolved_alerts, array $scan_meta): void
{
	$settings = vms_ticket_integrity_get_settings();
	if (empty($settings['email_alerts_enabled'])) {
		return;
	}

	$recipient = sanitize_email((string) ($settings['alert_recipient'] ?? ''));
	if ($recipient === '') {
		$recipient = sanitize_email((string) get_option('admin_email', ''));
	}
	if ($recipient === '') {
		return;
	}

	if (empty($alerts) && empty($resolved_alerts)) {
		return;
	}

	$site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
	$subject = sprintf('[%s] %s', $site_name, __('Ticket Integrity Monitor update', 'backstage-venue-manager'));
	$lines = array();
	/* translators: %s: scan completed. */
	$lines[] = sprintf(__('Scan completed: %s', 'backstage-venue-manager'), vms_ticket_integrity_format_datetime(absint($scan_meta['completed_at_gmt'] ?? time())));
	$lines[] = '';

	if (!empty($alerts)) {
		$lines[] = __('New / reminder issues:', 'backstage-venue-manager');
		foreach ($alerts as $alert) {
			$event_key = (string) ($alert['event_key'] ?? '');
			$issue_key = (string) ($alert['issue_key'] ?? '');
			if ($event_key === '' || $issue_key === '' || empty($events[$event_key]['issues'][$issue_key])) {
				continue;
			}

			$event = $events[$event_key];
			$issue = $events[$event_key]['issues'][$issue_key];
			$lines[] = sprintf(
				'- [%s] %s: %s',
				vms_ticket_integrity_status_label((string) ($issue['severity'] ?? '')),
				(string) ($event['event_title'] ?? __('Unknown event', 'backstage-venue-manager')),
				(string) ($issue['title'] ?? __('Issue detected', 'backstage-venue-manager'))
			);
			$details = trim((string) ($issue['details'] ?? ''));
			if ($details !== '') {
				$lines[] = '  ' . $details;
			}

			$events[$event_key]['issues'][$issue_key]['last_alerted_gmt'] = time();
		}
		$lines[] = '';
	}

	if (!empty($resolved_alerts)) {
		$lines[] = __('Resolved issues:', 'backstage-venue-manager');
		foreach ($resolved_alerts as $alert) {
			$event_key = (string) ($alert['event_key'] ?? '');
			$issue_key = (string) ($alert['issue_key'] ?? '');
			if ($event_key === '' || $issue_key === '' || empty($events[$event_key]['issues'][$issue_key])) {
				continue;
			}

			$event = $events[$event_key];
			$issue = $events[$event_key]['issues'][$issue_key];
			$lines[] = sprintf(
				'- %s: %s',
				(string) ($event['event_title'] ?? __('Unknown event', 'backstage-venue-manager')),
				(string) ($issue['title'] ?? __('Issue resolved', 'backstage-venue-manager'))
			);
			$events[$event_key]['issues'][$issue_key]['last_resolved_alerted_gmt'] = time();
		}
		$lines[] = '';
	}

	/* translators: %s: review the full monitor. */
	$lines[] = sprintf(__('Review the full monitor: %s', 'backstage-venue-manager'), vms_ticket_integrity_admin_url());
	$sent = wp_mail($recipient, $subject, implode("\n", $lines));

	if ($sent) {
		vms_ticket_integrity_log_event(
			'alert_email_sent',
			__('Ticket integrity alert email sent.', 'backstage-venue-manager'),
			array(
				'recipient' => $recipient,
				'alert_count' => count($alerts),
				'resolved_count' => count($resolved_alerts),
			)
		);
		return;
	}

	vms_ticket_integrity_log_event(
		'alert_email_failed',
		__('Ticket integrity alert email failed to send.', 'backstage-venue-manager'),
		array(
			'recipient' => $recipient,
			'alert_count' => count($alerts),
			'resolved_count' => count($resolved_alerts),
		)
	);
}

function vms_ticket_integrity_merge_scan_result(array &$events, array $existing_events, array &$alerts, array &$resolved_alerts, array $result, array $settings, int $now): void
{
	$plan_id = absint($result['plan_id'] ?? 0);
	$tec_event_id = absint($result['tec_event_id'] ?? 0);
	$event_key = vms_ticket_integrity_event_store_key($plan_id, $tec_event_id);
	$previous = $existing_events[$event_key] ?? array();
	$previous_issues = isset($previous['issues']) && is_array($previous['issues']) ? $previous['issues'] : array();

	$current_issues = isset($result['issues']) && is_array($result['issues']) ? $result['issues'] : array();
	$current_map = array();
	foreach ($current_issues as $issue) {
		if (!is_array($issue)) {
			continue;
		}
		$key = sanitize_key((string) ($issue['key'] ?? ''));
		if ($key === '') {
			continue;
		}

		$previous_issue = $previous_issues[$key] ?? array();
		$had_open_issue = is_array($previous_issue) && vms_ticket_integrity_issue_status($previous_issue) === 'open';

		$issue['status'] = 'open';
		$issue['first_detected_gmt'] = absint($previous_issue['first_detected_gmt'] ?? 0);
		if ($issue['first_detected_gmt'] <= 0) {
			$issue['first_detected_gmt'] = $now;
		}
		$issue['last_detected_gmt'] = $now;
		$issue['resolved_at_gmt'] = 0;
		$issue['last_alerted_gmt'] = absint($previous_issue['last_alerted_gmt'] ?? 0);
		$issue['last_resolved_alerted_gmt'] = absint($previous_issue['last_resolved_alerted_gmt'] ?? 0);
		$current_map[$key] = $issue;

		if (!$had_open_issue) {
			vms_ticket_integrity_log_event(
				'issue_detected',
				/* translators: %s: ticket integrity issue detected. */
				sprintf(__('Ticket integrity issue detected: %s', 'backstage-venue-manager'), (string) ($issue['title'] ?? $key)),
				array(
					'event_key' => $event_key,
					'plan_id' => $plan_id,
					'tec_event_id' => $tec_event_id,
					'issue_key' => $key,
					'severity' => (string) ($issue['severity'] ?? ''),
				)
			);
		}

		if (vms_ticket_integrity_should_alert_issue($issue, $settings)) {
			$reminder_interval = max(1, absint($settings['reminder_interval_hours'] ?? 24)) * HOUR_IN_SECONDS;
			$last_alerted = absint($issue['last_alerted_gmt'] ?? 0);
			if (!$had_open_issue || $last_alerted <= 0 || (($now - $last_alerted) >= $reminder_interval)) {
				$alerts[] = array(
					'event_key' => $event_key,
					'issue_key' => $key,
				);
			}
		}
	}

	foreach ($previous_issues as $key => $old_issue) {
		$key = sanitize_key((string) $key);
		if ($key === '' || !is_array($old_issue) || isset($current_map[$key])) {
			continue;
		}

		$old_issue['status'] = 'resolved';
		$old_issue['resolved_at_gmt'] = $now;
		$current_map[$key] = $old_issue;

		vms_ticket_integrity_log_event(
			'issue_resolved',
			/* translators: %s: ticket integrity issue resolved. */
			sprintf(__('Ticket integrity issue resolved: %s', 'backstage-venue-manager'), (string) ($old_issue['title'] ?? $key)),
			array(
				'event_key' => $event_key,
				'plan_id' => $plan_id,
				'tec_event_id' => $tec_event_id,
				'issue_key' => $key,
			)
		);

		if (!empty($settings['send_resolved_notifications']) && absint($old_issue['last_alerted_gmt'] ?? 0) > 0 && absint($old_issue['last_resolved_alerted_gmt'] ?? 0) <= 0) {
			$resolved_alerts[] = array(
				'event_key' => $event_key,
				'issue_key' => $key,
			);
		}
	}

	$current_map = vms_ticket_integrity_sort_issues(array_values($current_map));
	$issue_map = array();
	foreach ($current_map as $issue) {
		$key = sanitize_key((string) ($issue['key'] ?? ''));
		if ($key === '') {
			continue;
		}
		$issue_map[$key] = $issue;
	}

	$result['issues'] = $issue_map;
	$result['status'] = vms_ticket_integrity_status_from_issues($issue_map);
	$result['issue_summary'] = vms_ticket_integrity_issue_summary($issue_map);
	$result['first_detected_gmt'] = vms_ticket_integrity_issue_first_detected($issue_map);
	$result['last_detected_gmt'] = vms_ticket_integrity_issue_last_detected($issue_map);
	$result['updated_at_gmt'] = $now;
	$events[$event_key] = $result;
}

function vms_ticket_integrity_finalize_scan_store(array $store, array $events, array $alerts, array $resolved_alerts, array $scan_meta, int $events_scanned, int $now = 0): array
{
	$now = $now > 0 ? $now : time();
	$scope = sanitize_key((string) ($scan_meta['scope'] ?? 'full'));
	$summary = vms_ticket_integrity_calculate_summary(array_values($events));
	$scan_record = array(
		'trigger' => sanitize_key((string) ($scan_meta['trigger'] ?? 'manual')),
		'scope' => $scope,
		'started_at_gmt' => absint($scan_meta['started_at_gmt'] ?? $now),
		'completed_at_gmt' => $now,
		'days_ahead' => absint($scan_meta['days_ahead'] ?? 0),
		'events_scanned' => $events_scanned,
		'summary' => $summary,
	);

	vms_ticket_integrity_send_alert_email($events, $alerts, $resolved_alerts, $scan_record);

	$store = array(
		'version' => 1,
		'monitor_version' => defined('VMS_VERSION') ? (string) VMS_VERSION : '',
		'updated_at_gmt' => $now,
		'last_scan' => $scan_record,
		'summary' => $summary,
		'events' => $events,
		'payment_gateway_health' => is_array($store['payment_gateway_health'] ?? null) ? $store['payment_gateway_health'] : array(),
	);

	update_option(vms_ticket_integrity_results_option_key(), $store, false);
	return $store;
}

function vms_ticket_integrity_persist_scan_results(array $fresh_results, array $scan_meta = array()): array
{
	$now = time();
	$settings = vms_ticket_integrity_get_settings();
	$store = vms_ticket_integrity_get_results_store();
	$existing_events = isset($store['events']) && is_array($store['events']) ? $store['events'] : array();
	$scope = sanitize_key((string) ($scan_meta['scope'] ?? 'full'));
	$is_full = ($scope !== 'event');

	$events = $is_full ? array() : $existing_events;
	$alerts = array();
	$resolved_alerts = array();

	foreach ($fresh_results as $result) {
		if (!is_array($result)) {
			continue;
		}
		vms_ticket_integrity_merge_scan_result($events, $existing_events, $alerts, $resolved_alerts, $result, $settings, $now);
	}

	return vms_ticket_integrity_finalize_scan_store($store, $events, $alerts, $resolved_alerts, $scan_meta, count($fresh_results), $now);
}

function vms_ticket_integrity_scan_all(array $args = array()): array
{
	$trigger = sanitize_key((string) ($args['trigger'] ?? 'manual'));
	if (!vms_ticket_integrity_acquire_scan_lock($trigger)) {
		return array('ok' => false, 'message' => 'scan_locked');
	}

	$guard_id = vms_ticket_integrity_begin_fatal_guard(
		'scan',
		array(
			'trigger' => $trigger,
			'scope' => 'full',
			'compact_diagnostics' => !empty($args['compact_diagnostics']) ? 1 : 0,
		)
	);
	$started_at = time();
	$memory_snapshot = vms_ticket_integrity_scan_memory_snapshot($args);
	vms_ticket_integrity_log_event(
		'scan_started',
		__('Ticket integrity scan started.', 'backstage-venue-manager'),
		array(
			'trigger' => $trigger,
			'compact_diagnostics' => !empty($args['compact_diagnostics']) ? 1 : 0,
			'memory_limit_mb' => (float) ($memory_snapshot['memory_limit_mb'] ?? 0),
			'memory_usage_mb' => (float) ($memory_snapshot['memory_usage_mb'] ?? 0),
			'memory_headroom_mb' => (float) ($memory_snapshot['headroom_mb'] ?? 0),
			'memory_minimum_headroom_mb' => (float) ($memory_snapshot['minimum_headroom_mb'] ?? 0),
		)
	);

	try {
		if (!vms_ticket_integrity_scan_has_memory_headroom($args)) {
			vms_ticket_integrity_log_event(
				'scan_skipped_low_memory',
				__('Ticket integrity scan skipped because PHP memory headroom was too low before scan work began.', 'backstage-venue-manager'),
				array(
					'trigger' => $trigger,
					'compact_diagnostics' => !empty($args['compact_diagnostics']) ? 1 : 0,
					'memory_limit_mb' => (float) ($memory_snapshot['memory_limit_mb'] ?? 0),
					'memory_usage_mb' => (float) ($memory_snapshot['memory_usage_mb'] ?? 0),
					'memory_headroom_mb' => (float) ($memory_snapshot['headroom_mb'] ?? 0),
					'memory_minimum_headroom_mb' => (float) ($memory_snapshot['minimum_headroom_mb'] ?? 0),
				)
			);

			return array(
				'ok' => false,
				'message' => 'insufficient_memory_headroom',
			);
		}

		$targets = vms_ticket_integrity_build_targets($args);
		$settings = vms_ticket_integrity_get_settings();
		$store = vms_ticket_integrity_get_results_store();
		$existing_events = isset($store['events']) && is_array($store['events']) ? $store['events'] : array();
		$events = array();
		$alerts = array();
		$resolved_alerts = array();
		$events_scanned = 0;
		foreach ($targets as $target) {
			$plan_id = absint($target['plan_id'] ?? 0);
			$tec_event_id = absint($target['tec_event_id'] ?? 0);
			try {
				if (!function_exists('vms_ticket_integrity_scan_event_record')) {
					throw new RuntimeException('scan_helper_missing');
				}
				$result = vms_ticket_integrity_scan_event_record($plan_id, $args);
			} catch (Throwable $error) {
				vms_ticket_integrity_log_event(
					'scan_failed',
					/* translators: %d: number used in this message. */
					sprintf(__('Ticket integrity scan failed for plan %d.', 'backstage-venue-manager'), $plan_id),
					array(
						'plan_id' => $plan_id,
						'tec_event_id' => $tec_event_id,
						'error' => $error->getMessage(),
					)
				);
				$result = vms_ticket_integrity_build_failure_result($plan_id, $tec_event_id, $error);
			}

			vms_ticket_integrity_merge_scan_result($events, $existing_events, $alerts, $resolved_alerts, $result, $settings, time());
			$events_scanned++;
			if (function_exists('vms_ticket_inventory_forensics_reset_runtime_caches')) {
				vms_ticket_inventory_forensics_reset_runtime_caches();
			}
			if (function_exists('gc_collect_cycles')) {
				gc_collect_cycles();
			}
		}

		$store = vms_ticket_integrity_finalize_scan_store(
			$store,
			$events,
			$alerts,
			$resolved_alerts,
			array(
				'trigger' => $trigger,
				'scope' => 'full',
				'started_at_gmt' => $started_at,
				'days_ahead' => absint($args['days_ahead'] ?? vms_ticket_integrity_get_settings()['days_ahead']),
			),
			$events_scanned
		);

		vms_ticket_integrity_log_event(
			'scan_completed',
			__('Ticket integrity scan completed.', 'backstage-venue-manager'),
			array(
				'trigger' => $trigger,
				'events_scanned' => $events_scanned,
				'red' => absint($store['summary']['red'] ?? 0),
				'yellow' => absint($store['summary']['yellow'] ?? 0),
			)
		);

		return array(
			'ok' => true,
			'store' => $store,
			'events_scanned' => $events_scanned,
		);
	} catch (Throwable $error) {
		vms_ticket_integrity_log_event(
			'scan_failed',
			__('Ticket integrity scan failed before completion.', 'backstage-venue-manager'),
			array(
				'trigger' => $trigger,
				'error' => $error->getMessage(),
			)
		);

		return array(
			'ok' => false,
			'message' => $error->getMessage(),
		);
	} finally {
		vms_ticket_integrity_end_fatal_guard($guard_id);
		vms_ticket_integrity_release_scan_lock();
	}
}

function vms_ticket_integrity_scan_event_now(int $plan_id, array $args = array()): array
{
	$plan_id = absint($plan_id);
	if ($plan_id <= 0) {
		return array('ok' => false, 'message' => 'invalid_plan');
	}

	$trigger = sanitize_key((string) ($args['trigger'] ?? 'manual_event'));
	if (!vms_ticket_integrity_acquire_scan_lock($trigger)) {
		return array('ok' => false, 'message' => 'scan_locked');
	}

	$guard_id = vms_ticket_integrity_begin_fatal_guard(
		'scan',
		array(
			'trigger' => $trigger,
			'scope' => 'event',
			'plan_id' => $plan_id,
		)
	);
	$started_at = time();
	vms_ticket_integrity_log_event('scan_started', __('Targeted ticket integrity scan started.', 'backstage-venue-manager'), array('trigger' => $trigger, 'plan_id' => $plan_id));

	try {
		if (!function_exists('vms_ticket_integrity_scan_event_record')) {
			throw new RuntimeException('scan_helper_missing');
		}

		$result = vms_ticket_integrity_scan_event_record($plan_id, $args);
		$store = vms_ticket_integrity_persist_scan_results(
			array($result),
			array(
				'trigger' => $trigger,
				'scope' => 'event',
				'started_at_gmt' => $started_at,
			)
		);

		vms_ticket_integrity_log_event('scan_completed', __('Targeted ticket integrity scan completed.', 'backstage-venue-manager'), array('trigger' => $trigger, 'plan_id' => $plan_id));

		return array(
			'ok' => true,
			'store' => $store,
			'result' => $result,
		);
	} catch (Throwable $error) {
		vms_ticket_integrity_log_event(
			'scan_failed',
			/* translators: %d: number used in this message. */
			sprintf(__('Targeted ticket integrity scan failed for plan %d.', 'backstage-venue-manager'), $plan_id),
			array(
				'plan_id' => $plan_id,
				'error' => $error->getMessage(),
			)
		);

		return array(
			'ok' => false,
			'message' => $error->getMessage(),
		);
	} finally {
		vms_ticket_integrity_end_fatal_guard($guard_id);
		vms_ticket_integrity_release_scan_lock();
	}
}

function vms_ticket_integrity_render_dashboard_panel(): void
{
	$store = vms_ticket_integrity_get_results_store();
	$summary = isset($store['summary']) && is_array($store['summary']) ? $store['summary'] : vms_ticket_integrity_calculate_summary(array_values($store['events'] ?? array()));
	$last_scan = isset($store['last_scan']) && is_array($store['last_scan']) ? $store['last_scan'] : array();
	$events = vms_ticket_integrity_get_sorted_events();
	$problem_events = array_values(
		array_filter(
			$events,
			static function (array $event): bool {
				return in_array(sanitize_key((string) ($event['status'] ?? 'green')), array('red', 'yellow'), true);
			}
		)
	);

	echo '<div class="vms-dashboard-health vms-ticket-integrity-dashboard" data-vms-tour="ticket-integrity.dashboard">';
	echo '<h2>' . esc_html__('Ticket Integrity', 'backstage-venue-manager') . '</h2>';
	echo '<p class="description">' . esc_html__('Nightly and on-demand monitoring for upcoming event ticket failures.', 'backstage-venue-manager') . '</p>';
	echo '<p><strong>' . esc_html__('Last scan:', 'backstage-venue-manager') . '</strong> ' . esc_html(vms_ticket_integrity_format_datetime(absint($last_scan['completed_at_gmt'] ?? 0))) . '</p>';
	echo '<p><strong>' . esc_html__('Red:', 'backstage-venue-manager') . '</strong> ' . absint($summary['red'] ?? 0) . ' <strong>' . esc_html__('Yellow:', 'backstage-venue-manager') . '</strong> ' . absint($summary['yellow'] ?? 0) . ' <strong>' . esc_html__('Green:', 'backstage-venue-manager') . '</strong> ' . absint($summary['green'] ?? 0) . '</p>';

	if (!empty($problem_events)) {
		echo '<ul class="vms-ticket-integrity-dashboard__list">';
		foreach (array_slice($problem_events, 0, 3) as $event) {
			$url = vms_ticket_integrity_admin_url(array('event' => absint($event['plan_id'] ?? 0)));
			echo '<li><a href="' . esc_url($url) . '">' . esc_html((string) ($event['event_title'] ?? __('Untitled event', 'backstage-venue-manager'))) . '</a> <span class="' . esc_attr(vms_ticket_integrity_status_css_class((string) ($event['status'] ?? ''))) . '">' . esc_html(vms_ticket_integrity_status_label((string) ($event['status'] ?? ''))) . '</span></li>';
		}
		echo '</ul>';
	} else {
		echo '<p>' . esc_html__('No red or yellow events are currently recorded.', 'backstage-venue-manager') . '</p>';
	}

	echo '<p><a class="button" href="' . esc_url(vms_ticket_integrity_admin_url()) . '">' . esc_html__('Open Ticket Integrity', 'backstage-venue-manager') . '</a></p>';
	echo '</div>';
}
