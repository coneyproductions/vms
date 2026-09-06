<?php
defined('ABSPATH') || exit;

/**
 * Attempt to find an existing vendor by matching rules.
 *
 * @return array{
 *   status:string,
 *   vendor_id?:int,
 *   match_reason?:string,
 *   candidates?:int[]
 * }
 *
 * status:
 * - matched
 * - none
 * - ambiguous
 */
function vms_dt_match_vendor(array $row): array {
	$external_ref  = vms_dt_str($row, 'external_ref');
	$email         = vms_dt_str($row, 'primary_email');
	$phone         = vms_dt_str($row, 'primary_phone');

	// Priority 1: external_ref
	if ($external_ref !== '') {
		$ids = vms_dt_vendor_lookup('external_ref', $external_ref);
		return vms_dt_match_result_from_ids($ids, 'external_ref');
	}

	// Priority 2: email
	if ($email !== '') {
		$ids = vms_dt_vendor_lookup('primary_email', $email);
		return vms_dt_match_result_from_ids($ids, 'email');
	}

	// Priority 3: phone
	if ($phone !== '') {
		$ids = vms_dt_vendor_lookup('primary_phone', $phone);
		return vms_dt_match_result_from_ids($ids, 'phone');
	}

	return ['status' => 'none'];
}

function vms_dt_match_result_from_ids(array $ids, string $reason): array {
	$ids = array_values(array_unique(array_map('intval', $ids)));

	if (count($ids) === 1) {
		return [
			'status'       => 'matched',
			'vendor_id'    => $ids[0],
			'match_reason' => $reason,
		];
	}

	if (count($ids) > 1) {
		return [
			'status'       => 'ambiguous',
			'match_reason' => $reason,
			'candidates'   => $ids,
		];
	}

	return [
		'status'       => 'none',
		'match_reason' => 'none',
	];
}

/**
 * Lookup vendors by field using Core functions if present, else DB fallback.
 *
 * @param string $field external_ref|primary_email|primary_phone
 * @param string $value normalized value
 * @return int[] vendor IDs
 */
function vms_dt_vendor_lookup(string $field, string $value): array {
	// Prefer Core public functions if you later add them.
	if ($field === 'external_ref' && function_exists('vms_vendor_find_by_external_ref')) {
		$vid = vms_vendor_find_by_external_ref($value);
		return $vid ? [(int) $vid] : [];
	}
	if ($field === 'primary_email' && function_exists('vms_vendor_find_by_email')) {
		$vid = vms_vendor_find_by_email($value);
		return $vid ? [(int) $vid] : [];
	}
	if ($field === 'primary_phone' && function_exists('vms_vendor_find_by_phone')) {
		$vid = vms_vendor_find_by_phone($value);
		return $vid ? [(int) $vid] : [];
	}

	// DB fallback (read-only)
	global $wpdb;

	$table = apply_filters('vms_dt_vendor_table', $wpdb->prefix . 'vms_vendors');

	// If the table doesn't exist, return none. This keeps preview safe even before migrations.
	$like = $wpdb->esc_like($table);
	$exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like));
	if (!$exists) {
		return [];
	}

	$allowed = ['external_ref', 'primary_email', 'primary_phone'];
	if (!in_array($field, $allowed, true)) {
		return [];
	}

	$sql = "SELECT id FROM {$table} WHERE {$field} = %s";
	$ids = $wpdb->get_col($wpdb->prepare($sql, $value));

	return is_array($ids) ? array_map('intval', $ids) : [];
}
