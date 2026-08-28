<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_event_day_report_nonce_action')) {
    function bvmgr_event_day_report_nonce_action(int $event_plan_id): string
    {
        return 'bvmgr_event_day_report_' . max(0, $event_plan_id);
    }
}

if (!function_exists('bvmgr_event_day_report_url')) {
    function bvmgr_event_day_report_url(int $event_plan_id, array $args = array()): string
    {
        $query = array_merge(array(
            'action' => 'vms_event_day_report',
            'event_plan_id' => max(0, $event_plan_id),
        ), $args);
        $url = add_query_arg($query, admin_url('admin-post.php'));
        return wp_nonce_url($url, bvmgr_event_day_report_nonce_action($event_plan_id));
    }
}

if (!function_exists('bvmgr_event_day_report_truthy')) {
    function bvmgr_event_day_report_truthy($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value > 0;
        }
        return in_array(strtolower(trim((string) $value)), array('1', 'yes', 'true', 'checked_in', 'checked-in', 'going'), true);
    }
}

if (!function_exists('bvmgr_event_day_report_effective_qty')) {
    function bvmgr_event_day_report_effective_qty(array $row): int
    {
        if (array_key_exists('effective_qty', $row)) {
            return max(0, (int) $row['effective_qty']);
        }
        $qty = max(0, (int) ($row['qty'] ?? 0));
        $refunded = max(0, (int) ($row['refunded_qty'] ?? 0));
        return max(0, $qty - min($qty, $refunded));
    }
}

if (!function_exists('bvmgr_event_day_report_scope_woo_result')) {
    function bvmgr_event_day_report_scope_woo_result(array $result, int $event_plan_id): array
    {
        $rows = array();
        $selected_items = array();
        foreach ((array) ($result['rows'] ?? array()) as $row) {
            if (!is_array($row) || (int) ($row['event_plan_id'] ?? 0) !== $event_plan_id) {
                continue;
            }
            $rows[] = $row;
            $order_id = absint($row['order_id'] ?? 0);
            $order_item_id = absint($row['order_item_id'] ?? 0);
            if ($order_id > 0 && $order_item_id > 0) {
                $selected_items[$order_id][$order_item_id] = true;
            }
        }

        $original_linkage_issues = array_values((array) ($result['linkage_issues'] ?? array()));
        $linkage_issues = array();
        foreach ($original_linkage_issues as $issue) {
            if (!is_array($issue)) {
                continue;
            }
            $order_id = absint($issue['order_id'] ?? 0);
            if ($order_id <= 0 || empty($selected_items[$order_id])) {
                continue;
            }
            $candidate_item_ids = array_filter(array_map('absint', array(
                $issue['canonical_order_item_id'] ?? 0,
                $issue['literal_order_item_id'] ?? 0,
                $issue['resolved_order_item_id'] ?? 0,
            )));
            foreach ($candidate_item_ids as $candidate_item_id) {
                if (!empty($selected_items[$order_id][$candidate_item_id])) {
                    $linkage_issues[] = $issue;
                    break;
                }
            }
        }

        $original_linkage_warning = !empty($original_linkage_issues)
            ? sprintf(
                /* translators: %d: Number of attendee order-item linkage issues. */
                __('%d attendee order-item linkage issue(s) require reconciliation.', 'backstage-venue-manager'),
                count($original_linkage_issues)
            )
            : '';
        $warnings = array_values(array_filter((array) ($result['warnings'] ?? array()), static function ($warning) use ($original_linkage_warning): bool {
            return $original_linkage_warning === '' || (string) $warning !== $original_linkage_warning;
        }));
        if (!empty($linkage_issues)) {
            $warnings[] = sprintf(
                /* translators: %d: Number of attendee order-item linkage issues. */
                __('%d attendee order-item linkage issue(s) require reconciliation.', 'backstage-venue-manager'),
                count($linkage_issues)
            );
        }

        $result['rows'] = $rows;
        $result['linkage_issues'] = $linkage_issues;
        $result['warnings'] = array_values(array_unique($warnings));
        return $result;
    }
}

if (!function_exists('bvmgr_event_day_report_issue')) {
    function bvmgr_event_day_report_issue(string $code, string $message, string $context = '', string $severity = 'warning', string $scope = 'guests', array $details = array()): array
    {
        return array(
            'code' => sanitize_key($code),
            'severity' => in_array($severity, array('info', 'warning', 'error'), true) ? $severity : 'warning',
            'scope' => $scope === 'reservations' ? 'reservations' : 'guests',
            'message' => trim($message),
            'context' => trim($context),
            'details' => $details,
        );
    }
}

if (!function_exists('bvmgr_event_day_report_dedupe_issues')) {
    function bvmgr_event_day_report_dedupe_issues(array $issues): array
    {
        $deduped = array();
        foreach ($issues as $issue) {
            if (!is_array($issue) || trim((string) ($issue['message'] ?? '')) === '') {
                continue;
            }
            $key = sanitize_key((string) ($issue['code'] ?? 'issue')) . '|' . (string) ($issue['context'] ?? '') . '|' . (string) $issue['message'];
            $deduped[$key] = $issue;
        }
        return array_values($deduped);
    }
}

if (!function_exists('bvmgr_event_day_report_partition_issues')) {
    function bvmgr_event_day_report_partition_issues(array $issues): array
    {
        $partitioned = array('actionable' => array(), 'information' => array());
        foreach ($issues as $issue) {
            if (!is_array($issue) || trim((string) ($issue['message'] ?? '')) === '') {
                continue;
            }
            $bucket = (string) ($issue['severity'] ?? 'warning') === 'info' ? 'information' : 'actionable';
            $partitioned[$bucket][] = $issue;
        }
        return $partitioned;
    }
}

if (!function_exists('bvmgr_event_day_report_filter_issues')) {
    function bvmgr_event_day_report_filter_issues(array $issues, string $scope): array
    {
        if ($scope === 'full') {
            return array_values($issues);
        }
        return array_values(array_filter($issues, static function ($issue) use ($scope): bool {
            if (!is_array($issue)) {
                return false;
            }
            $issue_scope = (string) ($issue['scope'] ?? 'guests');
            return $issue_scope === $scope;
        }));
    }
}

if (!function_exists('bvmgr_event_day_report_group_information')) {
    function bvmgr_event_day_report_group_information(array $issues): array
    {
        $groups = array();
        foreach ($issues as $issue) {
            if (!is_array($issue) || (string) ($issue['severity'] ?? '') !== 'info') {
                continue;
            }
            $code = sanitize_key((string) ($issue['code'] ?? 'information'));
            $message = trim((string) ($issue['message'] ?? ''));
            if ($message === '') {
                continue;
            }
            $scope = (string) ($issue['scope'] ?? 'guests');
            $details = is_array($issue['details'] ?? null) ? $issue['details'] : array();
            $key = $code . '|' . $scope . '|' . $message;
            if (!isset($groups[$key])) {
                $groups[$key] = array(
                    'code' => $code,
                    'scope' => $scope,
                    'message' => $message,
                    'count' => 0,
                    'contexts' => array(),
                    'records' => array(),
                    'item_singular' => trim((string) ($details['item_singular'] ?? 'affected record')),
                    'item_plural' => trim((string) ($details['item_plural'] ?? 'affected records')),
                );
            }
            $groups[$key]['count']++;
            $context = trim((string) ($issue['context'] ?? ''));
            if ($context !== '') {
                $groups[$key]['contexts'][$context] = $context;
            }
            $record_reference = trim((string) ($details['reference'] ?? ''));
            $seat = max(0, (int) ($details['seat'] ?? 0));
            if ($record_reference !== '') {
                if (!isset($groups[$key]['records'][$record_reference])) {
                    $groups[$key]['records'][$record_reference] = array('reference' => $record_reference, 'seats' => array());
                }
                if ($seat > 0) {
                    $groups[$key]['records'][$record_reference]['seats'][$seat] = $seat;
                }
            }
        }

        foreach ($groups as &$group) {
            $group['contexts'] = array_values($group['contexts']);
            $group['records'] = array_values($group['records']);
            foreach ($group['records'] as &$record) {
                $record['seats'] = array_values($record['seats']);
                sort($record['seats'], SORT_NUMERIC);
            }
            unset($record);
        }
        unset($group);
        return array_values($groups);
    }
}

if (!function_exists('bvmgr_event_day_report_normalize_label')) {
    function bvmgr_event_day_report_normalize_label(string $label): string
    {
        $label = function_exists('remove_accents') ? remove_accents($label) : $label;
        $label = function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);
        $label = preg_replace('/[^a-z0-9]+/i', ' ', $label);
        return trim((string) preg_replace('/\s+/', ' ', (string) $label));
    }
}

if (!function_exists('bvmgr_event_day_report_unique_reservation_identity')) {
    function bvmgr_event_day_report_unique_reservation_identity(string $label): array
    {
        $label = trim(wp_strip_all_tags($label));
        $result = array('is_unique_looking' => false, 'key' => '', 'family' => $label, 'number' => 0);
        if ($label === '' || !preg_match('/^(.+?[\p{L}])(?:\s+(?:#\s*|no\.?\s*)?(\d+))\s*$/iu', $label, $matches)) {
            return $result;
        }

        $family = trim((string) $matches[1]);
        $number = max(0, (int) $matches[2]);
        $family_key = bvmgr_event_day_report_normalize_label($family);
        if ($family_key === '' || $number <= 0) {
            return $result;
        }

        return array(
            'is_unique_looking' => true,
            'key' => $family_key . ' ' . $number,
            'family' => $family,
            'number' => $number,
        );
    }
}

