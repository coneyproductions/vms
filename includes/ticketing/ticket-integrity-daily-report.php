<?php
defined('ABSPATH') || exit;

function bvmgr_ticket_integrity_daily_report_state_option_key(): string
{
	return 'vms_ticket_integrity_daily_report_state';
}

function bvmgr_ticket_integrity_daily_report_state_defaults(): array
{
	return array(
		'last_scheduled_run_at' => 0,
		'last_render_started_at' => 0,
		'last_render_finished_at' => 0,
		'last_send_attempt_at' => 0,
		'last_successful_send_at' => 0,
		'last_recipient' => '',
		'last_subject' => '',
		'last_mailer' => '',
		'last_result' => '',
		'last_error' => '',
		'last_trigger' => '',
		'last_mode' => '',
		'next_scheduled_run_at' => 0,
		'used_stale_snapshot' => 0,
		// Legacy compatibility keys kept in sync with the new state model.
		'last_attempted_gmt' => 0,
		'last_sent_gmt' => 0,
		'last_status' => '',
	);
}

function bvmgr_ticket_integrity_daily_report_status_from_result(string $result): string
{
	$result = sanitize_key($result);

	switch ($result) {
		case 'send_success':
		case 'sent':
			return 'sent';
		case 'send_failed':
		case 'skipped_no_snapshot':
		case 'skipped_scan_failed':
		case 'scan_refresh_failed':
		case 'disabled':
		case 'no_recipient':
		case 'empty_body':
			return 'failed';
		default:
			return '';
	}
}

function bvmgr_ticket_integrity_normalize_daily_report_state(array $state): array
{
	$normalized = array_merge(bvmgr_ticket_integrity_daily_report_state_defaults(), $state);

	if (empty($normalized['last_successful_send_at']) && !empty($normalized['last_sent_gmt'])) {
		$normalized['last_successful_send_at'] = absint($normalized['last_sent_gmt']);
	}
	if (empty($normalized['last_send_attempt_at']) && !empty($normalized['last_attempted_gmt'])) {
		$normalized['last_send_attempt_at'] = absint($normalized['last_attempted_gmt']);
	}
	if (!empty($normalized['last_successful_send_at']) && empty($normalized['last_sent_gmt'])) {
		$normalized['last_sent_gmt'] = absint($normalized['last_successful_send_at']);
	}
	if (!empty($normalized['last_send_attempt_at']) && empty($normalized['last_attempted_gmt'])) {
		$normalized['last_attempted_gmt'] = absint($normalized['last_send_attempt_at']);
	}
	if (($normalized['last_status'] ?? '') === '' && ($normalized['last_result'] ?? '') !== '') {
		$normalized['last_status'] = bvmgr_ticket_integrity_daily_report_status_from_result((string) ($normalized['last_result'] ?? ''));
	}
	if (($normalized['last_result'] ?? '') === '' && ($normalized['last_status'] ?? '') !== '') {
		$normalized['last_result'] = (($normalized['last_status'] ?? '') === 'sent') ? 'send_success' : 'send_failed';
	}

	foreach (array(
		'last_scheduled_run_at',
		'last_render_started_at',
		'last_render_finished_at',
		'last_send_attempt_at',
		'last_successful_send_at',
		'next_scheduled_run_at',
		'last_attempted_gmt',
		'last_sent_gmt',
	) as $timestamp_key) {
		$normalized[$timestamp_key] = absint($normalized[$timestamp_key] ?? 0);
	}

	$normalized['last_recipient'] = sanitize_email((string) ($normalized['last_recipient'] ?? ''));
	$normalized['last_subject'] = sanitize_text_field((string) ($normalized['last_subject'] ?? ''));
	$normalized['last_mailer'] = sanitize_text_field((string) ($normalized['last_mailer'] ?? ''));
	$normalized['last_result'] = sanitize_key((string) ($normalized['last_result'] ?? ''));
	$normalized['last_error'] = sanitize_text_field((string) ($normalized['last_error'] ?? ''));
	$normalized['last_trigger'] = sanitize_key((string) ($normalized['last_trigger'] ?? ''));
	$normalized['last_mode'] = sanitize_key((string) ($normalized['last_mode'] ?? ''));
	$normalized['last_status'] = sanitize_key((string) ($normalized['last_status'] ?? ''));
	$normalized['used_stale_snapshot'] = !empty($normalized['used_stale_snapshot']) ? 1 : 0;

	return $normalized;
}

function bvmgr_ticket_integrity_get_daily_report_state(): array
{
	$state = get_option(bvmgr_ticket_integrity_daily_report_state_option_key(), array());
	return bvmgr_ticket_integrity_normalize_daily_report_state(is_array($state) ? $state : array());
}

function bvmgr_ticket_integrity_update_daily_report_state(array $state): void
{
	$state = bvmgr_ticket_integrity_normalize_daily_report_state($state);
	update_option(bvmgr_ticket_integrity_daily_report_state_option_key(), $state, false);
}

function bvmgr_ticket_integrity_patch_daily_report_state(array $changes): array
{
	$state = array_merge(bvmgr_ticket_integrity_get_daily_report_state(), $changes);
	$state = bvmgr_ticket_integrity_normalize_daily_report_state($state);
	bvmgr_ticket_integrity_update_daily_report_state($state);

	return $state;
}

function bvmgr_ticket_integrity_daily_report_next_scheduled_run(): int
{
	if (!function_exists('wp_next_scheduled') || !function_exists('bvmgr_ticket_integrity_daily_report_hook')) {
		return 0;
	}

	return absint(wp_next_scheduled(bvmgr_ticket_integrity_daily_report_hook()));
}

function bvmgr_ticket_integrity_daily_report_mode(array $args = array()): string
{
	$mode = sanitize_key((string) ($args['mode'] ?? ''));
	if ($mode !== '') {
		return $mode;
	}

	return !empty($args['dry_run']) ? 'dry_run' : 'send';
}

function bvmgr_ticket_integrity_state_of_range_snapshot_completed_at(array $store): int
{
	$last_scan = is_array($store['last_scan'] ?? null) ? $store['last_scan'] : array();
	return absint($last_scan['completed_at_gmt'] ?? 0);
}

function bvmgr_ticket_integrity_state_of_range_has_usable_snapshot(array $store): bool
{
	if (bvmgr_ticket_integrity_state_of_range_snapshot_completed_at($store) <= 0) {
		return false;
	}

	if (array_key_exists('events', $store) && is_array($store['events'])) {
		return true;
	}

	return array_key_exists('summary', $store) && is_array($store['summary']);
}

function bvmgr_ticket_integrity_state_of_range_snapshot_status(array $store, int $reference_timestamp = 0): string
{
	if (!bvmgr_ticket_integrity_state_of_range_has_usable_snapshot($store)) {
		return 'missing';
	}

	$reference_timestamp = absint($reference_timestamp);
	if ($reference_timestamp <= 0) {
		$reference_timestamp = time();
	}

	return bvmgr_ticket_integrity_state_of_range_snapshot_completed_at($store) < ($reference_timestamp - (20 * HOUR_IN_SECONDS))
		? 'stale'
		: 'fresh';
}

