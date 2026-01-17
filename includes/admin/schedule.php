<?php

/**
 * VMS Admin Schedule
 *
 * - Monthly calendar view (similar spirit to Vendor Availability).
 * - Shows every month inside the schedule window (no missing months).
 * - Closed days still render (grayed) and can still show Event Plans.
 * - “Create” action validates the date is inside the configured window.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_vms_create_event_plan', 'vms_handle_create_event_plan');

/**
 * Normalize the schedule window bounds.
 *
 * Accepts multiple return shapes from vms_get_schedule_window_bounds():
 * - ['start_ymd' => 'YYYY-mm-dd', 'end_ymd' => 'YYYY-mm-dd']
 * - ['start' => 'YYYY-mm-dd', 'end' => 'YYYY-mm-dd']
 * - ['start_date' => 'YYYY-mm-dd', 'end_date' => 'YYYY-mm-dd']
 * - [0 => 'YYYY-mm-dd', 1 => 'YYYY-mm-dd']
 * - Same keys but values may be DateTimeInterface objects
 */
function vms_sch_get_window_bounds(int $venue_id): array
{
    $start = '';
    $end   = '';

    if (function_exists('vms_get_schedule_window_bounds')) {
        $raw = vms_get_schedule_window_bounds($venue_id);
        if (is_array($raw)) {
            $start = $raw['start_ymd'] ?? $raw['start'] ?? $raw['start_date'] ?? ($raw[0] ?? '');
            $end   = $raw['end_ymd']   ?? $raw['end']   ?? $raw['end_date']   ?? ($raw[1] ?? '');
        }

        if ($start instanceof DateTimeInterface) {
            $start = $start->format('Y-m-d');
        }
        if ($end instanceof DateTimeInterface) {
            $end = $end->format('Y-m-d');
        }
    }

    // Fallback window: first day of current month through end of month +24 months.
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $end)) {
        $start = current_time('Y-m-01');
        $end   = date('Y-m-t', strtotime('+24 months', strtotime($start)));
    }

    // Safety: ensure start <= end
    if (strtotime($start) > strtotime($end)) {
        $tmp = $start;
        $start = $end;
        $end = $tmp;
    }

    return array(
        'start_ymd' => $start,
        'end_ymd'   => $end,
    );
}

function vms_sch_is_valid_ymd(string $ymd): bool
{
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd);
}

function vms_sch_is_date_in_window(string $ymd, string $start_ymd, string $end_ymd): bool
{
    if (!vms_sch_is_valid_ymd($ymd) || !vms_sch_is_valid_ymd($start_ymd) || !vms_sch_is_valid_ymd($end_ymd)) {
        return false;
    }
    $t = strtotime($ymd);
    return $t >= strtotime($start_ymd) && $t <= strtotime($end_ymd);
}

function vms_sch_format_time_ampm(string $hhmm): string
{
    $hhmm = trim($hhmm);
    if ($hhmm === '') return '';

    $dt = DateTime::createFromFormat('H:i', $hhmm, wp_timezone());
    if (!$dt) return '';

    // Example: 7:00pm
    return strtolower($dt->format('g:ia'));
}

function vms_sch_plan_label(int $plan_id): string
{
    $band = trim((string) get_post_meta($plan_id, '_vms_band_name', true));
    $time = trim((string) get_post_meta($plan_id, '_vms_start_time', true));

    $label = '';
    if ($band !== '') {
        $label = $band;
        $fmt = vms_sch_format_time_ampm($time);
        if ($fmt !== '') {
            $label .= ' @ ' . $fmt;
        }
        return $label;
    }

    // Fallback: title with date prefix stripped if present.
    $title = (string) get_the_title($plan_id);
    $title = preg_replace('/[\x{2013}\x{2014}]/u', '-', (string) $s);

    $title = trim($title);
    return $title !== '' ? $title : 'Event Plan';
}


/**
 * Returns a map of open dates for the venue in the window.
 * open_map['YYYY-mm-dd'] = true
 */
function vms_sch_get_open_map(int $venue_id, string $start_ymd, string $end_ymd): array
{
    $open = array();

    if ($venue_id <= 0) return $open;

    if (function_exists('vms_get_active_dates_for_venue')) {
        // Some implementations accept a range; others ignore it.
        try {
            $dates = vms_get_active_dates_for_venue($venue_id, $start_ymd, $end_ymd);
        } catch (Throwable $e) {
            $dates = vms_get_active_dates_for_venue($venue_id);
        }

        if (is_array($dates)) {
            foreach ($dates as $d) {
                $d = (string) $d;
                if (vms_sch_is_valid_ymd($d)) {
                    $open[$d] = true;
                }
            }
        }
    }

    return $open;
}

