<?php
if (!defined('ABSPATH')) exit;

/**
 * Budget Forecast Calculator (v1.3 / FIN-08)
 *
 * Decision-support only. No data is saved.
 *
 * Adds (on top of v1.1):
 * - Auto-scaling cost rules (e.g., staffing that steps up with headcount)
 * - Attendance factor (tickets sold → expected heads)
 *
 * Notes:
 * - Keep v1.x dumb-friendly. Advanced rules are optional and tucked under a <details> block.
 * - No inline <style> is used; layout relies on WP admin table styling.
 */

function vms_budget_parse_money($raw): float
{
  $s = is_string($raw) ? $raw : '';
  $s = trim($s);

  // allow "$1,234.56" and "1234.56"
  $s = str_replace(array('$', ','), '', $s);

  if ($s === '') return 0.0;

  // handle "(123.45)" negative style
  $neg = false;
  if (substr($s, 0, 1) === '(' && substr($s, -1) === ')') {
    $neg = true;
    $s = substr($s, 1, -1);
    $s = trim($s);
  }

  if (!is_numeric($s)) return 0.0;

  $v = (float) $s;
  if ($neg) $v = 0.0 - abs($v);
  return $v;
}

function vms_budget_parse_percent($raw, float $min = 0.0, float $max = 100.0): float
{
  $v = vms_budget_parse_money($raw);
  if ($v < $min) $v = $min;
  if ($v > $max) $v = $max;
  return $v;
}

function vms_budget_fmt_money($v): string
{
  return '$' . number_format((float) $v, 2);
}

function vms_budget_fmt_int($v): string
{
  return number_format((int) $v);
}

function vms_budget_calculator_default_cost_items(): array
{
  // NOTE: Keep this list short and obvious. More rows can be added by the operator.
  // Tip: If you enable auto-scaling staffing rules below, keep any lump-sum staffing row OFF to avoid double counting.
  return array(
    array('enabled' => 1, 'label' => __('Venue overhead (utilities, misc.)', 'vms'), 'amount' => 200.00, 'type' => 'fixed'),
    array('enabled' => 0, 'label' => __('Staffing (lump sum — turn off if using auto-staff rules)', 'vms'), 'amount' => 250.00, 'type' => 'fixed'),
    array('enabled' => 0, 'label' => __('Facebook / Instagram ads', 'vms'), 'amount' => 75.00, 'type' => 'fixed'),
    array('enabled' => 0, 'label' => __('Radio / local media', 'vms'), 'amount' => 150.00, 'type' => 'fixed'),
    array('enabled' => 0, 'label' => __('Print (flyers, posters)', 'vms'), 'amount' => 35.00, 'type' => 'fixed'),
    array('enabled' => 0, 'label' => __('Production add-ons', 'vms'), 'amount' => 0.00, 'type' => 'fixed'),
    array('enabled' => 0, 'label' => __('Per-ticket cost (wristband, stub, etc.)', 'vms'), 'amount' => 0.00, 'type' => 'per_ticket'),
    array('enabled' => 0, 'label' => __('Other cost', 'vms'), 'amount' => 0.00, 'type' => 'fixed'),
  );
}

function vms_budget_calculator_default_autoscale_items(): array
{
  // These are optional and OFF by default.
  // Unit cost should represent the total cost per staff member for the event (e.g., hourly * hours).
  return array(
    array('enabled' => 0, 'label' => __('Bartender', 'vms'), 'unit_cost' => 160.00, 'per_n' => 100, 'min_units' => 1, 'max_units' => 0),
    array('enabled' => 0, 'label' => __('Security', 'vms'), 'unit_cost' => 200.00, 'per_n' => 150, 'min_units' => 1, 'max_units' => 0),
    array('enabled' => 0, 'label' => __('Door / ticketing', 'vms'), 'unit_cost' => 140.00, 'per_n' => 200, 'min_units' => 1, 'max_units' => 0),
    array('enabled' => 0, 'label' => __('Cleanup', 'vms'), 'unit_cost' => 120.00, 'per_n' => 200, 'min_units' => 1, 'max_units' => 0),
    array('enabled' => 0, 'label' => __('Sound tech', 'vms'), 'unit_cost' => 250.00, 'per_n' => 999999, 'min_units' => 1, 'max_units' => 1),
  );
}

function vms_budget_calculator_cost_profiles(): array
{
  // Built-in presets. No saving in v1.x.
  $base = vms_budget_calculator_default_cost_items();

  $no_promo = $base;
  foreach ($no_promo as &$it) {
    $label = strtolower((string) $it['label']);
    if (strpos($label, 'facebook') !== false || strpos($label, 'instagram') !== false) $it['enabled'] = 0;
    if (strpos($label, 'radio') !== false || strpos($label, 'media') !== false) $it['enabled'] = 0;
    if (strpos($label, 'print') !== false || strpos($label, 'flyers') !== false) $it['enabled'] = 0;
  }
  unset($it);

  $light = $base;
  foreach ($light as &$it) {
    $label = strtolower((string) $it['label']);
    if (strpos($label, 'facebook') !== false || strpos($label, 'instagram') !== false) { $it['enabled'] = 1; $it['amount'] = 60.00; }
    if (strpos($label, 'radio') !== false || strpos($label, 'media') !== false) $it['enabled'] = 0;
    if (strpos($label, 'print') !== false || strpos($label, 'flyers') !== false) { $it['enabled'] = 1; $it['amount'] = 25.00; }
  }
  unset($it);

  $full = $base;
  foreach ($full as &$it) {
    $label = strtolower((string) $it['label']);
    if (strpos($label, 'facebook') !== false || strpos($label, 'instagram') !== false) { $it['enabled'] = 1; $it['amount'] = 150.00; }
    if (strpos($label, 'radio') !== false || strpos($label, 'media') !== false) { $it['enabled'] = 1; $it['amount'] = 120.00; }
    if (strpos($label, 'print') !== false || strpos($label, 'flyers') !== false) { $it['enabled'] = 1; $it['amount'] = 40.00; }
  }
  unset($it);

  $festival = array(
    array('enabled' => 1, 'label' => __('Venue overhead (utilities, misc.)', 'vms'), 'amount' => 500.00, 'type' => 'fixed'),
    array('enabled' => 0, 'label' => __('Staffing (lump sum — turn off if using auto-staff rules)', 'vms'), 'amount' => 0.00, 'type' => 'fixed'),
    array('enabled' => 1, 'label' => __('Facebook / Instagram ads', 'vms'), 'amount' => 250.00, 'type' => 'fixed'),
    array('enabled' => 1, 'label' => __('Radio / local media', 'vms'), 'amount' => 250.00, 'type' => 'fixed'),
    array('enabled' => 1, 'label' => __('Production add-ons', 'vms'), 'amount' => 800.00, 'type' => 'fixed'),
    array('enabled' => 1, 'label' => __('Per-ticket cost (wristband, stub, etc.)', 'vms'), 'amount' => 0.50, 'type' => 'per_ticket'),
    array('enabled' => 1, 'label' => __('Other cost', 'vms'), 'amount' => 200.00, 'type' => 'fixed'),
  );

  return array(
    'custom'    => array('label' => __('Custom', 'vms'), 'items' => $base),
    'no_promo'  => array('label' => __('No Promo', 'vms'), 'items' => $no_promo),
    'light'     => array('label' => __('Light Promo', 'vms'), 'items' => $light),
    'full'      => array('label' => __('Full Promo', 'vms'), 'items' => $full),
    'festival'  => array('label' => __('Festival', 'vms'), 'items' => $festival),
  );
}

function vms_budget_calculator_defaults(): array
{
  $current_year = (int) wp_date('Y');
  if ($current_year < 2000) $current_year = 2000;
  if ($current_year > 2100) $current_year = 2100;

  return array(
    'mode'              => 'single', // single | period
    'events_count'      => 4,

    'tickets_sold'      => 120,
    'ticket_price'      => 20.00,
    'attendance_percent'=> 100.0,  // tickets sold → expected heads
    'bar_per_head'      => 12.00,
    'other_revenue'     => 0.00,

    'band_pay'          => 1500.00,

    // Ticketing / processing fees applied to ticket revenue only
    'fee_percent'       => 2.9,   // %
    'fee_fixed'         => 0.30,  // per ticket

    'target_profit'     => 250.00,

    // FIN-08 annual planning fields.
    'forecast_year'          => $current_year,
    'annual_goal_revenue'    => 100000.00,
    'annual_goal_profit'     => 25000.00,
    'annual_include_drafts'  => vms_budget_calculator_default_forecast_include_drafts(),

    'cost_profile'      => 'custom',
    'cost_items'        => vms_budget_calculator_default_cost_items(),

    'autoscale_items'   => vms_budget_calculator_default_autoscale_items(),
  );
}

function vms_budget_calculator_default_forecast_include_drafts(): int
{
  $user_id = get_current_user_id();
  if ($user_id <= 0) {
    return 1;
  }

  $pref = get_user_meta($user_id, '_vms_dash_include_drafts', true);
  if ($pref === '' || $pref === null) {
    return 1;
  }

  return !empty($pref) ? 1 : 0;
}

function vms_budget_calculator_is_valid_ymd(string $ymd): bool
{
  return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd);
}

function vms_budget_calculator_event_plan_date_key(): string
{
  $key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'date') : '_vms_event_date';
  if ($key === '') $key = '_vms_event_date';
  return $key;
}

function vms_budget_calculator_event_plan_status_key(): string
{
  $key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'status') : '_vms_event_plan_status';
  if ($key === '') $key = '_vms_event_plan_status';
  return $key;
}

function vms_budget_calculator_event_plan_ticket_stats_key(): string
{
  $key = function_exists('vms_meta_key') ? (string) vms_meta_key('event_plan', 'ticket_stats') : '_vms_ticket_stats_v1';
  if ($key === '') $key = '_vms_ticket_stats_v1';
  return $key;
}

