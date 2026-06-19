<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_ticket_sales_resolver_context_score')) {
    function vms_ticket_sales_resolver_context_score(array $context): int
    {
        $confidence = (string) ($context['resolution_confidence'] ?? '');

        if ($confidence === 'exact') {
            return 30;
        }
        if ($confidence === 'derived') {
            return 20;
        }
        if (vms_ticket_revenue_is_resolved_event_link($context)) {
            return 10;
        }
        if (!empty($context['is_ticket_related'])) {
            return 5;
        }

        return 0;
    }
}

if (!function_exists('vms_ticket_sales_resolver_attendee_ids_for_order_item')) {
    function vms_ticket_sales_resolver_attendee_ids_for_order_item(int $order_id, int $order_item_id, array &$cache): array
    {
        $cache_key = $order_id . ':' . $order_item_id;
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        if ($order_id <= 0 || $order_item_id <= 0) {
            $cache[$cache_key] = array();
            return $cache[$cache_key];
        }

        $attendee_ids = get_posts(array(
            'post_type' => 'tribe_wooticket',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'orderby' => 'ID',
            'order' => 'ASC',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_tribe_wooticket_order',
                    'value' => (string) $order_id,
                ),
                array(
                    'key' => '_tribe_wooticket_order_item',
                    'value' => (string) $order_item_id,
                ),
            ),
        ));

        $cache[$cache_key] = array_values(array_unique(array_filter(array_map('absint', (array) $attendee_ids))));
        return $cache[$cache_key];
    }
}

if (!function_exists('vms_ticket_sales_resolver_attendee_context')) {
    function vms_ticket_sales_resolver_attendee_context(int $order_id, $item, array &$product_cache, array &$event_cache, array &$attendee_cache): array
    {
        $context = array(
            'attendee_ids' => array(),
            'product_id' => 0,
            'product_sku' => '',
            'item_kind' => 'ticket',
            'line_kind' => '',
            'ticket_post_id' => 0,
            'tec_event_id' => 0,
            'event_title' => '',
            'event_slug' => '',
            'event_date' => '',
            'event_start_local' => '',
            'event_start_gmt' => '',
            'event_permalink' => '',
            'event_plan_id' => 0,
            'event_plan_title' => '',
            'resolution_source' => '',
            'resolution_confidence' => '',
            'diagnostic_flags' => array(),
            'is_ticket_related' => false,
        );

        $order_item_id = is_object($item) && method_exists($item, 'get_id') ? absint($item->get_id()) : 0;
        if ($order_id <= 0 || $order_item_id <= 0) {
            return $context;
        }

        $attendee_ids = vms_ticket_sales_resolver_attendee_ids_for_order_item($order_id, $order_item_id, $attendee_cache);
        if (empty($attendee_ids)) {
            return $context;
        }

        $context['attendee_ids'] = $attendee_ids;
        $context['is_ticket_related'] = true;

        $event_ids = array();
        $ticket_post_ids = array();
        foreach ($attendee_ids as $attendee_id) {
            $event_id = absint(get_post_meta($attendee_id, '_tribe_wooticket_event', true));
            if ($event_id > 0) {
                $event_ids[] = $event_id;
            }

            $ticket_post_id = absint(get_post_meta($attendee_id, '_tribe_wooticket_product', true));
            if ($ticket_post_id > 0) {
                $ticket_post_ids[] = $ticket_post_id;
            }
        }

        $event_ids = array_values(array_unique(array_filter(array_map('absint', $event_ids))));
        $ticket_post_ids = array_values(array_unique(array_filter(array_map('absint', $ticket_post_ids))));

        if (count($event_ids) > 1) {
            $context['diagnostic_flags'][] = 'attendee_event_mismatch';
        }
        if (count($ticket_post_ids) > 1) {
            $context['diagnostic_flags'][] = 'attendee_ticket_post_mismatch';
        }

        if (!empty($ticket_post_ids)) {
            $ticket_context = vms_ticket_revenue_product_context((int) $ticket_post_ids[0], $product_cache, $event_cache);
            $context = array_merge($context, $ticket_context);
            $context['ticket_post_id'] = (int) ($ticket_context['ticket_post_id'] ?? $ticket_post_ids[0]);
        }

        if (!empty($event_ids)) {
            $context = array_merge($context, vms_ticket_revenue_event_payload_for_tec_event((int) $event_ids[0], $event_cache));
            $context['tec_event_id'] = (int) $event_ids[0];
        }

        return $context;
    }
}

