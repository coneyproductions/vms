<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_event_plan_review_meta_key')) {
    function vms_event_plan_review_meta_key(string $slot): string
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

if (!function_exists('vms_event_plan_review_event_meta_key')) {
    function vms_event_plan_review_event_meta_key(string $key, string $fallback): string
    {
        if (function_exists('vms_meta_key')) {
            $resolved = (string) vms_meta_key('event_plan', $key);
            if ($resolved !== '') {
                return $resolved;
            }
        }

        return $fallback;
    }
}

if (!function_exists('vms_event_plan_review_current_snapshot')) {
    function vms_event_plan_review_current_snapshot(int $plan_id): array
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0 || get_post_type($plan_id) !== 'vms_event_plan') {
            return array();
        }

        $status_key = vms_event_plan_review_event_meta_key('status', '_vms_event_plan_status');
        $date_key = vms_event_plan_review_event_meta_key('date', '_vms_event_date');
        $venue_key = vms_event_plan_review_event_meta_key('venue_id', '_vms_venue_id');
        $primary_key = vms_event_plan_review_event_meta_key('band_vendor_id', '_vms_band_vendor_id');
        $secondary_type_key = vms_event_plan_review_event_meta_key('secondary_vendor_type', '_vms_secondary_vendor_type');
        $secondary_ids_key = vms_event_plan_review_event_meta_key('secondary_vendor_ids', '_vms_secondary_vendor_ids');
        $secondary_id_index_key = vms_event_plan_review_event_meta_key('secondary_vendor_id', '_vms_secondary_vendor_id');

        $secondary_vendor_ids = get_post_meta($plan_id, $secondary_ids_key, true);
        if (!is_array($secondary_vendor_ids)) {
            $secondary_vendor_ids = get_post_meta($plan_id, $secondary_id_index_key, false);
        }
        $secondary_vendor_ids = array_values(array_unique(array_filter(array_map('absint', (array) $secondary_vendor_ids))));
        sort($secondary_vendor_ids);

        $status = function_exists('vms_event_plan_get_status')
            ? (string) vms_event_plan_get_status($plan_id, 'review_snapshot')
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
        if (function_exists('vms_event_plan_review_lineup_rows')) {
            $lineup_rows = vms_event_plan_review_lineup_rows($lineup_rows);
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

if (!function_exists('vms_event_plan_review_get_snapshot')) {
    function vms_event_plan_review_get_snapshot(int $plan_id): array
    {
        $raw = (string) get_post_meta($plan_id, vms_event_plan_review_meta_key('snapshot_json'), true);
        if ($raw === '') {
            return array();
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : array();
    }
}

if (!function_exists('vms_event_plan_review_get_changes')) {
    function vms_event_plan_review_get_changes(int $plan_id): array
    {
        $raw = (string) get_post_meta($plan_id, vms_event_plan_review_meta_key('changes_json'), true);
        if ($raw === '') {
            return array();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return array();
        }

        // Guard display paths from older stored no-op changes such as
        // "Secondary vendor type changed from Food Truck to Food Truck".
        $changes = isset($decoded['changes']) && is_array($decoded['changes']) ? (array) $decoded['changes'] : array();
        if (!empty($changes) && function_exists('vms_event_plan_review_change_is_noop')) {
            $filtered = array();
            foreach ($changes as $change) {
                if (!is_array($change) || vms_event_plan_review_change_is_noop((array) $change)) {
                    continue;
                }
                $filtered[] = $change;
            }
            $decoded['changes'] = $filtered;
            $decoded['count'] = count($filtered);
            if (empty($filtered)) {
                return array();
            }
        }

        return $decoded;
    }
}

if (!function_exists('vms_event_plan_review_source_label')) {
    function vms_event_plan_review_source_label(string $source): string
    {
        $source = sanitize_key($source);
        $map = array(
            'event_plan_editor' => __('Event Plan editor', 'vms'),
            'fill_dates' => __('Fill Dates', 'vms'),
            'importer' => __('Importer', 'vms'),
            'unknown' => __('Unknown', 'vms'),
        );

        return $map[$source] ?? ucwords(str_replace(array('_', '-'), ' ', $source));
    }
}

if (!function_exists('vms_event_plan_review_vendor_label')) {
    function vms_event_plan_review_vendor_label(int $vendor_id): string
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return __('None', 'vms');
        }

        $title = trim((string) get_the_title($vendor_id));
        /* translators: %d: vendor post ID. */
        return $title !== '' ? $title : sprintf(__('Vendor #%d', 'vms'), $vendor_id);
    }
}

