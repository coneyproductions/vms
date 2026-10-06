<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$outreach = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/admissions/outreach.php');
$distribution = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/business-distribution.php');
$db = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/outreach/db.php');
$css = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/assets/css/outreach-admin.css');
$plugin = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/backstage-outreach.php');
$integration = (string) file_get_contents($root . '/companion-plugins/backstage-outreach/includes/integration-bvm.php');

$helper_start = strpos($outreach, "if (!function_exists('vms_pass_outreach_create_business_source_campaign'))");
$helper_end = strpos($outreach, "if (!function_exists('vms_pass_outreach_sanitize_campaign_payload'))", (int) $helper_start);
$helper = $helper_start !== false && $helper_end !== false ? substr($outreach, $helper_start, $helper_end - $helper_start) : '';

$assertions = array(
	'Reusable business route is an explicit create mode' => strpos($outreach, "'business_source'") !== false && strpos($outreach, 'Reusable Business Links / QRs') !== false,
	'Preview reads current active reusable memberships' => strpos($outreach, 'backstage_outreach_source_businesses($source_id, false)') !== false,
	'Preview binds Source and existing batch' => strpos($outreach, 'vms_pass_outreach_build_business_source_preview(int $source_id, int $batch_id)') !== false && strpos($outreach, 'batch_source_mismatch') !== false,
	'Preview lists every business rather than a sample' => strpos($outreach, 'foreach ($business_source_rows as $business_row)') !== false && strpos($outreach, 'vms-pass-business-source-table') !== false,
	'Blank email remains link eligible and delivery is not implied' => strpos($outreach, 'Email Delivery Unavailable') !== false && strpos($outreach, 'Reference only - email delivery is not prepared') !== false,
	'Business membership drift blocks creation' => strpos($helper, 'business_source_changed') !== false && strpos($helper, 'hash_equals') !== false,
	'Business create helper does not insert recipients or contacts' => $helper !== '' && strpos($helper, 'insert_prepared_recipients') === false && strpos($helper, 'vms_outreach_upsert_contact') === false,
	'Business creation preserves reviewed source and batch' => strpos($helper, "'related_source_id' => absint(\$current_preview['source_id']") !== false && strpos($helper, "'related_batch_id' => absint(\$current_preview['batch_id']") !== false,
	'Business and individual link quantities are separate' => strpos($outreach, 'Batch Individual Claim Links') !== false && strpos($distribution, 'Batch Individual Claim-Link Quantity') !== false,
	'Create action continues to QR setup' => strpos($outreach, 'Create Campaign and Continue to Business QR Setup') !== false && strpos($outreach, "'#backstage-outreach-partners'") !== false,
	'New QR setup reviews all active businesses by default' => strpos($distribution, '$checked = empty($rows) ||') !== false,
	'Paid offer has explicit discount wording' => strpos($distribution, 'Neighborhood Offer - 50% off paid admission') !== false,
	'Fifty-percent batches default to the paid setup path' => strpos($distribution, '$batch_defaults_to_paid') !== false && strpos($distribution, "'coupon_backed' : 'complimentary'") !== false,
	'Message preview is prominently sample-only' => strpos($outreach, 'SAMPLE DATA - NOT A DELIVERY PREVIEW') !== false,
	'Current defaults avoid transport-sensitive punctuation' => strpos($outreach, "'You\\'ve been invited") !== false && strpos($outreach, 'You’ve been invited') === false,
	'Legacy mojibake is repaired for display without a bulk update' => strpos($outreach, 'function vms_pass_outreach_display_text') !== false && strpos($outreach, 'Stored campaign history is not rewritten') !== false && strpos($helper, '$wpdb->update') === false,
	'Legitimate Unicode is not normalized as mojibake' => strpos($outreach, '$typographic_map') === false,
	'Narrow-screen business rows use labeled cards' => strpos($css, '.vms-pass-business-source-table td::before') !== false && strpos($css, 'content: attr(data-label)') !== false,
	'Business identity remains independent of email' => strpos($distribution, 'UNIQUE KEY public_id (public_id)') !== false && strpos($distribution, 'UNIQUE KEY email') === false,
	'Outreach 1.2.3 owns reproducible asset cache keys' => strpos($plugin, 'Version: 1.2.3') !== false && strpos($plugin, "BACKSTAGE_OUTREACH_VERSION', '1.2.3'") !== false && substr_count($integration, 'BACKSTAGE_OUTREACH_VERSION') >= 2 && strpos($integration, 'filemtime(') === false,
	'Schema versions remain unchanged' => strpos($distribution, "\$target = '1.2.1';") !== false && strpos($db, "return '1.1.0';") !== false,
);

$failed = array_keys(array_filter($assertions, static fn(bool $passed): bool => !$passed));
if ($failed) {
	throw new RuntimeException("Business Source campaign assertions failed:\n- " . implode("\n- ", $failed));
}

echo 'Business Source campaign PASS (' . count($assertions) . " assertions)\n";
