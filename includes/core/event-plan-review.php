<?php
defined('ABSPATH') || exit;

if (!function_exists('bvmgr_event_plan_review_meta_key')) {
    function bvmgr_event_plan_review_meta_key(string $slot): string
    {
        $map = array(
            'snapshot_json' => '_vms_published_snapshot_json',
            'snapshot_at' => '_vms_published_snapshot_at',
            'snapshot_by' => '_vms_published_snapshot_by',
            'changes_json' => '_vms_unpublished_changes_json',
            'changes_at' => '_vms_unpublished_changes_at',
            'changes_by' => '_vms_unpublished_changes_by',
            'changes_source' => '_vms_unpublished_changes_source',
        );

        return $map[$slot] ?? '';
    }
}

if (!function_exists('bvmgr_event_plan_review_event_meta_key')) {
    function bvmgr_event_plan_review_event_meta_key(string $key, string $fallback): string
    {
        if (function_exists('bvmgr_meta_key')) {
            $resolved = (string) bvmgr_meta_key('event_plan', $key);
            if ($resolved !== '') {
                return $resolved;
            }
        }

        return $fallback;
    }
}

if (!function_exists('bvmgr_event_plan_review_current_snapshot')) {
    function bvmgr_event_plan_review_current_snapshot(int $plan_id): array
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0 || get_post_type($plan_id) !== 'vms_event_plan') {
            return array();
        }

        $status_key = bvmgr_event_plan_review_event_meta_key('status', '_vms_event_plan_status');
        $date_key = bvmgr_event_plan_review_event_meta_key('date', '_vms_event_date');
        $venue_key = bvmgr_event_plan_review_event_meta_key('venue_id', '_vms_venue_id');
        $primary_key = bvmgr_event_plan_review_event_meta_key('band_vendor_id', '_vms_band_vendor_id');
        $secondary_type_key = bvmgr_event_plan_review_event_meta_key('secondary_vendor_type', '_vms_secondary_vendor_type');
        $secondary_ids_key = bvmgr_event_plan_review_event_meta_key('secondary_vendor_ids', '_vms_secondary_vendor_ids');
        $secondary_id_index_key = bvmgr_event_plan_review_event_meta_key('secondary_vendor_id', '_vms_secondary_vendor_id');

        $secondary_vendor_ids = get_post_meta($plan_id, $secondary_ids_key, true);
        if (!is_array($secondary_vendor_ids)) {
            $secondary_vendor_ids = get_post_meta($plan_id, $secondary_id_index_key, false);
        }
        $secondary_vendor_ids = array_values(array_unique(array_filter(array_map('absint', (array) $secondary_vendor_ids))));
        sort($secondary_vendor_ids);

        $status = function_exists('bvmgr_event_plan_get_status')
            ? (string) bvmgr_event_plan_get_status($plan_id, 'review_snapshot')
            : (string) get_post_meta($plan_id, $status_key, true);
        $status = sanitize_key($status);
        if ($status === 'canceled') {
            $status = 'cancelled';
        }
        if ($status === '') {
            $status = 'draft';
        }

        $lineup_rows = function_exists('vms_get_event_plan_lineup_entries')
            ? (array) vms_get_event_plan_lineup_entries($plan_id)
            : array();
        if (function_exists('bvmgr_event_plan_review_lineup_rows')) {
            $lineup_rows = bvmgr_event_plan_review_lineup_rows($lineup_rows);
        }

        return array(
            'title' => sanitize_text_field((string) get_the_title($plan_id)),
            'event_date' => sanitize_text_field((string) get_post_meta($plan_id, $date_key, true)),
            'start_time' => sanitize_text_field((string) get_post_meta($plan_id, '_vms_start_time', true)),
            'end_time' => sanitize_text_field((string) get_post_meta($plan_id, '_vms_end_time', true)),
            'venue_id' => absint(get_post_meta($plan_id, $venue_key, true)),
            'status' => $status,
            'primary_vendor_id' => absint(get_post_meta($plan_id, $primary_key, true)),
            'secondary_vendor_type' => sanitize_key((string) get_post_meta($plan_id, $secondary_type_key, true)),
            'secondary_vendor_ids' => $secondary_vendor_ids,
            'lineup_rows' => $lineup_rows,
        );
    }
}

if (!function_exists('bvmgr_event_plan_review_state_result')) {
    function bvmgr_event_plan_review_state_result(string $state, array $value = array(), string $reason = ''): array
    {
        return array(
            'state' => $state,
            'value' => $value,
            'reason' => $reason,
        );
    }
}

if (!function_exists('bvmgr_event_plan_review_is_list_array')) {
    function bvmgr_event_plan_review_is_list_array(array $value): bool
    {
        $index = 0;
        foreach (array_keys($value) as $key) {
            if ($key !== $index) {
                return false;
            }
            $index++;
        }

        return true;
    }
}

if (!function_exists('bvmgr_event_plan_review_decode_error_reason')) {
    function bvmgr_event_plan_review_decode_error_reason(): string
    {
        $map = array(
            JSON_ERROR_DEPTH => 'json-depth',
            JSON_ERROR_STATE_MISMATCH => 'json-state-mismatch',
            JSON_ERROR_CTRL_CHAR => 'json-control-char',
            JSON_ERROR_SYNTAX => 'json-syntax',
            JSON_ERROR_UTF8 => 'json-utf8',
        );

        return $map[json_last_error()] ?? 'json-error';
    }
}

if (!function_exists('bvmgr_event_plan_review_normalize_id_value')) {
    function bvmgr_event_plan_review_normalize_id_value($value, string $reason): array
    {
        if (is_bool($value) || is_array($value) || is_object($value) || $value === null) {
            return bvmgr_event_plan_review_state_result('invalid', array(), $reason);
        }

        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            return bvmgr_event_plan_review_state_result('invalid', array(), $reason);
        }

        if (is_string($value) && trim($value) === '') {
            return bvmgr_event_plan_review_state_result('invalid', array(), $reason);
        }

        if (!is_numeric($value)) {
            return bvmgr_event_plan_review_state_result('invalid', array(), $reason);
        }

        return bvmgr_event_plan_review_state_result('valid', array('normalized' => absint($value)));
    }
}

if (!function_exists('bvmgr_event_plan_review_normalize_text_value')) {
    function bvmgr_event_plan_review_normalize_text_value($value, string $reason): array
    {
        if (is_bool($value) || is_array($value) || is_object($value) || $value === null) {
            return bvmgr_event_plan_review_state_result('invalid', array(), $reason);
        }

        if (!is_scalar($value)) {
            return bvmgr_event_plan_review_state_result('invalid', array(), $reason);
        }

        return bvmgr_event_plan_review_state_result('valid', array('normalized' => sanitize_text_field((string) $value)));
    }
}

if (!function_exists('bvmgr_event_plan_review_normalize_key_value')) {
    function bvmgr_event_plan_review_normalize_key_value($value, string $reason): array
    {
        if (is_bool($value) || is_array($value) || is_object($value) || $value === null) {
            return bvmgr_event_plan_review_state_result('invalid', array(), $reason);
        }

        if (!is_scalar($value)) {
            return bvmgr_event_plan_review_state_result('invalid', array(), $reason);
        }

        return bvmgr_event_plan_review_state_result('valid', array('normalized' => sanitize_key((string) $value)));
    }
}