if (!function_exists('vms_ticket_sales_resolver_confidence_for_source')) {
    function vms_ticket_sales_resolver_confidence_for_source(string $source): string
    {
        switch (sanitize_key($source)) {
            case 'attendee_meta':
            case 'order_item_meta':
            case 'ticket_post_event_link':
                return 'exact';

            case 'event_plan_link':
            case 'order_item_plan':
            case 'order_item_snapshot':
                return 'derived';

            case 'unresolved':
                return 'unresolved';
        }

        return '';
    }
}

if (!function_exists('vms_ticket_sales_resolver_diagnostic_message')) {
    function vms_ticket_sales_resolver_diagnostic_message(array $resolved): string
    {
        $flags = array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($resolved['diagnostic_flags'] ?? array())))));
        $source = sanitize_key((string) ($resolved['resolution_source'] ?? ''));
        $confidence = (string) ($resolved['resolution_confidence'] ?? '');

        if ($confidence === 'unresolved') {
            $snapshot = is_array($resolved['raw_linkage_snapshot'] ?? null) ? $resolved['raw_linkage_snapshot'] : array();
            return vms_ticket_revenue_unresolved_reason($resolved, (array) ($snapshot['candidate_product_ids'] ?? array()));
        }

        if (in_array('attendee_event_mismatch', $flags, true)) {
            return 'Attendee records on this order line reference multiple event IDs.';
        }
        if (in_array('attendee_ticket_post_mismatch', $flags, true)) {
            return 'Attendee records on this order line reference multiple ticket products.';
        }
        if ($confidence === 'derived' && $source === 'order_item_snapshot') {
            return 'Event context was derived from order-item snapshot values.';
        }
        if ($confidence === 'derived' && in_array($source, array('event_plan_link', 'order_item_plan'), true)) {
            return 'Event context was derived from the linked Event Plan.';
        }

        return '';
    }
}

if (!function_exists('vms_ticket_sales_resolver_order_dates')) {
    function vms_ticket_sales_resolver_order_dates($order): array
    {
        $created = is_object($order) && method_exists($order, 'get_date_created')
            ? $order->get_date_created()
            : null;
        $timestamp = $created ? (int) $created->getTimestamp() : 0;

        return array(
            'order_date_gmt' => $timestamp > 0 ? wp_date('Y-m-d H:i:s', $timestamp, vms_ticket_sales_resolver_utc_timezone()) : '',
            'order_date_local' => $timestamp > 0 ? wp_date('Y-m-d H:i:s', $timestamp, wp_timezone()) : '',
        );
    }
}

