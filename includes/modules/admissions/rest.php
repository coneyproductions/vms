<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_admission_rest_ok')) {
	function vms_admission_rest_ok($data = array())
	{
		return rest_ensure_response(array(
			'ok' => true,
			'data' => $data,
			'error' => null,
		));
	}
}

if (!function_exists('vms_admission_rest_error')) {
	function vms_admission_rest_error(string $code, string $message, int $status = 400, $data = null)
	{
		return new WP_REST_Response(array(
			'ok' => false,
			'data' => $data,
			'error' => array(
				'code' => $code,
				'message' => $message,
			),
		), $status);
	}
}

if (!function_exists('vms_admission_rest_permission_error')) {
	function vms_admission_rest_permission_error(string $code, string $message, int $status = 403)
	{
		return new WP_Error($code, $message, array('status' => $status));
	}
}

if (!function_exists('vms_admission_rest_expired_session_message')) {
	function vms_admission_rest_expired_session_message(): string
	{
		return __('Your Admissions session expired. Refresh the page and try again.', 'backstage-venue-manager');
	}
}

if (!function_exists('vms_admission_rest_request_nonce')) {
	function vms_admission_rest_request_nonce(WP_REST_Request $request): string
	{
		$nonce = '';
		if (method_exists($request, 'get_header')) {
			$header_nonce = $request->get_header('X-WP-Nonce');
			if (is_string($header_nonce)) {
				$nonce = sanitize_text_field($header_nonce);
			}
		}
		if ($nonce === '' && method_exists($request, 'get_param')) {
			$param_nonce = $request->get_param('_wpnonce');
			if (!is_scalar($param_nonce)) {
				return '';
			}
			$nonce = sanitize_text_field((string) $param_nonce);
		}
		if ($nonce === '') {
			return '';
		}
		return $nonce;
	}
}

if (!function_exists('vms_admission_rest_request_has_valid_nonce')) {
	function vms_admission_rest_request_has_valid_nonce(WP_REST_Request $request): bool
	{
		$nonce = vms_admission_rest_request_nonce($request);
		if ($nonce === '') {
			return true;
		}
		return function_exists('wp_verify_nonce') && (bool) wp_verify_nonce($nonce, 'wp_rest');
	}
}

if (!function_exists('vms_admission_rest_can_checkin_request')) {
	function vms_admission_rest_can_checkin_request(WP_REST_Request $request)
	{
		if (!vms_admission_current_user_can_checkin()) {
			return vms_admission_rest_permission_error('vms_admission_forbidden', __('Access denied.', 'backstage-venue-manager'));
		}
		if (!vms_admission_rest_request_has_valid_nonce($request)) {
			return vms_admission_rest_permission_error('vms_admission_bad_nonce', vms_admission_rest_expired_session_message());
		}
		return true;
	}
}

if (!function_exists('vms_admission_rest_can_manage_request')) {
	function vms_admission_rest_can_manage_request(WP_REST_Request $request)
	{
		if (!vms_admission_current_user_can_manage()) {
			return vms_admission_rest_permission_error('vms_admission_forbidden', __('Access denied.', 'backstage-venue-manager'));
		}
		if (!vms_admission_rest_request_has_valid_nonce($request)) {
			return vms_admission_rest_permission_error('vms_admission_bad_nonce', vms_admission_rest_expired_session_message());
		}
		return true;
	}
}

if (!function_exists('vms_admission_event_plan_context')) {
	function vms_admission_event_plan_context(int $event_plan_id): ?array
	{
		$post = get_post($event_plan_id);
		if (!($post instanceof WP_Post) || $post->post_type !== 'vms_event_plan') {
			return null;
		}

		$venue_id = (int) get_post_meta($event_plan_id, '_vms_venue_id', true);
		$venue_name = $venue_id > 0 ? get_the_title($venue_id) : '';
		$event_date = (string) get_post_meta($event_plan_id, '_vms_event_date', true);
		$status_key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'status') : '_vms_event_plan_status';
		if ($status_key === '') {
			$status_key = '_vms_event_plan_status';
		}
		$status = (string) get_post_meta($event_plan_id, $status_key, true);

		return array(
			'event_plan_id' => $event_plan_id,
			'venue_id' => $venue_id,
			'event_date' => $event_date,
			'title' => get_the_title($event_plan_id),
			'venue_name' => $venue_name,
			'status' => $status,
		);
	}
}

if (!function_exists('vms_admission_format_local_datetime')) {
	function vms_admission_format_local_datetime(string $raw): string
	{
		$raw = trim($raw);
		if ($raw === '') {
			return '';
		}
		try {
			$dt = new DateTimeImmutable(str_replace('T', ' ', $raw), wp_timezone());
			return function_exists('wp_date') ? (string) wp_date('F j, Y g:i A', $dt->getTimestamp(), wp_timezone()) : $dt->format('F j, Y g:i A');
		} catch (Exception $e) {
			return $raw;
		}
	}
}

