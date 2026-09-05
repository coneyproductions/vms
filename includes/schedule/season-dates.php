<?php
defined('ABSPATH') || exit;

/**
 * Season Dates V1 (rules + generated active dates)
 *
 * Storage (options):
 * - vms_season_rules_v1         (per-venue rules)
 * - vms_season_active_dates_v1  (per-venue generated dates map)
 *
 * Design goals:
 * - Shared schedule layer (REST-safe) — no admin dependencies
 * - Reversible and deterministic
 * - Conservative defaults (no rules => closed)
 */

if (!defined('BVMGR_SEASON_RULES_OPT_V1')) {
    define('BVMGR_SEASON_RULES_OPT_V1', 'vms_season_rules_v1');
}
if (!defined('BVMGR_SEASON_ACTIVE_OPT_V1')) {
    define('BVMGR_SEASON_ACTIVE_OPT_V1', 'vms_season_active_dates_v1');
}

/** -------------------------
 *  Sanitizers / validators
 *  ------------------------- */

function bvmgr_sch_season_is_valid_mmdd(string $mmdd): bool
{
    if (!preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $mmdd)) {
        return false;
    }
    [$m, $d] = array_map('intval', explode('-', $mmdd));
    return checkdate($m, $d, 2000); // 2000 is leap year, so 02-29 is allowed
}
function bvmgr_sch_season_is_valid_ymd(string $ymd): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
        return false;
    }
    [$y, $m, $d] = array_map('intval', explode('-', $ymd));
    return checkdate($m, $d, $y);
}

function bvmgr_sch_season_sanitize_note($note): string
{
    $note = is_string($note) ? $note : '';
    $note = wp_strip_all_tags($note);
    return trim($note);
}
function bvmgr_sch_season_sanitize_days_w($raw): ?int
{
    if ($raw === null || $raw === '') {
        return null;
    }
    $mask = (int) $raw;
    if ($mask < 0)   $mask = 0;
    if ($mask > 127) $mask = 127;

    // Only store if at least one weekday selected.
    return ($mask > 0) ? $mask : null;
}

function bvmgr_sch_season_sanitize_rule(array $rule): ?array
{
    $type = isset($rule['type']) ? sanitize_key((string) $rule['type']) : '';
    $enabled = !empty($rule['enabled']);

    $id = isset($rule['id']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $rule['id']) : '';
    if ($id === '') {
        $id = 'r_' . substr(wp_generate_uuid4(), 0, 8);
    }

    $note = bvmgr_sch_season_sanitize_note($rule['note'] ?? '');

    if ($type === 'open_window') {
        $start = isset($rule['start_mmdd']) ? trim((string) $rule['start_mmdd']) : '';
        $end   = isset($rule['end_mmdd']) ? trim((string) $rule['end_mmdd']) : '';

        if (!bvmgr_sch_season_is_valid_mmdd($start) || !bvmgr_sch_season_is_valid_mmdd($end)) {
            return null;
        }

        $out = [
            'id'         => $id,
            'type'       => 'open_window',
            'enabled'    => $enabled,
            'start_mmdd' => $start,
            'end_mmdd'   => $end,
            'note'       => $note,
        ];

        // ✅ days_w is stored as an INT bitmask (1..127). Omit = all days.
        // Also accept legacy array-of-0..6 and convert to mask (defensive).
        $mask = null;

        if (isset($rule['days_w'])) {
            if (is_array($rule['days_w'])) {
                $tmp = 0;
                foreach ($rule['days_w'] as $d) {
                    if (!is_scalar($d)) continue;
                    $dow = (int) $d;
                    if ($dow < 0 || $dow > 6) continue;
                    $tmp |= (1 << $dow);
                }
                $mask = ($tmp > 0 && $tmp <= 127) ? $tmp : null;
            } else {
                $mask = bvmgr_sch_season_sanitize_days_w($rule['days_w']);
            }
        }

        if ($mask !== null) {
            $out['days_w'] = (int) $mask;
        } else {
            unset($out['days_w']); // blank/invalid => all days
        }

        return $out;
    }

    if (isset($rule['days_w']) && is_array($rule['days_w'])) {
        foreach ($rule['days_w'] as $d) {
            $d = is_numeric($d) ? (int) $d : -1;
            if ($d >= 0 && $d <= 6) {
                $days_w[] = $d;
            }
        }
        $days_w = array_values(array_unique($days_w));
    }

    if ($type === 'blackout_date') {
        $date = isset($rule['date_ymd']) ? trim((string) $rule['date_ymd']) : '';
        if (!bvmgr_sch_season_is_valid_ymd($date)) {
            return null;
        }

        return [
            'id'       => $id,
            'type'     => 'blackout_date',
            'enabled'  => $enabled,
            'date_ymd' => $date,
            'note'     => $note,
        ];
    }

    if ($type === 'blackout_range') {
        $start = isset($rule['start_ymd']) ? trim((string) $rule['start_ymd']) : '';
        $end   = isset($rule['end_ymd']) ? trim((string) $rule['end_ymd']) : '';

        if (!bvmgr_sch_season_is_valid_ymd($start) || !bvmgr_sch_season_is_valid_ymd($end)) {
            return null;
        }

        // Normalize order
        if (strcmp($start, $end) > 0) {
            $tmp = $start;
            $start = $end;
            $end = $tmp;
        }

        // A 1-day range is equivalent to blackout_date.
        if ($start === $end) {
            return [
                'id'       => $id,
                'type'     => 'blackout_date',
                'enabled'  => $enabled,
                'date_ymd' => $start,
                'note'     => $note,
            ];
        }

        return [
            'id'        => $id,
            'type'      => 'blackout_range',
            'enabled'   => $enabled,
            'start_ymd' => $start,
            'end_ymd'   => $end,
            'note'      => $note,
        ];
    }

    return null;
}

