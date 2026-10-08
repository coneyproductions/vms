<?php
/**
 * Native WooCommerce coupon-backed business distribution.
 *
 * The signed Outreach link establishes a server-side offer context. Customers
 * still select tickets through the native Event Tickets UI and complete the
 * normal WooCommerce checkout, payment, attendee, and fulfillment lifecycle.
 */

defined('ABSPATH') || exit;

const BACKSTAGE_OUTREACH_DISCOUNT_SESSION_KEY = 'backstage_outreach_discount_offer';
const BACKSTAGE_OUTREACH_COUPON_MANAGED_META = '_backstage_outreach_managed';
const BACKSTAGE_OUTREACH_COUPON_DISTRIBUTION_META = '_backstage_outreach_distribution_id';
const BACKSTAGE_OUTREACH_COUPON_CONFIG_HASH_META = '_backstage_outreach_configuration_hash';

function backstage_outreach_discount_json_ids(string $json): array
{
	$decoded = json_decode($json, true);
	if (!is_array($decoded)) {
		return array();
	}
	return array_values(array_unique(array_filter(array_map('absint', $decoded))));
}

function backstage_outreach_discount_encode_ids(array $ids): string
{
	$ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
	sort($ids, SORT_NUMERIC);
	return (string) wp_json_encode($ids);
}

function backstage_outreach_discount_distribution_type(array $row): string
{
	$type = sanitize_key((string) ($row['distribution_type'] ?? 'complimentary'));
	return $type === 'coupon_backed' ? 'coupon_backed' : 'complimentary';
}

/**
 * Resolve the paid offer represented by a batch.
 *
 * Free batches remain a supported 50% compatibility source for distributions
 * created by earlier Outreach releases. New paid batches use their reviewed
 * percentage or fixed-per-admission value directly.
 */
function backstage_outreach_discount_batch_terms(array $batch)
{
	$value_type = sanitize_key((string) ($batch['value_type'] ?? ''));
	$value_amount = round((float) ($batch['value_amount'] ?? 0), 2);
	if ($value_type === 'free') {
		return array(
			'value_type' => 'percent',
			'value_amount' => 50.0,
			'coupon_type' => 'percent',
			'legacy_free_capacity' => true,
		);
	}
	if ($value_type === 'percent' && $value_amount > 0 && $value_amount <= 100) {
		return array(
			'value_type' => 'percent',
			'value_amount' => $value_amount,
			'coupon_type' => 'percent',
			'legacy_free_capacity' => false,
		);
	}
	if ($value_type === 'fixed' && is_finite($value_amount) && $value_amount > 0 && $value_amount <= 99999999.99) {
		return array(
			'value_type' => 'fixed',
			'value_amount' => $value_amount,
			'coupon_type' => 'fixed_product',
			'legacy_free_capacity' => false,
		);
	}
	return new WP_Error('partner_coupon_unsupported_value', __('Paid Admission Offers require a valid Percentage Off or Fixed Amount Off batch.', 'backstage-outreach'));
}

function backstage_outreach_discount_display_amount(float $amount): string
{
	return rtrim(rtrim(number_format(round($amount, 2), 2, '.', ''), '0'), '.');
}

function backstage_outreach_discount_terms_label(array $terms, bool $with_prefix = true): string
{
	$amount = backstage_outreach_discount_display_amount((float) ($terms['value_amount'] ?? 0));
	$value = sanitize_key((string) ($terms['value_type'] ?? '')) === 'fixed'
		? sprintf(__('$%s off each admission', 'backstage-outreach'), $amount)
		: sprintf(__('%s%% off admission', 'backstage-outreach'), $amount);
	return $with_prefix ? sprintf(__('Admission Offer — %s', 'backstage-outreach'), $value) : $value;
}

function backstage_outreach_discount_terms_for_distribution(array $row)
{
	$batch = function_exists('bvmgr_pass_claims_get_batch_by_id')
		? bvmgr_pass_claims_get_batch_by_id(absint($row['related_batch_id'] ?? 0))
		: null;
	return is_array($batch)
		? backstage_outreach_discount_batch_terms($batch)
		: new WP_Error('partner_coupon_batch_missing', __('This Admission Offer batch is unavailable.', 'backstage-outreach'));
}

function backstage_outreach_discount_target_for_line(float $original_unit, int $quantity, array $terms): float
{
	$original_unit = max(0.0, $original_unit);
	$quantity = max(0, $quantity);
	if (sanitize_key((string) ($terms['value_type'] ?? '')) === 'fixed') {
		return min($original_unit, max(0.0, (float) ($terms['value_amount'] ?? 0))) * $quantity;
	}
	return ($original_unit * $quantity) * (max(0.0, min(100.0, (float) ($terms['value_amount'] ?? 0))) / 100);
}

function backstage_outreach_discount_ticket_role(int $product_id): string
{
	if (function_exists('bvmgr_ticketing_v2_product_role_for_naming')) {
		$role = sanitize_key((string) bvmgr_ticketing_v2_product_role_for_naming($product_id));
		if ($role !== '') {
			return $role;
		}
	}
	return sanitize_key((string) get_post_meta($product_id, '_vms_product_role', true));
}

function backstage_outreach_discount_ticket_products_for_event(int $event_plan_id): array
{
	$tec_event_id = absint(get_post_meta($event_plan_id, '_vms_tec_event_id', true));
	if ($tec_event_id <= 0 || !function_exists('bvmgr_get_ticket_product_ids_for_event')) {
		return array();
	}

	$out = array();
	foreach ((array) bvmgr_get_ticket_product_ids_for_event($tec_event_id) as $candidate_id) {
		$product_id = absint($candidate_id);
		$product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
		if (!$product || !in_array((string) get_post_status($product_id), array('publish', 'private'), true)) {
			continue;
		}
		if (absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true)) !== $tec_event_id) {
			continue;
		}
		$role = backstage_outreach_discount_ticket_role($product_id);
		if ($role !== '' && !in_array($role, array('ga_ticket', 'ticket', 'legacy_ticket'), true)) {
			continue;
		}
		if ((float) $product->get_price() <= 0) {
			continue;
		}
		$out[] = $product_id;
	}
	return array_values(array_unique($out));
}

function backstage_outreach_discount_offer_configuration(array $campaign, array $batch, string $distribution_type, bool $allow_legacy_free_capacity = false)
{
	$value_type = sanitize_key((string) ($batch['value_type'] ?? ''));
	if ($distribution_type === 'complimentary') {
		if ($value_type !== 'free') {
			return new WP_Error('partner_batch_not_complimentary', __('Complimentary distribution requires a Free Guest Pass batch.', 'backstage-outreach'));
		}
		return array('distribution_type' => 'complimentary', 'events' => array(), 'event_ids' => array(), 'product_ids' => array());
	}

	if (!class_exists('WooCommerce') || !class_exists('WC_Coupon') || !function_exists('wc_get_product')) {
		return new WP_Error('partner_coupon_woocommerce_missing', __('Coupon-backed distribution requires WooCommerce.', 'backstage-outreach'));
	}
	$terms = backstage_outreach_discount_batch_terms($batch);
	if (is_wp_error($terms)) {
		return $terms;
	}
	if (!empty($terms['legacy_free_capacity']) && !$allow_legacy_free_capacity) {
		return new WP_Error('partner_coupon_legacy_batch_new_distribution', __('New paid Admission Offers require a reviewed Percentage Off or Fixed Amount Off batch. Existing 50% links backed by a Free batch remain valid.', 'backstage-outreach'));
	}

	$events = function_exists('bvmgr_pass_claims_eligible_events_for_batch')
		? bvmgr_pass_claims_eligible_events_for_batch($batch)
		: array();
	if (function_exists('vms_pass_outreach_filter_events_for_campaign')) {
		$events = vms_pass_outreach_filter_events_for_campaign($campaign, $events);
	}
	$event_ids = array();
	$product_ids = array();
	foreach ($events as $event) {
		$event_id = absint($event['id'] ?? 0);
		if ($event_id <= 0) {
			continue;
		}
		$event_products = backstage_outreach_discount_ticket_products_for_event($event_id);
		if (empty($event_products)) {
			continue;
		}
		$event_ids[] = $event_id;
		$product_ids = array_merge($product_ids, $event_products);
	}
	$event_ids = array_values(array_unique($event_ids));
	$product_ids = array_values(array_unique(array_filter(array_map('absint', $product_ids))));
	sort($event_ids, SORT_NUMERIC);
	sort($product_ids, SORT_NUMERIC);
	if (empty($event_ids) || empty($product_ids)) {
		return new WP_Error('partner_coupon_no_eligible_tickets', __('No eligible paid Event Tickets products are available for this campaign scope.', 'backstage-outreach'));
	}

	return array(
		'distribution_type' => 'coupon_backed',
		'events' => $events,
		'event_ids' => $event_ids,
		'product_ids' => $product_ids,
		'value_type' => (string) $terms['value_type'],
		'value_amount' => (float) $terms['value_amount'],
		'coupon_type' => (string) $terms['coupon_type'],
		'legacy_free_capacity' => !empty($terms['legacy_free_capacity']),
		'per_order_ticket_cap' => max(1, absint($campaign['admissions_per_recipient'] ?? 1)),
	);
}

