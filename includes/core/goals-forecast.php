<?php
defined('ABSPATH') || exit;

/**
 * Goals + Forecast + Event Profitability core services.
 *
 * This file is intentionally provider-neutral. It consumes provider helpers
 * (like Data Tools Square) only through function-exists checks.
 */

if (!function_exists('vms_goals_log')) {
	function vms_goals_log(string $message): void
	{
		error_log('[VMS Goals] ' . $message);
	}
}

if (!function_exists('vms_goals_table_name')) {
	function vms_goals_table_name(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'vms_goals';
	}
}

if (!function_exists('vms_goals_settings_option_key')) {
	function vms_goals_settings_option_key(): string
	{
		return 'vms_goals_settings';
	}
}

if (!function_exists('vms_goals_settings_defaults')) {
	function vms_goals_settings_defaults(): array
	{
		return array(
			'default_metric' => 'true_profit',
			'default_overhead_mode' => 'include_overhead',
			'default_allocation_mode' => 'even',
			'default_trailing_window_events' => 6,

			'headcount_default_door_mode' => 'percent',
			'headcount_default_door_percent' => 15,
			'headcount_default_door_count' => 0,
			'default_avg_ticket_price_cents' => 2000,

			'concessions_model_defaults' => array(
				'buyer_rate_pct' => 35,
				'avg_spend_cents' => 1800,
				'use_bucket_keys' => array(),
				'mode' => 'simple',
			),

			'overhead_rules' => array(
				'mode' => 'flat_per_event',
				'flat_per_event_cents' => 0,
				'per_attendee_cents' => 0,
				'percent_of_gross_bps' => 0,
			),

			// Bucket key -> component key (ticket|concessions|add_on|other)
			'provider_bucket_component_map' => array(),

			// If empty, provider detection is auto.
			'enabled_actuals_providers' => array(),
		);
	}
}

if (!function_exists('vms_goals_merge_settings_with_defaults')) {
	function vms_goals_merge_settings_with_defaults(array $saved): array
	{
		$defaults = vms_goals_settings_defaults();
		$merged = wp_parse_args($saved, $defaults);

		$saved_concessions = isset($saved['concessions_model_defaults']) && is_array($saved['concessions_model_defaults'])
			? $saved['concessions_model_defaults']
			: array();
		$merged['concessions_model_defaults'] = wp_parse_args($saved_concessions, $defaults['concessions_model_defaults']);

		$saved_overhead = isset($saved['overhead_rules']) && is_array($saved['overhead_rules'])
			? $saved['overhead_rules']
			: array();
		$merged['overhead_rules'] = wp_parse_args($saved_overhead, $defaults['overhead_rules']);

		return $merged;
	}
}

if (!function_exists('vms_goals_get_settings')) {
	function vms_goals_get_settings(): array
	{
		$raw = get_option(vms_goals_settings_option_key(), array());
		if (!is_array($raw)) {
			$raw = array();
		}
		return vms_goals_merge_settings_with_defaults($raw);
	}
}

if (!function_exists('vms_goals_sanitize_bucket_component_map')) {
	function vms_goals_sanitize_bucket_component_map($raw): array
	{
		$out = array();
		if (!is_array($raw)) {
			return $out;
		}

		$allowed = array('ticket', 'concessions', 'add_on', 'other');
		foreach ($raw as $bucket_key => $component) {
			$key = sanitize_key((string) $bucket_key);
			$component = sanitize_key((string) $component);
			if ($key === '' || !in_array($component, $allowed, true)) {
				continue;
			}
			$out[$key] = $component;
		}

		return $out;
	}
}

if (!function_exists('vms_goals_update_settings')) {
	function vms_goals_update_settings(array $incoming): array
	{
		$current = vms_goals_get_settings();
		$merged = vms_goals_merge_settings_with_defaults(array_merge($current, $incoming));

		$allowed_metric = array('true_profit', 'event_profit', 'gross_revenue');
		$metric = sanitize_key((string) ($merged['default_metric'] ?? 'true_profit'));
		if (!in_array($metric, $allowed_metric, true)) {
			$metric = 'true_profit';
		}
		$merged['default_metric'] = $metric;

		$overhead_mode = sanitize_key((string) ($merged['default_overhead_mode'] ?? 'include_overhead'));
		if (!in_array($overhead_mode, array('include_overhead', 'exclude_overhead'), true)) {
			$overhead_mode = 'include_overhead';
		}
		$merged['default_overhead_mode'] = $overhead_mode;

		$allocation_mode = sanitize_key((string) ($merged['default_allocation_mode'] ?? 'even'));
		if (!in_array($allocation_mode, array('even', 'weighted'), true)) {
			$allocation_mode = 'even';
		}
		$merged['default_allocation_mode'] = $allocation_mode;

		$merged['default_trailing_window_events'] = max(1, min(52, (int) ($merged['default_trailing_window_events'] ?? 6)));
		$merged['headcount_default_door_mode'] = in_array(
			sanitize_key((string) ($merged['headcount_default_door_mode'] ?? 'percent')),
			array('percent', 'count'),
			true
		) ? sanitize_key((string) $merged['headcount_default_door_mode']) : 'percent';
		$merged['headcount_default_door_percent'] = max(0, min(95, (int) ($merged['headcount_default_door_percent'] ?? 15)));
		$merged['headcount_default_door_count'] = max(0, (int) ($merged['headcount_default_door_count'] ?? 0));
		$merged['default_avg_ticket_price_cents'] = max(0, (int) ($merged['default_avg_ticket_price_cents'] ?? 2000));

		$concessions = isset($merged['concessions_model_defaults']) && is_array($merged['concessions_model_defaults'])
			? $merged['concessions_model_defaults']
			: array();
		$concessions['buyer_rate_pct'] = max(0, min(100, (int) ($concessions['buyer_rate_pct'] ?? 35)));
		$concessions['avg_spend_cents'] = max(0, (int) ($concessions['avg_spend_cents'] ?? 1800));
		$concessions['mode'] = in_array(sanitize_key((string) ($concessions['mode'] ?? 'simple')), array('simple', 'multi_bucket'), true)
			? sanitize_key((string) $concessions['mode'])
			: 'simple';
		$use_bucket_keys = isset($concessions['use_bucket_keys']) && is_array($concessions['use_bucket_keys'])
			? $concessions['use_bucket_keys']
			: array();
		$concessions['use_bucket_keys'] = array_values(array_unique(array_filter(array_map('sanitize_key', $use_bucket_keys))));
		$merged['concessions_model_defaults'] = $concessions;

		$overhead = isset($merged['overhead_rules']) && is_array($merged['overhead_rules'])
			? $merged['overhead_rules']
			: array();
		$overhead['mode'] = in_array(
			sanitize_key((string) ($overhead['mode'] ?? 'flat_per_event')),
			array('flat_per_event', 'per_attendee', 'percent_of_gross', 'hybrid'),
			true
		) ? sanitize_key((string) $overhead['mode']) : 'flat_per_event';
		$overhead['flat_per_event_cents'] = max(0, (int) ($overhead['flat_per_event_cents'] ?? 0));
		$overhead['per_attendee_cents'] = max(0, (int) ($overhead['per_attendee_cents'] ?? 0));
		$overhead['percent_of_gross_bps'] = max(0, min(10000, (int) ($overhead['percent_of_gross_bps'] ?? 0)));
		$merged['overhead_rules'] = $overhead;

		$merged['provider_bucket_component_map'] = vms_goals_sanitize_bucket_component_map($merged['provider_bucket_component_map'] ?? array());

		$providers = isset($merged['enabled_actuals_providers']) && is_array($merged['enabled_actuals_providers'])
			? $merged['enabled_actuals_providers']
			: array();
		$merged['enabled_actuals_providers'] = array_values(array_unique(array_filter(array_map('sanitize_key', $providers))));

		update_option(vms_goals_settings_option_key(), $merged, false);
		return $merged;
	}
}

