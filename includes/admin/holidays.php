<?php

/**
 * VMS Admin: Holidays (venue-scoped)
 *
 * Storage: wp_options option "vms_holidays"
 *
 * Format:
 * vms_holidays[venue_id][YYYY-MM-DD] = [
 *   'name'   => string,
 *   'status' => 'open'|'closed',
 *   'rules'  => [
 *     'vendor' => [
 *       'structure' => 'flat_fee'|'door_split'|'flat_fee_door_split',
 *       'flat_fee_amount' => float,
 *       'door_split_percent' => float
 *     ]
 *   ]
 * ]
 */

if (!defined('ABSPATH')) {
	exit;
}

// === Holidays admin venue persistence (per-user) ==============================
define('VMS_HOLIDAYS_LAST_VENUE_USERMETA_KEY', '_vms_holidays_last_venue_id');

/**
 * Get venue_id from request or user's last selection, and persist when a valid venue is chosen.
 * Returns an int venue_id (0 if none).
 */
function vms_holidays_get_effective_venue_id()
{
	if (!is_user_logged_in()) {
		return 0;
	}

	$user_id = get_current_user_id();

	// 1) Request wins (GET/POST).
	$venue_id_req = 0;
	if (isset($_REQUEST['venue_id'])) {
		$venue_id_req = absint(wp_unslash($_REQUEST['venue_id']));
	}

	// If request venue looks valid, persist it and return it.
	if ($venue_id_req > 0 && post_type_exists('vms_venue')) {
		$post = get_post($venue_id_req);
		if ($post && $post->post_type === 'vms_venue') {
			update_user_meta($user_id, VMS_HOLIDAYS_LAST_VENUE_USERMETA_KEY, $venue_id_req);
			return $venue_id_req;
		}
	}

	// 2) Fall back to stored user selection.
	$stored = absint(get_user_meta($user_id, VMS_HOLIDAYS_LAST_VENUE_USERMETA_KEY, true));
	if ($stored > 0) {
		$post = get_post($stored);
		if ($post && $post->post_type === 'vms_venue') {
			return $stored;
		}
	}

	return 0;
}

/**
 * Build a safe URL back to this Holidays admin page with a venue_id.
 * Preserves the current page slug if available.
 */
function vms_holidays_admin_url_with_venue($venue_id)
{
	$venue_id = absint($venue_id);

	// Try to preserve the current admin page slug (?page=...).
	$page = '';
	if (isset($_GET['page'])) {
		$page = sanitize_key(wp_unslash($_GET['page']));
	}

	$args = array();
	if ($page !== '') {
		$args['page'] = $page;
	}
	if ($venue_id > 0) {
		$args['venue_id'] = $venue_id;
	}

	return add_query_arg($args, admin_url('admin.php'));
}

/**
 * Register admin-post handlers early (no output, safe redirects).
 */
if (is_admin()) {
	add_action('admin_post_vms_holidays_save', 'vms_admin_holidays_adminpost_save');
	add_action('admin_post_vms_holidays_delete', 'vms_admin_holidays_adminpost_delete');
	add_action('admin_post_vms_holidays_bulk_delete', 'vms_admin_holidays_adminpost_bulk_delete');
}

function vms_admin_holidays_register_page(): void
{
	// This function exists so menu.php can call it as a callback safely.
}

function vms_admin_holidays_adminpost_bulk_delete(): void
{
	vms_admin_holidays_adminpost_handle('bulk_delete');
}

/**
 * Admin-post: SAVE
 */
function vms_admin_holidays_adminpost_save(): void
{
	vms_admin_holidays_adminpost_handle('save');
}

/**
 * Admin-post: DELETE
 */
function vms_admin_holidays_adminpost_delete(): void
{
	vms_admin_holidays_adminpost_handle('delete');
}

/**
 * Shared admin-post handler.
 */