function bvmgr_ticket_integrity_capture_mailer_details(callable $send_callback): array
{
	$meta = array(
		'sent' => false,
		'mailer' => 'wp_mail',
		'error' => '',
	);

	$phpmailer_capture = static function ($phpmailer) use (&$meta): void {
		if (!is_object($phpmailer)) {
			return;
		}

		$mailer = sanitize_key((string) ($phpmailer->Mailer ?? ''));
		$host = sanitize_text_field((string) ($phpmailer->Host ?? ''));
		if ($mailer === '') {
			$mailer = 'phpmailer';
		}
		$meta['mailer'] = ($host !== '') ? ($mailer . ':' . $host) : $mailer;
	};

	$mail_failed_capture = static function ($error) use (&$meta): void {
		if (is_object($error) && method_exists($error, 'get_error_message')) {
			$meta['error'] = sanitize_text_field((string) $error->get_error_message());
			return;
		}

		if (is_scalar($error)) {
			$meta['error'] = sanitize_text_field((string) $error);
		}
	};

	add_action('phpmailer_init', $phpmailer_capture, 9999, 1);
	add_action('wp_mail_failed', $mail_failed_capture, 9999, 1);

	try {
		$meta['sent'] = (bool) $send_callback();
	} finally {
		remove_action('phpmailer_init', $phpmailer_capture, 9999);
		remove_action('wp_mail_failed', $mail_failed_capture, 9999);
	}

	if (!$meta['sent'] && $meta['error'] === '') {
		$meta['error'] = 'wp_mail_false';
	}

	return $meta;
}

function bvmgr_ticket_integrity_daily_report_status_snapshot(): array
{
	$state = bvmgr_ticket_integrity_get_daily_report_state();
	$next_run = bvmgr_ticket_integrity_daily_report_next_scheduled_run();
	$expected_local_time = function_exists('bvmgr_ticket_integrity_next_daily_report_timestamp')
		? wp_date('H:i', bvmgr_ticket_integrity_next_daily_report_timestamp(), wp_timezone())
		: '06:05';
	$hook = function_exists('bvmgr_ticket_integrity_daily_report_hook') ? bvmgr_ticket_integrity_daily_report_hook() : 'vms_ticket_integrity_daily_report';
	$hook_count = 0;
	$times = array();

	if (function_exists('_get_cron_array')) {
		$cron = _get_cron_array();
		foreach ((array) $cron as $timestamp => $hooks) {
			if (empty($hooks[$hook]) || !is_array($hooks[$hook])) {
				continue;
			}

			$hook_count += count((array) $hooks[$hook]);
			$times[] = absint($timestamp);
		}
	}

	sort($times);

	return array(
		'hook' => $hook,
		'expected_local_time' => $expected_local_time,
		'next_scheduled_run_at' => $next_run,
		'next_scheduled_run_local' => $next_run > 0 ? bvmgr_ticket_integrity_format_datetime($next_run) : __('Never', 'backstage-venue-manager'),
		'scheduled_hook_count' => $hook_count,
		'scheduled_timestamps' => $times,
		'state' => $state,
	);
}

function bvmgr_ticket_integrity_daily_report_recipient(array $settings = array()): string
{
	if (empty($settings)) {
		$settings = function_exists('bvmgr_ticket_integrity_get_settings') ? bvmgr_ticket_integrity_get_settings() : array();
	}

	$recipient = sanitize_email((string) ($settings['daily_report_recipient'] ?? ''));
	if ($recipient === '') {
		$recipient = sanitize_email((string) ($settings['alert_recipient'] ?? ''));
	}
	if ($recipient === '') {
		$recipient = sanitize_email((string) get_option('admin_email', ''));
	}

	return $recipient;
}

function bvmgr_ticket_integrity_money_string(float $amount): string
{
	if (function_exists('wc_price')) {
		return bvmgr_ticket_integrity_plain_text((string) wc_price($amount));
	}

	return '$' . number_format($amount, 2, '.', ',');
}

function bvmgr_ticket_integrity_plain_text(string $value): string
{
	$charset = function_exists('get_bloginfo') ? (string) get_bloginfo('charset') : 'UTF-8';
	if ($charset === '') {
		$charset = 'UTF-8';
	}

	$value = wp_strip_all_tags($value);
	return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, $charset);
}

function bvmgr_ticket_integrity_local_day_start(int $reference_timestamp = 0): DateTimeImmutable
{
	$tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
	$reference_timestamp = absint($reference_timestamp);
	if ($reference_timestamp <= 0) {
		$reference_timestamp = time();
	}

	return (new DateTimeImmutable('@' . $reference_timestamp))->setTimezone($tz)->setTime(0, 0, 0);
}

function bvmgr_ticket_integrity_days_to_event(int $timestamp, int $reference_timestamp = 0): ?int
{
	$timestamp = absint($timestamp);
	if ($timestamp <= 0) {
		return null;
	}

	$today = bvmgr_ticket_integrity_local_day_start($reference_timestamp);
	$event = bvmgr_ticket_integrity_local_day_start($timestamp);
	return (int) $today->diff($event)->format('%r%a');
}

function bvmgr_ticket_integrity_state_of_range_generated_at(array $store): int
{
	$report_meta = is_array($store['report_meta'] ?? null) ? $store['report_meta'] : array();
	$generated_at = absint($report_meta['generated_at_gmt'] ?? 0);

	return $generated_at > 0 ? $generated_at : time();
}

function bvmgr_ticket_integrity_state_of_range_event_is_upcoming(array $event, int $reference_timestamp = 0): bool
{
	$event_timestamp = absint($event['event_timestamp'] ?? 0);
	if ($event_timestamp <= 0) {
		return true;
	}

	return bvmgr_ticket_integrity_local_day_start($event_timestamp) >= bvmgr_ticket_integrity_local_day_start($reference_timestamp);
}

function bvmgr_ticket_integrity_filter_state_of_range_events(array $events, int $reference_timestamp = 0): array
{
	$filtered = array();
	foreach ($events as $event) {
		if (!is_array($event) || !bvmgr_ticket_integrity_state_of_range_event_is_upcoming($event, $reference_timestamp)) {
			continue;
		}

		$filtered[] = $event;
	}

	return $filtered;
}

function bvmgr_ticket_integrity_state_of_range_summary(array $events): array
{
	$summary = array(
		'events_scanned' => 0,
		'green' => 0,
		'yellow' => 0,
		'red' => 0,
		'informational' => 0,
	);

	foreach ($events as $event) {
		if (!is_array($event)) {
			continue;
		}

		$summary['events_scanned']++;
		$status = sanitize_key((string) ($event['status'] ?? 'green'));
		if (isset($summary[$status])) {
			$summary[$status]++;
		}
	}

	return $summary;
}

function bvmgr_ticket_integrity_ticket_remaining(array $ticket_snapshot): ?int
{
	$product = is_array($ticket_snapshot['product'] ?? null) ? $ticket_snapshot['product'] : array();
	if (($product['post_type'] ?? '') !== 'product') {
		return null;
	}

	if (!empty($product['managing_stock']) && is_numeric($product['stock_quantity'] ?? null)) {
		return max(0, (int) $product['stock_quantity']);
	}

	$total = max(0, (int) ($ticket_snapshot['inventory_total'] ?? 0));
	if ($total > 0) {
		$sold = max(0, (int) ($product['total_sales'] ?? 0));
		return max(0, $total - $sold);
	}

	return null;
}

