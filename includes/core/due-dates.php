<?php
/**
 * Due Dates / Compliance Obligations (procedural)
 *
 * Storage:
 * - wp_options
 *   - BVMGR_OPT_DUE_PAYEES      (vms_due_payees_v1)
 *   - BVMGR_OPT_DUE_OBLIGATIONS (vms_due_obligations_v1)
 *   - BVMGR_OPT_DUE_LOG         (vms_due_log_v1)
 *
 * Design goals:
 * - Operator-friendly reminders (dashboard)
 * - Separate Payee entity (not Vendors)
 * - Append-only completion log (audit trail)
 * - Reversible: obligations/payees are archived, not hard-deleted
 * Obligation range fields:
 * - start_date (YYYY-MM-DD, site timezone): first day the obligation becomes active
 * - end_date   (YYYY-MM-DD, optional): last day occurrences may be generated
 *
 * Goal: a newly created obligation must not imply "overdue history" before its start_date.

 */

defined('ABSPATH') || exit;

if (!function_exists('vms_due_opt')) {
  function vms_due_opt(string $const_or_key): string {
    // Allow constants, but fall back to provided string.
    if (defined($const_or_key)) {
      $v = constant($const_or_key);
      if (is_string($v) && $v !== '') return $v;
    }
    return $const_or_key;
  }
}

if (!function_exists('vms_due_new_id')) {
  function vms_due_new_id(string $prefix): string {
    $prefix = sanitize_key($prefix);
    if ($prefix === '') $prefix = 'id';
    $uuid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('', true);
    $uuid = preg_replace('/[^a-z0-9\-]/i', '', (string) $uuid);
    return $prefix . '_' . strtolower($uuid);
  }
}

if (!function_exists('vms_due_get_payees')) {
  function vms_due_get_payees(): array {
    $key = vms_due_opt('BVMGR_OPT_DUE_PAYEES');
    $raw = get_option($key, null);
    if (is_array($raw)) return $raw;

    // Back-compat: older builds accidentally stored under the literal
    // constant name instead of the resolved option key.
    $legacy_key = 'BVMGR_OPT_DUE_PAYEES';
    if ($legacy_key !== $key) {
      $legacy = get_option($legacy_key, null);
      if (is_array($legacy) && !empty($legacy)) {
        update_option($key, $legacy, false);
        return $legacy;
      }
    }

    return [];
  }
}

if (!function_exists('vms_due_save_payees')) {
  function vms_due_save_payees(array $payees): bool {
    return update_option(vms_due_opt('BVMGR_OPT_DUE_PAYEES'), $payees, false);
  }
}

if (!function_exists('vms_due_get_obligations')) {
  function vms_due_get_obligations(): array {
    $key = vms_due_opt('BVMGR_OPT_DUE_OBLIGATIONS');
    $raw = get_option($key, null);
    if (is_array($raw)) return $raw;

    $legacy_key = 'BVMGR_OPT_DUE_OBLIGATIONS';
    if ($legacy_key !== $key) {
      $legacy = get_option($legacy_key, null);
      if (is_array($legacy) && !empty($legacy)) {
        update_option($key, $legacy, false);
        return $legacy;
      }
    }

    return [];
  }
}

if (!function_exists('vms_due_save_obligations')) {
  function vms_due_save_obligations(array $obligations): bool {
    return update_option(vms_due_opt('BVMGR_OPT_DUE_OBLIGATIONS'), $obligations, false);
  }
}

if (!function_exists('vms_due_get_log')) {
  function vms_due_get_log(): array {
    $key = vms_due_opt('BVMGR_OPT_DUE_LOG');
    $raw = get_option($key, null);
    if (is_array($raw)) return $raw;

    $legacy_key = 'BVMGR_OPT_DUE_LOG';
    if ($legacy_key !== $key) {
      $legacy = get_option($legacy_key, null);
      if (is_array($legacy) && !empty($legacy)) {
        update_option($key, $legacy, false);
        return $legacy;
      }
    }

    return [];
  }
}

if (!function_exists('vms_due_append_log')) {
  function vms_due_append_log(array $entry): bool {
    $log = vms_due_get_log();
    $log[] = $entry;
    return update_option(vms_due_opt('BVMGR_OPT_DUE_LOG'), $log, false);
  }
}