if (!function_exists('vms_ticket_sales_resolver_resolve_line_context')) {
    function vms_ticket_sales_resolver_resolve_line_context($order, $item, array &$product_cache, array &$event_cache, array &$attendee_cache): array
    {
        $candidate_product_ids = vms_ticket_revenue_order_item_candidate_product_ids($item);
        $item_tec_event_id = absint(vms_ticket_revenue_get_item_meta_first($item, array('_vms_tec_event_post_id')));
        $item_plan_id = absint(vms_ticket_revenue_get_item_meta_first($item, array('_vms_event_plan_id')));
        $snapshot_title = vms_ticket_revenue_get_item_meta_first($item, array('_vms_event_title_snapshot', 'Event'));
        $snapshot_date = vms_ticket_revenue_normalize_ymd(vms_ticket_revenue_get_item_meta_first($item, array('_vms_event_date_snapshot', 'Event Date')));

        $resolved = array(
            'product_id' => !empty($candidate_product_ids) ? (int) $candidate_product_ids[0] : 0,
            'product_sku' => '',
            'item_kind' => 'ticket',
            'line_kind' => '',
            'ticket_post_id' => 0,
            'tec_event_id' => 0,
            'event_title' => '',
            'event_slug' => '',
            'event_date' => '',
            'event_start_local' => '',
            'event_start_gmt' => '',
            'event_permalink' => '',
            'event_plan_id' => 0,
            'event_plan_title' => '',
            'resolution_source' => '',
            'resolution_confidence' => '',
            'attendee_ids' => array(),
            'diagnostic_flags' => array(),
            'diagnostic_message' => '',
            'is_ticket_related' => false,
            'raw_linkage_snapshot' => array(
                'candidate_product_ids' => $candidate_product_ids,
                'item_tec_event_id' => $item_tec_event_id,
                'item_event_plan_id' => $item_plan_id,
                'event_title_snapshot' => $snapshot_title,
                'event_date_snapshot' => $snapshot_date,
            ),
        );

        $best_product_context = array();
        $best_product_score = 0;
        foreach ($candidate_product_ids as $candidate_product_id) {
            $candidate_context = vms_ticket_revenue_product_context($candidate_product_id, $product_cache, $event_cache);
            if ($resolved['product_id'] <= 0 && !empty($candidate_context['product_id'])) {
                $resolved['product_id'] = (int) $candidate_context['product_id'];
            }
            if ($resolved['product_sku'] === '' && !empty($candidate_context['product_sku'])) {
                $resolved['product_sku'] = (string) $candidate_context['product_sku'];
            }
            if ($resolved['line_kind'] === '' && !empty($candidate_context['line_kind'])) {
                $resolved['line_kind'] = (string) $candidate_context['line_kind'];
            }
            if ($resolved['item_kind'] === 'ticket' && !empty($candidate_context['item_kind'])) {
                $resolved['item_kind'] = (string) $candidate_context['item_kind'];
            }
            if ($resolved['ticket_post_id'] <= 0 && !empty($candidate_context['ticket_post_id'])) {
                $resolved['ticket_post_id'] = (int) $candidate_context['ticket_post_id'];
            }
            if (!empty($candidate_context['is_ticket_related'])) {
                $resolved['is_ticket_related'] = true;
            }

            $score = vms_ticket_sales_resolver_context_score($candidate_context);
            if ($score > $best_product_score) {
                $best_product_context = $candidate_context;
                $best_product_score = $score;
            }
        }

        $order_id = is_object($order) && method_exists($order, 'get_id') ? absint($order->get_id()) : 0;
        $attendee_context = vms_ticket_sales_resolver_attendee_context($order_id, $item, $product_cache, $event_cache, $attendee_cache);
        if (!empty($attendee_context['attendee_ids'])) {
            $resolved['attendee_ids'] = array_values(array_unique(array_filter(array_map('absint', (array) $attendee_context['attendee_ids']))));
            $resolved['raw_linkage_snapshot']['attendee_ids'] = $resolved['attendee_ids'];
            $resolved['diagnostic_flags'] = array_merge($resolved['diagnostic_flags'], (array) ($attendee_context['diagnostic_flags'] ?? array()));
            $resolved['is_ticket_related'] = true;

            if ($resolved['ticket_post_id'] <= 0 && !empty($attendee_context['ticket_post_id'])) {
                $resolved['ticket_post_id'] = (int) $attendee_context['ticket_post_id'];
            }
            if ($resolved['product_id'] <= 0 && !empty($attendee_context['product_id'])) {
                $resolved['product_id'] = (int) $attendee_context['product_id'];
            }
            if ($resolved['product_sku'] === '' && !empty($attendee_context['product_sku'])) {
                $resolved['product_sku'] = (string) $attendee_context['product_sku'];
            }
            if ($resolved['line_kind'] === '' && !empty($attendee_context['line_kind'])) {
                $resolved['line_kind'] = (string) $attendee_context['line_kind'];
            }
            if ($resolved['item_kind'] === 'ticket' && !empty($attendee_context['item_kind'])) {
                $resolved['item_kind'] = (string) $attendee_context['item_kind'];
            }

            if (!empty($attendee_context['tec_event_id'])) {
                $resolved = array_merge($resolved, $attendee_context);
                $resolved['resolution_source'] = 'attendee_meta';
                $resolved['resolution_confidence'] = 'exact';
            } elseif ($best_product_score === 0 && vms_ticket_revenue_is_resolved_event_link($attendee_context)) {
                $best_product_context = $attendee_context;
                $best_product_score = vms_ticket_sales_resolver_context_score($attendee_context);
            }
        }

        if ($item_tec_event_id > 0 && $resolved['resolution_confidence'] !== 'exact') {
            $resolved = array_merge($resolved, vms_ticket_revenue_event_payload_for_tec_event($item_tec_event_id, $event_cache));
            $resolved['resolution_source'] = 'order_item_meta';
            $resolved['resolution_confidence'] = 'exact';
            $resolved['is_ticket_related'] = true;
        }

        if ($item_plan_id > 0) {
            $resolved['event_plan_id'] = $item_plan_id;
            if ($resolved['event_plan_title'] === '') {
                $resolved['event_plan_title'] = (string) get_the_title($item_plan_id);
            }

            $plan_date = vms_ticket_revenue_normalize_ymd((string) get_post_meta($item_plan_id, '_vms_event_date', true));
            if ($resolved['event_date'] === '' && $plan_date !== '') {
                $resolved['event_date'] = $plan_date;
            }
            if ($resolved['event_start_local'] === '') {
                $resolved['event_start_local'] = trim((string) get_post_meta($item_plan_id, '_vms_event_plan_start_datetime', true));
            }
            if ($resolved['event_start_gmt'] === '' && $resolved['event_start_local'] !== '') {
                $resolved['event_start_gmt'] = (string) get_gmt_from_date($resolved['event_start_local'], 'Y-m-d H:i:s');
            }

            if ($resolved['tec_event_id'] <= 0 && $resolved['resolution_confidence'] !== 'exact') {
                $plan_event_id = absint(get_post_meta($item_plan_id, vms_ticket_revenue_plan_tec_meta_key(), true));
                if ($plan_event_id > 0) {
                    $resolved = array_merge($resolved, vms_ticket_revenue_event_payload_for_tec_event($plan_event_id, $event_cache));
                }
            }

            if ($resolved['resolution_source'] === '') {
                $resolved['resolution_source'] = 'order_item_plan';
                $resolved['resolution_confidence'] = 'derived';
            }
            $resolved['is_ticket_related'] = true;
        }

        if ($snapshot_title !== '' || $snapshot_date !== '') {
            if ($resolved['event_title'] === '' && $snapshot_title !== '') {
                $resolved['event_title'] = $snapshot_title;
            }
            if ($resolved['event_date'] === '' && $snapshot_date !== '') {
                $resolved['event_date'] = $snapshot_date;
            }
            if ($resolved['resolution_source'] === '') {
                $resolved['resolution_source'] = 'order_item_snapshot';
                $resolved['resolution_confidence'] = 'derived';
            }
            $resolved['is_ticket_related'] = true;
        }

        if ($resolved['resolution_source'] === '' && $best_product_score > 0) {
            $resolved = array_merge($resolved, $best_product_context);
            if ($resolved['resolution_source'] === '') {
                $resolved['resolution_source'] = (string) ($best_product_context['resolution_source'] ?? '');
            }
            if ($resolved['resolution_confidence'] === '') {
                $resolved['resolution_confidence'] = (string) ($best_product_context['resolution_confidence'] ?? '');
            }
        }

        if ($resolved['product_id'] > 0 && ($resolved['product_sku'] === '' || $resolved['line_kind'] === '' || $resolved['ticket_post_id'] <= 0)) {
            $product_context = vms_ticket_revenue_product_context((int) $resolved['product_id'], $product_cache, $event_cache);
            if ($resolved['product_sku'] === '' && !empty($product_context['product_sku'])) {
                $resolved['product_sku'] = (string) $product_context['product_sku'];
            }
            if ($resolved['line_kind'] === '' && !empty($product_context['line_kind'])) {
                $resolved['line_kind'] = (string) $product_context['line_kind'];
            }
            if ($resolved['ticket_post_id'] <= 0 && !empty($product_context['ticket_post_id'])) {
                $resolved['ticket_post_id'] = (int) $product_context['ticket_post_id'];
            }
            if ($resolved['item_kind'] === 'ticket' && !empty($product_context['item_kind'])) {
                $resolved['item_kind'] = (string) $product_context['item_kind'];
            }
        }

        if ($resolved['line_kind'] === '' && $resolved['is_ticket_related']) {
            $resolved['line_kind'] = 'unknown_ticket_related';
            if ($resolved['item_kind'] === 'ticket') {
                $resolved['item_kind'] = 'unknown_ticket_related';
            }
        }

        if ($resolved['resolution_confidence'] === '') {
            $resolved['resolution_confidence'] = vms_ticket_sales_resolver_confidence_for_source((string) $resolved['resolution_source']);
        }
        if ($resolved['resolution_confidence'] === '' && vms_ticket_revenue_is_resolved_event_link($resolved)) {
            $resolved['resolution_confidence'] = 'derived';
        }

        if (
            !$resolved['is_ticket_related']
            && (
                vms_ticket_revenue_is_resolved_event_link($resolved)
                || !empty($resolved['attendee_ids'])
                || $item_tec_event_id > 0
                || $item_plan_id > 0
                || $snapshot_title !== ''
                || $snapshot_date !== ''
            )
        ) {
            $resolved['is_ticket_related'] = true;
        }

        if (!vms_ticket_revenue_is_resolved_event_link($resolved)) {
            $resolved['resolution_source'] = 'unresolved';
            $resolved['resolution_confidence'] = 'unresolved';

            if (empty($candidate_product_ids)) {
                $resolved['diagnostic_flags'][] = 'missing_candidate_product_id';
            }
            if (empty($resolved['attendee_ids'])) {
                $resolved['diagnostic_flags'][] = 'missing_attendee_linkage';
            }
            if (!empty($resolved['product_id'])) {
                $resolved['diagnostic_flags'][] = 'product_missing_event_link';
            }
            if ($item_tec_event_id <= 0 && $item_plan_id <= 0 && $snapshot_title === '' && $snapshot_date === '') {
                $resolved['diagnostic_flags'][] = 'missing_item_event_meta';
            }
        } elseif ($resolved['resolution_confidence'] === 'derived') {
            $resolved['diagnostic_flags'][] = 'derived_event_link';
        }

        if ($resolved['resolution_source'] === 'order_item_snapshot') {
            $resolved['diagnostic_flags'][] = 'snapshot_event_link';
        }

        $resolved['diagnostic_flags'] = array_values(array_unique(array_filter(array_map('sanitize_key', (array) $resolved['diagnostic_flags']))));
        $resolved['diagnostic_message'] = vms_ticket_sales_resolver_diagnostic_message($resolved);

        return $resolved;
    }
}

