<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_pass_claims_capability')) {
	function vms_pass_claims_capability(): string
	{
		return function_exists('vms_admission_manage_capability') ? vms_admission_manage_capability() : 'manage_options';
	}
}

if (!function_exists('vms_pass_claims_menu_slug')) {
	function vms_pass_claims_menu_slug(): string
	{
		return 'vms-passes';
	}
}

if (!function_exists('vms_pass_claims_admin_page_url')) {
	function vms_pass_claims_admin_page_url(array $args = array()): string
	{
		return add_query_arg($args, admin_url('admin.php?page=' . vms_pass_claims_menu_slug()));
	}
}

if (!function_exists('vms_pass_claims_is_admin_page')) {
	function vms_pass_claims_is_admin_page(): bool
	{
		if (!is_admin()) {
			return false;
		}
		$page = vms_request_read_key($_GET, 'page'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive read-only Pass Claims page routing remains nonce-free while rejecting malformed page values.
		return $page === vms_pass_claims_menu_slug();
	}
}

if (!function_exists('vms_pass_claims_allowed_validity_types')) {
	function vms_pass_claims_allowed_validity_types(): array
	{
		return array('single_event', 'date_range', 'season', 'any_event');
	}
}

if (!function_exists('vms_pass_claims_allowed_value_types')) {
	function vms_pass_claims_allowed_value_types(): array
	{
		return array('free', 'percent', 'fixed');
	}
}

if (!function_exists('vms_pass_claims_allowed_batch_statuses')) {
	function vms_pass_claims_allowed_batch_statuses(): array
	{
		return array('active', 'paused', 'voided');
	}
}

if (!function_exists('vms_pass_claims_allowed_checkin_open_modes')) {
	function vms_pass_claims_allowed_checkin_open_modes(): array
	{
		return array('same_day', '24h', '48h', 'admins_only');
	}
}

if (!function_exists('vms_pass_claims_validity_labels')) {
	function vms_pass_claims_validity_labels(): array
	{
		return array(
			'single_event' => __('Single Event', 'backstage-venue-manager'),
			'date_range' => __('Date Range', 'backstage-venue-manager'),
			'season' => __('Season', 'backstage-venue-manager'),
			'any_event' => __('Any Event', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('vms_pass_claims_value_type_labels')) {
	function vms_pass_claims_value_type_labels(): array
	{
		return array(
			'free' => __('Free (100% off)', 'backstage-venue-manager'),
			'percent' => __('Percent Off', 'backstage-venue-manager'),
			'fixed' => __('Fixed Amount Off', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('vms_pass_claims_batch_status_labels')) {
	function vms_pass_claims_batch_status_labels(): array
	{
		return array(
			'active' => __('Active', 'backstage-venue-manager'),
			'paused' => __('Paused', 'backstage-venue-manager'),
			'voided' => __('Voided', 'backstage-venue-manager'),
		);
	}
}

if (!function_exists('vms_pass_claims_checkin_open_labels')) {
	function vms_pass_claims_checkin_open_labels(): array
	{
		return array(
			'same_day' => __('Same day', 'backstage-venue-manager'),
			'24h' => __('24 hours before', 'backstage-venue-manager'),
			'48h' => __('48 hours before', 'backstage-venue-manager'),
			'admins_only' => __('Always open for admins only', 'backstage-venue-manager'),
		);
	}
}


if (!function_exists('vms_pass_claims_today')) {
	function vms_pass_claims_today(): string
	{
		return function_exists('current_time') ? (string) current_time('Y-m-d') : gmdate('Y-m-d');
	}
}

if (!function_exists('vms_pass_claims_format_public_date')) {
	function vms_pass_claims_format_public_date(string $date): string
	{
		$date = trim($date);
		if ($date === '') {
			return '';
		}
		try {
			$dt = new DateTimeImmutable($date, wp_timezone());
			return function_exists('wp_date') ? wp_date('F j, Y', $dt->getTimestamp(), wp_timezone()) : $dt->format('F j, Y');
		} catch (Exception $e) {
			return $date;
		}
	}
}

if (!function_exists('vms_pass_claims_qr_image_url')) {
	function vms_pass_claims_qr_image_url(string $data): string
	{
		$data = trim($data);
		if ($data === '') {
			return '';
		}
		return 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&margin=16&data=' . rawurlencode($data);
	}
}

if (!function_exists('vms_pass_claims_parse_local_datetime')) {
	function vms_pass_claims_parse_local_datetime(string $raw): string
	{
		$raw = trim($raw);
		if ($raw === '') {
			return '';
		}
		$raw = str_replace('T', ' ', $raw);
		try {
			$dt = new DateTimeImmutable($raw, wp_timezone());
			return (string) wp_date('Y-m-d H:i:s', $dt->getTimestamp(), wp_timezone());
		} catch (Exception $e) {
			return '';
		}
	}
}

if (!function_exists('vms_pass_claims_format_local_datetime_input')) {
	function vms_pass_claims_format_local_datetime_input(string $raw): string
	{
		$raw = trim($raw);
		if ($raw === '') {
			return '';
		}
		$ts = strtotime($raw . ' ' . wp_timezone_string());
		if (!$ts) {
			return '';
		}
		return (string) wp_date('Y-m-d\TH:i', $ts, wp_timezone());
	}
}

if (!function_exists('vms_pass_claims_normalize_phone')) {
	function vms_pass_claims_normalize_phone(string $phone): string
	{
		$digits = preg_replace('/\D+/', '', $phone);
		if (!is_string($digits)) {
			return '';
		}
		if (strlen($digits) > 15) {
			$digits = substr($digits, 0, 15);
		}
		return $digits;
	}
}

if (!function_exists('vms_pass_claims_event_plan_is_published')) {
	function vms_pass_claims_event_plan_is_published(int $event_plan_id): bool
	{
		if ($event_plan_id <= 0) {
			return false;
		}
		$key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'status') : '_vms_event_plan_status';
		if ($key === '') {
			$key = '_vms_event_plan_status';
		}
		$status = sanitize_key((string) get_post_meta($event_plan_id, $key, true));
		return $status === 'published';
	}
}

if (!function_exists('vms_pass_claims_get_event_plan_brief')) {
	function vms_pass_claims_get_event_plan_brief(int $event_plan_id): ?array
	{
		if ($event_plan_id <= 0) {
			return null;
		}
		$post = get_post($event_plan_id);
		if (!($post instanceof WP_Post) || $post->post_type !== 'vms_event_plan') {
			return null;
		}
		if ((string) $post->post_status !== 'publish') {
			return null;
		}
		if (!vms_pass_claims_event_plan_is_published($event_plan_id)) {
			return null;
		}

		$event_date = (string) get_post_meta($event_plan_id, '_vms_event_date', true);
		if ($event_date !== '' && $event_date < vms_pass_claims_today()) {
			return null;
		}
		$venue_id = (int) get_post_meta($event_plan_id, '_vms_venue_id', true);
		$venue_name = $venue_id > 0 ? (string) get_the_title($venue_id) : '';

		return array(
			'id' => $event_plan_id,
			'title' => (string) get_the_title($event_plan_id),
			'event_date' => $event_date,
			'venue_id' => $venue_id,
			'venue_name' => $venue_name,
		);
	}
}

if (!function_exists('vms_pass_claims_get_published_event_plans')) {
	function vms_pass_claims_get_published_event_plans(int $limit = 300): array
	{
		$today = vms_pass_claims_today();
		$meta_query = array(
			'relation' => 'AND',
			array(
				'key' => function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'status') : '_vms_event_plan_status',
				'value' => 'published',
				'compare' => '=',
			),
			array(
				'key' => '_vms_event_date',
				'value' => $today,
				'compare' => '>=',
				'type' => 'DATE',
			),
		);

			$ids = get_posts(array(
				'post_type' => 'vms_event_plan',
				'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
				'posts_per_page' => max(1, min(500, $limit)),
				'fields' => 'ids',
				'meta_key' => '_vms_event_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Published event-plan lists intentionally sort by the existing event-date postmeta contract and remain bounded to the current admin request.
				'orderby' => 'meta_value',
				'order' => 'ASC',
				'meta_query' => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Published event-plan lists intentionally filter by the existing event-date/status postmeta contract and remain bounded to the current admin request.
			));

		$out = array();
		foreach ((array) $ids as $id) {
			$brief = vms_pass_claims_get_event_plan_brief((int) $id);
			if ($brief) {
				$out[] = $brief;
			}
		}
		return $out;
	}
}

if (!function_exists('vms_pass_claims_get_sources')) {
	function vms_pass_claims_get_sources(bool $include_inactive = false): array
	{
		global $wpdb;
		$table = vms_admission_table_pass_sources();
		if ($include_inactive) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pass-source lists read the plugin-owned pass-source table with a %i-prepared identifier so admin edits are immediately visible without a persistent cache layer.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i ORDER BY source_name ASC, id DESC LIMIT 500',
					$table
				),
				ARRAY_A
				);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Active pass-source lists read the plugin-owned pass-source table with a %i/%s-prepared identifier and status filter so admin edits are immediately visible without a persistent cache layer.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE status = %s ORDER BY source_name ASC, id DESC LIMIT 500',
					$table,
					'active'
				),
				ARRAY_A
			);
		}
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('vms_pass_claims_get_source_by_id')) {
	function vms_pass_claims_get_source_by_id(int $source_id): ?array
	{
		if ($source_id <= 0) {
			return null;
		}
		global $wpdb;
		$table = vms_admission_table_pass_sources();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pass-source reads target the plugin-owned pass-source table with a %i/%d-prepared identifier and ID, and admin maintenance must observe request-fresh state.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$table,
				$source_id
			),
			ARRAY_A
		);
		return is_array($row) ? $row : null;
	}
}

if (!function_exists('vms_pass_claims_get_batches')) {
	function vms_pass_claims_get_batches(int $limit = 200): array
	{
		global $wpdb;
		$table = vms_admission_table_pass_batches();
		$sources = vms_admission_table_pass_sources();
		$limit = max(1, min(500, $limit));
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pass-batch lists read plugin-owned batch and source tables with %i/%d-prepared identifiers and bounds so admin maintenance reflects immediate writes.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT b.*, s.source_name
				 FROM %i b
				 LEFT JOIN %i s ON s.id = b.source_id
				 ORDER BY b.id DESC
				 LIMIT %d',
				$table,
				$sources,
				$limit
			),
			ARRAY_A
		);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('vms_pass_claims_get_batch_by_id')) {
	function vms_pass_claims_get_batch_by_id(int $batch_id): ?array
	{
		if ($batch_id <= 0) {
			return null;
		}
		global $wpdb;
		$table = vms_admission_table_pass_batches();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pass-batch reads target the plugin-owned batch table with a %i/%d-prepared identifier and ID, and admin maintenance must observe request-fresh state.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$table,
				$batch_id
			),
			ARRAY_A
		);
		return is_array($row) ? $row : null;
	}
}

if (!function_exists('vms_pass_claims_get_token_by_id')) {
	function vms_pass_claims_get_token_by_id(int $token_id): ?array
	{
		if ($token_id <= 0) {
			return null;
		}
		global $wpdb;
		$table = vms_admission_table_pass_tokens();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pass-token reads target the plugin-owned token table with a %i/%d-prepared identifier and ID, and admin/public claim flows must observe request-fresh state.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$table,
				$token_id
			),
			ARRAY_A
		);
		return is_array($row) ? $row : null;
	}
}

if (!function_exists('vms_pass_claims_get_tokens')) {
	function vms_pass_claims_get_tokens(int $batch_id = 0, int $limit = 200): array
	{
		global $wpdb;
		$table = vms_admission_table_pass_tokens();
		$batches = vms_admission_table_pass_batches();
		$claims = vms_admission_table_pass_claims();
		$entries = vms_admission_table_entries();
		$limit = max(1, min(10000, $limit));
		if ($batch_id > 0) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch-scoped pass-token lists join plugin-owned token, batch, claim, and admissions tables with prepared identifiers and bounds so admin maintenance reflects immediate writes.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT t.*, b.batch_name, c.first_name, c.last_name, c.phone, c.email, c.event_plan_id, e.admission_emailed_at
					 FROM %i t
					 LEFT JOIN %i b ON b.id = t.batch_id
					 LEFT JOIN %i c ON c.id = t.claim_id
					 LEFT JOIN %i e ON e.id = t.reservation_entry_id
					 WHERE t.batch_id = %d
					 ORDER BY t.id DESC
					 LIMIT %d',
					$table,
					$batches,
					$claims,
					$entries,
					$batch_id,
					$limit
				),
				ARRAY_A
				);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Global pass-token lists join plugin-owned token, batch, claim, and admissions tables with prepared identifiers and bounds so admin maintenance reflects immediate writes.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT t.*, b.batch_name, c.first_name, c.last_name, c.phone, c.email, c.event_plan_id, e.admission_emailed_at
					 FROM %i t
					 LEFT JOIN %i b ON b.id = t.batch_id
					 LEFT JOIN %i c ON c.id = t.claim_id
					 LEFT JOIN %i e ON e.id = t.reservation_entry_id
					 ORDER BY t.id DESC
					 LIMIT %d',
					$table,
					$batches,
					$claims,
					$entries,
					$limit
				),
				ARRAY_A
			);
		}
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('vms_pass_claims_export_url')) {
	function vms_pass_claims_export_url(int $batch_id = 0): string
	{
		$batch_id = max(0, $batch_id);
		$url = add_query_arg(
			array(
				'action' => 'vms_pass_export_csv',
				'batch_id' => $batch_id,
			),
			admin_url('admin-post.php')
		);
		return (string) wp_nonce_url($url, 'vms_pass_export_' . $batch_id);
	}
}

if (!function_exists('vms_pass_claims_reports_export_url')) {
	function vms_pass_claims_reports_export_url(string $scope = 'source'): string
	{
		$scope = sanitize_key($scope);
		if (!in_array($scope, array('source', 'batch', 'source_event', 'event'), true)) {
			$scope = 'source';
		}
		$url = add_query_arg(
			array(
				'action' => 'vms_pass_report_export_csv',
				'scope' => $scope,
			),
			admin_url('admin-post.php')
		);
		return (string) wp_nonce_url($url, 'vms_pass_report_export_' . $scope);
	}
}

if (!function_exists('vms_pass_claims_format_claim_rate')) {
	function vms_pass_claims_format_claim_rate(int $claimed, int $issued): string
	{
		if ($issued <= 0) {
			return '0%';
		}
		return number_format_i18n(($claimed / $issued) * 100, 1) . '%';
	}
}

if (!function_exists('vms_pass_claims_event_context_for_reports')) {
	function vms_pass_claims_event_context_for_reports(int $event_plan_id): array
	{
		if ($event_plan_id <= 0) {
			return array(
				'title' => '',
				'date' => '',
			);
		}
		$post = get_post($event_plan_id);
		/* translators: %d: deleted event ID. */
		$title = ($post instanceof WP_Post) ? (string) get_the_title($event_plan_id) : sprintf(__('Deleted Event #%d', 'backstage-venue-manager'), $event_plan_id);
		$date = (string) get_post_meta($event_plan_id, '_vms_event_date', true);
		return array(
			'title' => $title,
			'date' => $date,
		);
	}
}