if (!function_exists('vms_due_log_index')) {
  function vms_due_log_index(array $log): array {
    $idx = [];
    foreach ($log as $row) {
      if (!is_array($row)) continue;
      $oid = isset($row['obligation_id']) ? sanitize_key((string) $row['obligation_id']) : '';
      $due = isset($row['due_date']) ? sanitize_text_field((string) $row['due_date']) : '';
      if ($oid === '' || $due === '') continue;
      $action = sanitize_key((string) ($row['action'] ?? ''));
      if ($action !== 'uncomplete') {
        $action = 'complete';
      }

      $k = $oid . '|' . $due;
      if ($action === 'uncomplete') {
        unset($idx[$k]);
      } else {
        $idx[$k] = true;
      }
    }
    return $idx;
  }
}

if (!function_exists('vms_due_is_completed')) {
  function vms_due_is_completed(string $obligation_id, string $due_date, array $log_index): bool {
    $k = sanitize_key($obligation_id) . '|' . sanitize_text_field($due_date);
    return !empty($log_index[$k]);
  }
}

if (!function_exists('vms_due_dt')) {
  function vms_due_dt(string $ymd, DateTimeZone $tz): ?DateTimeImmutable {
    $ymd = sanitize_text_field($ymd);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) return null;
    try {
      return new DateTimeImmutable($ymd . ' 00:00:00', $tz);
    } catch (Exception $e) {
      return null;
    }
  }
}


if (!function_exists('vms_due_norm_ymd')) {
  function vms_due_norm_ymd(string $ymd): string {
    $ymd = sanitize_text_field($ymd);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) ? $ymd : '';
  }
}

if (!function_exists('vms_due_dt_max')) {
  function vms_due_dt_max(DateTimeImmutable $a, DateTimeImmutable $b): DateTimeImmutable {
    return ($a >= $b) ? $a : $b;
  }
}

if (!function_exists('vms_due_dt_min')) {
  function vms_due_dt_min(DateTimeImmutable $a, DateTimeImmutable $b): DateTimeImmutable {
    return ($a <= $b) ? $a : $b;
  }
}

if (!function_exists('vms_due_obligation_effective_range')) {
  function vms_due_obligation_effective_range(array $ob, DateTimeImmutable $win_start, DateTimeImmutable $win_end, DateTimeImmutable $today): ?array {
    $tz = wp_timezone();

    $cadence = isset($ob['cadence']) ? sanitize_key((string) $ob['cadence']) : 'monthly';

    $start_ymd = vms_due_norm_ymd((string) ($ob['start_date'] ?? ''));
    $end_ymd   = vms_due_norm_ymd((string) ($ob['end_date'] ?? ''));

    $start_dt = ($start_ymd !== '') ? vms_due_dt($start_ymd, $tz) : null;
    $end_dt   = ($end_ymd !== '') ? vms_due_dt($end_ymd, $tz) : null;

    $due_dt = null;
    if ($cadence === 'one_time') {
      $due_ymd = vms_due_norm_ymd((string) ($ob['due_date'] ?? ''));
      if ($due_ymd !== '') {
        $due_dt = vms_due_dt($due_ymd, $tz);
      }
    }

    // Fallback start_date:
    // - one_time: use due_date if available (so overdue one-time items can surface)
    // - recurring: use today to avoid implied overdue history for newly created obligations
    if (!$start_dt) {
      $start_dt = ($cadence === 'one_time' && $due_dt) ? $due_dt : $today;
    }

    if ($end_dt && $end_dt < $start_dt) {
      $end_dt = null;
    }

    // Window intersected with obligation's active range.
    $eff_start = vms_due_dt_max($win_start, $start_dt);
    $eff_end   = $win_end;

    if ($end_dt) {
      $eff_end = vms_due_dt_min($eff_end, $end_dt);
    }

    // One-time obligations can be important even if overdue beyond the standard overdue window.
    // Allow a longer lookback, but still clamp to a safe maximum.
    if ($cadence === 'one_time' && $due_dt) {
      $max_back = $today->modify('-1825 days'); // 5 years
      if ($eff_start < $max_back) {
        $eff_start = $max_back;
      }
      // If due_dt is within the allowed window and within the obligation range, ensure it can be generated.
      if ($due_dt >= $start_dt && $due_dt <= $eff_end && $due_dt < $eff_start) {
        $eff_start = $due_dt;
      }
    }

    if ($eff_end < $eff_start) {
      return null;
    }

    return [$eff_start, $eff_end];
  }
}
if (!function_exists('vms_due_clamp_dom')) {
  function vms_due_clamp_dom(int $year, int $month, int $day): int {
    $day = max(1, min(31, $day));
    $month = max(1, min(12, $month));
    $last = (int) cal_days_in_month(CAL_GREGORIAN, $month, $year);
    return min($day, $last);
  }
}