function vms_admin_holidays_adminpost_handle(string $expected_action): void
{
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have permission to do that.', 'vms'));
	}

	// Read from REQUEST so this works for POST forms and GET delete links
	$nonce = isset($_REQUEST['vms_holidays_nonce'])
		? sanitize_text_field(wp_unslash((string) $_REQUEST['vms_holidays_nonce']))
		: '';

	$nonce_action = 'vms_holidays_' . $expected_action;
	if (!$nonce || !wp_verify_nonce($nonce, $nonce_action)) {
		wp_die(esc_html__('Security check failed.', 'vms'));
	}

	$venue_id = isset($_REQUEST['venue_id'])
		? absint(wp_unslash($_REQUEST['venue_id']))
		: 0;

	$action = isset($_REQUEST['vms_holidays_action'])
		? sanitize_text_field(wp_unslash((string) $_REQUEST['vms_holidays_action']))
		: '';

	if ($action !== $expected_action) {
		// Hard fail: wrong action was posted.
		$redirect = admin_url('admin.php?page=vms-holidays');
		if ($venue_id > 0) {
			$redirect = add_query_arg('venue_id', $venue_id, $redirect);
		}
		$redirect = add_query_arg('vms_err', '1', $redirect);
		$redirect = add_query_arg('vms_msg', rawurlencode('Invalid action.'), $redirect);
		wp_safe_redirect($redirect);
		exit;
	}

	$result = vms_admin_holidays_apply_post($expected_action);

	$redirect = admin_url('admin.php?page=vms-holidays');
	if ($venue_id > 0) {
		$redirect = add_query_arg('venue_id', $venue_id, $redirect);
	}

	if (!empty($result['ok'])) {
		$redirect = add_query_arg('vms_ok', '1', $redirect);
		$redirect = add_query_arg('vms_msg', rawurlencode((string) $result['message']), $redirect);
	} else {
		$redirect = add_query_arg('vms_err', '1', $redirect);
		$redirect = add_query_arg('vms_msg', rawurlencode((string) $result['message']), $redirect);
	}

	wp_safe_redirect($redirect);
	exit;
}


function vms_holidays_sanitize_money($raw): string
{
	$raw = is_string($raw) ? $raw : '';
	$raw = trim($raw);
	if ($raw === '') {
		return '';
	}

	$raw = str_replace(array('$', ',', ' '), '', $raw);
	$raw = preg_replace('/[^0-9.]/', '', $raw);
	if ($raw === '') {
		return '';
	}

	return (string) (0 + $raw);
}

function vms_holidays_sanitize_percent($raw): string
{
	$raw = is_string($raw) ? $raw : '';
	$raw = trim($raw);
	if ($raw === '') {
		return '';
	}

	$raw = str_replace(array('%', ',', ' '), '', $raw);
	$raw = preg_replace('/[^0-9.]/', '', $raw);
	if ($raw === '') {
		return '';
	}

	return (string) (0 + $raw);
}

/**
 * Validates manual holiday payload.
 * Returns: array('ok' => bool, 'message' => string)
 */
function vms_holidays_validate_manual_fields(string $name, string $status, string $v_structure, string $v_flat, string $v_split): array
{
	$name = trim($name);
	if ($name === '') {
		return array('ok' => false, 'message' => 'Holiday Name is required.');
	}

	if ($status !== 'open' && $status !== 'closed') {
		$status = 'open';
	}

	if ($v_structure !== '' && !in_array($v_structure, array('flat_fee', 'door_split', 'flat_fee_door_split'), true)) {
		return array('ok' => false, 'message' => 'Vendor Structure is invalid.');
	}

	$flat = ($v_flat !== '') ? vms_holidays_sanitize_money($v_flat) : '';
	$pct  = ($v_split !== '') ? vms_holidays_sanitize_percent($v_split) : '';

	if ($v_structure !== '') {
		$has_flat = ($flat !== '' && is_numeric($flat));
		$has_pct  = ($pct !== '' && is_numeric($pct));

		if (!$has_flat && !$has_pct) {
			return array('ok' => false, 'message' => 'Vendor override: if Structure is set, enter Flat Fee and/or Door Split Percent.');
		}
	}

	if ($flat !== '' && (!is_numeric($flat) || (float) $flat < 0)) {
		return array('ok' => false, 'message' => 'Vendor override: Flat Fee must be a number ≥ 0.');
	}

	if ($pct !== '') {
		if (!is_numeric($pct)) {
			return array('ok' => false, 'message' => 'Vendor override: Door Split Percent must be numeric.');
		}
		$pv = (float) $pct;
		if ($pv < 0 || $pv > 100) {
			return array('ok' => false, 'message' => 'Vendor override: Door Split Percent must be between 0 and 100.');
		}
	}

	return array('ok' => true, 'message' => 'OK');
}

