<?php
/** Event cancellation purchase scope. WooCommerce remains the monetary authority. */
defined('ABSPATH') || exit;

function bvmgr_cancel_money($amount): float
{
    return round((float) $amount, function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2);
}

/** Providers resolve purchase-time ownership, never a product name. */
function bvmgr_cancel_purchase_providers(): array
{
    return (array) apply_filters('vms_cancellation_purchase_providers', array(
        'express_bar' => array('plan_keys' => array('_vms_express_bar_event_plan_id')),
        'event_snapshot' => array('plan_keys' => array('_vms_event_plan_id', 'vms_event_plan_id'), 'tec_keys' => array('_vms_tec_event_post_id', '_vms_tec_event_id', '_tribe_wooticket_for_event')),
    ));
}

function bvmgr_cancel_purchase_identity($item, array $context, array $providers): array
{
    $claims = array();
    $hint = false;
    foreach ($providers as $name => $provider) {
        foreach (array('plan_keys' => 'event_plan_id', 'tec_keys' => 'tec_event_id') as $bucket => $target) {
            foreach (($provider[$bucket] ?? array()) as $key) {
                $raw = $item->get_meta($key, true);
                if ($raw === '' || $raw === null) continue;
                $hint = true;
                $claims[] = array('provider' => $name, 'key' => $key, 'value' => $raw, 'matches' => is_scalar($raw) && ctype_digit((string) $raw) && absint($raw) > 0 && absint($raw) === $context[$target]);
            }
        }
        if (isset($provider['match'])) {
            $claim = call_user_func($provider['match'], $item, $context);
            if ($claim !== null) {
                if (!is_array($claim) || !isset($claim['matches'])) throw new RuntimeException('Invalid purchase provider claim: ' . $name);
                $claims[] = $claim + array('provider' => $name);
                $hint = true;
            }
        }
    }
    $products = array_filter(array_unique(array(method_exists($item, 'get_product_id') ? absint($item->get_product_id()) : 0, method_exists($item, 'get_variation_id') ? absint($item->get_variation_id()) : 0)));
    $product_match = false;
    $product_other = false;
    foreach ($products as $pid) {
        $product_match = $product_match || isset($context['product_lookup'][$pid]);
        foreach (array('_vms_event_plan_id' => 'event_plan_id', '_vms_tec_event_id' => 'tec_event_id', '_tribe_wooticket_for_event' => 'tec_event_id') as $key => $target) {
            $value = absint(get_post_meta($pid, $key, true));
            if ($value > 0) {
                $product_match = $product_match || ($context[$target] > 0 && $value === $context[$target]);
                $product_other = $product_other || ($value !== $context[$target]);
            }
        }
    }
    if ($claims) {
        $matches = array_filter($claims, static function ($c) { return !empty($c['matches']); });
        // An explicit other-event snapshot wins over a shared product catalog match.
        if (!$matches && array_filter($claims, static function ($c) { return isset($c['value']) && (!is_scalar($c['value']) || !ctype_digit((string) $c['value']) || absint($c['value']) === 0); })) return array('state' => 'unresolved', 'provider' => $claims[0]['provider'], 'evidence' => $claims, 'reason' => 'invalid_event_snapshot');
        if (!$matches) return array('state' => 'other_event', 'provider' => $claims[0]['provider'], 'evidence' => $claims);
        return array('state' => count($matches) === count($claims) ? 'eligible' : 'unresolved', 'provider' => $claims[0]['provider'], 'evidence' => $claims, 'reason' => count($matches) === count($claims) ? '' : 'conflicting_event_snapshots');
    }
    if ($product_match) return array('state' => $product_other ? 'unresolved' : 'eligible', 'provider' => 'event_product', 'evidence' => $products, 'reason' => $product_other ? 'conflicting_product_relationships' : '');
    if ($product_other) return array('state' => 'other_event', 'provider' => 'event_product', 'evidence' => $products);
    if (method_exists($item, 'get_meta_data')) {
        foreach ($item->get_meta_data() as $meta) {
            $data = $meta->get_data();
            if (preg_match('/event|occurrence|reservation|express_bar/i', (string) $data['key'])) {
                $value = $data['value'] ?? '';
                // An unrecognized event field pointing at a known *other* event does
                // not make every cancellation across the site unresolved.
                if (is_scalar($value) && ctype_digit((string) $value) && absint($value) > 0
                    && !in_array(absint($value), array($context['event_plan_id'], $context['tec_event_id']), true)
                    && in_array(get_post_type(absint($value)), array('vms_event_plan', 'tribe_events'), true)) continue;
                $hint = true;
            }
        }
    }
    return array('state' => $hint ? 'unresolved' : 'unrelated', 'provider' => 'unresolved', 'evidence' => array(), 'reason' => $hint ? 'unsupported_event_relationship' : '');
}

