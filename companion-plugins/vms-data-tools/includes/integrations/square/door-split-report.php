<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Door Split Report v1
 * - Uses stored orders in {$wpdb->prefix}vms_square_orders
 * - Uses Square Catalog API to map line-item catalog_object_id (variation ID) -> item categories
 * - Counts only line items whose item categories intersect the configured Door Category IDs
 */

/* =========================================================
 * Settings helpers (constants first, then options)
 * ======================================================= */

function vmsdt_square_get_env() {
	if (defined('VMS_SQUARE_ENV') && VMS_SQUARE_ENV) { return VMS_SQUARE_ENV; }
	$env = get_option('vms_square_env', 'production');
	return $env ? $env : 'production';
}

function vmsdt_square_get_access_token() {
	if (defined('VMS_SQUARE_ACCESS_TOKEN') && VMS_SQUARE_ACCESS_TOKEN) { return VMS_SQUARE_ACCESS_TOKEN; }
	$token = get_option('vms_square_access_token', '');
	return $token ? $token : '';
}

function vmsdt_square_get_location_ids() {
	if (defined('VMS_SQUARE_LOCATION_IDS') && VMS_SQUARE_LOCATION_IDS) {
		$raw = VMS_SQUARE_LOCATION_IDS;
	} else {
		$raw = (string) get_option('vms_square_location_ids', '');
	}
	$parts = array_filter(array_map('trim', explode(',', $raw)));
	return array_values($parts);
}

function vmsdt_square_get_door_category_ids() {
	$ids = get_option('vms_square_door_category_ids', array());
	if (is_string($ids)) {
		$maybe = json_decode($ids, true);
		if (is_array($maybe)) { $ids = $maybe; }
	}
	if (!is_array($ids)) { $ids = array(); }
	$ids = array_values(array_filter(array_map('trim', $ids)));
	return $ids;
}

function vmsdt_square_api_base_url() {
	$env = vmsdt_square_get_env();
	return ($env === 'sandbox') ? 'https://connect.squareupsandbox.com' : 'https://connect.squareup.com';
}

function vmsdt_square_api_request($method, $path, $body = null) {
	$token = vmsdt_square_get_access_token();
	if (!$token) {
		return array('ok' => false, 'error' => 'Missing Square access token.');
	}

	$url = rtrim(vmsdt_square_api_base_url(), '/') . $path;

	$args = array(
		'method'  => strtoupper($method),
		'timeout' => 30,
		'headers' => array(
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
			// Square recommends specifying a Square-Version; docs currently show 2026-01-22.
			'Square-Version' => '2026-01-22',
		),
	);

	if ($body !== null) {
		$args['body'] = wp_json_encode($body);
	}

	$res = wp_remote_request($url, $args);
	if (is_wp_error($res)) {
		return array('ok' => false, 'error' => $res->get_error_message());
	}

	$code = (int) wp_remote_retrieve_response_code($res);
	$raw  = (string) wp_remote_retrieve_body($res);
	$data = json_decode($raw, true);

	if ($code < 200 || $code >= 300) {
		$msg = 'Square API error.';
		if (is_array($data) && !empty($data['errors'][0]['detail'])) {
			$msg = $data['errors'][0]['detail'];
		}
		return array('ok' => false, 'error' => $msg, 'http' => $code, 'raw' => $raw);
	}

	if (!is_array($data)) {
		return array('ok' => false, 'error' => 'Square API returned non-JSON response.', 'http' => $code, 'raw' => $raw);
	}

	return array('ok' => true, 'data' => $data);
}

/* =========================================================
 * Catalog mapping cache: variation_id -> category_ids[]
 * ======================================================= */

function vmsdt_square_get_variation_category_cache() {
	$cache = get_option('vms_square_variation_categories_cache', array());
	if (!is_array($cache)) { $cache = array(); }
	return $cache;
}

function vmsdt_square_set_variation_category_cache($cache) {
	if (!is_array($cache)) { $cache = array(); }
	update_option('vms_square_variation_categories_cache', $cache, false);
}

