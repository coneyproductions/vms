<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_social_event_meta_key')) {
	function vms_social_event_meta_key(string $field, string $fallback): string
	{
		if (function_exists('bvmgr_meta_key')) {
			$key = (string) bvmgr_meta_key('event_plan', $field);
			if ($key !== '') {
				return $key;
			}
		}
		return $fallback;
	}
}

if (!function_exists('vms_social_normalize_whitespace')) {
	function vms_social_normalize_whitespace(string $input): string
	{
		$input = preg_replace('/[\r\t ]+/', ' ', $input);
		$input = preg_replace('/ *\n+ */', "\n", (string) $input);
		return trim((string) $input);
	}
}

if (!function_exists('vms_social_trim_preview')) {
	function vms_social_trim_preview(string $text, int $width = 180): string
	{
		$text = (string) $text;
		$width = max(10, $width);
		if (function_exists('mb_strimwidth')) {
			return mb_strimwidth($text, 0, $width, '…');
		}
		if (strlen($text) <= $width) {
			return $text;
		}
		return substr($text, 0, max(0, $width - 1)) . '…';
	}
}

if (!function_exists('vms_social_format_local_date')) {
	function vms_social_format_local_date(string $ymd): string
	{
		$ymd = trim($ymd);
		if ($ymd === '') {
			return '';
		}
		$ts = strtotime($ymd . ' 00:00:00');
		if (!$ts) {
			return $ymd;
		}
		return wp_date('M j, Y', $ts, wp_timezone());
	}
}

if (!function_exists('vms_social_format_local_time')) {
	function vms_social_format_local_time(string $hhmm): string
	{
		$hhmm = trim($hhmm);
		if ($hhmm === '') {
			return '';
		}
		$ts = strtotime('2000-01-01 ' . $hhmm . ':00');
		if (!$ts) {
			$ts = strtotime('2000-01-01 ' . $hhmm);
		}
		if (!$ts) {
			return $hhmm;
		}
		return wp_date('g:ia', $ts, wp_timezone());
	}
}

if (!function_exists('vms_social_get_venue_location')) {
	function vms_social_get_venue_location(int $venue_id): array
	{
		if ($venue_id <= 0) {
			return array('name' => '', 'city' => '', 'state' => '');
		}

		$name = (string) get_the_title($venue_id);
		$city = (string) get_post_meta($venue_id, '_vms_city', true);
		$state = (string) get_post_meta($venue_id, '_vms_state', true);

		if ($city === '') {
			$city = (string) get_post_meta($venue_id, 'city', true);
		}
		if ($state === '') {
			$state = (string) get_post_meta($venue_id, 'state', true);
		}

		return array(
			'name' => $name,
			'city' => $city,
			'state' => strtoupper((string) $state),
		);
	}
}

if (!function_exists('vms_social_resolve_ticket_url')) {
	function vms_social_resolve_ticket_url(int $event_plan_id): string
	{
		if ($event_plan_id <= 0) {
			return '';
		}

		if (function_exists('vms_tec_get_ticket_url_for_plan')) {
			$url = esc_url_raw((string) vms_tec_get_ticket_url_for_plan($event_plan_id));
			if ($url !== '') {
				return $url;
			}
		}

		$tec_url_key = vms_social_event_meta_key('tec_event_url', '_vms_tec_event_url');
		$tec_id_key = vms_social_event_meta_key('tec_event_id', '_vms_tec_event_id');
		$tec_url = esc_url_raw((string) get_post_meta($event_plan_id, $tec_url_key, true));
		if ($tec_url !== '') {
			return $tec_url;
		}

		$tec_id = (int) get_post_meta($event_plan_id, $tec_id_key, true);
		if ($tec_id > 0) {
			$url = get_permalink($tec_id);
			if ($url) {
				return esc_url_raw((string) $url);
			}
		}

		$event_url = get_permalink($event_plan_id);
		if ($event_url) {
			return esc_url_raw((string) $event_url);
		}

		return '';
	}
}

if (!function_exists('vms_social_get_performer_names')) {
	function vms_social_get_performer_names(int $event_plan_id): string
	{
		if ($event_plan_id <= 0) {
			return '';
		}

		$names = array();
		$band_key = vms_social_event_meta_key('band_vendor_id', '_vms_band_vendor_id');
		$band_id = (int) get_post_meta($event_plan_id, $band_key, true);
		if ($band_id > 0) {
			$title = (string) get_the_title($band_id);
			if ($title !== '') {
				$names[] = $title;
			}
		}

		$secondary_key = vms_social_event_meta_key('secondary_vendor_ids', '_vms_secondary_vendor_ids');
		$secondary = get_post_meta($event_plan_id, $secondary_key, true);
		if (is_array($secondary)) {
			foreach ($secondary as $vendor_id) {
				$title = (string) get_the_title((int) $vendor_id);
				if ($title !== '') {
					$names[] = $title;
				}
			}
		}

		$names = array_values(array_unique(array_filter(array_map('trim', $names))));
		return implode(', ', $names);
	}
}

