<?php
declare(strict_types=1);

// The sibling live tree intentionally retains its certified vms_* admission
// symbols. Test-only aliases let the same behavioral fixture certify both trees.
if (!function_exists('bvmgr_admission_table_entries') && function_exists('vms_admission_table_entries')) { function bvmgr_admission_table_entries(): string { return vms_admission_table_entries(); } }
if (!function_exists('bvmgr_admission_table_audit') && function_exists('vms_admission_table_audit')) { function bvmgr_admission_table_audit(): string { return vms_admission_table_audit(); } }
if (!function_exists('bvmgr_admission_table_pass_claims') && function_exists('vms_admission_table_pass_claims')) { function bvmgr_admission_table_pass_claims(): string { return vms_admission_table_pass_claims(); } }
if (!function_exists('bvmgr_admission_now_mysql') && function_exists('vms_admission_now_mysql')) { function bvmgr_admission_now_mysql(): string { return vms_admission_now_mysql(); } }
if (!function_exists('bvmgr_admission_public_pass_url') && function_exists('vms_admission_public_pass_url')) { function bvmgr_admission_public_pass_url(string $token): string { return vms_admission_public_pass_url($token); } }
if (!function_exists('bvmgr_admission_rest_scan') && function_exists('vms_admission_rest_scan')) { function bvmgr_admission_rest_scan(WP_REST_Request $request) { return vms_admission_rest_scan($request); } }
if (!function_exists('bvmgr_admission_rest_checkin') && function_exists('vms_admission_rest_checkin')) { function bvmgr_admission_rest_checkin(WP_REST_Request $request) { return vms_admission_rest_checkin($request); } }
if (!function_exists('bvmgr_admission_rest_uncheckin') && function_exists('vms_admission_rest_uncheckin')) { function bvmgr_admission_rest_uncheckin(WP_REST_Request $request) { return vms_admission_rest_uncheckin($request); } }
if (!function_exists('bvmgr_admission_audit_log') && function_exists('vms_admission_audit_log')) { function bvmgr_admission_audit_log(int $event_plan_id, ?int $entry_id, string $action, int $actor_user_id, string $actor_context, array $details = array()): bool { return vms_admission_audit_log($event_plan_id, $entry_id, $action, $actor_user_id, $actor_context, $details); } }

final class BVMGR_Admission_Offer_Test_Distribution_Provider implements BVMGR_Admission_Offer_Distribution_Provider_Interface
{
	public function provider_key(): string { return 'phase_b_fixture'; }
	public function validate_distribution_context(array $context): array
	{
		return array(
			'provider' => $this->provider_key(),
			'mode' => 'test_fixture',
			'identity_scope_key' => sanitize_key((string) ($context['identity_scope_key'] ?? 'fixture:default')),
		);
	}
}

