<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_tasks_notification_task_url')) {
	function bvmgr_tasks_notification_task_url(int $instance_id = 0, int $assignee_user_id = 0): string
	{
        return add_query_arg(array('page'=>$instance_id>0?'vms-task-detail':'vms-my-tasks','task_id'=>$instance_id),admin_url('admin.php'));
	}
}

if (!function_exists('bvmgr_tasks_notification_context')) {
	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	function bvmgr_tasks_notification_context(array $row): array
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
			'task_url' => bvmgr_tasks_notification_task_url($instance_id, absint($row['assignee_user_id'] ?? 0)),
		);
	}
}

if (!function_exists('bvmgr_tasks_emit_notification_event')) {
	/**
	 * @param array<string,mixed> $payload
	 */
	function bvmgr_tasks_emit_notification_event(string $event, array $payload): void
	{
		$event = sanitize_key($event);
		if ($event === '') {
			return;
		}
		do_action($event, $payload);
		do_action('vms_tasks_notification_event', $event, $payload);
	}
}

if (!function_exists('bvmgr_tasks_maybe_notify_user')) {
	/**
	 * @param array<string,mixed> $vars
	 */
	function bvmgr_tasks_maybe_notify_user(
		int $user_id,
		string $event_key,
		string $template_key,
		array $vars,
		int $task_instance_id = 0
	): void {
        // Legacy call signature retained; only committed task notification work is eligible.
        bvmgr_tasks_delivery_tick();
	}
}

if (!function_exists('bvmgr_tasks_emit_assignment_notification')) {
	/**
	 * @param array<string,mixed> $row
	 */
	function bvmgr_tasks_emit_assignment_notification(array $row): void
	{
        bvmgr_tasks_delivery_tick();
	}
}

if (!function_exists('bvmgr_tasks_notifications_default_due_soon_window_minutes')) {
	function bvmgr_tasks_notifications_default_due_soon_window_minutes(): int
	{
		return 120;
	}
}

if (!function_exists('bvmgr_tasks_notification_format_floating_local_datetime')) {
	function bvmgr_tasks_notification_format_floating_local_datetime(string $format, string $expression): string
	{
		$utc = new DateTimeZone('UTC');
		try {
			$datetime = trim($expression) === ''
				? new DateTimeImmutable('@0')
				: new DateTimeImmutable($expression, $utc);
		} catch (Exception) {
			$datetime = new DateTimeImmutable('@0');
		}

		return $datetime->setTimezone($utc)->format($format);
	}
}

if (!function_exists('bvmgr_tasks_notification_scan_due_soon')) {
	function bvmgr_tasks_notification_scan_due_soon(): void
	{
        bvmgr_tasks_delivery_tick('reminders');
	}
}

if (!function_exists('bvmgr_tasks_notification_scan_overdue')) {
	function bvmgr_tasks_notification_scan_overdue(): void
	{
        bvmgr_tasks_delivery_tick('reminders');
	}
}

if (!function_exists('bvmgr_tasks_notification_digest_window_end')) {
	function bvmgr_tasks_notification_digest_window_end(string $window, string $now): string
	{
		$window = sanitize_key($window);
		if ($window === 'today') {
			return bvmgr_tasks_notification_format_floating_local_datetime('Y-m-d 23:59:59', $now);
		}
		if ($window === 'next7') {
			return bvmgr_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', $now . ' +7 days');
		}
		return bvmgr_tasks_notification_format_floating_local_datetime('Y-m-d H:i:s', $now . ' +3 days');
	}
}

if (!function_exists('bvmgr_tasks_notification_run_digest')) {
	function bvmgr_tasks_notification_run_digest(): void
	{
        bvmgr_tasks_delivery_tick('digest');
	}
}

if (!function_exists('bvmgr_tasks_notifications_tick')) {
	function bvmgr_tasks_notifications_tick(): void
	{
		if (!bvmgr_tasks_db_ready()) {
			return;
		}
		bvmgr_tasks_notification_scan_due_soon();
		bvmgr_tasks_notification_scan_overdue();
	}
}
add_action('bvmgr_tasks_notifications_tick', 'bvmgr_tasks_notifications_tick');
add_action('vms_tasks_notifications_tick', 'bvmgr_tasks_notifications_tick');

if (!function_exists('bvmgr_tasks_notifications_digest_tick')) {
	function bvmgr_tasks_notifications_digest_tick(): void
	{
		if (!bvmgr_tasks_db_ready()) {
			return;
		}
		bvmgr_tasks_notification_run_digest();
	}
}
add_action('bvmgr_tasks_notifications_digest_tick', 'bvmgr_tasks_notifications_digest_tick');
add_action('vms_tasks_notifications_digest_tick', 'bvmgr_tasks_notifications_digest_tick');

if (!function_exists('bvmgr_tasks_notifications_register_cron_schedules')) {
	function bvmgr_tasks_notifications_register_cron_schedules(array $schedules): array
	{
		if (!isset($schedules['vms_tasks_fifteen_minutes'])) {
			$schedules['vms_tasks_fifteen_minutes'] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display' => __('Every 15 Minutes (Backstage Venue Manager Staff Tasks)', 'backstage-venue-manager'),
			);
		}
		return $schedules;
	}
}
add_filter('cron_schedules', 'bvmgr_tasks_notifications_register_cron_schedules');

if (!function_exists('bvmgr_tasks_notifications_ensure_cron')) {
	function bvmgr_tasks_notifications_ensure_cron(): void
	{
		if (!function_exists('bvmgr_schedule_exists') || !bvmgr_schedule_exists('vms_tasks_fifteen_minutes')) {
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
// Schedules are installed explicitly, never repaired by page reads.
