<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_Provider_WeatherAPI')) {
	class VMSX_Weather_Risk_Provider_WeatherAPI implements VMSX_Weather_Risk_Provider_Interface {
		public function slug(): string
		{
			return 'weatherapi';
		}

		public function name(): string
		{
			return 'WeatherAPI';
		}

		public function is_enabled(array $settings): bool
		{
			return !empty($settings['provider_weatherapi_enabled']) && !empty($settings['provider_weatherapi_api_key']);
		}

		public function fetch(array $location, array $window, array $settings): array
		{
			if (!isset($location['latitude'], $location['longitude']) || !is_numeric($location['latitude']) || !is_numeric($location['longitude'])) {
				return VMSX_Weather_Risk_Weather_Normalizer::error_payload(
					$this->slug(),
					$this->name(),
					__('WeatherAPI requires latitude and longitude.', 'vmsx-weather-risk'),
					$location
				);
			}

			$key = trim((string) ($settings['provider_weatherapi_api_key'] ?? ''));
			if ($key === '') {
				return VMSX_Weather_Risk_Weather_Normalizer::error_payload(
					$this->slug(),
					$this->name(),
					__('WeatherAPI key is missing.', 'vmsx-weather-risk'),
					$location,
					'skipped'
				);
			}

			$window_start = max(0, (int) ($window['window_start_utc'] ?? 0));
			$window_end = max($window_start, (int) ($window['window_end_utc'] ?? 0));
			$days = (int) ceil(($window_end - $window_start) / DAY_IN_SECONDS) + 1;
			$days = max(1, min(14, $days));

			$url = add_query_arg(array(
				'key' => $key,
				'q' => round((float) $location['latitude'], 6) . ',' . round((float) $location['longitude'], 6),
				'days' => $days,
				'alerts' => 'yes',
				'aqi' => 'no',
			), 'https://api.weatherapi.com/v1/forecast.json');

			$data = VMSX_Weather_Risk_Helpers::remote_get_json($url);
			if (is_wp_error($data)) {
				return VMSX_Weather_Risk_Weather_Normalizer::error_payload($this->slug(), $this->name(), $data->get_error_message(), $location);
			}

			$forecast_days = isset($data['forecast']['forecastday']) && is_array($data['forecast']['forecastday'])
				? $data['forecast']['forecastday']
				: array();
			$hours = array();
			foreach ($forecast_days as $day) {
				if (!is_array($day) || empty($day['hour']) || !is_array($day['hour'])) {
					continue;
				}
				foreach ($day['hour'] as $hour) {
					if (!is_array($hour)) {
						continue;
					}
					$ts = isset($hour['time_epoch']) ? (int) $hour['time_epoch'] : 0;
					if ($ts <= 0) {
						continue;
					}
					$condition = isset($hour['condition']) && is_array($hour['condition']) ? $hour['condition'] : array();
					$summary = sanitize_text_field((string) ($condition['text'] ?? ''));
					$chance_rain = isset($hour['chance_of_rain']) ? (int) $hour['chance_of_rain'] : 0;
					$chance_snow = isset($hour['chance_of_snow']) ? (int) $hour['chance_of_snow'] : 0;
					$hours[] = array(
						'timestamp_utc' => $ts,
						'precip_probability' => max($chance_rain, $chance_snow),
						'precip_amount' => isset($hour['precip_in']) && is_numeric($hour['precip_in']) ? (float) $hour['precip_in'] : 0.0,
						'wind_mph' => max(
							(float) ($hour['wind_mph'] ?? 0),
							(float) ($hour['gust_mph'] ?? 0)
						),
						'temperature_f' => isset($hour['temp_f']) && is_numeric($hour['temp_f']) ? (float) $hour['temp_f'] : null,
						'lightning_risk_flag' => !empty($hour['will_it_thunder']) || VMSX_Weather_Risk_Helpers::contains_keywords($summary, array('thunder', 'lightning')),
						'severe_flag' => VMSX_Weather_Risk_Helpers::contains_keywords($summary, array('severe', 'hail', 'tornado', 'thunder')),
						'summary' => $summary,
					);
				}
			}

			$alerts = isset($data['alerts']['alert']) && is_array($data['alerts']['alert']) ? $data['alerts']['alert'] : array();

			return VMSX_Weather_Risk_Weather_Normalizer::success_payload(
				$this->slug(),
				$this->name(),
				$hours,
				$window,
				$location,
				$settings,
				array('alerts' => $alerts)
			);
		}
	}
}