if (!function_exists('vms_admission_event_plan_gate_block')) {
	function vms_admission_event_plan_gate_block(int $event_plan_id): ?array
	{
		if ($event_plan_id <= 0) {
			return null;
		}
		$plan = vms_admission_event_plan_context($event_plan_id);
		if (!is_array($plan)) {
			return array(
				'code' => 'invalid_event_plan',
				'message' => __('Event plan not found.', 'backstage-venue-manager'),
			);
		}
		$status = sanitize_key((string) ($plan['status'] ?? ''));
		if ($status === 'cancelled' || $status === 'canceled') {
			return array(
				'code' => 'event_cancelled',
				'message' => __('This event has been cancelled.', 'backstage-venue-manager'),
			);
		}
		return null;
	}
}

if (!function_exists('vms_admission_user_can_view_phone')) {
	function vms_admission_user_can_view_phone(): bool
	{
		$settings = vms_admission_settings();
		if (current_user_can(vms_admission_phone_view_capability()) || current_user_can(vms_admission_manage_capability())) {
			return true;
		}
		return !empty($settings['door_show_phone']);
	}
}

if (!function_exists('vms_admission_prepare_row')) {
	function vms_admission_prepare_row(array $row): array
	{
		$can_view_phone = vms_admission_user_can_view_phone();
		$phone = isset($row['phone']) ? (string) $row['phone'] : '';
		$row['phone_masked'] = $phone !== '' ? vms_admission_mask_phone($phone) : '';
		if (!$can_view_phone) {
			$row['phone'] = '';
		}
		$row['can_view_phone'] = $can_view_phone ? 1 : 0;
		$row['guest_email'] = isset($row['guest_email']) ? (string) $row['guest_email'] : '';
		$owner_vendor_id = isset($row['owner_vendor_id']) ? (int) $row['owner_vendor_id'] : 0;
		$row['owner_vendor_name'] = $owner_vendor_id > 0 ? (string) get_the_title($owner_vendor_id) : '';
		$token = isset($row['admission_token']) ? (string) $row['admission_token'] : '';
		$row['scan_url'] = ($token !== '' && function_exists('vms_admission_scan_url')) ? vms_admission_scan_url($token) : '';
		$row['source_label'] = vms_admission_source_label((string) ($row['source'] ?? ''), (string) ($row['admission_kind'] ?? ''));
		return $row;
	}
}

if (!function_exists('vms_admission_rest_entry_row')) {
	function vms_admission_rest_entry_row(int $entry_id): ?array
	{
		if ($entry_id <= 0) {
			return null;
		}
		global $wpdb;
		$table = vms_admission_table_entries();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admissions REST single-row reads target the plugin-owned entries table with a %i/%d-prepared identifier and ID, and request-fresh state is required after create/check-in/update mutations.
		$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d', $table, $entry_id), ARRAY_A);
		return is_array($row) ? $row : null;
	}
}


if (!function_exists('vms_admission_source_label')) {
	function vms_admission_source_label(string $source, string $kind = ''): string
	{
		$source = sanitize_key($source);
		$kind = sanitize_key($kind);
		if ($source === 'vendor' || $source === 'vendor_guest') {
			return __('Vendor Guest', 'backstage-venue-manager');
		}
		if ($source === 'pass_claim' || $kind === 'pass') {
			return __('Guest Pass', 'backstage-venue-manager');
		}
		if ($source === 'operator' || $kind === 'comp') {
			return __('Guest List', 'backstage-venue-manager');
		}
		if ($kind === 'ticket' || $source === 'tec' || $source === 'woo') {
			return __('Paid Ticket', 'backstage-venue-manager');
		}
		return __('Admission', 'backstage-venue-manager');
	}
}

if (!function_exists('vms_admission_parse_status_filter')) {
	function vms_admission_parse_status_filter(string $status): array
	{
		$status = sanitize_key($status);
		if ($status === '' || $status === 'active') {
			return array('active', 'partial');
		}
		if ($status === 'checked_in') {
			return array('checked_in');
		}
		if ($status === 'canceled') {
			return array('canceled');
		}
		if ($status === 'all') {
			return array('active', 'partial', 'checked_in', 'canceled');
		}
		return array('active');
	}
}

