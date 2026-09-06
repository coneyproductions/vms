<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_Helpers')) {
	class VMSX_Weather_Risk_Helpers {
		public static function event_meta_key(string $field, string $fallback): string
		{
			$meta_key = VMSX_Weather_Risk_Compatibility::core_function('vms_meta_key');
			if ($meta_key !== '') {
				$key = (string) $meta_key('event_plan', $field);
				if ($key !== '') {
					return $key;
				}
			}

			return $fallback;
		}

		public static function venue_meta_key(string $field, string $fallback): string
		{
			$meta_key = VMSX_Weather_Risk_Compatibility::core_function('vms_meta_key');
			if ($meta_key !== '') {
				$key = (string) $meta_key('venue', $field);
				if ($key !== '') {
					return $key;
				}
			}

			return $fallback;
		}

		public static function timezone(): DateTimeZone
		{
			return function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
		}

		public static function now_local(): DateTimeImmutable
		{
			return new DateTimeImmutable('now', self::timezone());
		}

		public static function parse_local_datetime(string $date_ymd, string $time_value = ''): ?DateTimeImmutable
		{
			$date_ymd = trim($date_ymd);
			$time_value = trim($time_value);
			if ($date_ymd === '') {
				return null;
			}

			if ($time_value === '') {
				$time_value = '00:00:00';
			}

			$time_value = preg_replace('/\s+/', ' ', $time_value);
			$formats = array(
				'Y-m-d H:i:s',
				'Y-m-d H:i',
				'Y-m-d g:i a',
				'Y-m-d g:ia',
			);

			foreach ($formats as $format) {
				$dt = DateTimeImmutable::createFromFormat($format, $date_ymd . ' ' . $time_value, self::timezone());
				if ($dt instanceof DateTimeImmutable) {
					return $dt;
				}
			}

			$ts = strtotime($date_ymd . ' ' . $time_value);
			if ($ts === false) {
				return null;
			}

			return (new DateTimeImmutable('@' . $ts))->setTimezone(self::timezone());
		}

		public static function format_local_timestamp(int $timestamp, string $format = ''): string
		{
			if ($timestamp <= 0) {
				return '';
			}

			if ($format === '') {
				$date_format = (string) get_option('date_format', 'Y-m-d');
				$time_format = (string) get_option('time_format', 'g:i a');
				$format = $date_format . ' ' . $time_format;
			}

			if (function_exists('wp_date')) {
				return (string) wp_date($format, $timestamp, self::timezone());
			}

			return gmdate('Y-m-d H:i', $timestamp);
		}

		public static function format_money_cents(?int $cents): string
		{
			if ($cents === null) {
				return __('N/A', 'vmsx-weather-risk');
			}

			$amount = $cents / 100;
			return '$' . number_format_i18n($amount, 2);
		}

		public static function format_money_float(?float $amount): string
		{
			if ($amount === null) {
				return __('N/A', 'vmsx-weather-risk');
			}

			return '$' . number_format_i18n($amount, 2);
		}

		public static function clamp_int($value, int $min, int $max): int
		{
			$value = (int) $value;
			if ($value < $min) {
				return $min;
			}
			if ($value > $max) {
				return $max;
			}
			return $value;
		}

		public static function clamp_float($value, float $min, float $max): float
		{
			$value = (float) $value;
			if ($value < $min) {
				return $min;
			}
			if ($value > $max) {
				return $max;
			}
			return $value;
		}

		public static function yes_no($value): int
		{
			return !empty($value) ? 1 : 0;
		}

		public static function sanitize_float_string($value, int $precision = 2): string
		{
			$raw = trim((string) $value);
			if ($raw === '') {
				return '';
			}

			$raw = str_replace(array('$', ','), '', $raw);
			if (!is_numeric($raw)) {
				return '';
			}

			return number_format((float) $raw, $precision, '.', '');
		}

		public static function sanitize_money_to_cents($value): int
		{
			$raw = self::sanitize_float_string($value, 2);
			if ($raw === '') {
				return 0;
			}

			return (int) round(((float) $raw) * 100);
		}

		public static function mask_secret(string $value): string
		{
			$value = trim($value);
			if ($value === '') {
				return '';
			}

			$len = strlen($value);
			if ($len <= 4) {
				return str_repeat('*', $len);
			}

			return str_repeat('*', max(4, $len - 4)) . substr($value, -4);
		}

		private static function contains_mojibake_markers(string $value): bool
		{
			if ($value === '') {
				return false;
			}

			return (bool) preg_match('/(?:Ã.|Â.|â€|â€™|â€œ|â€|â€“|â€”|â€¦|â€¢|â„¢|â‚¬|ï»¿|�)/u', $value);
		}

		private static function mojibake_score(string $value): int
		{
			if ($value === '') {
				return 0;
			}

			$score = 0;
			$markers = array('Ã', 'Â', 'â€', 'â€™', 'â€œ', 'â€', 'â€“', 'â€”', 'â€¦', 'â€¢', 'â„¢', 'â‚¬', 'ï»¿', '�');
			foreach ($markers as $marker) {
				$score += substr_count($value, $marker);
			}
			return $score;
		}

		private static function decode_html_entities_deep(string $value): string
		{
			for ($i = 0; $i < 3; $i++) {
				$decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
				if (!is_string($decoded) || $decoded === $value) {
					break;
				}
				$value = $decoded;
			}

			return $value;
		}

		private static function is_valid_utf8_string(string $value): bool
		{
			return $value === '' || preg_match('//u', $value) === 1;
		}

		private static function maybe_repair_mojibake(string $value): string
		{
			if ($value === '' || !self::contains_mojibake_markers($value)) {
				return $value;
			}

			$best = $value;
			$best_score = self::mojibake_score($value);

			for ($pass = 0; $pass < 2; $pass++) {
				$improved = false;
				foreach (array('Windows-1252', 'ISO-8859-1') as $target_encoding) {
					$candidate = @iconv('UTF-8', $target_encoding . '//IGNORE', $best);
					if (!is_string($candidate) || $candidate === '' || !self::is_valid_utf8_string($candidate)) {
						continue;
					}

					$candidate = self::decode_html_entities_deep($candidate);
					$score = self::mojibake_score($candidate);
					if ($score < $best_score) {
						$best = $candidate;
						$best_score = $score;
						$improved = true;
					}
				}

				if (!$improved || $best_score === 0) {
					break;
				}
			}

			return $best;
		}

		public static function clean_display_text(string $value): string
		{
			$value = trim(wp_strip_all_tags($value));
			if ($value === '') {
				return '';
			}

			if (function_exists('wp_check_invalid_utf8')) {
				$checked = wp_check_invalid_utf8($value, true);
				if (is_string($checked) && $checked !== '') {
					$value = $checked;
				}
			}

			$value = self::decode_html_entities_deep($value);
			$value = self::maybe_repair_mojibake($value);
			$value = self::decode_html_entities_deep($value);
			$value = str_replace(array("Â ", "â", "ï»¿"), ' ', $value);

			$replacements = array(
				'Ã¢â‚¬â„¢' => "'",
				'Ã¢â‚¬Ëœ' => "'",
				'Ã¢â‚¬Å“' => '"',
				'Ã¢â‚¬' => '"',
				'Ã¢â‚¬â€œ' => ' - ',
				'Ã¢â‚¬â€' => ' - ',
				'Ã¢â‚¬Â¢' => ' · ',
				'Ã¢â‚¬Â¦' => '…',
				'â€“' => ' - ',
				'â€”' => ' - ',
				'â€"' => ' - ',
				'â€' => ' - ',
				'â€˜' => "'",
				'â€™' => "'",
				'â€œ' => '"',
				'â€' => '"',
				'â€¢' => ' · ',
				'â€¦' => '…',
				'Â·' => ' · ',
				'–' => ' - ',
				'—' => ' - ',
				'‘' => "'",
				'’' => "'",
				'“' => '"',
				'”' => '"',
				'Ã—' => '×',
				'Ã©' => 'é',
				'Ã¨' => 'è',
				'Ã¢' => 'â',
				'Â' => '',
			);
			$value = strtr($value, $replacements);

			$value = preg_replace('/(?:â€(?:["”]|\x9d)?|â€\x9d|â€\x9c|â€\x94|â€\x93)/u', ' - ', (string) $value);
			$value = preg_replace('/\s*Â·\s*/u', ' · ', (string) $value);
			$value = preg_replace('/\s*[·•]+\s*/u', ' · ', (string) $value);
			$value = preg_replace('/\s*[–—-]+\s*/u', ' - ', (string) $value);
			$value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', (string) $value);
			$value = preg_replace('/\s{2,}/', ' ', (string) $value);
			$value = trim((string) $value, " 	

 -·");
			return sanitize_text_field((string) $value);
		}

		public static function format_log_context(array $context): string
		{
			if (empty($context)) {
				return '';
			}

			$pairs = array();
			$order = array(
				'address_attempt',
				'address_source',
				'address_variants',
				'provider',
				'providers_attempted',
				'status',
				'error',
				'http_code',
				'geocode_attempts_summary',
				'trigger',
				'venue_id',
				'tec_event_id',
				'tec_venue_id',
				'latitude',
				'longitude',
			);

			foreach ($order as $key) {
				if (!array_key_exists($key, $context)) {
					continue;
				}
				$value = $context[$key];
				if (is_array($value)) {
					$value = implode(', ', array_map('strval', $value));
				}
				$value = trim((string) $value);
				if ($value === '') {
					continue;
				}
				$label = ucwords(str_replace('_', ' ', $key));
				$pairs[] = $label . ': ' . self::clean_display_text($value);
			}

			if (empty($pairs)) {
				return '';
			}

			return implode(' | ', $pairs);
		}

		public static function advisory_band_from_score(int $score): string
		{
			if ($score >= 85) {
				return 'Critical';
			}
			if ($score >= 65) {
				return 'High';
			}
			if ($score >= 40) {
				return 'Watch';
			}
			return 'Low';
		}

		public static function band_css_class(string $label): string
		{
			$normalized = sanitize_html_class(strtolower(str_replace(' ', '-', $label)));
			if ($normalized === '') {
				$normalized = 'default';
			}
			return 'vmsx-weather-risk__pill--' . $normalized;
		}

		public static function contains_keywords(string $haystack, array $keywords): bool
		{
			$haystack = strtolower(trim($haystack));
			if ($haystack === '') {
				return false;
			}

			foreach ($keywords as $keyword) {
				$keyword = strtolower(trim((string) $keyword));
				if ($keyword !== '' && strpos($haystack, $keyword) !== false) {
					return true;
				}
			}

			return false;
		}

		public static function parse_wind_mph($value): ?float
		{
			if ($value === null || $value === '') {
				return null;
			}

			if (is_numeric($value)) {
				return (float) $value;
			}

			$value = strtolower((string) $value);
			if (!preg_match_all('/(\d+(?:\.\d+)?)/', $value, $matches) || empty($matches[1])) {
				return null;
			}

			$numbers = array_map('floatval', $matches[1]);
			return (float) max($numbers);
		}

		public static function remote_get_json_detailed(string $url, array $args = array()): array
		{
			$defaults = array(
				'timeout' => 20,
				'headers' => array(),
			);
			$args = wp_parse_args($args, $defaults);

			$response = wp_remote_get($url, $args);
			if (is_wp_error($response)) {
				return array(
					'ok' => false,
					'error' => $response->get_error_message(),
					'http_code' => 0,
					'url' => $url,
				);
			}

			$code = (int) wp_remote_retrieve_response_code($response);
			$body = (string) wp_remote_retrieve_body($response);
			if ($code < 200 || $code >= 300) {
				return array(
					'ok' => false,
					'error' => sprintf('HTTP %d', $code),
					'http_code' => $code,
					'body_snippet' => sanitize_text_field(mb_substr($body, 0, 200)),
					'url' => $url,
				);
			}

			$data = json_decode($body, true);
			if (!is_array($data)) {
				return array(
					'ok' => false,
					'error' => 'invalid json',
					'http_code' => $code,
					'body_snippet' => sanitize_text_field(mb_substr($body, 0, 200)),
					'url' => $url,
				);
			}

			return array(
				'ok' => true,
				'http_code' => $code,
				'data' => $data,
				'url' => $url,
			);
		}

		public static function remote_get_json(string $url, array $args = array())
		{
			$defaults = array(
				'timeout' => 15,
				'headers' => array(),
			);
			$args = wp_parse_args($args, $defaults);

			$response = wp_remote_get($url, $args);
			if (is_wp_error($response)) {
				return $response;
			}

			$code = (int) wp_remote_retrieve_response_code($response);
			$body = wp_remote_retrieve_body($response);
			if ($code < 200 || $code >= 300) {
				return new WP_Error(
					'vmsx_weather_risk_http_error',
					sprintf('HTTP %d while requesting weather data.', $code),
					array(
						'code' => $code,
						'body' => is_string($body) ? mb_substr($body, 0, 400) : '',
						'url' => $url,
					)
				);
			}

			$data = json_decode((string) $body, true);
			if (!is_array($data)) {
				return new WP_Error(
					'vmsx_weather_risk_invalid_json',
					'Weather provider returned invalid JSON.',
					array('url' => $url)
				);
			}

			return $data;
		}

		public static function get_event_summary(int $event_plan_id): array
		{
			$post = get_post($event_plan_id);
			if (!$post || $post->post_type !== 'vms_event_plan') {
				return array();
			}

			$date_key = self::event_meta_key('date', '_vms_event_date');
			$venue_key = self::event_meta_key('venue_id', '_vms_venue_id');
			$date = trim((string) get_post_meta($event_plan_id, $date_key, true));
			$start = trim((string) get_post_meta($event_plan_id, '_vms_start_time', true));
			$end = trim((string) get_post_meta($event_plan_id, '_vms_end_time', true));
			$venue_id = (int) get_post_meta($event_plan_id, $venue_key, true);
			$venue_name = $venue_id > 0 ? self::clean_display_text((string) get_the_title($venue_id)) : '';
			$start_dt = self::parse_local_datetime($date, $start);
			$end_dt = self::parse_local_datetime($date, $end);

			$time_label = '';
			if ($start_dt instanceof DateTimeImmutable) {
				$time_label = $start_dt->format((string) get_option('time_format', 'g:i a'));
				if ($end_dt instanceof DateTimeImmutable) {
					$time_label .= ' - ' . $end_dt->format((string) get_option('time_format', 'g:i a'));
				}
			}

			return array(
				'id' => $event_plan_id,
				'title' => self::clean_display_text((string) get_the_title($event_plan_id)),
				'date' => $date,
				'date_label' => $date !== '' ? self::format_local_timestamp((int) strtotime($date . ' 12:00:00'), (string) get_option('date_format', 'Y-m-d')) : '',
				'time_label' => $time_label,
				'venue_id' => $venue_id,
				'venue_name' => $venue_name,
				'edit_url' => (string) admin_url('post.php?post=' . $event_plan_id . '&action=edit'),
			);
		}

		public static function recent_event_plan_options(int $limit = 25): array
		{
			$today = self::now_local()->format('Y-m-d');
			$ids = get_posts(array(
				'post_type' => 'vms_event_plan',
				'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
				'posts_per_page' => $limit,
				'orderby' => 'meta_value',
				'order' => 'ASC',
				'meta_key' => self::event_meta_key('date', '_vms_event_date'),
				'fields' => 'ids',
				'meta_query' => array(
					array(
						'key' => self::event_meta_key('date', '_vms_event_date'),
						'value' => $today,
						'compare' => '>=',
						'type' => 'DATE',
					),
				),
				'suppress_filters' => false,
			));

			if (empty($ids)) {
				$ids = get_posts(array(
					'post_type' => 'vms_event_plan',
					'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
					'posts_per_page' => $limit,
					'orderby' => 'meta_value',
					'order' => 'DESC',
					'meta_key' => self::event_meta_key('date', '_vms_event_date'),
					'fields' => 'ids',
					'suppress_filters' => false,
				));
			}

			$options = array();
			foreach ((array) $ids as $plan_id) {
				$summary = self::get_event_summary((int) $plan_id);
				if (empty($summary)) {
					continue;
				}

				$label_parts = array_filter(array(
					(string) ($summary['title'] ?? ''),
					(string) ($summary['date'] ?? ''),
					(string) ($summary['venue_name'] ?? ''),
				));
				$options[] = array(
					'id' => (int) $plan_id,
					'label' => self::clean_display_text(implode(' - ', $label_parts)),
				);
			}

			return $options;
		}
	}
}
