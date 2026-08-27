<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('vms_vendor_availability_page_slug')) {
    function vms_vendor_availability_page_slug(): string
    {
        return 'vms-vendor-availability';
    }
}

if (!function_exists('vms_vendor_availability_is_valid_ym')) {
    function vms_vendor_availability_is_valid_ym(string $ym): bool
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            return false;
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $ym . '-01', wp_timezone());
        return ($dt instanceof DateTimeImmutable) && $dt->format('Y-m') === $ym;
    }
}

if (!function_exists('vms_vendor_availability_is_valid_ymd')) {
    function vms_vendor_availability_is_valid_ymd(string $ymd): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
            return false;
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $ymd, wp_timezone());
        return ($dt instanceof DateTimeImmutable) && $dt->format('Y-m-d') === $ymd;
    }
}

if (!function_exists('vms_vendor_availability_normalize_month')) {
    function vms_vendor_availability_normalize_month(string $raw = ''): string
    {
        $raw = trim($raw);
        if (vms_vendor_availability_is_valid_ym($raw)) {
            return $raw;
        }
        return wp_date('Y-m', time(), wp_timezone());
    }
}

if (!function_exists('vms_vendor_availability_normalize_date')) {
    function vms_vendor_availability_normalize_date(string $raw = '', string $fallback_month = ''): string
    {
        $raw = trim($raw);
        if (vms_vendor_availability_is_valid_ymd($raw)) {
            return $raw;
        }

        $fallback_month = vms_vendor_availability_normalize_month($fallback_month);
        $today = wp_date('Y-m-d', time(), wp_timezone());
        if (strpos($today, $fallback_month . '-') === 0) {
            return $today;
        }
        return $fallback_month . '-01';
    }
}

if (!function_exists('vms_vendor_availability_status_options')) {
    function vms_vendor_availability_status_options(): array
    {
        return array(
            'all' => __('All statuses', 'backstage-venue-manager'),
            'available' => __('Available', 'backstage-venue-manager'),
            'tentative' => __('Tentative', 'backstage-venue-manager'),
            'no-response' => __('No reply', 'backstage-venue-manager'),
            'booked' => __('Blocked', 'backstage-venue-manager'),
            'unavailable' => __('Unavailable', 'backstage-venue-manager'),
        );
    }
}

if (!function_exists('vms_vendor_availability_day_filter_options')) {
    function vms_vendor_availability_day_filter_options(): array
    {
        return array(
            'all' => __('All days', 'backstage-venue-manager'),
            'weekdays' => __('Weekdays only', 'backstage-venue-manager'),
            'weekends' => __('Weekends only', 'backstage-venue-manager'),
            'venue_open' => __('Venue open days only', 'backstage-venue-manager'),
        );
    }
}

if (!function_exists('vms_vendor_availability_setup_options')) {
    function vms_vendor_availability_setup_options(): array
    {
        return array(
            'all' => __('Any setup state', 'backstage-venue-manager'),
            'configured' => __('Has availability setup', 'backstage-venue-manager'),
            'missing' => __('Needs availability setup', 'backstage-venue-manager'),
        );
    }
}

if (!function_exists('vms_vendor_availability_roster_options')) {
    function vms_vendor_availability_roster_options(): array
    {
        return array(
            'published' => __('Published only', 'backstage-venue-manager'),
            'all' => __('All vendor records', 'backstage-venue-manager'),
        );
    }
}

if (!function_exists('vms_vendor_availability_view_options')) {
    function vms_vendor_availability_view_options(): array
    {
        return array(
            'month' => __('Month view', 'backstage-venue-manager'),
            'list' => __('List view', 'backstage-venue-manager'),
        );
    }
}

if (!function_exists('vms_vendor_availability_query_arg')) {
    function vms_vendor_availability_query_arg(string $key): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin availability filters only change display state.
        if (!isset($_GET[$key])) {
            return '';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only admin availability filters are sanitized and allowlisted by the caller.
        return (string) wp_unslash($_GET[$key]);
    }
}

if (!function_exists('vms_vendor_availability_selected_filters')) {
    function vms_vendor_availability_selected_filters(): array
    {
        $month = vms_vendor_availability_normalize_month(vms_vendor_availability_query_arg('month'));
        $date = vms_vendor_availability_normalize_date(vms_vendor_availability_query_arg('date'), $month);
        $view = sanitize_key(vms_vendor_availability_query_arg('view'));
        if ($view === '') {
            $view = 'month';
        }
        if (!array_key_exists($view, vms_vendor_availability_view_options())) {
            $view = 'month';
        }

        $status = sanitize_key(vms_vendor_availability_query_arg('availability_status'));
        if ($status === '') {
            $status = 'all';
        }
        if ($status === 'blocked') {
            $status = 'booked';
        }
        if (!array_key_exists($status, vms_vendor_availability_status_options())) {
            $status = 'all';
        }

        $day_filter = sanitize_key(vms_vendor_availability_query_arg('day_filter'));
        if ($day_filter === '') {
            $day_filter = 'all';
        }
        if (!array_key_exists($day_filter, vms_vendor_availability_day_filter_options())) {
            $day_filter = 'all';
        }

        $setup = sanitize_key(vms_vendor_availability_query_arg('availability_setup'));
        if ($setup === '') {
            $setup = 'all';
        }
        if (!array_key_exists($setup, vms_vendor_availability_setup_options())) {
            $setup = 'all';
        }

        $roster = sanitize_key(vms_vendor_availability_query_arg('roster'));
        if ($roster === '') {
            $roster = 'published';
        }
        if (!array_key_exists($roster, vms_vendor_availability_roster_options())) {
            $roster = 'published';
        }

        return array(
            'page' => vms_vendor_availability_page_slug(),
            'view' => $view,
            'month' => $month,
            'date' => $date,
            'q' => sanitize_text_field(vms_vendor_availability_query_arg('q')),
            'type' => sanitize_key(vms_vendor_availability_query_arg('vendor_type')),
            'status' => $status,
            'day_filter' => $day_filter,
            'venue_id' => absint(vms_vendor_availability_query_arg('venue_id')),
            'setup' => $setup,
            'roster' => $roster,
        );
    }
}

if (!function_exists('vms_vendor_availability_home_venue_id')) {
    function vms_vendor_availability_home_venue_id(int $vendor_id): int
    {
        if (function_exists('vms_vendor_guess_venue_id')) {
            return (int) vms_vendor_guess_venue_id($vendor_id);
        }

        $keys = array('_vms_home_venue_id', '_vms_primary_venue_id', '_vms_venue_id', 'venue_id');
        foreach ($keys as $key) {
            $value = absint(get_post_meta($vendor_id, $key, true));
            if ($value > 0) {
                return $value;
            }
        }

        return 0;
    }
}

if (!function_exists('vms_vendor_availability_venue_options')) {
    function vms_vendor_availability_venue_options(): array
    {
        $posts = get_posts(array(
            'post_type' => 'vms_venue',
            'post_status' => array('publish', 'draft', 'private', 'pending'),
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
        ));

        $options = array();
        foreach ($posts as $post) {
            if (!($post instanceof WP_Post)) {
                continue;
            }
            $options[(int) $post->ID] = (string) $post->post_title;
        }
        return $options;
    }
}

if (!function_exists('vms_vendor_availability_type_terms')) {
    function vms_vendor_availability_type_terms(int $vendor_id): array
    {
        $terms = get_the_terms($vendor_id, 'vms_vendor_type');
        if (is_wp_error($terms) || empty($terms)) {
            return array();
        }

        $out = array();
        foreach ($terms as $term) {
            if (!($term instanceof WP_Term)) {
                continue;
            }
            $out[] = array(
                'slug' => sanitize_key((string) $term->slug),
                'name' => (string) $term->name,
            );
        }

        return $out;
    }
}

if (!function_exists('vms_vendor_availability_days_in_month')) {
    function vms_vendor_availability_days_in_month(string $month): int
    {
        $month = vms_vendor_availability_normalize_month($month);
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $month . '-01', wp_timezone());
        if ($dt instanceof DateTimeImmutable) {
            return max(28, (int) $dt->format('t'));
        }

        return 31;
    }
}

if (!function_exists('vms_vendor_availability_day_of_week')) {
    function vms_vendor_availability_day_of_week(string $date): int
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date, wp_timezone());
        if ($dt instanceof DateTimeImmutable) {
            return (int) $dt->format('w');
        }

        return 0;
    }
}

if (!function_exists('vms_vendor_availability_type_options')) {
    function vms_vendor_availability_type_options(): array
    {
        $terms = get_terms(array(
            'taxonomy' => 'vms_vendor_type',
            'hide_empty' => false,
        ));
        if (is_wp_error($terms) || empty($terms)) {
            return array();
        }

        $options = array();
        foreach ($terms as $term) {
            if (!($term instanceof WP_Term)) {
                continue;
            }
            $options[sanitize_key((string) $term->slug)] = (string) $term->name;
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);
        return $options;
    }
}

