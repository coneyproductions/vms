<?php
/** Disposable runtime acceptance for the unified Party workflow. */

defined('ABSPATH') || exit;

function outreach_party_unified_assert(bool $condition, string $message): void
{
	if (!$condition) { throw new RuntimeException($message); }
}

if (!class_exists('WooCommerce') || !function_exists('backstage_outreach_party_adoption_preview')) {
	throw new RuntimeException('Outreach 1.2.20 and WooCommerce must be active.');
}

global $wpdb;
$marker = 'Unified Party ' . wp_generate_password(8, false, false);
$now = backstage_outreach_party_now();
$user_id = get_current_user_id() ?: 1;
$source_id = 0;
$batch_ids = array();
$campaign_ids = array();
$recipient_ids = array();
$edge_recipient_ids = array();
$party_ids = array();
$distribution_ids = array();
$coupon_ids = array();
$post_ids = array();
$suppression_id = 0;
$mail_attempts = 0;
$captured_mail = array();
$mail_block = static function ($return, array $atts) use (&$mail_attempts, &$captured_mail): bool { $mail_attempts++; $captured_mail[] = $atts; return true; };
add_filter('pre_wp_mail', $mail_block, PHP_INT_MAX, 2);
$unverified_transport = static fn(array $state): array => array('ready' => false, 'signs_rfc8058_headers' => false, 'method' => 'synthetic_unverified_transport', 'message' => 'Synthetic downstream signing is unverified.');
add_filter('backstage_outreach_mail_transport_readiness', $unverified_transport, PHP_INT_MAX, 1);
$synthetic_postal_address = static fn(string $address): string => 'Synthetic Venue, 100 Test Way, Example, TX 75001, US';
add_filter('backstage_outreach_postal_address', $synthetic_postal_address, PHP_INT_MAX, 1);

$tables = array(
	'parties' => backstage_outreach_party_table('parties'), 'methods' => backstage_outreach_party_table('contact_methods'),
	'sources' => backstage_outreach_party_table('sources'), 'roles' => backstage_outreach_party_table('campaign_roles'),
	'links' => backstage_outreach_party_table('legacy_links'), 'audit' => backstage_outreach_party_table('identity_audit'),
	'distributions' => backstage_outreach_party_table('referral_distributions'), 'redemptions' => backstage_outreach_party_table('referral_redemptions'),
	'activities' => backstage_outreach_party_table('contact_activities'),
);
$max_ids = array();
foreach ($tables as $key => $table) { $max_ids[$key] = (int) $wpdb->get_var($wpdb->prepare('SELECT COALESCE(MAX(id),0) FROM %i', $table)); }
$count = static fn(string $table): int => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $table));
$baseline = array(
	'businesses' => $count(backstage_outreach_business_table('businesses')),
	'memberships' => $count(backstage_outreach_business_table('source_businesses')),
	'tokens' => $count(bvmgr_admission_table_pass_tokens()),
	'campaign31' => $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', vms_admission_table_pass_outreach_campaigns(), 31), ARRAY_A),
	'batch84' => $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', bvmgr_admission_table_pass_batches(), 84), ARRAY_A),
);