function vmsdt_square_catalog_map_variations_to_categories($variation_ids, &$stats) {
	$stats = array_merge(array(
		'variations_total' => 0,
		'variations_missing' => 0,
		'batch_calls' => 0,
		'errors' => array(),
	), (array) $stats);

	$variation_ids = array_values(array_unique(array_filter($variation_ids)));
	$stats['variations_total'] = count($variation_ids);

	$cache = vmsdt_square_get_variation_category_cache();

	$missing = array();
	foreach ($variation_ids as $vid) {
		if (empty($cache[$vid]) || !is_array($cache[$vid])) {
			$missing[] = $vid;
		}
	}
	$stats['variations_missing'] = count($missing);

	// Nothing to fetch
	if (!$missing) {
		return $cache;
	}

	// Square batch retrieve allows up to 1000 IDs. We'll do 250 to stay conservative.
	$chunks = array_chunk($missing, 250);

	foreach ($chunks as $chunk) {
		$stats['batch_calls']++;

		$resp = vmsdt_square_api_request('POST', '/v2/catalog/batch-retrieve', array(
			'object_ids' => $chunk,
			'include_related_objects' => true,
		));

		if (empty($resp['ok'])) {
			$stats['errors'][] = $resp['error'] ?? 'Unknown catalog fetch error.';
			continue;
		}

		$data = $resp['data'];
		$objects = isset($data['objects']) && is_array($data['objects']) ? $data['objects'] : array();
		$related = isset($data['related_objects']) && is_array($data['related_objects']) ? $data['related_objects'] : array();

		// variation_id -> item_id
		$variation_to_item = array();
		foreach ($objects as $obj) {
			if (!is_array($obj)) { continue; }
			if (($obj['type'] ?? '') !== 'ITEM_VARIATION') { continue; }
			$vid = $obj['id'] ?? '';
			$item_id = $obj['item_variation_data']['item_id'] ?? '';
			if ($vid && $item_id) {
				$variation_to_item[$vid] = $item_id;
			}
		}

		// item_id -> category_ids[]
		$item_to_categories = array();
		foreach ($related as $obj) {
			if (!is_array($obj)) { continue; }
			if (($obj['type'] ?? '') !== 'ITEM') { continue; }

			$item_id = $obj['id'] ?? '';
			if (!$item_id) { continue; }

			$item_data = $obj['item_data'] ?? array();
			$cat_ids = array();

			// Preferred: item_data.categories[] objects with id
			if (!empty($item_data['categories']) && is_array($item_data['categories'])) {
				foreach ($item_data['categories'] as $c) {
					if (is_array($c) && !empty($c['id'])) { $cat_ids[] = (string) $c['id']; }
					if (is_string($c) && $c) { $cat_ids[] = $c; }
				}
			}

			// Fallbacks: category_id or category_ids
			if (!empty($item_data['category_id'])) {
				$cat_ids[] = (string) $item_data['category_id'];
			}
			if (!empty($item_data['category_ids']) && is_array($item_data['category_ids'])) {
				foreach ($item_data['category_ids'] as $c) {
					if (is_string($c) && $c) { $cat_ids[] = $c; }
				}
			}

			$cat_ids = array_values(array_unique(array_filter(array_map('trim', $cat_ids))));
			$item_to_categories[$item_id] = $cat_ids;
		}

		// Fill cache for variations in this batch
		foreach ($chunk as $vid) {
			$item_id = $variation_to_item[$vid] ?? '';
			$cache[$vid] = $item_id ? ($item_to_categories[$item_id] ?? array()) : array();
		}
	}

	vmsdt_square_set_variation_category_cache($cache);
	return $cache;
}

/* =========================================================
 * Money helpers
 * ======================================================= */

function vmsdt_square_format_money_from_cents($cents) {
	$amt = ((int) $cents) / 100;
	return '$' . number_format($amt, 2);
}

/* =========================================================
 * Compute report
 * ======================================================= */