if (!function_exists('vms_admission_rest_list')) {
	function vms_admission_rest_list(WP_REST_Request $req)
	{
		global $wpdb;
		$event_plan_id = absint($req->get_param('event_plan_id'));
		if ($event_plan_id <= 0) {
			return vms_admission_rest_error('invalid_event_plan', __('Event plan is required.', 'backstage-venue-manager'), 400);
		}
		if (!vms_admission_event_plan_context($event_plan_id)) {
			return vms_admission_rest_error('invalid_event_plan', __('Event plan not found.', 'backstage-venue-manager'), 404);
		}
		if (!vms_admission_current_user_can_checkin()) {
			return vms_admission_rest_error('forbidden', __('Access denied.', 'backstage-venue-manager'), 403);
		}

		$status_filter = vms_admission_parse_status_filter((string) $req->get_param('status'));
		$limit = absint($req->get_param('limit'));
		if ($limit <= 0) {
			$limit = 50;
		}
		if ($limit > 100) {
			$limit = 100;
		}

		$where = array('event_plan_id = %d');
		$params = array($event_plan_id);

		$in_placeholders = implode(',', array_fill(0, count($status_filter), '%s'));
		$where[] = "status IN ({$in_placeholders})";
		foreach ($status_filter as $status) {
			$params[] = $status;
		}

		$q = trim((string) $req->get_param('q'));
		if ($q !== '') {
			$q_norm = vms_admission_normalize_name($q);
			$email_norm = vms_admission_normalize_email($q);
			$phone_norm = vms_admission_normalize_phone($q);
			$scan_token = function_exists('vms_admission_extract_scan_token') ? vms_admission_extract_scan_token($q) : '';
			$parts = array();
			if ($q_norm !== '') {
				$parts[] = 'guest_name_norm LIKE %s';
				$params[] = '%' . $wpdb->esc_like($q_norm) . '%';
			}
			if ($email_norm !== '') {
				$parts[] = 'guest_email_norm LIKE %s';
				$params[] = '%' . $wpdb->esc_like($email_norm) . '%';
			}
			if ($phone_norm !== '') {
				$parts[] = 'phone_norm LIKE %s';
				$params[] = '%' . $wpdb->esc_like($phone_norm) . '%';
			}
			if ($scan_token !== '') {
				$parts[] = 'admission_token = %s';
				$params[] = $scan_token;
			}
			if (!empty($parts)) {
				$where[] = '(' . implode(' OR ', $parts) . ')';
			}
		}

		$where_sql = implode(' AND ', $where);
		$table = vms_admission_table_entries();
		$sql = "SELECT * FROM {$table} WHERE {$where_sql}
			ORDER BY
			CASE status WHEN 'active' THEN 0 WHEN 'partial' THEN 1 WHEN 'checked_in' THEN 2 WHEN 'canceled' THEN 3 ELSE 9 END ASC,
			CASE WHEN status='checked_in' THEN checked_in_at END DESC,
			CASE WHEN status<>'checked_in' THEN guest_name END ASC,
			id DESC
			LIMIT %d";
		$params[] = $limit;

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admissions list queries assemble a bounded WHERE clause from the literal placeholder fragments above, and the request-fresh door/admin list must reflect immediate custom-table writes.
		$rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
		if (!is_array($rows)) {
			return vms_admission_rest_error('db_error', __('Could not load admissions.', 'backstage-venue-manager'), 500);
		}

		$data = array_map('vms_admission_prepare_row', $rows);
		return vms_admission_rest_ok(array('items' => $data));
	}
}