try {
	update_option(backstage_outreach_party_schema_option_key(), '1.0.0', false);
	backstage_outreach_party_schema_upgrade();
	outreach_party_unified_assert(get_option(backstage_outreach_party_schema_option_key()) === '1.2.0', 'Production-equivalent Party 1.0 upgrade failed.');
	update_option(backstage_outreach_party_schema_option_key(), '1.1.0', false);
	backstage_outreach_party_schema_upgrade();
	outreach_party_unified_assert(get_option(backstage_outreach_party_schema_option_key()) === '1.2.0', 'Staging Party 1.1 upgrade failed.');
	outreach_party_unified_assert((string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tables['activities'])) === $tables['activities'], 'Contact activity ledger is missing.');

	$event_start = '2026-10-17 19:00:00';
	$event_end = '2026-10-17 22:00:00';
	$tec_event_id = tribe_create_event(array('post_status' => 'publish', 'post_title' => $marker . ' event', 'EventStartDate' => $event_start, 'EventEndDate' => $event_end));
	outreach_party_unified_assert(!is_wp_error($tec_event_id) && $tec_event_id > 0, 'Could not create synthetic October event.');
	$post_ids[] = (int) $tec_event_id;
	$plan_id = wp_insert_post(array('post_type' => 'vms_event_plan', 'post_status' => 'publish', 'post_title' => $marker . ' plan'), true);
	outreach_party_unified_assert(!is_wp_error($plan_id) && $plan_id > 0, 'Could not create synthetic Event Plan.');
	$post_ids[] = (int) $plan_id;
	update_post_meta((int) $plan_id, function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status', 'published');
	update_post_meta((int) $plan_id, '_vms_event_date', '2026-10-17');
	update_post_meta((int) $plan_id, '_vms_tec_event_id', (int) $tec_event_id);
	update_post_meta((int) $plan_id, '_vms_start_time', '19:00');
	$provider = tribe('tickets-plus.commerce.woo');
	$ticket_id = (int) $provider->ticket_add((int) $tec_event_id, array('ticket_name' => $marker . ' General Admission', 'ticket_price' => '20', 'ticket_show_description' => 'no', 'tribe-ticket' => array('capacity' => 1000, 'mode' => 'own')));
	outreach_party_unified_assert($ticket_id > 0, 'Could not create synthetic admission product.');
	$post_ids[] = $ticket_id;
	$ticket = wc_get_product($ticket_id);
	$ticket->set_status('publish'); $ticket->set_virtual(true); $ticket->set_regular_price('20'); $ticket->set_price('20'); $ticket->save();
	update_post_meta($ticket_id, '_vms_event_plan_id', (int) $plan_id);
	update_post_meta($ticket_id, '_vms_product_role', 'ga_ticket');
	update_post_meta($ticket_id, '_tribe_wooticket_for_event', (int) $tec_event_id);
	$wpdb->delete($wpdb->postmeta, array('post_id' => (int) $plan_id, 'meta_key' => '_vms_event_date'), array('%d', '%s'));
	$date_update_result = $wpdb->insert($wpdb->postmeta, array('post_id' => (int) $plan_id, 'meta_key' => '_vms_event_date', 'meta_value' => '2026-10-17'), array('%d', '%s', '%s'));
	clean_post_cache((int) $plan_id);
	clean_post_cache($ticket_id);

	outreach_party_unified_assert($wpdb->insert(bvmgr_admission_table_pass_sources(), array('source_name' => $marker . ' Source', 'status' => 'active', 'created_by' => $user_id, 'created_at' => $now)) !== false, 'Could not create synthetic Source.');
	$source_id = (int) $wpdb->insert_id;
	outreach_party_unified_assert($wpdb->insert(bvmgr_admission_table_pass_batches(), array(
		'source_id' => $source_id, 'batch_name' => $marker . ' historical complimentary', 'quantity' => 0, 'admissions_per_link' => 2, 'total_admission_cap' => 206,
		'validity_type' => 'date_range', 'start_date' => '2026-10-08', 'end_date' => '2026-10-31', 'venue_ids_json' => '[]', 'value_type' => 'free', 'value_amount' => 100,
		'applies_to' => 'entry_only', 'status' => 'active', 'checkin_open_mode' => 'same_day', 'max_per_phone' => 0, 'max_per_email' => 0, 'generated_count' => 0,
		'created_by' => $user_id, 'created_at' => $now,
	)) !== false, 'Could not create synthetic historical batch.');
	$historical_batch_id = (int) $wpdb->insert_id;
	$batch_ids[] = $historical_batch_id;
	for ($campaign_index = 1; $campaign_index <= 2; $campaign_index++) {
		outreach_party_unified_assert($wpdb->insert(vms_admission_table_pass_outreach_campaigns(), array(
			'campaign_name' => $marker . ' historical ' . $campaign_index, 'campaign_purpose' => 'guest_pass_invitation', 'related_source_id' => $source_id, 'related_batch_id' => $historical_batch_id,
			'validity_type' => 'date_range', 'start_date' => '2026-10-08', 'end_date' => '2026-10-31', 'admissions_per_recipient' => 2, 'total_admission_cap' => 206,
			'status' => 'draft', 'eligibility_mode' => 'anyone_with_invite', 'created_by' => $user_id, 'created_at' => $now,
		)) !== false, 'Could not create synthetic historical campaign.');
		$campaign_ids[] = (int) $wpdb->insert_id;
	}

	for ($person = 1; $person <= 103; $person++) {
		$name = $person <= 2 ? 'Alex Shared' : sprintf('Synthetic Realtor %03d', $person);
		$company = sprintf('Synthetic Brokerage %02d', (($person - 1) % 36) + 1);
		$phone = in_array($person, array(3, 4), true) ? '(903) 555-0100' : sprintf('(903) 55%1$d-%2$04d', $person % 10, $person);
		foreach ($campaign_ids as $campaign_index => $campaign_id) {
			$email = sprintf('realtor-%03d@example.test', $person);
			outreach_party_unified_assert($wpdb->insert(vms_pass_outreach_recipient_table(), array(
				'campaign_id' => $campaign_id, 'contact_id' => 0, 'full_name' => $name, 'first_name' => strtok($name, ' '), 'last_name' => trim(substr($name, strlen(strtok($name, ' ')))),
				'email' => $email !== '' ? $email : null, 'email_norm' => $email !== '' ? $email : null, 'phone' => $phone, 'phone_norm' => preg_replace('/\D+/', '', $phone), 'company' => $company,
				'invite_token' => strtolower(wp_generate_password(32, false, false)), 'send_status' => 'not_sent', 'status' => 'ready', 'created_by' => $user_id, 'created_at' => $now,
			)) !== false, 'Could not create synthetic historical recipient.');
			$recipient_ids[$person][$campaign_index] = (int) $wpdb->insert_id;
		}
	}
	outreach_party_unified_assert(count($recipient_ids, COUNT_RECURSIVE) - count($recipient_ids) === 206, 'Synthetic snapshot count is not 206.');
	$initial_recipient_ids = array_merge(...array_values($recipient_ids));
	outreach_party_unified_assert((int) $wpdb->get_var('SELECT COUNT(*) FROM `' . vms_pass_outreach_recipient_table() . '` WHERE id IN (' . implode(',', array_map('absint', $initial_recipient_ids)) . ') AND contact_id<>0') === 0, 'The real-world compatibility fixture contains a nonzero historical Contact ID.');

	$preexisting = backstage_outreach_party_save(array('party_type' => 'person', 'display_name' => 'Alex Shared', 'given_name' => 'Alex', 'family_name' => 'Shared', 'provenance_type' => 'synthetic_runtime', 'provenance_key' => $marker . '-preexisting'), $user_id);
	outreach_party_unified_assert(is_array($preexisting), 'Could not create reviewed preexisting Person.');
	$party_ids[] = (int) $preexisting['id'];
	$first_snapshot = backstage_outreach_party_get_legacy_snapshot('campaign_recipient', $recipient_ids[1][0]);
	$prelink = backstage_outreach_party_confirm_legacy_link((int) $preexisting['id'], 'campaign_recipient', $recipient_ids[1][0], (string) $first_snapshot['snapshot_hash'], array('Synthetic reviewed match'), $user_id);
	outreach_party_unified_assert(is_array($prelink), 'Could not seed reviewed Party reuse.');

	$preview = backstage_outreach_party_adoption_preview($source_id, $campaign_ids);
	outreach_party_unified_assert(is_array($preview), is_wp_error($preview) ? $preview->get_error_message() : 'Adoption preview failed.');
	outreach_party_unified_assert((int) $preview['summary']['recipient_count'] === 206 && (int) $preview['summary']['party_count'] === 103, 'Preview did not reconcile 206 zero-contact-ID snapshots to 103 compound identities.');
	outreach_party_unified_assert((int) $preview['summary']['compatibility_match_count'] === 103 && (int) $preview['summary']['missing_email_count'] === 0 && (int) $preview['summary']['ambiguous_count'] >= 4, 'Preview did not expose all compound matches and ambiguous contact evidence.');
	outreach_party_unified_assert(count(array_filter($preview['groups'], static fn(array $group): bool => str_starts_with((string) $group['group_key'], 'compat-') && count((array) $group['identity_evidence']) > 0)) === 103, 'Compound proposals did not retain explicit evidence for every match.');
	$choices = array_map(static fn(array $group): array => array('group_key' => $group['group_key'], 'selected' => true, 'identity_reviewed' => true, 'resolution' => $group['existing_party_id'] ? 'reuse' : 'create', 'party_id' => absint($group['existing_party_id'])), $preview['groups']);
	$adopted = backstage_outreach_party_adoption_commit($preview, $choices, $user_id);
	outreach_party_unified_assert(!$adopted['errors'] && $adopted['created'] === 102 && $adopted['reused'] === 1 && $adopted['linked'] === 206, 'Reviewed adoption did not create/reuse/link the expected identities: ' . wp_json_encode($adopted));
	$party_ids = array_values(array_unique(array_merge($party_ids, array_map('absint', $wpdb->get_col($wpdb->prepare('SELECT id FROM %i WHERE id>%d', $tables['parties'], $max_ids['parties']))))));
	outreach_party_unified_assert(count($party_ids) === 103, 'Exactly 103 canonical Parties were not produced.');
	outreach_party_unified_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE id>%d', $tables['links'], $max_ids['links'])) === 206, 'Both immutable campaign associations were not retained.');
	$changed_party_id = (int) $wpdb->get_var($wpdb->prepare('SELECT party_id FROM %i WHERE legacy_type=%s AND legacy_id=%d', $tables['links'], 'campaign_recipient', $recipient_ids[2][0]));
	outreach_party_unified_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE party_id=%d AND method_type=%s AND status=%s', $tables['methods'], $changed_party_id, 'email', 'active')) === 1, 'Matched historical email was not retained idempotently.');
	$replayed = backstage_outreach_party_adoption_commit($preview, $choices, $user_id);
	outreach_party_unified_assert(!$replayed['errors'] && $replayed['created'] === 0 && $replayed['reused'] === 103, 'Adoption replay was not idempotent and resumable.');

	$insert_edge_recipient = static function (int $campaign_id, string $name, string $email, string $phone, string $organization, int $contact_id = 0) use ($wpdb, $now, $user_id, &$edge_recipient_ids): int {
		$parts = preg_split('/\s+/', trim($name), 2);
		outreach_party_unified_assert($wpdb->insert(vms_pass_outreach_recipient_table(), array(
			'campaign_id' => $campaign_id, 'contact_id' => $contact_id, 'full_name' => $name, 'first_name' => (string) ($parts[0] ?? ''), 'last_name' => (string) ($parts[1] ?? ''),
			'email' => $email !== '' ? $email : null, 'email_norm' => $email !== '' ? vms_outreach_normalize_email($email) : null,
			'phone' => $phone !== '' ? $phone : null, 'phone_norm' => $phone !== '' ? vms_outreach_normalize_phone($phone) : null, 'company' => $organization,
			'invite_token' => strtolower(wp_generate_password(32, false, false)), 'send_status' => 'not_sent', 'status' => 'ready', 'created_by' => $user_id, 'created_at' => $now,
		)) !== false, 'Could not create a zero-contact-ID edge recipient.');
		$edge_recipient_ids[] = (int) $wpdb->insert_id;
		return (int) $wpdb->insert_id;
	};
	$shared_email_ids = array(
		$insert_edge_recipient($campaign_ids[0], 'Casey Alpha', 'shared-conflict@example.test', '9035550201', 'Edge Brokerage'),
		$insert_edge_recipient($campaign_ids[1], 'Casey Beta', 'shared-conflict@example.test', '9035550202', 'Edge Brokerage'),
	);
	$organization_conflict_ids = array(
		$insert_edge_recipient($campaign_ids[0], 'Jordan Organization', 'organization-conflict@example.test', '9035550203', 'Brokerage Alpha'),
		$insert_edge_recipient($campaign_ids[1], 'Jordan Organization', 'organization-conflict@example.test', '9035550204', 'Brokerage Beta'),
	);
	$duplicate_email_ids = array(
		$insert_edge_recipient($campaign_ids[0], 'Taylor Duplicate', 'same-campaign-duplicate@example.test', '9035550205', 'Duplicate Brokerage'),
		$insert_edge_recipient($campaign_ids[0], 'Taylor Duplicate', 'same-campaign-duplicate@example.test', '9035550205', 'Duplicate Brokerage'),
		$insert_edge_recipient($campaign_ids[1], 'Taylor Duplicate', 'same-campaign-duplicate@example.test', '9035550205', 'Duplicate Brokerage'),
	);
	$phone_only_ids = array(
		$insert_edge_recipient($campaign_ids[0], 'Morgan Phone', '', '9035550206', 'Phone Brokerage'),
		$insert_edge_recipient($campaign_ids[1], 'Morgan Phone', '', '9035550206', 'Phone Brokerage'),
	);
	$contact_group_ids = array(
		$insert_edge_recipient($campaign_ids[0], 'Legacy Contact Original', 'legacy-original@example.test', '9035550207', 'Original Brokerage', 990001),
		$insert_edge_recipient($campaign_ids[1], 'Legacy Contact Updated', 'legacy-updated@example.test', '9035550208', 'Updated Brokerage', 990001),
	);
	$mapping_conflict_ids = array(
		$insert_edge_recipient($campaign_ids[0], 'Mapped Conflict', 'mapped-conflict@example.test', '9035550209', 'Mapped Brokerage'),
		$insert_edge_recipient($campaign_ids[1], 'Mapped Conflict', 'mapped-conflict@example.test', '9035550209', 'Mapped Brokerage'),
	);
	$mapped_left = backstage_outreach_party_save(array('party_type' => 'person', 'display_name' => $marker . ' Mapped Left', 'provenance_type' => 'synthetic_runtime', 'provenance_key' => $marker . '-mapped-left'), $user_id);
	$mapped_right = backstage_outreach_party_save(array('party_type' => 'person', 'display_name' => $marker . ' Mapped Right', 'provenance_type' => 'synthetic_runtime', 'provenance_key' => $marker . '-mapped-right'), $user_id);
	outreach_party_unified_assert(is_array($mapped_left) && is_array($mapped_right), 'Could not create conflicting reviewed Party fixtures.');
	foreach (array((int) $mapped_left['id'], (int) $mapped_right['id']) as $index => $mapped_party_id) {
		$snapshot = backstage_outreach_party_get_legacy_snapshot('campaign_recipient', $mapping_conflict_ids[$index]);
		$link = backstage_outreach_party_confirm_legacy_link($mapped_party_id, 'campaign_recipient', $mapping_conflict_ids[$index], (string) $snapshot['snapshot_hash'], array('Synthetic prior review'), $user_id);
		outreach_party_unified_assert(is_array($link), 'Could not create a conflicting reviewed mapping fixture.');
	}
	$edge_preview = backstage_outreach_party_adoption_preview($source_id, $campaign_ids);
	$edge_groups = is_array($edge_preview) ? array_column($edge_preview['groups'], null, 'group_key') : array();
	foreach (array_merge($shared_email_ids, $organization_conflict_ids, $duplicate_email_ids, $phone_only_ids) as $edge_recipient_id) {
		$key = 'recipient-' . $edge_recipient_id;
		outreach_party_unified_assert(isset($edge_groups[$key]) && !empty($edge_groups[$key]['ambiguous_reasons']), 'Unsafe zero-contact-ID edge record was merged or omitted from manual review.');
	}
	outreach_party_unified_assert(isset($edge_groups['contact-990001']) && count($edge_groups['contact-990001']['campaign_ids']) === 2, 'Shared historical Contact ID grouping was not preserved.');
	$mapping_conflict_group = current(array_filter($edge_groups, static fn(array $group): bool => !empty($group['mapping_conflict'])));
	outreach_party_unified_assert(is_array($mapping_conflict_group) && count($mapping_conflict_group['mapped_party_ids']) === 2, 'Conflicting reviewed mappings were not surfaced.');
	$mapping_conflict_commit = backstage_outreach_party_adoption_commit($edge_preview, array(array('group_key' => $mapping_conflict_group['group_key'], 'selected' => true, 'identity_reviewed' => true, 'resolution' => 'reuse', 'party_id' => (int) $mapped_left['id'])), $user_id);
	outreach_party_unified_assert(count($mapping_conflict_commit['errors']) === 1, 'A conflicting reviewed mapping was silently relinked.');
	outreach_party_unified_assert(count($edge_groups) === 114, 'Contact-ID, conflict, duplicate, and phone-only records did not retain the expected safe grouping boundaries.');

	$campaign_review = backstage_outreach_party_partner_campaign_review(array('source_id' => $source_id, 'campaign_name' => $marker . ' 50%-off Admission Offer', 'start_date' => '2026-10-08', 'end_date' => '2026-10-31', 'expires_at' => '2026-10-31T23:59', 'total_admission_cap' => 250, 'status' => 'active'));
	outreach_party_unified_assert(is_array($campaign_review), is_wp_error($campaign_review) ? $campaign_review->get_error_message() : 'Paid campaign review failed.');
	$token_count_before_paid = $count(bvmgr_admission_table_pass_tokens());
	$paid = backstage_outreach_party_partner_campaign_create($campaign_review, $user_id);
	outreach_party_unified_assert(is_array($paid), is_wp_error($paid) ? $paid->get_error_message() : 'Paid campaign creation failed.');
	$campaign_ids[] = (int) $paid['campaign_id']; $batch_ids[] = (int) $paid['batch_id'];
	outreach_party_unified_assert((string) $paid['batch']['value_type'] === 'percent' && (float) $paid['batch']['value_amount'] === 50.0 && (int) $paid['batch']['admissions_per_link'] === 2 && (int) $paid['batch']['total_admission_cap'] === 250, 'Paid batch terms are incorrect.');
	outreach_party_unified_assert((int) $paid['batch']['generated_count'] === 0 && $count(bvmgr_admission_table_pass_tokens()) === $token_count_before_paid, 'Paid setup generated complimentary tokens.');
	outreach_party_unified_assert($count(backstage_outreach_business_table('businesses')) === $baseline['businesses'] && $count(backstage_outreach_business_table('source_businesses')) === $baseline['memberships'], 'Party workflow fabricated or changed reusable businesses.');
	update_post_meta((int) $plan_id, '_vms_event_date', '2026-10-17');
	clean_post_cache((int) $plan_id);
	$eligible_probe = bvmgr_pass_claims_eligible_events_for_batch($paid['batch']);
	$ticket_probe = backstage_outreach_discount_ticket_products_for_event((int) $plan_id);
	$eligible_plan_ids = array_map(static fn(array $event): int => absint($event['id'] ?? 0), $eligible_probe);
	outreach_party_unified_assert(in_array((int) $plan_id, $eligible_plan_ids, true) && in_array($ticket_id, $ticket_probe, true), 'Synthetic date-range eligibility fixture failed (events ' . implode(',', $eligible_plan_ids) . '; products ' . implode(',', $ticket_probe) . '; update ' . var_export($date_update_result, true) . '; today ' . bvmgr_pass_claims_today() . '; date ' . (string) get_post_meta((int) $plan_id, '_vms_event_date', true) . '; range ' . (string) ($paid['batch']['start_date'] ?? '') . '..' . (string) ($paid['batch']['end_date'] ?? '') . '; status key ' . (string) get_post_meta((int) $plan_id, function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status', true) . ').');

	$link_preview = backstage_outreach_party_bulk_link_preview((int) $paid['campaign_id'], $party_ids, 0, 0);
	$ready_count = is_array($link_preview) ? count(array_filter($link_preview['rows'], static fn(array $row): bool => $row['status'] === 'ready')) : 0;
	$sample_block = is_array($link_preview) ? (array) current(array_filter($link_preview['rows'], static fn(array $row): bool => $row['status'] !== 'ready')) : array();
	outreach_party_unified_assert(is_array($link_preview) && count($link_preview['rows']) === 103 && $ready_count === 103, 'Bulk link preview did not approve 103 Source-associated Parties (' . $ready_count . ' ready; ' . (string) ($sample_block['error'] ?? 'unknown') . ').');
	$links = backstage_outreach_party_bulk_link_commit($link_preview, $user_id);
	outreach_party_unified_assert($links['created'] === 103 && !$links['failed'], 'Bulk link creation did not verify 103 links and coupons.');
	$distribution_ids = array_map('absint', $wpdb->get_col($wpdb->prepare('SELECT id FROM %i WHERE campaign_id=%d', $tables['distributions'], (int) $paid['campaign_id'])));
	$coupon_ids = array_map('absint', $wpdb->get_col($wpdb->prepare('SELECT coupon_id FROM %i WHERE campaign_id=%d', $tables['distributions'], (int) $paid['campaign_id'])));
	outreach_party_unified_assert(count($distribution_ids) === 103 && count(array_filter($coupon_ids)) === 103, 'One verified distribution and coupon per Party was not created.');
	$links_replay = backstage_outreach_party_bulk_link_commit($link_preview, $user_id);
	outreach_party_unified_assert($links_replay['created'] === 0 && $links_replay['existing'] === 103 && !$links_replay['failed'], 'Bulk link replay duplicated or failed existing links.');
	$outsider = backstage_outreach_party_save(array('party_type' => 'person', 'display_name' => $marker . ' Outsider'), $user_id);
	outreach_party_unified_assert(is_array($outsider), 'Could not create partial-failure Party.');
	$party_ids[] = (int) $outsider['id'];
	$partial = backstage_outreach_party_bulk_link_preview((int) $paid['campaign_id'], array((int) $preexisting['id'], (int) $outsider['id']));
	$partial_result = backstage_outreach_party_bulk_link_commit($partial, $user_id);
	outreach_party_unified_assert($partial_result['existing'] === 1 && count($partial_result['failed']) === 1, 'Bulk partial failure was not isolated and resumable.');

	$contact_rows = backstage_outreach_party_contact_rows((int) $paid['campaign_id']);
	$sendable = array_values(array_filter($contact_rows, static fn(array $row): bool => $row['email'] !== '' && empty($row['suppressed'])));
	outreach_party_unified_assert(count($contact_rows) === 103 && count($sendable) >= 100, 'Contact dashboard did not load Party offer context.');
	$send_ids = array(absint($sendable[0]['id']), absint($sendable[1]['id']));
	$invite = backstage_outreach_party_invitation_preview((int) $paid['campaign_id'], $send_ids, 'first');
	outreach_party_unified_assert(count($invite['rows']) === 2 && count(array_filter($invite['rows'], static fn(array $row): bool => !empty($row['eligible']))) === 2, 'Selected sendable rows did not remain eligible in preview (' . wp_json_encode($invite['rows']) . ').');
	$handoff = backstage_outreach_party_invitation_handoff($invite, $user_id);
	outreach_party_unified_assert($handoff['handed_off'] === 2 && !$handoff['failed'], 'Reviewed first invitation handoff failed (' . wp_json_encode($handoff) . ').');
	$duplicate = backstage_outreach_party_invitation_preview((int) $paid['campaign_id'], $send_ids, 'first');
	outreach_party_unified_assert(count(array_filter($duplicate['rows'], static fn(array $row): bool => !$row['eligible'])) === 2, 'Duplicate first handoff was not blocked.');
	$resend = backstage_outreach_party_invitation_preview((int) $paid['campaign_id'], array($send_ids[0]), 'resend');
	$resend_result = backstage_outreach_party_invitation_handoff($resend, $user_id);
	outreach_party_unified_assert($resend_result['handed_off'] === 1, 'Deliberate resend failed.');
	outreach_party_unified_assert($mail_attempts === 3, 'Accepted first handoffs and deliberate resend did not produce exactly three blocked-delivery mail calls.');
	outreach_party_unified_assert(count($captured_mail) === 3, 'Party handoff capture count does not match mail attempts.');
	foreach ($captured_mail as $captured_party_mail) {
		outreach_party_unified_assert(str_contains((string) ($captured_party_mail['message'] ?? ''), 'Postal address:') && str_contains((string) ($captured_party_mail['message'] ?? ''), 'Unsubscribe from all Backstage Outreach promotional email: https://'), 'Party handoff is missing its automatic postal/unsubscribe footer.');
		outreach_party_unified_assert(!in_array('List-Unsubscribe-Post: List-Unsubscribe=One-Click', (array) ($captured_party_mail['headers'] ?? array()), true), 'Party handoff advertised RFC 8058 without verified header signing.');
	}

	$safety_row = $sendable[3];
	$safety_distribution_id = absint($safety_row['id']);
	$safety_party_id = absint($safety_row['party_id']);
	$safety_coupon_id = absint($safety_row['coupon_id']);
	$safety_configuration_hash = (string) $safety_row['configuration_hash'];
	$assert_handoff_blocked = static function (string $label) use ($paid, $safety_distribution_id, $user_id, &$mail_attempts): void {
		$attempts_before = $mail_attempts;
		$blocked_preview = backstage_outreach_party_invitation_preview((int) $paid['campaign_id'], array($safety_distribution_id), 'first');
		outreach_party_unified_assert(count($blocked_preview['rows']) === 1 && empty($blocked_preview['rows'][0]['eligible']), $label . ' was not blocked during server preview.');
		$blocked_handoff = backstage_outreach_party_invitation_handoff($blocked_preview, $user_id);
		outreach_party_unified_assert($blocked_handoff['handed_off'] === 0 && count($blocked_handoff['failed']) === 1 && $mail_attempts === $attempts_before, $label . ' reached the mailer or was not rejected during handoff.');
	};
	foreach (array('paused', 'revoked') as $blocked_status) {
		$wpdb->update($tables['distributions'], array('status' => $blocked_status), array('id' => $safety_distribution_id));
		$assert_handoff_blocked(ucfirst($blocked_status) . ' referral distribution');
		$wpdb->update($tables['distributions'], array('status' => 'active'), array('id' => $safety_distribution_id));
	}
	$wpdb->update(vms_admission_table_pass_outreach_campaigns(), array('status' => 'draft'), array('id' => (int) $paid['campaign_id']));
	$assert_handoff_blocked('Draft campaign');
	$wpdb->update(vms_admission_table_pass_outreach_campaigns(), array('status' => 'active'), array('id' => (int) $paid['campaign_id']));
	$wpdb->update($tables['distributions'], array('expires_at' => '2020-01-01 00:00:00'), array('id' => $safety_distribution_id));
	$assert_handoff_blocked('Expired referral distribution');
	$wpdb->update($tables['distributions'], array('expires_at' => null), array('id' => $safety_distribution_id));
	$wpdb->update(backstage_outreach_party_table('sources'), array('status' => 'inactive'), array('party_id' => $safety_party_id, 'source_id' => $source_id));
	$assert_handoff_blocked('Inactive Party Source association');
	$wpdb->update(backstage_outreach_party_table('sources'), array('status' => 'active'), array('party_id' => $safety_party_id, 'source_id' => $source_id));
	$wpdb->update($tables['parties'], array('status' => 'archived'), array('id' => $safety_party_id));
	$assert_handoff_blocked('Inactive Party');
	$wpdb->update($tables['parties'], array('status' => 'active'), array('id' => $safety_party_id));
	$wpdb->update(bvmgr_admission_table_pass_batches(), array('status' => 'paused'), array('id' => (int) $paid['batch_id']));
	$assert_handoff_blocked('Paused paid batch');
	$wpdb->update(bvmgr_admission_table_pass_batches(), array('status' => 'active'), array('id' => (int) $paid['batch_id']));
	$wpdb->update($tables['distributions'], array('configuration_hash' => str_repeat('0', 64)), array('id' => $safety_distribution_id));
	$assert_handoff_blocked('Stale offer configuration');
	$wpdb->update($tables['distributions'], array('configuration_hash' => $safety_configuration_hash), array('id' => $safety_distribution_id));
	$valid_token_hash = (string) $wpdb->get_var($wpdb->prepare('SELECT token_hash FROM %i WHERE id=%d', $tables['distributions'], $safety_distribution_id));
	$wpdb->update($tables['distributions'], array('token_hash' => str_repeat('0', 64)), array('id' => $safety_distribution_id));
	$assert_handoff_blocked('Invalid signed referral');
	$wpdb->update($tables['distributions'], array('token_hash' => $valid_token_hash), array('id' => $safety_distribution_id));
	$safety_coupon = new WC_Coupon($safety_coupon_id);
	$safety_coupon->update_meta_data(BACKSTAGE_OUTREACH_COUPON_OWNER_TYPE_META, 'business');
	$safety_coupon->delete_meta_data(BACKSTAGE_OUTREACH_COUPON_PARTY_DISTRIBUTION_META);
	$safety_coupon->update_meta_data(BACKSTAGE_OUTREACH_COUPON_DISTRIBUTION_META, $safety_distribution_id);
	$safety_coupon->save();
	$assert_handoff_blocked('Invalid managed-coupon ownership');
	$safety_coupon = new WC_Coupon($safety_coupon_id);
	$safety_coupon->update_meta_data(BACKSTAGE_OUTREACH_COUPON_OWNER_TYPE_META, 'party');
	$safety_coupon->update_meta_data(BACKSTAGE_OUTREACH_COUPON_PARTY_DISTRIBUTION_META, $safety_distribution_id);
	$safety_coupon->delete_meta_data(BACKSTAGE_OUTREACH_COUPON_DISTRIBUTION_META);
	$safety_coupon->save();
	$suppressed_row = $sendable[2];
	$suppression = vms_outreach_upsert_suppression(array('email' => $suppressed_row['email'], 'reason' => 'manual_admin', 'scope' => vms_outreach_default_suppression_scope(), 'notes' => $marker), $user_id);
	outreach_party_unified_assert(is_array($suppression), 'Could not create synthetic suppression.');
	$suppression_id = (int) $suppression['id'];
	$suppressed_preview = backstage_outreach_party_invitation_preview((int) $paid['campaign_id'], array((int) $suppressed_row['id']), 'first');
	outreach_party_unified_assert(empty($suppressed_preview['rows'][0]['eligible']) && $suppressed_preview['rows'][0]['blocked_reason'] === 'Suppressed', 'Suppressed address was not blocked.');
	$suppressed_handoff = backstage_outreach_party_invitation_handoff($suppressed_preview, $user_id);
	outreach_party_unified_assert($suppressed_handoff['handed_off'] === 0 && count($suppressed_handoff['failed']) === 1 && $mail_attempts === 3, 'Suppressed address reached the mailer.');
	$manual = backstage_outreach_party_record_activity(array('campaign_id' => (int) $paid['campaign_id'], 'distribution_id' => $send_ids[0], 'party_id' => (int) $sendable[0]['party_id'], 'activity_type' => 'manual_contact', 'activity_status' => 'logged', 'contact_method' => 'phone', 'notes' => 'Synthetic call', 'request_key' => hash('sha256', $marker . '|manual')), $user_id);
	outreach_party_unified_assert(is_array($manual), 'Manual contact activity was not recorded.');

	outreach_party_unified_assert($baseline['campaign31'] === $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', vms_admission_table_pass_outreach_campaigns(), 31), ARRAY_A), 'Campaign 31 changed.');
	outreach_party_unified_assert($baseline['batch84'] === $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', bvmgr_admission_table_pass_batches(), 84), ARRAY_A), 'Batch 84 changed.');
	outreach_party_unified_assert($count(bvmgr_admission_table_pass_tokens()) === $baseline['tokens'], 'Complimentary token inventory changed.');

	echo "Unified Party workflow runtime PASS: 103 Parties, 206 snapshots, 36 brokerage labels, 103 verified reusable links/coupons, safe replay/partial recovery, and audited handoffs.\n";
	echo wp_json_encode(array(
		'historical_contact_ids_nonzero' => 0,
		'compound_proposals' => 103,
		'confirmed_parties' => 103,
		'preserved_snapshot_links' => 206,
		'unsafe_edge_groups_kept_separate' => 9,
		'shared_contact_id_groups_preserved' => 1,
		'conflicting_prior_mappings_blocked' => 1,
		'invitation_states_blocked' => array('paused_distribution', 'revoked_distribution', 'draft_campaign', 'expired_distribution', 'inactive_source_membership', 'inactive_party', 'paused_batch', 'stale_configuration', 'invalid_signature', 'invalid_coupon_owner', 'suppressed_email'),
		'mailer_calls_for_blocked_states' => 0,
	), JSON_PRETTY_PRINT) . "\n";
} finally {
	remove_filter('pre_wp_mail', $mail_block, PHP_INT_MAX);
	remove_filter('backstage_outreach_mail_transport_readiness', $unverified_transport, PHP_INT_MAX);
	remove_filter('backstage_outreach_postal_address', $synthetic_postal_address, PHP_INT_MAX);
	foreach ($campaign_ids as $unsubscribe_campaign_id) {
		$wpdb->delete(backstage_outreach_unsubscribe_table(), array('source_campaign_id' => (int) $unsubscribe_campaign_id), array('%d'));
	}
	if ($suppression_id > 0) { vms_outreach_remove_suppression($suppression_id); }
	foreach (array_unique($coupon_ids) as $coupon_id) { if ($coupon_id > 0) { wp_delete_post($coupon_id, true); } }
	foreach (array('activities', 'redemptions', 'distributions', 'audit', 'links', 'roles', 'sources', 'methods', 'parties') as $key) {
		$wpdb->query($wpdb->prepare('DELETE FROM %i WHERE id>%d', $tables[$key], $max_ids[$key]));
	}
	foreach ($recipient_ids as $pair) { foreach ($pair as $recipient_id) { $wpdb->delete(vms_pass_outreach_recipient_table(), array('id' => $recipient_id), array('%d')); } }
	foreach ($edge_recipient_ids as $recipient_id) { $wpdb->delete(vms_pass_outreach_recipient_table(), array('id' => $recipient_id), array('%d')); }
	foreach (array_reverse($campaign_ids) as $campaign_id) { $wpdb->delete(vms_admission_table_pass_outreach_campaigns(), array('id' => $campaign_id), array('%d')); }
	foreach (array_reverse($batch_ids) as $batch_id) { $wpdb->delete(bvmgr_admission_table_pass_batches(), array('id' => $batch_id), array('%d')); }
	if ($source_id > 0) { $wpdb->delete(bvmgr_admission_table_pass_sources(), array('id' => $source_id), array('%d')); }
	foreach (array_reverse(array_unique($post_ids)) as $post_id) { wp_delete_post($post_id, true); }
	update_option(backstage_outreach_party_schema_option_key(), backstage_outreach_party_schema_target(), false);
}
