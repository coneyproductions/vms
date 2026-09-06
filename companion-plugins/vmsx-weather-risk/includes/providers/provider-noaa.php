<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_Provider_NOAA')) {
	class VMSX_Weather_Risk_Provider_NOAA implements VMSX_Weather_Risk_Provider_Interface {
		public function slug(): string
		{
			return 'noaa';
		}

		public function name(): string
		{
			return 'NOAA';
		}

		public function is_enabled(array $settings): bool
		{
			return !empty($settings['provider_noaa_enabled']);
		}

		public function fetch(array $location, array $window, array $settings): array
		{
			unset($settings);

			if (!isset($location['latitude'], $location['longitude']) || !is_numeric($location['latitude']) || !is_numeric($location['longitude'])) {
				return VMSX_Weather_Risk_Weather_Normalizer::error_payload(
					$this->slug(),
					$this->name(),
					__('NOAA requires latitude and longitude.', 'vmsx-weather-risk'),
					$location
				);
			}

			$lat = round((float) $location['latitude'], 4);
			$lon = round((float) $location['longitude'], 4);
			$contact = sanitize_email((string) get_option('admin_email'));
			$user_agent = sprintf('vmsx-weather-risk/%s (%s; %s)', VMSX_WR_VERSION, (string) home_url('/'), $contact !== '' ? $contact : 'weather@local');
			$args = array(
				'headers' => array(
					'Accept' => 'application/geo+json, application/json',
					'User-Agent' => $user_agent,
				),
			);

			$points = VMSX_Weather_Risk_Helpers::remote_get_json(
				'https://api.weather.gov/points/' . rawurlencode((string) $lat) . ',' . rawurlencode((string) $lon),
				$args
			);
			if (is_wp_error($points)) {
				return VMSX_Weather_Risk_Weather_Normalizer::error_payload($this->slug(), $this->name(), $points->get_error_message(), $location);
			}

			$forecast_url = (string) ($points['properties']['forecastHourly'] ?? '');
			if ($forecast_url === '') {
				return VMSX_Weather_Risk_Weather_Normalizer::error_payload(
					$this->slug(),
					$this->name(),
					__('NOAA did not return an hourly forecast endpoint for this location.', 'vmsx-weather-risk'),
					$location
				);
			}

			$forecast = VMSX_Weather_Risk_Helpers::remote_get_json($forecast_url, $args);
			if (is_wp_error($forecast)) {
				return VMSX_Weather_Risk_Weather_Normalizer::error_payload($this->slug(), $this->name(), $forecast->get_error_message(), $location);
			}

			$periods = isset($forecast['properties']['periods']) && is_array($forecast['properties']['periods'])
				? $forecast['properties']['periods']
				: array();
			$hours = array();
			foreach ($periods as $period) {
				if (!is_array($period)) {
					continue;
				}
				$ts = strtotime((string) ($period['startTime'] ?? ''));
				if ($ts === false || $ts <= 0) {
					continue;
				}
				$summary = sanitize_text_field((string) ($period['shortForecast'] ?? ''));
				$hours[] = array(
					'timestamp_utc' => $ts,
					'precip_probability' => (int) round((float) (($period['probabilityOfPrecipitation']['value'] ?? 0) ?: 0)),
					'precip_amount' => null,
					'wind_mph' => VMSX_Weather_Risk_Helpers::parse_wind_mph($period['windSpeed'] ?? ''),
					'temperature_f' => isset($period['temperature']) && is_numeric($period['temperature']) ? (float) $period['temperature'] : null,
					'lightning_risk_flag' => VMSX_Weather_Risk_Helpers::contains_keywords($summary, array('thunder', 'lightning')),
					'severe_flag' => VMSX_Weather_Risk_Helpers::contains_keywords($summary, array('severe', 'hail', 'tornado', 'damaging', 'thunderstorm')),
					'summary' => $summary,
				);
			}

			$relative_location = isset($points['properties']['relativeLocation']['properties']) && is_array($points['properties']['relativeLocation']['properties'])
				? $points['properties']['relativeLocation']['properties']
				: array();
			$location_label = trim(implode(', ', array_filter(array(
				(string) ($relative_location['city'] ?? ''),
				(string) ($relative_location['state'] ?? ''),
			))));

			return VMSX_Weather_Risk_Weather_Normalizer::success_payload(
				$this->slug(),
				$this->name(),
				$hours,
				$window,
				array_merge($location, array(
					'label' => $location_label !== '' ? $location_label : (string) ($location['label'] ?? ''),
				)),
				array()
			);
		}
	}
}
