<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_dt_vio_open_dates_allowed_statuses')) {
    /**
     * @return string[]
     */
    function vms_dt_vio_open_dates_allowed_statuses(bool $include_tentative): array
    {
        if ($include_tentative) {
            return array('published', 'ready', 'draft', 'tentative', 'confirmed');
        }

        return array('published');
    }
}

if (!function_exists('vms_dt_vio_extract_slot_limit_for_type')) {
    function vms_dt_vio_extract_slot_limit_for_type(int $event_plan_id, int $venue_id, string $vendor_type): int
    {
        $vendor_type = sanitize_key($vendor_type);
        if ($vendor_type === '') {
            return 0;
        }

        $limit = 0;

        if (vms_dt_has_core_function('vms_calendar_get_event_slot_limits')) {
            $map = (array) vms_dt_call_core_function('vms_calendar_get_event_slot_limits', $event_plan_id, $venue_id);
            if (isset($map[$vendor_type]) && is_numeric($map[$vendor_type])) {
                $limit = max(0, (int) $map[$vendor_type]);
            }
        }

        if ($limit > 0) {
            return $limit;
        }

        $k_slot_limits = vms_dt_has_core_function('vms_meta_key') ? (string) vms_dt_call_core_function('vms_meta_key', 'event_plan', 'slot_limits') : '_vms_slot_limits';
        if ($k_slot_limits === '') {
            $k_slot_limits = '_vms_slot_limits';
        }

        $raw = get_post_meta($event_plan_id, $k_slot_limits, true);
        if (!is_array($raw)) {
            return 0;
        }

        if (isset($raw[$vendor_type]) && is_numeric($raw[$vendor_type])) {
            return max(0, (int) $raw[$vendor_type]);
        }

        return 0;
    }
}

if (!function_exists('vms_dt_vio_event_plan_meta_keys')) {
    /**
     * @return array<string,string>
     */
    function vms_dt_vio_event_plan_meta_keys(): array
    {
        $keys = array(
            'date' => vms_dt_has_core_function('vms_meta_key') ? (string) vms_dt_call_core_function('vms_meta_key', 'event_plan', 'date') : '_vms_event_date',
            'venue_id' => vms_dt_has_core_function('vms_meta_key') ? (string) vms_dt_call_core_function('vms_meta_key', 'event_plan', 'venue_id') : '_vms_venue_id',
            'secondary_vendor_ids' => vms_dt_has_core_function('vms_meta_key') ? (string) vms_dt_call_core_function('vms_meta_key', 'event_plan', 'secondary_vendor_ids') : '_vms_secondary_vendor_ids',
            'secondary_vendor_id' => vms_dt_has_core_function('vms_meta_key') ? (string) vms_dt_call_core_function('vms_meta_key', 'event_plan', 'secondary_vendor_id') : '_vms_secondary_vendor_id',
            'secondary_vendor_type' => vms_dt_has_core_function('vms_meta_key') ? (string) vms_dt_call_core_function('vms_meta_key', 'event_plan', 'secondary_vendor_type') : '_vms_secondary_vendor_type',
            'primary_vendor_id' => vms_dt_has_core_function('vms_meta_key') ? (string) vms_dt_call_core_function('vms_meta_key', 'event_plan', 'band_vendor_id') : '_vms_band_vendor_id',
            'status' => vms_dt_has_core_function('vms_meta_key') ? (string) vms_dt_call_core_function('vms_meta_key', 'event_plan', 'status') : '_vms_event_plan_status',
        );

        foreach ($keys as $key => $value) {
            if ($value === '') {
                if ($key === 'date') {
                    $keys[$key] = '_vms_event_date';
                } elseif ($key === 'venue_id') {
                    $keys[$key] = '_vms_venue_id';
                } elseif ($key === 'secondary_vendor_ids') {
                    $keys[$key] = '_vms_secondary_vendor_ids';
                } elseif ($key === 'secondary_vendor_id') {
                    $keys[$key] = '_vms_secondary_vendor_id';
                } elseif ($key === 'secondary_vendor_type') {
                    $keys[$key] = '_vms_secondary_vendor_type';
                } elseif ($key === 'primary_vendor_id') {
                    $keys[$key] = '_vms_band_vendor_id';
                } elseif ($key === 'status') {
                    $keys[$key] = '_vms_event_plan_status';
                }
            }
        }

        return $keys;
    }
}

