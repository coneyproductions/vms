<?php
/** Focused source contract for Outreach UX stabilization issues #26, #31, and #27. */

declare(strict_types=1);

$root = dirname(__DIR__);
$outreach = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/admissions/outreach.php');
$distribution = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/business-distribution.php');
$dashboard = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/business-contact-dashboard.php');
$party = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/party-bulk-workflows.php');
$js = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/assets/js/outreach-admin.js');
$css = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/assets/css/outreach-admin.css');

$activation_start = strpos($distribution, 'function backstage_outreach_handle_business_campaign_activation');
$activation_end = strpos($distribution, "add_action('admin_post_backstage_outreach_business_activate_campaign'", (int) $activation_start);
$activation_handler = $activation_start !== false && $activation_end !== false
	? substr($distribution, $activation_start, $activation_end - $activation_start)
	: '';

$checks = array(
	'Issue 26 always renders one Step 4 create action with explicit review readiness' => substr_count($outreach, 'data-vms-business-review-ready=') === 1 && substr_count($outreach, 'Create Campaign and Continue to Business QR Setup') >= 2 && str_contains($outreach, 'disabled(!$business_review_ready'),
	'Issue 26 keeps the campaign name immediately before its create action' => strpos($outreach, 'name="business_campaign_name"') < strpos($outreach, 'data-vms-business-review-ready='),
	'Issue 26 supplies local initial, reviewed, and stale guidance' => str_contains($outreach, 'Review the %d active businesses above before creating this campaign.') && str_contains($outreach, '%d businesses reviewed. Campaign has NOT been created yet.') && str_contains($outreach, 'Review expired or changed. Preview Active Businesses again.'),
	'Issue 26 successful review targets and focuses Step 4' => str_contains($outreach, "'#vms-outreach-business-create-step'") && str_contains($outreach, 'focusBusinessCreateStep'),
	'Issue 26 stale review paths preserve submitted form payload' => substr_count($outreach, "'business_review' => __('Review expired or changed.") >= 3 && str_contains($outreach, 'vms_pass_outreach_soft_campaign_payload_for_form($raw, 0)'),
	'Issue 26 creation receipt carries campaign, source, and batch identities into Step 5' => str_contains($outreach, 'backstage_outreach_business_creation_result_') && str_contains($distribution, 'Campaign #%d created.') && str_contains($distribution, 'Generate reusable Business links'),
	'Issue 31 marks Business and Partner final handoff forms with reviewed counts' => str_contains($dashboard, 'data-vms-email-handoff-form') && str_contains($dashboard, 'data-vms-handoff-count=') && str_contains($party, 'data-vms-email-handoff-form') && str_contains($party, '$eligible_invitation_count'),
	'Issue 31 preserves native required confirmation and hidden server action fields' => str_contains($dashboard, 'name="share_mode" value="') && str_contains($party, 'name="confirm_handoff" value="1" required') && str_contains($party, 'name="review_token"'),
	'Issue 31 interlocks only valid submissions and protects repeats' => str_contains($js, 'event.defaultPrevented || !form.checkValidity()') && str_contains($js, "data-vms-submitting') === '1") && str_contains($js, 'event.preventDefault();'),
	'Issue 31 exposes accurate in-progress and uncertain-navigation copy' => str_contains($js, "reviewed ' + noun + '. Please wait; do not refresh or resend.'") && str_contains($js, 'The previous handoff may have completed. Review the current campaign activity before submitting again.'),
	'Issue 31 provides accessible progress UI without removing hidden POST values' => str_contains($dashboard, 'aria-live="assertive"') && str_contains($party, 'aria-live="assertive"') && str_contains($css, '.vms-pass-handoff-progress') && !str_contains($js, "querySelectorAll('input').forEach"),
	'Issue 27 activation is a capability and nonce checked POST' => str_contains($activation_handler, "REQUEST_METHOD") && str_contains($activation_handler, "!== 'POST'") && str_contains($activation_handler, "check_admin_referer('backstage_outreach_business_activate_campaign_"),
	'Issue 27 revalidates Source, batch, and active Business links' => str_contains($distribution, 'backstage_outreach_business_activation_readiness') && str_contains($distribution, 'business_campaign_source_missing') && str_contains($distribution, 'business_campaign_batch_missing') && str_contains($distribution, 'business_campaign_links_missing'),
	'Issue 27 uses the existing campaign activation logic and contextual audit trigger' => str_contains($activation_handler, "vms_pass_outreach_activate_campaign(\$campaign, 'business_sharing')") && str_contains($outreach, "'trigger' => sanitize_key(\$trigger)"),
	'Issue 27 returns to the Business sharing screen with explicit non-delivery copy' => str_contains($activation_handler, "backstage_outreach_business_redirect_to_step(\$campaign_id, 'backstage-outreach-business-share')") && str_contains($distribution, 'email invitations still require separate review and send.'),
	'Issue 27 handler cannot hand off email or generate links' => $activation_handler !== '' && !str_contains($activation_handler, 'backstage_outreach_send_promotional_email') && !str_contains($activation_handler, 'backstage_outreach_party_referral_create') && !str_contains($activation_handler, 'backstage_outreach_campaign_businesses'),
);

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed) {
	throw new RuntimeException("Outreach UX stabilization assertions failed:\n- " . implode("\n- ", $failed));
}

echo 'Outreach UX stabilization PASS (' . count($checks) . " assertions)\n";