/**
 * Returns a map of plans by date.
 * plans_by_date['YYYY-mm-dd'] = [plan_id, plan_id, …]
 */
function vms_sch_get_plans_by_date(int $venue_id, string $start_ymd, string $end_ymd): array
{
    $map = array();

    if ($venue_id <= 0) return $map;

    $args = array(
        'post_type'      => 'vms_event_plan',
        'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'     => '_vms_venue_id',
                'value'   => $venue_id,
                'compare' => '=',
            ),
            array(
                'key'     => '_vms_event_date',
                'value'   => array($start_ymd, $end_ymd),
                'compare' => 'BETWEEN',
                'type'    => 'DATE',
            ),
        ),
    );

    $plan_ids = get_posts($args);

    // Safety net: if BETWEEN yields nothing, do a wider query then filter.
    if (empty($plan_ids)) {
        $args['meta_query'] = array(
            'relation' => 'AND',
            array(
                'key'     => '_vms_venue_id',
                'value'   => $venue_id,
                'compare' => '=',
            ),
        );
        $plan_ids = get_posts($args);
    }

    foreach ($plan_ids as $pid) {
        $ymd = (string) get_post_meta($pid, '_vms_event_date', true);
        if (!vms_sch_is_valid_ymd($ymd)) continue;
        if (!vms_sch_is_date_in_window($ymd, $start_ymd, $end_ymd)) continue;

        if (!isset($map[$ymd])) $map[$ymd] = array();
        $map[$ymd][] = (int) $pid;
    }

    return $map;
}

function vms_handle_create_event_plan(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }

    $date = isset($_GET['date']) ? sanitize_text_field((string) $_GET['date']) : '';
    if (!vms_sch_is_valid_ymd($date)) {
        wp_die('Invalid date.');
    }

    $nonce = isset($_GET['_wpnonce']) ? (string) $_GET['_wpnonce'] : '';
    if (!wp_verify_nonce($nonce, 'vms_create_event_plan_' . $date)) {
        wp_die('Invalid nonce.');
    }

    $venue_id = function_exists('vms_get_current_venue_id') ? (int) vms_get_current_venue_id() : 0;
    if ($venue_id <= 0) {
        wp_die('Select a venue first.');
    }

    $bounds = vms_sch_get_window_bounds($venue_id);
    $start_ymd = (string) ($bounds['start_ymd'] ?? '');
    $end_ymd   = (string) ($bounds['end_ymd'] ?? '');

    if (!vms_sch_is_date_in_window($date, $start_ymd, $end_ymd)) {
        wp_die('That date is outside the configured schedule window.');
    }

    // Default behavior: if a plan already exists for this venue+date, we redirect to it.
    // If the user clicked an "Add" button, we allow creating an additional plan (multi-event days).
    $allow_add = isset($_GET['add']) && (string) $_GET['add'] === '1';

    $existing = get_posts(array(
        'post_type'      => 'vms_event_plan',
        'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'     => '_vms_event_date',
                'value'   => $date,
                'compare' => '=',
            ),
            array(
                'key'     => '_vms_venue_id',
                'value'   => $venue_id,
                'compare' => '=',
            ),
        ),
    ));

    if (!empty($existing) && !$allow_add) {
        $edit_link = get_edit_post_link((int) $existing[0], '');
        wp_safe_redirect($edit_link ?: admin_url('edit.php?post_type=vms_event_plan'));
        exit;
    }

    $venue_title = get_the_title($venue_id);
    $title = 'Event Plan — ' . $date;
    if ($venue_title) {
        $title = $venue_title . ' — ' . $date;
    }

    if (!empty($existing)) {
        $seq = count($existing) + 1;
        $title .= ' #' . $seq;
    }

    $plan_id = wp_insert_post(array(
        'post_type'   => 'vms_event_plan',
        'post_status' => 'draft',
        'post_title'  => $title,
    ));

    if (is_wp_error($plan_id) || !$plan_id) {
        wp_die('Failed to create Event Plan.');
    }

    update_post_meta((int) $plan_id, '_vms_event_date', $date);
    update_post_meta((int) $plan_id, '_vms_venue_id', $venue_id);

    $edit_link = get_edit_post_link((int) $plan_id, '');
    wp_safe_redirect($edit_link ?: admin_url('edit.php?post_type=vms_event_plan'));
    exit;
}

/**
 * Render: Schedule page
 */
