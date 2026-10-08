<?php
/** Disposable runtime test: wp eval-file wp-content/plugins/packages/vms-github-reconcile/tests/outreach-party-foundation-runtime.php */

defined('ABSPATH') || exit;

function outreach_party_runtime_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

global $wpdb;
$marker = 'Party Foundation ' . wp_generate_password(10, false, false);
$user_id = get_current_user_id() ?: 1;
$now = backstage_outreach_party_now();
$party_ids = array();
$source_id = 0;
$batch_id = 0;
$campaign_ids = array();
$recipient_ids = array();
$suppression_id = 0;
$legacy_counts = array();
$party_tables = array('referral_redemptions', 'referral_distributions', 'identity_audit', 'legacy_links', 'campaign_roles', 'sources', 'affiliations', 'contact_methods', 'parties');

$count = static function (string $table) use ($wpdb): int {
	return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $table));
};
foreach (array(
	'sources' => bvmgr_admission_table_pass_sources(),
	'batches' => bvmgr_admission_table_pass_batches(),
	'campaigns' => vms_admission_table_pass_outreach_campaigns(),
	'recipients' => vms_pass_outreach_recipient_table(),
	'businesses' => backstage_outreach_business_table('businesses'),
	'memberships' => backstage_outreach_business_table('source_businesses'),
	'distributions' => backstage_outreach_business_table('campaign_businesses'),
	'contacts' => vms_outreach_table_contacts(),
) as $key => $table) {
	$legacy_counts[$key] = $count($table);
}

