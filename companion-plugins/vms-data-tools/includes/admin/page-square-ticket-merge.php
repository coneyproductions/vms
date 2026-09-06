<?php
if (!defined('ABSPATH')) {
    exit;
}

function vms_dt_register_square_ticket_merge_page(): void
{
    $parent_slug = 'vms-data-tools';
    $cap = function_exists('vms_dt_manage_capability') ? vms_dt_manage_capability() : 'manage_options';

    add_submenu_page(
        $parent_slug,
        __('Square + Ticket Merge', 'vms-data-tools'),
        __('Square + Ticket Merge', 'vms-data-tools'),
        $cap,
        vms_dt_get_menu_slug_square_ticket_merge(),
        'vms_dt_render_square_ticket_merge_page'
    );
}

function vms_dt_square_ticket_merge_request_args(string $method = 'get'): array
{
    $source = strtolower($method) === 'post' ? $_POST : $_GET;
    $base = function_exists('vms_dt_ticket_revenue_request_args')
        ? vms_dt_ticket_revenue_request_args($method)
        : array();

    $location_id = isset($source['square_location_id']) ? sanitize_text_field((string) wp_unslash($source['square_location_id'])) : '';
    $payout_from = isset($source['payout_from']) ? sanitize_text_field((string) wp_unslash($source['payout_from'])) : '';
    $payout_to = isset($source['payout_to']) ? sanitize_text_field((string) wp_unslash($source['payout_to'])) : '';
    $preview_limit = isset($source['preview_limit']) ? max(25, min(500, (int) $source['preview_limit'])) : 150;

    $sold_from = (string) ($base['sold_from'] ?? '');
    $sold_to = (string) ($base['sold_to'] ?? '');

    if ($payout_from === '' && $sold_from !== '') {
        $payout_from = $sold_from;
    }
    if ($payout_to === '' && $sold_to !== '') {
        $payout_to = $sold_to;
    }

    if (vms_dt_has_core_function('vms_ticket_revenue_normalize_ymd')) {
        $payout_from = vms_dt_call_core_function('vms_ticket_revenue_normalize_ymd', $payout_from);
        $payout_to = vms_dt_call_core_function('vms_ticket_revenue_normalize_ymd', $payout_to);
    }

    if (!vms_dt_has_core_function('vms_ticket_revenue_is_valid_ymd') || !vms_dt_call_core_function('vms_ticket_revenue_is_valid_ymd', (string) $payout_from)) {
        $payout_from = '';
    }
    if (!vms_dt_has_core_function('vms_ticket_revenue_is_valid_ymd') || !vms_dt_call_core_function('vms_ticket_revenue_is_valid_ymd', (string) $payout_to)) {
        $payout_to = '';
    }

    $base['square_location_id'] = $location_id;
    $base['payout_from'] = $payout_from;
    $base['payout_to'] = $payout_to;
    $base['preview_limit'] = $preview_limit;

    return $base;
}

function vms_dt_square_ticket_merge_location_options(): array
{
    $out = array();
    if (!function_exists('vms_square_effective_location_ids')) {
        return $out;
    }

    foreach ((array) vms_square_effective_location_ids() as $location_id) {
        $location_id = trim((string) $location_id);
        if ($location_id === '') {
            continue;
        }
        $out[$location_id] = $location_id;
    }

    return $out;
}

function vms_dt_square_ticket_merge_date_to_rfc3339(string $ymd, bool $end_of_day = false): string
{
    if (!vms_dt_has_core_function('vms_ticket_revenue_is_valid_ymd') || !vms_dt_call_core_function('vms_ticket_revenue_is_valid_ymd', $ymd)) {
        return '';
    }

    try {
        $tz = wp_timezone();
        $time = $end_of_day ? '23:59:59' : '00:00:00';
        $dt = new DateTimeImmutable($ymd . ' ' . $time, $tz);
        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    } catch (Throwable $e) {
        return '';
    }
}

function vms_dt_square_ticket_merge_localize_rfc3339(string $value): array
{
    $out = array(
        'date' => '',
        'datetime' => '',
    );

    $value = trim($value);
    if ($value === '') {
        return $out;
    }

    try {
        $dt = new DateTimeImmutable($value);
        $local = $dt->setTimezone(wp_timezone());
        $out['date'] = $local->format('Y-m-d');
        $out['datetime'] = $local->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return $out;
    }

    return $out;
}

function vms_dt_square_ticket_merge_order_candidate_payment_ids($order): array
{
    if (!is_object($order) || !($order instanceof WC_Order)) {
        return array();
    }

    $candidates = array();

    $maybe_add = static function ($value) use (&$candidates): void {
        if (is_array($value) || is_object($value)) {
            return;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return;
        }
        if (strlen($value) < 8 || strlen($value) > 128) {
            return;
        }
        if (preg_match('/\s/', $value)) {
            return;
        }
        $candidates[] = $value;
    };

    if (method_exists($order, 'get_transaction_id')) {
        $maybe_add($order->get_transaction_id());
    }

    foreach (array(
        '_transaction_id',
        'transaction_id',
        '_square_transaction_id',
        'square_transaction_id',
        '_square_payment_id',
        'square_payment_id',
        '_wc_square_payment_id',
        '_wc_square_credit_card_trans_id',
        '_wc_square_credit_card_charge_id',
        '_wc_square_customer_card_payment_id',
        '_wc_square_customer_payment_id',
    ) as $meta_key) {
        $maybe_add($order->get_meta($meta_key, true));
    }

    foreach ((array) $order->get_meta_data() as $meta) {
        if (!is_object($meta) || !method_exists($meta, 'get_data')) {
            continue;
        }
        $data = (array) $meta->get_data();
        $key = isset($data['key']) ? strtolower((string) $data['key']) : '';
        if ($key === '') {
            continue;
        }
        if (strpos($key, 'square') === false && strpos($key, 'transaction') === false && strpos($key, 'payment') === false) {
            continue;
        }
        $maybe_add($data['value'] ?? '');
    }

    $candidates = array_values(array_unique(array_filter(array_map('strval', $candidates))));
    return $candidates;
}

function vms_dt_square_ticket_merge_order_gateway_slug($order): string
{
    if (!is_object($order) || !($order instanceof WC_Order) || !method_exists($order, 'get_payment_method')) {
        return '';
    }
    return sanitize_key((string) $order->get_payment_method());
}

function vms_dt_square_ticket_merge_order_gateway_title($order): string
{
    if (!is_object($order) || !($order instanceof WC_Order) || !method_exists($order, 'get_payment_method_title')) {
        return '';
    }
    return (string) $order->get_payment_method_title();
}

function vms_dt_square_ticket_merge_order_looks_square($order, array $candidate_payment_ids = array()): bool
{
    $slug = vms_dt_square_ticket_merge_order_gateway_slug($order);
    $title = strtolower(vms_dt_square_ticket_merge_order_gateway_title($order));

    if (strpos($slug, 'square') !== false || strpos($title, 'square') !== false) {
        return true;
    }

    foreach ($candidate_payment_ids as $id) {
        if (strpos((string) $id, 'cnon:') === 0) {
            continue;
        }
        return true;
    }

    return false;
}

function vms_dt_square_ticket_merge_list_payouts(string $location_id, string $from_ymd, string $to_ymd): array
{
    $result = array(
        'payouts' => array(),
        'warnings' => array(),
    );

    if (!function_exists('vms_square_api_request')) {
        $result['warnings'][] = __('Square Sync API helper is unavailable.', 'vms-data-tools');
        return $result;
    }

    $begin_time = vms_dt_square_ticket_merge_date_to_rfc3339($from_ymd, false);
    $end_time = vms_dt_square_ticket_merge_date_to_rfc3339($to_ymd, true);
    if ($begin_time === '' || $end_time === '') {
        $result['warnings'][] = __('Square payout window is invalid.', 'vms-data-tools');
        return $result;
    }

    $cursor = '';
    $seen = array();

    do {
        $query = array(
            'location_id' => $location_id,
            'begin_time' => $begin_time,
            'end_time' => $end_time,
            'limit' => 100,
            'sort_order' => 'ASC',
        );
        if ($cursor !== '') {
            $query['cursor'] = $cursor;
        }

        $path = '/v2/payouts?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $payload = vms_square_api_request('GET', $path, null);
        $items = isset($payload['payouts']) && is_array($payload['payouts']) ? $payload['payouts'] : array();

        foreach ($items as $payout) {
            if (!is_array($payout)) {
                continue;
            }
            $payout_id = isset($payout['id']) ? (string) $payout['id'] : '';
            if ($payout_id === '' || isset($seen[$payout_id])) {
                continue;
            }
            $seen[$payout_id] = true;
            $result['payouts'][] = $payout;
        }

        $cursor = isset($payload['cursor']) ? (string) $payload['cursor'] : '';
    } while ($cursor !== '');

    return $result;
}

