<?php
/** Shared ticket reporting adapter, extracted from the accepted P0/ECC resolver. */
defined('ABSPATH') || exit;

function bvmgr_financial_request_cache(int $event_plan_id, string $bucket, callable $resolver)
{
    static $cache = array();
    $key = $event_plan_id . ':' . $bucket;
    if (!array_key_exists($key, $cache)) { $cache[$key] = $resolver(); }
    return $cache[$key];
}

if (!function_exists('bvmgr_reporting_summarize_ticket_rows')) {
    /** Summarize net paid/free ticket rows after refunds, excluding add-ons. */
    function bvmgr_reporting_summarize_ticket_rows(array $rows): array
    {
        $paid_qty = 0;
        $free_qty = 0;
        $revenue_cents = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item_kind = sanitize_key((string) ($row['item_kind'] ?? 'ticket'));
            if (in_array($item_kind, array('addon', 'entitlement'), true)) {
                continue;
            }
            $qty = max(0, (int) ($row['quantity'] ?? 0));
            $refunded_qty = max(0, (int) ($row['refunded_quantity'] ?? 0));
            $net_qty = max(0, $qty - $refunded_qty);
            if ($net_qty <= 0) {
                continue;
            }
            $net_cents = max(0, (int) ($row['net_subtotal_cents'] ?? 0));
            $revenue_cents += $net_cents;
            if ($net_cents > 0) {
                $paid_qty += $net_qty;
            } else {
                $free_qty += $net_qty;
            }
        }

        return array(
            'paid_qty' => $paid_qty,
            'free_qty' => $free_qty,
            'total_qty' => $paid_qty + $free_qty,
            'revenue_cents' => $revenue_cents,
        );
    }
}