if (!function_exists('vms_ticket_sales_resolver_build_row')) {
    function vms_ticket_sales_resolver_build_row($order, $item, array $resolved, array $refund_map): array
    {
        $order_dates = vms_ticket_sales_resolver_order_dates($order);
        $status_slug = is_object($order) && method_exists($order, 'get_status')
            ? sanitize_key((string) $order->get_status())
            : '';
        $status_key = $status_slug !== '' ? 'wc-' . $status_slug : '';

        $gross_subtotal_cents = vms_ticket_revenue_money_to_cents($item->get_subtotal());
        $gross_tax_cents = vms_ticket_revenue_money_to_cents($item->get_subtotal_tax());
        $net_subtotal_cents = vms_ticket_revenue_money_to_cents($item->get_total());
        $line_tax_cents = vms_ticket_revenue_money_to_cents($item->get_total_tax());
        $discount_cents = max(0, $gross_subtotal_cents - $net_subtotal_cents);
        $discount_tax_cents = max(0, $gross_tax_cents - $line_tax_cents);
        $line_total_cents = $net_subtotal_cents + $line_tax_cents;

        $refund = isset($refund_map[$item->get_id()]) && is_array($refund_map[$item->get_id()])
            ? $refund_map[$item->get_id()]
            : array();
        $refunded_qty = (int) ($refund['qty'] ?? 0);
        $refunded_subtotal_cents = min($net_subtotal_cents, max(0, (int) ($refund['line_subtotal_cents'] ?? 0)));
        $refunded_tax_cents = min($line_tax_cents, max(0, (int) ($refund['line_tax_cents'] ?? 0)));
        $is_refunded = ($refunded_qty > 0 || $refunded_subtotal_cents > 0 || $refunded_tax_cents > 0);

        $line_kind = (string) ($resolved['line_kind'] ?? '');
        if ($line_kind === '' && !empty($resolved['is_ticket_related'])) {
            $line_kind = 'unknown_ticket_related';
        }

        $diagnostic_message = trim((string) ($resolved['diagnostic_message'] ?? ''));
        if ($diagnostic_message === '') {
            $diagnostic_message = vms_ticket_sales_resolver_diagnostic_message($resolved);
        }

        return array(
            'order_id' => is_object($order) && method_exists($order, 'get_id') ? (int) $order->get_id() : 0,
            'order_number' => is_object($order) && method_exists($order, 'get_order_number') ? (string) $order->get_order_number() : '',
            'order_date_gmt' => (string) ($order_dates['order_date_gmt'] ?? ''),
            'order_date_local' => (string) ($order_dates['order_date_local'] ?? ''),
            'order_status' => $status_key,
            'order_status_slug' => $status_slug,
            'customer_name' => is_object($order) && method_exists($order, 'get_formatted_billing_full_name')
                ? trim((string) $order->get_formatted_billing_full_name())
                : '',
            'customer_email' => is_object($order) && method_exists($order, 'get_billing_email')
                ? (string) $order->get_billing_email()
                : '',
            'order_item_id' => (int) $item->get_id(),
            'product_id' => (int) ($resolved['product_id'] ?? 0),
            'product_name' => (string) $item->get_name(),
            'product_sku' => (string) ($resolved['product_sku'] ?? ''),
            'line_kind' => $line_kind,
            'qty' => (int) $item->get_quantity(),
            'refunded_qty' => $refunded_qty,
            'event_id' => (int) ($resolved['tec_event_id'] ?? 0),
            'tec_event_id' => (int) ($resolved['tec_event_id'] ?? 0),
            'event_plan_id' => (int) ($resolved['event_plan_id'] ?? 0),
            'event_title' => (string) ($resolved['event_title'] ?? ''),
            'event_slug' => (string) ($resolved['event_slug'] ?? ''),
            'event_date' => (string) ($resolved['event_date'] ?? ''),
            'event_plan_title' => (string) ($resolved['event_plan_title'] ?? ''),
            'event_start_local' => (string) ($resolved['event_start_local'] ?? ''),
            'event_start_gmt' => (string) ($resolved['event_start_gmt'] ?? ''),
            'event_permalink' => (string) ($resolved['event_permalink'] ?? ''),
            'ticket_post_id' => (int) (($resolved['ticket_post_id'] ?? 0) ?: ($resolved['product_id'] ?? 0)),
            'attendee_ids' => array_values(array_unique(array_filter(array_map('absint', (array) ($resolved['attendee_ids'] ?? array()))))),
            'attendee_count' => count((array) ($resolved['attendee_ids'] ?? array())),
            'currency' => is_object($order) && method_exists($order, 'get_currency')
                ? (string) $order->get_currency()
                : '',
            'line_subtotal' => vms_ticket_revenue_cents_to_decimal($gross_subtotal_cents),
            'line_subtotal_cents' => $gross_subtotal_cents,
            'line_net_subtotal' => vms_ticket_revenue_cents_to_decimal($net_subtotal_cents),
            'line_net_subtotal_cents' => $net_subtotal_cents,
            'line_discount_total' => vms_ticket_revenue_cents_to_decimal($discount_cents),
            'line_discount_total_cents' => $discount_cents,
            'line_discount_tax_total_cents' => $discount_tax_cents,
            'line_tax_total' => vms_ticket_revenue_cents_to_decimal($line_tax_cents),
            'line_tax_total_cents' => $line_tax_cents,
            'line_total' => vms_ticket_revenue_cents_to_decimal($line_total_cents),
            'line_total_cents' => $line_total_cents,
            'line_refunded_total' => vms_ticket_revenue_cents_to_decimal($refunded_subtotal_cents),
            'line_refunded_total_cents' => $refunded_subtotal_cents,
            'line_refunded_tax_total' => vms_ticket_revenue_cents_to_decimal($refunded_tax_cents),
            'line_refunded_tax_total_cents' => $refunded_tax_cents,
            'is_refunded' => $is_refunded,
            'payment_method' => is_object($order) && method_exists($order, 'get_payment_method')
                ? (string) $order->get_payment_method()
                : '',
            'payment_method_title' => is_object($order) && method_exists($order, 'get_payment_method_title')
                ? (string) $order->get_payment_method_title()
                : '',
            'source_system' => 'woocommerce',
            'resolution_source' => (string) ($resolved['resolution_source'] ?? ''),
            'resolution_confidence' => (string) ($resolved['resolution_confidence'] ?? ''),
            'diagnostic_flags' => array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($resolved['diagnostic_flags'] ?? array()))))),
            'diagnostic_message' => $diagnostic_message,
            'notes' => $diagnostic_message,
            'raw_linkage_snapshot' => is_array($resolved['raw_linkage_snapshot'] ?? null) ? $resolved['raw_linkage_snapshot'] : array(),
        );
    }
}

