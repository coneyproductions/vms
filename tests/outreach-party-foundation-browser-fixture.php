<?php
/** Disposable browser fixture: wp eval-file ... [create|cleanup] <admin-user-id> */

defined('ABSPATH') || exit;
global $wpdb;
$option = 'backstage_outreach_party_browser_fixture';
$mode = sanitize_key((string) ($args[0] ?? 'create'));
$fixture = get_option($option, array());
$cleanup = static function (array $data) use ($wpdb, $option): void {
	$marker = sanitize_text_field((string) ($data['marker'] ?? 'Codex Party Browser Fixture'));
	$party_ids = array_map('absint', $wpdb->get_col($wpdb->prepare('SELECT id FROM %i WHERE display_name LIKE %s', backstage_outreach_party_table('parties'), $wpdb->esc_like($marker) . '%')));
	$recipient_id = absint($data['recipient_id'] ?? 0);
	if ($party_ids) {
		$ids = implode(',', $party_ids);
		$wpdb->query('DELETE FROM `' . backstage_outreach_party_table('identity_audit') . "` WHERE `from_party_id` IN ({$ids}) OR `to_party_id` IN ({$ids})");
		$wpdb->query('DELETE FROM `' . backstage_outreach_party_table('legacy_links') . "` WHERE `party_id` IN ({$ids})");
		$wpdb->query('DELETE FROM `' . backstage_outreach_party_table('campaign_roles') . "` WHERE `party_id` IN ({$ids})");
		$wpdb->query('DELETE FROM `' . backstage_outreach_party_table('sources') . "` WHERE `party_id` IN ({$ids})");
		$wpdb->query('DELETE FROM `' . backstage_outreach_party_table('affiliations') . "` WHERE `person_party_id` IN ({$ids}) OR `organization_party_id` IN ({$ids})");
		$wpdb->query('DELETE FROM `' . backstage_outreach_party_table('contact_methods') . "` WHERE `party_id` IN ({$ids})");
		$wpdb->query('DELETE FROM `' . backstage_outreach_party_table('parties') . "` WHERE `id` IN ({$ids})");
	}
	if ($recipient_id > 0) {
		$wpdb->delete(backstage_outreach_party_table('identity_audit'), array('legacy_type' => 'campaign_recipient', 'legacy_id' => $recipient_id));
		$wpdb->delete(vms_pass_outreach_recipient_table(), array('id' => $recipient_id));
	}
	if (!empty($data['campaign_id'])) {
		$wpdb->delete(vms_admission_table_pass_outreach_campaigns(), array('id' => absint($data['campaign_id'])));
	}
	if (!empty($data['source_id'])) {
		$wpdb->delete(bvmgr_admission_table_pass_sources(), array('id' => absint($data['source_id'])));
	}
	delete_option($option);
};

if ($mode === 'cleanup') {
	$cleanup(is_array($fixture) ? $fixture : array());
	echo "Party browser fixture cleaned.\n";
	return;
}
if (is_array($fixture) && !empty($fixture['person_id'])) {
	echo wp_json_encode($fixture) . "\n";
	return;
}

$marker = 'Codex Party Browser Fixture';
$user_id = absint($args[1] ?? 0);
if ($user_id <= 0 || !get_userdata($user_id)) {
	throw new RuntimeException('A disposable administrator ID is required.');
}
$now = backstage_outreach_party_now();
$wpdb->insert(bvmgr_admission_table_pass_sources(), array('source_name' => $marker . ' Source', 'status' => 'active', 'created_by' => $user_id, 'created_at' => $now));
$source_id = (int) $wpdb->insert_id;
$wpdb->insert(vms_admission_table_pass_outreach_campaigns(), array('campaign_name' => $marker . ' Historical', 'related_source_id' => $source_id, 'validity_type' => 'any_event', 'admissions_per_recipient' => 1, 'total_admission_cap' => 0, 'status' => 'draft', 'eligibility_mode' => 'anyone_with_invite', 'created_by' => $user_id, 'created_at' => $now, 'campaign_purpose' => 'guest_pass_invitation'));
$campaign_id = (int) $wpdb->insert_id;
$email = 'shared-party-browser@example.test';
$wpdb->insert(vms_pass_outreach_recipient_table(), array('campaign_id' => $campaign_id, 'full_name' => $marker . ' Café — 李', 'email' => $email, 'email_norm' => $email, 'company' => $marker . ' Realty', 'invite_token' => hash('sha256', $marker), 'status' => 'ready', 'claimed_headcount' => 0, 'created_by' => $user_id, 'created_at' => $now, 'send_status' => 'not_sent'));
$recipient_id = (int) $wpdb->insert_id;
$organization = backstage_outreach_party_save(array('party_type' => 'organization', 'display_name' => $marker . ' Realty — 東京'), $user_id);
$person = backstage_outreach_party_save(array('party_type' => 'person', 'display_name' => $marker . ' Café — 李', 'given_name' => 'Café', 'family_name' => '李', 'identifying_details' => 'North office'), $user_id);
$second = backstage_outreach_party_save(array('party_type' => 'person', 'display_name' => $marker . ' Shared Office'), $user_id);
if (!is_array($organization) || !is_array($person) || !is_array($second)) {
	throw new RuntimeException('Could not create browser Parties.');
}
backstage_outreach_party_save_contact_method((int) $person['id'], array('method_type' => 'email', 'value' => $email, 'is_primary' => 1), $user_id);
backstage_outreach_party_save_contact_method((int) $person['id'], array('method_type' => 'phone', 'value' => '(312) 555-0105', 'is_primary' => 1), $user_id);
backstage_outreach_party_save_contact_method((int) $second['id'], array('method_type' => 'email', 'value' => $email, 'is_primary' => 1), $user_id);
backstage_outreach_party_save_affiliation((int) $person['id'], (int) $organization['id'], 'broker', $user_id);
backstage_outreach_party_link_source((int) $person['id'], $source_id, $user_id, 'browser_fixture');
$fixture = array(
	'marker' => $marker,
	'admin_url' => vms_outreach_admin_page_url(array('section' => 'directory')),
	'person_id' => (int) $person['id'],
	'organization_id' => (int) $organization['id'],
	'second_person_id' => (int) $second['id'],
	'source_id' => $source_id,
	'campaign_id' => $campaign_id,
	'recipient_id' => $recipient_id,
	'email' => $email,
);
update_option($option, $fixture, false);
echo wp_json_encode($fixture, JSON_UNESCAPED_UNICODE) . "\n";