if (!function_exists('vms_admission_rest_create')) {
	function vms_admission_rest_create(WP_REST_Request $req)
	{
		if (!vms_admission_current_user_can_manage()) {
			return vms_admission_rest_error('forbidden', __('Access denied.', 'backstage-venue-manager'), 403);
		}

		$event_plan_id = absint($req->get_param('event_plan_id'));
		$guest_name = sanitize_text_field((string) $req->get_param('guest_name'));
		$guest_email = sanitize_email((string) $req->get_param('guest_email'));
		$party_size = absint($req->get_param('party_size'));
		$phone = sanitize_text_field((string) $req->get_param('phone'));
		$notes = sanitize_textarea_field((string) $req->get_param('notes'));

		if ($event_plan_id <= 0) {
			return vms_admission_rest_error('invalid_event_plan', __('Event plan is required.', 'backstage-venue-manager'), 400);
		}
		$plan = vms_admission_event_plan_context($event_plan_id);
		if (!$plan) {
			return vms_admission_rest_error('invalid_event_plan', __('Event plan not found.', 'backstage-venue-manager'), 404);
		}
		if ($guest_name === '') {
			return vms_admission_rest_error('invalid_guest_name', __('Guest name is required.', 'backstage-venue-manager'), 400);
		}

		$settings = vms_admission_settings();
		$max_party = max(1, (int) $settings['max_party_size']);
		if ($party_size < 1 || $party_size > $max_party) {
			/* translators: %d: number used in this message. */
			return vms_admission_rest_error('invalid_party_size', sprintf(__('Party size must be between 1 and %d.', 'backstage-venue-manager'), $max_party), 400);
		}

		$guest_name_norm = vms_admission_normalize_name($guest_name);
		$guest_email_norm = vms_admission_normalize_email($guest_email);
		$phone_norm = vms_admission_normalize_phone($phone);
		if ($guest_name_norm === '') {
			return vms_admission_rest_error('invalid_guest_name', __('Guest name is required.', 'backstage-venue-manager'), 400);
		}

		global $wpdb;
		$table = vms_admission_table_entries();
		$duplicate_check = function_exists('vms_admission_find_duplicate_entry_count')
			? (array) vms_admission_find_duplicate_entry_count($event_plan_id, $guest_name_norm, $guest_email_norm, $phone_norm, 0)
			: array('count' => 0);
		$duplicate_count = (int) ($duplicate_check['count'] ?? 0);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Admissions create writes directly to the plugin-owned entries table because no core API exposes this custom repository.
		$insert = $wpdb->insert(
			$table,
			array(
				'event_plan_id' => $event_plan_id,
				'venue_id' => (int) $plan['venue_id'],
				'admission_kind' => 'comp',
				'source' => 'operator',
				'owner_vendor_id' => null,
				'guest_name' => $guest_name,
				'guest_name_norm' => $guest_name_norm,
				'guest_email' => $guest_email !== '' ? $guest_email : null,
				'guest_email_norm' => $guest_email_norm !== '' ? $guest_email_norm : null,
				'party_size' => $party_size,
				'phone' => $phone,
				'phone_norm' => $phone_norm !== '' ? $phone_norm : null,
				'notes' => $notes,
				'status' => 'active',
				'created_by' => get_current_user_id(),
				'created_at' => vms_admission_now_mysql(),
			),
			array('%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s')
		);

		if ($insert === false) {
			error_log('VMS Admission create failed: ' . (string) $wpdb->last_error);
			return vms_admission_rest_error('db_error', __('Could not create admission entry.', 'backstage-venue-manager'), 500);
		}

		$entry_id = (int) $wpdb->insert_id;
		if (function_exists('vms_admission_ensure_entry_token')) {
			vms_admission_ensure_entry_token($entry_id);
		}
		$row = vms_admission_rest_entry_row($entry_id);
		vms_admission_audit_log($event_plan_id, $entry_id, 'entry_create', get_current_user_id(), 'admin', array(
			'duplicate_count' => $duplicate_count,
		));

		return vms_admission_rest_ok(array(
			'item' => vms_admission_prepare_row(is_array($row) ? $row : array()),
			'duplicate_warning' => $duplicate_count > 0 ? 1 : 0,
			'duplicate_count' => $duplicate_count,
		));
	}
}