if (!function_exists('vms_dt_vio_get_event_plan_opportunity_row')) {
    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>|null
     */
    function vms_dt_vio_get_event_plan_opportunity_row(int $event_plan_id, string $vendor_type, array $args = array()): ?array
    {
        $event_plan_id = absint($event_plan_id);
        $vendor_type = sanitize_key($vendor_type);
        if ($event_plan_id <= 0 || $vendor_type === '') {
            return null;
        }

        $include_primary_vendor = !empty($args['include_primary_vendor']);
        $include_tentative = !empty($args['include_tentative']);
        $allowed_statuses = isset($args['allowed_statuses']) && is_array($args['allowed_statuses'])
            ? array_values(array_unique(array_filter(array_map('sanitize_key', (array) $args['allowed_statuses']))))
            : vms_dt_vio_open_dates_allowed_statuses($include_tentative);

        $tz = vms_dt_has_core_function('vms_get_timezone') ? vms_dt_call_core_function('vms_get_timezone') : wp_timezone();
        $today = isset($args['today']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $args['today'])
            ? (string) $args['today']
            : wp_date('Y-m-d', time(), $tz);

        $plan = get_post($event_plan_id);
        if (!$plan || $plan->post_type !== 'vms_event_plan') {
            return null;
        }

        $keys = vms_dt_vio_event_plan_meta_keys();
        $status = vms_dt_has_core_function('vms_event_plan_get_status')
            ? (string) vms_dt_call_core_function('vms_event_plan_get_status', $event_plan_id, 'schedule_admin')
            : sanitize_key((string) get_post_meta($event_plan_id, $keys['status'], true));
        if (vms_dt_has_core_function('vms_event_plan_status_normalize')) {
            $status = (string) vms_dt_call_core_function('vms_event_plan_status_normalize', $status);
        } else {
            $status = sanitize_key($status);
        }

        if ($status === 'cancelled' || !in_array($status, $allowed_statuses, true)) {
            return null;
        }

        $date_ymd = (string) get_post_meta($event_plan_id, $keys['date'], true);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_ymd) || $date_ymd < $today) {
            return null;
        }

        $venue_id = absint(get_post_meta($event_plan_id, $keys['venue_id'], true));

        $secondary_ids = get_post_meta($event_plan_id, $keys['secondary_vendor_ids'], true);
        if (!is_array($secondary_ids)) {
            $secondary_ids = get_post_meta($event_plan_id, $keys['secondary_vendor_id'], false);
        }
        $secondary_ids = array_values(array_unique(array_filter(array_map('absint', (array) $secondary_ids))));
        $filled = count($secondary_ids);

        $limit = vms_dt_vio_extract_slot_limit_for_type($event_plan_id, $venue_id, $vendor_type);
        $slots_remaining = ($limit > 0) ? max(0, $limit - $filled) : (($filled === 0) ? 1 : 0);
        if ($slots_remaining <= 0) {
            return null;
        }

        $ts = strtotime($date_ymd . ' 12:00:00');
        if ($ts === false) {
            return null;
        }

        $event_title = trim((string) get_the_title($event_plan_id));
        if ($event_title === '') {
            $event_title = sprintf(
                /* translators: %d is an event plan id. */
                __('Event Plan #%d', 'vms-data-tools'),
                $event_plan_id
            );
        }

        $item = array(
            'event_plan_id' => $event_plan_id,
            'event_title' => $event_title,
            'date_ymd' => $date_ymd,
            'date_ts' => (int) $ts,
            'status' => $status,
            'status_label' => vms_dt_has_core_function('vms_event_plan_status_label')
                ? (string) vms_dt_call_core_function('vms_event_plan_status_label', $status)
                : ucfirst($status),
            'venue_id' => $venue_id,
            'venue_name' => ($venue_id > 0) ? (string) get_the_title($venue_id) : '',
            'primary_vendor_name' => '',
            'secondary_vendor_type' => sanitize_key((string) get_post_meta($event_plan_id, $keys['secondary_vendor_type'], true)),
            'secondary_vendor_count' => $filled,
            'slot_limit' => $limit,
            'slots_remaining' => $slots_remaining,
        );

        if ($include_primary_vendor) {
            $primary_vendor_id = absint(get_post_meta($event_plan_id, $keys['primary_vendor_id'], true));
            if ($primary_vendor_id > 0) {
                $item['primary_vendor_name'] = (string) get_the_title($primary_vendor_id);
            }
        }

        return $item;
    }
}