try {
	backstage_outreach_party_schema_upgrade();
	outreach_party_runtime_assert(get_option('backstage_outreach_party_db_version') === '1.1.0', 'Party schema marker was not installed.');
	outreach_party_runtime_assert(get_option('vms_outreach_db_version') === '1.1.0' && get_option('backstage_outreach_business_db_version') === '1.3.0', 'Existing Outreach schema markers changed.');
	foreach ($party_tables as $suffix) {
		$table = backstage_outreach_party_table($suffix);
		outreach_party_runtime_assert((string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table, "Missing Party table {$suffix}.");
	}

	$organization = backstage_outreach_party_save(array('party_type' => 'organization', 'display_name' => $marker . ' Realty Café — 東京', 'organization_name' => $marker . ' Realty Café — 東京', 'notes' => "Canonical only\nNo legacy rewrite"), $user_id);
	outreach_party_runtime_assert(is_array($organization), 'Could not create organization Party without email.');
	$party_ids[] = (int) $organization['id'];
	$person = backstage_outreach_party_save(array('party_type' => 'person', 'display_name' => $marker . ' Alex', 'given_name' => 'Alex', 'family_name' => 'Rivera', 'identifying_details' => 'North office'), $user_id);
	$shared = backstage_outreach_party_save(array('party_type' => 'person', 'display_name' => $marker . ' Jordan', 'given_name' => 'Jordan', 'family_name' => 'Lee'), $user_id);
	outreach_party_runtime_assert(is_array($person) && is_array($shared), 'Could not create person Parties.');
	$party_ids[] = (int) $person['id'];
	$party_ids[] = (int) $shared['id'];
	$email = strtolower(str_replace(' ', '-', $marker)) . '@example.test';
	foreach (array($person['id'], $shared['id']) as $party_id) {
		$method = backstage_outreach_party_save_contact_method((int) $party_id, array('method_type' => 'email', 'value' => $email, 'is_primary' => 1), $user_id);
		outreach_party_runtime_assert(is_array($method), 'Shared email was incorrectly treated as a global identity key.');
	}
	$phone = backstage_outreach_party_save_contact_method((int) $person['id'], array('method_type' => 'phone', 'label' => 'mobile', 'value' => '(312) 555-0199', 'is_primary' => 1), $user_id);
	$old_email = backstage_outreach_party_save_contact_method((int) $person['id'], array('method_type' => 'email', 'label' => 'previous', 'value' => 'previous-' . $email, 'status' => 'inactive'), $user_id);
	outreach_party_runtime_assert(is_array($phone) && is_array($old_email) && count(backstage_outreach_party_get_contact_methods((int) $person['id'], true)) === 3, 'Multiple/changed contact methods were not preserved.');
	$affiliation = backstage_outreach_party_save_affiliation((int) $person['id'], (int) $organization['id'], 'broker', $user_id);
	outreach_party_runtime_assert(is_array($affiliation), 'Person-to-organization affiliation failed.');
	outreach_party_runtime_assert(is_wp_error(backstage_outreach_party_save_affiliation((int) $organization['id'], (int) $person['id'], 'invalid', $user_id)), 'Invalid affiliation direction was accepted.');

	outreach_party_runtime_assert($wpdb->insert(bvmgr_admission_table_pass_sources(), array('source_name' => $marker, 'status' => 'active', 'created_by' => $user_id, 'created_at' => $now)) !== false, 'Could not create disposable Source.');
	$source_id = (int) $wpdb->insert_id;
	outreach_party_runtime_assert($wpdb->insert(bvmgr_admission_table_pass_batches(), array('source_id' => $source_id, 'batch_name' => $marker . ' free definition', 'quantity' => 0, 'validity_type' => 'any_event', 'venue_ids_json' => '[]', 'value_type' => 'free', 'value_amount' => '0.00', 'applies_to' => 'entry_only', 'status' => 'active', 'checkin_open_mode' => 'same_day', 'max_per_phone' => 0, 'generated_count' => 0, 'created_by' => $user_id, 'created_at' => $now, 'admissions_per_link' => 2, 'total_admission_cap' => 100, 'max_per_email' => 0)) !== false, 'Could not create disposable batch.');
	$batch_id = (int) $wpdb->insert_id;
	$source_link = backstage_outreach_party_link_source((int) $person['id'], $source_id, $user_id, 'synthetic_test');
	$source_retry = backstage_outreach_party_link_source((int) $person['id'], $source_id, $user_id, 'synthetic_test');
	outreach_party_runtime_assert(is_array($source_link) && is_array($source_retry) && (int) $source_link['id'] === (int) $source_retry['id'], 'Party-to-Source retry was not idempotent.');
	outreach_party_runtime_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE source_id=%d', backstage_outreach_business_table('source_businesses'), $source_id)) === 0, 'Party-to-Source association fabricated reusable-business membership.');
	$empty_preview = vms_pass_outreach_build_business_source_preview($source_id, $batch_id);
	outreach_party_runtime_assert(is_wp_error($empty_preview) && $empty_preview->get_error_code() === 'business_source_empty', 'Directory Source association changed reusable-business eligibility.');

	foreach (array('historical', 'draft') as $status) {
		outreach_party_runtime_assert($wpdb->insert(vms_admission_table_pass_outreach_campaigns(), array('campaign_name' => $marker . ' ' . $status, 'related_source_id' => $source_id, 'related_batch_id' => $batch_id, 'validity_type' => 'any_event', 'admissions_per_recipient' => 1, 'total_admission_cap' => 0, 'status' => $status === 'draft' ? 'draft' : 'completed', 'eligibility_mode' => 'anyone_with_invite', 'created_by' => $user_id, 'created_at' => $now, 'campaign_purpose' => 'guest_pass_invitation')) !== false, 'Could not create disposable campaign.');
		$campaign_ids[$status] = (int) $wpdb->insert_id;
	}
	$role = backstage_outreach_party_save_campaign_role((int) $person['id'], $campaign_ids['draft'], 'contacted', $user_id);
	outreach_party_runtime_assert(is_array($role), 'Party campaign role failed.');

	$org_ids = array((int) $organization['id']);
	for ($index = 1; $index < 36; $index++) {
		$org = backstage_outreach_party_save(array('party_type' => 'organization', 'display_name' => $marker . ' Brokerage ' . $index), $user_id);
		outreach_party_runtime_assert(is_array($org), 'Could not create Realtor brokerage Party.');
		$party_ids[] = (int) $org['id'];
		$org_ids[] = (int) $org['id'];
	}
	$realtor_party_ids = array((int) $person['id']);
	for ($index = 1; $index < 103; $index++) {
		$realtor = backstage_outreach_party_save(array('party_type' => 'person', 'display_name' => $marker . ' Realtor ' . $index, 'given_name' => 'Realtor', 'family_name' => (string) $index), $user_id);
		outreach_party_runtime_assert(is_array($realtor), 'Could not create Realtor Party.');
		$party_ids[] = (int) $realtor['id'];
		$realtor_party_ids[] = (int) $realtor['id'];
		outreach_party_runtime_assert(is_array(backstage_outreach_party_save_affiliation((int) $realtor['id'], $org_ids[$index % 36], 'agent', $user_id)), 'Could not create Realtor affiliation.');
	}
	foreach ($realtor_party_ids as $index => $party_id) {
		foreach ($campaign_ids as $campaign_id) {
			$recipient_email = sprintf('realtor-%03d@fixture.example.test', $index);
			outreach_party_runtime_assert($wpdb->insert(vms_pass_outreach_recipient_table(), array('campaign_id' => $campaign_id, 'first_name' => 'Realtor', 'last_name' => (string) $index, 'full_name' => $marker . ' Realtor ' . $index, 'email' => $recipient_email, 'email_norm' => $recipient_email, 'company' => $marker . ' Brokerage ' . ($index % 36), 'invite_token' => hash('sha256', $marker . '|' . $campaign_id . '|' . $index), 'status' => 'ready', 'claimed_headcount' => 0, 'created_by' => $user_id, 'created_at' => $now, 'send_status' => 'not_sent')) !== false, 'Could not create Realtor legacy recipient.');
			$recipient_id = (int) $wpdb->insert_id;
			$recipient_ids[] = $recipient_id;
			$snapshot = backstage_outreach_party_get_legacy_snapshot('campaign_recipient', $recipient_id);
			outreach_party_runtime_assert(is_array($snapshot), 'Could not snapshot legacy recipient.');
			$link = backstage_outreach_party_confirm_legacy_link($party_id, 'campaign_recipient', $recipient_id, $snapshot['snapshot_hash'], array('Fixture-reviewed historical/draft identity'), $user_id, hash('sha256', $marker . '|link|' . $recipient_id));
			outreach_party_runtime_assert(is_array($link), 'Could not map Realtor recipient snapshot.');
		}
	}
	outreach_party_runtime_assert(count($recipient_ids) === 206 && count($realtor_party_ids) === 103 && count($org_ids) === 36, 'Critical Realtor fixture cardinality is wrong.');
	outreach_party_runtime_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE source_id=%d', backstage_outreach_business_table('source_businesses'), $source_id)) === 0, 'Realtor identity mapping fabricated business memberships.');

	$probe_snapshot = backstage_outreach_party_get_legacy_snapshot('campaign_recipient', $recipient_ids[0]);
	$suggestions = backstage_outreach_party_suggestions(array('display_name' => '', 'email' => $email, 'phone' => '', 'organization' => ''), 20);
	outreach_party_runtime_assert(count($suggestions) >= 2 && !empty($suggestions[0]['ambiguous']), 'Shared-channel duplicate suggestions were not presented as ambiguous.');
	$first_link = backstage_outreach_party_get_legacy_link('campaign_recipient', $recipient_ids[0]);
	$retry = backstage_outreach_party_confirm_legacy_link((int) $first_link['party_id'], 'campaign_recipient', $recipient_ids[0], $probe_snapshot['snapshot_hash'], array('Retry'), $user_id, hash('sha256', $marker . '|retry'));
	outreach_party_runtime_assert(is_array($retry) && (int) $retry['id'] === (int) $first_link['id'], 'Reviewed mapping retry was not idempotent.');
	$wrong_party = backstage_outreach_party_confirm_legacy_link((int) $shared['id'], 'campaign_recipient', $recipient_ids[0], $probe_snapshot['snapshot_hash'], array('Wrong Party'), $user_id);
	outreach_party_runtime_assert(is_wp_error($wrong_party) && $wrong_party->get_error_code() === 'legacy_already_linked', 'Conflicting legacy mapping did not fail closed.');
	$corrected = backstage_outreach_party_reassign_legacy_link((int) $first_link['id'], (int) $shared['id'], $user_id, hash('sha256', $marker . '|correct'));
	outreach_party_runtime_assert(is_array($corrected) && (int) $corrected['party_id'] === (int) $shared['id'], 'Explicit mapping correction failed.');
	$unlink_request = hash('sha256', $marker . '|unlink');
	outreach_party_runtime_assert(backstage_outreach_party_unlink_legacy((int) $first_link['id'], $user_id, $unlink_request), 'Explicit mapping unlink failed.');
	outreach_party_runtime_assert(backstage_outreach_party_unlink_legacy((int) $first_link['id'], $user_id, $unlink_request), 'Explicit mapping unlink retry was not idempotent.');
	$restored = backstage_outreach_party_confirm_legacy_link((int) $person['id'], 'campaign_recipient', $recipient_ids[0], $probe_snapshot['snapshot_hash'], array('Restored'), $user_id, hash('sha256', $marker . '|restore'));
	outreach_party_runtime_assert(is_array($restored), 'Explicit mapping restore failed.');
	$wpdb->update(vms_pass_outreach_recipient_table(), array('full_name' => $marker . ' Changed'), array('id' => $recipient_ids[1]));
	$stale = backstage_outreach_party_confirm_legacy_link((int) $person['id'], 'campaign_recipient', $recipient_ids[1], hash('sha256', 'stale'), array('Stale'), $user_id);
	outreach_party_runtime_assert(is_wp_error($stale) && $stale->get_error_code() === 'legacy_snapshot_changed', 'Changed legacy snapshot was accepted.');

	$suppression = vms_outreach_upsert_suppression(array('email' => $email, 'reason' => 'manual', 'status' => 'active', 'scope' => vms_outreach_default_suppression_scope(), 'notes' => $marker), $user_id);
	outreach_party_runtime_assert(is_array($suppression), 'Could not create disposable suppression.');
	$suppression_id = (int) $suppression['id'];
	$states = backstage_outreach_party_email_suppression_state((int) $person['id']);
	outreach_party_runtime_assert(!empty(array_filter($states, static fn(array $state): bool => !empty($state['suppressed']))), 'Directory did not derive existing suppression state.');

	for ($index = count($party_ids); $index < 500; $index++) {
		$extra = backstage_outreach_party_save(array('party_type' => $index % 5 === 0 ? 'organization' : 'person', 'display_name' => $marker . ' Scale ' . $index), $user_id);
		outreach_party_runtime_assert(is_array($extra), 'Could not create scale Party.');
		$party_ids[] = (int) $extra['id'];
	}
	$timings = array();
	foreach (array(100, 200, 500) as $size) {
		$start = microtime(true);
		$rows = backstage_outreach_party_get_directory(array('search' => $marker, 'limit' => min(100, $size), 'page' => max(1, (int) ceil($size / 100))));
		$timings[(string) $size] = round((microtime(true) - $start) * 1000, 2);
		outreach_party_runtime_assert(is_array($rows) && $timings[(string) $size] < 2000, "Directory query at {$size} Parties exceeded two seconds.");
	}
	$missing = backstage_outreach_party_get_directory(array('search' => $marker, 'missing_email' => 'yes', 'limit' => 100));
	$has_email = backstage_outreach_party_get_directory(array('search' => $marker, 'missing_email' => 'no', 'limit' => 100));
	outreach_party_runtime_assert(count($missing) > 0 && count($has_email) >= 2, 'Missing-email directory filters failed.');

	echo "Outreach Party foundation runtime PASS\n";
	echo wp_json_encode(array('parties' => 500, 'realtor_people' => 103, 'brokerages' => 36, 'legacy_recipient_snapshots' => 206, 'business_memberships' => 0, 'directory_ms' => $timings), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} finally {
	if ($suppression_id > 0) {
		vms_outreach_remove_suppression($suppression_id);
	}
	foreach ($party_tables as $suffix) {
		$table = backstage_outreach_party_table($suffix);
		if ($suffix === 'parties') {
			$wpdb->query($wpdb->prepare('DELETE FROM %i WHERE display_name LIKE %s', $table, $wpdb->esc_like($marker) . '%'));
		} elseif ($suffix === 'identity_audit') {
			$party_list = $party_ids ? implode(',', array_map('absint', $party_ids)) : '0';
			$legacy_list = $recipient_ids ? implode(',', array_map('absint', $recipient_ids)) : '0';
			$wpdb->query("DELETE FROM `{$table}` WHERE `from_party_id` IN ({$party_list}) OR `to_party_id` IN ({$party_list}) OR (`legacy_type`='campaign_recipient' AND `legacy_id` IN ({$legacy_list}))");
		} elseif ($party_ids) {
			$ids = implode(',', array_map('absint', $party_ids));
			$column = $suffix === 'affiliations' ? 'person_party_id' : 'party_id';
			$wpdb->query("DELETE FROM `{$table}` WHERE `{$column}` IN ({$ids})");
		}
	}
	foreach ($recipient_ids as $recipient_id) {
		$wpdb->delete(vms_pass_outreach_recipient_table(), array('id' => $recipient_id));
	}
	foreach ($campaign_ids as $campaign_id) {
		$wpdb->delete(vms_admission_table_pass_outreach_campaigns(), array('id' => $campaign_id));
	}
	if ($batch_id > 0) {
		$wpdb->delete(bvmgr_admission_table_pass_batches(), array('id' => $batch_id));
	}
	if ($source_id > 0) {
		$wpdb->delete(bvmgr_admission_table_pass_sources(), array('id' => $source_id));
	}
	foreach (array(
		'sources' => bvmgr_admission_table_pass_sources(),
		'batches' => bvmgr_admission_table_pass_batches(),
		'campaigns' => vms_admission_table_pass_outreach_campaigns(),
		'recipients' => vms_pass_outreach_recipient_table(),
		'businesses' => backstage_outreach_business_table('businesses'),
		'memberships' => backstage_outreach_business_table('source_businesses'),
		'distributions' => backstage_outreach_business_table('campaign_businesses'),
		'contacts' => vms_outreach_table_contacts(),
	) as $key => $table) {
		outreach_party_runtime_assert($count($table) === $legacy_counts[$key], "Legacy {$key} count did not return to baseline.");
	}
}
