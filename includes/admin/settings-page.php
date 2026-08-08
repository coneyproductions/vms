<?php
if (!defined('ABSPATH')) exit;

/**
 * Data Integrity Scan (on-demand)
 * - Scans Event Plans for trashed/missing Vendors, orphaned Venues, and missing/trashed calendar events
 * - Flags affected Event Plans as "Needs attention"
 * - Forces Published/Ready Event Plans back to Draft for safety
 */
add_action('admin_post_vms_integrity_scan', 'vms_handle_integrity_scan');

add_action('admin_post_vms_set_default_venue', 'vms_handle_set_default_venue');
add_action('admin_post_vms_sync_entitlement_images', 'vms_handle_sync_entitlement_images');
// Ticketing inventory tools (Preview → Commit)
add_action('admin_post_vms_ticketing_stock_preview', 'vms_handle_ticketing_stock_preview');
add_action('admin_post_vms_ticketing_stock_commit', 'vms_handle_ticketing_stock_commit');
add_action('admin_post_vms_ticketing_stock_csv', 'vms_handle_ticketing_stock_csv');
add_action('admin_post_vms_ticketing_stock_clear_preview', 'vms_handle_ticketing_stock_clear_preview');

// Back-compat: older Settings button action
add_action('admin_post_vms_reconcile_ticketing_stock', 'vms_handle_reconcile_ticketing_stock');

if (!function_exists('vms_settings_page_help_button_allowed_html')) {
	function vms_settings_page_help_button_allowed_html(): array
	{
		return array(
			'button' => array(
				'type' => true,
				'class' => true,
				'data-vms-tour-start' => true,
				'data-vms-help-open' => true,
				'data-vms-tour' => true,
			),
		);
	}
}

if (!function_exists('vms_settings_page_dropdown_allowed_html')) {
	function vms_settings_page_dropdown_allowed_html(): array
	{
		return array(
			'select' => array(
				'id' => true,
				'name' => true,
				'class' => true,
			),
			'option' => array(
				'value' => true,
				'selected' => true,
			),
		);
	}
}

function vms_handle_set_default_venue(): void
{
  if (!current_user_can('manage_options')) {
    wp_die('Insufficient permissions.');
  }

  $venue_id = isset($_GET['venue_id']) ? absint(wp_unslash($_GET['venue_id'])) : 0;
  if ($venue_id <= 0) {
    wp_die('Missing venue_id.');
  }

  $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
  if (!wp_verify_nonce($nonce, 'vms_set_default_venue_' . $venue_id)) {
    wp_die('Invalid nonce.');
  }

  if (get_post_type($venue_id) !== 'vms_venue' || get_post_status($venue_id) !== 'publish') {
    wp_die('Venue must be published before it can be set as the Default Venue.');
  }

  $opts = (array) get_option('vms_settings', array());
  $opts['default_venue_id'] = (int) $venue_id;
  update_option('vms_settings', $opts);

  $redirect = add_query_arg(
    array(
      'page'       => 'vms-settings',
      'vms_notice' => 'default_venue_set',
    ),
    admin_url('admin.php')
  );

  wp_safe_redirect($redirect);
  exit;
}
function vms_handle_integrity_scan(): void
{
	if (!current_user_can('manage_options')) {
		wp_die('Insufficient permissions.');
	}

	check_admin_referer('vms_integrity_scan');

	$mode  = isset($_POST['mode']) ? sanitize_key((string) $_POST['mode']) : 'all';
	$limit = isset($_POST['limit']) ? (int) $_POST['limit'] : 500;

	// Safety: clamp limit
	if ($limit < 1) $limit = 500;
	if ($limit > 5000) $limit = 5000;

	$results = array();
	if ($mode === 'vendors') {
		$results = vms_integrity_scan_event_plans_for_missing_vendors($limit);
	} elseif ($mode === 'venues') {
		$results = vms_integrity_scan_event_plans_for_orphaned_venues($limit);
	} elseif ($mode === 'events') {
		$results = vms_integrity_scan_event_plans_for_orphaned_calendar_events($limit);
	} else {
		$results = vms_integrity_scan_event_plans_all($limit);
		$mode = 'all';
	}

	set_transient('vms_integrity_scan_last', array(
		'ts' => time(),
		'mode' => $mode,
		'limit' => $limit,
		'results' => $results,
	), 10 * MINUTE_IN_SECONDS);

	wp_safe_redirect(add_query_arg(array(
		'page' => 'vms-settings',
		'vms_scan_done' => '1',
	), admin_url('admin.php')));
	exit;
}

function vms_handle_sync_entitlement_images(): void
{
	if (!current_user_can('manage_options')) {
		wp_die('Insufficient permissions.');
	}

	check_admin_referer('vms_sync_entitlement_images');

	$ent_meta_key = function_exists('vms_ticketing_v2_product_meta_key')
		? vms_ticketing_v2_product_meta_key('ticketing_entitlement_id')
		: '_vms_ticketing_entitlement_id';
	$role_meta_key = function_exists('vms_ticketing_v2_product_meta_key')
		? vms_ticketing_v2_product_meta_key('product_role')
		: '_vms_product_role';
	$plan_meta_key = function_exists('vms_ticketing_v2_product_meta_key')
		? vms_ticketing_v2_product_meta_key('event_plan_id')
		: '_vms_event_plan_id';

	$product_ids = get_posts(array(
		'post_type' => 'product',
		'post_status' => array('publish', 'future', 'draft', 'pending', 'private'),
		'posts_per_page' => -1,
		'fields' => 'ids',
		'no_found_rows' => true,
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This capability- and nonce-gated sync must enumerate every product carrying one of the plugin-owned ticketing markers.
		'meta_query' => array(
			'relation' => 'OR',
			array(
				'key' => $ent_meta_key,
				'compare' => 'EXISTS',
			),
			array(
				'key' => $role_meta_key,
				'value' => 'entitlement',
				'compare' => '=',
			),
			array(
				'key' => $role_meta_key,
				'value' => 'ga_ticket',
				'compare' => '=',
			),
			array(
				'key' => $plan_meta_key,
				'compare' => 'EXISTS',
			),
		),
	));
	if (!is_array($product_ids)) {
		$product_ids = array();
	}

	$summary = array(
		'ts' => time(),
		'checked' => 0,
		'updated' => 0,
		'skipped' => 0,
		'errors' => 0,
		'results' => array(),
	);

	foreach ($product_ids as $pid_raw) {
		$pid = absint($pid_raw);
		if ($pid <= 0) {
			continue;
		}
		$summary['checked']++;

		$role = sanitize_key((string) get_post_meta($pid, $role_meta_key, true));
		$plan_id = absint(get_post_meta($pid, $plan_meta_key, true));
		if ($role === '' && $plan_id > 0 && absint(get_post_meta($pid, '_tribe_wooticket_for_event', true)) > 0) {
			$role = 'ga_ticket';
		}

		if ($role === 'ga_ticket') {
			if ($plan_id > 0 && function_exists('vms_ticketing_v2_sync_ticket_product_image_with_result')) {
				$res = vms_ticketing_v2_sync_ticket_product_image_with_result($pid, $plan_id);
			} else {
				$res = array(
					'status' => $plan_id > 0 ? 'error_missing_sync_function' : 'error_missing_plan',
					'product_id' => $pid,
					'plan_id' => $plan_id,
					'image_id' => 0,
					'message' => $plan_id > 0 ? 'ticket_image_sync_function_unavailable' : 'missing_event_plan_marker',
				);
			}
		} else {
			$entitlement_id = '';
			if (function_exists('vms_entitlements_get_product_entitlement_id')) {
				$entitlement_id = vms_entitlements_get_product_entitlement_id($pid);
			}
			if ($entitlement_id === '') {
				$entitlement_id = sanitize_key((string) get_post_meta($pid, $ent_meta_key, true));
			}

			if ($entitlement_id === '') {
				$res = array(
					'status' => ($role === 'entitlement') ? 'error_missing_entitlement' : 'skipped_unknown_product',
					'product_id' => $pid,
					'entitlement_id' => '',
					'plan_id' => $plan_id,
					'image_id' => 0,
					'message' => ($role === 'entitlement') ? 'missing_entitlement_marker' : 'not_a_vms_ticket_or_entitlement_image_product',
				);
			} elseif (function_exists('vms_entitlements_sync_product_image_with_result')) {
				$res = vms_entitlements_sync_product_image_with_result($pid, $entitlement_id);
			} else {
				$res = array(
					'status' => 'error_missing_sync_function',
					'product_id' => $pid,
					'entitlement_id' => $entitlement_id,
					'plan_id' => 0,
					'image_id' => 0,
					'message' => 'sync_function_unavailable',
				);
			}
		}

		$status = isset($res['status']) ? (string) $res['status'] : '';
		if (in_array($status, array('updated', 'cleared'), true)) {
			$summary['updated']++;
		} elseif (in_array($status, array('skipped_no_image', 'skipped_current'), true)) {
			$summary['skipped']++;
		} elseif (strpos($status, 'error_') === 0) {
			$summary['errors']++;
		}

		$summary['results'][] = $res;
	}

	set_transient('vms_entitlement_image_sync_last', $summary, 10 * MINUTE_IN_SECONDS);

	$summary_msg = sprintf(
		'Backfill complete: checked=%d updated=%d skipped=%d errors=%d',
		(int) $summary['checked'],
		(int) $summary['updated'],
		(int) $summary['skipped'],
		(int) $summary['errors']
	);
	if (function_exists('vms_entitlements_sync_image_log')) {
		vms_entitlements_sync_image_log($summary_msg);
	} else {
		error_log('[VMS Ticket Product Image Sync] ' . $summary_msg);
	}

	wp_safe_redirect(add_query_arg(array(
		'page' => 'vms-settings',
		'vms_entitlement_image_sync_done' => '1',
	), admin_url('admin.php')));
	exit;
}

function vms_ticketing_stock_preview_transient_key(int $user_id): string
{
	return 'vms_ticketing_stock_preview_' . max(1, $user_id);
}

/**
 * Shared scanner.
 *
 * @return array{ts:int,mode:string,checked:int,updated:int,skipped:int,errors:int,message:string,results:array<int,array<string,mixed>>}
 */
function vms_ticketing_stock_reconcile_scan(bool $apply_changes): array
{
	$summary = array(
		'ts' => time(),
		'mode' => $apply_changes ? 'commit' : 'preview',
		'checked' => 0,
		'updated' => 0,
		'skipped' => 0,
		'errors' => 0,
		'message' => '',
		'results' => array(),
	);

	if (!function_exists('wc_get_product') || !function_exists('vms_ticketing_v2_calc_sold_qty_for_entitlement_scope')) {
		$summary['errors'] = 1;
		$summary['message'] = 'woocommerce_or_ticketing_helpers_unavailable';
		return $summary;
	}

	$k_ent = function_exists('vms_ticketing_v2_product_meta_key')
		? vms_ticketing_v2_product_meta_key('ticketing_entitlement_id')
		: '_vms_ticketing_entitlement_id';
	$k_role = function_exists('vms_ticketing_v2_product_meta_key')
		? vms_ticketing_v2_product_meta_key('product_role')
		: '_vms_product_role';
	$k_plan = function_exists('vms_ticketing_v2_product_meta_key')
		? vms_ticketing_v2_product_meta_key('event_plan_id')
		: '_vms_event_plan_id';

	$product_ids = get_posts(array(
		'post_type' => 'product',
		'post_status' => array('publish', 'future', 'draft', 'pending', 'private'),
		'posts_per_page' => -1,
		'fields' => 'ids',
		'no_found_rows' => true,
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Stock preview and repair must enumerate the complete set of products carrying plugin-owned entitlement markers.
		'meta_query' => array(
			'relation' => 'OR',
			array(
				'key' => $k_role,
				'value' => 'entitlement',
				'compare' => '=',
			),
			array(
				'key' => $k_ent,
				'compare' => 'EXISTS',
			),
		),
	));
	if (!is_array($product_ids)) {
		$product_ids = array();
	}

	foreach ($product_ids as $pid_raw) {
		$pid = absint($pid_raw);
		if ($pid <= 0) continue;
		$summary['checked']++;

		$plan_id = absint(get_post_meta($pid, $k_plan, true));
		$ent_id = sanitize_key((string) get_post_meta($pid, $k_ent, true));
		$sku = trim((string) get_post_meta($pid, '_sku', true));
		$name = get_the_title($pid);

		$capacity = absint(get_post_meta($pid, '_vms_ticketing_entitlement_capacity_v2', true));
		if ($capacity <= 0 && $plan_id > 0 && function_exists('vms_ticketing_v2_get_config') && function_exists('vms_ticketing_v2_find_entitlement_cfg')) {
			$cfg = vms_ticketing_v2_get_config($plan_id);
			$ent_cfg = ($ent_id !== '') ? vms_ticketing_v2_find_entitlement_cfg((array) $cfg, $ent_id) : null;
			if (is_array($ent_cfg)) {
				$capacity = max(0, (int) ($ent_cfg['capacity'] ?? 0));
			}
		}

		if ($plan_id <= 0 || $ent_id === '' || $capacity <= 0) {
			$summary['skipped']++;
			$summary['results'][] = array(
				'product_id' => $pid,
				'product_name' => $name,
				'sku' => $sku,
				'plan_id' => $plan_id,
				'entitlement_id' => $ent_id,
				'capacity' => $capacity,
				'old_stock' => absint(get_post_meta($pid, '_stock', true)),
				'new_stock' => null,
				'sold_qty' => null,
				'status' => 'skipped_missing_scope_or_capacity',
			);
			continue;
		}

		$sold_res = vms_ticketing_v2_calc_sold_qty_for_entitlement_scope($plan_id, $ent_id, $sku, $pid);
		if (empty($sold_res['ok'])) {
			$summary['errors']++;
			$summary['results'][] = array(
				'product_id' => $pid,
				'product_name' => $name,
				'sku' => $sku,
				'plan_id' => $plan_id,
				'entitlement_id' => $ent_id,
				'capacity' => $capacity,
				'old_stock' => absint(get_post_meta($pid, '_stock', true)),
				'new_stock' => null,
				'sold_qty' => null,
				'status' => 'error_sold_qty',
				'message' => (string) ($sold_res['message'] ?? 'sold_qty_unavailable'),
			);
			continue;
		}

		$sold_qty = max(0, absint($sold_res['sold_qty'] ?? 0));
		$remaining = max(0, $capacity - $sold_qty);

		$p = wc_get_product($pid);
		if (!$p) {
			$summary['errors']++;
			$summary['results'][] = array(
				'product_id' => $pid,
				'product_name' => $name,
				'sku' => $sku,
				'plan_id' => $plan_id,
				'entitlement_id' => $ent_id,
				'capacity' => $capacity,
				'old_stock' => absint(get_post_meta($pid, '_stock', true)),
				'new_stock' => $remaining,
				'sold_qty' => $sold_qty,
				'status' => 'error_missing_product',
			);
			continue;
		}

		$before_stock = method_exists($p, 'get_stock_quantity') ? absint($p->get_stock_quantity()) : absint(get_post_meta($pid, '_stock', true));
		$changed = ($before_stock !== $remaining);

		if ($apply_changes) {
			$reason_text = sprintf(
				/* translators: 1: capacity, 2: sold quantity, 3: remaining quantity */
				__('Manual add-on reconciliation recalculated Woo stock from capacity %1$d minus sold quantity %2$d, leaving %3$d remaining.', 'backstage-venue-manager'),
				$capacity,
				$sold_qty,
				$remaining
			);
			if (!empty($sold_res['ignored_total_sales_count'])) {
				$reason_text .= ' ' . sprintf(
					/* translators: %d: number of products */
					__('The reconciliation ignored stale Woo total_sales counters on %d related product(s) and trusted the paid-order scan instead.', 'backstage-venue-manager'),
					absint($sold_res['ignored_total_sales_count'])
				);
			}

			if (function_exists('vms_ticketing_v2_push_inventory_write_context')) {
				vms_ticketing_v2_push_inventory_write_context(array(
					'trigger_source' => 'manual_action',
					'source_function' => 'vms_ticketing_stock_reconcile_scan',
					'derivation_source' => 'manual_entitlement_stock_reconciliation',
					'confidence_level' => 'authoritative',
					'expected_effect' => ($remaining > 0) ? 'reopen' : 'close',
					'reason_text' => $reason_text,
					'writer_branch' => 'manual_entitlement_stock_reconciliation',
					'result_health' => ($remaining > 0) ? 'expected_sellable_state' : 'expected_closed_state',
				));
			} elseif (function_exists('vms_ticket_mutation_audit_push_context')) {
				vms_ticket_mutation_audit_push_context(array(
					'trigger_source' => 'manual_action',
					'source_function' => 'vms_ticketing_stock_reconcile_scan',
					'derivation_source' => 'manual_entitlement_stock_reconciliation',
					'confidence_level' => 'authoritative',
					'expected_effect' => ($remaining > 0) ? 'reopen' : 'close',
					'reason_text' => $reason_text,
					'writer_branch' => 'manual_entitlement_stock_reconciliation',
					'result_health' => ($remaining > 0) ? 'expected_sellable_state' : 'expected_closed_state',
				));
			}

			try {
				if (method_exists($p, 'set_manage_stock')) $p->set_manage_stock(true);
				if (method_exists($p, 'set_backorders')) $p->set_backorders('no');
				if (method_exists($p, 'set_stock_quantity')) $p->set_stock_quantity($remaining);
				if (method_exists($p, 'set_stock_status')) $p->set_stock_status(($remaining > 0) ? 'instock' : 'outofstock');
				$p->save();

				update_post_meta($pid, '_vms_ticketing_entitlement_capacity_v2', $capacity);
				update_post_meta($pid, '_vms_ticketing_entitlement_sold_qty_v2', $sold_qty);
				update_post_meta($pid, '_vms_ticketing_entitlement_remaining_v2', $remaining);
				update_post_meta($pid, '_vms_ticketing_entitlement_stock_reconciled_at_gmt', time());
			} finally {
				if (function_exists('vms_ticketing_v2_pop_inventory_write_context')) {
					vms_ticketing_v2_pop_inventory_write_context();
				} elseif (function_exists('vms_ticket_mutation_audit_pop_context')) {
					vms_ticket_mutation_audit_pop_context();
				}
			}
		}

		if ($changed) {
			$summary['updated']++;
		} else {
			$summary['skipped']++;
		}

		$summary['results'][] = array(
			'product_id' => $pid,
			'product_name' => $name,
			'sku' => $sku,
			'plan_id' => $plan_id,
			'entitlement_id' => $ent_id,
			'capacity' => $capacity,
			'sold_qty' => $sold_qty,
			'old_stock' => $before_stock,
			'new_stock' => $remaining,
			'status' => $changed ? ($apply_changes ? 'updated' : 'would_update') : 'no_change',
		);
	}

	return $summary;
}

