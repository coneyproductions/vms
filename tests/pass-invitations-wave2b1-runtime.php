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
bvmgr_admission_maybe_upgrade_schema();
$assert(defined('BVMGR_VERSION') && BVMGR_VERSION === '1.3.12', 'BVM 1.3.12 is not active.');
$assert($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', bvmgr_admission_table_pass_invitations())) === bvmgr_admission_table_pass_invitations(), 'Invitation audit table is missing.');
$postal_filter = null;
if (function_exists('backstage_outreach_postal_address') && backstage_outreach_postal_address() === '') {
	$postal_filter = static fn(): string => 'Synthetic Test Venue, 100 Test Way, Test City, TN 37000, US';
	add_filter('backstage_outreach_postal_address', $postal_filter);
}

$marker = 'wave2b1-' . strtolower(wp_generate_password(10, false, false));
$now = bvmgr_admission_now_mysql();
$source_table = bvmgr_admission_table_pass_sources();
$batch_table = bvmgr_admission_table_pass_batches();
$token_table = bvmgr_admission_table_pass_tokens();
$invitation_table = bvmgr_admission_table_pass_invitations();
$entry_table = bvmgr_admission_table_entries();
$wpdb->query('START TRANSACTION');

try {
	$assert($wpdb->insert($source_table, array('source_name' => 'Wave 2B.1 ' . $marker, 'status' => 'active', 'created_by' => 1, 'created_at' => $now)) !== false, 'Could not create synthetic source.');
	$source_id = (int) $wpdb->insert_id;
	$assert($wpdb->insert($batch_table, array(
		'source_id' => $source_id, 'batch_name' => 'Wave 2B.1 Four Links', 'quantity' => 4,
		'admissions_per_link' => 2, 'total_admission_cap' => 8, 'validity_type' => 'any_event',
		'value_type' => 'free', 'value_amount' => 100, 'applies_to' => 'entry_only',
		'expires_at' => wp_date('Y-m-d H:i:s', time() + DAY_IN_SECONDS, wp_timezone()), 'status' => 'active',
		'checkin_open_mode' => 'same_day', 'max_per_phone' => 0, 'max_per_email' => 0,
		'generated_count' => 4, 'created_by' => 1, 'created_at' => $now,
	)) !== false, 'Could not create synthetic batch.');
	$batch_id = (int) $wpdb->insert_id;
	$tokens = array();
	for ($index = 1; $index <= 4; $index++) {
		$key = substr(hash('sha256', $marker . '|' . $index), 0, 24);
		$assert($wpdb->insert($token_table, array('batch_id' => $batch_id, 'source_id' => $source_id, 'token_public_key' => $key, 'token_hash' => hash('sha256', $key), 'status' => 'unclaimed', 'created_at' => $now, 'created_by' => 1)) !== false, 'Could not create synthetic token.');
		$tokens[] = (int) $wpdb->insert_id;
	}

	$batch = bvmgr_pass_claims_get_batch_by_id($batch_id);
	$token = bvmgr_pass_claims_get_token_by_id($tokens[0]);
	$defaults = bvmgr_pass_invitation_defaults((array) $token, (array) $batch, 'Ada Lovelace');
	$payload = bvmgr_pass_invitation_build_payload($tokens[0], array_merge($defaults, array('recipient_name' => 'Ada Lovelace', 'recipient_email' => $marker . '@example.test')));
	$assert(is_array($payload), 'Valid invitation payload was rejected.');
	$payload['reviewed_previous_attempt_id'] = 0;
	$assert(str_contains((string) $payload['message'], (string) $payload['claim_url']), 'Exact canonical claim link is missing from the message.');
	$assert(str_contains((string) $payload['message'], bvmgr_pass_invitation_default_signature()), 'Default signature is missing.');
	$assert(str_contains((string) $payload['message'], 'This promotional email was sent by'), 'Promotional identity is missing from the exact outgoing message.');
	$assert(str_contains((string) $payload['message'], 'Postal address:'), 'Postal address is missing from the exact outgoing message.');
	$assert(str_contains((string) $payload['message'], 'Unsubscribe from all Backstage Outreach promotional email:'), 'Unsubscribe instruction is missing from the exact outgoing message.');
	$assert((string) ($payload['from_name'] ?? '') !== '' && is_email((string) ($payload['from_email'] ?? '')), 'Effective sender identity is unavailable.');
	$assert((string) ($payload['reply_to'] ?? '') === (string) $payload['from_email'], 'Reply-To does not match the reviewed sender address.');
	$assert(in_array('From: ' . $payload['from_name'] . ' <' . $payload['from_email'] . '>', (array) ($payload['headers'] ?? array()), true), 'Reviewed From header is missing from transport headers.');
	$assert(in_array('Reply-To: ' . $payload['reply_to'], (array) ($payload['headers'] ?? array()), true), 'Reviewed Reply-To header is missing from transport headers.');
	$digest = bvmgr_pass_invitation_digest($payload, 1);
	$changed = $payload;
	$changed['recipient_email'] = 'wrong-' . $marker . '@example.test';
	$assert(!hash_equals($digest, bvmgr_pass_invitation_digest($changed, 1)), 'Wrong-recipient change did not invalidate the review.');
	$assert(!hash_equals($digest, bvmgr_pass_invitation_digest($payload, 2)), 'Review is not bound to the operator.');
	$assert(is_wp_error(bvmgr_pass_invitation_validate_recipient('', 'bad-address')), 'Invalid recipient details were accepted.');
	if (function_exists('vms_outreach_table_suppressions') && function_exists('vms_outreach_normalize_email')) {
		$suppressed_email = 'suppressed-' . $marker . '@example.test';
		$assert($wpdb->insert(vms_outreach_table_suppressions(), array(
			'email' => $suppressed_email, 'email_norm' => vms_outreach_normalize_email($suppressed_email),
			'reason' => 'manual_admin', 'scope' => 'global_outreach', 'source_label' => 'Wave 2B.1 synthetic',
			'suppressed_at' => $now, 'created_by' => 1, 'created_at' => $now,
		)) !== false, 'Could not create synthetic suppression fixture.');
		$suppressed = bvmgr_pass_invitation_validate_recipient('Suppressed Recipient', $suppressed_email);
		$assert(is_wp_error($suppressed) && $suppressed->get_error_code() === 'recipient_suppressed', 'Suppressed address was not blocked.');
	}

	$captured = array();
	$accept = static function ($short_circuit, array $atts) use (&$captured) {
		$captured[] = $atts;
		return true;
	};
	add_filter('pre_wp_mail', $accept, 99, 2);
	$transport = bvmgr_pass_invitation_transport($payload);
	remove_filter('pre_wp_mail', $accept, 99);
	$assert($transport['status'] === 'handed_off' && count($captured) === 1, 'Intercepted successful handoff was not classified correctly.');
	$assert((string) $captured[0]['to'] === (string) $payload['recipient_email'], 'Transport used the wrong recipient.');
	$assert(str_contains((string) $captured[0]['message'], (string) $payload['claim_url']), 'Transport message changed after review.');

	$reject = static fn() => false;
	add_filter('pre_wp_mail', $reject, 99);
	$assert(bvmgr_pass_invitation_transport($payload)['status'] === 'failed', 'Rejected mail was not classified as failed.');
	remove_filter('pre_wp_mail', $reject, 99);
	$throw = static function () { throw new RuntimeException('synthetic ambiguous transport'); };
	add_filter('pre_wp_mail', $throw, 99);
	$assert(bvmgr_pass_invitation_transport($payload)['status'] === 'in_doubt', 'Transport exception was not classified as in-doubt.');
	remove_filter('pre_wp_mail', $throw, 99);

	foreach (array(0, 1) as $index) {
		$email = $marker . '-' . $index . '@example.test';
		$assert($wpdb->insert($invitation_table, array(
			'token_id' => $tokens[$index], 'batch_id' => $batch_id, 'source_id' => $source_id,
			'attempt_key' => hash('sha256', $marker . '|attempt|' . $index), 'review_digest' => hash('sha256', $marker . '|review|' . $index),
			'recipient_name' => 'Synthetic Recipient ' . $index, 'recipient_email' => $email, 'recipient_email_hash' => hash('sha256', $email),
			'subject' => 'Synthetic invitation', 'message_hash' => hash('sha256', 'message'), 'claim_link_hash' => hash('sha256', bvmgr_pass_claims_build_claim_url((array) bvmgr_pass_claims_get_token_by_id($tokens[$index]))),
			'status' => 'handed_off', 'attempt_type' => 'initial', 'operator_user_id' => 1, 'created_at' => $now, 'completed_at' => $now,
		)) !== false, 'Could not create synthetic invitation audit.');
	}
	$assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE batch_id = %d', $invitation_table, $batch_id)) === 2, 'Two invited links were not recorded.');
	$assert(bvmgr_pass_invitation_latest($tokens[2]) === null && bvmgr_pass_invitation_latest($tokens[3]) === null, 'Untouched links acquired invitation history.');
	$assert((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE batch_id = %d AND status = 'unclaimed'", $token_table, $batch_id)) === 4, 'Invitation audit changed token entitlement state.');

	$recent = array('status' => 'sending', 'created_at' => $now);
	$stale = array('status' => 'sending', 'created_at' => wp_date('Y-m-d H:i:s', time() - (10 * MINUTE_IN_SECONDS), wp_timezone()));
	$assert(bvmgr_pass_invitation_attempt_is_active($recent), 'Fresh concurrent send intent was not blocked.');
	$assert(!bvmgr_pass_invitation_attempt_is_active($stale), 'Stale uncertain send intent cannot enter reviewed retry flow.');

	$voided = (array) bvmgr_pass_claims_get_token_by_id($tokens[2]);
	$voided['status'] = 'void';
	$assert(is_wp_error(bvmgr_pass_invitation_eligibility($voided, (array) $batch)), 'Voided token was eligible.');
	$claimed = (array) bvmgr_pass_claims_get_token_by_id($tokens[2]);
	$claimed['status'] = 'claimed';
	$assert(is_wp_error(bvmgr_pass_invitation_eligibility($claimed, (array) $batch)), 'Claimed token was eligible.');
	$expired_batch = (array) $batch;
	$expired_batch['expires_at'] = wp_date('Y-m-d H:i:s', time() - HOUR_IN_SECONDS, wp_timezone());
	$assert(is_wp_error(bvmgr_pass_invitation_eligibility((array) $token, $expired_batch)), 'Expired token was eligible.');

	$assert($wpdb->insert($entry_table, array(
		'event_plan_id' => 0, 'venue_id' => 0, 'admission_kind' => 'pass', 'source' => 'pass_claim',
		'guest_name' => 'Capacity Fixture', 'guest_name_norm' => 'capacity fixture', 'party_size' => 8, 'checked_in_qty' => 0,
		'status' => 'active', 'pass_source_id' => $source_id, 'pass_batch_id' => $batch_id, 'admission_token' => '', 'created_by' => 1, 'created_at' => $now,
	)) !== false, 'Could not create capacity fixture.');
	$assert(is_wp_error(bvmgr_pass_invitation_eligibility((array) $token, (array) $batch)), 'Capacity-exhausted token was eligible.');

	wp_set_current_user(0);
	$assert(!current_user_can(bvmgr_pass_claims_capability()), 'Anonymous user unexpectedly has invitation management capability.');
	$wpdb->query('ROLLBACK');
	if ($postal_filter instanceof Closure) {
		remove_filter('backstage_outreach_postal_address', $postal_filter);
	}
	echo "PASS: Wave 2B.1 runtime fixtures (4 links; 2 invited/2 untouched; stale/wrong recipient; duplicate/concurrent; expired/claimed/voided; unauthorized; capacity; intercepted success/failure/in-doubt).\n";
} catch (Throwable $error) {
	$wpdb->query('ROLLBACK');
	if ($postal_filter instanceof Closure) {
		remove_filter('backstage_outreach_postal_address', $postal_filter);
	}
	fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
	exit(1);
}