function backstage_outreach_discount_configuration_digest(array $configuration): string
{
	$event_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($configuration['event_ids'] ?? array())))));
	$product_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($configuration['product_ids'] ?? array())))));
	sort($event_ids, SORT_NUMERIC);
	sort($product_ids, SORT_NUMERIC);
	return hash('sha256', wp_json_encode(array(
		'distribution_type' => sanitize_key((string) ($configuration['distribution_type'] ?? '')),
		'event_ids' => $event_ids,
		'product_ids' => $product_ids,
		'value_type' => sanitize_key((string) ($configuration['value_type'] ?? '')),
		'value_amount' => (float) ($configuration['value_amount'] ?? 0),
		'coupon_type' => sanitize_key((string) ($configuration['coupon_type'] ?? '')),
		'legacy_free_capacity' => !empty($configuration['legacy_free_capacity']),
		'per_order_ticket_cap' => absint($configuration['per_order_ticket_cap'] ?? 0),
	)));
}

function backstage_outreach_discount_coupon_code(array $distribution): string
{
	return 'sr-biz50-' . substr(sanitize_key((string) ($distribution['public_key'] ?? '')), 0, 12);
}

function backstage_outreach_discount_coupon_state_hash(WC_Coupon $coupon): string
{
	$product_ids = array_values(array_unique(array_filter(array_map('absint', (array) $coupon->get_product_ids()))));
	sort($product_ids, SORT_NUMERIC);
	return hash('sha256', wp_json_encode(array(
		'code' => (string) $coupon->get_code(),
		'discount_type' => (string) $coupon->get_discount_type(),
		'amount' => (string) $coupon->get_amount(),
		'individual_use' => (bool) $coupon->get_individual_use(),
		'product_ids' => $product_ids,
		'usage_limit' => absint($coupon->get_usage_limit()),
		'limit_usage_to_x_items' => absint($coupon->get_limit_usage_to_x_items()),
		'free_shipping' => (bool) $coupon->get_free_shipping(),
		'exclude_sale_items' => (bool) $coupon->get_exclude_sale_items(),
		'distribution_id' => absint($coupon->get_meta(BACKSTAGE_OUTREACH_COUPON_DISTRIBUTION_META, true)),
	)));
}

function backstage_outreach_discount_set_coupon_status(int $coupon_id, string $status, int $distribution_id = 0): bool
{
	if ($coupon_id <= 0 || !class_exists('WC_Coupon')) {
		return $coupon_id <= 0;
	}
	$coupon = new WC_Coupon($coupon_id);
	if ($coupon->get_id() <= 0 || (string) $coupon->get_meta(BACKSTAGE_OUTREACH_COUPON_MANAGED_META, true) !== '1'
		|| ($distribution_id > 0 && absint($coupon->get_meta(BACKSTAGE_OUTREACH_COUPON_DISTRIBUTION_META, true)) !== $distribution_id)) {
		return false;
	}
	$coupon->set_status($status === 'publish' ? 'publish' : 'draft');
	$coupon->save();
	return true;
}

function backstage_outreach_discount_ensure_coupon(array $distribution, array $campaign, array $configuration)
{
	if (backstage_outreach_discount_distribution_type($distribution) !== 'coupon_backed') {
		$coupon_id = absint($distribution['coupon_id'] ?? 0);
		return backstage_outreach_discount_set_coupon_status($coupon_id, 'draft', absint($distribution['id'] ?? 0))
			? array('coupon_id' => $coupon_id, 'coupon_code' => sanitize_text_field((string) ($distribution['coupon_code'] ?? '')))
			: new WP_Error('managed_coupon_disable_failed', __('The prior managed coupon could not be disabled safely.', 'backstage-outreach'));
	}

	$distribution_id = absint($distribution['id'] ?? 0);
	$product_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($configuration['product_ids'] ?? array())))));
	$order_cap = absint($distribution['order_cap'] ?? 0);
	$per_order_cap = max(1, absint($configuration['per_order_ticket_cap'] ?? 1));
	$coupon_id = absint($distribution['coupon_id'] ?? 0);
	$coupon = $coupon_id > 0 ? new WC_Coupon($coupon_id) : new WC_Coupon();

	if ($coupon_id > 0) {
		if ($coupon->get_id() !== $coupon_id
			|| (string) $coupon->get_meta(BACKSTAGE_OUTREACH_COUPON_MANAGED_META, true) !== '1'
			|| absint($coupon->get_meta(BACKSTAGE_OUTREACH_COUPON_DISTRIBUTION_META, true)) !== $distribution_id) {
			return new WP_Error('managed_coupon_ownership_mismatch', __('The linked coupon is not owned by this business distribution. It was not modified.', 'backstage-outreach'));
		}
		$stored_hash = (string) $coupon->get_meta(BACKSTAGE_OUTREACH_COUPON_CONFIG_HASH_META, true);
		if ($stored_hash === '' || !hash_equals($stored_hash, backstage_outreach_discount_coupon_state_hash($coupon))) {
			return new WP_Error('managed_coupon_changed', __('The linked managed coupon was changed outside Outreach. Review it manually; Outreach did not overwrite it.', 'backstage-outreach'));
		}
	} else {
		$code = backstage_outreach_discount_coupon_code($distribution);
		$existing_id = function_exists('wc_get_coupon_id_by_code') ? absint(wc_get_coupon_id_by_code($code)) : 0;
		if ($existing_id > 0) {
			$existing = new WC_Coupon($existing_id);
			if ((string) $existing->get_meta(BACKSTAGE_OUTREACH_COUPON_MANAGED_META, true) !== '1'
				|| absint($existing->get_meta(BACKSTAGE_OUTREACH_COUPON_DISTRIBUTION_META, true)) !== $distribution_id) {
				return new WP_Error('managed_coupon_code_collision', __('A different coupon already uses the generated offer code. No coupon was changed.', 'backstage-outreach'));
			}
			$coupon = $existing;
		} else {
			$coupon->set_code($code);
		}
	}

	$coupon_type = sanitize_key((string) ($configuration['coupon_type'] ?? ''));
	$value_amount = round((float) ($configuration['value_amount'] ?? 0), 2);
	if (!in_array($coupon_type, array('percent', 'fixed_product'), true) || $value_amount <= 0) {
		return new WP_Error('managed_coupon_invalid_value', __('The reviewed Admission Offer value is invalid.', 'backstage-outreach'));
	}
	$coupon->set_discount_type($coupon_type);
	$coupon->set_amount(number_format($value_amount, 2, '.', ''));
	$coupon->set_individual_use(true);
	$coupon->set_product_ids($product_ids);
	$coupon->set_excluded_product_ids(array());
	$coupon->set_usage_limit($order_cap > 0 ? $order_cap : 0);
	$coupon->set_usage_limit_per_user(0);
	$coupon->set_limit_usage_to_x_items($per_order_cap);
	$coupon->set_free_shipping(false);
	$coupon->set_exclude_sale_items(false);
	$coupon->set_description(sprintf(
		/* translators: 1: offer value, 2: campaign name, 3: business name. */
		__('Admission Offer: %1$s for %2$s / %3$s. Change through Backstage Outreach only.', 'backstage-outreach'),
		backstage_outreach_discount_terms_label($configuration, false),
		(string) ($campaign['campaign_name'] ?? ''),
		(string) ($distribution['business_name'] ?? '')
	));
	$coupon->update_meta_data(BACKSTAGE_OUTREACH_COUPON_MANAGED_META, '1');
	$coupon->update_meta_data(BACKSTAGE_OUTREACH_COUPON_DISTRIBUTION_META, $distribution_id);
	$coupon->update_meta_data('_backstage_outreach_campaign_id', absint($distribution['campaign_id'] ?? 0));
	$coupon->update_meta_data('_backstage_outreach_source_id', absint($distribution['source_id'] ?? 0));
	$coupon->update_meta_data('_backstage_outreach_business_id', absint($distribution['business_id'] ?? 0));
	$coupon->set_status((string) ($distribution['status'] ?? '') === 'active' ? 'publish' : 'draft');
	$coupon_id = $coupon->save();
	if ($coupon_id <= 0) {
		return new WP_Error('managed_coupon_save_failed', __('The managed WooCommerce coupon could not be saved.', 'backstage-outreach'));
	}
	$coupon = new WC_Coupon($coupon_id);
	$coupon->update_meta_data(BACKSTAGE_OUTREACH_COUPON_CONFIG_HASH_META, backstage_outreach_discount_coupon_state_hash($coupon));
	$coupon->save_meta_data();
	return array('coupon_id' => $coupon_id, 'coupon_code' => (string) $coupon->get_code());
}