function vms_handle_ticketing_stock_preview(): void
{
	if (!current_user_can('manage_options')) wp_die('Insufficient permissions.');
	$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
	if (!$nonce || !wp_verify_nonce($nonce, 'vms_ticketing_stock_preview')) wp_die('Invalid nonce.');

	$rep = vms_ticketing_stock_reconcile_scan(false);
	set_transient(vms_ticketing_stock_preview_transient_key(get_current_user_id()), $rep, 30 * MINUTE_IN_SECONDS);

	wp_safe_redirect(add_query_arg(array(
		'page' => 'vms-settings',
		'vms_ticketing_stock_preview_done' => '1',
	), admin_url('admin.php')));
	exit;
}

function vms_handle_ticketing_stock_commit(): void
{
	if (!current_user_can('manage_options')) wp_die('Insufficient permissions.');
	$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
	if (!$nonce || !wp_verify_nonce($nonce, 'vms_ticketing_stock_commit')) wp_die('Invalid nonce.');

	// Always re-scan right before applying (orders may have changed since preview).
	$rep = vms_ticketing_stock_reconcile_scan(true);
	set_transient('vms_ticketing_stock_reconcile_last', $rep, 30 * MINUTE_IN_SECONDS);
	delete_transient(vms_ticketing_stock_preview_transient_key(get_current_user_id()));

	wp_safe_redirect(add_query_arg(array(
		'page' => 'vms-settings',
		'vms_ticketing_stock_commit_done' => '1',
	), admin_url('admin.php')));
	exit;
}

function vms_handle_ticketing_stock_clear_preview(): void
{
	if (!current_user_can('manage_options')) wp_die('Insufficient permissions.');
	$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
	if (!$nonce || !wp_verify_nonce($nonce, 'vms_ticketing_stock_clear_preview')) wp_die('Invalid nonce.');
	delete_transient(vms_ticketing_stock_preview_transient_key(get_current_user_id()));
	wp_safe_redirect(add_query_arg(array('page' => 'vms-settings'), admin_url('admin.php')));
	exit;
}

function vms_handle_ticketing_stock_csv(): void
{
	if (!current_user_can('manage_options')) wp_die('Insufficient permissions.');
	$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
	if (!$nonce || !wp_verify_nonce($nonce, 'vms_ticketing_stock_csv')) wp_die('Invalid nonce.');

	$mode = isset($_GET['mode']) ? sanitize_key(wp_unslash($_GET['mode'])) : 'preview';
	$rep = null;
	if ($mode === 'commit') {
		$rep = get_transient('vms_ticketing_stock_reconcile_last');
	}
	if (!is_array($rep)) {
		$rep = get_transient(vms_ticketing_stock_preview_transient_key(get_current_user_id()));
	}
	if (!is_array($rep)) {
		wp_die('No report available. Run Preview first.');
	}

	$rows = is_array($rep['results'] ?? null) ? (array) $rep['results'] : array();

	@set_time_limit(0); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Administrator-only ticketing stock CSV export streams a bounded transient report and WordPress does not provide a native execution-limit alternative.
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename=vms-ticketing-stock-' . $mode . '-report-' . gmdate('Ymd-His') . '.csv');

	$out = fopen('php://output', 'w');
	if (!$out) {
		wp_die('Unable to write CSV.');
	}
	// Header
	fputcsv($out, array('product_id', 'product_name', 'sku', 'plan_id', 'entitlement_id', 'capacity', 'sold_qty', 'old_stock', 'new_stock', 'status', 'message'));
	foreach ($rows as $r) {
		if (!is_array($r)) continue;
		fputcsv($out, array(
			(string) ($r['product_id'] ?? ''),
			(string) ($r['product_name'] ?? ''),
			(string) ($r['sku'] ?? ''),
			(string) ($r['plan_id'] ?? ''),
			(string) ($r['entitlement_id'] ?? ''),
			(string) ($r['capacity'] ?? ''),
			(string) ($r['sold_qty'] ?? ''),
			(string) ($r['old_stock'] ?? ''),
			(string) ($r['new_stock'] ?? ''),
			(string) ($r['status'] ?? ''),
			(string) ($r['message'] ?? ''),
		));
	}
	fclose($out); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the bounded administrator CSV response stream opened on php://output; no local filesystem path or WP_Filesystem replacement applies to this HTTP output handle.
	exit;
}

// Back-compat: treat as Commit
function vms_handle_reconcile_ticketing_stock(): void
{
	if (!current_user_can('manage_options')) wp_die('Insufficient permissions.');
	$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
	if (!$nonce || !wp_verify_nonce($nonce, 'vms_reconcile_ticketing_stock')) wp_die('Invalid nonce.');

	$rep = vms_ticketing_stock_reconcile_scan(true);
	set_transient('vms_ticketing_stock_reconcile_last', $rep, 30 * MINUTE_IN_SECONDS);

	wp_safe_redirect(add_query_arg(array(
		'page' => 'vms-settings',
		'vms_ticketing_stock_commit_done' => '1',
	), admin_url('admin.php')));
	exit;
}




function vms_sanitize_settings($input)
{
  $out = array();
  $input = (array) $input;

  // timezone
  $out['timezone'] = isset($input['timezone']) ? sanitize_text_field($input['timezone']) : '';

  // toggles
  $out['sch_hide_past_default'] = !empty($input['sch_hide_past_default']) ? 1 : 0;
  $out['enable_woo'] = !empty($input['enable_woo']) ? 1 : 0;
  $out['enable_tec_publish'] = array_key_exists('enable_tec_publish', (array)$input)
    ? (!empty($input['enable_tec_publish']) ? 1 : 0)
    : 1;

  // ticketing (default OFF when unset)
  $out['ticketing_enabled_default'] = !empty($input['ticketing_enabled_default']) ? 1 : 0;
  $ticket_ui_layout = isset($input['ticket_ui_layout']) ? sanitize_key((string) $input['ticket_ui_layout']) : 'classic';
  if (!in_array($ticket_ui_layout, array('classic', 'v2', 'progressive'), true)) {
    $ticket_ui_layout = 'classic';
  }
  $out['ticket_ui_layout'] = $ticket_ui_layout;
  $out['ticket_ui_v2_admin_preview'] = array_key_exists('ticket_ui_v2_admin_preview', $input)
    ? (!empty($input['ticket_ui_v2_admin_preview']) ? 1 : 0)
    : 0;
  $ticket_ui_availability_display = isset($input['ticket_ui_availability_display']) ? sanitize_key((string) $input['ticket_ui_availability_display']) : '';
  if (!in_array($ticket_ui_availability_display, array('always', 'low', 'hide'), true)) {
    $ticket_ui_availability_display = !empty($input['ticket_ui_show_availability']) ? 'low' : 'hide';
  }
  $out['ticket_ui_availability_display'] = $ticket_ui_availability_display;
  $out['ticket_ui_availability_low_threshold'] = max(1, absint($input['ticket_ui_availability_low_threshold'] ?? 25));
  $out['ticket_ui_show_availability'] = ($ticket_ui_availability_display !== 'hide') ? 1 : 0;
  $ticket_ui_sale_availability_display = isset($input['ticket_ui_sale_availability_display']) ? sanitize_key((string) $input['ticket_ui_sale_availability_display']) : 'when_capped';
  if (!in_array($ticket_ui_sale_availability_display, array('when_capped', 'low', 'hide'), true)) {
    $ticket_ui_sale_availability_display = 'when_capped';
  }
  $out['ticket_ui_sale_availability_display'] = $ticket_ui_sale_availability_display;
  $out['ticket_ui_sale_availability_low_threshold'] = max(1, absint($input['ticket_ui_sale_availability_low_threshold'] ?? 10));
  $out['ticket_ui_help_tickets_enabled'] = array_key_exists('ticket_ui_help_tickets_enabled', $input)
    ? (!empty($input['ticket_ui_help_tickets_enabled']) ? 1 : 0)
    : 0;
  $out['ticket_ui_help_addons_enabled'] = array_key_exists('ticket_ui_help_addons_enabled', $input)
    ? (!empty($input['ticket_ui_help_addons_enabled']) ? 1 : 0)
    : 0;
  $out['ticket_ui_help_tickets_default'] = isset($input['ticket_ui_help_tickets_default'])
    ? wp_kses_post((string) $input['ticket_ui_help_tickets_default'])
    : '';
  $out['ticket_ui_help_addons_default'] = isset($input['ticket_ui_help_addons_default'])
    ? wp_kses_post((string) $input['ticket_ui_help_addons_default'])
    : '';
  $out['ticket_ui_addons_heading'] = isset($input['ticket_ui_addons_heading'])
    ? sanitize_text_field((string) $input['ticket_ui_addons_heading'])
    : '';
  $out['ticket_ui_addons_subtext'] = isset($input['ticket_ui_addons_subtext'])
    ? sanitize_text_field((string) $input['ticket_ui_addons_subtext'])
    : '';

  // default venue
  $out['default_venue_id'] = isset($input['default_venue_id']) ? absint($input['default_venue_id']) : 0;

  // Tax: W-9 provider (operator-easy)
  // Allowed values:
  // - upload
  // - quickbooks_email
  // - tax1099_email
  $provider = isset($input['tax_w9_provider']) ? sanitize_text_field($input['tax_w9_provider']) : '';

  // Backward-compat mapping (if older key exists in the posted array)
  if ($provider === '' && isset($input['tax_w9_mode'])) {
    $legacy = sanitize_text_field($input['tax_w9_mode']);

    if ($legacy === 'upload') $provider = 'upload';
    if ($legacy === 'tax1099_direct') $provider = 'tax1099_email';
    if ($legacy === 'tax1099') $provider = 'tax1099_email';
    if ($legacy === 'external') $provider = 'tax1099_email';
    if ($legacy === 'quickbooks') $provider = 'quickbooks_email';
    if ($legacy === 'qbo') $provider = 'quickbooks_email';
  }

  if (!in_array($provider, array('upload', 'quickbooks_email', 'tax1099_email'), true)) {
    $provider = 'upload';
  }

  $out['tax_w9_provider'] = $provider;

  // Interface: help mode
  $help_mode = isset($input['help_mode']) ? sanitize_key((string) $input['help_mode']) : 'basic';
  if (!in_array($help_mode, array('off', 'basic', 'guided'), true)) {
    $help_mode = 'basic';
  }
  $out['help_mode'] = $help_mode;

  // Admissions / Guest List
  $max_party = isset($input['vms_admission_max_party_size']) ? absint($input['vms_admission_max_party_size']) : 6;
  if ($max_party < 1) $max_party = 6;
  if ($max_party > 100) $max_party = 100;
  $out['vms_admission_max_party_size'] = $max_party;
  $out['vms_admission_allow_uncheckin'] = !empty($input['vms_admission_allow_uncheckin']) ? 1 : 0;
  $out['vms_admission_allow_uncheckin_for_door'] = !empty($input['vms_admission_allow_uncheckin_for_door']) ? 1 : 0;
  $out['vms_admission_door_show_phone'] = !empty($input['vms_admission_door_show_phone']) ? 1 : 0;
  $out['availability_date_dispatch_enabled'] = array_key_exists('availability_date_dispatch_enabled', $input)
    ? (!empty($input['availability_date_dispatch_enabled']) ? 1 : 0)
    : 1;

  // Calendar (Core)
  $parse_map = function ($raw): array {
	    if (function_exists('vms_calendar_parse_assoc_map')) {
	      return (array) vms_calendar_parse_assoc_map($raw);
	    }
	    if (is_array($raw)) return $raw;
	    if (!is_string($raw)) return array();
	    $raw = trim($raw);
	    if ($raw === '') return array();
	    $decoded = vms_json_decode_associative($raw, 16);
	    if (!empty($decoded['ok']) && is_array($decoded['value']) && vms_json_decoded_is_object($decoded['value'], (string) ($decoded['top_level_token'] ?? ''))) {
	      return $decoded['value'];
	    }
	    $out = array();
	    $lines = preg_split('/\r\n|\r|\n/', $raw) ?: array();
    foreach ($lines as $line) {
      $line = trim((string) $line);
      if ($line === '' || strpos($line, '#') === 0) continue;
      $parts = preg_split('/\s*[:=]\s*/', $line, 2);
      if (!is_array($parts) || count($parts) < 2) continue;
      $k = sanitize_key((string) $parts[0]);
      $v = trim((string) $parts[1]);
      if ($k === '') continue;
      $out[$k] = $v;
    }
    return $out;
  };

  $sanitize_bool_map = function ($raw) use ($parse_map): array {
    $out_map = array();
    foreach ($parse_map($raw) as $slug => $value) {
      $k = sanitize_key((string) $slug);
      if ($k === '') continue;
      if (is_array($value) || is_object($value) || $value === null) continue;
      if (is_string($value) && trim($value) === '') continue; // preserve "use default" by omitting key
      $out_map[$k] = (!empty($value) && $value !== '0' && strtolower((string) $value) !== 'false') ? 1 : 0;
    }
    return $out_map;
  };

  $sanitize_int_map = function ($raw) use ($parse_map): array {
    $out_map = array();
    foreach ($parse_map($raw) as $slug => $value) {
      $k = sanitize_key((string) $slug);
      if ($k === '') continue;
      if (is_array($value) || is_object($value) || $value === null) continue;
      if (is_string($value) && trim($value) === '') continue; // preserve "use default" by omitting key
      $out_map[$k] = max(0, absint($value));
    }
    return $out_map;
  };

  if (function_exists('vms_calendar_sanitize_icon_map')) {
    $out['calendar_vendor_type_icons'] = (array) vms_calendar_sanitize_icon_map($input['calendar_vendor_type_icons'] ?? array());
  } else {
    $icon_map = array();
    foreach ($parse_map($input['calendar_vendor_type_icons'] ?? array()) as $slug => $icon) {
      $k = sanitize_key((string) $slug);
      $v = sanitize_text_field((string) $icon);
      if ($k === '' || $v === '') continue;
      $icon_map[$k] = $v;
    }
    $out['calendar_vendor_type_icons'] = $icon_map;
  }

  $out['calendar_default_slot_limits'] = $sanitize_int_map($input['calendar_default_slot_limits'] ?? array());

  $out['calendar_vendor_show_other_vendors_by_type'] = $sanitize_bool_map($input['calendar_vendor_show_other_vendors_by_type'] ?? array());
  $out['calendar_open_slot_display_by_vendor_type'] = $sanitize_bool_map($input['calendar_open_slot_display_by_vendor_type'] ?? array());
  $out['calendar_public_show_vendors_by_type'] = $sanitize_bool_map($input['calendar_public_show_vendors_by_type'] ?? array());

  $out['calendar_show_tickets_sold_to_vendors'] = !empty($input['calendar_show_tickets_sold_to_vendors']) ? 1 : 0;
  $out['vendor_portal_show_secondary_ticket_sales'] = !empty($input['vendor_portal_show_secondary_ticket_sales']) ? 1 : 0;
  $out['calendar_vendor_show_event_overlay'] = !empty($input['calendar_vendor_show_event_overlay']) ? 1 : 0;
  $out['calendar_show_open_slots_vendor'] = !empty($input['calendar_show_open_slots_vendor']) ? 1 : 0;
  $out['calendar_show_open_slots_public'] = !empty($input['calendar_show_open_slots_public']) ? 1 : 0;
  $out['calendar_vendor_show_tentative'] = !empty($input['calendar_vendor_show_tentative']) ? 1 : 0;

  $out['calendar_public_shortcode_enabled'] = !empty($input['calendar_public_shortcode_enabled']) ? 1 : 0;
  $out['calendar_public_show_vendors'] = !empty($input['calendar_public_show_vendors']) ? 1 : 0;
  $out['calendar_public_hide_past_default'] = !empty($input['calendar_public_hide_past_default']) ? 1 : 0;
  $public_default_view = isset($input['calendar_public_default_view']) ? sanitize_key((string) $input['calendar_public_default_view']) : 'auto';
  if (!in_array($public_default_view, array('auto', 'month', 'compact', 'list'), true)) {
    $public_default_view = 'auto';
  }
  $out['calendar_public_default_view'] = $public_default_view;

  $public_calendar_page_id = isset($input['public_calendar_page_id']) ? absint($input['public_calendar_page_id']) : 0;
  if ($public_calendar_page_id > 0 && get_post_type($public_calendar_page_id) !== 'page') {
    $public_calendar_page_id = 0;
  }
  $out['public_calendar_page_id'] = $public_calendar_page_id;
  $out['public_calendar_custom_url'] = isset($input['public_calendar_custom_url'])
    ? sanitize_text_field((string) $input['public_calendar_custom_url'])
    : '';

  $target = isset($input['calendar_open_slot_link_target']) ? sanitize_key((string) $input['calendar_open_slot_link_target']) : 'vendor_dashboard';
  if (!in_array($target, array('vendor_dashboard', 'vendor_registration', 'custom'), true)) {
    $target = 'vendor_dashboard';
  }
  $out['calendar_open_slot_link_target'] = $target;
  $out['calendar_open_slot_link_custom_url'] = isset($input['calendar_open_slot_link_custom_url'])
    ? esc_url_raw((string) $input['calendar_open_slot_link_custom_url'])
    : '';

  $target_map = array();
  foreach ($parse_map($input['calendar_open_slot_link_target_by_type'] ?? array()) as $slug => $val) {
    $k = sanitize_key((string) $slug);
    $v = sanitize_key((string) $val);
    if ($k === '') continue;
    if (!in_array($v, array('vendor_dashboard', 'vendor_registration', 'custom'), true)) continue;
    $target_map[$k] = $v;
  }
  $out['calendar_open_slot_link_target_by_type'] = $target_map;

  $custom_map = array();
  foreach ($parse_map($input['calendar_open_slot_link_custom_url_by_type'] ?? array()) as $slug => $url) {
    $k = sanitize_key((string) $slug);
    $u = esc_url_raw((string) $url);
    if ($k === '' || $u === '') continue;
    $custom_map[$k] = $u;
  }
  $out['calendar_open_slot_link_custom_url_by_type'] = $custom_map;

  $notify_target = isset($input['vendor_doc_submission_notify_target']) ? sanitize_key((string) $input['vendor_doc_submission_notify_target']) : 'site_admin';
  if (!in_array($notify_target, array('none', 'site_admin', 'user', 'role', 'capability'), true)) {
    $notify_target = 'site_admin';
  }
  $out['vendor_doc_submission_notify_enabled'] = array_key_exists('vendor_doc_submission_notify_enabled', $input)
    ? (!empty($input['vendor_doc_submission_notify_enabled']) ? 1 : 0)
    : 1;
  $out['vendor_doc_submission_notify_target'] = $notify_target;
  $out['vendor_doc_submission_notify_user_id'] = isset($input['vendor_doc_submission_notify_user_id']) ? absint($input['vendor_doc_submission_notify_user_id']) : 0;
  $out['vendor_doc_submission_notify_role'] = isset($input['vendor_doc_submission_notify_role']) ? sanitize_key((string) $input['vendor_doc_submission_notify_role']) : '';
  $out['vendor_doc_submission_notify_capability'] = isset($input['vendor_doc_submission_notify_capability']) ? sanitize_key((string) $input['vendor_doc_submission_notify_capability']) : '';

  $doc_role_ids = isset($input['staff_portal_doc_visibility_role_ids']) ? (array) $input['staff_portal_doc_visibility_role_ids'] : array();
  $doc_role_ids = array_values(array_unique(array_filter(array_map('absint', $doc_role_ids), function ($role_id) {
    return $role_id > 0;
  })));
  sort($doc_role_ids, SORT_NUMERIC);
  $out['staff_portal_doc_visibility_role_ids'] = $doc_role_ids;

  return $out;
}


