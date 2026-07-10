<?php
if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('vms_calendar_ics_query_value')) {
	function vms_calendar_ics_query_value(string $key, string $default = ''): string
	{
		$val = get_query_var($key, null);
		if (is_string($val) && $val !== '') {
			return $val;
		}
		if (isset($_GET[$key])) {
			return (string) wp_unslash($_GET[$key]);
		}
		return $default;
	}
}

if (!function_exists('vms_calendar_ics_valid_ymd')) {
	function vms_calendar_ics_valid_ymd(string $ymd): bool
	{
		return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd);
	}
}

if (!function_exists('vms_calendar_ics_escape_text')) {
	function vms_calendar_ics_escape_text(string $value): string
	{
		$value = str_replace('\\', '\\\\', $value);
		$value = str_replace(';', '\;', $value);
		$value = str_replace(',', '\,', $value);
		$value = preg_replace("/\r\n|\r|\n/", '\\n', $value);
		return (string) $value;
	}
}

if (!function_exists('vms_calendar_ics_fold_line')) {
	function vms_calendar_ics_fold_line(string $line): string
	{
		$max = 73;
		if (strlen($line) <= $max) {
			return $line;
		}

		$out = '';
		$len = strlen($line);
		$i = 0;
		while ($i < $len) {
			$chunk = substr($line, $i, $max);
			if ($i === 0) {
				$out .= $chunk;
			} else {
				$out .= "\r\n " . $chunk;
			}
			$i += $max;
		}
		return $out;
	}
}

if (!function_exists('vms_calendar_ics_line')) {
	function vms_calendar_ics_line(string $name, string $value): string
	{
		return vms_calendar_ics_fold_line($name . ':' . vms_calendar_ics_escape_text($value)) . "\r\n";
	}
}

if (!function_exists('vms_calendar_ics_dt_utc')) {
	function vms_calendar_ics_dt_utc(string $raw): string
	{
		$raw = trim($raw);
		if ($raw === '') {
			return '';
		}
		try {
			$dt = new DateTimeImmutable($raw);
		} catch (Exception $e) {
			return '';
		}
		return $dt->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
	}
}

if (!function_exists('vms_calendar_ics_dt_from_ymd_utc')) {
	function vms_calendar_ics_dt_from_ymd_utc(string $ymd): string
	{
		if (!vms_calendar_ics_valid_ymd($ymd)) {
			return '';
		}
		$tz = function_exists('vms_get_timezone') ? vms_get_timezone() : wp_timezone();
		try {
			$dt = new DateTimeImmutable($ymd . ' 00:00:00', $tz);
		} catch (Exception $e) {
			return '';
		}
		return $dt->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
	}
}

if (!function_exists('vms_calendar_ics_vendor_id_for_request')) {
	function vms_calendar_ics_vendor_id_for_request(int $user_id): int
	{
		$user_id = absint($user_id);
		if ($user_id <= 0) {
			return 0;
		}

		$requested_raw = vms_calendar_ics_query_value('vms_calendar_vendor_id', '');
		if ($requested_raw === '') {
			$requested_raw = vms_calendar_ics_query_value('vendor_id', '0');
		}
		$requested = absint($requested_raw);
		if ($requested > 0) {
			if (function_exists('vms_user_can_access_vendor') && vms_user_can_access_vendor($user_id, $requested)) {
				return $requested;
			}
			if (function_exists('vms_get_active_vendor_ids_for_user')) {
				$active = array_values(array_unique(array_map('absint', (array) vms_get_active_vendor_ids_for_user($user_id))));
				if (in_array($requested, $active, true)) {
					return $requested;
				}
			}
		}

		if (function_exists('vms_get_primary_vendor_id_for_user')) {
			return absint(vms_get_primary_vendor_id_for_user($user_id));
		}
		if (function_exists('vms_get_vendor_id_for_current_user')) {
			return absint(vms_get_vendor_id_for_current_user());
		}
		return 0;
	}
}

if (!function_exists('vms_calendar_ics_normalize_range')) {
	/**
	 * @return array{start:string,end:string}
	 */
	function vms_calendar_ics_normalize_range(): array
	{
		$today = wp_date('Y-m-d', time(), function_exists('vms_get_timezone') ? vms_get_timezone() : wp_timezone());
		$default_start = $today;
		$default_end = gmdate('Y-m-d', strtotime('+365 days', strtotime($default_start)));

		$start = sanitize_text_field(vms_calendar_ics_query_value('vms_calendar_start', ''));
		if ($start === '') {
			$start = sanitize_text_field(vms_calendar_ics_query_value('start', $default_start));
		}
		$end = sanitize_text_field(vms_calendar_ics_query_value('vms_calendar_end', ''));
		if ($end === '') {
			$end = sanitize_text_field(vms_calendar_ics_query_value('end', $default_end));
		}

		if (!vms_calendar_ics_valid_ymd($start)) {
			$start = $default_start;
		}
		if (!vms_calendar_ics_valid_ymd($end)) {
			$end = $default_end;
		}
		if ($end < $start) {
			$tmp = $start;
			$start = $end;
			$end = $tmp;
		}

		$span_days = (int) floor((strtotime($end) - strtotime($start)) / DAY_IN_SECONDS);
		if ($span_days > 730) {
			$end = gmdate('Y-m-d', strtotime('+730 days', strtotime($start)));
		}

		return array(
			'start' => $start,
			'end' => $end,
		);
	}
}

