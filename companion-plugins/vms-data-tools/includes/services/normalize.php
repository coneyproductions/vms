<?php
defined('ABSPATH') || exit;

function vms_dt_normalize_row(array $row): array {
	// Standard columns
	$row['display_name']   = vms_dt_str($row, 'display_name');
	$row['vendor_type']    = vms_dt_str($row, 'vendor_type');
	$row['status']         = vms_dt_str($row, 'status');
	$row['external_ref']   = vms_dt_str($row, 'external_ref');
	$row['primary_email']  = vms_dt_normalize_email(vms_dt_str($row, 'primary_email'));
	$row['primary_phone']  = vms_dt_normalize_phone(vms_dt_str($row, 'primary_phone'));

	$row['city']           = vms_dt_str($row, 'city');
	$row['state']          = vms_dt_str($row, 'state');
	$row['postal_code']    = vms_dt_str($row, 'postal_code');
	$row['country']        = vms_dt_str($row, 'country');

	// Contact (optional)
	$row['contact_name']   = vms_dt_str($row, 'contact_name');
	$row['contact_role']   = vms_dt_str($row, 'contact_role');
	$row['contact_email']  = vms_dt_normalize_email(vms_dt_str($row, 'contact_email'));
	$row['contact_phone']  = vms_dt_normalize_phone(vms_dt_str($row, 'contact_phone'));

	// Default country if blank
	if ($row['country'] === '') {
		$row['country'] = 'US';
	}

	return $row;
}

function vms_dt_str(array $row, string $key): string {
	return isset($row[$key]) ? trim((string) $row[$key]) : '';
}

function vms_dt_normalize_email(string $email): string {
	$email = trim(strtolower($email));
	return $email;
}

/**
 * Very simple phone normalization for v1:
 * - keep digits only
 * - if blank, blank
 */
function vms_dt_normalize_phone(string $phone): string {
	$phone = trim($phone);
	if ($phone === '') {
		return '';
	}
	$digits = preg_replace('/\D+/', '', $phone);
	return $digits ? $digits : '';
}
 