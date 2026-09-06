<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('vms_dt_register_reporting_pages')) {
    function vms_dt_register_reporting_pages(): void
    {
        $cap = function_exists('vms_dt_manage_capability') ? vms_dt_manage_capability() : 'manage_options';

        add_submenu_page(
            'vms-data-tools',
            __('Single Event Report', 'vms-data-tools'),
            __('Single Event', 'vms-data-tools'),
            $cap,
            vms_dt_get_menu_slug_reporting_single_event(),
            'vms_dt_render_reporting_single_event_page'
        );

        add_submenu_page(
            'vms-data-tools',
            __('Compare Events', 'vms-data-tools'),
            __('Compare Events', 'vms-data-tools'),
            $cap,
            vms_dt_get_menu_slug_reporting_compare_events(),
            'vms_dt_render_reporting_compare_events_page'
        );

        add_submenu_page(
            'vms-data-tools',
            __('Season / Year Report', 'vms-data-tools'),
            __('Season / Year', 'vms-data-tools'),
            $cap,
            vms_dt_get_menu_slug_reporting_season_year(),
            'vms_dt_render_reporting_season_year_page'
        );

        add_submenu_page(
            'vms-data-tools',
            __('Performer Payouts', 'vms-data-tools'),
            __('Performer Payouts', 'vms-data-tools'),
            $cap,
            vms_dt_get_menu_slug_reporting_performer_payouts(),
            'vms_dt_render_reporting_performer_payouts_page'
        );

        add_submenu_page(
            'vms-data-tools',
            __('Event Profitability', 'vms-data-tools'),
            __('Event Profitability', 'vms-data-tools'),
            $cap,
            vms_dt_get_menu_slug_reporting_profitability(),
            'vms_dt_render_reporting_profitability_page'
        );

        add_submenu_page(
            'vms-data-tools',
            __('Ticket Pace', 'vms-data-tools'),
            __('Ticket Pace', 'vms-data-tools'),
            $cap,
            vms_dt_get_menu_slug_reporting_ticket_pace(),
            'vms_dt_render_reporting_ticket_pace_page'
        );

        global $submenu;
        if (isset($submenu['vms-data-tools']) && is_array($submenu['vms-data-tools'])) {
            foreach ($submenu['vms-data-tools'] as &$item) {
                if (!is_array($item) || empty($item[2])) {
                    continue;
                }
                if ((string) $item[2] === vms_dt_get_menu_slug_revenue_intelligence()) {
                    $item[0] = __('Audit Tools', 'vms-data-tools');
                    break;
                }
            }
            unset($item);
        }
    }
}

if (!function_exists('vms_dt_reporting_nav')) {
    function vms_dt_reporting_nav(string $active_slug): void
    {
        $items = array(
            vms_dt_get_menu_slug_reporting_single_event()      => __('Single Event', 'vms-data-tools'),
            vms_dt_get_menu_slug_reporting_compare_events()    => __('Compare Events', 'vms-data-tools'),
            vms_dt_get_menu_slug_reporting_season_year()       => __('Season / Year', 'vms-data-tools'),
            vms_dt_get_menu_slug_reporting_performer_payouts() => __('Performer Payouts', 'vms-data-tools'),
            vms_dt_get_menu_slug_reporting_profitability()     => __('Event Profitability', 'vms-data-tools'),
            vms_dt_get_menu_slug_reporting_ticket_pace()       => __('Ticket Pace', 'vms-data-tools'),
            vms_dt_get_menu_slug_revenue_intelligence()        => __('Audit Tools', 'vms-data-tools'),
        );
        echo '<div class="vms-dt-toolbar vms-dt-section">';
        foreach ($items as $slug => $label) {
            $class = 'button' . ($slug === $active_slug ? ' button-primary' : '');
            echo '<a class="' . esc_attr($class) . '" href="' . esc_url(vms_dt_admin_url($slug)) . '">' . esc_html($label) . '</a>';
        }
        echo '</div>';
    }
}

if (!function_exists('vms_dt_reporting_parse_bytes')) {
    function vms_dt_reporting_parse_bytes($value): int
    {
        if (is_int($value)) {
            return max(0, $value);
        }

        $value = trim((string) $value);
        if ($value === '' || $value === '-1') {
            return 0;
        }
        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        $unit = strtolower(substr($value, -1));
        $bytes = (float) $value;
        switch ($unit) {
            case 'g':
                $bytes *= 1024;
                // no break
            case 'm':
                $bytes *= 1024;
                // no break
            case 'k':
                $bytes *= 1024;
                break;
        }

        return max(0, (int) round($bytes));
    }
}

if (!function_exists('vms_dt_reporting_memory_limit_bytes')) {
    function vms_dt_reporting_memory_limit_bytes(): int
    {
        $limits = array();
        if (defined('WP_MEMORY_LIMIT')) {
            $limits[] = vms_dt_reporting_parse_bytes(WP_MEMORY_LIMIT);
        }
        $limits[] = vms_dt_reporting_parse_bytes(ini_get('memory_limit'));
        $limits = array_values(array_filter(array_map('intval', $limits)));
        return !empty($limits) ? max($limits) : 0;
    }
}

if (!function_exists('vms_dt_reporting_memory_guard_bytes')) {
    function vms_dt_reporting_memory_guard_bytes(): int
    {
        $limit = vms_dt_reporting_memory_limit_bytes();
        if ($limit <= 0) {
            return 192 * 1024 * 1024;
        }

        $soft_limit = (int) floor($limit * 0.70);
        $hard_headroom = $limit - (24 * 1024 * 1024);
        if ($hard_headroom > 0) {
            $soft_limit = min($soft_limit, $hard_headroom);
        }

        $soft_limit = max(96 * 1024 * 1024, $soft_limit);
        return (int) apply_filters('vms_dt_reporting_memory_guard_bytes', $soft_limit, $limit);
    }
}

if (!function_exists('vms_dt_reporting_trace')) {
    function vms_dt_reporting_trace(string $hook_name, string $decision, array $context = array(), float $started_at = 0.0): void
    {
        $hook_name = sanitize_key($hook_name);
        if ($hook_name === '') {
            $hook_name = 'reporting';
        }

        $elapsed_ms = $started_at > 0 ? max(0.0, round((microtime(true) - $started_at) * 1000, 1)) : 0.0;
        $screen_id = vms_dt_has_core_function('vms_admin_guard_current_screen_id') ? vms_dt_call_core_function('vms_admin_guard_current_screen_id') : '';
        $request_uri = vms_dt_has_core_function('vms_admin_guard_request_uri')
            ? vms_dt_call_core_function('vms_admin_guard_request_uri')
            : trim((string) ($_SERVER['REQUEST_URI'] ?? ''));

        $payload = array(
            'hook' => $hook_name,
            'decision' => sanitize_key($decision),
            'request_uri' => $request_uri,
            'screen_id' => $screen_id,
            'elapsed_ms' => $elapsed_ms,
            'memory_mb' => round(((int) memory_get_usage(true)) / 1048576, 1),
        );

        foreach ($context as $key => $value) {
            $payload[sanitize_key((string) $key)] = is_scalar($value) ? $value : wp_json_encode($value);
        }

        if (vms_dt_has_core_function('vms_resource_fingerprint_flag')) {
            vms_dt_call_core_function('vms_resource_fingerprint_flag', 'dt_admin_trace', $payload);
        }
        if (vms_dt_has_core_function('vms_resource_fingerprint_add_marker')) {
            vms_dt_call_core_function('vms_resource_fingerprint_add_marker', 'dt.' . $hook_name, $elapsed_ms, $payload);
        }

        error_log('[VMS DT TRACE] ' . wp_json_encode($payload));
    }
}

if (!function_exists('vms_dt_reporting_current_request_args')) {
    function vms_dt_reporting_current_request_args(): array
    {
        $args = array();
        foreach ((array) $_GET as $key => $value) {
            $key = sanitize_key((string) $key);
            if ($key === '') {
                continue;
            }
            if (is_array($value)) {
                $clean = array();
                foreach ($value as $item) {
                    if (is_scalar($item)) {
                        $clean[] = sanitize_text_field((string) wp_unslash($item));
                    }
                }
                $args[$key] = $clean;
                continue;
            }

            $args[$key] = sanitize_text_field((string) wp_unslash($value));
        }
        return $args;
    }
}

if (!function_exists('vms_dt_reporting_current_request_url')) {
    function vms_dt_reporting_current_request_url(array $overrides = array(), array $remove = array()): string
    {
        $args = vms_dt_reporting_current_request_args();
        foreach ($remove as $key) {
            unset($args[sanitize_key((string) $key)]);
        }

        foreach ($overrides as $key => $value) {
            $key = sanitize_key((string) $key);
            if ($key === '') {
                continue;
            }
            if ($value === null) {
                unset($args[$key]);
                continue;
            }
            $args[$key] = $value;
        }

        return add_query_arg($args, admin_url('admin.php'));
    }
}

if (!function_exists('vms_dt_reporting_ticket_pace_mode')) {
    function vms_dt_reporting_ticket_pace_mode(array $effective_source = array(), int $compare_event_id = 0): string
    {
        if ($compare_event_id > 0) {
            return 'full';
        }

        $mode = sanitize_key((string) ($effective_source['vms_dt_pace_mode'] ?? ($_GET['vms_dt_pace_mode'] ?? '')));
        return $mode === 'full' ? 'full' : 'summary';
    }
}

if (!function_exists('vms_dt_reporting_ticket_pace_should_load_history')) {
    function vms_dt_reporting_ticket_pace_should_load_history(string $pace_mode, int $compare_event_id = 0): bool
    {
        return $compare_event_id > 0 || sanitize_key($pace_mode) === 'full';
    }
}

if (!function_exists('vms_dt_reporting_ticket_pace_mode_url')) {
    function vms_dt_reporting_ticket_pace_mode_url(string $pace_mode): string
    {
        $pace_mode = sanitize_key($pace_mode);
        if ($pace_mode !== 'full') {
            return vms_dt_reporting_current_request_url(array(), array('vms_dt_pace_mode'));
        }

        return vms_dt_reporting_current_request_url(array('vms_dt_pace_mode' => 'full'));
    }
}

if (!function_exists('vms_dt_reporting_enabled_detail_sections')) {
    function vms_dt_reporting_enabled_detail_sections(): array
    {
        $raw = $_GET['vms_dt_detail'] ?? array();
        $sections = array();
        if (is_array($raw)) {
            foreach ($raw as $item) {
                $item = sanitize_key((string) wp_unslash($item));
                if ($item !== '') {
                    $sections[] = $item;
                }
            }
        } else {
            $parts = preg_split('/[\s,]+/', (string) wp_unslash($raw)) ?: array();
            foreach ($parts as $item) {
                $item = sanitize_key((string) $item);
                if ($item !== '') {
                    $sections[] = $item;
                }
            }
        }

        $sections = array_values(array_unique($sections));
        return $sections;
    }
}

if (!function_exists('vms_dt_reporting_is_detail_enabled')) {
    function vms_dt_reporting_is_detail_enabled(string $section): bool
    {
        $section = sanitize_key($section);
        if ($section === '') {
            return false;
        }
        $enabled = vms_dt_reporting_enabled_detail_sections();
        return in_array('all', $enabled, true) || in_array($section, $enabled, true);
    }
}

if (!function_exists('vms_dt_reporting_section_toggle_url')) {
    function vms_dt_reporting_section_toggle_url(string $section, bool $enabled): string
    {
        $section = sanitize_key($section);
        $current = vms_dt_reporting_enabled_detail_sections();
        if ($enabled) {
            $current[] = $section;
        } else {
            $current = array_values(array_diff($current, array($section)));
        }
        $current = array_values(array_unique(array_filter($current)));
        if (empty($current)) {
            return vms_dt_reporting_current_request_url(array(), array('vms_dt_detail'));
        }
        return vms_dt_reporting_current_request_url(array('vms_dt_detail' => implode(',', $current)));
    }
}

if (!function_exists('vms_dt_reporting_support_link_url')) {
    function vms_dt_reporting_support_link_url(string $anchor, string $detail_section = 'single_event_supporting'): string
    {
        $anchor = trim($anchor);
        $detail_section = sanitize_key($detail_section);
        if ($anchor === '') {
            return '#';
        }

        if ($detail_section !== '' && !vms_dt_reporting_is_detail_enabled($detail_section)) {
            return vms_dt_reporting_section_toggle_url($detail_section, true) . '#' . rawurlencode($anchor);
        }

        return '#' . $anchor;
    }
}

if (!function_exists('vms_dt_reporting_render_deferred_detail_notice')) {
    function vms_dt_reporting_render_deferred_detail_notice(string $section, string $label, int $row_count = 0): void
    {
        $load_url = vms_dt_reporting_section_toggle_url($section, true);
        echo '<div class="notice notice-info inline"><p>' . esc_html(sprintf(__('Row-level %s is deferred by default on this admin page to avoid memory spikes. Load it only when needed.', 'vms-data-tools'), $label)) . '</p>';
        if ($row_count > 0) {
            echo '<p class="description">' . esc_html(sprintf(__('Current dataset size: %s rows.', 'vms-data-tools'), number_format($row_count))) . '</p>';
        }
        echo '<p><a class="button" href="' . esc_url($load_url) . '">' . esc_html__('Load row detail', 'vms-data-tools') . '</a></p></div>';
    }
}

if (!function_exists('vms_dt_reporting_rows_per_page')) {
    function vms_dt_reporting_rows_per_page(string $table_id, int $default = 100): int
    {
        $rows = (int) apply_filters('vms_dt_reporting_rows_per_page', $default, sanitize_key($table_id));
        return max(25, min(250, $rows));
    }
}

if (!function_exists('vms_dt_reporting_paginate_rows')) {
    function vms_dt_reporting_paginate_rows(string $table_id, array $rows, int $default = 100, bool $default_to_last_page = false): array
    {
        $table_id = sanitize_key($table_id);
        $page_arg = 'vms_dt_page_' . $table_id;
        $per_page = vms_dt_reporting_rows_per_page($table_id, $default);
        $total_rows = count($rows);
        $total_pages = max(1, (int) ceil($total_rows / max(1, $per_page)));
        $requested_page = isset($_GET[$page_arg]) ? max(0, absint(wp_unslash($_GET[$page_arg]))) : 0;
        $current_page = $requested_page > 0 ? min($requested_page, $total_pages) : ($default_to_last_page ? $total_pages : 1);
        $offset = max(0, ($current_page - 1) * $per_page);

        return array(
            'rows' => array_slice($rows, $offset, $per_page),
            'page_arg' => $page_arg,
            'current_page' => $current_page,
            'total_pages' => $total_pages,
            'per_page' => $per_page,
            'total_rows' => $total_rows,
            'offset' => $offset,
        );
    }
}

if (!function_exists('vms_dt_reporting_render_pagination_controls')) {
    function vms_dt_reporting_render_pagination_controls(array $pagination): void
    {
        $total_rows = (int) ($pagination['total_rows'] ?? 0);
        $per_page = (int) ($pagination['per_page'] ?? 0);
        $page_arg = sanitize_key((string) ($pagination['page_arg'] ?? ''));
        $current_page = max(1, (int) ($pagination['current_page'] ?? 1));
        $total_pages = max(1, (int) ($pagination['total_pages'] ?? 1));
        $offset = max(0, (int) ($pagination['offset'] ?? 0));
        if ($total_rows <= 0 || $per_page <= 0) {
            return;
        }

        $start = $offset + 1;
        $end = min($total_rows, $offset + $per_page);
        echo '<div class="vms-dt-toolbar"><div class="vms-dt-toolbar-left"><span class="description">' . esc_html(sprintf(__('Showing rows %1$s-%2$s of %3$s.', 'vms-data-tools'), number_format($start), number_format($end), number_format($total_rows))) . '</span></div>';
        if ($total_pages > 1 && $page_arg !== '') {
            $prev_url = $current_page > 1
                ? ($current_page === 2
                    ? vms_dt_reporting_current_request_url(array(), array($page_arg))
                    : vms_dt_reporting_current_request_url(array($page_arg => $current_page - 1)))
                : '';
            $next_url = $current_page < $total_pages
                ? vms_dt_reporting_current_request_url(array($page_arg => $current_page + 1))
                : '';

            echo '<div class="vms-dt-toolbar-right">';
            if ($prev_url !== '') {
                echo '<a class="button" href="' . esc_url($prev_url) . '">' . esc_html__('Previous page', 'vms-data-tools') . '</a> ';
            }
            echo '<span class="description">' . esc_html(sprintf(__('Page %1$s of %2$s', 'vms-data-tools'), number_format($current_page), number_format($total_pages))) . '</span>';
            if ($next_url !== '') {
                echo ' <a class="button" href="' . esc_url($next_url) . '">' . esc_html__('Next page', 'vms-data-tools') . '</a>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
}

if (!function_exists('vms_dt_reporting_memory_guard_should_skip')) {
    function vms_dt_reporting_memory_guard_should_skip(string $hook_name, array $context = array()): bool
    {
        $guard_bytes = vms_dt_reporting_memory_guard_bytes();
        $usage_bytes = (int) memory_get_usage(true);
        if ($guard_bytes <= 0 || $usage_bytes < $guard_bytes) {
            return false;
        }

        $context['guard_mb'] = round($guard_bytes / 1048576, 1);
        $context['memory_mb'] = round($usage_bytes / 1048576, 1);
        $context['reason'] = 'memory_guard';
        vms_dt_reporting_trace($hook_name, 'skipped', $context);
        return true;
    }
}

if (!function_exists('vms_dt_reporting_latest_event_plan_id')) {
    function vms_dt_reporting_latest_event_plan_id(): int
    {
        $events = function_exists('vms_dt_rr_get_event_options') ? vms_dt_rr_get_event_options(1) : array();
        if (!empty($events[0]['id'])) {
            return (int) $events[0]['id'];
        }
        return 0;
    }
}

if (!function_exists('vms_dt_reporting_memory_key')) {
    function vms_dt_reporting_memory_key(string $page_slug): string
    {
        return '_vms_dt_reporting_last_filters_' . sanitize_key($page_slug);
    }
}

if (!function_exists('vms_dt_reporting_memory_fields')) {
    function vms_dt_reporting_memory_fields(string $page_slug): array
    {
        switch ($page_slug) {
            case 'vms-dt-report-season-year':
                return array('event_from', 'event_to', 'venue_id');
            case 'vms-dt-report-compare-events':
                return array('event_plan_id', 'event_plan_b', 'square_scope_mode');
            case 'vms-dt-report-single-event':
            case 'vms-dt-report-performer-payouts':
                return array('event_plan_id', 'square_location_id', 'square_scope_mode', 'sold_from', 'sold_to');
            case 'vms-dt-report-ticket-pace':
                return array('event_plan_id', 'event_plan_b', 'square_location_id', 'square_scope_mode', 'sold_from', 'sold_to');
            case 'vms-dt-report-profitability':
                return array('venue_id', 'square_location_id', 'square_scope_mode', 'sold_from', 'sold_to', 'profit_window', 'report_search', 'profit_sort');
            default:
                return array();
        }
    }
}

if (!function_exists('vms_dt_reporting_request_has_memory_fields')) {
    function vms_dt_reporting_request_has_memory_fields(string $page_slug, ?array $source = null): bool
    {
        $source = is_array($source) ? $source : $_GET;
        foreach (vms_dt_reporting_memory_fields($page_slug) as $key) {
            if (array_key_exists($key, $source)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('vms_dt_reporting_extract_memory_filters')) {
    function vms_dt_reporting_extract_memory_filters(string $page_slug, ?array $source = null): array
    {
        $source = is_array($source) ? $source : $_GET;
        $filters = function_exists('vms_dt_rr_get_filters') ? vms_dt_rr_get_filters($source) : array();
        $saved = array();

        foreach (vms_dt_reporting_memory_fields($page_slug) as $key) {
            if (array_key_exists($key, $filters)) {
                $saved[$key] = $filters[$key];
            }
        }

        if (in_array('event_plan_b', vms_dt_reporting_memory_fields($page_slug), true)) {
            $saved['event_plan_b'] = isset($source['event_plan_b']) ? max(0, (int) wp_unslash($source['event_plan_b'])) : 0;
        }

        return $saved;
    }
}

if (!function_exists('vms_dt_reporting_get_memory_filters')) {
    function vms_dt_reporting_get_memory_filters(string $page_slug): array
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return array();
        }
        $saved = get_user_meta($user_id, vms_dt_reporting_memory_key($page_slug), true);
        return is_array($saved) ? $saved : array();
    }
}

if (!function_exists('vms_dt_reporting_remember_filters')) {
    function vms_dt_reporting_remember_filters(string $page_slug, ?array $source = null): void
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return;
        }
        update_user_meta($user_id, vms_dt_reporting_memory_key($page_slug), vms_dt_reporting_extract_memory_filters($page_slug, $source));
    }
}

if (!function_exists('vms_dt_reporting_effective_source')) {
    function vms_dt_reporting_effective_source(string $page_slug, ?array $source = null): array
    {
        $source = is_array($source) ? $source : $_GET;
        if (vms_dt_reporting_request_has_memory_fields($page_slug, $source)) {
            vms_dt_reporting_remember_filters($page_slug, $source);
            return $source;
        }

        $saved = vms_dt_reporting_get_memory_filters($page_slug);
        if (empty($saved)) {
            return $source;
        }

        foreach (vms_dt_reporting_memory_fields($page_slug) as $key) {
            if (!array_key_exists($key, $source) && array_key_exists($key, $saved)) {
                $source[$key] = $saved[$key];
            }
        }
        if (in_array('event_plan_b', vms_dt_reporting_memory_fields($page_slug), true) && !array_key_exists('event_plan_b', $source) && array_key_exists('event_plan_b', $saved)) {
            $source['event_plan_b'] = $saved['event_plan_b'];
        }
        return $source;
    }
}


if (!function_exists('vms_dt_reporting_eventbrite_option_name')) {
    function vms_dt_reporting_eventbrite_option_name(): string
    {
        return 'vms_dt_reporting_eventbrite_benchmark_v1';
    }
}

if (!function_exists('vms_dt_reporting_eventbrite_defaults')) {
    function vms_dt_reporting_eventbrite_defaults(): array
    {
        return array(
            'service_fee_percent' => 3.7,
            'service_fee_per_paid_ticket' => 1.79,
            'processing_fee_percent' => 2.9,
        );
    }
}

if (!function_exists('vms_dt_reporting_decimal_from_input')) {
    function vms_dt_reporting_decimal_from_input($value, int $scale = 4): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '', wp_unslash($value));
        }
        if (!is_numeric($value)) {
            return 0.0;
        }
        $value = (float) $value;
        if ($value < 0) {
            $value = 0.0;
        }
        return round($value, $scale);
    }
}

if (!function_exists('vms_dt_reporting_sanitize_eventbrite_settings')) {
    function vms_dt_reporting_sanitize_eventbrite_settings($raw): array
    {
        $defaults = vms_dt_reporting_eventbrite_defaults();
        $raw = is_array($raw) ? $raw : array();

        $service_fee_percent = array_key_exists('service_fee_percent', $raw)
            ? vms_dt_reporting_decimal_from_input($raw['service_fee_percent'], 4)
            : (float) $defaults['service_fee_percent'];
        $service_fee_per_paid_ticket = array_key_exists('service_fee_per_paid_ticket', $raw)
            ? vms_dt_reporting_decimal_from_input($raw['service_fee_per_paid_ticket'], 2)
            : (float) $defaults['service_fee_per_paid_ticket'];
        $processing_fee_percent = array_key_exists('processing_fee_percent', $raw)
            ? vms_dt_reporting_decimal_from_input($raw['processing_fee_percent'], 4)
            : (float) $defaults['processing_fee_percent'];

        return array(
            'service_fee_percent' => max(0.0, min(100.0, $service_fee_percent)),
            'service_fee_per_paid_ticket' => max(0.0, $service_fee_per_paid_ticket),
            'processing_fee_percent' => max(0.0, min(100.0, $processing_fee_percent)),
        );
    }
}

if (!function_exists('vms_dt_reporting_get_eventbrite_settings')) {
    function vms_dt_reporting_get_eventbrite_settings(): array
    {
        $raw = get_option(vms_dt_reporting_eventbrite_option_name(), array());
        $settings = vms_dt_reporting_sanitize_eventbrite_settings($raw);
        return array_merge(vms_dt_reporting_eventbrite_defaults(), $settings);
    }
}

if (!function_exists('vms_dt_reporting_format_percent')) {
    function vms_dt_reporting_format_percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
}

if (!function_exists('vms_dt_reporting_eventbrite_benchmark_label')) {
    function vms_dt_reporting_eventbrite_benchmark_label(array $settings = array()): string
    {
        $settings = !empty($settings) ? $settings : vms_dt_reporting_get_eventbrite_settings();
        return sprintf(
            __('%1$s%% service fee + %2$s per paid ticket, plus %3$s%% processing on paid ticket sales', 'vms-data-tools'),
            vms_dt_reporting_format_percent((float) ($settings['service_fee_percent'] ?? 0.0)),
            vms_dt_rr_money((int) round(((float) ($settings['service_fee_per_paid_ticket'] ?? 0.0)) * 100)),
            vms_dt_reporting_format_percent((float) ($settings['processing_fee_percent'] ?? 0.0))
        );
    }
}

if (!function_exists('vms_dt_reporting_maybe_save_eventbrite_settings')) {
    function vms_dt_reporting_maybe_save_eventbrite_settings(): string
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return '';
        }
        if (empty($_POST['vms_dt_eventbrite_save_settings'])) {
            return '';
        }
        if (!vms_dt_current_user_can_manage_tools()) {
            return '';
        }
        check_admin_referer('vms_dt_reporting_save_eventbrite_settings', 'vms_dt_eventbrite_nonce');
        $raw = isset($_POST['eventbrite_benchmark']) ? (array) wp_unslash($_POST['eventbrite_benchmark']) : array();
        $settings = vms_dt_reporting_sanitize_eventbrite_settings($raw);
        update_option(vms_dt_reporting_eventbrite_option_name(), $settings, false);
        return __('Eventbrite benchmark settings saved.', 'vms-data-tools');
    }
}

if (!function_exists('vms_dt_reporting_render_eventbrite_settings_card')) {
    function vms_dt_reporting_render_eventbrite_settings_card(): void
    {
        $settings = vms_dt_reporting_get_eventbrite_settings();
        ?>
        <form method="post" action="" class="vms-dt-card vms-dt-section">
            <div class="vms-dt-card-head">
                <div>
                    <h2><?php esc_html_e('Eventbrite benchmark', 'vms-data-tools'); ?></h2>
                    <p class="vms-dt-section-desc"><?php esc_html_e('These legacy benchmark numbers drive the savings estimate cards below. Free / comp / guest admissions stay out of this math.', 'vms-data-tools'); ?></p>
                </div>
            </div>
            <div class="vms-dt-filter-grid">
                <div class="vms-dt-field">
                    <label for="vms-dt-eventbrite-service-fee-percent"><?php esc_html_e('Service fee %', 'vms-data-tools'); ?></label>
                    <input id="vms-dt-eventbrite-service-fee-percent" type="number" step="0.0001" min="0" name="eventbrite_benchmark[service_fee_percent]" value="<?php echo esc_attr((string) ($settings['service_fee_percent'] ?? 0)); ?>" />
                </div>
                <div class="vms-dt-field">
                    <label for="vms-dt-eventbrite-service-fee-ticket"><?php esc_html_e('Service fee per paid ticket', 'vms-data-tools'); ?></label>
                    <input id="vms-dt-eventbrite-service-fee-ticket" type="number" step="0.01" min="0" name="eventbrite_benchmark[service_fee_per_paid_ticket]" value="<?php echo esc_attr((string) ($settings['service_fee_per_paid_ticket'] ?? 0)); ?>" />
                </div>
                <div class="vms-dt-field">
                    <label for="vms-dt-eventbrite-processing-fee-percent"><?php esc_html_e('Processing fee %', 'vms-data-tools'); ?></label>
                    <input id="vms-dt-eventbrite-processing-fee-percent" type="number" step="0.0001" min="0" name="eventbrite_benchmark[processing_fee_percent]" value="<?php echo esc_attr((string) ($settings['processing_fee_percent'] ?? 0)); ?>" />
                </div>
            </div>
            <?php wp_nonce_field('vms_dt_reporting_save_eventbrite_settings', 'vms_dt_eventbrite_nonce'); ?>
            <div class="vms-dt-toolbar">
                <div class="vms-dt-toolbar-left">
                    <button type="submit" name="vms_dt_eventbrite_save_settings" value="1" class="button button-secondary"><?php esc_html_e('Save benchmark', 'vms-data-tools'); ?></button>
                </div>
                <div class="vms-dt-toolbar-right">
                    <span class="description"><?php echo esc_html(vms_dt_reporting_eventbrite_benchmark_label($settings)); ?></span>
                </div>
            </div>
        </form>
        <?php
    }
}

if (!function_exists('vms_dt_reporting_zero_eventbrite_savings')) {
    function vms_dt_reporting_zero_eventbrite_savings(): array
    {
        return array(
            'settings' => vms_dt_reporting_get_eventbrite_settings(),
            'website_paid_ticket_qty' => 0,
            'website_paid_ticket_sales_cents' => 0,
            'website_paid_order_count' => 0,
            'door_paid_ticket_qty' => 0,
            'door_paid_ticket_sales_cents' => 0,
            'door_paid_order_count' => 0,
            'paid_ticket_qty_total' => 0,
            'paid_ticket_sales_cents_total' => 0,
            'paid_order_count_total' => 0,
            'excluded_free_ticket_qty' => 0,
            'service_fee_percent_cents' => 0,
            'service_fee_per_ticket_cents' => 0,
            'service_fee_total_cents' => 0,
            'processing_fee_total_cents' => 0,
            'total_avoided_fees_cents' => 0,
        );
    }
}

if (!function_exists('vms_dt_reporting_calculate_eventbrite_savings')) {
    function vms_dt_reporting_calculate_eventbrite_savings(array $row, array $evidence = array()): array
    {
        $result = vms_dt_reporting_zero_eventbrite_savings();
        $settings = $result['settings'];
        $website = isset($evidence['website']) && is_array($evidence['website']) ? (array) $evidence['website'] : array();
        $square = isset($evidence['square']) && is_array($evidence['square']) ? (array) $evidence['square'] : array();
        $website_ticket_rows = isset($website['ticket_rows']) && is_array($website['ticket_rows']) ? (array) $website['ticket_rows'] : array();
        $square_ticket_rows = isset($square['ticket_rows']) && is_array($square['ticket_rows']) ? (array) $square['ticket_rows'] : array();

        $website_orders = array();
        foreach ($website_ticket_rows as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $qty = max(0, (int) ($entry['quantity'] ?? 0) - (int) ($entry['refunded_quantity'] ?? 0));
            $net_cents = max(0, (int) ($entry['net_subtotal_cents'] ?? 0));
            if ($qty <= 0) {
                continue;
            }
            if ($net_cents > 0) {
                $result['website_paid_ticket_qty'] += $qty;
                $result['website_paid_ticket_sales_cents'] += $net_cents;
                $order_id = trim((string) ($entry['order_id'] ?? ''));
                if ($order_id !== '') {
                    $website_orders[$order_id] = true;
                }
            } else {
                $result['excluded_free_ticket_qty'] += $qty;
            }
        }
        $result['website_paid_order_count'] = count($website_orders);

        $door_orders = array();
        foreach ($square_ticket_rows as $entry) {
            if (!is_array($entry) || (($entry['treatment'] ?? '') !== 'counted') || empty($entry['is_direct_ticket'])) {
                continue;
            }
            $qty = max(0, (int) ($entry['quantity'] ?? 0));
            $net_cents = max(0, ((int) ($entry['gross_cents'] ?? 0)) - ((int) ($entry['tax_cents'] ?? 0)));
            if ($qty <= 0) {
                continue;
            }
            if ($net_cents > 0) {
                $result['door_paid_ticket_qty'] += $qty;
                $result['door_paid_ticket_sales_cents'] += $net_cents;
                $order_id = trim((string) ($entry['square_order_id'] ?? ''));
                if ($order_id !== '') {
                    $door_orders[$order_id] = true;
                }
            } else {
                $result['excluded_free_ticket_qty'] += $qty;
            }
        }
        $result['door_paid_order_count'] = count($door_orders);

        if (empty($website_ticket_rows)) {
            $result['website_paid_ticket_sales_cents'] = max(0, (int) ($row['website_ticket_net_cents'] ?? 0));
            $result['website_paid_ticket_qty'] = $result['website_paid_ticket_sales_cents'] > 0 ? max(0, (int) ($row['website_ticket_qty'] ?? 0)) : 0;
            $result['website_paid_order_count'] = max(0, (int) ($row['website_order_count'] ?? 0));
        }
        if (empty($square_ticket_rows)) {
            $result['door_paid_ticket_sales_cents'] = max(0, (int) ($row['square_direct_tickets_net_cents'] ?? ($row['square_direct_tickets_cents'] ?? 0)));
            $result['door_paid_ticket_qty'] = $result['door_paid_ticket_sales_cents'] > 0 ? max(0, (int) ($row['square_direct_ticket_qty'] ?? 0)) : 0;
        }

        $result['paid_ticket_qty_total'] = (int) $result['website_paid_ticket_qty'] + (int) $result['door_paid_ticket_qty'];
        $result['paid_ticket_sales_cents_total'] = (int) $result['website_paid_ticket_sales_cents'] + (int) $result['door_paid_ticket_sales_cents'];
        $result['paid_order_count_total'] = (int) $result['website_paid_order_count'] + (int) $result['door_paid_order_count'];

        $result['service_fee_percent_cents'] = (int) round(((int) $result['paid_ticket_sales_cents_total']) * (((float) ($settings['service_fee_percent'] ?? 0.0)) / 100));
        $result['service_fee_per_ticket_cents'] = (int) round(((float) ($settings['service_fee_per_paid_ticket'] ?? 0.0)) * 100) * (int) $result['paid_ticket_qty_total'];
        $result['service_fee_total_cents'] = (int) $result['service_fee_percent_cents'] + (int) $result['service_fee_per_ticket_cents'];
        $result['processing_fee_total_cents'] = (int) round(((int) $result['paid_ticket_sales_cents_total']) * (((float) ($settings['processing_fee_percent'] ?? 0.0)) / 100));
        $result['total_avoided_fees_cents'] = (int) $result['service_fee_total_cents'] + (int) $result['processing_fee_total_cents'];

        return $result;
    }
}

if (!function_exists('vms_dt_reporting_render_eventbrite_summary_section')) {
    function vms_dt_reporting_render_eventbrite_summary_section(array $eventbrite): void
    {
        $settings = isset($eventbrite['settings']) && is_array($eventbrite['settings']) ? (array) $eventbrite['settings'] : vms_dt_reporting_get_eventbrite_settings();
        ?>
        <div class="vms-dt-card vms-dt-section" id="vms-dt-detail-eventbrite-savings">
            <div class="vms-dt-card-head">
                <div>
                    <h2><?php esc_html_e('Estimated Eventbrite cost savings', 'vms-data-tools'); ?></h2>
                    <p class="vms-dt-section-desc"><?php echo esc_html(vms_dt_reporting_eventbrite_benchmark_label($settings)); ?></p>
                </div>
            </div>
            <div class="vms-dt-grid vms-dt-grid--cards">
                <div class="vms-dt-card"><p class="vms-dt-kpi-label"><?php esc_html_e('Estimated fees avoided', 'vms-data-tools'); ?></p><p class="vms-dt-kpi-value"><?php echo esc_html(vms_dt_rr_money((int) ($eventbrite['total_avoided_fees_cents'] ?? 0))); ?></p><p class="vms-dt-kpi-note"><?php echo esc_html(sprintf(__('%1$s paid tickets across %2$s paid orders', 'vms-data-tools'), number_format((int) ($eventbrite['paid_ticket_qty_total'] ?? 0)), number_format((int) ($eventbrite['paid_order_count_total'] ?? 0)))); ?></p></div>
                <div class="vms-dt-card"><p class="vms-dt-kpi-label"><?php esc_html_e('Paid ticket sales basis', 'vms-data-tools'); ?></p><p class="vms-dt-kpi-value"><?php echo esc_html(vms_dt_rr_money((int) ($eventbrite['paid_ticket_sales_cents_total'] ?? 0))); ?></p><p class="vms-dt-kpi-note"><?php esc_html_e('pre-tax paid ticket sales only', 'vms-data-tools'); ?></p></div>
                <div class="vms-dt-card"><p class="vms-dt-kpi-label"><?php esc_html_e('Service fee estimate', 'vms-data-tools'); ?></p><p class="vms-dt-kpi-value"><?php echo esc_html(vms_dt_rr_money((int) ($eventbrite['service_fee_total_cents'] ?? 0))); ?></p><p class="vms-dt-kpi-note"><?php echo esc_html(sprintf(__('%1$s percent component + %2$s per-ticket component', 'vms-data-tools'), vms_dt_rr_money((int) ($eventbrite['service_fee_percent_cents'] ?? 0)), vms_dt_rr_money((int) ($eventbrite['service_fee_per_ticket_cents'] ?? 0)))); ?></p></div>
                <div class="vms-dt-card"><p class="vms-dt-kpi-label"><?php esc_html_e('Processing fee estimate', 'vms-data-tools'); ?></p><p class="vms-dt-kpi-value"><?php echo esc_html(vms_dt_rr_money((int) ($eventbrite['processing_fee_total_cents'] ?? 0))); ?></p><p class="vms-dt-kpi-note"><?php echo esc_html(sprintf(__('%s processing applied to paid ticket sales basis', 'vms-data-tools'), vms_dt_reporting_format_percent((float) ($settings['processing_fee_percent'] ?? 0.0)) . '%')); ?></p></div>
            </div>
            <ul class="vms-dt-summary-list">
                <li><span class="vms-dt-summary-key"><?php esc_html_e('Website paid tickets', 'vms-data-tools'); ?></span><span class="vms-dt-summary-value"><?php echo esc_html(number_format((int) ($eventbrite['website_paid_ticket_qty'] ?? 0)) . ' • ' . vms_dt_rr_money((int) ($eventbrite['website_paid_ticket_sales_cents'] ?? 0))); ?></span></li>
                <li><span class="vms-dt-summary-key"><?php esc_html_e('Door paid tickets', 'vms-data-tools'); ?></span><span class="vms-dt-summary-value"><?php echo esc_html(number_format((int) ($eventbrite['door_paid_ticket_qty'] ?? 0)) . ' • ' . vms_dt_rr_money((int) ($eventbrite['door_paid_ticket_sales_cents'] ?? 0))); ?></span></li>
                <li><span class="vms-dt-summary-key"><?php esc_html_e('Excluded free / comp / guest qty', 'vms-data-tools'); ?></span><span class="vms-dt-summary-value"><?php echo esc_html(number_format((int) ($eventbrite['excluded_free_ticket_qty'] ?? 0))); ?></span></li>
            </ul>
            <p class="description"><?php esc_html_e('This is a benchmark estimate only. It intentionally excludes free / comp / guest admissions from the savings math so the investor-facing number stays tied to paid ticket activity.', 'vms-data-tools'); ?></p>
        </div>
        <?php
    }
}


if (!function_exists('vms_dt_reporting_build_event_filters')) {
    function vms_dt_reporting_build_event_filters(?array $source = null): array
    {
        $filters = function_exists('vms_dt_rr_get_filters') ? vms_dt_rr_get_filters($source) : array();
        $filters['compare'] = 0;
        if (empty($filters['event_plan_id'])) {
            $filters['event_plan_id'] = vms_dt_reporting_latest_event_plan_id();
        }
        if (!empty($filters['event_plan_id'])) {
            $event_date = (string) get_post_meta((int) $filters['event_plan_id'], '_vms_event_date', true);
            if ($event_date !== '') {
                $filters['event_from'] = $event_date;
                $filters['event_to'] = $event_date;
            }
        }
        return $filters;
    }
}

if (!function_exists('vms_dt_reporting_zero_row')) {
    function vms_dt_reporting_zero_row(): array
    {
        return array(
            'event_plan_id' => 0,
            'event_title' => '',
            'event_date' => '',
            'venue_name' => '',
            'status' => '',
            'website_ticket_net_cents' => 0,
            'website_ticket_tax_cents' => 0,
            'website_ticket_refunded_cents' => 0,
            'website_ticket_ordered_cents' => 0,
            'website_ticket_qty' => 0,
            'website_paid_ticket_qty' => 0,
            'website_free_ticket_qty' => 0,
            'website_addon_net_cents' => 0,
            'website_addon_tax_cents' => 0,
            'website_addon_refunded_cents' => 0,
            'website_addon_cents' => 0,
            'website_total_cents' => 0,
            'square_direct_tickets_cents' => 0,
            'square_direct_tickets_net_cents' => 0,
            'square_direct_ticket_qty' => 0,
            'square_paid_ticket_qty' => 0,
            'square_free_ticket_qty' => 0,
            'square_bar_cents' => 0,
            'square_food_cents' => 0,
            'square_merch_cents' => 0,
            'square_other_cents' => 0,
            'square_counted_total_cents' => 0,
            'square_overlap_excluded_cents' => 0,
            'square_unclassified_cents' => 0,
            'square_scope_line_total_cents' => 0,
            'square_scope_line_tax_cents' => 0,
            'square_scope_line_discount_cents' => 0,
            'square_scope_line_net_ex_tax_cents' => 0,
            'square_scope_tip_cents' => 0,
            'square_scope_service_cents' => 0,
            'square_scope_cash_cents' => 0,
            'square_scope_card_cents' => 0,
            'square_scope_other_tender_cents' => 0,
            'square_scope_total_collected_cents' => 0,
            'counted_total_cents' => 0,
        );
    }
}


if (!function_exists('vms_dt_reporting_zero_website_row')) {
    function vms_dt_reporting_zero_website_row(): array
    {
        return array(
            'website_ticket_cents' => 0,
            'website_addon_cents' => 0,
            'website_total_cents' => 0,
            'website_ticket_net_cents' => 0,
            'website_ticket_tax_cents' => 0,
            'website_ticket_refunded_cents' => 0,
            'website_ticket_ordered_cents' => 0,
            'website_addon_net_cents' => 0,
            'website_addon_tax_cents' => 0,
            'website_addon_refunded_cents' => 0,
            'website_addon_ordered_cents' => 0,
            'website_order_count' => 0,
            'website_line_count' => 0,
            'website_ticket_qty' => 0,
            'website_ticket_refunded_qty' => 0,
            'website_ticket_ordered_qty' => 0,
            'website_paid_ticket_qty' => 0,
            'website_free_ticket_qty' => 0,
            'website_addon_qty' => 0,
            'website_addon_refunded_qty' => 0,
            'website_addon_ordered_qty' => 0,
        );
    }
}

if (!function_exists('vms_dt_reporting_build_event_lifetime_website_truth')) {
    function vms_dt_reporting_build_event_lifetime_website_truth(int $event_plan_id): array
    {
        if ($event_plan_id <= 0) {
            return vms_dt_reporting_zero_website_row();
        }

        static $memory_cache = array();
        if (isset($memory_cache[$event_plan_id]) && is_array($memory_cache[$event_plan_id])) {
            if (vms_dt_has_core_function('vms_resource_fingerprint_flag')) {
                vms_dt_call_core_function('vms_resource_fingerprint_flag', 'dt_lifetime_website_truth_cache', 'memory_hit');
            }
            return $memory_cache[$event_plan_id];
        }

        $ticket_report = function_exists('vms_dt_rr_build_ticket_report')
            ? vms_dt_rr_build_ticket_report(array(
                'event_from' => '',
                'event_to' => '',
                'sold_from' => '',
                'sold_to' => '',
                'event_plan_id' => $event_plan_id,
            ))
            : array();

        $website = function_exists('vms_dt_rr_build_website_event_map')
            ? vms_dt_rr_build_website_event_map((array) $ticket_report, array($event_plan_id))
            : array();

        $map = isset($website['map']) && is_array($website['map']) ? $website['map'] : array();
        $result = isset($map[$event_plan_id]) && is_array($map[$event_plan_id])
            ? $map[$event_plan_id]
            : vms_dt_reporting_zero_website_row();
        $memory_cache[$event_plan_id] = $result;
        return $result;
    }
}

if (!function_exists('vms_dt_reporting_apply_single_event_truth')) {
    function vms_dt_reporting_apply_single_event_truth(array $row, array $website_truth): array
    {
        $row['website_ticket_cents'] = (int) ($website_truth['website_ticket_cents'] ?? 0);
        $row['website_addon_cents'] = (int) ($website_truth['website_addon_cents'] ?? 0);
        $row['website_total_cents'] = (int) ($website_truth['website_total_cents'] ?? 0);
        $row['website_ticket_net_cents'] = (int) ($website_truth['website_ticket_net_cents'] ?? 0);
        $row['website_ticket_tax_cents'] = (int) ($website_truth['website_ticket_tax_cents'] ?? 0);
        $row['website_ticket_refunded_cents'] = (int) ($website_truth['website_ticket_refunded_cents'] ?? 0);
        $row['website_ticket_ordered_cents'] = (int) ($website_truth['website_ticket_ordered_cents'] ?? 0);
        $row['website_addon_net_cents'] = (int) ($website_truth['website_addon_net_cents'] ?? 0);
        $row['website_addon_tax_cents'] = (int) ($website_truth['website_addon_tax_cents'] ?? 0);
        $row['website_addon_refunded_cents'] = (int) ($website_truth['website_addon_refunded_cents'] ?? 0);
        $row['website_addon_ordered_cents'] = (int) ($website_truth['website_addon_ordered_cents'] ?? 0);
        $row['website_order_count'] = (int) ($website_truth['website_order_count'] ?? 0);
        $row['website_line_count'] = (int) ($website_truth['website_line_count'] ?? 0);
        $row['website_ticket_qty'] = (int) ($website_truth['website_ticket_qty'] ?? 0);
        $row['website_ticket_refunded_qty'] = (int) ($website_truth['website_ticket_refunded_qty'] ?? 0);
        $row['website_ticket_ordered_qty'] = (int) ($website_truth['website_ticket_ordered_qty'] ?? 0);
        $row['website_paid_ticket_qty'] = (int) ($website_truth['website_paid_ticket_qty'] ?? ($row['website_paid_ticket_qty'] ?? 0));
        $row['website_free_ticket_qty'] = (int) ($website_truth['website_free_ticket_qty'] ?? ($row['website_free_ticket_qty'] ?? 0));
        $row['website_addon_qty'] = (int) ($website_truth['website_addon_qty'] ?? 0);
        $row['website_addon_refunded_qty'] = (int) ($website_truth['website_addon_refunded_qty'] ?? 0);
        $row['website_addon_ordered_qty'] = (int) ($website_truth['website_addon_ordered_qty'] ?? 0);
        return $row;
    }
}

if (!function_exists('vms_dt_reporting_build_single_event_summary')) {
    function vms_dt_reporting_build_single_event_summary(array $row): array
    {
        $online_ticket_sales = (int) ($row['website_ticket_net_cents'] ?? 0);
        $door_ticket_sales = (int) ($row['square_direct_tickets_net_cents'] ?? ($row['square_direct_tickets_cents'] ?? 0));
        $website_addons_net = (int) ($row['website_addon_net_cents'] ?? 0);
        $onsite_non_ticket = max(0, (int) (($row['square_counted_total_cents'] ?? 0) - ($row['square_direct_tickets_cents'] ?? 0)));
        $total_ticket_sales = $online_ticket_sales + $door_ticket_sales;
        $total_ticket_qty = (int) (($row['website_ticket_qty'] ?? 0) + ($row['square_direct_ticket_qty'] ?? 0));

        $website_collected = (int) ($row['website_total_cents'] ?? 0);
        $square_collected = (int) ($row['square_scope_total_collected_cents'] ?? 0);
        $gross_cashflow = $square_collected;
        $direct_square_pos = max(0, $square_collected - $website_collected);
        $profitability_basis = (int) ($row['counted_total_cents'] ?? 0);

        $uncategorized_held = (int) ($row['square_unclassified_cents'] ?? 0);
        $refunds_overlap = (int) (($row['website_ticket_refunded_cents'] ?? 0) + ($row['website_addon_refunded_cents'] ?? 0) + ($row['square_overlap_excluded_cents'] ?? 0) + $uncategorized_held);
        $tax_collected = (int) (($row['website_ticket_tax_cents'] ?? 0) + ($row['website_addon_tax_cents'] ?? 0) + ($row['square_scope_line_tax_cents'] ?? 0));
        $tips = (int) ($row['square_scope_tip_cents'] ?? 0);
        $combined_counted = $total_ticket_sales + $website_addons_net + $onsite_non_ticket;

        return array(
            'online_ticket_sales_cents' => $online_ticket_sales,
            'door_ticket_sales_cents' => $door_ticket_sales,
            'total_ticket_sales_cents' => $total_ticket_sales,
            'online_ticket_qty' => (int) ($row['website_ticket_qty'] ?? 0),
            'door_ticket_qty' => (int) ($row['square_direct_ticket_qty'] ?? 0),
            'total_ticket_qty' => $total_ticket_qty,
            'website_paid_ticket_qty' => (int) ($row['website_paid_ticket_qty'] ?? 0),
            'website_free_ticket_qty' => (int) ($row['website_free_ticket_qty'] ?? 0),
            'square_paid_ticket_qty' => (int) ($row['square_paid_ticket_qty'] ?? 0),
            'square_free_ticket_qty' => (int) ($row['square_free_ticket_qty'] ?? 0),
            'paid_ticket_qty_total' => max(0, (int) (($row['website_paid_ticket_qty'] ?? 0) + ($row['square_paid_ticket_qty'] ?? 0))),
            'free_ticket_qty_total' => max(0, (int) (($row['website_free_ticket_qty'] ?? 0) + ($row['square_free_ticket_qty'] ?? 0))),
            'website_addons_net_cents' => $website_addons_net,
            'onsite_non_ticket_cents' => $onsite_non_ticket,
            'combined_counted_cents' => $combined_counted,
            'website_collected_cents' => $website_collected,
            'square_collected_cents' => $square_collected,
            'gross_cashflow_cents' => $gross_cashflow,
            'direct_square_pos_cents' => $direct_square_pos,
            'profitability_basis_cents' => $profitability_basis,
            'tax_collected_cents' => $tax_collected,
            'tips_cents' => $tips,
            'refunds_overlap_cents' => $refunds_overlap,
            'uncategorized_held_cents' => $uncategorized_held,
        );
    }
}

if (!function_exists('vms_dt_reporting_get_vendor_payout_count_snapshot')) {
    function vms_dt_reporting_get_vendor_payout_count_snapshot(int $event_plan_id): array
    {
        $snapshot = array(
            'available' => false,
            'basis_source' => 'dt_costs',
            'bonus_basis_qty' => 0,
            'presales_qty' => 0,
            'eligible_door_qty' => 0,
            'excluded_qty' => 0,
        );

        $event_plan_id = absint($event_plan_id);
        if (
            $event_plan_id <= 0
            || !vms_dt_has_core_function('vms_vendor_portal_get_progress_headcount_context')
            || !vms_dt_has_core_function('vms_vendor_portal_get_count_breakdown')
        ) {
            return $snapshot;
        }

        $headcount_context = (array) vms_dt_call_core_function('vms_vendor_portal_get_progress_headcount_context', $event_plan_id);
        $count_breakdown = (array) vms_dt_call_core_function('vms_vendor_portal_get_count_breakdown', $event_plan_id, $headcount_context);

        $presales_qty = max(0, (int) ($count_breakdown['presales'] ?? 0));
        $eligible_door_qty = max(0, (int) ($count_breakdown['door_sales'] ?? 0));
        $excluded_qty = max(0, (int) ($count_breakdown['comp_guest'] ?? 0));
        $bonus_basis_qty = max(0, $presales_qty + $eligible_door_qty);

        if ($bonus_basis_qty <= 0 && $excluded_qty <= 0 && empty($count_breakdown['has_any'])) {
            return $snapshot;
        }

        $snapshot['available'] = true;
        $snapshot['basis_source'] = 'vendor_portal';
        $snapshot['bonus_basis_qty'] = $bonus_basis_qty;
        $snapshot['presales_qty'] = $presales_qty;
        $snapshot['eligible_door_qty'] = $eligible_door_qty;
        $snapshot['excluded_qty'] = $excluded_qty;

        return $snapshot;
    }
}

if (!function_exists('vms_dt_reporting_bonus_threshold_count')) {
    function vms_dt_reporting_bonus_threshold_count(array $terms): int
    {
        $structure = sanitize_key((string) ($terms['structure'] ?? ''));
        if ($structure !== 'attendance_bonus') {
            return 0;
        }

        $mode = sanitize_key((string) ($terms['attendance_bonus_mode'] ?? ''));
        $start_count = max(0, (int) ($terms['attendance_bonus_start_count'] ?? 0));
        if ($mode === 'step') {
            $step_size = max(0, (int) ($terms['attendance_bonus_step_size'] ?? 0));
            if ($step_size > 0) {
                return $start_count + $step_size;
            }
        }

        return $start_count;
    }
}

if (!function_exists('vms_dt_reporting_build_ticket_count_reconciliation')) {
    function vms_dt_reporting_build_ticket_count_reconciliation(array $summary, array $costs, array $comparison = array()): array
    {
        $online_record_qty = max(0, (int) ($summary['online_ticket_qty'] ?? 0));
        $door_record_qty = max(0, (int) ($summary['door_ticket_qty'] ?? 0));
        $total_record_qty = max(0, (int) ($summary['total_ticket_qty'] ?? ($online_record_qty + $door_record_qty)));

        $dt_bonus_basis_qty = max(0, (int) ($costs['paid_ticket_qty_total'] ?? 0));
        $dt_excluded_qty = max(0, (int) ($costs['free_ticket_qty_excluded'] ?? 0));

        $comparison_available = !empty($comparison['available']);
        $presales_qty = $comparison_available ? max(0, (int) ($comparison['presales_qty'] ?? 0)) : 0;
        $eligible_door_qty = $comparison_available ? max(0, (int) ($comparison['eligible_door_qty'] ?? 0)) : 0;
        $comparison_bonus_basis_qty = $comparison_available ? max(0, (int) ($comparison['bonus_basis_qty'] ?? 0)) : 0;
        $comparison_excluded_qty = $comparison_available ? max(0, (int) ($comparison['excluded_qty'] ?? 0)) : 0;

        $basis_source = $comparison_available ? (string) ($comparison['basis_source'] ?? 'vendor_portal') : 'dt_costs';
        $bonus_basis_qty = $comparison_available ? $comparison_bonus_basis_qty : $dt_bonus_basis_qty;
        $excluded_qty = $comparison_available ? $comparison_excluded_qty : $dt_excluded_qty;

        $basis_note = sprintf(
            __('%s paid/eligible admissions currently drive the vendor payout bonus basis.', 'vms-data-tools'),
            number_format($bonus_basis_qty)
        );
        if ($comparison_available) {
            $basis_note = sprintf(
                __('%1$s paid presales + %2$s eligible door sales = %3$s vendor payout bonus count.', 'vms-data-tools'),
                number_format($presales_qty),
                number_format($eligible_door_qty),
                number_format($bonus_basis_qty)
            );
        }
        if ($excluded_qty > 0) {
            $basis_note .= ' ' . sprintf(
                __('Excluded free / qualified / comp / guest list: %s.', 'vms-data-tools'),
                number_format($excluded_qty)
            );
        }

        $diagnostics = array();
        if ($total_record_qty > 0 && $bonus_basis_qty !== $total_record_qty) {
            $diagnostics[] = sprintf(
                __('%1$s total ticket/admission records are visible in the DT ticket story, but the vendor payout bonus basis is %2$s paid/eligible admissions.', 'vms-data-tools'),
                number_format($total_record_qty),
                number_format($bonus_basis_qty)
            );
        }

        if ($excluded_qty > 0) {
            $diagnostics[] = sprintf(
                __('Vendor payout bonus basis excludes %s free / qualified / comp / guest records.', 'vms-data-tools'),
                number_format($excluded_qty)
            );
        }

        $threshold_qty = vms_dt_reporting_bonus_threshold_count((array) ($costs['terms'] ?? array()));
        if ($threshold_qty > 0 && $total_record_qty >= $threshold_qty && $bonus_basis_qty < $threshold_qty) {
            $diagnostics[] = sprintf(
                __('Total ticket/admission records meet or exceed the %1$s-ticket bonus threshold, but the vendor payout bonus basis does not (%2$s).', 'vms-data-tools'),
                number_format($threshold_qty),
                number_format($bonus_basis_qty)
            );
        }

        if ($comparison_available && $door_record_qty !== $eligible_door_qty) {
            $diagnostics[] = sprintf(
                __('DT counted door records (%1$s) do not match vendor payout eligible door sales (%2$s).', 'vms-data-tools'),
                number_format($door_record_qty),
                number_format($eligible_door_qty)
            );
        }

        $diagnostics = array_values(array_unique(array_filter(array_map('strval', $diagnostics))));

        return array(
            'basis_source' => $basis_source,
            'online_record_qty' => $online_record_qty,
            'door_record_qty' => $door_record_qty,
            'total_record_qty' => $total_record_qty,
            'bonus_basis_qty' => $bonus_basis_qty,
            'presales_qty' => $presales_qty,
            'eligible_door_qty' => $eligible_door_qty,
            'excluded_qty' => $excluded_qty,
            'threshold_qty' => $threshold_qty,
            'basis_note' => $basis_note,
            'diagnostics' => $diagnostics,
        );
    }
}

if (!function_exists('vms_dt_reporting_build_online_ticket_note')) {
    function vms_dt_reporting_build_online_ticket_note(int $online_ticket_qty): string
    {
        return sprintf(
            __('%s completed online ticket/admission records', 'vms-data-tools'),
            number_format(max(0, $online_ticket_qty))
        );
    }
}

if (!function_exists('vms_dt_reporting_build_total_ticket_note')) {
    function vms_dt_reporting_build_total_ticket_note(int $online_ticket_qty, int $door_ticket_qty, int $total_ticket_qty, array $reconciliation = array()): string
    {
        $note = sprintf(
            __('%1$s online ticket/admission records + %2$s counted door records = %3$s total ticket/admission records', 'vms-data-tools'),
            number_format(max(0, $online_ticket_qty)),
            number_format(max(0, $door_ticket_qty)),
            number_format(max(0, $total_ticket_qty))
        );

        $bonus_basis_qty = max(0, (int) ($reconciliation['bonus_basis_qty'] ?? 0));
        if ($bonus_basis_qty > 0 && $bonus_basis_qty !== max(0, $total_ticket_qty)) {
            $note .= ' ' . sprintf(
                __('Vendor payout bonus basis: %s.', 'vms-data-tools'),
                number_format($bonus_basis_qty)
            );
        }

        return $note;
    }
}

if (!function_exists('vms_dt_reporting_build_event_model')) {
    function vms_dt_reporting_build_event_model(array $filters): array
    {
        $cache_key = md5(wp_json_encode($filters));
        static $memory_cache = array();
        if (isset($memory_cache[$cache_key]) && is_array($memory_cache[$cache_key])) {
            if (vms_dt_has_core_function('vms_resource_fingerprint_flag')) {
                vms_dt_call_core_function('vms_resource_fingerprint_flag', 'dt_event_model_cache', 'memory_hit');
            }
            return $memory_cache[$cache_key];
        }

        if (vms_dt_has_core_function('vms_resource_fingerprint_flag')) {
            vms_dt_call_core_function('vms_resource_fingerprint_flag', 'dt_report', array(
                'kind' => 'event_model',
                'event_plan_id' => (int) ($filters['event_plan_id'] ?? 0),
            ));
        }
        if (vms_dt_has_core_function('vms_resource_fingerprint_span_start')) {
            vms_dt_call_core_function('vms_resource_fingerprint_span_start', 'dt.event_model', array(
                'event_plan_id' => (int) ($filters['event_plan_id'] ?? 0),
                'sold_from' => (string) ($filters['sold_from'] ?? ''),
                'sold_to' => (string) ($filters['sold_to'] ?? ''),
            ));
        }

        try {
            $dataset = vms_dt_rr_build_report_dataset($filters);
            $rows = (array) ($dataset['event_rows'] ?? array());
            $row = !empty($rows[0]) && is_array($rows[0]) ? $rows[0] : vms_dt_reporting_zero_row();

            $event_plan_id = (int) ($row['event_plan_id'] ?? 0);
            $uses_default_lifetime_window = ((string) ($filters['sold_from'] ?? '') === '') && ((string) ($filters['sold_to'] ?? '') === '');
            if ($event_plan_id > 0) {
                $website_truth = ($uses_default_lifetime_window && !empty($dataset['website']['map'][$event_plan_id]) && is_array($dataset['website']['map'][$event_plan_id]))
                    ? (array) $dataset['website']['map'][$event_plan_id]
                    : vms_dt_reporting_build_event_lifetime_website_truth($event_plan_id);
                $row = vms_dt_reporting_apply_single_event_truth($row, $website_truth);
                if (!empty($dataset['event_rows'][0]) && is_array($dataset['event_rows'][0])) {
                    $dataset['event_rows'][0] = array_merge($dataset['event_rows'][0], $website_truth);
                }
            }

            $rows_for_overview = !empty($dataset['event_rows']) && is_array($dataset['event_rows']) ? (array) $dataset['event_rows'] : array($row);
            if (!empty($rows_for_overview[0]) && is_array($rows_for_overview[0]) && $event_plan_id > 0) {
                $rows_for_overview[0] = array_merge($rows_for_overview[0], array(
                    'website_ticket_net_cents' => (int) ($row['website_ticket_net_cents'] ?? 0),
                    'website_ticket_tax_cents' => (int) ($row['website_ticket_tax_cents'] ?? 0),
                    'website_ticket_refunded_cents' => (int) ($row['website_ticket_refunded_cents'] ?? 0),
                    'website_ticket_ordered_cents' => (int) ($row['website_ticket_ordered_cents'] ?? 0),
                    'website_addon_net_cents' => (int) ($row['website_addon_net_cents'] ?? 0),
                    'website_addon_tax_cents' => (int) ($row['website_addon_tax_cents'] ?? 0),
                    'website_addon_refunded_cents' => (int) ($row['website_addon_refunded_cents'] ?? 0),
                    'website_total_cents' => (int) ($row['website_total_cents'] ?? 0),
                    'website_ticket_qty' => (int) ($row['website_ticket_qty'] ?? 0),
                    'website_addon_qty' => (int) ($row['website_addon_qty'] ?? 0),
                ));
            }

            $overview = vms_dt_rr_build_overview($rows_for_overview, (array) ($dataset['website'] ?? array()), (array) ($dataset['square_meta'] ?? array()));
            $evidence = ($event_plan_id > 0)
                ? vms_dt_reporting_build_single_event_evidence(array(
                    'filters' => $filters,
                    'row' => $row,
                ))
                : array('website' => array(), 'square' => array());

            if ($event_plan_id > 0) {
                $square_evidence = isset($evidence['square']) && is_array($evidence['square']) ? (array) $evidence['square'] : array();
                $square_summary = isset($square_evidence['summary']) && is_array($square_evidence['summary']) ? (array) $square_evidence['summary'] : array();
                $square_onsite_rows = isset($square_evidence['onsite_rows']) && is_array($square_evidence['onsite_rows']) ? (array) $square_evidence['onsite_rows'] : array();
                $row_has_square_split = (
                    ((int) ($row['square_paid_ticket_qty'] ?? 0) + (int) ($row['square_free_ticket_qty'] ?? 0)) > 0
                );
                if (!empty($square_summary) || !empty($square_onsite_rows)) {
                    if (!$row_has_square_split || (int) ($row['square_direct_tickets_cents'] ?? 0) <= 0) {
                        $row['square_direct_tickets_cents'] = (int) ($square_summary['ticket_like_counted_cents'] ?? ($row['square_direct_tickets_cents'] ?? 0));
                    }
                    if (!$row_has_square_split || (int) ($row['square_direct_tickets_net_cents'] ?? 0) <= 0) {
                        $row['square_direct_tickets_net_cents'] = (int) ($square_summary['ticket_like_counted_net_cents'] ?? ($row['square_direct_tickets_net_cents'] ?? $row['square_direct_tickets_cents'] ?? 0));
                    }
                    if (!$row_has_square_split || (int) ($row['square_direct_ticket_qty'] ?? 0) <= 0) {
                        $row['square_direct_ticket_qty'] = (int) ($square_summary['ticket_like_counted_qty'] ?? ($row['square_direct_ticket_qty'] ?? 0));
                    }
                    $row['square_counted_total_cents'] = (int) ($row['square_direct_tickets_cents'] ?? 0) + vms_dt_reporting_sum_gross_cents($square_onsite_rows);
                }
            }

            $ticket_sources = vms_dt_reporting_build_ticket_source_rollup($row, (array) $evidence);
            $row['website_ticket_qty'] = (int) ($ticket_sources['website_ticket_qty'] ?? ($row['website_ticket_qty'] ?? 0));
            $row['website_paid_ticket_qty'] = (int) ($ticket_sources['website_paid_ticket_qty'] ?? ($row['website_paid_ticket_qty'] ?? 0));
            $row['website_free_ticket_qty'] = (int) ($ticket_sources['website_free_ticket_qty'] ?? ($row['website_free_ticket_qty'] ?? 0));
            $row['square_direct_ticket_qty'] = (int) ($ticket_sources['square_ticket_qty'] ?? ($row['square_direct_ticket_qty'] ?? 0));
            $row['square_paid_ticket_qty'] = (int) ($ticket_sources['square_paid_ticket_qty'] ?? ($row['square_paid_ticket_qty'] ?? 0));
            $row['square_free_ticket_qty'] = (int) ($ticket_sources['square_free_ticket_qty'] ?? ($row['square_free_ticket_qty'] ?? 0));
            $row['square_direct_tickets_net_cents'] = (int) ($ticket_sources['square_paid_ticket_revenue_cents'] ?? ($row['square_direct_tickets_net_cents'] ?? $row['square_direct_tickets_cents'] ?? 0));

            $model = array(
                'filters' => $filters,
                'dataset' => $dataset,
                'overview' => $overview,
                'row' => $row,
                'summary' => vms_dt_reporting_build_single_event_summary($row),
                'ticket_sources' => $ticket_sources,
                'costs' => vms_dt_reporting_calculate_event_costs($row, (array) $evidence),
                'evidence' => $evidence,
            );
            $model['eventbrite'] = vms_dt_reporting_calculate_eventbrite_savings((array) ($model['row'] ?? array()), (array) ($model['evidence'] ?? array()));
            $memory_cache[$cache_key] = $model;
            return $model;
        } finally {
            if (vms_dt_has_core_function('vms_resource_fingerprint_span_finish')) {
                vms_dt_call_core_function('vms_resource_fingerprint_span_finish', 'dt.event_model', array(
                    'event_plan_id' => (int) ($filters['event_plan_id'] ?? 0),
                ));
            }
        }
    }
}


if (!function_exists('vms_dt_reporting_local_datetime_from_utc')) {
    function vms_dt_reporting_local_datetime_from_utc(string $utc_value): string
    {
        $utc_value = trim($utc_value);
        if ($utc_value === '') {
            return '';
        }
        try {
            $dt = new DateTimeImmutable($utc_value, new DateTimeZone('UTC'));
            return $dt->setTimezone(wp_timezone())->format('Y-m-d g:i A');
        } catch (Throwable $e) {
            return $utc_value;
        }
    }
}


if (!function_exists('vms_dt_reporting_counted_door_ticket_rows')) {
    function vms_dt_reporting_counted_door_ticket_rows($rows): array
    {
        if (!is_array($rows)) {
            return array();
        }
        return array_values(array_filter($rows, static function ($entry): bool {
            return is_array($entry) && (($entry['treatment'] ?? '') === 'counted');
        }));
    }
}

if (!function_exists('vms_dt_reporting_square_ticket_channel_summary')) {
    function vms_dt_reporting_square_ticket_channel_summary($rows): array
    {
        if (!is_array($rows)) {
            $rows = array();
        }
        $summary = array(
            'door_register_qty' => 0,
            'door_register_cents' => 0,
            'door_qr_qty' => 0,
            'door_qr_cents' => 0,
            'held_qty' => 0,
            'held_cents' => 0,
        );
        foreach ($rows as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $qty = (int) ($entry['quantity'] ?? 0);
            $gross = (int) ($entry['gross_cents'] ?? 0);
            $source = strtolower((string) ($entry['source_label'] ?? ''));
            $treatment = (string) ($entry['treatment'] ?? '');
            if ($treatment !== 'counted') {
                $summary['held_qty'] += $qty;
                $summary['held_cents'] += $gross;
                continue;
            }
            if (strpos($source, 'door qr') !== false || strpos($source, 'payment link') !== false || strpos($source, 'qr') !== false) {
                $summary['door_qr_qty'] += $qty;
                $summary['door_qr_cents'] += $gross;
            } else {
                $summary['door_register_qty'] += $qty;
                $summary['door_register_cents'] += $gross;
            }
        }
        return $summary;
    }
}

if (!function_exists('vms_dt_reporting_build_website_detail_rows')) {
    function vms_dt_reporting_build_website_detail_rows(int $event_plan_id): array
    {
        $out = array(
            'ticket_rows' => array(),
            'addon_rows' => array(),
            'warnings' => array(),
            'counts' => array(),
        );

        if ($event_plan_id <= 0) {
            return $out;
        }

        $ticket_report = function_exists('vms_dt_rr_build_ticket_report')
            ? vms_dt_rr_build_ticket_report(array(
                'event_from' => '',
                'event_to' => '',
                'sold_from' => '',
                'sold_to' => '',
                'event_plan_id' => $event_plan_id,
            ))
            : array();

        $out['warnings'] = array_values(array_unique(array_filter(array_map('strval', (array) ($ticket_report['warnings'] ?? array())))));
        $out['counts'] = (array) ($ticket_report['counts'] ?? array());

        foreach ((array) ($ticket_report['rows'] ?? array()) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $entry = array(
                'order_id' => (int) ($row['order_id'] ?? 0),
                'order_number' => (string) ($row['order_number'] ?? ''),
                'sold_date' => (string) ($row['sold_date'] ?? ''),
                'sold_datetime' => (string) ($row['sold_datetime'] ?? ''),
                'customer_name' => (string) ($row['customer_name'] ?? ''),
                'customer_email' => (string) ($row['customer_email'] ?? ''),
                'item_name' => (string) ($row['item_name'] ?? ''),
                'product_sku' => (string) ($row['product_sku'] ?? ''),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'refunded_quantity' => (int) ($row['refunded_quantity'] ?? 0),
                'net_subtotal_cents' => (int) ($row['net_subtotal_cents'] ?? 0),
                'tax_cents' => (int) ($row['tax_cents'] ?? 0),
                'cash_total_cents' => (int) ($row['cash_total_cents'] ?? 0),
                'refunded_subtotal_cents' => (int) ($row['refunded_subtotal_cents'] ?? 0),
            );
            if (in_array(sanitize_key((string) ($row['item_kind'] ?? 'ticket')), array('entitlement', 'addon'), true)) {
                $out['addon_rows'][] = $entry;
            } else {
                $out['ticket_rows'][] = $entry;
            }
        }

        return $out;
    }
}

if (!function_exists('vms_dt_reporting_build_square_line_evidence')) {
    function vms_dt_reporting_build_square_line_evidence(int $event_plan_id, array $filters): array
    {
        $out = array(
            'errors' => array(),
            'warnings' => array(),
            'ticket_rows' => array(),
            'onsite_rows' => array(),
            'held_rows' => array(),
            'scope' => array(),
            'summary' => array(
                'ticket_like_qty_total' => 0,
                'ticket_like_counted_qty' => 0,
                'ticket_like_held_qty' => 0,
                'ticket_like_total_cents' => 0,
                'ticket_like_counted_cents' => 0,
                'ticket_like_counted_net_cents' => 0,
                'ticket_like_held_cents' => 0,
            ),
        );

        if ($event_plan_id <= 0) {
            return $out;
        }

        $scope = vms_dt_rr_resolve_square_location_scope($filters);
        $out['warnings'] = array_merge($out['warnings'], (array) ($scope['warnings'] ?? array()));
        $out['errors'] = array_merge($out['errors'], (array) ($scope['errors'] ?? array()));
        $out['scope'] = array(
            'selected_location_id' => (string) ($scope['selected_location_id'] ?? ''),
            'selected_location_label' => (string) ($scope['selected_label'] ?? ''),
            'requires_selection' => !empty($scope['requires_selection']),
            'scope_mode' => vms_dt_rr_normalize_square_scope_mode((string) ($filters['square_scope_mode'] ?? 'full_day')),
            'scope_label' => vms_dt_rr_square_scope_label((string) ($filters['square_scope_mode'] ?? 'full_day')),
        );

        $window = vms_dt_rr_build_event_window($event_plan_id, (string) ($filters['square_scope_mode'] ?? 'full_day'));
        $out['warnings'] = array_merge($out['warnings'], (array) ($window['warnings'] ?? array()));
        $out['errors'] = array_merge($out['errors'], (array) ($window['errors'] ?? array()));
        $out['scope']['window'] = $window;

        if (empty($scope['location_ids']) || empty($window['window_start_utc']) || empty($window['window_end_utc'])) {
            $out['errors'] = array_values(array_unique(array_filter(array_map('strval', $out['errors']))));
            $out['warnings'] = array_values(array_unique(array_filter(array_map('strval', $out['warnings']))));
            return $out;
        }

        $scan_start_utc = (string) $window['window_start_utc'];
        $scan_end_utc = (string) $window['window_end_utc'];
        if (!empty($filters['sold_from'])) {
            try {
                $sold_floor = (new DateTimeImmutable((string) $filters['sold_from'] . ' 00:00:00', wp_timezone()))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                if ($sold_floor > $scan_start_utc) {
                    $scan_start_utc = $sold_floor;
                }
            } catch (Throwable $e) {
            }
        }
        if (!empty($filters['sold_to'])) {
            try {
                $sold_ceiling = (new DateTimeImmutable((string) $filters['sold_to'] . ' 00:00:00', wp_timezone()))->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                if ($sold_ceiling < $scan_end_utc) {
                    $scan_end_utc = $sold_ceiling;
                }
            } catch (Throwable $e) {
            }
        }

        if ($scan_start_utc >= $scan_end_utc) {
            $out['errors'][] = 'The current sold-date/scope filters exclude all possible Square order times for this event.';
            $out['errors'] = array_values(array_unique(array_filter(array_map('strval', $out['errors']))));
            $out['warnings'] = array_values(array_unique(array_filter(array_map('strval', $out['warnings']))));
            return $out;
        }

        try {
            $rows = vms_dt_rr_square_fetch_scope_orders((array) $scope['location_ids'], $scan_start_utc, $scan_end_utc);
        } catch (Throwable $e) {
            $out['errors'][] = 'Square order lookup failed: ' . $e->getMessage();
            $out['errors'] = array_values(array_unique(array_filter(array_map('strval', $out['errors']))));
            $out['warnings'] = array_values(array_unique(array_filter(array_map('strval', $out['warnings']))));
            return $out;
        }

        try {
            vms_dt_rr_square_prime_catalog_map_pairs($rows);
        } catch (Throwable $e) {
            $out['warnings'][] = 'Square catalog lookup could not be refreshed, so some line items may still look vague.';
        }

        $bucket_map = function_exists('vms_square_effective_bucket_category_ids') ? (array) vms_square_effective_bucket_category_ids() : array();
        $bucket_labels = function_exists('vms_square_effective_bucket_labels') ? (array) vms_square_effective_bucket_labels() : array();
        $ticket_bucket_keys = vms_dt_rr_detect_ticket_bucket_keys($bucket_labels, $bucket_map);
        $category_to_bucket = array();
        foreach ($bucket_map as $bucket_key => $cat_ids) {
            foreach ((array) $cat_ids as $cat_id) {
                $cat_id = trim((string) $cat_id);
                if ($cat_id === '' || isset($category_to_bucket[$cat_id])) {
                    continue;
                }
                $category_to_bucket[$cat_id] = (string) $bucket_key;
            }
        }
        $variation_overrides = function_exists('vms_dt_rr_get_variation_bucket_overrides') ? (array) vms_dt_rr_get_variation_bucket_overrides() : array();
        $category_cache = function_exists('vms_dt_rr_get_category_cache_map') ? (array) vms_dt_rr_get_category_cache_map() : array();

        foreach ((array) $rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $closed_at_utc = trim((string) ($row['closed_at_utc'] ?? ''));
            if ($closed_at_utc === '' || $closed_at_utc < $window['window_start_utc'] || $closed_at_utc >= $window['window_end_utc']) {
                continue;
            }

            $source = vms_dt_rr_square_source_classification((string) ($row['raw_json'] ?? ''), (string) ($row['tenders_json'] ?? ''));
            $closed_local = vms_dt_reporting_local_datetime_from_utc($closed_at_utc);
            $line_items = json_decode((string) ($row['line_items_json'] ?? ''), true);
            if (!is_array($line_items)) {
                continue;
            }

            foreach ($line_items as $line_item) {
                if (!is_array($line_item)) {
                    continue;
                }

                $line_total = function_exists('vms_square_money_amount_from_money_obj') ? (int) vms_square_money_amount_from_money_obj($line_item, 'total_money') : 0;
                $line_qty = isset($line_item['quantity']) ? max(0, (int) round((float) $line_item['quantity'])) : 0;
                $line_tax = function_exists('vms_square_money_amount_from_money_obj') ? (int) vms_square_money_amount_from_money_obj($line_item, 'total_tax_money') : 0;
                $line_discount = function_exists('vms_square_money_amount_from_money_obj') ? (int) vms_square_money_amount_from_money_obj($line_item, 'total_discount_money') : 0;
                $variation_id = (string) ($line_item['catalog_object_id'] ?? '');
                $catalog_version = isset($line_item['catalog_version']) ? (int) $line_item['catalog_version'] : 0;
                $category_id = ($variation_id !== '' && $catalog_version > 0 && function_exists('vms_square_catalog_map_get_reporting_category_id'))
                    ? (string) vms_square_catalog_map_get_reporting_category_id($variation_id, $catalog_version)
                    : '';
                $category_name = $category_id !== '' ? (string) (($category_cache[$category_id]['name'] ?? $category_id)) : '';
                $override_bucket_key = $variation_id !== '' ? (string) ($variation_overrides[$variation_id] ?? '') : '';
                $bucket_key = $override_bucket_key !== '' ? $override_bucket_key : ($category_id !== '' ? (string) ($category_to_bucket[$category_id] ?? '') : '');
                $is_ticketish = vms_dt_rr_line_item_looks_ticketish($line_item, $category_name);
                $uncategorized_like = $category_name !== '' && strpos(strtolower(trim($category_name)), 'uncategorized') !== false;
                if ($override_bucket_key === '' && $uncategorized_like) {
                    $bucket_key = 'website_overlap';
                }

                if ($bucket_key === '' && $is_ticketish && $source['code'] === 'in_person') {
                    $bucket_key = 'ticket';
                }
                $bucket_label = $bucket_key !== '' ? (string) ($bucket_labels[$bucket_key] ?? ucwords(str_replace('_', ' ', $bucket_key))) : __('Unmapped', 'vms-data-tools');
                $display_group = ($bucket_key !== '') ? vms_dt_rr_bucket_display_group($bucket_key, $bucket_label, $ticket_bucket_keys) : '';
                if ($display_group === 'other' && $is_ticketish && $source['code'] === 'in_person') {
                    $display_group = 'square_direct_tickets';
                }
                $auto_overlap_candidate = ($source['code'] === 'website_candidate' && $is_ticketish);
                $treatment = 'held';
                $reason = '';

                if ($bucket_key === '') {
                    $reason = __('Held out: no bucket mapping yet.', 'vms-data-tools');
                } elseif ($bucket_key === 'ignore') {
                    $reason = __('Held out: ignored by mapper.', 'vms-data-tools');
                } elseif ($source['code'] === 'woo_linked' || $bucket_key === 'website_overlap') {
                    $reason = __('Held out: website / online overlap.', 'vms-data-tools');
                } else {
                    $treatment = 'counted';
                    if ($display_group === 'square_direct_tickets') {
                        $reason = $auto_overlap_candidate
                            ? __('Counted as door ticket revenue. Website-candidate guessing alone does not auto-hold ticket-category rows.', 'vms-data-tools')
                            : __('Counted as door ticket revenue.', 'vms-data-tools');
                    } else {
                        $reason = __('Counted as on-site revenue.', 'vms-data-tools');
                    }
                }

                $line_net = max(0, $line_total - $line_tax);
                $row_entry = array(
                    'closed_at_local' => $closed_local,
                    'closed_at_utc' => $closed_at_utc,
                    'square_order_id' => (string) ($row['square_order_id'] ?? ''),
                    'line_name' => (string) ($line_item['name'] ?? ''),
                    'variation_name' => (string) ($line_item['variation_name'] ?? ''),
                    'catalog_object_id' => $variation_id,
                    'quantity' => $line_qty,
                    'gross_cents' => $line_total,
                    'tax_cents' => $line_tax,
                    'net_cents' => $line_net,
                    'discount_cents' => $line_discount,
                    'source_code' => (string) ($source['code'] ?? ''),
                    'source_label' => (string) ($source['label'] ?? ''),
                    'bucket_key' => $bucket_key,
                    'bucket_label' => $bucket_label,
                    'category_name' => $category_name,
                    'treatment' => $treatment,
                    'reason' => $reason,
                    'is_ticketish' => $is_ticketish,
                    'is_direct_ticket' => ($display_group === 'square_direct_tickets'),
                );

                if ($is_ticketish || $display_group === 'square_direct_tickets') {
                    $out['ticket_rows'][] = $row_entry;
                    $out['summary']['ticket_like_qty_total'] += $line_qty;
                    $out['summary']['ticket_like_total_cents'] += $line_total;
                    if ($treatment === 'counted' && $display_group === 'square_direct_tickets') {
                        $out['summary']['ticket_like_counted_qty'] += $line_qty;
                        $out['summary']['ticket_like_counted_cents'] += $line_total;
                        $out['summary']['ticket_like_counted_net_cents'] += $line_net;
                    } else {
                        $out['summary']['ticket_like_held_qty'] += $line_qty;
                        $out['summary']['ticket_like_held_cents'] += $line_total;
                    }
                } elseif ($treatment === 'counted') {
                    $out['onsite_rows'][] = $row_entry;
                } else {
                    $out['held_rows'][] = $row_entry;
                }
            }
        }

        $out['errors'] = array_values(array_unique(array_filter(array_map('strval', $out['errors']))));
        $out['warnings'] = array_values(array_unique(array_filter(array_map('strval', $out['warnings']))));
        return $out;
    }
}

if (!function_exists('vms_dt_reporting_build_single_event_evidence')) {
    function vms_dt_reporting_build_single_event_evidence(array $model): array
    {
        $row = (array) ($model['row'] ?? array());
        $filters = (array) ($model['filters'] ?? array());
        $event_plan_id = (int) ($row['event_plan_id'] ?? 0);
        $cache_key = md5(wp_json_encode(array(
            'event_plan_id' => $event_plan_id,
            'filters' => $filters,
        )));
        static $memory_cache = array();
        if (isset($memory_cache[$cache_key]) && is_array($memory_cache[$cache_key])) {
            if (vms_dt_has_core_function('vms_resource_fingerprint_flag')) {
                vms_dt_call_core_function('vms_resource_fingerprint_flag', 'dt_single_event_evidence_cache', 'memory_hit');
            }
            return $memory_cache[$cache_key];
        }

        if (vms_dt_has_core_function('vms_resource_fingerprint_span_start')) {
            vms_dt_call_core_function('vms_resource_fingerprint_span_start', 'dt.single_event_evidence', array('event_plan_id' => $event_plan_id));
            vms_dt_call_core_function('vms_resource_fingerprint_span_start', 'dt.website_detail_rows', array('event_plan_id' => $event_plan_id));
        }
        $website = vms_dt_reporting_build_website_detail_rows($event_plan_id);
        if (vms_dt_has_core_function('vms_resource_fingerprint_span_finish')) {
            vms_dt_call_core_function('vms_resource_fingerprint_span_finish', 'dt.website_detail_rows', array(
                'event_plan_id' => $event_plan_id,
                'ticket_rows' => count((array) ($website['ticket_rows'] ?? array())),
                'addon_rows' => count((array) ($website['addon_rows'] ?? array())),
            ));
            vms_dt_call_core_function('vms_resource_fingerprint_span_start', 'dt.square_line_evidence', array('event_plan_id' => $event_plan_id));
        }
        $square = vms_dt_reporting_build_square_line_evidence($event_plan_id, $filters);
        if (vms_dt_has_core_function('vms_resource_fingerprint_span_finish')) {
            vms_dt_call_core_function('vms_resource_fingerprint_span_finish', 'dt.square_line_evidence', array(
                'event_plan_id' => $event_plan_id,
                'ticket_rows' => count((array) ($square['ticket_rows'] ?? array())),
                'onsite_rows' => count((array) ($square['onsite_rows'] ?? array())),
            ));
            vms_dt_call_core_function('vms_resource_fingerprint_span_finish', 'dt.single_event_evidence', array('event_plan_id' => $event_plan_id));
        }

        $result = array(
            'website' => $website,
            'square' => $square,
        );
        $memory_cache[$cache_key] = $result;
        return $result;
    }
}

if (!function_exists('vms_dt_reporting_zero_ticket_source_rollup')) {
    function vms_dt_reporting_zero_ticket_source_rollup(): array
    {
        return array(
            'source' => 'row',
            'website_rows_seen' => 0,
            'square_rows_seen' => 0,
            'website_ticket_qty' => 0,
            'website_paid_ticket_qty' => 0,
            'website_free_ticket_qty' => 0,
            'website_paid_ticket_revenue_cents' => 0,
            'square_ticket_qty' => 0,
            'square_paid_ticket_qty' => 0,
            'square_free_ticket_qty' => 0,
            'square_paid_ticket_revenue_cents' => 0,
            'paid_ticket_qty_total' => 0,
            'free_ticket_qty_total' => 0,
            'ticketed_attendance_qty' => 0,
            'paid_ticket_revenue_cents' => 0,
            'website_addons_net_cents' => 0,
            'has_countable_data' => false,
        );
    }
}

if (!function_exists('vms_dt_reporting_build_ticket_source_rollup')) {
    function vms_dt_reporting_build_ticket_source_rollup(array $row, array $evidence = array()): array
    {
        $result = vms_dt_reporting_zero_ticket_source_rollup();

        $result['website_ticket_qty'] = max(0, (int) ($row['website_ticket_qty'] ?? 0));
        $result['website_paid_ticket_qty'] = max(0, (int) ($row['website_paid_ticket_qty'] ?? 0));
        $result['website_free_ticket_qty'] = max(0, (int) ($row['website_free_ticket_qty'] ?? 0));
        $result['website_paid_ticket_revenue_cents'] = max(0, (int) ($row['website_ticket_net_cents'] ?? 0));

        $result['square_ticket_qty'] = max(0, (int) ($row['square_direct_ticket_qty'] ?? 0));
        $result['square_paid_ticket_qty'] = max(0, (int) ($row['square_paid_ticket_qty'] ?? 0));
        $result['square_free_ticket_qty'] = max(0, (int) ($row['square_free_ticket_qty'] ?? 0));
        $result['square_paid_ticket_revenue_cents'] = max(0, (int) ($row['square_direct_tickets_net_cents'] ?? ($row['square_direct_tickets_cents'] ?? 0)));

        $result['website_addons_net_cents'] = max(0, (int) ($row['website_addon_net_cents'] ?? 0));

        $website_evidence = isset($evidence['website']) && is_array($evidence['website']) ? (array) $evidence['website'] : array();
        $square_evidence = isset($evidence['square']) && is_array($evidence['square']) ? (array) $evidence['square'] : array();

        $website_ticket_rows = isset($website_evidence['ticket_rows']) && is_array($website_evidence['ticket_rows'])
            ? (array) $website_evidence['ticket_rows']
            : array();
        $square_ticket_rows = isset($square_evidence['ticket_rows']) && is_array($square_evidence['ticket_rows'])
            ? (array) $square_evidence['ticket_rows']
            : array();

        if (!empty($website_ticket_rows)) {
            $result['source'] = 'evidence';
            $result['website_ticket_qty'] = 0;
            $result['website_paid_ticket_qty'] = 0;
            $result['website_free_ticket_qty'] = 0;
            $result['website_paid_ticket_revenue_cents'] = 0;
            $result['website_rows_seen'] = 0;

            foreach ($website_ticket_rows as $ticket_row) {
                if (!is_array($ticket_row)) {
                    continue;
                }
                $qty = max(0, (int) ($ticket_row['quantity'] ?? 0) - (int) ($ticket_row['refunded_quantity'] ?? 0));
                if ($qty <= 0) {
                    continue;
                }

                $result['website_rows_seen']++;
                $result['website_ticket_qty'] += $qty;
                $net_subtotal_cents = (int) ($ticket_row['net_subtotal_cents'] ?? 0);
                if ($net_subtotal_cents > 0) {
                    $result['website_paid_ticket_qty'] += $qty;
                    $result['website_paid_ticket_revenue_cents'] += $net_subtotal_cents;
                } else {
                    $result['website_free_ticket_qty'] += $qty;
                }
            }
        }

        $row_has_square_split = (
            ((int) ($row['square_paid_ticket_qty'] ?? 0) + (int) ($row['square_free_ticket_qty'] ?? 0)) > 0
        );

        if (!empty($square_ticket_rows) && !$row_has_square_split) {
            $result['source'] = 'evidence';
            $result['square_ticket_qty'] = 0;
            $result['square_paid_ticket_qty'] = 0;
            $result['square_free_ticket_qty'] = 0;
            $result['square_paid_ticket_revenue_cents'] = 0;
            $result['square_rows_seen'] = 0;

            foreach ($square_ticket_rows as $ticket_row) {
                if (!is_array($ticket_row) || (($ticket_row['treatment'] ?? '') !== 'counted') || empty($ticket_row['is_direct_ticket'])) {
                    continue;
                }
                $qty = max(0, (int) ($ticket_row['quantity'] ?? 0));
                if ($qty <= 0) {
                    continue;
                }

                $result['square_rows_seen']++;
                $result['square_ticket_qty'] += $qty;
                $net_cents = max(0, (int) ($ticket_row['net_cents'] ?? (((int) ($ticket_row['gross_cents'] ?? 0)) - ((int) ($ticket_row['tax_cents'] ?? 0)))));
                if ($net_cents > 0) {
                    $result['square_paid_ticket_qty'] += $qty;
                    $result['square_paid_ticket_revenue_cents'] += $net_cents;
                } else {
                    $result['square_free_ticket_qty'] += $qty;
                }
            }
        }

        if ($result['website_ticket_qty'] > 0 && ($result['website_paid_ticket_qty'] + $result['website_free_ticket_qty']) <= 0) {
            if ($result['website_paid_ticket_revenue_cents'] > 0) {
                $result['website_paid_ticket_qty'] = $result['website_ticket_qty'];
            } else {
                $result['website_free_ticket_qty'] = $result['website_ticket_qty'];
            }
        }

        if ($result['square_ticket_qty'] > 0 && ($result['square_paid_ticket_qty'] + $result['square_free_ticket_qty']) <= 0) {
            if ($result['square_paid_ticket_revenue_cents'] > 0) {
                $result['square_paid_ticket_qty'] = $result['square_ticket_qty'];
            } else {
                $result['square_free_ticket_qty'] = $result['square_ticket_qty'];
            }
        }

        $result['paid_ticket_qty_total'] = max(0, (int) $result['website_paid_ticket_qty'] + (int) $result['square_paid_ticket_qty']);
        $result['free_ticket_qty_total'] = max(0, (int) $result['website_free_ticket_qty'] + (int) $result['square_free_ticket_qty']);
        $result['ticketed_attendance_qty'] = max(0, (int) $result['website_ticket_qty'] + (int) $result['square_ticket_qty']);
        $result['paid_ticket_revenue_cents'] = max(0, (int) $result['website_paid_ticket_revenue_cents'] + (int) $result['square_paid_ticket_revenue_cents']);
        $result['has_countable_data'] = (
            $result['ticketed_attendance_qty'] > 0
            || $result['website_rows_seen'] > 0
            || $result['square_rows_seen'] > 0
        );

        return $result;
    }
}

if (!function_exists('vms_dt_reporting_get_event_comp_terms')) {
    function vms_dt_reporting_get_event_comp_terms(int $event_plan_id): array
    {
        if ($event_plan_id <= 0) {
            return array(
                'structure' => 'flat_fee',
                'flat_fee_amount' => 0,
                'door_split_percent' => 0,
            );
        }
        if (vms_dt_has_core_function('vms_get_event_plan_comp_terms')) {
            return (array) vms_dt_call_core_function('vms_get_event_plan_comp_terms', $event_plan_id);
        }
        return array(
            'structure' => (string) get_post_meta($event_plan_id, '_vms_comp_structure', true),
            'flat_fee_amount' => get_post_meta($event_plan_id, '_vms_flat_fee_amount', true),
            'door_split_percent' => get_post_meta($event_plan_id, '_vms_door_split_percent', true),
            'attendance_bonus_mode' => (string) get_post_meta($event_plan_id, '_vms_attendance_bonus_mode', true),
            'attendance_bonus_start_count' => get_post_meta($event_plan_id, '_vms_attendance_bonus_start_count', true),
            'attendance_bonus_step_size' => get_post_meta($event_plan_id, '_vms_attendance_bonus_step_size', true),
            'attendance_bonus_step_bonus' => get_post_meta($event_plan_id, '_vms_attendance_bonus_step_bonus', true),
            'attendance_bonus_per_ticket_rate' => get_post_meta($event_plan_id, '_vms_attendance_bonus_per_ticket_rate', true),
            'attendance_bonus_max_bonus' => get_post_meta($event_plan_id, '_vms_attendance_bonus_max_bonus', true),
        );
    }
}


if (!function_exists('vms_dt_reporting_resolve_labor_row')) {
    function vms_dt_reporting_resolve_labor_row(int $event_plan_id, array $slot, array $role_meta, array $assignment): array
    {
        $staff_id = isset($assignment['staff_id']) ? absint($assignment['staff_id']) : 0;
        $staff_name = $staff_id > 0 ? (string) get_the_title($staff_id) : __('Unknown staff', 'vms-data-tools');
        if ($staff_name === '') {
            $staff_name = sprintf(__('Staff #%d', 'vms-data-tools'), $staff_id);
        }

        $role_name = isset($slot['role_name']) && (string) $slot['role_name'] !== ''
            ? (string) $slot['role_name']
            : __('Staff role', 'vms-data-tools');

        $assignment_status = isset($assignment['status']) ? sanitize_key((string) $assignment['status']) : '';
        $pay_type = isset($assignment['pay_type_override']) ? sanitize_key((string) $assignment['pay_type_override']) : '';
        if ($pay_type === '') {
            $pay_type = isset($slot['pay_type']) ? sanitize_key((string) $slot['pay_type']) : 'inherit_role';
        }
        $role_pay_type = isset($role_meta['default_pay_type']) ? sanitize_key((string) $role_meta['default_pay_type']) : 'none';
        if ($pay_type === 'inherit_role') {
            $pay_type = $role_pay_type;
        }
        if (!in_array($pay_type, array('hourly', 'flat', 'none'), true)) {
            $pay_type = 'none';
        }

        $pay_rate = null;
        if (isset($assignment['pay_rate_override']) && $assignment['pay_rate_override'] !== '' && $assignment['pay_rate_override'] !== null && is_numeric($assignment['pay_rate_override'])) {
            $pay_rate = (float) $assignment['pay_rate_override'];
        } elseif (isset($slot['pay_rate']) && $slot['pay_rate'] !== '' && $slot['pay_rate'] !== null && is_numeric($slot['pay_rate'])) {
            $pay_rate = (float) $slot['pay_rate'];
        } elseif (isset($role_meta['default_rate']) && $role_meta['default_rate'] !== '' && $role_meta['default_rate'] !== null && is_numeric($role_meta['default_rate'])) {
            $pay_rate = (float) $role_meta['default_rate'];
        }

        $time_mode = isset($slot['shift_time_mode']) ? sanitize_key((string) $slot['shift_time_mode']) : 'absolute';
        if (!in_array($time_mode, array('absolute', 'relative'), true)) {
            $time_mode = 'absolute';
        }
        $raw_start = isset($slot['shift_start_local']) ? trim((string) $slot['shift_start_local']) : '';
        $raw_end = isset($slot['shift_end_local']) ? trim((string) $slot['shift_end_local']) : '';
        $partial_absolute = ($time_mode === 'absolute' && (($raw_start !== '' && $raw_end === '') || ($raw_start === '' && $raw_end !== '')));

        $window = vms_dt_has_core_function('vms_staffing_resolve_slot_window')
            ? (array) vms_dt_call_core_function('vms_staffing_resolve_slot_window', $event_plan_id, $slot)
            : array();

        $window_source = 'none';
        if ($time_mode === 'relative') {
            $window_source = 'relative';
        } elseif ($raw_start !== '' || $raw_end !== '') {
            $window_source = 'slot';
        } elseif (!empty($window['start_ts']) || !empty($window['end_ts'])) {
            $window_source = 'event_fallback';
        }

        $start_label = '';
        $end_label = '';
        if ($raw_start !== '') {
            $start_label = $raw_start;
        } elseif (($window['start_local'] ?? null) instanceof DateTimeImmutable) {
            $start_label = $window['start_local']->format('g:i A');
        }
        if ($raw_end !== '') {
            $end_label = $raw_end;
        } elseif (($window['end_local'] ?? null) instanceof DateTimeImmutable) {
            $end_label = $window['end_local']->format('g:i A');
        }

        $duration_minutes = isset($window['duration_minutes']) ? (int) $window['duration_minutes'] : 0;
        $hours = $duration_minutes > 0 ? round($duration_minutes / 60, 2) : 0.0;

        $row = array(
            'staff_id' => $staff_id,
            'staff_name' => $staff_name,
            'role_name' => $role_name,
            'assignment_status' => $assignment_status,
            'pay_type' => $pay_type,
            'pay_rate' => $pay_rate,
            'pay_rate_label' => ($pay_rate !== null && $pay_rate >= 0)
                ? (($pay_type === 'hourly') ? sprintf(__('%s / hr', 'vms-data-tools'), vms_dt_rr_money((int) round($pay_rate * 100))) : vms_dt_rr_money((int) round($pay_rate * 100)))
                : __('Missing', 'vms-data-tools'),
            'time_mode' => $time_mode,
            'window_source' => $window_source,
            'shift_start' => $start_label,
            'shift_end' => $end_label,
            'duration_minutes' => $duration_minutes,
            'hours' => $hours,
            'cost_cents' => 0,
            'included' => false,
            'reason' => '',
            'overlap_flag' => false,
        );

        if ($pay_type === 'none') {
            $row['reason'] = __('Excluded because this staffing role is set to no pay.', 'vms-data-tools');
            return $row;
        }
        if ($pay_rate === null || $pay_rate < 0) {
            $row['reason'] = __('Excluded because the pay rate is missing.', 'vms-data-tools');
            return $row;
        }
        if ($pay_type === 'flat') {
            $row['included'] = true;
            $row['cost_cents'] = (int) round($pay_rate * 100);
            if ($partial_absolute) {
                $row['reason'] = __('Flat pay included, but only one side of the shift window is filled. Complete both times so scheduling and budget evidence stay trustworthy.', 'vms-data-tools');
            }
            return $row;
        }
        if ($partial_absolute && $duration_minutes <= 0) {
            $row['reason'] = __('Excluded because only one side of the shift window is filled and VMS could not infer a usable duration. Enter both start and end times, add a duration, or leave both times blank to use the event window.', 'vms-data-tools');
            return $row;
        }
        if ($duration_minutes <= 0) {
            $row['reason'] = __('Excluded because the shift duration could not be resolved.', 'vms-data-tools');
            return $row;
        }

        $row['included'] = true;
        $row['cost_cents'] = (int) round($pay_rate * ($duration_minutes / 60) * 100);
        if ($partial_absolute) {
            $row['reason'] = __('Hourly budget included using an inferred shift window because only one side of the shift time is filled. Review the staffing time so the labor evidence is explicit.', 'vms-data-tools');
        } elseif ($window_source === 'event_fallback') {
            $row['reason'] = __('Hourly budget is using the event start/end window because this staffing role has no explicit shift times.', 'vms-data-tools');
        }

        return $row;
    }
}


if (!function_exists('vms_dt_reporting_calculate_labor_overhead')) {
    function vms_dt_reporting_calculate_labor_overhead(int $event_plan_id): array
    {
        $event_plan_id = absint($event_plan_id);
        static $memory_cache = array();
        if ($event_plan_id > 0 && isset($memory_cache[$event_plan_id]) && is_array($memory_cache[$event_plan_id])) {
            if (vms_dt_has_core_function('vms_resource_fingerprint_flag')) {
                vms_dt_call_core_function('vms_resource_fingerprint_flag', 'dt_labor_overhead_cache', 'memory_hit');
            }
            return $memory_cache[$event_plan_id];
        }
        if ($event_plan_id <= 0) {
            return array(
                'labor_overhead_cents' => 0,
                'assigned_staff_count' => 0,
                'included_assignment_count' => 0,
                'assigned_slot_count' => 0,
                'detail_rows' => array(),
                'warnings' => array(),
            );
        }
        if (!vms_dt_has_core_function('vms_staffing_get_event_slots')) {
            return array(
                'labor_overhead_cents' => 0,
                'assigned_staff_count' => 0,
                'included_assignment_count' => 0,
                'assigned_slot_count' => 0,
                'detail_rows' => array(),
                'warnings' => array(),
            );
        }

        if (vms_dt_has_core_function('vms_resource_fingerprint_span_start')) {
            vms_dt_call_core_function('vms_resource_fingerprint_span_start', 'dt.labor_overhead', array('event_plan_id' => $event_plan_id));
        }
        $slots = (array) vms_dt_call_core_function('vms_staffing_get_event_slots', $event_plan_id, true);
        $labor_total_cents = 0;
        $assigned_staff_count = 0;
        $included_assignment_count = 0;
        $assigned_slot_count = 0;
        $warnings = array();
        $detail_rows = array();
        $overlap_windows_by_staff = array();

        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }
            $slot_status = isset($slot['status']) ? sanitize_key((string) $slot['status']) : 'active';
            if ($slot_status === 'canceled') {
                continue;
            }

            $assignments = isset($slot['assignments']) && is_array($slot['assignments']) ? $slot['assignments'] : array();
            $active_assignments = array();
            foreach ($assignments as $assignment) {
                if (!is_array($assignment)) {
                    continue;
                }
                $assignment_status = isset($assignment['status']) ? sanitize_key((string) $assignment['status']) : '';
                if (!in_array($assignment_status, array('proposed', 'confirmed'), true)) {
                    continue;
                }
                $staff_id = isset($assignment['staff_id']) ? absint($assignment['staff_id']) : 0;
                if ($staff_id <= 0) {
                    continue;
                }
                $active_assignments[] = $assignment;
            }

            if (empty($active_assignments)) {
                continue;
            }

            $assigned_slot_count++;
            $role_meta = isset($slot['role_meta']) && is_array($slot['role_meta']) ? $slot['role_meta'] : array();

            foreach ($active_assignments as $assignment) {
                $assigned_staff_count++;
                $detail = function_exists('vms_dt_reporting_resolve_labor_row')
                    ? (array) vms_dt_reporting_resolve_labor_row($event_plan_id, $slot, $role_meta, $assignment)
                    : array();

                if (empty($detail)) {
                    $role_name = isset($slot['role_name']) ? (string) $slot['role_name'] : __('Staff role', 'vms-data-tools');
                    $warnings[] = sprintf(__('Labor cost excluded for %s because staffing detail could not be resolved.', 'vms-data-tools'), $role_name);
                    continue;
                }

                $staff_id = isset($detail['staff_id']) ? absint($detail['staff_id']) : 0;
                $start_label = trim((string) ($detail['shift_start'] ?? ''));
                $end_label = trim((string) ($detail['shift_end'] ?? ''));
                $start_ts = null;
                $end_ts = null;
                if ($staff_id > 0 && !empty($detail['included']) && vms_dt_has_core_function('vms_staffing_resolve_slot_window')) {
                    $window = (array) vms_dt_call_core_function('vms_staffing_resolve_slot_window', $event_plan_id, $slot);
                    $start_ts = isset($window['start_ts']) && is_numeric($window['start_ts']) ? (int) $window['start_ts'] : null;
                    $end_ts = isset($window['end_ts']) && is_numeric($window['end_ts']) ? (int) $window['end_ts'] : null;
                    if (is_int($start_ts) && is_int($end_ts) && $end_ts > $start_ts) {
                        if (!isset($overlap_windows_by_staff[$staff_id])) {
                            $overlap_windows_by_staff[$staff_id] = array();
                        }
                        foreach ($overlap_windows_by_staff[$staff_id] as $existing_window) {
                            $existing_start = isset($existing_window['start_ts']) ? (int) $existing_window['start_ts'] : null;
                            $existing_end = isset($existing_window['end_ts']) ? (int) $existing_window['end_ts'] : null;
                            if (!is_int($existing_start) || !is_int($existing_end)) {
                                continue;
                            }
                            if ($start_ts < $existing_end && $end_ts > $existing_start) {
                                $detail['overlap_flag'] = true;
                                $detail['reason'] = trim((string) ($detail['reason'] ?? ''));
                                $overlap_note = __('Overlaps another included role for this same staff member. Budget may overstate pay when one person covers multiple roles in one shift.', 'vms-data-tools');
                                if ($detail['reason'] === '') {
                                    $detail['reason'] = $overlap_note;
                                } elseif (strpos($detail['reason'], $overlap_note) === false) {
                                    $detail['reason'] .= ' ' . $overlap_note;
                                }
                                $warnings[] = sprintf(__('Potential labor overlap for %s. Budget may overstate pay when one person covers multiple roles in one shift.', 'vms-data-tools'), (string) ($detail['staff_name'] ?? __('Staff member', 'vms-data-tools')));
                                break;
                            }
                        }
                        $overlap_windows_by_staff[$staff_id][] = array(
                            'start_ts' => $start_ts,
                            'end_ts' => $end_ts,
                        );
                    }
                }

                $detail_rows[] = $detail;
                if (!empty($detail['included'])) {
                    $included_assignment_count++;
                    $labor_total_cents += (int) ($detail['cost_cents'] ?? 0);
                }
                if (!empty($detail['reason'])) {
                    $warnings[] = (string) $detail['reason'];
                } elseif (empty($detail['included'])) {
                    $role_name = (string) ($detail['role_name'] ?? __('Staff role', 'vms-data-tools'));
                    $warnings[] = sprintf(__('Labor cost excluded for %s.', 'vms-data-tools'), $role_name);
                }
            }
        }

        $result = array(
            'labor_overhead_cents' => max(0, (int) $labor_total_cents),
            'assigned_staff_count' => $assigned_staff_count,
            'included_assignment_count' => $included_assignment_count,
            'assigned_slot_count' => $assigned_slot_count,
            'detail_rows' => $detail_rows,
            'warnings' => array_values(array_unique(array_filter(array_map('strval', $warnings)))),
        );
        $memory_cache[$event_plan_id] = $result;
        if (vms_dt_has_core_function('vms_resource_fingerprint_span_finish')) {
            vms_dt_call_core_function('vms_resource_fingerprint_span_finish', 'dt.labor_overhead', array(
                'event_plan_id' => $event_plan_id,
                'included_assignment_count' => $included_assignment_count,
                'assigned_slot_count' => $assigned_slot_count,
            ));
        }
        return $result;
    }
}

if (!function_exists('vms_dt_reporting_calculate_event_costs')) {
    function vms_dt_reporting_calculate_event_costs(array $row, array $evidence = array()): array
    {
        $event_plan_id = (int) ($row['event_plan_id'] ?? 0);
        if (vms_dt_has_core_function('vms_resource_fingerprint_span_start')) {
            vms_dt_call_core_function('vms_resource_fingerprint_span_start', 'dt.event_costs', array('event_plan_id' => $event_plan_id));
        }
        $ticket_sources = vms_dt_reporting_build_ticket_source_rollup($row, $evidence);
        $ticket_sales_total_cents = max(0, (int) ($ticket_sources['paid_ticket_revenue_cents'] ?? 0));
        $ticket_qty_total = max(0, (int) ($ticket_sources['ticketed_attendance_qty'] ?? 0));
        $paid_ticket_qty_total = max(0, (int) ($ticket_sources['paid_ticket_qty_total'] ?? 0));
        $free_ticket_qty_excluded = max(0, (int) ($ticket_sources['free_ticket_qty_total'] ?? 0));
        if ($paid_ticket_qty_total <= 0 && $free_ticket_qty_excluded <= 0) {
            $paid_ticket_qty_total = $ticket_qty_total;
        }

        $other_direct_costs_cents = max(0, (int) get_post_meta($event_plan_id, '_vms_event_direct_costs_cents', true));
        $labor = vms_dt_reporting_calculate_labor_overhead($event_plan_id);
        $labor_overhead_cents = max(0, (int) ($labor['labor_overhead_cents'] ?? 0));
        $band_vendor_id = (int) get_post_meta($event_plan_id, '_vms_band_vendor_id', true);
        $band_name = $band_vendor_id > 0 ? (string) get_the_title($band_vendor_id) : '';
        $terms = vms_dt_reporting_get_event_comp_terms($event_plan_id);
        $structure = sanitize_key((string) ($terms['structure'] ?? 'flat_fee'));
        if ($structure === '') {
            $structure = 'flat_fee';
        }
        $flat = is_numeric($terms['flat_fee_amount'] ?? null) ? max(0.0, (float) $terms['flat_fee_amount']) : 0.0;
        $split_pct = is_numeric($terms['door_split_percent'] ?? null) ? max(0.0, min(100.0, (float) $terms['door_split_percent'])) : 0.0;
        $door_split_cents = (int) round($ticket_sales_total_cents * ($split_pct / 100));
        $base_payout_cents = 0;
        $bonus_cents = 0;
        $structure_label = vms_dt_has_core_function('vms_pretty_structure_label') ? (string) vms_dt_call_core_function('vms_pretty_structure_label', $structure) : ucwords(str_replace('_', ' ', $structure));
        $bonus_hit = false;
        $bonus_note = '';

        if ($structure === 'attendance_bonus' && vms_dt_has_core_function('vms_calculate_attendance_bonus_payout')) {
            $bonus_calc = (array) vms_dt_call_core_function('vms_calculate_attendance_bonus_payout', $terms, $paid_ticket_qty_total);
            $base_payout_cents = (int) round(((float) ($bonus_calc['base_pay'] ?? 0.0)) * 100);
            $bonus_cents = (int) round(((float) ($bonus_calc['bonus'] ?? 0.0)) * 100);
            $bonus_hit = $bonus_cents > 0;
            $mode = (string) ($bonus_calc['mode'] ?? '');
            if ($mode === 'step') {
                $steps = (int) ($bonus_calc['steps_reached'] ?? 0);
                $bonus_note = $steps > 0 ? sprintf(__('Attendance bonus hit (%d step(s)).', 'vms-data-tools'), $steps) : __('Attendance bonus not reached yet.', 'vms-data-tools');
            } elseif ($mode === 'continuous') {
                $bonus_note = $bonus_hit ? __('Attendance bonus earned.', 'vms-data-tools') : __('Attendance bonus not reached yet.', 'vms-data-tools');
            }
            if ($free_ticket_qty_excluded > 0) {
                $bonus_note .= ' ' . sprintf(__('Excluded %d comp/free ticket(s) from the bonus count basis.', 'vms-data-tools'), $free_ticket_qty_excluded);
            }
        } elseif ($structure === 'door_split') {
            $base_payout_cents = $door_split_cents;
            $bonus_note = $split_pct > 0 ? sprintf(__('Door split only (%s%% of ticket sales).', 'vms-data-tools'), rtrim(rtrim(number_format($split_pct, 2, '.', ''), '0'), '.')) : '';
        } elseif ($structure === 'flat_fee_door_split') {
            $base_payout_cents = (int) round($flat * 100) + $door_split_cents;
            $bonus_note = $split_pct > 0 ? sprintf(__('Flat fee plus %s%% door split.', 'vms-data-tools'), rtrim(rtrim(number_format($split_pct, 2, '.', ''), '0'), '.')) : __('Flat fee plus door split.', 'vms-data-tools');
        } else {
            $base_payout_cents = (int) round($flat * 100);
        }

        $band_payout_cents = max(0, $base_payout_cents + $bonus_cents);
        $known_cost_total_cents = $band_payout_cents + $labor_overhead_cents + $other_direct_costs_cents;
        $total_collected_cents = (int) ($row['square_scope_total_collected_cents'] ?? 0);
        $counted_total_cents = (int) ($row['counted_total_cents'] ?? 0);
        $ticket_sales_cover_band = $ticket_sales_total_cents >= $band_payout_cents;
        $ticket_sales_cover_known = $ticket_sales_total_cents >= $known_cost_total_cents;

        $result = array(
            'event_plan_id' => $event_plan_id,
            'band_vendor_id' => $band_vendor_id,
            'band_name' => $band_name,
            'ticket_sales_total_cents' => $ticket_sales_total_cents,
            'ticket_qty_total' => $ticket_qty_total,
            'paid_ticket_qty_total' => $paid_ticket_qty_total,
            'free_ticket_qty_excluded' => $free_ticket_qty_excluded,
            'ticket_sources' => $ticket_sources,
            'structure' => $structure,
            'structure_label' => $structure_label,
            'split_percent' => $split_pct,
            'door_split_cents' => $door_split_cents,
            'base_payout_cents' => $base_payout_cents,
            'bonus_cents' => $bonus_cents,
            'band_payout_cents' => $band_payout_cents,
            'bonus_hit' => $bonus_hit,
            'bonus_note' => $bonus_note,
            'labor_overhead_cents' => $labor_overhead_cents,
            'assigned_staff_count' => (int) ($labor['assigned_staff_count'] ?? 0),
            'included_assignment_count' => (int) ($labor['included_assignment_count'] ?? 0),
            'assigned_slot_count' => (int) ($labor['assigned_slot_count'] ?? 0),
            'labor_warnings' => (array) ($labor['warnings'] ?? array()),
            'labor_detail_rows' => (array) ($labor['detail_rows'] ?? array()),
            'other_direct_costs_cents' => $other_direct_costs_cents,
            'known_cost_total_cents' => $known_cost_total_cents,
            'ticket_sales_cover_band' => $ticket_sales_cover_band,
            'ticket_sales_cover_known' => $ticket_sales_cover_known,
            'counted_net_after_known_costs_cents' => $counted_total_cents - $known_cost_total_cents,
            'collected_net_after_known_costs_cents' => $total_collected_cents - $known_cost_total_cents,
            'terms' => $terms,
        );
        if (vms_dt_has_core_function('vms_resource_fingerprint_span_finish')) {
            vms_dt_call_core_function('vms_resource_fingerprint_span_finish', 'dt.event_costs', array(
                'event_plan_id' => $event_plan_id,
                'ticket_sales_total_cents' => $ticket_sales_total_cents,
                'labor_overhead_cents' => $labor_overhead_cents,
            ));
        }
        return $result;
    }
}

if (!function_exists('vms_dt_reporting_render_event_picker')) {
    function vms_dt_reporting_render_event_picker(string $page_slug, array $filters, array $args = array()): void
    {
        $events = function_exists('vms_dt_rr_get_event_options') ? vms_dt_rr_get_event_options() : array();
        $square_locations = function_exists('vms_dt_rr_get_square_location_options') ? vms_dt_rr_get_square_location_options() : array();
        $show_sold = !empty($args['show_sold_dates']);
        $show_compare_event = !empty($args['show_compare_event']);
        $compare_event_id = isset($args['compare_event_id']) ? max(0, (int) $args['compare_event_id']) : 0;
        $hidden_fields = isset($args['hidden_fields']) && is_array($args['hidden_fields']) ? $args['hidden_fields'] : array();
        ?>
        <form method="get" action="" class="vms-dt-card vms-dt-section">
            <input type="hidden" name="page" value="<?php echo esc_attr($page_slug); ?>" />
            <?php foreach ($hidden_fields as $hidden_key => $hidden_value) : ?>
                <?php
                $hidden_key = sanitize_key((string) $hidden_key);
                if ($hidden_key === '' || $hidden_value === null) {
                    continue;
                }
                if (is_array($hidden_value)) {
                    $hidden_value = implode(',', array_map('sanitize_text_field', array_map('strval', $hidden_value)));
                }
                ?>
                <input type="hidden" name="<?php echo esc_attr($hidden_key); ?>" value="<?php echo esc_attr((string) $hidden_value); ?>" />
            <?php endforeach; ?>
            <div class="vms-dt-card-head">
                <div>
                    <h2><?php echo esc_html($args['title'] ?? __('Filters', 'vms-data-tools')); ?></h2>
                    <p class="vms-dt-section-desc"><?php echo esc_html($args['desc'] ?? __('Pick the event and reporting scope.', 'vms-data-tools')); ?></p>
                </div>
            </div>
            <div class="vms-dt-filter-grid">
                <div class="vms-dt-field">
                    <label for="vms-dt-report-event-plan"><?php esc_html_e('Event plan', 'vms-data-tools'); ?></label>
                    <select id="vms-dt-report-event-plan" name="event_plan_id">
                        <?php foreach ($events as $event) : ?>
                            <option value="<?php echo esc_attr((string) ($event['id'] ?? 0)); ?>" <?php selected((int) ($filters['event_plan_id'] ?? 0), (int) ($event['id'] ?? 0)); ?>>
                                <?php echo esc_html((string) ($event['label'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($show_compare_event) : ?>
                <div class="vms-dt-field">
                    <label for="vms-dt-report-compare-event"><?php esc_html_e('Compare pace to event', 'vms-data-tools'); ?></label>
                    <select id="vms-dt-report-compare-event" name="event_plan_b">
                        <option value="0"><?php esc_html_e('Historical average only', 'vms-data-tools'); ?></option>
                        <?php foreach ($events as $event) : ?>
                            <option value="<?php echo esc_attr((string) ($event['id'] ?? 0)); ?>" <?php selected($compare_event_id, (int) ($event['id'] ?? 0)); ?>>
                                <?php echo esc_html((string) ($event['label'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Optional. Pick a specific past event to compare pace by days-out instead of relying only on averages.', 'vms-data-tools'); ?></p>
                </div>
                <?php endif; ?>
                <div class="vms-dt-field">
                    <label for="vms-dt-report-square-location"><?php esc_html_e('Square location', 'vms-data-tools'); ?></label>
                    <select id="vms-dt-report-square-location" name="square_location_id">
                        <option value=""><?php echo count($square_locations) <= 1 ? esc_html__('Use configured location', 'vms-data-tools') : esc_html__('Select a Square location', 'vms-data-tools'); ?></option>
                        <?php foreach ($square_locations as $location_id => $label) : ?>
                            <option value="<?php echo esc_attr((string) $location_id); ?>" <?php selected((string) ($filters['square_location_id'] ?? ''), (string) $location_id); ?>><?php echo esc_html((string) $label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="vms-dt-field">
                    <label for="vms-dt-report-square-scope"><?php esc_html_e('Square scope', 'vms-data-tools'); ?></label>
                    <select id="vms-dt-report-square-scope" name="square_scope_mode">
                        <?php foreach (vms_dt_rr_square_scope_options() as $scope_key => $scope_label) : ?>
                            <option value="<?php echo esc_attr((string) $scope_key); ?>" <?php selected((string) ($filters['square_scope_mode'] ?? 'full_day'), (string) $scope_key); ?>><?php echo esc_html((string) $scope_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($show_sold) : ?>
                    <div class="vms-dt-field">
                        <label for="vms-dt-report-sold-from"><?php esc_html_e('Sold date from', 'vms-data-tools'); ?></label>
                        <input id="vms-dt-report-sold-from" type="date" name="sold_from" value="<?php echo esc_attr((string) ($filters['sold_from'] ?? '')); ?>" />
                    </div>
                    <div class="vms-dt-field">
                        <label for="vms-dt-report-sold-to"><?php esc_html_e('Sold date to', 'vms-data-tools'); ?></label>
                        <input id="vms-dt-report-sold-to" type="date" name="sold_to" value="<?php echo esc_attr((string) ($filters['sold_to'] ?? '')); ?>" />
                    </div>
                <?php endif; ?>
            </div>
            <div class="vms-dt-toolbar"><div class="vms-dt-toolbar-left"><button type="submit" class="button button-primary"><?php esc_html_e('Refresh report', 'vms-data-tools'); ?></button></div></div>
        </form>
        <?php
    }
}


if (!function_exists('vms_dt_reporting_support_link_html')) {
    function vms_dt_reporting_support_link_html(string $anchor, string $detail_section = 'single_event_supporting'): string
    {
        $anchor = trim($anchor);
        if ($anchor === '') {
            return '';
        }

        $href = vms_dt_reporting_support_link_url($anchor, $detail_section);
        $attrs = '';
        if ($detail_section === '' || vms_dt_reporting_is_detail_enabled($detail_section)) {
            $attrs = ' data-vms-open-target="' . esc_attr($anchor) . '"';
        }

        return '<p class="vms-dt-kpi-link"><a href="' . esc_url($href) . '"' . $attrs . '>' . esc_html__('View supporting data ↓', 'vms-data-tools') . '</a></p>';
    }
}

if (!function_exists('vms_dt_reporting_sum_gross_cents')) {
    function vms_dt_reporting_sum_gross_cents(array $rows): int
    {
        $sum = 0;
        foreach ($rows as $row) {
            if (is_array($row)) {
                $sum += (int) ($row['gross_cents'] ?? 0);
            }
        }
        return $sum;
    }
}


if (!function_exists('vms_dt_reporting_sum_net_cents')) {
    function vms_dt_reporting_sum_net_cents(array $rows): int
    {
        $sum = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (array_key_exists('net_subtotal_cents', $row)) {
                $sum += (int) ($row['net_subtotal_cents'] ?? 0);
                continue;
            }
            if (array_key_exists('net_cents', $row)) {
                $sum += (int) ($row['net_cents'] ?? 0);
                continue;
            }
            $gross = (int) ($row['gross_cents'] ?? 0);
            $tax = (int) ($row['tax_cents'] ?? 0);
            $sum += max(0, $gross - $tax);
        }
        return $sum;
    }
}

if (!function_exists('vms_dt_reporting_sum_tax_cents')) {
    function vms_dt_reporting_sum_tax_cents(array $rows): int
    {
        $sum = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $sum += (int) ($row['tax_cents'] ?? 0);
        }
        return $sum;
    }
}

if (!function_exists('vms_dt_reporting_sum_qty')) {
    function vms_dt_reporting_sum_qty(array $rows): int
    {
        $sum = 0;
        foreach ($rows as $row) {
            if (is_array($row)) {
                $sum += (int) ($row['quantity'] ?? 0);
            }
        }
        return $sum;
    }
}

if (!function_exists('vms_dt_reporting_unique_order_count')) {
    function vms_dt_reporting_unique_order_count(array $rows, string $key = 'square_order_id'): int
    {
        $seen = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $value = trim((string) ($row[$key] ?? ''));
            if ($value === '') {
                $value = trim((string) ($row['order_id'] ?? ''));
            }
            if ($value !== '') {
                $seen[$value] = true;
            }
        }
        return count($seen);
    }
}

if (!function_exists('vms_dt_reporting_row_financial_totals')) {
    function vms_dt_reporting_row_financial_totals(array $rows, string $order_key = 'square_order_id'): array
    {
        return array(
            'gross_cents' => vms_dt_reporting_sum_gross_cents($rows),
            'tax_cents' => vms_dt_reporting_sum_tax_cents($rows),
            'net_cents' => vms_dt_reporting_sum_net_cents($rows),
            'qty' => vms_dt_reporting_sum_qty($rows),
            'order_count' => vms_dt_reporting_unique_order_count($rows, $order_key),
        );
    }
}

if (!function_exists('vms_dt_reporting_extract_embedded_event_date')) {
    function vms_dt_reporting_extract_embedded_event_date(string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})\b/', $label, $matches)) {
            return (string) ($matches[1] ?? '');
        }

        return '';
    }
}

if (!function_exists('vms_dt_reporting_future_event_ticket_rows')) {
    function vms_dt_reporting_future_event_ticket_rows(array $rows, string $current_event_date): array
    {
        $current_event_date = trim($current_event_date);
        if ($current_event_date === '') {
            return array();
        }

        $future_rows = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $embedded_event_date = vms_dt_reporting_extract_embedded_event_date((string) ($row['line_name'] ?? ''));
            if ($embedded_event_date === '' || strcmp($embedded_event_date, $current_event_date) <= 0) {
                continue;
            }

            $row['embedded_event_date'] = $embedded_event_date;
            $future_rows[] = $row;
        }

        usort($future_rows, static function (array $left, array $right): int {
            $date_cmp = strcmp((string) ($left['embedded_event_date'] ?? ''), (string) ($right['embedded_event_date'] ?? ''));
            if ($date_cmp !== 0) {
                return $date_cmp;
            }
            return strcmp((string) ($left['closed_at_utc'] ?? ''), (string) ($right['closed_at_utc'] ?? ''));
        });

        return $future_rows;
    }
}

if (!function_exists('vms_dt_reporting_build_money_reconciliation')) {
    function vms_dt_reporting_build_money_reconciliation(array $row, array $summary, array $costs): array
    {
        $square_total_collected_cents = (int) ($summary['square_collected_cents'] ?? ($row['square_scope_total_collected_cents'] ?? 0));
        $website_originated_cents = (int) ($summary['website_collected_cents'] ?? ($row['website_total_cents'] ?? 0));
        $direct_square_pos_cents = (int) ($summary['direct_square_pos_cents'] ?? max(0, $square_total_collected_cents - $website_originated_cents));
        $tips_cents = (int) ($row['square_scope_tip_cents'] ?? ($summary['tips_cents'] ?? 0));
        $service_charge_cents = (int) ($row['square_scope_service_cents'] ?? 0);
        $website_refunds_face_cents = (int) (($row['website_ticket_refunded_cents'] ?? 0) + ($row['website_addon_refunded_cents'] ?? 0));
        $square_overlap_excluded_cents = (int) ($row['square_overlap_excluded_cents'] ?? 0);
        $square_unclassified_cents = (int) ($row['square_unclassified_cents'] ?? 0);
        $square_ignored_cents = (int) ($row['square_ignored_cents'] ?? 0);
        $profitability_basis_cents = (int) ($row['counted_total_cents'] ?? 0);
        $known_cost_total_cents = (int) ($costs['known_cost_total_cents'] ?? 0);
        $net_after_known_costs_cents = (int) ($costs['counted_net_after_known_costs_cents'] ?? ($profitability_basis_cents - $known_cost_total_cents));

        return array(
            'square_total_collected_cents' => $square_total_collected_cents,
            'website_originated_cents' => $website_originated_cents,
            'direct_square_pos_cents' => $direct_square_pos_cents,
            'tips_cents' => $tips_cents,
            'service_charge_cents' => $service_charge_cents,
            'website_refunds_face_cents' => $website_refunds_face_cents,
            'square_overlap_excluded_cents' => $square_overlap_excluded_cents,
            'square_unclassified_cents' => $square_unclassified_cents,
            'square_ignored_cents' => $square_ignored_cents,
            'profitability_basis_cents' => $profitability_basis_cents,
            'known_cost_total_cents' => $known_cost_total_cents,
            'net_after_known_costs_cents' => $net_after_known_costs_cents,
            'combined_counted_reference_cents' => (int) ($summary['combined_counted_cents'] ?? 0),
        );
    }
}



if (!function_exists('vms_dt_reporting_ticket_channel_label')) {
    function vms_dt_reporting_ticket_channel_label(array $source): string
    {
        $code = (string) ($source['code'] ?? '');
        $label = (string) ($source['label'] ?? '');
        $blob = strtolower(trim($code . ' ' . $label));
        if (strpos($blob, 'payment link') !== false || strpos($blob, 'door qr') !== false || strpos($blob, 'qr') !== false) {
            return 'QR / Payment Link';
        }
        if (strpos($blob, 'register') !== false || strpos($blob, 'in person') !== false || strpos($blob, 'pos') !== false) {
            return 'Register / POS';
        }
        if (strpos($blob, 'woo') !== false || strpos($blob, 'website') !== false || strpos($blob, 'online') !== false) {
            return 'Website / Online';
        }
        return $label !== '' ? $label : 'Unknown';
    }
}

if (!function_exists('vms_dt_reporting_square_fetch_window_bounds')) {
    function vms_dt_reporting_square_fetch_window_bounds(int $event_plan_id, array $filters): array
    {
        $window = vms_dt_rr_build_event_window($event_plan_id, (string) ($filters['square_scope_mode'] ?? 'full_day'));
        $scan_start_utc = (string) ($window['window_start_utc'] ?? '');
        $scan_end_utc = (string) ($window['window_end_utc'] ?? '');

        if ($scan_start_utc !== '' && !empty($filters['sold_from'])) {
            try {
                $sold_floor = (new DateTimeImmutable((string) $filters['sold_from'] . ' 00:00:00', wp_timezone()))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                if ($sold_floor > $scan_start_utc) {
                    $scan_start_utc = $sold_floor;
                }
            } catch (Throwable $e) {
            }
        }
        if ($scan_end_utc !== '' && !empty($filters['sold_to'])) {
            try {
                $sold_ceiling = (new DateTimeImmutable((string) $filters['sold_to'] . ' 00:00:00', wp_timezone()))->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                if ($sold_ceiling < $scan_end_utc) {
                    $scan_end_utc = $sold_ceiling;
                }
            } catch (Throwable $e) {
            }
        }

        return array(
            'window' => $window,
            'scan_start_utc' => $scan_start_utc,
            'scan_end_utc' => $scan_end_utc,
        );
    }
}

if (!function_exists('vms_dt_reporting_square_fetch_audit_analyze_rows')) {
    function vms_dt_reporting_square_fetch_audit_analyze_rows(array $rows, array $location_labels, array $selected_location_ids, array $selected_window, array $report_fetch_ids): array
    {
        $orders = array();
        $ticket_rows = array();
        $summary = array(
            'orders_count' => 0,
            'ticket_qty' => 0,
            'ticket_gross_cents' => 0,
            'report_fetch_ticket_qty' => 0,
            'report_fetch_ticket_gross_cents' => 0,
            'not_in_report_ticket_qty' => 0,
            'not_in_report_ticket_gross_cents' => 0,
            'other_location_ticket_qty' => 0,
            'other_location_ticket_gross_cents' => 0,
            'outside_scope_ticket_qty' => 0,
            'outside_scope_ticket_gross_cents' => 0,
        );

        $selected_start = (string) ($selected_window['window_start_utc'] ?? '');
        $selected_end = (string) ($selected_window['window_end_utc'] ?? '');

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $order_id = trim((string) ($row['square_order_id'] ?? ''));
            $location_id = trim((string) ($row['location_id'] ?? ''));
            $closed_at_utc = trim((string) ($row['closed_at_utc'] ?? ''));
            $source = vms_dt_rr_square_source_classification((string) ($row['raw_json'] ?? ''), (string) ($row['tenders_json'] ?? ''));
            $channel = vms_dt_reporting_ticket_channel_label($source);
            $line_items = json_decode((string) ($row['line_items_json'] ?? ''), true);
            if (!is_array($line_items)) {
                $line_items = array();
            }

            $in_selected_location = $location_id !== '' && in_array($location_id, $selected_location_ids, true);
            $in_selected_scope = ($closed_at_utc !== '' && $selected_start !== '' && $selected_end !== '' && $closed_at_utc >= $selected_start && $closed_at_utc < $selected_end);
            $in_report_fetch = $order_id !== '' && isset($report_fetch_ids[$order_id]);
            $ticket_qty = 0;
            $ticket_gross = 0;
            $ticket_names = array();

            foreach ($line_items as $line_item) {
                if (!is_array($line_item)) {
                    continue;
                }
                $line_name = trim((string) ($line_item['name'] ?? ''));
                $variation_name = trim((string) ($line_item['variation_name'] ?? ''));
                $sku = trim((string) ($line_item['catalog_object_id'] ?? ''));
                $qty = isset($line_item['quantity']) ? max(0, (int) round((float) $line_item['quantity'])) : 0;
                $gross = function_exists('vms_square_money_amount_from_money_obj') ? (int) vms_square_money_amount_from_money_obj($line_item, 'total_money') : 0;
                $ticketish = vms_dt_rr_line_item_looks_ticketish($line_item, '');
                if (!$ticketish) {
                    continue;
                }

                $ticket_qty += $qty;
                $ticket_gross += $gross;
                $ticket_names[] = $line_name !== '' ? $line_name : ($variation_name !== '' ? $variation_name : __('Ticket row', 'vms-data-tools'));

                $notes = array();
                if (!$in_selected_location) {
                    $notes[] = __('Different Square location than current report filter.', 'vms-data-tools');
                }
                if (!$in_selected_scope) {
                    $notes[] = __('Outside the current report time scope.', 'vms-data-tools');
                }
                if (!$in_report_fetch) {
                    $notes[] = __('Not present in the current report fetch set.', 'vms-data-tools');
                }
                if (empty($notes)) {
                    $notes[] = __('Seen by current report fetch.', 'vms-data-tools');
                }

                $ticket_rows[] = array(
                    'square_order_id' => $order_id,
                    'location_id' => $location_id,
                    'location_label' => (string) ($location_labels[$location_id] ?? ($location_id !== '' ? $location_id : __('Unknown', 'vms-data-tools'))),
                    'closed_at_local' => vms_dt_reporting_local_datetime_from_utc($closed_at_utc),
                    'closed_at_utc' => $closed_at_utc,
                    'source_label' => (string) ($source['label'] ?? ''),
                    'channel_label' => $channel,
                    'line_name' => $line_name,
                    'variation_name' => $variation_name,
                    'catalog_object_id' => $sku,
                    'quantity' => $qty,
                    'gross_cents' => $gross,
                    'in_selected_location' => $in_selected_location ? 'yes' : 'no',
                    'in_selected_scope' => $in_selected_scope ? 'yes' : 'no',
                    'in_report_fetch' => $in_report_fetch ? 'yes' : 'no',
                    'notes' => implode(' ', $notes),
                );
            }

            if ($ticket_qty <= 0) {
                continue;
            }

            $summary['orders_count']++;
            $summary['ticket_qty'] += $ticket_qty;
            $summary['ticket_gross_cents'] += $ticket_gross;
            if ($in_report_fetch) {
                $summary['report_fetch_ticket_qty'] += $ticket_qty;
                $summary['report_fetch_ticket_gross_cents'] += $ticket_gross;
            } else {
                $summary['not_in_report_ticket_qty'] += $ticket_qty;
                $summary['not_in_report_ticket_gross_cents'] += $ticket_gross;
            }
            if (!$in_selected_location) {
                $summary['other_location_ticket_qty'] += $ticket_qty;
                $summary['other_location_ticket_gross_cents'] += $ticket_gross;
            }
            if (!$in_selected_scope) {
                $summary['outside_scope_ticket_qty'] += $ticket_qty;
                $summary['outside_scope_ticket_gross_cents'] += $ticket_gross;
            }

            $orders[] = array(
                'square_order_id' => $order_id,
                'location_id' => $location_id,
                'location_label' => (string) ($location_labels[$location_id] ?? ($location_id !== '' ? $location_id : __('Unknown', 'vms-data-tools'))),
                'closed_at_local' => vms_dt_reporting_local_datetime_from_utc($closed_at_utc),
                'closed_at_utc' => $closed_at_utc,
                'source_label' => (string) ($source['label'] ?? ''),
                'channel_label' => $channel,
                'quantity' => $ticket_qty,
                'gross_cents' => $ticket_gross,
                'line_summary' => implode(', ', array_unique($ticket_names)),
                'in_selected_location' => $in_selected_location ? 'yes' : 'no',
                'in_selected_scope' => $in_selected_scope ? 'yes' : 'no',
                'in_report_fetch' => $in_report_fetch ? 'yes' : 'no',
            );
        }

        usort($orders, static function (array $a, array $b): int {
            return strcmp((string) ($a['closed_at_utc'] ?? ''), (string) ($b['closed_at_utc'] ?? ''));
        });
        usort($ticket_rows, static function (array $a, array $b): int {
            return strcmp((string) ($a['closed_at_utc'] ?? ''), (string) ($b['closed_at_utc'] ?? ''));
        });

        return array(
            'orders' => $orders,
            'ticket_rows' => $ticket_rows,
            'summary' => $summary,
        );
    }
}

if (!function_exists('vms_dt_reporting_build_square_fetch_audit')) {
    function vms_dt_reporting_build_square_fetch_audit(int $event_plan_id, array $filters): array
    {
        $out = array(
            'errors' => array(),
            'warnings' => array(),
            'selected_scope' => array('orders' => array(), 'ticket_rows' => array(), 'summary' => array()),
            'all_locations_day' => array('orders' => array(), 'ticket_rows' => array(), 'summary' => array()),
            'missing_from_report_rows' => array(),
            'scope' => array(),
        );

        if ($event_plan_id <= 0) {
            return $out;
        }

        $scope = vms_dt_rr_resolve_square_location_scope($filters);
        $out['warnings'] = array_merge($out['warnings'], (array) ($scope['warnings'] ?? array()));
        $out['errors'] = array_merge($out['errors'], (array) ($scope['errors'] ?? array()));
        $selected_location_ids = array_values(array_filter(array_map('strval', (array) ($scope['location_ids'] ?? array()))));
        $location_labels = function_exists('vms_dt_rr_get_square_location_options') ? (array) vms_dt_rr_get_square_location_options() : array();
        $out['scope'] = array(
            'selected_location_ids' => $selected_location_ids,
            'selected_location_label' => (string) ($scope['selected_label'] ?? ''),
            'selected_scope_mode' => vms_dt_rr_normalize_square_scope_mode((string) ($filters['square_scope_mode'] ?? 'full_day')),
            'selected_scope_label' => vms_dt_rr_square_scope_label((string) ($filters['square_scope_mode'] ?? 'full_day')),
        );

        $selected_bounds = vms_dt_reporting_square_fetch_window_bounds($event_plan_id, $filters);
        $selected_window = (array) ($selected_bounds['window'] ?? array());
        $out['warnings'] = array_merge($out['warnings'], (array) ($selected_window['warnings'] ?? array()));
        $out['errors'] = array_merge($out['errors'], (array) ($selected_window['errors'] ?? array()));

        if (empty($selected_location_ids) || empty($selected_bounds['scan_start_utc']) || empty($selected_bounds['scan_end_utc'])) {
            $out['errors'] = array_values(array_unique(array_filter(array_map('strval', $out['errors']))));
            $out['warnings'] = array_values(array_unique(array_filter(array_map('strval', $out['warnings']))));
            return $out;
        }

        try {
            $selected_rows = vms_dt_rr_square_fetch_scope_orders($selected_location_ids, (string) $selected_bounds['scan_start_utc'], (string) $selected_bounds['scan_end_utc']);
        } catch (Throwable $e) {
            $out['errors'][] = 'Square selected-scope fetch failed: ' . $e->getMessage();
            $selected_rows = array();
        }
        $selected_order_ids = array();
        foreach ((array) $selected_rows as $row) {
            if (is_array($row) && !empty($row['square_order_id'])) {
                $selected_order_ids[(string) $row['square_order_id']] = true;
            }
        }
        $out['selected_scope'] = vms_dt_reporting_square_fetch_audit_analyze_rows((array) $selected_rows, $location_labels, $selected_location_ids, $selected_window, $selected_order_ids);

        $all_location_ids = array_keys($location_labels);
        foreach ($selected_location_ids as $location_id) {
            if ($location_id !== '' && !in_array($location_id, $all_location_ids, true)) {
                $all_location_ids[] = $location_id;
            }
        }
        $all_location_ids = array_values(array_unique(array_filter(array_map('strval', $all_location_ids))));
        $full_day_window = vms_dt_rr_build_event_window($event_plan_id, 'full_day');
        if (empty($full_day_window['window_start_utc']) || empty($full_day_window['window_end_utc'])) {
            $out['warnings'][] = 'Full event-day Square fetch could not be built for the current event.';
        } else {
            if ($all_location_ids === $selected_location_ids
                && (string) ($out['scope']['selected_scope_mode'] ?? '') === 'full_day'
                && empty($filters['sold_from'])
                && empty($filters['sold_to'])) {
                $all_rows = $selected_rows;
            } else {
                try {
                    $all_rows = vms_dt_rr_square_fetch_scope_orders($all_location_ids, (string) $full_day_window['window_start_utc'], (string) $full_day_window['window_end_utc']);
                } catch (Throwable $e) {
                    $out['errors'][] = 'Square full-day all-location fetch failed: ' . $e->getMessage();
                    $all_rows = array();
                }
            }
            $out['all_locations_day'] = vms_dt_reporting_square_fetch_audit_analyze_rows((array) $all_rows, $location_labels, $selected_location_ids, $selected_window, $selected_order_ids);
        }

        $missing_rows = array();
        foreach ((array) ($out['all_locations_day']['ticket_rows'] ?? array()) as $entry) {
            if (!is_array($entry) || (($entry['in_report_fetch'] ?? 'no') === 'yes')) {
                continue;
            }
            $missing_rows[] = $entry;
        }
        $out['missing_from_report_rows'] = $missing_rows;
        $out['errors'] = array_values(array_unique(array_filter(array_map('strval', $out['errors']))));
        $out['warnings'] = array_values(array_unique(array_filter(array_map('strval', $out['warnings']))));
        return $out;
    }
}

if (!function_exists('vms_dt_reporting_render_square_fetch_audit')) {
    function vms_dt_reporting_render_square_fetch_audit(array $model): void
    {
        $started_at = microtime(true);
        $row = (array) ($model['row'] ?? array());
        $filters = (array) ($model['filters'] ?? array());
        $event_plan_id = (int) ($row['event_plan_id'] ?? 0);
        $detail_enabled = vms_dt_reporting_is_detail_enabled('single_event_audit');
        if (!$detail_enabled) {
            echo '<div class="vms-dt-table-card vms-dt-section" id="vms-dt-detail-door-fetch-audit-deferred">';
            echo '<div class="vms-dt-card-head"><div><h3>' . esc_html__('Square fetch audit', 'vms-data-tools') . '</h3><p class="vms-dt-section-desc">' . esc_html__('This section can fetch and render a large event-day audit table, so row-level detail is deferred by default.', 'vms-data-tools') . '</p></div></div>';
            vms_dt_reporting_render_deferred_detail_notice('single_event_audit', __('Square fetch audit', 'vms-data-tools'));
            echo '</div>';
            vms_dt_reporting_trace('single_event_square_fetch_audit', 'skipped', array(
                'event_plan_id' => $event_plan_id,
                'reason' => 'detail_deferred',
            ), $started_at);
            return;
        }

        $audit = vms_dt_reporting_build_square_fetch_audit($event_plan_id, $filters);
        $selected = (array) ($audit['selected_scope'] ?? array());
        $all_day = (array) ($audit['all_locations_day'] ?? array());
        $selected_summary = (array) ($selected['summary'] ?? array());
        $all_day_summary = (array) ($all_day['summary'] ?? array());
        $missing_rows = isset($audit['missing_from_report_rows']) && is_array($audit['missing_from_report_rows']) ? $audit['missing_from_report_rows'] : array();
        $selected_rows = isset($selected['ticket_rows']) && is_array($selected['ticket_rows']) ? $selected['ticket_rows'] : array();
        $selected_pagination = vms_dt_reporting_paginate_rows('single_event_audit_selected', $selected_rows, 100);
        $missing_pagination = vms_dt_reporting_paginate_rows('single_event_audit_missing', $missing_rows, 100);

        echo '<div class="vms-dt-table-card vms-dt-section" id="vms-dt-detail-door-fetch-audit">';
        echo '<div class="vms-dt-card-head"><div><h3>' . esc_html__('Square fetch audit', 'vms-data-tools') . '</h3><p class="vms-dt-section-desc">' . esc_html__('This shows what Square orders were fetched before ticket classification so you can see where rows disappear: fetch, scope, location, or treatment.', 'vms-data-tools') . '</p></div></div>';
        echo '<p><a class="button" href="' . esc_url(vms_dt_reporting_section_toggle_url('single_event_audit', false)) . '">' . esc_html__('Hide row detail', 'vms-data-tools') . '</a></p>';

        foreach ((array) ($audit['errors'] ?? array()) as $message) {
            echo '<div class="notice notice-error inline"><p>' . esc_html((string) $message) . '</p></div>';
        }
        foreach ((array) ($audit['warnings'] ?? array()) as $message) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html((string) $message) . '</p></div>';
        }

        echo '<p class="description">' . esc_html(sprintf(__('Current report fetch: %1$s ticket-like orders • %2$s ticket qty • %3$s gross. Full event-day scan across all accessible Woo Square locations: %4$s ticket-like orders • %5$s ticket qty • %6$s gross. Missing from the current report fetch: %7$s qty • %8$s gross.', 'vms-data-tools'), number_format((int) ($selected_summary['orders_count'] ?? 0)), number_format((int) ($selected_summary['ticket_qty'] ?? 0)), vms_dt_rr_money((int) ($selected_summary['ticket_gross_cents'] ?? 0)), number_format((int) ($all_day_summary['orders_count'] ?? 0)), number_format((int) ($all_day_summary['ticket_qty'] ?? 0)), vms_dt_rr_money((int) ($all_day_summary['ticket_gross_cents'] ?? 0)), number_format((int) ($all_day_summary['not_in_report_ticket_qty'] ?? 0)), vms_dt_rr_money((int) ($all_day_summary['not_in_report_ticket_gross_cents'] ?? 0)))) . '</p>';
        if (vms_dt_reporting_memory_guard_should_skip('single_event_square_fetch_audit', array(
            'event_plan_id' => $event_plan_id,
            'selected_rows' => count($selected_rows),
            'missing_rows' => count($missing_rows),
        ))) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('Row-level Square audit rendering was skipped because admin memory usage is already high. Narrow the event scope or reload the section after clearing other heavy admin tabs.', 'vms-data-tools') . '</p></div>';
            echo '</div>';
            return;
        }

        echo '<h4>' . esc_html__('Ticket-like rows in the current report fetch', 'vms-data-tools') . '</h4>';
        vms_dt_reporting_render_pagination_controls($selected_pagination);
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Local close', 'vms-data-tools') . '</th><th>' . esc_html__('UTC close', 'vms-data-tools') . '</th><th>' . esc_html__('Location', 'vms-data-tools') . '</th><th>' . esc_html__('Source', 'vms-data-tools') . '</th><th>' . esc_html__('Channel', 'vms-data-tools') . '</th><th>' . esc_html__('Order', 'vms-data-tools') . '</th><th>' . esc_html__('Line item', 'vms-data-tools') . '</th><th>' . esc_html__('Qty', 'vms-data-tools') . '</th><th>' . esc_html__('Gross', 'vms-data-tools') . '</th><th>' . esc_html__('Seen by report', 'vms-data-tools') . '</th></tr></thead><tbody>';
        if (empty($selected_rows)) {
            echo '<tr><td colspan="10">' . esc_html__('No ticket-like Square rows were visible in the current report fetch.', 'vms-data-tools') . '</td></tr>';
        } else {
            foreach ((array) ($selected_pagination['rows'] ?? array()) as $entry) {
                echo '<tr>';
                echo '<td>' . esc_html((string) ($entry['closed_at_local'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($entry['closed_at_utc'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($entry['location_label'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($entry['source_label'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($entry['channel_label'] ?? '')) . '</td>';
                echo '<td><code>' . esc_html((string) ($entry['square_order_id'] ?? '')) . '</code></td>';
                echo '<td>' . esc_html((string) ($entry['line_name'] ?? '')) . '</td>';
                echo '<td>' . esc_html(number_format((int) ($entry['quantity'] ?? 0))) . '</td>';
                echo '<td>' . esc_html(vms_dt_rr_money((int) ($entry['gross_cents'] ?? 0))) . '</td>';
                echo '<td>' . esc_html((string) ($entry['in_report_fetch'] ?? '')) . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody><tfoot><tr><th colspan="7">' . esc_html__('Totals', 'vms-data-tools') . '</th><th>' . esc_html(number_format(vms_dt_reporting_sum_qty($selected_rows))) . '</th><th>' . esc_html(vms_dt_rr_money(vms_dt_reporting_sum_gross_cents($selected_rows))) . '</th><th>' . esc_html(number_format(vms_dt_reporting_unique_order_count($selected_rows))) . ' ' . esc_html__('orders', 'vms-data-tools') . '</th></tr></tfoot></table>';
        vms_dt_reporting_render_pagination_controls($selected_pagination);

        echo '<h4>' . esc_html__('Event-day ticket-like rows found outside the current report fetch', 'vms-data-tools') . '</h4>';
        vms_dt_reporting_render_pagination_controls($missing_pagination);
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Local close', 'vms-data-tools') . '</th><th>' . esc_html__('UTC close', 'vms-data-tools') . '</th><th>' . esc_html__('Location', 'vms-data-tools') . '</th><th>' . esc_html__('Source', 'vms-data-tools') . '</th><th>' . esc_html__('Channel', 'vms-data-tools') . '</th><th>' . esc_html__('Order', 'vms-data-tools') . '</th><th>' . esc_html__('Line item', 'vms-data-tools') . '</th><th>' . esc_html__('Qty', 'vms-data-tools') . '</th><th>' . esc_html__('Gross', 'vms-data-tools') . '</th><th>' . esc_html__('Why missing', 'vms-data-tools') . '</th></tr></thead><tbody>';
        if (empty($missing_rows)) {
            echo '<tr><td colspan="10">' . esc_html__('No extra ticket-like rows were found outside the current report fetch on the full event day scan.', 'vms-data-tools') . '</td></tr>';
        } else {
            foreach ((array) ($missing_pagination['rows'] ?? array()) as $entry) {
                echo '<tr>';
                echo '<td>' . esc_html((string) ($entry['closed_at_local'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($entry['closed_at_utc'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($entry['location_label'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($entry['source_label'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($entry['channel_label'] ?? '')) . '</td>';
                echo '<td><code>' . esc_html((string) ($entry['square_order_id'] ?? '')) . '</code></td>';
                echo '<td>' . esc_html((string) ($entry['line_name'] ?? '')) . '</td>';
                echo '<td>' . esc_html(number_format((int) ($entry['quantity'] ?? 0))) . '</td>';
                echo '<td>' . esc_html(vms_dt_rr_money((int) ($entry['gross_cents'] ?? 0))) . '</td>';
                echo '<td>' . esc_html((string) ($entry['notes'] ?? '')) . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody><tfoot><tr><th colspan="7">' . esc_html__('Totals', 'vms-data-tools') . '</th><th>' . esc_html(number_format(vms_dt_reporting_sum_qty($missing_rows))) . '</th><th>' . esc_html(vms_dt_rr_money(vms_dt_reporting_sum_gross_cents($missing_rows))) . '</th><th>' . esc_html(number_format(vms_dt_reporting_unique_order_count($missing_rows))) . ' ' . esc_html__('orders', 'vms-data-tools') . '</th></tr></tfoot></table>';
        vms_dt_reporting_render_pagination_controls($missing_pagination);
        echo '</div>';
        vms_dt_reporting_trace('single_event_square_fetch_audit', 'rendered', array(
            'event_plan_id' => $event_plan_id,
            'selected_rows' => count($selected_rows),
            'missing_rows' => count($missing_rows),
        ), $started_at);
    }
}

if (!function_exists('vms_dt_reporting_render_single_event_supporting_data')) {
    function vms_dt_reporting_render_single_event_supporting_data(array $model): void
    {
        $started_at = microtime(true);
        $row = (array) ($model['row'] ?? array());
        $costs = (array) ($model['costs'] ?? array());
        $summary = isset($model['summary']) && is_array($model['summary']) ? $model['summary'] : array();
        $evidence = isset($model['evidence']) && is_array($model['evidence']) ? $model['evidence'] : array();
        $website = isset($evidence['website']) && is_array($evidence['website']) ? $evidence['website'] : array();
        $square = isset($evidence['square']) && is_array($evidence['square']) ? $evidence['square'] : array();
        $ticket_rows = (array) ($website['ticket_rows'] ?? array());
        $addon_rows = (array) ($website['addon_rows'] ?? array());
        $square_ticket_rows = (array) ($square['ticket_rows'] ?? array());
        $square_onsite_rows = (array) ($square['onsite_rows'] ?? array());
        $square_held_rows = (array) ($square['held_rows'] ?? array());
        $square_ticket_summary = (array) ($square['summary'] ?? array());

        $ticket_row_count = count($ticket_rows);
        $ticket_order_count = vms_dt_reporting_unique_order_count($ticket_rows, 'order_id');
        $ticket_qty_total = vms_dt_reporting_sum_qty($ticket_rows);
        $ticket_net_total = 0; $ticket_tax_total = 0; $ticket_collected_total = 0;
        foreach ($ticket_rows as $entry) { if (is_array($entry)) { $ticket_net_total += (int) ($entry['net_subtotal_cents'] ?? 0); $ticket_tax_total += (int) ($entry['tax_cents'] ?? 0); $ticket_collected_total += (int) ($entry['cash_total_cents'] ?? 0); } }

        $addon_row_count = count($addon_rows);
        $addon_order_count = vms_dt_reporting_unique_order_count($addon_rows, 'order_id');
        $addon_qty_total = vms_dt_reporting_sum_qty($addon_rows);
        $addon_net_total = 0; $addon_tax_total = 0; $addon_collected_total = 0;
        foreach ($addon_rows as $entry) { if (is_array($entry)) { $addon_net_total += (int) ($entry['net_subtotal_cents'] ?? 0); $addon_tax_total += (int) ($entry['tax_cents'] ?? 0); $addon_collected_total += (int) ($entry['cash_total_cents'] ?? 0); } }

        $door_counted_rows = vms_dt_reporting_counted_door_ticket_rows($square_ticket_rows);
        $door_held_rows_only = array_values(array_filter($square_ticket_rows, static function ($entry) { return is_array($entry) && (($entry['treatment'] ?? '') !== 'counted'); }));
        $door_held_totals = vms_dt_reporting_row_financial_totals($door_held_rows_only);
        $door_found_qty = vms_dt_reporting_sum_qty($door_counted_rows);
        $door_found_gross = vms_dt_reporting_sum_gross_cents($door_counted_rows);
        $door_counted_qty = $door_found_qty;
        $door_counted_gross = $door_found_gross;
        $door_held_qty = (int) ($door_held_totals['qty'] ?? 0);
        $door_held_gross = (int) ($door_held_totals['gross_cents'] ?? 0);
        $door_held_tax = (int) ($door_held_totals['tax_cents'] ?? 0);
        $door_held_net = (int) ($door_held_totals['net_cents'] ?? 0);
        $door_order_count = vms_dt_reporting_unique_order_count($door_counted_rows);
        $door_counted_order_count = vms_dt_reporting_unique_order_count($door_counted_rows);
        $door_held_order_count = (int) ($door_held_totals['order_count'] ?? 0);

        $future_ticket_rows = vms_dt_reporting_future_event_ticket_rows($square_ticket_rows, (string) ($row['event_date'] ?? ''));
        $future_ticket_totals = vms_dt_reporting_row_financial_totals($future_ticket_rows);

        $onsite_order_count = vms_dt_reporting_unique_order_count($square_onsite_rows);
        $onsite_qty_total = vms_dt_reporting_sum_qty($square_onsite_rows);
        $onsite_gross_total = vms_dt_reporting_sum_gross_cents($square_onsite_rows);
        $onsite_net_total = vms_dt_reporting_sum_net_cents($square_onsite_rows);

        $held_order_count = vms_dt_reporting_unique_order_count($square_held_rows);
        $held_qty_total = vms_dt_reporting_sum_qty($square_held_rows);
        $held_gross_total = vms_dt_reporting_sum_gross_cents($square_held_rows);
        $event_plan_id = (int) ($row['event_plan_id'] ?? 0);
        $detail_enabled = vms_dt_reporting_is_detail_enabled('single_event_supporting');
        $total_row_volume = $ticket_row_count + $addon_row_count + count($door_counted_rows) + count($square_onsite_rows) + count($square_held_rows);
        $ticket_pagination = vms_dt_reporting_paginate_rows('single_event_ticket_rows', $ticket_rows, 100);
        $addon_pagination = vms_dt_reporting_paginate_rows('single_event_addon_rows', $addon_rows, 100);
        $door_pagination = vms_dt_reporting_paginate_rows('single_event_door_rows', $door_counted_rows, 100);
        $held_ticket_pagination = vms_dt_reporting_paginate_rows('single_event_held_ticket_rows', $door_held_rows_only, 100);
        $future_ticket_pagination = vms_dt_reporting_paginate_rows('single_event_future_ticket_rows', $future_ticket_rows, 100);
        $onsite_pagination = vms_dt_reporting_paginate_rows('single_event_onsite_rows', $square_onsite_rows, 100);
        $held_pagination = vms_dt_reporting_paginate_rows('single_event_held_rows', $square_held_rows, 100);
        $reconciliation = vms_dt_reporting_build_money_reconciliation($row, $summary, $costs);
        $vendor_payout_snapshot = vms_dt_reporting_get_vendor_payout_count_snapshot($event_plan_id);
        $ticket_story_summary = $summary;
        $ticket_story_summary['door_ticket_qty'] = $door_counted_qty;
        $ticket_story_summary['total_ticket_qty'] = max(0, (int) ($ticket_story_summary['online_ticket_qty'] ?? 0) + $door_counted_qty);
        $ticket_count_reconciliation = vms_dt_reporting_build_ticket_count_reconciliation($ticket_story_summary, $costs, $vendor_payout_snapshot);
        $profitability_basis_note = __('Current row value used for profitability after known costs.', 'vms-data-tools');
        $combined_counted_reference_cents = (int) ($reconciliation['combined_counted_reference_cents'] ?? 0);
        if ($combined_counted_reference_cents !== (int) ($reconciliation['profitability_basis_cents'] ?? 0)) {
            $profitability_basis_note .= ' ' . sprintf(__('Visible counted event revenue reference on this page: %s.', 'vms-data-tools'), vms_dt_rr_money($combined_counted_reference_cents));
        }

        echo '<div class="vms-dt-card vms-dt-section" id="vms-dt-supporting-data">';
        echo '<div class="vms-dt-card-head"><div><h2>' . esc_html__('Supporting data', 'vms-data-tools') . '</h2><p class="vms-dt-section-desc">' . esc_html__('Use these sections to see the exact rows behind the headline numbers. This is where the juice lives.', 'vms-data-tools') . '</p></div></div>';

        if (!empty($square['errors']) || !empty($square['warnings']) || !empty($website['warnings'])) {
            echo '<div class="vms-dt-toolbar">';
            foreach ((array) ($square['errors'] ?? array()) as $message) {
                echo '<span class="vms-dt-badge vms-dt-badge--danger">' . esc_html((string) $message) . '</span>';
            }
            foreach (array_merge((array) ($square['warnings'] ?? array()), (array) ($website['warnings'] ?? array())) as $message) {
                echo '<span class="vms-dt-badge vms-dt-badge--neutral">' . esc_html((string) $message) . '</span>';
            }
            echo '</div>';
        }

        echo '<div class="vms-dt-grid vms-dt-grid--cards">';
        echo '<div class="vms-dt-card" id="vms-dt-detail-ticket-rollup"><p class="vms-dt-kpi-label">' . esc_html__('Ticket rollup', 'vms-data-tools') . '</p><p class="vms-dt-kpi-value">' . esc_html(vms_dt_rr_money((int) ($summary['total_ticket_sales_cents'] ?? 0))) . '</p><p class="vms-dt-kpi-note">' . esc_html__('Online ticket revenue + counted door revenue = total ticket revenue story. Vendor payout bonus count is tracked separately.', 'vms-data-tools') . '</p></div>';
        echo '<div class="vms-dt-card" id="vms-dt-detail-bonus-count-basis"><p class="vms-dt-kpi-label">' . esc_html__('Vendor payout bonus basis', 'vms-data-tools') . '</p><p class="vms-dt-kpi-value">' . esc_html(number_format((int) ($ticket_count_reconciliation['bonus_basis_qty'] ?? 0))) . '</p><p class="vms-dt-kpi-note">' . esc_html((string) ($ticket_count_reconciliation['basis_note'] ?? '')) . '</p></div>';
        echo '<div class="vms-dt-card" id="vms-dt-detail-square-ticket-audit"><p class="vms-dt-kpi-label">' . esc_html__('Door ticket audit', 'vms-data-tools') . '</p><p class="vms-dt-kpi-value">' . esc_html(number_format((int) ($square_ticket_summary['ticket_like_counted_qty'] ?? 0))) . ' / ' . esc_html(number_format((int) ($square_ticket_summary['ticket_like_qty_total'] ?? 0))) . '</p><p class="vms-dt-kpi-note">' . esc_html__('counted qty / all ticket-like Square qty found in this scope', 'vms-data-tools') . '</p></div>';
        echo '<div class="vms-dt-card" id="vms-dt-summary-square-collected"><p class="vms-dt-kpi-label">' . esc_html__('Square processor cash breakdown', 'vms-data-tools') . '</p><p class="vms-dt-kpi-value">' . esc_html(vms_dt_rr_money((int) ($summary['square_collected_cents'] ?? 0))) . '</p><p class="vms-dt-kpi-note">' . esc_html__('see processor cash, tender mix, tax, and tips below', 'vms-data-tools') . '</p></div>';
        echo '</div>';

        if (!empty($ticket_count_reconciliation['diagnostics'])) {
            echo '<div class="vms-dt-callout vms-dt-callout--warn"><strong>' . esc_html__('Count guard', 'vms-data-tools') . '</strong><ul class="vms-dt-note-list">';
            foreach ((array) $ticket_count_reconciliation['diagnostics'] as $message) {
                echo '<li>' . esc_html((string) $message) . '</li>';
            }
            echo '</ul></div>';
        }

        if (!$detail_enabled) {
            vms_dt_reporting_render_deferred_detail_notice('single_event_supporting', __('supporting data', 'vms-data-tools'), $total_row_volume);
            echo '</div>';
            vms_dt_reporting_trace('single_event_supporting_data', 'skipped', array(
                'event_plan_id' => $event_plan_id,
                'reason' => 'detail_deferred',
                'row_volume' => $total_row_volume,
            ), $started_at);
            return;
        }

        echo '<p><a class="button" href="' . esc_url(vms_dt_reporting_section_toggle_url('single_event_supporting', false)) . '">' . esc_html__('Hide row detail', 'vms-data-tools') . '</a></p>';
        if (vms_dt_reporting_memory_guard_should_skip('single_event_supporting_data', array(
            'event_plan_id' => $event_plan_id,
            'row_volume' => $total_row_volume,
        ))) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('Row-level supporting tables were skipped because admin memory usage is already high. Narrow the event scope or reload the section after closing other heavy admin tabs.', 'vms-data-tools') . '</p></div>';
            echo '</div>';
            return;
        }

        echo '<div class="vms-dt-two-col vms-dt-section">';
        echo '<div class="vms-dt-table-card" id="vms-dt-detail-online-tickets"><div class="vms-dt-card-head"><div><h3>' . esc_html__('Online ticket sales rows', 'vms-data-tools') . '</h3><p class="vms-dt-section-desc">' . esc_html__('Full event lifecycle from website / TEC ticket truth.', 'vms-data-tools') . '</p></div></div>';
        echo '<p class="description">' . esc_html(sprintf(__('Summary: %1$s orders • %2$s ticket qty • %3$s net • %4$s tax • %5$s collected', 'vms-data-tools'), number_format($ticket_order_count), number_format($ticket_qty_total), vms_dt_rr_money($ticket_net_total), vms_dt_rr_money($ticket_tax_total), vms_dt_rr_money($ticket_collected_total))) . '</p>';
        vms_dt_reporting_render_pagination_controls($ticket_pagination);
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Order', 'vms-data-tools') . '</th><th>' . esc_html__('Sold', 'vms-data-tools') . '</th><th>' . esc_html__('Item', 'vms-data-tools') . '</th><th>' . esc_html__('Qty', 'vms-data-tools') . '</th><th>' . esc_html__('Net', 'vms-data-tools') . '</th><th>' . esc_html__('Tax', 'vms-data-tools') . '</th><th>' . esc_html__('Collected', 'vms-data-tools') . '</th></tr></thead><tbody>';
        if (empty($ticket_rows)) {
            echo '<tr><td colspan="7">' . esc_html__('No website ticket rows were available for this event.', 'vms-data-tools') . '</td></tr>';
        } else {
            foreach ((array) ($ticket_pagination['rows'] ?? array()) as $entry) {
                $order_label = '#' . (string) ($entry['order_number'] !== '' ? $entry['order_number'] : (string) ($entry['order_id'] ?? ''));
                $qty_label = (int) ($entry['quantity'] ?? 0);
                $refunded_qty = (int) ($entry['refunded_quantity'] ?? 0);
                if ($refunded_qty > 0) {
                    $qty_label .= ' (' . sprintf(__('refunded %d', 'vms-data-tools'), $refunded_qty) . ')';
                }
                echo '<tr><td>' . esc_html($order_label) . '</td><td>' . esc_html((string) ($entry['sold_datetime'] ?: $entry['sold_date'])) . '</td><td><strong>' . esc_html((string) ($entry['item_name'] ?? '')) . '</strong><br><span class="description">' . esc_html((string) ($entry['customer_name'] ?? '')) . '</span></td><td>' . esc_html((string) $qty_label) . '</td><td>' . esc_html(vms_dt_rr_money((int) ($entry['net_subtotal_cents'] ?? 0))) . '</td><td>' . esc_html(vms_dt_rr_money((int) ($entry['tax_cents'] ?? 0))) . '</td><td><strong>' . esc_html(vms_dt_rr_money((int) ($entry['cash_total_cents'] ?? 0))) . '</strong></td></tr>';
            }
        }
        echo '</tbody><tfoot><tr><th colspan="3">' . esc_html__('Totals', 'vms-data-tools') . '</th><th>' . esc_html(number_format($ticket_qty_total)) . '</th><th><strong>' . esc_html(vms_dt_rr_money($ticket_net_total)) . '</strong></th><th>' . esc_html(vms_dt_rr_money($ticket_tax_total)) . '</th><th>' . esc_html(vms_dt_rr_money($ticket_collected_total)) . '</th></tr></tfoot></table></div>';
        vms_dt_reporting_render_pagination_controls($ticket_pagination);

        echo '<div class="vms-dt-table-card" id="vms-dt-detail-website-addons"><div class="vms-dt-card-head"><div><h3>' . esc_html__('Website add-on rows', 'vms-data-tools') . '</h3><p class="vms-dt-section-desc">' . esc_html__('Entitlements / add-ons tied to this event across the full ticket lifecycle.', 'vms-data-tools') . '</p></div></div>';
        echo '<p class="description">' . esc_html(sprintf(__('Summary: %1$s orders • %2$s add-on qty • %3$s net • %4$s tax • %5$s collected', 'vms-data-tools'), number_format($addon_order_count), number_format($addon_qty_total), vms_dt_rr_money($addon_net_total), vms_dt_rr_money($addon_tax_total), vms_dt_rr_money($addon_collected_total))) . '</p>';
        vms_dt_reporting_render_pagination_controls($addon_pagination);
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Order', 'vms-data-tools') . '</th><th>' . esc_html__('Sold', 'vms-data-tools') . '</th><th>' . esc_html__('Item', 'vms-data-tools') . '</th><th>' . esc_html__('Qty', 'vms-data-tools') . '</th><th>' . esc_html__('Net', 'vms-data-tools') . '</th><th>' . esc_html__('Tax', 'vms-data-tools') . '</th><th>' . esc_html__('Collected', 'vms-data-tools') . '</th></tr></thead><tbody>';
        if (empty($addon_rows)) {
            echo '<tr><td colspan="7">' . esc_html__('No website add-on rows were available for this event.', 'vms-data-tools') . '</td></tr>';
        } else {
            foreach ((array) ($addon_pagination['rows'] ?? array()) as $entry) {
                $order_label = '#' . (string) ($entry['order_number'] !== '' ? $entry['order_number'] : (string) ($entry['order_id'] ?? ''));
                echo '<tr><td>' . esc_html($order_label) . '</td><td>' . esc_html((string) ($entry['sold_datetime'] ?: $entry['sold_date'])) . '</td><td><strong>' . esc_html((string) ($entry['item_name'] ?? '')) . '</strong></td><td>' . esc_html((string) ((int) ($entry['quantity'] ?? 0))) . '</td><td>' . esc_html(vms_dt_rr_money((int) ($entry['net_subtotal_cents'] ?? 0))) . '</td><td>' . esc_html(vms_dt_rr_money((int) ($entry['tax_cents'] ?? 0))) . '</td><td><strong>' . esc_html(vms_dt_rr_money((int) ($entry['cash_total_cents'] ?? 0))) . '</strong></td></tr>';
            }
        }
        echo '</tbody><tfoot><tr><th colspan="3">' . esc_html__('Totals', 'vms-data-tools') . '</th><th>' . esc_html(number_format($addon_qty_total)) . '</th><th><strong>' . esc_html(vms_dt_rr_money($addon_net_total)) . '</strong></th><th>' . esc_html(vms_dt_rr_money($addon_tax_total)) . '</th><th>' . esc_html(vms_dt_rr_money($addon_collected_total)) . '</th></tr></tfoot></table></div>';
        vms_dt_reporting_render_pagination_controls($addon_pagination);
        echo '</div>';

        echo '<div class="vms-dt-table-card vms-dt-section" id="vms-dt-detail-door-tickets"><div class="vms-dt-card-head"><div><h3>' . esc_html__('Door ticket supporting rows', 'vms-data-tools') . '</h3><p class="vms-dt-section-desc">' . esc_html__('Only counted door-channel Square ticket rows are shown here. Woo/overlap and other held rows stay in Audit.', 'vms-data-tools') . '</p></div></div>';
        $door_channels = vms_dt_reporting_square_ticket_channel_summary($door_counted_rows);
        echo '<p class="description">' . esc_html(sprintf(__('Summary: %1$s orders • %2$s counted qty / %3$s counted gross revenue • counted split: %4$s register, %5$s QR/payment link • %6$s held qty / %7$s held gross moved to Audit', 'vms-data-tools'), number_format($door_order_count), number_format($door_counted_qty), vms_dt_rr_money($door_counted_gross), number_format((int) ($door_channels['door_register_qty'] ?? 0)), number_format((int) ($door_channels['door_qr_qty'] ?? 0)), number_format($door_held_qty), vms_dt_rr_money($door_held_gross))) . '</p>';
        vms_dt_reporting_render_pagination_controls($door_pagination);
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Closed', 'vms-data-tools') . '</th><th>' . esc_html__('Order', 'vms-data-tools') . '</th><th>' . esc_html__('Item', 'vms-data-tools') . '</th><th>' . esc_html__('Qty', 'vms-data-tools') . '</th><th>' . esc_html__('Gross', 'vms-data-tools') . '</th><th>' . esc_html__('Source', 'vms-data-tools') . '</th><th>' . esc_html__('Channel', 'vms-data-tools') . '</th><th>' . esc_html__('Treatment', 'vms-data-tools') . '</th><th>' . esc_html__('Why', 'vms-data-tools') . '</th></tr></thead><tbody>';
        if (empty($door_counted_rows)) {
            echo '<tr><td colspan="9">' . esc_html__('No counted door-ticket Square lines were found for this event/scope.', 'vms-data-tools') . '</td></tr>';
        } else {
            foreach ((array) ($door_pagination['rows'] ?? array()) as $entry) {
                $item_label = (string) ($entry['line_name'] ?? '');
                if ((string) ($entry['variation_name'] ?? '') !== '') {
                    $item_label .= ' — ' . (string) $entry['variation_name'];
                }
                $treatment = ((string) ($entry['treatment'] ?? '') === 'counted') ? __('Counted', 'vms-data-tools') : __('Held out', 'vms-data-tools');
                $meta_bits = array_filter(array(
                    (string) ($entry['bucket_label'] ?? ''),
                    (string) ($entry['category_name'] ?? ''),
                    (string) ($entry['catalog_object_id'] ?? ''),
                ));
                $channel_label = __('Register / POS', 'vms-data-tools');
                $source_blob = strtolower((string) ($entry['source_label'] ?? ''));
                if (strpos($source_blob, 'door qr') !== false || strpos($source_blob, 'payment link') !== false || strpos($source_blob, 'qr') !== false) {
                    $channel_label = __('QR / Payment Link', 'vms-data-tools');
                }
                echo '<tr><td><strong>' . esc_html((string) ($entry['closed_at_local'] ?? '')) . '</strong>' . (((string) ($entry['closed_at_utc'] ?? '')) !== '' ? '<br><span class="description">UTC ' . esc_html((string) ($entry['closed_at_utc'] ?? '')) . '</span>' : '') . '</td><td><code>' . esc_html((string) ($entry['square_order_id'] ?? '')) . '</code></td><td><strong>' . esc_html($item_label) . '</strong>' . (!empty($meta_bits) ? '<br><span class="description">' . esc_html(implode(' · ', $meta_bits)) . '</span>' : '') . '</td><td>' . esc_html((string) ((int) ($entry['quantity'] ?? 0))) . '</td><td><strong>' . esc_html(vms_dt_rr_money((int) ($entry['gross_cents'] ?? 0))) . '</strong></td><td>' . esc_html((string) ($entry['source_label'] ?? '')) . '</td><td>' . esc_html($channel_label) . '</td><td>' . esc_html($treatment) . '</td><td>' . esc_html((string) ($entry['reason'] ?? '')) . '</td></tr>';
            }
        }
        echo '</tbody><tfoot><tr><th colspan="3">' . esc_html__('Totals', 'vms-data-tools') . '</th><th>' . esc_html(number_format($door_counted_qty)) . '</th><th><strong>' . esc_html(vms_dt_rr_money($door_counted_gross)) . '</strong></th><th colspan="4">' . esc_html(sprintf(__('Register: %1$s qty / %2$s • QR/Payment Link: %3$s qty / %4$s • Audit-held: %5$s qty / %6$s', 'vms-data-tools'), number_format((int) ($door_channels['door_register_qty'] ?? 0)), vms_dt_rr_money((int) ($door_channels['door_register_cents'] ?? 0)), number_format((int) ($door_channels['door_qr_qty'] ?? 0)), vms_dt_rr_money((int) ($door_channels['door_qr_cents'] ?? 0)), number_format($door_held_qty), vms_dt_rr_money($door_held_gross))) . '</th></tr></tfoot></table>';
        vms_dt_reporting_render_pagination_controls($door_pagination);
        echo '<p class="description">' . esc_html(sprintf(__('Door-ticket summary: %1$s counted qty across %2$s orders. %3$s held qty across %4$s orders were moved to Audit.', 'vms-data-tools'), number_format($door_counted_qty), number_format($door_counted_order_count), number_format($door_held_qty), number_format($door_held_order_count))) . '</p>';
        echo '</div>';

        echo '<div class="vms-dt-table-card vms-dt-section" id="vms-dt-detail-held-ticket-rows"><div class="vms-dt-card-head"><div><h3>' . esc_html__('Held ticket-like Square rows', 'vms-data-tools') . '</h3><p class="vms-dt-section-desc">' . esc_html__('These are ticket-like Square rows captured by the event time window but intentionally not counted in the current revenue basis.', 'vms-data-tools') . '</p></div></div>';
        echo '<p class="description">' . esc_html(sprintf(__('Summary: %1$s orders • %2$s held ticket-like qty • %3$s gross • %4$s tax • %5$s net', 'vms-data-tools'), number_format($door_held_order_count), number_format($door_held_qty), vms_dt_rr_money($door_held_gross), vms_dt_rr_money($door_held_tax), vms_dt_rr_money($door_held_net))) . '</p>';
        if (!empty($future_ticket_rows)) {
            echo '<p class="description">' . esc_html(sprintf(__('Of those ticket-like rows, %1$s rows point to a later event date in the line item label and are broken out again below.', 'vms-data-tools'), number_format(count($future_ticket_rows)))) . '</p>';
        }
        vms_dt_reporting_render_pagination_controls($held_ticket_pagination);
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Closed', 'vms-data-tools') . '</th><th>' . esc_html__('Embedded event date', 'vms-data-tools') . '</th><th>' . esc_html__('Order', 'vms-data-tools') . '</th><th>' . esc_html__('Item', 'vms-data-tools') . '</th><th>' . esc_html__('Qty', 'vms-data-tools') . '</th><th>' . esc_html__('Gross', 'vms-data-tools') . '</th><th>' . esc_html__('Tax', 'vms-data-tools') . '</th><th>' . esc_html__('Net', 'vms-data-tools') . '</th><th>' . esc_html__('Source', 'vms-data-tools') . '</th><th>' . esc_html__('Why held', 'vms-data-tools') . '</th></tr></thead><tbody>';
        if (empty($door_held_rows_only)) {
            echo '<tr><td colspan="10">' . esc_html__('No held ticket-like Square rows were found for this event/scope.', 'vms-data-tools') . '</td></tr>';
        } else {
            foreach ((array) ($held_ticket_pagination['rows'] ?? array()) as $entry) {
                $item_label = (string) ($entry['line_name'] ?? '');
                if ((string) ($entry['variation_name'] ?? '') !== '') {
                    $item_label .= ' — ' . (string) $entry['variation_name'];
                }
                $embedded_event_date = vms_dt_reporting_extract_embedded_event_date((string) ($entry['line_name'] ?? ''));
                echo '<tr><td><strong>' . esc_html((string) ($entry['closed_at_local'] ?? '')) . '</strong>' . (((string) ($entry['closed_at_utc'] ?? '')) !== '' ? '<br><span class="description">UTC ' . esc_html((string) ($entry['closed_at_utc'] ?? '')) . '</span>' : '') . '</td><td>' . esc_html($embedded_event_date !== '' ? $embedded_event_date : '—') . '</td><td><code>' . esc_html((string) ($entry['square_order_id'] ?? '')) . '</code></td><td><strong>' . esc_html($item_label) . '</strong></td><td>' . esc_html((string) ((int) ($entry['quantity'] ?? 0))) . '</td><td><strong>' . esc_html(vms_dt_rr_money((int) ($entry['gross_cents'] ?? 0))) . '</strong></td><td>' . esc_html(vms_dt_rr_money((int) ($entry['tax_cents'] ?? 0))) . '</td><td>' . esc_html(vms_dt_rr_money(vms_dt_reporting_sum_net_cents(array($entry)))) . '</td><td>' . esc_html((string) ($entry['source_label'] ?? '')) . '</td><td>' . esc_html((string) ($entry['reason'] ?? '')) . '</td></tr>';
            }
        }
        echo '</tbody><tfoot><tr><th colspan="4">' . esc_html__('Totals', 'vms-data-tools') . '</th><th>' . esc_html(number_format($door_held_qty)) . '</th><th><strong>' . esc_html(vms_dt_rr_money($door_held_gross)) . '</strong></th><th>' . esc_html(vms_dt_rr_money($door_held_tax)) . '</th><th>' . esc_html(vms_dt_rr_money($door_held_net)) . '</th><th colspan="2">' . esc_html(sprintf(__('%1$s orders held out of ticket-like Square rows', 'vms-data-tools'), number_format($door_held_order_count))) . '</th></tr></tfoot></table></div>';
        vms_dt_reporting_render_pagination_controls($held_ticket_pagination);

        if (!empty($future_ticket_rows)) {
            echo '<div class="vms-dt-table-card vms-dt-section" id="vms-dt-detail-future-ticket-rows"><div class="vms-dt-card-head"><div><h3>' . esc_html__('Future-event Square ticket rows in this time window', 'vms-data-tools') . '</h3><p class="vms-dt-section-desc">' . esc_html__('These ticket-like Square rows were captured by the selected event time window, but their line labels point to a later event date.', 'vms-data-tools') . '</p></div></div>';
            echo '<p class="description">' . esc_html(sprintf(__('Summary: %1$s orders • %2$s future-event qty • %3$s gross • %4$s tax • %5$s net', 'vms-data-tools'), number_format((int) ($future_ticket_totals['order_count'] ?? 0)), number_format((int) ($future_ticket_totals['qty'] ?? 0)), vms_dt_rr_money((int) ($future_ticket_totals['gross_cents'] ?? 0)), vms_dt_rr_money((int) ($future_ticket_totals['tax_cents'] ?? 0)), vms_dt_rr_money((int) ($future_ticket_totals['net_cents'] ?? 0)))) . '</p>';
            vms_dt_reporting_render_pagination_controls($future_ticket_pagination);
            echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Closed', 'vms-data-tools') . '</th><th>' . esc_html__('Embedded event date', 'vms-data-tools') . '</th><th>' . esc_html__('Order', 'vms-data-tools') . '</th><th>' . esc_html__('Item', 'vms-data-tools') . '</th><th>' . esc_html__('Qty', 'vms-data-tools') . '</th><th>' . esc_html__('Gross', 'vms-data-tools') . '</th><th>' . esc_html__('Tax', 'vms-data-tools') . '</th><th>' . esc_html__('Net', 'vms-data-tools') . '</th><th>' . esc_html__('Treatment', 'vms-data-tools') . '</th><th>' . esc_html__('Why', 'vms-data-tools') . '</th></tr></thead><tbody>';
            foreach ((array) ($future_ticket_pagination['rows'] ?? array()) as $entry) {
                $item_label = (string) ($entry['line_name'] ?? '');
                if ((string) ($entry['variation_name'] ?? '') !== '') {
                    $item_label .= ' — ' . (string) $entry['variation_name'];
                }
                $treatment = ((string) ($entry['treatment'] ?? '') === 'counted') ? __('Counted', 'vms-data-tools') : __('Held out', 'vms-data-tools');
                echo '<tr><td><strong>' . esc_html((string) ($entry['closed_at_local'] ?? '')) . '</strong>' . (((string) ($entry['closed_at_utc'] ?? '')) !== '' ? '<br><span class="description">UTC ' . esc_html((string) ($entry['closed_at_utc'] ?? '')) . '</span>' : '') . '</td><td>' . esc_html((string) ($entry['embedded_event_date'] ?? '')) . '</td><td><code>' . esc_html((string) ($entry['square_order_id'] ?? '')) . '</code></td><td><strong>' . esc_html($item_label) . '</strong></td><td>' . esc_html((string) ((int) ($entry['quantity'] ?? 0))) . '</td><td><strong>' . esc_html(vms_dt_rr_money((int) ($entry['gross_cents'] ?? 0))) . '</strong></td><td>' . esc_html(vms_dt_rr_money((int) ($entry['tax_cents'] ?? 0))) . '</td><td>' . esc_html(vms_dt_rr_money(vms_dt_reporting_sum_net_cents(array($entry)))) . '</td><td>' . esc_html($treatment) . '</td><td>' . esc_html((string) ($entry['reason'] ?? '')) . '</td></tr>';
            }
            echo '</tbody><tfoot><tr><th colspan="4">' . esc_html__('Totals', 'vms-data-tools') . '</th><th>' . esc_html(number_format((int) ($future_ticket_totals['qty'] ?? 0))) . '</th><th><strong>' . esc_html(vms_dt_rr_money((int) ($future_ticket_totals['gross_cents'] ?? 0))) . '</strong></th><th>' . esc_html(vms_dt_rr_money((int) ($future_ticket_totals['tax_cents'] ?? 0))) . '</th><th>' . esc_html(vms_dt_rr_money((int) ($future_ticket_totals['net_cents'] ?? 0))) . '</th><th colspan="2">' . esc_html(sprintf(__('%1$s orders matched a later embedded event date', 'vms-data-tools'), number_format((int) ($future_ticket_totals['order_count'] ?? 0)))) . '</th></tr></tfoot></table></div>';
            vms_dt_reporting_render_pagination_controls($future_ticket_pagination);
        }

        echo '<div class="vms-dt-two-col vms-dt-section">';
        echo '<div class="vms-dt-table-card" id="vms-dt-detail-onsite-sales"><div class="vms-dt-card-head"><div><h3>' . esc_html__('On-site non-ticket rows', 'vms-data-tools') . '</h3><p class="vms-dt-section-desc">' . esc_html__('Square lines currently counted as bar, concessions, merch, or other on-site sales.', 'vms-data-tools') . '</p></div></div>';
        echo '<p class="description">' . esc_html(sprintf(__('Summary: %1$s orders • %2$s counted line qty • %3$s counted pre-tax revenue', 'vms-data-tools'), number_format($onsite_order_count), number_format($onsite_qty_total), vms_dt_rr_money($onsite_net_total))) . '</p>';
        vms_dt_reporting_render_pagination_controls($onsite_pagination);
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Closed', 'vms-data-tools') . '</th><th>' . esc_html__('Item', 'vms-data-tools') . '</th><th>' . esc_html__('Qty', 'vms-data-tools') . '</th><th>' . esc_html__('Bucket', 'vms-data-tools') . '</th><th>' . esc_html__('Gross', 'vms-data-tools') . '</th></tr></thead><tbody>';
        if (empty($square_onsite_rows)) {
            echo '<tr><td colspan="5">' . esc_html__('No counted on-site non-ticket rows were found.', 'vms-data-tools') . '</td></tr>';
        } else {
            foreach ((array) ($onsite_pagination['rows'] ?? array()) as $entry) {
                $item_label = (string) ($entry['line_name'] ?? '');
                if ((string) ($entry['variation_name'] ?? '') !== '') {
                    $item_label .= ' — ' . (string) $entry['variation_name'];
                }
                echo '<tr><td>' . esc_html((string) ($entry['closed_at_local'] ?? '')) . '</td><td><strong>' . esc_html($item_label) . '</strong></td><td>' . esc_html((string) ((int) ($entry['quantity'] ?? 0))) . '</td><td>' . esc_html((string) ($entry['bucket_label'] ?? '')) . '</td><td><strong>' . esc_html(vms_dt_rr_money((int) ($entry['gross_cents'] ?? 0))) . '</strong></td></tr>';
            }
        }
        echo '</tbody><tfoot><tr><th colspan="3">' . esc_html__('Totals', 'vms-data-tools') . '</th><th>' . esc_html(number_format($onsite_qty_total)) . '</th><th><strong>' . esc_html(vms_dt_rr_money($onsite_net_total)) . '</strong></th></tr></tfoot></table></div>';
        vms_dt_reporting_render_pagination_controls($onsite_pagination);

        echo '<div class="vms-dt-table-card" id="vms-dt-detail-money-collected"><div class="vms-dt-card-head"><div><h3>' . esc_html__('Processor cash and attribution breakdown', 'vms-data-tools') . '</h3><p class="vms-dt-section-desc">' . esc_html__('Square is the processor cash truth for the selected scope. Website totals below show the website-originated portion of that processor flow.', 'vms-data-tools') . '</p></div></div>';
        echo '<table class="widefat striped"><tbody>';
        echo '<tr id="vms-dt-detail-website-collected"><th>' . esc_html__('Website-originated portion', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($summary['website_collected_cents'] ?? 0))) . '</td><td>' . esc_html__('Website / Woo attribution for tickets, add-ons, and website tax. When Square processes website orders, this is already inside Square total collected.', 'vms-data-tools') . '</td></tr>';
        echo '<tr id="vms-dt-detail-square-collected"><th>' . esc_html__('Square total collected', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($summary['square_collected_cents'] ?? 0))) . '</td><td>' . esc_html__('Square processor total collected in the selected scope, including tax, tips, and service charges.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Direct Square / POS portion', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($reconciliation['direct_square_pos_cents'] ?? 0))) . '</td><td>' . esc_html__('Diagnostic split: Square total collected less website-originated attribution.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Square item sales found in scope', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($row['square_scope_line_total_cents'] ?? 0))) . '</td><td>' . esc_html(sprintf(__('Square %s scope, before tips/service charges.', 'vms-data-tools'), (string) (($square['scope']['scope_label'] ?? '') !== '' ? $square['scope']['scope_label'] : __('selected', 'vms-data-tools')))) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Cash tenders in scope', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($row['square_scope_cash_cents'] ?? 0))) . '</td><td>' . esc_html__('Cash recorded by Square in the selected scope.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Card tenders in scope', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($row['square_scope_card_cents'] ?? 0))) . '</td><td>' . esc_html__('Card recorded by Square in the selected scope.', 'vms-data-tools') . '</td></tr>';
        echo '<tr id="vms-dt-detail-tax"><th>' . esc_html__('Tax collected', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($summary['tax_collected_cents'] ?? 0))) . '</td><td>' . esc_html__('Website tax plus Square line tax in scope.', 'vms-data-tools') . '</td></tr>';
        echo '<tr id="vms-dt-detail-tips"><th>' . esc_html__('Tips', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($summary['tips_cents'] ?? 0))) . '</td><td>' . esc_html__('Square tips tracked separately from the current profitability basis.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Service charges', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($row['square_scope_service_cents'] ?? 0))) . '</td><td>' . esc_html__('Square service charges recorded in the selected scope.', 'vms-data-tools') . '</td></tr>';
        echo '<tr id="vms-dt-detail-held"><th>' . esc_html__('Refunds / overlap held', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($summary['refunds_overlap_cents'] ?? 0))) . '</td><td>' . esc_html__('Website refund face values plus conservative Square holdouts intentionally excluded from the current profitability basis story.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Held ticket-like Square gross', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money($door_held_gross)) . '</td><td>' . esc_html__('Gross ticket-like Square dollars currently held out of the current profitability basis.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Held ticket-like Square tax', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money($door_held_tax)) . '</td><td>' . esc_html__('Tax attached to held ticket-like Square rows.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Held ticket-like Square net', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money($door_held_net)) . '</td><td>' . esc_html__('Pre-tax net for held ticket-like Square rows.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Website refund face value', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($reconciliation['website_refunds_face_cents'] ?? 0))) . '</td><td>' . esc_html__('Shown for visibility. These refunds are already reflected in Website Collected and are not subtracted again in the reconciliation below.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Square overlap held / excluded', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($reconciliation['square_overlap_excluded_cents'] ?? 0))) . '</td><td>' . esc_html__('Square rows held because they overlap online/website revenue or were conservatively excluded.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Square unclassified', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($reconciliation['square_unclassified_cents'] ?? 0))) . '</td><td>' . esc_html__('Square rows still uncategorized and therefore not counted.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Square ignored', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($reconciliation['square_ignored_cents'] ?? 0))) . '</td><td>' . esc_html__('Square rows explicitly mapped to ignore.', 'vms-data-tools') . '</td></tr>';
        echo '<tr id="vms-dt-detail-profitability-basis"><th>' . esc_html__('Current profitability basis', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($reconciliation['profitability_basis_cents'] ?? 0))) . '</td><td>' . esc_html__('Current stored basis used by Net after known costs. Phase 2 will clean up this deeper basis.', 'vms-data-tools') . '</td></tr>';
        echo '</tbody></table></div>';
        echo '</div>';

        echo '<div class="vms-dt-table-card vms-dt-section" id="vms-dt-detail-money-reconciliation"><div class="vms-dt-card-head"><div><h3>' . esc_html__('Processor cash and profitability snapshot', 'vms-data-tools') . '</h3><p class="vms-dt-section-desc">' . esc_html__('This separates Square processor cash truth, website-originated attribution, current direct Square/POS portion, and the stored profitability basis used by net after known costs.', 'vms-data-tools') . '</p></div></div>';
        echo '<table class="widefat striped"><tbody>';
        echo '<tr><th>' . esc_html__('Square total collected', 'vms-data-tools') . '</th><td><strong>' . esc_html(vms_dt_rr_money((int) ($reconciliation['square_total_collected_cents'] ?? 0))) . '</strong></td><td>' . esc_html__('Square processor cash truth for the selected scope.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Less website-originated portion', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($reconciliation['website_originated_cents'] ?? 0))) . '</td><td>' . esc_html__('Website / Woo attribution that is already included inside Square total collected when Square processes website orders.', 'vms-data-tools') . '</td></tr>';
        echo '</tbody><tfoot>';
        echo '<tr><th>' . esc_html__('Direct Square / POS portion', 'vms-data-tools') . '</th><td><strong>' . esc_html(vms_dt_rr_money((int) ($reconciliation['direct_square_pos_cents'] ?? 0))) . '</strong></td><td>' . esc_html__('Diagnostic split: Square total collected less website-originated attribution.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Tax collected', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($summary['tax_collected_cents'] ?? 0))) . '</td><td>' . esc_html__('Website tax plus Square line tax in scope.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Tips tracked separately', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($reconciliation['tips_cents'] ?? 0))) . '</td><td>' . esc_html__('Tips stay in processor cash but outside the counted profitability story.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Service charges tracked separately', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($reconciliation['service_charge_cents'] ?? 0))) . '</td><td>' . esc_html__('Service charges stay in processor cash but outside the counted profitability story.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Refunds / overlap held', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($summary['refunds_overlap_cents'] ?? 0))) . '</td><td>' . esc_html__('Website refund face values plus conservative Square holdouts intentionally excluded from the current profitability basis story.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Counted event revenue reference', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($reconciliation['combined_counted_reference_cents'] ?? 0))) . '</td><td>' . esc_html__('Visible pre-tax-style reference shown on this page. It is not yet the stored profitability basis.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Current profitability basis / counted_total_cents', 'vms-data-tools') . '</th><td><strong>' . esc_html(vms_dt_rr_money((int) ($reconciliation['profitability_basis_cents'] ?? 0))) . '</strong></td><td>' . esc_html($profitability_basis_note) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Known cost total', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($reconciliation['known_cost_total_cents'] ?? 0))) . '</td><td>' . esc_html__('Band payout estimate + budgeted labor + other direct costs.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Net after known costs', 'vms-data-tools') . '</th><td><strong>' . esc_html(vms_dt_rr_money((int) ($reconciliation['net_after_known_costs_cents'] ?? 0))) . '</strong></td><td>' . esc_html__('Current profitability basis minus known cost total.', 'vms-data-tools') . '</td></tr>';
        echo '</tfoot></table></div>';

        if (!empty($square_held_rows)) {
            echo '<div class="vms-dt-table-card vms-dt-section" id="vms-dt-detail-held-rows"><div class="vms-dt-card-head"><div><h3>' . esc_html__('Held-out non-ticket Square rows', 'vms-data-tools') . '</h3><p class="vms-dt-section-desc">' . esc_html__('These non-ticket Square rows are excluded from the current profitability basis because they look like overlap, remain uncategorized, or were explicitly ignored.', 'vms-data-tools') . '</p></div></div>';
            echo '<p class="description">' . esc_html(sprintf(__('Summary: %1$s orders • %2$s held-out qty • %3$s held-out gross', 'vms-data-tools'), number_format($held_order_count), number_format($held_qty_total), vms_dt_rr_money($held_gross_total))) . '</p>';
            vms_dt_reporting_render_pagination_controls($held_pagination);
            echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Closed', 'vms-data-tools') . '</th><th>' . esc_html__('Item', 'vms-data-tools') . '</th><th>' . esc_html__('Qty', 'vms-data-tools') . '</th><th>' . esc_html__('Gross', 'vms-data-tools') . '</th><th>' . esc_html__('Why held', 'vms-data-tools') . '</th></tr></thead><tbody>';
            foreach ((array) ($held_pagination['rows'] ?? array()) as $entry) {
                $item_label = (string) ($entry['line_name'] ?? '');
                if ((string) ($entry['variation_name'] ?? '') !== '') {
                    $item_label .= ' — ' . (string) $entry['variation_name'];
                }
                echo '<tr><td>' . esc_html((string) ($entry['closed_at_local'] ?? '')) . '</td><td><strong>' . esc_html($item_label) . '</strong></td><td>' . esc_html((string) ((int) ($entry['quantity'] ?? 0))) . '</td><td><strong>' . esc_html(vms_dt_rr_money((int) ($entry['gross_cents'] ?? 0))) . '</strong></td><td>' . esc_html((string) ($entry['reason'] ?? '')) . '</td></tr>';
            }
            echo '</tbody><tfoot><tr><th colspan="2">' . esc_html__('Totals', 'vms-data-tools') . '</th><th>' . esc_html(number_format($held_qty_total)) . '</th><th><strong>' . esc_html(vms_dt_rr_money($held_gross_total)) . '</strong></th><th></th></tr></tfoot></table></div>';
            vms_dt_reporting_render_pagination_controls($held_pagination);
        }

        if (function_exists('vms_dt_reporting_render_cost_supporting_data')) {
            vms_dt_reporting_render_cost_supporting_data($model);
        }

        echo '</div>';
        vms_dt_reporting_trace('single_event_supporting_data', 'rendered', array(
            'event_plan_id' => $event_plan_id,
            'row_volume' => $total_row_volume,
        ), $started_at);
    }
}


if (!function_exists('vms_dt_reporting_render_cost_supporting_data')) {
    function vms_dt_reporting_render_cost_supporting_data(array $model): void
    {
        $row = (array) ($model['row'] ?? array());
        $summary = isset($model['summary']) && is_array($model['summary']) ? (array) $model['summary'] : array();
        $evidence = isset($model['evidence']) && is_array($model['evidence']) ? (array) $model['evidence'] : array();
        $square_evidence = isset($evidence['square']) && is_array($evidence['square']) ? (array) $evidence['square'] : array();
        $square_ticket_rows = isset($square_evidence['ticket_rows']) && is_array($square_evidence['ticket_rows']) ? (array) $square_evidence['ticket_rows'] : array();
        $costs = (array) ($model['costs'] ?? array());
        $terms = isset($costs['terms']) && is_array($costs['terms']) ? (array) $costs['terms'] : array();
        $vendor_payout_snapshot = vms_dt_reporting_get_vendor_payout_count_snapshot((int) ($row['event_plan_id'] ?? 0));
        $ticket_story_summary = $summary;
        if (!empty($square_ticket_rows)) {
            $door_ticket_qty = vms_dt_reporting_sum_qty(vms_dt_reporting_counted_door_ticket_rows($square_ticket_rows));
            $ticket_story_summary['door_ticket_qty'] = $door_ticket_qty;
            $ticket_story_summary['total_ticket_qty'] = max(0, (int) ($ticket_story_summary['online_ticket_qty'] ?? 0) + $door_ticket_qty);
        }
        $ticket_count_reconciliation = vms_dt_reporting_build_ticket_count_reconciliation($ticket_story_summary, $costs, $vendor_payout_snapshot);
        $labor_rows = isset($costs['labor_detail_rows']) && is_array($costs['labor_detail_rows']) ? (array) $costs['labor_detail_rows'] : array();
        $labor_pagination = vms_dt_reporting_paginate_rows('single_event_labor_rows', $labor_rows, 75);
        $labor_total_cents = 0;
        foreach ($labor_rows as $labor_row) {
            if (is_array($labor_row) && !empty($labor_row['included'])) {
                $labor_total_cents += (int) ($labor_row['cost_cents'] ?? 0);
            }
        }

        echo '<div class="vms-dt-table-card vms-dt-section" id="vms-dt-detail-band-payout"><div class="vms-dt-card-head"><div><h3>' . esc_html__('Band payout supporting data', 'vms-data-tools') . '</h3><p class="vms-dt-section-desc">' . esc_html__('This shows the payout structure and the exact inputs used for the current band payout estimate.', 'vms-data-tools') . '</p></div></div>';
        echo '<table class="widefat striped"><tbody>';
        echo '<tr><th>' . esc_html__('Comp structure', 'vms-data-tools') . '</th><td>' . esc_html((string) ($costs['structure_label'] ?? '')) . '</td><td>' . esc_html__('Current event payout model saved on the Event Plan.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Bonus-eligible paid count', 'vms-data-tools') . '</th><td>' . esc_html(number_format((int) ($ticket_count_reconciliation['bonus_basis_qty'] ?? ($costs['paid_ticket_qty_total'] ?? ($costs['ticket_qty_total'] ?? 0))))) . '</td><td>' . esc_html((string) ($ticket_count_reconciliation['basis_note'] ?? __('Paid/eligible admissions currently used for attendance bonus logic.', 'vms-data-tools'))) . '</td></tr>';
        if ((int) ($ticket_count_reconciliation['excluded_qty'] ?? 0) > 0) {
            echo '<tr><th>' . esc_html__('Excluded free / qualified / comp / guest list', 'vms-data-tools') . '</th><td>' . esc_html(number_format((int) ($ticket_count_reconciliation['excluded_qty'] ?? 0))) . '</td><td>' . esc_html__('These records stay out of the vendor payout bonus basis.', 'vms-data-tools') . '</td></tr>';
        }
        echo '<tr><th>' . esc_html__('Base payout', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($costs['base_payout_cents'] ?? 0))) . '</td><td>' . esc_html__('Guaranteed/base portion before any attendance bonus.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Attendance / variable bonus', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($costs['bonus_cents'] ?? 0))) . '</td><td>' . esc_html((string) ($costs['bonus_note'] ?? __('No bonus note.', 'vms-data-tools'))) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Band payout estimate', 'vms-data-tools') . '</th><td><strong>' . esc_html(vms_dt_rr_money((int) ($costs['band_payout_cents'] ?? 0))) . '</strong></td><td>' . esc_html__('Base payout plus any attendance / split bonus computed above.', 'vms-data-tools') . '</td></tr>';
        echo '</tbody></table></div>';

        echo '<div class="vms-dt-table-card vms-dt-section" id="vms-dt-detail-labor-budgeted"><div class="vms-dt-card-head"><div><h3>' . esc_html__('Budgeted labor supporting data', 'vms-data-tools') . '</h3><p class="vms-dt-section-desc">' . esc_html__('This is the current staffing-based labor budget. It is not actual paid labor yet.', 'vms-data-tools') . '</p></div></div>';
        echo '<div class="vms-dt-callout vms-dt-callout--warn"><strong>' . esc_html__('Budgeted only', 'vms-data-tools') . '</strong>' . esc_html__('This section is built from Event Plan staffing assignments plus saved pay defaults/overrides. VMS does not yet have a finished actual-paid staff payroll source of truth on this screen.', 'vms-data-tools') . '</div>';
        if (!empty($costs['labor_warnings'])) {
            echo '<ul class="vms-dt-note-list">';
            foreach ((array) $costs['labor_warnings'] as $warning) {
                echo '<li>' . esc_html((string) $warning) . '</li>';
            }
            echo '</ul>';
        }
        vms_dt_reporting_render_pagination_controls($labor_pagination);
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Staff', 'vms-data-tools') . '</th><th>' . esc_html__('Role', 'vms-data-tools') . '</th><th>' . esc_html__('Pay basis', 'vms-data-tools') . '</th><th>' . esc_html__('Rate', 'vms-data-tools') . '</th><th>' . esc_html__('Shift', 'vms-data-tools') . '</th><th>' . esc_html__('Hours', 'vms-data-tools') . '</th><th>' . esc_html__('Budgeted cost', 'vms-data-tools') . '</th><th>' . esc_html__('Status', 'vms-data-tools') . '</th></tr></thead><tbody>';
        if (empty($labor_rows)) {
            echo '<tr><td colspan="8">' . esc_html__('No staffing assignments were available to build a labor budget for this event.', 'vms-data-tools') . '</td></tr>';
        } else {
            foreach ((array) ($labor_pagination['rows'] ?? array()) as $labor_row) {
                $shift_label = trim((string) ($labor_row['shift_start'] ?? ''));
                $shift_end = trim((string) ($labor_row['shift_end'] ?? ''));
                if ($shift_end !== '') {
                    $shift_label = ($shift_label !== '' ? $shift_label . ' → ' : '→ ') . $shift_end;
                }
                if ($shift_label === '') {
                    $shift_label = __('No shift window saved', 'vms-data-tools');
                }
                $status_bits = array();
                if (!empty($labor_row['included'])) {
                    $status_bits[] = __('Included', 'vms-data-tools');
                } else {
                    $status_bits[] = __('Excluded', 'vms-data-tools');
                }
                if (!empty($labor_row['overlap_flag'])) {
                    $status_bits[] = __('Overlaps same-staff role', 'vms-data-tools');
                }
                if (!empty($labor_row['reason'])) {
                    $status_bits[] = (string) $labor_row['reason'];
                }
                echo '<tr>';
                echo '<td><strong>' . esc_html((string) ($labor_row['staff_name'] ?? '')) . '</strong></td>';
                echo '<td>' . esc_html((string) ($labor_row['role_name'] ?? '')) . '</td>';
                echo '<td>' . esc_html(ucfirst(str_replace('_', ' ', (string) ($labor_row['pay_type'] ?? '')))) . '</td>';
                echo '<td>' . esc_html((string) ($labor_row['pay_rate_label'] ?? '')) . '</td>';
                echo '<td>' . esc_html($shift_label) . '</td>';
                echo '<td>' . esc_html(!empty($labor_row['hours']) ? rtrim(rtrim(number_format((float) ($labor_row['hours'] ?? 0), 2, '.', ''), '0'), '.') : '—') . '</td>';
                echo '<td><strong>' . esc_html(vms_dt_rr_money((int) ($labor_row['cost_cents'] ?? 0))) . '</strong></td>';
                echo '<td>' . esc_html(implode(' • ', $status_bits)) . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody><tfoot><tr><th colspan="6">' . esc_html__('Budgeted labor total', 'vms-data-tools') . '</th><th><strong>' . esc_html(vms_dt_rr_money($labor_total_cents)) . '</strong></th><th>' . esc_html(sprintf(__('%1$s assignments reviewed • %2$s included', 'vms-data-tools'), number_format((int) ($costs['assigned_staff_count'] ?? 0)), number_format((int) ($costs['included_assignment_count'] ?? 0)))) . '</th></tr></tfoot></table></div>';
        vms_dt_reporting_render_pagination_controls($labor_pagination);

        echo '<div class="vms-dt-table-card vms-dt-section" id="vms-dt-detail-other-direct-costs"><div class="vms-dt-card-head"><div><h3>' . esc_html__('Other direct costs supporting data', 'vms-data-tools') . '</h3><p class="vms-dt-section-desc">' . esc_html__('Manual direct costs currently saved on the Event Plan for this event.', 'vms-data-tools') . '</p></div></div>';
        echo '<table class="widefat striped"><tbody>';
        echo '<tr><th>' . esc_html__('Saved direct costs', 'vms-data-tools') . '</th><td><strong>' . esc_html(vms_dt_rr_money((int) ($costs['other_direct_costs_cents'] ?? 0))) . '</strong></td><td>' . esc_html__('This is the current Event Plan direct-cost field used in the known-cost calculation.', 'vms-data-tools') . '</td></tr>';
        echo '</tbody></table></div>';

        echo '<div class="vms-dt-table-card vms-dt-section" id="vms-dt-detail-known-costs"><div class="vms-dt-card-head"><div><h3>' . esc_html__('Known cost rollup', 'vms-data-tools') . '</h3><p class="vms-dt-section-desc">' . esc_html__('This is the arithmetic behind known cost total and net after known costs.', 'vms-data-tools') . '</p></div></div>';
        echo '<table class="widefat striped"><tbody>';
        echo '<tr><th>' . esc_html__('Band payout estimate', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($costs['band_payout_cents'] ?? 0))) . '</td><td>' . esc_html__('Payout estimate from the band comp structure.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Budgeted labor overhead', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($costs['labor_overhead_cents'] ?? 0))) . '</td><td>' . esc_html__('Staffing-based labor budget from the table above.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Other direct costs', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) ($costs['other_direct_costs_cents'] ?? 0))) . '</td><td>' . esc_html__('Manual direct costs entered on the Event Plan.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Known cost total', 'vms-data-tools') . '</th><td><strong>' . esc_html(vms_dt_rr_money((int) ($costs['known_cost_total_cents'] ?? 0))) . '</strong></td><td>' . esc_html__('Band payout + budgeted labor + other direct costs.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Profitability basis / counted_total_cents', 'vms-data-tools') . '</th><td>' . esc_html(vms_dt_rr_money((int) (($row['counted_total_cents'] ?? 0)))) . '</td><td>' . esc_html__('Current stored profitability basis used on this page for net after known costs.', 'vms-data-tools') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Net after known costs', 'vms-data-tools') . '</th><td><strong>' . esc_html(vms_dt_rr_money((int) ($costs['counted_net_after_known_costs_cents'] ?? 0))) . '</strong></td><td>' . esc_html__('Profitability basis minus known cost total.', 'vms-data-tools') . '</td></tr>';
        echo '</tbody></table></div>';
    }
}

if (!function_exists('vms_dt_reporting_render_single_event_summary')) {
    function vms_dt_reporting_render_single_event_summary(array $model): void
    {
        $row = $model['row'];
        $costs = $model['costs'];
        $overview = $model['overview'];
        $summary = isset($model['summary']) && is_array($model['summary']) ? $model['summary'] : vms_dt_reporting_build_single_event_summary($row);
        $evidence = isset($model['evidence']) && is_array($model['evidence']) ? (array) $model['evidence'] : array();
        $square_evidence = isset($evidence['square']) && is_array($evidence['square']) ? (array) $evidence['square'] : array();
        $square_ticket_rows = isset($square_evidence['ticket_rows']) && is_array($square_evidence['ticket_rows']) ? (array) $square_evidence['ticket_rows'] : array();
        $square_ticket_summary = isset($square_evidence['summary']) && is_array($square_evidence['summary']) ? (array) $square_evidence['summary'] : array();
        $square_onsite_rows = isset($square_evidence['onsite_rows']) && is_array($square_evidence['onsite_rows']) ? (array) $square_evidence['onsite_rows'] : array();

        $online_ticket_sales = (int) ($summary['online_ticket_sales_cents'] ?? 0);
        $online_ticket_qty = (int) ($summary['online_ticket_qty'] ?? 0);

        $door_ticket_counted_rows = vms_dt_reporting_counted_door_ticket_rows($square_ticket_rows);
        $door_ticket_found_qty = vms_dt_reporting_sum_qty($door_ticket_counted_rows);
        $door_ticket_found_cents = vms_dt_reporting_sum_gross_cents($door_ticket_counted_rows);
        $door_ticket_found_net_cents = vms_dt_reporting_sum_net_cents($door_ticket_counted_rows);
        $door_ticket_held_rows = array_values(array_filter($square_ticket_rows, static function ($entry) { return is_array($entry) && (($entry['treatment'] ?? '') !== 'counted'); }));
        $door_ticket_held_totals = vms_dt_reporting_row_financial_totals($door_ticket_held_rows);
        $door_ticket_qty = $door_ticket_found_qty;
        $door_ticket_sales = $door_ticket_found_net_cents;
        $door_ticket_held_qty = (int) ($door_ticket_held_totals['qty'] ?? 0);
        $door_ticket_held_gross = (int) ($door_ticket_held_totals['gross_cents'] ?? 0);
        $door_ticket_held_tax = (int) ($door_ticket_held_totals['tax_cents'] ?? 0);
        $door_ticket_held_net = (int) ($door_ticket_held_totals['net_cents'] ?? 0);

        $total_ticket_sales = $online_ticket_sales + $door_ticket_sales;
        $total_ticket_qty = $online_ticket_qty + $door_ticket_qty;

        $website_addons_net = (int) ($summary['website_addons_net_cents'] ?? 0);
        $onsite_non_ticket = vms_dt_reporting_sum_net_cents($square_onsite_rows);
        if ($onsite_non_ticket <= 0) {
            $onsite_non_ticket = max(0, (int) (($summary['onsite_non_ticket_cents'] ?? 0) - (($row['square_scope_line_tax_cents'] ?? 0) - ($door_ticket_found_cents - $door_ticket_found_net_cents))));
        }
        $onsite_non_ticket_qty = vms_dt_reporting_sum_qty($square_onsite_rows);
        $combined_counted = $total_ticket_sales + $website_addons_net + $onsite_non_ticket;

        $website_collected = (int) ($summary['website_collected_cents'] ?? 0);
        $square_collected = (int) ($summary['square_collected_cents'] ?? 0);
        $tax_collected = (int) ($summary['tax_collected_cents'] ?? 0);
        $tips = (int) ($summary['tips_cents'] ?? 0);
        $refunds_overlap = (int) ($summary['refunds_overlap_cents'] ?? 0);
        $vendor_payout_snapshot = vms_dt_reporting_get_vendor_payout_count_snapshot((int) ($row['event_plan_id'] ?? 0));
        $ticket_story_summary = $summary;
        $ticket_story_summary['door_ticket_qty'] = $door_ticket_qty;
        $ticket_story_summary['total_ticket_qty'] = $total_ticket_qty;
        $ticket_count_reconciliation = vms_dt_reporting_build_ticket_count_reconciliation($ticket_story_summary, $costs, $vendor_payout_snapshot);

        ?>
        <div class="vms-dt-card vms-dt-section">
            <div class="vms-dt-card-head">
                <div>
                    <h2><?php esc_html_e('Ticket performance', 'vms-data-tools'); ?></h2>
                    <p class="vms-dt-section-desc"><?php esc_html_e('This is the ticket story for the selected event using pre-tax revenue only: full online ticket lifecycle plus ticket-like Square rows found in the selected scope.', 'vms-data-tools'); ?></p>
                </div>
            </div>
            <div class="vms-dt-grid vms-dt-grid--cards">
                <div class="vms-dt-card"><p class="vms-dt-kpi-label"><?php esc_html_e('Online ticket sales', 'vms-data-tools'); ?></p><p class="vms-dt-kpi-value"><?php echo esc_html(vms_dt_rr_money($online_ticket_sales)); ?></p><p class="vms-dt-kpi-note"><?php echo esc_html(vms_dt_reporting_build_online_ticket_note($online_ticket_qty)); ?></p><?php echo vms_dt_reporting_support_link_html('vms-dt-detail-online-tickets'); ?></div>
                <div class="vms-dt-card"><p class="vms-dt-kpi-label"><?php esc_html_e('Door ticket sales counted', 'vms-data-tools'); ?></p><p class="vms-dt-kpi-value"><?php echo esc_html(vms_dt_rr_money($door_ticket_sales)); ?></p><p class="vms-dt-kpi-note"><?php $door_channels = vms_dt_reporting_square_ticket_channel_summary($square_ticket_rows); echo esc_html(sprintf(__('%1$s counted door records • %2$s register • %3$s QR/payment link • %4$s held', 'vms-data-tools'), number_format($door_ticket_qty), number_format((int) ($door_channels['door_register_qty'] ?? 0)), number_format((int) ($door_channels['door_qr_qty'] ?? 0)), number_format($door_ticket_held_qty))); ?></p><?php echo vms_dt_reporting_support_link_html('vms-dt-detail-door-tickets'); ?></div>
                <div class="vms-dt-card"><p class="vms-dt-kpi-label"><?php esc_html_e('Total ticket sales', 'vms-data-tools'); ?></p><p class="vms-dt-kpi-value"><?php echo esc_html(vms_dt_rr_money($total_ticket_sales)); ?></p><p class="vms-dt-kpi-note"><?php echo esc_html(vms_dt_reporting_build_total_ticket_note($online_ticket_qty, $door_ticket_qty, $total_ticket_qty, $ticket_count_reconciliation)); ?></p><?php echo vms_dt_reporting_support_link_html('vms-dt-detail-ticket-rollup'); ?></div>
                <div class="vms-dt-card"><p class="vms-dt-kpi-label"><?php esc_html_e('Website add-ons net', 'vms-data-tools'); ?></p><p class="vms-dt-kpi-value"><?php echo esc_html(vms_dt_rr_money($website_addons_net)); ?></p><p class="vms-dt-kpi-note"><?php esc_html_e('add-ons before tax', 'vms-data-tools'); ?></p><?php echo vms_dt_reporting_support_link_html('vms-dt-detail-website-addons'); ?></div>
                <div class="vms-dt-card"><p class="vms-dt-kpi-label"><?php esc_html_e('On-site non-ticket sales', 'vms-data-tools'); ?></p><p class="vms-dt-kpi-value"><?php echo esc_html(vms_dt_rr_money($onsite_non_ticket)); ?></p><p class="vms-dt-kpi-note"><?php echo esc_html(sprintf(__('%s counted non-ticket line qty in scope', 'vms-data-tools'), number_format($onsite_non_ticket_qty))); ?></p><?php echo vms_dt_reporting_support_link_html('vms-dt-detail-onsite-sales'); ?></div>
                <div class="vms-dt-card"><p class="vms-dt-kpi-label"><?php esc_html_e('Counted event revenue reference', 'vms-data-tools'); ?></p><p class="vms-dt-kpi-value"><?php echo esc_html(vms_dt_rr_money($combined_counted)); ?></p><p class="vms-dt-kpi-note"><?php esc_html_e('pre-tax ticket sales + pre-tax counted on-site revenue reference', 'vms-data-tools'); ?></p><?php echo vms_dt_reporting_support_link_html('vms-dt-detail-money-reconciliation'); ?></div>
            </div>
        </div>

        <div class="vms-dt-card vms-dt-section">
            <div class="vms-dt-card-head">
                <div>
                    <h2><?php esc_html_e('Processor cash and attribution', 'vms-data-tools'); ?></h2>
                    <p class="vms-dt-section-desc"><?php esc_html_e('Square is the processor cash truth for the selected scope. Website totals below show the website-originated portion of that same flow. Tax and tips stay out of the revenue cards above.', 'vms-data-tools'); ?></p>
                </div>
            </div>
            <div class="vms-dt-grid vms-dt-grid--cards">
                <div class="vms-dt-card"><p class="vms-dt-kpi-label"><?php esc_html_e('Website-originated portion', 'vms-data-tools'); ?></p><p class="vms-dt-kpi-value"><?php echo esc_html(vms_dt_rr_money($website_collected)); ?></p><p class="vms-dt-kpi-note"><?php esc_html_e('website / Woo attribution including website tax', 'vms-data-tools'); ?></p><?php echo vms_dt_reporting_support_link_html('vms-dt-detail-website-collected'); ?></div>
                <div class="vms-dt-card"><p class="vms-dt-kpi-label"><?php esc_html_e('Square total collected', 'vms-data-tools'); ?></p><p class="vms-dt-kpi-value"><?php echo esc_html(vms_dt_rr_money($square_collected)); ?></p><p class="vms-dt-kpi-note"><?php echo esc_html(sprintf(__('Square %s processor total collected', 'vms-data-tools'), (string) ($overview['square_scope_label'] ?? __('selected', 'vms-data-tools')))); ?></p><?php echo vms_dt_reporting_support_link_html('vms-dt-detail-square-collected'); ?></div>
                <div class="vms-dt-card"><p class="vms-dt-kpi-label"><?php esc_html_e('Direct Square / POS portion', 'vms-data-tools'); ?></p><p class="vms-dt-kpi-value"><?php echo esc_html(vms_dt_rr_money((int) ($summary['direct_square_pos_cents'] ?? 0))); ?></p><p class="vms-dt-kpi-note"><?php esc_html_e('Square total collected less website-originated attribution', 'vms-data-tools'); ?></p><?php echo vms_dt_reporting_support_link_html('vms-dt-detail-money-reconciliation'); ?></div>
                <div class="vms-dt-card"><p class="vms-dt-kpi-label"><?php esc_html_e('Current profitability basis', 'vms-data-tools'); ?></p><p class="vms-dt-kpi-value"><?php echo esc_html(vms_dt_rr_money((int) ($summary['profitability_basis_cents'] ?? 0))); ?></p><p class="vms-dt-kpi-note"><?php esc_html_e('current stored basis used by net after known costs', 'vms-data-tools'); ?></p><?php echo vms_dt_reporting_support_link_html('vms-dt-detail-profitability-basis'); ?></div>
                <div class="vms-dt-card"><p class="vms-dt-kpi-label"><?php esc_html_e('Tax collected', 'vms-data-tools'); ?></p><p class="vms-dt-kpi-value"><?php echo esc_html(vms_dt_rr_money($tax_collected)); ?></p><p class="vms-dt-kpi-note"><?php esc_html_e('website tax + Square tax (kept out of revenue above)', 'vms-data-tools'); ?></p><?php echo vms_dt_reporting_support_link_html('vms-dt-detail-tax'); ?></div>
                <div class="vms-dt-card"><p class="vms-dt-kpi-label"><?php esc_html_e('Tips', 'vms-data-tools'); ?></p><p class="vms-dt-kpi-value"><?php echo esc_html(vms_dt_rr_money($tips)); ?></p><p class="vms-dt-kpi-note"><?php esc_html_e('tracked separately, not added into the current profitability basis', 'vms-data-tools'); ?></p><?php echo vms_dt_reporting_support_link_html('vms-dt-detail-tips'); ?></div>
                <div class="vms-dt-card"><p class="vms-dt-kpi-label"><?php esc_html_e('Refunds / overlap held', 'vms-data-tools'); ?></p><p class="vms-dt-kpi-value"><?php echo esc_html(vms_dt_rr_money($refunds_overlap)); ?></p><p class="vms-dt-kpi-note"><?php echo esc_html(sprintf(__('held ticket-like Square: %1$s gross • %2$s tax • %3$s net', 'vms-data-tools'), vms_dt_rr_money($door_ticket_held_gross), vms_dt_rr_money($door_ticket_held_tax), vms_dt_rr_money($door_ticket_held_net))); ?></p><?php echo vms_dt_reporting_support_link_html('vms-dt-detail-held-ticket-rows'); ?></div>
            </div>
        </div>

        <div class="vms-dt-grid vms-dt-grid--cards vms-dt-section">
            <div class="vms-dt-card"><p class="vms-dt-kpi-label"><?php esc_html_e('Band payout estimate', 'vms-data-tools'); ?></p><p class="vms-dt-kpi-value"><?php echo esc_html(vms_dt_rr_money((int) ($costs['band_payout_cents'] ?? 0))); ?></p><p class="vms-dt-kpi-note"><?php echo esc_html((string) ($costs['structure_label'] ?? '')); ?></p><?php echo vms_dt_reporting_support_link_html('vms-dt-detail-band-payout'); ?></div>
            <div class="vms-dt-card"><p class="vms-dt-kpi-label"><?php esc_html_e('Labor overhead (budgeted)', 'vms-data-tools'); ?></p><p class="vms-dt-kpi-value"><?php echo esc_html(vms_dt_rr_money((int) ($costs['labor_overhead_cents'] ?? 0))); ?></p><p class="vms-dt-kpi-note"><?php echo esc_html(sprintf(__('%1$s staffing assignments reviewed • %2$s included in budget', 'vms-data-tools'), number_format((int) ($costs['assigned_staff_count'] ?? 0)), number_format((int) ($costs['included_assignment_count'] ?? 0)))); ?></p><?php echo vms_dt_reporting_support_link_html('vms-dt-detail-labor-budgeted'); ?></div>
            <div class="vms-dt-card"><p class="vms-dt-kpi-label"><?php esc_html_e('Other direct costs', 'vms-data-tools'); ?></p><p class="vms-dt-kpi-value"><?php echo esc_html(vms_dt_rr_money((int) ($costs['other_direct_costs_cents'] ?? 0))); ?></p><p class="vms-dt-kpi-note"><?php esc_html_e('manual sound, contractor, and other entered costs', 'vms-data-tools'); ?></p><?php echo vms_dt_reporting_support_link_html('vms-dt-detail-other-direct-costs'); ?></div>
            <div class="vms-dt-card"><p class="vms-dt-kpi-label"><?php esc_html_e('Known cost total', 'vms-data-tools'); ?></p><p class="vms-dt-kpi-value"><?php echo esc_html(vms_dt_rr_money((int) ($costs['known_cost_total_cents'] ?? 0))); ?></p><p class="vms-dt-kpi-note"><?php esc_html_e('band + budgeted labor + other direct costs', 'vms-data-tools'); ?></p><?php echo vms_dt_reporting_support_link_html('vms-dt-detail-known-costs'); ?></div>
            <div class="vms-dt-card"><p class="vms-dt-kpi-label"><?php esc_html_e('Net after known costs', 'vms-data-tools'); ?></p><p class="vms-dt-kpi-value"><?php echo esc_html(vms_dt_rr_money((int) ($costs['counted_net_after_known_costs_cents'] ?? 0))); ?></p><p class="vms-dt-kpi-note"><?php esc_html_e('profitability basis minus known costs', 'vms-data-tools'); ?></p><?php echo vms_dt_reporting_support_link_html('vms-dt-detail-money-reconciliation'); ?></div>
        </div>

        <div class="vms-dt-card vms-dt-section">
            <div class="vms-dt-card-head"><div><h2><?php esc_html_e('Quick read', 'vms-data-tools'); ?></h2><p class="vms-dt-section-desc"><?php esc_html_e('This is the fast answer section for the night.', 'vms-data-tools'); ?></p></div></div>
            <ul class="vms-dt-summary-list">
                <li><span class="vms-dt-summary-key"><?php esc_html_e('Did ticket sales cover the band?', 'vms-data-tools'); ?></span><span class="vms-dt-summary-value"><?php echo esc_html(!empty($costs['ticket_sales_cover_band']) ? __('Yes', 'vms-data-tools') : __('No', 'vms-data-tools')); ?></span></li>
                <li><span class="vms-dt-summary-key"><?php esc_html_e('Did ticket sales cover band + labor + direct costs?', 'vms-data-tools'); ?></span><span class="vms-dt-summary-value"><?php echo esc_html(!empty($costs['ticket_sales_cover_known']) ? __('Yes', 'vms-data-tools') : __('No', 'vms-data-tools')); ?></span></li>
                <li><span class="vms-dt-summary-key"><?php esc_html_e('Did the band hit the bonus?', 'vms-data-tools'); ?></span><span class="vms-dt-summary-value"><?php echo esc_html(!empty($costs['bonus_hit']) ? __('Yes', 'vms-data-tools') : __('No', 'vms-data-tools')); ?></span></li>
                <li><span class="vms-dt-summary-key"><?php esc_html_e('Bonus amount', 'vms-data-tools'); ?></span><span class="vms-dt-summary-value"><?php echo esc_html(vms_dt_rr_money((int) ($costs['bonus_cents'] ?? 0))); ?></span></li>
                <li><span class="vms-dt-summary-key"><?php esc_html_e('Confidence', 'vms-data-tools'); ?></span><span class="vms-dt-summary-value"><?php echo esc_html((string) ($overview['confidence_label'] ?? '')); ?></span></li>
            </ul>
            <?php if (!empty($costs['bonus_note'])) : ?>
                <p class="description"><?php echo esc_html((string) $costs['bonus_note']); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('vms_dt_reporting_render_match_check')) {
    function vms_dt_reporting_render_match_check(array $model): void
    {
        $row = $model['row'];
        $overview = $model['overview'];
        $audit_url = add_query_arg(array(
            'page' => vms_dt_get_menu_slug_revenue_intelligence(),
            'event_plan_id' => (int) ($row['event_plan_id'] ?? 0),
            'event_from' => (string) ($row['event_date'] ?? ''),
            'event_to' => (string) ($row['event_date'] ?? ''),
            'square_scope_mode' => (string) ($model['filters']['square_scope_mode'] ?? 'full_day'),
            'square_location_id' => (string) ($model['filters']['square_location_id'] ?? ''),
        ), admin_url('admin.php'));
        ?>
        <div class="vms-dt-card vms-dt-section">
            <div class="vms-dt-card-head">
                <div>
                    <h2><?php esc_html_e('Match check', 'vms-data-tools'); ?></h2>
                    <p class="vms-dt-section-desc"><?php esc_html_e('These are the numbers that should line up with your website / TEC report and your Square day report.', 'vms-data-tools'); ?></p>
                </div>
                <div><a class="button" href="<?php echo esc_url($audit_url); ?>"><?php esc_html_e('Open Audit Tools', 'vms-data-tools'); ?></a></div>
            </div>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('Question', 'vms-data-tools'); ?></th><th><?php esc_html_e('This report', 'vms-data-tools'); ?></th><th><?php esc_html_e('How to match it', 'vms-data-tools'); ?></th></tr></thead>
                <tbody>
                    <tr><td><?php esc_html_e('TEC completed ticket sales', 'vms-data-tools'); ?></td><td><strong><?php echo esc_html(vms_dt_rr_money((int) ($row['website_ticket_net_cents'] ?? 0))); ?></strong></td><td><?php esc_html_e('All website ticket sales for this event, net of refunds, before tax.', 'vms-data-tools'); ?></td></tr>
                    <tr><td><?php esc_html_e('Website-originated portion', 'vms-data-tools'); ?></td><td><strong><?php echo esc_html(vms_dt_rr_money((int) ($row['website_total_cents'] ?? 0))); ?></strong></td><td><?php esc_html_e('Website / Woo attribution for this event across the full ticket lifecycle. When Square processes website orders, this is already inside Square total collected.', 'vms-data-tools'); ?></td></tr>
                    <tr><td><?php esc_html_e('Square item sales found in scope', 'vms-data-tools'); ?></td><td><strong><?php echo esc_html(vms_dt_rr_money((int) ($row['square_scope_line_total_cents'] ?? 0))); ?></strong></td><td><?php echo esc_html(sprintf(__('Square %s scope, before tips/service charges.', 'vms-data-tools'), (string) ($overview['square_scope_label'] ?? __('selected', 'vms-data-tools')))); ?></td></tr>
                    <tr><td><?php esc_html_e('Square total collected', 'vms-data-tools'); ?></td><td><strong><?php echo esc_html(vms_dt_rr_money((int) ($row['square_scope_total_collected_cents'] ?? 0))); ?></strong></td><td><?php esc_html_e('Square processor total collected in the selected scope, including taxes, tips, and service charges.', 'vms-data-tools'); ?></td></tr>
                    <tr><td><?php esc_html_e('Direct Square / POS portion', 'vms-data-tools'); ?></td><td><strong><?php echo esc_html(vms_dt_rr_money(max(0, (int) ($row['square_scope_total_collected_cents'] ?? 0) - (int) ($row['website_total_cents'] ?? 0)))); ?></strong></td><td><?php esc_html_e('Diagnostic split: Square total collected less website-originated attribution.', 'vms-data-tools'); ?></td></tr>
                    <tr><td><?php esc_html_e('Total ticket sales', 'vms-data-tools'); ?></td><td><strong><?php echo esc_html(vms_dt_rr_money((int) ($model['costs']['ticket_sales_total_cents'] ?? 0))); ?></strong></td><td><?php esc_html_e('Website ticket net plus counted direct ticket sales from Square.', 'vms-data-tools'); ?></td></tr>
                </tbody>
            </table>
        </div>
        <?php
    }
}

if (!function_exists('vms_dt_reporting_render_single_event_page')) {
    function vms_dt_render_reporting_single_event_page(): void
    {
        if (!vms_dt_current_user_can_manage_tools()) {
            return;
        }
        $started_at = microtime(true);
        if (vms_dt_has_core_function('vms_resource_fingerprint_flag')) {
            vms_dt_call_core_function('vms_resource_fingerprint_flag', 'dt_report', array(
                'page' => 'single_event',
            ));
        }
        if (vms_dt_has_core_function('vms_resource_fingerprint_span_start')) {
            vms_dt_call_core_function('vms_resource_fingerprint_span_start', 'dt.single_event_page', array('page' => 'single_event'));
        }
        $row = array();

        try {
            $save_notice = vms_dt_reporting_maybe_save_eventbrite_settings();
            $filters = vms_dt_reporting_build_event_filters(vms_dt_reporting_effective_source(vms_dt_get_menu_slug_reporting_single_event()));
            $model = vms_dt_reporting_build_event_model($filters);
            $row = $model['row'];
            echo '<div class="wrap vms-dt-wrap">';
            if ($save_notice !== '') {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($save_notice) . '</p></div>';
            }
            echo '<h1>' . esc_html__('Single Event Report', 'vms-data-tools') . '</h1>';
            echo '<p class="vms-dt-lead">' . esc_html__('Open one event and immediately see ticket sales, on-site sales, Square processor cash, website attribution, known costs, and payout coverage.', 'vms-data-tools') . '</p>';
            vms_dt_reporting_nav(vms_dt_get_menu_slug_reporting_single_event());
            vms_dt_reporting_render_event_picker(vms_dt_get_menu_slug_reporting_single_event(), $filters, array(
                'title' => __('Event scope', 'vms-data-tools'),
                'desc' => __('Use this page when you want the plain-English answer for one night.', 'vms-data-tools'),
            ));
            vms_dt_reporting_render_eventbrite_settings_card();
            if (empty($row['event_plan_id'])) {
                echo '<div class="notice notice-warning"><p>' . esc_html__('No event plan matched the current filters.', 'vms-data-tools') . '</p></div></div>';
                return;
            }
            echo '<div class="vms-dt-callout"><strong>' . esc_html((string) ($row['event_title'] ?? '')) . '</strong><div>' . esc_html((string) ($row['event_date'] ?? '')) . ' · ' . esc_html((string) ($row['venue_name'] ?? '')) . ' · ' . esc_html((string) ($row['status'] ?? '')) . '</div></div>';
            vms_dt_reporting_render_single_event_summary($model);
            vms_dt_reporting_render_eventbrite_summary_section((array) ($model['eventbrite'] ?? array()));
            vms_dt_reporting_render_square_fetch_audit($model);
            vms_dt_reporting_render_single_event_supporting_data($model);
            vms_dt_reporting_render_match_check($model);
            echo '<details class="vms-dt-card vms-dt-section"><summary><strong>' . esc_html__('Show website / Square parity detail', 'vms-data-tools') . '</strong></summary>';
            vms_dt_rr_render_parity_panel((array) ($model['dataset']['event_rows'] ?? array()), (array) ($model['overview'] ?? array()));
            echo '</details>';
            echo '<details class="vms-dt-card vms-dt-section"><summary><strong>' . esc_html__('Show reconciliation snapshot', 'vms-data-tools') . '</strong></summary>';
            vms_dt_rr_render_reconciliation_panel((array) ($model['overview'] ?? array()));
            echo '</details>';
            echo '</div>';
        } finally {
            if (vms_dt_has_core_function('vms_resource_fingerprint_span_finish')) {
                vms_dt_call_core_function('vms_resource_fingerprint_span_finish', 'dt.single_event_page', array(
                    'page' => 'single_event',
                ));
            }
            vms_dt_reporting_trace('single_event_page', 'rendered', array(
                'event_plan_id' => (int) ($row['event_plan_id'] ?? 0),
            ), $started_at);
        }
    }
}

if (!function_exists('vms_dt_reporting_get_compare_filters')) {
    function vms_dt_reporting_get_compare_filters(?array $source = null): array
    {
        $source = is_array($source) ? $source : $_GET;
        $left = vms_dt_reporting_build_event_filters($source);
        $right = $left;
        $right['event_plan_id'] = isset($source['event_plan_b']) ? max(0, (int) wp_unslash($source['event_plan_b'])) : 0;
        if ($right['event_plan_id'] <= 0) {
            $events = vms_dt_rr_get_event_options(2);
            $right['event_plan_id'] = !empty($events[1]['id']) ? (int) $events[1]['id'] : (int) ($left['event_plan_id'] ?? 0);
        }
        if (!empty($right['event_plan_id'])) {
            $event_date = (string) get_post_meta((int) $right['event_plan_id'], '_vms_event_date', true);
            if ($event_date !== '') {
                $right['event_from'] = $event_date;
                $right['event_to'] = $event_date;
            }
        }
        return array($left, $right);
    }
}

if (!function_exists('vms_dt_render_reporting_compare_events_page')) {
    function vms_dt_render_reporting_compare_events_page(): void
    {
        if (!vms_dt_current_user_can_manage_tools()) {
            return;
        }
        [$left_filters, $right_filters] = vms_dt_reporting_get_compare_filters(vms_dt_reporting_effective_source(vms_dt_get_menu_slug_reporting_compare_events()));
        $left = vms_dt_reporting_build_event_model($left_filters);
        $right = vms_dt_reporting_build_event_model($right_filters);
        $events = vms_dt_rr_get_event_options();
        echo '<div class="wrap vms-dt-wrap">';
        echo '<h1>' . esc_html__('Compare Events', 'vms-data-tools') . '</h1>';
        echo '<p class="vms-dt-lead">' . esc_html__('Use this page when you want to see two nights side by side without audit noise.', 'vms-data-tools') . '</p>';
        vms_dt_reporting_nav(vms_dt_get_menu_slug_reporting_compare_events());
        ?>
        <form method="get" action="" class="vms-dt-card vms-dt-section">
            <input type="hidden" name="page" value="<?php echo esc_attr(vms_dt_get_menu_slug_reporting_compare_events()); ?>" />
            <div class="vms-dt-card-head"><div><h2><?php esc_html_e('Pick two events', 'vms-data-tools'); ?></h2><p class="vms-dt-section-desc"><?php esc_html_e('This is the quick side-by-side page for operators and owners.', 'vms-data-tools'); ?></p></div></div>
            <div class="vms-dt-filter-grid">
                <div class="vms-dt-field"><label><?php esc_html_e('Event A', 'vms-data-tools'); ?></label><select name="event_plan_id"><?php foreach ($events as $event) : ?><option value="<?php echo esc_attr((string) ($event['id'] ?? 0)); ?>" <?php selected((int) ($left_filters['event_plan_id'] ?? 0), (int) ($event['id'] ?? 0)); ?>><?php echo esc_html((string) ($event['label'] ?? '')); ?></option><?php endforeach; ?></select></div>
                <div class="vms-dt-field"><label><?php esc_html_e('Event B', 'vms-data-tools'); ?></label><select name="event_plan_b"><?php foreach ($events as $event) : ?><option value="<?php echo esc_attr((string) ($event['id'] ?? 0)); ?>" <?php selected((int) ($right_filters['event_plan_id'] ?? 0), (int) ($event['id'] ?? 0)); ?>><?php echo esc_html((string) ($event['label'] ?? '')); ?></option><?php endforeach; ?></select></div>
                <div class="vms-dt-field"><label><?php esc_html_e('Square scope', 'vms-data-tools'); ?></label><select name="square_scope_mode"><?php foreach (vms_dt_rr_square_scope_options() as $scope_key => $scope_label) : ?><option value="<?php echo esc_attr((string) $scope_key); ?>" <?php selected((string) ($left_filters['square_scope_mode'] ?? 'full_day'), (string) $scope_key); ?>><?php echo esc_html((string) $scope_label); ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="vms-dt-toolbar"><div class="vms-dt-toolbar-left"><button class="button button-primary" type="submit"><?php esc_html_e('Compare events', 'vms-data-tools'); ?></button></div></div>
        </form>
        <?php
        $left_row = $left['row'];
        $right_row = $right['row'];
        echo '<div class="vms-dt-card vms-dt-section"><div class="vms-dt-card-head"><div><h2>' . esc_html__('Side-by-side summary', 'vms-data-tools') . '</h2></div></div><table class="widefat striped"><thead><tr><th>' . esc_html__('Metric', 'vms-data-tools') . '</th><th>' . esc_html((string) ($left_row['event_title'] ?? 'Event A')) . '</th><th>' . esc_html((string) ($right_row['event_title'] ?? 'Event B')) . '</th></tr></thead><tbody>';
        $metrics = array(
            __('Total ticket sales', 'vms-data-tools') => array((int) ($left['costs']['ticket_sales_total_cents'] ?? 0), (int) ($right['costs']['ticket_sales_total_cents'] ?? 0), 'money'),
            __('Tickets counted', 'vms-data-tools') => array((int) ($left['costs']['ticket_qty_total'] ?? 0), (int) ($right['costs']['ticket_qty_total'] ?? 0), 'count'),
            __('Website add-ons net', 'vms-data-tools') => array((int) ($left_row['website_addon_net_cents'] ?? 0), (int) ($right_row['website_addon_net_cents'] ?? 0), 'money'),
            __('On-site sales', 'vms-data-tools') => array((int) (($left_row['square_counted_total_cents'] ?? 0) - ($left_row['square_direct_tickets_cents'] ?? 0)), (int) (($right_row['square_counted_total_cents'] ?? 0) - ($right_row['square_direct_tickets_cents'] ?? 0)), 'money'),
            __('Square total collected', 'vms-data-tools') => array((int) ($left_row['square_scope_total_collected_cents'] ?? 0), (int) ($right_row['square_scope_total_collected_cents'] ?? 0), 'money'),
            __('Website-originated portion', 'vms-data-tools') => array((int) ($left_row['website_total_cents'] ?? 0), (int) ($right_row['website_total_cents'] ?? 0), 'money'),
            __('Direct Square / POS portion', 'vms-data-tools') => array(max(0, (int) (($left_row['square_scope_total_collected_cents'] ?? 0) - ($left_row['website_total_cents'] ?? 0))), max(0, (int) (($right_row['square_scope_total_collected_cents'] ?? 0) - ($right_row['website_total_cents'] ?? 0))), 'money'),
            __('Band payout estimate', 'vms-data-tools') => array((int) ($left['costs']['band_payout_cents'] ?? 0), (int) ($right['costs']['band_payout_cents'] ?? 0), 'money'),
            __('Other direct costs', 'vms-data-tools') => array((int) ($left['costs']['other_direct_costs_cents'] ?? 0), (int) ($right['costs']['other_direct_costs_cents'] ?? 0), 'money'),
            __('Net after known costs', 'vms-data-tools') => array((int) ($left['costs']['counted_net_after_known_costs_cents'] ?? 0), (int) ($right['costs']['counted_net_after_known_costs_cents'] ?? 0), 'money'),
        );
        foreach ($metrics as $label => $values) {
            [$a, $b, $type] = $values;
            $fmt = static function ($value) use ($type): string {
                return $type === 'count' ? number_format((int) $value) : vms_dt_rr_money((int) $value);
            };
            echo '<tr><td>' . esc_html($label) . '</td><td><strong>' . esc_html($fmt($a)) . '</strong></td><td><strong>' . esc_html($fmt($b)) . '</strong></td></tr>';
        }
        echo '</tbody></table></div></div>';
    }
}


if (!function_exists('vms_dt_rr_event_row_is_cancelled')) {
    function vms_dt_rr_event_row_is_cancelled(array $row): bool
    {
        $status = sanitize_key((string) ($row['status'] ?? ''));
        return in_array($status, array('cancelled', 'canceled'), true);
    }
}

if (!function_exists('vms_dt_rr_build_event_series')) {
    function vms_dt_rr_build_event_series(array $event_rows, bool $include_cancelled = false): array
    {
        $series = array();
        foreach ($event_rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (!$include_cancelled && vms_dt_rr_event_row_is_cancelled($row)) {
                continue;
            }
            $date = (string) ($row['event_date'] ?? '');
            if ($date === '') {
                continue;
            }
            $series[] = array(
                'label' => $date,
                'counted_total_cents' => (int) ($row['counted_total_cents'] ?? 0),
                'events_count' => 1,
                'event_title' => (string) ($row['event_title'] ?? ''),
            );
        }

        usort($series, static function (array $a, array $b): int {
            return strcmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        return array_values($series);
    }
}

if (!function_exists('vms_dt_reporting_build_season_models')) {
    function vms_dt_reporting_build_season_models(array $filters): array
    {
        $filters['event_plan_id'] = 0;
        $filters['compare'] = 0;
        $dataset = vms_dt_rr_build_report_dataset($filters);
        $overview = vms_dt_rr_build_overview((array) ($dataset['event_rows'] ?? array()), (array) ($dataset['website'] ?? array()), (array) ($dataset['square_meta'] ?? array()));
        $rows = array();
        foreach ((array) ($dataset['event_rows'] ?? array()) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $event_plan_id = (int) ($row['event_plan_id'] ?? 0);
            if ($event_plan_id > 0) {
                $website_truth = vms_dt_reporting_build_event_lifetime_website_truth($event_plan_id);
                $row = vms_dt_reporting_apply_single_event_truth($row, $website_truth);
            }
            $event_filters = $filters;
            $event_filters['event_plan_id'] = $event_plan_id;
            $evidence = $event_plan_id > 0
                ? vms_dt_reporting_build_single_event_evidence(array('row' => $row, 'filters' => $event_filters))
                : array('website' => array(), 'square' => array());
            $rows[] = array(
                'row' => $row,
                'costs' => vms_dt_reporting_calculate_event_costs($row, (array) $evidence),
                'eventbrite' => vms_dt_reporting_calculate_eventbrite_savings($row, (array) $evidence),
            );
        }
        return array('filters' => $filters, 'dataset' => $dataset, 'overview' => $overview, 'rows' => $rows);
    }
}

if (!function_exists('vms_dt_render_reporting_season_year_page')) {
    function vms_dt_render_reporting_season_year_page(): void
    {
        if (!vms_dt_current_user_can_manage_tools()) {
            return;
        }
        $save_notice = vms_dt_reporting_maybe_save_eventbrite_settings();
        $filters = vms_dt_rr_get_filters(vms_dt_reporting_effective_source(vms_dt_get_menu_slug_reporting_season_year()));
        $filters['event_plan_id'] = 0;
        $filters['compare'] = 0;
        $model = vms_dt_reporting_build_season_models($filters);
        $show_cancelled_in_trend = !empty($_GET['show_cancelled_trend']);
        $trend_cancelled_hidden_count = 0;
        foreach ((array) ($model['dataset']['event_rows'] ?? array()) as $trend_row) {
            if (is_array($trend_row) && vms_dt_rr_event_row_is_cancelled($trend_row)) {
                $trend_cancelled_hidden_count++;
            }
        }
        $direct_costs_total = 0;
        $known_net_total = 0;
        $eventbrite_total_avoided = 0;
        $eventbrite_paid_sales_total = 0;
        $eventbrite_paid_qty_total = 0;
        $eventbrite_paid_orders_total = 0;
        foreach ($model['rows'] as $entry) {
            $direct_costs_total += (int) ($entry['costs']['known_cost_total_cents'] ?? 0);
            $known_net_total += (int) ($entry['costs']['counted_net_after_known_costs_cents'] ?? 0);
            $eventbrite_total_avoided += (int) ($entry['eventbrite']['total_avoided_fees_cents'] ?? 0);
            $eventbrite_paid_sales_total += (int) ($entry['eventbrite']['paid_ticket_sales_cents_total'] ?? 0);
            $eventbrite_paid_qty_total += (int) ($entry['eventbrite']['paid_ticket_qty_total'] ?? 0);
            $eventbrite_paid_orders_total += (int) ($entry['eventbrite']['paid_order_count_total'] ?? 0);
        }
        echo '<div class="wrap vms-dt-wrap">';
        if ($save_notice !== '') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($save_notice) . '</p></div>';
        }
        echo '<h1>' . esc_html__('Season / Year Report', 'vms-data-tools') . '</h1>';
        echo '<p class="vms-dt-lead">' . esc_html__('Use this page when you want the big-picture leaderboard and trend view for the selected date range.', 'vms-data-tools') . '</p>';
        vms_dt_reporting_nav(vms_dt_get_menu_slug_reporting_season_year());
        ?>
        <form method="get" action="" class="vms-dt-card vms-dt-section">
            <input type="hidden" name="page" value="<?php echo esc_attr(vms_dt_get_menu_slug_reporting_season_year()); ?>" />
            <div class="vms-dt-card-head"><div><h2><?php esc_html_e('Date range', 'vms-data-tools'); ?></h2><p class="vms-dt-section-desc"><?php esc_html_e('This page compares every event in the selected range.', 'vms-data-tools'); ?></p></div></div>
            <div class="vms-dt-filter-grid">
                <div class="vms-dt-field"><label><?php esc_html_e('Event date from', 'vms-data-tools'); ?></label><input type="date" name="event_from" value="<?php echo esc_attr((string) ($filters['event_from'] ?? '')); ?>" /></div>
                <div class="vms-dt-field"><label><?php esc_html_e('Event date to', 'vms-data-tools'); ?></label><input type="date" name="event_to" value="<?php echo esc_attr((string) ($filters['event_to'] ?? '')); ?>" /></div>
                <div class="vms-dt-field"><label><?php esc_html_e('Venue', 'vms-data-tools'); ?></label><select name="venue_id"><option value="0"><?php esc_html_e('All venues', 'vms-data-tools'); ?></option><?php foreach (vms_dt_rr_get_venue_options() as $venue) : ?><option value="<?php echo esc_attr((string) ($venue['id'] ?? 0)); ?>" <?php selected((int) ($filters['venue_id'] ?? 0), (int) ($venue['id'] ?? 0)); ?>><?php echo esc_html((string) ($venue['label'] ?? '')); ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="vms-dt-toolbar">
                <div class="vms-dt-toolbar-left"><button class="button button-primary" type="submit"><?php esc_html_e('Refresh range', 'vms-data-tools'); ?></button></div>
                <div class="vms-dt-toolbar-right">
                    <label class="vms-dt-inline-checkbox"><input type="checkbox" name="show_cancelled_trend" value="1" <?php checked($show_cancelled_in_trend); ?> /> <?php esc_html_e('Show cancelled events in trend', 'vms-data-tools'); ?></label>
                </div>
            </div>
        </form>
        <?php
        vms_dt_reporting_render_eventbrite_settings_card();
        echo '<div class="vms-dt-grid vms-dt-grid--cards vms-dt-section">';
        $cards = array(
            array(__('Events', 'vms-data-tools'), number_format((int) ($model['overview']['events_count'] ?? 0)), __('events in scope', 'vms-data-tools')),
            array(__('Total ticket sales', 'vms-data-tools'), vms_dt_rr_money((int) ($model['overview']['ticket_sales_total_cents'] ?? 0)), __('website tickets net + direct tickets', 'vms-data-tools')),
            array(__('Estimated Eventbrite fees avoided', 'vms-data-tools'), vms_dt_rr_money($eventbrite_total_avoided), sprintf(__('%1$s paid tickets across %2$s paid orders', 'vms-data-tools'), number_format($eventbrite_paid_qty_total), number_format($eventbrite_paid_orders_total))),
            array(__('Paid ticket sales basis', 'vms-data-tools'), vms_dt_rr_money($eventbrite_paid_sales_total), __('pre-tax paid ticket sales only', 'vms-data-tools')),
            array(__('On-site sales', 'vms-data-tools'), vms_dt_rr_money((int) ($model['overview']['onsite_total_cents'] ?? 0)), __('counted on-site revenue', 'vms-data-tools')),
            array(__('Known cost total', 'vms-data-tools'), vms_dt_rr_money($direct_costs_total), __('band payout + labor + other direct costs', 'vms-data-tools')),
            array(__('Net after known costs', 'vms-data-tools'), vms_dt_rr_money($known_net_total), __('current profitability basis minus known costs', 'vms-data-tools')),
        );
        foreach ($cards as $card) {
            echo '<div class="vms-dt-card"><p class="vms-dt-kpi-label">' . esc_html($card[0]) . '</p><p class="vms-dt-kpi-value">' . esc_html($card[1]) . '</p><p class="vms-dt-kpi-note">' . esc_html($card[2]) . '</p></div>';
        }
        echo '</div>';
        echo '<div class="vms-dt-card vms-dt-section" id="vms-dt-range-eventbrite-savings"><div class="vms-dt-card-head"><div><h2>' . esc_html__('Estimated Eventbrite cost savings', 'vms-data-tools') . '</h2><p class="vms-dt-section-desc">' . esc_html(vms_dt_reporting_eventbrite_benchmark_label()) . '</p></div></div>';
        echo '<ul class="vms-dt-summary-list">';
        echo '<li><span class="vms-dt-summary-key">' . esc_html__('Estimated fees avoided', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html(vms_dt_rr_money($eventbrite_total_avoided)) . '</span></li>';
        echo '<li><span class="vms-dt-summary-key">' . esc_html__('Paid ticket sales basis', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html(vms_dt_rr_money($eventbrite_paid_sales_total)) . '</span></li>';
        echo '<li><span class="vms-dt-summary-key">' . esc_html__('Paid ticket qty basis', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html(number_format($eventbrite_paid_qty_total)) . '</span></li>';
        echo '<li><span class="vms-dt-summary-key">' . esc_html__('Paid order basis', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html(number_format($eventbrite_paid_orders_total)) . '</span></li>';
        echo '</ul><p class="description">' . esc_html__('This estimate intentionally excludes free / comp / guest admissions so the season/year savings number stays tied to paid ticket activity.', 'vms-data-tools') . '</p></div>';
        $trend_desc = __('Plots each event in the selected range so short ranges do not collapse into a single monthly dot.', 'vms-data-tools');
        if (!$show_cancelled_in_trend && $trend_cancelled_hidden_count > 0) {
            $trend_desc .= ' ' . sprintf(_n('%d cancelled event is hidden from the line so the trend reflects actual operating nights.', '%d cancelled events are hidden from the line so the trend reflects actual operating nights.', $trend_cancelled_hidden_count, 'vms-data-tools'), $trend_cancelled_hidden_count);
        } elseif ($show_cancelled_in_trend && $trend_cancelled_hidden_count > 0) {
            $trend_desc .= ' ' . __('Cancelled events are currently included in the line.', 'vms-data-tools');
        }
        echo '<div class="vms-dt-card vms-dt-section"><div class="vms-dt-card-head"><div><h2>' . esc_html__('Trend', 'vms-data-tools') . '</h2><p class="vms-dt-section-desc">' . esc_html($trend_desc) . '</p></div></div>' . vms_dt_rr_render_trend_svg(vms_dt_rr_build_event_series((array) ($model['dataset']['event_rows'] ?? array()), $show_cancelled_in_trend)) . '</div>';
        echo '<div class="vms-dt-card vms-dt-section"><div class="vms-dt-card-head"><div><h2>' . esc_html__('Event leaderboard', 'vms-data-tools') . '</h2><p class="vms-dt-section-desc">' . esc_html__('This is the fast ranking table for the selected range.', 'vms-data-tools') . '</p></div></div>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Event', 'vms-data-tools') . '</th><th>' . esc_html__('Ticket sales', 'vms-data-tools') . '</th><th>' . esc_html__('Estimated Eventbrite fees avoided', 'vms-data-tools') . '</th><th>' . esc_html__('On-site', 'vms-data-tools') . '</th><th>' . esc_html__('Known costs', 'vms-data-tools') . '</th><th>' . esc_html__('Net after known costs', 'vms-data-tools') . '</th></tr></thead><tbody>';
        foreach ($model['rows'] as $entry) {
            $row = $entry['row'];
            $costs = $entry['costs'];
            echo '<tr><td><strong>' . esc_html((string) ($row['event_title'] ?? '')) . '</strong><br><span class="description">' . esc_html((string) ($row['event_date'] ?? '')) . '</span></td><td>' . esc_html(vms_dt_rr_money((int) ($costs['ticket_sales_total_cents'] ?? 0))) . '</td><td>' . esc_html(vms_dt_rr_money((int) ($entry['eventbrite']['total_avoided_fees_cents'] ?? 0))) . '</td><td>' . esc_html(vms_dt_rr_money((int) (($row['square_counted_total_cents'] ?? 0) - ($row['square_direct_tickets_cents'] ?? 0)))) . '</td><td>' . esc_html(vms_dt_rr_money((int) ($costs['known_cost_total_cents'] ?? 0))) . '</td><td><strong>' . esc_html(vms_dt_rr_money((int) ($costs['counted_net_after_known_costs_cents'] ?? 0))) . '</strong></td></tr>';
        }
        echo '</tbody></table></div></div>';
    }
}

if (!function_exists('vms_dt_reporting_ticket_pace_cache_key')) {
    function vms_dt_reporting_ticket_pace_cache_key(int $event_plan_id, array $filters = array()): string
    {
        $payload = array(
            'event_plan_id' => max(0, $event_plan_id),
            'square_location_id' => sanitize_text_field((string) ($filters['square_location_id'] ?? '')),
            'square_scope_mode' => sanitize_key((string) ($filters['square_scope_mode'] ?? 'full_day')),
            'sold_from' => sanitize_text_field((string) ($filters['sold_from'] ?? '')),
            'sold_to' => sanitize_text_field((string) ($filters['sold_to'] ?? '')),
        );

        return 'vms_dt_tp_' . md5((string) wp_json_encode($payload));
    }
}

if (!function_exists('vms_dt_reporting_ticket_pace_rows')) {
    function vms_dt_reporting_ticket_pace_rows(int $event_plan_id, array $args = array()): array
    {
        $started_at = microtime(true);
        $event_plan_id = max(0, $event_plan_id);
        $filters = isset($args['filters']) && is_array($args['filters'])
            ? $args['filters']
            : vms_dt_reporting_build_event_filters(vms_dt_reporting_effective_source(vms_dt_get_menu_slug_reporting_ticket_pace()));
        $filters['event_plan_id'] = $event_plan_id;
        $filters['square_scope_mode'] = 'full_day';
        $allow_build = !array_key_exists('allow_build', $args) || !empty($args['allow_build']);
        $use_transient_cache = !array_key_exists('use_transient_cache', $args) || !empty($args['use_transient_cache']);
        $cache_key = vms_dt_reporting_ticket_pace_cache_key($event_plan_id, $filters);
        static $memory_cache = array();
        if ($cache_key !== '' && isset($memory_cache[$cache_key]) && is_array($memory_cache[$cache_key])) {
            if (vms_dt_has_core_function('vms_resource_fingerprint_flag')) {
                vms_dt_call_core_function('vms_resource_fingerprint_flag', 'dt_ticket_pace_rows_cache', 'memory_hit');
            }
            return $memory_cache[$cache_key];
        }

        if ($use_transient_cache && $cache_key !== '') {
            $cached_rows = get_transient($cache_key);
            if (is_array($cached_rows)) {
                if (vms_dt_has_core_function('vms_resource_fingerprint_flag')) {
                    vms_dt_call_core_function('vms_resource_fingerprint_flag', 'dt_ticket_pace_rows_cache', 'transient_hit');
                }
                $memory_cache[$cache_key] = $cached_rows;
                return $memory_cache[$cache_key];
            }
        }

        $event_date = (string) get_post_meta($event_plan_id, '_vms_event_date', true);
        if ($event_plan_id <= 0 || $event_date === '') {
            return array();
        }

        if (!$allow_build) {
            vms_dt_reporting_trace('ticket_pace_rows', 'skipped', array(
                'event_plan_id' => $event_plan_id,
                'reason' => 'cache_miss_deferred',
            ), $started_at);
            return array();
        }

        if (vms_dt_reporting_memory_guard_should_skip('ticket_pace_rows', array(
            'event_plan_id' => $event_plan_id,
            'reason' => 'memory_guard',
        ))) {
            return array();
        }

        $website = vms_dt_reporting_build_website_detail_rows($event_plan_id);
        $ticket_rows = isset($website['ticket_rows']) && is_array($website['ticket_rows']) ? $website['ticket_rows'] : array();
        $daily = array();
        foreach ($ticket_rows as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $sold_date = (string) ($entry['sold_date'] ?? '');
            if ($sold_date === '') {
                $sold_date = substr((string) ($entry['sold_datetime'] ?? ''), 0, 10);
            }
            if ($sold_date === '' || $sold_date > $event_date) {
                continue;
            }
            $days_out = max(0, (int) floor((strtotime($event_date . ' 00:00:00') - strtotime($sold_date . ' 00:00:00')) / DAY_IN_SECONDS));
            if (!isset($daily[$sold_date])) {
                $daily[$sold_date] = array(
                    'sold_date' => $sold_date,
                    'days_out' => $days_out,
                    'online_qty' => 0,
                    'online_net_cents' => 0,
                    'door_qty' => 0,
                    'door_gross_cents' => 0,
                    'excluded_free_online_qty' => 0,
                );
            }
            $line_qty = (int) ($entry['quantity'] ?? 0);
            $line_net_cents = (int) ($entry['net_subtotal_cents'] ?? 0);
            if ($line_net_cents > 0) {
                $daily[$sold_date]['online_qty'] += $line_qty;
            }
            $daily[$sold_date]['online_net_cents'] += $line_net_cents;
            if (!isset($daily[$sold_date]['excluded_free_online_qty'])) {
                $daily[$sold_date]['excluded_free_online_qty'] = 0;
            }
            if ($line_qty > 0 && $line_net_cents <= 0) {
                $daily[$sold_date]['excluded_free_online_qty'] += $line_qty;
            }
        }

        // add show-day door evidence (register + QR/payment-link sold at the door)
        $square = vms_dt_reporting_build_square_line_evidence($event_plan_id, $filters);
        foreach ((array) ($square['ticket_rows'] ?? array()) as $entry) {
            if (!is_array($entry) || (($entry['treatment'] ?? '') !== 'counted')) {
                continue;
            }
            $sold_date = substr((string) ($entry['closed_at_local'] ?? ''), 0, 10);
            if ($sold_date === '' || $sold_date > $event_date) {
                continue;
            }
            $days_out = max(0, (int) floor((strtotime($event_date . ' 00:00:00') - strtotime($sold_date . ' 00:00:00')) / DAY_IN_SECONDS));
            if (!isset($daily[$sold_date])) {
                $daily[$sold_date] = array(
                    'sold_date' => $sold_date,
                    'days_out' => $days_out,
                    'online_qty' => 0,
                    'online_net_cents' => 0,
                    'door_qty' => 0,
                    'door_gross_cents' => 0,
                    'excluded_free_online_qty' => 0,
                );
            }
            $daily[$sold_date]['door_qty'] += (int) ($entry['quantity'] ?? 0);
            $daily[$sold_date]['door_gross_cents'] += (int) ($entry['gross_cents'] ?? 0);
        }

        if (empty($daily)) {
            return array();
        }

        ksort($daily);
        $rows = array();
        $cum_qty = 0;
        $cum_sales = 0;
        foreach ($daily as $date => $entry) {
            $day_qty = (int) ($entry['online_qty'] ?? 0) + (int) ($entry['door_qty'] ?? 0);
            $day_sales = (int) ($entry['online_net_cents'] ?? 0) + (int) ($entry['door_gross_cents'] ?? 0);
            $cum_qty += $day_qty;
            $cum_sales += $day_sales;
            $rows[] = array(
                'sold_date' => $date,
                'days_out' => (int) ($entry['days_out'] ?? 0),
                'online_qty' => (int) ($entry['online_qty'] ?? 0),
                'online_net_cents' => (int) ($entry['online_net_cents'] ?? 0),
                'door_qty' => (int) ($entry['door_qty'] ?? 0),
                'door_gross_cents' => (int) ($entry['door_gross_cents'] ?? 0),
                'excluded_free_online_qty' => (int) ($entry['excluded_free_online_qty'] ?? 0),
                'day_qty' => $day_qty,
                'day_sales_cents' => $day_sales,
                'cum_qty' => $cum_qty,
                'cum_sales_cents' => $cum_sales,
            );
        }
        $memory_cache[$cache_key] = array_values($rows);
        if ($use_transient_cache && $cache_key !== '') {
            set_transient($cache_key, $memory_cache[$cache_key], 15 * MINUTE_IN_SECONDS);
        }
        vms_dt_reporting_trace('ticket_pace_rows', 'rendered', array(
            'event_plan_id' => $event_plan_id,
            'daily_rows' => count($memory_cache[$cache_key]),
            'cache' => 'miss',
        ), $started_at);
        return $memory_cache[$cache_key];
    }
}


if (!function_exists('vms_dt_reporting_ticket_pace_today')) {
    function vms_dt_reporting_ticket_pace_today(): string
    {
        return function_exists('wp_date') && function_exists('wp_timezone')
            ? wp_date('Y-m-d', time(), wp_timezone())
            : gmdate('Y-m-d');
    }
}

if (!function_exists('vms_dt_reporting_ticket_pace_checkpoint_date')) {
    function vms_dt_reporting_ticket_pace_checkpoint_date(string $event_date, int $milestone): string
    {
        $event_date = trim($event_date);
        if ($event_date === '') {
            return '';
        }
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $event_date, $timezone);
        if (!$date) {
            return '';
        }
        return $date->modify('-' . max(0, $milestone) . ' days')->format('Y-m-d');
    }
}

if (!function_exists('vms_dt_reporting_ticket_pace_days_out_on')) {
    function vms_dt_reporting_ticket_pace_days_out_on(string $event_date, string $as_of_date): int
    {
        $event_date = trim($event_date);
        $as_of_date = trim($as_of_date);
        if ($event_date === '' || $as_of_date === '') {
            return 0;
        }
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $event = DateTimeImmutable::createFromFormat('!Y-m-d', $event_date, $timezone);
        $as_of = DateTimeImmutable::createFromFormat('!Y-m-d', $as_of_date, $timezone);
        if (!$event || !$as_of || $as_of >= $event) {
            return 0;
        }
        return max(0, (int) $as_of->diff($event)->format('%a'));
    }
}

if (!function_exists('vms_dt_reporting_ticket_pace_milestone_reached')) {
    function vms_dt_reporting_ticket_pace_milestone_reached(string $event_date, int $milestone, string $today = ''): bool
    {
        $checkpoint_date = vms_dt_reporting_ticket_pace_checkpoint_date($event_date, $milestone);
        if ($checkpoint_date === '') {
            return false;
        }
        $today = $today !== '' ? $today : vms_dt_reporting_ticket_pace_today();
        return strcmp($today, $checkpoint_date) >= 0;
    }
}

if (!function_exists('vms_dt_reporting_ticket_pace_pick_checkpoint')) {
    function vms_dt_reporting_ticket_pace_pick_checkpoint(array $rows, int $milestone, string $event_date = '', bool $include_zero_snapshot = false): ?array
    {
        $candidate = null;
        foreach ($rows as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $days_out = (int) ($entry['days_out'] ?? -1);
            if ($days_out < $milestone) {
                break;
            }
            $candidate = $entry;
        }
        if ($candidate) {
            if ($event_date !== '') {
                $candidate['checkpoint_date'] = vms_dt_reporting_ticket_pace_checkpoint_date($event_date, $milestone);
            }
            $candidate['snapshot_is_zero'] = false;
            return $candidate;
        }
        if ($include_zero_snapshot) {
            $checkpoint_date = $event_date !== '' ? vms_dt_reporting_ticket_pace_checkpoint_date($event_date, $milestone) : '';
            return array(
                'sold_date' => $checkpoint_date,
                'checkpoint_date' => $checkpoint_date,
                'days_out' => $milestone,
                'online_qty' => 0,
                'online_net_cents' => 0,
                'door_qty' => 0,
                'door_gross_cents' => 0,
                'excluded_free_online_qty' => 0,
                'day_qty' => 0,
                'day_sales_cents' => 0,
                'cum_qty' => 0,
                'cum_sales_cents' => 0,
                'snapshot_is_zero' => true,
            );
        }
        if (!empty($rows)) {
            $last = end($rows);
            return is_array($last) ? $last : null;
        }
        return null;
    }
}

if (!function_exists('vms_dt_reporting_ticket_pace_variance_label')) {
    function vms_dt_reporting_ticket_pace_variance_label(int $current, int $average, bool $money = false): string
    {
        $delta = $current - $average;
        if ($delta === 0) {
            return __('On average', 'vms-data-tools');
        }
        $direction = $delta > 0 ? __('Ahead', 'vms-data-tools') : __('Behind', 'vms-data-tools');
        $amount = $money ? vms_dt_rr_money(abs($delta)) : number_format(abs($delta));
        return sprintf('%s %s', $direction, $amount);
    }
}


if (!function_exists('vms_dt_reporting_ticket_pace_closest_milestone')) {
    function vms_dt_reporting_ticket_pace_closest_milestone(int $current_days_out, array $milestones): int
    {
        if (empty($milestones)) {
            return -1;
        }
        $closest = -1;
        $best_diff = null;
        foreach ($milestones as $milestone) {
            $milestone = (int) $milestone;
            if ($milestone < $current_days_out) {
                continue;
            }
            $diff = abs($milestone - $current_days_out);
            if ($best_diff === null || $diff < $best_diff || ($diff === $best_diff && $milestone < $closest)) {
                $closest = $milestone;
                $best_diff = $diff;
            }
        }
        return $closest;
    }
}

if (!function_exists('vms_dt_reporting_ticket_pace_projection')) {
    function vms_dt_reporting_ticket_pace_projection(array $latest_entry, array $history): array
    {
        $days_out = (int) ($latest_entry['days_out'] ?? 0);
        if ($days_out <= 0) {
            return array();
        }
        $basis = isset($history['milestone_averages'][$days_out]) && is_array($history['milestone_averages'][$days_out])
            ? $history['milestone_averages'][$days_out]
            : null;
        $avg_final_qty = (float) ($history['avg_final_qty'] ?? 0);
        $avg_final_sales_cents = (float) ($history['avg_final_sales_cents'] ?? 0);
        if (!$basis || $avg_final_qty <= 0 || $avg_final_sales_cents <= 0) {
            return array();
        }

        $current_qty = max(0.0, (float) ($latest_entry['cum_qty'] ?? 0));
        $current_sales_cents = max(0.0, (float) ($latest_entry['cum_sales_cents'] ?? 0));
        $basis_qty = max(0.0, (float) ($basis['avg_cum_qty'] ?? 0));
        $basis_sales_cents = max(0.0, (float) ($basis['avg_cum_sales_cents'] ?? 0));

        $remaining_avg_qty = max(0.0, $avg_final_qty - $basis_qty);
        $remaining_avg_sales_cents = max(0.0, $avg_final_sales_cents - $basis_sales_cents);
        $qty_ratio = $basis_qty > 0 ? ($current_qty / $basis_qty) : 0.0;
        $sales_ratio = $basis_sales_cents > 0 ? ($current_sales_cents / $basis_sales_cents) : 0.0;

        return array(
            'days_out' => $days_out,
            'basis_events' => (int) ($basis['events_count'] ?? 0),
            'basis_avg_qty' => (int) round($basis_qty),
            'basis_avg_sales_cents' => (int) round($basis_sales_cents),
            'avg_remaining_qty' => (int) round($remaining_avg_qty),
            'avg_remaining_sales_cents' => (int) round($remaining_avg_sales_cents),
            // Main projection is intentionally conservative: current results plus the average historical remaining sales from this point.
            'projected_final_qty' => max((int) round($current_qty), (int) round($current_qty + $remaining_avg_qty)),
            'projected_final_sales_cents' => max((int) round($current_sales_cents), (int) round($current_sales_cents + $remaining_avg_sales_cents)),
            // Keep the old pace-multiplier math available for context, but do not use it as the main KPI because it can explode on outlier events.
            'multiplier_projected_final_qty' => $basis_qty > 0 ? max(0, (int) round($avg_final_qty * $qty_ratio)) : 0,
            'multiplier_projected_final_sales_cents' => $basis_sales_cents > 0 ? max(0, (int) round($avg_final_sales_cents * $sales_ratio)) : 0,
            'qty_ratio' => $qty_ratio,
            'sales_ratio' => $sales_ratio,
        );
    }
}


if (!function_exists('vms_dt_reporting_ticket_pace_event_is_cancelled')) {
    function vms_dt_reporting_ticket_pace_event_is_cancelled(int $event_plan_id): bool
    {
        if ($event_plan_id <= 0) {
            return false;
        }

        $candidates = array();
        if (vms_dt_has_core_function('vms_event_plan_get_status')) {
            $candidates[] = (string) vms_dt_call_core_function('vms_event_plan_get_status', $event_plan_id, 'dashboard');
            $candidates[] = (string) vms_dt_call_core_function('vms_event_plan_get_status', $event_plan_id, 'raw');
        }
        $candidates[] = (string) get_post_meta($event_plan_id, '_vms_event_plan_status', true);
        if (vms_dt_has_core_function('vms_meta_key')) {
            $meta_key = (string) vms_dt_call_core_function('vms_meta_key', 'event_plan', 'status');
            if ($meta_key !== '' && $meta_key !== '_vms_event_plan_status') {
                $candidates[] = (string) get_post_meta($event_plan_id, $meta_key, true);
            }
        }

        foreach ($candidates as $status) {
            $status = trim((string) $status);
            if ($status === '') {
                continue;
            }
            if (vms_dt_has_core_function('vms_event_plan_status_normalize')) {
                $status = (string) vms_dt_call_core_function('vms_event_plan_status_normalize', $status);
            }
            $normalized = sanitize_key(strtolower($status));
            if (in_array($normalized, array('cancelled', 'canceled'), true)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('vms_dt_reporting_ticket_pace_snapshot_at_days_out')) {
    function vms_dt_reporting_ticket_pace_snapshot_at_days_out(array $rows, string $event_date, int $days_out, string $today = '', bool $force_current = false): array
    {
        $today = $today !== '' ? $today : vms_dt_reporting_ticket_pace_today();
        $checkpoint_date = $force_current ? $today : vms_dt_reporting_ticket_pace_checkpoint_date($event_date, $days_out);
        $is_reached = $force_current || vms_dt_reporting_ticket_pace_milestone_reached($event_date, $days_out, $today);
        $entry = null;
        if ($is_reached) {
            if ($force_current) {
                $latest = !empty($rows) ? end($rows) : array();
                $entry = is_array($latest) ? $latest : array();
                if (!empty($entry)) {
                    $entry['sold_date'] = $today;
                    $entry['days_out'] = $days_out;
                    $entry['checkpoint_date'] = $today;
                }
            } else {
                $entry = vms_dt_reporting_ticket_pace_pick_checkpoint($rows, $days_out, $event_date, true);
            }
        }
        return array(
            'checkpoint_date' => $checkpoint_date,
            'is_reached' => $is_reached,
            'entry' => is_array($entry) ? $entry : array(),
        );
    }
}

if (!function_exists('vms_dt_reporting_ticket_pace_snapshot_qty')) {
    function vms_dt_reporting_ticket_pace_snapshot_qty(array $snapshot): int
    {
        $entry = isset($snapshot['entry']) && is_array($snapshot['entry']) ? $snapshot['entry'] : array();
        return (int) ($entry['cum_qty'] ?? 0);
    }
}

if (!function_exists('vms_dt_reporting_ticket_pace_snapshot_sales')) {
    function vms_dt_reporting_ticket_pace_snapshot_sales(array $snapshot): int
    {
        $entry = isset($snapshot['entry']) && is_array($snapshot['entry']) ? $snapshot['entry'] : array();
        return (int) ($entry['cum_sales_cents'] ?? 0);
    }
}

if (!function_exists('vms_dt_reporting_build_ticket_pace_history')) {
    function vms_dt_reporting_build_ticket_pace_history(int $event_plan_id, array $milestones = array(), array $args = array()): array
    {
        if ($event_plan_id <= 0) {
            return array(
                'compare_event_ids' => array(),
                'compare_scope_label' => '',
                'milestone_averages' => array(),
                'avg_final_qty' => 0,
                'avg_final_sales_cents' => 0,
                'used_events_count' => 0,
                'skipped_cancelled_count' => 0,
            );
        }

        $event_date = (string) get_post_meta($event_plan_id, '_vms_event_date', true);
        $venue_id = (int) get_post_meta($event_plan_id, '_vms_venue_id', true);
        $filters = isset($args['filters']) && is_array($args['filters']) ? $args['filters'] : array();
        $history_limit = max(4, min(12, (int) ($args['limit'] ?? 8)));
        $event_ids = array();
        $scope_label = __('previous non-cancelled events', 'vms-data-tools');

        $query_args = array(
            'post_type' => 'vms_event_plan',
            'post_status' => function_exists('vms_dt_rr_event_post_statuses') ? vms_dt_rr_event_post_statuses() : array('publish'),
            'posts_per_page' => $history_limit,
            'orderby' => 'meta_value',
            'meta_key' => '_vms_event_date',
            'order' => 'DESC',
            'fields' => 'ids',
            'post__not_in' => array($event_plan_id),
            'meta_query' => array('relation' => 'AND'),
        );
        if ($event_date !== '') {
            $query_args['meta_query'][] = array(
                'key' => '_vms_event_date',
                'value' => $event_date,
                'compare' => '<',
                'type' => 'DATE',
            );
        }
        if ($venue_id > 0) {
            $query_args['meta_query'][] = array(
                'key' => '_vms_venue_id',
                'value' => $venue_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            );
            $scope_label = __('previous non-cancelled events at this venue', 'vms-data-tools');
        }

        $event_ids = array_map('intval', (array) get_posts($query_args));
        if (empty($event_ids) && $venue_id > 0) {
            $query_args['meta_query'] = array_slice((array) $query_args['meta_query'], 0, 2);
            $scope_label = __('previous non-cancelled events across all venues', 'vms-data-tools');
            $event_ids = array_map('intval', (array) get_posts($query_args));
        }

        $requested_points = array_values(array_unique(array_map('intval', $milestones)));
        $history_events = array();
        $milestone_totals = array();
        $final_qty_total = 0;
        $final_sales_total = 0;
        $used_events = 0;
        $used_event_ids = array();
        $skipped_cancelled_count = 0;

        foreach ($event_ids as $compare_event_id) {
            if (vms_dt_reporting_ticket_pace_event_is_cancelled((int) $compare_event_id)) {
                $skipped_cancelled_count++;
                continue;
            }
            $compare_event_date = (string) get_post_meta((int) $compare_event_id, '_vms_event_date', true);
            $compare_rows = vms_dt_reporting_ticket_pace_rows((int) $compare_event_id, array(
                'filters' => $filters,
            ));
            if (empty($compare_rows)) {
                continue;
            }
            $used_events++;
            $used_event_ids[] = (int) $compare_event_id;
            $final = end($compare_rows);
            if (is_array($final)) {
                $final_qty_total += (int) ($final['cum_qty'] ?? 0);
                $final_sales_total += (int) ($final['cum_sales_cents'] ?? 0);
            }
            $history_events[$compare_event_id] = $compare_rows;
            foreach ($requested_points as $milestone) {
                $best = vms_dt_reporting_ticket_pace_pick_checkpoint($compare_rows, $milestone, $compare_event_date, true);
                if (!$best) {
                    continue;
                }
                if (!isset($milestone_totals[$milestone])) {
                    $milestone_totals[$milestone] = array(
                        'cum_qty_total' => 0,
                        'cum_sales_total_cents' => 0,
                        'events_count' => 0,
                    );
                }
                $milestone_totals[$milestone]['cum_qty_total'] += (int) ($best['cum_qty'] ?? 0);
                $milestone_totals[$milestone]['cum_sales_total_cents'] += (int) ($best['cum_sales_cents'] ?? 0);
                $milestone_totals[$milestone]['events_count'] += 1;
            }
        }

        $milestone_averages = array();
        foreach ($milestone_totals as $milestone => $totals) {
            $count = max(1, (int) ($totals['events_count'] ?? 0));
            $milestone_averages[(int) $milestone] = array(
                'avg_cum_qty' => (int) round(((int) ($totals['cum_qty_total'] ?? 0)) / $count),
                'avg_cum_sales_cents' => (int) round(((int) ($totals['cum_sales_total_cents'] ?? 0)) / $count),
                'events_count' => $count,
            );
        }

        return array(
            'compare_event_ids' => $used_event_ids,
            'compare_scope_label' => $scope_label,
            'milestone_averages' => $milestone_averages,
            'avg_final_qty' => $used_events > 0 ? (int) round($final_qty_total / $used_events) : 0,
            'avg_final_sales_cents' => $used_events > 0 ? (int) round($final_sales_total / $used_events) : 0,
            'used_events_count' => $used_events,
            'skipped_cancelled_count' => $skipped_cancelled_count,
        );
    }
}

if (!function_exists('vms_dt_reporting_render_ticket_pace_svg')) {
    function vms_dt_reporting_render_ticket_pace_svg(array $current_points, array $average_points, array $comparison_points = array()): string
    {
        if (empty($current_points) && empty($average_points) && empty($comparison_points)) {
            return '<div class="vms-dt-empty">' . esc_html__('Not enough pace history is available to plot this chart yet.', 'vms-data-tools') . '</div>';
        }

        $width = 900;
        $height = 280;
        $left = 52;
        $right = 24;
        $top = 20;
        $bottom = 44;
        $plot_width = $width - $left - $right;
        $plot_height = $height - $top - $bottom;
        $labels = array_values(array_unique(array_merge(array_keys($current_points), array_keys($average_points), array_keys($comparison_points))));
        rsort($labels, SORT_NUMERIC);
        if (empty($labels)) {
            return '<div class="vms-dt-empty">' . esc_html__('Not enough pace history is available to plot this chart yet.', 'vms-data-tools') . '</div>';
        }
        $max = 0;
        foreach ($labels as $label) {
            $max = max($max, (int) ($current_points[$label] ?? 0), (int) ($average_points[$label] ?? 0), (int) ($comparison_points[$label] ?? 0));
        }
        if ($max <= 0) {
            $max = 1;
        }
        $count = count($labels);
        $step = $count > 1 ? ($plot_width / ($count - 1)) : 0;
        $current_poly = array();
        $average_poly = array();
        $comparison_poly = array();
        $dots = array();
        $comparison_dots = array();
        foreach ($labels as $index => $days_out) {
            $x = $left + (int) round($index * $step);
            if (array_key_exists($days_out, $average_points)) {
                $value = (int) $average_points[$days_out];
                $y = $top + (int) round($plot_height - (($value / $max) * $plot_height));
                $average_poly[] = $x . ',' . $y;
            }
            if (array_key_exists($days_out, $comparison_points)) {
                $value = (int) $comparison_points[$days_out];
                $y = $top + (int) round($plot_height - (($value / $max) * $plot_height));
                $comparison_poly[] = $x . ',' . $y;
                $comparison_dots[] = array('x' => $x, 'y' => $y, 'days_out' => $days_out, 'value' => $value);
            }
            if (array_key_exists($days_out, $current_points)) {
                $value = (int) $current_points[$days_out];
                $y = $top + (int) round($plot_height - (($value / $max) * $plot_height));
                $current_poly[] = $x . ',' . $y;
                $dots[] = array('x' => $x, 'y' => $y, 'days_out' => $days_out, 'value' => $value);
            }
        }
        $svg = '<svg class="vms-dt-trend-svg" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="Ticket pace trend chart">';
        for ($i = 0; $i < 5; $i++) {
            $gy = $top + (int) round(($plot_height / 4) * $i);
            $svg .= '<line class="vms-dt-gridline" x1="' . $left . '" y1="' . $gy . '" x2="' . ($width - $right) . '" y2="' . $gy . '"></line>';
        }
        $svg .= '<line class="vms-dt-axis" x1="' . $left . '" y1="' . ($height - $bottom) . '" x2="' . ($width - $right) . '" y2="' . ($height - $bottom) . '"></line>';
        if (!empty($average_poly)) {
            $svg .= '<polyline class="vms-dt-trend-line vms-dt-trend-line--average" points="' . esc_attr(implode(' ', $average_poly)) . '"></polyline>';
        }
        if (!empty($comparison_poly)) {
            $svg .= '<polyline class="vms-dt-trend-line vms-dt-trend-line--comparison" points="' . esc_attr(implode(' ', $comparison_poly)) . '"></polyline>';
        }
        if (!empty($current_poly)) {
            $svg .= '<polyline class="vms-dt-trend-line" points="' . esc_attr(implode(' ', $current_poly)) . '"></polyline>';
        }
        foreach ($comparison_dots as $dot) {
            $svg .= '<circle class="vms-dt-trend-dot vms-dt-trend-dot--comparison" cx="' . (int) $dot['x'] . '" cy="' . (int) $dot['y'] . '" r="4"></circle>';
        }
        foreach ($dots as $dot) {
            $svg .= '<circle class="vms-dt-trend-dot" cx="' . (int) $dot['x'] . '" cy="' . (int) $dot['y'] . '" r="4"></circle>';
        }
        foreach ($labels as $index => $days_out) {
            $x = $left + (int) round($index * $step);
            $svg .= '<text class="vms-dt-axis-text" x="' . $x . '" y="' . ($height - 16) . '" text-anchor="middle">' . esc_html((string) $days_out) . '</text>';
        }
        $svg .= '</svg>';
        return $svg;
    }
}


if (!function_exists('vms_dt_render_reporting_ticket_pace_page')) {
    function vms_dt_render_reporting_ticket_pace_page(): void
    {
        if (!vms_dt_current_user_can_manage_tools()) {
            return;
        }
        $started_at = microtime(true);
        if (vms_dt_has_core_function('vms_resource_fingerprint_flag')) {
            vms_dt_call_core_function('vms_resource_fingerprint_flag', 'dt_report', array(
                'page' => 'ticket_pace',
            ));
        }
        $effective_source = vms_dt_reporting_effective_source(vms_dt_get_menu_slug_reporting_ticket_pace());
        $filters = vms_dt_reporting_build_event_filters($effective_source);
        $compare_event_id = isset($effective_source['event_plan_b']) ? max(0, (int) wp_unslash($effective_source['event_plan_b'])) : 0;
        $pace_mode = vms_dt_reporting_ticket_pace_mode($effective_source, $compare_event_id);
        $load_history = vms_dt_reporting_ticket_pace_should_load_history($pace_mode, $compare_event_id);
        $event_plan_id = (int) ($filters['event_plan_id'] ?? 0);
        $rows = vms_dt_reporting_ticket_pace_rows($event_plan_id, array(
            'filters' => $filters,
        ));
        $event_title = $event_plan_id > 0 ? (string) get_the_title($event_plan_id) : '';
        $event_date = $event_plan_id > 0 ? (string) get_post_meta($event_plan_id, '_vms_event_date', true) : '';

        echo '<div class="wrap vms-dt-wrap">';
        echo '<h1>' . esc_html__('Ticket Pace', 'vms-data-tools') . '</h1>';
        echo '<p class="vms-dt-lead">' . esc_html__('Use this to answer the spreadsheet question: where were ticket sales 30 days out, 7 days out, 1 day out, and on show day?', 'vms-data-tools') . '</p>';
        vms_dt_reporting_nav(vms_dt_get_menu_slug_reporting_ticket_pace());
        vms_dt_reporting_render_event_picker(vms_dt_get_menu_slug_reporting_ticket_pace(), $filters, array(
            'title' => __('Event scope', 'vms-data-tools'),
            'desc' => __('Pick one event. This page uses the full website ticket lifecycle plus counted show-day door sales (register + QR/payment-link) for pacing.', 'vms-data-tools'),
            'show_compare_event' => true,
            'compare_event_id' => $compare_event_id,
            'hidden_fields' => array(
                'vms_dt_pace_mode' => $pace_mode === 'full' ? 'full' : null,
            ),
        ));

        if ($event_plan_id <= 0 || $event_date === '') {
            echo '<div class="notice notice-warning"><p>' . esc_html__('No event plan matched the current filters.', 'vms-data-tools') . '</p></div></div>';
            vms_dt_reporting_trace('ticket_pace_page', 'skipped', array(
                'event_plan_id' => $event_plan_id,
                'reason' => 'missing_event_plan',
            ), $started_at);
            return;
        }

        $milestones = array(30, 14, 7, 3, 1, 0);
        $today = vms_dt_reporting_ticket_pace_today();
        $current_days_out = vms_dt_reporting_ticket_pace_days_out_on($event_date, $today);
        $latest_entry = !empty($rows) ? end($rows) : array();
        $current_snapshot = is_array($latest_entry) ? $latest_entry : array();
        if (!empty($current_snapshot)) {
            $current_snapshot['sold_date'] = $today;
            $current_snapshot['days_out'] = $current_days_out;
        }
        $history_points = $milestones;
        $history_points[] = $current_days_out;
        $history = array(
            'compare_event_ids' => array(),
            'compare_scope_label' => '',
            'milestone_averages' => array(),
            'avg_final_qty' => 0,
            'avg_final_sales_cents' => 0,
            'used_events_count' => 0,
            'skipped_cancelled_count' => 0,
        );
        if ($load_history) {
            $history = vms_dt_reporting_build_ticket_pace_history($event_plan_id, $history_points, array(
                'filters' => $filters,
                'limit' => $compare_event_id > 0 ? 12 : 8,
            ));
        } else {
            vms_dt_reporting_trace('ticket_pace_history', 'skipped', array(
                'event_plan_id' => $event_plan_id,
                'reason' => 'deferred_summary_mode',
            ), $started_at);
        }
        $milestone_rows = array();
        $chart_current = array();
        $chart_average = array();
        foreach ($milestones as $milestone) {
            $checkpoint_date = vms_dt_reporting_ticket_pace_checkpoint_date($event_date, $milestone);
            $is_reached = vms_dt_reporting_ticket_pace_milestone_reached($event_date, $milestone, $today);
            $best = $is_reached ? vms_dt_reporting_ticket_pace_pick_checkpoint($rows, $milestone, $event_date, true) : null;
            $milestone_rows[$milestone] = array(
                'checkpoint_date' => $checkpoint_date,
                'is_reached' => $is_reached,
                'entry' => $best,
            );
            if ($is_reached && is_array($best)) {
                $chart_current[$milestone] = (int) ($best['cum_qty'] ?? 0);
            }
            if (isset($history['milestone_averages'][$milestone])) {
                $chart_average[$milestone] = (int) ($history['milestone_averages'][$milestone]['avg_cum_qty'] ?? 0);
            }
        }
        if (!empty($current_snapshot)) {
            $chart_current[$current_days_out] = (int) ($current_snapshot['cum_qty'] ?? 0);
            if (isset($history['milestone_averages'][$current_days_out])) {
                $chart_average[$current_days_out] = (int) ($history['milestone_averages'][$current_days_out]['avg_cum_qty'] ?? 0);
            }
        }
        if ($compare_event_id === $event_plan_id) {
            $compare_event_id = 0;
        }
        $compare_event_title = '';
        $compare_event_date = '';
        $compare_rows = array();
        $compare_final_qty = 0;
        $compare_final_sales_cents = 0;
        $chart_compare = array();
        $comparison_points = array_values(array_unique(array_map('intval', array_merge($milestones, array($current_days_out)))));
        rsort($comparison_points, SORT_NUMERIC);
        if ($compare_event_id > 0) {
            $compare_event_title = (string) get_the_title($compare_event_id);
            $compare_event_date = (string) get_post_meta($compare_event_id, '_vms_event_date', true);
            $compare_rows = $compare_event_date !== '' ? vms_dt_reporting_ticket_pace_rows($compare_event_id, array(
                'filters' => $filters,
            )) : array();
            if (!empty($compare_rows)) {
                $compare_final = end($compare_rows);
                if (is_array($compare_final)) {
                    $compare_final_qty = (int) ($compare_final['cum_qty'] ?? 0);
                    $compare_final_sales_cents = (int) ($compare_final['cum_sales_cents'] ?? 0);
                }
                foreach ($comparison_points as $point_days_out) {
                    $compare_snapshot = vms_dt_reporting_ticket_pace_snapshot_at_days_out($compare_rows, $compare_event_date, (int) $point_days_out, $today, false);
                    if (!empty($compare_snapshot['is_reached']) && !empty($compare_snapshot['entry'])) {
                        $chart_compare[(int) $point_days_out] = vms_dt_reporting_ticket_pace_snapshot_qty($compare_snapshot);
                    }
                }
            }
        }
        $history_loaded = $load_history && !empty($history['milestone_averages']);
        $load_history_url = vms_dt_reporting_ticket_pace_mode_url('full');
        $projection = ($history_loaded && !empty($current_snapshot)) ? vms_dt_reporting_ticket_pace_projection($current_snapshot, $history) : array();
        $closest_milestone = vms_dt_reporting_ticket_pace_closest_milestone($current_days_out, $milestones);
        $excluded_free_qty_total = 0;
        foreach ($rows as $pace_row) {
            if (is_array($pace_row)) {
                $excluded_free_qty_total += (int) ($pace_row['excluded_free_online_qty'] ?? 0);
            }
        }

        echo '<div class="vms-dt-callout"><strong>' . esc_html($event_title) . '</strong><div>' . esc_html($event_date) . '</div></div>';

        $final_qty = !empty($rows) ? (int) (end($rows)['cum_qty'] ?? 0) : 0;
        $final_sales_cents = !empty($rows) ? (int) (end($rows)['cum_sales_cents'] ?? 0) : 0;
        echo '<div class="vms-dt-kpi-grid vms-dt-section">';
        $cards = array(
            array(__('Current paid qty', 'vms-data-tools'), number_format($final_qty), __('current event cumulative paid tickets', 'vms-data-tools')),
            array(__('Current sales', 'vms-data-tools'), vms_dt_rr_money($final_sales_cents), __('current event cumulative ticket sales', 'vms-data-tools')),
            array(
                __('Non-cancelled avg qty', 'vms-data-tools'),
                $history_loaded ? number_format((int) ($history['avg_final_qty'] ?? 0)) : '—',
                $history_loaded
                    ? sprintf(__('based on %d %s', 'vms-data-tools'), (int) ($history['used_events_count'] ?? 0), (string) ($history['compare_scope_label'] ?? __('previous events', 'vms-data-tools')))
                    : __('Deferred until benchmark history is requested.', 'vms-data-tools')
            ),
            array(
                __('Non-cancelled avg sales', 'vms-data-tools'),
                $history_loaded ? vms_dt_rr_money((int) ($history['avg_final_sales_cents'] ?? 0)) : '—',
                $history_loaded ? __('non-cancelled average final ticket sales', 'vms-data-tools') : __('Deferred until benchmark history is requested.', 'vms-data-tools')
            ),
        );
        if (!empty($projection)) {
            $cards[] = array(__('Projected finish', 'vms-data-tools'), number_format((int) ($projection['projected_final_qty'] ?? 0)) . ' / ' . vms_dt_rr_money((int) ($projection['projected_final_sales_cents'] ?? 0)), sprintf(__('current total + historical remaining from %d days out', 'vms-data-tools'), (int) ($projection['days_out'] ?? 0)));
        }
        foreach ($cards as $card) {
            echo '<div class="vms-dt-kpi"><p class="vms-dt-kpi-label">' . esc_html($card[0]) . '</p><p class="vms-dt-kpi-value">' . esc_html($card[1]) . '</p><p class="vms-dt-kpi-sub">' . esc_html($card[2]) . '</p></div>';
        }
        echo '</div>';

        if (!$load_history) {
            echo '<div class="notice notice-info inline"><p>' . esc_html__('Benchmark history is deferred on first load so this report stays lightweight on shared hosting. Load it only when you need historical averages or a selected-event comparison.', 'vms-data-tools') . '</p><p><a class="button" href="' . esc_url($load_history_url) . '">' . esc_html__('Load benchmark history', 'vms-data-tools') . '</a></p></div>';
        }

        echo '<div class="vms-dt-two-col vms-dt-section">';
        $pace_chart_desc = !empty($compare_event_id)
            ? __('Solid line is this event at reached checkpoints plus today’s current total. Dotted line is the selected comparison event at the same days-out checkpoints. Dashed line is the non-cancelled historical average.', 'vms-data-tools')
            : ($history_loaded
                ? __('Solid line is this event at reached checkpoints plus today’s current total. Dashed line is the non-cancelled historical average cumulative ticket count at the same checkpoints.', 'vms-data-tools')
                : __('Solid line is this event at reached checkpoints plus today’s current total. Historical average and comparison lines stay deferred until you explicitly load benchmark history.', 'vms-data-tools'));
        echo '<div class="vms-dt-chart-card"><div class="vms-dt-card-head"><div><h2>' . esc_html__('Pace trend', 'vms-data-tools') . '</h2><p class="vms-dt-section-desc">' . esc_html($pace_chart_desc) . '</p></div></div>' . vms_dt_reporting_render_ticket_pace_svg($chart_current, $chart_average, $chart_compare);
        echo '<div class="vms-dt-legend"><span class="vms-dt-legend-item"><span class="vms-dt-legend-swatch vms-dt-legend-swatch--pace-current"></span>' . esc_html__('This event', 'vms-data-tools') . '</span>';
        if ($history_loaded) {
            echo '<span class="vms-dt-legend-item"><span class="vms-dt-legend-swatch vms-dt-legend-swatch--pace-average"></span>' . esc_html__('Historical average', 'vms-data-tools') . '</span>';
        }
        if (!empty($compare_event_id)) {
            echo '<span class="vms-dt-legend-item"><span class="vms-dt-legend-swatch vms-dt-legend-swatch--pace-comparison"></span>' . esc_html__('Selected event', 'vms-data-tools') . '</span>';
        }
        echo '</div></div>';

        echo '<div class="vms-dt-table-card"><div class="vms-dt-card-head"><div><h2>' . esc_html__('How this compares', 'vms-data-tools') . '</h2><p class="vms-dt-section-desc">' . esc_html__('Averages exclude cancelled events. Use the optional selected-event comparison when one past show is a better benchmark than the full average.', 'vms-data-tools') . '</p></div></div>';
        echo '<ul class="vms-dt-summary-list">';
        if ($history_loaded) {
            echo '<li><span class="vms-dt-summary-key">' . esc_html__('Comparison group', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html(number_format((int) ($history['used_events_count'] ?? 0))) . '</span></li>';
            echo '<li><span class="vms-dt-summary-key">' . esc_html__('Scope', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html((string) ($history['compare_scope_label'] ?? __('previous events', 'vms-data-tools'))) . '</span></li>';
        } else {
            echo '<li><span class="vms-dt-summary-key">' . esc_html__('Benchmark history', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html__('Deferred until requested', 'vms-data-tools') . '</span></li>';
            echo '<li><span class="vms-dt-summary-key">' . esc_html__('Quick action', 'vms-data-tools') . '</span><span class="vms-dt-summary-value"><a href="' . esc_url($load_history_url) . '">' . esc_html__('Load benchmark history', 'vms-data-tools') . '</a></span></li>';
        }
        if ($history_loaded && (int) ($history['skipped_cancelled_count'] ?? 0) > 0) {
            echo '<li><span class="vms-dt-summary-key">' . esc_html__('Cancelled excluded', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html(number_format((int) ($history['skipped_cancelled_count'] ?? 0))) . '</span></li>';
        }
        if (!empty($compare_event_id)) {
            $compare_summary = trim($compare_event_title . ($compare_event_date !== '' ? ' · ' . $compare_event_date : ''));
            if ($compare_summary !== '') {
                echo '<li><span class="vms-dt-summary-key">' . esc_html__('Selected comparison', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html($compare_summary) . '</span></li>';
                echo '<li><span class="vms-dt-summary-key">' . esc_html__('Selected final', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html(number_format($compare_final_qty) . ' / ' . vms_dt_rr_money($compare_final_sales_cents)) . '</span></li>';
            }
        }
        echo '<li><span class="vms-dt-summary-key">' . esc_html__('Today', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html(sprintf(_n('%d day out', '%d days out', (int) $current_days_out, 'vms-data-tools'), (int) $current_days_out)) . '</span></li>';
        if ($closest_milestone >= 0) {
            $closest_label = sprintf(_n('%d day out', '%d days out', $closest_milestone, 'vms-data-tools'), $closest_milestone);
            echo '<li><span class="vms-dt-summary-key">' . esc_html__('Closest standard checkpoint', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html($closest_label) . '</span></li>';
        }
        if ($excluded_free_qty_total > 0) {
            echo '<li><span class="vms-dt-summary-key">' . esc_html__('Free qty excluded', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html(number_format($excluded_free_qty_total)) . '</span></li>';
        }
        if (!empty($projection)) {
            echo '<li><span class="vms-dt-summary-key">' . esc_html__('Projection basis', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html(sprintf(__('%d days out', 'vms-data-tools'), (int) ($projection['days_out'] ?? 0))) . '</span></li>';
            echo '<li><span class="vms-dt-summary-key">' . esc_html__('Projection note', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html__('Main projection uses current results plus the average historical remaining sales from today, so one hot checkpoint does not wildly inflate the finish.', 'vms-data-tools') . '</span></li>';
            if (!empty($projection['multiplier_projected_final_qty']) || !empty($projection['multiplier_projected_final_sales_cents'])) {
                $multiplier_value = number_format((int) ($projection['multiplier_projected_final_qty'] ?? 0)) . ' / ' . vms_dt_rr_money((int) ($projection['multiplier_projected_final_sales_cents'] ?? 0));
                echo '<li><span class="vms-dt-summary-key">' . esc_html__('High-side pace math', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html(sprintf(__('%s if the current multiplier holds; use cautiously.', 'vms-data-tools'), $multiplier_value)) . '</span></li>';
            }
        } elseif (!$history_loaded) {
            echo '<li><span class="vms-dt-summary-key">' . esc_html__('Projection note', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html__('Projection and averages stay deferred until you explicitly load benchmark history.', 'vms-data-tools') . '</span></li>';
        } else {
            echo '<li><span class="vms-dt-summary-key">' . esc_html__('Projection note', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html__('Need more historical checkpoint data before projecting a likely finish.', 'vms-data-tools') . '</span></li>';
        }
        echo '</ul></div>';
        echo '</div>';

        echo '<div class="vms-dt-table-card vms-dt-section"><div class="vms-dt-card-head"><div><h2>' . esc_html__('Milestones', 'vms-data-tools') . '</h2><p class="vms-dt-section-desc">' . esc_html__('These are the checkpoint numbers you used to track in spreadsheets, with a live Today row inserted between standard checkpoints when needed. Future checkpoints stay marked as not reached yet so the current total is not repeated into future rows early.', 'vms-data-tools') . '</p></div></div>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Milestone', 'vms-data-tools') . '</th><th>' . esc_html__('As of date', 'vms-data-tools') . '</th><th>' . esc_html__('Current qty', 'vms-data-tools') . '</th><th>' . esc_html__('Avg qty', 'vms-data-tools') . '</th><th>' . esc_html__('Qty variance', 'vms-data-tools') . '</th><th>' . esc_html__('Current sales', 'vms-data-tools') . '</th><th>' . esc_html__('Avg sales', 'vms-data-tools') . '</th><th>' . esc_html__('Sales variance', 'vms-data-tools') . '</th></tr></thead><tbody>';
        if (empty($milestone_rows)) {
            echo '<tr><td colspan="8">' . esc_html__('No pacing data was available for this event yet.', 'vms-data-tools') . '</td></tr>';
        } else {
            $milestone_keys = array_values(array_map('intval', array_keys($milestone_rows)));
            $show_today_row = !empty($current_snapshot) && !in_array((int) $current_days_out, $milestone_keys, true);
            $today_row_printed = false;
            $render_today_row = static function () use ($current_days_out, $today, $current_snapshot, $history, $history_loaded): void {
                $avg = isset($history['milestone_averages'][$current_days_out]) && is_array($history['milestone_averages'][$current_days_out]) ? $history['milestone_averages'][$current_days_out] : array();
                $avg_qty = (int) ($avg['avg_cum_qty'] ?? 0);
                $avg_sales = (int) ($avg['avg_cum_sales_cents'] ?? 0);
                $today_qty = (int) ($current_snapshot['cum_qty'] ?? 0);
                $today_sales = (int) ($current_snapshot['cum_sales_cents'] ?? 0);
                $today_label = sprintf(__('Today / current (%d days out)', 'vms-data-tools'), (int) $current_days_out);
                echo '<tr class="vms-dt-row-current"><td><strong>' . esc_html($today_label) . '</strong></td><td>' . esc_html($today) . '</td><td>' . esc_html(number_format($today_qty)) . '</td><td>' . ($history_loaded ? esc_html(number_format($avg_qty)) : '<span class="description">—</span>') . '</td><td>' . ($history_loaded ? esc_html(vms_dt_reporting_ticket_pace_variance_label($today_qty, $avg_qty, false)) : '<span class="description">—</span>') . '</td><td><strong>' . esc_html(vms_dt_rr_money($today_sales)) . '</strong></td><td>' . ($history_loaded ? esc_html(vms_dt_rr_money($avg_sales)) : '<span class="description">—</span>') . '</td><td>' . ($history_loaded ? esc_html(vms_dt_reporting_ticket_pace_variance_label($today_sales, $avg_sales, true)) : '<span class="description">—</span>') . '</td></tr>';
            };
            if ($show_today_row && !empty($milestone_keys) && (int) $current_days_out > max($milestone_keys)) {
                $render_today_row();
                $today_row_printed = true;
            }
            foreach ($milestone_keys as $milestone_index => $milestone) {
                $milestone_row = isset($milestone_rows[$milestone]) && is_array($milestone_rows[$milestone]) ? $milestone_rows[$milestone] : array();
                $avg = isset($history['milestone_averages'][$milestone]) && is_array($history['milestone_averages'][$milestone]) ? $history['milestone_averages'][$milestone] : array();
                $avg_qty = (int) ($avg['avg_cum_qty'] ?? 0);
                $avg_sales = (int) ($avg['avg_cum_sales_cents'] ?? 0);
                $is_reached = !empty($milestone_row['is_reached']);
                $entry = isset($milestone_row['entry']) && is_array($milestone_row['entry']) ? $milestone_row['entry'] : array();
                $checkpoint_date = (string) ($milestone_row['checkpoint_date'] ?? ($entry['checkpoint_date'] ?? ($entry['sold_date'] ?? '')));
                $is_closest = $is_reached && ((int) $milestone === (int) $closest_milestone);
                $row_classes = array();
                if ($is_closest) {
                    $row_classes[] = 'vms-dt-row-highlight';
                }
                if (!$is_reached) {
                    $row_classes[] = 'vms-dt-row-muted';
                }
                $row_class = !empty($row_classes) ? ' class="' . esc_attr(implode(' ', $row_classes)) . '"' : '';
                $milestone_label = sprintf(_n('%d day out', '%d days out', (int) $milestone, 'vms-data-tools'), (int) $milestone);
                if ($is_closest) {
                    $milestone_label .= ' • ' . __('Closest reached checkpoint', 'vms-data-tools');
                }
                if (!$is_reached) {
                    echo '<tr' . $row_class . '><td><strong>' . esc_html($milestone_label) . '</strong></td><td>' . esc_html($checkpoint_date) . '</td><td><span class="description">' . esc_html__('Not reached yet', 'vms-data-tools') . '</span></td><td>' . ($history_loaded ? esc_html(number_format($avg_qty)) : '<span class="description">—</span>') . '</td><td><span class="description">—</span></td><td><span class="description">' . esc_html__('Not reached yet', 'vms-data-tools') . '</span></td><td>' . ($history_loaded ? esc_html(vms_dt_rr_money($avg_sales)) : '<span class="description">—</span>') . '</td><td><span class="description">—</span></td></tr>';
                } else {
                    $current_qty = (int) ($entry['cum_qty'] ?? 0);
                    $current_sales = (int) ($entry['cum_sales_cents'] ?? 0);
                    echo '<tr' . $row_class . '><td><strong>' . esc_html($milestone_label) . '</strong></td><td>' . esc_html($checkpoint_date) . '</td><td>' . esc_html(number_format($current_qty)) . '</td><td>' . ($history_loaded ? esc_html(number_format($avg_qty)) : '<span class="description">—</span>') . '</td><td>' . ($history_loaded ? esc_html(vms_dt_reporting_ticket_pace_variance_label($current_qty, $avg_qty, false)) : '<span class="description">—</span>') . '</td><td><strong>' . esc_html(vms_dt_rr_money($current_sales)) . '</strong></td><td>' . ($history_loaded ? esc_html(vms_dt_rr_money($avg_sales)) : '<span class="description">—</span>') . '</td><td>' . ($history_loaded ? esc_html(vms_dt_reporting_ticket_pace_variance_label($current_sales, $avg_sales, true)) : '<span class="description">—</span>') . '</td></tr>';
                }
                $next_milestone = $milestone_keys[$milestone_index + 1] ?? null;
                if ($show_today_row && !$today_row_printed && (int) $milestone > (int) $current_days_out && ($next_milestone === null || (int) $next_milestone < (int) $current_days_out)) {
                    $render_today_row();
                    $today_row_printed = true;
                }
            }
        }
        echo '</tbody></table></div>';
        if ($compare_event_id > 0) {
            echo '<div class="vms-dt-table-card vms-dt-section"><div class="vms-dt-card-head"><div><h2>' . esc_html__('Event-to-event pace comparison', 'vms-data-tools') . '</h2><p class="vms-dt-section-desc">' . esc_html__('Compares this event to the selected event at the same days-out checkpoints. This is more useful than averaging unlike shows when you have one event that feels like the right benchmark.', 'vms-data-tools') . '</p></div></div>';
            if (empty($compare_rows) || $compare_event_date === '') {
                echo '<div class="vms-dt-empty">' . esc_html__('The selected comparison event does not have usable ticket pace rows yet.', 'vms-data-tools') . '</div></div>';
            } else {
                echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Days out', 'vms-data-tools') . '</th><th>' . esc_html__('This event date', 'vms-data-tools') . '</th><th>' . esc_html__('This qty', 'vms-data-tools') . '</th><th>' . esc_html__('This sales', 'vms-data-tools') . '</th><th>' . esc_html__('Comparison date', 'vms-data-tools') . '</th><th>' . esc_html__('Comparison qty', 'vms-data-tools') . '</th><th>' . esc_html__('Comparison sales', 'vms-data-tools') . '</th><th>' . esc_html__('Difference', 'vms-data-tools') . '</th></tr></thead><tbody>';
                foreach ($comparison_points as $point_days_out) {
                    $point_days_out = (int) $point_days_out;
                    $is_today_point = ($point_days_out === (int) $current_days_out);
                    $current_snapshot_for_point = vms_dt_reporting_ticket_pace_snapshot_at_days_out($rows, $event_date, $point_days_out, $today, $is_today_point);
                    $compare_snapshot_for_point = vms_dt_reporting_ticket_pace_snapshot_at_days_out($compare_rows, $compare_event_date, $point_days_out, $today, false);
                    $current_reached = !empty($current_snapshot_for_point['is_reached']) && !empty($current_snapshot_for_point['entry']);
                    $compare_reached = !empty($compare_snapshot_for_point['is_reached']) && !empty($compare_snapshot_for_point['entry']);
                    $current_qty = $current_reached ? vms_dt_reporting_ticket_pace_snapshot_qty($current_snapshot_for_point) : 0;
                    $current_sales = $current_reached ? vms_dt_reporting_ticket_pace_snapshot_sales($current_snapshot_for_point) : 0;
                    $compare_qty = $compare_reached ? vms_dt_reporting_ticket_pace_snapshot_qty($compare_snapshot_for_point) : 0;
                    $compare_sales = $compare_reached ? vms_dt_reporting_ticket_pace_snapshot_sales($compare_snapshot_for_point) : 0;
                    $diff = ($current_reached && $compare_reached)
                        ? vms_dt_reporting_ticket_pace_variance_label($current_qty, $compare_qty, false) . ' / ' . vms_dt_reporting_ticket_pace_variance_label($current_sales, $compare_sales, true)
                        : '—';
                    $row_classes = array();
                    if ($is_today_point) {
                        $row_classes[] = 'vms-dt-row-current';
                    } elseif (!$current_reached) {
                        $row_classes[] = 'vms-dt-row-muted';
                    }
                    $row_class = !empty($row_classes) ? ' class="' . esc_attr(implode(' ', $row_classes)) . '"' : '';
                    $days_label = sprintf(_n('%d day out', '%d days out', $point_days_out, 'vms-data-tools'), $point_days_out);
                    if ($is_today_point) {
                        $days_label .= ' • ' . __('Today/current', 'vms-data-tools');
                    }
                    echo '<tr' . $row_class . '><td><strong>' . esc_html($days_label) . '</strong></td><td>' . esc_html((string) ($current_snapshot_for_point['checkpoint_date'] ?? '')) . '</td><td>' . ($current_reached ? esc_html(number_format($current_qty)) : '<span class="description">' . esc_html__('Not reached yet', 'vms-data-tools') . '</span>') . '</td><td>' . ($current_reached ? '<strong>' . esc_html(vms_dt_rr_money($current_sales)) . '</strong>' : '<span class="description">' . esc_html__('Not reached yet', 'vms-data-tools') . '</span>') . '</td><td>' . esc_html((string) ($compare_snapshot_for_point['checkpoint_date'] ?? '')) . '</td><td>' . ($compare_reached ? esc_html(number_format($compare_qty)) : '<span class="description">' . esc_html__('Not reached yet', 'vms-data-tools') . '</span>') . '</td><td>' . ($compare_reached ? '<strong>' . esc_html(vms_dt_rr_money($compare_sales)) . '</strong>' : '<span class="description">' . esc_html__('Not reached yet', 'vms-data-tools') . '</span>') . '</td><td>' . esc_html($diff) . '</td></tr>';
                }
                echo '</tbody></table></div>';
            }
        }
        $total_day_qty = 0; $total_online_qty = 0; $total_door_qty = 0; $total_day_sales = 0;
        foreach ($rows as $entry) {
            $total_day_qty += (int) ($entry['day_qty'] ?? 0);
            $total_online_qty += (int) ($entry['online_qty'] ?? 0);
            $total_door_qty += (int) ($entry['door_qty'] ?? 0);
            $total_day_sales += (int) ($entry['day_sales_cents'] ?? 0);
        }
        $daily_pagination = vms_dt_reporting_paginate_rows('ticket_pace_daily', $rows, 120, true);
        $skip_daily_table = vms_dt_reporting_memory_guard_should_skip('ticket_pace_daily', array(
            'event_plan_id' => $event_plan_id,
            'row_count' => count($rows),
        ));

        echo '<div class="vms-dt-table-card vms-dt-section"><div class="vms-dt-card-head"><div><h2>' . esc_html__('Daily pace', 'vms-data-tools') . '</h2><p class="vms-dt-section-desc">' . esc_html__('Daily build toward the event. Online rows cover the full website ticket lifecycle using paid-ticket qty only; door rows are counted show-day door sales when they happened.', 'vms-data-tools') . '</p></div></div>';
        if ($skip_daily_table) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('The daily pace row table was skipped because admin memory usage is already high. Narrow the event scope or reload the page after clearing other heavy admin tabs.', 'vms-data-tools') . '</p></div>';
        } else {
            vms_dt_reporting_render_pagination_controls($daily_pagination);
            echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Sold date', 'vms-data-tools') . '</th><th>' . esc_html__('Days out', 'vms-data-tools') . '</th><th>' . esc_html__('Online qty', 'vms-data-tools') . '</th><th>' . esc_html__('Door qty', 'vms-data-tools') . '</th><th>' . esc_html__('Day qty', 'vms-data-tools') . '</th><th>' . esc_html__('Day sales', 'vms-data-tools') . '</th><th>' . esc_html__('Cum qty', 'vms-data-tools') . '</th><th>' . esc_html__('Cum sales', 'vms-data-tools') . '</th></tr></thead><tbody>';
            if (empty($rows)) {
                echo '<tr><td colspan="8">' . esc_html__('No daily pace rows were available for this event.', 'vms-data-tools') . '</td></tr>';
            } else {
                foreach ((array) ($daily_pagination['rows'] ?? array()) as $entry) {
                    echo '<tr><td>' . esc_html((string) ($entry['sold_date'] ?? '')) . '</td><td>' . esc_html(number_format((int) ($entry['days_out'] ?? 0))) . '</td><td>' . esc_html(number_format((int) ($entry['online_qty'] ?? 0))) . '</td><td>' . esc_html(number_format((int) ($entry['door_qty'] ?? 0))) . '</td><td><strong>' . esc_html(number_format((int) ($entry['day_qty'] ?? 0))) . '</strong></td><td>' . esc_html(vms_dt_rr_money((int) ($entry['day_sales_cents'] ?? 0))) . '</td><td>' . esc_html(number_format((int) ($entry['cum_qty'] ?? 0))) . '</td><td><strong>' . esc_html(vms_dt_rr_money((int) ($entry['cum_sales_cents'] ?? 0))) . '</strong></td></tr>';
                }
            }
            echo '</tbody><tfoot><tr><th colspan="2">' . esc_html__('Totals', 'vms-data-tools') . '</th><th>' . esc_html(number_format($total_online_qty)) . '</th><th>' . esc_html(number_format($total_door_qty)) . '</th><th>' . esc_html(number_format($total_day_qty)) . '</th><th>' . esc_html(vms_dt_rr_money($total_day_sales)) . '</th><th>' . esc_html(number_format(!empty($rows) ? (int) (end($rows)['cum_qty'] ?? 0) : 0)) . '</th><th><strong>' . esc_html(vms_dt_rr_money(!empty($rows) ? (int) (end($rows)['cum_sales_cents'] ?? 0) : 0)) . '</strong></th></tr></tfoot></table>';
            vms_dt_reporting_render_pagination_controls($daily_pagination);
        }
        echo '</div>';
        echo '</div>';
        vms_dt_reporting_trace('ticket_pace_page', 'rendered', array(
            'event_plan_id' => $event_plan_id,
            'daily_rows' => count($rows),
            'compare_event_id' => $compare_event_id,
        ), $started_at);
    }
}


if (!function_exists('vms_dt_render_reporting_performer_payouts_page')) {
    function vms_dt_render_reporting_performer_payouts_page(): void
    {
        if (!vms_dt_current_user_can_manage_tools()) {
            return;
        }
        $filters = vms_dt_reporting_build_event_filters(vms_dt_reporting_effective_source(vms_dt_get_menu_slug_reporting_performer_payouts()));
        $model = vms_dt_reporting_build_event_model($filters);
        $row = $model['row'];
        $costs = $model['costs'];
        echo '<div class="wrap vms-dt-wrap">';
        echo '<h1>' . esc_html__('Performer Payouts', 'vms-data-tools') . '</h1>'; 
        echo '<p class="vms-dt-lead">' . esc_html__('Use this page when you need the band-facing payout story for one event.', 'vms-data-tools') . '</p>';
        vms_dt_reporting_nav(vms_dt_get_menu_slug_reporting_performer_payouts());
        vms_dt_reporting_render_event_picker(vms_dt_get_menu_slug_reporting_performer_payouts(), $filters, array(
            'title' => __('Event scope', 'vms-data-tools'),
            'desc' => __('Pick one event and this page will summarize the payout math.', 'vms-data-tools'),
        ));
        if (empty($row['event_plan_id'])) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('No event plan matched the current filters.', 'vms-data-tools') . '</p></div></div>';
            return;
        }
        $band_name = (string) ($costs['band_name'] ?? '');
        echo '<div class="vms-dt-callout"><strong>' . esc_html((string) ($row['event_title'] ?? '')) . '</strong><div>' . esc_html((string) ($row['event_date'] ?? '')) . ' · ' . esc_html($band_name !== '' ? $band_name : __('No band linked', 'vms-data-tools')) . '</div></div>';
        echo '<div class="vms-dt-grid vms-dt-grid--cards vms-dt-section">';
        $cards = array(
            array(__('Tickets counted', 'vms-data-tools'), number_format((int) ($costs['ticket_qty_total'] ?? 0)), __('website ticket qty + counted direct ticket qty', 'vms-data-tools')),
            array(__('Total ticket sales', 'vms-data-tools'), vms_dt_rr_money((int) ($costs['ticket_sales_total_cents'] ?? 0)), __('ticket revenue basis for payout checks', 'vms-data-tools')),
            array(__('Base pay', 'vms-data-tools'), vms_dt_rr_money((int) max(0, ((int) ($costs['band_payout_cents'] ?? 0) - (int) ($costs['bonus_cents'] ?? 0)))), esc_html((string) ($costs['structure_label'] ?? ''))),
            array(__('Bonus', 'vms-data-tools'), vms_dt_rr_money((int) ($costs['bonus_cents'] ?? 0)), !empty($costs['bonus_hit']) ? __('earned', 'vms-data-tools') : __('not earned', 'vms-data-tools')),
            array(__('Total payout estimate', 'vms-data-tools'), vms_dt_rr_money((int) ($costs['band_payout_cents'] ?? 0)), __('based on current mapped ticket count / sales', 'vms-data-tools')),
        );
        foreach ($cards as $card) {
            echo '<div class="vms-dt-card"><p class="vms-dt-kpi-label">' . esc_html($card[0]) . '</p><p class="vms-dt-kpi-value">' . esc_html($card[1]) . '</p><p class="vms-dt-kpi-note">' . esc_html($card[2]) . '</p></div>';
        }
        echo '</div>';
        echo '<div class="vms-dt-card vms-dt-section"><div class="vms-dt-card-head"><div><h2>' . esc_html__('Band-facing summary', 'vms-data-tools') . '</h2><p class="vms-dt-section-desc">' . esc_html__('This is the screenshot-friendly block you can read or share.', 'vms-data-tools') . '</p></div></div>';
        echo '<ul class="vms-dt-summary-list">';
        echo '<li><span class="vms-dt-summary-key">' . esc_html__('Performer', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html($band_name !== '' ? $band_name : __('No band linked', 'vms-data-tools')) . '</span></li>';
        echo '<li><span class="vms-dt-summary-key">' . esc_html__('Event', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html((string) ($row['event_title'] ?? '')) . '</span></li>';
        echo '<li><span class="vms-dt-summary-key">' . esc_html__('Tickets counted', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html(number_format((int) ($costs['ticket_qty_total'] ?? 0))) . '</span></li>';
        echo '<li><span class="vms-dt-summary-key">' . esc_html__('Ticket sales', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html(vms_dt_rr_money((int) ($costs['ticket_sales_total_cents'] ?? 0))) . '</span></li>';
        echo '<li><span class="vms-dt-summary-key">' . esc_html__('Comp structure', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html((string) ($costs['structure_label'] ?? '')) . '</span></li>';
        echo '<li><span class="vms-dt-summary-key">' . esc_html__('Bonus result', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html(!empty($costs['bonus_hit']) ? __('Bonus earned', 'vms-data-tools') : __('No bonus yet', 'vms-data-tools')) . '</span></li>';
        echo '<li><span class="vms-dt-summary-key">' . esc_html__('Estimated payout', 'vms-data-tools') . '</span><span class="vms-dt-summary-value">' . esc_html(vms_dt_rr_money((int) ($costs['band_payout_cents'] ?? 0))) . '</span></li>';
        echo '</ul>';
        if (!empty($costs['bonus_note'])) {
            echo '<p class="description">' . esc_html((string) $costs['bonus_note']) . '</p>';
        }
        echo '</div></div>';
    }
}

if (!function_exists('vms_dt_reporting_profitability_margin_percent')) {
    function vms_dt_reporting_profitability_margin_percent(): float
    {
        return 65.0;
    }
}

if (!function_exists('vms_dt_reporting_build_profitability_filters')) {
    function vms_dt_reporting_build_profitability_filters(?array $source = null): array
    {
        $source = vms_dt_reporting_effective_source(vms_dt_get_menu_slug_reporting_profitability(), $source);
        $filters = function_exists('vms_dt_rr_get_filters') ? vms_dt_rr_get_filters($source) : array();
        $filters['event_plan_id'] = 0;
        $filters['event_from'] = '';
        $filters['event_to'] = '';
        $filters['profit_window'] = isset($source['profit_window']) ? sanitize_key((string) wp_unslash($source['profit_window'])) : 'all';
        if (!in_array($filters['profit_window'], array('all', 'live', 'future', 'past'), true)) {
            $filters['profit_window'] = 'all';
        }
        $filters['report_search'] = isset($source['report_search']) ? sanitize_text_field((string) wp_unslash($source['report_search'])) : '';
        $filters['profit_sort'] = isset($source['profit_sort']) ? sanitize_key((string) wp_unslash($source['profit_sort'])) : 'smart';
        if (!in_array($filters['profit_sort'], array('smart', 'date_desc', 'date_asc', 'score_desc', 'score_asc'), true)) {
            $filters['profit_sort'] = 'smart';
        }
        return $filters;
    }
}

if (!function_exists('vms_dt_reporting_profitability_time_bucket')) {
    function vms_dt_reporting_profitability_time_bucket(string $event_date): string
    {
        $event_date = trim($event_date);
        $today = wp_date('Y-m-d', time(), wp_timezone());
        if ($event_date === '') {
            return 'unknown';
        }
        if ($event_date === $today) {
            return 'live';
        }
        return strcmp($event_date, $today) > 0 ? 'future' : 'past';
    }
}


if (!function_exists('vms_dt_reporting_profitability_is_cancelled_status')) {
    function vms_dt_reporting_profitability_is_cancelled_status(string $status): bool
    {
        $status = sanitize_key($status);
        if ($status === 'canceled') {
            $status = 'cancelled';
        }
        return $status === 'cancelled';
    }
}

if (!function_exists('vms_dt_reporting_profitability_group_meta')) {
    function vms_dt_reporting_profitability_group_meta(string $time_bucket): array
    {
        switch ($time_bucket) {
            case 'live':
                return array(
                    'label' => __('Live', 'vms-data-tools'),
                    'desc'  => __('Events happening today.', 'vms-data-tools'),
                    'open'  => true,
                );
            case 'future':
                return array(
                    'label' => __('Future', 'vms-data-tools'),
                    'desc'  => __('Upcoming events and projected nights.', 'vms-data-tools'),
                    'open'  => true,
                );
            case 'past':
                return array(
                    'label' => __('Past', 'vms-data-tools'),
                    'desc'  => __('Completed or historical events.', 'vms-data-tools'),
                    'open'  => false,
                );
            default:
                return array(
                    'label' => __('Undated', 'vms-data-tools'),
                    'desc'  => __('Events without a usable date.', 'vms-data-tools'),
                    'open'  => false,
                );
        }
    }
}

if (!function_exists('vms_dt_reporting_profitability_progress_meta')) {
    function vms_dt_reporting_profitability_progress_meta(int $revenue_cents, int $known_cost_total_cents): array
    {
        $revenue_cents = max(0, $revenue_cents);
        $known_cost_total_cents = max(0, $known_cost_total_cents);

        if ($known_cost_total_cents <= 0) {
            return array(
                'key' => 'covered',
                'label' => __('Covered', 'vms-data-tools'),
                'class' => 'vms-dt-status-badge--good',
                'percent' => $revenue_cents > 0 ? 100 : 0,
                'bar_percent' => $revenue_cents > 0 ? 100 : 0,
                'target_label' => __('No cost target set yet.', 'vms-data-tools'),
            );
        }

        $ratio = $revenue_cents / $known_cost_total_cents;
        $percent = (int) round($ratio * 100);
        $bar_percent = max(0, min(100, $percent));

        if ($percent >= 100) {
            return array(
                'key' => 'covered',
                'label' => __('Covered', 'vms-data-tools'),
                'class' => 'vms-dt-status-badge--good',
                'percent' => $percent,
                'bar_percent' => $bar_percent,
                'target_label' => __('Revenue + estimated bar profit has covered known costs.', 'vms-data-tools'),
            );
        }
        if ($percent >= 75) {
            return array(
                'key' => 'close',
                'label' => __('Close', 'vms-data-tools'),
                'class' => 'vms-dt-status-badge--watch',
                'percent' => $percent,
                'bar_percent' => $bar_percent,
                'target_label' => __('Almost at break-even on known costs.', 'vms-data-tools'),
            );
        }

        return array(
            'key' => 'building',
            'label' => __('Building', 'vms-data-tools'),
            'class' => 'vms-dt-badge--neutral',
            'percent' => $percent,
            'bar_percent' => $bar_percent,
            'target_label' => __('Still building toward break-even on known costs.', 'vms-data-tools'),
        );
    }
}

if (!function_exists('vms_dt_reporting_profitability_stage_badge')) {
    function vms_dt_reporting_profitability_stage_badge(string $time_bucket): array
    {
        switch ($time_bucket) {
            case 'live':
                return array(
                    'label' => __('Live', 'vms-data-tools'),
                    'class' => 'vms-dt-badge--info',
                );
            case 'future':
                return array(
                    'label' => __('Projected', 'vms-data-tools'),
                    'class' => 'vms-dt-badge--neutral',
                );
            case 'past':
                return array(
                    'label' => __('Final', 'vms-data-tools'),
                    'class' => 'vms-dt-badge--neutral',
                );
            default:
                return array(
                    'label' => __('Undated', 'vms-data-tools'),
                    'class' => 'vms-dt-badge--neutral',
                );
        }
    }
}

if (!function_exists('vms_dt_reporting_profitability_ad_spend_meta_keys')) {
    function vms_dt_reporting_profitability_ad_spend_meta_keys(): array
    {
        $keys = array(
            '_vms_meta_ads_spend_cents',
            'vms_meta_ads_spend_cents',
            '_meta_ads_spend_cents',
            'meta_ads_spend_cents',
            '_vms_ad_spend_cents',
            'vms_ad_spend_cents',
            'ad_spend_cents',
            '_vms_meta_ads_spend',
            'vms_meta_ads_spend',
            '_meta_ads_spend',
            'meta_ads_spend',
            '_vms_ad_spend',
            'vms_ad_spend',
            'ad_spend',
            'vms_ma_last_budget_cents',
        );

        $keys = apply_filters('vms_dt_profitability_ad_spend_meta_keys', $keys);
        return array_values(array_unique(array_filter(array_map('strval', (array) $keys))));
    }
}

if (!function_exists('vms_dt_reporting_profitability_cost_value_to_cents')) {
    function vms_dt_reporting_profitability_cost_value_to_cents($value, string $source_key = ''): int
    {
        if (is_array($value)) {
            $candidate_keys = array('cents', 'amount_cents', 'spend_cents', 'ad_spend_cents', 'value_cents', 'amount', 'spend', 'ad_spend', 'value');
            foreach ($candidate_keys as $candidate_key) {
                if (array_key_exists($candidate_key, $value)) {
                    return vms_dt_reporting_profitability_cost_value_to_cents($value[$candidate_key], $candidate_key);
                }
            }
            return 0;
        }

        if (is_object($value)) {
            return vms_dt_reporting_profitability_cost_value_to_cents((array) $value, $source_key);
        }

        if (is_int($value)) {
            return stripos($source_key, 'cents') !== false ? max(0, $value) : max(0, $value * 100);
        }

        if (is_float($value)) {
            return stripos($source_key, 'cents') !== false ? max(0, (int) round($value)) : max(0, (int) round($value * 100));
        }

        if (!is_string($value)) {
            return 0;
        }

        $raw = trim($value);
        if ($raw === '') {
            return 0;
        }

        $normalized = preg_replace('/[^0-9,\.\-]/', '', $raw);
        if ($normalized === null || $normalized === '') {
            return 0;
        }

        if (strpos($normalized, ',') !== false && strpos($normalized, '.') !== false) {
            if (strrpos($normalized, ',') > strrpos($normalized, '.')) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif (strpos($normalized, ',') !== false) {
            $parts = explode(',', $normalized);
            $last = end($parts);
            if ($last !== false && strlen((string) $last) === 2) {
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        }

        if (!is_numeric($normalized)) {
            return 0;
        }

        $number = (float) $normalized;
        if ($number <= 0) {
            return 0;
        }

        return stripos($source_key, 'cents') !== false
            ? max(0, (int) round($number))
            : max(0, (int) round($number * 100));
    }
}

if (!function_exists('vms_dt_reporting_profitability_ad_spend_data')) {
    function vms_dt_reporting_profitability_ad_spend_data(int $event_plan_id, array $row = array()): array
    {
        $result = array(
            'cents' => 0,
            'source' => '',
            'label' => '',
        );

        if ($event_plan_id <= 0) {
            return $result;
        }

        $filtered = apply_filters('vms_dt_profitability_ad_spend_cents', null, $event_plan_id, $row);
        if ($filtered !== null) {
            $cents = vms_dt_reporting_profitability_cost_value_to_cents($filtered, 'cents');
            if ($cents > 0) {
                $result['cents'] = $cents;
                $result['source'] = (is_array($filtered) && !empty($filtered['source'])) ? (string) $filtered['source'] : 'filter';
                $result['label'] = (is_array($filtered) && !empty($filtered['label'])) ? (string) $filtered['label'] : __('Advertising', 'vms-data-tools');
                return $result;
            }
        }

        $callable_candidates = array(
            'vms_meta_ads_builder_get_event_ad_spend_cents',
            'vms_meta_ads_builder_get_event_spend_cents',
            'vms_meta_ads_get_event_ad_spend_cents',
            'vms_ma_ads_get_event_ad_spend_cents',
            'vms_ma_get_event_ad_spend_cents',
        );
        foreach ($callable_candidates as $callback) {
            if (!function_exists($callback)) {
                continue;
            }
            try {
                $value = $callback($event_plan_id, $row);
            } catch (\Throwable $e) {
                continue;
            }
            $cents = vms_dt_reporting_profitability_cost_value_to_cents($value, 'cents');
            if ($cents > 0) {
                $result['cents'] = $cents;
                $result['source'] = (is_array($value) && !empty($value['source'])) ? (string) $value['source'] : $callback;
                $result['label'] = (is_array($value) && !empty($value['label'])) ? (string) $value['label'] : __('Advertising', 'vms-data-tools');
                return $result;
            }
        }

        $post_ids = array($event_plan_id);
        foreach (array('event_id', 'tec_event_id', 'event_post_id', 'tec_event_post_id') as $row_key) {
            $candidate = (int) ($row[$row_key] ?? 0);
            if ($candidate > 0 && !in_array($candidate, $post_ids, true)) {
                $post_ids[] = $candidate;
            }
        }

        foreach ($post_ids as $post_id) {
            foreach (vms_dt_reporting_profitability_ad_spend_meta_keys() as $meta_key) {
                $value = get_post_meta($post_id, $meta_key, true);
                $cents = vms_dt_reporting_profitability_cost_value_to_cents($value, $meta_key);
                if ($cents > 0) {
                    $result['cents'] = $cents;
                    $result['source'] = $meta_key;
                    $result['label'] = ('vms_ma_last_budget_cents' === $meta_key)
                        ? __('Meta Ads budget', 'vms-data-tools')
                        : __('Advertising', 'vms-data-tools');
                    return $result;
                }
            }
        }

        return $result;
    }
}

if (!function_exists('vms_dt_reporting_build_profitability_rows')) {
    function vms_dt_reporting_build_profitability_rows(array $filters): array
    {
        $dataset_filters = $filters;
        $dataset_filters['event_plan_id'] = 0;
        $dataset_filters['event_from'] = '';
        $dataset_filters['event_to'] = '';

        $dataset = vms_dt_rr_build_report_dataset($dataset_filters);
        $rows = array();
        $search = strtolower(trim((string) ($filters['report_search'] ?? '')));
        $selected_window = (string) ($filters['profit_window'] ?? 'all');
        $margin_multiplier = vms_dt_reporting_profitability_margin_percent() / 100;

        foreach ((array) ($dataset['event_rows'] ?? array()) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $time_bucket = vms_dt_reporting_profitability_time_bucket((string) ($row['event_date'] ?? ''));
            if ($selected_window !== 'all' && $time_bucket !== $selected_window) {
                continue;
            }

            $title = (string) ($row['event_title'] ?? '');
            $venue_name = (string) ($row['venue_name'] ?? '');
            $status = (string) ($row['status'] ?? '');
            if ($search !== '') {
                $haystack = strtolower(trim($title . ' ' . $venue_name . ' ' . $status . ' ' . (string) ($row['event_date'] ?? '')));
                if ($haystack === '' || strpos($haystack, $search) === false) {
                    continue;
                }
            }
            $costs = vms_dt_reporting_calculate_event_costs($row);
            $concession_sales_cents = max(0, (int) ($row['square_bar_cents'] ?? 0) + (int) ($row['square_food_cents'] ?? 0));
            $estimated_bar_profit_cents = (int) round($concession_sales_cents * $margin_multiplier);
            $vendor_cost_cents = (int) ($costs['band_payout_cents'] ?? 0);
            $labor_overhead_cents = (int) ($costs['labor_overhead_cents'] ?? 0);
            $other_direct_costs_cents = (int) ($costs['other_direct_costs_cents'] ?? 0);
            $ad_spend_data = vms_dt_reporting_profitability_ad_spend_data((int) ($row['event_plan_id'] ?? 0), $row);
            $advertising_cost_cents = (int) ($ad_spend_data['cents'] ?? 0);
            $known_cost_total_cents = max(0, $vendor_cost_cents + $labor_overhead_cents + $other_direct_costs_cents + $advertising_cost_cents);
            $core_profit_cents = (int) ($costs['ticket_sales_total_cents'] ?? 0) - $known_cost_total_cents;
            $night_score_cents = $core_profit_cents + $estimated_bar_profit_cents;
            $revenue_plus_bar_cents = max(0, (int) ($costs['ticket_sales_total_cents'] ?? 0) + $estimated_bar_profit_cents);
            $progress_meta = vms_dt_reporting_profitability_progress_meta($revenue_plus_bar_cents, $known_cost_total_cents);
            $is_cancelled = vms_dt_reporting_profitability_is_cancelled_status($status);
            $cancelled_estimated_costs_cents = $known_cost_total_cents;
            $cancelled_cost_review_needed = ($is_cancelled && $cancelled_estimated_costs_cents > 0);
            $cancelled_review_message = '';
            if ($cancelled_cost_review_needed) {
                $cancelled_review_message = __('Cancelled event still carries estimated costs. Review the event plan and zero anything that was not actually owed.', 'vms-data-tools');
            }
            if ($is_cancelled) {
                $score_badge = array(
                    'label' => $cancelled_cost_review_needed ? __('Needs cost review', 'vms-data-tools') : __('Cancelled', 'vms-data-tools'),
                    'class' => $cancelled_cost_review_needed ? 'vms-dt-status-badge--watch' : 'vms-dt-status-badge--bad',
                );
            } else {
                $score_badge = array(
                    'label' => (string) ($progress_meta['label'] ?? __('Building', 'vms-data-tools')),
                    'class' => (string) ($progress_meta['class'] ?? 'vms-dt-badge--neutral'),
                );
            }
            $stage_badge = vms_dt_reporting_profitability_stage_badge($time_bucket);
            $issues = array_values(array_unique(array_filter(array_merge(
                (array) ($row['confidence_badges'] ?? array()),
                (array) ($row['square_errors'] ?? array()),
                (array) ($row['square_warnings'] ?? array())
            ))));
            if ($cancelled_cost_review_needed) {
                $issues[] = __('Cancelled plan still includes estimated costs.', 'vms-data-tools');
            }
            $issues = array_values(array_unique(array_filter(array_map('strval', $issues))));

            $rows[] = array(
                'event_plan_id' => (int) ($row['event_plan_id'] ?? 0),
                'event_title' => $title,
                'event_date' => (string) ($row['event_date'] ?? ''),
                'venue_name' => $venue_name,
                'status' => $status,
                'time_bucket' => $time_bucket,
                'is_cancelled' => $is_cancelled,
                'cancelled_cost_review_needed' => $cancelled_cost_review_needed,
                'cancelled_review_message' => $cancelled_review_message,
                'cancelled_estimated_costs_cents' => $cancelled_estimated_costs_cents,
                'stage_badge' => $stage_badge,
                'score_badge' => $score_badge,
                'ticket_qty_total' => (int) ($costs['ticket_qty_total'] ?? 0),
                'paid_ticket_qty_total' => (int) ($costs['paid_ticket_qty_total'] ?? 0),
                'comp_ticket_qty_total' => (int) ($costs['free_ticket_qty_excluded'] ?? 0),
                'ticket_sales_total_cents' => (int) ($costs['ticket_sales_total_cents'] ?? 0),
                'concession_sales_cents' => $concession_sales_cents,
                'estimated_bar_profit_cents' => $estimated_bar_profit_cents,
                'vendor_cost_cents' => $vendor_cost_cents,
                'labor_overhead_cents' => $labor_overhead_cents,
                'other_direct_costs_cents' => $other_direct_costs_cents,
                'advertising_cost_cents' => $advertising_cost_cents,
                'ad_spend_source' => (string) ($ad_spend_data['source'] ?? ''),
                'ad_spend_label' => (string) ($ad_spend_data['label'] ?? ''),
                'known_cost_total_cents' => $known_cost_total_cents,
                'revenue_plus_bar_cents' => $revenue_plus_bar_cents,
                'progress_meta' => $progress_meta,
                'core_profit_cents' => $core_profit_cents,
                'night_score_cents' => $night_score_cents,
                'issues' => $issues,
                'square_unclassified_cents' => (int) ($row['square_unclassified_cents'] ?? 0),
            );
        }

        $sort = (string) ($filters['profit_sort'] ?? 'smart');
        usort($rows, static function (array $a, array $b) use ($sort): int {
            switch ($sort) {
                case 'date_desc':
                    $cmp = strcmp((string) ($b['event_date'] ?? ''), (string) ($a['event_date'] ?? ''));
                    return $cmp !== 0 ? $cmp : strcmp((string) ($a['event_title'] ?? ''), (string) ($b['event_title'] ?? ''));
                case 'date_asc':
                    $cmp = strcmp((string) ($a['event_date'] ?? ''), (string) ($b['event_date'] ?? ''));
                    return $cmp !== 0 ? $cmp : strcmp((string) ($a['event_title'] ?? ''), (string) ($b['event_title'] ?? ''));
                case 'score_desc':
                    $cmp = ((int) ($b['night_score_cents'] ?? 0)) <=> ((int) ($a['night_score_cents'] ?? 0));
                    return $cmp !== 0 ? $cmp : strcmp((string) ($b['event_date'] ?? ''), (string) ($a['event_date'] ?? ''));
                case 'score_asc':
                    $cmp = ((int) ($a['night_score_cents'] ?? 0)) <=> ((int) ($b['night_score_cents'] ?? 0));
                    return $cmp !== 0 ? $cmp : strcmp((string) ($b['event_date'] ?? ''), (string) ($a['event_date'] ?? ''));
                case 'smart':
                default:
                    $a_bucket = (string) ($a['time_bucket'] ?? 'unknown');
                    $b_bucket = (string) ($b['time_bucket'] ?? 'unknown');
                    $bucket_rank = array('live' => 0, 'future' => 1, 'past' => 2, 'unknown' => 3);
                    $rank_cmp = ($bucket_rank[$a_bucket] ?? 9) <=> ($bucket_rank[$b_bucket] ?? 9);
                    if ($rank_cmp !== 0) {
                        return $rank_cmp;
                    }
                    if ($a_bucket === 'future') {
                        $cmp = strcmp((string) ($a['event_date'] ?? ''), (string) ($b['event_date'] ?? ''));
                        return $cmp !== 0 ? $cmp : strcmp((string) ($a['event_title'] ?? ''), (string) ($b['event_title'] ?? ''));
                    }
                    $cmp = strcmp((string) ($b['event_date'] ?? ''), (string) ($a['event_date'] ?? ''));
                    return $cmp !== 0 ? $cmp : strcmp((string) ($a['event_title'] ?? ''), (string) ($b['event_title'] ?? ''));
            }
        });

        
        $summary = array(
            'events_count' => count($rows),
            'ticket_sales_total_cents' => 0,
            'concession_sales_cents' => 0,
            'estimated_bar_profit_cents' => 0,
            'vendor_cost_cents' => 0,
            'labor_overhead_cents' => 0,
            'other_direct_costs_cents' => 0,
            'advertising_cost_cents' => 0,
            'core_profit_cents' => 0,
            'night_score_cents' => 0,
            'paid_ticket_qty_total' => 0,
            'comp_ticket_qty_total' => 0,
            'covered_count' => 0,
            'close_count' => 0,
            'building_count' => 0,
            'cancelled_count' => 0,
            'cancelled_review_count' => 0,
        );

        foreach ($rows as $entry) {
            $summary['ticket_sales_total_cents'] += (int) ($entry['ticket_sales_total_cents'] ?? 0);
            $summary['concession_sales_cents'] += (int) ($entry['concession_sales_cents'] ?? 0);
            $summary['estimated_bar_profit_cents'] += (int) ($entry['estimated_bar_profit_cents'] ?? 0);
            $summary['vendor_cost_cents'] += (int) ($entry['vendor_cost_cents'] ?? 0);
            $summary['labor_overhead_cents'] += (int) ($entry['labor_overhead_cents'] ?? 0);
            $summary['other_direct_costs_cents'] += (int) ($entry['other_direct_costs_cents'] ?? 0);
            $summary['advertising_cost_cents'] += (int) ($entry['advertising_cost_cents'] ?? 0);
            $summary['core_profit_cents'] += (int) ($entry['core_profit_cents'] ?? 0);
            $summary['night_score_cents'] += (int) ($entry['night_score_cents'] ?? 0);
            $summary['paid_ticket_qty_total'] += (int) ($entry['paid_ticket_qty_total'] ?? 0);
            $summary['comp_ticket_qty_total'] += (int) ($entry['comp_ticket_qty_total'] ?? 0);
            if (!empty($entry['is_cancelled'])) {
                $summary['cancelled_count']++;
                if (!empty($entry['cancelled_cost_review_needed'])) {
                    $summary['cancelled_review_count']++;
                }
                continue;
            }
            $progress_key = (string) (($entry['progress_meta']['key'] ?? 'building'));
            if ($progress_key === 'covered') {
                $summary['covered_count']++;
            } elseif ($progress_key === 'close') {
                $summary['close_count']++;
            } else {
                $summary['building_count']++;
            }
        }

        return array(
            'filters' => $filters,
            'dataset' => $dataset,
            'rows' => $rows,
            'summary' => $summary,
        );
    }
}

if (!function_exists('vms_dt_reporting_render_profitability_filters')) {
    function vms_dt_reporting_render_profitability_filters(string $page_slug, array $filters): void
    {
        $venues = function_exists('vms_dt_rr_get_venue_options') ? vms_dt_rr_get_venue_options() : array();
        $square_locations = function_exists('vms_dt_rr_get_square_location_options') ? vms_dt_rr_get_square_location_options() : array();
        ?>
        <form method="get" action="" class="vms-dt-card vms-dt-section">
            <input type="hidden" name="page" value="<?php echo esc_attr($page_slug); ?>" />
            <div class="vms-dt-card-head">
                <div>
                    <h2><?php esc_html_e('Quick filters', 'vms-data-tools'); ?></h2>
                    <p class="vms-dt-section-desc"><?php esc_html_e('This is the fast break-even progress board. QuickBooks remains the accounting source of truth.', 'vms-data-tools'); ?></p>
                </div>
            </div>
            <div class="vms-dt-filter-grid">
                <div class="vms-dt-field">
                    <label for="vms-dt-profit-window"><?php esc_html_e('Show', 'vms-data-tools'); ?></label>
                    <select id="vms-dt-profit-window" name="profit_window">
                        <option value="all" <?php selected((string) ($filters['profit_window'] ?? 'all'), 'all'); ?>><?php esc_html_e('All events', 'vms-data-tools'); ?></option>
                        <option value="live" <?php selected((string) ($filters['profit_window'] ?? 'all'), 'live'); ?>><?php esc_html_e('Live / today', 'vms-data-tools'); ?></option>
                        <option value="future" <?php selected((string) ($filters['profit_window'] ?? 'all'), 'future'); ?>><?php esc_html_e('Future only', 'vms-data-tools'); ?></option>
                        <option value="past" <?php selected((string) ($filters['profit_window'] ?? 'all'), 'past'); ?>><?php esc_html_e('Past only', 'vms-data-tools'); ?></option>
                    </select>
                </div>
                <div class="vms-dt-field">
                    <label for="vms-dt-profit-venue"><?php esc_html_e('Venue', 'vms-data-tools'); ?></label>
                    <select id="vms-dt-profit-venue" name="venue_id">
                        <option value="0"><?php esc_html_e('All venues', 'vms-data-tools'); ?></option>
                        <?php foreach ($venues as $venue) : ?>
                            <option value="<?php echo esc_attr((string) ($venue['id'] ?? 0)); ?>" <?php selected((int) ($filters['venue_id'] ?? 0), (int) ($venue['id'] ?? 0)); ?>><?php echo esc_html((string) ($venue['label'] ?? '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="vms-dt-field">
                    <label for="vms-dt-profit-search"><?php esc_html_e('Search', 'vms-data-tools'); ?></label>
                    <input id="vms-dt-profit-search" type="text" name="report_search" value="<?php echo esc_attr((string) ($filters['report_search'] ?? '')); ?>" placeholder="<?php echo esc_attr__('Event, venue, status…', 'vms-data-tools'); ?>" />
                </div>
                <div class="vms-dt-field">
                    <label for="vms-dt-profit-sort"><?php esc_html_e('Sort', 'vms-data-tools'); ?></label>
                    <select id="vms-dt-profit-sort" name="profit_sort">
                        <option value="smart" <?php selected((string) ($filters['profit_sort'] ?? 'smart'), 'smart'); ?>><?php esc_html_e('Smart', 'vms-data-tools'); ?></option>
                        <option value="date_desc" <?php selected((string) ($filters['profit_sort'] ?? 'smart'), 'date_desc'); ?>><?php esc_html_e('Newest first', 'vms-data-tools'); ?></option>
                        <option value="date_asc" <?php selected((string) ($filters['profit_sort'] ?? 'smart'), 'date_asc'); ?>><?php esc_html_e('Oldest first', 'vms-data-tools'); ?></option>
                        <option value="score_desc" <?php selected((string) ($filters['profit_sort'] ?? 'smart'), 'score_desc'); ?>><?php esc_html_e('Best night first', 'vms-data-tools'); ?></option>
                        <option value="score_asc" <?php selected((string) ($filters['profit_sort'] ?? 'smart'), 'score_asc'); ?>><?php esc_html_e('Worst night first', 'vms-data-tools'); ?></option>
                    </select>
                </div>
                <div class="vms-dt-field">
                    <label for="vms-dt-profit-square-location"><?php esc_html_e('Square location', 'vms-data-tools'); ?></label>
                    <select id="vms-dt-profit-square-location" name="square_location_id">
                        <option value=""><?php echo count($square_locations) <= 1 ? esc_html__('Use configured location', 'vms-data-tools') : esc_html__('Select a Square location', 'vms-data-tools'); ?></option>
                        <?php foreach ($square_locations as $location_id => $label) : ?>
                            <option value="<?php echo esc_attr((string) $location_id); ?>" <?php selected((string) ($filters['square_location_id'] ?? ''), (string) $location_id); ?>><?php echo esc_html((string) $label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="vms-dt-field">
                    <label for="vms-dt-profit-square-scope"><?php esc_html_e('Square scope', 'vms-data-tools'); ?></label>
                    <select id="vms-dt-profit-square-scope" name="square_scope_mode">
                        <?php foreach (vms_dt_rr_square_scope_options() as $scope_key => $scope_label) : ?>
                            <option value="<?php echo esc_attr((string) $scope_key); ?>" <?php selected((string) ($filters['square_scope_mode'] ?? 'full_day'), (string) $scope_key); ?>><?php echo esc_html((string) $scope_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="vms-dt-toolbar"><div class="vms-dt-toolbar-left"><button type="submit" class="button button-primary"><?php esc_html_e('Refresh list', 'vms-data-tools'); ?></button></div></div>
        </form>
        <?php
    }
}


if (!function_exists('vms_dt_reporting_render_profitability_card')) {
    function vms_dt_reporting_render_profitability_card(array $entry, array $filters): void
    {
        $stage_badge = (array) ($entry['stage_badge'] ?? array());
        $score_badge = (array) ($entry['score_badge'] ?? array());
        $event_title = (string) ($entry['event_title'] ?? '');
        $event_date = (string) ($entry['event_date'] ?? '');
        $venue_name = (string) ($entry['venue_name'] ?? '');
        $status = trim((string) ($entry['status'] ?? ''));
        $single_event_url = vms_dt_admin_url(vms_dt_get_menu_slug_reporting_single_event(), array('event_plan_id' => (int) ($entry['event_plan_id'] ?? 0), 'square_location_id' => (string) ($filters['square_location_id'] ?? ''), 'square_scope_mode' => (string) ($filters['square_scope_mode'] ?? 'full_day')));
        $event_plan_url = get_edit_post_link((int) ($entry['event_plan_id'] ?? 0), '');

        echo '<article class="vms-dt-profit-card">';
        echo '<div class="vms-dt-profit-card__head">';
        echo '<div><h3 class="vms-dt-profit-card__title">' . esc_html($event_title) . '</h3>';
        $sub = trim($event_date . ($venue_name !== '' ? ' · ' . $venue_name : ''));
        if ($sub !== '') {
            echo '<p class="vms-dt-event-sub">' . esc_html($sub) . '</p>';
        }
        echo '</div>';
        echo '<div class="vms-dt-badge-row">';
        if (!empty($stage_badge['label'])) {
            echo '<span class="vms-dt-badge ' . esc_attr((string) ($stage_badge['class'] ?? 'vms-dt-badge--neutral')) . '">' . esc_html((string) $stage_badge['label']) . '</span>';
        }
        if ($status !== '') {
            echo '<span class="vms-dt-badge vms-dt-badge--neutral">' . esc_html($status) . '</span>';
        }
        if (!empty($score_badge['label'])) {
            echo '<span class="vms-dt-status-badge ' . esc_attr((string) ($score_badge['class'] ?? 'vms-dt-badge--neutral')) . '">' . esc_html((string) $score_badge['label']) . '</span>';
        }
        echo '</div>';
        echo '</div>';

        if (!empty($entry['cancelled_cost_review_needed'])) {
            echo '<div class="vms-dt-callout vms-dt-callout--warn vms-dt-profit-card__alert"><strong>' . esc_html__('Cancelled event needs cost review', 'vms-data-tools') . '</strong><p>' . esc_html((string) ($entry['cancelled_review_message'] ?? '')) . '</p></div>';
        }

        echo '<div class="vms-dt-profit-metrics">';
        $metric_items = array(
            array(
                'label' => __('Paid tickets', 'vms-data-tools'),
                'short_label' => __('Paid', 'vms-data-tools'),
                'value' => number_format((int) ($entry['paid_ticket_qty_total'] ?? 0)),
            ),
            array(
                'label' => __('Comp / free', 'vms-data-tools'),
                'short_label' => __('Comp', 'vms-data-tools'),
                'value' => number_format((int) ($entry['comp_ticket_qty_total'] ?? 0)),
            ),
            array(
                'label' => __('Total admitted', 'vms-data-tools'),
                'short_label' => __('Admitted', 'vms-data-tools'),
                'value' => number_format((int) ($entry['ticket_qty_total'] ?? 0)),
            ),
            array(
                'label' => __('Ticket sales', 'vms-data-tools'),
                'short_label' => __('Tickets', 'vms-data-tools'),
                'value' => vms_dt_rr_money((int) ($entry['ticket_sales_total_cents'] ?? 0)),
            ),
            array(
                'label' => __('Concession sales', 'vms-data-tools'),
                'short_label' => __('Concessions', 'vms-data-tools'),
                'value' => vms_dt_rr_money((int) ($entry['concession_sales_cents'] ?? 0)),
            ),
            array(
                'label' => __('Vendor cost', 'vms-data-tools'),
                'short_label' => __('Vendor', 'vms-data-tools'),
                'value' => vms_dt_rr_money((int) ($entry['vendor_cost_cents'] ?? 0)),
            ),
            array(
                'label' => __('Labor OH', 'vms-data-tools'),
                'short_label' => __('Labor', 'vms-data-tools'),
                'value' => vms_dt_rr_money((int) ($entry['labor_overhead_cents'] ?? 0)),
            ),
            array(
                'label' => (string) ($entry['ad_spend_label'] ?: __('Advertising', 'vms-data-tools')),
                'short_label' => __('Meta budget', 'vms-data-tools'),
                'value' => vms_dt_rr_money((int) ($entry['advertising_cost_cents'] ?? 0)),
            ),
            array(
                'label' => __('Core profit', 'vms-data-tools'),
                'short_label' => __('Core profit', 'vms-data-tools'),
                'value' => vms_dt_rr_money((int) ($entry['core_profit_cents'] ?? 0)),
            ),
            array(
                'label' => __('Est. bar profit', 'vms-data-tools'),
                'short_label' => __('Bar profit', 'vms-data-tools'),
                'value' => vms_dt_rr_money((int) ($entry['estimated_bar_profit_cents'] ?? 0)),
            ),
            array(
                'label' => __('Night score', 'vms-data-tools'),
                'short_label' => __('Night score', 'vms-data-tools'),
                'value' => vms_dt_rr_money((int) ($entry['night_score_cents'] ?? 0)),
            ),
        );
        foreach ($metric_items as $metric) {
            echo '<div class="vms-dt-profit-metric">';
            echo '<span class="vms-dt-summary-key"><span class="vms-dt-mobile-label-full">' . esc_html((string) $metric['label']) . '</span><span class="vms-dt-mobile-label-short">' . esc_html((string) ($metric['short_label'] ?? $metric['label'])) . '</span></span>';
            echo '<span class="vms-dt-summary-value">' . esc_html((string) $metric['value']) . '</span>';
            echo '</div>';
        }
        echo '</div>';

        if (empty($entry['is_cancelled'])) {
            $progress_meta = is_array($entry['progress_meta'] ?? null) ? (array) $entry['progress_meta'] : array();
            $progress_class = (string) ($progress_meta['class'] ?? 'vms-dt-badge--neutral');
            $progress_percent = max(0, (int) ($progress_meta['percent'] ?? 0));
            $progress_bar_percent = max(0, min(100, (int) ($progress_meta['bar_percent'] ?? 0)));
            echo '<div class="vms-dt-profit-progress">';
            echo '<div class="vms-dt-profit-progress__head"><span class="vms-dt-summary-key">' . esc_html__('Break-even progress', 'vms-data-tools') . '</span><span class="vms-dt-status-badge ' . esc_attr($progress_class) . '">' . esc_html(sprintf(__('%1$s%% · %2$s', 'vms-data-tools'), number_format($progress_percent), (string) ($progress_meta['label'] ?? __('Building', 'vms-data-tools')))) . '</span></div>';
            echo '<div class="vms-dt-progress"><span class="vms-dt-progress__fill ' . esc_attr($progress_class) . '" style="width:' . esc_attr((string) $progress_bar_percent) . '%"></span></div>';
            echo '<p class="vms-dt-mini-note vms-dt-profit-progress__note">' . esc_html((string) ($progress_meta['target_label'] ?? __('Revenue + estimated bar profit compared with known costs.', 'vms-data-tools'))) . '</p>';
            echo '</div>';
        }

        echo '<div class="vms-dt-profit-card__footer">';
        if (!empty($entry['is_cancelled'])) {
            echo '<p class="vms-dt-mini-note">' . esc_html__('Cancelled rows stay visible for visibility, but you should review any estimated labor, vendor, advertising, or direct costs before trusting the score.', 'vms-data-tools') . '</p>';
        } else {
            echo '<p class="vms-dt-mini-note vms-dt-profit-card__formula">' . esc_html__('Night score = core profit + estimated bar profit at 65% margin. Break-even progress compares ticket sales plus estimated bar profit against known costs, including Meta Ads budget when set.', 'vms-data-tools') . '</p>';
        }
        echo '<div class="vms-dt-toolbar-right">';
        if ($event_plan_url !== '') {
            echo '<a class="button button-secondary" href="' . esc_url($event_plan_url) . '">' . esc_html__('Open event plan', 'vms-data-tools') . '</a>';
        }
        echo '<a class="button" href="' . esc_url($single_event_url) . '">' . esc_html__('Open single-event detail', 'vms-data-tools') . '</a>';
        echo '</div>';
        echo '</div>';

        $issues = (array) ($entry['issues'] ?? array());
        $extra_chips = array();
        if ((int) ($entry['other_direct_costs_cents'] ?? 0) > 0) {
            $extra_chips[] = sprintf(__('Other direct costs %s', 'vms-data-tools'), vms_dt_rr_money((int) ($entry['other_direct_costs_cents'] ?? 0)));
        }
        if ((int) ($entry['advertising_cost_cents'] ?? 0) > 0) {
            $extra_chips[] = sprintf(__('%1$s %2$s', 'vms-data-tools'), (string) ($entry['ad_spend_label'] ?: __('Advertising', 'vms-data-tools')), vms_dt_rr_money((int) ($entry['advertising_cost_cents'] ?? 0)));
        }
        if ((int) ($entry['square_unclassified_cents'] ?? 0) > 0) {
            $extra_chips[] = sprintf(__('Unclassified Square %s', 'vms-data-tools'), vms_dt_rr_money((int) ($entry['square_unclassified_cents'] ?? 0)));
        }
        if (!empty($entry['cancelled_cost_review_needed'])) {
            $extra_chips[] = sprintf(__('Estimated costs still loaded %s', 'vms-data-tools'), vms_dt_rr_money((int) ($entry['cancelled_estimated_costs_cents'] ?? 0)));
        }
        if (!empty($issues) || !empty($extra_chips)) {
            echo '<div class="vms-dt-chip-list">';
            foreach ($extra_chips as $chip) {
                echo '<span class="vms-dt-badge vms-dt-badge--neutral">' . esc_html((string) $chip) . '</span>';
            }
            foreach (array_slice($issues, 0, 4) as $issue) {
                echo '<span class="vms-dt-badge vms-dt-badge--neutral">' . esc_html((string) $issue) . '</span>';
            }
            echo '</div>';
        }
        echo '</article>';
    }
}

if (!function_exists('vms_dt_render_reporting_profitability_page')) {
    function vms_dt_render_reporting_profitability_page(): void
    {
        if (!vms_dt_current_user_can_manage_tools()) {
            return;
        }

        $filters = vms_dt_reporting_build_profitability_filters();
        $model = vms_dt_reporting_build_profitability_rows($filters);
        $rows = (array) ($model['rows'] ?? array());
        $summary = (array) ($model['summary'] ?? array());
        $square_meta = (array) (($model['dataset']['square_meta'] ?? array()));
        $scope = function_exists('vms_dt_rr_resolve_square_location_scope') ? vms_dt_rr_resolve_square_location_scope($filters) : array();

        echo '<div class="wrap vms-dt-wrap">';
        echo '<h1>' . esc_html__('Event Profitability', 'vms-data-tools') . '</h1>';
        echo '<p class="vms-dt-lead">' . esc_html__('Fast event-by-event profitability read for your phone: paid tickets, comps, concessions, labor overhead, Meta Ads budget when set, and a quick break-even progress view. This page is intentionally directional, not accounting-grade.', 'vms-data-tools') . '</p>';
        vms_dt_reporting_nav(vms_dt_get_menu_slug_reporting_profitability());
        vms_dt_reporting_render_profitability_filters(vms_dt_get_menu_slug_reporting_profitability(), $filters);

        $messages = array_values(array_unique(array_filter(array_map('strval', array_merge(
            (array) ($scope['errors'] ?? array()),
            (array) ($scope['warnings'] ?? array()),
            (array) ($square_meta['errors'] ?? array()),
            (array) ($square_meta['warnings'] ?? array())
        )))));
        if (!empty($messages)) {
            echo '<div class="vms-dt-callout vms-dt-callout--warn"><strong>' . esc_html__('Heads up', 'vms-data-tools') . '</strong><ul class="vms-dt-note-list">';
            foreach ($messages as $message) {
                echo '<li>' . esc_html($message) . '</li>';
            }
            echo '</ul></div>';
        }
        if ((int) ($summary['cancelled_review_count'] ?? 0) > 0) {
            echo '<div class="vms-dt-callout vms-dt-callout--warn"><strong>' . esc_html__('Cancelled events need review', 'vms-data-tools') . '</strong><p>' . esc_html(sprintf(_n('%d cancelled event still carries estimated costs. Open the event plan and zero anything not actually owed.', '%d cancelled events still carry estimated costs. Open each event plan and zero anything not actually owed.', (int) ($summary['cancelled_review_count'] ?? 0), 'vms-data-tools'), (int) ($summary['cancelled_review_count'] ?? 0))) . '</p></div>';
        }

        echo '<div class="vms-dt-grid vms-dt-grid--cards vms-dt-section">';
        $cards = array(
            array(__('Events shown', 'vms-data-tools'), number_format((int) ($summary['events_count'] ?? 0)), sprintf(__('Covered %1$d · Close %2$d · Building %3$d · Cancelled %4$d', 'vms-data-tools'), (int) ($summary['covered_count'] ?? 0), (int) ($summary['close_count'] ?? 0), (int) ($summary['building_count'] ?? 0), (int) ($summary['cancelled_count'] ?? 0))),
            array(__('Paid tickets', 'vms-data-tools'), number_format((int) ($summary['paid_ticket_qty_total'] ?? 0)), sprintf(__('Comp / free %s', 'vms-data-tools'), number_format((int) ($summary['comp_ticket_qty_total'] ?? 0)))),
            array(__('Ticket sales', 'vms-data-tools'), vms_dt_rr_money((int) ($summary['ticket_sales_total_cents'] ?? 0)), __('Ticket revenue only', 'vms-data-tools')),
            array(__('Concession sales', 'vms-data-tools'), vms_dt_rr_money((int) ($summary['concession_sales_cents'] ?? 0)), __('Square bar + food buckets', 'vms-data-tools')),
            array(__('Labor OH', 'vms-data-tools'), vms_dt_rr_money((int) ($summary['labor_overhead_cents'] ?? 0)), __('Estimated from assigned staff', 'vms-data-tools')),
            array(__('Advertising', 'vms-data-tools'), vms_dt_rr_money((int) ($summary['advertising_cost_cents'] ?? 0)), __('Meta Ads Builder budget when set', 'vms-data-tools')),
            array(__('Core profit', 'vms-data-tools'), vms_dt_rr_money((int) ($summary['core_profit_cents'] ?? 0)), __('Tickets minus vendor, labor, advertising, and known direct costs', 'vms-data-tools')),
            array(__('Night score', 'vms-data-tools'), vms_dt_rr_money((int) ($summary['night_score_cents'] ?? 0)), sprintf(__('Includes estimated bar profit at %s%% margin', 'vms-data-tools'), rtrim(rtrim(number_format(vms_dt_reporting_profitability_margin_percent(), 2, '.', ''), '0'), '.'))),
        );
        foreach ($cards as $card) {
            echo '<div class="vms-dt-card"><p class="vms-dt-kpi-label">' . esc_html($card[0]) . '</p><p class="vms-dt-kpi-value">' . esc_html($card[1]) . '</p><p class="vms-dt-kpi-note">' . esc_html($card[2]) . '</p></div>';
        }
        echo '</div>';

        echo '<div class="vms-dt-card vms-dt-section"><div class="vms-dt-card-head"><div><h2>' . esc_html__('Event list', 'vms-data-tools') . '</h2><p class="vms-dt-section-desc">' . esc_html__('Core profit stays ticket-only. Night score adds a simple estimated bar profit layer, and break-even progress shows how close each night is to covering known costs, including Meta Ads Builder budget when set.', 'vms-data-tools') . '</p></div></div>';

        if (empty($rows)) {
            echo '<div class="vms-dt-empty">' . esc_html__('No events matched the current filters.', 'vms-data-tools') . '</div></div></div>';
            return;
        }

        $grouped_rows = array(
            'live' => array(),
            'future' => array(),
            'past' => array(),
            'unknown' => array(),
        );
        foreach ($rows as $entry) {
            $bucket = (string) ($entry['time_bucket'] ?? 'unknown');
            if (!isset($grouped_rows[$bucket])) {
                $bucket = 'unknown';
            }
            $grouped_rows[$bucket][] = $entry;
        }

        foreach ($grouped_rows as $bucket => $bucket_rows) {
            if (empty($bucket_rows)) {
                continue;
            }
            $meta = vms_dt_reporting_profitability_group_meta((string) $bucket);
            $group_counts = array(
                'covered' => 0,
                'close' => 0,
                'building' => 0,
                'cancelled' => 0,
            );
            foreach ($bucket_rows as $bucket_entry) {
                if (!empty($bucket_entry['is_cancelled'])) {
                    $group_counts['cancelled']++;
                    continue;
                }
                $progress_key = (string) (($bucket_entry['progress_meta']['key'] ?? 'building'));
                if ($progress_key === 'covered') {
                    $group_counts['covered']++;
                } elseif ($progress_key === 'close') {
                    $group_counts['close']++;
                } else {
                    $group_counts['building']++;
                }
            }
            $summary_bits = array(number_format(count($bucket_rows)) . ' ' . _n('event', 'events', count($bucket_rows), 'vms-data-tools'));
            if ($group_counts['covered'] > 0) {
                $summary_bits[] = sprintf(__('Covered %d', 'vms-data-tools'), $group_counts['covered']);
            }
            if ($group_counts['close'] > 0) {
                $summary_bits[] = sprintf(__('Close %d', 'vms-data-tools'), $group_counts['close']);
            }
            if ($group_counts['building'] > 0) {
                $summary_bits[] = sprintf(__('Building %d', 'vms-data-tools'), $group_counts['building']);
            }
            if ($group_counts['cancelled'] > 0) {
                $summary_bits[] = sprintf(__('Cancelled %d', 'vms-data-tools'), $group_counts['cancelled']);
            }

            echo '<details class="vms-dt-profit-group"' . (!empty($meta['open']) ? ' open' : '') . '>';
            echo '<summary class="vms-dt-profit-group__summary">';
            echo '<span class="vms-dt-profit-group__title-wrap"><span class="vms-dt-profit-group__title">' . esc_html((string) ($meta['label'] ?? ucfirst((string) $bucket))) . '</span><span class="vms-dt-profit-group__desc">' . esc_html((string) ($meta['desc'] ?? '')) . '</span></span>';
            echo '<span class="vms-dt-profit-group__counts">' . esc_html(implode(' · ', $summary_bits)) . '</span>';
            echo '</summary>';
            echo '<div class="vms-dt-profit-list">';
            foreach ($bucket_rows as $entry) {
                vms_dt_reporting_render_profitability_card((array) $entry, $filters);
            }
            echo '</div>';
            echo '</details>';
        }
        echo '</div></div></div>';
    }
}
