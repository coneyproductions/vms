<?php
if (!defined('ABSPATH')) exit;

/**
 * Fetch + parse a vendor ICS feed and mark conflicting dates as unavailable.
 * this    
 * MVP rules:
 * - We mark dates as 'unavailable' when any event overlaps that date.
 * - We DO NOT set 'available' automatically.
 * - We DO NOT overwrite dates the vendor already set (manual wins).
 *
 * @param int   $vendor_id
 * @param array $active_dates array of date strings (should match your availability keys)
 * @return array ['ok'=>bool, 'marked'=>int, 'error'=>string]
 */
function vms_vendor_ics_sync_now(int $vendor_id, array $active_dates): array
{
    $ics_url = (string) get_post_meta($vendor_id, '_vms_ics_url', true);
    $ics_url = trim($ics_url);

    if ($ics_url === '') {
        return array('ok' => false, 'error' => __('No ICS URL saved for this vendor.', 'vms'));
    }

    $response = wp_remote_get($ics_url, array(
        'timeout' => 15,
        'redirection' => 3,
        'headers' => array('Accept' => 'text/calendar'),
    ));

    if (is_wp_error($response)) {
        return array('ok' => false, 'error' => $response->get_error_message());
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) {
        return array('ok' => false, 'error' => sprintf(__('ICS fetch failed (HTTP %d).', 'vms'), $code));
    }

    $raw = (string) wp_remote_retrieve_body($response);
    if ($raw === '') {
        return array('ok' => false, 'error' => __('ICS feed returned empty content.', 'vms'));
    }

    $events = vms_vendor_ics_extract_events($raw);
    if (!$events) {
        // Save an empty ICS layer (important so it can “clear” old unavailable marks)
        update_post_meta($vendor_id, '_vms_availability_ics', array());
        return array('ok' => true, 'ics_unavailable' => array());
    }

    // Busy dates (YYYY-MM-DD) in venue timezone
    $busy_dates = vms_vendor_ics_busy_dates($events); // returns ['YYYY-MM-DD' => true]

    // Only consider dates in active window
    $active_set = array_fill_keys($active_dates, true);

    $ics_unavailable = array();
    foreach ($busy_dates as $ymd => $_true) {
        if (isset($active_set[$ymd])) {
            $ics_unavailable[$ymd] = 'unavailable';
        }
    }

    // ✅ Write ICS layer ONLY (do not touch manual layer)
    update_post_meta($vendor_id, '_vms_availability_ics', $ics_unavailable);

    return array('ok' => true, 'ics_unavailable' => $ics_unavailable);
}
 

/**
 * Extract VEVENT date ranges from raw ICS, INCLUDING recurring rules.
 *
 * Returns array of ['start'=>timestamp,'end'=>timestamp].
 *
 * Supports:
 * - DTSTART/DTEND with optional TZID param
 * - RRULE weekly expansion (BYDAY/INTERVAL/UNTIL/COUNT)
 * - EXDATE exclusions (multiple, comma-separated)
 * - RECURRENCE-ID overrides (including STATUS:CANCELLED)
 *
 * IMPORTANT:
 * - We intentionally expand only within the active_dates range to keep it fast.
 */