if (!function_exists('vms_ticket_sales_resolver_row_matches')) {
    function vms_ticket_sales_resolver_row_matches(array $row, array $args): bool
    {
        if (!empty($args['order_ids']) && !in_array((int) ($row['order_id'] ?? 0), (array) $args['order_ids'], true)) {
            return false;
        }
        if (!empty($args['event_ids']) && !in_array((int) ($row['event_id'] ?? 0), (array) $args['event_ids'], true)) {
            return false;
        }
        if (!empty($args['event_plan_ids']) && !in_array((int) ($row['event_plan_id'] ?? 0), (array) $args['event_plan_ids'], true)) {
            return false;
        }
        if (!empty($args['product_ids']) && !in_array((int) ($row['product_id'] ?? 0), (array) $args['product_ids'], true)) {
            return false;
        }
        if (!empty($args['customer_email']) && strtolower((string) ($row['customer_email'] ?? '')) !== strtolower((string) $args['customer_email'])) {
            return false;
        }
        if (!empty($args['order_statuses']) && !in_array(sanitize_key((string) ($row['order_status_slug'] ?? '')), (array) $args['order_statuses'], true)) {
            return false;
        }

        $order_date = substr((string) ($row['order_date_local'] ?? ''), 0, 10);
        if (!empty($args['date_from']) && ($order_date === '' || $order_date < $args['date_from'])) {
            return false;
        }
        if (!empty($args['date_to']) && ($order_date === '' || $order_date > $args['date_to'])) {
            return false;
        }

        if (empty($args['include_unresolved']) && (string) ($row['resolution_confidence'] ?? '') === 'unresolved') {
            return false;
        }
        if (empty($args['include_refunded_lines']) && !empty($row['is_refunded'])) {
            return false;
        }

        return true;
    }
}

