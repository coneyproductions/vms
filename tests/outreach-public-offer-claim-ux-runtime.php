<?php
/** Disposable WordPress runtime acceptance for public claim-form UX and server validation. */

defined('ABSPATH') || exit;

function backstage_outreach_claim_ux_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

if (!defined('BACKSTAGE_OUTREACH_VERSION') || BACKSTAGE_OUTREACH_VERSION !== '1.2.23') {
	throw new RuntimeException('Backstage Outreach 1.2.23 must be active.');
}

global $wpdb;
$marker = 'Claim UX ' . wp_generate_password(10, false, false);
$now = backstage_outreach_business_now();
$user_id = get_current_user_id() ?: 1;
$post_ids = array();
$source_id = 0;
$batch_ids = array();
$campaign_ids = array();
$distribution_ids = array();
$business_id = 0;
$claim_id = 0;

$tables = array(
	'claims' => bvmgr_admission_table_pass_claims(),
	'entries' => bvmgr_admission_table_entries(),
	'tokens' => bvmgr_admission_table_pass_tokens(),
	'audit' => bvmgr_admission_table_audit(),
	'sources' => bvmgr_admission_table_pass_sources(),
	'batches' => bvmgr_admission_table_pass_batches(),
	'campaigns' => vms_admission_table_pass_outreach_campaigns(),
	'businesses' => backstage_outreach_business_table('businesses'),
	'memberships' => backstage_outreach_business_table('source_businesses'),
	'distributions' => backstage_outreach_business_table('campaign_businesses'),
	'distribution_claims' => backstage_outreach_business_table('distribution_claims'),
);

$fingerprints = static function () use ($wpdb, $tables): array {
	$out = array();
	foreach ($tables as $key => $table) {
		$rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A);
		$out[$key] = hash('sha256', wp_json_encode(is_array($rows) ? $rows : array()));
	}
	return $out;
};