if (!function_exists('vms_admission_rest_patch')) {
	function vms_admission_rest_patch(WP_REST_Request $req)
	{
		if (!vms_admission_current_user_can_manage()) {
			return vms_admission_rest_error('forbidden', __('Access denied.', 'backstage-venue-manager'), 403);
		}

		$entry_id = absint($req->get_param('id'));
		if ($entry_id <= 0) {
			return vms_admission_rest_error('invalid_entry', __('Entry not found.', 'backstage-venue-manager'), 404);
		}

		global $wpdb;
		$table = vms_admission_table_entries();
		$row = vms_admission_rest_entry_row($entry_id);
		if (!is_array($row)) {
			return vms_admission_rest_error('invalid_entry', __('Entry not found.', 'backstage-venue-manager'), 404);
		}

		$requested_status = null;
		if (null !== $req->get_param('status')) {
			$requested_status = sanitize_key((string) $req->get_param('status'));
			if (!in_array($requested_status, array('active', 'partial', 'checked_in', 'canceled'), true)) {
				return vms_admission_rest_error('invalid_status', __('Invalid status.', 'backstage-venue-manager'), 400);
			}
		}

		$is_canceled = ((string) ($row['status'] ?? '')) === 'canceled';
		$is_restore_only = $is_canceled
			&& $requested_status === 'active'
			&& null === $req->get_param('guest_name')
			&& null === $req->get_param('guest_email')
			&& null === $req->get_param('party_size')
			&& null === $req->get_param('phone')
			&& null === $req->get_param('notes');
		if ($is_canceled && !$is_restore_only) {
			return vms_admission_rest_error('cannot_edit_canceled', __('Canceled entries cannot be edited.', 'backstage-venue-manager'), 409);
		}

		$updates = array();
		$formats = array();
		$details = array();
		$settings = vms_admission_settings();
		$max_party = max(1, (int) $settings['max_party_size']);

		if (null !== $req->get_param('guest_name')) {
			$guest_name = sanitize_text_field((string) $req->get_param('guest_name'));
			if ($guest_name === '') {
				return vms_admission_rest_error('invalid_guest_name', __('Guest name is required.', 'backstage-venue-manager'), 400);
			}
			$updates['guest_name'] = $guest_name;
			$updates['guest_name_norm'] = vms_admission_normalize_name($guest_name);
			$formats[] = '%s';
			$formats[] = '%s';
			$details['guest_name'] = $guest_name;
		}

		if (null !== $req->get_param('guest_email')) {
			$guest_email = sanitize_email((string) $req->get_param('guest_email'));
			$updates['guest_email'] = $guest_email !== '' ? $guest_email : null;
			$updates['guest_email_norm'] = ($guest_email !== '') ? vms_admission_normalize_email($guest_email) : null;
			$formats[] = '%s';
			$formats[] = '%s';
			$details['guest_email'] = $guest_email;
		}

		if (null !== $req->get_param('party_size')) {
			$party_size = absint($req->get_param('party_size'));
			if ($party_size < 1 || $party_size > $max_party) {
				/* translators: %d: number used in this message. */
				return vms_admission_rest_error('invalid_party_size', sprintf(__('Party size must be between 1 and %d.', 'backstage-venue-manager'), $max_party), 400);
			}
			$updates['party_size'] = $party_size;
			$formats[] = '%d';
			$details['party_size'] = $party_size;
		}

		if (null !== $req->get_param('phone')) {
			$updates['phone'] = sanitize_text_field((string) $req->get_param('phone'));
			$updates['phone_norm'] = ($updates['phone'] !== '') ? vms_admission_normalize_phone($updates['phone']) : null;
			$formats[] = '%s';
			$formats[] = '%s';
			$details['phone'] = $updates['phone'];
		}

		if (null !== $req->get_param('notes')) {
			$updates['notes'] = sanitize_textarea_field((string) $req->get_param('notes'));
			$formats[] = '%s';
			$details['notes'] = $updates['notes'];
		}

		$status = null;
		if (null !== $requested_status) {
			$status = $requested_status;
			$updates['status'] = $status;
			$formats[] = '%s';
			$details['status'] = $status;
		}

		if (empty($updates)) {
			return vms_admission_rest_ok(array('item' => vms_admission_prepare_row($row)));
		}

		$updates['updated_by'] = get_current_user_id();
		$updates['updated_at'] = vms_admission_now_mysql();
		$formats[] = '%d';
		$formats[] = '%s';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admissions edit writes target the plugin-owned entries table directly so admin edits remain immediately visible to door and reporting flows.
		$ok = $wpdb->update($table, $updates, array('id' => $entry_id), $formats, array('%d'));
		if ($ok === false) {
			error_log('VMS Admission update failed: ' . (string) $wpdb->last_error);
			return vms_admission_rest_error('db_error', __('Could not update entry.', 'backstage-venue-manager'), 500);
		}

		$action = ($status === 'canceled') ? 'entry_cancel' : 'entry_update';
		vms_admission_audit_log((int) $row['event_plan_id'], $entry_id, $action, get_current_user_id(), 'admin', $details);

		$fresh = vms_admission_rest_entry_row($entry_id);
		return vms_admission_rest_ok(array('item' => vms_admission_prepare_row(is_array($fresh) ? $fresh : $row)));
	}
}

if (!function_exists('vms_admission_rest_checkin')) {
	function vms_admission_rest_checkin(WP_REST_Request $req)
	{
		if (!vms_admission_current_user_can_checkin()) {
			return vms_admission_rest_error('forbidden', __('Access denied.', 'backstage-venue-manager'), 403);
		}

		$entry_id = absint($req->get_param('id'));
		if ($entry_id <= 0) {
			return vms_admission_rest_error('invalid_entry', __('Entry not found.', 'backstage-venue-manager'), 404);
		}

		$qty = absint($req->get_param('qty'));
		if ($qty <= 0) {
			$qty = 1;
		}

		global $wpdb;
		$table = vms_admission_table_entries();
		$now = vms_admission_now_mysql();
		$user_id = get_current_user_id();

		$row = vms_admission_rest_entry_row($entry_id);
		if (!is_array($row)) {
			return vms_admission_rest_error('invalid_entry', __('Entry not found.', 'backstage-venue-manager'), 404);
		}

		$status = (string) ($row['status'] ?? 'active');
		if ($status === 'canceled') {
			return vms_admission_rest_error('conflict', __('Entry is canceled.', 'backstage-venue-manager'), 409);
		}
		$event_gate_block = vms_admission_event_plan_gate_block((int) ($row['event_plan_id'] ?? 0));
		if (is_array($event_gate_block)) {
			return vms_admission_rest_error((string) ($event_gate_block['code'] ?? 'event_unavailable'), (string) ($event_gate_block['message'] ?? __('This admission is not valid right now.', 'backstage-venue-manager')), 409, array('item' => vms_admission_prepare_row($row)));
		}

		$party_size = max(1, (int) ($row['party_size'] ?? 1));
		$checked_in_qty = max(0, (int) ($row['checked_in_qty'] ?? 0));

		if ($checked_in_qty >= $party_size) {
			return vms_admission_rest_error('conflict', __('Already fully checked in.', 'backstage-venue-manager'), 409);
		}

		$new_qty = min($party_size, $checked_in_qty + $qty);
		$new_status = ($new_qty >= $party_size) ? 'checked_in' : 'partial';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admissions check-in writes the plugin-owned entries table directly with a %i-prepared identifier so door actions persist immediately.
		$updated = $wpdb->query($wpdb->prepare(
			"UPDATE %i
			SET status = %s,
				checked_in_qty = %d,
				checked_in_at = %s,
				checked_in_by = %d,
				updated_at = %s,
				updated_by = %d
			WHERE id = %d AND status <> 'canceled'",
			$table,
			$new_status,
			$new_qty,
			$now,
			$user_id,
			$now,
			$user_id,
			$entry_id
		));
		if ($updated === false) {
			error_log('VMS Admission checkin failed: ' . (string) $wpdb->last_error);
			return vms_admission_rest_error('db_error', __('Could not check in guest.', 'backstage-venue-manager'), 500);
		}

		$fresh = vms_admission_rest_entry_row($entry_id);
		if (!is_array($fresh)) {
			return vms_admission_rest_error('invalid_entry', __('Entry not found.', 'backstage-venue-manager'), 404);
		}

		vms_admission_audit_log((int) $fresh['event_plan_id'], $entry_id, 'checkin', $user_id, 'door', array('qty' => $qty));
		return vms_admission_rest_ok(array('item' => vms_admission_prepare_row($fresh)));

	}
}

