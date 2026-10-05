<?php
/**
 * Contextual operator help for Guest Pass and reusable business QR setup.
 */

defined('ABSPATH') || exit;

if (!function_exists('backstage_outreach_help_button')) {
	/**
	 * Render a launcher through BVM's existing tours framework.
	 */
	function backstage_outreach_help_button(string $tour_id, string $anchor, string $label): string
	{
		$args = array(
			'tour_id' => $tour_id,
			'anchor' => $anchor,
			'label' => $label,
			'class' => 'backstage-outreach-help-button',
		);
		if (function_exists('bvmgr_render_help_button')) {
			return bvmgr_render_help_button($args);
		}
		// Compatibility with certified BVM runtimes that still expose the legacy-prefixed alias.
		if (function_exists('vms_render_help_button')) {
			return vms_render_help_button($args);
		}
		return '';
	}
}

if (!function_exists('backstage_outreach_register_contextual_help_tours')) {
	/**
	 * @param array<int,array<string,mixed>> $tours
	 * @return array<int,array<string,mixed>>
	 */
	function backstage_outreach_register_contextual_help_tours(array $tours): array
	{
		$capability = function_exists('bvmgr_pass_claims_capability')
			? bvmgr_pass_claims_capability()
			: (function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options');
		$audience = array('capabilities_any' => array($capability));

		$tours[] = array(
			'id' => 'backstage-outreach.guest-pass-batch-setup',
			'title' => __('Guest Pass Batch Setup', 'backstage-outreach'),
			'description' => __('Prepare the Source, eligible events, and capacity used by complimentary and paid business offers.', 'backstage-outreach'),
			'screen' => 'admin:vms-passes',
			'version' => '1.0.0',
			'level' => 'beginner',
			'audience' => $audience,
			'auto_run' => false,
			'allow_restart' => true,
			'tags' => array('guest-pass', 'outreach', 'business-qr'),
			'steps' => array(
				array(
					'id' => 'batch-prerequisites',
					'selector' => '[data-vms-tour="outreach-batch-help"]',
					'title' => __('Start with the prerequisites', 'backstage-outreach'),
					'body' => __('First save a Source on the Sources tab and publish the Event Plans customers may choose. A batch defines eligible events and capacity. It does not create a WooCommerce coupon.', 'backstage-outreach'),
					'placement' => 'bottom',
				),
				array(
					'id' => 'batch-source',
					'selector' => 'select[name="source_id"]',
					'title' => __('Choose the tracking Source', 'backstage-outreach'),
					'body' => __('Select the Source that will also be linked to the Outreach campaign. If this list is empty, return to Sources and create one before continuing.', 'backstage-outreach'),
					'placement' => 'right',
				),
				array(
					'id' => 'batch-eligibility',
					'selector' => 'select[name="validity_type"]',
					'title' => __('Define eligible events', 'backstage-outreach'),
					'body' => __('Use Validity Type with Single Event, dates, season, and optional Eligible Venues to define what the offer can admit. If no event is available, publish the Event Plan first.', 'backstage-outreach'),
					'placement' => 'right',
				),
				array(
					'id' => 'batch-limits',
					'selector' => 'input[name="admissions_per_link"]',
					'title' => __('Set ticket capacity', 'backstage-outreach'),
					'body' => __('Admissions per Claimed Link and Total Admission Cap protect complimentary capacity and the shared batch ticket cap. For the intended paid offer, the later campaign limit is two eligible tickets per order.', 'backstage-outreach'),
					'placement' => 'right',
				),
				array(
					'id' => 'batch-value',
					'selector' => 'select[name="value_type"]',
					'title' => __('Choose the batch value', 'backstage-outreach'),
					'body' => __('Complimentary Guest Passes use Free. A Neighborhood Offer may share an active Free capacity batch or use Percent Off set to exactly 50%. The managed WooCommerce coupon is still created later in Outreach.', 'backstage-outreach'),
					'placement' => 'right',
				),
				array(
					'id' => 'batch-review',
					'selector' => 'button[name="generation_mode"][value="preview"]',
					'title' => __('Preview before generating', 'backstage-outreach'),
					'body' => __('Preview reviews the batch without saving. Commit + Generate Guest Passes creates individual claim links; those claim QR codes are not the reusable business referral QR.', 'backstage-outreach'),
					'placement' => 'top',
				),
			),
		);

		$tours[] = array(
			'id' => 'backstage-outreach.business-qr-setup',
			'title' => __('Guest Pass and Business QR Setup', 'backstage-outreach'),
			'description' => __('Link the campaign, choose businesses, review terms, and distribute reusable referral QRs.', 'backstage-outreach'),
			'screen' => 'admin:vms-outreach',
			'version' => '1.0.0',
			'level' => 'beginner',
			'audience' => $audience,
			'auto_run' => false,
			'allow_restart' => true,
			'tags' => array('guest-pass', 'neighborhood-offer', 'business-qr'),
			'steps' => array(
				array(
					'id' => 'outreach-sequence',
					'selector' => '[data-vms-tour="outreach-qr-help"]',
					'title' => __('Use this setup sequence', 'backstage-outreach'),
					'body' => __('Create the batch with eligible events and limits; link its Source and batch to an Outreach campaign; select businesses; choose Neighborhood Offer; Review Selection; then Save Reviewed Links. Save the campaign first if no campaign is open.', 'backstage-outreach'),
					'placement' => 'bottom',
				),
				array(
					'id' => 'campaign-linkage',
					'selector' => '#vms-outreach-campaign-form',
					'title' => __('Link the campaign', 'backstage-outreach'),
					'body' => __('Choose the Tracking Source and Use Existing Batch / Invite Link Pool, then save the campaign. If either is missing, create it on the Guest Passes screen and return here.', 'backstage-outreach'),
					'placement' => 'bottom',
				),
				array(
					'id' => 'business-selection',
					'selector' => '[data-vms-tour="outreach-business-selection"]',
					'title' => __('Select businesses', 'backstage-outreach'),
					'body' => __('Select active businesses from the campaign Source. If none appear, open that Source, add or reactivate a business, then return to the campaign.', 'backstage-outreach'),
					'placement' => 'top',
				),
				array(
					'id' => 'offer-type',
					'selector' => '[data-vms-tour="outreach-distribution-type"]',
					'title' => __('Choose free or paid', 'backstage-outreach'),
					'body' => __('Complimentary Guest Pass issues free admissions. Neighborhood Offer gives customers 50% off eligible paid tickets: they scan, select eligible tickets, receive the automatic discount, and complete normal checkout.', 'backstage-outreach'),
					'placement' => 'left',
				),
				array(
					'id' => 'offer-limits',
					'selector' => '[data-vms-tour="outreach-business-limits"]',
					'title' => __('Understand the limits', 'backstage-outreach'),
					'body' => __('Set Passes Per Recipient to 2 for “50% off admission for up to 2 people” per order. Optional ticket cap per business counts discounted tickets; Optional paid-order cap per business counts paid orders. Campaign and batch caps count eligible ticket quantities across businesses.', 'backstage-outreach'),
					'placement' => 'left',
				),
				array(
					'id' => 'review-selection',
					'selector' => '[data-vms-tour="outreach-review-selection"]',
					'title' => __('Review before saving', 'backstage-outreach'),
					'body' => __('Review Selection does not change campaign state or create a coupon. Saving the reviewed Neighborhood Offer creates or safely reuses its managed native 50% coupon. Creating the batch alone does not.', 'backstage-outreach'),
					'placement' => 'top',
				),
				array(
					'id' => 'save-reviewed-links',
					'selector' => '[data-vms-tour="outreach-reviewed-preview"]',
					'title' => __('Save the reviewed links', 'backstage-outreach'),
					'body' => __('Confirm the businesses and terms, then use Save Reviewed Links. Change the offer here in Outreach—not by directly editing its managed WooCommerce coupon.', 'backstage-outreach'),
					'placement' => 'top',
				),
				array(
					'id' => 'business-qr',
					'selector' => '[data-vms-tour="outreach-qr-actions"]',
					'title' => __('Distribute the reusable business QR', 'backstage-outreach'),
					'body' => __('Use Copy Link, QR Download, or Printable QR for each business. This reusable business referral QR starts the offer; individual claim and ticket QR codes are separate credentials for a person or the gate.', 'backstage-outreach'),
					'placement' => 'left',
				),
				array(
					'id' => 'results',
					'selector' => '[data-vms-tour="outreach-business-results"]',
					'title' => __('Track paid and free results', 'backstage-outreach'),
					'body' => __('Paid offers show native Woo coupon uses plus Outreach-attributed paid orders, discounted tickets, refunds, and revenue. Historical coupon usage can differ from currently paid orders. Complimentary results remain claims, admissions, and check-ins.', 'backstage-outreach'),
					'placement' => 'top',
				),
				array(
					'id' => 'lifecycle',
					'selector' => '[data-vms-tour="outreach-offer-lifecycle"]',
					'title' => __('Pause, revoke, or let it expire', 'backstage-outreach'),
					'body' => __('Pause, revoke, and expiry block new redemptions. Previously purchased tickets and already issued complimentary admissions remain valid.', 'backstage-outreach'),
					'placement' => 'left',
				),
			),
		);

		return $tours;
	}
}
add_filter('vms_tours_register', 'backstage_outreach_register_contextual_help_tours', 40);

if (!function_exists('backstage_outreach_render_batch_help_launcher')) {
	function backstage_outreach_render_batch_help_launcher(): void
	{
		if (!is_admin()) {
			return;
		}
		$page = isset($_GET['page']) && is_scalar($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing for contextual help.
		$tab = isset($_GET['tab']) && is_scalar($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing for contextual help.
		if ($page !== 'vms-passes' || $tab !== 'batches') {
			return;
		}
		$capability = function_exists('bvmgr_pass_claims_capability')
			? bvmgr_pass_claims_capability()
			: (function_exists('vms_pass_claims_capability') ? vms_pass_claims_capability() : 'manage_options');
		if (!current_user_can($capability)) {
			return;
		}
		$button = backstage_outreach_help_button(
			'backstage-outreach.guest-pass-batch-setup',
			'outreach-batch-help',
			__('Guest Pass & QR Setup Help', 'backstage-outreach')
		);
		if ($button === '') {
			return;
		}
		echo '<div class="notice notice-info backstage-outreach-help-notice" data-vms-tour="outreach-batch-help-context"><p><strong>' . esc_html__('Setting up business QR offers?', 'backstage-outreach') . '</strong> ' . esc_html__('Start with a Source and published eligible events; the walkthrough explains the batch and Outreach sequence.', 'backstage-outreach') . ' ' . $button . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Button HTML is produced by BVM's escaped help-button renderer.
	}
}
add_action('admin_notices', 'backstage_outreach_render_batch_help_launcher', 20);
