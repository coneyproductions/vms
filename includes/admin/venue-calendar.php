<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * VMS — Admin Venue Calendar (Month View)
 * ==========================================================
 * Menu: VMS → Calendar
 */

add_action('admin_menu', function () {
    add_submenu_page(
        'vms-season-board',              // parent slug (your VMS menu)
        __('Venue Calendar', 'vms'),
        __('Calendar', 'vms'),
        'manage_options',
        'vms-venue-calendar',
        'vms_render_admin_venue_calendar_page'
    );
});

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'vms-season-board_page_vms-venue-calendar') return;

    // Minimal CSS (inline is fine for now)
    wp_add_inline_style('wp-admin', vms_calendar_admin_css());
});

function vms_render_admin_venue_calendar_page(): void
{
    if (!current_user_can('manage_options')) return;

    $venues = get_posts([
        'post_type'      => 'vms_venue',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    $venue_id = isset($_GET['venue_id']) ? absint($_GET['venue_id']) : 0;
    if ($venue_id <= 0 && !empty($venues)) $venue_id = (int) $venues[0]->ID;

    $ym = isset($_GET['ym']) ? sanitize_text_field(wp_unslash($_GET['ym'])) : gmdate('Y-m');

    $data = vms_get_event_plans_for_venue_month($venue_id, $ym);
    $month = $data['month'];
    $days  = $data['days'];

    $nav = vms_calendar_prev_next($ym);

    echo '<div class="wrap">';
    echo '<h1>Venue Calendar</h1>';

    // Filter row
    echo '<form method="get" style="margin: 12px 0 14px;">';
    echo '<input type="hidden" name="page" value="vms-venue-calendar" />';

    echo '<label style="font-weight:600;margin-right:8px;">Venue</label>';
    echo '<select name="venue_id" style="min-width:320px;">';
    foreach ($venues as $v) {
        printf(
            '<option value="%d"%s>%s</option>',
            (int) $v->ID,
            selected($venue_id, $v->ID, false),
            esc_html($v->post_title)
        );
    }
    echo '</select>';

    echo '<label style="font-weight:600;margin:0 8px 0 16px;">Month</label>';
    echo '<input type="month" name="ym" value="' . esc_attr($month['ym']) . '" /> ';

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
    echo vms_render_month_grid($month, $days, true);

    echo '</div>';
}

/**
 * Render the month grid.
 * $admin_mode: if true, cards link to edit screen.
 */
function vms_render_month_grid(array $month, array $days, bool $admin_mode = false): string
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

                $badge = vms_cal_status_badge((string)$ev['status']);

                $inner = $img . '<div class="vms-cal-card-text"><div class="vms-cal-name">' . $name . '</div><div class="vms-cal-meta">' . $time . ' ' . $badge . '</div></div>';

                if ($admin_mode && !empty($ev['edit_url'])) {
                    $out .= '<a class="vms-cal-card" href="' . esc_url($ev['edit_url']) . '">' . $inner . '</a>';
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

function vms_cal_status_badge(string $status): string
{
    $status = $status ?: 'draft';
    $label = strtoupper($status);

    $class = 'vms-badge vms-badge-grey';
    if ($status === 'ready') $class = 'vms-badge vms-badge-amber';
    if ($status === 'published') $class = 'vms-badge vms-badge-green';

    return '<span class="' . esc_attr($class) . '">' . esc_html($label) . '</span>';
}

function vms_calendar_admin_css(): string
{
    return "
    .vms-cal-nav{display:flex;align-items:center;justify-content:space-between;max-width:1100px;margin:10px 0 12px;}
    .vms-cal-title{font-weight:800;font-size:16px;}
    .vms-cal-grid{max-width:1100px;display:grid;grid-template-columns:repeat(7,1fr);gap:10px;}
    .vms-cal-head{font-weight:700;color:#374151;padding:4px 2px;}
    .vms-cal-cell{min-height:130px;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:10px 10px 12px;position:relative;}
    .vms-cal-empty{background:transparent;border:1px dashed #e5e7eb;}
    .vms-cal-daynum{position:absolute;top:8px;right:10px;font-weight:800;color:#9ca3af;}
    .vms-cal-card{display:flex;gap:10px;align-items:center;text-decoration:none;border:1px solid #f1f5f9;background:#f8fafc;border-radius:12px;padding:8px 10px;margin-top:10px;}
    .vms-cal-card:hover{border-color:#cbd5e1;background:#fff;}
    .vms-cal-avatar{width:34px;height:34px;border-radius:10px;object-fit:cover;flex:0 0 auto;}
    .vms-cal-avatar-fallback{background:#e5e7eb;}
    .vms-cal-card-text{min-width:0;flex:1;}
    .vms-cal-name{font-weight:800;color:#111827;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .vms-cal-meta{margin-top:2px;font-size:12px;color:#6b7280;display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
    .vms-badge{font-size:10px;font-weight:800;letter-spacing:.04em;border-radius:999px;padding:2px 8px;border:1px solid transparent;}
    .vms-badge-grey{background:#f3f4f6;color:#374151;border-color:#e5e7eb;}
    .vms-badge-amber{background:#fffbeb;color:#92400e;border-color:#fed7aa;}
    .vms-badge-green{background:#ecfdf5;color:#065f46;border-color:#a7f3d0;}
    ";
}