<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_tasks_settings_option_key')) {
	function vms_tasks_settings_option_key(): string
	{
		return defined('VMS_OPT_TASKS_SETTINGS') ? (string) VMS_OPT_TASKS_SETTINGS : 'vms_tasks_settings_v1';
	}
}

if (!function_exists('vms_tasks_settings_defaults')) {
	/**
	 * @return array<string,mixed>
	 */
	function vms_tasks_settings_defaults(): array
	{
		return array(
			'horizon_days' => 60,
			'regenerate_on_event_date_change' => 1,
			'regenerate_on_venue_change' => 1,
			'regenerate_on_event_type_change' => 1,
			'show_dashboard_cards' => 1,
			'dashboard_events_lookahead_days' => 14,
			'dashboard_max_events' => 10,
			'notify_assignment_alerts' => 1,
			'notify_due_soon_alerts' => 1,
			'notify_overdue_alerts' => 1,
			'notify_daily_digest' => 0,
			'notify_digest_time' => '08:00',
			'notify_digest_window' => 'next3',
		);
	}
}

if (!function_exists('vms_tasks_sanitize_settings')) {
	/**
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>
	 */
	function vms_tasks_sanitize_settings(array $input): array
	{
		$defaults = vms_tasks_settings_defaults();

		$out = array();
		$out['horizon_days'] = max(1, min(365, absint($input['horizon_days'] ?? $defaults['horizon_days'])));
		$out['regenerate_on_event_date_change'] = !empty($input['regenerate_on_event_date_change']) ? 1 : 0;
		$out['regenerate_on_venue_change'] = !empty($input['regenerate_on_venue_change']) ? 1 : 0;
		$out['regenerate_on_event_type_change'] = !empty($input['regenerate_on_event_type_change']) ? 1 : 0;
		$out['show_dashboard_cards'] = !empty($input['show_dashboard_cards']) ? 1 : 0;
		$out['dashboard_events_lookahead_days'] = max(1, min(90, absint($input['dashboard_events_lookahead_days'] ?? $defaults['dashboard_events_lookahead_days'])));
		$out['dashboard_max_events'] = max(1, min(50, absint($input['dashboard_max_events'] ?? $defaults['dashboard_max_events'])));
		$out['notify_assignment_alerts'] = !empty($input['notify_assignment_alerts']) ? 1 : 0;
		$out['notify_due_soon_alerts'] = !empty($input['notify_due_soon_alerts']) ? 1 : 0;
		$out['notify_overdue_alerts'] = !empty($input['notify_overdue_alerts']) ? 1 : 0;
		$out['notify_daily_digest'] = !empty($input['notify_daily_digest']) ? 1 : 0;
		$digest_time = sanitize_text_field((string) ($input['notify_digest_time'] ?? $defaults['notify_digest_time']));
		if (!preg_match('/^\d{2}:\d{2}$/', $digest_time)) {
			$digest_time = (string) $defaults['notify_digest_time'];
		}
		$out['notify_digest_time'] = $digest_time;
		$digest_window = sanitize_key((string) ($input['notify_digest_window'] ?? $defaults['notify_digest_window']));
		if (!in_array($digest_window, array('today', 'next3', 'next7'), true)) {
			$digest_window = (string) $defaults['notify_digest_window'];
		}
		$out['notify_digest_window'] = $digest_window;

		return $out;
	}
}

if (!function_exists('vms_tasks_get_settings')) {
	/**
	 * @return array<string,mixed>
	 */
	function vms_tasks_get_settings(): array
	{
		$defaults = vms_tasks_settings_defaults();
		$stored = get_option(vms_tasks_settings_option_key(), array());
		if (!is_array($stored)) {
			$stored = array();
		}

		return vms_tasks_sanitize_settings(array_merge($defaults, $stored));
	}
}

if (!function_exists('vms_tasks_update_settings')) {
	/**
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>
	 */
	function vms_tasks_update_settings(array $input): array
	{
		$settings = vms_tasks_sanitize_settings($input);
		update_option(vms_tasks_settings_option_key(), $settings, false);
		return $settings;
	}
}
