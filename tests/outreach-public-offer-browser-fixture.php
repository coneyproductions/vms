<?php
/** Disposable, GET-only public Guest Pass browser fixture. Run with `wp eval-file ... [create|cleanup] [single|multi]`. */

defined('ABSPATH') || exit;

global $wpdb;
$option = 'backstage_outreach_public_offer_browser_fixture';
$mode = sanitize_key((string) ($args[0] ?? 'create'));
$offer_scope = sanitize_key((string) ($args[1] ?? 'single'));
if (!in_array($offer_scope, array('single', 'multi'), true)) {
	throw new RuntimeException('Offer scope must be single or multi.');
}
$fixture = get_option($option, array());

if ($mode === 'cleanup') {
	if (is_array($fixture)) {
		$campaign_id = absint($fixture['campaign_id'] ?? 0);
		if ($campaign_id > 0) {
			$wpdb->delete(backstage_outreach_business_table('distribution_claims'), array('campaign_id' => $campaign_id));
			$wpdb->delete(backstage_outreach_business_table('campaign_businesses'), array('campaign_id' => $campaign_id));
			$wpdb->delete(vms_admission_table_pass_outreach_campaigns(), array('id' => $campaign_id));
		}
		$business_id = absint($fixture['business_id'] ?? 0);
		if ($business_id > 0) {
			$wpdb->delete(backstage_outreach_business_table('source_businesses'), array('business_id' => $business_id));
			$wpdb->delete(backstage_outreach_business_table('businesses'), array('id' => $business_id));
		}
		$batch_id = absint($fixture['batch_id'] ?? 0);
		if ($batch_id > 0) {
			$wpdb->delete(bvmgr_admission_table_pass_batches(), array('id' => $batch_id));
		}
		$source_id = absint($fixture['source_id'] ?? 0);
		if ($source_id > 0) {
			$wpdb->delete(bvmgr_admission_table_pass_sources(), array('id' => $source_id));
		}
		foreach (array_reverse((array) ($fixture['post_ids'] ?? array())) as $post_id) {
			wp_delete_post(absint($post_id), true);
		}
		foreach (array_reverse((array) ($fixture['tec_event_ids'] ?? array())) as $post_id) {
			wp_delete_post(absint($post_id), true);
		}
		foreach (array_reverse((array) ($fixture['attachment_ids'] ?? array())) as $attachment_id) {
			wp_delete_attachment(absint($attachment_id), true);
		}
	}
	delete_option($option);
	echo "Public offer browser fixture cleaned.\n";
	return;
}

if (is_array($fixture) && !empty($fixture['campaign_id'])) {
	throw new RuntimeException('Clean the existing public offer browser fixture before creating another one.');
}

$marker = 'Guest Pass Browser Fixture ' . wp_generate_password(8, false, false);
$now = backstage_outreach_business_now();
$post_ids = array();
$tec_event_ids = array();
$attachment_ids = array();
$event_dates = $offer_scope === 'single' ? array('2037-02-10') : array('2037-02-10', '2037-02-11');
foreach ($event_dates as $index => $event_date) {
	$event_id = (int) wp_insert_post(array(
		'post_type' => 'vms_event_plan',
		'post_status' => 'publish',
		'post_title' => $marker . ' Event ' . ($index + 1),
	));
	if ($event_id <= 0) {
		throw new RuntimeException('Could not create a disposable Event Plan.');
	}
	$post_ids[] = $event_id;
	$status_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status';
	update_post_meta($event_id, '_vms_event_date', $event_date);
	update_post_meta($event_id, $status_key, 'published');
	$tec_event_id = (int) wp_insert_post(array('post_type' => 'tribe_events', 'post_status' => 'publish', 'post_title' => $marker . ' Public Event ' . ($index + 1)));
	if ($tec_event_id <= 0) {
		throw new RuntimeException('Could not create a disposable linked TEC event.');
	}
	$tec_event_ids[] = $tec_event_id;
	$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFElEQVR42mNkYGD4z8DAwMDAxAADAA0EAQDRfRYAAAAASUVORK5CYII=', true);
	$upload = is_string($png) ? wp_upload_bits('public-offer-event-' . ($index + 1) . '.png', null, $png) : array('error' => 'decode');
	if (!empty($upload['error']) || empty($upload['file'])) {
		throw new RuntimeException('Could not create disposable event artwork.');
	}
	$attachment_id = (int) wp_insert_attachment(array('post_mime_type' => 'image/png', 'post_title' => $marker . ' Artwork ' . ($index + 1), 'post_status' => 'inherit'), (string) $upload['file']);
	if ($attachment_id <= 0) {
		throw new RuntimeException('Could not register disposable event artwork.');
	}
	$attachment_ids[] = $attachment_id;
	update_post_meta($attachment_id, '_wp_attachment_image_alt', $marker . ' event artwork ' . ($index + 1));
	update_post_meta($event_id, '_vms_tec_event_id', $tec_event_id);
	update_post_meta($tec_event_id, '_thumbnail_id', $attachment_id);
}

