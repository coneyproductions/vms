<?php
/** Source-level contract for the reviewed Party adoption and distribution workflow. */

$root = dirname(__DIR__);
$plugin = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/backstage-outreach.php');
$party = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/party-directory.php');
$workflow = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/party-bulk-workflows.php');
$admin = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/party-directory-admin.php');
$discounts = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/business-discount-offers.php');

$checks = array(
	'Candidate identity is 1.2.18' => str_contains($plugin, "Version: 1.2.18") && str_contains($plugin, "BACKSTAGE_OUTREACH_VERSION', '1.2.18"),
	'Party schema is additive 1.2 with a contact activity ledger' => str_contains($party, "return '1.2.0';") && str_contains($party, "'contact_activities'") && str_contains($party, 'UNIQUE KEY request_key'),
	'Workflow module is loaded after typed Party referrals' => strpos($plugin, "includes/party-paid-referrals.php") < strpos($plugin, "includes/party-bulk-workflows.php"),
	'Directory index never calls the per-Party referral renderer with null' => str_contains($admin, "is_array(\$party) && function_exists('backstage_outreach_party_referral_render_panel')"),
	'Adoption is bounded and requires a preview' => str_contains($workflow, 'min(1000, $limit)') && str_contains($workflow, 'party_adoption_scope_too_large') && str_contains($workflow, 'party_adoption_preview') && str_contains($workflow, 'review_token'),
	'Commit handlers enforce server-side explicit confirmation' => str_contains($workflow, "empty(\$_POST['confirm_adoption'])") && str_contains($workflow, "empty(\$_POST['confirm_campaign'])") && str_contains($workflow, "empty(\$_POST['confirm_links'])") && str_contains($workflow, "empty(\$_POST['confirm_handoff'])"),
	'Strong historical contact IDs are the only automatic grouping key' => str_contains($workflow, "'contact-' . \$contact_id") && str_contains($workflow, "'recipient-' . absint(\$row['id'])"),
	'Name email and phone collisions are review warnings, not merge keys' => str_contains($workflow, 'Shared %s appears under more than one historical identity') && str_contains($workflow, 'identity_reviewed'),
	'Adoption preserves immutable recipient IDs through legacy links' => str_contains($workflow, "backstage_outreach_party_confirm_legacy_link(\$party_id, 'campaign_recipient'") && str_contains($workflow, "'historical_recipient'"),
	'Reviewed legacy Contact links can safely reuse an existing Party' => str_contains($workflow, "backstage_outreach_party_get_legacy_link('outreach_contact', \$contact_id)"),
	'Adoption is resumable by provenance and audited request keys' => str_contains($workflow, "'recipient-adoption:'") && str_contains($workflow, "'bulk-adopt|'"),
	'No reusable business is created by the Party workflow' => !str_contains($workflow, "backstage_outreach_business_table('businesses')") && !str_contains($workflow, "backstage_outreach_business_save"),
	'Partner setup fixes the offer at 50 percent and two discounted admissions' => str_contains($workflow, "'value_type' => 'percent'") && str_contains($workflow, "'value_amount' => 50") && str_contains($workflow, "'admissions_per_link' => 2"),
	'Partner campaign requires an explicit total cap' => str_contains($workflow, 'partner_campaign_cap_required') && str_contains($workflow, 'total_admission_cap'),
	'New Partner batch is definition-only with no claim tokens' => str_contains($workflow, "'quantity' => 0") && str_contains($workflow, 'vms_pass_outreach_create_business_offer_batch') && !str_contains($workflow, 'bvmgr_pass_claims_generate_tokens_for_batch'),
	'Partner campaign path does not call reusable-business membership review' => !str_contains($workflow, 'vms_pass_outreach_build_business_source_preview') && !str_contains($workflow, 'source_businesses'),
	'Bulk link generation reuses the typed referral engine' => str_contains($workflow, 'backstage_outreach_party_referral_validate') && str_contains($workflow, 'backstage_outreach_party_referral_create'),
	'Bulk success requires both link and managed coupon verification' => str_contains($workflow, "absint(\$created['coupon_id']") && str_contains($workflow, 'backstage_outreach_party_referral_url($created)'),
	'Bulk replay distinguishes existing links and reports partial failures' => str_contains($workflow, "'existing' => 0") && str_contains($workflow, "'failed' => array()"),
	'Invitation preview snapshots exact personalized content' => str_contains($workflow, "'subject_snapshot'") && str_contains($workflow, "'message_snapshot'") && str_contains($workflow, "'content_hash'"),
	'First handoff blocks duplicates while resend is deliberate' => str_contains($workflow, "\$mode === 'resend'") && str_contains($workflow, 'Prior successful handoff; use deliberate resend'),
	'Email suppression and missing addresses fail closed' => str_contains($workflow, 'vms_outreach_email_is_suppressed') && str_contains($workflow, "Missing email"),
	'No campaign link or Party creation automatically sends mail' => substr_count($workflow, 'wp_mail(') === 1 && str_contains($workflow, 'party_invitation_handoff'),
	'Mailer acceptance is not represented as delivery' => str_contains($workflow, 'delivery is not asserted'),
	'Manual email phone text social and note methods remain available' => str_contains($workflow, "array('email', 'phone', 'text', 'social', 'note')"),
	'Checkout discount cap remains a discount-only calculation' => str_contains($discounts, 'discounted_ticket_quantity') && !str_contains($discounts, 'Reduce the eligible ticket quantity to continue.'),
	'Explicit removal suppression still covers Store API and session flows' => str_contains($discounts, 'backstage_outreach_discount_record_coupon_removal') && str_contains($discounts, 'rest_request_after_callbacks'),
);

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed) {
	fwrite(STDERR, "Unified Party workflow source checks failed:\n- " . implode("\n- ", $failed) . "\n");
	exit(1);
}

echo 'Unified Party workflow source checks PASS (' . count($checks) . " assertions)\n";
