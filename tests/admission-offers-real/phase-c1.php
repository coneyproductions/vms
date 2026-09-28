<?php
declare(strict_types=1);

/** @param callable(bool,string):void $assert @return array<string,mixed> */
function bvmgr_admission_offers_phase_c1_real_certify(callable $assert, string $wordpress_root): array
{
	global $wpdb;
	$tables = bvmgr_admission_offers_table_names();
	foreach (array_reverse($tables) as $table) $wpdb->query("DELETE FROM {$table}");
	wp_set_current_user(1);
	update_option('woocommerce_currency', 'USD', false);

	$entries = function_exists('bvmgr_admission_table_entries') ? bvmgr_admission_table_entries() : '';
	$pass_claims = function_exists('bvmgr_admission_table_pass_claims') ? bvmgr_admission_table_pass_claims() : '';
	$side_effect_snapshot = static function () use ($wpdb, $tables, $entries, $pass_claims): array {
		$table_counts = static function (string $pattern) use ($wpdb): array {
			$result = array();
			foreach ((array) $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $pattern)) as $table) {
				$result[(string) $table] = (int) $wpdb->get_var('SELECT COUNT(*) FROM `' . str_replace('`', '``', (string) $table) . '`');
			}
			ksort($result);
			return $result;
		};
		return array(
			'checkouts' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['checkouts']}"),
			'allocations' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['allocations']}"),
			'fulfillments' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['fulfillments']}"),
			'admissions' => $entries !== '' ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$entries}") : 0,
			'legacy_pass_claims' => $pass_claims !== '' ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$pass_claims}") : 0,
			'commerce_posts' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('shop_order','shop_order_placehold','tribe_wooticket')"),
			'woocommerce_sessions' => $table_counts('%woocommerce_sessions%'),
			'coupons' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='shop_coupon'"),
			'product_prices' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN ('_price','_regular_price','_sale_price')"),
			'outreach_tables' => $table_counts('%outreach%'),
			'outreach_options' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", '%outreach%')),
			'discount_tables' => $table_counts('%discount%'),
			'discount_options' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", '%discount%')),
			'square_tables' => $table_counts('%square%'),
			'square_options' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", '%square%')),
		);
	};
	$before_side_effects = $side_effect_snapshot();

	$venue_id = wp_insert_post(array('post_type' => 'vms_venue', 'post_status' => 'publish', 'post_title' => 'Phase C1 Venue'));
	$other_venue_id = wp_insert_post(array('post_type' => 'vms_venue', 'post_status' => 'publish', 'post_title' => 'Phase C1 Other Venue'));
	$event_id = wp_insert_post(array('post_type' => 'vms_event_plan', 'post_status' => 'publish', 'post_title' => 'Phase C1 Event'));
	update_post_meta($event_id, '_vms_venue_id', $venue_id);
	update_post_meta($event_id, '_vms_event_date', '2026-10-15');
	update_post_meta($event_id, function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status', 'published');
	$draft_event_id = wp_insert_post(array('post_type' => 'vms_event_plan', 'post_status' => 'draft', 'post_title' => 'Phase C1 Draft Event'));
	update_post_meta($draft_event_id, '_vms_venue_id', $venue_id);
	update_post_meta($draft_event_id, '_vms_event_date', '2026-10-16');
	update_post_meta($draft_event_id, function_exists('bvmgr_meta_key') ? bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status', 'published');

	$offer_repo = new BVMGR_Admission_Offer_Repository($wpdb);
	$eligibility_repo = new BVMGR_Admission_Offer_Eligibility_Repository($wpdb);
	$offer_sequence = 0;
	$make_offer = static function (array $overrides = array(), array $rules = array()) use (&$offer_sequence, $offer_repo, $eligibility_repo): int {
		$offer_sequence++;
		$base = array(
			'name' => 'Phase C1 Offer ' . $offer_sequence,
			'offer_type' => 'percent',
			'percent_basis_points' => 2500,
			'status' => 'active',
			'max_qty_per_claim' => 2,
			'capacity_total' => 20,
			'reservation_ttl_seconds' => 1200,
			'identity_policy' => array('identity_types' => array('email'), 'identity_scope' => 'offer'),
		);
		$input = array_merge($base, $overrides);
		if (($input['offer_type'] ?? '') === 'fixed') {
			$input['percent_basis_points'] = null;
			$input['fixed_amount_minor'] = $input['fixed_amount_minor'] ?? 500;
			$input['currency'] = $input['currency'] ?? 'USD';
		} elseif (($input['offer_type'] ?? '') === 'complimentary') {
			$input['percent_basis_points'] = null;
			$input['fixed_amount_minor'] = null;
			$input['currency'] = null;
		}
		$id = $offer_repo->create(BVMGR_Admission_Offer_Value::from_array($input, 'USD'), 1, '2026-09-28 12:00:00');
		foreach ($rules ?: array(array('scope_type' => 'any_event', 'mode' => 'include')) as $rule) {
			$eligibility_repo->create($id, BVMGR_Admission_Offer_Eligibility_Value::from_array($rule), 1, '2026-09-28 12:00:00');
		}
		return $id;
	};
	$request_sequence = 0;
	$request = static function (int $offer_id, int $selected_event, int $quantity, string $email, array $distribution = array()) use (&$request_sequence): array {
		$request_sequence++;
		return array(
			'offer_id' => $offer_id, 'event_plan_id' => $selected_event, 'quantity' => $quantity,
			'idempotency_key' => 'phase-c1-idempotency-' . $request_sequence . '-000000000000',
			'access_secret' => hash('sha256', 'phase-c1-access-' . $request_sequence),
			'claimant_identity' => array('first_name' => 'Paid', 'last_name' => 'Guest', 'email' => $email, 'phone' => '+1 615 555 0100'),
			'distribution_context' => array_merge(array('provider' => 'phase_c1_fixture', 'mode' => 'test_fixture'), $distribution),
			'actor_user_id' => 1,
		);
	};
	$rejects = static function (callable $callback, string $code) use ($assert): void {
		try { $callback(); $assert(false, 'Expected rejection: ' . $code); }
		catch (BVMGR_Admission_Offer_Domain_Exception $error) { $assert($error->getMessage() === $code, 'Wrong rejection for ' . $code . ': ' . $error->getMessage()); }
	};
	$now = '2026-09-28 12:00:00';
	$service = static function () use (&$now, $wpdb): BVMGR_Admission_Offer_Paid_Claim_Service {
		return new BVMGR_Admission_Offer_Paid_Claim_Service($wpdb, static fn(): string => $now, static function (): void {}, static fn(): bool => true, null, 'USD');
	};

	$percent_offer = $make_offer();
	$percent_request = $request($percent_offer, $event_id, 2, 'percent-c1@example.invalid');
	$percent = $service()->claim($percent_request);
	$assert($percent['claim']['status'] === 'claimed' && $percent['reservation']['state'] === 'held', 'Percent Offer must end at claimed plus held.');
	$assert($percent['reservation']['expires_at'] === '2026-09-28 12:20:00', 'Initial paid hold must expire exactly 20 minutes after acquisition.');
	$assert((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['reservations']} WHERE claim_id=%d", (int) $percent['claim']['id'])) === 1, 'Paid Claim must create exactly one Reservation.');
	$fixed_offer = $make_offer(array('offer_type' => 'fixed', 'fixed_amount_minor' => 500, 'currency' => 'USD'));
	$fixed = $service()->claim($request($fixed_offer, $event_id, 1, 'fixed-c1@example.invalid'));
	$assert($fixed['claim']['status'] === 'claimed' && $fixed['reservation']['state'] === 'held', 'Fixed Offer must end at claimed plus held.');

	$complimentary = $make_offer(array('offer_type' => 'complimentary'));
	$rejects(static fn() => $service()->claim($request($complimentary, $event_id, 1, 'comp-c1@example.invalid')), 'paid_offer_required');
	$inactive = $make_offer(array('status' => 'paused'));
	$rejects(static fn() => $service()->claim($request($inactive, $event_id, 1, 'inactive-c1@example.invalid')), 'offer_not_claimable');
	$expired_offer = $make_offer(array('claim_expires_at' => '2026-09-28 11:59:59'));
	$rejects(static fn() => $service()->claim($request($expired_offer, $event_id, 1, 'expired-c1@example.invalid')), 'offer_claim_expired');
	$invalid_event = $make_offer();
	$rejects(static fn() => $service()->claim($request($invalid_event, $draft_event_id, 1, 'draft-c1@example.invalid')), 'event_not_claimable');
	$excluded = $make_offer(array(), array(array('scope_type' => 'any_event', 'mode' => 'include'), array('scope_type' => 'event_plan', 'mode' => 'exclude', 'event_plan_id' => $event_id)));
	$rejects(static fn() => $service()->claim($request($excluded, $event_id, 1, 'excluded-c1@example.invalid')), 'event_excluded');
	$venue_mismatch = $make_offer(array(), array(array('scope_type' => 'venue', 'mode' => 'include', 'venue_id' => $other_venue_id)));
	$rejects(static fn() => $service()->claim($request($venue_mismatch, $event_id, 1, 'venue-c1@example.invalid')), 'event_not_eligible');
	$date_mismatch = $make_offer(array(), array(array('scope_type' => 'date_window', 'mode' => 'include', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30')));
	$rejects(static fn() => $service()->claim($request($date_mismatch, $event_id, 1, 'date-c1@example.invalid')), 'event_not_eligible');
	$season_mismatch = $make_offer(array(), array(array('scope_type' => 'season', 'mode' => 'include', 'season_key' => 'winter-2026')));
	$rejects(static fn() => $service()->claim($request($season_mismatch, $event_id, 1, 'season-c1@example.invalid')), 'event_not_eligible');
	$quantity_offer = $make_offer(array('max_qty_per_claim' => 1));
	$rejects(static fn() => $service()->claim($request($quantity_offer, $event_id, 2, 'quantity-c1@example.invalid')), 'claim_quantity_exceeds_offer_limit');
	$malformed_quantity = $request($quantity_offer, $event_id, 1, 'malformed-quantity-c1@example.invalid'); $malformed_quantity['quantity'] = 1.5;
	$rejects(static fn() => $service()->claim($malformed_quantity), 'invalid_paid_claim_request');

	$identity_offer = $make_offer();
	$service()->claim($request($identity_offer, $event_id, 1, 'duplicate-c1@example.invalid'));
	$rejects(static fn() => $service()->claim($request($identity_offer, $event_id, 1, 'duplicate-c1@example.invalid')), 'duplicate_or_invalid_scoped_identity');
	$scoped_offer = $make_offer(array('identity_policy' => array('identity_types' => array('email'), 'identity_scope' => 'distribution')));
	$service()->claim($request($scoped_offer, $event_id, 1, 'scoped-c1@example.invalid', array('identity_scope_key' => 'fixture:one')));
	$scoped = $service()->claim($request($scoped_offer, $event_id, 1, 'scoped-c1@example.invalid', array('identity_scope_key' => 'fixture:two')));
	$assert($scoped['claim']['status'] === 'claimed', 'Same identity must be accepted in a distinct approved scope.');

	$before_replay = array(
		'claims' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['claims']} WHERE offer_id=%d", $percent_offer)),
		'identities' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['identities']} WHERE offer_id=%d", $percent_offer)),
		'reservations' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['reservations']} WHERE offer_id=%d", $percent_offer)),
		'expires_at' => $percent['reservation']['expires_at'], 'state_version' => (int) $percent['reservation']['state_version'],
	);
	$now = '2026-09-28 12:05:00';
	$replay = $service()->claim($percent_request);
	$after_replay = array(
		'claims' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['claims']} WHERE offer_id=%d", $percent_offer)),
		'identities' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['identities']} WHERE offer_id=%d", $percent_offer)),
		'reservations' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['reservations']} WHERE offer_id=%d", $percent_offer)),
		'expires_at' => $replay['reservation']['expires_at'], 'state_version' => (int) $replay['reservation']['state_version'],
	);
	$assert((int) $replay['claim']['id'] === (int) $percent['claim']['id'] && (int) $replay['reservation']['id'] === (int) $percent['reservation']['id'], 'Replay must return the existing Claim and Reservation.');
	$assert($before_replay === $after_replay, 'Replay must not write rows, consume capacity, or renew TTL.');
	$conflict = $percent_request; $conflict['quantity'] = 1;
	$rejects(static fn() => $service()->claim($conflict), 'idempotency_key_conflict');

	$renew_offer = $make_offer();
	$now = '2026-09-28 12:00:00';
	$renewed = $service()->claim($request($renew_offer, $event_id, 1, 'renew-c1@example.invalid'));
	$renew_claim_id = (int) $renewed['claim']['id']; $renew_id = (int) $renewed['reservation']['id'];
	$initial_version = (int) $renewed['reservation']['state_version'];
	$initial_activation = $service()->renew_reservation($renew_offer, $renew_claim_id, $renew_id, 'validated_initial_activation');
	$assert($initial_activation['expires_at'] === '2026-09-28 12:20:00' && (int) $initial_activation['state_version'] === $initial_version, 'Validated initial activation must retain the acquisition-time soft TTL when it cannot extend it.');
	$now = '2026-09-28 12:05:00';
	$read_only = $service()->renew_reservation($renew_offer, $renew_claim_id, $renew_id, 'page_refresh');
	$assert($read_only['expires_at'] === '2026-09-28 12:20:00' && (int) $read_only['state_version'] === $initial_version, 'Page refresh must not renew.');
	$invalid_change = $service()->renew_reservation($renew_offer, $renew_claim_id, $renew_id, 'invalid_change');
	$assert($invalid_change['expires_at'] === '2026-09-28 12:20:00' && (int) $invalid_change['state_version'] === $initial_version, 'Invalid activity must not renew.');
	foreach (array('session_restored', 'totals_recalculated', 'background_read', 'replay') as $non_semantic_activity) {
		$non_semantic = $service()->renew_reservation($renew_offer, $renew_claim_id, $renew_id, $non_semantic_activity);
		$assert($non_semantic['expires_at'] === '2026-09-28 12:20:00' && (int) $non_semantic['state_version'] === $initial_version, $non_semantic_activity . ' must not renew.');
	}
	$now = '2026-09-28 12:15:00';
	$renewed = $service()->renew_reservation($renew_offer, $renew_claim_id, $renew_id, 'eligible_item_added');
	$assert($renewed['expires_at'] === '2026-09-28 12:35:00', 'Semantic activity must renew to now plus 20 minutes.');
	$now = '2026-09-28 12:30:00';
	$renewed = $service()->renew_reservation($renew_offer, $renew_claim_id, $renew_id, 'place_order_attempted');
	$assert($renewed['expires_at'] === '2026-09-28 12:45:00', 'Renewal must stop at the immutable 45-minute ceiling.');
	$now = '2026-09-28 12:40:00';
	$ceiling = $service()->renew_reservation($renew_offer, $renew_claim_id, $renew_id, 'checkout_entered');
	$assert($ceiling['expires_at'] === '2026-09-28 12:45:00', 'Later activity must never move the hard ceiling.');

	$now = '2026-09-28 12:00:00';
	$offer_cap_id = $make_offer(array('claim_expires_at' => '2026-09-28 12:25:00'));
	$offer_cap = $service()->claim($request($offer_cap_id, $event_id, 1, 'offer-cap-c1@example.invalid'));
	$now = '2026-09-28 12:10:00';
	$offer_capped = $service()->renew_reservation($offer_cap_id, (int) $offer_cap['claim']['id'], (int) $offer_cap['reservation']['id'], 'eligible_quantity_changed');
	$assert($offer_capped['expires_at'] === '2026-09-28 12:25:00', 'Offer expiry must cap a semantic renewal.');
	$now = '2026-09-28 12:00:00';
	$claim_cap_id = $make_offer();
	$claim_cap = $service()->claim($request($claim_cap_id, $event_id, 1, 'claim-cap-c1@example.invalid'));
	$wpdb->update($tables['claims'], array('expires_at' => '2026-09-28 12:23:00'), array('id' => (int) $claim_cap['claim']['id']), array('%s'), array('%d'));
	$now = '2026-09-28 12:10:00';
	$claim_capped = $service()->renew_reservation($claim_cap_id, (int) $claim_cap['claim']['id'], (int) $claim_cap['reservation']['id'], 'eligible_quantity_changed');
	$assert($claim_capped['expires_at'] === '2026-09-28 12:23:00', 'Claim expiry must independently cap a semantic renewal.');

	$now = '2026-09-28 12:00:00';
	$stale_offer = $make_offer(array('capacity_total' => 1, 'max_qty_per_claim' => 1));
	$stale_request = $request($stale_offer, $event_id, 1, 'stale-c1@example.invalid');
	$stale = $service()->claim($stale_request);
	$now = '2026-09-28 12:21:00';
	$fresh = $service()->claim($request($stale_offer, $event_id, 1, 'fresh-c1@example.invalid'));
	$stale_state = (string) $wpdb->get_var($wpdb->prepare("SELECT state FROM {$tables['reservations']} WHERE id=%d", (int) $stale['reservation']['id']));
	$assert($stale_state === 'expired' && $fresh['reservation']['state'] === 'held', 'Lazy expiry must preserve history and atomically reuse capacity.');
	$expired_replay = $service()->claim($stale_request);
	$assert($expired_replay['reservation']['state'] === 'expired', 'Replay must never revive an expired Reservation.');
	$assert((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['events']} WHERE entity_type='reservation' AND entity_id=%d AND event_key='paid-reservation-expired'", (int) $stale['reservation']['id'])) === 1, 'Lazy expiry must append one deterministic event.');

	$now = '2026-09-28 12:00:00';
	$release_offer = $make_offer(array('capacity_total' => 1, 'max_qty_per_claim' => 1));
	$release = $service()->claim($request($release_offer, $event_id, 1, 'release-c1@example.invalid'));
	$authority = array('actor_type' => 'distribution_provider', 'provider' => 'phase_c1_fixture', 'actor_user_id' => 1);
	$released = $service()->release_reservation($release_offer, (int) $release['claim']['id'], (int) $release['reservation']['id'], 'claimant_abandoned', $authority);
	$released_again = $service()->release_reservation($release_offer, (int) $release['claim']['id'], (int) $release['reservation']['id'], 'duplicate_request', $authority);
	$assert($released['state'] === 'released' && (int) $released_again['state_version'] === (int) $released['state_version'], 'Explicit release must be idempotent.');
	$release_payload = (string) $wpdb->get_var($wpdb->prepare("SELECT payload_redacted FROM {$tables['events']} WHERE entity_type='reservation' AND entity_id=%d AND event_key='paid-reservation-released'", (int) $release['reservation']['id']));
	$assert(str_contains($release_payload, 'claimant_abandoned') && !str_contains($release_payload, 'duplicate_request'), 'Release event must retain the original reason and remain immutable on retry.');
	$assert((string) $wpdb->get_var($wpdb->prepare("SELECT status FROM {$tables['claims']} WHERE id=%d", (int) $release['claim']['id'])) === 'claimed', 'Release must retain the durable recoverable Claim.');
	$replacement = $service()->claim($request($release_offer, $event_id, 1, 'replacement-c1@example.invalid'));
	$assert($replacement['reservation']['state'] === 'held', 'Released capacity must be reusable.');
	$denied = new BVMGR_Admission_Offer_Paid_Claim_Service($wpdb, static fn(): string => $now, static function (): void {}, static fn(): bool => false, null, 'USD');
	$rejects(static fn() => $denied->release_reservation($release_offer, (int) $replacement['claim']['id'], (int) $replacement['reservation']['id'], 'unauthorized', $authority), 'reservation_release_forbidden');

	$malformed_percent = $make_offer();
	$wpdb->update($tables['offers'], array('percent_basis_points' => 10000), array('id' => $malformed_percent), array('%d'), array('%d'));
	$rejects(static fn() => $service()->claim($request($malformed_percent, $event_id, 1, 'malformed-c1@example.invalid')), 'invalid_percent_value');
	$currency_offer = $make_offer(array('offer_type' => 'fixed', 'fixed_amount_minor' => 500, 'currency' => 'USD'));
	$cad_service = new BVMGR_Admission_Offer_Paid_Claim_Service($wpdb, static fn(): string => $now, static function (): void {}, static fn(): bool => true, null, 'CAD');
	$rejects(static fn() => $cad_service->claim($request($currency_offer, $event_id, 1, 'currency-c1@example.invalid')), 'fixed_currency_must_match_store_currency');

	$rollback_offer = $make_offer();
	$rollback_request = $request($rollback_offer, $event_id, 1, 'rollback-c1@example.invalid');
	$rollback_events_before = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['events']}");
	$injected = new BVMGR_Admission_Offer_Paid_Claim_Service($wpdb, static fn(): string => $now, static function (): void {}, static fn(): bool => true, static function (string $point): void {
		if ($point === 'after_reservation_insert') throw new BVMGR_Admission_Offer_Domain_Exception('injected_after_reservation_insert');
	}, 'USD');
	$rejects(static fn() => $injected->claim($rollback_request), 'injected_after_reservation_insert');
	$assert((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['claims']} WHERE offer_id=%d", $rollback_offer)) === 0
		&& (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['identities']} WHERE offer_id=%d", $rollback_offer)) === 0
		&& (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['reservations']} WHERE offer_id=%d", $rollback_offer)) === 0
		&& (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['events']}") === $rollback_events_before,
		'Injected failure must roll back Claim, identity, Reservation, and event state together.');
	$assert($service()->claim($rollback_request)['reservation']['state'] === 'held', 'Retry after rollback must acquire normally.');
	$paid_event_payloads = (string) $wpdb->get_var($wpdb->prepare("SELECT GROUP_CONCAT(COALESCE(payload_redacted,'')) FROM {$tables['events']} WHERE entity_type='claim' AND entity_id=%d", (int) $percent['claim']['id']));
	$assert(!str_contains($paid_event_payloads, 'percent-c1@example.invalid') && !str_contains($paid_event_payloads, (string) $percent_request['access_secret']), 'Paid domain events must not contain raw identity or access secrets.');

	$transition_offer = $make_offer();
	$transition = $service()->claim($request($transition_offer, $event_id, 1, 'transition-c1@example.invalid'));
	$assert(bvmgr_admission_offer_transition_entity($wpdb, 'claims', (int) $transition['claim']['id'], 'claimed', 'canceled', 1, 1, $now), 'Real generic Claim transition must write the status column.');
	$assert((string) $wpdb->get_var($wpdb->prepare("SELECT status FROM {$tables['claims']} WHERE id=%d", (int) $transition['claim']['id'])) === 'canceled', 'Real Claim transition must persist status.');

	$clear_offers = static function () use ($wpdb, $tables): void { foreach (array_reverse($tables) as $table) $wpdb->query("DELETE FROM {$table}"); };
	$worker = __DIR__ . '/worker.php';
	$start_worker = static function (array $payload) use ($worker, $wordpress_root): array {
		$command = array(PHP_BINARY, $worker, $wordpress_root, base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)));
		$pipes = array();
		$process = proc_open($command, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
		if (!is_resource($process)) throw new RuntimeException('paid_worker_start_failed');
		fclose($pipes[0]);
		return array($process, $pipes);
	};
	$finish_worker = static function (array $handle): array {
		[$process, $pipes] = $handle;
		$stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($process);
		if ($code !== 0 || $stdout === '') throw new RuntimeException('paid_worker_failed:' . $code . ':' . $stderr);
		return json_decode($stdout, true, 32, JSON_THROW_ON_ERROR);
	};
	$wait_marker = static function (string $marker): void {
		$deadline = microtime(true) + 10;
		while (!is_file($marker)) { if (microtime(true) > $deadline) throw new RuntimeException('paid_marker_timeout'); usleep(20000); }
	};
	$run_race = static function (int $capacity, int $a_qty, int $b_qty, bool $same_request, bool $seed_stale, array $expected, string $label) use ($clear_offers, $make_offer, $request, $event_id, $start_worker, $finish_worker, $wait_marker, $assert, $wpdb, $tables): array {
		$clear_offers();
		$offer_id = $make_offer(array('capacity_total' => $capacity, 'max_qty_per_claim' => max($a_qty, $b_qty, 1)));
		if ($seed_stale) {
			$wpdb->insert($tables['reservations'], array(
				'public_id' => bvmgr_admission_offer_generate_public_id('ar'), 'offer_id' => $offer_id, 'claim_id' => 999999,
				'event_plan_id' => $event_id, 'quantity' => $capacity, 'state' => 'held',
				'idempotency_key_hash' => bvmgr_admission_offer_idempotency_hash('stale-race-0000000000'),
				'expires_at' => '2026-09-28 11:59:59', 'state_version' => 1, 'created_at' => '2026-09-28 11:30:00',
			), array('%s','%d','%d','%d','%d','%s','%s','%s','%d','%s'));
		}
		$request_a = $request($offer_id, $event_id, $a_qty, $label . '-a@example.invalid');
		$request_b = $same_request ? $request_a : $request($offer_id, $event_id, $b_qty, $label . '-b@example.invalid');
		$marker = sys_get_temp_dir() . '/bvm-ao-c1-' . bin2hex(random_bytes(6)); $release_file = $marker . '.release';
		$base = array('mode' => 'paid_claim', 'now' => '2026-09-28 12:00:00');
		$a = $start_worker($base + array('request' => $request_a, 'marker' => $marker, 'release' => $release_file));
		$wait_marker($marker);
		$b = $start_worker($base + array('request' => $request_b));
		usleep(400000);
		$assert(proc_get_status($b[0])['running'], $label . ': second paid session must genuinely wait for the Offer lock.');
		file_put_contents($release_file, 'release');
		$result_a = $finish_worker($a); $result_b = $finish_worker($b);
		@unlink($marker); @unlink($release_file);
		$assert($result_a['connection_id'] !== $result_b['connection_id'], $label . ': contenders must use distinct MySQL sessions.');
		$assert($result_a['ok'] === $expected[0] && $result_b['ok'] === $expected[1], $label . ': outcomes mismatch.');
		$counts = array(
			'claims' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['claims']} WHERE offer_id=%d", $offer_id)),
			'reservations' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['reservations']} WHERE offer_id=%d AND state='held'", $offer_id)),
			'held_quantity' => (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(quantity),0) FROM {$tables['reservations']} WHERE offer_id=%d AND state='held'", $offer_id)),
			'expired' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables['reservations']} WHERE offer_id=%d AND state='expired'", $offer_id)),
		);
		return array('a' => $result_a, 'b' => $result_b, 'counts' => $counts);
	};

	$oversubscription = $run_race(2, 2, 1, false, false, array(true, false), 'paid-oversubscription');
	$assert(($oversubscription['b']['error'] ?? '') === 'offer_capacity_exhausted' && $oversubscription['counts']['held_quantity'] === 2, 'Capacity two race must allow only quantity two.');
	$fitting = $run_race(3, 2, 1, false, false, array(true, true), 'paid-fitting');
	$assert($fitting['counts']['claims'] === 2 && $fitting['counts']['held_quantity'] === 3, 'Fitting concurrent quantities must both commit.');
	$idempotent = $run_race(2, 1, 1, true, false, array(true, true), 'paid-idempotent');
	$assert($idempotent['counts']['claims'] === 1 && $idempotent['counts']['reservations'] === 1
		&& $idempotent['a']['claim_id'] === $idempotent['b']['claim_id']
		&& $idempotent['a']['reservation_id'] === $idempotent['b']['reservation_id'],
		'Same-idempotency race must leave exactly one Claim and one Reservation.');
	$final_slot = $run_race(1, 1, 1, false, false, array(true, false), 'paid-final-slot');
	$assert(($final_slot['b']['error'] ?? '') === 'offer_capacity_exhausted' && $final_slot['counts']['claims'] === 1, 'Different identities racing for the final slot must produce one winner.');
	$stale_race = $run_race(1, 1, 1, false, true, array(true, false), 'paid-stale');
	$assert(($stale_race['b']['error'] ?? '') === 'offer_capacity_exhausted' && $stale_race['counts']['expired'] === 1 && $stale_race['counts']['held_quantity'] === 1, 'Stale race must expire old capacity and serialize one new winner.');
	$clear_offers();
	$timeout_offer = $make_offer(array('capacity_total' => 2, 'max_qty_per_claim' => 1));
	$timeout_request_a = $request($timeout_offer, $event_id, 1, 'timeout-a-c1@example.invalid');
	$timeout_request_b = $request($timeout_offer, $event_id, 1, 'timeout-b-c1@example.invalid');
	$timeout_marker = sys_get_temp_dir() . '/bvm-ao-c1-timeout-' . bin2hex(random_bytes(6)); $timeout_release = $timeout_marker . '.release';
	$timeout_locker = $start_worker(array('mode' => 'paid_claim', 'now' => '2026-09-28 12:00:00', 'request' => $timeout_request_a, 'marker' => $timeout_marker, 'release' => $timeout_release));
	$wait_marker($timeout_marker);
	$timeout_result = $finish_worker($start_worker(array('mode' => 'paid_claim', 'now' => '2026-09-28 12:00:00', 'request' => $timeout_request_b, 'lock_wait_timeout' => 1)));
	$assert(!$timeout_result['ok'] && $timeout_result['error_class'] === 'BVMGR_Admission_Offer_Transient_Transaction_Exception', 'Paid lock timeout must exhaust the bounded three-attempt transient policy.');
	file_put_contents($timeout_release, 'release');
	$timeout_locker_result = $finish_worker($timeout_locker); @unlink($timeout_marker); @unlink($timeout_release);
	$assert($timeout_locker_result['ok'] && $timeout_locker_result['connection_id'] !== $timeout_result['connection_id'], 'Paid timeout fixture must use independent sessions and leave the locker healthy.');

	$after_side_effects = $side_effect_snapshot();
	$assert($before_side_effects === $after_side_effects, 'C1 must not change Woo, TEC, native admission, or legacy Guest Pass state.');
	$assert((int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['checkouts']}") === 0
		&& (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['allocations']}") === 0
		&& (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['fulfillments']}") === 0,
		'C1 must create zero Checkout, Allocation, and Fulfillment rows.');
	$paid_source = (string) file_get_contents(dirname(__DIR__, 2) . '/includes/modules/admission-offers/paid-claim-service.php');
	foreach (array('add_action(', 'add_filter(', 'register_rest_route(', 'wc_create_order(', 'WC()->', 'tribe_', 'vms_pass_', 'bvmgr_admission_table_entries') as $forbidden) {
		$assert(stripos($paid_source, $forbidden) === false, 'Paid service contains forbidden integration surface: ' . $forbidden);
	}

	return array(
		'percent_claim_id' => (int) $percent['claim']['id'],
		'fixed_claim_id' => (int) $fixed['claim']['id'],
		'ttl' => array('soft_seconds' => 1200, 'hard_seconds' => 2700),
		'concurrency' => array('oversubscription' => $oversubscription, 'fitting' => $fitting, 'idempotent' => $idempotent, 'final_slot' => $final_slot, 'stale' => $stale_race, 'timeout' => $timeout_result),
		'negative_side_effects' => $after_side_effects,
		'schema_tables' => count($tables),
	);
}