if (!function_exists('vms_ticket_sales_resolver_sort_rows')) {
    function vms_ticket_sales_resolver_sort_rows(array &$rows): void
    {
        usort($rows, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($a['order_date_local'] ?? ''), (string) ($b['order_date_local'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            $cmp = strcmp((string) ($a['event_start_local'] ?? ''), (string) ($b['event_start_local'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            $cmp = ((int) ($a['order_id'] ?? 0)) <=> ((int) ($b['order_id'] ?? 0));
            if ($cmp !== 0) {
                return $cmp;
            }

            return ((int) ($a['order_item_id'] ?? 0)) <=> ((int) ($b['order_item_id'] ?? 0));
        });
    }
}

if (!function_exists('vms_ticket_sales_resolver_get_result')) {
    function vms_ticket_sales_resolver_get_result(array $args = array()): array
    {
        $args = vms_ticket_sales_resolver_normalize_args($args);

        $result = array(
            'args' => $args,
            'rows' => array(),
            'warnings' => array(),
            'counts' => array(
                'orders_scanned' => 0,
                'line_items_scanned' => 0,
                'ticket_related_lines' => 0,
                'rows_matched' => 0,
                'rows_returned' => 0,
                'line_items_skipped_non_ticket' => 0,
                'line_items_skipped_filtered' => 0,
                'line_items_unresolved' => 0,
            ),
        );

        if (!function_exists('wc_get_orders') || !class_exists('WooCommerce')) {
            $result['warnings'][] = __('WooCommerce is not active; ticket sales resolver cannot run.', 'vms');
            return $result;
        }

        $order_ids = vms_ticket_sales_resolver_collect_order_ids($args);
        $result['counts']['orders_scanned'] = count($order_ids);
        if (empty($order_ids)) {
            return $result;
        }

        $product_cache = array();
        $event_cache = array();
        $attendee_cache = array();
        $rows = array();

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }

            $refund_map = vms_ticket_revenue_order_refund_map($order);
            foreach ((array) $order->get_items('line_item') as $item) {
                if (!$item) {
                    continue;
                }

                $result['counts']['line_items_scanned']++;

                $resolved = vms_ticket_sales_resolver_resolve_line_context($order, $item, $product_cache, $event_cache, $attendee_cache);
                if (empty($resolved['is_ticket_related'])) {
                    $result['counts']['line_items_skipped_non_ticket']++;
                    continue;
                }

                $result['counts']['ticket_related_lines']++;
                $row = vms_ticket_sales_resolver_build_row($order, $item, $resolved, $refund_map);
                if (!vms_ticket_sales_resolver_row_matches($row, $args)) {
                    $result['counts']['line_items_skipped_filtered']++;
                    continue;
                }

                if ((string) ($row['resolution_confidence'] ?? '') === 'unresolved') {
                    $result['counts']['line_items_unresolved']++;
                }

                $rows[] = $row;
            }
        }

        vms_ticket_sales_resolver_sort_rows($rows);

        $result['counts']['rows_matched'] = count($rows);
        $offset = max(0, (int) ($args['offset'] ?? 0));
        $limit = max(0, (int) ($args['limit'] ?? 0));
        if ($offset > 0 || $limit > 0) {
            $rows = array_slice($rows, $offset, $limit > 0 ? $limit : null);
        }

        $result['rows'] = array_values($rows);
        $result['counts']['rows_returned'] = count($result['rows']);

        if ($result['counts']['line_items_unresolved'] > 0) {
            $result['warnings'][] = sprintf(
                __('%d ticket-related Woo line item(s) were returned with unresolved event context. Review the diagnostic fields before relying on the final totals.', 'vms'),
                (int) $result['counts']['line_items_unresolved']
            );
        }

        return $result;
    }
}

if (!class_exists('VMS_Ticket_Revenue_Service')) {
    final class VMS_Ticket_Revenue_Service
    {
        public static function get_sales_result(array $args = array()): array
        {
            return vms_ticket_sales_resolver_get_result($args);
        }

        public static function get_sales_rows(array $args = array()): array
        {
            $result = self::get_sales_result($args);
            return (array) ($result['rows'] ?? array());
        }
    }
}

if (!function_exists('vms_get_ticket_sales_rows')) {
    /**
     * Developer example:
     * $rows = vms_get_ticket_sales_rows(array('date_from' => '2026-03-01', 'date_to' => '2026-03-31'));
     */
    function vms_get_ticket_sales_rows(array $args = array()): array
    {
        return VMS_Ticket_Revenue_Service::get_sales_rows($args);
    }
}
