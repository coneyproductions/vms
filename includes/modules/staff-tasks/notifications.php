<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_tasks_notification_task_url')) {
	function vms_tasks_notification_task_url(int $instance_id = 0, int $assignee_user_id = 0): string
	{
		$query = array('page' => 'vms-tasks');
		if ($instance_id > 0) {
			$query['task_instance_id'] = $instance_id;
		}
		if ($assignee_user_id > 0) {
			$query['assignee_user_id'] = $assignee_user_id;
		}
		return add_query_arg($query, admin_url('admin.php'));
	}
}

if (!function_exists('vms_tasks_notification_context')) {
	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	function vms_tasks_notification_context(array $row): array
	{
		$instance_id = absint($row['id'] ?? 0);
		$event_id = absint($row['event_id'] ?? 0);
		$event_label = '';
		if ($event_id > 0) {
			$event_label = trim((string) get_the_title($event_id));
			if ($event_label === '') {
				$event_label = sprintf(
					/* translators: %d is an event id. */
					__('Event #%d', 'backstage-venue-manager'),
					$event_id
				);
			}
		}

		return array(
			'task_instance_id' => $instance_id,
			'task_title' => (string) ($row['title'] ?? ''),
			'due_datetime' => (string) ($row['due_at_local'] ?? ''),
			'event_id' => $event_id,
			'event_context' => $event_label,
			'task_url' => vms_tasks_notification_task_url($instance_id, absint($row['assignee_user_id'] ?? 0)),
		);
	}
}

if (!function_exists('vms_tasks_emit_notification_event')) {
	/**
	 * @param array<string,mixed> $payload
	 */
	function vms_tasks_emit_notification_event(string $event, array $payload): void
	{
		$event = sanitize_key($event);
		if ($event === '') {
			return;
		}
		do_action($event, $payload);
		do_action('vms_tasks_notification_event', $event, $payload);
	}
}

if (!function_exists('vms_tasks_maybe_notify_user')) {
	/**
	 * @param array<string,mixed> $vars
	 */
	function vms_tasks_maybe_notify_user(
		int $user_id,
		string $event_key,
		string $template_key,
		array $vars,
		int $task_instance_id = 0
	): void {
		$user_id = absint($user_id);
		if ($user_id <= 0) {
			return;
		}

		if (function_exists('vms_notify_user')) {
			vms_notify_user($user_id, $event_key, $template_key, $vars);
			return;
		}

		if ($task_instance_id > 0) {
			vms_tasks_log_task_action(
				$task_instance_id,
				'notification_skipped',
				null,
				wp_json_encode(array(
					'event_key' => $event_key,
					'template_key' => $template_key,
					'reason' => 'core_notifications_api_unavailable',
				))
			);
		}
	}
}

if (!function_exists('vms_tasks_emit_assignment_notification')) {
	/**
	 * @param array<string,mixed> $row
	 */
	function vms_tasks_emit_assignment_notification(array $row): void
	{
		$settings = vms_tasks_get_settings();
		if (empty($settings['notify_assignment_alerts'])) {
			return;
		}
		$user_id = absint($row['assignee_user_id'] ?? 0);
		if ($user_id <= 0) {
			return;
		}

		$payload = vms_tasks_notification_context($row);
		$payload['recipient_user_id'] = $user_id;

		vms_tasks_emit_notification_event('vms_task_assigned', $payload);
		vms_tasks_maybe_notify_user(
			$user_id,
			'vms_task_assigned',
			'staff_tasks.task_assigned',
			$payload,
			absint($row['id'] ?? 0)
		);
	}
}

if (!function_exists('vms_tasks_notifications_default_due_soon_window_minutes')) {
	function vms_tasks_notifications_default_due_soon_window_minutes(): int
	{
		return 120;
	}
}

if (!function_exists('vms_tasks_notification_scan_due_soon')) {
	function vms_tasks_notification_scan_due_soon(): void
	{
		$settings = vms_tasks_get_settings();
		if (empty($settings['notify_due_soon_alerts'])) {
			return;
		}

		$now = vms_tasks_now_local_mysql();
		$window_minutes = vms_tasks_notifications_default_due_soon_window_minutes();
		$due_before = date('Y-m-d H:i:s', strtotime($now . ' +' . $window_minutes . ' minutes'));
		$rows = vms_tasks_get_instances(array(
			'status' => 'open',
			'due_after' => $now,
			'due_before' => $due_before,
			'limit' => 500,
		));

		foreach ($rows as $row) {
			$instance_id = absint($row['id'] ?? 0);
			$user_id = absint($row['assignee_user_id'] ?? 0);
			if ($instance_id <= 0 || $user_id <= 0) {
				continue;
			}
			if (function_exists('vms_tasks_has_task_action_log') && vms_tasks_has_task_action_log($instance_id, 'notification_due_soon')) {
				continue;
			}

			$payload = vms_tasks_notification_context($row);
			$payload['recipient_user_id'] = $user_id;
			$payload['due_soon_window_minutes'] = $window_minutes;

			vms_tasks_emit_notification_event('vms_task_due_soon', $payload);
			vms_tasks_maybe_notify_user(
				$user_id,
				'vms_task_due_soon',
				'staff_tasks.task_due_soon',
				$payload,
				$instance_id
			);
			vms_tasks_log_task_action($instance_id, 'notification_due_soon', null, wp_json_encode(array(
				'user_id' => $user_id,
				'due_before' => $due_before,
			)));
		}
	}
}