function vms_vendor_ics_extract_events(string $raw, array $active_dates = array()): array
{
    // Unfold lines (RFC5545): lines starting with space/tab continue previous line
    $raw = preg_replace("/\r\n[ \t]/", '', $raw);

    preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $raw, $matches);
    if (empty($matches[1])) return array();

    $fallback_tz = vms_get_timezone(); // your helpers.php
    $window = vms_vendor_ics_build_window_from_active_dates($active_dates, $fallback_tz);

    // Parse blocks into structured VEVENT records
    $parsed = array();
    foreach ($matches[1] as $block) {
        $ve = vms_vendor_ics_parse_vevent_block($block, $fallback_tz);
        if (!$ve) continue;
        $parsed[] = $ve;
    }

    if (empty($parsed)) return array();

    // Group VEVENTs by UID
    $by_uid = array();
    foreach ($parsed as $ve) {
        $uid = (string)($ve['uid'] ?? '');
        if ($uid === '') continue;
        if (!isset($by_uid[$uid])) $by_uid[$uid] = array();
        $by_uid[$uid][] = $ve;
    }

    $events = array();

    foreach ($by_uid as $uid => $items) {
        // Split into master + overrides
        $master = null;
        $overrides = array(); // key: recurrence_ts => vevent override
        foreach ($items as $ve) {
            if (!empty($ve['recurrence_id_ts'])) {
                $overrides[(int)$ve['recurrence_id_ts']] = $ve;
                continue;
            }
            // Prefer the one with RRULE as master if multiples exist
            if ($master === null) {
                $master = $ve;
            } elseif (!empty($ve['rrule_raw']) && empty($master['rrule_raw'])) {
                $master = $ve;
            }
        }

        if (!$master) continue;

        // If no RRULE: just a single event, but apply override if it exists.
        if (empty($master['rrule_raw'])) {
            $start_ts = (int)$master['start_ts'];
            $end_ts   = (int)$master['end_ts'];

            // Only include if within window (loosely)
            if (!vms_vendor_ics_range_intersects_window($start_ts, $end_ts, $window)) {
                continue;
            }

            // In practice, overrides on non-rrule events are rare, but we’ll honor them if present.
            if (isset($overrides[$start_ts])) {
                $ov = $overrides[$start_ts];
                if (!empty($ov['status']) && strtoupper((string)$ov['status']) === 'CANCELLED') {
                    continue;
                }
                $start_ts = (int)$ov['start_ts'];
                $end_ts   = (int)$ov['end_ts'];
            }

            $events[] = array('start' => $start_ts, 'end' => $end_ts);
            continue;
        }

        // RRULE expansion (weekly MVP)
        $occurrences = vms_vendor_ics_expand_rrule_weekly($master, $window, $fallback_tz);

        // Apply EXDATE exclusions
        if (!empty($master['exdate_set'])) {
            foreach ($occurrences as $k => $occ) {
                $st = (int)$occ['start'];
                if (isset($master['exdate_set'][$st])) {
                    unset($occurrences[$k]);
                }
            }
        }

        // Apply overrides (RECURRENCE-ID)
        foreach ($overrides as $rid_ts => $ov) {
            // Remove the original occurrence if present
            foreach ($occurrences as $k => $occ) {
                if ((int)$occ['start'] === (int)$rid_ts) {
                    unset($occurrences[$k]);
                    break;
                }
            }

            // If cancelled, it’s just removed.
            if (!empty($ov['status']) && strtoupper((string)$ov['status']) === 'CANCELLED') {
                continue;
            }

            // Add override occurrence if it intersects our window
            $ov_start = (int)$ov['start_ts'];
            $ov_end   = (int)$ov['end_ts'];

            if (vms_vendor_ics_range_intersects_window($ov_start, $ov_end, $window)) {
                $occurrences[] = array('start' => $ov_start, 'end' => $ov_end);
            }
        }

        // Add to final
        foreach ($occurrences as $occ) {
            $events[] = array('start' => (int)$occ['start'], 'end' => (int)$occ['end']);
        }
    }

    // Safety: normalize end>=start
    foreach ($events as &$e) {
        if ($e['end'] < $e['start']) $e['end'] = $e['start'];
    }
    unset($e);

    return $events;
}

/**
 * Parse a VEVENT block into a structured array.
 */
