<?php
/**
 * Disposable local percentage/fixed reusable-business offer exercise. Run with:
 * wp eval-file wp-content/plugins/packages/vms-github-reconcile/tests/business-variable-discount-runtime.php
 */

defined('ABSPATH') || exit;

function backstage_variable_discount_assert(bool $condition, string $message): void
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

if (!class_exists('WooCommerce') || !class_exists('WC_Coupon') || !function_exists('backstage_outreach_discount_batch_terms')) {
	throw new RuntimeException('Required WooCommerce and Backstage Outreach runtime is unavailable.');
}

global $wpdb;
$marker = 'BVM variable offer synthetic ' . wp_generate_password(8, false, false);
$now = backstage_outreach_business_now();
$user_id = get_current_user_id() ?: 1;
$post_ids = array();
$order_ids = array();
$coupon_ids = array();
$batch_ids = array();
$campaign_ids = array();
$distribution_ids = array();
$business_id = 0;
$source_id = 0;
$delivery_block = static fn() => true;
add_filter('pre_wp_mail', $delivery_block, PHP_INT_MAX);

try {
	$event_start = wp_date('Y-m-d 19:00:00', time() + (21 * DAY_IN_SECONDS), wp_timezone());
	$event_end = wp_date('Y-m-d 22:00:00', time() + (21 * DAY_IN_SECONDS), wp_timezone());
	$tec_event_id = tribe_create_event(array('post_status' => 'publish', 'post_title' => $marker . ' event', 'EventStartDate' => $event_start, 'EventEndDate' => $event_end));
	backstage_variable_discount_assert(!is_wp_error($tec_event_id) && $tec_event_id > 0, 'Could not create the disposable event.');
	$post_ids[] = (int) $tec_event_id;
	$event_plan_id = wp_insert_post(array('post_type' => 'vms_event_plan', 'post_status' => 'publish', 'post_title' => $marker . ' plan'), true);
	backstage_variable_discount_assert(!is_wp_error($event_plan_id) && $event_plan_id > 0, 'Could not create the disposable Event Plan.');
	$post_ids[] = (int) $event_plan_id;
	$status_key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'status') : '_vms_event_plan_status';
	update_post_meta((int) $event_plan_id, $status_key, 'published');
	update_post_meta((int) $event_plan_id, '_vms_event_date', wp_date('Y-m-d', time() + (21 * DAY_IN_SECONDS)));
	update_post_meta((int) $event_plan_id, '_vms_tec_event_id', (int) $tec_event_id);

	$ticket_provider = function_exists('tribe') ? tribe('tickets-plus.commerce.woo') : null;
	$create_ticket = static function (string $name, string $price) use ($ticket_provider, $tec_event_id, $event_plan_id, &$post_ids): int {
		$ticket_id = is_object($ticket_provider) ? (int) $ticket_provider->ticket_add((int) $tec_event_id, array(
			'ticket_name' => $name,
			'ticket_description' => 'Disposable variable-offer fixture',
			'ticket_price' => $price,
			'ticket_show_description' => 'no',
			'tribe-ticket' => array('capacity' => 100, 'mode' => 'own'),
		)) : 0;
		if ($ticket_id > 0) {
			$post_ids[] = $ticket_id;
			$product = wc_get_product($ticket_id);
			$product->set_status('publish');
			$product->set_tax_status('none');
			$product->set_virtual(true);
			$product->save();
			update_post_meta($ticket_id, '_vms_event_plan_id', (int) $event_plan_id);
			update_post_meta($ticket_id, '_vms_product_role', 'ga_ticket');
		}
		return $ticket_id;
	};
	$full_ticket_id = $create_ticket($marker . ' 100 dollar ticket', '100');
	$cheap_ticket_id = $create_ticket($marker . ' 10 dollar ticket', '10');
	backstage_variable_discount_assert($full_ticket_id > 0 && $cheap_ticket_id > 0, 'Could not create both eligible tickets.');

	$unrelated = new WC_Product_Simple();
	$unrelated->set_name($marker . ' unrelated product');
	$unrelated->set_status('publish');
	$unrelated->set_regular_price('40');
	$unrelated->set_price('40');
	$unrelated->set_tax_status('none');
	$unrelated_id = $unrelated->save();
	backstage_variable_discount_assert($unrelated_id > 0, 'Could not create the unrelated product.');
	$post_ids[] = $unrelated_id;

	$source_table = bvmgr_admission_table_pass_sources();
	$batch_table = bvmgr_admission_table_pass_batches();
	$campaign_table = vms_admission_table_pass_outreach_campaigns();
	backstage_variable_discount_assert($wpdb->insert($source_table, array('source_name' => $marker, 'status' => 'active', 'created_by' => $user_id, 'created_at' => $now)) !== false, 'Could not create the disposable Source.');
	$source_id = (int) $wpdb->insert_id;
	$business_id = backstage_outreach_insert_business(array('business_name' => $marker . ' business'), $user_id);
	backstage_variable_discount_assert($business_id > 0 && backstage_outreach_business_upsert_membership($source_id, $business_id, 'manual', null, 0, array(), $user_id), 'Could not create the disposable business membership.');

	$create_offer = static function (string $value_type, float $value_amount) use ($wpdb, $batch_table, $campaign_table, $source_id, $event_plan_id, $business_id, $user_id, $now, $marker, &$batch_ids, &$campaign_ids, &$distribution_ids, &$coupon_ids): array {
		$inserted = $wpdb->insert($batch_table, array(
			'source_id' => $source_id, 'batch_name' => $marker . ' ' . $value_type, 'quantity' => 0,
			'validity_type' => 'single_event', 'single_event_plan_id' => (int) $event_plan_id, 'venue_ids_json' => '[]',
			'value_type' => $value_type, 'value_amount' => $value_amount, 'applies_to' => 'entry_only', 'status' => 'active',
			'checkin_open_mode' => 'same_day', 'max_per_phone' => 0, 'generated_count' => 0, 'created_by' => $user_id,
			'created_at' => $now, 'admissions_per_link' => 2, 'total_admission_cap' => 20, 'max_per_email' => 0,
		));
		backstage_variable_discount_assert($inserted !== false, 'Could not create the ' . $value_type . ' batch.');
		$batch_id = (int) $wpdb->insert_id;
		$batch_ids[] = $batch_id;
		$inserted = $wpdb->insert($campaign_table, array(
			'campaign_name' => $marker . ' ' . $value_type, 'related_source_id' => $source_id, 'related_batch_id' => $batch_id,
			'validity_type' => 'single_event', 'single_event_plan_id' => (int) $event_plan_id,
			'admissions_per_recipient' => 2, 'total_admission_cap' => 20, 'status' => 'active',
			'eligibility_mode' => 'anyone_with_invite', 'created_by' => $user_id, 'created_at' => $now,
			'campaign_purpose' => 'guest_pass_invitation',
		));
		backstage_variable_discount_assert($inserted !== false, 'Could not create the ' . $value_type . ' campaign.');
		$campaign_id = (int) $wpdb->insert_id;
		$campaign_ids[] = $campaign_id;
		$campaign = vms_pass_outreach_get_campaign_by_id($campaign_id);
		$batch = bvmgr_pass_claims_get_batch_by_id($batch_id);
		$configuration = backstage_outreach_discount_offer_configuration($campaign, $batch, 'coupon_backed');
		backstage_variable_discount_assert(is_array($configuration), 'Could not resolve the ' . $value_type . ' configuration.');
		$distribution_id = backstage_outreach_create_distribution($campaign_id, $source_id, $business_id, 'coupon_backed', 0, 0, '', $configuration['event_ids'], $configuration['product_ids'], $user_id);
		backstage_variable_discount_assert($distribution_id > 0, 'Could not create the ' . $value_type . ' distribution.');
		$distribution_ids[] = $distribution_id;
		$distribution = backstage_outreach_discount_get_distribution($distribution_id);
		$coupon_result = backstage_outreach_discount_ensure_coupon($distribution, $campaign, $configuration);
		backstage_variable_discount_assert(is_array($coupon_result) && absint($coupon_result['coupon_id'] ?? 0) > 0, 'Could not create the ' . $value_type . ' managed coupon.');
		$coupon_ids[] = absint($coupon_result['coupon_id']);
		$wpdb->update(backstage_outreach_business_table('campaign_businesses'), array('coupon_id' => absint($coupon_result['coupon_id']), 'coupon_code' => (string) $coupon_result['coupon_code']), array('id' => $distribution_id));
		return array(
			'batch_id' => $batch_id,
			'campaign' => $campaign,
			'batch' => $batch,
			'configuration' => $configuration,
			'distribution' => backstage_outreach_discount_get_distribution($distribution_id),
			'coupon' => new WC_Coupon(absint($coupon_result['coupon_id'])),
		);
	};

	$percentage = $create_offer('percent', 37.5);
	$fixed = $create_offer('fixed', 12.35);
	$full_discount = $create_offer('percent', 100);
	$over_value_fixed = $create_offer('fixed', 150);
	backstage_variable_discount_assert($percentage['coupon']->get_discount_type() === 'percent' && abs((float) $percentage['coupon']->get_amount() - 37.5) < 0.001, 'The percentage coupon did not use the reviewed 37.5% value.');
	backstage_variable_discount_assert($fixed['coupon']->get_discount_type() === 'fixed_product' && abs((float) $fixed['coupon']->get_amount() - 12.35) < 0.001, 'The fixed coupon did not use the reviewed $12.35-per-admission value.');
	backstage_variable_discount_assert(backstage_outreach_discount_terms_label($percentage['configuration']) === 'Admission Offer — 37.5% off admission', 'Percentage customer wording is inaccurate.');
	backstage_variable_discount_assert(backstage_outreach_discount_terms_label($fixed['configuration']) === 'Admission Offer — $12.35 off each admission', 'Fixed customer wording is inaccurate.');
	backstage_variable_discount_assert(backstage_outreach_discount_configuration_digest($percentage['configuration']) !== backstage_outreach_discount_configuration_digest($fixed['configuration']), 'Offer type/value are absent from the stale-configuration digest.');

	$legacy_terms = backstage_outreach_discount_batch_terms(array('value_type' => 'free', 'value_amount' => 100));
	backstage_variable_discount_assert(is_array($legacy_terms) && !empty($legacy_terms['legacy_free_capacity']) && $legacy_terms['value_type'] === 'percent' && (float) $legacy_terms['value_amount'] === 50.0, 'Legacy paid Free-capacity batches lost their explicit 50% compatibility behavior.');
	backstage_variable_discount_assert(is_wp_error(backstage_outreach_discount_batch_terms(array('value_type' => 'percent', 'value_amount' => 0))), 'A zero percentage was accepted.');
	backstage_variable_discount_assert(is_wp_error(backstage_outreach_discount_batch_terms(array('value_type' => 'percent', 'value_amount' => 100.01))), 'A percentage above 100 was accepted.');
	backstage_variable_discount_assert(is_array(backstage_outreach_discount_batch_terms(array('value_type' => 'percent', 'value_amount' => 100))), 'The documented 100% percentage bound was rejected.');
	backstage_variable_discount_assert(is_wp_error(backstage_outreach_discount_batch_terms(array('value_type' => 'fixed', 'value_amount' => 0))), 'A zero fixed discount was accepted.');
	backstage_variable_discount_assert(is_wp_error(backstage_outreach_discount_batch_terms(array('value_type' => 'fixed', 'value_amount' => 100000000))), 'A fixed value beyond storage precision was accepted.');

	$cart_totals = static function (array $offer, array $products): array {
		WC()->cart->empty_cart(true);
		backstage_outreach_discount_session_clear();
		foreach ($products as $product_id => $quantity) {
			WC()->cart->add_to_cart((int) $product_id, (int) $quantity);
		}
		backstage_outreach_discount_session_set($offer['distribution'], backstage_outreach_distribution_token($offer['distribution']));
		backstage_outreach_discount_sync_cart_coupon();
		WC()->cart->calculate_totals();
		return array('discount' => (float) WC()->cart->get_discount_total(), 'total' => (float) WC()->cart->get_total('edit'));
	};
	$percentage_cart = $cart_totals($percentage, array($full_ticket_id => 1, $unrelated_id => 1));
	backstage_variable_discount_assert(abs($percentage_cart['discount'] - 37.5) < 0.01 && abs($percentage_cart['total'] - 102.5) < 0.01, 'The 37.5% mixed cart total is incorrect.');
	$fixed_cart = $cart_totals($fixed, array($full_ticket_id => 1, $cheap_ticket_id => 1, $unrelated_id => 1));
	backstage_variable_discount_assert(abs($fixed_cart['discount'] - 22.35) < 0.01 && abs($fixed_cart['total'] - 127.65) < 0.01, 'The $12.35 fixed offer did not floor the $10 ticket at zero or preserve the unrelated item.');
	$full_discount_cart = $cart_totals($full_discount, array($cheap_ticket_id => 1));
	$over_value_fixed_cart = $cart_totals($over_value_fixed, array($full_ticket_id => 1, $cheap_ticket_id => 1));
	backstage_variable_discount_assert(abs($full_discount_cart['discount'] - 10.0) < 0.01 && abs($full_discount_cart['total']) < 0.01, 'The 100% offer did not produce a native zero-total eligible cart.');
	backstage_variable_discount_assert(abs($over_value_fixed_cart['discount'] - 110.0) < 0.01 && abs($over_value_fixed_cart['total']) < 0.01, 'The fixed offer above ticket prices did not floor each eligible admission at zero.');

	$percentage_top_up = backstage_outreach_discount_exact_total(30.0, 80.0, array('_vms_discounts_original_unit_price' => 100.0, '_vms_discounts_line_discount' => 20.0, 'quantity' => 1), false, $percentage['coupon']);
	$fixed_top_up = backstage_outreach_discount_exact_total(12.35, 95.0, array('_vms_discounts_original_unit_price' => 100.0, '_vms_discounts_line_discount' => 5.0, 'quantity' => 1), false, $fixed['coupon']);
	$fixed_cheap_top_up = backstage_outreach_discount_exact_total(10.0, 6.0, array('_vms_discounts_original_unit_price' => 10.0, '_vms_discounts_line_discount' => 4.0, 'quantity' => 1), false, $fixed['coupon']);
	backstage_variable_discount_assert(abs((float) $percentage_top_up - 17.5) < 0.01, 'Commerce Discount top-up did not stop at the reviewed 37.5%.');
	backstage_variable_discount_assert(abs((float) $fixed_top_up - 7.35) < 0.01 && abs((float) $fixed_cheap_top_up - 6.0) < 0.01, 'Commerce Discount top-up did not use the fixed per-ticket value or zero floor.');

	WC()->cart->empty_cart(true);
	wc_clear_notices();
	WC()->cart->add_to_cart($full_ticket_id, 3);
	backstage_outreach_discount_session_set($fixed['distribution'], backstage_outreach_distribution_token($fixed['distribution']));
	backstage_outreach_discount_sync_cart_coupon();
	WC()->cart->calculate_totals();
	backstage_outreach_discount_validate_cart();
	backstage_variable_discount_assert(wc_notice_count('error') > 0, 'The two-admission customer limit did not block three eligible tickets.');
	wc_clear_notices();

	$make_order = static function (array $offer, array $products) use (&$order_ids): WC_Order {
		$order = wc_create_order();
		if (is_wp_error($order)) {
			throw new RuntimeException($order->get_error_message());
		}
		$order_ids[] = $order->get_id();
		$distribution = $offer['distribution'];
		$order->update_meta_data('_backstage_outreach_distribution_id', (int) $distribution['id']);
		$order->update_meta_data('_backstage_outreach_campaign_id', (int) $distribution['campaign_id']);
		$order->update_meta_data('_backstage_outreach_source_id', (int) $distribution['source_id']);
		$order->update_meta_data('_backstage_outreach_business_id', (int) $distribution['business_id']);
		$order->update_meta_data('_backstage_outreach_coupon_id', (int) $distribution['coupon_id']);
		$order->update_meta_data('_backstage_outreach_offer_type', 'coupon_backed');
		foreach ($products as $product_id => $quantity) {
			$order->add_product(wc_get_product((int) $product_id), (int) $quantity);
		}
		$order->save();
		$result = $order->apply_coupon((string) $distribution['coupon_code']);
		if (is_wp_error($result)) {
			throw new RuntimeException($result->get_error_message());
		}
		$order->calculate_totals();
		$order->save();
		return $order;
	};
	$percentage_order = $make_order($percentage, array($full_ticket_id => 1, $unrelated_id => 1));
	$fixed_order = $make_order($fixed, array($full_ticket_id => 1, $cheap_ticket_id => 1, $unrelated_id => 1));
	$full_discount_order = $make_order($full_discount, array($cheap_ticket_id => 1));
	$over_value_fixed_order = $make_order($over_value_fixed, array($full_ticket_id => 1, $cheap_ticket_id => 1));
	backstage_outreach_discount_stamp_order($fixed_order);
	$fixed_order->save_meta_data();
	backstage_outreach_discount_validate_order_pricing($percentage_order, $percentage['distribution']);
	backstage_outreach_discount_validate_order_pricing($fixed_order, $fixed['distribution']);
	backstage_variable_discount_assert(abs((float) $percentage_order->get_total() - 102.5) < 0.01 && abs((float) $fixed_order->get_total() - 127.65) < 0.01, 'Actual order/checkout totals do not match the customer-facing offer values.');
	backstage_variable_discount_assert((string) $fixed_order->get_meta('_backstage_outreach_offer_value_type', true) === 'fixed' && abs((float) $fixed_order->get_meta('_backstage_outreach_offer_value_amount', true) - 12.35) < 0.001, 'Order attribution omitted the reviewed fixed offer value.');
	foreach (array($full_discount_order, $over_value_fixed_order) as $zero_total_order) {
		backstage_variable_discount_assert(abs((float) $zero_total_order->get_total()) < 0.01, 'A fully discounted admission order did not retain a zero total.');
		backstage_outreach_discount_stamp_order($zero_total_order);
		$zero_total_order->save_meta_data();
		backstage_outreach_discount_reserve_order($zero_total_order);
		backstage_outreach_discount_reserve_order($zero_total_order);
		$zero_total_order->update_status('processing');
		$redemption = $wpdb->get_row($wpdb->prepare(
			'SELECT status, ticket_quantity FROM %i WHERE order_id=%d',
			backstage_outreach_business_table('paid_redemptions'),
			$zero_total_order->get_id()
		), ARRAY_A);
		backstage_variable_discount_assert(is_array($redemption) && $redemption['status'] === 'paid', 'A native zero-total order did not retain paid Admission Offer attribution.');
		backstage_variable_discount_assert(absint($redemption['ticket_quantity']) === count($zero_total_order->get_items('line_item')), 'A native zero-total order recorded the wrong admission quantity.');
		backstage_variable_discount_assert((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE order_id=%d', backstage_outreach_business_table('paid_redemptions'), $zero_total_order->get_id())) === 1, 'A retried zero-total reservation created duplicate admission attribution.');
	}

	$fixed_digest = backstage_outreach_discount_configuration_digest($fixed['configuration']);
	$wpdb->update($batch_table, array('value_amount' => 20.00), array('id' => (int) $fixed['batch_id']));
	$changed_batch = bvmgr_pass_claims_get_batch_by_id((int) $fixed['batch_id']);
	$changed_configuration = backstage_outreach_discount_offer_configuration($fixed['campaign'], $changed_batch, 'coupon_backed');
	backstage_variable_discount_assert(is_array($changed_configuration) && $fixed_digest !== backstage_outreach_discount_configuration_digest($changed_configuration), 'A changed offer value did not invalidate its reviewed digest.');
	backstage_variable_discount_assert(is_wp_error(backstage_outreach_discount_distribution_error($fixed['distribution'])), 'A changed batch value left the prior managed coupon usable.');
	$wpdb->update($batch_table, array('value_amount' => 12.35), array('id' => (int) $fixed['batch_id']));

	echo "Business variable discount runtime PASS\n";
	echo wp_json_encode(array(
		'percentage_offer' => '37.5% off admission',
		'percentage_mixed_cart_total' => $percentage_cart['total'],
		'fixed_offer' => '$12.35 off each admission',
		'fixed_mixed_cart_total' => $fixed_cart['total'],
		'cheap_ticket_after_fixed_offer' => 0,
		'full_discount_checkout_total' => $full_discount_order->get_total(),
		'over_value_fixed_checkout_total' => $over_value_fixed_order->get_total(),
		'unrelated_product_total' => 40,
		'legacy_free_paid_offer' => '50% off admission',
	), JSON_PRETTY_PRINT) . "\n";
} finally {
	remove_filter('pre_wp_mail', $delivery_block, PHP_INT_MAX);
	if (function_exists('WC') && WC() && WC()->cart) {
		WC()->cart->empty_cart(true);
	}
	backstage_outreach_discount_session_clear();
	foreach ($order_ids as $order_id) {
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
	foreach ($campaign_ids as $campaign_id) {
		$wpdb->delete(backstage_outreach_business_table('paid_redemptions'), array('campaign_id' => $campaign_id));
		$wpdb->delete(backstage_outreach_business_table('distribution_claims'), array('campaign_id' => $campaign_id));
		$wpdb->delete(backstage_outreach_business_table('campaign_businesses'), array('campaign_id' => $campaign_id));
		$wpdb->delete(vms_admission_table_pass_outreach_campaigns(), array('id' => $campaign_id));
	}
	if ($business_id > 0) {
		$wpdb->delete(backstage_outreach_business_table('source_businesses'), array('business_id' => $business_id));
		$wpdb->delete(backstage_outreach_business_table('businesses'), array('id' => $business_id));
	}
	foreach ($batch_ids as $batch_id) {
		$wpdb->delete(bvmgr_admission_table_pass_batches(), array('id' => $batch_id));
	}
	if ($source_id > 0) {
		$wpdb->delete(bvmgr_admission_table_pass_sources(), array('id' => $source_id));
	}
	foreach (array_reverse($post_ids) as $post_id) {
		wp_delete_post((int) $post_id, true);
	}
}
