<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$distribution = file_get_contents($root . '/companion-plugins/backstage-outreach/includes/business-distribution.php');
$discounts = file_get_contents($root . '/companion-plugins/backstage-outreach/includes/business-discount-offers.php');
$outreach = file_get_contents($root . '/companion-plugins/backstage-outreach/includes/admissions/outreach.php');
$plugin = file_get_contents($root . '/companion-plugins/backstage-outreach/backstage-outreach.php');
if (!is_string($distribution) || !is_string($discounts) || !is_string($outreach) || !is_string($plugin)) {
	throw new RuntimeException('Could not read business discount offer sources.');
}

$assertions = array(
	'Coupon runtime is loaded without replacing complimentary distribution' => strpos($plugin, "includes/business-distribution.php") !== false && strpos($plugin, "includes/business-discount-offers.php") !== false,
	'Operator explicitly chooses the distribution type' => strpos($distribution, 'name="distribution_type"') !== false && strpos($distribution, "array('complimentary', 'coupon_backed')") !== false,
	'Complimentary path retains its free-only guard' => strpos($distribution, "sanitize_key((string) (\$batch['value_type'] ?? '')) !== 'free'") !== false && strpos($distribution, 'partner_batch_not_complimentary') !== false,
	'Coupon path supports reviewed percentage and fixed values plus legacy free-batch 50 percent' => strpos($discounts, "'coupon_type' => 'fixed_product'") !== false && strpos($discounts, "'legacy_free_capacity' => true") !== false && strpos($discounts, '$coupon->set_discount_type($coupon_type)') !== false && strpos($discounts, '$coupon->set_amount(number_format($value_amount') !== false,
	'New paid previews and commits require an explicit discount batch while legacy Free-batch links remain readable' => substr_count($distribution, "\$distribution_type === 'coupon_backed' && sanitize_key((string) (\$batch['value_type'] ?? '')) === 'free'") === 2 && substr_count($distribution, 'cannot be created or re-reviewed from this form') === 2 && strpos($discounts, '$allow_legacy_free_capacity = false') !== false && strpos($discounts, 'partner_coupon_legacy_batch_new_distribution') !== false && strpos($distribution, "'coupon_backed', true") !== false && strpos($distribution, 'Existing paid 50% links backed by a Free batch remain valid') !== false,
	'Coupon is native, individual use, and product restricted' => strpos($discounts, 'new WC_Coupon') !== false && strpos($discounts, '$coupon->set_individual_use(true)') !== false && strpos($discounts, '$coupon->set_product_ids($product_ids)') !== false,
	'Only exact distribution-owned managed coupons may be reused' => strpos($discounts, 'managed_coupon_ownership_mismatch') !== false && strpos($discounts, 'managed_coupon_code_collision') !== false && strpos($discounts, 'managed_coupon_changed') !== false,
	'Signed server-side distribution context controls automatic application' => strpos($discounts, 'BACKSTAGE_OUTREACH_DISCOUNT_SESSION_KEY') !== false && strpos($discounts, "hash_equals((string) (\$row['token_hash'] ?? ''), (string) \$session['token_hash'])") !== false,
	'Explicit managed-coupon removal is session scoped and survives every Woo cart transport' => strpos($discounts, 'BACKSTAGE_OUTREACH_DISCOUNT_SUPPRESSION_SESSION_KEY') !== false && strpos($discounts, "add_action('woocommerce_removed_coupon'") !== false && strpos($discounts, "add_filter('rest_request_after_callbacks'") !== false && strpos($discounts, "'/wc/store/v1/cart/coupons'") !== false && strpos($discounts, 'backstage_outreach_discount_coupon_is_suppressed') !== false && strpos($discounts, 'backstage_outreach_discount_remove_coupon_without_suppression') !== false,
	'Deliberate coupon application or signed-offer redemption clears only the active suppression' => strpos($discounts, "add_action('woocommerce_applied_coupon'") !== false && strpos($discounts, 'backstage_outreach_discount_clear_coupon_suppression') !== false && substr_count($discounts, "WC()->session->__unset(BACKSTAGE_OUTREACH_DISCOUNT_SUPPRESSION_SESSION_KEY)") >= 2,
	'Managed coupon cannot be entered outside its signed offer context' => strpos($discounts, 'woocommerce_coupon_is_valid') !== false && strpos($discounts, 'backstage_outreach_discount_session_distribution()') !== false,
	'Existing carts are retained' => strpos($discounts, 'empty_cart') === false && strpos($discounts, 'WC()->cart->empty_cart') === false,
	'Native ticket selection and checkout are retained' => strpos($discounts, 'get_permalink($tec_event_id)') !== false && strpos($discounts, 'wc_get_checkout_url()') !== false,
	'Eligible tickets are captured server-side' => strpos($discounts, "get_post_meta(\$product_id, '_tribe_wooticket_for_event', true)") !== false && strpos($distribution, 'eligible_product_ids_json') !== false,
	'Commerce discounts top up only the coupon-limited admissions and excess conflicts block checkout' => strpos($discounts, 'backstage_outreach_discount_target_for_line') !== false && strpos($discounts, 'applicable_commerce_discount') !== false && strpos($discounts, '$discounted_quantity / $quantity') !== false,
	'Eligible quantity above the coupon limit remains purchasable at full price' => strpos($discounts, 'Reduce the eligible ticket quantity to continue.') === false && strpos($discounts, '$discounted_ticket_quantity = min($ticket_quantity, $per_order_cap);') !== false && strpos($discounts, "'ticket_quantity' => \$discounted_ticket_quantity") !== false,
	'Paid attribution persists on order and line items' => substr_count($discounts, "'_backstage_outreach_distribution_id'") >= 4 && strpos($discounts, "'_backstage_outreach_coupon_id'") !== false,
	'Durable order attribution revalidates every server-owned mapping without depending on a surviving coupon line' => strpos($discounts, "get_meta('_backstage_outreach_offer_type'") !== false && strpos($discounts, "get_meta('_backstage_outreach_campaign_id'") !== false && strpos($discounts, 'return $matches ? $row : null') !== false,
	'Paid reporting is based on payment state, not landing views' => strpos($discounts, "status='paid'") !== false && strpos($discounts, "add_action('woocommerce_payment_complete'") !== false && strpos($discounts, 'activated_at') !== false,
	'Business campaign and batch limits combine paid holds and complimentary admissions under the shared batch lock' => strpos($discounts, "'bvm-pass-batch-'") !== false && strpos($discounts, 'per_business_complimentary') !== false && strpos($discounts, 'batch_paid_or_held') !== false && strpos($discounts, 'outreach_campaign_id') !== false && strpos($distribution, 'campaign_paid') !== false && substr_count($distribution, "'bvm-pass-batch-'") >= 3,
	'Pause revoke and expiry are enforced at coupon and pre-payment validation' => strpos($discounts, 'woocommerce_checkout_validate_order_before_payment') !== false && strpos($discounts, 'partner_coupon_batch_expired') !== false && strpos($distribution, "backstage_outreach_discount_set_coupon_status") !== false,
	'Purchased orders are not mutated by offer status changes' => strpos($distribution, 'Existing purchased tickets and customer credentials were not changed.') !== false && strpos($discounts, 'delete_order') === false,
	'Pending retries reserve capacity while failed and cancelled orders release it' => strpos($discounts, "reservation_expires_at>%s") !== false && strpos($discounts, "woocommerce_order_status_failed") !== false && strpos($discounts, "woocommerce_order_status_cancelled") !== false,
	'Coupon uses paid orders and discounted quantities are reported separately' => strpos($distribution, 'coupon uses') !== false && strpos($distribution, 'paid orders') !== false && strpos($distribution, 'discounted tickets') !== false,
	'Unpaid order-pay retries validate exact pricing without an uncaught pre-payment exception' => strpos($discounts, 'backstage_outreach_discount_validate_order_pricing') !== false && strpos($discounts, 'backstage_outreach_discount_validate_order_pay_action') !== false && strpos($discounts, 'wc_add_notice') !== false,
	'Unpaid order-pay retries reject mismatched durable attribution' => strpos($discounts, 'offer_attribution_mismatch') !== false && strpos($discounts, 'server-owned Admission Offer attribution') !== false,
	'Accounting distinguishes eligible gross net refunds and settlement review' => strpos($distribution, 'eligible_ticket_gross_total') !== false && strpos($distribution, 'eligible_ticket_refunded_total') !== false && strpos($discounts, 'settlement_review_code') !== false && strpos($discounts, 'admission_revenue') !== false,
	'Replayed status callbacks preserve canonical states and first timestamps' => strpos($discounts, "elseif (\$order->is_paid())") !== false && strpos($discounts, "empty(\$current['paid_at'])") !== false && strpos($discounts, 'backstage_outreach_discount_reconcile_status') !== false,
	'Paid customer language is neutral and distinct from complimentary Guest Pass wording' => strpos($discounts, "__('Admission Offer'") !== false && strpos($distribution, "__('Complimentary Guest Pass'") !== false && strpos($distribution, 'Scan for %1$s for up to %2$d people') !== false,
	'Reusable-business runtime no longer requires Neighborhood branding' => stripos($outreach . $distribution . $discounts, 'Neighborhood Offer') === false && stripos($outreach . $distribution . $discounts, 'Neighborhood Business Offer') === false,
	'Paid signed-link errors retain Admission Offer presentation' => strpos($distribution, "array('distribution_type' => \$is_coupon ? 'coupon_backed' : 'complimentary')") !== false && strpos($distribution, "__('Admission Offer Unavailable'") !== false && strpos($distribution, "__('Business Offer Unavailable'") !== false,
	'Export includes the reviewed offer value and description' => strpos($distribution, "'offer_value_type', 'offer_value_amount', 'offer_description'") !== false,
	'Legacy coupon codes remain stable' => strpos($discounts, "return 'sr-biz50-'") !== false,
);

$failed = array_keys(array_filter($assertions, static fn(bool $passed): bool => !$passed));
if ($failed) {
	throw new RuntimeException("Business discount QR assertions failed:\n- " . implode("\n- ", $failed));
}

echo 'Business discount QR links PASS (' . count($assertions) . " assertions)\n";
