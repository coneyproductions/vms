<?php

defined('ABSPATH') || exit;

/**
 * Published Event Plan occurrence protection and the canonical reschedule service.
 *
 * Purchase-time snapshots are deliberately immutable. A successful operation writes
 * separate effective-occurrence metadata to Woo order items and an append-only plan
 * history entry so both the original purchase context and the current event context
 * remain available.
 */

if (!function_exists('bvmgr_event_occurrence_protected_meta_keys')) {
    function bvmgr_event_occurrence_protected_meta_keys(): array
    {
        return array(
            '_vms_event_date',
            '_vms_start_time',
            '_vms_end_time',
            '_vms_event_plan_start_datetime',
            '_vms_event_plan_end_datetime',
        );
    }
}

if (!function_exists('bvmgr_event_occurrence_is_published')) {
    function bvmgr_event_occurrence_is_published(int $plan_id): bool
    {
        $plan_id = absint($plan_id);
        return $plan_id > 0
            && get_post_type($plan_id) === 'vms_event_plan'
            && sanitize_key((string) get_post_meta($plan_id, '_vms_event_plan_status', true)) === 'published';
    }
}

if (!function_exists('bvmgr_event_occurrence_write_is_authorized')) {
    function bvmgr_event_occurrence_write_is_authorized(): bool
    {
        return !empty($GLOBALS['bvmgr_event_occurrence_write_depth']);
    }
}

if (!function_exists('bvmgr_event_occurrence_authorized_write')) {
    function bvmgr_event_occurrence_authorized_write(callable $callback)
    {
        $GLOBALS['bvmgr_event_occurrence_write_depth'] = max(0, (int) ($GLOBALS['bvmgr_event_occurrence_write_depth'] ?? 0)) + 1;
        try {
            return $callback();
        } finally {
            $GLOBALS['bvmgr_event_occurrence_write_depth'] = max(0, (int) $GLOBALS['bvmgr_event_occurrence_write_depth'] - 1);
        }
    }
}

if (!function_exists('bvmgr_event_occurrence_guard_meta_update')) {
    function bvmgr_event_occurrence_guard_meta_update($check, int $object_id, string $meta_key, $meta_value, $prev_value)
    {
        if ($check !== null || bvmgr_event_occurrence_write_is_authorized()) {
            return $check;
        }
        if (!in_array($meta_key, bvmgr_event_occurrence_protected_meta_keys(), true)) {
            return $check;
        }
        if (!bvmgr_event_occurrence_is_published($object_id)) {
            return $check;
        }

        $current = get_post_meta($object_id, $meta_key, true);
        if ((string) $current === (string) $meta_value) {
            return $check;
        }

        $GLOBALS['bvmgr_event_occurrence_last_blocked_write'] = array(
            'plan_id' => $object_id,
            'meta_key' => $meta_key,
            'attempted_value' => is_scalar($meta_value) ? (string) $meta_value : '',
        );
        do_action('bvmgr_event_occurrence_blocked_write', $object_id, $meta_key, $meta_value, $current);
        return false;
    }
}
add_filter('update_post_metadata', 'bvmgr_event_occurrence_guard_meta_update', 10, 5);

if (!function_exists('bvmgr_event_occurrence_guard_meta_add')) {
    function bvmgr_event_occurrence_guard_meta_add($check, int $object_id, string $meta_key, $meta_value, bool $unique)
    {
        if ($check !== null || bvmgr_event_occurrence_write_is_authorized()) {
            return $check;
        }
        if (!in_array($meta_key, bvmgr_event_occurrence_protected_meta_keys(), true)) {
            return $check;
        }
        if (!bvmgr_event_occurrence_is_published($object_id)) {
            return $check;
        }

        $GLOBALS['bvmgr_event_occurrence_last_blocked_write'] = array(
            'plan_id' => $object_id,
            'meta_key' => $meta_key,
            'attempted_value' => is_scalar($meta_value) ? (string) $meta_value : '',
        );
        do_action('bvmgr_event_occurrence_blocked_write', $object_id, $meta_key, $meta_value, get_post_meta($object_id, $meta_key, true));
        return false;
    }
}
add_filter('add_post_metadata', 'bvmgr_event_occurrence_guard_meta_add', 10, 5);

if (!function_exists('bvmgr_event_occurrence_guard_meta_delete')) {
    function bvmgr_event_occurrence_guard_meta_delete($delete, $object_ids, string $meta_key, $meta_value, $delete_all)
    {
        if ($delete !== null || bvmgr_event_occurrence_write_is_authorized()) {
            return $delete;
        }
        if (!in_array($meta_key, bvmgr_event_occurrence_protected_meta_keys(), true)) {
            return $delete;
        }
        foreach ((array) $object_ids as $object_id) {
            if (bvmgr_event_occurrence_is_published(absint($object_id))) {
                $GLOBALS['bvmgr_event_occurrence_last_blocked_write'] = array(
                    'plan_id' => absint($object_id),
                    'meta_key' => $meta_key,
                    'attempted_value' => '[delete]',
                );
                return false;
            }
        }
        return $delete;
    }
}
add_filter('delete_post_metadata', 'bvmgr_event_occurrence_guard_meta_delete', 10, 5);

if (!function_exists('bvmgr_event_occurrence_guard_meta_update_by_mid')) {
    function bvmgr_event_occurrence_guard_meta_update_by_mid($check, int $meta_id, $meta_value, $meta_key)
    {
        if ($check !== null || bvmgr_event_occurrence_write_is_authorized()) {
            return $check;
        }
        $meta = get_metadata_by_mid('post', $meta_id);
        if (!$meta) {
            return $check;
        }
        $object_id = absint($meta->post_id ?? 0);
        $original_key = (string) ($meta->meta_key ?? '');
        $target_key = $meta_key === false ? $original_key : (string) $meta_key;
        $protected = bvmgr_event_occurrence_protected_meta_keys();
        if (!in_array($original_key, $protected, true) && !in_array($target_key, $protected, true)) {
            return $check;
        }
        if (!bvmgr_event_occurrence_is_published($object_id)) {
            return $check;
        }
        if ($target_key === $original_key && (string) ($meta->meta_value ?? '') === (string) $meta_value) {
            return $check;
        }

        $GLOBALS['bvmgr_event_occurrence_last_blocked_write'] = array(
            'plan_id' => $object_id,
            'meta_key' => $target_key,
            'attempted_value' => is_scalar($meta_value) ? (string) $meta_value : '',
        );
        do_action('bvmgr_event_occurrence_blocked_write', $object_id, $target_key, $meta_value, $meta->meta_value ?? '');
        return false;
    }
}
add_filter('update_post_metadata_by_mid', 'bvmgr_event_occurrence_guard_meta_update_by_mid', 10, 4);

if (!function_exists('bvmgr_event_occurrence_guard_meta_delete_by_mid')) {
    function bvmgr_event_occurrence_guard_meta_delete_by_mid($check, int $meta_id)
    {
        if ($check !== null || bvmgr_event_occurrence_write_is_authorized()) {
            return $check;
        }
        $meta = get_metadata_by_mid('post', $meta_id);
        if (!$meta) {
            return $check;
        }
        $object_id = absint($meta->post_id ?? 0);
        $meta_key = (string) ($meta->meta_key ?? '');
        if (!in_array($meta_key, bvmgr_event_occurrence_protected_meta_keys(), true)
            || !bvmgr_event_occurrence_is_published($object_id)) {
            return $check;
        }

        $GLOBALS['bvmgr_event_occurrence_last_blocked_write'] = array(
            'plan_id' => $object_id,
            'meta_key' => $meta_key,
            'attempted_value' => '[delete-by-mid]',
        );
        return false;
    }
}
add_filter('delete_post_metadata_by_mid', 'bvmgr_event_occurrence_guard_meta_delete_by_mid', 10, 2);

