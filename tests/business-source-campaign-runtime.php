<?php
/**
 * Disposable local runtime exercise. Run with:
 * wp eval-file wp-content/plugins/packages/vms-github-reconcile/tests/business-source-campaign-runtime.php
 */

defined('ABSPATH') || exit;

function backstage_business_source_runtime_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

global $wpdb;
$marker = 'BVM business Source synthetic ' . wp_generate_password(8, false, false);
$now = backstage_outreach_business_now();
$user_id = get_current_user_id() ?: 1;
$source_id = 0;
$batch_id = 0;
$paid_batch_id = 0;
$campaign_id = 0;
$business_ids = array();
$drift_business_id = 0;

try {
	$legacy_display = vms_pass_outreach_display_text("\x59\x6f\x75\xc3\xa2\xe2\x82\xac\xe2\x84\xa2\x76\x65\x20\xc3\xa2\xe2\x82\xac\xe2\x80\x9d\x20\x68\x69\x73\x74\x6f\x72\x69\x63\x61\x6c\x20\xc3\x82\xc2\xb7\x20\x64\x65\x73\x63\x72\x69\x70\x74\x69\x6f\x6e");
	backstage_business_source_runtime_assert($legacy_display === "You've - historical | description", 'Legacy message/description mojibake was not repaired for display.');
	$unicode_round_trip = 'Café Âme — You’ve “arrived” • déjà vu';
	backstage_business_source_runtime_assert(vms_pass_outreach_display_text($unicode_round_trip) === $unicode_round_trip, 'Legitimate Unicode punctuation changed during display normalization.');
	backstage_business_source_runtime_assert(vms_pass_outreach_sanitize_plain_text_template($unicode_round_trip) === $unicode_round_trip, 'Legitimate Unicode punctuation changed during edit/save sanitization.');

	$source_table = bvmgr_admission_table_pass_sources();
	$batch_table = bvmgr_admission_table_pass_batches();
	$campaign_table = vms_admission_table_pass_outreach_campaigns();
	$recipient_table = vms_pass_outreach_recipient_table();

	backstage_business_source_runtime_assert($wpdb->insert($source_table, array(
		'source_name' => $marker,
		'status' => 'active',
		'created_by' => $user_id,
		'created_at' => $now,
	)) !== false, 'Could not create disposable Source.');
	$source_id = (int) $wpdb->insert_id;

	backstage_business_source_runtime_assert($wpdb->insert($batch_table, array(
		'source_id' => $source_id,
		'batch_name' => $marker . ' shared pool',
		'quantity' => 12,
		'validity_type' => 'any_event',
		'venue_ids_json' => '[]',
		'value_type' => 'free',
		'value_amount' => '0.00',
		'applies_to' => 'entry_only',
		'status' => 'active',
		'checkin_open_mode' => 'same_day',
		'max_per_phone' => 0,
		'generated_count' => 0,
		'created_by' => $user_id,
		'created_at' => $now,
		'admissions_per_link' => 2,
		'total_admission_cap' => 70,
		'max_per_email' => 0,
	)) !== false, 'Could not create disposable batch.');
	$batch_id = (int) $wpdb->insert_id;
	backstage_business_source_runtime_assert($wpdb->insert($batch_table, array(
		'source_id' => $source_id,
		'batch_name' => $marker . ' paid 50 percent pool',
		'quantity' => 7,
		'validity_type' => 'any_event',
		'venue_ids_json' => '[]',
		'value_type' => 'percent',
		'value_amount' => '50.00',
		'applies_to' => 'entry_only',
		'status' => 'active',
		'checkin_open_mode' => 'same_day',
		'max_per_phone' => 0,
		'generated_count' => 0,
		'created_by' => $user_id,
		'created_at' => $now,
		'admissions_per_link' => 2,
		'total_admission_cap' => 50,
		'max_per_email' => 0,
	)) !== false, 'Could not create disposable paid-discount batch.');
	$paid_batch_id = (int) $wpdb->insert_id;

	for ($index = 1; $index <= 35; $index++) {
		$business_id = backstage_outreach_insert_business(array(
			'business_name' => sprintf('%s business %02d', $marker, $index),
			'contact_name' => sprintf('Contact %02d', $index),
			'email' => $index <= 21 ? sprintf('business-%02d@example.test', $index) : '',
			'phone' => sprintf('555-010-%02d', $index),
			'website' => sprintf('https://example.test/business-%02d', $index),
			'address_line' => sprintf('%d Preview Lane', $index),
			'city' => 'Highland Park',
			'state' => 'IL',
			'postal_code' => '60035',
			'notes' => sprintf('Research note %02d <script>alert(1)</script>', $index),
		), $user_id);
		backstage_business_source_runtime_assert($business_id > 0, 'Could not create disposable business.');
		backstage_business_source_runtime_assert(
			backstage_outreach_business_upsert_membership($source_id, $business_id, 'manual', null, 0, array('fixture' => true), $user_id),
			'Could not create disposable Source membership.'
		);
		$business_ids[] = $business_id;
	}

	$preview = vms_pass_outreach_build_business_source_preview($source_id, $batch_id);
	backstage_business_source_runtime_assert(is_array($preview), 'Business Source preview failed.');
	backstage_business_source_runtime_assert((int) $preview['active_membership_count'] === 35, 'Preview did not include all 35 active businesses.');
	backstage_business_source_runtime_assert((int) $preview['link_eligible_count'] === 35, 'Blank email incorrectly removed link eligibility.');
	backstage_business_source_runtime_assert((int) $preview['email_count'] === 21 && (int) $preview['missing_email_count'] === 14, 'Email and blank-email totals are incorrect.');
	backstage_business_source_runtime_assert((int) $preview['individual_claim_link_quantity'] === 12 && (int) $preview['batch_total_admission_cap'] === 70, 'Batch quantities were not kept distinct.');
	backstage_business_source_runtime_assert(str_contains((string) $preview['rows'][0]['address'], 'Preview Lane'), 'Mapped address is absent from business preview.');
	backstage_business_source_runtime_assert(str_contains((string) $preview['rows'][0]['notes'], 'Research note'), 'Research Notes were not retained.');
	$paid_preview = vms_pass_outreach_build_business_source_preview($source_id, $paid_batch_id);
	backstage_business_source_runtime_assert(is_array($paid_preview) && $paid_preview['batch_offer_type'] === 'paid_discount' && (int) $paid_preview['link_eligible_count'] === 35, 'The 50% paid Neighborhood Offer route did not preserve all 35 link-eligible businesses.');
	backstage_business_source_runtime_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE campaign_id > 0 AND campaign_id IN (SELECT id FROM %i WHERE related_source_id=%d)', $recipient_table, $campaign_table, $source_id)) === 0, 'Fixture unexpectedly has historical recipients.');

	$drift_business_id = backstage_outreach_insert_business(array('business_name' => $marker . ' drift'), $user_id);
	backstage_business_source_runtime_assert($drift_business_id > 0 && backstage_outreach_business_upsert_membership($source_id, $drift_business_id, 'manual', null, 0, array(), $user_id), 'Could not create drift fixture.');
	$setup = array(
		'campaign_name' => $marker . ' campaign',
		'campaign_purpose' => 'guest_pass_invitation',
		'email_subject' => "You're invited to Serenade Range",
		'message_template' => vms_pass_outreach_default_message_template(),
		'internal_notes' => 'Disposable business Source campaign.',
		'related_source_id' => $source_id,
		'related_batch_id' => $batch_id,
		'validity_type' => 'any_event',
		'admissions_per_recipient' => 2,
		'total_admission_cap' => 0,
		'status' => 'active',
		'eligibility_mode' => 'anyone_with_invite',
		'recipient_source_mode' => 'business_source',
		'tracking_category_mode' => 'existing',
	);
	$drift_result = vms_pass_outreach_create_business_source_campaign($setup, $preview, $user_id);
	backstage_business_source_runtime_assert(is_wp_error($drift_result) && $drift_result->get_error_code() === 'business_source_changed', 'Membership drift did not block campaign creation.');
	$wpdb->delete(backstage_outreach_business_table('source_businesses'), array('business_id' => $drift_business_id));
	$wpdb->delete(backstage_outreach_business_table('businesses'), array('id' => $drift_business_id));
	$drift_business_id = 0;

	$preview = vms_pass_outreach_build_business_source_preview($source_id, $batch_id);
	$created = vms_pass_outreach_create_business_source_campaign($setup, $preview, $user_id);
	backstage_business_source_runtime_assert(is_array($created) && !empty($created['campaign']['id']), 'Reviewed business campaign was not created.');
	$campaign_id = (int) $created['campaign']['id'];
	backstage_business_source_runtime_assert((int) $created['campaign']['related_source_id'] === $source_id && (int) $created['campaign']['related_batch_id'] === $batch_id, 'Reviewed Source and batch were not carried into the campaign QR setup.');
	backstage_business_source_runtime_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE campaign_id=%d', $recipient_table, $campaign_id)) === 0, 'Business campaign created historical email recipients.');

	foreach ($business_ids as $business_id) {
		$distribution_id = backstage_outreach_create_distribution($campaign_id, $source_id, $business_id, 'complimentary', 0, 0, '', array(), array(), $user_id);
		backstage_business_source_runtime_assert($distribution_id > 0, 'Could not create one of the 35 reusable links.');
	}
	$distributions = backstage_outreach_distribution_rows($campaign_id);
	$tokens = array_map('backstage_outreach_distribution_token', $distributions);
	backstage_business_source_runtime_assert(count($distributions) === 35 && count(array_unique($tokens)) === 35, 'All 35 independent reusable links were not created.');
	backstage_business_source_runtime_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE campaign_id=%d', $recipient_table, $campaign_id)) === 0, 'Reusable link creation produced recipient records.');

	echo "Business Source campaign runtime PASS\n";
	echo wp_json_encode(array(
		'businesses' => 35,
		'with_email_reference' => 21,
		'missing_email' => 14,
		'historical_recipients' => 0,
		'reusable_links' => 35,
		'individual_claim_links' => 12,
		'shared_admission_cap' => 70,
	), JSON_PRETTY_PRINT) . "\n";
} finally {
	if ($campaign_id > 0) {
		$wpdb->delete(backstage_outreach_business_table('distribution_claims'), array('campaign_id' => $campaign_id));
		$wpdb->delete(backstage_outreach_business_table('campaign_businesses'), array('campaign_id' => $campaign_id));
		$wpdb->delete(vms_admission_table_pass_outreach_campaigns(), array('id' => $campaign_id));
	}
	if ($drift_business_id > 0) {
		$wpdb->delete(backstage_outreach_business_table('source_businesses'), array('business_id' => $drift_business_id));
		$wpdb->delete(backstage_outreach_business_table('businesses'), array('id' => $drift_business_id));
	}
	foreach ($business_ids as $business_id) {
		$wpdb->delete(backstage_outreach_business_table('source_businesses'), array('business_id' => $business_id));
		$wpdb->delete(backstage_outreach_business_table('businesses'), array('id' => $business_id));
	}
	if ($batch_id > 0) {
		$wpdb->delete(bvmgr_admission_table_pass_batches(), array('id' => $batch_id));
	}
	if ($paid_batch_id > 0) {
		$wpdb->delete(bvmgr_admission_table_pass_batches(), array('id' => $paid_batch_id));
	}
	if ($source_id > 0) {
		$wpdb->delete(bvmgr_admission_table_pass_sources(), array('id' => $source_id));
	}
	$wpdb->query($wpdb->prepare(
		'DELETE FROM %i WHERE action=%s AND details LIKE %s',
		bvmgr_admission_table_audit(),
		'pass_outreach_business_campaign_create',
		'%' . $wpdb->esc_like($marker) . '%'
	));
}