if (!function_exists('vms_goals_parse_money_to_cents')) {
	function vms_goals_parse_money_to_cents($value): int
	{
		if (is_int($value)) {
			return max(0, $value);
		}
		if (is_float($value)) {
			return max(0, (int) round($value * 100));
		}

		$raw = trim((string) $value);
		if ($raw === '') {
			return 0;
		}
		$raw = str_replace(array('$', ',', ' '), '', $raw);
		if ($raw === '' || !is_numeric($raw)) {
			return 0;
		}

		return max(0, (int) round(((float) $raw) * 100));
	}
}

if (!function_exists('vms_goals_parse_local_datetime')) {
	function vms_goals_parse_local_datetime(string $value): ?DateTimeImmutable
	{
		$value = trim(str_replace('T', ' ', $value));
		if ($value === '') {
			return null;
		}

		$tz = wp_timezone();
		$formats = array('Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d');
		foreach ($formats as $format) {
			$dt = DateTimeImmutable::createFromFormat($format, $value, $tz);
			$errors = DateTimeImmutable::getLastErrors();
			$count = is_array($errors) ? ((int) $errors['warning_count'] + (int) $errors['error_count']) : 0;
			if ($dt instanceof DateTimeImmutable && $count === 0) {
				if ($format === 'Y-m-d') {
					return new DateTimeImmutable($value . ' 00:00:00', $tz);
				}
				return $dt;
			}
		}
		return null;
	}
}

if (!function_exists('vms_goals_compute_period_range')) {
	function vms_goals_compute_period_range(string $period_type, string $custom_start = '', string $custom_end = ''): array
	{
		$period_type = sanitize_key($period_type);
		$tz = wp_timezone();
		$now = new DateTimeImmutable('now', $tz);
		$start = null;
		$end = null;

		if ($period_type === 'year') {
			$start = new DateTimeImmutable($now->format('Y') . '-01-01 00:00:00', $tz);
			$end = $start->modify('+1 year');
		} elseif ($period_type === 'quarter') {
			$month = (int) $now->format('n');
			$q_start_month = (int) (floor(($month - 1) / 3) * 3) + 1;
			$start = new DateTimeImmutable($now->format('Y') . '-' . sprintf('%02d', $q_start_month) . '-01 00:00:00', $tz);
			$end = $start->modify('+3 months');
		} elseif ($period_type === 'month') {
			$start = new DateTimeImmutable($now->format('Y-m-01 00:00:00'), $tz);
			$end = $start->modify('+1 month');
		} elseif ($period_type === 'week') {
			$dow = (int) $now->format('N'); // 1-7
			$start = (new DateTimeImmutable($now->format('Y-m-d 00:00:00'), $tz))->modify('-' . ($dow - 1) . ' days');
			$end = $start->modify('+7 days');
		} else {
			$period_type = 'custom';
			$parsed_start = vms_goals_parse_local_datetime($custom_start);
			$parsed_end = vms_goals_parse_local_datetime($custom_end);
			$start = $parsed_start ?: new DateTimeImmutable($now->format('Y-m-d 00:00:00'), $tz);
			$end = $parsed_end ?: $start->modify('+1 month');
		}

		if (!($start instanceof DateTimeImmutable) || !($end instanceof DateTimeImmutable) || $end <= $start) {
			$start = new DateTimeImmutable($now->format('Y-m-01 00:00:00'), $tz);
			$end = $start->modify('+1 month');
			$period_type = 'month';
		}

		return array(
			'period_type' => $period_type,
			'start_local' => $start->format('Y-m-d H:i:s'),
			'end_local' => $end->format('Y-m-d H:i:s'),
		);
	}
}

