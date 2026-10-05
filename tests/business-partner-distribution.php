<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$outreach = file_get_contents($root . '/companion-plugins/backstage-outreach/includes/business-distribution.php');
$integration = file_get_contents($root . '/companion-plugins/backstage-outreach/includes/integration-bvm.php');
$passes = file_get_contents($root . '/includes/modules/admissions/pass-claims.php');
$localQr = file_get_contents($root . '/includes/modules/admissions/local-qr.php');
if (!is_string($outreach) || !is_string($integration) || !is_string($passes) || !is_string($localQr)) {
	throw new RuntimeException('Could not read partner distribution sources.');
}

$assertions = array(
	'Business identity is independent of email' => strpos($outreach, 'UNIQUE KEY public_id (public_id)') !== false && strpos($outreach, 'UNIQUE KEY email') === false,
	'Source membership is explicit and many-to-many' => strpos($outreach, 'UNIQUE KEY source_business (source_id, business_id)') !== false,
	'Historical conversion retains snapshot provenance' => strpos($outreach, 'original_snapshot_json') !== false && strpos($outreach, 'provenance_recipient_id') !== false,
	'Historical conversion requires a current preview' => strpos($outreach, 'Historical conversion preview expired') !== false,
	'Historical commit rejects preview drift' => strpos($outreach, 'rows_digest') !== false && strpos($outreach, 'Historical recipients changed after preview') !== false,
	'Business conversion is atomic' => substr_count($outreach, "\$wpdb->query('START TRANSACTION')") >= 4 && strpos($outreach, 'No new businesses were retained.') !== false,
	'CSV imports reject silent truncation' => strpos($outreach, "count(\$rows) > 1000") !== false && strpos($outreach, 'csv_row_limit') !== false,
	'Business edits require Source membership' => substr_count($outreach, 'backstage_outreach_business_belongs_to_source(') >= 3,
	'Existing business identity can be reused across Sources' => strpos($outreach, 'reuse_business_id') !== false && strpos($outreach, 'Add one stable business identity to this Source') !== false,
	'Admin and public identifiers reject non-scalar input' => strpos($outreach, 'function backstage_outreach_request_absint') !== false && strpos($outreach, 'is_scalar($source[$key])') !== false,
	'Campaign/business association is idempotent' => strpos($outreach, 'UNIQUE KEY campaign_business (campaign_id, business_id)') !== false,
	'Campaign selection uses preview then commit' => strpos($outreach, 'distribution_mode') !== false && strpos($outreach, 'Save Reviewed Links') !== false,
	'Submission replay is idempotent' => strpos($outreach, 'UNIQUE KEY distribution_submission (distribution_id, submission_key_hash)') !== false,
	'Batch capacity is serialized across partner links' => strpos($outreach, "SELECT GET_LOCK(%s, %d)") !== false && strpos($outreach, 'FOR UPDATE') !== false,
	'Claim and attribution share a transaction' => strpos($outreach, "\$wpdb->query('START TRANSACTION')") !== false && strpos($outreach, "\$wpdb->query('COMMIT')") !== false,
	'Email delivery is deferred until after commit' => strpos($outreach, "'defer_email' => true") !== false && strpos($passes, "empty(\$context['defer_email'])") !== false,
	'Partner links do not allocate on GET' => strpos($outreach, "bvmgr_request_method() === 'post'") !== false,
	'Partner distribution is bounded to complimentary batches' => strpos($outreach, 'partner_batch_not_complimentary') !== false,
	'Partner party size is rejected before allocation' => strpos($outreach, 'Party size must be between 1 and %d.') !== false,
	'Partner submissions reuse the public claim rate limit' => strpos($outreach, "bvmgr_pass_claims_rate_limit_hit(\$ip, (string) \$distribution['public_key'])") !== false,
	'Attribution does not overload recipient identity' => strpos($outreach, "'outreach_distribution_id'") !== false && strpos($outreach, "['outreach_recipient_id']") === false,
	'Distribution state is audited without voiding passes' => strpos($outreach, 'outreach_partner_distribution_status') !== false && strpos($outreach, 'Existing purchased tickets and customer credentials were not changed.') !== false,
	'Revoked links cannot be resumed' => strpos($outreach, 'A revoked distribution cannot be resumed.') !== false && strpos($outreach, 'Revocation is permanent for this link.') !== false,
	'Claim submit revalidates mutable distribution state' => strpos($outreach, '$fresh_distribution') !== false && strpos($outreach, 'WHERE d.id=%d FOR UPDATE') !== false,
	'Post-commit mail failure preserves successful claim response' => strpos($outreach, 'mail_transport_exception') !== false && strpos($outreach, 'if (!$committed)') !== false,
	'Rewrite migration is idempotently requested' => strpos($outreach, "update_option('backstage_outreach_flush_rewrite', '1', false)") !== false,
	'Rewrite flush runs after all route registration' => strpos($integration, "add_action('init', 'backstage_outreach_maybe_flush_rewrite', 99)") !== false && strpos($integration, "add_action('init', 'backstage_outreach_register_public_route', 31)") !== false && strpos($outreach, "add_action('init', 'backstage_outreach_register_partner_route', 32)") !== false,
	'Partner print and export use same-site URL QR validation' => substr_count($outreach, 'bvmgr_pass_claims_claim_qr_image_url($url)') === 3 && strpos($localQr, "'/guest-pass/partner/'") !== false && strpos($localQr, "[a-f0-9]{48}\\.[a-f0-9]{64}") !== false,
	'Filters and exports use the same query builder' => substr_count($passes, 'bvmgr_pass_claims_filtered_query(') >= 4,
	'CSV cells are protected from formulas' => strpos($passes, "preg_match('/^[=+\\-@]/'") !== false,
	'Event filters use the claimed event' => strpos($passes, "c.event_plan_id=%d") !== false,
	'Pagination is deterministic' => strpos($passes, 'ORDER BY t.id DESC LIMIT %d OFFSET %d') !== false,
	'All-business filter works without a Source prerequisite' => strpos($passes, 'backstage_outreach_all_businesses(true)') !== false,
	'Filter row actions preserve the shared filter arguments' => substr_count($passes, 'array_merge($base_args') >= 3,
	'Filter dates reject impossible calendar dates' => strpos($passes, "DateTimeImmutable::createFromFormat('!Y-m-d'") !== false,
	'Export batch input rejects arrays' => strpos($passes, "isset(\$_REQUEST['batch_id']) && is_scalar(\$_REQUEST['batch_id'])") !== false,
);

$failed = array_keys(array_filter($assertions, static fn(bool $passed): bool => !$passed));
if ($failed) {
	throw new RuntimeException("Partner distribution assertions failed:\n- " . implode("\n- ", $failed));
}

echo 'Business partner distribution PASS (' . count($assertions) . " assertions)\n";
