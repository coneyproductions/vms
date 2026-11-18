<?php
defined('ABSPATH') || exit;

/**
 * Canonical Event Plan operational-window and active-context contracts.
 *
 * The window reader is intentionally read-only. Event Plan editors or add-ons
 * may persist the canonical metadata later without requiring Service Requests
 * (or any other consumer) to invent a separate event clock.
 */

if (!function_exists('bvmgr_operational_window_meta_keys')) {
	/** @return array<string,string> */
	function bvmgr_operational_window_meta_keys(): array
	{
		$meta_key = static function (string $field, string $fallback): string {
			if (function_exists('bvmgr_meta_key')) {
				$key = (string) bvmgr_meta_key('event_plan', $field);
				return $key !== '' ? $key : $fallback;
			}
			if (function_exists('vms_meta_key')) {
				$key = (string) vms_meta_key('event_plan', $field);
				return $key !== '' ? $key : $fallback;
			}
			return $fallback;
		};
		$keys = array(
			'start_local'          => $meta_key('operational_start_local', '_bvmgr_event_plan_operational_start_local'),
			'end_local'            => $meta_key('operational_end_local', '_bvmgr_event_plan_operational_end_local'),
			'start_offset_minutes' => $meta_key('operational_start_offset_minutes', '_bvmgr_event_plan_operational_start_offset_minutes'),
			'end_offset_minutes'   => $meta_key('operational_end_offset_minutes', '_bvmgr_event_plan_operational_end_offset_minutes'),
		);

		/**
		 * Filter the canonical Event Plan operational-window metadata keys.
		 *
		 * @param array<string,string> $keys Canonical metadata keys.
		 */
		$filtered = apply_filters('bvmgr_operational_window_meta_keys', $keys);
		return is_array($filtered) ? array_merge($keys, $filtered) : $keys;
	}
}

if (!function_exists('bvmgr_operational_parse_local_datetime')) {
	function bvmgr_operational_parse_local_datetime(string $value): ?DateTimeImmutable
	{
		$value = trim($value);
		if ($value === '') {
			return null;
		}

		$timezone = wp_timezone();
		$formats = array('Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i');
		foreach ($formats as $format) {
			$parsed = DateTimeImmutable::createFromFormat('!' . $format, $value, $timezone);
			$errors = DateTimeImmutable::getLastErrors();
			if (!($parsed instanceof DateTimeImmutable)
				|| ($errors !== false && (!empty($errors['warning_count']) || !empty($errors['error_count'])))
				|| $parsed->format($format) !== $value) {
				continue;
			}

			return $parsed;
		}

		return null;
	}
}

