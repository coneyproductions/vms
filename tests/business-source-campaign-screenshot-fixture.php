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
				$wpdb->delete(backstage_outreach_business_table('contact_activities'), array('campaign_id' => $campaign_id));
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
				delete_transient(backstage_outreach_campaign_business_form_key($campaign_id));
				delete_transient(backstage_outreach_campaign_business_share_key($campaign_id));
				delete_option(backstage_outreach_business_share_template_key($campaign_id));
				delete_option(backstage_outreach_flyer_design_option_key($campaign_id));
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
		foreach ((array) ($fixture['artwork_ids'] ?? array($fixture['artwork_id'] ?? 0)) as $fixture_artwork_id) {
			if (absint($fixture_artwork_id) > 0) {
				wp_delete_attachment(absint($fixture_artwork_id), true);
			}
		}
		foreach (array('event_plan_id', 'tec_event_id') as $fixture_post_key) {
			if (absint($fixture[$fixture_post_key] ?? 0) > 0) {
				wp_delete_post(absint($fixture[$fixture_post_key]), true);
			}
		}
		$user_id = absint($fixture['user_id'] ?? 1);
		$wpdb->query($wpdb->prepare(
			'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
			$wpdb->options,
			$wpdb->esc_like('_transient_backstage_outreach_partner_') . '%' . $wpdb->esc_like('_' . $user_id . '_') . '%',
			$wpdb->esc_like('_transient_timeout_backstage_outreach_partner_') . '%' . $wpdb->esc_like('_' . $user_id . '_') . '%'
		));
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
$artwork_ids = array();
$create_artwork = static function (string $filename, string $title, int $width, int $height, array $colors) use ($marker): int {
	if (!function_exists('imagecreatetruecolor')) {
		return 0;
	}
	$image = imagecreatetruecolor($width, $height);
	$background = imagecolorallocate($image, $colors[0][0], $colors[0][1], $colors[0][2]);
	$accent = imagecolorallocate($image, $colors[1][0], $colors[1][1], $colors[1][2]);
	$white = imagecolorallocate($image, 255, 255, 255);
	$gold = imagecolorallocate($image, 244, 198, 92);
	$soft = imagecolorallocate($image, 231, 240, 237);
	$ink = imagecolorallocate($image, 20, 34, 42);
	imagefilledrectangle($image, 0, 0, $width, $height, $background);
	imagefilledellipse($image, (int) ($width * .76), (int) ($height * .31), (int) ($width * .67), (int) ($width * .67), $accent);
	imagefilledellipse($image, (int) ($width * .18), (int) ($height * .51), (int) ($width * .24), (int) ($width * .24), $gold);
	imagefilledrectangle($image, 0, (int) ($height * .73), $width, $height, $soft);
	imagefilledrectangle($image, (int) ($width * .055), (int) ($height * .055), (int) ($width * .945), (int) ($height * .69), $background);
	$bold_font = '/System/Library/Fonts/Supplemental/Arial Bold.ttf';
	$regular_font = '/System/Library/Fonts/Supplemental/Arial.ttf';
	$draw_text = static function (string $text, int $size, int $x, int $y, int $color, bool $bold = false) use ($image, $bold_font, $regular_font): void {
		$font = $bold ? $bold_font : $regular_font;
		if (function_exists('imagettftext') && is_readable($font)) {
			imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
			return;
		}
		imagestring($image, $bold ? 5 : 4, $x, max(0, $y - 18), $text, $color);
	};
	$left = (int) ($width * .09);
	$draw_text('SUMMER NIGHTS', max(34, (int) ($width * .048)), $left, (int) ($height * .23), $white, true);
	$draw_text('LIVE', max(48, (int) ($width * .07)), $left, (int) ($height * .37), $white, true);
	$draw_text('THE LAKESIDE REVUE', max(22, (int) ($width * .026)), $left, (int) ($height * .45), $gold, true);
	$draw_text('Saturday, October 18  |  7:30 PM', max(18, (int) ($width * .018)), $left, (int) ($height * .485), $white);
	$draw_text('Doors 6:00 PM  •  Music under the stars', max(16, (int) ($width * .014)), $left, (int) ($height * .515), $white);
	$draw_text('RESERVED OFFER AREA', max(16, (int) ($width * .017)), $left, (int) ($height * .82), $ink, true);
	$draw_text('Business admission details and QR appear here.', max(15, (int) ($width * .016)), $left, (int) ($height * .865), $ink);
	$draw_text('Fixture artwork — not a real event', max(13, (int) ($width * .014)), $left, (int) ($height * .925), $ink);
	ob_start();
	imagepng($image);
	$png = (string) ob_get_clean();
	imagedestroy($image);
	$upload = wp_upload_bits($filename, null, $png);
	if (!empty($upload['error']) || empty($upload['file'])) {
		return 0;
	}
	$attachment_id = wp_insert_attachment(array(
		'post_mime_type' => 'image/png',
		'post_title' => $marker . ' ' . $title,
		'post_status' => 'inherit',
	), (string) $upload['file']);
	if ($attachment_id > 0) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, (string) $upload['file']));
	}
	return (int) $attachment_id;
};
$portrait_artwork_id = $create_artwork('business-source-browser-fixture-portrait.png', 'Portrait Artwork', 1200, 1600, array(array(16, 50, 63), array(13, 109, 87)));
$landscape_artwork_id = $create_artwork('business-source-browser-fixture-landscape.png', 'Landscape Artwork', 1600, 900, array(array(49, 36, 85), array(197, 83, 57)));
$event_artwork_id = $create_artwork('business-source-browser-fixture-event.png', 'Selected Event Artwork', 1800, 1200, array(array(66, 26, 43), array(178, 92, 52)));
$artwork_ids = array_values(array_filter(array($portrait_artwork_id, $landscape_artwork_id, $event_artwork_id)));
$artwork_id = $portrait_artwork_id;
$tec_event_id = wp_insert_post(array(
	'post_type' => 'tribe_events',
	'post_status' => 'publish',
	'post_title' => $marker . ' Linked Public Event',
), true);
$event_plan_id = wp_insert_post(array(
	'post_type' => 'vms_event_plan',
	'post_status' => 'publish',
	'post_title' => $marker . ' One Event — Café ひらがな',
), true);
if (is_wp_error($tec_event_id) || is_wp_error($event_plan_id) || absint($tec_event_id) <= 0 || absint($event_plan_id) <= 0 || $event_artwork_id <= 0) {
	throw new RuntimeException('Could not create the disposable event-artwork fixture.');
}
update_post_meta((int) $event_plan_id, function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status', 'published');
update_post_meta((int) $event_plan_id, '_vms_tec_event_id', (int) $tec_event_id);
update_post_meta((int) $tec_event_id, '_thumbnail_id', $event_artwork_id);
delete_post_meta((int) $event_plan_id, '_vms_event_date');
$wpdb->insert($wpdb->postmeta, array('post_id' => (int) $event_plan_id, 'meta_key' => '_vms_event_date', 'meta_value' => wp_date('Y-m-d', time() + (21 * DAY_IN_SECONDS))));
wp_cache_delete((int) $event_plan_id, 'post_meta');
$wpdb->insert(bvmgr_admission_table_pass_sources(), array(
	'source_name' => $marker . ' Existing Eligible Batches',
	'status' => 'active',
	'created_by' => $user_id,
	'created_at' => $now,
));
$existing_source_id = (int) $wpdb->insert_id;
$extra_source_ids[] = $existing_source_id;
$wpdb->insert(bvmgr_admission_table_pass_sources(), array(
	'source_name' => $marker . ' Zero Reusable Businesses',
	'status' => 'active',
	'created_by' => $user_id,
	'created_at' => $now,
));
$empty_source_id = (int) $wpdb->insert_id;
$extra_source_ids[] = $empty_source_id;
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
$extra_batch_ids[] = $insert_batch($source_id, $marker . ' Invalid 125%', 'active', 'percent', 125.0);
$existing_free_batch_id = $insert_batch($existing_source_id, $marker . ' Existing Complimentary', 'active', 'free', 0.0);
$existing_paid_batch_id = $insert_batch($existing_source_id, $marker . ' Existing Admission Offer', 'active', 'percent', 50.0);
$empty_free_batch_id = $insert_batch($empty_source_id, $marker . ' Zero-member Complimentary', 'active', 'free', 0.0);
$empty_paid_batch_id = $insert_batch($empty_source_id, $marker . ' Zero-member Admission Offer', 'active', 'percent', 50.0);
$extra_batch_ids[] = $existing_free_batch_id;
$extra_batch_ids[] = $existing_paid_batch_id;
$extra_batch_ids[] = $empty_free_batch_id;
$extra_batch_ids[] = $empty_paid_batch_id;
$business_ids = array();
for ($index = 1; $index <= 35; $index++) {
	$business_id = backstage_outreach_insert_business(array(
		'business_name' => $index === 1
			? 'Café — Browser Fixture Business 01 With An Extraordinarily Long Reception Desk Display Name ひらがな é'
			: sprintf('Browser Fixture Business %02d With A Readable Long Name', $index),
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
$paused_membership_business_id = backstage_outreach_insert_business(array('business_name' => $marker . ' Paused Membership'), $user_id);
$inactive_business_id = backstage_outreach_insert_business(array('business_name' => $marker . ' Inactive Business'), $user_id);
if ($paused_membership_business_id <= 0
	|| $inactive_business_id <= 0
	|| !backstage_outreach_business_upsert_membership($empty_source_id, $paused_membership_business_id, 'manual', null, 0, array('fixture' => true), $user_id)
	|| !backstage_outreach_business_upsert_membership($empty_source_id, $inactive_business_id, 'manual', null, 0, array('fixture' => true), $user_id)) {
	throw new RuntimeException('Could not create inactive zero-membership controls.');
}
$wpdb->update(backstage_outreach_business_table('source_businesses'), array('status' => 'paused'), array('source_id' => $empty_source_id, 'business_id' => $paused_membership_business_id));
$wpdb->update(backstage_outreach_business_table('businesses'), array('status' => 'inactive'), array('id' => $inactive_business_id));
$business_ids[] = $paused_membership_business_id;
$business_ids[] = $inactive_business_id;

$fixture = array(
	'user_id' => $user_id,
	'source_id' => $source_id,
	'no_eligible_source_id' => $source_id,
	'existing_source_id' => $existing_source_id,
	'empty_source_id' => $empty_source_id,
	'existing_free_batch_id' => $existing_free_batch_id,
	'existing_paid_batch_id' => $existing_paid_batch_id,
	'empty_free_batch_id' => $empty_free_batch_id,
	'empty_paid_batch_id' => $empty_paid_batch_id,
	'unrelated_batch_id' => $existing_free_batch_id,
	'extra_source_ids' => $extra_source_ids,
	'extra_batch_ids' => $extra_batch_ids,
	'business_ids' => $business_ids,
	'artwork_id' => $artwork_id,
	'portrait_artwork_id' => $portrait_artwork_id,
	'landscape_artwork_id' => $landscape_artwork_id,
	'event_artwork_id' => $event_artwork_id,
	'artwork_ids' => $artwork_ids,
	'event_plan_id' => (int) $event_plan_id,
	'tec_event_id' => (int) $tec_event_id,
	'admin_url' => vms_pass_outreach_admin_page_url(),
	'source_name' => $marker,
	'batch_name' => $marker . ' Created In Outreach',
);
update_option($option, $fixture, false);
echo wp_json_encode($fixture) . "\n";
