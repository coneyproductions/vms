<?php
/**
 * Payables Export (Core, provider-agnostic)
 *
 * v1 scope:
 * - Build a normalized "bill" model from Event Plans for a given date + venue(s).
 * - Designed to be consumed by exporter adapters (e.g., QBO Bills CSV in Data Tools).
 */

defined('ABSPATH') || exit;

/**
 * Convert a money-ish string into a float.
 * Accepts: "125", "125.00", "$1,250.50", " 1 250.50 "
 */
function vms_payables_sanitize_amount($raw): float
{
    $raw = is_scalar($raw) ? (string) $raw : '';
    $raw = trim($raw);

    if ($raw === '') {
        return 0.0;
    }

    // Keep digits, minus, dot.
    $raw = preg_replace('/[^0-9\-\.]/', '', $raw);
    if ($raw === '' || $raw === '-' || $raw === '.') {
        return 0.0;
    }

    return (float) $raw;
}

/**
 * Resolve a vendor name to use as the "payee"/"supplier" name for accounting exports.
 * v1 resolution:
 * 1) Vendor DBA (_vms_payee_dba)
 * 2) Vendor legal name (_vms_payee_legal_name)
 * 3) Vendor post_title
 */
function vms_payables_resolve_vendor_payee_name(int $vendor_id): string
{
    $vendor_id = (int) $vendor_id;
    if ($vendor_id <= 0) {
        return '';
    }

    $k_dba   = function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'payee_dba') : '_vms_payee_dba';
    $k_legal = function_exists('vms_meta_key') ? (string) vms_meta_key('vendor', 'payee_legal_name') : '_vms_payee_legal_name';

    $dba = (string) get_post_meta($vendor_id, $k_dba, true);
    $dba = trim($dba);

    if ($dba !== '') {
        return $dba;
    }

    $legal = (string) get_post_meta($vendor_id, $k_legal, true);
    $legal = trim($legal);

    if ($legal !== '') {
        return $legal;
    }

    $title = (string) get_the_title($vendor_id);
    return trim($title);
}

/**
 * Build a deterministic bill number for a vendor/date/venue.
 * Format (v1):
 *   VMS-{venue_slug}-{YYYYMMDD}-{vendor_id}
 */
function vms_payables_build_bill_no(string $event_date, int $venue_id, int $vendor_id): string
{
    $venue_id  = (int) $venue_id;
    $vendor_id = (int) $vendor_id;

    $venue_slug = '';
    if ($venue_id > 0) {
        $venue_slug = (string) get_post_field('post_name', $venue_id);
        $venue_slug = sanitize_title($venue_slug);
    }

    $ymd = preg_replace('/[^0-9]/', '', (string) $event_date); // supports YYYY-MM-DD → YYYYMMDD
    if (strlen($ymd) !== 8) {
        // fallback: today
        $ymd = gmdate('Ymd');
    }

    if ($venue_slug === '') {
        $venue_slug = 'venue';
    }

    return 'VMS-' . $venue_slug . '-' . $ymd . '-' . $vendor_id;
}

/**
 * Add days to a YYYY-MM-DD date. Returns YYYY-MM-DD or '' on failure.
 */
function vms_payables_add_days(string $ymd, int $days): string
{
    $ymd  = trim((string) $ymd);
    $days = (int) $days;

    if ($ymd === '') {
        return '';
    }

    $utc = new DateTimeZone('UTC');
    try {
        $date = new DateTimeImmutable($ymd . ' 00:00:00', $utc);
    } catch (Exception $exception) {
        return '';
    }
    $date = $date->setTimezone($utc);

    if ($date->getTimestamp() === 0) {
        return '';
    }

    if ($days !== 0) {
        $date = $date->modify(($days >= 0 ? '+' : '') . $days . ' days');
        if (!$date instanceof DateTimeImmutable) {
            return '';
        }
    }

    return $date->format('Y-m-d');
}

/**
 * Core: Build normalized bills from Event Plans for a given event date and venue list.
 *
 * @param string $event_date   YYYY-MM-DD
 * @param array  $venue_ids    int[]
 * @param array  $args {
 *   @type int    $terms_days      Optional. Adds days to due date (default 0).
 *   @type array  $status_allow    Optional. Allowed plan_status values (default ['ready','published']).
 *   @type bool   $include_zero    Optional. Include $0 lines (default false).
 * }
 *
 * @return array { 'bills' => array, 'warnings' => array }
 * Bills model:
 *  [
 *    'vendor_id'   => int,
 *    'venue_id'    => int,
 *    'event_date'  => 'YYYY-MM-DD',
 *    'bill_no'     => string,
 *    'supplier'    => string,
 *    'bill_date'   => 'YYYY-MM-DD',
 *    'due_date'    => 'YYYY-MM-DD',
 *    'lines'       => [
 *        [ 'plan_id' => int, 'amount' => float, 'description' => string, 'structure' => string ],
 *        ...
 *    ],
 *  ]
 */
