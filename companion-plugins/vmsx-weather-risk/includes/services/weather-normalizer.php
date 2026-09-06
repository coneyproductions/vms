<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_Weather_Normalizer')) {
	class VMSX_Weather_Risk_Weather_Normalizer {
		public static function success_payload(string $slug, string $name, array $hours, array $window, array $location, array $settings, array $extra = array()): array
		{
			$hours = self::filter_hours_by_window($hours, $window);
			if (empty($hours)) {
				return self::error_payload(
					$slug,
					$name,
					__('No forecast rows were available for the requested event window.', 'vmsx-weather-risk'),
					$location
				);
			}

			return array_merge(array(
				'provider_slug' => $slug,
				'provider_name' => $name,
				'status' => 'success',
				'fetched_at_utc' => time(),
				'location_used' => array(
					'label' => sanitize_text_field((string) ($location['label'] ?? '')),
					'latitude' => isset($location['latitude']) ? (float) $location['latitude'] : null,
					'longitude' => isset($location['longitude']) ? (float) $location['longitude'] : null,
				),
				'hours' => array_values($hours),
				'summary' => self::summarize_hours($hours, $settings),
				'error_message' => '',
			), $extra);
		}

		public static function error_payload(string $slug, string $name, string $message, array $location = array(), string $status = 'error'): array
		{
			return array(
				'provider_slug' => $slug,
				'provider_name' => $name,
				'status' => $status,
				'fetched_at_utc' => time(),
				'location_used' => array(
					'label' => sanitize_text_field((string) ($location['label'] ?? '')),
					'latitude' => isset($location['latitude']) ? (float) $location['latitude'] : null,
					'longitude' => isset($location['longitude']) ? (float) $location['longitude'] : null,
				),
				'hours' => array(),
				'summary' => array(
					'window_precip_probability_max' => 0,
					'window_precip_amount_total' => 0.0,
					'window_wind_max_mph' => 0.0,
					'lightning_any' => false,
					'severe_any' => false,
					'confidence_note' => '',
				),
				'error_message' => sanitize_text_field($message),
			);
		}

		private static function filter_hours_by_window(array $hours, array $window): array
		{
			$start = (int) ($window['window_start_utc'] ?? 0);
			$end = (int) ($window['window_end_utc'] ?? 0);
			$out = array();

			foreach ($hours as $hour) {
				if (!is_array($hour)) {
					continue;
				}
				$ts = isset($hour['timestamp_utc']) ? (int) $hour['timestamp_utc'] : 0;
				if ($ts <= 0) {
					continue;
				}
				if ($start > 0 && $ts < $start) {
					continue;
				}
				if ($end > 0 && $ts > $end) {
					continue;
				}

				$hour['timestamp_local'] = VMSX_Weather_Risk_Helpers::format_local_timestamp($ts);
				$out[] = $hour;
			}

			usort($out, static function (array $a, array $b): int {
				return ((int) ($a['timestamp_utc'] ?? 0)) <=> ((int) ($b['timestamp_utc'] ?? 0));
			});

			return $out;
		}

		private static function summarize_hours(array $hours, array $settings): array
		{
			$max_pop = 0;
			$total_precip = 0.0;
			$max_wind = 0.0;
			$lightning = false;
			$severe = false;
			$with_precip_amount = 0;

			foreach ($hours as $hour) {
				$max_pop = max($max_pop, (int) ($hour['precip_probability'] ?? 0));
				if (isset($hour['precip_amount']) && $hour['precip_amount'] !== null && $hour['precip_amount'] !== '') {
					$total_precip += (float) $hour['precip_amount'];
					$with_precip_amount++;
				}
				if (isset($hour['wind_mph']) && $hour['wind_mph'] !== null) {
					$max_wind = max($max_wind, (float) $hour['wind_mph']);
				}
				$lightning = $lightning || !empty($hour['lightning_risk_flag']);
				$severe = $severe || !empty($hour['severe_flag']);
			}

			$confidence = $with_precip_amount > 0
				? __('Provider supplied hourly precipitation amounts.', 'vmsx-weather-risk')
				: __('Provider supplied hourly forecast risk signals but not hourly precipitation totals.', 'vmsx-weather-risk');
			if (!empty($settings['provider_mode']) && (string) $settings['provider_mode'] === 'consensus') {
				$confidence = __('Consensus mode blends the enabled provider summaries.', 'vmsx-weather-risk');
			}

			return array(
				'window_precip_probability_max' => (int) round($max_pop),
				'window_precip_amount_total' => round($total_precip, 2),
				'window_wind_max_mph' => round($max_wind, 1),
				'lightning_any' => $lightning,
				'severe_any' => $severe,
				'confidence_note' => $confidence,
			);
		}
	}
}