function vms_dt_square_ticket_merge_list_payout_entries(string $payout_id): array
{
    $entries = array();
    $cursor = '';

    do {
        $query = array(
            'limit' => 100,
            'sort_order' => 'ASC',
        );
        if ($cursor !== '') {
            $query['cursor'] = $cursor;
        }

        $path = '/v2/payouts/' . rawurlencode($payout_id) . '/payout-entries?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $payload = vms_square_api_request('GET', $path, null);
        $items = isset($payload['payout_entries']) && is_array($payload['payout_entries']) ? $payload['payout_entries'] : array();
        foreach ($items as $entry) {
            if (is_array($entry)) {
                $entries[] = $entry;
            }
        }
        $cursor = isset($payload['cursor']) ? (string) $payload['cursor'] : '';
    } while ($cursor !== '');

    return $entries;
}

function vms_dt_square_ticket_merge_normalize_payout_entry(array $entry, array $payout): array
{
    $payment_id = '';
    $refund_id = '';

    foreach ($entry as $key => $value) {
        if (!is_string($key) || !is_array($value)) {
            continue;
        }
        if (strpos($key, 'type_') !== 0 || substr($key, -8) !== '_details') {
            continue;
        }
        if ($payment_id === '' && !empty($value['payment_id'])) {
            $payment_id = (string) $value['payment_id'];
        }
        if ($refund_id === '' && !empty($value['refund_id'])) {
            $refund_id = (string) $value['refund_id'];
        }
    }

    $effective = vms_dt_square_ticket_merge_localize_rfc3339((string) ($entry['effective_at'] ?? ''));
    $created = vms_dt_square_ticket_merge_localize_rfc3339((string) ($payout['created_at'] ?? ''));

    return array(
        'entry_id' => (string) ($entry['id'] ?? ''),
        'payout_id' => (string) ($entry['payout_id'] ?? ($payout['id'] ?? '')),
        'payout_status' => (string) ($payout['status'] ?? ''),
        'payout_type' => (string) ($payout['type'] ?? ''),
        'location_id' => (string) ($payout['location_id'] ?? ''),
        'arrival_date' => (string) ($payout['arrival_date'] ?? ''),
        'payout_created_date' => (string) ($created['date'] ?? ''),
        'payout_created_datetime' => (string) ($created['datetime'] ?? ''),
        'payout_amount_cents' => (int) ($payout['amount_money']['amount'] ?? 0),
        'effective_date' => (string) ($effective['date'] ?? ''),
        'effective_datetime' => (string) ($effective['datetime'] ?? ''),
        'type' => strtoupper((string) ($entry['type'] ?? '')),
        'gross_cents' => (int) ($entry['gross_amount_money']['amount'] ?? 0),
        'fee_cents' => (int) ($entry['fee_amount_money']['amount'] ?? 0),
        'net_cents' => (int) ($entry['net_amount_money']['amount'] ?? 0),
        'payment_id' => trim($payment_id),
        'refund_id' => trim($refund_id),
    );
}

function vms_dt_square_ticket_merge_fetch_square_report(string $location_id, string $from_ymd, string $to_ymd): array
{
    $result = array(
        'payouts' => array(),
        'entries' => array(),
        'warnings' => array(),
    );

    if ($location_id === '') {
        $result['warnings'][] = __('Choose a Square location before previewing the merge.', 'vms-data-tools');
        return $result;
    }

    try {
        $payout_res = vms_dt_square_ticket_merge_list_payouts($location_id, $from_ymd, $to_ymd);
        $result['warnings'] = array_merge($result['warnings'], (array) ($payout_res['warnings'] ?? array()));
        foreach ((array) ($payout_res['payouts'] ?? array()) as $payout) {
            if (!is_array($payout) || empty($payout['id'])) {
                continue;
            }
            $payout_id = (string) $payout['id'];
            $result['payouts'][$payout_id] = $payout;
            $entries = vms_dt_square_ticket_merge_list_payout_entries($payout_id);
            foreach ($entries as $entry) {
                $normalized = vms_dt_square_ticket_merge_normalize_payout_entry($entry, $payout);
                if ($normalized['entry_id'] === '') {
                    continue;
                }
                $result['entries'][$normalized['entry_id']] = $normalized;
            }
        }
    } catch (Throwable $e) {
        $result['warnings'][] = sprintf(
            __('Square payout lookup failed: %s', 'vms-data-tools'),
            $e->getMessage()
        );
    }

    return $result;
}

function vms_dt_square_ticket_merge_allocate_amount(int $amount, array $weights): array
{
    if (function_exists('vms_square_allocate_amount_by_weights')) {
        return (array) vms_square_allocate_amount_by_weights($amount, $weights);
    }

    $weights = array_filter(array_map('intval', $weights), static function ($value): bool {
        return $value > 0;
    });
    if (empty($weights)) {
        return array_fill_keys(array_keys($weights), 0);
    }

    $total = array_sum($weights);
    if ($total <= 0) {
        return array_fill_keys(array_keys($weights), 0);
    }

    $alloc = array();
    $remaining = $amount;
    $last_key = array_key_last($weights);
    foreach ($weights as $key => $weight) {
        if ($key === $last_key) {
            $alloc[$key] = $remaining;
            continue;
        }
        $share = (int) round(($amount * $weight) / $total);
        $alloc[$key] = $share;
        $remaining -= $share;
    }

    return $alloc;
}

