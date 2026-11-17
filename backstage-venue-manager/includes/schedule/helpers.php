<?php
defined('ABSPATH') || exit;

// Shared schedule helpers (used by admin + REST).
// Developers can override via filter 'vms_sch_all_venue_ids'.

if (!function_exists('bvmgr_sch_get_all_venue_ids')) {
    function bvmgr_sch_get_all_venue_ids(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        // Allow hard override.
        $filtered = apply_filters('vms_sch_all_venue_ids', null);
        if (is_array($filtered)) {
            $cached = array_values(array_unique(array_filter(array_map('intval', $filtered))));
            return $cached;
        }

        // Prefer canonical helper if present.
        if (function_exists('vms_get_all_venue_ids')) {
            $ids = (array) vms_get_all_venue_ids();
            $cached = array_values(array_unique(array_filter(array_map('intval', $ids))));
            return $cached;
        }

        // Option fallback.
        $opt = get_option('vms_venue_ids', null);
        if (is_array($opt) && !empty($opt)) {
            $cached = array_values(array_unique(array_filter(array_map('intval', $opt))));
            return $cached;
        }

        // CPT fallback(s): primary + TEC fallback.
        $primary = apply_filters('vms_venue_post_type', 'vms_venue');
        $post_types_to_try = array_values(array_unique(array_filter(array($primary, 'tribe_venue'))));

        foreach ($post_types_to_try as $pt) {
            $ids = get_posts(array(
                'post_type'      => $pt,
                'post_status'    => array('publish', 'private', 'draft'),
                'fields'         => 'ids',
                'posts_per_page' => -1,
                'no_found_rows'  => true,
            ));

            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
            if (!empty($ids)) {
                $cached = $ids;
                return $cached;
            }
        }

        $cached = array();
        return $cached;
    }
}

if (!function_exists('bvmgr_sch_get_schedule_venue_candidates')) {
    /**
     * Returns candidate VMS venue IDs for Schedule "This venue" context.
     *
     * This intentionally queries only vms_venue posts (not TEC venues), so the
     * selector and fallback logic use the same candidate set.
     */
    function bvmgr_sch_get_schedule_venue_candidates(int $limit = -1): array
    {
        $posts_per_page = (int) $limit;
        if ($posts_per_page === 0) {
            $posts_per_page = -1;
        }

        $ids = get_posts(array(
            'post_type'      => 'vms_venue',
            'post_status'    => array('publish', 'private', 'draft', 'pending', 'future'),
            'posts_per_page' => $posts_per_page,
            'fields'         => 'ids',
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ));

        if (!is_array($ids)) {
            return array();
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }
}

if (!function_exists('bvmgr_sch_pick_single_venue_candidate')) {
    /**
     * Returns a single venue ID when exactly one unique candidate exists; else 0.
     */
    function bvmgr_sch_pick_single_venue_candidate(array $candidate_ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $candidate_ids))));
        return (count($ids) === 1) ? (int) $ids[0] : 0;
    }
}
// ===============================
// Venue default compensation (shared)
// ===============================

if (!function_exists('bvmgr_get_venue_default_comp_by_dow')) {
    /**
     * Returns per-day default compensation rows stored on a venue.
     *
     * Storage: post_meta on vms_venue
     *   _vms_default_comp_by_dow (array keyed by 0..6)
     */
    function bvmgr_get_venue_default_comp_by_dow(int $venue_id): array
    {
        if ($venue_id <= 0) return array();
        $saved = get_post_meta($venue_id, '_vms_default_comp_by_dow', true);
        return is_array($saved) ? $saved : array();
    }
}