function vmsdt_square_compute_door_split_report($location_id, $start_date, $end_date, $band_pct) {
	global $wpdb;

	$band_pct = max(0, min(100, (int) $band_pct));

	$table = $wpdb->prefix . 'vms_square_orders';

	$door_category_ids = vmsdt_square_get_door_category_ids();
	if (!$door_category_ids) {
		return array('ok' => false, 'error' => 'Door Category IDs are not set yet.');
	}

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT square_order_id, business_date, line_items_json
			 FROM {$table}
			 WHERE location_id = %s
			   AND business_date BETWEEN %s AND %s
			   AND state = 'COMPLETED'
			 ORDER BY business_date ASC",
			$location_id, $start_date, $end_date
		),
		ARRAY_A
	);

	if (!is_array($rows)) { $rows = array(); }

	// Pass 1: collect variations + stash decoded line items
	$variation_ids = array();
	$orders = array();

	foreach ($rows as $r) {
		$line_items = json_decode($r['line_items_json'] ?? '[]', true);
		if (!is_array($line_items)) { $line_items = array(); }

		foreach ($line_items as $li) {
			if (!is_array($li)) { continue; }
			$vid = $li['catalog_object_id'] ?? '';
			if ($vid) { $variation_ids[] = $vid; }
		}

		$orders[] = array(
			'order_id' => $r['square_order_id'],
			'date' => $r['business_date'],
			'line_items' => $line_items,
		);
	}

	// Map variations -> categories
	$catalog_stats = array();
	$variation_category_cache = vmsdt_square_catalog_map_variations_to_categories($variation_ids, $catalog_stats);

	// Pass 2: compute totals
	$days = array();
	$totals = array(
		'gross_cents' => 0,
		'discount_cents' => 0,
		'tax_cents' => 0,
		'total_cents' => 0,
		'orders_seen' => 0,
		'door_line_items' => 0,
		'skipped_uncategorized' => 0,
	);

	foreach ($orders as $o) {
		$totals['orders_seen']++;

		$d = $o['date'];
		if (!isset($days[$d])) {
			$days[$d] = array(
				'gross_cents' => 0,
				'discount_cents' => 0,
				'tax_cents' => 0,
				'total_cents' => 0,
				'door_line_items' => 0,
				'skipped_uncategorized' => 0,
			);
		}

		foreach ($o['line_items'] as $li) {
			if (!is_array($li)) { continue; }

			$vid = $li['catalog_object_id'] ?? '';
			if (!$vid) {
				$totals['skipped_uncategorized']++;
				$days[$d]['skipped_uncategorized']++;
				continue;
			}

			$cats = $variation_category_cache[$vid] ?? array();
			if (!$cats || !array_intersect($cats, $door_category_ids)) {
				continue;
			}

			$gross = (int) ($li['gross_sales_money']['amount'] ?? 0);
			$disc  = (int) ($li['total_discount_money']['amount'] ?? 0);
			$tax   = (int) ($li['total_tax_money']['amount'] ?? 0);
			$total = (int) ($li['total_money']['amount'] ?? 0);

			$totals['gross_cents'] += $gross;
			$totals['discount_cents'] += $disc;
			$totals['tax_cents'] += $tax;
			$totals['total_cents'] += $total;
			$totals['door_line_items']++;

			$days[$d]['gross_cents'] += $gross;
			$days[$d]['discount_cents'] += $disc;
			$days[$d]['tax_cents'] += $tax;
			$days[$d]['total_cents'] += $total;
			$days[$d]['door_line_items']++;
		}
	}

	// Compute split (net door excludes tax)
	$net_cents = max(0, $totals['gross_cents'] - $totals['discount_cents']);
	$band_cents = (int) round($net_cents * ($band_pct / 100), 0);
	$house_cents = max(0, $net_cents - $band_cents);

	return array(
		'ok' => true,
		'door_category_ids' => $door_category_ids,
		'catalog_stats' => $catalog_stats,
		'totals' => $totals,
		'net_cents' => $net_cents,
		'band_pct' => $band_pct,
		'band_cents' => $band_cents,
		'house_cents' => $house_cents,
		'days' => $days,
		'notes' => array(
			'Split base is Gross Door (pre-tax) minus Discounts. Ticket tax is shown but excluded from the split base.',
			'Refunds/chargebacks are not subtracted in v1 unless they appear as negative/discount adjustments in the line items.',
			'Catalog reads require a token with ITEMS_READ permission.',
		),
	);
}