if (!function_exists('vms_pass_claims_reports_by_source')) {
	function vms_pass_claims_reports_by_source(): array
	{
		global $wpdb;
		$sources = vms_admission_table_pass_sources();
		$batches = vms_admission_table_pass_batches();
		$tokens = vms_admission_table_pass_tokens();
		$claims = vms_admission_table_pass_claims();
		$entries = vms_admission_table_entries();
		$entries = vms_admission_table_entries();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pass-source reporting joins plugin-owned source, batch, token, claim, and admissions tables with prepared identifiers so admin reporting reflects immediate request-fresh state.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					s.id,
					s.source_name,
					COUNT(DISTINCT b.id) AS batches_count,
					COUNT(t.id) AS tokens_issued,
					SUM(CASE WHEN t.status = 'claimed' THEN 1 ELSE 0 END) AS tokens_claimed,
					COUNT(c.id) AS reservations_count,
					SUM(CASE WHEN e.status <> 'canceled' AND e.checked_in_qty > 0 THEN 1 ELSE 0 END) AS checked_in_entries,
					SUM(CASE WHEN e.status <> 'canceled' THEN e.checked_in_qty ELSE 0 END) AS checked_in_headcount,
					COUNT(DISTINCT CASE WHEN c.phone_norm <> '' THEN c.phone_norm ELSE NULL END) AS unique_phones,
					GREATEST(COUNT(c.id) - COUNT(DISTINCT CASE WHEN c.phone_norm <> '' THEN c.phone_norm ELSE NULL END), 0) AS repeat_claims
					FROM %i s
					LEFT JOIN %i b ON b.source_id = s.id
					LEFT JOIN %i t ON t.batch_id = b.id
					LEFT JOIN %i c ON c.id = t.claim_id
					LEFT JOIN %i e ON e.id = c.reservation_entry_id
					GROUP BY s.id, s.source_name
					ORDER BY s.source_name ASC",
				$sources,
				$batches,
				$tokens,
				$claims,
				$entries
			),
			ARRAY_A
		);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('vms_pass_claims_reports_by_batch')) {
	function vms_pass_claims_reports_by_batch(): array
	{
		global $wpdb;
		$batches = vms_admission_table_pass_batches();
		$sources = vms_admission_table_pass_sources();
		$tokens = vms_admission_table_pass_tokens();
		$claims = vms_admission_table_pass_claims();
		$entries = vms_admission_table_entries();
		$entries = vms_admission_table_entries();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pass-batch reporting joins plugin-owned batch, source, token, claim, and admissions tables with prepared identifiers so admin reporting reflects immediate request-fresh state.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					b.id,
					b.batch_name,
					b.status,
					b.validity_type,
					b.value_type,
					b.value_amount,
					b.expires_at,
					s.source_name,
					COUNT(t.id) AS tokens_issued,
					SUM(CASE WHEN t.status = 'claimed' THEN 1 ELSE 0 END) AS tokens_claimed,
					SUM(CASE WHEN t.status = 'void' THEN 1 ELSE 0 END) AS tokens_void,
					COUNT(c.id) AS reservations_count,
					SUM(CASE WHEN e.status <> 'canceled' AND e.checked_in_qty > 0 THEN 1 ELSE 0 END) AS checked_in_entries,
					SUM(CASE WHEN e.status <> 'canceled' THEN e.checked_in_qty ELSE 0 END) AS checked_in_headcount,
					COUNT(DISTINCT CASE WHEN c.phone_norm <> '' THEN c.phone_norm ELSE NULL END) AS unique_phones,
					GREATEST(COUNT(c.id) - COUNT(DISTINCT CASE WHEN c.phone_norm <> '' THEN c.phone_norm ELSE NULL END), 0) AS repeat_claims
					FROM %i b
					LEFT JOIN %i s ON s.id = b.source_id
					LEFT JOIN %i t ON t.batch_id = b.id
					LEFT JOIN %i c ON c.id = t.claim_id
					LEFT JOIN %i e ON e.id = c.reservation_entry_id
					GROUP BY b.id, b.batch_name, b.status, b.validity_type, b.value_type, b.value_amount, b.expires_at, s.source_name
					ORDER BY b.id DESC
					LIMIT 400",
				$batches,
				$sources,
				$tokens,
				$claims,
				$entries
			),
			ARRAY_A
		);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('vms_pass_claims_reports_source_events')) {
	function vms_pass_claims_reports_source_events(): array
	{
		global $wpdb;
		$sources = vms_admission_table_pass_sources();
		$claims = vms_admission_table_pass_claims();
		$entries = vms_admission_table_entries();
		$entries = vms_admission_table_entries();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pass source-by-event reporting joins plugin-owned claim, source, and admissions tables with prepared identifiers so admin reporting reflects immediate request-fresh state.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					c.source_id,
					s.source_name,
					c.event_plan_id,
					COUNT(c.id) AS reservations_count,
					SUM(CASE WHEN e.status <> 'canceled' AND e.checked_in_qty > 0 THEN 1 ELSE 0 END) AS checked_in_entries,
					SUM(CASE WHEN e.status <> 'canceled' THEN e.checked_in_qty ELSE 0 END) AS checked_in_headcount,
					COUNT(DISTINCT CASE WHEN c.phone_norm <> '' THEN c.phone_norm ELSE NULL END) AS unique_phones,
					GREATEST(COUNT(c.id) - COUNT(DISTINCT CASE WHEN c.phone_norm <> '' THEN c.phone_norm ELSE NULL END), 0) AS repeat_claims
					FROM %i c
					LEFT JOIN %i s ON s.id = c.source_id
					LEFT JOIN %i e ON e.id = c.reservation_entry_id
					GROUP BY c.source_id, s.source_name, c.event_plan_id
					ORDER BY s.source_name ASC, reservations_count DESC, c.event_plan_id DESC
					LIMIT 1000",
				$claims,
				$sources,
				$entries
			),
			ARRAY_A
		);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('vms_pass_claims_reports_by_event')) {
	function vms_pass_claims_reports_by_event(): array
	{
		global $wpdb;
		$claims = vms_admission_table_pass_claims();
		$entries = vms_admission_table_entries();
		$entries = vms_admission_table_entries();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pass event reporting joins plugin-owned claim and admissions tables with prepared identifiers so admin reporting reflects immediate request-fresh state.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					c.event_plan_id,
					COUNT(c.id) AS reservations_count,
					COUNT(DISTINCT c.source_id) AS source_count,
					COUNT(DISTINCT c.batch_id) AS batch_count,
					SUM(CASE WHEN e.status <> 'canceled' AND e.checked_in_qty > 0 THEN 1 ELSE 0 END) AS checked_in_entries,
					SUM(CASE WHEN e.status <> 'canceled' THEN e.checked_in_qty ELSE 0 END) AS checked_in_headcount,
					COUNT(DISTINCT CASE WHEN c.phone_norm <> '' THEN c.phone_norm ELSE NULL END) AS unique_phones,
					GREATEST(COUNT(c.id) - COUNT(DISTINCT CASE WHEN c.phone_norm <> '' THEN c.phone_norm ELSE NULL END), 0) AS repeat_claims
					FROM %i c
					LEFT JOIN %i e ON e.id = c.reservation_entry_id
					GROUP BY c.event_plan_id
					ORDER BY reservations_count DESC, c.event_plan_id DESC
					LIMIT 400",
				$claims,
				$entries
			),
			ARRAY_A
		);
		return is_array($rows) ? $rows : array();
	}
}

if (!function_exists('vms_pass_claims_parse_venue_ids')) {
	function vms_pass_claims_parse_venue_ids($raw): array
	{
		$ids = array();
		foreach ((array) $raw as $value) {
			$id = absint($value);
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		$ids = array_values(array_unique($ids));
		sort($ids);
		return $ids;
	}
}

if (!function_exists('vms_pass_claims_decode_venue_ids_json')) {
	function vms_pass_claims_decode_venue_ids_json(string $raw): array
	{
		$raw = trim($raw);
		if ($raw === '') {
			return array();
		}

		$decoded = vms_json_decode_associative($raw, 8);
		if (
			empty($decoded['ok'])
			|| !is_array($decoded['value'])
			|| !vms_json_decoded_is_list($decoded['value'], (string) ($decoded['top_level_token'] ?? ''))
		) {
			return array();
		}

		return vms_pass_claims_parse_venue_ids($decoded['value']);
	}
}

if (!function_exists('vms_pass_claims_soft_batch_payload_for_form')) {
	function vms_pass_claims_soft_batch_payload_for_form(array $raw): array
	{
		$venue_ids = vms_pass_claims_parse_venue_ids($raw['venue_ids'] ?? array());
		$value_type = sanitize_key((string) ($raw['value_type'] ?? 'free'));
		$value_amount = isset($raw['value_amount']) ? (float) $raw['value_amount'] : 0.0;
		if ($value_type === 'free') {
			$value_amount = 100.0;
		}
		return array(
			'source_id' => isset($raw['source_id']) ? absint($raw['source_id']) : 0,
			'batch_name' => sanitize_text_field((string) ($raw['batch_name'] ?? '')),
			'quantity' => isset($raw['quantity']) ? absint($raw['quantity']) : 20,
			'admissions_per_link' => isset($raw['admissions_per_link']) ? max(1, min(absint($raw['admissions_per_link']), 100)) : 1,
			'total_admission_cap' => isset($raw['total_admission_cap']) ? absint($raw['total_admission_cap']) : 0,
			'validity_type' => sanitize_key((string) ($raw['validity_type'] ?? 'single_event')),
			'single_event_plan_id' => isset($raw['single_event_plan_id']) ? absint($raw['single_event_plan_id']) : 0,
			'start_date' => sanitize_text_field((string) ($raw['start_date'] ?? '')),
			'end_date' => sanitize_text_field((string) ($raw['end_date'] ?? '')),
			'season_label' => sanitize_text_field((string) ($raw['season_label'] ?? '')),
			'venue_ids_json' => !empty($venue_ids) ? wp_json_encode($venue_ids) : '',
			'value_type' => $value_type,
			'value_amount' => $value_amount,
			'expires_at' => vms_pass_claims_parse_local_datetime((string) ($raw['expires_at'] ?? '')),
			'status' => sanitize_key((string) ($raw['status'] ?? 'active')),
			'checkin_open_mode' => sanitize_key((string) ($raw['checkin_open_mode'] ?? 'same_day')),
			'max_per_phone' => isset($raw['max_per_phone']) ? min(absint($raw['max_per_phone']), 50) : 0,
			'max_per_email' => isset($raw['max_per_email']) ? min(absint($raw['max_per_email']), 50) : 0,
			'notes' => sanitize_textarea_field((string) ($raw['notes'] ?? '')),
		);
	}
}

if (!function_exists('vms_pass_claims_sanitize_batch_payload')) {
	function vms_pass_claims_sanitize_batch_payload(array $raw)
	{
		$source_id = isset($raw['source_id']) ? absint($raw['source_id']) : 0;
		$batch_name = sanitize_text_field((string) ($raw['batch_name'] ?? ''));
		$quantity = isset($raw['quantity']) ? absint($raw['quantity']) : 0;
		$admissions_per_link = isset($raw['admissions_per_link']) ? absint($raw['admissions_per_link']) : 1;
		$total_admission_cap = isset($raw['total_admission_cap']) ? absint($raw['total_admission_cap']) : 0;
		$validity_type = sanitize_key((string) ($raw['validity_type'] ?? 'single_event'));
		$single_event_plan_id = isset($raw['single_event_plan_id']) ? absint($raw['single_event_plan_id']) : 0;
		$start_date = sanitize_text_field((string) ($raw['start_date'] ?? ''));
		$end_date = sanitize_text_field((string) ($raw['end_date'] ?? ''));
		$season_label = sanitize_text_field((string) ($raw['season_label'] ?? ''));
		$venue_ids = vms_pass_claims_parse_venue_ids($raw['venue_ids'] ?? array());
		$value_type = sanitize_key((string) ($raw['value_type'] ?? 'free'));
		$value_amount = isset($raw['value_amount']) ? (float) $raw['value_amount'] : 0.0;
		$expires_at = vms_pass_claims_parse_local_datetime((string) ($raw['expires_at'] ?? ''));
		$status = sanitize_key((string) ($raw['status'] ?? 'active'));
		$checkin_open_mode = sanitize_key((string) ($raw['checkin_open_mode'] ?? 'same_day'));
		$max_per_phone = isset($raw['max_per_phone']) ? absint($raw['max_per_phone']) : 0;
		$max_per_email = isset($raw['max_per_email']) ? absint($raw['max_per_email']) : 0;
		$notes = sanitize_textarea_field((string) ($raw['notes'] ?? ''));

		if ($source_id <= 0 || !vms_pass_claims_get_source_by_id($source_id)) {
			return new WP_Error('invalid_source', __('Please select a valid Source.', 'backstage-venue-manager'));
		}
		if ($batch_name === '') {
			return new WP_Error('missing_batch_name', __('Batch name is required.', 'backstage-venue-manager'));
		}
		if ($quantity < 1 || $quantity > 5000) {
			return new WP_Error('invalid_quantity', __('Number of claim links must be between 1 and 5000.', 'backstage-venue-manager'));
		}
		if ($admissions_per_link < 1 || $admissions_per_link > 100) {
			return new WP_Error('invalid_admissions_per_link', __('Admissions per claimed link must be between 1 and 100.', 'backstage-venue-manager'));
		}
		if ($total_admission_cap < 1) {
			$total_admission_cap = $quantity * $admissions_per_link;
		}
		if ($total_admission_cap < 1 || $total_admission_cap > 50000) {
			return new WP_Error('invalid_total_admission_cap', __('Total admission cap must be between 1 and 50000.', 'backstage-venue-manager'));
		}
		if (!in_array($validity_type, vms_pass_claims_allowed_validity_types(), true)) {
			return new WP_Error('invalid_validity_type', __('Select a valid Validity Type.', 'backstage-venue-manager'));
		}
		if (!in_array($value_type, vms_pass_claims_allowed_value_types(), true)) {
			return new WP_Error('invalid_value_type', __('Select a valid Admission Value type.', 'backstage-venue-manager'));
		}
		if (!in_array($status, vms_pass_claims_allowed_batch_statuses(), true)) {
			return new WP_Error('invalid_status', __('Select a valid batch status.', 'backstage-venue-manager'));
		}
		if (!in_array($checkin_open_mode, vms_pass_claims_allowed_checkin_open_modes(), true)) {
			return new WP_Error('invalid_checkin_open_mode', __('Select a valid check-in open mode.', 'backstage-venue-manager'));
		}

		if ($validity_type === 'single_event') {
			if ($single_event_plan_id <= 0 || !vms_pass_claims_get_event_plan_brief($single_event_plan_id)) {
				return new WP_Error('invalid_single_event', __('Select a published Event Plan for Single Event validity.', 'backstage-venue-manager'));
			}
		}

		if ($validity_type === 'date_range') {
			if ($start_date === '' || $end_date === '') {
				return new WP_Error('invalid_date_range', __('Start and End dates are required for Date Range validity.', 'backstage-venue-manager'));
			}
			if ($start_date > $end_date) {
				return new WP_Error('invalid_date_range_order', __('Start date must be on or before end date.', 'backstage-venue-manager'));
			}
		}

		if ($validity_type === 'season' && $season_label === '' && ($start_date === '' || $end_date === '')) {
			return new WP_Error('invalid_season', __('For Season validity, provide a Season label or a date range.', 'backstage-venue-manager'));
		}

		if ($value_type === 'free') {
			$value_amount = 100.0;
		} elseif ($value_type === 'percent') {
			if ($value_amount <= 0 || $value_amount > 100) {
				return new WP_Error('invalid_percent_value', __('Percent Off must be greater than 0 and up to 100.', 'backstage-venue-manager'));
			}
		} else {
			if ($value_amount <= 0) {
				return new WP_Error('invalid_fixed_value', __('Fixed Amount Off must be greater than 0.', 'backstage-venue-manager'));
			}
		}

		if ($max_per_phone > 50) {
			$max_per_phone = 50;
		}
		if ($max_per_email > 50) {
			$max_per_email = 50;
		}

		return array(
			'source_id' => $source_id,
			'batch_name' => $batch_name,
			'quantity' => $quantity,
			'admissions_per_link' => $admissions_per_link,
			'total_admission_cap' => $total_admission_cap,
			'validity_type' => $validity_type,
			'single_event_plan_id' => $single_event_plan_id,
			'start_date' => $start_date,
			'end_date' => $end_date,
			'season_label' => $season_label,
			'venue_ids_json' => !empty($venue_ids) ? wp_json_encode($venue_ids) : '',
			'value_type' => $value_type,
			'value_amount' => $value_amount,
			'applies_to' => 'entry_only',
			'expires_at' => $expires_at,
			'status' => $status,
			'checkin_open_mode' => $checkin_open_mode,
			'max_per_phone' => $max_per_phone,
			'max_per_email' => $max_per_email,
			'notes' => $notes,
		);
	}
}

if (!function_exists('vms_pass_claims_generate_public_key')) {
	function vms_pass_claims_generate_public_key(): string
	{
		try {
			return strtolower(bin2hex(random_bytes(12)));
		} catch (Exception $e) {
			return strtolower(wp_generate_password(24, false, false));
		}
	}
}

if (!function_exists('vms_pass_claims_token_signature')) {
	function vms_pass_claims_token_signature(int $token_id, string $public_key, int $batch_id, string $created_at): string
	{
		$payload = $token_id . '|' . $public_key . '|' . $batch_id . '|' . $created_at;
		$sig = hash_hmac('sha256', $payload, wp_salt('auth'));
		return substr($sig, 0, 24);
	}
}

if (!function_exists('vms_pass_claims_build_raw_token')) {
	function vms_pass_claims_build_raw_token(array $token_row): string
	{
		$token_id = (int) ($token_row['id'] ?? 0);
		$public_key = strtolower((string) ($token_row['token_public_key'] ?? ''));
		$batch_id = (int) ($token_row['batch_id'] ?? 0);
		$created_at = (string) ($token_row['created_at'] ?? '');
		if ($token_id <= 0 || $public_key === '' || $batch_id <= 0 || $created_at === '') {
			return '';
		}
		$sig = vms_pass_claims_token_signature($token_id, $public_key, $batch_id, $created_at);
		return $public_key . '.' . $sig;
	}
}

if (!function_exists('vms_pass_claims_build_claim_url')) {
	function vms_pass_claims_build_claim_url(array $token_row): string
	{
		$raw = vms_pass_claims_build_raw_token($token_row);
		if ($raw === '') {
			return '';
		}
		return home_url('/pass/claim/' . rawurlencode($raw));
	}
}

if (!function_exists('vms_pass_claims_mask_token')) {
	function vms_pass_claims_mask_token(string $raw_token): string
	{
		$raw_token = trim($raw_token);
		if ($raw_token === '') {
			return '';
		}
		$len = strlen($raw_token);
		if ($len <= 12) {
			return str_repeat('*', $len);
		}
		return substr($raw_token, 0, 6) . str_repeat('*', max(2, $len - 10)) . substr($raw_token, -4);
	}
}

if (!function_exists('vms_pass_claims_generate_tokens_for_batch')) {
	function vms_pass_claims_generate_tokens_for_batch(int $batch_id, int $quantity, int $source_id, int $created_by)
	{
		if ($batch_id <= 0 || $quantity <= 0) {
			return new WP_Error('invalid_batch_generation', __('Batch generation parameters are invalid.', 'backstage-venue-manager'));
		}

			global $wpdb;
			$table = vms_admission_table_pass_tokens();
			$now = vms_admission_now_mysql();
			$samples = array();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch generation uses an explicit transaction on the plugin-owned token tables so partial token writes can roll back atomically.
			$wpdb->query('START TRANSACTION');
			for ($i = 0; $i < $quantity; $i += 1) {
				$public_key = vms_pass_claims_generate_public_key();
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Batch generation writes directly to the plugin-owned pass-token table because no core API exposes this repository.
				$inserted = $wpdb->insert(
				$table,
				array(
					'batch_id' => $batch_id,
					'source_id' => $source_id,
					'token_public_key' => $public_key,
					'token_hash' => '',
					'status' => 'unclaimed',
					'created_at' => $now,
					'created_by' => $created_by,
				),
				array('%d', '%d', '%s', '%s', '%s', '%s', '%d')
				);

				if ($inserted === false) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch generation rolls back the explicit token transaction immediately when any token insert fails.
					$wpdb->query('ROLLBACK');
					return new WP_Error('batch_token_insert_failed', __('Could not generate pass tokens.', 'backstage-venue-manager'));
				}

			$token_id = (int) $wpdb->insert_id;
			$token_row = array(
				'id' => $token_id,
				'batch_id' => $batch_id,
				'token_public_key' => $public_key,
				'created_at' => $now,
			);
				$raw_token = vms_pass_claims_build_raw_token($token_row);
				if ($raw_token === '') {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch generation rolls back the explicit token transaction immediately when token signing fails.
					$wpdb->query('ROLLBACK');
					return new WP_Error('batch_token_signature_failed', __('Could not sign pass tokens.', 'backstage-venue-manager'));
				}

				$hash = hash('sha256', $raw_token);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch generation writes the finalized token hash directly to the plugin-owned token table inside the open transaction.
				$updated = $wpdb->update(
					$table,
					array('token_hash' => $hash),
				array('id' => $token_id),
				array('%s'),
				array('%d')
				);
				if ($updated === false) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch generation rolls back the explicit token transaction immediately when any token hash write fails.
					$wpdb->query('ROLLBACK');
					return new WP_Error('batch_token_hash_failed', __('Could not finalize pass tokens.', 'backstage-venue-manager'));
				}

			if (count($samples) < 5) {
				$samples[] = array(
					'token' => $raw_token,
					'claim_url' => home_url('/pass/claim/' . rawurlencode($raw_token)),
				);
			}
			}

			$batches = vms_admission_table_pass_batches();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch generation updates the plugin-owned batch table directly with a %i-prepared identifier so generated counts persist in the same request transaction.
			$batch_update = $wpdb->query($wpdb->prepare(
				"UPDATE %i SET generated_count = generated_count + %d, generated_at = %s, updated_at = %s, updated_by = %d WHERE id = %d",
				$batches,
				$quantity,
				$now,
				$now,
			$created_by,
			$batch_id
			));

			if ($batch_update === false) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch generation rolls back the explicit token transaction immediately when the batch aggregate write fails.
				$wpdb->query('ROLLBACK');
				return new WP_Error('batch_update_failed', __('Could not finalize batch generation.', 'backstage-venue-manager'));
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch generation commits the explicit token transaction after all token and batch writes succeed.
			$wpdb->query('COMMIT');
		return array(
			'generated_count' => $quantity,
			'samples' => $samples,
		);
	}
}

