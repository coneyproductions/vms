<?php
defined('ABSPATH') || exit;

/**
 * Ticketing Integration (Phase B)
 *
 * Purpose:
 * - Allow VMS to define ticket tiers on an Event Plan.
 * - Create / sync WooCommerce tickets on a LINKED TEC event using Event Tickets ORM.
 *
 * Safety:
 * - No background creation.
 * - Operator must explicitly click Preview → Confirm → Commit.
 * - No destructive deletes in v1.
 */

function bvmgr_ticketing_b_meta_key(string $field, string $fallback): string {
    if (function_exists('bvmgr_meta_key')) {
        $k = (string) bvmgr_meta_key('event_plan', $field);
        if ($k !== '') {
            return $k;
        }
    }
    return $fallback;
}

function bvmgr_ticketing_b_is_event_tickets_woo_available(): bool {
    // Basic pre-reqs.
    if (!post_type_exists('tribe_events')) {
        return false;
    }
    if (!class_exists('WooCommerce') || !function_exists('wc_get_product')) {
        return false;
    }

    // Event Tickets must exist (base plugin).
    if (!function_exists('tribe_tickets') && !class_exists('Tribe__Tickets__Tickets')) {
        return false;
    }

    // Preferred: use the Event Tickets helper, but only require the methods we actually use.
    if (function_exists('tribe_tickets')) {
        try {
            $provider = tribe_tickets('woo');
        } catch (Throwable $e) {
            $provider = null;
        }

        if (is_object($provider) && method_exists($provider, 'set_args') && method_exists($provider, 'create')) {
            return true;
        }
    }

    // Fallback: older/newer provider class names (avoid hard failing when the helper differs).
    if (class_exists('Tribe__Tickets__Commerce__WooCommerce__Main')) {
        return true;
    }
    if (class_exists('Tribe__Tickets_Plus__Commerce__WooCommerce__Main')) {
        return true;
    }
    if (class_exists('Tribe__Tickets__Commerce__WooCommerce__Ticket')) {
        return true;
    }
    if (class_exists('Tribe__Tickets_Plus__Commerce__WooCommerce__Ticket')) {
        return true;
    }

    return false;
}


function bvmgr_ticketing_b_get_linked_tec_event_id(int $plan_id): int {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return 0;
    }
    $k_id = bvmgr_ticketing_b_meta_key('tec_event_id', '_vms_tec_event_id');
    return (int) get_post_meta($plan_id, $k_id, true);
}

function bvmgr_ticketing_b_get_mode(int $plan_id): string {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return 'read_only';
    }
    $k = bvmgr_ticketing_b_meta_key('ticketing_mode', '_vms_ticketing_mode_v1');
    $v = (string) get_post_meta($plan_id, $k, true);
    $v = trim($v);
    if ($v === '') {
        return 'read_only';
    }
    if (!in_array($v, array('none', 'read_only', 'vms_managed'), true)) {
        return 'read_only';
    }
    return $v;
}

function bvmgr_ticketing_b_set_mode(int $plan_id, string $mode): void {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return;
    }
    if (!in_array($mode, array('none', 'read_only', 'vms_managed'), true)) {
        $mode = 'read_only';
    }
    $k = bvmgr_ticketing_b_meta_key('ticketing_mode', '_vms_ticketing_mode_v1');
    update_post_meta($plan_id, $k, $mode);
}

function bvmgr_ticketing_b_get_tiers(int $plan_id): array {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return array();
    }
    $k = bvmgr_ticketing_b_meta_key('ticket_tiers', '_vms_ticket_tiers_v1');
    $tiers = get_post_meta($plan_id, $k, true);
    if (!is_array($tiers)) {
        return array();
    }
    return array_values($tiers);
}

function bvmgr_ticketing_b_set_tiers(int $plan_id, array $tiers): void {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return;
    }
    $k = bvmgr_ticketing_b_meta_key('ticket_tiers', '_vms_ticket_tiers_v1');
    update_post_meta($plan_id, $k, array_values($tiers));
}

function bvmgr_ticketing_b_get_map(int $plan_id): array {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return array();
    }
    $k = bvmgr_ticketing_b_meta_key('ticket_tier_map', '_vms_ticket_tier_map_v1');
    $m = get_post_meta($plan_id, $k, true);
    if (!is_array($m)) {
        return array();
    }
    return $m;
}

function bvmgr_ticketing_b_set_map(int $plan_id, array $map): void {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return;
    }
    $k = bvmgr_ticketing_b_meta_key('ticket_tier_map', '_vms_ticket_tier_map_v1');
    update_post_meta($plan_id, $k, $map);
}

function bvmgr_ticketing_b_normalize_tier(array $t): array {
    $tier_key = isset($t['tier_key']) ? sanitize_key((string) $t['tier_key']) : '';
    if ($tier_key === '') {
        $tier_key = 'tier_' . wp_generate_password(10, false, false);
        $tier_key = sanitize_key($tier_key);
    }

    $name = isset($t['name']) ? bvmgr_ticketing_v2_sanitize_plain_text_label($t['name']) : '';
    $name = trim($name);

    $price_raw = isset($t['price']) ? (string) $t['price'] : '0';
    $price_raw = trim($price_raw);
    // Store as a string so we don't lose formatting and to avoid float surprises.
    if ($price_raw === '') {
        $price_raw = '0';
    }
    if (!is_numeric($price_raw)) {
        $price_raw = '0';
    }
    $price = (string) (0 + $price_raw);
    if ($price === '-0') {
        $price = '0';
    }

    $early_price_raw = isset($t['early_price']) ? trim((string) $t['early_price']) : '';
    $early_price = '';
    if ($early_price_raw !== '' && is_numeric($early_price_raw)) {
        $early_price_value = max(0.0, (float) $early_price_raw);
        if ($early_price_value > 0) {
            $early_price = (string) (0 + $early_price_value);
        }
    }
    $early_price_start = isset($t['early_price_start']) ? sanitize_text_field((string) $t['early_price_start']) : '';
    $early_price_end = isset($t['early_price_end']) ? sanitize_text_field((string) $t['early_price_end']) : '';
    $early_price_start_relative_days = function_exists('bvmgr_ticketing_v2_normalize_relative_days') ? bvmgr_ticketing_v2_normalize_relative_days($t['early_price_start_relative_days'] ?? '') : '';
    $early_price_end_relative_days = function_exists('bvmgr_ticketing_v2_normalize_relative_days') ? bvmgr_ticketing_v2_normalize_relative_days($t['early_price_end_relative_days'] ?? '') : '';
    $early_price_cap = max(0, absint($t['early_price_cap'] ?? ($t['early_price_limit'] ?? 0)));

    $capacity = null;
    if (array_key_exists('capacity', $t)) {
        $cap = (string) $t['capacity'];
        $cap = trim($cap);
        if ($cap !== '') {
            $cap_i = (int) $cap;
            if ($cap_i >= 0) {
                $capacity = $cap_i;
            }
        }
    }

    $sales_start = isset($t['sales_start']) ? sanitize_text_field((string) $t['sales_start']) : '';
    $sales_end   = isset($t['sales_end']) ? sanitize_text_field((string) $t['sales_end']) : '';
    $sales_start_relative_days = function_exists('bvmgr_ticketing_v2_normalize_relative_days') ? bvmgr_ticketing_v2_normalize_relative_days($t['sales_start_relative_days'] ?? '') : '';
    $sales_end_relative_days = function_exists('bvmgr_ticketing_v2_normalize_relative_days') ? bvmgr_ticketing_v2_normalize_relative_days($t['sales_end_relative_days'] ?? '') : '';
    $is_hidden   = !empty($t['is_hidden']);

    $counts_attendance = array_key_exists('counts_toward_attendance', $t)
        ? !empty($t['counts_toward_attendance'])
        : true;

    $qualifies = !empty($t['qualifies_for_discounts']);
    $qual_code = isset($t['qualification_code']) ? sanitize_text_field((string) $t['qualification_code']) : '';
    $qual_code = trim($qual_code);

    return array(
        'tier_key' => $tier_key,
        'name' => $name,
        'price' => $price,
        'early_price' => $early_price,
        'early_price_start' => $early_price_start,
        'early_price_end' => $early_price_end,
        'early_price_start_relative_days' => $early_price_start_relative_days,
        'early_price_end_relative_days' => $early_price_end_relative_days,
        'early_price_cap' => $early_price_cap,
        'capacity' => $capacity,
        'sales_start' => $sales_start,
        'sales_end' => $sales_end,
        'sales_start_relative_days' => $sales_start_relative_days,
        'sales_end_relative_days' => $sales_end_relative_days,
        'is_hidden' => (bool) $is_hidden,
        'counts_toward_attendance' => (bool) $counts_attendance,
        'qualifies_for_discounts' => (bool) $qualifies,
        'qualification_code' => $qual_code,
    );
}

function bvmgr_ticketing_b_tier_hash(array $tier): string {
    $payload = array(
        'name' => (string) ($tier['name'] ?? ''),
        'price' => (string) ($tier['price'] ?? '0'),
        'early_price' => (string) ($tier['early_price'] ?? ''),
        'early_price_start' => (string) ($tier['early_price_start'] ?? ''),
        'early_price_end' => (string) ($tier['early_price_end'] ?? ''),
        'early_price_start_relative_days' => (string) ($tier['early_price_start_relative_days'] ?? ''),
        'early_price_end_relative_days' => (string) ($tier['early_price_end_relative_days'] ?? ''),
        'early_price_cap' => (string) ($tier['early_price_cap'] ?? '0'),
        'capacity' => $tier['capacity'] ?? null,
        'sales_start' => (string) ($tier['sales_start'] ?? ''),
        'sales_end' => (string) ($tier['sales_end'] ?? ''),
        'sales_start_relative_days' => (string) ($tier['sales_start_relative_days'] ?? ''),
        'sales_end_relative_days' => (string) ($tier['sales_end_relative_days'] ?? ''),
        'is_hidden' => !empty($tier['is_hidden']) ? 1 : 0,
    );
    return sha1(wp_json_encode($payload));
}

function bvmgr_ticketing_v2_money_string($value, string $empty_fallback = '0'): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return $empty_fallback;
    }
    if (!is_numeric($raw)) {
        return $empty_fallback;
    }
    $money = max(0.0, (float) $raw);
    $out = (string) (0 + $money);
    return ($out === '-0') ? '0' : $out;
}

function bvmgr_ticketing_v2_ticket_early_price_is_valid(float $regular_price, float $early_price, string $early_end_raw): bool {
    if ($regular_price <= 0 || $early_price <= 0 || $early_price >= $regular_price) {
        return false;
    }

    // Require an early-price end date so an advance price cannot accidentally
    // become an indefinite sale price when an operator forgets the deadline.
    $early_end_ts = function_exists('bvmgr_ticketing_v2_parse_datetime_to_timestamp')
        ? bvmgr_ticketing_v2_parse_datetime_to_timestamp($early_end_raw)
        : (int) strtotime($early_end_raw);

    return $early_end_ts > 0;
}

function bvmgr_ticketing_v2_ticket_early_price_cap(array $ticket): int {
    return max(0, absint($ticket['early_price_cap'] ?? ($ticket['early_price_limit'] ?? 0)));
}

function bvmgr_ticketing_v2_ticket_runtime_product_id(array $ticket): int {
    foreach (array('_vms_runtime_product_id', 'woo_product_id', 'product_id') as $key) {
        $pid = absint($ticket[$key] ?? 0);
        if ($pid > 0) {
            return $pid;
        }
    }
    return 0;
}

function bvmgr_ticketing_v2_net_sold_qty_for_product(int $product_id): int {
    $product_id = absint($product_id);
    if ($product_id <= 0) {
        return 0;
    }

    static $cache = array();
    if (isset($cache[$product_id])) {
        return max(0, absint($cache[$product_id]));
    }

    $paid_statuses = function_exists('bvmgr_ticketing_v2_paid_order_statuses') ? bvmgr_ticketing_v2_paid_order_statuses() : array('processing', 'completed', 'on-hold');

    // Use order-item SQL first because Woo's product lookup table can keep gross
    // quantities after full/partial refunds. Early Bird caps and customer-owned
    // ticket notices must use net active quantities.
    $sold = function_exists('bvmgr_ticketing_v2_calc_sold_qty_for_product_via_order_items')
        ? bvmgr_ticketing_v2_calc_sold_qty_for_product_via_order_items($product_id, $paid_statuses)
        : null;
    if ($sold === null && function_exists('bvmgr_ticketing_v2_calc_sold_qty_for_product_via_lookup')) {
        $sold = bvmgr_ticketing_v2_calc_sold_qty_for_product_via_lookup($product_id, $paid_statuses);
    }
    if ($sold === null && function_exists('bvmgr_ticketing_v2_calc_sold_qty_for_product')) {
        $summary = bvmgr_ticketing_v2_calc_sold_qty_for_product($product_id);
        $sold = is_array($summary) ? absint($summary['sold_qty'] ?? 0) : 0;
    }

    $cache[$product_id] = max(0, absint($sold));
    return max(0, absint($cache[$product_id]));
}

function bvmgr_ticketing_v2_get_ticket_early_price_state(array $ticket): array {
    $regular_price = max(0.0, (float) ($ticket['price'] ?? 0));
    $early_price = max(0.0, (float) ($ticket['early_price'] ?? 0));
    $early_start_raw = sanitize_text_field((string) ($ticket['early_price_start'] ?? ''));
    $early_end_raw = sanitize_text_field((string) ($ticket['early_price_end'] ?? ''));
    $early_cap = bvmgr_ticketing_v2_ticket_early_price_cap($ticket);
    $product_id = bvmgr_ticketing_v2_ticket_runtime_product_id($ticket);

    $start_ts = function_exists('bvmgr_ticketing_v2_parse_datetime_to_timestamp')
        ? bvmgr_ticketing_v2_parse_datetime_to_timestamp($early_start_raw)
        : (int) strtotime($early_start_raw);
    $end_ts = function_exists('bvmgr_ticketing_v2_parse_datetime_to_timestamp')
        ? bvmgr_ticketing_v2_parse_datetime_to_timestamp($early_end_raw)
        : (int) strtotime($early_end_raw);

    $valid = bvmgr_ticketing_v2_ticket_early_price_is_valid($regular_price, $early_price, $early_end_raw);
    if (!$valid && $regular_price > 0 && $early_price > 0 && $early_price < $regular_price && $early_cap > 0) {
        // A capped Early Bird pool can be used without a hard end date; the
        // cap becomes the expiry condition.
        $valid = true;
    }
    if ($valid && $start_ts > 0 && $end_ts > 0 && $start_ts > $end_ts) {
        $valid = false;
    }

    $now = time();
    $active_by_date = $valid
        && ($start_ts <= 0 || $now >= $start_ts)
        && ($end_ts <= 0 || $now <= $end_ts);

    $sold_qty = -1;
    $remaining_qty = -1;
    $cap_exhausted = false;
    if ($early_cap > 0 && $product_id > 0) {
        $sold_qty = bvmgr_ticketing_v2_net_sold_qty_for_product($product_id);
        $remaining_qty = max(0, $early_cap - $sold_qty);
        $cap_exhausted = ($remaining_qty <= 0);
    }

    $active = $active_by_date && !$cap_exhausted;

    return array(
        'valid' => $valid ? 1 : 0,
        'active' => $active ? 1 : 0,
        'active_by_date' => $active_by_date ? 1 : 0,
        'regular_price' => $regular_price,
        'early_price' => $early_price,
        'early_price_start' => $early_start_raw,
        'early_price_end' => $early_end_raw,
        'early_price_start_ts' => $start_ts,
        'early_price_end_ts' => $end_ts,
        'early_price_cap' => $early_cap,
        'product_id' => $product_id,
        'sold_qty' => $sold_qty,
        'remaining_qty' => $remaining_qty,
        'cap_exhausted' => $cap_exhausted ? 1 : 0,
    );
}

function bvmgr_ticketing_v2_get_ticket_effective_price(array $ticket): float {
    $regular_price = max(0.0, (float) ($ticket['price'] ?? 0));
    $state = bvmgr_ticketing_v2_get_ticket_early_price_state($ticket);
    return !empty($state['active']) ? max(0.0, (float) ($state['early_price'] ?? 0)) : $regular_price;
}

function bvmgr_ticketing_v2_price_payload_for_ticket(array $tier): array {
    $regular_price = bvmgr_ticketing_v2_money_string($tier['price'] ?? '0');
    $early_price = bvmgr_ticketing_v2_money_string($tier['early_price'] ?? '', '');
    $state = bvmgr_ticketing_v2_get_ticket_early_price_state($tier);
    $has_sale = !empty($state['valid']) && empty($state['cap_exhausted']);
    $effective_price = !empty($state['active']) ? $early_price : $regular_price;

    return array(
        'regular_price' => $regular_price,
        'sale_price' => $has_sale ? $early_price : '',
        'sale_from' => $has_sale && absint($state['early_price_start_ts'] ?? 0) > 0 ? absint($state['early_price_start_ts']) : '',
        'sale_to' => $has_sale && absint($state['early_price_end_ts'] ?? 0) > 0 ? absint($state['early_price_end_ts']) : '',
        'effective_price' => $effective_price,
        'has_sale' => $has_sale ? 1 : 0,
        'early_price_cap' => max(0, absint($state['early_price_cap'] ?? 0)),
    );
}

function bvmgr_ticketing_v2_apply_price_payload_to_product(int $product_id, array $tier): array {
    $product_id = absint($product_id);
    if ($product_id > 0) {
        $tier['_vms_runtime_product_id'] = $product_id;
    }
    $payload = bvmgr_ticketing_v2_price_payload_for_ticket($tier);
    if ($product_id <= 0) {
        return $payload;
    }

    update_post_meta($product_id, '_regular_price', $payload['regular_price']);
    update_post_meta($product_id, '_sale_price', $payload['sale_price']);
    update_post_meta($product_id, '_sale_price_dates_from', $payload['sale_from']);
    update_post_meta($product_id, '_sale_price_dates_to', $payload['sale_to']);
    update_post_meta($product_id, '_price', $payload['effective_price']);

    update_post_meta($product_id, '_vms_ticketing_regular_price_v2', $payload['regular_price']);
    $early_cap = max(0, absint($payload['early_price_cap'] ?? 0));
    if ($early_cap > 0) {
        update_post_meta($product_id, '_vms_ticketing_early_price_cap_v2', $early_cap);
    } else {
        delete_post_meta($product_id, '_vms_ticketing_early_price_cap_v2');
    }
    if (!empty($payload['has_sale'])) {
        update_post_meta($product_id, '_vms_ticketing_early_price_v2', $payload['sale_price']);
        update_post_meta($product_id, '_vms_ticketing_early_price_start_v2', sanitize_text_field((string) ($tier['early_price_start'] ?? '')));
        update_post_meta($product_id, '_vms_ticketing_early_price_end_v2', sanitize_text_field((string) ($tier['early_price_end'] ?? '')));
    } else {
        delete_post_meta($product_id, '_vms_ticketing_early_price_v2');
        delete_post_meta($product_id, '_vms_ticketing_early_price_start_v2');
        delete_post_meta($product_id, '_vms_ticketing_early_price_end_v2');
    }

    if (function_exists('wc_delete_product_transients')) {
        wc_delete_product_transients($product_id);
    }

    return $payload;
}


function bvmgr_ticketing_v2_get_ticket_config_for_product_price(int $product_id): array {
    $product_id = absint($product_id);
    if ($product_id <= 0) {
        return array();
    }

    static $cache = array();
    if (isset($cache[$product_id])) {
        return is_array($cache[$product_id]) ? $cache[$product_id] : array();
    }

    $role = sanitize_key((string) get_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('product_role'), true));
    if ($role !== 'ga_ticket') {
        $cache[$product_id] = array();
        return array();
    }

    $plan_id = absint(get_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('event_plan_id'), true));
    $ticket_key = sanitize_key((string) get_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_ticket_key'), true));
    if ($ticket_key === '') {
        $ticket_key = sanitize_key((string) get_post_meta($product_id, '_vms_ticket_key', true));
    }
    if ($plan_id <= 0 || $ticket_key === '') {
        $cache[$product_id] = array();
        return array();
    }

    $cfg = function_exists('bvmgr_ticketing_v2_get_saved_config') ? bvmgr_ticketing_v2_get_saved_config($plan_id) : array();
    $tickets = is_array($cfg['tickets'] ?? null) ? $cfg['tickets'] : array();
    foreach ($tickets as $ticket_row) {
        if (!is_array($ticket_row)) {
            continue;
        }
        $candidate_key = sanitize_key((string) ($ticket_row['ticket_key'] ?? ''));
        if ($candidate_key === $ticket_key) {
            $ticket_row['_vms_runtime_product_id'] = $product_id;
            $ticket_row['woo_product_id'] = $product_id;
            $cache[$product_id] = $ticket_row;
            return $ticket_row;
        }
    }

    $cache[$product_id] = array();
    return array();
}

function bvmgr_ticketing_v2_runtime_product_price_filter($price, $product) {
    if (!is_object($product) || !is_callable(array($product, 'get_id'))) {
        return $price;
    }

    $ticket = bvmgr_ticketing_v2_get_ticket_config_for_product_price((int) $product->get_id());
    if (empty($ticket)) {
        return $price;
    }

    return (string) bvmgr_ticketing_v2_get_ticket_effective_price($ticket);
}

function bvmgr_ticketing_v2_runtime_product_regular_price_filter($price, $product) {
    if (!is_object($product) || !is_callable(array($product, 'get_id'))) {
        return $price;
    }

    $ticket = bvmgr_ticketing_v2_get_ticket_config_for_product_price((int) $product->get_id());
    if (empty($ticket) || !isset($ticket['price']) || !is_numeric($ticket['price'])) {
        return $price;
    }

    return bvmgr_ticketing_v2_money_string($ticket['price']);
}

function bvmgr_ticketing_v2_runtime_product_sale_price_filter($price, $product) {
    if (!is_object($product) || !is_callable(array($product, 'get_id'))) {
        return $price;
    }

    $ticket = bvmgr_ticketing_v2_get_ticket_config_for_product_price((int) $product->get_id());
    if (empty($ticket)) {
        return $price;
    }

    $regular_price = max(0.0, (float) ($ticket['price'] ?? 0));
    $early_price = max(0.0, (float) ($ticket['early_price'] ?? 0));
    $effective = bvmgr_ticketing_v2_get_ticket_effective_price($ticket);
    if ($effective > 0 && $early_price > 0 && abs($effective - $early_price) < 0.00001 && $early_price < $regular_price) {
        return bvmgr_ticketing_v2_money_string($ticket['early_price'] ?? '', '');
    }

    return '';
}

function bvmgr_ticketing_v2_runtime_product_is_on_sale_filter($is_on_sale, $product): bool {
    if (!is_object($product) || !is_callable(array($product, 'get_id'))) {
        return (bool) $is_on_sale;
    }

    $ticket = bvmgr_ticketing_v2_get_ticket_config_for_product_price((int) $product->get_id());
    if (empty($ticket)) {
        return (bool) $is_on_sale;
    }

    $regular_price = max(0.0, (float) ($ticket['price'] ?? 0));
    $early_price = max(0.0, (float) ($ticket['early_price'] ?? 0));
    $effective = bvmgr_ticketing_v2_get_ticket_effective_price($ticket);
    return ($effective > 0 && $early_price > 0 && abs($effective - $early_price) < 0.00001 && $early_price < $regular_price);
}

add_filter('woocommerce_product_get_price', 'bvmgr_ticketing_v2_runtime_product_price_filter', 20, 2);
add_filter('woocommerce_product_get_regular_price', 'bvmgr_ticketing_v2_runtime_product_regular_price_filter', 20, 2);
add_filter('woocommerce_product_get_sale_price', 'bvmgr_ticketing_v2_runtime_product_sale_price_filter', 20, 2);
add_filter('woocommerce_product_is_on_sale', 'bvmgr_ticketing_v2_runtime_product_is_on_sale_filter', 20, 2);

function bvmgr_ticketing_b_get_event_ticket_products(int $tec_event_id): array {
    $tec_event_id = absint($tec_event_id);
    if ($tec_event_id <= 0) {
        return array();
    }
    if (function_exists('bvmgr_get_ticket_product_ids_for_event')) {
        $ids = bvmgr_get_ticket_product_ids_for_event($tec_event_id);
        $ids = is_array($ids) ? $ids : array();
        $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        return $ids;
    }
    return array();
}


function bvmgr_ticketing_v2_format_event_date_for_product_title(int $tec_event_id): string {
    $tec_event_id = absint($tec_event_id);
    if ($tec_event_id <= 0) {
        return '';
    }

    // Prefer TEC helpers when available.
    if (function_exists('tribe_get_start_date')) {
        $when = (string) tribe_get_start_date($tec_event_id, false, 'M j, Y');
        $when = trim($when);
        if ($when !== '') {
            return $when;
        }
    }

    $raw = (string) get_post_meta($tec_event_id, '_EventStartDate', true);
    if ($raw === '') {
        return '';
    }

    $ts = strtotime($raw);
    if (!$ts) {
        return '';
    }

    return (string) wp_date('M j, Y', $ts, wp_timezone());
}

function bvmgr_ticketing_v2_format_event_datetime_for_product_title(int $tec_event_id): string {
    $tec_event_id = absint($tec_event_id);
    if ($tec_event_id <= 0) {
        return '';
    }

    if (function_exists('tribe_get_start_date')) {
        $when = (string) tribe_get_start_date($tec_event_id, true, 'Y-m-d H:i');
        $when = trim($when);
        if ($when !== '') {
            return $when;
        }
    }

    $raw = (string) get_post_meta($tec_event_id, '_EventStartDate', true);
    if ($raw === '') {
        return '';
    }

    $ts = strtotime($raw);
    if (!$ts) {
        return '';
    }

    return (string) wp_date('Y-m-d H:i', $ts, wp_timezone());
}


function bvmgr_ticketing_v2_decode_plain_text_entities($value): string {
    if (!is_scalar($value) && $value !== null) {
        return '';
    }

    $text = (string) $value;
    for ($i = 0; $i < 3; $i++) {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($decoded === $text) {
            break;
        }
        $text = $decoded;
    }

    return $text;
}

function bvmgr_ticketing_v2_sanitize_plain_text_label($value): string {
    $text = bvmgr_ticketing_v2_decode_plain_text_entities($value);
    if ($text === '') {
        return '';
    }

    // WordPress text sanitization is intentionally conservative and converts a
    // customer/operator-entered comparison like "(<12yo)" to "(&lt;12yo)".
    // Preserve less-than signs that are not beginning an HTML tag, while still
    // allowing sanitize_text_field() to remove real tags such as <script>.
    $hash = substr(sha1($text), 0, 12);
    $lt_token = '__VMS_PLAIN_TEXT_LT_' . $hash . '__';
    $text = preg_replace('/<(?!\/?[A-Za-z])/u', $lt_token, $text);
    if (!is_string($text)) {
        $text = '';
    }

    $text = sanitize_text_field($text);
    $text = str_replace($lt_token, '<', $text);

    return trim($text);
}


/**
 * Legacy single-GA sync maps predate the multi-ticket config shape.  Earlier
 * builds assigned that legacy map to the first ticket row, which is unsafe when
 * a new template places "Early General Admission" before the real GA row.
 */
function bvmgr_ticketing_v2_should_apply_legacy_ga_map_to_ticket(string $ticket_key, string $ticket_label): bool {
    $ticket_key = sanitize_key($ticket_key);
    $label = strtolower(bvmgr_ticketing_v2_normalize_admin_ticket_title_for_match($ticket_label));
    $label = trim((string) preg_replace('/\s+/u', ' ', $label));

    // Never let the legacy GA map silently attach to a specialized/new ticket
    // just because that row happens to carry a reused key or appear first in a
    // newly applied template. Label intent must be checked before broad key
    // fallbacks such as "ga".
    if ($label !== '' && preg_match('/\b(early|advance|pre[-\s]?sale|presale|vip|child|children|kid|kids|veteran|police|fire|emt|nurse|teacher|school)\b/u', $label)) {
        return false;
    }

    if ($ticket_key === 'ga' || $ticket_key === 'general_admission' || $ticket_key === 'general-admission') {
        return true;
    }

    if ($label === 'general admission' || $label === 'ga admission' || $label === 'general admission ticket') {
        return true;
    }

    return false;
}

function bvmgr_ticketing_v2_detect_ticket_product_action_conflicts(array $actions): array {
    $by_pid = array();

    foreach ($actions as $action) {
        if (!is_array($action)) {
            continue;
        }
        $scope = sanitize_key((string) ($action['scope'] ?? ''));
        if ($scope !== 'ticket' && $scope !== 'ga') {
            continue;
        }
        $op = sanitize_key((string) ($action['action'] ?? $action['operation'] ?? ''));
        if (!in_array($op, array('update', 'adopt', 'disable'), true)) {
            continue;
        }
        $pid = absint($action['woo_product_id'] ?? 0);
        if ($pid <= 0) {
            continue;
        }
        if (!isset($by_pid[$pid])) {
            $by_pid[$pid] = array();
        }
        $by_pid[$pid][] = array(
            'ticket_key' => sanitize_key((string) ($action['ticket_key'] ?? '')),
            'label' => bvmgr_ticketing_v2_sanitize_plain_text_label((string) ($action['label'] ?? 'Ticket')),
            'action' => $op,
        );
    }

    $conflicts = array();
    foreach ($by_pid as $pid => $rows) {
        if (count($rows) < 2) {
            continue;
        }
        $labels = array();
        foreach ($rows as $row) {
            $label = trim((string) ($row['label'] ?? 'Ticket'));
            $op = sanitize_key((string) ($row['action'] ?? ''));
            $labels[] = $label !== '' ? ($label . ' [' . $op . ']') : ('Ticket [' . $op . ']');
        }
        $conflicts[] = array(
            'woo_product_id' => absint($pid),
            'items' => $rows,
            'message' => 'Ticket product #' . absint($pid) . ' is claimed by more than one ticket row: ' . implode(', ', array_values(array_unique($labels))) . '. Commit is blocked so one row cannot hide or overwrite the other.',
        );
    }

    return $conflicts;
}


function bvmgr_ticketing_v2_ticket_product_is_safe_to_retire_from_config(int $product_id, int $plan_id, int $tec_event_id, array $stale_mapped_product_ids = array()): bool {
    $product_id = absint($product_id);
    $plan_id = absint($plan_id);
    $tec_event_id = absint($tec_event_id);
    if ($product_id <= 0 || $plan_id <= 0 || $tec_event_id <= 0) {
        return false;
    }

    if (get_post_type($product_id) !== 'product' || (string) get_post_status($product_id) === 'trash') {
        return false;
    }

    $linked_event_id = absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true));
    if ($linked_event_id !== $tec_event_id) {
        return false;
    }

    $stale_mapped_product_ids = array_values(array_unique(array_filter(array_map('absint', $stale_mapped_product_ids))));
    if (in_array($product_id, $stale_mapped_product_ids, true)) {
        return true;
    }

    $source_plan = absint(get_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_source_plan_id'), true));
    if ($source_plan === $plan_id) {
        return true;
    }

    $product_plan = absint(get_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('event_plan_id'), true));
    if ($product_plan === $plan_id) {
        return true;
    }

    $marker_version = trim((string) get_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_marker_version'), true));
    $role = sanitize_key((string) get_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('product_role'), true));
    $ticket_key = sanitize_key((string) get_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_ticket_key'), true));
    if ($marker_version !== '' && $role === 'ga_ticket' && $ticket_key !== '') {
        return true;
    }

    return false;
}

function bvmgr_ticketing_v2_retire_ticket_product_from_config(int $product_id, int $plan_id, int $tec_event_id, string $reason = 'removed_from_current_config'): array {
    $product_id = absint($product_id);
    $plan_id = absint($plan_id);
    $tec_event_id = absint($tec_event_id);

    if ($product_id <= 0 || get_post_type($product_id) !== 'product' || (string) get_post_status($product_id) === 'trash') {
        return array('ok' => false, 'message' => 'invalid_product_for_retire');
    }

    $linked_event_id = absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true));
    if ($tec_event_id > 0 && $linked_event_id !== $tec_event_id) {
        return array('ok' => false, 'message' => 'retire_linkage_mismatch');
    }

    $did = false;
    if (function_exists('wc_get_product')) {
        $product = wc_get_product($product_id);
        if ($product) {
            if (method_exists($product, 'set_status')) {
                $product->set_status('draft');
            }
            if (method_exists($product, 'set_catalog_visibility')) {
                $product->set_catalog_visibility('hidden');
            }
            $product->save();
            $did = true;
        }
    }

    if (!$did) {
        wp_update_post(array(
            'ID' => $product_id,
            'post_status' => 'draft',
        ));
        update_post_meta($product_id, '_visibility', 'hidden');
    }

    update_post_meta($product_id, '_vms_ticketing_retired_from_current_config', 1);
    update_post_meta($product_id, '_vms_ticketing_retired_from_current_config_at', time());
    update_post_meta($product_id, '_vms_ticketing_retired_from_current_config_by', get_current_user_id());
    update_post_meta($product_id, '_vms_ticketing_retired_from_current_config_reason', sanitize_key($reason));
    if ($plan_id > 0) {
        update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('event_plan_id'), $plan_id);
        update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_source_plan_id'), $plan_id);
    }
    if ($tec_event_id > 0) {
        update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('tec_event_id'), $tec_event_id);
    }

    if (function_exists('clean_post_cache')) {
        clean_post_cache($product_id);
    }

    return array('ok' => true, 'message' => 'retired');
}

function bvmgr_ticketing_v2_normalize_admin_ticket_title_for_match(string $title): string {
    $title = trim(html_entity_decode(wp_strip_all_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($title === '') {
        return '';
    }

    $title = preg_replace('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}\s+-\s+/u', '', $title);
    $title = preg_replace('/\s+[—-]\s+.+\([A-Z][a-z]{2}\s+\d{1,2},\s+\d{4}\)$/u', '', (string) $title);

    return trim((string) $title);
}

function bvmgr_ticketing_v2_compose_product_admin_title(string $base_label, $tec_event_id = 0): string {
    $base_label = bvmgr_ticketing_v2_sanitize_plain_text_label($base_label);
    $tec_event_id = absint(is_scalar($tec_event_id) ? $tec_event_id : 0);

    if ($base_label === '' || $tec_event_id <= 0) {
        return $base_label;
    }

    $title_mode = (string) apply_filters('vms_ticketing_v2_product_admin_title_mode', 'prefixed_datetime', $base_label, $tec_event_id);
    $title_mode = $title_mode !== '' ? sanitize_key($title_mode) : 'prefixed_datetime';

    if ($title_mode === 'clean') {
        return $base_label;
    }

    if ($title_mode === 'legacy_suffix') {
        $event_title = trim((string) get_the_title($tec_event_id));
        $event_date  = bvmgr_ticketing_v2_format_event_date_for_product_title($tec_event_id);

        if ($event_title === '' || $event_date === '') {
            return $base_label;
        }

        $suffix = ' — ' . $event_title . ' (' . $event_date . ')';
        $plain = html_entity_decode(wp_strip_all_tags($base_label), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (strpos($plain, $suffix) !== false) {
            return $base_label;
        }

        return $base_label . $suffix;
    }

    $event_when = bvmgr_ticketing_v2_format_event_datetime_for_product_title($tec_event_id);
    if ($event_when === '') {
        return $base_label;
    }

    $plain = html_entity_decode(wp_strip_all_tags($base_label), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (strpos($plain, $event_when . ' - ') === 0) {
        return $base_label;
    }

    return $event_when . ' - ' . $base_label;
}

function bvmgr_ticketing_v2_find_ticket_title_match(array $product_ids, string $tier_name, array $args = array()): array {
    $tier_name = bvmgr_ticketing_v2_normalize_admin_ticket_title_for_match($tier_name);
    if ($tier_name === '') {
        return array('status' => 'none', 'product_id' => 0, 'candidates' => array(), 'message' => 'empty_title');
    }

    $tier_l = strtolower($tier_name);
    $ticket_key = sanitize_key((string) ($args['ticket_key'] ?? ''));
    $plan_id = absint($args['plan_id'] ?? 0);
    $tec_event_id = absint($args['tec_event_id'] ?? 0);

    $matches = array();
    foreach ($product_ids as $pid) {
        $pid = absint($pid);
        if ($pid <= 0 || get_post_type($pid) !== 'product' || (string) get_post_status($pid) === 'trash') {
            continue;
        }

        $title = (string) get_the_title($pid);
        $title_t = bvmgr_ticketing_v2_normalize_admin_ticket_title_for_match($title);
        if ($title_t === '' || strtolower($title_t) !== $tier_l) {
            continue;
        }

        $role = sanitize_key((string) get_post_meta($pid, bvmgr_ticketing_v2_product_meta_key('product_role'), true));
        if ($role === 'addon' || $role === 'entitlement') {
            continue;
        }

        $linked_event_id = absint(get_post_meta($pid, '_tribe_wooticket_for_event', true));
        $plan_marker = absint(get_post_meta($pid, bvmgr_ticketing_v2_product_meta_key('event_plan_id'), true));
        $ticket_key_meta = sanitize_key((string) get_post_meta($pid, bvmgr_ticketing_v2_product_meta_key('ticketing_ticket_key'), true));
        $retired = ((string) get_post_meta($pid, '_vms_legacy_retired', true) === '1');
        $sold_qty = function_exists('bvmgr_ticket_integrity_authoritative_product_sales_count')
            ? bvmgr_ticket_integrity_authoritative_product_sales_count($pid)
            : max(0, absint(get_post_meta($pid, 'total_sales', true)));
        $catalog_visibility = function_exists('bvmgr_ticketing_v2_get_product_catalog_visibility_state')
            ? (string) bvmgr_ticketing_v2_get_product_catalog_visibility_state($pid)
            : '';
        $is_public = ((string) get_post_status($pid) === 'publish' && $catalog_visibility !== 'hidden');

        $score = 0;
        if (!$retired) {
            $score += 100;
        }
        if ($sold_qty > 0) {
            $score += 150;
        }
        if ($is_public) {
            $score += 20;
        }
        if ($tec_event_id > 0 && $linked_event_id === $tec_event_id) {
            $score += 15;
        }
        if ($plan_id > 0 && $plan_marker === $plan_id) {
            $score += 10;
        }
        if ($ticket_key !== '' && $ticket_key_meta === $ticket_key) {
            $score += 10;
        }

        $matches[] = array(
            'product_id' => $pid,
            'score' => $score,
            'sold_qty' => $sold_qty,
            'retired' => $retired,
            'is_public' => $is_public,
            'linked_event_id' => $linked_event_id,
            'plan_marker' => $plan_marker,
            'ticket_key' => $ticket_key_meta,
        );
    }

    if (empty($matches)) {
        return array('status' => 'none', 'product_id' => 0, 'candidates' => array(), 'message' => 'no_title_match');
    }

    usort($matches, static function (array $a, array $b): int {
        if (($a['score'] ?? 0) !== ($b['score'] ?? 0)) {
            return (($b['score'] ?? 0) <=> ($a['score'] ?? 0));
        }
        if (($a['sold_qty'] ?? 0) !== ($b['sold_qty'] ?? 0)) {
            return (($b['sold_qty'] ?? 0) <=> ($a['sold_qty'] ?? 0));
        }
        return (($a['product_id'] ?? 0) <=> ($b['product_id'] ?? 0));
    });

    $top = $matches[0];
    $top_score = (int) ($top['score'] ?? 0);
    $top_sold = (int) ($top['sold_qty'] ?? 0);
    $competing = array_values(array_filter($matches, static function (array $candidate) use ($top_score, $top_sold): bool {
        return (int) ($candidate['score'] ?? 0) === $top_score && (int) ($candidate['sold_qty'] ?? 0) === $top_sold;
    }));

    if (count($competing) > 1) {
        return array(
            'status' => 'ambiguous',
            'product_id' => 0,
            'candidates' => array_values(array_map(static function (array $candidate): int {
                return absint($candidate['product_id'] ?? 0);
            }, $matches)),
            'message' => 'multiple_exact_title_matches',
        );
    }

    return array(
        'status' => 'found',
        'product_id' => absint($top['product_id'] ?? 0),
        'candidates' => array_values(array_map(static function (array $candidate): int {
            return absint($candidate['product_id'] ?? 0);
        }, $matches)),
        'message' => (!empty($top['retired']) ? 'retired_fallback_match' : (($top_sold > 0) ? 'preferred_sold_match' : 'exact_title_match')),
    );
}

function bvmgr_ticketing_b_find_match_by_title(array $product_ids, string $tier_name, array $args = array()): int {
    $match = bvmgr_ticketing_v2_find_ticket_title_match($product_ids, $tier_name, $args);
    return (($match['status'] ?? '') === 'found') ? absint($match['product_id'] ?? 0) : 0;
}

function bvmgr_ticketing_b_preview_sync(int $plan_id): array {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return array('ok' => false, 'message' => 'invalid_plan');
    }

    $tec_event_id = bvmgr_ticketing_b_get_linked_tec_event_id($plan_id);
    if ($tec_event_id <= 0) {
        return array('ok' => false, 'message' => 'missing_tec_link');
    }

    if (!bvmgr_ticketing_b_is_event_tickets_woo_available()) {
        return array('ok' => false, 'message' => 'event_tickets_woo_unavailable');
    }

    $tiers_raw = bvmgr_ticketing_b_get_tiers($plan_id);
    $tiers = array();
    foreach ($tiers_raw as $t) {
        if (!is_array($t)) {
            continue;
        }
        $tn = bvmgr_ticketing_b_normalize_tier($t);
        // Skip empty-name tiers.
        if (trim((string) $tn['name']) === '') {
            continue;
        }
        $tiers[] = $tn;
    }

    $map = bvmgr_ticketing_b_get_map($plan_id);
    $event_products = bvmgr_ticketing_b_get_event_ticket_products($tec_event_id);

    $items = array();
    foreach ($tiers as $tier) {
        $key = (string) $tier['tier_key'];
        $hash = bvmgr_ticketing_b_tier_hash($tier);
        $m = isset($map[$key]) && is_array($map[$key]) ? $map[$key] : array();

        $known_pid = isset($m['woo_product_id']) ? absint($m['woo_product_id']) : 0;
        $known_ok = ($known_pid > 0 && get_post_type($known_pid) === 'product');
        if ($known_ok) {
            $linked = (int) get_post_meta($known_pid, '_tribe_wooticket_for_event', true);
            if ($linked !== $tec_event_id) {
                $known_ok = false;
            }
        }

        if ($known_ok) {
            $prev_hash = isset($m['last_sync_hash']) ? (string) $m['last_sync_hash'] : '';
            if ($prev_hash !== '' && hash_equals($prev_hash, $hash)) {
                $items[] = array(
                    'tier_key' => $key,
                    'name' => (string) $tier['name'],
                    'action' => 'skip',
                    'reason' => 'No changes since last sync.',
                    'woo_product_id' => $known_pid,
                );
            } else {
                $items[] = array(
                    'tier_key' => $key,
                    'name' => (string) $tier['name'],
                    'action' => 'update',
                    'reason' => 'Tier changed since last sync.',
                    'woo_product_id' => $known_pid,
                );
            }
            continue;
        }

        // No mapping: attempt adopt by exact title match.
        $match = bvmgr_ticketing_v2_find_ticket_title_match($event_products, (string) $tier['name'], array(
            'plan_id' => $plan_id,
            'tec_event_id' => $tec_event_id,
            'ticket_key' => $key,
        ));
        if (($match['status'] ?? '') === 'found') {
            $matched_pid = absint($match['product_id'] ?? 0);
            $reason = 'Matched an existing TEC ticket by exact name.';
            if (($match['message'] ?? '') === 'preferred_sold_match') {
                $reason = 'Matched the sold exact-name ticket instead of creating a new duplicate path.';
            }
            $items[] = array(
                'tier_key' => $key,
                'name' => (string) $tier['name'],
                'action' => 'adopt',
                'reason' => $reason,
                'woo_product_id' => $matched_pid,
            );
            continue;
        }

        if (($match['status'] ?? '') === 'ambiguous') {
            $items[] = array(
                'tier_key' => $key,
                'name' => (string) $tier['name'],
                'action' => 'skip',
                'reason' => 'Multiple exact-name tickets already exist. Resolve duplicates before creating another ticket path.',
                'woo_product_id' => 0,
            );
            continue;
        }

        $items[] = array(
            'tier_key' => $key,
            'name' => (string) $tier['name'],
            'action' => 'create',
            'reason' => 'No existing ticket mapping found.',
            'woo_product_id' => 0,
        );
    }

    return array(
        'ok' => true,
        'tec_event_id' => $tec_event_id,
        'items' => $items,
        'tier_count' => count($tiers),
    );
}

function bvmgr_ticketing_b_normalize_sort_order($sort_order, int $fallback = 0): int {
    $sort_order = is_numeric($sort_order) ? (int) $sort_order : 0;
    if ($sort_order <= 0) {
        $sort_order = max(0, $fallback);
    }
    return max(0, $sort_order);
}

function bvmgr_ticketing_b_apply_product_sort_order(int $product_id, $sort_order, int $fallback = 0, string $scope = 'ticket'): array {
    $product_id = absint($product_id);
    if ($product_id <= 0) {
        return array('ok' => false, 'message' => 'invalid_product_id');
    }
    if (get_post_type($product_id) !== 'product') {
        return array('ok' => false, 'message' => 'not_a_product');
    }

    $menu_order = bvmgr_ticketing_b_normalize_sort_order($sort_order, $fallback);
    $scope = sanitize_key($scope);
    if ($scope === '') {
        $scope = 'ticket';
    }

    $current_menu_order = (int) get_post_field('menu_order', $product_id);
    if ($current_menu_order !== $menu_order) {
        wp_update_post(array(
            'ID' => $product_id,
            'menu_order' => $menu_order,
        ));
    }

    update_post_meta($product_id, '_vms_ticketing_sort_order_v2', $menu_order);
    update_post_meta($product_id, '_vms_ticketing_sort_scope_v2', $scope);

    return array(
        'ok' => true,
        'woo_product_id' => $product_id,
        'menu_order' => $menu_order,
    );
}

function bvmgr_ticketing_b_apply_update_to_product(int $product_id, array $tier, int $tec_event_id): array {
    $product_id = absint($product_id);
    $tec_event_id = absint($tec_event_id);
    if ($product_id <= 0 || $tec_event_id <= 0) {
        return array('ok' => false, 'message' => 'invalid_ids');
    }

    // Ensure a deterministic SKU so Woo product lists are distinguishable.
    $sku_suffix = sanitize_key((string) ($tier['tier_key'] ?? 'ga'));
    if ($sku_suffix === '') {
        $sku_suffix = 'ga';
    }
    $sku = 'VMS-TEC' . $tec_event_id . '-' . strtoupper($sku_suffix);
    $existing_sku = trim((string) get_post_meta($product_id, '_sku', true));
    if ($existing_sku === '' || stripos($existing_sku, 'SR-') === 0) {
        update_post_meta($product_id, '_sku', $sku);
    }

    if (get_post_type($product_id) !== 'product') {
        return array('ok' => false, 'message' => 'not_a_product');
    }

    // Ensure the Event Tickets linkage meta is correct.
    update_post_meta($product_id, '_tribe_wooticket_for_event', $tec_event_id);
    // Title.
    $base_title = (string) ($tier['name'] ?? '');
    $new_title = bvmgr_ticketing_v2_compose_product_admin_title($base_title, $tec_event_id);
    if ($new_title !== '') {
        wp_update_post(array(
            'ID' => $product_id,
            'post_title' => $new_title,
        ));
    }

    // Price. The stored ticket price is the regular price. Optional early
    // pricing is synced as a Woo scheduled sale on the same ticket product.
    bvmgr_ticketing_v2_apply_price_payload_to_product($product_id, $tier);

    // Capacity / stock.
    // IMPORTANT: stock is remaining units, not total capacity.
    // When operators change ticket capacity after sales, we must not reset stock back to the capacity.
    $cap = $tier['capacity'] ?? null;
    $result_meta = array(
        'derivation_source' => 'authoritative_config',
        'confidence_level' => 'authoritative',
        'expected_effect' => 'preserve',
        'reason_text' => __('Ticket inventory was updated from the authoritative ticket configuration.', 'backstage-venue-manager'),
        'writer_branch' => 'ticket_inventory_update',
        'result_health' => 'manual_review',
        'result_health_label' => bvmgr_ticketing_v2_inventory_result_health_label('manual_review'),
        'used_fallback' => 0,
        'final_stock_qty' => null,
        'final_stock_status' => '',
        'final_manage_stock' => 0,
    );
    if (is_int($cap) && $cap >= 0) {
        $sold_res = function_exists('bvmgr_ticketing_v2_calc_sold_qty_for_product')
            ? bvmgr_ticketing_v2_calc_sold_qty_for_product($product_id)
            : array('ok' => false, 'sold_qty' => 0, 'message' => 'sold_qty_helper_missing');

        if (!empty($sold_res['ok'])) {
            $sold_qty = max(0, absint($sold_res['sold_qty'] ?? 0));
            $remaining = max(0, $cap - $sold_qty);
            $reason_text = sprintf(
                /* translators: 1: capacity, 2: sold quantity, 3: remaining quantity */
                __('Ticket stock was recalculated from capacity %1$d minus sold quantity %2$d, leaving %3$d remaining.', 'backstage-venue-manager'),
                $cap,
                $sold_qty,
                $remaining
            );
            if (!empty($sold_res['ignored_total_sales'])) {
                $reason_text .= ' ' . sprintf(
                    /* translators: %d: Woo total_sales value */
                    __('Woo total_sales reported %d for this product, but rebuild ignored that stale lifetime counter and trusted the paid-order scan instead.', 'backstage-venue-manager'),
                    max(0, absint($sold_res['meta_total_sales'] ?? 0))
                );
            }
            bvmgr_ticketing_v2_push_inventory_write_context(array(
                'source_function' => 'vms_ticketing_b_apply_update_to_product',
                'derivation_source' => 'ticket_sold_count_reconciliation',
                'confidence_level' => 'authoritative',
                'expected_effect' => ($remaining > 0) ? 'reopen' : 'close',
                'reason_text' => $reason_text,
                'writer_branch' => 'ticket_sold_count_reconciliation',
                'result_health' => ($remaining > 0) ? 'expected_sellable_state' : 'expected_closed_state',
            ));
            try {
                update_post_meta($product_id, '_manage_stock', 'yes');
                update_post_meta($product_id, '_backorders', 'no');
                update_post_meta($product_id, '_tribe_ticket_capacity', $cap);
                update_post_meta($product_id, '_global_stock_mode', 'own');
                update_post_meta($product_id, '_stock', $remaining);
                update_post_meta($product_id, '_stock_status', ($remaining > 0) ? 'instock' : 'outofstock');

                // Diagnostics (safe for operators; not used as truth source).
                update_post_meta($product_id, '_vms_ticketing_capacity_v2', $cap);
                update_post_meta($product_id, '_vms_ticketing_sold_qty_v2', $sold_qty);
                update_post_meta($product_id, '_vms_ticketing_remaining_v2', $remaining);
                update_post_meta($product_id, '_vms_ticketing_stock_reconciled_at_gmt', time());
                delete_post_meta($product_id, '_vms_ticketing_stock_reconcile_error');
            } finally {
                bvmgr_ticketing_v2_pop_inventory_write_context();
            }

            $result_meta = array_merge(
                $result_meta,
                array(
                    'derivation_source' => 'ticket_sold_count_reconciliation',
                    'confidence_level' => 'authoritative',
                    'expected_effect' => ($remaining > 0) ? 'reopen' : 'close',
                    'reason_text' => $reason_text,
                    'writer_branch' => 'ticket_sold_count_reconciliation',
                ),
                bvmgr_ticketing_v2_classify_inventory_result(
                    'ticket_sold_count_reconciliation',
                    $cap,
                    true,
                    $remaining,
                    array(
                        'stock_qty' => $remaining,
                        'stock_status' => ($remaining > 0) ? 'instock' : 'outofstock',
                        'manage_stock' => true,
                    ),
                    ($cap > 0 && $remaining > 0)
                )
            );
        } else {
            // We could not compute sold qty safely.
            // Do NOT touch _stock, so we don't accidentally expand inventory.
            // Still set basic constraints (capacity + no backorders) so Woo won't oversell.
            if ($cap <= 0) {
                $reason_text = __('Ticket stock was set to 0 because the authoritative configured capacity is 0.', 'backstage-venue-manager');
                bvmgr_ticketing_v2_push_inventory_write_context(array(
                    'source_function' => 'vms_ticketing_b_apply_update_to_product',
                    'derivation_source' => 'authoritative_zero_capacity',
                    'confidence_level' => 'authoritative',
                    'expected_effect' => 'close',
                    'reason_text' => $reason_text,
                    'writer_branch' => 'ticket_zero_capacity_branch',
                    'result_health' => 'expected_closed_state',
                ));
                try {
                    update_post_meta($product_id, '_manage_stock', 'yes');
                    update_post_meta($product_id, '_backorders', 'no');
                    update_post_meta($product_id, '_tribe_ticket_capacity', $cap);
                    update_post_meta($product_id, '_global_stock_mode', 'own');
                    update_post_meta($product_id, '_stock', 0);
                    update_post_meta($product_id, '_stock_status', 'outofstock');
                    update_post_meta($product_id, '_vms_ticketing_capacity_v2', $cap);
                    update_post_meta($product_id, '_vms_ticketing_stock_reconciled_at_gmt', time());
                    update_post_meta($product_id, '_vms_ticketing_stock_reconcile_error', sanitize_text_field((string) ($sold_res['message'] ?? 'sold_qty_unavailable')));
                } finally {
                    bvmgr_ticketing_v2_pop_inventory_write_context();
                }

                $result_meta = array_merge(
                    $result_meta,
                    array(
                        'derivation_source' => 'authoritative_zero_capacity',
                        'confidence_level' => 'authoritative',
                        'expected_effect' => 'close',
                        'reason_text' => $reason_text,
                        'writer_branch' => 'ticket_zero_capacity_branch',
                    ),
                    bvmgr_ticketing_v2_classify_inventory_result(
                        'ticket_zero_capacity_branch',
                        $cap,
                        false,
                        0,
                        array(
                            'stock_qty' => 0,
                            'stock_status' => 'outofstock',
                            'manage_stock' => true,
                        ),
                        false
                    )
                );
            } else {
                $existing_stock = absint(get_post_meta($product_id, '_stock', true));
                $reason_text = sprintf(
                    /* translators: 1: existing stock quantity */
                    __('Sold quantity could not be derived safely, so rebuild preserved the existing stock quantity of %1$d and only normalized constraints and stock status.', 'backstage-venue-manager'),
                    $existing_stock
                );
                bvmgr_ticketing_v2_push_inventory_write_context(array(
                    'source_function' => 'vms_ticketing_b_apply_update_to_product',
                    'derivation_source' => 'ticket_existing_state_fallback',
                    'confidence_level' => 'fallback',
                    'expected_effect' => ($existing_stock > 0) ? 'preserve' : 'close',
                    'reason_text' => $reason_text,
                    'writer_branch' => 'ticket_existing_state_fallback',
                    'result_health' => ($existing_stock > 0) ? 'fallback_state_applied' : 'fallback_closed_state',
                ));
                try {
                    update_post_meta($product_id, '_manage_stock', 'yes');
                    update_post_meta($product_id, '_backorders', 'no');
                    update_post_meta($product_id, '_tribe_ticket_capacity', $cap);
                    update_post_meta($product_id, '_global_stock_mode', 'own');
                    update_post_meta($product_id, '_stock_status', ($existing_stock > 0) ? 'instock' : 'outofstock');
                    update_post_meta($product_id, '_vms_ticketing_capacity_v2', $cap);
                    update_post_meta($product_id, '_vms_ticketing_stock_reconciled_at_gmt', time());
                    update_post_meta($product_id, '_vms_ticketing_stock_reconcile_error', sanitize_text_field((string) ($sold_res['message'] ?? 'sold_qty_unavailable')));
                } finally {
                    bvmgr_ticketing_v2_pop_inventory_write_context();
                }

                $result_meta = array_merge(
                    $result_meta,
                    array(
                        'derivation_source' => 'ticket_existing_state_fallback',
                        'confidence_level' => 'fallback',
                        'expected_effect' => ($existing_stock > 0) ? 'preserve' : 'close',
                        'reason_text' => $reason_text,
                        'writer_branch' => 'ticket_existing_state_fallback',
                    ),
                    bvmgr_ticketing_v2_classify_inventory_result(
                        'ticket_existing_state_fallback',
                        $cap,
                        false,
                        max(0, $existing_stock),
                        array(
                            'stock_qty' => $existing_stock,
                            'stock_status' => ($existing_stock > 0) ? 'instock' : 'outofstock',
                            'manage_stock' => true,
                        ),
                        true
                    )
                );
            }
        }
    } else {
        $reason_text = __('Ticket inventory is unlimited for this branch, so manage-stock was disabled and stock status was forced open.', 'backstage-venue-manager');
        bvmgr_ticketing_v2_push_inventory_write_context(array(
            'source_function' => 'vms_ticketing_b_apply_update_to_product',
            'derivation_source' => 'authoritative_config',
            'confidence_level' => 'authoritative',
            'expected_effect' => 'reopen',
            'reason_text' => $reason_text,
            'writer_branch' => 'ticket_unlimited_branch',
            'result_health' => 'expected_sellable_state',
        ));
        try {
            update_post_meta($product_id, '_manage_stock', 'no');
            update_post_meta($product_id, '_tribe_ticket_capacity', -1);
            update_post_meta($product_id, '_global_stock_mode', 'unlimited');
            // Leave _stock alone; Woo may not care when manage_stock=no.
            update_post_meta($product_id, '_stock_status', 'instock');
            delete_post_meta($product_id, '_vms_ticketing_stock_reconcile_error');
        } finally {
            bvmgr_ticketing_v2_pop_inventory_write_context();
        }

        $result_meta = array_merge(
            $result_meta,
            array(
                'derivation_source' => 'authoritative_config',
                'confidence_level' => 'authoritative',
                'expected_effect' => 'reopen',
                'reason_text' => $reason_text,
                'writer_branch' => 'ticket_unlimited_branch',
            ),
            bvmgr_ticketing_v2_classify_inventory_result(
                'ticket_unlimited_branch',
                -1,
                true,
                1,
                array(
                    'stock_qty' => null,
                    'stock_status' => 'instock',
                    'manage_stock' => false,
                ),
                true
            )
        );
    }

    // Start/end dates. Resolve relative date rules and clamp the sell-through
    // date so ticket products cannot remain on sale after the event ends.
    $resolved_window = bvmgr_ticketing_b_resolve_sales_window($tec_event_id, $tier);
    $start = isset($resolved_window['start']) ? trim((string) $resolved_window['start']) : '';
    $end   = isset($resolved_window['end']) ? trim((string) $resolved_window['end']) : '';
    if ($start !== '') {
        update_post_meta($product_id, '_ticket_start_date', $start);
    }
    if ($end !== '') {
        update_post_meta($product_id, '_ticket_end_date', $end);
    }

    // Visibility.
    $hidden = !empty($tier['is_hidden']);
    if ($hidden) {
        // WC "catalog visibility": hide from catalog. (Tickets are usually not browsed in catalog.)
        update_post_meta($product_id, '_visibility', 'hidden');
    } else {
        delete_post_meta($product_id, '_visibility');
    }

    $sort_apply = bvmgr_ticketing_b_apply_product_sort_order(
        $product_id,
        $tier['sort_order'] ?? 0,
        0,
        'ticket'
    );
    if (empty($sort_apply['ok'])) {
        return array('ok' => false, 'message' => (string) ($sort_apply['message'] ?? 'sort_order_apply_failed'));
    }

    // Verification gate: ensure the linkage meta matches.
    $linked = (int) get_post_meta($product_id, '_tribe_wooticket_for_event', true);
    if ($linked !== $tec_event_id) {
        return array('ok' => false, 'message' => 'linkage_meta_mismatch');
    }

    return array_merge(array('ok' => true, 'woo_product_id' => $product_id), $result_meta);
}

function bvmgr_ticketing_b_create_woo_ticket(int $tec_event_id, array $tier): array {
    $tec_event_id = absint($tec_event_id);
    if ($tec_event_id <= 0) {
        return array('ok' => false, 'message' => 'invalid_tec_event');
    }

    $title = (string) ($tier['name'] ?? '');
    $title = trim($title);
    if ($title === '') {
        return array('ok' => false, 'message' => 'missing_title');
    }

    $args = array(
        'title' => bvmgr_ticketing_v2_compose_product_admin_title($title, $tec_event_id),
        'status' => 'publish',
        '_tribe_wooticket_for_event' => $tec_event_id,
    );

    // Price. Create with the same regular/scheduled-sale structure used by
    // updates so a single public ticket can carry early/regular price phases.
    $price_payload = bvmgr_ticketing_v2_price_payload_for_ticket($tier);
    $args['_price'] = (0 + (string) $price_payload['effective_price']);
    $args['_regular_price'] = (0 + (string) $price_payload['regular_price']);
    $args['_sale_price'] = (string) $price_payload['sale_price'];
    $args['_sale_price_dates_from'] = $price_payload['sale_from'];
    $args['_sale_price_dates_to'] = $price_payload['sale_to'];

    // Capacity / stock.
    $cap = $tier['capacity'] ?? null;
    if (is_int($cap) && $cap >= 0) {
        $args['_manage_stock'] = 'yes';
        $args['_stock'] = $cap;
        $args['_stock_status'] = ($cap > 0) ? 'instock' : 'outofstock';
        $args['_backorders'] = 'no';
        $args['_tribe_ticket_capacity'] = $cap;
        $args['_global_stock_mode'] = 'own';
    } else {
        $args['_manage_stock'] = 'no';
        $args['_tribe_ticket_capacity'] = -1;
        $args['_global_stock_mode'] = 'unlimited';
        $args['_stock_status'] = 'instock';
    }

    // Dates.
    // Event Tickets (Woo) can be picky about date payloads. To keep Phase B
    // compatible and predictable, we resolve safe defaults when blank:
    // - start: now (site timezone)
    // - end: TEC event end (site timezone), else event start
    $resolved = bvmgr_ticketing_b_resolve_sales_window($tec_event_id, $tier);
    if (!empty($resolved['start'])) {
        $args['_ticket_start_date'] = $resolved['start'];
    }
    if (!empty($resolved['end'])) {
        $args['_ticket_end_date'] = $resolved['end'];
    }

    // SKU (optional but helpful; keep deterministic-ish per event+tier).
    $args['_sku'] = 'VMS-' . $tec_event_id . '-' . sanitize_key((string) ($tier['tier_key'] ?? 'tier'));

    // Product type.
    $args['product_type'] = 'simple';
    $args['_virtual'] = 'yes';
    $args['_downloadable'] = 'no';

    $created = tribe_tickets('woo')->set_args($args)->create();

    $ticket_id = 0;
    if (is_numeric($created)) {
        $ticket_id = absint($created);
    } elseif (is_object($created) && isset($created->ID)) {
        $ticket_id = absint($created->ID);
    }

    if ($ticket_id <= 0) {
        return array('ok' => false, 'message' => 'create_failed');
    }

    $sort_apply = bvmgr_ticketing_b_apply_product_sort_order(
        $ticket_id,
        $tier['sort_order'] ?? 0,
        0,
        'ticket'
    );
    if (empty($sort_apply['ok'])) {
        return array('ok' => false, 'message' => (string) ($sort_apply['message'] ?? 'sort_order_apply_failed'), 'ticket_id' => $ticket_id);
    }

    // Verification gate: ensure the linkage meta matches.
    $linked = (int) get_post_meta($ticket_id, '_tribe_wooticket_for_event', true);
    if ($linked !== $tec_event_id) {
        return array('ok' => false, 'message' => 'linkage_meta_mismatch', 'ticket_id' => $ticket_id);
    }

    return array('ok' => true, 'ticket_id' => $ticket_id, 'woo_product_id' => $ticket_id);
}

/**
 * Resolve a safe ticket sales window for Event Tickets (Woo).
 *
 * If the operator leaves dates blank, we apply sane defaults so ticket creation
 * does not fail with provider payload validation.
 */
function bvmgr_ticketing_b_resolve_sales_window(int $tec_event_id, array $tier): array {
    $tz = wp_timezone();
    $now = wp_date('Y-m-d H:i:s', time(), $tz);

    $tec_event_id = absint($tec_event_id);
    $event_start = bvmgr_ticketing_v2_normalize_sales_window_value(bvmgr_ticketing_b_get_tec_event_start($tec_event_id));
    $event_end = function_exists('bvmgr_ticketing_b_get_tec_event_end')
        ? bvmgr_ticketing_v2_normalize_sales_window_value(bvmgr_ticketing_b_get_tec_event_end($tec_event_id))
        : '';
    if ($event_end === '') {
        $event_end = $event_start;
    }

    $start = isset($tier['sales_start']) ? trim((string) $tier['sales_start']) : '';
    $end   = isset($tier['sales_end']) ? trim((string) $tier['sales_end']) : '';

    $sales_start_relative_days = bvmgr_ticketing_v2_normalize_relative_days($tier['sales_start_relative_days'] ?? '');
    if ($sales_start_relative_days !== '' && $event_start !== '') {
        $resolved = bvmgr_ticketing_v2_relative_days_before_datetime($event_start, $sales_start_relative_days);
        if ($resolved !== '') {
            $start = $resolved;
        }
    }

    $sales_end_relative_days = bvmgr_ticketing_v2_normalize_relative_days($tier['sales_end_relative_days'] ?? '');
    if ($sales_end_relative_days !== '' && ($event_end !== '' || $event_start !== '')) {
        $resolved = bvmgr_ticketing_v2_relative_days_before_datetime($event_end !== '' ? $event_end : $event_start, $sales_end_relative_days);
        if ($resolved !== '') {
            $end = $resolved;
        }
    }

    // If both blank, use defaults.
    if ($start === '' && $end === '') {
        $start = $now;
        if ($event_end !== '') {
            $end = $event_end;
        } elseif ($event_start !== '') {
            $end = $event_start;
        } else {
            $end = $now;
        }
    }

    // If one side is blank, try to fill from event end/start or now.
    if ($start === '' && $end !== '') {
        $start = $now;
    }
    if ($end === '' && $start !== '') {
        $end = $event_end !== '' ? $event_end : ($event_start !== '' ? $event_start : $start);
    }

    if ($end !== '' && $event_end !== '' && strtotime($end) > strtotime($event_end)) {
        $end = $event_end;
    }

    // Final sanity: ensure end is not earlier than start.
    // If it is, clamp start to end so the ticket cannot stay open past event end.
    if ($start !== '' && $end !== '' && strtotime($end) < strtotime($start)) {
        $start = $end;
    }

    return array('start' => $start, 'end' => $end);
}

/**
 * Compare the Event Plan occurrence with its linked TEC event.
 *
 * Ticket sale windows are clamped to the linked TEC event. A native ticket
 * preview/commit must therefore never run while the plan and calendar event
 * describe different occurrences.
 */
function bvmgr_ticketing_v2_plan_calendar_alignment(int $plan_id, int $tec_event_id): array {
    $plan_id = absint($plan_id);
    $tec_event_id = absint($tec_event_id);
    $out = array(
        'checkable' => false,
        'aligned' => false,
        'expected_start' => '',
        'expected_end' => '',
        'current_start' => '',
        'current_end' => '',
    );

    if ($plan_id <= 0 || $tec_event_id <= 0 || !function_exists('bvmgr_build_tec_event_args')) {
        return $out;
    }

    $args = bvmgr_build_tec_event_args($plan_id, $tec_event_id);
    if (empty($args)) {
        return $out;
    }

    $out['expected_start'] = bvmgr_ticketing_v2_normalize_sales_window_value(
        trim((string) ($args['EventStartDate'] ?? '')) . ' ' . trim((string) ($args['EventStartTime'] ?? ''))
    );
    $out['expected_end'] = bvmgr_ticketing_v2_normalize_sales_window_value(
        trim((string) ($args['EventEndDate'] ?? '')) . ' ' . trim((string) ($args['EventEndTime'] ?? ''))
    );
    $out['current_start'] = bvmgr_ticketing_v2_normalize_sales_window_value(bvmgr_ticketing_b_get_tec_event_start($tec_event_id));
    $out['current_end'] = function_exists('bvmgr_ticketing_b_get_tec_event_end')
        ? bvmgr_ticketing_v2_normalize_sales_window_value(bvmgr_ticketing_b_get_tec_event_end($tec_event_id))
        : '';

    $out['checkable'] = (
        $out['expected_start'] !== ''
        && $out['expected_end'] !== ''
        && $out['current_start'] !== ''
        && $out['current_end'] !== ''
    );
    $out['aligned'] = (
        $out['checkable']
        && hash_equals($out['expected_start'], $out['current_start'])
        && hash_equals($out['expected_end'], $out['current_end'])
    );

    return $out;
}

/**
 * Whether the linked calendar occurrence had already completed before a change.
 */
function bvmgr_ticketing_v2_calendar_event_was_closed(int $tec_event_id): bool {
    $tec_event_id = absint($tec_event_id);
    if ($tec_event_id <= 0 || !function_exists('bvmgr_ticketing_b_get_tec_event_end')) {
        return false;
    }

    $event_end = bvmgr_ticketing_v2_normalize_sales_window_value(bvmgr_ticketing_b_get_tec_event_end($tec_event_id));
    if ($event_end === '') {
        return false;
    }

    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
    try {
        $end = new DateTimeImmutable($event_end, $tz);
        return $end->getTimestamp() < time();
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Invalidate object, Woo, Event Tickets, and page caches affected by a window sync.
 */
function bvmgr_ticketing_v2_invalidate_calendar_ticket_caches(int $tec_event_id, array $product_ids): void {
    $tec_event_id = absint($tec_event_id);
    $product_ids = array_values(array_unique(array_filter(array_map('absint', $product_ids))));

    foreach ($product_ids as $product_id) {
        if (function_exists('clean_post_cache')) {
            clean_post_cache($product_id);
        }
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($product_id);
        }
        if (function_exists('wp_cache_post_change')) {
            wp_cache_post_change($product_id);
        }
    }

    if ($tec_event_id > 0) {
        if (function_exists('clean_post_cache')) {
            clean_post_cache($tec_event_id);
        }
        if (function_exists('wp_cache_post_change')) {
            wp_cache_post_change($tec_event_id);
        }
        if (function_exists('tribe_tickets')) {
            try {
                $provider = tribe_tickets('woo');
                if (is_object($provider) && method_exists($provider, 'clear_ticket_cache_for_post')) {
                    $provider->clear_ticket_cache_for_post($tec_event_id);
                }
            } catch (Throwable $e) {
            }
        }
    }
}

/**
 * Re-derive mapped native ticket windows after a legitimate calendar date change.
 *
 * This intentionally refuses to reopen an occurrence that was already completed.
 * A future explicit Reschedule workflow owns that exceptional business operation.
 */
function bvmgr_ticketing_v2_sync_mapped_ticket_sales_windows_for_calendar_change(
    int $plan_id,
    int $tec_event_id,
    bool $event_was_closed
): array {
    $plan_id = absint($plan_id);
    $tec_event_id = absint($tec_event_id);
    $out = array(
        'ok' => true,
        'skipped' => false,
        'reason' => '',
        'updated_product_ids' => array(),
        'checked_product_ids' => array(),
        'errors' => array(),
    );

    if ($plan_id <= 0 || $tec_event_id <= 0) {
        $out['ok'] = false;
        $out['reason'] = 'invalid_event_context';
        return $out;
    }
    if ($event_was_closed) {
        $out['skipped'] = true;
        $out['reason'] = 'completed_event_not_reopened';
        return $out;
    }
    if (function_exists('bvmgr_event_plan_is_externally_ticketed') && bvmgr_event_plan_is_externally_ticketed($plan_id)) {
        $out['skipped'] = true;
        $out['reason'] = 'external_ticketing';
        return $out;
    }

    $cfg = bvmgr_ticketing_v2_get_config($plan_id);
    if ((string) ($cfg['mode'] ?? 'read_only') !== 'vms_managed') {
        $out['skipped'] = true;
        $out['reason'] = 'mode_not_managed';
        return $out;
    }

    $sync = bvmgr_ticketing_v2_get_sync($plan_id);
    $map = (isset($sync['map']['tickets']) && is_array($sync['map']['tickets'])) ? $sync['map']['tickets'] : array();
    $tickets = (isset($cfg['tickets']) && is_array($cfg['tickets'])) ? $cfg['tickets'] : array();

    foreach ($tickets as $ticket) {
        if (!is_array($ticket) || (array_key_exists('enabled', $ticket) && empty($ticket['enabled']))) {
            continue;
        }

        $ticket_key = sanitize_key((string) ($ticket['ticket_key'] ?? $ticket['key'] ?? ''));
        $map_row = ($ticket_key !== '' && isset($map[$ticket_key]) && is_array($map[$ticket_key])) ? $map[$ticket_key] : array();
        $product_id = absint($map_row['woo_product_id'] ?? 0);
        if ($product_id <= 0 && $ticket_key === 'ga') {
            $product_id = absint($sync['map']['ga']['woo_product_id'] ?? 0);
        }
        if ($product_id <= 0) {
            continue;
        }

        $out['checked_product_ids'][] = $product_id;
        if (
            get_post_type($product_id) !== 'product'
            || absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true)) !== $tec_event_id
        ) {
            $out['errors'][] = array('product_id' => $product_id, 'code' => 'invalid_ticket_mapping');
            continue;
        }

        $expected = bvmgr_ticketing_b_resolve_sales_window($tec_event_id, $ticket);
        $expected_start = bvmgr_ticketing_v2_normalize_sales_window_value((string) ($expected['start'] ?? ''));
        $expected_end = bvmgr_ticketing_v2_normalize_sales_window_value((string) ($expected['end'] ?? ''));
        $current_start = bvmgr_ticketing_v2_normalize_sales_window_value((string) get_post_meta($product_id, '_ticket_start_date', true));
        $current_end = bvmgr_ticketing_v2_normalize_sales_window_value((string) get_post_meta($product_id, '_ticket_end_date', true));

        if ($expected_start !== $current_start) {
            if ($expected_start === '') {
                delete_post_meta($product_id, '_ticket_start_date');
            } else {
                update_post_meta($product_id, '_ticket_start_date', $expected_start);
            }
        }
        if ($expected_end !== $current_end) {
            if ($expected_end === '') {
                delete_post_meta($product_id, '_ticket_end_date');
            } else {
                update_post_meta($product_id, '_ticket_end_date', $expected_end);
            }
        }

        $verified_start = bvmgr_ticketing_v2_normalize_sales_window_value((string) get_post_meta($product_id, '_ticket_start_date', true));
        $verified_end = bvmgr_ticketing_v2_normalize_sales_window_value((string) get_post_meta($product_id, '_ticket_end_date', true));
        if ($verified_start !== $expected_start || $verified_end !== $expected_end) {
            $out['errors'][] = array('product_id' => $product_id, 'code' => 'sales_window_verification_failed');
            continue;
        }

        if ($current_start !== $expected_start || $current_end !== $expected_end) {
            $out['updated_product_ids'][] = $product_id;
        }
    }

    $out['checked_product_ids'] = array_values(array_unique($out['checked_product_ids']));
    $out['updated_product_ids'] = array_values(array_unique($out['updated_product_ids']));
    $out['ok'] = empty($out['errors']);
    if (!$out['ok']) {
        $out['reason'] = 'ticket_sales_window_sync_failed';
    }

    bvmgr_ticketing_v2_invalidate_calendar_ticket_caches($tec_event_id, $out['checked_product_ids']);
    do_action('vms_ticketing_v2_calendar_sales_windows_synced', $plan_id, $tec_event_id, $out);

    return $out;
}

/**
 * Best-effort TEC event start datetime in 'Y-m-d H:i:s' (site timezone).
 */
function bvmgr_ticketing_b_get_tec_event_start(int $tec_event_id): string {
    $tec_event_id = absint($tec_event_id);
    if ($tec_event_id <= 0) {
        return '';
    }

    // Read canonical local-time meta first. TEC date helpers may retain the prior
    // occurrence in request-local caches immediately after tribe_update_event().
    $meta = get_post_meta($tec_event_id, '_EventStartDate', true);
    $meta = is_string($meta) ? trim($meta) : '';
    if ($meta !== '') {
        return $meta;
    }

    if (function_exists('tribe_get_start_date')) {
        $s = tribe_get_start_date($tec_event_id, true, 'Y-m-d H:i:s');
        $s = is_string($s) ? trim($s) : '';
        if ($s !== '') {
            return $s;
        }
    }

    return '';
}

/**
 * Best-effort TEC event end datetime in 'Y-m-d H:i:s' (site timezone).
 */
function bvmgr_ticketing_b_get_tec_event_end(int $tec_event_id): string {
    $tec_event_id = absint($tec_event_id);
    if ($tec_event_id <= 0) {
        return '';
    }

    // See the start-date helper above: prefer the just-persisted canonical meta
    // over a possibly stale request-local TEC object.
    $meta = get_post_meta($tec_event_id, '_EventEndDate', true);
    $meta = is_string($meta) ? trim($meta) : '';
    if ($meta !== '') {
        return $meta;
    }

    if (function_exists('tribe_get_end_date')) {
        $s = tribe_get_end_date($tec_event_id, true, 'Y-m-d H:i:s');
        $s = is_string($s) ? trim($s) : '';
        if ($s !== '') {
            return $s;
        }
    }

    return '';
}

function bvmgr_ticketing_b_commit_sync(int $plan_id, array $preview_items): array {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return array('ok' => false, 'message' => 'invalid_plan');
    }
    if (!current_user_can('edit_post', $plan_id)) {
        return array('ok' => false, 'message' => 'forbidden', 'http' => 403);
    }

    $tec_event_id = bvmgr_ticketing_b_get_linked_tec_event_id($plan_id);
    if ($tec_event_id <= 0) {
        return array('ok' => false, 'message' => 'missing_tec_link');
    }
    if (!bvmgr_ticketing_b_is_event_tickets_woo_available()) {
        return array('ok' => false, 'message' => 'event_tickets_woo_unavailable');
    }

    // Build tier lookup by tier_key.
    $tiers_raw = bvmgr_ticketing_b_get_tiers($plan_id);
    $tiers = array();
    foreach ($tiers_raw as $t) {
        if (!is_array($t)) {
            continue;
        }
        $tn = bvmgr_ticketing_b_normalize_tier($t);
        if (trim((string) $tn['name']) === '') {
            continue;
        }
        $tiers[(string) $tn['tier_key']] = $tn;
    }

    $map = bvmgr_ticketing_b_get_map($plan_id);
    $now = time();

    $results = array();
    foreach ($preview_items as $pi) {
        if (!is_array($pi)) {
            continue;
        }
        $tier_key = isset($pi['tier_key']) ? sanitize_key((string) $pi['tier_key']) : '';
        $action = isset($pi['action']) ? (string) $pi['action'] : '';
        if ($tier_key === '' || !isset($tiers[$tier_key])) {
            continue;
        }
        if (!in_array($action, array('skip', 'adopt', 'create', 'update'), true)) {
            continue;
        }

        $tier = $tiers[$tier_key];
        $hash = bvmgr_ticketing_b_tier_hash($tier);

        $row = array(
            'tier_key' => $tier_key,
            'name' => (string) $tier['name'],
            'action' => $action,
            'ok' => false,
            'message' => '',
            'woo_product_id' => 0,
        );

        try {
            if ($action === 'skip') {
                $row['ok'] = true;
                $row['message'] = 'skipped';
                $results[] = $row;
                continue;
            }

            if ($action === 'adopt') {
                $pid = isset($pi['woo_product_id']) ? absint($pi['woo_product_id']) : 0;
                if ($pid <= 0 || get_post_type($pid) !== 'product') {
                    $row['message'] = 'invalid_product_for_adopt';
                    $results[] = $row;
                    continue;
                }
                $linked = (int) get_post_meta($pid, '_tribe_wooticket_for_event', true);
                if ($linked !== $tec_event_id) {
                    $row['message'] = 'adopt_linkage_mismatch';
                    $results[] = $row;
                    continue;
                }
                $map[$tier_key] = array(
                    'provider' => 'woo',
                    'tec_ticket_id' => $pid,
                    'woo_product_id' => $pid,
                    'sync_status' => 'synced',
                    'last_sync_at' => $now,
                    'last_sync_hash' => $hash,
                    'last_error' => '',
                );
                $row['ok'] = true;
                $row['message'] = 'adopted';
                $row['woo_product_id'] = $pid;
                $results[] = $row;
                continue;
            }

            if ($action === 'create') {
                $created = bvmgr_ticketing_b_create_woo_ticket($tec_event_id, $tier);
                if (empty($created['ok'])) {
                    $row['message'] = isset($created['message']) ? (string) $created['message'] : 'create_failed';
                    $results[] = $row;
                    continue;
                }
                $pid = absint($created['woo_product_id'] ?? 0);
                $map[$tier_key] = array(
                    'provider' => 'woo',
                    'tec_ticket_id' => $pid,
                    'woo_product_id' => $pid,
                    'sync_status' => 'synced',
                    'last_sync_at' => $now,
                    'last_sync_hash' => $hash,
                    'last_error' => '',
                );
                $row['ok'] = true;
                $row['message'] = 'created';
                $row['woo_product_id'] = $pid;
                $results[] = $row;
                continue;
            }

            if ($action === 'update') {
                $m = isset($map[$tier_key]) && is_array($map[$tier_key]) ? $map[$tier_key] : array();
                $pid = isset($m['woo_product_id']) ? absint($m['woo_product_id']) : 0;
                if ($pid <= 0) {
                    $row['message'] = 'missing_mapping_for_update';
                    $results[] = $row;
                    continue;
                }
                $updated = bvmgr_ticketing_b_apply_update_to_product($pid, $tier, $tec_event_id);
                if (empty($updated['ok'])) {
                    $msg = isset($updated['message']) ? (string) $updated['message'] : 'update_failed';
                    $map[$tier_key]['sync_status'] = 'error';
                    $map[$tier_key]['last_error'] = $msg;
                    $map[$tier_key]['last_sync_at'] = $now;
                    $row['message'] = $msg;
                    $results[] = $row;
                    continue;
                }
                $map[$tier_key]['sync_status'] = 'synced';
                $map[$tier_key]['last_error'] = '';
                $map[$tier_key]['last_sync_at'] = $now;
                $map[$tier_key]['last_sync_hash'] = $hash;
                $row['ok'] = true;
                $row['message'] = 'updated';
                $row['woo_product_id'] = $pid;
                $results[] = $row;
                continue;
            }
        } catch (Throwable $e) {
            $row['message'] = 'exception: ' . $e->getMessage();
            $results[] = $row;
        }
    }

    bvmgr_ticketing_b_set_map($plan_id, $map);
    bvmgr_ticketing_b_set_mode($plan_id, 'vms_managed');

    // Clear cached Phase A stats; operator can refresh.
    $k_pids = bvmgr_ticketing_b_meta_key('ticket_product_ids', '_vms_ticket_product_ids_v1');
    $k_stat = bvmgr_ticketing_b_meta_key('ticket_stats', '_vms_ticket_stats_v1');
    delete_post_meta($plan_id, $k_pids);
    delete_post_meta($plan_id, $k_stat);

    $tier_sort_fallback = 10;
    foreach ($map as $tier_key => $map_row) {
        if (!is_array($map_row)) {
            continue;
        }
        if (!isset($tiers[$tier_key]) || !is_array($tiers[$tier_key])) {
            continue;
        }

        $pid = absint($map_row['woo_product_id'] ?? 0);
        if ($pid <= 0) {
            $tier_sort_fallback += 10;
            continue;
        }

        $tier_sort_order = bvmgr_ticketing_b_normalize_sort_order($tiers[$tier_key]['sort_order'] ?? 0, $tier_sort_fallback);
        bvmgr_ticketing_b_apply_product_sort_order($pid, $tier_sort_order, $tier_sort_fallback, 'ticket');
        $tier_sort_fallback += 10;
    }

    return array('ok' => true, 'results' => $results);
}

/**
 * AJAX: save ticket tiers.
 */
function bvmgr_ticketing_payload_is_object_like_array(array $value): bool {
    return empty($value) || !bvmgr_array_is_list_compat($value);
}

function bvmgr_ticketing_b_request_payload_value(array $source, string $key, &$present = null, &$valid = null, &$raw_string_bytes = null) {
    $present = array_key_exists($key, $source);
    $valid = false;
    $raw_string_bytes = 0;
    if (!$present) {
        return null;
    }

    if (is_array($source[$key])) {
        $value = wp_unslash($source[$key]);
        if (!is_array($value)) {
            return null;
        }
        $valid = true;
        return $value;
    }

    if (is_scalar($source[$key])) {
        $raw_string_bytes = is_string($source[$key]) ? strlen($source[$key]) : 0;
        $value = wp_unslash($source[$key]);
        if (!is_scalar($value)) {
            return null;
        }
        $valid = true;
        return (string) $value;
    }

    return null;
}

/**
 * @return array{ok:bool,value:array<int,mixed>}
 */
function bvmgr_ticketing_b_decode_list_payload(string $raw, int $max_bytes, int $depth = 32): array {
    $raw = trim($raw);
    if ($raw === '' || strlen($raw) > $max_bytes) {
        return array('ok' => false, 'value' => array());
    }

    $decoded = bvmgr_json_decode_associative($raw, $depth);
    if (
        empty($decoded['ok'])
        || !is_array($decoded['value'])
        || !bvmgr_json_decoded_is_list($decoded['value'], (string) ($decoded['top_level_token'] ?? ''))
    ) {
        return array('ok' => false, 'value' => array());
    }

    return array(
        'ok' => true,
        'value' => $decoded['value'],
    );
}

function bvmgr_ticketing_b_validate_tier_rows_payload(array $tiers): bool {
    if (!empty($tiers) && !bvmgr_array_is_list_compat($tiers)) {
        return false;
    }
    if (count($tiers) > 25) {
        return false;
    }

    $scalar_keys = array(
        'tier_key',
        'name',
        'price',
        'early_price',
        'early_price_start',
        'early_price_end',
        'early_price_start_relative_days',
        'early_price_end_relative_days',
        'early_price_cap',
        'capacity',
        'sales_start',
        'sales_end',
        'sales_start_relative_days',
        'sales_end_relative_days',
        'counts_toward_attendance',
        'qualifies_for_discounts',
        'qualification_code',
        'sort_order',
        'is_hidden',
    );

    foreach ($tiers as $tier) {
        if (!is_array($tier) || !bvmgr_ticketing_payload_is_object_like_array($tier)) {
            return false;
        }

        foreach ($scalar_keys as $scalar_key) {
            if (isset($tier[$scalar_key]) && (is_array($tier[$scalar_key]) || is_object($tier[$scalar_key]))) {
                return false;
            }
        }
    }

    return true;
}

function bvmgr_ticketing_b_validate_commit_items_payload(array $items): bool {
    if (!empty($items) && !bvmgr_array_is_list_compat($items)) {
        return false;
    }
    if (count($items) > 25) {
        return false;
    }

    foreach ($items as $item) {
        if (!is_array($item) || !bvmgr_ticketing_payload_is_object_like_array($item)) {
            return false;
        }

        if (!isset($item['tier_key']) || !is_scalar($item['tier_key']) || sanitize_key((string) $item['tier_key']) === '') {
            return false;
        }
        if (!isset($item['action']) || !is_scalar($item['action']) || !in_array((string) $item['action'], array('skip', 'adopt', 'create', 'update'), true)) {
            return false;
        }
        if (isset($item['woo_product_id']) && (is_array($item['woo_product_id']) || is_object($item['woo_product_id']))) {
            return false;
        }
    }

    return true;
}

function bvmgr_ticketing_v2_validate_config_payload(array $cfg): bool {
    if (!bvmgr_ticketing_payload_is_object_like_array($cfg)) {
        return false;
    }

    if (isset($cfg['mode']) && (is_array($cfg['mode']) || is_object($cfg['mode']))) {
        return false;
    }

    if (isset($cfg['ga']) && (!is_array($cfg['ga']) || !bvmgr_ticketing_payload_is_object_like_array($cfg['ga']))) {
        return false;
    }

    foreach (array('tickets', 'entitlements') as $list_key) {
        if (!array_key_exists($list_key, $cfg)) {
            continue;
        }

        $rows = $cfg[$list_key];
        if (!is_array($rows) || (!empty($rows) && !bvmgr_array_is_list_compat($rows)) || count($rows) > 200) {
            return false;
        }

        foreach ($rows as $row) {
            if (!is_array($row) || !bvmgr_ticketing_payload_is_object_like_array($row)) {
                return false;
            }
        }
    }

    return true;
}

function bvmgr_ticketing_b_ajax_save_tiers(): void {
    if (!check_ajax_referer(bvmgr_nonce_action_for_request('bvmgr_ticketing_nonce', 'nonce'), 'nonce', false)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);
    }

    $plan_id = bvmgr_request_read_absint($_POST, 'plan_id');
    if ($plan_id <= 0 || !current_user_can('edit_post', $plan_id)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'forbidden'), 403);
    }

    $tiers_present = false;
    $tiers_valid = false;
    $tiers_in_raw = bvmgr_ticketing_b_request_payload_value($_POST, 'tiers', $tiers_present, $tiers_valid);
    if (!$tiers_valid) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'invalid_payload_tiers'), 400);
    }

    // Harden: WP may slash JSON strings; browsers may send nested arrays.
    $tiers_in = null;
    if (is_array($tiers_in_raw)) {
        if (bvmgr_ticketing_b_validate_tier_rows_payload($tiers_in_raw)) {
            $tiers_in = $tiers_in_raw;
        }
    } elseif (is_string($tiers_in_raw)) {
        $tiers_in_raw = trim($tiers_in_raw);
        if ($tiers_in_raw !== '') {
            $decoded = bvmgr_ticketing_b_decode_list_payload($tiers_in_raw, 65536, 32);
            if (!empty($decoded['ok']) && bvmgr_ticketing_b_validate_tier_rows_payload($decoded['value'])) {
                $tiers_in = $decoded['value'];
            }
        }
    }

    if (!is_array($tiers_in)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'invalid_payload_tiers'), 400);
    }

$tiers_out = array();
    $seen = array();
    foreach ($tiers_in as $t) {
        if (!is_array($t)) {
            continue;
        }
        $tn = bvmgr_ticketing_b_normalize_tier($t);
        if (trim((string) $tn['name']) === '') {
            continue;
        }
        $k = (string) $tn['tier_key'];
        if ($k === '' || isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $tiers_out[] = $tn;
    }

    bvmgr_ticketing_b_set_tiers($plan_id, $tiers_out);

    bvmgr_ticketing_v2_ajax_send_success(array('tiers' => $tiers_out));
}
add_action('wp_ajax_vms_ticketing_save_tiers', 'bvmgr_ticketing_b_ajax_save_tiers');

/**
 * AJAX: preview sync.
 */
function bvmgr_ticketing_b_ajax_preview_sync(): void {
    if (!check_ajax_referer(bvmgr_nonce_action_for_request('bvmgr_ticketing_nonce', 'nonce'), 'nonce', false)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);
    }

    $plan_id = isset($_POST['plan_id']) ? absint($_POST['plan_id']) : 0;
    if ($plan_id <= 0 || !current_user_can('edit_post', $plan_id)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'forbidden'), 403);
    }

    $preview = bvmgr_ticketing_b_preview_sync($plan_id);
    if (empty($preview['ok'])) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => $preview['message'] ?? 'error'), 400);
    }

    bvmgr_ticketing_v2_ajax_send_success($preview);
}
add_action('wp_ajax_vms_ticketing_preview_sync', 'bvmgr_ticketing_b_ajax_preview_sync');

/**
 * AJAX: commit sync.
 */
function bvmgr_ticketing_b_ajax_commit_sync(): void {
    if (!check_ajax_referer(bvmgr_nonce_action_for_request('bvmgr_ticketing_nonce', 'nonce'), 'nonce', false)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);
    }

    $plan_id = bvmgr_request_read_absint($_POST, 'plan_id');
    if ($plan_id <= 0 || !current_user_can('edit_post', $plan_id)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'forbidden'), 403);
    }

    $items_present = false;
    $items_valid = false;
    $items_raw = bvmgr_ticketing_b_request_payload_value($_POST, 'items', $items_present, $items_valid);
    if (!$items_valid) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'invalid_payload_items'), 400);
    }

    $items = null;
    if (is_array($items_raw)) {
        if (bvmgr_ticketing_b_validate_commit_items_payload($items_raw)) {
            $items = $items_raw;
        }
    } elseif (is_string($items_raw)) {
        $items_raw = trim($items_raw);
        if ($items_raw !== '') {
            $decoded = bvmgr_ticketing_b_decode_list_payload($items_raw, 65536, 32);
            if (!empty($decoded['ok']) && bvmgr_ticketing_b_validate_commit_items_payload($decoded['value'])) {
                $items = $decoded['value'];
            }
        }
    }

    if (!is_array($items)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'invalid_payload_items'), 400);
    }

    $res = bvmgr_ticketing_b_commit_sync($plan_id, $items);
    if (empty($res['ok'])) {
        $http = isset($res['http']) ? (int) $res['http'] : 400;
        bvmgr_ticketing_v2_ajax_send_error(array(
            'message' => $res['message'] ?? 'error',
            'error_code' => $res['error_code'] ?? ($res['message'] ?? 'error'),
            'error_summary' => $res['error_summary'] ?? '',
            'diagnostics' => is_array($res['diagnostics'] ?? null) ? $res['diagnostics'] : array(),
        ), $http);
    }

    bvmgr_ticketing_v2_ajax_send_success($res);
}
add_action('wp_ajax_vms_ticketing_commit_sync', 'bvmgr_ticketing_b_ajax_commit_sync');

/* ========================================================================== 
   Ticketing Integration — Phase B v2
   GA attendance + entitlements + quantitative eligibility rules
   - GA ticket is created via Event Tickets (Woo provider)
   - Entitlements are Woo products (hidden) with rule enforcement in cart/checkout
   - Preview → Commit only; no deletes; no silent failures
   ========================================================================== */

function bvmgr_ticketing_v2_k(string $which): string {
    // Event Plan meta keys
    switch ($which) {
        case 'config':
            return bvmgr_ticketing_b_meta_key('ticketing_config_v2', '_vms_ticketing_config_v2');
        case 'sync':
            return bvmgr_ticketing_b_meta_key('ticketing_sync_v2', '_vms_ticketing_sync_v2');
        case 'stats':
            return bvmgr_ticketing_b_meta_key('ticketing_stats_v2', '_vms_ticketing_stats_v2');
        case 'migration_snapshot':
            return bvmgr_ticketing_b_meta_key('ticketing_migration_snapshot_v1', '_vms_ticketing_migration_snapshot_v1');
        default:
            return '';
    }
}

function bvmgr_ticketing_v2_product_meta_key(string $which): string {
    // Product meta keys (stored on Woo product posts)
    if (function_exists('bvmgr_meta_key')) {
        $k = bvmgr_meta_key('product', $which);
        if (is_string($k) && $k !== '') {
            return $k;
        }
    }

    // Fallbacks (must match meta-keys registry)
    switch ($which) {
        case 'event_plan_id':
            return '_vms_event_plan_id';
        case 'tec_event_id':
            return '_vms_tec_event_id';
        case 'product_role':
            return '_vms_product_role';
        case 'ticketing_entitlement_id':
            return '_vms_ticketing_entitlement_id';
        case 'ticketing_ticket_key':
            return '_vms_ticketing_ticket_key';
        case 'ticketing_counts_toward_unlock':
            return '_vms_ticketing_counts_toward_unlock';
        case 'ticketing_visibility_mode':
            return '_vms_ticketing_visibility_mode';
        case 'ticketing_verified_program':
            return '_vms_ticketing_verified_program';
        case 'ticketing_allowed_programs':
            return '_vms_ticketing_allowed_programs';
        case 'ticketing_allow_direct_grants':
            return '_vms_ticketing_allow_direct_grants';
        case 'ticketing_claim_grant_type':
            return '_vms_ticketing_claim_grant_type';
        case 'ticketing_claims_per_assignee':
            return '_vms_ticketing_claims_per_assignee';
        case 'ticketing_require_assignee_email':
            return '_vms_ticketing_require_assignee_email';
        case 'ticketing_max_qty_per_order':
            return '_vms_ticketing_max_qty_per_order';
        case 'ticketing_ratio_rule_enabled':
            return '_vms_ticketing_ratio_rule_enabled';
        case 'ticketing_ratio_rule_max_per_qualifying':
            return '_vms_ticketing_ratio_rule_max_per_qualifying';
        case 'ticketing_ratio_rule_qualifier_mode':
            return '_vms_ticketing_ratio_rule_qualifier_mode';
        case 'ticketing_ratio_rule_group':
            return '_vms_ticketing_ratio_rule_group';
        case 'ticketing_marker_version':
            return '_vms_ticketing_marker_version';
        case 'ticketing_source_plan_id':
            return '_vms_ticketing_source_plan_id';
        case 'ticketing_source_provider':
            return '_vms_ticketing_source_provider';
        default:
            return '';
    }
}

function bvmgr_ticketing_v2_reporting_category_map(): array {
    return array(
        'ticket' => array(
            'name' => 'Online Ticket',
            'slug' => 'online-ticket',
        ),
        'addon' => array(
            'name' => 'Online Addon',
            'slug' => 'online-addon',
        ),
    );
}

function bvmgr_ticketing_v2_reporting_category_kind_for_role(string $role): string {
    $role = sanitize_key($role);
    if (in_array($role, array('entitlement', 'addon'), true)) {
        return 'addon';
    }
    if (in_array($role, array('ga_ticket', 'ticket', 'legacy_ticket'), true)) {
        return 'ticket';
    }
    return '';
}

function bvmgr_ticketing_v2_ensure_reporting_category_term(string $kind): int {
    if (!taxonomy_exists('product_cat')) {
        return 0;
    }

    $map = bvmgr_ticketing_v2_reporting_category_map();
    if (!isset($map[$kind]) || !is_array($map[$kind])) {
        return 0;
    }

    $name = trim((string) ($map[$kind]['name'] ?? ''));
    $slug = sanitize_title((string) ($map[$kind]['slug'] ?? ''));
    if ($name === '' || $slug === '') {
        return 0;
    }

    $term = get_term_by('slug', $slug, 'product_cat');
    if ($term instanceof WP_Term) {
        return absint($term->term_id);
    }

    $existing = term_exists($name, 'product_cat');
    if (is_array($existing) && !empty($existing['term_id'])) {
        return absint($existing['term_id']);
    }
    if (is_numeric($existing)) {
        return absint($existing);
    }

    $created = wp_insert_term($name, 'product_cat', array('slug' => $slug));
    if (is_wp_error($created)) {
        $term = get_term_by('slug', $slug, 'product_cat');
        if ($term instanceof WP_Term) {
            return absint($term->term_id);
        }
        return 0;
    }

    return absint($created['term_id'] ?? 0);
}


function bvmgr_ticketing_v2_square_product_api_ready(): bool {
    return function_exists('wc_square')
        && function_exists('wc_get_product')
        && class_exists('\WooCommerce\Square\Handlers\Product');
}

function bvmgr_ticketing_v2_square_auto_sync_bridge_enabled(): bool {
    $enabled = false;

    /**
     * Safety valve for the reporting-category → Square sync bridge.
     *
     * Default is OFF because ticket inventory must not be re-written by a
     * background/manual Square sync that was only intended to backfill
     * reporting categories. Sites can opt back in deliberately if needed.
     */
    return (bool) apply_filters('vms_ticketing_v2_square_auto_sync_bridge_enabled', $enabled);
}

function bvmgr_ticketing_v2_square_sync_bridge_ready(): bool {
    return bvmgr_ticketing_v2_square_auto_sync_bridge_enabled()
        && bvmgr_ticketing_v2_square_product_api_ready()
        && function_exists('wc_square')
        && function_exists('wc_get_product')
        && is_object(wc_square())
        && method_exists(wc_square(), 'get_settings_handler')
        && method_exists(wc_square(), 'get_sync_handler')
        && class_exists('\\WooCommerce\\Square\\Handlers\\Product');
}

function bvmgr_ticketing_v2_square_prepare_product(int $product_id): bool {
    $product_id = absint($product_id);
    if ($product_id <= 0 || !bvmgr_ticketing_v2_square_sync_bridge_ready()) {
        return false;
    }

    try {
        $product = wc_get_product($product_id);
        if (!$product instanceof WC_Product) {
            return false;
        }

        if (!\WooCommerce\Square\Handlers\Product::can_sync_with_square($product)) {
            return false;
        }

        return (bool) \WooCommerce\Square\Handlers\Product::set_synced_with_square($product, 'yes');
    } catch (Throwable $e) {
        return false;
    }
}

function bvmgr_ticketing_v2_square_flush_manual_sync_queue(): void {
    $product_ids = $GLOBALS['bvmgr_ticketing_v2_square_sync_queue'] ?? array();
    unset($GLOBALS['bvmgr_ticketing_v2_square_sync_queue'], $GLOBALS['bvmgr_ticketing_v2_square_sync_queue_attached']);

    if (!is_array($product_ids) || empty($product_ids) || !bvmgr_ticketing_v2_square_sync_bridge_ready()) {
        return;
    }

    $product_ids = array_values(array_unique(array_filter(array_map('absint', $product_ids))));
    if (empty($product_ids)) {
        return;
    }

    try {
        $settings = wc_square()->get_settings_handler();
        $sync = wc_square()->get_sync_handler();

        if (!is_object($settings) || !is_object($sync)) {
            return;
        }

        if (method_exists($settings, 'is_connected') && !$settings->is_connected()) {
            return;
        }

        if (method_exists($settings, 'is_product_sync_enabled') && !$settings->is_product_sync_enabled()) {
            return;
        }

        if (method_exists($settings, 'is_system_of_record_woocommerce') && !$settings->is_system_of_record_woocommerce()) {
            return;
        }

        if (method_exists($sync, 'is_sync_in_progress') && $sync->is_sync_in_progress()) {
            return;
        }

        if (method_exists($sync, 'start_manual_sync')) {
            $sync->start_manual_sync($product_ids);
        }
    } catch (Throwable $e) {
        // Best-effort bridge only. Do not interrupt product saves.
    }
}

function bvmgr_ticketing_v2_square_queue_manual_sync(int $product_id): void {
    $product_id = absint($product_id);
    if ($product_id <= 0 || !bvmgr_ticketing_v2_square_sync_bridge_ready()) {
        return;
    }

    if (!isset($GLOBALS['bvmgr_ticketing_v2_square_sync_queue']) || !is_array($GLOBALS['bvmgr_ticketing_v2_square_sync_queue'])) {
        $GLOBALS['bvmgr_ticketing_v2_square_sync_queue'] = array();
    }

    $GLOBALS['bvmgr_ticketing_v2_square_sync_queue'][] = $product_id;

    if (empty($GLOBALS['bvmgr_ticketing_v2_square_sync_queue_attached'])) {
        $GLOBALS['bvmgr_ticketing_v2_square_sync_queue_attached'] = true;
        add_action('shutdown', 'bvmgr_ticketing_v2_square_flush_manual_sync_queue', 99);
    }
}

function bvmgr_ticketing_v2_apply_reporting_category(int $product_id, string $kind): bool {
    $product_id = absint($product_id);
    if ($product_id <= 0 || !taxonomy_exists('product_cat')) {
        return false;
    }

    $kind = ($kind === 'addon') ? 'addon' : (($kind === 'ticket') ? 'ticket' : '');
    if ($kind === '') {
        return false;
    }

    $target_term_id = bvmgr_ticketing_v2_ensure_reporting_category_term($kind);
    if ($target_term_id <= 0) {
        return false;
    }

    $other_kind = ($kind === 'ticket') ? 'addon' : 'ticket';
    $other_term_id = bvmgr_ticketing_v2_ensure_reporting_category_term($other_kind);
    $default_term_id = absint(get_option('default_product_cat', 0));

    $existing_terms = wp_get_object_terms($product_id, 'product_cat', array('fields' => 'ids'));
    if (is_wp_error($existing_terms) || !is_array($existing_terms)) {
        $existing_terms = array();
    }

    $term_ids = array_values(array_filter(array_map('absint', $existing_terms)));
    if ($other_term_id > 0) {
        $term_ids = array_values(array_diff($term_ids, array($other_term_id)));
    }
    if ($default_term_id > 0 && $default_term_id !== $target_term_id) {
        $term_ids = array_values(array_diff($term_ids, array($default_term_id)));
    }

    $term_ids[] = $target_term_id;
    $term_ids = array_values(array_unique(array_filter($term_ids)));

    $result = wp_set_object_terms($product_id, $term_ids, 'product_cat', false);
    if (is_wp_error($result)) {
        return false;
    }

    if (function_exists('bvmgr_square_firewall_is_protected_product') && bvmgr_square_firewall_is_protected_product($product_id)) {
        if (function_exists('bvmgr_square_firewall_protect_product')) {
            bvmgr_square_firewall_protect_product($product_id, true);
        }
    } elseif (bvmgr_ticketing_v2_square_prepare_product($product_id)) {
        bvmgr_ticketing_v2_square_queue_manual_sync($product_id);
    }

    return true;
}

function bvmgr_ticketing_v2_apply_reporting_category_by_product(int $product_id): bool {
    $product_id = absint($product_id);
    if ($product_id <= 0) {
        return false;
    }

    $role = sanitize_key((string) get_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('product_role'), true));
    $kind = bvmgr_ticketing_v2_reporting_category_kind_for_role($role);

    if ($kind === '') {
        $entitlement_id = sanitize_key((string) get_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_entitlement_id'), true));
        if ($entitlement_id !== '') {
            $kind = 'addon';
        }
    }

    if ($kind === '') {
        $source_plan_id = absint(get_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('event_plan_id'), true));
        $source_tec_event_id = absint(get_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('tec_event_id'), true));
        if ($source_plan_id > 0 || $source_tec_event_id > 0) {
            $kind = 'ticket';
        }
    }

    if ($kind === '') {
        return false;
    }

    return bvmgr_ticketing_v2_apply_reporting_category($product_id, $kind);
}

function bvmgr_ticketing_v2_reporting_category_candidate_ids(int $after_id = 0, int $limit = 100): array {
    global $wpdb;

    $after_id = max(0, absint($after_id));
    $limit = max(1, min(250, absint($limit)));

    $sql = $wpdb->prepare(
        "SELECT DISTINCT p.ID
        FROM %i p
        INNER JOIN %i pm ON pm.post_id = p.ID
        WHERE p.post_type = 'product'
          AND p.post_status NOT IN ('trash', 'auto-draft', 'inherit')
          AND p.ID > %d
          AND pm.meta_key IN (%s, %s, %s, %s)
        ORDER BY p.ID ASC
        LIMIT %d",
        $wpdb->posts,
        $wpdb->postmeta,
        $after_id,
        bvmgr_ticketing_v2_product_meta_key('product_role'),
        bvmgr_ticketing_v2_product_meta_key('ticketing_entitlement_id'),
        bvmgr_ticketing_v2_product_meta_key('event_plan_id'),
        bvmgr_ticketing_v2_product_meta_key('tec_event_id'),
        $limit
    );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- The bounded reporting-category backfill reads distinct product IDs with prepared core-table identifiers and must observe current product metadata before advancing its cursor.
    $rows = $wpdb->get_col($sql);
    if (!is_array($rows)) {
        return array();
    }

    return array_values(array_filter(array_map('absint', $rows)));
}

function bvmgr_ticketing_v2_reporting_category_backfill_once(): void {
    if (!is_admin() || !taxonomy_exists('product_cat')) {
        return;
    }

    $version = '2026-03-31-1';
    $version_option = 'vms_ticketing_reporting_category_backfill_version';
    if ((string) get_option($version_option, '') === $version) {
        return;
    }
    $guard = function_exists('bvmgr_admin_guard_begin')
        ? bvmgr_admin_guard_begin('admin_init.ticketing_reporting_category_backfill', array(
            'task' => 'ticketing_reporting_category_backfill',
            'allow_action' => 'ticketing_reporting_category_backfill',
            'lock_name' => 'ticketing_reporting_category_backfill',
            'lock_ttl' => 120,
        ))
        : true;
    if ($guard === false) {
        return;
    }
    $guard_context = array(
        'cursor' => 0,
        'products_processed' => 0,
    );

    try {
        $cursor_option = 'vms_ticketing_reporting_category_backfill_cursor';
        $cursor = absint(get_option($cursor_option, 0));
        $guard_context['cursor'] = $cursor;
        $product_ids = bvmgr_ticketing_v2_reporting_category_candidate_ids($cursor, 100);

        if (empty($product_ids)) {
            update_option($version_option, $version, false);
            delete_option($cursor_option);
            return;
        }

        foreach ($product_ids as $product_id) {
            bvmgr_ticketing_v2_apply_reporting_category_by_product($product_id);
            $cursor = $product_id;
            $guard_context['products_processed']++;
        }

        update_option($cursor_option, $cursor, false);
    } catch (Throwable $e) {
        // Best-effort migration: do not interrupt admin requests if one product is malformed.
    } finally {
        if (is_array($guard) && function_exists('bvmgr_admin_guard_finish')) {
            bvmgr_admin_guard_finish($guard, $guard_context);
        }
    }
}
add_action('admin_init', 'bvmgr_ticketing_v2_reporting_category_backfill_once', 55);

function bvmgr_ticketing_v2_square_unsync_candidates_once(): void {
    if (!is_admin() || !bvmgr_ticketing_v2_square_product_api_ready()) {
        return;
    }

    $version = '2026-04-01-1';
    $version_option = 'vms_ticketing_square_unsync_candidates_version';
    if ((string) get_option($version_option, '') === $version) {
        return;
    }
    $guard = function_exists('bvmgr_admin_guard_begin')
        ? bvmgr_admin_guard_begin('admin_init.ticketing_square_unsync_candidates', array(
            'task' => 'ticketing_square_unsync_candidates',
            'allow_action' => 'ticketing_square_unsync_candidates',
            'lock_name' => 'ticketing_square_unsync_candidates',
            'lock_ttl' => 120,
        ))
        : true;
    if ($guard === false) {
        return;
    }
    $guard_context = array(
        'cursor' => 0,
        'products_processed' => 0,
    );

    try {
        $cursor_option = 'vms_ticketing_square_unsync_candidates_cursor';
        $cursor = absint(get_option($cursor_option, 0));
        $guard_context['cursor'] = $cursor;
        $product_ids = bvmgr_ticketing_v2_reporting_category_candidate_ids($cursor, 100);

        if (empty($product_ids)) {
            update_option($version_option, $version, false);
            delete_option($cursor_option);
            return;
        }

        foreach ($product_ids as $product_id) {
            $product_id = absint($product_id);
            if ($product_id <= 0) {
                continue;
            }

            try {
                $product = wc_get_product($product_id);
                if ($product instanceof WC_Product && \WooCommerce\Square\Handlers\Product::can_sync_with_square($product)) {
                    \WooCommerce\Square\Handlers\Product::set_synced_with_square($product, 'no');
                }
            } catch (Throwable $e) {
                // Best-effort remediation only. Do not interrupt admin requests.
            }

            $cursor = $product_id;
            $guard_context['products_processed']++;
        }

        update_option($cursor_option, $cursor, false);
    } catch (Throwable $e) {
        // Best-effort remediation only. Do not interrupt admin requests.
    } finally {
        if (is_array($guard) && function_exists('bvmgr_admin_guard_finish')) {
            bvmgr_admin_guard_finish($guard, $guard_context);
        }
    }
}
add_action('admin_init', 'bvmgr_ticketing_v2_square_unsync_candidates_once', 56);


if (!function_exists('bvmgr_ticketing_v2_sanitize_program_list')) {
    /**
     * @param mixed $raw
     * @return string[]
     */
    function bvmgr_ticketing_v2_sanitize_program_list($raw): array
    {
        if (function_exists('bvmgr_ticketing_claims_sanitize_program_list')) {
            return bvmgr_ticketing_claims_sanitize_program_list($raw);
        }

        $list = array();
        if (is_array($raw)) {
            $list = $raw;
        } elseif (is_string($raw)) {
            $list = preg_split('/[\s,]+/', $raw) ?: array();
        }

        $out = array();
        foreach ($list as $entry) {
            $key = sanitize_key((string) $entry);
            if ($key === '') {
                continue;
            }
            $out[$key] = $key;
        }
        return array_values($out);
    }
}

if (!function_exists('bvmgr_ticketing_v2_normalize_allowed_programs')) {
    /**
     * @param mixed $raw
     * @return string[]
     */
    function bvmgr_ticketing_v2_normalize_allowed_programs($raw, string $legacy_program = ''): array
    {
        if (function_exists('bvmgr_ticketing_claims_normalize_allowed_programs')) {
            return bvmgr_ticketing_claims_normalize_allowed_programs($raw, $legacy_program);
        }

        $programs = bvmgr_ticketing_v2_sanitize_program_list($raw);
        if (!empty($programs)) {
            return $programs;
        }

        $legacy_key = sanitize_key($legacy_program);
        if ($legacy_key === '') {
            return array();
        }
        return bvmgr_ticketing_v2_sanitize_program_list(array($legacy_key));
    }
}

if (!function_exists('bvmgr_ticketing_v2_truthy')) {
    /**
     * @param mixed $value
     */
    function bvmgr_ticketing_v2_truthy($value, bool $default = false): bool
    {
        if (function_exists('bvmgr_ticketing_claims_truthy')) {
            return bvmgr_ticketing_claims_truthy($value, $default);
        }

        $raw = strtolower(trim((string) $value));
        if ($raw === '') {
            return $default;
        }
        if (in_array($raw, array('0', 'false', 'no', 'off'), true)) {
            return false;
        }
        return true;
    }
}



function bvmgr_ticketing_v2_ensure_tec_event_link(int $plan_id): array {
    $plan_id = absint($plan_id);
    $linked_tec_event_id = 0;
    $trace = function_exists('bvmgr_event_plan_perf_span_start')
        ? bvmgr_event_plan_perf_span_start('vms_ticketing_v2_ensure_tec_event_link', $plan_id, array('job_name' => 'tec_event_link'))
        : '';

    try {
    if ($plan_id <= 0) {
        return array('ok' => false, 'message' => __('Invalid event plan.', 'backstage-venue-manager'));
    }
	if (function_exists('bvmgr_event_plan_is_externally_ticketed') && bvmgr_event_plan_is_externally_ticketed($plan_id)) {
		return array(
			'ok' => false,
			'message' => __('External Ticketing is active. Native ticket synchronization is disabled; publish or re-sync the Event Plan to update its normal public calendar event.', 'backstage-venue-manager'),
			'code' => 'external_ticketing',
		);
	}

    $k_id  = bvmgr_ticketing_b_meta_key('tec_event_id', '_vms_tec_event_id');
    $k_url = bvmgr_ticketing_b_meta_key('tec_event_url', '_vms_tec_event_url');
    if (function_exists('bvmgr_event_plan_capture_actor_user_id')) {
        bvmgr_event_plan_capture_actor_user_id($plan_id, (int) get_current_user_id(), 'ticketing_v2_ensure_tec_event_link');
    }

    $existing = (int) get_post_meta($plan_id, $k_id, true);
    if ($existing > 0 && get_post_status($existing)) {
        $linked_tec_event_id = $existing;
        if (function_exists('bvmgr_event_plan_backfill_tec_event_author')) {
            bvmgr_event_plan_backfill_tec_event_author($plan_id, $existing, 'vms_ticketing_v2_ensure_tec_event_link');
        }
        $existing_permalink = get_permalink($existing);
        if (is_string($existing_permalink) && $existing_permalink !== '') {
            update_post_meta($plan_id, $k_url, esc_url_raw($existing_permalink));
        }
        return array('ok' => true, 'tec_event_id' => $existing, 'created' => false);
    }

    if (!function_exists('tribe_create_event')) {
        return array(
            'ok'      => false,
            'message' => __('The Events Calendar is required to create an event for tickets. Please install/activate The Events Calendar and try again.', 'backstage-venue-manager'),
        );
    }

    if (!function_exists('bvmgr_build_tec_event_args')) {
        return array(
            'ok'      => false,
            'message' => __('Backstage Venue Manager could not build the calendar event payload (internal missing function).', 'backstage-venue-manager'),
        );
    }

    $args = bvmgr_build_tec_event_args($plan_id);
    if (empty($args) || empty($args['EventStartDate']) || empty($args['EventEndDate'])) {
        return array(
            'ok'      => false,
            'message' => __('Save the Event Date and Times first, then try again.', 'backstage-venue-manager'),
        );
    }

    // Create as draft (unpublished). Publishing happens when the plan is published.
    $args['post_status'] = 'draft';
    if (function_exists('bvmgr_event_plan_apply_tec_author_args')) {
        $args = bvmgr_event_plan_apply_tec_author_args($plan_id, $args, 0, 'vms_ticketing_v2_ensure_tec_event_link');
    }

    $new_id = tribe_create_event($args);
    if (!$new_id || is_wp_error($new_id)) {
        $msg = is_wp_error($new_id) ? $new_id->get_error_message() : 'Unknown error';
        return array(
            'ok'      => false,
            /* translators: %s: failed to create the calendar event. */
            'message' => sprintf(__('Failed to create the calendar event: %s', 'backstage-venue-manager'), $msg),
        );
    }

    $tec_event_id = (int) $new_id;
    $linked_tec_event_id = $tec_event_id;
    update_post_meta($plan_id, $k_id, $tec_event_id);

    if (function_exists('bvmgr_event_plan_backfill_tec_event_author')) {
        bvmgr_event_plan_backfill_tec_event_author($plan_id, $tec_event_id, 'vms_ticketing_v2_ensure_tec_event_link');
    }

    $permalink = get_permalink($tec_event_id);
    if (is_string($permalink) && $permalink !== '') {
        update_post_meta($plan_id, $k_url, esc_url_raw($permalink));
    }

    // Do not auto-assign organizer; keep derived entities clean.
    delete_post_meta($tec_event_id, '_EventOrganizerID');

    return array('ok' => true, 'tec_event_id' => $tec_event_id, 'created' => true);
    } finally {
        if (function_exists('bvmgr_event_plan_perf_span_finish')) {
            bvmgr_event_plan_perf_span_finish(
                'vms_ticketing_v2_ensure_tec_event_link',
                $plan_id,
                $trace,
                array(
                    'job_name' => 'tec_event_link',
                    'linked_tec_event_id' => $linked_tec_event_id,
                )
            );
        }
    }
}

function bvmgr_ticketing_v2_get_config(int $plan_id): array {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
		return bvmgr_ticketing_v2_default_config(0);
    }

    $raw = get_post_meta($plan_id, bvmgr_ticketing_v2_k('config'), true);
    if (!is_array($raw)) {
		return bvmgr_ticketing_v2_hydrate_legacy_primary_ticket_image(bvmgr_ticketing_v2_default_config($plan_id), $plan_id);
    }
    return bvmgr_ticketing_v2_hydrate_legacy_primary_ticket_image(bvmgr_ticketing_v2_normalize_config($raw, $plan_id), $plan_id);
}

function bvmgr_ticketing_v2_get_saved_config(int $plan_id): array {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return array();
    }

    $raw = get_post_meta($plan_id, bvmgr_ticketing_v2_k('config'), true);
    if (!is_array($raw)) {
        return array();
    }

    return bvmgr_ticketing_v2_hydrate_legacy_primary_ticket_image(bvmgr_ticketing_v2_normalize_config($raw, $plan_id), $plan_id);
}

function bvmgr_ticketing_v2_normalize_sales_window_value(string $value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
    $formats = array(
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d\TH:i:s',
        'Y-m-d\TH:i',
    );

    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat('!' . $format, $value, $tz);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->setTimezone($tz)->format('Y-m-d H:i:s');
        }
    }

    try {
        $dt = new DateTimeImmutable($value, $tz);
        return $dt->setTimezone($tz)->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return '';
    }
}

function bvmgr_ticketing_v2_normalize_relative_days($value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }
    if (!preg_match('/^\d+$/', $raw)) {
        return '';
    }
    $days = min(3650, max(0, absint($raw)));
    return (string) $days;
}

function bvmgr_ticketing_v2_plan_time_to_datetime(int $plan_id, string $time_key, bool $is_end = false): string {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return '';
    }

    $event_date = trim((string) get_post_meta($plan_id, '_vms_event_date', true));
    $time_raw = trim((string) get_post_meta($plan_id, $time_key, true));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date) || !preg_match('/^\d{2}:\d{2}$/', $time_raw)) {
        return '';
    }

    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $event_date . ' ' . $time_raw, $tz);
    if (!$dt instanceof DateTimeImmutable) {
        return '';
    }

    if ($is_end) {
        $start_raw = trim((string) get_post_meta($plan_id, '_vms_start_time', true));
        if (preg_match('/^\d{2}:\d{2}$/', $start_raw)) {
            $start_dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $event_date . ' ' . $start_raw, $tz);
            if ($start_dt instanceof DateTimeImmutable && $dt->getTimestamp() <= $start_dt->getTimestamp()) {
                $dt = $dt->modify('+1 day');
            }
        }
    }

    return $dt->format('Y-m-d H:i:s');
}

function bvmgr_ticketing_v2_get_plan_event_anchor_datetimes(int $plan_id): array {
    $plan_id = absint($plan_id);
    $anchors = array(
        'event_start' => '',
        'event_end' => '',
    );
    if ($plan_id <= 0) {
        return $anchors;
    }

    $anchors['event_start'] = bvmgr_ticketing_v2_plan_time_to_datetime($plan_id, '_vms_start_time', false);
    $anchors['event_end'] = bvmgr_ticketing_v2_plan_time_to_datetime($plan_id, '_vms_end_time', true);

    $tec_event_id = bvmgr_ticketing_b_get_linked_tec_event_id($plan_id);
    if ($anchors['event_start'] === '' && $tec_event_id > 0) {
        $anchors['event_start'] = bvmgr_ticketing_v2_normalize_sales_window_value(bvmgr_ticketing_b_get_tec_event_start($tec_event_id));
    }
    if ($anchors['event_end'] === '' && $tec_event_id > 0 && function_exists('bvmgr_ticketing_b_get_tec_event_end')) {
        $anchors['event_end'] = bvmgr_ticketing_v2_normalize_sales_window_value(bvmgr_ticketing_b_get_tec_event_end($tec_event_id));
    }
    if ($anchors['event_end'] === '') {
        $anchors['event_end'] = $anchors['event_start'];
    }

    return $anchors;
}

function bvmgr_ticketing_v2_relative_days_before_datetime(string $anchor_datetime, string $relative_days): string {
    $anchor_datetime = bvmgr_ticketing_v2_normalize_sales_window_value($anchor_datetime);
    $relative_days = bvmgr_ticketing_v2_normalize_relative_days($relative_days);
    if ($anchor_datetime === '' || $relative_days === '') {
        return '';
    }

    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
    try {
        $dt = new DateTimeImmutable($anchor_datetime, $tz);
        if ((int) $relative_days > 0) {
            $dt = $dt->modify('-' . (int) $relative_days . ' days');
        }
        return $dt->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return '';
    }
}

function bvmgr_ticketing_v2_apply_relative_and_guarded_ticket_dates(array $ticket, array $anchors): array {
    $event_start = bvmgr_ticketing_v2_normalize_sales_window_value((string) ($anchors['event_start'] ?? ''));
    $event_end = bvmgr_ticketing_v2_normalize_sales_window_value((string) ($anchors['event_end'] ?? ''));

    $relative_map = array(
        'early_price_start' => array('relative_key' => 'early_price_start_relative_days', 'anchor' => $event_start),
        'early_price_end' => array('relative_key' => 'early_price_end_relative_days', 'anchor' => $event_start),
        'sales_start' => array('relative_key' => 'sales_start_relative_days', 'anchor' => $event_start),
        'sales_end' => array('relative_key' => 'sales_end_relative_days', 'anchor' => $event_end !== '' ? $event_end : $event_start),
    );

    foreach ($relative_map as $date_key => $row) {
        $relative_key = (string) ($row['relative_key'] ?? '');
        $relative_days = bvmgr_ticketing_v2_normalize_relative_days($ticket[$relative_key] ?? '');
        $ticket[$relative_key] = $relative_days;
        if ($relative_days === '') {
            continue;
        }
        $resolved = bvmgr_ticketing_v2_relative_days_before_datetime((string) ($row['anchor'] ?? ''), $relative_days);
        if ($resolved !== '') {
            $ticket[$date_key] = $resolved;
        }
    }

    if ($event_end !== '') {
        $sales_end = bvmgr_ticketing_v2_normalize_sales_window_value((string) ($ticket['sales_end'] ?? ''));
        if ($sales_end !== '' && strcmp($sales_end, $event_end) > 0) {
            $ticket['sales_end'] = $event_end;
        }
    }

    return $ticket;
}

function bvmgr_ticketing_v2_get_plan_sales_window_defaults(int $plan_id): array {
    $plan_id = absint($plan_id);
    $tz = wp_timezone();

    $sales_start = wp_date('Y-m-d H:i:s', time(), $tz);
    $sales_end = '';

    if ($plan_id > 0) {
        $anchors = bvmgr_ticketing_v2_get_plan_event_anchor_datetimes($plan_id);
        $sales_end = bvmgr_ticketing_v2_normalize_sales_window_value((string) ($anchors['event_end'] ?? ''));
        if ($sales_end === '') {
            $sales_end = bvmgr_ticketing_v2_normalize_sales_window_value((string) ($anchors['event_start'] ?? ''));
        }
    }

    return array(
        'sales_start' => $sales_start,
        'sales_end' => $sales_end,
    );
}

function bvmgr_ticketing_v2_get_product_sales_window(int $product_id): array {
    $product_id = absint($product_id);
    if ($product_id <= 0 || get_post_type($product_id) !== 'product') {
        return array(
            'sales_start' => '',
            'sales_end' => '',
            'sales_start_relative_days' => '',
            'sales_end_relative_days' => '0',
        );
    }

    return array(
        'sales_start' => bvmgr_ticketing_v2_normalize_sales_window_value((string) get_post_meta($product_id, '_ticket_start_date', true)),
        'sales_end' => bvmgr_ticketing_v2_normalize_sales_window_value((string) get_post_meta($product_id, '_ticket_end_date', true)),
    );
}

function bvmgr_ticketing_v2_guess_sales_window_product_id(int $plan_id, array $ticket_row, int $ticket_index, array $sync_map, array $existing_ticket_pids): int {
    $plan_id = absint($plan_id);
    $ticket_index = max(0, $ticket_index);

    $ticket_key = sanitize_key((string) ($ticket_row['ticket_key'] ?? $ticket_row['key'] ?? ''));
    if ($ticket_key !== '' && !empty($sync_map['tickets'][$ticket_key]) && is_array($sync_map['tickets'][$ticket_key])) {
        $mapped_pid = absint($sync_map['tickets'][$ticket_key]['woo_product_id'] ?? 0);
        if ($mapped_pid > 0 && get_post_type($mapped_pid) === 'product' && (string) get_post_status($mapped_pid) !== 'trash') {
            return $mapped_pid;
        }
    }

    if ($ticket_index === 0 && !empty($sync_map['ga']) && is_array($sync_map['ga'])) {
        $legacy_ga_pid = absint($sync_map['ga']['woo_product_id'] ?? 0);
        if ($legacy_ga_pid > 0 && get_post_type($legacy_ga_pid) === 'product' && (string) get_post_status($legacy_ga_pid) !== 'trash') {
            return $legacy_ga_pid;
        }
    }

    $ticket_title = trim((string) ($ticket_row['title'] ?? $ticket_row['label'] ?? ''));
    if ($ticket_title !== '' && !empty($existing_ticket_pids)) {
        $matched_pid = bvmgr_ticketing_b_find_match_by_title($existing_ticket_pids, $ticket_title, array(
            'plan_id' => $plan_id,
            'tec_event_id' => $tec_event_id,
            'ticket_key' => $ticket_key,
        ));
        if ($matched_pid > 0) {
            return $matched_pid;
        }
    }

    return 0;
}

function bvmgr_ticketing_v2_hydrate_missing_sales_windows(array $cfg, int $plan_id): array {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return $cfg;
    }

    $tickets = (isset($cfg['tickets']) && is_array($cfg['tickets'])) ? array_values($cfg['tickets']) : array();
    if (empty($tickets)) {
        return $cfg;
    }

    $defaults = bvmgr_ticketing_v2_get_plan_sales_window_defaults($plan_id);
    $sync = bvmgr_ticketing_v2_get_sync($plan_id);
    $sync_map = (isset($sync['map']) && is_array($sync['map'])) ? $sync['map'] : array();

    $tec_event_id = bvmgr_ticketing_b_get_linked_tec_event_id($plan_id);
    $existing_ticket_pids = ($tec_event_id > 0)
        ? array_values(array_filter(array_map('absint', bvmgr_ticketing_b_get_event_ticket_products($tec_event_id))))
        : array();

    foreach ($tickets as $idx => $ticket_row) {
        if (!is_array($ticket_row)) {
            continue;
        }

        $sales_start = bvmgr_ticketing_v2_normalize_sales_window_value((string) ($ticket_row['sales_start'] ?? ''));
        $sales_end = bvmgr_ticketing_v2_normalize_sales_window_value((string) ($ticket_row['sales_end'] ?? ''));

        if ($sales_start === '' || $sales_end === '') {
            $product_id = bvmgr_ticketing_v2_guess_sales_window_product_id($plan_id, $ticket_row, $idx, $sync_map, $existing_ticket_pids);
            if ($product_id > 0) {
                $product_window = bvmgr_ticketing_v2_get_product_sales_window($product_id);
                if ($sales_start === '' && $product_window['sales_start'] !== '') {
                    $sales_start = $product_window['sales_start'];
                }
                if ($sales_end === '' && $product_window['sales_end'] !== '') {
                    $sales_end = $product_window['sales_end'];
                }
            }
        }

        if ($sales_start === '' && $defaults['sales_start'] !== '') {
            $sales_start = $defaults['sales_start'];
        }
        if ($sales_end === '' && $defaults['sales_end'] !== '') {
            $sales_end = $defaults['sales_end'];
        }

        $tickets[$idx]['sales_start'] = $sales_start;
        $tickets[$idx]['sales_end'] = $sales_end;
    }

    $cfg['tickets'] = $tickets;

    $primary_ticket = $tickets[0];
    foreach ($tickets as $ticket_row) {
        if (!empty($ticket_row['counts_toward_unlock'])) {
            $primary_ticket = $ticket_row;
            break;
        }
    }

    if (!isset($cfg['ga']) || !is_array($cfg['ga'])) {
        $cfg['ga'] = array();
    }
    $cfg['ga']['sales_start'] = (string) ($primary_ticket['sales_start'] ?? '');
    $cfg['ga']['sales_end'] = (string) ($primary_ticket['sales_end'] ?? '');

    return $cfg;
}

function bvmgr_ticketing_v2_get_admin_config(int $plan_id): array {
    return bvmgr_ticketing_v2_hydrate_missing_sales_windows(bvmgr_ticketing_v2_get_config($plan_id), $plan_id);
}

function bvmgr_ticketing_v2_get_from_price_for_display(int $plan_id): ?float {
    $cfg = bvmgr_ticketing_v2_get_saved_config($plan_id);
    $tickets = (isset($cfg['tickets']) && is_array($cfg['tickets'])) ? $cfg['tickets'] : array();
    if (empty($tickets)) {
        return null;
    }

    $public_standard = array();
    $public_verified_only = array();

    foreach ($tickets as $ticket) {
        if (!is_array($ticket) || empty($ticket['enabled'])) {
            continue;
        }

        $visibility = sanitize_key((string) ($ticket['visibility_mode'] ?? 'public'));
        if (!in_array($visibility, array('public', 'verified'), true)) {
            continue;
        }

        $price = bvmgr_ticketing_v2_get_ticket_effective_price($ticket);
        if ($price <= 0) {
            continue;
        }

        $legacy_program = sanitize_key((string) ($ticket['verified_program'] ?? ''));
        $allowed_programs = bvmgr_ticketing_v2_normalize_allowed_programs($ticket['allowed_programs'] ?? array(), $legacy_program);
        $allow_direct_grants = bvmgr_ticketing_v2_truthy($ticket['allow_direct_grants'] ?? false, false);
        $is_verified_only = ($visibility === 'verified' || !empty($allowed_programs) || $allow_direct_grants);
        if ($is_verified_only) {
            $public_verified_only[] = $price;
        } else {
            $public_standard[] = $price;
        }
    }

    if (!empty($public_standard)) {
        return (float) min($public_standard);
    }
    if (!empty($public_verified_only)) {
        return (float) min($public_verified_only);
    }

    return null;
}

function bvmgr_ticketing_v2_set_config(int $plan_id, array $config): void {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return;
    }

    $config = bvmgr_ticketing_v2_normalize_config($config, $plan_id);
    $meta_key = bvmgr_ticketing_v2_k('config');
    $current_raw = get_post_meta($plan_id, $meta_key, true);

    // 0.2.24.656 performance guard: avoid a no-op update_post_meta() call.
    // WordPress metadata filters fire before the native no-change check, and VMS
    // ticket mutation audit builds before/after snapshots for ticket config writes.
    // Skipping truly unchanged saves here prevents expensive audit/snapshot work
    // during repeated editor saves, Draft, Ready, and guarded save retries.
    if (is_array($current_raw)) {
        $current_norm = bvmgr_ticketing_v2_normalize_config($current_raw, $plan_id);
        if (function_exists('bvmgr_ticketing_v2_hash_config_for_sync')) {
            $current_hash = bvmgr_ticketing_v2_hash_config_for_sync($current_norm);
            $new_hash = bvmgr_ticketing_v2_hash_config_for_sync($config);
            if ($current_hash !== '' && hash_equals($current_hash, $new_hash)) {
                $GLOBALS['bvmgr_ticketing_v2_last_set_config_noop'] = array(
                    'plan_id' => $plan_id,
                    'config_hash' => $new_hash,
                    'reason' => 'unchanged_config_hash',
                );
                return;
            }
        } elseif (maybe_serialize($current_norm) === maybe_serialize($config)) {
            $GLOBALS['bvmgr_ticketing_v2_last_set_config_noop'] = array(
                'plan_id' => $plan_id,
                'config_hash' => '',
                'reason' => 'unchanged_serialized_config',
            );
            return;
        }
    }

    $GLOBALS['bvmgr_ticketing_v2_last_set_config_noop'] = array(
        'plan_id' => $plan_id,
        'config_hash' => function_exists('bvmgr_ticketing_v2_hash_config_for_sync') ? bvmgr_ticketing_v2_hash_config_for_sync($config) : '',
        'reason' => 'updated',
    );

    bvmgr_ticketing_v2_sync_legacy_primary_ticket_image_meta($plan_id, $config);
    update_post_meta($plan_id, $meta_key, $config);
}

function bvmgr_ticketing_v2_get_sync(int $plan_id): array {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return array();
    }
    $raw = get_post_meta($plan_id, bvmgr_ticketing_v2_k('sync'), true);
    return is_array($raw) ? $raw : array();
}

function bvmgr_ticketing_v2_set_sync(int $plan_id, array $sync): void {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return;
    }
    update_post_meta($plan_id, bvmgr_ticketing_v2_k('sync'), $sync);
}

function bvmgr_ticketing_v2_get_stats(int $plan_id): array {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return array();
    }
    $raw = get_post_meta($plan_id, bvmgr_ticketing_v2_k('stats'), true);
    return is_array($raw) ? $raw : array();
}

function bvmgr_ticketing_v2_collect_sync_map_product_ids(array $sync_map): array {
    $ids = array();

    if (isset($sync_map['tickets']) && is_array($sync_map['tickets'])) {
        foreach ($sync_map['tickets'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $pid = absint($row['woo_product_id'] ?? 0);
            if ($pid > 0) {
                $ids[] = $pid;
            }
        }
    }

    if (isset($sync_map['ga']) && is_array($sync_map['ga'])) {
        $ga_pid = absint($sync_map['ga']['woo_product_id'] ?? 0);
        if ($ga_pid > 0) {
            $ids[] = $ga_pid;
        }
    }

    if (isset($sync_map['entitlements']) && is_array($sync_map['entitlements'])) {
        foreach ($sync_map['entitlements'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $pid = absint($row['woo_product_id'] ?? 0);
            if ($pid > 0) {
                $ids[] = $pid;
            }
        }
    }

    $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
    sort($ids, SORT_NUMERIC);
    return $ids;
}

/**
 * Reconcile canonical ticket IDs/stats caches for Event Plan ticketing.
 *
 * This keeps canonical post meta in sync with V2 mappings while preserving the
 * explicit-refresh rule for sold/revenue (no background money recomputation).
 */
function bvmgr_ticketing_v2_reconcile_event_plan_ticket_cache(int $plan_id, int $tec_event_id, array $sync_map, bool $persist = true): array {
    $plan_id = absint($plan_id);
    $tec_event_id = absint($tec_event_id);

    if ($plan_id <= 0) {
        return array(
            'sync_status' => 'mismatch',
            'warnings' => array('Invalid Event Plan ID for ticket reconciliation.'),
            'persist_ok' => false,
            'ticket_product_ids' => array(),
            'computed_at_gmt' => time(),
        );
    }

    $format_ids = static function (array $ids): string {
        $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        if (empty($ids)) {
            return '';
        }
        sort($ids, SORT_NUMERIC);
        if (count($ids) > 10) {
            $head = array_slice($ids, 0, 10);
            return implode(', ', $head) . ' +' . (count($ids) - 10) . ' more';
        }
        return implode(', ', $ids);
    };

    $mapped_all = bvmgr_ticketing_v2_collect_sync_map_product_ids($sync_map);
    $mapped_valid = array();
    $mapped_missing = array();
    $mapped_trashed = array();
    $mapped_not_product = array();
    $mapped_marker_mismatch = array();

    $k_product_plan = bvmgr_ticketing_v2_product_meta_key('event_plan_id');
    $k_product_tec  = bvmgr_ticketing_v2_product_meta_key('tec_event_id');

    foreach ($mapped_all as $pid) {
        $pid = absint($pid);
        if ($pid <= 0) {
            continue;
        }

        $ptype = get_post_type($pid);
        if ($ptype === '' || $ptype === false || $ptype === null) {
            $mapped_missing[] = $pid;
            continue;
        }
        if ($ptype !== 'product') {
            $mapped_not_product[] = $pid;
            continue;
        }
        $post_status = (string) get_post_status($pid);
        if ($post_status === 'trash') {
            $mapped_trashed[] = $pid;
            continue;
        }

        $marker_plan = $k_product_plan !== '' ? absint(get_post_meta($pid, $k_product_plan, true)) : 0;
        $marker_tec  = $k_product_tec !== '' ? absint(get_post_meta($pid, $k_product_tec, true)) : 0;

        if ($marker_plan > 0 && $marker_plan !== $plan_id) {
            $mapped_marker_mismatch[] = $pid;
            continue;
        }
        if ($tec_event_id > 0 && $marker_tec > 0 && $marker_tec !== $tec_event_id) {
            $mapped_marker_mismatch[] = $pid;
            continue;
        }

        $mapped_valid[] = $pid;
    }

    $mapped_valid = array_values(array_unique(array_filter(array_map('absint', $mapped_valid))));
    sort($mapped_valid, SORT_NUMERIC);

    $detected = ($tec_event_id > 0)
        ? bvmgr_ticketing_b_get_event_ticket_products($tec_event_id)
        : array();
    $detected = array_values(array_unique(array_filter(array_map('absint', (array) $detected))));
    sort($detected, SORT_NUMERIC);

    $k_manual = bvmgr_ticketing_b_meta_key('ticket_manual_product_ids', '_vms_ticket_manual_product_ids_v1');
    $manual = get_post_meta($plan_id, $k_manual, true);
    if (!is_array($manual)) {
        $manual = array();
    }
    $manual = array_values(array_unique(array_filter(array_map('absint', $manual))));
    sort($manual, SORT_NUMERIC);

    $canonical_ids = array_values(array_unique(array_filter(array_map('absint', array_merge($mapped_valid, $detected, $manual)))));
    sort($canonical_ids, SORT_NUMERIC);

    $detected_unmapped = array_values(array_diff($detected, $mapped_valid));
    sort($detected_unmapped, SORT_NUMERIC);

    $warnings = array();
    if (!empty($mapped_missing)) {
        $warnings[] = sprintf(
            /* translators: %s: mapped ticket products are missing. */
            __('Mapped ticket products are missing: %s. Run Preview → Commit to repair mappings.', 'backstage-venue-manager'),
            $format_ids($mapped_missing)
        );
    }
    if (!empty($mapped_trashed)) {
        $warnings[] = sprintf(
            /* translators: %s: mapped ticket products are in trash. */
            __('Mapped ticket products are in Trash: %s. Run Preview → Commit to repair mappings.', 'backstage-venue-manager'),
            $format_ids($mapped_trashed)
        );
    }
    if (!empty($mapped_not_product)) {
        $warnings[] = sprintf(
            /* translators: %s: mapped ticket ids are not woo products. */
            __('Mapped ticket IDs are not Woo products: %s. Run Preview → Commit to repair mappings.', 'backstage-venue-manager'),
            $format_ids($mapped_not_product)
        );
    }
    if (!empty($mapped_marker_mismatch)) {
        $warnings[] = sprintf(
            /* translators: %s: mapped ticket products have marker mismatches. */
            __('Mapped ticket products have marker mismatches: %s. Run Preview → Commit to restamp canonical IDs.', 'backstage-venue-manager'),
            $format_ids($mapped_marker_mismatch)
        );
    }
    if (!empty($detected_unmapped)) {
        $warnings[] = sprintf(
            /* translators: %s: comma-separated linked TEC ticket product IDs not tracked in the VMS sync map. */
            __('Linked TEC event has ticket products not tracked in VMS sync map: %s. Preview before commit to reconcile.', 'backstage-venue-manager'),
            $format_ids($detected_unmapped)
        );
    }

    $computed_at = time();
    $sync_status = empty($warnings) ? 'ok' : 'mismatch';

    $stats_v2 = array(
        'version' => 2,
        'provider' => 'pending_refresh',
        'sync_status' => $sync_status,
        'tec_event_id' => $tec_event_id,
        'ticket_product_ids' => $canonical_ids,
        'mapped_ticket_product_ids' => $mapped_valid,
        'detected_ticket_product_ids' => $detected,
        'manual_product_ids' => $manual,
        'mapped_missing_product_ids' => array_values(array_unique(array_filter(array_map('absint', $mapped_missing)))),
        'mapped_trashed_product_ids' => array_values(array_unique(array_filter(array_map('absint', $mapped_trashed)))),
        'mapped_not_product_ids' => array_values(array_unique(array_filter(array_map('absint', $mapped_not_product)))),
        'mapped_marker_mismatch_product_ids' => array_values(array_unique(array_filter(array_map('absint', $mapped_marker_mismatch)))),
        'detected_unmapped_product_ids' => $detected_unmapped,
        'warnings' => $warnings,
        'computed_at_gmt' => $computed_at,
        'note' => __('Ticket IDs were reconciled from Ticketing v2 sync. Click “Refresh ticket stats” to update sold/revenue totals.', 'backstage-venue-manager'),
    );

    $stats_v1 = array(
        'provider' => 'pending_refresh',
        'revenue_label' => __('Ticket IDs were reconciled from Ticketing v2 sync. Click “Refresh ticket stats” to update sold/revenue totals.', 'backstage-venue-manager'),
        'currency' => function_exists('get_woocommerce_currency') ? (string) get_woocommerce_currency() : '',
        'computed_at_gmt' => $computed_at,
        'sync_status' => $sync_status,
        'ticket_product_ids' => $canonical_ids,
    );
    if (!empty($warnings)) {
        $stats_v1['warnings'] = $warnings;
    }

    $persist_ok = true;
    $persist_failures = array();

    if ($persist) {
        $k_pids = bvmgr_ticketing_b_meta_key('ticket_product_ids', '_vms_ticket_product_ids_v1');
        $k_stat = bvmgr_ticketing_b_meta_key('ticket_stats', '_vms_ticket_stats_v1');

        update_post_meta($plan_id, $k_pids, $canonical_ids);
        update_post_meta($plan_id, $k_stat, $stats_v1);
        update_post_meta($plan_id, bvmgr_ticketing_v2_k('stats'), $stats_v2);

        $saved_ids = get_post_meta($plan_id, $k_pids, true);
        if (!is_array($saved_ids)) {
            $saved_ids = array();
        }
        $saved_ids = array_values(array_unique(array_filter(array_map('absint', $saved_ids))));
        sort($saved_ids, SORT_NUMERIC);
        if ($saved_ids !== $canonical_ids) {
            $persist_ok = false;
            $persist_failures[] = 'ticket_product_ids';
        }

        $saved_stats_v2 = bvmgr_ticketing_v2_get_stats($plan_id);
        $saved_status = is_array($saved_stats_v2) ? (string) ($saved_stats_v2['sync_status'] ?? '') : '';
        if ($saved_status === '') {
            $persist_ok = false;
            $persist_failures[] = 'ticketing_stats_v2';
        }

        if (!$persist_ok) {
            $persist_failures = array_values(array_unique(array_filter(array_map('sanitize_key', $persist_failures))));
            $warnings[] = sprintf(
                /* translators: %s: ticket reconciliation was applied, but persistence verification failed for. */
                __('Ticket reconciliation was applied, but persistence verification failed for: %s. Refresh this page and verify canonical ticket IDs.', 'backstage-venue-manager'),
                implode(', ', $persist_failures)
            );
            $sync_status = 'mismatch';
            $stats_v2['sync_status'] = $sync_status;
            $stats_v2['warnings'] = $warnings;
            $stats_v1['sync_status'] = $sync_status;
            $stats_v1['warnings'] = $warnings;
            update_post_meta($plan_id, $k_stat, $stats_v1);
            update_post_meta($plan_id, bvmgr_ticketing_v2_k('stats'), $stats_v2);
        }
    }

    return array(
        'sync_status' => $sync_status,
        'warnings' => $warnings,
        'persist_ok' => $persist_ok,
        'persist_failures' => $persist_failures,
        'ticket_product_ids' => $canonical_ids,
        'mapped_ticket_product_ids' => $mapped_valid,
        'detected_ticket_product_ids' => $detected,
        'manual_product_ids' => $manual,
        'computed_at_gmt' => $computed_at,
    );
}

function bvmgr_ticketing_v2_default_ent_id(int $plan_id, string $key): string {
    $plan_id = absint($plan_id);
    $key = sanitize_key($key);
    if ($key === "") {
        return "ent_" . wp_generate_password(10, false, false);
    }
    // Deterministic ID so Preview/Commit comparisons do not thrash when older configs are missing IDs.
    return "ent_" . substr(sha1("v2|" . $plan_id . "|" . $key), 0, 12);
}



function bvmgr_ticketing_v2_enabled_entitlement_sequence_warnings(array $entitlements): array {
    $groups = array();

    foreach ($entitlements as $ent) {
        if (!is_array($ent) || empty($ent['enabled'])) {
            continue;
        }
        $label = bvmgr_ticketing_v2_sanitize_plain_text_label($ent['label'] ?? '');
        if ($label === '') {
            continue;
        }
        if (!preg_match('/^(.+?)\s*#\s*(\d+)\b/u', $label, $m)) {
            continue;
        }

        $prefix = trim(preg_replace('/\s+/u', ' ', (string) $m[1]));
        $number_raw = (string) $m[2];
        $number = (int) $number_raw;
        if ($prefix === '' || $number <= 0) {
            continue;
        }
        $key = strtolower($prefix);
        if (!isset($groups[$key])) {
            $groups[$key] = array(
                'prefix' => $prefix,
                'pad' => strlen($number_raw),
                'numbers' => array(),
            );
        }
        $groups[$key]['pad'] = max((int) $groups[$key]['pad'], strlen($number_raw));
        $groups[$key]['numbers'][$number] = true;
    }

    $warnings = array();
    foreach ($groups as $group) {
        $numbers = array_keys((array) ($group['numbers'] ?? array()));
        $numbers = array_values(array_filter(array_map('intval', $numbers)));
        if (count($numbers) < 2) {
            continue;
        }
        sort($numbers, SORT_NUMERIC);
        $min = (int) reset($numbers);
        $max = (int) end($numbers);
        if ($max <= $min || ($max - $min) > 50) {
            continue;
        }

        $missing = array();
        $present = array_fill_keys($numbers, true);
        for ($i = $min; $i <= $max; $i++) {
            if (!isset($present[$i])) {
                $missing[] = str_pad((string) $i, max(1, (int) ($group['pad'] ?? 1)), '0', STR_PAD_LEFT);
            }
        }
        if (empty($missing)) {
            continue;
        }

        $sample = array_slice($missing, 0, 5);
        $more = count($missing) > 5 ? ' +' . (count($missing) - 5) . ' more' : '';
        $warnings[] = sprintf(
            /* translators: 1: value 1 used in this message, 2: value 2 used in this message, 3: value 3 used in this message. */
            __('Enabled add-on labels appear to skip %1$s #%2$s%3$s. Review the saved config before committing if those add-ons should exist.', 'backstage-venue-manager'),
            (string) ($group['prefix'] ?? 'Add-on'),
            implode(', #', $sample),
            $more
        );
    }

    return $warnings;
}

function bvmgr_ticketing_v2_default_config(int $plan_id, bool $seed_legacy = false): array {
    $plan_id = absint($plan_id);

    // Deprecated compatibility arg; legacy field seeding is intentionally retired.
    if ($seed_legacy) {
        // Intentionally ignored.
    }

    // If a default template exists, use it as the canonical default config shape.
    // 0.2.24.656: when a template is auto-used for a fresh plan, do not carry over
    // stale event-specific sales_end dates from the template source event. Missing
    // windows are hydrated from this plan, and stale/unsafe ends are reset to
    // this plan's event end before the config reaches the editor.
    if (function_exists('bvmgr_ticketing_v2_get_default_template_id') && function_exists('bvmgr_ticketing_v2_templates_get_all')) {
        $template_id = (string) bvmgr_ticketing_v2_get_default_template_id();
        if ($template_id !== '') {
            $templates = bvmgr_ticketing_v2_templates_get_all();
            $template_cfg = $templates[$template_id]['config'] ?? null;
            if (is_array($template_cfg)) {
                $cfg = bvmgr_ticketing_v2_normalize_config($template_cfg, $plan_id);
                if ($plan_id > 0) {
                    $cfg = bvmgr_ticketing_v2_hydrate_missing_sales_windows($cfg, $plan_id);
                    $target_show_datetime = bvmgr_ticketing_v2_resolve_template_apply_show_datetime($plan_id, '');
                    if ($target_show_datetime !== '') {
                        $anchors = bvmgr_ticketing_v2_get_plan_event_anchor_datetimes($plan_id);
                        $cfg = bvmgr_ticketing_v2_reset_stale_sales_end_to_show(
                            $cfg,
                            $target_show_datetime,
                            (string) ($anchors['event_start'] ?? '')
                        );
                    }
                }
                return $cfg;
            }
        }
    }

    $ga_price = 20.0;
    $ga_label = 'GA Admission';

    $cfg = array(
        'version' => 2,
        'mode' => 'read_only',
        'provider' => 'tec_tickets_woo',
        'tickets' => array(
            array(
                'enabled' => true,
                'ticket_key' => 'ga',
                'title' => $ga_label,
                'description' => '',
                'price' => (string) $ga_price,
                'early_price' => '',
                'early_price_start' => '',
                'early_price_end' => '',
                'early_price_start_relative_days' => '',
                'early_price_end_relative_days' => '',
                'early_price_cap' => 0,
                'inventory_total' => 0,
                'visibility_mode' => 'public',
                'verified_program' => '',
                'allowed_programs' => array(),
                'allow_direct_grants' => false,
                'claim_grant_type' => 'event_ticket_eligibility',
                'claims_per_assignee' => 1,
                'require_assignee_email' => true,
                'counts_toward_unlock' => true,
                'max_qty_per_order' => 0,
                'ratio_rule_enabled' => false,
                'ratio_rule_max_per_qualifying' => 0,
                'ratio_rule_qualifier_mode' => 'counts_toward_unlock',
                'ratio_rule_group' => '',
                'sort_order' => 10,
                'sales_start' => '',
                'sales_end' => '',
                'sales_start_relative_days' => '',
                'sales_end_relative_days' => '0',
                'image_mode' => 'event_featured',
                'image_id' => 0,
            ),
        ),
        'ga' => array(
            'enabled' => true,
            'label' => $ga_label,
            'price' => (string) $ga_price,
            'early_price' => '',
            'early_price_start' => '',
            'early_price_end' => '',
            'early_price_start_relative_days' => '',
            'early_price_end_relative_days' => '',
            'early_price_cap' => 0,
            'capacity' => 0,
            'sales_start' => '',
            'sales_end' => '',
            'sales_start_relative_days' => '',
            'sales_end_relative_days' => '0',
        ),
        'entitlements' => array(),
        'square' => array(
            'ga' => array(
                'mode' => 'none',
                'item_id' => '',
                'variation_id' => '',
            ),
        ),
    );

    return bvmgr_ticketing_v2_normalize_config($cfg, $plan_id);
}

function bvmgr_ticketing_v2_primary_ticket_index(array $cfg): int {
    $tickets = (isset($cfg['tickets']) && is_array($cfg['tickets'])) ? array_values($cfg['tickets']) : array();
    if (empty($tickets)) {
        return -1;
    }

    foreach ($tickets as $idx => $ticket_row) {
        if (!is_array($ticket_row)) {
            continue;
        }
        if (!empty($ticket_row['counts_toward_unlock'])) {
            return (int) $idx;
        }
    }

    return 0;
}

function bvmgr_ticketing_v2_sync_legacy_primary_ticket_image_meta(int $plan_id, array $cfg): void {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return;
    }

    $ticket_index = bvmgr_ticketing_v2_primary_ticket_index($cfg);
    if ($ticket_index < 0 || !isset($cfg['tickets'][$ticket_index]) || !is_array($cfg['tickets'][$ticket_index])) {
        delete_post_meta($plan_id, '_vms_ticketing_ga_image_mode');
        delete_post_meta($plan_id, '_vms_ticketing_ga_image_id');
        return;
    }

    $ticket = $cfg['tickets'][$ticket_index];
    $mode = sanitize_key((string) ($ticket['image_mode'] ?? 'event_featured'));
    if (!in_array($mode, array('event_featured', 'custom', 'none'), true)) {
        $mode = 'event_featured';
    }

    $legacy_mode = ($mode === 'event_featured') ? 'event_plan' : $mode;
    update_post_meta($plan_id, '_vms_ticketing_ga_image_mode', $legacy_mode);

    if ($mode === 'custom') {
        $image_id = absint($ticket['image_id'] ?? 0);
        if ($image_id > 0) {
            update_post_meta($plan_id, '_vms_ticketing_ga_image_id', $image_id);
        } else {
            delete_post_meta($plan_id, '_vms_ticketing_ga_image_id');
        }
        return;
    }

    delete_post_meta($plan_id, '_vms_ticketing_ga_image_id');
}

function bvmgr_ticketing_v2_hydrate_legacy_primary_ticket_image(array $cfg, int $plan_id): array {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return $cfg;
    }

    $ticket_index = bvmgr_ticketing_v2_primary_ticket_index($cfg);
    if ($ticket_index < 0 || !isset($cfg['tickets'][$ticket_index]) || !is_array($cfg['tickets'][$ticket_index])) {
        return $cfg;
    }

    $legacy_mode = sanitize_key((string) get_post_meta($plan_id, '_vms_ticketing_ga_image_mode', true));
    $legacy_image_id = absint(get_post_meta($plan_id, '_vms_ticketing_ga_image_id', true));
    if ($legacy_mode === '' && $legacy_image_id <= 0) {
        return $cfg;
    }

    if ($legacy_mode === '') {
        $legacy_mode = ($legacy_image_id > 0) ? 'custom' : 'event_featured';
    } elseif ($legacy_mode === 'event_plan') {
        $legacy_mode = 'event_featured';
    }

    if (!in_array($legacy_mode, array('event_featured', 'custom', 'none'), true)) {
        $legacy_mode = 'event_featured';
    }

    $cfg['tickets'] = array_values($cfg['tickets']);
    $cfg['tickets'][$ticket_index]['image_mode'] = $legacy_mode;
    $cfg['tickets'][$ticket_index]['image_id'] = ($legacy_mode === 'custom') ? $legacy_image_id : 0;

    return $cfg;
}

function bvmgr_ticketing_v2_normalize_config(array $in, int $plan_id = 0): array {
    $plan_id = absint($plan_id);

    $mode = isset($in['mode']) ? (string) $in['mode'] : 'read_only';
    $mode = in_array($mode, array('none', 'read_only', 'vms_managed'), true) ? $mode : 'read_only';

    $ga_in = (isset($in['ga']) && is_array($in['ga'])) ? $in['ga'] : array();
    $date_anchors = bvmgr_ticketing_v2_get_plan_event_anchor_datetimes($plan_id);

    $tickets_in = (isset($in['tickets']) && is_array($in['tickets'])) ? $in['tickets'] : array();
    if (empty($tickets_in)) {
        // Backward compatibility: hydrate tickets from legacy single-GA config.
        $tickets_in[] = array(
            'enabled' => true,
            'ticket_key' => 'ga',
            'title' => isset($ga_in['label']) ? (string) $ga_in['label'] : 'GA Admission',
            'description' => '',
            'price' => isset($ga_in['price']) ? (string) $ga_in['price'] : '0',
            'early_price' => isset($ga_in['early_price']) ? (string) $ga_in['early_price'] : '',
            'early_price_start' => isset($ga_in['early_price_start']) ? (string) $ga_in['early_price_start'] : '',
            'early_price_end' => isset($ga_in['early_price_end']) ? (string) $ga_in['early_price_end'] : '',
            'early_price_start_relative_days' => isset($ga_in['early_price_start_relative_days']) ? (string) $ga_in['early_price_start_relative_days'] : '',
            'early_price_end_relative_days' => isset($ga_in['early_price_end_relative_days']) ? (string) $ga_in['early_price_end_relative_days'] : '',
            'early_price_cap' => max(0, absint($ga_in['early_price_cap'] ?? ($ga_in['early_price_limit'] ?? 0))),
            'inventory_total' => isset($ga_in['capacity']) ? (int) $ga_in['capacity'] : 0,
            'visibility_mode' => 'public',
            'verified_program' => '',
            'allowed_programs' => array(),
            'allow_direct_grants' => false,
            'claim_grant_type' => 'event_ticket_eligibility',
            'claims_per_assignee' => 1,
            'require_assignee_email' => true,
            'counts_toward_unlock' => true,
            'max_qty_per_order' => 0,
            'sort_order' => 10,
            'sales_start' => isset($ga_in['sales_start']) ? (string) $ga_in['sales_start'] : '',
            'sales_end' => isset($ga_in['sales_end']) ? (string) $ga_in['sales_end'] : '',
            'sales_start_relative_days' => isset($ga_in['sales_start_relative_days']) ? (string) $ga_in['sales_start_relative_days'] : '',
            'sales_end_relative_days' => isset($ga_in['sales_end_relative_days']) ? (string) $ga_in['sales_end_relative_days'] : '',
            'image_mode' => 'event_featured',
            'image_id' => 0,
        );
    }

    $ticket_out = array();
    $ticket_seen = array();
    $ticket_sort_fallback = 10;
    foreach ($tickets_in as $row) {
        if (!is_array($row)) {
            continue;
        }

        $title = isset($row['title']) ? bvmgr_ticketing_v2_sanitize_plain_text_label($row['title']) : '';
        if ($title === '') {
            $title = isset($row['label']) ? bvmgr_ticketing_v2_sanitize_plain_text_label($row['label']) : '';
        }
        $title = trim($title);
        if ($title === '') {
            continue;
        }

        $key = isset($row['ticket_key']) ? sanitize_key((string) $row['ticket_key']) : '';
        if ($key === '') {
            $key = isset($row['key']) ? sanitize_key((string) $row['key']) : '';
        }
        if ($key === '') {
            $key = sanitize_key($title);
        }
        if ($key === '') {
            $key = 'ticket_' . substr(sha1('v2|ticket|' . $plan_id . '|' . $title . '|' . $ticket_sort_fallback), 0, 12);
        }
        $base_key = $key;
        $key_idx = 2;
        while (isset($ticket_seen[$key])) {
            $key = $base_key . '_' . $key_idx;
            $key_idx++;
        }
        $ticket_seen[$key] = true;

        $price = bvmgr_ticketing_v2_money_string($row['price'] ?? '0');

        $early_price = bvmgr_ticketing_v2_money_string($row['early_price'] ?? '', '');
        $early_price_start = isset($row['early_price_start']) ? sanitize_text_field((string) $row['early_price_start']) : '';
        $early_price_end = isset($row['early_price_end']) ? sanitize_text_field((string) $row['early_price_end']) : '';
        $early_price_start_relative_days = bvmgr_ticketing_v2_normalize_relative_days($row['early_price_start_relative_days'] ?? '');
        $early_price_end_relative_days = bvmgr_ticketing_v2_normalize_relative_days($row['early_price_end_relative_days'] ?? '');
        $early_price_cap = max(0, absint($row['early_price_cap'] ?? ($row['early_price_limit'] ?? 0)));
        if ($early_price !== '' && (float) $early_price <= 0) {
            $early_price = '';
        }

        $inventory_total = array_key_exists('inventory_total', $row)
            ? (int) $row['inventory_total']
            : (array_key_exists('capacity', $row) ? (int) $row['capacity'] : 0);
        $inventory_total = max(0, $inventory_total);

        $visibility_mode = isset($row['visibility_mode']) ? sanitize_key((string) $row['visibility_mode']) : '';
        if (!in_array($visibility_mode, array('public', 'login', 'verified'), true)) {
            $visibility_mode = 'public';
        }

        $verified_program = isset($row['verified_program']) ? sanitize_key((string) $row['verified_program']) : '';
        if ($verified_program === '') {
            $verified_program = isset($row['qualification_code']) ? sanitize_key((string) $row['qualification_code']) : '';
        }
        $allowed_programs = bvmgr_ticketing_v2_normalize_allowed_programs($row['allowed_programs'] ?? array(), $verified_program);
        $allow_direct_grants = bvmgr_ticketing_v2_truthy($row['allow_direct_grants'] ?? false, false);
        $claim_grant_type = sanitize_key((string) ($row['claim_grant_type'] ?? 'event_ticket_eligibility'));
        $allowed_claim_grant_types = function_exists('bvmgr_ticketing_claims_allowed_grant_types')
            ? (array) bvmgr_ticketing_claims_allowed_grant_types()
            : array('event_ticket_eligibility', 'event_free_admit', 'credential_benefit_override', 'event_grant');
        if (!in_array($claim_grant_type, $allowed_claim_grant_types, true)) {
            $claim_grant_type = 'event_ticket_eligibility';
        }
        $claims_per_assignee = max(0, absint($row['claims_per_assignee'] ?? 1));
        $require_assignee_email = bvmgr_ticketing_v2_truthy($row['require_assignee_email'] ?? true, true);
        if ($visibility_mode !== 'verified') {
            $verified_program = '';
            $allowed_programs = array();
            $allow_direct_grants = false;
            $claim_grant_type = 'event_ticket_eligibility';
            $claims_per_assignee = 1;
            $require_assignee_email = true;
        } elseif ($verified_program === '' && !empty($allowed_programs)) {
            $verified_program = (string) $allowed_programs[0];
        }

        $counts_toward_unlock = array_key_exists('counts_toward_unlock', $row)
            ? !empty($row['counts_toward_unlock'])
            : (array_key_exists('counts_toward_attendance', $row) ? !empty($row['counts_toward_attendance']) : true);
        $max_qty_per_order = array_key_exists('max_qty_per_order', $row) ? max(0, absint($row['max_qty_per_order'])) : 0;
        $ratio_rule_enabled = !empty($row['ratio_rule_enabled']);
        $ratio_rule_max_per_qualifying = array_key_exists('ratio_rule_max_per_qualifying', $row) ? max(0, absint($row['ratio_rule_max_per_qualifying'])) : 0;
        if (!$ratio_rule_enabled || $ratio_rule_max_per_qualifying <= 0) {
            $ratio_rule_enabled = false;
            $ratio_rule_max_per_qualifying = 0;
        }
        $ratio_rule_qualifier_mode = isset($row['ratio_rule_qualifier_mode']) ? sanitize_key((string) $row['ratio_rule_qualifier_mode']) : 'counts_toward_unlock';
        if (!in_array($ratio_rule_qualifier_mode, array('counts_toward_unlock'), true)) {
            $ratio_rule_qualifier_mode = 'counts_toward_unlock';
        }
        $ratio_rule_group = sanitize_title((string) ($row['ratio_rule_group'] ?? ''));
        if (!$ratio_rule_enabled) {
            $ratio_rule_group = '';
        }

        $sort_order = isset($row['sort_order']) ? (int) $row['sort_order'] : $ticket_sort_fallback;
        if ($sort_order <= 0) {
            $sort_order = $ticket_sort_fallback;
        }
        $ticket_sort_fallback += 10;

        $image_mode = isset($row['image_mode']) ? sanitize_key((string) $row['image_mode']) : 'event_featured';
        if (!in_array($image_mode, array('event_featured', 'custom', 'none'), true)) {
            $image_mode = 'event_featured';
        }
        $image_id = ($image_mode === 'custom') ? absint($row['image_id'] ?? 0) : 0;

        $ticket_row_out = array(
            'enabled' => array_key_exists('enabled', $row) ? !empty($row['enabled']) : true,
            'ticket_key' => $key,
            'title' => $title,
            'description' => isset($row['description']) ? bvmgr_ticketing_v2_sanitize_plain_text_label($row['description']) : '',
            'price' => $price,
            'early_price' => $early_price,
            'early_price_start' => $early_price_start,
            'early_price_end' => $early_price_end,
            'early_price_start_relative_days' => $early_price_start_relative_days,
            'early_price_end_relative_days' => $early_price_end_relative_days,
            'early_price_cap' => $early_price_cap,
            'inventory_total' => $inventory_total,
            'visibility_mode' => $visibility_mode,
            'verified_program' => $verified_program,
            'allowed_programs' => $allowed_programs,
            'allow_direct_grants' => (bool) $allow_direct_grants,
            'claim_grant_type' => $claim_grant_type,
            'claims_per_assignee' => $claims_per_assignee,
            'require_assignee_email' => (bool) $require_assignee_email,
            'counts_toward_unlock' => (bool) $counts_toward_unlock,
            'max_qty_per_order' => $max_qty_per_order,
            'ratio_rule_enabled' => (bool) $ratio_rule_enabled,
            'ratio_rule_max_per_qualifying' => $ratio_rule_max_per_qualifying,
            'ratio_rule_qualifier_mode' => $ratio_rule_qualifier_mode,
            'ratio_rule_group' => $ratio_rule_group,
            'sort_order' => $sort_order,
            'sales_start' => isset($row['sales_start']) ? sanitize_text_field((string) $row['sales_start']) : '',
            'sales_end' => isset($row['sales_end']) ? sanitize_text_field((string) $row['sales_end']) : '',
            'sales_start_relative_days' => bvmgr_ticketing_v2_normalize_relative_days($row['sales_start_relative_days'] ?? ''),
            'sales_end_relative_days' => bvmgr_ticketing_v2_normalize_relative_days($row['sales_end_relative_days'] ?? ''),
            'image_mode' => $image_mode,
            'image_id' => $image_id,
        );
        $ticket_out[] = bvmgr_ticketing_v2_apply_relative_and_guarded_ticket_dates($ticket_row_out, $date_anchors);

        if (count($ticket_out) >= 50) {
            break;
        }
    }

    if (empty($ticket_out)) {
        $ticket_out[] = array(
            'enabled' => true,
            'ticket_key' => 'ga',
            'title' => 'GA Admission',
            'description' => '',
            'price' => '0',
            'early_price' => '',
            'early_price_start' => '',
            'early_price_end' => '',
            'early_price_start_relative_days' => '',
            'early_price_end_relative_days' => '',
            'early_price_cap' => 0,
            'inventory_total' => 0,
            'visibility_mode' => 'public',
            'verified_program' => '',
            'allowed_programs' => array(),
            'allow_direct_grants' => false,
            'claim_grant_type' => 'event_ticket_eligibility',
            'claims_per_assignee' => 1,
            'require_assignee_email' => true,
            'counts_toward_unlock' => true,
            'max_qty_per_order' => 0,
            'sort_order' => 10,
            'sales_start' => '',
            'sales_end' => '',
            'sales_start_relative_days' => '',
            'sales_end_relative_days' => '0',
            'image_mode' => 'event_featured',
            'image_id' => 0,
        );
    }

    usort($ticket_out, static function ($a, $b): int {
        $sa = (int) ($a['sort_order'] ?? 0);
        $sb = (int) ($b['sort_order'] ?? 0);
        if ($sa === $sb) {
            $ka = (string) ($a['ticket_key'] ?? '');
            $kb = (string) ($b['ticket_key'] ?? '');
            return strcmp($ka, $kb);
        }
        return ($sa < $sb) ? -1 : 1;
    });

    $primary_ticket = $ticket_out[0];
    foreach ($ticket_out as $ticket_row) {
        if (!empty($ticket_row['counts_toward_unlock'])) {
            $primary_ticket = $ticket_row;
            break;
        }
    }

    // Backward compatibility: keep legacy single-GA shape populated.
    $ga = array(
        'enabled' => !empty($primary_ticket['enabled']),
        'label' => (string) ($primary_ticket['title'] ?? 'GA Admission'),
        'price' => (string) ($primary_ticket['price'] ?? '0'),
        'early_price' => (string) ($primary_ticket['early_price'] ?? ''),
        'early_price_start' => (string) ($primary_ticket['early_price_start'] ?? ''),
        'early_price_end' => (string) ($primary_ticket['early_price_end'] ?? ''),
        'early_price_start_relative_days' => (string) ($primary_ticket['early_price_start_relative_days'] ?? ''),
        'early_price_end_relative_days' => (string) ($primary_ticket['early_price_end_relative_days'] ?? ''),
        'early_price_cap' => max(0, absint($primary_ticket['early_price_cap'] ?? 0)),
        'capacity' => max(0, (int) ($primary_ticket['inventory_total'] ?? 0)),
        'sales_start' => (string) ($primary_ticket['sales_start'] ?? ''),
        'sales_end' => (string) ($primary_ticket['sales_end'] ?? ''),
        'sales_start_relative_days' => (string) ($primary_ticket['sales_start_relative_days'] ?? ''),
        'sales_end_relative_days' => (string) ($primary_ticket['sales_end_relative_days'] ?? ''),
    );

    $ent_out = array();
    $ents = (isset($in['entitlements']) && is_array($in['entitlements'])) ? $in['entitlements'] : array();
    $seen_ids = array();

    foreach ($ents as $e) {
        if (!is_array($e)) {
            continue;
        }

        $enabled = !empty($e['enabled']);
        $label = isset($e['label']) ? bvmgr_ticketing_v2_sanitize_plain_text_label($e['label']) : '';
        if ($label === '') {
            // Skip completely blank rows.
            continue;
        }

        $ent_key = isset($e['entitlement_key']) ? sanitize_key((string) $e['entitlement_key']) : '';
        if ($ent_key === '') {
            $ent_key = sanitize_key($label);
        }
        if ($ent_key === '') {
            $ent_key = 'ent_' . substr(sha1('v2|key|' . $plan_id . '|' . $label), 0, 8);
        }

        $ent_id = isset($e['entitlement_id']) ? sanitize_key((string) $e['entitlement_id']) : '';
        if ($ent_id === '') {
            $ent_id = bvmgr_ticketing_v2_default_ent_id($plan_id, $ent_key);
        }

        $base_ent_id = $ent_id;
        $suffix = 2;
        while (isset($seen_ids[$ent_id])) {
            $ent_id = $base_ent_id . '_' . $suffix;
            $suffix++;
        }
        $seen_ids[$ent_id] = true;

        $price = isset($e['price']) ? (string) $e['price'] : '0';
        $price = (string) max(0.0, (float) $price);

        $selector_mode = isset($e['selector_mode']) ? sanitize_key((string) $e['selector_mode']) : 'stepper';
        if (!in_array($selector_mode, array('stepper', 'checkbox'), true)) {
            $selector_mode = 'stepper';
        }

        $capacity = isset($e['capacity']) ? (int) $e['capacity'] : 0;
        $capacity = max(0, $capacity);

        $short_desc = isset($e['short_desc']) ? bvmgr_ticketing_v2_sanitize_plain_text_label($e['short_desc']) : '';
        $more_info_raw = isset($e['more_info']) ? (string) $e['more_info'] : '';
        $more_info = '';
        if ($more_info_raw !== '') {
            $more_info = trim((string) wp_kses_post($more_info_raw));
            if ($more_info === '' && trim($more_info_raw) !== '') {
                $more_info = sanitize_textarea_field($more_info_raw);
            }
        }
        $image_id = isset($e['image_id']) ? absint($e['image_id']) : 0;

        $elig_in = (isset($e['eligibility']) && is_array($e['eligibility'])) ? $e['eligibility'] : array();
        $pool_max_total = isset($elig_in['pool_max_total']) ? max(0, (int) $elig_in['pool_max_total']) : null;
        $pool_max_explicit = !empty($elig_in['pool_max_explicit']);
        $elig = array(
            'min_ga_per_unit' => isset($elig_in['min_ga_per_unit']) ? max(0, (int) $elig_in['min_ga_per_unit']) : 0,
            'max_units_per_order' => isset($elig_in['max_units_per_order']) ? max(0, (int) $elig_in['max_units_per_order']) : 0,
            'max_units_per_ga' => isset($elig_in['max_units_per_ga']) ? max(0, (int) $elig_in['max_units_per_ga']) : 0,
            'allow_without_ga' => !empty($elig_in['allow_without_ga']),
            'pool_key' => isset($elig_in['pool_key']) ? sanitize_key((string) $elig_in['pool_key']) : '',
            'pool_max_total' => 0,
            'pool_max_explicit' => $pool_max_explicit ? 1 : 0,
        );

        // Backfill shared pool grouping for legacy add-ons when not explicitly configured.
        // This avoids hardcoding caps in the front end while still supporting the common rule: one reserved seating add-on (table OR fire pit) per N GA tickets.
        if ($elig['pool_key'] === '' && $plan_id > 0) {
            if (strpos($ent_key, 'table_') === 0 || strpos($ent_key, 'fire_pit_') === 0) {
                $elig['pool_key'] = 'reserved_seating';
            }
        }
        if ($pool_max_total === null) {
            // No implicit hard cap for pooled groups.
            // Operators can set pool_max_total explicitly when needed.
            $pool_max_total = 0;
        } elseif (!$pool_max_explicit && $pool_max_total === 1 && $elig['pool_key'] !== '' && (int) $elig['min_ga_per_unit'] > 0) {
            // Back-compat: older builds auto-injected pool max=1 for pooled groups.
            // Treat that legacy implicit value as "no hard cap" so qualification scales with GA quantity.
            $pool_max_total = 0;
        }
        $elig['pool_max_total'] = max(0, (int) $pool_max_total);
        $elig['pool_max_explicit'] = ($elig['pool_max_total'] > 0 && $pool_max_explicit) ? 1 : 0;

        $sq_in = (isset($e['square']) && is_array($e['square'])) ? $e['square'] : array();
        $sq_mode = isset($sq_in['mode']) ? (string) $sq_in['mode'] : 'none';
        $sq_mode = in_array($sq_mode, array('none', 'generic', 'per_event'), true) ? $sq_mode : 'none';
        $sq = array(
            'mode' => $sq_mode,
            'item_id' => isset($sq_in['item_id']) ? sanitize_text_field((string) $sq_in['item_id']) : '',
            'variation_id' => isset($sq_in['variation_id']) ? sanitize_text_field((string) $sq_in['variation_id']) : '',
        );

        $ent_out[] = array(
            'entitlement_id' => $ent_id,
            'entitlement_key' => $ent_key,
            'enabled' => $enabled,
            'label' => $label,
            'price' => $price,
            'capacity' => $capacity,
            'short_desc' => $short_desc,
            'more_info' => $more_info,
            'image_id' => $image_id,
            'selector_mode' => $selector_mode,
            'eligibility' => $elig,
            'square' => $sq,
        );

        if (count($ent_out) >= 100) {
            break;
        }
    }

    $sq = (isset($in['square']) && is_array($in['square'])) ? $in['square'] : array();
    $sq_ga = (isset($sq['ga']) && is_array($sq['ga'])) ? $sq['ga'] : array();
    $sq_ga_mode = isset($sq_ga['mode']) ? (string) $sq_ga['mode'] : 'none';
    $sq_ga_mode = in_array($sq_ga_mode, array('none', 'generic', 'per_event'), true) ? $sq_ga_mode : 'none';

    return array(
        'version' => 2,
        'mode' => $mode,
        'provider' => 'tec_tickets_woo',
        'tickets' => $ticket_out,
        'ga' => $ga,
        'entitlements' => $ent_out,
        'square' => array(
            'ga' => array(
                'mode' => $sq_ga_mode,
                'item_id' => isset($sq_ga['item_id']) ? sanitize_text_field((string) $sq_ga['item_id']) : '',
                'variation_id' => isset($sq_ga['variation_id']) ? sanitize_text_field((string) $sq_ga['variation_id']) : '',
            ),
        ),
    );
}

function bvmgr_ticketing_v2_hash_config(array $cfg): string {
    // Hashing must be deterministic. Minor ordering differences in arrays (especially entitlements)
    // should not trap operators in a permanent "config changed" loop.
    $cfg_sorted = bvmgr_ticketing_v2_sort_for_hash($cfg);
    $json = wp_json_encode($cfg_sorted);
    $json = is_string($json) ? $json : '';
    return sha1($json);
}

/**
 * Sync hash guardrail for Preview → Commit.
 * Descriptive entitlement copy can change without forcing a new preview.
 */
function bvmgr_ticketing_v2_hash_config_for_sync(array $cfg): string {
    $cfg_for_sync = $cfg;
    if (isset($cfg_for_sync['entitlements']) && is_array($cfg_for_sync['entitlements'])) {
        $ent_out = array();
        foreach ($cfg_for_sync['entitlements'] as $ent) {
            if (!is_array($ent)) {
                continue;
            }
            unset($ent['short_desc'], $ent['more_info'], $ent['selector_mode']);
            $ent_out[] = $ent;
        }
        $cfg_for_sync['entitlements'] = $ent_out;
    }

    return bvmgr_ticketing_v2_hash_config($cfg_for_sync);
}

function bvmgr_ticketing_v2_is_list_array(array $arr): bool {
    $i = 0;
    foreach ($arr as $k => $v) {
        if ($k !== $i) {
            return false;
        }
        $i++;
    }
    return true;
}

function bvmgr_ticketing_v2_sort_for_hash($value) {
    if (!is_array($value)) {
        return $value;
    }

    // List arrays: keep stable ordering, but sort known "unordered" lists for hash stability.
    if (bvmgr_ticketing_v2_is_list_array($value)) {
        $list = $value;

        // Ticketing config: entitlements list order should not affect the config hash.
        if (!empty($list) && isset($list[0]) && is_array($list[0]) && array_key_exists('entitlement_id', $list[0])) {
            usort($list, function ($a, $b) {
                $ai = is_array($a) && isset($a['entitlement_id']) ? (string) $a['entitlement_id'] : '';
                $bi = is_array($b) && isset($b['entitlement_id']) ? (string) $b['entitlement_id'] : '';
                return strcmp($ai, $bi);
            });
        } elseif (!empty($list) && isset($list[0]) && is_array($list[0]) && array_key_exists('ticket_key', $list[0])) {
            usort($list, function ($a, $b) {
                $sa = is_array($a) ? (int) ($a['sort_order'] ?? 0) : 0;
                $sb = is_array($b) ? (int) ($b['sort_order'] ?? 0) : 0;
                if ($sa === $sb) {
                    $ak = is_array($a) ? (string) ($a['ticket_key'] ?? '') : '';
                    $bk = is_array($b) ? (string) ($b['ticket_key'] ?? '') : '';
                    return strcmp($ak, $bk);
                }
                return ($sa < $sb) ? -1 : 1;
            });
        }

        $out = array();
        foreach ($list as $item) {
            $out[] = bvmgr_ticketing_v2_sort_for_hash($item);
        }
        return $out;
    }

    // Assoc arrays: sort keys for stable encoding.
    ksort($value);
    $out = array();
    foreach ($value as $k => $v) {
        $out[$k] = bvmgr_ticketing_v2_sort_for_hash($v);
    }
    return $out;
}

function bvmgr_ticketing_v2_hash_ga(array $ga): string {
    $subset = array(
        'label' => (string) ($ga['label'] ?? ''),
        'price' => (string) ($ga['price'] ?? ''),
        'capacity' => (int) ($ga['capacity'] ?? 0),
        'sales_start' => (string) ($ga['sales_start'] ?? ''),
        'sales_end' => (string) ($ga['sales_end'] ?? ''),
    );
    $json = wp_json_encode($subset);
    $json = is_string($json) ? $json : '';
    return sha1($json);
}

function bvmgr_ticketing_v2_hash_ticket(array $ticket): string {
    $legacy_program = sanitize_key((string) ($ticket['verified_program'] ?? ''));
    $allowed_programs = bvmgr_ticketing_v2_normalize_allowed_programs($ticket['allowed_programs'] ?? array(), $legacy_program);
    $claim_grant_type = sanitize_key((string) ($ticket['claim_grant_type'] ?? 'event_ticket_eligibility'));
    $allowed_claim_grant_types = function_exists('bvmgr_ticketing_claims_allowed_grant_types')
        ? (array) bvmgr_ticketing_claims_allowed_grant_types()
        : array('event_ticket_eligibility', 'event_free_admit', 'credential_benefit_override', 'event_grant');
    if (!in_array($claim_grant_type, $allowed_claim_grant_types, true)) {
        $claim_grant_type = 'event_ticket_eligibility';
    }

    $subset = array(
        'ticket_key' => (string) ($ticket['ticket_key'] ?? ''),
        'title' => (string) ($ticket['title'] ?? ''),
        'description' => (string) ($ticket['description'] ?? ''),
        'price' => (string) ($ticket['price'] ?? ''),
        'early_price' => (string) ($ticket['early_price'] ?? ''),
        'early_price_start' => (string) ($ticket['early_price_start'] ?? ''),
        'early_price_end' => (string) ($ticket['early_price_end'] ?? ''),
        'early_price_start_relative_days' => (string) ($ticket['early_price_start_relative_days'] ?? ''),
        'early_price_end_relative_days' => (string) ($ticket['early_price_end_relative_days'] ?? ''),
        'inventory_total' => (int) ($ticket['inventory_total'] ?? 0),
        'visibility_mode' => (string) ($ticket['visibility_mode'] ?? 'public'),
        'verified_program' => $legacy_program,
        'allowed_programs' => $allowed_programs,
        'allow_direct_grants' => bvmgr_ticketing_v2_truthy($ticket['allow_direct_grants'] ?? false, false) ? 1 : 0,
        'claim_grant_type' => $claim_grant_type,
        'claims_per_assignee' => max(0, absint($ticket['claims_per_assignee'] ?? 1)),
        'require_assignee_email' => bvmgr_ticketing_v2_truthy($ticket['require_assignee_email'] ?? true, true) ? 1 : 0,
        'counts_toward_unlock' => !empty($ticket['counts_toward_unlock']) ? 1 : 0,
        'max_qty_per_order' => max(0, absint($ticket['max_qty_per_order'] ?? 0)),
        'sort_order' => bvmgr_ticketing_b_normalize_sort_order($ticket['sort_order'] ?? 0, 10),
        'sales_start' => (string) ($ticket['sales_start'] ?? ''),
        'sales_end' => (string) ($ticket['sales_end'] ?? ''),
        'sales_start_relative_days' => (string) ($ticket['sales_start_relative_days'] ?? ''),
        'sales_end_relative_days' => (string) ($ticket['sales_end_relative_days'] ?? ''),
        'image_mode' => in_array(sanitize_key((string) ($ticket['image_mode'] ?? 'event_featured')), array('event_featured', 'custom', 'none'), true)
            ? sanitize_key((string) ($ticket['image_mode'] ?? 'event_featured'))
            : 'event_featured',
        'image_id' => (sanitize_key((string) ($ticket['image_mode'] ?? 'event_featured')) === 'custom')
            ? absint($ticket['image_id'] ?? 0)
            : 0,
        'enabled' => array_key_exists('enabled', $ticket) ? (!empty($ticket['enabled']) ? 1 : 0) : 1,
    );
    $json = wp_json_encode($subset);
    $json = is_string($json) ? $json : '';
    return sha1($json);
}

function bvmgr_ticketing_v2_template_sales_end_guardrail_summary(array $cfg): array {
    $normalized = bvmgr_ticketing_v2_normalize_config($cfg, 0);
    $tickets_in = (isset($normalized['tickets']) && is_array($normalized['tickets'])) ? $normalized['tickets'] : array();
    $tickets = array();

    foreach ($tickets_in as $ticket) {
        if (!is_array($ticket)) {
            continue;
        }

        $tickets[] = array(
            'ticket_key' => sanitize_key((string) ($ticket['ticket_key'] ?? '')),
            'title' => bvmgr_ticketing_v2_sanitize_plain_text_label(($ticket['title'] ?? $ticket['label'] ?? '') ?: 'Ticket'),
            'sales_end' => bvmgr_ticketing_v2_normalize_sales_window_value((string) ($ticket['sales_end'] ?? '')),
        );
    }

    return array(
        'ticket_count' => count($tickets),
        'tickets' => $tickets,
    );
}

function bvmgr_ticketing_v2_resolve_template_apply_show_datetime(int $plan_id, string $show_datetime = ''): string {
    $plan_id = absint($plan_id);
    $show_datetime = bvmgr_ticketing_v2_normalize_sales_window_value($show_datetime);
    if ($show_datetime !== '') {
        return $show_datetime;
    }

    $defaults = bvmgr_ticketing_v2_get_plan_sales_window_defaults($plan_id);
    return bvmgr_ticketing_v2_normalize_sales_window_value((string) ($defaults['sales_end'] ?? ''));
}

function bvmgr_ticketing_v2_reset_stale_sales_end_to_show(array $cfg, string $show_datetime = '', string $event_start_datetime = ''): array {
    $target = bvmgr_ticketing_v2_normalize_sales_window_value($show_datetime);
    if ($target === '') {
        return $cfg;
    }

    $event_start = bvmgr_ticketing_v2_normalize_sales_window_value($event_start_datetime);
    if ($event_start === '') {
        // Backward compatibility: older callers passed the event/show start as the
        // only target. Keep that stale-template repair behavior when no separate
        // event-start anchor is available.
        $event_start = $target;
    }

    $tickets = (isset($cfg['tickets']) && is_array($cfg['tickets'])) ? array_values($cfg['tickets']) : array();
    if (empty($tickets)) {
        return $cfg;
    }

    foreach ($tickets as $idx => $ticket) {
        if (!is_array($ticket)) {
            continue;
        }

        $sales_end = bvmgr_ticketing_v2_normalize_sales_window_value((string) ($ticket['sales_end'] ?? ''));
        if ($sales_end === '') {
            continue;
        }

        if (strcmp($sales_end, $event_start) < 0 || strcmp($sales_end, $target) > 0) {
            $tickets[$idx]['sales_end'] = $target;
        }
    }

    $cfg['tickets'] = $tickets;

    $primary_ticket = $tickets[0];
    foreach ($tickets as $ticket) {
        if (!empty($ticket['counts_toward_unlock'])) {
            $primary_ticket = $ticket;
            break;
        }
    }

    if (!isset($cfg['ga']) || !is_array($cfg['ga'])) {
        $cfg['ga'] = array();
    }
    $cfg['ga']['sales_start'] = (string) ($primary_ticket['sales_start'] ?? '');
    $cfg['ga']['sales_end'] = (string) ($primary_ticket['sales_end'] ?? '');

    return $cfg;
}

/**
 * Ticketing v2 Templates
 *
 * Stored in wp_options as an operator convenience.
 * These templates affect only the saved v2 config on an Event Plan.
 * Nothing is created/changed in TEC/Woo until the operator uses Preview → Commit.
 */
function bvmgr_ticketing_v2_templates_option_key(): string {
    return defined('BVMGR_OPT_TICKETING_TEMPLATES_V1') ? (string) BVMGR_OPT_TICKETING_TEMPLATES_V1 : 'vms_ticketing_templates_v1';
}

function bvmgr_ticketing_v2_default_template_option_key(): string {
    return defined('BVMGR_OPT_TICKETING_DEFAULT_TEMPLATE_V1') ? (string) BVMGR_OPT_TICKETING_DEFAULT_TEMPLATE_V1 : 'vms_ticketing_default_template_v1';
}

function bvmgr_ticketing_v2_get_default_template_id(): string {
    $id = get_option(bvmgr_ticketing_v2_default_template_option_key(), '');
    $id = sanitize_key((string) $id);
    if ($id === '') {
        return '';
    }

    $templates = bvmgr_ticketing_v2_templates_get_all();
    if (empty($templates[$id])) {
        return '';
    }

    return $id;
}

function bvmgr_ticketing_v2_set_default_template_id(string $template_id): bool {
    $template_id = sanitize_key((string) $template_id);

    // Allow clearing the default.
    if ($template_id === '') {
        update_option(bvmgr_ticketing_v2_default_template_option_key(), '', false);
        return true;
    }

    $templates = bvmgr_ticketing_v2_templates_get_all();
    if (empty($templates[$template_id])) {
        return false;
    }

    update_option(bvmgr_ticketing_v2_default_template_option_key(), $template_id, false);
    return true;
}


function bvmgr_ticketing_v2_templates_get_all(): array {
    $raw = get_option(bvmgr_ticketing_v2_templates_option_key(), array());
    if (!is_array($raw)) {
        $raw = array();
    }

    $out = array();
    foreach ($raw as $id => $tpl) {
        $id = sanitize_key((string) $id);
        if ($id === '' || !is_array($tpl)) {
            continue;
        }

        $name = isset($tpl['name']) ? sanitize_text_field((string) $tpl['name']) : '';
        $cfg = isset($tpl['config']) && is_array($tpl['config']) ? $tpl['config'] : null;
        if ($name === '' || !is_array($cfg)) {
            continue;
        }

        $out[$id] = array(
            'id' => $id,
            'name' => $name,
            'created_at' => isset($tpl['created_at']) ? sanitize_text_field((string) $tpl['created_at']) : '',
            'updated_at' => isset($tpl['updated_at']) ? sanitize_text_field((string) $tpl['updated_at']) : '',
            'config' => $cfg,
            'sales_end_guardrail' => bvmgr_ticketing_v2_template_sales_end_guardrail_summary($cfg),
        );
    }

    return $out;
}

function bvmgr_ticketing_v2_templates_save(string $name, array $config): array {
    $name = trim(sanitize_text_field($name));
    if ($name === '') {
        return array('ok' => false, 'message' => 'missing_name');
    }

    $cfg = bvmgr_ticketing_v2_normalize_config($config, 0);

    $templates = bvmgr_ticketing_v2_templates_get_all();
    $id = 'tpl_' . substr(sha1(wp_generate_password(32, false, true) . '|' . $name . '|' . microtime(true)), 0, 12);

    $now = wp_date('Y-m-d H:i:s', time(), wp_timezone());
    $templates[$id] = array(
        'id' => $id,
        'name' => $name,
        'created_at' => $now,
        'updated_at' => $now,
        'config' => $cfg,
    );

    update_option(bvmgr_ticketing_v2_templates_option_key(), $templates, false);

    return array('ok' => true, 'template_id' => $id);
}

function bvmgr_ticketing_v2_templates_apply_to_plan(int $plan_id, string $template_id, array $options = array()): array {
    $plan_id = absint($plan_id);
    $template_id = sanitize_key($template_id);
    if ($plan_id <= 0 || $template_id === '') {
        return array('ok' => false, 'message' => 'invalid_payload');
    }

    $templates = bvmgr_ticketing_v2_templates_get_all();
    if (empty($templates[$template_id]) || !is_array($templates[$template_id]['config'] ?? null)) {
        return array('ok' => false, 'message' => 'template_not_found');
    }

    $cfg_before = bvmgr_ticketing_v2_get_config($plan_id);
    $cfg = bvmgr_ticketing_v2_normalize_config($templates[$template_id]['config'], $plan_id);
    $cfg = bvmgr_ticketing_v2_hydrate_missing_sales_windows($cfg, $plan_id);
    $target_show_datetime = bvmgr_ticketing_v2_resolve_template_apply_show_datetime($plan_id, (string) ($options['show_datetime'] ?? ''));
    if (!empty($options['reset_stale_sales_end']) && $target_show_datetime !== '') {
        $anchors = bvmgr_ticketing_v2_get_plan_event_anchor_datetimes($plan_id);
        $cfg = bvmgr_ticketing_v2_reset_stale_sales_end_to_show(
            $cfg,
            $target_show_datetime,
            (string) ($anchors['event_start'] ?? '')
        );
    }
    if (function_exists('bvmgr_ticket_mutation_audit_push_context')) {
        bvmgr_ticket_mutation_audit_push_context(array(
            'trigger_source' => 'manual_action',
            'change_type' => 'ticket_template_applied',
            'summary_text' => __('Applied a saved ticket template to this event.', 'backstage-venue-manager'),
            'source_function' => 'vms_ticketing_v2_templates_apply_to_plan',
            'source_hook' => sanitize_key((string) current_filter()),
            'requested_result_status' => 'success',
        ));
    }
    bvmgr_ticketing_v2_set_config($plan_id, $cfg);
    if (function_exists('bvmgr_ticket_mutation_audit_pop_context')) {
        bvmgr_ticket_mutation_audit_pop_context();
    }
    bvmgr_entitlements_sync_plan_image_changes($plan_id, $cfg_before, $cfg);

    return array(
        'ok' => true,
        'config' => $cfg,
        'applied_show_datetime' => $target_show_datetime,
    );
}

function bvmgr_ticketing_v2_hash_entitlement(array $ent): string {
    $subset = array(
        'label' => (string) ($ent['label'] ?? ''),
        'price' => (string) ($ent['price'] ?? ''),
        'capacity' => (int) ($ent['capacity'] ?? 0),
        'eligibility' => is_array($ent['eligibility'] ?? null) ? $ent['eligibility'] : array(),
    );
    $json = wp_json_encode($subset);
    $json = is_string($json) ? $json : '';
    return sha1($json);
}

function bvmgr_entitlements_sync_image_log(string $event_code, array $context = array(), $error = null): void {
    if (!function_exists('bvmgr_record_operational_issue')) {
        return;
    }

    if (func_num_args() === 1) {
        bvmgr_record_operational_issue(
            'entitlement_image_sync_legacy',
            array(
                'service' => 'ticketing',
                'operation' => 'sync_image',
                'status' => 'legacy',
            ),
            $event_code
        );
        return;
    }

    bvmgr_record_operational_issue($event_code, $context, $error);
}

function bvmgr_entitlements_find_config_entitlement(int $plan_id, string $entitlement_id): array {
    $plan_id = absint($plan_id);
    $entitlement_id = sanitize_key($entitlement_id);
    if ($plan_id <= 0 || $entitlement_id === '') {
        return array();
    }

    $raw_cfg = get_post_meta($plan_id, bvmgr_ticketing_v2_k('config'), true);
    if (!is_array($raw_cfg)) {
        return array();
    }

    $cfg = bvmgr_ticketing_v2_normalize_config($raw_cfg, $plan_id);
    $ents = is_array($cfg['entitlements'] ?? null) ? $cfg['entitlements'] : array();
    foreach ($ents as $ent) {
        if (!is_array($ent)) {
            continue;
        }
        $candidate_id = sanitize_key((string) ($ent['entitlement_id'] ?? ''));
        if ($candidate_id === $entitlement_id) {
            return $ent;
        }
    }

    return array();
}

function bvmgr_entitlements_find_raw_config_entitlement(int $plan_id, string $entitlement_id, string $entitlement_key = ''): array {
    $plan_id = absint($plan_id);
    $entitlement_id = sanitize_key($entitlement_id);
    $entitlement_key = sanitize_key($entitlement_key);
    if ($plan_id <= 0 || ($entitlement_id === '' && $entitlement_key === '')) {
        return array();
    }

    $raw_cfg = get_post_meta($plan_id, bvmgr_ticketing_v2_k('config'), true);
    if (!is_array($raw_cfg)) {
        return array();
    }

    $raw_ents = is_array($raw_cfg['entitlements'] ?? null) ? $raw_cfg['entitlements'] : array();
    foreach ($raw_ents as $raw_ent) {
        if (!is_array($raw_ent)) {
            continue;
        }
        $candidate_id = sanitize_key((string) ($raw_ent['entitlement_id'] ?? ''));
        $candidate_key = sanitize_key((string) ($raw_ent['entitlement_key'] ?? ''));
        if ($entitlement_id !== '' && $candidate_id === $entitlement_id) {
            return $raw_ent;
        }
        if ($entitlement_key !== '' && $candidate_key === $entitlement_key) {
            return $raw_ent;
        }
    }

    return array();
}

function bvmgr_entitlements_extract_image_url_from_raw_entitlement(array $raw_ent): string {
    $candidates = array(
        $raw_ent['image_url'] ?? '',
        $raw_ent['image'] ?? '',
        $raw_ent['image_src'] ?? '',
        $raw_ent['image_uri'] ?? '',
        $raw_ent['image_link'] ?? '',
    );

    // Some legacy payloads place a URL string in image_id.
    if (isset($raw_ent['image_id']) && is_string($raw_ent['image_id']) && !is_numeric($raw_ent['image_id'])) {
        $candidates[] = $raw_ent['image_id'];
    }

    foreach ($candidates as $candidate) {
        $url = trim((string) $candidate);
        if ($url === '') {
            continue;
        }
        $url = esc_url_raw($url);
        if ($url !== '') {
            return $url;
        }
    }

    return '';
}

function bvmgr_entitlements_get_entitlement_image_context($entitlement_id, int $plan_id_hint = 0): array {
    $entitlement_id = sanitize_key((string) $entitlement_id);
    $plan_id_hint = absint($plan_id_hint);

    $ctx = array(
        'entitlement_id' => $entitlement_id,
        'plan_id' => 0,
        'found' => false,
        'attachment_id' => 0,
        'configured_image_id' => 0,
        'configured_image_url' => '',
        'missing_attachment' => false,
        'policy_no_image' => false,
    );

    if ($entitlement_id === '') {
        return $ctx;
    }

    static $cache = array();
    $cache_key = $entitlement_id . '|' . $plan_id_hint;
    if (isset($cache[$cache_key]) && is_array($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    $plan_queue = array();
    if ($plan_id_hint > 0) {
        $plan_queue[] = $plan_id_hint;
    }

    static $all_plan_ids = null;
    if (!is_array($all_plan_ids)) {
        $all_plan_ids = array();
        $candidate_plan_ids = get_posts(array(
            'post_type' => 'vms_event_plan',
            'post_status' => array('publish', 'future', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Entitlement image discovery must locate plans carrying the ticketing configuration key; the complete ID list is built once and retained in a request-local static cache.
            'meta_query' => array(
                array(
                    'key' => bvmgr_ticketing_v2_k('config'),
                    'compare' => 'EXISTS',
                ),
            ),
        ));
        if (is_array($candidate_plan_ids)) {
            foreach ($candidate_plan_ids as $candidate_plan_id) {
                $candidate_plan_id = absint($candidate_plan_id);
                if ($candidate_plan_id > 0) {
                    $all_plan_ids[] = $candidate_plan_id;
                }
            }
        }
    }
    foreach ($all_plan_ids as $candidate_plan_id) {
        $plan_queue[] = absint($candidate_plan_id);
    }

    $plan_ids = array();
    foreach ($plan_queue as $candidate_plan_id) {
        $candidate_plan_id = absint($candidate_plan_id);
        if ($candidate_plan_id <= 0 || isset($plan_ids[$candidate_plan_id])) {
            continue;
        }
        $plan_ids[$candidate_plan_id] = $candidate_plan_id;
    }

    foreach ($plan_ids as $plan_id) {
        $ent = bvmgr_entitlements_find_config_entitlement($plan_id, $entitlement_id);
        if (empty($ent)) {
            continue;
        }

        $ctx['found'] = true;
        $ctx['plan_id'] = $plan_id;

        $image_id = absint($ent['image_id'] ?? 0);
        $ctx['configured_image_id'] = $image_id;
        if ($image_id > 0) {
            if (get_post_type($image_id) === 'attachment') {
                $ctx['attachment_id'] = $image_id;
            } else {
                $ctx['missing_attachment'] = true;
            }
            $cache[$cache_key] = $ctx;
            return $ctx;
        }

        $ent_key = sanitize_key((string) ($ent['entitlement_key'] ?? ''));
        $raw_ent = bvmgr_entitlements_find_raw_config_entitlement($plan_id, $entitlement_id, $ent_key);
        $image_url = bvmgr_entitlements_extract_image_url_from_raw_entitlement($raw_ent);
        if ($image_url !== '') {
            $ctx['configured_image_url'] = $image_url;
            if (function_exists('attachment_url_to_postid')) {
                $resolved = absint(attachment_url_to_postid($image_url));
                if ($resolved > 0 && get_post_type($resolved) === 'attachment') {
                    $ctx['attachment_id'] = $resolved;
                } else {
                    $ctx['missing_attachment'] = true;
                }
            } else {
                $ctx['missing_attachment'] = true;
            }
        } else {
            $ctx['policy_no_image'] = true;
        }

        $cache[$cache_key] = $ctx;
        return $ctx;
    }

    $cache[$cache_key] = $ctx;
    return $ctx;
}

function bvmgr_entitlements_get_image_attachment_id($entitlement_id, int $plan_id_hint = 0): int {
    $ctx = bvmgr_entitlements_get_entitlement_image_context($entitlement_id, $plan_id_hint);
    return absint($ctx['attachment_id'] ?? 0);
}

function bvmgr_entitlements_sync_product_image_with_result(int $product_id, $entitlement_id): array {
    $product_id = absint($product_id);
    $entitlement_id = sanitize_key((string) $entitlement_id);

    $result = array(
        'status' => 'skipped',
        'product_id' => $product_id,
        'entitlement_id' => $entitlement_id,
        'plan_id' => 0,
        'image_id' => 0,
        'message' => '',
    );

    if ($product_id <= 0 || get_post_type($product_id) !== 'product') {
        $result['status'] = 'error_missing_product';
        $result['message'] = 'missing_product';
        bvmgr_entitlements_sync_image_log(
            'entitlement_image_sync_product_failed',
            array(
                'service' => 'ticketing',
                'operation' => 'sync_image',
                'stage' => 'validate_product',
                'status' => $result['status'],
                'product_id' => $product_id,
            )
        );
        return $result;
    }

    $plan_meta_key = bvmgr_ticketing_v2_product_meta_key('event_plan_id');
    $plan_id_hint = absint(get_post_meta($product_id, $plan_meta_key, true));
    $result['plan_id'] = $plan_id_hint;

    // Build-spec contract: resolve image id via helper.
    $img_id = bvmgr_entitlements_get_image_attachment_id($entitlement_id, $plan_id_hint);
    $ctx = bvmgr_entitlements_get_entitlement_image_context($entitlement_id, $plan_id_hint);
    if (absint($ctx['plan_id'] ?? 0) > 0) {
        $result['plan_id'] = absint($ctx['plan_id']);
    }
    if ($img_id <= 0 && absint($ctx['attachment_id'] ?? 0) > 0) {
        $img_id = absint($ctx['attachment_id']);
    }
    $result['image_id'] = $img_id;

    if ($img_id > 0) {
        $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
        $wc_save_warning = '';
        if ($product && method_exists($product, 'set_image_id') && method_exists($product, 'save')) {
            try {
                $product->set_image_id($img_id);
                $product->save();
            } catch (Throwable $e) {
                $wc_save_warning = 'wc_save_failed: ' . $e->getMessage();
                bvmgr_entitlements_sync_image_log(
                    'entitlement_image_sync_product_save_failed',
                    array(
                        'service' => 'ticketing',
                        'operation' => 'sync_image',
                        'stage' => 'product_save',
                        'status' => 'warning_wc_save_failed',
                        'product_id' => $product_id,
                        'plan_id' => absint($result['plan_id']),
                        'post_id' => $img_id,
                    ),
                    $e
                );
            }
        }

        if (function_exists('set_post_thumbnail')) {
            set_post_thumbnail($product_id, $img_id);
        }

        $result['status'] = 'updated';
        $result['message'] = ($wc_save_warning !== '') ? ('updated_with_warning: ' . $wc_save_warning) : 'updated';
        bvmgr_entitlements_sync_image_log(
            'entitlement_image_sync_product_completed',
            array(
                'service' => 'ticketing',
                'operation' => 'sync_image',
                'stage' => 'apply_image',
                'status' => $result['status'],
                'product_id' => $product_id,
                'plan_id' => absint($result['plan_id']),
                'post_id' => $img_id,
            )
        );
        return $result;
    }

    if (empty($ctx['found'])) {
        $result['status'] = 'error_missing_entitlement';
        $result['message'] = 'missing_entitlement';
    } elseif (!empty($ctx['missing_attachment'])) {
        $result['status'] = 'error_missing_attachment';
        $result['message'] = 'missing_attachment';
    } elseif (!empty($ctx['policy_no_image'])) {
        $thumb_id = function_exists('get_post_thumbnail_id') ? absint(get_post_thumbnail_id($product_id)) : 0;
        if ($thumb_id > 0 && function_exists('delete_post_thumbnail')) {
            delete_post_thumbnail($product_id);
            $result['status'] = 'cleared';
            $result['message'] = 'cleared_no_image_policy';
        } else {
            $result['status'] = 'skipped_no_image';
            $result['message'] = 'no_image_policy_no_thumbnail';
        }
    } else {
        $result['status'] = 'skipped';
        $result['message'] = 'no_image_resolved';
    }

    bvmgr_entitlements_sync_image_log(
        'entitlement_image_sync_product_result',
        array(
            'service' => 'ticketing',
            'operation' => 'sync_image',
            'stage' => 'resolve_image',
            'status' => (string) $result['status'],
            'product_id' => $product_id,
            'plan_id' => absint($result['plan_id']),
            'post_id' => $img_id,
        )
    );

    return $result;
}

function bvmgr_entitlements_sync_product_image($product_id, $entitlement_id): void {
    bvmgr_entitlements_sync_product_image_with_result(absint($product_id), $entitlement_id);
}

/**
 * Resolve the target image attachment ID for a Ticketing v2 ticket row.
 */
function bvmgr_ticketing_v2_resolve_ticket_image_target_id(array $ticket, int $plan_id): int {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return 0;
    }

    $mode = sanitize_key((string) ($ticket['image_mode'] ?? 'event_featured'));
    if (!in_array($mode, array('event_featured', 'custom', 'none'), true)) {
        $mode = 'event_featured';
    }

    $custom_id = absint($ticket['image_id'] ?? 0);
    $event_featured_id = function_exists('bvmgr_ticketing_v2_resolve_event_featured_image_id')
        ? bvmgr_ticketing_v2_resolve_event_featured_image_id($plan_id)
        : (function_exists('get_post_thumbnail_id') ? absint(get_post_thumbnail_id($plan_id)) : 0);

    if ($mode === 'none') {
        return 0;
    }
    if ($mode === 'custom') {
        return ($custom_id > 0) ? $custom_id : $event_featured_id;
    }

    return $event_featured_id;
}

/**
 * Apply Ticketing v2 ticket image policy to a Woo product.
 */
function bvmgr_ticketing_v2_apply_ticket_image_policy(int $product_id, int $plan_id, array $ticket): void {
    $product_id = absint($product_id);
    $plan_id = absint($plan_id);
    if ($product_id <= 0 || $plan_id <= 0) {
        return;
    }

    $target_image_id = bvmgr_ticketing_v2_resolve_ticket_image_target_id($ticket, $plan_id);

    $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
    if ($product && method_exists($product, 'set_image_id') && method_exists($product, 'save')) {
        try {
            $product->set_image_id($target_image_id);
            $product->save();
        } catch (Throwable $e) {
            // Fall back to post thumbnail API below.
        }
    }

    if ($target_image_id > 0 && function_exists('set_post_thumbnail')) {
        set_post_thumbnail($product_id, $target_image_id);
    } elseif ($target_image_id === 0 && function_exists('delete_post_thumbnail')) {
        delete_post_thumbnail($product_id);
    }
}

function bvmgr_ticketing_v2_primary_ticket_config_for_plan(int $plan_id): array {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return array();
    }

    $cfg = bvmgr_ticketing_v2_get_config($plan_id);
    $ticket_index = bvmgr_ticketing_v2_primary_ticket_index($cfg);
    if ($ticket_index < 0 || !isset($cfg['tickets'][$ticket_index]) || !is_array($cfg['tickets'][$ticket_index])) {
        return array();
    }

    return $cfg['tickets'][$ticket_index];
}

/**
 * Backward-compatible wrapper for legacy GA-only callers.
 */
function bvmgr_ticketing_v2_apply_ga_ticket_image_policy(int $product_id, int $plan_id): void
{
    $ticket = bvmgr_ticketing_v2_primary_ticket_config_for_plan($plan_id);
    if (empty($ticket)) {
        $ticket = array(
            'image_mode' => 'event_featured',
            'image_id' => 0,
        );
    }
    bvmgr_ticketing_v2_apply_ticket_image_policy($product_id, $plan_id, $ticket);
}

/**
 * Resolve the VMS/TEC event image that should be used by event-featured ticket rows.
 *
 * The public event image can live on the Event Plan, on the linked TEC event, or (as a
 * final legacy fallback) on the primary vendor. GA tickets previously looked only at
 * the Event Plan thumbnail, which let TEC-backed events keep their public image while
 * the GA Woo product/order line stayed imageless.
 */
function bvmgr_ticketing_v2_resolve_event_featured_image_id(int $plan_id): int
{
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return 0;
    }

    $plan_thumb = function_exists('get_post_thumbnail_id') ? absint(get_post_thumbnail_id($plan_id)) : 0;
    if ($plan_thumb > 0) {
        return $plan_thumb;
    }

    $tec_event_id = 0;
    if (function_exists('bvmgr_ticketing_b_get_linked_tec_event_id')) {
        $tec_event_id = absint(bvmgr_ticketing_b_get_linked_tec_event_id($plan_id));
    }
    if ($tec_event_id <= 0) {
        $tec_key = function_exists('bvmgr_ticketing_b_meta_key')
            ? bvmgr_ticketing_b_meta_key('tec_event_id', '_vms_tec_event_id')
            : '_vms_tec_event_id';
        $tec_event_id = absint(get_post_meta($plan_id, $tec_key, true));
    }
    if ($tec_event_id > 0 && function_exists('get_post_thumbnail_id')) {
        $tec_thumb = absint(get_post_thumbnail_id($tec_event_id));
        if ($tec_thumb > 0) {
            return $tec_thumb;
        }
    }

    $vendor_id = absint(get_post_meta($plan_id, '_vms_band_vendor_id', true));
    if ($vendor_id > 0 && function_exists('get_post_thumbnail_id')) {
        $vendor_thumb = absint(get_post_thumbnail_id($vendor_id));
        if ($vendor_thumb > 0) {
            return $vendor_thumb;
        }
    }

    return 0;
}

function bvmgr_ticketing_v2_ticket_config_for_product(int $product_id, int $plan_id): array
{
    $product_id = absint($product_id);
    $plan_id = absint($plan_id);
    if ($product_id <= 0 || $plan_id <= 0 || !function_exists('bvmgr_ticketing_v2_get_config')) {
        return array();
    }

    $ticket_key_meta = function_exists('bvmgr_ticketing_v2_product_meta_key')
        ? bvmgr_ticketing_v2_product_meta_key('ticketing_ticket_key')
        : '_vms_ticketing_ticket_key';
    $ticket_key = sanitize_key((string) get_post_meta($product_id, $ticket_key_meta, true));

    $cfg = bvmgr_ticketing_v2_get_config($plan_id);
    $tickets = is_array($cfg['tickets'] ?? null) ? array_values($cfg['tickets']) : array();
    if ($ticket_key !== '') {
        foreach ($tickets as $ticket) {
            if (!is_array($ticket)) {
                continue;
            }
            if (sanitize_key((string) ($ticket['ticket_key'] ?? '')) === $ticket_key) {
                return $ticket;
            }
        }
    }

    return bvmgr_ticketing_v2_primary_ticket_config_for_plan($plan_id);
}

function bvmgr_ticketing_v2_sync_ticket_product_image_with_result(int $product_id, int $plan_id = 0, array $ticket = array()): array
{
    $product_id = absint($product_id);
    $plan_id = absint($plan_id);

    $result = array(
        'status' => 'skipped',
        'product_id' => $product_id,
        'plan_id' => $plan_id,
        'ticket_key' => '',
        'image_id' => 0,
        'message' => '',
    );

    if ($product_id <= 0 || get_post_type($product_id) !== 'product') {
        $result['status'] = 'error_missing_product';
        $result['message'] = 'missing_product';
        return $result;
    }

    if ($plan_id <= 0) {
        $plan_meta_key = function_exists('bvmgr_ticketing_v2_product_meta_key')
            ? bvmgr_ticketing_v2_product_meta_key('event_plan_id')
            : '_vms_event_plan_id';
        $plan_id = absint(get_post_meta($product_id, $plan_meta_key, true));
        $result['plan_id'] = $plan_id;
    }

    if ($plan_id <= 0) {
        $result['status'] = 'error_missing_plan';
        $result['message'] = 'missing_event_plan_marker';
        return $result;
    }

    if (empty($ticket)) {
        $ticket = bvmgr_ticketing_v2_ticket_config_for_product($product_id, $plan_id);
    }
    if (empty($ticket)) {
        $ticket = array(
            'image_mode' => 'event_featured',
            'image_id' => 0,
        );
    }

    $result['ticket_key'] = sanitize_key((string) ($ticket['ticket_key'] ?? ''));
    $target_image_id = bvmgr_ticketing_v2_resolve_ticket_image_target_id($ticket, $plan_id);
    $result['image_id'] = $target_image_id;

    $current_thumb = function_exists('get_post_thumbnail_id') ? absint(get_post_thumbnail_id($product_id)) : 0;
    if ($current_thumb === $target_image_id) {
        $result['status'] = 'skipped_current';
        $result['message'] = 'image_already_current';
        return $result;
    }

    bvmgr_ticketing_v2_apply_ticket_image_policy($product_id, $plan_id, $ticket);

    if ($target_image_id > 0) {
        $result['status'] = 'updated';
        $result['message'] = 'updated';
    } elseif ($current_thumb > 0) {
        $result['status'] = 'cleared';
        $result['message'] = 'cleared_no_image_policy';
    } else {
        $result['status'] = 'skipped_no_image';
        $result['message'] = 'no_image_resolved';
    }

    return $result;
}

function bvmgr_entitlements_get_product_entitlement_id(int $product_id): string {
    $product_id = absint($product_id);
    if ($product_id <= 0) {
        return '';
    }

    $ent_meta_key = bvmgr_ticketing_v2_product_meta_key('ticketing_entitlement_id');
    $entitlement_id = sanitize_key((string) get_post_meta($product_id, $ent_meta_key, true));
    if ($entitlement_id !== '') {
        return $entitlement_id;
    }

    $plan_meta_key = bvmgr_ticketing_v2_product_meta_key('event_plan_id');
    $plan_id = absint(get_post_meta($product_id, $plan_meta_key, true));
    if ($plan_id <= 0) {
        return '';
    }

    $sync = bvmgr_ticketing_v2_get_sync($plan_id);
    $emap = is_array($sync['map']['entitlements'] ?? null) ? $sync['map']['entitlements'] : array();
    foreach ($emap as $candidate_entitlement_id => $row) {
        if (!is_array($row)) {
            continue;
        }
        $pid = absint($row['woo_product_id'] ?? 0);
        if ($pid !== $product_id) {
            continue;
        }
        $resolved = sanitize_key((string) $candidate_entitlement_id);
        if ($resolved !== '') {
            update_post_meta($product_id, $ent_meta_key, $resolved);
        }
        return $resolved;
    }

    return '';
}

function bvmgr_entitlements_get_linked_product_id(int $plan_id, string $entitlement_id): int {
    $plan_id = absint($plan_id);
    $entitlement_id = sanitize_key($entitlement_id);
    if ($plan_id <= 0 || $entitlement_id === '') {
        return 0;
    }

    $sync = bvmgr_ticketing_v2_get_sync($plan_id);
    $emap = is_array($sync['map']['entitlements'] ?? null) ? $sync['map']['entitlements'] : array();
    if (isset($emap[$entitlement_id]) && is_array($emap[$entitlement_id])) {
        $mapped_pid = absint($emap[$entitlement_id]['woo_product_id'] ?? 0);
        if ($mapped_pid > 0 && get_post_type($mapped_pid) === 'product') {
            return $mapped_pid;
        }
    }

    $found = bvmgr_ticketing_v2_find_entitlement_product($plan_id, $entitlement_id);
    if (($found['status'] ?? '') === 'found') {
        $pid = absint($found['product_id'] ?? 0);
        if ($pid > 0 && get_post_type($pid) === 'product') {
            return $pid;
        }
    }

    return 0;
}

function bvmgr_entitlements_map_by_id(array $cfg): array {
    $out = array();
    $ents = is_array($cfg['entitlements'] ?? null) ? $cfg['entitlements'] : array();
    foreach ($ents as $ent) {
        if (!is_array($ent)) {
            continue;
        }
        $entitlement_id = sanitize_key((string) ($ent['entitlement_id'] ?? ''));
        if ($entitlement_id === '') {
            continue;
        }
        $out[$entitlement_id] = $ent;
    }
    return $out;
}

function bvmgr_entitlements_image_signature(array $ent): string {
    $image_id = absint($ent['image_id'] ?? 0);
    $image_url = isset($ent['image_url']) ? esc_url_raw((string) $ent['image_url']) : '';
    return $image_id . '|' . $image_url;
}

function bvmgr_entitlements_sync_plan_image_changes(int $plan_id, array $cfg_before, array $cfg_after): array {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return array();
    }

    $before_map = bvmgr_entitlements_map_by_id($cfg_before);
    $after_map = bvmgr_entitlements_map_by_id($cfg_after);
    $entitlement_ids = array_values(array_unique(array_merge(array_keys($before_map), array_keys($after_map))));
    $results = array();

    foreach ($entitlement_ids as $entitlement_id) {
        $entitlement_id = sanitize_key((string) $entitlement_id);
        if ($entitlement_id === '') {
            continue;
        }

        $before_sig = isset($before_map[$entitlement_id]) ? bvmgr_entitlements_image_signature($before_map[$entitlement_id]) : '';
        $after_sig = isset($after_map[$entitlement_id]) ? bvmgr_entitlements_image_signature($after_map[$entitlement_id]) : '';
        if ($before_sig === $after_sig) {
            continue;
        }

        $pid = bvmgr_entitlements_get_linked_product_id($plan_id, $entitlement_id);
        if ($pid <= 0) {
            $res = array(
                'status' => 'skipped_missing_product',
                'product_id' => 0,
                'entitlement_id' => $entitlement_id,
                'plan_id' => $plan_id,
                'image_id' => 0,
                'message' => 'no_linked_product_for_image_change',
            );
            $results[] = $res;
            bvmgr_entitlements_sync_image_log(
                'entitlement_image_sync_plan_skipped',
                array(
                    'service' => 'ticketing',
                    'operation' => 'sync_image',
                    'stage' => 'resolve_product',
                    'status' => $res['status'],
                    'plan_id' => $plan_id,
                )
            );
            continue;
        }

        $results[] = bvmgr_entitlements_sync_product_image_with_result($pid, $entitlement_id);
    }

    return $results;
}

function bvmgr_ticketing_v2_stamp_product_markers(int $product_id, int $plan_id, int $tec_event_id, string $role, string $entitlement_id = ''): void {
    $product_id = absint($product_id);
    $plan_id = absint($plan_id);
    $tec_event_id = absint($tec_event_id);
    if ($product_id <= 0 || $plan_id <= 0) {
        return;
    }

    update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('event_plan_id'), $plan_id);
    if ($tec_event_id > 0) {
        update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('tec_event_id'), $tec_event_id);
    }

    update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('product_role'), $role);

    if ($entitlement_id !== '') {
        update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_entitlement_id'), $entitlement_id);
    }

    update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_marker_version'), 1);
    update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_source_plan_id'), $plan_id);
    update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_source_provider'), 'tec_tickets_woo');

    if (function_exists('bvmgr_square_firewall_protect_product')) {
        bvmgr_square_firewall_protect_product($product_id, true);
    }

    $kind = bvmgr_ticketing_v2_reporting_category_kind_for_role($role);
    if ($kind !== '') {
        bvmgr_ticketing_v2_apply_reporting_category($product_id, $kind);
    }
}

// ====================================================== 
// Inventory reconciliation (tickets + entitlements)
// - Prevents stock from being reset to capacity during Ticketing v2 “Commit”
// - Computes sold units from paid Woo orders and derives remaining = capacity - sold
// ======================================================

function bvmgr_ticketing_v2_paid_order_statuses(): array {
    $statuses = array();
    if (function_exists('wc_get_is_paid_statuses')) {
        $statuses = (array) wc_get_is_paid_statuses();
    }
    if (empty($statuses)) {
        $statuses = array('processing', 'completed');
    }

    foreach (array('processing', 'completed', 'on-hold') as $s) {
        if (!in_array($s, $statuses, true)) {
            $statuses[] = $s;
        }
    }

    // Filter paid statuses used for sold-qty reconciliation.
    // Return slugs without the “wc-” prefix.
    $statuses = apply_filters('vms_ticketing_v2_paid_statuses', $statuses);

    $out = array();
    foreach ((array) $statuses as $s) {
        $s = sanitize_key((string) $s);
        if ($s !== '') {
            $out[] = $s;
        }
    }
    $out = array_values(array_unique($out));
    return $out;
}

/**
 * Compute sold quantity for a Woo product using paid orders.
 * Returns: ['ok'=>bool, 'sold_qty'=>int, 'order_ids'=>int[], 'message'=>string]
 */
function bvmgr_ticketing_v2_table_exists(string $table_name): bool {
    static $cache = array();

    $table_name = trim($table_name);
    if ($table_name === '') {
        return false;
    }

    if (array_key_exists($table_name, $cache)) {
        return (bool) $cache[$table_name];
    }

    global $wpdb;
    if (!isset($wpdb) || !is_object($wpdb)) {
        $cache[$table_name] = false;
        return false;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- WooCommerce table capability probes have no core API equivalent; the prepared result is cached for the remainder of the request while remaining deployment-fresh on the first probe.
    $cache[$table_name] = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name);
    return (bool) $cache[$table_name];
}

function bvmgr_ticketing_v2_paid_order_statuses_with_prefix(array $statuses): array {
    $out = array();
    foreach ($statuses as $status) {
        $normalized = sanitize_key((string) $status);
        if ($normalized === '') {
            continue;
        }
        if (strpos($normalized, 'wc-') !== 0) {
            $normalized = 'wc-' . $normalized;
        }
        $out[] = $normalized;
    }

    return array_values(array_unique($out));
}

function bvmgr_ticketing_v2_calc_sold_qty_for_product_via_lookup(int $product_id, array $paid_statuses): ?int {
    global $wpdb;

    $lookup_table = $wpdb->prefix . 'wc_order_product_lookup';
    $stats_table = $wpdb->prefix . 'wc_order_stats';
    if (!bvmgr_ticketing_v2_table_exists($lookup_table) || !bvmgr_ticketing_v2_table_exists($stats_table)) {
        return null;
    }

    $paid_statuses = bvmgr_ticketing_v2_paid_order_statuses_with_prefix($paid_statuses);
    if (empty($paid_statuses)) {
        return 0;
    }

    $status_placeholders = implode(', ', array_fill(0, count($paid_statuses), '%s'));
    $sql = '
        SELECT COALESCE(SUM(product_lookup.product_qty), 0)
        FROM %i product_lookup
        INNER JOIN %i order_stats
            ON order_stats.order_id = product_lookup.order_id
        WHERE (product_lookup.product_id = %d OR product_lookup.variation_id = %d)
          AND order_stats.status IN (' . $status_placeholders . ')
    '; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The status placeholder list is derived only from the count of sanitized paid-status slugs; identifiers and values remain wpdb-prepared.

    $prepare_args = array_merge(array($lookup_table, $stats_table, $product_id, $product_id), $paid_statuses);
    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The aggregate query contains only prepared identifiers/values and a bounded placeholder list derived from sanitized status count.
    $prepared = $wpdb->prepare($sql, $prepare_args);
    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Sold-quantity reconciliation needs a fresh aggregate from WooCommerce lookup tables, and no WooCommerce API provides the equivalent product/variation result.
    $value = $wpdb->get_var($prepared);
    if ($value === null) {
        return null;
    }

    return max(0, (int) round((float) $value));
}

function bvmgr_ticketing_v2_calc_sold_qty_for_product_via_order_items(int $product_id, array $paid_statuses): ?int {
    global $wpdb;

    $oi = $wpdb->prefix . 'woocommerce_order_items';
    $oim = $wpdb->prefix . 'woocommerce_order_itemmeta';
    if (!bvmgr_ticketing_v2_table_exists($oi) || !bvmgr_ticketing_v2_table_exists($oim)) {
        return null;
    }

    $paid_statuses = bvmgr_ticketing_v2_paid_order_statuses_with_prefix($paid_statuses);
    if (empty($paid_statuses)) {
        return 0;
    }

    $order_stats_table = $wpdb->prefix . 'wc_order_stats';
    $status_placeholders = implode(', ', array_fill(0, count($paid_statuses), '%s'));
    $order_join = '';
    $order_status_sql = '';

    if (bvmgr_ticketing_v2_table_exists($order_stats_table)) {
        $order_join = $wpdb->prepare('INNER JOIN %i order_stats ON order_stats.order_id = line_items.order_id', $order_stats_table);
        $order_status_sql = "AND order_stats.status IN ({$status_placeholders})";
    } else {
        $order_join = $wpdb->prepare("INNER JOIN %i orders ON orders.ID = line_items.order_id AND orders.post_type = 'shop_order'", $wpdb->posts);
        $order_status_sql = "AND orders.post_status IN ({$status_placeholders})";
    }

    $sql = "
        SELECT COALESCE(SUM(GREATEST(0, line_items.qty - COALESCE(refunds.refunded_qty, 0))), 0)
        FROM (
            SELECT
                oi.order_item_id,
                oi.order_id,
                MAX(CASE WHEN oim.meta_key = '_product_id' THEN CAST(oim.meta_value AS UNSIGNED) ELSE 0 END) AS product_id,
                MAX(CASE WHEN oim.meta_key = '_variation_id' THEN CAST(oim.meta_value AS UNSIGNED) ELSE 0 END) AS variation_id,
                MAX(CASE WHEN oim.meta_key = '_qty' THEN CAST(oim.meta_value AS SIGNED) ELSE 0 END) AS qty
            FROM %i oi
            INNER JOIN %i oim
                ON oim.order_item_id = oi.order_item_id
            WHERE oi.order_item_type = 'line_item'
              AND oim.meta_key IN ('_product_id', '_variation_id', '_qty')
            GROUP BY oi.order_item_id, oi.order_id
            HAVING product_id = %d OR variation_id = %d
        ) line_items
        {$order_join}
        LEFT JOIN (
            SELECT
                CAST(refunded_item.meta_value AS UNSIGNED) AS refunded_item_id,
                SUM(ABS(CAST(refund_qty.meta_value AS SIGNED))) AS refunded_qty
            FROM %i refund_items
            INNER JOIN %i refunded_item
                ON refunded_item.order_item_id = refund_items.order_item_id
               AND refunded_item.meta_key = '_refunded_item_id'
            INNER JOIN %i refund_qty
                ON refund_qty.order_item_id = refund_items.order_item_id
               AND refund_qty.meta_key = '_qty'
            INNER JOIN %i refund_posts
                ON refund_posts.ID = refund_items.order_id
               AND refund_posts.post_type = 'shop_order_refund'
            WHERE refund_items.order_item_type = 'line_item'
            GROUP BY CAST(refunded_item.meta_value AS UNSIGNED)
        ) refunds
            ON refunds.refunded_item_id = line_items.order_item_id
        WHERE line_items.qty > 0
          {$order_status_sql}
    "; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Join/status fragments select one of two bounded WooCommerce storage branches; table identifiers, product IDs, and sanitized paid statuses remain wpdb-prepared.

    $prepare_args = array_merge(array($oi, $oim, $product_id, $product_id, $oi, $oim, $oim, $wpdb->posts), $paid_statuses);
    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The aggregate query contains only prepared identifiers/values plus bounded WooCommerce storage and status-placeholder fragments.
    $prepared = $wpdb->prepare($sql, $prepare_args);
    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Sold-quantity reconciliation needs current order/refund aggregates, and no WooCommerce API preserves the product/variation and refund-subtraction contract.
    $value = $wpdb->get_var($prepared);
    if ($value === null) {
        return null;
    }

    return max(0, (int) round((float) $value));
}

function bvmgr_ticketing_v2_calc_sold_qty_for_product(int $product_id): array {
    $product_id = absint($product_id);
    if ($product_id <= 0) {
        return array('ok' => false, 'sold_qty' => 0, 'order_ids' => array(), 'message' => 'invalid_product_id');
    }

    global $wpdb;
    if (!isset($wpdb) || !is_object($wpdb)) {
        return array('ok' => false, 'sold_qty' => 0, 'order_ids' => array(), 'message' => 'woocommerce_unavailable');
    }

    $paid_statuses = bvmgr_ticketing_v2_paid_order_statuses();
    $meta_total = max(0, (int) get_post_meta($product_id, 'total_sales', true));

    $sold = bvmgr_ticketing_v2_calc_sold_qty_for_product_via_order_items($product_id, $paid_statuses);
    $provider = 'order_item_sql';
    if ($sold === null) {
        $sold = bvmgr_ticketing_v2_calc_sold_qty_for_product_via_lookup($product_id, $paid_statuses);
        $provider = 'lookup';
    }
    if ($sold === null) {
        return array(
            'ok' => false,
            'sold_qty' => 0,
            'order_ids' => array(),
            'order_scan_sold_qty' => 0,
            'meta_total_sales' => $meta_total,
            'ignored_total_sales' => 0,
            'message' => 'sold_qty_query_unavailable',
        );
    }

    $sold = max(0, (int) $sold);
    $message = ($meta_total > $sold) ? 'ignored_stale_total_sales' : 'ok';
    if ($message === 'ok' && $sold <= 0) {
        $message = 'no_orders';
    }

    return array(
        'ok' => true,
        'sold_qty' => $sold,
        'order_ids' => array(),
        'order_scan_sold_qty' => $sold,
        'meta_total_sales' => $meta_total,
        'ignored_total_sales' => ($meta_total > $sold) ? 1 : 0,
        'provider' => $provider,
        'message' => $message,
    );
}

/**
 * Find product IDs by SKU (including trashed products).
 */
function bvmgr_ticketing_v2_find_product_ids_by_sku(string $sku): array {
    $sku = trim((string) $sku);
    if ($sku === '') {
        return array();
    }

    $ids = get_posts(array(
        'post_type' => 'product',
        'post_status' => array('publish', 'draft', 'private', 'trash'),
        'posts_per_page' => 25,
        'fields' => 'ids',
        'no_found_rows' => true,
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Duplicate-SKU recovery intentionally returns every bounded product-status match, including trash; the single-result WooCommerce SKU helper cannot preserve that contract.
        'meta_query' => array(
            array(
                'key' => '_sku',
                'value' => $sku,
                'compare' => '=',
            ),
        ),
    ));

    if (!is_array($ids)) {
        return array();
    }

    $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
    return $ids;
}


/**
 * Pick an existing entitlement product by SKU.
 *
 * This prevents stock from being reset if an operator re-commits ticketing config and the
 * sync map fails to match the previously sold product ID.
 */
function bvmgr_ticketing_v2_pick_entitlement_product_by_sku(string $sku, int $plan_id = 0, string $entitlement_id = ''): int {
    $sku = trim((string) $sku);
    $plan_id = absint($plan_id);
    $entitlement_id = sanitize_key((string) $entitlement_id);

    if ($sku === '' || !function_exists('bvmgr_ticketing_v2_find_product_ids_by_sku')) {
        return 0;
    }

    $ids = bvmgr_ticketing_v2_find_product_ids_by_sku($sku);
    if (empty($ids)) {
        return 0;
    }

    $k_role = function_exists('bvmgr_ticketing_v2_product_meta_key')
        ? bvmgr_ticketing_v2_product_meta_key('product_role')
        : '_vms_product_role';
    $k_plan = function_exists('bvmgr_ticketing_v2_product_meta_key')
        ? bvmgr_ticketing_v2_product_meta_key('event_plan_id')
        : '_vms_event_plan_id';
    $k_ent = function_exists('bvmgr_ticketing_v2_product_meta_key')
        ? bvmgr_ticketing_v2_product_meta_key('ticketing_entitlement_id')
        : '_vms_ticketing_entitlement_id';

    $cands = array();
    foreach ($ids as $pid) {
        $pid = absint($pid);
        if ($pid <= 0) {
            continue;
        }
        $role = (string) get_post_meta($pid, $k_role, true);
        $role = sanitize_key($role);
        if ($role !== '' && $role !== 'entitlement') {
            continue;
        }
        $cands[] = $pid;
    }
    if (empty($cands)) {
        $cands = $ids;
    }

    $best = 0;
    $best_sold = -1;
    $best_score = -1;

    foreach ($cands as $pid) {
        $pid = absint($pid);
        if ($pid <= 0) {
            continue;
        }

        $score = 0;
        if ($plan_id > 0 && absint(get_post_meta($pid, $k_plan, true)) === $plan_id) {
            $score += 10;
        }
        if ($entitlement_id !== '' && sanitize_key((string) get_post_meta($pid, $k_ent, true)) === $entitlement_id) {
            $score += 5;
        }

        $sold = 0;
        if (function_exists('bvmgr_ticketing_v2_calc_sold_qty_for_product')) {
            $res = bvmgr_ticketing_v2_calc_sold_qty_for_product($pid);
            if (!empty($res['ok'])) {
                $sold = max(0, absint($res['sold_qty'] ?? 0));
            }
        }

        $is_better = false;
        if ($sold > $best_sold) {
            $is_better = true;
        } elseif ($sold == $best_sold && $score > $best_score) {
            $is_better = true;
        } elseif ($sold == $best_sold && $score == $best_score && ($best == 0 || $pid < $best)) {
            $is_better = true;
        }

        if ($is_better) {
            $best = $pid;
            $best_sold = $sold;
            $best_score = $score;
        }
    }

    return absint($best);
}


/**
 * Compute sold quantity for an entitlement across all matching products (markers and SKU).
 */
function bvmgr_ticketing_v2_calc_sold_qty_for_entitlement_scope(int $plan_id, string $entitlement_id, string $sku = '', int $canonical_pid = 0): array {
    $plan_id = absint($plan_id);
    $entitlement_id = sanitize_key($entitlement_id);
    $canonical_pid = absint($canonical_pid);

    if ($plan_id <= 0 || $entitlement_id === '') {
        return array('ok' => false, 'sold_qty' => 0, 'product_ids' => array(), 'message' => 'invalid_scope');
    }

    $ids = array();
    if ($canonical_pid > 0) {
        $ids[] = $canonical_pid;
    }

    $marker_ids = get_posts(array(
        'post_type' => 'product',
        'post_status' => array('publish', 'draft', 'private', 'trash'),
        'posts_per_page' => 25,
        'fields' => 'ids',
        'no_found_rows' => true,
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Entitlement reconciliation requires the bounded intersection of plan and entitlement markers before combining canonical and SKU-derived product IDs.
        'meta_query' => array(
            array(
                'key' => bvmgr_ticketing_v2_product_meta_key('event_plan_id'),
                'value' => $plan_id,
                'compare' => '=',
            ),
            array(
                'key' => bvmgr_ticketing_v2_product_meta_key('ticketing_entitlement_id'),
                'value' => $entitlement_id,
                'compare' => '=',
            ),
        ),
    ));
    if (is_array($marker_ids)) {
        $ids = array_merge($ids, $marker_ids);
    }

    if ($sku !== '') {
        $ids = array_merge($ids, bvmgr_ticketing_v2_find_product_ids_by_sku($sku));
    }

    $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
    if (empty($ids)) {
        return array('ok' => true, 'sold_qty' => 0, 'product_ids' => array(), 'message' => 'no_products');
    }

    $sold = 0;
    $errors = 0;
    $ignored_total_sales_count = 0;
    $ignored_total_sales_products = array();
    foreach ($ids as $pid) {
        $res = bvmgr_ticketing_v2_calc_sold_qty_for_product($pid);
        if (empty($res['ok'])) {
            $errors++;
            continue;
        }
        $sold += max(0, absint($res['sold_qty'] ?? 0));
        if (!empty($res['ignored_total_sales'])) {
            $ignored_total_sales_count++;
            $ignored_total_sales_products[] = $pid;
        }
    }

    $msg = ($errors > 0) ? ('partial_errors=' . (int) $errors) : 'ok';
    return array(
        'ok' => true,
        'sold_qty' => max(0, (int) $sold),
        'product_ids' => $ids,
        'ignored_total_sales_count' => $ignored_total_sales_count,
        'ignored_total_sales_products' => array_values(array_unique(array_map('absint', $ignored_total_sales_products))),
        'message' => $msg,
    );
}


function bvmgr_ticketing_v2_find_entitlement_product(int $plan_id, string $entitlement_id): array {
    $plan_id = absint($plan_id);
    $entitlement_id = sanitize_key($entitlement_id);
    if ($plan_id <= 0 || $entitlement_id === '') {
        return array('status' => 'none', 'product_id' => 0);
    }

    $args = array(
        'post_type' => 'product',
        'post_status' => array('publish', 'draft', 'private'),
        'fields' => 'ids',
        'posts_per_page' => 5,
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Entitlement lookup requires an exact bounded intersection of the plan and entitlement marker keys so ambiguity remains visible to callers.
        'meta_query' => array(
            array(
                'key' => bvmgr_ticketing_v2_product_meta_key('event_plan_id'),
                'value' => $plan_id,
                'compare' => '=',
            ),
            array(
                'key' => bvmgr_ticketing_v2_product_meta_key('ticketing_entitlement_id'),
                'value' => $entitlement_id,
                'compare' => '=',
            ),
        ),
    );

    $ids = get_posts($args);
    if (!is_array($ids) || empty($ids)) {
        return array('status' => 'none', 'product_id' => 0);
    }

    $ids = array_values(array_map('absint', $ids));
    $ids = array_filter($ids);

    if (count($ids) === 1) {
        return array('status' => 'found', 'product_id' => (int) $ids[0]);
    }

    return array('status' => 'ambiguous', 'product_id' => 0, 'candidates' => $ids);
}



// ====================================================== 
// Legacy SKU / duplicate suppression (SR-* products)
// ======================================================

function bvmgr_ticketing_v2_legacy_token_for_entitlement_key(string $entitlement_key): string {
    $k = sanitize_key($entitlement_key);
    if ($k === '') {
        return '';
    }

    if (preg_match('/^table_(\d{1,2})$/', $k, $m)) {
        $n = (int) $m[1];
        if ($n <= 0) {
            return '';
        }
        return 'TB' . $n;
    }

    if (preg_match('/^fire_pit_(\d{1,2})$/', $k, $m)) {
        $n = (int) $m[1];
        if ($n <= 0) {
            return '';
        }
        return 'FP' . $n;
    }

    if ($k === 'pool') {
        return 'POOL';
    }

    return '';
}

function bvmgr_ticketing_v2_entitlement_key_from_sku(string $sku): string {
    $sku = trim((string) $sku);
    if ($sku === '') {
        return '';
    }

    if (preg_match('/TB(\d{1,2})/i', $sku, $m)) {
        $n = (int) $m[1];
        if ($n > 0) {
            return 'table_' . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
        }
    }

    if (preg_match('/FP(\d{1,2})/i', $sku, $m)) {
        $n = (int) $m[1];
        if ($n > 0) {
            return 'fire_pit_' . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
        }
    }

    if (stripos($sku, 'POOL') !== false) {
        return 'pool';
    }

    return '';
}

function bvmgr_ticketing_v2_legacy_sku_event_needle(int $plan_id, int $tec_event_id): string {
    $plan_id = absint($plan_id);
    $tec_event_id = absint($tec_event_id);

    $date = '';
    if ($plan_id > 0) {
        $candidate = (string) get_post_meta($plan_id, '_vms_event_date', true);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate)) {
            $date = $candidate;
        }
    }

    $slug = '';
    if ($tec_event_id > 0) {
        $raw = (string) get_post_field('post_name', $tec_event_id);
        if ($raw === '') {
            $raw = (string) get_the_title($tec_event_id);
        }
        $slug = sanitize_title($raw);
    }

    if ($date !== '' && $slug !== '') {
        return $date . '_' . $slug;
    }
    if ($date !== '') {
        return $date;
    }
    if ($slug !== '') {
        return $slug;
    }
    return '';
}

function bvmgr_ticketing_v2_parse_legacy_sku_event_hint(string $sku): array {
    $sku = trim((string) $sku);
    if ($sku === '') {
        return array('date' => '', 'slug' => '');
    }

    if (preg_match('/(\d{4}-\d{2}-\d{2})_([a-z0-9\-]+)/i', $sku, $m)) {
        $date = (string) $m[1];
        $slug = sanitize_title((string) $m[2]);
        return array('date' => $date, 'slug' => $slug);
    }

    return array('date' => '', 'slug' => '');
}

function bvmgr_ticketing_v2_find_plan_id_by_tec_event(int $tec_event_id): int {
    $tec_event_id = absint($tec_event_id);
    if ($tec_event_id <= 0 || !post_type_exists('vms_event_plan')) {
        return 0;
    }

    $k_tec = function_exists('bvmgr_ticketing_b_meta_key')
        ? bvmgr_ticketing_b_meta_key('tec_event_id', '_vms_tec_event_id')
        : '_vms_tec_event_id';

    $args = array(
        'post_type' => 'vms_event_plan',
        'post_status' => array('publish', 'draft', 'private'),
        'fields' => 'ids',
        'posts_per_page' => 1,
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Legacy plan recovery performs one bounded exact lookup by the configured TEC event marker when no direct relationship API exists.
        'meta_query' => array(
            array(
                'key' => $k_tec,
                'value' => $tec_event_id,
                'compare' => '=',
            ),
        ),
    );

    $ids = get_posts($args);
    if (!is_array($ids) || empty($ids)) {
        return 0;
    }
    return absint($ids[0]);
}


function bvmgr_ticketing_v2_find_legacy_entitlement_product_by_key(int $plan_id, int $tec_event_id, string $entitlement_key): array {
    $plan_id = absint($plan_id);
    $tec_event_id = absint($tec_event_id);
    $token = bvmgr_ticketing_v2_legacy_token_for_entitlement_key($entitlement_key);

    if ($plan_id <= 0 || $tec_event_id <= 0 || $token === '') {
        return array('status' => 'none', 'product_id' => 0);
    }

    $needle = bvmgr_ticketing_v2_legacy_sku_event_needle($plan_id, $tec_event_id);

    $or = array(
        'relation' => 'OR',
        array(
            'relation' => 'AND',
            array(
                'key' => bvmgr_ticketing_v2_product_meta_key('event_plan_id'),
                'value' => $plan_id,
                'compare' => '=',
            ),
            array(
                'key' => bvmgr_ticketing_v2_product_meta_key('tec_event_id'),
                'value' => $tec_event_id,
                'compare' => '=',
            ),
        ),
        array(
            'key' => bvmgr_ticketing_v2_product_meta_key('tec_event_id'),
            'value' => $tec_event_id,
            'compare' => '=',
        ),
        array(
            'key' => '_tribe_wooticket_for_event',
            'value' => $tec_event_id,
            'compare' => '=',
        ),
    );

    if ($needle !== '') {
        $or[] = array(
            'key' => '_sku',
            'value' => $needle,
            'compare' => 'LIKE',
        );
    }

    $args = array(
        'post_type' => 'product',
        'post_status' => array('publish', 'draft', 'private'),
        'fields' => 'ids',
        'posts_per_page' => 10,
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Legacy entitlement recovery must combine bounded SKU-token and event-marker alternatives to distinguish one reusable product from ambiguous duplicates.
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => '_sku',
                'value' => 'SR-',
                'compare' => 'LIKE',
            ),
            array(
                'key' => '_sku',
                'value' => $token,
                'compare' => 'LIKE',
            ),
            $or,
        ),
    );

    $ids = get_posts($args);
    if (!is_array($ids) || empty($ids)) {
        return array('status' => 'none', 'product_id' => 0);
    }

    $ids = array_values(array_filter(array_map('absint', $ids)));
    if (count($ids) === 1) {
        return array('status' => 'found', 'product_id' => (int) $ids[0]);
    }

    return array('status' => 'ambiguous', 'product_id' => 0, 'candidates' => $ids);
}

function bvmgr_ticketing_v2_retire_legacy_duplicate_product(int $legacy_product_id, int $canonical_product_id, string $reason = 'legacy_duplicate'): bool {
    $legacy_product_id = absint($legacy_product_id);
    $canonical_product_id = absint($canonical_product_id);

    if ($legacy_product_id <= 0 || $canonical_product_id <= 0) {
        return false;
    }

    // Make the legacy product non-purchasable/hidden while keeping it in the DB (reversible).
    try {
        $did = false;

        if (function_exists('wc_get_product')) {
            $p = wc_get_product($legacy_product_id);
            if ($p) {
                if (method_exists($p, 'set_status')) {
                    $p->set_status('draft');
                }
                if (method_exists($p, 'set_catalog_visibility')) {
                    $p->set_catalog_visibility('hidden');
                }
                $p->save();
                $did = true;
            }
        }

        if (!$did) {
            wp_update_post(array(
                'ID' => $legacy_product_id,
                'post_status' => 'draft',
            ));
            update_post_meta($legacy_product_id, '_visibility', 'hidden');
        }

        update_post_meta($legacy_product_id, '_vms_legacy_retired', 1);
        update_post_meta($legacy_product_id, '_vms_legacy_retired_reason', sanitize_key($reason));
        update_post_meta($legacy_product_id, '_vms_legacy_duplicate_of', $canonical_product_id);
        update_post_meta($legacy_product_id, '_vms_legacy_retired_at_gmt', time());
        update_post_meta($legacy_product_id, '_vms_legacy_retired_by', (int) get_current_user_id());

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function bvmgr_ticketing_v2_cleanup_legacy_sr_duplicates(int $plan_id, int $tec_event_id, array $cfg, array $sync_map): array {
    $plan_id = absint($plan_id);
    $tec_event_id = absint($tec_event_id);

    if ($plan_id <= 0 || $tec_event_id <= 0) {
        return array('ok' => true, 'retired' => array(), 'warnings' => array());
    }

    $ent_key_to_pid = array();
    $ents = is_array($cfg['entitlements'] ?? null) ? $cfg['entitlements'] : array();
    foreach ($ents as $ent) {
        if (!is_array($ent) || empty($ent['enabled'])) {
            continue;
        }
        $ent_id = sanitize_key((string) ($ent['entitlement_id'] ?? ''));
        $ent_key = sanitize_key((string) ($ent['entitlement_key'] ?? ''));
        if ($ent_id === '' || $ent_key === '') {
            continue;
        }
        $mapped_pid = 0;
        if (isset($sync_map['entitlements']) && is_array($sync_map['entitlements']) && isset($sync_map['entitlements'][$ent_id]) && is_array($sync_map['entitlements'][$ent_id])) {
            $mapped_pid = absint($sync_map['entitlements'][$ent_id]['woo_product_id'] ?? 0);
        }
        if ($mapped_pid > 0) {
            $ent_key_to_pid[$ent_key] = $mapped_pid;
        }
    }

    if (empty($ent_key_to_pid)) {
        return array('ok' => true, 'retired' => array(), 'warnings' => array());
    }

    $needle = bvmgr_ticketing_v2_legacy_sku_event_needle($plan_id, $tec_event_id);

    $or = array(
        'relation' => 'OR',
        array(
            'relation' => 'AND',
            array(
                'key' => bvmgr_ticketing_v2_product_meta_key('event_plan_id'),
                'value' => $plan_id,
                'compare' => '=',
            ),
            array(
                'key' => bvmgr_ticketing_v2_product_meta_key('tec_event_id'),
                'value' => $tec_event_id,
                'compare' => '=',
            ),
        ),
        array(
            'key' => bvmgr_ticketing_v2_product_meta_key('tec_event_id'),
            'value' => $tec_event_id,
            'compare' => '=',
        ),
        array(
            'key' => '_tribe_wooticket_for_event',
            'value' => $tec_event_id,
            'compare' => '=',
        ),
    );

    if ($needle !== '') {
        $or[] = array(
            'key' => '_sku',
            'value' => $needle,
            'compare' => 'LIKE',
        );
    }

    $args = array(
        'post_type' => 'product',
        'post_status' => array('publish', 'draft', 'private'),
        'fields' => 'ids',
        'posts_per_page' => 200,
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Legacy duplicate cleanup intentionally inspects a bounded SR-* candidate set across event-marker alternatives before applying reversible retirement metadata.
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => '_sku',
                'value' => 'SR-',
                'compare' => 'LIKE',
            ),
            $or,
        ),
    );

    $legacy_ids = get_posts($args);
    if (!is_array($legacy_ids) || empty($legacy_ids)) {
        return array('ok' => true, 'retired' => array(), 'warnings' => array());
    }

    $retired = array();
    $warnings = array();

    foreach ($legacy_ids as $legacy_pid) {
        $legacy_pid = absint($legacy_pid);
        if ($legacy_pid <= 0) {
            continue;
        }

        // Skip if already marked retired.
        if ((string) get_post_meta($legacy_pid, '_vms_legacy_retired', true) === '1') {
            continue;
        }

        $sku = (string) get_post_meta($legacy_pid, '_sku', true);
        $ent_key = bvmgr_ticketing_v2_entitlement_key_from_sku($sku);
        if ($ent_key === '' || !isset($ent_key_to_pid[$ent_key])) {
            continue;
        }

        $canonical_pid = absint($ent_key_to_pid[$ent_key]);
        if ($canonical_pid <= 0 || $canonical_pid === $legacy_pid) {
            continue;
        }

        $ok = bvmgr_ticketing_v2_retire_legacy_duplicate_product($legacy_pid, $canonical_pid, 'sr_legacy_duplicate');
        if ($ok) {
            $retired[] = array('legacy_product_id' => $legacy_pid, 'canonical_product_id' => $canonical_pid, 'sku' => $sku);
        } else {
            $warnings[] = 'Failed to retire legacy duplicate product #' . $legacy_pid . ' (SKU: ' . $sku . ').';
        }
    }

    if (!empty($retired)) {
        $sample = array_slice($retired, 0, 3);
        $sample_txt = array();
        foreach ($sample as $r) {
            $sample_txt[] = '#' . absint($r['legacy_product_id']);
        }
        $msg = 'Retired legacy SR-* duplicate products: ' . implode(', ', $sample_txt);
        if (count($retired) > 3) {
            $msg .= ' (+' . (count($retired) - 3) . ' more)';
        }
        $warnings[] = $msg;
    }

    return array('ok' => true, 'retired' => $retired, 'warnings' => $warnings);
}

function bvmgr_ticketing_v2_legacy_cleanup_cron_init(): void {
    if (function_exists('bvmgr_should_run_runtime_maintenance') && !bvmgr_should_run_runtime_maintenance()) {
        return;
    }
    if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
        return;
    }

    if (!wp_next_scheduled('vms_ticketing_v2_legacy_cleanup')) {
        // Give the site a few minutes after deploy before the first run.
        wp_schedule_event(time() + 300, 'hourly', 'vms_ticketing_v2_legacy_cleanup');
    }
}
add_action('init', 'bvmgr_ticketing_v2_legacy_cleanup_cron_init');

function bvmgr_ticketing_v2_legacy_cleanup_runner(): void {
    if (!post_type_exists('product')) {
        return;
    }

    // Only scan published products to keep the cron lightweight.
    $args = array(
        'post_type' => 'product',
        'post_status' => array('publish'),
        'fields' => 'ids',
        'posts_per_page' => 200,
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The hourly legacy cleanup begins with a bounded published SR-* SKU candidate scan, then validates event and plan identity before any reversible mutation.
        'meta_query' => array(
            array(
                'key' => '_sku',
                'value' => 'SR-',
                'compare' => 'LIKE',
            ),
        ),
    );

    $pids = get_posts($args);
    if (!is_array($pids) || empty($pids)) {
        return;
    }

    $plan_to_tec = array();

    foreach ($pids as $pid) {
        $pid = absint($pid);
        if ($pid <= 0) {
            continue;
        }

        $sku = (string) get_post_meta($pid, '_sku', true);
        $hint = bvmgr_ticketing_v2_parse_legacy_sku_event_hint($sku);
        $date = is_array($hint) ? (string) ($hint['date'] ?? '') : '';
        $slug = is_array($hint) ? (string) ($hint['slug'] ?? '') : '';

        if ($date === '' || $slug === '' || !post_type_exists('tribe_events')) {
            continue;
        }

        $event = get_page_by_path($slug, OBJECT, 'tribe_events');
        if (!$event || empty($event->ID)) {
            continue;
        }

        $tec_event_id = absint($event->ID);
        if ($tec_event_id <= 0) {
            continue;
        }

        $start = (string) get_post_meta($tec_event_id, '_EventStartDate', true);
        if ($start !== '' && strpos($start, $date) !== 0) {
            continue;
        }

        $plan_id = bvmgr_ticketing_v2_find_plan_id_by_tec_event($tec_event_id);
        if ($plan_id <= 0) {
            continue;
        }

        $plan_to_tec[$plan_id] = $tec_event_id;
    }

    if (empty($plan_to_tec)) {
        return;
    }

    foreach ($plan_to_tec as $plan_id => $tec_event_id) {
        $plan_id = absint($plan_id);
        $tec_event_id = absint($tec_event_id);

        $cfg = bvmgr_ticketing_v2_get_config($plan_id);
        if (!is_array($cfg) || (string) ($cfg['mode'] ?? '') !== 'vms_managed') {
            continue;
        }

        $sync = bvmgr_ticketing_v2_get_sync($plan_id);
        $sync_map = (isset($sync['map']) && is_array($sync['map'])) ? $sync['map'] : array();

        bvmgr_ticketing_v2_cleanup_legacy_sr_duplicates($plan_id, $tec_event_id, $cfg, $sync_map);
    }
}
add_action('vms_ticketing_v2_legacy_cleanup', 'bvmgr_ticketing_v2_legacy_cleanup_runner');
function bvmgr_ticketing_v2_ticket_to_tier_like(array $ticket): array {
    $visibility_mode = sanitize_key((string) ($ticket['visibility_mode'] ?? 'public'));
    if (!in_array($visibility_mode, array('public', 'login', 'verified'), true)) {
        $visibility_mode = 'public';
    }

    $verified_program = sanitize_key((string) ($ticket['verified_program'] ?? ''));
    $allowed_programs = bvmgr_ticketing_v2_normalize_allowed_programs($ticket['allowed_programs'] ?? array(), $verified_program);
    if ($visibility_mode !== 'verified') {
        $verified_program = '';
        $allowed_programs = array();
    } elseif ($verified_program === '' && !empty($allowed_programs)) {
        $verified_program = (string) $allowed_programs[0];
    }

    return array(
        'tier_key' => sanitize_key((string) ($ticket['ticket_key'] ?? 'ticket')),
        'name' => bvmgr_ticketing_v2_sanitize_plain_text_label($ticket['title'] ?? 'GA Admission'),
        'price' => bvmgr_ticketing_v2_money_string($ticket['price'] ?? '0'),
        'early_price' => bvmgr_ticketing_v2_money_string($ticket['early_price'] ?? '', ''),
        'early_price_start' => sanitize_text_field((string) ($ticket['early_price_start'] ?? '')),
        'early_price_end' => sanitize_text_field((string) ($ticket['early_price_end'] ?? '')),
        'early_price_start_relative_days' => bvmgr_ticketing_v2_normalize_relative_days($ticket['early_price_start_relative_days'] ?? ''),
        'early_price_end_relative_days' => bvmgr_ticketing_v2_normalize_relative_days($ticket['early_price_end_relative_days'] ?? ''),
        'capacity' => max(0, (int) ($ticket['inventory_total'] ?? 0)),
        'sales_start' => sanitize_text_field((string) ($ticket['sales_start'] ?? '')),
        'sales_end' => sanitize_text_field((string) ($ticket['sales_end'] ?? '')),
        'sales_start_relative_days' => bvmgr_ticketing_v2_normalize_relative_days($ticket['sales_start_relative_days'] ?? ''),
        'sales_end_relative_days' => bvmgr_ticketing_v2_normalize_relative_days($ticket['sales_end_relative_days'] ?? ''),
        'sort_order' => bvmgr_ticketing_b_normalize_sort_order($ticket['sort_order'] ?? 0, 10),
        'is_hidden' => false,
        'counts_toward_attendance' => !empty($ticket['counts_toward_unlock']),
        'qualifies_for_discounts' => ($visibility_mode === 'verified'),
        'qualification_code' => $verified_program,
        'ratio_rule_enabled' => !empty($ticket['ratio_rule_enabled']),
        'ratio_rule_max_per_qualifying' => max(0, absint($ticket['ratio_rule_max_per_qualifying'] ?? 0)),
        'ratio_rule_qualifier_mode' => sanitize_key((string) ($ticket['ratio_rule_qualifier_mode'] ?? 'counts_toward_unlock')),
        'ratio_rule_group' => sanitize_title((string) ($ticket['ratio_rule_group'] ?? '')),
    );
}

function bvmgr_ticketing_v2_stamp_ticket_runtime_meta(int $product_id, int $tec_event_id, array $ticket): void {
    $product_id = absint($product_id);
    $tec_event_id = absint($tec_event_id);
    if ($product_id <= 0) {
        return;
    }

    $ticket_key = sanitize_key((string) ($ticket['ticket_key'] ?? ''));
    $counts_toward_unlock = !empty($ticket['counts_toward_unlock']) ? '1' : '0';
    $max_qty_per_order = max(0, absint($ticket['max_qty_per_order'] ?? 0));
    $ratio_rule_enabled = !empty($ticket['ratio_rule_enabled']);
    $ratio_rule_max_per_qualifying = max(0, absint($ticket['ratio_rule_max_per_qualifying'] ?? 0));
    if (!$ratio_rule_enabled || $ratio_rule_max_per_qualifying <= 0) {
        $ratio_rule_enabled = false;
        $ratio_rule_max_per_qualifying = 0;
    }
    $ratio_rule_qualifier_mode = sanitize_key((string) ($ticket['ratio_rule_qualifier_mode'] ?? 'counts_toward_unlock'));
    if (!in_array($ratio_rule_qualifier_mode, array('counts_toward_unlock'), true)) {
        $ratio_rule_qualifier_mode = 'counts_toward_unlock';
    }
    $ratio_rule_group = sanitize_title((string) ($ticket['ratio_rule_group'] ?? ''));
    if (!$ratio_rule_enabled) {
        $ratio_rule_group = '';
    }
    $visibility_mode = sanitize_key((string) ($ticket['visibility_mode'] ?? 'public'));
    if (!in_array($visibility_mode, array('public', 'login', 'verified'), true)) {
        $visibility_mode = 'public';
    }
    $verified_program = sanitize_key((string) ($ticket['verified_program'] ?? ''));
    $allowed_programs = bvmgr_ticketing_v2_normalize_allowed_programs($ticket['allowed_programs'] ?? array(), $verified_program);
    $allow_direct_grants = bvmgr_ticketing_v2_truthy($ticket['allow_direct_grants'] ?? false, false);
    $claim_grant_type = sanitize_key((string) ($ticket['claim_grant_type'] ?? 'event_ticket_eligibility'));
    $allowed_claim_grant_types = function_exists('bvmgr_ticketing_claims_allowed_grant_types')
        ? (array) bvmgr_ticketing_claims_allowed_grant_types()
        : array('event_ticket_eligibility', 'event_free_admit', 'credential_benefit_override', 'event_grant');
    if (!in_array($claim_grant_type, $allowed_claim_grant_types, true)) {
        $claim_grant_type = 'event_ticket_eligibility';
    }
    $claims_per_assignee = max(0, absint($ticket['claims_per_assignee'] ?? 1));
    $require_assignee_email = bvmgr_ticketing_v2_truthy($ticket['require_assignee_email'] ?? true, true);
    if ($visibility_mode !== 'verified') {
        $verified_program = '';
        $allowed_programs = array();
        $allow_direct_grants = false;
        $claim_grant_type = 'event_ticket_eligibility';
        $claims_per_assignee = 1;
        $require_assignee_email = true;
    } elseif ($verified_program === '' && !empty($allowed_programs)) {
        $verified_program = (string) $allowed_programs[0];
    }

    if ($ticket_key !== '') {
        update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_ticket_key'), $ticket_key);
        update_post_meta($product_id, '_vms_ticket_key', $ticket_key);
    }
    if ($tec_event_id > 0) {
        update_post_meta($product_id, '_vms_ticket_event_id', $tec_event_id);
    }
    update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_counts_toward_unlock'), $counts_toward_unlock);
    update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_visibility_mode'), $visibility_mode);
    if ($verified_program !== '') {
        update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_verified_program'), $verified_program);
    } else {
        delete_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_verified_program'));
    }
    if (!empty($allowed_programs)) {
        update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_allowed_programs'), implode(',', $allowed_programs));
    } else {
        delete_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_allowed_programs'));
    }
    update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_allow_direct_grants'), $allow_direct_grants ? '1' : '0');
    if ($visibility_mode === 'verified') {
        update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_claim_grant_type'), $claim_grant_type);
        update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_claims_per_assignee'), (string) $claims_per_assignee);
        update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_require_assignee_email'), $require_assignee_email ? '1' : '0');
    } else {
        delete_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_claim_grant_type'));
        delete_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_claims_per_assignee'));
        delete_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_require_assignee_email'));
    }
    if ($max_qty_per_order > 0) {
        update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_max_qty_per_order'), (string) $max_qty_per_order);
    } else {
        delete_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_max_qty_per_order'));
    }
    if ($ratio_rule_enabled && $ratio_rule_max_per_qualifying > 0) {
        update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_ratio_rule_enabled'), '1');
        update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_ratio_rule_max_per_qualifying'), (string) $ratio_rule_max_per_qualifying);
        update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_ratio_rule_qualifier_mode'), $ratio_rule_qualifier_mode);
        if ($ratio_rule_group !== '') {
            update_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_ratio_rule_group'), $ratio_rule_group);
        } else {
            delete_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_ratio_rule_group'));
        }
    } else {
        delete_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_ratio_rule_enabled'));
        delete_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_ratio_rule_max_per_qualifying'));
        delete_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_ratio_rule_qualifier_mode'));
        delete_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_ratio_rule_group'));
    }
}


function bvmgr_ticketing_v2_maybe_mark_primary_ticket_as_rsvp(int $product_id, string $ticket_key, string $primary_ticket_key, array $ticket_cfg): void
{
    $product_id = absint($product_id);
    $ticket_key = sanitize_key($ticket_key);
    $primary_ticket_key = sanitize_key($primary_ticket_key);

    if ($product_id <= 0 || $primary_ticket_key === '' || $ticket_key === '' || $ticket_key !== $primary_ticket_key) {
        return;
    }

    $visibility_mode = sanitize_key((string) ($ticket_cfg['visibility_mode'] ?? 'public'));
    $price = (float) ($ticket_cfg['price'] ?? 0);

    if ($visibility_mode === 'public' && $price <= 0) {
        update_post_meta($product_id, '_vms_is_rsvp', 'yes');
    } else {
        // Avoid accidentally labeling paid or qualified/free special tickets as RSVP.
        delete_post_meta($product_id, '_vms_is_rsvp');
    }
}

function bvmgr_ticketing_v2_apply_ticket_to_product(int $product_id, int $tec_event_id, array $ticket): array {
    $product_id = absint($product_id);
    $tec_event_id = absint($tec_event_id);
    if ($product_id <= 0 || $tec_event_id <= 0) {
        return array('ok' => false, 'message' => 'invalid_ids');
    }

    $tier_like = bvmgr_ticketing_v2_ticket_to_tier_like($ticket);
    return bvmgr_ticketing_b_apply_update_to_product($product_id, $tier_like, $tec_event_id);
}

function bvmgr_ticketing_v2_create_ticket(int $tec_event_id, array $ticket): array {
    $tec_event_id = absint($tec_event_id);
    if ($tec_event_id <= 0) {
        return array('ok' => false, 'message' => 'invalid_tec_event');
    }
    if (!function_exists('tribe_tickets')) {
        return array('ok' => false, 'message' => 'tribe_tickets_missing');
    }

    $tier_like = bvmgr_ticketing_v2_ticket_to_tier_like($ticket);
    $created = bvmgr_ticketing_b_create_woo_ticket($tec_event_id, $tier_like);
    if (empty($created['ok'])) {
        return array('ok' => false, 'message' => (string) ($created['message'] ?? 'create_failed'));
    }

    $product_id = absint($created['woo_product_id'] ?? 0);
    if ($product_id <= 0) {
        $product_id = absint($created['ticket_id'] ?? 0);
    }
    if ($product_id <= 0 || get_post_type($product_id) !== 'product') {
        return array('ok' => false, 'message' => 'not_a_product');
    }

    $upd = bvmgr_ticketing_b_apply_update_to_product($product_id, $tier_like, $tec_event_id);
    if (empty($upd['ok'])) {
        return array(
            'ok' => false,
            'message' => (string) ($upd['message'] ?? 'created_but_update_failed'),
            'woo_product_id' => $product_id,
            'tec_ticket_id' => absint($created['ticket_id'] ?? $product_id),
        );
    }

    return array_merge($upd, array(
        'ok' => true,
        'woo_product_id' => $product_id,
        'tec_ticket_id' => absint($created['ticket_id'] ?? $product_id),
    ));
}

function bvmgr_ticketing_v2_get_product_catalog_visibility_state(int $product_id): string {
    $product_id = absint($product_id);
    if ($product_id <= 0) {
        return '';
    }

    $legacy_visibility = sanitize_key((string) get_post_meta($product_id, '_visibility', true));
    if ($legacy_visibility === 'hidden') {
        return 'hidden';
    }

    $taxonomy_checked = false;
    if (taxonomy_exists('product_visibility')) {
        $taxonomy_checked = true;
        $terms = wp_get_object_terms($product_id, 'product_visibility', array('fields' => 'slugs'));
        if (is_array($terms)) {
            $terms = array_map('sanitize_key', $terms);
            if (in_array('exclude-from-catalog', $terms, true) && in_array('exclude-from-search', $terms, true)) {
                return 'hidden';
            }
        }
    }

    if (function_exists('wc_get_product')) {
        $product = wc_get_product($product_id);
        if ($product && method_exists($product, 'get_catalog_visibility')) {
            $visibility = sanitize_key((string) $product->get_catalog_visibility());
            if ($visibility === 'hidden' && $taxonomy_checked) {
                $visibility = '';
            }
            if (in_array($visibility, array('visible', 'catalog', 'search', 'hidden'), true)) {
                return $visibility;
            }
        }
    }

    if (in_array($legacy_visibility, array('visible', 'catalog', 'search', 'hidden'), true)) {
        return $legacy_visibility;
    }

	return '';
}

function bvmgr_ticketing_v2_push_inventory_write_context(array $context): void {
    if (!function_exists('bvmgr_ticket_mutation_audit_push_context')) {
        return;
    }

    if (empty($context['source_hook'])) {
        $context['source_hook'] = sanitize_key((string) current_filter());
    }
    if (empty($context['requested_result_status'])) {
        $context['requested_result_status'] = 'success';
    }
    if (empty($context['summary_text']) && !empty($context['reason_text'])) {
        $context['summary_text'] = (string) $context['reason_text'];
    }

    bvmgr_ticket_mutation_audit_push_context($context);
}

function bvmgr_ticketing_v2_pop_inventory_write_context(): void {
    if (function_exists('bvmgr_ticket_mutation_audit_pop_context')) {
        bvmgr_ticket_mutation_audit_pop_context();
    }
}

function bvmgr_ticketing_v2_parse_datetime_to_timestamp(string $raw): int {
    $raw = trim($raw);
    if ($raw === '') {
        return 0;
    }

    if (function_exists('wp_timezone')) {
        try {
            $dt = new DateTimeImmutable($raw, wp_timezone());
            return (int) $dt->getTimestamp();
        } catch (Throwable $e) {
            // Fall through to strtotime below.
        }
    }

    $ts = strtotime($raw);
    return $ts ? (int) $ts : 0;
}

function bvmgr_ticketing_v2_config_window_is_open(string $start_raw, string $end_raw): bool {
    $now = time();
    $start_ts = bvmgr_ticketing_v2_parse_datetime_to_timestamp($start_raw);
    $end_ts = bvmgr_ticketing_v2_parse_datetime_to_timestamp($end_raw);
    if ($start_ts > 0 && $now < $start_ts) {
        return false;
    }
    if ($end_ts > 0 && $now > $end_ts) {
        return false;
    }
    return true;
}

function bvmgr_ticketing_v2_read_product_inventory_state(int $product_id): array {
    $product_id = absint($product_id);
    $state = array(
        'stock_qty' => null,
        'stock_status' => '',
        'manage_stock' => false,
        'ticket_capacity' => null,
    );

    if ($product_id <= 0 || get_post_type($product_id) !== 'product') {
        return $state;
    }

    if (function_exists('wc_get_product')) {
        $product = wc_get_product($product_id);
        if ($product) {
            if (method_exists($product, 'get_stock_quantity')) {
                $state['stock_qty'] = $product->get_stock_quantity();
            }
            if (method_exists($product, 'get_stock_status')) {
                $state['stock_status'] = (string) $product->get_stock_status();
            }
            if (method_exists($product, 'managing_stock')) {
                $state['manage_stock'] = (bool) $product->managing_stock();
            }
        }
    }

    if ($state['stock_qty'] === null) {
        $stock_raw = get_post_meta($product_id, '_stock', true);
        $state['stock_qty'] = is_numeric($stock_raw) ? (int) $stock_raw : null;
    }
    if ($state['stock_status'] === '') {
        $state['stock_status'] = sanitize_key((string) get_post_meta($product_id, '_stock_status', true));
    }
    if (!$state['manage_stock']) {
        $state['manage_stock'] = ((string) get_post_meta($product_id, '_manage_stock', true) === 'yes');
    }

    $capacity_raw = get_post_meta($product_id, '_tribe_ticket_capacity', true);
    $state['ticket_capacity'] = is_numeric($capacity_raw) ? (int) $capacity_raw : null;

    return $state;
}

function bvmgr_ticketing_v2_inventory_result_health_label(string $health): string {
    switch (sanitize_key($health)) {
        case 'expected_sellable_state':
            return __('Write produced a sellable state', 'backstage-venue-manager');
        case 'expected_closed_state':
            return __('Write produced a valid closed state', 'backstage-venue-manager');
        case 'fallback_state_applied':
            return __('Write completed from a fallback branch', 'backstage-venue-manager');
        case 'fallback_closed_state':
            return __('Fallback branch left the product closed', 'backstage-venue-manager');
        case 'unexpected_closed_state':
            return __('Write completed but left the product unexpectedly closed', 'backstage-venue-manager');
        default:
            return __('Manual review required', 'backstage-venue-manager');
    }
}

function bvmgr_ticketing_v2_classify_inventory_result(string $role_branch, int $capacity, bool $sold_qty_ok, int $remaining, array $state, bool $expected_open): array {
    $stock_qty = is_numeric($state['stock_qty'] ?? null) ? (int) $state['stock_qty'] : null;
    $stock_status = sanitize_key((string) ($state['stock_status'] ?? ''));
    $closed = (($stock_qty !== null && $stock_qty <= 0) || $stock_status === 'outofstock');

    $health = 'manual_review';
    if ($sold_qty_ok) {
        if ($expected_open && $capacity > 0 && $remaining > 0 && $closed) {
            $health = 'unexpected_closed_state';
        } elseif ($remaining > 0) {
            $health = 'expected_sellable_state';
        } else {
            $health = 'expected_closed_state';
        }
    } else {
        $health = ($expected_open && $capacity > 0 && $closed) ? 'fallback_closed_state' : 'fallback_state_applied';
    }

    return array(
        'result_health' => $health,
        'result_health_label' => bvmgr_ticketing_v2_inventory_result_health_label($health),
        'used_fallback' => $sold_qty_ok ? 0 : 1,
        'writer_branch' => sanitize_key($role_branch),
        'final_stock_qty' => $stock_qty,
        'final_stock_status' => $stock_status,
        'final_manage_stock' => !empty($state['manage_stock']) ? 1 : 0,
    );
}

function bvmgr_ticketing_v2_extract_inventory_result_meta(array $result): array {
    $keys = array(
        'derivation_source',
        'confidence_level',
        'expected_effect',
        'reason_text',
        'writer_branch',
        'result_health',
        'result_health_label',
        'used_fallback',
        'final_stock_qty',
        'final_stock_status',
        'final_manage_stock',
    );

    $out = array();
    foreach ($keys as $key) {
        if (array_key_exists($key, $result)) {
            $out[$key] = $result[$key];
        }
    }

    return $out;
}

function bvmgr_ticketing_v2_inspect_enabled_ticket_product(int $product_id, array $ticket_cfg = array()): array {
    $product_id = absint($product_id);
    $out = array(
        'needs_restore' => false,
        'needs_status_restore' => false,
        'needs_visibility_restore' => false,
        'needs_inventory_repair' => false,
        'needs_purchase_limit_repair' => false,
        'needs_sales_window_repair' => false,
        'changes' => array(),
        'notes' => array(),
        'post_status' => '',
        'catalog_visibility' => '',
        'stock_qty' => null,
        'stock_status' => '',
        'manage_stock' => false,
        'skip_reason_code' => 'already_in_sync',
    );

    if ($product_id <= 0 || get_post_type($product_id) !== 'product') {
        return $out;
    }

    $post_status = (string) get_post_status($product_id);
    $out['post_status'] = $post_status;
    if (in_array($post_status, array('draft', 'private'), true)) {
        $out['needs_restore'] = true;
        $out['needs_status_restore'] = true;
        $out['changes'][] = 'status';
        $out['notes'][] = 'Mapped ticket exists but is unpublished. Will restore and republish.';
    }

    $catalog_visibility = bvmgr_ticketing_v2_get_product_catalog_visibility_state($product_id);
    $out['catalog_visibility'] = $catalog_visibility;
    if ($catalog_visibility === 'hidden') {
        $out['needs_restore'] = true;
        $out['needs_visibility_restore'] = true;
        $out['changes'][] = 'catalog_visibility';
        $out['notes'][] = 'Mapped ticket exists but is hidden. Will restore visibility.';
    }

    $inventory_state = bvmgr_ticketing_v2_read_product_inventory_state($product_id);
    $out['stock_qty'] = $inventory_state['stock_qty'];
    $out['stock_status'] = (string) ($inventory_state['stock_status'] ?? '');
    $out['manage_stock'] = !empty($inventory_state['manage_stock']);

    if (!empty($ticket_cfg)) {
        $tec_event_id = absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true));
        if ($tec_event_id > 0) {
            $expected_window = bvmgr_ticketing_b_resolve_sales_window($tec_event_id, $ticket_cfg);
            $expected_start = bvmgr_ticketing_v2_normalize_sales_window_value((string) ($expected_window['start'] ?? ''));
            $expected_end = bvmgr_ticketing_v2_normalize_sales_window_value((string) ($expected_window['end'] ?? ''));
            $current_start = bvmgr_ticketing_v2_normalize_sales_window_value((string) get_post_meta($product_id, '_ticket_start_date', true));
            $current_end = bvmgr_ticketing_v2_normalize_sales_window_value((string) get_post_meta($product_id, '_ticket_end_date', true));
            $has_explicit_start = (
                bvmgr_ticketing_v2_normalize_sales_window_value((string) ($ticket_cfg['sales_start'] ?? '')) !== ''
                || bvmgr_ticketing_v2_normalize_relative_days($ticket_cfg['sales_start_relative_days'] ?? '') !== ''
            );
            $start_drifted = $has_explicit_start && $current_start !== $expected_start;
            $end_drifted = $current_end !== $expected_end;

            if ($start_drifted || $end_drifted) {
                $out['needs_restore'] = true;
                $out['needs_sales_window_repair'] = true;
                $out['changes'][] = 'sales_window';
                $out['notes'][] = 'Mapped ticket sale dates are out of sync with the linked calendar occurrence. Rebuild will re-derive the Event Tickets sale window.';
                if ($out['skip_reason_code'] === 'already_in_sync') {
                    $out['skip_reason_code'] = 'sales_window_out_of_sync';
                }
            }
        }

        $inventory_total = max(0, absint($ticket_cfg['inventory_total'] ?? 0));
        $window_open = bvmgr_ticketing_v2_config_window_is_open(
            (string) ($ticket_cfg['sales_start'] ?? ''),
            (string) ($ticket_cfg['sales_end'] ?? '')
        );
        if ($inventory_total > 0 && $window_open) {
            if ($out['manage_stock'] && is_numeric($out['stock_qty']) && (int) $out['stock_qty'] <= 0) {
                $out['needs_restore'] = true;
                $out['needs_inventory_repair'] = true;
                $out['changes'][] = 'stock';
                $out['notes'][] = 'Mapped ticket is still intended to be sellable, but live stock is 0. Rebuild will recalculate remaining inventory.';
                $out['skip_reason_code'] = 'zero_stock_despite_sellable_config';
            }
            if ((string) $out['stock_status'] === 'outofstock') {
                $out['needs_restore'] = true;
                $out['needs_inventory_repair'] = true;
                $out['changes'][] = 'stock_status';
                $out['notes'][] = 'Mapped ticket is still intended to be sellable, but the live product is marked out of stock. Rebuild will recalculate sellability.';
                if ($out['skip_reason_code'] === 'already_in_sync') {
                    $out['skip_reason_code'] = 'outofstock_despite_sellable_config';
                }
            }
        }

        $expected_max_qty_per_order = array_key_exists('max_qty_per_order', $ticket_cfg)
            ? max(0, absint($ticket_cfg['max_qty_per_order']))
            : 0;
        $current_max_qty_per_order = max(0, absint(get_post_meta($product_id, bvmgr_ticketing_v2_product_meta_key('ticketing_max_qty_per_order'), true)));
        if ($current_max_qty_per_order !== $expected_max_qty_per_order) {
            $out['needs_restore'] = true;
            $out['needs_purchase_limit_repair'] = true;
            $out['changes'][] = 'purchase_limit';
            $out['notes'][] = 'Mapped ticket purchase limit is out of sync with config. Rebuild will resync the per-order cap and clear stale limits when config is unlimited.';
            if ($out['skip_reason_code'] === 'already_in_sync') {
                $out['skip_reason_code'] = 'purchase_limit_out_of_sync';
            }
        }
    }

    $out['changes'] = array_values(array_unique($out['changes']));
    return $out;
}

function bvmgr_ticketing_v2_compose_enabled_ticket_preview_note(bool $config_changed, array $repair): string {
    $needs_status_restore = !empty($repair['needs_status_restore']);
    $needs_visibility_restore = !empty($repair['needs_visibility_restore']);
    $needs_inventory_repair = !empty($repair['needs_inventory_repair']);
    $needs_sales_window_repair = !empty($repair['needs_sales_window_repair']);

    if ($config_changed) {
        if ($needs_status_restore && $needs_visibility_restore && $needs_inventory_repair) {
            return 'Will update ticket product to match config, restore it to published/visible state, and recalculate sellable inventory.';
        }
        if ($needs_status_restore && $needs_visibility_restore) {
            return 'Will update ticket product to match config and restore it to published, visible state.';
        }
        if ($needs_inventory_repair) {
            return 'Will update ticket product to match config and recalculate remaining inventory for a false sold-out state.';
        }
        if ($needs_status_restore) {
            return 'Will update ticket product to match config and republish it.';
        }
        if ($needs_visibility_restore) {
            return 'Will update ticket product to match config and restore visibility.';
        }
        return 'Will update ticket product to match config.';
    }

    if ($needs_status_restore && $needs_visibility_restore) {
        return 'Mapped ticket exists but is unpublished and hidden. Will restore it to published, visible state.';
    }
    if ($needs_inventory_repair) {
        return 'Mapped ticket is still customer-facing in config but live inventory has drifted into a false sold-out state. Will recalculate it.';
    }
    if ($needs_sales_window_repair) {
        return 'Mapped ticket sale dates are out of sync with the linked calendar occurrence. Will re-derive the Event Tickets sale window.';
    }
    if ($needs_status_restore) {
        return 'Mapped ticket exists but is unpublished. Will restore and republish.';
    }
    if ($needs_visibility_restore) {
        return 'Mapped ticket exists but is hidden. Will restore visibility.';
    }

    return 'No changes since last sync.';
}

function bvmgr_ticketing_v2_inspect_enabled_entitlement_product(int $product_id, array $ent_cfg = array()): array {
    $product_id = absint($product_id);
    $out = array(
        'needs_restore' => false,
        'needs_status_restore' => false,
        'needs_visibility_restore' => false,
        'needs_inventory_repair' => false,
        'changes' => array(),
        'notes' => array(),
        'post_status' => '',
        'catalog_visibility' => '',
        'stock_qty' => null,
        'stock_status' => '',
        'manage_stock' => false,
        'skip_reason_code' => 'already_in_sync',
    );

    if ($product_id <= 0 || get_post_type($product_id) !== 'product') {
        return $out;
    }

    $post_status = (string) get_post_status($product_id);
    $out['post_status'] = $post_status;
    if (in_array($post_status, array('draft', 'private'), true)) {
        $out['needs_restore'] = true;
        $out['needs_status_restore'] = true;
        $out['changes'][] = 'status';
        $out['notes'][] = 'Mapped add-on exists but is unpublished. Will restore and republish.';
    }

    $catalog_visibility = bvmgr_ticketing_v2_get_product_catalog_visibility_state($product_id);
    $out['catalog_visibility'] = $catalog_visibility;

    $inventory_state = bvmgr_ticketing_v2_read_product_inventory_state($product_id);
    $out['stock_qty'] = $inventory_state['stock_qty'];
    $out['stock_status'] = (string) ($inventory_state['stock_status'] ?? '');
    $out['manage_stock'] = !empty($inventory_state['manage_stock']);

    $capacity = max(0, absint($ent_cfg['capacity'] ?? 0));
    if ($capacity > 0) {
        if ($out['manage_stock'] && is_numeric($out['stock_qty']) && (int) $out['stock_qty'] <= 0) {
            $out['needs_restore'] = true;
            $out['needs_inventory_repair'] = true;
            $out['changes'][] = 'stock';
            $out['notes'][] = 'Mapped add-on still has positive configured capacity, but live stock is 0. Rebuild will recalculate remaining inventory.';
            $out['skip_reason_code'] = 'zero_stock_despite_positive_capacity';
        }
        if ((string) $out['stock_status'] === 'outofstock') {
            $out['needs_restore'] = true;
            $out['needs_inventory_repair'] = true;
            $out['changes'][] = 'stock_status';
            $out['notes'][] = 'Mapped add-on still has positive configured capacity, but the live product is marked out of stock.';
            if ($out['skip_reason_code'] === 'already_in_sync') {
                $out['skip_reason_code'] = 'outofstock_despite_positive_capacity';
            }
        }
    }

    $out['changes'] = array_values(array_unique($out['changes']));
    return $out;
}

function bvmgr_ticketing_v2_compose_entitlement_preview_note(bool $config_changed, array $repair): string {
    $needs_status_restore = !empty($repair['needs_status_restore']);
    $needs_inventory_repair = !empty($repair['needs_inventory_repair']);

    if ($config_changed) {
        if ($needs_status_restore) {
            return 'Will update add-on product to match config and republish it.';
        }
        if ($needs_inventory_repair) {
            return 'Will update add-on product to match config and recalculate remaining inventory for a closed-state drift.';
        }
        return 'Will update add-on product to match config.';
    }

    if ($needs_status_restore) {
        return 'Mapped add-on exists but is unpublished. Will restore and republish.';
    }
    if ($needs_inventory_repair) {
        return 'Mapped add-on still has configured capacity, but live inventory has drifted into a closed state. Will recalculate it.';
    }

    return 'No changes since last sync.';
}

function bvmgr_ticketing_v2_restore_enabled_ticket_product(int $product_id): array {
    $product_id = absint($product_id);
    if ($product_id <= 0) {
        return array('ok' => false, 'message' => 'invalid_product_id');
    }
    if (get_post_type($product_id) !== 'product') {
        return array('ok' => false, 'message' => 'not_product');
    }

    $used_wc_product = false;
    if (function_exists('wc_get_product')) {
        $product = wc_get_product($product_id);
        if ($product) {
            if (method_exists($product, 'set_status')) {
                $product->set_status('publish');
            }
            if (method_exists($product, 'set_catalog_visibility')) {
                $product->set_catalog_visibility('visible');
            }
            $product->save();
            $used_wc_product = true;
        }
    }

    if (!$used_wc_product) {
        $updated = wp_update_post(array(
            'ID' => $product_id,
            'post_status' => 'publish',
        ), true);
        if (is_wp_error($updated) || absint($updated) <= 0) {
            return array('ok' => false, 'message' => 'publish_restore_failed');
        }
    }

    delete_post_meta($product_id, '_visibility');
    if (taxonomy_exists('product_visibility')) {
        $removed = wp_remove_object_terms($product_id, array('exclude-from-catalog', 'exclude-from-search'), 'product_visibility');
        if (is_wp_error($removed)) {
            return array('ok' => false, 'message' => 'visibility_terms_restore_failed');
        }
    }
    if (function_exists('wc_delete_product_transients')) {
        wc_delete_product_transients($product_id);
    }

    clean_post_cache($product_id);

    $post_status = (string) get_post_status($product_id);
    if ($post_status !== 'publish') {
        return array(
            'ok' => false,
            'message' => 'publish_restore_failed',
            'post_status' => $post_status,
        );
    }

    $catalog_visibility = bvmgr_ticketing_v2_get_product_catalog_visibility_state($product_id);
    if ($catalog_visibility === 'hidden') {
        return array(
            'ok' => false,
            'message' => 'visibility_restore_failed',
            'catalog_visibility' => $catalog_visibility,
        );
    }

    return array(
        'ok' => true,
        'product_id' => $product_id,
        'post_status' => $post_status,
        'catalog_visibility' => $catalog_visibility,
    );
}

function bvmgr_ticketing_v2_apply_ga_to_ticket_product(int $product_id, int $tec_event_id, array $ga): array {
    $ticket = array(
        'ticket_key' => 'ga',
        'title' => (string) ($ga['label'] ?? 'GA Admission'),
        'price' => (string) ($ga['price'] ?? '0'),
        'inventory_total' => (int) ($ga['capacity'] ?? 0),
        'sales_start' => (string) ($ga['sales_start'] ?? ''),
        'sales_end' => (string) ($ga['sales_end'] ?? ''),
        'visibility_mode' => 'public',
        'verified_program' => '',
        'allowed_programs' => array(),
        'allow_direct_grants' => false,
        'claim_grant_type' => 'event_ticket_eligibility',
        'claims_per_assignee' => 1,
        'require_assignee_email' => true,
        'counts_toward_unlock' => true,
        'max_qty_per_order' => 0,
        'ratio_rule_enabled' => false,
        'ratio_rule_max_per_qualifying' => 0,
        'ratio_rule_qualifier_mode' => 'counts_toward_unlock',
        'ratio_rule_group' => '',
    );
    return bvmgr_ticketing_v2_apply_ticket_to_product($product_id, $tec_event_id, $ticket);
}

function bvmgr_ticketing_v2_create_ga_ticket(int $tec_event_id, array $ga): array {
    $ticket = array(
        'ticket_key' => 'ga',
        'title' => (string) ($ga['label'] ?? 'GA Admission'),
        'price' => (string) ($ga['price'] ?? '0'),
        'inventory_total' => (int) ($ga['capacity'] ?? 0),
        'sales_start' => (string) ($ga['sales_start'] ?? ''),
        'sales_end' => (string) ($ga['sales_end'] ?? ''),
        'visibility_mode' => 'public',
        'verified_program' => '',
        'allowed_programs' => array(),
        'allow_direct_grants' => false,
        'claim_grant_type' => 'event_ticket_eligibility',
        'claims_per_assignee' => 1,
        'require_assignee_email' => true,
        'counts_toward_unlock' => true,
        'max_qty_per_order' => 0,
        'ratio_rule_enabled' => false,
        'ratio_rule_max_per_qualifying' => 0,
        'ratio_rule_qualifier_mode' => 'counts_toward_unlock',
        'ratio_rule_group' => '',
    );
    return bvmgr_ticketing_v2_create_ticket($tec_event_id, $ticket);
}

function bvmgr_ticketing_v2_upsert_entitlement_product(int $plan_id, int $tec_event_id, array $ent, int $existing_product_id = 0): array {
    $plan_id = absint($plan_id);
    $tec_event_id = absint($tec_event_id);
    $existing_product_id = absint($existing_product_id);

    if (!class_exists('WC_Product_Simple')) {
        return array('ok' => false, 'message' => 'woocommerce_unavailable');
    }

    $label = bvmgr_ticketing_v2_sanitize_plain_text_label($ent['label'] ?? '');
    if ($label === '') {
        return array('ok' => false, 'message' => 'missing_label');
    }

    $price = max(0.0, (float) ($ent['price'] ?? 0));
    $capacity = max(0, (int) ($ent['capacity'] ?? 0));

    $ent_id = sanitize_key((string) ($ent['entitlement_id'] ?? ''));
    if ($ent_id === '') {
        return array('ok' => false, 'message' => 'missing_entitlement_id');
    }

    // Deterministic SKU so Woo product lists are distinguishable.
    // Use a short entitlement ID suffix to keep SKUs readable.
    $sku = 'VMS-TEC' . $tec_event_id . '-ENT-' . substr($ent_id, 0, 12);

    $product = null;
    if ($existing_product_id > 0 && function_exists('wc_get_product')) {
        $product = wc_get_product($existing_product_id);
    }

    if (!$product && $existing_product_id <= 0 && function_exists('bvmgr_ticketing_v2_pick_entitlement_product_by_sku') && function_exists('wc_get_product')) {
        $picked = bvmgr_ticketing_v2_pick_entitlement_product_by_sku((string) $sku, $plan_id, $ent_id);
        if ($picked > 0) {
            $product = wc_get_product($picked);
            $existing_product_id = $picked;
        }
    }

    $existing_inventory_state = ($existing_product_id > 0)
        ? bvmgr_ticketing_v2_read_product_inventory_state($existing_product_id)
        : array(
            'stock_qty' => null,
            'stock_status' => '',
            'manage_stock' => false,
            'ticket_capacity' => null,
        );

    if (!$product) {
        $product = new WC_Product_Simple();
    }

    $result_meta = array(
        'derivation_source' => 'entitlement_capacity_seed',
        'confidence_level' => 'authoritative',
        'expected_effect' => ($capacity > 0) ? 'reopen' : 'close',
        'reason_text' => __('Add-on inventory was seeded from the authoritative entitlement configuration.', 'backstage-venue-manager'),
        'writer_branch' => 'entitlement_capacity_seed',
        'result_health' => ($capacity > 0) ? 'expected_sellable_state' : 'expected_closed_state',
        'result_health_label' => bvmgr_ticketing_v2_inventory_result_health_label(($capacity > 0) ? 'expected_sellable_state' : 'expected_closed_state'),
        'used_fallback' => 0,
        'final_stock_qty' => $capacity,
        'final_stock_status' => ($capacity > 0) ? 'instock' : 'outofstock',
        'final_manage_stock' => 1,
    );

    $seed_reason = sprintf(
        /* translators: %d: configured entitlement capacity */
        __('Add-on stock was seeded from configured capacity %d before sold-count reconciliation.', 'backstage-venue-manager'),
        $capacity
    );
    bvmgr_ticketing_v2_push_inventory_write_context(array(
        'source_function' => 'vms_ticketing_v2_upsert_entitlement_product',
        'derivation_source' => 'entitlement_capacity_seed',
        'confidence_level' => 'authoritative',
        'expected_effect' => ($capacity > 0) ? 'reopen' : 'close',
        'reason_text' => $seed_reason,
        'writer_branch' => 'entitlement_capacity_seed',
        'result_health' => ($capacity > 0) ? 'expected_sellable_state' : 'expected_closed_state',
    ));
    try {
        $product->set_name(bvmgr_ticketing_v2_compose_product_admin_title($label, $tec_event_id));
        $product->set_regular_price($price);
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_virtual(true);

        if (method_exists($product, 'get_sku') && method_exists($product, 'set_sku')) {
            $existing_sku = trim((string) $product->get_sku());
            if ($existing_sku === '' || stripos($existing_sku, 'SR-') === 0) {
                $product->set_sku($sku);
            }
        }

        $product->set_manage_stock(true);
        if (method_exists($product, 'set_backorders')) {
            $product->set_backorders('no');
        }
        if (method_exists($product, 'set_stock_quantity')) {
            $product->set_stock_quantity($capacity);
        }
        if (method_exists($product, 'set_stock_status')) {
            $product->set_stock_status(($capacity > 0) ? 'instock' : 'outofstock');
        }
        $product->set_sold_individually($capacity === 1);

        $pid = (int) $product->save();
    } finally {
        bvmgr_ticketing_v2_pop_inventory_write_context();
    }

    if ($pid <= 0) {
        return array('ok' => false, 'message' => 'save_failed');
    }

    // Markers
    bvmgr_ticketing_v2_stamp_product_markers($pid, $plan_id, $tec_event_id, 'entitlement', $ent_id);

    // Inventory reconciliation: remaining = capacity - sold (from paid orders).
    if ($capacity >= 0 && function_exists('bvmgr_ticketing_v2_calc_sold_qty_for_entitlement_scope')) {
        $sold_res = bvmgr_ticketing_v2_calc_sold_qty_for_entitlement_scope($plan_id, $ent_id, $sku, $pid);
        if (!empty($sold_res['ok'])) {
            $sold_qty = max(0, absint($sold_res['sold_qty'] ?? 0));
            $remaining = max(0, $capacity - $sold_qty);
            $reason_text = sprintf(
                /* translators: 1: capacity, 2: sold quantity, 3: remaining quantity */
                __('Add-on stock was recalculated from capacity %1$d minus sold quantity %2$d, leaving %3$d remaining.', 'backstage-venue-manager'),
                $capacity,
                $sold_qty,
                $remaining
            );
            if (!empty($sold_res['ignored_total_sales_count'])) {
                $reason_text .= ' ' . sprintf(
                    /* translators: %d: number of products */
                    __('Rebuild ignored stale Woo total_sales counters on %d related add-on product(s) and trusted the paid-order scan instead.', 'backstage-venue-manager'),
                    absint($sold_res['ignored_total_sales_count'])
                );
            }

            bvmgr_ticketing_v2_push_inventory_write_context(array(
                'source_function' => 'vms_ticketing_v2_upsert_entitlement_product',
                'derivation_source' => 'entitlement_scope_sold_count_reconciliation',
                'confidence_level' => 'authoritative',
                'expected_effect' => ($remaining > 0) ? 'reopen' : 'close',
                'reason_text' => $reason_text,
                'writer_branch' => 'entitlement_sold_count_reconciliation',
                'result_health' => ($remaining > 0) ? 'expected_sellable_state' : 'expected_closed_state',
            ));
            try {
                if (function_exists('wc_get_product')) {
                    $p2 = wc_get_product($pid);
                    if ($p2 && method_exists($p2, 'set_manage_stock')) {
                        $p2->set_manage_stock(true);
                        if (method_exists($p2, 'set_backorders')) {
                            $p2->set_backorders('no');
                        }
                        if (method_exists($p2, 'set_stock_quantity')) {
                            $p2->set_stock_quantity($remaining);
                        }
                        if (method_exists($p2, 'set_stock_status')) {
                            $p2->set_stock_status(($remaining > 0) ? 'instock' : 'outofstock');
                        }
                        $p2->save();
                    }
                }

                update_post_meta($pid, '_vms_ticketing_entitlement_capacity_v2', $capacity);
                update_post_meta($pid, '_vms_ticketing_entitlement_sold_qty_v2', $sold_qty);
                update_post_meta($pid, '_vms_ticketing_entitlement_remaining_v2', $remaining);
                update_post_meta($pid, '_vms_ticketing_entitlement_stock_reconciled_at_gmt', time());
                delete_post_meta($pid, '_vms_ticketing_entitlement_stock_reconcile_error');

                if ($sold_qty > $capacity) {
                    update_post_meta($pid, '_vms_ticketing_entitlement_oversold_by_v2', $sold_qty - $capacity);
                } else {
                    delete_post_meta($pid, '_vms_ticketing_entitlement_oversold_by_v2');
                }
            } finally {
                bvmgr_ticketing_v2_pop_inventory_write_context();
            }

            $result_meta = array_merge(
                $result_meta,
                array(
                    'derivation_source' => 'entitlement_scope_sold_count_reconciliation',
                    'confidence_level' => 'authoritative',
                    'expected_effect' => ($remaining > 0) ? 'reopen' : 'close',
                    'reason_text' => $reason_text,
                    'writer_branch' => 'entitlement_sold_count_reconciliation',
                ),
                bvmgr_ticketing_v2_classify_inventory_result(
                    'entitlement_sold_count_reconciliation',
                    $capacity,
                    true,
                    $remaining,
                    array(
                        'stock_qty' => $remaining,
                        'stock_status' => ($remaining > 0) ? 'instock' : 'outofstock',
                        'manage_stock' => true,
                    ),
                    ($capacity > 0 && $remaining > 0)
                )
            );
        } else {
            if ($capacity <= 0) {
                $reason_text = __('Add-on stock was set to 0 because the authoritative configured capacity is 0.', 'backstage-venue-manager');
                bvmgr_ticketing_v2_push_inventory_write_context(array(
                    'source_function' => 'vms_ticketing_v2_upsert_entitlement_product',
                    'derivation_source' => 'authoritative_zero_capacity',
                    'confidence_level' => 'authoritative',
                    'expected_effect' => 'close',
                    'reason_text' => $reason_text,
                    'writer_branch' => 'entitlement_zero_capacity_branch',
                    'result_health' => 'expected_closed_state',
                ));
                try {
                    if (function_exists('wc_get_product')) {
                        $p2 = wc_get_product($pid);
                        if ($p2 && method_exists($p2, 'set_manage_stock')) {
                            $p2->set_manage_stock(true);
                            if (method_exists($p2, 'set_backorders')) {
                                $p2->set_backorders('no');
                            }
                            if (method_exists($p2, 'set_stock_quantity')) {
                                $p2->set_stock_quantity(0);
                            }
                            if (method_exists($p2, 'set_stock_status')) {
                                $p2->set_stock_status('outofstock');
                            }
                            $p2->save();
                        }
                    }
                    update_post_meta($pid, '_vms_ticketing_entitlement_capacity_v2', $capacity);
                    delete_post_meta($pid, '_vms_ticketing_entitlement_sold_qty_v2');
                    update_post_meta($pid, '_vms_ticketing_entitlement_remaining_v2', 0);
                    update_post_meta($pid, '_vms_ticketing_entitlement_stock_reconciled_at_gmt', time());
                    update_post_meta($pid, '_vms_ticketing_entitlement_stock_reconcile_error', sanitize_text_field((string) ($sold_res['message'] ?? 'sold_qty_unavailable')));
                    delete_post_meta($pid, '_vms_ticketing_entitlement_oversold_by_v2');
                } finally {
                    bvmgr_ticketing_v2_pop_inventory_write_context();
                }

                $result_meta = array_merge(
                    $result_meta,
                    array(
                        'derivation_source' => 'authoritative_zero_capacity',
                        'confidence_level' => 'authoritative',
                        'expected_effect' => 'close',
                        'reason_text' => $reason_text,
                        'writer_branch' => 'entitlement_zero_capacity_branch',
                    ),
                    bvmgr_ticketing_v2_classify_inventory_result(
                        'entitlement_zero_capacity_branch',
                        $capacity,
                        false,
                        0,
                        array(
                            'stock_qty' => 0,
                            'stock_status' => 'outofstock',
                            'manage_stock' => true,
                        ),
                        false
                    )
                );
            } else {
                $fallback_stock = is_numeric($existing_inventory_state['stock_qty'] ?? null)
                    ? max(0, (int) $existing_inventory_state['stock_qty'])
                    : $capacity;
                $fallback_status = ($fallback_stock > 0) ? 'instock' : 'outofstock';
                $reason_text = sprintf(
                    /* translators: %d: preserved stock quantity */
                    __('Add-on sold quantity could not be derived safely, so rebuild preserved the existing stock quantity of %d and only normalized stock constraints.', 'backstage-venue-manager'),
                    $fallback_stock
                );
                bvmgr_ticketing_v2_push_inventory_write_context(array(
                    'source_function' => 'vms_ticketing_v2_upsert_entitlement_product',
                    'derivation_source' => 'entitlement_existing_state_fallback',
                    'confidence_level' => 'fallback',
                    'expected_effect' => ($fallback_stock > 0) ? 'preserve' : 'close',
                    'reason_text' => $reason_text,
                    'writer_branch' => 'entitlement_existing_state_fallback',
                    'result_health' => ($fallback_stock > 0) ? 'fallback_state_applied' : 'fallback_closed_state',
                ));
                try {
                    if (function_exists('wc_get_product')) {
                        $p2 = wc_get_product($pid);
                        if ($p2 && method_exists($p2, 'set_manage_stock')) {
                            $p2->set_manage_stock(true);
                            if (method_exists($p2, 'set_backorders')) {
                                $p2->set_backorders('no');
                            }
                            if (method_exists($p2, 'set_stock_quantity')) {
                                $p2->set_stock_quantity($fallback_stock);
                            }
                            if (method_exists($p2, 'set_stock_status')) {
                                $p2->set_stock_status($fallback_status);
                            }
                            $p2->save();
                        }
                    }
                    update_post_meta($pid, '_vms_ticketing_entitlement_capacity_v2', $capacity);
                    delete_post_meta($pid, '_vms_ticketing_entitlement_sold_qty_v2');
                    delete_post_meta($pid, '_vms_ticketing_entitlement_remaining_v2');
                    update_post_meta($pid, '_vms_ticketing_entitlement_stock_reconciled_at_gmt', time());
                    update_post_meta($pid, '_vms_ticketing_entitlement_stock_reconcile_error', sanitize_text_field((string) ($sold_res['message'] ?? 'sold_qty_unavailable')));
                    delete_post_meta($pid, '_vms_ticketing_entitlement_oversold_by_v2');
                } finally {
                    bvmgr_ticketing_v2_pop_inventory_write_context();
                }

                $result_meta = array_merge(
                    $result_meta,
                    array(
                        'derivation_source' => 'entitlement_existing_state_fallback',
                        'confidence_level' => 'fallback',
                        'expected_effect' => ($fallback_stock > 0) ? 'preserve' : 'close',
                        'reason_text' => $reason_text,
                        'writer_branch' => 'entitlement_existing_state_fallback',
                    ),
                    bvmgr_ticketing_v2_classify_inventory_result(
                        'entitlement_existing_state_fallback',
                        $capacity,
                        false,
                        $fallback_stock,
                        array(
                            'stock_qty' => $fallback_stock,
                            'stock_status' => $fallback_status,
                            'manage_stock' => true,
                        ),
                        true
                    )
                );
            }
        }
    }

    // Store eligibility subset for quick inspection (truth remains config meta).
    $elig = is_array($ent['eligibility'] ?? null) ? $ent['eligibility'] : array();
    update_post_meta($pid, '_vms_ticketing_eligibility_snapshot_v1', $elig);

    // Keep Woo product featured image aligned with entitlement image source.
    bvmgr_entitlements_sync_product_image($pid, $ent_id);

    return array_merge(array('ok' => true, 'woo_product_id' => $pid), $result_meta);
}

function bvmgr_ticketing_v2_preview_sync(int $plan_id): array {
    $plan_id = absint($plan_id);
    if ($plan_id <= 0) {
        return array('ok' => false, 'message' => 'invalid_plan');
    }
    if (!current_user_can('edit_post', $plan_id)) {
        return array('ok' => false, 'message' => 'forbidden', 'http' => 403);
    }
	if (function_exists('bvmgr_event_plan_is_externally_ticketed') && bvmgr_event_plan_is_externally_ticketed($plan_id)) {
		return array(
			'ok' => false,
			'message' => 'external_ticketing',
			'detail' => __('External Ticketing is active. Native ticket preview and product synchronization are not needed for this Event Plan.', 'backstage-venue-manager'),
		);
	}

    $cfg = bvmgr_ticketing_v2_get_config($plan_id);
    $cfg_hash = bvmgr_ticketing_v2_hash_config_for_sync($cfg);

    $mode = (string) ($cfg['mode'] ?? 'read_only');

    // Preview is intentionally read-only. It may inspect an existing linked TEC event,
    // but it must not create or relink one. Event creation now happens in Commit
    // prepare phase so Preview stays cheap and predictable on shared hosting.
    $tec_event_id = bvmgr_ticketing_b_get_linked_tec_event_id($plan_id);
    $created_calendar_event = false;

    if ($mode !== 'none' && !bvmgr_ticketing_b_is_event_tickets_woo_available()) {
        return array('ok' => false, 'message' => 'event_tickets_woo_unavailable');
    }

    $sync = bvmgr_ticketing_v2_get_sync($plan_id);
    $sync_map = (isset($sync['map']) && is_array($sync['map'])) ? $sync['map'] : array();

    $actions = array();
    $warnings = array();
    $blocked = false;
    $calendar_alignment = array();
    $reschedule_required = get_post_meta($plan_id, '_vms_ticketing_reschedule_required_v1', true);
    if (
        $mode === 'vms_managed'
        && is_array($reschedule_required)
        && absint($reschedule_required['tec_event_id'] ?? 0) === $tec_event_id
    ) {
        $warnings[] = __('This completed occurrence was changed after closure. Native ticket windows remain closed until a future explicit Reschedule workflow resolves the event.', 'backstage-venue-manager');
        $blocked = true;
    }
    if ($mode === 'vms_managed' && $tec_event_id > 0) {
        $calendar_alignment = bvmgr_ticketing_v2_plan_calendar_alignment($plan_id, $tec_event_id);
        if (empty($calendar_alignment['checkable']) || empty($calendar_alignment['aligned'])) {
            $warnings[] = __('The Event Plan date or time does not match the linked calendar event. Publish or re-sync the calendar occurrence before committing ticket changes.', 'backstage-venue-manager');
            $blocked = true;
        }
    }

    // Multi-ticket sync preview
    $existing_ticket_pids = array();
    if ($tec_event_id > 0) {
        $existing_ticket_pids = bvmgr_ticketing_b_get_event_ticket_products($tec_event_id);
    }
    $existing_ticket_pids = array_values(array_filter(array_map('absint', (array) $existing_ticket_pids)));
    $unclaimed_existing_ticket_pids = $existing_ticket_pids;

    $tickets_cfg = (isset($cfg['tickets']) && is_array($cfg['tickets'])) ? $cfg['tickets'] : array();
    if (empty($tickets_cfg)) {
        // Defensive fallback to legacy GA-only config shape.
        $ga = is_array($cfg['ga'] ?? null) ? $cfg['ga'] : array();
        $tickets_cfg[] = array(
            'enabled' => true,
            'ticket_key' => 'ga',
            'title' => (string) ($ga['label'] ?? 'GA Admission'),
            'price' => (string) ($ga['price'] ?? '0'),
            'early_price' => (string) ($ga['early_price'] ?? ''),
            'early_price_start' => (string) ($ga['early_price_start'] ?? ''),
            'early_price_end' => (string) ($ga['early_price_end'] ?? ''),
            'inventory_total' => (int) ($ga['capacity'] ?? 0),
            'sales_start' => (string) ($ga['sales_start'] ?? ''),
            'sales_end' => (string) ($ga['sales_end'] ?? ''),
            'visibility_mode' => 'public',
            'verified_program' => '',
            'allowed_programs' => array(),
            'allow_direct_grants' => false,
            'claim_grant_type' => 'event_ticket_eligibility',
            'claims_per_assignee' => 1,
            'require_assignee_email' => true,
            'counts_toward_unlock' => true,
            'max_qty_per_order' => 0,
            'sort_order' => 10,
        );
    }

    $ticket_sync_map = (isset($sync_map['tickets']) && is_array($sync_map['tickets'])) ? $sync_map['tickets'] : array();
    $legacy_ga_pid = (isset($sync_map['ga']) && is_array($sync_map['ga'])) ? absint($sync_map['ga']['woo_product_id'] ?? 0) : 0;
    $legacy_ga_pid_claimed = false;
    $configured_ticket_keys = array();

    $enabled_ticket_count = 0;
    foreach ($tickets_cfg as $ticket_idx => $ticket_row) {
        if (!is_array($ticket_row)) {
            continue;
        }
        $enabled = array_key_exists('enabled', $ticket_row) ? !empty($ticket_row['enabled']) : true;
        if ($enabled) {
            $enabled_ticket_count++;
        }

        $ticket_key = sanitize_key((string) ($ticket_row['ticket_key'] ?? $ticket_row['key'] ?? ''));
        if ($ticket_key === '') {
            continue;
        }
        $configured_ticket_keys[$ticket_key] = true;
        $ticket_label = trim((string) ($ticket_row['title'] ?? ''));
        $ticket_label_for_row = ($ticket_label !== '') ? $ticket_label : $ticket_key;

        $visibility_mode = sanitize_key((string) ($ticket_row['visibility_mode'] ?? 'public'));
        if (!in_array($visibility_mode, array('public', 'login', 'verified'), true)) {
            $visibility_mode = 'public';
        }
        $verified_program = sanitize_key((string) ($ticket_row['verified_program'] ?? ''));
        $allowed_programs = bvmgr_ticketing_v2_normalize_allowed_programs($ticket_row['allowed_programs'] ?? array(), $verified_program);
        $allow_direct_grants = bvmgr_ticketing_v2_truthy($ticket_row['allow_direct_grants'] ?? false, false);
        if ($enabled && $visibility_mode === 'verified' && empty($allowed_programs) && !$allow_direct_grants) {
            $warnings[] = 'Ticket "' . $ticket_label . '" is set to "Verified group required" but has no credential program or direct-grant rule configured.';
            if ($mode === 'vms_managed') {
                $blocked = true;
            }
        }

        $regular_price = max(0.0, (float) ($ticket_row['price'] ?? 0));
        $early_price = max(0.0, (float) ($ticket_row['early_price'] ?? 0));
        $early_start = sanitize_text_field((string) ($ticket_row['early_price_start'] ?? ''));
        $early_end = sanitize_text_field((string) ($ticket_row['early_price_end'] ?? ''));
        if ($enabled && $early_price > 0) {
            $early_end_ts = bvmgr_ticketing_v2_parse_datetime_to_timestamp($early_end);
            $early_start_ts = bvmgr_ticketing_v2_parse_datetime_to_timestamp($early_start);
            if ($regular_price <= 0 || $early_price >= $regular_price) {
                $warnings[] = 'Ticket "' . $ticket_label_for_row . '" has an early price that is not lower than the regular price. Early price will not be synced until this is fixed.';
                if ($mode === 'vms_managed') {
                    $blocked = true;
                }
            } elseif ($early_end_ts <= 0) {
                $warnings[] = 'Ticket "' . $ticket_label_for_row . '" has an early price but no valid early-price end date. Add a deadline so the ticket can safely return to regular price.';
                if ($mode === 'vms_managed') {
                    $blocked = true;
                }
            } elseif ($early_start_ts > 0 && $early_start_ts > $early_end_ts) {
                $warnings[] = 'Ticket "' . $ticket_label_for_row . '" has an early-price start date after the early-price end date.';
                if ($mode === 'vms_managed') {
                    $blocked = true;
                }
            }
        }

        $ticket_hash = bvmgr_ticketing_v2_hash_ticket($ticket_row);

        $map_row = (isset($ticket_sync_map[$ticket_key]) && is_array($ticket_sync_map[$ticket_key])) ? $ticket_sync_map[$ticket_key] : array();
        $mapped_pid = absint($map_row['woo_product_id'] ?? 0);
        if (
            $mapped_pid <= 0
            && !$legacy_ga_pid_claimed
            && $legacy_ga_pid > 0
            && bvmgr_ticketing_v2_should_apply_legacy_ga_map_to_ticket($ticket_key, $ticket_label_for_row)
        ) {
            // Back-compat: allow the real GA row to inherit legacy single-GA map data,
            // but do not attach it to a newly inserted Early/VIP/etc. row merely
            // because that row is first in the template.
            $mapped_pid = $legacy_ga_pid;
            $legacy_ga_pid_claimed = true;
        }

        $row = array(
            'scope' => 'ticket',
            'ticket_key' => $ticket_key,
            'label' => $ticket_label_for_row,
            'action' => 'noop',
            'woo_product_id' => $mapped_pid,
            'notes' => '',
            'changes' => array(),
            'hash' => $ticket_hash,
            'skip_reason_code' => '',
            'skip_expected' => 0,
            'skip_safety_driven' => 0,
        );

        if ($mode !== 'vms_managed') {
            $row['notes'] = ($mode === 'none') ? 'Ticketing mode is none.' : 'Ticketing mode is read-only.';
            $row['skip_reason_code'] = 'mode_not_managed';
            $row['skip_expected'] = 1;
            $actions[] = $row;
            continue;
        }

        if ($mapped_pid > 0) {
            $pt = get_post_type($mapped_pid);
            if (empty($pt)) {
                $warnings[] = 'Ticket mapping for ' . $ticket_key . ' pointed to a missing product (#' . $mapped_pid . '). Mapping will be repaired on commit.';
                $mapped_pid = 0;
                $row['woo_product_id'] = 0;
            } elseif ($pt !== 'product') {
                $warnings[] = 'Ticket mapping for ' . $ticket_key . ' is not a Woo product (#' . $mapped_pid . ').';
                $mapped_pid = 0;
                $row['woo_product_id'] = 0;
            } else {
                $post_status = (string) get_post_status($mapped_pid);
                if ($post_status === 'trash') {
                    $warnings[] = 'Ticket mapping for ' . $ticket_key . ' points to a trashed product (#' . $mapped_pid . '). Mapping will be repaired on commit.';
                    $mapped_pid = 0;
                    $row['woo_product_id'] = 0;
                } else {
                    $linked = absint(get_post_meta($mapped_pid, '_tribe_wooticket_for_event', true));
                    if ($linked !== $tec_event_id) {
                        $warnings[] = 'Ticket mapping for ' . $ticket_key . ' points to product #' . $mapped_pid . ' linked to a different calendar event. Mapping will be repaired on commit.';
                        $mapped_pid = 0;
                        $row['woo_product_id'] = 0;
                    }
                }
            }
        }

        if (!$enabled) {
            if ($mapped_pid > 0) {
                $row['woo_product_id'] = $mapped_pid;
                $row['action'] = 'disable';
                $row['notes'] = 'Ticket is disabled in config. Will unpublish mapped ticket product (draft + hidden).';
                $row['changes'] = array('status', 'catalog_visibility');
                $unclaimed_existing_ticket_pids = array_values(array_diff($unclaimed_existing_ticket_pids, array($mapped_pid)));
            } else {
                $row['action'] = 'skip';
                $row['notes'] = 'Ticket is disabled and has no mapped product to unpublish.';
                $row['skip_reason_code'] = 'disabled_unmapped';
                $row['skip_expected'] = 1;
            }
            $actions[] = $row;
            continue;
        }

        if ($ticket_label === '') {
            continue;
        }

        if ($mapped_pid > 0) {
            $repair = bvmgr_ticketing_v2_inspect_enabled_ticket_product($mapped_pid, $ticket_row);
            $row['woo_product_id'] = $mapped_pid;
            $prev_hash = (string) ($map_row['last_sync_hash'] ?? '');
            $config_changed = !($prev_hash !== '' && hash_equals($prev_hash, $ticket_hash));
            $expected_max_qty_per_order = array_key_exists('max_qty_per_order', $ticket_row)
                ? max(0, absint($ticket_row['max_qty_per_order']))
                : 0;
            $mapped_max_qty_per_order = max(0, absint($map_row['max_qty_per_order'] ?? 0));
            $needs_sync_purchase_limit_repair = ($mapped_max_qty_per_order !== $expected_max_qty_per_order);
            if ($needs_sync_purchase_limit_repair) {
                $repair['needs_restore'] = true;
                $repair['changes'] = array_merge((array) ($repair['changes'] ?? array()), array('purchase_limit'));
                $repair['notes'] = array_merge((array) ($repair['notes'] ?? array()), array('Mapped ticket sync data has a stale per-order cap. Rebuild will rewrite it so unlimited tickets do not inherit old limits.'));
                if (($repair['skip_reason_code'] ?? 'already_in_sync') === 'already_in_sync') {
                    $repair['skip_reason_code'] = 'purchase_limit_sync_out_of_sync';
                }
            }
            if (!$config_changed && empty($repair['needs_restore'])) {
                $row['action'] = 'skip';
                $row['notes'] = bvmgr_ticketing_v2_compose_enabled_ticket_preview_note($config_changed, $repair);
                $row['skip_reason_code'] = (string) ($repair['skip_reason_code'] ?? 'already_in_sync');
                $row['skip_expected'] = 1;
            } else {
                $row['action'] = 'update';
                $changes = array();
                if ($config_changed) {
                    $changes = array('title', 'price', 'stock', 'sales_window', 'visibility');
                }
                if (!empty($repair['changes']) && is_array($repair['changes'])) {
                    $changes = array_merge($changes, $repair['changes']);
                }
                $row['notes'] = bvmgr_ticketing_v2_compose_enabled_ticket_preview_note($config_changed, $repair);
                if ($needs_sync_purchase_limit_repair || !empty($repair['needs_purchase_limit_repair'])) {
                    $row['notes'] .= ' Will resync the per-order purchase limit and clear stale caps when config is unlimited.';
                }
                $row['changes'] = array_values(array_unique(array_filter(array_map('strval', $changes))));
            }
            $unclaimed_existing_ticket_pids = array_values(array_diff($unclaimed_existing_ticket_pids, array($mapped_pid)));
            $actions[] = $row;
            continue;
        }

        $match = bvmgr_ticketing_v2_find_ticket_title_match($unclaimed_existing_ticket_pids, $ticket_label, array(
            'plan_id' => $plan_id,
            'tec_event_id' => $tec_event_id,
            'ticket_key' => $ticket_key,
        ));
        if (($match['status'] ?? '') === 'found') {
            $matched_pid = absint($match['product_id'] ?? 0);
            $row['action'] = 'adopt';
            $row['woo_product_id'] = $matched_pid;
            $match_message = (string) ($match['message'] ?? 'exact_title_match');
            if ($match_message === 'preferred_sold_match') {
                $row['notes'] = 'Matched an existing sold ticket product by exact title. Will adopt that product instead of creating a new path.';
            } elseif ($match_message === 'retired_fallback_match') {
                $row['notes'] = 'Matched a retired exact-title ticket product. Will adopt it instead of creating a new duplicate path.';
            } else {
                $row['notes'] = 'Matched existing Event Ticket by internal/public title. Will adopt.';
            }
            $unclaimed_existing_ticket_pids = array_values(array_diff($unclaimed_existing_ticket_pids, array($matched_pid)));
        } elseif (($match['status'] ?? '') === 'ambiguous') {
            $row['action'] = 'error';
            $row['notes'] = 'Multiple exact-title ticket products are attached to this event. Resolve or retire duplicates before committing so Backstage Venue Manager does not create another public ticket path.';
            $actions[] = $row;
            $blocked = true;
            continue;
        } else {
            $row['action'] = 'create';
            $row['notes'] = 'No mapped ticket found. Will create a new Event Ticket.';
        }

        $actions[] = $row;
    }

    $ticket_product_conflicts = bvmgr_ticketing_v2_detect_ticket_product_action_conflicts($actions);
    if (!empty($ticket_product_conflicts)) {
        foreach ($ticket_product_conflicts as $conflict) {
            $warnings[] = (string) ($conflict['message'] ?? 'A ticket product is claimed by more than one ticket row.');
        }
        $blocked = true;
    }

    if ($enabled_ticket_count < 1) {
        $warnings[] = 'Ticketing config has no enabled ticket rows. Commit will only unpublish mapped disabled tickets.';
    }

    if (!empty($unclaimed_existing_ticket_pids)) {
        $stale_mapped_ticket_product_ids = array();
        foreach ($ticket_sync_map as $mapped_ticket_key => $mapped_ticket_row) {
            $mapped_ticket_key = sanitize_key((string) $mapped_ticket_key);
            if ($mapped_ticket_key === '' || isset($configured_ticket_keys[$mapped_ticket_key]) || !is_array($mapped_ticket_row)) {
                continue;
            }
            $mapped_pid = absint($mapped_ticket_row['woo_product_id'] ?? 0);
            if ($mapped_pid > 0) {
                $stale_mapped_ticket_product_ids[] = $mapped_pid;
            }
        }
        $stale_mapped_ticket_product_ids = array_values(array_unique(array_filter(array_map('absint', $stale_mapped_ticket_product_ids))));

        $left_alone = array();
        foreach ($unclaimed_existing_ticket_pids as $unclaimed_pid) {
            $unclaimed_pid = absint($unclaimed_pid);
            if ($unclaimed_pid <= 0) {
                continue;
            }

            if (bvmgr_ticketing_v2_ticket_product_is_safe_to_retire_from_config($unclaimed_pid, $plan_id, $tec_event_id, $stale_mapped_ticket_product_ids)) {
                $actions[] = array(
                    'scope' => 'ticket_cleanup',
                    'action' => 'retire_unmapped',
                    'ticket_key' => sanitize_key((string) get_post_meta($unclaimed_pid, bvmgr_ticketing_v2_product_meta_key('ticketing_ticket_key'), true)),
                    'label' => bvmgr_ticketing_v2_sanitize_plain_text_label((string) get_the_title($unclaimed_pid)),
                    'woo_product_id' => $unclaimed_pid,
                    'notes' => 'Ticket product is no longer present in the current ticket config. Will unpublish it as draft + hidden so it is removed from the public ticket list without deleting order history.',
                    'changes' => array('status', 'catalog_visibility'),
                    'skip_reason_code' => '',
                    'skip_expected' => 0,
                    'skip_safety_driven' => 0,
                );
            } else {
                $left_alone[] = $unclaimed_pid;
            }
        }

        if (!empty($left_alone)) {
            $warnings[] = 'Linked TEC event has existing ticket products not mapped to current config and not clearly VMS-owned: #' . implode(', #', array_values(array_map('absint', $left_alone))) . '. VMS will leave them alone.';
        }
    }

    // Entitlements preview
    $ents = (isset($cfg['entitlements']) && is_array($cfg['entitlements'])) ? $cfg['entitlements'] : array();
    if (!empty($ents)) {
        $warnings = array_merge($warnings, bvmgr_ticketing_v2_enabled_entitlement_sequence_warnings($ents));
    }
    foreach ($ents as $ent) {
        if (!is_array($ent)) {
            continue;
        }
        $enabled = !empty($ent['enabled']);
        if (!$enabled) {
            continue;
        }

        $ent_id = sanitize_key((string) ($ent['entitlement_id'] ?? ''));
        if ($ent_id === '') {
            continue;
        }

        $ent_hash = bvmgr_ticketing_v2_hash_entitlement($ent);

        $m = (isset($sync_map['entitlements']) && is_array($sync_map['entitlements']) && isset($sync_map['entitlements'][$ent_id]) && is_array($sync_map['entitlements'][$ent_id]))
            ? $sync_map['entitlements'][$ent_id]
            : array();
        $mapped_pid = absint($m['woo_product_id'] ?? 0);

        $row = array(
            'scope' => 'entitlement',
            'entitlement_id' => $ent_id,
            'label' => (string) ($ent['label'] ?? ''),
            'action' => 'noop',
            'woo_product_id' => $mapped_pid,
            'notes' => '',
            'changes' => array(),
            'hash' => $ent_hash,
            'skip_reason_code' => '',
            'skip_expected' => 0,
            'skip_safety_driven' => 0,
        );

        if ($mode !== 'vms_managed') {
            $row['notes'] = ($mode === 'none') ? 'Ticketing mode is none.' : 'Ticketing mode is read-only.';
            $row['skip_reason_code'] = 'mode_not_managed';
            $row['skip_expected'] = 1;
            $actions[] = $row;
            continue;
        }

        if ($blocked) {
            $row['action'] = 'skip';
            $row['notes'] = 'Blocked by upstream conditions.';
            $row['skip_reason_code'] = 'blocked_upstream';
            $row['skip_safety_driven'] = 1;
            $actions[] = $row;
            continue;
        }

        if ($mapped_pid > 0) {
            $pt = get_post_type($mapped_pid);

            // Missing post: stale mapping. Do not error; treat as unmapped and allow adopt/create.
            if (empty($pt)) {
                $warnings[] = 'Entitlement mapping for ' . $ent_id . ' pointed to a missing product (#' . $mapped_pid . '). Mapping will be repaired on commit.';
                $mapped_pid = 0;
                $row['woo_product_id'] = 0;
            } elseif ($pt !== 'product') {
                // Existing post that is not a Woo product is a hard error.
                $row['action'] = 'error';
                $row['notes'] = 'Mapped ID is not a Woo product.';
                $actions[] = $row;
                $blocked = true;
                continue;
            } elseif ((string) get_post_status($mapped_pid) === 'trash') {
                $warnings[] = 'Entitlement mapping for ' . $ent_id . ' pointed to a trashed product (#' . $mapped_pid . '). Mapping will be repaired on commit.';
                $mapped_pid = 0;
                $row['woo_product_id'] = 0;
            }
        }

        if ($mapped_pid > 0) {
            $repair = bvmgr_ticketing_v2_inspect_enabled_entitlement_product($mapped_pid, $ent);
            $prev_hash = (string) ($m['last_sync_hash'] ?? '');
            $config_changed = !($prev_hash !== '' && hash_equals($prev_hash, $ent_hash));
            if (!$config_changed && empty($repair['needs_restore'])) {
                $row['action'] = 'skip';
                $row['notes'] = bvmgr_ticketing_v2_compose_entitlement_preview_note($config_changed, $repair);
                $row['skip_reason_code'] = (string) ($repair['skip_reason_code'] ?? 'already_in_sync');
                $row['skip_expected'] = 1;
            } else {
                $row['action'] = 'update';
                $changes = array();
                if ($config_changed) {
                    $changes = array('title', 'price', 'stock');
                }
                if (!empty($repair['changes']) && is_array($repair['changes'])) {
                    $changes = array_merge($changes, $repair['changes']);
                }
                $row['notes'] = bvmgr_ticketing_v2_compose_entitlement_preview_note($config_changed, $repair);
                $row['changes'] = array_values(array_unique(array_filter(array_map('strval', $changes))));
            }
            $actions[] = $row;
            continue;
        }

        $found = bvmgr_ticketing_v2_find_entitlement_product($plan_id, $ent_id);
        if (($found['status'] ?? '') === 'found') {
            $row['action'] = 'adopt';
            $row['woo_product_id'] = (int) ($found['product_id'] ?? 0);
            $row['notes'] = 'Found an existing entitlement product with matching markers. Will adopt.';
            $actions[] = $row;
            continue;
        }

        if (($found['status'] ?? '') === 'ambiguous') {
            $row['action'] = 'error';
            $row['notes'] = 'Multiple products match this entitlement marker. Resolve by deleting or unmarking duplicates.';
            $actions[] = $row;
            continue;
        }

        $ent_key = sanitize_key((string) ($ent['entitlement_key'] ?? ''));
        if ($ent_key !== '') {
            $legacy = bvmgr_ticketing_v2_find_legacy_entitlement_product_by_key($plan_id, $tec_event_id, $ent_key);
            if (($legacy['status'] ?? '') === 'found') {
                $row['action'] = 'adopt';
                $row['woo_product_id'] = (int) ($legacy['product_id'] ?? 0);
                $row['notes'] = 'Found legacy SR-* entitlement product by SKU pattern. Will adopt and migrate.';
                $actions[] = $row;
                continue;
            }
            if (($legacy['status'] ?? '') === 'ambiguous') {
                $row['action'] = 'error';
                $row['notes'] = 'Multiple legacy SR-* products match this entitlement. Resolve duplicates before committing.';
                $actions[] = $row;
                continue;
            }
        }

        $row['action'] = 'create';
        $row['notes'] = 'Will create a new entitlement product.';
        $actions[] = $row;
    }

    $reconciliation = bvmgr_ticketing_v2_reconcile_event_plan_ticket_cache($plan_id, $tec_event_id, $sync_map, false);
    if (!empty($reconciliation['warnings']) && is_array($reconciliation['warnings'])) {
        $warnings = array_merge($warnings, $reconciliation['warnings']);
    }
    if ($mode === 'vms_managed' && $tec_event_id <= 0) {
        $warnings[] = __('Commit will create and link a draft TEC event shell before applying ticket and add-on changes.', 'backstage-venue-manager');
    }
    $warnings = array_values(array_unique(array_filter(array_map('strval', $warnings))));

    // Store transient preview payload for commit.
    // Commit lookup sanitizes preview_id via sanitize_key() (lowercase).
    // Generate a lowercase-safe ID up front so preview/commit use the same transient key.
    $preview_id = sanitize_key('prev_' . strtolower(wp_generate_password(16, false, false)));
    $payload = array(
        'version' => 2,
        'plan_id' => $plan_id,
        'user_id' => get_current_user_id(),
        'tec_event_id' => $tec_event_id,
        'created_calendar_event' => $created_calendar_event,
        'calendar_event_status' => $created_calendar_event ? 'draft' : '',
        'mode' => $mode,
        'calendar_alignment' => $calendar_alignment,
        'reschedule_required' => is_array($reschedule_required) ? $reschedule_required : array(),
        'config_hash' => $cfg_hash,
        'actions' => $actions,
        'warnings' => $warnings,
        'blocked' => $blocked,
        'reconciliation' => $reconciliation,
        'created_at' => time(),
    );

    set_transient('vms_tix_v2_prev_' . $preview_id, $payload, 15 * MINUTE_IN_SECONDS);

    return array(
        'ok' => true,
        'version' => 2,
        'preview_id' => $preview_id,
        'config_hash' => $cfg_hash,
        'mode' => $mode,
        'tec_event_id' => $tec_event_id,
        'created_calendar_event' => $created_calendar_event,
        'calendar_event_status' => $created_calendar_event ? 'draft' : '',
        'calendar_alignment' => $calendar_alignment,
        'reschedule_required' => is_array($reschedule_required) ? $reschedule_required : array(),
        'blocked' => $blocked,
        'warnings' => $warnings,
        'reconciliation' => $reconciliation,
        'actions' => $actions,
    );
}

function bvmgr_ticketing_v2_apply_saved_product_sort_orders(int $plan_id, array $cfg, array $sync_map): array {
    $plan_id = absint($plan_id);
    $applied = array(
        'tickets' => array(),
        'entitlements' => array(),
    );

    $tickets = is_array($cfg['tickets'] ?? null) ? $cfg['tickets'] : array();
    if (empty($tickets)) {
        $ga = is_array($cfg['ga'] ?? null) ? $cfg['ga'] : array();
        $tickets[] = array(
            'enabled' => true,
            'ticket_key' => 'ga',
            'title' => (string) ($ga['label'] ?? 'GA Admission'),
            'price' => (string) ($ga['price'] ?? '0'),
            'inventory_total' => (int) ($ga['capacity'] ?? 0),
            'sales_start' => (string) ($ga['sales_start'] ?? ''),
            'sales_end' => (string) ($ga['sales_end'] ?? ''),
            'visibility_mode' => 'public',
            'sort_order' => 10,
        );
    }

    $ticket_fallback = 10;
    foreach ($tickets as $ticket_row) {
        if (!is_array($ticket_row)) {
            continue;
        }
        $ticket_key = sanitize_key((string) ($ticket_row['ticket_key'] ?? $ticket_row['key'] ?? ''));
        if ($ticket_key === '') {
            continue;
        }

        $map_row = (isset($sync_map['tickets'][$ticket_key]) && is_array($sync_map['tickets'][$ticket_key])) ? $sync_map['tickets'][$ticket_key] : array();
        $pid = absint($map_row['woo_product_id'] ?? 0);
        if ($pid <= 0) {
            $ticket_fallback += 10;
            continue;
        }

        $menu_order = bvmgr_ticketing_b_normalize_sort_order($ticket_row['sort_order'] ?? 0, $ticket_fallback);
        $sort_apply = bvmgr_ticketing_b_apply_product_sort_order($pid, $menu_order, $ticket_fallback, 'ticket');
        if (!empty($sort_apply['ok'])) {
            $applied['tickets'][$ticket_key] = $sort_apply;
        }
        $ticket_fallback += 10;
    }

    $entitlements = is_array($cfg['entitlements'] ?? null) ? $cfg['entitlements'] : array();
    $ent_fallback = 10;
    foreach ($entitlements as $ent_row) {
        if (!is_array($ent_row)) {
            continue;
        }
        $ent_id = sanitize_key((string) ($ent_row['entitlement_id'] ?? ''));
        if ($ent_id === '') {
            $ent_fallback += 10;
            continue;
        }

        $map_row = (isset($sync_map['entitlements'][$ent_id]) && is_array($sync_map['entitlements'][$ent_id])) ? $sync_map['entitlements'][$ent_id] : array();
        $pid = absint($map_row['woo_product_id'] ?? 0);
        if ($pid <= 0) {
            $ent_fallback += 10;
            continue;
        }

        $menu_order = bvmgr_ticketing_b_normalize_sort_order($ent_row['sort_order'] ?? 0, $ent_fallback);
        $sort_apply = bvmgr_ticketing_b_apply_product_sort_order($pid, $menu_order, $ent_fallback, 'entitlement');
        if (!empty($sort_apply['ok'])) {
            $applied['entitlements'][$ent_id] = $sort_apply;
        }
        $ent_fallback += 10;
    }

    return $applied;
}


function bvmgr_ticketing_v2_commit_error_summary(string $code): string {
    $code = sanitize_key($code);
    switch ($code) {
        case 'invalid_payload':
            return __('Backstage Venue Manager could not start the commit because the request payload was incomplete.', 'backstage-venue-manager');
        case 'forbidden':
            return __('Your account does not have permission to commit ticket changes for this Event Plan.', 'backstage-venue-manager');
        case 'missing_preview':
            return __('Backstage Venue Manager could not find the Preview snapshot for this commit. The preview may have expired or never finished saving.', 'backstage-venue-manager');
        case 'preview_owner_mismatch':
            return __('The Preview snapshot belongs to a different user session, so Backstage Venue Manager refused to apply it.', 'backstage-venue-manager');
        case 'preview_blocked':
            return __('The last Preview is still blocked by one or more ticketing issues, so Commit was stopped on purpose.', 'backstage-venue-manager');
        case 'preview_not_managed':
        case 'not_managed_mode':
            return __('Ticketing is not in VMS-managed mode, so Commit cannot create or update ticket products.', 'backstage-venue-manager');
        case 'stale_config':
            return __('The ticketing settings changed after the last Preview, so that Preview is no longer safe to commit.', 'backstage-venue-manager');
        case 'ticket_product_mapping_conflict':
            return __('Two or more ticket rows are trying to control the same Woo ticket product, so Commit was stopped to protect existing sales.', 'backstage-venue-manager');
        case 'missing_tec_link':
            return __('No linked TEC event was available for this commit, so Backstage Venue Manager had nowhere safe to attach the tickets.', 'backstage-venue-manager');
        case 'calendar_event_out_of_sync':
            return __('The Event Plan occurrence does not match its linked calendar event, so Backstage Venue Manager refused to derive ticket sale dates from stale calendar data.', 'backstage-venue-manager');
        case 'stale_calendar_occurrence':
            return __('The Event Plan or linked calendar occurrence changed after Preview, so that Preview is no longer safe to commit.', 'backstage-venue-manager');
        case 'completed_event_reschedule_required':
            return __('Backstage Venue Manager will not reopen native ticket sales for an occurrence that had already completed. An explicit Reschedule workflow is required.', 'backstage-venue-manager');
        case 'commit_not_ready_to_finalize':
            return __('Commit batching had not finished preparing all ticket actions, so Backstage Venue Manager refused to finalize a partial sync.', 'backstage-venue-manager');
        case 'event_tickets_woo_unavailable':
            return __('Event Tickets (WooCommerce) is not available right now, so Backstage Venue Manager cannot create or sync tickets.', 'backstage-venue-manager');
        default:
            return __('Commit failed before Backstage Venue Manager could safely apply the ticket changes.', 'backstage-venue-manager');
    }
}

function bvmgr_ticketing_v2_commit_error_steps(string $code, array $diagnostics = array()): array {
    $code = sanitize_key($code);
    $steps = array();

    switch ($code) {
        case 'missing_preview':
            $steps[] = __('Click “Preview sync” again to generate a fresh snapshot, then try Commit again.', 'backstage-venue-manager');
            break;
        case 'preview_blocked':
            $steps[] = __('Review the blocked issues shown in Preview, fix them, then run “Preview sync” again.', 'backstage-venue-manager');
            break;
        case 'preview_not_managed':
        case 'not_managed_mode':
            $steps[] = __('Set Mode to “VMS-managed”, click “Save config”, then run “Preview sync” again before committing.', 'backstage-venue-manager');
            break;
        case 'stale_config':
            $steps[] = __('Run “Preview sync” again so Backstage Venue Manager can compare the current config before committing.', 'backstage-venue-manager');
            break;
        case 'ticket_product_mapping_conflict':
            $steps[] = __('Review the ticket Preview for duplicate product IDs, then save/preview again after each ticket row points to its own product or has no mapped product.', 'backstage-venue-manager');
            break;
        case 'missing_tec_link':
            $steps[] = __('Run “Preview sync” again so Backstage Venue Manager can create or relink the TEC event before committing.', 'backstage-venue-manager');
            break;
        case 'calendar_event_out_of_sync':
            $steps[] = __('Publish or re-sync the Event Plan to its calendar event, then run “Preview sync” again.', 'backstage-venue-manager');
            break;
        case 'stale_calendar_occurrence':
            $steps[] = __('Run “Preview sync” again after the calendar occurrence is synchronized.', 'backstage-venue-manager');
            break;
        case 'completed_event_reschedule_required':
            $steps[] = __('Leave the completed occurrence closed. Use the future explicit Reschedule workflow if the event did not actually occur.', 'backstage-venue-manager');
            break;
        case 'commit_not_ready_to_finalize':
            $steps[] = __('Run “Preview sync” again to rebuild the action list, then retry Commit from the beginning.', 'backstage-venue-manager');
            break;
        case 'event_tickets_woo_unavailable':
            $steps[] = __('Activate Event Tickets, Event Tickets Plus, and WooCommerce, then try Preview → Commit again.', 'backstage-venue-manager');
            break;
        case 'preview_owner_mismatch':
            $steps[] = __('Generate a fresh Preview in your current browser session, then commit that new Preview.', 'backstage-venue-manager');
            break;
        case 'forbidden':
            $steps[] = __('Use an account that can edit this Event Plan, or ask an authorized admin to run the commit.', 'backstage-venue-manager');
            break;
    }

    $untracked = is_array($diagnostics['untracked_event_ticket_product_ids'] ?? null) ? array_values(array_filter(array_map('absint', $diagnostics['untracked_event_ticket_product_ids']))) : array();
    if (!empty($untracked)) {
        $steps[] = sprintf(
            /* translators: %s: comma-separated linked TEC ticket product IDs VMS is not tracking. */
            __('This linked TEC event already has ticket products Backstage Venue Manager is not tracking: %s. Backstage Venue Manager will not delete them automatically.', 'backstage-venue-manager'),
            '#' . implode(', #', $untracked)
        );
    }

    $verified_issues = is_array($diagnostics['verified_ticket_rule_issues'] ?? null) ? array_values(array_filter(array_map('strval', $diagnostics['verified_ticket_rule_issues']))) : array();
    if (!empty($verified_issues)) {
        $steps[] = __('At least one qualified ticket is missing its credential rule, so qualification enforcement may not work until you fix that row and preview again.', 'backstage-venue-manager');
    }

    return array_values(array_unique(array_filter(array_map('strval', $steps))));
}

function bvmgr_ticketing_v2_build_commit_failure_diagnostics(int $plan_id, array $context = array()): array {
    $plan_id = absint($plan_id);
    $context = is_array($context) ? $context : array();
    $message = sanitize_key((string) ($context['message'] ?? 'error'));

    $cfg = ($plan_id > 0) ? bvmgr_ticketing_v2_get_config($plan_id) : array();
    $current_mode = is_array($cfg) ? (string) ($cfg['mode'] ?? 'read_only') : 'read_only';
    $linked_tec_event_id = ($plan_id > 0 && function_exists('bvmgr_ticketing_b_get_linked_tec_event_id')) ? absint(bvmgr_ticketing_b_get_linked_tec_event_id($plan_id)) : 0;
    $linked_tec_event_title = ($linked_tec_event_id > 0) ? (string) get_the_title($linked_tec_event_id) : '';

    $sync = ($plan_id > 0) ? bvmgr_ticketing_v2_get_sync($plan_id) : array();
    $sync_map = (isset($sync['map']) && is_array($sync['map'])) ? $sync['map'] : array();
    $sync_map_ticket_product_ids = function_exists('bvmgr_ticketing_v2_collect_sync_map_product_ids')
        ? array_values(array_filter(array_map('absint', bvmgr_ticketing_v2_collect_sync_map_product_ids($sync_map))))
        : array();

    $existing_ticket_product_ids = array();
    if ($linked_tec_event_id > 0 && function_exists('bvmgr_ticketing_b_get_event_ticket_products')) {
        $existing_ticket_product_ids = array_values(array_filter(array_map('absint', (array) bvmgr_ticketing_b_get_event_ticket_products($linked_tec_event_id))));
    }
    $untracked_event_ticket_product_ids = array_values(array_diff($existing_ticket_product_ids, $sync_map_ticket_product_ids));

    $preview_payload = is_array($context['preview_payload'] ?? null) ? $context['preview_payload'] : array();
    $preview_warnings = is_array($preview_payload['warnings'] ?? null) ? array_values(array_unique(array_filter(array_map('strval', $preview_payload['warnings'])))) : array();
    $preview_age_seconds = 0;
    $preview_created_at = absint($preview_payload['created_at'] ?? 0);
    if ($preview_created_at > 0) {
        $preview_age_seconds = max(0, time() - $preview_created_at);
    }

    $verified_ticket_rule_issues = array();
    $tickets_cfg = is_array($cfg['tickets'] ?? null) ? $cfg['tickets'] : array();
    foreach ($tickets_cfg as $ticket_row) {
        if (!is_array($ticket_row)) {
            continue;
        }
        $enabled = array_key_exists('enabled', $ticket_row) ? !empty($ticket_row['enabled']) : true;
        if (!$enabled) {
            continue;
        }
        $visibility_mode = sanitize_key((string) ($ticket_row['visibility_mode'] ?? 'public'));
        if ($visibility_mode !== 'verified') {
            continue;
        }
        $verified_program = sanitize_key((string) ($ticket_row['verified_program'] ?? ''));
        $allowed_programs = bvmgr_ticketing_v2_normalize_allowed_programs($ticket_row['allowed_programs'] ?? array(), $verified_program);
        $allow_direct_grants = bvmgr_ticketing_v2_truthy($ticket_row['allow_direct_grants'] ?? false, false);
        if (!empty($allowed_programs) || $allow_direct_grants) {
            continue;
        }
        $ticket_label = trim((string) ($ticket_row['title'] ?? $ticket_row['ticket_key'] ?? 'Verified ticket'));
        $verified_ticket_rule_issues[] = sprintf(
            /* translators: %s: human-readable value used in this message. */
            __('%s is set to require credentials, but no credential program or direct-grant rule is configured.', 'backstage-venue-manager'),
            $ticket_label
        );
    }

    $diagnostics = array(
        'stage' => sanitize_key((string) ($context['stage'] ?? 'preflight')),
        'error_code' => $message,
        'summary' => bvmgr_ticketing_v2_commit_error_summary($message),
        'plan_id' => $plan_id,
        'requested_preview_id' => (string) ($context['requested_preview_id'] ?? ''),
        'sanitized_preview_id' => (string) ($context['sanitized_preview_id'] ?? ''),
        'preview_mode' => (string) ($preview_payload['mode'] ?? ''),
        'preview_blocked' => !empty($preview_payload['blocked']) ? 1 : 0,
        'preview_age_seconds' => $preview_age_seconds,
        'preview_warnings' => $preview_warnings,
        'preview_action_count' => is_array($preview_payload['actions'] ?? null) ? count($preview_payload['actions']) : 0,
        'current_mode' => $current_mode,
        'linked_tec_event_id' => $linked_tec_event_id,
        'linked_tec_event_title' => $linked_tec_event_title,
        'existing_ticket_product_ids' => $existing_ticket_product_ids,
        'sync_map_ticket_product_ids' => $sync_map_ticket_product_ids,
        'untracked_event_ticket_product_ids' => $untracked_event_ticket_product_ids,
        'verified_ticket_rule_issues' => $verified_ticket_rule_issues,
    );

    if (isset($context['current_config_hash'])) {
        $diagnostics['current_config_hash'] = (string) $context['current_config_hash'];
    }
    if (isset($context['preview_config_hash'])) {
        $diagnostics['preview_config_hash'] = (string) $context['preview_config_hash'];
    }

    if (isset($context['ticket_product_conflicts']) && is_array($context['ticket_product_conflicts'])) {
        $diagnostics['ticket_product_conflicts'] = $context['ticket_product_conflicts'];
    }

    $diagnostics['suggested_next_steps'] = bvmgr_ticketing_v2_commit_error_steps($message, $diagnostics);

    return $diagnostics;
}

function bvmgr_ticketing_v2_commit_error_response(int $plan_id, string $message, array $context = array()): array {
    $context = is_array($context) ? $context : array();
    $code = sanitize_key($message);
    $http = isset($context['http']) ? (int) $context['http'] : 400;
    $diagnostics = bvmgr_ticketing_v2_build_commit_failure_diagnostics($plan_id, array_merge($context, array('message' => $code)));

    return array(
        'ok' => false,
        'message' => $code,
        'error_code' => $code,
        'error_summary' => (string) ($diagnostics['summary'] ?? ''),
        'diagnostics' => $diagnostics,
        'http' => $http,
    );
}


function bvmgr_ticketing_v2_commit_progress_key(int $plan_id, string $preview_id): string {
    $plan_id = absint($plan_id);
    $preview_id = sanitize_key($preview_id);
    if ($preview_id === '') {
        $preview_id = substr(md5((string) $plan_id), 0, 12);
    }

    return 'vms_tix_v2_cmt_' . $plan_id . '_' . substr(md5($preview_id), 0, 12);
}

function bvmgr_ticketing_v2_get_commit_progress(int $plan_id, string $preview_id): array {
    $key = bvmgr_ticketing_v2_commit_progress_key($plan_id, $preview_id);
    $progress = get_transient($key);

    return is_array($progress) ? $progress : array();
}

function bvmgr_ticketing_v2_set_commit_progress(int $plan_id, string $preview_id, array $progress): void {
    $key = bvmgr_ticketing_v2_commit_progress_key($plan_id, $preview_id);
    $progress = is_array($progress) ? $progress : array();
    $progress['plan_id'] = absint($plan_id);
    $progress['preview_id'] = sanitize_key($preview_id);
    $progress['updated_at'] = time();
    if (empty($progress['started_at'])) {
        $progress['started_at'] = time();
    }
    set_transient($key, $progress, 15 * MINUTE_IN_SECONDS);
}

function bvmgr_ticketing_v2_clear_commit_progress(int $plan_id, string $preview_id): void {
    $key = bvmgr_ticketing_v2_commit_progress_key($plan_id, $preview_id);
    delete_transient($key);
}

function bvmgr_ticketing_v2_commit_action_priority(array $action): int {
    $scope = sanitize_key((string) ($action['scope'] ?? ''));
    $operation = sanitize_key((string) ($action['action'] ?? ''));

    if ($scope === 'ticket' || $scope === 'ga') {
        switch ($operation) {
            case 'create': return 10;
            case 'adopt': return 20;
            case 'update': return 30;
            case 'disable': return 40;
            default: return 90;
        }
    }

    if ($scope === 'ticket_cleanup') {
        switch ($operation) {
            case 'retire_unmapped': return 45;
            default: return 95;
        }
    }

    if ($scope === 'entitlement') {
        switch ($operation) {
            case 'create': return 110;
            case 'adopt': return 120;
            case 'update': return 130;
            case 'disable': return 140;
            default: return 190;
        }
    }

    return 500;
}

function bvmgr_ticketing_v2_commit_action_weight(array $action): int {
    $scope = sanitize_key((string) ($action['scope'] ?? ''));
    $operation = sanitize_key((string) ($action['action'] ?? ''));

    if ($scope === 'ticket' || $scope === 'ga') {
        switch ($operation) {
            case 'create': return 5;
            case 'adopt': return 3;
            case 'update': return 3;
            case 'disable': return 2;
            default: return 1;
        }
    }

    if ($scope === 'ticket_cleanup') {
        switch ($operation) {
            case 'retire_unmapped': return 2;
            default: return 1;
        }
    }

    if ($scope === 'entitlement') {
        switch ($operation) {
            case 'create': return 4;
            case 'adopt': return 2;
            case 'update': return 2;
            case 'disable': return 1;
            default: return 1;
        }
    }

    return 1;
}

function bvmgr_ticketing_v2_order_commit_actions(array $actions): array {
    $normalized = array();
    foreach ($actions as $index => $action) {
        if (!is_array($action)) {
            continue;
        }
        $action['__vms_original_index'] = (int) $index;
        $normalized[] = $action;
    }

    usort($normalized, static function (array $left, array $right): int {
        $left_priority = bvmgr_ticketing_v2_commit_action_priority($left);
        $right_priority = bvmgr_ticketing_v2_commit_action_priority($right);
        if ($left_priority === $right_priority) {
            return ((int) ($left['__vms_original_index'] ?? 0)) <=> ((int) ($right['__vms_original_index'] ?? 0));
        }
        return $left_priority <=> $right_priority;
    });

    foreach ($normalized as &$action) {
        unset($action['__vms_original_index']);
    }
    unset($action);

    return $normalized;
}

function bvmgr_ticketing_v2_slice_commit_actions(array $actions, int $cursor, int $max_actions, int $max_budget): array {
    $total = count($actions);
    $cursor = max(0, min($total, $cursor));
    $max_actions = max(1, $max_actions);
    $max_budget = max(1, $max_budget);

    $selected = array();
    $next_cursor = $cursor;
    $budget_used = 0;

    for ($i = $cursor; $i < $total; $i++) {
        $action = $actions[$i];
        if (!is_array($action)) {
            $next_cursor = $i + 1;
            continue;
        }

        $weight = max(1, bvmgr_ticketing_v2_commit_action_weight($action));
        if (!empty($selected) && (count($selected) >= $max_actions || ($budget_used + $weight) > $max_budget)) {
            break;
        }

        $selected[] = $action;
        $budget_used += $weight;
        $next_cursor = $i + 1;

        if (count($selected) >= $max_actions || $budget_used >= $max_budget) {
            break;
        }
    }

    return array(
        'actions' => $selected,
        'cursor' => $cursor,
        'next_cursor' => $next_cursor,
        'done' => ($next_cursor >= $total),
        'total' => $total,
        'budget_used' => $budget_used,
        'batch_count' => count($selected),
    );
}

function bvmgr_ticketing_v2_cleanup_preview_keys(array $preview_ids, string $primary_key = ''): void {
    $preview_ids = array_values(array_unique(array_filter(array_map('strval', $preview_ids))));
    foreach ($preview_ids as $pid) {
        $cleanup_key = 'vms_tix_v2_prev_' . $pid;
        if ($primary_key !== '' && $cleanup_key === $primary_key) {
            delete_transient($cleanup_key);
            continue;
        }
        delete_transient($cleanup_key);
    }
    if ($primary_key !== '') {
        delete_transient($primary_key);
    }
}

function bvmgr_ticketing_v2_commit_sync(int $plan_id, string $preview_id, array $options = array()): array {
    $plan_id = absint($plan_id);
    $preview_id_raw = trim((string) $preview_id);
    $preview_id = sanitize_key($preview_id_raw);
    $options = is_array($options) ? $options : array();
    $requested_phase = sanitize_key((string) ($options['phase'] ?? 'prepare'));
    if (!in_array($requested_phase, array('prepare', 'actions', 'finalize'), true)) {
        $requested_phase = 'prepare';
    }
    $requested_cursor = isset($options['cursor']) ? max(0, (int) $options['cursor']) : 0;
    $max_batch_actions = max(1, (int) apply_filters('vms_ticketing_v2_commit_batch_max_actions', isset($options['max_actions']) ? (int) $options['max_actions'] : 4, $plan_id));
    $max_batch_budget = max(1, (int) apply_filters('vms_ticketing_v2_commit_batch_budget', isset($options['max_budget']) ? (int) $options['max_budget'] : 10, $plan_id));

    if ($plan_id <= 0 || ($preview_id_raw === '' && $preview_id === '')) {
        return bvmgr_ticketing_v2_commit_error_response($plan_id, 'invalid_payload', array(
            'stage' => 'request_validation',
            'requested_preview_id' => $preview_id_raw,
            'sanitized_preview_id' => $preview_id,
        ));
    }
    if (!current_user_can('edit_post', $plan_id)) {
        return bvmgr_ticketing_v2_commit_error_response($plan_id, 'forbidden', array(
            'stage' => 'request_validation',
            'http' => 403,
            'requested_preview_id' => $preview_id_raw,
            'sanitized_preview_id' => $preview_id,
        ));
    }
	if (function_exists('bvmgr_event_plan_is_externally_ticketed') && bvmgr_event_plan_is_externally_ticketed($plan_id)) {
		return bvmgr_ticketing_v2_commit_error_response($plan_id, 'external_ticketing', array(
			'stage' => 'request_validation',
			'http' => 409,
			'message' => __('External Ticketing is active. Native ticket products were not created or changed.', 'backstage-venue-manager'),
		));
	}

    $preview_ids = array_values(array_unique(array_filter(array(
        $preview_id_raw,
        $preview_id,
    ), 'strlen')));

    $key = '';
    $payload = null;
    foreach ($preview_ids as $pid) {
        $candidate_key = 'vms_tix_v2_prev_' . $pid;
        $candidate_payload = get_transient($candidate_key);
        if (is_array($candidate_payload)) {
            $key = $candidate_key;
            $payload = $candidate_payload;
            break;
        }
    }

    if (!is_array($payload) || (int) ($payload['plan_id'] ?? 0) !== $plan_id) {
        return bvmgr_ticketing_v2_commit_error_response($plan_id, 'missing_preview', array(
            'stage' => 'preview_lookup',
            'requested_preview_id' => $preview_id_raw,
            'sanitized_preview_id' => $preview_id,
        ));
    }

    if ((int) ($payload['user_id'] ?? 0) !== get_current_user_id()) {
        return bvmgr_ticketing_v2_commit_error_response($plan_id, 'preview_owner_mismatch', array(
            'stage' => 'preview_validation',
            'requested_preview_id' => $preview_id_raw,
            'sanitized_preview_id' => $preview_id,
            'preview_payload' => $payload,
        ));
    }

    if (!empty($payload['blocked'])) {
        return bvmgr_ticketing_v2_commit_error_response($plan_id, 'preview_blocked', array(
            'stage' => 'preview_validation',
            'requested_preview_id' => $preview_id_raw,
            'sanitized_preview_id' => $preview_id,
            'preview_payload' => $payload,
        ));
    }

    // Guardrail: preview must be generated in VMS-managed mode. Otherwise a commit
    // could "succeed" with only NOOP actions from read-only mode.
    if ((string) ($payload['mode'] ?? '') !== 'vms_managed') {
        return bvmgr_ticketing_v2_commit_error_response($plan_id, 'preview_not_managed', array(
            'stage' => 'preview_validation',
            'requested_preview_id' => $preview_id_raw,
            'sanitized_preview_id' => $preview_id,
            'preview_payload' => $payload,
        ));
    }

    // Ensure config has not changed since preview.
    $cfg = bvmgr_ticketing_v2_get_config($plan_id);
    $cfg_hash_now = bvmgr_ticketing_v2_hash_config_for_sync($cfg);
    if ($cfg_hash_now !== (string) ($payload['config_hash'] ?? '')) {
        return bvmgr_ticketing_v2_commit_error_response($plan_id, 'stale_config', array(
            'stage' => 'config_guard',
            'requested_preview_id' => $preview_id_raw,
            'sanitized_preview_id' => $preview_id,
            'preview_payload' => $payload,
            'current_config_hash' => $cfg_hash_now,
            'preview_config_hash' => (string) ($payload['config_hash'] ?? ''),
        ));
    }

    $mode = (string) ($cfg['mode'] ?? 'read_only');
    if ($mode !== 'vms_managed') {
        return bvmgr_ticketing_v2_commit_error_response($plan_id, 'not_managed_mode', array(
            'stage' => 'config_guard',
            'requested_preview_id' => $preview_id_raw,
            'sanitized_preview_id' => $preview_id,
            'preview_payload' => $payload,
            'current_config_hash' => $cfg_hash_now,
            'preview_config_hash' => (string) ($payload['config_hash'] ?? ''),
        ));
    }

    $ticket_product_conflicts = bvmgr_ticketing_v2_detect_ticket_product_action_conflicts((isset($payload['actions']) && is_array($payload['actions'])) ? $payload['actions'] : array());
    if (!empty($ticket_product_conflicts)) {
        return bvmgr_ticketing_v2_commit_error_response($plan_id, 'ticket_product_mapping_conflict', array(
            'stage' => 'product_mapping_guard',
            'requested_preview_id' => $preview_id_raw,
            'sanitized_preview_id' => $preview_id,
            'preview_payload' => $payload,
            'ticket_product_conflicts' => $ticket_product_conflicts,
            'current_config_hash' => $cfg_hash_now,
            'preview_config_hash' => (string) ($payload['config_hash'] ?? ''),
        ));
    }

    $tec_event_id = absint($payload['tec_event_id'] ?? 0);
    $prepared_calendar_event = false;
    if ($requested_phase === 'prepare' || $tec_event_id <= 0) {
        $existing_link = bvmgr_ticketing_b_get_linked_tec_event_id($plan_id);
        if ($existing_link > 0) {
            $tec_event_id = $existing_link;
        } else {
            $ens = bvmgr_ticketing_v2_ensure_tec_event_link($plan_id);
            if (empty($ens['ok'])) {
                $msg = isset($ens['message']) ? (string) $ens['message'] : 'missing_tec_link';
                return bvmgr_ticketing_v2_commit_error_response($plan_id, $msg, array(
                    'stage' => 'prepare_link',
                    'requested_preview_id' => $preview_id_raw,
                    'sanitized_preview_id' => $preview_id,
                    'preview_payload' => $payload,
                    'current_config_hash' => $cfg_hash_now,
                    'preview_config_hash' => (string) ($payload['config_hash'] ?? ''),
                ));
            }
            $tec_event_id = absint($ens['tec_event_id'] ?? 0);
            $prepared_calendar_event = !empty($ens['created']);
        }

        if ($tec_event_id > 0) {
            $payload['tec_event_id'] = $tec_event_id;
            $payload['created_calendar_event'] = $prepared_calendar_event;
            $payload['calendar_event_status'] = $prepared_calendar_event ? 'draft' : ((string) ($payload['calendar_event_status'] ?? ''));
            set_transient($key, $payload, 15 * MINUTE_IN_SECONDS);
        }
    }
    if ($tec_event_id <= 0) {
        return bvmgr_ticketing_v2_commit_error_response($plan_id, 'missing_tec_link', array(
            'stage' => 'link_guard',
            'requested_preview_id' => $preview_id_raw,
            'sanitized_preview_id' => $preview_id,
            'preview_payload' => $payload,
            'current_config_hash' => $cfg_hash_now,
            'preview_config_hash' => (string) ($payload['config_hash'] ?? ''),
        ));
    }

    $reschedule_required = get_post_meta($plan_id, '_vms_ticketing_reschedule_required_v1', true);
    if (
        is_array($reschedule_required)
        && absint($reschedule_required['tec_event_id'] ?? 0) === $tec_event_id
    ) {
        return bvmgr_ticketing_v2_commit_error_response($plan_id, 'completed_event_reschedule_required', array(
            'stage' => 'completed_event_guard',
            'http' => 409,
            'requested_preview_id' => $preview_id_raw,
            'sanitized_preview_id' => $preview_id,
            'preview_payload' => $payload,
            'reschedule_required' => $reschedule_required,
            'current_config_hash' => $cfg_hash_now,
            'preview_config_hash' => (string) ($payload['config_hash'] ?? ''),
        ));
    }

    $calendar_alignment = bvmgr_ticketing_v2_plan_calendar_alignment($plan_id, $tec_event_id);
    if (empty($calendar_alignment['checkable']) || empty($calendar_alignment['aligned'])) {
        return bvmgr_ticketing_v2_commit_error_response($plan_id, 'calendar_event_out_of_sync', array(
            'stage' => 'calendar_alignment_guard',
            'http' => 409,
            'requested_preview_id' => $preview_id_raw,
            'sanitized_preview_id' => $preview_id,
            'preview_payload' => $payload,
            'calendar_alignment' => $calendar_alignment,
            'current_config_hash' => $cfg_hash_now,
            'preview_config_hash' => (string) ($payload['config_hash'] ?? ''),
        ));
    }

    $preview_calendar_alignment = is_array($payload['calendar_alignment'] ?? null) ? $payload['calendar_alignment'] : array();
    if (!empty($preview_calendar_alignment)) {
        foreach (array('expected_start', 'expected_end', 'current_start', 'current_end') as $alignment_key) {
            if ((string) ($preview_calendar_alignment[$alignment_key] ?? '') !== (string) ($calendar_alignment[$alignment_key] ?? '')) {
                return bvmgr_ticketing_v2_commit_error_response($plan_id, 'stale_calendar_occurrence', array(
                    'stage' => 'calendar_alignment_guard',
                    'http' => 409,
                    'requested_preview_id' => $preview_id_raw,
                    'sanitized_preview_id' => $preview_id,
                    'preview_payload' => $payload,
                    'calendar_alignment' => $calendar_alignment,
                    'current_config_hash' => $cfg_hash_now,
                    'preview_config_hash' => (string) ($payload['config_hash'] ?? ''),
                ));
            }
        }
    }

    if ($requested_phase === 'prepare') {
        bvmgr_ticketing_v2_set_commit_progress($plan_id, $preview_id, array(
            'status' => 'prepared',
            'next_cursor' => 0,
            'total_actions' => max(0, count(bvmgr_ticketing_v2_order_commit_actions((isset($payload['actions']) && is_array($payload['actions'])) ? $payload['actions'] : array()))),
            'config_hash' => $cfg_hash_now,
            'tec_event_id' => $tec_event_id,
            'started_at' => time(),
        ));

        return array(
            'ok' => true,
            'phase' => 'prepare',
            'partial' => true,
            'prepared' => true,
            'needs_actions' => true,
            'finished' => false,
            'results' => array(),
            'warnings' => array(),
            'tec_event_id' => $tec_event_id,
            'tec_event_title' => ($tec_event_id > 0) ? (string) get_the_title($tec_event_id) : '',
            'tec_event_view_url' => ($tec_event_id > 0) ? (string) get_permalink($tec_event_id) : '',
            'tec_event_edit_url' => ($tec_event_id > 0) ? (string) get_edit_post_link($tec_event_id, 'raw') : '',
            'created_calendar_event' => $prepared_calendar_event,
            'total_actions' => max(0, count(bvmgr_ticketing_v2_order_commit_actions((isset($payload['actions']) && is_array($payload['actions'])) ? $payload['actions'] : array()))),
            'processed_actions' => 0,
            'remaining_actions' => max(0, count(bvmgr_ticketing_v2_order_commit_actions((isset($payload['actions']) && is_array($payload['actions'])) ? $payload['actions'] : array()))),
            'next_cursor' => 0,
        );
    }

    if (!bvmgr_ticketing_b_is_event_tickets_woo_available()) {
        return bvmgr_ticketing_v2_commit_error_response($plan_id, 'event_tickets_woo_unavailable', array(
            'stage' => 'dependency_guard',
            'requested_preview_id' => $preview_id_raw,
            'sanitized_preview_id' => $preview_id,
            'preview_payload' => $payload,
            'current_config_hash' => $cfg_hash_now,
            'preview_config_hash' => (string) ($payload['config_hash'] ?? ''),
        ));
    }

    $sync = bvmgr_ticketing_v2_get_sync($plan_id);
    if (!is_array($sync)) {
        $sync = array();
    }

    $sync_map = (isset($sync['map']) && is_array($sync['map'])) ? $sync['map'] : array();
    if (!isset($sync_map['tickets']) || !is_array($sync_map['tickets'])) {
        $sync_map['tickets'] = array();
    }
    if (!isset($sync_map['entitlements']) || !is_array($sync_map['entitlements'])) {
        $sync_map['entitlements'] = array();
    }

    // Prune orphan entitlement mappings that are no longer present in current config.
    // Without this, deleted/renamed legacy entitlement IDs can keep stale missing-product
    // warnings alive across subsequent Preview/Commit cycles.
    $allowed_ent_ids = array();
    $cfg_ents = is_array($cfg['entitlements'] ?? null) ? $cfg['entitlements'] : array();
    foreach ($cfg_ents as $ent_row) {
        if (!is_array($ent_row)) {
            continue;
        }
        $ent_id = sanitize_key((string) ($ent_row['entitlement_id'] ?? ''));
        if ($ent_id !== '') {
            $allowed_ent_ids[$ent_id] = true;
        }
    }
    if (!empty($sync_map['entitlements'])) {
        $pruned_ent_map = array();
        foreach ($sync_map['entitlements'] as $ent_id => $ent_sync_row) {
            $ent_id = sanitize_key((string) $ent_id);
            if ($ent_id === '' || !isset($allowed_ent_ids[$ent_id])) {
                continue;
            }
            if (is_array($ent_sync_row)) {
                $pruned_ent_map[$ent_id] = $ent_sync_row;
            }
        }
        $sync_map['entitlements'] = $pruned_ent_map;
    }

    $tickets_cfg = is_array($cfg['tickets'] ?? null) ? $cfg['tickets'] : array();
    if (empty($tickets_cfg)) {
        // Defensive fallback: hydrate from legacy GA-only shape.
        $ga_fallback = is_array($cfg['ga'] ?? null) ? $cfg['ga'] : array();
        $tickets_cfg[] = array(
            'enabled' => true,
            'ticket_key' => 'ga',
            'title' => (string) ($ga_fallback['label'] ?? 'GA Admission'),
            'price' => (string) ($ga_fallback['price'] ?? '0'),
            'inventory_total' => (int) ($ga_fallback['capacity'] ?? 0),
            'sales_start' => (string) ($ga_fallback['sales_start'] ?? ''),
            'sales_end' => (string) ($ga_fallback['sales_end'] ?? ''),
            'visibility_mode' => 'public',
            'verified_program' => '',
            'allowed_programs' => array(),
            'allow_direct_grants' => false,
            'claim_grant_type' => 'event_ticket_eligibility',
            'claims_per_assignee' => 1,
            'require_assignee_email' => true,
            'counts_toward_unlock' => true,
            'max_qty_per_order' => 0,
            'sort_order' => 10,
        );
    }

    $ticket_cfg_by_key = array();
    $allowed_ticket_keys = array();
    $primary_ticket_key = '';
    foreach ($tickets_cfg as $ticket_row) {
        if (!is_array($ticket_row)) {
            continue;
        }
        $key = sanitize_key((string) ($ticket_row['ticket_key'] ?? $ticket_row['key'] ?? ''));
        if ($key === '') {
            continue;
        }
        $allowed_ticket_keys[$key] = true;
        $ticket_cfg_by_key[$key] = $ticket_row;
        if ($primary_ticket_key === '' && (array_key_exists('enabled', $ticket_row) ? !empty($ticket_row['enabled']) : true)) {
            $primary_ticket_key = $key;
        }
    }

    $stale_ticket_map_product_ids = array();
    if (!empty($sync_map['tickets'])) {
        foreach ($sync_map['tickets'] as $ticket_key => $ticket_sync_row) {
            $ticket_key = sanitize_key((string) $ticket_key);
            if ($ticket_key === '' || isset($allowed_ticket_keys[$ticket_key]) || !is_array($ticket_sync_row)) {
                continue;
            }
            $mapped_pid = absint($ticket_sync_row['woo_product_id'] ?? 0);
            if ($mapped_pid > 0) {
                $stale_ticket_map_product_ids[] = $mapped_pid;
            }
        }
        $stale_ticket_map_product_ids = array_values(array_unique(array_filter(array_map('absint', $stale_ticket_map_product_ids))));

        $pruned_ticket_map = array();
        foreach ($sync_map['tickets'] as $ticket_key => $ticket_sync_row) {
            $ticket_key = sanitize_key((string) $ticket_key);
            if ($ticket_key === '' || !isset($allowed_ticket_keys[$ticket_key])) {
                continue;
            }
            if (is_array($ticket_sync_row)) {
                $pruned_ticket_map[$ticket_key] = $ticket_sync_row;
            }
        }
        $sync_map['tickets'] = $pruned_ticket_map;
    }

    $now = time();
    $results = array();

    $actions = (isset($payload['actions']) && is_array($payload['actions'])) ? $payload['actions'] : array();
    $ordered_actions = bvmgr_ticketing_v2_order_commit_actions($actions);
    $total_actions = count($ordered_actions);
    $commit_progress = bvmgr_ticketing_v2_get_commit_progress($plan_id, $preview_id);
    $stored_cursor = max(0, (int) ($commit_progress['next_cursor'] ?? 0));
    $stored_status = sanitize_key((string) ($commit_progress['status'] ?? ''));
    $stored_total_actions = max(0, (int) ($commit_progress['total_actions'] ?? 0));
    if ($stored_total_actions > 0 && $stored_total_actions !== $total_actions) {
        $stored_cursor = 0;
        $stored_status = '';
        bvmgr_ticketing_v2_clear_commit_progress($plan_id, $preview_id);
        $commit_progress = array();
    }

    $batch_cursor = max($requested_cursor, $stored_cursor);
    $needs_finalize = false;
    $batch_meta = array(
        'cursor' => $batch_cursor,
        'next_cursor' => $batch_cursor,
        'done' => true,
        'total' => $total_actions,
        'budget_used' => 0,
        'batch_count' => 0,
    );
    $batch_actions = array();

    if ($requested_phase === 'finalize') {
        if ($stored_status !== 'actions_complete' && $batch_cursor < $total_actions) {
            return bvmgr_ticketing_v2_commit_error_response($plan_id, 'commit_not_ready_to_finalize', array(
                'stage' => 'finalize_guard',
                'requested_preview_id' => $preview_id_raw,
                'sanitized_preview_id' => $preview_id,
                'preview_payload' => $payload,
                'current_config_hash' => $cfg_hash_now,
                'preview_config_hash' => (string) ($payload['config_hash'] ?? ''),
            ));
        }
    } elseif ($stored_status === 'actions_complete' && $stored_cursor >= $total_actions) {
        $needs_finalize = true;
        $batch_meta['cursor'] = $stored_cursor;
        $batch_meta['next_cursor'] = $stored_cursor;
        $batch_meta['done'] = true;
    } else {
        $batch_meta = bvmgr_ticketing_v2_slice_commit_actions($ordered_actions, $batch_cursor, $max_batch_actions, $max_batch_budget);
        $batch_actions = is_array($batch_meta['actions'] ?? null) ? $batch_meta['actions'] : array();
        $batch_cursor = max(0, (int) ($batch_meta['cursor'] ?? $batch_cursor));
    }

    foreach ($batch_actions as $a) {
        if (!is_array($a)) {
            continue;
        }
        $scope = (string) ($a['scope'] ?? '');
        $act = (string) ($a['action'] ?? '');

        if ($scope === 'ticket_cleanup') {
            $pid = absint($a['woo_product_id'] ?? 0);
            $row = array(
                'scope' => 'ticket_cleanup',
                'action' => $act,
                'ticket_key' => sanitize_key((string) ($a['ticket_key'] ?? '')),
                'label' => bvmgr_ticketing_v2_sanitize_plain_text_label((string) ($a['label'] ?? 'Ticket')),
                'ok' => false,
                'woo_product_id' => $pid,
                'message' => '',
            );

            try {
                if ($act !== 'retire_unmapped') {
                    $row['message'] = 'unknown_cleanup_action';
                    $results[] = $row;
                    continue;
                }

                if (!bvmgr_ticketing_v2_ticket_product_is_safe_to_retire_from_config($pid, $plan_id, $tec_event_id, $stale_ticket_map_product_ids)) {
                    $row['message'] = 'retire_safety_check_failed';
                    $results[] = $row;
                    continue;
                }

                $retired = bvmgr_ticketing_v2_retire_ticket_product_from_config($pid, $plan_id, $tec_event_id, 'removed_from_current_config');
                if (empty($retired['ok'])) {
                    $row['message'] = (string) ($retired['message'] ?? 'retire_failed');
                    $results[] = $row;
                    continue;
                }

                $row['ok'] = true;
                $row['message'] = 'retired';
                $results[] = $row;
            } catch (Throwable $e) {
                $row['message'] = 'exception: ' . $e->getMessage();
                $results[] = $row;
            }

            continue;
        }

        if ($scope === 'ticket' || $scope === 'ga') {
            $ticket_key = sanitize_key((string) ($a['ticket_key'] ?? ''));
            if ($ticket_key === '' && $scope === 'ga') {
                $ticket_key = $primary_ticket_key !== '' ? $primary_ticket_key : 'ga';
            }

            $ticket_cfg = ($ticket_key !== '' && isset($ticket_cfg_by_key[$ticket_key]) && is_array($ticket_cfg_by_key[$ticket_key]))
                ? $ticket_cfg_by_key[$ticket_key]
                : null;
            if (!is_array($ticket_cfg)) {
                $results[] = array(
                    'scope' => 'ticket',
                    'ticket_key' => $ticket_key,
                    'label' => '',
                    'action' => $act,
                    'ok' => false,
                    'woo_product_id' => 0,
                    'message' => 'missing_ticket_config',
                );
                continue;
            }

            $ticket_hash = bvmgr_ticketing_v2_hash_ticket($ticket_cfg);
            $ticket_label = (string) ($ticket_cfg['title'] ?? $ticket_key);
            $ticket_visibility_mode = sanitize_key((string) ($ticket_cfg['visibility_mode'] ?? 'public'));
            if (!in_array($ticket_visibility_mode, array('public', 'login', 'verified'), true)) {
                $ticket_visibility_mode = 'public';
            }
            $ticket_verified_program = sanitize_key((string) ($ticket_cfg['verified_program'] ?? ''));
            $ticket_allowed_programs = bvmgr_ticketing_v2_normalize_allowed_programs($ticket_cfg['allowed_programs'] ?? array(), $ticket_verified_program);
            $ticket_allow_direct_grants = bvmgr_ticketing_v2_truthy($ticket_cfg['allow_direct_grants'] ?? false, false);
            $ticket_claim_grant_type = sanitize_key((string) ($ticket_cfg['claim_grant_type'] ?? 'event_ticket_eligibility'));
            $allowed_claim_grant_types = function_exists('bvmgr_ticketing_claims_allowed_grant_types')
                ? (array) bvmgr_ticketing_claims_allowed_grant_types()
                : array('event_ticket_eligibility', 'event_free_admit', 'credential_benefit_override', 'event_grant');
            if (!in_array($ticket_claim_grant_type, $allowed_claim_grant_types, true)) {
                $ticket_claim_grant_type = 'event_ticket_eligibility';
            }
            $ticket_claims_per_assignee = max(0, absint($ticket_cfg['claims_per_assignee'] ?? 1));
            $ticket_require_assignee_email = bvmgr_ticketing_v2_truthy($ticket_cfg['require_assignee_email'] ?? true, true);
            if ($ticket_visibility_mode !== 'verified') {
                $ticket_verified_program = '';
                $ticket_allowed_programs = array();
                $ticket_allow_direct_grants = false;
                $ticket_claim_grant_type = 'event_ticket_eligibility';
                $ticket_claims_per_assignee = 1;
                $ticket_require_assignee_email = true;
            } elseif ($ticket_verified_program === '' && !empty($ticket_allowed_programs)) {
                $ticket_verified_program = (string) $ticket_allowed_programs[0];
            }
            $row = array(
                'scope' => 'ticket',
                'ticket_key' => $ticket_key,
                'label' => $ticket_label,
                'action' => $act,
                'ok' => false,
                'message' => '',
                'woo_product_id' => 0,
            );

            try {
                if ($act === 'disable') {
                    $pid = absint($a['woo_product_id'] ?? 0);
                    if ($pid <= 0 && isset($sync_map['tickets'][$ticket_key]) && is_array($sync_map['tickets'][$ticket_key])) {
                        $pid = absint($sync_map['tickets'][$ticket_key]['woo_product_id'] ?? 0);
                    }
                    if ($pid <= 0 || get_post_type($pid) !== 'product' || (string) get_post_status($pid) === 'trash') {
                        $row['message'] = 'invalid_product_for_disable';
                        $results[] = $row;
                        continue;
                    }

                    $did = false;
                    if (function_exists('wc_get_product')) {
                        $p = wc_get_product($pid);
                        if ($p) {
                            if (method_exists($p, 'set_status')) {
                                $p->set_status('draft');
                            }
                            if (method_exists($p, 'set_catalog_visibility')) {
                                $p->set_catalog_visibility('hidden');
                            }
                            $p->save();
                            $did = true;
                        }
                    }
                    if (!$did) {
                        wp_update_post(array(
                            'ID' => $pid,
                            'post_status' => 'draft',
                        ));
                        update_post_meta($pid, '_visibility', 'hidden');
                    }

                    $ticket_sync_prev = (isset($sync_map['tickets'][$ticket_key]) && is_array($sync_map['tickets'][$ticket_key]))
                        ? $sync_map['tickets'][$ticket_key]
                        : array();
                    $sync_map['tickets'][$ticket_key] = array(
                        'provider' => 'tec_tickets_woo',
                        'ticket_key' => $ticket_key,
                        'label' => $ticket_label,
                        'tec_ticket_id' => absint($ticket_sync_prev['tec_ticket_id'] ?? $pid),
                        'woo_product_id' => $pid,
                        'counts_toward_unlock' => !empty($ticket_cfg['counts_toward_unlock']) ? 1 : 0,
                        'max_qty_per_order' => max(0, absint($ticket_cfg['max_qty_per_order'] ?? 0)),
                        'visibility_mode' => $ticket_visibility_mode,
                        'verified_program' => $ticket_verified_program,
                        'allowed_programs' => $ticket_allowed_programs,
                        'allow_direct_grants' => $ticket_allow_direct_grants ? 1 : 0,
                        'claim_grant_type' => $ticket_claim_grant_type,
                        'claims_per_assignee' => $ticket_claims_per_assignee,
                        'require_assignee_email' => $ticket_require_assignee_email ? 1 : 0,
                        'sync_status' => 'disabled',
                        'last_sync_at' => $now,
                        'last_sync_hash' => $ticket_hash,
                        'last_error' => '',
                    );

                    $row['ok'] = true;
                    $row['woo_product_id'] = $pid;
                    $row['message'] = 'disabled';
                    $results[] = $row;
                    continue;
                }

                if ($act === 'create') {
                    $created = bvmgr_ticketing_v2_create_ticket($tec_event_id, $ticket_cfg);
                    if (empty($created['ok'])) {
                        $row['message'] = (string) ($created['message'] ?? 'create_failed');
                        $results[] = $row;
                        continue;
                    }

                    $pid = absint($created['woo_product_id'] ?? 0);
                    $ticket_id = absint($created['tec_ticket_id'] ?? 0);
                    if ($ticket_id <= 0) {
                        $ticket_id = $pid;
                    }

                    bvmgr_ticketing_v2_stamp_product_markers($pid, $plan_id, $tec_event_id, 'ga_ticket');
                    bvmgr_ticketing_v2_stamp_ticket_runtime_meta($pid, $tec_event_id, $ticket_cfg);
                    bvmgr_ticketing_v2_maybe_mark_primary_ticket_as_rsvp($pid, $ticket_key, $primary_ticket_key, $ticket_cfg);
                    bvmgr_ticketing_v2_apply_ticket_image_policy($pid, $plan_id, $ticket_cfg);
                    $restored = bvmgr_ticketing_v2_restore_enabled_ticket_product($pid);
                    if (empty($restored['ok'])) {
                        $row['message'] = (string) ($restored['message'] ?? 'restore_failed_after_create');
                        $results[] = $row;
                        continue;
                    }

                    $sync_map['tickets'][$ticket_key] = array(
                        'provider' => 'tec_tickets_woo',
                        'ticket_key' => $ticket_key,
                        'label' => $ticket_label,
                        'tec_ticket_id' => $ticket_id,
                        'woo_product_id' => $pid,
                        'counts_toward_unlock' => !empty($ticket_cfg['counts_toward_unlock']) ? 1 : 0,
                        'max_qty_per_order' => max(0, absint($ticket_cfg['max_qty_per_order'] ?? 0)),
                        'visibility_mode' => $ticket_visibility_mode,
                        'verified_program' => $ticket_verified_program,
                        'allowed_programs' => $ticket_allowed_programs,
                        'allow_direct_grants' => $ticket_allow_direct_grants ? 1 : 0,
                        'claim_grant_type' => $ticket_claim_grant_type,
                        'claims_per_assignee' => $ticket_claims_per_assignee,
                        'require_assignee_email' => $ticket_require_assignee_email ? 1 : 0,
                        'sync_status' => 'synced',
                        'last_sync_at' => $now,
                        'last_sync_hash' => $ticket_hash,
                        'last_error' => '',
                    );

                    if ($primary_ticket_key !== '' && $ticket_key === $primary_ticket_key) {
                        $sync_map['ga'] = array(
                            'provider' => 'tec_tickets_woo',
                            'tec_ticket_id' => $ticket_id,
                            'woo_product_id' => $pid,
                            'ticket_key' => $ticket_key,
                            'sync_status' => 'synced',
                            'last_sync_at' => $now,
                            'last_sync_hash' => $ticket_hash,
                            'last_error' => '',
                        );
                    }

                    $row['ok'] = true;
                    $row['woo_product_id'] = $pid;
                    $row['message'] = 'created';
                    $row = array_merge($row, bvmgr_ticketing_v2_extract_inventory_result_meta($created));
                    $results[] = $row;
                    continue;
                }

                if ($act === 'adopt') {
                    $pid = absint($a['woo_product_id'] ?? 0);
                    if ($pid <= 0 || get_post_type($pid) !== 'product' || (string) get_post_status($pid) === 'trash') {
                        $row['message'] = 'invalid_product_for_adopt';
                        $results[] = $row;
                        continue;
                    }

                    $linked = (int) get_post_meta($pid, '_tribe_wooticket_for_event', true);
                    if ($linked !== $tec_event_id) {
                        $row['message'] = 'adopt_linkage_mismatch';
                        $results[] = $row;
                        continue;
                    }

                    $applied = bvmgr_ticketing_v2_apply_ticket_to_product($pid, $tec_event_id, $ticket_cfg);
                    if (empty($applied['ok'])) {
                        $row['message'] = (string) ($applied['message'] ?? 'apply_failed');
                        $results[] = $row;
                        continue;
                    }

                    bvmgr_ticketing_v2_stamp_product_markers($pid, $plan_id, $tec_event_id, 'ga_ticket');
                    bvmgr_ticketing_v2_stamp_ticket_runtime_meta($pid, $tec_event_id, $ticket_cfg);
                    bvmgr_ticketing_v2_maybe_mark_primary_ticket_as_rsvp($pid, $ticket_key, $primary_ticket_key, $ticket_cfg);
                    bvmgr_ticketing_v2_apply_ticket_image_policy($pid, $plan_id, $ticket_cfg);
                    $restored = bvmgr_ticketing_v2_restore_enabled_ticket_product($pid);
                    if (empty($restored['ok'])) {
                        $row['message'] = (string) ($restored['message'] ?? 'restore_failed_after_adopt');
                        $results[] = $row;
                        continue;
                    }

                    $sync_map['tickets'][$ticket_key] = array(
                        'provider' => 'tec_tickets_woo',
                        'ticket_key' => $ticket_key,
                        'label' => $ticket_label,
                        'tec_ticket_id' => $pid,
                        'woo_product_id' => $pid,
                        'counts_toward_unlock' => !empty($ticket_cfg['counts_toward_unlock']) ? 1 : 0,
                        'max_qty_per_order' => max(0, absint($ticket_cfg['max_qty_per_order'] ?? 0)),
                        'visibility_mode' => $ticket_visibility_mode,
                        'verified_program' => $ticket_verified_program,
                        'allowed_programs' => $ticket_allowed_programs,
                        'allow_direct_grants' => $ticket_allow_direct_grants ? 1 : 0,
                        'claim_grant_type' => $ticket_claim_grant_type,
                        'claims_per_assignee' => $ticket_claims_per_assignee,
                        'require_assignee_email' => $ticket_require_assignee_email ? 1 : 0,
                        'sync_status' => 'synced',
                        'last_sync_at' => $now,
                        'last_sync_hash' => $ticket_hash,
                        'last_error' => '',
                    );

                    if ($primary_ticket_key !== '' && $ticket_key === $primary_ticket_key) {
                        $sync_map['ga'] = array(
                            'provider' => 'tec_tickets_woo',
                            'tec_ticket_id' => $pid,
                            'woo_product_id' => $pid,
                            'ticket_key' => $ticket_key,
                            'sync_status' => 'synced',
                            'last_sync_at' => $now,
                            'last_sync_hash' => $ticket_hash,
                            'last_error' => '',
                        );
                    }

                    $row['ok'] = true;
                    $row['woo_product_id'] = $pid;
                    $row['message'] = 'adopted';
                    $row = array_merge($row, bvmgr_ticketing_v2_extract_inventory_result_meta($applied));
                    $results[] = $row;
                    continue;
                }

                if ($act === 'update') {
                    $pid = absint($a['woo_product_id'] ?? 0);
                    if ($pid <= 0 || get_post_type($pid) !== 'product' || (string) get_post_status($pid) === 'trash') {
                        $row['message'] = 'invalid_product_for_update';
                        $results[] = $row;
                        continue;
                    }

                    $applied = bvmgr_ticketing_v2_apply_ticket_to_product($pid, $tec_event_id, $ticket_cfg);
                    if (empty($applied['ok'])) {
                        $row['message'] = (string) ($applied['message'] ?? 'update_failed');
                        $results[] = $row;
                        continue;
                    }

                    bvmgr_ticketing_v2_stamp_product_markers($pid, $plan_id, $tec_event_id, 'ga_ticket');
                    bvmgr_ticketing_v2_stamp_ticket_runtime_meta($pid, $tec_event_id, $ticket_cfg);
                    bvmgr_ticketing_v2_maybe_mark_primary_ticket_as_rsvp($pid, $ticket_key, $primary_ticket_key, $ticket_cfg);
                    bvmgr_ticketing_v2_apply_ticket_image_policy($pid, $plan_id, $ticket_cfg);
                    $restored = bvmgr_ticketing_v2_restore_enabled_ticket_product($pid);
                    if (empty($restored['ok'])) {
                        $row['message'] = (string) ($restored['message'] ?? 'restore_failed_after_update');
                        $results[] = $row;
                        continue;
                    }

                    $sync_map['tickets'][$ticket_key] = array(
                        'provider' => 'tec_tickets_woo',
                        'ticket_key' => $ticket_key,
                        'label' => $ticket_label,
                        'tec_ticket_id' => $pid,
                        'woo_product_id' => $pid,
                        'counts_toward_unlock' => !empty($ticket_cfg['counts_toward_unlock']) ? 1 : 0,
                        'max_qty_per_order' => max(0, absint($ticket_cfg['max_qty_per_order'] ?? 0)),
                        'visibility_mode' => $ticket_visibility_mode,
                        'verified_program' => $ticket_verified_program,
                        'allowed_programs' => $ticket_allowed_programs,
                        'allow_direct_grants' => $ticket_allow_direct_grants ? 1 : 0,
                        'claim_grant_type' => $ticket_claim_grant_type,
                        'claims_per_assignee' => $ticket_claims_per_assignee,
                        'require_assignee_email' => $ticket_require_assignee_email ? 1 : 0,
                        'sync_status' => 'synced',
                        'last_sync_at' => $now,
                        'last_sync_hash' => $ticket_hash,
                        'last_error' => '',
                    );

                    if ($primary_ticket_key !== '' && $ticket_key === $primary_ticket_key) {
                        $sync_map['ga'] = array(
                            'provider' => 'tec_tickets_woo',
                            'tec_ticket_id' => $pid,
                            'woo_product_id' => $pid,
                            'ticket_key' => $ticket_key,
                            'sync_status' => 'synced',
                            'last_sync_at' => $now,
                            'last_sync_hash' => $ticket_hash,
                            'last_error' => '',
                        );
                    }

                    $row['ok'] = true;
                    $row['woo_product_id'] = $pid;
                    $row['message'] = 'updated';
                    $row = array_merge($row, bvmgr_ticketing_v2_extract_inventory_result_meta($applied));
                    $results[] = $row;
                    continue;
                }

                $row['ok'] = true;
                $row['woo_product_id'] = absint($a['woo_product_id'] ?? 0);
                $row['message'] = 'noop';
                $results[] = $row;
            } catch (Throwable $e) {
                $row['message'] = 'exception: ' . $e->getMessage();
                $results[] = $row;
            }

            continue;
        }

        if ($scope === 'entitlement') {
            $ent_id = sanitize_key((string) ($a['entitlement_id'] ?? ''));
            if ($ent_id === '') {
                continue;
            }

            // Find entitlement config.
            $ent_cfg = null;
            $ents = is_array($cfg['entitlements'] ?? null) ? $cfg['entitlements'] : array();
            foreach ($ents as $e) {
                if (is_array($e) && sanitize_key((string) ($e['entitlement_id'] ?? '')) === $ent_id) {
                    $ent_cfg = $e;
                    break;
                }
            }
            if (!is_array($ent_cfg)) {
                $results[] = array(
                    'scope' => 'entitlement',
                    'entitlement_id' => $ent_id,
                    'action' => $act,
                    'ok' => false,
                    'woo_product_id' => 0,
                    'message' => 'missing_entitlement_config',
                );
                continue;
            }

            $ent_hash = bvmgr_ticketing_v2_hash_entitlement($ent_cfg);

            $row = array(
                'scope' => 'entitlement',
                'entitlement_id' => $ent_id,
                'action' => $act,
                'ok' => false,
                'woo_product_id' => 0,
                'message' => '',
            );

            try {
                if ($act === 'create') {
                    $created = bvmgr_ticketing_v2_upsert_entitlement_product($plan_id, $tec_event_id, $ent_cfg, 0);
                    if (empty($created['ok'])) {
                        $row['message'] = (string) ($created['message'] ?? 'create_failed');
                        $results[] = $row;
                        continue;
                    }
                    $pid = absint($created['woo_product_id'] ?? 0);
                    $sync_map['entitlements'][$ent_id] = array(
                        'provider' => 'woo',
                        'woo_product_id' => $pid,
                        'sync_status' => 'synced',
                        'last_sync_at' => $now,
                        'last_sync_hash' => $ent_hash,
                        'last_error' => '',
                    );
                    $row['ok'] = true;
                    $row['woo_product_id'] = $pid;
                    $row['message'] = 'created';
                    $row = array_merge($row, bvmgr_ticketing_v2_extract_inventory_result_meta($created));
                    $results[] = $row;
                    continue;
                }

                if ($act === 'adopt') {
                    $pid = absint($a['woo_product_id'] ?? 0);
                    if ($pid <= 0 || get_post_type($pid) !== 'product') {
                        $row['message'] = 'invalid_product_for_adopt';
                        $results[] = $row;
                        continue;
                    }

                    // Ensure markers are correct.
                    bvmgr_ticketing_v2_stamp_product_markers($pid, $plan_id, $tec_event_id, 'entitlement', $ent_id);

                    // Apply updates to align with config.
                    $updated = bvmgr_ticketing_v2_upsert_entitlement_product($plan_id, $tec_event_id, $ent_cfg, $pid);
                    if (empty($updated['ok'])) {
                        $row['message'] = (string) ($updated['message'] ?? 'update_failed');
                        $results[] = $row;
                        continue;
                    }

                    $sync_map['entitlements'][$ent_id] = array(
                        'provider' => 'woo',
                        'woo_product_id' => $pid,
                        'sync_status' => 'synced',
                        'last_sync_at' => $now,
                        'last_sync_hash' => $ent_hash,
                        'last_error' => '',
                    );

                    $row['ok'] = true;
                    $row['woo_product_id'] = $pid;
                    $row['message'] = 'adopted';
                    $row = array_merge($row, bvmgr_ticketing_v2_extract_inventory_result_meta($updated));
                    $results[] = $row;
                    continue;
                }

                if ($act === 'update') {
                    $pid = absint($a['woo_product_id'] ?? 0);
                    if ($pid <= 0 || get_post_type($pid) !== 'product') {
                        $row['message'] = 'invalid_product_for_update';
                        $results[] = $row;
                        continue;
                    }

                    $updated = bvmgr_ticketing_v2_upsert_entitlement_product($plan_id, $tec_event_id, $ent_cfg, $pid);
                    if (empty($updated['ok'])) {
                        $row['message'] = (string) ($updated['message'] ?? 'update_failed');
                        $results[] = $row;
                        continue;
                    }

                    $sync_map['entitlements'][$ent_id] = array(
                        'provider' => 'woo',
                        'woo_product_id' => $pid,
                        'sync_status' => 'synced',
                        'last_sync_at' => $now,
                        'last_sync_hash' => $ent_hash,
                        'last_error' => '',
                    );

                    $row['ok'] = true;
                    $row['woo_product_id'] = $pid;
                    $row['message'] = 'updated';
                    $row = array_merge($row, bvmgr_ticketing_v2_extract_inventory_result_meta($updated));
                    $results[] = $row;
                    continue;
                }

                $row['ok'] = true;
                $row['woo_product_id'] = absint($a['woo_product_id'] ?? 0);
                $row['message'] = 'noop';
                $results[] = $row;
            } catch (Throwable $e) {
                $row['message'] = 'exception: ' . $e->getMessage();
                $results[] = $row;
            }
        }
    }

    $batch_failed = false;
    foreach ($results as $result_row) {
        if (is_array($result_row) && array_key_exists('ok', $result_row) && $result_row['ok'] === false) {
            $batch_failed = true;
            break;
        }
    }

    // Backward compatibility: keep legacy ga map aligned to the primary ticket row.
    if ($primary_ticket_key !== '' && isset($sync_map['tickets'][$primary_ticket_key]) && is_array($sync_map['tickets'][$primary_ticket_key])) {
        $primary_ticket_map = $sync_map['tickets'][$primary_ticket_key];
        $primary_pid = absint($primary_ticket_map['woo_product_id'] ?? 0);
        if ($primary_pid > 0) {
            $sync_map['ga'] = array(
                'provider' => 'tec_tickets_woo',
                'tec_ticket_id' => absint($primary_ticket_map['tec_ticket_id'] ?? $primary_pid),
                'woo_product_id' => $primary_pid,
                'ticket_key' => $primary_ticket_key,
                'sync_status' => (string) ($primary_ticket_map['sync_status'] ?? 'synced'),
                'last_sync_at' => (int) ($primary_ticket_map['last_sync_at'] ?? $now),
                'last_sync_hash' => (string) ($primary_ticket_map['last_sync_hash'] ?? ''),
                'last_error' => (string) ($primary_ticket_map['last_error'] ?? ''),
            );
        }
    } elseif (isset($sync_map['ga'])) {
        unset($sync_map['ga']);
    }

    $partial_sync_out = array(
        'version' => 2,
        'provider' => 'tec_tickets_woo',
        'tec_event_id' => $tec_event_id,
        'config_hash' => $cfg_hash_now,
        'mode_at_last_commit' => $mode,
        'map' => $sync_map,
        'last_commit' => array(
            'at' => $now,
            'by' => get_current_user_id(),
            'phase' => ($requested_phase === 'finalize') ? 'finalize' : (($requested_phase === 'prepare') ? 'prepare' : 'actions'),
            'next_cursor' => max(0, (int) ($batch_meta['next_cursor'] ?? $batch_cursor)),
            'total_actions' => $total_actions,
            'status' => $batch_failed ? 'error' : (($requested_phase === 'finalize') ? 'finalizing' : (($needs_finalize || !empty($batch_meta['done'])) ? 'actions_complete' : 'actions_in_progress')),
        ),
        'reconciliation' => is_array($sync['reconciliation'] ?? null) ? $sync['reconciliation'] : array(),
        'last_error' => $batch_failed ? 'batch_failed' : '',
    );
    bvmgr_ticketing_v2_set_sync($plan_id, $partial_sync_out);

    if ($requested_phase !== 'finalize') {
        if ($batch_failed) {
            bvmgr_ticketing_v2_clear_commit_progress($plan_id, $preview_id);
            bvmgr_ticketing_v2_cleanup_preview_keys($preview_ids, $key);
            return array(
                'ok' => true,
                'phase' => 'stopped',
                'finished' => true,
                'commit_interrupted' => true,
                'results' => $results,
                'warnings' => array(__('Some items failed. Fix the errors and run Preview sync again before continuing.', 'backstage-venue-manager')),
                'sync' => $partial_sync_out,
                'total_actions' => $total_actions,
                'batch_count' => (int) ($batch_meta['batch_count'] ?? 0),
                'processed_actions' => max(0, (int) ($batch_meta['next_cursor'] ?? $batch_cursor)),
                'remaining_actions' => max(0, $total_actions - max(0, (int) ($batch_meta['next_cursor'] ?? $batch_cursor))),
                'next_cursor' => max(0, (int) ($batch_meta['next_cursor'] ?? $batch_cursor)),
                'tec_event_id' => $tec_event_id,
                'tec_event_title' => ($tec_event_id > 0) ? (string) get_the_title($tec_event_id) : '',
                'tec_event_view_url' => ($tec_event_id > 0) ? (string) get_permalink($tec_event_id) : '',
                'tec_event_edit_url' => ($tec_event_id > 0) ? (string) get_edit_post_link($tec_event_id, 'raw') : '',
            );
        }

        if (!empty($needs_finalize) || !empty($batch_meta['done'])) {
            bvmgr_ticketing_v2_set_commit_progress($plan_id, $preview_id, array(
                'status' => 'actions_complete',
                'next_cursor' => $total_actions,
                'total_actions' => $total_actions,
                'config_hash' => $cfg_hash_now,
                'tec_event_id' => $tec_event_id,
                'started_at' => (int) ($commit_progress['started_at'] ?? $now),
            ));
            return array(
                'ok' => true,
                'phase' => 'actions_complete',
                'partial' => true,
                'needs_finalize' => true,
                'finished' => false,
                'results' => $results,
                'sync' => $partial_sync_out,
                'total_actions' => $total_actions,
                'batch_count' => (int) ($batch_meta['batch_count'] ?? 0),
                'processed_actions' => $total_actions,
                'remaining_actions' => 0,
                'next_cursor' => $total_actions,
                'tec_event_id' => $tec_event_id,
                'tec_event_title' => ($tec_event_id > 0) ? (string) get_the_title($tec_event_id) : '',
                'tec_event_view_url' => ($tec_event_id > 0) ? (string) get_permalink($tec_event_id) : '',
                'tec_event_edit_url' => ($tec_event_id > 0) ? (string) get_edit_post_link($tec_event_id, 'raw') : '',
            );
        }

        bvmgr_ticketing_v2_set_commit_progress($plan_id, $preview_id, array(
            'status' => 'actions_in_progress',
            'next_cursor' => max(0, (int) ($batch_meta['next_cursor'] ?? $batch_cursor)),
            'total_actions' => $total_actions,
            'config_hash' => $cfg_hash_now,
            'tec_event_id' => $tec_event_id,
            'started_at' => (int) ($commit_progress['started_at'] ?? $now),
        ));

        return array(
            'ok' => true,
            'phase' => 'actions',
            'partial' => true,
            'finished' => false,
            'results' => $results,
            'sync' => $partial_sync_out,
            'total_actions' => $total_actions,
            'batch_count' => (int) ($batch_meta['batch_count'] ?? 0),
            'processed_actions' => max(0, (int) ($batch_meta['next_cursor'] ?? $batch_cursor)),
            'remaining_actions' => max(0, $total_actions - max(0, (int) ($batch_meta['next_cursor'] ?? $batch_cursor))),
            'next_cursor' => max(0, (int) ($batch_meta['next_cursor'] ?? $batch_cursor)),
            'tec_event_id' => $tec_event_id,
            'tec_event_title' => ($tec_event_id > 0) ? (string) get_the_title($tec_event_id) : '',
            'tec_event_view_url' => ($tec_event_id > 0) ? (string) get_permalink($tec_event_id) : '',
            'tec_event_edit_url' => ($tec_event_id > 0) ? (string) get_edit_post_link($tec_event_id, 'raw') : '',
        );
    }

    $sort_reapply = bvmgr_ticketing_v2_apply_saved_product_sort_orders($plan_id, $cfg, $sync_map);

    $reconciliation = bvmgr_ticketing_v2_reconcile_event_plan_ticket_cache($plan_id, $tec_event_id, $sync_map, true);
    $recon_warnings = (is_array($reconciliation) && !empty($reconciliation['warnings']) && is_array($reconciliation['warnings']))
        ? $reconciliation['warnings']
        : array();
    $recon_warnings = array_values(array_unique(array_filter(array_map('strval', $recon_warnings))));

    // Legacy suppression: retire any SR-* duplicates for entitlements now managed by Ticketing v2.
    $legacy_cleanup = bvmgr_ticketing_v2_cleanup_legacy_sr_duplicates($plan_id, $tec_event_id, $cfg, $sync_map);
    if (is_array($legacy_cleanup) && !empty($legacy_cleanup['retired']) && is_array($legacy_cleanup['retired']) && function_exists('bvmgr_add_admin_notice')) {
        $count = count($legacy_cleanup['retired']);
        if ($count > 0) {
            /* translators: %d: number used in this message. */
            bvmgr_add_admin_notice(sprintf(__('Retired %d legacy SR-prefixed duplicate products for this event.', 'backstage-venue-manager'), $count), 'warning');
        }
    }
    if (is_array($legacy_cleanup) && !empty($legacy_cleanup['warnings']) && is_array($legacy_cleanup['warnings'])) {
        $recon_warnings = array_merge($recon_warnings, $legacy_cleanup['warnings']);
        $recon_warnings = array_values(array_unique(array_filter(array_map('strval', $recon_warnings))));
    }

    $sync_out = array(
        'version' => 2,
        'provider' => 'tec_tickets_woo',
        'tec_event_id' => $tec_event_id,
        'config_hash' => $cfg_hash_now,
        'mode_at_last_commit' => $mode,
        'map' => $sync_map,
        'last_commit' => array(
            'at' => $now,
            'by' => get_current_user_id(),
        ),
        'reconciliation' => array(
            'sync_status' => (string) ($reconciliation['sync_status'] ?? 'ok'),
            'persist_ok' => !empty($reconciliation['persist_ok']),
            'ticket_product_ids' => is_array($reconciliation['ticket_product_ids'] ?? null) ? $reconciliation['ticket_product_ids'] : array(),
            'warnings' => $recon_warnings,
            'computed_at_gmt' => (int) ($reconciliation['computed_at_gmt'] ?? $now),
        ),
        'last_error' => '',
    );

    bvmgr_ticketing_v2_set_sync($plan_id, $sync_out);

    $duplicate_cleanup = function_exists('bvmgr_ticket_integrity_duplicate_cleanup_run')
        ? bvmgr_ticket_integrity_duplicate_cleanup_run($plan_id, array('source_function' => 'vms_ticketing_v2_commit_sync'))
        : array();
    if (!empty($duplicate_cleanup['ok'])) {
        if (!empty($duplicate_cleanup['summary_text']) && (($duplicate_cleanup['status'] ?? '') === 'complete' || ($duplicate_cleanup['status'] ?? '') === 'partial')) {
            $recon_warnings[] = (string) $duplicate_cleanup['summary_text'];
        }
        if (!empty($duplicate_cleanup['warnings']) && is_array($duplicate_cleanup['warnings'])) {
            $recon_warnings = array_merge($recon_warnings, $duplicate_cleanup['warnings']);
        }
        $recon_warnings = array_values(array_unique(array_filter(array_map('strval', $recon_warnings))));
    }

    $saved_after_cleanup = bvmgr_ticketing_v2_get_sync($plan_id);
    if (is_array($saved_after_cleanup) && !empty($saved_after_cleanup)) {
        $sync_out = $saved_after_cleanup;
        if (!is_array($sync_out['reconciliation'] ?? null)) {
            $sync_out['reconciliation'] = array();
        }
        $sync_out['reconciliation']['warnings'] = $recon_warnings;
    }

    // Validate persistence after write to avoid silent meta divergence.
    $saved_sync = bvmgr_ticketing_v2_get_sync($plan_id);
    $sync_persist_ok = (
        is_array($saved_sync)
        && (int) ($saved_sync['tec_event_id'] ?? 0) === $tec_event_id
        && (string) ($saved_sync['config_hash'] ?? '') === $cfg_hash_now
    );
    if (!$sync_persist_ok) {
        $recon_warnings[] = __('Commit completed, but Backstage Venue Manager could not verify sync persistence. Refresh this page and run Preview again before any further Commit.', 'backstage-venue-manager');
        $recon_warnings = array_values(array_unique(array_filter(array_map('strval', $recon_warnings))));
        $reconciliation['warnings'] = $recon_warnings;
        $reconciliation['sync_status'] = 'mismatch';
        $reconciliation['persist_ok'] = false;
        $sync_out['reconciliation']['sync_status'] = 'mismatch';
        $sync_out['reconciliation']['persist_ok'] = false;
        $sync_out['reconciliation']['warnings'] = $recon_warnings;
        bvmgr_ticketing_v2_set_sync($plan_id, $sync_out);
    }

    if (function_exists('bvmgr_add_admin_notice')) {
        if (!empty($recon_warnings)) {
            $sample = array_slice($recon_warnings, 0, 2);
            $msg = __('Ticketing sync committed, but reconciliation found mismatches:', 'backstage-venue-manager') . ' ' . implode(' ', $sample);
            if (count($recon_warnings) > 2) {
                /* translators: %d: number used in this message. */
                $msg .= ' ' . sprintf(__('(+%d more)', 'backstage-venue-manager'), count($recon_warnings) - 2);
            }
            bvmgr_add_admin_notice($msg, 'warning');
        } else {
            bvmgr_add_admin_notice(__('Ticketing sync committed and canonical ticket IDs were reconciled. Click “Refresh ticket stats” to update sold/revenue totals.', 'backstage-venue-manager'), 'success');
        }
    }

    bvmgr_ticketing_v2_clear_commit_progress($plan_id, $preview_id);
    bvmgr_ticketing_v2_cleanup_preview_keys($preview_ids, $key);

    $tec_event_view_url = ($tec_event_id > 0) ? (string) get_permalink($tec_event_id) : '';
    $tec_event_edit_url = ($tec_event_id > 0) ? (string) get_edit_post_link($tec_event_id, 'raw') : '';

    return array(
        'ok' => true,
        'phase' => 'complete',
        'finished' => true,
        'results' => $results,
        'sync' => $sync_out,
        'warnings' => $recon_warnings,
        'reconciliation' => $reconciliation,
        'sort_reapply' => $sort_reapply,
        'tec_event_id' => $tec_event_id,
        'tec_event_title' => ($tec_event_id > 0) ? (string) get_the_title($tec_event_id) : '',
        'tec_event_view_url' => $tec_event_view_url,
        'tec_event_edit_url' => $tec_event_edit_url,
        'total_actions' => $total_actions,
        'processed_actions' => $total_actions,
        'remaining_actions' => 0,
        'next_cursor' => $total_actions,
    );
}

/**
 * AJAX: save Ticketing v2 config.
 */

if (!function_exists('bvmgr_ticketing_v2_ajax_send_json_success_fast')) {
    /**
     * Send an AJAX JSON success response without waiting on expensive shutdown work.
     *
     * 0.2.24.658 also sets Content-Length and allows the caller to identify the
     * operation with X-VMS-Fast-Ajax so staging can tell whether the browser delay
     * is happening before the handler, during payload transfer, or after PHP work.
     */
    function bvmgr_ticketing_v2_ajax_send_json_success_fast(array $data, string $operation = 'ticketing-v2-save-config'): void
    {
        $operation = sanitize_key($operation);
        if ($operation === '') {
            $operation = 'ticketing-v2-save-config';
        }

        $payload = wp_json_encode(array('success' => true, 'data' => $data));
        if (!is_string($payload) || $payload === '') {
            $payload = '{"success":true,"data":{}}';
        }

        if (!headers_sent()) {
            status_header(200);
            nocache_headers();
            header('Content-Type: application/json; charset=' . get_option('blog_charset'));
            header('X-VMS-Fast-Ajax: ' . $operation);
            header('Content-Length: ' . strlen($payload));
            header('Connection: close');
        }

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        echo $payload;

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            @flush();
        }

        exit;
    }
}

function bvmgr_ticketing_v2_ajax_save_config(): void {
    $handler_entered_at = microtime(true);
    $request_started_at = isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : 0.0;
    $request_age_at_handler_ms = ($request_started_at > 0)
        ? (int) round(max(0.0, $handler_entered_at - $request_started_at) * 1000)
        : 0;

    if (!check_ajax_referer(bvmgr_nonce_action_for_request('bvmgr_ticketing_nonce', 'nonce'), 'nonce', false)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);
    }

    $plan_id = bvmgr_request_read_absint($_POST, 'plan_id');
    if ($plan_id <= 0 || !current_user_can('edit_post', $plan_id)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'forbidden'), 403);
    }

    $config_present = false;
    $config_valid = false;
    $raw_config_bytes = 0;
    $raw = bvmgr_ticketing_b_request_payload_value($_POST, 'config', $config_present, $config_valid, $raw_config_bytes);
    if (!$config_valid) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'invalid_payload_config'), 400);
    }

    $cfg_in = null;
    if (is_array($raw)) {
        if (bvmgr_ticketing_v2_validate_config_payload($raw)) {
            $cfg_in = $raw;
        }
    } elseif (is_string($raw)) {
        $raw = trim($raw);
        if ($raw !== '' && strlen($raw) <= 262144) {
            $decoded = bvmgr_json_decode_associative($raw, 64);
            if (
                !empty($decoded['ok'])
                && is_array($decoded['value'])
                && bvmgr_json_decoded_is_object($decoded['value'], (string) ($decoded['top_level_token'] ?? ''))
                && bvmgr_ticketing_v2_validate_config_payload($decoded['value'])
            ) {
                $cfg_in = $decoded['value'];
            }
        }
    }

    if (!is_array($cfg_in)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'invalid_payload_config'), 400);
    }

    $started_at = microtime(true);
    $cfg_before = bvmgr_ticketing_v2_get_config($plan_id);
    $saved_before_raw = get_post_meta($plan_id, bvmgr_ticketing_v2_k('config'), true);
    $had_saved_config = is_array($saved_before_raw);
    $cfg_normalized = bvmgr_ticketing_v2_normalize_config($cfg_in, $plan_id);
    $cfg = bvmgr_ticketing_v2_hydrate_missing_sales_windows($cfg_normalized, $plan_id);

    $before_hash = bvmgr_ticketing_v2_hash_config_for_sync($cfg_before);
    $input_hash = bvmgr_ticketing_v2_hash_config_for_sync($cfg_normalized);
    $after_hash = bvmgr_ticketing_v2_hash_config_for_sync($cfg);
    $config_changed = (!$had_saved_config || !hash_equals($before_hash, $after_hash));
    $server_adjusted_config = !hash_equals($input_hash, $after_hash);
    $image_sync_results = array();

    if ($config_changed) {
        if (function_exists('bvmgr_ticket_mutation_audit_push_context')) {
            bvmgr_ticket_mutation_audit_push_context(array(
                'trigger_source' => 'manual_action',
                'change_type' => 'ticket_config_saved',
                'summary_text' => __('Saved Ticketing v2 settings for this event.', 'backstage-venue-manager'),
                'source_function' => 'vms_ticketing_v2_ajax_save_config',
                'source_hook' => sanitize_key((string) current_filter()),
                'requested_result_status' => 'success',
            ));
        }
        bvmgr_ticketing_v2_set_config($plan_id, $cfg);
        if (function_exists('bvmgr_ticket_mutation_audit_pop_context')) {
            bvmgr_ticket_mutation_audit_pop_context();
        }
        $image_sync_results = bvmgr_entitlements_sync_plan_image_changes($plan_id, $cfg_before, $cfg);
    } else {
        $GLOBALS['bvmgr_ticketing_v2_last_set_config_noop'] = array(
            'plan_id' => $plan_id,
            'config_hash' => $after_hash,
            'reason' => 'ajax_unchanged_config_hash',
        );
    }

    $elapsed_ms = (int) round((microtime(true) - $started_at) * 1000);
    $handler_elapsed_ms = (int) round((microtime(true) - $handler_entered_at) * 1000);
    $return_config = !empty($_POST['return_config']);

    $response = array(
        'config_hash' => $after_hash,
        'config_changed' => $config_changed,
        'had_saved_config' => $had_saved_config,
        'server_adjusted_config' => $server_adjusted_config,
        'config_omitted' => !$return_config,
        'image_sync_count' => is_array($image_sync_results) ? count($image_sync_results) : 0,
        'elapsed_ms' => $elapsed_ms,
        'handler_elapsed_ms' => $handler_elapsed_ms,
        'request_age_at_handler_ms' => $request_age_at_handler_ms,
        'raw_config_bytes' => $raw_config_bytes,
        'fast_response' => true,
        'minimal_response' => !$return_config,
    );

    if ($return_config) {
        $response['config'] = $cfg;
        $response['config_response_bytes_estimate'] = strlen((string) wp_json_encode($cfg));
        $response['config_omitted'] = false;
        $response['minimal_response'] = false;
    }

    bvmgr_ticketing_v2_ajax_send_json_success_fast($response, 'ticketing-v2-save-config');
}

/**
 * AJAX: save current plan config as a reusable template.
 */
function bvmgr_ticketing_v2_ajax_save_template(): void {
    if (!check_ajax_referer(bvmgr_nonce_action_for_request('bvmgr_ticketing_nonce', 'nonce'), 'nonce', false)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);
    }

    $plan_id = bvmgr_request_read_absint($_POST, 'plan_id');
    if ($plan_id <= 0 || !current_user_can('edit_post', $plan_id)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'forbidden'), 403);
    }

    $name = sanitize_text_field(bvmgr_request_read_scalar($_POST, 'name'));
    $config_present = false;
    $config_valid = false;
    $cfg_raw = bvmgr_ticketing_b_request_payload_value($_POST, 'config', $config_present, $config_valid);
    if ($config_present && !$config_valid) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'invalid_payload_config'), 400);
    }
    if (is_string($cfg_raw) && $cfg_raw !== '') {
        $cfg_raw = trim($cfg_raw);
        if (strlen($cfg_raw) > 262144) {
            bvmgr_ticketing_v2_ajax_send_error(array('message' => 'invalid_payload_config'), 400);
        }
        $decoded = bvmgr_json_decode_associative($cfg_raw, 64);
        if (
            empty($decoded['ok'])
            || !is_array($decoded['value'])
            || !bvmgr_json_decoded_is_object($decoded['value'], (string) ($decoded['top_level_token'] ?? ''))
            || !bvmgr_ticketing_v2_validate_config_payload($decoded['value'])
        ) {
            bvmgr_ticketing_v2_ajax_send_error(array('message' => 'invalid_payload_config'), 400);
        }
        $cfg_in = $decoded['value'];
    } elseif (is_array($cfg_raw)) {
        if (!bvmgr_ticketing_v2_validate_config_payload($cfg_raw)) {
            bvmgr_ticketing_v2_ajax_send_error(array('message' => 'invalid_payload_config'), 400);
        }
        $cfg_in = $cfg_raw;
    } else {
        $cfg_in = array();
    }

    $res = bvmgr_ticketing_v2_templates_save($name, $cfg_in);
    if (empty($res['ok'])) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => $res['message'] ?? 'error'), 400);
    }

    $templates = bvmgr_ticketing_v2_templates_get_all();
    $list = array();
    foreach ($templates as $id => $t) {
        $list[] = array(
            'id' => $id,
            'name' => $t['name'] ?? $id,
            'updated_at' => $t['updated_at'] ?? '',
            'sales_end_guardrail' => (isset($t['sales_end_guardrail']) && is_array($t['sales_end_guardrail']))
                ? $t['sales_end_guardrail']
                : bvmgr_ticketing_v2_template_sales_end_guardrail_summary((array) ($t['config'] ?? array())),
        );
    }
 
    bvmgr_ticketing_v2_ajax_send_success(array(
        'template_id' => (string) ($res['template_id'] ?? ''),
        'templates' => $list,
    ));
}

/**
 * AJAX: apply a saved template to a plan (saves immediately).
 */
function bvmgr_ticketing_v2_ajax_apply_template(): void {
    if (!check_ajax_referer(bvmgr_nonce_action_for_request('bvmgr_ticketing_nonce', 'nonce'), 'nonce', false)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);
    }

    $plan_id = isset($_POST['plan_id']) ? absint($_POST['plan_id']) : 0;
    if ($plan_id <= 0 || !current_user_can('edit_post', $plan_id)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'forbidden'), 403);
    }

    $template_id = isset($_POST['template_id']) ? sanitize_key((string) $_POST['template_id']) : '';
    $show_datetime = isset($_POST['show_datetime']) ? sanitize_text_field(wp_unslash((string) $_POST['show_datetime'])) : '';
    $reset_stale_sales_end = !empty($_POST['reset_stale_sales_end']);

    $res = bvmgr_ticketing_v2_templates_apply_to_plan($plan_id, $template_id, array(
        'show_datetime' => $show_datetime,
        'reset_stale_sales_end' => $reset_stale_sales_end,
    ));
    if (empty($res['ok'])) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => $res['message'] ?? 'error'), 400);
    }

    bvmgr_ticketing_v2_ajax_send_success(array(
        'config' => $res['config'],
        'config_hash' => bvmgr_ticketing_v2_hash_config_for_sync($res['config']),
        'applied_show_datetime' => (string) ($res['applied_show_datetime'] ?? ''),
    ));
}
 
/**
 * AJAX: clear the saved v2 config for this plan (returns to uninitialized).
 */
function bvmgr_ticketing_v2_ajax_clear_config(): void {
    if (!check_ajax_referer(bvmgr_nonce_action_for_request('bvmgr_ticketing_nonce', 'nonce'), 'nonce', false)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);
    }
 
    $plan_id = isset($_POST['plan_id']) ? absint($_POST['plan_id']) : 0;
    if ($plan_id <= 0 || !current_user_can('edit_post', $plan_id)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'forbidden'), 403);
    }

    if (function_exists('bvmgr_ticket_mutation_audit_push_context')) {
        bvmgr_ticket_mutation_audit_push_context(array(
            'trigger_source' => 'manual_action',
            'change_type' => 'ticket_config_cleared',
            'summary_text' => __('Cleared the saved Ticketing v2 config for this event.', 'backstage-venue-manager'),
            'source_function' => 'vms_ticketing_v2_ajax_clear_config',
            'source_hook' => sanitize_key((string) current_filter()),
            'requested_result_status' => 'success',
        ));
    }
    delete_post_meta($plan_id, bvmgr_ticketing_v2_k('config'));
    delete_post_meta($plan_id, '_vms_ticketing_ga_image_mode');
    delete_post_meta($plan_id, '_vms_ticketing_ga_image_id');
    if (function_exists('bvmgr_ticket_mutation_audit_pop_context')) {
        bvmgr_ticket_mutation_audit_pop_context();
    }

    bvmgr_ticketing_v2_ajax_send_success(array(
        'config' => bvmgr_ticketing_v2_default_config($plan_id),
    ));
}

/**
 * AJAX: initialize v2 config from legacy add-on fields (saves immediately).
 */
function bvmgr_ticketing_v2_ajax_init_from_legacy(): void {
    if (!check_ajax_referer(bvmgr_nonce_action_for_request('bvmgr_ticketing_nonce', 'nonce'), 'nonce', false)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);
    }

    $plan_id = isset($_POST['plan_id']) ? absint($_POST['plan_id']) : 0;
    if ($plan_id <= 0 || !current_user_can('edit_post', $plan_id)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'forbidden'), 403);
    }

    bvmgr_ticketing_v2_ajax_send_error(array(
        'message' => 'legacy_init_retired',
        'detail' => __('Legacy Ticketing initializer is retired. Configure Ticketing v2 directly and use Preview → Commit.', 'backstage-venue-manager'),
    ), 400);
}
add_action('wp_ajax_vms_ticketing_v2_save_config', 'bvmgr_ticketing_v2_ajax_save_config');
add_action('wp_ajax_vms_ticketing_v2_save_template', 'bvmgr_ticketing_v2_ajax_save_template');
add_action('wp_ajax_vms_ticketing_v2_apply_template', 'bvmgr_ticketing_v2_ajax_apply_template');
add_action('wp_ajax_vms_ticketing_v2_clear_config', 'bvmgr_ticketing_v2_ajax_clear_config');
add_action('wp_ajax_vms_ticketing_v2_init_from_legacy', 'bvmgr_ticketing_v2_ajax_init_from_legacy');

/**
 * AJAX: set the operator default Ticketing v2 template id.
 */
function bvmgr_ticketing_v2_ajax_set_default_template(): void {
    if (!check_ajax_referer(bvmgr_nonce_action_for_request('bvmgr_ticketing_nonce', 'nonce'), 'nonce', false)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);
    }

    if (!current_user_can('manage_options')) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'forbidden'), 403);
    }

    $template_id = isset($_POST['template_id']) ? sanitize_key((string) $_POST['template_id']) : '';
    $template_id = sanitize_key($template_id);

    if ($template_id !== '') {
        $templates = bvmgr_ticketing_v2_templates_get_all();
        if (empty($templates[$template_id])) {
            bvmgr_ticketing_v2_ajax_send_error(array('message' => 'template_not_found'), 400);
        }
    }

    $ok = bvmgr_ticketing_v2_set_default_template_id($template_id);
    if (!$ok) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'template_not_found'), 400);
    }

    $name = '';
    if ($template_id !== '') {
        $templates = bvmgr_ticketing_v2_templates_get_all();
        if (!empty($templates[$template_id]) && is_array($templates[$template_id])) {
            $name = (string) (($templates[$template_id]['name'] ?? '') ?: $template_id);
        }
    }

    bvmgr_ticketing_v2_ajax_send_success(array(
        'default_template_id' => $template_id,
        'default_template_name' => $name,
    ));
}
add_action('wp_ajax_vms_ticketing_v2_set_default_template', 'bvmgr_ticketing_v2_ajax_set_default_template');


/**
 * AJAX: preview Ticketing v2 sync.
 */
function bvmgr_ticketing_v2_ajax_preview_sync(): void {
    $handler_entered_at = microtime(true);
    $request_started_at = isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : 0.0;
    $request_age_at_handler_ms = ($request_started_at > 0)
        ? (int) round(max(0.0, $handler_entered_at - $request_started_at) * 1000)
        : 0;

    if (!check_ajax_referer(bvmgr_nonce_action_for_request('bvmgr_ticketing_nonce', 'nonce'), 'nonce', false)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);
    }

    $plan_id = isset($_POST['plan_id']) ? absint($_POST['plan_id']) : 0;
    if ($plan_id <= 0 || !current_user_can('edit_post', $plan_id)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'forbidden'), 403);
    }

    $preview_started_at = microtime(true);
    $preview = bvmgr_ticketing_v2_preview_sync($plan_id);
    $preview_elapsed_ms = (int) round((microtime(true) - $preview_started_at) * 1000);

    if (empty($preview['ok'])) {
        $http = isset($preview['http']) ? (int) $preview['http'] : 400;
        bvmgr_ticketing_v2_ajax_send_error(array(
            'message' => $preview['message'] ?? 'error',
            'preview_elapsed_ms' => $preview_elapsed_ms,
            'request_age_at_handler_ms' => $request_age_at_handler_ms,
        ), $http);
    }

    $preview['ajax_timing'] = array(
        'preview_elapsed_ms' => $preview_elapsed_ms,
        'handler_elapsed_ms' => (int) round((microtime(true) - $handler_entered_at) * 1000),
        'request_age_at_handler_ms' => $request_age_at_handler_ms,
        'fast_response' => true,
    );

    bvmgr_ticketing_v2_ajax_send_json_success_fast($preview, 'ticketing-v2-preview-sync');
}
add_action('wp_ajax_vms_ticketing_v2_preview_sync', 'bvmgr_ticketing_v2_ajax_preview_sync');

/**
 * AJAX: commit Ticketing v2 sync.
 */
function bvmgr_ticketing_v2_ajax_commit_sync(): void {
    if (!check_ajax_referer(bvmgr_nonce_action_for_request('bvmgr_ticketing_nonce', 'nonce'), 'nonce', false)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'bad_nonce'), 403);
    }

    $plan_id = bvmgr_request_read_absint($_POST, 'plan_id');
    if ($plan_id <= 0 || !current_user_can('edit_post', $plan_id)) {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'forbidden'), 403);
    }

    $preview_id = sanitize_key(bvmgr_request_read_scalar($_POST, 'preview_id'));
    if ($preview_id === '') {
        bvmgr_ticketing_v2_ajax_send_error(array('message' => 'invalid_payload_preview_id'), 400);
    }

    if (function_exists('bvmgr_ticket_mutation_audit_push_context')) {
        bvmgr_ticket_mutation_audit_push_context(array(
            'trigger_source' => 'preview_commit',
            'change_type' => 'preview_commit_applied',
            'summary_text' => __('Applied Preview / Commit changes for this event.', 'backstage-venue-manager'),
            'source_function' => 'vms_ticketing_v2_ajax_commit_sync',
            'source_hook' => sanitize_key((string) current_filter()),
            'requested_result_status' => 'success',
        ));
    }
    $commit_phase = isset($_POST['commit_phase']) ? sanitize_key((string) wp_unslash($_POST['commit_phase'])) : 'prepare';
    if (!in_array($commit_phase, array('prepare', 'actions', 'finalize'), true)) {
        $commit_phase = 'prepare';
    }
    $cursor = isset($_POST['cursor']) ? max(0, (int) $_POST['cursor']) : 0;
    $res = bvmgr_ticketing_v2_commit_sync($plan_id, $preview_id, array(
        'phase' => $commit_phase,
        'cursor' => $cursor,
    ));
    if (function_exists('bvmgr_ticket_mutation_audit_pop_context')) {
        bvmgr_ticket_mutation_audit_pop_context();
    }
    if (empty($res['ok'])) {
        $http = isset($res['http']) ? (int) $res['http'] : 400;
        bvmgr_ticketing_v2_ajax_send_error(array(
            'message' => $res['message'] ?? 'error',
            'error_code' => $res['error_code'] ?? ($res['message'] ?? 'error'),
            'error_summary' => $res['error_summary'] ?? '',
            'diagnostics' => is_array($res['diagnostics'] ?? null) ? $res['diagnostics'] : array(),
        ), $http);
    }

    bvmgr_ticketing_v2_ajax_send_success($res);
}
add_action('wp_ajax_vms_ticketing_v2_commit_sync', 'bvmgr_ticketing_v2_ajax_commit_sync');
