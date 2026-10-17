<?php

$root = dirname(__DIR__);
$reporting = file_get_contents($root . '/includes/modules/admissions/pass-reporting.php');
$claims = file_get_contents($root . '/includes/modules/admissions/pass-claims.php');
$admissions = file_get_contents($root . '/includes/modules/admissions/admissions.php');
$css = file_get_contents($root . '/assets/css/vms-pass-claims-admin.css');
$plugin = file_get_contents($root . '/backstage-venue-manager.php');
$readme = file_get_contents($root . '/readme.txt');

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, "FAIL: {$message}\n");
		exit(1);
	}
};

$assert(str_contains($admissions, "require_once __DIR__ . '/pass-reporting.php';"), 'Canonical reporting module is not loaded.');
$assert(str_contains($plugin, 'Version: 1.3.13') && str_contains($readme, 'Stable tag: 1.3.13'), 'Release identity is not 1.3.13.');
$assert(str_contains($reporting, 'bvmgr_pass_reporting_enrich_batches') && str_contains($reporting, 'bvmgr_pass_reporting_csv_row'), 'UI and CSV do not share the canonical aggregation row.');
$assert(str_contains($reporting, 'EXISTS (SELECT 1 FROM %i b WHERE b.source_id=s.id AND '), 'Per Source enumeration is not constrained by the canonical batch filter scope.');
$assert(str_contains($reporting, "if ((int) (\$row['batches_count'] ?? 0) === 0)"), 'Derived availability filtering can leak unrelated zero-valued source rows.');
$assert(str_contains($reporting, 'COUNT(DISTINCT token_id) unique_invited_links'), 'Invitation attempts are not separated from unique invited links.');
$assert(str_contains($reporting, "SUM(status NOT IN ('handed_off','failed')) indeterminate_attempts"), 'Indeterminate handoff attempts are not explicit.');
$assert(str_contains($reporting, 'SELECT DISTINCT c.related_batch_id batch_id, dc.pass_token_id'), 'Reusable-claim tokens are not excluded from direct-link issuance.');
$assert(str_contains($reporting, "e.status<>'canceled'"), 'Canceled native admissions are not excluded.');
$assert(str_contains($reporting, 'SUM(e.party_size),0) reusable_reserved_headcount'), 'Reusable utilization is not grounded in native admissions.');
$assert(str_contains($reporting, 'allocated_admission_capacity') && str_contains($reporting, 'allocation_utilization'), 'Reusable allocation metrics are missing.');
$assert(str_contains($reporting, 'party_redemptions') && str_contains($reporting, 'refunded_redemptions'), 'Paid/refunded Party reporting is missing.');
$assert(str_contains($reporting, 'Outreach is disabled or its reporting API is unavailable.'), 'Outreach graceful degradation is missing.');
$assert(str_contains($claims, 'bvmgr_pass_claims_stream_canonical_report_csv') && str_contains($claims, '$page_size = 100'), 'CSV does not use bounded streaming.');
$assert(str_contains($claims, 'bvmgr_pass_claims_csv_safe($value)'), 'Canonical CSV does not neutralize formula cells.');
$assert(str_contains($claims, 'current_user_can(bvmgr_pass_claims_capability())'), 'Canonical report export does not enforce the established capability.');
$assert(str_contains($claims, "wp_verify_nonce(\$nonce, bvmgr_nonce_action_for_value(\$nonce, 'bvmgr_pass_report_export_' . \$scope))"), 'Canonical report export does not verify its scoped nonce.');
$assert(!preg_match('/token_hash|claim_url|unsubscribe|recipient_email|coupon_code/', implode(',', array_map('strval', array('source_id', 'source_name', 'batch_id', 'batch_name')))), 'Privacy-safe header fixture is invalid.');
$assert(str_contains($claims, 'Export %s CSV'), 'Canonical UI export action is missing.');
$assert(str_contains($claims, 'No audit means “Not tracked historically,” not “never sent.”'), 'Historical invitation qualification is missing.');
$assert(str_contains($claims, 'Mail handoff means WordPress accepted the message for transport; it does not prove inbox delivery.'), 'Handoff semantics are missing.');
$assert(str_contains($css, '.vms-pass-report-filters') && str_contains($css, '.vms-pass-table-scroll:focus') && str_contains($css, '@media (max-width: 900px)'), 'Responsive/accessibility report CSS is incomplete.');

echo "Wave 2B.2 reporting source contracts PASS\n";