if (!function_exists('bvmgr_event_day_report_audit_reservations')) {
    function bvmgr_event_day_report_audit_reservations(array $reservations): array
    {
        $groups = array();
        $issues = array();
        $duplicate_count = 0;
        $quantity_count = 0;
        $units = 0;

        foreach ($reservations as $index => &$reservation) {
            $reservation['warnings'] = array_values((array) ($reservation['warnings'] ?? array()));
            $reservation['audit'] = bvmgr_event_day_report_unique_reservation_identity((string) ($reservation['label'] ?? ''));
            $qty = max(0, (int) ($reservation['qty'] ?? 0));
            $units += $qty;
            if (empty($reservation['audit']['is_unique_looking'])) {
                continue;
            }
            $groups[(string) $reservation['audit']['key']][] = $index;
            if ($qty > 1) {
                $message = sprintf('Quantity needs review — %1$s has an active quantity of %2$d.', (string) $reservation['label'], $qty);
                $reservation['warnings'][] = $message;
                $issues[] = bvmgr_event_day_report_issue('reservation_suspicious_quantity', $message, (string) ($reservation['reference'] ?? ''), 'warning', 'reservations');
                $quantity_count++;
            }
        }
        unset($reservation);

        foreach ($groups as $indexes) {
            $orders = array();
            $order_contexts = array();
            foreach ($indexes as $index) {
                $order_key = (string) (($reservations[$index]['order_id'] ?? 0) ?: ($reservations[$index]['party_key'] ?? $index));
                $orders[$order_key] = true;
                $reference = trim((string) ($reservations[$index]['reference'] ?? ''));
                $customer = trim((string) ($reservations[$index]['customer_name'] ?? ''));
                $context = implode(' — ', array_filter(array($reference, $customer)));
                if ($context !== '') {
                    $order_contexts[$order_key] = $context;
                }
            }
            if (count($orders) <= 1) {
                continue;
            }
            $label = (string) ($reservations[$indexes[0]]['label'] ?? 'Reservation');
            $message = sprintf('Possible duplicate allocation — %1$s appears on %2$d active orders.', $label, count($orders));
            foreach ($indexes as $index) {
                $reservations[$index]['warnings'][] = $message;
            }
            $issues[] = bvmgr_event_day_report_issue(
                'reservation_duplicate_allocation',
                $message,
                !empty($order_contexts) ? implode('; ', array_values($order_contexts)) : $label,
                'warning',
                'reservations'
            );
            $duplicate_count++;
        }

        return array(
            'rows' => array_values($reservations),
            'issues' => $issues,
            'summary' => array(
                'units' => $units,
                'duplicate_allocations' => $duplicate_count,
                'quantities_needing_review' => $quantity_count,
            ),
        );
    }
}

if (!function_exists('bvmgr_event_day_report_ops_lookup')) {
    function bvmgr_event_day_report_ops_lookup(int $event_plan_id, int $tec_event_id): array
    {
        $lookup = array('ids' => array(), 'references' => array());
        if (!function_exists('vms_ops_ticket_get_event_attendees')) {
            return $lookup;
        }

        $contexts = array();
        if ($tec_event_id > 0) {
            $contexts[] = array($tec_event_id, 'tribe_events');
        }
        if ($event_plan_id > 0) {
            $contexts[] = array($event_plan_id, 'vms_event_plan');
        }
        foreach ($contexts as $context) {
            try {
                $attendees = (array) vms_ops_ticket_get_event_attendees((int) $context[0], (string) $context[1]);
            } catch (Throwable $e) {
                $attendees = array();
            }
            foreach ($attendees as $attendee) {
                if (!is_array($attendee) || (empty($attendee['checked_in']) && empty($attendee['checked_in_local']))) {
                    continue;
                }
                $attendee_id = absint($attendee['attendee_id'] ?? 0);
                if ($attendee_id > 0) {
                    $lookup['ids'][$attendee_id] = true;
                }
                foreach (array('ticket_ref', 'ticket_id', 'security_code') as $key) {
                    $reference = strtolower(trim((string) ($attendee[$key] ?? '')));
                    if ($reference !== '') {
                        $lookup['references'][$reference] = true;
                    }
                }
            }
        }
        return $lookup;
    }
}

if (!function_exists('bvmgr_event_day_report_attendee_checked_in')) {
    function bvmgr_event_day_report_attendee_checked_in(int $attendee_id, string $reference, string $native_meta, array $ops_lookup): bool
    {
        if (bvmgr_event_day_report_truthy($native_meta)) {
            return true;
        }
        if ($attendee_id > 0 && !empty($ops_lookup['ids'][$attendee_id])) {
            return true;
        }
        $reference = strtolower(trim($reference));
        return $reference !== '' && !empty($ops_lookup['references'][$reference]);
    }
}

if (!function_exists('bvmgr_event_day_report_collect_woo_sources')) {
    function bvmgr_event_day_report_collect_woo_sources(int $event_plan_id, array $ops_lookup): array
    {
        $result = class_exists('BVMGR_Ticket_Revenue_Service')
            ? BVMGR_Ticket_Revenue_Service::get_sales_result(array(
                'event_plan_ids' => array($event_plan_id),
                'order_statuses' => array('processing', 'completed', 'refunded'),
                'include_refunded_lines' => true,
                'include_unresolved' => false,
            ))
            : array('rows' => array(), 'warnings' => array(), 'linkage_issues' => array());

        $result = bvmgr_event_day_report_scope_woo_result($result, $event_plan_id);

        $rows = array();
        foreach ((array) ($result['rows'] ?? array()) as $row) {
            if (!is_array($row) || (int) ($row['event_plan_id'] ?? 0) !== $event_plan_id) {
                continue;
            }
            $row['attendees'] = array();
            foreach ((array) ($row['attendee_ids'] ?? array()) as $attendee_id) {
                $attendee_id = absint($attendee_id);
                $post = $attendee_id > 0 ? get_post($attendee_id) : null;
                if (!($post instanceof WP_Post) || !in_array((string) $post->post_status, vms_ticket_sales_resolver_active_attendee_post_statuses(), true)) {
                    continue;
                }
                $name = trim((string) get_post_meta($attendee_id, '_tribe_tickets_full_name', true));
                if ($name === '') {
                    $name = trim((string) (get_post_meta($attendee_id, '_tribe_attendee_name', true) ?: get_post_meta($attendee_id, '_tribe_full_name', true)));
                }
                $email = sanitize_email((string) get_post_meta($attendee_id, '_tribe_tickets_email', true));
                if ($email === '') {
                    $email = sanitize_email((string) (get_post_meta($attendee_id, '_tribe_attendee_email', true) ?: get_post_meta($attendee_id, '_tribe_email', true)));
                }
                $reference = trim((string) (get_post_meta($attendee_id, '_tribe_wooticket_security_code', true) ?: $attendee_id));
                $native = (string) get_post_meta($attendee_id, '_tribe_wooticket_checkedin', true);
                $row['attendees'][] = array(
                    'id' => $attendee_id,
                    'name' => $name,
                    'email' => $email,
                    'reference' => $reference,
                    'native_checked_in' => bvmgr_event_day_report_truthy($native),
                    'ops_checked_in' => !bvmgr_event_day_report_truthy($native) && bvmgr_event_day_report_attendee_checked_in($attendee_id, $reference, '', $ops_lookup),
                    'checked_in' => bvmgr_event_day_report_attendee_checked_in($attendee_id, $reference, $native, $ops_lookup),
                );
            }

            $row['claim_assignments'] = array();
            $order_id = (int) ($row['order_id'] ?? 0);
            $order_item_id = (int) ($row['order_item_id'] ?? 0);
            $order = $order_id > 0 && function_exists('wc_get_order') ? wc_get_order($order_id) : null;
            $item = $order && method_exists($order, 'get_item') ? $order->get_item($order_item_id) : null;
            if ($item && method_exists($item, 'get_meta')) {
                $raw_assignments = $item->get_meta('_vms_claim_assignments', true);
                if (is_string($raw_assignments) && $raw_assignments !== '') {
                    $decoded = json_decode($raw_assignments, true);
                    $raw_assignments = is_array($decoded) ? $decoded : array();
                }
                $assignments = function_exists('bvmgr_ticketing_v2_claim_assignments_normalize')
                    ? bvmgr_ticketing_v2_claim_assignments_normalize($raw_assignments)
                    : (is_array($raw_assignments) ? $raw_assignments : array());
                foreach ($assignments as $assignment) {
                    $email = sanitize_email((string) ($assignment['assignee_email'] ?? ($assignment['email'] ?? '')));
                    if ($email === '') {
                        continue;
                    }
                    $user = get_user_by('email', $email);
                    $row['claim_assignments'][] = array(
                        'seat' => max(1, (int) ($assignment['seat'] ?? 1)),
                        'email' => $email,
                        'account_display_name' => $user instanceof WP_User ? trim((string) $user->display_name) : '',
                    );
                }
            }
            $rows[] = $row;
        }

        $result['rows'] = $rows;
        return $result;
    }
}

if (!function_exists('bvmgr_event_day_report_collect_admissions')) {
    function bvmgr_event_day_report_collect_admissions(int $event_plan_id, array $ops_lookup): array
    {
        if (!function_exists('bvmgr_admission_table_entries')) {
            return array();
        }
        global $wpdb;
        $table = bvmgr_admission_table_entries();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Event-day report reads the custom admissions store.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, event_plan_id, admission_kind, source, owner_vendor_id, guest_name, guest_email, party_size, checked_in_qty, status, claim_reference, claim_meta FROM %i WHERE event_plan_id = %d AND status NOT IN ('canceled','cancelled') ORDER BY guest_name ASC, id ASC",
            $table,
            $event_plan_id
        ), ARRAY_A);

        foreach ((array) $rows as &$row) {
            $bridge = function_exists('bvmgr_admission_vendor_guest_bridge_context_from_claim_meta')
                ? bvmgr_admission_vendor_guest_bridge_context_from_claim_meta($row['claim_meta'] ?? '')
                : array();
            $row['bridge'] = $bridge;
            $row['bridge_checked_qty'] = 0;
            $row['bridge_found_qty'] = 0;
            foreach ((array) ($bridge['attendee_ids'] ?? array()) as $attendee_id) {
                $attendee_id = absint($attendee_id);
                $post = $attendee_id > 0 ? get_post($attendee_id) : null;
                if (!($post instanceof WP_Post) || !in_array((string) $post->post_status, vms_ticket_sales_resolver_active_attendee_post_statuses(), true)) {
                    continue;
                }
                $row['bridge_found_qty']++;
                $reference = trim((string) get_post_meta($attendee_id, '_tribe_wooticket_security_code', true));
                if (bvmgr_event_day_report_attendee_checked_in($attendee_id, $reference, (string) get_post_meta($attendee_id, '_tribe_wooticket_checkedin', true), $ops_lookup)) {
                    $row['bridge_checked_qty']++;
                }
            }
        }
        unset($row);
        return array_values((array) $rows);
    }
}