if (!function_exists('vms_goals_normalize_goal_payload')) {
	function vms_goals_normalize_goal_payload(array $input): array
	{
		$name = sanitize_text_field((string) ($input['name'] ?? ''));
		if ($name === '') {
			$name = 'Untitled Goal';
		}

		$metric = sanitize_key((string) ($input['metric'] ?? 'true_profit'));
		if (!in_array($metric, array('true_profit', 'event_profit', 'gross_revenue'), true)) {
			$metric = 'true_profit';
		}

		$period_type = sanitize_key((string) ($input['period_type'] ?? 'month'));
		if (!in_array($period_type, array('year', 'quarter', 'month', 'week', 'custom'), true)) {
			$period_type = 'month';
		}

		$range = vms_goals_compute_period_range(
			$period_type,
			(string) ($input['period_start_local'] ?? ''),
			(string) ($input['period_end_local'] ?? '')
		);

		$allocation_mode = sanitize_key((string) ($input['allocation_mode'] ?? 'even'));
		if (!in_array($allocation_mode, array('even', 'weighted'), true)) {
			$allocation_mode = 'even';
		}

		$weight_mode = sanitize_key((string) ($input['weight_mode'] ?? 'none'));
		if (!in_array($weight_mode, array('none', 'forecast_headcount', 'forecast_revenue'), true)) {
			$weight_mode = 'none';
		}

		$target_cents = vms_goals_parse_money_to_cents($input['target_cents'] ?? 0);
		$venue_id = isset($input['venue_id']) ? absint($input['venue_id']) : 0;
		$is_active = !empty($input['is_active']) ? 1 : 0;

		return array(
			'name' => $name,
			'metric' => $metric,
			'period_type' => $range['period_type'],
			'period_start_local' => $range['start_local'],
			'period_end_local' => $range['end_local'],
			'target_cents' => $target_cents,
			'allocation_mode' => $allocation_mode,
			'weight_mode' => $weight_mode,
			'venue_id' => $venue_id,
			'is_active' => $is_active,
		);
	}
}

if (!function_exists('vms_goals_list')) {
	function vms_goals_list(): array
	{
		global $wpdb;
		$table = vms_goals_table_name();
		$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
		if ((string) $exists !== (string) $table) {
			return array();
		}

		$rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY is_active DESC, updated_at_utc DESC, id DESC", ARRAY_A);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('vms_goals_get_goal')) {
	function vms_goals_get_goal(int $goal_id): array
	{
		global $wpdb;
		if ($goal_id <= 0) {
			return array();
		}
		$table = vms_goals_table_name();
		$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $goal_id), ARRAY_A);
		return is_array($row) ? $row : array();
	}
}

if (!function_exists('vms_goals_get_active_goal')) {
	function vms_goals_get_active_goal(): array
	{
		global $wpdb;
		$table = vms_goals_table_name();
		$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
		if ((string) $exists !== (string) $table) {
			return array();
		}
		$row = $wpdb->get_row("SELECT * FROM {$table} WHERE is_active = 1 ORDER BY updated_at_utc DESC, id DESC LIMIT 1", ARRAY_A);
		return is_array($row) ? $row : array();
	}
}

if (!function_exists('vms_goals_save_goal')) {
	function vms_goals_save_goal(array $input, int $goal_id = 0): array
	{
		global $wpdb;
		$table = vms_goals_table_name();
		$payload = vms_goals_normalize_goal_payload($input);
		$now = gmdate('Y-m-d H:i:s');

		$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
		if ((string) $exists !== (string) $table) {
			return array('ok' => false, 'message' => 'Goals table is unavailable.');
		}

		if ($goal_id > 0) {
			$ok = $wpdb->update(
				$table,
				array_merge($payload, array('updated_at_utc' => $now)),
				array('id' => $goal_id),
				array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s'),
				array('%d')
			);
			if ($ok === false) {
				return array('ok' => false, 'message' => 'Failed to update goal.');
			}
			$saved_id = $goal_id;
		} else {
			$ok = $wpdb->insert(
				$table,
				array_merge($payload, array('created_at_utc' => $now, 'updated_at_utc' => $now)),
				array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s')
			);
			if ($ok === false) {
				return array('ok' => false, 'message' => 'Failed to create goal.');
			}
			$saved_id = (int) $wpdb->insert_id;
		}

		if (!empty($payload['is_active'])) {
			$wpdb->query($wpdb->prepare("UPDATE {$table} SET is_active = 0 WHERE id <> %d", $saved_id));
		}

		return array('ok' => true, 'goal_id' => $saved_id, 'message' => 'Goal saved.');
	}
}

if (!function_exists('vms_goals_delete_goal')) {
	function vms_goals_delete_goal(int $goal_id): bool
	{
		global $wpdb;
		if ($goal_id <= 0) {
			return false;
		}
		$table = vms_goals_table_name();
		$deleted = $wpdb->delete($table, array('id' => $goal_id), array('%d'));
		return $deleted !== false;
	}
}

if (!function_exists('vms_goals_set_active_goal')) {
	function vms_goals_set_active_goal(int $goal_id): bool
	{
		global $wpdb;
		if ($goal_id <= 0) {
			return false;
		}
		$table = vms_goals_table_name();
		$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
		if ((string) $exists !== (string) $table) {
			return false;
		}

		$wpdb->query("UPDATE {$table} SET is_active = 0");
		$updated = $wpdb->update(
			$table,
			array(
				'is_active' => 1,
				'updated_at_utc' => gmdate('Y-m-d H:i:s'),
			),
			array('id' => $goal_id),
			array('%d', '%s'),
			array('%d')
		);
		return $updated !== false;
	}
}

if (!function_exists('vms_pos_provider_detect')) {
	function vms_pos_provider_detect(): array
	{
		$providers = array();
		if (function_exists('vms_square_get_event_actuals')) {
			$providers['square'] = array(
				'slug' => 'square',
				'label' => 'Square',
			);
		}
		return $providers;
	}
}

if (!function_exists('vms_goals_component_map_for_buckets')) {
	function vms_goals_component_map_for_buckets(): array
	{
		$settings = vms_goals_get_settings();
		$map = isset($settings['provider_bucket_component_map']) && is_array($settings['provider_bucket_component_map'])
			? $settings['provider_bucket_component_map']
			: array();
		return vms_goals_sanitize_bucket_component_map($map);
	}
}

if (!function_exists('vms_goals_provider_has_hard_errors')) {
	function vms_goals_provider_has_hard_errors(string $provider, array $raw, array $errors): bool
	{
		$provider = sanitize_key($provider);
		if ($provider === 'square' && function_exists('vms_square_actuals_has_hard_errors')) {
			try {
				return (bool) vms_square_actuals_has_hard_errors($raw);
			} catch (Throwable $e) {
				vms_goals_log('Hard-error check failed (square): ' . $e->getMessage());
			}
		}

		return !empty($errors);
	}
}

