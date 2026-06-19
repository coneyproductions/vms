<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('vms_ticket_revenue_money_to_cents')) {
    function vms_ticket_revenue_money_to_cents($amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}

if (!function_exists('vms_ticket_revenue_cents_to_decimal')) {
    function vms_ticket_revenue_cents_to_decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}

if (!function_exists('vms_ticket_revenue_default_statuses')) {
    function vms_ticket_revenue_default_statuses(): array
    {
        return array('processing', 'completed', 'refunded');
    }
}

if (!function_exists('vms_ticket_revenue_available_statuses')) {
    function vms_ticket_revenue_available_statuses(): array
    {
        $statuses = function_exists('wc_get_order_statuses') ? (array) wc_get_order_statuses() : array();
        $out = array();
        foreach ($statuses as $key => $label) {
            $slug = (string) $key;
            if (strpos($slug, 'wc-') === 0) {
                $slug = substr($slug, 3);
            }
            $slug = sanitize_key($slug);
            if ($slug === '') {
                continue;
            }
            $out[$slug] = (string) $label;
        }
        if (empty($out)) {
            $out = array(
                'pending' => __('Pending payment', 'vms'),
                'processing' => __('Processing', 'vms'),
                'completed' => __('Completed', 'vms'),
                'on-hold' => __('On hold', 'vms'),
                'cancelled' => __('Cancelled', 'vms'),
                'refunded' => __('Refunded', 'vms'),
                'failed' => __('Failed', 'vms'),
            );
        }
        return $out;
    }
}

if (!function_exists('vms_ticket_revenue_is_valid_ymd')) {
    function vms_ticket_revenue_is_valid_ymd(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value));
    }
}

if (!function_exists('vms_ticket_revenue_normalize_ymd')) {
    function vms_ticket_revenue_normalize_ymd($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (vms_ticket_revenue_is_valid_ymd($value)) {
            return $value;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $m)) {
            return (string) $m[1];
        }

        $ts = strtotime($value);
        if (!$ts) {
            return '';
        }

        return (string) wp_date('Y-m-d', $ts, wp_timezone());
    }
}

if (!function_exists('vms_ticket_revenue_wp_now_ymd')) {
    function vms_ticket_revenue_wp_now_ymd(): string
    {
        return wp_date('Y-m-d', time(), wp_timezone());
    }
}

if (!function_exists('vms_ticket_revenue_plan_tec_meta_key')) {
    function vms_ticket_revenue_plan_tec_meta_key(): string
    {
        if (function_exists('vms_ticketing_b_meta_key')) {
            return (string) vms_ticketing_b_meta_key('tec_event_id', '_vms_tec_event_id');
        }
        return '_vms_tec_event_id';
    }
}

if (!function_exists('vms_ticket_revenue_product_event_plan_meta_key')) {
    function vms_ticket_revenue_product_event_plan_meta_key(): string
    {
        if (function_exists('vms_ticketing_v2_product_meta_key')) {
            return (string) vms_ticketing_v2_product_meta_key('event_plan_id');
        }
        return '_vms_event_plan_id';
    }
}

if (!function_exists('vms_ticket_revenue_product_tec_meta_key')) {
    function vms_ticket_revenue_product_tec_meta_key(): string
    {
        if (function_exists('vms_ticketing_v2_product_meta_key')) {
            return (string) vms_ticketing_v2_product_meta_key('tec_event_id');
        }
        return '_vms_tec_event_id';
    }
}

if (!function_exists('vms_ticket_revenue_normalize_statuses')) {
    function vms_ticket_revenue_normalize_statuses(array $statuses): array
    {
        $available = vms_ticket_revenue_available_statuses();
        $out = array();
        foreach ($statuses as $status) {
            $status = sanitize_key((string) $status);
            if ($status === '' || !isset($available[$status])) {
                continue;
            }
            $out[] = $status;
        }
        $out = array_values(array_unique($out));
        if (empty($out)) {
            $out = vms_ticket_revenue_default_statuses();
        }
        return $out;
    }
}

if (!function_exists('vms_ticket_revenue_normalize_args')) {
    function vms_ticket_revenue_normalize_args(array $args = array()): array
    {
        $as_of_date = isset($args['as_of_date']) ? sanitize_text_field((string) $args['as_of_date']) : vms_ticket_revenue_wp_now_ymd();
        $as_of_date = vms_ticket_revenue_normalize_ymd($as_of_date);
        if (!vms_ticket_revenue_is_valid_ymd($as_of_date)) {
            $as_of_date = vms_ticket_revenue_wp_now_ymd();
        }

        $recognition = isset($args['recognition_status']) ? sanitize_key((string) $args['recognition_status']) : 'all';
        if (!in_array($recognition, array('all', 'deferred', 'earned', 'unknown'), true)) {
            $recognition = 'all';
        }

        $order_statuses = isset($args['order_statuses']) && is_array($args['order_statuses'])
            ? vms_ticket_revenue_normalize_statuses((array) $args['order_statuses'])
            : vms_ticket_revenue_default_statuses();

        $preview_limit = isset($args['preview_limit']) ? max(1, (int) $args['preview_limit']) : 200;
        $unresolved_limit = isset($args['unresolved_limit']) ? max(1, (int) $args['unresolved_limit']) : 150;

        $out = array(
            'sold_from' => '',
            'sold_to' => '',
            'event_from' => '',
            'event_to' => '',
            'as_of_date' => $as_of_date,
            'recognition_status' => $recognition,
            'order_statuses' => $order_statuses,
            'event_plan_id' => isset($args['event_plan_id']) ? absint($args['event_plan_id']) : 0,
            'tec_event_id' => isset($args['tec_event_id']) ? absint($args['tec_event_id']) : 0,
            'preview_limit' => $preview_limit,
            'unresolved_limit' => $unresolved_limit,
        );

        foreach (array('sold_from', 'sold_to', 'event_from', 'event_to') as $key) {
            $value = isset($args[$key]) ? sanitize_text_field((string) $args[$key]) : '';
            $out[$key] = vms_ticket_revenue_normalize_ymd($value);
            if (!vms_ticket_revenue_is_valid_ymd($out[$key])) {
                $out[$key] = '';
            }
        }

        return $out;
    }
}