if (!function_exists('bvmgr_event_plan_review_normalize_secondary_vendor_ids')) {
    function bvmgr_event_plan_review_normalize_secondary_vendor_ids($value, string $reason): array
    {
        if (!is_array($value) || !bvmgr_event_plan_review_is_list_array($value)) {
            return bvmgr_event_plan_review_state_result('invalid', array(), $reason);
        }

        $normalized = array();
        foreach ($value as $vendor_id) {
            $result = bvmgr_event_plan_review_normalize_id_value($vendor_id, $reason);
            if ('valid' !== $result['state']) {
                return $result;
            }
            $normalized[] = (int) ($result['value']['normalized'] ?? 0);
        }

        $normalized = array_values(array_unique(array_filter($normalized)));
        sort($normalized);

        return bvmgr_event_plan_review_state_result('valid', $normalized);
    }
}

if (!function_exists('bvmgr_event_plan_review_normalize_lineup_rows_value')) {
    function bvmgr_event_plan_review_normalize_lineup_rows_value($value, string $reason): array
    {
        if (!is_array($value) || !bvmgr_event_plan_review_is_list_array($value)) {
            return bvmgr_event_plan_review_state_result('invalid', array(), $reason);
        }

        $prepared = array();
        foreach ($value as $index => $row) {
            if (!is_array($row) || bvmgr_event_plan_review_is_list_array($row)) {
                return bvmgr_event_plan_review_state_result('invalid', array(), $reason);
            }

            $required = array('vendor_id', 'vendor_label', 'role', 'set_start', 'set_end', 'guaranteed_fee', 'sort_order');
            foreach ($required as $required_key) {
                if (!array_key_exists($required_key, $row)) {
                    return bvmgr_event_plan_review_state_result('invalid', array(), $reason . '-missing-' . $required_key);
                }
            }

            $vendor_id = bvmgr_event_plan_review_normalize_id_value($row['vendor_id'], $reason . '-vendor-id');
            if ('valid' !== $vendor_id['state']) {
                return $vendor_id;
            }

            $vendor_label = bvmgr_event_plan_review_normalize_text_value($row['vendor_label'], $reason . '-vendor-label');
            if ('valid' !== $vendor_label['state']) {
                return $vendor_label;
            }

            $role = bvmgr_event_plan_review_normalize_key_value($row['role'], $reason . '-role');
            if ('valid' !== $role['state']) {
                return $role;
            }

            $set_start = bvmgr_event_plan_review_normalize_text_value($row['set_start'], $reason . '-set-start');
            if ('valid' !== $set_start['state']) {
                return $set_start;
            }

            $set_end = bvmgr_event_plan_review_normalize_text_value($row['set_end'], $reason . '-set-end');
            if ('valid' !== $set_end['state']) {
                return $set_end;
            }

            $sort_order = bvmgr_event_plan_review_normalize_id_value($row['sort_order'], $reason . '-sort-order');
            if ('valid' !== $sort_order['state']) {
                return $sort_order;
            }

            $guaranteed_fee = $row['guaranteed_fee'];
            if (is_bool($guaranteed_fee) || is_array($guaranteed_fee) || is_object($guaranteed_fee)) {
                return bvmgr_event_plan_review_state_result('invalid', array(), $reason . '-guaranteed-fee');
            }
            if ($guaranteed_fee === '' || $guaranteed_fee === null) {
                $guaranteed_fee = '';
            } elseif (!is_int($guaranteed_fee) && !is_float($guaranteed_fee) && !is_string($guaranteed_fee)) {
                return bvmgr_event_plan_review_state_result('invalid', array(), $reason . '-guaranteed-fee');
            } elseif (!is_numeric($guaranteed_fee)) {
                return bvmgr_event_plan_review_state_result('invalid', array(), $reason . '-guaranteed-fee');
            } else {
                $guaranteed_fee = round((float) $guaranteed_fee, 2);
            }

            $prepared[] = array(
                'vendor_id' => (int) ($vendor_id['value']['normalized'] ?? 0),
                'vendor_label' => (string) ($vendor_label['value']['normalized'] ?? ''),
                'role' => (string) ($role['value']['normalized'] ?? ''),
                'set_start' => (string) ($set_start['value']['normalized'] ?? ''),
                'set_end' => (string) ($set_end['value']['normalized'] ?? ''),
                'guaranteed_fee' => $guaranteed_fee,
                'sort_order' => (int) ($sort_order['value']['normalized'] ?? $index),
            );
        }

        return bvmgr_event_plan_review_state_result('valid', bvmgr_event_plan_review_lineup_rows($prepared));
    }
}

if (!function_exists('bvmgr_event_plan_review_decode_snapshot_json')) {
    function bvmgr_event_plan_review_decode_snapshot_json(string $raw): array
    {
        if (trim($raw) === '') {
            return bvmgr_event_plan_review_state_result('missing', array(), 'snapshot-missing');
        }

        $decoded = json_decode($raw, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            return bvmgr_event_plan_review_state_result('invalid', array(), 'snapshot-' . bvmgr_event_plan_review_decode_error_reason());
        }

        if (!is_array($decoded) || bvmgr_event_plan_review_is_list_array($decoded)) {
            return bvmgr_event_plan_review_state_result('invalid', array(), 'snapshot-schema-top-level');
        }

        $required = array(
            'title',
            'event_date',
            'start_time',
            'end_time',
            'venue_id',
            'status',
            'primary_vendor_id',
            'secondary_vendor_type',
            'secondary_vendor_ids',
            'lineup_rows',
        );
        foreach ($required as $field) {
            if (!array_key_exists($field, $decoded)) {
                return bvmgr_event_plan_review_state_result('invalid', array(), 'snapshot-missing-' . $field);
            }
        }

        $title = bvmgr_event_plan_review_normalize_text_value($decoded['title'], 'snapshot-title');
        $event_date = bvmgr_event_plan_review_normalize_text_value($decoded['event_date'], 'snapshot-event-date');
        $start_time = bvmgr_event_plan_review_normalize_text_value($decoded['start_time'], 'snapshot-start-time');
        $end_time = bvmgr_event_plan_review_normalize_text_value($decoded['end_time'], 'snapshot-end-time');
        $venue_id = bvmgr_event_plan_review_normalize_id_value($decoded['venue_id'], 'snapshot-venue-id');
        $status = bvmgr_event_plan_review_normalize_key_value($decoded['status'], 'snapshot-status');
        $primary_vendor_id = bvmgr_event_plan_review_normalize_id_value($decoded['primary_vendor_id'], 'snapshot-primary-vendor-id');
        $secondary_vendor_type = bvmgr_event_plan_review_normalize_key_value($decoded['secondary_vendor_type'], 'snapshot-secondary-vendor-type');
        $secondary_vendor_ids = bvmgr_event_plan_review_normalize_secondary_vendor_ids($decoded['secondary_vendor_ids'], 'snapshot-secondary-vendor-ids');
        $lineup_rows = bvmgr_event_plan_review_normalize_lineup_rows_value($decoded['lineup_rows'], 'snapshot-lineup-rows');

        $parts = array($title, $event_date, $start_time, $end_time, $venue_id, $status, $primary_vendor_id, $secondary_vendor_type, $secondary_vendor_ids, $lineup_rows);
        foreach ($parts as $part) {
            if ('valid' !== $part['state']) {
                return $part;
            }
        }

        $normalized_status = (string) ($status['value']['normalized'] ?? '');
        if ('canceled' === $normalized_status) {
            $normalized_status = 'cancelled';
        }
        if ('' === $normalized_status) {
            $normalized_status = 'draft';
        }

        return bvmgr_event_plan_review_state_result(
            'valid',
            array(
                'title' => (string) ($title['value']['normalized'] ?? ''),
                'event_date' => (string) ($event_date['value']['normalized'] ?? ''),
                'start_time' => (string) ($start_time['value']['normalized'] ?? ''),
                'end_time' => (string) ($end_time['value']['normalized'] ?? ''),
                'venue_id' => (int) ($venue_id['value']['normalized'] ?? 0),
                'status' => $normalized_status,
                'primary_vendor_id' => (int) ($primary_vendor_id['value']['normalized'] ?? 0),
                'secondary_vendor_type' => (string) ($secondary_vendor_type['value']['normalized'] ?? ''),
                'secondary_vendor_ids' => (array) ($secondary_vendor_ids['value'] ?? array()),
                'lineup_rows' => (array) ($lineup_rows['value'] ?? array()),
            )
        );
    }
}