if (!function_exists('bvmgr_reporting_get_ticket_truth')) {
    /**
     * Resolve the best available ticket-sales truth for Event Command Center.
     *
     * Preference order:
     * 1. Data Tools reporting model when active, because it can combine website + door/onsite ticket sales.
     * 2. Core VMS ticket revenue rows, because they read Woo order lines directly and avoid stale ticket_stats cache.
     * 3. Existing cached goal/ticket stats as a last-resort fallback in the caller.
     */
    function bvmgr_reporting_get_ticket_truth(int $plan_id): array
    {
        return (array) bvmgr_financial_request_cache($plan_id, 'ticket_reporting_truth', static function () use ($plan_id): array {
            $plan_id = absint($plan_id);
            if (function_exists('bvmgr_resource_fingerprint_flag')) {
                bvmgr_resource_fingerprint_flag('ecc_calculation', array(
                    'plan_id' => $plan_id,
                    'step' => 'ticket_reporting_truth',
                ));
            }
            if (function_exists('bvmgr_resource_fingerprint_span_start')) {
                bvmgr_resource_fingerprint_span_start('ecc.ticket_reporting_truth', array('plan_id' => $plan_id));
            }
            $empty = array(
                'available' => false,
                'calculated' => false,
                'source' => '',
                'source_label' => '',
                'paid_qty' => 0,
                'free_qty' => 0,
                'total_qty' => 0,
                'revenue_cents' => 0,
                'warnings' => array(),
            );

            try {
                if ($plan_id <= 0) {
                    return $empty;
                }

                if (function_exists('bvmgr_reporting_resolve_event_ticket_sales')) {
                    $provider_result = bvmgr_reporting_resolve_event_ticket_sales($plan_id, array(
                        'scope' => 'event_command_center',
                    ));
                    if (!empty($provider_result['available']) && !empty($provider_result['calculated']) && !bvmgr_financial_source_is_stale((array) ($provider_result['freshness'] ?? array()))) {
                        if (function_exists('bvmgr_resource_fingerprint_add_marker')) {
                            bvmgr_resource_fingerprint_add_marker('ecc.ticket_source.reporting_provider', 0.0, array(
                                'plan_id' => $plan_id,
                                'provider_id' => (string) ($provider_result['provider_id'] ?? ''),
                                'provider_version' => (string) ($provider_result['provider_version'] ?? ''),
                            ));
                        }
                        return array(
                            'available' => true,
                            'calculated' => true,
                            'source' => sanitize_key((string) ($provider_result['source'] ?? '')),
                            'source_label' => (string) ($provider_result['source_label'] ?? ''),
                            'provider_id' => sanitize_key((string) ($provider_result['provider_id'] ?? '')),
                            'provider_version' => (string) ($provider_result['provider_version'] ?? ''),
                            'provider_contract_version' => (int) ($provider_result['provider_contract_version'] ?? 0),
                            'freshness' => (array) ($provider_result['freshness'] ?? array()),
                            'updated_label' => (string) ($provider_result['updated_label'] ?? ''),
                            'paid_qty' => max(0, (int) ($provider_result['paid_qty'] ?? 0)),
                            'free_qty' => max(0, (int) ($provider_result['free_qty'] ?? 0)),
                            'total_qty' => max(0, (int) ($provider_result['total_qty'] ?? 0)),
                            'revenue_cents' => max(0, (int) ($provider_result['revenue_cents'] ?? 0)),
                            'warnings' => array_values(array_unique(array_filter(array_merge(
                                (array) ($provider_result['warnings'] ?? array()),
                                (array) ($provider_result['errors'] ?? array())
                            )))),
                        );
                    }

                    if (bvmgr_financial_source_is_stale((array) ($provider_result['freshness'] ?? array()))) {
                        $empty['warnings'][] = __('Stale provider result excluded; using available fallback evidence.', 'backstage-venue-manager');
                    }

                    $empty['warnings'] = array_values(array_unique(array_filter(array_merge(
                        (array) ($empty['warnings'] ?? array()),
                        (array) ($provider_result['warnings'] ?? array()),
                        (array) ($provider_result['errors'] ?? array())
                    ))));
                }

                if (function_exists('bvmgr_ticket_revenue_build_report') && class_exists('BVMGR_Ticket_Revenue_Service') && class_exists('WooCommerce') && function_exists('wc_get_orders')) {
                    try {
                        $report = (array) bvmgr_ticket_revenue_build_report(array(
                            'event_from' => '',
                            'event_to' => '',
                            'sold_from' => '',
                            'sold_to' => '',
                            'event_plan_id' => $plan_id,
                            'recognition_status' => 'all',
                            'preview_limit' => 500,
                            'unresolved_limit' => 100,
                        ));
                        if (function_exists('bvmgr_resource_fingerprint_add_marker')) {
                            bvmgr_resource_fingerprint_add_marker('ecc.ticket_source.core_ticket_revenue', 0.0, array('plan_id' => $plan_id));
                        }

                        $row_totals = bvmgr_reporting_summarize_ticket_rows((array) ($report['rows'] ?? array()));
                        $paid_qty = (int) $row_totals['paid_qty'];
                        $free_qty = (int) $row_totals['free_qty'];
                        $revenue_cents = (int) $row_totals['revenue_cents'];
                        $total_qty = (int) $row_totals['total_qty'];
                        if (array_key_exists('rows', $report)) {
                            return array(
                                'available' => true,
                                'calculated' => true,
                                'source' => 'core_ticket_revenue',
                                'source_label' => __('VMS ticket revenue rows', 'backstage-venue-manager'),
                                'paid_qty' => $paid_qty,
                                'free_qty' => $free_qty,
                                'total_qty' => $total_qty,
                                'revenue_cents' => $revenue_cents,
                                'warnings' => array_values(array_unique(array_filter(array_merge(
                                    (array) ($empty['warnings'] ?? array()),
                                    (array) ($report['warnings'] ?? array())
                                )))),
                            );
                        }
                    } catch (Throwable $e) {
                        /* translators: %s: exception message from ticket revenue reporting lookup. */
                        $empty['warnings'][] = sprintf(__('Ticket revenue rows could not be read: %s', 'backstage-venue-manager'), $e->getMessage());
                    }
                }

                return $empty;
            } finally {
                if (function_exists('bvmgr_resource_fingerprint_span_finish')) {
                    bvmgr_resource_fingerprint_span_finish('ecc.ticket_reporting_truth', array('plan_id' => $plan_id));
                }
            }
        });
    }
}