if (!function_exists('bvmgr_event_occurrence_lock_editor_request')) {
    function bvmgr_event_occurrence_lock_editor_request(int $plan_id, array $request): array
    {
        if (!bvmgr_event_occurrence_is_published($plan_id) || bvmgr_event_occurrence_write_is_authorized()) {
            return array('request' => $request, 'blocked' => false, 'fields' => array());
        }

        $map = array(
            'vms_event_date' => '_vms_event_date',
            'vms_start_time' => '_vms_start_time',
            'vms_end_time' => '_vms_end_time',
        );
        $blocked = array();
        foreach ($map as $field => $meta_key) {
            if (!array_key_exists($field, $request)) {
                continue;
            }
            $stored = (string) get_post_meta($plan_id, $meta_key, true);
            $attempted = sanitize_text_field(wp_unslash((string) $request[$field]));
            if ($attempted !== $stored) {
                $blocked[] = $field;
            }
            $request[$field] = $stored;
        }

        return array('request' => $request, 'blocked' => !empty($blocked), 'fields' => $blocked);
    }
}

if (!function_exists('bvmgr_event_occurrence_parse_local')) {
    function bvmgr_event_occurrence_parse_local(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        foreach (array('Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i') as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!' . $format, $value, $tz);
            $errors = DateTimeImmutable::getLastErrors();
            if ($parsed instanceof DateTimeImmutable && ($errors === false || (empty($errors['warning_count']) && empty($errors['error_count'])))) {
                return $parsed;
            }
        }
        return null;
    }
}

if (!function_exists('bvmgr_event_occurrence_from_parts')) {
    function bvmgr_event_occurrence_from_parts(string $date, string $start_time, string $end_time): array
    {
        $out = array('valid' => false, 'start' => null, 'end' => null, 'error' => 'invalid_schedule');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            || !preg_match('/^\d{2}:\d{2}$/', $start_time)
            || !preg_match('/^\d{2}:\d{2}$/', $end_time)) {
            return $out;
        }
        $start = bvmgr_event_occurrence_parse_local($date . ' ' . $start_time);
        $end = bvmgr_event_occurrence_parse_local($date . ' ' . $end_time);
        if (!($start instanceof DateTimeImmutable) || !($end instanceof DateTimeImmutable)) {
            return $out;
        }
        if ($end <= $start) {
            $end = $end->modify('+1 day');
        }
        if ($end <= $start || ($end->getTimestamp() - $start->getTimestamp()) > DAY_IN_SECONDS) {
            return $out;
        }
        return array('valid' => true, 'start' => $start, 'end' => $end, 'error' => '');
    }
}

if (!function_exists('bvmgr_event_occurrence_for_plan')) {
    function bvmgr_event_occurrence_for_plan(int $plan_id): array
    {
        return bvmgr_event_occurrence_from_parts(
            trim((string) get_post_meta($plan_id, '_vms_event_date', true)),
            trim((string) get_post_meta($plan_id, '_vms_start_time', true)),
            trim((string) get_post_meta($plan_id, '_vms_end_time', true))
        );
    }
}

if (!function_exists('bvmgr_event_occurrence_payload')) {
    function bvmgr_event_occurrence_payload(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $utc = new DateTimeZone('UTC');
        return array(
            'date' => $start->format('Y-m-d'),
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'start_local' => $start->format('Y-m-d H:i:s'),
            'end_local' => $end->format('Y-m-d H:i:s'),
            'start_utc' => $start->setTimezone($utc)->format('Y-m-d H:i:s'),
            'end_utc' => $end->setTimezone($utc)->format('Y-m-d H:i:s'),
            'timezone' => $start->getTimezone()->getName(),
        );
    }
}

if (!function_exists('bvmgr_event_occurrence_snapshot_date')) {
    function bvmgr_event_occurrence_snapshot_date(int $order_item_id): string
    {
        if ($order_item_id <= 0 || !function_exists('wc_get_order_item_meta')) {
            return '';
        }
        $effective = trim((string) wc_get_order_item_meta($order_item_id, '_vms_effective_event_start_local', true));
        $effective_dt = bvmgr_event_occurrence_parse_local($effective);
        if ($effective_dt instanceof DateTimeImmutable) {
            return $effective_dt->format('Y-m-d');
        }
        $date = trim((string) wc_get_order_item_meta($order_item_id, '_vms_event_date_snapshot', true));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        $when = trim((string) wc_get_order_item_meta($order_item_id, '_vms_event_when_snapshot', true));
        if ($when !== '') {
            try {
                return (new DateTimeImmutable($when, wp_timezone()))->format('Y-m-d');
            } catch (Throwable $throwable) {
                return '';
            }
        }
        return '';
    }
}

if (!function_exists('bvmgr_event_occurrence_order_item_name_date')) {
    function bvmgr_event_occurrence_order_item_name_date(int $order_item_id): string
    {
        if ($order_item_id <= 0 || !class_exists('WC_Order_Item_Product')) {
            return '';
        }
        $item = new WC_Order_Item_Product($order_item_id);
        $name = trim((string) $item->get_name());
        if (preg_match('/^(\d{4}-\d{2}-\d{2})\s+\d{2}:\d{2}\s+(?:-|–|—)\s+/u', $name, $matches)) {
            return (string) $matches[1];
        }
        if (preg_match('/\(([A-Z][a-z]{2}\s+\d{1,2},\s+\d{4})\)$/u', $name, $matches)) {
            try {
                return (new DateTimeImmutable((string) $matches[1], wp_timezone()))->format('Y-m-d');
            } catch (Throwable $throwable) {
                return '';
            }
        }
        return '';
    }
}

if (!function_exists('bvmgr_event_occurrence_collect_product_ids')) {
    function bvmgr_event_occurrence_collect_product_ids(int $plan_id, int $tec_event_id, array $rows): array
    {
        $ids = array();
        foreach ($rows as $row) {
            $ids[] = absint($row['product_id'] ?? 0);
        }
        if ($tec_event_id > 0 && function_exists('bvmgr_ticketing_b_get_event_ticket_products')) {
            $ids = array_merge($ids, (array) bvmgr_ticketing_b_get_event_ticket_products($tec_event_id));
        }
        $plan_products = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'meta_query' => array(
                array('key' => '_vms_event_plan_id', 'value' => $plan_id, 'compare' => '='),
            ),
        ));
        $ids = array_merge($ids, is_array($plan_products) ? $plan_products : array());
        return array_values(array_unique(array_filter(array_map('absint', $ids))));
    }
}

if (!function_exists('bvmgr_event_occurrence_custom_admissions_impact')) {
    function bvmgr_event_occurrence_custom_admissions_impact(int $plan_id): array
    {
        global $wpdb;
        $out = array('rows' => 0, 'admission_units' => 0);
        if (!function_exists('bvmgr_admission_table_entries')) {
            return $out;
        }
        $table = (string) bvmgr_admission_table_entries();
        if ($table === '') {
            return $out;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only impact summary for the custom admissions table.
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ((string) $exists !== $table) {
            return $out;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only impact summary for the custom admissions table.
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS row_count, COALESCE(SUM(party_size), 0) AS unit_count FROM %i WHERE event_plan_id = %d AND status <> 'canceled'",
            $table,
            $plan_id
        ), ARRAY_A);
        $out['rows'] = max(0, (int) ($row['row_count'] ?? 0));
        $out['admission_units'] = max(0, (int) ($row['unit_count'] ?? 0));
        return $out;
    }
}