function backstage_outreach_discount_get_distribution(int $distribution_id): ?array
{
	if ($distribution_id <= 0) {
		return null;
	}
	global $wpdb;
	$row = $wpdb->get_row($wpdb->prepare(
		'SELECT d.*, b.business_name, b.status AS business_status, m.status AS membership_status,
		c.campaign_name, c.status AS campaign_status, c.related_batch_id, c.related_source_id, c.expires_at AS campaign_expires_at,
		c.total_admission_cap AS campaign_ticket_cap, c.admissions_per_recipient
		FROM %i d INNER JOIN %i b ON b.id=d.business_id
		INNER JOIN %i m ON m.source_id=d.source_id AND m.business_id=d.business_id
		INNER JOIN %i c ON c.id=d.campaign_id WHERE d.id=%d',
		backstage_outreach_business_table('campaign_businesses'),
		backstage_outreach_business_table('businesses'),
		backstage_outreach_business_table('source_businesses'),
		vms_admission_table_pass_outreach_campaigns(),
		$distribution_id
	), ARRAY_A);
	return is_array($row) ? $row : null;
}

function backstage_outreach_discount_distribution_error(array $row)
{
	if (backstage_outreach_discount_distribution_type($row) !== 'coupon_backed') {
		return new WP_Error('partner_coupon_wrong_type', __('This link is not configured as an Admission Offer.', 'backstage-outreach'));
	}
	if ((string) ($row['status'] ?? '') !== 'active'
		|| (string) ($row['business_status'] ?? '') !== 'active'
		|| (string) ($row['membership_status'] ?? '') !== 'active') {
		return new WP_Error('partner_link_paused', __('This Admission Offer is paused or revoked.', 'backstage-outreach'));
	}
	if ((string) ($row['campaign_status'] ?? '') !== 'active') {
		return new WP_Error('partner_campaign_inactive', __('This Admission Offer campaign is not currently active.', 'backstage-outreach'));
	}
	foreach (array('expires_at', 'campaign_expires_at') as $expiry_key) {
		if (empty($row[$expiry_key])) {
			continue;
		}
		try {
			if ((new DateTimeImmutable((string) $row[$expiry_key], wp_timezone()))->getTimestamp() <= time()) {
				return new WP_Error('partner_link_expired', __('This Admission Offer has expired.', 'backstage-outreach'));
			}
		} catch (Exception $error) {
			return new WP_Error('partner_link_expired', __('This Admission Offer has an invalid expiry.', 'backstage-outreach'));
		}
	}
	if (absint($row['source_id'] ?? 0) !== absint($row['related_source_id'] ?? 0)) {
		return new WP_Error('partner_link_mismatch', __('This Admission Offer no longer matches its campaign Source.', 'backstage-outreach'));
	}
	$batch = bvmgr_pass_claims_get_batch_by_id(absint($row['related_batch_id'] ?? 0));
	$terms = is_array($batch) ? backstage_outreach_discount_batch_terms($batch) : null;
	if (!is_array($batch) || (string) ($batch['status'] ?? '') !== 'active'
		|| absint($batch['source_id'] ?? 0) !== absint($row['source_id'] ?? 0)
		|| is_wp_error($terms)) {
		return new WP_Error('partner_coupon_batch_inactive', __('This Admission Offer is not currently available.', 'backstage-outreach'));
	}
	if (!empty($batch['expires_at'])) {
		try {
			if ((new DateTimeImmutable((string) $batch['expires_at'], wp_timezone()))->getTimestamp() <= time()) {
				return new WP_Error('partner_coupon_batch_expired', __('This Admission Offer has expired.', 'backstage-outreach'));
			}
		} catch (Exception $error) {
			return new WP_Error('partner_coupon_batch_expired', __('This Admission Offer has an invalid batch expiry.', 'backstage-outreach'));
		}
	}
	$coupon_id = absint($row['coupon_id'] ?? 0);
	if ($coupon_id <= 0 || !class_exists('WC_Coupon')) {
		return new WP_Error('partner_coupon_missing', __('This Admission Offer is not ready for checkout.', 'backstage-outreach'));
	}
	$coupon = new WC_Coupon($coupon_id);
	$stored_hash = (string) $coupon->get_meta(BACKSTAGE_OUTREACH_COUPON_CONFIG_HASH_META, true);
	$expected_coupon_type = is_array($terms) ? (string) ($terms['coupon_type'] ?? '') : '';
	$expected_amount = is_array($terms) ? (float) ($terms['value_amount'] ?? 0) : 0.0;
	if ($coupon->get_id() !== $coupon_id || (string) $coupon->get_status() !== 'publish'
		|| (string) $coupon->get_meta(BACKSTAGE_OUTREACH_COUPON_MANAGED_META, true) !== '1'
		|| absint($coupon->get_meta(BACKSTAGE_OUTREACH_COUPON_DISTRIBUTION_META, true)) !== absint($row['id'] ?? 0)
		|| (string) $coupon->get_discount_type() !== $expected_coupon_type
		|| abs((float) $coupon->get_amount() - $expected_amount) > 0.00001
		|| $stored_hash === '' || !hash_equals($stored_hash, backstage_outreach_discount_coupon_state_hash($coupon))) {
		return new WP_Error('partner_coupon_inactive', __('This Admission Offer is not currently available at checkout.', 'backstage-outreach'));
	}
	return null;
}

function backstage_outreach_discount_session_get(): array
{
	if (!function_exists('WC') || !WC() || !WC()->session) {
		return array();
	}
	$value = WC()->session->get(BACKSTAGE_OUTREACH_DISCOUNT_SESSION_KEY, array());
	return is_array($value) ? $value : array();
}

function backstage_outreach_discount_session_set(array $distribution, string $raw_token): void
{
	if (!function_exists('WC') || !WC() || !WC()->session) {
		return;
	}
	WC()->session->set(BACKSTAGE_OUTREACH_DISCOUNT_SESSION_KEY, array(
		'distribution_id' => absint($distribution['id'] ?? 0),
		'token_hash' => hash('sha256', $raw_token),
		'activated_at' => time(),
	));
	if (method_exists(WC()->session, 'set_customer_session_cookie')) {
		WC()->session->set_customer_session_cookie(true);
	}
}

function backstage_outreach_discount_session_clear(): void
{
	if (function_exists('WC') && WC() && WC()->session) {
		WC()->session->__unset(BACKSTAGE_OUTREACH_DISCOUNT_SESSION_KEY);
	}
}

function backstage_outreach_discount_session_distribution(): ?array
{
	$session = backstage_outreach_discount_session_get();
	$row = backstage_outreach_discount_get_distribution(absint($session['distribution_id'] ?? 0));
	if (!is_array($row) || empty($session['token_hash']) || !hash_equals((string) ($row['token_hash'] ?? ''), (string) $session['token_hash'])) {
		return null;
	}
	return $row;
}

function backstage_outreach_discount_managed_coupon_distribution($coupon): ?array
{
	if (!($coupon instanceof WC_Coupon) || (string) $coupon->get_meta(BACKSTAGE_OUTREACH_COUPON_MANAGED_META, true) !== '1') {
		return null;
	}
	$row = backstage_outreach_discount_get_distribution(absint($coupon->get_meta(BACKSTAGE_OUTREACH_COUPON_DISTRIBUTION_META, true)));
	return is_array($row) && absint($row['coupon_id'] ?? 0) === $coupon->get_id() ? $row : null;
}

function backstage_outreach_discount_coupon_is_valid($valid, $coupon, $discounts)
{
	$row = backstage_outreach_discount_managed_coupon_distribution($coupon);
	if (!is_array($row)) {
		return $valid;
	}
	$error = backstage_outreach_discount_distribution_error($row);
	if (is_wp_error($error)) {
		return false;
	}
	$object = is_object($discounts) && method_exists($discounts, 'get_object') ? $discounts->get_object() : null;
	if ($object instanceof WC_Order) {
		return absint($object->get_meta('_backstage_outreach_distribution_id', true)) === absint($row['id'] ?? 0);
	}
	$session_row = backstage_outreach_discount_session_distribution();
	return is_array($session_row) && absint($session_row['id'] ?? 0) === absint($row['id'] ?? 0);
}
add_filter('woocommerce_coupon_is_valid', 'backstage_outreach_discount_coupon_is_valid', 20, 3);

function backstage_outreach_discount_coupon_valid_for_product($valid, $product, $coupon, $values = array())
{
	$row = backstage_outreach_discount_managed_coupon_distribution($coupon);
	if (!is_array($row)) {
		return $valid;
	}
	$product_id = is_object($product) && method_exists($product, 'get_id') ? absint($product->get_id()) : 0;
	$parent_id = is_object($product) && method_exists($product, 'get_parent_id') ? absint($product->get_parent_id()) : 0;
	$allowed = backstage_outreach_discount_json_ids((string) ($row['eligible_product_ids_json'] ?? ''));
	return in_array($product_id, $allowed, true) || ($parent_id > 0 && in_array($parent_id, $allowed, true));
}
add_filter('woocommerce_coupon_is_valid_for_product', 'backstage_outreach_discount_coupon_valid_for_product', 20, 4);

