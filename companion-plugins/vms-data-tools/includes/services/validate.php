<?php
defined('ABSPATH') || exit;

function vms_dt_allowed_vendor_types(): array {
	return ['performer', 'staff', 'food', 'vendor', 'venue', 'other'];
}

function vms_dt_allowed_statuses(): array {
	return ['active', 'inactive', 'archived'];
}

/**
 * Validate a normalized row.
 *
 * @return array{errors:array, warnings:array, is_blocked:bool}
 */
function vms_dt_validate_row(array $row): array {
	$errors = [];
	$warnings = [];

	$display_name = vms_dt_str($row, 'display_name');
	$vendor_type  = vms_dt_str($row, 'vendor_type');
	$status       = vms_dt_str($row, 'status');

	if ($display_name === '') {
		$errors[] = vms_dt_err('missing_display_name', 'display_name is required.');
	}

	if ($vendor_type === '') {
		$errors[] = vms_dt_err('missing_vendor_type', 'vendor_type is required.');
	} elseif (!in_array($vendor_type, vms_dt_allowed_vendor_types(), true)) {
		// Per contract: fallback to vendor + warning
		$warnings[] = vms_dt_warn('vendor_type_invalid', 'vendor_type is invalid and will default to vendor.');
	}

	if ($status !== '' && !in_array($status, vms_dt_allowed_statuses(), true)) {
		$errors[] = vms_dt_err('invalid_status', 'status must be active, inactive, or archived.');
	}

	// Email validation if present
	$primary_email = vms_dt_str($row, 'primary_email');
	if ($primary_email !== '' && !is_email($primary_email)) {
		$errors[] = vms_dt_err('invalid_email', 'primary_email is not a valid email.');
	}

	$contact_email = vms_dt_str($row, 'contact_email');
	if ($contact_email !== '' && !is_email($contact_email)) {
		$errors[] = vms_dt_err('invalid_contact_email', 'contact_email is not a valid email.');
	}

	// Contact cluster rule
	$has_any_contact_field = (
		vms_dt_str($row, 'contact_name') !== '' ||
		vms_dt_str($row, 'contact_role') !== '' ||
		vms_dt_str($row, 'contact_email') !== '' ||
		vms_dt_str($row, 'contact_phone') !== ''
	);

	if ($has_any_contact_field && vms_dt_str($row, 'contact_name') === '') {
		$errors[] = vms_dt_err('contact_name_required', 'contact_name is required when any contact_* field is present.');
	}

	// Matching key requirement is enforced after matching decision:
	// For create actions, we require at least one of external_ref, primary_email, primary_phone.

	return [
		'errors'     => $errors,
		'warnings'   => $warnings,
		'is_blocked' => count($errors) > 0,
	];
}
