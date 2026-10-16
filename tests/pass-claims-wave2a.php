<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$passClaims = (string) file_get_contents($root . '/includes/modules/admissions/pass-claims.php');
$adminJs = (string) file_get_contents($root . '/assets/js/vms-pass-claims-admin.js');
$adminCss = (string) file_get_contents($root . '/assets/css/vms-pass-claims-admin.css');
$publicCss = (string) file_get_contents($root . '/assets/css/vms-pass-claims-public.css');

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};

$assert(str_contains($passClaims, 'bvmgr_pass_claims_preview_digest'), 'Preview payloads must carry a server-computed digest.');
$assert(str_contains($passClaims, "SELECT GET_LOCK(%s, %d)"), 'Commit must serialize concurrent preview commits.');
$assert(str_contains($passClaims, "['state'] = 'committing'"), 'Commit must consume the reviewed snapshot before writes.');
$assert(str_contains($passClaims, 'bvmgr_pass_claims_sanitize_batch_payload((array) wp_unslash($_POST))'), 'Commit must revalidate current posted fields.');
$assert(str_contains($passClaims, 'The batch changed after preview.'), 'Stale previews must fail with actionable guidance.');
$assert(!str_contains($passClaims, 'Preview Samples'), 'Preview must not generate or display synthetic signed claim URLs.');
$assert(str_contains($passClaims, 'View Generated Guest Passes'), 'Successful creation must expose the primary next action.');
$assert(str_contains($passClaims, 'does not send invitations or email'), 'Creation must explicitly distinguish generation from invitations.');

foreach (array('single_event', 'date_range season', 'season') as $scope) {
	$assert(str_contains($passClaims, 'data-vms-validity-fields="' . $scope . '"'), 'Missing conditional validity wrapper: ' . $scope);
}
$assert(str_contains($passClaims, 'data-vms-discount-amount'), 'Discount amount must have a conditional wrapper.');
$assert(str_contains($adminJs, "String(valueType.value || '') === 'free'"), 'Free value type must hide discount amount.');
$assert(str_contains($adminJs, 'commit.disabled = changed'), 'Editing a reviewed form must disable stale commit.');
$assert(str_contains($adminJs, 'field.required = applies'), 'Conditional required state must track the selected validity type.');

$assert(str_contains($passClaims, 'bvmgr_pass_claims_handle_batch_metadata_save'), 'Batch metadata edit handler is missing.');
$assert(str_contains($passClaims, "COALESCE(updated_at, '') = %s"), 'Batch metadata edit must use optimistic concurrency.');
$assert(str_contains($passClaims, "SET batch_name = %s, notes = %s, updated_by = %d, updated_at = %s"), 'Batch metadata update must be limited to name, notes, and audit metadata.');
$assert(str_contains($passClaims, 'pass_batch_metadata_update'), 'Batch metadata edits must be audited.');
$assert(str_contains($passClaims, 'bvmgr_pass_claims_handle_batch_duplicate_draft'), 'Duplicate-as-draft handler is missing.');
$assert(str_contains($passClaims, "['status'] = 'paused'"), 'Duplicated form drafts must default to paused.');
$assert(str_contains($passClaims, 'No claim links, claims, campaigns, or history were copied.'), 'Duplicate action must disclose its safe boundary.');
$assert(str_contains($passClaims, 'bvmgr_pass_claims_batch_can_retire'), 'Safe retirement predicate is missing.');
$assert(str_contains($passClaims, "!empty(\$summary['outreach_verified'])"), 'Retirement must fail closed when Outreach linkage cannot be verified.');
$assert(str_contains($passClaims, "(int) (\$summary['token_rows'] ?? 0) === 0"), 'Retirement must reject every persisted token, including internal Outreach tokens.');
$assert(str_contains($passClaims, "(int) (\$summary['campaigns'] ?? 0) === 0"), 'Retirement must reject linked Outreach campaigns.');
$assert(str_contains($passClaims, "array('status' => 'voided'"), 'Safe unused retirement must use the existing status model.');
$managementStart = strpos($passClaims, "function bvmgr_pass_claims_render_batch_management");
$managementEnd = strpos($passClaims, "if (!function_exists('bvmgr_pass_claims_render_batches_tab'))", (int) $managementStart);
$managementSource = ($managementStart !== false && $managementEnd !== false) ? substr($passClaims, $managementStart, $managementEnd - $managementStart) : '';
$assert($managementSource !== '', 'Batch management renderer must be locatable.');
foreach (array('name="value_type"', 'name="value_amount"', 'name="admissions_per_link"', 'name="total_admission_cap"', 'name="validity_type"') as $unsafeField) {
	$assert(!str_contains($managementSource, $unsafeField), 'Issued economic/scope field must remain read-only in batch management: ' . $unsafeField);
}

foreach (array('Directly Issued Guest Pass Links', 'Outreach Reusable Links', 'Per-link Allocations (sum)', 'Actual Claims', 'Reserved Admissions', 'Checked-in Admissions', 'Linked Outreach Campaigns') as $label) {
	$assert(str_contains($passClaims, $label), 'Batch detail is missing factual count: ' . $label);
}
$assert(str_contains($passClaims, "['generated_count'] ?? 0"), 'Direct-link reporting must use generated_count and exclude internal Outreach claim tokens.');
$assert(str_contains($passClaims, "SUM(CASE WHEN status <> 'canceled' THEN party_size"), 'Reserved admissions must be headcount-based.');
$assert(str_contains($passClaims, "SUM(CASE WHEN status <> 'canceled' THEN checked_in_qty"), 'Check-ins must be headcount-based.');
$assert(str_contains($passClaims, 'Shared Batch Limit'), 'Shared capacity must be labeled independently.');
$assert(str_contains($passClaims, 'allocations do not reserve that capacity'), 'Per-link allocations must be distinguished from the shared limit.');

$assert(str_contains($passClaims, 'Send me event updates and reminders (optional).'), 'Optional marketing consent label must remain.');
$assert(!str_contains($passClaims, 'Your pass email is sent automatically if you enter an email.'), 'Redundant automatic-email sentence must be removed.');
$assert(str_contains($publicCss, 'align-items: flex-start'), 'Public checkbox must align beside multi-line label text.');
$assert(str_contains($publicCss, '.vms-pass-grid .vms-pass-checkbox'), 'Public checkbox row must override the higher-specificity grid label column rule.');
$assert(str_contains($publicCss, 'flex: 0 0 auto'), 'Public checkbox control must not stretch.');
$assert(str_contains($adminCss, 'overflow-x: auto'), 'Narrow admin cards must contain wide batch tables without page-level overflow.');
$assert(str_contains($adminCss, '@media (max-width: 900px)'), 'Admin management layout must have a narrow-screen breakpoint.');

if (!empty($failures)) {
	fwrite(STDERR, "Wave 2A assertions failed:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "PASS: Wave 2A Guest Pass workflow guards, management boundaries, reporting labels, and consent layout.\n";