function bvmgr_cancel_purchase_line($order, $item, int $item_id, array $identity): array
{
    $item_type = method_exists($item, 'get_type') ? $item->get_type() : 'line_item';
    $product_id = method_exists($item, 'get_product_id') ? (int) $item->get_product_id() : 0;
    $quantity = method_exists($item, 'get_quantity') ? (float) $item->get_quantity() : 1.0;
    $total = (float) $item->get_total();
    $taxes = (array) ($item->get_taxes()['total'] ?? array());
    $previous = (float) $order->get_total_refunded_for_item($item_id, $item_type);
    $remaining_taxes = array();
    foreach ($taxes as $tax_id => $tax) {
        $refunded = (float) $order->get_tax_refunded_for_item($item_id, $tax_id, $item_type);
        $previous += $refunded;
        $remaining_taxes[$tax_id] = bvmgr_cancel_money(max(0, (float) $tax - $refunded));
    }
    $original = bvmgr_cancel_money($total + array_sum($taxes));
    $remaining_total = bvmgr_cancel_money(max(0, $total - (float) $order->get_total_refunded_for_item($item_id, $item_type)));
    $remaining = bvmgr_cancel_money($remaining_total + array_sum($remaining_taxes));
    $state = $identity['state'];
    if ($state === 'eligible' && $remaining === 0.0) $state = 'zero_balance';
    return array(
        'key' => $order->get_id() . ':' . $item_id, 'order_id' => $order->get_id(), 'item_id' => $item_id,
        'provider' => $identity['provider'], 'relationship' => $identity['evidence'], 'product_id' => $product_id, 'item_type' => $item_type,
        'product_role' => bvmgr_cancellation_refund_product_role($product_id), 'name' => $item->get_name(),
        'qty' => $quantity, 'refundable_qty' => max(0, $quantity - abs((float) $order->get_qty_refunded_for_item($item_id, $item_type))),
        'original_amount' => $original, 'previously_refunded' => bvmgr_cancel_money($previous), 'remaining' => $remaining,
        'refund_total' => $remaining_total, 'refund_tax' => $remaining_taxes, 'state' => $state,
        'proposed' => $state === 'eligible' ? $remaining : 0.0, 'reason' => $identity['reason'] ?? '',
        // Compatibility projection for the existing candidate/credit readers.
        'line_subtotal' => $total, 'line_tax_total' => array_sum($taxes), 'line_total' => $original, 'taxes' => $taxes, 'match_source' => $identity['provider'], 'qty_refunded' => abs((float) $order->get_qty_refunded_for_item($item_id, $item_type)),
    );
}

