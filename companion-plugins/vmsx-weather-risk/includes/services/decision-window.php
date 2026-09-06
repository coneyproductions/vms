<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_Decision_Window')) {
	class VMSX_Weather_Risk_Decision_Window {
		public static function build(int $event_plan_id, array $settings): array
		{
			$date_key = VMSX_Weather_Risk_Helpers::event_meta_key('date', '_vms_event_date');
			$date_ymd = trim((string) get_post_meta($event_plan_id, $date_key, true));
			$start_raw = trim((string) get_post_meta($event_plan_id, '_vms_start_time', true));
			$end_raw = trim((string) get_post_meta($event_plan_id, '_vms_end_time', true));
			$pre_buffer = max(0, (int) ($settings['pre_event_buffer_minutes'] ?? 120));
			$post_buffer = max(0, (int) ($settings['post_event_buffer_minutes'] ?? 60));
			$duration_minutes = 120;

			$out = array(
				'ok' => false,
				'event_date' => $date_ymd,
				'start_raw' => $start_raw,
				'end_raw' => $end_raw,
				'is_day_only' => false,
				'errors' => array(),
				'event_start_local' => '',
				'event_end_local' => '',
				'window_start_local' => '',
				'window_end_local' => '',
				'event_start_utc' => 0,
				'event_end_utc' => 0,
				'window_start_utc' => 0,
				'window_end_utc' => 0,
				'days_until_event' => 0,
				'label' => '',
			);

			if ($date_ymd === '') {
				$out['errors'][] = __('Event date is missing.', 'vmsx-weather-risk');
				return $out;
			}

			$start_dt = VMSX_Weather_Risk_Helpers::parse_local_datetime($date_ymd, $start_raw);
			$end_dt = VMSX_Weather_Risk_Helpers::parse_local_datetime($date_ymd, $end_raw);
			if (!$start_dt instanceof DateTimeImmutable) {
				$out['is_day_only'] = true;
				$start_dt = VMSX_Weather_Risk_Helpers::parse_local_datetime($date_ymd, '00:00:00');
				$end_dt = VMSX_Weather_Risk_Helpers::parse_local_datetime($date_ymd, '23:59:59');
				$out['errors'][] = __('Start time is missing, so Show Risk Advisor is using a day-only weather summary.', 'vmsx-weather-risk');
			}

			if (!$end_dt instanceof DateTimeImmutable || $end_dt <= $start_dt) {
				$end_dt = $start_dt->modify('+' . $duration_minutes . ' minutes');
				$out['errors'][] = __('End time is missing or invalid, so Show Risk Advisor assumed a 2-hour event duration.', 'vmsx-weather-risk');
			}

			$window_start = $start_dt->modify('-' . $pre_buffer . ' minutes');
			$window_end = $end_dt->modify('+' . $post_buffer . ' minutes');
			$now = VMSX_Weather_Risk_Helpers::now_local();
			$days_until_event = (int) floor(($start_dt->getTimestamp() - $now->getTimestamp()) / DAY_IN_SECONDS);

			$out['ok'] = true;
			$out['event_start_local'] = $start_dt->format(DateTimeInterface::ATOM);
			$out['event_end_local'] = $end_dt->format(DateTimeInterface::ATOM);
			$out['window_start_local'] = $window_start->format(DateTimeInterface::ATOM);
			$out['window_end_local'] = $window_end->format(DateTimeInterface::ATOM);
			$out['event_start_utc'] = $start_dt->getTimestamp();
			$out['event_end_utc'] = $end_dt->getTimestamp();
			$out['window_start_utc'] = $window_start->getTimestamp();
			$out['window_end_utc'] = $window_end->getTimestamp();
			$out['days_until_event'] = $days_until_event;

			$date_label = VMSX_Weather_Risk_Helpers::format_local_timestamp($start_dt->getTimestamp(), (string) get_option('date_format', 'Y-m-d'));
			if (!empty($out['is_day_only'])) {
				$out['label'] = trim($date_label . ' · ' . __('Day-only weather summary', 'vmsx-weather-risk'));
			} else {
				$time_format = (string) get_option('time_format', 'g:i a');
				$out['label'] = trim(
					$date_label . ' · ' .
					$start_dt->format($time_format) . ' - ' . $end_dt->format($time_format) .
					' (' . $window_start->format($time_format) . ' - ' . $window_end->format($time_format) . ' window)'
				);
			}

			return $out;
		}
	}
}
