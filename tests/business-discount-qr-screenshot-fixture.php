<?php
/** Disposable screenshot fixture. Run with `wp eval-file ... [create|cleanup]`. */

defined('ABSPATH') || exit;
global $wpdb;
$option = 'backstage_outreach_discount_screenshot_fixture';
$mode = sanitize_key((string) ($args[0] ?? 'create'));
$offer_type = sanitize_key((string) ($args[1] ?? 'percent'));
if (!in_array($offer_type, array('percent', 'fixed'), true)) {
	throw new RuntimeException('Offer type must be percent or fixed.');
}
$offer_amount = $offer_type === 'fixed' ? '12.35' : '50.00';
$offer_text = $offer_type === 'fixed' ? '$12.35 off each admission' : '50% off admission';
$fixture = get_option($option, array());

if ($mode === 'cleanup') {
	if (is_array($fixture)) {
		foreach ((array) ($fixture['coupon_ids'] ?? array()) as $coupon_id) {
			$coupon = new WC_Coupon(absint($coupon_id));
			if ($coupon->get_id() > 0) { $coupon->delete(true); }
		}
		$campaign_id = absint($fixture['campaign_id'] ?? 0);
		if ($campaign_id > 0) {
			$wpdb->delete(backstage_outreach_business_table('paid_redemptions'), array('campaign_id' => $campaign_id));
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
		if ($batch_id > 0) { $wpdb->delete(bvmgr_admission_table_pass_batches(), array('id' => $batch_id)); }
		$source_id = absint($fixture['source_id'] ?? 0);
		if ($source_id > 0) { $wpdb->delete(bvmgr_admission_table_pass_sources(), array('id' => $source_id)); }
		foreach (array_reverse((array) ($fixture['post_ids'] ?? array())) as $post_id) {
			wp_delete_post(absint($post_id), true);
		}
	}
	delete_option($option);
	echo "Screenshot fixture cleaned.\n";
	return;
}

if (is_array($fixture) && !empty($fixture['campaign_id'])) {
	echo wp_json_encode($fixture) . "\n";
	return;
}

$marker = 'QR Discount Screenshot Fixture';
$now = backstage_outreach_business_now();
$post_ids = array();
$tec_event_id = (int) $wpdb->get_var("SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->prefix}tec_events te ON te.post_id=p.ID WHERE p.post_type='tribe_events' AND p.post_status='publish' AND p.post_title LIKE 'DISPOSABLE QA%' AND te.start_date_utc>UTC_TIMESTAMP() ORDER BY te.start_date_utc LIMIT 1");
if ($tec_event_id <= 0) {
	throw new RuntimeException('A disposable published future TEC event is required for screenshots.');
}
$event_plan_id = (int) wp_insert_post(array('post_type' => 'vms_event_plan', 'post_status' => 'publish', 'post_title' => $marker . ' Event Plan'));
$post_ids[] = $event_plan_id;
$status_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status';
update_post_meta($event_plan_id, $status_key, 'published');
update_post_meta($event_plan_id, '_vms_event_date', wp_date('Y-m-d', time() + (14 * DAY_IN_SECONDS)));
update_post_meta($event_plan_id, '_vms_tec_event_id', $tec_event_id);
$provider = tribe('tickets-plus.commerce.woo');
$ticket_id = (int) $provider->ticket_add($tec_event_id, array('ticket_name' => 'Eligible General Admission', 'ticket_price' => '100', 'ticket_show_description' => 'no', 'tribe-ticket' => array('capacity' => 100, 'mode' => 'own')));
$post_ids[] = $ticket_id;
$ticket = wc_get_product($ticket_id);
$ticket->set_status('publish');
$ticket->set_tax_status('none');
$ticket->set_virtual(true);
$ticket->save();
update_post_meta($ticket_id, '_vms_event_plan_id', $event_plan_id);
update_post_meta($ticket_id, '_vms_product_role', 'ga_ticket');

$wpdb->insert(bvmgr_admission_table_pass_sources(), array('source_name' => $marker, 'status' => 'active', 'created_by' => 1, 'created_at' => $now));
$source_id = (int) $wpdb->insert_id;
$wpdb->insert(bvmgr_admission_table_pass_batches(), array('source_id' => $source_id, 'batch_name' => $offer_text . ' Business Offer', 'quantity' => 100, 'validity_type' => 'single_event', 'single_event_plan_id' => $event_plan_id, 'venue_ids_json' => '[]', 'value_type' => $offer_type, 'value_amount' => $offer_amount, 'applies_to' => 'entry_only', 'status' => 'active', 'checkin_open_mode' => 'same_day', 'max_per_phone' => 0, 'generated_count' => 0, 'created_by' => 1, 'created_at' => $now, 'admissions_per_link' => 2, 'total_admission_cap' => 40, 'max_per_email' => 0));
$batch_id = (int) $wpdb->insert_id;
$wpdb->insert(vms_admission_table_pass_outreach_campaigns(), array('campaign_name' => $offer_text . ' Partner Offer', 'related_source_id' => $source_id, 'related_batch_id' => $batch_id, 'validity_type' => 'single_event', 'single_event_plan_id' => $event_plan_id, 'expires_at' => wp_date('Y-m-d H:i:s', time() + (30 * DAY_IN_SECONDS), wp_timezone()), 'admissions_per_recipient' => 2, 'total_admission_cap' => 40, 'status' => 'active', 'eligibility_mode' => 'anyone_with_invite', 'created_by' => 1, 'created_at' => $now, 'campaign_purpose' => 'guest_pass_invitation'));
$campaign_id = (int) $wpdb->insert_id;
$business_id = backstage_outreach_insert_business(array('business_name' => 'Main Street Coffee', 'contact_name' => 'Partner Manager'), 1);
backstage_outreach_business_upsert_membership($source_id, $business_id, 'manual', null, 0, array(), 1);
$campaign = vms_pass_outreach_get_campaign_by_id($campaign_id);
$batch = bvmgr_pass_claims_get_batch_by_id($batch_id);
$configuration = backstage_outreach_discount_offer_configuration($campaign, $batch, 'coupon_backed');
$distribution_id = backstage_outreach_create_distribution($campaign_id, $source_id, $business_id, 'coupon_backed', 20, 10, wp_date('Y-m-d H:i:s', time() + (30 * DAY_IN_SECONDS), wp_timezone()), $configuration['event_ids'], $configuration['product_ids'], 1);
$distribution = backstage_outreach_discount_get_distribution($distribution_id);
$coupon = backstage_outreach_discount_ensure_coupon($distribution, $campaign, $configuration);
$wpdb->update(backstage_outreach_business_table('campaign_businesses'), array('coupon_id' => (int) $coupon['coupon_id'], 'coupon_code' => (string) $coupon['coupon_code']), array('id' => $distribution_id));
$distribution = backstage_outreach_discount_get_distribution($distribution_id);
$fixture = array(
	'campaign_id' => $campaign_id, 'source_id' => $source_id, 'batch_id' => $batch_id,
	'business_id' => $business_id, 'distribution_id' => $distribution_id,
	'coupon_ids' => array((int) $coupon['coupon_id']), 'post_ids' => $post_ids, 'ticket_id' => $ticket_id,
	'admin_url' => vms_pass_outreach_admin_page_url(array('campaign_id' => $campaign_id)) . '#backstage-outreach-partners',
	'public_url' => backstage_outreach_distribution_url($distribution),
	'offer_text' => $offer_text,
	'cart_url' => add_query_arg('add-to-cart', $ticket_id, wc_get_cart_url()),
);
update_option($option, $fixture, false);
echo wp_json_encode($fixture) . "\n";
