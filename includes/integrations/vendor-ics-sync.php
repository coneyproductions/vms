<?php
if (!defined('ABSPATH')) exit;

/**
 * Fetch + parse a vendor ICS feed and mark conflicting dates as unavailable.
 *
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
        'timeout'     => 15,
        'redirection' => 3,
        'headers'     => array(
            'Accept' => 'text/calendar',
        ),
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

    // If calendar is empty, clear ICS layer for active dates and return ok.
    if (!$events) {
        update_post_meta($vendor_id, '_vms_availability_ics', array());
        return array('ok' => true, 'ics_unavailable' => array(), 'marked' => 0);
    }

    // Build set of busy dates (YYYY-MM-DD) in VMS timezone.
    $busy_dates = vms_vendor_ics_busy_dates($events);

    // Only keep busy dates that are in active_dates
    $active_set = array_fill_keys($active_dates, true);

    $ics_unavailable = array();
    foreach ($busy_dates as $ymd => $_true) {
        if (!isset($active_set[$ymd])) continue;
        $ics_unavailable[$ymd] = 'unavailable';
    }

    // Rebuild ICS layer entirely on each sync (do NOT write into manual).
    update_post_meta($vendor_id, '_vms_availability_ics', $ics_unavailable);

    return array(
        'ok'            => true,
        'ics_unavailable'=> $ics_unavailable,
        'marked'        => count($ics_unavailable),
    );
}


/**
 * Extract VEVENT date ranges from raw ICS.
 * MVP parser: handles DTSTART/DTEND in common formats.
 * Returns array of ['start'=>timestamp,'end'=>timestamp].
 */
function vms_vendor_ics_extract_events(string $raw): array
{
    // Unfold lines (RFC5545): lines starting with space/tab continue previous line
    $raw = preg_replace("/\r\n[ \t]/", '', $raw);

    preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $raw, $matches);
    if (empty($matches[1])) return array();

    $tz = vms_get_timezone(); // from your helpers.php
    $events = array();

    foreach ($matches[1] as $block) {
        $dtstart = vms_vendor_ics_find_prop($block, 'DTSTART');
        $dtend   = vms_vendor_ics_find_prop($block, 'DTEND');

        if (!$dtstart) continue;

        $start_ts = vms_vendor_ics_parse_dt($dtstart, $tz);
        if (!$start_ts) continue;

        // If no DTEND, assume 1 hour event
        $end_ts = $dtend ? vms_vendor_ics_parse_dt($dtend, $tz) : ($start_ts + 3600);
        if (!$end_ts) $end_ts = $start_ts + 3600;

        // Normalize end >= start
        if ($end_ts < $start_ts) $end_ts = $start_ts;

        $events[] = array('start' => $start_ts, 'end' => $end_ts);
    }

    return $events;
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
                static $dbg = 0;
                if ($dbg < 6) {
                    error_log(sprintf(
                        'VMS ICS OVERLAP ymd=%s start=%s end=%s ws=%s we=%s cond1(start<we)=%s cond2(end>ws)=%s',
                        $ymd,
                        (new DateTime("@$start_ts"))->setTimezone($tz)->format('Y-m-d H:i T'),
                        (new DateTime("@$end_ts"))->setTimezone($tz)->format('Y-m-d H:i T'),
                        (new DateTime("@$ws_ts"))->setTimezone($tz)->format('Y-m-d H:i T'),
                        (new DateTime("@$we_ts"))->setTimezone($tz)->format('Y-m-d H:i T'),
                        ($start_ts < $we_ts ? 'true' : 'false'),
                        ($end_ts > $ws_ts ? 'true' : 'false')
                    ));
                    $dbg++;
                }

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
