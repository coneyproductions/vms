<?php
defined('ABSPATH') || exit;

if (!defined('VMS_CALENDAR_TICKET_COUNTS_CRON_HOOK')) {
	define('VMS_CALENDAR_TICKET_COUNTS_CRON_HOOK', 'vms_calendar_ticket_counts_nightly');
}

if (!function_exists('vms_calendar_ticket_counts_meta_key')) {
	function vms_calendar_ticket_counts_meta_key(): string
	{
		$key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'tickets_sold_count') : '';
		return $key !== '' ? $key : '_vms_tickets_sold_count';
	}
}

if (!function_exists('vms_calendar_ticket_counts_tec_key')) {
	function vms_calendar_ticket_counts_tec_key(): string
	{
		$key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'tec_event_id') : '';
		return $key !== '' ? $key : '_vms_tec_event_id';
	}
}

if (!function_exists('vms_calendar_ticket_counts_date_key')) {
	function vms_calendar_ticket_counts_date_key(): string
	{
		$key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'date') : '';
		return $key !== '' ? $key : '_vms_event_date';
	}
}

if (!function_exists('vms_calendar_ticket_counts_get_tec_event_id_for_plan')) {
	function vms_calendar_ticket_counts_get_tec_event_id_for_plan(int $plan_id): int
	{
		$plan_id = absint($plan_id);
		if ($plan_id <= 0) {
			return 0;
		}
		if (function_exists('vms_ticketing_b_get_linked_tec_event_id')) {
			$tec = absint(vms_ticketing_b_get_linked_tec_event_id($plan_id));
			if ($tec > 0) {
				return $tec;
			}
		}
		return absint(get_post_meta($plan_id, vms_calendar_ticket_counts_tec_key(), true));
	}
}

if (!function_exists('vms_calendar_ticket_counts_get_product_ids_for_tec_event')) {
	/**
	 * @return int[]
	 */
	function vms_calendar_ticket_counts_get_product_ids_for_tec_event(int $tec_event_id): array
	{
		$tec_event_id = absint($tec_event_id);
		if ($tec_event_id <= 0) {
			return array();
		}

		$ids = array();
		if (function_exists('vms_ticketing_get_ticket_product_ids_for_tec_event')) {
			$ids = (array) vms_ticketing_get_ticket_product_ids_for_tec_event($tec_event_id);
		} elseif (function_exists('vms_ticketing_b_get_event_ticket_products')) {
			$ids = (array) vms_ticketing_b_get_event_ticket_products($tec_event_id);
		} elseif (function_exists('vms_get_ticket_product_ids_for_event')) {
			$ids = (array) vms_get_ticket_product_ids_for_event($tec_event_id);
		}

		return array_values(array_unique(array_filter(array_map('absint', $ids))));
	}
}

if (!function_exists('vms_calendar_ticket_counts_compute_qty')) {
	function vms_calendar_ticket_counts_compute_qty(array $product_ids): int
	{
		$product_ids = array_values(array_unique(array_filter(array_map('absint', $product_ids))));
		if (empty($product_ids)) {
			return 0;
		}

		if (function_exists('vms_ticketing_compute_stats')) {
			$stats = (array) vms_ticketing_compute_stats($product_ids);
			return max(0, (int) ($stats['qty_sold'] ?? 0));
		}

		if (!function_exists('wc_get_product')) {
			return 0;
		}

		$qty = 0;
		foreach ($product_ids as $product_id) {
			$product = wc_get_product($product_id);
			if (!$product || !method_exists($product, 'get_total_sales')) {
				continue;
			}
			$qty += max(0, (int) $product->get_total_sales());
		}
		return max(0, $qty);
	}
}

if (!function_exists('vms_calendar_ticket_counts_set_plan_qty')) {
	function vms_calendar_ticket_counts_set_plan_qty(int $plan_id, int $qty): void
	{
		$plan_id = absint($plan_id);
		if ($plan_id <= 0) {
			return;
		}

		$qty = max(0, (int) $qty);
		$key = vms_calendar_ticket_counts_meta_key();
		$aliases = array($key, '_vms_tickets_sold_count', 'vms_tickets_sold_count');
		$aliases = array_values(array_unique(array_filter(array_map('strval', $aliases))));

		$before = (int) get_post_meta($plan_id, $key, true);
		foreach ($aliases as $meta_key) {
			update_post_meta($plan_id, $meta_key, $qty);
		}

		if ($before !== $qty && function_exists('vms_calendar_feed_cache_bust')) {
			vms_calendar_feed_cache_bust();
		}
	}
}

if (!function_exists('vms_calendar_ticket_counts_refresh_plan')) {
	function vms_calendar_ticket_counts_refresh_plan(int $plan_id): int
	{
		$plan_id = absint($plan_id);
		if ($plan_id <= 0 || get_post_type($plan_id) !== 'vms_event_plan') {
			return 0;
		}

		$tec_event_id = vms_calendar_ticket_counts_get_tec_event_id_for_plan($plan_id);
		if ($tec_event_id <= 0) {
			vms_calendar_ticket_counts_set_plan_qty($plan_id, 0);
			return 0;
		}

		$product_ids = vms_calendar_ticket_counts_get_product_ids_for_tec_event($tec_event_id);
		$qty = vms_calendar_ticket_counts_compute_qty($product_ids);
		vms_calendar_ticket_counts_set_plan_qty($plan_id, $qty);
		return $qty;
	}
}