/** Pure read. Exhaustion, provider failures and unknown ownership remain visible. */
function bvmgr_cancel_purchase_discover(int $event_plan_id): array
{
    $scope = array('version' => 1, 'event_title' => get_the_title($event_plan_id), 'event_plan_id' => $event_plan_id, 'tec_event_id' => absint(get_post_meta($event_plan_id, '_vms_tec_event_id', true)),
        'occurrence' => (string) get_post_meta($event_plan_id, '_vms_event_date', true), 'candidates' => array(), 'warnings' => array(), 'coverage_complete' => false, 'orders_scanned' => 0);
    if (!function_exists('wc_get_orders')) { $scope['warnings'][] = 'woocommerce_unavailable'; return $scope; }
    try {
        $providers = bvmgr_cancel_purchase_providers();
        $products = bvmgr_cancellation_get_event_refundable_product_ids($event_plan_id, $scope['tec_event_id']);
        $context = $scope + array('product_lookup' => array_fill_keys($products, true));
        $max_pages = max(1, (int) apply_filters('vms_cancellation_purchase_max_pages', 100, $event_plan_id));
        $seen = array();
        $initial_total = null;
        for ($page = 1; $page <= $max_pages; $page++) {
            $batch = wc_get_orders(array('type' => 'shop_order', 'limit' => 100, 'page' => $page, 'paginate' => true, 'orderby' => 'ID', 'order' => 'ASC', 'status' => array_keys(wc_get_order_statuses())));
            if (!is_object($batch) || !isset($batch->orders, $batch->total, $batch->max_num_pages)) throw new RuntimeException('Order coverage unavailable');
            if ($initial_total === null) $initial_total = (int) $batch->total;
            if ($initial_total !== (int) $batch->total) throw new RuntimeException('Order population changed during discovery');
            foreach ($batch->orders as $order) {
                $id = (int) $order->get_id();
                if (isset($seen[$id])) throw new RuntimeException('Duplicate order while scanning');
                $seen[$id] = true;
                $scope['orders_scanned']++;
                $lines = array();
                foreach ($order->get_items(array('line_item', 'fee', 'shipping')) as $item_id => $item) {
                    try { $identity = bvmgr_cancel_purchase_identity($item, $context, $providers); }
                    catch (Throwable $e) {
                        $scope['warnings'][] = 'provider_failure: ' . sanitize_text_field($e->getMessage());
                        $identity = array('state' => 'unresolved', 'provider' => 'provider_failure', 'evidence' => array(), 'reason' => 'provider_failure');
                    }
                    if ($identity['state'] === 'unrelated' || $identity['state'] === 'other_event') continue;
                    $lines[] = bvmgr_cancel_purchase_line($order, $item, (int) $item_id, $identity);
                }
                if (!$lines) continue;
                $paid = in_array($order->get_status(), wc_get_is_paid_statuses(), true) || $order->get_status() === 'refunded';
                $reason = '';
                if (!$paid) $reason = 'payment_status_requires_review';
                if (function_exists('bvmgr_event_credit_find_existing') && bvmgr_event_credit_find_existing($event_plan_id, $id)) $reason = 'existing_event_credit_requires_review';
                $balance = bvmgr_cancel_money($order->get_remaining_refund_amount());
                if (bvmgr_cancel_money(array_sum(array_column($lines, 'remaining'))) > $balance) $reason = 'unallocated_refund_or_order_balance_conflict';
                $gateway = wc_get_payment_gateway_by_order($order);
                if (array_sum(array_column($lines, 'remaining')) > 0 && (!$gateway || !$gateway->supports('refunds') || (function_exists('wc_can_refund_order') && !wc_can_refund_order($order)))) $reason = $reason ?: 'order_not_auto_refundable';
                if ($reason) foreach ($lines as &$line) {
                    if ($line['remaining'] > 0) { $line['state'] = 'unresolved'; $line['reason'] = $reason; $line['proposed'] = 0.0; }
                }
                unset($line);
                $scope['candidates'][] = array('order_id' => $id, 'order_number' => $order->get_order_number(), 'customer' => trim($order->get_formatted_billing_full_name() . ' ' . $order->get_billing_email()),
                    'currency' => $order->get_currency(), 'order_status' => $order->get_status(), 'status' => $order->get_status(), 'total' => (float) $order->get_total(), 'total_refunded' => (float) $order->get_total_refunded(), 'payment_method' => method_exists($order, 'get_payment_method') ? $order->get_payment_method() : '', 'payment_method_title' => method_exists($order, 'get_payment_method_title') ? $order->get_payment_method_title() : '', 'remaining_total' => $balance, 'line_items' => $lines,
                    'estimated_refund_total' => bvmgr_cancel_money(array_sum(array_column($lines, 'proposed'))), 'auto_refund_eligible' => !$reason && !in_array('unresolved', array_column($lines, 'state'), true), 'manual_review_reason' => $reason ?: (in_array('unresolved', array_column($lines, 'state'), true) ? 'unresolved_components' : ''));
            }
            if ($page >= (int) $batch->max_num_pages) {
                $scope['coverage_complete'] = count($seen) === $initial_total;
                break;
            }
        }
        if (!$scope['coverage_complete']) $scope['warnings'][] = 'incomplete_order_scan';
        // Additional storage providers must explicitly return coverage and unresolved components.
        foreach ($providers as $name => $provider) {
            if (!isset($provider['discover'])) continue;
            $extra = call_user_func($provider['discover'], $context);
            if (!is_array($extra) || empty($extra['coverage_complete'])) $scope['warnings'][] = 'incomplete_provider: ' . $name;
            foreach (($extra['exceptions'] ?? array()) as $exception) $scope['warnings'][] = $name . ': ' . sanitize_text_field((string) $exception);
            foreach (($extra['components'] ?? array()) as $component) {
                if (!is_array($component) || empty($component['id']) || !isset($component['remaining'], $component['original_amount'], $component['currency'])) throw new RuntimeException('Invalid external purchase component: ' . $name);
                $external_id = 'provider:' . $name . ':' . sanitize_key((string) $component['id']);
                $remaining = bvmgr_cancel_money(max(0, (float) $component['remaining']));
                $line = array('key' => $external_id, 'order_id' => $external_id, 'item_id' => 0, 'provider' => $name, 'relationship' => array('event_plan_id' => $event_plan_id), 'product_id' => 0, 'product_role' => '', 'name' => (string) ($component['name'] ?? $external_id), 'qty' => (float) ($component['quantity'] ?? 1), 'refundable_qty' => 0.0, 'original_amount' => bvmgr_cancel_money($component['original_amount']), 'previously_refunded' => bvmgr_cancel_money($component['previously_refunded'] ?? 0), 'remaining' => $remaining, 'proposed' => 0.0, 'refund_total' => 0.0, 'refund_tax' => array(), 'state' => $remaining > 0 ? 'unresolved' : 'zero_balance', 'reason' => $remaining > 0 ? 'external_financial_authority_requires_review' : 'provider_reports_no_refundable_balance');
                $scope['candidates'][] = array('order_id' => $external_id, 'order_number' => $external_id, 'customer' => (string) ($component['customer'] ?? ''), 'currency' => (string) $component['currency'], 'line_items' => array($line), 'estimated_refund_total' => 0.0, 'auto_refund_eligible' => false, 'manual_review_reason' => $line['reason']);
            }

        }
    } catch (Throwable $e) {
        $scope['coverage_complete'] = false;
        $scope['warnings'][] = sanitize_text_field($e->getMessage());
    }
    $scope['candidate_order_count'] = count($scope['candidates']);
    $scope['auto_refund_eligible_count'] = count(array_filter($scope['candidates'], static function ($order) { return !empty($order['auto_refund_eligible']); }));
    $scope['manual_review_count'] = $scope['candidate_order_count'] - $scope['auto_refund_eligible_count'];
    $scope['scan_limit_reached'] = !$scope['coverage_complete'];
    $scope['requires_operator_review'] = !$scope['coverage_complete'] || !empty($scope['warnings']);
    return $scope;
}

