<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/core/tax-bypass.php';

require_once __DIR__ . '/schedule/helpers.php';
require_once __DIR__ . '/schedule/schedule.php';

// BOTH?
if (!function_exists('bvmgr_sch_get_all_venue_ids')) {
  $path = __DIR__ . '/schedule/helpers.php';
  if (file_exists($path)) {
    require_once $path;
  }
}

add_action('rest_api_init', function () {
  register_rest_route('vms/v1', '/dashboard', [
    'methods'             => 'GET',
    'permission_callback' => function () {
      return current_user_can('manage_options');
    },
    'callback'            => 'bvmgr_dashboard_rest_get',
    'args'                => [
      'range' => [
        'required' => true,
        'sanitize_callback' => 'sanitize_text_field',
      ],
      'venue_id' => [
        'required' => false,
        'sanitize_callback' => 'absint',
        'default' => 0, // 0 = all venues
      ],
      'include_canceled' => [
        'required' => false,
        'sanitize_callback' => 'absint',
        'default' => 0,
      ],
      'only_open' => [
        'required' => false,
        'sanitize_callback' => 'absint',
        'default' => 0,
      ],
      'include_drafts' => [
        'required' => false,
        'sanitize_callback' => 'absint',
        'default' => 0,
      ],
      'staffing_n' => [
        'required' => false,
        'sanitize_callback' => 'absint',
        'default' => 10,
      ],
      'staffing_include_drafts' => [
        'required' => false,
        'sanitize_callback' => 'absint',
        'default' => 0,
      ],
    ],
  ]);
});

function bvmgr_dashboard_get_due_span_days(): int
{
  // Reuse dashboard week span semantics so Due Dates and Financial Snapshot align.
  $span_raw = get_option('vms_dash_week_span', 1);
  $span_int = (int) $span_raw;
  if ($span_int >= 14 || $span_int === 2) {
    return 14;
  }
  return 7;
}


