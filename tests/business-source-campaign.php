<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$outreach = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/admissions/outreach.php');
$distribution = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/business-distribution.php');
$discounts = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/business-discount-offers.php');
$recipients = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/admissions/outreach-recipients.php');
$db = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/outreach/db.php');
$css = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/assets/css/outreach-admin.css');
$js = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/assets/js/outreach-admin.js');
$plugin = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/backstage-outreach.php');
$integration = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/integration-bvm.php');
$claims = (string) file_get_contents($root . '/includes/modules/admissions/pass-claims.php');
$discount_router_start = strpos($discounts, 'function backstage_outreach_discount_offer_router');
$discount_router_end = strpos($discounts, 'function backstage_outreach_discount_order_context', (int) $discount_router_start);
$discount_router = $discount_router_start !== false && $discount_router_end !== false ? substr($discounts, $discount_router_start, $discount_router_end - $discount_router_start) : '';

$helper_start = strpos($outreach, "if (!function_exists('vms_pass_outreach_create_business_source_campaign'))");
$helper_end = strpos($outreach, "if (!function_exists('vms_pass_outreach_sanitize_campaign_payload'))", (int) $helper_start);
$helper = $helper_start !== false && $helper_end !== false ? substr($outreach, $helper_start, $helper_end - $helper_start) : '';