if (!function_exists('vms_due_last_dom')) {
  function vms_due_last_dom(int $year, int $month): int {
    $month = max(1, min(12, $month));
    return (int) cal_days_in_month(CAL_GREGORIAN, $month, $year);
  }
}

if (!function_exists('vms_due_iter_months')) {
  function vms_due_iter_months(DateTimeImmutable $start, DateTimeImmutable $end, DateTimeZone $tz): array {
    // Returns array of [Y, m] for each month that intersects the range.
    $out = [];
    $cur = new DateTimeImmutable($start->format('Y-m-01') . ' 00:00:00', $tz);
    $endMonth = new DateTimeImmutable($end->format('Y-m-01') . ' 00:00:00', $tz);

    while ($cur <= $endMonth) {
      $out[] = [(int) $cur->format('Y'), (int) $cur->format('n')];
      $cur = $cur->modify('+1 month');
    }

    return $out;
  }
}

if (!function_exists('vms_due_generate_occurrences')) {
  function vms_due_generate_occurrences(array $ob, DateTimeImmutable $start, DateTimeImmutable $end): array {
    $tz = wp_timezone();

    $cadence = isset($ob['cadence']) ? sanitize_key((string) $ob['cadence']) : 'monthly';
    $day = isset($ob['day']) ? (int) $ob['day'] : 1;
    $month = isset($ob['month']) ? (int) $ob['month'] : 1;
    $q_month = isset($ob['quarter_month']) ? (int) $ob['quarter_month'] : 1; // 1..3 within quarter

    $eom = !empty($ob['eom']);

    $out = [];

    if ($cadence === 'one_time') {
      $ymd = isset($ob['due_date']) ? (string) $ob['due_date'] : '';
      $dt = vms_due_dt($ymd, $tz);
      if ($dt && $dt >= $start && $dt <= $end) {
        $out[] = $dt->format('Y-m-d');
      }
      return $out;
    }

    if ($cadence === 'annual') {
      $month = max(1, min(12, $month));
      $years = range((int) $start->format('Y'), (int) $end->format('Y'));
      foreach ($years as $y) {
        $dom = $eom ? vms_due_last_dom($y, $month) : vms_due_clamp_dom($y, $month, $day);
        $dt = new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $y, $month, $dom), $tz);
        if ($dt >= $start && $dt <= $end) {
          $out[] = $dt->format('Y-m-d');
        }
      }
      return $out;
    }

    if ($cadence === 'quarterly') {
      $q_month = max(1, min(3, $q_month));
      $years = range((int) $start->format('Y'), (int) $end->format('Y'));
      foreach ($years as $y) {
        // Standard quarters: Jan/Apr/Jul/Oct.
        foreach ([1, 4, 7, 10] as $qStart) {
          $m = $qStart + ($q_month - 1);
          $dom = $eom ? vms_due_last_dom($y, $m) : vms_due_clamp_dom($y, $m, $day);
          $dt = new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $y, $m, $dom), $tz);
          if ($dt >= $start && $dt <= $end) {
            $out[] = $dt->format('Y-m-d');
          }
        }
      }
      sort($out);
      return $out;
    }

    // Default: monthly
    $months = vms_due_iter_months($start, $end, $tz);
    foreach ($months as $pair) {
      $y = (int) $pair[0];
      $m = (int) $pair[1];
      $dom = $eom ? vms_due_last_dom($y, $m) : vms_due_clamp_dom($y, $m, $day);
      $dt = new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $y, $m, $dom), $tz);
      if ($dt >= $start && $dt <= $end) {
        $out[] = $dt->format('Y-m-d');
      }
    }

    return $out;
  }
}

