<?php
/**
 * Disposable local runtime exercise. Run with:
 * wp eval-file wp-content/plugins/packages/vms-github-reconcile/tests/business-source-campaign-runtime.php
 */

defined('ABSPATH') || exit;

$backstage_business_source_delivery_block = static fn() => true;
add_filter('pre_wp_mail', $backstage_business_source_delivery_block, PHP_INT_MAX);

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
$inactive_batch_id = 0;
$unsupported_batch_id = 0;
$unrelated_batch_id = 0;
$created_batch_id = 0;
$no_eligible_source_id = 0;
$unrelated_source_id = 0;
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

	$insert_fixture_batch = static function (int $fixture_source_id, string $name, string $status, string $value_type, float $value_amount) use ($wpdb, $batch_table, $user_id, $now): int {
		$inserted = $wpdb->insert($batch_table, array(
			'source_id' => $fixture_source_id,
			'batch_name' => $name,
			'quantity' => 5,
			'validity_type' => 'any_event',
			'venue_ids_json' => '[]',
			'value_type' => $value_type,
			'value_amount' => $value_amount,
			'applies_to' => 'entry_only',
			'status' => $status,
			'checkin_open_mode' => 'same_day',
			'max_per_phone' => 0,
			'generated_count' => 0,
			'created_by' => $user_id,
			'created_at' => $now,
			'admissions_per_link' => 2,
			'total_admission_cap' => 10,
			'max_per_email' => 0,
		));
		return $inserted === false ? 0 : (int) $wpdb->insert_id;
	};
	$inactive_batch_id = $insert_fixture_batch($source_id, $marker . ' inactive', 'paused', 'free', 0.0);
	$unsupported_batch_id = $insert_fixture_batch($source_id, $marker . ' unsupported', 'active', 'percent', 25.0);
	backstage_business_source_runtime_assert($inactive_batch_id > 0 && $unsupported_batch_id > 0, 'Could not create ineligible batch fixtures.');

	foreach (array('no eligible', 'unrelated') as $source_suffix) {
		backstage_business_source_runtime_assert($wpdb->insert($source_table, array(
			'source_name' => $marker . ' ' . $source_suffix,
			'status' => 'active',
			'created_by' => $user_id,
			'created_at' => $now,
		)) !== false, 'Could not create auxiliary Source fixture.');
		if ($source_suffix === 'no eligible') {
			$no_eligible_source_id = (int) $wpdb->insert_id;
		} else {
			$unrelated_source_id = (int) $wpdb->insert_id;
		}
	}
	$unrelated_batch_id = $insert_fixture_batch($unrelated_source_id, $marker . ' unrelated eligible', 'active', 'free', 0.0);
	backstage_business_source_runtime_assert($unrelated_batch_id > 0, 'Could not create unrelated eligible batch.');

	$fixture_batches = array(
		bvmgr_pass_claims_get_batch_by_id($batch_id),
		bvmgr_pass_claims_get_batch_by_id($paid_batch_id),
		bvmgr_pass_claims_get_batch_by_id($inactive_batch_id),
		bvmgr_pass_claims_get_batch_by_id($unsupported_batch_id),
		bvmgr_pass_claims_get_batch_by_id($unrelated_batch_id),
	);
	$eligible_for_source = vms_pass_outreach_eligible_business_batches($fixture_batches, $source_id);
	backstage_business_source_runtime_assert(count($eligible_for_source) === 2, 'Initial batch filtering did not exclude unrelated, inactive, or unsupported batches.');
	backstage_business_source_runtime_assert(vms_pass_outreach_eligible_business_batches($fixture_batches, $no_eligible_source_id) === array(), 'A Source with no eligible batch did not produce the empty state.');
	$forged_setup = vms_pass_outreach_sanitize_upload_first_campaign_setup(array(
		'recipient_source_mode' => 'business_source',
		'tracking_category_mode' => 'existing',
		'related_source_id' => $source_id,
		'related_batch_id' => $unrelated_batch_id,
	), false);
	backstage_business_source_runtime_assert(is_wp_error($forged_setup) && $forged_setup->get_error_code() === 'batch_source_mismatch', 'Forged Source/batch mismatch was not blocked server-side.');
	$empty_setup = vms_pass_outreach_sanitize_upload_first_campaign_setup(array('recipient_source_mode' => 'business_source'), false);
	backstage_business_source_runtime_assert(is_wp_error($empty_setup) && $empty_setup->get_error_code() === 'missing_related_source', 'Disabled-JavaScript empty submission did not identify the first missing prerequisite.');

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
	backstage_business_source_runtime_assert(
		backstage_outreach_business_upsert_membership($no_eligible_source_id, $business_ids[0], 'manual', null, 0, array('fixture' => true), $user_id),
		'Could not link a business to the no-eligible-batch Source.'
	);

	$new_batch_review = vms_pass_outreach_prepare_business_batch_review(array(
		'recipient_source_mode' => 'business_source',
		'tracking_category_mode' => 'existing',
		'related_source_id' => $source_id,
		'business_campaign_name' => $marker . ' preserved Café campaign',
		'business_batch_name' => $marker . ' reviewed batch',
		'business_batch_offer_type' => 'percent_50',
		'business_batch_quantity' => 9,
		'business_batch_admissions_per_link' => 2,
		'business_batch_total_admission_cap' => 70,
		'business_batch_validity_type' => 'any_event',
		'business_batch_single_event_plan_id' => 0,
		'business_batch_start_date' => '',
		'business_batch_end_date' => '',
		'business_batch_season_label' => '',
		'business_batch_expires_at' => '',
		'business_batch_notes' => 'Reviewed explicitly — no generated claim links.',
	), $user_id);
	backstage_business_source_runtime_assert(is_array($new_batch_review), 'Explicit new-batch review failed.');
	backstage_business_source_runtime_assert((string) ($new_batch_review['form_payload']['campaign_name'] ?? '') === $marker . ' preserved Café campaign', 'Campaign draft was not preserved through batch review.');
	$created_batch = vms_pass_outreach_create_business_offer_batch((array) $new_batch_review['batch_payload'], $user_id);
	backstage_business_source_runtime_assert(is_array($created_batch) && absint($created_batch['id'] ?? 0) > 0, 'Explicit reviewed batch definition was not created.');
	$created_batch_id = absint($created_batch['id']);
	backstage_business_source_runtime_assert(absint($created_batch['generated_count'] ?? 0) === 0, 'New batch setup silently generated individual claim links.');
	backstage_business_source_runtime_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE batch_id=%d', bvmgr_admission_table_pass_tokens(), $created_batch_id)) === 0, 'New batch setup inserted individual claim-link tokens.');
	backstage_business_source_runtime_assert(vms_pass_outreach_business_batch_offer_label($created_batch) === 'Neighborhood Offer — 50% off admission for up to 2 people', 'Paid offer wording does not reflect reviewed settings exactly.');

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

	$batch_drift_preview = vms_pass_outreach_build_business_source_preview($source_id, $batch_id);
	backstage_business_source_runtime_assert(is_array($batch_drift_preview), 'Could not prepare the batch-drift review fixture.');
	$wpdb->update($batch_table, array('status' => 'paused'), array('id' => $batch_id));
	$batch_drift_result = vms_pass_outreach_create_business_source_campaign($setup, $batch_drift_preview, $user_id);
	backstage_business_source_runtime_assert(is_wp_error($batch_drift_result) && $batch_drift_result->get_error_code() === 'inactive_related_batch', 'Batch status drift did not block campaign creation.');
	$wpdb->update($batch_table, array('status' => 'active'), array('id' => $batch_id));
	$wpdb->update($batch_table, array('source_id' => $unrelated_source_id), array('id' => $batch_id));
	$batch_owner_drift_result = vms_pass_outreach_create_business_source_campaign($setup, $batch_drift_preview, $user_id);
	backstage_business_source_runtime_assert(is_wp_error($batch_owner_drift_result) && $batch_owner_drift_result->get_error_code() === 'batch_source_mismatch', 'Batch Source drift did not block campaign creation.');
	$wpdb->update($batch_table, array('source_id' => $source_id), array('id' => $batch_id));

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
	remove_filter('pre_wp_mail', $backstage_business_source_delivery_block, PHP_INT_MAX);
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
	foreach (array($inactive_batch_id, $unsupported_batch_id, $unrelated_batch_id, $created_batch_id) as $fixture_batch_id) {
		if ($fixture_batch_id > 0) {
			$wpdb->delete(bvmgr_admission_table_pass_batches(), array('id' => $fixture_batch_id));
		}
	}
	if ($source_id > 0) {
		$wpdb->delete(bvmgr_admission_table_pass_sources(), array('id' => $source_id));
	}
	foreach (array($no_eligible_source_id, $unrelated_source_id) as $fixture_source_id) {
		if ($fixture_source_id > 0) {
			$wpdb->delete(bvmgr_admission_table_pass_sources(), array('id' => $fixture_source_id));
		}
	}
	$wpdb->query($wpdb->prepare(
		'DELETE FROM %i WHERE action=%s AND details LIKE %s',
		bvmgr_admission_table_audit(),
		'pass_outreach_business_campaign_create',
		'%' . $wpdb->esc_like($marker) . '%'
	));
	$wpdb->query($wpdb->prepare(
		'DELETE FROM %i WHERE action=%s AND details LIKE %s',
		bvmgr_admission_table_audit(),
		'pass_outreach_business_batch_create',
		'%' . $wpdb->esc_like('"batch_id":' . $created_batch_id) . '%'
	));
}