function vms_budget_calculator_ensure_inclusion_helpers(): void
{
  if (function_exists('vms_event_plan_should_include') && function_exists('vms_event_plan_get_status')) {
    return;
  }

  $path = dirname(__DIR__) . '/core/event-plan-inclusion.php';
  if (file_exists($path)) {
    require_once $path;
  }
}

function vms_budget_calculator_collect_event_plans_for_year(int $year, bool $include_drafts): array
{
  $year = max(2000, min(2100, $year));
  $start_ymd = sprintf('%04d-01-01', $year);
  $end_ymd = sprintf('%04d-12-31', $year);
  $date_key = vms_budget_calculator_event_plan_date_key();
  $status_key = vms_budget_calculator_event_plan_status_key();

  vms_budget_calculator_ensure_inclusion_helpers();

  $args = array(
    'post_type'      => 'vms_event_plan',
    'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_query'     => array(
      array(
        'key'     => $date_key,
        'value'   => array($start_ymd, $end_ymd),
        'compare' => 'BETWEEN',
        'type'    => 'DATE',
      ),
    ),
  );

  $plan_ids = get_posts($args);
  if (empty($plan_ids)) {
    $args['meta_query'] = array();
    $plan_ids = get_posts($args);
  }

  $rows = array();
  foreach ((array) $plan_ids as $pid_raw) {
    $pid = absint($pid_raw);
    if ($pid <= 0) continue;

    $event_date = (string) get_post_meta($pid, $date_key, true);
    if (!vms_budget_calculator_is_valid_ymd($event_date)) continue;
    if ($event_date < $start_ymd || $event_date > $end_ymd) continue;

    if (function_exists('vms_event_plan_should_include')) {
      if (!vms_event_plan_should_include($pid, 'financial', array(
        'include_drafts' => (bool) $include_drafts,
        'include_cancelled' => false,
      ))) {
        continue;
      }
    }

    $status = '';
    if (function_exists('vms_event_plan_get_status')) {
      $status = (string) vms_event_plan_get_status($pid, 'financial');
    } else {
      $status = sanitize_key((string) get_post_meta($pid, $status_key, true));
    }
    if ($status === 'canceled') $status = 'cancelled';
    if ($status === '') $status = 'draft';

    $status_label = function_exists('vms_event_plan_status_label')
      ? (string) vms_event_plan_status_label($status)
      : ucwords(str_replace(array('_', '-'), ' ', $status));

    $rows[] = array(
      'plan_id'       => $pid,
      'event_date'    => $event_date,
      'status'        => $status,
      'status_label'  => $status_label,
      'venue_id'      => (int) get_post_meta($pid, '_vms_venue_id', true),
      'title'         => (string) get_the_title($pid),
      'edit_link'     => (string) get_edit_post_link($pid, ''),
    );
  }

  usort($rows, function ($a, $b) {
    $ad = (string) ($a['event_date'] ?? '');
    $bd = (string) ($b['event_date'] ?? '');
    if ($ad === $bd) {
      return ((int) ($a['plan_id'] ?? 0)) <=> ((int) ($b['plan_id'] ?? 0));
    }
    return strcmp($ad, $bd);
  });

  return $rows;
}

function vms_budget_calculator_get_plan_ticket_stats(int $plan_id): array
{
  $plan_id = absint($plan_id);
  if ($plan_id <= 0) {
    return array(
      'has_qty' => false,
      'qty_sold' => 0,
      'has_revenue' => false,
      'revenue' => 0.0,
      'provider' => '',
      'computed_at_gmt' => 0,
    );
  }

  static $cache = array();
  if (isset($cache[$plan_id])) {
    return $cache[$plan_id];
  }

  $raw = get_post_meta($plan_id, vms_budget_calculator_event_plan_ticket_stats_key(), true);
  $raw = is_array($raw) ? $raw : array();

  $has_qty = false;
  $qty_sold = 0;
  if (array_key_exists('qty_sold', $raw) && is_numeric($raw['qty_sold'])) {
    $has_qty = true;
    $qty_sold = max(0, (int) $raw['qty_sold']);
  } elseif (array_key_exists('qty', $raw) && is_numeric($raw['qty'])) {
    $has_qty = true;
    $qty_sold = max(0, (int) $raw['qty']);
  }

  $has_revenue = false;
  $revenue = 0.0;
  if (array_key_exists('revenue', $raw) && is_numeric($raw['revenue'])) {
    $has_revenue = true;
    $revenue = max(0.0, (float) $raw['revenue']);
  } elseif (array_key_exists('gross_revenue', $raw) && is_numeric($raw['gross_revenue'])) {
    $has_revenue = true;
    $revenue = max(0.0, (float) $raw['gross_revenue']);
  }

  $out = array(
    'has_qty' => $has_qty,
    'qty_sold' => $qty_sold,
    'has_revenue' => $has_revenue,
    'revenue' => $revenue,
    'provider' => isset($raw['provider']) ? (string) $raw['provider'] : '',
    'computed_at_gmt' => isset($raw['computed_at_gmt']) ? max(0, (int) $raw['computed_at_gmt']) : 0,
  );

  $cache[$plan_id] = $out;
  return $out;
}

function vms_budget_calculator_resolve_plan_band_pay(int $plan_id, string $event_date_ymd, float $fallback_band_pay): array
{
  $band_pay = max(0.0, (float) $fallback_band_pay);
  $source = 'input_assumption';
  $structure = '';
  $flat_fee = null;

  $snapshot = get_post_meta($plan_id, '_vms_comp_snapshot', true);
  if (is_array($snapshot)) {
    $structure = isset($snapshot['structure']) ? sanitize_key((string) $snapshot['structure']) : '';
    if (array_key_exists('flat_fee_amount', $snapshot) && is_numeric($snapshot['flat_fee_amount'])) {
      $flat_fee = (float) $snapshot['flat_fee_amount'];
    }
  }

  if ($structure === '') {
    $structure = sanitize_key((string) get_post_meta($plan_id, '_vms_comp_structure', true));
  }

  if ($flat_fee === null) {
    $flat_raw = get_post_meta($plan_id, '_vms_flat_fee_amount', true);
    if ($flat_raw !== '' && $flat_raw !== null && is_numeric($flat_raw)) {
      $flat_fee = (float) $flat_raw;
    }
  }

  $needs_flat = in_array($structure, array('flat_fee', 'flat_fee_door_split', 'attendance_bonus'), true);
  if ($needs_flat && $flat_fee !== null && $flat_fee > 0) {
    $band_pay = max(0.0, (float) $flat_fee);
    $source = ($structure === 'attendance_bonus') ? 'event_plan_base_pay' : 'event_plan_flat_fee';
  } elseif ($needs_flat && function_exists('vms_get_venue_default_comp_for_date')) {
    $venue_id = (int) get_post_meta($plan_id, '_vms_venue_id', true);
    if ($venue_id > 0 && vms_budget_calculator_is_valid_ymd($event_date_ymd)) {
      $def = (array) vms_get_venue_default_comp_for_date($venue_id, $event_date_ymd);
      $def_struct = isset($def['structure']) ? sanitize_key((string) $def['structure']) : '';
      $def_flat = $def['flat_fee_amount'] ?? null;
      if (in_array($def_struct, array('flat_fee', 'flat_fee_door_split', 'attendance_bonus'), true) && is_numeric($def_flat) && (float) $def_flat > 0) {
        $band_pay = max(0.0, (float) $def_flat);
        $source = ($def_struct === 'attendance_bonus') ? 'venue_default_base_pay' : 'venue_default_flat_fee';
      }
    }
  }

  return array(
    'band_pay' => $band_pay,
    'source' => $source,
    'structure' => $structure,
  );
}

function vms_budget_calculator_band_pay_source_label(string $source): string
{
  if ($source === 'event_plan_base_pay') return __('Event Plan base pay', 'vms');
  if ($source === 'event_plan_flat_fee') return __('Event Plan flat fee', 'vms');
  if ($source === 'venue_default_base_pay') return __('Venue default base pay', 'vms');
  if ($source === 'venue_default_flat_fee') return __('Venue default flat fee', 'vms');
  return __('Calculator assumption', 'vms');
}

function vms_budget_calculator_ticket_source_label(string $source): string
{
  if ($source === 'actual_ticket_stats') return __('Actual ticket stats', 'vms');
  if ($source === 'modeled_past') return __('Modeled (no past stats)', 'vms');
  return __('Modeled forecast', 'vms');
}

function vms_budget_calculator_progress_pct(float $value, float $goal): ?float
{
  if ($goal <= 0.0) return null;
  return ($value / $goal) * 100.0;
}

function vms_budget_calculator_sanitize_cost_items(array $items): array
{
  $out = array();
  foreach ($items as $it) {
    $enabled = !empty($it['enabled']) ? 1 : 0;

    $label = isset($it['label']) ? (string) $it['label'] : '';
    $label = trim(wp_strip_all_tags($label));

    $type = isset($it['type']) ? (string) $it['type'] : 'fixed';
    if ($type !== 'fixed' && $type !== 'per_ticket') $type = 'fixed';

    $amount = isset($it['amount']) ? (float) $it['amount'] : 0.0;

    // Ignore totally empty rows.
    if ($label === '' && abs($amount) < 0.00001) continue;

    // Clamp to non-negative for v1.x
    if ($amount < 0) $amount = 0.0;
    if ($amount > 100000000) $amount = 100000000.0;

    $out[] = array(
      'enabled' => $enabled,
      'label'   => $label,
      'amount'  => $amount,
      'type'    => $type,
    );
  }

  // Always keep at least one row so the UI does not collapse.
  if (empty($out)) $out = vms_budget_calculator_default_cost_items();

  return $out;
}