function backstage_outreach_discount_remove_managed_coupons(): void
{
	if (!function_exists('WC') || !WC() || !WC()->cart) {
		return;
	}
	foreach ((array) WC()->cart->get_applied_coupons() as $code) {
		$coupon = new WC_Coupon($code);
		if ((string) $coupon->get_meta(BACKSTAGE_OUTREACH_COUPON_MANAGED_META, true) === '1') {
			WC()->cart->remove_coupon($code);
		}
	}
}

function backstage_outreach_discount_cart_has_eligible_item(array $row): bool
{
	if (!function_exists('WC') || !WC() || !WC()->cart) {
		return false;
	}
	$allowed = backstage_outreach_discount_json_ids((string) ($row['eligible_product_ids_json'] ?? ''));
	foreach (WC()->cart->get_cart() as $item) {
		$product_id = absint($item['variation_id'] ?? 0) ?: absint($item['product_id'] ?? 0);
		$parent_id = absint($item['product_id'] ?? 0);
		if (in_array($product_id, $allowed, true) || in_array($parent_id, $allowed, true)) {
			return true;
		}
	}
	return false;
}

function backstage_outreach_discount_sync_cart_coupon(): void
{
	static $running = false;
	if ($running || !function_exists('WC') || !WC() || !WC()->cart) {
		return;
	}
	$running = true;
	try {
		$row = backstage_outreach_discount_session_distribution();
		if (!is_array($row) || is_wp_error(backstage_outreach_discount_distribution_error($row))) {
			backstage_outreach_discount_remove_managed_coupons();
			if (!is_array($row)) {
				backstage_outreach_discount_session_clear();
			}
			return;
		}
		$code = wc_format_coupon_code((string) ($row['coupon_code'] ?? ''));
		if ($code === '' || !backstage_outreach_discount_cart_has_eligible_item($row)) {
			backstage_outreach_discount_remove_managed_coupons();
			return;
		}
		foreach ((array) WC()->cart->get_applied_coupons() as $applied) {
			$coupon = new WC_Coupon($applied);
			if ((string) $coupon->get_meta(BACKSTAGE_OUTREACH_COUPON_MANAGED_META, true) === '1' && !wc_is_same_coupon($applied, $code)) {
				WC()->cart->remove_coupon($applied);
			}
		}
		if (!WC()->cart->has_discount($code)) {
			WC()->cart->apply_coupon($code);
		}
	} finally {
		$running = false;
	}
}
add_action('woocommerce_cart_loaded_from_session', 'backstage_outreach_discount_sync_cart_coupon', 50);
add_action('woocommerce_before_calculate_totals', 'backstage_outreach_discount_sync_cart_coupon', 40);
add_action('woocommerce_add_to_cart', 'backstage_outreach_discount_sync_cart_coupon', 40);

function backstage_outreach_discount_exact_total($discount, $discounting_amount, $cart_item, $single, $coupon)
{
	$row = backstage_outreach_discount_managed_coupon_distribution($coupon);
	if (!is_array($row) || !is_array($cart_item) || $single) {
		return $discount;
	}
	$original_unit = isset($cart_item['_vms_discounts_original_unit_price']) ? (float) $cart_item['_vms_discounts_original_unit_price'] : 0.0;
	$commerce_line_discount = isset($cart_item['_vms_discounts_line_discount']) ? (float) $cart_item['_vms_discounts_line_discount'] : 0.0;
	$quantity = max(1, absint($cart_item['quantity'] ?? 1));
	if ($original_unit <= 0 || $commerce_line_discount <= 0) {
		return $discount;
	}
	$terms = backstage_outreach_discount_terms_for_distribution($row);
	if (is_wp_error($terms)) {
		return $discount;
	}
	$target_total_discount = backstage_outreach_discount_target_for_line($original_unit, $quantity, $terms);
	return max(0.0, min((float) $discounting_amount, $target_total_discount - $commerce_line_discount));
}
add_filter('woocommerce_coupon_get_discount_amount', 'backstage_outreach_discount_exact_total', 20, 5);

function backstage_outreach_discount_validate_cart(): void
{
	$row = backstage_outreach_discount_session_distribution();
	if (!is_array($row) || !function_exists('WC') || !WC() || !WC()->cart) {
		return;
	}
	$error = backstage_outreach_discount_distribution_error($row);
	if (is_wp_error($error)) {
		wc_add_notice($error->get_error_message(), 'error');
		return;
	}
	$allowed = backstage_outreach_discount_json_ids((string) ($row['eligible_product_ids_json'] ?? ''));
	$terms = backstage_outreach_discount_terms_for_distribution($row);
	if (is_wp_error($terms)) {
		wc_add_notice($terms->get_error_message(), 'error');
		return;
	}
	$quantity = 0;
	$excess_existing_discount = false;
	foreach (WC()->cart->get_cart() as $item) {
		$product_id = absint($item['variation_id'] ?? 0) ?: absint($item['product_id'] ?? 0);
		$parent_id = absint($item['product_id'] ?? 0);
		if (!in_array($product_id, $allowed, true) && !in_array($parent_id, $allowed, true)) {
			continue;
		}
		$item_quantity = max(0, absint($item['quantity'] ?? 0));
		$quantity += $item_quantity;
		$original_unit = isset($item['_vms_discounts_original_unit_price']) ? max(0.0, (float) $item['_vms_discounts_original_unit_price']) : 0.0;
		$commerce_discount = isset($item['_vms_discounts_line_discount']) ? max(0.0, (float) $item['_vms_discounts_line_discount']) : 0.0;
		if ($original_unit > 0 && $commerce_discount > backstage_outreach_discount_target_for_line($original_unit, $item_quantity, $terms) + 0.00001) {
			$excess_existing_discount = true;
		}
	}
	$per_order_cap = max(1, absint($row['admissions_per_recipient'] ?? 1));
	if ($quantity > $per_order_cap) {
		wc_add_notice(sprintf(__('This offer allows up to %d eligible tickets per order. Reduce the eligible ticket quantity to continue.', 'backstage-outreach'), $per_order_cap), 'error');
	}
	if ($excess_existing_discount) {
		wc_add_notice(sprintf(__('An existing automatic ticket discount would exceed this Admission Offer (%s). The offer will not stack beyond its advertised value; remove the conflicting discount before checkout.', 'backstage-outreach'), backstage_outreach_discount_terms_label($terms, false)), 'error');
	}
}
add_action('woocommerce_check_cart_items', 'backstage_outreach_discount_validate_cart', 30);

function backstage_outreach_discount_sort_offer_events(array $events): array
{
	usort($events, static function (array $left, array $right): int {
		$left_date = trim((string) ($left['event_date'] ?? ''));
		$right_date = trim((string) ($right['event_date'] ?? ''));
		$left_key = ($left_date !== '' ? $left_date : '9999-12-31') . ' ' . trim((string) ($left['start_time'] ?? ''));
		$right_key = ($right_date !== '' ? $right_date : '9999-12-31') . ' ' . trim((string) ($right['start_time'] ?? ''));
		$comparison = strcmp($left_key, $right_key);
		return $comparison !== 0 ? $comparison : absint($left['id'] ?? 0) <=> absint($right['id'] ?? 0);
	});
	return $events;
}

function backstage_outreach_discount_offer_events(array $row): array
{
	$events = array();
	foreach (backstage_outreach_discount_json_ids((string) ($row['eligible_event_ids_json'] ?? '')) as $event_plan_id) {
		$brief = bvmgr_pass_claims_get_event_plan_brief($event_plan_id);
		if (!is_array($brief)) {
			continue;
		}
		$tec_event_id = absint(get_post_meta($event_plan_id, '_vms_tec_event_id', true));
		$url = $tec_event_id > 0 ? get_permalink($tec_event_id) : '';
		if (!is_string($url) || $url === '') {
			continue;
		}
		$brief['ticket_url'] = $url;
		$brief['start_time'] = sanitize_text_field((string) get_post_meta($event_plan_id, '_vms_start_time', true));
		if ($tec_event_id > 0 && function_exists('tribe_get_start_date')) {
			$tec_start = sanitize_text_field((string) tribe_get_start_date($tec_event_id, false, 'Y-m-d H:i:s'));
			if ($tec_start !== '') {
				try {
					$tec_start_value = new DateTimeImmutable($tec_start, wp_timezone());
					if ((string) ($brief['event_date'] ?? '') === '') {
						$brief['event_date'] = $tec_start_value->format('Y-m-d');
					}
					if ((string) $brief['start_time'] === '') {
						$brief['start_time'] = $tec_start_value->format('H:i');
					}
				} catch (Exception $error) {
					// Keep the Event Plan display values when TEC start metadata is unavailable.
				}
			}
		}
		$featured_id = function_exists('get_post_thumbnail_id') ? absint(get_post_thumbnail_id($tec_event_id > 0 ? $tec_event_id : $event_plan_id)) : 0;
		$featured_url = $featured_id > 0 && function_exists('wp_get_attachment_image_url') ? wp_get_attachment_image_url($featured_id, 'large') : '';
		$brief['featured_image_url'] = is_string($featured_url) ? esc_url_raw($featured_url) : '';
		$events[] = $brief;
	}
	return backstage_outreach_discount_sort_offer_events($events);
}