if (!function_exists('vms_vendor_availability_setup_summary')) {
    function vms_vendor_availability_setup_summary(int $vendor_id): array
    {
        $manual = function_exists('vms_vendor_normalize_manual_availability')
            ? vms_vendor_normalize_manual_availability($vendor_id)
            : array();
        $pattern_enabled = (int) get_post_meta($vendor_id, '_vms_pattern_enabled', true);
        $pattern_days = function_exists('vms_vendor_normalize_pattern_days')
            ? vms_vendor_normalize_pattern_days($vendor_id)
            : array();
        $ics_unavailable = function_exists('vms_vendor_normalize_ics_unavailable')
            ? vms_vendor_normalize_ics_unavailable($vendor_id)
            : array();
        $has_setup = function_exists('vms_vendor_has_availability_setup')
            ? (bool) vms_vendor_has_availability_setup($vendor_id)
            : (!empty($manual) || (!empty($pattern_enabled) && !empty($pattern_days)) || !empty($ics_unavailable));

        $parts = array();
        if (!empty($manual)) {
            /* translators: %d: number of manual availability dates. */
            $parts[] = sprintf(_n('%d manual date', '%d manual dates', count($manual), 'backstage-venue-manager'), count($manual));
        }
        if ($pattern_enabled && !empty($pattern_days)) {
            /* translators: %d: number of recurring pattern days. */
            $parts[] = sprintf(_n('%d pattern day', '%d pattern days', count($pattern_days), 'backstage-venue-manager'), count($pattern_days));
        }
        if (!empty($ics_unavailable)) {
            /* translators: %d: number of ICS availability blocks. */
            $parts[] = sprintf(_n('%d ICS block', '%d ICS blocks', count($ics_unavailable), 'backstage-venue-manager'), count($ics_unavailable));
        }

        return array(
            'has_setup' => $has_setup,
            'label' => $has_setup
                ? (!empty($parts) ? implode(' · ', $parts) : __('Availability configured', 'backstage-venue-manager'))
                : __('No availability setup yet', 'backstage-venue-manager'),
            'tone' => $has_setup ? 'success' : 'warning',
        );
    }
}

if (!function_exists('vms_vendor_availability_collect_vendors')) {
    function vms_vendor_availability_collect_vendors(): array
    {
        $vendor_ids = get_posts(array(
            'post_type' => defined('BVMGR_VENDOR_CPT') ? BVMGR_VENDOR_CPT : 'vms_vendor',
            'post_status' => array('publish', 'draft', 'private', 'pending'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
        ));

        $venue_options = vms_vendor_availability_venue_options();
        $rows = array();
        foreach ((array) $vendor_ids as $vendor_id) {
            $vendor_id = absint($vendor_id);
            if ($vendor_id <= 0) {
                continue;
            }

            $title = (string) get_the_title($vendor_id);
            $types = vms_vendor_availability_type_terms($vendor_id);
            $type_names = array();
            $type_slugs = array();
            foreach ($types as $type) {
                if (!empty($type['name'])) {
                    $type_names[] = (string) $type['name'];
                }
                if (!empty($type['slug'])) {
                    $type_slugs[] = (string) $type['slug'];
                }
            }
            $home_venue_id = vms_vendor_availability_home_venue_id($vendor_id);
            $setup = vms_vendor_availability_setup_summary($vendor_id);

            $rows[] = array(
                'vendor_id' => $vendor_id,
                'title' => $title,
                'edit_link' => get_edit_post_link($vendor_id, ''),
                'post_status' => (string) get_post_status($vendor_id),
                'types' => $type_names,
                'type_slugs' => $type_slugs,
                'home_venue_id' => $home_venue_id,
                'home_venue_label' => ($home_venue_id > 0 && isset($venue_options[$home_venue_id])) ? (string) $venue_options[$home_venue_id] : '',
                'setup' => $setup,
            );
        }

        return $rows;
    }
}

if (!function_exists('vms_vendor_availability_filter_vendors')) {
    function vms_vendor_availability_filter_vendors(array $vendors, array $filters): array
    {
        $q = strtolower(trim((string) ($filters['q'] ?? '')));
        $type = sanitize_key((string) ($filters['type'] ?? ''));
        $venue_id = absint($filters['venue_id'] ?? 0);
        $setup = sanitize_key((string) ($filters['setup'] ?? 'all'));
        $roster = sanitize_key((string) ($filters['roster'] ?? 'published'));

        $out = array();
        foreach ($vendors as $vendor) {
            $vendor_status = sanitize_key((string) ($vendor['post_status'] ?? ''));
            if ($roster !== 'all' && $vendor_status !== 'publish') {
                continue;
            }

            if ($type !== '') {
                $slugs = array_values(array_filter(array_map('sanitize_key', (array) ($vendor['type_slugs'] ?? array()))));
                if ($type === 'uncategorized') {
                    if (!empty($slugs)) {
                        continue;
                    }
                } elseif (!in_array($type, $slugs, true)) {
                    continue;
                }
            }

            if ($venue_id > 0 && (int) ($vendor['home_venue_id'] ?? 0) !== $venue_id) {
                continue;
            }

            $has_setup = !empty($vendor['setup']['has_setup']);
            if ($setup === 'configured' && !$has_setup) {
                continue;
            }
            if ($setup === 'missing' && $has_setup) {
                continue;
            }

            if ($q !== '') {
                $haystack = strtolower(trim(implode(' ', array_filter(array(
                    (string) ($vendor['title'] ?? ''),
                    implode(' ', (array) ($vendor['types'] ?? array())),
                    (string) ($vendor['home_venue_label'] ?? ''),
                )))));
                if (strpos($haystack, $q) === false) {
                    continue;
                }
            }

            $out[] = $vendor;
        }

        usort($out, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        });

        return $out;
    }
}

if (!function_exists('vms_vendor_availability_busy_map')) {
    /**
     * @return array<string,array<int,array<string,mixed>>>
     */
    function vms_vendor_availability_busy_map(string $start_date, string $end_date): array
    {
        if (!function_exists('bvmgr_get_calendar_events')) {
            return array();
        }

        $events = (array) bvmgr_get_calendar_events(array(
            'start_date' => $start_date,
            'end_date' => $end_date,
            'context' => 'admin',
            'include_past' => true,
            'include_statuses' => array('draft', 'ready', 'published', 'tentative', 'confirmed'),
        ));

        $out = array();
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $date = isset($event['date_key']) ? (string) $event['date_key'] : '';
            if (!vms_vendor_availability_is_valid_ymd($date)) {
                continue;
            }

            $plan_status = isset($event['plan_status']) ? (string) $event['plan_status'] : '';
            $busy_status = function_exists('bvmgr_calendar_assignment_status_for_plan')
                ? (string) (bvmgr_calendar_assignment_status_for_plan($plan_status) ?? '')
                : '';
            if ($busy_status !== 'booked' && $busy_status !== 'tentative') {
                continue;
            }

            $plan_id = absint($event['event_plan_id'] ?? 0);
            $venue_id = absint($event['venue_id'] ?? 0);
            $venue_label = '';
            if ($venue_id > 0) {
                $venue_label = (string) get_the_title($venue_id);
            }
            $title = trim((string) ($event['title'] ?? ''));
            if ($title === '') {
                $title = __('(Event)', 'backstage-venue-manager');
            }

            $time_label = '';
            $start_local = isset($event['start_local']) ? (string) $event['start_local'] : '';
            if ($start_local !== '') {
                try {
                    $time_dt = new DateTimeImmutable($start_local);
                    $time_label = $time_dt->format('g:ia');
                } catch (Exception $e) {
                    $time_label = '';
                }
            }

            $detail = $title;
            if ($time_label !== '') {
                $detail .= ' @ ' . $time_label;
            }
            if ($venue_label !== '') {
                $detail .= ' · ' . $venue_label;
            }

            $groups = isset($event['vendor_groups']) && is_array($event['vendor_groups']) ? $event['vendor_groups'] : array();
            foreach ($groups as $group) {
                if (!is_array($group)) {
                    continue;
                }
                $vendors = isset($group['vendors']) && is_array($group['vendors']) ? $group['vendors'] : array();
                foreach ($vendors as $vendor_row) {
                    if (!is_array($vendor_row)) {
                        continue;
                    }
                    $vendor_id = absint($vendor_row['vendor_id'] ?? 0);
                    if ($vendor_id <= 0) {
                        continue;
                    }

                    if (!isset($out[$date][$vendor_id]) || $busy_status === 'booked') {
                        $out[$date][$vendor_id] = array(
                            'status' => $busy_status,
                            'plan_id' => $plan_id,
                            'plan_title' => $title,
                            'venue_id' => $venue_id,
                            'venue_label' => $venue_label,
                            'detail' => $detail,
                        );
                    }
                }
            }
        }

        return $out;
    }
}

if (!function_exists('vms_vendor_availability_busy_source_for_date')) {
    function vms_vendor_availability_busy_source_for_date(int $vendor_id, string $date, int $exclude_plan_id = 0): string
    {
        $vendor_id = absint($vendor_id);
        $exclude_plan_id = absint($exclude_plan_id);
        if ($vendor_id <= 0 || !vms_vendor_availability_is_valid_ymd($date)) {
            return '';
        }

        $map = vms_vendor_availability_busy_map($date, $date);
        if (empty($map[$date][$vendor_id]) || !is_array($map[$date][$vendor_id])) {
            return '';
        }

        $row = $map[$date][$vendor_id];
        if ($exclude_plan_id > 0 && absint($row['plan_id'] ?? 0) === $exclude_plan_id) {
            return '';
        }

        $status = sanitize_key((string) ($row['status'] ?? ''));
        return in_array($status, array('booked', 'tentative'), true) ? $status : '';
    }
}

if (!function_exists('vms_get_vendor_availability_for_date')) {
    /**
     * Backward-compatible admin helper expected by Event Plan vendor pickers.
     *
     * @param array<string,mixed> $args
     */
    function vms_get_vendor_availability_for_date(int $vendor_id, string $date, array $args = array()): string
    {
        $busy_source = isset($args['busy_source']) ? sanitize_key((string) $args['busy_source']) : '';
        if ($busy_source === '') {
            $busy_source = vms_vendor_availability_busy_source_for_date(
                $vendor_id,
                $date,
                isset($args['exclude_plan_id']) ? absint($args['exclude_plan_id']) : 0
            );
        }

        if (!function_exists('vms_vendor_effective_availability_for_date')) {
            return '';
        }

        $resolved = (array) vms_vendor_effective_availability_for_date($vendor_id, $date, array(
            'busy_source' => $busy_source,
        ));

        $state = sanitize_key((string) ($resolved['state'] ?? ''));
        if (in_array($state, array('available', 'unavailable', 'tentative', 'booked', 'no-response'), true)) {
            return $state;
        }
        if ($state === 'no_response') {
            return 'no-response';
        }
        return $state;
    }
}