if (!function_exists('bvmgr_reporting_normalize_ticket_cache')) {
    /** Interpret cached sales without converting absent or pending values into zero. */
    function bvmgr_reporting_normalize_ticket_cache(array $ticket_stats, array $ticketing_stats_v2 = array(), int $now = 0, int $stale_after = 0): array
    {
        $now = $now > 0 ? $now : time();
        $stale_after = $stale_after > 0 ? $stale_after : (12 * 60 * 60);
        $provider = sanitize_key((string) ($ticket_stats['provider'] ?? ''));
        $v2_provider = sanitize_key((string) ($ticketing_stats_v2['provider'] ?? ''));

        $qty = null;
        if (array_key_exists('qty_sold', $ticket_stats) && is_numeric($ticket_stats['qty_sold'])) {
            $qty = max(0, (int) $ticket_stats['qty_sold']);
        } elseif (array_key_exists('qty', $ticket_stats) && is_numeric($ticket_stats['qty'])) {
            $qty = max(0, (int) $ticket_stats['qty']);
        }

        $revenue_cents = null;
        if (array_key_exists('revenue_cents', $ticket_stats) && is_numeric($ticket_stats['revenue_cents'])) {
            $revenue_cents = max(0, (int) $ticket_stats['revenue_cents']);
        } elseif (array_key_exists('revenue', $ticket_stats) && is_numeric($ticket_stats['revenue'])) {
            $revenue_cents = max(0, (int) round(((float) $ticket_stats['revenue']) * 100));
        }

        $computed_at_gmt = 0;
        foreach (array('computed_at_gmt', 'updated_at_gmt', 'pulled_at_gmt', 'computed_at') as $stamp_key) {
            if (!array_key_exists($stamp_key, $ticket_stats)) {
                continue;
            }
            $raw_stamp = $ticket_stats[$stamp_key];
            $stamp = is_numeric($raw_stamp) ? (int) $raw_stamp : strtotime((string) $raw_stamp . ' UTC');
            if ($stamp !== false) {
                $computed_at_gmt = max($computed_at_gmt, (int) $stamp);
            }
        }

        $v2_computed_at_gmt = 0;
        foreach (array('computed_at_gmt', 'updated_at_gmt', 'pulled_at_gmt', 'computed_at') as $stamp_key) {
            if (!array_key_exists($stamp_key, $ticketing_stats_v2)) {
                continue;
            }
            $raw_stamp = $ticketing_stats_v2[$stamp_key];
            $stamp = is_numeric($raw_stamp) ? (int) $raw_stamp : strtotime((string) $raw_stamp . ' UTC');
            if ($stamp !== false) {
                $v2_computed_at_gmt = max($v2_computed_at_gmt, (int) $stamp);
            }
        }

        $has_values = $qty !== null && $revenue_cents !== null;
        $pending = $provider === 'pending_refresh'
            || (!$has_values && $v2_provider === 'pending_refresh');
        if ($pending) {
            $state = 'PENDING_REFRESH';
        } elseif (!$has_values || $computed_at_gmt <= 0) {
            $state = 'UNAVAILABLE';
        } elseif (($now - $computed_at_gmt) > $stale_after) {
            $state = 'STALE';
        } elseif ($qty === 0 && $revenue_cents === 0) {
            $state = 'VALID_ZERO';
        } else {
            $state = 'CURRENT';
        }

        $display_sales = in_array($state, array('CURRENT', 'STALE', 'VALID_ZERO'), true);
        return array(
            'state' => $state,
            'display_sales' => $display_sales,
            'is_current' => in_array($state, array('CURRENT', 'VALID_ZERO'), true),
            'is_stale' => $state === 'STALE',
            'is_pending_refresh' => $state === 'PENDING_REFRESH',
            'is_valid_zero' => $state === 'VALID_ZERO',
            'sold' => $display_sales ? $qty : null,
            'revenue_cents' => $display_sales ? $revenue_cents : null,
            'provider' => $provider,
            'stats_computed_at_gmt' => $computed_at_gmt,
            'mapping_computed_at_gmt' => $v2_computed_at_gmt,
            'warnings' => array_values(array_filter(array_merge(
                (array) ($ticket_stats['warnings'] ?? array()),
                (array) ($ticketing_stats_v2['warnings'] ?? array())
            ))),
        );
    }
}