function backstage_outreach_discount_offer_event_date_label(array $event): string
{
	$date = sanitize_text_field((string) ($event['event_date'] ?? ''));
	$time = sanitize_text_field((string) ($event['start_time'] ?? ''));
	if ($date === '') {
		return '';
	}
	try {
		$value = new DateTimeImmutable(trim($date . ' ' . $time), wp_timezone());
		$format = get_option('date_format');
		if ($time !== '') {
			$format .= ' ' . get_option('time_format');
		}
		return wp_date((string) $format, $value->getTimestamp(), wp_timezone());
	} catch (Exception $error) {
		return function_exists('bvmgr_pass_claims_format_public_date') ? bvmgr_pass_claims_format_public_date($date) : $date;
	}
}

function backstage_outreach_discount_offer_router(array $distribution, string $raw_token): void
{
	$error = backstage_outreach_discount_distribution_error($distribution);
	if (is_wp_error($error)) {
		backstage_outreach_render_public_offer_status(__('Admission Offer Unavailable', 'backstage-outreach'), $error->get_error_message(), 410);
	}
	$terms = backstage_outreach_discount_terms_for_distribution($distribution);
	if (is_wp_error($terms)) {
		backstage_outreach_render_public_offer_status(__('Admission Offer Unavailable', 'backstage-outreach'), $terms->get_error_message(), 410);
	}
	backstage_outreach_discount_session_set($distribution, $raw_token);
	backstage_outreach_discount_sync_cart_coupon();
	$events = backstage_outreach_discount_offer_events($distribution);
	if (empty($events)) {
		backstage_outreach_render_public_offer_status(__('No Eligible Tickets', 'backstage-outreach'), __('There are no eligible admission tickets available for this offer right now.', 'backstage-outreach'), 410);
	}
	bvmgr_pass_claims_render_public_shell(__('Admission Offer', 'backstage-outreach'), static function () use ($distribution, $events, $terms): void {
		$per_order_cap = max(1, absint($distribution['admissions_per_recipient'] ?? 1));
		$value_label = backstage_outreach_discount_terms_label($terms, false);
		$branding = backstage_outreach_flyer_branding();
		$design = backstage_outreach_resolved_flyer_design(
			absint($distribution['campaign_id'] ?? 0),
			is_array($distribution['batch'] ?? null) ? (array) $distribution['batch'] : array()
		);
		$artwork_url = (string) $design['artwork_url'];
		$fallback_image = $artwork_url !== '' ? $artwork_url : (string) $branding['logo_url'];
		$expiry = backstage_outreach_distribution_effective_expiry($distribution);
		$per_business_cap = absint($distribution['admission_cap'] ?? 0);
		$overall_cap = absint(($distribution['batch']['total_admission_cap'] ?? 0));
		echo '<style>.vms-offer-hero{text-align:center;margin-bottom:24px}.vms-offer-logo{display:block;max-width:min(340px,80vw);max-height:120px;width:auto;height:auto;margin:0 auto 18px}.vms-offer-venue{font-size:1.5rem;font-weight:800}.vms-offer-art{display:block;width:100%;max-height:340px;object-fit:cover;border-radius:16px;margin:18px 0}.vms-offer-benefit{font-size:clamp(1.5rem,5vw,2.3rem);line-height:1.15}.vms-offer-events{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr));gap:16px}.vms-offer-event{overflow:hidden}.vms-offer-event img,.vms-offer-event-fallback{display:flex;width:100%;aspect-ratio:16/9;align-items:center;justify-content:center;object-fit:cover;border-radius:10px}.vms-offer-event-fallback{padding:16px;background:#e8f2ee;color:#245548;text-align:center;font-weight:800}.vms-offer-event h2{margin-bottom:4px}.vms-offer-terms{margin-top:22px}.vms-offer-terms summary{cursor:pointer;font-weight:700}.vms-offer-availability{padding:12px 16px;border-radius:10px;background:#f3f8f6}.vms-pass-card .vms-offer-business{color:#526174}@media(max-width:390px){.vms-offer-events{grid-template-columns:1fr}.vms-offer-benefit{font-size:1.55rem}}</style>';
		echo '<header class="vms-offer-hero">';
		if ((string) $branding['logo_url'] !== '') {
			echo '<img class="vms-offer-logo" src="' . esc_url((string) $branding['logo_url']) . '" alt="' . esc_attr((string) $branding['site_name']) . '">';
		} else {
			echo '<p class="vms-offer-venue">' . esc_html((string) $branding['site_name']) . '</p>';
		}
		echo '<h1>' . esc_html((string) $design['heading']) . '</h1>';
		if ((string) $design['subheading'] !== '') {
			echo '<p>' . esc_html((string) $design['subheading']) . '</p>';
		}
		if ($artwork_url !== '') {
			echo '<img class="vms-offer-art" src="' . esc_url($artwork_url) . '" alt="">';
		}
		echo '<p class="vms-offer-benefit"><strong>' . esc_html(sprintf(__('%1$s for up to %2$d people', 'backstage-outreach'), $value_label, $per_order_cap)) . '</strong></p><p class="vms-offer-business">' . esc_html(sprintf(__('Shared by %s', 'backstage-outreach'), (string) $distribution['business_name'])) . '</p><p>' . esc_html__('Choose tickets for an eligible event. Your offer is applied automatically in the cart and checkout.', 'backstage-outreach') . '</p></header>';
		if ($expiry !== '' || $per_business_cap > 0 || $overall_cap > 0) {
			echo '<div class="vms-offer-availability"><strong>' . esc_html__('Availability', 'backstage-outreach') . '</strong><ul>';
			if ($expiry !== '') {
				echo '<li>' . esc_html(sprintf(__('Use this offer by %s.', 'backstage-outreach'), $expiry)) . '</li>';
			}
			if ($per_business_cap > 0) {
				echo '<li>' . esc_html(sprintf(_n('Maximum through this business: %d discounted admission.', 'Maximum through this business: %d discounted admissions.', $per_business_cap, 'backstage-outreach'), $per_business_cap)) . '</li>';
			}
			if ($overall_cap > 0) {
				echo '<li>' . esc_html(sprintf(_n('Up to %d admission is shared across all participating businesses and may be used first come, first served.', 'Up to %d admissions are shared across all participating businesses and may be used first come, first served.', $overall_cap, 'backstage-outreach'), $overall_cap)) . '</li>';
			}
			echo '</ul></div>';
		}
		echo '<h2>' . esc_html__('Choose tickets', 'backstage-outreach') . '</h2><div class="vms-offer-events">';
		foreach ($events as $event) {
			$label = (string) ($event['title'] ?? __('Event', 'backstage-outreach'));
			$date = backstage_outreach_discount_offer_event_date_label($event);
			$image_url = (string) ($event['featured_image_url'] ?? '') ?: $fallback_image;
			echo '<article class="vms-pass-card vms-offer-event">';
			if ($image_url !== '') {
				echo '<img src="' . esc_url($image_url) . '" alt="">';
			} else {
				echo '<div class="vms-offer-event-fallback">' . esc_html((string) $branding['site_name']) . '</div>';
			}
			echo '<h2>' . esc_html($label) . '</h2>';
			if ($date !== '') {
				echo '<p>' . esc_html($date) . '</p>';
			}
			echo '<p><a class="button" href="' . esc_url((string) $event['ticket_url']) . '">' . esc_html__('Choose tickets', 'backstage-outreach') . '</a></p></article>';
		}
		echo '</div><details class="vms-offer-terms"><summary>' . esc_html__('Offer terms', 'backstage-outreach') . '</summary><ul>';
		echo '<li>' . esc_html(sprintf(__('%s applies only to eligible admission tickets. Merchandise, food, rentals, add-ons, and other tickets keep their normal pricing.', 'backstage-outreach'), $value_label)) . '</li>';
		echo '<li>' . esc_html__('This offer cannot be combined with another coupon or a larger automatic ticket discount.', 'backstage-outreach') . '</li>';
		echo '<li>' . esc_html(sprintf(__('%1$s for up to %2$d people per order. A fixed discount never reduces a ticket below $0.', 'backstage-outreach'), $value_label, $per_order_cap)) . '</li>';
		if (absint($distribution['order_cap'] ?? 0) > 0) {
			echo '<li>' . esc_html(sprintf(_n('This business link may be used for up to %d order.', 'This business link may be used for up to %d orders.', absint($distribution['order_cap']), 'backstage-outreach'), absint($distribution['order_cap']))) . '</li>';
		}
		echo '</ul></details>';
		if (function_exists('WC') && WC() && WC()->cart && backstage_outreach_discount_cart_has_eligible_item($distribution)) {
			echo '<p class="vms-pass-actions"><a class="button" href="' . esc_url(wc_get_checkout_url()) . '">' . esc_html__('Continue to Checkout', 'backstage-outreach') . '</a></p>';
		}
	});
}