function bvmgr_dashboard_rest_get(WP_REST_Request $req)
{

  // ---------- Inputs (sanitized / normalized) ----------
  $range = sanitize_key((string) $req->get_param('range'));
  if ($range !== 'today' && $range !== 'week' && $range !== 'bills' && $range !== 'due' && $range !== 'financial' && $range !== 'staffing') {
    $range = 'week';
  }

  $venue_id_int = absint($req->get_param('venue_id')); // 0 = all venues
  $venue_id     = ($venue_id_int === 0) ? 'all' : (string) $venue_id_int;

  $include_canceled = absint($req->get_param('include_canceled'));
  $only_open        = absint($req->get_param('only_open'));
  $include_drafts   = absint($req->get_param('include_drafts'));
  $staffing_n       = absint($req->get_param('staffing_n'));
  $staffing_include_drafts = absint($req->get_param('staffing_include_drafts'));
  if (!in_array($staffing_n, array(5, 10, 20), true)) {
    $staffing_n = 10;
  }

  // ---------- Admin-only opt-in debug (query or header) ----------
  $debug_flag = (string) $req->get_param('vms_debug');
  if ($debug_flag === '') {
    $debug_flag = (string) $req->get_header('x-vms-debug');
  }
  $debug_on = (
    is_user_logged_in()
    && current_user_can('manage_options')
    && ($debug_flag === '1' || $debug_flag === 'true')
  );

  // ---------- Bills (separate range, not schedule-derived) ----------
  if ($range === 'bills') {
    $resp = bvmgr_dashboard_build_bills_response([
      'venue_id' => $venue_id,
      'include_canceled' => (bool) $include_canceled,
      'only_open' => (bool) $only_open,
      'include_drafts' => (bool) $include_drafts,
    ]);

    if ($debug_on) {
      $resp['debug'] = [
        'enabled' => true,
        'flag' => $debug_flag,
        'tz' => wp_timezone_string(),
        'time' => time(),
      ];
    }

    return rest_ensure_response($resp);
  }

  // ---------- Due Dates (separate range, not schedule-derived) ----------
  if ($range === 'due') {
    if (!function_exists('bvmgr_due_build_dashboard_response')) {
      $path = __DIR__ . '/core/due-dates.php';
      if (file_exists($path)) require_once $path;
    }

    $span_days = bvmgr_dashboard_get_due_span_days();
    $resp = bvmgr_due_build_dashboard_response($span_days);

    if ($debug_on) {
      $resp['debug'] = [
        'enabled' => true,
        'flag' => $debug_flag,
        'tz' => wp_timezone_string(),
        'time' => time(),
        'span_days' => $span_days,
      ];
    }

    return rest_ensure_response($resp);
  }

  // ---------- Financial Snapshot (summary cards from bills + due) ----------
  if ($range === 'financial') {
    $resp = bvmgr_dashboard_build_financial_response([
      'venue_id' => $venue_id,
      'include_canceled' => (bool) $include_canceled,
      'only_open' => (bool) $only_open,
      'include_drafts' => (bool) $include_drafts,
    ]);

    if ($debug_on) {
      $resp['debug'] = [
        'enabled' => true,
        'flag' => $debug_flag,
        'tz' => wp_timezone_string(),
        'time' => time(),
      ];
    }

    return rest_ensure_response($resp);
  }

  // ---------- Staffing Readiness (next N events rollup cache) ----------
  if ($range === 'staffing') {
    if (function_exists('bvmgr_staffing_build_dashboard_response')) {
      $resp = bvmgr_staffing_build_dashboard_response([
        'venue_id' => $venue_id,
        'staffing_n' => $staffing_n,
        'include_drafts' => (bool) $staffing_include_drafts,
      ]);
    } else {
      $resp = [
        'range' => 'staffing',
        'range_start' => wp_date('Y-m-d 00:00:00'),
        'range_end' => wp_date('Y-m-d 23:59:59'),
        'filters' => [
          'venue_id' => $venue_id,
          'staffing_n' => $staffing_n,
          'include_drafts' => (bool) $staffing_include_drafts ? 1 : 0,
        ],
        'items' => [],
      ];
    }

    if ($debug_on) {
      $resp['debug'] = [
        'enabled' => true,
        'flag' => $debug_flag,
        'tz' => wp_timezone_string(),
        'time' => time(),
        'staffing_n' => $staffing_n,
        'staffing_include_drafts' => $staffing_include_drafts,
      ];
    }

    return rest_ensure_response($resp);
  }

  $tz = wp_timezone();
  // ---------- Range calculation ----------
  if ($range === 'today') {

    $start = new DateTimeImmutable('today', $tz);
    $end   = $start->modify('+1 day');
  } else {

    $today = new DateTimeImmutable('today', $tz);

    $mode = (string) get_option('vms_dash_week_mode', 'calendar');
    $mode = ($mode === 'lookahead') ? 'lookahead' : 'calendar';

    // Week preview length: 1 week (7 days) or 2 weeks (14 days).
    // Support either stored as 1/2 (weeks) or 7/14 (days).
    $span_raw = get_option('vms_dash_week_span', 1);
    $span_int = (int) $span_raw;

    if ($span_int >= 14) {
      $weeks = 2;
    } elseif ($span_int === 2) {
      $weeks = 2;
    } else {
      $weeks = 1;
    }

    if ($mode === 'lookahead') {
      // Look ahead from today (next N weeks), not tied to calendar week boundaries.
      $start = $today;
      $end   = $start->modify('+' . (7 * $weeks) . ' days');
    } else {

      // Week starts on (0=Sun..6=Sat). Prefer VMS dash setting, else WP setting.
      $week_start = (int) get_option('vms_dash_week_start', -1);
      if ($week_start < 0 || $week_start > 6) {
        $week_start = (int) get_option('start_of_week', 1);
      }
      if ($week_start < 0 || $week_start > 6) {
        $week_start = 1; // Monday default
      }

      // Calendar week boundary start (based on week_start)
      $dow  = (int) $today->format('w'); // 0=Sun..6=Sat
      $diff = ($dow - $week_start + 7) % 7;

      $start = $today->modify('-' . $diff . ' days');
      $end   = $start->modify('+' . (7 * $weeks) . ' days');
    }
}

  // ---------- Build args for schedule helper ----------
  $args = [
    'start'            => $start,
    'end'              => $end,
    'venue_id'         => $venue_id,
    'include_canceled' => $include_canceled,
    'only_open'        => $only_open,
    'include_drafts'   => $include_drafts,
  ];

  // ---------- Fetch schedule items (optionally with debug passthrough) ----------
  $raw_items      = [];
  $schedule_debug = null;

  if ($debug_on) {
    $args['__debug'] = 1;
  }

  $raw = bvmgr_schedule_items_for_dashboard($args);

  if ($debug_on && is_array($raw) && isset($raw['__items'])) {
    $raw_items = is_array($raw['__items']) ? $raw['__items'] : [];
    $schedule_debug = isset($raw['__debug']) ? $raw['__debug'] : null;
  } else {
    $raw_items = is_array($raw) ? $raw : [];
  }

  // ---------- Normalize to the dashboard item shape the JS expects ----------
  $items = array_values(array_filter(array_map('bvmgr_dashboard_normalize_item', $raw_items)));

  // ---------- Response ----------
  $resp = [
    'range'       => $range,
    'range_start' => $start->format('Y-m-d H:i:s'),
    'range_end'   => $end->format('Y-m-d H:i:s'),
    'items'       => $items,
    'filters'     => [
      'venue_id'         => $venue_id,
      'include_canceled' => $include_canceled,
      'only_open'        => $only_open,
      'include_drafts'   => $include_drafts,
    ],
  ];

  if ($debug_on) {
    $resp['debug'] = [
      'enabled'          => true,
      'flag'             => $debug_flag,
      'tz'               => wp_timezone_string(),
      'schedule'         => $schedule_debug,
      'raw_count'        => count($raw_items),
      'normalized_count' => count($items),
      'time'             => time(),

      // Helpful, minimal range diagnostics (no probes, no noise).
      'week_mode'        => ($range === 'week') ? ((get_option('vms_dash_week_mode', 'calendar') === 'lookahead') ? 'lookahead' : 'calendar') : '',
      'week_span'        => ($range === 'week') ? ((int) get_option('vms_dash_week_span', 1)) : 0,
      'week_start'       => ($range === 'week') ? ((int) get_option('start_of_week', 1)) : -1,
    ];
  }

  return rest_ensure_response($resp);
}

/**
 * Upcoming Bills range (admin-only visibility)
 * - Uses event plans inside the derived event-date window
 * - Computes an estimated due date = event date + terms_days
 * - Sums only the “known” portion (flat fees)
 */