function vms_budget_calculator_sanitize_autoscale_items(array $items): array
{
  $out = array();

  foreach ($items as $it) {
    $enabled = !empty($it['enabled']) ? 1 : 0;

    $label = isset($it['label']) ? (string) $it['label'] : '';
    $label = trim(wp_strip_all_tags($label));

    $unit_cost = isset($it['unit_cost']) ? (float) $it['unit_cost'] : 0.0;
    if ($unit_cost < 0) $unit_cost = 0.0;
    if ($unit_cost > 100000000) $unit_cost = 100000000.0;

    $per_n = isset($it['per_n']) ? (int) $it['per_n'] : 0;
    if ($per_n < 0) $per_n = 0;
    if ($per_n > 1000000) $per_n = 1000000;

    $min_units = isset($it['min_units']) ? (int) $it['min_units'] : 0;
    if ($min_units < 0) $min_units = 0;
    if ($min_units > 1000) $min_units = 1000;

    $max_units = isset($it['max_units']) ? (int) $it['max_units'] : 0;
    if ($max_units < 0) $max_units = 0;
    if ($max_units > 1000) $max_units = 1000;

    // Ignore totally empty rows.
    if ($label === '' && abs($unit_cost) < 0.00001 && $per_n === 0 && $min_units === 0) continue;

    $out[] = array(
      'enabled'    => $enabled,
      'label'      => $label,
      'unit_cost'  => $unit_cost,
      'per_n'      => $per_n,
      'min_units'  => $min_units,
      'max_units'  => $max_units,
    );
  }

  if (empty($out)) $out = vms_budget_calculator_default_autoscale_items();

  return $out;
}

function vms_budget_calculator_read_input(array $defaults): array
{
  $in = $defaults;

  $in['mode'] = (isset($_POST['mode']) && $_POST['mode'] === 'period') ? 'period' : 'single';

  $in['events_count'] = (int) ($_POST['events_count'] ?? $defaults['events_count']);
  if ($in['events_count'] < 1) $in['events_count'] = 1;
  if ($in['events_count'] > 365) $in['events_count'] = 365;

  $in['tickets_sold']  = (int) ($_POST['tickets_sold'] ?? $defaults['tickets_sold']);
  if ($in['tickets_sold'] < 0) $in['tickets_sold'] = 0;
  if ($in['tickets_sold'] > 1000000) $in['tickets_sold'] = 1000000;

  $in['ticket_price']  = vms_budget_parse_money($_POST['ticket_price'] ?? $defaults['ticket_price']);
  $in['attendance_percent'] = vms_budget_parse_percent($_POST['attendance_percent'] ?? $defaults['attendance_percent'], 0.0, 200.0);
  $in['bar_per_head']  = vms_budget_parse_money($_POST['bar_per_head'] ?? $defaults['bar_per_head']);
  $in['other_revenue'] = vms_budget_parse_money($_POST['other_revenue'] ?? $defaults['other_revenue']);

  $in['band_pay']      = vms_budget_parse_money($_POST['band_pay'] ?? $defaults['band_pay']);

  $in['fee_percent']   = vms_budget_parse_percent($_POST['fee_percent'] ?? $defaults['fee_percent'], 0.0, 100.0);
  $in['fee_fixed']     = vms_budget_parse_money($_POST['fee_fixed'] ?? $defaults['fee_fixed']);

  $in['target_profit'] = vms_budget_parse_money($_POST['target_profit'] ?? $defaults['target_profit']);

  $in['forecast_year'] = (int) ($_POST['forecast_year'] ?? $defaults['forecast_year']);
  if ($in['forecast_year'] < 2000) $in['forecast_year'] = 2000;
  if ($in['forecast_year'] > 2100) $in['forecast_year'] = 2100;

  $in['annual_goal_revenue'] = vms_budget_parse_money($_POST['annual_goal_revenue'] ?? $defaults['annual_goal_revenue']);
  $in['annual_goal_profit']  = vms_budget_parse_money($_POST['annual_goal_profit'] ?? $defaults['annual_goal_profit']);
  $in['annual_include_drafts'] = !empty($_POST['annual_include_drafts']) ? 1 : 0;

  // Clamp money values (avoid wild numbers from bad input)
  foreach (array('ticket_price','bar_per_head','other_revenue','band_pay','fee_fixed','target_profit','annual_goal_revenue','annual_goal_profit') as $k) {
    if ($in[$k] < 0) $in[$k] = 0.0;
    if ($in[$k] > 100000000) $in[$k] = 100000000.0;
  }

  $profiles = vms_budget_calculator_cost_profiles();

  $in['cost_profile'] = isset($_POST['cost_profile']) ? sanitize_key((string) $_POST['cost_profile']) : $defaults['cost_profile'];
  if (!isset($profiles[$in['cost_profile']])) $in['cost_profile'] = 'custom';

  $action = isset($_POST['vms_budget_action']) ? sanitize_key((string) $_POST['vms_budget_action']) : '';

  // Read cost items from POST if present (regardless of profile), so changing one input does not wipe custom rows.
  $enableds = isset($_POST['cost_enabled']) && is_array($_POST['cost_enabled']) ? $_POST['cost_enabled'] : array();
  $labels   = isset($_POST['cost_label']) && is_array($_POST['cost_label']) ? $_POST['cost_label'] : array();
  $amounts  = isset($_POST['cost_amount']) && is_array($_POST['cost_amount']) ? $_POST['cost_amount'] : array();
  $types    = isset($_POST['cost_type']) && is_array($_POST['cost_type']) ? $_POST['cost_type'] : array();

  $max_rows = max(count($labels), count($amounts), count($types), count($enableds));
  $max_rows = min($max_rows, 20); // hard cap for safety

  if ($max_rows > 0) {
    $items = array();
    for ($i = 0; $i < $max_rows; $i++) {
      $items[] = array(
        'enabled' => !empty($enableds[$i]) ? 1 : 0,
        'label'   => isset($labels[$i]) ? (string) $labels[$i] : '',
        'amount'  => vms_budget_parse_money($amounts[$i] ?? 0),
        'type'    => isset($types[$i]) ? (string) $types[$i] : 'fixed',
      );
    }
    $in['cost_items'] = vms_budget_calculator_sanitize_cost_items($items);
  } else {
    $in['cost_items'] = vms_budget_calculator_sanitize_cost_items($profiles[$in['cost_profile']]['items']);
  }

  // Read auto-scaling items from POST (or default).
  $a_enabled = isset($_POST['auto_enabled']) && is_array($_POST['auto_enabled']) ? $_POST['auto_enabled'] : array();
  $a_label   = isset($_POST['auto_label']) && is_array($_POST['auto_label']) ? $_POST['auto_label'] : array();
  $a_unit    = isset($_POST['auto_unit_cost']) && is_array($_POST['auto_unit_cost']) ? $_POST['auto_unit_cost'] : array();
  $a_pern    = isset($_POST['auto_per_n']) && is_array($_POST['auto_per_n']) ? $_POST['auto_per_n'] : array();
  $a_min     = isset($_POST['auto_min_units']) && is_array($_POST['auto_min_units']) ? $_POST['auto_min_units'] : array();
  $a_max     = isset($_POST['auto_max_units']) && is_array($_POST['auto_max_units']) ? $_POST['auto_max_units'] : array();

  $a_rows = max(count($a_label), count($a_unit), count($a_pern), count($a_min), count($a_max), count($a_enabled));
  $a_rows = min($a_rows, 12);

  if ($a_rows > 0) {
    $items = array();
    for ($i = 0; $i < $a_rows; $i++) {
      $items[] = array(
        'enabled'   => !empty($a_enabled[$i]) ? 1 : 0,
        'label'     => isset($a_label[$i]) ? (string) $a_label[$i] : '',
        'unit_cost' => vms_budget_parse_money($a_unit[$i] ?? 0),
        'per_n'     => (int) ($a_pern[$i] ?? 0),
        'min_units' => (int) ($a_min[$i] ?? 0),
        'max_units' => (int) ($a_max[$i] ?? 0),
      );
    }
    $in['autoscale_items'] = vms_budget_calculator_sanitize_autoscale_items($items);
  } else {
    $in['autoscale_items'] = vms_budget_calculator_sanitize_autoscale_items($defaults['autoscale_items']);
  }

  // If applying a profile, replace only the cost_items list (not the auto-staff rules).
  if ($action === 'apply_profile' && isset($profiles[$in['cost_profile']])) {
    $in['cost_items'] = vms_budget_calculator_sanitize_cost_items($profiles[$in['cost_profile']]['items']);
  }

  return $in;
}

function vms_budget_calculator_cost_totals(array $items): array
{
  $fixed = 0.0;
  $per_ticket = 0.0;

  foreach ($items as $it) {
    if (empty($it['enabled'])) continue;

    $amt = isset($it['amount']) ? (float) $it['amount'] : 0.0;
    $type = isset($it['type']) ? (string) $it['type'] : 'fixed';

    if ($type === 'per_ticket') $per_ticket += $amt;
    else $fixed += $amt;
  }

  return array('fixed' => $fixed, 'per_ticket' => $per_ticket);
}

function vms_budget_calculator_compute_autoscale(array $autoscale_items, int $headcount): array
{
  $total = 0.0;
  $rows = array();

  if ($headcount <= 0) {
    return array('total' => 0.0, 'rows' => array());
  }

  foreach ($autoscale_items as $it) {
    if (empty($it['enabled'])) continue;

    $label = isset($it['label']) ? (string) $it['label'] : '';
    $label = trim($label);
    if ($label === '') continue;

    $unit_cost = isset($it['unit_cost']) ? (float) $it['unit_cost'] : 0.0;
    if ($unit_cost < 0) $unit_cost = 0.0;

    $per_n = isset($it['per_n']) ? (int) $it['per_n'] : 0;
    $min_units = isset($it['min_units']) ? (int) $it['min_units'] : 0;
    $max_units = isset($it['max_units']) ? (int) $it['max_units'] : 0;

    $qty = 0;
    if ($per_n > 0) {
      $qty = (int) ceil($headcount / $per_n);
    }

    if ($qty < $min_units) $qty = $min_units;
    if ($max_units > 0 && $qty > $max_units) $qty = $max_units;

    if ($qty <= 0) continue;

    $row_total = $qty * $unit_cost;
    $total += $row_total;

    $rows[] = array(
      'label'     => $label,
      'qty'       => $qty,
      'per_n'     => $per_n,
      'unit_cost' => $unit_cost,
      'total'     => $row_total,
    );
  }

  return array('total' => $total, 'rows' => $rows);
}

