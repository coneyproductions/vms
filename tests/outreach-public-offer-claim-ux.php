<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$distribution = file_get_contents($root . '/companion-plugins/backstage-outreach/includes/business-distribution.php');
$discounts = file_get_contents($root . '/companion-plugins/backstage-outreach/includes/business-discount-offers.php');
$publicCss = file_get_contents($root . '/companion-plugins/backstage-outreach/assets/css/outreach-public.css');
$publicJs = file_get_contents($root . '/companion-plugins/backstage-outreach/assets/js/outreach-public.js');
$passClaims = file_get_contents($root . '/includes/modules/admissions/pass-claims.php');
if (!is_string($distribution) || !is_string($discounts) || !is_string($publicCss) || !is_string($publicJs) || !is_string($passClaims)) {
	throw new RuntimeException('Could not read public offer claim UX sources.');
}

$assertions = array(
	'Single eligible events are server-associated and rendered read-only' => strpos($distribution, 'count($events) === 1') !== false && strpos($distribution, 'name="event_plan_id" value=') !== false && strpos($distribution, "__('Your event'") !== false,
	'Multi-event offers retain an eligible-event selector' => strpos($distribution, "__('Choose an eligible event'") !== false && strpos($distribution, 'foreach ($events as $event)') !== false,
	'Fresh server state revalidates event eligibility inside the claim transaction' => strpos($distribution, '$fresh_events = bvmgr_pass_claims_eligible_events_for_batch($fresh_batch);') !== false && strpos($distribution, "new WP_Error('invalid_event'") !== false,
	'Quantity metadata remains a maximum rather than an invented fixed mode' => strpos($distribution, "['admissions_per_link']") !== false && strpos($distribution, 'quantity_mode') === false,
	'One valid admission is read-only while larger limits use bounded options' => strpos($distribution, '$max === 1') !== false && strpos($distribution, 'type="hidden" name="party_size" value="1"') !== false && strpos($distribution, 'for ($quantity = 1; $quantity <= $max; $quantity += 1)') !== false,
	'Redundant bounded-quantity helper copy is absent' => strpos($distribution, 'Choose 1 to %d. This offer allows up to %d complimentary admissions.') === false,
	'New forms default to the maximum eligible quantity' => strpos($distribution, "'party_size' => \$max_party_size") !== false,
	'Fresh server state revalidates quantity inside the claim transaction' => strpos($distribution, "new WP_Error('invalid_party_size'") !== false && substr_count($distribution, 'Party size must be between 1 and %d.') >= 2,
	'Complimentary presentation names the benefit and quantity' => strpos($distribution, 'complimentary admission') !== false && strpos($distribution, 'data-backstage-claim-button') !== false,
	'Claim button follows the selected valid quantity' => strpos($publicJs, '[data-backstage-claim-quantity]') !== false && strpos($publicJs, "replace('%d'") !== false,
	'Confusing copied-contact explanation is removed' => strpos($distribution, 'contact information is never copied into this form') === false,
	'Referral attribution remains subtle and visible' => strpos($distribution, 'vms-pass-referral') !== false && strpos($distribution, "__('Shared by %s'") !== false,
	'Claim attribution remains persisted internally' => strpos($distribution, "'referring_business_id'") !== false && strpos($distribution, "'outreach_distribution_id'") !== false,
	'Email is client-required while phone remains optional' => strpos($distribution, 'type="email" name="email" autocomplete="email"') !== false && strpos($distribution, 'autocomplete="email" value=') !== false && strpos($distribution, 'autocomplete="tel" value=') !== false && strpos($distribution, "esc_html__('Phone (optional)'") !== false,
	'Email purpose separates confirmation from marketing enrollment' => strpos($distribution, 'Required for pass confirmation and event-related communication. This does not enroll you in marketing.') !== false,
	'Server policy requires valid email and explicitly permits missing phone' => strpos($distribution, "new WP_Error('invalid_claimant_email'") !== false && strpos($distribution, "'require_phone' => false") !== false && strpos($distribution, "'require_email' => true") !== false && strpos($passClaims, "new WP_Error('invalid_claim_email'") !== false,
	'Marketing remains optional and unchecked by default' => strpos($distribution, "'opt_in' => 0") !== false && strpos($distribution, 'occasional venue news and marketing emails (optional).') !== false,
	'Marketing checkbox and label stay centered inline' => strpos($publicCss, '.vms-pass-checkbox') !== false && strpos($publicCss, 'flex-direction: row') !== false && strpos($publicCss, 'align-items: center') !== false,
	'Single-event artwork resolves through the linked TEC featured image with accessible alt text' => strpos($distribution, 'function backstage_outreach_partner_event_artwork') !== false && strpos($distribution, "get_post_meta(\$event_plan_id, '_vms_tec_event_id'") !== false && strpos($distribution, "'_wp_attachment_image_alt'") !== false,
	'Multi-event artwork follows the selected option and starts neutral' => strpos($distribution, 'data-backstage-event-preview') !== false && strpos($distribution, 'data-event-image=') !== false && strpos($publicJs, 'updateEventPreview') !== false && strpos($publicJs, 'option.dataset.eventImage') !== false,
	'Discount pages use Neighborhood Offer and Discount Voucher language' => strpos($discounts, "__('Neighborhood Offer'") !== false && strpos($discounts, 'Discount Voucher') !== false,
	'Discount pages name the discount cap without implying complimentary admission' => strpos($discounts, 'up to %2$d discounted admissions') !== false && strpos($discounts, 'admissions are not complimentary or already purchased') !== false,
	'Single-event discounts receive a prominent event summary' => strpos($discounts, 'vms-offer-event-highlight') !== false && strpos($discounts, "__('Valid for this event'") !== false,
	'Mobile claim layout keeps full-width actions and compact spacing' => strpos($publicCss, '@media (max-width: 480px)') !== false && strpos($publicCss, 'width: 100%') !== false,
);

$failed = array_keys(array_filter($assertions, static fn(bool $passed): bool => !$passed));
if ($failed) {
	throw new RuntimeException("Public offer claim UX assertions failed:\n- " . implode("\n- ", $failed));
}

echo 'Outreach public offer claim UX PASS (' . count($assertions) . " assertions)\n";