function vms_render_schedule_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }

    $venue_id = function_exists('vms_get_current_venue_id') ? (int) vms_get_current_venue_id() : 0;
    $view     = isset($_GET['view']) ? sanitize_text_field((string) $_GET['view']) : 'calendar';
    if ($view !== 'list' && $view !== 'calendar') {
        $view = 'calendar';
    }

    echo '<div class="wrap">';
    echo '<h1>Schedule</h1>';

    if (function_exists('vms_render_current_venue_selector')) {
        vms_render_current_venue_selector();
    }

    echo '<p>High-level view of dates, booked vendors, and event plan status.</p>';

    if ($venue_id <= 0) {
        echo '<div class="notice notice-warning"><p>Select a venue to view its schedule.</p></div>';
        echo '</div>';
        return;
    }

    $bounds = vms_sch_get_window_bounds($venue_id);
    $start_ymd = (string) $bounds['start_ymd'];
    $end_ymd   = (string) $bounds['end_ymd'];

    $open_map  = vms_sch_get_open_map($venue_id, $start_ymd, $end_ymd);
    $plans_by_date = vms_sch_get_plans_by_date($venue_id, $start_ymd, $end_ymd);

    $base_url = remove_query_arg(array('view'));
    $cal_url  = add_query_arg('view', 'calendar', $base_url);
    $list_url = add_query_arg('view', 'list', $base_url);

    echo '<div class="vms-portal-nav" style="margin:12px 0">';
    echo '<a class="' . ($view === 'calendar' ? 'is-active' : '') . '" href="' . esc_url($cal_url) . '">Calendar</a>';
    echo '<a class="' . ($view === 'list' ? 'is-active' : '') . '" href="' . esc_url($list_url) . '">List</a>';
    echo '</div>';

    if ($view === 'list') {
        vms_render_schedule_list_view($venue_id, $start_ymd, $end_ymd, $open_map, $plans_by_date);
    } else {
        vms_render_schedule_calendar_view($venue_id, $start_ymd, $end_ymd, $open_map, $plans_by_date);
    }

    echo '</div>';
}

