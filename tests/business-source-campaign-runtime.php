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
$fixed_batch_id = 0;
$inactive_batch_id = 0;
$unsupported_batch_id = 0;
$unrelated_batch_id = 0;
$created_batch_id = 0;
$complimentary_batch_id = 0;
$no_eligible_source_id = 0;
$unrelated_source_id = 0;
$campaign_id = 0;
$complimentary_campaign_id = 0;
$complimentary_claim_id = 0;
$event_plan_id = 0;
$alternate_event_plan_id = 0;
$missing_art_event_plan_id = 0;
$tec_event_ids = array();
$flyer_artwork_ids = array();
$business_ids = array();
$drift_business_id = 0;

try {
	$switched_draft = vms_pass_outreach_normalize_business_route_request(array(
		'recipient_source_mode' => 'business_source',
		'business_campaign_name' => 'Café — switched draft',
		'admissions_per_recipient' => '3',
		'validity_type' => 'date_range',
		'start_date' => '2030-03-01',
		'end_date' => '2030-03-31',
		'business_batch_end_date' => '2030-04-02',
		'business_batch_touched_fields' => 'business_batch_end_date',
	));
	backstage_business_source_runtime_assert((string) $switched_draft['campaign_name'] === 'Café — switched draft' && (string) $switched_draft['business_batch_validity_type'] === 'date_range' && (string) $switched_draft['business_batch_start_date'] === '2030-03-01', 'Compatible campaign draft values did not transfer to an unfilled business-batch draft.');
	backstage_business_source_runtime_assert((string) $switched_draft['business_batch_end_date'] === '2030-04-02', 'An independently edited business-batch draft value was overwritten during route switching.');
	$legacy_display = vms_pass_outreach_display_text("\x59\x6f\x75\xc3\xa2\xe2\x82\xac\xe2\x84\xa2\x76\x65\x20\xc3\xa2\xe2\x82\xac\xe2\x80\x9d\x20\x68\x69\x73\x74\x6f\x72\x69\x63\x61\x6c\x20\xc3\x82\xc2\xb7\x20\x64\x65\x73\x63\x72\x69\x70\x74\x69\x6f\x6e");
	backstage_business_source_runtime_assert($legacy_display === "You've - historical | description", 'Legacy message/description mojibake was not repaired for display.');
	$unicode_round_trip = 'Café Âme — You’ve “arrived” • déjà vu';
	backstage_business_source_runtime_assert(vms_pass_outreach_display_text($unicode_round_trip) === $unicode_round_trip, 'Legitimate Unicode punctuation changed during display normalization.');
	backstage_business_source_runtime_assert(vms_pass_outreach_sanitize_plain_text_template($unicode_round_trip) === $unicode_round_trip, 'Legitimate Unicode punctuation changed during edit/save sanitization.');

	$source_table = bvmgr_admission_table_pass_sources();
	$batch_table = bvmgr_admission_table_pass_batches();
	$campaign_table = vms_admission_table_pass_outreach_campaigns();
	$recipient_table = vms_pass_outreach_recipient_table();
	$event_plan_id = wp_insert_post(array(
		'post_type' => 'vms_event_plan',
		'post_status' => 'publish',
		'post_title' => $marker . ' Café — claim event',
	), true);
	backstage_business_source_runtime_assert(!is_wp_error($event_plan_id) && $event_plan_id > 0, 'Could not create disposable Event Plan.');
	$status_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status';
	update_post_meta((int) $event_plan_id, $status_key, 'published');
	update_post_meta((int) $event_plan_id, '_vms_event_date', wp_date('Y-m-d', time() + (14 * DAY_IN_SECONDS)));
	$create_flyer_artwork = static function (string $filename, string $title) use (&$flyer_artwork_ids): int {
		$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFElEQVR42mNkYGD4z8DAwMDAxAADAA0EAQDRfRYAAAAASUVORK5CYII=', true);
		$upload = is_string($png) ? wp_upload_bits($filename, null, $png) : array('error' => 'decode');
		if (!empty($upload['error']) || empty($upload['file'])) {
			return 0;
		}
		$attachment_id = wp_insert_attachment(array('post_mime_type' => 'image/png', 'post_title' => $title, 'post_status' => 'inherit'), (string) $upload['file']);
		if (is_wp_error($attachment_id) || absint($attachment_id) <= 0) {
			return 0;
		}
		$flyer_artwork_ids[] = absint($attachment_id);
		return absint($attachment_id);
	};
	$event_artwork_id = $create_flyer_artwork('outreach-event-artwork-primary.png', $marker . ' primary event artwork');
	$alternate_event_artwork_id = $create_flyer_artwork('outreach-event-artwork-alternate.png', $marker . ' alternate event artwork');
	backstage_business_source_runtime_assert($event_artwork_id > 0 && $alternate_event_artwork_id > 0, 'Could not create disposable automatic-artwork images.');
	$primary_tec_event_id = wp_insert_post(array('post_type' => 'tribe_events', 'post_status' => 'publish', 'post_title' => $marker . ' linked public event'), true);
	$alternate_tec_event_id = wp_insert_post(array('post_type' => 'tribe_events', 'post_status' => 'publish', 'post_title' => $marker . ' alternate linked public event'), true);
	$alternate_event_plan_id = wp_insert_post(array('post_type' => 'vms_event_plan', 'post_status' => 'publish', 'post_title' => $marker . ' alternate Event Plan'), true);
	$missing_art_event_plan_id = wp_insert_post(array('post_type' => 'vms_event_plan', 'post_status' => 'publish', 'post_title' => $marker . ' Event Plan without artwork'), true);
	backstage_business_source_runtime_assert(!is_wp_error($primary_tec_event_id) && !is_wp_error($alternate_tec_event_id) && !is_wp_error($alternate_event_plan_id) && !is_wp_error($missing_art_event_plan_id), 'Could not create disposable automatic-artwork event relationships.');
	$tec_event_ids = array(absint($primary_tec_event_id), absint($alternate_tec_event_id));
	update_post_meta((int) $event_plan_id, '_vms_tec_event_id', absint($primary_tec_event_id));
	update_post_meta(absint($primary_tec_event_id), '_thumbnail_id', $event_artwork_id);
	foreach (array($alternate_event_plan_id, $missing_art_event_plan_id) as $fixture_event_plan_id) {
		update_post_meta(absint($fixture_event_plan_id), $status_key, 'published');
		update_post_meta(absint($fixture_event_plan_id), '_vms_event_date', wp_date('Y-m-d', time() + (21 * DAY_IN_SECONDS)));
	}
	update_post_meta((int) $alternate_event_plan_id, '_vms_tec_event_id', absint($alternate_tec_event_id));
	update_post_meta(absint($alternate_tec_event_id), '_thumbnail_id', $alternate_event_artwork_id);

	backstage_business_source_runtime_assert($wpdb->insert($source_table, array(
		'source_name' => $marker,
		'status' => 'active',
		'created_by' => $user_id,
		'created_at' => $now,
	)) !== false, 'Could not create disposable Source.');
	$source_id = (int) $wpdb->insert_id;
	$normal_batch_validation = bvmgr_pass_claims_sanitize_batch_payload(array(
		'source_id' => $source_id,
		'batch_name' => $marker . ' normal individual links',
		'quantity' => 3,
		'admissions_per_link' => 2,
		'total_admission_cap' => 0,
		'validity_type' => 'any_event',
		'value_type' => 'free',
		'status' => 'active',
		'checkin_open_mode' => 'same_day',
	));
	backstage_business_source_runtime_assert(is_array($normal_batch_validation) && (int) $normal_batch_validation['quantity'] === 3 && (int) $normal_batch_validation['total_admission_cap'] === 6, 'Normal individual-pass quantity validation or cap derivation changed.');

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
	$normal_generation = bvmgr_pass_claims_generate_tokens_for_batch($batch_id, 3, $source_id, $user_id);
	backstage_business_source_runtime_assert(is_array($normal_generation) && (int) ($normal_generation['generated_count'] ?? 0) === 3, 'Ordinary individual-pass token generation changed.');
	backstage_business_source_runtime_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE batch_id=%d', bvmgr_admission_table_pass_tokens(), $batch_id)) === 3, 'Ordinary individual-pass generation did not persist all requested links.');
	backstage_business_source_runtime_assert((int) $wpdb->get_var($wpdb->prepare('SELECT generated_count FROM %i WHERE id=%d', $batch_table, $batch_id)) === 3, 'Ordinary individual-pass generation did not update generated_count.');
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
	$fixed_batch_id = $insert_fixture_batch($source_id, $marker . ' fixed 12.35', 'active', 'fixed', 12.35);
	$unsupported_batch_id = $insert_fixture_batch($source_id, $marker . ' unsupported', 'active', 'percent', 125.0);
	backstage_business_source_runtime_assert($inactive_batch_id > 0 && $fixed_batch_id > 0 && $unsupported_batch_id > 0, 'Could not create eligibility fixtures.');

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
		bvmgr_pass_claims_get_batch_by_id($fixed_batch_id),
		bvmgr_pass_claims_get_batch_by_id($inactive_batch_id),
		bvmgr_pass_claims_get_batch_by_id($unsupported_batch_id),
		bvmgr_pass_claims_get_batch_by_id($unrelated_batch_id),
	);
	$eligible_for_source = vms_pass_outreach_eligible_business_batches($fixture_batches, $source_id);
	backstage_business_source_runtime_assert(count($eligible_for_source) === 3, 'Initial batch filtering did not retain percentage/fixed batches or exclude unrelated, inactive, and invalid batches.');
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
		'business_batch_offer_type' => 'percent',
		'business_batch_offer_amount' => '37.50',
		'business_batch_admissions_per_link' => 2,
		'business_batch_total_admission_cap' => 70,
		'business_batch_per_business_admission_cap' => 8,
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
	backstage_business_source_runtime_assert((int) ($new_batch_review['per_business_admission_cap'] ?? 0) === 8 && (int) ($new_batch_review['form_payload']['business_admission_cap'] ?? 0) === 8, 'Reviewed per-business admission limit was not preserved for QR setup.');
	backstage_business_source_runtime_assert((int) ($new_batch_review['batch_payload']['quantity'] ?? -1) === 0, 'Definition-only review did not retain a true zero individual-link quantity.');
	backstage_business_source_runtime_assert((int) ($new_batch_review['batch_payload']['single_event_plan_id'] ?? -1) === 0 && (string) ($new_batch_review['batch_payload']['start_date'] ?? 'x') === '' && (string) ($new_batch_review['batch_payload']['season_label'] ?? 'x') === '', 'Any Event review retained irrelevant forged scope values.');
	$created_batch = vms_pass_outreach_create_business_offer_batch((array) $new_batch_review['batch_payload'], $user_id);
	backstage_business_source_runtime_assert(is_array($created_batch) && absint($created_batch['id'] ?? 0) > 0, 'Explicit reviewed batch definition was not created.');
	$created_batch_id = absint($created_batch['id']);
	backstage_business_source_runtime_assert(absint($created_batch['generated_count'] ?? 0) === 0, 'New batch setup silently generated individual claim links.');
	backstage_business_source_runtime_assert(absint($created_batch['quantity'] ?? 1) === 0, 'New batch definition stored a hidden individual claim-link quantity.');
	backstage_business_source_runtime_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE batch_id=%d', bvmgr_admission_table_pass_tokens(), $created_batch_id)) === 0, 'New batch setup inserted individual claim-link tokens.');
	backstage_business_source_runtime_assert(vms_pass_outreach_business_batch_offer_label($created_batch) === 'Admission Offer — 37.5% off admission for up to 2 people', 'Percentage offer wording does not reflect reviewed settings exactly.');
	$fixed_review = vms_pass_outreach_prepare_business_batch_review(array(
		'recipient_source_mode' => 'business_source',
		'tracking_category_mode' => 'existing',
		'related_source_id' => $source_id,
		'business_campaign_name' => $marker . ' fixed draft',
		'business_batch_name' => $marker . ' fixed reviewed batch',
		'business_batch_offer_type' => 'fixed',
		'business_batch_offer_amount' => '12.345',
		'business_batch_admissions_per_link' => 2,
		'business_batch_total_admission_cap' => 70,
		'business_batch_validity_type' => 'any_event',
	), $user_id);
	backstage_business_source_runtime_assert(is_array($fixed_review) && abs((float) $fixed_review['batch_payload']['value_amount'] - 12.35) < 0.001, 'Fixed offer amount was not rounded to the stored currency precision.');
	backstage_business_source_runtime_assert(vms_pass_outreach_business_batch_offer_label(array_merge($fixed_review['batch_payload'], array('id' => 1))) === 'Admission Offer — $12.35 off each admission for up to 2 people', 'Fixed offer wording is not per admission.');
	$rounded_percentage = vms_pass_outreach_prepare_business_batch_review(array_merge((array) $fixed_review['form_payload'], array(
		'related_source_id' => $source_id,
		'business_batch_name' => $marker . ' rounded percentage',
		'business_batch_offer_type' => 'percent',
		'business_batch_offer_amount' => '37.555',
		'business_batch_admissions_per_link' => 2,
		'business_batch_total_admission_cap' => 70,
		'business_batch_validity_type' => 'any_event',
	)), $user_id);
	backstage_business_source_runtime_assert(is_array($rounded_percentage) && abs((float) $rounded_percentage['batch_payload']['value_amount'] - 37.56) < 0.001, 'Percentage amount was not rounded to reviewed precision.');
	$invalid_percentage = vms_pass_outreach_prepare_business_batch_review(array_merge((array) $fixed_review['form_payload'], array(
		'related_source_id' => $source_id,
		'business_batch_name' => $marker . ' invalid percent',
		'business_batch_offer_type' => 'percent',
		'business_batch_offer_amount' => '100.01',
		'business_batch_admissions_per_link' => 2,
		'business_batch_total_admission_cap' => 70,
		'business_batch_validity_type' => 'any_event',
	)), $user_id);
	backstage_business_source_runtime_assert(is_wp_error($invalid_percentage) && $invalid_percentage->get_error_code() === 'invalid_percent_value', 'Percentage values above 100 were accepted.');
	$oversized_fixed = vms_pass_outreach_prepare_business_batch_review(array_merge((array) $fixed_review['form_payload'], array(
		'related_source_id' => $source_id,
		'business_batch_name' => $marker . ' oversized fixed',
		'business_batch_offer_type' => 'fixed',
		'business_batch_offer_amount' => '100000000',
		'business_batch_admissions_per_link' => 2,
		'business_batch_total_admission_cap' => 70,
		'business_batch_validity_type' => 'any_event',
	)), $user_id);
	backstage_business_source_runtime_assert(is_wp_error($oversized_fixed) && $oversized_fixed->get_error_code() === 'invalid_fixed_value', 'A fixed value beyond storage precision was accepted.');

	$scope_base = array(
		'recipient_source_mode' => 'business_source',
		'tracking_category_mode' => 'existing',
		'related_source_id' => $source_id,
		'business_campaign_name' => $marker . ' scope draft Café — You’ve…',
		'business_batch_name' => $marker . ' scope review',
		'business_batch_offer_type' => 'free',
		'business_batch_admissions_per_link' => 2,
		'business_batch_total_admission_cap' => 100,
		'business_batch_per_business_admission_cap' => 2,
		'business_batch_expires_at' => '2030-12-31T23:00',
		'business_batch_notes' => 'Café — You’ve… ひらがな é',
	);
	$scope_cases = array(
		'any_event' => array('event' => $event_plan_id, 'start' => '2030-01-01', 'end' => '2030-02-01', 'season' => 'Forged season'),
		'single_event' => array('event' => $event_plan_id, 'start' => '2030-01-01', 'end' => '2030-02-01', 'season' => 'Forged season'),
		'date_range' => array('event' => $event_plan_id, 'start' => '2030-01-01', 'end' => '2030-02-01', 'season' => 'Forged season'),
		'season' => array('event' => $event_plan_id, 'start' => '2030-01-01', 'end' => '2030-02-01', 'season' => 'Winter Café'),
	);
	foreach ($scope_cases as $scope => $scope_values) {
		$scope_review = vms_pass_outreach_prepare_business_batch_review(array_merge($scope_base, array(
			'business_batch_validity_type' => $scope,
			'business_batch_single_event_plan_id' => $scope_values['event'],
			'business_batch_start_date' => $scope_values['start'],
			'business_batch_end_date' => $scope_values['end'],
			'business_batch_season_label' => $scope_values['season'],
		)), $user_id);
		backstage_business_source_runtime_assert(is_array($scope_review), 'Definition review failed for scope ' . $scope . '.');
		$scope_payload = (array) $scope_review['batch_payload'];
		backstage_business_source_runtime_assert((string) $scope_payload['expires_at'] !== '', 'Offer expiry was lost for scope ' . $scope . '.');
		if ($scope === 'any_event') {
			backstage_business_source_runtime_assert((int) $scope_payload['single_event_plan_id'] === 0 && $scope_payload['start_date'] === '' && $scope_payload['end_date'] === '' && $scope_payload['season_label'] === '', 'Any Event retained hidden scope values.');
		} elseif ($scope === 'single_event') {
			backstage_business_source_runtime_assert((int) $scope_payload['single_event_plan_id'] === $event_plan_id && $scope_payload['start_date'] === '' && $scope_payload['end_date'] === '' && $scope_payload['season_label'] === '', 'One Event did not isolate its event selection.');
		} elseif ($scope === 'date_range') {
			backstage_business_source_runtime_assert((int) $scope_payload['single_event_plan_id'] === 0 && $scope_payload['start_date'] === '2030-01-01' && $scope_payload['end_date'] === '2030-02-01' && $scope_payload['season_label'] === '', 'Date Range did not isolate its dates.');
		} else {
			backstage_business_source_runtime_assert((int) $scope_payload['single_event_plan_id'] === 0 && $scope_payload['season_label'] === 'Winter Café' && $scope_payload['start_date'] === '2030-01-01' && $scope_payload['end_date'] === '2030-02-01', 'Season did not retain its supported label and dates.');
		}
		backstage_business_source_runtime_assert((string) ($scope_review['form_payload']['campaign_name'] ?? '') === $marker . ' scope draft Café — You’ve…', 'UTF-8 campaign draft was not preserved for scope ' . $scope . '.');
	}
	$invalid_date_scope = vms_pass_outreach_prepare_business_batch_review(array_merge($scope_base, array(
		'business_batch_validity_type' => 'date_range',
		'business_batch_start_date' => '',
		'business_batch_end_date' => '',
	)), $user_id);
	backstage_business_source_runtime_assert(is_wp_error($invalid_date_scope) && $invalid_date_scope->get_error_code() === 'invalid_date_range', 'Disabled-JavaScript Date Range submission bypassed required dates.');

	$complimentary_review = vms_pass_outreach_prepare_business_batch_review(array_merge($scope_base, array(
		'business_batch_name' => $marker . ' complimentary claim batch',
		'business_batch_validity_type' => 'single_event',
		'business_batch_single_event_plan_id' => $event_plan_id,
		'business_batch_start_date' => '',
		'business_batch_end_date' => '',
		'business_batch_season_label' => '',
		'business_batch_expires_at' => '',
	)), $user_id);
	backstage_business_source_runtime_assert(is_array($complimentary_review), 'Complimentary definition-only review failed.');
	$complimentary_batch = vms_pass_outreach_create_business_offer_batch((array) $complimentary_review['batch_payload'], $user_id);
	backstage_business_source_runtime_assert(is_array($complimentary_batch) && absint($complimentary_batch['id'] ?? 0) > 0, 'Complimentary definition-only batch creation failed.');
	$complimentary_batch_id = absint($complimentary_batch['id']);
	backstage_business_source_runtime_assert(absint($complimentary_batch['quantity'] ?? 1) === 0 && absint($complimentary_batch['generated_count'] ?? 1) === 0, 'Complimentary definition created unwanted individual-link quantity or generated links.');

	$preview = vms_pass_outreach_build_business_source_preview($source_id, $batch_id);
	backstage_business_source_runtime_assert(is_array($preview), 'Business Source preview failed.');
	backstage_business_source_runtime_assert((int) $preview['active_membership_count'] === 35, 'Preview did not include all 35 active businesses.');
	backstage_business_source_runtime_assert((int) $preview['link_eligible_count'] === 35, 'Blank email incorrectly removed link eligibility.');
	backstage_business_source_runtime_assert((int) $preview['email_count'] === 21 && (int) $preview['missing_email_count'] === 14, 'Email and blank-email totals are incorrect.');
	backstage_business_source_runtime_assert((int) $preview['individual_claim_link_quantity'] === 12 && (int) $preview['batch_total_admission_cap'] === 70, 'Batch quantities were not kept distinct.');
	backstage_business_source_runtime_assert(str_contains((string) $preview['rows'][0]['address'], 'Preview Lane'), 'Mapped address is absent from business preview.');
	backstage_business_source_runtime_assert(str_contains((string) $preview['rows'][0]['notes'], 'Research note'), 'Research Notes were not retained.');
	$paid_preview = vms_pass_outreach_build_business_source_preview($source_id, $paid_batch_id);
	backstage_business_source_runtime_assert(is_array($paid_preview) && $paid_preview['batch_offer_type'] === 'paid_discount' && (int) $paid_preview['link_eligible_count'] === 35, 'The percentage Admission Offer route did not preserve all 35 link-eligible businesses.');
	$fixed_preview = vms_pass_outreach_build_business_source_preview($source_id, $fixed_batch_id);
	backstage_business_source_runtime_assert(is_array($fixed_preview) && $fixed_preview['batch_offer_type'] === 'paid_discount' && str_contains((string) $fixed_preview['batch_offer_label'], '$12.35 off each admission'), 'The fixed Admission Offer route was not eligible or accurately labeled.');
	backstage_business_source_runtime_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE campaign_id > 0 AND campaign_id IN (SELECT id FROM %i WHERE related_source_id=%d)', $recipient_table, $campaign_table, $source_id)) === 0, 'Fixture unexpectedly has historical recipients.');

	$complimentary_preview = vms_pass_outreach_build_business_source_preview($source_id, $complimentary_batch_id);
	backstage_business_source_runtime_assert(is_array($complimentary_preview) && $complimentary_preview['batch_offer_type'] === 'complimentary', 'Complimentary definition preview failed.');
	$complimentary_setup = array(
		'campaign_name' => $marker . ' complimentary claim campaign',
		'campaign_purpose' => 'guest_pass_invitation',
		'email_subject' => "You're invited to Serenade Range",
		'message_template' => vms_pass_outreach_default_message_template(),
		'internal_notes' => 'Disposable complimentary customer claim.',
		'related_source_id' => $source_id,
		'related_batch_id' => $complimentary_batch_id,
		'validity_type' => 'single_event',
		'single_event_plan_id' => $event_plan_id,
		'admissions_per_recipient' => 2,
		'total_admission_cap' => 0,
		'business_admission_cap' => 2,
		'status' => 'active',
		'eligibility_mode' => 'anyone_with_invite',
		'recipient_source_mode' => 'business_source',
		'tracking_category_mode' => 'existing',
	);
	$complimentary_created = vms_pass_outreach_create_business_source_campaign($complimentary_setup, $complimentary_preview, $user_id);
	backstage_business_source_runtime_assert(is_array($complimentary_created) && !empty($complimentary_created['campaign']['id']), 'Complimentary customer-claim campaign creation failed.');
	$complimentary_campaign_id = absint($complimentary_created['campaign']['id']);
	$handoff = get_transient(vms_pass_outreach_business_distribution_handoff_key($complimentary_campaign_id));
	backstage_business_source_runtime_assert(is_array($handoff) && (int) ($handoff['admission_cap'] ?? 0) === 2, 'Reviewed per-business limit did not reach the QR setup handoff.');
	$complimentary_distribution_id = backstage_outreach_create_distribution($complimentary_campaign_id, $source_id, $business_ids[0], 'complimentary', 2, 0, '', array(), array(), $user_id);
	backstage_business_source_runtime_assert($complimentary_distribution_id > 0, 'Complimentary reusable business link creation failed.');
	$flyer_read_only_before = array(
		'tokens' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE batch_id=%d', bvmgr_admission_table_pass_tokens(), $complimentary_batch_id)),
		'claims' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE batch_id=%d', bvmgr_admission_table_pass_claims(), $complimentary_batch_id)),
		'mappings' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE campaign_id=%d', backstage_outreach_business_table('distribution_claims'), $complimentary_campaign_id)),
		'generated_count' => (int) $wpdb->get_var($wpdb->prepare('SELECT generated_count FROM %i WHERE id=%d', $batch_table, $complimentary_batch_id)),
	);
	$flyer_row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', backstage_outreach_business_table('campaign_businesses'), $complimentary_distribution_id), ARRAY_A);
	$flyer_context = backstage_outreach_distribution_flyer_context(backstage_outreach_distribution_token((array) $flyer_row));
	backstage_business_source_runtime_assert(is_array($flyer_context), 'Active signed flyer context was rejected' . (is_wp_error($flyer_context) ? ': ' . $flyer_context->get_error_code() . ' — ' . $flyer_context->get_error_message() : '.'));
	$default_design = backstage_outreach_resolved_flyer_design($complimentary_campaign_id, (array) $flyer_context['batch']);
	$default_flyer_html = backstage_outreach_distribution_flyer_html($flyer_context);
	backstage_business_source_runtime_assert((string) $default_design['heading'] !== '' && str_contains($default_flyer_html, (string) $default_design['heading']), 'The venue-default flyer design was not resolved on a campaign without overrides.');
	backstage_business_source_runtime_assert((string) $default_design['artwork_source'] === 'event' && (int) $default_design['artwork_id'] === $event_artwork_id, 'Automatic artwork did not follow the One Event batch through its linked TEC event image.');
	$alternate_design = backstage_outreach_resolved_flyer_design($complimentary_campaign_id, array_merge((array) $flyer_context['batch'], array('single_event_plan_id' => $alternate_event_plan_id)));
	backstage_business_source_runtime_assert((string) $alternate_design['artwork_source'] === 'event' && (int) $alternate_design['artwork_id'] === $alternate_event_artwork_id, 'Changing the reviewed Event Plan did not change automatic artwork.');
	$missing_event_design = backstage_outreach_resolved_flyer_design($complimentary_campaign_id, array_merge((array) $flyer_context['batch'], array('single_event_plan_id' => $missing_art_event_plan_id)));
	backstage_business_source_runtime_assert((string) $missing_event_design['artwork_source'] !== 'event', 'A One Event batch without event artwork did not fall back safely.');
	$non_single_design = backstage_outreach_resolved_flyer_design($complimentary_campaign_id, array_merge((array) $flyer_context['batch'], array('validity_type' => 'date_range', 'single_event_plan_id' => $event_plan_id)));
	backstage_business_source_runtime_assert((string) $non_single_design['artwork_source'] !== 'event', 'A non-single-event scope incorrectly used an Event Plan image.');
	update_option(backstage_outreach_flyer_design_option_key($complimentary_campaign_id), array('artwork_mode' => 'custom', 'artwork_id' => $event_artwork_id), false);
	$custom_primary_design = backstage_outreach_resolved_flyer_design($complimentary_campaign_id, (array) $flyer_context['batch']);
	$custom_alternate_design = backstage_outreach_resolved_flyer_design($complimentary_campaign_id, array_merge((array) $flyer_context['batch'], array('single_event_plan_id' => $alternate_event_plan_id)));
	backstage_business_source_runtime_assert((string) $custom_primary_design['artwork_source'] === 'campaign' && (int) $custom_primary_design['artwork_id'] === $event_artwork_id && (int) $custom_alternate_design['artwork_id'] === $event_artwork_id, 'Changing the selected event overwrote an explicit campaign artwork override.');
	update_option(backstage_outreach_flyer_design_option_key($complimentary_campaign_id), array('artwork_mode' => 'none', 'artwork_id' => $event_artwork_id), false);
	$explicit_none_design = backstage_outreach_resolved_flyer_design($complimentary_campaign_id, (array) $flyer_context['batch']);
	backstage_business_source_runtime_assert((string) $explicit_none_design['artwork_source'] === 'none' && (int) $explicit_none_design['artwork_id'] === 0, 'Explicit No artwork did not override automatic event artwork.');
	delete_option(backstage_outreach_flyer_design_option_key($complimentary_campaign_id));
	$portrait_layout = backstage_outreach_normalize_flyer_layout('portrait', 'panels', 'right');
	$landscape_layout = backstage_outreach_normalize_flyer_layout('landscape', 'panels', 'right');
	backstage_business_source_runtime_assert($portrait_layout['orientation'] === 'portrait' && $portrait_layout['panel_position'] === 'bottom', 'Legacy portrait side-by-side flyer settings were not normalized to stacked layout.');
	backstage_business_source_runtime_assert($landscape_layout['orientation'] === 'landscape' && $landscape_layout['panel_position'] === 'bottom', 'Legacy landscape side-by-side flyer settings were not normalized to the horizontal offer band.');
	update_option(backstage_outreach_flyer_design_option_key($complimentary_campaign_id), array(
		'heading' => 'Café — Live Music ひらがな é',
		'subheading' => 'You’ve found tonight’s offer.',
		'artwork_mode' => 'none',
		'artwork_id' => 0,
		'layout_mode' => 'custom',
		'orientation' => 'landscape',
		'composition' => 'full',
		'panel_position' => 'right',
		'show_heading' => 1,
		'show_subheading' => 1,
		'show_logo' => 0,
	), false);
	$flyer_html = backstage_outreach_distribution_flyer_html($flyer_context);
	backstage_business_source_runtime_assert(str_contains($flyer_html, 'Print full flyer') && str_contains($flyer_html, 'Download full-flyer PDF') && str_contains($flyer_html, 'Print Landscape Letter offer only') && str_contains($flyer_html, 'Download Landscape Letter offer-only PDF') && str_contains($flyer_html, 'Complimentary Guest Passes') && str_contains($flyer_html, 'Maximum through this business') && str_contains($flyer_html, '@page{size:letter landscape') && str_contains($flyer_html, 'flyer-composition-full flyer-panel-bottom'), 'Complimentary flyer omitted full/Landscape Letter offer-only print/PDF actions, offer, limit, or normalized Letter composition.');
	backstage_business_source_runtime_assert(str_contains($flyer_html, 'Scan to choose an eligible event and claim your passes') && !str_contains($flyer_html, 'Guest Pass credentials'), 'Complimentary flyer retained internal terminology instead of plain scan guidance.');
	backstage_business_source_runtime_assert(str_contains($flyer_html, 'Café — Live Music ひらがな é') && str_contains($flyer_html, 'You’ve found tonight’s offer.'), 'Campaign flyer heading/subheading or UTF-8 did not survive resolution and rendering.');
	backstage_business_source_runtime_assert(strpos($flyer_html, '<h1 class="heading"') < strpos($flyer_html, '<p class="business"'), 'Flyer did not lead with venue/live-music presentation before the business attribution.');
	backstage_business_source_runtime_assert(str_contains($flyer_html, bvmgr_pass_claims_claim_qr_image_url(backstage_outreach_distribution_url($flyer_context))), 'Complimentary flyer QR was not generated from the actual customer offer URL.');
	backstage_business_source_runtime_assert(str_contains($flyer_html, vms_pass_outreach_business_batch_scope_label((array) $flyer_context['batch'])), 'Complimentary flyer omitted its applicable event scope.');
	$flyer_expiry_timestamp = time() + (2 * DAY_IN_SECONDS);
	$flyer_expiry = wp_date('Y-m-d H:i:s', $flyer_expiry_timestamp, wp_timezone());
	$expiring_flyer_html = backstage_outreach_distribution_flyer_html(array_merge($flyer_context, array('expires_at' => $flyer_expiry)));
	backstage_business_source_runtime_assert(str_contains($expiring_flyer_html, wp_date('F j, Y \\a\\t g:i a T', $flyer_expiry_timestamp, wp_timezone())), 'Flyer expiry omitted its local date, time, or timezone.');
	backstage_business_source_runtime_assert(!str_contains($flyer_html, 'Research note') && !str_contains($flyer_html, 'Contact') && !str_contains($flyer_html, '_wpnonce'), 'Public flyer exposed private business or admin data.');
	$flyer_read_only_after = array(
		'tokens' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE batch_id=%d', bvmgr_admission_table_pass_tokens(), $complimentary_batch_id)),
		'claims' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE batch_id=%d', bvmgr_admission_table_pass_claims(), $complimentary_batch_id)),
		'mappings' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE campaign_id=%d', backstage_outreach_business_table('distribution_claims'), $complimentary_campaign_id)),
		'generated_count' => (int) $wpdb->get_var($wpdb->prepare('SELECT generated_count FROM %i WHERE id=%d', $batch_table, $complimentary_batch_id)),
	);
	backstage_business_source_runtime_assert($flyer_read_only_after === $flyer_read_only_before, 'Opening or rendering the public flyer created a claim, token, mapping, or generated individual link.');
	$wpdb->update(backstage_outreach_business_table('campaign_businesses'), array('status' => 'paused'), array('id' => $complimentary_distribution_id));
	backstage_business_source_runtime_assert(is_wp_error(backstage_outreach_distribution_flyer_context(backstage_outreach_distribution_token((array) $flyer_row))), 'Paused flyer remained publicly available.');
	$wpdb->update(backstage_outreach_business_table('campaign_businesses'), array('status' => 'revoked'), array('id' => $complimentary_distribution_id));
	backstage_business_source_runtime_assert(is_wp_error(backstage_outreach_distribution_flyer_context(backstage_outreach_distribution_token((array) $flyer_row))), 'Revoked flyer remained publicly available.');
	$wpdb->update(backstage_outreach_business_table('campaign_businesses'), array('status' => 'active', 'expires_at' => wp_date('Y-m-d H:i:s', time() - HOUR_IN_SECONDS, wp_timezone())), array('id' => $complimentary_distribution_id));
	backstage_business_source_runtime_assert(is_wp_error(backstage_outreach_distribution_flyer_context(backstage_outreach_distribution_token((array) $flyer_row))), 'Expired flyer remained publicly available.');
	$wpdb->update(backstage_outreach_business_table('campaign_businesses'), array('expires_at' => null), array('id' => $complimentary_distribution_id));
	$complimentary_distribution_rows = backstage_outreach_distribution_rows($complimentary_campaign_id);
	$complimentary_distribution_row = reset($complimentary_distribution_rows);
	$complimentary_distribution = is_array($complimentary_distribution_row)
		? backstage_outreach_distribution_context(backstage_outreach_distribution_token($complimentary_distribution_row))
		: null;
	backstage_business_source_runtime_assert(is_array($complimentary_distribution), 'Complimentary reusable business link did not resolve.');
	backstage_business_source_runtime_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE batch_id=%d', bvmgr_admission_table_pass_tokens(), $complimentary_batch_id)) === 0, 'Complimentary definition had a token before an actual customer claim.');
	$claim_event = bvmgr_pass_claims_get_event_plan_brief($event_plan_id);
	$claim_input = array(
		'first_name' => 'Café',
		'last_name' => 'Customer',
		'phone' => '312-555-0199',
		'email' => '',
		'party_size' => 2,
		'opt_in' => 0,
	);
	$failed_submission_key = 'disposable-failed-' . wp_generate_uuid4();
	$force_claim_failure = static function ($payload, array $context) use ($complimentary_distribution_id) {
		return absint($context['distribution_id'] ?? 0) === $complimentary_distribution_id ? null : $payload;
	};
	add_filter('bvmgr_pass_claims_claim_insert_payload', $force_claim_failure, PHP_INT_MAX, 2);
	$failed_claim = backstage_outreach_partner_claim($complimentary_distribution, (array) $claim_event, $claim_input, $failed_submission_key);
	remove_filter('bvmgr_pass_claims_claim_insert_payload', $force_claim_failure, PHP_INT_MAX);
	backstage_business_source_runtime_assert(is_wp_error($failed_claim) && $failed_claim->get_error_code() === 'invalid_claim_insert_payload', 'Disposable forced claim failure did not reach the post-token rollback path.');
	backstage_business_source_runtime_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE batch_id=%d', bvmgr_admission_table_pass_tokens(), $complimentary_batch_id)) === 0, 'Failed claim retained its internal token instead of rolling back.');
	backstage_business_source_runtime_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE batch_id=%d', bvmgr_admission_table_pass_claims(), $complimentary_batch_id)) === 0, 'Failed claim retained a pass claim row.');
	backstage_business_source_runtime_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE pass_batch_id=%d', bvmgr_admission_table_entries(), $complimentary_batch_id)) === 0, 'Failed claim retained an admission row.');
	backstage_business_source_runtime_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE campaign_id=%d', backstage_outreach_business_table('distribution_claims'), $complimentary_campaign_id)) === 0, 'Failed claim retained an Outreach claim mapping.');

	$successful_submission_key = 'disposable-success-' . wp_generate_uuid4();
	$claim_result = backstage_outreach_partner_claim($complimentary_distribution, (array) $claim_event, $claim_input, $successful_submission_key);
	backstage_business_source_runtime_assert(is_array($claim_result) && absint($claim_result['claim_id'] ?? 0) > 0, 'Zero-token complimentary business batch could not complete an actual customer claim.');
	$complimentary_claim_id = absint($claim_result['claim_id']);
	backstage_business_source_runtime_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE batch_id=%d AND status=%s', bvmgr_admission_table_pass_tokens(), $complimentary_batch_id, 'claimed')) === 1, 'Complimentary customer claim did not atomically consume its internal claim token.');
	backstage_business_source_runtime_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE batch_id=%d AND status=%s', bvmgr_admission_table_pass_tokens(), $complimentary_batch_id, 'unclaimed')) === 0, 'Complimentary internal token was exposed as an unclaimed individual link.');
	backstage_business_source_runtime_assert((int) $wpdb->get_var($wpdb->prepare('SELECT generated_count FROM %i WHERE id=%d', $batch_table, $complimentary_batch_id)) === 0, 'Complimentary customer claim changed the individual-link generated count.');
	$claim_counts_before_retry = array(
		'tokens' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE batch_id=%d', bvmgr_admission_table_pass_tokens(), $complimentary_batch_id)),
		'claims' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE batch_id=%d', bvmgr_admission_table_pass_claims(), $complimentary_batch_id)),
		'entries' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE pass_batch_id=%d', bvmgr_admission_table_entries(), $complimentary_batch_id)),
		'mappings' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE campaign_id=%d', backstage_outreach_business_table('distribution_claims'), $complimentary_campaign_id)),
	);
	$retried_claim = backstage_outreach_partner_claim($complimentary_distribution, (array) $claim_event, $claim_input, $successful_submission_key);
	backstage_business_source_runtime_assert(is_array($retried_claim) && absint($retried_claim['claim_id'] ?? 0) === $complimentary_claim_id, 'Retry did not return the original complimentary claim.');
	$claim_counts_after_retry = array(
		'tokens' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE batch_id=%d', bvmgr_admission_table_pass_tokens(), $complimentary_batch_id)),
		'claims' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE batch_id=%d', bvmgr_admission_table_pass_claims(), $complimentary_batch_id)),
		'entries' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE pass_batch_id=%d', bvmgr_admission_table_entries(), $complimentary_batch_id)),
		'mappings' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE campaign_id=%d', backstage_outreach_business_table('distribution_claims'), $complimentary_campaign_id)),
	);
	backstage_business_source_runtime_assert($claim_counts_after_retry === $claim_counts_before_retry, 'Retry created duplicate claim data or consumed capacity twice.');
	$competing_input = array_merge($claim_input, array('first_name' => 'Competing', 'phone' => '312-555-0188'));
	$competing_claim = backstage_outreach_partner_claim($complimentary_distribution, (array) $claim_event, $competing_input, 'disposable-competing-' . wp_generate_uuid4());
	backstage_business_source_runtime_assert(is_wp_error($competing_claim), 'A competing complimentary claim exceeded the enforced per-business admission limit.');

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
	backstage_business_source_runtime_assert(backstage_outreach_is_reusable_business_campaign((array) $created['campaign']), 'Explicit business campaign was not identified from its creation audit.');
	$review_expiry_input = '2031-04-05T18:45';
	$review_expiry = bvmgr_pass_claims_parse_local_datetime($review_expiry_input);
	$review_token = backstage_outreach_business_review_token();
	set_transient(backstage_outreach_campaign_business_preview_key($campaign_id), array(
		'business_ids' => array_slice($business_ids, 0, 3),
		'distribution_type' => 'complimentary',
		'admission_cap' => 9,
		'order_cap' => 0,
		'expires_at' => $review_expiry,
		'expires_input' => $review_expiry_input,
		'membership_digest' => backstage_outreach_business_membership_digest($business_ids),
		'review_token' => $review_token,
		'reviewed_at' => time(),
		'configuration_digest' => '',
	), 30 * MINUTE_IN_SECONDS);
	ob_start();
	backstage_outreach_render_business_distribution_panel((array) $created['campaign']);
	$panel_html = (string) ob_get_clean();
	delete_transient(backstage_outreach_campaign_business_preview_key($campaign_id));
	backstage_business_source_runtime_assert(str_contains($panel_html, 'value="9"') && str_contains($panel_html, 'value="2031-04-05T18:45"'), 'Step 5 did not render pending reviewed caps and expiry instead of saved values.');
	backstage_business_source_runtime_assert(str_contains($panel_html, 'id="backstage-outreach-business-review"') && !str_contains($panel_html, 'notice notice-info inline" data-vms-tour="outreach-reviewed-preview'), 'The Step 5 review card was not retained as workflow content.');
	backstage_business_source_runtime_assert(str_contains($panel_html, 'Share with businesses') && str_contains($panel_html, 'This workflow does not create Outreach recipients'), 'Saved business links did not continue to route-aware sharing.');

	$first_distribution = $distributions[0];
	$wpdb->update(backstage_outreach_business_table('campaign_businesses'), array('admission_cap' => 4, 'expires_at' => $review_expiry), array('id' => (int) $first_distribution['id']));
	$updated_distributions = backstage_outreach_distribution_rows($campaign_id);
	$first_distribution = $updated_distributions[0];
	$share_context = backstage_outreach_business_share_context((array) $first_distribution, (array) $created['campaign'], bvmgr_pass_claims_get_batch_by_id($batch_id), 'Café — {business_name}', 'Hello {contact_name}…');
	backstage_business_source_runtime_assert(str_contains((string) $share_context['subject'], 'Café —') && str_contains((string) $share_context['message'], 'Hello') && str_contains((string) $share_context['message'], 'Total admissions allowed per business: 4'), 'UTF-8 or personalized limit details were lost from business sharing.');
	backstage_business_source_runtime_assert(str_contains((string) $share_context['message'], backstage_outreach_distribution_url((array) $first_distribution)) && str_contains((string) $share_context['message'], backstage_outreach_distribution_flyer_url((array) $first_distribution)), 'A personalized business message did not contain that business\'s own offer and flyer links.');
	backstage_business_source_runtime_assert(str_contains((string) $share_context['message'], 'does not reserve admissions') && str_contains((string) $share_context['message'], backstage_outreach_business_format_local_datetime($review_expiry)), 'Shared-capacity qualification or site-timezone expiry was missing from business sharing.');
	$email_count = count(array_filter($distributions, static fn(array $row): bool => sanitize_email((string) ($row['email'] ?? '')) !== ''));
	backstage_business_source_runtime_assert($email_count === 21, 'Business sharing did not retain 21 email-eligible and 14 copy-only businesses.');
	$delivery_review = array('subject' => 'Café — {business_name}', 'message' => 'Hello {contact_name}…');
	$delivery_result = backstage_outreach_attempt_business_share_email($first_distribution, (array) $created['campaign'], bvmgr_pass_claims_get_batch_by_id($batch_id), $delivery_review, array());
	backstage_business_source_runtime_assert((string) ($delivery_result['status'] ?? '') === 'handed_off' && (string) ($delivery_result['code'] ?? '') === 'accepted_by_mailer', 'Intercepted reviewed business email was not handed off through the delivery path.');
	$sent_map = backstage_outreach_business_share_sent_map($campaign_id);
	backstage_business_source_runtime_assert(isset($sent_map[(int) $first_distribution['id']]), 'Business delivery audit was not recognized for duplicate-send prevention.');
	$duplicate_delivery = backstage_outreach_attempt_business_share_email($first_distribution, (array) $created['campaign'], bvmgr_pass_claims_get_batch_by_id($batch_id), $delivery_review, $sent_map);
	backstage_business_source_runtime_assert((string) ($duplicate_delivery['status'] ?? '') === 'skipped' && (string) ($duplicate_delivery['code'] ?? '') === 'already_sent', 'A retried business email was not blocked after its sent audit.');
	backstage_business_source_runtime_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE campaign_id=%d', $recipient_table, $campaign_id)) === 0, 'Business sharing created individual Outreach recipients.');

	echo "Business Source campaign runtime PASS\n";
	echo wp_json_encode(array(
		'businesses' => 35,
		'with_email_reference' => 21,
		'missing_email' => 14,
		'historical_recipients' => 0,
		'reusable_links' => 35,
		'personalized_business_messages' => 35,
		'email_eligible_businesses' => 21,
		'copy_only_businesses' => 14,
		'ordinary_individual_links_generated' => 3,
		'new_definition_individual_claim_links' => 0,
		'complimentary_claim_internal_tokens' => 1,
		'failed_claim_residue' => 0,
		'retry_duplicate_writes' => 0,
		'shared_admission_cap' => 70,
	), JSON_PRETTY_PRINT) . "\n";
} finally {
	remove_filter('pre_wp_mail', $backstage_business_source_delivery_block, PHP_INT_MAX);
	if ($complimentary_campaign_id > 0) {
		delete_option(backstage_outreach_flyer_design_option_key($complimentary_campaign_id));
		delete_transient(vms_pass_outreach_business_distribution_handoff_key($complimentary_campaign_id));
		$wpdb->delete(backstage_outreach_business_table('distribution_claims'), array('campaign_id' => $complimentary_campaign_id));
		$wpdb->delete(backstage_outreach_business_table('campaign_businesses'), array('campaign_id' => $complimentary_campaign_id));
		$wpdb->delete(vms_admission_table_pass_outreach_campaigns(), array('id' => $complimentary_campaign_id));
	}
	if ($complimentary_claim_id > 0) {
		$wpdb->delete(bvmgr_admission_table_entries(), array('pass_claim_id' => $complimentary_claim_id));
		$wpdb->delete(bvmgr_admission_table_pass_claims(), array('id' => $complimentary_claim_id));
	}
	if ($complimentary_batch_id > 0) {
		$wpdb->delete(bvmgr_admission_table_pass_tokens(), array('batch_id' => $complimentary_batch_id));
		$wpdb->delete(bvmgr_admission_table_pass_batches(), array('id' => $complimentary_batch_id));
	}
	if ($campaign_id > 0) {
		delete_transient(backstage_outreach_campaign_business_preview_key($campaign_id));
		delete_transient(backstage_outreach_campaign_business_form_key($campaign_id));
		delete_transient(backstage_outreach_campaign_business_share_key($campaign_id));
		delete_option(backstage_outreach_business_share_template_key($campaign_id));
		$wpdb->query($wpdb->prepare(
			'DELETE FROM %i WHERE action IN (%s,%s,%s) AND details LIKE %s',
			bvmgr_admission_table_audit(),
			'outreach_business_share_email_handed_off',
			'outreach_business_share_email_sent',
			'outreach_business_share_email_failed',
			'%' . $wpdb->esc_like('"campaign_id":' . $campaign_id) . '%'
		));
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
		$wpdb->delete(bvmgr_admission_table_pass_tokens(), array('batch_id' => $batch_id));
		$wpdb->delete(bvmgr_admission_table_pass_batches(), array('id' => $batch_id));
	}
	if ($paid_batch_id > 0) {
		$wpdb->delete(bvmgr_admission_table_pass_batches(), array('id' => $paid_batch_id));
	}
	foreach (array($inactive_batch_id, $fixed_batch_id, $unsupported_batch_id, $unrelated_batch_id, $created_batch_id) as $fixture_batch_id) {
		if ($fixture_batch_id > 0) {
			$wpdb->delete(bvmgr_admission_table_pass_batches(), array('id' => $fixture_batch_id));
		}
	}
	foreach (array($event_plan_id, $alternate_event_plan_id, $missing_art_event_plan_id) as $fixture_event_plan_id) {
		if ($fixture_event_plan_id > 0) {
			$wpdb->delete(bvmgr_admission_table_audit(), array('event_plan_id' => $fixture_event_plan_id));
			wp_delete_post($fixture_event_plan_id, true);
		}
	}
	foreach ($tec_event_ids as $fixture_tec_event_id) {
		if ($fixture_tec_event_id > 0) {
			wp_delete_post($fixture_tec_event_id, true);
		}
	}
	foreach ($flyer_artwork_ids as $fixture_artwork_id) {
		if ($fixture_artwork_id > 0) {
			wp_delete_attachment($fixture_artwork_id, true);
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
	foreach (array($created_batch_id, $complimentary_batch_id) as $audited_batch_id) {
		$wpdb->query($wpdb->prepare(
			'DELETE FROM %i WHERE action=%s AND details LIKE %s',
			bvmgr_admission_table_audit(),
			'pass_outreach_business_batch_create',
			'%' . $wpdb->esc_like('"batch_id":' . $audited_batch_id) . '%'
		));
	}
}
