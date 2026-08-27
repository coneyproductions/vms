<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/season-dates.php';

// Shared schedule functions used by REST + admin.
// Keep implementations identical to the originals from includes/admin/schedule.php.
/**
 * Returns a map of open dates for the venue in the window.
 * open_map['YYYY-mm-dd'] = true
 */
function vms_sch_get_open_map(int $venue_id, string $start_ymd, string $end_ymd): array
{
    $open = array();

    if ($venue_id <= 0) return $open;

    if (function_exists('bvmgr_get_active_dates_for_venue')) {
        // Some implementations accept a range; others ignore it.
        try {
            $dates = bvmgr_get_active_dates_for_venue($venue_id, $start_ymd, $end_ymd);
        } catch (Throwable $e) {
            $dates = bvmgr_get_active_dates_for_venue($venue_id);
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
function vms_sch_get_plans_by_date(int $venue_id, string $start_ymd, string $end_ymd, bool $include_drafts = false, array $opts = array())
{
    $map = array();

    if ($venue_id <= 0) {
        return $map;
    }

    $context = isset($opts['context']) ? sanitize_key((string) $opts['context']) : 'schedule_admin';

    // Inclusion flags are explicit: Published-only by default (Schedule includes cancelled by default).
    $flags = array(
        'include_drafts'    => (bool) $include_drafts,
        'include_cancelled' => array_key_exists('include_cancelled', $opts)
            ? (bool) $opts['include_cancelled']
            : (in_array($context, array('schedule_admin', 'event_list'), true)),
    );

    $args = array(
        'post_type'      => 'vms_event_plan',
        'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
        'posts_per_page' => -1,
        'fields'         => 'ids',
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The complete schedule lookup is bounded by one venue and the caller's explicit date window.
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'     => bvmgr_meta_key('event_plan', 'venue_id'),
                'value'   => (int) $venue_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ),
            array(
                'key'     => bvmgr_meta_key('event_plan', 'date'),
                'value'   => array($start_ymd, $end_ymd),
                'compare' => 'BETWEEN',
                'type'    => 'DATE',
            ),
        ),
    );

    $plan_ids = get_posts($args);

    // Safety net: if BETWEEN yields nothing, widen then filter manually.
    if (empty($plan_ids)) {
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The legacy fallback removes the date bound, scans all plans for this venue, and reapplies the caller's window in PHP below.
        $args['meta_query'] = array(
            array(
                'key'     => bvmgr_meta_key('event_plan', 'venue_id'),
                'value'   => (int) $venue_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ),
        );
        $plan_ids = get_posts($args);
    }

    foreach ($plan_ids as $pid) {
        $pid = absint($pid);
        if ($pid <= 0) {
            continue;
        }

        // Canonical inclusion (status meta, context-aware).
        if (function_exists('vms_event_plan_should_include')) {
            if (!vms_event_plan_should_include($pid, $context, $flags)) {
                continue;
            }
        }

        $ymd = (string) get_post_meta($pid, bvmgr_meta_key('event_plan', 'date'), true);
        if (!vms_sch_is_valid_ymd($ymd)) continue;
        if (!vms_sch_is_date_in_window($ymd, $start_ymd, $end_ymd)) continue;

        if (!isset($map[$ymd])) $map[$ymd] = array();

        $plan_status = function_exists('bvmgr_event_plan_get_status') ? (string) bvmgr_event_plan_get_status($pid, $context) : '';

        $map[$ymd][] = array(
            'plan_id'     => (int) $pid,
            'post_status' => (string) get_post_status($pid),
            'plan_status' => $plan_status,
        );
    }

    return $map;
}


/**
 * Returns a map of plans by date for ALL venues.
 * plans_by_date['YYYY-mm-dd'] = [
 *   ['plan_id' => 123, 'venue_id' => 55],
 *   ['plan_id' => 124, 'venue_id' => 56],
 * ]
 */
function vms_sch_get_plans_by_date_all(array $venue_ids, string $start_ymd, string $end_ymd, bool $include_drafts = false, array $opts = array())
{
    $map = array();
    $venue_ids = array_values(array_filter(array_map('intval', $venue_ids)));
    if (empty($venue_ids)) return $map;

    $context = isset($opts['context']) ? sanitize_key((string) $opts['context']) : 'schedule_admin';

    $flags = array(
        'include_drafts'    => (bool) $include_drafts,
        'include_cancelled' => array_key_exists('include_cancelled', $opts)
            ? (bool) $opts['include_cancelled']
            : (in_array($context, array('schedule_admin', 'event_list'), true)),
    );

    $args = array(
        'post_type'      => 'vms_event_plan',
        'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
        'posts_per_page' => -1,
        'fields'         => 'ids',
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The complete schedule lookup is bounded by the caller's finite venue set and explicit date window.
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'     => bvmgr_meta_key('event_plan', 'venue_id'),
                'value'   => $venue_ids,
                'compare' => 'IN',
                'type'    => 'NUMERIC',
            ),
            array(
                'key'     => bvmgr_meta_key('event_plan', 'date'),
                'value'   => array($start_ymd, $end_ymd),
                'compare' => 'BETWEEN',
                'type'    => 'DATE',
            ),
        ),
    );

    $plan_ids = get_posts($args);

    // Safety net: if BETWEEN yields nothing, widen then filter manually.
    if (empty($plan_ids)) {
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The legacy fallback removes the date bound, scans all plans for the selected venues, and reapplies the caller's window in PHP below.
        $args['meta_query'] = array(
            array(
                'key'     => bvmgr_meta_key('event_plan', 'venue_id'),
                'value'   => $venue_ids,
                'compare' => 'IN',
                'type'    => 'NUMERIC',
            ),
        );
        $plan_ids = get_posts($args);
    }

    foreach ($plan_ids as $pid) {
        $pid = (int) $pid;
        if ($pid <= 0) continue;

        if (function_exists('vms_event_plan_should_include')) {
            if (!vms_event_plan_should_include($pid, $context, $flags)) {
                continue;
            }
        }

        $ymd = (string) get_post_meta($pid, bvmgr_meta_key('event_plan', 'date'), true);
        if (!vms_sch_is_valid_ymd($ymd)) continue;
        if (!vms_sch_is_date_in_window($ymd, $start_ymd, $end_ymd)) continue;

        $vid = (int) get_post_meta($pid, bvmgr_meta_key('event_plan', 'venue_id'), true);

        if (!isset($map[$ymd])) $map[$ymd] = array();

        $plan_status = function_exists('bvmgr_event_plan_get_status') ? (string) bvmgr_event_plan_get_status($pid, $context) : '';

        $map[$ymd][] = array(
            'plan_id'     => $pid,
            'venue_id'    => $vid,
            'plan_status' => $plan_status,
        );
    }

    return $map;
}