if (!function_exists('vms_goals_normalize_provider_actuals')) {
	function vms_goals_normalize_provider_actuals(string $provider, array $raw): array
	{
		$errors = array();
		$meta = isset($raw['meta']) && is_array($raw['meta']) ? $raw['meta'] : array();
		if (!empty($meta['errors']) && is_array($meta['errors'])) {
			$errors = array_values(array_filter(array_map('strval', $meta['errors'])));
		}

		$totals = isset($raw['totals']) && is_array($raw['totals']) ? $raw['totals'] : array();
		$gross = max(0, (int) ($totals['gross'] ?? 0));

		$buckets = isset($raw['buckets']) && is_array($raw['buckets']) ? $raw['buckets'] : array();
		$component_map = vms_goals_component_map_for_buckets();
		$ticket = 0;
		$concessions = 0;
		$add_on = 0;
		$other = 0;

		foreach ($buckets as $bucket_key => $bucket_totals) {
			if (!is_array($bucket_totals)) {
				continue;
			}
			$bucket_key = sanitize_key((string) $bucket_key);
			$bucket_gross = max(0, (int) ($bucket_totals['gross'] ?? 0));
			$component = isset($component_map[$bucket_key]) ? $component_map[$bucket_key] : '';
			if ($component === '') {
				$component = ($bucket_key === 'door') ? 'ticket' : 'concessions';
			}

			if ($component === 'ticket') {
				$ticket += $bucket_gross;
			} elseif ($component === 'add_on') {
				$add_on += $bucket_gross;
			} elseif ($component === 'other') {
				$other += $bucket_gross;
			} else {
				$concessions += $bucket_gross;
			}
		}

		$sum_components = $ticket + $concessions + $add_on + $other;
		if ($gross <= 0) {
			$gross = $sum_components;
		} elseif ($sum_components < $gross) {
			$other += ($gross - $sum_components);
		}

		$out_totals = array(
			'gross_revenue_cents' => $gross,
			'ticket_revenue_cents' => $ticket,
			'add_on_revenue_cents' => $add_on,
			'concessions_revenue_cents' => $concessions,
			'other_revenue_cents' => $other,
			'direct_costs_cents' => 0,
			'processing_fees_cents' => 0,
			'overhead_allocated_cents' => 0,
			'true_profit_cents' => $gross,
		);

		$has_hard_errors = vms_goals_provider_has_hard_errors($provider, $raw, $errors);

		return array(
			'ok' => !$has_hard_errors,
			'provider' => $provider,
			'pulled_at_utc' => (string) ($meta['pulled_at_utc'] ?? gmdate('Y-m-d H:i:s')),
			'errors' => $errors,
			'totals' => $out_totals,
			'raw' => $raw,
		);
	}
}

if (!function_exists('vms_pos_get_event_actuals')) {
	function vms_pos_get_event_actuals(int $event_plan_id, array $args = array()): array
	{
		$providers = vms_pos_provider_detect();
		if (empty($providers)) {
			return array(
				'ok' => false,
				'provider' => 'none',
				'errors' => array('No POS actuals provider available.'),
				'totals' => array(),
			);
		}

		$settings = vms_goals_get_settings();
		$enabled = isset($settings['enabled_actuals_providers']) && is_array($settings['enabled_actuals_providers'])
			? $settings['enabled_actuals_providers']
			: array();
		$enabled = array_values(array_unique(array_filter(array_map('sanitize_key', $enabled))));

		$order = array_keys($providers);
		if (!empty($enabled)) {
			$filtered = array();
			foreach ($enabled as $slug) {
				if (isset($providers[$slug])) {
					$filtered[] = $slug;
				}
			}
			if (!empty($filtered)) {
				$order = $filtered;
			}
		}

		foreach ($order as $slug) {
			if ($slug === 'square' && function_exists('vms_square_get_event_actuals')) {
				try {
					$raw = vms_square_get_event_actuals($event_plan_id, $args);
					if (!is_array($raw)) {
						continue;
					}
					return vms_goals_normalize_provider_actuals('square', $raw);
				} catch (Throwable $e) {
					vms_goals_log('Provider call failed (square): ' . $e->getMessage());
					return array(
						'ok' => false,
						'provider' => 'square',
						'errors' => array('Square provider error: ' . $e->getMessage()),
						'totals' => array(),
					);
				}
			}
		}

		return array(
			'ok' => false,
			'provider' => 'none',
			'errors' => array('No enabled provider could return actuals.'),
			'totals' => array(),
		);
	}
}