function vms_budget_calculator_compute_core(array $in): array
{
  $n = (int) $in['tickets_sold'];

  $attendance_factor = ((float) $in['attendance_percent']) / 100.0;
  if ($attendance_factor < 0) $attendance_factor = 0.0;
  if ($attendance_factor > 2.0) $attendance_factor = 2.0;

  $headcount = (int) ceil($n * $attendance_factor);
  if ($headcount < 0) $headcount = 0;

  $totals = vms_budget_calculator_cost_totals($in['cost_items']);
  $fixed_costs_other = $totals['fixed'];
  $per_ticket_costs  = $totals['per_ticket'];

  $ticket_revenue = $in['ticket_price'] * $n;
  $bar_revenue    = $in['bar_per_head'] * $headcount;
  $other_revenue  = $in['other_revenue'];

  $gross_revenue  = $ticket_revenue + $bar_revenue + $other_revenue;

  $fee_percent_amt = $ticket_revenue * ($in['fee_percent'] / 100.0);
  $fee_fixed_amt   = $in['fee_fixed'] * $n;

  $variable_costs  = $per_ticket_costs * $n;

  $auto = vms_budget_calculator_compute_autoscale($in['autoscale_items'], $headcount);
  $autoscale_costs = (float) $auto['total'];

  $costs_ex_band = $fixed_costs_other + $variable_costs + $autoscale_costs + $fee_percent_amt + $fee_fixed_amt;
  $total_costs   = $costs_ex_band + $in['band_pay'];

  $net_profit = $gross_revenue - $total_costs;

  // Max band pay that still hits target profit (per event)
  $max_band_pay_for_target = $gross_revenue - ($fixed_costs_other + $variable_costs + $autoscale_costs + $fee_percent_amt + $fee_fixed_amt) - $in['target_profit'];
  if ($max_band_pay_for_target < 0) $max_band_pay_for_target = 0.0;

  // Ticket price needed for target profit and break-even (tickets held constant).
  $ticket_price_needed = null;
  $break_even_price = null;

  $n_safe = max(1, $n);
  $den_price = $n_safe * (1.0 - ($in['fee_percent'] / 100.0));

  if ($den_price > 0) {
    // Non-ticket revenue is bar + other. Bar revenue is based on headcount.
    $non_ticket_rev = ($bar_revenue + $other_revenue);

    $base = $fixed_costs_other + $variable_costs + $autoscale_costs + $in['band_pay'] + $fee_fixed_amt - $non_ticket_rev;

    $need = $base + $in['target_profit'];
    $p = $need / $den_price;
    if ($p < 0) $p = 0.0;
    $ticket_price_needed = $p;

    $be = $base / $den_price;
    if ($be < 0) $be = 0.0;
    $break_even_price = $be;
  }

  return array(
    'tickets_sold'              => $n,
    'headcount'                => $headcount,

    'ticket_revenue'            => $ticket_revenue,
    'bar_revenue'               => $bar_revenue,
    'other_revenue'             => $other_revenue,
    'gross_revenue'             => $gross_revenue,

    'fee_percent_amt'           => $fee_percent_amt,
    'fee_fixed_amt'             => $fee_fixed_amt,

    'fixed_costs_other'         => $fixed_costs_other,
    'per_ticket_costs'          => $per_ticket_costs,
    'variable_costs'            => $variable_costs,

    'autoscale_costs'           => $autoscale_costs,
    'autoscale_rows'            => $auto['rows'],

    'costs_ex_band'             => $costs_ex_band,
    'total_costs'               => $total_costs,

    'net_profit'                => $net_profit,

    'max_band_pay_for_target'   => $max_band_pay_for_target,
    'ticket_price_needed'       => $ticket_price_needed,
    'break_even_price'          => $break_even_price,
  );
}

function vms_budget_calculator_compute(array $in): array
{
  // Wrapper that adds “tickets needed” values.
  // This must not be inside vms_budget_calculator_compute_core() to avoid recursion.
  $r = vms_budget_calculator_compute_core($in);

  $r['tickets_needed_be'] = vms_budget_calculator_find_tickets_for_profit($in, 0.0, 5000);
  $r['tickets_needed_target'] = vms_budget_calculator_find_tickets_for_profit($in, (float) $in['target_profit'], 5000);

  return $r;
}

function vms_budget_calculator_profit_for_tickets(array $in, int $tickets): array
{
  $tmp = $in;
  $tmp['tickets_sold'] = max(0, $tickets);
  return vms_budget_calculator_compute_core($tmp);
}

function vms_budget_calculator_ticket_price_needed_for(array $in, int $tickets, float $target_profit): ?float
{
  $tmp = $in;
  $tmp['tickets_sold'] = max(1, $tickets);

  $n = (int) $tmp['tickets_sold'];

  $attendance_factor = ((float) $tmp['attendance_percent']) / 100.0;
  if ($attendance_factor < 0) $attendance_factor = 0.0;
  if ($attendance_factor > 2.0) $attendance_factor = 2.0;

  $headcount = (int) ceil($n * $attendance_factor);
  if ($headcount < 0) $headcount = 0;

  $totals = vms_budget_calculator_cost_totals($tmp['cost_items']);
  $fixed_costs_other = $totals['fixed'];
  $per_ticket_costs  = $totals['per_ticket'];

  $bar_revenue    = $tmp['bar_per_head'] * $headcount;
  $other_revenue  = $tmp['other_revenue'];
  $non_ticket_rev = $bar_revenue + $other_revenue;

  $fee_fixed_amt   = $tmp['fee_fixed'] * $n;
  $variable_costs  = $per_ticket_costs * $n;

  $auto = vms_budget_calculator_compute_autoscale($tmp['autoscale_items'], $headcount);
  $autoscale_costs = (float) $auto['total'];

  $den = $n * (1.0 - ($tmp['fee_percent'] / 100.0));
  if ($den <= 0) return null;

  $base = $fixed_costs_other + $variable_costs + $autoscale_costs + $tmp['band_pay'] + $fee_fixed_amt - $non_ticket_rev;
  $need = $base + $target_profit;

  $p = $need / $den;
  if ($p < 0) $p = 0.0;
  return $p;
}

function vms_budget_calculator_find_tickets_for_profit(array $in, float $target_profit, int $max_tickets = 5000): ?int
{
  // Conservative linear search.
  // For v1.x scale and the small max, this is fast and avoids monotonic assumptions.
  $max_tickets = max(0, min(20000, $max_tickets));

  for ($t = 0; $t <= $max_tickets; $t++) {
    $r = vms_budget_calculator_profit_for_tickets($in, $t);
    if (!isset($r['net_profit'])) continue;
    if ((float) $r['net_profit'] >= $target_profit) return $t;
  }

  return null;
}

function vms_budget_calculator_collect_actual_ticket_revenue(int $year, bool $include_drafts, string $end_ymd = ''): array
{
  $rows = vms_budget_calculator_collect_event_plans_for_year($year, $include_drafts);
  $has_end = vms_budget_calculator_is_valid_ymd($end_ymd);

  $events_total = 0;
  $events_with_revenue = 0;
  $revenue_total = 0.0;

  foreach ($rows as $row) {
    $event_date = isset($row['event_date']) ? (string) $row['event_date'] : '';
    if ($has_end && $event_date > $end_ymd) continue;

    $events_total++;
    $stats = vms_budget_calculator_get_plan_ticket_stats((int) ($row['plan_id'] ?? 0));
    if (empty($stats['has_revenue'])) continue;

    $events_with_revenue++;
    $revenue_total += (float) ($stats['revenue'] ?? 0.0);
  }

  return array(
    'year' => $year,
    'events_total' => $events_total,
    'events_with_revenue' => $events_with_revenue,
    'revenue' => $revenue_total,
  );
}

