<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	fwrite(STDERR, "Run with wp eval-file.\n");
	exit(2);
}

$assert = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

global $wpdb;
$now = bvmgr_admission_now_mysql();
$marker = 'wave2a-' . strtolower(wp_generate_password(10, false, false));
$wpdb->query('START TRANSACTION');

try {
	$assert(defined('BVMGR_VERSION') && BVMGR_VERSION === '1.3.9', 'BVM 1.3.9 is not active.');

	$sourceTable = bvmgr_admission_table_pass_sources();
	$batchTable = bvmgr_admission_table_pass_batches();
	$tokenTable = bvmgr_admission_table_pass_tokens();
	$claimTable = bvmgr_admission_table_pass_claims();
	$entryTable = bvmgr_admission_table_entries();
	$assert($wpdb->insert($sourceTable, array('source_name' => 'Wave 2A ' . $marker, 'status' => 'active', 'created_by' => 1, 'created_at' => $now)) !== false, 'Could not create the synthetic source.');
	$sourceId = (int) $wpdb->insert_id;

	$assert($wpdb->insert($wpdb->posts, array(
		'post_author' => 1,
		'post_date' => $now,
		'post_date_gmt' => get_gmt_from_date($now),
		'post_content' => '',
		'post_title' => 'Wave 2A Synthetic Event',
		'post_excerpt' => '',
		'post_status' => 'publish',
		'comment_status' => 'closed',
		'ping_status' => 'closed',
		'post_password' => '',
		'post_name' => $marker,
		'to_ping' => '',
		'pinged' => '',
		'post_modified' => $now,
		'post_modified_gmt' => get_gmt_from_date($now),
		'post_content_filtered' => '',
		'post_parent' => 0,
		'guid' => home_url('/?' . $marker),
		'menu_order' => 0,
		'post_type' => 'vms_event_plan',
		'post_mime_type' => '',
		'comment_count' => 0,
	)) !== false, 'Could not create the synthetic Event Plan row.');
	$eventId = (int) $wpdb->insert_id;
	$futureDate = wp_date('Y-m-d', time() + (30 * DAY_IN_SECONDS), wp_timezone());
	$statusKey = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status';
	foreach (array($statusKey => 'published', '_vms_event_plan_status' => 'published', '_vms_event_date' => $futureDate) as $key => $value) {
		$assert($wpdb->insert($wpdb->postmeta, array('post_id' => $eventId, 'meta_key' => $key, 'meta_value' => $value)) !== false, 'Could not create synthetic Event Plan metadata.');
	}
	clean_post_cache($eventId);

	$base = array(
		'source_id' => $sourceId,
		'batch_name' => 'Wave 2A Validation',
		'quantity' => 4,
		'admissions_per_link' => 2,
		'total_admission_cap' => 8,
		'validity_type' => 'any_event',
		'single_event_plan_id' => $eventId,
		'start_date' => $futureDate,
		'end_date' => wp_date('Y-m-d', time() + (60 * DAY_IN_SECONDS), wp_timezone()),
		'season_label' => 'Synthetic Season',
		'venue_ids' => array(),
		'value_type' => 'free',
		'value_amount' => 31.25,
		'expires_at' => '',
		'status' => 'active',
		'checkin_open_mode' => 'same_day',
		'max_per_phone' => 0,
		'max_per_email' => 0,
		'notes' => 'Synthetic only',
	);
	$baseDigest = bvmgr_pass_claims_preview_digest($base, 1);
	$changedForDigest = $base;
	$changedForDigest['batch_name'] = 'Changed after review';
	$assert(!hash_equals($baseDigest, bvmgr_pass_claims_preview_digest($changedForDigest, 1)), 'Material edits must invalidate the preview digest.');
	$assert(!hash_equals($baseDigest, bvmgr_pass_claims_preview_digest($base, 2)), 'Preview digests must be bound to the reviewing user.');
	$duplicateSource = $base;
	$duplicateSource['venue_ids_json'] = wp_json_encode(array(11, 22));
	unset($duplicateSource['venue_ids']);
	$duplicateDraft = bvmgr_pass_claims_soft_batch_payload_for_form($duplicateSource);
	$assert(bvmgr_pass_claims_decode_venue_ids_json((string) $duplicateDraft['venue_ids_json']) === array(11, 22), 'Duplicate draft must preserve eligible venue values without copying rights.');

	foreach (array('any_event', 'single_event', 'date_range', 'season') as $validity) {
		foreach (array('free', 'percent', 'fixed') as $valueType) {
			$raw = $base;
			$raw['validity_type'] = $validity;
			$raw['value_type'] = $valueType;
			$raw['value_amount'] = $valueType === 'percent' ? 50 : ($valueType === 'fixed' ? 25 : 31.25);
			$result = bvmgr_pass_claims_sanitize_batch_payload($raw);
			$assert(is_array($result), 'Validation failed for ' . $validity . '/' . $valueType . ': ' . (is_wp_error($result) ? $result->get_error_message() : 'unknown'));
			if ($validity === 'any_event') {
				$assert((int) $result['single_event_plan_id'] === 0 && $result['start_date'] === '' && $result['end_date'] === '' && $result['season_label'] === '', 'Any Event retained irrelevant scope values.');
			} elseif ($validity === 'single_event') {
				$assert((int) $result['single_event_plan_id'] === $eventId && $result['start_date'] === '' && $result['end_date'] === '' && $result['season_label'] === '', 'Single Event scope normalization failed.');
			} elseif ($validity === 'date_range') {
				$assert((int) $result['single_event_plan_id'] === 0 && $result['season_label'] === '', 'Date Range retained irrelevant scope values.');
			} else {
				$assert((int) $result['single_event_plan_id'] === 0 && $result['season_label'] === 'Synthetic Season', 'Season scope normalization failed.');
			}
			if ($valueType === 'free') {
				$assert((float) $result['value_amount'] === 100.0, 'Free value did not normalize to 100%.');
			}
		}
	}

	$insertBatch = static function (array $overrides) use ($wpdb, $batchTable, $sourceId, $now, $assert): int {
		$row = array_merge(array(
			'source_id' => $sourceId, 'batch_name' => 'Wave 2A Fixture', 'quantity' => 4,
			'admissions_per_link' => 2, 'total_admission_cap' => 8, 'validity_type' => 'any_event',
			'single_event_plan_id' => 0, 'start_date' => '', 'end_date' => '', 'season_label' => '',
			'venue_ids_json' => '', 'value_type' => 'free', 'value_amount' => 100,
			'applies_to' => 'entry_only', 'expires_at' => '', 'status' => 'active',
			'checkin_open_mode' => 'same_day', 'max_per_phone' => 0, 'max_per_email' => 0,
			'notes' => 'Synthetic fixture', 'generated_count' => 0, 'created_by' => 1, 'created_at' => $now,
		), $overrides);
		$assert($wpdb->insert($batchTable, $row) !== false, 'Could not create synthetic batch.');
		return (int) $wpdb->insert_id;
	};
	$insertToken = static function (int $batchId, int $index, string $status = 'unclaimed') use ($wpdb, $tokenTable, $sourceId, $now, $marker, $assert): int {
		$key = substr(hash('sha256', $marker . '|' . $batchId . '|' . $index), 0, 24);
		$assert($wpdb->insert($tokenTable, array('batch_id' => $batchId, 'source_id' => $sourceId, 'token_public_key' => $key, 'token_hash' => hash('sha256', $key), 'status' => $status, 'created_at' => $now, 'created_by' => 1)) !== false, 'Could not create synthetic token.');
		return (int) $wpdb->insert_id;
	};
	$insertClaim = static function (int $batchId, int $tokenId, int $index, int $partySize, int $checkedIn) use ($wpdb, $claimTable, $entryTable, $tokenTable, $sourceId, $eventId, $now, $marker, $assert): void {
		$assert($wpdb->insert($claimTable, array('token_id' => $tokenId, 'batch_id' => $batchId, 'source_id' => $sourceId, 'event_plan_id' => $eventId, 'first_name' => 'Synthetic', 'last_name' => 'Guest ' . $index, 'phone' => '555000' . $index, 'phone_norm' => '555000' . $index, 'email' => $marker . $index . '@example.test', 'opt_in' => 0, 'created_at' => $now)) !== false, 'Could not create synthetic claim.');
		$claimId = (int) $wpdb->insert_id;
		$assert($wpdb->insert($entryTable, array('event_plan_id' => $eventId, 'venue_id' => 0, 'admission_kind' => 'guest_pass', 'source' => 'pass_claim', 'guest_name' => 'Synthetic Guest ' . $index, 'guest_name_norm' => 'synthetic guest ' . $index, 'party_size' => $partySize, 'checked_in_qty' => $checkedIn, 'status' => 'active', 'pass_source_id' => $sourceId, 'pass_batch_id' => $batchId, 'pass_token_id' => $tokenId, 'pass_claim_id' => $claimId, 'admission_token' => '', 'created_by' => 1, 'created_at' => $now)) !== false, 'Could not create synthetic admission.');
		$entryId = (int) $wpdb->insert_id;
		$wpdb->update($claimTable, array('reservation_entry_id' => $entryId), array('id' => $claimId));
		$wpdb->update($tokenTable, array('status' => 'claimed', 'claim_id' => $claimId, 'reservation_entry_id' => $entryId, 'claimed_at' => $now), array('id' => $tokenId));
	};

	$unusedBatch = $insertBatch(array('batch_name' => 'Unused', 'quantity' => 0, 'total_admission_cap' => 8));
	$unusedSummary = bvmgr_pass_claims_batch_activity_summary($unusedBatch);
	$assert(bvmgr_pass_claims_batch_can_retire((array) bvmgr_pass_claims_get_batch_by_id($unusedBatch), $unusedSummary), 'A truly unused and unlinked batch should be safely retireable.');

	$partialBatch = $insertBatch(array('batch_name' => 'Four Links x Two', 'generated_count' => 4));
	$partialTokens = array();
	for ($i = 1; $i <= 4; $i++) {
		$partialTokens[] = $insertToken($partialBatch, $i);
	}
	$insertClaim($partialBatch, $partialTokens[0], 1, 2, 1);
	$insertClaim($partialBatch, $partialTokens[1], 2, 1, 0);
	$partialSummary = bvmgr_pass_claims_batch_activity_summary($partialBatch);
	$assert($partialSummary['core_links'] === 4 && $partialSummary['claims'] === 2 && $partialSummary['reserved_admissions'] === 3 && $partialSummary['checked_in_admissions'] === 1, 'Four-link partially claimed accounting is incorrect.');
	$assert(!bvmgr_pass_claims_batch_can_retire((array) bvmgr_pass_claims_get_batch_by_id($partialBatch), $partialSummary), 'A batch with issued rights must not retire.');

	$insertBatch(array('batch_name' => 'Expired', 'expires_at' => wp_date('Y-m-d H:i:s', time() - DAY_IN_SECONDS, wp_timezone())));
	$fullBatch = $insertBatch(array('batch_name' => 'Fully Claimed', 'quantity' => 1, 'generated_count' => 1, 'total_admission_cap' => 2));
	$fullToken = $insertToken($fullBatch, 1);
	$insertClaim($fullBatch, $fullToken, 20, 2, 2);
	$fullSummary = bvmgr_pass_claims_batch_activity_summary($fullBatch);
	$assert($fullSummary['claims'] === 1 && $fullSummary['reserved_admissions'] === 2 && $fullSummary['checked_in_admissions'] === 2, 'Fully claimed accounting is incorrect.');

	if (function_exists('vms_admission_table_pass_outreach_campaigns') && function_exists('backstage_outreach_business_table')) {
		$chamberBatch = $insertBatch(array('batch_name' => 'Chamber Equivalent', 'quantity' => 0, 'generated_count' => 0, 'total_admission_cap' => 50));
		$campaignTable = vms_admission_table_pass_outreach_campaigns();
		$assert($wpdb->insert($campaignTable, array('campaign_name' => 'Wave 2A Chamber Equivalent', 'related_source_id' => $sourceId, 'related_batch_id' => $chamberBatch, 'validity_type' => 'any_event', 'admissions_per_recipient' => 1, 'total_admission_cap' => 50, 'status' => 'active', 'eligibility_mode' => 'anyone_with_invite', 'created_by' => 1, 'created_at' => $now)) !== false, 'Could not create synthetic linked campaign.');
		$campaignId = (int) $wpdb->insert_id;
		$distributionTable = backstage_outreach_business_table('campaign_businesses');
		$publicKey = substr(hash('sha256', $marker . '|distribution'), 0, 24);
		$assert($wpdb->insert($distributionTable, array('campaign_id' => $campaignId, 'source_id' => $sourceId, 'business_id' => 987654321, 'public_key' => $publicKey, 'token_hash' => hash('sha256', $publicKey), 'status' => 'active', 'distribution_type' => 'complimentary', 'admission_cap' => 50, 'created_by' => 1, 'created_at' => $now)) !== false, 'Could not create synthetic reusable link.');
		for ($i = 1; $i <= 4; $i++) {
			$tokenId = $insertToken($chamberBatch, 100 + $i);
			$insertClaim($chamberBatch, $tokenId, 100 + $i, 1, $i <= 2 ? 1 : 0);
		}
		$chamberSummary = bvmgr_pass_claims_batch_activity_summary($chamberBatch);
		$assert($chamberSummary['core_links'] === 0 && $chamberSummary['reusable_links'] === 1 && $chamberSummary['reusable_link_allocation'] === 50 && $chamberSummary['claims'] === 4 && $chamberSummary['reserved_admissions'] === 4 && $chamberSummary['checked_in_admissions'] === 2, 'Outreach/Chamber-equivalent accounting is not truthful.');
		$assert(!bvmgr_pass_claims_batch_can_retire((array) bvmgr_pass_claims_get_batch_by_id($chamberBatch), $chamberSummary), 'A linked Outreach batch must not retire.');
	}

	$wpdb->query('ROLLBACK');
	echo "PASS: Wave 2A runtime fixtures (all validity/value types; unused, partial, expired, full; 4x2; linked Outreach counts; safe retirement).\n";
} catch (Throwable $error) {
	$wpdb->query('ROLLBACK');
	fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
	exit(1);
}