if (!function_exists('bvmgr_event_occurrence_preview')) {
    function bvmgr_event_occurrence_preview(int $plan_id, string $expected_old_start, string $new_start, string $reason): array
    {
        $plan_id = absint($plan_id);
        $reason = sanitize_key($reason);
        $allowed_reasons = array('date_correction', 'rescheduled');
        $preview = array(
            'allowed' => false,
            'plan_id' => $plan_id,
            'plan_title' => $plan_id > 0 ? (string) get_the_title($plan_id) : '',
            'reason' => $reason,
            'mode' => '',
            'canonical' => array(),
            'old' => array(),
            'new' => array(),
            'tec_event_id' => 0,
            'external_ticketing' => false,
            'warnings' => array(),
            'ambiguities' => array(),
            'rows' => array(),
            'product_ids' => array(),
            'attendee_ids' => array(),
            'notification_rows' => array(),
            'counts' => array(
                'orders' => 0,
                'line_items' => 0,
                'admission_units' => 0,
                'reservation_units' => 0,
                'free_units' => 0,
                'paid_units' => 0,
                'registered_assignments' => 0,
                'numbered_reservation_units' => 0,
                'multi_quantity_lines' => 0,
                'customers' => 0,
                'custom_admission_rows' => 0,
                'custom_admission_units' => 0,
            ),
            'contacts' => array(),
            'categories' => array('admissions' => array(), 'reservations' => array()),
        );

        $post = get_post($plan_id);
        if (!$post || $post->post_type !== 'vms_event_plan') {
            $preview['ambiguities'][] = 'Event Plan not found.';
            return $preview;
        }
        if (!bvmgr_event_occurrence_is_published($plan_id)) {
            $preview['ambiguities'][] = 'The Event Plan is not in the published workflow state.';
            return $preview;
        }
        if (!in_array($reason, $allowed_reasons, true)) {
            $preview['ambiguities'][] = 'Reason must be date_correction or rescheduled.';
            return $preview;
        }

        $canonical = bvmgr_event_occurrence_for_plan($plan_id);
        $old_start_dt = bvmgr_event_occurrence_parse_local($expected_old_start);
        $new_start_dt = bvmgr_event_occurrence_parse_local($new_start);
        if (empty($canonical['valid']) || !($old_start_dt instanceof DateTimeImmutable) || !($new_start_dt instanceof DateTimeImmutable)) {
            $preview['ambiguities'][] = 'Canonical, expected-old, or new occurrence is invalid.';
            return $preview;
        }
        if ($old_start_dt == $new_start_dt) {
            $preview['ambiguities'][] = 'Expected-old and new occurrence must differ.';
            return $preview;
        }

        $duration = $canonical['end']->getTimestamp() - $canonical['start']->getTimestamp();
        $old_end_dt = $old_start_dt->modify('+' . $duration . ' seconds');
        $new_end_dt = $new_start_dt->modify('+' . $duration . ' seconds');
        $preview['canonical'] = bvmgr_event_occurrence_payload($canonical['start'], $canonical['end']);
        $preview['old'] = bvmgr_event_occurrence_payload($old_start_dt, $old_end_dt);
        $preview['new'] = bvmgr_event_occurrence_payload($new_start_dt, $new_end_dt);

        if ($canonical['start']->format('Y-m-d H:i') === $old_start_dt->format('Y-m-d H:i')) {
            $preview['mode'] = 'forward';
        } elseif ($canonical['start']->format('Y-m-d H:i') === $new_start_dt->format('Y-m-d H:i')) {
            $preview['mode'] = 'repair';
        } else {
            $preview['ambiguities'][] = 'Canonical occurrence matches neither expected-old nor new occurrence.';
            return $preview;
        }

        $tec_event_id = absint(get_post_meta($plan_id, '_vms_tec_event_id', true));
        $preview['tec_event_id'] = $tec_event_id;
        if ($tec_event_id <= 0 || get_post_type($tec_event_id) !== 'tribe_events') {
            $preview['ambiguities'][] = 'The linked calendar event is missing or invalid.';
        }

        $preview['external_ticketing'] = function_exists('bvmgr_event_plan_is_externally_ticketed')
            && bvmgr_event_plan_is_externally_ticketed($plan_id);
        if ($preview['external_ticketing']) {
            $preview['warnings'][] = 'External ticket provider dates are not changed by this operation and must be updated separately.';
        }

        $resolver_available = class_exists('BVMGR_Ticket_Revenue_Service')
            && function_exists('wc_get_orders')
            && class_exists('WooCommerce');
        $result = $resolver_available
            ? BVMGR_Ticket_Revenue_Service::get_sales_result(array(
                'event_plan_ids' => array($plan_id),
                'order_statuses' => array('processing', 'completed', 'refunded'),
                'include_unresolved' => true,
                'include_refunded_lines' => true,
            ))
            : array('rows' => array(), 'warnings' => array('Ticket sales resolver is unavailable.'));
        if (!$resolver_available) {
            $preview['ambiguities'][] = 'Ticket and order impact cannot be resolved because WooCommerce or the sales resolver is unavailable.';
        }
        foreach ((array) ($result['warnings'] ?? array()) as $warning) {
            if (trim((string) $warning) !== '') {
                $preview['warnings'][] = trim((string) $warning);
            }
        }
        if ((int) ($result['counts']['line_items_unresolved'] ?? 0) > 0) {
            $preview['ambiguities'][] = 'The ticket sales resolver returned one or more unresolved linked line items.';
        }

        $orders = array();
        $contacts = array();
        $notifications = array();
        foreach ((array) ($result['rows'] ?? array()) as $row) {
            if ((int) ($row['event_plan_id'] ?? 0) !== $plan_id) {
                continue;
            }
            $item_id = absint($row['order_item_id'] ?? 0);
            $qty = max(0, (int) ($row['qty'] ?? 0) - (int) ($row['refunded_qty'] ?? 0));
            if ($item_id <= 0 || $qty <= 0) {
                continue;
            }
            $snapshot_date = bvmgr_event_occurrence_snapshot_date($item_id);
            $name_date = bvmgr_event_occurrence_order_item_name_date($item_id);
            $effective_start = trim((string) wc_get_order_item_meta($item_id, '_vms_effective_event_start_local', true));
            $effective_dt = bvmgr_event_occurrence_parse_local($effective_start);
            $effective_date = $effective_dt instanceof DateTimeImmutable ? $effective_dt->format('Y-m-d') : '';
            $old_date = $old_start_dt->format('Y-m-d');
            $new_date = $new_start_dt->format('Y-m-d');
            $target = false;
            if ($effective_date !== '' && $effective_date === $old_date) {
                $target = true;
            } elseif ($effective_date !== '' && $effective_date === $new_date) {
                $target = false;
            } elseif ($effective_date !== '') {
                $preview['ambiguities'][] = sprintf('Order item %d has an unrecognized effective occurrence date (%s).', $item_id, $effective_date);
            } elseif ($snapshot_date === $old_date || $name_date === $old_date) {
                $target = true;
            } elseif ($snapshot_date === $new_date && ($name_date === '' || $name_date === $new_date)) {
                $target = false;
            } elseif ($snapshot_date === '' && $preview['mode'] === 'forward') {
                $target = true;
            } else {
                $preview['ambiguities'][] = sprintf('Order item %d has an unrecognized occurrence date (%s).', $item_id, $snapshot_date !== '' ? $snapshot_date : 'missing');
            }
            if (!$target) {
                continue;
            }

            $line_kind = sanitize_key((string) ($row['line_kind'] ?? ''));
            if (!in_array($line_kind, array('ticket', 'addon'), true)) {
                $preview['ambiguities'][] = sprintf('Order item %d has ambiguous entitlement type %s.', $item_id, $line_kind !== '' ? $line_kind : 'missing');
                continue;
            }
            $row['effective_quantity'] = $qty;
            $row['snapshot_date'] = $snapshot_date;
            $row['order_item_name_date'] = $name_date;
            $preview['rows'][] = $row;
            $orders[absint($row['order_id'] ?? 0)] = true;
            $email = sanitize_email((string) ($row['customer_email'] ?? ''));
            if ($email !== '') {
                $contacts[$email] = true;
            }
            $preview['counts']['line_items']++;
            if ($line_kind === 'addon') {
                $preview['counts']['reservation_units'] += $qty;
            } else {
                $preview['counts']['admission_units'] += $qty;
            }
            $category_label = trim((string) ($row['product_name'] ?? ''));
            if (function_exists('bvmgr_ticketing_v2_normalize_admin_ticket_title_for_match')) {
                $category_label = bvmgr_ticketing_v2_normalize_admin_ticket_title_for_match($category_label);
            }
            if ($category_label === '') {
                $category_label = $line_kind === 'addon' ? 'Reservation' : 'Admission';
            }
            $category_bucket = $line_kind === 'addon' ? 'reservations' : 'admissions';
            $preview['categories'][$category_bucket][$category_label] = (int) ($preview['categories'][$category_bucket][$category_label] ?? 0) + $qty;
            $order_id = absint($row['order_id'] ?? 0);
            $notification_key = $order_id . '|' . strtolower($email);
            if (!isset($notifications[$notification_key])) {
                $notifications[$notification_key] = array(
                    'order_id' => $order_id,
                    'customer_name' => sanitize_text_field((string) ($row['customer_name'] ?? '')),
                    'customer_email' => $email,
                    'entitlements' => array(),
                );
            }
            $notifications[$notification_key]['entitlements'][] = array(
                'order_item_id' => $item_id,
                'kind' => $line_kind,
                'label' => $category_label,
                'quantity' => $qty,
            );
            if ($line_kind === 'addon' && preg_match('/(?:#|\b(?:table|seat|space|spot)\s*)0*\d+\b/i', $category_label)) {
                $preview['counts']['numbered_reservation_units'] += $qty;
            }
            if ((int) ($row['line_total_cents'] ?? 0) > 0) {
                $preview['counts']['paid_units'] += $qty;
            } else {
                $preview['counts']['free_units'] += $qty;
            }
            if ($qty > 1) {
                $preview['counts']['multi_quantity_lines']++;
            }
            $assignments = json_decode((string) wc_get_order_item_meta($item_id, '_vms_claim_assignments', true), true);
            if (is_array($assignments)) {
                $preview['counts']['registered_assignments'] += count($assignments);
            }
            foreach ((array) ($row['attendee_ids'] ?? array()) as $attendee_id) {
                $attendee_id = absint($attendee_id);
                if ($attendee_id <= 0) {
                    continue;
                }
                $attendee_event_id = absint(get_post_meta($attendee_id, '_tribe_wooticket_event', true));
                if ($attendee_event_id <= 0) {
                    $attendee_event_id = absint(get_post_meta($attendee_id, '_tribe_rsvp_event', true));
                }
                if ($attendee_event_id !== $tec_event_id) {
                    $preview['ambiguities'][] = sprintf('Attendee %d does not link to calendar event %d.', $attendee_id, $tec_event_id);
                }
                $preview['attendee_ids'][] = $attendee_id;
            }
        }
        $preview['counts']['orders'] = count(array_filter(array_keys($orders)));
        $preview['counts']['customers'] = count($contacts);
        $preview['contacts'] = array_values(array_keys($contacts));
        $preview['notification_rows'] = array_values($notifications);
        $preview['attendee_ids'] = array_values(array_unique(array_filter(array_map('absint', $preview['attendee_ids']))));
        $preview['product_ids'] = bvmgr_event_occurrence_collect_product_ids($plan_id, $tec_event_id, $preview['rows']);

        foreach ($preview['product_ids'] as $product_id) {
            $product_plan_id = absint(get_post_meta($product_id, '_vms_event_plan_id', true));
            $product_event_id = absint(get_post_meta($product_id, '_vms_tec_event_id', true));
            if ($product_event_id <= 0) {
                $product_event_id = absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true));
            }
            if ($product_plan_id > 0 && $product_plan_id !== $plan_id) {
                $preview['ambiguities'][] = sprintf('Product %d links to a different Event Plan.', $product_id);
            }
            if ($product_event_id > 0 && $product_event_id !== $tec_event_id) {
                $preview['ambiguities'][] = sprintf('Product %d links to a different calendar event.', $product_id);
            }
        }

        $custom = bvmgr_event_occurrence_custom_admissions_impact($plan_id);
        $preview['counts']['custom_admission_rows'] = (int) $custom['rows'];
        $preview['counts']['custom_admission_units'] = (int) $custom['admission_units'];
        $preview['warnings'] = array_values(array_unique($preview['warnings']));
        $preview['ambiguities'] = array_values(array_unique($preview['ambiguities']));
        $preview['allowed'] = empty($preview['ambiguities']);
        return $preview;
    }
}