function vms_field_timezone()
{
  $opts  = (array) get_option('vms_settings', array());
  $saved = isset($opts['timezone']) ? (string) $opts['timezone'] : '';

  $tzs = DateTimeZone::listIdentifiers();

  $wp_tz = get_option('timezone_string');
  if (!$saved && $wp_tz) $saved = $wp_tz;

  echo '<select name="vms_settings[timezone]" class="vms-minw-320">';
  echo '<option value="">' . esc_html__('(Use WordPress Site Timezone)', 'backstage-venue-manager') . '</option>';

  foreach ($tzs as $tz) {
    echo '<option value="' . esc_attr($tz) . '" ' . selected($saved, $tz, false) . '>' . esc_html($tz) . '</option>';
  }
  echo '</select>';

  echo '<p class="description">';
  echo esc_html__('Use a named timezone (e.g., America/Chicago) to handle daylight saving time correctly.', 'backstage-venue-manager');
  echo '</p>';

  $wp_offset = get_option('gmt_offset');
  if (empty($wp_tz) && $wp_offset !== '') {
    echo '<p class="description vms-text-danger">';
    echo esc_html__('Warning: Your WordPress site timezone is set as a UTC offset. Consider switching WP to a named timezone for best results.', 'backstage-venue-manager');
    echo '</p>';
  }
}

function vms_field_enable_woo()
{
  $opts = (array) get_option('vms_settings', array());
  $val  = !empty($opts['enable_woo']) ? 1 : 0;

  echo '<label>';
  echo '<input type="checkbox" name="vms_settings[enable_woo]" value="1" ' . checked($val, 1, false) . ' /> ';
  echo esc_html__('Enable WooCommerce product publishing + attendance integration', 'backstage-venue-manager');
  echo '</label>';
}

function vms_dashboard_settings_defaults()
{
  add_option('vms_dash_week_mode', 'calendar');   // calendar | lookahead
  add_option('vms_dash_week_start', 1);          // 0=Sun,1=Mon…6=Sat
  add_option('vms_dash_week_span', 1);           // 1 or 2
}
add_action('admin_init', 'vms_dashboard_settings_defaults');

function vms_field_enable_tec_publish()
{
  $opts = (array) get_option('vms_settings', array());
  $val  = array_key_exists('enable_tec_publish', $opts) ? (int) $opts['enable_tec_publish'] : 1;

  echo '<label>';
  echo '<input type="checkbox" name="vms_settings[enable_tec_publish]" value="1" ' . checked($val, 1, false) . ' /> ';
  echo esc_html__('Allow “Publish to Calendar” actions', 'backstage-venue-manager');
  echo '</label>';
}

function vms_field_default_venue()
{
  $opts  = (array) get_option('vms_settings', array());
  $saved = isset($opts['default_venue_id']) ? (int) $opts['default_venue_id'] : 0;

  $venues = get_posts(array(
    'post_type'      => 'vms_venue',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
    'fields'         => 'ids',
    'post_status'    => array('publish', 'draft', 'private', 'pending', 'future'),
  ));

  echo '<select name="vms_settings[default_venue_id]" class="vms-minw-320">';
  echo '<option value="0">' . esc_html__('— None —', 'backstage-venue-manager') . '</option>';

  foreach ($venues as $vid) {
    $vid = (int) $vid;
    echo '<option value="' . esc_attr((string) $vid) . '" ' . selected($saved, $vid, false) . '>';
    echo esc_html(get_the_title($vid));
    echo '</option>';
  }

  echo '</select>';
  echo '<p class="description">' . esc_html__('Used when no venue is selected in context.', 'backstage-venue-manager') . '</p>';

  // Validate: Default Venue should be a published venue.
  $saved_is_valid = ($saved > 0 && get_post_type($saved) === 'vms_venue' && get_post_status($saved) === 'publish');

  // Compute published venues (for safe "Fix now" suggestion when there is exactly one).
  $published = get_posts(array(
    'post_type'      => 'vms_venue',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'post_status'       => 'publish',
  ));
  $published = array_values(array_unique(array_map('intval', (array) $published)));

  vms_render_settings_default_venue_alert(
    vms_build_settings_default_venue_alert_context($saved, $venues, $published, $saved_is_valid)
  );
}

/**
 * @return array{visible:bool,class:string,label:string,href:string,target:string,rel:string}
 */
function vms_settings_default_venue_alert_default_action_context(): array
{
  return array(
    'visible' => false,
    'class' => '',
    'label' => '',
    'href' => '',
    'target' => '',
    'rel' => '',
  );
}

/**
 * @param array<int,mixed> $venues
 * @param array<int,mixed> $published
 * @return array<string,mixed>
 */
function vms_build_settings_default_venue_alert_context(int $saved, array $venues, array $published, bool $saved_is_valid): array
{
  $context = array(
    'show' => false,
    'state' => 'hidden',
    'notice_class' => '',
    'status' => '',
    'primary_action' => vms_settings_default_venue_alert_default_action_context(),
    'secondary_action' => vms_settings_default_venue_alert_default_action_context(),
  );

  $all = array_values(array_unique(array_map('intval', $venues)));
  $published = array_values(array_unique(array_map('intval', $published)));

  if (count($all) === 1) {
    $only_id = (int) $all[0];
    $only_status = (string) get_post_status($only_id);

    if ($only_status !== 'publish') {
      $edit_url = get_edit_post_link($only_id, 'raw');
      if (empty($edit_url)) {
        $edit_url = admin_url('post.php?post=' . $only_id . '&action=edit');
      }

      $context['show'] = true;
      $context['state'] = 'single_unpublished';
      $context['notice_class'] = 'notice notice-error vms-settings-default-venue-alert';
      $context['status'] = $only_status;
      $context['primary_action'] = array(
        'visible' => true,
        'class' => 'button button-primary',
        'label' => 'Open venue and publish',
        'href' => $edit_url,
        'target' => '',
        'rel' => '',
      );

      return $context;
    }
  }

  if ($saved_is_valid) {
    return $context;
  }

  $context['show'] = true;
  $context['state'] = 'unset';
  $context['notice_class'] = 'notice notice-warning vms-settings-default-venue-alert';

  if ($saved > 0 && get_post_type($saved) === 'vms_venue') {
    $saved_status = (string) get_post_status($saved);
    $edit_url = get_edit_post_link($saved, 'raw');
    if (empty($edit_url)) {
      $edit_url = admin_url('post.php?post=' . $saved . '&action=edit');
    }

    $context['state'] = 'selected_unpublished';
    $context['status'] = $saved_status;
    $context['primary_action'] = array(
      'visible' => true,
      'class' => 'button button-secondary',
      'label' => 'Open selected venue',
      'href' => $edit_url,
      'target' => '',
      'rel' => '',
    );
  }

  if (count($published) === 1) {
    $vid = (int) $published[0];
    $context['secondary_action'] = array(
      'visible' => true,
      'class' => 'button button-primary',
      'label' => 'Fix now: set Default Venue to “' . get_the_title($vid) . '”',
      'href' => wp_nonce_url(
        admin_url('admin-post.php?action=vms_set_default_venue&venue_id=' . $vid),
        'vms_set_default_venue_' . $vid
      ),
      'target' => '',
      'rel' => '',
    );
  }

  return $context;
}

/**
 * @param array<string,mixed> $context
 */
function vms_render_settings_default_venue_alert(array $context): void
{
  if (empty($context['show'])) {
    return;
  }

  echo '<div class="' . esc_attr((string) ($context['notice_class'] ?? '')) . '">';

  if (($context['state'] ?? 'hidden') === 'single_unpublished') {
    echo '<p><strong>Action required:</strong> Your only venue is not published (status: <strong>' . esc_html((string) ($context['status'] ?? '')) . '</strong>). This will cause Schedule and Season Dates to appear empty.</p>';
  } elseif (($context['state'] ?? 'hidden') === 'selected_unpublished') {
    echo '<p><strong>Default Venue needs attention:</strong> The selected venue is not published (status: <strong>' . esc_html((string) ($context['status'] ?? '')) . '</strong>). Publish it or choose a published venue.</p>';
  } else {
    echo '<p><strong>Default Venue is not set.</strong> This can cause parts of VMS to load with no venue context (especially in single-venue installs).</p>';
  }

  $primary_action = is_array($context['primary_action'] ?? null) ? $context['primary_action'] : array();
  if (!empty($primary_action['visible'])) {
    echo '<p><a';
    echo ' class="' . esc_attr((string) ($primary_action['class'] ?? '')) . '"';
    echo ' href="' . esc_url((string) ($primary_action['href'] ?? '')) . '"';
    if ((string) ($primary_action['target'] ?? '') !== '') {
      echo ' target="' . esc_attr((string) $primary_action['target']) . '"';
    }
    if ((string) ($primary_action['rel'] ?? '') !== '') {
      echo ' rel="' . esc_attr((string) $primary_action['rel']) . '"';
    }
    echo '>' . esc_html((string) ($primary_action['label'] ?? '')) . '</a></p>';
  }

  $secondary_action = is_array($context['secondary_action'] ?? null) ? $context['secondary_action'] : array();
  if (!empty($secondary_action['visible'])) {
    echo '<p><a';
    echo ' class="' . esc_attr((string) ($secondary_action['class'] ?? '')) . '"';
    echo ' href="' . esc_url((string) ($secondary_action['href'] ?? '')) . '"';
    if ((string) ($secondary_action['target'] ?? '') !== '') {
      echo ' target="' . esc_attr((string) $secondary_action['target']) . '"';
    }
    if ((string) ($secondary_action['rel'] ?? '') !== '') {
      echo ' rel="' . esc_attr((string) $secondary_action['rel']) . '"';
    }
    echo '>' . esc_html((string) ($secondary_action['label'] ?? '')) . '</a></p>';
  }

  echo '</div>';
}

function vms_field_admission_max_party_size()
{
  $opts = (array) get_option('vms_settings', array());
  $val = isset($opts['vms_admission_max_party_size']) ? absint($opts['vms_admission_max_party_size']) : 6;
  if ($val < 1) $val = 6;
  if ($val > 100) $val = 100;

  echo '<input type="number" min="1" max="100" step="1" name="vms_settings[vms_admission_max_party_size]" value="' . esc_attr((string) $val) . '" class="vms-input-narrow" />';
  echo '<p class="description">' . esc_html__('Maximum number of people allowed in a single guest list entry.', 'backstage-venue-manager') . '</p>';
}

function vms_field_admission_allow_uncheckin()
{
  $opts = (array) get_option('vms_settings', array());
  $val = !empty($opts['vms_admission_allow_uncheckin']) ? 1 : 0;

  echo '<label>';
  echo '<input type="checkbox" name="vms_settings[vms_admission_allow_uncheckin]" value="1" ' . checked($val, 1, false) . ' /> ';
  echo esc_html__('Allow an entry to be checked in and later undone (reverted back to Active).', 'backstage-venue-manager');
  echo '</label>';
}

function vms_field_admission_allow_uncheckin_for_door()
{
  $opts = (array) get_option('vms_settings', array());
  $val = !empty($opts['vms_admission_allow_uncheckin_for_door']) ? 1 : 0;

  echo '<label>';
  echo '<input type="checkbox" name="vms_settings[vms_admission_allow_uncheckin_for_door]" value="1" ' . checked($val, 1, false) . ' /> ';
  echo esc_html__('If undo is enabled, allow door users to undo check-in (otherwise managers only).', 'backstage-venue-manager');
  echo '</label>';
}

function vms_field_admission_door_show_phone()
{
  $opts = (array) get_option('vms_settings', array());
  $val = !empty($opts['vms_admission_door_show_phone']) ? 1 : 0;

  echo '<label>';
  echo '<input type="checkbox" name="vms_settings[vms_admission_door_show_phone]" value="1" ' . checked($val, 1, false) . ' /> ';
  echo esc_html__('Show full phone numbers in the door check-in UI (otherwise show masked phone).', 'backstage-venue-manager');
  echo '</label>';
}

function vms_field_vendor_portal_show_secondary_ticket_sales()
{
  $opts = (array) get_option('vms_settings', array());
  $val = !empty($opts['vendor_portal_show_secondary_ticket_sales']) ? 1 : 0;

  echo '<label>';
  echo '<input type="checkbox" name="vms_settings[vendor_portal_show_secondary_ticket_sales]" value="1" ' . checked($val, 1, false) . ' /> ';
  echo esc_html__('Show ticket sales / attendance snapshot to secondary vendors in Vendor Portal', 'backstage-venue-manager');
  echo '</label>';
  echo '<p class="description">' . esc_html__('Useful for food vendors and other secondary vendors who need expected crowd size. Compensation and bonus details remain hidden.', 'backstage-venue-manager') . '</p>';
}

function vms_field_availability_date_dispatch_enabled()
{
  $opts = (array) get_option('vms_settings', array());
  $val = array_key_exists('availability_date_dispatch_enabled', $opts) ? (!empty($opts['availability_date_dispatch_enabled']) ? 1 : 0) : 1;

  echo '<label>';
  echo '<input type="checkbox" name="vms_settings[availability_date_dispatch_enabled]" value="1" ' . checked($val, 1, false) . ' /> ';
  echo esc_html__('Enable the Availability & Date Dispatch internal module', 'backstage-venue-manager');
  echo '</label>';
  echo '<p class="description">' . esc_html__('When enabled, operators can send secure availability requests from Event Plans and record ADD responses back into canonical vendor availability. Disable this checkbox to turn off the module without deleting its stored request history.', 'backstage-venue-manager') . '</p>';
}

function vms_field_vendor_doc_submission_notifications()
{
  $settings = function_exists('vms_vendor_submission_alert_settings')
    ? vms_vendor_submission_alert_settings()
    : array(
        'vendor_doc_submission_notify_enabled' => 1,
        'vendor_doc_submission_notify_target' => 'site_admin',
        'vendor_doc_submission_notify_user_id' => 0,
        'vendor_doc_submission_notify_role' => '',
        'vendor_doc_submission_notify_capability' => '',
      );

  $enabled = !empty($settings['vendor_doc_submission_notify_enabled']) ? 1 : 0;
  $target = isset($settings['vendor_doc_submission_notify_target']) ? (string) $settings['vendor_doc_submission_notify_target'] : 'site_admin';
  $user_id = isset($settings['vendor_doc_submission_notify_user_id']) ? absint($settings['vendor_doc_submission_notify_user_id']) : 0;
  $role = isset($settings['vendor_doc_submission_notify_role']) ? sanitize_key((string) $settings['vendor_doc_submission_notify_role']) : '';
  $capability = isset($settings['vendor_doc_submission_notify_capability']) ? sanitize_key((string) $settings['vendor_doc_submission_notify_capability']) : '';

  $target_options = function_exists('vms_vendor_submission_recipient_mode_options')
    ? vms_vendor_submission_recipient_mode_options()
    : array(
        'site_admin' => __('Site admin email', 'backstage-venue-manager'),
        'user' => __('Specific WordPress user', 'backstage-venue-manager'),
        'role' => __('All users in a WordPress role', 'backstage-venue-manager'),
        'capability' => __('All users with a capability', 'backstage-venue-manager'),
        'none' => __('Do not email anyone', 'backstage-venue-manager'),
      );

  echo '<label>';
  echo '<input type="checkbox" name="vms_settings[vendor_doc_submission_notify_enabled]" value="1" ' . checked($enabled, 1, false) . ' /> ';
  echo esc_html__('Send an email alert when a vendor submits tech docs, a W-9/tax step, or a promo video', 'backstage-venue-manager');
  echo '</label>';

  echo '<p class="description">' . esc_html__('These submissions still flag the vendor as Needs review in VMS even if email alerts are turned off.', 'backstage-venue-manager') . '</p>';

  echo '<p><label for="vms_vendor_doc_submission_notify_target"><strong>' . esc_html__('Send alerts to', 'backstage-venue-manager') . '</strong></label><br>';
  echo '<select id="vms_vendor_doc_submission_notify_target" name="vms_settings[vendor_doc_submission_notify_target]" class="vms-minw-320">';
  foreach ($target_options as $value => $label) {
    echo '<option value="' . esc_attr((string) $value) . '" ' . selected($target, (string) $value, false) . '>' . esc_html((string) $label) . '</option>';
  }
  echo '</select></p>';

  $users = get_users(array(
    'orderby' => 'display_name',
    'order' => 'ASC',
  ));
  echo '<p><label for="vms_vendor_doc_submission_notify_user_id"><strong>' . esc_html__('Specific user', 'backstage-venue-manager') . '</strong></label><br>';
  echo '<select id="vms_vendor_doc_submission_notify_user_id" name="vms_settings[vendor_doc_submission_notify_user_id]" class="vms-minw-320">';
  echo '<option value="0">' . esc_html__('— Select a user —', 'backstage-venue-manager') . '</option>';
  foreach ($users as $user) {
    if (!($user instanceof WP_User)) continue;
    $label = $user->display_name !== '' ? $user->display_name : $user->user_login;
    if ($user->user_email !== '') {
      $label .= ' (' . $user->user_email . ')';
    }
    echo '<option value="' . esc_attr((string) $user->ID) . '" ' . selected($user_id, (int) $user->ID, false) . '>' . esc_html($label) . '</option>';
  }
  echo '</select><br><span class="description">' . esc_html__('Used only when “Specific WordPress user” is selected above.', 'backstage-venue-manager') . '</span></p>';

  global $wp_roles;
  $role_names = ($wp_roles instanceof WP_Roles) ? (array) $wp_roles->roles : array();
  echo '<p><label for="vms_vendor_doc_submission_notify_role"><strong>' . esc_html__('WordPress role', 'backstage-venue-manager') . '</strong></label><br>';
  echo '<select id="vms_vendor_doc_submission_notify_role" name="vms_settings[vendor_doc_submission_notify_role]" class="vms-minw-320">';
  echo '<option value="">' . esc_html__('— Select a role —', 'backstage-venue-manager') . '</option>';
  foreach ($role_names as $role_key => $role_data) {
    $role_label = isset($role_data['name']) ? (string) $role_data['name'] : (string) $role_key;
    echo '<option value="' . esc_attr((string) $role_key) . '" ' . selected($role, (string) $role_key, false) . '>' . esc_html($role_label) . '</option>';
  }
  echo '</select><br><span class="description">' . esc_html__('Used only when “All users in a WordPress role” is selected above.', 'backstage-venue-manager') . '</span></p>';

  echo '<p><label for="vms_vendor_doc_submission_notify_capability"><strong>' . esc_html__('Capability', 'backstage-venue-manager') . '</strong></label><br>';
  echo '<input id="vms_vendor_doc_submission_notify_capability" type="text" class="regular-text" name="vms_settings[vendor_doc_submission_notify_capability]" value="' . esc_attr($capability) . '" placeholder="manage_options" />';
  echo '<br><span class="description">' . esc_html__('Used only when “All users with a capability” is selected above. Example: manage_options.', 'backstage-venue-manager') . '</span></p>';
}