function backstage_outreach_discount_order_context($order): ?array
{
	if (!($order instanceof WC_Order)) {
		return null;
	}
	$distribution_id = absint($order->get_meta('_backstage_outreach_distribution_id', true));
	$row = $distribution_id > 0 ? backstage_outreach_discount_get_distribution($distribution_id) : backstage_outreach_discount_session_distribution();
	if (!is_array($row)) {
		return null;
	}
	if ($distribution_id > 0) {
		$matches = (string) $order->get_meta('_backstage_outreach_offer_type', true) === 'coupon_backed'
			&& absint($order->get_meta('_backstage_outreach_campaign_id', true)) === absint($row['campaign_id'] ?? 0)
			&& absint($order->get_meta('_backstage_outreach_source_id', true)) === absint($row['source_id'] ?? 0)
			&& absint($order->get_meta('_backstage_outreach_business_id', true)) === absint($row['business_id'] ?? 0)
			&& absint($order->get_meta('_backstage_outreach_coupon_id', true)) === absint($row['coupon_id'] ?? 0);
		return $matches ? $row : null;
	}
	$code = wc_format_coupon_code((string) ($row['coupon_code'] ?? ''));
	$order_codes = array_map('wc_format_coupon_code', (array) $order->get_coupon_codes());
	return $code !== '' && in_array($code, $order_codes, true) ? $row : null;
}

function backstage_outreach_discount_stamp_order($order, $posted_data = array()): void
{
	if (!($order instanceof WC_Order)) {
		return;
	}
	$row = backstage_outreach_discount_order_context($order);
	if (!is_array($row)) {
		return;
	}
	$order->update_meta_data('_backstage_outreach_distribution_id', absint($row['id']));
	$order->update_meta_data('_backstage_outreach_campaign_id', absint($row['campaign_id']));
	$order->update_meta_data('_backstage_outreach_source_id', absint($row['source_id']));
	$order->update_meta_data('_backstage_outreach_business_id', absint($row['business_id']));
	$order->update_meta_data('_backstage_outreach_coupon_id', absint($row['coupon_id']));
	$order->update_meta_data('_backstage_outreach_offer_type', 'coupon_backed');
	$terms = backstage_outreach_discount_terms_for_distribution($row);
	if (is_array($terms)) {
		$order->update_meta_data('_backstage_outreach_offer_value_type', (string) $terms['value_type']);
		$order->update_meta_data('_backstage_outreach_offer_value_amount', (float) $terms['value_amount']);
		$order->update_meta_data('_backstage_outreach_offer_legacy_free_capacity', !empty($terms['legacy_free_capacity']) ? '1' : '0');
	}
}
add_action('woocommerce_checkout_create_order', 'backstage_outreach_discount_stamp_order', 30, 2);

function backstage_outreach_discount_stamp_line_item($item, string $cart_item_key, array $values, $order): void
{
	if (!is_object($item) || !method_exists($item, 'add_meta_data')) {
		return;
	}
	$row = backstage_outreach_discount_session_distribution();
	if (!is_array($row)) {
		return;
	}
	$product_id = absint($values['variation_id'] ?? 0) ?: absint($values['product_id'] ?? 0);
	$parent_id = absint($values['product_id'] ?? 0);
	$allowed = backstage_outreach_discount_json_ids((string) ($row['eligible_product_ids_json'] ?? ''));
	if (!in_array($product_id, $allowed, true) && !in_array($parent_id, $allowed, true)) {
		return;
	}
	$item->add_meta_data('_backstage_outreach_distribution_id', absint($row['id']), true);
	$item->add_meta_data('_backstage_outreach_campaign_id', absint($row['campaign_id']), true);
	$item->add_meta_data('_backstage_outreach_source_id', absint($row['source_id']), true);
	$item->add_meta_data('_backstage_outreach_business_id', absint($row['business_id']), true);
	$item->add_meta_data('_backstage_outreach_coupon_id', absint($row['coupon_id']), true);
	$terms = backstage_outreach_discount_terms_for_distribution($row);
	if (is_array($terms)) {
		$item->add_meta_data('_backstage_outreach_offer_value_type', (string) $terms['value_type'], true);
		$item->add_meta_data('_backstage_outreach_offer_value_amount', (float) $terms['value_amount'], true);
	}
}
add_action('woocommerce_checkout_create_order_line_item', 'backstage_outreach_discount_stamp_line_item', 30, 4);

function backstage_outreach_discount_order_ticket_snapshot(WC_Order $order, array $row): array
{
	$allowed = backstage_outreach_discount_json_ids((string) ($row['eligible_product_ids_json'] ?? ''));
	$ids = array();
	$quantity = 0;
	foreach ($order->get_items('line_item') as $item) {
		$product_id = absint($item->get_variation_id()) ?: absint($item->get_product_id());
		$parent_id = absint($item->get_product_id());
		if (!in_array($product_id, $allowed, true) && !in_array($parent_id, $allowed, true)) {
			continue;
		}
		$ids[] = $product_id;
		$quantity += max(0, (int) $item->get_quantity());
	}
	return array('product_ids' => array_values(array_unique($ids)), 'quantity' => $quantity);
}

function backstage_outreach_discount_order_financial_snapshot(WC_Order $order, array $row): array
{
	$allowed = backstage_outreach_discount_json_ids((string) ($row['eligible_product_ids_json'] ?? ''));
	$gross = 0.0;
	$commerce_discount = 0.0;
	$coupon_discount = 0.0;
	$net = 0.0;
	$target_discount = 0.0;
	$terms = backstage_outreach_discount_terms_for_distribution($row);
	foreach ($order->get_items('line_item') as $item) {
		$product_id = absint($item->get_variation_id()) ?: absint($item->get_product_id());
		$parent_id = absint($item->get_product_id());
		if (!in_array($product_id, $allowed, true) && !in_array($parent_id, $allowed, true)) {
			continue;
		}
		$line_subtotal = max(0.0, (float) $item->get_subtotal());
		$line_total = max(0.0, (float) $item->get_total());
		$line_commerce_discount = max(0.0, (float) $item->get_meta('_vms_discounts_line_discount', true));
		$line_original = max(0.0, (float) $item->get_meta('_vms_discounts_original_line_subtotal', true));
		if ($line_original <= 0.0) {
			$line_original = $line_subtotal + $line_commerce_discount;
		}
		$gross += $line_original;
		$commerce_discount += $line_commerce_discount;
		$coupon_discount += max(0.0, $line_subtotal - $line_total);
		$net += $line_total;
		if (is_array($terms)) {
			$line_quantity = max(1, (int) $item->get_quantity());
			$target_discount += backstage_outreach_discount_target_for_line($line_original / $line_quantity, $line_quantity, $terms);
		}
	}
	return array(
		'gross' => $gross,
		'commerce_discount' => $commerce_discount,
		'coupon_discount' => $coupon_discount,
		'net' => $net,
		'target_discount' => $target_discount,
	);
}

function backstage_outreach_discount_validate_order_pricing(WC_Order $order, array $row): void
{
	$expected_code = wc_format_coupon_code((string) ($row['coupon_code'] ?? ''));
	$order_codes = array_values(array_filter(array_map('wc_format_coupon_code', (array) $order->get_coupon_codes())));
	if ($expected_code === '' || count($order_codes) !== 1 || !wc_is_same_coupon($order_codes[0], $expected_code)) {
		throw new Exception(__('The unpaid order no longer contains only its managed Admission Offer coupon. Reopen the signed offer link and start checkout again.', 'backstage-outreach'));
	}
	$financial = backstage_outreach_discount_order_financial_snapshot($order, $row);
	$gross = (float) $financial['gross'];
	$commerce_discount = (float) $financial['commerce_discount'];
	$coupon_discount = (float) $financial['coupon_discount'];
	$target = (float) $financial['target_discount'];
	$precision = 1 / max(1, 10 ** wc_get_price_decimals());
	if ($gross <= 0.0 || $commerce_discount > $target + $precision || abs(($commerce_discount + $coupon_discount) - $target) > $precision) {
		throw new Exception(__('The unpaid order no longer matches the Admission Offer price. Reopen the signed offer link and start checkout again.', 'backstage-outreach'));
	}
}

function backstage_outreach_discount_order_eligible_refunded_total(WC_Order $order, array $row): float
{
	$allowed = backstage_outreach_discount_json_ids((string) ($row['eligible_product_ids_json'] ?? ''));
	$total = 0.0;
	foreach ($order->get_refunds() as $refund) {
		foreach ($refund->get_items('line_item') as $item) {
			$product_id = absint($item->get_variation_id()) ?: absint($item->get_product_id());
			$parent_id = absint($item->get_product_id());
			if (in_array($product_id, $allowed, true) || in_array($parent_id, $allowed, true)) {
				$total += abs((float) $item->get_total());
			}
		}
	}
	return $total;
}

