<?php
/** Disposable local browser fixture. Run with `wp eval-file ... create <temporary-admin-user-id>`. */

defined('ABSPATH') || exit;

global $wpdb;
$option = 'backstage_outreach_business_source_screenshot_fixture';
$mode = sanitize_key((string) ($args[0] ?? 'create'));
$fixture = get_option($option, array());

if ($mode === 'cleanup') {
	if (is_array($fixture)) {
		foreach ((array) ($fixture['business_ids'] ?? array()) as $business_id) {
			$wpdb->delete(backstage_outreach_business_table('source_businesses'), array('business_id' => absint($business_id)));
			$wpdb->delete(backstage_outreach_business_table('businesses'), array('id' => absint($business_id)));
		}
		if (!empty($fixture['batch_id'])) {
			$wpdb->delete(bvmgr_admission_table_pass_batches(), array('id' => absint($fixture['batch_id'])));
		}
		if (!empty($fixture['source_id'])) {
			$wpdb->delete(bvmgr_admission_table_pass_sources(), array('id' => absint($fixture['source_id'])));
		}
		$user_id = absint($fixture['user_id'] ?? 1);
		vms_pass_outreach_clear_upload_preview($user_id);
		vms_pass_outreach_clear_upload_mapping($user_id);
		vms_pass_outreach_clear_campaign_form_flash($user_id);
	}
	delete_option($option);
	echo "Business Source browser fixture cleaned.\n";
	return;
}

if (is_array($fixture) && !empty($fixture['source_id'])) {
	echo wp_json_encode($fixture) . "\n";
	return;
}

$marker = 'Business Source Browser Fixture';
$now = backstage_outreach_business_now();
$user_id = absint($args[1] ?? 0);
if ($user_id <= 0 || !get_userdata($user_id)) {
	throw new RuntimeException('A disposable browser-test user ID is required.');
}
$wpdb->insert(bvmgr_admission_table_pass_sources(), array(
	'source_name' => $marker,
	'status' => 'active',
	'created_by' => $user_id,
	'created_at' => $now,
));
$source_id = (int) $wpdb->insert_id;
$wpdb->insert(bvmgr_admission_table_pass_batches(), array(
	'source_id' => $source_id,
	'batch_name' => $marker . ' Capacity',
	'quantity' => 9,
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
));
$batch_id = (int) $wpdb->insert_id;
$business_ids = array();
for ($index = 1; $index <= 35; $index++) {
	$business_id = backstage_outreach_insert_business(array(
		'business_name' => sprintf('Browser Fixture Business %02d With A Readable Long Name', $index),
		'contact_name' => sprintf('Fixture Contact %02d', $index),
		'email' => $index <= 21 ? sprintf('browser-business-%02d@example.test', $index) : '',
		'phone' => sprintf('555-020-%02d', $index),
		'website' => sprintf('https://example.test/browser-business-%02d', $index),
		'address_line' => sprintf('%d Long Review Avenue Suite %d', $index, $index + 100),
		'city' => 'Highland Park',
		'state' => 'Illinois',
		'postal_code' => '60035',
		'notes' => sprintf('Research Notes %02d: retained for operator review and escaped safely.', $index),
	), $user_id);
	if ($business_id <= 0 || !backstage_outreach_business_upsert_membership($source_id, $business_id, 'manual', null, 0, array('fixture' => true), $user_id)) {
		throw new RuntimeException('Could not create business browser fixture.');
	}
	$business_ids[] = $business_id;
}

$fixture = array(
	'user_id' => $user_id,
	'source_id' => $source_id,
	'batch_id' => $batch_id,
	'business_ids' => $business_ids,
	'admin_url' => vms_pass_outreach_admin_page_url(),
	'source_name' => $marker,
	'batch_name' => $marker . ' Capacity',
);
update_option($option, $fixture, false);
echo wp_json_encode($fixture) . "\n";
