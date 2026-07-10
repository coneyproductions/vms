<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_lineup_schedule_meta_key')) {
    function vms_lineup_schedule_meta_key(string $slot, string $fallback): string
    {
        if (function_exists('vms_meta_key')) {
            $resolved = (string) vms_meta_key('event_plan', $slot);
            if ($resolved !== '') {
                return $resolved;
            }
        }

        return $fallback;
    }
}

if (!function_exists('vms_lineup_schedule_sanitize_time')) {
    function vms_lineup_schedule_sanitize_time(string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return '';
        }

        return gmdate('H:i', $ts);
    }
}

if (!function_exists('vms_lineup_schedule_time_to_minutes')) {
    function vms_lineup_schedule_time_to_minutes(string $value): ?int
    {
        $value = vms_lineup_schedule_sanitize_time($value);
        if ($value === '') {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $value));
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return null;
        }

        return ($hour * 60) + $minute;
    }
}

if (!function_exists('vms_lineup_schedule_minutes_to_label')) {
    function vms_lineup_schedule_minutes_to_label(?int $minutes): string
    {
        if ($minutes === null || $minutes < 0) {
            return '';
        }

        $hours = (int) floor($minutes / 60);
        $mins = (int) ($minutes % 60);
        $parts = array();
        if ($hours > 0) {
            /* translators: %d: Number of hours. */
            $parts[] = sprintf(_n('%d hr', '%d hrs', $hours, 'backstage-venue-manager'), $hours);
        }
        if ($mins > 0 || empty($parts)) {
            /* translators: %d: Number of minutes. */
            $parts[] = sprintf(_n('%d min', '%d mins', $mins, 'backstage-venue-manager'), $mins);
        }

        return implode(' ', $parts);
    }
}

if (!function_exists('vms_lineup_schedule_format_time_label')) {
    function vms_lineup_schedule_format_time_label(string $value): string
    {
        $value = vms_lineup_schedule_sanitize_time($value);
        if ($value === '') {
            return '';
        }

        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $dt = DateTimeImmutable::createFromFormat('!H:i', $value, $timezone);
        if (!($dt instanceof DateTimeImmutable)) {
            return $value;
        }

        return strtolower($dt->format('g:i A'));
    }
}

if (!function_exists('vms_lineup_schedule_vendor_exists')) {
    function vms_lineup_schedule_vendor_exists(int $vendor_id): bool
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return false;
        }

        if (function_exists('vms_event_plan_vendor_exists')) {
            return (bool) vms_event_plan_vendor_exists($vendor_id);
        }

        $post = get_post($vendor_id);
        return ($post instanceof WP_Post) && $post->post_type === 'vms_vendor' && $post->post_status !== 'trash';
    }
}


if (!function_exists('vms_get_lineup_supporting_compensation_default')) {
    /**
     * Resolve the default guaranteed fee for a supporting lineup entry.
     *
     * Supporting rows intentionally use the flat/guaranteed portion of the
     * vendor default compensation as a lightweight starting point. Advanced
     * split / attendance-bonus structures remain primary-entry territory.
     *
     * @return array{guaranteed_fee:float|string,structure:string}
     */
    function vms_get_lineup_supporting_compensation_default(int $vendor_id, int $venue_id = 0, string $event_date = ''): array
    {
        $vendor_id = absint($vendor_id);
        $venue_id = absint($venue_id);
        $event_date = trim((string) $event_date);

        if ($vendor_id <= 0 || !function_exists('vms_get_vendor_supporting_default_comp_terms')) {
            return array(
                'guaranteed_fee' => '',
                'structure' => '',
            );
        }

        $terms = (array) vms_get_vendor_supporting_default_comp_terms($vendor_id);
        $structure = sanitize_key((string) ($terms['structure'] ?? ''));

        $guaranteed_fee = '';
        if (array_key_exists('flat_fee_amount', $terms) && $terms['flat_fee_amount'] !== '' && $terms['flat_fee_amount'] !== null && is_numeric($terms['flat_fee_amount'])) {
            $guaranteed_fee = round((float) $terms['flat_fee_amount'], 2);
            if ($guaranteed_fee < 0) {
                $guaranteed_fee = 0.0;
            }
        }

        return array(
            'guaranteed_fee' => $guaranteed_fee,
            'structure' => $structure,
        );
    }
}

