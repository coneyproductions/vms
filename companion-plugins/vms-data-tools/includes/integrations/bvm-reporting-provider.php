<?php
/**
 * Active-plugin-owned reporting provider for Backstage Venue Manager.
 */

defined('ABSPATH') || exit;

if (!function_exists('vms_dt_bvm_reporting_load_dependencies')) {
	function vms_dt_bvm_reporting_load_dependencies(): bool
	{
		if (
			function_exists('vms_dt_reporting_build_event_model')
			&& function_exists('vms_dt_reporting_build_website_detail_rows')
			&& function_exists('vms_dt_reporting_build_square_line_evidence')
			&& function_exists('vms_dt_reporting_build_ticket_source_rollup')
		) {
			return true;
		}

		require_once VMS_DT_ADMIN_DIR . 'page-revenue-intelligence.php';
		require_once VMS_DT_ADMIN_DIR . 'page-reporting-module.php';

		return function_exists('vms_dt_reporting_build_event_model')
			&& function_exists('vms_dt_reporting_build_website_detail_rows')
			&& function_exists('vms_dt_reporting_build_square_line_evidence')
			&& function_exists('vms_dt_reporting_build_ticket_source_rollup');
	}
}

if (!function_exists('vms_dt_bvm_reporting_event_summary')) {
	/** @return array<string,mixed> */
	function vms_dt_bvm_reporting_event_summary(int $event_plan_id): array
	{
		$model = (array) vms_dt_reporting_build_event_model(array(
			'event_plan_id' => $event_plan_id,
			'event_from' => '',
			'event_to' => '',
			'sold_from' => '',
			'sold_to' => '',
			'venue_id' => 0,
			'square_location_id' => '',
			'square_scope_mode' => 'full_day',
			'compare' => 0,
		));
		$costs = isset($model['costs']) && is_array($model['costs']) ? (array) $model['costs'] : array();
		$summary = isset($model['summary']) && is_array($model['summary']) ? (array) $model['summary'] : array();
		$row = isset($model['row']) && is_array($model['row']) ? (array) $model['row'] : array();

		$paid_qty = max(0, (int) ($costs['paid_ticket_qty_total'] ?? 0));
		if ($paid_qty <= 0) {
			$paid_qty = max(0, (int) (($row['website_paid_ticket_qty'] ?? 0) + ($row['square_paid_ticket_qty'] ?? 0)));
		}

		$free_qty = max(0, (int) ($costs['free_ticket_qty_excluded'] ?? 0));
		if ($free_qty <= 0) {
			$free_qty = max(0, (int) (($row['website_free_ticket_qty'] ?? 0) + ($row['square_free_ticket_qty'] ?? 0)));
		}

		$total_qty = max(0, (int) ($costs['ticket_qty_total'] ?? 0));
		if ($total_qty <= 0) {
			$total_qty = max(0, (int) ($summary['total_ticket_qty'] ?? 0));
		}
		if ($total_qty <= 0 && ($paid_qty > 0 || $free_qty > 0)) {
			$total_qty = $paid_qty + $free_qty;
		}

		$revenue_cents = max(0, (int) ($costs['ticket_sales_total_cents'] ?? 0));
		if ($revenue_cents <= 0) {
			$revenue_cents = max(0, (int) ($summary['total_ticket_sales_cents'] ?? 0));
		}

		$calculated = array_key_exists('costs', $model)
			|| array_key_exists('summary', $model)
			|| array_key_exists('row', $model);
		if (!$calculated) {
			return array(
				'available' => true,
				'calculated' => false,
				'warnings' => array('Data Tools did not return an event reporting model.'),
			);
		}

		$website_paid_qty = max(0, (int) ($row['website_paid_ticket_qty'] ?? 0));
		$square_paid_qty = max(0, (int) ($row['square_paid_ticket_qty'] ?? 0));
		$website_free_qty = max(0, (int) ($row['website_free_ticket_qty'] ?? 0));
		$square_free_qty = max(0, (int) ($row['square_free_ticket_qty'] ?? 0));

		return array(
			'available' => true,
			'calculated' => true,
			'source' => 'dt_reporting_model',
			'source_label' => __('Data Tools reporting model', 'vms-data-tools'),
			'source_mode' => 'data_tools_live',
			'paid_qty' => $paid_qty,
			'free_qty' => $free_qty,
			'total_qty' => $total_qty,
			'revenue_cents' => $revenue_cents,
			'headcount' => $total_qty,
			'online_qty' => $website_paid_qty,
			'door_qty' => $square_paid_qty + $square_free_qty,
			'door_paid_qty' => $square_paid_qty,
			'door_free_qty' => $square_free_qty,
			'paid_ticket_qty_total' => $paid_qty,
			'free_ticket_qty_total' => $free_qty,
			'ticketed_attendance_qty' => $total_qty,
			'has_countable_data' => ($total_qty > 0 || $paid_qty > 0 || $free_qty > 0),
			'warnings' => array_values(array_unique(array_filter(array_merge(
				(array) ($row['confidence_badges'] ?? array()),
				(array) ($row['square_warnings'] ?? array())
			)))),
			'errors' => array_values(array_unique(array_filter((array) ($row['square_errors'] ?? array())))),
		);
	}
}