function vms_budget_calculator_compute_annual_forecast(array $in): array
{
  $year = isset($in['forecast_year']) ? (int) $in['forecast_year'] : (int) wp_date('Y');
  $year = max(2000, min(2100, $year));
  $include_drafts = !empty($in['annual_include_drafts']);
  $today_ymd = wp_date('Y-m-d');
  $current_year = (int) wp_date('Y');

  $plans = vms_budget_calculator_collect_event_plans_for_year($year, $include_drafts);

  $event_count = 0;
  $past_event_count = 0;
  $future_event_count = 0;
  $past_actual_ticket_events = 0;
  $past_missing_ticket_stats_events = 0;
  $events_using_plan_band_pay = 0;

  $forecast_ticket_revenue = 0.0;
  $forecast_gross_revenue = 0.0;
  $forecast_total_costs = 0.0;
  $forecast_net_profit = 0.0;
  $forecast_band_pay_total = 0.0;

  $past_actual_ticket_revenue = 0.0;
  $past_actual_ticket_qty = 0;

  $rows = array();

  foreach ($plans as $plan) {
    $plan_id = (int) ($plan['plan_id'] ?? 0);
    if ($plan_id <= 0) continue;

    $event_count++;
    $event_date = (string) ($plan['event_date'] ?? '');
    $is_past = ($event_date !== '' && $event_date < $today_ymd);
    if ($year < $current_year) $is_past = true;
    if ($year > $current_year) $is_past = false;

    if ($is_past) $past_event_count++;
    else $future_event_count++;

    $band_pay = vms_budget_calculator_resolve_plan_band_pay($plan_id, $event_date, (float) ($in['band_pay'] ?? 0.0));
    if (($band_pay['source'] ?? '') !== 'input_assumption') {
      $events_using_plan_band_pay++;
    }

    $stats = vms_budget_calculator_get_plan_ticket_stats($plan_id);

    $tmp = $in;
    $tmp['band_pay'] = (float) ($band_pay['band_pay'] ?? 0.0);
    if ($is_past && !empty($stats['has_qty'])) {
      $tmp['tickets_sold'] = max(0, (int) ($stats['qty_sold'] ?? 0));
    }

    $model = vms_budget_calculator_compute_core($tmp);

    $ticket_revenue = (float) ($model['ticket_revenue'] ?? 0.0);
    $ticket_source = 'modeled_forecast';

    if ($is_past && !empty($stats['has_revenue'])) {
      $ticket_revenue = max(0.0, (float) ($stats['revenue'] ?? 0.0));
      $ticket_source = 'actual_ticket_stats';
      $past_actual_ticket_events++;
      $past_actual_ticket_revenue += $ticket_revenue;
      if (!empty($stats['has_qty'])) {
        $past_actual_ticket_qty += max(0, (int) ($stats['qty_sold'] ?? 0));
      }
    } elseif ($is_past) {
      $ticket_source = 'modeled_past';
      $past_missing_ticket_stats_events++;
    }

    $fee_percent_amt = $ticket_revenue * (((float) ($tmp['fee_percent'] ?? 0.0)) / 100.0);
    $fee_fixed_amt = (float) ($model['fee_fixed_amt'] ?? 0.0);
    $fixed_other = (float) ($model['fixed_costs_other'] ?? 0.0);
    $variable_costs = (float) ($model['variable_costs'] ?? 0.0);
    $autoscale_costs = (float) ($model['autoscale_costs'] ?? 0.0);
    $bar_revenue = (float) ($model['bar_revenue'] ?? 0.0);
    $other_revenue = (float) ($model['other_revenue'] ?? 0.0);

    $gross_revenue = $ticket_revenue + $bar_revenue + $other_revenue;
    $total_costs = $fixed_other + $variable_costs + $autoscale_costs + $fee_percent_amt + $fee_fixed_amt + (float) $tmp['band_pay'];
    $net_profit = $gross_revenue - $total_costs;

    $forecast_ticket_revenue += $ticket_revenue;
    $forecast_gross_revenue += $gross_revenue;
    $forecast_total_costs += $total_costs;
    $forecast_net_profit += $net_profit;
    $forecast_band_pay_total += (float) $tmp['band_pay'];

    $rows[] = array(
      'plan_id' => $plan_id,
      'event_date' => $event_date,
      'title' => (string) ($plan['title'] ?? ''),
      'status' => (string) ($plan['status'] ?? ''),
      'status_label' => (string) ($plan['status_label'] ?? ''),
      'edit_link' => (string) ($plan['edit_link'] ?? ''),
      'is_past' => $is_past ? 1 : 0,
      'band_pay' => (float) $tmp['band_pay'],
      'band_pay_source' => (string) ($band_pay['source'] ?? 'input_assumption'),
      'band_pay_source_label' => vms_budget_calculator_band_pay_source_label((string) ($band_pay['source'] ?? 'input_assumption')),
      'ticket_revenue' => $ticket_revenue,
      'ticket_source' => $ticket_source,
      'ticket_source_label' => vms_budget_calculator_ticket_source_label($ticket_source),
      'gross_revenue' => $gross_revenue,
      'net_profit' => $net_profit,
    );
  }

  $goal_revenue = max(0.0, (float) ($in['annual_goal_revenue'] ?? 0.0));
  $goal_profit = max(0.0, (float) ($in['annual_goal_profit'] ?? 0.0));
  $revenue_goal_progress_pct = vms_budget_calculator_progress_pct($forecast_gross_revenue, $goal_revenue);
  $profit_goal_progress_pct = vms_budget_calculator_progress_pct($forecast_net_profit, $goal_profit);

  $revenue_goal_gap = ($goal_revenue > 0.0) ? ($goal_revenue - $forecast_gross_revenue) : null;
  $profit_goal_gap = ($goal_profit > 0.0) ? ($goal_profit - $forecast_net_profit) : null;

  $prior_year = max(2000, $year - 1);
  $prior_full = vms_budget_calculator_collect_actual_ticket_revenue($prior_year, $include_drafts);
  $prior_to_date = array('revenue' => 0.0, 'events_with_revenue' => 0, 'events_total' => 0);

  if ($year === $current_year) {
    try {
      $today = new DateTimeImmutable('today', wp_timezone());
      $prior_cutoff = $today->modify('-1 year')->format('Y-m-d');
      $prior_to_date = vms_budget_calculator_collect_actual_ticket_revenue($prior_year, $include_drafts, $prior_cutoff);
    } catch (Throwable $e) {
      $prior_to_date = array('revenue' => 0.0, 'events_with_revenue' => 0, 'events_total' => 0);
    }
  }

  $display_cap = 60;

  return array(
    'year' => $year,
    'include_drafts' => $include_drafts ? 1 : 0,
    'event_count' => $event_count,
    'past_event_count' => $past_event_count,
    'future_event_count' => $future_event_count,
    'past_actual_ticket_events' => $past_actual_ticket_events,
    'past_missing_ticket_stats_events' => $past_missing_ticket_stats_events,
    'events_using_plan_band_pay' => $events_using_plan_band_pay,
    'forecast_ticket_revenue' => $forecast_ticket_revenue,
    'forecast_gross_revenue' => $forecast_gross_revenue,
    'forecast_total_costs' => $forecast_total_costs,
    'forecast_net_profit' => $forecast_net_profit,
    'forecast_band_pay_total' => $forecast_band_pay_total,
    'goal_revenue' => $goal_revenue,
    'goal_profit' => $goal_profit,
    'revenue_goal_progress_pct' => $revenue_goal_progress_pct,
    'profit_goal_progress_pct' => $profit_goal_progress_pct,
    'revenue_goal_gap' => $revenue_goal_gap,
    'profit_goal_gap' => $profit_goal_gap,
    'past_actual_ticket_revenue' => $past_actual_ticket_revenue,
    'past_actual_ticket_qty' => $past_actual_ticket_qty,
    'prior_year' => $prior_year,
    'prior_year_actual_ticket_revenue' => (float) ($prior_full['revenue'] ?? 0.0),
    'prior_year_actual_ticket_events' => (int) ($prior_full['events_with_revenue'] ?? 0),
    'prior_year_total_events' => (int) ($prior_full['events_total'] ?? 0),
    'prior_year_to_date_actual_ticket_revenue' => (float) ($prior_to_date['revenue'] ?? 0.0),
    'prior_year_to_date_actual_ticket_events' => (int) ($prior_to_date['events_with_revenue'] ?? 0),
    'rows' => array_slice($rows, 0, $display_cap),
    'rows_hidden' => max(0, count($rows) - $display_cap),
  );
}

function vms_budget_calculator_field_money(string $name, string $label, $value): void
{
  echo '<p>';
  echo '<label for="' . esc_attr($name) . '"><strong>' . esc_html($label) . '</strong></label><br>';
  echo '<input class="regular-text" type="text" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '">';
  echo '</p>';
}

function vms_budget_calculator_field_int(string $name, string $label, $value, int $min = 0, int $max = 1000000): void
{
  echo '<p>';
  echo '<label for="' . esc_attr($name) . '"><strong>' . esc_html($label) . '</strong></label><br>';
  echo '<input class="small-text" type="number" min="' . esc_attr((string) $min) . '" max="' . esc_attr((string) $max) . '" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '">';
  echo '</p>';
}

function vms_budget_calculator_field_select(string $name, string $label, string $value, array $options): void
{
  echo '<p>';
  echo '<label for="' . esc_attr($name) . '"><strong>' . esc_html($label) . '</strong></label><br>';
  echo '<select id="' . esc_attr($name) . '" name="' . esc_attr($name) . '">';
  foreach ($options as $k => $v) {
    $sel = selected((string) $value, (string) $k, false);
    echo '<option value="' . esc_attr((string) $k) . '"' . $sel . '>' . esc_html((string) $v) . '</option>';
  }
  echo '</select>';
  echo '</p>';
}

function vms_budget_calculator_render_cost_items(array $items): void
{
  // Always show a few extra blank rows for custom entries.
  $rows = $items;
  while (count($rows) < 12) {
    $rows[] = array('enabled' => 0, 'label' => '', 'amount' => 0.0, 'type' => 'fixed');
  }

  echo '<table class="widefat striped">';
  echo '<thead><tr>';
  echo '<th>' . esc_html__('Use', 'vms') . '</th>';
  echo '<th>' . esc_html__('Cost item', 'vms') . '</th>';
  echo '<th>' . esc_html__('Amount', 'vms') . '</th>';
  echo '<th>' . esc_html__('Type', 'vms') . '</th>';
  echo '</tr></thead>';
  echo '<tbody>';

  foreach ($rows as $i => $it) {
    $enabled = !empty($it['enabled']) ? 1 : 0;
    $label   = isset($it['label']) ? (string) $it['label'] : '';
    $amount  = isset($it['amount']) ? (float) $it['amount'] : 0.0;
    $type    = isset($it['type']) ? (string) $it['type'] : 'fixed';
    if ($type !== 'fixed' && $type !== 'per_ticket') $type = 'fixed';

    echo '<tr>';
    echo '<td><input type="checkbox" name="cost_enabled[' . esc_attr((string) $i) . ']" value="1" ' . checked(1, $enabled, false) . '></td>';
    echo '<td><input class="regular-text" type="text" name="cost_label[' . esc_attr((string) $i) . ']" value="' . esc_attr($label) . '" placeholder="' . esc_attr__('e.g., Facebook ads', 'vms') . '"></td>';
    echo '<td><input class="small-text" type="text" name="cost_amount[' . esc_attr((string) $i) . ']" value="' . esc_attr((string) $amount) . '"></td>';
    echo '<td>';
    echo '<select name="cost_type[' . esc_attr((string) $i) . ']">';
    echo '<option value="fixed"' . selected('fixed', $type, false) . '>' . esc_html__('Fixed (per event)', 'vms') . '</option>';
    echo '<option value="per_ticket"' . selected('per_ticket', $type, false) . '>' . esc_html__('Per ticket', 'vms') . '</option>';
    echo '</select>';
    echo '</td>';
    echo '</tr>';
  }

  echo '</tbody>';
  echo '</table>';

  echo '<p class="description">' . esc_html__('Tip: Uncheck items that do not apply. “Per ticket” items scale with tickets sold.', 'vms') . '</p>';
}