if (!function_exists('vms_calendar_ics_collect_events')) {
	/**
	 * @return array<int,array<string,mixed>>
	 */
	function vms_calendar_ics_collect_events(string $mode, int $viewer_vendor_id): array
	{
		$range = vms_calendar_ics_normalize_range();

		$venue_raw = sanitize_text_field(vms_calendar_ics_query_value('vms_calendar_venue', ''));
		if ($venue_raw === '') {
			$venue_raw = sanitize_text_field(vms_calendar_ics_query_value('venue', ''));
		}
		if ($venue_raw === '') {
			$venue_raw = sanitize_text_field(vms_calendar_ics_query_value('venue_id', ''));
		}
		$venue_ids = 'all';
		if ($venue_raw !== '' && strtolower($venue_raw) !== 'all') {
			$venue = absint($venue_raw);
			if ($venue > 0) {
				$venue_ids = array($venue);
			}
		}

		$args = array(
			'start_date' => $range['start'],
			'end_date' => $range['end'],
			'venue_ids' => $venue_ids,
			'context' => ($mode === 'vendor') ? 'vendor' : 'public',
			'include_past' => true,
		);
		if ($mode === 'vendor') {
			$args['viewer_vendor_id'] = $viewer_vendor_id;
		}

		if (!function_exists('vms_get_calendar_events')) {
			return array();
		}
		return (array) vms_get_calendar_events($args);
	}
}

if (!function_exists('vms_calendar_ics_event_uid')) {
	function vms_calendar_ics_event_uid(int $event_plan_id, string $date_key, string $site_host): string
	{
		$event_plan_id = absint($event_plan_id);
		$date_key = sanitize_text_field($date_key);
		$site_host = sanitize_text_field($site_host);
		if ($site_host === '') {
			$site_host = 'localhost';
		}
		return 'vms-' . $event_plan_id . '-' . $date_key . '@' . $site_host;
	}
}

if (!function_exists('vms_calendar_ics_status_label')) {
	function vms_calendar_ics_status_label(array $event): string
	{
		$viewer = isset($event['viewer_status']) && is_array($event['viewer_status']) ? $event['viewer_status'] : array();
		$assigned = !empty($viewer['assigned']);
		$status = $assigned ? sanitize_key((string) ($viewer['assignment_status'] ?? '')) : '';
		if ($status === 'booked') {
			return 'Booked';
		}
		if ($status === 'tentative') {
			return 'Tentative';
		}
		return '';
	}
}