if (!function_exists('bvmgr_event_plan_review_normalize_change_row')) {
    function bvmgr_event_plan_review_normalize_change_row($row): array
    {
        if (!is_array($row) || bvmgr_event_plan_review_is_list_array($row)) {
            return bvmgr_event_plan_review_state_result('invalid', array(), 'changes-row-shape');
        }

        $required = array('field', 'label', 'summary');
        foreach ($required as $required_key) {
            if (!array_key_exists($required_key, $row)) {
                return bvmgr_event_plan_review_state_result('invalid', array(), 'changes-row-missing-' . $required_key);
            }
        }

        $field_result = bvmgr_event_plan_review_normalize_key_value($row['field'], 'changes-row-field');
        if ('valid' !== $field_result['state']) {
            return $field_result;
        }

        $field = (string) ($field_result['value']['normalized'] ?? '');
        if ('' === $field) {
            return bvmgr_event_plan_review_state_result('invalid', array(), 'changes-row-field');
        }

        $string_fields = array('title', 'event_date', 'start_time', 'end_time', 'status', 'secondary_vendor_type');
        $id_fields = array('venue_id', 'primary_vendor_id');
        $list_fields = array('secondary_vendor_ids', 'lineup_rows');
        if (!in_array($field, array_merge($string_fields, $id_fields, $list_fields), true)) {
            return bvmgr_event_plan_review_state_result('invalid', array(), 'changes-row-unknown-field');
        }

        $label = bvmgr_event_plan_review_normalize_text_value($row['label'], 'changes-row-label');
        $summary = bvmgr_event_plan_review_normalize_text_value($row['summary'], 'changes-row-summary');
        if ('valid' !== $label['state']) {
            return $label;
        }
        if ('valid' !== $summary['state']) {
            return $summary;
        }

        $normalized = array(
            'field' => $field,
            'label' => (string) ($label['value']['normalized'] ?? ''),
            'summary' => (string) ($summary['value']['normalized'] ?? ''),
        );

        if (in_array($field, $string_fields, true)) {
            foreach (array('before', 'after', 'before_label', 'after_label') as $key) {
                if (!array_key_exists($key, $row)) {
                    return bvmgr_event_plan_review_state_result('invalid', array(), 'changes-row-missing-' . $key);
                }
                $value = bvmgr_event_plan_review_normalize_text_value($row[$key], 'changes-row-' . $key);
                if ('valid' !== $value['state']) {
                    return $value;
                }
                $normalized[$key] = (string) ($value['value']['normalized'] ?? '');
            }
        } elseif (in_array($field, $id_fields, true)) {
            foreach (array('before', 'after') as $key) {
                if (!array_key_exists($key, $row)) {
                    return bvmgr_event_plan_review_state_result('invalid', array(), 'changes-row-missing-' . $key);
                }
                $value = bvmgr_event_plan_review_normalize_id_value($row[$key], 'changes-row-' . $key);
                if ('valid' !== $value['state']) {
                    return $value;
                }
                $normalized[$key] = (int) ($value['value']['normalized'] ?? 0);
            }
            foreach (array('before_label', 'after_label') as $key) {
                if (!array_key_exists($key, $row)) {
                    return bvmgr_event_plan_review_state_result('invalid', array(), 'changes-row-missing-' . $key);
                }
                $value = bvmgr_event_plan_review_normalize_text_value($row[$key], 'changes-row-' . $key);
                if ('valid' !== $value['state']) {
                    return $value;
                }
                $normalized[$key] = (string) ($value['value']['normalized'] ?? '');
            }
        } elseif ('secondary_vendor_ids' === $field) {
            foreach (array('before', 'after') as $key) {
                if (!array_key_exists($key, $row)) {
                    return bvmgr_event_plan_review_state_result('invalid', array(), 'changes-row-missing-' . $key);
                }
                $value = bvmgr_event_plan_review_normalize_secondary_vendor_ids($row[$key], 'changes-row-' . $key);
                if ('valid' !== $value['state']) {
                    return $value;
                }
                $normalized[$key] = (array) ($value['value'] ?? array());
            }
        } elseif ('lineup_rows' === $field) {
            foreach (array('before', 'after') as $key) {
                if (!array_key_exists($key, $row)) {
                    return bvmgr_event_plan_review_state_result('invalid', array(), 'changes-row-missing-' . $key);
                }
                $value = bvmgr_event_plan_review_normalize_lineup_rows_value($row[$key], 'changes-row-' . $key);
                if ('valid' !== $value['state']) {
                    return $value;
                }
                $normalized[$key] = (array) ($value['value'] ?? array());
            }
        }

        return bvmgr_event_plan_review_state_result('valid', $normalized);
    }
}

if (!function_exists('bvmgr_event_plan_review_decode_changes_json')) {
    function bvmgr_event_plan_review_decode_changes_json(string $raw): array
    {
        if (trim($raw) === '') {
            return bvmgr_event_plan_review_state_result('missing', array(), 'changes-missing');
        }

        $decoded = json_decode($raw, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            return bvmgr_event_plan_review_state_result('invalid', array(), 'changes-' . bvmgr_event_plan_review_decode_error_reason());
        }

        if (!is_array($decoded) || bvmgr_event_plan_review_is_list_array($decoded)) {
            return bvmgr_event_plan_review_state_result('invalid', array(), 'changes-schema-top-level');
        }

        if (!array_key_exists('changes', $decoded)) {
            return bvmgr_event_plan_review_state_result('invalid', array(), 'changes-missing-changes');
        }

        if (!is_array($decoded['changes']) || !bvmgr_event_plan_review_is_list_array($decoded['changes'])) {
            return bvmgr_event_plan_review_state_result('invalid', array(), 'changes-invalid-list');
        }

        $normalized_changes = array();
        foreach ((array) $decoded['changes'] as $row) {
            $normalized_row = bvmgr_event_plan_review_normalize_change_row($row);
            if ('valid' !== $normalized_row['state']) {
                return $normalized_row;
            }

            $change = (array) ($normalized_row['value'] ?? array());
            if (function_exists('bvmgr_event_plan_review_change_is_noop') && bvmgr_event_plan_review_change_is_noop($change)) {
                continue;
            }

            $normalized_changes[] = $change;
        }

        return bvmgr_event_plan_review_state_result(
            'valid',
            array(
                'count' => count($normalized_changes),
                'changes' => $normalized_changes,
            )
        );
    }
}

