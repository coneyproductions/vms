<?php
/** Disposable operator-browser fixture: wp eval-file ... [create|cleanup] <admin-user-id> */

defined('ABSPATH') || exit;
global $wpdb;
$option = 'backstage_outreach_party_referral_browser_fixture';
$mode = sanitize_key((string) ($args[0] ?? 'create'));
$fixture = get_option($option, array());
$cleanup = static function (array $data) use ($wpdb, $option): void {
	$party_id = absint($data['party_id'] ?? 0);
	foreach (array_map('absint', (array) ($data['recipient_ids'] ?? array())) as $recipient_id) {
		$wpdb->delete(vms_pass_outreach_recipient_table(), array('id' => $recipient_id), array('%d'));
	}
	$distribution_ids = $party_id > 0 ? array_map('absint', $wpdb->get_col($wpdb->prepare('SELECT id FROM %i WHERE party_id=%d', backstage_outreach_party_table('referral_distributions'), $party_id))) : array();
	if ($distribution_ids) {
		$ids = implode(',', $distribution_ids);
		$coupon_ids = array_map('absint', $wpdb->get_col("SELECT coupon_id FROM `" . backstage_outreach_party_table('referral_distributions') . "` WHERE id IN ({$ids})"));
		$wpdb->query("DELETE FROM `" . backstage_outreach_party_table('contact_activities') . "` WHERE distribution_id IN ({$ids})");
		$wpdb->query("DELETE FROM `" . backstage_outreach_party_table('referral_redemptions') . "` WHERE distribution_id IN ({$ids})");
		$wpdb->query("DELETE FROM `" . backstage_outreach_party_table('referral_distributions') . "` WHERE id IN ({$ids})");
		foreach ($coupon_ids as $coupon_id) {
			$coupon = new WC_Coupon($coupon_id);
			if ($coupon->get_id() > 0) {
				$coupon->delete(true);
			}
		}
	}
	if ($party_id > 0) {
		delete_transient(backstage_outreach_party_referral_review_key($party_id));
		$wpdb->query($wpdb->prepare('DELETE FROM %i WHERE from_party_id=%d OR to_party_id=%d', backstage_outreach_party_table('identity_audit'), $party_id, $party_id));
		foreach (array('legacy_links', 'campaign_roles', 'sources', 'contact_methods') as $suffix) {
			$wpdb->delete(backstage_outreach_party_table($suffix), array('party_id' => $party_id));
		}
		$wpdb->query($wpdb->prepare('DELETE FROM %i WHERE person_party_id=%d OR organization_party_id=%d', backstage_outreach_party_table('affiliations'), $party_id, $party_id));
		$wpdb->delete(backstage_outreach_party_table('parties'), array('id' => $party_id));
	}
	if (!empty($data['campaign_id'])) {
		$wpdb->delete(vms_admission_table_pass_outreach_campaigns(), array('id' => absint($data['campaign_id'])));
	}
	foreach (array_map('absint', (array) ($data['historical_campaign_ids'] ?? array())) as $campaign_id) {
		$wpdb->delete(vms_admission_table_pass_outreach_campaigns(), array('id' => $campaign_id), array('%d'));
	}
	if (!empty($data['batch_id'])) {
		$wpdb->delete(bvmgr_admission_table_pass_batches(), array('id' => absint($data['batch_id'])));
	}
	if (!empty($data['source_id'])) {
		$wpdb->delete(bvmgr_admission_table_pass_sources(), array('id' => absint($data['source_id'])));
	}
	foreach (array_reverse(array_map('absint', (array) ($data['post_ids'] ?? array()))) as $post_id) {
		wp_delete_post($post_id, true);
	}
	if (!empty($data['admin_id'])) {
		delete_transient('backstage_outreach_party_adoption_' . absint($data['admin_id']));
	}
	delete_option($option);
};

if ($mode === 'cleanup') {
	$cleanup(is_array($fixture) ? $fixture : array());
	echo "Party paid-referral browser fixture cleaned.\n";
	return;
}
if (is_array($fixture) && !empty($fixture['party_id'])) {
	echo wp_json_encode($fixture, JSON_UNESCAPED_UNICODE) . "\n";
	return;
}

