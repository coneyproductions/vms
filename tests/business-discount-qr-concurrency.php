<?php
/**
 * Destructive synthetic local race test. Run with:
 * wp eval-file wp-content/plugins/packages/vms-github-reconcile/tests/business-discount-qr-concurrency.php
 */

defined('ABSPATH') || exit;

function backstage_discount_concurrency_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

if (!function_exists('proc_open')) {
	throw new RuntimeException('The concurrent test requires proc_open.');
}
if (!class_exists('WooCommerce') || !function_exists('backstage_outreach_partner_claim')) {
	throw new RuntimeException('The WooCommerce and Backstage Outreach runtime is unavailable.');
}

global $wpdb;
$marker = 'BVM discount QR race ' . wp_generate_password(8, false, false);
$now = backstage_outreach_business_now();
$post_ids = array();
$business_ids = array();
$campaign_ids = array();
$distribution_ids = array();
$coupon_id = 0;
$order_id = 0;
$source_id = 0;
$batch_id = 0;
$result_files = array();
$fixture_option = '';

add_filter('pre_wp_mail', static fn() => true, PHP_INT_MAX);

try {
	$event_start = wp_date('Y-m-d 19:00:00', time() + (21 * DAY_IN_SECONDS), wp_timezone());
	$event_end = wp_date('Y-m-d 22:00:00', time() + (21 * DAY_IN_SECONDS), wp_timezone());
	$tec_event_id = tribe_create_event(array('post_status' => 'publish', 'post_title' => $marker . ' event', 'EventStartDate' => $event_start, 'EventEndDate' => $event_end));
	backstage_discount_concurrency_assert(!is_wp_error($tec_event_id) && $tec_event_id > 0, 'Could not create the race event.');
	$post_ids[] = (int) $tec_event_id;
	$event_plan_id = wp_insert_post(array('post_type' => 'vms_event_plan', 'post_status' => 'publish', 'post_title' => $marker . ' plan'), true);
	backstage_discount_concurrency_assert(!is_wp_error($event_plan_id) && $event_plan_id > 0, 'Could not create the race event plan.');
	$post_ids[] = (int) $event_plan_id;
	$status_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status';
	update_post_meta((int) $event_plan_id, $status_key, 'published');
	update_post_meta((int) $event_plan_id, '_vms_event_date', wp_date('Y-m-d', time() + (21 * DAY_IN_SECONDS)));
	update_post_meta((int) $event_plan_id, '_vms_tec_event_id', (int) $tec_event_id);

	$ticket_provider = tribe('tickets-plus.commerce.woo');
	$ticket_id = (int) $ticket_provider->ticket_add((int) $tec_event_id, array(
		'ticket_name' => $marker . ' ticket', 'ticket_price' => '100', 'ticket_show_description' => 'no',
		'tribe-ticket' => array('capacity' => 10, 'mode' => 'own'),
	));
	backstage_discount_concurrency_assert($ticket_id > 0, 'Could not create the race ticket.');
	$post_ids[] = $ticket_id;
	$ticket = wc_get_product($ticket_id);
	$ticket->set_status('publish');
	$ticket->set_tax_status('none');
	$ticket->set_virtual(true);
	$ticket->save();
	update_post_meta($ticket_id, '_vms_event_plan_id', (int) $event_plan_id);
	update_post_meta($ticket_id, '_vms_product_role', 'ga_ticket');

	$source_table = bvmgr_admission_table_pass_sources();
	$batch_table = bvmgr_admission_table_pass_batches();
	$campaign_table = vms_admission_table_pass_outreach_campaigns();
	backstage_discount_concurrency_assert($wpdb->insert($source_table, array('source_name' => $marker, 'status' => 'active', 'created_by' => 1, 'created_at' => $now)) !== false, 'Could not create the race Source.');
	$source_id = (int) $wpdb->insert_id;
	backstage_discount_concurrency_assert($wpdb->insert($batch_table, array(
		'source_id' => $source_id, 'batch_name' => $marker, 'quantity' => 0, 'validity_type' => 'single_event',
		'single_event_plan_id' => (int) $event_plan_id, 'venue_ids_json' => '[]', 'value_type' => 'free',
		'value_amount' => '0.00', 'applies_to' => 'entry_only', 'status' => 'active', 'checkin_open_mode' => 'same_day',
		'max_per_phone' => 0, 'generated_count' => 0, 'created_by' => 1, 'created_at' => $now,
		'admissions_per_link' => 1, 'total_admission_cap' => 1, 'max_per_email' => 0,
	)) !== false, 'Could not create the one-slot shared batch.');
	$batch_id = (int) $wpdb->insert_id;
	backstage_discount_concurrency_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE batch_id=%d', bvmgr_admission_table_pass_tokens(), $batch_id)) === 0, 'Zero-link race batch unexpectedly generated individual tokens.');

	foreach (array('paid', 'free') as $mode) {
		backstage_discount_concurrency_assert($wpdb->insert($campaign_table, array(
			'campaign_name' => $marker . ' ' . $mode, 'related_source_id' => $source_id, 'related_batch_id' => $batch_id,
			'validity_type' => 'single_event', 'single_event_plan_id' => (int) $event_plan_id,
			'admissions_per_recipient' => 1, 'total_admission_cap' => 1, 'status' => 'active',
			'eligibility_mode' => 'anyone_with_invite', 'created_by' => 1, 'created_at' => $now,
			'campaign_purpose' => 'guest_pass_invitation',
		)) !== false, 'Could not create a race campaign.');
		$campaign_ids[$mode] = (int) $wpdb->insert_id;
		$business_id = backstage_outreach_insert_business(array('business_name' => $marker . ' ' . $mode), 1);
		backstage_discount_concurrency_assert($business_id > 0 && backstage_outreach_business_upsert_membership($source_id, $business_id, 'manual', null, 0, array(), 1), 'Could not create a race business.');
		$business_ids[$mode] = $business_id;
	}

	$paid_campaign = vms_pass_outreach_get_campaign_by_id($campaign_ids['paid']);
	$free_campaign = vms_pass_outreach_get_campaign_by_id($campaign_ids['free']);
	$batch = bvmgr_pass_claims_get_batch_by_id($batch_id);
	$configuration = backstage_outreach_discount_offer_configuration($paid_campaign, $batch, 'coupon_backed', true);
	backstage_discount_concurrency_assert(is_array($configuration) && in_array($ticket_id, $configuration['product_ids'], true), 'The paid offer did not resolve its eligible ticket from the shared free batch.');
	$paid_distribution_id = backstage_outreach_create_distribution($campaign_ids['paid'], $source_id, $business_ids['paid'], 'coupon_backed', 1, 0, '', $configuration['event_ids'], $configuration['product_ids'], 1);
	$free_distribution_id = backstage_outreach_create_distribution($campaign_ids['free'], $source_id, $business_ids['free'], 'complimentary', 1, 0, '', array(), array(), 1);
	backstage_discount_concurrency_assert($paid_distribution_id > 0 && $free_distribution_id > 0, 'Could not create both race distributions.');
	$distribution_ids = array($paid_distribution_id, $free_distribution_id);
	$paid_distribution = backstage_outreach_discount_get_distribution($paid_distribution_id);
	$coupon_result = backstage_outreach_discount_ensure_coupon($paid_distribution, $paid_campaign, $configuration);
	backstage_discount_concurrency_assert(is_array($coupon_result) && !empty($coupon_result['coupon_id']), 'Could not create the race coupon.');
	$coupon_id = (int) $coupon_result['coupon_id'];
	$wpdb->update(backstage_outreach_business_table('campaign_businesses'), array('coupon_id' => $coupon_id, 'coupon_code' => (string) $coupon_result['coupon_code']), array('id' => $paid_distribution_id));
	$paid_distribution = backstage_outreach_discount_get_distribution($paid_distribution_id);
	$free_distribution = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d', backstage_outreach_business_table('campaign_businesses'), $free_distribution_id), ARRAY_A);
	$free_token = backstage_outreach_distribution_token($free_distribution);

	$order = wc_create_order();
	backstage_discount_concurrency_assert(!is_wp_error($order), 'Could not create the race order.');
	$order_id = $order->get_id();
	$order->set_billing_email('paid-race@example.test');
	foreach (array('distribution' => $paid_distribution_id, 'campaign' => $campaign_ids['paid'], 'source' => $source_id, 'business' => $business_ids['paid'], 'coupon' => $coupon_id) as $key => $value) {
		$order->update_meta_data('_backstage_outreach_' . $key . '_id', $value);
	}
	$order->update_meta_data('_backstage_outreach_offer_type', 'coupon_backed');
	$order->add_product(wc_get_product($ticket_id), 1);
	$order->save();
	$coupon_applied = $order->apply_coupon((string) $paid_distribution['coupon_code']);
	backstage_discount_concurrency_assert(!is_wp_error($coupon_applied), 'Could not apply the race coupon.');
	$order->calculate_totals();
	$order->save();
	backstage_discount_concurrency_assert((int) backstage_outreach_discount_order_ticket_snapshot($order, $paid_distribution)['quantity'] === 1, 'The race order did not retain its eligible ticket before forking.');

	$result_files = array(tempnam(sys_get_temp_dir(), 'bvm-paid-race-'), tempnam(sys_get_temp_dir(), 'bvm-free-race-'));
	$fixture_option = 'backstage_outreach_discount_race_' . strtolower(wp_generate_password(12, false, false));
	update_option($fixture_option, array(
		'order_id' => $order_id,
		'free_token' => $free_token,
		'event_plan_id' => (int) $event_plan_id,
		'start_at' => microtime(true) + 2.0,
	), false);
	$worker = getenv('BACKSTAGE_OUTREACH_CONCURRENCY_WORKER');
	if (!is_string($worker) || $worker === '') {
		$worker = ABSPATH . 'wp-content/plugins/packages/vms-github-reconcile/tests/business-discount-qr-concurrency-worker.php';
	}
	$wp_cli = getenv('BACKSTAGE_OUTREACH_WP_CLI');
	if (!is_string($wp_cli) || $wp_cli === '') {
		$wp_cli = '/opt/homebrew/bin/wp';
	}
	backstage_discount_concurrency_assert(is_file($worker), 'The clean WordPress race worker is unavailable.');
	$processes = array();
	foreach (array('paid', 'free') as $index => $mode) {
		$descriptors = array(
			0 => array('pipe', 'r'),
			1 => array('file', $result_files[$index], 'w'),
			2 => array('file', '/dev/null', 'a'),
		);
		$process = proc_open(array($wp_cli, 'eval-file', $worker, $mode, $fixture_option), $descriptors, $pipes, ABSPATH);
		backstage_discount_concurrency_assert(is_resource($process), 'Could not start a clean WordPress race worker.');
		fclose($pipes[0]);
		$processes[] = $process;
	}
	foreach ($processes as $process) {
		backstage_discount_concurrency_assert(proc_close($process) === 0, 'A clean WordPress race worker failed.');
	}
	wp_cache_flush();

	$paid_output = (string) file_get_contents($result_files[0]);
	$free_output = (string) file_get_contents($result_files[1]);
	$paid_result = preg_match('/RACE_RESULT:(won|lost:[^\r\n]*)/', $paid_output, $paid_match) ? $paid_match[1] : 'worker-output-missing';
	$free_result = preg_match('/RACE_RESULT:(won|lost:[^\r\n]*)/', $free_output, $free_match) ? $free_match[1] : 'worker-output-missing';
	$paid_quantity = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(ticket_quantity),0) FROM %i WHERE order_id=%d AND status='pending'", backstage_outreach_business_table('paid_redemptions'), $order_id));
	$free_quantity = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(party_size),0) FROM %i WHERE pass_batch_id=%d AND status<>'canceled'", bvmgr_admission_table_entries(), $batch_id));
	backstage_discount_concurrency_assert(($paid_result === 'won') xor ($free_result === 'won'), 'The concurrent last-slot race did not produce exactly one winner.');
	backstage_discount_concurrency_assert($paid_quantity + $free_quantity === 1, 'Paid and complimentary racers exceeded or lost the shared one-admission cap.');

	if ($paid_result === 'won') {
		backstage_outreach_discount_reserve_order(new WC_Order($order_id));
		$replay_context = backstage_outreach_distribution_context($free_token);
		$replay = backstage_outreach_partner_claim($replay_context, bvmgr_pass_claims_get_event_plan_brief((int) $event_plan_id), array('first_name' => 'Free', 'last_name' => 'Racer', 'phone' => '3125550199', 'email' => '', 'party_size' => 1, 'opt_in' => 0), 'shared-last-slot-race');
		backstage_discount_concurrency_assert(is_wp_error($replay), 'Complimentary replay bypassed a paid last-slot reservation.');
	} else {
		$replay_context = backstage_outreach_distribution_context($free_token);
		$replay = backstage_outreach_partner_claim($replay_context, bvmgr_pass_claims_get_event_plan_brief((int) $event_plan_id), array('first_name' => 'Free', 'last_name' => 'Racer', 'phone' => '3125550199', 'email' => '', 'party_size' => 1, 'opt_in' => 0), 'shared-last-slot-race');
		backstage_discount_concurrency_assert(!is_wp_error($replay), 'A successful complimentary submission was not replay-idempotent.');
		$paid_replay_blocked = false;
		try {
			backstage_outreach_discount_reserve_order(new WC_Order($order_id));
		} catch (Throwable $error) {
			$paid_replay_blocked = true;
		}
		backstage_discount_concurrency_assert($paid_replay_blocked, 'Paid replay bypassed a complimentary last-slot claim.');
	}
	$mapping_count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE distribution_id=%d', backstage_outreach_business_table('distribution_claims'), $free_distribution_id));
	$ledger_count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE order_id=%d', backstage_outreach_business_table('paid_redemptions'), $order_id));
	backstage_discount_concurrency_assert($mapping_count <= 1 && $ledger_count <= 1, 'Replay created duplicate claim or paid-ledger rows.');
	$internal_token_count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE batch_id=%d', bvmgr_admission_table_pass_tokens(), $batch_id));
	$unclaimed_internal_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE batch_id=%d AND status='unclaimed'", bvmgr_admission_table_pass_tokens(), $batch_id));
	$generated_count = (int) $wpdb->get_var($wpdb->prepare('SELECT generated_count FROM %i WHERE id=%d', $batch_table, $batch_id));
	backstage_discount_concurrency_assert($internal_token_count === $free_quantity && $unclaimed_internal_count === 0, 'The concurrent complimentary path retained or exposed an internal token incorrectly.');
	backstage_discount_concurrency_assert($generated_count === 0, 'The concurrent complimentary path incremented generated_count.');
	$lock_available = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', 'bvm-pass-batch-' . $batch_id, 1));
	backstage_discount_concurrency_assert($lock_available === 1, 'The shared batch lock was not released after the race.');
	$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', 'bvm-pass-batch-' . $batch_id));

	echo "Business discount QR concurrent last-slot PASS\n";
	echo wp_json_encode(array('paid' => $paid_result, 'complimentary' => $free_result, 'shared_quantity' => $paid_quantity + $free_quantity, 'paid_ledger_rows' => $ledger_count, 'complimentary_mapping_rows' => $mapping_count, 'internal_tokens' => $internal_token_count, 'unclaimed_internal_tokens' => $unclaimed_internal_count, 'generated_count' => $generated_count), JSON_PRETTY_PRINT) . "\n";
} finally {
	if ($fixture_option !== '') {
		delete_option($fixture_option);
	}
	foreach ($result_files as $result_file) {
		if (is_string($result_file) && file_exists($result_file)) {
			unlink($result_file);
		}
	}
	if ($order_id > 0) {
		$order = wc_get_order($order_id);
		if ($order) {
			$order->delete(true);
		}
	}
	if ($coupon_id > 0) {
		$coupon = new WC_Coupon($coupon_id);
		if ($coupon->get_id() > 0) {
			$coupon->delete(true);
		}
	}
	if ($batch_id > 0) {
		$claim_ids = array_map('absint', $wpdb->get_col($wpdb->prepare('SELECT id FROM %i WHERE batch_id=%d', bvmgr_admission_table_pass_claims(), $batch_id)));
		$wpdb->delete(bvmgr_admission_table_entries(), array('pass_batch_id' => $batch_id));
		foreach ($claim_ids as $claim_id) {
			$wpdb->delete(backstage_outreach_business_table('distribution_claims'), array('pass_claim_id' => $claim_id));
		}
		$wpdb->delete(bvmgr_admission_table_pass_claims(), array('batch_id' => $batch_id));
		$wpdb->delete(bvmgr_admission_table_pass_tokens(), array('batch_id' => $batch_id));
	}
	foreach ($campaign_ids as $campaign_id) {
		$wpdb->delete(backstage_outreach_business_table('paid_redemptions'), array('campaign_id' => $campaign_id));
		$wpdb->delete(backstage_outreach_business_table('distribution_claims'), array('campaign_id' => $campaign_id));
		$wpdb->delete(backstage_outreach_business_table('campaign_businesses'), array('campaign_id' => $campaign_id));
		$wpdb->delete(vms_admission_table_pass_outreach_campaigns(), array('id' => $campaign_id));
	}
	foreach ($business_ids as $business_id) {
		$wpdb->delete(backstage_outreach_business_table('source_businesses'), array('business_id' => $business_id));
		$wpdb->delete(backstage_outreach_business_table('businesses'), array('id' => $business_id));
	}
	if ($batch_id > 0) {
		$wpdb->delete(bvmgr_admission_table_pass_batches(), array('id' => $batch_id));
	}
	if ($source_id > 0) {
		$wpdb->delete(bvmgr_admission_table_pass_sources(), array('id' => $source_id));
	}
	foreach (array_reverse($post_ids) as $post_id) {
		wp_delete_post((int) $post_id, true);
	}
}