$wpdb->insert(bvmgr_admission_table_pass_sources(), array('source_name' => $marker, 'status' => 'active', 'created_by' => 1, 'created_at' => $now));
$source_id = (int) $wpdb->insert_id;
$validity_type = $offer_scope === 'single' ? 'single_event' : 'date_range';
$single_event_id = $offer_scope === 'single' ? $post_ids[0] : null;
$wpdb->insert(bvmgr_admission_table_pass_batches(), array(
	'source_id' => $source_id, 'batch_name' => $marker, 'quantity' => 0,
	'validity_type' => $validity_type, 'single_event_plan_id' => $single_event_id,
	'start_date' => $offer_scope === 'multi' ? $event_dates[0] : null,
	'end_date' => $offer_scope === 'multi' ? $event_dates[1] : null,
	'venue_ids_json' => '[]', 'value_type' => 'free', 'value_amount' => 100,
	'applies_to' => 'entry_only', 'status' => 'active', 'checkin_open_mode' => 'same_day',
	'max_per_phone' => 0, 'generated_count' => 0, 'created_by' => 1, 'created_at' => $now,
	'admissions_per_link' => 2, 'total_admission_cap' => 20, 'max_per_email' => 0,
));
$batch_id = (int) $wpdb->insert_id;
$wpdb->insert(vms_admission_table_pass_outreach_campaigns(), array(
	'campaign_name' => $marker, 'related_source_id' => $source_id, 'related_batch_id' => $batch_id,
	'validity_type' => $validity_type, 'single_event_plan_id' => $single_event_id,
	'start_date' => $offer_scope === 'multi' ? $event_dates[0] : null,
	'end_date' => $offer_scope === 'multi' ? $event_dates[1] : null,
	'admissions_per_recipient' => 2, 'total_admission_cap' => 20, 'status' => 'active',
	'eligibility_mode' => 'anyone_with_invite', 'created_by' => 1, 'created_at' => $now,
	'campaign_purpose' => 'guest_pass_invitation',
));
$campaign_id = (int) $wpdb->insert_id;
$business_id = backstage_outreach_insert_business(array('business_name' => 'Main Street Coffee', 'contact_name' => 'Fixture'), 1);
backstage_outreach_business_upsert_membership($source_id, $business_id, 'manual', null, 0, array(), 1);
$distribution_id = backstage_outreach_create_distribution($campaign_id, $source_id, $business_id, 'complimentary', 20, 0, '', $post_ids, array(), 1);
$distribution = backstage_outreach_discount_get_distribution($distribution_id);
$fixture = array(
	'campaign_id' => $campaign_id, 'source_id' => $source_id, 'batch_id' => $batch_id,
	'business_id' => $business_id, 'distribution_id' => $distribution_id, 'post_ids' => $post_ids,
	'tec_event_ids' => $tec_event_ids, 'attachment_ids' => $attachment_ids,
	'public_url' => backstage_outreach_distribution_url($distribution), 'offer_scope' => $offer_scope,
	'event_titles' => array_map('get_the_title', $post_ids),
	'event_dates' => array_map('bvmgr_pass_claims_format_public_date', $event_dates),
);
update_option($option, $fixture, false);
echo wp_json_encode($fixture) . "\n";
