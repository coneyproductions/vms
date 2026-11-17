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
function bvmgr_event_plan_statuses(): array {
	$statuses = [
		'draft'     => __('Draft', 'backstage-venue-manager'),
		'ready'     => __('Ready', 'backstage-venue-manager'),
		'published' => __('Published', 'backstage-venue-manager'),
		'tentative' => __('Tentative', 'backstage-venue-manager'),
		'confirmed' => __('Confirmed', 'backstage-venue-manager'),
		'cancelled' => __('Cancelled', 'backstage-venue-manager'),
		'archived'  => __('Archived', 'backstage-venue-manager'),
	];

	return apply_filters('vms_event_plan_statuses', $statuses);
}

/**
 * Vendor availability statuses (canonical).
 *
 * @return array<string,string>
 */
function bvmgr_vendor_availability_statuses(): array {
	$statuses = [
		'available'   => __('Available', 'backstage-venue-manager'),
		'unavailable' => __('Unavailable', 'backstage-venue-manager'),
		'tentative'   => __('Tentative', 'backstage-venue-manager'),
		'unknown'     => __('Unknown', 'backstage-venue-manager'),
	];

	return apply_filters('vms_vendor_availability_statuses', $statuses);
}

/**
 * Payment statuses (canonical).
 *
 * @return array<string,string>
 */
function bvmgr_payment_statuses(): array {
	$statuses = [
		'unpaid'   => __('Unpaid', 'backstage-venue-manager'),
		'partial'  => __('Partial', 'backstage-venue-manager'),
		'paid'     => __('Paid', 'backstage-venue-manager'),
		'void'     => __('Void', 'backstage-venue-manager'),
	];

	return apply_filters('vms_payment_statuses', $statuses);
}

/**
 * Cancellation reasons (optional enum).
 *
 * @return array<string,string>
 */
function bvmgr_cancellation_reasons(): array {
	$reasons = [
		'weather'         => __('Weather', 'backstage-venue-manager'),
		'low_sales'       => __('Low sales', 'backstage-venue-manager'),
		'artist_cancelled'=> __('Artist cancelled', 'backstage-venue-manager'),
		'venue_issue'     => __('Venue issue', 'backstage-venue-manager'),
		'other'           => __('Other', 'backstage-venue-manager'),
	];

	return apply_filters('vms_cancellation_reasons', $reasons);
}