if (!function_exists('vms_ticket_revenue_build_order_query')) {
    function vms_ticket_revenue_build_order_query(array $args): array
    {
        $query = array(
            'type' => 'shop_order',
            'status' => array_values($args['order_statuses']),
            'return' => 'ids',
            'limit' => 100,
            'paginate' => true,
            'page' => 1,
        );

        $from = (string) ($args['sold_from'] ?? '');
        $to = (string) ($args['sold_to'] ?? '');

        if ($from !== '' && $to !== '') {
            $query['date_created'] = $from . '...' . $to;
        } elseif ($from !== '') {
            $query['date_created'] = '>=' . $from;
        } elseif ($to !== '') {
            $query['date_created'] = '<=' . $to;
        }

        return $query;
    }
}

if (!function_exists('vms_ticket_revenue_order_refund_map')) {
    function vms_ticket_revenue_order_refund_map($order): array
    {
        $map = array();
        if (!$order || !method_exists($order, 'get_refunds')) {
            return $map;
        }

        foreach ((array) $order->get_refunds() as $refund) {
            if (!$refund || !method_exists($refund, 'get_items')) {
                continue;
            }
            foreach ((array) $refund->get_items('line_item') as $refund_item) {
                if (!$refund_item || !method_exists($refund_item, 'get_meta')) {
                    continue;
                }

                $refunded_item_id = absint($refund_item->get_meta('_refunded_item_id', true));
                if ($refunded_item_id <= 0) {
                    continue;
                }

                if (!isset($map[$refunded_item_id])) {
                    $map[$refunded_item_id] = array(
                        'qty' => 0,
                        'line_subtotal_cents' => 0,
                        'line_tax_cents' => 0,
                    );
                }

                $map[$refunded_item_id]['qty'] += abs((int) $refund_item->get_quantity());
                $map[$refunded_item_id]['line_subtotal_cents'] += abs(vms_ticket_revenue_money_to_cents($refund_item->get_total()));
                $map[$refunded_item_id]['line_tax_cents'] += abs(vms_ticket_revenue_money_to_cents($refund_item->get_total_tax()));
            }
        }

        return $map;
    }
}

if (!function_exists('vms_ticket_sales_resolver_utc_timezone')) {
    function vms_ticket_sales_resolver_utc_timezone(): DateTimeZone
    {
        static $timezone = null;

        if (!$timezone instanceof DateTimeZone) {
            $timezone = new DateTimeZone('UTC');
        }

        return $timezone;
    }
}

if (!function_exists('vms_ticket_sales_resolver_normalize_int_list')) {
    function vms_ticket_sales_resolver_normalize_int_list($value): array
    {
        $raw = array();

        if (is_array($value)) {
            array_walk_recursive($value, static function ($item) use (&$raw): void {
                $raw[] = $item;
            });
        } elseif (is_string($value)) {
            $raw = preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        } elseif ($value !== null && $value !== '') {
            $raw = array($value);
        }

        $ids = array();
        foreach ($raw as $item) {
            $id = absint($item);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}

if (!function_exists('vms_ticket_sales_resolver_normalize_bool')) {
    function vms_ticket_sales_resolver_normalize_bool($value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int) $value) !== 0;
        }

        $value = strtolower(trim((string) $value));
        if (in_array($value, array('1', 'true', 'yes', 'on'), true)) {
            return true;
        }
        if (in_array($value, array('0', 'false', 'no', 'off'), true)) {
            return false;
        }

        return $default;
    }
}

if (!function_exists('vms_ticket_sales_resolver_normalize_args')) {
    function vms_ticket_sales_resolver_normalize_args(array $args = array()): array
    {
        $order_statuses_raw = $args['order_statuses'] ?? array();
        if (!is_array($order_statuses_raw)) {
            $order_statuses_raw = preg_split('/[\s,]+/', (string) $order_statuses_raw, -1, PREG_SPLIT_NO_EMPTY);
        }

        $date_from = isset($args['date_from']) ? (string) $args['date_from'] : (string) ($args['sold_from'] ?? '');
        $date_to = isset($args['date_to']) ? (string) $args['date_to'] : (string) ($args['sold_to'] ?? '');

        $event_ids = vms_ticket_sales_resolver_normalize_int_list($args['event_ids'] ?? array());
        $legacy_event_id = absint($args['tec_event_id'] ?? 0);
        if ($legacy_event_id > 0 && empty($event_ids)) {
            $event_ids[] = $legacy_event_id;
        }

        $event_plan_ids = vms_ticket_sales_resolver_normalize_int_list($args['event_plan_ids'] ?? array());
        $legacy_event_plan_id = absint($args['event_plan_id'] ?? 0);
        if ($legacy_event_plan_id > 0 && empty($event_plan_ids)) {
            $event_plan_ids[] = $legacy_event_plan_id;
        }

        return array(
            'date_from' => vms_ticket_revenue_normalize_ymd($date_from),
            'date_to' => vms_ticket_revenue_normalize_ymd($date_to),
            'order_statuses' => vms_ticket_revenue_normalize_statuses((array) $order_statuses_raw),
            'order_ids' => vms_ticket_sales_resolver_normalize_int_list($args['order_ids'] ?? array()),
            'event_ids' => array_values(array_unique(array_filter(array_map('absint', $event_ids)))),
            'event_plan_ids' => array_values(array_unique(array_filter(array_map('absint', $event_plan_ids)))),
            'product_ids' => vms_ticket_sales_resolver_normalize_int_list($args['product_ids'] ?? array()),
            'customer_email' => sanitize_email((string) ($args['customer_email'] ?? '')),
            'include_unresolved' => vms_ticket_sales_resolver_normalize_bool($args['include_unresolved'] ?? null, true),
            'include_refunded_lines' => vms_ticket_sales_resolver_normalize_bool($args['include_refunded_lines'] ?? null, true),
            'limit' => max(0, (int) ($args['limit'] ?? 0)),
            'offset' => max(0, (int) ($args['offset'] ?? 0)),
        );
    }
}