function bvmgr_dashboard_build_bills_response(array $args): array
{
  $tz = wp_timezone();
  $today = new DateTimeImmutable('today', $tz);

  $span_days = absint(get_option('vms_dash_bills_span', 30));
  if ($span_days < 1) $span_days = 1;
  if ($span_days > 365) $span_days = 365;

  $terms_days = (int) get_option('vms_dash_bills_terms_days', 0);
  if ($terms_days < 0) $terms_days = 0;
  if ($terms_days > 365) $terms_days = 365;

  $due_start = $today;
  $due_end   = $due_start->modify('+' . $span_days . ' days'); // exclusive end

  // Query event-date window that can produce due dates inside the due window
  $event_start = $due_start->modify('-' . $terms_days . ' days');
  $event_end   = $due_end->modify('-' . $terms_days . ' days'); // exclusive end

  $event_start_ymd = $event_start->format('Y-m-d');
  $event_end_ymd = $event_end->modify('-1 day')->format('Y-m-d');

  $venue_id = isset($args['venue_id']) ? (string) $args['venue_id'] : 'all';
  $include_drafts   = !empty($args['include_drafts']);
  $include_canceled = !empty($args['include_canceled']);

  $venue_ids = [];
  if ($venue_id === 'all') {
    $venue_ids = function_exists('bvmgr_sch_get_all_venue_ids') ? (array) bvmgr_sch_get_all_venue_ids() : [];
  } else {
    $venue_ids = [absint($venue_id)];
  }
  $venue_ids = array_values(array_filter(array_map('absint', $venue_ids)));

  $venue_name_map = function_exists('bvmgr_sch_get_venue_name_map') ? (array) bvmgr_sch_get_venue_name_map($venue_ids) : [];

  // Collect plan IDs inside the event-date window
  $plans_by_date = [];
  if (!empty($venue_ids)) {
    if ($venue_id === 'all' && function_exists('bvmgr_sch_get_plans_by_date_all')) {
      $plans_by_date = (array) bvmgr_sch_get_plans_by_date_all($venue_ids, $event_start_ymd, $event_end_ymd, $include_drafts, array('context' => 'dashboard_bills', 'include_cancelled' => false));
    } elseif ($venue_id !== 'all' && function_exists('bvmgr_sch_get_plans_by_date')) {
      $plans_by_date = (array) bvmgr_sch_get_plans_by_date((int) $venue_ids[0], $event_start_ymd, $event_end_ymd, $include_drafts, array('context' => 'dashboard_bills', 'include_cancelled' => false));
    }
  }

  $items = [];
  $known_total = 0.0;

  // Flatten
  if (is_array($plans_by_date)) {
    foreach ($plans_by_date as $ymd => $rows) {
      if (!is_array($rows)) continue;
      foreach ($rows as $row) {
        $plan_id = null;
        $venue_id_row = null;

        if (is_numeric($row)) {
          $plan_id = (int) $row;
        } elseif (is_array($row) || is_object($row)) {
          $r = is_object($row) ? (array) $row : $row;
          $plan_id = isset($r['plan_id']) ? (int) $r['plan_id'] : (isset($r['id']) ? (int) $r['id'] : null);
          $venue_id_row = isset($r['venue_id']) ? (int) $r['venue_id'] : null;
        }

        if (!$plan_id) continue;

        // Canonical plan status (context-aware, with row passthrough when available).
        $plan_status = '';
        if (is_array($row) || is_object($row)) {
          $r = is_object($row) ? (array) $row : $row;
          $plan_status = isset($r['plan_status']) ? sanitize_key((string) $r['plan_status']) : '';
        }
        if ($plan_status === '' && function_exists('bvmgr_event_plan_get_status')) {
          $plan_status = (string) bvmgr_event_plan_get_status($plan_id, 'dashboard_bills');
          $plan_status = sanitize_key($plan_status);
        }
        if ($plan_status === 'canceled') $plan_status = 'cancelled';
        if ($plan_status === '') $plan_status = 'draft';

        // Canonical inclusion parity for financial feed (FIN-01):
        // Published-only by default; Include Draft/Ready enables what-if rows.
        if (function_exists('bvmgr_event_plan_should_include')) {
          if (!bvmgr_event_plan_should_include((int) $plan_id, 'dashboard_bills', array(
            'include_drafts'    => (bool) $include_drafts,
            'include_cancelled' => false,
          ))) {
            continue;
          }
        }

        // Skip cancelled unless explicitly included
        if (!$include_canceled) {
          if ($plan_status === 'cancelled') continue;
        }


        $event_date_ymd = (string) get_post_meta($plan_id, '_vms_event_date', true);
        if ($event_date_ymd === '') $event_date_ymd = (string) $ymd;

        $event_dt = DateTimeImmutable::createFromFormat('Y-m-d', $event_date_ymd, $tz);
        if (!$event_dt) continue;

        $due_dt = $event_dt->modify('+' . $terms_days . ' days');

        // Filter due window
        if ($due_dt < $due_start || $due_dt >= $due_end) continue;

        $due_ymd = $due_dt->format('Y-m-d');

        $band_vendor_id = (int) get_post_meta($plan_id, '_vms_band_vendor_id', true);
        $vendor_name = $band_vendor_id ? (string) get_the_title($band_vendor_id) : '(no vendor)';

        $venue_id_effective = (int) ($venue_id_row ?: get_post_meta($plan_id, '_vms_venue_id', true));
        $venue_name = $venue_id_effective && isset($venue_name_map[$venue_id_effective]) ? (string) $venue_name_map[$venue_id_effective] : '';

        // Prefer locked snapshot if present
        $snapshot = get_post_meta($plan_id, '_vms_comp_snapshot', true);
        $structure = '';
        $flat_fee = null;
        $door_split = null;

        if (is_array($snapshot)) {
          $structure = isset($snapshot['structure']) ? (string) $snapshot['structure'] : '';
          $flat_fee  = array_key_exists('flat_fee_amount', $snapshot) ? $snapshot['flat_fee_amount'] : null;
          $door_split = array_key_exists('door_split_percent', $snapshot) ? $snapshot['door_split_percent'] : null;
        }

        if ($structure === '') $structure = (string) get_post_meta($plan_id, '_vms_comp_structure', true);

        if ($flat_fee === null) {
          $v = get_post_meta($plan_id, '_vms_flat_fee_amount', true);
          $flat_fee = ($v === '' || $v === null) ? null : (float) $v;
        }

        if ($door_split === null) {
          $v = get_post_meta($plan_id, '_vms_door_split_percent', true);
          $door_split = ($v === '' || $v === null) ? null : (float) $v;
        }

        // If flat fee not explicitly stored on the plan and no snapshot exists,
        // fall back to the venue's per-day default comp (by DOW).
        if (($flat_fee === null || (float) $flat_fee <= 0) && in_array($structure, array('flat_fee', 'flat_fee_door_split', 'attendance_bonus'), true)) {
          if (function_exists('bvmgr_get_venue_default_comp_for_date') && $venue_id_effective > 0 && $event_date_ymd !== '') {
            $def = (array) bvmgr_get_venue_default_comp_for_date((int) $venue_id_effective, (string) $event_date_ymd);
            $def_struct = isset($def['structure']) ? (string) $def['structure'] : '';
            $def_fee = isset($def['flat_fee_amount']) ? $def['flat_fee_amount'] : null;
            if (in_array($def_struct, array('flat_fee', 'flat_fee_door_split', 'attendance_bonus'), true) && is_numeric($def_fee) && (float) $def_fee > 0) {
              $flat_fee = (float) $def_fee;
            }
          }
        }

        $structure_label = bvmgr_dashboard_comp_structure_label($structure);

        $known_amount = null;
        if (in_array($structure, array('flat_fee', 'flat_fee_door_split', 'attendance_bonus'), true)) {
          if (is_numeric($flat_fee) && (float) $flat_fee > 0) {
            $known_amount = (float) $flat_fee;
          }
        }

        if ($known_amount !== null) {
          $known_total += (float) $known_amount;
        }


        $needs_attention_reasons = [];
        if ($band_vendor_id <= 0) {
          $needs_attention_reasons[] = 'missing_vendor';
        }
        if (in_array($structure, array('flat_fee', 'flat_fee_door_split', 'attendance_bonus'), true) && $known_amount === null) {
          $needs_attention_reasons[] = 'missing_amount';
        }

        $tax_missing = false;
        $tax_bypass_active = false;
        $tax_bypass_until = '';
        if ($band_vendor_id > 0 && function_exists('bvmgr_is_vendor_tax_profile_complete')) {
          $tax_missing = !bvmgr_is_vendor_tax_profile_complete((int) $band_vendor_id);
        }
        if ($band_vendor_id > 0 && function_exists('bvmgr_get_tax_bypass_status')) {
          $st = (array) bvmgr_get_tax_bypass_status((int) $band_vendor_id);
          $tax_bypass_active = !empty($st['is_active']);
          $tax_bypass_until = isset($st['until']) ? (string) $st['until'] : '';
        }
        $payment_blocked = ($tax_missing && !$tax_bypass_active);
        if ($payment_blocked) {
          $needs_attention_reasons[] = 'tax_incomplete';
        }

        $is_estimated = true;
        $plan_status_label = function_exists('bvmgr_event_plan_status_label')
          ? (string) bvmgr_event_plan_status_label((string) $plan_status)
          : ucwords(str_replace(array('_', '-'), ' ', (string) $plan_status));

        $items[] = [
          'plan_id' => $plan_id,
          'plan_status' => (string) $plan_status,
          'plan_status_label' => (string) $plan_status_label,
          'event_date' => $event_date_ymd,
          'due_date' => $due_ymd,
          'is_estimated' => (bool) $is_estimated,
          'needs_attention' => !empty($needs_attention_reasons),
          'needs_attention_reasons' => $needs_attention_reasons,
          'vendor_id' => $band_vendor_id,
          'vendor_name' => $vendor_name,
          'venue_id' => $venue_id_effective,
          'venue_name' => $venue_name,
          'structure' => $structure,
          'structure_label' => $structure_label,
          'known_amount' => ($known_amount === null) ? null : (float) $known_amount,
          'known_amount_fmt' => ($known_amount === null) ? null : bvmgr_dashboard_money_fmt((float) $known_amount),
          'payment_blocked' => (bool) $payment_blocked,
          'tax_missing' => (bool) $tax_missing,
          'tax_bypass_active' => (bool) $tax_bypass_active,
          'tax_bypass_until' => (string) $tax_bypass_until,
          'vendor_edit_link' => ($band_vendor_id > 0) ? get_edit_post_link((int) $band_vendor_id, '') : '',
          'edit_link' => get_edit_post_link($plan_id, ''),
        ];
      }
    }
  }

  usort($items, function ($a, $b) {
    $ad = (string) ($a['due_date'] ?? '');
    $bd = (string) ($b['due_date'] ?? '');
    if ($ad === $bd) {
      return strcmp((string) ($a['vendor_name'] ?? ''), (string) ($b['vendor_name'] ?? ''));
    }
    return strcmp($ad, $bd);
  });

  return [
    'range' => 'bills',
    'range_start' => $due_start->format('Y-m-d H:i:s'),
    'range_end' => $due_end->format('Y-m-d H:i:s'),
    'span_days' => $span_days,
    'terms_days' => $terms_days,
    'known_total' => (float) $known_total,
    'known_total_fmt' => bvmgr_dashboard_money_fmt((float) $known_total),
    'items' => $items,
  ];
}

