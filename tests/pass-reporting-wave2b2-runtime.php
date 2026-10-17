<?php
/**
 * Run through WordPress:
 * wp eval-file wp-content/plugins/packages/.../tests/pass-reporting-wave2b2-runtime.php
 */

defined('ABSPATH') || exit(1);

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$assert(defined('BVMGR_VERSION') && BVMGR_VERSION === '1.3.13', 'BVM 1.3.13 is not active.');
$assert(function_exists('bvmgr_pass_reporting_batches'), 'Canonical reporting runtime is unavailable.');

global $wpdb;
$now = current_time('mysql');
$marker = 'BVM-W2B2-' . wp_generate_uuid4();
$started = $wpdb->query('START TRANSACTION') !== false;
$assert($started, 'Could not start isolated reporting fixture transaction.');

try {
	$source_table = bvmgr_admission_table_pass_sources();
	$batch_table = bvmgr_admission_table_pass_batches();
	$token_table = bvmgr_admission_table_pass_tokens();
	$claim_table = bvmgr_admission_table_pass_claims();
	$entry_table = bvmgr_admission_table_entries();
	$invitation_table = bvmgr_admission_table_pass_invitations();

	$insert_source = static function (string $name) use ($wpdb, $source_table, $now): int {
		$wpdb->insert($source_table, array('source_name' => $name, 'status' => 'active', 'created_by' => 1, 'created_at' => $now));
		return (int) $wpdb->insert_id;
	};
	$insert_batch = static function (int $source_id, string $name, int $quantity, int $cap) use ($wpdb, $batch_table, $now): int {
		$wpdb->insert($batch_table, array(
			'source_id' => $source_id, 'batch_name' => $name, 'quantity' => $quantity, 'admissions_per_link' => 2,
			'total_admission_cap' => $cap, 'validity_type' => 'date_range', 'start_date' => '2026-01-01', 'end_date' => '2030-12-31',
			'value_type' => 'free', 'value_amount' => 0, 'applies_to' => 'entry_only', 'status' => 'active',
			'checkin_open_mode' => 'same_day', 'max_per_phone' => 0, 'max_per_email' => 0, 'generated_count' => $quantity,
			'created_by' => 1, 'created_at' => $now,
		));
		return (int) $wpdb->insert_id;
	};
	$insert_token = static function (int $source_id, int $batch_id, string $status, string $suffix) use ($wpdb, $token_table, $now, $marker): int {
		$key = substr(hash('sha256', $marker . '|token|' . $suffix), 0, 48);
		$wpdb->insert($token_table, array('batch_id' => $batch_id, 'source_id' => $source_id, 'token_public_key' => $key, 'token_hash' => hash('sha256', $key), 'status' => $status, 'created_at' => $now, 'created_by' => 1));
		return (int) $wpdb->insert_id;
	};

	$direct_source = $insert_source($marker . ' Direct');
	$direct_batch = $insert_batch($direct_source, $marker . ' Direct Batch', 4, 8);
	$direct_tokens = array();
	for ($i = 1; $i <= 4; $i++) {
		$direct_tokens[] = $insert_token($direct_source, $direct_batch, 'unclaimed', 'direct-' . $i);
	}
	$attempt_statuses = array(
		array($direct_tokens[0], 'handed_off', 'a'),
		array($direct_tokens[0], 'failed', 'b'),
		array($direct_tokens[1], 'sending', 'c'),
		array($direct_tokens[1], 'handed_off', 'd'),
	);
	foreach ($attempt_statuses as $attempt) {
		$wpdb->insert($invitation_table, array(
			'token_id' => $attempt[0], 'batch_id' => $direct_batch, 'source_id' => $direct_source,
			'attempt_key' => hash('sha256', $marker . '|attempt|' . $attempt[2]), 'review_digest' => hash('sha256', $marker . '|review|' . $attempt[2]),
			'recipient_name' => 'Synthetic Recipient', 'recipient_email' => 'synthetic@example.invalid', 'recipient_email_hash' => hash('sha256', 'synthetic@example.invalid'),
			'subject' => 'Synthetic', 'message_hash' => hash('sha256', 'message'), 'claim_link_hash' => hash('sha256', 'link'),
			'status' => $attempt[1], 'attempt_type' => 'initial', 'operator_user_id' => 1, 'created_at' => $now,
		));
	}

	$unrelated_sources = array();
	for ($i = 1; $i <= 10; $i++) {
		$name = $i === 1 ? $marker . ' A Source 44 unrelated' : $marker . ' U Unrelated Source ' . (44 + $i);
		$source_id = $insert_source($name);
		$insert_batch($source_id, $marker . ' Unrelated Batch ' . $i, 0, 10);
		$unrelated_sources[] = $source_id;
	}

	$chamber_source = $insert_source($marker . ' B Source 42 Whitehouse Chamber');
	$chamber_batch = $insert_batch($chamber_source, $marker . ' Batch 86 equivalent', 0, 1000);
	$campaign_table = vms_admission_table_pass_outreach_campaigns();
	$distribution_table = backstage_outreach_business_table('campaign_businesses');
	$distribution_claim_table = backstage_outreach_business_table('distribution_claims');
	$wpdb->insert($campaign_table, array(
		'campaign_name' => $marker . ' Campaign 32 equivalent', 'campaign_purpose' => 'guest_pass_invitation',
		'related_source_id' => $chamber_source, 'related_batch_id' => $chamber_batch, 'validity_type' => 'batch_default',
		'admissions_per_recipient' => 2, 'total_admission_cap' => 1000, 'status' => 'active', 'eligibility_mode' => 'anyone_with_invite',
		'created_by' => 1, 'created_at' => $now,
	));
	$campaign_id = (int) $wpdb->insert_id;
	$wpdb->insert($distribution_table, array(
		'campaign_id' => $campaign_id, 'source_id' => $chamber_source, 'business_id' => 987654321,
		'public_key' => substr(hash('sha256', $marker . '|distribution'), 0, 48), 'token_hash' => hash('sha256', $marker . '|distribution-token'),
		'status' => 'active', 'distribution_type' => 'complimentary', 'admission_cap' => 50, 'order_cap' => 0,
		'created_by' => 1, 'created_at' => $now,
	));
	$distribution_id = (int) $wpdb->insert_id;

	for ($i = 1; $i <= 4; $i++) {
		$token_id = $insert_token($chamber_source, $chamber_batch, 'claimed', 'reusable-' . $i);
		$wpdb->insert($claim_table, array(
			'token_id' => $token_id, 'batch_id' => $chamber_batch, 'source_id' => $chamber_source, 'event_plan_id' => 900000 + $i,
			'first_name' => 'Synthetic', 'last_name' => 'Guest ' . $i, 'phone' => '55500000' . $i, 'phone_norm' => '55500000' . $i,
			'email' => null, 'opt_in' => 0, 'created_at' => $now,
		));
		$claim_id = (int) $wpdb->insert_id;
		$entry_id = 0;
		for ($seat = 1; $seat <= 2; $seat++) {
			$wpdb->insert($entry_table, array(
				'event_plan_id' => 900000 + $i, 'venue_id' => 1, 'admission_kind' => 'pass', 'source' => 'pass_claim',
				'guest_name' => 'Synthetic Guest ' . $i . '-' . $seat, 'guest_name_norm' => 'synthetic guest ' . $i . '-' . $seat, 'party_size' => 1,
				'checked_in_qty' => $i <= 2 ? 1 : 0, 'status' => $i <= 2 ? 'checked_in' : 'active',
				'pass_source_id' => $chamber_source, 'pass_batch_id' => $chamber_batch, 'pass_token_id' => $token_id, 'pass_claim_id' => $claim_id,
				'claim_reference' => 'synthetic:' . $i . ':' . $seat, 'admission_token' => substr(hash('sha256', $marker . '|entry|' . $i . '|' . $seat), 0, 64),
				'created_by' => 1, 'created_at' => $now,
			));
			if ($entry_id === 0) {
				$entry_id = (int) $wpdb->insert_id;
			}
		}
		$wpdb->update($claim_table, array('reservation_entry_id' => $entry_id), array('id' => $claim_id));
		$wpdb->update($token_table, array('claim_id' => $claim_id, 'reservation_entry_id' => $entry_id, 'claimed_at' => $now), array('id' => $token_id));
		$wpdb->insert($distribution_claim_table, array(
			'distribution_id' => $distribution_id, 'campaign_id' => $campaign_id, 'source_id' => $chamber_source, 'business_id' => 987654321,
			'pass_token_id' => $token_id, 'pass_claim_id' => $claim_id, 'reservation_entry_id' => $entry_id, 'party_size' => 2,
			'submission_key_hash' => hash('sha256', $marker . '|submission|' . $i), 'identity_hash' => hash('sha256', $marker . '|identity|' . $i),
			'status' => 'fulfilled', 'created_at' => $now, 'fulfilled_at' => $now,
		));
	}

	$filters = bvmgr_pass_reporting_filters(array('report_source_id' => $direct_source));
	$direct = bvmgr_pass_reporting_batches($filters, 0, 20);
	$assert(count($direct['rows']) === 1, 'Direct fixture did not produce one batch row.');
	$d = $direct['rows'][0];
	$assert((int) $d['direct_links_issued'] === 4 && (int) $d['invitation_attempts'] === 4 && (int) $d['unique_invited_links'] === 2, 'Direct link/invitation attempt separation is incorrect.');
	$assert((int) $d['latest_handed_off'] === 1 && (int) $d['latest_failed'] === 1 && (int) $d['latest_indeterminate'] === 0, 'Latest invitation outcomes are inflated by history.');

	$filters = bvmgr_pass_reporting_filters(array('report_batch_id' => $chamber_batch));
	$golden = bvmgr_pass_reporting_batches($filters, 0, 20);
	$assert(count($golden['rows']) === 1, 'Golden fixture did not produce one batch row.');
	$g = $golden['rows'][0];
	$assert((int) $g['direct_links_issued'] === 0, 'Reusable internal tokens were mislabeled as direct links.');
	$assert((int) $g['reusable_links'] === 1 && (int) $g['allocated_admission_capacity'] === 50, 'Reusable link/allocation should be 1/50.');
	$assert((int) $g['claims'] === 4 && (int) $g['reusable_fulfilled_claims'] === 4, 'Fulfilled claims should be 4.');
	$assert((int) $g['reserved_entries'] === 8 && (int) $g['reserved_headcount'] === 8 && (int) $g['reusable_reserved_headcount'] === 8, 'Eight canonical native admissions must remain independently counted.');
	$assert((int) $g['checked_in_entries'] === 4 && (int) $g['checked_in_headcount'] === 4, 'Checked-in admissions should be 4.');
	$assert((float) $g['allocation_utilization'] === 16.0, 'Allocation utilization should be 8/50 = 16%.');
	$assert((int) $g['total_admission_cap'] === 1000, 'Global batch cap must remain separate from allocation 50.');

	$source_result = bvmgr_pass_reporting_sources($filters, 0, 20);
	$assert((int) $source_result['total'] === 1 && count($source_result['rows']) === 1, 'Batch-filtered Per Source reporting leaked unrelated sources.');
	$source_row = $source_result['rows'][0];
	$assert((int) $source_row['source_id'] === $chamber_source, 'Batch-filtered Per Source reporting did not return Source 42 equivalent.');
	$assert((int) $source_row['direct_links_issued'] === 0 && (int) $source_row['reusable_links'] === 1 && (int) $source_row['allocated_admission_capacity'] === 50, 'Source 42 direct/reusable/allocation metrics are incorrect.');
	$assert((int) $source_row['claims'] === 4 && (int) $source_row['reserved_headcount'] === 8 && (int) $source_row['checked_in_headcount'] === 4 && (float) $source_row['allocation_utilization'] === 16.0, 'Source 42 claim/admission/check-in/utilization metrics are incorrect.');
	$source_csv = bvmgr_pass_reporting_csv_row('source', $source_row);
	$assert((int) $source_csv[0] === $chamber_source && count($source_csv) === count(bvmgr_pass_reporting_csv_headers('source')), 'Per Source UI/CSV row identity or width diverged.');

	$all_fixture_sources = bvmgr_pass_reporting_sources(bvmgr_pass_reporting_filters(array('report_s' => $marker)), 0, 100);
	$assert((int) $all_fixture_sources['total'] === 12, 'Unfiltered-equivalent fixture scope did not retain exactly all 12 populated sources.');
	$fixture_ids = array_map('intval', array_column($all_fixture_sources['rows'], 'source_id'));
	$source_44_position = array_search($unrelated_sources[0], $fixture_ids, true);
	$source_42_position = array_search($chamber_source, $fixture_ids, true);
	$assert($source_44_position !== false && $source_42_position !== false && $source_44_position < $source_42_position, 'Unrelated Source 44 fixture did not precede Source 42 in canonical order.');
	$empty_sources = bvmgr_pass_reporting_sources(bvmgr_pass_reporting_filters(array('report_batch_id' => 2147483647)), 0, 20);
	$assert((int) $empty_sources['total'] === 0 && $empty_sources['rows'] === array(), 'Nonexistent Batch filter did not produce a true empty Per Source result.');
	$cross_filtered = bvmgr_pass_reporting_sources(bvmgr_pass_reporting_filters(array('report_source_id' => $unrelated_sources[0], 'report_batch_id' => $chamber_batch)), 0, 20);
	$assert((int) $cross_filtered['total'] === 0 && $cross_filtered['rows'] === array(), 'Combined Source+Batch filter leaked a nonmatching source.');
	$fixed_batch = $insert_batch($direct_source, $marker . ' Fixed Paid Offer', 0, 10);
	$wpdb->update($batch_table, array('value_type' => 'fixed', 'value_amount' => 25), array('id' => $fixed_batch));
	$fixed_sources = bvmgr_pass_reporting_sources(bvmgr_pass_reporting_filters(array('report_value_type' => 'fixed', 'report_status' => 'active', 'report_s' => $marker)), 0, 20);
	$assert((int) $fixed_sources['total'] === 1 && (int) $fixed_sources['rows'][0]['source_id'] === $direct_source && (int) $fixed_sources['rows'][0]['batches_count'] === 1, 'Paid/complimentary and status filters did not constrain the canonical source set.');
	$wpdb->insert($distribution_table, array(
		'campaign_id' => $campaign_id, 'source_id' => $chamber_source, 'business_id' => 987654322,
		'public_key' => substr(hash('sha256', $marker . '|distribution-2'), 0, 48), 'token_hash' => hash('sha256', $marker . '|distribution-token-2'),
		'status' => 'active', 'distribution_type' => 'complimentary', 'admission_cap' => 50, 'order_cap' => 0,
		'created_by' => 1, 'created_at' => $now,
	));
	$pooled = bvmgr_pass_reporting_batches($filters, 0, 20)['rows'][0];
	$assert((int) $pooled['reusable_links'] === 2 && (int) $pooled['allocated_admission_capacity'] === 100, 'Independent partner allocations were not summed accurately.');
	$assert((int) $pooled['total_admission_cap'] === 1000 && (int) $pooled['reserved_headcount'] === 8, 'Shared pool or actual reservations were repeated once per business.');
	$paid_table = backstage_outreach_business_table('paid_redemptions');
	foreach (array(array('paid', 3), array('refunded', 2), array('cancelled', 4)) as $index => $paid_fixture) {
		$wpdb->insert($paid_table, array(
			'distribution_id' => $distribution_id, 'campaign_id' => $campaign_id, 'source_id' => $chamber_source, 'business_id' => 987654321,
			'coupon_id' => 800000 + $index, 'coupon_code' => $marker . '-' . $index, 'order_id' => 810000 + $index,
			'status' => $paid_fixture[0], 'ticket_quantity' => $paid_fixture[1], 'discount_total' => 0, 'eligible_ticket_gross_total' => 0,
			'eligible_ticket_net_total' => 0, 'eligible_ticket_refunded_total' => $paid_fixture[0] === 'refunded' ? 1 : 0,
			'order_total' => 0, 'refunded_total' => $paid_fixture[0] === 'refunded' ? 1 : 0, 'currency' => 'USD',
			'eligible_ticket_ids_json' => '[]', 'created_at' => $now,
		));
	}
	$paid = bvmgr_pass_reporting_batches($filters, 0, 20)['rows'][0];
	$assert((int) $paid['paid_redemptions'] === 1 && (int) $paid['paid_admissions'] === 3, 'Paid metrics included fully refunded or canceled admissions.');
	$assert((int) $paid['refunded_redemptions'] === 1 && (int) $paid['cancelled_redemptions'] === 1, 'Refund/cancellation metrics are not separated.');

	$headers = bvmgr_pass_reporting_csv_headers('batch');
	$csv = bvmgr_pass_reporting_csv_row('batch', $g);
	$assert(count($headers) === count($csv), 'UI/CSV canonical row width differs.');
	$assert(!preg_grep('/token|url|email|phone|coupon_code|unsubscribe/i', $headers), 'CSV exposes a bearer secret or recipient PII column.');
	$assert(bvmgr_pass_claims_csv_safe('=2+2') === "'=2+2", 'CSV formula neutralization failed.');

	$large_source = $insert_source($marker . ' Large Export');
	for ($i = 1; $i <= 205; $i++) {
		$insert_batch($large_source, $marker . ' Large ' . $i, 0, 0);
	}
	$large_filters = bvmgr_pass_reporting_filters(array('report_source_id' => $large_source));
	$large_page_1 = bvmgr_pass_reporting_batches($large_filters, 0, 100);
	$large_page_2 = bvmgr_pass_reporting_batches($large_filters, 100, 100);
	$large_page_3 = bvmgr_pass_reporting_batches($large_filters, 200, 100);
	$assert((int) $large_page_1['total'] === 205 && count($large_page_1['rows']) === 100 && count($large_page_2['rows']) === 100 && count($large_page_3['rows']) === 5, 'Large-list bounded paging is incorrect.');
	$large_source_row = bvmgr_pass_reporting_sources($large_filters, 0, 20)['rows'][0] ?? array();
	$assert((int) ($large_source_row['batches_count'] ?? 0) === 205, 'Per-Source totals were truncated at one query page.');

	$past_batch = $insert_batch($direct_source, $marker . ' Historical', 0, 10);
	$wpdb->update($batch_table, array('end_date' => '2020-01-01'), array('id' => $past_batch));
	$past = bvmgr_pass_reporting_batches(bvmgr_pass_reporting_filters(array('report_batch_id' => $past_batch)), 0, 20)['rows'][0] ?? array();
	$assert((string) ($past['availability_key'] ?? '') === 'past_event' && ($past['remaining_capacity'] ?? null) === null, 'Past offers were presented as currently redeemable.');
	$past_sources = bvmgr_pass_reporting_sources(bvmgr_pass_reporting_filters(array('report_source_id' => $direct_source, 'report_availability' => 'past_event')), 0, 20);
	$assert((int) $past_sources['total'] === 1 && count($past_sources['rows']) === 1 && (int) $past_sources['rows'][0]['batches_count'] === 1, 'Derived availability did not constrain Per Source enumeration and totals.');
	$multi_batch_source = bvmgr_pass_reporting_sources(bvmgr_pass_reporting_filters(array('report_source_id' => $direct_source)), 0, 20);
	$assert((int) $multi_batch_source['total'] === 1 && (int) $multi_batch_source['rows'][0]['batches_count'] === 3, 'A source spanning several batches did not retain all unfiltered batches.');

	$wpdb->query('ROLLBACK');
	echo "Wave 2B.2 runtime PASS: 12-source filter parity; golden 0/1/50/4/8/4 at 16%; pooled cap and 205-row paging PASS.\n";
} catch (Throwable $error) {
	$wpdb->query('ROLLBACK');
	fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
	exit(1);
}