if (!function_exists('vms_ticket_sales_resolver_collect_order_ids')) {
    function vms_ticket_sales_resolver_collect_order_ids(array $args): array
    {
        if (!empty($args['order_ids'])) {
            return array_values(array_unique(array_filter(array_map('absint', (array) $args['order_ids']))));
        }

        $query = array(
            'type' => 'shop_order',
            'status' => array_values($args['order_statuses']),
            'return' => 'ids',
            'limit' => 100,
            'paginate' => true,
            'page' => 1,
        );

        $from = (string) ($args['date_from'] ?? '');
        $to = (string) ($args['date_to'] ?? '');
        if ($from !== '' && $to !== '') {
            $query['date_created'] = $from . '...' . $to;
        } elseif ($from !== '') {
            $query['date_created'] = '>=' . $from;
        } elseif ($to !== '') {
            $query['date_created'] = '<=' . $to;
        }

        $order_ids = array();
        $page = 1;
        do {
            $query['page'] = $page;
            $batch = wc_get_orders($query);
            $ids = array();
            $max_num_pages = 0;

            if (is_array($batch) && isset($batch['orders'])) {
                $ids = array_map('absint', (array) $batch['orders']);
                $max_num_pages = max(0, (int) ($batch['max_num_pages'] ?? 0));
            } elseif (is_object($batch) && isset($batch->orders)) {
                $ids = array_map('absint', (array) $batch->orders);
                $max_num_pages = max(0, (int) ($batch->max_num_pages ?? 0));
            } elseif (is_array($batch)) {
                $ids = array_map('absint', $batch);
                $max_num_pages = empty($ids) ? 0 : $page;
            }

            if (!empty($ids)) {
                $order_ids = array_merge($order_ids, $ids);
            }

            $page++;
        } while (!empty($ids) && ($max_num_pages === 0 || $page <= $max_num_pages));

        return array_values(array_unique(array_filter(array_map('absint', $order_ids))));
    }
}

if (!function_exists('vms_ticket_sales_resolver_line_kind_for_product')) {
    function vms_ticket_sales_resolver_line_kind_for_product(int $product_id): string
    {
        $product_id = absint($product_id);
        if ($product_id <= 0) {
            return '';
        }

        $role = '';
        if (function_exists('vms_ticketing_v2_product_role_for_naming')) {
            $role = sanitize_key((string) vms_ticketing_v2_product_role_for_naming($product_id));
        } elseif (function_exists('vms_ticketing_v2_product_meta_key')) {
            $role = sanitize_key((string) get_post_meta($product_id, vms_ticketing_v2_product_meta_key('product_role'), true));
        } else {
            $role = sanitize_key((string) get_post_meta($product_id, '_vms_product_role', true));
        }

        if (in_array($role, array('entitlement', 'addon'), true)) {
            return 'addon';
        }
        if (in_array($role, array('ga_ticket', 'ticket', 'legacy_ticket'), true)) {
            return 'ticket';
        }

        if (function_exists('vms_ticketing_v2_product_is_entitlement') && vms_ticketing_v2_product_is_entitlement($product_id)) {
            return 'addon';
        }

        foreach (array('_vms_ticketing_entitlement_id', '_sr_addon_type', '_sr_required_qualifiers_per_unit', '_sr_addon_unit_label') as $meta_key) {
            $value = get_post_meta($product_id, $meta_key, true);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return 'addon';
            }
        }

        $tec_event_id = 0;
        if (function_exists('vms_ticketing_v2_resolve_event_id_for_product')) {
            $tec_event_id = absint(vms_ticketing_v2_resolve_event_id_for_product($product_id));
        }
        if ($tec_event_id > 0) {
            return 'ticket';
        }

        $event_plan_id = absint(get_post_meta($product_id, vms_ticket_revenue_product_event_plan_meta_key(), true));
        $stored_tec_event_id = absint(get_post_meta($product_id, vms_ticket_revenue_product_tec_meta_key(), true));
        $marker_version_key = function_exists('vms_ticketing_v2_product_meta_key')
            ? vms_ticketing_v2_product_meta_key('ticketing_marker_version')
            : '_vms_ticketing_marker_version';
        $source_provider_key = function_exists('vms_ticketing_v2_product_meta_key')
            ? vms_ticketing_v2_product_meta_key('ticketing_source_provider')
            : '_vms_ticketing_source_provider';
        $has_ticketing_marker = absint(get_post_meta($product_id, $marker_version_key, true)) > 0
            || trim((string) get_post_meta($product_id, $source_provider_key, true)) !== '';
        if ($has_ticketing_marker && ($event_plan_id > 0 || $stored_tec_event_id > 0)) {
            return 'unknown_ticket_related';
        }

        return '';
    }
}