if (!function_exists('vms_due_build_dashboard_items')) {
  function vms_due_build_dashboard_items(int $span_days = 30): array {
    $tz = wp_timezone();
    $today = new DateTimeImmutable('today', $tz);

    $span_days = max(1, min(365, (int) $span_days));

    // Include a reasonable overdue window so we can surface missed items without infinite history.
    $overdue_days = 120;

    // Overdue lookback is intentionally bounded so recurring obligations do not backfill years of implied missed payments.
    $start = $today->modify('-' . $overdue_days . ' days');
    $end = $today->modify('+' . $span_days . ' days');

    $payees = vms_due_get_payees();
    $obligations = vms_due_get_obligations();
    $log = vms_due_get_log();
    $log_idx = vms_due_log_index($log);

    $items = [];
    foreach ($obligations as $oid => $ob) {
      if (!is_array($ob)) continue;
      $oid = sanitize_key((string) ($ob['id'] ?? $oid));
      if ($oid === '') continue;

      $active = isset($ob['is_active']) ? (int) $ob['is_active'] : 1;
      if ($active !== 1) continue;

      $title = isset($ob['title']) ? sanitize_text_field((string) $ob['title']) : '';
      if ($title === '') $title = '(untitled obligation)';

      $payee_id = isset($ob['payee_id']) ? sanitize_key((string) $ob['payee_id']) : '';

      // Payee requirement (defaults to required for safety).
      // Storage field name: payee_required (1 required, 0 optional).
      $payee_required = 1;
      if (array_key_exists('payee_required', $ob)) {
        $payee_required = (int) $ob['payee_required'] ? 1 : 0;
      }

      $payee = ($payee_id !== '' && isset($payees[$payee_id]) && is_array($payees[$payee_id])) ? $payees[$payee_id] : null;
      $payee_name = $payee && isset($payee['name']) ? sanitize_text_field((string) $payee['name']) : '(no payee)';

      $first_ident = '';
      if ($payee) {
        $acct_num = isset($payee['account_number']) ? sanitize_text_field((string) $payee['account_number']) : '';
        $acct_id  = isset($payee['account_id']) ? sanitize_text_field((string) $payee['account_id']) : '';
        if ($acct_num !== '') {
          $first_ident = 'Acct#: ' . $acct_num;
        } elseif ($acct_id !== '') {
          $first_ident = 'Acct ID: ' . $acct_id;
        }
      }

      if ($first_ident === '') {
        $idents = [];
        if ($payee && isset($payee['identifiers']) && is_array($payee['identifiers'])) {
          $idents = $payee['identifiers'];
        }
        if (!empty($idents) && is_array($idents[0])) {
          $lab = isset($idents[0]['label']) ? sanitize_text_field((string) $idents[0]['label']) : '';
          $val = isset($idents[0]['value']) ? sanitize_text_field((string) $idents[0]['value']) : '';
          if ($lab !== '' && $val !== '') {
            $first_ident = $lab . ': ' . $val;
          } elseif ($val !== '') {
            $first_ident = $val;
          }
        }
      }

      $remind_days = isset($ob['remind_days']) ? (int) $ob['remind_days'] : 14;
      $remind_days = max(0, min(365, $remind_days));

      $range = vms_due_obligation_effective_range($ob, $start, $end, $today);
      if (!$range || !is_array($range) || count($range) !== 2) {
        continue;
      }
      $rstart = $range[0];
      $rend   = $range[1];

      $occ = vms_due_generate_occurrences($ob, $rstart, $rend);
      foreach ($occ as $due_ymd) {
        if (vms_due_is_completed($oid, $due_ymd, $log_idx)) {
          continue;
        }

        $due_dt = vms_due_dt($due_ymd, $tz);
        if (!$due_dt) continue;

        $days_until = (int) floor(($due_dt->getTimestamp() - $today->getTimestamp()) / 86400);

        $status = 'upcoming';
        if ($days_until < 0) {
          $status = 'overdue';
        } elseif ($remind_days > 0 && $days_until <= $remind_days) {
          $status = 'due_soon';
        }

        $items[] = [
          'obligation_id' => $oid,
          'title' => $title,
          'payee_id' => $payee_id,
          'payee_name' => $payee_name,
          'due_date' => $due_ymd,
          'days_until' => $days_until,
          'status' => $status,
          'identifier' => $first_ident,
          // Needs attention only when a payee is required but missing, or when a payee id is set but cannot be resolved.
          'needs_attention' => (($payee_required === 1 && $payee_id === '') || ($payee_id !== '' && !$payee)),
        ];
      }
    }
    // Sort + prioritize: overdue items first, then upcoming.
    $overdue = [];
    $other = [];

    foreach ($items as $it) {
      if (!is_array($it)) continue;
      if (($it['status'] ?? '') === 'overdue') {
        $overdue[] = $it;
      } else {
        $other[] = $it;
      }
    }

    $sort_by_due_asc = function ($a, $b) {
      $ad = (string)($a['due_date'] ?? '');
      $bd = (string)($b['due_date'] ?? '');
      if ($ad === $bd) {
        return strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
      }
      return strcmp($ad, $bd);
    };

    $sort_by_due_desc = function ($a, $b) {
      $ad = (string)($a['due_date'] ?? '');
      $bd = (string)($b['due_date'] ?? '');
      if ($ad === $bd) {
        return strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
      }
      return strcmp($bd, $ad);
    };

    usort($overdue, $sort_by_due_desc);
    usort($other, $sort_by_due_asc);

    // Limit for dashboard readability, but do not let a long overdue history
    // completely push out upcoming items.
    $max_total = 50;
    $max_overdue = 25;

    $out = array_slice($overdue, 0, $max_overdue);
    $remaining = max(0, $max_total - count($out));
    if ($remaining > 0) {
      $out = array_merge($out, array_slice($other, 0, $remaining));
    }

    return $out;
  }
}

