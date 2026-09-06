<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_Scheduler')) {
	class VMSX_Weather_Risk_Scheduler {
		public static function init(): void
		{
			add_filter('cron_schedules', array(__CLASS__, 'register_schedule'));
			add_action(VMSX_WR_CRON_HOOK, array(__CLASS__, 'run'));
			add_action('init', array(__CLASS__, 'ensure_scheduled'));
			add_action('vms_event_plan_saved', array(__CLASS__, 'invalidate_event_snapshot'), 10, 2);
		}

		public static function activate(): void
		{
			add_filter('cron_schedules', array(__CLASS__, 'register_schedule'));
			self::ensure_scheduled();
		}

		public static function deactivate(): void
		{
			wp_clear_scheduled_hook(VMSX_WR_CRON_HOOK);
		}

		public static function register_schedule(array $schedules): array
		{
			if (!isset($schedules['vmsx_weather_risk_hourly'])) {
				$schedules['vmsx_weather_risk_hourly'] = array(
					'interval' => HOUR_IN_SECONDS,
					'display' => __('Hourly (Show Risk Advisor)', 'vmsx-weather-risk'),
				);
			}
			return $schedules;
		}

		public static function ensure_scheduled(): void
		{
			if (!wp_next_scheduled(VMSX_WR_CRON_HOOK)) {
				wp_schedule_event(time() + 300, 'vmsx_weather_risk_hourly', VMSX_WR_CRON_HOOK);
			}
		}

		public static function invalidate_event_snapshot(int $event_plan_id, array $context = array()): void
		{
			unset($context);
			if ($event_plan_id <= 0) {
				return;
			}
			VMSX_Weather_Risk_Cache::delete_snapshot($event_plan_id);
		}

		public static function run(): void
		{
			$settings = VMSX_Weather_Risk_Settings::get();
			if (empty($settings['enabled'])) {
				return;
			}

			$today = VMSX_Weather_Risk_Helpers::now_local()->format('Y-m-d');
			$end = VMSX_Weather_Risk_Helpers::now_local()->modify('+' . max(1, (int) ($settings['watch_window_days'] ?? 10)) . ' days')->format('Y-m-d');
			$date_key = VMSX_Weather_Risk_Helpers::event_meta_key('date', '_vms_event_date');
			$ids = get_posts(array(
				'post_type' => 'vms_event_plan',
				'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
				'posts_per_page' => 80,
				'orderby' => 'meta_value',
				'order' => 'ASC',
				'meta_key' => $date_key,
				'fields' => 'ids',
				'suppress_filters' => false,
				'meta_query' => array(
					array(
						'key' => $date_key,
						'value' => array($today, $end),
						'compare' => 'BETWEEN',
						'type' => 'DATE',
					),
				),
			));

			foreach ((array) $ids as $event_plan_id) {
				$event_plan_id = (int) $event_plan_id;
				if ($event_plan_id <= 0) {
					continue;
				}
				$window = VMSX_Weather_Risk_Decision_Window::build($event_plan_id, $settings);
				if (empty($window['ok'])) {
					continue;
				}
				if (!self::is_due($event_plan_id, $window)) {
					continue;
				}
				VMSX_Weather_Risk_Advisory_Engine::refresh_snapshot($event_plan_id, false, 'cron');
			}
		}

		private static function is_due(int $event_plan_id, array $window): bool
		{
			$snapshot = VMSX_Weather_Risk_Cache::get_snapshot($event_plan_id);
			$last = is_array($snapshot) ? (int) ($snapshot['computed_at_utc'] ?? 0) : 0;
			if ($last <= 0) {
				return true;
			}

			$days_out = (int) ($window['days_until_event'] ?? 999);
			$interval = DAY_IN_SECONDS;
			if ($days_out <= 2) {
				$interval = HOUR_IN_SECONDS;
			} elseif ($days_out <= 5) {
				$interval = 4 * HOUR_IN_SECONDS;
			}

			return (time() - $last) >= $interval;
		}
	}
}