if (!function_exists('vms_lineup_schedule_make_row_id')) {
    function vms_lineup_schedule_make_row_id(): string
    {
        return 'lineup_' . wp_generate_password(10, false, false);
    }
}

if (!function_exists('vms_normalize_event_plan_lineup_entries')) {
    /**
     * @param array<int,mixed> $raw_rows
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    function vms_normalize_event_plan_lineup_entries(array $raw_rows, array $context = array()): array
    {
        $legacy_primary_vendor_id = absint($context['legacy_primary_vendor_id'] ?? 0);
        $event_start = vms_lineup_schedule_sanitize_time((string) ($context['event_start'] ?? ''));
        $event_end = vms_lineup_schedule_sanitize_time((string) ($context['event_end'] ?? ''));
        $venue_id = absint($context['venue_id'] ?? 0);
        $event_date = trim((string) ($context['event_date'] ?? ''));

        $rows = array();
        $primary_index = null;

        foreach ($raw_rows as $index => $raw_row) {
            if (!is_array($raw_row)) {
                continue;
            }

            $vendor_id = absint($raw_row['vendor_id'] ?? 0);
            if ($vendor_id > 0 && !vms_lineup_schedule_vendor_exists($vendor_id)) {
                $vendor_id = 0;
            }

            $role = sanitize_key((string) ($raw_row['role'] ?? 'supporting'));
            if (!in_array($role, array('primary', 'supporting'), true)) {
                $role = 'supporting';
            }

            $public_name_override = sanitize_text_field((string) ($raw_row['public_name_override'] ?? ''));
            $show_public = !empty($raw_row['show_public']) ? '1' : '';
            $show_portal = !empty($raw_row['show_portal']) ? '1' : '';
            $set_start = vms_lineup_schedule_sanitize_time((string) ($raw_row['set_start'] ?? ''));
            $set_end = vms_lineup_schedule_sanitize_time((string) ($raw_row['set_end'] ?? ''));
            $guaranteed_fee_raw = trim((string) ($raw_row['guaranteed_fee'] ?? ''));
            $guaranteed_fee_raw = preg_replace('/[^0-9.\-]/', '', $guaranteed_fee_raw);
            $guaranteed_fee = ($guaranteed_fee_raw !== '' && is_numeric($guaranteed_fee_raw)) ? round((float) $guaranteed_fee_raw, 2) : '';
            if ($guaranteed_fee !== '' && $guaranteed_fee < 0) {
                $guaranteed_fee = 0.0;
            }

            if ($role === 'supporting' && $guaranteed_fee === '' && $vendor_id > 0 && function_exists('vms_get_lineup_supporting_compensation_default')) {
                $support_defaults = (array) vms_get_lineup_supporting_compensation_default($vendor_id, $venue_id, $event_date);
                if (array_key_exists('guaranteed_fee', $support_defaults) && $support_defaults['guaranteed_fee'] !== '' && $support_defaults['guaranteed_fee'] !== null && is_numeric($support_defaults['guaranteed_fee'])) {
                    $guaranteed_fee = round((float) $support_defaults['guaranteed_fee'], 2);
                }
            }

            $schedule_notes = sanitize_textarea_field((string) ($raw_row['schedule_notes'] ?? ''));
            $pay_notes = sanitize_textarea_field((string) ($raw_row['pay_notes'] ?? ''));
            $internal_notes = sanitize_textarea_field((string) ($raw_row['internal_notes'] ?? ''));
            $sort_order = array_key_exists('sort_order', $raw_row) ? (int) $raw_row['sort_order'] : (int) $index;
            $row_id = sanitize_key((string) ($raw_row['row_id'] ?? ''));
            if ($row_id === '') {
                $row_id = vms_lineup_schedule_make_row_id();
            }

            $has_content = ($vendor_id > 0)
                || ($set_start !== '')
                || ($set_end !== '')
                || ($public_name_override !== '')
                || ($schedule_notes !== '')
                || ($pay_notes !== '')
                || ($internal_notes !== '')
                || ($guaranteed_fee !== '')
                || $show_public === '1'
                || $show_portal === '1';

            if (!$has_content) {
                continue;
            }

            $rows[] = array(
                'row_id' => $row_id,
                'vendor_id' => $vendor_id,
                'role' => $role,
                'sort_order' => $sort_order,
                'set_start' => $set_start,
                'set_end' => $set_end,
                'public_name_override' => $public_name_override,
                'show_public' => $show_public,
                'show_portal' => $show_portal,
                'guaranteed_fee' => $guaranteed_fee,
                'pay_notes' => $pay_notes,
                'schedule_notes' => $schedule_notes,
                'internal_notes' => $internal_notes,
            );

            if ($role === 'primary' && $primary_index === null) {
                $primary_index = count($rows) - 1;
            }
        }

        usort($rows, static function (array $left, array $right): int {
            $left_sort = (int) ($left['sort_order'] ?? 0);
            $right_sort = (int) ($right['sort_order'] ?? 0);
            if ($left_sort === $right_sort) {
                return strcmp((string) ($left['row_id'] ?? ''), (string) ($right['row_id'] ?? ''));
            }
            return $left_sort <=> $right_sort;
        });

        if (empty($rows) && ($legacy_primary_vendor_id > 0 || $event_start !== '' || $event_end !== '')) {
            $rows[] = array(
                'row_id' => vms_lineup_schedule_make_row_id(),
                'vendor_id' => $legacy_primary_vendor_id,
                'role' => 'primary',
                'sort_order' => 0,
                'set_start' => $event_start,
                'set_end' => $event_end,
                'public_name_override' => '',
                'show_public' => '1',
                'show_portal' => '1',
                'guaranteed_fee' => '',
                'pay_notes' => '',
                'schedule_notes' => '',
                'internal_notes' => '',
            );
        }

        if (!empty($rows)) {
            $primary_index = 0;
            foreach ($rows as $index => $row) {
                if (sanitize_key((string) ($row['role'] ?? '')) === 'primary') {
                    $primary_index = $index;
                    break;
                }
            }

            foreach ($rows as $index => &$row) {
                $row['role'] = ($index === $primary_index) ? 'primary' : 'supporting';
                $row['sort_order'] = $index;
                if ($row['role'] === 'primary') {
                    if ($row['show_public'] === '') {
                        $row['show_public'] = '1';
                    }
                    if ($row['show_portal'] === '') {
                        $row['show_portal'] = '1';
                    }
                }
            }
            unset($row);
        }

        return array_values($rows);
    }
}

if (!function_exists('vms_lineup_schedule_enrich_entries')) {
    /**
     * @param array<int,array<string,mixed>> $entries
     * @return array{entries:array<int,array<string,mixed>>,warnings:array<int,array<string,mixed>>,summary:array<string,mixed>}
     */
    function vms_lineup_schedule_enrich_entries(array $entries, array $context = array()): array
    {
        $event_start = vms_lineup_schedule_sanitize_time((string) ($context['event_start'] ?? ''));
        $event_end = vms_lineup_schedule_sanitize_time((string) ($context['event_end'] ?? ''));
        $event_start_minutes = vms_lineup_schedule_time_to_minutes($event_start);
        $event_end_minutes = vms_lineup_schedule_time_to_minutes($event_end);

        $warnings = array();
        $warning_dedupe = array();
        $push_warning = static function (string $code, string $message, string $row_id = '', string $severity = 'warning') use (&$warnings, &$warning_dedupe): void {
            $key = $code . '|' . $row_id . '|' . $message;
            if (isset($warning_dedupe[$key])) {
                return;
            }
            $warning_dedupe[$key] = true;
            $warnings[] = array(
                'code' => sanitize_key($code),
                'row_id' => sanitize_key($row_id),
                'severity' => sanitize_key($severity) ?: 'warning',
                'message' => sanitize_text_field($message),
            );
        };

        $vendor_seen = array();
        $total_runtime = 0;
        $earliest_start = null;
        $primary_start = '';
        $primary_vendor_id = 0;
        $primary_index = null;

        foreach ($entries as $index => &$entry) {
            $row_id = sanitize_key((string) ($entry['row_id'] ?? ''));
            $vendor_id = absint($entry['vendor_id'] ?? 0);
            $vendor_title = $vendor_id > 0 ? trim((string) get_the_title($vendor_id)) : '';
            $display_name = trim((string) ($entry['public_name_override'] ?? ''));
            if ($display_name === '') {
                $display_name = $vendor_title;
            }
            if ($display_name === '') {
                $display_name = __('Unassigned lineup entry', 'backstage-venue-manager');
            }

            $set_start = vms_lineup_schedule_sanitize_time((string) ($entry['set_start'] ?? ''));
            $set_end = vms_lineup_schedule_sanitize_time((string) ($entry['set_end'] ?? ''));
            $start_minutes = vms_lineup_schedule_time_to_minutes($set_start);
            $end_minutes = vms_lineup_schedule_time_to_minutes($set_end);
            $duration_minutes = null;
            if ($start_minutes !== null && $end_minutes !== null) {
                $duration_minutes = $end_minutes - $start_minutes;
            }

            if ($vendor_id > 0) {
                if (isset($vendor_seen[$vendor_id])) {
                    $push_warning(
                        'duplicate_vendor',
                        /* translators: %s: Lineup entry display name. */
                        sprintf(__('The same lineup vendor is assigned more than once: %s.', 'backstage-venue-manager'), $display_name),
                        $row_id
                    );
                }
                $vendor_seen[$vendor_id] = true;
            }

            if (($set_start === '') xor ($set_end === '')) {
                $push_warning(
                    'missing_time',
                    /* translators: %s: Lineup entry display name. */
                    sprintf(__('Set time is incomplete for %s.', 'backstage-venue-manager'), $display_name),
                    $row_id
                );
            }

            if ($start_minutes !== null && $end_minutes !== null) {
                if ($duration_minutes !== null && $duration_minutes <= 0) {
                    $push_warning(
                        'end_before_start',
                        /* translators: %s: Lineup entry display name. */
                        sprintf(__('Set end is not after set start for %s.', 'backstage-venue-manager'), $display_name),
                        $row_id,
                        'error'
                    );
                } else {
                    $total_runtime += (int) $duration_minutes;
                    if ($earliest_start === null || $start_minutes < $earliest_start) {
                        $earliest_start = $start_minutes;
                    }
                    if ($duration_minutes < 20) {
                        $push_warning(
                            'short_duration',
                            /* translators: %s: Lineup entry display name. */
                            sprintf(__('The set for %s looks unusually short.', 'backstage-venue-manager'), $display_name),
                            $row_id
                        );
                    } elseif ($duration_minutes > 240) {
                        $push_warning(
                            'long_duration',
                            /* translators: %s: Lineup entry display name. */
                            sprintf(__('The set for %s looks unusually long.', 'backstage-venue-manager'), $display_name),
                            $row_id
                        );
                    }
                }
            }

            if ($start_minutes !== null && $event_start_minutes !== null && $start_minutes < $event_start_minutes) {
                $push_warning(
                    'outside_event_bounds',
                    /* translators: %s: Lineup entry display name. */
                    sprintf(__('The lineup starts before the event start for %s.', 'backstage-venue-manager'), $display_name),
                    $row_id
                );
            }
            if ($end_minutes !== null && $event_end_minutes !== null && $end_minutes > $event_end_minutes) {
                $push_warning(
                    'outside_event_bounds',
                    /* translators: %s: Lineup entry display name. */
                    sprintf(__('The lineup ends after the event end for %s.', 'backstage-venue-manager'), $display_name),
                    $row_id
                );
            }

            $entry['vendor_title'] = $vendor_title;
            $entry['display_name'] = $display_name;
            $entry['set_start'] = $set_start;
            $entry['set_end'] = $set_end;
            $entry['set_start_label'] = vms_lineup_schedule_format_time_label($set_start);
            $entry['set_end_label'] = vms_lineup_schedule_format_time_label($set_end);
            $entry['start_minutes'] = $start_minutes;
            $entry['end_minutes'] = $end_minutes;
            $entry['duration_minutes'] = ($duration_minutes !== null && $duration_minutes > 0) ? (int) $duration_minutes : null;
            $entry['duration_label'] = ($duration_minutes !== null && $duration_minutes > 0) ? vms_lineup_schedule_minutes_to_label((int) $duration_minutes) : '';
            $entry['downtime_before_minutes'] = null;
            $entry['downtime_before_label'] = '';
            $entry['warning_count'] = 0;

            if (($entry['role'] ?? '') === 'primary') {
                $primary_index = $index;
                $primary_vendor_id = $vendor_id;
                $primary_start = $entry['set_start_label'];
            }
        }
        unset($entry);

        if ($primary_index !== null && count($entries) > 1 && $primary_index !== (count($entries) - 1)) {
            $primary_entry = $entries[$primary_index];
            $push_warning(
                'primary_not_last',
                /* translators: %s: Primary lineup entry display name. */
                sprintf(__('The primary lineup entry is not scheduled last: %s.', 'backstage-venue-manager'), (string) ($primary_entry['display_name'] ?? __('Primary', 'backstage-venue-manager'))),
                sanitize_key((string) ($primary_entry['row_id'] ?? ''))
            );
        }

        $last_end_minutes = null;
        $last_label = '';
        foreach ($entries as $index => &$entry) {
            $row_id = sanitize_key((string) ($entry['row_id'] ?? ''));
            $start_minutes = $entry['start_minutes'];
            $end_minutes = $entry['end_minutes'];
            $display_name = (string) ($entry['display_name'] ?? __('Lineup entry', 'backstage-venue-manager'));

            if ($index > 0 && $start_minutes !== null && $last_end_minutes !== null) {
                $delta = $start_minutes - $last_end_minutes;
                if ($delta < 0) {
                    $push_warning(
                        'overlap',
                        /* translators: 1: Previous lineup entry display name, 2: Current lineup entry display name. */
                        sprintf(__('There is a schedule overlap between %1$s and %2$s.', 'backstage-venue-manager'), $last_label, $display_name),
                        $row_id,
                        'error'
                    );
                } else {
                    $entry['downtime_before_minutes'] = $delta;
                    $entry['downtime_before_label'] = vms_lineup_schedule_minutes_to_label($delta);
                    if ($delta >= 45) {
                        $push_warning(
                            'large_gap',
                            /* translators: %s: Lineup entry display name. */
                            sprintf(__('There is a large gap before %s.', 'backstage-venue-manager'), $display_name),
                            $row_id
                        );
                    }
                }
            }

            if ($end_minutes !== null) {
                $last_end_minutes = $end_minutes;
                $last_label = $display_name;
            }
        }
        unset($entry);

        foreach ($warnings as $warning) {
            $row_id = sanitize_key((string) ($warning['row_id'] ?? ''));
            if ($row_id === '') {
                continue;
            }
            foreach ($entries as &$entry) {
                if (sanitize_key((string) ($entry['row_id'] ?? '')) === $row_id) {
                    $entry['warning_count'] = (int) ($entry['warning_count'] ?? 0) + 1;
                    break;
                }
            }
            unset($entry);
        }

        $summary = array(
            'primary_vendor_id' => $primary_vendor_id,
            'primary_vendor_label' => $primary_vendor_id > 0 ? trim((string) get_the_title($primary_vendor_id)) : __('Unassigned primary vendor', 'backstage-venue-manager'),
            'supporting_count' => max(0, count($entries) - 1),
            'earliest_start' => $earliest_start !== null ? vms_lineup_schedule_format_time_label(sprintf('%02d:%02d', (int) floor($earliest_start / 60), (int) ($earliest_start % 60))) : '',
            'primary_start' => $primary_start,
            'total_runtime_minutes' => $total_runtime,
            'total_runtime_label' => vms_lineup_schedule_minutes_to_label($total_runtime),
            'warning_count' => count($warnings),
            'entry_count' => count($entries),
        );

        return array(
            'entries' => array_values($entries),
            'warnings' => array_values($warnings),
            'summary' => $summary,
        );
    }
}