if (!function_exists('vms_admission_rest_uncheckin')) {
	function vms_admission_rest_uncheckin(WP_REST_Request $req)
	{
		$settings = vms_admission_settings();
		$allow_uncheckin = !empty($settings['allow_uncheckin']);
		$allow_door = !empty($settings['allow_uncheckin_for_door']);
		$can_manage = vms_admission_current_user_can_manage();
		$can_door = current_user_can(vms_admission_door_capability());

		if (!$allow_uncheckin) {
			return vms_admission_rest_error('disabled', __('Undo check-in is disabled.', 'backstage-venue-manager'), 403);
		}
		if (!($can_manage || ($can_door && $allow_door))) {
			return vms_admission_rest_error('forbidden', __('Access denied.', 'backstage-venue-manager'), 403);
		}

		$entry_id = absint($req->get_param('id'));
		if ($entry_id <= 0) {
			return vms_admission_rest_error('invalid_entry', __('Entry not found.', 'backstage-venue-manager'), 404);
		}

		$qty = absint($req->get_param('qty'));
		if ($qty <= 0) {
			$qty = 1;
		}

		global $wpdb;
		$table = vms_admission_table_entries();
		$now = vms_admission_now_mysql();
		$user_id = get_current_user_id();

		$row = vms_admission_rest_entry_row($entry_id);
		if (!is_array($row)) {
			return vms_admission_rest_error('invalid_entry', __('Entry not found.', 'backstage-venue-manager'), 404);
		}

		$status = (string) ($row['status'] ?? 'active');
		if ($status === 'canceled') {
			return vms_admission_rest_error('conflict', __('Entry is canceled.', 'backstage-venue-manager'), 409);
		}

		$party_size = max(1, (int) ($row['party_size'] ?? 1));
		$checked_in_qty = max(0, (int) ($row['checked_in_qty'] ?? 0));
		if ($checked_in_qty <= 0) {
			return vms_admission_rest_error('conflict', __('Entry is not checked in.', 'backstage-venue-manager'), 409);
		}

		$new_qty = max(0, $checked_in_qty - $qty);
		$new_status = ($new_qty <= 0) ? 'active' : 'partial';

		if ($new_qty <= 0) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admissions uncheck-in writes the plugin-owned entries table directly with a %i-prepared identifier so door/admin reversals persist immediately.
			$updated = $wpdb->query($wpdb->prepare(
				"UPDATE %i
				SET status = 'active',
					checked_in_qty = 0,
					checked_in_at = NULL,
					checked_in_by = NULL,
					updated_at = %s,
					updated_by = %d
				WHERE id = %d AND status <> 'canceled'",
				$table,
				$now,
				$user_id,
				$entry_id
			));
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admissions uncheck-in writes the plugin-owned entries table directly with a %i-prepared identifier so door/admin reversals persist immediately.
			$updated = $wpdb->query($wpdb->prepare(
				"UPDATE %i
				SET status = 'partial',
					checked_in_qty = %d,
					updated_at = %s,
					updated_by = %d
				WHERE id = %d AND status <> 'canceled'",
				$table,
				$new_qty,
				$now,
				$user_id,
				$entry_id
			));
		}
		if ($updated === false) {
			error_log('VMS Admission uncheckin failed: ' . (string) $wpdb->last_error);
			return vms_admission_rest_error('db_error', __('Could not undo check-in.', 'backstage-venue-manager'), 500);
		}

		$fresh = vms_admission_rest_entry_row($entry_id);
		if (!is_array($fresh)) {
			return vms_admission_rest_error('invalid_entry', __('Entry not found.', 'backstage-venue-manager'), 404);
		}

		$ctx = $can_manage ? 'admin' : 'door';
		vms_admission_audit_log((int) $fresh['event_plan_id'], $entry_id, 'uncheckin', $user_id, $ctx, array('qty' => $qty));
		return vms_admission_rest_ok(array('item' => vms_admission_prepare_row($fresh)));

	}
}