/**
 * Build a lookup map: venue_id => venue_name
 */
function vms_sch_get_venue_name_map(array $venue_ids): array
{
    $map = array();
    foreach ($venue_ids as $vid) {
        $vid = (int) $vid;
        if ($vid <= 0) continue;
        $map[$vid] = (string) get_the_title($vid);
    }
    return $map;
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

/**
 * Render: Schedule page
 */
// function vms_render_schedule_page(): void
// {
//     if (!current_user_can('manage_options')) {
//         wp_die('Insufficient permissions.');
//     }

//     $user_id = get_current_user_id();

//     // If the URL explicitly specifies a venue, save it as the new "current venue"
//     if (isset($_GET['venue'])) {
//         $venue_raw = sanitize_text_field((string) $_GET['venue']);

//         if ($venue_raw === 'all') {
//             update_user_meta($user_id, '_vms_current_venue_id', 'all');
//         } else {
//             $venue_int = (int) $venue_raw;
//             if ($venue_int > 0) {
//                 update_user_meta($user_id, '_vms_current_venue_id', (string) $venue_int);
//             }
//         }
//     }

//     $venue_id_raw = function_exists('vms_get_current_venue_id')
//         ? vms_get_current_venue_id()
//         : 0;

//     $venue_id = ($venue_id_raw === 'all')
//         ? 'all'
//         : (int) $venue_id_raw;

//     $view     = isset($_GET['view']) ? sanitize_text_field((string) $_GET['view']) : 'calendar';
//     if ($view !== 'list' && $view !== 'calendar') {
//         $view = 'calendar';
//     }

//     $scope = isset($_GET['scope']) ? sanitize_key((string) $_GET['scope']) : 'venue';
//     if ($scope !== 'venue' && $scope !== 'all') {
//         $scope = 'venue';
//     }

//     echo '<div class="wrap">';
//     echo '<h1>Schedule</h1>';

//     if (function_exists('vms_render_current_venue_selector')) {
//         vms_render_current_venue_selector();
//     }

//     $base_url = remove_query_arg(array('view', 'scope'));
//     $scope_venue_url = add_query_arg(array('scope' => 'venue', 'view' => $view), $base_url);
//     $scope_all_url   = add_query_arg(array('scope' => 'all',   'view' => $view), $base_url);

//     echo '<div class="vms-portal-nav" style="margin:12px 0">';
//     echo '<a class="' . ($scope === 'venue' ? 'is-active' : '') . '" href="' . esc_url($scope_venue_url) . '">This venue</a>';
//     echo '<a class="' . ($scope === 'all' ? 'is-active' : '') . '" href="' . esc_url($scope_all_url) . '">All venues</a>';
//     echo '</div>';

//     echo '<p>High-level view of dates, booked vendors, and event plan status.</p>';

//     if ($scope === 'venue' && (int)$venue_id <= 0) {
//         echo '<div class="notice notice-warning"><p>Select a venue to view its schedule.</p></div>';
//         echo '</div>';
//         return;
//     }

//     if ($scope === 'all') {

//         $venue_ids = vms_sch_get_all_venue_ids();
//         if (empty($venue_ids)) {
//             echo '<div class="notice notice-warning"><p>No venues found to display.</p></div>';
//             echo '</div>';
//             return;
//         }

//         $bounds_venue_id = (int) $venue_ids[0];

//         $bounds = vms_sch_get_window_bounds($bounds_venue_id);
//         $start_ymd = (string) $bounds['start_ymd'];
//         $end_ymd   = (string) $bounds['end_ymd'];

//         $venue_name_map = vms_sch_get_venue_name_map($venue_ids);

//         // Open/closed state is venue-specific — skip it in All Venues
//         $open_map = array();

//         $plans_by_date = vms_sch_get_plans_by_date_all(
//             $venue_ids,
//             $start_ymd,
//             $end_ymd
//         );
//     } else {

//         // ✅ Original single-venue behavior (unchanged)
//         $bounds = vms_sch_get_window_bounds($venue_id);
//         $start_ymd = (string) $bounds['start_ymd'];
//         $end_ymd   = (string) $bounds['end_ymd'];

//         $open_map  = vms_sch_get_open_map($venue_id, $start_ymd, $end_ymd);
//         $plans_by_date = vms_sch_get_plans_by_date(
//             $venue_id,
//             $start_ymd,
//             $end_ymd
//         );

//         $venue_name_map = array(
//             (int) $venue_id => get_the_title($venue_id)
//         );
//     }

//     $base_url = remove_query_arg(array('view'));
//     $cal_url  = add_query_arg('view', 'calendar', $base_url);
//     $list_url = add_query_arg('view', 'list', $base_url);

//     echo '<div class="vms-portal-nav" style="margin:12px 0">';
//     echo '<a class="' . ($view === 'calendar' ? 'is-active' : '') . '" href="' . esc_url($cal_url) . '">Calendar</a>';
//     echo '<a class="' . ($view === 'list' ? 'is-active' : '') . '" href="' . esc_url($list_url) . '">List</a>';
//     echo '</div>';

//     if ($view === 'list') {
//         if ($scope === 'all') {
//             vms_render_schedule_list_view_all($start_ymd, $end_ymd, $plans_by_date, $venue_name_map);
//         } else {
//             vms_render_schedule_list_view($venue_id, $start_ymd, $end_ymd, $open_map, $plans_by_date);
//         }
//     } else {
//         if ($scope === 'all') {
//             vms_render_schedule_calendar_view_all($start_ymd, $end_ymd, $plans_by_date, $venue_name_map);
//         } else {
//             vms_render_schedule_calendar_view($venue_id, $start_ymd, $end_ymd, $open_map, $plans_by_date);
//         }
//     }

//     echo '</div>';
// }

// function vms_render_schedule_list_view(int $venue_id, string $start_ymd, string $end_ymd, array $open_map, array $plans_by_date): void
// {

//     $start_ts = strtotime($start_ymd);
//     $end_ts   = strtotime($end_ymd);

//     if (!$start_ts || !$end_ts) {
//         echo '<div class="notice notice-error"><p>Schedule window bounds were invalid.</p></div>';
//         return;
//     }

//     echo '<table class="widefat striped vms-sch-list">';
//     echo '<thead><tr>';
//     echo '<th style="width:140px">Date</th>';
//     // echo '<th style="width:120px">Open/Closed</th>'; // DISABLED TEMPORARILY FOR CLEANER LOOK
//     echo '<th>Event Plans</th>';
//     echo '<th style="width:140px">Actions</th>';
//     echo '</tr></thead><tbody>';

//     for ($ts = $start_ts; $ts <= $end_ts; $ts = strtotime('+1 day', $ts)) {
//         $ymd = date('Y-m-d', $ts);
//         $event_status = '';
//         $statuses = [];
//         $has_plans = !empty($plans_by_date[$ymd]);
//         $is_open = $has_plans || isset($open_map[$ymd]);

//         $badge = $is_open
//             ? '<span class="vms-sch-badge is-open">Open</span>'
//             : '<span class="vms-sch-badge is-closed">Closed</span>';

//         $plans_html = '';

//         if (!empty($plans_by_date[$ymd])) {
//             $links = array();

//             foreach ($plans_by_date[$ymd] as $row) {

//                 $plan_id = vms_get_plan_id_from_row($row);
//                 if ($plan_id <= 0) continue;

//                 $status = vms_map_plan_status($plan_id);
//                 if ($status !== '') {
//                     $statuses[] = $status;
//                 }

//                 $html = vms_get_plan_headliner_link_html($plan_id);
//                 if ($html !== '') {
//                     $links[] = $html;
//                 }
//             }

//             $is_open = $has_plans || isset($open_map[$ymd]);

//             if (in_array('cancelled', $statuses, true)) {
//                 $event_status = 'cancelled';
//             } elseif (in_array('draft', $statuses, true)) {
//                 $event_status = 'draft';
//             } elseif (in_array('confirmed', $statuses, true)) {
//                 $event_status = 'confirmed';
//             }

//             $plans_html = !empty($links)
//                 ? implode('<br>', $links)
//                 : '<span class="vms-muted">No plan</span>';
//         } else {
//             $plans_html = '<span class="vms-muted">No plan</span>';
//         }

//         $create_url = wp_nonce_url(
//             admin_url('admin-post.php?action=vms_create_event_plan&date=' . $ymd),
//             'vms_create_event_plan_' . $ymd
//         );

//         $status_class = '';

//         if ($event_status === 'draft') {
//             $status_class = 'vms-status-draft';
//         } elseif ($event_status === 'confirmed') {
//             $status_class = 'vms-status-confirmed';
//         } elseif ($event_status === 'cancelled') {
//             $status_class = 'vms-status-cancelled';
//         }

//         echo '<tr class="'
//             . ($is_open ? 'vms-venue-open' : 'vms-venue-closed')
//             . ' ' . $status_class
//             . '">';

//         echo '<td>' . esc_html(date_i18n('D, M j, Y', $ts)) . '</td>';
//         // echo '<td>' . $badge . '</td>'; // DISABLED TEMPORARILY FOR CLEANER LOOK
//         echo '<td>' . $plans_html . '</td>';
//         echo '<td>';
//         $btn_label = $has_plans ? 'Add' : 'Create';
//         $btn_url   = $create_url;
//         if ($has_plans) {
//             $btn_url = add_query_arg('add', '1', $btn_url);
//         }
//         echo '<a class="button" href="' . esc_url($btn_url) . '">' . esc_html($btn_label) . '</a>';
//         echo '</td>';
//         echo '</tr>';
//     }

//     echo '</tbody></table>';
// }

// function vms_render_schedule_calendar_view(int $venue_id, string $start_ymd, string $end_ymd, array $open_map, array $plans_by_date): void
// {
//     $start_ts = strtotime($start_ymd);
//     $end_ts   = strtotime($end_ymd);

//     if (!$start_ts || !$end_ts) {
//         echo '<div class="notice notice-error"><p>Schedule window bounds were invalid.</p></div>';
//         return;
//     }

//     $tz = wp_timezone();
//     $start_dt = new DateTime($start_ymd, $tz);
//     $end_dt   = new DateTime($end_ymd, $tz);

//     // Iterate month-by-month
//     $cursor = new DateTime($start_dt->format('Y-m-01'), $tz);
//     $end_month = new DateTime($end_dt->format('Y-m-01'), $tz);

//     $dow = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');

//     while ($cursor <= $end_month) {
//         $month_start = new DateTime($cursor->format('Y-m-01'), $tz);
//         $month_end   = new DateTime($cursor->format('Y-m-t'), $tz);

//         $month_label = $month_start->format('F Y');

//         echo '<details class="vms-panel vms-panel-month" open>'; // default open; user can collapse
//         echo '<summary class="vms-panel-summary">' . esc_html($month_label) . '</summary>';
//         echo '<div class="vms-panel-body vms-sch-month-body">';

//         echo '<table class="vms-av-grid vms-sch-grid" style="width:100%">';
//         echo '<thead><tr>';
//         foreach ($dow as $d) {
//             echo '<th>' . esc_html($d) . '</th>';
//         }
//         echo '</tr></thead><tbody>';

//         $first_w = (int) $month_start->format('w'); // 0 Sunday
//         $days_in_month = (int) $month_start->format('t');

//         $cell = 0;
//         echo '<tr>';

//         // Leading blanks
//         for ($i = 0; $i < $first_w; $i++) {
//             echo '<td><div class="vms-sch-cell is-outside"></div></td>';
//             $cell++;
//         }

//         for ($day = 1; $day <= $days_in_month; $day++) {
//             $ymd = $month_start->format('Y-m-') . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
//             $in_window = vms_sch_is_date_in_window($ymd, $start_ymd, $end_ymd);

//             $classes = array('vms-sch-cell');
//             if (!$in_window) {
//                 $classes[] = 'is-outside';
//             }

//             $has_plans = $in_window && !empty($plans_by_date[$ymd]);
//             $is_open = ($in_window && isset($open_map[$ymd])) || $has_plans;
//             if ($is_open) {
//                 $classes[] = 'is-open';
//             }

//             if ($in_window && !$is_open) {
//                 $classes[] = 'is-closed';
//             }

//             $today_ymd = (new DateTime('now', $tz))->format('Y-m-d');

//             if ($ymd === $today_ymd) {
//                 $classes[] = 'is-today';
//             } elseif ($ymd < $today_ymd) {
//                 $classes[] = 'is-past';
//             }

//             $badge = '';
//             if ($in_window) {
//                 $badge = $is_open
//                     ? '<span class="vms-sch-badge is-open">Open</span>'
//                     : '<span class="vms-sch-badge is-closed">Closed</span>';
//             }

//             $plans_html = '';
//             if ($in_window && !empty($plans_by_date[$ymd])) {
//                 $items = array();
//                 foreach ($plans_by_date[$ymd] as $pid) {
//                     $items[] = '<div class="vms-sch-plan"><a href="' . esc_url(get_edit_post_link((int) $pid, '')) . '">' . esc_html(vms_sch_plan_label((int) $pid)) . '</a></div>';
//                 }
//                 $plans_html = implode('', $items);
//             }

//             $create_html = '';
//             if ($in_window) {
//                 $btn_label = $has_plans ? 'Add' : 'Create';

//                 $create_url = wp_nonce_url(
//                     admin_url('admin-post.php?action=vms_create_event_plan&date=' . $ymd),
//                     'vms_create_event_plan_' . $ymd
//                 );

//                 // If there is already at least one plan on this date, pass a flag so the handler
//                 // knows you intend to add an additional plan (multi-event days).
//                 if ($has_plans) {
//                     $create_url = add_query_arg('add', '1', $create_url);
//                 }

//                 $create_html = '<div style="margin-top:8px"><a class="button button-small" href="' . esc_url($create_url) . '">' . esc_html($btn_label) . '</a></div>';
//             }

//             echo '<td>';
//             echo '<div class="' . esc_attr(implode(' ', $classes)) . '">';
//             echo '<div class="vms-sch-daynum">' . esc_html((string) $day) . '</div>';
//             echo $badge;
//             echo $plans_html;
//             echo $create_html;
//             echo '</div>';
//             echo '</td>';

//             $cell++;

//             if ($cell % 7 === 0 && $day !== $days_in_month) {
//                 echo '</tr><tr>';
//             }
//         }

//         // Trailing blanks
//         while ($cell % 7 !== 0) {
//             echo '<td><div class="vms-sch-cell is-outside"></div></td>';
//             $cell++;
//         }

//         echo '</tr>';
//         echo '</tbody></table>';

//         echo '</div>';
//         echo '</details>';

//         $cursor = new DateTime(date('Y-m-01', strtotime('+1 month', $cursor->getTimestamp())), $tz);
//     }
// }

// function vms_render_schedule_list_view_all(string $start_ymd, string $end_ymd, array $plans_by_date, array $venue_name_map): void
// {
//     $start_ts = strtotime($start_ymd);
//     $end_ts   = strtotime($end_ymd);

//     if (!$start_ts || !$end_ts) {
//         echo '<div class="notice notice-error"><p>Schedule window bounds were invalid.</p></div>';
//         return;
//     }

//     echo '<table class="widefat striped">';
//     echo '<thead><tr>';
//     echo '<th style="width:140px">Date</th>';
//     echo '<th>Event Plans</th>';
//     echo '</tr></thead><tbody>';

//     for ($ts = $start_ts; $ts <= $end_ts; $ts = strtotime('+1 day', $ts)) {
//         $ymd = date('Y-m-d', $ts);

//         $plans_html = '';

//         if (!empty($plans_by_date[$ymd])) {

//             $links = array();

//             foreach ($plans_by_date[$ymd] as $row) {

//                 $plan_id = vms_get_plan_id_from_row($row);
//                 if ($plan_id <= 0) continue;

//                 $html = vms_get_plan_headliner_link_html($plan_id);
//                 if ($html !== '') {
//                     $links[] = $html;
//                 }
//             }

//             $plans_html = !empty($links)
//                 ? implode('<br>', $links)
//                 : '<span class="vms-muted">No plan</span>';
//         } else {
//             $plans_html = '<span class="vms-muted">No plan</span>';
//         }


//         echo '<tr>';
//         echo '<td>' . esc_html(date_i18n('D, M j, Y', $ts)) . '</td>';
//         echo '<td>' . $plans_html . '</td>';
//         echo '</tr>';
//     }

//     echo '</tbody></table>';
// }

// function vms_render_schedule_calendar_view_all(string $start_ymd, string $end_ymd, array $plans_by_date, array $venue_name_map): void
// {
//     $start_ts = strtotime($start_ymd);
//     $end_ts   = strtotime($end_ymd);
//     $today_ymd = wp_date('Y-m-d'); // uses WP timezone settings

//     if (!$start_ts || !$end_ts) {
//         echo '<div class="notice notice-error"><p>Schedule window bounds were invalid.</p></div>';
//         return;
//     }

//     // Normalize to first day of start month
//     $cursor = strtotime(date('Y-m-01', $start_ts));
//     $end_month = strtotime(date('Y-m-01', $end_ts));

//     while ($cursor <= $end_month) {
//         $month_label = date_i18n('F Y', $cursor);
//         $month_start = strtotime(date('Y-m-01', $cursor));
//         $month_end   = strtotime(date('Y-m-t', $cursor));

//         echo '<details class="vms-av-method vms-sch-month" open>';
//         echo '<summary><span>' . esc_html($month_label) . '</span></summary>';
//         echo '<div class="vms-sch-month-body">';

//         echo '<table class="widefat vms-av-grid">';
//         echo '<thead><tr>';
//         foreach (array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat') as $dow) {
//             echo '<th style="text-align:center;">' . esc_html($dow) . '</th>';
//         }
//         echo '</tr></thead><tbody>';

//         $first_dow = (int) date('w', $month_start); // 0=Sun
//         $day_ts = $month_start;

//         echo '<tr>';
//         for ($i = 0; $i < $first_dow; $i++) {
//             echo '<td>&nbsp;</td>';
//         }

//         while ($day_ts <= $month_end) {
//             $w = (int) date('w', $day_ts);
//             $ymd = date('Y-m-d', $day_ts);

//             $cell_classes = 'vms-sch-cell';
//             if (!$in_window) {
//                 $cell_classes .= ' is-outside';
//             } else {
//                 if ($ymd === $today_ymd) {
//                     $cell_classes .= ' is-today';
//                 } elseif ($ymd < $today_ymd) {
//                     $cell_classes .= ' is-past';
//                 }
//             }

//             if ($w === 0 && $day_ts !== $month_start) {
//                 echo '</tr><tr>';
//             }

//             $in_window = ($day_ts >= $start_ts && $day_ts <= $end_ts);

//             echo '<td style="vertical-align:top;">';
//             echo '<div class="' . esc_attr($cell_classes) . '">';
//             echo '<div class="vms-sch-daynum">' . esc_html((string) ((int) substr($ymd, 8, 2))) . '</div>';

//             if ($in_window && !empty($plans_by_date[$ymd])) {

//                 // Group plan IDs by venue ID
//                 $by_venue = [];
//                 foreach ($plans_by_date[$ymd] as $row) {
//                     $pid = (int) ($row['plan_id'] ?? 0);
//                     $vid = (int) ($row['venue_id'] ?? 0);
//                     if ($pid <= 0 || $vid <= 0) continue;

//                     if (!isset($by_venue[$vid])) {
//                         $by_venue[$vid] = [];
//                     }
//                     $by_venue[$vid][] = $pid;
//                 }

//                 // Render: venue label once, then its plans underneath
//                 foreach ($by_venue as $vid => $pids) {
//                     $venue_label = $venue_name_map[$vid] ?? ('Venue #' . $vid);

//                     echo '<div class="vms-sch-planline">';
//                     echo '<span class="vms-sch-venue-tag">' . esc_html($venue_label) . '</span>';

//                     foreach ($pids as $pid) {
//                         echo '<div class="vms-sch-planitem">';
//                         echo '<a class="vms-sch-planlink" href="' . esc_url(get_edit_post_link($pid, '')) . '">'
//                             . esc_html(vms_sch_plan_label($pid))
//                             . '</a>';
//                         echo '</div>';
//                     }

//                     echo '</div>';
//                 }
//             } elseif ($in_window) {
//                 echo '<div class="vms-muted"> </div>';
//             }

//             echo '</div>';
//             echo '</td>';

//             $day_ts = strtotime('+1 day', $day_ts);
//         }

//         $last_dow = (int) date('w', $month_end);
//         for ($i = $last_dow; $i < 6; $i++) {
//             echo '<td>&nbsp;</td>';
//         }
//         echo '</tr>';

//         echo '</tbody></table>';
//         echo '</div></details>';

//         $cursor = strtotime('+1 month', $cursor);
//     }
// }