if (!function_exists('vms_social_event_plan_context')) {
	/**
	 * @return array<string,mixed>
	 */
	function vms_social_event_plan_context(int $event_plan_id): array
	{
		$event_plan_id = absint($event_plan_id);
		$post = $event_plan_id > 0 ? get_post($event_plan_id) : null;
		if (!($post instanceof WP_Post) || $post->post_type !== 'vms_event_plan') {
			return array();
		}

		$date_key = vms_social_event_meta_key('date', '_vms_event_date');
		$venue_key = vms_social_event_meta_key('venue_id', '_vms_venue_id');
		$status_key = vms_social_event_meta_key('status', '_vms_event_plan_status');

		$event_date_raw = (string) get_post_meta($event_plan_id, $date_key, true);
		$start_time_raw = (string) get_post_meta($event_plan_id, '_vms_start_time', true);
		$end_time_raw = (string) get_post_meta($event_plan_id, '_vms_end_time', true);
		$venue_id = (int) get_post_meta($event_plan_id, $venue_key, true);
		$status = sanitize_key((string) get_post_meta($event_plan_id, $status_key, true));
		$venue = vms_social_get_venue_location($venue_id);

		$featured_image_url = '';
		$thumbnail_id = (int) get_post_thumbnail_id($event_plan_id);
		if ($thumbnail_id > 0) {
			$featured_image_url = (string) wp_get_attachment_url($thumbnail_id);
		}

		$price_text = '';
		$from_price = function_exists('vms_ticketing_v2_get_from_price_for_display')
			? vms_ticketing_v2_get_from_price_for_display($event_plan_id)
			: null;
		if ($from_price !== null && $from_price > 0) {
			$price_text = 'From $' . number_format_i18n((float) $from_price, 2);
		}

		$ticket_url = vms_social_resolve_ticket_url($event_plan_id);
		$event_url = get_permalink($event_plan_id);
		$event_url = $event_url ? esc_url_raw((string) $event_url) : '';

		return array(
			'event_plan_id' => $event_plan_id,
			'event_title' => (string) get_the_title($event_plan_id),
			'event_status' => $status !== '' ? $status : 'draft',
			'event_date_raw' => $event_date_raw,
			'event_date' => vms_social_format_local_date($event_date_raw),
			'start_time_raw' => $start_time_raw,
			'end_time_raw' => $end_time_raw,
			'start_time' => vms_social_format_local_time($start_time_raw),
			'end_time' => vms_social_format_local_time($end_time_raw),
			'venue_id' => $venue_id,
			'venue_name' => (string) ($venue['name'] ?? ''),
			'venue_city' => (string) ($venue['city'] ?? ''),
			'venue_state' => (string) ($venue['state'] ?? ''),
			'ticket_url' => $ticket_url,
			'event_url' => $event_url,
			'featured_image_url' => esc_url_raw($featured_image_url),
			'performer_names' => vms_social_get_performer_names($event_plan_id),
			'price_text' => $price_text,
			'hashtags' => '',
		);
	}
}

if (!function_exists('vms_social_context_from_queue_row')) {
	/**
	 * @param array<string,mixed> $queue_row
	 * @return array<string,mixed>
	 */
	function vms_social_context_from_queue_row(array $queue_row): array
	{
		$event_plan_id = (int) ($queue_row['event_plan_id'] ?? 0);
		if ($event_plan_id > 0) {
			return vms_social_event_plan_context($event_plan_id);
		}

		$tec_event_id = (int) ($queue_row['tec_event_id'] ?? 0);
		if ($tec_event_id > 0) {
			$title = (string) get_the_title($tec_event_id);
			$url = get_permalink($tec_event_id);
			return array(
				'event_plan_id' => 0,
				'event_title' => $title,
				'event_status' => 'published',
				'event_date_raw' => '',
				'event_date' => '',
				'start_time_raw' => '',
				'end_time_raw' => '',
				'start_time' => '',
				'end_time' => '',
				'venue_id' => (int) ($queue_row['venue_id'] ?? 0),
				'venue_name' => '',
				'venue_city' => '',
				'venue_state' => '',
				'ticket_url' => $url ? esc_url_raw((string) $url) : '',
				'event_url' => $url ? esc_url_raw((string) $url) : '',
				'featured_image_url' => '',
				'performer_names' => '',
				'price_text' => '',
				'hashtags' => '',
			);
		}

		return array();
	}
}
