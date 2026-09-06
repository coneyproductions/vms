<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_Capabilities')) {
	class VMSX_Weather_Risk_Capabilities {
		public static function settings_capability(): string
		{
			return (string) apply_filters('vmsx_weather_risk_settings_capability', 'manage_options');
		}

		public static function can_manage_settings(): bool
		{
			return current_user_can(self::settings_capability());
		}

		public static function can_view_event(int $event_plan_id): bool
		{
			if ($event_plan_id <= 0) {
				return false;
			}

			return current_user_can('edit_post', $event_plan_id) || self::can_manage_settings();
		}

		public static function can_refresh_event(int $event_plan_id): bool
		{
			return self::can_view_event($event_plan_id);
		}
	}
}