function bvmgr_sch_season_rules_hash(array $rules): string
{
    // Deterministic hash of sanitized rules (order-independent by id sort).
    $sorted = $rules;
    usort($sorted, function ($a, $b) {
        return strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
    });
    return hash('sha256', wp_json_encode($sorted));
}

function bvmgr_sch_season_dow_w(string $ymd): int
{
    // Day-of-week for a date-only value should not be timezone-sensitive.
    // Use UTC explicitly to avoid server tz surprises.
    try {
        $dt = new DateTimeImmutable($ymd, new DateTimeZone('UTC'));

        return (int) $dt->format('w'); // 0=Sun .. 6=Sat

    } catch (Exception $e) {
        return -1;
    }
}

/** -------------------------
 *  Option access helpers
 *  ------------------------- */

function bvmgr_sch_season_get_rules_all(): array
{
    $all = get_option(BVMGR_SEASON_RULES_OPT_V1, []);
    return is_array($all) ? $all : [];
}

function bvmgr_sch_season_get_rules(int $venue_id): array
{
    $venue_id = absint($venue_id);
    if ($venue_id <= 0) {
        return [];
    }

    $all = bvmgr_sch_season_get_rules_all();
    $rules = $all[$venue_id] ?? [];
    return is_array($rules) ? array_values($rules) : [];
}

function bvmgr_sch_season_save_rules(int $venue_id, array $rules): array
{
    $venue_id = absint($venue_id);
    if ($venue_id <= 0) {
        return [];
    }

    $sanitized = [];
    foreach ($rules as $rule) {
        if (!is_array($rule)) {
            continue;
        }
        $clean = bvmgr_sch_season_sanitize_rule($rule);
        if ($clean) {
            $sanitized[] = $clean;
        }
    }

    $all = bvmgr_sch_season_get_rules_all();
    $all[$venue_id] = array_values($sanitized);

    update_option(BVMGR_SEASON_RULES_OPT_V1, $all, false);

    return $all[$venue_id];
}

function bvmgr_sch_season_get_active_all(): array
{
    $all = get_option(BVMGR_SEASON_ACTIVE_OPT_V1, []);
    return is_array($all) ? $all : [];
}