function bvmgr_cancel_scope_hash(array $scope): string
{
    return hash('sha256', wp_json_encode($scope));
}

/** Reasons are a per-job audit decision; the raw discovery snapshot stays immutable. */
function bvmgr_cancel_scope_exclusions(array $scope, array $exclusions): array
{
    foreach ($scope['candidates'] as &$order) {
        foreach ($order['line_items'] as &$line) {
            $exclusion = $exclusions[$line['key']] ?? array();
            if (!empty($exclusion['reason']) && !empty($exclusion['actor']) && !empty($exclusion['at_gmt']) && !empty($exclusion['job_id'])) {
                $line['state'] = 'excluded'; $line['reason'] = $exclusion['reason']; $line['exclusion'] = $exclusion; $line['proposed'] = 0.0;
            }
        }
        unset($line);
    }
    unset($order);
    return $scope;
}

function bvmgr_cancel_scope_blocked(array $scope): bool
{
    if (empty($scope['coverage_complete']) || !empty($scope['warnings'])) return true;
    foreach ($scope['candidates'] as $order) foreach ($order['line_items'] as $line) if ($line['state'] === 'unresolved') return true;
    return false;
}

function bvmgr_cancel_step_scope(array $summary): array
{
    foreach (($summary['steps'] ?? array()) as $step) if ($step['key'] === 'refund_discovery') return (array) ($step['data'] ?? array());
    return array();
}