if (!function_exists('vms_get_event_plan_lineup_entries')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function vms_get_event_plan_lineup_entries(int $post_id, array $context = array()): array
    {
        $post_id = absint($post_id);
        if ($post_id <= 0) {
            return array();
        }

        $lineup_key = vms_lineup_schedule_meta_key('lineup_entries_v1', '_vms_lineup_entries_v1');
        $band_key = vms_lineup_schedule_meta_key('band_vendor_id', '_vms_band_vendor_id');
        $raw = get_post_meta($post_id, $lineup_key, true);
        if (!is_array($raw)) {
            $raw = array();
        }

        $context['legacy_primary_vendor_id'] = absint($context['legacy_primary_vendor_id'] ?? get_post_meta($post_id, $band_key, true));
        $context['event_start'] = (string) ($context['event_start'] ?? get_post_meta($post_id, '_vms_start_time', true));
        $context['event_end'] = (string) ($context['event_end'] ?? get_post_meta($post_id, '_vms_end_time', true));
        $context['venue_id'] = absint($context['venue_id'] ?? get_post_meta($post_id, '_vms_venue_id', true));
        $context['event_date'] = (string) ($context['event_date'] ?? get_post_meta($post_id, '_vms_event_date', true));

        $normalized = vms_normalize_event_plan_lineup_entries((array) $raw, $context);
        $enriched = vms_lineup_schedule_enrich_entries($normalized, $context);

        return (array) ($enriched['entries'] ?? array());
    }
}