if (!function_exists('bvmgr_event_day_report_collect_season_passes')) {
    function bvmgr_event_day_report_collect_season_passes(int $event_plan_id): array
    {
        if (
            !function_exists('vms_season_passes_table_passholders')
            || !function_exists('vms_season_passes_table_pass_types')
            || !function_exists('vms_season_passes_is_event_eligible_for_type')
        ) {
            return array('rows' => array(), 'issues' => array());
        }
        global $wpdb;
        $passholders_table = vms_season_passes_table_passholders();
        $pass_types_table = vms_season_passes_table_pass_types();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Event-day report reads the custom season-pass stores.
        $passholders = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ph.*, pt.pass_type_name, pt.public_display_label, pt.season_start_date, pt.season_end_date, pt.status AS pass_type_status, pt.usage_model, pt.total_uses_allowed, pt.max_uses_per_event, pt.eligibility_mode, pt.eligible_event_ids_json, pt.excluded_event_ids_json
                FROM %i ph
                INNER JOIN %i pt ON pt.id = ph.pass_type_id
                WHERE ph.status = 'active' AND pt.status = 'active'
                ORDER BY ph.id ASC",
                $passholders_table,
                $pass_types_table
            ),
            ARRAY_A
        );
        $rows = array();
        foreach ((array) $passholders as $passholder) {
            if (function_exists('vms_season_passes_normalize_pass_type_row')) {
                $passholder = vms_season_passes_normalize_pass_type_row((array) $passholder);
            }
            if (!is_array($passholder) || !vms_season_passes_is_event_eligible_for_type($passholder, $event_plan_id)) {
                continue;
            }
            $passholder_id = (int) ($passholder['id'] ?? 0);
            $checkins = function_exists('vms_season_passes_count_successful_checkins_for_passholder_event')
                ? max(0, (int) vms_season_passes_count_successful_checkins_for_passholder_event($passholder_id, $event_plan_id))
                : 0;
            $usage = function_exists('vms_season_passes_get_passholder_usage_summary')
                ? (array) vms_season_passes_get_passholder_usage_summary($passholder)
                : array();
            if (!empty($usage['is_fully_used']) && $checkins <= 0) {
                continue;
            }
            $name = trim((string) (($passholder['first_name'] ?? '') . ' ' . ($passholder['last_name'] ?? '')));
            $label = trim((string) (($passholder['public_display_label'] ?? '') ?: ($passholder['pass_type_name'] ?? 'Season Pass')));
            $rows[] = array(
                'id' => $passholder_id,
                'name' => $name,
                'email' => sanitize_email((string) ($passholder['email'] ?? '')),
                'label' => $label !== '' ? $label : 'Season Pass',
                'checked_in' => $checkins > 0,
            );
        }
        $issues = !empty($wpdb->last_error)
            ? array(bvmgr_event_day_report_issue('season_pass_read_error', 'Season-pass eligibility could not be read completely.', 'Season Passes'))
            : array();
        return array('rows' => $rows, 'issues' => $issues);
    }
}