if (!function_exists('bvmgr_event_plan_review_has_snapshot_marker')) {
    function bvmgr_event_plan_review_has_snapshot_marker(int $plan_id): bool
    {
        $snapshot_at = (string) get_post_meta($plan_id, bvmgr_event_plan_review_meta_key('snapshot_at'), true);
        $snapshot_by = absint(get_post_meta($plan_id, bvmgr_event_plan_review_meta_key('snapshot_by'), true));

        return '' !== trim($snapshot_at) || $snapshot_by > 0;
    }
}

if (!function_exists('bvmgr_event_plan_review_has_changes_marker')) {
    function bvmgr_event_plan_review_has_changes_marker(int $plan_id): bool
    {
        $changes_at = (string) get_post_meta($plan_id, bvmgr_event_plan_review_meta_key('changes_at'), true);
        $changes_by = absint(get_post_meta($plan_id, bvmgr_event_plan_review_meta_key('changes_by'), true));
        $changes_source = (string) get_post_meta($plan_id, bvmgr_event_plan_review_meta_key('changes_source'), true);

        return '' !== trim($changes_at) || $changes_by > 0 || '' !== trim($changes_source);
    }
}

if (!function_exists('bvmgr_event_plan_review_get_snapshot_state')) {
    function bvmgr_event_plan_review_get_snapshot_state(int $plan_id): array
    {
        $raw = (string) get_post_meta($plan_id, bvmgr_event_plan_review_meta_key('snapshot_json'), true);
        $state = bvmgr_event_plan_review_decode_snapshot_json($raw);
        if ('missing' === ($state['state'] ?? '') && bvmgr_event_plan_review_has_snapshot_marker($plan_id)) {
            return bvmgr_event_plan_review_state_result('invalid', array(), 'snapshot-marker-without-valid-json');
        }

        return $state;
    }
}

if (!function_exists('bvmgr_event_plan_review_get_changes_state')) {
    function bvmgr_event_plan_review_get_changes_state(int $plan_id): array
    {
        $raw = (string) get_post_meta($plan_id, bvmgr_event_plan_review_meta_key('changes_json'), true);
        $state = bvmgr_event_plan_review_decode_changes_json($raw);
        if ('missing' === ($state['state'] ?? '') && bvmgr_event_plan_review_has_changes_marker($plan_id)) {
            return bvmgr_event_plan_review_state_result('invalid', array(), 'changes-marker-without-valid-json');
        }

        return $state;
    }
}

if (!function_exists('bvmgr_event_plan_review_get_snapshot')) {
    function bvmgr_event_plan_review_get_snapshot(int $plan_id): array
    {
        $state = bvmgr_event_plan_review_get_snapshot_state($plan_id);
        return 'valid' === ($state['state'] ?? '') ? (array) ($state['value'] ?? array()) : array();
    }
}

if (!function_exists('bvmgr_event_plan_review_get_changes')) {
    function bvmgr_event_plan_review_get_changes(int $plan_id): array
    {
        $state = bvmgr_event_plan_review_get_changes_state($plan_id);
        return 'valid' === ($state['state'] ?? '') ? (array) ($state['value'] ?? array()) : array();
    }
}

if (!function_exists('bvmgr_event_plan_review_source_label')) {
    function bvmgr_event_plan_review_source_label(string $source): string
    {
        $source = sanitize_key($source);
        $map = array(
            'event_plan_editor' => __('Event Plan editor', 'backstage-venue-manager'),
            'fill_dates' => __('Fill Dates', 'backstage-venue-manager'),
            'importer' => __('Importer', 'backstage-venue-manager'),
            'unknown' => __('Unknown', 'backstage-venue-manager'),
        );

        return $map[$source] ?? ucwords(str_replace(array('_', '-'), ' ', $source));
    }
}

if (!function_exists('bvmgr_event_plan_review_vendor_label')) {
    function bvmgr_event_plan_review_vendor_label(int $vendor_id): string
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return __('None', 'backstage-venue-manager');
        }

        $title = trim((string) get_the_title($vendor_id));
        /* translators: %d: vendor post ID. */
        return $title !== '' ? $title : sprintf(__('Vendor #%d', 'backstage-venue-manager'), $vendor_id);
    }
}

if (!function_exists('bvmgr_event_plan_review_term_label')) {
    function bvmgr_event_plan_review_term_label(string $slug): string
    {
        $slug = function_exists('bvmgr_vendor_type_normalize_slug')
            ? bvmgr_vendor_type_normalize_slug($slug)
            : sanitize_key($slug);
        if ($slug === '') {
            return __('Not set', 'backstage-venue-manager');
        }

        $term = function_exists('bvmgr_vendor_type_get_term')
            ? bvmgr_vendor_type_get_term($slug)
            : get_term_by('slug', $slug, 'vms_vendor_type');
        if ($term instanceof WP_Term) {
            return (string) $term->name;
        }

        return ucwords(str_replace(array('-', '_'), ' ', $slug));
    }
}

if (!function_exists('bvmgr_event_plan_review_venue_label')) {
    function bvmgr_event_plan_review_venue_label(int $venue_id): string
    {
        $venue_id = absint($venue_id);
        if ($venue_id <= 0) {
            return __('Unassigned venue', 'backstage-venue-manager');
        }

        $title = trim((string) get_the_title($venue_id));
        /* translators: %d: venue post ID. */
        return $title !== '' ? $title : sprintf(__('Venue #%d', 'backstage-venue-manager'), $venue_id);
    }
}


if (!function_exists('bvmgr_event_plan_review_compare_token')) {
    function bvmgr_event_plan_review_compare_token($value): string
    {
        if (is_array($value) || is_object($value)) {
            $encoded = wp_json_encode($value);
            $value = is_string($encoded) ? $encoded : '';
        }

        $text = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return strtolower(trim((string) $text));
    }
}

if (!function_exists('bvmgr_event_plan_review_change_is_noop')) {
    function bvmgr_event_plan_review_change_is_noop(array $change): bool
    {
        $before_label = bvmgr_event_plan_review_compare_token($change['before_label'] ?? null);
        $after_label = bvmgr_event_plan_review_compare_token($change['after_label'] ?? null);
        if ($before_label !== '' && $after_label !== '' && $before_label === $after_label) {
            return true;
        }

        $before = bvmgr_event_plan_review_compare_token($change['before'] ?? null);
        $after = bvmgr_event_plan_review_compare_token($change['after'] ?? null);
        return $before !== '' && $after !== '' && $before === $after;
    }
}

if (!function_exists('bvmgr_event_plan_review_status_label')) {
    function bvmgr_event_plan_review_status_label(string $status): string
    {
        $status = sanitize_key($status);
        if ($status === 'canceled') {
            $status = 'cancelled';
        }
        if ($status === '') {
            $status = 'draft';
        }

        if (function_exists('bvmgr_event_plan_status_label')) {
            return (string) bvmgr_event_plan_status_label($status);
        }

        return ucwords(str_replace(array('_', '-'), ' ', $status));
    }
}

if (!function_exists('bvmgr_event_plan_review_format_date')) {
    function bvmgr_event_plan_review_format_date(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return __('Not set', 'backstage-venue-manager');
        }

        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('M j, Y');
        }

        return $date;
    }
}

