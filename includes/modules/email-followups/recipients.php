<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_email_followups_time_label')) {
	function vms_email_followups_time_label(string $hhmm): string
	{
		$hhmm = trim($hhmm);
		if ($hhmm === '') {
			return '';
		}
		$ts = strtotime('2000-01-01 ' . $hhmm);
		return $ts ? wp_date('g:ia', $ts, wp_timezone()) : $hhmm;
	}
}

if (!function_exists('vms_email_followups_plan_tec_event_id')) {
	function vms_email_followups_plan_tec_event_id(int $event_plan_id): int
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return 0;
		}
		$key = function_exists('vms_ticket_revenue_plan_tec_meta_key') ? vms_ticket_revenue_plan_tec_meta_key() : '_vms_tec_event_id';
		$tec_event_id = absint(get_post_meta($event_plan_id, $key, true));
		if ($tec_event_id <= 0) {
			$tec_event_id = absint(get_post_meta($event_plan_id, '_vms_calendar_event_id', true));
		}
		return $tec_event_id;
	}
}

if (!function_exists('vms_email_followups_event_context')) {
	function vms_email_followups_event_context(int $event_plan_id): array
	{
		$event_plan_id = absint($event_plan_id);
		$post = $event_plan_id > 0 ? get_post($event_plan_id) : null;
		if (!$post || $post->post_type !== 'vms_event_plan') {
			return array('valid' => false, 'message' => __('Event Plan not found.', 'backstage-venue-manager'));
		}

		$status_key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'status') : '_vms_event_plan_status';
		if ($status_key === '') {
			$status_key = '_vms_event_plan_status';
		}
		$status = sanitize_key((string) get_post_meta($event_plan_id, $status_key, true));
		$date = trim((string) get_post_meta($event_plan_id, '_vms_event_date', true));
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			$start_local = trim((string) get_post_meta($event_plan_id, '_vms_event_plan_start_datetime', true));
			if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $start_local, $m)) {
				$date = (string) $m[1];
			}
		}

		$start_time = trim((string) get_post_meta($event_plan_id, '_vms_start_time', true));
		$end_time = trim((string) get_post_meta($event_plan_id, '_vms_end_time', true));
		if ($start_time === '') {
			$start_time = '19:00';
		}
		$start_local = $date !== '' ? $date . ' ' . $start_time . ':00' : '';
		$start_ts = $start_local !== '' ? strtotime($start_local) : 0;
		$gates_ts = $start_ts > 0 ? $start_ts - HOUR_IN_SECONDS : 0;

		$venue_id = absint(get_post_meta($event_plan_id, '_vms_venue_id', true));
		if ($venue_id <= 0) {
			$venue_id = absint(get_post_meta($event_plan_id, '_vms_event_plan_venue_id', true));
		}
		$venue_name = $venue_id > 0 ? trim((string) get_the_title($venue_id)) : '';
		if ($venue_name === '') {
			$venue_name = sanitize_text_field((string) get_bloginfo('name'));
		}

		$tec_event_id = vms_email_followups_plan_tec_event_id($event_plan_id);
		$event_url = $tec_event_id > 0 ? get_permalink($tec_event_id) : '';
		if (!$event_url && $event_plan_id > 0) {
			$event_url = home_url('/');
		}

		return array(
			'valid' => true,
			'event_plan_id' => $event_plan_id,
			'tec_event_id' => $tec_event_id,
			'post_status' => (string) $post->post_status,
			'plan_status' => $status,
			'event_name' => (string) get_the_title($event_plan_id),
			'event_date' => $date,
			'event_date_label' => $start_ts > 0 ? wp_date('l, F j, Y', $start_ts, wp_timezone()) : $date,
			'start_time' => $start_time,
			'start_time_label' => vms_email_followups_time_label($start_time),
			'end_time' => $end_time,
			'end_time_label' => vms_email_followups_time_label($end_time),
			'gates_time_label' => $gates_ts > 0 ? wp_date('g:ia', $gates_ts, wp_timezone()) : '',
			'start_ts' => $start_ts,
			'venue_id' => $venue_id,
			'venue_name' => $venue_name,
			'event_url' => esc_url_raw((string) $event_url),
			'site_url' => esc_url_raw(home_url('/')),
			'site_name' => sanitize_text_field((string) get_bloginfo('name')),
		);
	}
}

if (!function_exists('vms_email_followups_context_allows_send')) {
	function vms_email_followups_context_allows_send(array $context): array
	{
		if (empty($context['valid'])) {
			return array(false, (string) ($context['message'] ?? 'invalid_event'));
		}
		if ((string) ($context['post_status'] ?? '') !== 'publish') {
			return array(false, 'event_plan_not_published');
		}
		if (in_array(sanitize_key((string) ($context['plan_status'] ?? '')), array('cancelled', 'canceled'), true)) {
			return array(false, 'event_plan_cancelled');
		}
		if ((string) ($context['event_date'] ?? '') === '') {
			return array(false, 'missing_event_date');
		}
		return array(true, 'ok');
	}
}