/** Independent rescan joins by order/item, including newly appearing and missing lines. */
function bvmgr_cancel_reconcile(array $accepted, array $current, array $attempts = array()): array
{
    $report = array('version' => 1, 'event_plan_id' => $accepted['event_plan_id'] ?? 0, 'event_title' => $accepted['event_title'] ?? '', 'occurrence' => $accepted['occurrence'] ?? '', 'tec_event_id' => $accepted['tec_event_id'] ?? 0, 'status' => 'completed_successfully', 'orders' => array(), 'totals' => array(), 'counts' => array('success' => 0, 'partial' => 0, 'failure' => 0, 'exclusions' => 0), 'warnings' => $current['warnings'] ?? array());
    $now = array();
    foreach (($current['candidates'] ?? array()) as $order) foreach ($order['line_items'] as $line) $now[$line['key']] = $line;
    $approved_keys = array();
    foreach (($accepted['candidates'] ?? array()) as $order) foreach ($order['line_items'] as $line) $approved_keys[$line['key']] = true;
    foreach (($current['candidates'] ?? array()) as $order) {
        $extra = array();
        foreach ($order['line_items'] as $line) if (!isset($approved_keys[$line['key']])) {
            $report['warnings'][] = 'New affected component: ' . $line['key'] . ' ' . $line['name'];
            $line['proposed'] = 0.0; $line['state'] = 'unresolved'; $line['reason'] = 'not_in_accepted_preflight';
            $extra[] = $line;
        }
        if (!$extra) continue;
        $found = false;
        foreach ($accepted['candidates'] as &$candidate) if ($candidate['order_id'] === $order['order_id']) {
            $candidate['line_items'] = array_merge($candidate['line_items'], $extra); $found = true; break;
        }
        unset($candidate);
        if (!$found) { $order['line_items'] = $extra; $accepted['candidates'][] = $order; }
    }
    $known = array();
    foreach (($accepted['candidates'] ?? array()) as $order) {
        $result = $order;
        $result['line_items'] = array(); $result['expected'] = 0.0; $result['actual'] = 0.0; $result['excluded'] = 0.0; $result['residual'] = 0.0; $result['unresolved'] = 0;
        foreach ($order['line_items'] as $line) {
            $known[$line['key']] = true;
            $live = $now[$line['key']] ?? null;
            $line['actual'] = $live ? bvmgr_cancel_money(max(0, $live['previously_refunded'] - $line['previously_refunded'])) : 0.0;
            $line['expected_residual'] = bvmgr_cancel_money(max(0, $line['remaining'] - $line['proposed']));
            $line['residual'] = $live ? $live['remaining'] : $line['remaining'];
            $line['outcome'] = $line['state'] === 'excluded' ? 'excluded' : ($live && $live['state'] !== 'unresolved' && $line['residual'] === 0.0 ? 'refunded_or_zero' : 'unresolved');
            if ($line['state'] === 'eligible' && bvmgr_cancel_money($line['actual'] - $line['proposed']) !== 0.0) $line['outcome'] = 'unresolved';
            if ($line['outcome'] === 'unresolved') $result['unresolved']++;
            $result['expected'] += $line['proposed']; $result['actual'] += $line['actual'];
            if ($line['state'] === 'excluded') $result['excluded'] += $line['residual'];
            else $result['residual'] += $line['residual'];
            $result['line_items'][] = $line;
        }
        $attempt = $attempts[$order['order_id']] ?? array();
        $result['failure'] = $attempt['error'] ?? '';
        $result['status'] = $result['residual'] > 0 || $result['unresolved'] || $result['failure'] ? ($result['actual'] > 0 ? 'partial' : 'failure') : ($result['excluded'] > 0 ? 'exclusions' : 'success');
        $report['counts'][$result['status']]++;
        $currency = $order['currency'];
        if (!isset($report['totals'][$currency])) $report['totals'][$currency] = array('expected' => 0.0, 'actual' => 0.0, 'excluded' => 0.0, 'residual' => 0.0);
        foreach (array('expected', 'actual', 'excluded', 'residual') as $key) $report['totals'][$currency][$key] = bvmgr_cancel_money($report['totals'][$currency][$key] + $result[$key]);
        $report['orders'][] = $result;
    }
    foreach ($now as $key => $line) if (!isset($known[$key])) {
        $report['warnings'][] = 'New affected component: ' . $key . ' ' . $line['name'];
        $report['new_components'][] = $line;
    }
    if (empty($current['coverage_complete']) || empty($accepted['coverage_complete'])) $report['warnings'][] = 'Coverage incomplete';
    if ($report['warnings'] || $report['counts']['partial'] || $report['counts']['failure']) $report['status'] = 'attention_required';
    elseif ($report['counts']['exclusions']) $report['status'] = 'completed_with_exclusions';
    return $report;
}