if (!function_exists('vms_get_event_plan_lineup_primary_entry')) {
    function vms_get_event_plan_lineup_primary_entry(int $post_id, array $context = array()): array
    {
        $entries = vms_get_event_plan_lineup_entries($post_id, $context);
        foreach ($entries as $entry) {
            if (sanitize_key((string) ($entry['role'] ?? '')) === 'primary') {
                return $entry;
            }
        }
        return array();
    }
}

if (!function_exists('vms_get_event_plan_lineup_supporting_entries')) {
    function vms_get_event_plan_lineup_supporting_entries(int $post_id, array $context = array()): array
    {
        $entries = vms_get_event_plan_lineup_entries($post_id, $context);
        return array_values(array_filter($entries, static function (array $entry): bool {
            return sanitize_key((string) ($entry['role'] ?? '')) === 'supporting';
        }));
    }
}

if (!function_exists('vms_get_event_plan_lineup_vendor_ids')) {
    function vms_get_event_plan_lineup_vendor_ids(int $post_id, array $context = array()): array
    {
        $ids = array();
        foreach (vms_get_event_plan_lineup_entries($post_id, $context) as $entry) {
            $vendor_id = absint($entry['vendor_id'] ?? 0);
            if ($vendor_id > 0 && !in_array($vendor_id, $ids, true)) {
                $ids[] = $vendor_id;
            }
        }
        return $ids;
    }
}