function bvmgr_dashboard_build_financial_response(array $args): array
{
  if (!function_exists('bvmgr_due_build_dashboard_response')) {
    $path = __DIR__ . '/core/due-dates.php';
    if (file_exists($path)) require_once $path;
  }

  $bills = bvmgr_dashboard_build_bills_response($args);
  $due_span_days = bvmgr_dashboard_get_due_span_days();
  $due = function_exists('bvmgr_due_build_dashboard_response')
    ? bvmgr_due_build_dashboard_response($due_span_days)
    : ['range_start' => '', 'range_end' => '', 'active_obligations' => 0, 'counts' => [], 'items' => []];

  $bills_items = (isset($bills['items']) && is_array($bills['items'])) ? $bills['items'] : [];
  $bills_total = count($bills_items);
  $bills_needs_attention = 0;
  $bills_missing_vendor = 0;
  $bills_missing_amount = 0;
  $bills_payment_blocked = 0;

  foreach ($bills_items as $row) {
    if (!is_array($row)) continue;
    $reasons = (isset($row['needs_attention_reasons']) && is_array($row['needs_attention_reasons'])) ? $row['needs_attention_reasons'] : [];
    if (!empty($reasons)) $bills_needs_attention++;
    if (in_array('missing_vendor', $reasons, true)) $bills_missing_vendor++;
    if (in_array('missing_amount', $reasons, true)) $bills_missing_amount++;
    if (!empty($row['payment_blocked']) || in_array('tax_incomplete', $reasons, true)) $bills_payment_blocked++;
  }

  $due_counts = (isset($due['counts']) && is_array($due['counts'])) ? $due['counts'] : [];
  $due_items = (isset($due['items']) && is_array($due['items'])) ? $due['items'] : [];
  $due_overdue = isset($due_counts['overdue']) ? (int) $due_counts['overdue'] : 0;
  $due_7 = isset($due_counts['due_7']) ? (int) $due_counts['due_7'] : 0;
  $due_14 = isset($due_counts['due_14']) ? (int) $due_counts['due_14'] : 0;
  $due_30 = isset($due_counts['due_30']) ? (int) $due_counts['due_30'] : 0;
  $due_needs_attention = isset($due_counts['needs_attention']) ? (int) $due_counts['needs_attention'] : 0;
  $due_active = isset($due['active_obligations']) ? (int) $due['active_obligations'] : 0;

  $bills_window_days = isset($bills['span_days']) ? max(1, (int) $bills['span_days']) : 30;
  $window_days = max($bills_window_days, $due_span_days);
  $tz = wp_timezone();
  $today = new DateTimeImmutable('today', $tz);
  $range_start = $today->format('Y-m-d H:i:s');
  $range_end = $today->modify('+' . $window_days . ' days')->format('Y-m-d H:i:s');

  return [
    'range' => 'financial',
    'range_start' => $range_start,
    'range_end' => $range_end,
    'filters' => [
      'venue_id' => isset($args['venue_id']) ? (string) $args['venue_id'] : 'all',
      'include_canceled' => !empty($args['include_canceled']) ? 1 : 0,
      'only_open' => !empty($args['only_open']) ? 1 : 0,
      'include_drafts' => !empty($args['include_drafts']) ? 1 : 0,
    ],
    'summary' => [
      'known_total' => isset($bills['known_total']) ? (float) $bills['known_total'] : 0.0,
      'known_total_fmt' => isset($bills['known_total_fmt']) ? (string) $bills['known_total_fmt'] : bvmgr_dashboard_money_fmt(0.0),
      'bills_total' => $bills_total,
      'bills_needs_attention' => $bills_needs_attention,
      'bills_missing_vendor' => $bills_missing_vendor,
      'bills_missing_amount' => $bills_missing_amount,
      'bills_payment_blocked' => $bills_payment_blocked,
      'bills_window_days' => $bills_window_days,
      'due_active_obligations' => $due_active,
      'due_window_items' => count($due_items),
      'due_overdue' => $due_overdue,
      'due_7' => $due_7,
      'due_14' => $due_14,
      'due_30' => $due_30,
      'due_needs_attention' => $due_needs_attention,
      'due_window_days' => $due_span_days,
    ],
  ];
}

