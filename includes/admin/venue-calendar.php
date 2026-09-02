<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * VMS — Admin Venue Calendar (Month View)
 * ==========================================================
 * Menu: VMS → Calendar
 */

// function vms_calendar_register_admin_menu(string $parent_slug, string $capability): void
// {
// 	add_submenu_page(
// 		$parent_slug,
// 		__('Venue Calendar', 'vms'),
// 		__('Calendar', 'vms'),
// 		$capability,
// 		'vms-venue-calendar',
// 		'vms_render_admin_venue_calendar_page'
// 	);
// }

function bvmgr_render_admin_venue_calendar_page(): void
{
    if (!current_user_can('manage_options')) return;

    $venues = get_posts([
        'post_type'      => 'vms_venue',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only venue-calendar filters only change which venue is displayed.
    $venue_id = bvmgr_request_read_absint($_GET, 'venue_id');
    if ($venue_id <= 0 && !empty($venues)) $venue_id = (int) $venues[0]->ID;

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only venue-calendar filters only change which month is displayed.
    $ym = bvmgr_request_read_text_field($_GET, 'ym');
    if ($ym === '') {
        $ym = gmdate('Y-m');
    }

    $data = bvmgr_get_event_plans_for_venue_month($venue_id, $ym);
    $month = $data['month'];
    $days  = $data['days'];

    $nav = bvmgr_calendar_prev_next($ym);

    echo '<div class="wrap vms-venue-calendar">';
    echo '<h1>Venue Calendar</h1>';

    // Filter row
    echo '<form method="get" class="vms-cal-filters">';
    echo '<input type="hidden" name="page" value="vms-venue-calendar" />';

    echo '<label class="vms-cal-filter-label">Venue</label>';
    echo '<select name="venue_id" class="vms-cal-filter-venue">';
    foreach ($venues as $v) {
        printf(
            '<option value="%d"%s>%s</option>',
            (int) $v->ID,
            selected($venue_id, $v->ID, false),
            esc_html($v->post_title)
        );
    }
    echo '</select>';

    echo '<label class="vms-cal-filter-label vms-cal-filter-label-month">Month</label>';
    echo '<input type="month" name="ym" class="vms-cal-filter-month" value="' . esc_attr($month['ym']) . '" /> ';

    echo '<button class="button button-primary">Go</button>';
    echo '</form>';

    // Month nav
    $base = admin_url('admin.php?page=vms-venue-calendar&venue_id=' . $venue_id);
    echo '<div class="vms-cal-nav">';
    echo '<a class="button" href="' . esc_url($base . '&ym=' . $nav['prev']) . '">← ' . esc_html($nav['prev']) . '</a>';
    echo '<div class="vms-cal-title">' . esc_html(date_i18n('F Y', strtotime($month['start']))) . '</div>';
    echo '<a class="button" href="' . esc_url($base . '&ym=' . $nav['next']) . '">' . esc_html($nav['next']) . ' →</a>';
    echo '</div>';

    // Calendar grid
    echo bvmgr_render_month_grid($month, $days, true);

    echo '</div>';
}

/**
 * Render the month grid.
 * $admin_mode: if true, cards link to edit screen.
 */
function bvmgr_render_month_grid(array $month, array $days, bool $admin_mode = false): string
{
    $start_ts = $month['start_ts'];
    $days_in_month = $month['days_in_month'];

    // 0=Sunday..6=Saturday, for the 1st day of the month
    $first_wday = (int) gmdate('w', $start_ts);

    $out = '';
    $out .= '<div class="vms-cal-grid">';
    $out .= '<div class="vms-cal-head">Sun</div><div class="vms-cal-head">Mon</div><div class="vms-cal-head">Tue</div><div class="vms-cal-head">Wed</div><div class="vms-cal-head">Thu</div><div class="vms-cal-head">Fri</div><div class="vms-cal-head">Sat</div>';

    // Blank cells before 1st
    for ($i = 0; $i < $first_wday; $i++) {
        $out .= '<div class="vms-cal-cell vms-cal-empty"></div>';
    }

    for ($day = 1; $day <= $days_in_month; $day++) {
        $out .= '<div class="vms-cal-cell">';
        $out .= '<div class="vms-cal-daynum">' . (int)$day . '</div>';

        if (!empty($days[$day])) {
            foreach ($days[$day] as $ev) {
                $img = $ev['img_url'] ? '<img class="vms-cal-avatar" src="' . esc_url($ev['img_url']) . '" alt="" />' : '<div class="vms-cal-avatar vms-cal-avatar-fallback"></div>';
                $name = esc_html($ev['band_name']);
                $time = $ev['start_time'] ? esc_html($ev['start_time']) : '';

                $badge = bvmgr_cal_status_badge((string)$ev['status']);

                $inner = $img . '<div class="vms-cal-card-text"><div class="vms-cal-name">' . $name . '</div><div class="vms-cal-meta">' . $time . ' ' . $badge . '</div></div>';
                $view_url = isset($ev['view_url']) ? esc_url((string) $ev['view_url']) : '';

                if ($admin_mode && !empty($ev['edit_url'])) {
                    $out .= '<a class="vms-cal-card" href="' . esc_url($ev['edit_url']) . '">' . $inner . '</a>';
                } elseif (!$admin_mode && $view_url !== '') {
                    $out .= '<a class="vms-cal-card" href="' . $view_url . '">' . $inner . '</a>';
                } else {
                    $out .= '<div class="vms-cal-card">' . $inner . '</div>';
                }
            }
        }

        $out .= '</div>';
    }

    $out .= '</div>';
    return $out;
}

function bvmgr_cal_status_badge(string $status): string
{
    $status = $status ?: 'draft';
    $label = strtoupper($status);

    $class = 'vms-badge vms-badge-grey';
    if ($status === 'ready') $class = 'vms-badge vms-badge-amber';
    if ($status === 'published') $class = 'vms-badge vms-badge-green';

    return '<span class="' . esc_attr($class) . '">' . esc_html($label) . '</span>';
}