if (!function_exists('bvmgr_event_occurrence_shift_ticket_schedule')) {
    function bvmgr_event_occurrence_shift_ticket_schedule(array $config, DateTimeImmutable $old_start, DateTimeImmutable $new_start): array
    {
        $delta = $new_start->getTimestamp() - $old_start->getTimestamp();
        $old_date = $old_start->format('Y-m-d');
        $date_keys = array('sales_start', 'sales_end', 'early_price_start', 'early_price_end');
        $shift_row = static function (array $row) use ($date_keys, $old_date, $delta): array {
            foreach ($date_keys as $key) {
                $raw = trim((string) ($row[$key] ?? ''));
                if ($raw === '' || substr($raw, 0, 10) !== $old_date) {
                    continue;
                }
                $parsed = bvmgr_event_occurrence_parse_local($raw);
                if ($parsed instanceof DateTimeImmutable) {
                    $row[$key] = $parsed->modify(($delta >= 0 ? '+' : '') . $delta . ' seconds')->format('Y-m-d H:i:s');
                }
            }
            return $row;
        };

        if (isset($config['tickets']) && is_array($config['tickets'])) {
            foreach ($config['tickets'] as $index => $ticket) {
                if (is_array($ticket)) {
                    $config['tickets'][$index] = $shift_row($ticket);
                }
            }
        }
        if (isset($config['ga']) && is_array($config['ga'])) {
            $config['ga'] = $shift_row($config['ga']);
        }
        return $config;
    }
}

if (!function_exists('bvmgr_event_occurrence_effective_when_label')) {
    function bvmgr_event_occurrence_effective_when_label(DateTimeImmutable $start): string
    {
        return wp_date('D, M j, Y g:ia', $start->getTimestamp(), $start->getTimezone());
    }
}