if (!function_exists('vms_get_event_plan_lineup_supporting_guaranteed_total')) {
    function vms_get_event_plan_lineup_supporting_guaranteed_total(int $post_id, array $context = array()): float
    {
        $total = 0.0;
        foreach (vms_get_event_plan_lineup_supporting_entries($post_id, $context) as $entry) {
            $fee = $entry['guaranteed_fee'] ?? '';
            if ($fee !== '' && $fee !== null && is_numeric($fee)) {
                $total += max(0.0, (float) $fee);
            }
        }
        return round($total, 2);
    }
}

if (!function_exists('vms_get_event_plan_lineup_summary')) {
    function vms_get_event_plan_lineup_summary(int $post_id, array $context = array()): array
    {
        $entries = vms_get_event_plan_lineup_entries($post_id, $context);
        $enriched = vms_lineup_schedule_enrich_entries($entries, $context);
        return (array) ($enriched['summary'] ?? array());
    }
}

if (!function_exists('vms_get_event_plan_lineup_warnings')) {
    function vms_get_event_plan_lineup_warnings(int $post_id, array $context = array()): array
    {
        $entries = vms_get_event_plan_lineup_entries($post_id, $context);
        $enriched = vms_lineup_schedule_enrich_entries($entries, $context);
        return (array) ($enriched['warnings'] ?? array());
    }
}