function bvmgr_ticket_integrity_ticket_report_remaining(array $ticket_snapshot): ?int
{
	$product = is_array($ticket_snapshot['product'] ?? null) ? $ticket_snapshot['product'] : array();
	if (($product['post_type'] ?? '') !== 'product') {
		return null;
	}

	$total = max(0, (int) ($ticket_snapshot['inventory_total'] ?? 0));
	$sold = max(0, (int) ($product['total_sales'] ?? 0));
	if ($total > 0) {
		return max(0, $total - $sold);
	}

	if (!empty($product['managing_stock']) && is_numeric($product['stock_quantity'] ?? null)) {
		return max(0, (int) $product['stock_quantity']);
	}

	return null;
}

function bvmgr_ticket_integrity_report_statuses(): array
{
	$statuses = apply_filters('vms_ticket_integrity_daily_report_statuses', array('wc-completed'));
	$out = array();
	foreach ((array) $statuses as $status) {
		$status = sanitize_key((string) $status);
		if ($status === '') {
			continue;
		}
		if (strpos($status, 'wc-') !== 0) {
			$status = 'wc-' . $status;
		}
		$out[] = $status;
	}

	$out = array_values(array_unique($out));
	return !empty($out) ? $out : array('wc-completed');
}

function bvmgr_ticket_integrity_report_table_exists(string $table_name): bool
{
	$table_name = trim($table_name);
	if ($table_name === '') {
		return false;
	}

	if (function_exists('bvmgr_ticketing_v2_table_exists')) {
		return bvmgr_ticketing_v2_table_exists($table_name);
	}

	global $wpdb;
	if (!isset($wpdb) || !is_object($wpdb)) {
		return false;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema readiness performs a prepared exact-name probe for each of two WooCommerce lookup tables; the result must reflect current schema availability.
	return ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name);
}

function bvmgr_ticket_integrity_report_lookup_metrics(array $product_ids, array $statuses = array()): array
{
	$product_ids = array_values(array_filter(array_map('absint', $product_ids)));
	$statuses = !empty($statuses) ? $statuses : bvmgr_ticket_integrity_report_statuses();

	$empty = array(
		'provider' => 'none',
		'statuses' => $statuses,
		'qty' => 0,
		'net_revenue' => 0.0,
		'gross_revenue' => 0.0,
		'by_product' => array(),
	);

	if (empty($product_ids) || !function_exists('bvmgr_ticketing_is_woo_active') || !bvmgr_ticketing_is_woo_active()) {
		return $empty;
	}

	global $wpdb;
	if (!isset($wpdb) || !is_object($wpdb)) {
		return $empty;
	}

	$lookup_table = $wpdb->prefix . 'wc_order_product_lookup';
	$stats_table = $wpdb->prefix . 'wc_order_stats';
	if (
		!bvmgr_ticket_integrity_report_table_exists($lookup_table)
		|| !bvmgr_ticket_integrity_report_table_exists($stats_table)
	) {
		$fallback = $empty;
		$fallback['provider'] = 'woo_product_totals';
		foreach ($product_ids as $product_id) {
			$product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
			if (!$product) {
				continue;
			}

			$qty = max(0, (int) $product->get_total_sales());
			$net = max(0.0, $qty * (float) $product->get_price());
			$fallback['qty'] += $qty;
			$fallback['net_revenue'] += $net;
			$fallback['gross_revenue'] += $net;
			$fallback['by_product'][$product_id] = array(
				'qty' => $qty,
				'net_revenue' => $net,
				'gross_revenue' => $net,
			);
		}

		return $fallback;
	}

	$pid_placeholders = implode(', ', array_fill(0, count($product_ids), '%d'));
	$status_placeholders = implode(', ', array_fill(0, count($statuses), '%s'));
	$sql = "
		SELECT
			product_lookup.product_id AS product_id,
			COALESCE(SUM(product_lookup.product_qty), 0) AS qty,
			COALESCE(SUM(product_lookup.product_net_revenue), 0) AS net_revenue,
			COALESCE(SUM(product_lookup.product_gross_revenue), 0) AS gross_revenue
		FROM %i product_lookup
		INNER JOIN %i order_stats
			ON order_stats.order_id = product_lookup.order_id
		WHERE product_lookup.product_id IN ({$pid_placeholders})
		  AND order_stats.status IN ({$status_placeholders})
		GROUP BY product_lookup.product_id
	";

	$args = array_merge(array($lookup_table, $stats_table), $product_ids, $statuses);
	// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The daily report prepares both table identifiers and every product/status value before one request-fresh aggregate; no WooCommerce API exposes this grouped metric contract.
	$rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);
	$result = $empty;
	$result['provider'] = 'woo_lookup_completed';
	foreach ((array) $rows as $row) {
		$product_id = absint($row['product_id'] ?? 0);
		if ($product_id <= 0) {
			continue;
		}

		$qty = max(0, (int) round((float) ($row['qty'] ?? 0)));
		$net = max(0.0, (float) ($row['net_revenue'] ?? 0.0));
		$gross = max(0.0, (float) ($row['gross_revenue'] ?? 0.0));

		$result['qty'] += $qty;
		$result['net_revenue'] += $net;
		$result['gross_revenue'] += $gross;
		$result['by_product'][$product_id] = array(
			'qty' => $qty,
			'net_revenue' => $net,
			'gross_revenue' => $gross,
		);
	}

	return $result;
}

function bvmgr_ticket_integrity_report_ticket_is_included(array $ticket_snapshot): bool
{
	$product = is_array($ticket_snapshot['product'] ?? null) ? $ticket_snapshot['product'] : array();
	if (($ticket_snapshot['mapping_state'] ?? '') !== 'ok') {
		return false;
	}

	return (($product['post_type'] ?? '') === 'product');
}


function bvmgr_ticket_integrity_should_monitor_low_inventory(array $ticket_snapshot): bool
{
	if (empty($ticket_snapshot['customer_facing'])) {
		return false;
	}

	$policy = '';
	foreach (array('low_inventory_alert_policy', 'inventory_alert_policy', 'alert_policy') as $policy_key) {
		if (isset($ticket_snapshot[$policy_key])) {
			$policy = sanitize_key((string) $ticket_snapshot[$policy_key]);
			break;
		}
	}

	if ($policy === 'never' || $policy === 'off' || $policy === 'disabled') {
		return false;
	}
	if ($policy === 'always' || $policy === 'on' || $policy === 'enabled') {
		return true;
	}

	$price = isset($ticket_snapshot['config_price']) && is_numeric($ticket_snapshot['config_price'])
		? (float) $ticket_snapshot['config_price']
		: null;
	if ($price === null) {
		$product = is_array($ticket_snapshot['product'] ?? null) ? $ticket_snapshot['product'] : array();
		$price = isset($product['price']) && is_numeric($product['price']) ? (float) $product['price'] : 0.0;
	}

	/**
	 * Default policy: free/comp/qualified informational ticket rows should not create
	 * low-inventory alarms unless a future ticket-level policy explicitly opts them in.
	 */
	$should_monitor = $price > 0;
	return (bool) apply_filters('vms_ticket_integrity_should_monitor_low_inventory', $should_monitor, $ticket_snapshot, $policy);
}