if (!function_exists('vms_due_dashboard_summary')) {
  function vms_due_dashboard_summary(array $items): array {
    $overdue = 0;
    $d7 = 0;
    $d14 = 0;
    $d30 = 0;
    $attn = 0;

    foreach ($items as $it) {
      if (!is_array($it)) continue;
      $days = isset($it['days_until']) ? (int) $it['days_until'] : 9999;
      if (!empty($it['needs_attention'])) $attn++;

      if ($days < 0) {
        $overdue++;
      } elseif ($days <= 7) {
        $d7++;
      } elseif ($days <= 14) {
        $d14++;
      } elseif ($days <= 30) {
        $d30++;
      }
    }

    return [
      'overdue' => $overdue,
      'due_7' => $d7,
      'due_14' => $d14,
      'due_30' => $d30,
      'needs_attention' => $attn,
    ];
  }
}



// Back-compat alias used by dashboard response builder.
// (The canonical helper is vms_due_dashboard_summary().)
if (!function_exists('vms_due_summary_counts')) {
  function vms_due_summary_counts(array $items): array {
    return vms_due_dashboard_summary($items);
  }
}

if (!function_exists('vms_due_norm_list_status_filter')) {
  function vms_due_norm_list_status_filter(string $status): string {
    $status = sanitize_key($status);
    $allowed = ['open', 'completed', 'overdue', 'due_soon', 'upcoming', 'all'];
    return in_array($status, $allowed, true) ? $status : 'open';
  }
}

if (!function_exists('vms_due_norm_list_cadence_filter')) {
  function vms_due_norm_list_cadence_filter(string $cadence): string {
    $cadence = sanitize_key($cadence);
    $allowed = ['all', 'monthly', 'quarterly', 'annual', 'one_time'];
    return in_array($cadence, $allowed, true) ? $cadence : 'all';
  }
}

if (!function_exists('vms_due_norm_list_payee_filter')) {
  function vms_due_norm_list_payee_filter(string $payee_id): string {
    $payee_id = sanitize_text_field($payee_id);
    if ($payee_id === '' || $payee_id === 'all') return 'all';
    if ($payee_id === 'none') return 'none';
    $payee_id = sanitize_key($payee_id);
    return ($payee_id !== '') ? $payee_id : 'all';
  }
}

if (!function_exists('vms_due_list_status_matches_filter')) {
  function vms_due_list_status_matches_filter(string $item_status, string $status_filter): bool {
    $item_status = sanitize_key($item_status);
    $status_filter = vms_due_norm_list_status_filter($status_filter);

    if ($status_filter === 'all') {
      return true;
    }
    if ($status_filter === 'open') {
      return $item_status !== 'completed';
    }

    return $item_status === $status_filter;
  }
}