function bvmgr_sch_season_get_active_payload(int $venue_id): array
{
    static $cache = [];

    $venue_id = absint($venue_id);
    if ($venue_id <= 0) {
        return [];
    }

    if (isset($cache[$venue_id]) && is_array($cache[$venue_id])) {
        return $cache[$venue_id];
    }

    $all = bvmgr_sch_season_get_active_all();

    $payload = [];
    if (isset($all[$venue_id]) && is_array($all[$venue_id])) {
        $payload = $all[$venue_id];
    } elseif (isset($all[(string) $venue_id]) && is_array($all[(string) $venue_id])) {
        $payload = $all[(string) $venue_id];
    }

    $cache[$venue_id] = $payload;
    return $payload;
}

function bvmgr_sch_season_set_active_payload(int $venue_id, array $payload): void
{
    $venue_id = absint($venue_id);
    if ($venue_id <= 0) {
        return;
    }
    $all = bvmgr_sch_season_get_active_all();
    $all[$venue_id] = $payload;
    update_option(BVMGR_SEASON_ACTIVE_OPT_V1, $all, false);
}

function bvmgr_sch_season_clear_active_dates(int $venue_id): void
{
    $venue_id = absint($venue_id);
    if ($venue_id <= 0) {
        return;
    }
    $all = bvmgr_sch_season_get_active_all();
    unset($all[$venue_id]);
    update_option(BVMGR_SEASON_ACTIVE_OPT_V1, $all, false);
}


// Canonical: return a fast lookup map of blackout dates for a venue.
// Uses stored payload when available; otherwise falls back to scanning rules in one place only.
if (!function_exists('bvmgr_sch_season_get_blackout_map')) {
    function bvmgr_sch_season_get_blackout_map(int $venue_id): array
    {
        static $cache = [];

        if (isset($cache[$venue_id]) && is_array($cache[$venue_id])) {
            return $cache[$venue_id];
        }

        $map = [];

        // 1) Prefer stored generated payload (single source of truth when present).
        $payload = function_exists('bvmgr_sch_season_get_active_payload') ? bvmgr_sch_season_get_active_payload($venue_id) : [];
        $list = [];

        if (is_array($payload)) {
            if (isset($payload['blackout_ymd']) && is_array($payload['blackout_ymd'])) {
                $list = $payload['blackout_ymd'];
            } elseif (isset($payload['blackouts']) && is_array($payload['blackouts'])) {
                $list = $payload['blackouts'];
            } elseif (isset($payload['dates']) && is_array($payload['dates'])) {
                // Conservative: only treat as blackout when explicitly marked.
                foreach ($payload['dates'] as $k => $v) {
                    $k = (string) $k;

                    $is_marked = false;
                    if (is_array($v)) {
                        $status = isset($v['status']) ? (string) $v['status'] : '';
                        $type   = isset($v['type']) ? (string) $v['type'] : '';
                        $is_marked = (!empty($v['is_blackout']) || $status === 'blackout' || $type === 'blackout');
                    } elseif (is_string($v)) {
                        $is_marked = ((string) $v === 'blackout');
                    }

                    if ($is_marked) {
                        $list[] = $k;
                    }
                }
            }
        }

        if (is_array($list) && !empty($list)) {
            foreach ($list as $d) {
                $d = (string) $d;

                $valid = true;
                if (function_exists('bvmgr_sch_season_is_valid_ymd')) {
                    $valid = bvmgr_sch_season_is_valid_ymd($d);
                } else {
                    $valid = (bool) preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $d);
                }

                if ($valid) {
                    $map[$d] = true;
                }
            }

            $cache[$venue_id] = $map;
            return $map;
        }

        // // 2) Fallback: scan rules once, here (includes venue bucket and 0 bucket).
        // if (function_exists('vms_sch_season_get_rules')) {
        //     foreach (array($venue_id, 0) as $bid) {
        //         $rules = vms_sch_season_get_rules((int) $bid);
        //         if (!is_array($rules)) {
        //             continue;
        //         }

        //         foreach ($rules as $r) {
        //             if (!is_array($r)) {
        //                 continue;
        //             }
        //             if (($r['type'] ?? '') !== 'blackout_date') {
        //                 continue;
        //             }

        //             $enabled = $r['enabled'] ?? 1;
        //             if ($enabled === 0 || $enabled === false || (string) $enabled === '0') {
        //                 continue;
        //             }

        //             $d = isset($r['date_ymd']) ? (string) $r['date_ymd'] : '';
        //             if ($d) {
        //                 $map[$d] = true;
        //             }
        //         }
        //     }
        // }

        $cache[$venue_id] = $map;
        return $map;
    }
}