/** Persists intent before Woo transport; ambiguous attempts are never blindly replayed. */
function bvmgr_cancel_execute_scope(int $event_plan_id, array $summary): array
{
    $accepted = $summary['purchase_preflight'] ?? array();
    $data = array('requires_operator_review' => true, 'refunds_created' => array(), 'failed_orders' => array());
    if (empty($accepted['accepted_by']) || empty($accepted['scope'])) return array('status' => 'blocked', 'message' => 'purchase_preflight_acceptance_required', 'data' => $data);
    $scope = $accepted['scope'];
    if (bvmgr_cancel_scope_blocked($scope)) return array('status' => 'blocked', 'message' => 'unresolved_purchase_scope', 'data' => $data);
    $attempts = array();
    $fresh = bvmgr_cancel_purchase_discover($event_plan_id);
    // Compare identities and amounts to the approved snapshot before first execution.
    $key = '_vms_cancel_purchase_attempt_' . md5('event:' . $event_plan_id);
    foreach ($scope['candidates'] as $candidate) {
        if (array_sum(array_column($candidate['line_items'], 'proposed')) <= 0) continue;
        $order = wc_get_order($candidate['order_id']);
        if (!$order) { $attempts[$candidate['order_id']] = array('error' => 'order_missing'); continue; }
        $prior = $order->get_meta($key, true);
        if ($prior) {
            $attempts[$candidate['order_id']] = $prior;
            if (($prior['status'] ?? '') !== 'succeeded') $attempts[$candidate['order_id']]['error'] = 'prior_attempt_requires_manual_reconciliation';
            continue;
        }
        $live_order = null;
        foreach ($fresh['candidates'] as $row) if ($row['order_id'] === $candidate['order_id']) $live_order = $row;
        $raw_candidate = null;
        foreach ($accepted['raw_scope']['candidates'] as $row) if ($row['order_id'] === $candidate['order_id']) $raw_candidate = $row;
        if (!$fresh['coverage_complete'] || $fresh['warnings'] || $live_order !== $raw_candidate) {
            $attempts[$candidate['order_id']] = array('error' => 'purchase_scope_changed_reopen_preflight'); continue;
        }
        $lines = array(); $amount = 0.0;
        foreach ($candidate['line_items'] as $line) {
            if ($line['state'] !== 'eligible' || $line['proposed'] <= 0) continue;
            $lines[$line['item_id']] = array('qty' => $line['refundable_qty'], 'refund_total' => $line['refund_total'], 'refund_tax' => $line['refund_tax']);
            $amount += $line['proposed'];
        }
        $amount = bvmgr_cancel_money($amount);
        if (!$lines || $amount <= 0) continue;
        $attempt = array('status' => 'initiated', 'job_id' => $summary['job_id'], 'run_id' => $summary['active_run']['run_id'] ?? '', 'at_gmt' => gmdate('Y-m-d H:i:s'), 'amount' => $amount);
        try {
            $order->update_meta_data($key, $attempt); $order->save_meta_data();
            $check = wc_get_order($order->get_id());
            if ($check->get_meta($key, true) !== $attempt) throw new RuntimeException('Refund intent persistence failed');
            $refund = wc_create_refund(array('order_id' => $order->get_id(), 'amount' => $amount, 'reason' => 'Backstage Venue Manager cancellation ' . $summary['job_id'], 'line_items' => $lines, 'refund_payment' => true, 'restock_items' => false));
            if (is_wp_error($refund)) throw new RuntimeException($refund->get_error_message());
            if (!is_object($refund) || !$refund->get_id()) throw new RuntimeException('Unknown Woo refund result');
            $attempt['status'] = 'succeeded'; $attempt['refund_id'] = $refund->get_id();
            $order->update_meta_data($key, $attempt); $order->save_meta_data();
            $data['refunds_created'][] = array('order_id' => $order->get_id(), 'refund_id' => $refund->get_id(), 'amount' => $amount);
        } catch (Throwable $e) {
            $attempt['status'] = 'attention_required'; $attempt['error'] = sanitize_text_field($e->getMessage());
            // Leave the durable initiated intent intact if saving the outcome is uncertain.
            $data['failed_orders'][] = array('order_id' => $order->get_id(), 'error' => $attempt['error']);
        }
        $attempts[$order->get_id()] = $attempt;
    }
    $data['reconciliation'] = bvmgr_cancel_reconcile($scope, bvmgr_cancel_purchase_discover($event_plan_id), $attempts);
    $data['attempts'] = $attempts;
    $data['requires_operator_review'] = $data['reconciliation']['status'] === 'attention_required';
    return array('status' => $data['requires_operator_review'] ? 'blocked' : 'done', 'message' => $data['reconciliation']['status'], 'data' => $data);
}