if (!function_exists('vms_vendor_availability_next_item_map')) {
    function vms_vendor_availability_next_item_map(): array
    {
        if (function_exists('vms_vendor_command_center_collect_plan_maps')) {
            $maps = (array) vms_vendor_command_center_collect_plan_maps();
            $next = isset($maps['next_map']) && is_array($maps['next_map']) ? $maps['next_map'] : array();
            return $next;
        }
        return array();
    }
}

if (!function_exists('vms_vendor_availability_day_rows')) {
    function vms_vendor_availability_day_rows(array $vendors, string $date, array $busy_map, array $filters): array
    {
        $next_map = vms_vendor_availability_next_item_map();
        $selected_status = sanitize_key((string) ($filters['status'] ?? 'all'));
        $day_busy = isset($busy_map[$date]) && is_array($busy_map[$date]) ? $busy_map[$date] : array();
        $rows = array();

        foreach ($vendors as $vendor) {
            $vendor_id = absint($vendor['vendor_id'] ?? 0);
            if ($vendor_id <= 0) {
                continue;
            }

            $busy_info = isset($day_busy[$vendor_id]) && is_array($day_busy[$vendor_id]) ? $day_busy[$vendor_id] : array();
            $resolved = function_exists('vms_vendor_effective_availability_for_date')
                ? (array) vms_vendor_effective_availability_for_date($vendor_id, $date, array(
                    'busy_source' => sanitize_key((string) ($busy_info['status'] ?? '')),
                ))
                : array();

            $state = sanitize_key((string) ($resolved['state'] ?? 'no-response'));
            if ($state === 'no_response') {
                $state = 'no-response';
            }
            if ($selected_status !== 'all' && $state !== $selected_status) {
                continue;
            }

            $detail = trim((string) ($resolved['detail'] ?? ''));
            if (!empty($busy_info['detail']) && in_array($state, array('booked', 'tentative'), true)) {
                $detail = (string) $busy_info['detail'];
            }

            $rows[] = array(
                'vendor_id' => $vendor_id,
                'title' => (string) ($vendor['title'] ?? ''),
                'edit_link' => (string) ($vendor['edit_link'] ?? ''),
                'types' => (array) ($vendor['types'] ?? array()),
                'type_slugs' => (array) ($vendor['type_slugs'] ?? array()),
                'home_venue_id' => (int) ($vendor['home_venue_id'] ?? 0),
                'home_venue_label' => (string) ($vendor['home_venue_label'] ?? ''),
                'post_status' => (string) ($vendor['post_status'] ?? ''),
                'setup' => (array) ($vendor['setup'] ?? array()),
                'state' => $state,
                'label' => (string) ($resolved['label'] ?? __('No reply', 'backstage-venue-manager')),
                'source' => (string) ($resolved['source'] ?? ''),
                'detail' => $detail,
                'conflict' => !empty($resolved['conflict']),
                'assignable' => !empty($resolved['assignable']),
                'busy_info' => $busy_info,
                'next_item' => isset($next_map[$vendor_id]) && is_array($next_map[$vendor_id]) ? $next_map[$vendor_id] : array(),
            );
        }

        usort($rows, static function (array $a, array $b): int {
            $priority = array(
                'available' => 1,
                'no-response' => 2,
                'tentative' => 3,
                'booked' => 4,
                'unavailable' => 5,
            );
            $ap = $priority[sanitize_key((string) ($a['state'] ?? ''))] ?? 99;
            $bp = $priority[sanitize_key((string) ($b['state'] ?? ''))] ?? 99;
            if ($ap === $bp) {
                return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
            }
            return $ap <=> $bp;
        });

        return $rows;
    }
}

if (!function_exists('vms_vendor_availability_day_summary')) {
    function vms_vendor_availability_day_summary(array $rows): array
    {
        $summary = array(
            'total' => count($rows),
            'available' => 0,
            'unavailable' => 0,
            'tentative' => 0,
            'booked' => 0,
            'no-response' => 0,
        );

        foreach ($rows as $row) {
            $state = sanitize_key((string) ($row['state'] ?? ''));
            if (isset($summary[$state])) {
                $summary[$state]++;
            }
        }

        return $summary;
    }
}

if (!function_exists('vms_vendor_availability_pill')) {
    function vms_vendor_availability_pill(string $label, string $tone = 'neutral', string $title = ''): string
    {
        if (function_exists('vms_vendor_command_center_pill')) {
            return vms_vendor_command_center_pill($label, $tone, $title);
        }

        $classes = 'vms-vcc-pill vms-vcc-pill--' . sanitize_html_class($tone);
        $attrs = $title !== '' ? ' title="' . esc_attr($title) . '"' : '';
        return '<span class="' . esc_attr($classes) . '"' . $attrs . '>' . esc_html($label) . '</span>';
    }
}

if (!function_exists('vms_vendor_availability_state_tone')) {
    function vms_vendor_availability_state_tone(string $state): string
    {
        $state = sanitize_key($state);
        $map = array(
            'available' => 'success',
            'no-response' => 'warning',
            'tentative' => 'warning',
            'booked' => 'danger',
            'unavailable' => 'neutral',
        );
        return $map[$state] ?? 'neutral';
    }
}

if (!function_exists('vms_vendor_availability_month_matrix_rows')) {
    function vms_vendor_availability_month_matrix_rows(array $vendors, string $month, array $busy_map, array $filters = array()): array
    {
        $month_start = $month . '-01';
        $days_in_month = vms_vendor_availability_days_in_month($month);
        $out = array();
        for ($day = 1; $day <= $days_in_month; $day++) {
            $date = sprintf('%s-%02d', $month, $day);
            $row_filters = $filters;
            if (empty($row_filters['status'])) {
                $row_filters['status'] = 'all';
            }
            $rows = vms_vendor_availability_day_matches_filter($date, $row_filters)
                ? vms_vendor_availability_day_rows($vendors, $date, $busy_map, $row_filters)
                : array();
            $out[$date] = array(
                'rows' => $rows,
                'summary' => vms_vendor_availability_day_summary($rows),
            );
        }
        return $out;
    }
}


if (!function_exists('vms_vendor_availability_focus_state')) {
    function vms_vendor_availability_focus_state(array $filters = array()): string
    {
        $selected = sanitize_key((string) ($filters['status'] ?? 'all'));
        if ($selected !== '' && $selected !== 'all') {
            return $selected;
        }
        return 'available';
    }
}

if (!function_exists('vms_vendor_availability_state_label')) {
    function vms_vendor_availability_state_label(string $state): string
    {
        $state = sanitize_key($state);
        $labels = vms_vendor_availability_status_options();
        return (string) ($labels[$state] ?? __('Vendors', 'backstage-venue-manager'));
    }
}

if (!function_exists('vms_vendor_availability_focus_rows')) {
    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array{rows:array<int,array<string,mixed>>,total:int,hidden:int}
     */
    function vms_vendor_availability_focus_rows(array $rows, string $focus_state = 'available', int $limit = 3): array
    {
        $focus_state = sanitize_key($focus_state);
        $matches = array_values(array_filter($rows, static function (array $row) use ($focus_state): bool {
            return sanitize_key((string) ($row['state'] ?? '')) === $focus_state;
        }));

        return array(
            'rows' => array_slice($matches, 0, max(1, $limit)),
            'total' => count($matches),
            'hidden' => max(0, count($matches) - max(1, $limit)),
        );
    }
}

if (!function_exists('vms_vendor_availability_group_rows')) {
    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,array{label:string,rows:array<int,array<string,mixed>>}>
     */
    function vms_vendor_availability_group_rows(array $rows): array
    {
        $ordered_states = array('available', 'no-response', 'tentative', 'booked', 'unavailable');
        $groups = array();
        foreach ($ordered_states as $state) {
            $groups[$state] = array(
                'label' => vms_vendor_availability_state_label($state),
                'rows' => array(),
            );
        }

        foreach ($rows as $row) {
            $state = sanitize_key((string) ($row['state'] ?? 'no-response'));
            if (!isset($groups[$state])) {
                $groups[$state] = array(
                    'label' => vms_vendor_availability_state_label($state),
                    'rows' => array(),
                );
            }
            $groups[$state]['rows'][] = $row;
        }

        return array_filter($groups, static function (array $group): bool {
            return !empty($group['rows']);
        });
    }
}

if (!function_exists('vms_vendor_availability_vendor_month_rows')) {
    /**
     * @return array<string,array<string,mixed>>
     */
    function vms_vendor_availability_vendor_month_rows(int $vendor_id, string $month): array
    {
        $vendor_id = absint($vendor_id);
        $month = vms_vendor_availability_normalize_month($month);
        if ($vendor_id <= 0) {
            return array();
        }

        $month_start = $month . '-01';
        $days_in_month = vms_vendor_availability_days_in_month($month);
        $month_end = $month . '-' . str_pad((string) $days_in_month, 2, '0', STR_PAD_LEFT);
        $busy_map = vms_vendor_availability_busy_map($month_start, $month_end);
        $rows = array();

        for ($day = 1; $day <= $days_in_month; $day++) {
            $date = sprintf('%s-%02d', $month, $day);
            $busy_info = isset($busy_map[$date][$vendor_id]) && is_array($busy_map[$date][$vendor_id]) ? $busy_map[$date][$vendor_id] : array();
            $resolved = function_exists('vms_vendor_effective_availability_for_date')
                ? (array) vms_vendor_effective_availability_for_date($vendor_id, $date, array(
                    'busy_source' => sanitize_key((string) ($busy_info['status'] ?? '')),
                ))
                : array();

            $state = sanitize_key((string) ($resolved['state'] ?? 'no-response'));
            if ($state === 'no_response') {
                $state = 'no-response';
            }

            $detail = trim((string) ($resolved['detail'] ?? ''));
            if (!empty($busy_info['detail']) && in_array($state, array('booked', 'tentative'), true)) {
                $detail = (string) $busy_info['detail'];
            }

            $rows[$date] = array(
                'date' => $date,
                'day' => $day,
                'state' => $state,
                'label' => (string) ($resolved['label'] ?? __('No reply', 'backstage-venue-manager')),
                'source' => (string) ($resolved['source'] ?? ''),
                'detail' => $detail,
                'tone' => vms_vendor_availability_state_tone($state),
            );
        }

        return $rows;
    }
}

