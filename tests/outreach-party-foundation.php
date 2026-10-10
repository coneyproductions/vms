<?php

$root = dirname(__DIR__);
$plugin = file_get_contents($root . '/companion-plugins/backstage-outreach/backstage-outreach.php');
$domain = file_get_contents($root . '/companion-plugins/backstage-outreach/includes/party-directory.php');
$admin = file_get_contents($root . '/companion-plugins/backstage-outreach/includes/party-directory-admin.php');
$ui = file_get_contents($root . '/companion-plugins/backstage-outreach/includes/outreach/admin-ui.php');
$design = file_get_contents($root . '/docs/outreach-unified-contacts-foundation-2026-10-08.md');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
	$assertions++;
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$assert(strpos($plugin, 'Version: 1.2.20') !== false && strpos($plugin, "BACKSTAGE_OUTREACH_VERSION', '1.2.20'") !== false, 'Outreach release metadata is not 1.2.20.');
$assert(strpos($plugin, "includes/party-directory.php") !== false && strpos($plugin, "includes/party-directory-admin.php") !== false, 'Party foundation bootstrap is incomplete.');
$assert(strpos($plugin, 'backstage_outreach_party_schema_upgrade();') !== false, 'Party schema upgrade is not registered.');
$assert(strpos($domain, "return '1.2.0';") !== false, 'Independent Party schema marker is missing.');
foreach (array('parties', 'contact_methods', 'affiliations', 'sources', 'campaign_roles', 'legacy_links', 'identity_audit', 'referral_distributions', 'referral_redemptions') as $table) {
	$assert(strpos($domain, "backstage_outreach_party_table('{$table}')") !== false, "Party table {$table} is missing.");
}
$assert(strpos($domain, 'UNIQUE KEY legacy_reference (legacy_type, legacy_id)') !== false, 'Legacy references are not constrained to one current Party.');
$assert(strpos($domain, 'UNIQUE KEY party_method_value (party_id, method_type, value_norm)') !== false, 'Party contact-method retries are not idempotently constrained.');
$assert(strpos($domain, 'UNIQUE KEY party_source (party_id, source_id)') !== false, 'Party-to-Source retries are not idempotently constrained.');
$assert(strpos($domain, 'vms_pass_outreach_recipients') === false, 'Domain code contains an unexpected hardcoded recipient table name.');
$assert(strpos($domain, 'START TRANSACTION') !== false && strpos($domain, 'FOR UPDATE') !== false && strpos($domain, 'ROLLBACK') !== false, 'Reviewed link mutations are not transactionally guarded.');
$assert(strpos($domain, 'legacy_snapshot_changed') !== false && strpos($domain, 'hash_equals') !== false, 'Legacy snapshot drift is not enforced.');
$assert(strpos($domain, 'legacy_already_linked') !== false && strpos($domain, 'legacy_corrected') !== false && strpos($domain, 'legacy_unlinked') !== false, 'Explicit correction/unlink safeguards are incomplete.');
$assert(strpos($domain, 'directory_only') !== false, 'Source/campaign associations are not explicitly marked directory-only.');
$assert(strpos($domain, 'vms_outreach_email_is_suppressed') !== false, 'Existing channel suppression is not derived by the Party directory.');
$assert(strpos($admin, 'current_user_can(backstage_outreach_party_admin_capability())') !== false && strpos($admin, 'check_admin_referer($nonce_action)') !== false && substr_count($admin, "add_action('admin_post_backstage_outreach_party_") >= 9, 'Party admin mutations are not consistently capability/nonce protected.');
$assert(strpos($admin, 'Email not provided; email delivery is unavailable.') !== false, 'Missing-email state is not explicit.');
$assert(strpos($admin, 'does not create a reusable-business membership') !== false, 'Directory Source associations do not clearly preserve business eligibility boundaries.');
$assert(strpos($admin, 'Ambiguous—review required') !== false && strpos($admin, 'inspect the immutable snapshot and match reasons') !== false, 'Operator duplicate review guidance is incomplete.');
$assert(strpos($ui, "'directory' => __('Contacts & Partners'") !== false && strpos($ui, "'contacts' => __('Legacy Contacts / Prospects'") !== false, 'Canonical and legacy directories are not visibly separated.');
$assert(strpos($design, 'There is no automatic migration or backfill.') !== false, 'Design gate does not preserve legacy authority.');
$assert(strpos($design, '`contacted_party_id`') !== false && strpos($design, '`referring_party_id`') !== false && strpos($design, '`redeeming_customer`') !== false, 'Future typed distribution contract is incomplete.');
$assert(strpos($design, 'must not use complimentary BVM Guest Pass claims') !== false, 'Future paid-referral boundary is not documented.');

echo "PASS: {$assertions} Outreach Party foundation assertions.\n";
