<?php
defined('ABSPATH') || exit;

/**
 * Upsert primary contact if contact fields exist.
 */
function vms_dt_vendor_upsert_primary_contact(int $vendor_id, array $row): array {
	$has_any = (
		$row['contact_name'] !== '' ||
		$row['contact_role'] !== '' ||
		$row['contact_email'] !== '' ||
		$row['contact_phone'] !== ''
	);

	if (!$has_any) {
		return ['ok' => true, 'did' => false];
	}

	// Prefer Core API if available
	if (function_exists('vms_vendor_upsert_primary_contact')) {
		vms_vendor_upsert_primary_contact($vendor_id, [
			'name'  => $row['contact_name'],
			'role'  => $row['contact_role'],
			'email' => $row['contact_email'],
			'phone' => $row['contact_phone'],
		]);
		return ['ok' => true, 'did' => true];
	}

	// DB fallback
	global $wpdb;
	$t_contacts = $wpdb->prefix . 'vms_vendor_contacts';

	$exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($t_contacts)));
	if (!$exists) {
		return [
			'ok' => false,
			'error' => vms_dt_err('vendor_contacts_table_missing', 'Vendor contacts table does not exist: ' . $t_contacts),
		];
	}

	// Find existing primary
	$existing_id = (int) $wpdb->get_var(
		$wpdb->prepare("SELECT id FROM {$t_contacts} WHERE vendor_id=%d AND is_primary=1 LIMIT 1", $vendor_id)
	);

	$data = [
		'vendor_id'   => $vendor_id,
		'name'        => $row['contact_name'],
		'role'        => $row['contact_role'],
		'email'       => $row['contact_email'],
		'phone'       => $row['contact_phone'],
		'is_primary'  => 1,
		'notes'       => '',
	];

	$formats = ['%d','%s','%s','%s','%s','%d','%s'];

	if ($existing_id > 0) {
		$wpdb->update($t_contacts, $data, ['id' => $existing_id], $formats, ['%d']);
		return ['ok' => true, 'did' => true];
	}

	$wpdb->insert($t_contacts, $data, $formats);
	return ['ok' => true, 'did' => true];
}