if (!function_exists('vms_goals_refresh_event_actuals')) {
	function vms_goals_refresh_event_actuals(int $event_plan_id, array $args = array()): array
	{
		if ($event_plan_id <= 0) {
			return array('ok' => false, 'message' => 'Invalid event plan id.');
		}

		$result = vms_pos_get_event_actuals($event_plan_id, $args);
		if (empty($result['ok'])) {
			$msg = !empty($result['errors']) ? implode(' | ', (array) $result['errors']) : 'Provider returned no data.';
			vms_goals_log('Actuals refresh failed for event ' . $event_plan_id . ': ' . $msg);
			return array('ok' => false, 'message' => $msg, 'data' => $result);
		}

		$incoming_totals = isset($result['totals']) && is_array($result['totals']) ? $result['totals'] : array();
		$existing_totals = vms_goals_get_manual_event_actual_totals($event_plan_id);
		$totals = $existing_totals;

		foreach (array('gross_revenue_cents', 'ticket_revenue_cents', 'add_on_revenue_cents', 'concessions_revenue_cents', 'other_revenue_cents') as $revenue_key) {
			if (array_key_exists($revenue_key, $incoming_totals)) {
				$totals[$revenue_key] = max(0, (int) $incoming_totals[$revenue_key]);
			}
		}

		$gross = max(0, (int) ($totals['gross_revenue_cents'] ?? 0));
		$direct = max(0, (int) ($totals['direct_costs_cents'] ?? 0));
		$fees = max(0, (int) ($totals['processing_fees_cents'] ?? 0));
		$overhead = max(0, (int) ($totals['overhead_allocated_cents'] ?? 0));
		$totals['true_profit_cents'] = (int) ($gross - $direct - $fees - $overhead);

		$pulled_at = (string) ($result['pulled_at_utc'] ?? gmdate('Y-m-d H:i:s'));
		$provider = sanitize_key((string) ($result['provider'] ?? 'none'));

		update_post_meta($event_plan_id, '_vms_event_actuals_totals', $totals);
		update_post_meta($event_plan_id, '_vms_event_actuals_pulled_at_utc', $pulled_at);
		update_post_meta($event_plan_id, '_vms_event_actuals_provider', $provider);

		if (array_key_exists('concessions_revenue_cents', $totals)) {
			update_post_meta($event_plan_id, '_vms_concessions_actual_cents', (int) $totals['concessions_revenue_cents']);
			update_post_meta($event_plan_id, '_vms_concessions_actual_source', 'provider');
		}

		$result['totals'] = $totals;
		$warning_msg = '';
		if (!empty($result['errors'])) {
			$warning_msg = ' Warnings: ' . implode(' | ', array_values(array_filter(array_map('strval', (array) $result['errors']))));
		}

		return array(
			'ok' => true,
			'message' => 'Actuals refreshed from provider.' . $warning_msg,
			'data' => $result,
		);
	}
}

if (!function_exists('vms_goals_get_ticket_stats')) {
	function vms_goals_get_ticket_stats(int $event_plan_id): array
	{
		$key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'ticket_stats') : '_vms_ticket_stats_v1';
		if ($key === '') {
			$key = '_vms_ticket_stats_v1';
		}

		$raw = get_post_meta($event_plan_id, $key, true);
		if (!is_array($raw)) {
			$raw = array();
		}

		$qty = 0;
		if (array_key_exists('qty_sold', $raw) && is_numeric($raw['qty_sold'])) {
			$qty = max(0, (int) $raw['qty_sold']);
		} elseif (array_key_exists('qty', $raw) && is_numeric($raw['qty'])) {
			$qty = max(0, (int) $raw['qty']);
		}

		$revenue_cents = 0;
		if (array_key_exists('revenue_cents', $raw) && is_numeric($raw['revenue_cents'])) {
			$revenue_cents = max(0, (int) $raw['revenue_cents']);
		} elseif (array_key_exists('revenue', $raw) && is_numeric($raw['revenue'])) {
			$revenue_cents = max(0, (int) round(((float) $raw['revenue']) * 100));
		}

		return array(
			'qty_sold' => $qty,
			'revenue_cents' => $revenue_cents,
		);
	}
}

if (!function_exists('vms_goals_estimate_door_count')) {
	function vms_goals_estimate_door_count(int $presale_count, string $mode, int $percent, int $count): int
	{
		$presale_count = max(0, $presale_count);
		$mode = sanitize_key($mode);
		if ($mode === 'count') {
			return max(0, $count);
		}

		$percent = max(0, min(95, $percent));
		if ($percent <= 0 || $presale_count <= 0) {
			return 0;
		}

		$p = $percent / 100.0;
		$door = (int) round(($presale_count / max(0.01, (1.0 - $p))) - $presale_count);
		return max(0, $door);
	}
}

if (!function_exists('vms_goals_get_headcount_for_mode')) {
	function vms_goals_get_headcount_for_mode(int $event_plan_id, string $mode, array $args = array()): int
	{
		$settings = vms_goals_get_settings();
		$ticket_stats = vms_goals_get_ticket_stats($event_plan_id);
		$presale = (int) ($ticket_stats['qty_sold'] ?? 0);

		$forecast = max(0, (int) get_post_meta($event_plan_id, '_vms_forecast_headcount', true));
		$true = max(0, (int) get_post_meta($event_plan_id, '_vms_true_headcount', true));

		$door_mode = (string) get_post_meta($event_plan_id, '_vms_door_sales_mode', true);
		if (!in_array($door_mode, array('percent', 'count'), true)) {
			$door_mode = (string) ($settings['headcount_default_door_mode'] ?? 'percent');
		}

		$door_percent = (int) get_post_meta($event_plan_id, '_vms_door_sales_percent', true);
		if ($door_percent <= 0) {
			$door_percent = (int) ($settings['headcount_default_door_percent'] ?? 15);
		}
		$door_count = (int) get_post_meta($event_plan_id, '_vms_door_sales_count', true);
		if ($door_count <= 0) {
			$door_count = (int) ($settings['headcount_default_door_count'] ?? 0);
		}

		$comp_forecast = max(0, (int) get_post_meta($event_plan_id, '_vms_comp_headcount_forecast', true));
		$comp_true = max(0, (int) get_post_meta($event_plan_id, '_vms_comp_headcount_true', true));

		$mode = sanitize_key($mode);
		if ($mode === 'true') {
			if ($true > 0) {
				return $true + $comp_true;
			}
			$door_est = vms_goals_estimate_door_count($presale, $door_mode, $door_percent, $door_count);
			return max($forecast, $presale + $door_est + $comp_forecast);
		}

		if ($mode === 'ticketed') {
			$door_est = vms_goals_estimate_door_count($presale, $door_mode, $door_percent, $door_count);
			$ticketed = $presale + $door_est + $comp_forecast;
			return max($ticketed, $forecast);
		}

		// Forecast mode
		if (!empty($args['headcount_override'])) {
			return max(0, (int) $args['headcount_override']);
		}
		if ($forecast > 0) {
			return $forecast + $comp_forecast;
		}

		$door_est = vms_goals_estimate_door_count($presale, $door_mode, $door_percent, $door_count);
		return $presale + $door_est + $comp_forecast;
	}
}