if (!function_exists('bvmgr_event_occurrence_product_base_title')) {
    function bvmgr_event_occurrence_product_base_title(int $product_id): string
    {
        $title = trim((string) get_post_field('post_title', $product_id, 'raw'));
        if (function_exists('bvmgr_ticketing_v2_normalize_admin_ticket_title_for_match')) {
            $title = bvmgr_ticketing_v2_normalize_admin_ticket_title_for_match($title);
        }
        $title = trim(html_entity_decode(wp_strip_all_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        do {
            $before = $title;
            $title = trim((string) preg_replace('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}\s+(?:-|–|—)\s+/u', '', $title));
        } while ($title !== $before);
        return $title;
    }
}

if (!function_exists('bvmgr_event_occurrence_update_order_item')) {
    function bvmgr_event_occurrence_update_order_item(array $row, array $new_payload, string $operation_id): void
    {
        $item_id = absint($row['order_item_id'] ?? 0);
        if ($item_id <= 0 || !function_exists('wc_update_order_item_meta')) {
            throw new RuntimeException('Woo order item API is unavailable.');
        }

        $item = class_exists('WC_Order_Item_Product') ? new WC_Order_Item_Product($item_id) : null;
        if (!$item || !method_exists($item, 'get_id') || (int) $item->get_id() !== $item_id) {
            throw new RuntimeException(sprintf('Order item %d could not be loaded.', $item_id));
        }

        $current_name = (string) $item->get_name();
        if ((string) wc_get_order_item_meta($item_id, '_vms_original_order_item_name_snapshot', true) === '') {
            wc_update_order_item_meta($item_id, '_vms_original_order_item_name_snapshot', $current_name);
        }
        $base_name = function_exists('bvmgr_ticketing_v2_normalize_admin_ticket_title_for_match')
            ? bvmgr_ticketing_v2_normalize_admin_ticket_title_for_match($current_name)
            : trim((string) preg_replace('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}\s+(?:-|–|—)\s+/u', '', $current_name));
        if ($base_name !== '' && $base_name !== $current_name) {
            $item->set_name($base_name);
            $item->save();
        }

        $new_start = bvmgr_event_occurrence_parse_local((string) $new_payload['start_local']);
        if (!($new_start instanceof DateTimeImmutable)) {
            throw new RuntimeException('New effective occurrence could not be formatted.');
        }
        $when = bvmgr_event_occurrence_effective_when_label($new_start);
        wc_update_order_item_meta($item_id, '_vms_effective_event_date', (string) $new_payload['date']);
        wc_update_order_item_meta($item_id, '_vms_effective_event_when', $when);
        wc_update_order_item_meta($item_id, '_vms_effective_event_start_local', (string) $new_payload['start_local']);
        wc_update_order_item_meta($item_id, '_vms_effective_event_end_local', (string) $new_payload['end_local']);
        wc_update_order_item_meta($item_id, '_vms_effective_event_start_utc', (string) $new_payload['start_utc']);
        wc_update_order_item_meta($item_id, '_vms_effective_event_end_utc', (string) $new_payload['end_utc']);
        wc_update_order_item_meta($item_id, '_vms_occurrence_operation_id', $operation_id);
        wc_update_order_item_meta($item_id, __('When', 'backstage-venue-manager'), $when);

        if ((string) wc_get_order_item_meta($item_id, '_vms_effective_event_start_local', true) !== (string) $new_payload['start_local']) {
            throw new RuntimeException(sprintf('Order item %d failed effective-occurrence verification.', $item_id));
        }
    }
}

if (!function_exists('bvmgr_event_occurrence_invariant_snapshot')) {
    function bvmgr_event_occurrence_invariant_snapshot(array $preview): array
    {
        $snapshot = array('orders' => array(), 'items' => array(), 'attendees' => array(), 'products' => array());
        foreach ((array) ($preview['rows'] ?? array()) as $row) {
            $item_id = absint($row['order_item_id'] ?? 0);
            if ($item_id <= 0 || !class_exists('WC_Order_Item_Product')) {
                continue;
            }
            $item = new WC_Order_Item_Product($item_id);
            $snapshot['items'][$item_id] = array(
                'order_id' => absint($item->get_order_id()),
                'product_id' => absint($item->get_product_id()),
                'variation_id' => absint($item->get_variation_id()),
                'quantity' => (int) $item->get_quantity(),
                'subtotal' => (string) $item->get_subtotal(),
                'subtotal_tax' => (string) $item->get_subtotal_tax(),
                'total' => (string) $item->get_total(),
                'total_tax' => (string) $item->get_total_tax(),
                'assignments' => (string) $item->get_meta('_vms_claim_assignments', true),
                'date_snapshot' => (string) $item->get_meta('_vms_event_date_snapshot', true),
                'when_snapshot' => (string) $item->get_meta('_vms_event_when_snapshot', true),
                'title_snapshot' => (string) $item->get_meta('_vms_event_title_snapshot', true),
            );
            $order_id = absint($item->get_order_id());
            if ($order_id > 0 && !isset($snapshot['orders'][$order_id]) && function_exists('wc_get_order')) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $paid_at = $order->get_date_paid();
                    $snapshot['orders'][$order_id] = array(
                        'status' => (string) $order->get_status(),
                        'currency' => (string) $order->get_currency(),
                        'total' => (string) $order->get_total(),
                        'total_tax' => (string) $order->get_total_tax(),
                        'discount_total' => (string) $order->get_discount_total(),
                        'discount_tax' => (string) $order->get_discount_tax(),
                        'shipping_total' => (string) $order->get_shipping_total(),
                        'shipping_tax' => (string) $order->get_shipping_tax(),
                        'payment_method' => (string) $order->get_payment_method(),
                        'transaction_id' => (string) $order->get_transaction_id(),
                        'paid_at' => $paid_at ? (int) $paid_at->getTimestamp() : 0,
                    );
                }
            }
        }
        foreach ((array) ($preview['attendee_ids'] ?? array()) as $attendee_id) {
            $attendee_id = absint($attendee_id);
            $snapshot['attendees'][$attendee_id] = array(
                'event_id' => absint(get_post_meta($attendee_id, '_tribe_wooticket_event', true)),
                'product_id' => absint(get_post_meta($attendee_id, '_tribe_wooticket_product', true)),
                'order_id' => absint(get_post_meta($attendee_id, '_tribe_wooticket_order', true)),
                'order_item_id' => absint(get_post_meta($attendee_id, '_tribe_wooticket_order_item', true)),
                'checkedin' => get_post_meta($attendee_id, '_tribe_wooticket_checkedin', true),
                'security_code' => get_post_meta($attendee_id, '_tribe_wooticket_security_code', true),
            );
        }
        foreach ((array) ($preview['product_ids'] ?? array()) as $product_id) {
            $product_id = absint($product_id);
            $product = $product_id > 0 && function_exists('wc_get_product') ? wc_get_product($product_id) : null;
            if (!$product) {
                continue;
            }
            $snapshot['products'][$product_id] = array(
                'sku' => (string) $product->get_sku('edit'),
                'regular_price' => (string) $product->get_regular_price('edit'),
                'sale_price' => (string) $product->get_sale_price('edit'),
                'price' => (string) $product->get_price('edit'),
                'manage_stock' => (bool) $product->get_manage_stock('edit'),
                'stock_quantity' => $product->get_stock_quantity('edit'),
                'stock_status' => (string) $product->get_stock_status('edit'),
                'sold_individually' => (bool) $product->get_sold_individually('edit'),
                'total_sales' => (int) get_post_meta($product_id, 'total_sales', true),
                'event_plan_id' => absint(get_post_meta($product_id, '_vms_event_plan_id', true)),
                'tec_event_id' => absint(get_post_meta($product_id, '_vms_tec_event_id', true)),
                'native_event_id' => absint(get_post_meta($product_id, '_tribe_wooticket_for_event', true)),
                'product_role' => (string) get_post_meta($product_id, '_vms_product_role', true),
            );
        }
        return $snapshot;
    }
}