if (!function_exists('vms_dt_bvm_reporting_vendor_portal_summary')) {
	function vms_dt_bvm_reporting_complimentary_label(string $item_name, string $source): string
	{
		$label = trim(wp_strip_all_tags($item_name));
		$label = (string) preg_replace('/^\d{4}-\d{2}-\d{2}(?:\s+\d{1,2}:\d{2})?\s*[-\x{2013}\x{2014}:]\s*/u', '', $label);
		$searchable = strtolower($label);

		if (strpos($searchable, 'veteran') !== false) {
			return __('Veterans', 'vms-data-tools');
		}
		if (strpos($searchable, 'child') !== false && (strpos($searchable, '12') !== false || strpos($searchable, 'under') !== false)) {
			return __('Children 12 & under', 'vms-data-tools');
		}
		if (
			strpos($searchable, 'police') !== false
			|| strpos($searchable, 'fire') !== false
			|| strpos($searchable, 'emt') !== false
			|| strpos($searchable, 'first responder') !== false
		) {
			return __('Police / Fire / EMT', 'vms-data-tools');
		}
		if (strpos($searchable, 'teacher') !== false) {
			return __('Public School Teacher', 'vms-data-tools');
		}
		if ($label !== '') {
			return $label;
		}

		return $source === 'square'
			? __('Complimentary door admission', 'vms-data-tools')
			: __('Complimentary website admission', 'vms-data-tools');
	}

	/**
	 * @param array<int,array<string,mixed>> $website_rows
	 * @param array<int,array<string,mixed>> $square_rows
	 * @return array<int,array{key:string,label:string,qty:int,source:string}>
	 */
	function vms_dt_bvm_reporting_complimentary_categories(array $website_rows, array $square_rows): array
	{
		$categories = array();
		$add_category = static function (string $label, int $qty, string $source) use (&$categories): void {
			$qty = max(0, $qty);
			$label = trim($label);
			if ($label === '' || $qty <= 0) {
				return;
			}

			$key = sanitize_key($label);
			$aggregate_key = $source . ':' . $key;
			if (!isset($categories[$aggregate_key])) {
				$categories[$aggregate_key] = array(
					'key' => $key,
					'label' => $label,
					'qty' => 0,
					'source' => $source,
				);
			}
			$categories[$aggregate_key]['qty'] += $qty;
		};

		foreach ($website_rows as $row) {
			if (!is_array($row) || (int) ($row['net_subtotal_cents'] ?? 0) > 0) {
				continue;
			}
			$qty = max(0, (int) ($row['quantity'] ?? 0) - (int) ($row['refunded_quantity'] ?? 0));
			$label = vms_dt_bvm_reporting_complimentary_label((string) ($row['item_name'] ?? ''), 'website');
			$add_category($label, $qty, 'website');
		}

		foreach ($square_rows as $row) {
			if (
				!is_array($row)
				|| (($row['treatment'] ?? '') !== 'counted')
				|| empty($row['is_direct_ticket'])
				|| (int) ($row['net_cents'] ?? 0) > 0
			) {
				continue;
			}
			$label = vms_dt_bvm_reporting_complimentary_label((string) ($row['line_name'] ?? ''), 'square');
			$add_category($label, max(0, (int) ($row['quantity'] ?? 0)), 'square');
		}

		$categories = array_values($categories);
		usort($categories, static function (array $left, array $right): int {
			$qty_order = ((int) ($right['qty'] ?? 0)) <=> ((int) ($left['qty'] ?? 0));
			return $qty_order !== 0 ? $qty_order : strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
		});

		return $categories;
	}

	/** @return array<string,mixed> */
	function vms_dt_bvm_reporting_vendor_portal_summary(int $event_plan_id): array
	{
		$event_date = (string) get_post_meta($event_plan_id, '_vms_event_date', true);
		$website = (array) vms_dt_reporting_build_website_detail_rows($event_plan_id);
		$website_ticket_rows = array();

		foreach ((array) ($website['ticket_rows'] ?? array()) as $entry) {
			if (!is_array($entry)) {
				continue;
			}

			$sold_date = (string) ($entry['sold_date'] ?? '');
			if ($sold_date === '') {
				$sold_date = substr((string) ($entry['sold_datetime'] ?? ''), 0, 10);
			}
			if ($sold_date !== '' && $event_date !== '' && $sold_date > $event_date) {
				continue;
			}
			$website_ticket_rows[] = $entry;
		}

		$square_location_id = trim((string) get_post_meta($event_plan_id, '_vms_square_location_id', true));
		$square = (array) vms_dt_reporting_build_square_line_evidence($event_plan_id, array(
			'event_plan_id' => $event_plan_id,
			'square_location_id' => $square_location_id,
			'square_scope_mode' => 'full_day',
			'sold_from' => '',
			'sold_to' => '',
		));
		$ticket_sources = (array) vms_dt_reporting_build_ticket_source_rollup(
			array(),
			array(
				'website' => array('ticket_rows' => $website_ticket_rows),
				'square' => $square,
			)
		);

		$online_qty = max(0, (int) ($ticket_sources['website_paid_ticket_qty'] ?? 0));
		$online_net_cents = max(0, (int) ($ticket_sources['website_paid_ticket_revenue_cents'] ?? 0));
		$excluded_free_online_qty = max(0, (int) ($ticket_sources['website_free_ticket_qty'] ?? 0));
		$door_qty = max(0, (int) ($ticket_sources['square_ticket_qty'] ?? 0));
		$door_paid_qty = max(0, (int) ($ticket_sources['square_paid_ticket_qty'] ?? 0));
		$door_free_qty = max(0, (int) ($ticket_sources['square_free_ticket_qty'] ?? 0));
		$door_gross_cents = max(0, (int) ($ticket_sources['square_paid_ticket_revenue_cents'] ?? 0));
		$website_rows_seen = max(0, (int) ($ticket_sources['website_rows_seen'] ?? count($website_ticket_rows)));
		$door_rows_seen = max(0, (int) ($ticket_sources['square_rows_seen'] ?? 0));

		$headcount = max(0, (int) ($ticket_sources['ticketed_attendance_qty'] ?? ($online_qty + $excluded_free_online_qty + $door_qty)));
		$sales_cents = max(0, (int) ($ticket_sources['paid_ticket_revenue_cents'] ?? ($online_net_cents + $door_gross_cents)));
		$free_ticket_qty_total = max(0, (int) ($ticket_sources['free_ticket_qty_total'] ?? ($excluded_free_online_qty + $door_free_qty)));
		$paid_ticket_qty_total = max(0, (int) ($ticket_sources['paid_ticket_qty_total'] ?? ($online_qty + $door_paid_qty)));
		$complimentary_categories = vms_dt_bvm_reporting_complimentary_categories(
			$website_ticket_rows,
			(array) ($square['ticket_rows'] ?? array())
		);
		$has_countable_data = !empty($ticket_sources['has_countable_data'])
			|| ($headcount > 0)
			|| ($website_rows_seen > 0)
			|| ($door_rows_seen > 0)
			|| ($free_ticket_qty_total > 0);

		$label = __('Paid ticket sales', 'vms-data-tools');
		if ($free_ticket_qty_total > 0) {
			$label = __('Ticketed attendance', 'vms-data-tools');
		} elseif ($door_paid_qty > 0) {
			$label = __('Paid ticket sales + counted door sales', 'vms-data-tools');
		}

		return array(
			'available' => true,
			'calculated' => true,
			'source' => 'data_tools_merged_ticket_sales',
			'source_label' => $label,
			'source_mode' => 'data_tools_live',
			'label' => $label,
			'updated_label' => wp_date('M j, Y g:ia', current_time('timestamp'), wp_timezone()),
			'paid_qty' => $paid_ticket_qty_total,
			'free_qty' => $free_ticket_qty_total,
			'total_qty' => $headcount,
			'revenue_cents' => $sales_cents,
			'headcount' => $headcount,
			'online_qty' => $online_qty,
			'online_net_cents' => $online_net_cents,
			'door_qty' => $door_qty,
			'door_paid_qty' => $door_paid_qty,
			'door_free_qty' => $door_free_qty,
			'door_gross_cents' => $door_gross_cents,
			'sales_cents' => $sales_cents,
			'excluded_free_online_qty' => $excluded_free_online_qty,
			'excluded_free_ticket_qty_total' => $free_ticket_qty_total,
			'paid_ticket_qty_total' => $paid_ticket_qty_total,
			'free_ticket_qty_total' => $free_ticket_qty_total,
			'ticketed_attendance_qty' => $headcount,
			'complimentary_categories' => $complimentary_categories,
			'has_countable_data' => $has_countable_data,
			'warnings' => array_values(array_unique(array_filter(array_map('strval', (array) ($square['warnings'] ?? array()))))),
			'errors' => array_values(array_unique(array_filter(array_map('strval', (array) ($square['errors'] ?? array()))))),
		);
	}
}