if (!function_exists('vms_goals_get_overhead_allocated_cents')) {
	function vms_goals_get_overhead_allocated_cents(int $gross_cents, int $headcount, array $overhead_rules): int
	{
		$gross_cents = max(0, $gross_cents);
		$headcount = max(0, $headcount);
		$mode = sanitize_key((string) ($overhead_rules['mode'] ?? 'flat_per_event'));
		$flat = max(0, (int) ($overhead_rules['flat_per_event_cents'] ?? 0));
		$per_attendee = max(0, (int) ($overhead_rules['per_attendee_cents'] ?? 0));
		$bps = max(0, (int) ($overhead_rules['percent_of_gross_bps'] ?? 0));

		if ($mode === 'per_attendee') {
			return $headcount * $per_attendee;
		}
		if ($mode === 'percent_of_gross') {
			return (int) round($gross_cents * ($bps / 10000));
		}
		if ($mode === 'hybrid') {
			return $flat + ($headcount * $per_attendee) + (int) round($gross_cents * ($bps / 10000));
		}
		return $flat;
	}
}

if (!function_exists('vms_goals_get_manual_event_actual_totals')) {
	function vms_goals_get_manual_event_actual_totals(int $event_plan_id): array
	{
		$raw = get_post_meta($event_plan_id, '_vms_event_actuals_totals', true);
		if (!is_array($raw)) {
			$raw = array();
		}

		$keys = array(
			'gross_revenue_cents',
			'ticket_revenue_cents',
			'add_on_revenue_cents',
			'concessions_revenue_cents',
			'other_revenue_cents',
			'direct_costs_cents',
			'processing_fees_cents',
			'overhead_allocated_cents',
			'true_profit_cents',
		);

		$out = array();
		foreach ($keys as $key) {
			$val = (int) ($raw[$key] ?? 0);
			$out[$key] = ($key === 'true_profit_cents') ? $val : max(0, $val);
		}
		return $out;
	}
}

if (!function_exists('vms_goals_get_default_direct_costs_cents')) {
	function vms_goals_get_default_direct_costs_cents(int $event_plan_id): int
	{
		$manual = get_post_meta($event_plan_id, '_vms_event_direct_costs_cents', true);
		if ($manual !== '' && is_numeric($manual)) {
			return max(0, (int) $manual);
		}

			$comp_structure = sanitize_key((string) get_post_meta($event_plan_id, '_vms_comp_structure', true));
			if (in_array($comp_structure, array('flat', 'flat_fee', 'flat_fee_door_split', 'attendance_bonus'), true)) {
				$flat = get_post_meta($event_plan_id, '_vms_flat_fee_amount', true);
				if ($flat !== '' && is_numeric($flat)) {
					$total_cents = max(0, (int) round(((float) $flat) * 100));
					$commission_percent = get_post_meta($event_plan_id, '_vms_commission_percent', true);
					$commission_mode = sanitize_key((string) get_post_meta($event_plan_id, '_vms_commission_mode', true));
					if (!in_array($commission_mode, array('artist_fee', 'gross'), true)) {
						$commission_mode = 'artist_fee';
					}
					if ($commission_percent !== '' && is_numeric($commission_percent) && (float) $commission_percent > 0 && $commission_mode === 'artist_fee') {
						$commission_amount = function_exists('vms_calculate_agent_fee_amount')
							? vms_calculate_agent_fee_amount((float) $flat, (float) $commission_percent, $commission_mode)
							: (((float) $flat) * ((float) $commission_percent / 100));
						$total_cents += max(0, (int) round(((float) $commission_amount) * 100));
					}
					return $total_cents;
			}
		}

		return 0;
	}
}

if (!function_exists('vms_goals_get_default_processing_fees_cents')) {
	function vms_goals_get_default_processing_fees_cents(int $event_plan_id): int
	{
		$manual = get_post_meta($event_plan_id, '_vms_event_processing_fees_cents', true);
		if ($manual !== '' && is_numeric($manual)) {
			return max(0, (int) $manual);
		}
		return 0;
	}
}

