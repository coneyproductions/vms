<?php
/**
 * VMS Status Registries
 */

defined('ABSPATH') || exit;

/**
 * Event Plan statuses (canonical).
 *
 * @return array<string,string> key => human label
 */
function vms_event_plan_statuses(): array {
	$statuses = [
		'draft'     => vms_i18n_runtime('Draft', VMS_TEXTDOMAIN),
		'ready'     => vms_i18n_runtime('Ready', VMS_TEXTDOMAIN),
		'published' => vms_i18n_runtime('Published', VMS_TEXTDOMAIN),
		'tentative' => vms_i18n_runtime('Tentative', VMS_TEXTDOMAIN),
		'confirmed' => vms_i18n_runtime('Confirmed', VMS_TEXTDOMAIN),
		'cancelled' => vms_i18n_runtime('Cancelled', VMS_TEXTDOMAIN),
		'archived'  => vms_i18n_runtime('Archived', VMS_TEXTDOMAIN),
	];

	return apply_filters('vms_event_plan_statuses', $statuses);
}

/**
 * Vendor availability statuses (canonical).
 *
 * @return array<string,string>
 */
function vms_vendor_availability_statuses(): array {
	$statuses = [
		'available'   => vms_i18n_runtime('Available', VMS_TEXTDOMAIN),
		'unavailable' => vms_i18n_runtime('Unavailable', VMS_TEXTDOMAIN),
		'tentative'   => vms_i18n_runtime('Tentative', VMS_TEXTDOMAIN),
		'unknown'     => vms_i18n_runtime('Unknown', VMS_TEXTDOMAIN),
	];

	return apply_filters('vms_vendor_availability_statuses', $statuses);
}

/**
 * Payment statuses (canonical).
 *
 * @return array<string,string>
 */
function vms_payment_statuses(): array {
	$statuses = [
		'unpaid'   => vms_i18n_runtime('Unpaid', VMS_TEXTDOMAIN),
		'partial'  => vms_i18n_runtime('Partial', VMS_TEXTDOMAIN),
		'paid'     => vms_i18n_runtime('Paid', VMS_TEXTDOMAIN),
		'void'     => vms_i18n_runtime('Void', VMS_TEXTDOMAIN),
	];

	return apply_filters('vms_payment_statuses', $statuses);
}

/**
 * Cancellation reasons (optional enum).
 *
 * @return array<string,string>
 */
function vms_cancellation_reasons(): array {
	$reasons = [
		'weather'         => vms_i18n_runtime('Weather', VMS_TEXTDOMAIN),
		'low_sales'       => vms_i18n_runtime('Low sales', VMS_TEXTDOMAIN),
		'artist_cancelled'=> vms_i18n_runtime('Artist cancelled', VMS_TEXTDOMAIN),
		'venue_issue'     => vms_i18n_runtime('Venue issue', VMS_TEXTDOMAIN),
		'other'           => vms_i18n_runtime('Other', VMS_TEXTDOMAIN),
	];

	return apply_filters('vms_cancellation_reasons', $reasons);
}