$user_id = absint($args[1] ?? 0);
if ($user_id <= 0 || !get_userdata($user_id)) {
	throw new RuntimeException('A disposable administrator ID is required.');
}
$marker = 'Codex Partner Café — 東京';
$now = backstage_outreach_party_now();
$post_ids = array();
$event_start = wp_date('Y-m-d 19:00:00', time() + (18 * DAY_IN_SECONDS), wp_timezone());
$event_end = wp_date('Y-m-d 22:00:00', time() + (18 * DAY_IN_SECONDS), wp_timezone());
$event_id = tribe_create_event(array('post_status' => 'publish', 'post_title' => $marker . ' Event', 'EventStartDate' => $event_start, 'EventEndDate' => $event_end));
if (is_wp_error($event_id) || !$event_id) {
	throw new RuntimeException('Could not create the disposable event.');
}
$post_ids[] = (int) $event_id;
$plan_id = wp_insert_post(array('post_type' => 'vms_event_plan', 'post_status' => 'publish', 'post_title' => $marker . ' Plan'), true);
if (is_wp_error($plan_id) || !$plan_id) {
	throw new RuntimeException('Could not create the disposable Event Plan.');
}
$post_ids[] = (int) $plan_id;
update_post_meta((int) $plan_id, function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status', 'published');
update_post_meta((int) $plan_id, '_vms_event_date', wp_date('Y-m-d', time() + (18 * DAY_IN_SECONDS)));
update_post_meta((int) $plan_id, '_vms_tec_event_id', (int) $event_id);
update_post_meta((int) $plan_id, '_vms_start_time', '19:00');
$ticket_id = (int) tribe('tickets-plus.commerce.woo')->ticket_add((int) $event_id, array('ticket_name' => $marker . ' Ticket', 'ticket_price' => '60', 'ticket_show_description' => 'no', 'tribe-ticket' => array('capacity' => 100, 'mode' => 'own')));
if ($ticket_id <= 0) {
	throw new RuntimeException('Could not create the disposable ticket.');
}
$post_ids[] = $ticket_id;
$ticket = wc_get_product($ticket_id);
$ticket->set_status('publish');
$ticket->set_virtual(true);
$ticket->save();
update_post_meta($ticket_id, '_vms_event_plan_id', (int) $plan_id);
update_post_meta($ticket_id, '_vms_product_role', 'ga_ticket');

$wpdb->insert(bvmgr_admission_table_pass_sources(), array('source_name' => $marker . ' Source', 'status' => 'active', 'created_by' => $user_id, 'created_at' => $now));
$source_id = (int) $wpdb->insert_id;
$wpdb->insert(bvmgr_admission_table_pass_batches(), array('source_id' => $source_id, 'batch_name' => $marker . ' 50%', 'quantity' => 0, 'validity_type' => 'single_event', 'single_event_plan_id' => $plan_id, 'venue_ids_json' => '[]', 'value_type' => 'percent', 'value_amount' => 50, 'applies_to' => 'entry_only', 'status' => 'active', 'checkin_open_mode' => 'same_day', 'max_per_phone' => 0, 'generated_count' => 0, 'created_by' => $user_id, 'created_at' => $now, 'admissions_per_link' => 2, 'total_admission_cap' => 20, 'max_per_email' => 0));
$batch_id = (int) $wpdb->insert_id;
$wpdb->insert(vms_admission_table_pass_outreach_campaigns(), array('campaign_name' => $marker . ' Campaign', 'related_source_id' => $source_id, 'related_batch_id' => $batch_id, 'validity_type' => 'single_event', 'single_event_plan_id' => $plan_id, 'admissions_per_recipient' => 2, 'total_admission_cap' => 20, 'status' => 'active', 'eligibility_mode' => 'anyone_with_invite', 'created_by' => $user_id, 'created_at' => $now, 'campaign_purpose' => 'guest_pass_invitation'));
$campaign_id = (int) $wpdb->insert_id;
$historical_campaign_ids = array();
$recipient_ids = array();
for ($campaign_index = 1; $campaign_index <= 2; $campaign_index++) {
	$wpdb->insert(vms_admission_table_pass_outreach_campaigns(), array('campaign_name' => $marker . ' Historical ' . $campaign_index, 'related_source_id' => $source_id, 'related_batch_id' => $batch_id, 'validity_type' => 'single_event', 'single_event_plan_id' => $plan_id, 'admissions_per_recipient' => 2, 'total_admission_cap' => 206, 'status' => 'draft', 'eligibility_mode' => 'anyone_with_invite', 'created_by' => $user_id, 'created_at' => $now, 'campaign_purpose' => 'guest_pass_invitation'));
	$historical_campaign_ids[] = (int) $wpdb->insert_id;
}
foreach ($historical_campaign_ids as $campaign_id_for_recipient) {
	for ($person = 1; $person <= 103; $person++) {
		$name = sprintf('%s Realtor %03d', $marker, $person);
		$email = sprintf('party-browser-realtor-%03d@example.test', $person);
		$company = sprintf('Browser Brokerage %02d', (($person - 1) % 36) + 1);
		$wpdb->insert(vms_pass_outreach_recipient_table(), array(
			'campaign_id' => $campaign_id_for_recipient, 'contact_id' => 0, 'full_name' => $name, 'first_name' => $marker,
			'last_name' => sprintf('Realtor %03d', $person), 'email' => $email, 'email_norm' => $email, 'phone' => sprintf('903555%04d', $person),
			'phone_norm' => sprintf('903555%04d', $person), 'company' => $company, 'invite_token' => strtolower(wp_generate_password(32, false, false)),
			'send_status' => 'not_sent', 'status' => 'ready', 'created_by' => $user_id, 'created_at' => $now,
		));
		if ($wpdb->insert_id <= 0) { throw new RuntimeException('Could not create the adoption browser recipient fixture.'); }
		$recipient_ids[] = (int) $wpdb->insert_id;
	}
}
$party = backstage_outreach_party_save(array('party_type' => 'person', 'display_name' => $marker, 'given_name' => 'Café', 'family_name' => '東京'), $user_id);
if (!is_array($party) || is_wp_error(backstage_outreach_party_link_source((int) $party['id'], $source_id, $user_id, 'browser_fixture'))) {
	throw new RuntimeException('Could not create the canonical Party fixture.');
}
$email = backstage_outreach_party_save_contact_method((int) $party['id'], array('method_type' => 'email', 'value' => 'party-browser@example.test', 'is_primary' => 1, 'provenance_type' => 'browser_fixture'), $user_id);
if (is_wp_error($email)) {
	throw new RuntimeException('Could not create the Party email fixture.');
}
$fixture = array(
	'marker' => $marker,
	'admin_url' => vms_outreach_admin_page_url(array('section' => 'directory', 'view' => 'edit', 'party_id' => (int) $party['id'])),
	'party_id' => (int) $party['id'],
	'source_id' => $source_id,
	'batch_id' => $batch_id,
	'campaign_id' => $campaign_id,
	'historical_campaign_ids' => $historical_campaign_ids,
	'recipient_ids' => $recipient_ids,
	'admin_id' => $user_id,
	'post_ids' => $post_ids,
);
update_option($option, $fixture, false);
echo wp_json_encode($fixture, JSON_UNESCAPED_UNICODE) . "\n";