if (!function_exists('bvmgr_event_plan_review_format_time')) {
    function bvmgr_event_plan_review_format_time(string $time): string
    {
        $time = trim($time);
        if ($time === '') {
            return __('Not set', 'backstage-venue-manager');
        }

        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $dt = DateTimeImmutable::createFromFormat('!H:i', $time, $timezone);
        if (!($dt instanceof DateTimeImmutable)) {
            $dt = DateTimeImmutable::createFromFormat('!H:i:s', $time, $timezone);
        }
        if ($dt instanceof DateTimeImmutable) {
            return strtolower($dt->format('g:i A'));
        }

        return $time;
    }
}

if (!function_exists('bvmgr_event_plan_review_secondary_vendor_labels')) {
    function bvmgr_event_plan_review_secondary_vendor_labels(array $ids): array
    {
        $labels = array();
        foreach ($ids as $vendor_id) {
            $vendor_id = absint($vendor_id);
            if ($vendor_id <= 0) {
                continue;
            }
            $labels[] = bvmgr_event_plan_review_vendor_label($vendor_id);
        }
        return $labels;
    }
}

if (!function_exists('bvmgr_event_plan_review_lineup_rows')) {
    function bvmgr_event_plan_review_lineup_rows(array $rows): array
    {
        $normalized = array();
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $vendor_id = absint($row['vendor_id'] ?? 0);
            $role = sanitize_key((string) ($row['role'] ?? 'supporting'));
            if (!in_array($role, array('primary', 'supporting'), true)) {
                $role = 'supporting';
            }
            $set_start = sanitize_text_field((string) ($row['set_start'] ?? ''));
            $set_end = sanitize_text_field((string) ($row['set_end'] ?? ''));
            $guaranteed_fee = $row['guaranteed_fee'] ?? '';
            if ($guaranteed_fee !== '' && $guaranteed_fee !== null && is_numeric($guaranteed_fee)) {
                $guaranteed_fee = round((float) $guaranteed_fee, 2);
            } else {
                $guaranteed_fee = '';
            }
            $normalized[] = array(
                'vendor_id' => $vendor_id,
                'vendor_label' => bvmgr_event_plan_review_vendor_label($vendor_id),
                'role' => $role,
                'set_start' => $set_start,
                'set_end' => $set_end,
                'guaranteed_fee' => $guaranteed_fee,
                'sort_order' => (int) ($row['sort_order'] ?? $index),
            );
        }
        return array_values($normalized);
    }
}

if (!function_exists('bvmgr_event_plan_review_lineup_signature')) {
    function bvmgr_event_plan_review_lineup_signature(array $rows): array
    {
        $parts = array();
        foreach (bvmgr_event_plan_review_lineup_rows($rows) as $row) {
            $parts[] = implode('|', array(
                sanitize_key((string) ($row['role'] ?? '')),
                (string) absint($row['vendor_id'] ?? 0),
                sanitize_text_field((string) ($row['set_start'] ?? '')),
                sanitize_text_field((string) ($row['set_end'] ?? '')),
                ($row['guaranteed_fee'] === '' || $row['guaranteed_fee'] === null) ? '' : number_format((float) $row['guaranteed_fee'], 2, '.', ''),
                (string) ((int) ($row['sort_order'] ?? 0)),
            ));
        }
        return $parts;
    }
}

if (!function_exists('bvmgr_event_plan_review_lineup_summary')) {
    function bvmgr_event_plan_review_lineup_summary(array $before_rows, array $after_rows): string
    {
        $before_rows = bvmgr_event_plan_review_lineup_rows($before_rows);
        $after_rows = bvmgr_event_plan_review_lineup_rows($after_rows);

        $before_by_vendor = array();
        foreach ($before_rows as $row) {
            $vendor_id = absint($row['vendor_id'] ?? 0);
            if ($vendor_id > 0) {
                $before_by_vendor[$vendor_id] = $row;
            }
        }
        $after_by_vendor = array();
        foreach ($after_rows as $row) {
            $vendor_id = absint($row['vendor_id'] ?? 0);
            if ($vendor_id > 0) {
                $after_by_vendor[$vendor_id] = $row;
            }
        }

        $before_vendor_ids = array_keys($before_by_vendor);
        $after_vendor_ids = array_keys($after_by_vendor);
        sort($before_vendor_ids);
        sort($after_vendor_ids);

        $added = array_values(array_diff($after_vendor_ids, $before_vendor_ids));
        $removed = array_values(array_diff($before_vendor_ids, $after_vendor_ids));
        $timing_changed = array();
        $fee_changed = array();
        $order_changed = false;

        foreach (array_intersect($before_vendor_ids, $after_vendor_ids) as $vendor_id) {
            $before_row = $before_by_vendor[$vendor_id];
            $after_row = $after_by_vendor[$vendor_id];
            if ((string) ($before_row['set_start'] ?? '') !== (string) ($after_row['set_start'] ?? '') || (string) ($before_row['set_end'] ?? '') !== (string) ($after_row['set_end'] ?? '')) {
                $timing_changed[] = bvmgr_event_plan_review_vendor_label((int) $vendor_id);
            }
            $before_fee = $before_row['guaranteed_fee'] ?? '';
            $after_fee = $after_row['guaranteed_fee'] ?? '';
            if ((string) $before_fee !== (string) $after_fee) {
                $fee_changed[] = bvmgr_event_plan_review_vendor_label((int) $vendor_id);
            }
            if ((int) ($before_row['sort_order'] ?? 0) !== (int) ($after_row['sort_order'] ?? 0)) {
                $order_changed = true;
            }
        }

        $parts = array();
        if (!empty($added)) {
            /* translators: %s: comma-separated vendor names added to the lineup. */
            $parts[] = sprintf(__('added %s', 'backstage-venue-manager'), implode(', ', bvmgr_event_plan_review_secondary_vendor_labels($added)));
        }
        if (!empty($removed)) {
            /* translators: %s: comma-separated vendor names removed from the lineup. */
            $parts[] = sprintf(__('removed %s', 'backstage-venue-manager'), implode(', ', bvmgr_event_plan_review_secondary_vendor_labels($removed)));
        }
        if (!empty($timing_changed)) {
            /* translators: %s: comma-separated vendor names with changed set times. */
            $parts[] = sprintf(__('set times changed for %s', 'backstage-venue-manager'), implode(', ', $timing_changed));
        }
        if (!empty($fee_changed)) {
            /* translators: %s: comma-separated vendor names with changed compensation. */
            $parts[] = sprintf(__('supporting compensation changed for %s', 'backstage-venue-manager'), implode(', ', $fee_changed));
        }
        if ($order_changed) {
            $parts[] = __('lineup order changed', 'backstage-venue-manager');
        }

        if (empty($parts)) {
            $parts[] = __('lineup details changed', 'backstage-venue-manager');
        }

        /* translators: %s: semicolon-separated lineup change summaries. */
        return sprintf(__('Lineup & schedule updated: %s', 'backstage-venue-manager'), implode('; ', $parts));
    }
}