if (!function_exists('vms_admission_rest_scan')) {
	function vms_admission_rest_scan(WP_REST_Request $req)
	{
		if (!vms_admission_current_user_can_checkin()) {
			return vms_admission_rest_error('forbidden', __('Access denied.', 'backstage-venue-manager'), 403);
		}

		$raw = sanitize_text_field((string) $req->get_param('scan'));
		$event_plan_id = absint($req->get_param('event_plan_id'));
		$auto_checkin = !empty($req->get_param('auto_checkin'));
		$token = function_exists('vms_admission_extract_scan_token') ? vms_admission_extract_scan_token($raw) : '';
		if ($token === '') {
			return vms_admission_rest_error('not_found', __('This code was not recognized. Search by name or phone if needed.', 'backstage-venue-manager'), 404);
		}

		global $wpdb;
		$table = vms_admission_table_entries();
		if ($event_plan_id > 0) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admissions scan reads one request-fresh custom-table row with %i/%s/%d-prepared values so door validation reflects current token and event state.
			$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE admission_token = %s AND event_plan_id = %d LIMIT 1', $table, $token, $event_plan_id), ARRAY_A);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admissions scan reads one request-fresh custom-table row with %i/%s-prepared values so door validation reflects current token state.
			$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE admission_token = %s LIMIT 1', $table, $token), ARRAY_A);
		}
		if (!is_array($row)) {
			return vms_admission_rest_error('not_found', __('Admission not found for this event.', 'backstage-venue-manager'), 404);
		}
		if ((string) ($row['status'] ?? '') === 'canceled') {
			return vms_admission_rest_error('voided', __('This admission is voided/canceled.', 'backstage-venue-manager'), 409, array('item' => vms_admission_prepare_row($row)));
		}
		if ($event_plan_id > 0 && (int) ($row['event_plan_id'] ?? 0) !== $event_plan_id) {
			return vms_admission_rest_error('wrong_event', __('This admission is not valid for the selected event.', 'backstage-venue-manager'), 409, array('item' => vms_admission_prepare_row($row)));
		}
		$event_gate_block = vms_admission_event_plan_gate_block((int) ($row['event_plan_id'] ?? 0));
		if (is_array($event_gate_block)) {
			return vms_admission_rest_error((string) ($event_gate_block['code'] ?? 'event_unavailable'), (string) ($event_gate_block['message'] ?? __('This admission is not valid right now.', 'backstage-venue-manager')), 409, array('item' => vms_admission_prepare_row($row)));
		}
		$party_size = max(1, (int) ($row['party_size'] ?? 1));
		$checked_in_qty = max(0, (int) ($row['checked_in_qty'] ?? 0));
		if ($checked_in_qty >= $party_size) {
			$message = __('Already checked in.', 'backstage-venue-manager');
			$checked_in_at = vms_admission_format_local_datetime((string) ($row['checked_in_at'] ?? ''));
			if ($checked_in_at !== '') {
				/* translators: %s: human-readable value used in this message. */
				$message = sprintf(__('Already checked in at %s.', 'backstage-venue-manager'), $checked_in_at);
			}
			return vms_admission_rest_error('already_checked_in', $message, 409, array('item' => vms_admission_prepare_row($row)));
		}
		if (!$auto_checkin) {
			return vms_admission_rest_ok(array('item' => vms_admission_prepare_row($row), 'status' => 'valid'));
		}
		$check_req = new WP_REST_Request('POST', '/vms/v1/admissions/' . (int) $row['id'] . '/checkin');
		$check_req->set_param('id', (int) $row['id']);
		$check_req->set_param('qty', 1);
		return vms_admission_rest_checkin($check_req);
	}
}