function vms_vendor_ics_parse_vevent_block(string $block, DateTimeZone $fallback_tz): ?array
{
    $uid = vms_vendor_ics_find_prop_value($block, 'UID');
    if (!$uid) return null;

    $dtstart = vms_vendor_ics_find_prop_with_params($block, 'DTSTART');
    if (!$dtstart) return null;

    $dtend   = vms_vendor_ics_find_prop_with_params($block, 'DTEND');
    $rrule   = vms_vendor_ics_find_prop_value($block, 'RRULE');
    $status  = vms_vendor_ics_find_prop_value($block, 'STATUS');

    // Recurrence override
    $rec_id = vms_vendor_ics_find_prop_with_params($block, 'RECURRENCE-ID');

    // Parse DTSTART/DTEND with TZID awareness
    $start_ts = vms_vendor_ics_parse_dt_with_params($dtstart['value'], $dtstart['params'], $fallback_tz);
    if (!$start_ts) return null;

    $end_ts = null;
    if ($dtend) {
        $end_ts = vms_vendor_ics_parse_dt_with_params($dtend['value'], $dtend['params'], $fallback_tz);
    }
    if (!$end_ts) {
        // If no DTEND, assume 1 hour
        $end_ts = $start_ts + 3600;
    }

    // Parse EXDATE(s) (can be multiple lines AND comma-separated)
    $exdate_set = vms_vendor_ics_parse_exdates($block, $fallback_tz);

    $recurrence_id_ts = null;
    if ($rec_id) {
        $recurrence_id_ts = vms_vendor_ics_parse_dt_with_params($rec_id['value'], $rec_id['params'], $fallback_tz);
    }

    return array(
        'uid'              => (string)$uid,
        'start_ts'         => (int)$start_ts,
        'end_ts'           => (int)$end_ts,
        'duration'         => (int)$end_ts - (int)$start_ts,
        'rrule_raw'        => $rrule ? (string)$rrule : '',
        'status'           => $status ? (string)$status : '',
        'recurrence_id_ts' => $recurrence_id_ts ? (int)$recurrence_id_ts : 0,
        'exdate_set'       => $exdate_set,
        // Keep original DTSTART params around (useful for debugging)
        'dtstart_params'   => $dtstart['params'],
    );
}

/**
 * Find property VALUE only (no params).
 */
function vms_vendor_ics_find_prop_value(string $block, string $prop): ?string
{
    if (preg_match('/^' . preg_quote($prop, '/') . '(?:;[^:]*)?:(.+)$/m', $block, $m)) {
        return trim((string)$m[1]);
    }
    return null;
}

/**
 * Find a property and also parse its params.
 * Example: DTSTART;TZID=America/Chicago:20260105T180000
 * Returns: ['value'=>'20260105T180000', 'params'=>['TZID'=>'America/Chicago', ...]]
 */
function vms_vendor_ics_find_prop_with_params(string $block, string $prop): ?array
{
    if (!preg_match('/^' . preg_quote($prop, '/') . '([^:]*)\:(.+)$/m', $block, $m)) {
        return null;
    }

    $param_str = trim((string)$m[1]); // like ;TZID=America/Chicago;VALUE=DATE
    $value     = trim((string)$m[2]);

    $params = array();
    if ($param_str !== '') {
        // Split ;A=B;C=D...
        $pieces = explode(';', ltrim($param_str, ';'));
        foreach ($pieces as $p) {
            if ($p === '') continue;
            $kv = explode('=', $p, 2);
            if (count($kv) === 2) {
                $params[strtoupper(trim($kv[0]))] = trim($kv[1]);
            } else {
                // Param with no '=' (rare) – store as flag
                $params[strtoupper(trim($kv[0]))] = '1';
            }
        }
    }

    return array('value' => $value, 'params' => $params);
}

/**
 * Parse EXDATE lines into a set keyed by occurrence start timestamp.
 * Google can emit:
 *   EXDATE;TZID=America/Chicago:20260110T180000,20260117T180000
 * And can emit multiple EXDATE lines.
 */
function vms_vendor_ics_parse_exdates(string $block, DateTimeZone $fallback_tz): array
{
    $set = array();

    // Find all EXDATE lines (including params)
    if (!preg_match_all('/^EXDATE([^:]*)\:(.+)$/m', $block, $mm, PREG_SET_ORDER)) {
        return $set;
    }

    foreach ($mm as $m) {
        $param_str = trim((string)$m[1]);
        $value_str = trim((string)$m[2]);

        // Parse params into array
        $params = array();
        if ($param_str !== '') {
            $pieces = explode(';', ltrim($param_str, ';'));
            foreach ($pieces as $p) {
                if ($p === '') continue;
                $kv = explode('=', $p, 2);
                if (count($kv) === 2) $params[strtoupper(trim($kv[0]))] = trim($kv[1]);
            }
        }

        // Values can be comma-separated
        $vals = array_map('trim', explode(',', $value_str));
        foreach ($vals as $v) {
            if ($v === '') continue;
            $ts = vms_vendor_ics_parse_dt_with_params($v, $params, $fallback_tz);
            if ($ts) $set[(int)$ts] = true;
        }
    }

    return $set;
}