$assertions = array(
	'Reusable business route is an explicit create mode' => strpos($outreach, "'business_source'") !== false && strpos($outreach, 'Reusable Business Links / QRs') !== false,
	'Route choice precedes route-specific campaign fields' => strpos($outreach, 'Delivery / Recipient Route') < strpos($outreach, "vms_pass_outreach_render_collapsible_summary(__('Campaign'") && strpos($outreach, 'syncBusinessOfferDraft') !== false && strpos($outreach, 'business_batch_touched_fields') !== false,
	'Preview reads current active reusable memberships' => strpos($outreach, 'backstage_outreach_source_businesses($source_id, false)') !== false,
	'Preview binds Source and existing batch' => strpos($outreach, 'vms_pass_outreach_build_business_source_preview(int $source_id, int $batch_id)') !== false && strpos($outreach, 'batch_source_mismatch') !== false,
	'Batch picker uses canonical eligibility and Source filtering' => strpos($outreach, 'vms_pass_outreach_business_batch_eligibility_error') !== false && strpos($outreach, 'vms_pass_outreach_eligible_business_batches') !== false && strpos($outreach, 'foreach ($eligible_business_batches as $batch)') !== false,
	'Client picker filters on Source changes and disables invalid preview' => strpos($outreach, 'function syncBusinessBatches') !== false && strpos($outreach, 'data-vms-business-preview-button') !== false && strpos($outreach, 'preview.disabled=sourceId<=0||!entry') !== false,
	'Server retains forged and stale pair enforcement' => strpos($outreach, 'vms_pass_outreach_business_batch_eligibility_error($batch, $related_source_id)') !== false && strpos($outreach, 'businessReviewInvalidated') !== false,
	'Missing-batch guidance is one accessible empty state with one action' => substr_count($outreach, "__('No eligible offer batch for this Source'") === 1 && substr_count($outreach, "__('Create an offer batch for this Source'") === 1 && strpos($outreach, 'role="status" aria-live="polite" data-vms-business-empty') !== false,
	'New batch uses review and explicit confirmation without token generation' => strpos($outreach, "'business_batch_preview'") !== false && strpos($outreach, "'business_batch_commit'") !== false && strpos($outreach, "'generated_count' => 0") !== false && strpos($outreach, 'Confirm and Create Batch Definition') !== false,
	'Batch setup uses definition-only core validation with true zero quantity' => substr_count($outreach, 'bvmgr_pass_claims_sanitize_batch_definition_payload') >= 2 && strpos($outreach, "'quantity' => 0") !== false && strpos($outreach, 'name="business_batch_quantity"') === false && strpos($claims, "'definition_only' => true") !== false,
	'Normal individual-link validation and generation remain intact' => strpos($claims, "if (!\$definition_only && (\$quantity < 1 || \$quantity > 5000))") !== false && strpos($claims, 'bvmgr_pass_claims_generate_tokens_for_batch') !== false,
	'Stable error anchor and accessible focus are implemented' => strpos($outreach, "return 'vms-outreach-business-source-setup';") !== false && strpos($outreach, 'aria-invalid="true"') !== false && strpos($js, 'scrollToAdminTarget(target, true)') !== false,
	'Business workflow shows ordered prerequisites' => strpos($outreach, 'Reusable-business campaign setup steps') !== false && strpos($outreach, 'Business QR generation / print / export') !== false && strpos($distribution, 'Step 5 — Generate, Print, or Export Business QRs') !== false,
	'Preview lists every business rather than a sample' => strpos($outreach, 'foreach ($business_source_rows as $business_row)') !== false && strpos($outreach, 'vms-pass-business-source-table') !== false,
	'Blank email remains link eligible and delivery is not implied' => strpos($outreach, 'Email Delivery Unavailable') !== false && strpos($outreach, 'Reference only - email delivery is not prepared') !== false,
	'Business membership drift blocks creation' => strpos($helper, 'business_source_changed') !== false && strpos($helper, 'hash_equals') !== false,
	'Business create helper does not insert recipients or contacts' => $helper !== '' && strpos($helper, 'insert_prepared_recipients') === false && strpos($helper, 'vms_outreach_upsert_contact') === false,
	'Business creation preserves reviewed source and batch' => strpos($helper, "'related_source_id' => absint(\$current_preview['source_id']") !== false && strpos($helper, "'related_batch_id' => absint(\$current_preview['batch_id']") !== false,
	'Business flow uses plain-language per-customer and total labels' => substr_count($outreach, 'Total admissions available across all businesses') >= 3 && substr_count($distribution, 'Admissions per customer') >= 1,
	'Per-business admission limit is reviewed and handed to existing enforcement' => substr_count($outreach, 'Total admissions allowed per business') >= 3 && strpos($outreach, 'business_admission_cap') !== false && strpos($distribution, "'admission_cap' => \$cap") !== false && strpos($distribution, 'vms_pass_outreach_business_distribution_handoff_key') !== false,
	'Business review state does not reuse zero-recipient totals' => strpos($outreach, 'data-vms-business-review-summary') !== false && strpos($outreach, 'data-vms-individual-review-summary') !== false && strpos($outreach, 'Business Review becomes available after confirmation') !== false,
	'Conditional business scope controls are server and client enforced' => strpos($outreach, 'data-vms-business-scope="single_event"') !== false && strpos($outreach, 'data-vms-business-scope="date_range season"') !== false && strpos($outreach, 'function updateBusinessBatchScope') !== false && strpos($claims, 'Definition-only workflows ignore fields outside the selected scope.') !== false,
	'Complimentary business claims use internal transactional tokens' => strpos($distribution, 'bvmgr_pass_claims_create_internal_claim_token') !== false && strpos($claims, 'does not change the') !== false,
	'Create action continues to QR setup' => strpos($outreach, 'Create Campaign and Continue to Business QR Setup') !== false && strpos($outreach, "'#backstage-outreach-partners'") !== false,
	'Business campaign creation has one Step 4 section with its name and action' => substr_count($outreach, 'Step 4 — Create Campaign') === 1 && strpos($outreach, 'Step 4 — Campaign Creation') === false && strpos($outreach, 'vms-outreach-business-create-heading') !== false && strpos($outreach, 'data-vms-nonbusiness-create-actions') !== false,
	'New QR setup reviews all active businesses by default' => strpos($distribution, 'empty($rows) || (isset($by_business[$id])') !== false,
	'Step 5 renders pending review values inside workflow content' => strpos($distribution, '$pending = is_array($preview) ? $preview') !== false && strpos($distribution, 'id="backstage-outreach-business-review" class="vms-pass-preview-summary') !== false && strpos($distribution, 'notice notice-info inline" data-vms-tour="outreach-reviewed-preview') === false,
	'Step 5 review is exact, expiring, and membership-bound' => strpos($distribution, 'review_token') !== false && strpos($distribution, 'membership_digest') !== false && strpos($distribution, 'backstage_outreach_business_membership_digest($allowed)') !== false,
	'Admission limits are grouped with shared-pool qualification' => strpos($outreach, 'data-vms-business-limit-group') !== false && strpos($distribution, 'class="vms-pass-span-2 vms-pass-limit-group"') !== false && substr_count($outreach, 'A per-business maximum does not reserve admissions') >= 1,
	'Business route is identified independently of recipient count' => strpos($distribution, 'function backstage_outreach_is_reusable_business_campaign') !== false && strpos($distribution, "'pass_outreach_business_campaign_create'") !== false && strpos($recipients, '$historical_recipient_count <= 0') !== false,
	'Business campaign management replaces recipient-import prerequisites' => strpos($recipients, 'Use the linked Source businesses') !== false && strpos($recipients, 'Continue to Business Links & Sharing') !== false && strpos($recipients, '!$is_business_campaign') !== false,
	'Business sharing is personalized and does not create recipients' => strpos($distribution, 'function backstage_outreach_business_share_context') !== false && strpos($distribution, 'Customer offer URL') !== false && strpos($distribution, 'Printable flyer URL') !== false && strpos($distribution, 'This workflow does not create Outreach recipients') !== false,
	'Business email delivery is reviewed, suppressed, audited, duplicate-safe, and reports mail handoff accurately' => strpos($distribution, 'share_review_token') !== false && strpos($distribution, 'vms_outreach_email_is_suppressed') !== false && strpos($distribution, 'outreach_business_share_email_handed_off') !== false && strpos($distribution, 'delivery not confirmed') !== false && strpos($distribution, 'isset($sent_map[$distribution_id])') !== false,
	'Individual recipient tools document CSV and saved-contact selection' => strpos($recipients, 'Required column:') !== false && strpos($recipients, 'Recipient CSV template / example') !== false && strpos($recipients, 'Select saved Outreach contacts') !== false && strpos($recipients, 'autocomplete="off"') !== false,
	'Paid offers have neutral value-specific wording' => strpos($outreach, 'Admission Offer — %1$s%% off admission') !== false && strpos($outreach, 'Admission Offer — $%1$s off each admission') !== false && strpos($distribution, '$paid_type_label') !== false,
	'Percentage and fixed batches default to the paid setup path' => strpos($distribution, '$batch_defaults_to_paid') !== false && strpos($distribution, "array('percent', 'fixed')") !== false && strpos($distribution, "'coupon_backed' : 'complimentary'") !== false,
	'Offer amount is conditional and accessible' => strpos($outreach, 'data-vms-business-offer-amount') !== false && strpos($outreach, 'function updateBusinessOffer') !== false && strpos($outreach, 'greater than 0 and up to 100') !== false,
	'Date and expiry fields stay compact on mobile' => strpos($css, 'width: min(12rem, 100%)') !== false && strpos($css, 'width: min(19rem, 100%)') !== false,
	'Shareable flyer uses the signed distribution identity and read-only route' => strpos($distribution, '/guest-pass/business-flyer/') !== false && strpos($distribution, 'backstage_outreach_distribution_context($raw_token)') !== false && strpos($distribution, 'backstage_outreach_distribution_flyer_router') !== false && strpos($distribution, 'backstage_partner_submit') !== false,
	'Flyer contains public-safe reception and print details' => strpos($distribution, 'Print / Save as PDF') !== false && strpos($distribution, '@page{size:letter portrait') !== false && strpos($distribution, 'Printable flyer') !== false && strpos($distribution, 'Maximum through this business') !== false && strpos($distribution, 'Research note') === false,
	'Flyer state and privacy are enforced without admin credentials' => strpos($distribution, 'backstage_outreach_distribution_flyer_context') !== false && strpos($distribution, 'partner_flyer_expired') !== false && strpos($distribution, '<meta name="robots" content="noindex,nofollow">') !== false,
	'Flyer design supports venue defaults and campaign overrides without changing signed links' => strpos($distribution, 'backstage_outreach_flyer_design_default') !== false && strpos($distribution, 'backstage_outreach_flyer_design_campaign_') !== false && strpos($distribution, 'campaign_artwork_mode') !== false && strpos($distribution, 'Existing business links now use the updated presentation') !== false,
	'Artwork remains separate printable image content' => strpos($distribution, '<img class="artwork"') !== false && strpos($distribution, 'background-image') === false && strpos($distribution, 'never baked into this image') !== false,
	'Business link controls are grouped by customer offer, flyer, and lifecycle' => strpos($distribution, "__('Customer offer'") !== false && strpos($distribution, "__('Printable flyer'") !== false && strpos($distribution, "__('Manage'") !== false && strpos($distribution, 'vms-pass-business-action-groups') !== false,
	'Draft activation guidance targets the existing status control without activating' => strpos($distribution, 'data-vms-open-section-target="vms-outreach-campaign-status"') !== false && strpos($outreach, 'id="vms-outreach-campaign-status"') !== false,
	'Paid offer page leads with venue benefit and chronological ticket choices' => strpos($discounts, 'usort($events') !== false && strpos($discounts, 'featured_image_url') !== false && strpos($discounts, "__('Choose tickets'") !== false && strpos($discounts, "__('Shared by %s'") !== false,
	'Public offer wording avoids implementation jargon' => $discount_router !== '' && strpos($discount_router, 'Commerce Discount') === false && strpos($discount_router, 'managed coupon') === false && strpos($discounts, 'automatic ticket discount') !== false,
	'Unavailable business pages retain signed-state status and venue navigation' => strpos($distribution, 'backstage_outreach_render_public_offer_status') !== false && strpos($distribution, 'Visit the venue homepage') !== false && strpos($distribution, '$status === 404 ? 404 : 410') !== false,
	'Message preview is prominently sample-only' => strpos($outreach, 'SAMPLE DATA - NOT A DELIVERY PREVIEW') !== false,
	'Current defaults avoid transport-sensitive punctuation' => strpos($outreach, "'You\\'ve been invited") !== false && strpos($outreach, 'You’ve been invited') === false,
	'Legacy mojibake is repaired for display without a bulk update' => strpos($outreach, 'function vms_pass_outreach_display_text') !== false && strpos($outreach, 'Stored campaign history is not rewritten') !== false && strpos($helper, '$wpdb->update') === false,
	'Legitimate Unicode is not normalized as mojibake' => strpos($outreach, '$typographic_map') === false,
	'Narrow-screen business rows use labeled cards' => strpos($css, '.vms-pass-business-source-table td::before') !== false && strpos($css, 'content: attr(data-label)') !== false,
	'Narrow-screen workflow does not overflow' => strpos($css, '.vms-pass-business-steps') !== false && strpos($css, 'grid-template-columns: 1fr') !== false && strpos($css, 'overflow-wrap: anywhere') !== false,
	'Business identity remains independent of email' => strpos($distribution, 'UNIQUE KEY public_id (public_id)') !== false && strpos($distribution, 'UNIQUE KEY email') === false,
	'Outreach 1.2.9 owns reproducible asset cache keys' => strpos($plugin, 'Version: 1.2.9') !== false && strpos($plugin, "BACKSTAGE_OUTREACH_VERSION', '1.2.9'") !== false && substr_count($integration, 'BACKSTAGE_OUTREACH_VERSION') >= 2 && strpos($integration, 'filemtime(') === false,
	'Schema versions remain unchanged' => strpos($distribution, "\$target = '1.2.1';") !== false && strpos($db, "return '1.1.0';") !== false,
);

$failed = array_keys(array_filter($assertions, static fn(bool $passed): bool => !$passed));
if ($failed) {
	throw new RuntimeException("Business Source campaign assertions failed:\n- " . implode("\n- ", $failed));
}

echo 'Business Source campaign PASS (' . count($assertions) . " assertions)\n";