function vms_field_staff_portal_doc_visibility_roles()
{
  $opts = (array) get_option('vms_settings', array());
  $selected = isset($opts['staff_portal_doc_visibility_role_ids']) && is_array($opts['staff_portal_doc_visibility_role_ids'])
    ? array_values(array_unique(array_filter(array_map('absint', (array) $opts['staff_portal_doc_visibility_role_ids']))))
    : array();

  $role_map = function_exists('vms_staffing_role_map_by_id') ? (array) vms_staffing_role_map_by_id(false) : array();

  if (empty($role_map)) {
    echo '<p class="description">' . esc_html__('No staffing roles are available yet. Create staffing roles first, then come back here to limit who sees event docs in the Staff Portal.', 'backstage-venue-manager') . '</p>';
    return;
  }

  echo '<fieldset>';
  echo '<legend class="screen-reader-text">' . esc_html__('Staff Portal tech doc visibility', 'backstage-venue-manager') . '</legend>';
  echo '<p class="description">' . esc_html__('Leave everything unchecked to show event tech docs to all assigned staff. Check one or more staffing roles to only show docs when the assigned shift uses one of those roles.', 'backstage-venue-manager') . '</p>';

  foreach ($role_map as $role_id => $role) {
    $role_id = absint($role_id);
    if ($role_id <= 0) continue;
    /* translators: %d: staffing role ID */
    $label = isset($role['name']) ? (string) $role['name'] : sprintf(__('Role #%d', 'backstage-venue-manager'), $role_id);
    echo '<label style="display:block;margin:0 0 6px;">';
    echo '<input type="checkbox" name="vms_settings[staff_portal_doc_visibility_role_ids][]" value="' . esc_attr((string) $role_id) . '" ' . checked(in_array($role_id, $selected, true), true, false) . ' /> ';
    echo esc_html($label);
    echo '</label>';
  }

  echo '</fieldset>';
}

function vms_field_season_dates_link()

{
  $url = admin_url('admin.php?page=vms-season-dates');

  echo '<a class="button button-secondary" href="' . esc_url($url) . '">';
  echo esc_html__('Manage Season Dates', 'backstage-venue-manager');
  echo '</a>';

  $rules = get_option('vms_season_rules_v1', array());
  $active_dates = get_option('vms_season_active_dates_v1', array());

  $rules_count = is_array($rules) ? count($rules) : 0;
  $dates_count = is_array($active_dates) ? count($active_dates) : 0;

  echo '<p class="description">';
  echo esc_html(sprintf('Season rules: %d | Active dates generated: %d', $rules_count, $dates_count));
  echo '</p>';
}

add_action('admin_init', function () {

  // IMPORTANT: group name must match settings_fields() below.
register_setting('vms_settings_group', 'vms_settings', array(
  'type' => 'array',
  'sanitize_callback' => 'vms_sanitize_settings',
  'default' => array(),
));

  add_settings_section(
    'vms_settings_venues',
    __('Venues', 'backstage-venue-manager'),
    function () {
      echo '<p>' . esc_html__('Default venue behavior and fallbacks.', 'backstage-venue-manager') . '</p>';
    },
    'vms-settings'
  );

  add_settings_field(
    'vms_default_venue_id',
    __('Default Venue', 'backstage-venue-manager'),
    'vms_field_default_venue',
    'vms-settings',
    'vms_settings_venues'
  );
});

add_action('admin_init', function () {

  add_settings_section(
    'vms_settings_admissions',
    __('Guest List / Admissions', 'backstage-venue-manager'),
    function () {
      echo '<p>' . esc_html__('Controls for the Guest List / Comp Admission module and door check-in behavior.', 'backstage-venue-manager') . '</p>';
    },
    'vms-settings'
  );

  add_settings_field(
    'vms_admission_max_party_size',
    __('Max party size', 'backstage-venue-manager'),
    'vms_field_admission_max_party_size',
    'vms-settings',
    'vms_settings_admissions'
  );

  add_settings_field(
    'vms_admission_allow_uncheckin',
    __('Allow undo check-in', 'backstage-venue-manager'),
    'vms_field_admission_allow_uncheckin',
    'vms-settings',
    'vms_settings_admissions'
  );

  add_settings_field(
    'vms_admission_allow_uncheckin_for_door',
    __('Allow undo check-in for door staff', 'backstage-venue-manager'),
    'vms_field_admission_allow_uncheckin_for_door',
    'vms-settings',
    'vms_settings_admissions'
  );

  add_settings_field(
    'vms_admission_door_show_phone',
    __('Show phone numbers at door', 'backstage-venue-manager'),
    'vms_field_admission_door_show_phone',
    'vms-settings',
    'vms_settings_admissions'
  );
});

add_action('admin_init', function () {

  add_settings_section(
    'vms_settings_vendor_portal',
    __('Vendor Portal', 'backstage-venue-manager'),
    function () {
      echo '<p>' . esc_html__('Controls what vendors can see inside their portal.', 'backstage-venue-manager') . '</p>';
    },
    'vms-settings'
  );

  add_settings_field(
    'vms_vendor_portal_show_secondary_ticket_sales',
    __('Secondary vendor crowd visibility', 'backstage-venue-manager'),
    'vms_field_vendor_portal_show_secondary_ticket_sales',
    'vms-settings',
    'vms_settings_vendor_portal'
  );

  add_settings_field(
    'vms_availability_date_dispatch_enabled',
    __('Availability & Date Dispatch', 'backstage-venue-manager'),
    'vms_field_availability_date_dispatch_enabled',
    'vms-settings',
    'vms_settings_vendor_portal'
  );

  add_settings_field(
    'vms_vendor_doc_submission_notifications',
    __('Vendor document submission alerts', 'backstage-venue-manager'),
    'vms_field_vendor_doc_submission_notifications',
    'vms-settings',
    'vms_settings_vendor_portal'
  );
});

add_action('admin_init', function () {

  add_settings_section(
    'vms_settings_staff_portal',
    __('Staff Portal', 'backstage-venue-manager'),
    function () {
      echo '<p>' . esc_html__('Controls what assigned staff can see inside their portal dashboard.', 'backstage-venue-manager') . '</p>';
    },
    'vms-settings'
  );

  add_settings_field(
    'vms_staff_portal_doc_visibility_roles',
    __('Tech doc visibility by staffing role', 'backstage-venue-manager'),
    'vms_field_staff_portal_doc_visibility_roles',
    'vms-settings',
    'vms_settings_staff_portal'
  );
});

add_action('admin_init', function () {

  // Register options
  register_setting('vms_settings_group', 'vms_dash_week_mode', [
    'type' => 'string',
    'sanitize_callback' => function ($v) {
      return in_array($v, ['calendar', 'lookahead'], true) ? $v : 'calendar';
    },
    'default' => 'calendar',
  ]);

  register_setting('vms_settings_group', 'vms_dash_week_start', [
    'type' => 'integer',
    'sanitize_callback' => function ($v) {
      $v = (int) $v;
      return ($v >= 0 && $v <= 6) ? $v : 1;
    },
    'default' => 1, // Monday
  ]);

  register_setting('vms_settings_group', 'vms_dash_week_span', [
    'type' => 'integer',
    'sanitize_callback' => function ($v) {
      $v = (int) $v;
      return ($v === 2) ? 2 : 1;
    },
    'default' => 1,
  ]);

  
  register_setting('vms_settings_group', 'vms_dash_bills_span', [
    'type' => 'integer',
    'sanitize_callback' => function ($v) {
      $v = absint($v);
      if ($v < 1) $v = 1;
      if ($v > 365) $v = 365;
      return $v;
    },
    'default' => 30,
  ]);

  register_setting('vms_settings_group', 'vms_dash_bills_terms_days', [
    'type' => 'integer',
    'sanitize_callback' => function ($v) {
      $v = (int) $v;
      if ($v < 0) $v = 0;
      if ($v > 365) $v = 365;
      return $v;
    },
    'default' => 0,
  ]);
// Section
  add_settings_section(
    'vms_dash_section',
    __('Dashboard', 'backstage-venue-manager'),
    function () {
      echo '<p class="description">Controls how the Dashboard calculates ranges (This Week and Upcoming Bills).</p>';
    },
    'vms-settings'
  );

  // Fields
  add_settings_field(
    'vms_dash_week_mode',
    __('Week view mode', 'backstage-venue-manager'),
    'vms_dash_field_week_mode',
    'vms-settings',
    'vms_dash_section'
  );

  add_settings_field(
    'vms_dash_week_start',
    __('Week starts on', 'backstage-venue-manager'),
    'vms_dash_field_week_start',
    'vms-settings',
    'vms_dash_section'
  );

  add_settings_field(
    'vms_dash_week_span',
    __('Week preview length', 'backstage-venue-manager'),
    'vms_dash_field_week_span',
    'vms-settings',
    'vms_dash_section'
  );


  add_settings_field(
    'vms_dash_bills_span',
    __('Upcoming Bills window (days)', 'backstage-venue-manager'),
    'vms_dash_field_bills_span',
    'vms-settings',
    'vms_dash_section'
  );

  add_settings_field(
    'vms_dash_bills_terms_days',
    __('Upcoming Bills terms offset (days)', 'backstage-venue-manager'),
    'vms_dash_field_bills_terms_days',
    'vms-settings',
    'vms_dash_section'
  );
});

function vms_dash_field_week_mode()
{
  $val = get_option('vms_dash_week_mode', 'calendar');
  echo '<select name="vms_dash_week_mode">';
  echo '<option value="calendar"' . selected($val, 'calendar', false) . '>Calendar week (configurable start day)</option>';
  echo '<option value="lookahead"' . selected($val, 'lookahead', false) . '>Look ahead (next 7 days)</option>';
  echo '</select>';
}

function vms_dash_field_week_start()
{
  $val = (int) get_option('vms_dash_week_start', 1);
  $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  echo '<select name="vms_dash_week_start">';
  foreach ($days as $i => $label) {
    echo '<option value="' . esc_attr($i) . '"' . selected($val, $i, false) . '>' . esc_html($label) . '</option>';
  }
  echo '</select>';
  echo '<p class="description">Used only for “Calendar week” mode.</p>';
}

function vms_dash_field_week_span()
{
  $val = (int) get_option('vms_dash_week_span', 1);
  echo '<select name="vms_dash_week_span">';
  echo '<option value="1"' . selected($val, 1, false) . '>Current week only</option>';
  echo '<option value="2"' . selected($val, 2, false) . '>Current + next week (14 days)</option>';
  echo '</select>';
}


function vms_dash_field_bills_span()
{
  $val = (int) get_option('vms_dash_bills_span', 30);
  echo '<input type="number" min="1" max="365" step="1" name="vms_dash_bills_span" value="' . esc_attr($val) . '" class="vms-input-narrow" /> ';
  echo '<span class="description">Days ahead to include in Upcoming Bills.</span>';
}

function vms_dash_field_bills_terms_days()
{
  $val = (int) get_option('vms_dash_bills_terms_days', 0);
  echo '<input type="number" min="0" max="365" step="1" name="vms_dash_bills_terms_days" value="' . esc_attr($val) . '" class="vms-input-narrow" /> ';
  echo '<span class="description">Adds days to the event date to estimate a due date.</span>';
}

if (!function_exists('vms_settings_assoc_map_to_lines')) {
  /**
   * Render map values as newline key:value rows for textarea editing.
   *
   * @param mixed $raw
   */
  function vms_settings_assoc_map_to_lines($raw): string
  {
    $map = function_exists('vms_calendar_parse_assoc_map')
      ? (array) vms_calendar_parse_assoc_map($raw)
      : (is_array($raw) ? $raw : array());

    if (empty($map)) return '';

    $lines = array();
    foreach ($map as $k => $v) {
      $key = sanitize_key((string) $k);
      if ($key === '') continue;
      $lines[] = $key . ': ' . trim((string) $v);
    }
    return implode("\n", $lines);
  }
}

if (!function_exists('vms_settings_calendar_icon_choices')) {
  /**
   * Curated icon choices for non-technical admin selection.
   *
   * @return array<string,string> icon => label
   */
  function vms_settings_calendar_icon_choices(): array
  {
    return array(
      '🎵' => __('Music', 'backstage-venue-manager'),
      '🎤' => __('Mic', 'backstage-venue-manager'),
      '🎸' => __('Guitar', 'backstage-venue-manager'),
      '🎹' => __('Keys', 'backstage-venue-manager'),
      '🥁' => __('Drums', 'backstage-venue-manager'),
      '🍔' => __('Food', 'backstage-venue-manager'),
      '🌮' => __('Taco', 'backstage-venue-manager'),
      '🍕' => __('Pizza', 'backstage-venue-manager'),
      '🍹' => __('Cocktail', 'backstage-venue-manager'),
      '🍺' => __('Beer', 'backstage-venue-manager'),
      '🍷' => __('Wine', 'backstage-venue-manager'),
      '☕' => __('Coffee', 'backstage-venue-manager'),
      '🧁' => __('Dessert', 'backstage-venue-manager'),
      '🛍️' => __('Merch', 'backstage-venue-manager'),
      '🎪' => __('Booth', 'backstage-venue-manager'),
      '📸' => __('Photo', 'backstage-venue-manager'),
      '🎥' => __('Video', 'backstage-venue-manager'),
      '🎨' => __('Art', 'backstage-venue-manager'),
      '🎟️' => __('Tickets', 'backstage-venue-manager'),
      '🧰' => __('Services', 'backstage-venue-manager'),
      '🚚' => __('Truck', 'backstage-venue-manager'),
      '✨' => __('General', 'backstage-venue-manager'),
    );
  }
}

if (!function_exists('vms_settings_calendar_vendor_type_label')) {
  /**
   * Neutral Schedule settings labels without changing stored vendor-type slugs.
   */
  function vms_settings_calendar_vendor_type_label(string $slug, string $label): string
  {
    $slug = sanitize_key($slug);
    $label = trim($label);
    $label_key = sanitize_title($label);

    $primary_keys = array(
      'artist',
      'band',
      'bands',
      'headliner',
      'musician',
      'performer',
      'performers',
      'talent',
    );
    if (in_array($slug, $primary_keys, true) || in_array($label_key, $primary_keys, true)) {
      return __('Primary Vendor', 'backstage-venue-manager');
    }

    $secondary_keys = array(
      'food',
      'food-truck',
      'food-vendor',
      'food_vendor',
      'food_truck',
    );
    if (in_array($slug, $secondary_keys, true) || in_array($label_key, $secondary_keys, true)) {
      return __('Secondary Vendor', 'backstage-venue-manager');
    }

    return ($label !== '') ? $label : $slug;
  }
}

if (!function_exists('vms_settings_calendar_vendor_type_rows')) {
  /**
   * Rows for Vendor Type icon selectors.
   *
   * @param array<string,mixed> $seed_map
   * @return array<int,array{slug:string,label:string}>
   */
  function vms_settings_calendar_vendor_type_rows(array $seed_map): array
  {
    $rows = array();
    $seen = array();

    $terms = get_terms(array(
      'taxonomy' => 'vms_vendor_type',
      'hide_empty' => false,
      'orderby' => 'name',
      'order' => 'ASC',
    ));

    if (is_array($terms) && !is_wp_error($terms)) {
      foreach ($terms as $term) {
        if (!$term instanceof WP_Term) {
          continue;
        }
        $slug = sanitize_key((string) $term->slug);
        if ($slug === '' || isset($seen[$slug])) {
          continue;
        }
        $seen[$slug] = true;
        $rows[] = array(
          'slug' => $slug,
          'label' => vms_settings_calendar_vendor_type_label($slug, (string) $term->name),
        );
      }
    }

    // Preserve older/unknown saved keys so existing values are not dropped.
    foreach ($seed_map as $slug => $_value) {
      $k = sanitize_key((string) $slug);
      if ($k === '' || isset($seen[$k])) {
        continue;
      }
      $rows[] = array(
        'slug' => $k,
        /* translators: %s: saved vendor type key */
        'label' => vms_settings_calendar_vendor_type_label($k, sprintf(__('Archived type (%s)', 'backstage-venue-manager'), $k)),
      );
    }

    return $rows;
  }
}

