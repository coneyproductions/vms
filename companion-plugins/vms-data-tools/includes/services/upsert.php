<?php
defined('ABSPATH') || exit;

/**
 * Upsert vendor (create or update) using Core APIs if present, else DB fallback.
 *
 * @return array{ok:bool, vendor_id?:int, action?:string, error?:array}
 */
function vms_dt_vendor_upsert(array $row, int $matched_vendor_id = 0): array {
	// Prefer Core API if available (future-proof)
	if (function_exists('vms_vendor_upsert')) {
		$res = vms_vendor_upsert($row, $matched_vendor_id);
		if (is_array($res) && !empty($res['vendor_id'])) {
			return [
				'ok'       => true,
				'vendor_id'=> (int) $res['vendor_id'],
				'action'   => !empty($res['action']) ? (string) $res['action'] : ($matched_vendor_id ? 'updated' : 'created'),
			];
		}
		return [
			'ok'    => false,
			'error' => vms_dt_err('core_upsert_failed', 'Core vendor upsert failed.'),
		];
	}

	// DB fallback
	global $wpdb;
	$t_vendors = $wpdb->prefix . 'vms_vendors';

	$exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($t_vendors)));
	if (!$exists) {
		return [
			'ok'    => false,
			'error' => vms_dt_err('vendors_table_missing', 'Vendor table does not exist: ' . $t_vendors),
		];
	}

	$data = [
		'vendor_type'   => $row['vendor_type'],
		'display_name'  => $row['display_name'],
		'status'        => $row['status'],
		'primary_email' => $row['primary_email'],
		'primary_phone' => $row['primary_phone'],
		'external_ref'  => $row['external_ref'],
		'city'          => $row['city'],
		'state'         => $row['state'],
		'postal_code'   => $row['postal_code'],
		'country'       => $row['country'],
		'updated_at'    => current_time('mysql'),
	];

	$formats = ['%s','%s','%s','%s','%s','%s','%s','%s','%s','%s'];

	if ($matched_vendor_id > 0) {
		$wpdb->update($t_vendors, $data, ['id' => $matched_vendor_id], $formats, ['%d']);
		return [
			'ok'       => true,
			'vendor_id'=> $matched_vendor_id,
			'action'   => 'updated',
		];
	}

	// Create: generate uuid
	$uuid = wp_generate_uuid4();
	$data_create = [
		'uuid'       => $uuid,
		'created_at' => current_time('mysql'),
	] + $data;

	$formats_create = array_merge(['%s','%s'], $formats);

	$ins = $wpdb->insert($t_vendors, $data_create, $formats_create);
	if (!$ins) {
		return [
			'ok'    => false,
			'error' => vms_dt_err('db_insert_failed', 'Failed to insert vendor.'),
		];
	}

	return [
		'ok'       => true,
		'vendor_id'=> (int) $wpdb->insert_id,
		'action'   => 'created',
	];
}