$before = $fingerprints();
$failure = null;
try {
	$event_dates = array(
		wp_date('Y-m-d', time() + (14 * DAY_IN_SECONDS), wp_timezone()),
		wp_date('Y-m-d', time() + (21 * DAY_IN_SECONDS), wp_timezone()),
	);
	foreach ($event_dates as $index => $event_date) {
		$event_id = (int) wp_insert_post(array(
			'post_type' => 'vms_event_plan',
			'post_status' => 'publish',
			'post_title' => $marker . ' Event ' . ($index + 1),
		));
		backstage_outreach_claim_ux_assert($event_id > 0, 'Could not create a disposable Event Plan.');
		$post_ids[] = $event_id;
		$status_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status';
		update_post_meta($event_id, '_vms_event_date', $event_date);
		update_post_meta($event_id, $status_key, 'published');
		backstage_outreach_claim_ux_assert(get_post_meta($event_id, '_vms_event_date', true) === $event_date, 'Could not store the disposable Event Plan date.');
	}

	backstage_outreach_claim_ux_assert($wpdb->insert($tables['sources'], array(
		'source_name' => $marker,
		'status' => 'active',
		'created_by' => $user_id,
		'created_at' => $now,
	)) !== false, 'Could not create the disposable Source.');
	$source_id = (int) $wpdb->insert_id;

	$insert_batch = static function (string $name, string $validity, int $single_event_id, int $max) use ($wpdb, $tables, $source_id, $user_id, $now, &$batch_ids): int {
		$inserted = $wpdb->insert($tables['batches'], array(
			'source_id' => $source_id,
			'batch_name' => $name,
			'quantity' => 0,
			'validity_type' => $validity,
			'single_event_plan_id' => $single_event_id ?: null,
			'venue_ids_json' => '[]',
			'value_type' => 'free',
			'value_amount' => 100,
			'applies_to' => 'entry_only',
			'status' => 'active',
			'checkin_open_mode' => 'same_day',
			'max_per_phone' => 0,
			'generated_count' => 0,
			'created_by' => $user_id,
			'created_at' => $now,
			'admissions_per_link' => $max,
			'total_admission_cap' => 20,
			'max_per_email' => 0,
		));
		backstage_outreach_claim_ux_assert($inserted !== false, 'Could not create a disposable Guest Pass batch.');
		$batch_id = (int) $wpdb->insert_id;
		$batch_ids[] = $batch_id;
		return $batch_id;
	};

	$insert_campaign = static function (string $name, int $batch_id, string $validity, int $single_event_id, int $max) use ($wpdb, $tables, $source_id, $user_id, $now, &$campaign_ids): int {
		$inserted = $wpdb->insert($tables['campaigns'], array(
			'campaign_name' => $name,
			'related_source_id' => $source_id,
			'related_batch_id' => $batch_id,
			'validity_type' => $validity,
			'single_event_plan_id' => $single_event_id ?: null,
			'admissions_per_recipient' => $max,
			'total_admission_cap' => 20,
			'status' => 'active',
			'eligibility_mode' => 'anyone_with_invite',
			'created_by' => $user_id,
			'created_at' => $now,
			'campaign_purpose' => 'guest_pass_invitation',
		));
		backstage_outreach_claim_ux_assert($inserted !== false, 'Could not create a disposable Outreach campaign.');
		$campaign_id = (int) $wpdb->insert_id;
		$campaign_ids[] = $campaign_id;
		return $campaign_id;
	};

	$single_batch_id = $insert_batch($marker . ' Single', 'single_event', $post_ids[0], 2);
	$single_campaign_id = $insert_campaign($marker . ' Single', $single_batch_id, 'single_event', $post_ids[0], 2);
	$multi_batch_id = $insert_batch($marker . ' Multi', 'any_event', 0, 2);
	$multi_campaign_id = $insert_campaign($marker . ' Multi', $multi_batch_id, 'any_event', 0, 2);

	$business_id = backstage_outreach_insert_business(array('business_name' => $marker . ' Facebook', 'contact_name' => 'Fixture'), $user_id);
	backstage_outreach_claim_ux_assert($business_id > 0, 'Could not create the disposable referring business.');
	backstage_outreach_claim_ux_assert(backstage_outreach_business_upsert_membership($source_id, $business_id, 'manual', null, 0, array(), $user_id), 'Could not create the disposable business membership.');

	foreach (array($single_campaign_id, $multi_campaign_id) as $campaign_id) {
		$distribution_id = backstage_outreach_create_distribution($campaign_id, $source_id, $business_id, 'complimentary', 20, 0, '', $post_ids, array(), $user_id);
		backstage_outreach_claim_ux_assert($distribution_id > 0, 'Could not create a disposable complimentary distribution.');
		$distribution_ids[$campaign_id] = $distribution_id;
	}

	$single_row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', $tables['distributions'], $distribution_ids[$single_campaign_id]), ARRAY_A);
	$single_context = backstage_outreach_distribution_context(backstage_outreach_distribution_token((array) $single_row));
	backstage_outreach_claim_ux_assert(is_array($single_context), 'Could not resolve the disposable single-event distribution.');
	$single_events = bvmgr_pass_claims_eligible_events_for_batch((array) $single_context['batch']);
	$single_campaign = vms_pass_outreach_get_campaign_by_id($single_campaign_id);
	$single_events = vms_pass_outreach_filter_events_for_campaign((array) $single_campaign, $single_events);
	backstage_outreach_claim_ux_assert(count($single_events) === 1, 'Single-event campaign did not resolve to exactly one eligible event.');
	$single_form = backstage_outreach_partner_form_html($single_context, $single_events, array('first_name' => '', 'last_name' => '', 'phone' => '', 'email' => '', 'event_plan_id' => 0, 'party_size' => 2, 'opt_in' => 0), '', 'fixture-key');
	backstage_outreach_claim_ux_assert(substr_count($single_form, 'name="event_plan_id"') === 1 && strpos($single_form, '<select name="event_plan_id"') === false, 'Single-event form still exposes an event selector.');
	backstage_outreach_claim_ux_assert(strpos($single_form, $marker . ' Event 1') !== false && strpos($single_form, bvmgr_pass_claims_format_public_date($event_dates[0])) !== false, 'Single-event form does not show the event title and date.');
	backstage_outreach_claim_ux_assert(substr_count($single_form, '<option value="1"') === 1 && substr_count($single_form, '<option value="2"') === 1 && preg_match('/<option value="2" selected=(?:"selected"|\'selected\')>/', $single_form) === 1, 'Up-to-two offer does not expose only quantities 1 and 2 with 2 selected.');
	backstage_outreach_claim_ux_assert(strpos($single_form, 'Claim 2 Guest Passes') !== false && strpos($single_form, 'Referred by:') === false && strpos($single_form, 'Shared by ' . $marker . ' Facebook') !== false, 'Complimentary heading, button, or subtle referral presentation is incorrect.');
	backstage_outreach_claim_ux_assert(strpos($single_form, 'contact information is never copied') === false && preg_match('/name="opt_in"[^>]*checked/', $single_form) !== 1, 'Removed explanation or default opt-in state regressed.');

	$one_context = $single_context;
	$one_context['batch']['admissions_per_link'] = 1;
	$one_context['admissions_per_recipient'] = 1;
	$one_form = backstage_outreach_partner_form_html($one_context, $single_events, array('first_name' => '', 'last_name' => '', 'phone' => '', 'email' => '', 'event_plan_id' => 0, 'party_size' => 1, 'opt_in' => 0), '', 'fixture-key');
	backstage_outreach_claim_ux_assert(strpos($one_form, 'type="hidden" name="party_size" value="1"') !== false && strpos($one_form, 'data-backstage-claim-quantity') === false, 'One-valid-quantity offer is not displayed read-only.');

	$multi_row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', $tables['distributions'], $distribution_ids[$multi_campaign_id]), ARRAY_A);
	$multi_context = backstage_outreach_distribution_context(backstage_outreach_distribution_token((array) $multi_row));
	backstage_outreach_claim_ux_assert(is_array($multi_context), 'Could not resolve the disposable multi-event distribution.');
	$multi_events = array_map('bvmgr_pass_claims_get_event_plan_brief', $post_ids);
	$multi_form = backstage_outreach_partner_form_html($multi_context, $multi_events, array('first_name' => '', 'last_name' => '', 'phone' => '', 'email' => '', 'event_plan_id' => 0, 'party_size' => 2, 'opt_in' => 0), '', 'fixture-key');
	backstage_outreach_claim_ux_assert(strpos($multi_form, '<select name="event_plan_id" required>') !== false && substr_count($multi_form, '<option value="' . $post_ids[0] . '"') === 1 && substr_count($multi_form, '<option value="' . $post_ids[1] . '"') === 1, 'Multi-event form does not retain its bounded event selector.');

	$invalid_quantity = backstage_outreach_partner_claim($single_context, (array) $single_events[0], array('first_name' => 'Synthetic', 'last_name' => 'Quantity', 'phone' => '3125550101', 'email' => '', 'party_size' => 3, 'opt_in' => 0), 'invalid-quantity');
	backstage_outreach_claim_ux_assert(is_wp_error($invalid_quantity) && $invalid_quantity->get_error_code() === 'invalid_party_size', 'Out-of-range quantity was not rejected inside the transactional server boundary.');

	$invalid_event = backstage_outreach_partner_claim($multi_context, array('id' => 99999999), array('first_name' => 'Synthetic', 'last_name' => 'Event', 'phone' => '3125550102', 'email' => '', 'party_size' => 2, 'opt_in' => 0), 'invalid-event');
	backstage_outreach_claim_ux_assert(is_wp_error($invalid_event) && $invalid_event->get_error_code() === 'invalid_event', 'Ineligible event was not rejected inside the transactional server boundary.');

	$valid = backstage_outreach_partner_claim($single_context, array('id' => 99999999), array('first_name' => 'Synthetic', 'last_name' => 'Attribution', 'phone' => '3125550103', 'email' => '', 'party_size' => 2, 'opt_in' => 0), 'valid-single-event');
	backstage_outreach_claim_ux_assert(is_array($valid) && (int) $valid['event_plan_id'] === $post_ids[0] && (int) $valid['party_size'] === 2, 'Single eligible event was not auto-associated with the synthetic claim.');
	$claim_id = (int) $valid['claim_id'];
	$claim = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', $tables['claims'], $claim_id), ARRAY_A);
	$mapping = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE distribution_id=%d AND pass_claim_id=%d', $tables['distribution_claims'], $distribution_ids[$single_campaign_id], $claim_id), ARRAY_A);
	$entry = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE pass_claim_id=%d ORDER BY id ASC LIMIT 1', $tables['entries'], $claim_id), ARRAY_A);
	$meta = is_array($entry) ? json_decode((string) ($entry['claim_meta'] ?? ''), true) : null;
	backstage_outreach_claim_ux_assert(is_array($claim) && (int) $claim['outreach_campaign_id'] === $single_campaign_id && (int) $claim['opt_in'] === 0, 'Claim campaign attribution or optional-consent state was not preserved.');
	backstage_outreach_claim_ux_assert(is_array($mapping) && (int) $mapping['business_id'] === $business_id && (int) $mapping['party_size'] === 2 && (string) $mapping['status'] === 'fulfilled', 'Distribution attribution was not preserved after claim.');
	backstage_outreach_claim_ux_assert(is_array($meta) && (int) ($meta['outreach_distribution_id'] ?? 0) === $distribution_ids[$single_campaign_id] && (int) ($meta['referring_business_id'] ?? 0) === $business_id, 'Admission metadata lost the referring distribution or business.');
} catch (Throwable $error) {
	$failure = $error;
} finally {
	if ($claim_id > 0) {
		$wpdb->delete($tables['audit'], array('action' => 'pass_claim', 'event_plan_id' => $post_ids[0] ?? 0));
		$wpdb->delete($tables['entries'], array('pass_claim_id' => $claim_id));
		$wpdb->delete($tables['tokens'], array('claim_id' => $claim_id));
		$wpdb->delete($tables['claims'], array('id' => $claim_id));
	}
	foreach ($distribution_ids as $distribution_id) {
		$wpdb->delete($tables['distribution_claims'], array('distribution_id' => $distribution_id));
		$wpdb->delete($tables['distributions'], array('id' => $distribution_id));
	}
	foreach ($campaign_ids as $campaign_id) {
		$wpdb->delete($tables['campaigns'], array('id' => $campaign_id));
	}
	if ($business_id > 0) {
		$wpdb->delete($tables['memberships'], array('business_id' => $business_id));
		$wpdb->delete($tables['businesses'], array('id' => $business_id));
	}
	foreach ($batch_ids as $batch_id) {
		$wpdb->delete($tables['batches'], array('id' => $batch_id));
	}
	if ($source_id > 0) {
		$wpdb->delete($tables['sources'], array('id' => $source_id));
	}
	foreach (array_reverse($post_ids) as $post_id) {
		wp_delete_post($post_id, true);
	}
}

if ($failure instanceof Throwable) {
	throw $failure;
}

$after = $fingerprints();
backstage_outreach_claim_ux_assert($after === $before, 'Existing campaign, claim, distribution, admission, token, or audit records changed after fixture cleanup.');

echo "Outreach public claim UX runtime PASS\n";