// Canonical: answer “is this date a blackout?” without duplicating logic in callers.
if (!function_exists('bvmgr_sch_season_is_blackout')) {
    function bvmgr_sch_season_is_blackout(int $venue_id, string $ymd): bool
    {
        $ymd = (string) $ymd;

        $valid = true;
        if (function_exists('bvmgr_sch_season_is_valid_ymd')) {
            $valid = bvmgr_sch_season_is_valid_ymd($ymd);
        } else {
            $valid = (bool) preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $ymd);
        }

        if (!$valid) {
            return false;
        }

        $map = function_exists('bvmgr_sch_season_get_blackout_map') ? bvmgr_sch_season_get_blackout_map($venue_id) : [];
        return isset($map[$ymd]);
    }
}


/** -------------------------
 *  Core logic
 *  ------------------------- */

function bvmgr_sch_season_is_open_by_rules(array $rules, string $ymd): bool
{
    if (!bvmgr_sch_season_is_valid_ymd($ymd)) {
        return false;
    }

    $mmdd  = substr($ymd, 5, 5);
    $dow_w = bvmgr_sch_season_dow_w($ymd); // 0..6 (Sun..Sat)

    $has_open_rule = false;
    $is_open = false;

    foreach ($rules as $r) {
        if (empty($r['enabled'])) {
            continue;
        }
        $type = $r['type'] ?? '';

        if ($type === 'open_window') {
            $has_open_rule = true;
            $start = (string) ($r['start_mmdd'] ?? '');
            $end   = (string) ($r['end_mmdd'] ?? '');

            if (!bvmgr_sch_season_is_valid_mmdd($start) || !bvmgr_sch_season_is_valid_mmdd($end)) {
                continue;
            }

            // Determine whether this date falls inside the MM-DD window (supports wrap-around seasons).
            $in_window = false;
            if ($start <= $end) {
                $in_window = ($mmdd >= $start && $mmdd <= $end);
            } else {
                $in_window = ($mmdd >= $start || $mmdd <= $end);
            }

            if (!$in_window) {
                continue;
            }

            // Optional weekday constraint: if days_w exists, enforce it.
            // Optional weekday constraint: days_w is stored as an INT bitmask (1..127). Omit/0 means all days.
            // Also accept legacy array-of-0..6 defensively.
            $mask = 0;
            if (isset($r['days_w'])) {
                if (is_array($r['days_w'])) {
                    foreach ($r['days_w'] as $d) {
                        if (!is_scalar($d)) continue;
                        $d = (int) $d;
                        if ($d < 0 || $d > 6) continue;
                        $mask |= (1 << $d);
                    }
                } else {
                    $mask = (int) $r['days_w'];
                    if ($mask < 0)   $mask = 0;
                    if ($mask > 127) $mask = 127;
                }
            }

            if ($mask > 0) {
                if ($dow_w < 0) {
                    continue;
                }
                if (!(($mask & (1 << $dow_w)) > 0)) {
                    continue;
                }
            }


            $is_open = true;
        }
    }

    // Conservative: if no open window rules exist, we treat as closed.
    if (!$has_open_rule) {
        $is_open = false;
    }

    // Blackouts override open.
    if ($is_open) {
        foreach ($rules as $r) {
            if (empty($r['enabled'])) {
                continue;
            }
            $t = (string) ($r['type'] ?? '');
            if ($t === 'blackout_date') {
                $date = (string) ($r['date_ymd'] ?? '');
                if ($date === $ymd) {
                    return false;
                }
            } elseif ($t === 'blackout_range') {
                $s = (string) ($r['start_ymd'] ?? '');
                $e = (string) ($r['end_ymd'] ?? '');
                if ($s && $e && $s <= $ymd && $ymd <= $e) {
                    return false;
                }
            }
        }
    }

    return $is_open;
}