function bvmgr_dashboard_comp_structure_label(string $structure): string
{
  $structure = sanitize_key($structure);
  if ($structure === 'door_split') return 'Door Split';
  if ($structure === 'flat_fee_door_split') return 'Flat Fee + Door Split';
  if ($structure === 'attendance_bonus') return 'Base + Attendance Bonus';
  return 'Flat Fee';
}

function bvmgr_dashboard_money_fmt(float $amount): string
{
  return '$' . number_format((float) $amount, 2, '.', ',');
}


/**
 * This is the single bridge point between Dashboard and Schedule data.
 * We do not change Schedule UI. We only reuse its data.
 */
/* DROP-IN PATCH — add “why did items_count=0?” samples to debug */

function bvmgr_schedule_items_for_dashboard(array $args)
{
  $want_debug = !empty($args['__debug']);

  $tz = wp_timezone();
  $start = $args['start'] instanceof DateTimeImmutable ? $args['start'] : new DateTimeImmutable('today', $tz);
  $end   = $args['end']   instanceof DateTimeImmutable ? $args['end']   : $start->modify('+1 day');

  $start_ymd = $start->format('Y-m-d');
  $end_inclusive = $end->modify('-1 day');
  $end_ymd = $end_inclusive->format('Y-m-d');

  $venue_id = isset($args['venue_id']) ? (string) $args['venue_id'] : 'all';
  $include_canceled = !empty($args['include_canceled']);
  $only_open = !empty($args['only_open']);
  $include_drafts = !empty($args['include_drafts']);

  $debug = [
    'start_ymd' => $start_ymd,
    'end_ymd' => $end_ymd,
    'venue_id' => $venue_id,
    'include_canceled' => (int) $include_canceled,
    'include_drafts' => (int) $include_drafts,
    'only_open' => (int) $only_open,
    'branch' => ($venue_id === 'all') ? 'all' : 'single',
    'venue_ids_count' => 0,
    'venue_ids_sample' => [],
    'plans_days' => 0,
    'plans_total' => 0,
    'plans_sample_date' => null,
    'plans_sample_row' => null,
    'open_map_days' => null,
    'open_map_total' => null,
    'open_map_sample_for_plan_date' => null,
    'items_count' => 0,
  ];

  $items = [];

  if ($venue_id === 'all') {
    $venue_ids = bvmgr_sch_get_all_venue_ids();
    $debug['venue_ids_count'] = is_array($venue_ids) ? count($venue_ids) : 0;
    $debug['venue_ids_sample'] = is_array($venue_ids) ? array_slice($venue_ids, 0, 10) : [];

    if (empty($venue_ids)) {
      if ($want_debug) return ['__debug' => $debug, '__items' => []];
      return [];
    }

    // $plans_by_date = vms_sch_get_plans_by_date_all($venue_ids, $start_ymd, $end_ymd);
    $plans_by_date = bvmgr_sch_get_plans_by_date_all($venue_ids, $start_ymd, $end_ymd, $include_drafts, array('context' => 'dashboard', 'include_cancelled' => $include_canceled));
    $venue_name_map = bvmgr_sch_get_venue_name_map($venue_ids);

    if (is_array($plans_by_date)) {
      $debug['plans_days'] = count($plans_by_date);
      $total = 0;
      foreach ($plans_by_date as $day => $rows) {
        if (is_array($rows)) {
          $total += count($rows);
          if ($debug['plans_sample_row'] === null && !empty($rows)) {
            $debug['plans_sample_date'] = $day;
            $debug['plans_sample_row'] = $rows[0];
          }
        }
      }
      $debug['plans_total'] = $total;
    }

    // $items = vms_dashboard_flatten_plans_by_date($plans_by_date, $venue_name_map, null, $include_canceled, $only_open);
    $items = bvmgr_dashboard_flatten_plans_by_date(
      $plans_by_date,
      $venue_name_map,
      null,
      $include_canceled,
      $only_open,
      !empty($args['include_drafts'])
    );
  } else {
    $vid = (int) $venue_id;

    $open_map = bvmgr_sch_get_open_map($vid, $start_ymd, $end_ymd);
    $plans_by_date = bvmgr_sch_get_plans_by_date($vid, $start_ymd, $end_ymd, $include_drafts, array('context' => 'dashboard', 'include_cancelled' => $include_canceled));

    if ($want_debug && is_array($plans_by_date)) {
      $debug['rows_for_2026_01_25'] = $plans_by_date['2026-01-25'] ?? null;
    }

    if (is_array($open_map)) {
      $debug['open_map_days'] = count($open_map);
      $debug['open_map_total'] = count($open_map);
    } else {
      $debug['open_map_days'] = 0;
      $debug['open_map_total'] = 0;
    }

    if (is_array($plans_by_date)) {
      $debug['plans_days'] = count($plans_by_date);
      $total = 0;
      foreach ($plans_by_date as $day => $rows) {
        if (is_array($rows)) {
          $total += count($rows);
          if ($debug['plans_sample_row'] === null && !empty($rows)) {
            $debug['plans_sample_date'] = $day;

            $first = $rows[0];

            // If it's an int (plan post ID), record it as plan_id.
            if (is_int($first) || (is_string($first) && ctype_digit((string) $first))) {
              $pid = absint($first);
              $debug['plans_sample_row'] = [
                'plan_id'     => $pid,
                'venue_id'    => $vid,
                'post_status' => $pid ? (string) get_post_status($pid) : '',
              ];
            } elseif (is_array($first)) {
              // If it's an array already, pass through.
              $debug['plans_sample_row'] = $first;
            } else {
              $debug['plans_sample_row'] = [
                'plan_id'     => 0,
                'venue_id'    => $vid,
                'post_status' => '',
              ];
            }
          }
        }
      }
      $debug['plans_total'] = $total;
    }

    if ($debug['plans_sample_date'] && is_array($open_map)) {
      $d = $debug['plans_sample_date'];
      $debug['open_map_sample_for_plan_date'] = $open_map[$d] ?? null;
    }

    $venue_name_map = [
      $vid => (string) (get_the_title($vid) ?: 'Venue'),
    ];

    $items = bvmgr_dashboard_flatten_plans_by_date(
      $plans_by_date,
      $venue_name_map,
      $open_map,
      $include_canceled,
      $only_open,
      !empty($args['include_drafts'])
    );
  }

  $debug['items_count'] = is_array($items) ? count($items) : 0;

  if ($want_debug) {
    return ['__debug' => $debug, '__items' => $items];
  }

  return $items;
}


