<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_Page_Settings')) {
	class VMSX_Weather_Risk_Page_Settings {
		public static function render(): void
		{
			if (!VMSX_Weather_Risk_Capabilities::can_manage_settings()) {
				return;
			}

			$settings = VMSX_Weather_Risk_Settings::get();
			$notice = isset($_GET['vmsx_weather_risk_notice']) ? sanitize_key((string) $_GET['vmsx_weather_risk_notice']) : '';
			$actions_html = '<a class="button" href="' . esc_url(VMSX_Weather_Risk_Admin_Menu::details_url()) . '">' . esc_html__('Open Show Risk Advisor', 'vmsx-weather-risk') . '</a>';

			$render_content = static function () use ($settings, $notice): void {
				if ($notice === 'saved') {
					echo '<div class="notice notice-success"><p>' . esc_html__('Show Risk settings saved.', 'vmsx-weather-risk') . '</p></div>';
				}

				echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
				wp_nonce_field('vmsx_weather_risk_save_settings');
				echo '<input type="hidden" name="action" value="vmsx_weather_risk_save_settings">';

				echo '<h2>' . esc_html__('General', 'vmsx-weather-risk') . '</h2>';
				echo '<table class="form-table" role="presentation">';
				self::checkbox_row('enabled', __('Enable Show Risk Advisor', 'vmsx-weather-risk'), !empty($settings['enabled']), __('Turns on Event Plan cards, manual refresh, and scheduled advisory refresh.', 'vmsx-weather-risk'));
				self::select_row(
					'provider_mode',
					__('Provider mode', 'vmsx-weather-risk'),
					array(
						'conservative' => __('Most conservative', 'vmsx-weather-risk'),
						'consensus' => __('Consensus / blended', 'vmsx-weather-risk'),
						'preferred' => __('Preferred source', 'vmsx-weather-risk'),
					),
					(string) $settings['provider_mode'],
					__('Choose how the advisory combines enabled providers.', 'vmsx-weather-risk')
				);
				self::select_row(
					'preferred_provider',
					__('Preferred provider', 'vmsx-weather-risk'),
					array(
						'openmeteo' => 'Open-Meteo',
						'noaa' => 'NOAA',
						'openweather' => 'OpenWeather',
						'weatherapi' => 'WeatherAPI',
					),
					(string) $settings['preferred_provider'],
					__('Used only when provider mode is set to Preferred source.', 'vmsx-weather-risk')
				);
				self::number_row('watch_window_days', __('Watch window (days)', 'vmsx-weather-risk'), (string) $settings['watch_window_days'], 1, 14, __('Upcoming events inside this window are eligible for scheduled refresh.', 'vmsx-weather-risk'));
				self::number_row('cache_ttl_minutes', __('Cache TTL (minutes)', 'vmsx-weather-risk'), (string) $settings['cache_ttl_minutes'], 15, 240, __('Provider payloads are cached for this long before a normal refresh pulls fresh data.', 'vmsx-weather-risk'));
				self::number_row('pre_event_buffer_minutes', __('Pre-event buffer (minutes)', 'vmsx-weather-risk'), (string) $settings['pre_event_buffer_minutes'], 0, 360, __('Added before the event start when building the forecast window.', 'vmsx-weather-risk'));
				self::number_row('post_event_buffer_minutes', __('Post-event buffer (minutes)', 'vmsx-weather-risk'), (string) $settings['post_event_buffer_minutes'], 0, 360, __('Added after the event end when building the forecast window.', 'vmsx-weather-risk'));
				self::select_row(
					'decision_style',
					__('Decision style', 'vmsx-weather-risk'),
					array(
						'conservative' => __('Conservative', 'vmsx-weather-risk'),
						'balanced' => __('Balanced', 'vmsx-weather-risk'),
						'aggressive' => __('Aggressive', 'vmsx-weather-risk'),
					),
					(string) $settings['decision_style'],
					__('Controls how quickly the final advisory escalates from Proceed to Consider Cancellation.', 'vmsx-weather-risk')
				);
				echo '</table>';

				echo '<h2>' . esc_html__('Viability thresholds', 'vmsx-weather-risk') . '</h2>';
				echo '<table class="form-table" role="presentation">';
				self::number_row('min_viable_ticket_qty', __('Minimum viable ticket qty', 'vmsx-weather-risk'), (string) $settings['min_viable_ticket_qty'], 0, 50000, __('Used as the baseline sales viability check when live ticket stats are available.', 'vmsx-weather-risk'));
				self::text_row('min_viable_gross_dollars', __('Minimum viable gross ($)', 'vmsx-weather-risk'), number_format(((int) $settings['min_viable_gross_cents']) / 100, 2, '.', ''), __('Used alongside ticket quantity for basic viability scoring.', 'vmsx-weather-risk'));
				self::number_row('weather_weight', __('Weather weight', 'vmsx-weather-risk'), (string) $settings['weather_weight'], 0, 100, __('Relative weight of weather risk in the final advisory.', 'vmsx-weather-risk'));
				self::number_row('sales_weight', __('Sales weight', 'vmsx-weather-risk'), (string) $settings['sales_weight'], 0, 100, __('Relative weight of sales/ticket context in the final advisory.', 'vmsx-weather-risk'));
				self::number_row('financial_weight', __('Financial weight', 'vmsx-weather-risk'), (string) $settings['financial_weight'], 0, 100, __('Relative weight of financial exposure in the final advisory.', 'vmsx-weather-risk'));
				self::number_row('wind_threshold_mph', __('Wind threshold (mph)', 'vmsx-weather-risk'), (string) $settings['wind_threshold_mph'], 5, 100, __('Forecast wind/gust above this threshold materially increases weather risk.', 'vmsx-weather-risk'));
				self::text_row('heavy_rain_threshold_inches', __('Heavy rain threshold (inches)', 'vmsx-weather-risk'), (string) $settings['heavy_rain_threshold_inches'], __('Projected event-window precipitation above this threshold materially increases weather risk.', 'vmsx-weather-risk'));
				self::checkbox_row('lightning_hard_stop', __('Treat lightning as hard stop', 'vmsx-weather-risk'), !empty($settings['lightning_hard_stop']), __('When enabled, thunder/lightning cannot score below High Risk.', 'vmsx-weather-risk'));
				self::checkbox_row('show_provider_details_by_default', __('Open provider details by default', 'vmsx-weather-risk'), !empty($settings['show_provider_details_by_default']), __('Expands provider breakdown sections on the details page by default.', 'vmsx-weather-risk'));
				echo '</table>';

				echo '<h2>' . esc_html__('Providers', 'vmsx-weather-risk') . '</h2>';
				echo '<table class="form-table" role="presentation">';
				self::checkbox_row('provider_openmeteo_enabled', __('Enable Open-Meteo', 'vmsx-weather-risk'), !empty($settings['provider_openmeteo_enabled']), __('Free global provider with hourly forecast data. No API key required.', 'vmsx-weather-risk'));
				self::checkbox_row('provider_noaa_enabled', __('Enable NOAA', 'vmsx-weather-risk'), !empty($settings['provider_noaa_enabled']), __('Free U.S. government provider. Requires a resolvable latitude/longitude.', 'vmsx-weather-risk'));
				self::checkbox_row('provider_openweather_enabled', __('Enable OpenWeather', 'vmsx-weather-risk'), !empty($settings['provider_openweather_enabled']), __('Requires an OpenWeather key with access to hourly forecast data.', 'vmsx-weather-risk'));
				self::password_row('provider_openweather_api_key', __('OpenWeather API key', 'vmsx-weather-risk'), (string) $settings['provider_openweather_api_key']);
				self::checkbox_row('provider_weatherapi_enabled', __('Enable WeatherAPI', 'vmsx-weather-risk'), !empty($settings['provider_weatherapi_enabled']), __('Requires a WeatherAPI key.', 'vmsx-weather-risk'));
				self::password_row('provider_weatherapi_api_key', __('WeatherAPI key', 'vmsx-weather-risk'), (string) $settings['provider_weatherapi_api_key']);
				echo '</table>';

				echo '<h2>' . esc_html__('Fallback location', 'vmsx-weather-risk') . '</h2>';
				echo '<table class="form-table" role="presentation">';
				self::text_row('fallback_location_label', __('Fallback location label', 'vmsx-weather-risk'), (string) $settings['fallback_location_label'], __('Only used when an event lacks precise venue coordinates.', 'vmsx-weather-risk'));
				self::text_row('fallback_latitude', __('Fallback latitude', 'vmsx-weather-risk'), (string) $settings['fallback_latitude'], __('Optional fallback coordinate when venue data is incomplete.', 'vmsx-weather-risk'));
				self::text_row('fallback_longitude', __('Fallback longitude', 'vmsx-weather-risk'), (string) $settings['fallback_longitude'], __('Optional fallback coordinate when venue data is incomplete.', 'vmsx-weather-risk'));
				echo '</table>';

				submit_button(__('Save Show Risk Settings', 'vmsx-weather-risk'));
				echo '</form>';
			};

			$render_shell = VMSX_Weather_Risk_Compatibility::core_function('vms_admin_ui_render_shell');
			if ($render_shell !== '') {
				$render_shell(
					array(
						'title' => __('Show Risk Settings', 'vmsx-weather-risk'),
						'subtitle' => __('Configure provider access, thresholds, cache behavior, and fallback coordinates for Show Risk Advisor.', 'vmsx-weather-risk'),
						'actions_html' => $actions_html,
						'shell_id' => 'vms-weather-risk-settings',
						'content_class' => 'vmsx-weather-risk-admin',
					),
					$render_content
				);
				return;
			}

			echo '<div class="wrap vmsx-weather-risk-admin">';
			echo '<h1>' . esc_html__('Show Risk Settings', 'vmsx-weather-risk') . '</h1>';
			echo '<p class="description">' . esc_html__('Configure provider access, decision thresholds, cache settings, and fallback coordinates for Show Risk Advisor.', 'vmsx-weather-risk') . '</p>';
			$render_content();
			echo '</div>';
		}

		private static function checkbox_row(string $key, string $label, bool $checked, string $description = ''): void
		{
			echo '<tr><th scope="row">' . esc_html($label) . '</th><td>';
			echo '<label><input type="checkbox" name="settings[' . esc_attr($key) . ']" value="1" ' . checked($checked, true, false) . '> ';
			if ($description !== '') {
				echo esc_html($description);
			}
			echo '</label></td></tr>';
		}

		private static function select_row(string $key, string $label, array $options, string $current, string $description = ''): void
		{
			echo '<tr><th scope="row"><label for="vmsx-weather-risk-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
			echo '<select id="vmsx-weather-risk-' . esc_attr($key) . '" name="settings[' . esc_attr($key) . ']">';
			foreach ($options as $value => $option_label) {
				echo '<option value="' . esc_attr((string) $value) . '" ' . selected($current, (string) $value, false) . '>' . esc_html((string) $option_label) . '</option>';
			}
			echo '</select>';
			if ($description !== '') {
				echo '<p class="description">' . esc_html($description) . '</p>';
			}
			echo '</td></tr>';
		}

		private static function number_row(string $key, string $label, string $value, int $min, int $max, string $description = ''): void
		{
			echo '<tr><th scope="row"><label for="vmsx-weather-risk-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
			echo '<input id="vmsx-weather-risk-' . esc_attr($key) . '" type="number" min="' . esc_attr((string) $min) . '" max="' . esc_attr((string) $max) . '" name="settings[' . esc_attr($key) . ']" value="' . esc_attr($value) . '">';
			if ($description !== '') {
				echo '<p class="description">' . esc_html($description) . '</p>';
			}
			echo '</td></tr>';
		}

		private static function text_row(string $key, string $label, string $value, string $description = ''): void
		{
			echo '<tr><th scope="row"><label for="vmsx-weather-risk-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
			echo '<input id="vmsx-weather-risk-' . esc_attr($key) . '" class="regular-text" type="text" name="settings[' . esc_attr($key) . ']" value="' . esc_attr($value) . '">';
			if ($description !== '') {
				echo '<p class="description">' . esc_html($description) . '</p>';
			}
			echo '</td></tr>';
		}

		private static function password_row(string $key, string $label, string $stored_value): void
		{
			echo '<tr><th scope="row"><label for="vmsx-weather-risk-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
			echo '<input id="vmsx-weather-risk-' . esc_attr($key) . '" class="regular-text" type="password" name="settings[' . esc_attr($key) . ']" value="" autocomplete="new-password">';
			if ($stored_value !== '') {
				echo '<p class="description">' . esc_html(sprintf(__('Stored key: %s', 'vmsx-weather-risk'), VMSX_Weather_Risk_Helpers::mask_secret($stored_value))) . '</p>';
			} else {
				echo '<p class="description">' . esc_html__('Leave blank to keep this key empty.', 'vmsx-weather-risk') . '</p>';
			}
			echo '</td></tr>';
		}
	}
}