if (!function_exists('vms_vendor_availability_is_bookable_state')) {
    function vms_vendor_availability_is_bookable_state(string $state): bool
    {
        $state = sanitize_key($state);
        return !in_array($state, array('booked', 'tentative', 'unavailable'), true);
    }
}

if (!function_exists('vms_vendor_availability_venue_is_open_for_date')) {
    function vms_vendor_availability_venue_is_open_for_date(int $venue_id, string $date): ?bool
    {
        $venue_id = absint($venue_id);
        if ($venue_id <= 0 || !vms_vendor_availability_is_valid_ymd($date)) {
            return null;
        }
        if (function_exists('bvmgr_is_venue_closed_on_date') && bvmgr_is_venue_closed_on_date($venue_id, $date)) {
            return false;
        }
        if (function_exists('bvmgr_venue_is_open_on_date')) {
            return (bool) bvmgr_venue_is_open_on_date($venue_id, $date);
        }
        return null;
    }
}

if (!function_exists('vms_vendor_availability_row_venue_open_state')) {
    function vms_vendor_availability_row_venue_open_state(array $row, string $date, array $filters = array()): ?bool
    {
        $venue_id = absint($filters['venue_id'] ?? 0);
        if ($venue_id <= 0) {
            $venue_id = absint($row['home_venue_id'] ?? 0);
        }
        return vms_vendor_availability_venue_is_open_for_date($venue_id, $date);
    }
}

if (!function_exists('vms_vendor_availability_day_matches_filter')) {
    function vms_vendor_availability_day_matches_filter(string $date, array $filters): bool
    {
        if (!vms_vendor_availability_is_valid_ymd($date)) {
            return false;
        }

        $filter = sanitize_key((string) ($filters['day_filter'] ?? 'all'));
        if ($filter === '' || $filter === 'all') {
            return true;
        }

        $dow = vms_vendor_availability_day_of_week($date);
        if ($filter === 'weekdays') {
            return $dow >= 1 && $dow <= 5;
        }
        if ($filter === 'weekends') {
            return $dow === 0 || $dow === 6;
        }
        if ($filter === 'venue_open') {
            $venue_id = absint($filters['venue_id'] ?? 0);
            if ($venue_id <= 0) {
                return true;
            }
            return vms_vendor_availability_venue_is_open_for_date($venue_id, $date) === true;
        }

        return true;
    }
}

if (!function_exists('vms_vendor_availability_booking_links')) {
    /**
     * @return array{url:string,override_url:string,venue_open:?bool}
     */
    function vms_vendor_availability_booking_links(array $row, string $date, array $filters = array()): array
    {
        $venue_open = vms_vendor_availability_row_venue_open_state($row, $date, $filters);
        $can_book_by_state = !empty($row['assignable']) && vms_vendor_availability_is_bookable_state((string) ($row['state'] ?? ''));
        $url = '';
        $override_url = '';
        if ($can_book_by_state) {
            if ($venue_open !== false) {
                $url = vms_vendor_availability_new_plan_url($row, $date, $filters);
            } else {
                $override_url = vms_vendor_availability_new_plan_url($row, $date, $filters, true);
            }
        }
        return array(
            'url' => $url,
            'override_url' => $override_url,
            'venue_open' => $venue_open,
        );
	}
}

if (!function_exists('vms_vendor_availability_type_group_label_for_row')) {
	function vms_vendor_availability_type_group_label_for_row(array $row): string
	{
		$types = array_values(array_filter(array_map('strval', (array) ($row['types'] ?? array()))));
		return !empty($types) ? (string) $types[0] : (string) __('Uncategorized', 'backstage-venue-manager');
	}
}

if (!function_exists('vms_vendor_availability_group_rows_by_type')) {
	/**
	 * @return array<string,array{label:string,rows:array<int,array<string,mixed>>}>
	 */
	function vms_vendor_availability_group_rows_by_type(array $rows): array
	{
		$groups = array();
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			$label = vms_vendor_availability_type_group_label_for_row($row);
			$key = sanitize_key($label);
			if ($key === '') {
				$key = 'uncategorized';
			}
			if (!isset($groups[$key])) {
				$groups[$key] = array(
					'label' => $label,
					'rows' => array(),
				);
			}
			$groups[$key]['rows'][] = $row;
		}

		uasort($groups, static function (array $left, array $right): int {
			return strcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
		});

		return $groups;
	}
}

if (!function_exists('vms_vendor_availability_find_next_bookable_vendor_date')) {
    /**
     * @param array<string,mixed> $booking_seed
     * @return array{date:string,url:string,label:string,state:string}
     */
    function vms_vendor_availability_find_next_bookable_vendor_date(int $vendor_id, array $booking_seed, string $start_date = ''): array
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            return array();
        }

        $today = wp_date('Y-m-d', time(), wp_timezone());
        $start_date = vms_vendor_availability_is_valid_ymd($start_date) ? $start_date : $today;
        $cursor = DateTimeImmutable::createFromFormat('Y-m-d', $start_date, wp_timezone());
        if (!($cursor instanceof DateTimeImmutable)) {
            $cursor = new DateTimeImmutable($today, wp_timezone());
        }
        $cursor = $cursor->modify('first day of this month');

        for ($i = 0; $i < 6; $i++) {
            $month = $cursor->format('Y-m');
            $rows = vms_vendor_availability_vendor_month_rows($vendor_id, $month);
            foreach ($rows as $date => $row) {
                if (!vms_vendor_availability_is_valid_ymd((string) $date) || (string) $date < $start_date) {
                    continue;
                }

                $state = sanitize_key((string) ($row['state'] ?? 'no-response'));
                if (!vms_vendor_availability_is_bookable_state($state)) {
                    continue;
                }
                if (vms_vendor_availability_row_venue_open_state($booking_seed, (string) $date, array('venue_id' => (int) ($booking_seed['home_venue_id'] ?? 0))) === false) {
                    continue;
                }

                $url = vms_vendor_availability_new_plan_url($booking_seed, (string) $date, array(
                    'venue_id' => (int) ($booking_seed['home_venue_id'] ?? 0),
                ));
                if ($url === '') {
                    continue;
                }

                return array(
                    'date' => (string) $date,
                    'url' => $url,
                    'label' => (string) ($row['label'] ?? __('Book', 'backstage-venue-manager')),
                    'state' => $state,
                );
            }

            $cursor = $cursor->modify('first day of next month');
        }

        return array();
    }
}