/** A proposal is planned, never a failed refund merely because money is still outstanding. */
function bvmgr_cancel_preflight_report(array $scope): array
{
    $report = bvmgr_cancel_reconcile($scope, $scope);
    $report['status'] = bvmgr_cancel_scope_blocked($scope) ? 'attention_required' : 'planned';
    $report['counts'] = array('planned' => 0, 'attention_required' => 0);
    foreach ($report['orders'] as &$order) {
        $order['status'] = 'planned';
        foreach ($order['line_items'] as &$line) {
            $line['outcome'] = $line['state'];
            if ($line['state'] === 'unresolved') $order['status'] = 'attention_required';
        }
        unset($line);
        $report['counts'][$order['status']]++;
    }
    unset($order);
    foreach ($report['totals'] as &$total) $total['expected_residual'] = bvmgr_cancel_money(max(0, $total['residual'] - $total['expected']));
    unset($total);
    return $report;
}

/** Called by the writer after processing; report GET only projects the saved result. */
function bvmgr_cancel_job_report(int $event_plan_id, array $summary): array
{
    foreach (($summary['steps'] ?? array()) as $step) {
        if ($step['key'] === 'refund_execution' && !empty($step['data']['reconciliation'])) return $step['data']['reconciliation'];
    }
    $scope = $summary['purchase_preflight']['scope'] ?? bvmgr_cancel_step_scope($summary);
    if (empty($scope['version'])) return array('status' => 'attention_required', 'warnings' => array('Historical purchase coverage is unverified.'), 'orders' => array(), 'totals' => array(), 'counts' => array());
    return bvmgr_cancel_preflight_report($scope);
}