function vms_dt_square_ticket_merge_build_report(array $args): array
{
    $report = array(
        'ticket' => array(),
        'square' => array(
            'payouts' => array(),
            'entries' => array(),
            'warnings' => array(),
        ),
        'warnings' => array(),
        'counts' => array(
            'ticket_orders' => 0,
            'ticket_orders_with_square_candidates' => 0,
            'ticket_orders_matched' => 0,
            'ticket_orders_unmatched' => 0,
            'square_payouts' => 0,
            'square_entries' => 0,
            'matched_square_entries' => 0,
            'unmatched_square_payment_entries' => 0,
            'unallocated_square_adjustment_entries' => 0,
        ),
        'orders' => array(),
        'sold_date_summary' => array(),
        'event_summary' => array(),
        'payout_summary' => array(),
        'rows' => array(),
        'unmatched_orders' => array(),
        'unmatched_square_entries' => array(),
        'unallocated_adjustment_entries' => array(),
    );

    if (!vms_dt_has_core_function('vms_ticket_revenue_build_report')) {
        $report['warnings'][] = __('Ticket revenue service is unavailable.', 'vms-data-tools');
        return $report;
    }

    $ticket_args = $args;
    unset($ticket_args['square_location_id'], $ticket_args['payout_from'], $ticket_args['payout_to']);
    $ticket = vms_dt_call_core_function('vms_ticket_revenue_build_report', $ticket_args);
    $report['ticket'] = $ticket;
    $report['warnings'] = array_merge($report['warnings'], (array) ($ticket['warnings'] ?? array()));

    $orders = array();
    $rows_by_order = array();
    foreach ((array) ($ticket['rows'] ?? array()) as $row) {
        $order_id = (int) ($row['order_id'] ?? 0);
        if ($order_id <= 0) {
            continue;
        }
        if (!isset($orders[$order_id])) {
            $orders[$order_id] = array(
                'order_id' => $order_id,
                'order_number' => (string) ($row['order_number'] ?? $order_id),
                'sold_date' => (string) ($row['sold_date'] ?? ''),
                'sold_datetime' => (string) ($row['sold_datetime'] ?? ''),
                'gateway_slug' => '',
                'gateway_title' => (string) ($row['payment_method'] ?? ''),
                'looks_square' => false,
                'candidate_payment_ids' => array(),
                'ticket_line_count' => 0,
                'ticket_quantity' => 0,
                'ticket_net_sales_cents' => 0,
                'ticket_tax_cents' => 0,
                'ticket_cash_total_cents' => 0,
                'event_names' => array(),
                'matched_square_entry_ids' => array(),
                'matched_square_payment_ids' => array(),
                'matched_square_payout_ids' => array(),
                'matched_square_gross_cents' => 0,
                'matched_square_fee_cents' => 0,
                'matched_square_net_cents' => 0,
                'matched_square_charge_gross_cents' => 0,
                'matched_square_refund_gross_cents' => 0,
                'gross_delta_cents' => 0,
            );
        }

        $orders[$order_id]['ticket_line_count']++;
        $orders[$order_id]['ticket_quantity'] += (int) ($row['quantity'] ?? 0);
        $orders[$order_id]['ticket_net_sales_cents'] += (int) ($row['net_subtotal_cents'] ?? 0);
        $orders[$order_id]['ticket_tax_cents'] += (int) ($row['tax_cents'] ?? 0);
        $orders[$order_id]['ticket_cash_total_cents'] += (int) ($row['cash_total_cents'] ?? 0);
        $event_name = trim((string) (($row['event_plan_title'] ?? '') ?: ($row['event_title'] ?? '')));
        if ($event_name !== '') {
            $orders[$order_id]['event_names'][$event_name] = true;
        }
        $rows_by_order[$order_id][] = $row;
    }

    $report['counts']['ticket_orders'] = count($orders);

    foreach (array_keys($orders) as $order_id) {
        $order = wc_get_order($order_id);
        if (!$order || !($order instanceof WC_Order)) {
            continue;
        }
        $candidate_payment_ids = vms_dt_square_ticket_merge_order_candidate_payment_ids($order);
        $orders[$order_id]['gateway_slug'] = vms_dt_square_ticket_merge_order_gateway_slug($order);
        $orders[$order_id]['gateway_title'] = vms_dt_square_ticket_merge_order_gateway_title($order) ?: $orders[$order_id]['gateway_title'];
        $orders[$order_id]['candidate_payment_ids'] = $candidate_payment_ids;
        $orders[$order_id]['looks_square'] = vms_dt_square_ticket_merge_order_looks_square($order, $candidate_payment_ids);
        if (!empty($candidate_payment_ids)) {
            $report['counts']['ticket_orders_with_square_candidates']++;
        }
    }

    $square = vms_dt_square_ticket_merge_fetch_square_report(
        (string) ($args['square_location_id'] ?? ''),
        (string) ($args['payout_from'] ?? ''),
        (string) ($args['payout_to'] ?? '')
    );
    $report['square'] = $square;
    $report['warnings'] = array_merge($report['warnings'], (array) ($square['warnings'] ?? array()));
    $report['counts']['square_payouts'] = count((array) ($square['payouts'] ?? array()));
    $report['counts']['square_entries'] = count((array) ($square['entries'] ?? array()));

    $entries_by_payment_id = array();
    foreach ((array) ($square['entries'] ?? array()) as $entry) {
        $payment_id = (string) ($entry['payment_id'] ?? '');
        if ($payment_id === '') {
            continue;
        }
        if (!isset($entries_by_payment_id[$payment_id])) {
            $entries_by_payment_id[$payment_id] = array();
        }
        $entries_by_payment_id[$payment_id][] = $entry;
    }

    $matched_entry_ids = array();
    foreach ($orders as $order_id => $order_row) {
        $entry_map = array();
        foreach ((array) $order_row['candidate_payment_ids'] as $payment_id) {
            foreach ((array) ($entries_by_payment_id[$payment_id] ?? array()) as $entry) {
                $entry_id = (string) ($entry['entry_id'] ?? '');
                if ($entry_id === '') {
                    continue;
                }
                $entry_map[$entry_id] = $entry;
                $orders[$order_id]['matched_square_payment_ids'][$payment_id] = true;
            }
        }

        foreach ($entry_map as $entry_id => $entry) {
            $orders[$order_id]['matched_square_entry_ids'][$entry_id] = true;
            $orders[$order_id]['matched_square_payout_ids'][(string) ($entry['payout_id'] ?? '')] = true;
            $orders[$order_id]['matched_square_gross_cents'] += (int) ($entry['gross_cents'] ?? 0);
            $orders[$order_id]['matched_square_fee_cents'] += (int) ($entry['fee_cents'] ?? 0);
            $orders[$order_id]['matched_square_net_cents'] += (int) ($entry['net_cents'] ?? 0);

            $type = strtoupper((string) ($entry['type'] ?? ''));
            if ($type === 'CHARGE') {
                $orders[$order_id]['matched_square_charge_gross_cents'] += (int) ($entry['gross_cents'] ?? 0);
            } elseif ($type === 'REFUND') {
                $orders[$order_id]['matched_square_refund_gross_cents'] += (int) ($entry['gross_cents'] ?? 0);
            }
            $matched_entry_ids[$entry_id] = true;
        }

        $orders[$order_id]['matched_square_entry_ids'] = array_keys($orders[$order_id]['matched_square_entry_ids']);
        $orders[$order_id]['matched_square_payment_ids'] = array_keys($orders[$order_id]['matched_square_payment_ids']);
        $orders[$order_id]['matched_square_payout_ids'] = array_values(array_filter(array_keys($orders[$order_id]['matched_square_payout_ids'])));
        $orders[$order_id]['event_names'] = array_keys($orders[$order_id]['event_names']);
        $orders[$order_id]['gross_delta_cents'] = (int) $orders[$order_id]['matched_square_gross_cents'] - (int) $orders[$order_id]['ticket_cash_total_cents'];

        if (!empty($orders[$order_id]['matched_square_entry_ids'])) {
            $report['counts']['ticket_orders_matched']++;
        } else {
            $report['counts']['ticket_orders_unmatched']++;
        }
    }

    $report['counts']['matched_square_entries'] = count($matched_entry_ids);

    foreach ((array) ($square['entries'] ?? array()) as $entry) {
        $entry_id = (string) ($entry['entry_id'] ?? '');
        if ($entry_id === '') {
            continue;
        }
        if (isset($matched_entry_ids[$entry_id])) {
            continue;
        }
        if (!empty($entry['payment_id'])) {
            $report['counts']['unmatched_square_payment_entries']++;
            $report['unmatched_square_entries'][] = $entry;
        } else {
            $report['counts']['unallocated_square_adjustment_entries']++;
            $report['unallocated_adjustment_entries'][] = $entry;
        }
    }

    $rows = array();
    $event_summary = array();
    foreach ($rows_by_order as $order_id => $order_rows) {
        $weights = array();
        foreach ($order_rows as $row) {
            $weights[(int) ($row['line_item_id'] ?? 0)] = max(0, (int) ($row['cash_total_cents'] ?? 0));
        }

        $alloc_gross = vms_dt_square_ticket_merge_allocate_amount((int) ($orders[$order_id]['matched_square_gross_cents'] ?? 0), $weights);
        $alloc_fee = vms_dt_square_ticket_merge_allocate_amount((int) ($orders[$order_id]['matched_square_fee_cents'] ?? 0), $weights);
        $alloc_net = vms_dt_square_ticket_merge_allocate_amount((int) ($orders[$order_id]['matched_square_net_cents'] ?? 0), $weights);

        foreach ($order_rows as $row) {
            $line_item_id = (int) ($row['line_item_id'] ?? 0);
            $row['square_gross_cents'] = (int) ($alloc_gross[$line_item_id] ?? 0);
            $row['square_fee_cents'] = (int) ($alloc_fee[$line_item_id] ?? 0);
            $row['square_net_cents'] = (int) ($alloc_net[$line_item_id] ?? 0);
            $row['square_matched'] = !empty($orders[$order_id]['matched_square_entry_ids']);
            $rows[] = $row;

            $event_key = vms_dt_has_core_function('vms_ticket_revenue_event_key') ? vms_dt_call_core_function('vms_ticket_revenue_event_key', $row) : ((string) ($row['event_date'] ?? '') . '|' . (string) ($row['event_title'] ?? '') . '|' . (string) ($row['event_plan_title'] ?? ''));
            if (!isset($event_summary[$event_key])) {
                $event_summary[$event_key] = array(
                    'event_plan_id' => (int) ($row['event_plan_id'] ?? 0),
                    'event_plan_title' => (string) ($row['event_plan_title'] ?? ''),
                    'tec_event_id' => (int) ($row['tec_event_id'] ?? 0),
                    'event_title' => (string) ($row['event_title'] ?? ''),
                    'event_date' => (string) ($row['event_date'] ?? ''),
                    'recognition_status' => (string) ($row['recognition_status'] ?? 'unknown'),
                    'line_count' => 0,
                    'order_ids' => array(),
                    'ticket_cash_total_cents' => 0,
                    'square_gross_cents' => 0,
                    'square_fee_cents' => 0,
                    'square_net_cents' => 0,
                );
            }
            $event_summary[$event_key]['line_count']++;
            $event_summary[$event_key]['order_ids'][(int) ($row['order_id'] ?? 0)] = true;
            $event_summary[$event_key]['ticket_cash_total_cents'] += (int) ($row['cash_total_cents'] ?? 0);
            $event_summary[$event_key]['square_gross_cents'] += (int) ($row['square_gross_cents'] ?? 0);
            $event_summary[$event_key]['square_fee_cents'] += (int) ($row['square_fee_cents'] ?? 0);
            $event_summary[$event_key]['square_net_cents'] += (int) ($row['square_net_cents'] ?? 0);
        }
    }

    foreach ($event_summary as $key => $summary_row) {
        $event_summary[$key]['order_count'] = count((array) ($summary_row['order_ids'] ?? array()));
        unset($event_summary[$key]['order_ids']);
    }

    uasort($event_summary, static function (array $a, array $b): int {
        $cmp = strcmp((string) ($a['event_date'] ?? ''), (string) ($b['event_date'] ?? ''));
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp(
            (string) (($a['event_plan_title'] ?? '') ?: ($a['event_title'] ?? '')),
            (string) (($b['event_plan_title'] ?? '') ?: ($b['event_title'] ?? ''))
        );
    });

    $sold_date_summary = array();
    foreach ($orders as $order) {
        $sold_date = (string) ($order['sold_date'] ?? '');
        if ($sold_date === '') {
            $sold_date = 'unknown';
        }
        if (!isset($sold_date_summary[$sold_date])) {
            $sold_date_summary[$sold_date] = array(
                'sold_date' => $sold_date,
                'order_count' => 0,
                'matched_order_count' => 0,
                'ticket_cash_total_cents' => 0,
                'ticket_tax_cents' => 0,
                'square_gross_cents' => 0,
                'square_fee_cents' => 0,
                'square_net_cents' => 0,
            );
        }
        $sold_date_summary[$sold_date]['order_count']++;
        if (!empty($order['matched_square_entry_ids'])) {
            $sold_date_summary[$sold_date]['matched_order_count']++;
        }
        $sold_date_summary[$sold_date]['ticket_cash_total_cents'] += (int) ($order['ticket_cash_total_cents'] ?? 0);
        $sold_date_summary[$sold_date]['ticket_tax_cents'] += (int) ($order['ticket_tax_cents'] ?? 0);
        $sold_date_summary[$sold_date]['square_gross_cents'] += (int) ($order['matched_square_gross_cents'] ?? 0);
        $sold_date_summary[$sold_date]['square_fee_cents'] += (int) ($order['matched_square_fee_cents'] ?? 0);
        $sold_date_summary[$sold_date]['square_net_cents'] += (int) ($order['matched_square_net_cents'] ?? 0);
    }
    ksort($sold_date_summary);

    $payout_summary = array();
    foreach ((array) ($square['payouts'] ?? array()) as $payout_id => $payout) {
        $created = vms_dt_square_ticket_merge_localize_rfc3339((string) ($payout['created_at'] ?? ''));
        $payout_summary[$payout_id] = array(
            'payout_id' => (string) ($payout['id'] ?? $payout_id),
            'location_id' => (string) ($payout['location_id'] ?? ''),
            'status' => (string) ($payout['status'] ?? ''),
            'type' => (string) ($payout['type'] ?? ''),
            'arrival_date' => (string) ($payout['arrival_date'] ?? ''),
            'created_date' => (string) ($created['date'] ?? ''),
            'amount_cents' => (int) ($payout['amount_money']['amount'] ?? 0),
            'ticket_entry_count' => 0,
            'ticket_order_count' => 0,
            'ticket_gross_cents' => 0,
            'ticket_fee_cents' => 0,
            'ticket_net_cents' => 0,
            'other_payment_entry_count' => 0,
            'other_payment_net_cents' => 0,
            'global_adjustment_entry_count' => 0,
            'global_adjustment_net_cents' => 0,
            '_ticket_orders' => array(),
        );
    }

    $order_ids_by_entry = array();
    foreach ($orders as $order_id => $order) {
        foreach ((array) ($order['matched_square_entry_ids'] ?? array()) as $entry_id) {
            $order_ids_by_entry[(string) $entry_id] = $order_id;
        }
    }

    foreach ((array) ($square['entries'] ?? array()) as $entry) {
        $payout_id = (string) ($entry['payout_id'] ?? '');
        if ($payout_id === '' || !isset($payout_summary[$payout_id])) {
            continue;
        }
        $entry_id = (string) ($entry['entry_id'] ?? '');
        if ($entry_id !== '' && isset($order_ids_by_entry[$entry_id])) {
            $order_id = (int) $order_ids_by_entry[$entry_id];
            $payout_summary[$payout_id]['ticket_entry_count']++;
            $payout_summary[$payout_id]['ticket_gross_cents'] += (int) ($entry['gross_cents'] ?? 0);
            $payout_summary[$payout_id]['ticket_fee_cents'] += (int) ($entry['fee_cents'] ?? 0);
            $payout_summary[$payout_id]['ticket_net_cents'] += (int) ($entry['net_cents'] ?? 0);
            $payout_summary[$payout_id]['_ticket_orders'][$order_id] = true;
        } elseif (!empty($entry['payment_id'])) {
            $payout_summary[$payout_id]['other_payment_entry_count']++;
            $payout_summary[$payout_id]['other_payment_net_cents'] += (int) ($entry['net_cents'] ?? 0);
        } else {
            $payout_summary[$payout_id]['global_adjustment_entry_count']++;
            $payout_summary[$payout_id]['global_adjustment_net_cents'] += (int) ($entry['net_cents'] ?? 0);
        }
    }

    foreach ($payout_summary as $payout_id => $summary_row) {
        $payout_summary[$payout_id]['ticket_order_count'] = count((array) ($summary_row['_ticket_orders'] ?? array()));
        unset($payout_summary[$payout_id]['_ticket_orders']);
    }

    uasort($payout_summary, static function (array $a, array $b): int {
        $cmp = strcmp((string) ($a['arrival_date'] ?? ''), (string) ($b['arrival_date'] ?? ''));
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp((string) ($a['payout_id'] ?? ''), (string) ($b['payout_id'] ?? ''));
    });

    uasort($orders, static function (array $a, array $b): int {
        $cmp = strcmp((string) ($a['sold_datetime'] ?? ''), (string) ($b['sold_datetime'] ?? ''));
        if ($cmp !== 0) {
            return $cmp;
        }
        return ((int) ($a['order_id'] ?? 0)) <=> ((int) ($b['order_id'] ?? 0));
    });

    $report['orders'] = $orders;
    $report['unmatched_orders'] = array_values(array_filter($orders, static function (array $order): bool {
        return empty($order['matched_square_entry_ids']);
    }));
    $report['sold_date_summary'] = $sold_date_summary;
    $report['event_summary'] = $event_summary;
    $report['payout_summary'] = $payout_summary;
    $report['rows'] = $rows;

    if ($report['counts']['ticket_orders'] > 0 && $report['counts']['ticket_orders_matched'] === 0) {
        $report['warnings'][] = __('No ticket orders matched Square payout entries. This usually means the payout window is too narrow, the selected Square location is wrong, the Woo orders were paid through a different gateway, or the Woo transaction IDs do not map cleanly to Square payment IDs on this site.', 'vms-data-tools');
    }

    if ($report['counts']['unallocated_square_adjustment_entries'] > 0) {
        $report['warnings'][] = __('Some Square payout adjustments have no payment ID and cannot be allocated back to a specific Woo order. They remain visible in the payout summary so deposit math stays honest.', 'vms-data-tools');
    }

    return $report;
}