function bvmgr_ticket_integrity_low_inventory_signal(array $ticket_snapshot, int $event_timestamp = 0): array
{
	$signal = array(
		'flagged' => false,
		'severity' => '',
		'remaining' => 0,
		'total' => 0,
		'percent_remaining' => 0.0,
	);

	if (!bvmgr_ticket_integrity_should_monitor_low_inventory($ticket_snapshot)) {
		return $signal;
	}
	if ((string) ($ticket_snapshot['mapping_state'] ?? '') !== 'ok') {
		return $signal;
	}
	if ($event_timestamp > 0 && $event_timestamp <= time()) {
		return $signal;
	}

	$product = is_array($ticket_snapshot['product'] ?? null) ? $ticket_snapshot['product'] : array();
	if (($product['post_type'] ?? '') !== 'product') {
		return $signal;
	}
	if (($product['is_in_stock'] ?? null) === false || (string) ($product['stock_status'] ?? '') === 'outofstock') {
		return $signal;
	}

	$remaining = bvmgr_ticket_integrity_ticket_remaining($ticket_snapshot);
	if ($remaining === null || $remaining <= 0) {
		return $signal;
	}

	$total = max(0, (int) ($ticket_snapshot['inventory_total'] ?? 0));
	if ($total <= 0) {
		$total = $remaining + max(0, (int) ($product['total_sales'] ?? 0));
	}
	if ($total <= 0) {
		return $signal;
	}

	$settings = function_exists('bvmgr_ticket_integrity_get_settings') ? bvmgr_ticket_integrity_get_settings() : array();
	$low_count = max(1, absint($settings['low_inventory_threshold'] ?? 25));
	$low_percent = max(1, absint($settings['low_inventory_percent_threshold'] ?? 10));
	$critical_count = max(1, absint($settings['critical_inventory_threshold'] ?? 5));
	$critical_percent = max(1, absint($settings['critical_inventory_percent_threshold'] ?? 3));
	$percent_remaining = ($total > 0) ? (($remaining / max(1, $total)) * 100) : 0.0;

	$signal['remaining'] = $remaining;
	$signal['total'] = $total;
	$signal['percent_remaining'] = $percent_remaining;

	if ($remaining <= $critical_count || $percent_remaining <= $critical_percent) {
		$signal['flagged'] = true;
		$signal['severity'] = 'red';
		return $signal;
	}

	if ($remaining <= $low_count || $percent_remaining <= $low_percent) {
		$signal['flagged'] = true;
		$signal['severity'] = 'yellow';
	}

	return $signal;
}

function bvmgr_ticket_integrity_build_state_of_range_event_row(array $event, int $reference_timestamp = 0): array
{
	$ticket_snapshots = is_array($event['ticket_snapshots'] ?? null) ? $event['ticket_snapshots'] : array();
	$report_tickets = array_values(array_filter($ticket_snapshots, static function (array $ticket): bool {
		return bvmgr_ticket_integrity_report_ticket_is_included($ticket);
	}));
	$customer_facing = array_values(array_filter($report_tickets, static function (array $ticket): bool {
		return !empty($ticket['customer_facing']);
	}));

	$product_ids = array();
	$total_capacity = 0;
	$tickets_left = 0;
	$known_left = false;
	$paid_sold = 0;
	$free_sold = 0;
	$gross_sales = 0.0;
	$low_inventory = array();

	foreach ($report_tickets as $ticket) {
		$product = is_array($ticket['product'] ?? null) ? $ticket['product'] : array();
		$pid = absint($ticket['mapped_product_id'] ?? 0);
		if ($pid > 0) {
			$product_ids[] = $pid;
		}

		$total_capacity += max(0, (int) ($ticket['inventory_total'] ?? 0));
		$remaining = bvmgr_ticket_integrity_ticket_report_remaining($ticket);
		if ($remaining !== null) {
			$tickets_left += $remaining;
			$known_left = true;
		}
	}

	$product_ids = array_values(array_unique(array_filter(array_map('absint', $product_ids))));
	$stats = bvmgr_ticket_integrity_report_lookup_metrics($product_ids);
	foreach ($report_tickets as $ticket) {
		$pid = absint($ticket['mapped_product_id'] ?? 0);
		if ($pid > 0) {
			$product = is_array($ticket['product'] ?? null) ? $ticket['product'] : array();
			$product_stats = is_array($stats['by_product'][$pid] ?? null) ? $stats['by_product'][$pid] : array();
			$sold = max(0, (int) ($product_stats['qty'] ?? 0));
			$gross_sales += max(0.0, (float) ($product_stats['net_revenue'] ?? 0.0));
			if ((float) ($ticket['config_price'] ?? 0) > 0 || (float) ($product['price'] ?? 0) > 0) {
				$paid_sold += $sold;
			} else {
				$free_sold += $sold;
			}
		}

		$inventory_signal = bvmgr_ticket_integrity_low_inventory_signal($ticket, absint($event['event_timestamp'] ?? 0));
		if (!empty($inventory_signal['flagged'])) {
			$inventory_signal['ticket_title'] = bvmgr_ticket_integrity_plain_text((string) ($ticket['title'] ?? $ticket['ticket_key'] ?? 'Ticket'));
			$low_inventory[] = $inventory_signal;
		}
	}

	$tickets_sold = max(0, $paid_sold + $free_sold);
	$open_issues = function_exists('bvmgr_ticket_integrity_open_issues') ? bvmgr_ticket_integrity_open_issues((array) ($event['issues'] ?? array())) : (array) ($event['issues'] ?? array());
	$red_issues = 0;
	$yellow_issues = 0;
	foreach ($open_issues as $issue) {
		$severity = sanitize_key((string) ($issue['severity'] ?? ''));
		if ($severity === 'red') {
			$red_issues++;
		} elseif ($severity === 'yellow') {
			$yellow_issues++;
		}
	}

	return array(
		'event_title' => bvmgr_ticket_integrity_plain_text((string) ($event['event_title'] ?? __('Untitled event', 'backstage-venue-manager'))),
		'event_date_local' => (string) ($event['event_date_local'] ?? ''),
		'event_timestamp' => absint($event['event_timestamp'] ?? 0),
		'days_to_event' => bvmgr_ticket_integrity_days_to_event(absint($event['event_timestamp'] ?? 0), $reference_timestamp),
		'status' => sanitize_key((string) ($event['status'] ?? 'green')),
		'tickets_sold' => $tickets_sold,
		'paid_tickets_sold' => $paid_sold,
		'free_tickets_sold' => $free_sold,
		'gross_sales' => $gross_sales,
		'tickets_left' => $known_left ? $tickets_left : null,
		'total_capacity' => $total_capacity,
		'issue_summary' => bvmgr_ticket_integrity_plain_text((string) ($event['issue_summary'] ?? '')),
		'red_issues' => $red_issues,
		'yellow_issues' => $yellow_issues,
		'low_inventory' => $low_inventory,
	);
}