if (!function_exists('vms_admission_rest_summary')) {
	function vms_admission_rest_summary(WP_REST_Request $req)
	{
		if (!vms_admission_current_user_can_checkin()) {
			return vms_admission_rest_error('forbidden', __('Access denied.', 'backstage-venue-manager'), 403);
		}

		$event_plan_id = absint($req->get_param('event_plan_id'));
		if ($event_plan_id <= 0) {
			return vms_admission_rest_error('invalid_event_plan', __('Event plan is required.', 'backstage-venue-manager'), 400);
		}

		global $wpdb;
		$table = vms_admission_table_entries();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admissions summary reads aggregate the plugin-owned entries table with a %i/%d-prepared identifier and event filter, and dashboard/door totals must stay request-fresh after writes.
		$totals = $wpdb->get_row($wpdb->prepare(
			"SELECT
			SUM(CASE WHEN status <> 'canceled' THEN 1 ELSE 0 END) AS total_entries,
			SUM(CASE WHEN status <> 'canceled' THEN party_size ELSE 0 END) AS total_headcount,
			SUM(CASE WHEN status <> 'canceled' AND checked_in_qty > 0 THEN 1 ELSE 0 END) AS checked_in_entries,
			SUM(CASE WHEN status <> 'canceled' THEN checked_in_qty ELSE 0 END) AS checked_in_headcount
			FROM %i
			WHERE event_plan_id = %d",
			$table,
			$event_plan_id
		), ARRAY_A);
		if (!is_array($totals)) {
			return vms_admission_rest_error('db_error', __('Could not load summary.', 'backstage-venue-manager'), 500);
		}

		$data = array(
			'total_entries' => (int) ($totals['total_entries'] ?? 0),
			'total_headcount' => (int) ($totals['total_headcount'] ?? 0),
			'checked_in_entries' => (int) ($totals['checked_in_entries'] ?? 0),
			'checked_in_headcount' => (int) ($totals['checked_in_headcount'] ?? 0),
		);
		return vms_admission_rest_ok($data);
	}
}

if (!function_exists('vms_admission_rest_event_plans_today')) {
	function vms_admission_rest_event_plans_today(WP_REST_Request $req)
	{
		if (!vms_admission_current_user_can_checkin()) {
			return vms_admission_rest_error('forbidden', __('Access denied.', 'backstage-venue-manager'), 403);
		}

		$today = wp_date('Y-m-d', time(), wp_timezone());
			$ids = get_posts(array(
				'post_type' => 'vms_event_plan',
				'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
				'posts_per_page' => 50,
				'fields' => 'ids',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admissions door-plan lists intentionally filter by the existing event-date postmeta contract and remain tightly bounded to the current day view.
					array(
						'key' => '_vms_event_date',
					'value' => $today,
					'compare' => '=',
				),
			),
		));

		$out = array();
		foreach ((array) $ids as $event_plan_id) {
			$plan = vms_admission_event_plan_context((int) $event_plan_id);
			if (!$plan) {
				continue;
			}
			if ((string) $plan['status'] === 'cancelled' || (string) $plan['status'] === 'canceled') {
				continue;
			}
			$out[] = $plan;
		}

		usort($out, function (array $a, array $b): int {
			return strcmp((string) $a['title'], (string) $b['title']);
		});

		return vms_admission_rest_ok(array('items' => $out));
	}
}

add_action('rest_api_init', function (): void {
	register_rest_route('vms/v1', '/admissions', array(
		'methods' => WP_REST_Server::READABLE,
		'permission_callback' => 'vms_admission_rest_can_checkin_request',
		'callback' => 'vms_admission_rest_list',
	));

	register_rest_route('vms/v1', '/admissions', array(
		'methods' => WP_REST_Server::CREATABLE,
		'permission_callback' => 'vms_admission_rest_can_manage_request',
		'callback' => 'vms_admission_rest_create',
	));

	register_rest_route('vms/v1', '/admissions/(?P<id>\d+)', array(
		'methods' => 'PATCH',
		'permission_callback' => 'vms_admission_rest_can_manage_request',
		'callback' => 'vms_admission_rest_patch',
	));

	register_rest_route('vms/v1', '/admissions/(?P<id>\d+)/checkin', array(
		'methods' => WP_REST_Server::CREATABLE,
		'permission_callback' => 'vms_admission_rest_can_checkin_request',
		'callback' => 'vms_admission_rest_checkin',
	));

	register_rest_route('vms/v1', '/admissions/(?P<id>\d+)/uncheckin', array(
		'methods' => WP_REST_Server::CREATABLE,
		'permission_callback' => 'vms_admission_rest_can_checkin_request',
		'callback' => 'vms_admission_rest_uncheckin',
	));

	register_rest_route('vms/v1', '/admissions/scan', array(
		'methods' => WP_REST_Server::CREATABLE,
		'permission_callback' => 'vms_admission_rest_can_checkin_request',
		'callback' => 'vms_admission_rest_scan',
	));

	register_rest_route('vms/v1', '/admissions/summary', array(
		'methods' => WP_REST_Server::READABLE,
		'permission_callback' => 'vms_admission_rest_can_checkin_request',
		'callback' => 'vms_admission_rest_summary',
	));

	register_rest_route('vms/v1', '/event-plans/today', array(
		'methods' => WP_REST_Server::READABLE,
		'permission_callback' => 'vms_admission_rest_can_checkin_request',
		'callback' => 'vms_admission_rest_event_plans_today',
	));
});