function bvmgr_sch_season_is_blackout_by_rules(array $rules, string $ymd): bool
{
    if (!bvmgr_sch_season_is_valid_ymd($ymd)) {
        return false;
    }

    foreach ($rules as $r) {
        if (empty($r['enabled'])) {
            continue;
        }
        $t = (string) ($r['type'] ?? '');
        if ($t === 'blackout_date') {
            $date = (string) ($r['date_ymd'] ?? '');
            if ($date === $ymd) {
                return true;
            }
        } elseif ($t === 'blackout_range') {
            $s = (string) ($r['start_ymd'] ?? '');
            $e = (string) ($r['end_ymd'] ?? '');
            if ($s && $e && $s <= $ymd && $ymd <= $e) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Build a notes map for blackout dates for a given window.
 *
 * Output shape:
 *   [
 *     'YYYY-MM-DD' => ['note1', 'note2', ...],
 *   ]
 *
 * Includes venue rules and global rules (venue_id=0).
 */
if (!function_exists('bvmgr_sch_season_get_blackout_notes_map')) {
    function bvmgr_sch_season_get_blackout_notes_map(int $venue_id, string $from_ymd, string $to_ymd): array
    {
        if ($venue_id <= 0) return array();
        if (!bvmgr_sch_season_is_valid_ymd($from_ymd) || !bvmgr_sch_season_is_valid_ymd($to_ymd)) {
            return array();
        }
        if ($from_ymd > $to_ymd) {
            $tmp = $from_ymd;
            $from_ymd = $to_ymd;
            $to_ymd = $tmp;
        }

        $out = array();

        $buckets = array((int) $venue_id, 0);
        foreach ($buckets as $bid) {
            $rules = bvmgr_sch_season_get_rules((int) $bid);
            if (!is_array($rules)) continue;

            foreach ($rules as $r) {
                if (!is_array($r) || empty($r['enabled'])) continue;
                $t = (string) ($r['type'] ?? '');
                $note = bvmgr_sch_season_sanitize_note($r['note'] ?? '');

                if ($t === 'blackout_date') {
                    $d = (string) ($r['date_ymd'] ?? '');
                    if (!$d || !bvmgr_sch_season_is_valid_ymd($d)) continue;
                    if ($d < $from_ymd || $d > $to_ymd) continue;
                    if (!isset($out[$d])) $out[$d] = array();
                    if ($note !== '' && !in_array($note, $out[$d], true)) {
                        $out[$d][] = $note;
                    } elseif ($note === '' && empty($out[$d])) {
                        // Ensure a placeholder exists so the date is still flagged.
                        $out[$d][] = '';
                    }
                } elseif ($t === 'blackout_range') {
                    $s = (string) ($r['start_ymd'] ?? '');
                    $e = (string) ($r['end_ymd'] ?? '');
                    if (!$s || !$e || !bvmgr_sch_season_is_valid_ymd($s) || !bvmgr_sch_season_is_valid_ymd($e)) continue;
                    if ($s > $e) { $tmp = $s; $s = $e; $e = $tmp; }

                    // Clip to window
                    $run_from = ($s < $from_ymd) ? $from_ymd : $s;
                    $run_to   = ($e > $to_ymd)   ? $to_ymd   : $e;
                    if ($run_from > $run_to) continue;

                    $ts_from = strtotime($run_from);
                    $ts_to   = strtotime($run_to);
                    if (!$ts_from || !$ts_to) continue;

                    for ($ts = $ts_from; $ts <= $ts_to; $ts = strtotime('+1 day', $ts)) {
                        $d = gmdate('Y-m-d', $ts);
                        if (!isset($out[$d])) $out[$d] = array();
                        if ($note !== '' && !in_array($note, $out[$d], true)) {
                            $out[$d][] = $note;
                        } elseif ($note === '' && empty($out[$d])) {
                            $out[$d][] = '';
                        }
                    }
                }
            }
        }

        return $out;
    }
}

/**
 * Public API:
 * Returns true if venue is open on date, using generated dates when available for that range,
 * otherwise computing from rules.
 */
function bvmgr_sch_is_venue_open_on_date(int $venue_id, string $ymd): bool
{
    $venue_id = absint($venue_id);
    if ($venue_id <= 0) {
        return false;
    }
    if (!bvmgr_sch_season_is_valid_ymd($ymd)) {
        return false;
    }

    $payload = bvmgr_sch_season_get_active_payload($venue_id);

    $from = isset($payload['from_ymd']) ? (string) $payload['from_ymd'] : '';
    $to   = isset($payload['to_ymd']) ? (string) $payload['to_ymd'] : '';

    if ($from && $to && bvmgr_sch_season_is_valid_ymd($from) && bvmgr_sch_season_is_valid_ymd($to)) {
        if ($ymd >= $from && $ymd <= $to) {
            $map = $payload['dates_map'] ?? [];
            return is_array($map) && isset($map[$ymd]);
        }
    }

    $rules = bvmgr_sch_season_get_rules($venue_id);
    return bvmgr_sch_season_is_open_by_rules($rules, $ymd);
}

/**
 * Generate and return (but do not automatically save) the active dates payload for a venue.
 * The UI layer should call vms_sch_season_set_active_payload() only after explicit confirmation.
 */
function bvmgr_sch_season_generate_active_dates(int $venue_id, string $from_ymd, string $to_ymd): array
{
    $venue_id = absint($venue_id);

    if ($venue_id <= 0 || !bvmgr_sch_season_is_valid_ymd($from_ymd) || !bvmgr_sch_season_is_valid_ymd($to_ymd)) {
        return [
            'error' => 'invalid_input',
            'message' => 'Invalid venue or date range.',
        ];
    }

    if ($from_ymd > $to_ymd) {
        return [
            'error' => 'invalid_range',
            'message' => 'From date must be on or before To date.',
        ];
    }

    // Hard cap to prevent accidental huge generations.
    $from_ts = strtotime($from_ymd . ' 00:00:00');
    $to_ts   = strtotime($to_ymd . ' 00:00:00');
    $days = (int) floor(($to_ts - $from_ts) / DAY_IN_SECONDS) + 1;

    if ($days < 1 || $days > 800) {
        return [
            'error' => 'range_too_large',
            'message' => 'Range too large for V1 (max 800 days).',
        ];
    }

    $rules = bvmgr_sch_season_get_rules($venue_id);
    $dates_map = [];
    $blackout_map = [];

    for ($i = 0; $i < $days; $i++) {
        $ts = $from_ts + ($i * DAY_IN_SECONDS);
        $ymd = gmdate('Y-m-d', $ts);

        // Record blackouts explicitly.
        if (bvmgr_sch_season_is_blackout_by_rules($rules, $ymd)) {
            $blackout_map[$ymd] = 1;
            continue;
        }

        // Record open dates.
        if (bvmgr_sch_season_is_open_by_rules($rules, $ymd)) {
            $dates_map[$ymd] = 1;
        }
    }

    $payload = [
        'from_ymd'     => $from_ymd,
        'to_ymd'       => $to_ymd,
        'generated_at' => current_time('mysql'),
        'rules_hash'   => bvmgr_sch_season_rules_hash($rules),
        'dates_map'    => $dates_map,

        // New: explicit blackout storage (canonical).
        'blackout_ymd' => array_keys($blackout_map),
    ];

    sort($payload['blackout_ymd']);

    return $payload;
}