if (!function_exists('vms_pass_claims_preview_transient_key')) {
	function vms_pass_claims_preview_transient_key(int $user_id): string
	{
		return 'vms_pass_claim_preview_' . $user_id;
	}
}

if (!function_exists('vms_pass_claims_draft_transient_key')) {
	function vms_pass_claims_draft_transient_key(int $user_id): string
	{
		return 'vms_pass_claim_draft_' . $user_id;
	}
}

if (!function_exists('vms_pass_claims_generated_transient_key')) {
	function vms_pass_claims_generated_transient_key(int $user_id): string
	{
		return 'vms_pass_claim_generated_' . $user_id;
	}
}

if (!function_exists('vms_pass_claims_error_transient_key')) {
	function vms_pass_claims_error_transient_key(int $user_id): string
	{
		return 'vms_pass_claim_error_' . $user_id;
	}
}

if (!function_exists('vms_pass_claims_set_user_message')) {
	function vms_pass_claims_set_user_message(string $type, string $message): void
	{
		$user_id = get_current_user_id();
		if ($user_id <= 0) {
			return;
		}
		set_transient(vms_pass_claims_error_transient_key($user_id), array(
			'type' => $type,
			'message' => $message,
		), 10 * MINUTE_IN_SECONDS);
	}
}

if (!function_exists('vms_pass_claims_pop_user_message')) {
	function vms_pass_claims_pop_user_message(): array
	{
		$user_id = get_current_user_id();
		if ($user_id <= 0) {
			return array();
		}
		$key = vms_pass_claims_error_transient_key($user_id);
		$payload = get_transient($key);
		delete_transient($key);
		return is_array($payload) ? $payload : array();
	}
}

if (!function_exists('vms_pass_claims_handle_source_save')) {
	function vms_pass_claims_handle_source_save(): void
	{
		if (!current_user_can(vms_pass_claims_capability())) {
			wp_die(esc_html__('Access denied.', 'backstage-venue-manager'));
		}
		check_admin_referer('vms_pass_source_save');

		$source_name = sanitize_text_field((string) wp_unslash($_POST['source_name'] ?? ''));
		$contact_name = sanitize_text_field((string) wp_unslash($_POST['contact_name'] ?? ''));
		$phone = sanitize_text_field((string) wp_unslash($_POST['phone'] ?? ''));
		$email = sanitize_email((string) wp_unslash($_POST['email'] ?? ''));
		$notes = sanitize_textarea_field((string) wp_unslash($_POST['notes'] ?? ''));

		if ($source_name === '') {
			vms_pass_claims_set_user_message('error', __('Source name is required.', 'backstage-venue-manager'));
			wp_safe_redirect(vms_pass_claims_admin_page_url(array('tab' => 'sources')));
			exit;
		}

			global $wpdb;
			$table = vms_admission_table_pass_sources();
			$now = vms_admission_now_mysql();
			$user_id = get_current_user_id();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Pass-source creation writes directly to the plugin-owned source table because no core API exposes this repository.
			$inserted = $wpdb->insert(
			$table,
			array(
				'source_name' => $source_name,
				'contact_name' => $contact_name,
				'phone' => $phone,
				'email' => $email,
				'notes' => $notes,
				'status' => 'active',
				'created_by' => $user_id,
				'created_at' => $now,
			),
			array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
		);

		if ($inserted === false) {
			vms_pass_claims_set_user_message('error', __('Could not save Source.', 'backstage-venue-manager'));
			wp_safe_redirect(vms_pass_claims_admin_page_url(array('tab' => 'sources')));
			exit;
		}

		$source_id = (int) $wpdb->insert_id;
		if (function_exists('vms_admission_audit_log')) {
			vms_admission_audit_log(0, null, 'pass_source_create', $user_id, 'admin', array(
				'source_id' => $source_id,
				'source_name' => $source_name,
			));
		}

		wp_safe_redirect(vms_pass_claims_admin_page_url(array(
			'tab' => 'sources',
			'result' => 'source_saved',
		)));
		exit;
	}
}
add_action('admin_post_vms_pass_source_save', 'vms_pass_claims_handle_source_save');

if (!function_exists('vms_pass_claims_handle_batch_generate')) {
	function vms_pass_claims_handle_batch_generate(): void
	{
		if (!current_user_can(vms_pass_claims_capability())) {
			wp_die(esc_html__('Access denied.', 'backstage-venue-manager'));
		}
		check_admin_referer('vms_pass_batch_generate');

		$mode = sanitize_key((string) wp_unslash($_POST['generation_mode'] ?? 'preview'));
		$user_id = get_current_user_id();
		$payload = array();

		if ($mode === 'commit') {
			$preview = get_transient(vms_pass_claims_preview_transient_key($user_id));
			if (!is_array($preview) || empty($preview['payload']) || !is_array($preview['payload'])) {
				vms_pass_claims_set_user_message('error', __('Preview expired or missing. Preview the batch again before committing.', 'backstage-venue-manager'));
				wp_safe_redirect(vms_pass_claims_admin_page_url(array('tab' => 'batches')));
				exit;
			}
			$payload = (array) $preview['payload'];
		} else {
			$raw = isset($_POST) ? (array) wp_unslash($_POST) : array();
			set_transient(vms_pass_claims_draft_transient_key($user_id), vms_pass_claims_soft_batch_payload_for_form($raw), 10 * MINUTE_IN_SECONDS);
			$payload = vms_pass_claims_sanitize_batch_payload($raw);
			if (is_wp_error($payload)) {
				vms_pass_claims_set_user_message('error', $payload->get_error_message());
				wp_safe_redirect(vms_pass_claims_admin_page_url(array('tab' => 'batches')));
				exit;
			}
		}

		if ($mode === 'preview') {
			$samples = array();
			for ($i = 0; $i < 3; $i += 1) {
				$pseudo = array(
					'id' => 100000 + $i,
					'batch_id' => 999,
					'token_public_key' => vms_pass_claims_generate_public_key(),
					'created_at' => vms_admission_now_mysql(),
				);
				$raw_token = vms_pass_claims_build_raw_token($pseudo);
				if ($raw_token === '') {
					continue;
				}
				$samples[] = array(
					'token' => $raw_token,
					'claim_url' => home_url('/pass/claim/' . rawurlencode($raw_token)),
				);
			}
			set_transient(vms_pass_claims_preview_transient_key($user_id), array(
				'payload' => $payload,
				'samples' => $samples,
			), 10 * MINUTE_IN_SECONDS);
			delete_transient(vms_pass_claims_draft_transient_key($user_id));

			wp_safe_redirect(vms_pass_claims_admin_page_url(array(
				'tab' => 'batches',
				'result' => 'batch_preview',
			)));
			exit;
		}

			global $wpdb;
			$now = vms_admission_now_mysql();
			$table = vms_admission_table_pass_batches();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Pass-batch creation writes directly to the plugin-owned batch table because no core API exposes this repository.
			$insert = $wpdb->insert(
			$table,
			array(
				'source_id' => (int) $payload['source_id'],
				'batch_name' => (string) $payload['batch_name'],
				'quantity' => (int) $payload['quantity'],
				'admissions_per_link' => (int) $payload['admissions_per_link'],
				'total_admission_cap' => (int) $payload['total_admission_cap'],
				'validity_type' => (string) $payload['validity_type'],
				'single_event_plan_id' => (int) $payload['single_event_plan_id'],
				'start_date' => (string) $payload['start_date'],
				'end_date' => (string) $payload['end_date'],
				'season_label' => (string) $payload['season_label'],
				'venue_ids_json' => (string) $payload['venue_ids_json'],
				'value_type' => (string) $payload['value_type'],
				'value_amount' => (float) $payload['value_amount'],
				'applies_to' => 'entry_only',
				'expires_at' => (string) $payload['expires_at'],
				'status' => (string) $payload['status'],
				'checkin_open_mode' => (string) $payload['checkin_open_mode'],
				'max_per_phone' => (int) $payload['max_per_phone'],
				'max_per_email' => (int) $payload['max_per_email'],
				'notes' => (string) $payload['notes'],
				'created_by' => $user_id,
				'created_at' => $now,
			),
			array('%d', '%s', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s')
		);

		if ($insert === false) {
			vms_pass_claims_set_user_message('error', __('Could not create batch.', 'backstage-venue-manager'));
			wp_safe_redirect(vms_pass_claims_admin_page_url(array('tab' => 'batches')));
			exit;
		}

			$batch_id = (int) $wpdb->insert_id;
			$generated = vms_pass_claims_generate_tokens_for_batch($batch_id, (int) $payload['quantity'], (int) $payload['source_id'], $user_id);
			if (is_wp_error($generated)) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch-create rollback deletes the plugin-owned batch row directly when token generation fails inside the same request.
				$wpdb->delete($table, array('id' => $batch_id), array('%d'));
				vms_pass_claims_set_user_message('error', $generated->get_error_message());
				wp_safe_redirect(vms_pass_claims_admin_page_url(array('tab' => 'batches')));
			exit;
		}

		if (function_exists('vms_admission_audit_log')) {
			vms_admission_audit_log(0, null, 'pass_batch_generate', $user_id, 'admin', array(
				'batch_id' => $batch_id,
				'quantity' => (int) $payload['quantity'],
				'admissions_per_link' => (int) $payload['admissions_per_link'],
				'total_admission_cap' => (int) $payload['total_admission_cap'],
				'validity_type' => (string) $payload['validity_type'],
				'value_type' => (string) $payload['value_type'],
				'value_amount' => (float) $payload['value_amount'],
			));
		}

		delete_transient(vms_pass_claims_preview_transient_key($user_id));
		delete_transient(vms_pass_claims_draft_transient_key($user_id));
		set_transient(vms_pass_claims_generated_transient_key($user_id), array(
			'batch_id' => $batch_id,
			'batch_name' => (string) $payload['batch_name'],
			'generated_count' => (int) ($generated['generated_count'] ?? 0),
			'samples' => (array) ($generated['samples'] ?? array()),
		), 10 * MINUTE_IN_SECONDS);

		wp_safe_redirect(vms_pass_claims_admin_page_url(array(
			'tab' => 'batches',
			'result' => 'batch_generated',
			'batch_id' => $batch_id,
		)));
		exit;
	}
}
add_action('admin_post_vms_pass_batch_generate', 'vms_pass_claims_handle_batch_generate');

