<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$source = (string) file_get_contents($root . '/includes/modules/admissions/pass-invitations.php');
$db = (string) file_get_contents($root . '/includes/modules/admissions/db.php');
$claims = (string) file_get_contents($root . '/includes/modules/admissions/pass-claims.php');
$js = (string) file_get_contents($root . '/assets/js/vms-pass-claims-admin.js');
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};

$assert(str_contains($db, "return '1.5.0';"), 'Invitation audit schema version is missing.');
foreach (array('token_id', 'batch_id', 'source_id', 'attempt_key', 'review_digest', 'recipient_email_hash', 'recipient_party_id', 'message_hash', 'claim_link_hash', 'operator_user_id', 'previous_attempt_id') as $field) {
	$assert(str_contains($db, $field), 'Invitation audit field is missing: ' . $field);
}
$assert(str_contains($source, "bvmgr_pass_claims_build_claim_url(\$token)"), 'Existing canonical claim-link generator is not reused.');
$assert(!str_contains($source, 'bvmgr_pass_claims_generate_tokens_for_batch'), 'Invitation flow must not generate tokens.');
$assert(!str_contains($source, 'wp_create_user'), 'Invitation flow must not create customers or subscribers.');
$assert(str_contains($source, 'vms_outreach_email_is_suppressed'), 'Global Outreach suppression check is missing.');
$assert(str_contains($source, 'Trey, Dalene & Henley Coney'), 'Approved default signature is missing.');
$assert(str_contains($source, 'This is a bearer link.'), 'Bearer-link warning is missing.');
$assert(str_contains($source, 'Preview never sends email.'), 'Preview no-send promise is missing.');
$assert(str_contains($source, 'bvmgr_pass_invitation_digest'), 'Review digest is missing.');
$assert(str_contains($source, 'GET_LOCK'), 'Per-token send serialization is missing.');
$assert(str_contains($source, 'reviewed_previous_attempt_id'), 'Reviewed state is not bound to the exact prior attempt for replay protection.');
$assert(str_contains($source, "'status' => 'sending'"), 'Durable send intent is missing.');
$assert(str_contains($source, "'status' => \$accepted ? 'handed_off' : 'failed'"), 'Mail handoff/failure distinction is missing.');
$assert(str_contains($source, "'status' => 'in_doubt'"), 'Ambiguous transport handling is missing.');
$assert(str_contains($source, 'recipient_email_hash'), 'Hashed recipient audit attribution is missing.');
$assert(str_contains($source, 'backstage_outreach_party_suggestions'), 'Optional stable Party attribution lookup is missing.');
$assert(!str_contains($source, "'claim_url' => (string) \$fresh['claim_url']"), 'Raw signed claim URL must not be stored in the audit table.');
$assert(str_contains($source, 'Explicitly confirm the reviewed resend'), 'Reviewed resend gate is missing.');
$assert(str_contains($source, 'Explicitly confirm reassignment'), 'Recipient reassignment gate is missing.');
$assert(str_contains($source, 'current_user_can(bvmgr_pass_claims_capability())'), 'Operator capability gate is missing.');
$assert(str_contains($source, "check_admin_referer('bvmgr_pass_invitation_preview_"), 'Preview nonce validation is missing.');
$assert(str_contains($source, "check_admin_referer('bvmgr_pass_invitation_send_"), 'Send nonce validation is missing.');
$assert(str_contains($claims, 'Send Invitation'), 'Eligible-pass action is missing from the pass list.');
$assert(str_contains($js, "confirmButton.disabled = true"), 'Duplicate-click browser interlock is missing.');
$assert(str_contains($js, 'Fields changed after preview') || str_contains($source, 'Fields changed after preview'), 'Stale-review operator warning is missing.');

if ($failures !== array()) {
	fwrite(STDERR, "Wave 2B.1 assertions failed:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "PASS: Wave 2B.1 direct invitation schema, security, idempotency, transport, audit, and operator UX guards.\n";
