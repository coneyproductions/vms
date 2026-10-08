<?php
/** Focused source contract for canonical Party paid referral links. */

$root = dirname(__DIR__);
$plugin = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/backstage-outreach.php');
$party = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/party-directory.php');
$referrals = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/party-paid-referrals.php');
$discounts = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/business-discount-offers.php');
$business = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/business-distribution.php');

$checks = array(
	'Plugin loads the focused Party paid-referral adapter' => str_contains($plugin, "includes/party-paid-referrals.php"),
	'Party schema advances independently and adds typed referral tables' => str_contains($party, "return '1.1.0';") && str_contains($party, "'referral_distributions'") && str_contains($party, "'referral_redemptions'"),
	'Partner distributions key campaign plus Party without a business id' => str_contains($party, 'UNIQUE KEY campaign_party (campaign_id, party_id)') && !preg_match('/referral_distributions[\s\S]{0,1800}business_id/', $party),
	'Partner redemptions attribute the canonical Party' => str_contains($party, 'party_id BIGINT(20) UNSIGNED NOT NULL') && str_contains($party, 'KEY party_status (party_id, status)'),
	'Signed Partner URL uses a distinct path and signature domain' => str_contains($referrals, "'party-referral|1|'") && str_contains($referrals, "'/admission-offer/partner/'"),
	'Party and business coupon namespaces are distinct' => str_contains($discounts, "return 'sr-party-'") && str_contains($discounts, "return 'sr-biz50-'") && str_contains($discounts, 'BACKSTAGE_OUTREACH_COUPON_PARTY_DISTRIBUTION_META'),
	'Existing business distribution lookup remains the default' => str_contains($discounts, "function backstage_outreach_discount_get_distribution(int \$distribution_id, string \$owner_type = 'business')"),
	'Typed sessions and orders prevent Party/business id collisions' => str_contains($discounts, "'owner_type' => backstage_outreach_discount_owner_type") && str_contains($discounts, "_backstage_outreach_party_distribution_id") && str_contains($discounts, "_backstage_outreach_party_id"),
	'Party order validation does not reuse the legacy business distribution key' => str_contains($discounts, "delete_meta_data('_backstage_outreach_distribution_id')") && str_contains($discounts, "get_meta('_backstage_outreach_party_distribution_id', true)"),
	'Shared campaign and batch capacity sums both paid ledgers' => str_contains($discounts, "backstage_outreach_party_table('referral_redemptions')") && str_contains($discounts, "backstage_outreach_discount_paid_capacity_sum('campaign'") && str_contains($discounts, "backstage_outreach_discount_paid_capacity_sum('batch'"),
	'Existing complimentary business claims also include Party paid capacity' => str_contains($business, "backstage_outreach_discount_paid_capacity_sum('campaign'") && str_contains($business, "backstage_outreach_discount_paid_capacity_sum('batch'"),
	'Operator generation is review-gated and one-time' => str_contains($referrals, 'backstage_outreach_party_referral_review_key') && str_contains($referrals, "delete_transient(backstage_outreach_party_referral_review_key") && str_contains($referrals, 'configuration_hash'),
	'Only active Source-associated canonical Parties are eligible' => str_contains($referrals, 'party_referral_source_mismatch') && str_contains($referrals, "(string) \$party['status'] !== 'active'"),
	'Only reviewed Percentage/Fixed campaigns can create new links' => str_contains($referrals, "backstage_outreach_discount_offer_configuration(\$campaign, \$batch, 'coupon_backed')") && !str_contains($referrals, "allow_legacy_free_capacity"),
	'Partner links support active, paused, and irrevocable revoked states' => str_contains($referrals, "array('active', 'paused', 'revoked')") && str_contains($referrals, "=== 'revoked'"),
	'Partner paid-order limits are checked in the locked reservation path' => str_contains($discounts, 'COUNT(DISTINCT order_id)') && str_contains($discounts, 'paid-order limit'),
	'No automatic Party email or complimentary claim path was introduced' => !str_contains($referrals, 'wp_mail(') && !str_contains($referrals, 'bvmgr_pass_claims_create_claim') && !str_contains($referrals, 'pass_tokens'),
	'Party type changes fail closed when associations exist' => str_contains($party, 'party_type_associations_exist') && str_contains($party, "'referral_distributions'"),
);

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed) {
	fwrite(STDERR, "Party paid referral source checks failed:\n- " . implode("\n- ", $failed) . "\n");
	exit(1);
}

echo "Party paid referral source checks PASS (" . count($checks) . " assertions)\n";