if (!function_exists('vms_ticket_revenue_event_payload_for_tec_event')) {
    function vms_ticket_revenue_event_payload_for_tec_event(int $tec_event_id, array &$event_cache): array
    {
        $tec_event_id = absint($tec_event_id);
        if ($tec_event_id <= 0) {
            return array();
        }

        if (isset($event_cache[$tec_event_id])) {
            return $event_cache[$tec_event_id];
        }

        $event_title = (string) get_the_title($tec_event_id);
        $event_slug = (string) get_post_field('post_name', $tec_event_id);
        $event_start_local = trim((string) get_post_meta($tec_event_id, '_EventStartDate', true));
        $event_start_gmt = trim((string) get_post_meta($tec_event_id, '_EventStartDateUTC', true));
        if ($event_start_gmt === '' && $event_start_local !== '') {
            $event_start_gmt = (string) get_gmt_from_date($event_start_local, 'Y-m-d H:i:s');
        }
        $event_date = vms_ticket_revenue_normalize_ymd($event_start_local);
        $event_permalink = (string) get_permalink($tec_event_id);

        $plan_id = 0;
        if (function_exists('vms_ticketing_v2_find_plan_id_by_tec_event_id')) {
            $plan_id = absint(vms_ticketing_v2_find_plan_id_by_tec_event_id($tec_event_id));
        } elseif (function_exists('vms_ticketing_v2_find_plan_id_by_tec_event')) {
            $plan_id = absint(vms_ticketing_v2_find_plan_id_by_tec_event($tec_event_id));
        }

        $plan_title = '';
        if ($plan_id > 0) {
            $plan_title = (string) get_the_title($plan_id);
            $plan_date = vms_ticket_revenue_normalize_ymd((string) get_post_meta($plan_id, '_vms_event_date', true));
            if ($plan_date !== '') {
                $event_date = $plan_date;
            }

            $plan_start_local = trim((string) get_post_meta($plan_id, '_vms_event_plan_start_datetime', true));
            if ($event_start_local === '' && $plan_start_local !== '') {
                $event_start_local = $plan_start_local;
            }
            if ($event_start_gmt === '' && $plan_start_local !== '') {
                $event_start_gmt = (string) get_gmt_from_date($plan_start_local, 'Y-m-d H:i:s');
            }
        }

        $event_cache[$tec_event_id] = array(
            'tec_event_id' => $tec_event_id,
            'event_title' => $event_title,
            'event_slug' => $event_slug,
            'event_date' => $event_date,
            'event_start_local' => $event_start_local,
            'event_start_gmt' => $event_start_gmt,
            'event_permalink' => $event_permalink,
            'event_plan_id' => $plan_id,
            'event_plan_title' => $plan_title,
        );

        return $event_cache[$tec_event_id];
    }
}

if (!function_exists('vms_ticket_revenue_product_context')) {
    function vms_ticket_revenue_product_context(int $product_id, array &$product_cache, array &$event_cache): array
    {
        $product_id = absint($product_id);
        if ($product_id <= 0) {
            return array();
        }

        if (isset($product_cache[$product_id])) {
            return $product_cache[$product_id];
        }

        $sku = '';
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
            if ($product && method_exists($product, 'get_sku')) {
                $sku = (string) $product->get_sku();
            }
        }

        $line_kind = vms_ticket_sales_resolver_line_kind_for_product($product_id);
        $item_kind = ($line_kind === 'addon') ? 'addon' : (($line_kind !== '') ? $line_kind : 'ticket');

        $event_plan_id = absint(get_post_meta($product_id, vms_ticket_revenue_product_event_plan_meta_key(), true));
        $marker_version_key = function_exists('vms_ticketing_v2_product_meta_key')
            ? vms_ticketing_v2_product_meta_key('ticketing_marker_version')
            : '_vms_ticketing_marker_version';
        $source_provider_key = function_exists('vms_ticketing_v2_product_meta_key')
            ? vms_ticketing_v2_product_meta_key('ticketing_source_provider')
            : '_vms_ticketing_source_provider';
        $has_ticketing_marker = absint(get_post_meta($product_id, $marker_version_key, true)) > 0
            || trim((string) get_post_meta($product_id, $source_provider_key, true)) !== '';
        $tec_event_id = 0;
        $resolution_source = '';
        $resolution_confidence = '';
        if (function_exists('vms_ticketing_v2_resolve_event_id_for_product')) {
            $tec_event_id = absint(vms_ticketing_v2_resolve_event_id_for_product($product_id));
        }
        if ($tec_event_id > 0) {
            $resolution_source = 'ticket_post_event_link';
            $resolution_confidence = 'exact';
        }
        if ($tec_event_id <= 0) {
            $tec_event_id = absint(get_post_meta($product_id, '_vms_ticket_event_id', true));
            if ($tec_event_id > 0) {
                $resolution_source = 'ticket_post_event_link';
                $resolution_confidence = 'exact';
            }
        }
        if ($tec_event_id <= 0) {
            $tec_event_id = absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true));
            if ($tec_event_id > 0) {
                $resolution_source = 'ticket_post_event_link';
                $resolution_confidence = 'exact';
            }
        }
        if ($tec_event_id <= 0) {
            $tec_event_id = absint(get_post_meta($product_id, vms_ticket_revenue_product_tec_meta_key(), true));
            if ($tec_event_id > 0) {
                $resolution_source = 'ticket_post_event_link';
                $resolution_confidence = 'exact';
            }
        }
        if ($tec_event_id <= 0 && $event_plan_id > 0) {
            $tec_event_id = absint(get_post_meta($event_plan_id, vms_ticket_revenue_plan_tec_meta_key(), true));
            if ($tec_event_id > 0) {
                $resolution_source = 'event_plan_link';
                $resolution_confidence = 'derived';
            }
        }

        $resolved = array(
            'product_id' => $product_id,
            'product_sku' => $sku,
            'item_kind' => $item_kind,
            'line_kind' => $line_kind,
            'ticket_post_id' => $product_id,
            'tec_event_id' => 0,
            'event_title' => '',
            'event_slug' => '',
            'event_date' => '',
            'event_start_local' => '',
            'event_start_gmt' => '',
            'event_permalink' => '',
            'event_plan_id' => $event_plan_id,
            'event_plan_title' => $event_plan_id > 0 ? (string) get_the_title($event_plan_id) : '',
            'resolution_source' => $resolution_source,
            'resolution_confidence' => $resolution_confidence,
            'is_ticket_related' => ($line_kind !== '' || $tec_event_id > 0 || ($has_ticketing_marker && $event_plan_id > 0)),
        );

        if ($tec_event_id > 0) {
            $resolved = array_merge($resolved, vms_ticket_revenue_event_payload_for_tec_event($tec_event_id, $event_cache));
        }

        if ($resolved['event_plan_id'] > 0 && $resolved['event_plan_title'] === '') {
            $resolved['event_plan_title'] = (string) get_the_title((int) $resolved['event_plan_id']);
        }
        if ($resolved['event_date'] === '' && $resolved['event_plan_id'] > 0) {
            $resolved['event_date'] = vms_ticket_revenue_normalize_ymd((string) get_post_meta((int) $resolved['event_plan_id'], '_vms_event_date', true));
        }
        if ($resolved['event_start_local'] === '' && $resolved['event_plan_id'] > 0) {
            $resolved['event_start_local'] = trim((string) get_post_meta((int) $resolved['event_plan_id'], '_vms_event_plan_start_datetime', true));
        }
        if ($resolved['event_start_gmt'] === '' && $resolved['event_start_local'] !== '') {
            $resolved['event_start_gmt'] = (string) get_gmt_from_date($resolved['event_start_local'], 'Y-m-d H:i:s');
        }
        if ($resolved['resolution_source'] === '' && !empty($resolved['tec_event_id'])) {
            $resolved['resolution_source'] = 'ticket_post_event_link';
            $resolved['resolution_confidence'] = 'exact';
        }

        $product_cache[$product_id] = $resolved;
        return $resolved;
    }
}