/**
 * Parse datetime respecting TZID when present, and Zulu when value ends with Z.
 */
function vms_vendor_ics_parse_dt_with_params(string $value, array $params, DateTimeZone $fallback_tz): ?int
{
    $value = trim($value);

    // If TZID supplied, try it.
    $tz = $fallback_tz;
    if (!empty($params['TZID'])) {
        try {
            $tz = new DateTimeZone((string)$params['TZID']);
        } catch (Exception $e) {
            $tz = $fallback_tz;
        }
    }

    // VALUE=DATE means all-day date
    if (!empty($params['VALUE']) && strtoupper((string)$params['VALUE']) === 'DATE') {
        if (preg_match('/^\d{8}$/', $value)) {
            $dt = DateTime::createFromFormat('Ymd', $value, $tz);
            if (!$dt) return null;
            $dt->setTime(0, 0, 0);
            return $dt->getTimestamp();
        }
    }

    // DATE-only: YYYYMMDD
    if (preg_match('/^\d{8}$/', $value)) {
        $dt = DateTime::createFromFormat('Ymd', $value, $tz);
        if (!$dt) return null;
        $dt->setTime(0, 0, 0);
        return $dt->getTimestamp();
    }

    // Zulu
    if (preg_match('/^\d{8}T\d{6}Z$/', $value)) {
        $dt = DateTime::createFromFormat('Ymd\THis\Z', $value, new DateTimeZone('UTC'));
        return $dt ? $dt->getTimestamp() : null;
    }
    if (preg_match('/^\d{8}T\d{4}Z$/', $value)) {
        $dt = DateTime::createFromFormat('Ymd\THi\Z', $value, new DateTimeZone('UTC'));
        return $dt ? $dt->getTimestamp() : null;
    }

    // Local with seconds
    if (preg_match('/^\d{8}T\d{6}$/', $value)) {
        $dt = DateTime::createFromFormat('Ymd\THis', $value, $tz);
        return $dt ? $dt->getTimestamp() : null;
    }

    // Local without seconds
    if (preg_match('/^\d{8}T\d{4}$/', $value)) {
        $dt = DateTime::createFromFormat('Ymd\THi', $value, $tz);
        return $dt ? $dt->getTimestamp() : null;
    }

    // Fallback parse
    try {
        $dt = new DateTime($value, $tz);
        return $dt->getTimestamp();
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Build a reasonable expansion window based on active_dates:
 * - start: min(active_dates) at 00:00
 * - end:   max(active_dates)+1 day at 00:00
 * Adds some padding so weekly rules don’t miss boundary cases.
 */
function vms_vendor_ics_build_window_from_active_dates(array $active_dates, DateTimeZone $tz): array
{
    // Default: today -> +12 months
    $now = new DateTime('now', $tz);
    $default_start = (clone $now)->setTime(0, 0, 0);
    $default_end   = (clone $now)->modify('+12 months')->setTime(23, 59, 59);

    if (empty($active_dates)) {
        return array(
            'start_ts' => $default_start->getTimestamp(),
            'end_ts'   => $default_end->getTimestamp(),
        );
    }

    // Determine min/max YYYY-MM-DD
    sort($active_dates);
    $min = reset($active_dates);
    $max = end($active_dates);

    $start = DateTime::createFromFormat('Y-m-d H:i:s', $min . ' 00:00:00', $tz);
    $end   = DateTime::createFromFormat('Y-m-d H:i:s', $max . ' 23:59:59', $tz);

    if (!$start || !$end) {
        return array(
            'start_ts' => $default_start->getTimestamp(),
            'end_ts'   => $default_end->getTimestamp(),
        );
    }

    // Padding (helps INTERVAL weeks that start before window)
    $start->modify('-14 days');
    $end->modify('+14 days');

    return array(
        'start_ts' => $start->getTimestamp(),
        'end_ts'   => $end->getTimestamp(),
    );
}

function vms_vendor_ics_range_intersects_window(int $start_ts, int $end_ts, array $window): bool
{
    $ws = (int)($window['start_ts'] ?? 0);
    $we = (int)($window['end_ts'] ?? 0);
    if ($we <= 0) return true;

    // overlap if event_start < window_end && event_end > window_start
    return ($start_ts < $we) && ($end_ts > $ws);
}

/**
 * Parse RRULE into key=>value pairs.
 * Example: FREQ=WEEKLY;INTERVAL=1;BYDAY=MO,WE,FR;UNTIL=20261231T000000Z
 */
function vms_vendor_ics_parse_rrule(string $rrule_raw): array
{
    $out = array();
    $rrule_raw = trim($rrule_raw);
    if ($rrule_raw === '') return $out;

    $parts = explode(';', $rrule_raw);
    foreach ($parts as $p) {
        $kv = explode('=', $p, 2);
        if (count($kv) !== 2) continue;
        $out[strtoupper(trim($kv[0]))] = trim($kv[1]);
    }
    return $out;
}

/**
 * Expand WEEKLY RRULE occurrences for a master VEVENT inside window.
 * Returns array of ['start'=>ts,'end'=>ts].
 *
 * Notes:
 * - Uses DTSTART time-of-day as the time for each occurrence.
 * - Handles BYDAY, INTERVAL, UNTIL, COUNT.
 * - If BYDAY omitted, uses DTSTART weekday.
 */
function vms_vendor_ics_expand_rrule_weekly(array $master, array $window, DateTimeZone $fallback_tz): array
{
    $rr = vms_vendor_ics_parse_rrule((string)$master['rrule_raw']);
    $freq = strtoupper((string)($rr['FREQ'] ?? ''));
    if ($freq !== 'WEEKLY') {
        // MVP: only weekly right now. You can add DAILY/MONTHLY later.
        return array();
    }

    $interval = max(1, (int)($rr['INTERVAL'] ?? 1));
    $count    = isset($rr['COUNT']) ? max(0, (int)$rr['COUNT']) : 0;

    // UNTIL may be Zulu or local; parse with DTSTART tz if possible
    $until_ts = 0;
    if (!empty($rr['UNTIL'])) {
        $until_ts = (int)(vms_vendor_ics_parse_dt_with_params((string)$rr['UNTIL'], $master['dtstart_params'] ?? array(), $fallback_tz) ?? 0);
    }

    // Determine timezone used for DTSTART interpretation
    $tz = $fallback_tz;
    if (!empty($master['dtstart_params']['TZID'])) {
        try {
            $tz = new DateTimeZone((string)$master['dtstart_params']['TZID']);
        } catch (Exception $e) {
            $tz = $fallback_tz;
        }
    }

    $dtstart = new DateTime('@' . (int)$master['start_ts']);
    $dtstart->setTimezone($tz);

    $duration = (int)($master['duration'] ?? 3600);
    if ($duration <= 0) $duration = 3600;

    // BYDAY handling
    $byday_raw = (string)($rr['BYDAY'] ?? '');
    $byday = array();
    if ($byday_raw !== '') {
        $byday = array_map('trim', explode(',', $byday_raw));
    } else {
        // Default to DTSTART weekday
        $map = array(
            'MO' => 1,
            'TU' => 2,
            'WE' => 3,
            'TH' => 4,
            'FR' => 5,
            'SA' => 6,
            'SU' => 7,
        );
        $dow = (int)$dtstart->format('N'); // 1..7
        $rev = array_flip($map);
        $byday = array($rev[$dow] ?? 'MO');
    }

    $map = array(
        'MO' => 1,
        'TU' => 2,
        'WE' => 3,
        'TH' => 4,
        'FR' => 5,
        'SA' => 6,
        'SU' => 7,
    );

    // Anchor week start: Monday of DTSTART week
    $week_anchor = clone $dtstart;
    $week_anchor->modify('monday this week');

    $window_start = (int)($window['start_ts'] ?? 0);
    $window_end   = (int)($window['end_ts'] ?? 0);

    // Jump close to window start to avoid iterating from the beginning of time.
    if ($window_start > 0) {
        $ws = new DateTime('@' . $window_start);
        $ws->setTimezone($tz);
        $ws->modify('monday this week');

        // estimate number of weeks between anchors, then step by interval
        $diff_days = (int)$week_anchor->diff($ws)->format('%r%a');
        $diff_weeks = (int)floor($diff_days / 7);

        if ($diff_weeks > 0) {
            // Snap down to a multiple of interval
            $step_weeks = (int)(floor($diff_weeks / $interval) * $interval);
            if ($step_weeks > 0) {
                $week_anchor->modify('+' . $step_weeks . ' weeks');
            }
        }
    }

    $out = array();
    $made = 0;
    $safety = 0;

    while (true) {
        $safety++;
        if ($safety > 4000) break; // hard stop safety

        // For each BYDAY in this week, create occurrence at DTSTART time
        foreach ($byday as $d) {
            $d = strtoupper($d);
            if (!isset($map[$d])) continue;

            $occ = clone $week_anchor;
            $target_n = (int)$map[$d];

            // week_anchor is Monday => N=1; add (target_n-1) days
            $occ->modify('+' . ($target_n - 1) . ' days');

            // Apply DTSTART time-of-day
            $occ->setTime((int)$dtstart->format('H'), (int)$dtstart->format('i'), (int)$dtstart->format('s'));

            $start_ts = $occ->getTimestamp();
            $end_ts   = $start_ts + $duration;

            // Don’t include occurrences earlier than the master DTSTART (some BYDAY combos can generate before DTSTART)
            if ($start_ts < (int)$master['start_ts']) {
                continue;
            }

            // UNTIL cutoff (inclusive-ish: if start > until, stop adding)
            if ($until_ts > 0 && $start_ts > $until_ts) {
                continue;
            }

            // Window filter
            if ($window_end > 0 && $start_ts >= $window_end) {
                continue;
            }
            if ($window_start > 0 && $end_ts <= $window_start) {
                continue;
            }

            $out[] = array('start' => $start_ts, 'end' => $end_ts);
            $made++;

            if ($count > 0 && $made >= $count) {
                break 2; // done
            }
        }

        // Next interval week
        $week_anchor->modify('+' . $interval . ' weeks');

        // Break conditions: past window end, or past UNTIL by a comfortable margin
        if ($window_end > 0 && $week_anchor->getTimestamp() > ($window_end + 86400 * 14)) {
            break;
        }
        if ($until_ts > 0 && $week_anchor->getTimestamp() > ($until_ts + 86400 * 14)) {
            break;
        }
    }

    return $out;
}
function vms_vendor_ics_find_prop(string $block, string $prop): ?string
{
    // Matches lines like: DTSTART:20260105T180000Z or DTSTART;TZID=America/Chicago:...
    if (preg_match('/^' . preg_quote($prop, '/') . '(?:;[^:]*)?:(.+)$/m', $block, $m)) {
        return trim((string) $m[1]);
    }
    return null;
}

/**
 * Parse common ICS datetime formats into timestamp using given timezone when needed.
 */
function vms_vendor_ics_parse_dt(string $value, DateTimeZone $fallback_tz): ?int
{
    $value = trim($value);

    // DATE-only: YYYYMMDD (all-day)
    if (preg_match('/^\d{8}$/', $value)) {
        $dt = DateTime::createFromFormat('Ymd', $value, $fallback_tz);
        if (!$dt) return null;
        $dt->setTime(0, 0, 0);
        return $dt->getTimestamp();
    }

    // Date-time with Zulu: YYYYMMDDTHHMMSSZ
    if (preg_match('/^\d{8}T\d{6}Z$/', $value)) {
        $dt = DateTime::createFromFormat('Ymd\THis\Z', $value, new DateTimeZone('UTC'));
        return $dt ? $dt->getTimestamp() : null;
    }

    // Date-time without seconds, Zulu: YYYYMMDDTHHMMZ
    if (preg_match('/^\d{8}T\d{4}Z$/', $value)) {
        $dt = DateTime::createFromFormat('Ymd\THi\Z', $value, new DateTimeZone('UTC'));
        return $dt ? $dt->getTimestamp() : null;
    }

    // Date-time local: YYYYMMDDTHHMMSS
    if (preg_match('/^\d{8}T\d{6}$/', $value)) {
        $dt = DateTime::createFromFormat('Ymd\THis', $value, $fallback_tz);
        return $dt ? $dt->getTimestamp() : null;
    }

    // Date-time local without seconds: YYYYMMDDTHHMM
    if (preg_match('/^\d{8}T\d{4}$/', $value)) {
        $dt = DateTime::createFromFormat('Ymd\THi', $value, $fallback_tz);
        return $dt ? $dt->getTimestamp() : null;
    }

    // Fallback
    try {
        $dt = new DateTime($value, $fallback_tz);
        return $dt->getTimestamp();
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Convert events to a set of busy dates (YYYY-MM-DD) in VMS timezone.
 * If an event spans multiple days, all days are marked busy.
 */
function vms_vendor_ics_busy_dates(array $events): array
{
    $tz = vms_get_timezone();
    $busy = array();

    // MVP show window (local time)
    // Later: make these settings-driven per venue/day
    $window_start = '17:00'; // 5:00pm
    $window_end   = '23:59'; // 11:59pm

    foreach ($events as $ev) {
        $start_ts = (int) $ev['start'];
        $end_ts   = (int) $ev['end'];

        // Convert event start/end into localized DateTime
        $ev_start = new DateTime('@' . $start_ts);
        $ev_start->setTimezone($tz);

        $ev_end = new DateTime('@' . $end_ts);
        $ev_end->setTimezone($tz);

        // Iterate each date spanned by the event
        $day = clone $ev_start;
        $day->setTime(0, 0, 0);

        $last_day = clone $ev_end;
        $last_day->setTime(0, 0, 0);

        while ($day <= $last_day) {
            $ymd = $day->format('Y-m-d');

            // Build the show window for THIS day
            $ws = DateTime::createFromFormat('Y-m-d H:i', $ymd . ' ' . $window_start, $tz);
            $we = DateTime::createFromFormat('Y-m-d H:i', $ymd . ' ' . $window_end, $tz);

            if ($ws && $we) {
                $ws_ts = $ws->getTimestamp();
                $we_ts = $we->getTimestamp();

                // DEBUG (temporary): log overlap math for first few events/days
                // static $dbg = 0;
                // if ($dbg < 6) {
                //     error_log(sprintf(
                //         'VMS ICS OVERLAP ymd=%s start=%s end=%s ws=%s we=%s cond1(start<we)=%s cond2(end>ws)=%s',
                //         $ymd,
                //         (new DateTime("@$start_ts"))->setTimezone($tz)->format('Y-m-d H:i T'),
                //         (new DateTime("@$end_ts"))->setTimezone($tz)->format('Y-m-d H:i T'),
                //         (new DateTime("@$ws_ts"))->setTimezone($tz)->format('Y-m-d H:i T'),
                //         (new DateTime("@$we_ts"))->setTimezone($tz)->format('Y-m-d H:i T'),
                //         ($start_ts < $we_ts ? 'true' : 'false'),
                //         ($end_ts > $ws_ts ? 'true' : 'false')
                //     ));
                //     $dbg++;
                // }

                // Compute overlap: (event_start < window_end) && (event_end > window_start)
                if ($start_ts < $we_ts && $end_ts > $ws_ts) {
                    $busy[$ymd] = true;
                }
            }

            $day->modify('+1 day');
        }
    }

    return $busy;
}
