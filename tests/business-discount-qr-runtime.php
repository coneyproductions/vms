<?php
/**
 * Disposable local integration exercise. Run with:
 * wp eval-file wp-content/plugins/packages/vms-github-reconcile/tests/business-discount-qr-runtime.php
 */

defined('ABSPATH') || exit;

function backstage_discount_runtime_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

if (!class_exists('WooCommerce') || !class_exists('WC_Coupon') || !function_exists('backstage_outreach_discount_ensure_coupon')) {
	throw new RuntimeException('Required WooCommerce and Backstage Outreach runtime is unavailable.');
}

global $wpdb;
$marker = 'BVM discount QR synthetic ' . wp_generate_password(8, false, false);
$now = backstage_outreach_business_now();
$user_id = 1;
$post_ids = array();
$order_ids = array();
$coupon_ids = array();
$business_ids = array();
$distribution_ids = array();
$source_id = 0;
$batch_id = 0;
$campaign_id = 0;

add_filter('pre_wp_mail', static fn() => true, PHP_INT_MAX);

try {
	$event_start = wp_date('Y-m-d 19:00:00', time() + (14 * DAY_IN_SECONDS), wp_timezone());
	$event_end = wp_date('Y-m-d 22:00:00', time() + (14 * DAY_IN_SECONDS), wp_timezone());
	$tec_event_id = tribe_create_event(array('post_status' => 'publish', 'post_title' => $marker . ' event', 'EventStartDate' => $event_start, 'EventEndDate' => $event_end));
	backstage_discount_runtime_assert(!is_wp_error($tec_event_id) && $tec_event_id > 0, 'Could not create synthetic TEC event.');
	$post_ids[] = (int) $tec_event_id;

	$event_plan_id = wp_insert_post(array('post_type' => 'vms_event_plan', 'post_status' => 'publish', 'post_title' => $marker . ' plan'), true);
	backstage_discount_runtime_assert(!is_wp_error($event_plan_id) && $event_plan_id > 0, 'Could not create synthetic event plan.');
	$post_ids[] = (int) $event_plan_id;
	$status_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status';
	update_post_meta((int) $event_plan_id, $status_key, 'published');
	update_post_meta((int) $event_plan_id, '_vms_event_date', wp_date('Y-m-d', time() + (14 * DAY_IN_SECONDS)));
	update_post_meta((int) $event_plan_id, '_vms_tec_event_id', (int) $tec_event_id);

	$ticket_provider = function_exists('tribe') ? tribe('tickets-plus.commerce.woo') : null;
	$ticket_id = is_object($ticket_provider) ? (int) $ticket_provider->ticket_add((int) $tec_event_id, array(
		'ticket_name' => $marker . ' eligible ticket',
		'ticket_description' => 'Disposable runtime fixture',
		'ticket_price' => '100',
		'ticket_show_description' => 'no',
		'tribe-ticket' => array('capacity' => 100, 'mode' => 'own'),
	)) : 0;
	backstage_discount_runtime_assert($ticket_id > 0, 'Could not create synthetic eligible ticket.');
	$post_ids[] = $ticket_id;
	$ticket = wc_get_product($ticket_id);
	$ticket->set_status('publish');
	$ticket->set_tax_status('none');
	$ticket->set_virtual(true);
	$ticket->save();
	backstage_discount_runtime_assert((bool) $ticket_provider->get_ticket((int) $tec_event_id, $ticket_id), 'Event Tickets did not recognize its synthetic Woo ticket.');
	update_post_meta($ticket_id, '_vms_event_plan_id', (int) $event_plan_id);
	update_post_meta($ticket_id, '_vms_product_role', 'ga_ticket');

	$unrelated = new WC_Product_Simple();
	$unrelated->set_name($marker . ' unrelated item');
	$unrelated->set_status('publish');
	$unrelated->set_regular_price('40');
	$unrelated->set_price('40');
	$unrelated->set_tax_status('none');
	$unrelated_id = $unrelated->save();
	backstage_discount_runtime_assert($unrelated_id > 0, 'Could not create synthetic unrelated product.');
	$post_ids[] = $unrelated_id;

	$source_table = bvmgr_admission_table_pass_sources();
	$batch_table = bvmgr_admission_table_pass_batches();
	$campaign_table = vms_admission_table_pass_outreach_campaigns();
	backstage_discount_runtime_assert($wpdb->insert($source_table, array('source_name' => $marker, 'status' => 'active', 'created_by' => $user_id, 'created_at' => $now)) !== false, 'Could not create synthetic Source.');
	$source_id = (int) $wpdb->insert_id;
	backstage_discount_runtime_assert($wpdb->insert($batch_table, array(
		'source_id' => $source_id, 'batch_name' => $marker, 'quantity' => 20, 'validity_type' => 'single_event',
		'single_event_plan_id' => (int) $event_plan_id, 'venue_ids_json' => '[]', 'value_type' => 'free',
		'value_amount' => '0.00', 'applies_to' => 'entry_only', 'status' => 'active', 'checkin_open_mode' => 'same_day',
		'max_per_phone' => 0, 'generated_count' => 0, 'created_by' => $user_id, 'created_at' => $now,
		'admissions_per_link' => 2, 'total_admission_cap' => 12, 'max_per_email' => 0,
	)) !== false, 'Could not create synthetic shared-capacity batch.');
	$batch_id = (int) $wpdb->insert_id;
	backstage_discount_runtime_assert($wpdb->insert($campaign_table, array(
		'campaign_name' => $marker, 'related_source_id' => $source_id, 'related_batch_id' => $batch_id,
		'validity_type' => 'single_event', 'single_event_plan_id' => (int) $event_plan_id,
		'admissions_per_recipient' => 2, 'total_admission_cap' => 10, 'status' => 'active',
		'eligibility_mode' => 'anyone_with_invite', 'created_by' => $user_id, 'created_at' => $now,
		'campaign_purpose' => 'guest_pass_invitation',
	)) !== false, 'Could not create synthetic Outreach campaign.');
	$campaign_id = (int) $wpdb->insert_id;
	$campaign = vms_pass_outreach_get_campaign_by_id($campaign_id);
	$batch = bvmgr_pass_claims_get_batch_by_id($batch_id);
	backstage_discount_runtime_assert(is_array($campaign) && is_array($batch), 'Synthetic campaign or batch did not reload.');
	$configuration = backstage_outreach_discount_offer_configuration($campaign, $batch, 'coupon_backed');
	backstage_discount_runtime_assert(is_array($configuration) && in_array($ticket_id, $configuration['product_ids'], true), 'Server-side eligible ticket resolution failed.');
	backstage_discount_runtime_assert(!in_array($unrelated_id, $configuration['product_ids'], true), 'Unrelated product entered eligible scope.');

	foreach (array('A', 'B') as $label) {
		$business_id = backstage_outreach_insert_business(array('business_name' => $marker . ' business ' . $label), $user_id);
		backstage_discount_runtime_assert($business_id > 0 && backstage_outreach_business_upsert_membership($source_id, $business_id, 'manual', null, 0, array(), $user_id), 'Could not create synthetic business membership.');
		$business_ids[$label] = $business_id;
		$distribution_id = backstage_outreach_create_distribution($campaign_id, $source_id, $business_id, 'coupon_backed', 8, $label === 'A' ? 2 : 20, '', $configuration['event_ids'], $configuration['product_ids'], $user_id);
		backstage_discount_runtime_assert($distribution_id > 0, 'Could not create synthetic coupon distribution.');
		$distribution = backstage_outreach_discount_get_distribution($distribution_id);
		$coupon_result = is_array($distribution) ? backstage_outreach_discount_ensure_coupon($distribution, $campaign, $configuration) : null;
		backstage_discount_runtime_assert(is_array($coupon_result) && !empty($coupon_result['coupon_id']), 'Could not create managed native coupon.');
		$wpdb->update(backstage_outreach_business_table('campaign_businesses'), array('coupon_id' => (int) $coupon_result['coupon_id'], 'coupon_code' => (string) $coupon_result['coupon_code']), array('id' => $distribution_id));
		$distribution_ids[$label] = $distribution_id;
		$coupon_ids[$label] = (int) $coupon_result['coupon_id'];
	}

	$distribution_a = backstage_outreach_discount_get_distribution($distribution_ids['A']);
	$distribution_b = backstage_outreach_discount_get_distribution($distribution_ids['B']);
	backstage_discount_runtime_assert(is_array($distribution_a) && is_array($distribution_b) && $distribution_a['coupon_code'] !== $distribution_b['coupon_code'], 'Per-business managed coupon attribution is not distinct.');
	$managed_coupon_a = new WC_Coupon($coupon_ids['A']);
	$commerce_top_up = backstage_outreach_discount_exact_total(40.0, 80.0, array(
		'_vms_discounts_original_unit_price' => 100.0,
		'_vms_discounts_line_discount' => 20.0,
		'quantity' => 1,
	), false, $managed_coupon_a);
	$commerce_excess = backstage_outreach_discount_exact_total(20.0, 40.0, array(
		'_vms_discounts_original_unit_price' => 100.0,
		'_vms_discounts_line_discount' => 60.0,
		'quantity' => 1,
	), false, $managed_coupon_a);
	backstage_discount_runtime_assert(abs((float) $commerce_top_up - 30.0) < 0.01 && abs((float) $commerce_excess) < 0.01, 'Commerce Discount reconciliation did not top up to exactly 50% without over-stacking.');

	$ordinary_coupon = new WC_Coupon();
	$ordinary_coupon->set_code('bvm-test-' . strtolower(wp_generate_password(10, false, false)));
	$ordinary_coupon->set_discount_type('percent');
	$ordinary_coupon->set_amount('10');
	$ordinary_coupon->set_status('publish');
	$ordinary_coupon_id = $ordinary_coupon->save();
	$coupon_ids['ordinary'] = $ordinary_coupon_id;

	WC()->cart->empty_cart(true);
	backstage_outreach_discount_session_clear();
	WC()->cart->add_to_cart($unrelated_id, 1);
	WC()->cart->add_to_cart($ticket_id, 1);
	WC()->cart->apply_coupon($ordinary_coupon->get_code());
	backstage_outreach_discount_session_set($distribution_a, backstage_outreach_distribution_token($distribution_a));
	backstage_outreach_discount_sync_cart_coupon();
	WC()->cart->calculate_totals();
	$applied = array_map('wc_format_coupon_code', WC()->cart->get_applied_coupons());
	backstage_discount_runtime_assert(count(WC()->cart->get_cart()) === 2, 'Activating the offer did not preserve existing cart contents.');
	backstage_discount_runtime_assert(in_array(wc_format_coupon_code((string) $distribution_a['coupon_code']), $applied, true) && !in_array(wc_format_coupon_code($ordinary_coupon->get_code()), $applied, true), 'Managed individual-use coupon did not prevent coupon stacking.');
	backstage_discount_runtime_assert(abs((float) WC()->cart->get_discount_total() - 50.0) < 0.01, 'Eligible ticket did not receive exactly 50% off.');
	backstage_discount_runtime_assert(abs((float) WC()->cart->get_total('edit') - 90.0) < 0.01, 'Unrelated product price or reconciled cart total is incorrect.');
	$ordinary_replacement = WC()->cart->apply_coupon($ordinary_coupon->get_code());
	$applied = array_map('wc_format_coupon_code', WC()->cart->get_applied_coupons());
	backstage_discount_runtime_assert($ordinary_replacement === false && in_array(wc_format_coupon_code((string) $distribution_a['coupon_code']), $applied, true), 'A manually entered coupon replaced or stacked with the managed individual-use coupon.');
	WC()->cart->remove_coupon((string) $distribution_a['coupon_code']);
	backstage_outreach_discount_sync_cart_coupon();
	backstage_discount_runtime_assert(WC()->cart->has_discount((string) $distribution_a['coupon_code']), 'The valid signed session did not restore its removed managed coupon.');
	backstage_outreach_discount_session_set($distribution_b, backstage_outreach_distribution_token($distribution_b));
	backstage_outreach_discount_sync_cart_coupon();
	backstage_discount_runtime_assert(WC()->cart->has_discount((string) $distribution_b['coupon_code']) && !WC()->cart->has_discount((string) $distribution_a['coupon_code']) && count(WC()->cart->get_cart()) === 2, 'Switching signed business context did not replace only the managed coupon while preserving the cart.');
	WC()->session->set(BACKSTAGE_OUTREACH_DISCOUNT_SESSION_KEY, array('distribution_id' => $distribution_ids['A'], 'token_hash' => str_repeat('0', 64), 'activated_at' => time()));
	backstage_outreach_discount_sync_cart_coupon();
	backstage_discount_runtime_assert(empty(backstage_outreach_discount_session_get()) && !WC()->cart->has_discount((string) $distribution_b['coupon_code']), 'A forged restored session retained a managed coupon.');
	backstage_outreach_discount_session_set($distribution_a, backstage_outreach_distribution_token($distribution_a));
	backstage_outreach_discount_sync_cart_coupon();

	WC()->cart->remove_coupon((string) $distribution_a['coupon_code']);
	backstage_outreach_discount_session_clear();
	$direct_apply = WC()->cart->apply_coupon((string) $distribution_a['coupon_code']);
	backstage_discount_runtime_assert($direct_apply === false, 'Managed coupon bypassed the required signed offer context.');

	$make_order = static function (array $distribution, string $email, bool $include_coupon = true) use ($ticket_id, $unrelated_id, &$order_ids): WC_Order {
		$order = wc_create_order();
		if (is_wp_error($order)) {
			throw new RuntimeException($order->get_error_message());
		}
		$order_ids[] = $order->get_id();
		$order->set_billing_first_name('Synthetic');
		$order->set_billing_last_name('Customer');
		$order->set_billing_email($email);
		$order->set_created_via('business-discount-runtime-test');
		$order->update_meta_data('_backstage_outreach_distribution_id', (int) $distribution['id']);
		$order->update_meta_data('_backstage_outreach_campaign_id', (int) $distribution['campaign_id']);
		$order->update_meta_data('_backstage_outreach_source_id', (int) $distribution['source_id']);
		$order->update_meta_data('_backstage_outreach_business_id', (int) $distribution['business_id']);
		$order->update_meta_data('_backstage_outreach_coupon_id', (int) $distribution['coupon_id']);
		$order->update_meta_data('_backstage_outreach_offer_type', 'coupon_backed');
		$order->add_product(wc_get_product($ticket_id), 1);
		$order->add_product(wc_get_product($unrelated_id), 1);
		$order->save();
		if ($include_coupon) {
			$result = $order->apply_coupon((string) $distribution['coupon_code']);
			if (is_wp_error($result)) {
				throw new RuntimeException($result->get_error_message());
			}
		}
		$order->calculate_totals();
		$order->save();
		return $order;
	};

	$a1 = $make_order($distribution_a, 'customer-a1@example.test');
	backstage_outreach_discount_reserve_order($a1);
	$a1->payment_complete('synthetic-a1');
	$a1_row_before_replay = $wpdb->get_row($wpdb->prepare('SELECT paid_at FROM %i WHERE order_id=%d', backstage_outreach_business_table('paid_redemptions'), $a1->get_id()), ARRAY_A);
	backstage_outreach_discount_mark_paid($a1->get_id());
	backstage_outreach_discount_mark_paid($a1->get_id());
	$a1_row_after_replay = $wpdb->get_row($wpdb->prepare('SELECT status, paid_at FROM %i WHERE order_id=%d', backstage_outreach_business_table('paid_redemptions'), $a1->get_id()), ARRAY_A);
	backstage_discount_runtime_assert($a1_row_after_replay['status'] === 'paid' && $a1_row_before_replay['paid_at'] === $a1_row_after_replay['paid_at'], 'Repeated paid callbacks changed redemption state or its original paid timestamp.');
	$a2 = $make_order($distribution_a, 'customer-a2@example.test');
	backstage_outreach_discount_reserve_order($a2);
	$a2->payment_complete('synthetic-a2');
	backstage_discount_runtime_assert($a1->get_id() !== $a2->get_id() && abs((float) $a1->get_total() - 90.0) < 0.01 && abs((float) $a2->get_total() - 90.0) < 0.01, 'Two independent customers did not receive separate correctly discounted orders.');
	$stats_a = backstage_outreach_discount_paid_stats($distribution_ids['A']);
	backstage_discount_runtime_assert((int) $stats_a['paid_orders'] === 2 && (int) $stats_a['discounted_tickets'] === 2, 'Paid redemption reporting counted the same-business orders incorrectly.');
	$order_limit_blocked = false;
	try {
		$make_order($distribution_a, 'customer-a3@example.test');
	} catch (Throwable $error) {
		$order_limit_blocked = true;
	}
	backstage_discount_runtime_assert($order_limit_blocked, 'The native managed-coupon paid-order usage limit was not enforced.');
	backstage_outreach_discount_session_clear();
	backstage_discount_runtime_assert((int) backstage_outreach_discount_order_context(wc_get_order($a1->get_id()))['business_id'] === $business_ids['A'], 'Order attribution did not survive a refreshed session.');

	$b1 = $make_order($distribution_b, 'customer-b1@example.test');
	backstage_outreach_discount_reserve_order($b1);
	$b1->update_status('failed');
	$failed_before_replay = $wpdb->get_row($wpdb->prepare('SELECT status, failed_at FROM %i WHERE order_id=%d', backstage_outreach_business_table('paid_redemptions'), $b1->get_id()), ARRAY_A);
	backstage_outreach_discount_mark_failed($b1->get_id());
	$failed_after_replay = $wpdb->get_row($wpdb->prepare('SELECT status, failed_at FROM %i WHERE order_id=%d', backstage_outreach_business_table('paid_redemptions'), $b1->get_id()), ARRAY_A);
	$status = (string) ($failed_after_replay['status'] ?? '');
	backstage_discount_runtime_assert($status === 'failed', 'Failed payment did not release its pending redemption.');
	backstage_discount_runtime_assert($failed_before_replay['failed_at'] === $failed_after_replay['failed_at'], 'Repeated failed callbacks changed the first failure timestamp.');
	backstage_outreach_discount_validate_order_before_payment($b1);
	$b1->payment_complete('synthetic-b1-retry');
	backstage_outreach_discount_mark_failed($b1->get_id());
	$context_b = backstage_outreach_discount_order_context(wc_get_order($b1->get_id()));
	backstage_discount_runtime_assert(is_array($context_b) && (int) $context_b['business_id'] === $business_ids['B'], 'Checkout retry crossed business attribution.');
	backstage_discount_runtime_assert($wpdb->get_var($wpdb->prepare('SELECT status FROM %i WHERE order_id=%d', backstage_outreach_business_table('paid_redemptions'), $b1->get_id())) === 'paid', 'A delayed failed callback downgraded a successfully retried paid order.');

	$wpdb->update(backstage_outreach_business_table('campaign_businesses'), array('admission_cap' => 2), array('id' => $distribution_ids['B']));
	$distribution_b = backstage_outreach_discount_get_distribution($distribution_ids['B']);
	$expired_hold = $make_order($distribution_b, 'expired-hold@example.test');
	backstage_outreach_discount_reserve_order($expired_hold);
	$wpdb->update(backstage_outreach_business_table('paid_redemptions'), array('reservation_expires_at' => wp_date('Y-m-d H:i:s', time() - MINUTE_IN_SECONDS, wp_timezone())), array('order_id' => $expired_hold->get_id()));
	$replacement_hold = $make_order($distribution_b, 'replacement-hold@example.test');
	backstage_outreach_discount_reserve_order($replacement_hold);
	backstage_discount_runtime_assert($wpdb->get_var($wpdb->prepare('SELECT status FROM %i WHERE order_id=%d', backstage_outreach_business_table('paid_redemptions'), $replacement_hold->get_id())) === 'pending', 'An expired reservation did not release its last slot for a new checkout.');
	$replacement_hold->update_status('cancelled');
	$expired_hold->update_status('cancelled');
	$wpdb->update(backstage_outreach_business_table('campaign_businesses'), array('admission_cap' => 8), array('id' => $distribution_ids['B']));
	$distribution_b = backstage_outreach_discount_get_distribution($distribution_ids['B']);

	$b2 = $make_order($distribution_b, 'customer-b2@example.test');
	backstage_outreach_discount_reserve_order($b2);
	$b2->update_status('cancelled');
	$cancelled_before_replay = $wpdb->get_row($wpdb->prepare('SELECT status, cancelled_at FROM %i WHERE order_id=%d', backstage_outreach_business_table('paid_redemptions'), $b2->get_id()), ARRAY_A);
	backstage_outreach_discount_mark_cancelled($b2->get_id());
	backstage_outreach_discount_mark_paid($b2->get_id());
	$cancelled_after_replay = $wpdb->get_row($wpdb->prepare('SELECT status, cancelled_at FROM %i WHERE order_id=%d', backstage_outreach_business_table('paid_redemptions'), $b2->get_id()), ARRAY_A);
	backstage_discount_runtime_assert($cancelled_after_replay['status'] === 'cancelled' && $cancelled_before_replay['cancelled_at'] === $cancelled_after_replay['cancelled_at'], 'Cancellation replay changed the released reservation or resurrected it as paid.');

	$b3 = $make_order($distribution_b, 'customer-b3@example.test');
	backstage_outreach_discount_reserve_order($b3);
	$b3->payment_complete('synthetic-b3');
	$refund = wc_create_refund(array('order_id' => $b3->get_id(), 'amount' => $b3->get_total(), 'reason' => $marker, 'refund_payment' => false, 'restock_items' => false));
	backstage_discount_runtime_assert(!is_wp_error($refund), 'Synthetic full refund failed.');
	backstage_outreach_discount_record_refund($b3->get_id());
	backstage_outreach_discount_mark_paid($b3->get_id());
	backstage_discount_runtime_assert($wpdb->get_var($wpdb->prepare('SELECT status FROM %i WHERE order_id=%d', backstage_outreach_business_table('paid_redemptions'), $b3->get_id())) === 'refunded', 'Full refund replay resurrected the redemption or failed to release it.');

	$b4 = $make_order($distribution_b, 'customer-b4@example.test');
	backstage_outreach_discount_reserve_order($b4);
	$b4->payment_complete('synthetic-b4');
	$eligible_order_item_id = 0;
	foreach ($b4->get_items('line_item') as $item_id => $item) {
		if ((int) $item->get_product_id() === $ticket_id) {
			$eligible_order_item_id = (int) $item_id;
			break;
		}
	}
	backstage_discount_runtime_assert($eligible_order_item_id > 0, 'The eligible ticket order line could not be identified for an item-specific refund.');
	$partial_refund = wc_create_refund(array(
		'order_id' => $b4->get_id(), 'amount' => 10, 'reason' => $marker . ' partial', 'refund_payment' => false, 'restock_items' => false,
		'line_items' => array($eligible_order_item_id => array('qty' => 0, 'refund_total' => 10, 'refund_tax' => array())),
	));
	backstage_discount_runtime_assert(!is_wp_error($partial_refund), 'Synthetic partial refund failed.');
	$partial_row = $wpdb->get_row($wpdb->prepare('SELECT status, refunded_total, eligible_ticket_refunded_total FROM %i WHERE order_id=%d', backstage_outreach_business_table('paid_redemptions'), $b4->get_id()), ARRAY_A);
	backstage_discount_runtime_assert(is_array($partial_row) && $partial_row['status'] === 'paid' && abs((float) $partial_row['refunded_total'] - 10.0) < 0.01 && abs((float) $partial_row['eligible_ticket_refunded_total'] - 10.0) < 0.01, 'Item-specific partial refund did not preserve paid attendance classification and admission accounting.');

	$without_coupon = $make_order($distribution_b, 'coupon-removed@example.test', false);
	$coupon_removed_blocked = false;
	try {
		backstage_outreach_discount_validate_order_before_payment($without_coupon);
	} catch (Throwable $error) {
		$coupon_removed_blocked = str_contains($error->getMessage(), 'coupon was removed');
	}
	backstage_discount_runtime_assert($coupon_removed_blocked, 'An attributed unpaid order bypassed validation after coupon removal.');
	wc_clear_notices();
	backstage_outreach_discount_validate_order_pay_action($without_coupon);
	backstage_discount_runtime_assert(wc_notice_count('error') > 0, 'Classic order-payment validation did not block safely through a WooCommerce error notice.');
	wc_clear_notices();
	$removed_after_reservation = $make_order($distribution_b, 'coupon-removed-after-reservation@example.test');
	backstage_outreach_discount_reserve_order($removed_after_reservation);
	$removed_after_reservation->remove_coupon((string) $distribution_b['coupon_code']);
	$removed_after_reservation->calculate_totals(false);
	$removed_after_reservation->save();
	$removed_after_reservation->update_status('cancelled');
	backstage_discount_runtime_assert($wpdb->get_var($wpdb->prepare('SELECT status FROM %i WHERE order_id=%d', backstage_outreach_business_table('paid_redemptions'), $removed_after_reservation->get_id())) === 'cancelled', 'Coupon removal prevented cancellation from releasing an existing paid-offer reservation.');

	$tampered = $make_order($distribution_b, 'pricing-tampered@example.test');
	foreach ($tampered->get_items('line_item') as $item) {
		if ((int) $item->get_product_id() === $ticket_id) {
			$item->set_total(60);
			$item->save();
		}
	}
	$tampered->calculate_totals(false);
	$tampered->save();
	$pricing_blocked = false;
	try {
		backstage_outreach_discount_validate_order_before_payment($tampered);
	} catch (Throwable $error) {
		$pricing_blocked = str_contains($error->getMessage(), 'no longer matches');
	}
	backstage_discount_runtime_assert($pricing_blocked, 'An unpaid order-payment retry bypassed exact 50% pricing validation.');

	$extra_coupon_order = $make_order($distribution_b, 'extra-coupon@example.test');
	$extra_coupon_item = new WC_Order_Item_Coupon();
	$extra_coupon_item->set_code($ordinary_coupon->get_code());
	$extra_coupon_item->set_discount(0);
	$extra_coupon_order->add_item($extra_coupon_item);
	$extra_coupon_order->save();
	$single_coupon_blocked = false;
	try {
		backstage_outreach_discount_validate_order_before_payment($extra_coupon_order);
	} catch (Throwable $error) {
		$single_coupon_blocked = str_contains($error->getMessage(), 'only its managed');
	}
	backstage_discount_runtime_assert($single_coupon_blocked, 'An unpaid order-payment retry retained an additional coupon.');
	$forged_attribution = $make_order($distribution_b, 'forged-attribution@example.test');
	$forged_attribution->update_meta_data('_backstage_outreach_business_id', $business_ids['A']);
	$forged_attribution->save_meta_data();
	$attribution_blocked = false;
	try {
		backstage_outreach_discount_validate_order_before_payment($forged_attribution);
	} catch (Throwable $error) {
		$attribution_blocked = str_contains($error->getMessage(), 'server-owned');
	}
	backstage_discount_runtime_assert($attribution_blocked, 'An unpaid order-payment retry trusted mismatched submitted attribution IDs.');

	// The CLI-created orders bypass the normal checkout-update hook; invoke the
	// same native Event Tickets fulfillment service that the checkout hook uses.
	add_filter('event_tickets_woo_ticket_generating_order_stati', static fn() => array('immediate'), PHP_INT_MAX);
	$a1->update_status('completed');
	$ticket_item_name = '';
	foreach ($a1->get_items('line_item') as $item) {
		if ((int) $item->get_product_id() === $ticket_id) {
			$ticket_item_name = (string) $item->get_name();
		}
	}
	backstage_discount_runtime_assert($ticket_item_name === (string) wc_get_product($ticket_id)->get_name(), 'The discounted order changed the existing ticket line name used by Square and fulfillment.');
	$ticket_provider->generate_tickets($a1->get_id());
	$ticket_provider->generate_tickets($a2->get_id());
	$attendees = array_map('absint', $wpdb->get_col($wpdb->prepare(
		"SELECT post_id FROM %i WHERE meta_key='_tribe_wooticket_order' AND meta_value IN (%d,%d)",
		$wpdb->postmeta, $a1->get_id(), $a2->get_id()
	)));
	backstage_discount_runtime_assert(count($attendees) >= 2, 'Native Event Tickets fulfillment did not create tickets for the two paid customers.');
	$checked_in = $ticket_provider->checkin($attendees[0], false, (int) $tec_event_id);
	backstage_discount_runtime_assert($checked_in === true && (string) get_post_meta($attendees[0], '_tribe_wooticket_checkedin', true) === '1', 'Native Event Tickets check-in failed for a fulfilled discounted ticket.');

	$pause_order = $make_order($distribution_b, 'pause-order@example.test');
	backstage_outreach_discount_reserve_order($pause_order);
	$wpdb->update(backstage_outreach_business_table('campaign_businesses'), array('status' => 'paused'), array('id' => $distribution_ids['B']));
	backstage_outreach_discount_set_coupon_status($coupon_ids['B'], 'draft', $distribution_ids['B']);
	$paused = backstage_outreach_discount_get_distribution($distribution_ids['B']);
	backstage_discount_runtime_assert(is_wp_error(backstage_outreach_discount_distribution_error($paused)), 'Paused offer remained usable.');
	$paused_context = backstage_outreach_distribution_context(backstage_outreach_distribution_token($paused));
	$paused_error_data = is_wp_error($paused_context) ? $paused_context->get_error_data() : array();
	backstage_discount_runtime_assert(is_wp_error($paused_context) && ($paused_error_data['distribution_type'] ?? '') === 'coupon_backed' && !str_contains($paused_context->get_error_message(), 'Guest Pass'), 'Paused paid-link presentation fell back to complimentary Guest Pass wording.');
	$pause_blocked = false;
	try {
		backstage_outreach_discount_validate_order_before_payment($pause_order);
	} catch (Throwable $error) {
		$pause_blocked = true;
	}
	backstage_discount_runtime_assert($pause_blocked, 'A paused offer remained payable through its existing order-payment URL.');
	backstage_discount_runtime_assert(wc_get_order($a1->get_id())->is_paid() && count($attendees) >= 2, 'Pausing the offer altered an already purchased order or ticket.');
	$wpdb->update(backstage_outreach_business_table('campaign_businesses'), array('status' => 'active', 'expires_at' => wp_date('Y-m-d H:i:s', time() - HOUR_IN_SECONDS, wp_timezone())), array('id' => $distribution_ids['B']));
	backstage_outreach_discount_set_coupon_status($coupon_ids['B'], 'publish', $distribution_ids['B']);
	$expired = backstage_outreach_discount_get_distribution($distribution_ids['B']);
	backstage_discount_runtime_assert(is_wp_error(backstage_outreach_discount_distribution_error($expired)), 'Expired offer remained usable.');
	$expiry_blocked = false;
	try {
		backstage_outreach_discount_validate_order_before_payment($pause_order);
	} catch (Throwable $error) {
		$expiry_blocked = true;
	}
	backstage_discount_runtime_assert($expiry_blocked, 'An expired offer remained payable through its existing order-payment URL.');
	$wpdb->update(backstage_outreach_business_table('campaign_businesses'), array('status' => 'active', 'expires_at' => null), array('id' => $distribution_ids['B']));
	backstage_outreach_discount_set_coupon_status($coupon_ids['B'], 'publish', $distribution_ids['B']);
	$distribution_b = backstage_outreach_discount_get_distribution($distribution_ids['B']);
	$wpdb->update(backstage_outreach_business_table('campaign_businesses'), array('status' => 'revoked', 'expires_at' => null), array('id' => $distribution_ids['A']));
	backstage_outreach_discount_set_coupon_status($coupon_ids['A'], 'draft', $distribution_ids['A']);
	backstage_discount_runtime_assert(wc_get_order($a2->get_id())->is_paid(), 'Revocation altered an already purchased ticket order.');

	$in_flight = $make_order($distribution_b, 'in-flight@example.test');
	backstage_outreach_discount_reserve_order($in_flight);
	$wpdb->update(backstage_outreach_business_table('campaign_businesses'), array('status' => 'revoked'), array('id' => $distribution_ids['B']));
	backstage_outreach_discount_set_coupon_status($coupon_ids['B'], 'draft', $distribution_ids['B']);
	$revoked_blocked = false;
	try {
		backstage_outreach_discount_validate_order_before_payment($in_flight);
	} catch (Throwable $error) {
		$revoked_blocked = true;
	}
	backstage_discount_runtime_assert($revoked_blocked, 'A revoked offer remained payable through its existing order-payment URL.');
	$in_flight->payment_complete('synthetic-already-dispatched');
	$in_flight_row = $wpdb->get_row($wpdb->prepare('SELECT status, settlement_review_code FROM %i WHERE order_id=%d', backstage_outreach_business_table('paid_redemptions'), $in_flight->get_id()), ARRAY_A);
	backstage_discount_runtime_assert($in_flight_row['status'] === 'paid' && $in_flight_row['settlement_review_code'] === 'settled_after_offer_closed', 'A gateway settlement already in flight was not honored and flagged after revocation.');
	$stats_b = backstage_outreach_discount_paid_stats($distribution_ids['B']);
	backstage_discount_runtime_assert((int) $stats_b['paid_orders'] === 3 && (int) $stats_b['discounted_tickets'] === 3, 'Paid orders and discounted ticket quantities did not reconcile after failure, cancellation, refunds, retry, and in-flight settlement.');
	backstage_discount_runtime_assert(abs((float) $stats_b['admission_revenue'] - 140.0) < 0.01 && abs((float) $stats_b['order_revenue'] - 260.0) < 0.01 && abs((float) $stats_b['refunded_total'] - 100.0) < 0.01, 'Admission revenue, whole-order revenue, or refunds did not reconcile.');
	backstage_discount_runtime_assert((int) $stats_b['refunded_orders'] === 1 && (int) $stats_b['cancelled_orders'] === 4 && (int) $stats_b['review_required_orders'] === 1, 'Refund, cancellation, or settlement-review accounting did not reconcile.');

	echo "Business discount QR runtime PASS\n";
	echo wp_json_encode(array(
		'cart_total' => (float) $a1->get_total(),
		'business_a_paid_orders' => 2,
		'business_a_discounted_tickets' => 2,
		'business_b_retry_status' => (string) wc_get_order($b1->get_id())->get_status(),
		'business_b_paid_orders' => (int) $stats_b['paid_orders'],
		'business_b_admission_revenue' => (float) $stats_b['admission_revenue'],
		'business_b_order_revenue' => (float) $stats_b['order_revenue'],
		'in_flight_review' => (string) $in_flight_row['settlement_review_code'],
		'native_attendees' => count($attendees),
		'native_checkin' => 'passed',
		'square_mode' => is_plugin_active('woocommerce-square/woocommerce-square.php') ? 'active' : 'inactive',
	), JSON_PRETTY_PRINT) . "\n";
} finally {
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
	foreach ($coupon_ids as $coupon_id) {
		$coupon = new WC_Coupon((int) $coupon_id);
		if ($coupon->get_id() > 0) {
			$coupon->delete(true);
		}
	}
	if ($campaign_id > 0) {
		$wpdb->delete(backstage_outreach_business_table('paid_redemptions'), array('campaign_id' => $campaign_id));
		$wpdb->delete(backstage_outreach_business_table('distribution_claims'), array('campaign_id' => $campaign_id));
		$wpdb->delete(backstage_outreach_business_table('campaign_businesses'), array('campaign_id' => $campaign_id));
		$wpdb->delete($campaign_table, array('id' => $campaign_id));
	}
	foreach ($business_ids as $business_id) {
		$wpdb->delete(backstage_outreach_business_table('source_businesses'), array('business_id' => $business_id));
		$wpdb->delete(backstage_outreach_business_table('businesses'), array('id' => $business_id));
	}
	if ($batch_id > 0) {
		$wpdb->delete($batch_table, array('id' => $batch_id));
	}
	if ($source_id > 0) {
		$wpdb->delete($source_table, array('id' => $source_id));
	}
	foreach (array_reverse($post_ids) as $post_id) {
		wp_delete_post((int) $post_id, true);
	}
}