if (!function_exists('bvmgr_get_venue_default_comp_for_date')) {
    /**
     * Returns the normalized default compensation row for a venue + event date.
     *
     * Output keys:
     * - structure (string)
     * - flat_fee_amount (string|float|null)
     * - door_split_percent (string|float|null)
     * - commission_percent (string|float|null)
     */
    function bvmgr_get_venue_default_comp_for_date(int $venue_id, string $event_date): array
    {
        $event_date = trim($event_date);
        if ($venue_id <= 0 || $event_date === '') return array();

        // Use VMS timezone helper if available; fallback to WP timezone.
        $tz = null;
        if (function_exists('bvmgr_get_timezone')) {
            $tz = bvmgr_get_timezone(); // expected DateTimeZone
        }
        if (!$tz instanceof DateTimeZone) {
            $tz = wp_timezone();
        }

        try {
            // Expect Y-m-d. If passed other formats, DateTimeImmutable will attempt parse.
            $dt = new DateTimeImmutable($event_date, $tz);
        } catch (Exception $e) {
            return array();
        }

        $dow = (int) $dt->format('w'); // 0..6 (Sun..Sat)

        $all = bvmgr_get_venue_default_comp_by_dow($venue_id);
        if (!isset($all[$dow]) || !is_array($all[$dow])) return array();

        $row = $all[$dow];

        // Normalize output keys (keep strings as-is; callers decide casting)
        return array(
            'structure'          => isset($row['structure']) ? (string) $row['structure'] : '',
            'flat_fee_amount'    => isset($row['flat_fee_amount']) ? $row['flat_fee_amount'] : '',
            'door_split_percent' => isset($row['door_split_percent']) ? $row['door_split_percent'] : '',
            'commission_percent' => isset($row['commission_percent']) ? $row['commission_percent'] : '',
        );
    }
}

// ===============================
// Holidays (shared)
// ===============================

if (!function_exists('bvmgr_sch_get_holidays_for_date')) {
    /**
     * Return holiday entries for a venue + date.
     *
     * Storage: wp_options option "vms_holidays"
     *
     * Normal output shape:
     * [
     *   ['name' => string, 'status' => string],
     *   ...
     * ]
     *
     * Defensive: if a future format stores multiple holidays under a single date,
     * we also accept a numeric array of holiday objects.
     */
    function bvmgr_sch_get_holidays_for_date(int $venue_id, string $ymd): array
    {
        $ymd = trim($ymd);
        if ($venue_id <= 0 || $ymd === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
            return array();
        }

        static $all = null;
        if ($all === null) {
            $opt = get_option('vms_holidays', array());
            $all = is_array($opt) ? $opt : array();
        }

        $raw = $all[$venue_id][$ymd] ?? null;
        if (!is_array($raw) || empty($raw)) {
            return array();
        }

        // Future-proof: numeric array of holiday entries.
        $keys = array_keys($raw);
        $is_list = ($keys === range(0, count($keys) - 1));
        if ($is_list) {
            $out = array();
            foreach ($raw as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $name = trim((string) ($item['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $status = sanitize_key((string) ($item['status'] ?? ''));
                $out[] = array('name' => $name, 'status' => $status);
            }
            return $out;
        }

        $name = trim((string) ($raw['name'] ?? ''));
        if ($name === '') {
            return array();
        }
        $status = sanitize_key((string) ($raw['status'] ?? ''));
        return array(
            array(
                'name' => $name,
                'status' => $status,
            )
        );
    }
}

// ===============================
// Holiday precedence helpers (shared)
// ===============================

if (!function_exists('bvmgr_sch_holiday_forces_open')) {
    /**
     * Returns true when ANY holiday entry on this date is explicitly marked as open.
     * Holiday always wins over blackout in Schedule semantics.
     */
    function bvmgr_sch_holiday_forces_open(int $venue_id, string $ymd): bool
    {
        $entries = function_exists('bvmgr_sch_get_holidays_for_date') ? bvmgr_sch_get_holidays_for_date($venue_id, $ymd) : array();
        if (empty($entries)) {
            return false;
        }
        foreach ($entries as $h) {
            if (!is_array($h)) continue;
            $st = sanitize_key((string) ($h['status'] ?? ''));
            if ($st === 'open') {
                return true;
            }
        }
        return false;
    }
}