function vms_budget_calculator_render_autoscale_items(array $items): void
{
  $rows = $items;
  while (count($rows) < 8) {
    $rows[] = array('enabled' => 0, 'label' => '', 'unit_cost' => 0.0, 'per_n' => 0, 'min_units' => 0, 'max_units' => 0);
  }

  echo '<table class="widefat striped">';
  echo '<thead><tr>';
  echo '<th>' . esc_html__('Use', 'vms') . '</th>';
  echo '<th>' . esc_html__('Role / line item', 'vms') . '</th>';
  echo '<th>' . esc_html__('Unit cost (per staff)', 'vms') . '</th>';
  echo '<th>' . esc_html__('Heads per 1', 'vms') . '</th>';
  echo '<th>' . esc_html__('Min #', 'vms') . '</th>';
  echo '<th>' . esc_html__('Max #', 'vms') . '</th>';
  echo '</tr></thead>';
  echo '<tbody>';

  foreach ($rows as $i => $it) {
    $enabled = !empty($it['enabled']) ? 1 : 0;
    $label   = isset($it['label']) ? (string) $it['label'] : '';
    $unit    = isset($it['unit_cost']) ? (float) $it['unit_cost'] : 0.0;
    $per_n   = isset($it['per_n']) ? (int) $it['per_n'] : 0;
    $min_u   = isset($it['min_units']) ? (int) $it['min_units'] : 0;
    $max_u   = isset($it['max_units']) ? (int) $it['max_units'] : 0;

    echo '<tr>';
    echo '<td><input type="checkbox" name="auto_enabled[' . esc_attr((string) $i) . ']" value="1" ' . checked(1, $enabled, false) . '></td>';
    echo '<td><input class="regular-text" type="text" name="auto_label[' . esc_attr((string) $i) . ']" value="' . esc_attr($label) . '" placeholder="' . esc_attr__('e.g., Bartender', 'vms') . '"></td>';
    echo '<td><input class="small-text" type="text" name="auto_unit_cost[' . esc_attr((string) $i) . ']" value="' . esc_attr((string) $unit) . '"></td>';
    echo '<td><input class="small-text" type="number" min="0" max="1000000" name="auto_per_n[' . esc_attr((string) $i) . ']" value="' . esc_attr((string) $per_n) . '"></td>';
    echo '<td><input class="small-text" type="number" min="0" max="1000" name="auto_min_units[' . esc_attr((string) $i) . ']" value="' . esc_attr((string) $min_u) . '"></td>';
    echo '<td><input class="small-text" type="number" min="0" max="1000" name="auto_max_units[' . esc_attr((string) $i) . ']" value="' . esc_attr((string) $max_u) . '"></td>';
    echo '</tr>';
  }

  echo '</tbody>';
  echo '</table>';

  echo '<p class="description">' . esc_html__('How it works: staff count = ceil(headcount ÷ “Heads per 1”), then clamped to Min/Max. Set Max # to 0 for no cap.', 'vms') . '</p>';
}

