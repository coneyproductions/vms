<?php
/** Disposable local browser fixture. Run with `wp eval-file ... create <temporary-admin-user-id>`. */

defined('ABSPATH') || exit;

global $wpdb;
$option = 'backstage_outreach_business_source_screenshot_fixture';
$mode = sanitize_key((string) ($args[0] ?? 'create'));
$fixture = get_option($option, array());

if ($mode === 'cleanup') {
	if (is_array($fixture)) {
		$fixture_source_ids = array_values(array_filter(array_map('absint', array_merge(
			array($fixture['source_id'] ?? 0),
			(array) ($fixture['extra_source_ids'] ?? array())
		))));
		if (!empty($fixture_source_ids)) {
			$placeholders = implode(',', array_fill(0, count($fixture_source_ids), '%d'));
			$campaign_ids = $wpdb->get_col($wpdb->prepare(
				"SELECT id FROM %i WHERE related_source_id IN ({$placeholders})",
				array_merge(array(vms_admission_table_pass_outreach_campaigns()), $fixture_source_ids)
			));
			foreach ((array) $campaign_ids as $campaign_id) {
				$campaign_id = absint($campaign_id);
				$wpdb->query($wpdb->prepare(
					'DELETE FROM %i WHERE details LIKE %s',
					bvmgr_admission_table_audit(),
					'%' . $wpdb->esc_like('"campaign_id":' . $campaign_id) . '%'
				));
				$wpdb->delete(backstage_outreach_business_table('distribution_claims'), array('campaign_id' => $campaign_id));
				$wpdb->delete(backstage_outreach_business_table('campaign_businesses'), array('campaign_id' => $campaign_id));
				$wpdb->delete(vms_pass_outreach_recipient_table(), array('campaign_id' => $campaign_id));
				$wpdb->delete(vms_admission_table_pass_outreach_campaigns(), array('id' => $campaign_id));
				delete_transient(backstage_outreach_campaign_business_preview_key($campaign_id));
			}
		}
		foreach ((array) ($fixture['business_ids'] ?? array()) as $business_id) {
			$wpdb->delete(backstage_outreach_business_table('source_businesses'), array('business_id' => absint($business_id)));
			$wpdb->delete(backstage_outreach_business_table('businesses'), array('id' => absint($business_id)));
		}
		foreach ((array) ($fixture['extra_batch_ids'] ?? array()) as $fixture_batch_id) {
			$wpdb->delete(bvmgr_admission_table_pass_batches(), array('id' => absint($fixture_batch_id)));
		}
		$created_batch_ids = $wpdb->get_col($wpdb->prepare(
			'SELECT id FROM %i WHERE batch_name LIKE %s',
			bvmgr_admission_table_pass_batches(),
			'Business Source Browser Fixture Created In Outreach%'
		));
		foreach ((array) $created_batch_ids as $created_batch_id) {
			$created_batch_id = absint($created_batch_id);
			$wpdb->delete(bvmgr_admission_table_pass_tokens(), array('batch_id' => $created_batch_id));
			$wpdb->delete(bvmgr_admission_table_pass_batches(), array('id' => $created_batch_id));
			$wpdb->query($wpdb->prepare(
				'DELETE FROM %i WHERE action=%s AND details LIKE %s',
				bvmgr_admission_table_audit(),
				'pass_outreach_business_batch_create',
				'%' . $wpdb->esc_like('"batch_id":' . $created_batch_id) . '%'
			));
		}
		if (!empty($fixture['source_id'])) {
			$wpdb->delete(bvmgr_admission_table_pass_sources(), array('id' => absint($fixture['source_id'])));
		}
		foreach ((array) ($fixture['extra_source_ids'] ?? array()) as $fixture_source_id) {
			$wpdb->delete(bvmgr_admission_table_pass_sources(), array('id' => absint($fixture_source_id)));
		}
		$user_id = absint($fixture['user_id'] ?? 1);
		vms_pass_outreach_clear_upload_preview($user_id);
		vms_pass_outreach_clear_upload_mapping($user_id);
		vms_pass_outreach_clear_business_batch_review($user_id);
		vms_pass_outreach_clear_campaign_form_flash($user_id);
		$wpdb->query($wpdb->prepare(
			'DELETE FROM %i WHERE details LIKE %s',
			bvmgr_admission_table_audit(),
			'%' . $wpdb->esc_like('Business Source Browser Fixture') . '%'
		));
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
$extra_source_ids = array();
$extra_batch_ids = array();
$wpdb->insert(bvmgr_admission_table_pass_sources(), array(
	'source_name' => $marker . ' Existing Eligible Batches',
	'status' => 'active',
	'created_by' => $user_id,
	'created_at' => $now,
));
$existing_source_id = (int) $wpdb->insert_id;
$extra_source_ids[] = $existing_source_id;
$insert_batch = static function (int $fixture_source_id, string $name, string $status, string $value_type, float $value_amount) use ($wpdb, $user_id, $now): int {
	$wpdb->insert(bvmgr_admission_table_pass_batches(), array(
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
	return (int) $wpdb->insert_id;
};
$extra_batch_ids[] = $insert_batch($source_id, $marker . ' Inactive', 'paused', 'free', 0.0);
$extra_batch_ids[] = $insert_batch($source_id, $marker . ' Unsupported 25%', 'active', 'percent', 25.0);
$existing_free_batch_id = $insert_batch($existing_source_id, $marker . ' Existing Complimentary', 'active', 'free', 0.0);
$existing_paid_batch_id = $insert_batch($existing_source_id, $marker . ' Existing Neighborhood Offer', 'active', 'percent', 50.0);
$extra_batch_ids[] = $existing_free_batch_id;
$extra_batch_ids[] = $existing_paid_batch_id;
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
if (!backstage_outreach_business_upsert_membership($existing_source_id, $business_ids[0], 'manual', null, 0, array('fixture' => true), $user_id)) {
	throw new RuntimeException('Could not link a business to the existing-batch Source.');
}

$fixture = array(
	'user_id' => $user_id,
	'source_id' => $source_id,
	'no_eligible_source_id' => $source_id,
	'existing_source_id' => $existing_source_id,
	'existing_free_batch_id' => $existing_free_batch_id,
	'existing_paid_batch_id' => $existing_paid_batch_id,
	'unrelated_batch_id' => $existing_free_batch_id,
	'extra_source_ids' => $extra_source_ids,
	'extra_batch_ids' => $extra_batch_ids,
	'business_ids' => $business_ids,
	'admin_url' => vms_pass_outreach_admin_page_url(),
	'source_name' => $marker,
	'batch_name' => $marker . ' Created In Outreach',
);
update_option($option, $fixture, false);
echo wp_json_encode($fixture) . "\n";
