<?php

defined('ABSPATH') || exit;

if (!function_exists('vms_event_plan_checkin_close_meta_key')) {
    function vms_event_plan_checkin_close_meta_key(): string
    {
        $key = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'checkin_close_at') : '';
        return $key !== '' ? $key : '_checkin_close_at';
    }
}

if (!function_exists('vms_event_plan_parse_local_datetime')) {
    function vms_event_plan_parse_local_datetime(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        try {
            return new DateTimeImmutable($value, $tz);
        } catch (Exception $exception) {
            return null;
        }
    }
}

if (!function_exists('vms_event_plan_start_datetime')) {
    function vms_event_plan_start_datetime(int $post_id): ?DateTimeImmutable
    {
        $stored = vms_event_plan_parse_local_datetime((string) get_post_meta($post_id, '_vms_event_plan_start_datetime', true));
        if ($stored instanceof DateTimeImmutable) {
            return $stored;
        }

        $event_date = trim((string) get_post_meta($post_id, '_vms_event_date', true));
        $start_time = trim((string) get_post_meta($post_id, '_vms_start_time', true));
        if ($event_date === '' || $start_time === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $start_time)) {
            $start_time .= ':00';
        }

        return vms_event_plan_parse_local_datetime($event_date . ' ' . $start_time);
    }
}

if (!function_exists('vms_event_plan_end_datetime')) {
    function vms_event_plan_end_datetime(int $post_id): ?DateTimeImmutable
    {
        $start = vms_event_plan_start_datetime($post_id);
        $stored = vms_event_plan_parse_local_datetime((string) get_post_meta($post_id, '_vms_event_plan_end_datetime', true));
        if ($stored instanceof DateTimeImmutable) {
            if ($start instanceof DateTimeImmutable && $stored <= $start) {
                return null;
            }

            return $stored;
        }

        $event_date = trim((string) get_post_meta($post_id, '_vms_event_date', true));
        $end_time = trim((string) get_post_meta($post_id, '_vms_end_time', true));
        if ($event_date === '' || $end_time === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $end_time)) {
            $end_time .= ':00';
        }

        $end = vms_event_plan_parse_local_datetime($event_date . ' ' . $end_time);
        if (!($end instanceof DateTimeImmutable)) {
            return null;
        }

        if ($start instanceof DateTimeImmutable && $end <= $start) {
            return $end->add(new DateInterval('P1D'));
        }

        return $end;
    }
}

if (!function_exists('vms_event_plan_checkin_close_buffer_hours')) {
    function vms_event_plan_checkin_close_buffer_hours(): int
    {
        if (function_exists('vms_ops_ticket_post_show_scan_buffer_hours')) {
            return max(0, min(12, (int) vms_ops_ticket_post_show_scan_buffer_hours()));
        }

        $hours = 4;
        if (function_exists('vms_ops_default_settings')) {
            $defaults = (array) vms_ops_default_settings();
            $hours = isset($defaults['post_show_scan_buffer_hours']) ? (int) $defaults['post_show_scan_buffer_hours'] : $hours;
        }

        if (function_exists('get_option')) {
            $settings = get_option('vms_ops_settings', array());
            if (is_array($settings) && isset($settings['post_show_scan_buffer_hours'])) {
                $hours = (int) $settings['post_show_scan_buffer_hours'];
            }
        }

        return max(0, min(12, $hours));
    }
}

if (!function_exists('vms_event_plan_apply_checkin_close_buffer')) {
    function vms_event_plan_apply_checkin_close_buffer(DateTimeImmutable $event_end): DateTimeImmutable
    {
        if (function_exists('vms_ops_ticket_apply_post_show_buffer')) {
            return vms_ops_ticket_apply_post_show_buffer($event_end);
        }

        $buffer_hours = vms_event_plan_checkin_close_buffer_hours();
        if ($buffer_hours <= 0) {
            return $event_end;
        }

        return $event_end->add(new DateInterval('PT' . $buffer_hours . 'H'));
    }
}

if (!function_exists('vms_event_plan_resolve_checkin_close_meta')) {
    function vms_event_plan_resolve_checkin_close_meta(int $post_id): array
    {
        $start = vms_event_plan_start_datetime($post_id);
        $end = vms_event_plan_end_datetime($post_id);

        if (!($start instanceof DateTimeImmutable) || !($end instanceof DateTimeImmutable)) {
            return array(
                'datetime' => null,
                'reason' => 'missing_schedule',
                'start_at' => $start,
                'end_at' => $end,
            );
        }

        if ($end <= $start) {
            return array(
                'datetime' => null,
                'reason' => 'invalid_schedule',
                'start_at' => $start,
                'end_at' => $end,
            );
        }

        return array(
            'datetime' => vms_event_plan_apply_checkin_close_buffer($end),
            'reason' => 'stored',
            'start_at' => $start,
            'end_at' => $end,
        );
    }
}

if (!function_exists('vms_event_plan_store_checkin_close_meta')) {
    function vms_event_plan_store_checkin_close_meta(int $post_id): array
    {
        $post_id = absint($post_id);
        $meta_key = vms_event_plan_checkin_close_meta_key();
        $resolved = vms_event_plan_resolve_checkin_close_meta($post_id);
        $datetime = $resolved['datetime'] ?? null;
        $value = $datetime instanceof DateTimeImmutable ? $datetime->format('Y-m-d H:i:s') : '';

        if ($value !== '') {
            update_post_meta($post_id, $meta_key, $value);
        } else {
            delete_post_meta($post_id, $meta_key);
        }

        $resolved['stored'] = $value !== '';
        $resolved['checkin_close_at'] = $value;
        $resolved['meta_key'] = $meta_key; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Check-in result descriptor only; no query is executed here.

        return $resolved;
    }
}

if (!function_exists('vms_event_plan_sync_checkin_close_meta_to_tec')) {
    function vms_event_plan_sync_checkin_close_meta_to_tec(int $post_id, int $tec_event_id): array
    {
        $resolved = vms_event_plan_store_checkin_close_meta($post_id);
        $tec_event_id = absint($tec_event_id);

        if ($tec_event_id > 0) {
            $value = (string) ($resolved['checkin_close_at'] ?? '');
            if ($value !== '') {
                update_post_meta($tec_event_id, '_checkin_close_at', $value);
            } else {
                delete_post_meta($tec_event_id, '_checkin_close_at');
            }
        }

        $resolved['linked_tec_event_id'] = $tec_event_id;
        return $resolved;
    }
}