function vms_render_budget_calculator_page(): void
{
  if (!current_user_can('manage_options')) {
    wp_die(__('You do not have permission to access this page.', 'vms'));
  }

  $defaults = vms_budget_calculator_defaults();
  $in       = $defaults;
  $results  = null;
  $annual_results = null;
  $did_calc = false;

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_admin_referer('vms_budget_calc');

    $in = vms_budget_calculator_read_input($defaults);

    $action = isset($_POST['vms_budget_action']) ? sanitize_key((string) $_POST['vms_budget_action']) : '';
    if ($action === 'calculate') {
      $results  = vms_budget_calculator_compute($in);
      $annual_results = vms_budget_calculator_compute_annual_forecast($in);
      $did_calc = true;
    }
  } else {
    // Initial view uses defaults.
    $profiles = vms_budget_calculator_cost_profiles();
    $in['cost_items'] = vms_budget_calculator_sanitize_cost_items($profiles[$in['cost_profile']]['items']);
    $in['autoscale_items'] = vms_budget_calculator_sanitize_autoscale_items($defaults['autoscale_items']);
  }

  $profiles = vms_budget_calculator_cost_profiles();
  $profile_options = array();
  foreach ($profiles as $k => $p) $profile_options[$k] = $p['label'];

  echo '<div class="wrap vms-bcalc">';
  echo '<h1>' . esc_html__('Budget Calculator', 'vms') . '</h1>';
  echo '<p class="description">' . esc_html__('Decision-support only. Use this to sanity-check band pay and ticket pricing before committing.', 'vms') . '</p>';

  echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=vms-budget-calculator')) . '">';
  wp_nonce_field('vms_budget_calc');

  echo '<h2 class="title">' . esc_html__('Inputs', 'vms') . '</h2>';

  echo '<h3>' . esc_html__('Scope', 'vms') . '</h3>';
  echo '<p>';
  echo '<label><input type="radio" name="mode" value="single" ' . checked('single', $in['mode'], false) . '> ' . esc_html__('Single Event', 'vms') . '</label>';
  echo '&nbsp;&nbsp;';
  echo '<label><input type="radio" name="mode" value="period" ' . checked('period', $in['mode'], false) . '> ' . esc_html__('Multiple Events (same assumptions)', 'vms') . '</label>';
  echo '</p>';

  echo '<p>';
  echo '<label for="events_count"><strong>' . esc_html__('Events (if multiple):', 'vms') . '</strong></label><br>';
  echo '<input class="small-text" type="number" min="1" max="365" id="events_count" name="events_count" value="' . esc_attr((string) $in['events_count']) . '">';
  echo '</p>';

  echo '<hr>';

  echo '<h3>' . esc_html__('Revenue assumptions', 'vms') . '</h3>';
  vms_budget_calculator_field_int('tickets_sold', __('Tickets sold (estimate)', 'vms'), $in['tickets_sold'], 0, 1000000);
  vms_budget_calculator_field_money('ticket_price', __('Ticket price', 'vms'), $in['ticket_price']);

  echo '<p>';
  echo '<label for="attendance_percent"><strong>' . esc_html__('Expected attendance % (vs tickets sold)', 'vms') . '</strong></label><br>';
  echo '<input class="small-text" type="number" min="0" max="200" step="0.1" id="attendance_percent" name="attendance_percent" value="' . esc_attr((string) $in['attendance_percent']) . '"> ';
  echo '<span class="description">' . esc_html__('Example: 95 means expect 95 people for 100 tickets sold.', 'vms') . '</span>';
  echo '</p>';

  vms_budget_calculator_field_money('bar_per_head', __('Bar revenue per head', 'vms'), $in['bar_per_head']);
  vms_budget_calculator_field_money('other_revenue', __('Other revenue (per event)', 'vms'), $in['other_revenue']);

  echo '<hr>';

  echo '<h3>' . esc_html__('Music Vendor / talent', 'vms') . '</h3>';
  vms_budget_calculator_field_money('band_pay', __('Music Vendor pay (per event)', 'vms'), $in['band_pay']);
  vms_budget_calculator_field_money('target_profit', __('Target profit (per event)', 'vms'), $in['target_profit']);

  echo '<hr>';

  echo '<h3>' . esc_html__('Fees (ticket revenue only)', 'vms') . '</h3>';
  vms_budget_calculator_field_money('fee_fixed', __('Fee per ticket', 'vms'), $in['fee_fixed']);
  vms_budget_calculator_field_money('fee_percent', __('Fee percent (e.g., 2.9)', 'vms'), $in['fee_percent']);

  echo '<hr>';

  echo '<h3>' . esc_html__('Annual goals + live forecast', 'vms') . '</h3>';
  vms_budget_calculator_field_int('forecast_year', __('Forecast year', 'vms'), $in['forecast_year'], 2000, 2100);
  vms_budget_calculator_field_money('annual_goal_revenue', __('Annual revenue goal', 'vms'), $in['annual_goal_revenue']);
  vms_budget_calculator_field_money('annual_goal_profit', __('Annual net profit goal', 'vms'), $in['annual_goal_profit']);

  echo '<p>';
  echo '<label><input type="checkbox" name="annual_include_drafts" value="1" ' . checked(1, !empty($in['annual_include_drafts']) ? 1 : 0, false) . '> ' . esc_html__('Include Draft/Ready/Tentative/Confirmed Event Plans in annual forecast', 'vms') . '</label>';
  echo '</p>';
  echo '<p class="description">' . esc_html__('Forecast uses canonical financial inclusion behavior and current Event Plan data (status/date/pay). Decision-support only: no automatic payment or money actions are performed.', 'vms') . '</p>';

  echo '<hr>';

  echo '<h3>' . esc_html__('Cost components', 'vms') . '</h3>';
  vms_budget_calculator_field_select('cost_profile', __('Cost profile (preset)', 'vms'), $in['cost_profile'], $profile_options);

  echo '<p>';
  echo '<button class="button" type="submit" name="vms_budget_action" value="apply_profile">' . esc_html__('Apply profile', 'vms') . '</button>';
  echo '</p>';

  vms_budget_calculator_render_cost_items($in['cost_items']);

  echo '<details>';
  echo '<summary><strong>' . esc_html__('Advanced: Auto-scaling costs (staff steps up with headcount)', 'vms') . '</strong></summary>';
  echo '<p class="description">' . esc_html__('Use this when you want staffing to auto-increment with headcount (e.g., 1 bartender per 100 people). Unit cost should represent total pay per staff member for the event.', 'vms') . '</p>';
  vms_budget_calculator_render_autoscale_items($in['autoscale_items']);
  echo '</details>';

  echo '<p>';
  echo '<button class="button button-primary" type="submit" name="vms_budget_action" value="calculate">' . esc_html__('Calculate', 'vms') . '</button>';
  echo '</p>';

  echo '</form>';

  if ($did_calc && is_array($results)) {
    $n = (int) $results['tickets_sold'];
    $headcount = (int) $results['headcount'];

    echo '<hr>';
    echo '<h2 class="title">' . esc_html__('Results', 'vms') . '</h2>';

    echo '<p><strong>' . esc_html__('Expected headcount:', 'vms') . '</strong> ' . esc_html(vms_budget_fmt_int($headcount)) . '</p>';

    // Per-event summary
    echo '<h3>' . esc_html__('Per event', 'vms') . '</h3>';
    echo '<table class="widefat striped">';
    echo '<tbody>';
    echo '<tr><th>' . esc_html__('Gross revenue', 'vms') . '</th><td>' . esc_html(vms_budget_fmt_money($results['gross_revenue'])) . '</td></tr>';
    echo '<tr><th>' . esc_html__('Fees (estimated)', 'vms') . '</th><td>' . esc_html(vms_budget_fmt_money($results['fee_percent_amt'] + $results['fee_fixed_amt'])) . '</td></tr>';
    echo '<tr><th>' . esc_html__('Other costs (fixed)', 'vms') . '</th><td>' . esc_html(vms_budget_fmt_money($results['fixed_costs_other'])) . '</td></tr>';
    echo '<tr><th>' . esc_html__('Other costs (per-ticket)', 'vms') . '</th><td>' . esc_html(vms_budget_fmt_money($results['variable_costs'])) . '</td></tr>';
    echo '<tr><th>' . esc_html__('Auto-scaling costs (staff rules)', 'vms') . '</th><td>' . esc_html(vms_budget_fmt_money($results['autoscale_costs'])) . '</td></tr>';
    echo '<tr><th>' . esc_html__('Music Vendor pay', 'vms') . '</th><td>' . esc_html(vms_budget_fmt_money($in['band_pay'])) . '</td></tr>';
    echo '<tr><th>' . esc_html__('Net profit', 'vms') . '</th><td><strong>' . esc_html(vms_budget_fmt_money($results['net_profit'])) . '</strong></td></tr>';
    echo '</tbody>';
    echo '</table>';

    if (!empty($results['autoscale_rows'])) {
      echo '<h4>' . esc_html__('Auto-scaling breakdown', 'vms') . '</h4>';
      echo '<table class="widefat striped"><thead><tr>';
      echo '<th>' . esc_html__('Line item', 'vms') . '</th>';
      echo '<th>' . esc_html__('# staff', 'vms') . '</th>';
      echo '<th>' . esc_html__('Unit cost', 'vms') . '</th>';
      echo '<th>' . esc_html__('Total', 'vms') . '</th>';
      echo '</tr></thead><tbody>';

      foreach ($results['autoscale_rows'] as $r) {
        echo '<tr>';
        echo '<td>' . esc_html((string) $r['label']) . '</td>';
        echo '<td>' . esc_html(vms_budget_fmt_int((int) $r['qty'])) . '</td>';
        echo '<td>' . esc_html(vms_budget_fmt_money((float) $r['unit_cost'])) . '</td>';
        echo '<td><strong>' . esc_html(vms_budget_fmt_money((float) $r['total'])) . '</strong></td>';
        echo '</tr>';
      }

      echo '</tbody></table>';
    }

    // Period totals if enabled
    if ($in['mode'] === 'period' && $in['events_count'] > 1) {
      $k = (int) $in['events_count'];
      echo '<h3>' . sprintf(esc_html__('Period totals (%d events)', 'vms'), $k) . '</h3>';
      echo '<table class="widefat striped"><tbody>';
      echo '<tr><th>' . esc_html__('Gross revenue', 'vms') . '</th><td>' . esc_html(vms_budget_fmt_money($results['gross_revenue'] * $k)) . '</td></tr>';
      echo '<tr><th>' . esc_html__('Fees (estimated)', 'vms') . '</th><td>' . esc_html(vms_budget_fmt_money(($results['fee_percent_amt'] + $results['fee_fixed_amt']) * $k)) . '</td></tr>';
      echo '<tr><th>' . esc_html__('Other costs (fixed)', 'vms') . '</th><td>' . esc_html(vms_budget_fmt_money($results['fixed_costs_other'] * $k)) . '</td></tr>';
      echo '<tr><th>' . esc_html__('Other costs (per-ticket)', 'vms') . '</th><td>' . esc_html(vms_budget_fmt_money($results['variable_costs'] * $k)) . '</td></tr>';
      echo '<tr><th>' . esc_html__('Auto-scaling costs (staff rules)', 'vms') . '</th><td>' . esc_html(vms_budget_fmt_money($results['autoscale_costs'] * $k)) . '</td></tr>';
      echo '<tr><th>' . esc_html__('Music Vendor pay', 'vms') . '</th><td>' . esc_html(vms_budget_fmt_money($in['band_pay'] * $k)) . '</td></tr>';
      echo '<tr><th>' . esc_html__('Net profit', 'vms') . '</th><td><strong>' . esc_html(vms_budget_fmt_money($results['net_profit'] * $k)) . '</strong></td></tr>';
      echo '</tbody></table>';
    }

    // Decision helpers
    echo '<h3>' . esc_html__('Decision helpers', 'vms') . '</h3>';
    echo '<table class="widefat striped"><tbody>';

    echo '<tr><th>' . esc_html__('Max Music Vendor pay (still hits target profit)', 'vms') . '</th><td><strong>' . esc_html(vms_budget_fmt_money($results['max_band_pay_for_target'])) . '</strong></td></tr>';

    if ($results['ticket_price_needed'] !== null) {
      echo '<tr><th>' . esc_html__('Ticket price needed (hits target profit, at this ticket count)', 'vms') . '</th><td><strong>' . esc_html(vms_budget_fmt_money($results['ticket_price_needed'])) . '</strong></td></tr>';
    } else {
      echo '<tr><th>' . esc_html__('Ticket price needed (hits target profit)', 'vms') . '</th><td>' . esc_html__('Not possible (fee percent is 100% or higher)', 'vms') . '</td></tr>';
    }

    if ($results['break_even_price'] !== null) {
      echo '<tr><th>' . esc_html__('Break-even ticket price (at this ticket count)', 'vms') . '</th><td>' . esc_html(vms_budget_fmt_money($results['break_even_price'])) . '</td></tr>';
    }

    if ($results['tickets_needed_target'] !== null) {
      echo '<tr><th>' . esc_html__('Tickets needed (hits target profit, at this ticket price)', 'vms') . '</th><td><strong>' . esc_html(vms_budget_fmt_int((int) $results['tickets_needed_target'])) . '</strong></td></tr>';
    } else {
      echo '<tr><th>' . esc_html__('Tickets needed (hits target profit)', 'vms') . '</th><td>' . esc_html__('Not found within search limit (raise your ticket price or lower costs)', 'vms') . '</td></tr>';
    }

    if ($results['tickets_needed_be'] !== null) {
      echo '<tr><th>' . esc_html__('Break-even tickets (at this ticket price)', 'vms') . '</th><td>' . esc_html(vms_budget_fmt_int((int) $results['tickets_needed_be'])) . '</td></tr>';
    } else {
      echo '<tr><th>' . esc_html__('Break-even tickets', 'vms') . '</th><td>' . esc_html__('Not found within search limit (raise your ticket price or lower costs)', 'vms') . '</td></tr>';
    }

    echo '</tbody></table>';

    // Quick ladder
    echo '<h3>' . esc_html__('Quick scenarios (ticket count ladder)', 'vms') . '</h3>';
    $ladder = array(
      array('label' => '50%',  'mult' => 0.50),
      array('label' => '75%',  'mult' => 0.75),
      array('label' => '100%', 'mult' => 1.00),
      array('label' => '125%', 'mult' => 1.25),
    );

    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Tickets', 'vms') . '</th>';
    echo '<th>' . esc_html__('Expected heads', 'vms') . '</th>';
    echo '<th>' . esc_html__('Profit', 'vms') . '</th>';
    echo '<th>' . esc_html__('Ticket price to hit target profit', 'vms') . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($ladder as $row) {
      $t = (int) round($n * $row['mult']);
      $t = max(0, $t);

      $r = vms_budget_calculator_profit_for_tickets($in, $t);

      $p_need = vms_budget_calculator_ticket_price_needed_for($in, max(1, $t), (float) $in['target_profit']);

      $heads = isset($r['headcount']) ? (int) $r['headcount'] : 0;

      echo '<tr>';
      echo '<td>' . esc_html($row['label'] . ' = ' . (string) $t) . '</td>';
      echo '<td>' . esc_html(vms_budget_fmt_int($heads)) . '</td>';
      echo '<td><strong>' . esc_html(vms_budget_fmt_money($r['net_profit'])) . '</strong></td>';
      echo '<td>' . ($p_need === null ? esc_html__('Not possible', 'vms') : esc_html(vms_budget_fmt_money($p_need))) . '</td>';
      echo '</tr>';
    }

    echo '</tbody></table>';

    if (is_array($annual_results)) {
      $year = (int) ($annual_results['year'] ?? (int) wp_date('Y'));
      $rev_pct = isset($annual_results['revenue_goal_progress_pct']) ? $annual_results['revenue_goal_progress_pct'] : null;
      $profit_pct = isset($annual_results['profit_goal_progress_pct']) ? $annual_results['profit_goal_progress_pct'] : null;

      $rev_fill = ($rev_pct === null) ? 0.0 : max(0.0, min(100.0, (float) $rev_pct));
      $profit_fill = ($profit_pct === null) ? 0.0 : max(0.0, min(100.0, (float) $profit_pct));

      $rev_state = 'is-na';
      if ($rev_pct !== null) {
        if ((float) $rev_pct >= 100.0) $rev_state = 'is-good';
        elseif ((float) $rev_pct >= 75.0) $rev_state = 'is-warning';
        else $rev_state = 'is-risk';
      }

      $profit_state = 'is-na';
      if ($profit_pct !== null) {
        if ((float) $profit_pct >= 100.0) $profit_state = 'is-good';
        elseif ((float) $profit_pct >= 75.0) $profit_state = 'is-warning';
        else $profit_state = 'is-risk';
      }

      $rev_gap = $annual_results['revenue_goal_gap'] ?? null;
      $profit_gap = $annual_results['profit_goal_gap'] ?? null;

      $rev_gap_text = __('No goal set', 'vms');
      if ($rev_gap !== null) {
        $rev_gap_f = (float) $rev_gap;
        if ($rev_gap_f > 0) $rev_gap_text = sprintf(__('Need %s more', 'vms'), vms_budget_fmt_money($rev_gap_f));
        else $rev_gap_text = sprintf(__('Goal met (+%s)', 'vms'), vms_budget_fmt_money(abs($rev_gap_f)));
      }

      $profit_gap_text = __('No goal set', 'vms');
      if ($profit_gap !== null) {
        $profit_gap_f = (float) $profit_gap;
        if ($profit_gap_f > 0) $profit_gap_text = sprintf(__('Need %s more', 'vms'), vms_budget_fmt_money($profit_gap_f));
        else $profit_gap_text = sprintf(__('Goal met (+%s)', 'vms'), vms_budget_fmt_money(abs($profit_gap_f)));
      }

      echo '<h3>' . esc_html__('Annual goals + progress + forecast', 'vms') . '</h3>';
      echo '<p class="description">' . esc_html(sprintf(__('Live forecast for %d from current Event Plan rows. Past ticket revenue uses explicit ticket-stats snapshots when available.', 'vms'), $year)) . '</p>';
      echo '<p class="description">' . (!empty($annual_results['include_drafts']) ? esc_html__('Inclusion mode: includes Draft/Ready/Tentative/Confirmed + Published (Cancelled excluded).', 'vms') : esc_html__('Inclusion mode: Published-only (Cancelled excluded).', 'vms')) . '</p>';

      echo '<div class="vms-bcalc-progress-grid">';
      echo '<div class="vms-bcalc-progress-card ' . esc_attr($rev_state) . '">';
      echo '<div class="vms-bcalc-progress-label">' . esc_html__('Revenue Goal Progress', 'vms') . '</div>';
      echo '<div class="vms-bcalc-progress-value"><strong>' . esc_html(vms_budget_fmt_money((float) ($annual_results['forecast_gross_revenue'] ?? 0.0))) . '</strong>';
      echo ' / ' . esc_html(vms_budget_fmt_money((float) ($annual_results['goal_revenue'] ?? 0.0))) . '</div>';
      echo '<progress class="vms-bcalc-progress-bar" max="100" value="' . esc_attr(number_format((float) $rev_fill, 2, '.', '')) . '"></progress>';
      echo '<div class="vms-bcalc-progress-meta">' . esc_html($rev_pct === null ? __('No revenue goal entered', 'vms') : (number_format((float) $rev_pct, 1) . '%')) . ' · ' . esc_html($rev_gap_text) . '</div>';
      echo '</div>';

      echo '<div class="vms-bcalc-progress-card ' . esc_attr($profit_state) . '">';
      echo '<div class="vms-bcalc-progress-label">' . esc_html__('Profit Goal Progress', 'vms') . '</div>';
      echo '<div class="vms-bcalc-progress-value"><strong>' . esc_html(vms_budget_fmt_money((float) ($annual_results['forecast_net_profit'] ?? 0.0))) . '</strong>';
      echo ' / ' . esc_html(vms_budget_fmt_money((float) ($annual_results['goal_profit'] ?? 0.0))) . '</div>';
      echo '<progress class="vms-bcalc-progress-bar" max="100" value="' . esc_attr(number_format((float) $profit_fill, 2, '.', '')) . '"></progress>';
      echo '<div class="vms-bcalc-progress-meta">' . esc_html($profit_pct === null ? __('No profit goal entered', 'vms') : (number_format((float) $profit_pct, 1) . '%')) . ' · ' . esc_html($profit_gap_text) . '</div>';
      echo '</div>';
      echo '</div>';

      echo '<table class="widefat striped"><tbody>';
      echo '<tr><th>' . esc_html__('Included Event Plans', 'vms') . '</th><td>' . esc_html(vms_budget_fmt_int((int) ($annual_results['event_count'] ?? 0))) . '</td></tr>';
      echo '<tr><th>' . esc_html__('Past / future plans', 'vms') . '</th><td>' . esc_html(vms_budget_fmt_int((int) ($annual_results['past_event_count'] ?? 0)) . ' / ' . vms_budget_fmt_int((int) ($annual_results['future_event_count'] ?? 0))) . '</td></tr>';
      echo '<tr><th>' . esc_html__('Projected gross revenue', 'vms') . '</th><td><strong>' . esc_html(vms_budget_fmt_money((float) ($annual_results['forecast_gross_revenue'] ?? 0.0))) . '</strong></td></tr>';
      echo '<tr><th>' . esc_html__('Projected total costs', 'vms') . '</th><td>' . esc_html(vms_budget_fmt_money((float) ($annual_results['forecast_total_costs'] ?? 0.0))) . '</td></tr>';
      echo '<tr><th>' . esc_html__('Projected net profit', 'vms') . '</th><td><strong>' . esc_html(vms_budget_fmt_money((float) ($annual_results['forecast_net_profit'] ?? 0.0))) . '</strong></td></tr>';
      echo '<tr><th>' . esc_html__('Music Vendor pay using Event Plan values', 'vms') . '</th><td>' . esc_html(vms_budget_fmt_int((int) ($annual_results['events_using_plan_band_pay'] ?? 0))) . '</td></tr>';
      echo '</tbody></table>';

      echo '<h4>' . esc_html__('Past revenue context', 'vms') . '</h4>';
      echo '<table class="widefat striped"><tbody>';
      echo '<tr><th>' . esc_html__('Past ticket revenue (actual snapshots)', 'vms') . '</th><td><strong>' . esc_html(vms_budget_fmt_money((float) ($annual_results['past_actual_ticket_revenue'] ?? 0.0))) . '</strong></td></tr>';
      echo '<tr><th>' . esc_html__('Past events with actual ticket stats', 'vms') . '</th><td>' . esc_html(vms_budget_fmt_int((int) ($annual_results['past_actual_ticket_events'] ?? 0))) . '</td></tr>';
      echo '<tr><th>' . esc_html__('Past events missing ticket stats', 'vms') . '</th><td>' . esc_html(vms_budget_fmt_int((int) ($annual_results['past_missing_ticket_stats_events'] ?? 0))) . '</td></tr>';
      echo '<tr><th>' . esc_html__('Prior year actual ticket revenue (full year)', 'vms') . '</th><td>' . esc_html(vms_budget_fmt_money((float) ($annual_results['prior_year_actual_ticket_revenue'] ?? 0.0))) . ' (' . esc_html(vms_budget_fmt_int((int) ($annual_results['prior_year_actual_ticket_events'] ?? 0))) . '/' . esc_html(vms_budget_fmt_int((int) ($annual_results['prior_year_total_events'] ?? 0))) . ' ' . esc_html__('events with stats', 'vms') . ')</td></tr>';
      if ($year === (int) wp_date('Y')) {
        echo '<tr><th>' . esc_html__('Prior year to-date ticket revenue', 'vms') . '</th><td>' . esc_html(vms_budget_fmt_money((float) ($annual_results['prior_year_to_date_actual_ticket_revenue'] ?? 0.0))) . ' (' . esc_html(vms_budget_fmt_int((int) ($annual_results['prior_year_to_date_actual_ticket_events'] ?? 0))) . ' ' . esc_html__('events with stats', 'vms') . ')</td></tr>';
      }
      echo '</tbody></table>';
      echo '<p class="description">' . esc_html__('Past ticket revenue context depends on explicit "Refresh ticket stats" snapshots on Event Plans. Missing snapshots fall back to modeled assumptions in forecast lines.', 'vms') . '</p>';

      $forecast_rows = isset($annual_results['rows']) && is_array($annual_results['rows']) ? $annual_results['rows'] : array();
      if (!empty($forecast_rows)) {
        echo '<h4>' . esc_html__('Live Event Plan forecast details', 'vms') . '</h4>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Date', 'vms') . '</th>';
        echo '<th>' . esc_html__('Event Plan', 'vms') . '</th>';
        echo '<th>' . esc_html__('Status', 'vms') . '</th>';
        echo '<th>' . esc_html__('Music Vendor pay', 'vms') . '</th>';
        echo '<th>' . esc_html__('Ticket revenue source', 'vms') . '</th>';
        echo '<th>' . esc_html__('Forecast net', 'vms') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($forecast_rows as $row) {
          $plan_label = (string) ($row['title'] ?? '');
          if ($plan_label === '') $plan_label = sprintf(__('Event Plan #%d', 'vms'), (int) ($row['plan_id'] ?? 0));
          $date_label = (string) ($row['event_date'] ?? '');
          $edit_link = (string) ($row['edit_link'] ?? '');

          echo '<tr>';
          echo '<td>' . esc_html($date_label) . '</td>';
          if ($edit_link !== '') {
            echo '<td><a href="' . esc_url($edit_link) . '">' . esc_html($plan_label) . '</a></td>';
          } else {
            echo '<td>' . esc_html($plan_label) . '</td>';
          }
          echo '<td>' . esc_html((string) ($row['status_label'] ?? '')) . '</td>';
          echo '<td>' . esc_html(vms_budget_fmt_money((float) ($row['band_pay'] ?? 0.0))) . '<br><span class="vms-bcalc-source">' . esc_html((string) ($row['band_pay_source_label'] ?? '')) . '</span></td>';
          echo '<td><span class="vms-bcalc-source">' . esc_html((string) ($row['ticket_source_label'] ?? '')) . '</span><br>' . esc_html(vms_budget_fmt_money((float) ($row['ticket_revenue'] ?? 0.0))) . '</td>';
          echo '<td><strong>' . esc_html(vms_budget_fmt_money((float) ($row['net_profit'] ?? 0.0))) . '</strong></td>';
          echo '</tr>';
        }

        echo '</tbody></table>';
        $rows_hidden = (int) ($annual_results['rows_hidden'] ?? 0);
        if ($rows_hidden > 0) {
          echo '<p class="description">' . esc_html(sprintf(__('Showing first %d plans. %d additional plans are included in totals.', 'vms'), count($forecast_rows), $rows_hidden)) . '</p>';
        }
      } else {
        echo '<div class="notice notice-info"><p>' . esc_html__('No Event Plans matched this annual forecast window and inclusion mode.', 'vms') . '</p></div>';
      }
    }
  }

  echo '</div>';
}