if (!function_exists('bvmgr_event_occurrence_verify_invariants')) {
    function bvmgr_event_occurrence_verify_invariants(array $before): array
    {
        $errors = array();
        foreach ((array) ($before['orders'] ?? array()) as $order_id => $expected) {
            $order = function_exists('wc_get_order') ? wc_get_order((int) $order_id) : null;
            if (!$order) {
                $errors[] = sprintf('Order %d disappeared.', $order_id);
                continue;
            }
            $paid_at = $order->get_date_paid();
            $actual = array(
                'status' => (string) $order->get_status(),
                'currency' => (string) $order->get_currency(),
                'total' => (string) $order->get_total(),
                'total_tax' => (string) $order->get_total_tax(),
                'discount_total' => (string) $order->get_discount_total(),
                'discount_tax' => (string) $order->get_discount_tax(),
                'shipping_total' => (string) $order->get_shipping_total(),
                'shipping_tax' => (string) $order->get_shipping_tax(),
                'payment_method' => (string) $order->get_payment_method(),
                'transaction_id' => (string) $order->get_transaction_id(),
                'paid_at' => $paid_at ? (int) $paid_at->getTimestamp() : 0,
            );
            if ($actual !== $expected) {
                $errors[] = sprintf('Order %d status, payment, tax, discount, shipping, currency, or total changed.', $order_id);
            }
        }
        foreach ((array) ($before['items'] ?? array()) as $item_id => $expected) {
            $item = class_exists('WC_Order_Item_Product') ? new WC_Order_Item_Product((int) $item_id) : null;
            if (!$item) {
                $errors[] = sprintf('Order item %d disappeared.', $item_id);
                continue;
            }
            $actual = array(
                'order_id' => absint($item->get_order_id()),
                'product_id' => absint($item->get_product_id()),
                'variation_id' => absint($item->get_variation_id()),
                'quantity' => (int) $item->get_quantity(),
                'subtotal' => (string) $item->get_subtotal(),
                'subtotal_tax' => (string) $item->get_subtotal_tax(),
                'total' => (string) $item->get_total(),
                'total_tax' => (string) $item->get_total_tax(),
                'assignments' => (string) $item->get_meta('_vms_claim_assignments', true),
                'date_snapshot' => (string) $item->get_meta('_vms_event_date_snapshot', true),
                'when_snapshot' => (string) $item->get_meta('_vms_event_when_snapshot', true),
                'title_snapshot' => (string) $item->get_meta('_vms_event_title_snapshot', true),
            );
            if ($actual !== $expected) {
                $errors[] = sprintf('Order item %d financial, quantity, identity, or assignment data changed.', $item_id);
            }
        }
        foreach ((array) ($before['attendees'] ?? array()) as $attendee_id => $expected) {
            $actual = array(
                'event_id' => absint(get_post_meta((int) $attendee_id, '_tribe_wooticket_event', true)),
                'product_id' => absint(get_post_meta((int) $attendee_id, '_tribe_wooticket_product', true)),
                'order_id' => absint(get_post_meta((int) $attendee_id, '_tribe_wooticket_order', true)),
                'order_item_id' => absint(get_post_meta((int) $attendee_id, '_tribe_wooticket_order_item', true)),
                'checkedin' => get_post_meta((int) $attendee_id, '_tribe_wooticket_checkedin', true),
                'security_code' => get_post_meta((int) $attendee_id, '_tribe_wooticket_security_code', true),
            );
            if ($actual !== $expected) {
                $errors[] = sprintf('Attendee %d identity, event linkage, check-in, or security code changed.', $attendee_id);
            }
        }
        foreach ((array) ($before['products'] ?? array()) as $product_id => $expected) {
            $product = function_exists('wc_get_product') ? wc_get_product((int) $product_id) : null;
            if (!$product) {
                $errors[] = sprintf('Product %d disappeared.', $product_id);
                continue;
            }
            $actual = array(
                'sku' => (string) $product->get_sku('edit'),
                'regular_price' => (string) $product->get_regular_price('edit'),
                'sale_price' => (string) $product->get_sale_price('edit'),
                'price' => (string) $product->get_price('edit'),
                'manage_stock' => (bool) $product->get_manage_stock('edit'),
                'stock_quantity' => $product->get_stock_quantity('edit'),
                'stock_status' => (string) $product->get_stock_status('edit'),
                'sold_individually' => (bool) $product->get_sold_individually('edit'),
                'total_sales' => (int) get_post_meta((int) $product_id, 'total_sales', true),
                'event_plan_id' => absint(get_post_meta((int) $product_id, '_vms_event_plan_id', true)),
                'tec_event_id' => absint(get_post_meta((int) $product_id, '_vms_tec_event_id', true)),
                'native_event_id' => absint(get_post_meta((int) $product_id, '_tribe_wooticket_for_event', true)),
                'product_role' => (string) get_post_meta((int) $product_id, '_vms_product_role', true),
            );
            if ($actual !== $expected) {
                $errors[] = sprintf('Product %d price, stock, SKU, or sales identity changed.', $product_id);
            }
        }
        return $errors;
    }
}