if (!function_exists('bvmgr_event_plan_review_build_changes')) {
    function bvmgr_event_plan_review_build_changes(array $snapshot, array $current): array
    {
        $changes = array();

        $string_fields = array(
            'title' => __('Plan title', 'backstage-venue-manager'),
            'event_date' => __('Event date', 'backstage-venue-manager'),
            'start_time' => __('Start time', 'backstage-venue-manager'),
            'end_time' => __('End time', 'backstage-venue-manager'),
            'status' => __('Plan status', 'backstage-venue-manager'),
            'secondary_vendor_type' => __('Secondary vendor type', 'backstage-venue-manager'),
        );

        foreach ($string_fields as $field => $label) {
            $before = (string) ($snapshot[$field] ?? '');
            $after = (string) ($current[$field] ?? '');
            if ($before === $after) {
                continue;
            }

            if ($field === 'event_date') {
                $before_label = bvmgr_event_plan_review_format_date($before);
                $after_label = bvmgr_event_plan_review_format_date($after);
            } elseif (in_array($field, array('start_time', 'end_time'), true)) {
                $before_label = bvmgr_event_plan_review_format_time($before);
                $after_label = bvmgr_event_plan_review_format_time($after);
            } elseif ($field === 'status') {
                $before_label = bvmgr_event_plan_review_status_label($before);
                $after_label = bvmgr_event_plan_review_status_label($after);
            } elseif ($field === 'secondary_vendor_type') {
                $before_label = bvmgr_event_plan_review_term_label($before);
                $after_label = bvmgr_event_plan_review_term_label($after);
            } else {
                $before_label = ($before !== '') ? $before : __('Not set', 'backstage-venue-manager');
                $after_label = ($after !== '') ? $after : __('Not set', 'backstage-venue-manager');
            }

            $change = array(
                'field' => $field,
                'label' => $label,
                'before' => $before,
                'after' => $after,
                'before_label' => $before_label,
                'after_label' => $after_label,
                /* translators: 1: field label, 2: previous value, 3: current value. */
                'summary' => sprintf(__('%1$s changed from %2$s to %3$s', 'backstage-venue-manager'), $label, $before_label, $after_label),
            );
            if (function_exists('bvmgr_event_plan_review_change_is_noop') && bvmgr_event_plan_review_change_is_noop($change)) {
                continue;
            }

            $changes[] = $change;
        }

        $before_venue = absint($snapshot['venue_id'] ?? 0);
        $after_venue = absint($current['venue_id'] ?? 0);
        if ($before_venue !== $after_venue) {
            $changes[] = array(
                'field' => 'venue_id',
                'label' => __('Venue', 'backstage-venue-manager'),
                'before' => $before_venue,
                'after' => $after_venue,
                'before_label' => bvmgr_event_plan_review_venue_label($before_venue),
                'after_label' => bvmgr_event_plan_review_venue_label($after_venue),
                'summary' => sprintf(
                    /* translators: 1: previous venue label, 2: current venue label. */
                    __('Venue changed from %1$s to %2$s', 'backstage-venue-manager'),
                    bvmgr_event_plan_review_venue_label($before_venue),
                    bvmgr_event_plan_review_venue_label($after_venue)
                ),
            );
        }

        $before_primary = absint($snapshot['primary_vendor_id'] ?? 0);
        $after_primary = absint($current['primary_vendor_id'] ?? 0);
        if ($before_primary !== $after_primary) {
            $changes[] = array(
                'field' => 'primary_vendor_id',
                'label' => __('Primary vendor', 'backstage-venue-manager'),
                'before' => $before_primary,
                'after' => $after_primary,
                'before_label' => bvmgr_event_plan_review_vendor_label($before_primary),
                'after_label' => bvmgr_event_plan_review_vendor_label($after_primary),
                'summary' => sprintf(
                    /* translators: 1: previous primary vendor label, 2: current primary vendor label. */
                    __('Primary vendor changed from %1$s to %2$s', 'backstage-venue-manager'),
                    bvmgr_event_plan_review_vendor_label($before_primary),
                    bvmgr_event_plan_review_vendor_label($after_primary)
                ),
            );
        }

        $before_secondary = array_values(array_unique(array_filter(array_map('absint', (array) ($snapshot['secondary_vendor_ids'] ?? array())))));
        $after_secondary = array_values(array_unique(array_filter(array_map('absint', (array) ($current['secondary_vendor_ids'] ?? array())))));
        sort($before_secondary);
        sort($after_secondary);
        if ($before_secondary !== $after_secondary) {
            $added = array_values(array_diff($after_secondary, $before_secondary));
            $removed = array_values(array_diff($before_secondary, $after_secondary));
            $parts = array();
            if (!empty($added)) {
                /* translators: %s: comma-separated vendor names added to the secondary vendor list. */
                $parts[] = sprintf(__('added %s', 'backstage-venue-manager'), implode(', ', bvmgr_event_plan_review_secondary_vendor_labels($added)));
            }
            if (!empty($removed)) {
                /* translators: %s: comma-separated vendor names removed from the secondary vendor list. */
                $parts[] = sprintf(__('removed %s', 'backstage-venue-manager'), implode(', ', bvmgr_event_plan_review_secondary_vendor_labels($removed)));
            }
            if (empty($parts)) {
                $parts[] = __('secondary vendor selections changed', 'backstage-venue-manager');
            }
            $changes[] = array(
                'field' => 'secondary_vendor_ids',
                'label' => __('Secondary vendors', 'backstage-venue-manager'),
                'before' => $before_secondary,
                'after' => $after_secondary,
                /* translators: %s: semicolon-separated secondary vendor change summaries. */
                'summary' => sprintf(__('Secondary vendors updated: %s', 'backstage-venue-manager'), implode('; ', $parts)),
            );
        }

        $before_lineup = is_array($snapshot['lineup_rows'] ?? null) ? (array) $snapshot['lineup_rows'] : array();
        $after_lineup = is_array($current['lineup_rows'] ?? null) ? (array) $current['lineup_rows'] : array();
        if (function_exists('bvmgr_event_plan_review_lineup_signature') && bvmgr_event_plan_review_lineup_signature($before_lineup) !== bvmgr_event_plan_review_lineup_signature($after_lineup)) {
            $changes[] = array(
                'field' => 'lineup_rows',
                'label' => __('Lineup & schedule', 'backstage-venue-manager'),
                'before' => $before_lineup,
                'after' => $after_lineup,
                'summary' => function_exists('bvmgr_event_plan_review_lineup_summary')
                    ? bvmgr_event_plan_review_lineup_summary($before_lineup, $after_lineup)
                    : __('Lineup & schedule changed.', 'backstage-venue-manager'),
            );
        }

        return $changes;
    }
}


if (!function_exists('bvmgr_event_plan_review_clean_text')) {
    function bvmgr_event_plan_review_clean_text(string $text): string
    {
        $text = wp_strip_all_tags((string) $text);
        $text = str_replace(
            array('\u2192', 'u2192', '→', 'Â·', '·', 'â€¢', '—', '–', 'â€”', 'â€“', 'Â'),
            array(' to ', ' to ', ' to ', ' | ', ' | ', ' * ', ' - ', ' - ', ' - ', ' - ', ''),
            $text
        );
        $text = preg_replace('/\s+/', ' ', $text);
        return trim((string) $text);
    }
}