/**
 * Pure “apply” logic. No echoes. No redirects. Returns result array.
 */
function vms_admin_holidays_apply_post(string $action): array
{
	$venue_id = isset($_REQUEST['venue_id']) ? absint(wp_unslash($_REQUEST['venue_id'])) : 0;
	if ($venue_id <= 0) {
		return array('ok' => false, 'message' => 'Venue is required.');
	}

	$all = function_exists('vms_get_holidays_option') ? (array) vms_get_holidays_option() : array();
	if (!isset($all[$venue_id]) || !is_array($all[$venue_id])) {
		$all[$venue_id] = array();
	}

	// Bulk delete
	if ($action === 'bulk_delete') {
		// $confirm = isset($_POST['confirm']) ? sanitize_text_field(wp_unslash((string) $_POST['confirm'])) : '';
		$confirm = isset($_REQUEST['confirm']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['confirm'])) : '';

		if ($confirm !== '1') {
			return array('ok' => false, 'message' => 'Confirmation required.');
		}

		// $dates = (isset($_POST['holiday_dates']) && is_array($_POST['holiday_dates'])) ? $_POST['holiday_dates'] : array();
		$dates = (isset($_REQUEST['holiday_dates']) && is_array($_REQUEST['holiday_dates'])) ? $_REQUEST['holiday_dates'] : array();

		$dates = array_map(
			static function ($d) {
				return sanitize_text_field(wp_unslash((string) $d));
			},
			$dates
		);

		if (empty($dates)) {
			return array('ok' => false, 'message' => 'Select at least one holiday to delete.');
		}

		$deleted = 0;
		foreach ($dates as $d) {
			if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
				continue;
			}
			if (isset($all[$venue_id][$d])) {
				unset($all[$venue_id][$d]);
				$deleted++;
			}
		}

		if ($deleted > 0) {
			if (isset($all[$venue_id]) && empty($all[$venue_id])) {
				unset($all[$venue_id]);
			}
			update_option('vms_holidays', $all, false);
		}

		return array('ok' => true, 'message' => $deleted > 0 ? 'Deleted selected holidays.' : 'Nothing was deleted.');
	}

	$date = isset($_REQUEST['holiday_date']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['holiday_date'])) : '';
	if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
		return array('ok' => false, 'message' => 'Date is required and must be YYYY-MM-DD.');
	}

	if ($action === 'delete') {
		$confirm = isset($_REQUEST['confirm']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['confirm'])) : '';
		if ($confirm !== '1') {
			return array('ok' => false, 'message' => 'Confirmation required.');
		}

		if (isset($all[$venue_id][$date])) {
			unset($all[$venue_id][$date]);
			if (isset($all[$venue_id]) && empty($all[$venue_id])) {
				unset($all[$venue_id]);
			}
			update_option('vms_holidays', $all, false);
			return array('ok' => true, 'message' => 'Holiday deleted.');
		}
		return array('ok' => true, 'message' => 'Nothing to delete.');
	}

	// action === 'save'
	$name   = isset($_POST['holiday_name']) ? sanitize_text_field(wp_unslash((string) $_POST['holiday_name'])) : '';
	$status = isset($_POST['holiday_status']) ? sanitize_text_field(wp_unslash((string) $_POST['holiday_status'])) : 'open';
	if ($status !== 'open' && $status !== 'closed') {
		$status = 'open';
	}

	$v_structure = isset($_POST['vendor_structure']) ? sanitize_text_field(wp_unslash((string) $_POST['vendor_structure'])) : '';
	if (!in_array($v_structure, array('', 'flat_fee', 'door_split', 'flat_fee_door_split'), true)) {
		$v_structure = '';
	}

	$v_flat_raw  = isset($_POST['vendor_flat_fee_amount']) ? sanitize_text_field(wp_unslash((string) $_POST['vendor_flat_fee_amount'])) : '';
	$v_split_raw = isset($_POST['vendor_door_split_percent']) ? sanitize_text_field(wp_unslash((string) $_POST['vendor_door_split_percent'])) : '';

	$check = vms_holidays_validate_manual_fields($name, $status, $v_structure, $v_flat_raw, $v_split_raw);
	if (empty($check['ok'])) {
		return array('ok' => false, 'message' => (string) $check['message']);
	}

	$v_flat  = ($v_flat_raw !== '') ? vms_holidays_sanitize_money($v_flat_raw) : '';
	$v_split = ($v_split_raw !== '') ? vms_holidays_sanitize_percent($v_split_raw) : '';

	$rules = array();
	$vendor = array();

	if ($v_structure !== '') {
		$vendor['structure'] = $v_structure;
	}

	if ($v_flat !== '') {
		$vendor['flat_fee_amount'] = (float) $v_flat;
	}

	if ($v_split !== '') {
		$vendor['door_split_percent'] = (float) $v_split;
	}

	if (!empty($vendor)) {
		$rules['vendor'] = $vendor;
	}

	$all[$venue_id][$date] = array(
		'name'   => $name,
		'status' => $status,
		'rules'  => $rules,
	);

	update_option('vms_holidays', $all, false);

	return array('ok' => true, 'message' => 'Holiday saved.');
}