if (!function_exists('bvmgr_event_occurrence_integrity')) {
    function bvmgr_event_occurrence_integrity(int $plan_id): array
    {
        $plan_id = absint($plan_id);
        $occurrence = bvmgr_event_occurrence_for_plan($plan_id);
        $report = array(
            'ok' => false,
            'plan_id' => $plan_id,
            'canonical_date' => '',
            'mismatch_units' => 0,
            'mismatch_admission_units' => 0,
            'mismatch_reservation_units' => 0,
            'mismatch_line_items' => 0,
            'product_mismatches' => array(),
            'attendee_mismatches' => array(),
            'calendar_mismatch' => false,
            'resolver_available' => false,
            'unresolved_line_items' => 0,
            'messages' => array(),
        );
        if (empty($occurrence['valid'])) {
            $report['messages'][] = 'Canonical Event Plan occurrence is invalid.';
            return $report;
        }
        $payload = bvmgr_event_occurrence_payload($occurrence['start'], $occurrence['end']);
        $report['canonical_date'] = $payload['date'];
        $tec_event_id = absint(get_post_meta($plan_id, '_vms_tec_event_id', true));
        if ($tec_event_id <= 0 || trim((string) get_post_meta($tec_event_id, '_EventStartDate', true)) !== $payload['start_local']) {
            $report['calendar_mismatch'] = true;
            $report['messages'][] = 'Linked calendar event start does not match the Event Plan.';
        }

        $report['resolver_available'] = class_exists('BVMGR_Ticket_Revenue_Service')
            && function_exists('wc_get_orders')
            && class_exists('WooCommerce');
        if (!$report['resolver_available']) {
            $report['messages'][] = 'Ticket and order integrity could not be checked because WooCommerce or the sales resolver is unavailable.';
        }
        $result = $report['resolver_available']
            ? BVMGR_Ticket_Revenue_Service::get_sales_result(array(
                'event_plan_ids' => array($plan_id),
                'order_statuses' => array('processing', 'completed', 'refunded'),
                'include_unresolved' => true,
                'include_refunded_lines' => true,
            ))
            : array('rows' => array());
        $report['unresolved_line_items'] = (int) ($result['counts']['line_items_unresolved'] ?? 0);
        if ($report['unresolved_line_items'] > 0) {
            $report['messages'][] = sprintf('%d linked ticket line items have unresolved occurrence context.', $report['unresolved_line_items']);
        }
        $rows = array();
        $attendees = array();
        foreach ((array) ($result['rows'] ?? array()) as $row) {
            if ((int) ($row['event_plan_id'] ?? 0) !== $plan_id) {
                continue;
            }
            $rows[] = $row;
            $qty = max(0, (int) ($row['qty'] ?? 0) - (int) ($row['refunded_qty'] ?? 0));
            if ($qty <= 0) {
                continue;
            }
            $item_id = absint($row['order_item_id'] ?? 0);
            $name_date = bvmgr_event_occurrence_order_item_name_date($item_id);
            if (bvmgr_event_occurrence_snapshot_date($item_id) !== $payload['date']
                || ($name_date !== '' && $name_date !== $payload['date'])) {
                $report['mismatch_line_items']++;
                $report['mismatch_units'] += $qty;
                if (sanitize_key((string) ($row['line_kind'] ?? '')) === 'addon') {
                    $report['mismatch_reservation_units'] += $qty;
                } else {
                    $report['mismatch_admission_units'] += $qty;
                }
            }
            foreach ((array) ($row['attendee_ids'] ?? array()) as $attendee_id) {
                $attendees[] = absint($attendee_id);
            }
        }
        foreach (array_values(array_unique(array_filter($attendees))) as $attendee_id) {
            $event_id = absint(get_post_meta($attendee_id, '_tribe_wooticket_event', true));
            if ($event_id <= 0) {
                $event_id = absint(get_post_meta($attendee_id, '_tribe_rsvp_event', true));
            }
            if ($event_id !== $tec_event_id) {
                $report['attendee_mismatches'][] = $attendee_id;
            }
        }

        foreach (bvmgr_event_occurrence_collect_product_ids($plan_id, $tec_event_id, $rows) as $product_id) {
            $title = trim((string) get_the_title($product_id));
            if (preg_match_all('/\d{4}-\d{2}-\d{2}/', $title, $matches)) {
                foreach ((array) ($matches[0] ?? array()) as $title_date) {
                    if ((string) $title_date !== $payload['date']) {
                        $report['product_mismatches'][] = $product_id;
                        break;
                    }
                }
            }
        }
        $report['product_mismatches'] = array_values(array_unique($report['product_mismatches']));
        $report['attendee_mismatches'] = array_values(array_unique($report['attendee_mismatches']));
        if ($report['mismatch_units'] > 0) {
            $report['messages'][] = sprintf('%d active admission/reservation units retain a different effective occurrence.', $report['mismatch_units']);
        }
        if (!empty($report['product_mismatches'])) {
            $report['messages'][] = 'One or more product titles retain a different occurrence date.';
        }
        if (!empty($report['attendee_mismatches'])) {
            $report['messages'][] = 'One or more attendee records link to a different calendar event.';
        }
        $report['ok'] = !$report['calendar_mismatch']
            && $report['resolver_available']
            && $report['unresolved_line_items'] === 0
            && $report['mismatch_units'] === 0
            && empty($report['product_mismatches'])
            && empty($report['attendee_mismatches']);
        return $report;
    }
}

if (!function_exists('bvmgr_event_occurrence_history')) {
    function bvmgr_event_occurrence_history(int $plan_id): array
    {
        $history = get_post_meta($plan_id, '_vms_event_occurrence_history_v1', true);
        return is_array($history) ? array_values($history) : array();
    }
}