if (!function_exists('vms_event_plan_review_term_label')) {
    function vms_event_plan_review_term_label(string $slug): string
    {
        $slug = function_exists('vms_vendor_type_normalize_slug')
            ? vms_vendor_type_normalize_slug($slug)
            : sanitize_key($slug);
        if ($slug === '') {
            return __('Not set', 'vms');
        }

        $term = function_exists('vms_vendor_type_get_term')
            ? vms_vendor_type_get_term($slug)
            : get_term_by('slug', $slug, 'vms_vendor_type');
        if ($term instanceof WP_Term) {
            return (string) $term->name;
        }

        return ucwords(str_replace(array('-', '_'), ' ', $slug));
    }
}

if (!function_exists('vms_event_plan_review_venue_label')) {
    function vms_event_plan_review_venue_label(int $venue_id): string
    {
        $venue_id = absint($venue_id);
        if ($venue_id <= 0) {
            return __('Unassigned venue', 'vms');
        }

        $title = trim((string) get_the_title($venue_id));
        /* translators: %d: venue post ID. */
        return $title !== '' ? $title : sprintf(__('Venue #%d', 'vms'), $venue_id);
    }
}


if (!function_exists('vms_event_plan_review_compare_token')) {
    function vms_event_plan_review_compare_token($value): string
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

if (!function_exists('vms_event_plan_review_change_is_noop')) {
    function vms_event_plan_review_change_is_noop(array $change): bool
    {
        $before_label = vms_event_plan_review_compare_token($change['before_label'] ?? null);
        $after_label = vms_event_plan_review_compare_token($change['after_label'] ?? null);
        if ($before_label !== '' && $after_label !== '' && $before_label === $after_label) {
            return true;
        }

        $before = vms_event_plan_review_compare_token($change['before'] ?? null);
        $after = vms_event_plan_review_compare_token($change['after'] ?? null);
        return $before !== '' && $after !== '' && $before === $after;
    }
}

if (!function_exists('vms_event_plan_review_status_label')) {
    function vms_event_plan_review_status_label(string $status): string
    {
        $status = sanitize_key($status);
        if ($status === 'canceled') {
            $status = 'cancelled';
        }
        if ($status === '') {
            $status = 'draft';
        }

        if (function_exists('vms_event_plan_status_label')) {
            return (string) vms_event_plan_status_label($status);
        }

        return ucwords(str_replace(array('_', '-'), ' ', $status));
    }
}

if (!function_exists('vms_event_plan_review_format_date')) {
    function vms_event_plan_review_format_date(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return __('Not set', 'vms');
        }

        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('M j, Y');
        }

        return $date;
    }
}

