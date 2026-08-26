<?php
/**
 * VMS CSV Contracts
 *
 * Central definition of required columns and column mapping rules so importers do not drift.
 */

defined('ABSPATH') || exit;

/**
 * Vendor CSV required columns (canonical).
 *
 * @return string[]
 */
function vms_csv_vendor_required_columns(): array {
	$required = [
		'display_name',
	];

	return apply_filters('vms_csv_vendor_required_columns', $required);
}

/**
 * Vendor CSV recommended columns (non-fatal if missing).
 *
 * @return string[]
 */
function vms_csv_vendor_recommended_columns(): array {
	$recommended = [
		'email',
		'phone',
		'contact_name',
		'notes',
	];

	return apply_filters('vms_csv_vendor_recommended_columns', $recommended);
}

/**
 * Vendor CSV column to meta mapping.
 *
 * Keys are CSV headers. Values are meta keys or special handlers.
 *
 * @return array<string,string>
 */
function vms_csv_vendor_column_map(): array {
	$map = [
		'display_name' => BVMGR_META_VENDOR_DISPLAY_NAME,
		'contact_name' => BVMGR_META_VENDOR_CONTACT_NAME,
		'email'        => BVMGR_META_VENDOR_EMAIL,
		'phone'        => BVMGR_META_VENDOR_PHONE,
		// 'notes' could map to a meta key you define later, or a post_content handler.
	];

	return apply_filters('vms_csv_vendor_column_map', $map);
}

/**
 * Event Plan CSV required columns.
 *
 * @return string[]
 */
function vms_csv_event_plan_required_columns(): array {
	$required = [
		'event_key',
		'event_date',
		'venue_name',
		'primary_vendor_name',
	];

	return apply_filters('vms_csv_event_plan_required_columns', $required);
}

/**
 * Event Plan CSV recommended columns.
 *
 * @return string[]
 */
function vms_csv_event_plan_recommended_columns(): array {
	$recommended = [
		'event_title',
		'start_time',
		'end_time',
		'agenda_text',
		'comp_structure',
		'flat_fee_amount',
		'door_split_percent',
		'attendance_bonus_mode',
		'attendance_bonus_start_count',
		'attendance_bonus_step_size',
		'attendance_bonus_step_bonus',
		'attendance_bonus_per_ticket_rate',
		'attendance_bonus_max_bonus',
		'secondary_vendor_type',
		'secondary_vendor_1',
		'secondary_vendor_2',
		'secondary_vendor_3',
	];

	return apply_filters('vms_csv_event_plan_recommended_columns', $recommended);
}