function vms_dt_square_ticket_merge_post_args(): array
{
    return vms_dt_square_ticket_merge_request_args('post');
}

function vms_dt_square_ticket_merge_download_hidden_fields(array $args): void
{
    foreach (array('date_preset', 'sold_from', 'sold_to', 'event_from', 'event_to', 'as_of_date', 'recognition_status', 'square_location_id', 'payout_from', 'payout_to', 'preview_limit') as $key) {
        echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr((string) ($args[$key] ?? '')) . '" />';
    }

    foreach ((array) ($args['order_statuses'] ?? array()) as $status) {
        echo '<input type="hidden" name="order_statuses[]" value="' . esc_attr((string) $status) . '" />';
    }
}

function vms_dt_square_ticket_merge_decimal(int $cents): string
{
    if (vms_dt_has_core_function('vms_ticket_revenue_cents_to_decimal')) {
        return (string) vms_dt_call_core_function('vms_ticket_revenue_cents_to_decimal', $cents);
    }

    return number_format($cents / 100, 2, '.', '');
}

function vms_dt_square_ticket_merge_assert_download_access(string $nonce_action): void
{
    if (!function_exists('vms_dt_current_user_can_manage_tools') || !vms_dt_current_user_can_manage_tools()) {
        wp_die(esc_html__('You do not have permission to download this file.', 'vms-data-tools'));
    }

    check_admin_referer($nonce_action, 'nonce');

    if (!vms_dt_has_core_function('vms_ticket_revenue_build_report')) {
        wp_die(esc_html__('VMS core ticket revenue service is missing.', 'vms-data-tools'));
    }
}

function vms_dt_square_ticket_merge_build_post_report(): array
{
    return vms_dt_square_ticket_merge_build_report(vms_dt_square_ticket_merge_post_args());
}

function vms_dt_square_ticket_merge_send_temp_csv($fh, string $filename_prefix): void
{
    rewind($fh);
    $csv = (string) stream_get_contents($fh);
    fclose($fh);

    $filename = $filename_prefix . '-' . wp_date('Ymd-His', time(), wp_timezone()) . '.csv';
    vms_dt_download_csv_response($filename, $csv);
}