if (!function_exists('bvmgr_event_plan_review_compact_summary')) {
    function bvmgr_event_plan_review_compact_summary(array $changes): string
    {
        if (empty($changes)) {
            return '';
        }

        $by_field = array();
        foreach ($changes as $change) {
            $field = sanitize_key((string) ($change['field'] ?? ''));
            if ($field === '') {
                continue;
            }
            $by_field[$field] = $change;
        }

        if (!empty($by_field['secondary_vendor_type'])) {
            $type_change = $by_field['secondary_vendor_type'];
            $before_label = trim((string) ($type_change['before_label'] ?? ''));
            $after_label = trim((string) ($type_change['after_label'] ?? ''));
            if ($before_label !== '' && $after_label !== '') {
                if (function_exists('bvmgr_event_plan_review_compare_token') && bvmgr_event_plan_review_compare_token($before_label) === bvmgr_event_plan_review_compare_token($after_label)) {
                    unset($by_field['secondary_vendor_type']);
                } else {
                $summary = sprintf(
                    /* translators: 1: previous secondary vendor type label, 2: current secondary vendor type label. */
                    __('Secondary vendor type changed from %1$s to %2$s', 'backstage-venue-manager'),
                    $before_label,
                    $after_label
                );

                $extras = array();
                if (!empty($by_field['secondary_vendor_ids'])) {
                    $extras[] = __('secondary vendor selections were updated to match', 'backstage-venue-manager');
                }
                if (!empty($by_field['status'])) {
                    $before_status = sanitize_key((string) ($by_field['status']['before'] ?? ''));
                    $after_status = sanitize_key((string) ($by_field['status']['after'] ?? ''));
                    if ($before_status === 'published' && $after_status === 'draft') {
                        $extras[] = __('plan returned to Draft for review', 'backstage-venue-manager');
                    }
                }

                if (!empty($extras)) {
                    $summary .= '. ' . ucfirst(implode('; ', $extras)) . '.';
                }

                return bvmgr_event_plan_review_clean_text($summary);
                }
            }
        }

        if (!empty($by_field['lineup_rows'])) {
            $lineup_summary = trim((string) ($by_field['lineup_rows']['summary'] ?? ''));
            if ($lineup_summary !== '') {
                return bvmgr_event_plan_review_clean_text($lineup_summary);
            }
        }

        foreach ($changes as $change) {
            $field = sanitize_key((string) ($change['field'] ?? ''));
            if ($field === 'status' && count($changes) > 1) {
                continue;
            }

            $summary = trim((string) ($change['summary'] ?? ''));
            if ($summary !== '') {
                return bvmgr_event_plan_review_clean_text($summary);
            }
        }

        foreach ($changes as $change) {
            $summary = trim((string) ($change['summary'] ?? ''));
            if ($summary !== '') {
                return bvmgr_event_plan_review_clean_text($summary);
            }
        }

        return '';
    }
}

if (!function_exists('bvmgr_event_plan_review_integrity_message')) {
    function bvmgr_event_plan_review_integrity_message(): string
    {
        return __('Stored Event Plan review state is unavailable or invalid. Review and republish this Event Plan to establish a clean baseline.', 'backstage-venue-manager');
    }
}

if (!function_exists('bvmgr_event_plan_review_get_integrity_issue')) {
    function bvmgr_event_plan_review_get_integrity_issue(int $plan_id): array
    {
        $snapshot_state = bvmgr_event_plan_review_get_snapshot_state($plan_id);
        if ('invalid' === ($snapshot_state['state'] ?? '')) {
            return array(
                'type' => 'snapshot_invalid',
                'reason' => (string) ($snapshot_state['reason'] ?? ''),
                'message' => bvmgr_event_plan_review_integrity_message(),
            );
        }

        $changes_state = bvmgr_event_plan_review_get_changes_state($plan_id);
        if ('invalid' === ($changes_state['state'] ?? '')) {
            return array(
                'type' => 'changes_invalid',
                'reason' => (string) ($changes_state['reason'] ?? ''),
                'message' => bvmgr_event_plan_review_integrity_message(),
            );
        }

        return array();
    }
}

if (!function_exists('bvmgr_event_plan_review_clear_changes')) {
    function bvmgr_event_plan_review_clear_changes(int $plan_id): void
    {
        delete_post_meta($plan_id, bvmgr_event_plan_review_meta_key('changes_json'));
        delete_post_meta($plan_id, bvmgr_event_plan_review_meta_key('changes_at'));
        delete_post_meta($plan_id, bvmgr_event_plan_review_meta_key('changes_by'));
        delete_post_meta($plan_id, bvmgr_event_plan_review_meta_key('changes_source'));
    }
}

if (!function_exists('bvmgr_event_plan_review_mark_published')) {
    function bvmgr_event_plan_review_mark_published(int $plan_id, string $source = 'event_plan_editor', int $user_id = 0): array
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0 || get_post_type($plan_id) !== 'vms_event_plan') {
            return array();
        }

        if ($user_id <= 0) {
            $user_id = get_current_user_id();
        }

        $snapshot = bvmgr_event_plan_review_current_snapshot($plan_id);
        update_post_meta($plan_id, bvmgr_event_plan_review_meta_key('snapshot_json'), wp_json_encode($snapshot));
        update_post_meta($plan_id, bvmgr_event_plan_review_meta_key('snapshot_at'), current_time('mysql'));
        update_post_meta($plan_id, bvmgr_event_plan_review_meta_key('snapshot_by'), absint($user_id));
        bvmgr_event_plan_review_clear_changes($plan_id);

        return $snapshot;
    }
}

if (!function_exists('bvmgr_event_plan_review_touch')) {
    function bvmgr_event_plan_review_touch(int $plan_id, string $source = 'unknown', int $user_id = 0): array
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0 || get_post_type($plan_id) !== 'vms_event_plan') {
            return array();
        }

        $snapshot_state = bvmgr_event_plan_review_get_snapshot_state($plan_id);
        if ('missing' === ($snapshot_state['state'] ?? '')) {
            bvmgr_event_plan_review_clear_changes($plan_id);
            return array();
        }

        if ('invalid' === ($snapshot_state['state'] ?? '')) {
            $changes_state = bvmgr_event_plan_review_get_changes_state($plan_id);
            return 'valid' === ($changes_state['state'] ?? '') ? (array) ($changes_state['value'] ?? array()) : array();
        }

        if ($user_id <= 0) {
            $user_id = get_current_user_id();
        }

        $snapshot = (array) ($snapshot_state['value'] ?? array());
        $current = bvmgr_event_plan_review_current_snapshot($plan_id);
        $changes = bvmgr_event_plan_review_build_changes($snapshot, $current);
        if (empty($changes)) {
            bvmgr_event_plan_review_clear_changes($plan_id);
            return array();
        }

        $payload = array(
            'count' => count($changes),
            'changes' => $changes,
        );

        update_post_meta($plan_id, bvmgr_event_plan_review_meta_key('changes_json'), wp_json_encode($payload));
        update_post_meta($plan_id, bvmgr_event_plan_review_meta_key('changes_at'), current_time('mysql'));
        update_post_meta($plan_id, bvmgr_event_plan_review_meta_key('changes_by'), absint($user_id));
        update_post_meta($plan_id, bvmgr_event_plan_review_meta_key('changes_source'), sanitize_key($source));

        return $payload;
    }
}

if (!function_exists('bvmgr_event_plan_review_has_changes')) {
    function bvmgr_event_plan_review_has_changes(int $plan_id): bool
    {
        $changes_state = bvmgr_event_plan_review_get_changes_state($plan_id);
        if ('valid' === ($changes_state['state'] ?? '')) {
            $changes = (array) ($changes_state['value'] ?? array());
            if (!empty($changes['count']) && !empty($changes['changes']) && is_array($changes['changes'])) {
                return true;
            }
        }

        $snapshot_state = bvmgr_event_plan_review_get_snapshot_state($plan_id);
        return 'invalid' === ($snapshot_state['state'] ?? '') || 'invalid' === ($changes_state['state'] ?? '');
    }
}