/**
 * Flattens Schedule's plans_by_date structure into dashboard "items".
 *
 * $plans_by_date expected format (as used by schedule renderers):
 * [
 *   'YYYY-MM-DD' => [ $row, $row, ... ],
 *   ...
 * ]
 *
 * $open_map optional format:
 * [
 *   'YYYY-MM-DD' => true|false,
 *   ...
 * ]
 */
function bvmgr_dashboard_flatten_plans_by_date(
  $plans_by_date,
  array $venue_name_map,
  $open_map,
  bool $include_canceled,
  bool $only_open,
  bool $include_drafts = false
) {
  $items = [];
  if (!is_array($plans_by_date)) return $items;

  // Fallback item builder when normalize_schedule_row fails
  $fallback_item = function (string $ymd, $row) use ($venue_name_map) {

    // Row can be:
    // - an int post_id (e.g. 590)
    // - a numeric string post_id
    // - or an associative array with plan_id/post_id/id
    $post_id = 0;

    if (is_int($row) || (is_string($row) && ctype_digit($row))) {
      $post_id = absint($row);
      $row = [];
    } elseif (is_array($row)) {
      if (isset($row['plan_id'])) $post_id = absint($row['plan_id']);
      elseif (isset($row['post_id'])) $post_id = absint($row['post_id']);
      elseif (isset($row['id'])) $post_id = absint($row['id']);
    } else {
      $row = [];
    }

    $title = (string) ($row['title'] ?? $row['name'] ?? $row['event_title'] ?? $row['plan_title'] ?? $row['post_title'] ?? '');
    if (($title === '' || $title === '(untitled)') && $post_id) {
      $pt = get_the_title($post_id);
      if (!empty($pt)) $title = (string) $pt;
    }

    $status = (string) ($row['status'] ?? $row['plan_status'] ?? $row['post_status'] ?? '');
    if ($status === '' && $post_id) {
      $status = (string) get_post_status($post_id);
    }

    $venue_id =
      isset($row['venue_id']) ? absint($row['venue_id'])
      : (isset($row['venue']) ? absint($row['venue'])
        : (isset($row['venue_post_id']) ? absint($row['venue_post_id']) : 0));

    $venue_name = '';
    if ($venue_id && isset($venue_name_map[$venue_id])) {
      $venue_name = (string) $venue_name_map[$venue_id];
    } else {
      $first = reset($venue_name_map);
      if (!empty($first)) $venue_name = (string) $first;
    }

    $start_ts = 0;
    if (isset($row['start_ts'])) $start_ts = (int) $row['start_ts'];
    elseif (isset($row['ts'])) $start_ts = (int) $row['ts'];
    elseif (isset($row['start'])) $start_ts = is_numeric($row['start']) ? (int) $row['start'] : (int) strtotime((string) $row['start']);
    elseif (isset($row['start_datetime'])) $start_ts = (int) strtotime((string) $row['start_datetime']);
    elseif (isset($row['start_time'])) $start_ts = (int) strtotime($ymd . ' ' . (string) $row['start_time']);
    else $start_ts = (int) strtotime($ymd . ' 00:00:00');

    $edit_link = '';
    if (!empty($row['edit_link'])) {
      $edit_link = (string) $row['edit_link'];
    } elseif ($post_id) {
      $edit_link = admin_url('post.php?post=' . $post_id . '&action=edit');
    }

    return [
      'title'     => $title,
      'status'    => $status,
      'venue_name' => $venue_name,
      'start_ts'  => $start_ts,
      'edit_link' => $edit_link,
    ];
  };

  foreach ($plans_by_date as $ymd => $rows) {
    if (!is_array($rows)) continue;

    // Only-open filter (when we have open_map)
    // IMPORTANT: do NOT hide scheduled rows just because the day is marked closed.
    if ($only_open && is_array($open_map)) {
      $val = $open_map[$ymd] ?? null;
      $is_open_day = !empty($val);

      if (!$is_open_day && (empty($rows) || count($rows) === 0)) {
        continue;
      }
    }

    foreach ($rows as $row) {
      $it = bvmgr_dashboard_normalize_schedule_row($ymd, $row, $venue_name_map, $open_map);

      if (!$it || !is_array($it)) {
        $it = $fallback_item((string) $ymd, $row);
      }

      // Hard safety: if this row cannot be tied to a real plan post ID, do not render it.
      $plan_id = 0;

      if (is_int($row) || (is_string($row) && ctype_digit((string) $row))) {
        $plan_id = absint($row);
      } elseif (is_array($row)) {
        if (isset($row['plan_id'])) $plan_id = absint($row['plan_id']);
        elseif (isset($row['post_id'])) $plan_id = absint($row['post_id']);
        elseif (isset($row['id'])) $plan_id = absint($row['id']);
      }

      if ($plan_id <= 0) {
        continue;
      }

      // Ensure title/status exist for valid plan IDs.
      $t = trim((string) ($it['title'] ?? ''));
      if ($t === '' || $t === '(untitled)') {
        $pt = get_the_title($plan_id);
        if (!empty($pt)) {
          $it['title'] = (string) $pt;
        }
      }

      $st = trim((string) ($it['status'] ?? ''));
      if ($st === '') {
        $it['status'] = (string) get_post_status($plan_id);
      }

      $st_low = strtolower((string) ($it['status'] ?? ''));
      if ($st_low === 'draft' || $st_low === 'pending' || $st_low === 'future') {
        $it['status'] = 'Draft';
      }

      if ($plan_id) {
        // Fill missing title from WP title.
        $t = trim((string) ($it['title'] ?? ''));
        if ($t === '' || $t === '(untitled)') {
          $pt = get_the_title($plan_id);
          if (!empty($pt)) {
            $it['title'] = (string) $pt;
          }
        }

        // Fill missing status from WP post status.
        $st_raw = trim((string) ($it['status'] ?? ''));
        if ($st_raw === '') {
          $it['status'] = (string) get_post_status($plan_id);
        }
      }

      // Display label: Draft should read exactly "Draft".
      $st = strtolower((string) ($it['status'] ?? ''));
      if ($st === 'draft' || $st === 'pending' || $st === 'future') {
        $it['status'] = 'Draft';
      }

      // Canonical inclusion rules: Published-only by default; optional what-if includes Draft/Ready.
      // Cancelled is controlled by its own toggle.
      if (function_exists('bvmgr_event_plan_should_include')) {
        if ($plan_id > 0 && !bvmgr_event_plan_should_include($plan_id, 'dashboard', array(
          'include_drafts'    => (bool) $include_drafts,
          'include_cancelled' => (bool) $include_canceled,
        ))) {
          continue;
        }

        $status_key = function_exists('bvmgr_event_plan_get_status')
          ? (string) bvmgr_event_plan_get_status($plan_id, 'dashboard')
          : sanitize_key((string) ($it['status'] ?? ''));
        if ($status_key === 'canceled') {
          $status_key = 'cancelled';
        }
        if ($status_key === '') {
          $status_key = 'draft';
        }

        $status_label = function_exists('bvmgr_event_plan_status_label')
          ? (string) bvmgr_event_plan_status_label($status_key)
          : ucwords(str_replace(array('_', '-'), ' ', $status_key));

        // Dashboard should display canonical status labels (Draft/Ready/Published/Cancelled).
        $it['status_key'] = (string) $status_key;
        $it['status'] = (string) $status_label;
        $it['is_canceled'] = ($status_key === 'cancelled');
      } else {
        // Fallback behavior: Published-only unless include_drafts is on.
        if (!$include_drafts) {
          $st_key = function_exists('sanitize_key') ? sanitize_key((string) ($it['status'] ?? '')) : strtolower((string) ($it['status'] ?? ''));
          if ($st_key === '') {
            $st_key = $plan_id ? sanitize_key((string) get_post_status($plan_id)) : '';
          }

          if ($st_key !== 'published' && $st_key !== 'publish') {
            continue;
          }
        }

        if (!$include_canceled) {
          $st = strtolower((string) ($it['status'] ?? ''));
          if ($st === 'canceled' || $st === 'cancelled') {
            continue;
          }
        }
      }


      // Tax compliance visibility: show payment blocked or bypass badges on the dashboard event lists.
      $vendor_id = 0;
      $tax_missing = false;
      $tax_bypass_active = false;
      $tax_bypass_until = '';

      $k_band_vendor_id = function_exists('bvmgr_meta_key') ? (string) bvmgr_meta_key('event_plan', 'band_vendor_id') : '';
      if ($k_band_vendor_id === '') $k_band_vendor_id = '_vms_band_vendor_id';

      if ($plan_id > 0) {
        $vendor_id = absint(get_post_meta($plan_id, $k_band_vendor_id, true));
      }

      if ($vendor_id > 0 && function_exists('bvmgr_is_vendor_tax_profile_complete')) {
        $tax_missing = !bvmgr_is_vendor_tax_profile_complete($vendor_id);
      }

      if ($vendor_id > 0 && function_exists('bvmgr_get_tax_bypass_status')) {
        $st_bypass = (array) bvmgr_get_tax_bypass_status($vendor_id);
        $tax_bypass_active = !empty($st_bypass['is_active']);
        $tax_bypass_until  = isset($st_bypass['until']) ? (string) $st_bypass['until'] : '';
      }

      $it['payment_blocked'] = (bool) ($tax_missing && !$tax_bypass_active);
      $it['tax_missing'] = (bool) $tax_missing;
      $it['tax_bypass_active'] = (bool) $tax_bypass_active;
      $it['tax_bypass_until'] = (string) $tax_bypass_until;

      if ($vendor_id > 0) {
        $it['vendor_id'] = (int) $vendor_id;
        $it['vendor_edit_link'] = get_edit_post_link($vendor_id, 'raw');
      }

      $items[] = $it;
    }
  }

  usort($items, function ($a, $b) {
    return ($a['start_ts'] ?? 0) <=> ($b['start_ts'] ?? 0);
  });

  return $items;
}




