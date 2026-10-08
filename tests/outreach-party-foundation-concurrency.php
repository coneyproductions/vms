<?php
/** Disposable race test: wp eval-file wp-content/plugins/packages/vms-github-reconcile/tests/outreach-party-foundation-concurrency.php */

defined('ABSPATH') || exit;
if (!function_exists('proc_open')) {
	throw new RuntimeException('proc_open is required.');
}
function outreach_party_race_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

global $wpdb;
$marker = 'Party Race ' . wp_generate_password(8, false, false);
$user_id = get_current_user_id() ?: 1;
$now = backstage_outreach_party_now();
$campaign_id = 0;
$recipient_id = 0;
$party_ids = array();
$files = array();
$option = 'backstage_outreach_party_race_' . strtolower(wp_generate_password(10, false, false));
try {
	outreach_party_race_assert($wpdb->insert(vms_admission_table_pass_outreach_campaigns(), array('campaign_name' => $marker, 'validity_type' => 'any_event', 'admissions_per_recipient' => 1, 'total_admission_cap' => 0, 'status' => 'draft', 'eligibility_mode' => 'anyone_with_invite', 'created_by' => $user_id, 'created_at' => $now, 'campaign_purpose' => 'guest_pass_invitation')) !== false, 'Could not create race campaign.');
	$campaign_id = (int) $wpdb->insert_id;
	outreach_party_race_assert($wpdb->insert(vms_pass_outreach_recipient_table(), array('campaign_id' => $campaign_id, 'full_name' => $marker, 'email' => 'party-race@example.test', 'email_norm' => 'party-race@example.test', 'invite_token' => hash('sha256', $marker), 'status' => 'ready', 'claimed_headcount' => 0, 'created_by' => $user_id, 'created_at' => $now, 'send_status' => 'not_sent')) !== false, 'Could not create race recipient.');
	$recipient_id = (int) $wpdb->insert_id;
	foreach (array('A', 'B') as $label) {
		$party = backstage_outreach_party_save(array('party_type' => 'person', 'display_name' => $marker . ' ' . $label), $user_id);
		outreach_party_race_assert(is_array($party), 'Could not create race Party.');
		$party_ids[] = (int) $party['id'];
	}
	$snapshot = backstage_outreach_party_get_legacy_snapshot('campaign_recipient', $recipient_id);
	update_option($option, array('marker' => $marker, 'party_a' => $party_ids[0], 'party_b' => $party_ids[1], 'recipient_id' => $recipient_id, 'snapshot_hash' => $snapshot['snapshot_hash'], 'user_id' => $user_id, 'start_at' => microtime(true) + 2.0), false);
	$worker = ABSPATH . 'wp-content/plugins/packages/vms-github-reconcile/tests/outreach-party-foundation-concurrency-worker.php';
	$processes = array();
	foreach (array('a', 'b') as $side) {
		$file = tempnam(sys_get_temp_dir(), 'party-race-');
		$files[] = $file;
		$process = proc_open(array('/opt/homebrew/bin/wp', 'eval-file', $worker, $option, $side), array(0 => array('pipe', 'r'), 1 => array('file', $file, 'w'), 2 => array('file', '/dev/null', 'a')), $pipes, ABSPATH);
		outreach_party_race_assert(is_resource($process), 'Could not start Party race worker.');
		fclose($pipes[0]);
		$processes[] = $process;
	}
	foreach ($processes as $process) {
		outreach_party_race_assert(proc_close($process) === 0, 'A Party race worker failed.');
	}
	$outputs = array_map(static fn(string $file): string => (string) file_get_contents($file), $files);
	$wins = count(array_filter($outputs, static fn(string $output): bool => strpos($output, 'PARTY_RACE:won:') !== false));
	$blocked = count(array_filter($outputs, static fn(string $output): bool => strpos($output, 'PARTY_RACE:blocked:legacy_already_linked') !== false));
	$link_count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE legacy_type=%s AND legacy_id=%d', backstage_outreach_party_table('legacy_links'), 'campaign_recipient', $recipient_id));
	outreach_party_race_assert($wins === 1 && $blocked === 1 && $link_count === 1, 'Concurrent explicit mappings did not produce exactly one winner and one fail-closed result: ' . wp_json_encode($outputs));
	echo "Outreach Party concurrent mapping PASS\n" . implode('', $outputs);
} finally {
	delete_option($option);
	foreach ($files as $file) {
		if (is_file($file)) {
			unlink($file);
		}
	}
	if ($recipient_id > 0) {
		$wpdb->delete(backstage_outreach_party_table('legacy_links'), array('legacy_type' => 'campaign_recipient', 'legacy_id' => $recipient_id));
		$wpdb->delete(backstage_outreach_party_table('identity_audit'), array('legacy_type' => 'campaign_recipient', 'legacy_id' => $recipient_id));
		$wpdb->delete(vms_pass_outreach_recipient_table(), array('id' => $recipient_id));
	}
	if ($party_ids) {
		$ids = implode(',', array_map('absint', $party_ids));
		$wpdb->query('DELETE FROM `' . backstage_outreach_party_table('identity_audit') . "` WHERE `from_party_id` IN ({$ids}) OR `to_party_id` IN ({$ids})");
		$wpdb->query('DELETE FROM `' . backstage_outreach_party_table('parties') . "` WHERE `id` IN ({$ids})");
	}
	if ($campaign_id > 0) {
		$wpdb->delete(vms_admission_table_pass_outreach_campaigns(), array('id' => $campaign_id));
	}
}