if (!function_exists('vms_ticket_revenue_order_item_candidate_product_ids')) {
    function vms_ticket_revenue_order_item_candidate_product_ids($item): array
    {
        $ids = array();
        if (!$item || !is_object($item)) {
            return $ids;
        }

        $variation_id = method_exists($item, 'get_variation_id') ? absint($item->get_variation_id()) : 0;
        $product_id = method_exists($item, 'get_product_id') ? absint($item->get_product_id()) : 0;

        foreach (array($variation_id, $product_id) as $id) {
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        if ($variation_id > 0) {
            $parent_id = absint(wp_get_post_parent_id($variation_id));
            if ($parent_id > 0) {
                $ids[] = $parent_id;
            }
        }

        return array_values(array_unique(array_filter(array_map('absint', $ids))));
    }
}

if (!function_exists('vms_ticket_revenue_get_item_meta_first')) {
    function vms_ticket_revenue_get_item_meta_first($item, array $keys): string
    {
        if (!$item || !is_object($item) || !method_exists($item, 'get_meta')) {
            return '';
        }

        foreach ($keys as $key) {
            $value = $item->get_meta($key, true);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }
}

if (!function_exists('vms_ticket_revenue_resolve_event_for_order_item')) {
    function vms_ticket_revenue_resolve_event_for_order_item($item, array &$product_cache, array &$event_cache): array
    {
        $candidate_product_ids = vms_ticket_revenue_order_item_candidate_product_ids($item);
        $primary_product_id = !empty($candidate_product_ids) ? (int) $candidate_product_ids[0] : 0;

        $resolved = array(
            'product_id' => $primary_product_id,
            'product_sku' => '',
            'item_kind' => 'ticket',
            'tec_event_id' => 0,
            'event_title' => '',
            'event_slug' => '',
            'event_date' => '',
            'event_plan_id' => 0,
            'event_plan_title' => '',
            'resolution_source' => '',
        );

        foreach ($candidate_product_ids as $candidate_id) {
            $ctx = vms_ticket_revenue_product_context($candidate_id, $product_cache, $event_cache);
            if ($resolved['product_id'] <= 0 && !empty($ctx['product_id'])) {
                $resolved['product_id'] = (int) $ctx['product_id'];
            }
            if ($resolved['product_sku'] === '' && !empty($ctx['product_sku'])) {
                $resolved['product_sku'] = (string) $ctx['product_sku'];
            }
            if ($resolved['item_kind'] === 'ticket' && !empty($ctx['item_kind'])) {
                $resolved['item_kind'] = (string) $ctx['item_kind'];
            }
            if (!empty($ctx['tec_event_id']) || !empty($ctx['event_plan_id']) || !empty($ctx['event_title']) || !empty($ctx['event_date'])) {
                $resolved = array_merge($resolved, $ctx);
                if ($resolved['resolution_source'] === '') {
                    $resolved['resolution_source'] = (string) ($ctx['resolution_source'] ?? 'product_meta');
                }
                break;
            }
        }

        $item_tec_event_id = absint(vms_ticket_revenue_get_item_meta_first($item, array('_vms_tec_event_post_id')));
        if ($item_tec_event_id > 0) {
            $resolved = array_merge($resolved, vms_ticket_revenue_event_payload_for_tec_event($item_tec_event_id, $event_cache));
            $resolved['resolution_source'] = 'order_item_meta';
        }

        $item_plan_id = absint(vms_ticket_revenue_get_item_meta_first($item, array('_vms_event_plan_id')));
        if ($item_plan_id > 0) {
            $resolved['event_plan_id'] = $item_plan_id;
            if ($resolved['event_plan_title'] === '') {
                $resolved['event_plan_title'] = (string) get_the_title($item_plan_id);
            }
            $plan_date = vms_ticket_revenue_normalize_ymd((string) get_post_meta($item_plan_id, '_vms_event_date', true));
            if ($resolved['event_date'] === '' && $plan_date !== '') {
                $resolved['event_date'] = $plan_date;
            }
            if ($resolved['tec_event_id'] <= 0) {
                $plan_event_id = absint(get_post_meta($item_plan_id, vms_ticket_revenue_plan_tec_meta_key(), true));
                if ($plan_event_id > 0) {
                    $resolved = array_merge($resolved, vms_ticket_revenue_event_payload_for_tec_event($plan_event_id, $event_cache));
                }
            }
            if ($resolved['resolution_source'] === '') {
                $resolved['resolution_source'] = 'order_item_plan';
            }
        }

        $snapshot_title = vms_ticket_revenue_get_item_meta_first($item, array('_vms_event_title_snapshot', 'Event'));
        $snapshot_date = vms_ticket_revenue_normalize_ymd(vms_ticket_revenue_get_item_meta_first($item, array('_vms_event_date_snapshot', 'Event Date')));
        if ($snapshot_title !== '') {
            $resolved['event_title'] = $snapshot_title;
            if ($resolved['resolution_source'] === '') {
                $resolved['resolution_source'] = 'order_item_snapshot';
            }
        }
        if ($snapshot_date !== '') {
            $resolved['event_date'] = $snapshot_date;
            if ($resolved['resolution_source'] === '') {
                $resolved['resolution_source'] = 'order_item_snapshot';
            }
        }

        if ($resolved['event_plan_id'] > 0 && $resolved['event_plan_title'] === '') {
            $resolved['event_plan_title'] = (string) get_the_title((int) $resolved['event_plan_id']);
        }
        if ($resolved['product_id'] > 0 && $resolved['product_sku'] === '') {
            $ctx = vms_ticket_revenue_product_context((int) $resolved['product_id'], $product_cache, $event_cache);
            if (!empty($ctx['product_sku'])) {
                $resolved['product_sku'] = (string) $ctx['product_sku'];
            }
            if (!empty($ctx['item_kind'])) {
                $resolved['item_kind'] = (string) $ctx['item_kind'];
            }
        }

        return $resolved;
    }
}

if (!function_exists('vms_ticket_revenue_recognition_for_date')) {
    function vms_ticket_revenue_recognition_for_date(string $event_date, string $as_of_date): string
    {
        if (!vms_ticket_revenue_is_valid_ymd($event_date) || !vms_ticket_revenue_is_valid_ymd($as_of_date)) {
            return 'unknown';
        }

        return ($event_date < $as_of_date) ? 'earned' : 'deferred';
    }
}

if (!function_exists('vms_ticket_revenue_filter_match')) {
    function vms_ticket_revenue_filter_match(array $row, array $args): bool
    {
        $event_date = (string) ($row['event_date'] ?? '');
        $plan_id = (int) ($row['event_plan_id'] ?? 0);
        $tec_event_id = (int) ($row['tec_event_id'] ?? 0);
        $recognition = (string) ($row['recognition_status'] ?? 'unknown');

        if (!empty($args['event_plan_id']) && (int) $args['event_plan_id'] !== $plan_id) {
            return false;
        }
        if (!empty($args['tec_event_id']) && (int) $args['tec_event_id'] !== $tec_event_id) {
            return false;
        }
        if (!empty($args['event_from']) && (!vms_ticket_revenue_is_valid_ymd($event_date) || $event_date < $args['event_from'])) {
            return false;
        }
        if (!empty($args['event_to']) && (!vms_ticket_revenue_is_valid_ymd($event_date) || $event_date > $args['event_to'])) {
            return false;
        }
        if (($args['recognition_status'] ?? 'all') !== 'all' && $recognition !== (string) $args['recognition_status']) {
            return false;
        }

        return true;
    }
}

if (!function_exists('vms_ticket_revenue_event_key')) {
    function vms_ticket_revenue_event_key(array $row): string
    {
        $plan_id = (int) ($row['event_plan_id'] ?? 0);
        $tec_event_id = (int) ($row['tec_event_id'] ?? 0);
        if ($plan_id > 0) {
            return 'plan:' . $plan_id;
        }
        if ($tec_event_id > 0) {
            return 'tec:' . $tec_event_id;
        }
        return 'snapshot:' . md5((string) ($row['event_title'] ?? '') . '|' . (string) ($row['event_date'] ?? ''));
    }
}

if (!function_exists('vms_ticket_revenue_is_resolved_event_link')) {
    function vms_ticket_revenue_is_resolved_event_link(array $event): bool
    {
        return !empty($event['tec_event_id'])
            || !empty($event['event_plan_id'])
            || trim((string) ($event['event_title'] ?? '')) !== ''
            || trim((string) ($event['event_date'] ?? '')) !== '';
    }
}

if (!function_exists('vms_ticket_revenue_unresolved_reason')) {
    function vms_ticket_revenue_unresolved_reason(array $event, array $candidate_product_ids): string
    {
        if (empty($candidate_product_ids)) {
            return 'No Woo product/variation ID on order line.';
        }
        if (!empty($event['product_id']) && empty($event['tec_event_id']) && empty($event['event_plan_id']) && empty($event['event_title']) && empty($event['event_date'])) {
            return 'Product resolved, but no TEC event, Event Plan, or event snapshots were found.';
        }
        return 'Could not resolve event linkage from product meta or order-item snapshots.';
    }
}

if (!function_exists('vms_ticket_revenue_build_unresolved_row')) {
    function vms_ticket_revenue_build_unresolved_row($order, $item, array $event, array $candidate_product_ids, string $sold_date, string $sold_datetime): array
    {
        $customer_name = is_object($order) && method_exists($order, 'get_formatted_billing_full_name')
            ? trim((string) $order->get_formatted_billing_full_name())
            : '';
        $customer_email = is_object($order) && method_exists($order, 'get_billing_email')
            ? (string) $order->get_billing_email()
            : '';

        return array(
            'order_id' => is_object($order) && method_exists($order, 'get_id') ? (int) $order->get_id() : 0,
            'order_number' => is_object($order) && method_exists($order, 'get_order_number') ? (string) $order->get_order_number() : '',
            'order_status' => is_object($order) && method_exists($order, 'get_status') ? sanitize_key((string) $order->get_status()) : '',
            'sold_date' => $sold_date,
            'sold_datetime' => $sold_datetime,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'line_item_id' => is_object($item) && method_exists($item, 'get_id') ? (int) $item->get_id() : 0,
            'item_name' => is_object($item) && method_exists($item, 'get_name') ? (string) $item->get_name() : '',
            'quantity' => is_object($item) && method_exists($item, 'get_quantity') ? (int) $item->get_quantity() : 0,
            'candidate_product_ids' => implode(', ', $candidate_product_ids),
            'product_id' => (int) ($event['product_id'] ?? 0),
            'product_sku' => (string) ($event['product_sku'] ?? ''),
            'event_title_snapshot' => vms_ticket_revenue_get_item_meta_first($item, array('_vms_event_title_snapshot', 'Event')),
            'event_date_snapshot' => vms_ticket_revenue_normalize_ymd(vms_ticket_revenue_get_item_meta_first($item, array('_vms_event_date_snapshot', 'Event Date'))),
            'item_tec_event_id' => absint(vms_ticket_revenue_get_item_meta_first($item, array('_vms_tec_event_post_id'))),
            'item_event_plan_id' => absint(vms_ticket_revenue_get_item_meta_first($item, array('_vms_event_plan_id'))),
            'resolution_source' => (string) ($event['resolution_source'] ?? ''),
            'reason' => vms_ticket_revenue_unresolved_reason($event, $candidate_product_ids),
        );
    }
}

if (!function_exists('vms_ticket_revenue_build_report')) {
    function vms_ticket_revenue_build_report(array $args = array()): array
    {
        $args = vms_ticket_revenue_normalize_args($args);

        $resolver_args = array(
            'date_from' => (string) ($args['sold_from'] ?? ''),
            'date_to' => (string) ($args['sold_to'] ?? ''),
            'order_statuses' => (array) ($args['order_statuses'] ?? array()),
            'event_ids' => !empty($args['tec_event_id']) ? array((int) $args['tec_event_id']) : array(),
            'event_plan_ids' => !empty($args['event_plan_id']) ? array((int) $args['event_plan_id']) : array(),
            'include_unresolved' => true,
        );

        $resolver_result = class_exists('VMS_Ticket_Revenue_Service')
            ? VMS_Ticket_Revenue_Service::get_sales_result($resolver_args)
            : array(
                'rows' => array(),
                'warnings' => array(__('Ticket sales resolver is unavailable.', 'vms')),
                'counts' => array(),
            );

        $result = array(
            'args' => $args,
            'rows' => array(),
            'summary' => array(
                'order_count' => 0,
                'line_count' => 0,
                'gross_subtotal_cents' => 0,
                'discount_cents' => 0,
                'net_subtotal_cents' => 0,
                'tax_cents' => 0,
                'refunded_subtotal_cents' => 0,
                'refunded_tax_cents' => 0,
                'cash_total_cents' => 0,
                'earned_cents' => 0,
                'deferred_cents' => 0,
                'unknown_cents' => 0,
            ),
            'event_summary' => array(),
            'warnings' => (array) ($resolver_result['warnings'] ?? array()),
            'unresolved_rows' => array(),
            'counts' => array(
                'orders_scanned' => (int) ($resolver_result['counts']['orders_scanned'] ?? 0),
                'orders_with_event_lines' => 0,
                'line_items_scanned' => (int) ($resolver_result['counts']['line_items_scanned'] ?? 0),
                'line_items_exported' => 0,
                'line_items_skipped_unlinked' => 0,
                'line_items_skipped_filtered' => (int) ($resolver_result['counts']['line_items_skipped_filtered'] ?? 0),
                'line_items_skipped_no_product' => 0,
                'line_items_skipped_unresolved_event' => 0,
                'unresolved_rows_captured' => 0,
            ),
        );

        if (!class_exists('VMS_Ticket_Revenue_Service')) {
            return $result;
        }

        $seen_order_ids = array();
        foreach ((array) ($resolver_result['rows'] ?? array()) as $sales_row) {
            if ((string) ($sales_row['resolution_confidence'] ?? '') === 'unresolved') {
                $result['counts']['line_items_skipped_unlinked']++;

                $snapshot = is_array($sales_row['raw_linkage_snapshot'] ?? null) ? $sales_row['raw_linkage_snapshot'] : array();
                $candidate_product_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($snapshot['candidate_product_ids'] ?? array())))));
                if (empty($candidate_product_ids) && (int) ($sales_row['product_id'] ?? 0) <= 0) {
                    $result['counts']['line_items_skipped_no_product']++;
                } else {
                    $result['counts']['line_items_skipped_unresolved_event']++;
                }

                if (count($result['unresolved_rows']) < (int) ($args['unresolved_limit'] ?? 150)) {
                    $result['unresolved_rows'][] = $sales_row;
                    $result['counts']['unresolved_rows_captured']++;
                }

                continue;
            }

            $net_after_refund_subtotal_cents = max(
                0,
                (int) ($sales_row['line_net_subtotal_cents'] ?? 0) - (int) ($sales_row['line_refunded_total_cents'] ?? 0)
            );
            $net_after_refund_tax_cents = max(
                0,
                (int) ($sales_row['line_tax_total_cents'] ?? 0) - (int) ($sales_row['line_refunded_tax_total_cents'] ?? 0)
            );
            $cash_total_cents = $net_after_refund_subtotal_cents + $net_after_refund_tax_cents;
            $recognition_status = vms_ticket_revenue_recognition_for_date((string) ($sales_row['event_date'] ?? ''), (string) $args['as_of_date']);

            $row = array(
                'order_id' => (int) ($sales_row['order_id'] ?? 0),
                'order_number' => (string) ($sales_row['order_number'] ?? ''),
                'order_status' => sanitize_key((string) ($sales_row['order_status_slug'] ?? '')),
                'order_currency' => (string) ($sales_row['currency'] ?? ''),
                'sold_date' => substr((string) ($sales_row['order_date_local'] ?? ''), 0, 10),
                'sold_datetime' => (string) ($sales_row['order_date_local'] ?? ''),
                'payment_method' => (string) (($sales_row['payment_method_title'] ?? '') !== '' ? $sales_row['payment_method_title'] : ($sales_row['payment_method'] ?? '')),
                'customer_name' => (string) ($sales_row['customer_name'] ?? ''),
                'customer_email' => (string) ($sales_row['customer_email'] ?? ''),
                'line_item_id' => (int) ($sales_row['order_item_id'] ?? 0),
                'product_id' => (int) ($sales_row['product_id'] ?? 0),
                'product_sku' => (string) ($sales_row['product_sku'] ?? ''),
                'item_kind' => (string) ($sales_row['line_kind'] ?? 'ticket'),
                'item_name' => (string) ($sales_row['product_name'] ?? ''),
                'quantity' => (int) ($sales_row['qty'] ?? 0),
                'refunded_quantity' => (int) ($sales_row['refunded_qty'] ?? 0),
                'gross_subtotal_cents' => (int) ($sales_row['line_subtotal_cents'] ?? 0),
                'discount_cents' => (int) ($sales_row['line_discount_total_cents'] ?? 0),
                'discount_tax_cents' => (int) ($sales_row['line_discount_tax_total_cents'] ?? 0),
                'net_subtotal_cents' => $net_after_refund_subtotal_cents,
                'tax_cents' => $net_after_refund_tax_cents,
                'refunded_subtotal_cents' => (int) ($sales_row['line_refunded_total_cents'] ?? 0),
                'refunded_tax_cents' => (int) ($sales_row['line_refunded_tax_total_cents'] ?? 0),
                'cash_total_cents' => $cash_total_cents,
                'tec_event_id' => (int) ($sales_row['event_id'] ?? 0),
                'event_title' => (string) ($sales_row['event_title'] ?? ''),
                'event_slug' => (string) ($sales_row['event_slug'] ?? ''),
                'event_date' => (string) ($sales_row['event_date'] ?? ''),
                'event_plan_id' => (int) ($sales_row['event_plan_id'] ?? 0),
                'event_plan_title' => (string) ($sales_row['event_plan_title'] ?? ''),
                'recognition_status' => $recognition_status,
                'recognition_as_of_date' => (string) $args['as_of_date'],
                'resolution_source' => (string) ($sales_row['resolution_source'] ?? ''),
            );

            if (!vms_ticket_revenue_filter_match($row, $args)) {
                $result['counts']['line_items_skipped_filtered']++;
                continue;
            }

            $order_id = (int) $row['order_id'];
            $seen_order_ids[$order_id] = true;
            $result['counts']['line_items_exported']++;
            $result['summary']['line_count']++;
            $result['summary']['gross_subtotal_cents'] += (int) $row['gross_subtotal_cents'];
            $result['summary']['discount_cents'] += (int) $row['discount_cents'];
            $result['summary']['net_subtotal_cents'] += (int) $row['net_subtotal_cents'];
            $result['summary']['tax_cents'] += (int) $row['tax_cents'];
            $result['summary']['refunded_subtotal_cents'] += (int) $row['refunded_subtotal_cents'];
            $result['summary']['refunded_tax_cents'] += (int) $row['refunded_tax_cents'];
            $result['summary']['cash_total_cents'] += (int) $row['cash_total_cents'];

            $bucket_key = $recognition_status . '_cents';
            if (!isset($result['summary'][$bucket_key])) {
                $bucket_key = 'unknown_cents';
            }
            $result['summary'][$bucket_key] += (int) $row['cash_total_cents'];
            $result['rows'][] = $row;

            $event_key = vms_ticket_revenue_event_key($row);
            if (!isset($result['event_summary'][$event_key])) {
                $result['event_summary'][$event_key] = array(
                    'event_plan_id' => (int) $row['event_plan_id'],
                    'event_plan_title' => (string) $row['event_plan_title'],
                    'tec_event_id' => (int) $row['tec_event_id'],
                    'event_title' => (string) $row['event_title'],
                    'event_slug' => (string) $row['event_slug'],
                    'event_date' => (string) $row['event_date'],
                    'recognition_status' => (string) $row['recognition_status'],
                    'line_count' => 0,
                    'order_count' => 0,
                    'gross_subtotal_cents' => 0,
                    'discount_cents' => 0,
                    'net_subtotal_cents' => 0,
                    'tax_cents' => 0,
                    'refunded_subtotal_cents' => 0,
                    'refunded_tax_cents' => 0,
                    'cash_total_cents' => 0,
                    '_orders' => array(),
                );
            }

            $result['event_summary'][$event_key]['line_count']++;
            $result['event_summary'][$event_key]['gross_subtotal_cents'] += (int) $row['gross_subtotal_cents'];
            $result['event_summary'][$event_key]['discount_cents'] += (int) $row['discount_cents'];
            $result['event_summary'][$event_key]['net_subtotal_cents'] += (int) $row['net_subtotal_cents'];
            $result['event_summary'][$event_key]['tax_cents'] += (int) $row['tax_cents'];
            $result['event_summary'][$event_key]['refunded_subtotal_cents'] += (int) $row['refunded_subtotal_cents'];
            $result['event_summary'][$event_key]['refunded_tax_cents'] += (int) $row['refunded_tax_cents'];
            $result['event_summary'][$event_key]['cash_total_cents'] += (int) $row['cash_total_cents'];
            $result['event_summary'][$event_key]['_orders'][$order_id] = true;
        }

        $result['summary']['order_count'] = count($seen_order_ids);
        $result['counts']['orders_with_event_lines'] = count($seen_order_ids);

        foreach ($result['event_summary'] as $key => $row) {
            $result['event_summary'][$key]['order_count'] = count((array) ($row['_orders'] ?? array()));
            unset($result['event_summary'][$key]['_orders']);
        }

        usort($result['rows'], static function (array $a, array $b): int {
            $cmp = strcmp((string) ($a['sold_datetime'] ?? ''), (string) ($b['sold_datetime'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = strcmp((string) ($a['event_date'] ?? ''), (string) ($b['event_date'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            return ((int) ($a['order_id'] ?? 0)) <=> ((int) ($b['order_id'] ?? 0));
        });

        uasort($result['event_summary'], static function (array $a, array $b): int {
            $cmp = strcmp((string) ($a['event_date'] ?? ''), (string) ($b['event_date'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp((string) ($a['event_plan_title'] ?: $a['event_title'] ?? ''), (string) ($b['event_plan_title'] ?: $b['event_title'] ?? ''));
        });

        return $result;
    }
}