/**
 * Normalize one Schedule row into dashboard item shape.
 * This is intentionally defensive: it tries common key names.
 */
function bvmgr_dashboard_normalize_schedule_row(string $ymd, $row, array $venue_name_map, $open_map)
{
  if (is_object($row)) $row = (array) $row;
  if (!is_array($row)) return null;

  // Attempt to locate plan/event id
  $plan_id = $row['plan_id'] ?? $row['id'] ?? $row['post_id'] ?? null;
  if ($plan_id) $plan_id = (int) $plan_id;

  // Venue id may be on the row in all-venues mode
  $venue_id = $row['venue_id'] ?? $row['venue'] ?? $row['venue_post_id'] ?? null;
  if ($venue_id !== null) $venue_id = (int) $venue_id;

  // Status often exists in schedule row
  $status = $row['status'] ?? $row['plan_status'] ?? null;

  // Time fields: try a few likely keys used by schedule.php
  $start_hhmm = $row['start_time'] ?? $row['start'] ?? $row['start_hhmm'] ?? null;
  $end_hhmm   = $row['end_time']   ?? $row['end']   ?? $row['end_hhmm']   ?? null;

  $start_ts = bvmgr_dashboard_ts_from_ymd_hhmm($ymd, $start_hhmm);
  $end_ts   = bvmgr_dashboard_ts_from_ymd_hhmm($ymd, $end_hhmm);

  // Title: prefer plan title if we have it
  $title = $row['title'] ?? $row['name'] ?? null;
  if (!$title && $plan_id) {
    $title = get_the_title($plan_id);
  }
  if (!$title) $title = '(untitled)';

  // Venue name
  $venue_name = null;
  if ($venue_id && isset($venue_name_map[$venue_id])) {
    $venue_name = $venue_name_map[$venue_id];
  }

  // is_open: only meaningful if open_map exists
  $is_open = true;
  if (is_array($open_map)) {
    $is_open = !empty($open_map[$ymd]);
  }

  return [
    'event_id'    => $plan_id ?: null,
    'title'       => $title,
    'start_ts'    => $start_ts ?: strtotime($ymd . ' 00:00:00'),
    'end_ts'      => $end_ts ?: null,
    'venue_id'    => $venue_id ?: null,
    'venue_name'  => $venue_name,
    'status'      => $status,
    'is_canceled' => (strtolower((string) $status) === 'canceled' || strtolower((string) $status) === 'cancelled'),
    'is_open'     => (bool) $is_open,
    'edit_link'   => ($plan_id ? get_edit_post_link($plan_id, 'raw') : null),
  ];
}