if (!function_exists('bvmgr_event_plan_review_render_banner')) {
    function bvmgr_event_plan_review_render_banner(WP_Post $post): void
    {
        if ($post->post_type !== 'vms_event_plan') {
            return;
        }

        $plan_id = (int) $post->ID;
        $changes_state = bvmgr_event_plan_review_get_changes_state($plan_id);
        $changes = 'valid' === ($changes_state['state'] ?? '') ? (array) ($changes_state['value'] ?? array()) : array();
        $integrity_issue = bvmgr_event_plan_review_get_integrity_issue($plan_id);
        $has_detailed_changes = !empty($changes['changes']) && is_array($changes['changes']);

        if (!$has_detailed_changes && empty($integrity_issue)) {
            return;
        }

        $count = max(0, (int) ($changes['count'] ?? count((array) ($changes['changes'] ?? array()))));
        $changed_at = (string) get_post_meta($plan_id, bvmgr_event_plan_review_meta_key('changes_at'), true);
        $changed_by = absint(get_post_meta($plan_id, bvmgr_event_plan_review_meta_key('changes_by'), true));
        $changed_source = (string) get_post_meta($plan_id, bvmgr_event_plan_review_meta_key('changes_source'), true);
        $user_name = '';
        if ($changed_by > 0) {
            $user = get_userdata($changed_by);
            if ($user instanceof WP_User) {
                $user_name = (string) $user->display_name;
            }
        }

        $meta_bits = array();
        if ($changed_at !== '') {
            /* translators: %s: timestamp when the tracked changes were last updated. */
            $meta_bits[] = sprintf(__('Updated: %s', 'backstage-venue-manager'), $changed_at);
        }
        if ($user_name !== '') {
            /* translators: %s: display name of the user who made the tracked changes. */
            $meta_bits[] = sprintf(__('By: %s', 'backstage-venue-manager'), $user_name);
        }
        if ($changed_source !== '') {
            /* translators: %s: source label for the tracked changes. */
            $meta_bits[] = sprintf(__('Source: %s', 'backstage-venue-manager'), bvmgr_event_plan_review_source_label($changed_source));
        }

        echo '<div class="notice notice-warning inline">';
        if ($has_detailed_changes) {
            /* translators: %d: number of unpublished tracked changes since the last publish. */
            echo '<p><strong>' . esc_html__('Needs Review', 'backstage-venue-manager') . '</strong> ' . esc_html(sprintf(_n('%d unpublished change since last publish.', '%d unpublished changes since last publish.', $count, 'backstage-venue-manager'), $count)) . '</p>';
        } else {
            echo '<p><strong>' . esc_html__('Needs Review', 'backstage-venue-manager') . '</strong> ' . esc_html(bvmgr_event_plan_review_integrity_message()) . '</p>';
        }
        if (!empty($meta_bits)) {
            echo '<p class="description">' . esc_html(implode(' | ', $meta_bits)) . '</p>';
        }
        if ($has_detailed_changes) {
            echo '<ul class="vms-review-banner-list">';
            foreach (array_slice((array) $changes['changes'], 0, 6) as $change) {
                $summary = trim((string) ($change['summary'] ?? ''));
                if ($summary === '') {
                    continue;
                }
                echo '<li>' . esc_html(bvmgr_event_plan_review_clean_text($summary)) . '</li>';
            }
            echo '</ul>';
        }
        if (!empty($integrity_issue)) {
            echo '<p class="description">' . esc_html((string) ($integrity_issue['message'] ?? bvmgr_event_plan_review_integrity_message())) . '</p>';
        }
        echo '<p><em>' . esc_html__('Review the plan, then click Publish Now again to make the current version the new baseline.', 'backstage-venue-manager') . '</em></p>';
        echo '</div>';
    }
}
add_action('edit_form_after_title', 'bvmgr_event_plan_review_render_banner', 8);

if (!function_exists('bvmgr_event_plan_review_render_status_note')) {
    function bvmgr_event_plan_review_render_status_note(string $column, int $post_id): void
    {
        if ($column !== 'vms_plan_status' || !bvmgr_event_plan_review_has_changes($post_id)) {
            return;
        }

        $changes = bvmgr_event_plan_review_get_changes($post_id);
        $integrity_issue = bvmgr_event_plan_review_get_integrity_issue($post_id);
        $count = max(0, (int) ($changes['count'] ?? 0));
        $first = bvmgr_event_plan_review_compact_summary((array) ($changes['changes'] ?? array()));

        echo '<div class="description vms-review-status-note"><strong>' . esc_html__('Needs Review', 'backstage-venue-manager') . '</strong>';
        if ($count > 0) {
            /* translators: %d: number of tracked changes. */
            echo ' | ' . esc_html(sprintf(_n('%d tracked change', '%d tracked changes', $count, 'backstage-venue-manager'), $count));
        }
        echo '</div>';
        if ($first !== '') {
            echo '<div class="description">' . esc_html(bvmgr_event_plan_review_clean_text($first)) . '</div>';
        } elseif (!empty($integrity_issue)) {
            echo '<div class="description">' . esc_html((string) ($integrity_issue['message'] ?? bvmgr_event_plan_review_integrity_message())) . '</div>';
        }
    }
}
add_action('manage_vms_event_plan_posts_custom_column', 'bvmgr_event_plan_review_render_status_note', 20, 2);

if (!function_exists('bvmgr_event_plan_review_post_data')) {
    function bvmgr_event_plan_review_post_data(): array
    {
        static $request = null;
        if (is_array($request)) {
            return $request;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Review fallback inspects editor POST only to read the action after local nonce verification.
        $request = (isset($_POST) && is_array($_POST)) ? wp_unslash($_POST) : array();
        return is_array($request) ? $request : array();
    }
}

if (!function_exists('bvmgr_event_plan_review_editor_action')) {
    function bvmgr_event_plan_review_editor_action(): string
    {
        if (function_exists('bvmgr_event_plan_editor_verified_post_data')) {
            $request = bvmgr_event_plan_editor_verified_post_data();
            return isset($request['vms_event_plan_action']) ? sanitize_key((string) $request['vms_event_plan_action']) : '';
        }

        $request = bvmgr_event_plan_review_post_data();
        if (!isset($request['vms_event_plan_details_nonce']) || is_array($request['vms_event_plan_details_nonce'])) {
            return '';
        }

        $nonce = sanitize_text_field((string) $request['vms_event_plan_details_nonce']);
        if ($nonce === '' || !wp_verify_nonce($nonce, 'vms_save_event_plan_details')) {
            return '';
        }

        if (!isset($request['vms_event_plan_action']) || is_array($request['vms_event_plan_action'])) {
            return '';
        }

        return sanitize_key((string) $request['vms_event_plan_action']);
    }
}

if (!function_exists('bvmgr_event_plan_review_after_save')) {
    function bvmgr_event_plan_review_after_save(int $post_id, WP_Post $post): void
    {
        if ($post->post_type !== 'vms_event_plan') {
            return;
        }
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $action = bvmgr_event_plan_review_editor_action();
        if ($action === 'publish_now') {
            bvmgr_event_plan_review_mark_published($post_id, 'event_plan_editor');
            return;
        }

        bvmgr_event_plan_review_touch($post_id, 'event_plan_editor');
    }
}
add_action('save_post_vms_event_plan', 'bvmgr_event_plan_review_after_save', 999, 2);