if (!function_exists('vms_calendar_ticket_counts_refresh_plans')) {
	/**
	 * @param int[] $plan_ids
	 */
	function vms_calendar_ticket_counts_refresh_plans(array $plan_ids): void
	{
		$plan_ids = array_values(array_unique(array_filter(array_map('absint', $plan_ids))));
		foreach ($plan_ids as $plan_id) {
			vms_calendar_ticket_counts_refresh_plan($plan_id);
		}
	}
}

if (!function_exists('vms_calendar_ticket_counts_find_plan_ids_by_tec_event')) {
	/**
	 * @return int[]
	 */
	function vms_calendar_ticket_counts_find_plan_ids_by_tec_event(int $tec_event_id): array
	{
		$tec_event_id = absint($tec_event_id);
		if ($tec_event_id <= 0) {
			return array();
		}

		$plan_ids = get_posts(array(
			'post_type' => 'vms_event_plan',
			'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'meta_query' => array(
				array(
					'key' => vms_calendar_ticket_counts_tec_key(),
					'value' => $tec_event_id,
					'compare' => '=',
					'type' => 'NUMERIC',
				),
			),
		));

		return array_values(array_unique(array_filter(array_map('absint', (array) $plan_ids))));
	}
}

if (!function_exists('vms_calendar_ticket_counts_collect_plan_ids_from_order')) {
	/**
	 * @return int[]
	 */
	function vms_calendar_ticket_counts_collect_plan_ids_from_order(int $order_id): array
	{
		$order_id = absint($order_id);
		if ($order_id <= 0 || !function_exists('wc_get_order')) {
			return array();
		}

		$order = wc_get_order($order_id);
		if (!$order || !is_object($order) || !method_exists($order, 'get_items')) {
			return array();
		}

		$product_to_tec_keys = array(
			'_tribe_wooticket_for_event',
			'_vms_ticket_event_id',
			vms_calendar_ticket_counts_tec_key(),
		);
		if (function_exists('vms_ticketing_v2_product_meta_key')) {
			$k = (string) vms_ticketing_v2_product_meta_key('tec_event_id');
			if ($k !== '') {
				$product_to_tec_keys[] = $k;
			}
		}
		$product_to_tec_keys = array_values(array_unique(array_filter(array_map('strval', $product_to_tec_keys))));

		$plan_ids = array();
		foreach ((array) $order->get_items('line_item') as $item) {
			if (!is_object($item) || !method_exists($item, 'get_product_id')) {
				continue;
			}

			$candidate_products = array(
				absint(method_exists($item, 'get_variation_id') ? $item->get_variation_id() : 0),
				absint($item->get_product_id()),
			);
			$candidate_products = array_values(array_unique(array_filter($candidate_products)));

			$tec_event_ids = array();
			foreach ($candidate_products as $product_id) {
				foreach ($product_to_tec_keys as $meta_key) {
					$tec = absint(get_post_meta($product_id, $meta_key, true));
					if ($tec > 0) {
						$tec_event_ids[] = $tec;
					}
				}
			}

			foreach (array_values(array_unique(array_filter(array_map('absint', $tec_event_ids)))) as $tec_event_id) {
				$plan_ids = array_merge($plan_ids, vms_calendar_ticket_counts_find_plan_ids_by_tec_event($tec_event_id));
			}
		}

		return array_values(array_unique(array_filter(array_map('absint', $plan_ids))));
	}
}

if (!function_exists('vms_calendar_ticket_counts_order_status_changed')) {
	function vms_calendar_ticket_counts_order_status_changed($order_id, $old_status = '', $new_status = '', $order = null): void
	{
		$plan_ids = vms_calendar_ticket_counts_collect_plan_ids_from_order(absint($order_id));
		if (empty($plan_ids)) {
			return;
		}
		vms_calendar_ticket_counts_refresh_plans($plan_ids);
	}
}
add_action('woocommerce_order_status_changed', 'vms_calendar_ticket_counts_order_status_changed', 20, 4);

if (!function_exists('vms_calendar_ticket_counts_nightly_scan')) {
	function vms_calendar_ticket_counts_nightly_scan(): void
	{
		$today = wp_date('Y-m-d', time(), function_exists('vms_get_timezone') ? vms_get_timezone() : wp_timezone());
		$days = (int) apply_filters('vms_calendar_ticket_counts_scan_window_days', 60);
		if ($days < 1) {
			$days = 60;
		}
		$end = gmdate('Y-m-d', strtotime('+' . $days . ' days', strtotime($today)));

		$plan_ids = get_posts(array(
			'post_type' => 'vms_event_plan',
			'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
			'posts_per_page' => -1,
			'fields' => 'ids',
			'no_found_rows' => true,
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => vms_calendar_ticket_counts_date_key(),
					'value' => $today,
					'compare' => '>=',
					'type' => 'DATE',
				),
				array(
					'key' => vms_calendar_ticket_counts_date_key(),
					'value' => $end,
					'compare' => '<=',
					'type' => 'DATE',
				),
			),
		));

		vms_calendar_ticket_counts_refresh_plans((array) $plan_ids);
	}
}
add_action(VMS_CALENDAR_TICKET_COUNTS_CRON_HOOK, 'vms_calendar_ticket_counts_nightly_scan');

if (!function_exists('vms_calendar_ticket_counts_schedule_cron')) {
	function vms_calendar_ticket_counts_schedule_cron(): void
	{
		if (function_exists('vms_should_run_runtime_maintenance') && !vms_should_run_runtime_maintenance()) {
			return;
		}
		if (!wp_next_scheduled(VMS_CALENDAR_TICKET_COUNTS_CRON_HOOK)) {
			wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', VMS_CALENDAR_TICKET_COUNTS_CRON_HOOK);
		}
	}
}
add_action('init', 'vms_calendar_ticket_counts_schedule_cron', 40);