if (!function_exists('vms_rebuild_event_plan_lineup_indexes')) {
    function vms_rebuild_event_plan_lineup_indexes(int $post_id, array $entries = array()): void
    {
        $post_id = absint($post_id);
        if ($post_id <= 0) {
            return;
        }

        if (empty($entries)) {
            $entries = vms_get_event_plan_lineup_entries($post_id);
        }

        $index_key = vms_lineup_schedule_meta_key('lineup_entry_vendor_id', '_vms_lineup_entry_vendor_id');

        $seen = array();
        $next_vendor_ids = array();
        foreach ($entries as $entry) {
            $vendor_id = absint($entry['vendor_id'] ?? 0);
            if ($vendor_id <= 0 || isset($seen[$vendor_id])) {
                continue;
            }
            $next_vendor_ids[] = $vendor_id;
            $seen[$vendor_id] = true;
        }
        sort($next_vendor_ids, SORT_NUMERIC);

        $current_vendor_ids = array_values(array_unique(array_filter(array_map('absint', get_post_meta($post_id, $index_key, false)))));
        sort($current_vendor_ids, SORT_NUMERIC);

        if ($current_vendor_ids === $next_vendor_ids) {
            return;
        }

        delete_post_meta($post_id, $index_key);
        foreach ($next_vendor_ids as $vendor_id) {
            add_post_meta($post_id, $index_key, $vendor_id, false);
        }
    }
}