if (!function_exists('vms_render_vendor_availability_vendor_profile_calendar')) {
    function vms_render_vendor_availability_vendor_profile_calendar(int $vendor_id, string $month = ''): void
    {
        $vendor_id = absint($vendor_id);
        if ($vendor_id <= 0) {
            echo '<p>' . esc_html__('Availability snapshot will appear after this vendor is saved.', 'backstage-venue-manager') . '</p>';
            return;
        }

        $month = vms_vendor_availability_normalize_month($month);
        $month_rows = vms_vendor_availability_vendor_month_rows($vendor_id, $month);
        $summary = vms_vendor_availability_day_summary(array_values($month_rows));
        $setup = vms_vendor_availability_setup_summary($vendor_id);
        $matrix = function_exists('vms_av_build_month_matrix') ? vms_av_build_month_matrix($month) : array();
        $month_label = date_i18n('F Y', strtotime($month . '-01'));
        $today = wp_date('Y-m-d', time(), wp_timezone());

        $base_edit_url = admin_url('post.php');
        $month_dt = DateTimeImmutable::createFromFormat('Y-m-d', $month . '-01', wp_timezone());
        if (!($month_dt instanceof DateTimeImmutable)) {
            $month_dt = new DateTimeImmutable('first day of this month', wp_timezone());
        }
        $prev_month = $month_dt->modify('-1 month')->format('Y-m');
        $next_month = $month_dt->modify('+1 month')->format('Y-m');
        $current_month = wp_date('Y-m', time(), wp_timezone());
        $prev_url = add_query_arg(array('post' => $vendor_id, 'action' => 'edit', 'vms_vendor_month' => $prev_month), $base_edit_url);
        $next_url = add_query_arg(array('post' => $vendor_id, 'action' => 'edit', 'vms_vendor_month' => $next_month), $base_edit_url);
        $current_url = add_query_arg(array('post' => $vendor_id, 'action' => 'edit', 'vms_vendor_month' => $current_month), $base_edit_url);
        $board_url = add_query_arg(array(
            'page' => vms_vendor_availability_page_slug(),
            'month' => $month,
            'date' => $month . '-01',
            'q' => get_the_title($vendor_id),
            'view' => 'month',
            'roster' => 'all',
        ), admin_url('admin.php'));
        $type_terms = vms_vendor_availability_type_terms($vendor_id);
        $type_slugs = array();
        foreach ($type_terms as $type_term) {
            if (!empty($type_term['slug'])) {
                $type_slugs[] = (string) $type_term['slug'];
            }
        }
        $profile_booking_seed = array(
            'vendor_id' => $vendor_id,
            'title' => (string) get_the_title($vendor_id),
            'type_slugs' => $type_slugs,
            'home_venue_id' => vms_vendor_availability_home_venue_id($vendor_id),
            'assignable' => true,
        );
        $next_booking = vms_vendor_availability_find_next_bookable_vendor_date($vendor_id, $profile_booking_seed, $today);

        echo '<div class="vms-va-profile">';
        echo '<div class="vms-va-profile__head">';
        echo '<div>';
        echo '<p class="description">' . esc_html__('Read-only snapshot of this vendor\'s resolved availability. It mirrors the availability board logic, including manual overrides, pattern rules, ICS blocks, and scheduled Event Plans.', 'backstage-venue-manager') . '</p>';
        echo '<div class="vms-va-profile__meta">';
        echo wp_kses_post(vms_vendor_availability_pill((string) ($setup['label'] ?? __('No availability setup yet', 'backstage-venue-manager')), (string) ($setup['tone'] ?? 'warning')));
        echo '</div>';
        echo '</div>';
        echo '<div class="vms-va-profile__nav">';
        echo '<a class="button button-small" href="' . esc_url($prev_url) . '">&larr; ' . esc_html__('Prev month', 'backstage-venue-manager') . '</a>';
        echo '<a class="button button-small" href="' . esc_url($current_url) . '">' . esc_html__('Current month', 'backstage-venue-manager') . '</a>';
        echo '<a class="button button-small" href="' . esc_url($next_url) . '">' . esc_html__('Next month', 'backstage-venue-manager') . ' &rarr;</a>';
        echo '<a class="button button-secondary button-small" href="' . esc_url($board_url) . '">' . esc_html__('Open on availability board', 'backstage-venue-manager') . '</a>';
        if (!empty($next_booking['url']) && !empty($next_booking['date'])) {
            /* translators: %s: formatted next open booking date. */
            echo '<a class="button button-primary button-small" href="' . esc_url((string) $next_booking['url']) . '">' . esc_html(sprintf(__('Book next open date (%s)', 'backstage-venue-manager'), date_i18n(get_option('date_format'), strtotime((string) $next_booking['date'])))) . '</a>';
        }
        echo '</div>';
        echo '</div>';

        echo '<div class="vms-va-profile__summary">';
        echo '<strong>' . esc_html($month_label) . '</strong>';
        $summary_parts = array(
            /* translators: %d: number of available dates in the current month summary. */
            sprintf(__('Available: %d', 'backstage-venue-manager'), (int) ($summary['available'] ?? 0)),
            /* translators: %d: number of no-response dates in the current month summary. */
            sprintf(__('No reply: %d', 'backstage-venue-manager'), (int) ($summary['no-response'] ?? 0)),
            /* translators: %d: number of tentative dates in the current month summary. */
            sprintf(__('Tentative: %d', 'backstage-venue-manager'), (int) ($summary['tentative'] ?? 0)),
            /* translators: %d: number of booked dates in the current month summary. */
            sprintf(__('Booked: %d', 'backstage-venue-manager'), (int) ($summary['booked'] ?? 0)),
            /* translators: %d: number of unavailable dates in the current month summary. */
            sprintf(__('Unavailable: %d', 'backstage-venue-manager'), (int) ($summary['unavailable'] ?? 0))
        );
        echo '<span>' . esc_html(implode(' · ', $summary_parts)) . '</span>';
        echo '</div>';

        if (empty($matrix)) {
            echo '<p>' . esc_html__('Calendar could not be generated for this month.', 'backstage-venue-manager') . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="vms-va-profile__table-wrap">';
        echo '<table class="widefat vms-va-profile-grid">';
        echo '<thead><tr>';
        foreach (array(__('Sun', 'backstage-venue-manager'), __('Mon', 'backstage-venue-manager'), __('Tue', 'backstage-venue-manager'), __('Wed', 'backstage-venue-manager'), __('Thu', 'backstage-venue-manager'), __('Fri', 'backstage-venue-manager'), __('Sat', 'backstage-venue-manager')) as $dow) {
            echo '<th>' . esc_html($dow) . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($matrix as $week) {
            echo '<tr>';
            foreach ($week as $cell) {
                $date = isset($cell['date']) ? (string) $cell['date'] : '';
                $day = isset($cell['day']) ? (int) $cell['day'] : 0;
                if ($date === '' || $day <= 0) {
                    echo '<td class="vms-va-profile-grid__empty"></td>';
                    continue;
                }

                $row = isset($month_rows[$date]) && is_array($month_rows[$date]) ? $month_rows[$date] : array();
                $state = sanitize_key((string) ($row['state'] ?? 'no-response'));
                $label = (string) ($row['label'] ?? __('No reply', 'backstage-venue-manager'));
                $source = trim((string) ($row['source'] ?? ''));
                $detail = trim((string) ($row['detail'] ?? ''));
                $classes = array(
                    'vms-va-profile-grid__cell',
                    'is-' . sanitize_html_class($state),
                );
                if ($date === $today) {
                    $classes[] = 'is-today';
                }

                $title_parts = array_filter(array(
                    $label,
                    $source,
                    $detail,
                ));
                $title_text = !empty($title_parts) ? implode(' — ', $title_parts) : '';
                $profile_booking_row = array_merge($profile_booking_seed, array(
                    'state' => $state,
                    'assignable' => !empty($profile_booking_seed['type_slugs']) && $date >= $today,
                ));
                $booking_links = vms_vendor_availability_booking_links($profile_booking_row, $date, array('venue_id' => (int) ($profile_booking_seed['home_venue_id'] ?? 0)));
                $booking_url = (string) ($booking_links['url'] ?? '');
                $override_booking_url = (string) ($booking_links['override_url'] ?? '');

                echo '<td class="' . esc_attr(implode(' ', $classes)) . '"';
                if ($title_text !== '') {
                    echo ' title="' . esc_attr($title_text) . '"';
                }
                echo '>';
                echo '<div class="vms-va-profile-grid__day">' . esc_html((string) $day) . '</div>';
                echo '<div class="vms-va-profile-grid__pill">' . wp_kses_post(vms_vendor_availability_pill($label, vms_vendor_availability_state_tone($state))) . '</div>';
                if ($booking_url !== '') {
                    echo '<div class="vms-va-profile-grid__actions"><a class="button button-small vms-va-inline-book" href="' . esc_url($booking_url) . '">' . esc_html__('Book', 'backstage-venue-manager') . '</a></div>';
                } elseif ($override_booking_url !== '') {
                    echo '<div class="vms-va-profile-grid__actions"><span class="vms-va-muted">' . esc_html__('Venue closed', 'backstage-venue-manager') . '</span><a class="button button-small vms-va-inline-book" href="' . esc_url($override_booking_url) . '">' . esc_html__('Override venue schedule and book anyway', 'backstage-venue-manager') . '</a></div>';
                }
                echo '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
        echo '<div class="vms-va-profile__legend">';
        foreach (array('available', 'no-response', 'tentative', 'booked', 'unavailable') as $state) {
            echo wp_kses_post(vms_vendor_availability_pill(vms_vendor_availability_state_label($state), vms_vendor_availability_state_tone($state))) . ' ';
        }
        echo '</div>';
        echo '</div>';
    }
}

if (!function_exists('vms_render_vendor_availability_page')) {
    function vms_render_vendor_availability_page(): void
    {
        $tour_button = '<button type="button" class="button button-secondary vms-tour-help-trigger" data-vms-tour-start="vms.vendor_availability.basics" data-vms-tour="vendor-availability.help-action">' . esc_html__('Start Guided Tour', 'backstage-venue-manager') . '</button>';
        if (function_exists('bvmgr_render_help_button')) {
            $tour_button = bvmgr_render_help_button(array(
                'tour_id' => 'vms.vendor_availability.basics',
                'anchor' => 'vendor-availability.help-action',
                'label' => __('Start Guided Tour', 'backstage-venue-manager'),
                'class' => 'button-secondary',
            ));
        }

        $actions_html = '<div class="vms-va-header-actions">' . $tour_button . '</div>';

        if (function_exists('bvmgr_admin_ui_render_shell')) {
            bvmgr_admin_ui_render_shell(
                array(
                    'title' => __('Vendor Availability', 'backstage-venue-manager'),
                    'subtitle' => __('See who is actually available first, then expand each day for the full vendor picture without jumping into each profile.', 'backstage-venue-manager'),
                    'shell_id' => 'vms-vendor-availability',
                    'content_class' => 'vms-va-content',
                    'actions_html' => $actions_html,
                ),
                'vms_render_vendor_availability_page_content'
            );
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Vendor Availability', 'backstage-venue-manager') . '</h1>';
        vms_render_vendor_availability_page_content();
        echo '</div>';
    }
}

if (!function_exists('vms_render_vendor_availability_page_content')) {
    function vms_render_vendor_availability_page_content(): void
    {
        $filters = vms_vendor_availability_selected_filters();
        $all_vendors = vms_vendor_availability_collect_vendors();
        $vendors = vms_vendor_availability_filter_vendors($all_vendors, $filters);
        $venue_options = vms_vendor_availability_venue_options();
        $type_options = vms_vendor_availability_type_options();

        $month_start = $filters['month'] . '-01';
        $month_end = $filters['month'] . '-' . str_pad((string) vms_vendor_availability_days_in_month((string) $filters['month']), 2, '0', STR_PAD_LEFT);
        $busy_start = ((string) $filters['date'] < $month_start) ? (string) $filters['date'] : $month_start;
        $busy_end = ((string) $filters['date'] > $month_end) ? (string) $filters['date'] : $month_end;
        $busy_map = vms_vendor_availability_busy_map($busy_start, $busy_end);
		$selected_day_rows = vms_vendor_availability_day_matches_filter((string) $filters['date'], $filters)
			? vms_vendor_availability_day_rows($vendors, (string) $filters['date'], $busy_map, $filters)
			: array();
        $selected_day_summary = vms_vendor_availability_day_summary($selected_day_rows);
		$month_rows = vms_vendor_availability_month_matrix_rows($vendors, (string) $filters['month'], $busy_map, $filters);

        echo '<div class="vms-va-intro" data-vms-tour="vendor-availability.help">';
        echo '<p>' . esc_html__('Use Month view to see vendor names first. Each day surfaces a short list of who is available at a glance, and List view explains the why when you need more context.', 'backstage-venue-manager') . '</p>';
        echo '</div>';

        echo '<form method="get" class="vms-va-filters" data-vms-tour="vendor-availability.filters">';
        echo '<input type="hidden" name="page" value="' . esc_attr(vms_vendor_availability_page_slug()) . '">';

        echo '<div class="vms-va-filter-grid">';

        echo '<p><label for="vms-va-view"><strong>' . esc_html__('View', 'backstage-venue-manager') . '</strong></label><br>';
        echo '<select id="vms-va-view" name="view">';
        foreach (vms_vendor_availability_view_options() as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected((string) $filters['view'], $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></p>';

        echo '<p><label for="vms-va-month"><strong>' . esc_html__('Month', 'backstage-venue-manager') . '</strong></label><br>';
        echo '<input type="month" id="vms-va-month" name="month" value="' . esc_attr((string) $filters['month']) . '"></p>';

        echo '<p><label for="vms-va-date"><strong>' . esc_html__('Detail date', 'backstage-venue-manager') . '</strong></label><br>';
        echo '<input type="date" id="vms-va-date" name="date" value="' . esc_attr((string) $filters['date']) . '"></p>';

        echo '<p><label for="vms-va-q"><strong>' . esc_html__('Search', 'backstage-venue-manager') . '</strong></label><br>';
        echo '<input type="search" id="vms-va-q" name="q" value="' . esc_attr((string) $filters['q']) . '" placeholder="' . esc_attr__('Vendor, type, or venue', 'backstage-venue-manager') . '"></p>';

        echo '<p><label for="vms-va-type"><strong>' . esc_html__('Vendor type', 'backstage-venue-manager') . '</strong></label><br>';
        echo '<select id="vms-va-type" name="vendor_type">';
        echo '<option value="">' . esc_html__('All types', 'backstage-venue-manager') . '</option>';
        foreach ($type_options as $slug => $label) {
            echo '<option value="' . esc_attr((string) $slug) . '" ' . selected((string) $filters['type'], (string) $slug, false) . '>' . esc_html((string) $label) . '</option>';
        }
        echo '<option value="uncategorized" ' . selected((string) $filters['type'], 'uncategorized', false) . '>' . esc_html__('Uncategorized', 'backstage-venue-manager') . '</option>';
        echo '</select></p>';

        echo '<p><label for="vms-va-status"><strong>' . esc_html__('Availability status', 'backstage-venue-manager') . '</strong></label><br>';
        echo '<select id="vms-va-status" name="availability_status">';
        foreach (vms_vendor_availability_status_options() as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected((string) $filters['status'], $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></p>';

        echo '<p><label for="vms-va-day-filter"><strong>' . esc_html__('Day filter', 'backstage-venue-manager') . '</strong></label><br>';
        echo '<select id="vms-va-day-filter" name="day_filter">';
        foreach (vms_vendor_availability_day_filter_options() as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected((string) $filters['day_filter'], $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></p>';

        echo '<p><label for="vms-va-venue"><strong>' . esc_html__('Home venue', 'backstage-venue-manager') . '</strong></label><br>';
        echo '<select id="vms-va-venue" name="venue_id">';
        echo '<option value="0">' . esc_html__('All venues', 'backstage-venue-manager') . '</option>';
        foreach ($venue_options as $venue_id => $label) {
            echo '<option value="' . esc_attr((string) $venue_id) . '" ' . selected((int) $filters['venue_id'], (int) $venue_id, false) . '>' . esc_html((string) $label) . '</option>';
        }
        echo '</select></p>';

        echo '<p><label for="vms-va-setup"><strong>' . esc_html__('Availability setup', 'backstage-venue-manager') . '</strong></label><br>';
        echo '<select id="vms-va-setup" name="availability_setup">';
        foreach (vms_vendor_availability_setup_options() as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected((string) $filters['setup'], $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></p>';

        echo '<p><label for="vms-va-roster"><strong>' . esc_html__('Roster filter', 'backstage-venue-manager') . '</strong></label><br>';
        echo '<select id="vms-va-roster" name="roster">';
        foreach (vms_vendor_availability_roster_options() as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected((string) $filters['roster'], $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></p>';

        echo '</div>';

        echo '<p class="vms-va-filter-actions">';
        submit_button(__('Apply filters', 'backstage-venue-manager'), 'primary', '', false);
        echo ' <a class="button" href="' . esc_url(admin_url('admin.php?page=' . vms_vendor_availability_page_slug())) . '">' . esc_html__('Reset', 'backstage-venue-manager') . '</a>';
        echo '</p>';
        echo '</form>';

        echo '<div class="vms-va-summary-grid" data-vms-tour="vendor-availability.summary">';
        $cards = array(
            array('label' => __('Filtered vendors', 'backstage-venue-manager'), 'value' => (string) count($vendors), 'tone' => 'neutral'),
            array('label' => __('Available', 'backstage-venue-manager'), 'value' => (string) ($selected_day_summary['available'] ?? 0), 'tone' => 'success'),
            array('label' => __('No reply', 'backstage-venue-manager'), 'value' => (string) ($selected_day_summary['no-response'] ?? 0), 'tone' => 'warning'),
            array('label' => __('Tentative', 'backstage-venue-manager'), 'value' => (string) ($selected_day_summary['tentative'] ?? 0), 'tone' => 'warning'),
            array('label' => __('Booked', 'backstage-venue-manager'), 'value' => (string) ($selected_day_summary['booked'] ?? 0), 'tone' => 'danger'),
            array('label' => __('Unavailable', 'backstage-venue-manager'), 'value' => (string) ($selected_day_summary['unavailable'] ?? 0), 'tone' => 'neutral'),
        );
        foreach ($cards as $card) {
            $label = (string) ($card['label'] ?? '');
            $tone = (string) ($card['tone'] ?? 'neutral');
            echo '<div class="vms-va-summary-card vms-va-summary-card--' . esc_attr($tone) . '">';
            echo '<div class="vms-va-summary-card__value">' . esc_html((string) $card['value']) . '</div>';
            echo '<div class="vms-va-summary-card__label">';
            if ($label === __('Filtered vendors', 'backstage-venue-manager')) {
                echo '<span class="vms-va-summary-card__labeltext">' . esc_html($label) . '</span>';
            } else {
                echo wp_kses_post(vms_vendor_availability_pill($label, $tone));
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';

        if ((string) $filters['view'] === 'month') {
            vms_render_vendor_availability_month_view($vendors, $filters, $month_rows);
        }

        vms_render_vendor_availability_list_view($selected_day_rows, (string) $filters['date'], (string) $filters['view'], $filters);
    }
}

if (!function_exists('vms_render_vendor_availability_month_view')) {
    function vms_render_vendor_availability_month_view(array $vendors, array $filters, array $month_rows): void
    {
        $month = (string) ($filters['month'] ?? wp_date('Y-m'));
        $selected_status = sanitize_key((string) ($filters['status'] ?? 'all'));
        $selected_date = (string) ($filters['date'] ?? '');
        $matrix = function_exists('vms_av_build_month_matrix') ? vms_av_build_month_matrix($month) : array();
        $month_label = date_i18n('F Y', strtotime($month . '-01'));

        echo '<div class="vms-va-month" data-vms-tour="vendor-availability.month">';
        echo '<div class="vms-va-section-head">';
        echo '<h2>' . esc_html($month_label) . '</h2>';
        echo '<p class="description">' . esc_html__('Each day shows a short names-first list so you can scan who is open without leaving the calendar. Expand a day when you need the full roster context.', 'backstage-venue-manager') . '</p>';
        echo '</div>';

        echo '<table class="widefat vms-va-month-grid">';
        echo '<thead><tr>';
        foreach (array(__('Sun', 'backstage-venue-manager'), __('Mon', 'backstage-venue-manager'), __('Tue', 'backstage-venue-manager'), __('Wed', 'backstage-venue-manager'), __('Thu', 'backstage-venue-manager'), __('Fri', 'backstage-venue-manager'), __('Sat', 'backstage-venue-manager')) as $dow) {
            echo '<th>' . esc_html($dow) . '</th>';
        }
        echo '</tr></thead><tbody>';

        $today = wp_date('Y-m-d', time(), wp_timezone());
        foreach ($matrix as $week) {
            echo '<tr>';
            foreach ($week as $cell) {
                $date = isset($cell['date']) ? (string) $cell['date'] : '';
                $day = isset($cell['day']) ? (int) $cell['day'] : 0;

                if ($date === '' || $day <= 0) {
                    echo '<td class="vms-va-month-grid__empty"></td>';
                    continue;
                }

                $cell_classes = array('vms-va-month-grid__cell');
                if ($date === $today) {
                    $cell_classes[] = 'is-today';
                }
                if ($date === $selected_date) {
                    $cell_classes[] = 'is-selected';
                }
                if ($date < $today) {
                    $cell_classes[] = 'is-past';
                }
                $day_matches = vms_vendor_availability_day_matches_filter($date, $filters);
                $venue_open = vms_vendor_availability_venue_is_open_for_date((int) ($filters['venue_id'] ?? 0), $date);
                if (!$day_matches) {
                    $cell_classes[] = 'is-filtered-out';
                }
                if ($venue_open === false) {
                    $cell_classes[] = 'is-venue-closed';
                }

                $summary = isset($month_rows[$date]['summary']) && is_array($month_rows[$date]['summary']) ? $month_rows[$date]['summary'] : array();
                $all_day_rows = isset($month_rows[$date]['rows']) && is_array($month_rows[$date]['rows']) ? $month_rows[$date]['rows'] : array();
                if (!$day_matches) {
                    $summary = array();
                    $all_day_rows = array();
                }

                echo '<td class="' . esc_attr(implode(' ', $cell_classes)) . '">';
                echo '<div class="vms-va-dayhead">';
                echo '<strong>' . esc_html((string) $day) . '</strong>';
                echo '<a href="' . esc_url(add_query_arg(array(
                    'page' => vms_vendor_availability_page_slug(),
                    'view' => 'list',
                    'month' => $month,
                    'date' => $date,
                    'q' => (string) ($filters['q'] ?? ''),
                    'vendor_type' => (string) ($filters['type'] ?? ''),
                    'availability_status' => (string) ($filters['status'] ?? 'all'),
                    'day_filter' => (string) ($filters['day_filter'] ?? 'all'),
                    'venue_id' => (int) ($filters['venue_id'] ?? 0),
                    'availability_setup' => (string) ($filters['setup'] ?? 'all'),
                    'roster' => (string) ($filters['roster'] ?? 'published'),
                ), admin_url('admin.php'))) . '">' . esc_html__('Review', 'backstage-venue-manager') . '</a>';
                echo '</div>';
                if (!$day_matches) {
                    echo '<div class="vms-va-daynotice">' . esc_html__('Hidden by day filter', 'backstage-venue-manager') . '</div>';
                } elseif ($venue_open === false) {
                    echo '<div class="vms-va-daynotice vms-va-daynotice--closed">' . esc_html__('Venue closed', 'backstage-venue-manager') . '</div>';
                }

                echo '<div class="vms-va-daycounts">';
                $count_specs = array(
                    'available' => __('A', 'backstage-venue-manager'),
                    'no-response' => __('NR', 'backstage-venue-manager'),
                    'tentative' => __('T', 'backstage-venue-manager'),
                    'booked' => __('B', 'backstage-venue-manager'),
                    'unavailable' => __('U', 'backstage-venue-manager'),
                );
                foreach ($count_specs as $state => $abbr) {
                    $value = (int) ($summary[$state] ?? 0);
                    $classes = 'vms-va-count vms-va-count--' . sanitize_html_class(vms_vendor_availability_state_tone($state));
                    if ($selected_status !== 'all' && $selected_status === $state) {
                        $classes .= ' is-active-filter';
                    }
                    echo '<span class="' . esc_attr($classes) . '">' . esc_html($abbr . ' ' . $value) . '</span>';
                }
                echo '</div>';

                $focus_state = vms_vendor_availability_focus_state($filters);
                $focus = vms_vendor_availability_focus_rows($all_day_rows, $focus_state, 3);
                echo '<div class="vms-va-dayfocus">';
                /* translators: %s: focused availability state label. */
                echo '<div class="vms-va-dayfocus__label">' . esc_html(sprintf(__('%s at a glance', 'backstage-venue-manager'), vms_vendor_availability_state_label($focus_state))) . '</div>';
                if (!empty($focus['rows'])) {
                    echo '<ul class="vms-va-daylist">';
                    foreach ((array) $focus['rows'] as $focus_row) {
                        $name = (string) ($focus_row['title'] ?? '');
                        $edit_link = (string) ($focus_row['edit_link'] ?? '');
                        $booking_links = vms_vendor_availability_booking_links((array) $focus_row, $date, $filters);
                        $booking_url = (string) ($booking_links['url'] ?? '');
                        $override_booking_url = (string) ($booking_links['override_url'] ?? '');
                        echo '<li class="vms-va-daylist__item">';
                        echo '<span class="vms-va-daylist__name">';
                        if ($edit_link !== '') {
                            echo '<a href="' . esc_url($edit_link) . '">' . esc_html($name) . '</a>';
                        } else {
                            echo esc_html($name);
                        }
                        echo '</span>';
                        if ($booking_url !== '') {
                            echo '<a class="button button-small vms-va-inline-book" href="' . esc_url($booking_url) . '">' . esc_html__('Book', 'backstage-venue-manager') . '</a>';
                        } elseif ($override_booking_url !== '') {
                            echo '<span class="vms-va-muted">' . esc_html__('Venue closed', 'backstage-venue-manager') . '</span>';
                        }
                        echo '</li>';
                    }
                    echo '</ul>';
                } else {
                    /* translators: %s: focused availability state label in lowercase. */
                    echo '<div class="vms-va-muted">' . esc_html(sprintf(__('No %s vendors for this date.', 'backstage-venue-manager'), strtolower(vms_vendor_availability_state_label($focus_state)))) . '</div>';
                }

                if (count($all_day_rows) > 0) {
                    echo '<div class="vms-va-daydetail-link">';
                    /* translators: %d: number of vendors matching the current filters. */
                    echo '<span class="vms-va-muted">' . esc_html(sprintf(_n('%d vendor matches filters.', '%d vendors match filters.', count($all_day_rows), 'backstage-venue-manager'), count($all_day_rows))) . '</span>';
                    echo ' <a href="' . esc_url(add_query_arg(array(
                        'page' => vms_vendor_availability_page_slug(),
                        'view' => 'list',
                        'month' => $month,
                        'date' => $date,
                        'q' => (string) ($filters['q'] ?? ''),
                        'vendor_type' => (string) ($filters['type'] ?? ''),
                        'availability_status' => (string) ($filters['status'] ?? 'all'),
                        'day_filter' => (string) ($filters['day_filter'] ?? 'all'),
                        'venue_id' => (int) ($filters['venue_id'] ?? 0),
                        'availability_setup' => (string) ($filters['setup'] ?? 'all'),
                        'roster' => (string) ($filters['roster'] ?? 'published'),
                    ), admin_url('admin.php'))) . '">' . esc_html__('View full detail', 'backstage-venue-manager') . '</a>';
                    echo '</div>';
                }
                echo '</div>';

                echo '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }
}


if (!function_exists('vms_vendor_availability_booking_prefill_mode')) {
    /**
     * @param array<string,mixed> $row
     * @return array{mode:string,type_slug:string}
     */
    function vms_vendor_availability_booking_prefill_mode(array $row): array
    {
        $type_slugs = array_values(array_filter(array_map('sanitize_key', (array) ($row['type_slugs'] ?? array()))));
        if (empty($type_slugs)) {
            return array('mode' => '', 'type_slug' => '');
        }

        $primary_keys = array(
            'artist',
            'band',
            'bands',
            'headliner',
            'musician',
            'performer',
            'performers',
            'talent',
        );

        foreach ($type_slugs as $slug) {
            if (in_array($slug, $primary_keys, true)) {
                return array('mode' => 'primary', 'type_slug' => $slug);
            }
        }

        return array(
            'mode' => 'secondary',
            'type_slug' => (string) $type_slugs[0],
        );
    }
}

if (!function_exists('vms_vendor_availability_new_plan_url')) {
    /**
     * Build a safe, non-destructive booking shortcut from the availability board.
     * Opens a new Event Plan with the date/venue/vendor prefilled only.
     *
     * @param array<string,mixed> $row
     * @param array<string,mixed> $filters
     */
    function vms_vendor_availability_new_plan_url(array $row, string $date, array $filters = array(), bool $override_venue_schedule = false): string
    {
        $vendor_id = absint($row['vendor_id'] ?? 0);
        if ($vendor_id <= 0 || !vms_vendor_availability_is_valid_ymd($date)) {
            return '';
        }

        $prefill = vms_vendor_availability_booking_prefill_mode($row);
        $mode = sanitize_key((string) ($prefill['mode'] ?? ''));
        if ($mode === '') {
            return '';
        }

        $venue_id = absint($filters['venue_id'] ?? 0);
        if ($venue_id <= 0) {
            $venue_id = absint($row['home_venue_id'] ?? 0);
        }

        $args = array(
            'post_type' => 'vms_event_plan',
            'vms_date' => $date,
            'vms_prefill_vendor_id' => $vendor_id,
            'vms_prefill_vendor_label' => (string) ($row['title'] ?? ''),
        );
        if ($venue_id > 0) {
            $args['vms_venue_id'] = $venue_id;
        }
        if ($override_venue_schedule) {
            $args['vms_override_venue_schedule'] = 1;
        }

        $fragment = '#vms_band_vendor_id';
        if ($mode === 'primary') {
            $args['vms_prefill_vendor_mode'] = 'primary';
            $args['vms_band_vendor_id'] = $vendor_id;
        } else {
            $type_slug = sanitize_key((string) ($prefill['type_slug'] ?? ''));
            if ($type_slug === '') {
                return '';
            }
            $args['vms_prefill_vendor_mode'] = 'secondary';
            $args['vms_prefill_vendor_type'] = $type_slug;
            $args['vms_secondary_vendor_type'] = $type_slug;
            $args['vms_secondary_vendor_id'] = $vendor_id;
            $fragment = '#vms-secondary-vendors-section';
        }

        return add_query_arg($args, admin_url('post-new.php')) . $fragment;
    }
}

if (!function_exists('vms_vendor_availability_get_list_empty_state_notice_context')) {
    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array{show:bool}
     */
    function vms_vendor_availability_get_list_empty_state_notice_context(array $rows): array
    {
        return array(
            'show' => empty($rows),
        );
    }
}

if (!function_exists('vms_vendor_availability_render_list_empty_state_notice')) {
    /**
     * @param array{show?:mixed} $context
     */
    function vms_vendor_availability_render_list_empty_state_notice(array $context): void
    {
        if (empty($context['show'])) {
            return;
        }

        echo '<div class="notice notice-info inline"><p>' . esc_html__('No vendors matched the current filters for this date.', 'backstage-venue-manager') . '</p></div>';
    }
}

if (!function_exists('vms_render_vendor_availability_list_view')) {
    function vms_render_vendor_availability_list_view(array $rows, string $date, string $active_view = 'list', array $filters = array()): void
    {
        $classes = 'vms-va-list';
        $empty_state_notice_context = vms_vendor_availability_get_list_empty_state_notice_context($rows);
        if ($active_view !== 'list') {
            $classes .= ' vms-va-list--secondary';
        }

        echo '<div class="' . esc_attr($classes) . '" data-vms-tour="vendor-availability.list">';
        echo '<div class="vms-va-section-head">';
        /* translators: %s: formatted selected date. */
        echo '<h2>' . esc_html(sprintf(__('Detail for %s', 'backstage-venue-manager'), date_i18n(get_option('date_format'), strtotime($date)))) . '</h2>';
        echo '<p class="description">' . esc_html__('This view explains why each filtered vendor is free, blocked, tentative, or still unconfirmed on the selected date.', 'backstage-venue-manager') . '</p>';
        echo '</div>';

        if (!empty($empty_state_notice_context['show'])) {
            vms_vendor_availability_render_list_empty_state_notice($empty_state_notice_context);
            echo '</div>';
            return;
        }

        echo '<div class="vms-va-table-wrap">';
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Vendor', 'backstage-venue-manager') . '</th>';
        echo '<th>' . esc_html__('Home venue', 'backstage-venue-manager') . '</th>';
        echo '<th>' . esc_html__('Status', 'backstage-venue-manager') . '</th>';
        echo '<th>' . esc_html__('Next scheduled date', 'backstage-venue-manager') . '</th>';
        echo '<th>' . esc_html__('Actions', 'backstage-venue-manager') . '</th>';
        echo '</tr></thead><tbody>';

        foreach (vms_vendor_availability_group_rows_by_type($rows) as $type_group) {
            $group_rows = isset($type_group['rows']) && is_array($type_group['rows']) ? $type_group['rows'] : array();
            if (empty($group_rows)) {
                continue;
            }
            /* translators: 1: vendor type group label, 2: number of vendors in the group. */
            echo '<tr class="vms-va-type-group-row"><th colspan="5">' . esc_html(sprintf(__('%1$s (%2$d)', 'backstage-venue-manager'), (string) ($type_group['label'] ?? __('Vendor type', 'backstage-venue-manager')), count($group_rows))) . '</th></tr>';

        foreach ($group_rows as $row) {
            $title = (string) ($row['title'] ?? '');
            $edit_link = (string) ($row['edit_link'] ?? '');
            $types = (array) ($row['types'] ?? array());
            $home_venue = (string) ($row['home_venue_label'] ?? '');
            $state = sanitize_key((string) ($row['state'] ?? ''));
            $label = (string) ($row['label'] ?? '');
            $source = (string) ($row['source'] ?? '');
            $detail = (string) ($row['detail'] ?? '');
            $setup = (array) ($row['setup'] ?? array());
            $setup_label = (string) ($setup['label'] ?? __('No availability setup yet', 'backstage-venue-manager'));
            $has_setup = !empty($setup['has_setup']);
            $next_item = isset($row['next_item']) && is_array($row['next_item']) ? $row['next_item'] : array();
            $next_date = isset($next_item['event_date']) ? (string) $next_item['event_date'] : '';
            $next_plan_label = isset($next_item['event_label']) ? (string) $next_item['event_label'] : '';
            $booking_links = vms_vendor_availability_booking_links((array) $row, $date, $filters);
            $booking_url = (string) ($booking_links['url'] ?? '');
            $override_booking_url = (string) ($booking_links['override_url'] ?? '');

            echo '<tr>';
            echo '<td>';
            if ($edit_link !== '') {
                echo '<a href="' . esc_url($edit_link) . '"><strong>' . esc_html($title) . '</strong></a>';
            } else {
                echo '<strong>' . esc_html($title) . '</strong>';
            }
            $post_status = sanitize_key((string) ($row['post_status'] ?? ''));
            if ($post_status !== 'publish' && $post_status !== '') {
                /* translators: %s: WordPress post status label. */
                echo '<div class="vms-va-subline">' . esc_html(sprintf(__('Record status: %s', 'backstage-venue-manager'), ucfirst($post_status))) . '</div>';
            }
            if (!empty($types)) {
                echo '<div class="vms-va-type-badges">';
                foreach ($types as $type) {
                    echo wp_kses_post(vms_vendor_availability_pill((string) $type, 'neutral')) . ' ';
                }
                echo '</div>';
            } else {
                echo '<div class="vms-va-type-badges">' . wp_kses_post(vms_vendor_availability_pill(__('Uncategorized', 'backstage-venue-manager'), 'neutral')) . '</div>';
            }
            echo '</td>';

            echo '<td>' . ($home_venue !== '' ? esc_html($home_venue) : '<span class="vms-va-muted">' . esc_html__('—', 'backstage-venue-manager') . '</span>') . '</td>';

            echo '<td>';
            $compact_label = $label !== '' ? $label : __('No reply', 'backstage-venue-manager');
            $compact_detail = $detail;
            if ($state === 'no-response') {
                if (!$has_setup) {
                    $compact_label = __('No response / no setup', 'backstage-venue-manager');
                    $compact_detail = '';
                } else {
                    $compact_label = __('No response', 'backstage-venue-manager');
                }
            }
            echo wp_kses_post(vms_vendor_availability_pill($compact_label, vms_vendor_availability_state_tone($state)));
            if ($source !== '' && !($state === 'no-response' && !$has_setup)) {
                echo '<div class="vms-va-subline"><strong>' . esc_html($source) . '</strong></div>';
            }
            if ($compact_detail !== '') {
                echo '<div class="vms-va-subline">' . esc_html($compact_detail) . '</div>';
            }
            if ($has_setup && $setup_label !== '' && stripos($compact_detail, $setup_label) === false) {
                /* translators: %s: vendor availability setup summary label. */
                echo '<div class="vms-va-subline">' . esc_html(sprintf(__('Setup: %s', 'backstage-venue-manager'), $setup_label)) . '</div>';
            }
            echo '</td>';

            echo '<td>';
            if ($next_date !== '') {
                echo '<div><strong>' . esc_html(date_i18n(get_option('date_format'), strtotime($next_date))) . '</strong></div>';
                if ($next_plan_label !== '') {
                    echo '<div class="vms-va-subline">' . esc_html($next_plan_label) . '</div>';
                }
            } else {
                echo '<span class="vms-va-muted">' . esc_html__('No future date', 'backstage-venue-manager') . '</span>';
            }
            echo '</td>';

            echo '<td>';
            echo '<div class="vms-va-actions">';
            if ($booking_url !== '') {
                echo '<a class="button button-primary button-small" href="' . esc_url($booking_url) . '">' . esc_html__('Start booking', 'backstage-venue-manager') . '</a>';
            } elseif ($override_booking_url !== '') {
                echo '<span class="vms-va-muted">' . esc_html__('Venue closed', 'backstage-venue-manager') . '</span>';
                echo '<a class="button button-small" href="' . esc_url($override_booking_url) . '">' . esc_html__('Override venue schedule and book anyway', 'backstage-venue-manager') . '</a>';
            }
            if ($edit_link !== '') {
                echo '<a class="button button-small" href="' . esc_url($edit_link) . '">' . esc_html__('Edit vendor', 'backstage-venue-manager') . '</a>';
            }
            echo '</div>';
            echo '</td>';
            echo '</tr>';
        }
        }

        echo '</tbody></table>';
        echo '</div>';
        echo '</div>';
    }
}

if (!function_exists('vms_vendor_availability_register_tours')) {
    /**
     * @param array<int,array<string,mixed>> $tours
     * @return array<int,array<string,mixed>>
     */
    function vms_vendor_availability_register_tours(array $tours): array
    {
        $tours[] = array(
            'id' => 'vms.vendor_availability.basics',
            'title' => __('Vendor Availability', 'backstage-venue-manager'),
            'screen' => 'admin:' . vms_vendor_availability_page_slug(),
            'version' => '1.0.0',
            'level' => 'beginner',
            'audience' => array('admin'),
            'steps' => array(
                array(
                    'id' => 'vendor-availability-help',
                    'title' => __('What this board is for', 'backstage-venue-manager'),
                    'selector' => '[data-vms-tour="vendor-availability.help"]',
                    'body' => __('Use this board to answer one practical question quickly: who is truly available on a given date, who is only tentative, who is already booked, and who still has not set availability at all.', 'backstage-venue-manager'),
                    'position' => 'bottom',
                ),
                array(
                    'id' => 'vendor-availability-filters',
                    'title' => __('Filter the roster before you scan', 'backstage-venue-manager'),
                    'selector' => '[data-vms-tour="vendor-availability.filters"]',
                    'body' => __('Narrow by date, type, venue, setup state, or status first. That keeps the board useful when your roster grows and avoids chasing the wrong vendors.', 'backstage-venue-manager'),
                    'position' => 'bottom',
                ),
                array(
                    'id' => 'vendor-availability-summary',
                    'title' => __('Read the day snapshot', 'backstage-venue-manager'),
                    'selector' => '[data-vms-tour="vendor-availability.summary"]',
                    'body' => __('These counts are for the selected detail date and the currently filtered vendor set. This gives you a quick staffing-style scan before you open the detailed table.', 'backstage-venue-manager'),
                    'position' => 'bottom',
                ),
                array(
                    'id' => 'vendor-availability-month',
                    'title' => __('Use month view to spot openings', 'backstage-venue-manager'),
                    'selector' => '[data-vms-tour="vendor-availability.month"]',
                    'body' => __('Month view is for fast scanning. Each day now leads with vendor names so you can answer “who is open?” immediately, then expand the cell for the full day roster when needed.', 'backstage-venue-manager'),
                    'position' => 'top',
                ),
                array(
                    'id' => 'vendor-availability-list',
                    'title' => __('Use list view to see the why', 'backstage-venue-manager'),
                    'selector' => '[data-vms-tour="vendor-availability.list"]',
                    'body' => __('The detailed table explains the reason behind each status. That matters because “booked,” “tentative,” “manual unavailable,” “pattern blocked,” and “no reply” all need different follow-up actions.', 'backstage-venue-manager'),
                    'position' => 'top',
                ),
            ),
        );

        return $tours;
    }
}
add_filter('vms_tours_register', 'vms_vendor_availability_register_tours');
