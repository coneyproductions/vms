<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_Advisory_Engine')) {
	class VMSX_Weather_Risk_Advisory_Engine {
		public static function get_snapshot(int $event_plan_id): ?array
		{
			return VMSX_Weather_Risk_Cache::get_snapshot($event_plan_id);
		}

		public static function refresh_snapshot(int $event_plan_id, bool $force = false, string $source = 'manual'): array
		{
			$settings = VMSX_Weather_Risk_Settings::get();
			$event = VMSX_Weather_Risk_Helpers::get_event_summary($event_plan_id);
			$window = VMSX_Weather_Risk_Decision_Window::build($event_plan_id, $settings);
			$location = VMSX_Weather_Risk_Venue_Location::resolve($event_plan_id, $settings);
			$ticket = VMSX_Weather_Risk_Ticket_Context::build($event_plan_id, $settings, $window);
			$dt = VMSX_Weather_Risk_DT_Enrichment::build($event_plan_id, $ticket);
			$providers = self::collect_provider_payloads($location, $window, $settings, $force);
			$provider_health = self::provider_health_summary($providers);
			$weather = self::combine_weather($providers, $settings);
			$weather_score = self::score_weather($weather, $providers, $settings, $location);
			$sales_score = self::score_sales($ticket, $dt, $window, $settings);
			$financial_score = self::score_financial($ticket, $weather_score, $settings);
			$final = self::build_final_advisory($weather_score, $sales_score, $financial_score, $settings, $weather);
			$reason_candidates = array_merge(
				(array) ($weather_score['reasons'] ?? array()),
				(array) ($sales_score['reasons'] ?? array()),
				(array) ($financial_score['reasons'] ?? array())
			);
			$reasons = array_values(array_unique(array_filter(array_map('sanitize_text_field', $reason_candidates))));
			$warnings = array_values(array_unique(array_filter(array_merge(
				(array) ($window['errors'] ?? array()),
				(array) ($location['warnings'] ?? array()),
				(array) ($ticket['warnings'] ?? array())
			))));
			$confidence_band = self::confidence_band($providers, $location, $ticket, $dt);
			$decision_checkpoint = self::decision_checkpoint($window, $final['label']);

			$snapshot = array(
				'version' => 1,
				'event_id' => $event_plan_id,
				'computed_at_utc' => time(),
				'computed_at_local' => VMSX_Weather_Risk_Helpers::format_local_timestamp(time()),
				'mode_used' => (string) ($weather['mode_used'] ?? ($settings['provider_mode'] ?? 'conservative')),
				'event' => $event,
				'window' => $window,
				'location' => $location,
				'advisory' => $final,
				'confidence_band' => $confidence_band,
				'weather_risk' => $weather_score,
				'sales_risk' => $sales_score,
				'financial_exposure' => $financial_score,
				'decision_checkpoint' => $decision_checkpoint,
				'reasons' => $reasons,
				'providers' => array_values($providers),
				'provider_health' => $provider_health,
				'sales_context' => $ticket,
				'dt_context' => $dt,
				'warnings' => $warnings,
				'refresh_source' => sanitize_key($source),
			);

			VMSX_Weather_Risk_Cache::set_snapshot($event_plan_id, $snapshot);
			VMSX_Weather_Risk_Logging::write(
				'Show Risk advisory recalculated.',
				array(
					'event_plan_id' => $event_plan_id,
					'source' => $source,
					'advisory' => (string) ($final['label'] ?? ''),
					'provider_responded' => (int) ($provider_health['responded'] ?? 0),
					'provider_expected' => (int) ($provider_health['expected'] ?? 0),
				),
				'info'
			);

			return $snapshot;
		}

		private static function collect_provider_payloads(array $location, array $window, array $settings, bool $force): array
		{
			$providers = array(
				new VMSX_Weather_Risk_Provider_OpenMeteo(),
				new VMSX_Weather_Risk_Provider_NOAA(),
				new VMSX_Weather_Risk_Provider_OpenWeather(),
				new VMSX_Weather_Risk_Provider_WeatherAPI(),
			);
			$ttl_minutes = max(15, (int) ($settings['cache_ttl_minutes'] ?? 60));
			$payloads = array();

			foreach ($providers as $provider) {
				$slug = $provider->slug();
				if (!$provider->is_enabled($settings)) {
					$payloads[$slug] = self::skipped_provider_payload($provider, $settings, $location);
					continue;
				}

				$cached = $force ? null : VMSX_Weather_Risk_Cache::get_provider($slug, $location, $window);
				if (is_array($cached)) {
					$cached['cache_hit'] = true;
					$payloads[$slug] = $cached;
					continue;
				}

				$payload = $provider->fetch($location, $window, $settings);
				$payload['cache_hit'] = false;
				VMSX_Weather_Risk_Cache::set_provider($slug, $location, $window, $payload, $ttl_minutes);
				$payloads[$slug] = $payload;

				$level = (($payload['status'] ?? '') === 'success') ? 'info' : 'warning';
				$message = (($payload['status'] ?? '') === 'success')
					? sprintf('%s weather fetch succeeded.', $provider->name())
					: sprintf('%s weather fetch did not succeed.', $provider->name());
				VMSX_Weather_Risk_Logging::write(
					$message,
					array(
						'provider' => $slug,
						'event_plan_id' => (int) ($location['event_plan_id'] ?? 0),
						'status' => (string) ($payload['status'] ?? ''),
						'error' => (string) ($payload['error_message'] ?? ''),
					),
					$level
				);
			}

			return $payloads;
		}

		private static function provider_health_summary(array $providers): array
		{
			$expected = 0;
			$responded = 0;
			$errors = 0;
			$skipped = 0;
			foreach ($providers as $provider) {
				if (!is_array($provider)) {
					continue;
				}
				$status = (string) ($provider['status'] ?? '');
				if ($status === 'skipped') {
					$skipped++;
					continue;
				}
				$expected++;
				if ($status === 'success') {
					$responded++;
				} else {
					$errors++;
				}
			}

			$label = sprintf(
				/* translators: 1: providers responded 2: enabled providers */
				_n('%1$d/%2$d active provider responded', '%1$d/%2$d active providers responded', max(1, $expected), 'vmsx-weather-risk'),
				$responded,
				max(1, $expected)
			);
			if ($skipped > 0) {
				$label .= ' ' . sprintf(
					/* translators: %d: skipped providers */
					_n('(%d skipped)', '(%d skipped)', $skipped, 'vmsx-weather-risk'),
					$skipped
				);
			}

			return array(
				'expected' => $expected,
				'responded' => $responded,
				'errors' => $errors,
				'skipped' => $skipped,
				'label' => $label,
			);
		}

		private static function skipped_provider_payload(VMSX_Weather_Risk_Provider_Interface $provider, array $settings, array $location): array
		{
			$slug = $provider->slug();
			$message = __('Provider is disabled in Show Risk Settings.', 'vmsx-weather-risk');

			switch ($slug) {
				case 'openmeteo':
					$message = __('Open-Meteo is disabled in Show Risk Settings.', 'vmsx-weather-risk');
					break;
				case 'openweather':
					if (!empty($settings['provider_openweather_enabled']) && empty($settings['provider_openweather_api_key'])) {
						$message = __('OpenWeather is enabled, but no API key is saved yet.', 'vmsx-weather-risk');
					} else {
						$message = __('OpenWeather is disabled in Show Risk Settings.', 'vmsx-weather-risk');
					}
					break;
				case 'weatherapi':
					if (!empty($settings['provider_weatherapi_enabled']) && empty($settings['provider_weatherapi_api_key'])) {
						$message = __('WeatherAPI is enabled, but no API key is saved yet.', 'vmsx-weather-risk');
					} else {
						$message = __('WeatherAPI is disabled in Show Risk Settings.', 'vmsx-weather-risk');
					}
					break;
				case 'noaa':
					$message = __('NOAA is disabled in Show Risk Settings.', 'vmsx-weather-risk');
					break;
			}

			return VMSX_Weather_Risk_Weather_Normalizer::error_payload(
				$slug,
				$provider->name(),
				$message,
				$location,
				'skipped'
			);
		}

		private static function combine_weather(array $providers, array $settings): array
		{
			$mode = sanitize_key((string) ($settings['provider_mode'] ?? 'conservative'));
			$preferred = sanitize_key((string) ($settings['preferred_provider'] ?? 'noaa'));
			$success = array();
			foreach ($providers as $provider) {
				if (is_array($provider) && ($provider['status'] ?? '') === 'success') {
					$success[] = $provider;
				}
			}

			if (empty($success)) {
				return array(
					'mode_used' => $mode,
					'metrics' => array(
						'max_precip_probability' => 0,
						'total_precip_inches' => 0.0,
						'max_wind_mph' => 0.0,
						'lightning_any' => false,
						'severe_any' => false,
					),
					'notes' => array(__('No weather providers responded for this event window.', 'vmsx-weather-risk')),
				);
			}

			$used = $success;
			if ($mode === 'preferred') {
				$preferred_match = null;
				foreach ($success as $provider) {
					if (($provider['provider_slug'] ?? '') === $preferred) {
						$preferred_match = $provider;
						break;
					}
				}
				if ($preferred_match) {
					$used = array($preferred_match);
				} else {
					$used = array($success[0]);
				}
			}

			$pop_values = array();
			$precip_values = array();
			$wind_values = array();
			$lightning_any = false;
			$severe_any = false;
			$source_names = array();
			foreach ($used as $provider) {
				$summary = isset($provider['summary']) && is_array($provider['summary']) ? $provider['summary'] : array();
				$pop_values[] = (int) ($summary['window_precip_probability_max'] ?? 0);
				$precip_values[] = (float) ($summary['window_precip_amount_total'] ?? 0.0);
				$wind_values[] = (float) ($summary['window_wind_max_mph'] ?? 0.0);
				$lightning_any = $lightning_any || !empty($summary['lightning_any']);
				$severe_any = $severe_any || !empty($summary['severe_any']);
				$source_names[] = (string) ($provider['provider_name'] ?? '');
			}

			if ($mode === 'consensus' && count($used) > 1) {
				sort($pop_values);
				sort($precip_values);
				sort($wind_values);
				$middle = (int) floor(count($used) / 2);
				$max_precip_probability = (int) $pop_values[$middle];
				$total_precip_inches = (float) $precip_values[$middle];
				$max_wind_mph = (float) $wind_values[$middle];
			} else {
				$max_precip_probability = max($pop_values);
				$total_precip_inches = max($precip_values);
				$max_wind_mph = max($wind_values);
			}

			$notes = array();
			if (count($success) === 1) {
				$notes[] = __('Only one provider responded, so the weather read is directionally useful but lower confidence.', 'vmsx-weather-risk');
			}
			if ($mode === 'preferred' && count($used) === 1 && ($used[0]['provider_slug'] ?? '') !== $preferred) {
				$notes[] = __('Preferred provider did not respond, so the advisory fell back to the next available provider.', 'vmsx-weather-risk');
			}

			return array(
				'mode_used' => $mode,
				'metrics' => array(
					'max_precip_probability' => (int) round($max_precip_probability),
					'total_precip_inches' => round((float) $total_precip_inches, 2),
					'max_wind_mph' => round((float) $max_wind_mph, 1),
					'lightning_any' => $lightning_any,
					'severe_any' => $severe_any,
				),
				'source_names' => array_values(array_unique(array_filter($source_names))),
				'notes' => $notes,
			);
		}

		private static function score_weather(array $weather, array $providers, array $settings, array $location): array
		{
			$metrics = isset($weather['metrics']) && is_array($weather['metrics']) ? $weather['metrics'] : array();
			$score = 0;
			$reasons = array();
			$success_count = 0;
			foreach ($providers as $provider) {
				if (is_array($provider) && ($provider['status'] ?? '') === 'success') {
					$success_count++;
				}
			}

			if ($success_count === 0) {
				$score = 30;
				$reasons[] = __('No provider returned a usable forecast, so the advisory cannot confidently rate weather exposure yet.', 'vmsx-weather-risk');
			}

			$max_pop = (int) ($metrics['max_precip_probability'] ?? 0);
			if ($max_pop >= 80) {
				$score += 40;
				$reasons[] = sprintf(__('Peak precipitation probability in the event window is %d%%.', 'vmsx-weather-risk'), $max_pop);
			} elseif ($max_pop >= 60) {
				$score += 28;
				$reasons[] = sprintf(__('Event-window precipitation probability is elevated at %d%%.', 'vmsx-weather-risk'), $max_pop);
			} elseif ($max_pop >= 40) {
				$score += 16;
				$reasons[] = sprintf(__('Event-window precipitation probability is in watch territory at %d%%.', 'vmsx-weather-risk'), $max_pop);
			}

			$precip_inches = (float) ($metrics['total_precip_inches'] ?? 0.0);
			$heavy_rain = (float) ($settings['heavy_rain_threshold_inches'] ?? 0.25);
			if ($precip_inches >= $heavy_rain && $heavy_rain > 0) {
				$score += 18;
				$reasons[] = sprintf(__('Projected precipitation total across the event window is %.2f".', 'vmsx-weather-risk'), $precip_inches);
			} elseif ($precip_inches >= ($heavy_rain / 2) && $precip_inches > 0) {
				$score += 8;
			}

			$wind_threshold = max(5, (int) ($settings['wind_threshold_mph'] ?? 25));
			$max_wind = (float) ($metrics['max_wind_mph'] ?? 0.0);
			if ($max_wind >= $wind_threshold) {
				$score += 15 + min(15, (int) round($max_wind - $wind_threshold));
				$reasons[] = sprintf(__('Peak forecast wind/gust signal reaches %.1f mph.', 'vmsx-weather-risk'), $max_wind);
			}

			if (!empty($metrics['lightning_any'])) {
				$score = max($score, !empty($settings['lightning_hard_stop']) ? 85 : 70);
				$reasons[] = __('At least one provider flags thunder/lightning during the event window.', 'vmsx-weather-risk');
			}
			if (!empty($metrics['severe_any'])) {
				$score = max($score, 70);
				$reasons[] = __('At least one provider flags severe-storm language during the event window.', 'vmsx-weather-risk');
			}
			if (!isset($location['latitude']) || !is_numeric($location['latitude']) || !isset($location['longitude']) || !is_numeric($location['longitude'])) {
				$reasons[] = __('Precise venue coordinates are missing, so weather scoring is limited.', 'vmsx-weather-risk');
			}
			foreach ((array) ($weather['notes'] ?? array()) as $note) {
				$reasons[] = sanitize_text_field((string) $note);
			}

			$score = max(0, min(100, (int) round($score)));

			return array(
				'score' => $score,
				'band' => VMSX_Weather_Risk_Helpers::advisory_band_from_score($score),
				'metrics' => $metrics,
				'reasons' => array_values(array_unique(array_filter($reasons))),
			);
		}

		private static function score_sales(array $ticket, array $dt, array $window, array $settings): array
		{
			unset($settings);
			$score = 0;
			$reasons = array();

			if (!empty($ticket['is_missing'])) {
				$score += 35;
				$reasons[] = __('Ticket stats are missing, so the sales side of the advisory is lower confidence.', 'vmsx-weather-risk');
			} else {
				$ratios = array();
				if ($ticket['qty_ratio'] !== null) {
					$ratios[] = (float) $ticket['qty_ratio'];
				}
				if ($ticket['gross_ratio'] !== null) {
					$ratios[] = (float) $ticket['gross_ratio'];
				}
				if (!empty($ratios)) {
					$ratio = min($ratios);
					if ($ratio >= 1) {
						$reasons[] = __('Current ticket snapshot is meeting the configured viability floor.', 'vmsx-weather-risk');
					} elseif ($ratio >= 0.75) {
						$score += 15;
						$reasons[] = __('Current ticket snapshot is slightly below the configured viability floor.', 'vmsx-weather-risk');
					} elseif ($ratio >= 0.50) {
						$score += 30;
						$reasons[] = __('Current ticket snapshot is materially below the configured viability floor.', 'vmsx-weather-risk');
					} elseif ($ratio >= 0.25) {
						$score += 50;
						$reasons[] = __('Current ticket snapshot is far below the configured viability floor.', 'vmsx-weather-risk');
					} else {
						$score += 70;
						$reasons[] = __('Current ticket snapshot is severely below the configured viability floor.', 'vmsx-weather-risk');
					}
				}
			}

			if (!empty($ticket['is_stale'])) {
				$score += 10;
			}

			if (!empty($dt['available']) && !empty($dt['used'])) {
				if (($dt['pace_band'] ?? '') === 'behind') {
					$score += 15;
					$reasons[] = __('DT pace comparison shows this event is behind comparable checkpoints.', 'vmsx-weather-risk');
				} elseif (($dt['pace_band'] ?? '') === 'ahead') {
					$score = max(0, $score - 10);
					$reasons[] = __('DT pace comparison shows this event is ahead of comparable checkpoints.', 'vmsx-weather-risk');
				} elseif (($dt['pace_band'] ?? '') === 'on_pace') {
					$reasons[] = __('DT pace comparison shows this event is roughly on pace.', 'vmsx-weather-risk');
				}
			}

			if (($window['days_until_event'] ?? 0) <= 2 && (($ticket['qty_ratio'] ?? 1) < 0.75 || ($ticket['gross_ratio'] ?? 1) < 0.75)) {
				$score += 10;
				$reasons[] = __('The event is close and sales have limited time left to recover.', 'vmsx-weather-risk');
			}

			$score = max(0, min(100, (int) round($score)));
			return array(
				'score' => $score,
				'band' => VMSX_Weather_Risk_Helpers::advisory_band_from_score($score),
				'reasons' => array_values(array_unique(array_filter($reasons))),
			);
		}

		private static function score_financial(array $ticket, array $weather_score, array $settings): array
		{
			unset($settings);
			$score = 0;
			$reasons = array();
			$exposure = (int) ($ticket['known_exposure_cents'] ?? 0);
			$gross = (int) ($ticket['gross_cents'] ?? 0);

			if ($exposure > 0 && $gross <= 0) {
				$score += 35;
				$reasons[] = __('Known labor/vendor exposure exists before ticket revenue has caught up.', 'vmsx-weather-risk');
			} elseif ($exposure > 0 && $gross > 0) {
				$ratio = $gross > 0 ? ($exposure / $gross) : 0;
				if ($ratio >= 1.25) {
					$score += 45;
					$reasons[] = __('Known labor/vendor exposure currently exceeds ticket revenue.', 'vmsx-weather-risk');
				} elseif ($ratio >= 1.0) {
					$score += 35;
					$reasons[] = __('Known labor/vendor exposure is roughly equal to ticket revenue.', 'vmsx-weather-risk');
				} elseif ($ratio >= 0.75) {
					$score += 20;
					$reasons[] = __('Known labor/vendor exposure is large relative to ticket revenue.', 'vmsx-weather-risk');
				} elseif ($ratio >= 0.5) {
					$score += 10;
				}
			}

			if ($exposure === 0) {
				$reasons[] = __('No meaningful labor/vendor exposure was loaded into the financial view yet.', 'vmsx-weather-risk');
			}

			if (($weather_score['score'] ?? 0) >= 65 && $exposure > 0) {
				$score += 15;
				$reasons[] = __('Elevated weather risk is landing on an event with known financial exposure.', 'vmsx-weather-risk');
			}

			$score = max(0, min(100, (int) round($score)));
			return array(
				'score' => $score,
				'band' => VMSX_Weather_Risk_Helpers::advisory_band_from_score($score),
				'reasons' => array_values(array_unique(array_filter($reasons))),
			);
		}

		private static function build_final_advisory(array $weather_score, array $sales_score, array $financial_score, array $settings, array $weather): array
		{
			$weather_weight = max(0, (int) ($settings['weather_weight'] ?? 50));
			$sales_weight = max(0, (int) ($settings['sales_weight'] ?? 35));
			$financial_weight = max(0, (int) ($settings['financial_weight'] ?? 15));
			$total_weight = max(1, $weather_weight + $sales_weight + $financial_weight);
			$score = (
				((int) ($weather_score['score'] ?? 0) * $weather_weight) +
				((int) ($sales_score['score'] ?? 0) * $sales_weight) +
				((int) ($financial_score['score'] ?? 0) * $financial_weight)
			) / $total_weight;
			$score = (int) round($score);

			if (!empty($weather['metrics']['lightning_any']) && !empty($settings['lightning_hard_stop'])) {
				$score = max($score, 85);
			}

			$style = sanitize_key((string) ($settings['decision_style'] ?? 'conservative'));
			$monitor_threshold = 35;
			$high_threshold = 60;
			$cancel_threshold = 80;
			if ($style === 'balanced') {
				$monitor_threshold = 40;
				$high_threshold = 65;
				$cancel_threshold = 85;
			} elseif ($style === 'aggressive') {
				$monitor_threshold = 50;
				$high_threshold = 75;
				$cancel_threshold = 92;
			}

			if ($score >= $cancel_threshold) {
				$label = 'Consider Cancellation';
			} elseif ($score >= $high_threshold) {
				$label = 'High Risk';
			} elseif ($score >= $monitor_threshold) {
				$label = 'Monitor Closely';
			} else {
				$label = 'Proceed';
			}

			return array(
				'label' => $label,
				'score' => max(0, min(100, $score)),
				'band' => VMSX_Weather_Risk_Helpers::advisory_band_from_score($score),
			);
		}

		private static function confidence_band(array $providers, array $location, array $ticket, array $dt): string
		{
			$score = 0;
			$success = 0;
			foreach ($providers as $provider) {
				if (is_array($provider) && ($provider['status'] ?? '') === 'success') {
					$success++;
				}
			}
			if ($success >= 2) {
				$score += 2;
			} elseif ($success === 1) {
				$score += 1;
			}
			if (!empty($location['latitude']) && !empty($location['longitude'])) {
				$score += 1;
			}
			if (empty($ticket['is_missing'])) {
				$score += 1;
			}
			if (!empty($dt['used'])) {
				$score += 1;
			}

			if ($score >= 4) {
				return 'High';
			}
			if ($score >= 2) {
				return 'Medium';
			}
			return 'Low';
		}

		private static function decision_checkpoint(array $window, string $label): string
		{
			$days_out = (int) ($window['days_until_event'] ?? 0);
			if ($label === 'Consider Cancellation') {
				if ($days_out <= 0) {
					return __('Review now and recheck in 1 hour if the event is still undecided.', 'vmsx-weather-risk');
				}
				if ($days_out <= 2) {
					return __('Recheck every 4 hours until showtime.', 'vmsx-weather-risk');
				}
				return __('Recheck at 10:00 AM and 4:00 PM as the event approaches.', 'vmsx-weather-risk');
			}
			if ($label === 'High Risk') {
				if ($days_out <= 2) {
					return __('Recheck in 4 hours.', 'vmsx-weather-risk');
				}
				return __('Recheck tomorrow morning.', 'vmsx-weather-risk');
			}
			if ($label === 'Monitor Closely') {
				if ($days_out <= 3) {
					return __('Recheck tomorrow morning.', 'vmsx-weather-risk');
				}
				return __('Recheck 72 hours before showtime.', 'vmsx-weather-risk');
			}
			if ($days_out <= 2) {
				return __('Recheck tomorrow morning.', 'vmsx-weather-risk');
			}
			return __('Recheck 48 to 72 hours before showtime.', 'vmsx-weather-risk');
		}
	}
}
