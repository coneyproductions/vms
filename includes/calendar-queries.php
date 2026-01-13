<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * VMS — Calendar Queries (Admin + Public)
 * ==========================================================
 * Central place for pulling "events for a venue in a month".
 *
 * Data source:
 * - Event Plans CPT: vms_event_plan
 * - Meta:
 *   - _vms_venue_id (int)
 *   - _vms_event_date (YYYY-MM-DD)
 *   - _vms_band_vendor_id (int)
 *
 * Output:
 * - Array of day => list of event cards
 */

function vms_parse_month_ym(string $ym): array
{
    // Accept "YYYY-MM" only. Fall back to current month.
    if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
        $ym = gmdate('Y-m');
    }

    $start = $ym . '-01';
    $start_ts = strtotime($start . ' 00:00:00');
    if (!$start_ts) {
        $ym = gmdate('Y-m');
        $start = $ym . '-01';
        $start_ts = strtotime($start . ' 00:00:00');
    }

    $end_ts = strtotime('+1 month', $start_ts);
    $end = gmdate('Y-m-d', $end_ts);

    return [
        'ym'        => $ym,
        'start'     => $start,
        'end'       => $end,     // exclusive end (first day next month)
        'start_ts'  => $start_ts,
        'end_ts'    => $end_ts,
        'days_in_month' => (int) gmdate('t', $start_ts),
    ];
}

function vms_get_event_plans_for_venue_month(int $venue_id, string $ym): array
{
    if ($venue_id <= 0) return [];

    $m = vms_parse_month_ym($ym);

    // Pull all plans for this venue where _vms_event_date is within [start, end)
    $plans = get_posts([
        'post_type'      => 'vms_event_plan',
        'post_status'    => ['publish', 'draft'],
        'posts_per_page' => -1,
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_key'       => '_vms_event_date',
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => '_vms_venue_id',
                'value'   => $venue_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
            [
                'key'     => '_vms_event_date',
                'value'   => $m['start'],
                'compare' => '>=',
                'type'    => 'CHAR',
            ],
            [
                'key'     => '_vms_event_date',
                'value'   => $m['end'],
                'compare' => '<',
                'type'    => 'CHAR',
            ],
        ],
    ]);

    // Group by day-of-month (1..31)
    $by_day = [];

    foreach ($plans as $p) {
        $date = (string) get_post_meta($p->ID, '_vms_event_date', true);
        if (!$date) continue;

        $ts = strtotime($date . ' 00:00:00');
        if (!$ts) continue;

        $day = (int) gmdate('j', $ts);

        $band_id   = (int) get_post_meta($p->ID, '_vms_band_vendor_id', true);
        $band_name = $band_id ? get_the_title($band_id) : '';
        if (!$band_name) $band_name = '(No band selected)';

        // Prefer band featured image, fallback to plan featured image
        $img_id = $band_id ? get_post_thumbnail_id($band_id) : 0;
        if (!$img_id) $img_id = get_post_thumbnail_id($p->ID);

        $img_url = $img_id ? wp_get_attachment_image_url($img_id, 'thumbnail') : '';

        $status = (string) get_post_meta($p->ID, '_vms_event_plan_status', true);
        if ($status === '') $status = 'draft';

        $start_time = (string) get_post_meta($p->ID, '_vms_start_time', true);

        $by_day[$day][] = [
            'plan_id'    => (int) $p->ID,
            'date'       => $date,
            'band_id'    => $band_id,
            'band_name'  => $band_name,
            'img_url'    => $img_url,
            'status'     => $status,
            'start_time' => $start_time,
            'edit_url'   => get_edit_post_link($p->ID, ''),
        ];
    }

    // Sort events within each day by time (if present)
    foreach ($by_day as $d => $list) {
        usort($list, function($a, $b) {
            return strcmp((string)($a['start_time'] ?? ''), (string)($b['start_time'] ?? ''));
        });
        $by_day[$d] = $list;
    }

    return [
        'month' => $m,
        'days'  => $by_day,
    ];
}

function vms_calendar_prev_next(string $ym): array
{
    $m = vms_parse_month_ym($ym);
    $prev = gmdate('Y-m', strtotime('-1 month', $m['start_ts']));
    $next = gmdate('Y-m', strtotime('+1 month', $m['start_ts']));
    return ['prev' => $prev, 'next' => $next];
}