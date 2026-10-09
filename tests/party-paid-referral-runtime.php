<?php
/** Disposable local runtime exercise for canonical Party paid referrals. */

defined('ABSPATH') || exit;

function outreach_party_referral_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

if (!class_exists('WooCommerce') || !function_exists('backstage_outreach_party_referral_create')) {
	throw new RuntimeException('Required Outreach/WooCommerce runtime is unavailable.');
}

global $wpdb;
$marker = 'Outreach Party referral ' . wp_generate_password(8, false, false);
$now = backstage_outreach_business_now();
$user_id = get_current_user_id() ?: 1;
$post_ids = array();
$source_id = 0;
$party_ids = array();
$batch_ids = array();
$campaign_ids = array();
$distribution_ids = array();
$coupon_ids = array();
$order_ids = array();
$delivery_block = static fn() => true;
add_filter('pre_wp_mail', $delivery_block, PHP_INT_MAX);

$count = static fn(string $table): int => (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
$baselines = array(
	'party_distributions' => $count(backstage_outreach_party_table('referral_distributions')),
	'party_redemptions' => $count(backstage_outreach_party_table('referral_redemptions')),
	'business_distributions' => $count(backstage_outreach_business_table('campaign_businesses')),
	'business_redemptions' => $count(backstage_outreach_business_table('paid_redemptions')),
	'tokens' => $count(bvmgr_admission_table_pass_tokens()),
);
$fitness_hash = (string) $wpdb->get_var($wpdb->prepare(
	"SELECT SHA2(GROUP_CONCAT(CONCAT_WS('|',id,campaign_id,source_id,business_id,public_key,token_hash,status,distribution_type,admission_cap,order_cap,COALESCE(coupon_id,0),COALESCE(coupon_code,''),COALESCE(expires_at,'')) ORDER BY id SEPARATOR '\n'),256) FROM %i WHERE campaign_id=%d",
	backstage_outreach_business_table('campaign_businesses'), 25
));
$realtor_before = array(
	'campaign31' => $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', vms_admission_table_pass_outreach_campaigns(), 31), ARRAY_A),
	'batch84' => $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', bvmgr_admission_table_pass_batches(), 84), ARRAY_A),
	'recipients31' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE campaign_id=%d', vms_pass_outreach_recipient_table(), 31)),
);

try {
	$event_start = wp_date('Y-m-d 19:00:00', time() + (21 * DAY_IN_SECONDS), wp_timezone());
	$event_end = wp_date('Y-m-d 22:00:00', time() + (21 * DAY_IN_SECONDS), wp_timezone());
	$tec_event_id = tribe_create_event(array('post_status' => 'publish', 'post_title' => $marker . ' event', 'EventStartDate' => $event_start, 'EventEndDate' => $event_end));
	outreach_party_referral_assert(!is_wp_error($tec_event_id) && $tec_event_id > 0, 'Could not create the disposable event.');
	$post_ids[] = (int) $tec_event_id;
	$event_plan_id = wp_insert_post(array('post_type' => 'vms_event_plan', 'post_status' => 'publish', 'post_title' => $marker . ' plan'), true);
	outreach_party_referral_assert(!is_wp_error($event_plan_id) && $event_plan_id > 0, 'Could not create the Event Plan.');
	$post_ids[] = (int) $event_plan_id;
	$status_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status';
	update_post_meta((int) $event_plan_id, $status_key, 'published');
	update_post_meta((int) $event_plan_id, '_vms_event_date', wp_date('Y-m-d', time() + (21 * DAY_IN_SECONDS)));
	update_post_meta((int) $event_plan_id, '_vms_tec_event_id', (int) $tec_event_id);
	update_post_meta((int) $event_plan_id, '_vms_start_time', '19:00');

	$ticket_provider = tribe('tickets-plus.commerce.woo');
	$ticket_id = (int) $ticket_provider->ticket_add((int) $tec_event_id, array(
		'ticket_name' => $marker . ' ticket', 'ticket_price' => '80', 'ticket_show_description' => 'no',
		'tribe-ticket' => array('capacity' => 200, 'mode' => 'own'),
	));
	outreach_party_referral_assert($ticket_id > 0, 'Could not create the eligible ticket.');
	$post_ids[] = $ticket_id;
	$ticket = wc_get_product($ticket_id);
	$ticket->set_status('publish');
	$ticket->set_virtual(true);
	$ticket->set_tax_status('taxable');
	$ticket->save();
	update_post_meta($ticket_id, '_vms_event_plan_id', (int) $event_plan_id);
	update_post_meta($ticket_id, '_vms_product_role', 'ga_ticket');

	$unrelated = new WC_Product_Simple();
	$unrelated->set_name($marker . ' unrelated');
	$unrelated->set_status('publish');
	$unrelated->set_regular_price('25');
	$unrelated->set_price('25');
	$unrelated->set_tax_status('taxable');
	$unrelated_id = $unrelated->save();
	$post_ids[] = $unrelated_id;

	outreach_party_referral_assert($wpdb->insert(bvmgr_admission_table_pass_sources(), array('source_name' => $marker, 'status' => 'active', 'created_by' => $user_id, 'created_at' => $now)) !== false, 'Could not create Source.');
	$source_id = (int) $wpdb->insert_id;

	foreach (array('Person' => 'person', 'Organization' => 'organization', 'Fixed' => 'organization') as $label => $type) {
		$party = backstage_outreach_party_save(array('party_type' => $type, 'display_name' => $marker . ' ' . $label, 'organization_name' => $type === 'organization' ? $marker . ' ' . $label : ''), $user_id);
		outreach_party_referral_assert(is_array($party), 'Could not create ' . $label . ' Party.');
		$party_ids[$label] = (int) $party['id'];
		outreach_party_referral_assert(!is_wp_error(backstage_outreach_party_link_source((int) $party['id'], $source_id, $user_id, 'synthetic_runtime')), 'Could not associate Source to ' . $label . '.');
	}

	$create_campaign = static function (string $value_type, float $value_amount, string $suffix) use ($wpdb, $source_id, $event_plan_id, $user_id, $now, $marker, &$batch_ids, &$campaign_ids): array {
		outreach_party_referral_assert($wpdb->insert(bvmgr_admission_table_pass_batches(), array(
			'source_id' => $source_id, 'batch_name' => $marker . ' ' . $suffix, 'quantity' => 0,
			'validity_type' => 'single_event', 'single_event_plan_id' => $event_plan_id, 'venue_ids_json' => '[]',
			'value_type' => $value_type, 'value_amount' => $value_amount, 'applies_to' => 'entry_only', 'status' => 'active',
			'checkin_open_mode' => 'same_day', 'max_per_phone' => 0, 'generated_count' => 0, 'created_by' => $user_id,
			'created_at' => $now, 'admissions_per_link' => 2, 'total_admission_cap' => 12, 'max_per_email' => 0,
		)) !== false, 'Could not create ' . $suffix . ' batch.');
		$batch_id = (int) $wpdb->insert_id;
		$batch_ids[] = $batch_id;
		outreach_party_referral_assert($wpdb->insert(vms_admission_table_pass_outreach_campaigns(), array(
			'campaign_name' => $marker . ' ' . $suffix, 'related_source_id' => $source_id, 'related_batch_id' => $batch_id,
			'validity_type' => 'single_event', 'single_event_plan_id' => $event_plan_id,
			'admissions_per_recipient' => 2, 'total_admission_cap' => 12, 'status' => 'active',
			'eligibility_mode' => 'anyone_with_invite', 'created_by' => $user_id, 'created_at' => $now,
			'campaign_purpose' => 'guest_pass_invitation',
		)) !== false, 'Could not create ' . $suffix . ' campaign.');
		$campaign_id = (int) $wpdb->insert_id;
		$campaign_ids[] = $campaign_id;
		return array('batch_id' => $batch_id, 'campaign_id' => $campaign_id);
	};
	$percent_campaign = $create_campaign('percent', 50, 'percent');
	$fixed_campaign = $create_campaign('fixed', 12.35, 'fixed');

	$create_referral = static function (int $party_id, int $campaign_id, int $admission_cap, int $order_cap = 0) use ($user_id, &$distribution_ids, &$coupon_ids): array {
		$review = backstage_outreach_party_referral_validate($party_id, $campaign_id, $admission_cap, $order_cap, '');
		outreach_party_referral_assert(is_array($review), is_wp_error($review) ? $review->get_error_message() : 'Partner review failed.');
		$row = backstage_outreach_party_referral_create($review, $user_id);
		outreach_party_referral_assert(is_array($row), is_wp_error($row) ? $row->get_error_message() : 'Partner link creation failed.');
		$distribution_ids[] = (int) $row['id'];
		$coupon_ids[] = (int) $row['coupon_id'];
		return $row;
	};
	$person = $create_referral($party_ids['Person'], $percent_campaign['campaign_id'], 4, 3);
	$organization = $create_referral($party_ids['Organization'], $percent_campaign['campaign_id'], 4);
	$fixed = $create_referral($party_ids['Fixed'], $fixed_campaign['campaign_id'], 4, 1);

	outreach_party_referral_assert($person['owner_type'] === 'party' && (int) $person['party_id'] === $party_ids['Person'], 'Person attribution is not canonical Party attribution.');
	outreach_party_referral_assert($organization['party_type'] === 'organization', 'Organization Party type was not retained.');
	outreach_party_referral_assert(str_contains(backstage_outreach_party_referral_url($person), '/admission-offer/partner/'), 'Partner URL did not use the distinct route.');
	$person_token = backstage_outreach_party_referral_token($person);
	outreach_party_referral_assert(hash_equals((string) $person['token_hash'], hash('sha256', $person_token)), 'Partner token hash does not match its signed URL.');
	outreach_party_referral_assert(!hash_equals(backstage_outreach_party_referral_signature($person), backstage_outreach_party_referral_signature(array_merge($person, array('party_id' => $party_ids['Organization'])))), 'Party identity was not included in the signature domain.');
	$first_context = backstage_outreach_party_referral_context($person_token);
	$replayed_context = backstage_outreach_party_referral_context($person_token);
	outreach_party_referral_assert(is_array($first_context) && is_array($replayed_context) && (int) $first_context['id'] === (int) $replayed_context['id'], 'The reusable signed Partner link did not survive a safe replay.');
	$tampered_token = substr($person_token, 0, -1) . (substr($person_token, -1) === 'a' ? 'b' : 'a');
	outreach_party_referral_assert(is_wp_error(backstage_outreach_party_referral_context($tampered_token)), 'A forged Partner token was accepted.');

	$person_coupon = new WC_Coupon((int) $person['coupon_id']);
	$fixed_coupon = new WC_Coupon((int) $fixed['coupon_id']);
	outreach_party_referral_assert(str_starts_with((string) $person_coupon->get_code(), 'sr-party-'), 'Partner coupon namespace is incorrect.');
	outreach_party_referral_assert((string) $person_coupon->get_meta(BACKSTAGE_OUTREACH_COUPON_OWNER_TYPE_META, true) === 'party' && (int) $person_coupon->get_meta(BACKSTAGE_OUTREACH_COUPON_PARTY_DISTRIBUTION_META, true) === (int) $person['id'], 'Partner coupon ownership is not typed.');
	outreach_party_referral_assert((int) $person_coupon->get_meta(BACKSTAGE_OUTREACH_COUPON_DISTRIBUTION_META, true) === 0, 'Partner coupon reused business ownership metadata.');
	outreach_party_referral_assert($person_coupon->get_discount_type() === 'percent' && (float) $person_coupon->get_amount() === 50.0, 'Person 50% coupon terms are wrong.');
	outreach_party_referral_assert((int) $person_coupon->get_usage_limit() === 3 && $person_coupon->get_individual_use(), 'Partner paid-order or coupon nonstacking protection is wrong.');
	outreach_party_referral_assert($fixed_coupon->get_discount_type() === 'fixed_product' && abs((float) $fixed_coupon->get_amount() - 12.35) < 0.001, 'Fixed-dollar Partner coupon terms are wrong.');
	// Let the focused reservation check, rather than WooCommerce's parallel usage gate, prove the locked paid-order cap below.
	$fixed_coupon->set_usage_limit(0);
	$fixed_coupon->save();
	$fixed_coupon->update_meta_data(BACKSTAGE_OUTREACH_COUPON_CONFIG_HASH_META, backstage_outreach_discount_coupon_state_hash($fixed_coupon));
	$fixed_coupon->save();

	WC()->cart->empty_cart(true);
	backstage_outreach_discount_session_clear();
	WC()->cart->add_to_cart($ticket_id, 1);
	WC()->cart->add_to_cart($unrelated_id, 1);
	backstage_outreach_discount_session_set($person, $person_token);
	backstage_outreach_discount_sync_cart_coupon();
	WC()->cart->calculate_totals();
	outreach_party_referral_assert(abs((float) WC()->cart->get_discount_total() - 40.0) < 0.02, 'The 50% Partner offer did not discount only the eligible $80 ticket.');
	outreach_party_referral_assert((float) WC()->cart->get_total('edit') > 0.0, 'The positive-price Partner cart stopped using normal WooCommerce totals.');

	WC()->cart->empty_cart(true);
	backstage_outreach_discount_session_clear();
	WC()->cart->add_to_cart($ticket_id, 1);
	backstage_outreach_discount_session_set($fixed, backstage_outreach_party_referral_token($fixed));
	backstage_outreach_discount_sync_cart_coupon();
	WC()->cart->calculate_totals();
	outreach_party_referral_assert(abs((float) WC()->cart->get_discount_total() - 12.35) < 0.02, 'Fixed-dollar Partner cart amount is wrong.');

	$make_order = static function (array $distribution, int $quantity, string $email) use ($ticket_id, &$order_ids): WC_Order {
		$order = wc_create_order();
		if (is_wp_error($order)) {
			throw new RuntimeException($order->get_error_message());
		}
		$order_ids[] = $order->get_id();
		$order->set_billing_first_name('Purchasing');
		$order->set_billing_last_name('Customer');
		$order->set_billing_email($email);
		$order->update_meta_data('_backstage_outreach_owner_type', 'party');
		$order->update_meta_data('_backstage_outreach_party_distribution_id', (int) $distribution['id']);
		$order->update_meta_data('_backstage_outreach_campaign_id', (int) $distribution['campaign_id']);
		$order->update_meta_data('_backstage_outreach_source_id', (int) $distribution['source_id']);
		$order->update_meta_data('_backstage_outreach_party_id', (int) $distribution['party_id']);
		$order->update_meta_data('_backstage_outreach_coupon_id', (int) $distribution['coupon_id']);
		$order->update_meta_data('_backstage_outreach_offer_type', 'coupon_backed');
		$order->add_product(wc_get_product($ticket_id), $quantity);
		$order->save();
		$result = $order->apply_coupon((string) $distribution['coupon_code']);
		if (is_wp_error($result)) {
			throw new RuntimeException($result->get_error_message());
		}
		$order->calculate_totals();
		$order->save();
		return $order;
	};

	$person_order = $make_order($person, 2, 'customer-not-partner@example.test');
	backstage_outreach_discount_reserve_order($person_order);
	backstage_outreach_discount_reserve_order($person_order);
	$person_ledger = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE order_id=%d', backstage_outreach_party_table('referral_redemptions'), $person_order->get_id()), ARRAY_A);
	outreach_party_referral_assert(is_array($person_ledger) && (int) $person_ledger['party_id'] === $party_ids['Person'] && (int) $person_ledger['ticket_quantity'] === 2, 'Order attribution did not remain with the referring Person Party.');
	outreach_party_referral_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE order_id=%d', backstage_outreach_party_table('referral_redemptions'), $person_order->get_id())) === 1, 'Retry created duplicate Partner redemptions.');
	outreach_party_referral_assert((string) $person_order->get_billing_email() === 'customer-not-partner@example.test', 'Referring Party identity leaked into purchasing customer identity.');
	outreach_party_referral_assert(bvmgr_vendor_portal_product_is_paid_admission($ticket_id), 'The positive-price ticket was not classified as a paid admission product.');
	outreach_party_referral_assert($person_order->get_total() > 0 && !$person_order->get_meta('_vms_guest_pass_claim_id', true), 'Paid Partner checkout was routed through complimentary Guest Pass behavior.');

	$person_order->payment_complete();
	backstage_outreach_discount_mark_paid($person_order->get_id());
	$paid_row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE order_id=%d', backstage_outreach_party_table('referral_redemptions'), $person_order->get_id()), ARRAY_A);
	outreach_party_referral_assert((string) $paid_row['status'] === 'paid', 'Paid order status did not reconcile.');
	$refund = wc_create_refund(array('order_id' => $person_order->get_id(), 'amount' => 10, 'reason' => 'Synthetic partial refund', 'refund_payment' => false, 'restock_items' => false));
	outreach_party_referral_assert(!is_wp_error($refund), 'Could not create the partial refund.');
	backstage_outreach_discount_record_refund($person_order->get_id());
	$partial = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE order_id=%d', backstage_outreach_party_table('referral_redemptions'), $person_order->get_id()), ARRAY_A);
	outreach_party_referral_assert((string) $partial['status'] === 'paid' && (float) $partial['refunded_total'] >= 10, 'Partial refund did not preserve paid status and refund accounting.');

	$refunded_order = $make_order($fixed, 1, 'refunded@example.test');
	$order_cap_retry = $make_order($fixed, 1, 'failed@example.test');
	backstage_outreach_discount_reserve_order($refunded_order);
	$refunded_order->payment_complete();
	backstage_outreach_discount_mark_paid($refunded_order->get_id());
	$order_cap_blocked = false;
	try {
		backstage_outreach_discount_reserve_order($order_cap_retry);
	} catch (Throwable $error) {
		$order_cap_blocked = str_contains($error->getMessage(), 'paid-order limit');
	}
	outreach_party_referral_assert($order_cap_blocked, 'The Partner paid-order cap did not block an additional active order.');
	$full_refund = wc_create_refund(array('order_id' => $refunded_order->get_id(), 'amount' => $refunded_order->get_total(), 'reason' => 'Synthetic full refund', 'refund_payment' => false, 'restock_items' => false));
	outreach_party_referral_assert(!is_wp_error($full_refund), 'Could not create the full refund.');
	backstage_outreach_discount_record_refund($refunded_order->get_id());
	$full_refund_status = (string) $wpdb->get_var($wpdb->prepare('SELECT status FROM %i WHERE order_id=%d', backstage_outreach_party_table('referral_redemptions'), $refunded_order->get_id()));
	outreach_party_referral_assert($full_refund_status === 'refunded', 'Full refund did not release Partner capacity.');

	// Two independent PHP processes compete for the final two shared admissions.
	outreach_party_referral_assert(function_exists('proc_open'), 'Concurrent checkout verification requires proc_open.');
	$wpdb->update(vms_admission_table_pass_outreach_campaigns(), array('total_admission_cap' => 4), array('id' => (int) $percent_campaign['campaign_id']));
	$wpdb->update(bvmgr_admission_table_pass_batches(), array('total_admission_cap' => 4), array('id' => (int) $percent_campaign['batch_id']));
	$race_orders = array(
		$make_order($organization, 2, 'race-one@example.test'),
		$make_order($organization, 2, 'race-two@example.test'),
	);
	$race_option = 'outreach_party_referral_race_' . strtolower(wp_generate_password(10, false, false));
	update_option($race_option, array('order_ids' => array($race_orders[0]->get_id(), $race_orders[1]->get_id()), 'start_at' => microtime(true) + 2.0), false);
	$race_files = array(tempnam(sys_get_temp_dir(), 'party-race-'), tempnam(sys_get_temp_dir(), 'party-race-'));
	$processes = array();
	$worker = (string) ($args[0] ?? (__DIR__ . '/party-paid-referral-concurrency-worker.php'));
	$wp_cli = (string) ($args[1] ?? (PHP_OS_FAMILY === 'Darwin' ? '/opt/homebrew/bin/wp' : '/usr/bin/wp'));
	outreach_party_referral_assert(is_file($worker) && is_executable($wp_cli), 'Concurrent checkout worker or WP-CLI executable is unavailable.');
	foreach (array(0, 1) as $index) {
		$descriptors = array(0 => array('pipe', 'r'), 1 => array('file', $race_files[$index], 'w'), 2 => array('file', '/dev/null', 'a'));
		$process = proc_open(array($wp_cli, 'eval-file', $worker, $race_option, (string) $index), $descriptors, $pipes, ABSPATH);
		outreach_party_referral_assert(is_resource($process), 'Could not start a Party checkout race worker.');
		fclose($pipes[0]);
		$processes[] = $process;
	}
	foreach ($processes as $process) {
		outreach_party_referral_assert(proc_close($process) === 0, 'A Party checkout race worker failed.');
	}
	wp_cache_flush();
	$race_results = array_map(static function (string $file): string {
		$output = (string) file_get_contents($file);
		return preg_match('/PARTY_RACE:(won|lost:[^\r\n]*)/', $output, $match) ? $match[1] : 'missing';
	}, $race_files);
	$race_ids = array($race_orders[0]->get_id(), $race_orders[1]->get_id());
	$race_quantity = (int) $wpdb->get_var('SELECT COALESCE(SUM(ticket_quantity),0) FROM `' . backstage_outreach_party_table('referral_redemptions') . '` WHERE order_id IN (' . implode(',', array_map('absint', $race_ids)) . ") AND status='pending'");
	outreach_party_referral_assert(count(array_filter($race_results, static fn(string $result): bool => $result === 'won')) === 1 && $race_quantity === 2, 'Concurrent Partner checkouts exceeded or lost the final shared capacity.');
	delete_option($race_option);
	foreach ($race_files as $file) {
		if (is_string($file) && file_exists($file)) {
			unlink($file);
		}
	}
	$wpdb->query('DELETE FROM `' . backstage_outreach_party_table('referral_redemptions') . '` WHERE order_id IN (' . implode(',', array_map('absint', $race_ids)) . ')');
	foreach ($race_orders as $race_order) {
		$race_order->delete(true);
	}
	$order_ids = array_values(array_diff($order_ids, $race_ids));
	$wpdb->update(vms_admission_table_pass_outreach_campaigns(), array('total_admission_cap' => 12), array('id' => (int) $percent_campaign['campaign_id']));
	$wpdb->update(bvmgr_admission_table_pass_batches(), array('total_admission_cap' => 12), array('id' => (int) $percent_campaign['batch_id']));

	$failed_order = $order_cap_retry;
	backstage_outreach_discount_reserve_order($failed_order);
	$failed_order->set_status('failed');
	$failed_order->save();
	backstage_outreach_discount_mark_failed($failed_order->get_id());
	outreach_party_referral_assert((string) $wpdb->get_var($wpdb->prepare('SELECT status FROM %i WHERE order_id=%d', backstage_outreach_party_table('referral_redemptions'), $failed_order->get_id())) === 'failed', 'Failed order status did not release capacity.');
	$cancelled_order = $make_order($organization, 1, 'cancelled@example.test');
	backstage_outreach_discount_reserve_order($cancelled_order);
	$cancelled_order->set_status('cancelled');
	$cancelled_order->save();
	backstage_outreach_discount_mark_cancelled($cancelled_order->get_id());
	outreach_party_referral_assert((string) $wpdb->get_var($wpdb->prepare('SELECT status FROM %i WHERE order_id=%d', backstage_outreach_party_table('referral_redemptions'), $cancelled_order->get_id())) === 'cancelled', 'Cancelled order status did not release capacity.');

	$distribution_table = backstage_outreach_party_table('referral_distributions');
	$wpdb->update($distribution_table, array('status' => 'paused'), array('id' => (int) $organization['id']));
	$paused = backstage_outreach_party_referral_get_distribution((int) $organization['id']);
	outreach_party_referral_assert(is_wp_error(backstage_outreach_discount_distribution_error($paused)), 'Paused Partner link remained available.');
	$wpdb->update($distribution_table, array('status' => 'active', 'expires_at' => '2000-01-01 00:00:00'), array('id' => (int) $organization['id']));
	$expired = backstage_outreach_party_referral_get_distribution((int) $organization['id']);
	outreach_party_referral_assert(is_wp_error(backstage_outreach_discount_distribution_error($expired)), 'Expired Partner link remained available.');
	$wpdb->update($distribution_table, array('expires_at' => null), array('id' => (int) $organization['id']));
	$wpdb->update($distribution_table, array('status' => 'revoked'), array('id' => (int) $fixed['id']));
	$revoked = backstage_outreach_party_referral_get_distribution((int) $fixed['id']);
	outreach_party_referral_assert(is_wp_error(backstage_outreach_discount_distribution_error($revoked)), 'Revoked Partner link remained available.');

	$wpdb->update(bvmgr_admission_table_pass_batches(), array('value_amount' => 45), array('id' => (int) $percent_campaign['batch_id']));
	$stale = backstage_outreach_party_referral_get_distribution((int) $organization['id']);
	$stale_error = backstage_outreach_discount_distribution_error($stale);
	outreach_party_referral_assert(is_wp_error($stale_error) && $stale_error->get_error_code() === 'partner_offer_stale', 'Reviewed Partner configuration did not fail closed after batch drift.');
	$wpdb->update(bvmgr_admission_table_pass_batches(), array('value_amount' => 50), array('id' => (int) $percent_campaign['batch_id']));

	$type_change = backstage_outreach_party_save(array('party_type' => 'organization', 'display_name' => $marker . ' Person'), $user_id, $party_ids['Person']);
	outreach_party_referral_assert(is_wp_error($type_change) && $type_change->get_error_code() === 'party_type_associations_exist', 'Associated Person was allowed to become an Organization.');
	outreach_party_referral_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE batch_id IN (' . implode(',', array_map('absint', $batch_ids)) . ')', bvmgr_admission_table_pass_tokens())) === 0, 'Paid Partner offers generated individual Guest Pass tokens.');

	echo "Party paid referral runtime PASS\n";
	echo wp_json_encode(array(
		'person_offer' => '50% off', 'organization_offer' => '50% off', 'fixed_offer' => '$12.35 off each admission',
		'customer_limit' => 2, 'person_admission_cap' => 4, 'person_order_cap' => 3,
		'party_attribution' => $party_ids['Person'], 'purchaser_email' => 'customer-not-partner@example.test',
		'partial_refund' => (float) $partial['refunded_total'], 'full_refund_status' => $full_refund_status,
		'locked_order_cap_blocked' => $order_cap_blocked, 'individual_tokens' => 0,
		'concurrent_last_slot' => $race_results,
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} finally {
	remove_filter('pre_wp_mail', $delivery_block, PHP_INT_MAX);
	WC()->cart->empty_cart(true);
	backstage_outreach_discount_session_clear();
	foreach ($order_ids as $order_id) {
		$attendee_ids = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM %i WHERE meta_key='_tribe_wooticket_order' AND meta_value=%d", $wpdb->postmeta, $order_id));
		foreach ($attendee_ids as $attendee_id) {
			wp_delete_post((int) $attendee_id, true);
		}
		$order = wc_get_order($order_id);
		if ($order) {
			$order->delete(true);
		}
	}
	foreach ($post_ids as $related_post_id) {
		$attendee_ids = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM %i WHERE meta_key IN ('_tribe_wooticket_event','_tribe_wooticket_product') AND meta_value=%d", $wpdb->postmeta, $related_post_id));
		foreach ($attendee_ids as $attendee_id) {
			wp_delete_post((int) $attendee_id, true);
		}
	}
	foreach (array_unique($coupon_ids) as $coupon_id) {
		$coupon = new WC_Coupon($coupon_id);
		if ($coupon->get_id() > 0) {
			$coupon->delete(true);
		}
	}
	if ($distribution_ids) {
		$ids = implode(',', array_map('absint', $distribution_ids));
		$wpdb->query("DELETE FROM `" . backstage_outreach_party_table('referral_redemptions') . "` WHERE `distribution_id` IN ({$ids})");
		$wpdb->query("DELETE FROM `" . backstage_outreach_party_table('referral_distributions') . "` WHERE `id` IN ({$ids})");
	}
	if ($party_ids) {
		$ids = implode(',', array_map('absint', array_values($party_ids)));
		$wpdb->query("DELETE FROM `" . backstage_outreach_party_table('identity_audit') . "` WHERE `from_party_id` IN ({$ids}) OR `to_party_id` IN ({$ids})");
		foreach (array('contact_methods', 'sources', 'campaign_roles', 'legacy_links') as $suffix) {
			$wpdb->query("DELETE FROM `" . backstage_outreach_party_table($suffix) . "` WHERE `party_id` IN ({$ids})");
		}
		$wpdb->query("DELETE FROM `" . backstage_outreach_party_table('affiliations') . "` WHERE `person_party_id` IN ({$ids}) OR `organization_party_id` IN ({$ids})");
		$wpdb->query("DELETE FROM `" . backstage_outreach_party_table('parties') . "` WHERE `id` IN ({$ids})");
	}
	foreach ($campaign_ids as $campaign_id) {
		$wpdb->delete(vms_admission_table_pass_outreach_campaigns(), array('id' => $campaign_id));
	}
	foreach ($batch_ids as $batch_id) {
		$wpdb->delete(bvmgr_admission_table_pass_batches(), array('id' => $batch_id));
	}
	if ($source_id > 0) {
		$wpdb->delete(bvmgr_admission_table_pass_sources(), array('id' => $source_id));
	}
	foreach (array_reverse($post_ids) as $post_id) {
		wp_delete_post($post_id, true);
	}
	outreach_party_referral_assert($count(backstage_outreach_party_table('referral_distributions')) === $baselines['party_distributions'], 'Party distributions did not return to baseline.');
	outreach_party_referral_assert($count(backstage_outreach_party_table('referral_redemptions')) === $baselines['party_redemptions'], 'Party redemptions did not return to baseline.');
	outreach_party_referral_assert($count(backstage_outreach_business_table('campaign_businesses')) === $baselines['business_distributions'], 'Business distributions changed.');
	outreach_party_referral_assert($count(backstage_outreach_business_table('paid_redemptions')) === $baselines['business_redemptions'], 'Business redemptions changed.');
	outreach_party_referral_assert($count(bvmgr_admission_table_pass_tokens()) === $baselines['tokens'], 'Guest Pass token count changed.');
	$fitness_after = (string) $wpdb->get_var($wpdb->prepare(
		"SELECT SHA2(GROUP_CONCAT(CONCAT_WS('|',id,campaign_id,source_id,business_id,public_key,token_hash,status,distribution_type,admission_cap,order_cap,COALESCE(coupon_id,0),COALESCE(coupon_code,''),COALESCE(expires_at,'')) ORDER BY id SEPARATOR '\n'),256) FROM %i WHERE campaign_id=%d",
		backstage_outreach_business_table('campaign_businesses'), 25
	));
	outreach_party_referral_assert(hash_equals($fitness_hash, $fitness_after), 'Existing Fitness distributions changed.');
	outreach_party_referral_assert($realtor_before['campaign31'] === $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', vms_admission_table_pass_outreach_campaigns(), 31), ARRAY_A), 'Campaign 31 changed.');
	outreach_party_referral_assert($realtor_before['batch84'] === $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', bvmgr_admission_table_pass_batches(), 84), ARRAY_A), 'Batch 84 changed.');
	outreach_party_referral_assert($realtor_before['recipients31'] === (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE campaign_id=%d', vms_pass_outreach_recipient_table(), 31)), 'Campaign 31 recipients changed.');
}