function bvmgr_dashboard_ts_from_ymd_hhmm(string $ymd, $hhmm)
{
  if (!$hhmm || !is_string($hhmm)) return null;

  // Normalize common forms: "7:00 PM", "19:00", "1900", "7:00pm"
  $hhmm_trim = trim($hhmm);

  // If it already parses, use strtotime with the date prefix.
  $ts = strtotime($ymd . ' ' . $hhmm_trim);
  if ($ts !== false) return $ts;

  // Try plain 4-digit military "HHMM"
  if (preg_match('/^\d{4}$/', $hhmm_trim)) {
    $h = substr($hhmm_trim, 0, 2);
    $m = substr($hhmm_trim, 2, 2);
    $ts2 = strtotime($ymd . " {$h}:{$m}:00");
    return ($ts2 !== false) ? $ts2 : null;
  }

  return null;
}

function bvmgr_dashboard_normalize_item($it)
{
  // If it's already normalized, pass through.
  if (is_array($it) && isset($it['start_ts']) && isset($it['title'])) return $it;

  // If Schedule returns objects/arrays with different keys, map them here.
  // We keep this defensive so it fails gracefully.
  if (is_object($it)) $it = (array) $it;
  if (!is_array($it)) return null;

  // Common key guesses (you will likely adjust once you see actual Schedule output)
  $start_ts = $it['start_ts'] ?? (isset($it['start']) ? strtotime($it['start']) : null);
  $end_ts   = $it['end_ts'] ?? (isset($it['end']) ? strtotime($it['end']) : null);

  $title = $it['title'] ?? ($it['event_title'] ?? ($it['name'] ?? null));
  if (!$title || !$start_ts) return null;

  return [
    'event_id'   => $it['event_id'] ?? ($it['id'] ?? null),
    'title'      => $title,
    'start_ts'   => (int) $start_ts,
    'end_ts'     => $end_ts ? (int) $end_ts : null,
    'venue_id'   => $it['venue_id'] ?? null,
    'venue_name' => $it['venue_name'] ?? null,
    'status'     => $it['status'] ?? null,
    'is_canceled' => (bool) ($it['is_canceled'] ?? false),
    'is_open'    => (bool) ($it['is_open'] ?? true),
    'edit_link'  => $it['edit_link'] ?? null,
  ];
}