if (!function_exists('vms_tasks_notification_scan_overdue')) {
	function vms_tasks_notification_scan_overdue(): void
	{
		$settings = vms_tasks_get_settings();
		if (empty($settings['notify_overdue_alerts'])) {
			return;
		}

		$now = vms_tasks_now_local_mysql();
		$rows = vms_tasks_get_instances(array(
			'status' => 'open',
			'due_before' => $now,
			'limit' => 500,
		));

		foreach ($rows as $row) {
			$instance_id = absint($row['id'] ?? 0);
			$user_id = absint($row['assignee_user_id'] ?? 0);
			if ($instance_id <= 0 || $user_id <= 0) {
				continue;
			}
			if (function_exists('vms_tasks_has_task_action_log') && vms_tasks_has_task_action_log($instance_id, 'notification_overdue')) {
				continue;
			}

			$payload = vms_tasks_notification_context($row);
			$payload['recipient_user_id'] = $user_id;

			vms_tasks_emit_notification_event('vms_task_overdue', $payload);
			vms_tasks_maybe_notify_user(
				$user_id,
				'vms_task_overdue',
				'staff_tasks.task_overdue',
				$payload,
				$instance_id
			);
			vms_tasks_log_task_action($instance_id, 'notification_overdue', null, wp_json_encode(array(
				'user_id' => $user_id,
				'at' => $now,
			)));
		}
	}
}

if (!function_exists('vms_tasks_notification_digest_window_end')) {
	function vms_tasks_notification_digest_window_end(string $window, string $now): string
	{
		$window = sanitize_key($window);
		if ($window === 'today') {
			return date('Y-m-d 23:59:59', strtotime($now));
		}
		if ($window === 'next7') {
			return date('Y-m-d H:i:s', strtotime($now . ' +7 days'));
		}
		return date('Y-m-d H:i:s', strtotime($now . ' +3 days'));
	}
}

if (!function_exists('vms_tasks_notification_run_digest')) {
	function vms_tasks_notification_run_digest(): void
	{
		$settings = vms_tasks_get_settings();
		if (empty($settings['notify_daily_digest'])) {
			return;
		}

		$now = vms_tasks_now_local_mysql();
		$today = date('Y-m-d', strtotime($now));
		$time = (string) ($settings['notify_digest_time'] ?? '08:00');
		$run_after = $today . ' ' . $time . ':00';
		if (strtotime($now) < strtotime($run_after)) {
			return;
		}

		$last_run_day = (string) get_option('vms_tasks_digest_last_run_day', '');
		if ($last_run_day === $today) {
			return;
		}

		$window = (string) ($settings['notify_digest_window'] ?? 'next3');
		$due_before = vms_tasks_notification_digest_window_end($window, $now);
		$rows = vms_tasks_get_instances(array(
			'status' => 'open',
			'due_after' => $now,
			'due_before' => $due_before,
			'limit' => 1000,
		));

		$grouped = array();
		foreach ($rows as $row) {
			$user_id = absint($row['assignee_user_id'] ?? 0);
			if ($user_id <= 0) {
				continue;
			}
			if (!isset($grouped[$user_id])) {
				$grouped[$user_id] = array();
			}
			$grouped[$user_id][] = vms_tasks_notification_context($row);
		}

		foreach ($grouped as $user_id => $items) {
			$payload = array(
				'recipient_user_id' => (int) $user_id,
				'window' => $window,
				'due_before' => $due_before,
				'task_count' => count($items),
				'tasks' => $items,
				'task_url' => vms_tasks_notification_task_url(0, (int) $user_id),
			);
			vms_tasks_emit_notification_event('vms_task_digest', $payload);
			vms_tasks_maybe_notify_user(
				(int) $user_id,
				'vms_task_digest',
				'staff_tasks.task_digest_daily',
				$payload,
				0
			);
		}

		update_option('vms_tasks_digest_last_run_day', $today, false);
	}
}

if (!function_exists('vms_tasks_notifications_tick')) {
	function vms_tasks_notifications_tick(): void
	{
		if (!vms_tasks_db_ready()) {
			return;
		}
		vms_tasks_notification_scan_due_soon();
		vms_tasks_notification_scan_overdue();
	}
}
add_action('vms_tasks_notifications_tick', 'vms_tasks_notifications_tick');

if (!function_exists('vms_tasks_notifications_digest_tick')) {
	function vms_tasks_notifications_digest_tick(): void
	{
		if (!vms_tasks_db_ready()) {
			return;
		}
		vms_tasks_notification_run_digest();
	}
}
add_action('vms_tasks_notifications_digest_tick', 'vms_tasks_notifications_digest_tick');

if (!function_exists('vms_tasks_notifications_register_cron_schedules')) {
	function vms_tasks_notifications_register_cron_schedules(array $schedules): array
	{
		if (!isset($schedules['vms_tasks_fifteen_minutes'])) {
			$schedules['vms_tasks_fifteen_minutes'] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display' => __('Every 15 Minutes (VMS Staff Tasks)', 'backstage-venue-manager'),
			);
		}
		return $schedules;
	}
}
add_filter('cron_schedules', 'vms_tasks_notifications_register_cron_schedules');

if (!function_exists('vms_tasks_notifications_ensure_cron')) {
	function vms_tasks_notifications_ensure_cron(): void
	{
		if (function_exists('vms_should_run_runtime_maintenance') && !vms_should_run_runtime_maintenance()) {
			return;
		}
		if (!function_exists('vms_schedule_exists') || !vms_schedule_exists('vms_tasks_fifteen_minutes')) {
			return;
		}
		if (!wp_next_scheduled('vms_tasks_notifications_tick')) {
			wp_schedule_event(time() + 120, 'vms_tasks_fifteen_minutes', 'vms_tasks_notifications_tick');
		}
		if (!wp_next_scheduled('vms_tasks_notifications_digest_tick')) {
			wp_schedule_event(time() + 300, 'hourly', 'vms_tasks_notifications_digest_tick');
		}
	}
}
add_action('init', 'vms_tasks_notifications_ensure_cron', 30);