function backstage_outreach_discount_order_coupon_total(WC_Order $order, string $code): float
{
	foreach ($order->get_items('coupon') as $item) {
		if (wc_is_same_coupon((string) $item->get_code(), $code)) {
			return max(0.0, (float) $item->get_discount() + (float) $item->get_discount_tax());
		}
	}
	return 0.0;
}

function backstage_outreach_discount_reserve_order(WC_Order $order): void
{
	$row = backstage_outreach_discount_order_context($order);
	if (!is_array($row) || $order->get_id() <= 0) {
		return;
	}
	global $wpdb;
	$table = backstage_outreach_business_table('paid_redemptions');
	$batch_id = absint($row['related_batch_id'] ?? 0);
	if ($batch_id <= 0) {
		throw new Exception(__('The offer batch could not be validated.', 'backstage-outreach'));
	}
	$lock_name = 'bvm-pass-batch-' . $batch_id;
	if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, 5)) !== 1) {
		throw new Exception(__('The offer limit is being updated. Please try checkout again.', 'backstage-outreach'));
	}
	try {
		$row = backstage_outreach_discount_get_distribution(absint($row['id'] ?? 0));
		$error = is_array($row) ? backstage_outreach_discount_distribution_error($row) : new WP_Error('offer_missing', __('This Admission Offer is no longer available.', 'backstage-outreach'));
		if (is_wp_error($error)) {
			throw new Exception($error->get_error_message());
		}
		$snapshot = backstage_outreach_discount_order_ticket_snapshot($order, $row);
		$ticket_quantity = absint($snapshot['quantity'] ?? 0);
		if ($ticket_quantity <= 0) {
			throw new Exception(__('The Admission Offer requires at least one eligible ticket.', 'backstage-outreach'));
		}
		$per_order_cap = max(1, absint($row['admissions_per_recipient'] ?? 1));
		if ($ticket_quantity > $per_order_cap) {
			throw new Exception(sprintf(__('This offer allows up to %d eligible tickets per order.', 'backstage-outreach'), $per_order_cap));
		}
		backstage_outreach_discount_validate_order_pricing($order, $row);
		$campaign_id = absint($row['campaign_id']);
		$now = backstage_outreach_business_now();
		$per_business_used = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COALESCE(SUM(ticket_quantity),0) FROM %i WHERE distribution_id=%d AND order_id<>%d AND (status='paid' OR (status='pending' AND reservation_expires_at>%s))",
			$table, absint($row['id']), $order->get_id(), $now
		));
		$per_business_complimentary = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COALESCE(SUM(party_size),0) FROM %i WHERE distribution_id=%d AND status='fulfilled'",
			backstage_outreach_business_table('distribution_claims'), absint($row['id'])
		));
		$business_cap = absint($row['admission_cap'] ?? 0);
		if ($business_cap > 0 && $per_business_used + $per_business_complimentary + $ticket_quantity > $business_cap) {
			throw new Exception(__('This Admission Offer has reached its discounted-ticket limit.', 'backstage-outreach'));
		}
		$paid_or_held = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COALESCE(SUM(ticket_quantity),0) FROM %i WHERE campaign_id=%d AND order_id<>%d AND (status='paid' OR (status='pending' AND reservation_expires_at>%s))",
			$table, $campaign_id, $order->get_id(), $now
		));
		$complimentary = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COALESCE(SUM(e.party_size),0) FROM %i e INNER JOIN %i pc ON pc.id=e.pass_claim_id WHERE pc.outreach_campaign_id=%d AND e.status<>'canceled'",
			bvmgr_admission_table_entries(), bvmgr_admission_table_pass_claims(), $campaign_id
		));
		$campaign_cap = absint($row['campaign_ticket_cap'] ?? 0);
		if ($campaign_cap > 0 && $paid_or_held + $complimentary + $ticket_quantity > $campaign_cap) {
			throw new Exception(__('This campaign has reached its combined ticket limit.', 'backstage-outreach'));
		}
		$batch = bvmgr_pass_claims_get_batch_by_id(absint($row['related_batch_id'] ?? 0));
		$batch_cap = is_array($batch) ? absint($batch['total_admission_cap'] ?? 0) : 0;
		if ($batch_cap > 0) {
			$batch_complimentary = (int) $wpdb->get_var($wpdb->prepare(
				"SELECT COALESCE(SUM(party_size),0) FROM %i WHERE pass_batch_id=%d AND status<>'canceled'",
				bvmgr_admission_table_entries(), absint($row['related_batch_id'])
			));
			$batch_paid_or_held = (int) $wpdb->get_var($wpdb->prepare(
				"SELECT COALESCE(SUM(pr.ticket_quantity),0) FROM %i pr INNER JOIN %i c ON c.id=pr.campaign_id WHERE c.related_batch_id=%d AND pr.order_id<>%d AND (pr.status='paid' OR (pr.status='pending' AND pr.reservation_expires_at>%s))",
				$table, vms_admission_table_pass_outreach_campaigns(), absint($row['related_batch_id']), $order->get_id(), $now
			));
			if ($batch_paid_or_held + $batch_complimentary + $ticket_quantity > $batch_cap) {
				throw new Exception(__('This offer batch has reached its combined ticket limit.', 'backstage-outreach'));
			}
		}
		$hold_minutes = max(10, absint(get_option('woocommerce_hold_stock_minutes', 60)));
		$expires = wp_date('Y-m-d H:i:s', time() + ($hold_minutes * MINUTE_IN_SECONDS), wp_timezone());
		$financial = backstage_outreach_discount_order_financial_snapshot($order, $row);
		$data = array(
			'distribution_id' => absint($row['id']), 'campaign_id' => $campaign_id,
			'source_id' => absint($row['source_id']), 'business_id' => absint($row['business_id']),
			'coupon_id' => absint($row['coupon_id']), 'coupon_code' => (string) $row['coupon_code'],
			'order_id' => $order->get_id(), 'status' => 'pending', 'ticket_quantity' => $ticket_quantity,
			'discount_total' => backstage_outreach_discount_order_coupon_total($order, (string) $row['coupon_code']),
			'eligible_ticket_gross_total' => (float) $financial['gross'],
			'eligible_ticket_net_total' => (float) $financial['net'],
			'eligible_ticket_refunded_total' => backstage_outreach_discount_order_eligible_refunded_total($order, $row),
			'order_total' => max(0.0, (float) $order->get_total()),
			'refunded_total' => max(0.0, (float) $order->get_total_refunded()),
			'settlement_review_code' => null,
			'currency' => (string) $order->get_currency(),
			'eligible_ticket_ids_json' => backstage_outreach_discount_encode_ids((array) $snapshot['product_ids']),
			'updated_at' => $now, 'reservation_expires_at' => $expires,
		);
		$existing_id = absint($wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE order_id=%d', $table, $order->get_id())));
		if ($existing_id > 0) {
			$written = $wpdb->update($table, $data, array('id' => $existing_id));
		} else {
			$data['created_at'] = $now;
			$written = $wpdb->insert($table, $data);
		}
		if ($written === false) {
			throw new Exception(__('The offer attribution could not be reserved safely. Please retry.', 'backstage-outreach'));
		}
		$order->update_meta_data('_backstage_outreach_discounted_ticket_quantity', $ticket_quantity);
		$order->update_meta_data('_backstage_outreach_eligible_ticket_ids', (array) $snapshot['product_ids']);
		$order->save_meta_data();
	} finally {
		$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
	}
}

function backstage_outreach_discount_classic_order_created($order): void
{
	if ($order instanceof WC_Order) {
		backstage_outreach_discount_stamp_order($order);
		$order->save_meta_data();
		backstage_outreach_discount_reserve_order($order);
	}
}
add_action('woocommerce_checkout_order_created', 'backstage_outreach_discount_classic_order_created', 30);

function backstage_outreach_discount_store_api_order($order): void
{
	if ($order instanceof WC_Order) {
		backstage_outreach_discount_stamp_order($order);
		$order->save_meta_data();
		backstage_outreach_discount_reserve_order($order);
	}
}
add_action('woocommerce_store_api_checkout_update_order_meta', 'backstage_outreach_discount_store_api_order', 30);