function vms_payables_build_bills_for_export(string $event_date, array $venue_ids, array $args = []): array
{
    $event_date = trim((string) $event_date);

    $venue_ids = array_map('intval', (array) $venue_ids);
    $venue_ids = array_values(array_filter($venue_ids, function ($v) { return $v > 0; }));

    $terms_days   = isset($args['terms_days']) ? (int) $args['terms_days'] : 0;
    $status_allow = isset($args['status_allow']) ? (array) $args['status_allow'] : ['published'];
    $include_zero = !empty($args['include_zero']);
    $include_tax_incomplete = !empty($args['include_tax_incomplete']);

    if (function_exists('vms_event_plan_allowed_statuses')) {
        $all = vms_event_plan_allowed_statuses('payables_export', array(
            'include_drafts' => true,
            'include_cancelled' => false,
            'include_archived' => false,
        ));
        $status_allow = array_values(array_intersect(array_map('sanitize_key', (array) $status_allow), (array) $all));
        if (empty($status_allow)) {
            $status_allow = array('published');
        }
    }

    $warnings = [];

    if ($event_date === '' || empty($venue_ids)) {
        return [
            'bills'    => [],
            'warnings' => ['Missing event date and/or venues.'],
        ];
    }

    if (!defined('BVMGR_CPT_EVENT_PLAN')) {
        define('BVMGR_CPT_EVENT_PLAN', 'vms_event_plan');
    }

    $k_date   = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'date') : '_vms_event_date';
    $k_venue  = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'venue_id') : '_vms_venue_id';
    $k_status = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'status') : '_vms_event_plan_status';
    $k_vendor = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'band_vendor_id') : '_vms_band_vendor_id';
    $k_struct = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'comp_structure') : '_vms_comp_structure';
    $k_flat   = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'flat_fee_amount') : '_vms_flat_fee_amount';

    $q = [
        'post_type'      => BVMGR_CPT_EVENT_PLAN,
        'post_status'    => ['publish', 'draft', 'pending', 'private'],
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'orderby'        => 'ID',
        'order'          => 'ASC',
        'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- A payables export must include the complete Event Plan set for one requested date, finite venue list, and allowlisted workflow statuses so no payable line is omitted.
            'relation' => 'AND',
            [
                'key'     => $k_date,
                'value'   => $event_date,
                'compare' => '=',
            ],
            [
                'key'     => $k_venue,
                'value'   => $venue_ids,
                'compare' => 'IN',
                'type'    => 'NUMERIC',
            ],
            [
                'key'     => $k_status,
                'value'   => $status_allow,
                'compare' => 'IN',
            ],
        ],
    ];

    $plan_ids = get_posts($q);
    if (empty($plan_ids)) {
        return [
            'bills'    => [],
            'warnings' => [],
        ];
    }

    $bills = [];

    $append_line = static function (array &$bills, int $plan_id, int $venue_id, string $event_date, int $vendor_id, float $amount, string $structure, string $description, bool $payment_blocked, bool $tax_missing, bool $bypass_active, string $bypass_until, int $terms_days) {
        $supplier = vms_payables_resolve_vendor_payee_name($vendor_id);
        if ($supplier === '') {
            return false;
        }

        $bill_no   = vms_payables_build_bill_no($event_date, $venue_id, $vendor_id);
        $bill_date = $event_date;
        $due_date  = ($terms_days !== 0) ? vms_payables_add_days($event_date, $terms_days) : $event_date;
        $key = $vendor_id . '|' . $venue_id . '|' . $event_date;

        if (!isset($bills[$key])) {
            $bills[$key] = [
                'vendor_id'  => $vendor_id,
                'venue_id'   => $venue_id,
                'event_date' => $event_date,
                'bill_no'    => $bill_no,
                'supplier'   => $supplier,
                'bill_date'  => $bill_date,
                'due_date'   => $due_date,
                'lines'      => [],
                'payment_blocked' => (bool) $payment_blocked,
                'tax_missing' => (bool) $tax_missing,
                'tax_bypass_active' => (bool) $bypass_active,
                'tax_bypass_until' => (string) $bypass_until,
            ];
        }

        $bills[$key]['lines'][] = [
            'plan_id'     => $plan_id,
            'amount'      => $amount,
            'description' => $description,
            'structure'   => $structure,
        ];

        return true;
    };

    foreach ((array) $plan_ids as $plan_id) {
        $plan_id  = (int) $plan_id;
        $venue_id = (int) get_post_meta($plan_id, $k_venue, true);
        $vendor_id = (int) get_post_meta($plan_id, $k_vendor, true);

        if ($venue_id <= 0) {
            $warnings[] = 'Plan #' . $plan_id . ' is missing a venue link; skipped.';
            continue;
        }

        $venue_name = (string) get_the_title($venue_id);
        $plan_title = (string) get_the_title($plan_id);
        $base_desc = 'Event ' . $event_date;
        if (trim($venue_name) !== '') {
            $base_desc .= ' — ' . trim($venue_name);
        }
        if (trim($plan_title) !== '') {
            $base_desc .= ' — ' . trim($plan_title);
        }
        $base_desc .= ' (Plan #' . $plan_id . ')';

        if ($vendor_id > 0) {
            $tax_missing = false;
            if (function_exists('vms_is_vendor_tax_profile_complete')) {
                $tax_missing = !vms_is_vendor_tax_profile_complete((int) $vendor_id);
            }

            $bypass_active = false;
            $bypass_until = '';
            if (function_exists('vms_get_tax_bypass_status')) {
                $st = (array) vms_get_tax_bypass_status((int) $vendor_id);
                $bypass_active = !empty($st['is_active']);
                $bypass_until = isset($st['until']) ? (string) $st['until'] : '';
            }

            $payment_blocked = ($tax_missing && !$bypass_active);

            if ($payment_blocked && empty($include_tax_incomplete)) {
                $vendor_label = vms_payables_resolve_vendor_payee_name($vendor_id);
                $warnings[] = "Excluded bill for '" . $vendor_label . "' (tax profile incomplete). Payments/exports are blocked until resolved or bypass set.";
            } else {
                $structure = trim((string) get_post_meta($plan_id, $k_struct, true));
                $amount = vms_payables_sanitize_amount(get_post_meta($plan_id, $k_flat, true));

                if ($amount <= 0.0 && !$include_zero) {
                    $guaranteed_structures = array('flat_fee', 'flat_fee_door_split', 'attendance_bonus');
                    if ($structure !== '' && !in_array($structure, $guaranteed_structures, true)) {
                        $warnings[] = 'Plan #' . $plan_id . ' uses comp structure "' . $structure . '" (no exportable flat fee); skipped.';
                    } elseif ($structure === 'attendance_bonus') {
                        $warnings[] = 'Plan #' . $plan_id . ' has $0 attendance-bonus base pay; skipped.';
                    } else {
                        $warnings[] = 'Plan #' . $plan_id . ' has $0 flat fee; skipped.';
                    }
                } else {
                    $primary_desc = $base_desc;
                    if (!empty(function_exists('vms_get_event_plan_lineup_primary_entry') ? vms_get_event_plan_lineup_primary_entry($plan_id) : array())) {
                        $primary_desc .= ' — Primary lineup entry';
                    }
                    if (!$append_line($bills, $plan_id, $venue_id, $event_date, $vendor_id, $amount, $structure, $primary_desc, $payment_blocked, $tax_missing, $bypass_active, $bypass_until, $terms_days)) {
                        $warnings[] = 'Vendor #' . $vendor_id . ' has no name; skipped.';
                    }
                }
            }
        } else {
            $warnings[] = 'Plan #' . $plan_id . ' has no primary vendor linked; primary payable skipped.';
        }

        if (!function_exists('vms_get_event_plan_lineup_supporting_entries')) {
            continue;
        }

        $supporting_entries = (array) vms_get_event_plan_lineup_supporting_entries($plan_id, array(
            'event_date' => $event_date,
            'venue_id' => $venue_id,
        ));

        foreach ($supporting_entries as $entry_index => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $support_vendor_id = absint($entry['vendor_id'] ?? 0);
            if ($support_vendor_id <= 0) {
                continue;
            }

            $support_amount = vms_payables_sanitize_amount($entry['guaranteed_fee'] ?? '');
            if ($support_amount <= 0.0 && !$include_zero) {
                $warnings[] = 'Plan #' . $plan_id . ' supporting lineup entry #' . ($entry_index + 1) . ' has $0 guaranteed fee; skipped.';
                continue;
            }

            $support_tax_missing = false;
            if (function_exists('vms_is_vendor_tax_profile_complete')) {
                $support_tax_missing = !vms_is_vendor_tax_profile_complete((int) $support_vendor_id);
            }
            $support_bypass_active = false;
            $support_bypass_until = '';
            if (function_exists('vms_get_tax_bypass_status')) {
                $support_st = (array) vms_get_tax_bypass_status((int) $support_vendor_id);
                $support_bypass_active = !empty($support_st['is_active']);
                $support_bypass_until = isset($support_st['until']) ? (string) $support_st['until'] : '';
            }
            $support_payment_blocked = ($support_tax_missing && !$support_bypass_active);
            if ($support_payment_blocked && empty($include_tax_incomplete)) {
                $vendor_label = vms_payables_resolve_vendor_payee_name($support_vendor_id);
                $warnings[] = "Excluded bill for '" . $vendor_label . "' (tax profile incomplete). Payments/exports are blocked until resolved or bypass set.";
                continue;
            }

            $support_name = trim((string) ($entry['display_name'] ?? get_the_title($support_vendor_id)));
            $support_desc = $base_desc . ' — Supporting lineup fee';
            if ($support_name !== '') {
                $support_desc .= ' — ' . $support_name;
            }
            if (!$append_line($bills, $plan_id, $venue_id, $event_date, $support_vendor_id, $support_amount, 'supporting_guaranteed_fee', $support_desc, $support_payment_blocked, $support_tax_missing, $support_bypass_active, $support_bypass_until, $terms_days)) {
                $warnings[] = 'Vendor #' . $support_vendor_id . ' has no name; skipped.';
            }
        }
    }

    $out = array_values($bills);

    return [
        'bills'    => $out,
        'warnings' => array_values(array_unique($warnings)),
    ];
}