if (!function_exists('vms_pass_claims_handle_token_status_change')) {
	function vms_pass_claims_handle_token_status_change(): void
	{
		if (!current_user_can(vms_pass_claims_capability())) {
			wp_die(esc_html__('Access denied.', 'backstage-venue-manager'));
		}

		$token_id = isset($_REQUEST['token_id']) ? absint((string) $_REQUEST['token_id']) : 0;
		$target_status = sanitize_key((string) ($_REQUEST['status'] ?? ''));
		$batch_id = isset($_REQUEST['batch_id']) ? absint((string) $_REQUEST['batch_id']) : 0;
		$nonce = (isset($_REQUEST['_wpnonce']) && !is_array($_REQUEST['_wpnonce']))
			? sanitize_text_field(wp_unslash((string) $_REQUEST['_wpnonce']))
			: '';

		if ($token_id <= 0 || !in_array($target_status, array('void', 'unclaimed'), true)) {
			vms_pass_claims_set_user_message('error', __('Invalid pass action request.', 'backstage-venue-manager'));
			wp_safe_redirect(vms_pass_claims_admin_page_url(array('tab' => 'passes', 'batch_id' => $batch_id)));
			exit;
		}
		if (!wp_verify_nonce($nonce, 'vms_pass_token_status_' . $token_id . '_' . $target_status)) {
			wp_die(esc_html__('Invalid request nonce.', 'backstage-venue-manager'));
		}

		$token_row = vms_pass_claims_get_token_by_id($token_id);
		if (!$token_row) {
			vms_pass_claims_set_user_message('error', __('Pass token not found.', 'backstage-venue-manager'));
			wp_safe_redirect(vms_pass_claims_admin_page_url(array('tab' => 'passes', 'batch_id' => $batch_id)));
			exit;
		}

		$current_status = sanitize_key((string) ($token_row['status'] ?? ''));
		$user_id = get_current_user_id();
		$now = vms_admission_now_mysql();
		global $wpdb;
		$table = vms_admission_table_pass_tokens();

		if ($target_status === 'void') {
			if ($current_status === 'claimed') {
				vms_pass_claims_set_user_message('error', __('Claimed passes cannot be voided from this screen.', 'backstage-venue-manager'));
				wp_safe_redirect(vms_pass_claims_admin_page_url(array('tab' => 'passes', 'batch_id' => (int) ($token_row['batch_id'] ?? $batch_id))));
				exit;
				}
				if ($current_status !== 'void') {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pass-token status changes write directly to the plugin-owned token table so admin maintenance immediately reflects voided tokens.
					$updated = $wpdb->update(
						$table,
						array(
						'status' => 'void',
						'voided_at' => $now,
					),
					array('id' => $token_id),
					array('%s', '%s'),
					array('%d')
				);
				if ($updated === false) {
					vms_pass_claims_set_user_message('error', __('Could not void pass.', 'backstage-venue-manager'));
					wp_safe_redirect(vms_pass_claims_admin_page_url(array('tab' => 'passes', 'batch_id' => (int) ($token_row['batch_id'] ?? $batch_id))));
					exit;
				}
				if (function_exists('vms_admission_audit_log')) {
					vms_admission_audit_log(0, null, 'pass_token_void', $user_id, 'admin', array(
						'token_id' => $token_id,
						'batch_id' => (int) ($token_row['batch_id'] ?? 0),
					));
				}
			}
			wp_safe_redirect(vms_pass_claims_admin_page_url(array(
				'tab' => 'passes',
				'batch_id' => (int) ($token_row['batch_id'] ?? $batch_id),
				'result' => 'token_voided',
			)));
			exit;
		}

			if ($current_status !== 'void') {
				vms_pass_claims_set_user_message('error', __('Only void passes can be restored.', 'backstage-venue-manager'));
				wp_safe_redirect(vms_pass_claims_admin_page_url(array('tab' => 'passes', 'batch_id' => (int) ($token_row['batch_id'] ?? $batch_id))));
				exit;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pass-token status restoration writes directly to the plugin-owned token table so admin maintenance immediately reflects restored tokens.
			$updated = $wpdb->update(
				$table,
				array(
				'status' => 'unclaimed',
				'voided_at' => null,
			),
			array('id' => $token_id),
			array('%s', '%s'),
			array('%d')
		);
		if ($updated === false) {
			vms_pass_claims_set_user_message('error', __('Could not restore pass.', 'backstage-venue-manager'));
			wp_safe_redirect(vms_pass_claims_admin_page_url(array('tab' => 'passes', 'batch_id' => (int) ($token_row['batch_id'] ?? $batch_id))));
			exit;
		}
		if (function_exists('vms_admission_audit_log')) {
			vms_admission_audit_log(0, null, 'pass_token_restore', $user_id, 'admin', array(
				'token_id' => $token_id,
				'batch_id' => (int) ($token_row['batch_id'] ?? 0),
			));
		}

		wp_safe_redirect(vms_pass_claims_admin_page_url(array(
			'tab' => 'passes',
			'batch_id' => (int) ($token_row['batch_id'] ?? $batch_id),
			'result' => 'token_restored',
		)));
		exit;
	}
}
add_action('admin_post_vms_pass_token_status', 'vms_pass_claims_handle_token_status_change');

if (!function_exists('vms_pass_claims_handle_resend_email')) {
	function vms_pass_claims_handle_resend_email(): void
	{
		if (!current_user_can(vms_pass_claims_capability())) {
			wp_die(esc_html__('Access denied.', 'backstage-venue-manager'));
		}
		$token_id = isset($_REQUEST['token_id']) ? absint((string) $_REQUEST['token_id']) : 0;
		$batch_id = isset($_REQUEST['batch_id']) ? absint((string) $_REQUEST['batch_id']) : 0;
		$nonce = (isset($_REQUEST['_wpnonce']) && !is_array($_REQUEST['_wpnonce']))
			? sanitize_text_field(wp_unslash((string) $_REQUEST['_wpnonce']))
			: '';
		if ($token_id <= 0 || !wp_verify_nonce($nonce, 'vms_pass_resend_email_' . $token_id)) {
			wp_die(esc_html__('Invalid request nonce.', 'backstage-venue-manager'));
		}
		$token_row = vms_pass_claims_get_token_by_id($token_id);
		$entry_id = is_array($token_row) ? (int) ($token_row['reservation_entry_id'] ?? 0) : 0;
		if ($entry_id <= 0) {
			vms_pass_claims_set_user_message('error', __('Could not resend the pass email because this pass is not linked to an admission record.', 'backstage-venue-manager'));
		} elseif (!function_exists('vms_admission_email_pass_result')) {
			vms_pass_claims_set_user_message('error', __('Could not resend the pass email because the email helper is unavailable.', 'backstage-venue-manager'));
		} else {
			$result = vms_admission_email_pass_result($entry_id, 'guest_pass_admin_resend');
			if (!empty($result['sent'])) {
				$email = isset($result['email']) ? (string) $result['email'] : '';
				/* translators: %s: email address. */
				vms_pass_claims_set_user_message('success', $email !== '' ? sprintf(__('Pass email resent to %s.', 'backstage-venue-manager'), $email) : __('Pass email resent.', 'backstage-venue-manager'));
			} else {
				$message = isset($result['message']) ? (string) $result['message'] : __('WordPress did not accept the pass email for delivery.', 'backstage-venue-manager');
				/* translators: %s: human-readable pass email delivery failure message. */
				vms_pass_claims_set_user_message('error', sprintf(__('Pass email was not sent: %s', 'backstage-venue-manager'), $message));
			}
		}
		wp_safe_redirect(vms_pass_claims_admin_page_url(array('tab' => 'passes', 'batch_id' => $batch_id)));
		exit;
	}
}
add_action('admin_post_vms_pass_resend_email', 'vms_pass_claims_handle_resend_email');


if (!function_exists('vms_pass_claims_handle_export_csv')) {
	function vms_pass_claims_handle_export_csv(): void
	{
		if (!current_user_can(vms_pass_claims_capability())) {
			wp_die(esc_html__('Access denied.', 'backstage-venue-manager'));
		}

		$batch_id = isset($_REQUEST['batch_id']) ? absint((string) $_REQUEST['batch_id']) : 0;
		$nonce = (isset($_REQUEST['_wpnonce']) && !is_array($_REQUEST['_wpnonce']))
			? sanitize_text_field(wp_unslash((string) $_REQUEST['_wpnonce']))
			: '';
		if (!wp_verify_nonce($nonce, 'vms_pass_export_' . $batch_id)) {
			wp_die(esc_html__('Invalid request nonce.', 'backstage-venue-manager'));
		}

		$rows = vms_pass_claims_get_tokens($batch_id, 10000);
		if (!headers_sent()) {
			nocache_headers();
			$filename = 'vms-pass-tokens';
			if ($batch_id > 0) {
				$filename .= '-batch-' . $batch_id;
			}
			$filename .= '-' . gmdate('Ymd-His') . '.csv';
			header('Content-Type: text/csv; charset=utf-8');
			header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
		}

		$out = fopen('php://output', 'wb');
		if ($out === false) {
			wp_die(esc_html__('Could not open export stream.', 'backstage-venue-manager'));
		}

		fputcsv($out, array(
			'token_id',
			'batch_id',
			'batch_name',
			'status',
			'token_masked',
			'claim_url',
			'claimer_first_name',
			'claimer_last_name',
			'claimer_phone',
			'claimer_email',
			'event_plan_id',
			'event_title',
			'event_date',
			'reservation_entry_id',
			'claimed_at',
			'created_at',
		));

		foreach ($rows as $row) {
			$raw_token = vms_pass_claims_build_raw_token($row);
			$masked = vms_pass_claims_mask_token($raw_token);
			$claim_url = vms_pass_claims_build_claim_url($row);
			$event_plan_id = (int) ($row['event_plan_id'] ?? 0);
			$event_title = $event_plan_id > 0 ? (string) get_the_title($event_plan_id) : '';
			$event_date = $event_plan_id > 0 ? (string) get_post_meta($event_plan_id, '_vms_event_date', true) : '';
			fputcsv($out, array(
				(int) ($row['id'] ?? 0),
				(int) ($row['batch_id'] ?? 0),
				(string) ($row['batch_name'] ?? ''),
				(string) ($row['status'] ?? ''),
				$masked,
				$claim_url,
				(string) ($row['first_name'] ?? ''),
				(string) ($row['last_name'] ?? ''),
				(string) ($row['phone'] ?? ''),
				(string) ($row['email'] ?? ''),
				$event_plan_id,
				$event_title,
				$event_date,
				(int) ($row['reservation_entry_id'] ?? 0),
				(string) ($row['claimed_at'] ?? ''),
				(string) ($row['created_at'] ?? ''),
			));
		}
		fclose($out); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the bounded administrator CSV response stream opened on php://output; no local filesystem path or WP_Filesystem replacement applies to this HTTP output handle.

		if (function_exists('vms_admission_audit_log')) {
			vms_admission_audit_log(0, null, 'pass_tokens_export_csv', get_current_user_id(), 'admin', array(
				'batch_id' => $batch_id,
				'row_count' => count($rows),
			));
		}
		exit;
	}
}
add_action('admin_post_vms_pass_export_csv', 'vms_pass_claims_handle_export_csv');

if (!function_exists('vms_pass_claims_handle_report_export_csv')) {
	function vms_pass_claims_handle_report_export_csv(): void
	{
		if (!current_user_can(vms_pass_claims_capability())) {
			wp_die(esc_html__('Access denied.', 'backstage-venue-manager'));
		}

		$scope = isset($_REQUEST['scope']) ? sanitize_key((string) $_REQUEST['scope']) : 'source';
		if (!in_array($scope, array('source', 'batch', 'source_event', 'event'), true)) {
			$scope = 'source';
		}
		$nonce = (isset($_REQUEST['_wpnonce']) && !is_array($_REQUEST['_wpnonce']))
			? sanitize_text_field(wp_unslash((string) $_REQUEST['_wpnonce']))
			: '';
		if (!wp_verify_nonce($nonce, 'vms_pass_report_export_' . $scope)) {
			wp_die(esc_html__('Invalid request nonce.', 'backstage-venue-manager'));
		}

		$rows = array();
		if ($scope === 'batch') {
			$rows = vms_pass_claims_reports_by_batch();
		} elseif ($scope === 'source_event') {
			$rows = vms_pass_claims_reports_source_events();
		} elseif ($scope === 'event') {
			$rows = vms_pass_claims_reports_by_event();
		} else {
			$rows = vms_pass_claims_reports_by_source();
		}

		if (!headers_sent()) {
			nocache_headers();
			$filename = 'vms-pass-reports-' . $scope . '-' . gmdate('Ymd-His') . '.csv';
			header('Content-Type: text/csv; charset=utf-8');
			header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
		}

		$out = fopen('php://output', 'wb');
		if ($out === false) {
			wp_die(esc_html__('Could not open export stream.', 'backstage-venue-manager'));
		}

		if ($scope === 'batch') {
			fputcsv($out, array(
				'batch_id',
				'batch_name',
				'source_name',
				'validity_type',
				'value_type',
				'value_amount',
				'expires_at',
				'status',
				'tokens_issued',
				'tokens_claimed',
				'claim_rate',
				'tokens_void',
				'reservations_count',
				'checked_in_entries',
				'checked_in_headcount',
				'unique_phones',
				'repeat_claims',
			));
			foreach ($rows as $row) {
				$issued = (int) ($row['tokens_issued'] ?? 0);
				$claimed = (int) ($row['tokens_claimed'] ?? 0);
				fputcsv($out, array(
					(int) ($row['id'] ?? 0),
					(string) ($row['batch_name'] ?? ''),
					(string) ($row['source_name'] ?? ''),
					(string) ($row['validity_type'] ?? ''),
					(string) ($row['value_type'] ?? ''),
					(float) ($row['value_amount'] ?? 0),
					(string) ($row['expires_at'] ?? ''),
					(string) ($row['status'] ?? ''),
					$issued,
					$claimed,
					vms_pass_claims_format_claim_rate($claimed, $issued),
					(int) ($row['tokens_void'] ?? 0),
					(int) ($row['reservations_count'] ?? 0),
					(int) ($row['checked_in_entries'] ?? 0),
					(int) ($row['checked_in_headcount'] ?? 0),
					(int) ($row['unique_phones'] ?? 0),
					(int) ($row['repeat_claims'] ?? 0),
				));
			}
		} elseif ($scope === 'source_event') {
			fputcsv($out, array(
				'source_id',
				'source_name',
				'event_plan_id',
				'event_title',
				'event_date',
				'reservations_count',
				'checked_in_entries',
				'checked_in_headcount',
				'unique_phones',
				'repeat_claims',
			));
			foreach ($rows as $row) {
				$event_plan_id = (int) ($row['event_plan_id'] ?? 0);
				$event_ctx = vms_pass_claims_event_context_for_reports($event_plan_id);
				fputcsv($out, array(
					(int) ($row['source_id'] ?? 0),
					(string) ($row['source_name'] ?? ''),
					$event_plan_id,
					(string) ($event_ctx['title'] ?? ''),
					(string) ($event_ctx['date'] ?? ''),
					(int) ($row['reservations_count'] ?? 0),
					(int) ($row['checked_in_entries'] ?? 0),
					(int) ($row['checked_in_headcount'] ?? 0),
					(int) ($row['unique_phones'] ?? 0),
					(int) ($row['repeat_claims'] ?? 0),
				));
			}
		} elseif ($scope === 'event') {
			fputcsv($out, array(
				'event_plan_id',
				'event_title',
				'event_date',
				'reservations_count',
				'source_count',
				'batch_count',
				'checked_in_entries',
				'checked_in_headcount',
				'unique_phones',
				'repeat_claims',
			));
			foreach ($rows as $row) {
				$event_plan_id = (int) ($row['event_plan_id'] ?? 0);
				$event_ctx = vms_pass_claims_event_context_for_reports($event_plan_id);
				fputcsv($out, array(
					$event_plan_id,
					(string) ($event_ctx['title'] ?? ''),
					(string) ($event_ctx['date'] ?? ''),
					(int) ($row['reservations_count'] ?? 0),
					(int) ($row['source_count'] ?? 0),
					(int) ($row['batch_count'] ?? 0),
					(int) ($row['checked_in_entries'] ?? 0),
					(int) ($row['checked_in_headcount'] ?? 0),
					(int) ($row['unique_phones'] ?? 0),
					(int) ($row['repeat_claims'] ?? 0),
				));
			}
		} else {
			fputcsv($out, array(
				'source_id',
				'source_name',
				'batches_count',
				'tokens_issued',
				'tokens_claimed',
				'claim_rate',
				'reservations_count',
				'checked_in_entries',
				'checked_in_headcount',
				'unique_phones',
				'repeat_claims',
			));
			foreach ($rows as $row) {
				$issued = (int) ($row['tokens_issued'] ?? 0);
				$claimed = (int) ($row['tokens_claimed'] ?? 0);
				fputcsv($out, array(
					(int) ($row['id'] ?? 0),
					(string) ($row['source_name'] ?? ''),
					(int) ($row['batches_count'] ?? 0),
					$issued,
					$claimed,
					vms_pass_claims_format_claim_rate($claimed, $issued),
					(int) ($row['reservations_count'] ?? 0),
					(int) ($row['checked_in_entries'] ?? 0),
					(int) ($row['checked_in_headcount'] ?? 0),
					(int) ($row['unique_phones'] ?? 0),
					(int) ($row['repeat_claims'] ?? 0),
				));
			}
		}

		fclose($out); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the bounded administrator CSV response stream opened on php://output; no local filesystem path or WP_Filesystem replacement applies to this HTTP output handle.

		if (function_exists('vms_admission_audit_log')) {
			vms_admission_audit_log(0, null, 'pass_reports_export_csv', get_current_user_id(), 'admin', array(
				'scope' => $scope,
				'row_count' => count($rows),
			));
		}
		exit;
	}
}
add_action('admin_post_vms_pass_report_export_csv', 'vms_pass_claims_handle_report_export_csv');

if (!function_exists('vms_pass_claims_render_admin_notices')) {
	function vms_pass_claims_render_admin_notices(): void
	{
		$result = vms_request_read_key($_GET, 'result'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive read-only admin notice state remains bookmarkable and does not require a nonce.
		if ($result === 'source_saved') {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Source saved.', 'backstage-venue-manager') . '</p></div>';
		}
		if ($result === 'batch_preview') {
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__('Preview generated. Review sample URLs below before committing.', 'backstage-venue-manager') . '</p></div>';
		}
		if ($result === 'batch_generated') {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Batch created and passes generated.', 'backstage-venue-manager') . '</p></div>';
		}
		if ($result === 'token_voided') {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Pass voided.', 'backstage-venue-manager') . '</p></div>';
		}
		if ($result === 'token_restored') {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Pass restored to unclaimed.', 'backstage-venue-manager') . '</p></div>';
		}

		$msg = vms_pass_claims_pop_user_message();
		if (!empty($msg['message'])) {
			$class = (!empty($msg['type']) && $msg['type'] === 'error') ? 'notice notice-error' : 'notice notice-info';
			echo '<div class="' . esc_attr($class) . ' is-dismissible"><p>' . esc_html((string) $msg['message']) . '</p></div>';
		}
	}
}

if (!function_exists('vms_pass_claims_render_tab_nav')) {
	function vms_pass_claims_render_tab_nav(string $tab): void
	{
		$tabs = array(
			'sources' => __('Sources', 'backstage-venue-manager'),
			'batches' => __('Batches', 'backstage-venue-manager'),
			'passes' => __('Guest Passes', 'backstage-venue-manager'),
			'reports' => __('Reports', 'backstage-venue-manager'),
		);
		echo '<nav class="vms-pass-tabs" aria-label="Guest Passes sections">';
		foreach ($tabs as $key => $label) {
			$url = vms_pass_claims_admin_page_url(array('tab' => $key));
			$class = 'vms-pass-tab';
			if ($tab === $key) {
				$class .= ' is-current';
			}
			echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '"';
			if ($tab === $key) {
				echo ' aria-current="page"';
			}
			echo '>' . esc_html($label) . '</a>';
		}
		echo '</nav>';
	}
}

if (!function_exists('vms_pass_claims_render_sources_tab')) {
	function vms_pass_claims_render_sources_tab(): void
	{
		$sources = vms_pass_claims_get_sources(true);

		echo '<section class="vms-pass-card">';
		echo '<h2>' . esc_html__('Create Source', 'backstage-venue-manager') . '</h2>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-form">';
		echo '<input type="hidden" name="action" value="vms_pass_source_save">';
		wp_nonce_field('vms_pass_source_save');
		echo '<div class="vms-pass-grid">';
		echo '<label>' . esc_html__('Source Name', 'backstage-venue-manager') . '<input type="text" name="source_name" required></label>';
		echo '<label>' . esc_html__('Contact Name', 'backstage-venue-manager') . '<input type="text" name="contact_name"></label>';
		echo '<label>' . esc_html__('Phone', 'backstage-venue-manager') . '<input type="text" name="phone"></label>';
		echo '<label>' . esc_html__('Email', 'backstage-venue-manager') . '<input type="email" name="email"></label>';
		echo '<label class="vms-pass-span-2">' . esc_html__('Notes', 'backstage-venue-manager') . '<textarea name="notes" rows="3"></textarea></label>';
		echo '</div>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__('Save Source', 'backstage-venue-manager') . '</button></p>';
		echo '</form>';
		echo '</section>';

		echo '<section class="vms-pass-card">';
		echo '<h2>' . esc_html__('Sources', 'backstage-venue-manager') . '</h2>';
		echo '<table class="widefat striped">';
		echo '<thead><tr><th>' . esc_html__('Name', 'backstage-venue-manager') . '</th><th>' . esc_html__('Contact', 'backstage-venue-manager') . '</th><th>' . esc_html__('Phone', 'backstage-venue-manager') . '</th><th>' . esc_html__('Email', 'backstage-venue-manager') . '</th><th>' . esc_html__('Status', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
		if (empty($sources)) {
			echo '<tr><td colspan="5">' . esc_html__('No sources yet.', 'backstage-venue-manager') . '</td></tr>';
		} else {
			foreach ($sources as $source) {
				echo '<tr>';
				echo '<td><strong>' . esc_html((string) ($source['source_name'] ?? '')) . '</strong><div class="description">' . esc_html((string) ($source['notes'] ?? '')) . '</div></td>';
				echo '<td>' . esc_html((string) ($source['contact_name'] ?? '')) . '</td>';
				echo '<td>' . esc_html((string) ($source['phone'] ?? '')) . '</td>';
				echo '<td>' . esc_html((string) ($source['email'] ?? '')) . '</td>';
				echo '<td>' . esc_html((string) ($source['status'] ?? '')) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '</section>';
	}
}


if (!function_exists('vms_pass_claims_preview_payload_for_form')) {
	function vms_pass_claims_preview_payload_for_form(): array
	{
		$user_id = get_current_user_id();
		$preview = $user_id > 0 ? get_transient(vms_pass_claims_preview_transient_key($user_id)) : false;
		if (is_array($preview) && !empty($preview['payload']) && is_array($preview['payload'])) {
			return (array) $preview['payload'];
		}
		$draft = $user_id > 0 ? get_transient(vms_pass_claims_draft_transient_key($user_id)) : false;
		if (is_array($draft)) {
			return $draft;
		}
		return array();
	}
}

if (!function_exists('vms_pass_claims_payload_value')) {
	function vms_pass_claims_payload_value(array $payload, string $key, $default = '')
	{
		return array_key_exists($key, $payload) ? $payload[$key] : $default;
	}
}

if (!function_exists('vms_pass_claims_render_preview_summary')) {
	function vms_pass_claims_render_preview_summary(array $payload): void
	{
		if (empty($payload)) {
			return;
		}
		$source = vms_pass_claims_get_source_by_id((int) ($payload['source_id'] ?? 0));
		$source_name = is_array($source) ? (string) ($source['source_name'] ?? '') : '';
		$event_label = '';
		$event = vms_pass_claims_get_event_plan_brief((int) ($payload['single_event_plan_id'] ?? 0));
		if (is_array($event)) {
			$event_label = (string) ($event['title'] ?? '');
			if (!empty($event['event_date'])) {
				$event_label .= ' (' . (string) $event['event_date'] . ')';
			}
		}
		$validity_labels = vms_pass_claims_validity_labels();
		$value_labels = vms_pass_claims_value_type_labels();
		$status_labels = vms_pass_claims_batch_status_labels();
		$checkin_labels = vms_pass_claims_checkin_open_labels();
		$venue_ids = vms_pass_claims_decode_venue_ids_json((string) ($payload['venue_ids_json'] ?? ''));
		$venue_names = array();
		foreach ((array) $venue_ids as $venue_id) {
			$venue_id = (int) $venue_id;
			if ($venue_id > 0) {
				$venue_names[] = (string) get_the_title($venue_id);
			}
		}
		$rows = array(
			__('Source', 'backstage-venue-manager') => $source_name,
			__('Batch Name', 'backstage-venue-manager') => (string) ($payload['batch_name'] ?? ''),
			__('Claim Links', 'backstage-venue-manager') => (string) (int) ($payload['quantity'] ?? 0),
			__('Admissions per Claimed Link', 'backstage-venue-manager') => (string) (int) ($payload['admissions_per_link'] ?? 1),
			__('Total Admission Cap', 'backstage-venue-manager') => (string) (int) ($payload['total_admission_cap'] ?? 0),
			__('Validity Type', 'backstage-venue-manager') => (string) ($validity_labels[$payload['validity_type'] ?? ''] ?? ($payload['validity_type'] ?? '')),
			__('Single Event', 'backstage-venue-manager') => $event_label,
			__('Start Date', 'backstage-venue-manager') => (string) ($payload['start_date'] ?? ''),
			__('End Date', 'backstage-venue-manager') => (string) ($payload['end_date'] ?? ''),
			__('Season Label', 'backstage-venue-manager') => (string) ($payload['season_label'] ?? ''),
			__('Eligible Venues', 'backstage-venue-manager') => !empty($venue_names) ? implode(', ', $venue_names) : __('Any configured venue', 'backstage-venue-manager'),
			__('Admission Value', 'backstage-venue-manager') => (string) ($value_labels[$payload['value_type'] ?? ''] ?? ($payload['value_type'] ?? '')),
			__('Discount Amount', 'backstage-venue-manager') => (string) ($payload['value_amount'] ?? '0'),
			__('Expiration', 'backstage-venue-manager') => (string) ($payload['expires_at'] ?? ''),
			__('Status', 'backstage-venue-manager') => (string) ($status_labels[$payload['status'] ?? ''] ?? ($payload['status'] ?? '')),
			__('Check-in Opens', 'backstage-venue-manager') => (string) ($checkin_labels[$payload['checkin_open_mode'] ?? ''] ?? ($payload['checkin_open_mode'] ?? '')),
			__('Max Claims per Phone', 'backstage-venue-manager') => (string) (int) ($payload['max_per_phone'] ?? 0),
			__('Max Claims per Email', 'backstage-venue-manager') => (string) (int) ($payload['max_per_email'] ?? 0),
			__('Notes', 'backstage-venue-manager') => (string) ($payload['notes'] ?? ''),
		);
		echo '<div class="vms-pass-preview-summary"><h3>' . esc_html__('Commit will use this exact previewed data', 'backstage-venue-manager') . '</h3><dl>';
		foreach ($rows as $label => $value) {
			if ($value === '') {
				$value = __('Not set', 'backstage-venue-manager');
			}
			echo '<dt>' . esc_html((string) $label) . '</dt><dd>' . esc_html((string) $value) . '</dd>';
		}
		echo '</dl></div>';
	}
}

	if (!function_exists('vms_pass_claims_render_batches_tab')) {
	function vms_pass_claims_render_batches_tab(): void
	{
		$sources = vms_pass_claims_get_sources(false);
		$event_plans = vms_pass_claims_get_published_event_plans(300);
		$form_payload = vms_pass_claims_preview_payload_for_form();
		$venues = get_posts(array(
			'post_type' => 'vms_venue',
			'post_status' => array('publish', 'draft', 'pending', 'private'),
			'posts_per_page' => 300,
			'fields' => 'ids',
			'orderby' => 'title',
			'order' => 'ASC',
		));

		echo '<section class="vms-pass-card">';
		echo '<h2>' . esc_html__('Create Batch', 'backstage-venue-manager') . '</h2>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="vms-pass-form">';
		echo '<input type="hidden" name="action" value="vms_pass_batch_generate">';
		wp_nonce_field('vms_pass_batch_generate');
		echo '<div class="vms-pass-grid">';

		echo '<label>' . esc_html__('Source', 'backstage-venue-manager') . '<select name="source_id" required>';
		echo '<option value="">' . esc_html__('Select source', 'backstage-venue-manager') . '</option>';
		foreach ($sources as $source) {
			$source_id = (int) ($source['id'] ?? 0);
			if ($source_id <= 0) {
				continue;
			}
			echo '<option value="' . esc_attr((string) $source_id) . '"' . selected((int) vms_pass_claims_payload_value($form_payload, 'source_id', 0), $source_id, false) . '>' . esc_html((string) ($source['source_name'] ?? '')) . '</option>';
		}
		echo '</select></label>';

		echo '<label>' . esc_html__('Batch Name', 'backstage-venue-manager') . '<input type="text" name="batch_name" value="' . esc_attr((string) vms_pass_claims_payload_value($form_payload, 'batch_name', '')) . '" required></label>';
		echo '<label>' . esc_html__('Number of Claim Links', 'backstage-venue-manager') . '<input type="number" min="1" max="5000" name="quantity" value="' . esc_attr((string) vms_pass_claims_payload_value($form_payload, 'quantity', 20)) . '" required><span class="description">' . esc_html__('How many unique claim URLs to create.', 'backstage-venue-manager') . '</span></label>';
		echo '<label>' . esc_html__('Admissions per Claimed Link', 'backstage-venue-manager') . '<input type="number" min="1" max="100" name="admissions_per_link" value="' . esc_attr((string) vms_pass_claims_payload_value($form_payload, 'admissions_per_link', 1)) . '" required><span class="description">' . esc_html__('Example: 4 means each claim link can admit up to 4 people.', 'backstage-venue-manager') . '</span></label>';
		echo '<label>' . esc_html__('Total Admission Cap', 'backstage-venue-manager') . '<input type="number" min="0" max="50000" name="total_admission_cap" value="' . esc_attr((string) vms_pass_claims_payload_value($form_payload, 'total_admission_cap', 0)) . '"><span class="description">' . esc_html__('0 uses claim links × admissions per link.', 'backstage-venue-manager') . '</span></label>';

		echo '<label>' . esc_html__('Validity Type', 'backstage-venue-manager') . '<select name="validity_type">';
		foreach (vms_pass_claims_validity_labels() as $key => $label) {
			echo '<option value="' . esc_attr((string) $key) . '"' . selected((string) vms_pass_claims_payload_value($form_payload, 'validity_type', 'single_event'), (string) $key, false) . '>' . esc_html((string) $label) . '</option>';
		}
		echo '</select></label>';

		echo '<label>' . esc_html__('Single Event (if used)', 'backstage-venue-manager') . '<select name="single_event_plan_id">';
		echo '<option value="0">' . esc_html__('Select event', 'backstage-venue-manager') . '</option>';
		foreach ($event_plans as $plan) {
			$plan_id = (int) ($plan['id'] ?? 0);
			if ($plan_id <= 0) {
				continue;
			}
			$label = (string) ($plan['title'] ?? 'Event');
			if (!empty($plan['event_date'])) {
				$label .= ' (' . (string) $plan['event_date'] . ')';
			}
			echo '<option value="' . esc_attr((string) $plan_id) . '"' . selected((int) vms_pass_claims_payload_value($form_payload, 'single_event_plan_id', 0), $plan_id, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select></label>';

		echo '<label>' . esc_html__('Start Date (Date Range / Season)', 'backstage-venue-manager') . '<input type="date" name="start_date" value="' . esc_attr((string) vms_pass_claims_payload_value($form_payload, 'start_date', '')) . '"></label>';
		echo '<label>' . esc_html__('End Date (Date Range / Season)', 'backstage-venue-manager') . '<input type="date" name="end_date" value="' . esc_attr((string) vms_pass_claims_payload_value($form_payload, 'end_date', '')) . '"></label>';
		echo '<label>' . esc_html__('Season Label (optional)', 'backstage-venue-manager') . '<input type="text" name="season_label" value="' . esc_attr((string) vms_pass_claims_payload_value($form_payload, 'season_label', '')) . '" placeholder="Spring 2026"></label>';

		echo '<label class="vms-pass-span-2">' . esc_html__('Eligible Venues (optional)', 'backstage-venue-manager') . '<select name="venue_ids[]" multiple size="4">';
			$selected_venues = vms_pass_claims_decode_venue_ids_json((string) vms_pass_claims_payload_value($form_payload, 'venue_ids_json', ''));
			foreach ((array) $venues as $venue_id) {
			$venue_id = (int) $venue_id;
			if ($venue_id <= 0) {
				continue;
			}
			echo '<option value="' . esc_attr((string) $venue_id) . '"' . selected(in_array($venue_id, array_map('intval', (array) $selected_venues), true), true, false) . '>' . esc_html((string) get_the_title($venue_id)) . '</option>';
		}
		echo '</select><span class="description">' . esc_html__('Hold Ctrl/Cmd to select multiple.', 'backstage-venue-manager') . '</span></label>';

		echo '<label>' . esc_html__('Admission Value', 'backstage-venue-manager') . '<select name="value_type">';
		foreach (vms_pass_claims_value_type_labels() as $key => $label) {
			echo '<option value="' . esc_attr((string) $key) . '"' . selected((string) vms_pass_claims_payload_value($form_payload, 'value_type', 'free'), (string) $key, false) . '>' . esc_html((string) $label) . '</option>';
		}
		echo '</select></label>';
		echo '<label>' . esc_html__('Discount Amount', 'backstage-venue-manager') . '<input type="number" min="0" step="0.01" name="value_amount" value="' . esc_attr((string) vms_pass_claims_payload_value($form_payload, 'value_amount', 0)) . '"></label>';
		echo '<label>' . esc_html__('Expiration', 'backstage-venue-manager') . '<input type="datetime-local" name="expires_at" value="' . esc_attr(vms_pass_claims_format_local_datetime_input((string) vms_pass_claims_payload_value($form_payload, 'expires_at', ''))) . '"></label>';

		echo '<label>' . esc_html__('Status', 'backstage-venue-manager') . '<select name="status">';
		foreach (vms_pass_claims_batch_status_labels() as $key => $label) {
			echo '<option value="' . esc_attr((string) $key) . '"' . selected((string) vms_pass_claims_payload_value($form_payload, 'status', 'active'), (string) $key, false) . '>' . esc_html((string) $label) . '</option>';
		}
		echo '</select></label>';

		echo '<label>' . esc_html__('Check-in Opens', 'backstage-venue-manager') . '<select name="checkin_open_mode">';
		foreach (vms_pass_claims_checkin_open_labels() as $key => $label) {
			echo '<option value="' . esc_attr((string) $key) . '"' . selected((string) vms_pass_claims_payload_value($form_payload, 'checkin_open_mode', 'same_day'), (string) $key, false) . '>' . esc_html((string) $label) . '</option>';
		}
		echo '</select></label>';
		echo '<label>' . esc_html__('Max Claims per Phone (0 = unlimited)', 'backstage-venue-manager') . '<input type="number" min="0" max="50" name="max_per_phone" value="' . esc_attr((string) vms_pass_claims_payload_value($form_payload, 'max_per_phone', 0)) . '"></label>';
		echo '<label>' . esc_html__('Max Claims per Email (0 = unlimited)', 'backstage-venue-manager') . '<input type="number" min="0" max="50" name="max_per_email" value="' . esc_attr((string) vms_pass_claims_payload_value($form_payload, 'max_per_email', 0)) . '"></label>';
		echo '<label class="vms-pass-span-2">' . esc_html__('Notes', 'backstage-venue-manager') . '<textarea name="notes" rows="3">' . esc_textarea((string) vms_pass_claims_payload_value($form_payload, 'notes', '')) . '</textarea></label>';

		echo '</div>';
		echo '<p class="vms-pass-actions">';
		echo '<button type="submit" class="button" name="generation_mode" value="preview">' . esc_html__('Preview', 'backstage-venue-manager') . '</button> ';
		echo '<button type="submit" class="button button-primary" name="generation_mode" value="commit">' . esc_html__('Commit + Generate Guest Passes', 'backstage-venue-manager') . '</button>';
		echo '</p>';
		echo '</form>';
		echo '</section>';

		$user_id = get_current_user_id();
		$preview = get_transient(vms_pass_claims_preview_transient_key($user_id));
		if (is_array($preview) && !empty($preview['samples'])) {
			echo '<section class="vms-pass-card">';
			echo '<h2>' . esc_html__('Preview Samples', 'backstage-venue-manager') . '</h2>';
			vms_pass_claims_render_preview_summary((array) ($preview['payload'] ?? array()));
			echo '<ul class="vms-pass-url-list">';
			foreach ((array) $preview['samples'] as $sample) {
				$url = (string) ($sample['claim_url'] ?? '');
				if ($url === '') {
					continue;
				}
				echo '<li><code>' . esc_html($url) . '</code></li>';
			}
			echo '</ul>';
			echo '</section>';
		}

		$generated = get_transient(vms_pass_claims_generated_transient_key($user_id));
		if (is_array($generated) && !empty($generated['samples'])) {
			echo '<section class="vms-pass-card">';
			echo '<h2>' . esc_html__('Latest Generated Batch', 'backstage-venue-manager') . '</h2>';
			/* translators: %d: number of items described in this message. */
			echo '<p><strong>' . esc_html((string) ($generated['batch_name'] ?? '')) . '</strong> - ' . esc_html(sprintf(__('%d passes generated', 'backstage-venue-manager'), (int) ($generated['generated_count'] ?? 0))) . '</p>';
			echo '<ul class="vms-pass-url-list">';
			foreach ((array) $generated['samples'] as $sample) {
				$url = (string) ($sample['claim_url'] ?? '');
				if ($url === '') {
					continue;
				}
				echo '<li><code>' . esc_html($url) . '</code></li>';
			}
			echo '</ul>';
			echo '</section>';
		}

		$batches = vms_pass_claims_get_batches(200);
		echo '<section class="vms-pass-card">';
		echo '<h2>' . esc_html__('Batches', 'backstage-venue-manager') . '</h2>';
		echo '<table class="widefat striped">';
		echo '<thead><tr><th>' . esc_html__('Batch', 'backstage-venue-manager') . '</th><th>' . esc_html__('Source', 'backstage-venue-manager') . '</th><th>' . esc_html__('Quantity', 'backstage-venue-manager') . '</th><th>' . esc_html__('Generated', 'backstage-venue-manager') . '</th><th>' . esc_html__('Validity', 'backstage-venue-manager') . '</th><th>' . esc_html__('Value', 'backstage-venue-manager') . '</th><th>' . esc_html__('Status', 'backstage-venue-manager') . '</th><th>' . esc_html__('Actions', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
		if (empty($batches)) {
			echo '<tr><td colspan="8">' . esc_html__('No batches yet.', 'backstage-venue-manager') . '</td></tr>';
		} else {
			$validity_labels = vms_pass_claims_validity_labels();
			foreach ($batches as $batch) {
				$batch_id = (int) ($batch['id'] ?? 0);
				$value = (string) ($batch['value_type'] ?? 'free');
				$amount = (float) ($batch['value_amount'] ?? 0);
				$value_label = $value;
				if ($value === 'free') {
					$value_label = 'Free';
				} elseif ($value === 'percent') {
					$value_label = rtrim(rtrim(number_format($amount, 2), '0'), '.') . '%';
				} elseif ($value === 'fixed') {
					$value_label = '$' . number_format($amount, 2);
				}
				$passes_url = vms_pass_claims_admin_page_url(array('tab' => 'passes', 'batch_id' => $batch_id));
				$export_url = vms_pass_claims_export_url($batch_id);
				echo '<tr>';
				echo '<td><strong>' . esc_html((string) ($batch['batch_name'] ?? '')) . '</strong><div class="description">#' . esc_html((string) $batch_id) . '</div></td>';
				echo '<td>' . esc_html((string) ($batch['source_name'] ?? '')) . '</td>';
				echo '<td>' . esc_html((string) (int) ($batch['quantity'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) (int) ($batch['generated_count'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) ($validity_labels[(string) ($batch['validity_type'] ?? '')] ?? (string) ($batch['validity_type'] ?? ''))) . '</td>';
				echo '<td>' . esc_html($value_label) . '</td>';
				echo '<td>' . esc_html((string) ($batch['status'] ?? '')) . '</td>';
				echo '<td><a class="button button-small" href="' . esc_url($passes_url) . '">' . esc_html__('View Guest Passes', 'backstage-venue-manager') . '</a> <a class="button button-small" href="' . esc_url($export_url) . '">' . esc_html__('Export CSV', 'backstage-venue-manager') . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '</section>';
	}
}

	if (!function_exists('vms_pass_claims_render_passes_tab')) {
	function vms_pass_claims_render_passes_tab(): void
	{
		$batch_id = vms_request_read_absint($_GET, 'batch_id'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive read-only batch filtering remains nonce-free while rejecting malformed identifier shapes.
		$tokens = vms_pass_claims_get_tokens($batch_id, 300);
		$export_url = vms_pass_claims_export_url($batch_id);

		echo '<section class="vms-pass-card">';
		echo '<h2>' . esc_html__('Guest Passes', 'backstage-venue-manager') . '</h2>';
		if ($batch_id > 0) {
			/* translators: %d: filtered to batch ID. */
			echo '<p class="description">' . esc_html(sprintf(__('Filtered to Batch #%d', 'backstage-venue-manager'), $batch_id)) . '</p>';
		}
		echo '<p class="vms-pass-actions"><a class="button" href="' . esc_url($export_url) . '">' . esc_html__('Export Current CSV', 'backstage-venue-manager') . '</a></p>';
		echo '<table class="widefat striped">';
		echo '<thead><tr><th>' . esc_html__('Batch', 'backstage-venue-manager') . '</th><th>' . esc_html__('Token', 'backstage-venue-manager') . '</th><th>' . esc_html__('Claim URL', 'backstage-venue-manager') . '</th><th>' . esc_html__('Status', 'backstage-venue-manager') . '</th><th>' . esc_html__('Claimer', 'backstage-venue-manager') . '</th><th>' . esc_html__('Event', 'backstage-venue-manager') . '</th><th>' . esc_html__('Created', 'backstage-venue-manager') . '</th><th>' . esc_html__('Actions', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
		if (empty($tokens)) {
			echo '<tr><td colspan="9">' . esc_html__('No passes found.', 'backstage-venue-manager') . '</td></tr>';
		} else {
			foreach ($tokens as $token_row) {
				$token_id = (int) ($token_row['id'] ?? 0);
				$raw_token = vms_pass_claims_build_raw_token($token_row);
				$claim_url = vms_pass_claims_build_claim_url($token_row);
				$masked = vms_pass_claims_mask_token($raw_token);
				$claim_input_id = 'vms-pass-claim-url-' . $token_id;
				$claimer = trim((string) (($token_row['first_name'] ?? '') . ' ' . ($token_row['last_name'] ?? '')));
				if ($claimer === '') {
					$claimer = (string) ($token_row['phone'] ?? '');
				}
				$status = sanitize_key((string) ($token_row['status'] ?? ''));
				$row_batch_id = (int) ($token_row['batch_id'] ?? 0);
				$event_plan_id = (int) ($token_row['event_plan_id'] ?? 0);
				$event_title = $event_plan_id > 0 ? (string) get_the_title($event_plan_id) : '';
				$event_date = $event_plan_id > 0 ? (string) get_post_meta($event_plan_id, '_vms_event_date', true) : '';
				$event_label = $event_title;
				if ($event_label === '' && $event_plan_id > 0) {
					$event_label = '#' . $event_plan_id;
				}
				if ($event_label !== '' && $event_date !== '') {
					$event_label .= ' (' . $event_date . ')';
				}
				$created_at = (string) ($token_row['created_at'] ?? '');
				echo '<tr>';
				echo '<td><strong>' . esc_html((string) ($token_row['batch_name'] ?? '')) . '</strong><div class="description">#' . esc_html((string) $row_batch_id) . '</div></td>';
				echo '<td><code>' . esc_html($masked) . '</code></td>';
				echo '<td><div class="vms-pass-copy">';
				echo '<input type="text" readonly id="' . esc_attr($claim_input_id) . '" value="' . esc_attr($claim_url) . '">';
				echo '<button type="button" class="button button-small" data-vms-copy="#' . esc_attr($claim_input_id) . '">' . esc_html__('Copy', 'backstage-venue-manager') . '</button>';
				echo '</div></td>';
				echo '<td>' . esc_html($status) . '</td>';
				echo '<td>' . esc_html($claimer) . '</td>';
				echo '<td>' . esc_html($event_label) . '</td>';
				$email_label = '';
				if (!empty($token_row['email'])) {
					$email_label = (string) $token_row['email'];
					if (!empty($token_row['admission_emailed_at'])) {
						$email_label .= ' — sent ' . (string) $token_row['admission_emailed_at'];
					} elseif ($status === 'claimed') {
						$email_label .= ' — not sent';
					}
				} elseif ($status === 'claimed') {
					$email_label = __('No email saved', 'backstage-venue-manager');
				}
				echo '<td>' . esc_html($email_label) . '</td>';
				echo '<td>' . esc_html($created_at) . '</td>';
				echo '<td class="vms-pass-row-actions">';
				if ($status === 'claimed' && $token_id > 0) {
					$entry_id = (int) ($token_row['reservation_entry_id'] ?? 0);
					if ($entry_id > 0 && function_exists('vms_admission_ensure_entry_token') && function_exists('vms_admission_scan_url')) {
						$entry_token = vms_admission_ensure_entry_token($entry_id);
						if ($entry_token !== '') {
							$view_url = function_exists('vms_admission_public_pass_url') ? vms_admission_public_pass_url($entry_token, true) : vms_admission_scan_url($entry_token);
							echo '<a class="button button-small" href="' . esc_url($view_url) . '" target="_blank" rel="noopener">' . esc_html__('View Pass', 'backstage-venue-manager') . '</a> ';
						}
					}
					if (!empty($token_row['email'])) {
						$resend_url = add_query_arg(array('action' => 'vms_pass_resend_email', 'token_id' => $token_id, 'batch_id' => $row_batch_id), admin_url('admin-post.php'));
						$resend_url = wp_nonce_url($resend_url, 'vms_pass_resend_email_' . $token_id);
						echo '<a class="button button-small" href="' . esc_url($resend_url) . '">' . esc_html__('Resend Email', 'backstage-venue-manager') . '</a>';
					}
				} elseif ($status === 'unclaimed' && $token_id > 0) {
					$void_url = add_query_arg(
						array(
							'action' => 'vms_pass_token_status',
							'token_id' => $token_id,
							'status' => 'void',
							'batch_id' => $row_batch_id,
						),
						admin_url('admin-post.php')
					);
					$void_url = wp_nonce_url($void_url, 'vms_pass_token_status_' . $token_id . '_void');
					echo '<a class="button button-small" href="' . esc_url($void_url) . '" onclick="return confirm(' . esc_attr(wp_json_encode(__('Void this pass? Claim link will stop working until restored.', 'backstage-venue-manager'))) . ');">' . esc_html__('Void', 'backstage-venue-manager') . '</a>';
				} elseif ($status === 'void' && $token_id > 0) {
					$restore_url = add_query_arg(
						array(
							'action' => 'vms_pass_token_status',
							'token_id' => $token_id,
							'status' => 'unclaimed',
							'batch_id' => $row_batch_id,
						),
						admin_url('admin-post.php')
					);
					$restore_url = wp_nonce_url($restore_url, 'vms_pass_token_status_' . $token_id . '_unclaimed');
					echo '<a class="button button-small" href="' . esc_url($restore_url) . '" onclick="return confirm(' . esc_attr(wp_json_encode(__('Restore this pass to unclaimed status?', 'backstage-venue-manager'))) . ');">' . esc_html__('Restore', 'backstage-venue-manager') . '</a>';
				} else {
					echo '<span class="description">' . esc_html__('No action', 'backstage-venue-manager') . '</span>';
				}
				echo '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '</section>';
	}
}

if (!function_exists('vms_pass_claims_render_reports_tab')) {
	function vms_pass_claims_render_reports_tab(): void
	{
		$by_source = vms_pass_claims_reports_by_source();
		$by_batch = vms_pass_claims_reports_by_batch();
		$source_events = vms_pass_claims_reports_source_events();
		$by_event = vms_pass_claims_reports_by_event();

		echo '<section class="vms-pass-card">';
		echo '<h2>' . esc_html__('Report: Per Source', 'backstage-venue-manager') . '</h2>';
		echo '<p class="vms-pass-actions"><a class="button" href="' . esc_url(vms_pass_claims_reports_export_url('source')) . '">' . esc_html__('Export Source CSV', 'backstage-venue-manager') . '</a></p>';
		echo '<table class="widefat striped">';
		echo '<thead><tr><th>' . esc_html__('Source', 'backstage-venue-manager') . '</th><th>' . esc_html__('Batches', 'backstage-venue-manager') . '</th><th>' . esc_html__('Passes Issued', 'backstage-venue-manager') . '</th><th>' . esc_html__('Passes Claimed', 'backstage-venue-manager') . '</th><th>' . esc_html__('Claim Rate', 'backstage-venue-manager') . '</th><th>' . esc_html__('Reservations', 'backstage-venue-manager') . '</th><th>' . esc_html__('Checked-In (Entries)', 'backstage-venue-manager') . '</th><th>' . esc_html__('Checked-In (Headcount)', 'backstage-venue-manager') . '</th><th>' . esc_html__('Unique Phones', 'backstage-venue-manager') . '</th><th>' . esc_html__('Repeat Claims', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
		if (empty($by_source)) {
			echo '<tr><td colspan="10">' . esc_html__('No source data yet.', 'backstage-venue-manager') . '</td></tr>';
		} else {
			foreach ($by_source as $row) {
				$issued = (int) ($row['tokens_issued'] ?? 0);
				$claimed = (int) ($row['tokens_claimed'] ?? 0);
				$rate = vms_pass_claims_format_claim_rate($claimed, $issued);
				echo '<tr>';
				echo '<td>' . esc_html((string) ($row['source_name'] ?? '')) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['batches_count'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) $issued) . '</td>';
				echo '<td>' . esc_html((string) $claimed) . '</td>';
				echo '<td>' . esc_html($rate) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['reservations_count'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['checked_in_entries'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['checked_in_headcount'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['unique_phones'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['repeat_claims'] ?? 0)) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '</section>';

		echo '<section class="vms-pass-card">';
		echo '<h2>' . esc_html__('Report: Per Batch', 'backstage-venue-manager') . '</h2>';
		echo '<p class="vms-pass-actions"><a class="button" href="' . esc_url(vms_pass_claims_reports_export_url('batch')) . '">' . esc_html__('Export Batch CSV', 'backstage-venue-manager') . '</a></p>';
		echo '<table class="widefat striped">';
		echo '<thead><tr><th>' . esc_html__('Batch', 'backstage-venue-manager') . '</th><th>' . esc_html__('Source', 'backstage-venue-manager') . '</th><th>' . esc_html__('Issued', 'backstage-venue-manager') . '</th><th>' . esc_html__('Claimed', 'backstage-venue-manager') . '</th><th>' . esc_html__('Claim Rate', 'backstage-venue-manager') . '</th><th>' . esc_html__('Reservations', 'backstage-venue-manager') . '</th><th>' . esc_html__('Checked-In (Entries)', 'backstage-venue-manager') . '</th><th>' . esc_html__('Checked-In (Headcount)', 'backstage-venue-manager') . '</th><th>' . esc_html__('Unique Phones', 'backstage-venue-manager') . '</th><th>' . esc_html__('Repeat Claims', 'backstage-venue-manager') . '</th><th>' . esc_html__('Void', 'backstage-venue-manager') . '</th><th>' . esc_html__('Expires', 'backstage-venue-manager') . '</th><th>' . esc_html__('Status', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
		if (empty($by_batch)) {
			echo '<tr><td colspan="13">' . esc_html__('No batch data yet.', 'backstage-venue-manager') . '</td></tr>';
		} else {
			foreach ($by_batch as $row) {
				$issued = (int) ($row['tokens_issued'] ?? 0);
				$claimed = (int) ($row['tokens_claimed'] ?? 0);
				echo '<tr>';
				echo '<td><strong>' . esc_html((string) ($row['batch_name'] ?? '')) . '</strong><div class="description">#' . esc_html((string) (int) ($row['id'] ?? 0)) . '</div></td>';
				echo '<td>' . esc_html((string) ($row['source_name'] ?? '')) . '</td>';
				echo '<td>' . esc_html((string) $issued) . '</td>';
				echo '<td>' . esc_html((string) $claimed) . '</td>';
				echo '<td>' . esc_html(vms_pass_claims_format_claim_rate($claimed, $issued)) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['reservations_count'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['checked_in_entries'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['checked_in_headcount'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['unique_phones'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['repeat_claims'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['tokens_void'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) ($row['expires_at'] ?? '')) . '</td>';
				echo '<td>' . esc_html((string) ($row['status'] ?? '')) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '</section>';

		echo '<section class="vms-pass-card">';
		echo '<h2>' . esc_html__('Report: Source Reservations by Event', 'backstage-venue-manager') . '</h2>';
		echo '<p class="vms-pass-actions"><a class="button" href="' . esc_url(vms_pass_claims_reports_export_url('source_event')) . '">' . esc_html__('Export Source/Event CSV', 'backstage-venue-manager') . '</a></p>';
		echo '<table class="widefat striped">';
		echo '<thead><tr><th>' . esc_html__('Source', 'backstage-venue-manager') . '</th><th>' . esc_html__('Event', 'backstage-venue-manager') . '</th><th>' . esc_html__('Reservations', 'backstage-venue-manager') . '</th><th>' . esc_html__('Checked-In (Entries)', 'backstage-venue-manager') . '</th><th>' . esc_html__('Checked-In (Headcount)', 'backstage-venue-manager') . '</th><th>' . esc_html__('Unique Phones', 'backstage-venue-manager') . '</th><th>' . esc_html__('Repeat Claims', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
		if (empty($source_events)) {
			echo '<tr><td colspan="7">' . esc_html__('No source/event attribution data yet.', 'backstage-venue-manager') . '</td></tr>';
		} else {
			foreach ($source_events as $row) {
				$event_plan_id = (int) ($row['event_plan_id'] ?? 0);
				$event_ctx = vms_pass_claims_event_context_for_reports($event_plan_id);
				$event_label = (string) ($event_ctx['title'] ?? '');
				if ($event_label === '' && $event_plan_id > 0) {
					$event_label = '#' . $event_plan_id;
				}
				$event_date = (string) ($event_ctx['date'] ?? '');
				if ($event_label !== '' && $event_date !== '') {
					$event_label .= ' (' . $event_date . ')';
				}

				echo '<tr>';
				echo '<td>' . esc_html((string) ($row['source_name'] ?? '')) . '</td>';
				echo '<td>' . esc_html($event_label) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['reservations_count'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['checked_in_entries'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['checked_in_headcount'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['unique_phones'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['repeat_claims'] ?? 0)) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '</section>';

		echo '<section class="vms-pass-card">';
		echo '<h2>' . esc_html__('Report: Per Event', 'backstage-venue-manager') . '</h2>';
		echo '<p class="vms-pass-actions"><a class="button" href="' . esc_url(vms_pass_claims_reports_export_url('event')) . '">' . esc_html__('Export Event CSV', 'backstage-venue-manager') . '</a></p>';
		echo '<table class="widefat striped">';
		echo '<thead><tr><th>' . esc_html__('Event', 'backstage-venue-manager') . '</th><th>' . esc_html__('Reservations', 'backstage-venue-manager') . '</th><th>' . esc_html__('Sources', 'backstage-venue-manager') . '</th><th>' . esc_html__('Batches', 'backstage-venue-manager') . '</th><th>' . esc_html__('Checked-In (Entries)', 'backstage-venue-manager') . '</th><th>' . esc_html__('Checked-In (Headcount)', 'backstage-venue-manager') . '</th><th>' . esc_html__('Unique Phones', 'backstage-venue-manager') . '</th><th>' . esc_html__('Repeat Claims', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
		if (empty($by_event)) {
			echo '<tr><td colspan="8">' . esc_html__('No event attribution data yet.', 'backstage-venue-manager') . '</td></tr>';
		} else {
			foreach ($by_event as $row) {
				$event_plan_id = (int) ($row['event_plan_id'] ?? 0);
				$event_ctx = vms_pass_claims_event_context_for_reports($event_plan_id);
				$event_label = (string) ($event_ctx['title'] ?? '');
				if ($event_label === '' && $event_plan_id > 0) {
					$event_label = '#' . $event_plan_id;
				}
				$event_date = (string) ($event_ctx['date'] ?? '');
				if ($event_label !== '' && $event_date !== '') {
					$event_label .= ' (' . $event_date . ')';
				}

				echo '<tr>';
				echo '<td>' . esc_html($event_label) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['reservations_count'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['source_count'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['batch_count'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['checked_in_entries'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['checked_in_headcount'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['unique_phones'] ?? 0)) . '</td>';
				echo '<td>' . esc_html((string) (int) ($row['repeat_claims'] ?? 0)) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
		echo '</section>';
	}
}

if (!function_exists('vms_pass_claims_render_admin_page')) {
	function vms_pass_claims_render_admin_page(): void
	{
		if (!current_user_can(vms_pass_claims_capability())) {
			wp_die(esc_html__('You do not have permission to manage Pass Claims.', 'backstage-venue-manager'));
		}

		$tab = vms_request_read_key($_GET, 'tab'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive read-only tab navigation remains nonce-free while rejecting malformed tab values.
		if ($tab === '') {
			$tab = 'sources';
		}
		if (!in_array($tab, array('sources', 'batches', 'passes', 'reports'), true)) {
			$tab = 'sources';
		}

			$content = static function () use ($tab): void {
				vms_pass_claims_render_tab_nav($tab);
				if ($tab === 'sources') {
					vms_pass_claims_render_sources_tab();
					return;
				}
				if ($tab === 'batches') {
					vms_pass_claims_render_batches_tab();
					return;
				}
				if ($tab === 'passes') {
					vms_pass_claims_render_passes_tab();
					return;
				}
				vms_pass_claims_render_reports_tab();
			};

			if (function_exists('vms_admin_ui_render_shell')) {
				vms_admin_ui_render_shell(
					array(
						'title' => __('Guest Passes', 'backstage-venue-manager'),
						'subtitle' => __('Forecast-first pass claims with Source attribution, batch generation, and door check-in parity.', 'backstage-venue-manager'),
						'shell_id' => 'vms-pass-claims-wrap',
						'notices_callback' => 'vms_pass_claims_render_admin_notices',
					),
					$content
				);
				return;
			}

			echo '<div class="wrap">';
			echo '<h1>' . esc_html__('Guest Passes', 'backstage-venue-manager') . '</h1>';
			vms_pass_claims_render_admin_notices();
			$content();
			echo '</div>';
		}
	}

if (!function_exists('vms_pass_claims_admin_enqueue_assets')) {
	function vms_pass_claims_admin_enqueue_assets(): void
	{
		if (!vms_pass_claims_is_admin_page()) {
			return;
		}
		$ver = defined('BVMGR_VERSION') ? BVMGR_VERSION : null;
		wp_enqueue_style(
			'vms-pass-claims-admin',
			BVMGR_PLUGIN_URL . 'assets/css/vms-pass-claims-admin.css',
			array('vms-admin', 'vms-admin-ui'),
			$ver
		);
		wp_enqueue_script(
			'vms-pass-claims-admin',
			BVMGR_PLUGIN_URL . 'assets/js/vms-pass-claims-admin.js',
			array(),
			$ver,
			true
		);
	}
}
add_action('admin_enqueue_scripts', 'vms_pass_claims_admin_enqueue_assets', 50);

if (!function_exists('vms_pass_claims_register_rewrite')) {
	function vms_pass_claims_register_rewrite(): void
	{
		add_rewrite_tag('%vms_pass_claim_token%', '([^&]+)');
		add_rewrite_rule('^pass/claim/([^/]+)/?$', 'index.php?vms_pass_claim_token=$matches[1]', 'top');
	}
}
add_action('init', 'vms_pass_claims_register_rewrite', 30);

if (!function_exists('vms_pass_claims_get_request_token')) {
	function vms_pass_claims_get_request_token(): string
	{
		$token = get_query_var('vms_pass_claim_token');
		if (is_string($token) && $token !== '') {
			return rawurldecode($token);
		}

		$token = '';
		if (array_key_exists('vms_pass_claim_token', $_GET) && is_scalar($_GET['vms_pass_claim_token'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public claim-token fallback preserves the existing token lookup contract without adding a nonce to navigation.
			$raw_token = wp_unslash($_GET['vms_pass_claim_token']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Preserve the exact unslashed claim token before raw decoding and verification.
			if (is_scalar($raw_token)) {
				$token = (string) $raw_token;
			}
		}
		if ($token !== '') {
			return rawurldecode($token);
		}

		$uri = vms_request_current_uri();
		if ($uri !== '' && preg_match('~^/pass/claim/([^/?#]+)~', $uri, $m)) {
			return rawurldecode((string) $m[1]);
		}

		return '';
	}
}

if (!function_exists('vms_pass_claims_find_token_by_raw')) {
	function vms_pass_claims_find_token_by_raw(string $raw_token): ?array
	{
		$raw_token = trim($raw_token);
		if ($raw_token === '' || strpos($raw_token, '.') === false) {
			return null;
		}
		$parts = explode('.', $raw_token, 2);
		if (count($parts) !== 2) {
			return null;
		}
		$public_key = sanitize_key((string) $parts[0]);
		$sig = strtolower(sanitize_text_field((string) $parts[1]));
		if ($public_key === '' || $sig === '') {
			return null;
		}

			global $wpdb;
			$table = vms_admission_table_pass_tokens();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Public pass-token reads target the plugin-owned token table with a %i/%s-prepared identifier and public key so claim validation observes request-fresh token state.
			$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE token_public_key = %s', $table, $public_key), ARRAY_A);
		if (!is_array($row)) {
			return null;
		}

		$expected_sig = vms_pass_claims_token_signature((int) $row['id'], (string) $row['token_public_key'], (int) $row['batch_id'], (string) $row['created_at']);
		if (!hash_equals($expected_sig, $sig)) {
			return null;
		}

		$hash = hash('sha256', $raw_token);
		$stored_hash = (string) ($row['token_hash'] ?? '');
		if ($stored_hash === '' || !hash_equals($stored_hash, $hash)) {
			return null;
		}

		return $row;
	}
}

if (!function_exists('vms_pass_claims_eligible_events_for_batch')) {
	function vms_pass_claims_eligible_events_for_batch(array $batch): array
	{
		$validity_type = sanitize_key((string) ($batch['validity_type'] ?? 'single_event'));
		$venue_ids = vms_pass_claims_decode_venue_ids_json((string) ($batch['venue_ids_json'] ?? ''));

		if ($validity_type === 'single_event') {
			$single = vms_pass_claims_get_event_plan_brief((int) ($batch['single_event_plan_id'] ?? 0));
			return $single ? array($single) : array();
		}

		$today = vms_pass_claims_today();
		$meta_query = array(
			'relation' => 'AND',
			array(
				'key' => function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'status') : '_vms_event_plan_status',
				'value' => 'published',
				'compare' => '=',
			),
			array(
				'key' => '_vms_event_date',
				'value' => $today,
				'compare' => '>=',
				'type' => 'DATE',
			),
		);

		$start_date = (string) ($batch['start_date'] ?? '');
		$end_date = (string) ($batch['end_date'] ?? '');
		if (($validity_type === 'date_range' || $validity_type === 'season') && $start_date !== '') {
			$meta_query[] = array(
				'key' => '_vms_event_date',
				'value' => $start_date,
				'compare' => '>=',
			);
		}
		if (($validity_type === 'date_range' || $validity_type === 'season') && $end_date !== '') {
			$meta_query[] = array(
				'key' => '_vms_event_date',
				'value' => $end_date,
				'compare' => '<=',
			);
		}
		if (!empty($venue_ids)) {
			$meta_query[] = array(
				'key' => '_vms_venue_id',
				'value' => $venue_ids,
				'compare' => 'IN',
			);
		}

			$ids = get_posts(array(
				'post_type' => 'vms_event_plan',
				'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
				'posts_per_page' => 300,
				'fields' => 'ids',
				'meta_key' => '_vms_event_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Eligible-event lists intentionally sort by the existing event-date postmeta contract and remain bounded to the active pass-claim request.
				'orderby' => 'meta_value',
				'order' => 'ASC',
				'meta_query' => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Eligible-event lists intentionally filter by the existing event-date/status/venue postmeta contract and remain bounded to the active pass-claim request.
			));

		$out = array();
		foreach ((array) $ids as $id) {
			$brief = vms_pass_claims_get_event_plan_brief((int) $id);
			if ($brief) {
				$out[] = $brief;
			}
		}
		return $out;
	}
}

if (!function_exists('vms_pass_claims_empty_events_notice')) {
	function vms_pass_claims_empty_events_notice(array $batch): array
	{
		$notice = array(
			'title' => __('No Eligible Events', 'backstage-venue-manager'),
			'message' => __('There are no eligible published events for this pass right now.', 'backstage-venue-manager'),
		);

		if (sanitize_key((string) ($batch['validity_type'] ?? 'single_event')) !== 'single_event') {
			return $notice;
		}

		$event_plan_id = (int) ($batch['single_event_plan_id'] ?? 0);
		if ($event_plan_id <= 0) {
			return $notice;
		}

		$post = get_post($event_plan_id);
		if (!($post instanceof WP_Post) || $post->post_type !== 'vms_event_plan') {
			return $notice;
		}

		$event_date = (string) get_post_meta($event_plan_id, '_vms_event_date', true);
		if ($event_date !== '' && $event_date < vms_pass_claims_today()) {
			return array(
				'title' => __('Event Passed', 'backstage-venue-manager'),
				'message' => __('This event has already passed.', 'backstage-venue-manager'),
			);
		}

		$status_key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'status') : '_vms_event_plan_status';
		if ($status_key === '') {
			$status_key = '_vms_event_plan_status';
		}
		$status = sanitize_key((string) get_post_meta($event_plan_id, $status_key, true));
		if ($status === 'cancelled' || $status === 'canceled') {
			return array(
				'title' => __('Event Cancelled', 'backstage-venue-manager'),
				'message' => __('This event has been cancelled.', 'backstage-venue-manager'),
			);
		}

		return $notice;
	}
}

if (!function_exists('vms_pass_claims_rate_limit_hit')) {
	function vms_pass_claims_rate_limit_hit(string $ip, string $token_public_key): bool
	{
		$bucket = wp_date('YmdHi', time(), wp_timezone());
		$key = 'vms_pass_claim_rl_' . md5($ip . '|' . $token_public_key . '|' . $bucket);
		$count = (int) get_transient($key);
		if ($count >= 20) {
			return true;
		}
		set_transient($key, $count + 1, 70);
		return false;
	}
}

if (!function_exists('vms_pass_claims_lock_token_for_claim')) {
	function vms_pass_claims_lock_token_for_claim(string $tokens_table, int $token_id): int
	{
		if ($token_id <= 0) {
			return 0;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Claim-state locking writes directly to the plugin-owned token table with a %i/%d-prepared identifier and ID so concurrent claim attempts serialize immediately.
		return (int) $wpdb->query($wpdb->prepare("UPDATE %i SET status = 'claiming' WHERE id = %d AND status = 'unclaimed'", $tokens_table, $token_id));
	}
}

if (!function_exists('vms_pass_claims_reset_token_unclaimed')) {
	function vms_pass_claims_reset_token_unclaimed(string $tokens_table, int $token_id): void
	{
		if ($token_id <= 0) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Claim-state rollback writes directly to the plugin-owned token table with a %i/%d-prepared identifier and ID so failed claims release the token immediately.
		$wpdb->query($wpdb->prepare("UPDATE %i SET status = 'unclaimed' WHERE id = %d", $tokens_table, $token_id));
	}
}

if (!function_exists('vms_pass_claims_create_claim')) {
	function vms_pass_claims_create_claim(array $token_row, array $batch, array $event_plan, array $input)
	{
		global $wpdb;
		$tokens_table = vms_admission_table_pass_tokens();
		$claims_table = vms_admission_table_pass_claims();
		$entries_table = vms_admission_table_entries();

		$token_id = (int) ($token_row['id'] ?? 0);
			if ($token_id <= 0) {
				return new WP_Error('invalid_token', __('Invalid pass token.', 'backstage-venue-manager'));
			}

			$lock = vms_pass_claims_lock_token_for_claim($tokens_table, $token_id);
			if ($lock !== 1) {
				return new WP_Error('already_claimed', __('This pass has already been claimed.', 'backstage-venue-manager'));
			}

		$first_name = sanitize_text_field((string) ($input['first_name'] ?? ''));
		$last_name = sanitize_text_field((string) ($input['last_name'] ?? ''));
		$phone = sanitize_text_field((string) ($input['phone'] ?? ''));
		$email = sanitize_email((string) ($input['email'] ?? ''));
		$phone_norm = vms_pass_claims_normalize_phone($phone);
		$opt_in = !empty($input['opt_in']) ? 1 : 0;
		$settings = function_exists('vms_admission_settings') ? vms_admission_settings() : array();
		$global_max_party_size = max(1, (int) ($settings['max_party_size'] ?? 6));
		$batch_max_party_size = max(1, (int) ($batch['admissions_per_link'] ?? 1));
		$max_party_size = max(1, min($global_max_party_size, $batch_max_party_size));
		$party_size = max(1, min($max_party_size, absint((string) ($input['party_size'] ?? '1'))));
		$event_plan_id = (int) ($event_plan['id'] ?? 0);
		$venue_id = (int) ($event_plan['venue_id'] ?? 0);
		$batch_id = (int) ($batch['id'] ?? 0);
		$source_id = (int) ($batch['source_id'] ?? 0);
			$now = vms_admission_now_mysql();

			if ($first_name === '' || $last_name === '' || $phone_norm === '' || $event_plan_id <= 0) {
				vms_pass_claims_reset_token_unclaimed($tokens_table, $token_id);
				return new WP_Error('invalid_claim_input', __('First name, last name, phone, and event are required.', 'backstage-venue-manager'));
			}

			$max_per_phone = max(0, (int) ($batch['max_per_phone'] ?? 0));
			if ($max_per_phone > 0) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Claim-limit counts read the plugin-owned claims table with a %i/%d/%s-prepared identifier and filters so public claims reflect immediate prior submissions.
				$existing = (int) $wpdb->get_var($wpdb->prepare(
					"SELECT COUNT(1) FROM %i WHERE batch_id = %d AND phone_norm = %s",
					$claims_table,
					$batch_id,
					$phone_norm
				));
				if ($existing >= $max_per_phone) {
					vms_pass_claims_reset_token_unclaimed($tokens_table, $token_id);
					return new WP_Error('phone_limit', __('This phone number has reached the claim limit for this pass batch.', 'backstage-venue-manager'));
				}
			}

			$max_per_email = max(0, (int) ($batch['max_per_email'] ?? 0));
			if ($max_per_email > 0 && $email !== '') {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Claim-limit counts read the plugin-owned claims table with a %i/%d/%s-prepared identifier and filters so public claims reflect immediate prior submissions.
				$existing_email = (int) $wpdb->get_var($wpdb->prepare(
					"SELECT COUNT(1) FROM %i WHERE batch_id = %d AND email = %s",
					$claims_table,
					$batch_id,
					$email
				));
				if ($existing_email >= $max_per_email) {
					vms_pass_claims_reset_token_unclaimed($tokens_table, $token_id);
					return new WP_Error('email_limit', __('This email address has reached the claim limit for this pass batch.', 'backstage-venue-manager'));
				}
			}

			$total_admission_cap = max(0, (int) ($batch['total_admission_cap'] ?? 0));
			if ($total_admission_cap > 0) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admission-cap counts read the plugin-owned admissions table with a %i/%d-prepared identifier and batch filter so public claims reflect immediate reservation writes.
				$claimed_headcount = (int) $wpdb->get_var($wpdb->prepare(
					"SELECT COALESCE(SUM(party_size), 0) FROM %i WHERE pass_batch_id = %d AND status <> 'canceled'",
					$entries_table,
					$batch_id
				));
				if (($claimed_headcount + $party_size) > $total_admission_cap) {
					vms_pass_claims_reset_token_unclaimed($tokens_table, $token_id);
					return new WP_Error('batch_capacity_limit', __('This pass batch does not have enough admissions remaining for that group size.', 'backstage-venue-manager'));
				}
			}

			$ip = vms_request_remote_addr();
			$user_agent = vms_request_user_agent();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Public claim creation writes directly to the plugin-owned claims table because no core API exposes this repository.
			$insert_claim = $wpdb->insert(
				$claims_table,
			array(
				'token_id' => $token_id,
				'batch_id' => $batch_id,
				'source_id' => $source_id,
				'event_plan_id' => $event_plan_id,
				'first_name' => $first_name,
				'last_name' => $last_name,
				'phone' => $phone,
				'phone_norm' => $phone_norm,
				'email' => $email,
				'opt_in' => $opt_in,
				'ip' => $ip,
				'user_agent' => $user_agent,
				'created_at' => $now,
			),
			array('%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
			);
			if ($insert_claim === false) {
				vms_pass_claims_reset_token_unclaimed($tokens_table, $token_id);
				return new WP_Error('claim_insert_failed', __('Could not create claim.', 'backstage-venue-manager'));
			}
		$claim_id = (int) $wpdb->insert_id;

		$guest_name = trim($first_name . ' ' . $last_name);
		$value_type = sanitize_key((string) ($batch['value_type'] ?? 'free'));
		$value_amount = (float) ($batch['value_amount'] ?? 0);
		$claim_reference = 'pc:' . $token_id;
		$notes = sprintf('Pass claim from batch #%d. Party size: %d.', $batch_id, $party_size);

		$entry_ids = array();
		$admission_tokens = array();
			for ($slot = 1; $slot <= $party_size; $slot += 1) {
				$slot_reference = $party_size > 1 ? sprintf('pc:%d:%d', $token_id, $slot) : $claim_reference;
				$slot_notes = $party_size > 1
					? sprintf('Pass claim from batch #%1$d. Individual pass %2$d of %3$d.', $batch_id, $slot, $party_size)
					: $notes;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Public claim reservation writes directly to the plugin-owned admissions table because no core API exposes this repository.
				$insert_entry = $wpdb->insert(
					$entries_table,
				array(
					'event_plan_id' => $event_plan_id,
					'venue_id' => $venue_id,
					'admission_kind' => 'pass',
					'source' => 'pass_claim',
					'owner_vendor_id' => null,
					'guest_name' => $guest_name,
					'guest_name_norm' => vms_admission_normalize_name($guest_name),
					'guest_email' => $email !== '' ? $email : null,
					'guest_email_norm' => $email !== '' ? vms_admission_normalize_email($email) : null,
					'party_size' => 1,
					'checked_in_qty' => 0,
					'phone' => $phone,
					'phone_norm' => $phone_norm !== '' ? $phone_norm : null,
					'notes' => $slot_notes,
					'status' => 'active',
					'pass_source_id' => $source_id,
					'pass_batch_id' => $batch_id,
					'pass_token_id' => $token_id,
					'pass_claim_id' => $claim_id,
					'discount_type' => $value_type,
					'discount_value' => $value_amount,
					'claim_reference' => $slot_reference,
					'claim_meta' => wp_json_encode(array(
						'first_name' => $first_name,
						'last_name' => $last_name,
						'email' => $email,
						'group_size' => $party_size,
						'group_slot' => $slot,
					)),
					'created_by' => 0,
					'created_at' => $now,
				),
				array('%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%f', '%s', '%s', '%d', '%s')
				);
				if ($insert_entry === false) {
					foreach ($entry_ids as $created_entry_id) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reservation rollback deletes directly from the plugin-owned admissions table so partial public-claim writes are fully reverted in-request.
						$wpdb->delete($entries_table, array('id' => (int) $created_entry_id), array('%d'));
					}
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reservation rollback deletes the plugin-owned claim row directly when an admissions insert fails.
					$wpdb->delete($claims_table, array('id' => $claim_id), array('%d'));
					vms_pass_claims_reset_token_unclaimed($tokens_table, $token_id);
					return new WP_Error('reservation_insert_failed', __('Could not create reservation.', 'backstage-venue-manager'));
				}
			$new_entry_id = (int) $wpdb->insert_id;
			$entry_ids[] = $new_entry_id;
			$new_token = function_exists('vms_admission_ensure_entry_token') ? vms_admission_ensure_entry_token($new_entry_id) : '';
			if ($new_token !== '') {
				$admission_tokens[] = array(
					'entry_id' => $new_entry_id,
					'token' => $new_token,
					'reference' => 'GL-' . $new_entry_id,
					'slot' => $slot,
				);
			}
		}
			$entry_id = (int) ($entry_ids[0] ?? 0);
			$admission_token = (string) ($admission_tokens[0]['token'] ?? '');

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Claim finalization writes the plugin-owned claims table directly so the reservation pointer persists with the freshly created admissions rows.
			$wpdb->update(
				$claims_table,
				array('reservation_entry_id' => $entry_id),
			array('id' => $claim_id),
			array('%d'),
				array('%d')
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Claim finalization writes the plugin-owned token table directly so the claimed state and reservation pointer persist immediately.
			$token_update = $wpdb->update(
				$tokens_table,
				array(
				'status' => 'claimed',
				'claimed_at' => $now,
				'claim_id' => $claim_id,
				'reservation_entry_id' => $entry_id,
			),
			array('id' => $token_id),
			array('%s', '%s', '%d', '%d'),
			array('%d')
			);

			if ($token_update === false) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Claim-finalization rollback writes the plugin-owned token table directly to release the token when the claimed-state write fails.
				$wpdb->update(
					$tokens_table,
					array('status' => 'unclaimed', 'claimed_at' => null, 'claim_id' => null, 'reservation_entry_id' => null),
				array('id' => $token_id),
				array('%s', '%s', '%d', '%d'),
					array('%d')
				);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Claim-finalization rollback deletes the plugin-owned claim row directly when the token state cannot be finalized.
				$wpdb->delete($claims_table, array('id' => $claim_id), array('%d'));
				foreach ($entry_ids as $created_entry_id) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Claim-finalization rollback deletes plugin-owned admissions rows directly when the token state cannot be finalized.
					$wpdb->delete($entries_table, array('id' => (int) $created_entry_id), array('%d'));
				}
				return new WP_Error('token_finalize_failed', __('Could not finalize claim token.', 'backstage-venue-manager'));
		}

		if (function_exists('vms_admission_audit_log')) {
			vms_admission_audit_log($event_plan_id, $entry_id, 'pass_claim', 0, 'public', array(
				'batch_id' => $batch_id,
				'token_id' => $token_id,
				'claim_id' => $claim_id,
				'source_id' => $source_id,
				'party_size' => $party_size,
			));
		}

		$email_sent = false;
		$email_result = array();
		if ($email !== '' && function_exists('vms_admission_email_pass_result')) {
			$email_result = vms_admission_email_pass_result($entry_id, 'guest_pass_claim');
			$email_sent = !empty($email_result['sent']);
		}

		return array(
			'claim_id' => $claim_id,
			'entry_id' => $entry_id,
			'event_plan_id' => $event_plan_id,
			'event_title' => (string) ($event_plan['title'] ?? ''),
			'event_date' => (string) ($event_plan['event_date'] ?? ''),
			'venue_name' => (string) ($event_plan['venue_name'] ?? ''),
			'reference' => 'GL-' . $entry_id,
			'scan_url' => $admission_token !== '' && function_exists('vms_admission_scan_url') ? vms_admission_scan_url($admission_token) : '',
			'admission_token' => $admission_token,
			'admission_tokens' => $admission_tokens,
			'party_size' => $party_size,
			'email_sent' => $email_sent,
			'email_result' => $email_result,
		);
	}
}

if (!function_exists('vms_pass_claims_public_status_allowed_html')) {
	function vms_pass_claims_public_status_allowed_html(): array
	{
		return array(
			'h1' => array(),
			'p' => array(
				'class' => true,
			),
		);
	}
}

if (!function_exists('vms_pass_claims_public_status_fragment')) {
	function vms_pass_claims_public_status_fragment(string $title, string $message): string
	{
		$html = '<h1>' . esc_html($title) . '</h1>';
		$html .= '<p class="vms-pass-error">' . esc_html($message) . '</p>';
		return wp_kses($html, vms_pass_claims_public_status_allowed_html());
	}
}

if (!function_exists('vms_pass_claims_render_public_status_screen')) {
	function vms_pass_claims_render_public_status_screen(string $headline, string $title, string $message): void
	{
		vms_pass_claims_render_public_shell($headline, static function () use ($title, $message): void {
			echo vms_pass_claims_public_status_fragment($title, $message);
		});
	}
}

if (!function_exists('vms_pass_claims_public_claimed_card_html')) {
	function vms_pass_claims_public_claimed_card_html(int $entry_id): string
	{
		$html = '<h1>' . esc_html__('Already Claimed', 'backstage-venue-manager') . '</h1>';
		$html .= '<p class="vms-pass-note">' . esc_html__('This pass has already been claimed.', 'backstage-venue-manager') . '</p>';
		if ($entry_id > 0) {
			$html .= '<p class="vms-pass-meta"><strong>' . esc_html__('Reference:', 'backstage-venue-manager') . '</strong> ' . esc_html('GL-' . $entry_id) . '</p>';
		}
		return $html;
	}
}

if (!function_exists('vms_pass_claims_render_public_claimed_card')) {
	function vms_pass_claims_render_public_claimed_card(int $entry_id): void
	{
		vms_pass_claims_render_public_shell(__('Already Claimed', 'backstage-venue-manager'), static function () use ($entry_id): void {
			echo vms_pass_claims_public_claimed_card_html($entry_id);
		});
	}
}

if (!function_exists('vms_pass_claims_public_success_confirmation_html')) {
	function vms_pass_claims_public_success_confirmation_html(array $success, string $posted_email): string
	{
		$admission_tokens = is_array($success['admission_tokens'] ?? null) ? (array) $success['admission_tokens'] : array();
		$scan_url = (string) ($success['scan_url'] ?? '');
		$qr_url = $scan_url !== '' ? vms_pass_claims_qr_image_url('vms-admission:' . (string) ($success['admission_token'] ?? '')) : '';

		$html = '<h1>' . esc_html__('You Are Confirmed', 'backstage-venue-manager') . '</h1>';
		$html .= '<div class="vms-pass-success">' . esc_html__('Your pass has been claimed and your reservation is confirmed.', 'backstage-venue-manager') . '</div>';
		$html .= '<div class="vms-pass-ticket">';
		$html .= '<h2>' . esc_html(count($admission_tokens) > 1 ? __('Show these passes at the gate', 'backstage-venue-manager') : __('Show this pass at the gate', 'backstage-venue-manager')) . '</h2>';
		if (count($admission_tokens) > 1) {
			$html .= '<p class="vms-pass-hint">' . esc_html__('Each person has their own QR code, so your group can arrive separately.', 'backstage-venue-manager') . '</p>';
			$html .= '<div class="vms-pass-qr-grid">';
			$total = count($admission_tokens);
			foreach ($admission_tokens as $index => $admission_token_row) {
				$token = (string) ($admission_token_row['token'] ?? '');
				if ($token === '') {
					continue;
				}
				$group_qr_url = vms_pass_claims_qr_image_url('vms-admission:' . $token);
				$reference = (string) ($admission_token_row['reference'] ?? ('GL-' . (int) ($admission_token_row['entry_id'] ?? 0)));
				/* translators: 1: number 1 used in this message, 2: number 2 used in this message. */
				$html .= '<div class="vms-pass-qr-item"><strong>' . esc_html(sprintf(__('Pass %1$d of %2$d', 'backstage-venue-manager'), $index + 1, $total)) . '</strong>';
				if ($group_qr_url !== '') {
					$html .= '<img class="vms-pass-qr" src="' . esc_url($group_qr_url) . '" alt="' . esc_attr__('Gate QR code', 'backstage-venue-manager') . '">';
				}
				$html .= '<span>' . esc_html($reference) . '</span></div>';
			}
			$html .= '</div>';
		} elseif ($qr_url !== '') {
			$html .= '<div class="vms-pass-qr-wrap"><img class="vms-pass-qr" src="' . esc_url($qr_url) . '" alt="' . esc_attr__('Gate QR code', 'backstage-venue-manager') . '"></div>';
		}
		$html .= '<p class="vms-pass-meta"><strong>' . esc_html__('Event:', 'backstage-venue-manager') . '</strong> ' . esc_html((string) ($success['event_title'] ?? '')) . '</p>';
		if (!empty($success['event_date'])) {
			$html .= '<p class="vms-pass-meta"><strong>' . esc_html__('Date:', 'backstage-venue-manager') . '</strong> ' . esc_html(vms_pass_claims_format_public_date((string) $success['event_date'])) . '</p>';
		}
		if (!empty($success['venue_name'])) {
			$html .= '<p class="vms-pass-meta"><strong>' . esc_html__('Venue:', 'backstage-venue-manager') . '</strong> ' . esc_html((string) $success['venue_name']) . '</p>';
		}
		if (!empty($success['party_size']) && (int) $success['party_size'] > 1) {
			/* translators: %d: number of items described in this message. */
			$html .= '<p class="vms-pass-meta"><strong>' . esc_html__('Admissions:', 'backstage-venue-manager') . '</strong> ' . esc_html(sprintf(_n('%d individual pass', '%d individual passes', (int) $success['party_size'], 'backstage-venue-manager'), (int) $success['party_size'])) . '</p>';
		}
		$html .= '<p class="vms-pass-meta"><strong>' . esc_html__('Reference:', 'backstage-venue-manager') . '</strong> ' . esc_html((string) ($success['reference'] ?? '')) . '</p>';
		if ($scan_url !== '') {
			$pass_url = !empty($success['admission_token']) && function_exists('vms_admission_public_pass_url') ? vms_admission_public_pass_url((string) $success['admission_token'], true) : $scan_url;
			$html .= '<p class="vms-pass-actions"><a class="vms-pass-button" href="' . esc_url($pass_url) . '" target="_blank" rel="noopener">' . esc_html__('View / Print Passes', 'backstage-venue-manager') . '</a></p>';
		}
		$html .= '<p class="vms-pass-hint">' . esc_html__('Screenshot this page or open it at the gate. Door staff can scan each QR code or search your name/phone.', 'backstage-venue-manager') . '</p>';
		$html .= '</div>';
		if (!empty($success['email_sent'])) {
			$html .= '<p class="vms-pass-note">' . esc_html__('We also emailed a copy of this pass to the email address you entered.', 'backstage-venue-manager') . '</p>';
		} elseif ($posted_email !== '') {
			$email_result = isset($success['email_result']) && is_array($success['email_result']) ? $success['email_result'] : array();
			$reason = isset($email_result['message']) ? (string) $email_result['message'] : '';
			$message = $reason !== ''
				/* translators: %s: human-readable pass email delivery failure reason. */
				? sprintf(__('Your pass is confirmed, but the email was not sent: %s. Screenshot this page and use it at the gate.', 'backstage-venue-manager'), $reason)
				: __('Your pass is confirmed, but the email was not sent. Screenshot this page and use it at the gate.', 'backstage-venue-manager');
			$html .= '<p class="vms-pass-note vms-pass-note-warning">' . esc_html($message) . '</p>';
		}

		return $html;
	}
}

if (!function_exists('vms_pass_claims_render_public_success_confirmation')) {
	function vms_pass_claims_render_public_success_confirmation(array $success, string $posted_email): void
	{
		vms_pass_claims_render_public_shell(__('Pass Claimed', 'backstage-venue-manager'), static function () use ($success, $posted_email): void {
			echo vms_pass_claims_public_success_confirmation_html($success, $posted_email);
		});
	}
}

if (!function_exists('vms_pass_claims_public_form_html')) {
	function vms_pass_claims_public_form_html(array $batch, array $eligible_events, array $posted, string $error, int $max_party_size): string
	{
		$html = '<h1>' . esc_html__('Claim Your Pass', 'backstage-venue-manager') . '</h1>';
		$html .= '<p class="vms-pass-meta">' . esc_html__('Complete this claim before arrival. Door staff can only check in claimed reservations.', 'backstage-venue-manager') . '</p>';
		$html .= '<p class="vms-pass-note"><strong>' . esc_html__('Batch:', 'backstage-venue-manager') . '</strong> ' . esc_html((string) ($batch['batch_name'] ?? '')) . '</p>';
		if ($error !== '') {
			$html .= '<p class="vms-pass-error">' . esc_html($error) . '</p>';
		}

		$html .= '<form method="post">';
		$html .= wp_nonce_field('vms_pass_claim_submit', '_vms_pass_claim_nonce', true, false);
		$html .= '<div class="vms-pass-grid">';
		$html .= '<label>' . esc_html__('First Name', 'backstage-venue-manager') . '<input type="text" name="first_name" value="' . esc_attr((string) ($posted['first_name'] ?? '')) . '" required></label>';
		$html .= '<label>' . esc_html__('Last Name', 'backstage-venue-manager') . '<input type="text" name="last_name" value="' . esc_attr((string) ($posted['last_name'] ?? '')) . '" required></label>';
		$html .= '<label>' . esc_html__('Phone', 'backstage-venue-manager') . '<input type="text" name="phone" value="' . esc_attr((string) ($posted['phone'] ?? '')) . '" required></label>';
		$html .= '<label>' . esc_html__('Email (optional)', 'backstage-venue-manager') . '<input type="email" name="email" value="' . esc_attr((string) ($posted['email'] ?? '')) . '"></label>';
		$html .= '<label class="vms-pass-span-2">' . esc_html__('Select Event', 'backstage-venue-manager') . '<select name="event_plan_id" required>';
		$html .= '<option value="">' . esc_html__('Choose an event', 'backstage-venue-manager') . '</option>';
		foreach ($eligible_events as $event) {
			$event_id = (int) ($event['id'] ?? 0);
			$label = (string) ($event['title'] ?? 'Event');
			if (!empty($event['event_date'])) {
				$label .= ' (' . vms_pass_claims_format_public_date((string) $event['event_date']) . ')';
			}
			if (!empty($event['venue_name'])) {
				$label .= ' - ' . (string) $event['venue_name'];
			}
			$html .= '<option value="' . esc_attr((string) $event_id) . '"' . selected((int) ($posted['event_plan_id'] ?? 0), $event_id, false) . '>' . esc_html($label) . '</option>';
		}
		$html .= '</select></label>';
		/* translators: %d: maximum number of people this pass link can admit. */
		$html .= '<label class="vms-pass-span-2">' . esc_html__('How many people will use this pass?', 'backstage-venue-manager') . '<span class="vms-pass-number-control"><button type="button" class="vms-pass-number-control__button" data-vms-pass-party-decrease aria-label="' . esc_attr__('Decrease party size', 'backstage-venue-manager') . '">-</button><input type="number" name="party_size" min="1" max="' . esc_attr((string) $max_party_size) . '" step="1" inputmode="numeric" data-vms-pass-party-size value="' . esc_attr((string) max(1, min($max_party_size, (int) ($posted['party_size'] ?? 1)))) . '"><button type="button" class="vms-pass-number-control__button" data-vms-pass-party-increase aria-label="' . esc_attr__('Increase party size', 'backstage-venue-manager') . '">+</button></span><span class="vms-pass-field-help">' . esc_html(sprintf(__('This link can admit up to %d people.', 'backstage-venue-manager'), $max_party_size)) . '</span></label>';
		$html .= '<label class="vms-pass-span-2 vms-pass-checkbox"><input type="checkbox" name="opt_in" value="1"' . checked(1, (int) ($posted['opt_in'] ?? 0), false) . '> <span>' . esc_html__('Send me event updates and reminders (optional). Your pass email is sent automatically if you enter an email.', 'backstage-venue-manager') . '</span></label>';
		$html .= '</div>';
		$html .= '<p class="vms-pass-actions"><button type="submit" name="vms_pass_claim_submit" value="1">' . esc_html__('Claim Pass', 'backstage-venue-manager') . '</button></p>';
		$html .= '</form>';

		return $html;
	}
}

if (!function_exists('vms_pass_claims_render_public_form')) {
	function vms_pass_claims_render_public_form(array $batch, array $eligible_events, array $posted, string $error, int $max_party_size): void
	{
		vms_pass_claims_render_public_shell(__('Claim Your Pass', 'backstage-venue-manager'), static function () use ($batch, $eligible_events, $posted, $error, $max_party_size): void {
			echo vms_pass_claims_public_form_html($batch, $eligible_events, $posted, $error, $max_party_size);
		});
	}
}

if (!function_exists('vms_pass_claims_render_public_shell')) {
	/**
	 * Render the public pass shell around a package-owned public-family renderer.
	 *
	 * @param callable():void $render_content Package-owned renderer callback that echoes one accepted Pass Claims public family.
	 */
	function vms_pass_claims_render_public_shell(string $headline, callable $render_content): void
	{
		status_header(200);
		nocache_headers();

		add_filter('document_title_parts', static function (array $parts) use ($headline): array {
			$parts['title'] = $headline;
			return $parts;
		}, 20);

		if (function_exists('wp_enqueue_style') && defined('BVMGR_PLUGIN_URL')) {
			wp_enqueue_style('vms-pass-claims-public', BVMGR_PLUGIN_URL . 'assets/css/vms-pass-claims-public.css', array(), defined('BVMGR_VERSION') ? BVMGR_VERSION : null);
		}
		if (function_exists('wp_enqueue_script') && defined('BVMGR_PLUGIN_URL')) {
			wp_enqueue_script('vms-pass-claims-public', BVMGR_PLUGIN_URL . 'assets/js/vms-pass-claims-public.js', array(), defined('BVMGR_VERSION') ? BVMGR_VERSION : null, true);
		}

		if (function_exists('get_header')) {
			get_header();
		} else {
			echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body>';
		}

		echo '<main id="primary" class="site-main vms-pass-public-page" role="main">';
		echo '<div class="vms-pass-wrap"><div class="vms-pass-card">';
		$render_content();
		echo '</div></div>';
		echo '</main>';

		if (function_exists('get_footer')) {
			get_footer();
		} else {
			echo '</body></html>';
		}
		exit;
	}
}

if (!function_exists('vms_pass_claims_render_public_claim')) {
	function vms_pass_claims_render_public_claim(string $raw_token): void
	{
		$token_row = vms_pass_claims_find_token_by_raw($raw_token);
		if (!$token_row) {
			vms_pass_claims_render_public_status_screen(
				__('Claim Pass', 'backstage-venue-manager'),
				__('Pass Not Found', 'backstage-venue-manager'),
				__('This pass link is invalid or has expired.', 'backstage-venue-manager')
			);
		}

		$batch = vms_pass_claims_get_batch_by_id((int) ($token_row['batch_id'] ?? 0));
		if (!$batch) {
			vms_pass_claims_render_public_status_screen(
				__('Claim Pass', 'backstage-venue-manager'),
				__('Batch Not Found', 'backstage-venue-manager'),
				__('This pass batch is no longer available.', 'backstage-venue-manager')
			);
		}

		$batch_status = sanitize_key((string) ($batch['status'] ?? ''));
		$token_status = sanitize_key((string) ($token_row['status'] ?? ''));
		if ($batch_status !== 'active' || $token_status === 'void') {
			vms_pass_claims_render_public_status_screen(
				__('Claim Pass', 'backstage-venue-manager'),
				__('Pass Unavailable', 'backstage-venue-manager'),
				__('This pass is not currently active.', 'backstage-venue-manager')
			);
		}

		$expires_at = trim((string) ($batch['expires_at'] ?? ''));
		// MySQL zero-date values represent "no expiration" for older/default rows.
		if ($expires_at !== '' && strpos($expires_at, '0000-00-00') !== 0) {
			try {
				$expires_dt = new DateTimeImmutable($expires_at, wp_timezone());
				if (time() >= $expires_dt->getTimestamp()) {
					vms_pass_claims_render_public_status_screen(
						__('Claim Pass', 'backstage-venue-manager'),
						__('Pass Expired', 'backstage-venue-manager'),
						__('This pass link has expired.', 'backstage-venue-manager')
					);
				}
			} catch (Exception $e) {
				// Ignore malformed expiration values.
			}
		}

		if ($token_status === 'claimed') {
			vms_pass_claims_render_public_claimed_card((int) ($token_row['reservation_entry_id'] ?? 0));
		}

		$ip = vms_request_remote_addr();
		if ($ip !== '' && vms_pass_claims_rate_limit_hit($ip, (string) ($token_row['token_public_key'] ?? ''))) {
			vms_pass_claims_render_public_status_screen(
				__('Claim Pass', 'backstage-venue-manager'),
				__('Please Wait', 'backstage-venue-manager'),
				__('Too many attempts. Please try again shortly.', 'backstage-venue-manager')
			);
		}

		$eligible_events = vms_pass_claims_eligible_events_for_batch($batch);
		if (empty($eligible_events)) {
			$empty_notice = vms_pass_claims_empty_events_notice($batch);
			vms_pass_claims_render_public_status_screen(
				__('Claim Pass', 'backstage-venue-manager'),
				(string) ($empty_notice['title'] ?? __('No Eligible Events', 'backstage-venue-manager')),
				(string) ($empty_notice['message'] ?? __('There are no eligible published events for this pass right now.', 'backstage-venue-manager'))
			);
		}

		$error = '';
		$success = array();
		$posted = array(
			'first_name' => '',
			'last_name' => '',
			'phone' => '',
			'email' => '',
			'event_plan_id' => '',
			'party_size' => 1,
			'opt_in' => 0,
		);

		if (vms_request_method() === 'post' && isset($_POST['vms_pass_claim_submit'])) {
			$nonce = (isset($_POST['_vms_pass_claim_nonce']) && !is_array($_POST['_vms_pass_claim_nonce']))
				? sanitize_text_field(wp_unslash((string) $_POST['_vms_pass_claim_nonce']))
				: '';
			if (!wp_verify_nonce($nonce, 'vms_pass_claim_submit')) {
				$error = __('Invalid request. Please refresh and try again.', 'backstage-venue-manager');
			} else {
				$posted['first_name'] = sanitize_text_field((string) wp_unslash($_POST['first_name'] ?? ''));
				$posted['last_name'] = sanitize_text_field((string) wp_unslash($_POST['last_name'] ?? ''));
				$posted['phone'] = sanitize_text_field((string) wp_unslash($_POST['phone'] ?? ''));
				$posted['email'] = sanitize_email((string) wp_unslash($_POST['email'] ?? ''));
				$posted['event_plan_id'] = absint((string) wp_unslash($_POST['event_plan_id'] ?? '0'));
				$global_max_party_size = function_exists('vms_admission_settings') ? max(1, (int) (vms_admission_settings()['max_party_size'] ?? 6)) : 6;
				$batch_max_party_size = max(1, (int) ($batch['admissions_per_link'] ?? 1));
				$max_party_size = max(1, min($global_max_party_size, $batch_max_party_size));
				$requested_party_size = absint((string) wp_unslash($_POST['party_size'] ?? '1'));
				$posted['party_size'] = max(1, $requested_party_size);
				if ($requested_party_size < 1 || $requested_party_size > $max_party_size) {
					/* translators: %d: number used in this message. */
					$error = sprintf(__('Party size must be between 1 and %d.', 'backstage-venue-manager'), $max_party_size);
				}
				$posted['opt_in'] = !empty($_POST['opt_in']) ? 1 : 0;

				$selected_event = null;
				foreach ($eligible_events as $event) {
					if ((int) ($event['id'] ?? 0) === (int) $posted['event_plan_id']) {
						$selected_event = $event;
						break;
					}
				}

				if ($error !== '') {
					// Keep the submitted form visible with the validation message.
				} elseif (!$selected_event) {
					$error = __('Please choose a valid event.', 'backstage-venue-manager');
				} else {
					$result = vms_pass_claims_create_claim($token_row, $batch, $selected_event, $posted);
					if (is_wp_error($result)) {
						$error = $result->get_error_message();
					} else {
						$success = is_array($result) ? $result : array();
					}
				}
			}
		}

		$html = '';
		if (!empty($success)) {
			vms_pass_claims_render_public_success_confirmation($success, (string) $posted['email']);
		}

		$global_max_party_size = function_exists('vms_admission_settings') ? max(1, (int) (vms_admission_settings()['max_party_size'] ?? 6)) : 6;
		$batch_max_party_size = max(1, (int) ($batch['admissions_per_link'] ?? 1));
		$max_party_size = max(1, min($global_max_party_size, $batch_max_party_size));

		vms_pass_claims_render_public_form($batch, $eligible_events, $posted, $error, $max_party_size);
	}
}

if (!function_exists('vms_pass_claims_template_router')) {
	function vms_pass_claims_template_router(): void
	{
		if (is_admin()) {
			return;
		}
		$token = vms_pass_claims_get_request_token();
		if ($token === '') {
			return;
		}
		vms_pass_claims_render_public_claim($token);
	}
}
add_action('template_redirect', 'vms_pass_claims_template_router', 0);