if (!function_exists('vms_dt_bvm_reporting_provider')) {
	/**
	 * @param array<string,mixed> $context
	 * @return array<string,mixed>
	 */
	function vms_dt_bvm_reporting_provider(int $event_plan_id, array $context = array()): array
	{
		$event_plan_id = absint($event_plan_id);
		if ($event_plan_id <= 0) {
			return array('available' => false, 'calculated' => false);
		}

		if (!vms_dt_bvm_reporting_load_dependencies()) {
			return array(
				'available' => false,
				'calculated' => false,
				'errors' => array('Data Tools reporting dependencies are unavailable.'),
			);
		}

		$scope = sanitize_key((string) ($context['scope'] ?? 'event_command_center'));
		if ($scope === 'vendor_portal') {
			return vms_dt_bvm_reporting_vendor_portal_summary($event_plan_id);
		}

		return vms_dt_bvm_reporting_event_summary($event_plan_id);
	}
}

if (!function_exists('vms_dt_register_bvm_reporting_provider')) {
	function vms_dt_register_bvm_reporting_provider(): bool
	{
		static $registered = false;
		if ($registered) {
			return true;
		}
		if (!function_exists('bvmgr_reporting_register_provider')) {
			return false;
		}

		$registered = bvmgr_reporting_register_provider(array(
			'id' => 'vms-data-tools',
			'label' => 'VMS Data Tools',
			'version' => VMS_DT_VERSION,
			'contract_version' => 1,
			'capabilities' => array('event_ticket_sales'),
			'priority' => 20,
			'callback' => 'vms_dt_bvm_reporting_provider',
		));

		return $registered;
	}
}

vms_dt_register_bvm_reporting_provider();
add_action('plugins_loaded', 'vms_dt_register_bvm_reporting_provider', 20);