/**
 * Page renderer (GET-only).
 */
function vms_admin_holidays_page(): void
{
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have permission to access this page.', 'vms'));
	}

	$venue_id = vms_holidays_get_effective_venue_id();

	$venues = get_posts(array(
		'post_type'      => 'vms_venue',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	));

	// Default venue selection
	if ($venue_id <= 0) {
		$maybe = (int) get_user_meta(get_current_user_id(), '_vms_current_venue_id', true);
		if ($maybe > 0) {
			$venue_id = $maybe;
		} elseif (!empty($venues) && is_object($venues[0]) && !empty($venues[0]->ID)) {
			$venue_id = (int) $venues[0]->ID;
		}
	}

	// Persist selection so Holidays matches Schedule behavior
	if ($venue_id > 0) {
		update_user_meta(get_current_user_id(), '_vms_current_venue_id', $venue_id);
	}

	$all = function_exists('vms_get_holidays_option') ? (array) vms_get_holidays_option() : array();
	$venue_holidays = ($venue_id > 0 && isset($all[$venue_id]) && is_array($all[$venue_id])) ? $all[$venue_id] : array();

	if (!empty($venue_holidays)) {
		ksort($venue_holidays);
	}

	$edit_date = isset($_GET['edit_date']) ? sanitize_text_field(wp_unslash((string) $_GET['edit_date'])) : '';
	$edit_row = null;
	if ($venue_id > 0 && $edit_date && isset($venue_holidays[$edit_date]) && is_array($venue_holidays[$edit_date])) {
		$edit_row = $venue_holidays[$edit_date];
	}

	$name   = $edit_row && isset($edit_row['name']) ? (string) $edit_row['name'] : '';
	$status = $edit_row && isset($edit_row['status']) ? (string) $edit_row['status'] : 'open';
	if ($status !== 'open' && $status !== 'closed') {
		$status = 'open';
	}

	$rules  = ($edit_row && isset($edit_row['rules']) && is_array($edit_row['rules'])) ? $edit_row['rules'] : array();
	$vendor = (isset($rules['vendor']) && is_array($rules['vendor'])) ? $rules['vendor'] : array();

	$v_structure = isset($vendor['structure']) ? (string) $vendor['structure'] : '';
	if (!in_array($v_structure, array('flat_fee', 'door_split', 'flat_fee_door_split'), true)) {
		$v_structure = '';
	}

	$v_flat  = array_key_exists('flat_fee_amount', $vendor) ? $vendor['flat_fee_amount'] : '';
	$v_split = array_key_exists('door_split_percent', $vendor) ? $vendor['door_split_percent'] : '';

	// Begin output
		echo '<div class="wrap vms-holidays-admin">';
	echo '<h1>' . esc_html__('Holidays', 'vms') . '</h1>';

	// Notices (from redirects)
	if (isset($_GET['vms_ok']) && $_GET['vms_ok'] === '1') {
		$msg = isset($_GET['vms_msg']) ? rawurldecode((string) $_GET['vms_msg']) : 'Saved.';
		echo '<div class="notice notice-success"><p><strong>' . esc_html($msg) . '</strong></p></div>';
	} elseif (isset($_GET['vms_err']) && $_GET['vms_err'] === '1') {
		$msg = isset($_GET['vms_msg']) ? rawurldecode((string) $_GET['vms_msg']) : 'Error.';
		echo '<div class="notice notice-error"><p><strong>' . esc_html($msg) . '</strong></p></div>';
	}

	// Venue selector (sticky per admin user)
	$venue_id = vms_holidays_get_effective_venue_id();

		echo '<form id="vms_holidays_venue_form" class="vms-holidays-venue-form" method="get" action="' . esc_url(admin_url('admin.php')) . '">';
	echo '<input type="hidden" name="page" value="vms-holidays" />';

	echo '<label for="vms_holidays_venue_id"><strong>' . esc_html__('Venue', 'vms') . '</strong></label><br />';

		echo '<select id="vms_holidays_venue_id" class="vms-holidays-venue-select" name="venue_id" onchange="document.getElementById(\'vms_holidays_venue_form\').submit();">';
	echo '<option value="0">' . esc_html__('-- Select a Venue --', 'vms') . '</option>';

	foreach ($venues as $v) {
		if (!is_object($v) || empty($v->ID)) {
			continue;
		}

		$vid = (int) $v->ID;

		echo '<option value="' . esc_attr($vid) . '" ' . selected($venue_id, $vid, false) . '>';
		echo esc_html((string) $v->post_title);
		echo '</option>';
	}

	echo '</select> ';
	echo '<button type="submit" class="button">' . esc_html__('Load', 'vms') . '</button>';
	echo '</form>';

	if ($venue_id <= 0) {
		echo '<p class="description">' . esc_html__('Select a venue to manage its holidays.', 'vms') . '</p>';
		echo '</div>';
		return;
	}

	// Editor card
		echo '<div class="vms-holidays-editor-card">';
		echo '<h2 class="vms-holidays-editor-title">' . esc_html($edit_row ? __('Edit Holiday', 'vms') : __('Add Holiday', 'vms')) . '</h2>';

	echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
	wp_nonce_field('vms_holidays_save', 'vms_holidays_nonce');

	echo '<input type="hidden" name="action" value="vms_holidays_save" />';
	echo '<input type="hidden" name="vms_holidays_action" value="save" />';
	echo '<input type="hidden" name="venue_id" value="' . esc_attr($venue_id) . '" />';

		echo '<p class="vms-holidays-field">';
	echo '<label for="vms_holiday_date"><strong>' . esc_html__('Date', 'vms') . '</strong></label><br />';
	echo '<input type="date" id="vms_holiday_date" name="holiday_date" value="' . esc_attr($edit_date) . '" required />';
	echo '</p>';

		echo '<p class="vms-holidays-field">';
	echo '<label for="vms_holiday_name"><strong>' . esc_html__('Holiday Name', 'vms') . '</strong></label><br />';
		echo '<input type="text" id="vms_holiday_name" class="vms-holidays-name-input" name="holiday_name" value="' . esc_attr($name) . '" placeholder="' . esc_attr__('Memorial Day', 'vms') . '" required />';
	echo '</p>';

		echo '<p class="vms-holidays-field">';
	echo '<label for="vms_holiday_status"><strong>' . esc_html__('Venue Status', 'vms') . '</strong></label><br />';
		echo '<select id="vms_holiday_status" class="vms-holidays-status-select" name="holiday_status">';
	echo '<option value="open" ' . selected($status, 'open', false) . '>' . esc_html__('Open', 'vms') . '</option>';
	echo '<option value="closed" ' . selected($status, 'closed', false) . '>' . esc_html__('Closed', 'vms') . '</option>';
	echo '</select>';
	echo '<br /><span class="description">' . esc_html__('If Closed, Event Plans should not be marked Ready or Published for this venue/date.', 'vms') . '</span>';
	echo '</p>';

		echo '<hr class="vms-holidays-divider" />';
		echo '<h3 class="vms-holidays-subtitle">' . esc_html__('Vendor Pay Defaults (this holiday only)', 'vms') . '</h3>';
		echo '<p class="description vms-holidays-help">' . esc_html__('These values override venue defaults for Event Plans on this date (when filled).', 'vms') . '</p>';

		echo '<p class="vms-holidays-field">';
	echo '<label for="vms_vendor_structure"><strong>' . esc_html__('Structure', 'vms') . '</strong></label><br />';
		echo '<select id="vms_vendor_structure" class="vms-holidays-structure-select" name="vendor_structure">';
	echo '<option value="">' . esc_html__('(No override)', 'vms') . '</option>';
	echo '<option value="flat_fee" ' . selected($v_structure, 'flat_fee', false) . '>' . esc_html__('Flat Fee', 'vms') . '</option>';
	echo '<option value="door_split" ' . selected($v_structure, 'door_split', false) . '>' . esc_html__('Door Split', 'vms') . '</option>';
	echo '<option value="flat_fee_door_split" ' . selected($v_structure, 'flat_fee_door_split', false) . '>' . esc_html__('Flat Fee + Door Split', 'vms') . '</option>';
	echo '</select>';
	echo '</p>';

		echo '<p class="vms-holidays-field">';
	echo '<label for="vms_vendor_flat"><strong>' . esc_html__('Flat Fee Amount', 'vms') . '</strong></label><br />';
		echo '<input type="number" step="0.01" min="0" id="vms_vendor_flat" class="vms-holidays-number-input" name="vendor_flat_fee_amount" value="' . esc_attr($v_flat) . '" />';
	echo '</p>';

		echo '<p class="vms-holidays-field">';
	echo '<label for="vms_vendor_split"><strong>' . esc_html__('Door Split Percent', 'vms') . '</strong></label><br />';
		echo '<input type="number" step="0.01" min="0" max="100" id="vms_vendor_split" class="vms-holidays-number-input" name="vendor_door_split_percent" value="' . esc_attr($v_split) . '" /> %';
	echo '</p>';

		echo '<p class="vms-holidays-actions">';
	echo '<button type="submit" class="button button-primary">' . esc_html($edit_row ? __('Update Holiday', 'vms') : __('Add Holiday', 'vms')) . '</button> ';
	echo '<a class="button" href="' . esc_url(add_query_arg(array('page' => 'vms-holidays', 'venue_id' => $venue_id), admin_url('admin.php'))) . '">' . esc_html__('Clear', 'vms') . '</a>';
	echo '</p>';

	echo '</form>';
	echo '</div>';

	// List table
		echo '<h2 class="vms-holidays-list-title">' . esc_html__('Holidays for this Venue', 'vms') . '</h2>';

	if (empty($venue_holidays)) {
		echo '<p class="description">' . esc_html__('No holidays configured for this venue yet.', 'vms') . '</p>';
		echo '</div>';
		return;
	}

		echo '<div class="vms-holidays-table-wrap">';

	// === BULK DELETE FORM (single form, no nesting) ============================
	echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';

	// Action-specific nonce for bulk delete
	wp_nonce_field('vms_holidays_bulk_delete', 'vms_holidays_nonce');

	echo '<input type="hidden" name="action" value="vms_holidays_bulk_delete" />';
	echo '<input type="hidden" name="vms_holidays_action" value="bulk_delete" />';
	echo '<input type="hidden" name="venue_id" value="' . esc_attr($venue_id) . '" />';

	// Server-side confirm failsafe (handler must require confirm=1)
	echo '<input type="hidden" name="confirm" value="1" />';

		echo '<p class="vms-holidays-bulk-actions">';
	echo '<button type="submit" class="button" onclick="return confirm(\'Delete selected holidays?\');">' . esc_html__('Delete Selected', 'vms') . '</button>';
	echo '</p>';

	echo '<table class="widefat striped">';
	echo '<thead><tr>';
		echo '<th class="vms-holidays-col-check">';
	echo '<input type="checkbox" id="vms_holidays_select_all" />';
	echo '</th>';
	echo '<th>' . esc_html__('Date', 'vms') . '</th>';
	echo '<th>' . esc_html__('Name', 'vms') . '</th>';
	echo '<th>' . esc_html__('Status', 'vms') . '</th>';
	echo '<th>' . esc_html__('Vendor Pay Override', 'vms') . '</th>';
	echo '<th>' . esc_html__('Actions', 'vms') . '</th>';
	echo '</tr></thead>';
	echo '<tbody>';

	foreach ($venue_holidays as $d => $row) {
		if (!is_array($row)) {
			continue;
		}

		$r_name = isset($row['name']) ? (string) $row['name'] : '';
		$r_status = isset($row['status']) ? (string) $row['status'] : 'open';
		if ($r_status !== 'open' && $r_status !== 'closed') {
			$r_status = 'open';
		}

		$r_rules = (isset($row['rules']) && is_array($row['rules'])) ? $row['rules'] : array();
		$r_vendor = (isset($r_rules['vendor']) && is_array($r_rules['vendor'])) ? $r_rules['vendor'] : array();

		$override_summary = '';
		if (!empty($r_vendor)) {
			$os = array();
			if (!empty($r_vendor['structure'])) {
				$os[] = (string) $r_vendor['structure'];
			}
			if (array_key_exists('flat_fee_amount', $r_vendor) && $r_vendor['flat_fee_amount'] !== '' && $r_vendor['flat_fee_amount'] !== null) {
				$os[] = 'flat $' . (string) $r_vendor['flat_fee_amount'];
			}
			if (array_key_exists('door_split_percent', $r_vendor) && $r_vendor['door_split_percent'] !== '' && $r_vendor['door_split_percent'] !== null) {
				$os[] = 'split ' . (string) $r_vendor['door_split_percent'] . '%';
			}
			$override_summary = implode(' | ', $os);
		}

		$edit_link = add_query_arg(array(
			'page' => 'vms-holidays',
			'venue_id' => $venue_id,
			'edit_date' => $d,
		), admin_url('admin.php'));

		// Per-row delete link (no nested form), nonce-protected
		$delete_url = add_query_arg(array(
			'action' => 'vms_holidays_delete',
			'vms_holidays_action' => 'delete',
			'venue_id' => $venue_id,
			'holiday_date' => $d,
			'confirm' => '1',
		), admin_url('admin-post.php'));

		$delete_url = wp_nonce_url($delete_url, 'vms_holidays_delete', 'vms_holidays_nonce');

		echo '<tr>';

		echo '<td><input type="checkbox" class="vms_holidays_row_cb" name="holiday_dates[]" value="' . esc_attr($d) . '" /></td>';

		echo '<td><strong>' . esc_html($d) . '</strong></td>';
		echo '<td>' . esc_html($r_name) . '</td>';
		echo '<td>' . esc_html(strtoupper($r_status)) . '</td>';
		echo '<td>' . esc_html($override_summary !== '' ? $override_summary : __('(none)', 'vms')) . '</td>';

		echo '<td>';
		echo '<a class="button button-small" href="' . esc_url($edit_link) . '">' . esc_html__('Edit', 'vms') . '</a> ';
		echo '<a class="button button-small" href="' . esc_url($delete_url) . '" onclick="return confirm(\'Delete this holiday?\');">' . esc_html__('Delete', 'vms') . '</a>';
		echo '</td>';

		echo '</tr>';
	}

	echo '</tbody>';
	echo '</table>';

	// Select-all JS (inline, tiny, no external deps)
	echo '<script>
	(function() {
		var all = document.getElementById("vms_holidays_select_all");
		if (!all) return;
		all.addEventListener("change", function() {
			var boxes = document.querySelectorAll(".vms_holidays_row_cb");
			for (var i = 0; i < boxes.length; i++) {
				boxes[i].checked = all.checked;
			}
		});
	})();
	</script>';

	echo '</form>'; // end bulk delete form
	echo '</div>';  // end max-width wrapper
	echo '</div>';
}