/** Acceptance is an explicit POST operation; no transport or persistence on a read. */
function bvmgr_cancel_accept_preflight(int $event_plan_id, string $job_id, string $hash, array $reasons): array
{
    if (get_post_type($event_plan_id) !== 'vms_event_plan' || !current_user_can('edit_post', $event_plan_id) || !current_user_can('manage_woocommerce')) return array('ok' => false, 'error' => 'permission_denied');
    $key = '_vms_cancel_job_summary';
    $before = get_post_meta($event_plan_id, $key, true);
    if (!is_array($before) || ($before['job_id'] ?? '') !== $job_id || !empty($before['purchase_preflight']['accepted_by'])) return array('ok' => false, 'error' => 'job_changed_or_already_accepted');
    if (!in_array($before['policy'], bvmgr_cancellation_auto_refund_policies(), true)) return array('ok' => false, 'error' => 'automatic_refund_policy_required');
    $sales_stopped = false;
    foreach (($before['steps'] ?? array()) as $step) if ($step['key'] === 'provider_sales_stop' && $step['status'] === 'done') $sales_stopped = true;
    if (!$sales_stopped) return array('ok' => false, 'error' => 'resolve_sales_stop_before_accepting_refunds');
    $raw = bvmgr_cancel_purchase_discover($event_plan_id);
    if (!hash_equals(bvmgr_cancel_scope_hash($raw), $hash)) return array('ok' => false, 'error' => 'purchase_scope_changed_reopen_preflight');
    $exclusions = array();
    foreach ($raw['candidates'] as $order) foreach ($order['line_items'] as $line) {
        $reason = isset($reasons[$line['key']]) && is_scalar($reasons[$line['key']]) ? sanitize_textarea_field((string) $reasons[$line['key']]) : '';
        if ($reason !== '') $exclusions[$line['key']] = array('reason' => $reason, 'actor' => get_current_user_id(), 'at_gmt' => gmdate('Y-m-d H:i:s'), 'job_id' => $job_id, 'item_id' => $line['item_id'], 'order_id' => $line['order_id']);
    }
    $scope = bvmgr_cancel_scope_exclusions($raw, $exclusions);
    if (bvmgr_cancel_scope_blocked($scope)) return array('ok' => false, 'error' => 'unresolved_scope_requires_review_or_exclusion');
    $state_key = '_vms_cancel_job_state';
    $state = get_post_meta($event_plan_id, $state_key, true);
    if ($state === 'running' || !update_post_meta($event_plan_id, $state_key, 'running', $state)) return array('ok' => false, 'error' => 'job_running');
    $next = $before;
    $next['purchase_preflight'] = array('version' => 1, 'accepted_by' => get_current_user_id(), 'accepted_at_gmt' => gmdate('Y-m-d H:i:s'), 'raw_scope' => $raw, 'scope' => $scope, 'exclusions' => $exclusions, 'hash' => $hash);
    foreach ($next['steps'] as &$step) {
        if ($step['key'] === 'refund_discovery') { $step['data'] = $raw; $step['status'] = 'done'; }
        if ($step['key'] === 'refund_execution') { $step['status'] = 'pending'; $step['data'] = array(); }
    }
    unset($step);
    $saved = update_post_meta($event_plan_id, $key, $next, $before);
    update_post_meta($event_plan_id, $state_key, $saved ? 'queued' : $state, 'running');
    return $saved ? array('ok' => true) : array('ok' => false, 'error' => 'acceptance_persistence_failed');
}