if (!function_exists('vms_goals_get_event_pnl')) {
	function vms_goals_get_event_pnl(int $event_plan_id, array $args = array()): array
	{
		$settings = vms_goals_get_settings();
		$mode = sanitize_key((string) ($args['headcount_mode'] ?? 'forecast'));
		if (!in_array($mode, array('forecast', 'ticketed', 'true'), true)) {
			$mode = 'forecast';
		}

		$include_overhead = array_key_exists('include_overhead', $args)
			? (bool) $args['include_overhead']
			: ((string) ($settings['default_overhead_mode'] ?? 'include_overhead') === 'include_overhead');

		$headcount = vms_goals_get_headcount_for_mode($event_plan_id, $mode, $args);
		$ticket_stats = vms_goals_get_ticket_stats($event_plan_id);
		$avg_ticket_price = max(0, (int) ($settings['default_avg_ticket_price_cents'] ?? 2000));
		if ((int) ($ticket_stats['qty_sold'] ?? 0) > 0 && (int) ($ticket_stats['revenue_cents'] ?? 0) > 0) {
			$avg_ticket_price = (int) round(((int) $ticket_stats['revenue_cents']) / max(1, (int) $ticket_stats['qty_sold']));
		}

		$manual_actuals = vms_goals_get_manual_event_actual_totals($event_plan_id);
		$has_manual_actuals = false;
		foreach ($manual_actuals as $v) {
			if ((int) $v > 0) {
				$has_manual_actuals = true;
				break;
			}
		}

		$ticket_revenue = 0;
		$add_on_revenue = 0;
		$concessions_revenue = 0;
		$other_revenue = 0;

		if ($mode === 'true' && $has_manual_actuals) {
			$ticket_revenue = (int) ($manual_actuals['ticket_revenue_cents'] ?? 0);
			$add_on_revenue = (int) ($manual_actuals['add_on_revenue_cents'] ?? 0);
			$concessions_revenue = (int) ($manual_actuals['concessions_revenue_cents'] ?? 0);
			$other_revenue = (int) ($manual_actuals['other_revenue_cents'] ?? 0);
		} else {
			$ticket_revenue = $headcount * $avg_ticket_price;
			$concessions_actual = max(0, (int) get_post_meta($event_plan_id, '_vms_concessions_actual_cents', true));

			if ($mode === 'true' && $concessions_actual > 0) {
				$concessions_revenue = $concessions_actual;
			} else {
				$concessions = isset($settings['concessions_model_defaults']) && is_array($settings['concessions_model_defaults'])
					? $settings['concessions_model_defaults']
					: array();
				$buyer_rate = max(0, min(100, (int) ($concessions['buyer_rate_pct'] ?? 35)));
				$avg_spend = max(0, (int) ($concessions['avg_spend_cents'] ?? 1800));
				$buyers = (int) round($headcount * ($buyer_rate / 100));
				$concessions_revenue = $buyers * $avg_spend;
			}
		}

		$gross_revenue = $ticket_revenue + $add_on_revenue + $concessions_revenue + $other_revenue;

		$direct_costs = ($mode === 'true' && $has_manual_actuals)
			? (int) ($manual_actuals['direct_costs_cents'] ?? 0)
			: vms_goals_get_default_direct_costs_cents($event_plan_id);
		$processing_fees = ($mode === 'true' && $has_manual_actuals)
			? (int) ($manual_actuals['processing_fees_cents'] ?? 0)
			: vms_goals_get_default_processing_fees_cents($event_plan_id);

		$overhead_rules = isset($settings['overhead_rules']) && is_array($settings['overhead_rules'])
			? $settings['overhead_rules']
			: array();
		$overhead_allocated = ($mode === 'true' && $has_manual_actuals && (int) ($manual_actuals['overhead_allocated_cents'] ?? 0) > 0)
			? (int) $manual_actuals['overhead_allocated_cents']
			: vms_goals_get_overhead_allocated_cents($gross_revenue, $headcount, $overhead_rules);
		if (!$include_overhead) {
			$overhead_allocated = 0;
		}

		$event_profit = $gross_revenue - $direct_costs - $processing_fees;
		$true_profit = $event_profit - $overhead_allocated;

		return array(
			'event_plan_id' => $event_plan_id,
			'headcount_mode' => $mode,
			'headcount' => max(0, $headcount),
			'include_overhead' => $include_overhead,
			'ticket_revenue_cents' => max(0, $ticket_revenue),
			'add_on_revenue_cents' => max(0, $add_on_revenue),
			'concessions_revenue_cents' => max(0, $concessions_revenue),
			'other_revenue_cents' => max(0, $other_revenue),
			'gross_revenue_cents' => max(0, $gross_revenue),
			'direct_costs_cents' => max(0, $direct_costs),
			'processing_fees_cents' => max(0, $processing_fees),
			'overhead_allocated_cents' => max(0, $overhead_allocated),
			'event_profit_cents' => (int) $event_profit,
			'true_profit_cents' => (int) $true_profit,
		);
	}
}

if (!function_exists('vms_goals_break_even_headcount')) {
	function vms_goals_break_even_headcount(int $event_plan_id, array $args = array()): array
	{
		$max_h = max(10, (int) ($args['max_headcount'] ?? 2000));
		$base = vms_goals_get_event_pnl($event_plan_id, array_merge($args, array('headcount_mode' => 'forecast')));
		$forecast_profit = (int) ($base['true_profit_cents'] ?? 0);

		$profit_at_0 = (int) (vms_goals_get_event_pnl($event_plan_id, array_merge($args, array('headcount_mode' => 'forecast', 'headcount_override' => 0)))['true_profit_cents'] ?? 0);
		if ($profit_at_0 >= 0) {
			return array(
				'found' => true,
				'break_even_headcount' => 0,
				'profit_at_forecast_cents' => $forecast_profit,
				'profit_at_plus_10_cents' => (int) (vms_goals_get_event_pnl($event_plan_id, array_merge($args, array('headcount_mode' => 'forecast', 'headcount_override' => 10)))['true_profit_cents'] ?? 0),
			);
		}

		$profit_at_max = (int) (vms_goals_get_event_pnl($event_plan_id, array_merge($args, array('headcount_mode' => 'forecast', 'headcount_override' => $max_h)))['true_profit_cents'] ?? 0);
		if ($profit_at_max < 0) {
			return array(
				'found' => false,
				'break_even_headcount' => $max_h,
				'profit_at_forecast_cents' => $forecast_profit,
				'profit_at_plus_10_cents' => (int) (vms_goals_get_event_pnl($event_plan_id, array_merge($args, array('headcount_mode' => 'forecast', 'headcount_override' => max(0, (int) ($base['headcount'] ?? 0) + 10))))['true_profit_cents'] ?? 0),
				'warning' => 'Break-even exceeds configured search cap.',
			);
		}

		$low = 0;
		$high = $max_h;
		while (($high - $low) > 1) {
			$mid = (int) floor(($low + $high) / 2);
			$profit = (int) (vms_goals_get_event_pnl($event_plan_id, array_merge($args, array('headcount_mode' => 'forecast', 'headcount_override' => $mid)))['true_profit_cents'] ?? 0);
			if ($profit >= 0) {
				$high = $mid;
			} else {
				$low = $mid;
			}
		}

		$profit_plus_10 = (int) (vms_goals_get_event_pnl(
			$event_plan_id,
			array_merge($args, array('headcount_mode' => 'forecast', 'headcount_override' => max(0, (int) ($base['headcount'] ?? 0) + 10)))
		)['true_profit_cents'] ?? 0);

		return array(
			'found' => true,
			'break_even_headcount' => $high,
			'profit_at_forecast_cents' => $forecast_profit,
			'profit_at_plus_10_cents' => $profit_plus_10,
		);
	}
}

if (!function_exists('vms_goals_metric_value_from_pnl')) {
	function vms_goals_metric_value_from_pnl(string $metric, array $pnl): int
	{
		$metric = sanitize_key($metric);
		if ($metric === 'gross_revenue') {
			return (int) ($pnl['gross_revenue_cents'] ?? 0);
		}
		if ($metric === 'event_profit') {
			return (int) ($pnl['event_profit_cents'] ?? 0);
		}
		return (int) ($pnl['true_profit_cents'] ?? 0);
	}
}