if (!function_exists('vms_event_plan_review_format_time')) {
    function vms_event_plan_review_format_time(string $time): string
    {
        $time = trim($time);
        if ($time === '') {
            return __('Not set', 'vms');
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

if (!function_exists('vms_event_plan_review_secondary_vendor_labels')) {
    function vms_event_plan_review_secondary_vendor_labels(array $ids): array
    {
        $labels = array();
        foreach ($ids as $vendor_id) {
            $vendor_id = absint($vendor_id);
            if ($vendor_id <= 0) {
                continue;
            }
            $labels[] = vms_event_plan_review_vendor_label($vendor_id);
        }
        return $labels;
    }
}

if (!function_exists('vms_event_plan_review_lineup_rows')) {
    function vms_event_plan_review_lineup_rows(array $rows): array
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
                'vendor_label' => vms_event_plan_review_vendor_label($vendor_id),
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

if (!function_exists('vms_event_plan_review_lineup_signature')) {
    function vms_event_plan_review_lineup_signature(array $rows): array
    {
        $parts = array();
        foreach (vms_event_plan_review_lineup_rows($rows) as $row) {
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

if (!function_exists('vms_event_plan_review_lineup_summary')) {
    function vms_event_plan_review_lineup_summary(array $before_rows, array $after_rows): string
    {
        $before_rows = vms_event_plan_review_lineup_rows($before_rows);
        $after_rows = vms_event_plan_review_lineup_rows($after_rows);

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
                $timing_changed[] = vms_event_plan_review_vendor_label((int) $vendor_id);
            }
            $before_fee = $before_row['guaranteed_fee'] ?? '';
            $after_fee = $after_row['guaranteed_fee'] ?? '';
            if ((string) $before_fee !== (string) $after_fee) {
                $fee_changed[] = vms_event_plan_review_vendor_label((int) $vendor_id);
            }
            if ((int) ($before_row['sort_order'] ?? 0) !== (int) ($after_row['sort_order'] ?? 0)) {
                $order_changed = true;
            }
        }

        $parts = array();
        if (!empty($added)) {
            /* translators: %s: comma-separated vendor names added to the lineup. */
            $parts[] = sprintf(__('added %s', 'vms'), implode(', ', vms_event_plan_review_secondary_vendor_labels($added)));
        }
        if (!empty($removed)) {
            /* translators: %s: comma-separated vendor names removed from the lineup. */
            $parts[] = sprintf(__('removed %s', 'vms'), implode(', ', vms_event_plan_review_secondary_vendor_labels($removed)));
        }
        if (!empty($timing_changed)) {
            /* translators: %s: comma-separated vendor names with changed set times. */
            $parts[] = sprintf(__('set times changed for %s', 'vms'), implode(', ', $timing_changed));
        }
        if (!empty($fee_changed)) {
            /* translators: %s: comma-separated vendor names with changed compensation. */
            $parts[] = sprintf(__('supporting compensation changed for %s', 'vms'), implode(', ', $fee_changed));
        }
        if ($order_changed) {
            $parts[] = __('lineup order changed', 'vms');
        }

        if (empty($parts)) {
            $parts[] = __('lineup details changed', 'vms');
        }

        /* translators: %s: semicolon-separated lineup change summaries. */
        return sprintf(__('Lineup & schedule updated: %s', 'vms'), implode('; ', $parts));
    }
}

if (!function_exists('vms_event_plan_review_build_changes')) {
    function vms_event_plan_review_build_changes(array $snapshot, array $current): array
    {
        $changes = array();

        $string_fields = array(
            'title' => __('Plan title', 'vms'),
            'event_date' => __('Event date', 'vms'),
            'start_time' => __('Start time', 'vms'),
            'end_time' => __('End time', 'vms'),
            'status' => __('Plan status', 'vms'),
            'secondary_vendor_type' => __('Secondary vendor type', 'vms'),
        );

        foreach ($string_fields as $field => $label) {
            $before = (string) ($snapshot[$field] ?? '');
            $after = (string) ($current[$field] ?? '');
            if ($before === $after) {
                continue;
            }

            if ($field === 'event_date') {
                $before_label = vms_event_plan_review_format_date($before);
                $after_label = vms_event_plan_review_format_date($after);
            } elseif (in_array($field, array('start_time', 'end_time'), true)) {
                $before_label = vms_event_plan_review_format_time($before);
                $after_label = vms_event_plan_review_format_time($after);
            } elseif ($field === 'status') {
                $before_label = vms_event_plan_review_status_label($before);
                $after_label = vms_event_plan_review_status_label($after);
            } elseif ($field === 'secondary_vendor_type') {
                $before_label = vms_event_plan_review_term_label($before);
                $after_label = vms_event_plan_review_term_label($after);
            } else {
                $before_label = ($before !== '') ? $before : __('Not set', 'vms');
                $after_label = ($after !== '') ? $after : __('Not set', 'vms');
            }

            $change = array(
                'field' => $field,
                'label' => $label,
                'before' => $before,
                'after' => $after,
                'before_label' => $before_label,
                'after_label' => $after_label,
                /* translators: 1: field label, 2: previous value, 3: current value. */
                'summary' => sprintf(__('%1$s changed from %2$s to %3$s', 'vms'), $label, $before_label, $after_label),
            );
            if (function_exists('vms_event_plan_review_change_is_noop') && vms_event_plan_review_change_is_noop($change)) {
                continue;
            }

            $changes[] = $change;
        }

        $before_venue = absint($snapshot['venue_id'] ?? 0);
        $after_venue = absint($current['venue_id'] ?? 0);
        if ($before_venue !== $after_venue) {
            $changes[] = array(
                'field' => 'venue_id',
                'label' => __('Venue', 'vms'),
                'before' => $before_venue,
                'after' => $after_venue,
                'before_label' => vms_event_plan_review_venue_label($before_venue),
                'after_label' => vms_event_plan_review_venue_label($after_venue),
                'summary' => sprintf(
                    /* translators: 1: previous venue label, 2: current venue label. */
                    __('Venue changed from %1$s to %2$s', 'vms'),
                    vms_event_plan_review_venue_label($before_venue),
                    vms_event_plan_review_venue_label($after_venue)
                ),
            );
        }

        $before_primary = absint($snapshot['primary_vendor_id'] ?? 0);
        $after_primary = absint($current['primary_vendor_id'] ?? 0);
        if ($before_primary !== $after_primary) {
            $changes[] = array(
                'field' => 'primary_vendor_id',
                'label' => __('Primary vendor', 'vms'),
                'before' => $before_primary,
                'after' => $after_primary,
                'before_label' => vms_event_plan_review_vendor_label($before_primary),
                'after_label' => vms_event_plan_review_vendor_label($after_primary),
                'summary' => sprintf(
                    /* translators: 1: previous primary vendor label, 2: current primary vendor label. */
                    __('Primary vendor changed from %1$s to %2$s', 'vms'),
                    vms_event_plan_review_vendor_label($before_primary),
                    vms_event_plan_review_vendor_label($after_primary)
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
                $parts[] = sprintf(__('added %s', 'vms'), implode(', ', vms_event_plan_review_secondary_vendor_labels($added)));
            }
            if (!empty($removed)) {
                /* translators: %s: comma-separated vendor names removed from the secondary vendor list. */
                $parts[] = sprintf(__('removed %s', 'vms'), implode(', ', vms_event_plan_review_secondary_vendor_labels($removed)));
            }
            if (empty($parts)) {
                $parts[] = __('secondary vendor selections changed', 'vms');
            }
            $changes[] = array(
                'field' => 'secondary_vendor_ids',
                'label' => __('Secondary vendors', 'vms'),
                'before' => $before_secondary,
                'after' => $after_secondary,
                /* translators: %s: semicolon-separated secondary vendor change summaries. */
                'summary' => sprintf(__('Secondary vendors updated: %s', 'vms'), implode('; ', $parts)),
            );
        }

        $before_lineup = is_array($snapshot['lineup_rows'] ?? null) ? (array) $snapshot['lineup_rows'] : array();
        $after_lineup = is_array($current['lineup_rows'] ?? null) ? (array) $current['lineup_rows'] : array();
        if (function_exists('vms_event_plan_review_lineup_signature') && vms_event_plan_review_lineup_signature($before_lineup) !== vms_event_plan_review_lineup_signature($after_lineup)) {
            $changes[] = array(
                'field' => 'lineup_rows',
                'label' => __('Lineup & schedule', 'vms'),
                'before' => $before_lineup,
                'after' => $after_lineup,
                'summary' => function_exists('vms_event_plan_review_lineup_summary')
                    ? vms_event_plan_review_lineup_summary($before_lineup, $after_lineup)
                    : __('Lineup & schedule changed.', 'vms'),
            );
        }

        return $changes;
    }
}


if (!function_exists('vms_event_plan_review_clean_text')) {
    function vms_event_plan_review_clean_text(string $text): string
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


if (!function_exists('vms_event_plan_review_compact_summary')) {
    function vms_event_plan_review_compact_summary(array $changes): string
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
                if (function_exists('vms_event_plan_review_compare_token') && vms_event_plan_review_compare_token($before_label) === vms_event_plan_review_compare_token($after_label)) {
                    unset($by_field['secondary_vendor_type']);
                } else {
                $summary = sprintf(
                    /* translators: 1: previous secondary vendor type label, 2: current secondary vendor type label. */
                    __('Secondary vendor type changed from %1$s to %2$s', 'vms'),
                    $before_label,
                    $after_label
                );

                $extras = array();
                if (!empty($by_field['secondary_vendor_ids'])) {
                    $extras[] = __('secondary vendor selections were updated to match', 'vms');
                }
                if (!empty($by_field['status'])) {
                    $before_status = sanitize_key((string) ($by_field['status']['before'] ?? ''));
                    $after_status = sanitize_key((string) ($by_field['status']['after'] ?? ''));
                    if ($before_status === 'published' && $after_status === 'draft') {
                        $extras[] = __('plan returned to Draft for review', 'vms');
                    }
                }

                if (!empty($extras)) {
                    $summary .= '. ' . ucfirst(implode('; ', $extras)) . '.';
                }

                return vms_event_plan_review_clean_text($summary);
                }
            }
        }

        if (!empty($by_field['lineup_rows'])) {
            $lineup_summary = trim((string) ($by_field['lineup_rows']['summary'] ?? ''));
            if ($lineup_summary !== '') {
                return vms_event_plan_review_clean_text($lineup_summary);
            }
        }

        foreach ($changes as $change) {
            $field = sanitize_key((string) ($change['field'] ?? ''));
            if ($field === 'status' && count($changes) > 1) {
                continue;
            }

            $summary = trim((string) ($change['summary'] ?? ''));
            if ($summary !== '') {
                return vms_event_plan_review_clean_text($summary);
            }
        }

        foreach ($changes as $change) {
            $summary = trim((string) ($change['summary'] ?? ''));
            if ($summary !== '') {
                return vms_event_plan_review_clean_text($summary);
            }
        }

        return '';
    }
}

if (!function_exists('vms_event_plan_review_clear_changes')) {
    function vms_event_plan_review_clear_changes(int $plan_id): void
    {
        delete_post_meta($plan_id, vms_event_plan_review_meta_key('changes_json'));
        delete_post_meta($plan_id, vms_event_plan_review_meta_key('changes_at'));
        delete_post_meta($plan_id, vms_event_plan_review_meta_key('changes_by'));
        delete_post_meta($plan_id, vms_event_plan_review_meta_key('changes_source'));
    }
}

if (!function_exists('vms_event_plan_review_mark_published')) {
    function vms_event_plan_review_mark_published(int $plan_id, string $source = 'event_plan_editor', int $user_id = 0): array
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0 || get_post_type($plan_id) !== 'vms_event_plan') {
            return array();
        }

        if ($user_id <= 0) {
            $user_id = get_current_user_id();
        }

        $snapshot = vms_event_plan_review_current_snapshot($plan_id);
        update_post_meta($plan_id, vms_event_plan_review_meta_key('snapshot_json'), wp_json_encode($snapshot));
        update_post_meta($plan_id, vms_event_plan_review_meta_key('snapshot_at'), current_time('mysql'));
        update_post_meta($plan_id, vms_event_plan_review_meta_key('snapshot_by'), absint($user_id));
        vms_event_plan_review_clear_changes($plan_id);

        return $snapshot;
    }
}

if (!function_exists('vms_event_plan_review_touch')) {
    function vms_event_plan_review_touch(int $plan_id, string $source = 'unknown', int $user_id = 0): array
    {
        $plan_id = absint($plan_id);
        if ($plan_id <= 0 || get_post_type($plan_id) !== 'vms_event_plan') {
            return array();
        }

        $snapshot = vms_event_plan_review_get_snapshot($plan_id);
        if (empty($snapshot)) {
            vms_event_plan_review_clear_changes($plan_id);
            return array();
        }

        if ($user_id <= 0) {
            $user_id = get_current_user_id();
        }

        $current = vms_event_plan_review_current_snapshot($plan_id);
        $changes = vms_event_plan_review_build_changes($snapshot, $current);
        if (empty($changes)) {
            vms_event_plan_review_clear_changes($plan_id);
            return array();
        }

        $payload = array(
            'count' => count($changes),
            'changes' => $changes,
        );

        update_post_meta($plan_id, vms_event_plan_review_meta_key('changes_json'), wp_json_encode($payload));
        update_post_meta($plan_id, vms_event_plan_review_meta_key('changes_at'), current_time('mysql'));
        update_post_meta($plan_id, vms_event_plan_review_meta_key('changes_by'), absint($user_id));
        update_post_meta($plan_id, vms_event_plan_review_meta_key('changes_source'), sanitize_key($source));

        return $payload;
    }
}

if (!function_exists('vms_event_plan_review_has_changes')) {
    function vms_event_plan_review_has_changes(int $plan_id): bool
    {
        $changes = vms_event_plan_review_get_changes($plan_id);
        return !empty($changes['count']) && !empty($changes['changes']) && is_array($changes['changes']);
    }
}

if (!function_exists('vms_event_plan_review_render_banner')) {
    function vms_event_plan_review_render_banner(WP_Post $post): void
    {
        if ($post->post_type !== 'vms_event_plan') {
            return;
        }

        $changes = vms_event_plan_review_get_changes((int) $post->ID);
        if (empty($changes['changes']) || !is_array($changes['changes'])) {
            return;
        }

        $count = max(0, (int) ($changes['count'] ?? count($changes['changes'])));
        $changed_at = (string) get_post_meta($post->ID, vms_event_plan_review_meta_key('changes_at'), true);
        $changed_by = absint(get_post_meta($post->ID, vms_event_plan_review_meta_key('changes_by'), true));
        $changed_source = (string) get_post_meta($post->ID, vms_event_plan_review_meta_key('changes_source'), true);
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
            $meta_bits[] = sprintf(__('Updated: %s', 'vms'), $changed_at);
        }
        if ($user_name !== '') {
            /* translators: %s: display name of the user who made the tracked changes. */
            $meta_bits[] = sprintf(__('By: %s', 'vms'), $user_name);
        }
        if ($changed_source !== '') {
            /* translators: %s: source label for the tracked changes. */
            $meta_bits[] = sprintf(__('Source: %s', 'vms'), vms_event_plan_review_source_label($changed_source));
        }

        echo '<div class="notice notice-warning inline">';
        /* translators: %d: number of unpublished tracked changes since the last publish. */
        echo '<p><strong>' . esc_html__('Needs Review', 'vms') . '</strong> ' . esc_html(sprintf(_n('%d unpublished change since last publish.', '%d unpublished changes since last publish.', $count, 'vms'), $count)) . '</p>';
        if (!empty($meta_bits)) {
            echo '<p class="description">' . esc_html(implode(' | ', $meta_bits)) . '</p>';
        }
        echo '<ul class="vms-review-banner-list">';
        foreach (array_slice((array) $changes['changes'], 0, 6) as $change) {
            $summary = trim((string) ($change['summary'] ?? ''));
            if ($summary === '') {
                continue;
            }
            echo '<li>' . esc_html(vms_event_plan_review_clean_text($summary)) . '</li>';
        }
        echo '</ul>';
        echo '<p><em>' . esc_html__('Review the plan, then click Publish Now again to make the current version the new baseline.', 'vms') . '</em></p>';
        echo '</div>';
    }
}
add_action('edit_form_after_title', 'vms_event_plan_review_render_banner', 8);

if (!function_exists('vms_event_plan_review_render_status_note')) {
    function vms_event_plan_review_render_status_note(string $column, int $post_id): void
    {
        if ($column !== 'vms_plan_status' || !vms_event_plan_review_has_changes($post_id)) {
            return;
        }

        $changes = vms_event_plan_review_get_changes($post_id);
        $count = max(0, (int) ($changes['count'] ?? 0));
        $first = vms_event_plan_review_compact_summary((array) ($changes['changes'] ?? array()));

        echo '<div class="description vms-review-status-note"><strong>' . esc_html__('Needs Review', 'vms') . '</strong>';
        if ($count > 0) {
            /* translators: %d: number of tracked changes. */
            echo ' | ' . esc_html(sprintf(_n('%d tracked change', '%d tracked changes', $count, 'vms'), $count));
        }
        echo '</div>';
        if ($first !== '') {
            echo '<div class="description">' . esc_html(vms_event_plan_review_clean_text($first)) . '</div>';
        }
    }
}
add_action('manage_vms_event_plan_posts_custom_column', 'vms_event_plan_review_render_status_note', 20, 2);

if (!function_exists('vms_event_plan_review_after_save')) {
    function vms_event_plan_review_after_save(int $post_id, WP_Post $post): void
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

        $action = isset($_POST['vms_event_plan_action']) ? sanitize_key((string) wp_unslash($_POST['vms_event_plan_action'])) : '';
        if ($action === 'publish_now') {
            vms_event_plan_review_mark_published($post_id, 'event_plan_editor');
            return;
        }

        vms_event_plan_review_touch($post_id, 'event_plan_editor');
    }
}
add_action('save_post_vms_event_plan', 'vms_event_plan_review_after_save', 999, 2);