if (!function_exists('bvmgr_operational_event_occurrence')) {
	/** @return array{valid:bool,start:?DateTimeImmutable,end:?DateTimeImmutable,reason:string,source:string} */
	function bvmgr_operational_event_occurrence(int $event_plan_id): array
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return array('valid' => false, 'start' => null, 'end' => null, 'reason' => 'invalid_event_plan', 'source' => 'none');
		}

		$start_helper = function_exists('bvmgr_event_plan_start_datetime')
			? 'bvmgr_event_plan_start_datetime'
			: (function_exists('vms_event_plan_start_datetime') ? 'vms_event_plan_start_datetime' : '');
		$end_helper = function_exists('bvmgr_event_plan_end_datetime')
			? 'bvmgr_event_plan_end_datetime'
			: (function_exists('vms_event_plan_end_datetime') ? 'vms_event_plan_end_datetime' : '');
		if ($start_helper !== '' && $end_helper !== '') {
			$start = call_user_func($start_helper, $event_plan_id);
			$end = call_user_func($end_helper, $event_plan_id);
			if ($start instanceof DateTimeImmutable && $end instanceof DateTimeImmutable && $end > $start) {
				return array('valid' => true, 'start' => $start, 'end' => $end, 'reason' => '', 'source' => 'event_plan_datetime');
			}
		}

		$occurrence_helper = function_exists('bvmgr_event_occurrence_for_plan')
			? 'bvmgr_event_occurrence_for_plan'
			: (function_exists('vms_event_occurrence_for_plan') ? 'vms_event_occurrence_for_plan' : '');
		if ($occurrence_helper !== '') {
			$occurrence = call_user_func($occurrence_helper, $event_plan_id);
			if (!empty($occurrence['valid'])
				&& ($occurrence['start'] ?? null) instanceof DateTimeImmutable
				&& ($occurrence['end'] ?? null) instanceof DateTimeImmutable) {
				return array(
					'valid' => true,
					'start' => $occurrence['start'],
					'end' => $occurrence['end'],
					'reason' => '',
					'source' => 'event_occurrence',
				);
			}
		}

		$date = trim((string) get_post_meta($event_plan_id, '_vms_event_date', true));
		$start_time = trim((string) get_post_meta($event_plan_id, '_vms_start_time', true));
		$end_time = trim((string) get_post_meta($event_plan_id, '_vms_end_time', true));
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
			|| !preg_match('/^\d{2}:\d{2}$/', $start_time)
			|| !preg_match('/^\d{2}:\d{2}$/', $end_time)) {
			return array('valid' => false, 'start' => null, 'end' => null, 'reason' => 'incomplete_event_timing', 'source' => 'none');
		}

		$start = bvmgr_operational_parse_local_datetime($date . ' ' . $start_time);
		$end = bvmgr_operational_parse_local_datetime($date . ' ' . $end_time);
		if (!($start instanceof DateTimeImmutable) || !($end instanceof DateTimeImmutable)) {
			return array('valid' => false, 'start' => null, 'end' => null, 'reason' => 'invalid_event_timing', 'source' => 'none');
		}
		if ($end < $start) {
			$end = $end->modify('+1 day');
		}
		if ($end <= $start || ($end->getTimestamp() - $start->getTimestamp()) > DAY_IN_SECONDS) {
			return array('valid' => false, 'start' => null, 'end' => null, 'reason' => 'invalid_event_window', 'source' => 'none');
		}

		return array('valid' => true, 'start' => $start, 'end' => $end, 'reason' => '', 'source' => 'event_schedule');
	}
}