function vms_dt_square_ticket_merge_render_download_forms(array $args): void
{
    $downloads = array(
        array(
            'action' => 'vms_dt_square_ticket_merge_export_sold_date_csv',
            'nonce' => 'vms_dt_square_ticket_merge_export_sold_date_csv',
            'label' => __('Download sold-date CSV', 'vms-data-tools'),
            'primary' => true,
        ),
        array(
            'action' => 'vms_dt_square_ticket_merge_export_event_summary_csv',
            'nonce' => 'vms_dt_square_ticket_merge_export_event_summary_csv',
            'label' => __('Download event overlay CSV', 'vms-data-tools'),
        ),
        array(
            'action' => 'vms_dt_square_ticket_merge_export_order_detail_csv',
            'nonce' => 'vms_dt_square_ticket_merge_export_order_detail_csv',
            'label' => __('Download order detail CSV', 'vms-data-tools'),
        ),
        array(
            'action' => 'vms_dt_square_ticket_merge_export_payout_summary_csv',
            'nonce' => 'vms_dt_square_ticket_merge_export_payout_summary_csv',
            'label' => __('Download payout summary CSV', 'vms-data-tools'),
        ),
        array(
            'action' => 'vms_dt_square_ticket_merge_export_unmatched_orders_csv',
            'nonce' => 'vms_dt_square_ticket_merge_export_unmatched_orders_csv',
            'label' => __('Download unmatched orders CSV', 'vms-data-tools'),
        ),
        array(
            'action' => 'vms_dt_square_ticket_merge_export_unmatched_square_entries_csv',
            'nonce' => 'vms_dt_square_ticket_merge_export_unmatched_square_entries_csv',
            'label' => __('Download unmatched Square entries CSV', 'vms-data-tools'),
        ),
    );

    echo '<div class="vms-dt-actions">';
    foreach ($downloads as $download) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr((string) $download['action']) . '" />';
        echo '<input type="hidden" name="nonce" value="' . esc_attr(wp_create_nonce((string) $download['nonce'])) . '" />';
        vms_dt_square_ticket_merge_download_hidden_fields($args);
        $class = !empty($download['primary']) ? 'button button-primary' : 'button';
        echo '<button class="' . esc_attr($class) . '" type="submit">' . esc_html((string) $download['label']) . '</button>';
        echo '</form>';
    }
    echo '</div>';
}

