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

    // Convert exclusive end to inclusive end for the canonical feed.
    $month_end_inclusive = gmdate('Y-m-d', strtotime('-1 day', strtotime($m['end'])));
    $events = function_exists('vms_get_calendar_events')
        ? (array) vms_get_calendar_events([
            'start_date' => $m['start'],
            'end_date' => $month_end_inclusive,
            'venue_ids' => [$venue_id],
            'context' => 'admin',
            'include_past' => true,
            'include_statuses' => ['draft', 'ready', 'published', 'tentative', 'confirmed', 'cancelled', 'archived'],
        ])
        : [];

    // Group by day-of-month (1..31)
    $by_day = [];

    foreach ($events as $event) {
        $date = isset($event['date_key']) ? (string) $event['date_key'] : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            continue;
        }

        $ts = strtotime($date . ' 00:00:00');
        if (!$ts) {
            continue;
        }
        $day = (int) gmdate('j', $ts);

        $band_id = 0;
        $band_name = '';
        $groups = isset($event['vendor_groups']) && is_array($event['vendor_groups']) ? $event['vendor_groups'] : [];
        if (!empty($groups['talent']['vendors'][0]) && is_array($groups['talent']['vendors'][0])) {
            $band_id = (int) ($groups['talent']['vendors'][0]['vendor_id'] ?? 0);
            $band_name = (string) ($groups['talent']['vendors'][0]['display_name'] ?? '');
        }
        if ($band_name === '') {
            foreach ($groups as $group) {
                if (!is_array($group) || empty($group['vendors'][0]) || !is_array($group['vendors'][0])) {
                    continue;
                }
                $band_id = (int) ($group['vendors'][0]['vendor_id'] ?? 0);
                $band_name = (string) ($group['vendors'][0]['display_name'] ?? '');
                if ($band_name !== '') {
                    break;
                }
            }
        }
        if ($band_name === '') {
            $band_name = (string) ($event['title'] ?? '(Event)');
        }

        $img_url = isset($event['image_url']) ? (string) $event['image_url'] : '';

        $status = isset($event['plan_status']) ? sanitize_key((string) $event['plan_status']) : 'draft';
        if ($status === '') {
            $status = 'draft';
        }

        $start_time = '';
        $start_local = isset($event['start_local']) ? (string) $event['start_local'] : '';
        if ($start_local !== '') {
            try {
                $dt = new DateTimeImmutable($start_local);
                $start_time = $dt->format('H:i');
            } catch (Exception $e) {
                $start_time = '';
            }
        }

        $plan_id = (int) ($event['event_plan_id'] ?? 0);
        $by_day[$day][] = [
            'plan_id'    => $plan_id,
            'date'       => $date,
            'band_id'    => $band_id,
            'band_name'  => $band_name,
            'img_url'    => $img_url,
            'status'     => $status,
            'start_time' => $start_time,
            'edit_url'   => ($plan_id > 0 ? get_edit_post_link($plan_id, '') : ''),
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