if (!function_exists('bvmgr_event_plan_operational_window')) {
	/**
	 * Return the canonical operational window for one Event Plan.
	 *
	 * Explicit local datetime metadata overrides the corresponding occurrence
	 * boundary. Signed offsets otherwise extend or contract the show window.
	 *
	 * @return array<string,mixed>
	 */
	function bvmgr_event_plan_operational_window(int $event_plan_id): array
	{
		$event_plan_id = absint($event_plan_id);
		$invalid = array(
			'valid' => false,
			'event_plan_id' => $event_plan_id,
			'source' => 'none',
			'reason' => 'invalid_event_plan',
			'timezone' => wp_timezone()->getName(),
			'start_local' => '',
			'end_local' => '',
			'start_gmt' => '',
			'end_gmt' => '',
			'start_timestamp' => 0,
			'end_timestamp' => 0,
		);
		if ($event_plan_id <= 0) {
			return $invalid;
		}

		$keys = bvmgr_operational_window_meta_keys();
		$explicit_start_raw = trim((string) get_post_meta($event_plan_id, (string) $keys['start_local'], true));
		$explicit_end_raw = trim((string) get_post_meta($event_plan_id, (string) $keys['end_local'], true));
		$explicit_start = bvmgr_operational_parse_local_datetime($explicit_start_raw);
		$explicit_end = bvmgr_operational_parse_local_datetime($explicit_end_raw);
		if ($explicit_start_raw !== '' && !($explicit_start instanceof DateTimeImmutable)) {
			$invalid['reason'] = 'invalid_explicit_start';
			return $invalid;
		}
		if ($explicit_end_raw !== '' && !($explicit_end instanceof DateTimeImmutable)) {
			$invalid['reason'] = 'invalid_explicit_end';
			return $invalid;
		}

		$occurrence = bvmgr_operational_event_occurrence($event_plan_id);
		$start = $explicit_start;
		$end = $explicit_end;
		$source = ($explicit_start instanceof DateTimeImmutable || $explicit_end instanceof DateTimeImmutable)
			? 'explicit_meta'
			: sanitize_key((string) ($occurrence['source'] ?? 'event_occurrence'));

		$offsets = array(
			'start' => (int) get_post_meta($event_plan_id, (string) $keys['start_offset_minutes'], true),
			'end' => (int) get_post_meta($event_plan_id, (string) $keys['end_offset_minutes'], true),
		);
		/**
		 * Filter signed operational offsets from the public occurrence.
		 *
		 * @param array{start:int,end:int} $offsets      Signed minute offsets.
		 * @param int                      $event_plan_id Event Plan post ID.
		 */
		$filtered_offsets = apply_filters('bvmgr_event_plan_operational_window_offsets', $offsets, $event_plan_id);
		if (is_array($filtered_offsets)) {
			$offsets['start'] = (int) ($filtered_offsets['start'] ?? $offsets['start']);
			$offsets['end'] = (int) ($filtered_offsets['end'] ?? $offsets['end']);
		}
		$offsets['start'] = max(-10080, min(10080, $offsets['start']));
		$offsets['end'] = max(-10080, min(10080, $offsets['end']));

		if (!empty($occurrence['valid'])) {
			if (!($start instanceof DateTimeImmutable)) {
				$start = $occurrence['start']->modify(($offsets['start'] >= 0 ? '+' : '') . $offsets['start'] . ' minutes');
			}
			if (!($end instanceof DateTimeImmutable)) {
				$end = $occurrence['end']->modify(($offsets['end'] >= 0 ? '+' : '') . $offsets['end'] . ' minutes');
			}
			if ($source !== 'explicit_meta' && ($offsets['start'] !== 0 || $offsets['end'] !== 0)) {
				$source .= '_offsets';
			}
		}

		if (!($start instanceof DateTimeImmutable) || !($end instanceof DateTimeImmutable)) {
			$invalid['reason'] = (string) ($occurrence['reason'] ?? 'incomplete_operational_window');
			return $invalid;
		}
		if ($end <= $start) {
			$invalid['reason'] = 'invalid_operational_window';
			return $invalid;
		}

		$bounds = array('start' => $start, 'end' => $end, 'source' => $source);
		/**
		 * Filter resolved operational bounds before the serializable result is built.
		 *
		 * @param array<string,mixed> $bounds        Start/end DateTimeImmutable values and source.
		 * @param int                 $event_plan_id Event Plan post ID.
		 */
		$filtered_bounds = apply_filters('bvmgr_event_plan_operational_window_bounds', $bounds, $event_plan_id);
		if (is_array($filtered_bounds)) {
			$start = ($filtered_bounds['start'] ?? null) instanceof DateTimeImmutable ? $filtered_bounds['start'] : $start;
			$end = ($filtered_bounds['end'] ?? null) instanceof DateTimeImmutable ? $filtered_bounds['end'] : $end;
			$source = sanitize_key((string) ($filtered_bounds['source'] ?? $source));
		}
		if ($end <= $start) {
			$invalid['reason'] = 'invalid_filtered_operational_window';
			return $invalid;
		}

		$utc = new DateTimeZone('UTC');
		$result = array(
			'valid' => true,
			'event_plan_id' => $event_plan_id,
			'source' => $source !== '' ? $source : 'filtered',
			'reason' => '',
			'timezone' => $start->getTimezone()->getName(),
			'start_local' => $start->format('Y-m-d H:i:s'),
			'end_local' => $end->format('Y-m-d H:i:s'),
			'start_gmt' => $start->setTimezone($utc)->format('Y-m-d H:i:s'),
			'end_gmt' => $end->setTimezone($utc)->format('Y-m-d H:i:s'),
			'start_timestamp' => $start->getTimestamp(),
			'end_timestamp' => $end->getTimestamp(),
		);

		/**
		 * Add consumer-specific data to the final read-only operational-window contract.
		 * Canonical fields are restored after this filter.
		 *
		 * @param array<string,mixed> $result        Window result.
		 * @param int                 $event_plan_id Event Plan post ID.
		 */
		$filtered_result = apply_filters('bvmgr_event_plan_operational_window', $result, $event_plan_id);
		return is_array($filtered_result) ? array_merge($filtered_result, $result) : $result;
	}
}

