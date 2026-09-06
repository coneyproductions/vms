<?php

defined('ABSPATH') || exit;

if (!class_exists('VMSX_Weather_Risk_DT_Enrichment')) {
	class VMSX_Weather_Risk_DT_Enrichment {
		public static function build(int $event_plan_id, array $ticket_context): array
		{
			unset($ticket_context);

			if (
				!function_exists('vms_dt_reporting_ticket_pace_rows') ||
				!function_exists('vms_dt_reporting_build_ticket_pace_history') ||
				!function_exists('vms_dt_reporting_ticket_pace_projection')
			) {
				return array(
					'available' => false,
					'used' => false,
					'note' => __('VMS Data Tools pace helpers are not available in this environment.', 'vmsx-weather-risk'),
				);
			}

			$rows = (array) vms_dt_reporting_ticket_pace_rows($event_plan_id);
			if (empty($rows)) {
				return array(
					'available' => true,
					'used' => false,
					'note' => __('Ticket pace history is not available for this event yet.', 'vmsx-weather-risk'),
				);
			}

			$latest = end($rows);
			if (!is_array($latest)) {
				return array(
					'available' => true,
					'used' => false,
					'note' => __('Ticket pace history could not be summarized.', 'vmsx-weather-risk'),
				);
			}

			$milestones = array(60, 30, 14, 7, 3, 1);
			$history = (array) vms_dt_reporting_build_ticket_pace_history($event_plan_id, $milestones);
			$averages = isset($history['milestone_averages']) && is_array($history['milestone_averages']) ? $history['milestone_averages'] : array();
			$milestone_keys = array_map('intval', array_keys($averages));
			$closest = !empty($milestone_keys) && function_exists('vms_dt_reporting_ticket_pace_closest_milestone')
				? (int) vms_dt_reporting_ticket_pace_closest_milestone((int) ($latest['days_out'] ?? 0), $milestone_keys)
				: (int) ($latest['days_out'] ?? 0);
			$basis = $averages[$closest] ?? null;
			$projection = (array) vms_dt_reporting_ticket_pace_projection($latest, $history);

			$pace_band = 'unknown';
			$pace_label = __('Not enough comparable pace history', 'vmsx-weather-risk');
			$qty_variance = null;
			$sales_variance_cents = null;

			if (is_array($basis)) {
				$avg_qty = (int) ($basis['avg_cum_qty'] ?? 0);
				$avg_sales = (int) ($basis['avg_cum_sales_cents'] ?? 0);
				$current_qty = (int) ($latest['cum_qty'] ?? 0);
				$current_sales = (int) ($latest['cum_sales_cents'] ?? 0);
				$qty_variance = $current_qty - $avg_qty;
				$sales_variance_cents = $current_sales - $avg_sales;
				if ($qty_variance > 2 || $sales_variance_cents > 10000) {
					$pace_band = 'ahead';
					$pace_label = __('Ahead of pace', 'vmsx-weather-risk');
				} elseif ($qty_variance < -2 || $sales_variance_cents < -10000) {
					$pace_band = 'behind';
					$pace_label = __('Behind pace', 'vmsx-weather-risk');
				} else {
					$pace_band = 'on_pace';
					$pace_label = __('On pace', 'vmsx-weather-risk');
				}
			}

			return array(
				'available' => true,
				'used' => is_array($basis),
				'note' => is_array($basis)
					? __('DT pace enrichment is active for this event.', 'vmsx-weather-risk')
					: __('Historical DT comparison data was limited for this event.', 'vmsx-weather-risk'),
				'pace_band' => $pace_band,
				'pace_label' => $pace_label,
				'checkpoint_days_out' => $closest,
				'current_days_out' => (int) ($latest['days_out'] ?? 0),
				'current_qty' => (int) ($latest['cum_qty'] ?? 0),
				'current_sales_cents' => (int) ($latest['cum_sales_cents'] ?? 0),
				'avg_comparable_qty' => is_array($basis) ? (int) ($basis['avg_cum_qty'] ?? 0) : 0,
				'avg_comparable_sales_cents' => is_array($basis) ? (int) ($basis['avg_cum_sales_cents'] ?? 0) : 0,
				'qty_variance' => $qty_variance,
				'sales_variance_cents' => $sales_variance_cents,
				'compare_scope_label' => sanitize_text_field((string) ($history['compare_scope_label'] ?? '')),
				'projection' => $projection,
			);
		}
	}
}