if (!function_exists('vms_dt_vio_find_open_dates')) {
    /**
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    function vms_dt_vio_find_open_dates(array $args): array
    {
        $vendor_type = sanitize_key((string) ($args['vendor_type'] ?? ''));
        if ($vendor_type === '') {
            return array();
        }

        $next_n = max(1, min(30, absint($args['next_n'] ?? 8)));
        $lookahead_days = max(1, min(365, absint($args['lookahead_days'] ?? 90)));
        $include_primary_vendor = !empty($args['include_primary_vendor']);
        $include_tentative = !empty($args['include_tentative']);
        $venue_ids = vms_dt_vio_parse_id_list($args['venue_ids'] ?? array());

        $tz = vms_dt_has_core_function('vms_get_timezone') ? vms_dt_call_core_function('vms_get_timezone') : wp_timezone();
        $today = wp_date('Y-m-d', time(), $tz);
        $end_date = wp_date('Y-m-d', time() + ($lookahead_days * DAY_IN_SECONDS), $tz);
        $keys = vms_dt_vio_event_plan_meta_keys();
        // IMPORTANT: Do not filter the event plan query by secondary vendor type meta.
        // In VMS, this meta is typically only written *after* a secondary vendor is assigned,
        // which would incorrectly exclude otherwise-open dates from the preview.
        // Eligibility for a vendor type is handled later via slot limits (if present)
        // and/or whether any secondary vendors are already assigned.

        $meta_query = array(
            'relation' => 'AND',
            array(
                'key' => $keys['date'],
                'value' => $today,
                'compare' => '>=',
                'type' => 'DATE',
            ),
            array(
                'key' => $keys['date'],
                'value' => $end_date,
                'compare' => '<=',
                'type' => 'DATE',
            ),
            // no vendor-type meta filter here
        );

        if (!empty($venue_ids)) {
            $meta_query[] = array(
                'key' => $keys['venue_id'],
                'value' => $venue_ids,
                'compare' => 'IN',
                'type' => 'NUMERIC',
            );
        }

        $query_args = array(
            'post_type' => 'vms_event_plan',
            'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_key' => $keys['date'],
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => $meta_query,
            'no_found_rows' => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
        );

        $ids = get_posts($query_args);
        if (!is_array($ids) || empty($ids)) {
            return array();
        }

        $items = array();
        $allowed_statuses = vms_dt_vio_open_dates_allowed_statuses($include_tentative);

        foreach ($ids as $plan_id_raw) {
            $plan_id = absint($plan_id_raw);
            if ($plan_id <= 0) {
                continue;
            }

            $item = vms_dt_vio_get_event_plan_opportunity_row($plan_id, $vendor_type, array(
                'today' => $today,
                'include_primary_vendor' => $include_primary_vendor,
                'include_tentative' => $include_tentative,
                'allowed_statuses' => $allowed_statuses,
            ));
            if (!is_array($item)) {
                continue;
            }

            $items[] = $item;
            if (count($items) >= $next_n) {
                break;
            }
        }

        return $items;
    }
}

if (!function_exists('vms_dt_vio_render_open_dates_snippet')) {
    /**
     * @param array<int,array<string,mixed>> $open_dates
     */
    function vms_dt_vio_render_open_dates_snippet(array $open_dates, string $lang, array $args = array()): string
    {
        $include_primary_vendor = !empty($args['include_primary_vendor']);
        $include_tentative = !empty($args['include_tentative']);

        return (string) vms_dt_vio_with_language_locale($lang, static function () use ($open_dates, $include_primary_vendor, $include_tentative): string {
            $tz = vms_dt_has_core_function('vms_get_timezone') ? vms_dt_call_core_function('vms_get_timezone') : wp_timezone();
            $date_format = (string) get_option('date_format', 'M j, Y');

            $out = '<p><strong>' . esc_html__('Upcoming open dates', 'vms-data-tools') . '</strong></p>';
            $out .= '<ul>';

            if (empty($open_dates)) {
                $out .= '<li>' . esc_html__('No open dates found in this window.', 'vms-data-tools') . '</li>';
            } else {
                foreach ($open_dates as $item) {
                    $date_ts = absint($item['date_ts'] ?? 0);
                    if ($date_ts <= 0) {
                        continue;
                    }

                    $parts = array();
                    $parts[] = wp_date($date_format, $date_ts, $tz);

                    $venue_name = trim((string) ($item['venue_name'] ?? ''));
                    if ($venue_name !== '') {
                        $parts[] = $venue_name;
                    }

                    $event_title = trim((string) ($item['event_title'] ?? ''));
                    if ($event_title !== '') {
                        $parts[] = $event_title;
                    }

                    if ($include_primary_vendor) {
                        $primary_name = trim((string) ($item['primary_vendor_name'] ?? ''));
                        if ($primary_name !== '') {
                            $parts[] = sprintf(
                                /* translators: %s is a vendor name. */
                                __('Primary: %s', 'vms-data-tools'),
                                $primary_name
                            );
                        }
                    }

                    $status = sanitize_key((string) ($item['status'] ?? ''));
                    if ($include_tentative && $status !== '' && $status !== 'published') {
                        $status_label = trim((string) ($item['status_label'] ?? $status));
                        if ($status_label !== '') {
                            $parts[] = sprintf(
                                /* translators: %s is an event status label. */
                                __('Status: %s', 'vms-data-tools'),
                                $status_label
                            );
                        }
                    }

                    $line = implode(' - ', array_map('wp_strip_all_tags', $parts));
                    $out .= '<li>' . esc_html($line) . '</li>';
                }
            }

            $out .= '</ul>';

            $portal_url = '';
            $portal_page_id = absint(get_option('vms_page_vendor_portal', 0));
            if ($portal_page_id > 0) {
                $portal_url = (string) get_permalink($portal_page_id);
            }
            if ($portal_url === '') {
                $portal_url = home_url('/');
            }

            $out .= '<p><a href="' . esc_url($portal_url) . '">' . esc_html__('View all opportunities', 'vms-data-tools') . '</a></p>';

            return $out;
        });
    }
}