function bvmgr_ticket_integrity_prepare_state_of_range_payload(string $trigger = 'daily_report', int $generated_at_gmt = 0, array $args = array()): array
{
	$store = function_exists('bvmgr_ticket_integrity_get_results_store') ? bvmgr_ticket_integrity_get_results_store() : array();
	$allow_refresh = !array_key_exists('allow_refresh', $args) || !empty($args['allow_refresh']);
	$snapshot_status = bvmgr_ticket_integrity_state_of_range_snapshot_status($store, time());
	$needs_refresh = ($snapshot_status !== 'fresh');
	$payload = array(
		'store' => $store,
		'needs_refresh' => $needs_refresh,
		'refresh_attempted' => false,
		'refresh_ok' => true,
		'refresh_message' => ($snapshot_status === 'fresh') ? 'not_needed' : (($snapshot_status === 'stale') ? 'stale_snapshot' : 'no_usable_snapshot'),
		'used_stale_snapshot' => ($snapshot_status === 'stale'),
		'snapshot_status' => $snapshot_status,
	);

	if ($needs_refresh && $allow_refresh && function_exists('bvmgr_ticket_integrity_scan_all')) {
		$payload['refresh_attempted'] = true;
		$result = bvmgr_ticket_integrity_scan_all(array('trigger' => sanitize_key($trigger)));
		if (!empty($result['ok']) && !empty($result['store']) && is_array($result['store'])) {
			$store = $result['store'];
			$payload['snapshot_status'] = bvmgr_ticket_integrity_state_of_range_snapshot_status($store, time());
			$payload['used_stale_snapshot'] = (($payload['snapshot_status'] ?? '') === 'stale');
			$payload['refresh_message'] = 'ok';
		} else {
			$payload['refresh_ok'] = false;
			$payload['refresh_message'] = sanitize_key((string) ($result['message'] ?? 'scan_failed'));
			$payload['snapshot_status'] = bvmgr_ticket_integrity_state_of_range_snapshot_status($store, time());
			if (($payload['snapshot_status'] ?? '') !== 'missing') {
				$payload['used_stale_snapshot'] = true;
			} else {
				$store = array();
			}
		}
	}

	$report_meta = is_array($store['report_meta'] ?? null) ? $store['report_meta'] : array();
	$report_meta['generated_at_gmt'] = ($generated_at_gmt > 0) ? absint($generated_at_gmt) : time();
	$report_meta['refresh_failed'] = !empty($payload['refresh_attempted']) && empty($payload['refresh_ok']) ? 1 : 0;
	$report_meta['used_stale_snapshot'] = !empty($payload['used_stale_snapshot']) ? 1 : 0;
	$report_meta['refresh_message'] = (string) $payload['refresh_message'];
	$report_meta['snapshot_status'] = sanitize_key((string) ($payload['snapshot_status'] ?? 'missing'));
	$store['report_meta'] = $report_meta;

	if (function_exists('bvmgr_ticket_integrity_prepare_payment_gateway_health')) {
		$store['payment_gateway_health'] = bvmgr_ticket_integrity_prepare_payment_gateway_health($trigger, 30 * MINUTE_IN_SECONDS);
	}

	$payload['store'] = is_array($store) ? $store : array();
	return $payload;
}

function bvmgr_ticket_integrity_prepare_state_of_range_store(string $trigger = 'daily_report', int $generated_at_gmt = 0, array $args = array()): array
{
	$payload = bvmgr_ticket_integrity_prepare_state_of_range_payload($trigger, $generated_at_gmt, $args);
	$store = is_array($payload['store'] ?? null) ? $payload['store'] : array();
	return is_array($store) ? $store : array();
}

function bvmgr_ticket_integrity_build_state_of_range_payment_gateway_lines(array $health): array
{
	$lines = array();
	$status = function_exists('bvmgr_ticket_integrity_payment_gateway_status_label')
		? bvmgr_ticket_integrity_payment_gateway_status_label((string) ($health['status'] ?? 'unknown'))
		: strtoupper((string) ($health['status'] ?? 'unknown'));
	$checkout = is_array($health['checkout'] ?? null) ? $health['checkout'] : array();
	$square = is_array($health['square'] ?? null) ? $health['square'] : array();
	$apple_pay = is_array($health['apple_pay'] ?? null) ? $health['apple_pay'] : array();

	$lines[] = __('Payment Gateway Health', 'backstage-venue-manager');
	/* translators: %s: status. */
	$lines[] = '- ' . sprintf(__('Status: %s', 'backstage-venue-manager'), $status);
	/* translators: %s: last checked. */
	$lines[] = '- ' . sprintf(__('Last checked: %s', 'backstage-venue-manager'), bvmgr_ticket_integrity_format_datetime(absint($health['last_checked_gmt'] ?? 0)));
	/* translators: %d: checkout methods available. */
	$lines[] = '- ' . sprintf(__('Checkout methods available: %d', 'backstage-venue-manager'), absint($checkout['available_count'] ?? 0));

	$available_titles = (array) ($checkout['available_gateway_titles'] ?? array());
	if (!empty($available_titles)) {
		/* translators: %s: available methods. */
		$lines[] = '- ' . sprintf(__('Available methods: %s', 'backstage-venue-manager'), implode(', ', $available_titles));
	} else {
		$lines[] = '- ' . __('Available methods: none', 'backstage-venue-manager');
	}

	if (!empty($square['plugin_active']) || !empty($square['expected'])) {
		$lines[] = '- ' . sprintf(
			/* translators: 1: value 1 used in this message, 2: value 2 used in this message, 3: value 3 used in this message. */
			__('Square: %1$s | Gateway %2$s | %3$s', 'backstage-venue-manager'),
			!empty($square['connection_present']) ? __('Connected', 'backstage-venue-manager') : __('Disconnected', 'backstage-venue-manager'),
			!empty($square['gateway_enabled']) ? __('enabled', 'backstage-venue-manager') : __('disabled', 'backstage-venue-manager'),
			(string) ($square['environment_label'] ?? __('Unknown', 'backstage-venue-manager'))
		);
	}

	if (!empty($apple_pay['failed'])) {
		$lines[] = '- ' . __('Apple Pay: domain registration failed', 'backstage-venue-manager');
	} elseif (!empty($apple_pay['enabled']) && !empty($apple_pay['domain_registered'])) {
		$lines[] = '- ' . __('Apple Pay: domain registered', 'backstage-venue-manager');
	}

	$summary = trim((string) ($health['summary'] ?? ''));
	if ($summary !== '') {
		/* translators: %s: summary. */
		$lines[] = '- ' . sprintf(__('Summary: %s', 'backstage-venue-manager'), $summary);
	}

	$incident = is_array($health['incident'] ?? null) ? $health['incident'] : array();
	if (!empty($incident['active'])) {
		$lines[] = '- ' . sprintf(
			/* translators: %s: current incident since. */
			__('Current incident since: %s', 'backstage-venue-manager'),
			bvmgr_ticket_integrity_format_datetime(absint($incident['first_detected_failure_gmt'] ?? 0))
		);
	}

	$last_incident = is_array($health['last_incident'] ?? null) ? $health['last_incident'] : array();
	if (empty($incident['active']) && !empty($last_incident['resolved_at_gmt'])) {
		$lines[] = '- ' . sprintf(
			/* translators: %s: most recent incident resolved. */
			__('Most recent incident resolved: %s', 'backstage-venue-manager'),
			bvmgr_ticket_integrity_format_datetime(absint($last_incident['resolved_at_gmt'] ?? 0))
		);
	}

	foreach ((array) ($health['failed_checks'] ?? array()) as $check) {
		if (!is_array($check)) {
			continue;
		}
		$lines[] = sprintf(
			'  - [%1$s] %2$s',
			function_exists('bvmgr_ticket_integrity_payment_gateway_status_label')
				? bvmgr_ticket_integrity_payment_gateway_status_label((string) ($check['status'] ?? 'warning'))
				: strtoupper((string) ($check['status'] ?? 'warning')),
			(string) ($check['message'] ?? '')
		);
	}

	$lines[] = '';
	return $lines;
}

