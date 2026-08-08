<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_email_followups_cron_hook')) {
	function vms_email_followups_cron_hook(): string
	{
		return 'vms_email_followups_cron';
	}
}

if (!function_exists('vms_email_followups_scheduled_timestamp')) {
	function vms_email_followups_scheduled_timestamp(int $event_plan_id, string $email_key): int
	{
		$definitions = vms_email_followups_template_definitions();
		if (!isset($definitions[$email_key])) {
			return 0;
		}
		$context = vms_email_followups_event_context($event_plan_id);
		if (empty($context['event_date'])) {
			return 0;
		}
		$settings = vms_email_followups_settings();
		$def = (array) $definitions[$email_key];
		if (($def['kind'] ?? '') === 'manual') {
			return 0;
		}
		$offset_days = (int) ($def['offset_days'] ?? 0);
		if (isset($def['send_hour'])) {
			$hour = min(23, max(0, (int) $def['send_hour']));
		} else {
			$hour_key = (string) ($def['hour_key'] ?? 'send_hour');
			$hour = min(23, max(0, (int) ($settings[$hour_key] ?? 9)));
		}
		$base_ts = strtotime((string) $context['event_date'] . ' 00:00:00');
		if (!$base_ts) {
			return 0;
		}
		return $base_ts + ($offset_days * DAY_IN_SECONDS) + ($hour * HOUR_IN_SECONDS);
	}
}

if (!function_exists('vms_email_followups_schedule_cron')) {
	function vms_email_followups_schedule_cron(): void
	{
		if (function_exists('vms_should_run_runtime_maintenance') && !vms_should_run_runtime_maintenance()) {
			return;
		}
		if (!wp_next_scheduled(vms_email_followups_cron_hook())) {
			wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', vms_email_followups_cron_hook());
		}
	}
}
add_action('init', 'vms_email_followups_schedule_cron');

if (!function_exists('vms_email_followups_due_items')) {
	function vms_email_followups_due_items(): array
	{
		$settings = vms_email_followups_settings();
		if (empty($settings['enabled']) || empty($settings['auto_send_enabled'])) {
			return array();
		}
		$now = time();
		$window = max(1, (int) ($settings['reminder_window_hours'] ?? 24)) * HOUR_IN_SECONDS;
		$from = wp_date('Y-m-d', $now - (65 * DAY_IN_SECONDS), wp_timezone());
		$to = wp_date('Y-m-d', $now + (65 * DAY_IN_SECONDS), wp_timezone());
		$plans = get_posts(array(
			'post_type' => 'vms_event_plan',
			'post_status' => 'publish',
			'posts_per_page' => 100,
			'fields' => 'ids',
			'orderby' => 'meta_value',
			'order' => 'ASC',
			'meta_key' => '_vms_event_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- The hourly cron query intentionally orders at most 100 Event Plans across its 130-day window by canonical event-date metadata.
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The hourly cron query intentionally filters at most 100 Event Plans to its 130-day window by canonical event-date metadata.
				array(
					'key' => '_vms_event_date',
					'value' => array($from, $to),
					'compare' => 'BETWEEN',
					'type' => 'DATE',
				),
			),
		));
		if (!is_array($plans)) {
			$plans = array();
		}
		$items = array();
		$enabled = is_array($settings['templates_enabled'] ?? null) ? (array) $settings['templates_enabled'] : array();
		foreach ($plans as $plan_id_raw) {
			$plan_id = absint($plan_id_raw);
			$context = vms_email_followups_event_context($plan_id);
			list($allowed) = vms_email_followups_context_allows_send($context);
			if (!$allowed) {
				continue;
			}
			foreach (vms_email_followups_template_definitions() as $email_key => $def) {
				if (($def['kind'] ?? '') !== 'scheduled') {
					continue;
				}
				if (empty($enabled[$email_key])) {
					continue;
				}
				$scheduled_ts = vms_email_followups_scheduled_timestamp($plan_id, $email_key);
				if ($scheduled_ts <= 0 || $scheduled_ts > $now || $scheduled_ts < ($now - $window)) {
					continue;
				}
				if (vms_email_followups_was_sent($plan_id, $email_key)) {
					continue;
				}
				$items[] = array(
					'event_plan_id' => $plan_id,
					'email_key' => $email_key,
					'scheduled_ts' => $scheduled_ts,
				);
			}
		}
		return $items;
	}
}

if (!function_exists('vms_email_followups_cron_run')) {
	function vms_email_followups_cron_run(): void
	{
		foreach (vms_email_followups_due_items() as $item) {
			vms_email_followups_send_event_email((string) ($item['email_key'] ?? ''), absint($item['event_plan_id'] ?? 0), 'auto');
		}
	}
}
add_action(vms_email_followups_cron_hook(), 'vms_email_followups_cron_run');