function backstage_outreach_discount_sync_redemption_status(int $order_id, string $status): void
{
	$order = wc_get_order($order_id);
	if (!$order) {
		return;
	}
	$row = backstage_outreach_discount_order_context($order);
	if (!is_array($row)) {
		return;
	}
	global $wpdb;
	$table = backstage_outreach_business_table('paid_redemptions');
	$batch_id = absint($row['related_batch_id'] ?? 0);
	$lock_name = $batch_id > 0 ? 'bvm-pass-batch-' . $batch_id : '';
	if ($lock_name === '' || (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, 15)) !== 1) {
		wp_schedule_single_event(time() + 30, 'backstage_outreach_discount_reconcile_status', array($order_id, $status));
		return;
	}
	try {
		$now = backstage_outreach_business_now();
		$current = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE order_id=%d', $table, $order_id), ARRAY_A);
		if (!is_array($current)) {
			return;
		}
		$fully_refunded = (float) $order->get_total() > 0.0 && $order->get_remaining_refund_amount() <= 0;
		$order_status = (string) $order->get_status();
		if ($fully_refunded) {
			$status = 'refunded';
		} elseif ($order->is_paid()) {
			$status = 'paid';
		} elseif (in_array($order_status, array('refunded', 'cancelled', 'failed'), true)) {
			$status = $order_status;
		} elseif ($status === 'paid') {
			return;
		}
		$review_code = (string) ($current['settlement_review_code'] ?? '');
		if ($status === 'paid') {
			$late = false;
			if (!empty($current['reservation_expires_at'])) {
				try {
					$late = (new DateTimeImmutable((string) $current['reservation_expires_at'], wp_timezone()))->getTimestamp() <= time();
				} catch (Exception $error) {
					$late = true;
				}
			}
			$closed = is_wp_error(backstage_outreach_discount_distribution_error($row));
			if ($late && $closed) {
				$review_code = 'late_settlement_after_offer_closed';
			} elseif ($late) {
				$review_code = 'late_settlement_after_reservation_expiry';
			} elseif ($closed) {
				$review_code = 'settled_after_offer_closed';
			}
		}
		$financial = backstage_outreach_discount_order_financial_snapshot($order, $row);
		$data = array(
			'status' => $status,
			'discount_total' => backstage_outreach_discount_order_coupon_total($order, (string) $row['coupon_code']),
			'eligible_ticket_gross_total' => (float) $financial['gross'],
			'eligible_ticket_net_total' => (float) $financial['net'],
			'eligible_ticket_refunded_total' => backstage_outreach_discount_order_eligible_refunded_total($order, $row),
			'order_total' => max(0.0, (float) $order->get_total()),
			'refunded_total' => max(0.0, (float) $order->get_total_refunded()),
			'settlement_review_code' => $review_code !== '' ? $review_code : null,
			'updated_at' => $now,
			'reservation_expires_at' => null,
		);
		if ($status === 'paid' && empty($current['paid_at'])) { $data['paid_at'] = $now; }
		if ($status === 'failed' && empty($current['failed_at'])) { $data['failed_at'] = $now; }
		if ($status === 'cancelled' && empty($current['cancelled_at'])) { $data['cancelled_at'] = $now; }
		if ($status === 'refunded' && empty($current['refunded_at'])) { $data['refunded_at'] = $now; }
		if ($wpdb->update($table, $data, array('order_id' => $order_id)) === false) {
			wp_schedule_single_event(time() + 30, 'backstage_outreach_discount_reconcile_status', array($order_id, $status));
		}
	} finally {
		$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
	}
}
add_action('backstage_outreach_discount_reconcile_status', 'backstage_outreach_discount_sync_redemption_status', 10, 2);

function backstage_outreach_discount_mark_paid(int $order_id): void { backstage_outreach_discount_sync_redemption_status($order_id, 'paid'); }
function backstage_outreach_discount_mark_failed(int $order_id): void { backstage_outreach_discount_sync_redemption_status($order_id, 'failed'); }
function backstage_outreach_discount_mark_cancelled(int $order_id): void { backstage_outreach_discount_sync_redemption_status($order_id, 'cancelled'); }
function backstage_outreach_discount_mark_refunded(int $order_id): void { backstage_outreach_discount_sync_redemption_status($order_id, 'refunded'); }
function backstage_outreach_discount_record_refund(int $order_id): void
{
	$order = wc_get_order($order_id);
	if (!$order) {
		return;
	}
	$status = $order->get_remaining_refund_amount() <= 0 ? 'refunded' : 'paid';
	backstage_outreach_discount_sync_redemption_status($order_id, $status);
}
add_action('woocommerce_payment_complete', 'backstage_outreach_discount_mark_paid', 30);
add_action('woocommerce_order_status_processing', 'backstage_outreach_discount_mark_paid', 30);
add_action('woocommerce_order_status_completed', 'backstage_outreach_discount_mark_paid', 30);
add_action('woocommerce_order_status_failed', 'backstage_outreach_discount_mark_failed', 30);
add_action('woocommerce_order_status_cancelled', 'backstage_outreach_discount_mark_cancelled', 30);
add_action('woocommerce_order_status_refunded', 'backstage_outreach_discount_mark_refunded', 30);
add_action('woocommerce_order_refunded', 'backstage_outreach_discount_record_refund', 30, 1);

function backstage_outreach_discount_validate_order_before_payment($order, $errors = null): void
{
	if (!($order instanceof WC_Order) || $order->is_paid()) {
		return;
	}
	$distribution_id = absint($order->get_meta('_backstage_outreach_distribution_id', true));
	if ($distribution_id <= 0) {
		return;
	}
	$row = backstage_outreach_discount_get_distribution($distribution_id);
	$error = is_array($row) ? backstage_outreach_discount_distribution_error($row) : new WP_Error('offer_missing', __('This Admission Offer is no longer available.', 'backstage-outreach'));
	if (!is_wp_error($error) && !is_array(backstage_outreach_discount_order_context($order))) {
		$error = new WP_Error('offer_attribution_mismatch', __('This unpaid order no longer matches its server-owned Admission Offer attribution. Reopen the signed business link and start checkout again.', 'backstage-outreach'));
	}
	if (!is_wp_error($error) && is_array($row)) {
		$expected_code = wc_format_coupon_code((string) ($row['coupon_code'] ?? ''));
		$order_codes = array_map('wc_format_coupon_code', (array) $order->get_coupon_codes());
		if ($expected_code === '' || !in_array($expected_code, $order_codes, true)) {
			$error = new WP_Error('offer_coupon_removed', __('The managed Admission Offer coupon was removed from this unpaid order. Reopen the signed business link and return to checkout.', 'backstage-outreach'));
		}
	}
	if (!is_wp_error($error)) {
		backstage_outreach_discount_reserve_order($order);
		return;
	}
	if ($errors instanceof WP_Error) {
		$errors->add('backstage_outreach_offer_unavailable', $error->get_error_message());
		return;
	}
	throw new Exception($error->get_error_message());
}
add_action('woocommerce_checkout_validate_order_before_payment', 'backstage_outreach_discount_validate_order_before_payment', 20, 2);

function backstage_outreach_discount_validate_order_pay_action($order): void
{
	try {
		backstage_outreach_discount_validate_order_before_payment($order);
	} catch (Throwable $error) {
		wc_add_notice($error->getMessage(), 'error');
	}
}
add_action('woocommerce_before_pay_action', 'backstage_outreach_discount_validate_order_pay_action', 20, 1);

function backstage_outreach_discount_paid_stats(int $distribution_id): array
{
	global $wpdb;
	$row = $wpdb->get_row($wpdb->prepare(
		"SELECT
		COUNT(DISTINCT CASE WHEN status='paid' THEN order_id END) paid_orders,
		COALESCE(SUM(CASE WHEN status='paid' THEN ticket_quantity ELSE 0 END),0) discounted_tickets,
		COALESCE(SUM(CASE WHEN status='paid' THEN discount_total ELSE 0 END),0) discount_total,
		COALESCE(SUM(CASE WHEN status='paid' THEN eligible_ticket_gross_total ELSE 0 END),0) eligible_ticket_gross_total,
		COALESCE(SUM(CASE WHEN status='paid' THEN GREATEST(0, eligible_ticket_net_total-eligible_ticket_refunded_total) ELSE 0 END),0) admission_revenue,
		COALESCE(SUM(CASE WHEN status='paid' THEN GREATEST(0, order_total-refunded_total) ELSE 0 END),0) order_revenue,
		COALESCE(SUM(refunded_total),0) refunded_total,
		COUNT(DISTINCT CASE WHEN status='refunded' THEN order_id END) refunded_orders,
		COUNT(DISTINCT CASE WHEN status='cancelled' THEN order_id END) cancelled_orders,
		COUNT(DISTINCT CASE WHEN settlement_review_code IS NOT NULL THEN order_id END) review_required_orders,
		MAX(currency) currency
		FROM %i WHERE distribution_id=%d",
		backstage_outreach_business_table('paid_redemptions'), $distribution_id
	), ARRAY_A);
	return is_array($row) ? $row : array('paid_orders' => 0, 'discounted_tickets' => 0, 'discount_total' => 0, 'eligible_ticket_gross_total' => 0, 'admission_revenue' => 0, 'order_revenue' => 0, 'refunded_total' => 0, 'refunded_orders' => 0, 'cancelled_orders' => 0, 'review_required_orders' => 0, 'currency' => '');
}