function vms_dt_square_ticket_merge_render_unmatched_orders_table(array $orders, int $limit): void
{
    echo '<div class="vms-dt-table-wrap"><table class="widefat striped vms-dt-table">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Sold', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Order', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Gateway', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Events', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Ticket cash', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Candidate payment IDs', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Square-looking?', 'vms-data-tools') . '</th>';
    echo '</tr></thead><tbody>';
    if (empty($orders)) {
        echo '<tr><td colspan="7">' . esc_html__('No unmatched ticket orders remain in the current result set.', 'vms-data-tools') . '</td></tr>';
    } else {
        foreach (array_slice($orders, 0, $limit) as $row) {
            $gateway = trim((string) (($row['gateway_title'] ?? '') ?: ($row['gateway_slug'] ?? '')));
            $events = !empty($row['event_names']) ? implode(', ', array_slice((array) $row['event_names'], 0, 3)) : '—';
            if (!empty($row['event_names']) && count((array) $row['event_names']) > 3) {
                $events .= ' +' . (count((array) $row['event_names']) - 3);
            }
            echo '<tr>';
            echo '<td>' . esc_html((string) (($row['sold_datetime'] ?? '') ?: ($row['sold_date'] ?? '—'))) . '</td>';
            echo '<td>#' . esc_html((string) ($row['order_number'] ?? $row['order_id'] ?? '')) . '</td>';
            echo '<td>' . esc_html($gateway !== '' ? $gateway : '—') . '</td>';
            echo '<td>' . esc_html($events) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['ticket_cash_total_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(!empty($row['candidate_payment_ids']) ? implode(', ', (array) $row['candidate_payment_ids']) : '—') . '</td>';
            echo '<td>' . esc_html(!empty($row['looks_square']) ? __('Yes', 'vms-data-tools') : __('No', 'vms-data-tools')) . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table></div>';
}

function vms_dt_square_ticket_merge_render_unmatched_square_entries_table(array $entries, int $limit, bool $is_adjustment = false): void
{
    echo '<div class="vms-dt-table-wrap"><table class="widefat striped vms-dt-table">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Effective', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Payout', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Type', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Payment ID', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Gross', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Fee', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Net', 'vms-data-tools') . '</th>';
    echo '</tr></thead><tbody>';
    if (empty($entries)) {
        $empty = $is_adjustment
            ? __('No unallocated Square adjustment entries remain in the current result set.', 'vms-data-tools')
            : __('No unmatched Square payment entries remain in the current result set.', 'vms-data-tools');
        echo '<tr><td colspan="7">' . esc_html($empty) . '</td></tr>';
    } else {
        foreach (array_slice($entries, 0, $limit) as $entry) {
            echo '<tr>';
            echo '<td>' . esc_html((string) (($entry['effective_datetime'] ?? '') ?: ($entry['effective_date'] ?? '—'))) . '</td>';
            echo '<td><strong>' . esc_html((string) ($entry['payout_id'] ?? '')) . '</strong><div class="vms-dt-subtle">' . esc_html((string) ($entry['arrival_date'] ?? '')) . '</div></td>';
            echo '<td>' . esc_html((string) ($entry['type'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) (($entry['payment_id'] ?? '') !== '' ? $entry['payment_id'] : '—')) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($entry['gross_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($entry['fee_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($entry['net_cents'] ?? 0))) . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table></div>';
}

function vms_dt_square_ticket_merge_download_sold_date_csv(): void
{
    vms_dt_square_ticket_merge_assert_download_access('vms_dt_square_ticket_merge_export_sold_date_csv');
    $report = vms_dt_square_ticket_merge_build_post_report();
    $fh = fopen('php://temp', 'w+');
    fputcsv($fh, array('sold_date', 'order_count', 'matched_order_count', 'ticket_cash_total', 'ticket_tax', 'square_gross', 'square_fee', 'square_net'));
    foreach ((array) ($report['sold_date_summary'] ?? array()) as $row) {
        fputcsv($fh, array(
            (string) ($row['sold_date'] ?? ''),
            (int) ($row['order_count'] ?? 0),
            (int) ($row['matched_order_count'] ?? 0),
            vms_dt_square_ticket_merge_decimal((int) ($row['ticket_cash_total_cents'] ?? 0)),
            vms_dt_square_ticket_merge_decimal((int) ($row['ticket_tax_cents'] ?? 0)),
            vms_dt_square_ticket_merge_decimal((int) ($row['square_gross_cents'] ?? 0)),
            vms_dt_square_ticket_merge_decimal((int) ($row['square_fee_cents'] ?? 0)),
            vms_dt_square_ticket_merge_decimal((int) ($row['square_net_cents'] ?? 0)),
        ));
    }
    vms_dt_square_ticket_merge_send_temp_csv($fh, 'vms-square-ticket-merge-sold-dates');
}
add_action('admin_post_vms_dt_square_ticket_merge_export_sold_date_csv', 'vms_dt_square_ticket_merge_download_sold_date_csv');

function vms_dt_square_ticket_merge_download_event_summary_csv(): void
{
    vms_dt_square_ticket_merge_assert_download_access('vms_dt_square_ticket_merge_export_event_summary_csv');
    $report = vms_dt_square_ticket_merge_build_post_report();
    $fh = fopen('php://temp', 'w+');
    fputcsv($fh, array('event_plan_id', 'event_plan_title', 'tec_event_id', 'event_title', 'event_date', 'recognition_status', 'order_count', 'line_count', 'ticket_cash_total', 'square_gross', 'square_fee', 'square_net'));
    foreach ((array) ($report['event_summary'] ?? array()) as $row) {
        fputcsv($fh, array(
            (int) ($row['event_plan_id'] ?? 0),
            (string) ($row['event_plan_title'] ?? ''),
            (int) ($row['tec_event_id'] ?? 0),
            (string) ($row['event_title'] ?? ''),
            (string) ($row['event_date'] ?? ''),
            (string) ($row['recognition_status'] ?? ''),
            (int) ($row['order_count'] ?? 0),
            (int) ($row['line_count'] ?? 0),
            vms_dt_square_ticket_merge_decimal((int) ($row['ticket_cash_total_cents'] ?? 0)),
            vms_dt_square_ticket_merge_decimal((int) ($row['square_gross_cents'] ?? 0)),
            vms_dt_square_ticket_merge_decimal((int) ($row['square_fee_cents'] ?? 0)),
            vms_dt_square_ticket_merge_decimal((int) ($row['square_net_cents'] ?? 0)),
        ));
    }
    vms_dt_square_ticket_merge_send_temp_csv($fh, 'vms-square-ticket-merge-events');
}
add_action('admin_post_vms_dt_square_ticket_merge_export_event_summary_csv', 'vms_dt_square_ticket_merge_download_event_summary_csv');

function vms_dt_square_ticket_merge_download_order_detail_csv(): void
{
    vms_dt_square_ticket_merge_assert_download_access('vms_dt_square_ticket_merge_export_order_detail_csv');
    $report = vms_dt_square_ticket_merge_build_post_report();
    $fh = fopen('php://temp', 'w+');
    fputcsv($fh, array('order_id', 'order_number', 'sold_date', 'sold_datetime', 'gateway_slug', 'gateway_title', 'looks_square', 'ticket_line_count', 'ticket_quantity', 'ticket_net_sales', 'ticket_tax', 'ticket_cash_total', 'event_names', 'candidate_payment_ids', 'matched_square_payment_ids', 'matched_square_payout_ids', 'matched_square_gross', 'matched_square_fee', 'matched_square_net', 'gross_delta', 'matched'));
    foreach ((array) ($report['orders'] ?? array()) as $row) {
        fputcsv($fh, array(
            (int) ($row['order_id'] ?? 0),
            (string) ($row['order_number'] ?? ''),
            (string) ($row['sold_date'] ?? ''),
            (string) ($row['sold_datetime'] ?? ''),
            (string) ($row['gateway_slug'] ?? ''),
            (string) ($row['gateway_title'] ?? ''),
            !empty($row['looks_square']) ? 'yes' : 'no',
            (int) ($row['ticket_line_count'] ?? 0),
            (int) ($row['ticket_quantity'] ?? 0),
            vms_dt_square_ticket_merge_decimal((int) ($row['ticket_net_sales_cents'] ?? 0)),
            vms_dt_square_ticket_merge_decimal((int) ($row['ticket_tax_cents'] ?? 0)),
            vms_dt_square_ticket_merge_decimal((int) ($row['ticket_cash_total_cents'] ?? 0)),
            implode(' | ', (array) ($row['event_names'] ?? array())),
            implode(' | ', (array) ($row['candidate_payment_ids'] ?? array())),
            implode(' | ', (array) ($row['matched_square_payment_ids'] ?? array())),
            implode(' | ', (array) ($row['matched_square_payout_ids'] ?? array())),
            vms_dt_square_ticket_merge_decimal((int) ($row['matched_square_gross_cents'] ?? 0)),
            vms_dt_square_ticket_merge_decimal((int) ($row['matched_square_fee_cents'] ?? 0)),
            vms_dt_square_ticket_merge_decimal((int) ($row['matched_square_net_cents'] ?? 0)),
            vms_dt_square_ticket_merge_decimal((int) ($row['gross_delta_cents'] ?? 0)),
            !empty($row['matched_square_entry_ids']) ? 'matched' : 'unmatched',
        ));
    }
    vms_dt_square_ticket_merge_send_temp_csv($fh, 'vms-square-ticket-merge-orders');
}
add_action('admin_post_vms_dt_square_ticket_merge_export_order_detail_csv', 'vms_dt_square_ticket_merge_download_order_detail_csv');

function vms_dt_square_ticket_merge_download_payout_summary_csv(): void
{
    vms_dt_square_ticket_merge_assert_download_access('vms_dt_square_ticket_merge_export_payout_summary_csv');
    $report = vms_dt_square_ticket_merge_build_post_report();
    $fh = fopen('php://temp', 'w+');
    fputcsv($fh, array('payout_id', 'location_id', 'status', 'type', 'arrival_date', 'created_date', 'amount', 'ticket_entry_count', 'ticket_order_count', 'ticket_gross', 'ticket_fee', 'ticket_net', 'other_payment_entry_count', 'other_payment_net', 'global_adjustment_entry_count', 'global_adjustment_net'));
    foreach ((array) ($report['payout_summary'] ?? array()) as $row) {
        fputcsv($fh, array(
            (string) ($row['payout_id'] ?? ''),
            (string) ($row['location_id'] ?? ''),
            (string) ($row['status'] ?? ''),
            (string) ($row['type'] ?? ''),
            (string) ($row['arrival_date'] ?? ''),
            (string) ($row['created_date'] ?? ''),
            vms_dt_square_ticket_merge_decimal((int) ($row['amount_cents'] ?? 0)),
            (int) ($row['ticket_entry_count'] ?? 0),
            (int) ($row['ticket_order_count'] ?? 0),
            vms_dt_square_ticket_merge_decimal((int) ($row['ticket_gross_cents'] ?? 0)),
            vms_dt_square_ticket_merge_decimal((int) ($row['ticket_fee_cents'] ?? 0)),
            vms_dt_square_ticket_merge_decimal((int) ($row['ticket_net_cents'] ?? 0)),
            (int) ($row['other_payment_entry_count'] ?? 0),
            vms_dt_square_ticket_merge_decimal((int) ($row['other_payment_net_cents'] ?? 0)),
            (int) ($row['global_adjustment_entry_count'] ?? 0),
            vms_dt_square_ticket_merge_decimal((int) ($row['global_adjustment_net_cents'] ?? 0)),
        ));
    }
    vms_dt_square_ticket_merge_send_temp_csv($fh, 'vms-square-ticket-merge-payouts');
}
add_action('admin_post_vms_dt_square_ticket_merge_export_payout_summary_csv', 'vms_dt_square_ticket_merge_download_payout_summary_csv');

function vms_dt_square_ticket_merge_download_unmatched_orders_csv(): void
{
    vms_dt_square_ticket_merge_assert_download_access('vms_dt_square_ticket_merge_export_unmatched_orders_csv');
    $report = vms_dt_square_ticket_merge_build_post_report();
    $fh = fopen('php://temp', 'w+');
    fputcsv($fh, array('order_id', 'order_number', 'sold_date', 'sold_datetime', 'gateway_slug', 'gateway_title', 'looks_square', 'ticket_cash_total', 'event_names', 'candidate_payment_ids'));
    foreach ((array) ($report['unmatched_orders'] ?? array()) as $row) {
        fputcsv($fh, array(
            (int) ($row['order_id'] ?? 0),
            (string) ($row['order_number'] ?? ''),
            (string) ($row['sold_date'] ?? ''),
            (string) ($row['sold_datetime'] ?? ''),
            (string) ($row['gateway_slug'] ?? ''),
            (string) ($row['gateway_title'] ?? ''),
            !empty($row['looks_square']) ? 'yes' : 'no',
            vms_dt_square_ticket_merge_decimal((int) ($row['ticket_cash_total_cents'] ?? 0)),
            implode(' | ', (array) ($row['event_names'] ?? array())),
            implode(' | ', (array) ($row['candidate_payment_ids'] ?? array())),
        ));
    }
    vms_dt_square_ticket_merge_send_temp_csv($fh, 'vms-square-ticket-merge-unmatched-orders');
}
add_action('admin_post_vms_dt_square_ticket_merge_export_unmatched_orders_csv', 'vms_dt_square_ticket_merge_download_unmatched_orders_csv');

function vms_dt_square_ticket_merge_download_unmatched_square_entries_csv(): void
{
    vms_dt_square_ticket_merge_assert_download_access('vms_dt_square_ticket_merge_export_unmatched_square_entries_csv');
    $report = vms_dt_square_ticket_merge_build_post_report();
    $fh = fopen('php://temp', 'w+');
    fputcsv($fh, array('bucket', 'entry_id', 'payout_id', 'payout_status', 'arrival_date', 'effective_date', 'effective_datetime', 'type', 'payment_id', 'refund_id', 'gross', 'fee', 'net'));
    foreach (array(
        'unmatched_payment_entry' => (array) ($report['unmatched_square_entries'] ?? array()),
        'unallocated_adjustment' => (array) ($report['unallocated_adjustment_entries'] ?? array()),
    ) as $bucket => $entries) {
        foreach ($entries as $entry) {
            fputcsv($fh, array(
                $bucket,
                (string) ($entry['entry_id'] ?? ''),
                (string) ($entry['payout_id'] ?? ''),
                (string) ($entry['payout_status'] ?? ''),
                (string) ($entry['arrival_date'] ?? ''),
                (string) ($entry['effective_date'] ?? ''),
                (string) ($entry['effective_datetime'] ?? ''),
                (string) ($entry['type'] ?? ''),
                (string) ($entry['payment_id'] ?? ''),
                (string) ($entry['refund_id'] ?? ''),
                vms_dt_square_ticket_merge_decimal((int) ($entry['gross_cents'] ?? 0)),
                vms_dt_square_ticket_merge_decimal((int) ($entry['fee_cents'] ?? 0)),
                vms_dt_square_ticket_merge_decimal((int) ($entry['net_cents'] ?? 0)),
            ));
        }
    }
    vms_dt_square_ticket_merge_send_temp_csv($fh, 'vms-square-ticket-merge-unmatched-square');
}
add_action('admin_post_vms_dt_square_ticket_merge_export_unmatched_square_entries_csv', 'vms_dt_square_ticket_merge_download_unmatched_square_entries_csv');

function vms_dt_square_ticket_merge_render_form(array $args, array $location_options, array $available_statuses): void
{
    $page_slug = vms_dt_get_menu_slug_square_ticket_merge();
    $preset_options = function_exists('vms_dt_ticket_revenue_preset_options')
        ? vms_dt_ticket_revenue_preset_options()
        : array('custom' => __('Custom', 'vms-data-tools'));

    echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
    echo '<input type="hidden" name="page" value="' . esc_attr($page_slug) . '" />';
    echo '<div class="vms-dt-form-grid">';

    echo '<label class="vms-dt-field"><span>' . esc_html__('Sold date preset', 'vms-data-tools') . '</span><select name="date_preset">';
    foreach ($preset_options as $value => $label) {
        echo '<option value="' . esc_attr((string) $value) . '"' . selected((string) ($args['date_preset'] ?? 'custom'), (string) $value, false) . '>' . esc_html((string) $label) . '</option>';
    }
    echo '</select></label>';

    echo '<label class="vms-dt-field"><span>' . esc_html__('Sold from', 'vms-data-tools') . '</span><input type="date" name="sold_from" value="' . esc_attr((string) ($args['sold_from'] ?? '')) . '" /></label>';
    echo '<label class="vms-dt-field"><span>' . esc_html__('Sold to', 'vms-data-tools') . '</span><input type="date" name="sold_to" value="' . esc_attr((string) ($args['sold_to'] ?? '')) . '" /></label>';
    echo '<label class="vms-dt-field"><span>' . esc_html__('Event from', 'vms-data-tools') . '</span><input type="date" name="event_from" value="' . esc_attr((string) ($args['event_from'] ?? '')) . '" /></label>';
    echo '<label class="vms-dt-field"><span>' . esc_html__('Event to', 'vms-data-tools') . '</span><input type="date" name="event_to" value="' . esc_attr((string) ($args['event_to'] ?? '')) . '" /></label>';
    echo '<label class="vms-dt-field"><span>' . esc_html__('Recognition as of', 'vms-data-tools') . '</span><input type="date" name="as_of_date" value="' . esc_attr((string) ($args['as_of_date'] ?? '')) . '" /></label>';

    echo '<label class="vms-dt-field"><span>' . esc_html__('Square location', 'vms-data-tools') . '</span><select name="square_location_id">';
    echo '<option value="">' . esc_html__('Choose location', 'vms-data-tools') . '</option>';
    foreach ($location_options as $value => $label) {
        echo '<option value="' . esc_attr((string) $value) . '"' . selected((string) ($args['square_location_id'] ?? ''), (string) $value, false) . '>' . esc_html((string) $label) . '</option>';
    }
    echo '</select></label>';

    echo '<label class="vms-dt-field"><span>' . esc_html__('Payout window from', 'vms-data-tools') . '</span><input type="date" name="payout_from" value="' . esc_attr((string) ($args['payout_from'] ?? '')) . '" /></label>';
    echo '<label class="vms-dt-field"><span>' . esc_html__('Payout window to', 'vms-data-tools') . '</span><input type="date" name="payout_to" value="' . esc_attr((string) ($args['payout_to'] ?? '')) . '" /></label>';
    echo '<label class="vms-dt-field"><span>' . esc_html__('Preview rows', 'vms-data-tools') . '</span><input type="number" min="25" max="500" step="25" name="preview_limit" value="' . esc_attr((string) ((int) ($args['preview_limit'] ?? 150))) . '" /></label>';

    echo '</div>';

    echo '<div class="vms-dt-field-group">';
    echo '<div class="vms-dt-field-group__label">' . esc_html__('Order statuses', 'vms-data-tools') . '</div>';
    echo '<div class="vms-dt-check-grid">';
    $selected_statuses = (array) ($args['order_statuses'] ?? array());
    foreach ($available_statuses as $status => $label) {
        echo '<label class="vms-dt-check"><input type="checkbox" name="order_statuses[]" value="' . esc_attr((string) $status) . '"' . checked(in_array((string) $status, $selected_statuses, true), true, false) . ' /> <span>' . esc_html((string) $label) . '</span></label>';
    }
    echo '</div>';
    echo '</div>';

    echo '<p><button type="submit" class="button button-primary" name="vms_dt_preview" value="1">' . esc_html__('Preview merge', 'vms-data-tools') . '</button></p>';
    echo '</form>';
}

function vms_dt_square_ticket_merge_render_kpis(array $report): void
{
    $counts = (array) ($report['counts'] ?? array());
    $ticket_summary = (array) (($report['ticket']['summary'] ?? array()));

    echo '<div class="vms-dt-kpi-grid">';
    $items = array(
        array('label' => __('Ticket orders', 'vms-data-tools'), 'value' => (int) ($counts['ticket_orders'] ?? 0)),
        array('label' => __('Matched ticket orders', 'vms-data-tools'), 'value' => (int) ($counts['ticket_orders_matched'] ?? 0)),
        array('label' => __('Unmatched ticket orders', 'vms-data-tools'), 'value' => (int) ($counts['ticket_orders_unmatched'] ?? 0)),
        array('label' => __('Square payouts', 'vms-data-tools'), 'value' => (int) ($counts['square_payouts'] ?? 0)),
        array('label' => __('Square entries', 'vms-data-tools'), 'value' => (int) ($counts['square_entries'] ?? 0)),
        array('label' => __('Unmatched Square entries', 'vms-data-tools'), 'value' => (int) (($counts['unmatched_square_payment_entries'] ?? 0) + ($counts['unallocated_square_adjustment_entries'] ?? 0))),
        array('label' => __('Ticket cash total', 'vms-data-tools'), 'value' => function_exists('vms_dt_ticket_revenue_money') ? vms_dt_ticket_revenue_money((int) ($ticket_summary['cash_total_cents'] ?? 0)) : (string) ((int) ($ticket_summary['cash_total_cents'] ?? 0))),
        array('label' => __('Deferred ticket revenue', 'vms-data-tools'), 'value' => function_exists('vms_dt_ticket_revenue_money') ? vms_dt_ticket_revenue_money((int) ($ticket_summary['deferred_cents'] ?? 0)) : (string) ((int) ($ticket_summary['deferred_cents'] ?? 0))),
    );
    foreach ($items as $item) {
        echo '<div class="vms-dt-kpi"><div class="vms-dt-kpi__label">' . esc_html((string) $item['label']) . '</div><div class="vms-dt-kpi__value">' . esc_html((string) $item['value']) . '</div></div>';
    }
    echo '</div>';
}

function vms_dt_square_ticket_merge_render_sold_date_table(array $rows): void
{
    echo '<div class="vms-dt-table-wrap"><table class="widefat striped vms-dt-table">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Sold date', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Orders', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Matched', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Ticket cash', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Square gross', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Square fees', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Square net', 'vms-data-tools') . '</th>';
    echo '</tr></thead><tbody>';
    if (empty($rows)) {
        echo '<tr><td colspan="7">' . esc_html__('No sold-date rows matched the current filters.', 'vms-data-tools') . '</td></tr>';
    } else {
        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . esc_html((string) ($row['sold_date'] ?? '—')) . '</td>';
            echo '<td>' . esc_html((string) ((int) ($row['order_count'] ?? 0))) . '</td>';
            echo '<td>' . esc_html((string) ((int) ($row['matched_order_count'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['ticket_cash_total_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['square_gross_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['square_fee_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['square_net_cents'] ?? 0))) . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table></div>';
}

function vms_dt_square_ticket_merge_render_event_table(array $rows): void
{
    echo '<div class="vms-dt-table-wrap"><table class="widefat striped vms-dt-table">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Event', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Event date', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Recognition', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Orders', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Lines', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Ticket cash', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Allocated Square gross', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Allocated Square fees', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Allocated Square net', 'vms-data-tools') . '</th>';
    echo '</tr></thead><tbody>';
    if (empty($rows)) {
        echo '<tr><td colspan="9">' . esc_html__('No event rows matched the current filters.', 'vms-data-tools') . '</td></tr>';
    } else {
        foreach ($rows as $row) {
            $event_name = trim((string) (($row['event_plan_title'] ?? '') ?: ($row['event_title'] ?? __('Unknown event', 'vms-data-tools'))));
            echo '<tr>';
            echo '<td><strong>' . esc_html($event_name) . '</strong></td>';
            echo '<td>' . esc_html((string) ($row['event_date'] ?? '—')) . '</td>';
            echo '<td>' . esc_html(ucfirst((string) ($row['recognition_status'] ?? 'unknown'))) . '</td>';
            echo '<td>' . esc_html((string) ((int) ($row['order_count'] ?? 0))) . '</td>';
            echo '<td>' . esc_html((string) ((int) ($row['line_count'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['ticket_cash_total_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['square_gross_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['square_fee_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['square_net_cents'] ?? 0))) . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table></div>';
}

function vms_dt_square_ticket_merge_render_order_table(array $orders, int $limit): void
{
    echo '<div class="vms-dt-table-wrap"><table class="widefat striped vms-dt-table">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Sold', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Order', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Gateway', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Events', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Ticket cash', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Square gross', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Square fee', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Square net', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Gross delta', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Match', 'vms-data-tools') . '</th>';
    echo '</tr></thead><tbody>';
    if (empty($orders)) {
        echo '<tr><td colspan="10">' . esc_html__('No ticket orders matched the current filters.', 'vms-data-tools') . '</td></tr>';
    } else {
        $slice = array_slice($orders, 0, $limit, true);
        foreach ($slice as $row) {
            $gateway = trim((string) (($row['gateway_title'] ?? '') ?: ($row['gateway_slug'] ?? '')));
            $events = !empty($row['event_names']) ? implode(', ', array_slice((array) $row['event_names'], 0, 3)) : '—';
            if (!empty($row['event_names']) && count((array) $row['event_names']) > 3) {
                $events .= ' +' . (count((array) $row['event_names']) - 3);
            }
            echo '<tr>';
            echo '<td>' . esc_html((string) ($row['sold_datetime'] ?: $row['sold_date'])) . '</td>';
            echo '<td>#' . esc_html((string) ($row['order_number'] ?? $row['order_id'])) . '<div class="vms-dt-subtle">' . esc_html(implode(', ', (array) ($row['matched_square_payment_ids'] ?? array()))) . '</div></td>';
            echo '<td>' . esc_html($gateway !== '' ? $gateway : '—') . '</td>';
            echo '<td>' . esc_html($events) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['ticket_cash_total_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['matched_square_gross_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['matched_square_fee_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['matched_square_net_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['gross_delta_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(!empty($row['matched_square_entry_ids']) ? __('Matched', 'vms-data-tools') : __('Unmatched', 'vms-data-tools')) . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table></div>';
}

function vms_dt_square_ticket_merge_render_payout_table(array $rows, int $limit): void
{
    echo '<div class="vms-dt-table-wrap"><table class="widefat striped vms-dt-table">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Arrival', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Payout', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Amount', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Ticket orders', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Ticket net', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Other payment net', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Global adjustments', 'vms-data-tools') . '</th>';
    echo '<th>' . esc_html__('Status', 'vms-data-tools') . '</th>';
    echo '</tr></thead><tbody>';
    if (empty($rows)) {
        echo '<tr><td colspan="8">' . esc_html__('No Square payouts were returned for the selected window.', 'vms-data-tools') . '</td></tr>';
    } else {
        $slice = array_slice($rows, 0, $limit, true);
        foreach ($slice as $row) {
            echo '<tr>';
            echo '<td>' . esc_html((string) ($row['arrival_date'] ?? '—')) . '</td>';
            echo '<td><strong>' . esc_html((string) ($row['payout_id'] ?? '')) . '</strong><div class="vms-dt-subtle">' . esc_html((string) ($row['location_id'] ?? '')) . '</div></td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['amount_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html((string) ((int) ($row['ticket_order_count'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['ticket_net_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['other_payment_net_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(vms_dt_ticket_revenue_money((int) ($row['global_adjustment_net_cents'] ?? 0))) . '</td>';
            echo '<td>' . esc_html((string) ($row['status'] ?? '')) . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table></div>';
}

function vms_dt_render_square_ticket_merge_page(): void
{
    if (!function_exists('vms_dt_current_user_can_manage_tools') || !vms_dt_current_user_can_manage_tools()) {
        return;
    }

    $args = vms_dt_square_ticket_merge_request_args();
    $available_statuses = vms_dt_has_core_function('vms_ticket_revenue_available_statuses')
        ? vms_dt_call_core_function('vms_ticket_revenue_available_statuses')
        : array();
    $location_options = vms_dt_square_ticket_merge_location_options();
    if ((string) ($args['square_location_id'] ?? '') === '' && count($location_options) === 1) {
        $args['square_location_id'] = (string) array_key_first($location_options);
    }

    $do_preview = isset($_GET['vms_dt_preview']) && (string) $_GET['vms_dt_preview'] === '1';

    echo '<div class="wrap vms-dt-wrap">';
    echo '<h1>' . esc_html__('Square + Ticket Merge', 'vms-data-tools') . '</h1>';

    echo '<div class="vms-dt-card vms-dt-section">';
    echo '<p>' . esc_html__('Use the ticket revenue resolver as the event and recognition source of truth, then layer Square payout data on top so ticket deposits, fees, and timing stay in one reconciliation workflow.', 'vms-data-tools') . '</p>';
    echo '<div class="vms-dt-callout"><strong>' . esc_html__('Current merge logic', 'vms-data-tools') . '</strong><p>' . esc_html__('The page matches Woo ticket orders to Square payout entries by Woo transaction/payment identifiers when available. Ticket revenue still follows event recognition rules; payout timing is shown separately so bank deposits do not distort earned-vs-deferred reporting.', 'vms-data-tools') . '</p></div>';
    echo '</div>';

    echo '<div class="vms-dt-card vms-dt-section">';
    echo '<h2>' . esc_html__('Filters', 'vms-data-tools') . '</h2>';
    vms_dt_square_ticket_merge_render_form($args, $location_options, $available_statuses);
    echo '</div>';

    if ($do_preview) {
        $report = vms_dt_square_ticket_merge_build_report($args);

        if (!empty($report['warnings'])) {
            foreach (array_unique(array_filter(array_map('strval', (array) $report['warnings']))) as $warning) {
                echo '<div class="notice notice-warning"><p>' . esc_html($warning) . '</p></div>';
            }
        }

        echo '<div class="vms-dt-card vms-dt-section">';
        echo '<h2>' . esc_html__('Snapshot', 'vms-data-tools') . '</h2>';
        vms_dt_square_ticket_merge_render_kpis($report);
        echo '</div>';

        echo '<div class="vms-dt-card vms-dt-section">';
        echo '<h2>' . esc_html__('Download', 'vms-data-tools') . '</h2>';
        echo '<p class="vms-dt-section-desc">' . esc_html__('Download the merged sold-date, event, order, payout, or diagnostics views using the same filters shown above.', 'vms-data-tools') . '</p>';
        vms_dt_square_ticket_merge_render_download_forms($args);
        echo '</div>';

        echo '<div class="vms-dt-card vms-dt-section">';
        echo '<h2>' . esc_html__('Sold-date reconciliation', 'vms-data-tools') . '</h2>';
        echo '<p class="vms-dt-section-desc">' . esc_html__('This keeps the ticket sold date intact, then shows how much Square gross, fee, and net activity matched back to those orders.', 'vms-data-tools') . '</p>';
        vms_dt_square_ticket_merge_render_sold_date_table((array) ($report['sold_date_summary'] ?? array()));
        echo '</div>';

        echo '<div class="vms-dt-card vms-dt-section">';
        echo '<h2>' . esc_html__('Event recognition + Square overlay', 'vms-data-tools') . '</h2>';
        echo '<p class="vms-dt-section-desc">' . esc_html__('Square gross, fee, and net values are allocated back to ticket lines by cash-total weight inside each matched order so event-level reporting stays readable.', 'vms-data-tools') . '</p>';
        vms_dt_square_ticket_merge_render_event_table((array) ($report['event_summary'] ?? array()));
        echo '</div>';

        echo '<div class="vms-dt-card vms-dt-section">';
        echo '<h2>' . esc_html__('Order match detail', 'vms-data-tools') . '</h2>';
        echo '<p class="vms-dt-section-desc">' . esc_html__('Use this when a daily total looks off. Gross delta compares the matched Square entry gross against the ticket cash total exported from Woo.', 'vms-data-tools') . '</p>';
        vms_dt_square_ticket_merge_render_order_table((array) ($report['orders'] ?? array()), (int) ($args['preview_limit'] ?? 150));
        echo '</div>';

        echo '<div class="vms-dt-card vms-dt-section">';
        echo '<h2>' . esc_html__('Unmatched ticket orders', 'vms-data-tools') . '</h2>';
        echo '<p class="vms-dt-section-desc">' . esc_html__('These ticket orders stayed unmatched after the current Square merge pass. Candidate payment IDs are shown so you can spot missing or mis-mapped transaction metadata quickly.', 'vms-data-tools') . '</p>';
        vms_dt_square_ticket_merge_render_unmatched_orders_table((array) ($report['unmatched_orders'] ?? array()), (int) ($args['preview_limit'] ?? 150));
        echo '</div>';

        echo '<div class="vms-dt-card vms-dt-section">';
        echo '<h2>' . esc_html__('Unmatched Square payment entries', 'vms-data-tools') . '</h2>';
        echo '<p class="vms-dt-section-desc">' . esc_html__('These Square entries carried payment IDs but did not map back to a ticket order in the current filtered result set.', 'vms-data-tools') . '</p>';
        vms_dt_square_ticket_merge_render_unmatched_square_entries_table((array) ($report['unmatched_square_entries'] ?? array()), (int) ($args['preview_limit'] ?? 150), false);
        echo '</div>';

        echo '<div class="vms-dt-card vms-dt-section">';
        echo '<h2>' . esc_html__('Unallocated Square adjustments', 'vms-data-tools') . '</h2>';
        echo '<p class="vms-dt-section-desc">' . esc_html__('These payout adjustments carried no payment ID, so they remain deposit-level adjustments instead of being forced onto a specific ticket order.', 'vms-data-tools') . '</p>';
        vms_dt_square_ticket_merge_render_unmatched_square_entries_table((array) ($report['unallocated_adjustment_entries'] ?? array()), (int) ($args['preview_limit'] ?? 150), true);
        echo '</div>';

        echo '<div class="vms-dt-card vms-dt-section">';
        echo '<h2>' . esc_html__('Square payout deposit view', 'vms-data-tools') . '</h2>';
        echo '<p class="vms-dt-section-desc">' . esc_html__('Payout timing stays separate here. Ticket-related net shows what portion of each Square payout matched to ticket orders in the current filter window.', 'vms-data-tools') . '</p>';
        vms_dt_square_ticket_merge_render_payout_table((array) ($report['payout_summary'] ?? array()), (int) ($args['preview_limit'] ?? 150));
        echo '</div>';
    }

    echo '</div>';
}