function vms_render_settings_page_notices(): void
{
  // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only settings notice state only affects admin feedback.
  if (vms_request_read_key($_GET, 'vms_notice') === 'default_venue_set') {
    echo '<div class="notice notice-success"><p>' . esc_html__('Default venue updated.', 'backstage-venue-manager') . '</p></div>';
  }
}

function vms_render_settings_page()
{
  vms_get_settings_page_ticketing_stock_notice_state(true);

  if (function_exists('vms_admin_ui_render_shell')) {
    vms_admin_ui_render_shell(
      array(
        'title' => __('VMS Settings', 'backstage-venue-manager'),
        'notices_callback' => 'vms_render_settings_page_notice_bar',
      ),
      'vms_render_settings_page_content'
    );
    return;
  }

  echo '<div class="wrap"><h1>' . esc_html__('VMS Settings', 'backstage-venue-manager') . '</h1>';
  vms_render_settings_page_notices();
  ob_start();
  vms_render_settings_page_content(true);
  $content_html = (string) ob_get_clean();
  $content_html = str_replace(
    vms_settings_page_ticketing_stock_notice_placeholder(),
    vms_get_settings_page_ticketing_stock_notice_markup(),
    $content_html
  );
  echo $content_html;
  echo '</div>';
}

function vms_render_settings_page_content(bool $include_ticketing_stock_notice_placeholder = false)
{
  if (defined('VMS_VERSION')) {
    echo '<p class="description">' . esc_html__('Plugin version:', 'backstage-venue-manager') . ' ' . esc_html((string) VMS_VERSION) . '</p>';
  }
  echo '<form method="post" action="options.php">';
  settings_fields('vms_settings_group');
  do_settings_sections('vms-settings');


  // Ticketing
  $settings = (array) get_option('vms_settings', array());
  $ticketing_default = !empty($settings['ticketing_enabled_default']) ? 1 : 0;
  $ticket_ui_layout = isset($settings['ticket_ui_layout']) ? sanitize_key((string) $settings['ticket_ui_layout']) : 'classic';
  if (!in_array($ticket_ui_layout, array('classic', 'v2', 'progressive'), true)) {
    $ticket_ui_layout = 'classic';
  }
  $ticket_ui_admin_preview = array_key_exists('ticket_ui_v2_admin_preview', $settings)
    ? (!empty($settings['ticket_ui_v2_admin_preview']) ? 1 : 0)
    : 1;
  $ticket_ui_show_availability = array_key_exists('ticket_ui_show_availability', $settings)
    ? (!empty($settings['ticket_ui_show_availability']) ? 1 : 0)
    : 1;
  $ticket_ui_availability_display = isset($settings['ticket_ui_availability_display'])
    ? sanitize_key((string) $settings['ticket_ui_availability_display'])
    : ($ticket_ui_show_availability ? 'low' : 'hide');
  if (!in_array($ticket_ui_availability_display, array('always', 'low', 'hide'), true)) {
    $ticket_ui_availability_display = 'low';
  }
  $ticket_ui_availability_low_threshold = max(1, absint($settings['ticket_ui_availability_low_threshold'] ?? 25));
  $ticket_ui_sale_availability_display = isset($settings['ticket_ui_sale_availability_display'])
    ? sanitize_key((string) $settings['ticket_ui_sale_availability_display'])
    : 'when_capped';
  if (!in_array($ticket_ui_sale_availability_display, array('when_capped', 'low', 'hide'), true)) {
    $ticket_ui_sale_availability_display = 'when_capped';
  }
  $ticket_ui_sale_availability_low_threshold = max(1, absint($settings['ticket_ui_sale_availability_low_threshold'] ?? 10));
  $ticket_help_tickets_enabled = array_key_exists('ticket_ui_help_tickets_enabled', $settings)
    ? (!empty($settings['ticket_ui_help_tickets_enabled']) ? 1 : 0)
    : 1;
  $ticket_help_addons_enabled = array_key_exists('ticket_ui_help_addons_enabled', $settings)
    ? (!empty($settings['ticket_ui_help_addons_enabled']) ? 1 : 0)
    : 1;
  $ticket_help_tickets_default = isset($settings['ticket_ui_help_tickets_default']) ? trim((string) $settings['ticket_ui_help_tickets_default']) : '';
  if ($ticket_help_tickets_default === '' && function_exists('vms_ticketing_ui_help_default_text')) {
    $ticket_help_tickets_default = wpautop(esc_html((string) vms_ticketing_ui_help_default_text('tickets')));
  }
  $ticket_help_addons_default = isset($settings['ticket_ui_help_addons_default']) ? trim((string) $settings['ticket_ui_help_addons_default']) : '';
  if ($ticket_help_addons_default === '' && function_exists('vms_ticketing_ui_help_default_text')) {
    $ticket_help_addons_default = wpautop(esc_html((string) vms_ticketing_ui_help_default_text('addons')));
  }
  $ticket_ui_addons_heading = isset($settings['ticket_ui_addons_heading']) ? trim(html_entity_decode((string) $settings['ticket_ui_addons_heading'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
  if ($ticket_ui_addons_heading === '') {
    $ticket_ui_addons_heading = function_exists('vms_ticketing_ui_addons_section_heading_default') ? vms_ticketing_ui_addons_section_heading_default() : 'Fire Pits & Tables';
  }
  $ticket_ui_addons_subtext = isset($settings['ticket_ui_addons_subtext']) ? trim(html_entity_decode((string) $settings['ticket_ui_addons_subtext'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
  if ($ticket_ui_addons_subtext === '') {
    $ticket_ui_addons_subtext = function_exists('vms_ticketing_ui_addons_section_subtext_default') ? vms_ticketing_ui_addons_section_subtext_default() : 'Click here to add a fire pit or table to your order.';
  }

  echo '<h2 class="vms-mt-24">' . esc_html__('Ticketing', 'backstage-venue-manager') . '</h2>';
  echo '<table class="form-table" role="presentation">';
  echo '<tr>';
  echo '<th scope="row">' . esc_html__('Default behavior', 'backstage-venue-manager') . '</th>';
  echo '<td>';
  echo '<label>';
  echo '<input type="checkbox" name="vms_settings[ticketing_enabled_default]" value="1" ' . checked($ticketing_default, 1, false) . ' /> ';
  echo esc_html__('Enable ticketing features by default for new Event Plans', 'backstage-venue-manager');
  echo '</label>';
  echo '<p class="description">' . esc_html__('Recommended for operators who regularly sell tickets. Even when enabled, ticketing requires The Events Calendar + Event Tickets (Woo) + WooCommerce.', 'backstage-venue-manager') . '</p>';
  echo '</td>';
  echo '</tr>';
  echo '<tr data-vms-tour="ticketing-ui.about">';
  echo '<th scope="row"><label for="vms_ticket_ui_layout">' . esc_html__('Ticket UI', 'backstage-venue-manager') . '</label></th>';
  echo '<td data-vms-tour="ticketing-ui.settings-root">';
  $ticketing_ui_tour_button = '<button type="button" class="button button-secondary vms-tour-help-trigger" data-vms-tour-start="vms.settings.ticketing_ui" data-vms-tour="ticketing-ui.start-tour">' . esc_html__('Start Guided Tour', 'backstage-venue-manager') . '</button>';
  if (function_exists('vms_render_help_button')) {
    $ticketing_ui_tour_button = vms_render_help_button(array(
      'tour_id' => 'vms.settings.ticketing_ui',
      'anchor' => 'ticketing-ui.start-tour',
      'label' => __('Start Guided Tour', 'backstage-venue-manager'),
    ));
  }
  echo '<p>' . wp_kses($ticketing_ui_tour_button, vms_settings_page_help_button_allowed_html()) . '</p>';
  echo '<p class="description">' . esc_html__('Ticket UI can run in Safe Mode, the older unified V2 layout, or the new progressive flow that keeps all admission choices together while tucking optional add-ons below.', 'backstage-venue-manager') . '</p>';
  echo '<p data-vms-tour="ticketing-ui.public-enable">';
  echo '<label for="vms_ticket_ui_layout"><strong>' . esc_html__('Ticket UI Layout', 'backstage-venue-manager') . '</strong></label><br />';
  echo '<select id="vms_ticket_ui_layout" name="vms_settings[ticket_ui_layout]">';
  echo '<option value="classic"' . selected($ticket_ui_layout, 'classic', false) . '>' . esc_html__('Safe Mode (TEC-only)', 'backstage-venue-manager') . '</option>';
  echo '<option value="v2"' . selected($ticket_ui_layout, 'v2', false) . '>' . esc_html__('V2 (Unified)', 'backstage-venue-manager') . '</option>';
  echo '<option value="progressive"' . selected($ticket_ui_layout, 'progressive', false) . '>' . esc_html__('Progressive (Tickets + Add-ons)', 'backstage-venue-manager') . '</option>';
  echo '</select>';
  echo '</p>';
  echo '<p>';
  echo '<label for="vms_ticket_ui_addons_heading"><strong>' . esc_html__('Add-on section heading', 'backstage-venue-manager') . '</strong></label><br />';
  echo '<input id="vms_ticket_ui_addons_heading" type="text" class="regular-text" name="vms_settings[ticket_ui_addons_heading]" value="' . esc_attr($ticket_ui_addons_heading) . '" />';
  echo '<span class="description vms-ml-10">' . esc_html__('Shown as the collapsed add-on section title in Progressive layout.', 'backstage-venue-manager') . '</span>';
  echo '</p>';
  echo '<p>';
  echo '<label for="vms_ticket_ui_addons_subtext"><strong>' . esc_html__('Add-on section subtext', 'backstage-venue-manager') . '</strong></label><br />';
  echo '<input id="vms_ticket_ui_addons_subtext" type="text" class="large-text" name="vms_settings[ticket_ui_addons_subtext]" value="' . esc_attr($ticket_ui_addons_subtext) . '" />';
  echo '</p>';
  echo '<p data-vms-tour="ticketing-ui.admin-preview">';
  echo '<label>';
  echo '<input type="checkbox" name="vms_settings[ticket_ui_v2_admin_preview]" value="1" ' . checked($ticket_ui_admin_preview, 1, false) . ' /> ';
  echo esc_html__('Enable V2 preview for admins only when layout is Safe Mode', 'backstage-venue-manager');
  echo '</label>';
  echo '</p>';
  echo '<p>';
  echo '<label for="vms_ticket_ui_availability_display"><strong>' . esc_html__('Display total ticket availability', 'backstage-venue-manager') . '</strong></label><br />';
  echo '<select id="vms_ticket_ui_availability_display" name="vms_settings[ticket_ui_availability_display]">';
  echo '<option value="always"' . selected($ticket_ui_availability_display, 'always', false) . '>' . esc_html__('Always show total remaining', 'backstage-venue-manager') . '</option>';
  echo '<option value="low"' . selected($ticket_ui_availability_display, 'low', false) . '>' . esc_html__('Only show when low', 'backstage-venue-manager') . '</option>';
  echo '<option value="hide"' . selected($ticket_ui_availability_display, 'hide', false) . '>' . esc_html__('Hide total availability', 'backstage-venue-manager') . '</option>';
  echo '</select>';
  echo '<label for="vms_ticket_ui_availability_low_threshold" class="vms-ml-10">' . esc_html__('Low threshold', 'backstage-venue-manager') . '</label> ';
  echo '<input id="vms_ticket_ui_availability_low_threshold" type="number" min="1" step="1" class="small-text" name="vms_settings[ticket_ui_availability_low_threshold]" value="' . esc_attr((string) $ticket_ui_availability_low_threshold) . '" />';
  echo '<br /><span class="description">' . esc_html__('Recommended: only show total inventory when an event is close to selling out, so the page does not advertise large remaining capacity.', 'backstage-venue-manager') . '</span>';
  echo '</p>';
  echo '<p>';
  echo '<label for="vms_ticket_ui_sale_availability_display"><strong>' . esc_html__('Display sale quantity remaining', 'backstage-venue-manager') . '</strong></label><br />';
  echo '<select id="vms_ticket_ui_sale_availability_display" name="vms_settings[ticket_ui_sale_availability_display]">';
  echo '<option value="when_capped"' . selected($ticket_ui_sale_availability_display, 'when_capped', false) . '>' . esc_html__('Show when a capped sale is active', 'backstage-venue-manager') . '</option>';
  echo '<option value="low"' . selected($ticket_ui_sale_availability_display, 'low', false) . '>' . esc_html__('Only show when sale quantity is low', 'backstage-venue-manager') . '</option>';
  echo '<option value="hide"' . selected($ticket_ui_sale_availability_display, 'hide', false) . '>' . esc_html__('Hide sale quantity', 'backstage-venue-manager') . '</option>';
  echo '</select>';
  echo '<label for="vms_ticket_ui_sale_availability_low_threshold" class="vms-ml-10">' . esc_html__('Sale low threshold', 'backstage-venue-manager') . '</label> ';
  echo '<input id="vms_ticket_ui_sale_availability_low_threshold" type="number" min="1" step="1" class="small-text" name="vms_settings[ticket_ui_sale_availability_low_threshold]" value="' . esc_attr((string) $ticket_ui_sale_availability_low_threshold) . '" />';
  echo '<br /><span class="description">' . esc_html__('This is separate from total availability so Early Bird urgency can show without exposing full event capacity.', 'backstage-venue-manager') . '</span>';
  echo '</p>';
  echo '<p>';
  echo '<label>';
  echo '<input type="checkbox" name="vms_settings[ticket_ui_help_tickets_enabled]" value="1" ' . checked($ticket_help_tickets_enabled, 1, false) . ' /> ';
  echo esc_html__('Show ticket help above Tickets', 'backstage-venue-manager');
  echo '</label>';
  echo '</p>';
  echo '<p>';
  echo '<label for="vms_ticket_ui_help_tickets_default"><strong>' . esc_html__('Default ticket help copy', 'backstage-venue-manager') . '</strong></label><br />';
  wp_editor(
    $ticket_help_tickets_default,
    'vms_ticket_ui_help_tickets_default_editor',
    array(
      'textarea_name' => 'vms_settings[ticket_ui_help_tickets_default]',
      'textarea_rows' => 6,
      'media_buttons' => false,
      'teeny' => false,
      'quicktags' => true,
      'tinymce' => array(
        'toolbar1' => 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,forecolor,removeformat,undo,redo',
        'toolbar2' => '',
      ),
    )
  );
  echo '</p>';
  echo '<p>';
  echo '<label>';
  echo '<input type="checkbox" name="vms_settings[ticket_ui_help_addons_enabled]" value="1" ' . checked($ticket_help_addons_enabled, 1, false) . ' /> ';
  echo esc_html__('Show add-on help above the add-on section', 'backstage-venue-manager');
  echo '</label>';
  echo '</p>';
  echo '<p>';
  echo '<label for="vms_ticket_ui_help_addons_default"><strong>' . esc_html__('Default add-on help copy', 'backstage-venue-manager') . '</strong></label><br />';
  wp_editor(
    $ticket_help_addons_default,
    'vms_ticket_ui_help_addons_default_editor',
    array(
      'textarea_name' => 'vms_settings[ticket_ui_help_addons_default]',
      'textarea_rows' => 6,
      'media_buttons' => false,
      'teeny' => false,
      'quicktags' => true,
      'tinymce' => array(
        'toolbar1' => 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,forecolor,removeformat,undo,redo',
        'toolbar2' => '',
      ),
    )
  );
  echo '</p>';
  echo '<p class="description">' . esc_html__('These are the default public help boxes shown above tickets and above add-ons. Use the editor for bold, lists, alignment, links, and text color. Individual Event Plans can override the copy with the same editor when a show needs custom wording.', 'backstage-venue-manager') . '</p>';
  echo '<p class="description" data-vms-tour="ticketing-ui.rollback">' . esc_html__('Behavior: when Layout is Safe Mode and Admin Preview is ON, admins see V2 while public users stay on Safe Mode. To roll back instantly, set Layout to Safe Mode and turn Admin Preview OFF.', 'backstage-venue-manager') . '</p>';
  echo '</td>';
  echo '</tr>';
  echo '</table>';


  // Ticketing inventory tools (Preview → Commit)
  $ticketing_stock_notice_state = vms_get_settings_page_ticketing_stock_notice_state();
  $preview_rep = $ticketing_stock_notice_state['preview_report'] ?? false;

  if ($include_ticketing_stock_notice_placeholder) {
    echo vms_settings_page_ticketing_stock_notice_placeholder();
  }

  echo '<h3 class="vms-mt-16">' . esc_html__('Ticketing inventory tools', 'backstage-venue-manager') . '</h3>';
  echo '<p class="description">' . esc_html__('Preview and reconcile entitlement availability from paid orders. Use Preview first, then Commit fixes. This prevents stock from being reset to full capacity after you change ticketing config and commit again.', 'backstage-venue-manager') . '</p>';

  $preview_url = add_query_arg(
    array(
      'action' => 'vms_ticketing_stock_preview',
      '_wpnonce' => wp_create_nonce('vms_ticketing_stock_preview'),
    ),
    admin_url('admin-post.php')
  );
  $commit_url = add_query_arg(
    array(
      'action' => 'vms_ticketing_stock_commit',
      '_wpnonce' => wp_create_nonce('vms_ticketing_stock_commit'),
    ),
    admin_url('admin-post.php')
  );
  $csv_url = add_query_arg(
    array(
      'action' => 'vms_ticketing_stock_csv',
      'mode' => 'preview',
      '_wpnonce' => wp_create_nonce('vms_ticketing_stock_csv'),
    ),
    admin_url('admin-post.php')
  );
  $clear_preview_url = add_query_arg(
    array(
      'action' => 'vms_ticketing_stock_clear_preview',
      '_wpnonce' => wp_create_nonce('vms_ticketing_stock_clear_preview'),
    ),
    admin_url('admin-post.php')
  );

  echo '<p style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">';
  echo '<a class="button button-secondary" href="' . esc_url($preview_url) . '">' . esc_html__('Preview changes', 'backstage-venue-manager') . '</a>';
  if (is_array($preview_rep)) {
    echo '<a class="button button-primary" href="' . esc_url($commit_url) . '">' . esc_html__('Commit fixes', 'backstage-venue-manager') . '</a>';
    echo '<a class="button" href="' . esc_url($csv_url) . '">' . esc_html__('Download CSV (preview)', 'backstage-venue-manager') . '</a>';
    echo '<a class="button-link" href="' . esc_url($clear_preview_url) . '">' . esc_html__('Clear preview', 'backstage-venue-manager') . '</a>';
  } else {
    echo '<span class="description" style="margin-left:6px;">' . esc_html__('Run Preview to generate a report before committing.', 'backstage-venue-manager') . '</span>';
  }
  echo '</p>';

  if (is_array($preview_rep) && is_array($preview_rep['results'] ?? null)) {
    $rows = (array) $preview_rep['results'];
    $max_rows = 75;
    echo '<div class="vms-mt-8" style="max-width:1100px;">';
    echo '<h4 class="vms-mt-8" style="margin:10px 0 6px;">' . esc_html__('Preview details (most recent)', 'backstage-venue-manager') . '</h4>';
    /* translators: %d: maximum number of preview rows shown */
    echo '<p class="description">' . esc_html(sprintf(__('Showing up to %d rows. Download CSV for the full report.', 'backstage-venue-manager'), $max_rows)) . '</p>';
    echo '<table class="widefat striped" style="margin-top:8px;">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Product', 'backstage-venue-manager') . '</th>';
    echo '<th>' . esc_html__('SKU', 'backstage-venue-manager') . '</th>';
    echo '<th>' . esc_html__('Capacity', 'backstage-venue-manager') . '</th>';
    echo '<th>' . esc_html__('Sold', 'backstage-venue-manager') . '</th>';
    echo '<th>' . esc_html__('Old qty', 'backstage-venue-manager') . '</th>';
    echo '<th>' . esc_html__('New qty', 'backstage-venue-manager') . '</th>';
    echo '<th>' . esc_html__('Status', 'backstage-venue-manager') . '</th>';
    echo '</tr></thead><tbody>';
    $i = 0;
    foreach ($rows as $r) {
      if (!is_array($r)) continue;
      $i++;
      if ($i > $max_rows) break;
      $pid = absint($r['product_id'] ?? 0);
      $name = (string) ($r['product_name'] ?? '');
      $sku = (string) ($r['sku'] ?? '');
      $cap = $r['capacity'] ?? '';
      $sold = $r['sold_qty'] ?? '';
      $old = $r['old_stock'] ?? '';
      $new = $r['new_stock'] ?? '';
      $status = (string) ($r['status'] ?? '');
      $edit = ($pid > 0) ? get_edit_post_link($pid, 'raw') : '';
      $label = $name !== '' ? $name : ('#' . $pid);
      if ($edit) {
        $label = '<a href="' . esc_url($edit) . '">' . esc_html($label) . '</a>';
      } else {
        $label = esc_html($label);
      }
      echo '<tr>';
      echo '<td>' . wp_kses_post($label) . '</td>';
      echo '<td><code>' . esc_html($sku) . '</code></td>';
      echo '<td>' . esc_html((string) $cap) . '</td>';
      echo '<td>' . esc_html((string) $sold) . '</td>';
      echo '<td>' . esc_html((string) $old) . '</td>';
      echo '<td>' . esc_html((string) $new) . '</td>';
      echo '<td>' . esc_html($status) . '</td>';
      echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
  }


  // Calendar (Core)
  $settings = (array) get_option('vms_settings', array());
  $calendar_public_enabled = array_key_exists('calendar_public_shortcode_enabled', $settings) ? (int) $settings['calendar_public_shortcode_enabled'] : 1;
  $calendar_show_tickets = !empty($settings['calendar_show_tickets_sold_to_vendors']) ? 1 : 0;
  $calendar_vendor_overlay = array_key_exists('calendar_vendor_show_event_overlay', $settings) ? (int) $settings['calendar_vendor_show_event_overlay'] : 1;
  $calendar_vendor_tentative = !empty($settings['calendar_vendor_show_tentative']) ? 1 : 0;
  $calendar_open_slots_vendor = array_key_exists('calendar_show_open_slots_vendor', $settings) ? (int) $settings['calendar_show_open_slots_vendor'] : 1;
  $calendar_open_slots_public = !empty($settings['calendar_show_open_slots_public']) ? 1 : 0;
  $calendar_public_show_vendors = array_key_exists('calendar_public_show_vendors', $settings) ? (int) $settings['calendar_public_show_vendors'] : 1;
  $calendar_public_hide_past = array_key_exists('calendar_public_hide_past_default', $settings) ? (!empty($settings['calendar_public_hide_past_default']) ? 1 : 0) : 1;
  $calendar_public_default_view = isset($settings['calendar_public_default_view']) ? sanitize_key((string) $settings['calendar_public_default_view']) : 'auto';
  if (!in_array($calendar_public_default_view, array('auto', 'month', 'compact', 'list'), true)) {
    $calendar_public_default_view = 'auto';
  }
  $public_calendar_page_id = isset($settings['public_calendar_page_id']) ? absint($settings['public_calendar_page_id']) : 0;
  $public_calendar_custom_url = isset($settings['public_calendar_custom_url']) ? trim((string) $settings['public_calendar_custom_url']) : '';
  $public_calendar_resolved_url = function_exists('vms_get_public_event_calendar_url') ? (string) vms_get_public_event_calendar_url() : '';
  $calendar_open_slot_target = isset($settings['calendar_open_slot_link_target']) ? sanitize_key((string) $settings['calendar_open_slot_link_target']) : 'vendor_dashboard';
  if (!in_array($calendar_open_slot_target, array('vendor_dashboard', 'vendor_registration', 'custom'), true)) {
    $calendar_open_slot_target = 'vendor_dashboard';
  }
  $calendar_open_slot_custom = isset($settings['calendar_open_slot_link_custom_url']) ? esc_url((string) $settings['calendar_open_slot_link_custom_url']) : '';
  $parse_assoc_map = static function ($raw): array {
    if (function_exists('vms_calendar_parse_assoc_map')) {
      return (array) vms_calendar_parse_assoc_map($raw);
    }
    return is_array($raw) ? $raw : array();
  };
  $saved_icon_map = $parse_assoc_map($settings['calendar_vendor_type_icons'] ?? array());
  $saved_slot_limit_map = $parse_assoc_map($settings['calendar_default_slot_limits'] ?? array());
  $saved_vendor_visibility_map = $parse_assoc_map($settings['calendar_vendor_show_other_vendors_by_type'] ?? array());
  $saved_open_slot_display_map = $parse_assoc_map($settings['calendar_open_slot_display_by_vendor_type'] ?? array());
  $saved_target_by_type_map = $parse_assoc_map($settings['calendar_open_slot_link_target_by_type'] ?? array());
  $saved_custom_url_by_type_map = $parse_assoc_map($settings['calendar_open_slot_link_custom_url_by_type'] ?? array());
  $saved_public_visibility_map = $parse_assoc_map($settings['calendar_public_show_vendors_by_type'] ?? array());
  $vendor_type_rows = vms_settings_calendar_vendor_type_rows(array_merge(
    (array) $saved_icon_map,
    (array) $saved_slot_limit_map,
    (array) $saved_vendor_visibility_map,
    (array) $saved_open_slot_display_map,
    (array) $saved_target_by_type_map,
    (array) $saved_custom_url_by_type_map,
    (array) $saved_public_visibility_map
  ));
  $bool_choice_for_slug = static function (array $map, string $slug): string {
    if (!array_key_exists($slug, $map)) {
      return '';
    }
    $value = $map[$slug];
    if (is_array($value) || is_object($value) || $value === null) {
      return '';
    }
    return (!empty($value) && $value !== '0' && strtolower((string) $value) !== 'false') ? '1' : '0';
  };

  echo '<h2 class="vms-mt-24">' . esc_html__('Calendar', 'backstage-venue-manager') . '</h2>';
  echo '<table class="form-table" role="presentation">';

  echo '<tr><th scope="row">' . esc_html__('Vendor Type Icons', 'backstage-venue-manager') . '</th><td>';
  $icon_choices = vms_settings_calendar_icon_choices();

  if (empty($vendor_type_rows)) {
    echo '<p class="description">' . esc_html__('Create Vendor Types first. Then you can pick icons from a list here.', 'backstage-venue-manager') . '</p>';
  } else {
    echo '<div class="vms-settings-icon-grid">';
    foreach ($vendor_type_rows as $row) {
      $slug = sanitize_key((string) ($row['slug'] ?? ''));
      if ($slug === '') {
        continue;
      }
      $label = trim((string) ($row['label'] ?? $slug));
      if ($label === '') {
        $label = $slug;
      }
      $current_icon = trim((string) ($saved_icon_map[$slug] ?? ''));
      $field_id = 'vms-cal-icon-' . $slug;

      echo '<label class="vms-settings-icon-row" for="' . esc_attr($field_id) . '">';
      echo '<span class="vms-settings-icon-type">' . esc_html($label) . '</span>';
      echo '<span class="vms-settings-icon-select-wrap">';
      echo '<select id="' . esc_attr($field_id) . '" name="vms_settings[calendar_vendor_type_icons][' . esc_attr($slug) . ']" class="vms-settings-icon-select">';
      echo '<option value="">' . esc_html__('No icon', 'backstage-venue-manager') . '</option>';
      foreach ($icon_choices as $icon => $icon_label) {
        $option_label = trim($icon . ' ' . (string) $icon_label);
        echo '<option value="' . esc_attr((string) $icon) . '"' . selected($current_icon, (string) $icon, false) . '>' . esc_html($option_label) . '</option>';
      }
      if ($current_icon !== '' && !array_key_exists($current_icon, $icon_choices)) {
        echo '<option value="' . esc_attr($current_icon) . '" selected>' . esc_html($current_icon . ' ' . __('(custom saved)', 'backstage-venue-manager')) . '</option>';
      }
      echo '</select>';
      $preview = ($current_icon !== '') ? $current_icon : '—';
      echo '<span class="vms-settings-icon-preview" aria-hidden="true">' . esc_html($preview) . '</span>';
      echo '</span>';
      echo '</label>';
    }
    echo '</div>';
  }
  echo '<p class="description">' . esc_html__('Pick icons from the list. No manual formatting required.', 'backstage-venue-manager') . '</p>';
  echo '</td></tr>';

  echo '<tr><th scope="row">' . esc_html__('Slot Limit Defaults', 'backstage-venue-manager') . '</th><td>';
  if (empty($vendor_type_rows)) {
    echo '<p class="description">' . esc_html__('Create Vendor Types first. Then you can set slot limits from pickers here.', 'backstage-venue-manager') . '</p>';
  } else {
    echo '<div class="vms-settings-map-grid">';
    foreach ($vendor_type_rows as $row) {
      $slug = sanitize_key((string) ($row['slug'] ?? ''));
      if ($slug === '') {
        continue;
      }
      $label = trim((string) ($row['label'] ?? $slug));
      if ($label === '') {
        $label = $slug;
      }
      $field_id = 'vms-cal-slot-limit-' . $slug;
      $limit_value = '';
      if (array_key_exists($slug, $saved_slot_limit_map)) {
        $raw_limit = $saved_slot_limit_map[$slug];
        if (!is_array($raw_limit) && !is_object($raw_limit) && $raw_limit !== null && trim((string) $raw_limit) !== '') {
          $limit_value = (string) max(0, absint($raw_limit));
        }
      }
      echo '<label class="vms-settings-map-row" for="' . esc_attr($field_id) . '">';
      echo '<span class="vms-settings-map-type">' . esc_html($label) . '</span>';
      echo '<span class="vms-settings-map-controls">';
      echo '<input id="' . esc_attr($field_id) . '" type="number" min="0" step="1" class="small-text vms-settings-map-number" name="vms_settings[calendar_default_slot_limits][' . esc_attr($slug) . ']" value="' . esc_attr($limit_value) . '" placeholder="' . esc_attr__('Use default', 'backstage-venue-manager') . '" />';
      echo '</span>';
      echo '</label>';
    }
    echo '</div>';
  }
  echo '<p class="description">' . esc_html__('Set max slots per Vendor Type. Leave blank to keep default behavior.', 'backstage-venue-manager') . '</p>';
  echo '</td></tr>';

  echo '<tr><th scope="row">' . esc_html__('Vendor Calendar Visibility', 'backstage-venue-manager') . '</th><td>';
  echo '<label><input type="checkbox" name="vms_settings[calendar_vendor_show_event_overlay]" value="1" ' . checked($calendar_vendor_overlay, 1, false) . ' /> ' . esc_html__('Show scheduled event overlay in vendor availability calendar', 'backstage-venue-manager') . '</label><br />';
  echo '<label><input type="checkbox" name="vms_settings[calendar_vendor_show_tentative]" value="1" ' . checked($calendar_vendor_tentative, 1, false) . ' /> ' . esc_html__('Show Draft/Ready events to vendors as Tentative', 'backstage-venue-manager') . '</label><br />';
  echo '<label><input type="checkbox" name="vms_settings[calendar_show_tickets_sold_to_vendors]" value="1" ' . checked($calendar_show_tickets, 1, false) . ' /> ' . esc_html__('Show tickets sold to vendors', 'backstage-venue-manager') . '</label><br />';
  echo '<label><input type="checkbox" name="vms_settings[calendar_show_open_slots_vendor]" value="1" ' . checked($calendar_open_slots_vendor, 1, false) . ' /> ' . esc_html__('Show open slot indicator in vendor calendar', 'backstage-venue-manager') . '</label><br />';
  echo '<label><input type="checkbox" name="vms_settings[calendar_show_open_slots_public]" value="1" ' . checked($calendar_open_slots_public, 1, false) . ' /> ' . esc_html__('Show open slot indicator in public calendar', 'backstage-venue-manager') . '</label>';
  echo '<p><strong>' . esc_html__('Per-type overrides', 'backstage-venue-manager') . '</strong></p>';
  if (empty($vendor_type_rows)) {
    echo '<p class="description">' . esc_html__('Create Vendor Types first. Then you can set per-type visibility here.', 'backstage-venue-manager') . '</p>';
  } else {
    echo '<div class="vms-settings-map-grid">';
    foreach ($vendor_type_rows as $row) {
      $slug = sanitize_key((string) ($row['slug'] ?? ''));
      if ($slug === '') {
        continue;
      }
      $label = trim((string) ($row['label'] ?? $slug));
      if ($label === '') {
        $label = $slug;
      }
      $other_choice = $bool_choice_for_slug((array) $saved_vendor_visibility_map, $slug);
      $open_slot_choice = $bool_choice_for_slug((array) $saved_open_slot_display_map, $slug);
      echo '<div class="vms-settings-map-row vms-settings-map-row-multi">';
      echo '<span class="vms-settings-map-type">' . esc_html($label) . '</span>';
      echo '<span class="vms-settings-map-controls">';
      /* translators: %s: vendor type label */
      echo '<label class="screen-reader-text" for="vms-cal-other-vendors-' . esc_attr($slug) . '">' . esc_html(sprintf(__('Show other vendors for %s', 'backstage-venue-manager'), $label)) . '</label>';
      echo '<select id="vms-cal-other-vendors-' . esc_attr($slug) . '" class="vms-settings-map-select" name="vms_settings[calendar_vendor_show_other_vendors_by_type][' . esc_attr($slug) . ']">';
      echo '<option value="">' . esc_html__('Use default', 'backstage-venue-manager') . '</option>';
      echo '<option value="1"' . selected($other_choice, '1', false) . '>' . esc_html__('Show other vendors', 'backstage-venue-manager') . '</option>';
      echo '<option value="0"' . selected($other_choice, '0', false) . '>' . esc_html__('Hide other vendors', 'backstage-venue-manager') . '</option>';
      echo '</select>';
      /* translators: %s: vendor type label */
      echo '<label class="screen-reader-text" for="vms-cal-open-slot-' . esc_attr($slug) . '">' . esc_html(sprintf(__('Open slot indicator for %s', 'backstage-venue-manager'), $label)) . '</label>';
      echo '<select id="vms-cal-open-slot-' . esc_attr($slug) . '" class="vms-settings-map-select" name="vms_settings[calendar_open_slot_display_by_vendor_type][' . esc_attr($slug) . ']">';
      echo '<option value="">' . esc_html__('Use default', 'backstage-venue-manager') . '</option>';
      echo '<option value="1"' . selected($open_slot_choice, '1', false) . '>' . esc_html__('Show open slots', 'backstage-venue-manager') . '</option>';
      echo '<option value="0"' . selected($open_slot_choice, '0', false) . '>' . esc_html__('Hide open slots', 'backstage-venue-manager') . '</option>';
      echo '</select>';
      echo '</span>';
      echo '</div>';
    }
    echo '</div>';
  }
  echo '<p class="description">' . esc_html__('Use "Use default" to keep automatic behavior by vendor type.', 'backstage-venue-manager') . '</p>';
  echo '</td></tr>';

  echo '<tr><th scope="row">' . esc_html__('Open Slot Link', 'backstage-venue-manager') . '</th><td>';
  echo '<select name="vms_settings[calendar_open_slot_link_target]">';
  echo '<option value="vendor_dashboard"' . selected($calendar_open_slot_target, 'vendor_dashboard', false) . '>' . esc_html__('Vendor dashboard', 'backstage-venue-manager') . '</option>';
  echo '<option value="vendor_registration"' . selected($calendar_open_slot_target, 'vendor_registration', false) . '>' . esc_html__('Vendor registration page', 'backstage-venue-manager') . '</option>';
  echo '<option value="custom"' . selected($calendar_open_slot_target, 'custom', false) . '>' . esc_html__('Custom URL', 'backstage-venue-manager') . '</option>';
  echo '</select>';
  echo '<p><input type="url" class="regular-text" name="vms_settings[calendar_open_slot_link_custom_url]" value="' . esc_attr($calendar_open_slot_custom) . '" placeholder="https://example.com/apply" /></p>';
  echo '<p><strong>' . esc_html__('Per-type link target overrides', 'backstage-venue-manager') . '</strong></p>';
  if (empty($vendor_type_rows)) {
    echo '<p class="description">' . esc_html__('Create Vendor Types first. Then you can set per-type link behavior here.', 'backstage-venue-manager') . '</p>';
  } else {
    echo '<div class="vms-settings-map-grid">';
    foreach ($vendor_type_rows as $row) {
      $slug = sanitize_key((string) ($row['slug'] ?? ''));
      if ($slug === '') {
        continue;
      }
      $label = trim((string) ($row['label'] ?? $slug));
      if ($label === '') {
        $label = $slug;
      }
      $target_choice = isset($saved_target_by_type_map[$slug]) ? sanitize_key((string) $saved_target_by_type_map[$slug]) : '';
      if (!in_array($target_choice, array('vendor_dashboard', 'vendor_registration', 'custom'), true)) {
        $target_choice = '';
      }
      $custom_choice = isset($saved_custom_url_by_type_map[$slug]) ? esc_url((string) $saved_custom_url_by_type_map[$slug]) : '';

      echo '<div class="vms-settings-map-row vms-settings-map-row-link">';
      echo '<span class="vms-settings-map-type">' . esc_html($label) . '</span>';
      echo '<span class="vms-settings-map-controls">';
      /* translators: %s: vendor type label */
      echo '<label class="screen-reader-text" for="vms-cal-link-target-' . esc_attr($slug) . '">' . esc_html(sprintf(__('Open slot target for %s', 'backstage-venue-manager'), $label)) . '</label>';
      echo '<select id="vms-cal-link-target-' . esc_attr($slug) . '" class="vms-settings-map-select" name="vms_settings[calendar_open_slot_link_target_by_type][' . esc_attr($slug) . ']">';
      echo '<option value="">' . esc_html__('Use global', 'backstage-venue-manager') . '</option>';
      echo '<option value="vendor_dashboard"' . selected($target_choice, 'vendor_dashboard', false) . '>' . esc_html__('Vendor dashboard', 'backstage-venue-manager') . '</option>';
      echo '<option value="vendor_registration"' . selected($target_choice, 'vendor_registration', false) . '>' . esc_html__('Vendor registration', 'backstage-venue-manager') . '</option>';
      echo '<option value="custom"' . selected($target_choice, 'custom', false) . '>' . esc_html__('Custom URL', 'backstage-venue-manager') . '</option>';
      echo '</select>';
      /* translators: %s: vendor type label */
      echo '<label class="screen-reader-text" for="vms-cal-link-custom-' . esc_attr($slug) . '">' . esc_html(sprintf(__('Custom URL for %s', 'backstage-venue-manager'), $label)) . '</label>';
      echo '<input id="vms-cal-link-custom-' . esc_attr($slug) . '" type="url" class="regular-text vms-settings-map-url" name="vms_settings[calendar_open_slot_link_custom_url_by_type][' . esc_attr($slug) . ']" value="' . esc_attr($custom_choice) . '" placeholder="https://example.com/apply" />';
      echo '</span>';
      echo '</div>';
    }
    echo '</div>';
  }
  echo '<p class="description">' . esc_html__('Choose a target per Vendor Type. Enter a custom URL only when target is Custom URL.', 'backstage-venue-manager') . '</p>';
  echo '</td></tr>';

  echo '<tr><th scope="row">' . esc_html__('Public Calendar', 'backstage-venue-manager') . '</th><td>';
  echo '<label><input type="checkbox" name="vms_settings[calendar_public_shortcode_enabled]" value="1" ' . checked($calendar_public_enabled, 1, false) . ' /> ' . esc_html__('Enable [vms_public_calendar] shortcode', 'backstage-venue-manager') . '</label><br />';
  echo '<label><input type="checkbox" name="vms_settings[calendar_public_show_vendors]" value="1" ' . checked($calendar_public_show_vendors, 1, false) . ' /> ' . esc_html__('Allow vendor lines in public calendar', 'backstage-venue-manager') . '</label><br />';
  echo '<label><input type="checkbox" name="vms_settings[calendar_public_hide_past_default]" value="1" ' . checked($calendar_public_hide_past, 1, false) . ' /> ' . esc_html__('Hide past events by default', 'backstage-venue-manager') . '</label>';
  echo '<p><label for="vms_public_calendar_default_view"><strong>' . esc_html__('Default public calendar view', 'backstage-venue-manager') . '</strong></label><br />';
  echo '<select id="vms_public_calendar_default_view" name="vms_settings[calendar_public_default_view]">';
  echo '<option value="auto"' . selected($calendar_public_default_view, 'auto', false) . '>' . esc_html__('Auto', 'backstage-venue-manager') . '</option>';
  echo '<option value="month"' . selected($calendar_public_default_view, 'month', false) . '>' . esc_html__('Month', 'backstage-venue-manager') . '</option>';
  echo '<option value="compact"' . selected($calendar_public_default_view, 'compact', false) . '>' . esc_html__('Compact weekend chunks', 'backstage-venue-manager') . '</option>';
  echo '<option value="list"' . selected($calendar_public_default_view, 'list', false) . '>' . esc_html__('List', 'backstage-venue-manager') . '</option>';
  echo '</select></p>';
  echo '<p class="description">' . esc_html__('Compact weekend chunks starts at the selected month, shows three months at a time on desktop, and only keeps weekday columns that are actually open or booked for that month.', 'backstage-venue-manager') . '</p>';
  echo '<p><strong>' . esc_html__('Customer-facing event calendar link', 'backstage-venue-manager') . '</strong></p>';
  echo '<p class="description">' . esc_html__('Used in public cancellation notices and other customer-facing links that send visitors to browse upcoming events.', 'backstage-venue-manager') . '</p>';
  echo '<p>';
  echo '<label for="vms_public_calendar_page_id"><strong>' . esc_html__('WordPress page', 'backstage-venue-manager') . '</strong></label><br />';
  $public_calendar_page_dropdown = wp_dropdown_pages(array(
    'name'              => 'vms_settings[public_calendar_page_id]',
    'id'                => 'vms_public_calendar_page_id',
    'selected'          => esc_attr((string) $public_calendar_page_id),
    'show_option_none'  => esc_html__('— Auto-detect —', 'backstage-venue-manager'),
    'option_none_value' => '0',
    'post_status'       => 'publish',
    'echo'              => 0,
  ));
  if (is_string($public_calendar_page_dropdown) && $public_calendar_page_dropdown !== '') {
    echo wp_kses($public_calendar_page_dropdown, vms_settings_page_dropdown_allowed_html());
  }
  echo '</p>';
  echo '<p class="description">' . esc_html__('Choose the public page customers should use to browse events. Auto-detect first looks for the VMS public calendar page, then falls back to the TEC events archive.', 'backstage-venue-manager') . '</p>';
  echo '<p>';
  echo '<label for="vms_public_calendar_custom_url"><strong>' . esc_html__('Advanced custom URL override', 'backstage-venue-manager') . '</strong></label><br />';
  echo '<input id="vms_public_calendar_custom_url" type="text" class="regular-text" name="vms_settings[public_calendar_custom_url]" value="' . esc_attr($public_calendar_custom_url) . '" placeholder="/events-calendar" />';
  echo '</p>';
  echo '<p class="description">' . esc_html__('Optional. Use only when the destination is not a normal WordPress page, such as a TEC archive, external calendar, or custom landing page. Site-relative paths like /events-calendar are allowed.', 'backstage-venue-manager') . '</p>';
  if ($public_calendar_resolved_url !== '') {
    echo '<p class="description"><strong>' . esc_html__('Resolved link:', 'backstage-venue-manager') . '</strong> <code>' . esc_html($public_calendar_resolved_url) . '</code></p>';
  }
  echo '<p><strong>' . esc_html__('Public vendor visibility by type', 'backstage-venue-manager') . '</strong></p>';
  if (empty($vendor_type_rows)) {
    echo '<p class="description">' . esc_html__('Create Vendor Types first. Then you can set public visibility by type here.', 'backstage-venue-manager') . '</p>';
  } else {
    echo '<div class="vms-settings-map-grid">';
    foreach ($vendor_type_rows as $row) {
      $slug = sanitize_key((string) ($row['slug'] ?? ''));
      if ($slug === '') {
        continue;
      }
      $label = trim((string) ($row['label'] ?? $slug));
      if ($label === '') {
        $label = $slug;
      }
      $public_choice = $bool_choice_for_slug((array) $saved_public_visibility_map, $slug);
      echo '<div class="vms-settings-map-row">';
      echo '<span class="vms-settings-map-type">' . esc_html($label) . '</span>';
      echo '<span class="vms-settings-map-controls">';
      /* translators: %s: vendor type label */
      echo '<label class="screen-reader-text" for="vms-cal-public-vendors-' . esc_attr($slug) . '">' . esc_html(sprintf(__('Public vendor visibility for %s', 'backstage-venue-manager'), $label)) . '</label>';
      echo '<select id="vms-cal-public-vendors-' . esc_attr($slug) . '" class="vms-settings-map-select" name="vms_settings[calendar_public_show_vendors_by_type][' . esc_attr($slug) . ']">';
      echo '<option value="">' . esc_html__('Use default', 'backstage-venue-manager') . '</option>';
      echo '<option value="1"' . selected($public_choice, '1', false) . '>' . esc_html__('Show vendors', 'backstage-venue-manager') . '</option>';
      echo '<option value="0"' . selected($public_choice, '0', false) . '>' . esc_html__('Hide vendors', 'backstage-venue-manager') . '</option>';
      echo '</select>';
      echo '</span>';
      echo '</div>';
    }
    echo '</div>';
  }
  echo '<p class="description">' . esc_html__('Use default keeps built-in behavior (Primary Vendor shown publicly by default).', 'backstage-venue-manager') . '</p>';
  echo '</td></tr>';

  echo '</table>';


  // Tax (operator-easy)
  $settings = (array) get_option('vms_settings', array());
  $provider = isset($settings['tax_w9_provider']) ? (string) $settings['tax_w9_provider'] : 'upload';

  echo '<h2 class="vms-mt-24">Tax</h2>';

  echo '<table class="form-table" role="presentation">';

  echo '<tr>';
  echo '<th scope="row"><label for="vms_tax_w9_provider">W-9 Provider</label></th>';
  echo '<td>';
  echo '<select id="vms_tax_w9_provider" name="vms_settings[tax_w9_provider]">';
  echo '<option value="upload"' . selected($provider, 'upload', false) . '>Upload to this website</option>';
  echo '<option value="quickbooks_email"' . selected($provider, 'quickbooks_email', false) . '>QuickBooks Online (email request)</option>';
  echo '<option value="tax1099_email"' . selected($provider, 'tax1099_email', false) . '>Tax1099 (email request)</option>';
  echo '</select>';
  echo '<p class="description">QuickBooks Online and Tax1099 are email-driven off-site workflows. Vendors confirm completion back in the portal. No links are used.</p>';
  echo '</td>';
  echo '</tr>';

  echo '</table>';

  // Interface (operator-friendly help)
  $help_mode = isset($settings['help_mode']) ? sanitize_key((string) $settings['help_mode']) : 'basic';
  if (!in_array($help_mode, array('off', 'basic', 'guided'), true)) {
    $help_mode = 'basic';
  }

  echo '<h2 class="vms-mt-24">Interface</h2>';
  echo '<table class="form-table" role="presentation">';

  echo '<tr>';
  echo '<th scope="row"><label for="vms_help_mode">Help Mode</label></th>';
  echo '<td>';
  echo '<select id="vms_help_mode" name="vms_settings[help_mode]">';
  echo '<option value="off"' . selected($help_mode, 'off', false) . '>Off</option>';
  echo '<option value="basic"' . selected($help_mode, 'basic', false) . '>Basic</option>';
  echo '<option value="guided"' . selected($help_mode, 'guided', false) . '>Guided</option>';
  echo '</select>';
  echo '<p class="description">Basic adds small tooltips on confusing items. Guided adds extra setup hints.</p>';
  echo '</td>';
  echo '</tr>';

  echo '</table>';

  submit_button();
  echo '</form>';

  echo '<hr class="vms-hr-spaced">';
  echo '<h2>Public Pages</h2>';
  echo '<p class="description">VMS uses these pages for vendors and staff. If any are missing, you can repair them here.</p>';

  $pages = vms_required_public_pages();

  echo '<div class="vms-card-wide">';
  echo '<table class="widefat striped vms-mt-10">';
  echo '<thead><tr>';
  echo '<th class="vms-col-220">Page</th>';
  echo '<th>Slug</th>';
  echo '<th>Status</th>';
  echo '<th>Link</th>';
  echo '</tr></thead><tbody>';

  foreach ($pages as $key => $spec) {
    $page = get_page_by_path($spec['slug'], OBJECT, 'page');

    $status = 'Missing';
    $status_html = '<span class="vms-status vms-status-missing">⚠️ Missing</span>';
    $link_html = '—';

    if ($page) {
      if ($page->post_status === 'trash') {
        $status = 'In Trash';
        $status_html = '<span class="vms-status vms-status-trash">🗑️ In Trash</span>';
      } else {
        $status = 'OK';
        $status_html = '<span class="vms-status vms-status-ok">✅ OK</span>';
      }

      $url = get_permalink($page->ID);
      if ($url) {
        $link_html = '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">View</a>';
      }
    }

    echo '<tr>';
    echo '<td><strong>' . esc_html($spec['title']) . '</strong></td>';
    echo '<td><code>' . esc_html($spec['slug']) . '</code></td>';
    echo '<td>' . wp_kses_post($status_html) . '</td>';
    echo '<td>' . wp_kses_post($link_html) . '</td>';
    echo '</tr>';
  }

  echo '</tbody></table>';


  $repair_url = wp_nonce_url(
    admin_url('admin-post.php?action=vms_repair_pages'),
    'vms_repair_pages'
  );

  echo '<p class="vms-mt-14">';
  echo '<a class="button button-primary" href="' . esc_url($repair_url) . '">Repair / Recreate Pages</a>';
  echo '<span class="description vms-ml-10">Creates missing pages and restores any that are trashed.</span>';
  echo '</p>';

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only settings notice state only affects admin feedback.
			if (vms_request_read_scalar($_GET, 'vms_entitlement_image_sync_done') === '1') {
			$img_sync = get_transient('vms_entitlement_image_sync_last');
			if (is_array($img_sync)) {
				$ts_readable = wp_date('Y-m-d H:i', (int) ($img_sync['ts'] ?? 0), wp_timezone());
				$errors = (int) ($img_sync['errors'] ?? 0);
				$notice_class = ($errors > 0) ? 'notice notice-warning' : 'notice notice-success';

				echo '<div class="' . esc_attr($notice_class) . '"><p><strong>Entitlement image sync complete.</strong> ';
				echo 'Checked: ' . (int) ($img_sync['checked'] ?? 0) . ' &nbsp;|&nbsp; ';
				echo 'Updated: ' . (int) ($img_sync['updated'] ?? 0) . ' &nbsp;|&nbsp; ';
					echo 'Skipped (no entitlement image): ' . (int) ($img_sync['skipped'] ?? 0) . ' &nbsp;|&nbsp; ';
					echo 'Errors: ' . esc_html((string) $errors) . ' &nbsp;|&nbsp; ';
				echo esc_html($ts_readable);
				echo '</p></div>';

				$error_rows = array();
				$rows = is_array($img_sync['results'] ?? null) ? $img_sync['results'] : array();
				foreach ($rows as $row) {
					if (!is_array($row)) {
						continue;
					}
					$status = isset($row['status']) ? (string) $row['status'] : '';
					if (strpos($status, 'error_') === 0) {
						$error_rows[] = $row;
					}
				}

				if (!empty($error_rows)) {
					echo '<div class="vms-card vms-mt-10">';
					echo '<h3>Entitlement Image Sync Errors</h3>';
					echo '<ul class="ul-disc">';
					foreach ($error_rows as $row) {
						$product_id = absint($row['product_id'] ?? 0);
						$entitlement_id = sanitize_key((string) ($row['entitlement_id'] ?? ''));
						$message = sanitize_text_field((string) ($row['message'] ?? 'error'));
							echo '<li>';
							echo 'Product #' . esc_html((string) $product_id) . ' | Entitlement: ' . esc_html($entitlement_id !== '' ? $entitlement_id : '(missing)') . ' | ';
							echo esc_html($message);
							echo '</li>';
					}
					echo '</ul>';
					echo '</div>';
				}
			}
		}

		echo '<div class="vms-card vms-mt-14">';
		echo '<h2>Ticketing Image Tools</h2>';
		echo '<p>Sync GA ticket and entitlement/add-on images from Ticketing v2 config to WooCommerce product featured images so cart, checkout, and order thumbnails stay aligned.</p>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('vms_sync_entitlement_images');
		echo '<input type="hidden" name="action" value="vms_sync_entitlement_images" />';
		submit_button('Sync Ticket Images to Woo Products', 'secondary', 'submit', false);
		echo '</form>';
		echo '<p class="description">Safe and reversible. This only sets or clears Woo product featured images and never deletes media files.</p>';
		echo '</div>';

  
			// =========================================================
			// Data Integrity Tools (On-demand Scan)
			// =========================================================
		vms_render_settings_page_integrity_scan_result(vms_get_settings_page_integrity_scan_result_context());

		echo '<div class="vms-card">';
		echo '<h2>Data Integrity</h2>';
		echo '<p>Scan Event Plans for broken links: missing or trashed Vendors, orphaned Venues, and missing or trashed calendar events. Published Event Plans with a linked calendar event that is not published (and not scheduled) are flagged as <strong>Needs attention</strong> unless suppressed per plan. Event Plans are forced back to Draft only for broken links (missing, trashed, or invalid references).</p>';

		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('vms_integrity_scan');
		echo '<input type="hidden" name="action" value="vms_integrity_scan" />';

		echo '<p><label for="vms_integrity_mode"><strong>Mode</strong></label><br />';
		echo '<select id="vms_integrity_mode" name="mode">';
		echo '<option value="all">Scan vendor links + venue links + calendar events</option>';
		echo '<option value="vendors">Scan vendor links only</option>';
		echo '<option value="venues">Scan venue links only</option>';
		echo '<option value="events">Scan calendar events only</option>';
		echo '</select></p>';

		echo '<p><label for="vms_integrity_limit"><strong>Scan limit</strong></label><br />';
		echo '<input id="vms_integrity_limit" name="limit" type="number" min="1" max="5000" value="500" /></p>';

		submit_button('Run integrity scan', 'primary', 'submit', false);
		echo '</form>';

		echo '</div>';

echo '</div>';
}

function vms_settings_page_ticketing_stock_notice_placeholder(): string
{
  return '<!-- vms-settings-ticketing-stock-notice -->';
}

function vms_get_settings_page_ticketing_stock_notice_state(bool $refresh = false): array
{
  static $state = null;

  if ($refresh) {
    $state = null;
  }

  if (is_array($state)) {
    return $state;
  }

  $preview_key = function_exists('vms_ticketing_stock_preview_transient_key')
    ? vms_ticketing_stock_preview_transient_key(get_current_user_id())
    : 'vms_ticketing_stock_preview_' . max(1, get_current_user_id());

  // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only ticketing-stock notice state only affects admin messaging.
  $preview_done = (vms_request_read_scalar($_GET, 'vms_ticketing_stock_preview_done') === '1');
  // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only ticketing-stock notice state only affects admin messaging.
  $commit_done = (vms_request_read_scalar($_GET, 'vms_ticketing_stock_commit_done') === '1');

  $state = array(
    'preview_done' => $preview_done,
    'commit_done' => $commit_done,
    'preview_report' => get_transient($preview_key),
    'commit_report' => false,
  );

  if ($state['commit_done']) {
    $state['commit_report'] = get_transient('vms_ticketing_stock_reconcile_last');
  }

  return $state;
}

function vms_render_settings_page_notice_bar(): void
{
  vms_render_settings_page_notices();
  vms_render_settings_page_ticketing_stock_notices(vms_get_settings_page_ticketing_stock_notice_state());
}

function vms_get_settings_page_ticketing_stock_notice_markup(): string
{
  ob_start();
  vms_render_settings_page_ticketing_stock_notices(vms_get_settings_page_ticketing_stock_notice_state());
  return (string) ob_get_clean();
}

function vms_render_settings_page_ticketing_stock_notices(array $ticketing_stock_notice_state): void
{
  $preview_rep = $ticketing_stock_notice_state['preview_report'] ?? false;

  if (!empty($ticketing_stock_notice_state['preview_done']) && is_array($preview_rep)) {
    $checked = (int) ($preview_rep['checked'] ?? 0);
    $updated = (int) ($preview_rep['updated'] ?? 0);
    $skipped = (int) ($preview_rep['skipped'] ?? 0);
    $errors  = (int) ($preview_rep['errors'] ?? 0);
    echo '<div class="notice notice-info"><p>' . esc_html(sprintf('Ticketing stock preview ready: checked=%d would_update=%d skipped=%d errors=%d', $checked, $updated, $skipped, $errors)) . '</p></div>';
  }

  $commit_rep = $ticketing_stock_notice_state['commit_report'] ?? false;

  if (!empty($ticketing_stock_notice_state['commit_done']) && is_array($commit_rep)) {
    $checked = (int) ($commit_rep['checked'] ?? 0);
    $updated = (int) ($commit_rep['updated'] ?? 0);
    $skipped = (int) ($commit_rep['skipped'] ?? 0);
    $errors  = (int) ($commit_rep['errors'] ?? 0);
    echo '<div class="notice notice-success"><p>' . esc_html(sprintf('Ticketing stock reconcile complete: checked=%d updated=%d skipped=%d errors=%d', $checked, $updated, $skipped, $errors)) . '</p></div>';
  }
}

function vms_settings_page_integrity_scan_normalize_count($value): int
{
  $count = (int) $value;
  return $count < 0 ? 0 : $count;
}

function vms_settings_page_integrity_scan_normalize_limit($value): int
{
  $limit = (int) $value;
  if ($limit < 1) {
    return 500;
  }
  if ($limit > 5000) {
    return 5000;
  }

  return $limit;
}

function vms_settings_page_integrity_scan_normalize_mode($value): string
{
  $mode = sanitize_key((string) $value);
  if (!in_array($mode, array('all', 'vendors', 'venues', 'events'), true)) {
    return 'all';
  }

  return $mode;
}

/**
 * @return array{visible:bool,label:string,href:string,class:string,target:string,rel:string}
 */
function vms_settings_page_integrity_scan_default_action_context(): array
{
  return array(
    'visible' => false,
    'label' => '',
    'href' => '',
    'class' => '',
    'target' => '',
    'rel' => '',
  );
}

/**
 * @param mixed $results
 * @return array{section:string,label:string,checked:int,missing:int,trashed:int,secondary_missing:int,secondary_trashed:int,unpublished:int,unlinked:int,cleared_refs:int,forced_draft:int,action:array{visible:bool,label:string,href:string,class:string,target:string,rel:string}}
 */
function vms_settings_page_integrity_scan_normalize_section_context(string $section, $results): array
{
  $results = is_array($results) ? $results : array();
  $context = array(
    'section' => $section,
    'label' => '',
    'checked' => 0,
    'missing' => 0,
    'trashed' => 0,
    'secondary_missing' => 0,
    'secondary_trashed' => 0,
    'unpublished' => 0,
    'unlinked' => 0,
    'cleared_refs' => 0,
    'forced_draft' => 0,
    'action' => vms_settings_page_integrity_scan_default_action_context(),
  );

  if ($section === 'vendors') {
    $context['label'] = 'Event Plans (Vendor links):';
    $context['checked'] = vms_settings_page_integrity_scan_normalize_count($results['checked'] ?? 0);
    $context['missing'] = vms_settings_page_integrity_scan_normalize_count($results['flagged_missing_vendor'] ?? 0);
    $context['trashed'] = vms_settings_page_integrity_scan_normalize_count($results['flagged_trashed_vendor'] ?? 0);
    $context['secondary_missing'] = vms_settings_page_integrity_scan_normalize_count($results['flagged_missing_secondary_vendor'] ?? 0);
    $context['secondary_trashed'] = vms_settings_page_integrity_scan_normalize_count($results['flagged_trashed_secondary_vendor'] ?? 0);
    $context['forced_draft'] = vms_settings_page_integrity_scan_normalize_count($results['forced_draft'] ?? 0);
    return $context;
  }

  if ($section === 'venues') {
    $context['label'] = 'Event Plans (Venue links):';
    $context['checked'] = vms_settings_page_integrity_scan_normalize_count($results['checked'] ?? 0);
    $context['missing'] = vms_settings_page_integrity_scan_normalize_count($results['flagged_missing_venue'] ?? 0);
    $context['trashed'] = vms_settings_page_integrity_scan_normalize_count($results['flagged_trashed_venue'] ?? 0);
    $context['unpublished'] = vms_settings_page_integrity_scan_normalize_count($results['flagged_venue_unpublished'] ?? 0);
    $context['cleared_refs'] = vms_settings_page_integrity_scan_normalize_count($results['cleared_venue_refs'] ?? 0);
    $context['forced_draft'] = vms_settings_page_integrity_scan_normalize_count($results['forced_draft'] ?? 0);
    if ($context['trashed'] > 0) {
      $context['action'] = array(
        'visible' => true,
        'label' => 'Review trashed venue links',
        'href' => admin_url('admin.php?page=vms-integrity-venue-links'),
        'class' => 'button button-secondary',
        'target' => '',
        'rel' => '',
      );
    }
    return $context;
  }

  $context['label'] = 'Event Plans (Calendar):';
  $context['checked'] = vms_settings_page_integrity_scan_normalize_count($results['checked'] ?? 0);
  $context['unlinked'] = vms_settings_page_integrity_scan_normalize_count($results['flagged_calendar_event_unlinked'] ?? 0);
  $context['missing'] = vms_settings_page_integrity_scan_normalize_count($results['flagged_missing_calendar_event'] ?? 0);
  $context['trashed'] = vms_settings_page_integrity_scan_normalize_count($results['flagged_trashed_calendar_event'] ?? 0);
  $context['unpublished'] = vms_settings_page_integrity_scan_normalize_count($results['flagged_calendar_event_unpublished'] ?? 0);
  $context['cleared_refs'] = vms_settings_page_integrity_scan_normalize_count($results['cleared_calendar_event_refs'] ?? 0);
  $context['forced_draft'] = vms_settings_page_integrity_scan_normalize_count($results['forced_draft'] ?? 0);
  if (
    $context['unlinked'] > 0 ||
    $context['missing'] > 0 ||
    $context['trashed'] > 0 ||
    $context['unpublished'] > 0
  ) {
    $context['action'] = array(
      'visible' => true,
      'label' => 'Review calendar links',
      'href' => admin_url('admin.php?page=vms-integrity-calendar-links'),
      'class' => 'button button-secondary',
      'target' => '',
      'rel' => '',
    );
  }

  return $context;
}

/**
 * @return array<string,int>
 */
function vms_settings_page_integrity_scan_normalize_single_mode_results(string $mode, array $results): array
{
  if ($mode === 'vendors') {
    return array(
      'checked' => vms_settings_page_integrity_scan_normalize_count($results['checked'] ?? 0),
      'flagged_missing_vendor' => vms_settings_page_integrity_scan_normalize_count($results['flagged_missing_vendor'] ?? 0),
      'flagged_trashed_vendor' => vms_settings_page_integrity_scan_normalize_count($results['flagged_trashed_vendor'] ?? 0),
      'flagged_missing_secondary_vendor' => vms_settings_page_integrity_scan_normalize_count($results['flagged_missing_secondary_vendor'] ?? 0),
      'flagged_trashed_secondary_vendor' => vms_settings_page_integrity_scan_normalize_count($results['flagged_trashed_secondary_vendor'] ?? 0),
      'removed_missing_secondary_vendor_ids' => vms_settings_page_integrity_scan_normalize_count($results['removed_missing_secondary_vendor_ids'] ?? 0),
      'forced_draft' => vms_settings_page_integrity_scan_normalize_count($results['forced_draft'] ?? 0),
    );
  }

  if ($mode === 'venues') {
    return array(
      'checked' => vms_settings_page_integrity_scan_normalize_count($results['checked'] ?? 0),
      'flagged_missing_venue' => vms_settings_page_integrity_scan_normalize_count($results['flagged_missing_venue'] ?? 0),
      'flagged_trashed_venue' => vms_settings_page_integrity_scan_normalize_count($results['flagged_trashed_venue'] ?? 0),
      'flagged_venue_unpublished' => vms_settings_page_integrity_scan_normalize_count($results['flagged_venue_unpublished'] ?? 0),
      'cleared_venue_refs' => vms_settings_page_integrity_scan_normalize_count($results['cleared_venue_refs'] ?? 0),
      'forced_draft' => vms_settings_page_integrity_scan_normalize_count($results['forced_draft'] ?? 0),
    );
  }

  if ($mode === 'events') {
    return array(
      'checked' => vms_settings_page_integrity_scan_normalize_count($results['checked'] ?? 0),
      'flagged_calendar_event_unlinked' => vms_settings_page_integrity_scan_normalize_count($results['flagged_calendar_event_unlinked'] ?? 0),
      'flagged_missing_calendar_event' => vms_settings_page_integrity_scan_normalize_count($results['flagged_missing_calendar_event'] ?? 0),
      'flagged_trashed_calendar_event' => vms_settings_page_integrity_scan_normalize_count($results['flagged_trashed_calendar_event'] ?? 0),
      'flagged_calendar_event_unpublished' => vms_settings_page_integrity_scan_normalize_count($results['flagged_calendar_event_unpublished'] ?? 0),
      'cleared_calendar_event_refs' => vms_settings_page_integrity_scan_normalize_count($results['cleared_calendar_event_refs'] ?? 0),
      'forced_draft' => vms_settings_page_integrity_scan_normalize_count($results['forced_draft'] ?? 0),
    );
  }

  return array();
}

/**
 * @param mixed $stored_result
 * @return array<string,mixed>
 */
function vms_build_settings_page_integrity_scan_result_context($stored_result, bool $scan_done_requested): array
{
  $context = array(
    'requested' => $scan_done_requested,
    'show' => false,
    'status' => $scan_done_requested ? 'missing' : 'hidden',
    'layout' => 'none',
    'notice_class' => 'notice notice-success',
    'summary_title' => 'Integrity scan complete.',
    'mode' => 'all',
    'mode_label' => 'all',
    'limit' => 500,
    'timestamp' => '',
    'single_result_json' => '',
    'sections' => array(),
  );

  if (!$scan_done_requested || !is_array($stored_result)) {
    return $context;
  }

  $results = $stored_result['results'] ?? null;
  if (!is_array($results) || $results === array()) {
    return $context;
  }

  $mode = vms_settings_page_integrity_scan_normalize_mode($stored_result['mode'] ?? 'all');
  $context['mode'] = $mode;
  $context['mode_label'] = $mode;
  $context['limit'] = vms_settings_page_integrity_scan_normalize_limit($stored_result['limit'] ?? 500);
  $context['timestamp'] = wp_date('Y-m-d H:i', (int) ($stored_result['ts'] ?? 0), wp_timezone());

  if (isset($results['vendors']) || isset($results['venues']) || isset($results['events'])) {
    $context['show'] = true;
    $context['status'] = 'composite';
    $context['layout'] = 'composite';
    $context['sections'] = array(
      vms_settings_page_integrity_scan_normalize_section_context('vendors', $results['vendors'] ?? array()),
      vms_settings_page_integrity_scan_normalize_section_context('venues', $results['venues'] ?? array()),
      vms_settings_page_integrity_scan_normalize_section_context('events', $results['events'] ?? array()),
    );
    return $context;
  }

  if (!in_array($mode, array('vendors', 'venues', 'events'), true)) {
    $context['status'] = 'invalid';
    return $context;
  }

  $single_results = vms_settings_page_integrity_scan_normalize_single_mode_results($mode, $results);
  if ($single_results === array()) {
    $context['status'] = 'invalid';
    return $context;
  }

  $context['show'] = true;
  $context['status'] = 'single';
  $context['layout'] = 'single';
  $context['single_result_json'] = (string) wp_json_encode($single_results);
  return $context;
}

/**
 * @return array<string,mixed>
 */
function vms_get_settings_page_integrity_scan_result_context(bool $refresh = false): array
{
  static $context = null;

  if ($refresh) {
    $context = null;
  }

  if (is_array($context)) {
    return $context;
  }

  // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only integrity-scan notice state only affects admin messaging.
  $scan_done_requested = (vms_request_read_scalar($_GET, 'vms_scan_done') === '1');
  $stored_result = $scan_done_requested ? get_transient('vms_integrity_scan_last') : false;
  $context = vms_build_settings_page_integrity_scan_result_context($stored_result, $scan_done_requested);

  return $context;
}

/**
 * @param array<string,mixed> $context
 */
function vms_render_settings_page_integrity_scan_result(array $context): void
{
  if (empty($context['show'])) {
    return;
  }

  echo '<div class="vms-settings-integrity-scan-result">';
  echo '<div class="' . esc_attr((string) ($context['notice_class'] ?? 'notice notice-success')) . '">';
  echo '<p><strong>' . esc_html((string) ($context['summary_title'] ?? 'Integrity scan complete.')) . '</strong> ';
  echo 'Mode: ' . esc_html((string) ($context['mode_label'] ?? 'all')) . ' &nbsp;|&nbsp; ';
  echo 'Limit: ' . (int) ($context['limit'] ?? 500) . ' &nbsp;|&nbsp; ';
  echo esc_html((string) ($context['timestamp'] ?? ''));
  echo '</p>';

  if (($context['layout'] ?? 'none') === 'composite') {
    $sections = is_array($context['sections'] ?? null) ? $context['sections'] : array();
    foreach ($sections as $section) {
      if (!is_array($section)) {
        continue;
      }

      echo '<p><strong>' . esc_html((string) ($section['label'] ?? '')) . '</strong> ';
      if (($section['section'] ?? '') === 'vendors') {
        echo 'Checked ' . (int) ($section['checked'] ?? 0) .
          ', Missing ' . (int) ($section['missing'] ?? 0) .
          ', Trashed ' . (int) ($section['trashed'] ?? 0) .
          ', Secondary missing ' . (int) ($section['secondary_missing'] ?? 0) .
          ', Secondary trashed ' . (int) ($section['secondary_trashed'] ?? 0) .
          ', Forced draft ' . (int) ($section['forced_draft'] ?? 0);
      } elseif (($section['section'] ?? '') === 'venues') {
        echo 'Checked ' . (int) ($section['checked'] ?? 0) .
          ', Missing ' . (int) ($section['missing'] ?? 0) .
          ', Trashed ' . (int) ($section['trashed'] ?? 0) .
          ', Unpublished ' . (int) ($section['unpublished'] ?? 0) .
          ', Cleared refs ' . (int) ($section['cleared_refs'] ?? 0) .
          ', Forced draft ' . (int) ($section['forced_draft'] ?? 0);
      } else {
        echo 'Checked ' . (int) ($section['checked'] ?? 0) .
          ', Unlinked ' . (int) ($section['unlinked'] ?? 0) .
          ', Missing ' . (int) ($section['missing'] ?? 0) .
          ', Trashed ' . (int) ($section['trashed'] ?? 0) .
          ', Unpublished ' . (int) ($section['unpublished'] ?? 0) .
          ', Cleared refs ' . (int) ($section['cleared_refs'] ?? 0) .
          ', Forced draft ' . (int) ($section['forced_draft'] ?? 0);
      }

      $action = is_array($section['action'] ?? null) ? $section['action'] : array();
      if (!empty($action['visible'])) {
        echo ' &nbsp;|&nbsp; <a';
        echo ' class="' . esc_attr((string) ($action['class'] ?? '')) . '"';
        echo ' href="' . esc_url((string) ($action['href'] ?? '')) . '"';
        if ((string) ($action['target'] ?? '') !== '') {
          echo ' target="' . esc_attr((string) $action['target']) . '"';
        }
        if ((string) ($action['rel'] ?? '') !== '') {
          echo ' rel="' . esc_attr((string) $action['rel']) . '"';
        }
        echo '>' . esc_html((string) ($action['label'] ?? '')) . '</a>';
      }

      echo '</p>';
    }
  } elseif (($context['layout'] ?? 'none') === 'single') {
    echo '<p><strong>Results:</strong> ' . esc_html((string) ($context['single_result_json'] ?? '')) . '</p>';
  }

  echo '</div>';
  echo '</div>';
}

function vms_field_sch_hide_past_default()
{
  $opts = (array) get_option('vms_settings', array());
  $val  = array_key_exists('sch_hide_past_default', $opts) ? (int) $opts['sch_hide_past_default'] : 1; // default ON

  echo '<label>';
  echo '<input type="checkbox" name="vms_settings[sch_hide_past_default]" value="1" ' . checked($val, 1, false) . ' /> ';
  echo esc_html__('Hide past dates by default in Schedule list view', 'backstage-venue-manager');
  echo '</label>';

  echo '<p class="description">';
  echo esc_html__('When enabled, past dates are hidden unless you add show_past=1 to the URL.', 'backstage-venue-manager');
  echo '</p>';
}