function vms_render_schedule_list_view(int $venue_id, string $start_ymd, string $end_ymd, array $open_map, array $plans_by_date): void
{
    $start_ts = strtotime($start_ymd);
    $end_ts   = strtotime($end_ymd);

    if (!$start_ts || !$end_ts) {
        echo '<div class="notice notice-error"><p>Schedule window bounds were invalid.</p></div>';
        return;
    }

    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th style="width:140px">Date</th>';
    echo '<th style="width:120px">Open/Closed</th>';
    echo '<th>Event Plans</th>';
    echo '<th style="width:140px">Actions</th>';
    echo '</tr></thead><tbody>';

    for ($ts = $start_ts; $ts <= $end_ts; $ts = strtotime('+1 day', $ts)) {
        $ymd = date('Y-m-d', $ts);
        $has_plans = !empty($plans_by_date[$ymd]);
        $is_open = $has_plans || isset($open_map[$ymd]);

        $badge = $is_open
            ? '<span class="vms-sch-badge is-open">Open</span>'
            : '<span class="vms-sch-badge is-closed">Closed</span>';

        $plans_html = '';
        if (!empty($plans_by_date[$ymd])) {
            $links = array();
            foreach ($plans_by_date[$ymd] as $pid) {
                $links[] = '<a href="' . esc_url(get_edit_post_link((int) $pid, '')) . '">' . esc_html(vms_sch_plan_label((int) $pid)) . '</a>';
            }
            $plans_html = implode('<br>', $links);
        } else {
            $plans_html = '<span class="vms-muted">No plan</span>';
        }

        $create_url = wp_nonce_url(
            admin_url('admin-post.php?action=vms_create_event_plan&date=' . $ymd),
            'vms_create_event_plan_' . $ymd
        );

        echo '<tr>';
        echo '<td>' . esc_html(date_i18n('D, M j, Y', $ts)) . '</td>';
        echo '<td>' . $badge . '</td>';
        echo '<td>' . $plans_html . '</td>';
        echo '<td>';
        $btn_label = $has_plans ? 'Add' : 'Create';
        $btn_url   = $create_url;
        if ($has_plans) {
            $btn_url = add_query_arg('add', '1', $btn_url);
        }
        echo '<a class="button" href="' . esc_url($btn_url) . '">' . esc_html($btn_label) . '</a>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

function vms_render_schedule_calendar_view(int $venue_id, string $start_ymd, string $end_ymd, array $open_map, array $plans_by_date): void
{
    $start_ts = strtotime($start_ymd);
    $end_ts   = strtotime($end_ymd);

    if (!$start_ts || !$end_ts) {
        echo '<div class="notice notice-error"><p>Schedule window bounds were invalid.</p></div>';
        return;
    }

    $tz = wp_timezone();
    $start_dt = new DateTime($start_ymd, $tz);
    $end_dt   = new DateTime($end_ymd, $tz);

    // Iterate month-by-month
    $cursor = new DateTime($start_dt->format('Y-m-01'), $tz);
    $end_month = new DateTime($end_dt->format('Y-m-01'), $tz);

    $dow = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');

    while ($cursor <= $end_month) {
        $month_start = new DateTime($cursor->format('Y-m-01'), $tz);
        $month_end   = new DateTime($cursor->format('Y-m-t'), $tz);

        $month_label = $month_start->format('F Y');

        echo '<details class="vms-panel vms-panel-month" open>'; // default open; user can collapse
        echo '<summary class="vms-panel-summary">' . esc_html($month_label) . '</summary>';
        echo '<div class="vms-panel-body vms-sch-month-body">';

        echo '<table class="vms-av-grid vms-sch-grid" style="width:100%">';
        echo '<thead><tr>';
        foreach ($dow as $d) {
            echo '<th>' . esc_html($d) . '</th>';
        }
        echo '</tr></thead><tbody>';

        $first_w = (int) $month_start->format('w'); // 0 Sunday
        $days_in_month = (int) $month_start->format('t');

        $cell = 0;
        echo '<tr>';

        // Leading blanks
        for ($i = 0; $i < $first_w; $i++) {
            echo '<td><div class="vms-sch-cell is-outside"></div></td>';
            $cell++;
        }

        for ($day = 1; $day <= $days_in_month; $day++) {
            $ymd = $month_start->format('Y-m-') . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
            $in_window = vms_sch_is_date_in_window($ymd, $start_ymd, $end_ymd);

            $classes = array('vms-sch-cell');
            if (!$in_window) {
                $classes[] = 'is-outside';
            }

            $has_plans = $in_window && !empty($plans_by_date[$ymd]);
            $is_open = ($in_window && isset($open_map[$ymd])) || $has_plans;
            if ($is_open) {
                $classes[] = 'is-open';
            }

            if ($in_window && !$is_open) {
                $classes[] = 'is-closed';
            }

            $badge = '';
            if ($in_window) {
                $badge = $is_open
                    ? '<span class="vms-sch-badge is-open">Open</span>'
                    : '<span class="vms-sch-badge is-closed">Closed</span>';
            }

            $plans_html = '';
            if ($in_window && !empty($plans_by_date[$ymd])) {
                $items = array();
                foreach ($plans_by_date[$ymd] as $pid) {
                    $items[] = '<div class="vms-sch-plan"><a href="' . esc_url(get_edit_post_link((int) $pid, '')) . '">' . esc_html(vms_sch_plan_label((int) $pid)) . '</a></div>';
                }
                $plans_html = implode('', $items);
            }

            $create_html = '';
            if ($in_window) {
                $btn_label = $has_plans ? 'Add' : 'Create';

                $create_url = wp_nonce_url(
                    admin_url('admin-post.php?action=vms_create_event_plan&date=' . $ymd),
                    'vms_create_event_plan_' . $ymd
                );

                // If there is already at least one plan on this date, pass a flag so the handler
                // knows you intend to add an additional plan (multi-event days).
                if ($has_plans) {
                    $create_url = add_query_arg('add', '1', $create_url);
                }

                $create_html = '<div style="margin-top:8px"><a class="button button-small" href="' . esc_url($create_url) . '">' . esc_html($btn_label) . '</a></div>';
            }

            echo '<td>';
            echo '<div class="' . esc_attr(implode(' ', $classes)) . '">';
            echo '<div class="vms-sch-daynum">' . esc_html((string) $day) . '</div>';
            echo $badge;
            echo $plans_html;
            echo $create_html;
            echo '</div>';
            echo '</td>';

            $cell++;

            if ($cell % 7 === 0 && $day !== $days_in_month) {
                echo '</tr><tr>';
            }
        }

        // Trailing blanks
        while ($cell % 7 !== 0) {
            echo '<td><div class="vms-sch-cell is-outside"></div></td>';
            $cell++;
        }

        echo '</tr>';
        echo '</tbody></table>';

        echo '</div>';
        echo '</details>';

        $cursor = new DateTime(date('Y-m-01', strtotime('+1 month', $cursor->getTimestamp())), $tz);
    }
}