if (!function_exists('vms_email_followups_event_recipients')) {
	function vms_email_followups_event_recipients(int $event_plan_id): array
	{
		$event_plan_id = absint($event_plan_id);
		$result = array(
			'recipients' => array(),
			'counts' => array(
				'rows_scanned' => 0,
				'invalid_email' => 0,
				'refunded_or_zero' => 0,
				'unique_recipients' => 0,
				'tickets_net' => 0,
			),
			'warnings' => array(),
		);

		if (!function_exists('vms_get_ticket_sales_rows')) {
			$result['warnings'][] = __('Ticket sales resolver is unavailable.', 'backstage-venue-manager');
			return $result;
		}

		$context = vms_email_followups_event_context($event_plan_id);
		$args = array(
			'event_plan_id' => $event_plan_id,
			'order_statuses' => array('processing', 'completed', 'on-hold'),
			'include_refunded_lines' => true,
			'include_unresolved' => false,
			'limit' => 0,
		);
		if (!empty($context['tec_event_id'])) {
			$args['tec_event_id'] = absint($context['tec_event_id']);
		}

		$rows = vms_get_ticket_sales_rows($args);
		if (!is_array($rows)) {
			$rows = array();
		}

		$by_email = array();
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			$result['counts']['rows_scanned']++;
			$email = sanitize_email((string) ($row['customer_email'] ?? ''));
			if (!is_email($email)) {
				$result['counts']['invalid_email']++;
				continue;
			}
			$qty = max(0, (int) ($row['qty'] ?? 0));
			$refunded_qty = max(0, (int) ($row['refunded_qty'] ?? 0));
			$net_qty = max(0, $qty - $refunded_qty);
			if ($net_qty <= 0 || !empty($row['is_refunded']) && $net_qty <= 0) {
				$result['counts']['refunded_or_zero']++;
				continue;
			}

			$key = strtolower($email);
			if (!isset($by_email[$key])) {
				$by_email[$key] = array(
					'email' => $email,
					'name' => sanitize_text_field((string) ($row['customer_name'] ?? '')),
					'qty' => 0,
					'order_ids' => array(),
					'order_numbers' => array(),
				);
			}
			$by_email[$key]['qty'] += $net_qty;
			if (!empty($row['order_id'])) {
				$by_email[$key]['order_ids'][] = absint($row['order_id']);
			}
			if (!empty($row['order_number'])) {
				$by_email[$key]['order_numbers'][] = sanitize_text_field((string) $row['order_number']);
			}
		}

		foreach ($by_email as $email => $recipient) {
			$recipient['order_ids'] = array_values(array_unique(array_filter(array_map('absint', (array) $recipient['order_ids']))));
			$recipient['order_numbers'] = array_values(array_unique(array_filter(array_map('sanitize_text_field', (array) $recipient['order_numbers']))));
			$result['recipients'][] = $recipient;
			$result['counts']['tickets_net'] += (int) $recipient['qty'];
		}
		$result['counts']['unique_recipients'] = count($result['recipients']);
		return $result;
	}
}

if (!function_exists('vms_email_followups_event_choices')) {
	function vms_email_followups_event_choices(int $limit = 80, int $selected_event_plan_id = 0): array
	{
		$limit = max(1, min(200, $limit));
		$now = time();
		$today = wp_date('Y-m-d', $now, wp_timezone());
		$today_ts = strtotime($today . ' 00:00:00') ?: $now;
		$from = wp_date('Y-m-d', $now - (365 * DAY_IN_SECONDS), wp_timezone());
		$to = wp_date('Y-m-d', $now + (365 * DAY_IN_SECONDS), wp_timezone());

		$posts = get_posts(array(
			'post_type' => 'vms_event_plan',
			'post_status' => array('publish', 'draft', 'pending', 'private'),
			'posts_per_page' => 200,
			'orderby' => 'meta_value',
			'order' => 'DESC',
			'meta_key' => '_vms_event_date',
			'meta_query' => array(
				array(
					'key' => '_vms_event_date',
					'value' => array($from, $to),
					'compare' => 'BETWEEN',
					'type' => 'DATE',
				),
			),
		));
		if (!is_array($posts)) {
			$posts = array();
		}

		$by_id = array();
		foreach ($posts as $post) {
			if ($post instanceof WP_Post) {
				$by_id[(int) $post->ID] = $post;
			}
		}

		if ($selected_event_plan_id > 0 && empty($by_id[$selected_event_plan_id])) {
			$selected = get_post($selected_event_plan_id);
			if ($selected instanceof WP_Post && $selected->post_type === 'vms_event_plan') {
				$by_id[(int) $selected->ID] = $selected;
			}
		}

		$posts = array_values($by_id);
		usort($posts, static function ($a, $b) use ($today_ts): int {
			$a_date = (string) get_post_meta($a->ID, '_vms_event_date', true);
			$b_date = (string) get_post_meta($b->ID, '_vms_event_date', true);
			$a_ts = strtotime($a_date . ' 00:00:00') ?: 0;
			$b_ts = strtotime($b_date . ' 00:00:00') ?: 0;
			$a_distance = $a_ts > 0 ? abs($a_ts - $today_ts) : PHP_INT_MAX;
			$b_distance = $b_ts > 0 ? abs($b_ts - $today_ts) : PHP_INT_MAX;
			if ($a_distance === $b_distance) {
				return $a_ts <=> $b_ts;
			}
			return $a_distance <=> $b_distance;
		});

		return array_slice($posts, 0, $limit);
	}
}

if (!function_exists('vms_email_followups_upcoming_event_choices')) {
	function vms_email_followups_upcoming_event_choices(int $limit = 50): array
	{
		return vms_email_followups_event_choices($limit);
	}
}

if (!function_exists('vms_email_followups_event_choice_label')) {
	function vms_email_followups_event_choice_label(WP_Post $plan): string
	{
		$date = (string) get_post_meta($plan->ID, '_vms_event_date', true);
		$today = wp_date('Y-m-d', time(), wp_timezone());
		$status = '';
		if ($date !== '') {
			if ($date < $today) {
				$status = ' — ' . __('past event', 'backstage-venue-manager');
			} elseif ($date === $today) {
				$status = ' — ' . __('today', 'backstage-venue-manager');
			}
		}

		return trim($date . ' — ' . get_the_title($plan) . $status);
	}
}
