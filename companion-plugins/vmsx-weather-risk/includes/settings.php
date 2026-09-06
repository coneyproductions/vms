<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_Settings')) {
	class VMSX_Weather_Risk_Settings {
		public static function init(): void
		{
			add_action('admin_post_vmsx_weather_risk_save_settings', array(__CLASS__, 'handle_post'));
		}

		public static function defaults(): array
		{
			return array(
				'enabled' => 1,
				'provider_mode' => 'conservative',
				'preferred_provider' => 'openmeteo',
				'cache_ttl_minutes' => 60,
				'watch_window_days' => 10,
				'pre_event_buffer_minutes' => 120,
				'post_event_buffer_minutes' => 60,
				'decision_style' => 'conservative',
				'min_viable_ticket_qty' => 75,
				'min_viable_gross_cents' => 150000,
				'weather_weight' => 50,
				'sales_weight' => 35,
				'financial_weight' => 15,
				'provider_openmeteo_enabled' => 1,
				'provider_openweather_enabled' => 0,
				'provider_openweather_api_key' => '',
				'provider_weatherapi_enabled' => 0,
				'provider_weatherapi_api_key' => '',
				'provider_noaa_enabled' => 1,
				'wind_threshold_mph' => 25,
				'heavy_rain_threshold_inches' => '0.25',
				'lightning_hard_stop' => 1,
				'show_provider_details_by_default' => 0,
				'fallback_location_label' => '',
				'fallback_latitude' => '',
				'fallback_longitude' => '',
			);
		}

		public static function get(): array
		{
			$stored = get_option(VMSX_WR_OPTION_SETTINGS, array());
			if (!is_array($stored)) {
				$stored = array();
			}

			return wp_parse_args($stored, self::defaults());
		}

		public static function seed_defaults(): void
		{
			$stored = get_option(VMSX_WR_OPTION_SETTINGS, null);
			if (!is_array($stored)) {
				add_option(VMSX_WR_OPTION_SETTINGS, self::defaults(), '', false);
			}
		}

		public static function sanitize(array $input, array $existing = array()): array
		{
			$defaults = self::defaults();
			$existing = wp_parse_args($existing, $defaults);
			$out = $defaults;

			$provider_modes = array('conservative', 'consensus', 'preferred');
			$decision_styles = array('conservative', 'balanced', 'aggressive');
			$preferred = sanitize_key((string) ($input['preferred_provider'] ?? $existing['preferred_provider']));
			if (!in_array($preferred, array('openmeteo', 'noaa', 'openweather', 'weatherapi'), true)) {
				$preferred = 'noaa';
			}

			$out['enabled'] = VMSX_Weather_Risk_Helpers::yes_no($input['enabled'] ?? 0);
			$out['provider_mode'] = sanitize_key((string) ($input['provider_mode'] ?? $existing['provider_mode']));
			if (!in_array($out['provider_mode'], $provider_modes, true)) {
				$out['provider_mode'] = $defaults['provider_mode'];
			}
			$out['preferred_provider'] = $preferred;
			$out['cache_ttl_minutes'] = VMSX_Weather_Risk_Helpers::clamp_int($input['cache_ttl_minutes'] ?? $existing['cache_ttl_minutes'], 15, 240);
			$out['watch_window_days'] = VMSX_Weather_Risk_Helpers::clamp_int($input['watch_window_days'] ?? $existing['watch_window_days'], 1, 14);
			$out['pre_event_buffer_minutes'] = VMSX_Weather_Risk_Helpers::clamp_int($input['pre_event_buffer_minutes'] ?? $existing['pre_event_buffer_minutes'], 0, 360);
			$out['post_event_buffer_minutes'] = VMSX_Weather_Risk_Helpers::clamp_int($input['post_event_buffer_minutes'] ?? $existing['post_event_buffer_minutes'], 0, 360);
			$out['decision_style'] = sanitize_key((string) ($input['decision_style'] ?? $existing['decision_style']));
			if (!in_array($out['decision_style'], $decision_styles, true)) {
				$out['decision_style'] = $defaults['decision_style'];
			}
			$out['min_viable_ticket_qty'] = VMSX_Weather_Risk_Helpers::clamp_int($input['min_viable_ticket_qty'] ?? $existing['min_viable_ticket_qty'], 0, 50000);
			$out['min_viable_gross_cents'] = max(0, VMSX_Weather_Risk_Helpers::sanitize_money_to_cents($input['min_viable_gross_dollars'] ?? ($existing['min_viable_gross_cents'] / 100)));
			$out['weather_weight'] = VMSX_Weather_Risk_Helpers::clamp_int($input['weather_weight'] ?? $existing['weather_weight'], 0, 100);
			$out['sales_weight'] = VMSX_Weather_Risk_Helpers::clamp_int($input['sales_weight'] ?? $existing['sales_weight'], 0, 100);
			$out['financial_weight'] = VMSX_Weather_Risk_Helpers::clamp_int($input['financial_weight'] ?? $existing['financial_weight'], 0, 100);
			$out['provider_openmeteo_enabled'] = VMSX_Weather_Risk_Helpers::yes_no($input['provider_openmeteo_enabled'] ?? 0);
			$out['provider_openweather_enabled'] = VMSX_Weather_Risk_Helpers::yes_no($input['provider_openweather_enabled'] ?? 0);
			$out['provider_weatherapi_enabled'] = VMSX_Weather_Risk_Helpers::yes_no($input['provider_weatherapi_enabled'] ?? 0);
			$out['provider_noaa_enabled'] = VMSX_Weather_Risk_Helpers::yes_no($input['provider_noaa_enabled'] ?? 0);
			$out['wind_threshold_mph'] = VMSX_Weather_Risk_Helpers::clamp_int($input['wind_threshold_mph'] ?? $existing['wind_threshold_mph'], 5, 100);
			$out['heavy_rain_threshold_inches'] = VMSX_Weather_Risk_Helpers::sanitize_float_string($input['heavy_rain_threshold_inches'] ?? $existing['heavy_rain_threshold_inches'], 2);
			if ($out['heavy_rain_threshold_inches'] === '') {
				$out['heavy_rain_threshold_inches'] = $defaults['heavy_rain_threshold_inches'];
			}
			$out['lightning_hard_stop'] = VMSX_Weather_Risk_Helpers::yes_no($input['lightning_hard_stop'] ?? 0);
			$out['show_provider_details_by_default'] = VMSX_Weather_Risk_Helpers::yes_no($input['show_provider_details_by_default'] ?? 0);
			$out['fallback_location_label'] = sanitize_text_field((string) ($input['fallback_location_label'] ?? $existing['fallback_location_label']));
			$out['fallback_latitude'] = VMSX_Weather_Risk_Helpers::sanitize_float_string($input['fallback_latitude'] ?? $existing['fallback_latitude'], 6);
			$out['fallback_longitude'] = VMSX_Weather_Risk_Helpers::sanitize_float_string($input['fallback_longitude'] ?? $existing['fallback_longitude'], 6);

			$openweather_key = trim((string) ($input['provider_openweather_api_key'] ?? ''));
			$weatherapi_key = trim((string) ($input['provider_weatherapi_api_key'] ?? ''));
			$out['provider_openweather_api_key'] = $openweather_key !== ''
				? sanitize_text_field($openweather_key)
				: (string) ($existing['provider_openweather_api_key'] ?? '');
			$out['provider_weatherapi_api_key'] = $weatherapi_key !== ''
				? sanitize_text_field($weatherapi_key)
				: (string) ($existing['provider_weatherapi_api_key'] ?? '');

			return $out;
		}

		public static function update(array $settings): void
		{
			update_option(VMSX_WR_OPTION_SETTINGS, $settings, false);
		}

		public static function handle_post(): void
		{
			if (!VMSX_Weather_Risk_Capabilities::can_manage_settings()) {
				wp_die('Forbidden', 403);
			}

			check_admin_referer('vmsx_weather_risk_save_settings');

			$raw = isset($_POST['settings']) && is_array($_POST['settings']) ? wp_unslash($_POST['settings']) : array();
			$settings = self::sanitize((array) $raw, self::get());
			self::update($settings);

			VMSX_Weather_Risk_Logging::write(
				'Show Risk settings updated.',
				array('source' => 'settings'),
				'info'
			);

			$redirect = add_query_arg(
				array(
					'page' => 'vms-weather-risk-settings',
					'vmsx_weather_risk_notice' => 'saved',
				),
				admin_url('admin.php')
			);

			wp_safe_redirect($redirect);
			exit;
		}
	}
}
