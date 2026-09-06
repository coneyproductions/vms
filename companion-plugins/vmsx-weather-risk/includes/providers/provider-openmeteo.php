<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_Provider_OpenMeteo')) {
	class VMSX_Weather_Risk_Provider_OpenMeteo implements VMSX_Weather_Risk_Provider_Interface {
		public function slug(): string
		{
			return 'openmeteo';
		}

		public function name(): string
		{
			return 'Open-Meteo';
		}

		public function is_enabled(array $settings): bool
		{
			return !empty($settings['provider_openmeteo_enabled']);
		}

		public function fetch(array $location, array $window, array $settings): array
		{
			unset($settings);

			if (!isset($location['latitude'], $location['longitude']) || !is_numeric($location['latitude']) || !is_numeric($location['longitude'])) {
				return VMSX_Weather_Risk_Weather_Normalizer::error_payload(
					$this->slug(),
					$this->name(),
					__('Open-Meteo requires latitude and longitude.', 'vmsx-weather-risk'),
					$location
				);
			}

			$url = add_query_arg(array(
				'latitude' => round((float) $location['latitude'], 6),
				'longitude' => round((float) $location['longitude'], 6),
				'hourly' => 'temperature_2m,precipitation_probability,precipitation,windspeed_10m,windgusts_10m,weathercode',
				'timezone' => 'UTC',
				'temperature_unit' => 'fahrenheit',
				'wind_speed_unit' => 'mph',
				'precipitation_unit' => 'inch',
				'forecast_days' => 16,
			), 'https://api.open-meteo.com/v1/forecast');

			$data = VMSX_Weather_Risk_Helpers::remote_get_json($url, array(
				'headers' => array(
					'Accept' => 'application/json',
					'User-Agent' => sprintf('vmsx-weather-risk/%s (%s)', VMSX_WR_VERSION, (string) home_url('/')),
				),
			));

			if (is_wp_error($data)) {
				return VMSX_Weather_Risk_Weather_Normalizer::error_payload($this->slug(), $this->name(), $data->get_error_message(), $location);
			}

			$hourly = isset($data['hourly']) && is_array($data['hourly']) ? $data['hourly'] : array();
			$times = isset($hourly['time']) && is_array($hourly['time']) ? $hourly['time'] : array();
			if (empty($times)) {
				return VMSX_Weather_Risk_Weather_Normalizer::error_payload($this->slug(), $this->name(), __('Open-Meteo did not return hourly rows for this location.', 'vmsx-weather-risk'), $location);
			}

			$pops = isset($hourly['precipitation_probability']) && is_array($hourly['precipitation_probability']) ? $hourly['precipitation_probability'] : array();
			$precips = isset($hourly['precipitation']) && is_array($hourly['precipitation']) ? $hourly['precipitation'] : array();
			$winds = isset($hourly['windspeed_10m']) && is_array($hourly['windspeed_10m']) ? $hourly['windspeed_10m'] : array();
			$gusts = isset($hourly['windgusts_10m']) && is_array($hourly['windgusts_10m']) ? $hourly['windgusts_10m'] : array();
			$temps = isset($hourly['temperature_2m']) && is_array($hourly['temperature_2m']) ? $hourly['temperature_2m'] : array();
			$codes = isset($hourly['weathercode']) && is_array($hourly['weathercode']) ? $hourly['weathercode'] : array();

			$hours = array();
			foreach ($times as $i => $time_value) {
				$ts = strtotime((string) $time_value . ' UTC');
				if ($ts === false || $ts <= 0) {
					continue;
				}
				$code = isset($codes[$i]) && is_numeric($codes[$i]) ? (int) $codes[$i] : null;
				$wind = null;
				if (isset($winds[$i]) && is_numeric($winds[$i])) {
					$wind = (float) $winds[$i];
				}
				if (isset($gusts[$i]) && is_numeric($gusts[$i])) {
					$wind = $wind === null ? (float) $gusts[$i] : max($wind, (float) $gusts[$i]);
				}

				$hours[] = array(
					'timestamp_utc' => $ts,
					'precip_probability' => isset($pops[$i]) && is_numeric($pops[$i]) ? (int) round((float) $pops[$i]) : 0,
					'precip_amount' => isset($precips[$i]) && is_numeric($precips[$i]) ? round((float) $precips[$i], 2) : null,
					'wind_mph' => $wind !== null ? round($wind, 1) : null,
					'temperature_f' => isset($temps[$i]) && is_numeric($temps[$i]) ? round((float) $temps[$i], 1) : null,
					'lightning_risk_flag' => in_array($code, array(95, 96, 99), true),
					'severe_flag' => in_array($code, array(65, 67, 82, 86, 95, 96, 99), true),
					'summary' => self::code_summary($code),
				);
			}

			return VMSX_Weather_Risk_Weather_Normalizer::success_payload(
				$this->slug(),
				$this->name(),
				$hours,
				$window,
				$location,
				array()
			);
		}

		private static function code_summary(?int $code): string
		{
			$map = array(
				0 => 'Clear',
				1 => 'Mostly clear',
				2 => 'Partly cloudy',
				3 => 'Overcast',
				45 => 'Fog',
				48 => 'Rime fog',
				51 => 'Light drizzle',
				53 => 'Drizzle',
				55 => 'Heavy drizzle',
				56 => 'Freezing drizzle',
				57 => 'Heavy freezing drizzle',
				61 => 'Light rain',
				63 => 'Rain',
				65 => 'Heavy rain',
				66 => 'Freezing rain',
				67 => 'Heavy freezing rain',
				71 => 'Light snow',
				73 => 'Snow',
				75 => 'Heavy snow',
				77 => 'Snow grains',
				80 => 'Rain showers',
				81 => 'Heavy rain showers',
				82 => 'Violent rain showers',
				85 => 'Snow showers',
				86 => 'Heavy snow showers',
				95 => 'Thunderstorm',
				96 => 'Thunderstorm with hail',
				99 => 'Severe thunderstorm with hail',
			);
			return isset($map[$code]) ? (string) $map[$code] : 'Open-Meteo forecast';
		}
	}
}