/* =========================================================
 * Render section on the Tools page
 * ======================================================= */

function vmsdt_square_render_door_split_report_section() {
	if (function_exists('vms_square_current_user_can_manage')) {
		if (!vms_square_current_user_can_manage()) { return; }
	} elseif (!current_user_can('manage_options')) {
		return;
	}

	$location_ids = vmsdt_square_get_location_ids();
	$default_loc = $location_ids ? $location_ids[0] : '';

	$tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
	$today = function_exists('wp_date') ? wp_date('Y-m-d', time(), $tz) : gmdate('Y-m-d');

	$location_id = isset($_POST['vmsdt_door_loc']) ? sanitize_text_field($_POST['vmsdt_door_loc']) : $default_loc;
	$start_date  = isset($_POST['vmsdt_door_start']) ? sanitize_text_field($_POST['vmsdt_door_start']) : $today;
	$end_date    = isset($_POST['vmsdt_door_end']) ? sanitize_text_field($_POST['vmsdt_door_end']) : $today;
	$band_pct    = isset($_POST['vmsdt_door_pct']) ? (int) $_POST['vmsdt_door_pct'] : 70;

	echo '<hr />';
	echo '<h2>Door Split Report (v1)</h2>';

	if (!$default_loc) {
		echo '<div class="notice notice-error"><p>No Square location IDs found. Set VMS_SQUARE_LOCATION_IDS in wp-config.php or set the plugin location IDs option.</p></div>';
		return;
	}

	echo '<form method="post" style="margin-top:12px;">';
	wp_nonce_field('vmsdt_door_split_report', 'vmsdt_door_split_report_nonce');

	echo '<table class="form-table" role="presentation">';
	echo '<tr><th scope="row"><label for="vmsdt_door_loc">Location</label></th><td>';
	echo '<select name="vmsdt_door_loc" id="vmsdt_door_loc">';
	foreach ($location_ids as $lid) {
		$sel = selected($location_id, $lid, false);
		echo '<option value="' . esc_attr($lid) . '"' . $sel . '>' . esc_html($lid) . '</option>';
	}
	echo '</select></td></tr>';

	echo '<tr><th scope="row"><label for="vmsdt_door_start">Start date</label></th><td>';
	echo '<input type="date" name="vmsdt_door_start" id="vmsdt_door_start" value="' . esc_attr($start_date) . '" /></td></tr>';

	echo '<tr><th scope="row"><label for="vmsdt_door_end">End date</label></th><td>';
	echo '<input type="date" name="vmsdt_door_end" id="vmsdt_door_end" value="' . esc_attr($end_date) . '" /></td></tr>';

	echo '<tr><th scope="row"><label for="vmsdt_door_pct">Band percent</label></th><td>';
	echo '<input type="number" min="0" max="100" step="1" name="vmsdt_door_pct" id="vmsdt_door_pct" value="' . esc_attr($band_pct) . '" /> %';
	echo '<p class="description">Split base excludes ticket tax. Discounts are subtracted.</p>';
	echo '</td></tr>';

	echo '</table>';

	submit_button('Run Door Split Report', 'primary', 'vmsdt_door_run', false);
	echo '</form>';

	// Run report if submitted
	if (!empty($_POST['vmsdt_door_run'])) {
		if (empty($_POST['vmsdt_door_split_report_nonce']) || !wp_verify_nonce($_POST['vmsdt_door_split_report_nonce'], 'vmsdt_door_split_report')) {
			echo '<div class="notice notice-error"><p>Security check failed. Please try again.</p></div>';
			return;
		}

		$res = vmsdt_square_compute_door_split_report($location_id, $start_date, $end_date, $band_pct);

		if (empty($res['ok'])) {
			$msg = esc_html($res['error'] ?? 'Report failed.');
			echo '<div class="notice notice-error"><p>' . $msg . '</p></div>';
			return;
		}

		$net = $res['net_cents'];
		$band = $res['band_cents'];
		$house = $res['house_cents'];

		echo '<h3>Totals</h3>';
		echo '<table class="widefat striped" style="max-width:900px;">';
		echo '<tbody>';
		echo '<tr><th>Gross Door (pre-tax)</th><td>' . esc_html(vmsdt_square_format_money_from_cents($res['totals']['gross_cents'])) . '</td></tr>';
		echo '<tr><th>Discounts</th><td>' . esc_html(vmsdt_square_format_money_from_cents($res['totals']['discount_cents'])) . '</td></tr>';
		echo '<tr><th>Net Door (split base)</th><td><strong>' . esc_html(vmsdt_square_format_money_from_cents($net)) . '</strong></td></tr>';
		echo '<tr><th>Ticket Tax collected (excluded from split)</th><td>' . esc_html(vmsdt_square_format_money_from_cents($res['totals']['tax_cents'])) . '</td></tr>';
		echo '<tr><th>Band (' . (int) $res['band_pct'] . '% of net)</th><td><strong>' . esc_html(vmsdt_square_format_money_from_cents($band)) . '</strong></td></tr>';
		echo '<tr><th>House</th><td><strong>' . esc_html(vmsdt_square_format_money_from_cents($house)) . '</strong></td></tr>';
		echo '</tbody>';
		echo '</table>';

		echo '<p class="description" style="max-width:900px;margin-top:10px;">';
		echo 'Orders scanned: ' . (int) $res['totals']['orders_seen'] . ' | ';
		echo 'Door line items counted: ' . (int) $res['totals']['door_line_items'] . ' | ';
		echo 'Uncategorized line items skipped: ' . (int) $res['totals']['skipped_uncategorized'];
		echo '</p>';

		if (!empty($res['catalog_stats']['errors'])) {
			echo '<div class="notice notice-warning"><p><strong>Catalog lookup warnings:</strong><br />';
			echo esc_html(implode(' | ', $res['catalog_stats']['errors']));
			echo '</p></div>';
		}

		echo '<h3>Daily Breakdown</h3>';
		echo '<table class="widefat striped" style="max-width:1100px;">';
		echo '<thead><tr>';
		echo '<th>Date</th><th>Gross Door</th><th>Discounts</th><th>Net Door</th><th>Tax</th><th>Band</th><th>House</th><th>Door Items</th>';
		echo '</tr></thead><tbody>';

		foreach ($res['days'] as $date => $d) {
			$day_net = max(0, (int) $d['gross_cents'] - (int) $d['discount_cents']);
			$day_band = (int) round($day_net * ($res['band_pct'] / 100), 0);
			$day_house = max(0, $day_net - $day_band);

			echo '<tr>';
			echo '<td>' . esc_html($date) . '</td>';
			echo '<td>' . esc_html(vmsdt_square_format_money_from_cents($d['gross_cents'])) . '</td>';
			echo '<td>' . esc_html(vmsdt_square_format_money_from_cents($d['discount_cents'])) . '</td>';
			echo '<td><strong>' . esc_html(vmsdt_square_format_money_from_cents($day_net)) . '</strong></td>';
			echo '<td>' . esc_html(vmsdt_square_format_money_from_cents($d['tax_cents'])) . '</td>';
			echo '<td><strong>' . esc_html(vmsdt_square_format_money_from_cents($day_band)) . '</strong></td>';
			echo '<td><strong>' . esc_html(vmsdt_square_format_money_from_cents($day_house)) . '</strong></td>';
			echo '<td>' . (int) $d['door_line_items'] . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		echo '<p class="description" style="max-width:1100px;margin-top:10px;">';
		echo esc_html(implode(' ', $res['notes']));
		echo '</p>';
	}
}