if (!function_exists('vms_goals_get_event_ids_in_period')) {
	function vms_goals_get_event_ids_in_period(string $start_local, string $end_local, int $limit = -1): array
	{
		$start_dt = vms_goals_parse_local_datetime($start_local);
		$end_dt = vms_goals_parse_local_datetime($end_local);
		if (!($start_dt instanceof DateTimeImmutable) || !($end_dt instanceof DateTimeImmutable) || $end_dt <= $start_dt) {
			return array();
		}

		$start_date = $start_dt->format('Y-m-d');
		$end_date = $end_dt->modify('-1 second')->format('Y-m-d');
		$limit = (int) $limit;
		$posts_per_page = ($limit > 0) ? $limit : -1;

		$q = new WP_Query(array(
			'post_type' => 'vms_event_plan',
			'post_status' => array('publish', 'draft', 'private'),
			'posts_per_page' => $posts_per_page,
			'fields' => 'ids',
			'no_found_rows' => true,
			'meta_key' => '_vms_event_date',
			'orderby' => 'meta_value',
			'order' => 'ASC',
			'meta_query' => array(
				array(
					'key' => '_vms_event_date',
					'value' => array($start_date, $end_date),
					'compare' => 'BETWEEN',
					'type' => 'DATE',
				),
			),
		));

		$ids = !empty($q->posts) ? array_map('intval', (array) $q->posts) : array();
		$out = array();
		foreach ($ids as $id) {
			$include = true;
			if (function_exists('vms_event_plan_should_include')) {
				$include = vms_event_plan_should_include($id, 'financial', array(
					'include_drafts' => false,
					'include_cancelled' => false,
				));
			}
			if ($include) {
				$out[] = $id;
			}
		}
		return $out;
	}
}

if (!function_exists('vms_goals_compute_goal_progress')) {
	function vms_goals_compute_goal_progress(array $goal, array $args = array()): array
	{
		$metric = sanitize_key((string) ($goal['metric'] ?? 'true_profit'));
		$target_cents = max(0, (int) ($goal['target_cents'] ?? 0));
		$start_local = (string) ($goal['period_start_local'] ?? '');
		$end_local = (string) ($goal['period_end_local'] ?? '');
		$settings = vms_goals_get_settings();
		$trailing_n = max(1, (int) ($settings['default_trailing_window_events'] ?? 6));
		$max_events = max(25, (int) apply_filters('vms_goals_progress_max_events', 120));

		$event_ids = vms_goals_get_event_ids_in_period($start_local, $end_local, $max_events + 1);
		$is_truncated = (count($event_ids) > $max_events);
		if ($is_truncated) {
			$event_ids = array_slice($event_ids, 0, $max_events);
			vms_goals_log('Goal progress evaluation capped at ' . $max_events . ' events for performance.');
		}

		$today = wp_date('Y-m-d', null, wp_timezone());

		$completed_rows = array();
		$remaining_rows = array();
		foreach ($event_ids as $event_id) {
			$event_date = (string) get_post_meta($event_id, '_vms_event_date', true);
			$is_completed = ($event_date !== '' && $event_date < $today);
			if ($is_completed) {
				$pnl = vms_goals_get_event_pnl($event_id, array(
					'headcount_mode' => 'true',
					'include_overhead' => true,
				));
				$metric_value = vms_goals_metric_value_from_pnl($metric, $pnl);
				$row = array(
					'event_plan_id' => $event_id,
					'event_date' => $event_date,
					'metric_value_cents' => $metric_value,
					'pnl' => $pnl,
				);
				$completed_rows[] = $row;
			} else {
				$row = array(
					'event_plan_id' => $event_id,
					'event_date' => $event_date,
					'metric_value_cents' => 0,
					'pnl' => array(),
				);
				$remaining_rows[] = $row;
			}
		}

		usort($completed_rows, static function ($a, $b) {
			return strcmp((string) ($a['event_date'] ?? ''), (string) ($b['event_date'] ?? ''));
		});
		usort($remaining_rows, static function ($a, $b) {
			return strcmp((string) ($a['event_date'] ?? ''), (string) ($b['event_date'] ?? ''));
		});

		$actual_to_date = 0;
		foreach ($completed_rows as $row) {
			$actual_to_date += (int) ($row['metric_value_cents'] ?? 0);
		}

		$remaining_required = $target_cents - $actual_to_date;
		$remaining_count = count($remaining_rows);
		$required_avg = ($remaining_count > 0)
			? (int) round($remaining_required / $remaining_count)
			: 0;

		$trail = array_slice($completed_rows, -1 * $trailing_n);
		$trail_sum = 0;
		foreach ($trail as $row) {
			$trail_sum += (int) ($row['metric_value_cents'] ?? 0);
		}
		$trail_count = count($trail);
		$trailing_avg = ($trail_count > 0) ? (int) round($trail_sum / $trail_count) : 0;
		$projected_end = $actual_to_date + ($trailing_avg * $remaining_count);
		$projection_gap = $target_cents - $projected_end;
		$ahead_by = ($remaining_required < 0) ? abs($remaining_required) : 0;

		$allocations = array();
		if ($remaining_count > 0) {
			$per_event = (int) round($remaining_required / $remaining_count);
			foreach ($remaining_rows as $row) {
				$allocations[] = array(
					'event_plan_id' => (int) $row['event_plan_id'],
					'event_date' => (string) ($row['event_date'] ?? ''),
					'required_contribution_cents' => $per_event,
				);
			}
		}

		return array(
			'metric' => $metric,
			'target_cents' => $target_cents,
			'is_truncated' => $is_truncated,
			'max_events_evaluated' => $max_events,
			'actual_to_date_cents' => $actual_to_date,
			'remaining_required_cents' => $remaining_required,
			'remaining_events_count' => $remaining_count,
			'required_avg_per_remaining_event_cents' => $required_avg,
			'trailing_avg_cents' => $trailing_avg,
			'projected_end_cents' => $projected_end,
			'projection_gap_cents' => $projection_gap,
			'ahead_by_cents' => $ahead_by,
			'completed_rows' => $completed_rows,
			'remaining_rows' => $remaining_rows,
			'allocations' => $allocations,
		);
	}
}