function bvmgr_ticket_integrity_build_state_of_range_email(array $store): array
{
	$events = function_exists('bvmgr_ticket_integrity_sort_events')
		? bvmgr_ticket_integrity_sort_events(array_values((array) ($store['events'] ?? array())))
		: array_values((array) ($store['events'] ?? array()));
	$site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
	$subject = sprintf('[%s] %s', $site_name, __('State of the Range', 'backstage-venue-manager'));
	$last_scan = is_array($store['last_scan'] ?? null) ? $store['last_scan'] : array();
	$report_generated_at = bvmgr_ticket_integrity_state_of_range_generated_at($store);
	$events = bvmgr_ticket_integrity_filter_state_of_range_events($events, $report_generated_at);
	$summary = bvmgr_ticket_integrity_state_of_range_summary($events);

	$lines = array();
	$lines[] = __('State of the Range', 'backstage-venue-manager');
	$lines[] = str_repeat('=', 18);
	/* translators: %s: generated. */
	$lines[] = sprintf(__('Generated: %s', 'backstage-venue-manager'), function_exists('bvmgr_ticket_integrity_format_datetime') ? bvmgr_ticket_integrity_format_datetime($report_generated_at) : wp_date('Y-m-d g:i a', $report_generated_at));
	/* translators: %s: last integrity scan. */
	$lines[] = sprintf(__('Last integrity scan: %s', 'backstage-venue-manager'), function_exists('bvmgr_ticket_integrity_format_datetime') ? bvmgr_ticket_integrity_format_datetime(absint($last_scan['completed_at_gmt'] ?? 0)) : wp_date('Y-m-d g:i a'));
	$report_meta = is_array($store['report_meta'] ?? null) ? $store['report_meta'] : array();
	if (!empty($report_meta['used_stale_snapshot'])) {
		$lines[] = sprintf(
			!empty($report_meta['refresh_failed'])
				/* translators: %s: formatted date/time of the last successful integrity snapshot. */
				? __('Warning: today\'s integrity refresh failed, so this email is using the last successful snapshot from %s.', 'backstage-venue-manager')
				/* translators: %s: formatted date/time of the last successful integrity snapshot. */
				: __('Warning: this email is using the last available integrity snapshot from %s. Run a fresh integrity scan to update the data.', 'backstage-venue-manager'),
			function_exists('bvmgr_ticket_integrity_format_datetime') ? bvmgr_ticket_integrity_format_datetime(absint($last_scan['completed_at_gmt'] ?? 0)) : wp_date('Y-m-d g:i a', absint($last_scan['completed_at_gmt'] ?? 0))
		);
	} elseif (!empty($report_meta['refresh_failed'])) {
		$lines[] = __('Warning: today\'s integrity refresh failed and no usable snapshot was available for the report.', 'backstage-venue-manager');
	}
	$lines[] = '';

	$payment_gateway_health = is_array($store['payment_gateway_health'] ?? null) ? $store['payment_gateway_health'] : array();
	if (!empty($payment_gateway_health)) {
		$lines = array_merge($lines, bvmgr_ticket_integrity_build_state_of_range_payment_gateway_lines($payment_gateway_health));
	}

	$rows = array();
	$total_sold = 0;
	$total_gross = 0.0;
	$attention_count = 0;
	$low_inventory_count = 0;
	$shows_this_week = 0;
	$urgent_lines = array();

	foreach ($events as $event) {
		if (!is_array($event)) {
			continue;
		}
		$row = bvmgr_ticket_integrity_build_state_of_range_event_row($event, $report_generated_at);
		$rows[] = $row;
		$total_sold += (int) ($row['tickets_sold'] ?? 0);
		$total_gross += (float) ($row['gross_sales'] ?? 0.0);
		if (in_array((string) ($row['status'] ?? ''), array('red', 'yellow'), true)) {
			$attention_count++;
		}
		if (!empty($row['low_inventory'])) {
			$low_inventory_count++;
		}
		$days = $row['days_to_event'];
		if ($days !== null && $days >= 0 && $days <= 7) {
			$shows_this_week++;
		}

		if (($row['status'] ?? '') === 'red' || !empty($row['low_inventory'])) {
			$urgent = '- ' . (string) ($row['event_title'] ?? __('Untitled event', 'backstage-venue-manager'));
			if (!empty($row['low_inventory'])) {
				$low_names = array();
				foreach ((array) $row['low_inventory'] as $signal) {
					$low_names[] = (string) ($signal['ticket_title'] ?? __('Ticket', 'backstage-venue-manager'));
				}
				/* translators: %s: human-readable value used in this message. */
				$urgent .= ': ' . sprintf(__('Low inventory on %s', 'backstage-venue-manager'), implode(', ', array_slice(array_unique($low_names), 0, 3)));
			} else {
				$urgent .= ': ' . (string) ($row['issue_summary'] ?? __('Needs review', 'backstage-venue-manager'));
			}
			$urgent_lines[] = $urgent;
		}
	}

	$lines[] = __('Morning snapshot', 'backstage-venue-manager');
	/* translators: %d: events scanned. */
	$lines[] = '- ' . sprintf(__('Events scanned: %d', 'backstage-venue-manager'), absint($summary['events_scanned'] ?? count($rows)));
	/* translators: %d: shows this week. */
	$lines[] = '- ' . sprintf(__('Shows this week: %d', 'backstage-venue-manager'), $shows_this_week);
	/* translators: %d: tickets sold (tracked upcoming events). */
	$lines[] = '- ' . sprintf(__('Tickets sold (tracked upcoming events): %d', 'backstage-venue-manager'), $total_sold);
	/* translators: %s: gross sales (tracked upcoming events). */
	$lines[] = '- ' . sprintf(__('Gross sales (tracked upcoming events): %s', 'backstage-venue-manager'), bvmgr_ticket_integrity_money_string($total_gross));
	/* translators: %d: events needing attention. */
	$lines[] = '- ' . sprintf(__('Events needing attention: %d', 'backstage-venue-manager'), $attention_count);
	/* translators: %d: low inventory warnings. */
	$lines[] = '- ' . sprintf(__('Low inventory warnings: %d', 'backstage-venue-manager'), $low_inventory_count);
	/* translators: 1: number 1 used in this message, 2: number 2 used in this message, 3: number 3 used in this message. */
	$lines[] = '- ' . sprintf(__('Integrity summary — Red: %1$d, Yellow: %2$d, Green: %3$d', 'backstage-venue-manager'), absint($summary['red'] ?? 0), absint($summary['yellow'] ?? 0), absint($summary['green'] ?? 0));
	$lines[] = '';

	if (!empty($urgent_lines)) {
		$lines[] = __('Urgent / notable items', 'backstage-venue-manager');
		foreach ($urgent_lines as $line) {
			$lines[] = $line;
		}
		$lines[] = '';
	}

	$lines[] = __('Upcoming events', 'backstage-venue-manager');
	foreach ($rows as $row) {
		$days = $row['days_to_event'];
		$days_text = ($days === null) ? __('n/a', 'backstage-venue-manager') : (string) $days;
		$tickets_left = ($row['tickets_left'] === null) ? __('n/a', 'backstage-venue-manager') : (string) $row['tickets_left'];
		$lines[] = sprintf(
			'- %1$s — %2$s',
			(string) ($row['event_title'] ?? __('Untitled event', 'backstage-venue-manager')),
			(string) ($row['event_date_local'] ?? __('No event date', 'backstage-venue-manager'))
		);
			$lines[] = sprintf(
				'  %1$s | %2$s | %3$s | %4$s | %5$s',
				/* translators: %s: days. */
				sprintf(__('Days: %s', 'backstage-venue-manager'), $days_text),
				/* translators: %d: sold. */
				sprintf(__('Sold: %d', 'backstage-venue-manager'), absint($row['tickets_sold'] ?? 0)),
				/* translators: %s: gross. */
				sprintf(__('Gross: %s', 'backstage-venue-manager'), bvmgr_ticket_integrity_money_string((float) ($row['gross_sales'] ?? 0))),
				/* translators: %s: available inventory. */
				sprintf(__('Available inventory: %s', 'backstage-venue-manager'), $tickets_left),
				/* translators: %s: status. */
				sprintf(__('Status: %s', 'backstage-venue-manager'), strtoupper((string) ($row['status'] ?? 'green')))
			);
			$lines[] = sprintf(
				'  %1$s | %2$s | %3$s',
				/* translators: %d: ticket capacity. */
				sprintf(__('Ticket capacity: %d', 'backstage-venue-manager'), absint($row['total_capacity'] ?? 0)),
				/* translators: %d: paid sold. */
				sprintf(__('Paid sold: %d', 'backstage-venue-manager'), absint($row['paid_tickets_sold'] ?? 0)),
				/* translators: %d: free/qualified sold. */
				sprintf(__('Free/qualified sold: %d', 'backstage-venue-manager'), absint($row['free_tickets_sold'] ?? 0))
			);
		if (!empty($row['low_inventory'])) {
			foreach ((array) $row['low_inventory'] as $signal) {
				$lines[] = sprintf(
					'  %1$s',
					sprintf(
						/* translators: 1: value 1 used in this message, 2: number 2 used in this message, 3: value 3 used in this message. */
						__('Low inventory: %1$s has %2$d left (%3$s%%).', 'backstage-venue-manager'),
						(string) ($signal['ticket_title'] ?? __('Ticket', 'backstage-venue-manager')),
						absint($signal['remaining'] ?? 0),
						number_format((float) ($signal['percent_remaining'] ?? 0), 1)
					)
				);
			}
		}
		if (!empty($row['issue_summary']) && ($row['status'] ?? '') !== 'green') {
			/* translators: %s: issues. */
			$lines[] = '  ' . sprintf(__('Issues: %s', 'backstage-venue-manager'), (string) $row['issue_summary']);
		}
		$lines[] = '';
	}

	/* translators: %s: review the full monitor. */
	$lines[] = sprintf(__('Review the full monitor: %s', 'backstage-venue-manager'), function_exists('bvmgr_ticket_integrity_admin_url') ? bvmgr_ticket_integrity_admin_url() : admin_url());

	return array(
		'subject' => bvmgr_ticket_integrity_plain_text($subject),
		'body' => bvmgr_ticket_integrity_plain_text(implode("
", $lines)),
		'rows' => $rows,
	);
}

function bvmgr_ticket_integrity_send_state_of_range_report(string $trigger = 'manual', array $args = array()): array
{
	$settings = function_exists('bvmgr_ticket_integrity_get_settings') ? bvmgr_ticket_integrity_get_settings() : array();
	$dry_run = !empty($args['dry_run']);
	$mode = bvmgr_ticket_integrity_daily_report_mode($args);
	$generated_at_gmt = absint($args['generated_at_gmt'] ?? 0);
	if ($trigger === 'cron' && empty($settings['daily_report_enabled'])) {
		bvmgr_ticket_integrity_patch_daily_report_state(
			array(
				'last_trigger' => sanitize_key($trigger),
				'last_mode' => $mode,
				'last_result' => 'disabled',
				'next_scheduled_run_at' => bvmgr_ticket_integrity_daily_report_next_scheduled_run(),
			)
		);
		return array('ok' => false, 'message' => 'daily_report_disabled');
	}

	$recipient = sanitize_email((string) ($args['recipient'] ?? ''));
	if ($recipient === '') {
		$recipient = bvmgr_ticket_integrity_daily_report_recipient($settings);
	}

	if (!$dry_run && $recipient === '') {
		bvmgr_ticket_integrity_patch_daily_report_state(
			array(
				'last_trigger' => sanitize_key($trigger),
				'last_mode' => $mode,
				'last_result' => 'no_recipient',
				'last_error' => 'no_recipient',
				'next_scheduled_run_at' => bvmgr_ticket_integrity_daily_report_next_scheduled_run(),
			)
		);
		if (function_exists('bvmgr_ticket_integrity_log_event')) {
			bvmgr_ticket_integrity_log_event(
				'daily_report_failed',
				__('State of the Range could not start because no recipient is configured.', 'backstage-venue-manager'),
				array(
					'trigger' => sanitize_key($trigger),
				)
			);
		}
		return array('ok' => false, 'message' => __('No daily report recipient is configured.', 'backstage-venue-manager'));
	}

	$guard_id = function_exists('bvmgr_ticket_integrity_begin_fatal_guard')
		? bvmgr_ticket_integrity_begin_fatal_guard(
			'daily_report',
			array(
				'trigger' => sanitize_key($trigger),
				'recipient' => $recipient,
				'mode' => $mode,
			)
		)
		: '';

	if (function_exists('bvmgr_ticket_integrity_log_event')) {
		bvmgr_ticket_integrity_log_event(
			'daily_report_started',
			__('State of the Range started.', 'backstage-venue-manager'),
			array(
				'trigger' => sanitize_key($trigger),
				'recipient' => $recipient,
				'mode' => $mode,
			)
		);
	}

	bvmgr_ticket_integrity_patch_daily_report_state(
		array(
			'last_trigger' => sanitize_key($trigger),
			'last_mode' => $mode,
			'last_recipient' => $recipient,
			'last_render_started_at' => time(),
			'last_subject' => '',
			'last_mailer' => '',
			'last_result' => 'render_started',
			'last_error' => '',
			'last_status' => '',
			'used_stale_snapshot' => 0,
			'next_scheduled_run_at' => bvmgr_ticket_integrity_daily_report_next_scheduled_run(),
		)
	);

	try {
		$payload = bvmgr_ticket_integrity_prepare_state_of_range_payload(
			$trigger === 'cron' ? 'daily_report' : 'manual_daily_report',
			$generated_at_gmt,
			array(
				'allow_refresh' => ($trigger !== 'cron'),
			)
		);
		$store = is_array($payload['store'] ?? null) ? $payload['store'] : array();

		if (($payload['snapshot_status'] ?? '') === 'missing') {
			$result_key = !empty($payload['refresh_attempted']) ? 'skipped_scan_failed' : 'skipped_no_snapshot';
			$error_key = !empty($payload['refresh_attempted'])
				? sanitize_text_field((string) ($payload['refresh_message'] ?? 'scan_failed'))
				: 'no_usable_snapshot';
			bvmgr_ticket_integrity_patch_daily_report_state(
				array(
					'last_recipient' => $recipient,
					'last_trigger' => sanitize_key($trigger),
					'last_mode' => $mode,
					'last_result' => $result_key,
					'last_status' => 'failed',
					'last_error' => $error_key,
					'used_stale_snapshot' => 0,
				)
			);

			if (function_exists('bvmgr_ticket_integrity_log_event')) {
				if (!empty($payload['refresh_attempted'])) {
					bvmgr_ticket_integrity_log_event(
						'daily_report_skipped_scan_failed',
						__('State of the Range was skipped because the required refresh scan failed.', 'backstage-venue-manager'),
						array(
							'trigger' => sanitize_key($trigger),
							'recipient' => $recipient,
							'refresh_message' => (string) ($payload['refresh_message'] ?? 'scan_failed'),
						)
					);
				} else {
					bvmgr_ticket_integrity_log_event(
						'daily_report_skipped_no_snapshot',
						__('State of the Range was skipped because no usable integrity snapshot was available.', 'backstage-venue-manager'),
						array(
							'trigger' => sanitize_key($trigger),
							'recipient' => $recipient,
						)
					);
				}
				bvmgr_ticket_integrity_log_event(
					'daily_report_failed',
					!empty($payload['refresh_attempted'])
						? __('State of the Range failed because a fresh scan could not be completed.', 'backstage-venue-manager')
						: __('State of the Range failed because no usable integrity snapshot was available.', 'backstage-venue-manager'),
					array(
						'trigger' => sanitize_key($trigger),
						'recipient' => $recipient,
						'refresh_message' => (string) ($payload['refresh_message'] ?? 'no_usable_snapshot'),
					)
				);
			}

			return array(
				'ok' => false,
				'message' => !empty($payload['refresh_attempted'])
					? __('A fresh integrity scan failed and no prior snapshot was available for the daily report.', 'backstage-venue-manager')
					: __('No usable integrity snapshot is available for the daily report.', 'backstage-venue-manager'),
			);
		}

		$email = bvmgr_ticket_integrity_build_state_of_range_email($store);
		$subject = (string) ($email['subject'] ?? __('State of the Range', 'backstage-venue-manager'));
		$body = (string) ($email['body'] ?? '');
		bvmgr_ticket_integrity_patch_daily_report_state(
			array(
				'last_render_finished_at' => time(),
				'last_subject' => $subject,
				'last_recipient' => $recipient,
				'last_trigger' => sanitize_key($trigger),
				'last_mode' => $mode,
				'last_result' => 'rendered',
				'used_stale_snapshot' => !empty($payload['used_stale_snapshot']) ? 1 : 0,
			)
		);
		if (trim($body) === '') {
			bvmgr_ticket_integrity_patch_daily_report_state(
				array(
					'last_recipient' => $recipient,
					'last_subject' => $subject,
					'last_trigger' => sanitize_key($trigger),
					'last_mode' => $mode,
					'last_result' => 'empty_body',
					'last_status' => 'failed',
					'last_error' => 'empty_body',
					'used_stale_snapshot' => !empty($payload['used_stale_snapshot']) ? 1 : 0,
				)
			);

			if (function_exists('bvmgr_ticket_integrity_log_event')) {
				bvmgr_ticket_integrity_log_event(
					'daily_report_failed',
					__('State of the Range email body was empty.', 'backstage-venue-manager'),
					array(
						'trigger' => sanitize_key($trigger),
						'recipient' => $recipient,
					)
				);
			}

			return array('ok' => false, 'message' => __('The daily report body was empty.', 'backstage-venue-manager'));
		}

		if ($dry_run) {
			bvmgr_ticket_integrity_patch_daily_report_state(
				array(
					'last_recipient' => $recipient,
					'last_subject' => $subject,
					'last_trigger' => sanitize_key($trigger),
					'last_mode' => $mode,
					'last_result' => 'dry_run_rendered',
					'last_error' => '',
					'used_stale_snapshot' => !empty($payload['used_stale_snapshot']) ? 1 : 0,
				)
			);

			if (function_exists('bvmgr_ticket_integrity_log_event')) {
				bvmgr_ticket_integrity_log_event(
					'daily_report_dry_run',
					__('State of the Range dry run rendered successfully.', 'backstage-venue-manager'),
					array(
						'trigger' => sanitize_key($trigger),
						'mode' => $mode,
						'recipient' => $recipient,
						'used_stale_snapshot' => !empty($payload['used_stale_snapshot']) ? 1 : 0,
					)
				);
			}

			return array(
				'ok' => true,
				'message' => __('State of the Range dry run rendered successfully.', 'backstage-venue-manager'),
				'mode' => $mode,
				'email' => $email,
			);
		}

		$send_attempt_at = time();
		bvmgr_ticket_integrity_patch_daily_report_state(
			array(
				'last_send_attempt_at' => $send_attempt_at,
				'last_attempted_gmt' => $send_attempt_at,
				'last_recipient' => $recipient,
				'last_subject' => $subject,
				'last_trigger' => sanitize_key($trigger),
				'last_mode' => $mode,
				'last_result' => 'send_attempted',
			)
		);

		$mail = bvmgr_ticket_integrity_capture_mailer_details(
			static function () use ($recipient, $subject, $body): bool {
				return (bool) wp_mail($recipient, $subject, $body);
			}
		);
		$sent = !empty($mail['sent']);
		$state_changes = array(
			'last_recipient' => $recipient,
			'last_subject' => $subject,
			'last_mailer' => sanitize_text_field((string) ($mail['mailer'] ?? 'wp_mail')),
			'last_trigger' => sanitize_key($trigger),
			'last_mode' => $mode,
			'last_result' => $sent ? 'send_success' : 'send_failed',
			'last_status' => $sent ? 'sent' : 'failed',
			'last_error' => $sent ? '' : sanitize_text_field((string) ($mail['error'] ?? 'wp_mail_false')),
			'used_stale_snapshot' => !empty($payload['used_stale_snapshot']) ? 1 : 0,
		);
		if ($sent) {
			$state_changes['last_successful_send_at'] = $send_attempt_at;
			$state_changes['last_sent_gmt'] = $send_attempt_at;
		}
		bvmgr_ticket_integrity_patch_daily_report_state($state_changes);

		if (function_exists('bvmgr_ticket_integrity_log_event')) {
			bvmgr_ticket_integrity_log_event(
				$sent ? 'daily_report_sent' : 'daily_report_failed',
				$sent ? __('State of the Range email sent.', 'backstage-venue-manager') : __('State of the Range email failed to send.', 'backstage-venue-manager'),
				array(
					'recipient' => $recipient,
					'mode' => $mode,
					'trigger' => sanitize_key($trigger),
					'event_count' => count((array) ($store['events'] ?? array())),
					'mailer' => sanitize_text_field((string) ($mail['mailer'] ?? 'wp_mail')),
					'mail_error' => !$sent ? sanitize_text_field((string) ($mail['error'] ?? 'wp_mail_false')) : '',
					'used_stale_snapshot' => !empty($payload['used_stale_snapshot']) ? 1 : 0,
					'refresh_attempted' => !empty($payload['refresh_attempted']) ? 1 : 0,
					'refresh_message' => (string) ($payload['refresh_message'] ?? 'ok'),
				)
			);
		}

		return array(
			'ok' => (bool) $sent,
			'message' => $sent ? __('State of the Range email sent.', 'backstage-venue-manager') : __('wp_mail returned false while sending the daily report.', 'backstage-venue-manager'),
			'mode' => $mode,
			'email' => $email,
		);
	} finally {
		if ($guard_id !== '' && function_exists('bvmgr_ticket_integrity_end_fatal_guard')) {
			bvmgr_ticket_integrity_end_fatal_guard($guard_id);
		}
	}
}
