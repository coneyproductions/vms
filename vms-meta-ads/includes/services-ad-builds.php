<?php

defined('ABSPATH') || exit;

if (!class_exists('VMS_Meta_Ads_Builds_Service')) {
	class VMS_Meta_Ads_Builds_Service {
		private const STATUS_ALLOWED = array('draft', 'exported', 'meta_created_paused', 'live', 'ended', 'error');
		private const MODE_ALLOWED = array('simple', 'autoramp');
		private const CREATIVE_ALLOWED = array('dark_post', 'boost_post', 'fb_event');
		private const GOAL_ALLOWED = array('traffic', 'engagement', 'leads', 'messages', 'event_responses');

		public static function get_build(int $id)
		{
			global $wpdb;
			$build = $wpdb->get_row(
				$wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'vms_meta_ads_builds WHERE id = %d', $id),
				ARRAY_A
			);
			if (!$build) {
				return new WP_Error('not_found', __('Build not found.', 'vms-meta-ads'), array('status' => 404));
			}
			$tiers = $wpdb->get_results(
				$wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'vms_meta_ads_tiers WHERE build_id = %d ORDER BY start_time ASC', $id),
				ARRAY_A
			);
			return self::hydrate_build($build, $tiers);
		}

		public static function list_builds(array $filters): array
		{
			global $wpdb;
			$where = array('1=1');
			$params = array();

			if (!empty($filters['event_plan_id'])) {
				$where[] = 'event_plan_id = %d';
				$params[] = absint($filters['event_plan_id']);
			}
			if (!empty($filters['venue_id'])) {
				$where[] = 'venue_id = %d';
				$params[] = absint($filters['venue_id']);
			}
			if (!empty($filters['status']) && in_array($filters['status'], self::STATUS_ALLOWED, true)) {
				$where[] = 'status = %s';
				$params[] = $filters['status'];
			}
			$limit = max(1, min(100, absint($filters['limit'] ?? 20)));

			$sql = 'SELECT * FROM ' . $wpdb->prefix . 'vms_meta_ads_builds WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT %d';
			$params[] = $limit;
			$rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
			if (!$rows) {
				return array();
			}

			$ids = wp_list_pluck($rows, 'id');
			$in = implode(',', array_fill(0, count($ids), '%d'));
			$tiers = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'vms_meta_ads_tiers WHERE build_id IN (' . $in . ') ORDER BY start_time ASC', $ids), ARRAY_A);
			$tiers_by_build = array();
			foreach ($tiers as $tier) {
				$build_id = (int) $tier['build_id'];
				if (!isset($tiers_by_build[$build_id])) {
					$tiers_by_build[$build_id] = array();
				}
				$tiers_by_build[$build_id][] = $tier;
			}

			$items = array();
			foreach ($rows as $row) {
				$build_id = (int) $row['id'];
				$items[] = self::hydrate_build($row, $tiers_by_build[$build_id] ?? array());
			}
			return $items;
		}

		public static function save_build(array $input)
		{
			$normalized = self::normalize_input($input);
			if (is_wp_error($normalized)) {
				return $normalized;
			}

			$payloads = self::build_payloads($normalized);
			if (is_wp_error($payloads)) {
				return $payloads;
			}

			global $wpdb;
			$table = $wpdb->prefix . 'vms_meta_ads_builds';
			$now = current_time('mysql', true);
			$build_id = absint($input['id'] ?? 0);
			$is_update = $build_id > 0;

			$row = array(
				'event_plan_id' => $normalized['event_plan_id'] ?: null,
				'venue_id' => $normalized['venue_id'] ?: null,
				'source_type' => $normalized['source_type'],
				'source_ref' => $normalized['source_ref'],
				'mode' => $normalized['mode'],
				'goal' => $normalized['goal'],
				'objective_meta' => $normalized['objective_meta'],
				'creative_mode' => $normalized['creative_mode'],
				'post_asset_id' => $normalized['post_asset_id'] ?: null,
				'destination_url' => $normalized['destination_url'],
				'utm_url' => $payloads['utm_url'],
				'copy_payload' => wp_json_encode($payloads['copy_payload']),
				'targeting_payload' => wp_json_encode($payloads['targeting_payload']),
				'budget_payload' => wp_json_encode($payloads['budget_payload']),
				'schedule_payload' => wp_json_encode($payloads['schedule_payload']),
				'placements_payload' => wp_json_encode($payloads['placements_payload']),
				'status' => 'draft',
				'updated_at' => $now,
			);

			$formats = array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');

			if ($is_update) {
				$existing = self::get_build($build_id);
				if (is_wp_error($existing)) {
					return $existing;
				}
				$updated = $wpdb->update($table, $row, array('id' => $build_id), $formats, array('%d'));
				if ($updated === false) {
					return new WP_Error('db_update_failed', __('Unable to update build.', 'vms-meta-ads'));
				}
				vms_ma_log('build_updated', array('build_id' => $build_id), $build_id);
			} else {
				$row['created_by'] = get_current_user_id();
				$row['created_at'] = $now;
				$formats[] = '%d';
				$formats[] = '%s';
				$inserted = $wpdb->insert($table, $row, $formats);
				if ($inserted === false) {
					return new WP_Error('db_insert_failed', __('Unable to create build.', 'vms-meta-ads'));
				}
				$build_id = (int) $wpdb->insert_id;
				vms_ma_log('build_created', array('build_id' => $build_id), $build_id);
			}

			$tier_result = self::replace_tiers($build_id, $payloads['tiers']);
			if (is_wp_error($tier_result)) {
				return $tier_result;
			}
			self::sync_event_plan_meta($normalized, $payloads, $build_id);

			return self::get_build($build_id);
		}

		public static function export_copy_pack(int $build_id)
		{
			$build = self::get_build($build_id);
			if (is_wp_error($build)) {
				return $build;
			}

			global $wpdb;
			$updated = $wpdb->update(
				$wpdb->prefix . 'vms_meta_ads_builds',
				array('status' => 'exported', 'updated_at' => current_time('mysql', true)),
				array('id' => $build_id),
				array('%s', '%s'),
				array('%d')
			);
			if ($updated === false) {
				return new WP_Error('db_export_failed', __('Unable to mark build exported.', 'vms-meta-ads'));
			}

			vms_ma_log('export_generated', array('build_id' => $build_id), $build_id);
			$build['status'] = 'exported';
			$event_plan_id = (int) ($build['event_plan_id'] ?? 0);
			if ($event_plan_id > 0) {
				update_post_meta($event_plan_id, 'vms_ma_last_exported_local', wp_date('Y-m-d H:i:s', null, wp_timezone()));
			}
			return array(
				'build' => $build,
				'copy_pack' => $build['copy_payload'],
			);
		}

		private static function hydrate_build(array $build, array $tiers): array
		{
			$json_fields = array('copy_payload', 'targeting_payload', 'budget_payload', 'schedule_payload', 'placements_payload', 'meta_ids');
			foreach ($json_fields as $field) {
				$build[$field] = !empty($build[$field]) ? json_decode((string) $build[$field], true) : array();
				if (!is_array($build[$field])) {
					$build[$field] = array();
				}
			}
			$build['id'] = (int) $build['id'];
			$build['event_plan_id'] = (int) $build['event_plan_id'];
			$build['venue_id'] = (int) $build['venue_id'];
			$build['post_asset_id'] = (int) $build['post_asset_id'];
			$build['created_by'] = (int) $build['created_by'];
			$build['tiers'] = array_map(static function (array $tier): array {
				$tier['id'] = (int) $tier['id'];
				$tier['build_id'] = (int) $tier['build_id'];
				$tier['budget_amount_minor'] = (int) $tier['budget_amount_minor'];
				return $tier;
			}, $tiers);
			return $build;
		}

		private static function normalize_input(array $input)
		{
			$mode = in_array($input['mode'] ?? '', self::MODE_ALLOWED, true) ? $input['mode'] : 'autoramp';
			$creative_mode = in_array($input['creative_mode'] ?? '', self::CREATIVE_ALLOWED, true) ? $input['creative_mode'] : 'dark_post';
			$goal = in_array($input['goal'] ?? '', self::GOAL_ALLOWED, true) ? $input['goal'] : 'traffic';
			$objective = sanitize_text_field((string) ($input['objective_meta'] ?? 'OUTCOME_TRAFFIC'));
			$source_type = sanitize_key((string) ($input['source_type'] ?? 'event_plan'));
			$source_ref = sanitize_text_field((string) ($input['source_ref'] ?? ''));
			$event_plan_id = absint($input['event_plan_id'] ?? 0);
			$venue_id = absint($input['venue_id'] ?? 0);
			$post_asset_id = absint($input['post_asset_id'] ?? 0);
			$destination_url = esc_url_raw((string) ($input['destination_url'] ?? ''));

			if (!$destination_url || !wp_http_validate_url($destination_url)) {
				return new WP_Error('invalid_destination_url', __('A valid destination URL is required.', 'vms-meta-ads'), array('status' => 400));
			}

			$event_start = sanitize_text_field((string) ($input['event_start'] ?? ''));
			if ($event_start === '') {
				$event_start = self::infer_event_start($event_plan_id);
			}
			if ($event_start === '') {
				return new WP_Error('missing_event_start', __('Event start is required to calculate schedule tiers.', 'vms-meta-ads'), array('status' => 400));
			}

			$radius = max(1, absint($input['radius_miles'] ?? 15));
			$age_min = max(13, absint($input['age_min'] ?? 21));
			$age_max = max($age_min, absint($input['age_max'] ?? 65));
			$interests_raw = sanitize_text_field((string) ($input['interests'] ?? ''));
			$interests = array_values(array_filter(array_map('trim', explode(',', $interests_raw))));
			$placements_mode = ($input['placements_mode'] ?? 'automatic') === 'manual' ? 'manual' : 'automatic';

			$total_budget_minor = absint($input['total_budget_minor'] ?? 0);
			if ($total_budget_minor < 1) {
				return new WP_Error('invalid_budget', __('Total budget must be greater than zero.', 'vms-meta-ads'), array('status' => 400));
			}

			$settings = VMS_Meta_Ads_Utils::get_settings();
			$max_lifetime = max(1, absint($settings['budget_max_lifetime_minor']));
			if ($total_budget_minor > $max_lifetime) {
				$max_lifetime_dollars = number_format_i18n($max_lifetime / 100, 2);
				return new WP_Error(
					'budget_over_max',
					sprintf(__('Total budget exceeds clamp of $%s.', 'vms-meta-ads'), $max_lifetime_dollars),
					array('status' => 400)
				);
			}

				$event_name = sanitize_text_field((string) ($input['event_name'] ?? self::infer_event_name($event_plan_id)));
				$venue_name = sanitize_text_field((string) ($input['venue_name'] ?? self::infer_venue_name($venue_id)));
				$preset_mode = sanitize_key((string) ($input['preset_mode'] ?? 'flat_run'));
				if (!in_array($preset_mode, array('flat_run', 'promo_bundle_30_14_7', 'simple_7_day', 'simple_14_day', 'simple_30_day', 'manual_dates'), true)) {
					$preset_mode = 'flat_run';
				}
				$optimization = sanitize_key((string) ($input['optimization'] ?? 'link_clicks'));
				if (!in_array($optimization, array('link_clicks', 'landing_page_views'), true)) {
					$optimization = 'link_clicks';
				}

				return array(
				'event_plan_id' => $event_plan_id,
				'venue_id' => $venue_id,
				'source_type' => $source_type,
				'source_ref' => $source_ref,
				'mode' => $mode,
				'goal' => $goal,
				'objective_meta' => $objective,
				'creative_mode' => $creative_mode,
				'post_asset_id' => $post_asset_id,
				'destination_url' => $destination_url,
				'event_start' => $event_start,
				'radius_miles' => $radius,
				'age_min' => $age_min,
				'age_max' => $age_max,
				'interests' => $interests,
				'placements_mode' => $placements_mode,
				'total_budget_minor' => $total_budget_minor,
				'event_name' => $event_name,
				'venue_name' => $venue_name,
					'tier_budgets' => is_array($input['tier_budgets'] ?? null) ? $input['tier_budgets'] : array(),
					'preset_mode' => $preset_mode,
					'optimization' => $optimization,
					'end_buffer_hours' => max(0, absint($input['end_buffer_hours'] ?? 2)),
					'manual_start' => sanitize_text_field((string) ($input['manual_start'] ?? '')),
					'manual_end' => sanitize_text_field((string) ($input['manual_end'] ?? '')),
					'primary_text' => sanitize_textarea_field((string) ($input['primary_text'] ?? '')),
				'headline' => sanitize_text_field((string) ($input['headline'] ?? '')),
				'description' => sanitize_text_field((string) ($input['description'] ?? '')),
				'fb_event_id' => sanitize_text_field((string) ($input['fb_event_id'] ?? '')),
			);
		}

		private static function build_payloads(array $normalized)
		{
			$tiers_result = self::build_tiers(
				$normalized['mode'],
				$normalized['preset_mode'],
				$normalized['event_start'],
				$normalized['total_budget_minor'],
				$normalized['tier_budgets'],
				$normalized['end_buffer_hours'],
				$normalized['manual_start'],
				$normalized['manual_end']
			);
			if (is_wp_error($tiers_result)) {
				return $tiers_result;
			}

			$event_dt = new DateTimeImmutable($normalized['event_start'], wp_timezone());
			$utm_url = self::build_utm_url($normalized['destination_url'], $normalized['venue_name'], $normalized['event_name'], $event_dt);
			$copy_pack = self::build_copy_pack($normalized, $tiers_result, $utm_url, $event_dt);

			return array(
				'utm_url' => $utm_url,
				'copy_payload' => $copy_pack,
				'targeting_payload' => array(
					'radius_miles' => $normalized['radius_miles'],
					'age_min' => $normalized['age_min'],
					'age_max' => $normalized['age_max'],
					'interests' => $normalized['interests'],
				),
				'budget_payload' => array(
					'total_budget_minor' => $normalized['total_budget_minor'],
					'tiers' => array_map(static function (array $tier): array {
						return array(
							'tier_key' => $tier['tier_key'],
							'budget_type' => $tier['budget_type'],
							'budget_amount_minor' => $tier['budget_amount_minor'],
						);
					}, $tiers_result),
				),
				'schedule_payload' => array(
					'event_start' => $event_dt->format('Y-m-d H:i:s'),
					'preset_mode' => $normalized['preset_mode'],
					'end_buffer_hours' => $normalized['end_buffer_hours'],
					'manual_start' => $normalized['manual_start'],
					'manual_end' => $normalized['manual_end'],
					'tiers' => array_map(static function (array $tier): array {
						return array(
							'tier_key' => $tier['tier_key'],
							'start_time' => $tier['start_time'],
							'end_time' => $tier['end_time'],
						);
					}, $tiers_result),
				),
				'placements_payload' => array(
					'mode' => $normalized['placements_mode'],
				),
				'tiers' => $tiers_result,
			);
		}

		private static function build_tiers(string $mode, string $preset_mode, string $event_start, int $total_budget_minor, array $tier_budgets, int $end_buffer_hours = 2, string $manual_start = '', string $manual_end = '')
		{
			$tz = wp_timezone();
			$event_dt = new DateTimeImmutable($event_start, $tz);
			$today = new DateTimeImmutable('now', $tz);
			if ($event_dt->format('Y-m-d') <= $today->format('Y-m-d')) {
				return new WP_Error('event_not_future', __('Event date must be in the future to create ads.', 'vms-meta-ads'), array('status' => 400));
			}

			$days_until = (int) $today->diff($event_dt)->format('%a');
			$tiers = array();
			$defaults = array(
				'd30' => 20,
				'd14' => 30,
				'd7' => 50,
			);

			$build_tier = static function (string $key, string $label, DateTimeImmutable $start, DateTimeImmutable $end, int $budget) {
				return array(
					'tier_key' => $key,
					'tier_label' => $label,
					'start_time' => $start->format('Y-m-d H:i:s'),
					'end_time' => $end->format('Y-m-d H:i:s'),
					'budget_type' => 'lifetime',
					'budget_amount_minor' => $budget,
				);
			};

			if ($preset_mode === 'manual_dates') {
				$manual_start_dt = null;
				$manual_end_dt = null;
				try {
					$manual_start_dt = $manual_start !== '' ? new DateTimeImmutable($manual_start, $tz) : null;
					$manual_end_dt = $manual_end !== '' ? new DateTimeImmutable($manual_end, $tz) : null;
				} catch (Exception $e) {
					$manual_start_dt = null;
					$manual_end_dt = null;
				}
				if (!($manual_start_dt instanceof DateTimeImmutable) || !($manual_end_dt instanceof DateTimeImmutable) || $manual_end_dt <= $manual_start_dt) {
					return new WP_Error('invalid_manual_dates', __('Manual start/end must be valid and end must be after start.', 'vms-meta-ads'), array('status' => 400));
				}
				$tiers[] = $build_tier('manual', __('Manual window', 'vms-meta-ads'), $manual_start_dt, $manual_end_dt, $total_budget_minor);
			} elseif ($preset_mode === 'flat_run') {
				$end = $event_dt->sub(new DateInterval('PT' . max(0, $end_buffer_hours) . 'H'));
				$tiers[] = $build_tier('flat_run', __('Flat run', 'vms-meta-ads'), $today, $end, $total_budget_minor);
			} elseif ($preset_mode === 'simple_7_day' || $preset_mode === 'simple_14_day' || $preset_mode === 'simple_30_day') {
				$days = 7;
				if ($preset_mode === 'simple_14_day') {
					$days = 14;
				} elseif ($preset_mode === 'simple_30_day') {
					$days = 30;
				}
				$start = $event_dt->sub(new DateInterval('P' . $days . 'D'));
				$end = $event_dt->sub(new DateInterval('PT' . max(0, $end_buffer_hours) . 'H'));
				if ($start < $today) {
					$start = $today;
				}
				$tiers[] = $build_tier('simple_' . $days, sprintf(__('Simple %dd', 'vms-meta-ads'), $days), $start, $end, $total_budget_minor);
			} elseif ($mode === 'simple') {
				$start = $today;
				$end = $event_dt->sub(new DateInterval('PT' . max(0, $end_buffer_hours) . 'H'));
				$tiers[] = $build_tier('promo', __('Promo', 'vms-meta-ads'), $start, $end, $total_budget_minor);
			} else {
				$end_dt = $event_dt->sub(new DateInterval('PT' . max(0, $end_buffer_hours) . 'H'));
				if ($days_until > 10) {
					$budget = self::resolve_tier_budget('d30', $total_budget_minor, $defaults, $tier_budgets);
					$tiers[] = $build_tier('d30', __('30d Awareness', 'vms-meta-ads'), $event_dt->sub(new DateInterval('P30D')), $event_dt->sub(new DateInterval('P15D')), $budget);
				}
				if ($days_until > 6) {
					$budget = self::resolve_tier_budget('d14', $total_budget_minor, $defaults, $tier_budgets);
					$tiers[] = $build_tier('d14', __('14d Intent', 'vms-meta-ads'), $event_dt->sub(new DateInterval('P14D')), $event_dt->sub(new DateInterval('P8D')), $budget);
				}
				$budget = self::resolve_tier_budget('d7', $total_budget_minor, $defaults, $tier_budgets);
				$tiers[] = $build_tier('d7', __('7d Urgency', 'vms-meta-ads'), $event_dt->sub(new DateInterval('P7D')), $end_dt, $budget);
			}

			if (empty($tiers)) {
				return new WP_Error('no_tiers', __('No valid tiers were produced from the selected event date.', 'vms-meta-ads'), array('status' => 400));
			}
			$tiers = array_values(array_filter($tiers, static function (array $tier) use ($today): bool {
				try {
					$start = new DateTimeImmutable($tier['start_time'], wp_timezone());
					$end = new DateTimeImmutable($tier['end_time'], wp_timezone());
				} catch (Exception $e) {
					return false;
				}
				if ($end <= $start) {
					return false;
				}
				return $end > $today;
			}));

			$settings = VMS_Meta_Ads_Utils::get_settings();
			$max_lifetime = absint($settings['budget_max_lifetime_minor']);
			$total_tier_budget = 0;
			foreach ($tiers as $tier) {
				if ($tier['budget_amount_minor'] < 1) {
					return new WP_Error('tier_budget_invalid', __('Each tier budget must be greater than zero.', 'vms-meta-ads'), array('status' => 400));
				}
				if ($tier['budget_amount_minor'] > $max_lifetime) {
					return new WP_Error('tier_budget_over_max', __('A tier budget exceeds the configured lifetime clamp.', 'vms-meta-ads'), array('status' => 400));
				}
				$total_tier_budget += (int) $tier['budget_amount_minor'];
			}

			if ($total_tier_budget !== $total_budget_minor) {
				$delta = $total_budget_minor - $total_tier_budget;
				$last = count($tiers) - 1;
				$tiers[$last]['budget_amount_minor'] += $delta;
			}

			return $tiers;
		}

		private static function resolve_tier_budget(string $key, int $total_budget_minor, array $defaults, array $tier_budgets): int
		{
			if (isset($tier_budgets[$key])) {
				return max(0, absint($tier_budgets[$key]));
			}
			$percent = $defaults[$key] ?? 0;
			return (int) floor(($total_budget_minor * $percent) / 100);
		}

		private static function replace_tiers(int $build_id, array $tiers)
		{
			global $wpdb;
			$table = $wpdb->prefix . 'vms_meta_ads_tiers';
			$deleted = $wpdb->delete($table, array('build_id' => $build_id), array('%d'));
			if ($deleted === false) {
				return new WP_Error('db_tier_delete_failed', __('Unable to replace tier rows.', 'vms-meta-ads'));
			}

			$now = current_time('mysql', true);
			foreach ($tiers as $tier) {
				$ok = $wpdb->insert(
					$table,
					array(
						'build_id' => $build_id,
						'tier_key' => $tier['tier_key'],
						'tier_label' => $tier['tier_label'],
						'start_time' => $tier['start_time'],
						'end_time' => $tier['end_time'],
						'budget_type' => $tier['budget_type'],
						'budget_amount_minor' => $tier['budget_amount_minor'],
						'created_at' => $now,
						'updated_at' => $now,
					),
					array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
				);
				if ($ok === false) {
					return new WP_Error('db_tier_insert_failed', __('Unable to save tier rows.', 'vms-meta-ads'));
				}
			}
			return true;
		}

		private static function build_utm_url(string $destination_url, string $venue_name, string $event_name, DateTimeImmutable $event_dt): string
		{
			$venue_slug = sanitize_title($venue_name ?: 'venue');
			$event_slug = sanitize_title($event_name ?: 'event');
			$campaign = $venue_slug . '_' . $event_slug . '_' . $event_dt->format('Ymd');

			$parts = wp_parse_url($destination_url);
			$query = array();
			if (!empty($parts['query'])) {
				parse_str($parts['query'], $query);
			}
			$query['utm_source'] = 'facebook';
			$query['utm_medium'] = 'paid_social';
			$query['utm_campaign'] = $campaign;

			$base = $destination_url;
			if (strpos($destination_url, '?') !== false) {
				$base = (string) strtok($destination_url, '?');
			}

			return $base . '?' . http_build_query($query);
		}

		private static function build_copy_pack(array $normalized, array $tiers, string $utm_url, DateTimeImmutable $event_dt): array
		{
			$venue_name = $normalized['venue_name'] ?: __('Venue', 'vms-meta-ads');
			$event_name = $normalized['event_name'] ?: __('Event', 'vms-meta-ads');
			$date_display = wp_date('M j, Y g:i A', $event_dt->getTimestamp(), wp_timezone());

			$campaign_name = sprintf('%s — %s — %s', $venue_name, $event_name, $event_dt->format('Y-m-d'));
			$primary_variants = array(
				sprintf('%s at %s on %s. Grab your tickets now.', $event_name, $venue_name, $date_display),
				sprintf('Don\'t miss %s at %s. Tickets are moving.', $event_name, $venue_name),
				sprintf('%s is coming up fast. Lock in your spot today.', $event_name),
			);
			$headline_variants = array(
				sprintf('%s Tickets Available', $event_name),
				sprintf('%s at %s', $event_name, $venue_name),
				'Reserve Your Spot Now',
			);

			if ($normalized['primary_text'] !== '') {
				array_unshift($primary_variants, $normalized['primary_text']);
				$primary_variants = array_slice(array_values(array_unique($primary_variants)), 0, 8);
			}
			if ($normalized['headline'] !== '') {
				array_unshift($headline_variants, $normalized['headline']);
				$headline_variants = array_slice(array_values(array_unique($headline_variants)), 0, 8);
			}

			$adset_names = array();
			$ad_names = array();
			foreach ($tiers as $tier) {
				$adset_names[] = self::adset_name_for_tier($tier['tier_key'], $event_dt);
				$ad_names[] = sprintf('%s — %s — V1', $normalized['creative_mode'], $tier['tier_key']);
			}

			return array(
				'campaign_name' => $campaign_name,
				'adset_names' => $adset_names,
				'ad_names' => $ad_names,
				'primary_text_variants' => $primary_variants,
				'headline_variants' => $headline_variants,
				'description_variants' => $normalized['description'] !== '' ? array($normalized['description']) : array(),
				'cta_suggestion' => 'LEARN_MORE',
				'destination_url' => $normalized['destination_url'],
				'utm_url' => $utm_url,
				'audience_recipe_summary' => sprintf(
					'Radius %dmi, age %d-%d%s',
					$normalized['radius_miles'],
					$normalized['age_min'],
					$normalized['age_max'],
					empty($normalized['interests']) ? '' : ', interests: ' . implode(', ', $normalized['interests'])
				),
				'budget_schedule_summary' => array_map(static function (array $tier): string {
					return sprintf(
						'%s: $%0.2f (%s to %s)',
						$tier['tier_label'],
						$tier['budget_amount_minor'] / 100,
						wp_date('M j g:i A', strtotime($tier['start_time']), wp_timezone()),
						wp_date('M j g:i A', strtotime($tier['end_time']), wp_timezone())
					);
				}, $tiers),
				'creative_mode' => $normalized['creative_mode'],
				'fb_event_id' => $normalized['fb_event_id'],
				);
			}

		private static function sync_event_plan_meta(array $normalized, array $payloads, int $build_id): void
		{
			$event_plan_id = (int) ($normalized['event_plan_id'] ?? 0);
			if ($event_plan_id < 1) {
				return;
			}

			$copy = (array) ($payloads['copy_payload'] ?? array());
			$schedule = (array) ($payloads['schedule_payload'] ?? array());
			$draft_json = array(
				'build_id' => $build_id,
				'event_plan_id' => $event_plan_id,
				'preset_mode' => (string) ($normalized['preset_mode'] ?? 'flat_run'),
				'optimization' => (string) ($normalized['optimization'] ?? 'link_clicks'),
				'goal' => (string) ($normalized['goal'] ?? 'traffic'),
				'end_buffer_hours' => (int) ($normalized['end_buffer_hours'] ?? 2),
				'manual_start' => (string) ($normalized['manual_start'] ?? ''),
				'manual_end' => (string) ($normalized['manual_end'] ?? ''),
				'total_budget_minor' => (int) ($normalized['total_budget_minor'] ?? 0),
				'updated_local' => wp_date('Y-m-d H:i:s', null, wp_timezone()),
			);

			update_post_meta($event_plan_id, 'vms_ma_last_draft_json', wp_json_encode($draft_json));
			update_post_meta($event_plan_id, 'vms_ma_last_copy_pack', self::copy_pack_to_text($copy));
			update_post_meta($event_plan_id, 'vms_ma_last_updated_local', wp_date('Y-m-d H:i:s', null, wp_timezone()));
			update_post_meta($event_plan_id, 'vms_ma_last_preset', (string) ($normalized['preset_mode'] ?? 'flat_run'));
			update_post_meta($event_plan_id, 'vms_ma_last_budget_cents', (int) ($normalized['total_budget_minor'] ?? 0));
			update_post_meta($event_plan_id, 'vms_ma_last_schedule_json', wp_json_encode($schedule));
		}

		private static function copy_pack_to_text(array $copy): string
		{
			$lines = array();
			$lines[] = 'Campaign: ' . (string) ($copy['campaign_name'] ?? '');
			$lines[] = 'Destination URL: ' . (string) ($copy['destination_url'] ?? '');
			$lines[] = 'UTM URL: ' . (string) ($copy['utm_url'] ?? '');
			$lines[] = 'Audience: ' . (string) ($copy['audience_recipe_summary'] ?? '');
			$lines[] = 'Budget + Schedule:';
			foreach ((array) ($copy['budget_schedule_summary'] ?? array()) as $row) {
				$lines[] = '- ' . (string) $row;
			}
			$lines[] = 'Primary text variants:';
			foreach ((array) ($copy['primary_text_variants'] ?? array()) as $row) {
				$lines[] = '- ' . (string) $row;
			}
			$lines[] = 'Headline variants:';
			foreach ((array) ($copy['headline_variants'] ?? array()) as $row) {
				$lines[] = '- ' . (string) $row;
			}
			return implode("\n", $lines);
		}

		private static function adset_name_for_tier(string $tier_key, DateTimeImmutable $event_dt): string
		{
			switch ($tier_key) {
				case 'd30':
					$label = '30d Awareness';
					break;
				case 'd14':
					$label = '14d Intent';
					break;
				case 'd7':
					$label = '7d Urgency';
					break;
				default:
					$label = 'Promo';
					break;
			}
			return $label . ' — ' . $event_dt->format('Y-m-d');
		}

		private static function infer_event_start(int $event_plan_id): string
		{
			if ($event_plan_id < 1) {
				return '';
			}
			$candidates = array(
				'_vms_event_start',
				'_vms_event_date',
				'_EventStartDate',
				'event_start',
			);
			foreach ($candidates as $meta_key) {
				$value = (string) get_post_meta($event_plan_id, $meta_key, true);
				if ($value !== '') {
					$ts = strtotime($value);
					if ($ts !== false) {
						return wp_date('Y-m-d H:i:s', $ts, wp_timezone());
					}
				}
			}
			$post = get_post($event_plan_id);
			if ($post && $post->post_date_gmt) {
				$ts = strtotime($post->post_date_gmt . ' GMT');
				if ($ts !== false) {
					return wp_date('Y-m-d H:i:s', $ts, wp_timezone());
				}
			}
			return '';
		}

		private static function infer_event_name(int $event_plan_id): string
		{
			if ($event_plan_id < 1) {
				return '';
			}
			$post = get_post($event_plan_id);
			return $post ? sanitize_text_field($post->post_title) : '';
		}

		private static function infer_venue_name(int $venue_id): string
		{
			if ($venue_id < 1) {
				return '';
			}
			$post = get_post($venue_id);
			return $post ? sanitize_text_field($post->post_title) : '';
		}
	}
}
