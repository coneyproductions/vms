<?php
/** Source-level contract for the reviewed Party adoption and distribution workflow. */

$root = dirname(__DIR__);
$plugin = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/backstage-outreach.php');
$party = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/party-directory.php');
$workflow = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/party-bulk-workflows.php');
$referrals = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/party-paid-referrals.php');
$admin = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/party-directory-admin.php');
$discounts = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/business-discount-offers.php');

$checks = array(
	'Candidate identity is 1.2.23' => str_contains($plugin, "Version: 1.2.23") && str_contains($plugin, "BACKSTAGE_OUTREACH_VERSION', '1.2.23"),
	'Party schema is additive 1.2 with a contact activity ledger' => str_contains($party, "return '1.2.0';") && str_contains($party, "'contact_activities'") && str_contains($party, 'UNIQUE KEY request_key'),
	'Workflow module is loaded after typed Party referrals' => strpos($plugin, "includes/party-paid-referrals.php") < strpos($plugin, "includes/party-bulk-workflows.php"),
	'Directory index never calls the per-Party referral renderer with null' => str_contains($admin, "is_array(\$party) && function_exists('backstage_outreach_party_referral_render_panel')"),
	'Adoption is bounded and requires a preview' => str_contains($workflow, 'min(1000, $limit)') && str_contains($workflow, 'party_adoption_scope_too_large') && str_contains($workflow, 'party_adoption_preview') && str_contains($workflow, 'review_token'),
	'Commit handlers enforce server-side explicit confirmation' => str_contains($workflow, "empty(\$_POST['confirm_adoption'])") && str_contains($workflow, "empty(\$_POST['confirm_campaign'])") && str_contains($workflow, "empty(\$_POST['confirm_links'])") && str_contains($workflow, "empty(\$_POST['confirm_handoff'])"),
	'Shared historical contact IDs remain an authoritative grouping key' => str_contains($workflow, "'contact-' . \$contact_id"),
	'Missing-contact compatibility requires the compound email name and organization signature' => str_contains($workflow, 'party_adoption_compatibility_plan') && str_contains($workflow, "array(\$identity['email'], \$identity['name'], \$identity['organization'])") && str_contains($workflow, "'compat-' . \$compatibility_signature"),
	'Same-campaign duplicate emails and conflicting compound identities require review' => str_contains($workflow, 'duplicate_email_rows') && str_contains($workflow, 'matching email has a conflicting normalized name, organization, or same-campaign duplicate'),
	'Every adoption proposal presents explicit identity evidence' => str_contains($workflow, "'identity_evidence'") && str_contains($workflow, 'Identity evidence:'),
	'Conflicting reviewed mappings cannot be silently relinked' => str_contains($workflow, "'mapping_conflict'") && str_contains($workflow, 'cannot be silently relinked'),
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
	'Invitation handoff revalidates the live referral and managed coupon immediately before mail' => substr_count($workflow, 'backstage_outreach_party_invitation_offer_error') >= 3 && str_contains($workflow, 'backstage_outreach_discount_distribution_error') && str_contains($workflow, 'party_invitation_signature_invalid'),
	'No campaign link or Party creation automatically sends mail' => !str_contains($workflow, 'wp_mail(') && str_contains($workflow, 'backstage_outreach_send_promotional_email') && str_contains($workflow, 'party_invitation_handoff'),
	'Mailer acceptance is not represented as delivery' => str_contains($workflow, 'delivery is not asserted'),
	'Campaign results use persisted Party distributions rather than campaign names' => str_contains($referrals, 'function backstage_outreach_party_campaign_results') && str_contains($referrals, "backstage_outreach_party_table('referral_distributions')") && str_contains($workflow, 'Offer-page visits: Not tracked') && str_contains($workflow, 'accepted email handoffs'),
	'Party workflow lists are bounded with sticky headers and live selection counts' => substr_count($workflow, 'vms-pass-table-scroll--party-workflow') >= 3 && str_contains($workflow, 'data-vms-sticky-table') && str_contains($workflow, 'data-vms-party-selected-count'),
	'Partner campaign context survives reviewed workflow redirects' => str_contains($workflow, "array('partner_campaign_id' => \$campaign_id)") && str_contains($workflow, "backstage_outreach_party_workflow_redirect('outreach-party-contact-dashboard', \$campaign_id)"),
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