if (!function_exists('bvmgr_operational_context_timestamp')) {
	function bvmgr_operational_context_timestamp($at = null): ?DateTimeImmutable
	{
		$timezone = wp_timezone();
		if ($at instanceof DateTimeInterface) {
			return (new DateTimeImmutable('@' . $at->getTimestamp()))->setTimezone($timezone);
		}
		if (is_int($at) || (is_string($at) && preg_match('/^\d+$/', $at))) {
			return (new DateTimeImmutable('@' . (int) $at))->setTimezone($timezone);
		}
		if (is_string($at) && trim($at) !== '') {
			return bvmgr_operational_parse_local_datetime(trim($at));
		}
		if ($at !== null) {
			return null;
		}

		return new DateTimeImmutable('now', $timezone);
	}
}

if (!function_exists('bvmgr_operational_event_plan_status')) {
	function bvmgr_operational_event_plan_status(int $event_plan_id): string
	{
		if (function_exists('bvmgr_event_plan_get_status')) {
			return sanitize_key((string) bvmgr_event_plan_get_status($event_plan_id, 'operational_context'));
		}
		if (function_exists('vms_event_plan_get_status')) {
			return sanitize_key((string) vms_event_plan_get_status($event_plan_id, 'operational_context'));
		}

		return sanitize_key((string) get_post_meta($event_plan_id, '_vms_event_plan_status', true));
	}
}

if (!function_exists('bvmgr_operational_event_context_candidate_ids')) {
	/** @return int[] */
	function bvmgr_operational_event_context_candidate_ids(int $venue_id, DateTimeImmutable $at, array $args = array()): array
	{
		if (isset($args['candidate_ids']) && is_array($args['candidate_ids'])) {
			$ids = $args['candidate_ids'];
		} else {
			$query_args = array(
				'post_type' => 'vms_event_plan',
				'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
				'posts_per_page' => -1,
				'fields' => 'ids',
				'no_found_rows' => true,
				'orderby' => 'ID',
				'order' => 'ASC',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Canonical resolution must inspect every Event Plan scoped to this venue so it cannot silently miss an active or explicitly overridden window.
					array(
						'key' => '_vms_venue_id',
						'value' => $venue_id,
						'compare' => '=',
						'type' => 'NUMERIC',
					),
				),
			);
			/**
			 * Filter the venue-scoped Event Plan query used by operational-context resolution.
			 *
			 * @param array<string,mixed> $query_args Query arguments.
			 * @param int                 $venue_id   Venue post ID.
			 * @param DateTimeImmutable   $at         Site-local timestamp.
			 */
			$filtered_query_args = apply_filters('bvmgr_operational_event_context_query_args', $query_args, $venue_id, $at);
			$ids = get_posts(is_array($filtered_query_args) ? $filtered_query_args : $query_args);
		}

		$ids = array_values(array_unique(array_filter(array_map('absint', (array) $ids))));
		/**
		 * Filter candidate Event Plan IDs before status, venue, and window validation.
		 *
		 * @param int[]             $ids      Candidate Event Plan IDs.
		 * @param int               $venue_id Venue post ID.
		 * @param DateTimeImmutable $at       Site-local timestamp.
		 * @param array             $args     Resolver arguments.
		 */
		$filtered_ids = apply_filters('bvmgr_operational_event_context_candidate_ids', $ids, $venue_id, $at, $args);
		return array_values(array_unique(array_filter(array_map('absint', is_array($filtered_ids) ? $filtered_ids : $ids))));
	}
}