if (!function_exists('vms_due_build_obligations_list_response')) {
  /**
   * Build due-instance list with deterministic filters + stable ordering.
   *
   * Args:
   * - status: open|completed|overdue|due_soon|upcoming|all (default open)
   * - cadence: all|monthly|quarterly|annual|one_time (default all)
   * - payee_id: all|none|{payee_id} (default all)
   * - include_archived: bool (default false)
   * - lookback_days: 0..1825 (default 120)
   * - lookahead_days: 1..1825 (default 120)
   * - limit: 1..1000 (default 500)
   */
  function vms_due_build_obligations_list_response(array $args = []): array {
    $tz = wp_timezone();
    $today = new DateTimeImmutable('today', $tz);

    $status_filter = vms_due_norm_list_status_filter((string) ($args['status'] ?? 'open'));
    $cadence_filter = vms_due_norm_list_cadence_filter((string) ($args['cadence'] ?? 'all'));
    $payee_filter = vms_due_norm_list_payee_filter((string) ($args['payee_id'] ?? 'all'));
    $include_archived = !empty($args['include_archived']);

    $lookback_days = isset($args['lookback_days']) ? (int) $args['lookback_days'] : 120;
    $lookahead_days = isset($args['lookahead_days']) ? (int) $args['lookahead_days'] : 120;
    $limit = isset($args['limit']) ? (int) $args['limit'] : 500;

    $lookback_days = max(0, min(1825, $lookback_days));
    $lookahead_days = max(1, min(1825, $lookahead_days));
    $limit = max(1, min(1000, $limit));

    $start = $today->modify('-' . $lookback_days . ' days');
    $end = $today->modify('+' . $lookahead_days . ' days');

    $payees = vms_due_get_payees();
    $obligations = vms_due_get_obligations();
    $log_idx = vms_due_log_index(vms_due_get_log());

    $counts = [
      'open' => 0,
      'completed' => 0,
      'overdue' => 0,
      'due_soon' => 0,
      'upcoming' => 0,
    ];

    $active_obligations = 0;
    $items = [];

    foreach ($obligations as $oid => $ob) {
      if (!is_array($ob)) continue;
      $oid = sanitize_key((string) ($ob['id'] ?? $oid));
      if ($oid === '') continue;

      $is_active = !empty($ob['is_active']);
      if ($is_active) {
        $active_obligations++;
      }
      if (!$include_archived && !$is_active) {
        continue;
      }

      $cadence = sanitize_key((string) ($ob['cadence'] ?? 'monthly'));
      if (!in_array($cadence, ['monthly', 'quarterly', 'annual', 'one_time'], true)) {
        $cadence = 'monthly';
      }
      if ($cadence_filter !== 'all' && $cadence !== $cadence_filter) {
        continue;
      }

      $payee_id = sanitize_key((string) ($ob['payee_id'] ?? ''));
      if ($payee_filter === 'none' && $payee_id !== '') {
        continue;
      }
      if ($payee_filter !== 'all' && $payee_filter !== 'none' && $payee_id !== $payee_filter) {
        continue;
      }

      $title = sanitize_text_field((string) ($ob['title'] ?? ''));
      if ($title === '') {
        $title = '(untitled obligation)';
      }

      $payee_required = 1;
      if (array_key_exists('payee_required', $ob)) {
        $payee_required = (int) $ob['payee_required'] ? 1 : 0;
      }

      $payee = ($payee_id !== '' && isset($payees[$payee_id]) && is_array($payees[$payee_id])) ? $payees[$payee_id] : null;
      $payee_name = $payee && isset($payee['name']) ? sanitize_text_field((string) $payee['name']) : '(no payee)';

      $first_ident = '';
      if ($payee) {
        $acct_num = isset($payee['account_number']) ? sanitize_text_field((string) $payee['account_number']) : '';
        $acct_id  = isset($payee['account_id']) ? sanitize_text_field((string) $payee['account_id']) : '';
        if ($acct_num !== '') {
          $first_ident = 'Acct#: ' . $acct_num;
        } elseif ($acct_id !== '') {
          $first_ident = 'Acct ID: ' . $acct_id;
        }
      }
      if ($first_ident === '') {
        $idents = [];
        if ($payee && isset($payee['identifiers']) && is_array($payee['identifiers'])) {
          $idents = $payee['identifiers'];
        }
        if (!empty($idents) && is_array($idents[0])) {
          $lab = isset($idents[0]['label']) ? sanitize_text_field((string) $idents[0]['label']) : '';
          $val = isset($idents[0]['value']) ? sanitize_text_field((string) $idents[0]['value']) : '';
          if ($lab !== '' && $val !== '') {
            $first_ident = $lab . ': ' . $val;
          } elseif ($val !== '') {
            $first_ident = $val;
          }
        }
      }

      $remind_days = isset($ob['remind_days']) ? (int) $ob['remind_days'] : 14;
      $remind_days = max(0, min(365, $remind_days));

      $range = vms_due_obligation_effective_range($ob, $start, $end, $today);
      if (!$range || !is_array($range) || count($range) !== 2) {
        continue;
      }
      $rstart = $range[0];
      $rend = $range[1];

      $occurrences = vms_due_generate_occurrences($ob, $rstart, $rend);
      foreach ($occurrences as $due_ymd) {
        $due_dt = vms_due_dt($due_ymd, $tz);
        if (!$due_dt) continue;

        $days_until = (int) floor(($due_dt->getTimestamp() - $today->getTimestamp()) / 86400);
        $is_completed = vms_due_is_completed($oid, $due_ymd, $log_idx);

        $status = 'upcoming';
        if ($is_completed) {
          $status = 'completed';
        } elseif ($days_until < 0) {
          $status = 'overdue';
        } elseif ($remind_days > 0 && $days_until <= $remind_days) {
          $status = 'due_soon';
        }

        if ($status !== 'completed') {
          $counts['open']++;
        }
        if (isset($counts[$status])) {
          $counts[$status]++;
        }

        if (!vms_due_list_status_matches_filter($status, $status_filter)) {
          continue;
        }

        $items[] = [
          'obligation_id' => $oid,
          'title' => $title,
          'payee_id' => $payee_id,
          'payee_name' => $payee_name,
          'cadence' => $cadence,
          'due_date' => $due_ymd,
          'days_until' => $days_until,
          'status' => $status,
          'is_completed' => $is_completed ? 1 : 0,
          'is_active' => $is_active ? 1 : 0,
          'identifier' => $first_ident,
          'needs_attention' => (($payee_required === 1 && $payee_id === '') || ($payee_id !== '' && !$payee)),
        ];
      }
    }

    usort($items, function ($a, $b) {
      $rank = ['overdue' => 0, 'due_soon' => 1, 'upcoming' => 2, 'completed' => 3];
      $as = sanitize_key((string) ($a['status'] ?? 'upcoming'));
      $bs = sanitize_key((string) ($b['status'] ?? 'upcoming'));
      $ar = isset($rank[$as]) ? (int) $rank[$as] : 99;
      $br = isset($rank[$bs]) ? (int) $rank[$bs] : 99;
      if ($ar !== $br) {
        return $ar <=> $br;
      }

      $ad = (string) ($a['due_date'] ?? '');
      $bd = (string) ($b['due_date'] ?? '');
      if ($as === 'overdue') {
        if ($ad !== $bd) return strcmp($bd, $ad);
      } else {
        if ($ad !== $bd) return strcmp($ad, $bd);
      }

      $at = (string) ($a['title'] ?? '');
      $bt = (string) ($b['title'] ?? '');
      if ($at !== $bt) {
        return strcmp($at, $bt);
      }

      return strcmp((string) ($a['obligation_id'] ?? ''), (string) ($b['obligation_id'] ?? ''));
    });

    $total_items = count($items);
    $truncated = false;
    if ($total_items > $limit) {
      $items = array_slice($items, 0, $limit);
      $truncated = true;
    }

    return [
      'filters' => [
        'status' => $status_filter,
        'cadence' => $cadence_filter,
        'payee_id' => $payee_filter,
        'include_archived' => $include_archived ? 1 : 0,
      ],
      'window' => [
        'start_ymd' => $start->format('Y-m-d'),
        'end_ymd' => $end->format('Y-m-d'),
        'lookback_days' => $lookback_days,
        'lookahead_days' => $lookahead_days,
      ],
      'active_obligations' => $active_obligations,
      'counts' => $counts,
      'total_items' => $total_items,
      'truncated' => $truncated ? 1 : 0,
      'items' => $items,
    ];
  }
}

