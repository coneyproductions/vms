<?php

defined('ABSPATH') || exit;

if (!class_exists('VMS_Meta_Ads_Event_Plans_Service')) {
	class VMS_Meta_Ads_Event_Plans_Service {
		public static function list_event_plans(array $args = array()): array
		{
			$today = wp_date('Y-m-d', null, wp_timezone());
			$after = self::normalize_ymd((string) ($args['after'] ?? ''));
			if ($after === '') {
				$after = $today;
			}
			$days = absint($args['days'] ?? 90);
			if ($days < 1) {
				$days = 90;
			}
			$days = min($days, 365);
			$limit = absint($args['limit'] ?? 30);
			if ($limit < 1) {
				$limit = 30;
			}
			$limit = min($limit, 100);
			$q = sanitize_text_field((string) ($args['q'] ?? ''));
			$venue_id = absint($args['venue_id'] ?? 0);
			$statuses = self::normalize_status_filters((array) ($args['statuses'] ?? array('published', 'ready')));

			$to = wp_date('Y-m-d', strtotime($after . ' +' . $days . ' days'), wp_timezone());
			$query_args = array(
				'post_type' => 'vms_event_plan',
				'post_status' => array('publish', 'draft', 'pending', 'future', 'private'),
				'posts_per_page' => max($limit * 3, 45),
				'fields' => 'ids',
				'orderby' => 'meta_value',
				'order' => 'ASC',
				'meta_key' => '_vms_event_date',
				'suppress_filters' => false,
				'meta_query' => array(
					array(
						'key' => '_vms_event_date',
						'value' => array($after, $to),
						'compare' => 'BETWEEN',
						'type' => 'DATE',
					),
				),
			);
			if ($q !== '') {
				$query_args['s'] = $q;
			}
			if ($venue_id > 0) {
				$query_args['meta_query'][] = array(
					'key' => '_vms_venue_id',
					'value' => $venue_id,
					'compare' => '=',
					'type' => 'NUMERIC',
				);
			}

			$rows = get_posts($query_args);
			$items = array();
			foreach ($rows as $plan_id) {
				$item = self::build_item((int) $plan_id);
				if (!$item) {
					continue;
				}
				if (!empty($statuses) && !in_array($item['event_plan_status'], $statuses, true)) {
					continue;
				}
				$items[] = $item;
				if (count($items) >= $limit) {
					break;
				}
			}
			return $items;
		}

		public static function get_event_plan(int $event_plan_id): ?array
		{
			return self::build_item($event_plan_id);
		}

		private static function build_item(int $event_plan_id): ?array
		{
			if ($event_plan_id < 1) {
				return null;
			}
			$post = get_post($event_plan_id);
			if (!$post || $post->post_type !== 'vms_event_plan') {
				return null;
			}
			$title = sanitize_text_field((string) $post->post_title);
			$venue_id = (int) get_post_meta($event_plan_id, '_vms_venue_id', true);
			$venue_name = $venue_id > 0 ? sanitize_text_field((string) get_the_title($venue_id)) : '';
			$start_dt = self::infer_start_datetime($event_plan_id, $post);
			$start_input = $start_dt ? $start_dt->format('Y-m-d H:i') : '';
			$date_format = (string) get_option('date_format', 'Y-m-d');
			$time_format = (string) get_option('time_format', 'g:i a');
			$start_display = $start_dt ? wp_date($date_format . ' ' . $time_format, $start_dt->getTimestamp(), wp_timezone()) : '';
			$tec_event_id = (int) get_post_meta($event_plan_id, '_vms_tec_event_id', true);
			$event_permalink = get_permalink($event_plan_id);
			if (!is_string($event_permalink)) {
				$event_permalink = '';
			}
			$ticket_url = self::resolve_ticket_url($event_plan_id, $tec_event_id, $event_permalink);
			$status = self::infer_internal_status($event_plan_id);
			$ma_last_updated = trim((string) get_post_meta($event_plan_id, 'vms_ma_last_updated_local', true));
			$ma_last_exported = trim((string) get_post_meta($event_plan_id, 'vms_ma_last_exported_local', true));
			$ma_status_label = __('No draft yet', 'vms-meta-ads');
			if ($ma_last_updated !== '') {
				$ma_status_label = sprintf(__('Draft saved %s', 'vms-meta-ads'), $ma_last_updated);
			}
			if ($ma_last_exported !== '') {
				$ma_status_label = sprintf(__('Exported %s', 'vms-meta-ads'), $ma_last_exported);
			}

			return array(
				'id' => $event_plan_id,
				'title' => $title,
				'start_local' => $start_input,
				'start_input' => $start_input,
				'start_display' => $start_display,
				'venue_name' => $venue_name,
				'venue_id' => $venue_id,
				'ticket_url' => $ticket_url,
				'event_permalink' => $event_permalink,
				'tec_event_id' => $tec_event_id,
				'has_ticket_url' => $ticket_url !== '',
				'event_plan_status' => $status,
				'meta_ads_status' => $ma_status_label,
				'meta_ads_last_updated' => $ma_last_updated,
				'meta_ads_last_exported' => $ma_last_exported,
			);
		}

		private static function infer_internal_status(int $event_plan_id): string
		{
			$status = '';
			if (function_exists('vms_event_plan_get_status')) {
				$status = sanitize_key((string) vms_event_plan_get_status($event_plan_id, 'event_list'));
			}
			if ($status === '') {
				$status = sanitize_key((string) get_post_meta($event_plan_id, '_vms_event_plan_status', true));
			}
			return $status !== '' ? $status : 'draft';
		}

		private static function infer_start_datetime(int $event_plan_id, WP_Post $post): ?DateTimeImmutable
		{
			$date = trim((string) get_post_meta($event_plan_id, '_vms_event_date', true));
			$time = trim((string) get_post_meta($event_plan_id, '_vms_start_time', true));
			if ($date !== '') {
				$dt = self::parse_local_datetime($date . ' ' . ($time !== '' ? $time : '00:00:00'));
				if ($dt instanceof DateTimeImmutable) {
					return $dt;
				}
			}
			$fallback_keys = array('_vms_event_start', '_EventStartDate', 'event_start');
			foreach ($fallback_keys as $key) {
				$value = trim((string) get_post_meta($event_plan_id, $key, true));
				if ($value === '') {
					continue;
				}
				$dt = self::parse_meta_datetime($value);
				if ($dt instanceof DateTimeImmutable) {
					return $dt;
				}
			}
			$post_gmt = trim((string) $post->post_date_gmt);
			if ($post_gmt !== '' && $post_gmt !== '0000-00-00 00:00:00') {
				try {
					$dt = new DateTimeImmutable($post_gmt . ' UTC');
					return $dt->setTimezone(wp_timezone());
				} catch (Exception $e) {
					return null;
				}
			}
			return null;
		}

		private static function parse_local_datetime(string $raw): ?DateTimeImmutable
		{
			$raw = trim($raw);
			if ($raw === '') {
				return null;
			}
			$tz = wp_timezone();
			$formats = array(
				'Y-m-d H:i:s',
				'Y-m-d H:i',
				'Y-m-d g:i A',
				'Y-m-d g:ia',
				'Y-m-d h:i A',
				'Y-m-d h:ia',
				'Y-m-d',
			);
			foreach ($formats as $format) {
				$dt = DateTimeImmutable::createFromFormat($format, $raw, $tz);
				if ($dt instanceof DateTimeImmutable) {
					return $dt;
				}
			}
			try {
				return new DateTimeImmutable($raw, $tz);
			} catch (Exception $e) {
				return null;
			}
		}

		private static function parse_meta_datetime(string $raw): ?DateTimeImmutable
		{
			$raw = trim($raw);
			if ($raw === '') {
				return null;
			}
			$tz = wp_timezone();
			$has_explicit_tz = (bool) preg_match('/(?:Z|[+\-]\d{2}:?\d{2}| (?:UTC|GMT|[A-Z]{3,5}))$/', $raw);
			try {
				if ($has_explicit_tz) {
					$dt = new DateTimeImmutable($raw);
					return $dt->setTimezone($tz);
				}
				return new DateTimeImmutable($raw, $tz);
			} catch (Exception $e) {
				return null;
			}
		}

		private static function resolve_ticket_url(int $event_plan_id, int $tec_event_id, string $event_permalink): string
		{
			$candidate_keys = array(
				'_vms_destination_url',
				'_vms_ticket_url',
				'_vms_ticket_link',
				'_vms_event_url',
			);
			foreach ($candidate_keys as $key) {
				$meta = esc_url_raw((string) get_post_meta($event_plan_id, $key, true));
				if ($meta !== '') {
					return $meta;
				}
			}

			if ($tec_event_id > 0) {
				$tec_keys = array('_EventURL', '_EventWebsite', '_vms_tec_event_url');
				foreach ($tec_keys as $key) {
					$meta = esc_url_raw((string) get_post_meta($tec_event_id, $key, true));
					if ($meta !== '') {
						return $meta;
					}
				}
			}

			$plan_tec_url = esc_url_raw((string) get_post_meta($event_plan_id, '_vms_tec_event_url', true));
			if ($plan_tec_url !== '') {
				return $plan_tec_url;
			}

			if ($event_permalink !== '') {
				return esc_url_raw($event_permalink);
			}

			return '';
		}

		private static function normalize_ymd(string $value): string
		{
			$value = trim($value);
			if ($value === '') {
				return '';
			}
			if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
				return '';
			}
			$ts = strtotime($value . ' 00:00:00');
			if ($ts === false) {
				return '';
			}
			return wp_date('Y-m-d', $ts, wp_timezone());
		}

		private static function normalize_status_filters(array $statuses): array
		{
			if (empty($statuses)) {
				$statuses = array('published', 'ready');
			}
			$out = array();
			foreach ($statuses as $status) {
				$key = sanitize_key((string) $status);
				if ($key === '') {
					continue;
				}
				$out[$key] = true;
			}
			return array_keys($out);
		}
	}
}