if (!function_exists('bvmgr_resolve_operational_event_context')) {
	/**
	 * Resolve the operationally active Event Plan for a venue and timestamp.
	 *
	 * @return array<string,mixed> Status is none, single, or ambiguous.
	 */
	function bvmgr_resolve_operational_event_context(int $venue_id, $at = null, array $args = array()): array
	{
		$venue_id = absint($venue_id);
		$timestamp = bvmgr_operational_context_timestamp($at);
		$result = array(
			'status' => 'none',
			'reason' => 'invalid_context',
			'venue_id' => $venue_id,
			'at_local' => $timestamp instanceof DateTimeImmutable ? $timestamp->format('Y-m-d H:i:s') : '',
			'at_timestamp' => $timestamp instanceof DateTimeImmutable ? $timestamp->getTimestamp() : 0,
			'event_plan_id' => 0,
			'event_plan_ids' => array(),
			'candidates' => array(),
		);
		if ($venue_id <= 0 || !($timestamp instanceof DateTimeImmutable)) {
			return $result;
		}

		$allowed_statuses = array('ready', 'published', 'confirmed');
		/**
		 * Filter Event Plan workflow statuses eligible for operational resolution.
		 *
		 * @param string[]          $allowed_statuses Eligible status keys.
		 * @param int               $venue_id        Venue post ID.
		 * @param DateTimeImmutable $timestamp       Site-local timestamp.
		 */
		$filtered_statuses = apply_filters('bvmgr_operational_event_context_statuses', $allowed_statuses, $venue_id, $timestamp);
		$allowed_statuses = array_values(array_unique(array_filter(array_map('sanitize_key', is_array($filtered_statuses) ? $filtered_statuses : $allowed_statuses))));
		$allowed_statuses = array_values(array_diff($allowed_statuses, array('cancelled', 'canceled', 'archived')));

		$matches = array();
		foreach (bvmgr_operational_event_context_candidate_ids($venue_id, $timestamp, $args) as $event_plan_id) {
			if (function_exists('get_post_type') && get_post_type($event_plan_id) !== 'vms_event_plan') {
				continue;
			}
			if (absint(get_post_meta($event_plan_id, '_vms_venue_id', true)) !== $venue_id) {
				continue;
			}
			$status = bvmgr_operational_event_plan_status($event_plan_id);
			if (!in_array($status, $allowed_statuses, true)) {
				continue;
			}
			$window = bvmgr_event_plan_operational_window($event_plan_id);
			if (empty($window['valid'])) {
				continue;
			}
			$start_timestamp = (int) ($window['start_timestamp'] ?? 0);
			$end_timestamp = (int) ($window['end_timestamp'] ?? 0);
			$at_timestamp = $timestamp->getTimestamp();
			if ($start_timestamp <= 0 || $end_timestamp <= $start_timestamp || $at_timestamp < $start_timestamp || $at_timestamp >= $end_timestamp) {
				continue;
			}

			$matches[] = array(
				'event_plan_id' => $event_plan_id,
				'event_label' => function_exists('get_the_title') ? (string) get_the_title($event_plan_id) : '',
				'venue_id' => $venue_id,
				'status' => $status,
				'operational_start_local' => (string) ($window['start_local'] ?? ''),
				'operational_end_local' => (string) ($window['end_local'] ?? ''),
				'operational_start_gmt' => (string) ($window['start_gmt'] ?? ''),
				'operational_end_gmt' => (string) ($window['end_gmt'] ?? ''),
				'window_source' => sanitize_key((string) ($window['source'] ?? '')),
				'window' => $window,
			);
		}

		usort($matches, static function (array $left, array $right): int {
			$left_start = (int) ($left['window']['start_timestamp'] ?? 0);
			$right_start = (int) ($right['window']['start_timestamp'] ?? 0);
			return ($left_start <=> $right_start) ?: ((int) ($left['event_plan_id'] ?? 0) <=> (int) ($right['event_plan_id'] ?? 0));
		});

		$result['candidates'] = $matches;
		$result['event_plan_ids'] = array_values(array_map(static fn(array $row): int => (int) $row['event_plan_id'], $matches));
		if (count($matches) === 1) {
			$result['status'] = 'single';
			$result['reason'] = 'one_active_operational_window';
			$result['event_plan_id'] = (int) $matches[0]['event_plan_id'];
		} elseif (count($matches) > 1) {
			$result['status'] = 'ambiguous';
			$result['reason'] = 'overlapping_operational_windows';
		} else {
			$result['reason'] = 'no_active_operational_window';
		}

		/**
		 * Add consumer-specific data to the final operational Event Context result.
		 *
		 * Canonical fields are restored after this filter, so a consumer cannot
		 * silently collapse an ambiguous result to one Event Plan.
		 *
		 * @param array<string,mixed> $result    Resolver result.
		 * @param int                 $venue_id  Venue post ID.
		 * @param DateTimeImmutable   $timestamp Site-local timestamp.
		 * @param array               $args      Resolver arguments.
		 */
		$filtered_result = apply_filters('bvmgr_operational_event_context_result', $result, $venue_id, $timestamp, $args);
		return is_array($filtered_result) ? array_merge($filtered_result, $result) : $result;
	}
}