/** @param callable(bool,string):void $assert @return array<string,mixed> */
function bvmgr_admission_offers_phase_b_real_certify(callable $assert): array
{
	global $wpdb;
	$tables = bvmgr_admission_offers_table_names();
	$entries = bvmgr_admission_table_entries();
	$audit = bvmgr_admission_table_audit();
	foreach (array_reverse($tables) as $table) $wpdb->query("DELETE FROM {$table}");
	$wpdb->query("DELETE FROM {$audit}");
	$wpdb->query("DELETE FROM {$entries}");
	wp_set_current_user(1);

	$venue_id = wp_insert_post(array('post_type' => 'vms_venue', 'post_status' => 'publish', 'post_title' => 'Phase B Venue'));
	$event_id = wp_insert_post(array('post_type' => 'vms_event_plan', 'post_status' => 'publish', 'post_title' => 'Phase B Event'));
	update_post_meta($event_id, '_vms_venue_id', $venue_id);
	update_post_meta($event_id, '_vms_event_date', '2026-10-15');
	update_post_meta($event_id, function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status', 'published');
	$draft_event_id = wp_insert_post(array('post_type' => 'vms_event_plan', 'post_status' => 'draft', 'post_title' => 'Draft Phase B Event'));
	update_post_meta($draft_event_id, '_vms_venue_id', $venue_id);
	update_post_meta($draft_event_id, '_vms_event_date', '2026-10-16');
	update_post_meta($draft_event_id, function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status', 'draft');

	$offer_repo = new BVMGR_Admission_Offer_Repository($wpdb);
	$eligibility_repo = new BVMGR_Admission_Offer_Eligibility_Repository($wpdb);
	$fixture = new BVMGR_Admission_Offer_Test_Distribution_Provider();
	$sequence = 0;
	$make_offer = static function (array $overrides = array(), array $rules = array()) use (&$sequence, $offer_repo, $eligibility_repo): int {
		$sequence++;
		$base = array('name' => 'Phase B Offer ' . $sequence, 'offer_type' => 'complimentary', 'status' => 'active', 'max_qty_per_claim' => 2, 'capacity_total' => 20, 'identity_policy' => array('identity_types' => array('email'), 'identity_scope' => 'offer'));
		$id = $offer_repo->create(BVMGR_Admission_Offer_Value::from_array(array_merge($base, $overrides), 'USD'), 1);
		foreach ($rules ?: array(array('scope_type' => 'any_event', 'mode' => 'include')) as $rule) $eligibility_repo->create($id, BVMGR_Admission_Offer_Eligibility_Value::from_array($rule), 1);
		return $id;
	};
	$request_no = 0;
	$request = static function (int $offer_id, int $selected_event, int $quantity, string $email, array $distribution = array()) use (&$request_no, $fixture): array {
		$request_no++;
		return array(
			'offer_id' => $offer_id, 'event_plan_id' => $selected_event, 'quantity' => $quantity,
			'idempotency_key' => 'phase-b-idempotency-' . $request_no . '-000000000000',
			'access_secret' => hash('sha256', 'phase-b-access-' . $request_no),
			'claimant_identity' => array('first_name' => 'Phase', 'last_name' => 'Guest', 'email' => $email, 'phone' => '+1 615 555 0100'),
			'distribution_context' => $fixture->validate_distribution_context($distribution), 'actor_user_id' => 1,
		);
	};
	$rejects = static function (callable $callback, string $code) use ($assert): void {
		try { $callback(); $assert(false, 'Expected rejection: ' . $code); }
		catch (BVMGR_Admission_Offer_Domain_Exception $error) { $assert($error->getMessage() === $code, 'Wrong rejection for ' . $code . ': ' . $error->getMessage()); }
	};

	$offer_id = $make_offer();
	$claim_request = $request($offer_id, $event_id, 2, 'valid@example.invalid');
	$result = (new BVMGR_Admission_Offer_Claim_Service())->claim($claim_request);
	$claim_id = (int) $result['claim']['id'];
	$assert($claim_id > 0 && $result['claim']['status'] === 'fulfilled', 'Valid complimentary claim must fulfill.');
	$assert((string) $result['claim']['access_secret_hash'] === bvmgr_admission_offer_hash_secret((string) $claim_request['access_secret']) && (string) $result['claim']['access_secret_hash'] !== (string) $claim_request['access_secret'], 'Claim access secret must be stored only as a one-way hash.');
	$assert(count($result['fulfillments']) === 2, 'Quantity two must create two fulfillment rows.');
	$entry_rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$entries} WHERE source='admission_offer' AND claim_reference LIKE %s ORDER BY id", $result['claim']['public_id'] . ':%'), ARRAY_A);
	$assert(count($entry_rows) === 2, 'Quantity two must create two native admissions.');
	$assert(count(array_unique(array_column($entry_rows, 'admission_token'))) === 2 && !in_array('', array_column($entry_rows, 'admission_token'), true), 'Every native admission unit must have a unique token.');
	$event_payloads = (string) $wpdb->get_var($wpdb->prepare("SELECT GROUP_CONCAT(COALESCE(payload_redacted,'')) FROM {$tables['events']} WHERE entity_type='claim' AND entity_id=%d", $claim_id));
	$assert(!str_contains($event_payloads, 'valid@example.invalid') && !str_contains($event_payloads, (string) $claim_request['access_secret']), 'Domain events must not contain raw identity or access secrets.');
	$pass_url = bvmgr_admission_public_pass_url((string) $entry_rows[0]['admission_token']);
	$assert(str_contains($pass_url, 'bvmgr_admission_scan_token=') || str_contains($pass_url, 'vms_admission_scan_token='), 'Native pass URL must carry the tree-authoritative scanner token.');
	$before_replay = array((int) $wpdb->get_var("SELECT COUNT(*) FROM {$entries}"), (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['fulfillments']}"), (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['reservations']}"));
	$replay = (new BVMGR_Admission_Offer_Claim_Service())->claim($claim_request);
	$after_replay = array((int) $wpdb->get_var("SELECT COUNT(*) FROM {$entries}"), (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['fulfillments']}"), (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['reservations']}"));
	$assert((int) $replay['claim']['id'] === $claim_id && $before_replay === $after_replay, 'Claim replay must not duplicate claim capacity, fulfillments, or admissions.');

	$scan = new WP_REST_Request('POST', '/vms/v1/admissions/scan');
	$scan->set_param('scan', 'vms-admission:' . $entry_rows[0]['admission_token']);
	$scan->set_param('event_plan_id', $event_id);
	$scan_data = bvmgr_admission_rest_scan($scan)->get_data();
	$assert(!empty($scan_data['ok']) && ($scan_data['data']['status'] ?? '') === 'valid', 'Native QR must pass the actual scanner validation path before check-in.');
	foreach ($entry_rows as $index => $entry) {
		$check = new WP_REST_Request('POST', '/vms/v1/admissions/' . $entry['id'] . '/checkin');
		$check->set_param('id', (int) $entry['id']); $check->set_param('qty', 1);
		$check_data = bvmgr_admission_rest_checkin($check)->get_data();
		$assert(!empty($check_data['ok']), 'Native check-in must succeed.');
		$status = (string) $wpdb->get_var($wpdb->prepare("SELECT status FROM {$tables['claims']} WHERE id=%d", $claim_id));
		$assert($status === ($index === 0 ? 'partially_used' : 'used'), 'Aggregate claim check-in state mismatch.');
		if ($index === 0) {
			update_option('vms_settings', array('vms_admission_allow_uncheckin' => 1), false);
			$uncheck = new WP_REST_Request('POST', '/vms/v1/admissions/' . $entry['id'] . '/uncheckin');
			$uncheck->set_param('id', (int) $entry['id']); $uncheck->set_param('qty', 1);
			$assert(!empty(bvmgr_admission_rest_uncheckin($uncheck)->get_data()['ok']), 'Native uncheck-in must succeed.');
			$assert((string) $wpdb->get_var($wpdb->prepare("SELECT status FROM {$tables['claims']} WHERE id=%d", $claim_id)) === 'fulfilled', 'Uncheck-in must restore the aggregate fulfilled state.');
			$assert(!empty(bvmgr_admission_rest_checkin($check)->get_data()['ok']), 'Native re-check-in must succeed.');
			$assert((string) $wpdb->get_var($wpdb->prepare("SELECT status FROM {$tables['claims']} WHERE id=%d", $claim_id)) === 'partially_used', 'Re-check-in must restore the aggregate partial state.');
		}
	}
	$revoked_checked = (new BVMGR_Admission_Offer_Lifecycle_Service())->revoke_claim($claim_id, 'certification', true, 1);
	$assert(!$revoked_checked['capacity_released'] && $revoked_checked['checked_in_history'], 'Checked-in capacity must never be released.');
	$assert((int) $wpdb->get_var($wpdb->prepare("SELECT SUM(checked_in_qty) FROM {$entries} WHERE source='admission_offer' AND claim_reference LIKE %s", $result['claim']['public_id'] . ':%')) === 2, 'Revocation must retain checked-in attendance history.');
	$scan_revoked = bvmgr_admission_rest_scan($scan)->get_data();
	$assert(empty($scan_revoked['ok']) && ($scan_revoked['error']['code'] ?? '') === 'voided', 'Revoked native credential must be rejected by the scanner.');

	$percent_offer = $make_offer(array('offer_type' => 'percent', 'percent_basis_points' => 5000));
	$rejects(static fn() => (new BVMGR_Admission_Offer_Claim_Service())->claim($request($percent_offer, $event_id, 1, 'percent@example.invalid')), 'complimentary_offer_required');
	$fixed_offer = $make_offer(array('offer_type' => 'fixed', 'fixed_amount_minor' => 500, 'currency' => 'USD'));
	$rejects(static fn() => (new BVMGR_Admission_Offer_Claim_Service())->claim($request($fixed_offer, $event_id, 1, 'fixed@example.invalid')), 'complimentary_offer_required');
	$inactive = $make_offer(array('status' => 'paused'));
	$rejects(static fn() => (new BVMGR_Admission_Offer_Claim_Service())->claim($request($inactive, $event_id, 1, 'inactive@example.invalid')), 'offer_not_claimable');
	$expired = $make_offer(array('claim_expires_at' => '2020-01-01 00:00:00'));
	$rejects(static fn() => (new BVMGR_Admission_Offer_Claim_Service())->claim($request($expired, $event_id, 1, 'expired@example.invalid')), 'offer_claim_expired');
	$invalid_event_offer = $make_offer();
	$rejects(static fn() => (new BVMGR_Admission_Offer_Claim_Service())->claim($request($invalid_event_offer, $draft_event_id, 1, 'draft@example.invalid')), 'event_not_claimable');
	$excluded = $make_offer(array(), array(array('scope_type' => 'any_event', 'mode' => 'include'), array('scope_type' => 'event_plan', 'mode' => 'exclude', 'event_plan_id' => $event_id)));
	$rejects(static fn() => (new BVMGR_Admission_Offer_Claim_Service())->claim($request($excluded, $event_id, 1, 'excluded@example.invalid')), 'event_excluded');
	$venue_offer = $make_offer(array(), array(array('scope_type' => 'venue', 'mode' => 'include', 'venue_id' => $venue_id)));
	$assert((new BVMGR_Admission_Offer_Claim_Service())->claim($request($venue_offer, $event_id, 1, 'venue@example.invalid'))['claim']['status'] === 'fulfilled', 'Venue eligibility must match.');
	$date_offer = $make_offer(array(), array(array('scope_type' => 'date_window', 'mode' => 'include', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31')));
	$assert((new BVMGR_Admission_Offer_Claim_Service())->claim($request($date_offer, $event_id, 1, 'date@example.invalid'))['claim']['status'] === 'fulfilled', 'Date-window eligibility must match.');
	$season_filter = static fn(array $keys): array => array_merge($keys, array('fall-2026'));
	add_filter('bvmgr_admission_offer_event_season_keys', $season_filter, 10, 1);
	$season_offer = $make_offer(array(), array(array('scope_type' => 'season', 'mode' => 'include', 'season_key' => 'fall-2026')));
	$assert((new BVMGR_Admission_Offer_Claim_Service())->claim($request($season_offer, $event_id, 1, 'season@example.invalid'))['claim']['status'] === 'fulfilled', 'Explicit season-key eligibility must match.');
	remove_filter('bvmgr_admission_offer_event_season_keys', $season_filter, 10);
	$quantity_offer = $make_offer(array('max_qty_per_claim' => 1));
	$rejects(static fn() => (new BVMGR_Admission_Offer_Claim_Service())->claim($request($quantity_offer, $event_id, 2, 'quantity@example.invalid')), 'claim_quantity_exceeds_offer_limit');

	$identity_offer = $make_offer();
	(new BVMGR_Admission_Offer_Claim_Service())->claim($request($identity_offer, $event_id, 1, 'duplicate@example.invalid'));
	$duplicate_before = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['claims']} WHERE offer_id=%d", $identity_offer));
	$rejects(static fn() => (new BVMGR_Admission_Offer_Claim_Service())->claim($request($identity_offer, $event_id, 1, 'duplicate@example.invalid')), 'duplicate_or_invalid_scoped_identity');
	$assert((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['claims']} WHERE offer_id=%d", $identity_offer)) === $duplicate_before, 'Identity failure must roll back its partial Claim.');
	$scoped_offer = $make_offer(array('identity_policy' => array('identity_types' => array('email'), 'identity_scope' => 'distribution')));
	(new BVMGR_Admission_Offer_Claim_Service())->claim($request($scoped_offer, $event_id, 1, 'scoped@example.invalid', array('identity_scope_key' => 'fixture:one')));
	$assert((new BVMGR_Admission_Offer_Claim_Service())->claim($request($scoped_offer, $event_id, 1, 'scoped@example.invalid', array('identity_scope_key' => 'fixture:two')))['claim']['status'] === 'fulfilled', 'Same identity must be allowed in a different configured scope.');

	$capacity_offer = $make_offer(array('capacity_total' => 2));
	(new BVMGR_Admission_Offer_Claim_Service())->claim($request($capacity_offer, $event_id, 2, 'capacity@example.invalid'));
	$rejects(static fn() => (new BVMGR_Admission_Offer_Claim_Service())->claim($request($capacity_offer, $event_id, 1, 'oversub@example.invalid')), 'offer_capacity_exhausted');
	$assert((int) $wpdb->get_var($wpdb->prepare("SELECT SUM(quantity) FROM {$tables['reservations']} WHERE offer_id=%d AND state='consumed'", $capacity_offer)) === 2, 'Exact capacity must be consumed once.');

	$release_offer = $make_offer(array('capacity_total' => 1));
	$release_result = (new BVMGR_Admission_Offer_Claim_Service())->claim($request($release_offer, $event_id, 1, 'release@example.invalid'));
	$release_claim_id = (int) $release_result['claim']['id'];
	$release = (new BVMGR_Admission_Offer_Lifecycle_Service())->revoke_claim($release_claim_id, 'release before check-in', true, 1);
	$assert($release['capacity_released'] && (string) $wpdb->get_var($wpdb->prepare("SELECT state FROM {$tables['reservations']} WHERE claim_id=%d", $release_claim_id)) === 'released', 'Explicit pre-check-in revocation must release capacity.');
	$assert((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['identities']} WHERE claim_id=%d", $release_claim_id)) > 0, 'Revocation must retain identity enforcement history.');
	wp_set_current_user(0);
	$rejects(static fn() => (new BVMGR_Admission_Offer_Lifecycle_Service())->revoke_claim($release_claim_id, 'unauthorized retry', false), 'revocation_forbidden');
	wp_set_current_user(1);
	$unit_offer = $make_offer(array('capacity_total' => 1));
	$unit_result = (new BVMGR_Admission_Offer_Claim_Service())->claim($request($unit_offer, $event_id, 1, 'unit-revoke@example.invalid'));
	$unit_id = (int) $unit_result['fulfillments'][0]['id'];
	$unit_revoke = (new BVMGR_Admission_Offer_Lifecycle_Service())->revoke_fulfillment($unit_id, 'unit certification', 1);
	$assert($unit_revoke['revoked'] && (string) $wpdb->get_var($wpdb->prepare("SELECT state FROM {$tables['fulfillments']} WHERE id=%d", $unit_id)) === 'revoked', 'Individual fulfillment revocation must project to the unit state.');
	$assert((string) $wpdb->get_var($wpdb->prepare("SELECT state FROM {$tables['reservations']} WHERE claim_id=%d", (int) $unit_result['claim']['id'])) === 'consumed', 'Individual-unit revocation must not release claim capacity.');

	$interrupt_points = array('after_claim_creation', 'after_admission_create', 'after_fulfillment_link', 'after_unit_commit', 'after_final_state');
	$recovery = array();
	foreach ($interrupt_points as $point) {
		$interrupted_offer = $make_offer();
		$interrupted_request = $request($interrupted_offer, $event_id, 2, $point . '@example.invalid');
		$fired = false;
		$inject = static function (string $seen, array $context) use ($point, &$fired): void {
			if (!$fired && $seen === $point && ($point !== 'after_unit_commit' || (int) ($context['unit'] ?? 0) === 1)) { $fired = true; throw new BVMGR_Admission_Offer_Domain_Exception('injected_' . $point); }
		};
		try { (new BVMGR_Admission_Offer_Claim_Service($wpdb, null, $inject))->claim($interrupted_request); }
		catch (BVMGR_Admission_Offer_Domain_Exception $error) { $assert($error->getMessage() === 'injected_' . $point, 'Expected injected interruption at ' . $point); }
		$assert($fired, 'Interruption point was not reached: ' . $point);
		$recovered = (new BVMGR_Admission_Offer_Claim_Service())->claim($interrupted_request);
		$recovered_id = (int) $recovered['claim']['id'];
		$admission_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$entries} WHERE source='admission_offer' AND claim_reference LIKE %s", $recovered['claim']['public_id'] . ':%'));
		$fulfillment_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['fulfillments']} WHERE claim_id=%d", $recovered_id));
		$assert($recovered['claim']['status'] === 'fulfilled' && $admission_count === 2 && $fulfillment_count === 2, 'Interrupted claim must recover without duplicate units: ' . $point);
		$recovery[$point] = array('claim_id' => $recovered_id, 'admissions' => $admission_count, 'fulfillments' => $fulfillment_count);
	}

	$legacy_entry = array('event_plan_id' => $event_id, 'venue_id' => $venue_id, 'admission_kind' => 'comp', 'source' => 'operator', 'guest_name' => 'Legacy Guest', 'guest_name_norm' => 'legacy guest', 'party_size' => 1, 'checked_in_qty' => 0, 'status' => 'active', 'created_by' => 1, 'created_at' => bvmgr_admission_now_mysql());
	$wpdb->insert($entries, $legacy_entry, array('%d','%d','%s','%s','%s','%s','%d','%d','%s','%d','%s'));
	$legacy_id = (int) $wpdb->insert_id;
	$legacy_claim_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['claims']}");
	bvmgr_admission_audit_log($event_id, $legacy_id, 'checkin', 1, 'door', array('qty' => 1));
	$assert((int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['claims']}") === $legacy_claim_count, 'Legacy admission audit must not enter Offer lifecycle.');
	$assert((int) $wpdb->get_var('SELECT COUNT(*) FROM ' . bvmgr_admission_table_pass_claims()) === 0, 'Phase B must not write legacy Guest Pass claims.');
	$post_types = $wpdb->get_col("SELECT DISTINCT post_type FROM {$wpdb->posts} WHERE post_type IN ('shop_order','shop_order_placehold','tribe_wooticket')");
	$assert($post_types === array(), 'Phase B must not create Woo orders or TEC attendees.');

	return array('claim_id' => $claim_id, 'event_plan_id' => $event_id, 'venue_id' => $venue_id, 'recovery' => $recovery, 'runtime_tables_unchanged' => count($tables) === 9);
}
