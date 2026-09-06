<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_Ticket_Context')) {
	class VMSX_Weather_Risk_Ticket_Context {
		public static function build(int $event_plan_id, array $settings, array $window): array
		{
			unset($window);
			$key = VMSX_Weather_Risk_Helpers::event_meta_key('ticket_stats', '_vms_ticket_stats_v1');
			$raw = get_post_meta($event_plan_id, $key, true);
			$stats = is_array($raw) ? $raw : array();
			$warnings = array();

			$dt_truth = self::dt_truth_stats($event_plan_id);
			$live_stats = self::live_ticketing_stats($event_plan_id, $key);

			if (is_array($dt_truth)) {
				$stats = array_merge($stats, $dt_truth);
			} elseif (is_array($live_stats)) {
				$stats = array_merge($stats, $live_stats);
			}

			$sold_qty = self::first_int($stats, array('qty_sold', 'qty'));
			$gross_cents = self::gross_cents_from_stats($stats);
			$provider = sanitize_key((string) ($stats['provider'] ?? ''));
			$computed_at = self::timestamp_from_stats($stats);
			$now = time();
			$is_missing = ($sold_qty === null && $gross_cents === null);
			$is_stale = (!$is_missing && $computed_at > 0) ? (($now - $computed_at) > DAY_IN_SECONDS) : $is_missing;
			$min_qty = max(0, (int) ($settings['min_viable_ticket_qty'] ?? 0));
			$min_gross = max(0, (int) ($settings['min_viable_gross_cents'] ?? 0));
			$qty_ratio = ($min_qty > 0 && $sold_qty !== null) ? ((float) $sold_qty / $min_qty) : null;
			$gross_ratio = ($min_gross > 0 && $gross_cents !== null) ? ((float) $gross_cents / $min_gross) : null;
			$labor_cost = VMSX_Weather_Risk_Compatibility::core_function('vms_event_profitability_get_labor_cost_cents');
			$labor_cents = $labor_cost !== ''
				? max(0, (int) $labor_cost($event_plan_id))
				: 0;
			$vendor_cents = self::estimate_vendor_cost_cents($event_plan_id);
			$known_exposure_cents = max(0, $labor_cents + $vendor_cents);

			if (is_array($dt_truth)) {
				$warnings[] = __('Using VMS Data Tools website ticket truth for this event snapshot.', 'vmsx-weather-risk');
			} elseif (is_array($live_stats)) {
				$warnings[] = __('Using a fresh VMS ticketing calculation instead of the older cached ticket snapshot.', 'vmsx-weather-risk');
			}

			if ($is_missing) {
				$warnings[] = __('Ticket stats are missing for this event plan.', 'vmsx-weather-risk');
			} elseif ($is_stale) {
				$warnings[] = __('Ticket stats look stale, so sales confidence is reduced.', 'vmsx-weather-risk');
			}

			return array(
				'sold_qty' => $sold_qty,
				'gross_cents' => $gross_cents,
				'provider' => $provider,
				'provider_label' => self::provider_label($provider),
				'computed_at_utc' => $computed_at,
				'computed_at_local' => VMSX_Weather_Risk_Helpers::format_local_timestamp($computed_at),
				'is_missing' => $is_missing,
				'is_stale' => $is_stale,
				'qty_ratio' => $qty_ratio,
				'gross_ratio' => $gross_ratio,
				'min_viable_ticket_qty' => $min_qty,
				'min_viable_gross_cents' => $min_gross,
				'known_exposure_cents' => $known_exposure_cents,
				'labor_cost_cents' => $labor_cents,
				'vendor_cost_cents' => $vendor_cents,
				'warnings' => array_values(array_unique(array_filter($warnings))),
			);
		}

		private static function provider_label(string $provider): string
		{
			switch ($provider) {
				case 'woo_analytics':
					return __('Woo analytics', 'vmsx-weather-risk');
				case 'woo_product_totals':
					return __('Woo product totals', 'vmsx-weather-risk');
				case 'square':
					return __('Square', 'vmsx-weather-risk');
				case 'pending_refresh':
					return __('Pending refresh', 'vmsx-weather-risk');
				case 'dt_website_truth':
					return __('DT website truth', 'vmsx-weather-risk');
				case 'vms_live_ticketing':
					return __('Fresh VMS ticketing', 'vmsx-weather-risk');
				default:
					return $provider !== '' ? ucwords(str_replace('_', ' ', $provider)) : __('N/A', 'vmsx-weather-risk');
			}
		}

		private static function dt_truth_stats(int $event_plan_id): ?array
		{
			if (!function_exists('vms_dt_reporting_build_event_lifetime_website_truth')) {
				return null;
			}

			$truth = (array) vms_dt_reporting_build_event_lifetime_website_truth($event_plan_id);
			if (empty($truth)) {
				return null;
			}

			$qty = max(0, (int) ($truth['website_ticket_qty'] ?? 0));
			$gross_cents = max(0, (int) ($truth['website_ticket_net_cents'] ?? 0));
			$order_count = max(0, (int) ($truth['website_order_count'] ?? 0));
			$line_count = max(0, (int) ($truth['website_line_count'] ?? 0));

			if ($qty <= 0 && $gross_cents <= 0 && $order_count <= 0 && $line_count <= 0) {
				return null;
			}

			return array(
				'provider' => 'dt_website_truth',
				'qty_sold' => $qty,
				'revenue_cents' => $gross_cents,
				'computed_at_gmt' => time(),
				'source_detail' => 'dt',
			);
		}

		private static function live_ticketing_stats(int $event_plan_id, string $ticket_stats_key): ?array
		{
			if (!function_exists('vms_ticketing_compute_stats')) {
				return null;
			}

			$tec_key = VMSX_Weather_Risk_Helpers::event_meta_key('tec_event_id', '_vms_tec_event_id');
			$tec_id = (int) get_post_meta($event_plan_id, $tec_key, true);
			$detected = ($tec_id > 0 && function_exists('vms_ticketing_get_ticket_product_ids_for_tec_event'))
				? (array) vms_ticketing_get_ticket_product_ids_for_tec_event($tec_id)
				: array();
			$manual = function_exists('vms_ticketing_get_manual_product_ids')
				? (array) vms_ticketing_get_manual_product_ids($event_plan_id)
				: array();
			$product_ids = array_values(array_unique(array_filter(array_map('absint', array_merge($detected, $manual)))));

			if (empty($product_ids)) {
				return null;
			}

			$stats = (array) vms_ticketing_compute_stats($product_ids);
			if (empty($stats)) {
				return null;
			}

			$stats['provider'] = 'vms_live_ticketing';
			$stats['detected_product_ids'] = $detected;
			$stats['manual_product_ids'] = $manual;
			if (empty($stats['computed_at_gmt'])) {
				$stats['computed_at_gmt'] = time();
			}

			update_post_meta($event_plan_id, $ticket_stats_key, $stats);

			return $stats;
		}

		private static function first_int(array $stats, array $keys): ?int
		{
			foreach ($keys as $key) {
				if (!array_key_exists($key, $stats)) {
					continue;
				}
				$value = $stats[$key];
				if (is_numeric($value)) {
					return max(0, (int) $value);
				}
			}
			return null;
		}

		private static function gross_cents_from_stats(array $stats): ?int
		{
			if (array_key_exists('revenue_cents', $stats) && is_numeric($stats['revenue_cents'])) {
				return max(0, (int) $stats['revenue_cents']);
			}
			if (array_key_exists('gross_cents', $stats) && is_numeric($stats['gross_cents'])) {
				return max(0, (int) $stats['gross_cents']);
			}
			if (array_key_exists('revenue', $stats) && is_numeric($stats['revenue'])) {
				return max(0, (int) round(((float) $stats['revenue']) * 100));
			}
			return null;
		}

		private static function timestamp_from_stats(array $stats): int
		{
			foreach (array('computed_at_gmt', 'updated_at_gmt', 'pulled_at_gmt', 'computed_at', 'updated_at', 'pulled_at') as $key) {
				if (!array_key_exists($key, $stats)) {
					continue;
				}
				$value = $stats[$key];
				if (is_numeric($value)) {
					$ts = (int) round((float) $value);
					if ($ts > 999999999999) {
						$ts = (int) floor($ts / 1000);
					}
					if ($ts > 0) {
						return $ts;
					}
				}
				if (is_string($value) && trim($value) !== '') {
					$ts = strtotime(trim($value) . (substr((string) $key, -4) === '_gmt' ? ' UTC' : ''));
					if ($ts) {
						return (int) $ts;
					}
				}
			}
			return 0;
		}

		private static function estimate_vendor_cost_cents(int $event_plan_id): int
		{
			$flat_fee = get_post_meta($event_plan_id, '_vms_flat_fee_amount', true);
			if ($flat_fee !== '' && $flat_fee !== null && is_numeric($flat_fee)) {
				return max(0, (int) round(((float) $flat_fee) * 100));
			}

			return 0;
		}
	}
}