if (!function_exists('vms_due_validate_log_target')) {
  /**
   * Validate obligation + due_date pair for completion/uncompletion transitions.
   */
  function vms_due_validate_log_target(string $obligation_id, string $due_date): array {
    $obligation_id = sanitize_key($obligation_id);
    $due_date = sanitize_text_field($due_date);

    if ($obligation_id === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
      return ['ok' => false, 'error' => 'invalid_input'];
    }

    $obligations = vms_due_get_obligations();
    if (empty($obligations[$obligation_id]) || !is_array($obligations[$obligation_id])) {
      return ['ok' => false, 'error' => 'obligation_not_found'];
    }

    $ob = $obligations[$obligation_id];

    // Validate that this due_date belongs to this obligation and is within the obligation's active range.
    $tz = wp_timezone();
    $due_dt = vms_due_dt($due_date, $tz);
    if (!$due_dt) {
      return ['ok' => false, 'error' => 'invalid_input'];
    }

    $cadence = isset($ob['cadence']) ? sanitize_key((string) $ob['cadence']) : 'monthly';

    $start_dt = null;
    $start_ymd = vms_due_norm_ymd((string) ($ob['start_date'] ?? ''));
    if ($start_ymd !== '') {
      $start_dt = vms_due_dt($start_ymd, $tz);
    }
    if (!$start_dt) {
      $today = new DateTimeImmutable('today', $tz);
      $start_dt = ($cadence === 'one_time') ? $due_dt : $today;
    }

    $end_dt = null;
    $end_ymd = vms_due_norm_ymd((string) ($ob['end_date'] ?? ''));
    if ($end_ymd !== '') {
      $end_dt = vms_due_dt($end_ymd, $tz);
    }
    if ($end_dt && $end_dt < $start_dt) {
      $end_dt = null;
    }

    if ($due_dt < $start_dt) {
      return ['ok' => false, 'error' => 'due_before_start_date'];
    }
    if ($end_dt && $due_dt > $end_dt) {
      return ['ok' => false, 'error' => 'due_after_end_date'];
    }

    // Make sure this due_date matches the cadence rule (prevents arbitrary log entries).
    $matches = vms_due_generate_occurrences($ob, $due_dt, $due_dt);
    if (!in_array($due_date, $matches, true)) {
      return ['ok' => false, 'error' => 'due_date_not_valid_for_obligation'];
    }

    return ['ok' => true, 'obligation' => $ob];
  }
}