if (!function_exists('bvmgr_event_day_report_collect_rsvps')) {
    function bvmgr_event_day_report_collect_rsvps(int $event_plan_id, int $tec_event_id, array $ops_lookup): array
    {
        if ($tec_event_id <= 0) {
            return array('rows' => array(), 'issues' => array());
        }
        $resolved_plan_id = function_exists('bvmgr_get_event_plan_for_tec_event') ? (int) bvmgr_get_event_plan_for_tec_event($tec_event_id) : $event_plan_id;
        if ($resolved_plan_id > 0 && $resolved_plan_id !== $event_plan_id) {
            return array(
                'rows' => array(),
                'issues' => array(bvmgr_event_day_report_issue(
                    'rsvp_duplicate_plan_boundary',
                    sprintf('RSVP attendees were not included because TEC event #%1$d deterministically resolves to Event Plan #%2$d, not this selected plan.', $tec_event_id, $resolved_plan_id),
                    'Event Plan #' . $event_plan_id,
                    'info'
                )),
            );
        }

        $ids = get_posts(array(
            'post_type' => 'tribe_rsvp_attendees',
            'post_status' => array('publish', 'private'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'orderby' => 'ID',
            'order' => 'ASC',
            'meta_query' => array(array('key' => '_tribe_rsvp_event', 'value' => (string) $tec_event_id)),
        ));
        $rows = array();
        foreach ((array) $ids as $attendee_id) {
            $attendee_id = absint($attendee_id);
            if ($attendee_id <= 0 || !bvmgr_event_day_report_truthy((string) get_post_meta($attendee_id, '_tribe_rsvp_status', true))) {
                continue;
            }
            $product_id = absint(get_post_meta($attendee_id, '_tribe_rsvp_product', true));
            $reference = trim((string) (get_post_meta($attendee_id, '_tribe_rsvp_security_code', true) ?: $attendee_id));
            $rows[] = array(
                'id' => $attendee_id,
                'order_key' => trim((string) get_post_meta($attendee_id, '_tribe_rsvp_order', true)),
                'name' => trim((string) get_post_meta($attendee_id, '_tribe_rsvp_full_name', true)),
                'email' => sanitize_email((string) get_post_meta($attendee_id, '_tribe_rsvp_email', true)),
                'label' => $product_id > 0 ? trim((string) get_the_title($product_id)) : 'RSVP',
                'reference' => $reference,
                'checked_in' => bvmgr_event_day_report_attendee_checked_in($attendee_id, $reference, (string) get_post_meta($attendee_id, '_tribe_rsvp_checkedin', true), $ops_lookup),
            );
        }
        return array('rows' => $rows, 'issues' => array());
    }
}

if (!function_exists('bvmgr_event_day_report_party_add')) {
    function bvmgr_event_day_report_party_add(array &$parties, string $key, array $payload): void
    {
        if (!isset($parties[$key])) {
            $parties[$key] = array(
                'key' => $key,
                'order_id' => max(0, (int) ($payload['order_id'] ?? 0)),
                'name' => trim((string) ($payload['name'] ?? '')),
                'email' => sanitize_email((string) ($payload['email'] ?? '')),
                'expected' => 0,
                'checked_in' => 0,
                'admissions' => array(),
                'reservations' => array(),
                'references' => array(),
                'identities' => array(),
                'source_families' => array(),
            );
        }

        $party = &$parties[$key];
        if ($party['name'] === '' && !empty($payload['name'])) {
            $party['name'] = trim((string) $payload['name']);
        }
        if ($party['email'] === '' && !empty($payload['email'])) {
            $party['email'] = sanitize_email((string) $payload['email']);
        }
        $expected = max(0, (int) ($payload['expected'] ?? 0));
        $party['expected'] += $expected;
        $party['checked_in'] += min($expected, max(0, (int) ($payload['checked_in'] ?? 0)));

        $admission_label = trim((string) ($payload['admission_label'] ?? 'Admission'));
        if ($expected > 0) {
            $party['admissions'][$admission_label] = (int) ($party['admissions'][$admission_label] ?? 0) + $expected;
        }
        foreach ((array) ($payload['references'] ?? array()) as $reference) {
            $reference = trim((string) $reference);
            if ($reference !== '') {
                $party['references'][] = $reference;
            }
        }
        foreach ((array) ($payload['identities'] ?? array()) as $identity) {
            if (is_array($identity) && (!empty($identity['name']) || !empty($identity['email']) || !empty($identity['reference']))) {
                $party['identities'][] = $identity;
            }
        }
        $source_family = sanitize_key((string) ($payload['source_family'] ?? ''));
        if ($source_family !== '') {
            $party['source_families'][] = $source_family;
        }
        unset($party);
    }
}

if (!function_exists('bvmgr_event_day_report_admission_label')) {
    function bvmgr_event_day_report_admission_label(string $source, string $kind): string
    {
        $source = sanitize_key($source);
        $kind = sanitize_key($kind);
        if (in_array($source, array('vendor', 'vendor_guest'), true)) {
            return 'Vendor Guest';
        }
        if ($source === 'pass_claim' || $kind === 'pass') {
            return 'Guest Pass';
        }
        if ($source === 'operator' || $kind === 'comp') {
            return 'Comp';
        }
        return $kind !== '' ? ucwords(str_replace('_', ' ', $kind)) : 'Admission';
    }
}

if (!function_exists('bvmgr_event_day_report_build_model_from_sources')) {
    function bvmgr_event_day_report_build_model_from_sources(array $plan, array $sources): array
    {
        $event_plan_id = (int) ($plan['event_plan_id'] ?? 0);
        $parties = array();
        $reservations = array();
        $issues = array_values((array) ($sources['issues'] ?? array()));
        $admissions = array_values((array) ($sources['admissions'] ?? array()));
        $woo_result = is_array($sources['woo_result'] ?? null) ? $sources['woo_result'] : array();
        $woo_rows = array_values((array) ($woo_result['rows'] ?? ($sources['woo_rows'] ?? array())));

        $bridge_attendee_ids = array();
        $bridge_order_items = array();
        foreach ($admissions as $admission) {
            $bridge = is_array($admission['bridge'] ?? null) ? $admission['bridge'] : array();
            foreach ((array) ($bridge['attendee_ids'] ?? array()) as $attendee_id) {
                $attendee_id = absint($attendee_id);
                if ($attendee_id > 0) {
                    $bridge_attendee_ids[$attendee_id] = true;
                }
            }
            $bridge_order_id = absint($bridge['order_id'] ?? 0);
            $bridge_item_id = absint($bridge['order_item_id'] ?? 0);
            if ($bridge_order_id > 0 && $bridge_item_id > 0) {
                $bridge_order_items[$bridge_order_id . ':' . $bridge_item_id] = true;
            }
        }

        foreach ((array) ($woo_result['warnings'] ?? array()) as $warning) {
            if (trim((string) $warning) !== '') {
                $issues[] = bvmgr_event_day_report_issue('ticket_sales_resolver_warning', (string) $warning, 'WooCommerce / Event Tickets', 'info');
            }
        }
        foreach ((array) ($woo_result['linkage_issues'] ?? array()) as $linkage_issue) {
            $code = sanitize_key((string) ($linkage_issue['code'] ?? 'attendee_linkage_issue'));
            $context = sprintf('Order #%1$d · attendee #%2$d', (int) ($linkage_issue['order_id'] ?? 0), (int) ($linkage_issue['attendee_id'] ?? 0));
            if ($code === 'attendee_order_item_meta_conflict') {
                $message = sprintf(
                    'Attendee order-item metadata conflicts: canonical item #%1$d was preferred over literal item #%2$d.',
                    (int) ($linkage_issue['canonical_order_item_id'] ?? 0),
                    (int) ($linkage_issue['literal_order_item_id'] ?? 0)
                );
            } else {
                $message = sprintf('Attendee points to missing or unresolvable order item #%d.', (int) ($linkage_issue['resolved_order_item_id'] ?? 0));
            }
            $issues[] = bvmgr_event_day_report_issue($code, $message, $context);
        }

        foreach ($woo_rows as $row) {
            if (!is_array($row) || (int) ($row['event_plan_id'] ?? 0) !== $event_plan_id) {
                continue;
            }
            $effective_qty = bvmgr_event_day_report_effective_qty($row);
            if ($effective_qty <= 0) {
                continue;
            }
            $line_kind = sanitize_key((string) ($row['line_kind'] ?? ''));
            $order_id = (int) ($row['order_id'] ?? 0);
            $order_number = trim((string) (($row['order_number'] ?? '') ?: $order_id));
            $reference = $order_number !== '' ? 'Order #' . $order_number : '';
            $order_item_key = $order_id . ':' . (int) ($row['order_item_id'] ?? 0);

            if ($line_kind === 'addon') {
                $reservations[] = array(
                    'key' => 'order-item:' . $order_item_key,
                    'party_key' => 'order:' . $order_id,
                    'order_id' => $order_id,
                    'order_number' => $order_number,
                    'reference' => $reference,
                    'label' => trim((string) ($row['product_name'] ?? 'Reservation')),
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'family_label' => trim((string) ($row['reservation_family'] ?? '')),
                    'customer_name' => trim((string) ($row['customer_name'] ?? '')),
                    'customer_email' => sanitize_email((string) ($row['customer_email'] ?? '')),
                    'qty' => $effective_qty,
                    'party_size' => 0,
                    'warnings' => array(),
                );
                continue;
            }
            if ($line_kind !== 'ticket') {
                continue;
            }
            if (!empty($bridge_order_items[$order_item_key])) {
                continue;
            }

            $attendees = array();
            $suppressed_count = 0;
            foreach ((array) ($row['attendees'] ?? array()) as $attendee) {
                if (!is_array($attendee)) {
                    continue;
                }
                $attendee_id = absint($attendee['id'] ?? 0);
                if ($attendee_id > 0 && !empty($bridge_attendee_ids[$attendee_id])) {
                    $suppressed_count++;
                    continue;
                }
                $attendees[] = $attendee;
            }
            $expected = max(0, $effective_qty - min($effective_qty, $suppressed_count));
            if ($expected <= 0) {
                continue;
            }

            $checked_in = 0;
            $identities = array();
            foreach ($attendees as $attendee) {
                if (!empty($attendee['checked_in'])) {
                    $checked_in++;
                }
                $identities[] = array(
                    'label' => 'Ticket holder',
                    'name' => trim((string) ($attendee['name'] ?? '')),
                    'email' => sanitize_email((string) ($attendee['email'] ?? '')),
                    'reference' => trim((string) ($attendee['reference'] ?? '')),
                    'checked_in' => !empty($attendee['checked_in']),
                );
            }
            foreach ((array) ($row['claim_assignments'] ?? array()) as $assignment) {
                if (!is_array($assignment) || empty($assignment['email'])) {
                    continue;
                }
                $identities[] = array(
                    'label' => 'Registered guest email',
                    'name' => '',
                    'email' => sanitize_email((string) $assignment['email']),
                    'reference' => !empty($assignment['account_display_name']) ? 'Account display: ' . trim((string) $assignment['account_display_name']) : '',
                    'checked_in' => false,
                );
                $issues[] = bvmgr_event_day_report_issue(
                    'registered_guest_name_unverified',
                    'A registered-ticket assignment has an email but no durable verified guest-name snapshot. The report does not treat the purchaser/attendee name as that guest\'s verified name.',
                    $reference . ' · seat ' . max(1, (int) ($assignment['seat'] ?? 1)),
                    'info',
                    'guests',
                    array(
                        'reference' => $reference,
                        'seat' => max(1, (int) ($assignment['seat'] ?? 1)),
                        'item_singular' => 'registered-ticket assignment',
                        'item_plural' => 'registered-ticket assignments',
                    )
                );
            }

            $customer_name = trim((string) ($row['customer_name'] ?? ''));
            $customer_email = sanitize_email((string) ($row['customer_email'] ?? ''));
            if ($customer_name === '' && !empty($attendees[0]['name'])) {
                $customer_name = trim((string) $attendees[0]['name']);
            }
            if ($customer_email === '' && !empty($attendees[0]['email'])) {
                $customer_email = sanitize_email((string) $attendees[0]['email']);
            }
            if ($customer_name === '') {
                $customer_name = $reference !== '' ? $reference : 'Ticket Party';
            }

            bvmgr_event_day_report_party_add($parties, 'order:' . $order_id, array(
                'order_id' => $order_id,
                'name' => $customer_name,
                'email' => $customer_email,
                'expected' => $expected,
                'checked_in' => min($expected, $checked_in),
                'admission_label' => trim((string) (($row['product_name'] ?? '') ?: 'Ticket')),
                'references' => array($reference),
                'identities' => $identities,
                'source_family' => 'woo',
            ));

            $qty = max(0, (int) ($row['qty'] ?? 0));
            $refunded_qty = max(0, (int) ($row['refunded_qty'] ?? 0));
            if ($refunded_qty > 0 && $effective_qty < $qty && count($attendees) > $effective_qty) {
                $issues[] = bvmgr_event_day_report_issue(
                    'partial_refund_identity_ambiguity',
                    sprintf('%1$d of %2$d admissions remains active; the specific refunded attendee cannot be identified.', $effective_qty, $qty),
                    $reference . ' · ' . trim((string) ($row['product_name'] ?? 'Ticket'))
                );
            }
            if (count($attendees) < $expected) {
                $issues[] = bvmgr_event_day_report_issue(
                    'missing_attendee_records',
                    sprintf('%1$d active admissions are expected, but only %2$d active attendee records are linked.', $expected, count($attendees)),
                    $reference . ' · ' . trim((string) ($row['product_name'] ?? 'Ticket'))
                );
            }
        }

        foreach ($admissions as $admission) {
            if (!is_array($admission)) {
                continue;
            }
            $entry_id = (int) ($admission['id'] ?? 0);
            $party_size = max(0, (int) ($admission['party_size'] ?? 0));
            if ($party_size <= 0) {
                continue;
            }
            $checked_in = max(0, (int) ($admission['checked_in_qty'] ?? 0));
            $admission_status = sanitize_key((string) ($admission['status'] ?? 'active'));
            if ($admission_status === 'checked_in' && $checked_in <= 0) {
                $checked_in = $party_size;
            }
            $checked_in = max($checked_in, max(0, (int) ($admission['bridge_checked_qty'] ?? 0)));
            $source = (string) ($admission['source'] ?? '');
            $kind = (string) ($admission['admission_kind'] ?? '');
            $reference = trim((string) ($admission['claim_reference'] ?? ''));
            if ($reference === '') {
                $reference = bvmgr_event_day_report_admission_label($source, $kind) . ' #' . $entry_id;
            }
            bvmgr_event_day_report_party_add($parties, 'admission:' . $entry_id, array(
                'name' => trim((string) ($admission['guest_name'] ?? 'Guest Party')),
                'email' => sanitize_email((string) ($admission['guest_email'] ?? '')),
                'expected' => $party_size,
                'checked_in' => min($party_size, $checked_in),
                'admission_label' => bvmgr_event_day_report_admission_label($source, $kind),
                'references' => array($reference),
                'source_family' => 'vms_admission',
            ));

            $bridge = is_array($admission['bridge'] ?? null) ? $admission['bridge'] : array();
            $bridge_ids = array_values(array_filter(array_map('absint', (array) ($bridge['attendee_ids'] ?? array()))));
            if (!empty($bridge_ids) && (int) ($admission['bridge_found_qty'] ?? 0) < count($bridge_ids)) {
                $issues[] = bvmgr_event_day_report_issue(
                    'vendor_bridge_inconsistency',
                    'A vendor-guest bridge references attendee records that are missing or no longer active. The canonical VMS admission remains on the report.',
                    'Vendor admission #' . $entry_id
                );
            }
        }

        foreach ((array) ($sources['season_passes'] ?? array()) as $passholder) {
            if (!is_array($passholder)) {
                continue;
            }
            $passholder_id = (int) ($passholder['id'] ?? 0);
            bvmgr_event_day_report_party_add($parties, 'season-pass:' . $passholder_id, array(
                'name' => trim((string) ($passholder['name'] ?? 'Passholder')),
                'email' => sanitize_email((string) ($passholder['email'] ?? '')),
                'expected' => 1,
                'checked_in' => !empty($passholder['checked_in']) ? 1 : 0,
                'admission_label' => trim((string) (($passholder['label'] ?? '') ?: 'Season Pass')),
                'references' => array('Season Pass #' . $passholder_id),
                'source_family' => 'season_pass',
            ));
        }

        foreach ((array) ($sources['rsvps'] ?? array()) as $rsvp) {
            if (!is_array($rsvp)) {
                continue;
            }
            $rsvp_id = (int) ($rsvp['id'] ?? 0);
            $order_key = trim((string) ($rsvp['order_key'] ?? ''));
            $party_key = $order_key !== '' ? 'rsvp-order:' . $order_key : 'rsvp:' . $rsvp_id;
            bvmgr_event_day_report_party_add($parties, $party_key, array(
                'name' => trim((string) ($rsvp['name'] ?? 'RSVP Guest')),
                'email' => sanitize_email((string) ($rsvp['email'] ?? '')),
                'expected' => 1,
                'checked_in' => !empty($rsvp['checked_in']) ? 1 : 0,
                'admission_label' => trim((string) (($rsvp['label'] ?? '') ?: 'RSVP')),
                'references' => array('RSVP ' . trim((string) ($rsvp['reference'] ?? $rsvp_id))),
                'identities' => array(array(
                    'label' => 'RSVP attendee',
                    'name' => trim((string) ($rsvp['name'] ?? '')),
                    'email' => sanitize_email((string) ($rsvp['email'] ?? '')),
                    'reference' => trim((string) ($rsvp['reference'] ?? '')),
                    'checked_in' => !empty($rsvp['checked_in']),
                )),
                'source_family' => 'rsvp',
            ));
        }

        $audit = bvmgr_event_day_report_audit_reservations($reservations);
        $reservations = (array) $audit['rows'];
        $issues = array_merge($issues, (array) $audit['issues']);
        foreach ($reservations as &$reservation) {
            $party_key = (string) ($reservation['party_key'] ?? '');
            if (isset($parties[$party_key])) {
                $reservation['party_size'] = (int) $parties[$party_key]['expected'];
                $parties[$party_key]['reservations'][] = (string) $reservation['label'] . ' ×' . (int) $reservation['qty'];
            }
            if (empty($reservation['audit']['family'])) {
                $reservation['audit']['family'] = (string) $reservation['label'];
            }
        }
        unset($reservation);

        foreach ($parties as &$party) {
            $party['checked_in'] = min((int) $party['expected'], max(0, (int) $party['checked_in']));
            $party['references'] = array_values(array_unique(array_filter(array_map('trim', $party['references']))));
            $party['reservations'] = array_values(array_unique(array_filter(array_map('trim', $party['reservations']))));
            $party['source_families'] = array_values(array_unique(array_filter(array_map('sanitize_key', $party['source_families']))));
        }
        unset($party);

        $parties = array_values($parties);
        usort($parties, static function (array $a, array $b): int {
            $cmp = strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            return $cmp !== 0 ? $cmp : strcasecmp(implode(' ', (array) ($a['references'] ?? array())), implode(' ', (array) ($b['references'] ?? array())));
        });
        usort($reservations, static function (array $a, array $b): int {
            $family_cmp = strnatcasecmp((string) ($a['audit']['family'] ?? ''), (string) ($b['audit']['family'] ?? ''));
            return $family_cmp !== 0 ? $family_cmp : strnatcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        $expected = 0;
        $checked_in = 0;
        foreach ($parties as $party) {
            $expected += max(0, (int) ($party['expected'] ?? 0));
            $checked_in += max(0, (int) ($party['checked_in'] ?? 0));
        }

        return array(
            'plan' => $plan,
            'parties' => $parties,
            'reservations' => $reservations,
            'issues' => bvmgr_event_day_report_dedupe_issues($issues),
            'totals' => array(
                'expected' => $expected,
                'checked_in' => min($expected, $checked_in),
                'remaining' => max(0, $expected - $checked_in),
                'reservation_units' => (int) ($audit['summary']['units'] ?? 0),
            ),
            'reservation_audit' => (array) ($audit['summary'] ?? array()),
        );
    }
}

if (!function_exists('bvmgr_event_day_report_build_model')) {
    function bvmgr_event_day_report_build_model(int $event_plan_id): array
    {
        $context = function_exists('bvmgr_admission_event_plan_context') ? bvmgr_admission_event_plan_context($event_plan_id) : null;
        if (!is_array($context)) {
            return array();
        }
        $tec_event_id = absint(get_post_meta($event_plan_id, function_exists('bvmgr_ticket_revenue_plan_tec_meta_key') ? bvmgr_ticket_revenue_plan_tec_meta_key() : '_vms_tec_event_id', true));
        $start_time = trim((string) get_post_meta($event_plan_id, '_vms_start_time', true));
        $end_time = trim((string) get_post_meta($event_plan_id, '_vms_end_time', true));
        $plan = array_merge($context, array(
            'tec_event_id' => $tec_event_id,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'schedule_label' => trim((string) ($context['event_date'] ?? '') . ($start_time !== '' ? ' · ' . $start_time : '') . ($end_time !== '' ? '–' . $end_time : '')),
        ));
        $ops_lookup = bvmgr_event_day_report_ops_lookup($event_plan_id, $tec_event_id);
        $woo_result = bvmgr_event_day_report_collect_woo_sources($event_plan_id, $ops_lookup);
        $admissions = bvmgr_event_day_report_collect_admissions($event_plan_id, $ops_lookup);
        $season = bvmgr_event_day_report_collect_season_passes($event_plan_id);
        $rsvp = bvmgr_event_day_report_collect_rsvps($event_plan_id, $tec_event_id, $ops_lookup);

        $model = bvmgr_event_day_report_build_model_from_sources($plan, array(
            'woo_result' => $woo_result,
            'admissions' => $admissions,
            'season_passes' => (array) ($season['rows'] ?? array()),
            'rsvps' => (array) ($rsvp['rows'] ?? array()),
            'issues' => array_merge((array) ($season['issues'] ?? array()), (array) ($rsvp['issues'] ?? array())),
        ));

        if (function_exists('bvmgr_event_occurrence_integrity')) {
            $integrity = bvmgr_event_occurrence_integrity($event_plan_id);
            $admission_mismatches = (int) ($integrity['mismatch_admission_units'] ?? 0);
            $reservation_mismatches = (int) ($integrity['mismatch_reservation_units'] ?? 0);
            if ($admission_mismatches > 0 || $reservation_mismatches > 0) {
                $model['issues'][] = bvmgr_event_day_report_issue(
                    'event_occurrence_date_mismatch',
                    sprintf(
                        /* translators: 1: admission count, 2: reservation count. */
                        __('Date mismatch detected: %1$d admissions and %2$d reservations reference a different event occurrence.', 'backstage-venue-manager'),
                        $admission_mismatches,
                        $reservation_mismatches
                    ),
                    __('Run the controlled Event Plan date-change preview before event day.', 'backstage-venue-manager'),
                    'error',
                    'guests',
                    $integrity
                );
                $model['issues'] = bvmgr_event_day_report_dedupe_issues((array) $model['issues']);
            }
            $model['occurrence_integrity'] = $integrity;
        }

        return $model;
    }
}

if (!function_exists('bvmgr_event_day_report_format_admissions')) {
    function bvmgr_event_day_report_format_admissions(array $admissions): string
    {
        $parts = array();
        foreach ($admissions as $label => $qty) {
            $parts[] = trim((string) $label) . ((int) $qty > 1 ? ' ×' . (int) $qty : '');
        }
        return implode(', ', $parts);
    }
}

if (!function_exists('bvmgr_event_day_report_guest_search_text')) {
    function bvmgr_event_day_report_guest_search_text(array $party): string
    {
        $values = array(
            (string) ($party['name'] ?? ''),
            (string) ($party['email'] ?? ''),
            implode(' ', (array) ($party['references'] ?? array())),
            implode(' ', array_keys((array) ($party['admissions'] ?? array()))),
            implode(' ', (array) ($party['reservations'] ?? array())),
        );
        foreach ((array) ($party['identities'] ?? array()) as $identity) {
            if (is_array($identity)) {
                $values[] = implode(' ', array_map('strval', $identity));
            }
        }
        return strtolower(trim(implode(' ', $values)));
    }
}

if (!function_exists('bvmgr_event_day_report_render_identity_list')) {
    function bvmgr_event_day_report_render_identity_list(array $identities): void
    {
        echo '<ul>';
        foreach ($identities as $identity) {
            if (!is_array($identity)) {
                continue;
            }
            $bits = array_filter(array(
                trim((string) ($identity['label'] ?? 'Guest')),
                trim((string) ($identity['name'] ?? '')),
                trim((string) ($identity['email'] ?? '')),
                trim((string) ($identity['reference'] ?? '')),
                !empty($identity['checked_in']) ? __('Checked in', 'backstage-venue-manager') : '',
            ));
            echo '<li>' . esc_html(implode(' · ', $bits)) . '</li>';
        }
        echo '</ul>';
    }
}

if (!function_exists('bvmgr_event_day_report_render_header')) {
    function bvmgr_event_day_report_render_header(array $model): void
    {
        $plan = (array) ($model['plan'] ?? array());
        $totals = (array) ($model['totals'] ?? array());
        echo '<header class="vms-edr-header vms-edr-screen-header">';
        echo '<div><p class="vms-edr-eyebrow">' . esc_html__('Event-Day Guest & Reservations Report', 'backstage-venue-manager') . '</p>';
        echo '<h1>' . esc_html((string) ($plan['title'] ?? 'Event')) . '</h1>';
        echo '<p class="vms-edr-event-meta">' . esc_html((string) ($plan['schedule_label'] ?? '')) . ' · ' . esc_html(sprintf(
            /* translators: %d: Event Plan post ID. */
            __('Event Plan #%d', 'backstage-venue-manager'),
            (int) ($plan['event_plan_id'] ?? 0)
        )) . '</p></div>';
        echo '<div class="vms-edr-totals" aria-label="' . esc_attr__('Operational totals', 'backstage-venue-manager') . '">';
        $metrics = array(
            __('Expected Admissions', 'backstage-venue-manager') => (int) ($totals['expected'] ?? 0),
            __('Checked In', 'backstage-venue-manager') => (int) ($totals['checked_in'] ?? 0),
            __('Remaining', 'backstage-venue-manager') => (int) ($totals['remaining'] ?? 0),
            __('Reservation Units', 'backstage-venue-manager') => (int) ($totals['reservation_units'] ?? 0),
        );
        foreach ($metrics as $label => $value) {
            echo '<div><span>' . esc_html($label) . '</span><strong>' . esc_html((string) $value) . '</strong></div>';
        }
        echo '</div></header>';
        echo '<header class="vms-edr-print-header" aria-hidden="true"><h1>' . esc_html((string) ($plan['title'] ?? 'Event')) . ' <span>— ';
        echo esc_html((string) ($plan['schedule_label'] ?? '')) . ' · ' . esc_html(sprintf(
            /* translators: %d: Event Plan post ID. */
            __('Event Plan #%d', 'backstage-venue-manager'),
            (int) ($plan['event_plan_id'] ?? 0)
        )) . '</span></h1>';
        echo '<p>' . esc_html(sprintf(
            /* translators: 1: expected admissions, 2: checked-in admissions, 3: remaining admissions, 4: reservation units. */
            __('Expected %1$d · Checked in %2$d · Remaining %3$d · Reservations %4$d', 'backstage-venue-manager'),
            (int) ($totals['expected'] ?? 0),
            (int) ($totals['checked_in'] ?? 0),
            (int) ($totals['remaining'] ?? 0),
            (int) ($totals['reservation_units'] ?? 0)
        )) . '</p></header>';
    }
}

if (!function_exists('bvmgr_event_day_report_render_guests')) {
    function bvmgr_event_day_report_render_guests(array $model, bool $print = false): void
    {
        $parties = (array) ($model['parties'] ?? array());
        echo '<section class="vms-edr-panel" id="guest-list" data-vms-edr-panel="guests">';
        echo '<div class="vms-edr-section-heading"><div><p class="vms-edr-kicker">' . esc_html__('Guest List', 'backstage-venue-manager') . '</p><h2>' . esc_html__('Expected guests and check-in state', 'backstage-venue-manager') . '</h2></div>';
        if (!$print) {
            echo '<label class="vms-edr-search"><span>' . esc_html__('Search Guest List', 'backstage-venue-manager') . '</span><input type="search" id="vms-edr-search" placeholder="' . esc_attr__('Name, email, order, or ticket reference', 'backstage-venue-manager') . '"></label>';
        }
        echo '</div>';
        if (empty($parties)) {
            echo '<p class="vms-edr-empty">' . esc_html__('No active admissions were found for this Event Plan.', 'backstage-venue-manager') . '</p></section>';
            return;
        }

        echo '<div class="vms-edr-table-wrap vms-edr-desktop-table"><table class="vms-edr-table vms-edr-guest-table"><thead><tr>';
        echo '<th class="vms-edr-paper-check" scope="col">' . esc_html__('Paper', 'backstage-venue-manager') . '</th>';
        echo '<th scope="col">' . esc_html__('Guest / Party', 'backstage-venue-manager') . '</th><th scope="col">' . esc_html__('Admission', 'backstage-venue-manager') . '</th>';
        echo '<th class="num" scope="col">' . esc_html__('Expected', 'backstage-venue-manager') . '</th><th class="num" scope="col">' . esc_html__('Checked In', 'backstage-venue-manager') . '</th>';
        echo '<th scope="col">' . esc_html__('Reservation Summary', 'backstage-venue-manager') . '</th><th scope="col">' . esc_html__('Reference', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
        foreach ($parties as $party) {
            $expected = max(0, (int) ($party['expected'] ?? 0));
            $checked = min($expected, max(0, (int) ($party['checked_in'] ?? 0)));
            $search = bvmgr_event_day_report_guest_search_text($party);
            echo '<tr data-vms-edr-guest-row data-search="' . esc_attr($search) . '">';
            echo '<td class="vms-edr-paper-check" aria-label="' . esc_attr__('Manual paper check-off', 'backstage-venue-manager') . '"><span></span></td>';
            echo '<td><strong>' . esc_html((string) ($party['name'] ?? 'Guest Party')) . '</strong>';
            if (!empty($party['email'])) {
                echo '<small>' . esc_html((string) $party['email']) . '</small>';
            }
            if (!empty($party['identities'])) {
                echo '<details class="vms-edr-details"><summary>' . esc_html__('Identity details', 'backstage-venue-manager') . '</summary>';
                bvmgr_event_day_report_render_identity_list((array) $party['identities']);
                echo '</details>';
            }
            echo '</td>';
            echo '<td>' . esc_html(bvmgr_event_day_report_format_admissions((array) ($party['admissions'] ?? array()))) . '</td>';
            echo '<td class="num">' . esc_html((string) $expected) . '</td>';
            echo '<td class="num"><strong>' . esc_html($checked . ' / ' . $expected) . '</strong></td>';
            echo '<td>' . (!empty($party['reservations']) ? esc_html(implode(', ', (array) $party['reservations'])) : '<span class="muted">—</span>') . '</td>';
            echo '<td>' . esc_html(implode(', ', (array) ($party['references'] ?? array()))) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        echo '<div class="vms-edr-mobile-list vms-edr-mobile-guests" aria-label="' . esc_attr__('Guest list', 'backstage-venue-manager') . '">';
        foreach ($parties as $party) {
            $expected = max(0, (int) ($party['expected'] ?? 0));
            $checked = min($expected, max(0, (int) ($party['checked_in'] ?? 0)));
            $search = bvmgr_event_day_report_guest_search_text($party);
            $admissions = bvmgr_event_day_report_format_admissions((array) ($party['admissions'] ?? array()));
            $reservations = !empty($party['reservations']) ? implode(', ', (array) $party['reservations']) : '—';
            $references = implode(', ', (array) ($party['references'] ?? array()));
            echo '<details class="vms-edr-mobile-record vms-edr-mobile-guest" data-vms-edr-guest-row data-search="' . esc_attr($search) . '">';
            echo '<summary><strong>' . esc_html((string) ($party['name'] ?? 'Guest Party')) . '</strong></summary><div class="vms-edr-mobile-body"><dl>';
            echo '<dt>' . esc_html__('Email', 'backstage-venue-manager') . '</dt><dd>' . esc_html((string) (($party['email'] ?? '') ?: '—')) . '</dd>';
            echo '<dt>' . esc_html__('Admission', 'backstage-venue-manager') . '</dt><dd>' . esc_html($admissions !== '' ? $admissions : '—') . '</dd>';
            echo '<dt>' . esc_html__('Expected', 'backstage-venue-manager') . '</dt><dd>' . esc_html((string) $expected) . '</dd>';
            echo '<dt>' . esc_html__('Checked In', 'backstage-venue-manager') . '</dt><dd><strong>' . esc_html($checked . ' / ' . $expected) . '</strong></dd>';
            echo '<dt>' . esc_html__('Reservation', 'backstage-venue-manager') . '</dt><dd>' . esc_html($reservations) . '</dd>';
            echo '<dt>' . esc_html__('Order / Reference', 'backstage-venue-manager') . '</dt><dd>' . esc_html($references !== '' ? $references : '—') . '</dd>';
            echo '</dl>';
            if (!empty($party['identities'])) {
                echo '<div class="vms-edr-mobile-identities"><strong>' . esc_html__('Identity details', 'backstage-venue-manager') . '</strong>';
                bvmgr_event_day_report_render_identity_list((array) $party['identities']);
                echo '</div>';
            }
            echo '</div></details>';
        }
        echo '</div><p class="vms-edr-empty" id="vms-edr-no-search-results" hidden>' . esc_html__('No guests match that search.', 'backstage-venue-manager') . '</p></section>';
    }
}

if (!function_exists('bvmgr_event_day_report_render_reservation_audit')) {
    function bvmgr_event_day_report_render_reservation_audit(array $model): void
    {
        $audit = (array) ($model['reservation_audit'] ?? array());
        $duplicates = (int) ($audit['duplicate_allocations'] ?? 0);
        $quantities = (int) ($audit['quantities_needing_review'] ?? 0);
        echo '<aside class="vms-edr-audit"><div><span>' . esc_html__('Reservation Audit', 'backstage-venue-manager') . '</span><strong>' . esc_html(sprintf(
            /* translators: %d: Number of reservation units. */
            _n('%d reservation unit', '%d reservation units', (int) ($audit['units'] ?? 0), 'backstage-venue-manager'),
            (int) ($audit['units'] ?? 0)
        )) . '</strong></div>';
        if ($duplicates <= 0 && $quantities <= 0) {
            echo '<p>' . esc_html__('No reservation allocation anomalies detected.', 'backstage-venue-manager') . '</p>';
        } else {
            echo '<ul><li>' . esc_html(sprintf(
                /* translators: %d: Number of possible duplicate allocations. */
                _n('%d possible duplicate allocation', '%d possible duplicate allocations', $duplicates, 'backstage-venue-manager'),
                $duplicates
            )) . '</li>';
            echo '<li>' . esc_html(sprintf(
                /* translators: %d: Number of quantities needing review. */
                _n('%d quantity needing review', '%d quantities needing review', $quantities, 'backstage-venue-manager'),
                $quantities
            )) . '</li></ul>';
        }
        echo '</aside>';
    }
}

if (!function_exists('bvmgr_event_day_report_render_reservations')) {
    function bvmgr_event_day_report_render_reservations(array $model, bool $print = false): void
    {
        $reservations = (array) ($model['reservations'] ?? array());
        echo '<section class="vms-edr-panel" id="reservations" data-vms-edr-panel="reservations">';
        echo '<div class="vms-edr-section-heading"><div><p class="vms-edr-kicker">' . esc_html__('Reservations', 'backstage-venue-manager') . '</p><h2>' . esc_html__('Exact sold add-on allocations', 'backstage-venue-manager') . '</h2></div></div>';
        bvmgr_event_day_report_render_reservation_audit($model);
        if (empty($reservations)) {
            echo '<p class="vms-edr-empty">' . esc_html__('No active reservation/add-on lines were found for this Event Plan.', 'backstage-venue-manager') . '</p></section>';
            return;
        }

        echo '<div class="vms-edr-table-wrap vms-edr-desktop-table"><table class="vms-edr-table vms-edr-reservation-table"><thead><tr><th scope="col">' . esc_html__('Exact Reservation', 'backstage-venue-manager') . '</th>';
        echo '<th scope="col">' . esc_html__('Customer', 'backstage-venue-manager') . '</th><th class="num" scope="col">' . esc_html__('Qty', 'backstage-venue-manager') . '</th>';
        echo '<th class="num" scope="col">' . esc_html__('Admission Party', 'backstage-venue-manager') . '</th><th scope="col">' . esc_html__('Order', 'backstage-venue-manager') . '</th></tr></thead><tbody>';
        $last_family = '';
        foreach ($reservations as $reservation) {
            $family = trim((string) ($reservation['audit']['family'] ?? $reservation['label'] ?? 'Reservations'));
            if ($print && strcasecmp($last_family, $family) !== 0) {
                echo '<tr class="vms-edr-family-row"><th colspan="5" scope="rowgroup">' . esc_html(strtoupper($family)) . '</th></tr>';
                $last_family = $family;
            }
            $warnings = array_values((array) ($reservation['warnings'] ?? array()));
            echo '<tr' . (!empty($warnings) ? ' class="has-warning"' : '') . '>';
            echo '<td><strong>' . (!empty($warnings) ? '<span class="vms-edr-warning-icon" aria-label="' . esc_attr__('Needs review', 'backstage-venue-manager') . '">⚠</span> ' : '') . esc_html((string) ($reservation['label'] ?? 'Reservation')) . '</strong>';
            if (!empty($reservation['family_label'])) {
                echo '<small>' . esc_html((string) $reservation['family_label']) . '</small>';
            }
            foreach ($warnings as $warning) {
                echo '<span class="vms-edr-row-warning">' . esc_html((string) $warning) . '</span>';
            }
            echo '</td><td><strong>' . esc_html((string) ($reservation['customer_name'] ?? 'Customer')) . '</strong>';
            if (!empty($reservation['customer_email'])) {
                echo '<small>' . esc_html((string) $reservation['customer_email']) . '</small>';
            }
            echo '</td><td class="num">' . esc_html((string) (int) ($reservation['qty'] ?? 0)) . '</td>';
            echo '<td class="num">' . esc_html(sprintf(
                /* translators: %d: Number of guests in the reservation party. */
                _n('%d guest', '%d guests', (int) ($reservation['party_size'] ?? 0), 'backstage-venue-manager'),
                (int) ($reservation['party_size'] ?? 0)
            )) . '</td>';
            echo '<td>' . esc_html((string) ($reservation['reference'] ?? '')) . '</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<div class="vms-edr-mobile-list vms-edr-mobile-reservations" aria-label="' . esc_attr__('Reservation list', 'backstage-venue-manager') . '">';
        foreach ($reservations as $reservation) {
            $warnings = array_values((array) ($reservation['warnings'] ?? array()));
            $party_size = max(0, (int) ($reservation['party_size'] ?? 0));
            echo '<details class="vms-edr-mobile-record vms-edr-mobile-reservation' . (!empty($warnings) ? ' has-warning' : '') . '">';
            echo '<summary><span><strong>';
            if (!empty($warnings)) {
                echo '<span class="vms-edr-warning-icon" aria-label="' . esc_attr__('Needs review', 'backstage-venue-manager') . '">⚠</span> ';
            }
            echo esc_html((string) ($reservation['label'] ?? 'Reservation')) . '</strong>';
            if (!empty($warnings)) {
                echo '<span class="vms-edr-mobile-warning">' . esc_html(sprintf(
                    /* translators: %s: First reservation audit warning. */
                    __('Needs review: %s', 'backstage-venue-manager'),
                    (string) $warnings[0]
                )) . '</span>';
            }
            echo '</span></summary><div class="vms-edr-mobile-body"><dl>';
            echo '<dt>' . esc_html__('Customer', 'backstage-venue-manager') . '</dt><dd><strong>' . esc_html((string) ($reservation['customer_name'] ?? 'Customer')) . '</strong>';
            if (!empty($reservation['customer_email'])) {
                echo '<small>' . esc_html((string) $reservation['customer_email']) . '</small>';
            }
            echo '</dd><dt>' . esc_html__('Party', 'backstage-venue-manager') . '</dt><dd>' . esc_html(sprintf(
                /* translators: %d: Number of guests in the reservation party. */
                _n('%d guest', '%d guests', $party_size, 'backstage-venue-manager'),
                $party_size
            )) . '</dd>';
            echo '<dt>' . esc_html__('Qty', 'backstage-venue-manager') . '</dt><dd>' . esc_html((string) (int) ($reservation['qty'] ?? 0)) . '</dd>';
            echo '<dt>' . esc_html__('Order', 'backstage-venue-manager') . '</dt><dd>' . esc_html((string) ($reservation['reference'] ?? '')) . '</dd></dl>';
            if (count($warnings) > 1) {
                echo '<ul class="vms-edr-mobile-warning-list">';
                foreach (array_slice($warnings, 1) as $warning) {
                    echo '<li>' . esc_html((string) $warning) . '</li>';
                }
                echo '</ul>';
            }
            echo '</div></details>';
        }
        echo '</div></section>';
    }
}

if (!function_exists('bvmgr_event_day_report_render_issues')) {
    function bvmgr_event_day_report_render_issues(array $model): void
    {
        $partitioned = bvmgr_event_day_report_partition_issues((array) ($model['issues'] ?? array()));
        $issues = $partitioned['actionable'];
        echo '<section class="vms-edr-panel" id="issues" data-vms-edr-panel="issues">';
        echo '<div class="vms-edr-section-heading"><div><p class="vms-edr-kicker">' . esc_html__('Issues', 'backstage-venue-manager') . '</p><h2>' . esc_html__('Actionable reconciliation issues', 'backstage-venue-manager') . '</h2></div></div>';
        if (empty($issues)) {
            echo '<p class="vms-edr-good">' . esc_html__('No actionable reconciliation issues detected.', 'backstage-venue-manager') . '</p></section>';
            return;
        }
        echo '<ol class="vms-edr-issues">';
        foreach ($issues as $issue) {
            echo '<li class="is-' . esc_attr((string) ($issue['severity'] ?? 'warning')) . '"><strong>' . esc_html((string) ($issue['message'] ?? 'Issue')) . '</strong>';
            if (!empty($issue['context'])) {
                echo '<span>' . esc_html((string) $issue['context']) . '</span>';
            }
            echo '</li>';
        }
        echo '</ol></section>';
    }
}

if (!function_exists('bvmgr_event_day_report_render_information')) {
    function bvmgr_event_day_report_render_information(array $model, bool $compact = false): void
    {
        $partitioned = bvmgr_event_day_report_partition_issues((array) ($model['issues'] ?? array()));
        $groups = bvmgr_event_day_report_group_information($partitioned['information']);
        echo '<section class="vms-edr-panel vms-edr-information-panel' . ($compact ? ' is-compact' : '') . '" id="information" data-vms-edr-panel="information">';
        if (!$compact) {
            echo '<div class="vms-edr-section-heading"><div><p class="vms-edr-kicker">' . esc_html__('Information', 'backstage-venue-manager') . '</p><h2>' . esc_html__('Historical and data-quality notices', 'backstage-venue-manager') . '</h2></div></div>';
        }
        if (empty($groups)) {
            echo '<p class="vms-edr-good">' . esc_html__('No informational notices detected.', 'backstage-venue-manager') . '</p></section>';
            return;
        }
        echo '<ol class="vms-edr-issues vms-edr-information-groups">';
        foreach ($groups as $group) {
            $count = max(1, (int) ($group['count'] ?? 1));
            $item_label = $count === 1 ? (string) ($group['item_singular'] ?? 'affected record') : (string) ($group['item_plural'] ?? 'affected records');
            echo '<li class="is-info"><strong>' . esc_html($count . ' ' . $item_label) . '</strong>';
            echo '<span>' . esc_html((string) ($group['message'] ?? 'Information')) . '</span>';
            if (!$compact && (!empty($group['records']) || !empty($group['contexts']))) {
                echo '<details class="vms-edr-info-context"><summary>' . esc_html(sprintf(
                    /* translators: %d: Number of affected records. */
                    __('Show affected order / seat context (%d)', 'backstage-venue-manager'),
                    $count
                )) . '</summary><ul>';
                if (!empty($group['records'])) {
                    foreach ((array) $group['records'] as $record) {
                        $reference = trim((string) ($record['reference'] ?? ''));
                        $seats = array_values(array_map('intval', (array) ($record['seats'] ?? array())));
                        $label = $reference;
                        if (!empty($seats)) {
                            $label .= ' · ' . sprintf(
                                /* translators: %s: Comma-separated seat numbers. */
                                _n('seat %s', 'seats %s', count($seats), 'backstage-venue-manager'),
                                implode(', ', $seats)
                            );
                        }
                        echo '<li>' . esc_html($label) . '</li>';
                    }
                } else {
                    foreach ((array) $group['contexts'] as $context) {
                        echo '<li>' . esc_html((string) $context) . '</li>';
                    }
                }
                echo '</ul></details>';
            }
            echo '</li>';
        }
        echo '</ol></section>';
    }
}

if (!function_exists('bvmgr_event_day_report_render_styles')) {
    function bvmgr_event_day_report_render_styles(): void
    {
        wp_enqueue_style(
            'bvmgr-event-day-report',
            BVMGR_PLUGIN_URL . 'assets/css/vms-event-day-report.css',
            array(),
            BVMGR_VERSION
        );
        wp_print_styles('bvmgr-event-day-report');
    }
}

if (!function_exists('bvmgr_event_day_report_print_date_token')) {
    function bvmgr_event_day_report_print_date_token(array $plan): string
    {
        $event_date = trim((string) ($plan['event_date'] ?? ''));
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $event_date, $matches)) {
            return '';
        }
        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        if (!checkdate($month, $day, $year)) {
            return '';
        }
        return $matches[2] . $matches[3] . substr($matches[1], -2);
    }
}

if (!function_exists('bvmgr_event_day_report_safe_title_part')) {
    function bvmgr_event_day_report_safe_title_part(string $value): string
    {
        $value = wp_strip_all_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
        $value = str_replace(array('/', '\\', ':', '*', '?', '"', '<', '>', '|'), ' ', $value);
        $value = trim((string) preg_replace('/\s+/u', ' ', $value), " .\t\n\r\0\x0B");
        return $value !== '' ? $value : __('Event', 'backstage-venue-manager');
    }
}

if (!function_exists('bvmgr_event_day_report_document_title')) {
    function bvmgr_event_day_report_document_title(array $model, string $mode, bool $print): string
    {
        $plan = (array) ($model['plan'] ?? array());
        if (!$print) {
            return trim((string) ($plan['title'] ?? 'Event')) . ' — ' . __('Event Day Report', 'backstage-venue-manager');
        }

        $report_types = array(
            'guests' => __('Event Day Guest List', 'backstage-venue-manager'),
            'reservations' => __('Event Day Reservations', 'backstage-venue-manager'),
            'full' => __('Event Day Report', 'backstage-venue-manager'),
        );
        $parts = array_filter(array(
            bvmgr_event_day_report_print_date_token($plan),
            bvmgr_event_day_report_safe_title_part((string) ($plan['title'] ?? 'Event')),
            (string) ($report_types[$mode] ?? $report_types['full']),
        ));
        return implode(' - ', $parts);
    }
}

if (!function_exists('bvmgr_event_day_report_render_document')) {
    function bvmgr_event_day_report_render_document(array $model, string $mode = 'full', bool $print = false): void
    {
        $plan = (array) ($model['plan'] ?? array());
        $event_plan_id = (int) ($plan['event_plan_id'] ?? 0);
        $mode = in_array($mode, array('guests', 'reservations', 'issues', 'information', 'full'), true) ? $mode : 'full';
        $partitioned = bvmgr_event_day_report_partition_issues((array) ($model['issues'] ?? array()));
        $document_title = bvmgr_event_day_report_document_title($model, $mode, $print);
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<meta name="robots" content="noindex,noarchive"><title>' . esc_html($document_title) . '</title>';
        bvmgr_event_day_report_render_styles();
        echo '</head><body data-vms-edr-auto-print="' . ($print ? '1' : '0') . '"><main class="vms-edr">';
        if ($print) {
            echo '<button class="vms-edr-print-now" type="button" data-vms-edr-print-now>' . esc_html__('Print now', 'backstage-venue-manager') . '</button>';
        }
        bvmgr_event_day_report_render_header($model);
        if (!$print) {
            echo '<div class="vms-edr-toolbar"><nav class="vms-edr-tabs" aria-label="' . esc_attr__('Report views', 'backstage-venue-manager') . '">';
            echo '<button type="button" data-vms-edr-tab="guests" aria-selected="true">' . esc_html__('Guest List', 'backstage-venue-manager') . '</button>';
            echo '<button type="button" data-vms-edr-tab="reservations" aria-selected="false">' . esc_html__('Reservations', 'backstage-venue-manager') . '</button>';
            echo '<button type="button" data-vms-edr-tab="issues" aria-selected="false">' . esc_html(sprintf(
                /* translators: %d: Number of actionable report issues. */
                __('Issues (%d)', 'backstage-venue-manager'),
                count($partitioned['actionable'])
            )) . '</button>';
            echo '<button type="button" data-vms-edr-tab="information" aria-selected="false">' . esc_html(sprintf(
                /* translators: %d: Number of informational report notices. */
                __('Info (%d)', 'backstage-venue-manager'),
                count($partitioned['information'])
            )) . '</button></nav>';
            echo '<div class="vms-edr-print-actions"><a class="button" target="_blank" rel="noopener" href="' . esc_url(bvmgr_event_day_report_url($event_plan_id, array('print' => 'guests'))) . '">' . esc_html__('Print Guest List', 'backstage-venue-manager') . '</a>';
            echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url(bvmgr_event_day_report_url($event_plan_id, array('print' => 'reservations'))) . '">' . esc_html__('Print Reservations', 'backstage-venue-manager') . '</a>';
            echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url(bvmgr_event_day_report_url($event_plan_id, array('print' => 'full'))) . '">' . esc_html__('Print Full Event Report', 'backstage-venue-manager') . '</a></div>';
            echo '<details class="vms-edr-mobile-print-menu"><summary>' . esc_html__('Print', 'backstage-venue-manager') . '</summary><div class="vms-edr-mobile-print-links">';
            echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url(bvmgr_event_day_report_url($event_plan_id, array('print' => 'guests'))) . '">' . esc_html__('Guest List', 'backstage-venue-manager') . '</a>';
            echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url(bvmgr_event_day_report_url($event_plan_id, array('print' => 'reservations'))) . '">' . esc_html__('Reservations', 'backstage-venue-manager') . '</a>';
            echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url(bvmgr_event_day_report_url($event_plan_id, array('print' => 'full'))) . '">' . esc_html__('Full Event Report', 'backstage-venue-manager') . '</a></div></details></div>';
        }
        if ($mode === 'full' || $mode === 'guests') {
            bvmgr_event_day_report_render_guests($model, $print);
        }
        if ($mode === 'full' || $mode === 'reservations') {
            bvmgr_event_day_report_render_reservations($model, $print);
        }
        if ($print) {
            $scope = $mode === 'reservations' ? 'reservations' : ($mode === 'guests' ? 'guests' : 'full');
            $scoped_model = $model;
            $scoped_model['issues'] = bvmgr_event_day_report_filter_issues((array) ($model['issues'] ?? array()), $scope);
            $scoped_partitioned = bvmgr_event_day_report_partition_issues((array) $scoped_model['issues']);
            if ($mode === 'full' || !empty($scoped_partitioned['actionable'])) {
                bvmgr_event_day_report_render_issues($scoped_model);
            }
            if ($mode === 'full') {
                bvmgr_event_day_report_render_information($scoped_model);
            } elseif ($mode === 'guests' && !empty($scoped_partitioned['information'])) {
                bvmgr_event_day_report_render_information($scoped_model, true);
            }
        } else {
            if ($mode === 'full' || $mode === 'issues') {
                bvmgr_event_day_report_render_issues($model);
            }
            if ($mode === 'full' || $mode === 'information') {
                bvmgr_event_day_report_render_information($model);
            }
        }
        echo '</main>';
        wp_enqueue_script(
            'bvmgr-event-day-report',
            BVMGR_PLUGIN_URL . 'assets/js/vms-event-day-report.js',
            array(),
            BVMGR_VERSION,
            true
        );
        wp_print_scripts('bvmgr-event-day-report');
        echo '</body></html>';
    }
}

if (!function_exists('bvmgr_event_day_report_handle_request')) {
    function bvmgr_event_day_report_handle_request(): void
    {
        $event_plan_id = isset($_GET['event_plan_id']) ? absint(wp_unslash($_GET['event_plan_id'])) : 0;
        $capability = function_exists('bvmgr_admission_manage_capability') ? bvmgr_admission_manage_capability() : 'manage_options';
        if (!current_user_can($capability)) {
            wp_die(esc_html__('Access denied.', 'backstage-venue-manager'), esc_html__('Event Day Report', 'backstage-venue-manager'), array('response' => 403));
        }
        if ($event_plan_id <= 0 || get_post_type($event_plan_id) !== 'vms_event_plan') {
            wp_die(esc_html__('Event Plan not found.', 'backstage-venue-manager'), esc_html__('Event Day Report', 'backstage-venue-manager'), array('response' => 404));
        }
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field((string) wp_unslash($_GET['_wpnonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, bvmgr_event_day_report_nonce_action($event_plan_id))) {
            wp_die(esc_html__('Invalid report request.', 'backstage-venue-manager'), esc_html__('Event Day Report', 'backstage-venue-manager'), array('response' => 403));
        }

        $print_mode = isset($_GET['print']) ? sanitize_key((string) wp_unslash($_GET['print'])) : '';
        $print = in_array($print_mode, array('guests', 'reservations', 'full'), true);
        $mode = $print ? $print_mode : 'full';
        $model = bvmgr_event_day_report_build_model($event_plan_id);
        if (empty($model)) {
            wp_die(esc_html__('The selected Event Plan could not be read.', 'backstage-venue-manager'), esc_html__('Event Day Report', 'backstage-venue-manager'), array('response' => 404));
        }

        nocache_headers();
        header('Content-Type: text/html; charset=' . get_option('blog_charset', 'UTF-8'));
        header('X-Robots-Tag: noindex, noarchive', true);
        header('X-Content-Type-Options: nosniff', true);
        if (function_exists('bvmgr_admission_audit_log')) {
            bvmgr_admission_audit_log($event_plan_id, null, $print ? 'event_day_report_print' : 'event_day_report_view', get_current_user_id(), 'admin', array(
                'report_mode' => $mode,
                'party_count' => count((array) ($model['parties'] ?? array())),
                'reservation_count' => count((array) ($model['reservations'] ?? array())),
                'issue_count' => count((array) ($model['issues'] ?? array())),
            ));
        }
        bvmgr_event_day_report_render_document($model, $mode, $print);
        exit;
    }
}
add_action('admin_post_vms_event_day_report', 'bvmgr_event_day_report_handle_request');