if (!function_exists('vms_sync_event_plan_lineup_legacy_primary')) {
    function vms_sync_event_plan_lineup_legacy_primary(int $post_id, array $entries = array()): int
    {
        $post_id = absint($post_id);
        if ($post_id <= 0) {
            return 0;
        }

        if (empty($entries)) {
            $entries = vms_get_event_plan_lineup_entries($post_id);
        }

        $primary_vendor_id = 0;
        $primary_entry_id = '';
        foreach ($entries as $entry) {
            if (sanitize_key((string) ($entry['role'] ?? '')) !== 'primary') {
                continue;
            }
            $primary_vendor_id = absint($entry['vendor_id'] ?? 0);
            $primary_entry_id = sanitize_key((string) ($entry['row_id'] ?? ''));
            break;
        }

        $band_key = vms_lineup_schedule_meta_key('band_vendor_id', '_vms_band_vendor_id');
        $primary_entry_key = vms_lineup_schedule_meta_key('lineup_primary_entry_id', '_vms_lineup_primary_entry_id');

        update_post_meta($post_id, $band_key, $primary_vendor_id);
        if ($primary_entry_id !== '') {
            update_post_meta($post_id, $primary_entry_key, $primary_entry_id);
        } else {
            delete_post_meta($post_id, $primary_entry_key);
        }

        return $primary_vendor_id;
    }
}

if (!function_exists('vms_save_event_plan_lineup_entries')) {
    /**
     * @param array<int,mixed> $posted_rows
     * @return array{entries:array<int,array<string,mixed>>,warnings:array<int,array<string,mixed>>,summary:array<string,mixed>,primary_vendor_id:int}
     */
    function vms_save_event_plan_lineup_entries(int $post_id, array $posted_rows, array $context = array()): array
    {
        $post_id = absint($post_id);
        if ($post_id <= 0) {
            return array(
                'entries' => array(),
                'warnings' => array(),
                'summary' => array(),
                'primary_vendor_id' => 0,
            );
        }

        $lineup_key = vms_lineup_schedule_meta_key('lineup_entries_v1', '_vms_lineup_entries_v1');
        $context['legacy_primary_vendor_id'] = absint($context['legacy_primary_vendor_id'] ?? get_post_meta($post_id, vms_lineup_schedule_meta_key('band_vendor_id', '_vms_band_vendor_id'), true));
        $context['event_start'] = (string) ($context['event_start'] ?? get_post_meta($post_id, '_vms_start_time', true));
        $context['event_end'] = (string) ($context['event_end'] ?? get_post_meta($post_id, '_vms_end_time', true));
        $context['venue_id'] = absint($context['venue_id'] ?? get_post_meta($post_id, '_vms_venue_id', true));
        $context['event_date'] = (string) ($context['event_date'] ?? get_post_meta($post_id, '_vms_event_date', true));

        $normalized = vms_normalize_event_plan_lineup_entries($posted_rows, $context);

        if (!empty($normalized)) {
            update_post_meta($post_id, $lineup_key, $normalized);
        } else {
            delete_post_meta($post_id, $lineup_key);
        }

        $enriched = vms_lineup_schedule_enrich_entries($normalized, $context);
        $entries = (array) ($enriched['entries'] ?? array());
        $primary_vendor_id = vms_sync_event_plan_lineup_legacy_primary($post_id, $entries);
        vms_rebuild_event_plan_lineup_indexes($post_id, $entries);

        return array(
            'entries' => $entries,
            'warnings' => (array) ($enriched['warnings'] ?? array()),
            'summary' => (array) ($enriched['summary'] ?? array()),
            'primary_vendor_id' => $primary_vendor_id,
        );
    }
}