if (!function_exists('vms_calendar_ics_build')) {
	/**
	 * @param array<int,array<string,mixed>> $events
	 */
	function vms_calendar_ics_build(array $events, string $mode): string
	{
		$mode = sanitize_key($mode);
		$site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
		$tz = function_exists('vms_get_timezone') ? vms_get_timezone() : wp_timezone();
		$tz_name = ($tz instanceof DateTimeZone) ? (string) $tz->getName() : 'UTC';
		$site_host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
		$cal_name = ($mode === 'vendor')
			? sprintf(__('%s Vendor Calendar', 'backstage-venue-manager'), $site_name)
			: sprintf(__('%s Public Calendar', 'backstage-venue-manager'), $site_name);

		$out = "BEGIN:VCALENDAR\r\n";
		$out .= "VERSION:2.0\r\n";
		$out .= "PRODID:-//VMS//Calendar Feed//EN\r\n";
		$out .= "CALSCALE:GREGORIAN\r\n";
		$out .= "METHOD:PUBLISH\r\n";
		$out .= vms_calendar_ics_line('X-WR-CALNAME', $cal_name);
		$out .= vms_calendar_ics_line('X-WR-TIMEZONE', $tz_name);

		$now_utc = gmdate('Ymd\THis\Z');
		foreach ($events as $event) {
			$event_plan_id = absint($event['event_plan_id'] ?? 0);
			$date_key = (string) ($event['date_key'] ?? '');
			if ($event_plan_id <= 0 || !vms_calendar_ics_valid_ymd($date_key)) {
				continue;
			}

			$title = trim((string) ($event['title'] ?? ''));
			if ($title === '') {
				$title = __('Event', 'backstage-venue-manager');
			}

			$status_label = ($mode === 'vendor') ? vms_calendar_ics_status_label($event) : '';
			$summary = ($status_label !== '') ? ($title . ' (' . $status_label . ')') : $title;

			$dtstart = vms_calendar_ics_dt_utc((string) ($event['start_local'] ?? ''));
			if ($dtstart === '') {
				$dtstart = vms_calendar_ics_dt_from_ymd_utc($date_key);
			}
			if ($dtstart === '') {
				continue;
			}
			$dtend = vms_calendar_ics_dt_utc((string) ($event['end_local'] ?? ''));

			$venue_name = trim((string) ($event['venue_name'] ?? ''));
			$public_url = trim((string) ($event['public_url'] ?? ''));
			$excerpt = trim((string) ($event['excerpt'] ?? ''));

			$description_parts = array();
			if ($venue_name !== '') {
				$description_parts[] = sprintf(__('Venue: %s', 'backstage-venue-manager'), $venue_name);
			}
			if ($status_label !== '') {
				$description_parts[] = sprintf(__('Your status: %s', 'backstage-venue-manager'), $status_label);
			}
			if ($excerpt !== '') {
				$description_parts[] = $excerpt;
			}
			if ($public_url !== '') {
				$description_parts[] = $public_url;
			}
			$description = implode("\n", $description_parts);

			$ical_status = 'CONFIRMED';
			$plan_status = sanitize_key((string) ($event['plan_status'] ?? ''));
			if ($plan_status !== 'published') {
				$ical_status = 'TENTATIVE';
			}

			$out .= "BEGIN:VEVENT\r\n";
			$out .= vms_calendar_ics_line('UID', vms_calendar_ics_event_uid($event_plan_id, $date_key, $site_host));
			$out .= "DTSTAMP:" . $now_utc . "\r\n";
			$out .= "DTSTART:" . $dtstart . "\r\n";
			if ($dtend !== '') {
				$out .= "DTEND:" . $dtend . "\r\n";
			}
			$out .= vms_calendar_ics_line('SUMMARY', $summary);
			if ($description !== '') {
				$out .= vms_calendar_ics_line('DESCRIPTION', $description);
			}
			if ($venue_name !== '') {
				$out .= vms_calendar_ics_line('LOCATION', $venue_name);
			}
			if ($public_url !== '') {
				$out .= vms_calendar_ics_line('URL', $public_url);
			}
			$out .= "STATUS:" . $ical_status . "\r\n";
			$out .= "END:VEVENT\r\n";
		}

		$out .= "END:VCALENDAR\r\n";
		return $out;
	}
}

if (!function_exists('vms_calendar_ics_add_query_vars')) {
	function vms_calendar_ics_add_query_vars(array $vars): array
	{
		$vars[] = 'vms_calendar_ics';
		$vars[] = 'vms_calendar_start';
		$vars[] = 'vms_calendar_end';
		$vars[] = 'vms_calendar_venue';
		$vars[] = 'vms_calendar_vendor_id';
		return $vars;
	}
}
add_filter('query_vars', 'vms_calendar_ics_add_query_vars', 12);

if (!function_exists('vms_calendar_ics_render')) {
	function vms_calendar_ics_render(): void
	{
		$mode = sanitize_key(vms_calendar_ics_query_value('vms_calendar_ics', ''));
		if ($mode === '') {
			return;
		}
		if (!in_array($mode, array('public', 'vendor'), true)) {
			status_header(400);
			nocache_headers();
			header('Content-Type: text/plain; charset=utf-8');
			echo 'Invalid calendar feed mode.';
			exit;
		}

		$viewer_vendor_id = 0;
		if ($mode === 'vendor') {
			if (!is_user_logged_in()) {
				status_header(403);
				nocache_headers();
				header('Content-Type: text/plain; charset=utf-8');
				echo 'Vendor calendar feed requires login.';
				exit;
			}

			$viewer_vendor_id = vms_calendar_ics_vendor_id_for_request(get_current_user_id());
			if ($viewer_vendor_id <= 0) {
				status_header(403);
				nocache_headers();
				header('Content-Type: text/plain; charset=utf-8');
				echo 'No accessible vendor profile found for this account.';
				exit;
			}
		}

		$events = vms_calendar_ics_collect_events($mode, $viewer_vendor_id);
		$ics = vms_calendar_ics_build($events, $mode);

		status_header(200);
		nocache_headers();
		header('Content-Type: text/calendar; charset=utf-8');
		header('Content-Disposition: inline; filename="vms-calendar-' . $mode . '.ics"');
		echo $ics;
		exit;
	}
}
add_action('template_redirect', 'vms_calendar_ics_render', 3);