if (!function_exists('bvmgr_event_occurrence_operation_already_recorded')) {
    function bvmgr_event_occurrence_operation_already_recorded(int $plan_id, array $preview): bool
    {
        foreach (array_reverse(bvmgr_event_occurrence_history($plan_id)) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if ((string) ($entry['old_start_local'] ?? '') === (string) ($preview['old']['start_local'] ?? '')
                && (string) ($entry['new_start_local'] ?? '') === (string) ($preview['new']['start_local'] ?? '')
                && (string) ($entry['reason'] ?? '') === (string) ($preview['reason'] ?? '')) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('bvmgr_event_occurrence_clear_runtime_caches')) {
    function bvmgr_event_occurrence_clear_runtime_caches(array $preview): void
    {
        $plan_id = absint($preview['plan_id'] ?? 0);
        $tec_event_id = absint($preview['tec_event_id'] ?? 0);
        if ($plan_id > 0) {
            clean_post_cache($plan_id);
        }
        if ($tec_event_id > 0) {
            clean_post_cache($tec_event_id);
        }
        foreach ((array) ($preview['product_ids'] ?? array()) as $product_id) {
            $product_id = absint($product_id);
            if ($product_id > 0) {
                clean_post_cache($product_id);
            }
        }
        foreach ((array) ($preview['rows'] ?? array()) as $row) {
            $item_id = absint($row['order_item_id'] ?? 0);
            $order_id = absint($row['order_id'] ?? 0);
            if ($item_id > 0) {
                wp_cache_delete('item-' . $item_id, 'order-items');
                wp_cache_delete($item_id, 'order_item_meta');
            }
            if ($order_id > 0) {
                wp_cache_delete('order-items-' . $order_id, 'orders');
                wp_cache_delete('order-needs-processing-' . $order_id, 'orders');
                clean_post_cache($order_id);
            }
        }
        if (class_exists('WC_Cache_Helper')) {
            WC_Cache_Helper::invalidate_cache_group('orders');
        }
    }
}

if (!function_exists('bvmgr_event_occurrence_apply')) {
    function bvmgr_event_occurrence_apply(int $plan_id, string $expected_old_start, string $new_start, string $reason, int $actor_user_id): array
    {
        global $wpdb;
        $plan_id = absint($plan_id);
        $actor_user_id = absint($actor_user_id);
        $preview = bvmgr_event_occurrence_preview($plan_id, $expected_old_start, $new_start, $reason);
        $result = array('ok' => false, 'noop' => false, 'rolled_back' => false, 'message' => '', 'preview' => $preview, 'integrity' => array(), 'operation_id' => '');
        if (empty($preview['allowed'])) {
            $result['message'] = 'Operation is blocked by unresolved ambiguity.';
            return $result;
        }
        if ($actor_user_id <= 0 || !user_can($actor_user_id, 'edit_post', $plan_id)) {
            $result['message'] = 'An authenticated user who can edit this Event Plan is required.';
            return $result;
        }
        if (bvmgr_event_occurrence_operation_already_recorded($plan_id, $preview)) {
            $integrity = bvmgr_event_occurrence_integrity($plan_id);
            $result['ok'] = !empty($integrity['ok']);
            $result['noop'] = true;
            $result['message'] = $result['ok'] ? 'Occurrence operation was already applied; no changes were made.' : 'The operation is recorded, but integrity verification is not clean.';
            $result['integrity'] = $integrity;
            return $result;
        }

        $operation_id = wp_generate_uuid4();
        $result['operation_id'] = $operation_id;
        $invariants = bvmgr_event_occurrence_invariant_snapshot($preview);
        $transaction_started = false;

        try {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The canonical multi-record occurrence update requires an explicit transaction boundary; caching does not apply to transaction control.
            if ($wpdb->query('START TRANSACTION') === false) {
                throw new RuntimeException('Database transaction could not be started.');
            }
            $transaction_started = true;
            $revalidated = bvmgr_event_occurrence_preview($plan_id, $expected_old_start, $new_start, $reason);
            $fingerprint_keys = array('canonical', 'old', 'new', 'tec_event_id', 'rows', 'product_ids', 'attendee_ids', 'counts');
            $fingerprint = static function (array $candidate) use ($fingerprint_keys): string {
                return (string) wp_json_encode(array_intersect_key($candidate, array_fill_keys($fingerprint_keys, true)));
            };
            if (empty($revalidated['allowed']) || $fingerprint($revalidated) !== $fingerprint($preview)) {
                throw new RuntimeException('Affected entitlement set changed after preview; rerun the preview.');
            }

            $new_payload = $preview['new'];
            if ((string) $preview['mode'] === 'forward') {
                bvmgr_event_occurrence_authorized_write(static function () use ($plan_id, $new_payload): void {
                    update_post_meta($plan_id, '_vms_event_date', (string) $new_payload['date']);
                    update_post_meta($plan_id, '_vms_start_time', (string) $new_payload['start_time']);
                    update_post_meta($plan_id, '_vms_end_time', (string) $new_payload['end_time']);
                    update_post_meta($plan_id, '_vms_event_plan_start_datetime', (string) $new_payload['start_local']);
                    update_post_meta($plan_id, '_vms_event_plan_end_datetime', (string) $new_payload['end_local']);
                });
            }

            $tec_event_id = absint($preview['tec_event_id']);
            update_post_meta($tec_event_id, '_EventStartDate', (string) $new_payload['start_local']);
            update_post_meta($tec_event_id, '_EventEndDate', (string) $new_payload['end_local']);
            update_post_meta($tec_event_id, '_EventStartDateUTC', (string) $new_payload['start_utc']);
            update_post_meta($tec_event_id, '_EventEndDateUTC', (string) $new_payload['end_utc']);
            update_post_meta($tec_event_id, '_EventTimezone', (string) $new_payload['timezone']);

            if (function_exists('bvmgr_ticketing_v2_get_config') && function_exists('bvmgr_ticketing_v2_set_config')) {
                $old_start_dt = bvmgr_event_occurrence_parse_local((string) $preview['old']['start_local']);
                $new_start_dt = bvmgr_event_occurrence_parse_local((string) $new_payload['start_local']);
                if ($old_start_dt instanceof DateTimeImmutable && $new_start_dt instanceof DateTimeImmutable) {
                    $config = bvmgr_ticketing_v2_get_config($plan_id);
                    bvmgr_ticketing_v2_set_config($plan_id, bvmgr_event_occurrence_shift_ticket_schedule($config, $old_start_dt, $new_start_dt));
                }
            }

            foreach ((array) $preview['product_ids'] as $product_id) {
                $product_id = absint($product_id);
                $base_title = bvmgr_event_occurrence_product_base_title($product_id);
                $new_title = function_exists('bvmgr_ticketing_v2_compose_product_admin_title')
                    ? bvmgr_ticketing_v2_compose_product_admin_title($base_title, $tec_event_id)
                    : $base_title;
                if ($new_title !== '' && $new_title !== (string) get_the_title($product_id)) {
                    $updated = wp_update_post(array('ID' => $product_id, 'post_title' => $new_title), true);
                    if (is_wp_error($updated)) {
                        throw new RuntimeException(sprintf('Product %d title update failed: %s', $product_id, $updated->get_error_message()));
                    }
                }
            }

            if (function_exists('bvmgr_ticketing_v2_sync_mapped_ticket_sales_windows_for_calendar_change')) {
                $sync = bvmgr_ticketing_v2_sync_mapped_ticket_sales_windows_for_calendar_change($plan_id, $tec_event_id, false);
                if (empty($sync['ok']) && empty($sync['skipped'])) {
                    throw new RuntimeException('Mapped ticket sales-window synchronization failed.');
                }
            }

            foreach ((array) $preview['rows'] as $row) {
                bvmgr_event_occurrence_update_order_item($row, $new_payload, $operation_id);
            }

            if (function_exists('bvmgr_event_plan_sync_checkin_close_meta_to_tec')) {
                bvmgr_event_occurrence_authorized_write(static function () use ($plan_id, $tec_event_id): void {
                    bvmgr_event_plan_sync_checkin_close_meta_to_tec($plan_id, $tec_event_id);
                });
            }
            delete_post_meta($plan_id, '_vms_ticketing_reschedule_required_v1');

            $history = bvmgr_event_occurrence_history($plan_id);
            $history[] = array(
                'operation_id' => $operation_id,
                'mode' => (string) $preview['mode'],
                'reason' => (string) $preview['reason'],
                'old_start_local' => (string) $preview['old']['start_local'],
                'old_end_local' => (string) $preview['old']['end_local'],
                'old_start_utc' => (string) $preview['old']['start_utc'],
                'old_end_utc' => (string) $preview['old']['end_utc'],
                'new_start_local' => (string) $new_payload['start_local'],
                'new_end_local' => (string) $new_payload['end_local'],
                'new_start_utc' => (string) $new_payload['start_utc'],
                'new_end_utc' => (string) $new_payload['end_utc'],
                'timezone' => (string) $new_payload['timezone'],
                'actor_user_id' => $actor_user_id,
                'created_at_utc' => gmdate('Y-m-d H:i:s'),
                'impact_counts' => (array) $preview['counts'],
                'product_ids' => array_values(array_map('absint', (array) $preview['product_ids'])),
                'order_ids' => array_values(array_unique(array_filter(array_map(static function (array $row): int {
                    return absint($row['order_id'] ?? 0);
                }, (array) $preview['rows'])))),
            );
            update_post_meta($plan_id, '_vms_event_occurrence_history_v1', $history);

            do_action('bvmgr_event_occurrence_before_verify', $plan_id, $operation_id, $preview);
            $invariant_errors = bvmgr_event_occurrence_verify_invariants($invariants);
            if (!empty($invariant_errors)) {
                throw new RuntimeException(implode(' ', $invariant_errors));
            }
            $integrity = bvmgr_event_occurrence_integrity($plan_id);
            if (empty($integrity['ok'])) {
                throw new RuntimeException('Post-operation integrity verification failed: ' . implode(' ', (array) ($integrity['messages'] ?? array())));
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Commit follows complete invariant and integrity verification; caching does not apply to transaction control.
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Database commit failed.');
            }
            $transaction_started = false;
            bvmgr_event_occurrence_clear_runtime_caches($preview);
            $result['ok'] = true;
            $result['message'] = 'Occurrence operation applied and verified.';
            $result['integrity'] = $integrity;
            return $result;
        } catch (Throwable $throwable) {
            if ($transaction_started) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Every failure after transaction start must roll back the entire occurrence change; caching does not apply to transaction control.
                $wpdb->query('ROLLBACK');
                $result['rolled_back'] = true;
            }
            bvmgr_event_occurrence_clear_runtime_caches($preview);
            $result['message'] = $throwable->getMessage();
            return $result;
        }
    }
}