if (!function_exists('vms_due_safe_complete')) {
  function vms_due_safe_complete(string $obligation_id, string $due_date, string $notes = '', string $proof_url = ''): array {
    $obligation_id = sanitize_key($obligation_id);
    $due_date = sanitize_text_field($due_date);
    $notes = sanitize_textarea_field($notes);
    $proof_url = esc_url_raw($proof_url);

    $valid = vms_due_validate_log_target($obligation_id, $due_date);
    if (empty($valid['ok'])) {
      return $valid;
    }

    $log = vms_due_get_log();
    $idx = vms_due_log_index($log);
    if (vms_due_is_completed($obligation_id, $due_date, $idx)) {
      return ['ok' => true, 'already' => true];
    }

    $entry = [
      'ts' => time(),
      'user_id' => get_current_user_id(),
      'obligation_id' => $obligation_id,
      'due_date' => $due_date,
      'action' => 'complete',
      'notes' => $notes,
      'proof_url' => $proof_url,
    ];

    $ok = vms_due_append_log($entry);
    return ['ok' => (bool) $ok, 'already' => false];
  }
}

if (!function_exists('vms_due_safe_uncomplete')) {
  function vms_due_safe_uncomplete(string $obligation_id, string $due_date, string $notes = '', string $proof_url = ''): array {
    $obligation_id = sanitize_key($obligation_id);
    $due_date = sanitize_text_field($due_date);
    $notes = sanitize_textarea_field($notes);
    $proof_url = esc_url_raw($proof_url);

    if ($obligation_id === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
      return ['ok' => false, 'error' => 'invalid_input'];
    }

    $obligations = vms_due_get_obligations();
    if (empty($obligations[$obligation_id]) || !is_array($obligations[$obligation_id])) {
      return ['ok' => false, 'error' => 'obligation_not_found'];
    }

    $log = vms_due_get_log();
    $idx = vms_due_log_index($log);
    if (!vms_due_is_completed($obligation_id, $due_date, $idx)) {
      return ['ok' => true, 'already' => true];
    }

    $entry = [
      'ts' => time(),
      'user_id' => get_current_user_id(),
      'obligation_id' => $obligation_id,
      'due_date' => $due_date,
      'action' => 'uncomplete',
      'notes' => $notes,
      'proof_url' => $proof_url,
    ];

    $ok = vms_due_append_log($entry);
    return ['ok' => (bool) $ok, 'already' => false];
  }
}

if (!function_exists('vms_due_build_dashboard_response')) {
  function vms_due_build_dashboard_response(int $span_days = 30): array {
    $tz = wp_timezone();
    $today = new DateTimeImmutable('today', $tz);

    $span_days = max(1, min(365, (int) $span_days));

    $overdue_days = 120;
    $start = $today->modify('-' . $overdue_days . ' days');
    $end = $today->modify('+' . $span_days . ' days');

    $items = vms_due_build_dashboard_items($span_days);
    $counts = vms_due_summary_counts($items);

    // How many active obligations exist (helps render empty-state copy).
    $obs = vms_due_get_obligations();
    $active = 0;
    foreach ($obs as $ob) {
      if (!is_array($ob)) continue;
      if (!empty($ob['is_active'])) $active++;
    }

    return [
      'range' => 'due',
      'range_start' => $start->format('Y-m-d H:i:s'),
      'range_end' => $end->format('Y-m-d H:i:s'),
      'overdue_window_days' => $overdue_days,
      'lookahead_days' => $span_days,
      'active_obligations' => $active,
      'counts' => $counts,
      'items' => $items,
    ];
  }
}
