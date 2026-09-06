<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_Provider_OpenWeather')) {
	class VMSX_Weather_Risk_Provider_OpenWeather implements VMSX_Weather_Risk_Provider_Interface {
		public function slug(): string
		{
			return 'openweather';
		}

		public function name(): string
		{
			return 'OpenWeather';
		}

		public function is_enabled(array $settings): bool
		{
			return !empty($settings['provider_openweather_enabled']) && !empty($settings['provider_openweather_api_key']);
		}

		public function fetch(array $location, array $window, array $settings): array
		{
			if (!isset($location['latitude'], $location['longitude']) || !is_numeric($location['latitude']) || !is_numeric($location['longitude'])) {
				return VMSX_Weather_Risk_Weather_Normalizer::error_payload(
					$this->slug(),
					$this->name(),
					__('OpenWeather requires latitude and longitude.', 'vmsx-weather-risk'),
					$location
				);
			}

			$key = trim((string) ($settings['provider_openweather_api_key'] ?? ''));
			if ($key === '') {
				return VMSX_Weather_Risk_Weather_Normalizer::error_payload(
					$this->slug(),
					$this->name(),
					__('OpenWeather API key is missing.', 'vmsx-weather-risk'),
					$location,
					'skipped'
				);
			}

			$url = add_query_arg(array(
				'lat' => round((float) $location['latitude'], 6),
				'lon' => round((float) $location['longitude'], 6),
				'exclude' => 'current,minutely,daily',
				'units' => 'imperial',
				'appid' => $key,
			), 'https://api.openweathermap.org/data/3.0/onecall');

			$data = VMSX_Weather_Risk_Helpers::remote_get_json($url);
			if (is_wp_error($data)) {
				return VMSX_Weather_Risk_Weather_Normalizer::error_payload($this->slug(), $this->name(), $data->get_error_message(), $location);
			}

			$hourly = isset($data['hourly']) && is_array($data['hourly']) ? $data['hourly'] : array();
			$alerts = isset($data['alerts']) && is_array($data['alerts']) ? $data['alerts'] : array();
			$hours = array();

			foreach ($hourly as $entry) {
				if (!is_array($entry)) {
					continue;
				}
				$ts = isset($entry['dt']) ? (int) $entry['dt'] : 0;
				if ($ts <= 0) {
					continue;
				}
				$weather = isset($entry['weather'][0]) && is_array($entry['weather'][0]) ? $entry['weather'][0] : array();
				$summary = sanitize_text_field((string) ($weather['description'] ?? ($weather['main'] ?? '')));
				$rain_mm = isset($entry['rain']['1h']) && is_numeric($entry['rain']['1h']) ? (float) $entry['rain']['1h'] : 0.0;
				$snow_mm = isset($entry['snow']['1h']) && is_numeric($entry['snow']['1h']) ? (float) $entry['snow']['1h'] : 0.0;
				$hours[] = array(
					'timestamp_utc' => $ts,
					'precip_probability' => (int) round(((float) ($entry['pop'] ?? 0)) * 100),
					'precip_amount' => round(($rain_mm + $snow_mm) * 0.0393701, 2),
					'wind_mph' => max(
						(float) ($entry['wind_speed'] ?? 0),
						(float) ($entry['wind_gust'] ?? 0)
					),
					'temperature_f' => isset($entry['temp']) && is_numeric($entry['temp']) ? (float) $entry['temp'] : null,
					'lightning_risk_flag' => VMSX_Weather_Risk_Helpers::contains_keywords((string) ($weather['main'] ?? '') . ' ' . $summary, array('thunder')),
					'severe_flag' => VMSX_Weather_Risk_Helpers::contains_keywords((string) ($weather['main'] ?? '') . ' ' . $summary, array('thunder', 'storm', 'tornado', 'hail')),
					'summary' => $summary,
				);
			}

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
